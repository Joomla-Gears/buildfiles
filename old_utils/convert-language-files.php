<?php
/**
 * Akeeba Build Files
 *
 * @package    buildfiles
 * @copyright  (c) 2010-2017 Akeeba Ltd
 */

define('DS', DIRECTORY_SEPARATOR);

function scanDirectory($dir)
{
	$files = array();

	$dh = opendir($dir);
	while ($path = readdir($dh))
	{
		if (!is_file($dir . DS . $path))
		{
			continue;
		}

		$lastdot = strrpos($path, '.');
		$extension = substr($path, $lastdot + 1);
		if (strtoupper($extension) != 'INI')
		{
			continue;
		}

		$files[] = $dir . DS . $path;
	}

	return $files;
}

function parse_ini_file_php($file, $process_sections = false, $raw_data = false)
{
	$process_sections = ($process_sections !== true) ? false : true;

	if (!$raw_data)
	{
		$ini = @file($file);
	}
	else
	{
		$ini = $file;
	}
	if (count($ini) == 0)
	{
		return array();
	}

	$sections = array();
	$values = array();
	$result = array();
	$globals = array();
	$i = 0;
	foreach ($ini as $line)
	{
		$line = trim($line);
		$line = str_replace("\t", " ", $line);

		// Comments
		if (!preg_match('/^[a-zA-Z0-9[]/', $line))
		{
			continue;
		}

		// Sections
		if ($line{0} == '[')
		{
			$tmp = explode(']', $line);
			$sections[] = trim(substr($tmp[0], 1));
			$i++;
			continue;
		}

		// Key-value pair
		list($key, $value) = explode('=', $line, 2);
		$key = trim($key);
		$value = trim($value);
		if (strstr($value, ";"))
		{
			$tmp = explode(';', $value);
			if (count($tmp) == 2)
			{
				if ((($value{0} != '"') && ($value{0} != "'")) ||
					preg_match('/^".*"\s*;/', $value) || preg_match('/^".*;[^"]*$/', $value) ||
					preg_match("/^'.*'\s*;/", $value) || preg_match("/^'.*;[^']*$/", $value)
				)
				{
					$value = $tmp[0];
				}
			}
			else
			{
				if ($value{0} == '"')
				{
					$value = preg_replace('/^"(.*)".*/', '$1', $value);
				}
				elseif ($value{0} == "'")
				{
					$value = preg_replace("/^'(.*)'.*/", '$1', $value);
				}
				else
				{
					$value = $tmp[0];
				}
			}
		}
		$value = trim($value);
		$value = trim($value, "'\"");

		if ($i == 0)
		{
			if (substr($line, -1, 2) == '[]')
			{
				$globals[$key][] = $value;
			}
			else
			{
				$globals[$key] = $value;
			}
		}
		else
		{
			if (substr($line, -1, 2) == '[]')
			{
				$values[$i - 1][$key][] = $value;
			}
			else
			{
				$values[$i - 1][$key] = $value;
			}
		}
	}

	for ($j = 0; $j < $i; $j++)
	{
		if ($process_sections === true)
		{
			$result[$sections[$j]] = $values[$j];
		}
		else
		{
			$result[] = $values[$j];
		}
	}

	return $result + $globals;
}

function processFile($from, $to)
{
	echo "Processing " . basename($from) . "...\n";

	$raw_data = parse_ini_file_php($from, false, false);
	$out = '';
	foreach ($raw_data as $key => $line)
	{
		$out .= $key . '="';
		$out .= str_replace('"', '', $line); // I hate myself, I hate Joomla! for relying on the f*cking PHP INI parser :(
		$out .= "\"\n";
	}
	echo ">>> $to\n";
	file_put_contents($to, $out);
}

$base_path = dirname(__FILE__) . DS . '..' . DS . 'translations';
$target_base = dirname(__FILE__);

$files = scanDirectory($base_path . DS . 'backend');
foreach ($files as $file)
{
	processFile($file, $target_base . DS . 'backend' . DS . basename($file));
}

$files = scanDirectory($base_path . DS . 'frontend');

foreach ($files as $file)
{
	processFile($file, $target_base . DS . 'frontend' . DS . basename($file));
}
