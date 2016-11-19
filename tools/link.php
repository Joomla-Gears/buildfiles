<?php
/**
 * Akeeba Build Tools - Internal Linker
 * Copyright (c)2010-2016 Akeeba Ltd
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

// Internal linking script
$hardlink_files = array();

$symlink_files = array();

$symlink_folders = array();

define('IS_WINDOWS', substr(PHP_OS, 0, 3) == 'WIN');

function TranslateWinPath($p_path)
{
	$is_unc = false;

	if (IS_WINDOWS)
	{
		// Is this a UNC path?
		$is_unc = (substr($p_path, 0, 2) == '\\\\') || (substr($p_path, 0, 2) == '//');
		// Change potential windows directory separator
		if ((strpos($p_path, '\\') > 0) || (substr($p_path, 0, 1) == '\\'))
		{
			$p_path = strtr($p_path, '\\', '/');
		}
	}

	// Remove multiple slashes
	$p_path = str_replace('///', '/', $p_path);
	$p_path = str_replace('//', '/', $p_path);

	// Fix UNC paths
	if ($is_unc)
	{
		$p_path = '//' . ltrim($p_path, '/');
	}

	return $p_path;
}

function doLink($from, $to, $type = 'symlink', $path)
{
	$realTo = $path . '/' . $to;
	$realFrom = $path . '/' . $from;

	if (IS_WINDOWS)
	{
		// Windows doesn't play nice with paths containing UNIX path separators
		$realTo = TranslateWinPath($realTo);
		$realFrom = TranslateWinPath($realFrom);
		// Windows doesn't play nice with relative paths in symlinks
		$realFrom = realpath($realFrom);
	}
	elseif ($type == 'symlink')
	{
		$parts = explode('/', $to);
		$prefix = '';

		for ($i = 0; $i < count($parts) - 1; $i++)
		{
			$prefix .= '../';
		}

		$realFrom = $prefix . $from;
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
		if(IS_WINDOWS)
		{
			$extraArguments = '';

			if (is_dir($realFrom))
			{
				$extraArguments = ' /D ';
			}

			$cmd = 'mklink ' . $extraArguments .' "' . $realTo . '" "' . $realFrom . '"';
			$res = exec($cmd);
		}
		else
		{
			$res = @symlink($realFrom, $realTo);
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
                    $return = $return && $deleteFolderResult;
                }

                if (!$deleteFolderResult)
                {
                    // echo "  Failed deleting folder {$file->getPathname()}\n";
                }
            }

            // We have to try the rmdir in case this is a Windows directory symlink.
            $deleteFileResult = @rmdir($file->getPathname()) || @unlink($file->getPathname());
            $return = $return && $deleteFileResult;

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

function showUsage()
{
	$file = basename(__FILE__);
	echo <<<ENDUSAGE

Usage:
	php $file /path/to/repository

ENDUSAGE;
}

if (!isset($repoRoot))
{
	$year = gmdate('Y');
	echo <<<ENDBANNER
Akeeba Build Tools - Linker
Internal file and directory symlinker
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
}

if (!file_exists($repoRoot . '/build/templates/link.php'))
{
	die("Error: build/templates/link.php not found\n");
}

require_once $repoRoot . '/build/templates/link.php';

echo "Hard linking files...\n";
if (!empty($hardlink_files))
{
	foreach ($hardlink_files as $from => $to)
	{
		doLink($from, $to, 'link', $repoRoot);
	}
}

echo "Symlinking files...\n";
if (!empty($symlink_files))
{
	foreach ($symlink_files as $from => $to)
	{
		doLink($from, $to, 'symlink', $repoRoot);
	}
}

echo "Symlinking folders...\n";
if (!empty($symlink_folders))
{
	foreach ($symlink_folders as $from => $to)
	{
		doLink($from, $to, 'symlink', $repoRoot);
	}
}
