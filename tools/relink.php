<?php
/**
 * Akeeba Build Tools - Relinker
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

if (stristr(php_uname(), 'windows'))
{
	define('AKEEBA_RELINK_WINDOWS', 1);
}

/**
 * Is this path a symlink?
 *
 * @param   string  $path  The path to test
 *
 * @return  bool  True if it is a symlink
 */
function isLink($path)
{
	if (defined('AKEEBA_RELINK_WINDOWS'))
	{
		return file_exists($path);
	}

	return is_link($path);
}

/**
 * Create a directory symlink
 *
 * @param   string  $from  Directory which already exists
 * @param   string  $to    Path to the symlink we'll create
 *
 * @return  void
 */
function symlink_dir($from, $to)
{
	if (is_dir($to))
	{
		$cmd = 'rm -rf "' . $to . '"';

		if (defined('AKEEBA_RELINK_WINDOWS'))
		{
			$cmd = 'rmdir /s /q "' . $to . '"';
		}

		exec($cmd);
	}

	if (defined('AKEEBA_RELINK_WINDOWS'))
	{
		$cmd = 'mklink /D "' . $to . '" "' . $from . '"';

		exec($cmd);

		return;
	}

	@symlink($from, $to);
}

/**
 * Create a file symlink
 *
 * @param   string  $from  File which already exists
 * @param   string  $to    Path to the symlink we'll create
 *
 * @return  void
 */
function symlink_file($from, $to)
{
	if (file_exists($to))
	{
		$cmd = 'rm -f "' . $to . '"';

		if (defined('AKEEBA_RELINK_WINDOWS'))
		{
			$cmd = 'del /f /q "' . $to . '"';
		}

		exec($cmd);
	}

	if (defined('AKEEBA_RELINK_WINDOWS'))
	{
		$cmd = 'mklink "' . $to . '" "' . $from . '"';

		exec($cmd);

		return;
	}

	@symlink($from, $to);
}

/**
 * Create a file hardlink
 *
 * @param   string  $from  File which already exists
 * @param   string  $to    Path to the hardlink we'll create
 *
 * @return  void
 */
function hardlink_file($from, $to)
{
	if (file_exists($to))
	{
		$cmd = 'rm -f "' . $to . '"';

		if (defined('AKEEBA_RELINK_WINDOWS'))
		{
			$cmd = 'del /f /q "' . $to . '"';
		}

		exec($cmd);
	}

	if (defined('AKEEBA_RELINK_WINDOWS'))
	{
		$cmd = 'mklink /H "' . $to . '" "' . $from . '"';

		exec($cmd);

		return;
	}

	@link($from, $to);
}

/**
 * Required on Windows to turn all forward slashes to backslashes and, conversely, when on Linux / Mac OS X convert all
 * backslashes to slashes.
 *
 * @param   string  $path  The path to convert
 *
 * @return  string  The converted path
 */
function realpath2($path)
{
	if (defined('AKEEBA_RELINK_WINDOWS'))
	{
		return str_replace('/', '\\', $path);
	}

	return str_replace('\\', '/', $path);
}

class AkeebaRelink
{
	/**
	 * The path to the sources
	 *
	 * @var   string
	 */
	private $repositoryRoot = null;

	/**
	 * The path to the site's root
	 *
	 * @var   string
	 */
	private $siteRoot = null;

	/**
	 * Information about the modules
	 *
	 * @var   array
	 */
	private $modules = array();

	/**
	 * Information about the plugins
	 *
	 * @var   array
	 */
	private $plugins = array();

	/**
	 * Information about the component
	 *
	 * @var   array
	 */
	private $component = array();

	/**
	 * Information about the templates
	 *
	 * @var   array
	 */
	private $templates = array();

	/**
	 * Public constructor. Initialises the class with the user-supplied information.
	 *
	 * @param   array   $config  Configuration parameters. We need root and site.
	 */
	public function __construct($config = array())
	{
		if (!array_key_exists('root', $config))
		{
			$config['root'] = dirname(__FILE__);
		}

		if (!array_key_exists('site', $config))
		{
			throw new InvalidArgumentException("You have not specified the site root.");
		}

		$this->repositoryRoot = $config['root'];
		$this->siteRoot       = $config['site'];

		// Load information about the bundled extensions
		$this->_scanComponent();
		$this->_fetchModules();
		$this->_fetchPlugins();
		$this->_fetchTemplates();
	}

