<?php
/**
 * Akeeba Build Tools - Language linker
 * Copyright (c)2010-2014 Akeeba Ltd
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
 * @package     buildfiles
 * @subpackage  tools
 * @license     GPL v3
 */

/**
 * Translate a windows path
 *
 * @param   string $p_path The path to translate
 *
 * @return  string  The translated path
 */
function translateWinPath($p_path)
{
	if (stristr(php_uname(), 'windows'))
	{
		// Change potential windows directory separator
		if ((strpos($p_path, '\\') > 0) || (substr($p_path, 0, 1) == '\\'))
		{
			$p_path = strtr($p_path, '\\', '/');
		}

		$p_path = strtr($p_path, '/', '\\');
	}

	return $p_path;
}

/**
 * Link all translation files
 *
 * @param   string $root   The root path of the real extensions
 * @param   string $target The target path (where translations are stored)
 */
function linkTranslations($root, $target)
{
	echo "$root\n";
	foreach (new DirectoryIterator($root) as $oArea)
	{
		if (!$oArea->isDir())
		{
			continue;
		}
		if ($oArea->isDot())
		{
			continue;
		}
		$area = $oArea->getFilename();

		$areaDir = $root . '/' . $area;

		echo "\t$area\n";

		foreach (new DirectoryIterator($areaDir) as $oModule)
		{
			if (!$oModule->isDir())
			{
				continue;
			}
			if ($oModule->isDot())
			{
				continue;
			}
			$module = $oModule->getFilename();

			echo "\t\t$module";

			$moduleDir = $areaDir . '/' . $module;

			$from = $target . '/' . $area . '/' . $module . '/en-GB';
			$to = $moduleDir . '/language/en-GB';

			if (!is_dir($from))
			{
				// Some things may be untranslated
				echo "\tNot translated\n";
				continue;
			}

			if (stristr(php_uname(), 'windows') && is_dir($from))
			{
				if (file_exists($to))
				{
					if (!@unlink($to))
					{
						echo "\tCannot remove old link\n";
						continue;
					}
				}
			}
			elseif (is_link($to))
			{
				if (!@unlink($to))
				{
					echo "\tCannot remove old link\n";
					continue;
				}
			}
			elseif (is_dir($to))
			{
				// Let's do it The Hard Way™
				$cmd = 'rm -rf "' . $to . '"';
				exec($cmd);
				echo "\tHard Way™";
			}

			if (stristr(php_uname(), 'windows') && is_dir($from))
			{
				$f = translateWinPath($from);
				$t = translateWinPath($to);
				$cmd = 'mklink /D "' . $to . '" "' . $from . '"';
				exec($cmd);
			}
			else
			{
				@symlink($from, $to);
			}

			echo "\tLINKED\n";
		}
	}
}

function showUsage()
{
	$file = basename(__FILE__);
	echo <<<ENDUSAGE

Usage:
	php $file /path/to/repository

ENDUSAGE;
}

$year = gmdate('Y');
echo <<<ENDBANNER
Akeeba Build Tools - Language Linker
Link translation files to the respective extensions' directories
-------------------------------------------------------------------------------
Copyright ©2010-$year Nicholas K. Dionysopoulos / AkeebaBackup.com
Distributed under the GNU General Public License v3 or later
-------------------------------------------------------------------------------

ENDBANNER;

if ($argc < 2)
{
	showUsage();
	die();
}

$repoRoot = $argv[1];

$root = $repoRoot . '/modules';
$target = $repoRoot . '/translations/modules';
if (is_dir($root) && is_dir($target))
{
	linkTranslations($root, $target);
}

$root = $repoRoot . '/plugins';
$target = $repoRoot . '/translations/plugins';
if (is_dir($root) && is_dir($target))
{
	linkTranslations($root, $target);
}