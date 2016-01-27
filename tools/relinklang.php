<?php
/**
 * Akeeba Build Tools - Language Relinker
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

if (stristr(php_uname(), 'windows'))
{
	define('AKEEBA_RELINK_WINDOWS', 1);
}

/**
 * Is this path a symlink?
 *
 * @param   string $path The path to test
 *
 * @return  bool  True if it is a symlink
 */
function isLink($path)
{
	if (defined('AKEEBA_RELINK_WINDOWS'))
	{
		return file_exists($path);
	}
	else
	{
		return is_link($path);
	}
}

/**
 * Create a directory symlink
 *
 * @param   string $from Directory which already exists
 * @param   string $to   Path to the symlink we'll create
 *
 * @return  void
 */
function symlink_dir($from, $to)
{
	if (is_dir($to))
	{
		if (defined('AKEEBA_RELINK_WINDOWS'))
		{
			$cmd = 'rmdir /s /q "' . $to . '"';
		}
		else
		{
			$cmd = 'rm -rf "' . $to . '"';
		}
		exec($cmd);
	}

	if (defined('AKEEBA_RELINK_WINDOWS'))
	{
		$cmd = 'mklink /D "' . $to . '" "' . $from . '"';
		exec($cmd);
	}
	else
	{
		@symlink($from, $to);
	}
}

/**
 * Create a file symlink
 *
 * @param   string $from File which already exists
 * @param   string $to   Path to the symlink we'll create
 *
 * @return  void
 */
function symlink_file($from, $to)
{
	if (file_exists($to))
	{
		if (defined('AKEEBA_RELINK_WINDOWS'))
		{
			$cmd = 'del /f /q "' . $to . '"';
		}
		else
		{
			$cmd = 'rm -f "' . $to . '"';
		}
		exec($cmd);
	}

	if (defined('AKEEBA_RELINK_WINDOWS'))
	{
		$cmd = 'mklink "' . $to . '" "' . $from . '"';
		exec($cmd);
	}
	else
	{
		@symlink($from, $to);
	}
}

/**
 * Create a file hardlink
 *
 * @param   string $from File which already exists
 * @param   string $to   Path to the hardlink we'll create
 *
 * @return  void
 */
function hardlink_file($from, $to)
{
	if (file_exists($to))
	{
		if (defined('AKEEBA_RELINK_WINDOWS'))
		{
			$cmd = 'del /f /q "' . $to . '"';
		}
		else
		{
			$cmd = 'rm -f "' . $to . '"';
		}
		exec($cmd);
	}

	if (defined('AKEEBA_RELINK_WINDOWS'))
	{
		$cmd = 'mklink /H "' . $to . '" "' . $from . '"';
		exec($cmd);
	}
	else
	{
		@link($from, $to);
	}
}

/**
 * Required on Windows to turn all forward slashes to backslashes and, conversely, when on Linux / Mac OS X convert all
 * backslashes to slashes.
 *
 * @param   string $path The path to convert
 *
 * @return  string  The converted path
 */
function realpath2($path)
{
	if (defined('AKEEBA_RELINK_WINDOWS'))
	{
		return str_replace('/', '\\', $path);
	}
	else
	{
		return str_replace('\\', '/', $path);
	}
}

class AkeebaRelinkLanguage
{
	/** @var string The path to the sources */
	private $root = null;

	/** @var string The path to the site's root */
	private $siteRoot = null;

	/** @var array A list of back-end and front-end languages installed on the site */
	protected $siteLanguages = ['site' => [], 'admin' => []];

	/** @var array Language file source directories (from the extension's path) */
	protected $sources = ['site' => [], 'admin' => []];

	/**
	 * Aliases of site and admin directories inside the various subdirectories of the translations folder
	 *
	 * @var  array
	 */
	protected $directoryAliases = [
		'site'  => ['site', 'root', 'frontend', 'front-end'],
		'admin' => ['admin', 'administrator', 'backend', 'back-end'],
	];

