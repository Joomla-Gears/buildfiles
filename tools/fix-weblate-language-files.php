<?php
/**
 * Akeeba Build Tools - Fix Weblate language files
 * Copyright (c)2010-2017 Akeeba Ltd
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * Purpose of this tool:
 *
 * Importing Joomla! INI files to Weblate is buggy. It makes this:
 * COM_EXAMPLE_FOO="This is an example"
 * into this broken thing
 * COM_EXAMPLE_FOO=""_QQ_"This is an example"_QQ_""
 * It also seems to have its own mind about how double quotes are encoded. This tool fixes these broken strings.
 *
 * @package     buildfiles
 * @subpackage  tools
 * @license     GPL v3
 */

use GetOptionKit\OptionCollection;
use GetOptionKit\OptionParser;
use GetOptionKit\OptionPrinter\ConsoleOptionPrinter;
use GetOptionKit\OptionResult;

require_once __DIR__ . '/../vendor/autoload.php';

$year = gmdate('Y');
echo <<<ENDBANNER
Akeeba Build Tools - Fix Weblate language files 1.0
Fixes language strings broken by Weblate 
-------------------------------------------------------------------------------
Copyright ©2010-$year Akeeba Ltd
Distributed under the GNU General Public License v3 or later
-------------------------------------------------------------------------------

ENDBANNER;

class Scanner
{
	private $repoRoot;

	private $langDir = 'translations';

	private $cliOptions;

	public function __construct(string $repoRoot, OptionResult $cliOptions)
	{
		$this->repoRoot   = $repoRoot;
		$this->cliOptions = $cliOptions;
		$this->langDir    = $cliOptions->get('directory');
	}

	public function run()
	{
		$myRoot      = $this->repoRoot . '/' . $this->langDir;

		foreach (new DirectoryIterator($myRoot) as $oArea)
		{
			if (!$oArea->isDir() || $oArea->isDot())
			{
				continue;
			}

			$area    = $oArea->getFilename();
			$areaDir = $myRoot . '/' . $area;

			foreach (new DirectoryIterator($areaDir) as $oFolder)
			{
				if ($oFolder->isDot())
				{
					continue;
				}

				// ANGIE and Kickstart languages are shallower
				if (!$oFolder->isDir() && ($oFolder->getExtension() == 'ini'))
				{
					$this->processLanguageFolder($areaDir);

					continue 2;
				}

				$folder    = $oFolder->getFilename();
				$folderDir = $areaDir . '/' . $folder;

				// Is this a component?
				if (is_dir($folderDir . '/en-GB'))
				{
					$this->processTopLevelFolder($folderDir);

					continue;
				}

				// Is this a module or plugin?
				foreach (new DirectoryIterator($folderDir) as $oExtension)
				{
					if (!$oExtension->isDir() || $oExtension->isDot())
					{
						continue;
					}

					$extension    = $oExtension->getFilename();
					$extensionDir = $folderDir . '/' . $extension;

					if (is_dir($extensionDir . '/en-GB'))
					{
						$this->processTopLevelFolder($extensionDir);
					}
				}
			}
		}
	}

	/**
	 * Process a top level folder. It has subfolders for each language, e.g. en-GB, el-GR etc. Each subfolder has the
	 * INI files I need to process.
	 *
	 * @param   string  $folder  The folder to process
	 *
	 * @return  void
	 */
	protected function processTopLevelFolder($folder)
	{
		$di = new DirectoryIterator($folder);

		foreach ($di as $subFolder)
		{
			if (!$subFolder->isDir() || $subFolder->isDot())
			{
				continue;
			}

			$this->processLanguageFolder($subFolder->getPathname());
		}
	}

	protected function processLanguageFolder($folder)
	{
		$di = new DirectoryIterator($folder);

		foreach ($di as $file)
		{
			if (!$file->isFile() || ($file->getExtension() != 'ini'))
			{
				continue;
			}

			$this->processLanguageFile($file->getPathname());
		}
	}

	protected function processLanguageFile($filePath)
	{
		$contents = file_get_contents($filePath);

		// Do I even have to process this file?
		if (strpos($contents, '"_QQ_"') === false)
		{
			return;
		}

		echo "$filePath\n";

		$lines = explode("\n", $contents);
		$result = '';

		foreach ($lines as $line)
		{
			$line = trim($line);
			$isComment = substr($line, 0, 1) == ';';
			$isLanguageKey = strpos($line, '=') !== false;

			if (empty($line) || $isComment || !$isLanguageKey)
			{
				$result .= $line . "\n";

				continue;
			}

			list($key, $value) = explode('=', $line, 2);

			// Remove surrounding double quotes
			if (substr($value, 0, 1) == '"')
			{
				$value  = substr($value, 1);
			}

			if (substr($value, -1) == '"')
			{
				$value  = substr($value, 0, -1);
			}

			// Remove surrounding "_QQ_" (the Weblate bug)
			if (substr($value, 0, 6) == '"_QQ_"')
			{
				$value  = substr($value, 6);
			}

			if (substr($value, -6) == '"_QQ_"')
			{
				$value  = substr($value, 0, -6);
			}

			// Replace inline escaped double quote \" with single double quote
			$value = str_replace('\\"', '"', $value);

			// Replace inline old-style "_QQ_" with double quote "
			$value = str_replace('"_QQ_"', '"', $value);

			// Normalize inline double quotes to escaped double quotes
			$value = str_replace('"', '\\"', $value);

			// Construct and add the line
			$result .= $key . '="' . $value . "\"\n";

			// Someone managed to cock up so badly that we ended up with escaped \"_QQ_\" in some files...
			$result = str_replace('="\"_QQ_\"', '="', $result);

			$result = rtrim($result, "\n");

			if (substr($result, -9) == '\"_QQ_\""')
			{
				$result = substr($result, 0, -9) . '"';
			}

			$result .= "\n";
		}

		$result = rtrim($result, "\n");

		// If the contents didn't change we won't touch the file, avoiding an expensive reread of the file by Weblate.
		if ($result == $contents)
		{
			echo "\tunchanged\n";
			return;
		}

		// Write the file back
		file_put_contents($filePath, $result);
	}
}

$specs = new OptionCollection;
$specs->add('r|repo?', 'Repository working copy to scan (default: current working directory).')
	->isa('String');
$specs->add('d|directory?', 'Language directory in the repository (default: "translations").')
	->isa('String')
	->defaultValue("translations");

try
{
	$parser = new OptionParser($specs);
	$result = $parser->parse($argv);
}
catch (Exception $e)
{
	echo $e->getMessage();
	exit (255);
}

if (!$result->count())
{
	$self = basename($argv[0]);
	echo <<< ERRORPREFIX

Usage:
	$self [arguments]

Available arguments:

ERRORPREFIX;

	$printer = new ConsoleOptionPrinter();

	echo $printer->render($parser->specs);

	exit(1);
}

try
{
	$scanner      = new Scanner($result->get('repo'), $result);
	$scanner->run();
}
catch (Exception $e)
{
	echo $e->getMessage();
}
