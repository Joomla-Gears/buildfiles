<?php
/**
 * Akeeba Build Tools
 *
 * Language Package Builder
 *
 * @package        buildfiles
 * @license        GPL v3
 * @copyright      2010-2017 Akeeba Ltd
 */

use Akeeba\BuildLang\Builder;
use Akeeba\BuildLang\BuilderBare;
use Akeeba\BuildLang\Parameters;

require_once __DIR__ . '/include.php';
require_once __DIR__ . '/../linklib/include.php';

$year = gmdate('Y');
echo <<<ENDBANNER
Akeeba Build Tools - Language Builder 2.0
Automatically build and upload language ZIP files 
-------------------------------------------------------------------------------
Copyright ©2010-$year Akeeba Ltd
Distributed under the GNU General Public License v3 or later
-------------------------------------------------------------------------------

ENDBANNER;

if ($argc < 3)
{
	$script = basename($argv[0]);

	echo <<< USAGE

Usage:
	$script /path/to/build.properties /path/to/repository_root [version]

USAGE;

	exit(255);
}

$propsFile     = $argv[1];
$rootDirectory = realpath($argv[2]);
$version       = '0.0.' . gmdate('YdmHis');

if ($argc > 3)
{
	$version = $argv[3];
}


try
{
	$parameters = new Parameters($propsFile, ['extra.version' => $version]);
	$builder    = new BuilderBare($rootDirectory, $parameters);
	$builder->buildAll();
}
catch (Exception $e)
{
	echo "WHOOPSIE!\n";
	echo $e->getMessage();
	exit (1);
}