	/**
	 * Aliases of the top-level directory holding the translations for all the extensions of this component
	 *
	 * @var array
	 */
	protected $translationDirectoryAliases = ['translation', 'translations', 'language'];

	/**
	 * Public constructor. Initialises the class with the user-supplied information.
	 *
	 * @param   array $config Configuration parameters. We need root and site.
	 *
	 * @return  AkeebaRelinkLanguage
	 */
	public function __construct($config = array())
	{
		if ( !array_key_exists('root', $config))
		{
			$config['root'] = dirname(__FILE__);
		}
		if ( !array_key_exists('site', $config))
		{
			$config['site'] = '/Users/nicholas/Sites/jbeta';
		}

		$this->root     = $config['root'];
		$this->siteRoot = $config['site'];

		$this->scanSite();
		$this->scanRepository();
		$this->reduceLanguageLists();
	}

	/**
	 * Scans the site for installed languages and populates $this->siteLanguages
	 *
	 * @return  void
	 */
	protected function scanSite()
	{
		$di = new DirectoryIterator($this->siteRoot . '/administrator/language');

		foreach ($di as $dir)
		{
			if ($dir->isDot())
			{
				continue;
			}

			if ( !$dir->isDir())
			{
				continue;
			}

			$this->siteLanguages['admin'][] = $dir->getFilename();
		}

		$di = new DirectoryIterator($this->siteRoot . '/language');

		foreach ($di as $dir)
		{
			if ($dir->isDot())
			{
				continue;
			}

			if ( !$dir->isDir())
			{
				continue;
			}

			$this->siteLanguages['site'][] = $dir->getFilename();
		}
	}

	/**
	 * Scan the entire repository for translation file sources
	 *
	 * @return  void
	 */
	protected function scanRepository()
	{
		// Loop for all translation directory aliases
		foreach ($this->translationDirectoryAliases as $translationDirectory)
		{
			// Make sure the translation directory exists
			$mainRoot = $this->root . '/' . $translationDirectory;

			if (!is_dir($mainRoot))
			{
				continue;
			}

			$mainRoot = realpath($mainRoot);

			$this->scanAwfComponent($mainRoot);
			$this->scanComponent($mainRoot);
			$this->scanModules($mainRoot);
			$this->scanPlugins($mainRoot);
		}
	}

	/**
	 * Scan for component translations
	 *
	 * @param   string  $mainRoot  The top-level directory where all of extension translations are stored
	 *
	 * @return  void
	 */
	protected function scanComponent($mainRoot)
	{
		// Get the root folder of the component translations
		$root = realpath($mainRoot . '/component');

		// Make sure the directory exists
		if (empty($root) || !is_dir($root))
		{
			return;
		}

		// Go through all of the front-end / back-end directory aliases
		foreach ($this->directoryAliases as $siteSide => $allAliases)
		{
			foreach ($allAliases as $alias)
			{
				// Get the possible language root e.g. /repodir/translation/component/frontend
				$langRoot = realpath($root . '/' . $alias);

				// Make sure the directory exists
				if (empty($langRoot) || !is_dir($langRoot))
				{
					continue;
				}

				$di = new DirectoryIterator($langRoot);

				foreach ($di as $directory)
				{
					if ($directory->isDot() || !$directory->isDir())
					{
						continue;
					}

					// e.g. $this->sources['site']['en-GB'] =  /repodir/translation/component/frontend/en-GB
					if (!isset($this->sources[$siteSide][$directory->getFilename()]))
					{
						$this->sources[$siteSide][$directory->getFilename()] = [];
					}

					$this->sources[$siteSide][$directory->getFilename()][] = $directory->getPathname();
				}
			}
		}
	}

