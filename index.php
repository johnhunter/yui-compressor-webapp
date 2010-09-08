<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<?php

include_once "inc/core.php";

if($_POST['submit']){
    $yui = new Yui;
    $yui->execute(array_merge($_FILES, $_POST));
}

?>
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
    <title>Javascript / CSS Compressor</title>


    <link rel="stylesheet" type="text/css" href="assets/general.css" media="all" />
	<script type="text/javascript" src="assets/jquery-1.2.6.js"></script>
	<script type="text/javascript" src="assets/general.js"></script>

<!--
	<link rel="stylesheet" type="text/css" href="assets/all.css" media="all" />
	<script type="text/javascript" src="assets/all.js"></script>
-->
</head>
<body>
<div id="container">
    <div id="main">
        <h1>Javascript / CSS Compressor</h1>
        <form class="smart-form" method="post" enctype="multipart/form-data" action="./">
			
			
            <div id="files">
                <p class="row">
                    <label>
						<strong>Upload File</strong>
                    	<input type="file" name="upload[]" value="" />
						<a class="remove-field control" href="#remove">- remove</a>
					</label>
                </p>

	            <!-- further upload fields added here -->
            </div>
			
            <div id="fieldAction">
                <a id="addFileUpload" class="control" href="#add-file">+ add another file</a>
            </div>
			
			<div id="meta">
				 <p>
	                <label for="name">Name for compressed file:</label>
	                <input type="text" id="name" name="name" value="" maxlength="40" />
					<strong>.</strong> <input id="name-suffix" name="name-suffix" disabled="disabled" maxlength="3" />
		        </p>
				<p>
					<label for="file-header" title="You can edit the header or use the defaut. [file list] will substitute the compressed file names, [date:time] will substitute the comression timestamp.">Header comment:</label>
					<textarea id="file-header" name="file-header" rows="4" cols="40" wrap="off">/*
	Compressed from: [file list]
	On: [date:time]
	For licences see original source files.
*/</textarea>
				</p>
			</div>
			
			<a id="options-control" class="control" href="#show / hide options"><strong>options</strong></a>
			<div id="options" style="display:none">
				<fieldset>
					<legend>General options</legend>
					<label for="verbose">
						<input type="checkbox" id="verbose" name="verbose" checked="checked" value="true" />
						display informational messages and warnings 
					</label>
					<label for="line_break">Insert line breaks: &nbsp;
						<select name="line_break" id="line_break">
							<option value="">none</option>
							<option value="0">after each statement / css rule</option>
							<option value="120">at column 120 (readable)</option>
							<option value="8000">at column 8000 (source control friendly)</option>
						</select>
					</label>
				</fieldset>
				<fieldset>
					<legend>JavaScript options</legend>
					<label for="skipmin">
						<input type="checkbox" id="skipmin" name="skipmin" checked="checked" value="true" />
						don't compress files ending 'min.js'
					</label>
					<label for="nomunge">
						<input type="checkbox" id="nomunge" name="nomunge" value="true" />
						minify only - don't obfuscate local variables  
					</label>
					<label for="preserve_semi">
						<input type="checkbox" id="preserve_semi" name="preserve_semi" value="true" />
						preserve semicolons
					</label>
					<label for="disable_optimizations">
						<input type="checkbox" id="disable_optimizations" name="disable_optimizations" value="true" />
						disable micro-optimizations (e.g. pre-processing string concatenations)
					</label>
				</fieldset>
			</div>
			
            <p class="action">
                <input id="compress-button" type="submit" name="submit" value="compress files" />
            </p>
        </form>
		
        <?php if($yui->compressedFile){ ?>
	
        <div id="compressedFile">
            <p class="hint">Right click and save file...</p>
            <h3><?php echo 'Download file: ' . $yui->compressedFile; ?></h3>
        </div>

        <?php } ?>
		<?php if ($yui->report) { ?>
		<div id="report">
			<h3>Compression report</h3>
			<?php echo $yui->report; ?>
		</div>
		<?php } ?>
    </div>

	<p id="yui">
		Powered by <img src="assets/yahoo.gif" width="140" height="33" alt="Yahoo">
		<a href="http://developer.yahoo.com/yui/compressor/">YUI compressor</a>
	</p>


</div>
</body>
</html>
