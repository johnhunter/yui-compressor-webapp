<?php
/*
	Web implementation of the Yui compressor.
	author: J. Hunter / A. Smith
	Modified: 2010-07-25
	- added compression options
	- added compression reporting
	- added cleanup to remove old compressed files
	- usability changes to UI
	- improved client-side error reporting
	- fixed bug with file permissions and changed output dir name
	- changed Yui member visability, renamed some vars and moved to K&R syntax
	- added compressor error to report to avoid silent failure
	- added an option to not compress files ending [-._]min.js
	
	TODO: properly sanitize all user input
*/

class Yui {
	
	// chown these to webserver and chmod 0755
	protected $tmp_dir = 'tmp';
	protected $output_dir = 'download';
	
	protected $fp = array();
	protected $fileList;
	protected $ext;
	public $report = ''; // store warnings from compressor
	
	// Execute all code
	public function execute($data) {
		
		if($this->upload($data)) {
			$this->write($this->fp['content'], $data);
		}
	}

	// Upload file(s)
	protected function upload($data) {
		$newData = $data['upload'];
		if($newData) {
			$options = $this->getOptions($data);
			$skipMinFiles = $data['skipmin'];
			
			// Check how many files are being uploaded
			foreach($newData['error'] as $key => $error) {
				if ($error == UPLOAD_ERR_OK) {
					$tmp_name = $newData['tmp_name'][$key];
					$name = $newData['name'][$key];

					move_uploaded_file($tmp_name, "$this->tmp_dir/$name");

					if(file_exists($this->tmp_dir . DS . $name)) {
						$fileInfo = pathinfo($name);
						$this->fileList[] = $fileInfo['basename'];
						$this->copy($name, $options, $skipMinFiles);
					}
				}
			}
		}
		return true;
	}
	
	// CHANGED: Get the options string to send options to the compressor JH 2010-03-28.
	protected function getOptions($data) {
		/*	
			Global Options
			-h, --help Displays this information
			--type Specifies the type of the input file
			--charset Read the input file using
			--line-break Insert a line break after the specified column number
			-v, --verbose Display informational messages and warnings
			-o Place the output into . Defaults to stdout.

			JavaScript Options
			--nomunge Minify only, do not obfuscate
			--preserve-semi Preserve all semicolons
			--disable-optimizations Disable all micro optimizations
			
			Note: charset is hardwired
			
			TODO: Consider making options sticky.
			
		*/
		$options = "";
		$options .= "--charset UTF-8 ";
		
		$breakLength = $data['line_break']
		if(is_numeric($breakLength)) {
			$breakLength = min(intval($breakLength), 10000);// sanitize input
			$options .= "--line-break " . $breakLength . ' ';
		}
		
		if($data['verbose']) $options .= "--verbose ";
		if($data['nomunge']) $options .= "--nomunge  ";
		if($data['preserve_semi']) $options .= "--preserve-semi ";
		if($data['disable_optimizations']) $options .= "--disable-optimizations ";
		
		return $options;
	}
	
	// Copy content
	protected function copy($data, $options, $skipMinFiles) {
		$PathArray = explode('/', $_SERVER['SCRIPT_FILENAME']);
		$fileInfo = pathinfo($data);
		$ext = $fileInfo['extension'];
		$compression = 'none';
		
		// sanitize input
		switch ($ext) {
			case 'js':
			case 'css':
				break;
			default:
				$ext = 'txt';
		}
		$this->ext = '.' . $ext;

		array_pop($PathArray);
		$dir = implode(DS, $PathArray) . DS;
		$input = $dir . $this->tmp_dir . DS . $data;
		
		
		$dontCompress = false;
		if ($skipMinFiles and preg_match('/.+(-|\.|_)min$/', $fileInfo['filename'])) {
			$dontCompress = true;
		}
		
		if ($dontCompress) {
			$output = $input;
			$err = 0;
		}
		else {
			$output = $dir . $this->tmp_dir . DS . uniqid($fileInfo['filename']) . $this->ext;
			
			$cmd = "java -jar " . $dir . "yuicompressor-2.4.2.jar " . $options . "-o " . $output . " " . $input . " 2>&1";
			exec($cmd, $out, $err); // Run Compressor
			$compression = round((filesize($output) / filesize($input)) * 100, 0) . '%';
			
			unlink($input); // Delete Input File
		}
		
		
		if ($err === 0) {
			$this->fp['content'] .= file_get_contents($output);
			$this->fp['content'] .= "\n\n";

			unlink($output);
		}
		
		
		$this->createReport($data, $compression, $out, $err);

	}
	
