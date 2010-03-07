<?php
/**
 * Copyright Zikula Foundation 2009 - Zikula Application Framework
 *
 * This work is contributed to the Zikula Foundation under one or more
 * Contributor Agreements and licensed to You under the following license:
 *
 * @license GNU/LGPLv2 (or at your option, any later version).
 * @package Zikula
 *
 * Please see the NOTICE file distributed with this source code for further
 * information regarding copyright and licensing.
 */

/**
 * Smarty function to the current users theme
 *
 * Available parameters:
 *   - assign:   If set, the results are assigned to the corresponding variable instead of printed out
 *
 * @param        array       $params      All attributes passed to this function from the template
 * @param        object      &$smarty     Reference to the Smarty object
 * @return       string      the variables content
 */
function smarty_function_usergettheme($params, &$smarty)
{
    $assign = isset($params['assign'])  ? $params['assign']  : null;

    $result = pnUserGetTheme();

    if ($assign) {
        $smarty->assign($assign, $result);
    } else {
        return $result;
    }
}
