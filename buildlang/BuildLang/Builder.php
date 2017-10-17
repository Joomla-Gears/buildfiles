<?php
/**
 * Akeeba Build Files
 *
 * @package        buildfiles
 * @copyright  (c) 2010-2017 Akeeba Ltd
 */

namespace Akeeba\BuildLang;


use Akeeba\Engine\Postproc\Connector\S3v4\Acl;
use Akeeba\Engine\Postproc\Connector\S3v4\Input;
use Akeeba\LinkLibrary\Scanner\Component;
use Akeeba\LinkLibrary\Scanner\Library;
use Akeeba\LinkLibrary\Scanner\Module;
use Akeeba\LinkLibrary\Scanner\Plugin;
use Akeeba\LinkLibrary\Scanner\Template;
use Akeeba\LinkLibrary\ScannerInterface;
use RuntimeException;
use ZipArchive;

class Builder
{
	/**
	 * Absolute path to the root of the repository
	 *
	 * @var  string
	 */
	private $repositoryRoot = '';

	/**
	 * Configuration parameters
	 *
	 * @var  Parameters
	 */
	private $parameters;

	/**
	 * Administrator language files
	 *
	 * @var  array
	 */
	private $adminLangFiles = [];

	/**
	 * Site language files
	 *
	 * @var  array
	 */
	private $siteLangFiles = [];

	/**
	 * Builder constructor.
	 *
	 * @param   string     $repositoryRoot Absolute path to the repository root
	 * @param   Parameters $parameters     Configuration parameters
	 */
	public function __construct(string $repositoryRoot, Parameters $parameters)
	{
		$this->repositoryRoot = $repositoryRoot;
		$this->parameters     = $parameters;

		$this->scanLanguages();
	}

	/**
	 * Get the unique language codes for both frontend and backend
	 *
	 * @return  array
	 */
	public function getLanguageCodes(): array
	{
		$siteLangCodes  = array_keys($this->siteLangFiles);
		$adminLangCodes = array_keys($this->adminLangFiles);
		$codes          = array_merge($siteLangCodes, $adminLangCodes);

		return array_unique($codes);
	}

	public function buildAll()
	{
		$langCodes     = $this->getLanguageCodes();
		$packages      = [];
		$tempDirectory = sys_get_temp_dir();
		$s3            = $this->parameters->s3;
		$bucket        = $this->parameters->s3Bucket;
		$path          = $this->parameters->s3Path;
		$softwareSlug  = $this->parameters->packageNameURL;

		// Build all packages
		foreach ($langCodes as $code)
		{
			try
			{
				// Add the successfully built packages to a list
				$tempPath        = $this->buildPackageFor($code, $tempDirectory);
				$packages[$code] = basename($tempPath);
			}
			catch (RuntimeException $e)
			{
				// Ignore packages that failed to build
				continue;
			}

			// Upload the temporary package file to S3 and delete it afterwards
			$uploadPath = trim($path, '/') . '/' . trim($softwareSlug, '/') . '/' . $packages[$code];
			echo "Uploading $code to s3://$bucket/$uploadPath\n";
			/**/
			$inputDefinition = Input::createFromFile($tempPath);
			$s3->putObject($inputDefinition, $bucket, $uploadPath, Acl::ACL_PUBLIC_READ);
			unset($inputDefinition);
			/**/

			@unlink($tempPath);
		}

		// Build and upload the HTML index file
		$tempHtml   = $this->buildHTML($packages);
		$uploadPath = trim($path, '/') . '/' . trim($softwareSlug, '/') . '/index.html';
		echo "Uploading index.html to s3://$bucket/$uploadPath\n";

		$inputDefinition = Input::createFromData($tempHtml);
		$s3->putObject($inputDefinition, $bucket, $uploadPath, Acl::ACL_PUBLIC_READ);
		unset($inputDefinition);
	}