	// Create and store the report
	protected function createReport ($fileName, $compression, $out, $err) {
		$report = '';
		
		if ($err === 0) {
			$report .= "<strong>File: {$fileName} (compressed: {$compression})</strong><br />\n";
			if (sizeof($out)) {
				$report .= " - fixing warnings will improve the quality and compressibility of your code.\n";
			}
		}
		else {
			$report .= "<strong class=\"error\">File: {$fileName} COMPRESSION FAILED!!</strong><br />";
			$report .= " - see error line numbers listed below and fix the file before compressing again.\n";
		}
		
		$report .= '<dl>';
		/*
			TODO: Improve the report parsing.
		*/
		for ($i = 0; $i < sizeof($out); $i++) {
			$line = $out[$i];
			if ($line == '') continue;
			if (strpos($line, '[WARNING]') === 0) {
				$report .= '<dt class="warning">' . htmlentities($line) .'</dt>';
			}
			elseif (strpos($line, '[ERROR]') === 0) {
				$report .= '<dt class="error">' . htmlentities($line) .'</dt>';
			}
			else {
				$report .= '<dd>' . $this->parseWarnings($line) .'</dd>';
			}
		}
		
		$report .= "</dl>\n";
		
		$this->report .= $report;
	}

	// Write file
	protected function write($data, $name_set = NULL) {
		
		$this->clearOldFiles();
		
		// Create filename
		$name = $name_set['name'] != '' ? $name_set['name'] : 'lib_' . date('Ymd_His');
		$filename = $name . $this->ext;

		// Make New Directory
		$newUploadDir = $this->output_dir . DS . date('Ymd_His') . DS;
		$rs = @mkdir($newUploadDir, 0755);// fixed mode - has to be an int
		if($rs) {
			file_put_contents($newUploadDir . $filename, $this->processVar($name_set['file-header']) . "\n\n" . $data);

			if(file_exists($newUploadDir . $filename)) {
				// Return url for file
				$this->compressedFile = '<a href="' . SYSTEM_PATH . $newUploadDir . $filename .'" target="_blank">'. $filename .'</a>';
				return $this->compressedFile;
			}
		}
	}

	// Process Variable Placeholders
	protected function processVar($content) {

		$fileCount = count($this->fileList);
		$i = 1;
		foreach($this->fileList as $theFile) {
			$theFileList .= $theFile;
			if ($fileCount > 1 && $fileCount != $i) {
				$theFileList .= ', ';
			}
			$i++;
		}

		$find = array (
			'[file list]',
			'[date:time]'
		);

		$replace = array (
			$theFileList,
			date('d/m/Y H:i')
		);

		return str_replace($find, $replace, $content);
	}
	
	// Format warning output
	protected function parseWarnings($line) {
		
		$line = htmlentities($line);
		$line = preg_replace(
			array('/---&gt; /','/ &lt;---/'),
			array('<em>','</em>'),
			$line);
			
		return $line;
	}

	// remove older comressed files from file system
	protected function clearOldFiles() {
		
		$dir = opendir($this->output_dir);
		$path = $this->output_dir . DS;
		$todayMatch = date('Ymd') . '_';
		
		while($file = readdir($dir)) {
			if (strpos($file, '.') !== 0) {
				// delete dirs not created today
				if (strpos($file, $todayMatch) === false) {
					$this->deleteDir($path . $file);
				}
			}
		}
		closedir($dir);
	}

	// Recursively empty a directory and then delete it.
	public function deleteDir($dirname) {
		if (is_dir($dirname)) {
			$dir_handle = opendir($dirname);
		}
		if (!$dir_handle) {
			return false;
		}
		while ($file = readdir($dir_handle)) {
			if ($file != "." && $file != "..") {
				if (!is_dir($dirname . DS . $file)) {
					unlink($dirname . DS . $file);
				}
				else {
					$this->deleteDir($dirname . DS . $file);
				}
			}
		}
		closedir($dir_handle);
		rmdir($dirname);
		return true;
	}
	
	// Debug
	public static function debug($data) {
		echo "<pre>";
		print_r($data);
		echo "</pre>";
	}
}