	/**
	 * Gets the information for all included modules
	 *
	 * @return  void
	 */
	private function _fetchModules()
	{
		// Check if we have site/admin subdirectories, or just a bunch of modules
		$scanPath = $this->repositoryRoot . '/modules';

		$paths = [
			$scanPath,
		];

		if (is_dir($scanPath . '/admin') || is_dir($scanPath . '/site'))
		{
			$paths = [
				$scanPath . '/admin',
				$scanPath . '/site',
			];
		}

		// Iterate directories
		$this->modules = [];

		foreach ($paths as $path)
		{
			if (!is_dir($path) && !isLink($path))
			{
				continue;
			}

			foreach (new DirectoryIterator($path) as $fileInfo)
			{
				if ($fileInfo->isDot() || !$fileInfo->isDir())
				{
					continue;
				}

				$modPath = $path . '/' . $fileInfo->getFilename();
				$info    = $this->_scanModule($modPath);

				if (!is_array($info) || !array_key_exists('module', $info))
				{
					continue;
				}

				$this->modules[] = $info;
			}
		}
	}

	/**
	 * Gets the information for all included plugins
	 *
	 * @return  void
	 */
	private function _fetchPlugins()
	{
		// Check if we have site/admin subdirectories, or just a bunch of modules
		$scanPath = $this->repositoryRoot . '/plugins';

		$possibleFolders   = ['system', 'content', 'user', 'search', 'finder'];
		$hasPossibleFolder = false;

		foreach ($possibleFolders as $folder)
		{
			if (is_dir($scanPath . '/' . $folder))
			{
				$hasPossibleFolder = true;
				break;
			}
		}

		$paths = [
			$scanPath,
		];

		if ($hasPossibleFolder)
		{
			$paths = [];

			foreach (new DirectoryIterator($scanPath) as $fileInfo)
			{
				if ($fileInfo->isDot() || !$fileInfo->isDir())
				{
					continue;
				}

				$paths[] = $scanPath . '/' . $fileInfo->getFilename();
			}
		}

		// Iterate directories
		$this->plugins = [];

		foreach ($paths as $path)
		{
			if (!is_dir($path) && !isLink($path))
			{
				continue;
			}

			foreach (new DirectoryIterator($path) as $fileInfo)
			{
				if ($fileInfo->isDot() || !$fileInfo->isDir())
				{
					continue;
				}

				$plgPath = $path . '/' . $fileInfo->getFilename();
				$info    = $this->_scanPlugin($plgPath);

				if (!is_array($info) || !array_key_exists('plugin', $info))
				{
					continue;
				}

				$this->plugins[] = $info;
			}
		}
	}

	/**
	 * Gets the information for all included templates
	 *
	 * @return  void
	 */
	private function _fetchTemplates()
	{
		// Check if we have site/admin subdirectories, or just a bunch of templates
		$scanPath = $this->repositoryRoot . '/templates';

		$paths = [
			$scanPath,
		];

		if (is_dir($scanPath . '/admin') || is_dir($scanPath . '/site'))
		{
			$paths = [
				$scanPath . '/admin',
				$scanPath . '/site',
			];
		}

		// Iterate directories
		$this->templates = [];

		foreach ($paths as $path)
		{
			if (!is_dir($path) && !isLink($path))
			{
				continue;
			}

			foreach (new DirectoryIterator($path) as $fileInfo)
			{
				if ($fileInfo->isDot() || !$fileInfo->isDir())
				{
					continue;
				}

				$tplPath = $path . '/' . $fileInfo->getFilename();
				$info    = $this->_scanTemplate($tplPath);

				if (!is_array($info) || !array_key_exists('template', $info))
				{
					continue;
				}

				$this->templates[] = $info;
			}
		}
	}

