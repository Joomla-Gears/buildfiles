<?php
/**
 * Created by PhpStorm.
 * User: nicholas
 * Date: 6/3/2017
 * Time: 4:33 μμ
 */

namespace Akeeba\LinkLibrary;

/**
 * Internal linker for Akeeba projects
 */
class ProjectLinker
{
	/**
	 * List of files to hard link, in the form realFile => hardLink
	 *
	 * @var  array
	 */
	private $hardlink_files = [];

	/**
	 * List of files to symbolic link, in the form realFile => symbolicLink
	 *
	 * @var  array
	 */
	private $symlink_files = [];

	/**
	 * List of folders to symbolic link, in the form realFolder => symbolicLink
	 *
	 * @var  array
	 */
	private $symlink_folders = [];

	/**
	 * The root folder of the repository
	 *
	 * @var  string
	 */
	private $repositoryRoot = '';

	/**
	 * ProjectLinker constructor.
	 *
	 * If $path is a directory it is assumed to be the repository. If it's a file we assume the repository is two levels
	 * up. If it's empty you need to set up manually.
	 *
	 * @param   string|null  $path  The repository path, or the link configuration file, or null.
	 */
	public function __construct($path = null)
	{
		if (!empty($path))
		{
			if (is_dir($path))
			{
				$this->setUpWithPath($path);
			}
			elseif (is_file($path))
			{
				$this->setUpWithFile($path);
			}
		}
	}

	/**
	 * Set up this class given the repository root path.
	 *
	 * An assumption is made that the link.php configuration file is inside the repository's build/template/link.php
	 * path.
	 *
	 * @param   string  $path  The path to the repository root
	 */
	public function setUpWithPath($path)
	{
		if (!is_dir($path))
		{
			throw new \RuntimeException("The folder $path does not exist");
		}

		$path = realpath($path);
		$file = $path . '/build/templates/link.php';

		$this->loadConfig($file);
		$this->setRepositoryRoot($path);
	}

	/**
	 * Set up this class given the link.php file.
	 *
	 * An assumption is made that the repository root is two levels above the file.
	 *
	 * @param   string  $file  The full path to the link.php setup file.
	 */
	public function setUpWithFile($file)
	{
		if (!file_exists($file))
		{
			throw new \RuntimeException("The file $file does not exist");
		}

		$file = realpath($file);
		$path = realpath(dirname($file) . '/../../');

		$this->loadConfig($file);
		$this->setRepositoryRoot($path);
	}

	/**
	 * Load a configuration file.
	 *
	 * @param   string  $file  The full path to the configuration link.php file
	 *
	 * @return  ProjectLinker
	 */
	public function loadConfig($file)
	{
		if (!file_exists($file) || !is_file($file))
		{
			throw new \RuntimeException("Cannot open link configuration file $file");
		}

		/** @var   array  $hardlink_files   Hard link files, set up by the file */
		/** @var   array  $symlink_files    Symbolic link files, set up by the file */
		/** @var   array  $symlink_folders  Symbolic link fodlers, set up by the file */
		require_once $file;

		if (!isset($hardlink_files) || !isset($symlink_files) || !isset($symlink_folders))
		{
			throw new \RuntimeException("The link configuration file $file does not include the necessary arrays");
		}

		$this->setHardlinkFiles($hardlink_files);
		$this->setSymlinkFiles($symlink_files);
		$this->setSymlinkFolders($symlink_folders);

		return $this;
	}

	/**
	 * Get the hard link files
	 *
	 * @return  array
	 */
	public function getHardlinkFiles()
	{
		return $this->hardlink_files;
	}

	/**
	 * Set the hard link files
	 *
	 * @param   array  $hardlink_files
	 *
	 * @return  ProjectLinker
	 */
	public function setHardlinkFiles(array $hardlink_files)
	{
		$this->hardlink_files = $hardlink_files;

		return $this;
	}

	/**
	 * Get the symbolic link files
	 *
	 * @return  array
	 */
	public function getSymlinkFiles()
	{
		return $this->symlink_files;
	}

	/**
	 * @param array $symlink_files
	 *
	 * @return ProjectLinker
	 */
	public function setSymlinkFiles($symlink_files)
	{
		$this->symlink_files = $symlink_files;

		return $this;
	}

	/**
	 * @return array
	 */
	public function getSymlinkFolders()
	{
		return $this->symlink_folders;
	}

	/**
	 * @param array $symlink_folders
	 *
	 * @return ProjectLinker
	 */
	public function setSymlinkFolders($symlink_folders)
	{
		$this->symlink_folders = $symlink_folders;

		return $this;
	}

	/**
	 * Get the repository root
	 *
	 * @return  string
	 */
	public function getRepositoryRoot()
	{
		return $this->repositoryRoot;
	}

	/**
	 * Set the repository root
	 *
	 * @param   string  $repositoryRoot
	 *
	 * @return  ProjectLinker
	 */
	public function setRepositoryRoot($repositoryRoot)
	{
		if (!is_dir($repositoryRoot))
		{
			throw new \RuntimeException("The path $repositoryRoot cannot be the repository root: it is not a directory or it does not exist.");
		}

		$this->repositoryRoot = $repositoryRoot;

		return $this;
	}
}