<?php
/**
 * Akeeba Build Files
 *
 * @package    buildfiles
 * @copyright  (c) 2010-2018 Akeeba Ltd
 */

//require_once 'phing/Task.php';

/**
 * Class RelinkSiteTask
 *
 * Relinks the extensions contained in the repository to the defined Joomla! site.
 *
 * Example:
 *
 * <relink site="/Path/To/Your/Site" repository="/path/to/repository" />
 */
class RelinkSiteTask extends Task
{
	/**
	 * The path to the repository containing all the extensions
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

		$relink = new \Akeeba\LinkLibrary\Relink($this->repository);
		$relink->setVerbose(true);
		$relink->relink($this->site);

		return true;
	}


}
