<?php

$oldSite = '/Users/nicholas/Sites/abcom';
$newSite = '/Users/nicholas/Sites/dev15';
$folders = array(
	'administrator/components/com_akeebasubs',
	'components/com_akeebasubs',
	'media/com_akeebasubs',
	'plugins/akeebasubs',
	'plugins/akpayment'
);

function compareDirs($old, $new, $root)
{
	$files = array();
	$folders = array();
	$cmd = 'diff -rq '.escapeshellarg($old).' '.escapeshellarg($new);
	$raw_input = shell_exec($cmd);
	$lines = explode("\n", $raw_input);
	foreach($lines as $line)
	{
		$exp = "Only in $old";
		if(strpos($line, $exp) === 0) {
			$line = substr($line, strlen($exp));
			$fname = str_replace(": ", "/", $line);
			if(is_dir($old.$fname)) {
				$folders[] = $root.$fname;
			} else {
				$files[] = $root.$fname;
			}
		}
	}
	
	return array(
		'folders'		=> $folders,
		'files'			=> $files
	);
}

$remfolders = array();
$remfiles = array();

foreach($folders as $folder)
{
	$ret = compareDirs($oldSite.'/'.$folder, $newSite.'/'.$folder, $folder);
	var_dump($ret);
	$remfolders = array_merge($remfolders, $ret['folders']);
	$remfiles = array_merge($remfiles, $ret['files']);
}

echo '$removeFiles = array('."\n";
foreach($remfiles as $f) echo "\t'$f',\n";
echo ");\n";
echo '$removeFolders = array('."\n";
foreach($remfolders as $f) echo "\t'$f',\n";
echo ");\n";