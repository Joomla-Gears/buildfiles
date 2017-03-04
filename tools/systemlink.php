<?php
/**
 * Akeeba Build Tools - System linker
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
 * @package     buildfiles
 * @subpackage  tools
 * @license     GPL v3
 */

define('IS_WINDOWS', substr(PHP_OS, 0, 3) == 'WIN');

/**
 * Create a link
 *
 * @param   string  $from  The location of the file which already exists
 * @param   string  $to    The symlink to be created
 * @param   string  $type  The type of link to create: symlink (symbolic link) or link (hard link)
 * @param   string  $path  The path that $to and $form are relative to
 */
function doLink($from, $to, $type = 'symlink', $path = null)
{
	$realTo   = $to;
	$realFrom = $from;

	if (!empty($path))
	{
		$realTo   = $path . '/' . $to;
		$realFrom = $path . '/' . $from;
	}

	if (IS_WINDOWS)
	{
		// Windows doesn't play nice with paths containing UNIX path separators
		$realTo   = TranslateWinPath($realTo);
		$realFrom = TranslateWinPath($realFrom);

		// Windows doesn't play nice with relative paths in symlinks
		$realFrom = realpath($realFrom);
	}
	elseif ($type == 'symlink')
	{
		$realFrom = realpath($realFrom);
	}

	if (is_file($realTo) || is_dir($realTo) || is_link($realTo) || file_exists($realTo))
	{
		if (IS_WINDOWS && is_dir($realTo))
		{
			// Windows can't unlink() directory symlinks; it needs rmdir() to be used instead
			$res = @rmdir($realTo);
		}
		else
		{
			$res = @unlink($realTo);
		}

		// Invalid symlinks are not reported as directories but require @rmdir to delete them because FREAKING WINDOWS.
		if (!$res && IS_WINDOWS)
		{
			$res = @rmdir($realTo);
		}

		if (!$res && is_dir($realTo))
		{
			// This is an actual directory, not an old symlink
			$res = recursiveUnlink($realTo);
		}

		if (!$res)
		{
			echo "FAILED UNLINK  : $realTo\n";

			return;
		}
	}

	if ($type == 'symlink')
	{
		if (IS_WINDOWS)
		{
			$extraArguments = '';

			if (is_dir($realFrom))
			{
				$extraArguments = ' /D ';
			}

			$relativeFrom = getRelativePath($realTo, $realFrom);
			$cmd = 'mklink ' . $extraArguments . ' "' . $realTo . '" "' . $relativeFrom . '"';
			$res = exec($cmd);
		}
		else
		{
			$relativeFrom = getRelativePath($realTo, $realFrom);
			$res = @symlink($relativeFrom, $realTo);
		}
	}
	elseif ($type == 'link')
	{
		$res = @link($realFrom, $realTo);
	}

	if (!$res)
	{
		if ($type == 'symlink')
		{
			echo "FAILED SYMLINK : $realTo\n";
		}
		elseif ($type == 'link')
		{
			echo "FAILED LINK    : $realTo\n";
		}
	}
}

/**
 * Normalize Windows or mixed Windows and UNIX paths to UNIX style
 *
 * @param   string  $path  The path to nromalize
 *
 * @return  string
 */
function TranslateWinPath($path)
{
	$is_unc = false;

	if (IS_WINDOWS)
	{
		// Is this a UNC path?
		$is_unc = (substr($path, 0, 2) == '\\\\') || (substr($path, 0, 2) == '//');

		// Change potential windows directory separator
		if ((strpos($path, '\\') > 0) || (substr($path, 0, 1) == '\\'))
		{
			$path = strtr($path, '\\', '/');
		}
	}

	// Remove multiple slashes
	$path = str_replace('///', '/', $path);
	$path = str_replace('//', '/', $path);

	// Fix UNC paths
	if ($is_unc)
	{
		$path = '//' . ltrim($path, '/');
	}

	return $path;
}

/**
 * Recursively delete a directory
 *
 * @param   string  $dir  The directory to remove
 *
 * @return  bool  True on success
 */
function recursiveUnlink($dir)
{
	$return = true;

	try
	{
		$dh = new DirectoryIterator($dir);

		foreach ($dh as $file)
		{
			if ($file->isDot())
			{
				continue;
			}

			if ($file->isDir())
			{
				// We have to try the rmdir in case this is a Windows directory symlink OR an empty folder.
				$deleteFolderResult = @rmdir($file->getPathname());

				// If rmdir failed (non-empty, real folder) we have to recursively delete it
				if (!$deleteFolderResult)
				{
					$deleteFolderResult = recursiveUnlink($file->getPathname());
					$return             = $return && $deleteFolderResult;
				}

				if (!$deleteFolderResult)
				{
					// echo "  Failed deleting folder {$file->getPathname()}\n";
				}
			}

			// We have to try the rmdir in case this is a Windows directory symlink.
			$deleteFileResult = @rmdir($file->getPathname()) || @unlink($file->getPathname());
			$return           = $return && $deleteFileResult;

			if (!$deleteFileResult)
			{
				// echo "  Failed deleting file {$file->getPathname()}\n";
			}
		}

		$return = $return && @rmdir($dir);

		return $return;
	}
	catch (Exception $e)
	{
		return false;
	}
}

/**
 * Get the relative path between two folders
 *
 * @param   string  $pathToConvert  Convert this folder to a location relative to $from
 * @param   string  $basePath       Base folder
 *
 * @return  string  The relative path
 */
function getRelativePath($pathToConvert, $basePath)
{
	// Some compatibility fixes for Windows paths
	$pathToConvert = is_dir($pathToConvert) ? rtrim($pathToConvert, '\/') . '/' : $pathToConvert;
	$basePath      = is_dir($basePath) ? rtrim($basePath, '\/') . '/' : $basePath;
	$pathToConvert = str_replace('\\', '/', $pathToConvert);
	$basePath      = str_replace('\\', '/', $basePath);

	$pathToConvert = explode('/', $pathToConvert);
	$basePath      = explode('/', $basePath);
	$relPath       = $basePath;

	foreach ($pathToConvert as $depth => $dir)
	{
		// find first non-matching dir
		if ($dir === $basePath[$depth])
		{
			// ignore this directory
			array_shift($relPath);
		}
		else
		{
			// get number of remaining dirs to $pathToConvert
			$remaining = count($pathToConvert) - $depth;

			if ($remaining > 1)
			{
				// add traversals up to first matching dir
				$padLength = (count($relPath) + $remaining - 1) * -1;
				$relPath   = array_pad($relPath, $padLength, '..');
				break;
			}
			else
			{
				$relPath[0] = '.' . DIRECTORY_SEPARATOR . $relPath[0];
			}
		}
	}

	return implode(DIRECTORY_SEPARATOR, $relPath);
}

$options = getopt('s:d:t:', array('source:', 'destination:', 'type:'));

$source = $options['s'] ? $options['s'] : $options['source'];
$dest   = $options['d'] ? $options['d'] : $options['destination'];
$type   = $options['t'] ? $options['t'] : $options['type'];

// Sanity checks
if(!$source || !$dest || !$type)
{
    echo 'You must supply the source, destination and type arguments';
    return;
}

if(!in_array($type, array('link', 'symlink')))
{
    echo 'Unknown link type: '.$type;
    return;
}

doLink($source, $dest, $type);