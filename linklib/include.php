<?php
/**
 * @package   buildfiles
 * @copyright Copyright (c)2010-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\LinkLibrary;

use Composer\Autoload\ClassLoader;

if (version_compare(PHP_VERSION, '7.0', 'lt'))
{
	echo <<< END

********************************************************************************
**                                   WARNING                                  **
********************************************************************************

The link library REQUIRES PHP 7.0 or later.

--------------------------------------------------------------------------------
HOW TO FIX
--------------------------------------------------------------------------------

Use your PHP 7 binary to run the Build Files tools

- or -

Make PHP 7 your default PHP CLI version (recommended)

END;

	throw new \RuntimeException("Wrong PHP version");
}

$autoloaderFile = __DIR__ . '/../vendor/autoload.php';

if (!file_exists($autoloaderFile))
{
	echo <<< END

********************************************************************************
**                                   WARNING                                  **
********************************************************************************

You have NOT initialized Composer on the buildfiles repository. This script is
about to die with an error.

--------------------------------------------------------------------------------
HOW TO FIX
--------------------------------------------------------------------------------

Go to the buildfiles repository and run:

php ./composer.phar install


END;

	throw new \RuntimeException("Composer is not initialized in the buildfiles repository");
}

// Get a reference to Composer's autloader
/** @var ClassLoader $composerAutoloader */
if (class_exists('Composer\\Autoload\\ClassLoader'))
{
	$composerAutoloader = new \Composer\Autoload\ClassLoader();
	$composerAutoloader->register();
}
else
{
	$composerAutoloader = require($autoloaderFile);
}

// Register this directory as the PSR-4 source for our namespace prefix
$composerAutoloader->addPsr4('Akeeba\\LinkLibrary\\', __DIR__);
