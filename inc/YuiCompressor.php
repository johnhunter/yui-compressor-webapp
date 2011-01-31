<?php

/*
	YuiCompressor.php - A web UI to the YUI Compressor by Julien Lecomte.

	Author       John Hunter for johnhunter.info
	Created       2011-01-31
	Copyright (c) 2011 johnhunter.info. All rights reserved. 
	
	Requires >= PHP 5.2.0
	
	Based on the YUI Compressor which requires Java 1.4
	http://yuilibrary.com/projects/yuicompressor/
	
	NOTE: This is a complete rewrite of an original solution built by J Hunter & Andrew Smith while at Syzygy UK Ltd.
	
	
	TODO: Integrate http://www.julienlecomte.net/yuicompressor/README for hints etc.
	
*/

/**
* YuiCompressor
*/
class YuiCompressor {
	
	
	protected $tmpDir;
	protected $outputDir;
	protected $root;
	protected $files;
	protected $output;
	protected $skipMinFiles;
	protected $compressorOptions;
	protected $ext;
	protected $eof;
	public $fileHtmlLink;
	public $report;
	
	
	function __construct() {
		
		// chown these to webserver and chmod 0755
		$this->tmpDir = 'tmp';
		$this->outputDir = 'download';
		
		
		$this->root = dirname($_SERVER['SCRIPT_FILENAME']) . '/';
		$this->files = array();
		$this->output = '';
		$this->skipMinFiles = true;
		$this->eof = "\n";// user can change this in options
		$this->compressorOptions = '';
		$this->report = '';
		$this->fileHtmlLink = '';
		
	}
	
	
	public function run ($postFiles = null, $postData = null) {
		
		if (!isset($postFiles)) $postFiles =& $_FILES;
		if (!isset($postData)) $postData =& $_POST;
		
		$this->setOptions($postData);
		
		$this->getUpload($this->sortFiles($postFiles['upload'], $postData['fileorder']));
		
		foreach($this->files as $file) {
			
			$this->makeCompressedFile($file);
			// ...calls createFileReport
			
		}
		
		if (!empty($this->output)) {
			$this->saveCompressedFile($postData['name'], $postData['file-header']);
		}
	}
	
	
	protected function setOptions (&$data) {
		
		switch ($data['eol_style']) {
			case 'crlf':
				$this->eof = "\r\n";
				break;
			case 'cr':
				$this->eof = "\r";
				break;
			default:
				$this->eof = "\n";
				break;
		}
		
		$this->skipMinFiles = !empty($data['skipmin']);// TODO harmonise field names.
		$this->ext = '.' . $this->validateExtn($data['name-suffix']);
		
		/*	
			Compressor options:
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
			
		*/
		
		$options = "";
		$options .= "--charset UTF-8 ";// NOTE: charset is hardwired
		
		$breakLength = $data['line_break'];
		
		if(is_numeric($breakLength)) {
			$breakLength = min(intval($breakLength), 10000);// sanitize input
			$options .= "--line-break $breakLength ";
		}
		
		if($data['verbose']) $options .= "--verbose ";
		if($data['nomunge']) $options .= "--nomunge ";
		if($data['preserve_semi']) $options .= "--preserve-semi ";
		if($data['disable_optimizations']) $options .= "--disable-optimizations ";
		
		$this->compressorOptions = $options;
	}
	
	
	protected function getUpload (&$uploadFiles) {
		
		if($uploadFiles) {
			
			// Check how many files are being uploaded
			foreach($uploadFiles['name'] as $key => $origName) {
				
				$error = $uploadFiles['error'][$key];
				$nameInfo = pathinfo($origName);
				
				if ($error == UPLOAD_ERR_OK) {
					
					$name = $nameInfo['filename'] .'.'. $this->validateExtn($nameInfo['extension']);
					$path = "$this->tmpDir/$name";
					
					move_uploaded_file($uploadFiles['tmp_name'][$key], $path);

					if(file_exists($path)) {
						$fileInfo = pathinfo($name);
						$this->files[] = $name;
					}
				}
				else {
					// warn user of errors
					switch ($error) {
						case UPLOAD_ERR_INI_SIZE:
						case UPLOAD_ERR_FORM_SIZE:
							$this->warnUser("The file {$nameInfo['basename']} is too large and has been ignored. ");
							break;
							
						case UPLOAD_ERR_PARTIAL:
							$this->warnUser("The file {$nameInfo['basename']} was not uploaded fully and has been ignored. ");
							break;
							
						case UPLOAD_ERR_NO_FILE:
							$this->warnUser("The file {$nameInfo['basename']} was missing and has been ignored. ");
							break;

						default:
							$this->warnUser("Some error occured with the upload of {$nameInfo['basename']} (error $error). ");
							break;
					}
				}
			}
		}
	}

	
	protected function makeCompressedFile ($file) {
		
		$fileName = pathinfo($file, PATHINFO_FILENAME);
		$tmpPath = $this->root . $this->tmpDir . '/';
		$input = $tmpPath . $file;
		$compression = 'none';
		
		
		$dontCompress = ($this->skipMinFiles and preg_match('/.+(-|\.|_)min$/', $fileName));
		
		if ($dontCompress) {
			$output = $input;
			$err = 0;
		}
		else {
			$output = $tmpPath . uniqid($fileName) . $this->ext;
			
			$cmd = "java -jar {$this->root}yuicompressor-2.4.2.jar {$this->compressorOptions} -o $output $input 2>&1";
			
			echo "CMD is: $cmd\n";
			exit;
			
			exec($cmd, $out, $err);
		}
		
		
		
		if ($err === 0) {
			$compression = round((filesize($output) / filesize($input)) * 100, 0) . '%';
			
			$this->output .= file_get_contents($output);
			$this->output .= $this->eof.$this->eof;
			unlink($output);
		}
		
		unlink($input);
		
		$this->createFileReport($file, $compression, $out, $err);	
	}
	
	
	protected function saveCompressedFile ($name = '', $header = '') {
		
		$data = $this->output;
		
		$this->clearOldFiles();
		
		// Create filename
		if (empty($name)) $name =  'lib_' . date('Ymd_His');
		$name .= $this->ext;

		// Make New Directory
		$outDir = "$this->$outputDir/" . date('Ymd_His') . '/';
		$outPath = $outDir . $name;
		
		if($rs = @mkdir($outDir, 0755)) {
			
			file_put_contents($outPath, $this->processVar($name_set['file-header']) . $this->eof.$this->eof . $this->output . $this->eof);

			if(file_exists($outPath)) {
				// url for file
				$this->fileHtmlLink = '<a href="' . $outPath . '" target="_blank">'. $name .'</a>';
				
			}
		}
		
		return $this->fileHtmlLink;
	}

	
	protected function createFileReport ($fileName, $compression, $out, $err) {
		$report = '';
		$count = sizeof($out);
		
		if ($err === 0) {
			$report .= "<h4>{$fileName} (compressed: {$compression})</h4>\n";
		}
		else {
			$report .= "<h4 class=\"error\">{$fileName} COMPRESSION FAILED!!<h4>";
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
					$report .= '<dd>' . $this->formatReportWarnings($line) .'</dd>';
				}
			}
			$report .= "</dl>\n";
		}
		
		
		$this->report .= $report;
	}

	
	protected function sortFiles (&$uploadFiles, &$fileOrder) {
	
		// If a fileOrder list (HTML5 file api) provided then reorder the uploaded files
		if (isset($fileOrder)) {
			$newList = Array();
			$count = 0;
			$itemIndex; // file index in FILES array based on fileorder list
			
			foreach($fileOrder as $itemName) {
				$itemIndex = array_search($itemName, $uploadFiles['name']);
				
				$newList['name'][$count]     = $uploadFiles['name'][$itemIndex];
				$newList['tmp_name'][$count] = $uploadFiles['tmp_name'][$itemIndex];
				$newList['error'][$count]    = $uploadFiles['error'][$itemIndex];
				$newList['size'][$count]     = $uploadFiles['size'][$itemIndex];
				$newList['type'][$count]     = $uploadFiles['type'][$itemIndex];
				
				$count++;
			}
			return $newList;
		}
		
		return $uploadFiles;
		
	}
	
	
	protected function validateExtn($ext) {
		switch ($ext) {
			case 'js':
			case 'css':
				break;
			default:
				$ext = 'txt';// force to a safe file extn
		}
		return $ext;
	}
	
	
	protected function formatReportWarnings ($line) {
		
		$line = htmlentities($line);
		$line = preg_replace(
			array('/---&gt; /','/ &lt;---/'),
			array('<em>','</em>'),
			$line);
			
		return $line;
	}


	// remove older comressed files from file system
	protected function clearOldFiles() {
		
		// TODO: remove dirs that are 2 days old.
		$dir = opendir($this->output_dir);
		$path = $this->output_dir . '/';
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
	protected function deleteDir($dirname) {
		if (is_dir($dirname)) {
			$dir_handle = opendir($dirname);
		}
		if (!$dir_handle) {
			return false;
		}
		while ($file = readdir($dir_handle)) {
			if ($file != "." && $file != "..") {
				$filepath = $dirname . '/' . $file;
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
	
	
	// send a message to the user.
	protected function warnUser ($message, $isFatal = false) {
		
		$message = preg_replace('/\'/', '`', $message);
		
		// send the warning to the top level window
		$js  = "top.userWarning = top.userWarning || [];\n";
		$js .= "top.userWarning.push('$message');\n";
		
		if ($isFatal) {
			
			// fire warning now because we're going to exit.
			$js .= "top.showWarning && top.showWarning(true);\n";
			echo "\n<script>\n$js</script>\n";
			
			// TODO: format fatal frame nicely?
			exit;
			
		}
		else {
			echo "\n<script>\n$js</script>\n";
		}	
	}
	
}





?>