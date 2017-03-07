<?php
/**
 * Akeeba Build Tools - System linker
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

\Akeeba\LinkLibrary\LinkHelper::makeLink($source, $dest, $type);