<?php
/**
 * Akeeba Build Tools
 *
 * @package        buildfiles
 * @license        GPL v3
 * @copyright      2010-2017 Akeeba Ltd
 */

namespace Akeeba\LinkLibrary;

use Composer\Autoload\ClassLoader;

// Get a reference to Composer's autloader
/** @var ClassLoader $composerAutoloader */
$composerAutoloader = require(__DIR__ . '/../vendor/autoload.php');

// Register this directory as the PSR-4 source for our namespace prefix
$composerAutoloader->addPsr4('Akeeba\\LinkLibrary', __DIR__);