	/**
	 * Scans a module directory to fetch the extension information
	 *
	 * @param   string  $path  The module path to scan
	 *
	 * @return  array
	 */
	private function _scanModule($path)
	{
		// Find the XML files
		foreach (new DirectoryIterator($path) as $fileInfo)
		{
			if ($fileInfo->isDot() || !$fileInfo->isFile())
			{
				continue;
			}

			$fname = $fileInfo->getFilename();

			if (substr($fname, -4) != '.xml')
			{
				continue;
			}

			$xmlDoc = new DOMDocument;
			$xmlDoc->load($path . '/' . $fname, LIBXML_NOBLANKS | LIBXML_NOCDATA | LIBXML_NOENT | LIBXML_NONET);

			$rootNodes    = $xmlDoc->getElementsByTagname('install');
			$altRootNodes = $xmlDoc->getElementsByTagname('extension');

			if ($altRootNodes->length >= 1)
			{
				unset($rootNodes);
				$rootNodes = $altRootNodes;
			}

			if ($rootNodes->length < 1)
			{
				unset($xmlDoc);
				continue;
			}

			$root = $rootNodes->item(0);

			if (!$root->hasAttributes())
			{
				unset($xmlDoc);
				continue;
			}

			if ($root->getAttribute('type') != 'module')
			{
				unset($xmlDoc);
				continue;
			}

			$module = '';
			$files  = $xmlDoc->getElementsByTagName('files')->item(0)->childNodes;

			/** @var DOMElement $file */
			foreach ($files as $file)
			{
				if ($file->hasAttributes())
				{
					$module = $file->getAttribute('module');
				}
			}

			$langFolder = null;
			$langFiles  = [];

			if ($xmlDoc->getElementsByTagName('languages')->length >= 1)
			{
				$langTag    = $xmlDoc->getElementsByTagName('languages')->item(0);
				$langFolder = $path . '/' . $langTag->getAttribute('folder');
				$langFiles  = [];

				foreach ($langTag->childNodes as $langFile)
				{
					if (!($langFile instanceof DOMElement))
					{
						continue;
					}

					$tag               = $langFile->getAttribute('tag');
					$lfPath            = $langFolder . '/' . $langFile->textContent;
					$langFiles[$tag][] = $lfPath;
				}
			}

			if (empty($module))
			{
				unset($xmlDoc);
				continue;
			}

			$ret = [
				'module'    => $module,
				'path'      => $path,
				'client'    => $root->getAttribute('client'),
				'langPath'  => $langFolder,
				'langFiles' => $langFiles,
			];

			unset($xmlDoc);

			return $ret;
		}
	}

	/**
	 * Scans a plugin directory to fetch the extension information
	 *
	 * @param   string  $path  The plugin path to scan
	 *
	 * @return  array
	 */
	private function _scanPlugin($path)
	{
		// Find the XML files
		foreach (new DirectoryIterator($path) as $fileInfo)
		{
			if ($fileInfo->isDot() || !$fileInfo->isFile())
			{
				continue;
			}

			$fname = $fileInfo->getFilename();

			if (substr($fname, -4) != '.xml')
			{
				continue;
			}

			$xmlDoc = new DOMDocument;
			$xmlDoc->load($path . '/' . $fname, LIBXML_NOBLANKS | LIBXML_NOCDATA | LIBXML_NOENT | LIBXML_NONET);

			$rootNodes    = $xmlDoc->getElementsByTagname('install');
			$altRootNodes = $xmlDoc->getElementsByTagname('extension');

			if ($altRootNodes->length >= 1)
			{
				unset($rootNodes);
				$rootNodes = $altRootNodes;
			}

			if ($rootNodes->length < 1)
			{
				unset($xmlDoc);
				continue;
			}

			$root = $rootNodes->item(0);

			if (!$root->hasAttributes())
			{
				unset($xmlDoc);
				continue;
			}

			if ($root->getAttribute('type') != 'plugin')
			{
				unset($xmlDoc);
				continue;
			}

			$folder = $root->getAttribute('group');

			$plugin = '';
			$files  = $xmlDoc->getElementsByTagName('files')->item(0)->childNodes;

			/** @var DOMElement $file */
			foreach ($files as $file)
			{
				if ($file->hasAttributes())
				{
					$plugin = $file->getAttribute('plugin');
				}
			}

			$langFolder = null;
			$langFiles  = [];

			if ($xmlDoc->getElementsByTagName('languages')->length >= 1)
			{
				$langTag    = $xmlDoc->getElementsByTagName('languages')->item(0);
				$langFolder = $path . '/' . $langTag->getAttribute('folder');
				$langFiles  = [];

				foreach ($langTag->childNodes as $langFile)
				{
					if (!($langFile instanceof DOMElement))
					{
						continue;
					}

					$tag               = $langFile->getAttribute('tag');
					$lfPath            = $langFolder . '/' . $langFile->textContent;
					$langFiles[$tag][] = $lfPath;
				}
			}

			if (empty($plugin))
			{
				unset($xmlDoc);
				continue;
			}

			$ret = [
				'plugin'    => $plugin,
				'folder'    => $folder,
				'path'      => $path,
				'langPath'  => $langFolder,
				'langFiles' => $langFiles,
			];

			unset($xmlDoc);

			return $ret;
		}
	}

