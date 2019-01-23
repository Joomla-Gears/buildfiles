<?php
define('IS_WINDOWS', substr(PHP_OS, 0, 3) == 'WIN');

require_once 'phing/Task.php';

class SvnremoveTask extends ExecTask
{
	/**
	 * The working copy.
	 *
	 * @var   string
	 */
	private $workingCopy;

	/** @var string Path to the exported Git copy */
	private $gitExport;

	/**
	 * Sets the path to the working copy
	 *
	 * @param   string  $workingCopy
	 */
	public function setWorkingCopy($workingCopy)
	{
		$this->workingCopy = $workingCopy;
	}

	public function setGitExport($gitExport)
	{
		$this->gitExport = $gitExport;
	}

	/**
	 * Main entry point for task
	 */
	public function main()
	{
		$cwd				= getcwd();
		$this->workingCopy	= realpath($this->workingCopy);
		$this->gitExport	= realpath($this->gitExport);

		/*
		 * Brief explanation of the command:
		 *  - recursive to fetch all the changes in subfolders
		 *  - brief to have only the name of the file
		 *  - redirect errors to /dev/null to suppress any errors
		 *  	- grep for "Only in", this means that the file is only on the SVN trunk and not in the exported Git folder.
		 * 		  Thus it needs to be removed
		 */
		$obsolete_paths = [];
		exec("diff --brief --recursive ".$this->gitExport." ".$this->workingCopy." 2>/dev/null | grep '^Only in'", $obsolete_paths);
		chdir($this->workingCopy);

		$out = [];

		foreach ($obsolete_paths as $line)
		{
			$line = trim($line);

			// We will end up with a bunch of lines like this:
			// 		Only in svn-core/akeebabackupwp/trunk/app/Solo/Pythia/Oracle: Grav.php
			// Let's remove the stating path
			$line = str_ireplace('Only in '.$this->workingCopy, '', $line);

			// We should have something like this:
			//		app/Solo/Pythia/Oracle: Grav.php
			// Let's convert the semi-colon to a forward slash, remove any extra slash and we should be done
			$line = str_replace(': ', '/', $line);
			$line = trim($line, '/');

			$out[] = "\tRemoving ".$line;

			exec('svn rm '.$line);
		}

		chdir($cwd);

		$this->project->setProperty('svn.output', implode("\r\n", $out));
	}
}