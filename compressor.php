<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<title>Compressor result</title>
<?php

include 'inc/YuiCompressor.php';


$compressor = new YuiCompressor();

if ($_POST['submit']) {
	
	$compressor->run();
	
}
?>
	<link rel="stylesheet" type="text/css" href="assets/general.css" media="all">
</head>
<body>
	
	<a href="#close" class="control close">&laquo; re-upload files</a>
	
	<?php if($link = $compressor->fileHtmlLink){ ?>
    <div id="compressed-file">
        <p class="hint">Right click and save file...</p>
        <h3>Download file: <?php echo $link; ?></h3>
    </div>

    <?php } ?>
	<?php if ($report = $compressor->report) { ?>
	<div id="report">
		<h3>Compression report</h3>
		<?php echo $report; ?>
	</div>
	<?php } ?>
	
	
</body>