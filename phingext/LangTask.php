<?php
/**
 * Akeeba Build Files
 *
 * @package    buildfiles
 * @copyright  (c) 2010-2017 Akeeba Ltd
 */

require_once "phing/Task.php";
require_once 'phing/tasks/system/MatchingTask.php';
include_once 'phing/util/SourceFileScanner.php';
include_once 'phing/mappers/MergeMapper.php';
include_once 'phing/util/StringHelper.php';
require_once 'pclzip.php';

class LangTask extends Task
{
	/** @var string The path where translations are held */
	private $sourcePath = '';

	/** @var string The path where the archives will be written to */
	private $destPath = '';

	/** @var string The template name of the generated archive, e.g. 'com_myextension-1.0-' in order to create 'com_myextension-1.0-en-GB.zip' etc. */
	private $nameTemplate = '';

	/** @var string Package version */
	private $version = '';

	/** @var string Package date */
	private $date = '';

	/** @var string Subdirectory holding backend languages */
	private $backendPath = 'backend';

	/** @var string Subdirectory holding the front-end translation files */
	private $frontendPath = 'frontend';

	private $languages = array();

	public function setSource($sourcePath)
	{
		$this->sourcePath = $sourcePath;
	}

	public function setDest($destPath)
	{
		$this->destPath = $destPath;
	}

	public function setTemplate($template)
	{
		$this->nameTemplate = $template;
	}

	public function setVersion($version)
	{
		$this->version = $version;
	}

	public function setDate($date)
	{
		$this->date = $date;
	}

	public function setFrontendPath($path)
	{
		$this->frontendPath = $path;
	}

	public function setBackendPath($path)
	{
		$this->backendPath = $path;
	}

	public function main()
	{
		$this->scanBackend();
		$this->scanFrontend();
		foreach ($this->languages as $tag => $langData)
		{
			$this->makeLanguage($tag, $langData);
		}
	}

	private function scanBackend()
	{
		$path = $this->sourcePath . DIRECTORY_SEPARATOR . $this->backendPath;
		$files = $this->scanDirectory($path);

		foreach ($files as $file)
		{
			if (strtolower(substr(basename($file), -4)) != '.ini')
			{
				continue;
			}
			$basename = basename($file, '.ini');
			$tag = substr($basename, 0, strpos($basename, '.'));
			if (!array_key_exists($tag, $this->languages))
			{
				$this->languages[$tag] = array(
					'name'      => '',
					'author'    => '',
					'authorurl' => '',
					'backend'   => array(),
					'frontend'  => array()
				);
			}
			$this->languages[$tag]['backend'][] = basename($file);
			$langData = $this->parse_language($path . DIRECTORY_SEPARATOR . $file);
			$this->languages[$tag]['name'] = $this->conditionalSet($langData, 'TRANSLATION_LANGUAGE', $this->languages[$tag]['name']);
			$this->languages[$tag]['author'] = $this->conditionalSet($langData, 'TRANSLATION_AUTHOR', $this->languages[$tag]['author']);
			$this->languages[$tag]['authorurl'] = $this->conditionalSet($langData, 'TRANSLATION_AUTHOR_URL', $this->languages[$tag]['authorurl']);
		}
	}

	private function scanFrontend()
	{
		$path = $this->sourcePath . DIRECTORY_SEPARATOR . $this->frontendPath;
		$files = $this->scanDirectory($path);

		foreach ($files as $file)
		{
			if (strtolower(substr(basename($file), -4)) != '.ini')
			{
				continue;
			}
			$basename = basename($file, '.ini');
			$tag = substr($basename, 0, strpos($basename, '.'));
			if (!array_key_exists($tag, $this->languages))
			{
				$this->languages[$tag] = array(
					'name'      => '',
					'author'    => '',
					'authorurl' => '',
					'backend'   => array(),
					'frontend'  => array()
				);
			}
			$this->languages[$tag]['frontend'][] = basename($file);
		}
	}

