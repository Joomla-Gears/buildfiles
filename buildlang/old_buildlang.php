<?php
/**
 * Akeeba Build Files
 *
 * @package    buildfiles
 * @copyright  (c) 2010-2017 Akeeba Ltd
 */
require_once '../phingext/pclzip.php';

function scan($root)
{
	$ret = array();

	// Scan component frontend languages
	_mergeLangRet($ret, _scanLangDir($root . '/component/frontend'), 'frontend');

	// Scan component backend languages
	_mergeLangRet($ret, _scanLangDir($root . '/component/backend'), 'backend');

	// Scan modules, admin
	try
	{
		foreach (new DirectoryIterator($root . '/modules/admin') as $mname)
		{
			if ($mname->isDot())
			{
				continue;
			}
			if (!$mname->isDir())
			{
				continue;
			}
			$module = $mname->getFilename();
			_mergeLangRet($ret, _scanLangDir($root . '/modules/admin/' . $module), 'backend');
		}
	}
	catch (Exception $exc)
	{
		//echo $exc->getTraceAsString();
	}

	// Scan modules, site
	try
	{
		foreach (new DirectoryIterator($root . '/modules/site') as $mname)
		{
			if ($mname->isDot())
			{
				continue;
			}
			if (!$mname->isDir())
			{
				continue;
			}
			$module = $mname->getFilename();
			_mergeLangRet($ret, _scanLangDir($root . '/modules/site/' . $module), 'frontend');
		}
	}
	catch (Exception $exc)
	{
		//echo $exc->getTraceAsString();
	}

	// Scan plugins
	try
	{
		foreach (new DirectoryIterator($root . '/plugins') as $fldname)
		{
			if ($fldname->isDot())
			{
				continue;
			}
			if (!$fldname->isDir())
			{
				continue;
			}
			$path = $root . '/plugins/' . $fldname->getFilename();
			// Scan this folder for plugins
			try
			{
				foreach (new DirectoryIterator($path) as $pname)
				{
					if ($pname->isDot())
					{
						continue;
					}
					if (!$pname->isDir())
					{
						continue;
					}
					$plugin = $pname->getFilename();
					_mergeLangRet($ret, _scanLangDir($path . '/' . $plugin), 'backend');
				}
			}
			catch (Exception $exc)
			{
				//echo $exc->getTraceAsString();
			}
		}
	}
	catch (Exception $exc)
	{
		//echo $exc->getTraceAsString();
	}

	return $ret;
}

function _mergeLangRet(&$ret, $temp, $area = 'frontend')
{
	foreach ($temp as $lang => $files)
	{
		$existing = array();
		if (array_key_exists($lang, $ret))
		{
			if (array_key_exists($area, $ret[$lang]))
			{
				$existing = $ret[$lang][$area];
			}
		}
		$ret[$lang][$area] = array_merge($existing, $files);
	}
}

function _scanLangDir($path)
{
	$langs = array();
	try
	{
		foreach (new DirectoryIterator($path) as $file)
		{
			if ($file->isDot())
			{
				continue;
			}
			if (!$file->isDir())
			{
				continue;
			}
			$langs[] = $file->getFileName();
		}
	}
	catch (Exception $exc)
	{
		//echo $exc->getTraceAsString();
	}

	$ret = array();
	foreach ($langs as $lang)
	{
		try
		{
			foreach (new DirectoryIterator($path . '/' . $lang) as $file)
			{
				if (!$file->isFile())
				{
					continue;
				}
				$fname = $file->getFileName();
				if (substr($fname, -4) != '.ini')
				{
					continue;
				}
				$ret[$lang][] = $path . '/' . $lang . '/' . $fname;
			}
		}
		catch (Exception $exc)
		{
			//echo $exc->getTraceAsString();
		}
	}

	return $ret;
}

echo <<<ENDBANNER
BuildLang 1.0
Copyright (c) 2010-2017 Akeeba Ltd


ENDBANNER;

// Load the properties
$propsFile = $argv[1];
$rootDirectory = realpath($argv[2]);

if (strpos($propsFile, ';') !== false)
{
	$propFiles = explode(';', $propsFile);
	$props = [];

	foreach($propFiles as $propsFile)
	{
		if (!file_exists($propsFile))
		{
			continue;
		}

		$newProps = parse_ini_file($propsFile);

		if (!is_array($newProps) || empty($newProps))
		{
			continue;
		}

		$props = array_merge($props, $newProps);
	}
}
else
{
	$props = parse_ini_file($propsFile);
}

// Get some basic parameters
$packageName = $props['langbuilder.packagename'];
$softwareName = $props['langbuilder.software'];
$authorName = isset($props['langbuilder.authorname']) ? $props['langbuilder.authorname'] : 'Akeeba Ltd';
$authorUrl = isset($props['langbuilder.authorurl']) ? $props['langbuilder.authorurl'] : 'https://www.akeeba.com';
$license = isset($props['langbuilder.license']) ? $props['langbuilder.license'] : 'http://www.gnu.org/licenses/gpl-3.0.html GNU/GPL';
$langVersions = isset($props['langbuilder.jversions']) ? $props['langbuilder.jversions'] : '3.x';

// Create an URL-friendly version of the package name
$packageNameURL = str_replace(' ', '-', strtolower(trim($packageName)));

// Instanciate S3
require_once('S3.php');

$s3 = new S3($props['s3.access'], $props['s3.private']);
$s3Bucket = $props['s3.bucket'];
$s3Path = $props['s3.path'];
$s3LangPath = isset($props['s3.langpath']) ? $props['s3.langpath'] : 'https://cdn.akeebabackup.com/language';

