<?php
// Internal linking script
$hardlink_files = array(
	# Live Update
	'../liveupdate/code/liveupdate.php'			=> 'component/backend/liveupdate/liveupdate.php',
	# Akeeba Engine
	'../backupengine/engine/autoloader.php'		=> 'component/backend/akeeba/autoloader.php',
	'../backupengine/engine/factory.php'		=> 'component/backend/akeeba/factory.php',
	'../backupengine/engine/configuration.php'		=> 'component/backend/akeeba/configuration.php',
	'../backupengine/engine/platform/abstract.php'
												=> 'component/backend/akeeba/platform/abstract.php',
	'../backupengine/engine/platform/interface.php'
												=> 'component/backend/akeeba/platform/interface.php',
	'../backupengine/engine/platform/platform.php'
												=> 'component/backend/akeeba/platform/platform.php',
	# Akeeba Backup Professional
	'../abpro/joomla/cli/akeeba-backup.php'		=> 'component/cli/akeeba-backup.php',
	'../abpro/joomla/cli/akeeba-altbackup.php'	=> 'component/cli/akeeba-altbackup.php',
);

$symlink_files = array(
	# Live Update
	'../liveupdate/code/LICENSE.txt'			=> 'component/backend/liveupdate/LICENSE.txt',
	# OTP Plugin
	'../liveupdate/plugins/system/oneclickaction/LICENSE.txt'
												=> 'plugins/system/oneclickaction/LICENSE.txt',
	'../liveupdate/plugins/system/oneclickaction/oneclickaction.php'
												=> 'plugins/system/oneclickaction/oneclickaction.php',
	'../liveupdate/plugins/system/oneclickaction/oneclickaction.xml'
												=> 'plugins/system/oneclickaction/oneclickaction.xml',
	# Akeeba Backup Professional
	'../abpro/joomla/backend/backup.php'		=> 'component/backend/backup.php',
	'../abpro/joomla/backend/altbackup.php'		=> 'component/backend/altbackup.php',
	'../abpro/joomla/backend/akeeba/plugins'	=> 'component/backend/akeeba/plugins',
	'../abpro/joomla/backend/plugins'			=> 'component/backend/plugins',
	'../abpro/joomla/media/plugins'				=> 'component/media/plugins',
);

$symlink_folders = array(
	# Component translation
	'translations/component/backend/en-GB'		=> 'component/language/backend/en-GB',
	'translations/component/frontend/en-GB'		=> 'component/language/frontend/en-GB',
	# Live Update
	'../liveupdate/code/assets'					=> 'component/backend/liveupdate/assets',
	'../liveupdate/code/classes'				=> 'component/backend/liveupdate/classes',
	'../liveupdate/code/language'				=> 'component/backend/liveupdate/language',
	# OTP Plugin
	'../liveupdate/plugins/system/oneclickaction/sql'
												=> 'plugins/system/oneclickaction/sql',
	'../liveupdate/plugins/system/oneclickaction/language'
												=> 'translations/plugins/system/oneclickaction',
	# FOF
	'../fof/fof'								=> 'component/fof',
	
	# Akeeba Strapper
	'../fof/strapper'							=> 'component/strapper',
	
	# Akeeba Engine
	'../backupengine/engine/abstract'			=> 'component/backend/akeeba/abstract',
	'../backupengine/engine/assets'				=> 'component/backend/akeeba/assets',
	'../backupengine/engine/core'				=> 'component/backend/akeeba/core',
	'../backupengine/engine/drivers'			=> 'component/backend/akeeba/drivers',
	'../backupengine/engine/engines'			=> 'component/backend/akeeba/engines',
	'../backupengine/engine/filters'			=> 'component/backend/akeeba/filters',
	'../backupengine/engine/utils'				=> 'component/backend/akeeba/utils',
	
	# Akeeba Backup Professional
	'../abpro/joomla/backend/akeeba/plugins'	=> 'component/backend/akeeba/plugins',
	'../abpro/joomla/backend/plugins'			=> 'component/backend/plugins',
	'../abpro/joomla/media/plugins'				=> 'component/media/plugins',
);

define('IS_WINDOWS', substr(PHP_OS, 0, 3) == 'WIN');

function TranslateWinPath( $p_path )
{
	$is_unc = false;

	if (IS_WINDOWS)
	{
		// Is this a UNC path?
		$is_unc = (substr($p_path, 0, 2) == '\\\\') || (substr($p_path, 0, 2) == '//');
		// Change potential windows directory separator
		if ((strpos($p_path, '\\') > 0) || (substr($p_path, 0, 1) == '\\')){
			$p_path = strtr($p_path, '\\', '/');
		}
	}

	// Remove multiple slashes
	$p_path = str_replace('///','/',$p_path);
	$p_path = str_replace('//','/',$p_path);

	// Fix UNC paths
	if($is_unc)
	{
		$p_path = '//'.ltrim($p_path,'/');
	}

	return $p_path;
}

function doLink($from, $to, $type = 'symlink')
{
	$path = dirname(__FILE__);
	
	$realTo = $path .'/'. $to;
	$realFrom = $path.'/'.$from;
	if(IS_WINDOWS) {
		// Windows doesn't play nice with paths containing UNIX path separators
		$realTo = TranslateWinPath($realTo);
		$realFrom = TranslateWinPath($realFrom);
		// Windows doesn't play nice with relative paths in symlinks
		$realFrom = realpath($realFrom);
	}
	if(is_file($realTo) || is_dir($realTo) || is_link($realTo) || file_exists($realTo)) {
		if(IS_WINDOWS && is_dir($realTo)) {
			// Windows can't unlink() directory symlinks; it needs rmdir() to be used instead
			$res = @rmdir($realTo);
		} else {
			$res = @unlink($realTo);
		}
		if(!$res) {
			echo "FAILED UNLINK  : $realTo\n";
			return;
		}
	}
	if($type == 'symlink') {
		$res = @symlink($realFrom, $realTo);
	} elseif($type == 'link') {
		$res = @link($realFrom, $realTo);
	}
	if(!$res) {
		if($type == 'symlink') {
			echo "FAILED SYMLINK : $realTo\n";
		} elseif($type == 'link') {
			echo "FAILED LINK    : $realTo\n";
		}
	}
}


echo "Hard linking files...\n";
if(!empty($hardlink_files)) foreach($hardlink_files as $from => $to) {
	doLink($from, $to, 'link');
}

echo "Symlinking files...\n";
if(!empty($symlink_files)) foreach($symlink_files as $from => $to) {
	doLink($from, $to, 'symlink');
}

echo "Symlinking folders...\n";
if(!empty($symlink_folders)) foreach($symlink_folders as $from => $to) {
	doLink($from, $to, 'symlink');
}
