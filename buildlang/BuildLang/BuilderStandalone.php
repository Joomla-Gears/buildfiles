<?php
/**
 * Akeeba Build Files
 *
 * @package        buildfiles
 * @copyright  (c) 2010-2017 Akeeba Ltd
 */

namespace Akeeba\BuildLang;


use DirectoryIterator;
use ZipArchive;

/**
 * Builds the language files of a standalone script
 */
class BuilderStandalone extends Builder
{
	/**
	 * Scan the languages in this repository
	 *
	 * @return  void
	 */
	protected function scanLanguages()
	{
		$this->siteLangFiles  = [];
		$this->adminLangFiles = [];

		foreach (new DirectoryIterator($this->repositoryRoot) as $subfolder)
		{
			if (!$subfolder->isDir() || $subfolder->isDot())
			{
				continue;
			}

			$folderName = $subfolder->getFilename();

			if (in_array($folderName, $this->parameters->ignoreFolders))
			{
				continue;
			}

			$areaDir = $this->repositoryRoot . '/' . $folderName;

			foreach (new DirectoryIterator($areaDir) as $oFile)
			{
				if ($oFile->isDir() || $oFile->isDot())
				{
					continue;
				}

				$langCode = $oFile->getFilename();
				$langCode = substr($langCode, 0, strpos($langCode, '.'));

				if (!isset($this->siteLangFiles[$langCode]))
				{
					$this->siteLangFiles[$langCode] = [];
				}

				$this->siteLangFiles[$langCode][] = $oFile->getRealPath();
			}
		}
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

		// Add the files (nominally, frontend)
		foreach ($this->siteLangFiles[$code] as $filePath)
		{
			$zip->addFile($filePath, basename($filePath));
		}

		$zip->close();

		return $zipPath;
	}

}
