<?php
/**
 * Akeeba Build Tools - System linker
 * Copyright (c)2010-2014 Akeeba Ltd
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

define('IS_WINDOWS', substr(PHP_OS, 0, 3) == 'WIN');

/**
 * Translate a windows path
 *
 * @param   string  $p_path  The path to translate
 *
 * @return  string  The translated path
 */
function systemlink_TranslateWinPath( $p_path )
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

/**
 * @param   string  $realFrom
 * @param   string  $realTo
 * @param   string  $type
 *
 * @throws Exception
 */
function systemlink_createLink($realFrom, $realTo, $type = 'symlink')
{
    if(IS_WINDOWS)
    {
        // Windows doesn't play nice with paths containing UNIX path separators
        $realTo   = TranslateWinPath($realTo);
        $realFrom = TranslateWinPath($realFrom);

        // Windows doesn't play nice with relative paths in symlinks
        $realFrom = realpath($realFrom);
    }

    if(is_file($realTo) || is_dir($realTo) || is_link($realTo) || file_exists($realTo))
    {
        if(IS_WINDOWS && is_dir($realTo))
        {
            // Windows can't unlink() directory symlinks; it needs rmdir() to be used instead
            $res = @rmdir($realTo);
        }
        else
        {
            $res = @unlink($realTo);
        }

        if(!$res)
        {
            echo "FAILED UNLINK  : $realTo\n";
        }
    }

    if($type == 'symlink')
    {
        $res = @symlink($realFrom, $realTo);
    }
    elseif($type == 'link')
    {
        $res = @link($realFrom, $realTo);
    }

    if(!$res)
    {
        if($type == 'symlink')
        {
            echo "FAILED SYMLINK : $realTo\n";
        }
        elseif($type == 'link')
        {
            echo ("FAILED LINK    : $realTo\n");
        }
    }
}

$options = getopt('s:d:t:', array('source:', 'destination:', 'type:'));

$source = $options['s'] ? $options['s'] : $options['source'];
$dest   = $options['d'] ? $options['d'] : $options['destination'];
$type   = $options['t'] ? $options['t'] : $options['type'];

// Sanity checks
if(!$source || !$dest || !$type)
{
    echo 'You must supply the source, destination and type arguments';
    return;
}

if(!in_array($type, array('link', 'symlink')))
{
    echo 'Unknown link type: '.$type;
    return;
}

systemlink_createLink($source, $dest, $type);