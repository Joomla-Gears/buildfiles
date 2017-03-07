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

if (!class_exists('Akeeba\\LinkLibrary\\ProjectLinker'))
{
	require_once __DIR__ . '/../linklib/include.php';
}

$linker = new \Akeeba\LinkLibrary\ProjectLinker($repoRoot);
$linker->link();