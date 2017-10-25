<?php
/**
 * Akeeba Build Tools
 *
 * Script to move the language files of an Akeeba repository for a Joomla! extension back to the translations folder
 * in the root of the site.
 *
 * @package        buildfiles
 * @license        GPL v3
 * @copyright      2010-2017 Akeeba Ltd
 */

use Akeeba\BuildLang\BuilderBare;
use Akeeba\BuildLang\Parameters;
use GetOptionKit\OptionPrinter\ConsoleOptionPrinter;
use GetOptionKit\OptionResult;

// Include the necessary libraries
require_once __DIR__ . '/../buildlang/include.php';
require_once __DIR__ . '/../linklib/include.php';

// Make sure I can parse the CLI options
/** @var OptionResult $cliOptions */
$cliOptions = require __DIR__ . '/../buildlang/BuildLang/build-language-options.php';

// Does the invokation meet the minimum requirements?
$showUsage = false;

if (!$cliOptions->has('translations') || $cliOptions->has('help'))
{
	$showUsage = true;
}

// Display the program banner if necessary
if ($showUsage || !$cliOptions->has('quiet'))
{
	$year = gmdate('Y');
	echo <<<ENDBANNER
Akeeba Build Tools - Language Package Builder 2.0
Assembles the language packages and links to the relevant translation stats 
-------------------------------------------------------------------------------
Copyright ©2010-$year Akeeba Ltd
Distributed under the GNU General Public License v3 or later
-------------------------------------------------------------------------------

ENDBANNER;
}

// Do I have to print out usage and quit?
if ($showUsage)
{
	echo sprintf("\nUsage:\n\tphp %s [OPTIONS]\n\nWhere OPTIONS is a combination of:\n\n", basename($argv[0]));

	$printer = new ConsoleOptionPrinter();
	echo $printer->render($specs);

	exit (253);
}

// Set up extra properties from the command line
$extraProperties = [];

if ($cliOptions->has('version'))
{
	$extraProperties['extra.version'] = $cliOptions->get('version');
}

if ($cliOptions->has('output'))
{
	$extraProperties['extra.outputDirectory'] = $cliOptions->get('output');
}

if ($cliOptions->has('quiet'))
{
	$extraProperties['extra.quiet'] = true;
}

if ($cliOptions->has('keep'))
{
	$extraProperties['extra.keepOutput'] = true;
}

if ($cliOptions->has('no-upload'))
{
	$extraProperties['extra.uploadToS3'] = false;
}

// Get the list of parameter files
$paramsFiles        = $cliOptions->get('params');
$translationsFolder = $cliOptions->get('translations');

if ($cliOptions->has('default-params'))
{
	// Default parameters. Order matters: each file overrides the previous ones
	$paramsFiles[] = $translationsFolder . '/../../build.parameters';
	$paramsFiles[] = $translationsFolder . '/../../build.ini';
	$paramsFiles[] = $translationsFolder . '/../build.ini';
	$paramsFiles[] = $translationsFolder . '/build.ini';
}

// Run the language builder
$parameters = new Parameters(implode(';', $paramsFiles), $extraProperties);
$builder    = new BuilderBare($translationsFolder, $parameters);
$builder->buildAll();