	protected function buildHTML(array $packages): string
	{
		$langTable = '';

		foreach ($packages as $code => $baseName)
		{
			// TODO Query the % complete through the Weblate API
			$percent   = 0;
			$info      = new LanguageInfo($code);
			$url       = 'https://' . $this->parameters->s3CDNHostname . '/' .
				$this->parameters->s3Path . '/' .
				$this->parameters->packageNameURL . '/' .
				$baseName;
			$langTable .= <<< HTML
        <tr>
            <td>
                <span class="flag-icon flag-icon-{$info->getCountry()}" title="{$info->getName()}"></span>
            </td>
            <td class="hidden-xs">
                $code
            </td>
            <td>
                {$info->getName()}
                <span class="visible-xs text-muted small">
                    [{$percent}%]
                </span>
            </td>
            <td class="hidden-xs">
                <div class="progress">
                    <div class="progress-bar" role="progressbar" aria-valuenow="{$percent}" aria-valuemin="0"
                         aria-valuemax="100" style="width: {$percent}%;">
                        <span>{$percent}%</span>
                    </div>
                </div>
            </td>
            <td>
                <a class="btn btn-link" href="$url">
                    <span class="glyphicon glyphicon-download-alt"></span>
                    Download
                </a>
            </td>
        </tr>

HTML;
		}

		$replacements = [
			'[SOFTWARE]'       => $this->parameters->softwareName,
			'[PACKAGENAME]'    => $this->parameters->packageName,
			'[PACKAGENAMEURL]' => $this->parameters->packageNameURL,
			'[AUTHORNAME]'     => $this->parameters->authorName,
			'[AUTHORURL]'      => $this->parameters->authorUrl,
			'[LICENSE]'        => $this->parameters->license,
			'[DATE]'           => gmdate('Y-m-d H:i:s T'),
			'[YEAR]'           => gmdate('Y'),
			'[LANGTABLE]'      => $langTable,
		];

		$inFile = rtrim($this->repositoryRoot, '/\\') . '/' . $this->parameters->prototypeHTML;
		$html   = file_get_contents($inFile);

		if ($html !== false)
		{
			$html = str_replace(array_keys($replacements), array_values($replacements), $html);
		}

		return $html;
	}

	/**
	 * Builds the language ZIP package for a specific language
	 *
	 * @param   string $code            Language code, e.g. en-GB
	 * @param   string $targetDirectory The target directory where the ZIP file will be built
	 *
	 * @return  string  The full path to the ZIP file created by this script
	 */
	protected function buildPackageFor(string $code, string $targetDirectory): string
	{
		$langCodes = $this->getLanguageCodes();

		if (!in_array($code, $langCodes))
		{
			throw new \OutOfBoundsException("Can not build XML manifest for $code because it does not exist in the repository.");
		}

		$baseFileName = $this->parameters->packageName . '-' . $code . '.zip';
		$zipPath      = rtrim($targetDirectory, '/\\') . '/' . $baseFileName;

		if (is_file($zipPath))
		{
			@unlink($zipPath);
		}

		$zip          = new ZipArchive();
		$createStatus = $zip->open($zipPath, ZipArchive::CREATE);

		if ($createStatus !== true)
		{
			throw new \RuntimeException("Can not create ZIP file $zipPath");
		}

		// Add a manifest
		$manifestName = basename($baseFileName, '.zip') . '.xml';
		$manifest     = $this->getManifestXMLFor($code);
		$zip->addFromString($manifestName, $manifest);

		// Add backend files
		if (isset($this->adminLangFiles[$code]) && count($this->adminLangFiles[$code]))
		{
			foreach ($this->adminLangFiles[$code] as $filePath)
			{
				$zip->addFile($filePath, 'backend/' . basename($filePath));
			}
		}

		// Add frontend files
		if (isset($this->siteLangFiles[$code]) && count($this->siteLangFiles[$code]))
		{
			foreach ($this->siteLangFiles[$code] as $filePath)
			{
				$zip->addFile($filePath, 'frontend/' . basename($filePath));
			}
		}

		$zip->close();

		return $zipPath;
	}