// Scan languages
$root = $rootDirectory . '/translations';
$langs = scan($root);
ksort($langs);
$numlangs = count($langs);
echo "Found $numlangs languages\n\n";

if ($argc > 3)
{
	$version = $argv[2];
}
else
{
	$version = '0.0.' . gmdate('YdmHis');
}

date_default_timezone_set('Europe/Athens');

$date = gmdate('d M Y');
$year = gmdate('Y');

$langToName = parse_ini_file(__DIR__ . '/map.ini');
$badLanguage = parse_ini_file(__DIR__ . '/badlang.ini');

$langHTMLTable = '';
$row = 1;
foreach ($langs as $tag => $files)
{
	if (isset($badLanguage[$tag]))
	{
		$tag = $badLanguage[$tag];
	}

	if (!isset($langToName[$tag]))
	{
		echo "\033[1;31mUnknown language tag $tag\033[0m\n";
		continue;
	}

	$langName = $langToName[$tag];
	echo "Building $langName ($tag)...\n";

	// Get paths to temp and output files
	@mkdir($rootDirectory . '/release/languages');
	$j20ZIPPath = $rootDirectory . '/release/languages/' . $packageName . '-' . $tag . '-j3x.zip';
	$tempXMLPath = $rootDirectory . '/release/' . $packageName . '-' . $tag . '.xml';

	// Start new ZIP files
	@unlink($j20ZIPPath);
	$zip20 = new PclZip($j20ZIPPath);

	// Produce the Joomla! 1.6/1.7/2.5 manifest contents
	$j3XML = <<<ENDHEAD
<?xml version="1.0" encoding="utf-8"?>
<extension type="file" version="1.6" method="upgrade" client="site">
    <name><![CDATA[$packageName - $tag]]></name>
    <author><![CDATA[$authorName]]></author>
    <authorurl>$authorUrl</authorurl>
	<copyright>Copyright (C)$year $authorName. All rights reserved.</copyright>
	<license>$license</license>
    <version>$version</version>
    <creationDate>$date</creationDate>
    <description><![CDATA[$langName translation file for $softwareName]]></description>
	<fileset>

ENDHEAD;

	if (array_key_exists('backend', $files))
	{
		$j3XML .= "\t\t<files folder=\"backend\" target=\"administrator/language/$tag\">\n";
		foreach ($files['backend'] as $file)
		{
			$j3XML .= "\t\t\t<filename>" . baseName($file) . "</filename>\n";
		}
		$j3XML .= "\t\t</files>\n";
	}
	if (array_key_exists('frontend', $files))
	{
		$j3XML .= "\t\t<files folder=\"frontend\" target=\"language/$tag\">\n";
		foreach ($files['frontend'] as $file)
		{
			$j3XML .= "\t\t\t<filename>" . baseName($file) . "</filename>\n";
		}
		$j3XML .= "\t\t</files>\n";
	}
	$j3XML .= "\t</fileset>\n</extension>";

	// Add the manifest (J! 2.x)
	@unlink($tempXMLPath);
	@file_put_contents($tempXMLPath, $j3XML);
	$zip20->add($tempXMLPath,
		PCLZIP_OPT_ADD_PATH, '',
		PCLZIP_OPT_REMOVE_PATH, dirname($tempXMLPath)
	);
	@unlink($tempXMLPath);

	// Add back-end files to archives
	if (array_key_exists('backend', $files))
	{
		foreach ($files['backend'] as $file)
		{
			$zip20->add($file,
				PCLZIP_OPT_ADD_PATH, 'backend',
				PCLZIP_OPT_REMOVE_PATH, dirname($file));
		}
	}
	// Add front-end files to archives
	if (array_key_exists('frontend', $files))
	{
		foreach ($files['frontend'] as $file)
		{
			$zip20->add($file,
				PCLZIP_OPT_ADD_PATH, 'frontend',
				PCLZIP_OPT_REMOVE_PATH, dirname($file));
		}
	}

	// Close archives
	unset($zip20);

	$parts = explode('-', $tag);
	$country = strtolower($parts[1]);
	if ($tag == 'ca-ES')
	{
		$country = 'catalonia';
	}

	$base20 = basename($j20ZIPPath);

	$row = 1 - $row;
	$langHTMLTable .= <<<ENDHTML
	<tr class="row$row">
		<td width="16"><img src="$s3LangPath/flags/$country.png" /></td>
		<td width="50" align="center"><tt>$tag</tt></td>
		<td width="250">$langName</td>
		<td>
			<a href="$s3LangPath/$packageNameURL/$base20">Download for Joomla! $langVersions</a>
		</td>
	</tr>

ENDHTML;

	echo "\tUploading " . basename($j20ZIPPath) . "\n";
	$s3->putObjectFile($j20ZIPPath, $s3Bucket, $s3Path . '/' . $packageNameURL . '/' . basename($j20ZIPPath), S3::ACL_PUBLIC_READ);
}

$html = @file_get_contents($rootDirectory . '/translations/_pages/index.html');
$html = str_replace('[DATE]', gmdate('d M Y H:i:s'), $html);
$html = str_replace('[LANGTABLE]', $langHTMLTable, $html);
$html = str_replace('[YEAR]', gmdate('Y'), $html);

echo "Uploading index.html file\n";
$tempHTMLPath = $rootDirectory . '/release/index.html';
@file_put_contents($tempHTMLPath, $html);
$s3->putObjectFile($tempHTMLPath, $s3Bucket, $s3Path . '/' . $packageNameURL . '/index.html', S3::ACL_PUBLIC_READ);
@unlink($tempHTMLPath);

echo "\nDone\n\n";
