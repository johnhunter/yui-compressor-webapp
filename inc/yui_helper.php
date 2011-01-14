<?php
/*
	Web implementation of the Yui compressor.
	author: J. Hunter / A. Smith
	Modified: 2011-01-08
	- added compression options
	- added compression reporting
	- added cleanup to remove old compressed files
	- usability changes to UI
	- improved client-side error reporting
	- fixed bug with file permissions and changed output dir name
	- changed Yui member visability, renamed some vars and moved to K&R syntax
	- added compressor error to report to avoid silent failure
	- added an option to not compress files ending [-._]min.js
	- added option to reorder files based on fileorder array
	- added for multifile hendling
	- rewritten to display result in iframe
	
	TODO: properly sanitize all user input
	TODO: refactor the entire php application :)
	

		
*/

class Yui {
	
	// chown these to webserver and chmod 0755
	protected $tmp_dir = 'tmp';
	protected $output_dir = 'download';
	
	protected $fp = array();
	protected $fileList;
	protected $ext;
	public $report = ''; // store warnings from compressor
	
	
	public function validateExtn($ext) {
		switch ($ext) {
			case 'js':
			case 'css':
				break;
			default:
				$ext = 'txt';
		}
		return $ext;
	}
	
	// Execute all code
	public function execute($data) {
		
		if($this->upload($data)) {
			$this->write($this->fp['content'], $data);
		}
	}

	// Upload file(s)
	protected function upload($data) {
		$upload = $data['upload'];
	
		// If a fileOrder list (HTML5 file api) provided then reorder the uploaded files
		if (isset($data['fileorder'])) {
			$newData = Array();
			$count = 0;
			$itemIndex; // file index in FILES array based on fileorder list
			
			foreach($data['fileorder'] as $itemName) {
				$itemIndex = array_search($itemName, $upload['name']);
				
				$newData['name'][$count]     = $upload['name'][$itemIndex];
				$newData['tmp_name'][$count] = $upload['tmp_name'][$itemIndex];
				$newData['error'][$count]    = $upload['error'][$itemIndex];
				$newData['size'][$count]     = $upload['size'][$itemIndex];
				$newData['type'][$count]     = $upload['type'][$itemIndex];
				
				$count++;
			}
		}
		else {
			$newData = $upload;
		}
		
		if($newData) {
			$options = $this->getOptions($data);
			$skipMinFiles = $data['skipmin'];
			
			// Check how many files are being uploaded
			foreach($newData['error'] as $key => $error) {
				if ($error == UPLOAD_ERR_OK) {
					$tmp_name = $newData['tmp_name'][$key];
					$name = $newData['name'][$key];
					$nameInfo = pathinfo($name);
					
					$name = $nameInfo['filename'] . '.' . $this->validateExtn($nameInfo['extension']);
					
					move_uploaded_file($tmp_name, "$this->tmp_dir/$name");

					if(file_exists($this->tmp_dir . DS . $name)) {
						$fileInfo = pathinfo($name);
						$this->fileList[] = $fileInfo['basename'];
						$this->compressAndConcat($name, $options, $skipMinFiles);
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
		
		$breakLength = $data['line_break'];
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
	protected function compressAndConcat($data, $options, $skipMinFiles) {
		$fileInfo = pathinfo($data);
		$dir = dirname($_SERVER['SCRIPT_FILENAME']) . DS;
		$tmpdir = $dir . $this->tmp_dir . DS;
		$input = $tmpdir . $data;
		$compression = 'none';
		
		$this->ext = '.' . $this->validateExtn($fileInfo['extension']);
		
		$dontCompress = false;
		if ($skipMinFiles and preg_match('/.+(-|\.|_)min$/', $fileInfo['filename'])) {
			$dontCompress = true;
		}
		
		if ($dontCompress) {
			$output = $input;
			$err = 0;
		}
		else {
			$output = $tmpdir . uniqid($fileInfo['filename']) . $this->ext;
			
			$cmd = "java -jar " . $dir . "yuicompressor-2.4.2.jar " . $options . "-o " . $output . " " . $input . " 2>&1";
			exec($cmd, $out, $err); // Run Compressor
			
		}
		
		if ($err === 0) {
			$compression = round((filesize($output) / filesize($input)) * 100, 0) . '%';
			
			$this->fp['content'] .= file_get_contents($output);
			$this->fp['content'] .= "\n\n";
			unlink($output);
		}
		
		unlink($input); // Delete Input File
		
		$this->createReport($data, $compression, $out, $err);

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
	
	
	// Create and store the report
	protected function createReport ($fileName, $compression, $out, $err) {
		$report = '';
		$count = sizeof($out);
		
		if ($err === 0) {
			$report .= "<h4>{$fileName} (compressed: {$compression})</h4>\n";
			if (sizeof($out)) {
				//$report .= " - fixing warnings will improve the quality and compressibility of your code.\n";
			}
		}
		else {
			$report .= "<h4 class=\"error\">{$fileName} COMPRESSION FAILED!!<h4>";
			//$report .= " - see error line numbers listed below and fix the file before compressing again.\n";
		}
		
		if ($count > 0) {
			
			$report .= '<dl>';
			/*
				TODO: Improve the report parsing.
			*/
			for ($i = 0; $i < $count; $i++) {
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
		}

		
		$this->report .= $report;
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
				$filepath = $dirname . DS . $file;
				if (!is_dir($filepath)) {
					unlink($filepath);
				}
				else {
					$this->deleteDir($filepath);
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