	/**
	 * Returns the XML manifest file for a language
	 *
	 * @param   string $code
	 *
	 * @return  string
	 */
	private function getManifestXMLFor(string $code): string
	{
		$langCodes = $this->getLanguageCodes();
		$langInfo  = new LanguageInfo($code);

		if (!in_array($code, $langCodes))
		{
			throw new \OutOfBoundsException("Can not build XML manifest for $code because it does not exist in the repository.");
		}

		$xmlSource         = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<extension type="file" version="1.6" method="upgrade" client="site">
    <name />
    <author />
    <authorurl />
	<copyright />
	<license />
    <version />
    <creationDate />
    <description />
	<fileset />
</extension>
XML;
		$xml               = new \SimpleXMLElement($xmlSource, LIBXML_COMPACT | LIBXML_NONET);
		$xml->name         = $this->parameters->packageName . ' - ' . $langInfo->getName();
		$xml->author       = $this->parameters->authorName;
		$xml->authorurl    = $this->parameters->authorUrl;
		$xml->copyright    = sprintf('Copyright (C) 2010-%s %s. All rights reserved.', gmdate('Y'), $this->parameters->authorName);
		$xml->license      = $this->parameters->license;
		$xml->version      = $this->parameters->version;
		$xml->creationDate = gmdate('d M Y');;
		$xml->description = sprintf('%s language files for %s', $langInfo->getName(), $this->parameters->softwareName);

		/** @var \SimpleXMLElement $fileset */
		$fileset = $xml->fileset;

		// Add administrator languages to the manifest
		if (isset($this->adminLangFiles[$code]) && count($this->adminLangFiles[$code]))
		{
			$adminContainer = $fileset->addChild('files');
			$adminContainer->addAttribute('folder', 'backend');
			$adminContainer->addAttribute('target', sprintf('administrator/language/%s', $langInfo->getCode()));

			foreach ($this->adminLangFiles[$code] as $filePath)
			{
				$adminContainer->addChild('filename', basename($filePath));
			}
		}

		// Add site languages to the manifest
		if (isset($this->siteLangFiles[$code]) && count($this->siteLangFiles[$code]))
		{
			$adminContainer = $fileset->addChild('files');
			$adminContainer->addAttribute('folder', 'frontend');
			$adminContainer->addAttribute('target', sprintf('language/%s', $langInfo->getCode()));

			foreach ($this->siteLangFiles[$code] as $filePath)
			{
				$adminContainer->addChild('filename', basename($filePath));
			}
		}

		return $xml->asXML();
	}

	/**
	 * Scan the languages in this repository
	 *
	 * @return  void
	 */
	private function scanLanguages()
	{
		// Discover all the extensions in the repository
		/** @var ScannerInterface[] $extensions */
		$extensions = [];
		$extensions = array_merge($extensions, Component::detect($this->repositoryRoot));
		$extensions = array_merge($extensions, Library::detect($this->repositoryRoot));
		$extensions = array_merge($extensions, Module::detect($this->repositoryRoot));
		$extensions = array_merge($extensions, Plugin::detect($this->repositoryRoot));
		$extensions = array_merge($extensions, Template::detect($this->repositoryRoot));

		$this->siteLangFiles  = [];
		$this->adminLangFiles = [];

		// Scan the language files for each extension and add them to the global list
		foreach ($extensions as $extension)
		{
			$scanResults = $extension->getScanResults();

			if (is_array($scanResults->siteLangFiles) && !empty($scanResults->siteLangFiles))
			{
				$this->siteLangFiles = array_merge_recursive($this->siteLangFiles, $scanResults->siteLangFiles);
			}

			if (is_array($scanResults->adminLangFiles) && !empty($scanResults->adminLangFiles))
			{
				$this->adminLangFiles = array_merge_recursive($this->adminLangFiles, $scanResults->adminLangFiles);
			}
		}
	}
}
