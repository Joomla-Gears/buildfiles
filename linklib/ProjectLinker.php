<?php
/**
 * Akeeba Build Tools
 *
 * @package        buildfiles
 * @license        GPL v3
 * @copyright      2010-2017 Akeeba Ltd
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
	 * Output verbosity level. 0 = none; 1 (default) = minimal; 2 = each linked file / folder.
	 *
	 * @var  int
	 */
	private $verbosityLevel = 1;

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
	 *
	 * @return  void
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
	 *
	 * @return  void
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
	 * Applies the internal linking
	 *
	 * @return  void
	 */
	public function link()
	{
		if (empty($this->repositoryRoot) || !is_dir($this->repositoryRoot))
		{
			throw new \RuntimeException("You need to specify a valid repository root");
		}

		if (empty($this->hardlink_files) && empty($this->symlink_files) && empty($this->symlink_folders))
		{
			throw new \RuntimeException("You need to specify some files to link");
		}

		if (!empty($this->hardlink_files))
		{
			if ($this->verbosityLevel)
			{
				echo "Hard linking files...\n";
			}

			foreach ($this->hardlink_files as $from => $to)
			{
				if ($this->verbosityLevel >= 2)
				{
					echo "-- $from => $to";
				}

				LinkHelper::makeLink($from, $to, 'link', $this->repositoryRoot);
			}
		}

		if (!empty($this->symlink_files))
		{
			if ($this->verbosityLevel)
			{
				echo "Symlinking files...\n";
			}

			foreach ($this->symlink_files as $from => $to)
			{
				if ($this->verbosityLevel >= 2)
				{
					echo "-- $from => $to";
				}

				LinkHelper::makeLink($from, $to, 'symlink', $this->repositoryRoot);
			}
		}

		if (!empty($this->symlink_folders))
		{
			if ($this->verbosityLevel)
			{
				echo "Symlinking folders...\n";
			}

			foreach ($this->symlink_folders as $from => $to)
			{
				if ($this->verbosityLevel >= 2)
				{
					echo "-- $from => $to";
				}

				LinkHelper::makeLink($from, $to, 'symlink', $this->repositoryRoot);
			}
		}
	}

	/**
	 * Load a configuration file.
	 *
	 * @param   string  $file  The full path to the configuration link.php file
	 *
	 * @return  ProjectLinker
	 */
	public function loadConfig(string $file): ProjectLinker
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
	public function getHardlinkFiles(): array
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
	public function setHardlinkFiles(array $hardlink_files): ProjectLinker
	{
		$this->hardlink_files = $hardlink_files;

		return $this;
	}

	/**
	 * Get the symbolic link files
	 *
	 * @return  array
	 */
	public function getSymlinkFiles(): array
	{
		return $this->symlink_files;
	}

	/**
	 * Set the symbolic link files
	 *
	 * @param   array  $symlink_files
	 *
	 * @return  ProjectLinker
	 */
	public function setSymlinkFiles($symlink_files): ProjectLinker
	{
		$this->symlink_files = $symlink_files;

		return $this;
	}

	/**
	 * Get the symbolic link folders
	 *
	 * @return  array
	 */
	public function getSymlinkFolders(): array
	{
		return $this->symlink_folders;
	}

	/**
	 * Set the symbolic link folders
	 *
	 * @param   array  $symlink_folders
	 *
	 * @return  ProjectLinker
	 */
	public function setSymlinkFolders($symlink_folders): ProjectLinker
	{
		$this->symlink_folders = $symlink_folders;

		return $this;
	}

	/**
	 * Get the repository root
	 *
	 * @return  string
	 */
	public function getRepositoryRoot(): string
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
	public function setRepositoryRoot($repositoryRoot): string
	{
		if (!is_dir($repositoryRoot))
		{
			throw new \RuntimeException("The path $repositoryRoot cannot be the repository root: it is not a directory or it does not exist.");
		}

		$this->repositoryRoot = $repositoryRoot;

		return $this;
	}

	/**
	 * Set the output verbosity level. 0 = none; 1 (default) = minimal; 2 = each linked file / folder.
	 *
	 * @param   int  $verbosityLevel
	 *
	 * @return  ProjectLinker
	 */
	public function setVerbosityLevel(int $verbosityLevel): ProjectLinker
	{
		$this->verbosityLevel = $verbosityLevel;

		return $this;
	}
}