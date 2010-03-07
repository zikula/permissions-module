<?php
/**
 * Zikula Application Framework
 *
 * @Copyright (c) 2003, 2005 Danilo Segan <danilo@kvota.net>.
 * @copyright (c) 2009, Zikula Development Team
 * @link http://www.zikula.org
 * @version $Id$
 * @license GNU/GPL version 2 (or at your option, any later version).
 */

/**
 * File reader with seek ability
 * Reads whole file at once
 *
 */
class CachedFileReader extends StringReader
{
    public function __construct($filename)
    {
        if (is_readable($filename)) {
            $this->setStream(file_get_contents($filename));
        } else {
            $this->setError(2); // File doesn't exist
        }
    }
}