	/**
	 * Scans a template directory to fetch the extension information
	 *
	 * @param   string   $path  The template path to scan
	 *
	 * @return  array
	 */
	private function _scanTemplate($path)
	{
		// Find the XML files
		foreach (new DirectoryIterator($path) as $fileInfo)
		{
			if ($fileInfo->isDot() || !$fileInfo->isFile())
			{
				continue;
			}

			$fname = $fileInfo->getFilename();

			if (substr($fname, -4) != '.xml')
			{
				continue;
			}

			$xmlDoc = new DOMDocument;
			$xmlDoc->load($path . '/' . $fname, LIBXML_NOBLANKS | LIBXML_NOCDATA | LIBXML_NOENT | LIBXML_NONET);

			$rootNodes    = $xmlDoc->getElementsByTagname('install');
			$altRootNodes = $xmlDoc->getElementsByTagname('extension');

			if ($altRootNodes->length >= 1)
			{
				unset($rootNodes);
				$rootNodes = $altRootNodes;
			}

			if ($rootNodes->length < 1)
			{
				unset($xmlDoc);
				continue;
			}

			$root = $rootNodes->item(0);

			if (!$root->hasAttributes())
			{
				unset($xmlDoc);
				continue;
			}

			if ($root->getAttribute('type') != 'template')
			{
				unset($xmlDoc);
				continue;
			}

			$template = strtolower($xmlDoc->getElementsByTagName('name')->item(0)->nodeValue);

			$langFolder = null;
			$langFiles  = [];

			if ($xmlDoc->getElementsByTagName('languages')->length >= 1)
			{
				$langTag    = $xmlDoc->getElementsByTagName('languages')->item(0);
				$langFolder = $path . '/' . $langTag->getAttribute('folder');
				$langFiles  = [];

				foreach ($langTag->childNodes as $langFile)
				{
					if (!($langFile instanceof DOMElement))
					{
						continue;
					}

					$tag               = $langFile->getAttribute('tag');
					$lfPath            = $langFolder . '/' . $langFile->textContent;
					$langFiles[$tag][] = $lfPath;
				}
			}

			if (empty($template))
			{
				unset($xmlDoc);
				continue;
			}

			$ret = [
				'template'  => $template,
				'path'      => $path,
				'client'    => $root->getAttribute('client'),
				'langPath'  => $langFolder,
				'langFiles' => $langFiles,
			];

			unset($xmlDoc);

			return $ret;
		}
	}

