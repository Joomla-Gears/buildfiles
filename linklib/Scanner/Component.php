<?php
/**
 * Akeeba Build Tools
 *
 * @package        buildfiles
 * @license        GPL v3
 * @copyright Copyright (c)2010-2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 */

namespace Akeeba\LinkLibrary\Scanner;

use Akeeba\LinkLibrary\MapResult;
use Akeeba\LinkLibrary\ScannerInterface;
use Akeeba\LinkLibrary\ScanResult;
use RuntimeException;

/**
 * Scanner class for Joomla! components
 */
class Component extends AbstractScanner
{
	/**
	 * Constructor.
	 *
	 * The languageRoot is optional and applies only if the languages are stored in a directory other than the one
	 * specified in the extension's XML file.
	 *
	 * @param   string  $extensionRoot  The absolute path to the extension's root folder
	 * @param   string  $languageRoot   The absolute path to the extension's language folder (optional)
	 */
	public function __construct($extensionRoot, $languageRoot = null)
	{
		$this->manifestExtensionType = 'component';

		parent::__construct($extensionRoot, $languageRoot);
	}

	/**
	 * Scans the extension for files and folders to link
	 *
	 * @return  ScanResult
	 */
	public function scan()
	{
		// Get the XML manifest
		$xmlDoc = $this->getXMLManifest();

		if (empty($xmlDoc))
		{
			throw new RuntimeException("Cannot get XML manifest for component in {$this->extensionRoot}");
		}

		// Initialize the result
		$result                = new ScanResult();
		$result->extensionType = 'component';

		// Get the extension name
		$nameNode = $xmlDoc->getElementsByTagName('name');

		if (!$nameNode->length)
		{
			throw new RuntimeException("Cannot get name node in the XML manifest for {$this->extensionRoot}");
		}

		$result->extension = strtolower($nameNode->item(0)->textContent);

		if (substr($result->extension, 0, 4) != 'com_')
		{
			$result->extension = 'com_' . $result->extension;
		}

		// Get the <files> tags for front and back-end
		$result->siteFolder = $this->extensionRoot;
		$allFilesTags       = $xmlDoc->getElementsByTagName('files');

		if ($allFilesTags->length != 2)
		{
			throw new RuntimeException("Not enough <files> tags found in XML manifest for {$result->extension}");
		}

		$nodePath0     = $allFilesTags->item(0)->getNodePath();
		$siteFilesTag  = $allFilesTags->item(0);
		$adminFilesTag = $allFilesTags->item(1);

		if ($nodePath0 != '/extension/files')
		{
			$siteFilesTag  = $allFilesTags->item(1);
			$adminFilesTag = $allFilesTags->item(0);
		}

		// Get the site and admin folders
		if ($siteFilesTag->hasAttribute('folder'))
		{
			$result->siteFolder = $this->extensionRoot . '/' . $siteFilesTag->getAttribute('folder');
		}

		if ($adminFilesTag->hasAttribute('folder'))
		{
			$result->adminFolder = $this->extensionRoot . '/' . $adminFilesTag->getAttribute('folder');
		}

		// Get the media folder
		$result->mediaFolder      = null;
		$result->mediaDestination = null;
		$allMediaTags             = $xmlDoc->getElementsByTagName('media');

		if ($allMediaTags->length >= 1)
		{
			$result->mediaFolder      = $this->extensionRoot . '/' . (string) $allMediaTags->item(0)
			                                                                               ->getAttribute('folder');
			$result->mediaDestination = $allMediaTags->item(0)->getAttribute('destination');
		}

		// Do we have a CLI folder
		$result->cliFolder = $this->extensionRoot . '/cli';

		if (!is_dir($result->cliFolder))
		{
			$result->cliFolder = '';
		}

		// Get the <languages> tags for front and back-end
		$xpath = new \DOMXPath($xmlDoc);

		// Get frontend language files from the frontend <languages> tag
		$result->siteLangPath  = null;
		$result->siteLangFiles = [];
		$frontEndLanguageNodes = $xpath->query('/extension/languages');

		foreach ($frontEndLanguageNodes as $node)
		{
			list($languageRoot, $languageFiles) = $this->scanLanguageNode($node);

			if (!empty($languageFiles))
			{
				$result->siteLangFiles = $languageFiles;
				$result->siteLangPath  = $languageRoot;
			}
		}

		// Get backend language files from the backend <languages> tag
		$result->adminLangPath  = null;
		$result->adminLangFiles = [];
		$backEndLanguageNodes   = $xpath->query('/extension/administration/languages');

		foreach ($backEndLanguageNodes as $node)
		{
			list($languageRoot, $languageFiles) = $this->scanLanguageNode($node);

			if (!empty($languageFiles))
			{
				$result->adminLangFiles = $languageFiles;
				$result->adminLangPath  = $languageRoot;
			}
		}

		// Scan language files in a separate root, if one is specified
		if (!empty($this->languageRoot))
		{
			$langPath  = $this->languageRoot . '/component/frontend';
			$langFiles = $this->scanLanguageFolder($langPath);

			if (!empty($langFiles))
			{
				$result->siteLangPath  = $langPath;
				$result->siteLangFiles = $langFiles;
			}

			$langPath  = $this->languageRoot . '/component/backend';
			$langFiles = $this->scanLanguageFolder($langPath);

			if (!empty($langFiles))
			{
				$result->adminLangPath  = $langPath;
				$result->adminLangFiles = $langFiles;
			}
		}

		return $result;
	}

	/**
	 * Parses the last scan and generates a link map
	 *
	 * @return  MapResult
	 */
	public function map()
	{
		$scan = $this->getScanResults();
		$result = parent::map();

		// Frontend and backend directories
		$dirs = [
			$scan->siteFolder  => $this->siteRoot . '/components/' . $scan->extension,
			$scan->adminFolder => $this->siteRoot . '/administrator/components/' . $scan->extension,
		];

		$result->dirs = array_merge($result->dirs, $dirs);

		return $result;
	}

	/**
	 * Detect extensions of type Component in the repository and return an array of ScannerInterface objects for them.
	 *
	 * @param   string  $repositoryRoot  The repository root to scan
	 *
	 * @return  ScannerInterface[]
	 */
	public static function detect(string $repositoryRoot): array
	{
		// Components are always in one specific path
		$path = $repositoryRoot . '/component';

		if (!is_dir($path))
		{
			return [];
		}

		// Figure out the language root to use
		$languageRoot     = null;
		$translationsRoot = self::getTranslationsRoot($repositoryRoot);

		if ($translationsRoot)
		{
			$languageRoot = $translationsRoot . '/component';

			if (!is_dir($languageRoot))
			{
				$languageRoot = null;
			}
		}

		// Get the extension ScannerInterface object
		$extension          = new Component($path, $translationsRoot);

		return [$extension];
	}

}
