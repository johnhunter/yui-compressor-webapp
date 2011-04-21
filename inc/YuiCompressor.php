<?php

/*
	YuiCompressor.php - A web UI to the YUI Compressor by Julien Lecomte.

	Author       John Hunter for johnhunter.info
	Created       2011-01-31
	Copyright (c) 2011 johnhunter.info. All rights reserved. 
	
	Requires: >= Java 1.4, >= PHP 5.2.0
	
	Based on the YUI Compressor
	http://yuilibrary.com/projects/yuicompressor/
	
	This is a complete rewrite based on a personal project by John Hunter & Andrew Smith while at Syzygy UK Ltd.
	
	NOTE: Css compression converts rgb(nnn,nnn,nnn) to #0x0x0x format.
	
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
	protected $eol;
	protected $keepHours;
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
		$this->eol = "\n";// user can change this in options
		$this->compressorOptions = '';
		$this->report = '';
		$this->fileHtmlLink = '';
		$this->keepHours = 1; // number of hours to keep files.
		
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
	
	public function convertEol ($content) {
		return preg_replace('/(\r?\n)|\r/', $this->eol, $content);
	}
	
		
	protected function setOptions (&$data) {
		
		switch ($data['eol-style']) {
			case 'crlf':
				$this->eol = "\r\n";
				break;
			case 'cr':
				$this->eol = "\r";
				break;
			default:
				$this->eol = "\n";
				break;
		}
		
		$this->skipMinFiles = !empty($data['skip-min']);// TODO harmonise field names.
		
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
			--no-munge Minify only, do not obfuscate
			--preserve-semi Preserve all semicolons
			--disable-optimizations Disable all micro optimizations
			
		*/
		
		$options = "";
		$options .= "--charset UTF-8 ";// NOTE: charset is hardwired
		
		$breakLength = $data['line-break'];
		
		if(is_numeric($breakLength)) {
			$breakLength = min(intval($breakLength), 10000);// sanitize input
			$options .= "--line-break $breakLength ";
		}
		
		if($data['verbose']) $options .= "--verbose ";
		if($data['no-munge']) $options .= "--no-munge ";
		if($data['preserve-semi']) $options .= "--preserve-semi ";
		if($data['disable-optimizations']) $options .= "--disable-optimizations ";
		
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
							$this->warnUser("Some error occurred with the upload of {$nameInfo['basename']} (error $error). ");
							break;
					}
				}
			}
		}
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
			
			$cmd = "java -jar {$this->root}yuicompressor-2.4.5.jar {$this->compressorOptions} -o $output $input 2>&1";
			
			exec($cmd, $out, $err);
		}
		
		
		
		if ($err === 0) {
			$compression = round((filesize($output) / filesize($input)) * 100, 0) . '%';
			
			$this->output .= file_get_contents($output);
			$this->output .= $this->eol.$this->eol;
			unlink($output);
		}
		
		if (!$dontCompress) unlink($input);
		
		$this->createFileReport($file, $compression, $out, $err);	
	}
	
	protected function saveCompressedFile ($name = '', $header = '') {
		
		$this->clearOldFiles();
		
		// Create filename
		if (empty($name)) $name =  'lib-min';
		$name .= $this->ext;

		// Make New Directory
		$outId = substr(md5($_SERVER['REMOTE_ADDR']), 0, 8);
		$outDir = "$this->outputDir/". time() ."_$outId/";
		
		$outPath = $outDir . $name;
		
		if(mkdir($outDir, 0744)) {
			
			$out  = $this->parsePlaceholderVars($header);
			$out .= $this->eol.$this->eol;
			$out .= $this->output;
			
			if(file_put_contents($outPath, $out)) {
				// url for file
				$this->fileHtmlLink = '<a href="' . $outPath . '" target="_blank">'. $name .'</a>';
			}
		}
		
		return $this->fileHtmlLink;
	}
	
	protected function parsePlaceholderVars ($content) {
		
		$this->convertEol($content);

		$fileCount = count($this->files);
		$i = 1;
		foreach($this->files as $theFile) {
			$theFileList .= $theFile;
			if ($fileCount > 1 && $fileCount != $i) {
				$theFileList .= ', ';
			}
			$i++;
		}
		
		$content = str_replace(array (
				'[file list]',
				'[date:time]'
			), array (
				$theFileList,
				date('d/m/Y H:i')
			), $content);

		return $content;
	}
	
	protected function createFileReport ($fileName, $compression, $out, $err) {
		$report = '';
		$count = sizeof($out);
		
		if ($err === 0) {
			$report .= "<h4>{$fileName} (compressed: {$compression})</h4>\n";
		}
		else {
			$report .= "<h4 class=\"error\">{$fileName} COMPRESSION FAILED!!</h4>";
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
	
	protected function validateExtn ($ext) {
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
	
	protected function clearOldFiles () {

		$path = "$this->outputDir/";
		$handle = opendir($path);
		
		$expiryHour = date("H") - $this->keepHours;
		$expiryDate = mktime($expiryHour, date("i"), date("s"), date("m") , date("d"), date("Y"));

		while($file = readdir($handle)) {
			if ($file != '.' && $file != '..') {
				
				if (is_dir($path . $file)) {
					
					$nameTokens = explode('_', $file);
					if (count($nameTokens) === 2) {
						
						$dirDate = array_shift($nameTokens);
						
						if ($dirDate < $expiryDate) {
							$this->deleteDir($path . $file);
						}
					}
				}
			}
		}
		closedir($handle);
	}
	
	// Recursively empty a directory and then delete it.
	protected function deleteDir ($dirname) {
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