<?php
/**
 * Akeeba Build Tools
 *
 * @package    buildfiles
 * @license    GNU/GPL v3
 * @copyright  Copyright (c)2010-2019 Nicholas K. Dionysopoulos / Akeeba Ltd
 */

/**
 * Script to relink the repository's extensions to a Joomla site
 */

/**
 * Displays the usage of this tool
 *
 * @return  void
 */
function showUsage()
{
	$file = basename(__FILE__);
	echo <<<ENDUSAGE

Usage:
	php $file /path/to/site /path/to/repository

ENDUSAGE;
}

$year = gmdate('Y');
echo <<<ENDBANNER
Akeeba Build Tools - Relinker 3.1
No-configuration extension linker
-------------------------------------------------------------------------------
Copyright ©2010-$year Akeeba Ltd
Distributed under the GNU General Public License v3 or later
-------------------------------------------------------------------------------

ENDBANNER;

if ($argc < 3)
{
	showUsage();
	die();
}

if (!class_exists('Akeeba\\LinkLibrary\\Relink'))
{
	require_once __DIR__ . '/../linklib/include.php';
}

$siteRoot = $argv[1];
$repoRoot = $argv[2];

$relink = new \Akeeba\LinkLibrary\Relink($repoRoot);
$relink->setVerbose(true);
$relink->relink($siteRoot);
