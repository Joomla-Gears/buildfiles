<?php
/**
 * Akeeba Build Tools
 *
 * Internal linker script
 *
 * @package        buildfiles
 * @license        GPL v3
 * @copyright      2010-2017 Akeeba Ltd
 */

$hardlink_files = array();
$symlink_files = array();
$symlink_folders = array();


/**
 * Display the usage of this tool
 *
 * @return  void
 */
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
Copyright ©2010-$year Akeeba Ltd
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

$repoRoot = realpath($repoRoot);

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
