<?php
/**
 * Akeeba Build Files
 *
 * @package    buildfiles
 * @copyright  (c) 2010-2018 Akeeba Ltd
 */

//require_once 'phing/Task.php';

/**
 * Class RelinkWPSiteTask
 *
 * Relinks the plugins contained in the repository to the defined WordPress site.
 * Remember to edit the build/templates/relink-wp.php file in the repository.
 *
 * Example:
 *
 * <relinkwp site="/Path/To/Your/Site" repository="/path/to/repository" />
 */
class RelinkWPSiteTask extends Task
{
	/**
	 * The path to the repository containing the plugin
	 *
	 * @var   string
	 */
	private $repository = null;

	/**
	 * The path to the site's root.
	 *
	 * @var    string
	 */
	private $site = null;

	/**
	 * Set the site root folder
	 *
	 * @param   string  $siteRoot  The new site root
	 *
	 * @return  void
	 */
	public function setSite($siteRoot)
	{
		$this->site = $siteRoot;
	}

	/**
	 * Set the repository root folder
	 *
	 * @param   string  $repository  The new repository root folder
	 *
	 * @return  void
	 */
	public function setRepository(string $repository)
	{
		$this->repository = $repository;
	}

	/**
	 * Main entry point for task.
	 *
	 * @return    bool
	 */
	public function main()
	{
		$this->log("Processing links for " . $this->site, Project::MSG_INFO);

		if (empty($this->repository))
		{
			$this->repository = realpath($this->project->getBasedir() . '/../..');
		}

		if (!is_dir($this->site))
		{
			throw new BuildException("Site root folder {$this->site} is not a valid directory");
		}

		if (!is_dir($this->repository))
		{
			throw new BuildException("Repository folder {$this->repository} is not a valid directory");
		}

		if (!class_exists('Akeeba\\LinkLibrary\\ProjectLinker'))
		{
			require_once __DIR__ . '/../linklib/include.php';
		}

		$linker = new \Akeeba\LinkLibrary\ProjectLinker();
		$linker->setRepositoryRoot($this->repository);

		include $this->repository . '/build/templates/relink-wp.php';

		if (isset($hardlink_files) && is_array($hardlink_files) && !empty($hardlink_files))
		{
			$linker->setHardlinkFiles($this->processPathArray($hardlink_files));
		}

		if (isset($symlink_files) && is_array($symlink_files) && !empty($symlink_files))
		{
			$linker->setSymlinkFiles($this->processPathArray($symlink_files));
		}

		if (isset($symlink_folders) && is_array($symlink_folders) && !empty($symlink_folders))
		{
			$linker->setSymlinkFolders($this->processPathArray($symlink_folders));
		}

		$linker->link();

		return true;
	}

	/**
	 * Process a path array which is in the form
	 * relative repo path => relative site path
	 * into the form
	 * relative repo path => ABSOLUTE site path
	 *
	 * @param   array  $pathArray
	 *
	 * @return  array
	 */
	private function processPathArray(array $pathArray): array
	{
		$temp = [];

		foreach ($pathArray as $local => $sitePath)
		{
			$temp[$local] = $this->site . '/' . $sitePath;
		}

		return $temp;
	}


}