	/**
	 * Scan the component directory and get some useful info
	 *
	 * @return  void
	 */
	private function _scanComponent()
	{
		$path = $this->repositoryRoot . '/component';

		$this->component = [
			'component'      => '',
			'siteFolder'     => '',
			'adminFolder'    => '',
			'mediaFolder'    => '',
			'cliFolder'      => '',
			'siteLangPath'   => '',
			'siteLangFiles'  => '',
			'adminLangPath'  => '',
			'adminLangFiles' => '',
		];

		if (!is_dir($path))
		{
			return;
		}

		// Find the XML files
		foreach (new DirectoryIterator($path) as $fileInfo)
		{
			if ($fileInfo->isDot() || !$fileInfo->isFile())
			{
				continue;
			}

			$fname = $fileInfo->getFilename();

			if (substr($fname, -4) != '.xml')
			{
				continue;
			}

			$xmlDoc = new DOMDocument;
			$xmlDoc->load($path . '/' . $fname, LIBXML_NOBLANKS | LIBXML_NOCDATA | LIBXML_NOENT | LIBXML_NONET);

			$rootNodes    = $xmlDoc->getElementsByTagname('install');
			$altRootNodes = $xmlDoc->getElementsByTagname('extension');

			if ($altRootNodes->length >= 1)
			{
				unset($rootNodes);
				$rootNodes = $altRootNodes;
			}

			if ($rootNodes->length < 1)
			{
				unset($xmlDoc);
				continue;
			}

			$root = $rootNodes->item(0);

			if (!$root->hasAttributes())
			{
				unset($xmlDoc);
				continue;
			}

			if ($root->getAttribute('type') != 'component')
			{
				unset($xmlDoc);
				continue;
			}

			// Get the component name
			$component = strtolower($xmlDoc->getElementsByTagName('name')->item(0)->textContent);

			if (substr($component, 0, 4) != 'com_')
			{
				$component = 'com_' . $component;
			}

			// Get the <files> tags for front and back-end
			$siteFolder   = $path;
			$allFilesTags = $xmlDoc->getElementsByTagName('files');
			$nodePath0    = $allFilesTags->item(0)->getNodePath();

			if (in_array($nodePath0, ['/install/files', '/extension/files']))
			{
				$siteFilesTag  = $allFilesTags->item(0);
				$adminFilesTag = $allFilesTags->item(1);
			}
			else
			{
				$siteFilesTag  = $allFilesTags->item(1);
				$adminFilesTag = $allFilesTags->item(0);
			}

			// Get the site and admin folders
			if ($siteFilesTag->hasAttribute('folder'))
			{
				$siteFolder = $path . '/' . $siteFilesTag->getAttribute('folder');
			}

			if ($adminFilesTag->hasAttribute('folder'))
			{
				$adminFolder = $path . '/' . $adminFilesTag->getAttribute('folder');
			}

			// Get the media folder
			$mediaFolder  = null;
			$allMediaTags = $xmlDoc->getElementsByTagName('media');

			if ($allMediaTags->length >= 1)
			{
				$mediaFolder = $path . '/' . $allMediaTags->item(0)->getAttribute('folder');
			}

			// Do we have a CLI folder
			$cliFolder = $path . '/cli';

			if (!is_dir($cliFolder))
			{
				$cliFolder = '';
			}

			// Get the <languages> tags for front and back-end
			$langFolderSite    = $path;
			$langFolderAdmin   = $path;
			$allLanguagesTags  = $xmlDoc->getElementsByTagName('languages');
			$nodePath0         = '';
			$nodePath1         = '';
			$siteLanguagesTag  = '';
			$adminLanguagesTag = '';
			$langFilesSite     = [];
			$langFilesAdmin    = [];

			// Do I have any language tag defined in the "old" way?
			if ($allLanguagesTags->item(0))
			{
				$nodePath0 = $allLanguagesTags->item(0)->getNodePath();

				if ($allLanguagesTags->item(1))
				{
					$nodePath1 = $allLanguagesTags->item(1)->getNodePath();
				}

				if (in_array($nodePath0, ['/install/languages', '/extension/languages']))
				{
					$siteLanguagesTag = $allLanguagesTags->item(0);

					if ($nodePath1)
					{
						$adminLanguagesTag = $allLanguagesTags->item(1);
					}
				}
				else
				{
					$adminLanguagesTag = $allLanguagesTags->item(0);

					if ($nodePath1)
					{
						$siteLanguagesTag = $allLanguagesTags->item(1);
					}
				}

				// Get the site and admin language folders
				if ($siteLanguagesTag)
				{
					if ($siteLanguagesTag->hasAttribute('folder'))
					{
						$langFolderSite = $path . '/' . $siteLanguagesTag->getAttribute('folder');
					}
				}

				if ($adminLanguagesTag)
				{
					if ($adminLanguagesTag->hasAttribute('folder'))
					{
						$langFolderAdmin = $path . '/' . $adminLanguagesTag->getAttribute('folder');
					}
				}

				// Get the frontend languages
				$langFilesSite = [];

				if ($siteLanguagesTag && $siteLanguagesTag->hasChildNodes())
				{
					foreach ($siteLanguagesTag->childNodes as $langFile)
					{
						if (!($langFile instanceof DOMElement))
						{
							continue;
						}

						$tag                   = $langFile->getAttribute('tag');
						$langFilesSite[$tag][] = $langFolderSite . '/' . $langFile->textContent;
					}
				}

				// Get the backend languages
				$langFilesAdmin = [];

				if ($adminLanguagesTag && $adminLanguagesTag->hasChildNodes())
				{
					foreach ($adminLanguagesTag->childNodes as $langFile)
					{
						if (!($langFile instanceof DOMElement))
						{
							continue;
						}

						$tag                    = $langFile->getAttribute('tag');
						$langFilesAdmin[$tag][] = $langFolderAdmin . '/' . $langFile->textContent;
					}
				}
			}

			if (empty($component))
			{
				unset($xmlDoc);
				continue;
			}

			$this->component = [
				'component'      => $component,
				'siteFolder'     => $siteFolder,
				'adminFolder'    => $adminFolder,
				'mediaFolder'    => $mediaFolder,
				'cliFolder'      => $cliFolder,
				'siteLangPath'   => $langFolderSite,
				'siteLangFiles'  => $langFilesSite,
				'adminLangPath'  => $langFolderAdmin,
				'adminLangFiles' => $langFilesAdmin,
			];

			unset($xmlDoc);

			return;
		}
	}

