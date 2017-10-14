<?php
/**
 * Akeeba Build Tools - Weblate component JSON exporter
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

use GetOptionKit\OptionCollection;
use GetOptionKit\OptionParser;
use GetOptionKit\OptionPrinter\ConsoleOptionPrinter;
use Cz\Git\GitRepository;
use GetOptionKit\OptionResult;

require_once __DIR__ . '/../vendor/autoload.php';

$year = gmdate('Y');
echo <<<ENDBANNER
Akeeba Build Tools - Weblate component JSON exporter 1.0
Creates a JSON file which lets you import the translations in Weblate as components 
-------------------------------------------------------------------------------
Copyright ©2010-$year Akeeba Ltd
Distributed under the GNU General Public License v3 or later
-------------------------------------------------------------------------------

ENDBANNER;

class OwnGitRepository extends GitRepository
{
	public function getRemoteUrl($name, $push = false)
	{
		$flags = $push ? '--push' : '';

		$command = self::processCommand(["git remote get-url $flags", $name]);

		return $this->extractFromCommand($command);
	}

	public function listRemotes()
	{
		return $this->extractFromCommand('git remote', 'trim');
	}

}

class RepoInfo
{
	private $path;

	private $organization;

	private $repository;

	private $branch;

	public function __construct($path = null)
	{
		if (empty($path))
		{
			$path = getcwd();
		}

		$this->path = $path;

		$this->scan();
	}

	public function scan()
	{
		$repo         = new OwnGitRepository($this->path);
		$remotes      = $repo->listRemotes();
		$remote       = $remotes[0];
		$urls         = $repo->getRemoteUrl($remote);
		$url          = $urls[0];
		$this->branch = $repo->getCurrentBranchName();

		if (strpos($url, 'github.com') === false)
		{
			throw new RuntimeException('The repository must be hosted on GitHub for this script to work');
		}

		$this->parseUrl($url);
	}

	protected function parseUrl($url)
	{
		list ($junk, $repoPath) = explode('github.com', $url);
		$repoPath = ltrim($repoPath, ':/');
		$repoPath = (substr($repoPath, -4) == '.git') ? substr($repoPath, 0, -4) : $repoPath;
		list($this->organization, $this->repository) = explode('/', $repoPath);
	}

	/**
	 * @return null|string
	 */
	public function getPath()
	{
		return $this->path;
	}

	/**
	 * @return mixed
	 */
	public function getOrganization()
	{
		return $this->organization;
	}

	/**
	 * @return mixed
	 */
	public function getRepository()
	{
		return $this->repository;
	}

	/**
	 * @return mixed
	 */
	public function getBranch()
	{
		return $this->branch;
	}
}

class Scanner
{
	private $repo;

	private $langDir = 'translations';

	private $cliOptions;

	public function __construct(RepoInfo $repo, OptionResult $cliOptions)
	{
		$this->repo       = $repo;
		$this->cliOptions = $cliOptions;
		$this->langDir    = $cliOptions->get('directory');
	}

	public function run()
	{
		$myRoot      = $this->repo->getPath() . '/' . $this->langDir;
		$returnArray = [];

		foreach (new DirectoryIterator($myRoot) as $oArea)
		{
			if (!$oArea->isDir() || $oArea->isDot())
			{
				continue;
			}

			$area = $oArea->getFilename();

			$areaDir = $myRoot . '/' . $area;
			$slug    = array();

			switch ($area)
			{
				case 'component':
					$slug[] = 'component';
					break;

				case 'modules':
					$slug[] = 'mod';
					break;

				case 'plugins':
					$slug[] = 'plg';
					break;

				default:
					break;
			}

			if (empty($slug))
			{
				continue;
			}

			foreach (new DirectoryIterator($areaDir) as $oFolder)
			{
				if (!$oFolder->isDir() || $oFolder->isDot())
				{
					continue;
				}

				$folder    = $oFolder->getFilename();
				$slug[]    = $folder;
				$folderDir = $areaDir . '/' . $folder;

				// Is this a component?
				if (is_dir($folderDir . '/en-GB'))
				{
					$returnArray = array_merge($returnArray, $this->generateObjectFor($slug, $folderDir));

					array_pop($slug);

					continue;
				}

				// Is this a module or plugin?
				foreach (new DirectoryIterator($folderDir) as $oExtension)
				{
					if (!$oExtension->isDir() || $oExtension->isDot())
					{
						continue;
					}

					$extension    = $oExtension->getFilename();
					$slug[]       = $extension;
					$extensionDir = $folderDir . '/' . $extension;

					if (is_dir($extensionDir . '/en-GB'))
					{
						$returnArray = array_merge($returnArray, $this->generateObjectFor($slug, $extensionDir));
					}

					array_pop($slug);
				}

				array_pop($slug);
			}
		}

		usort($returnArray, function ($a, $b) {
			if ($a['slug'] == $b['slug'])
			{
				return 0;
			}

			return $a['slug'] < $b['slug'] ? -1 : 1;
		});

		return $returnArray;
	}