	private function makeLanguage($tag, $langData)
	{
		$validname = strip_tags($langData['name']);
		$validauthor = strip_tags($langData['author']);
		$this->log("Building language package for $tag ({$validname})", Project::MSG_INFO);

		// Create XML file
		$xmlFilename = $this->destPath . DIRECTORY_SEPARATOR . $tag . '.xml';
		$xmlData = <<<FILEDATA
<?xml version="1.0" encoding="utf-8"?>
<install version="1.5" client="both" type="language" method="upgrade">
    <name><![CDATA[{$validname}]]></name>
    <tag>$tag</tag>
    <version>{$this->version}</version>
    <date>{$this->date}</date>
    <author><![CDATA[{$validauthor}]]></author>
    <authorurl>{$langData['authorurl']}</authorurl>
    <description><![CDATA[Akeeba Backup {$validname}]]></description>

FILEDATA;
		if (count($langData['backend']))
		{
			$xmlData .= <<<FILEDATA
	<administration>
		<files folder="backend">

FILEDATA;
			foreach ($langData['backend'] as $fname)
			{
				$xmlData .= "\t\t\t<filename>$fname</filename>\n";
			}
			$xmlData .= "\t\t</files>\n\t</administration>\n";
		}
		if (count($langData['frontend']))
		{
			$xmlData .= <<<FILEDATA
	<site>
		<files folder="frontend">

FILEDATA;
			foreach ($langData['frontend'] as $fname)
			{
				$xmlData .= "\t\t\t<filename>$fname</filename>\n";
			}
			$xmlData .= "\t\t</files>\n\t</site>\n";
		}
		$xmlData .= "\t<params />\n</install>";
		file_put_contents($xmlFilename, $xmlData);

		// Create ZIP file
		$zipFileName = $this->destPath . DIRECTORY_SEPARATOR . $this->nameTemplate . $tag . '.zip';
		@unlink($zipFileName);
		$zip = new PclZip($zipFileName);

		// Add XML file
		$zip->add(array($xmlFilename),
			PCLZIP_OPT_ADD_PATH, '',
			PCLZIP_OPT_REMOVE_PATH, $this->destPath);
		unlink($xmlFilename);

		// Add frontend files
		if (count($langData['frontend']))
		{
			foreach ($langData['frontend'] as $file)
			{
				$frontEndPath = $this->sourcePath . DIRECTORY_SEPARATOR . $this->frontendPath;
				$file = $frontEndPath . DIRECTORY_SEPARATOR . $file;
				$zip->add(array($file),
					PCLZIP_OPT_ADD_PATH, 'frontend',
					PCLZIP_OPT_REMOVE_PATH, $frontEndPath);
			}
		}

		// Add backend files
		if (count($langData['backend']))
		{
			foreach ($langData['backend'] as $file)
			{
				$backEndPath = $this->sourcePath . DIRECTORY_SEPARATOR . $this->backendPath;
				$file = $backEndPath . DIRECTORY_SEPARATOR . $file;
				$zip->add(array($file),
					PCLZIP_OPT_ADD_PATH, 'backend',
					PCLZIP_OPT_REMOVE_PATH, $backEndPath);
			}
		}
	}

	private function scanDirectory($directory)
	{
		$files = array();
		$dirs = array();

		$handle = opendir($directory);
		if ($handle === false)
		{
			return array();
		}
		while ($aFile = readdir($handle))
		{
			if (($aFile != '.') && ($aFile != '..'))
			{
				if (is_file($directory . '/' . $aFile))
				{
					$files[] = $aFile;
				}
				else
				{
					if (($aFile != '.svn') && is_dir($directory . '/' . $aFile))
					{
						$dirs[] = $aFile;
					}
				}
			}
		}
		closedir($handle);

		// Recurse into sub-directories
		if (count($dirs))
		{
			foreach ($dirs as $dir)
			{
				$morefiles = scanDirectory($directory . '/' . $dir);
				if (count($morefiles) == 0)
				{
					// Empty directory
					$files[] = $dir;
				}
				else
				{
					foreach ($morefiles as $aFile)
					{
						$files[] = $dir . '/' . $aFile;
					}
				}
			}
		}

		return $files;
	}

	private function parse_language($filename)
	{
		$ret = array();
		$rawdata = file($filename);
		foreach ($rawdata as $line)
		{
			if (substr($line, 0, 1) == chr(0xEF))
			{
				$line = substr($line, 3);
			}
			$line = ltrim($line, "\uFEFF");
			$line = trim(rtrim($line, "\n"));
			if (substr($line, 0, 1) == "#")
			{
				continue;
			}
			if (substr($line, 0, 1) == ";")
			{
				continue;
			}
			if (empty($line))
			{
				continue;
			}
			if (strpos($line, '=') === false)
			{
				continue;
			}
			list($key, $value) = explode('=', $line, 2);
			if ((substr($value, 0, 1) == '"') && (substr($value, -1) == '"'))
			{
				$value = substr($value, 1, -1);
			}
			$ret[$key] = $value;
		}

		return $ret;
	}

	private function conditionalSet($array, $key, $data)
	{
		if (!empty($data))
		{
			return $data;
		}
		if (!array_key_exists($key, $array))
		{
			return $data;
		}

		return $array[$key];
	}
}