	/**
	 * Maps the folders and files for the component
	 *
	 * @return  array
	 */
	private function _mapComponent()
	{
		$files     = [];
		$hardfiles = [];

		// Frontend and backend directories
		$dirs = [
			$this->component['siteFolder']  =>
				$this->siteRoot . '/components/' . $this->component['component'],
			$this->component['adminFolder'] =>
				$this->siteRoot . '/administrator/components/' . $this->component['component'],
		];

		// Media directory
		if ($this->component['mediaFolder'])
		{
			$dirs[$this->component['mediaFolder']] =
				$this->siteRoot . '/media/' . $this->component['component'];
		}

		// CLI files
		if ($this->component['cliFolder'])
		{
			foreach (new DirectoryIterator($this->component['cliFolder']) as $fileInfo)
			{
				if ($fileInfo->isDot() || !$fileInfo->isFile())
				{
					continue;
				}

				$fname = $fileInfo->getFileName();

				if (substr($fname, -4) != '.php')
				{
					continue;
				}

				$hardfiles[$this->component['cliFolder'] . '/' . $fname] =
					$this->siteRoot . '/cli/' . $fname;
			}
		}

		// Front-end language files
		$basePath = $this->siteRoot . '/language/';

		if (!empty($this->component['siteLangFiles']))
		{
			foreach ($this->component['siteLangFiles'] as $tag => $lfiles)
			{
				$path = $basePath . $tag . '/';

				foreach ($lfiles as $lfile)
				{
					$files[$lfile] = $path . basename($lfile);
				}
			}
		}

		// Back-end language files
		$basePath = $this->siteRoot . '/administrator/language/';

		if (!empty($this->component['adminLangFiles']))
		{
			foreach ($this->component['adminLangFiles'] as $tag => $lfiles)
			{
				$path = $basePath . $tag . '/';

				foreach ($lfiles as $lfile)
				{
					$files[$lfile] = $path . basename($lfile);
				}
			}
		}

		return [
			'dirs'      => $dirs,
			'files'     => $files,
			'hardfiles' => $hardfiles,
		];
	}

	/**
	 * Maps the folders and files for a module
	 *
	 * @param   string  $module  The module path to map
	 *
	 * @return  array
	 */
	private function _mapModule($module)
	{
		$files = [];
		$dirs  = [];

		$basePath = $this->siteRoot . '/';

		if ($module['client'] != 'site')
		{
			$basePath .= 'administrator/';
		}
		$basePath .= 'modules/' . $module['module'];

		$dirs[$module['path']] = $basePath;

		// Language files
		$basePath = $this->siteRoot . '/language/';

		if ($module['client'] != 'site')
		{
			$basePath = $this->siteRoot . '/administrator/language/';
		}

		if (!empty($module['langFiles']))
		{
			foreach ($module['langFiles'] as $tag => $lfiles)
			{
				$path = $basePath . $tag . '/';

				foreach ($lfiles as $lfile)
				{
					$files[$lfile] = $path . basename($lfile);
				}
			}
		}

		return [
			'dirs'  => $dirs,
			'files' => $files,
		];
	}

	/**
	 * Maps the folders and files for a plugin
	 *
	 * @param   string  $plugin  The plugin path to map
	 *
	 * @return  array
	 */
	private function _mapPlugin($plugin)
	{
		$files = [];
		$dirs  = [];

		// Joomla! 1.6 or later -- just link one folder
		$basePath              = $this->siteRoot . '/plugins/' . $plugin['folder'] . '/' . $plugin['plugin'];
		$dirs[$plugin['path']] = $basePath;

		// Language files
		$basePath = $this->siteRoot . '/administrator/language/';

		if (!empty($plugin['langFiles']))
		{
			foreach ($plugin['langFiles'] as $tag => $lfiles)
			{
				$path = $basePath . $tag . '/';

				foreach ($lfiles as $lfile)
				{
					$files[$lfile] = $path . basename($lfile);
				}
			}
		}

		return [
			'dirs'  => $dirs,
			'files' => $files,
		];
	}