	private function generateObjectFor($slugArray, $rootDir)
	{
		$root        = $this->repo->getPath();
		$masterTitle = $this->slugToTitle($slugArray);
		$files       = glob($rootDir . '/en-GB/*.ini');
		$slug        = implode($slugArray, '_');
		$return      = [];

		foreach ($files as $f)
		{
			$title = $masterTitle;

			if (substr($f, -8) == '.sys.ini')
			{
				$title .= ' (system language strings)';
				$slug  .= '_sys';
			}
			elseif (substr($f, -9) == '.menu.ini')
			{
				$title .= ' (menu option strings)';
				$slug  .= '_menu';
			}

			$file_proto    = basename($f);
			$file_proto    = substr($file_proto, 5);
			$file_proto    = $rootDir . '/*/*' . $file_proto;
			$file_proto    = substr($file_proto, strlen($root) + 1);
			$file_template = str_replace('*', 'en-GB', $file_proto);

			$auth = '';

			if ($this->cliOptions->has('username') && $this->cliOptions->has('token'))
			{
				$auth = urlencode($this->cliOptions->get('username')) . ':' . urlencode($this->cliOptions->get('token')) . '@';
			}

			$gitHubRepo = 'https://' . $auth . 'github.com/' . $this->repo->getOrganization() . '/' . $this->repo->getRepository() . '.git';

			$return[] = [
				'name'        => $title,
				'slug'        => $slug,
				'vcs'         => 'git',
				'repo'        => $gitHubRepo,
				'branch'      => $this->repo->getBranch(),
				'filemask'    => $file_proto,
				'template'    => $file_template,
				'file_format' => 'joomla',
				'license'     => 'GNU GPL v3',
				'license_url' => 'https://www.gnu.org/licenses/gpl-3.0.en.html',
			];
		}

		return $return;
	}

	private function slugToTitle(array $slugArray)
	{
		$return = "Language strings for " . implode(" ", $slugArray);

		switch ($slugArray[0])
		{
			case 'component':
				$return = 'Component';
				// 1 [component]: frontend, backend
				$return .= ' ' . ucfirst($slugArray[1]);
				// 2 [component]: n/a
				break;

			case 'mod':
			case 'module':
				// 1 [mod]: admin, site
				$return = ($slugArray[1] == 'site') ? 'Frontend' : 'Backend';
				$return .= ' Module ';
				// 2 [mod]: <module name>
				$return .= $slugArray[2];
				break;

			case 'plg':
			case 'plugin':
				// 1 [mod]: <folder>
				$return = ucfirst($slugArray[1]);
				$return .= ' Plugin ';
				// 2 [mod]: <module name>
				$return .= $slugArray[2];
				break;
		}

		return $return;
	}
}

$specs = new OptionCollection;
$specs->add('u|username:', '* GitHub username for committing language files.')
	->isa('String');
$specs->add('t|token:', '* GitHub personal access token for committing language files.')
	->isa('String');
$specs->add('o|output?', 'Output file (default "weblate.json").')
	->isa('String')
	->defaultValue("weblate.json");
$specs->add('r|repo?', 'Repository working copy to scan (default: current working directory).')
	->isa('String');
$specs->add('d|directory?', 'Language directory in the repository (default: "translations").')
	->isa('String')
	->defaultValue("translations");

try
{
	$parser = new OptionParser($specs);
	$result = $parser->parse($argv);
}
catch (Exception $e)
{
	echo $e->getMessage();
	exit (255);
}

if (!$result->has('username') || !$result->has('token'))
{
	$self = basename($argv[0]);
	echo <<< ERRORPREFIX

Usage:
	$self [arguments]

Available arguments:

ERRORPREFIX;

	$printer = new ConsoleOptionPrinter();

	echo $printer->render($parser->specs);

	exit(1);
}

try
{
	$repoInfo     = new RepoInfo($result->get('repo'));
	$scanner      = new Scanner($repoInfo, $result);
	$returnObject = $scanner->run();
	$json         = json_encode($returnObject, JSON_PRETTY_PRINT);

	file_put_contents($result->get('output'), $json);
}
catch (Exception $e)
{
	echo $e->getMessage();
}
