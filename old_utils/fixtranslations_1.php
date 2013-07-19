<?php

/**
 * Fixes the translation INI files to be compatible with Joomla! 1.5 and 1.6
 */

$translationPaths = array(
	'installers/abi/installation/lang'
);

echo <<<ENDBANNER
Akeeba Translation INI Fixer
===============================================================================
Copyright (c)2011 Nicholas K. Dionysopoulos - All legal rights reserved

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.

    
ENDBANNER;

foreach($translationPaths as $root)
{
	echo "Fixing translations in $root\n";
	$files = glob($root.'/*.ini');
	if(!empty($files)) foreach($files as $file) {
		echo "\t$file\n";
		$fp = fopen($file, 'rt');
		if($fp == false) die('Could not open file.');
		$out = '';
		while(!feof($fp)) {
			$line = fgets($fp);
			$trimmed = trim($line);
			
			// Transform comments
			if(substr($trimmed,0,1) == '#') {
				$out .= ';'.substr($trimmed,1)."\n";
				continue;
			}
			
			if(substr($trimmed,0,1) == ';') {
				$out .= "$trimmed\n";
				continue;
			}
			
			// Detect blank lines
			if(empty($trimmed)) {
				$out .= "\n";
				continue;
			}
			
			// Process key-value pairs
			list($key, $value) = explode('=', $trimmed, 2);
			$value = trim($value, '"');
			$value = str_replace('\\"', "'", $value);
			$value = str_replace('"_QQ_"', "'", $value);
			$value = str_replace('"', "'", $value);
			$key = strtoupper($key);
			$key = trim($key);
			$out .= "$key=\"$value\"\n";
		}
		fclose($fp);
		
		file_put_contents($file, $out);
	}
}