	/**
	 * Maps the folders and files for a template
	 *
	 * @param   string  $template  The template path to map
	 *
	 * @return  array
	 */
	private function _mapTemplate($template)
	{
		$files = [];
		$dirs  = [];

		$basePath = $this->siteRoot . '/';

		if ($template['client'] != 'site')
		{
			$basePath .= 'administrator/';
		}

		$basePath .= 'templates/' . $template['template'];

		$dirs[$template['path']] = $basePath;

		// Language files
		$basePath = $this->siteRoot . '/language/';

		if ($template['client'] != 'site')
		{
			$basePath = $this->siteRoot . '/administrator/language/';
		}

		if (!empty($template['langFiles']))
		{
			foreach ($template['langFiles'] as $tag => $lfiles)
			{
				$path = $basePath . $tag . '/';

				foreach ($lfiles as $lfile)
				{
					$files[$lfile] = $path . basename($lfile);
				}
			}
		}

		return [
			'dirs'  => $dirs,
			'files' => $files,
		];
	}

	/**
	 * Unlinks the component
	 *
	 * @return  void
	 */
	public function unlinkComponent()
	{
		if (empty($this->component['component']))
		{
			return;
		}

		echo "Unlinking component " . $this->component['component'] . "\n";

		$dirs  = [];
		$files = [];
		$map   = $this->_mapComponent();
		extract($map);

		$dirs  = array_values($dirs);
		$files = array_values($files);

		$this->unlinkDirectoriesFromList($dirs);

		if (!empty($files))
		{
			$this->unlinkFilesFromList($files);
		}

		if (isset($hardfiles) && !empty($hardfiles))
		{
			$this->unlinkFilesFromList($hardfiles);
		}
	}

	/**
	 * Unlinks the modules
	 *
	 * @return  void
	 */
	public function unlinkModules()
	{
		if (empty($this->modules))
		{
			return;
		}

		foreach ($this->modules as $module)
		{
			echo "Unlinking module " . $module['module'] . ' (' . $module['client'] . ")\n";

			$dirs  = [];
			$files = [];

			$map = $this->_mapModule($module);
			extract($map);

			$dirs  = array_values($dirs);
			$files = array_values($files);

			$this->unlinkDirectoriesFromList($dirs);

			if (!empty($files))
			{
				$this->unlinkFilesFromList($files);
			}
		}
	}

	/**
	 * Unlinks the plugins
	 *
	 * @return  void
	 */
	public function unlinkPlugins()
	{
		if (empty($this->plugins))
		{
			return;
		}

		foreach ($this->plugins as $plugin)
		{
			echo "Unlinking plugin " . $plugin['plugin'] . ' (' . $plugin['folder'] . ")\n";

			$dirs  = [];
			$files = [];

			$map = $this->_mapPlugin($plugin);
			extract($map);

			$dirs  = array_values($dirs);
			$files = array_values($files);

			$this->unlinkDirectoriesFromList($dirs);

			if (!empty($files))
			{
				$this->unlinkFilesFromList($files);
			}
		}
	}

	/**
	 * Unlinks the templates
	 *
	 * @return  void
	 */
	public function unlinkTemplates()
	{
		if (empty($this->templates))
		{
			return;
		}

		foreach ($this->templates as $template)
		{
			echo "Unlinking template " . $template['template'] . ' (' . $template['client'] . ")\n";

			$dirs  = [];
			$files = [];

			$map = $this->_mapTemplate($template);
			extract($map);

			$dirs  = array_values($dirs);
			$files = array_values($files);

			$this->unlinkDirectoriesFromList($dirs);

			if (!empty($files))
			{
				$this->unlinkFilesFromList($files);
			}
		}
	}

	/**
	 * Relinks the component
	 *
	 * @return  void
	 */
	public function linkComponent()
	{
		if (empty($this->component['component']))
		{
			return;
		}

		echo "Linking component " . $this->component['component'] . "\n";

		$dirs  = [];
		$files = [];

		$map = $this->_mapComponent();
		extract($map);

		foreach ($dirs as $from => $to)
		{
			symlink_dir(realpath2($from), realpath2($to));
		}

		foreach ($files as $from => $to)
		{
			symlink_file(realpath2($from), realpath2($to));
		}

		if (isset($hardfiles) && !empty($hardfiles))
		{
			foreach ($hardfiles as $from => $to)
			{
				if (@file_exists(realpath2($to)))
				{
					unlink(realpath2($to));
				}

				hardlink_file(realpath2($from), realpath2($to));
			}
		}
	}