	/**
	 * Scan for AWF-based component translations
	 *
	 * @param   string  $mainRoot  The top-level directory where all of extension translations are stored
	 *
	 * @return  void
	 */
	protected function scanAwfComponent($mainRoot)
	{
		// Get the root folder of the component translations
		$root = realpath($mainRoot);

		// Make sure the directory exists
		if (empty($root) || !is_dir($root))
		{
			return;
		}

		$ldi = new DirectoryIterator($root);

		foreach ($ldi as $langDir)
		{
			if ($langDir->isDot() || !$langDir->isDir())
			{
				continue;
			}

			$directoryName = $langDir->getFilename();

			if (in_array($directoryName, array(
				'_pages',
				'component',
				'plugins',
				'modules',
			)))
			{
				continue;
			}

			$langRoot      = realpath($root . '/' . $directoryName);
			$di            = new DirectoryIterator($langRoot);

			foreach ($di as $directory)
			{
				if ($directory->isDot() || !$directory->isDir())
				{
					continue;
				}

				// e.g. $this->sources['site']['en-GB'] =  /repodir/component/backend/app/languages/foobar/en-GB
				if (!isset($this->sources['admin'][$directory->getFilename()]))
				{
					$this->sources['admin'][$directory->getFilename()] = [];
				}

				$this->sources['admin'][$directory->getFilename()][] = $directory->getPathname();
			}
		}
	}

	/**
	 * Scan for module translations
	 *
	 * @param   string  $mainRoot  The top-level directory where all of extension translations are stored
	 *
	 * @return  void
	 */
	protected function scanModules($mainRoot)
	{
		// Get the root folder of the component translations
		$root = realpath($mainRoot . '/modules');

		// Make sure the directory exists
		if (empty($root) || !is_dir($root))
		{
			return;
		}

		// Go through all of the front-end / back-end directory aliases
		foreach ($this->directoryAliases as $siteSide => $allAliases)
		{
			foreach ($allAliases as $alias)
			{
				// Get the possible language root e.g. /repodir/translation/component/frontend
				$langRoot = realpath($root . '/' . $alias);

				// Make sure the directory exists
				if (empty($langRoot) || !is_dir($langRoot))
				{
					continue;
				}

				$di = new DirectoryIterator($langRoot);

				foreach ($di as $directory)
				{
					if ($directory->isDot() || !$directory->isDir())
					{
						continue;
					}

					$moduleRoot = $directory->getPathname();
					$moduleDI = new DirectoryIterator($moduleRoot);

					foreach($moduleDI as $moduleLangDir)
					{
						if ($moduleLangDir->isDot() || !$moduleLangDir->isDir())
						{
							continue;
						}

						// e.g. $this->sources['site']['en-GB'] =  /repodir/translation/modules/site/foobar/en-GB
						if (!isset($this->sources[$siteSide][$moduleLangDir->getFilename()]))
						{
							$this->sources[$siteSide][$moduleLangDir->getFilename()] = [];
						}

						$this->sources[$siteSide][$moduleLangDir->getFilename()][] = $moduleLangDir->getPathname();
					}
				}
			}
		}
	}

	/**
	 * Scan for plugin translations
	 *
	 * @param   string  $mainRoot  The top-level directory where all of extension translations are stored
	 *
	 * @return  void
	 */
	protected function scanPlugins($mainRoot)
	{
		// Get the root folder of the component translations
		$root = realpath($mainRoot . '/plugins');

		// Make sure the directory exists
		if (empty($root) || !is_dir($root))
		{
			return;
		}

		// Go through all plugin folders
		$folderDI = new DirectoryIterator($root);

		foreach($folderDI as $folder)
		{
			if ($folder->isDot() || !$folder->isDir())
			{
				continue;
			}

			// e.g. /repodir/translation/plugins/system
			$folderPath = $folder->getPathname();
			$pluginsDI = new DirectoryIterator($folderPath);

			// Go through all plugins
			foreach ($pluginsDI as $plugin)
			{
				if ($plugin->isDot() || !$plugin->isDir())
				{
					continue;
				}

				// e.g. /repodir/translation/plugins/system/foobar
				$pluginPath = $plugin->getPathname();
				$langsDI = new DirectoryIterator($pluginPath);

				foreach ($langsDI as $langDir)
				{
					if ($langDir->isDot() || !$langDir->isDir())
					{
						continue;
					}

					// e.g. $this->sources['site']['en-GB'] =  /repodir/translation/plugins/system/foobar/en-GB
					if (!isset($this->sources['admin'][$langDir->getFilename()]))
					{
						$this->sources['admin'][$langDir->getFilename()] = [];
					}
					$this->sources['admin'][$langDir->getFilename()][] = $langDir->getPathname();
				}
			}
		}
	}

