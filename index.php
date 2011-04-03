<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
    <title>Javascript / CSS Compressor</title>
	<!--
		
		This application uses the HTML5 file api and requires at least:
		- Firefox 3.6
		- Chrome 9
		- Safari 5
		
	-->
    <link rel="stylesheet" type="text/css" href="assets/general.css" media="all">

</head>
<body>
<div id="container">
	
	<h1>Javascript / CSS Compressor</h1>
	
    <div id="main">
        <form method="post" enctype="multipart/form-data" target="result-frame" action="compressor.php">
			
			<div id="upload-wrapper">
				<button>select files for upload&hellip;</button>
			    <input type="file" multiple="true" id="upload" name="upload[]">
			</div>


			<div id="filenames">
				<!--filenames dynamically created here-->
			</div>
			
			
			<div id="meta">
				 <p>
	                <label for="name">Name for compressed file:</label>
	                <input type="text" id="name" name="name" value="" maxlength="40">
					<strong>.</strong> <input id="name-suffix" readonly="readonly" name="name-suffix" maxlength="3">
		        </p>
			</div>
			
			<a id="options-control" class="control" href="#show / hide options"><strong>options</strong></a>
			<div id="options" style="display:none">
				
				<p>
					<label for="file-header" title="You can edit the header or use the defaut. [file list] will substitute the compressed file names, [date:time] will substitute the comression timestamp.">Header comment:</label>
					<textarea id="file-header" name="file-header" rows="4" cols="40" wrap="off">/*
	Compressed from: [file list]
	On: [date:time]
	For licences see original source files.
*/</textarea>
				</p>
				
				<fieldset>
					<legend>General options</legend>
					<label for="verbose">
						<input type="checkbox" id="verbose" name="verbose" checked="checked" value="true">
						display informational messages and warnings 
					</label>
					<label for="line-break">Insert line breaks: &nbsp;
						<select name="line-break" id="line-break">
							<option value="">none</option>
							<option value="0">after each statement / css rule</option>
							<option value="80">at column 80</option>
							<option value="120">at column 120</option>
							<option value="8000">at column 8000 (source control friendly)</option>
						</select>
					</label>
					<label for="eol-style">End of line style: &nbsp;
						<select name="eol-style" id="eol-style">
							<option value="lf">LF - Unix, OS X (preferred)</option>
							<option value="crlf">CRLF - Windows, MS-DOS</option>
							<option value="cr">CR - OS 9 (obsolete)</option>
						</select>
					</label>
					
				</fieldset>
				<fieldset>
					<legend>JavaScript options</legend>
					<label for="skip-min">
						<input type="checkbox" id="skip-min" name="skip-min" checked="checked" value="true">
						don't compress files ending 'min.js'
					</label>
					<label for="no-munge">
						<input type="checkbox" id="no-munge" name="no-munge" value="true">
						minify only - don't obfuscate local variables  
					</label>
					<label for="preserve-semi">
						<input type="checkbox" id="preserve-semi" name="preserve-semi" value="true">
						preserve semicolons
					</label>
					<label for="disable-optimizations">
						<input type="checkbox" id="disable-optimizations" name="disable-optimizations" value="true">
						disable micro-optimizations (e.g. pre-processing string concatenations)
					</label>
				</fieldset>
			</div>
			
            <p class="action">
				<button id="compress-button" name="submit" value="compress files"><b>compress files</b></button>
            </p>

        </form>
		
        <iframe id="result-frame" name="result-frame" src="" allowtransparency="true" style="display:none"></iframe>
    </div>

	<div id="credits">
		
		<p class="yui">
			Powered by <img class="yui" src="assets/yahoo.gif" width="140" height="33" alt="Yahoo">
			<a href="http://developer.yahoo.com/yui/compressor/">YUI compressor</a>
		</p>
		
		<p class="licence">
			Licenced under the Simplified BSD License: &copy; 2011 John Hunter. All rights reserved.<br>
			<a href="https://github.com/johnhunter/yui-compressor-webapp">https://github.com/johnhunter/yui-compressor-webapp</a>
		</p>
		
	</div>

	


</div>


<script id="fileRowTmpl" type="text/x-jquery-tmpl">
    <p class="row">
		<code>${name}</code>
		<small>${kbSize}k</small>
		<input type="hidden" name="fileorder[]" value="${name}">
		<a class="remove-field control" href="#remove">remove</a>
	</p>
</script>


</body>

<!--
<script src="assets/jquery-1.4.2.min.js"></script>
<script src="assets/jquery.tmpl.min.js"></script>
<script src="assets/jquery-ui-1.8.5.custom.min.js"></script>
-->

<script src="assets/lib.js"></script>
<script src="assets/general.js"></script>

</html>