	/**
	 * Relinks the modules
	 *
	 * @return  void
	 */
	public function linkModules()
	{
		if (empty($this->modules))
		{
			return;
		}

		foreach ($this->modules as $module)
		{
			echo "Linking module " . $module['module'] . ' (' . $module['client'] . ")\n";

			$dirs  = [];
			$files = [];

			$map = $this->_mapModule($module);
			extract($map);

			foreach ($dirs as $from => $to)
			{
				symlink_dir(realpath2($from), realpath2($to));
			}

			foreach ($files as $from => $to)
			{
				symlink_file(realpath2($from), realpath2($to));
			}
		}
	}

	/**
	 * Relinks the plugins
	 *
	 * @return  void
	 */
	public function linkPlugins()
	{
		if (empty($this->plugins))
		{
			return;
		}

		foreach ($this->plugins as $plugin)
		{
			echo "Linking plugin " . $plugin['plugin'] . ' (' . $plugin['folder'] . ")\n";

			$dirs  = [];
			$files = [];

			$map = $this->_mapPlugin($plugin);
			extract($map);

			foreach ($dirs as $from => $to)
			{
				symlink_dir(realpath2($from), realpath2($to));
			}

			foreach ($files as $from => $to)
			{
				symlink_file(realpath2($from), realpath2($to));
			}
		}
	}

	/**
	 * Relinks the templates
	 *
	 * @return  void
	 */
	public function linkTemplates()
	{
		if (empty($this->templates))
		{
			return;
		}

		foreach ($this->templates as $template)
		{
			echo "Linking template " . $template['template'] . ' (' . $template['client'] . ")\n";

			$dirs  = [];
			$files = [];

			$map = $this->_mapTemplate($template);
			extract($map);

			foreach ($dirs as $from => $to)
			{
				symlink_dir(realpath2($from), realpath2($to));
			}

			foreach ($files as $from => $to)
			{
				symlink_file(realpath2($from), realpath2($to));
			}
		}
	}

	/**
	 * Remove a list of directories
	 *
	 * @param   array  $dirs  The directories to remove
	 *
	 * @return  bool  True on success
	 */
	private function unlinkDirectoriesFromList($dirs)
	{
		foreach ($dirs as $dir)
		{
			$result = true;

			if (isLink($dir))
			{
				$result = unlink(realpath2($dir));
			}
			elseif (is_dir($dir))
			{
				$result = $this->removeDirectoryRecursive($dir);
			}

			if ($result === false)
			{
				return $result;
			}
		}

		return true;
	}

	/**
	 * Remove a list of files
	 *
	 * @param   array  $files  The files to remove
	 *
	 * @return  bool
	 */
	private function unlinkFilesFromList($files)
	{
		foreach ($files as $file)
		{
			$result = true;

			if (isLink($file) || is_file($file))
			{
				$result = unlink(realpath2($file));
			}

			if ($result === false)
			{
				return $result;
			}
		}

		return true;
	}

	/**
	 * Recursively delete a directory
	 *
	 * @param   string  $dir
	 *
	 * @return  bool
	 */
	private function removeDirectoryRecursive($dir)
	{
		// When the directory is a symlink, don't delete recursively. That would
		// fuck up the plugins.
		if (isLink($dir))
		{
			return @unlink(realpath2($dir));
		}

		$handle = opendir($dir);

		while (false != ($item = readdir($handle)))
		{
			if (!in_array($item, array('.', '..')))
			{
				$path = $dir . '/' . $item;

				if (isLink($path))
				{
					$result = @unlink(realpath2($path));
				}
				elseif (is_file($path))
				{
					$result = @unlink(realpath2($path));
				}
				elseif (is_dir($path))
				{
					$result = $this->removeDirectoryRecursive(realpath2($path));
				}
				else
				{
					$result = @unlink(realpath2($path));
				}

				if (!$result)
				{
					return false;
				}
			}
		}
		closedir($handle);

		if (!rmdir($dir))
		{
			return false;
		}

		return true;
	}
}

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

$config = array();

$config['site'] = $argv[1];
$config['root'] = $argv[2];

$relink = new AkeebaRelink($config);

$relink->unlinkComponent();
$relink->linkComponent();

$relink->unlinkModules();
$relink->linkModules();

$relink->unlinkPlugins();
$relink->linkPlugins();

$relink->unlinkTemplates();
$relink->linkTemplates();