	/**
	 * Reduces the language lists to their common elements
	 */
	public function reduceLanguageLists()
	{
		// First, remove languages from $this->sources which do not exist in $this->siteLanguages
		$newSources = [];

		foreach ($this->sources as $siteSide => $languages)
		{
			$newSources[$siteSide] = [];

			foreach ($languages as $lang => $sources)
			{
				if (in_array($lang, $this->siteLanguages[$siteSide]))
				{
					$newSources[$siteSide][$lang] = $sources;
				}
			}
		}

		$this->sources = $newSources;

		// Then, remove site languages with no translation sources
		$newSiteLanguages = [];

		foreach($this->siteLanguages as $siteSite => $languages)
		{
			$newSiteLanguages[$siteSide] = [];

			foreach ($languages as $lang)
			{
				if (isset($this->sources[$siteSide][$lang]) && !empty($this->sources[$siteSide][$lang]))
				{
					$newSiteLanguages[$siteSide][] = $lang;
				}
			}
		}

		$this->siteLanguages = $newSiteLanguages;
	}

	public function relink()
	{
		// Get the paths of all the files to symlink
		$targetPaths = [
			'site'	=> $this->siteRoot . '/language',
			'admin'	=> $this->siteRoot . '/administrator/language',
		];

		$filesToLink = [];

		foreach ($this->sources as $siteSide => $languages)
		{
			$siteSideRoot = $targetPaths[$siteSide];

			foreach($languages as $language => $sourceDirectories)
			{
				$targetLanguageDirectory = $siteSideRoot . '/' . $language;

				foreach ($sourceDirectories as $sourceDir)
				{
					$di = new DirectoryIterator($sourceDir);

					foreach ($di as $file)
					{
						if ($file->isDot() || !$file->isFile())
						{
							continue;
						}

						if ($file->getExtension() != 'ini')
						{
							continue;
						}

						$sourcePath = $file->getPathname();
						$targetPath = $targetLanguageDirectory . '/' . $file->getFilename();
						$filesToLink[$sourcePath] = $targetPath;
					}
				}
			}
		}

		// Unlink the old language files
		$filesToUnlink = array_values($filesToLink);
		$this->unlinkFilesFromList($filesToUnlink);

		// Link the language files
		foreach ($filesToLink as $from => $to)
		{
			symlink_file($from, $to);
		}
	}

	/**
	 * Remove a list of files
	 *
	 * @param array $files
	 *
	 * @return boolean
	 */
	private function unlinkFilesFromList($files)
	{
		foreach ($files as $file)
		{
			if (isLink($file) || is_file($file))
			{
				@unlink(realpath2($file));
			}
		}
	}

}

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
Akeeba Build Tools - Language Relinker
No-configuration extension translation linker
-------------------------------------------------------------------------------
Copyright ©2010-$year Nicholas K. Dionysopoulos / AkeebaBackup.com
Distributed under the GNU General Public License v3 or later
-------------------------------------------------------------------------------

ENDBANNER;

if ($argc < 3)
{
	showUsage();
	die();
}

$config = array();

$config['site'] = $argv[1];
$config['root'] = $argv[2];

$relink = new AkeebaRelinkLanguage($config);
$relink->relink();