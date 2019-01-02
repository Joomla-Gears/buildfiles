<?php
/**
 * Akeeba Build Tools
 *
 * @package    buildfiles
 * @license    GNU/GPL v3
 * @copyright  Copyright (c)2010-2019 Nicholas K. Dionysopoulos / Akeeba Ltd
 */

/**
 * Akeeba Build Tools - System linker
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