<?php
/**
 * Zikula Application Framework
 *
 * @copyright (c) 2002, Zikula Development Team
 * @link http://www.zikula.org
 * @version $Id$
 * @license GNU/GPL - http://www.gnu.org/copyleft/gpl.html
 * @package Zikula_System_Modules
 * @subpackage Search
 * @author Patrick Kellum
 * @author Stefano Garuti (ported to pnAPI)
 */

/**
* Main user function
*
* This function is the default function. Call the function to show the search form.
*
* @author Stefano Garuti
* @return string HTML string templated
*/
function search_user_main()
{
    // Security check
    if (!SecurityUtil::checkPermission('Search::', '::', ACCESS_READ)) {
        return LogUtil::registerPermissionError();
    }

    // Create output object
    $pnRender = & pnRender::getInstance('Search');

    // Return the output that has been generated by this function
    return $pnRender->fetch('search_user_main.htm');
}

/*
* Splits the query string into words suitable for a mysql query
*
* This function is ported 'as is' from the old, nonAPI, module
* it is called from each plugin so we can't delete it or change it's name
*
* @author Patrick Kellum
* @param string $q the string to parse and split
* @param string $dbwildcard wrap each word in a DB wildcard character (%)
* @return array an array of words optionally surrounded by '%'
*/
function search_split_query($q, $dbwildcard = true)
{
    if (!isset($q)) {
        return;
    }

    $w = array();
    $stripped = DataUtil::formatForStore($q);
    $qwords = preg_split('/ /', $stripped, -1, PREG_SPLIT_NO_EMPTY);

    foreach($qwords as $word) {
        if ($dbwildcard) {
            $w[] = '%' . $word . '%';
        } else {
            $w[] = $word;
        }
    }

    return $w;
}

/*
* Contruct part of a where clause out of the supplied search parameters
*
*/
function search_construct_where($args, $fields, $mlfield = null)
{
    $where = '';

    if (!isset($args) || empty($args) || !isset($fields) || empty($fields)) {
        return $where;
    }

    if (!empty($args['q'])) {
        $q = DataUtil::formatForStore($args['q']);
        $q = str_replace('%', '\\%', $q);  // Don't allow user input % as wildcard
        $where .= ' (';
        if ($args['searchtype'] !== 'EXACT') {
            $searchwords = search_split_query($q);
            $connector = $args['searchtype'] == 'AND' ? ' AND ' : ' OR ';
        } else {
            $searchwords = array("%{$q}%");
        }
        $start = true;
        foreach($searchwords as $word) {
            $where .= (!$start ? $connector : '') . ' (';
            // I'm not sure if "LIKE" is the best solution in terms of DB portability (PC)
            foreach ($fields as $field) {
                $where .= "{$field} LIKE '$word' OR ";
            }
            $where = substr($where, 0 , -4);
            $where .= ')';
            $start = false;
        }
        $where .= ') ';
    }

    // Check if we're in a multilingual setup
    if (isset($mlfield) && pnConfigGetVar('multilingual') == 1) {
        $currentlang = ZLanguage::getLanguageCode();
        $where .= "AND ({$mlfield} = '$currentlang' OR {$mlfield} = '')";
    }

    return $where;
}

/**
* Generate complete search form
*
* Generate the whole search form, including the various plugins options.
* It uses the Search API's getallplugins() function to find plugins.
*
* @author Patrick Kellum
* @author Stefano Garuti
*
* @return string HTML string templated
*/
function search_user_form($vars)
{
    // Security check
    if (!SecurityUtil::checkPermission('Search::', '::', ACCESS_READ)) {
        return LogUtil::registerPermissionError();
    }

    // get parameter from input
    $vars['q'] = strip_tags(FormUtil::getPassedValue('q', '', 'REQUEST'));
    $vars['searchtype'] = FormUtil::getPassedValue('searchtype', SessionUtil::getVar('searchtype'), 'REQUEST');
    $vars['searchorder'] = FormUtil::getPassedValue('searchorder', SessionUtil::getVar('searchorder'), 'REQUEST');
    $vars['numlimit'] = pnModGetVar('Search', 'itemsperpage', 25);
    // this var allows the headers to not be displayed
    if (!isset($vars['titles']))
      $vars['titles'] = true;

    // set some defaults
    if (!isset($vars['searchtype']) || empty($vars['searchtype'])) {
        $vars['searchtype'] = 'AND';
    }
    if (!isset($vars['searchorder']) || empty($vars['searchorder'])) {
        $vars['searchorder'] = 'newest';
    }

    // reset the session vars for a new search
    SessionUtil::delVar('searchtype');
    SessionUtil::delVar('searchorder');
    SessionUtil::delVar('searchactive');
    SessionUtil::delVar('searchmodvar');

    // get all the search plugins
    $search_modules = pnModAPIFunc('Search', 'user', 'getallplugins');

    if (count($search_modules) > 0) {
        $plugin_options = array();
        foreach($search_modules as $mods) {
            // as every search plugins return a formatted html string
            // we assign it to a generic holder named 'plugin_options'
            // maybe in future this will change
            // we should retrieve from the plugins an array of values
            // and formatting it here according with the module's template
            // we have also to provide some trick to assure the 'backward compatibility'

            $plugin_options[$mods['title']] = pnModAPIFunc($mods['title'], 'search', 'options', $vars);
        }
        // Create output object
        $pnRender = & pnRender::getInstance('Search');
        // add content to template
        $pnRender->assign($vars);
        $pnRender->assign('plugin_options', $plugin_options);

        // Return the output that has been generated by this function
        return $pnRender->fetch('search_user_form.htm');
    } else {
        // Create output object
        $pnRender = & pnRender::getInstance('Search');
        // Return the output that has been generated by this function
        return $pnRender->fetch('search_user_noplugins.htm');
    }
}


/** Class for doing module based access check and URL creation of search result
 *
 * - The module based access is somewhat deprecated (it still works but is not
 *   used since it makes it impossible to count the number of search result).
 * - The URL for each found item is created here. By doing this we only create
 *   URLs for results the user actually view and save some time this way.
 * @package Zikula_System_Modules
 * @subpackage Search
 */
class search_result_checker
{
    // This variable contains a table of all search plugins (indexed by module name)
    var $search_modules = array();


    function search_result_checker($search_modules)
    {
        $this->search_modules = $search_modules;
    }


    // This method is called by DBUtil::selectObjectArrayFilter() for each and every search result.
    // A return value of true means "keep result" - false means "discard".
    // The decision is delegated to the search plugin (module) that generated the result
    function checkResult(&$datarow)
    {
        // Get module name
        $module = $datarow['module'];

        // Get plugin information
        $mod = $this->search_modules[$module];

        $ok = true;

        if (isset($mod['functions'])) {
            foreach ($mod['functions'] as $contenttype => $function) {
                // Delegate check to search plugin
                // (also allow plugin to write 'url' => ... into $datarow by passing it by reference)
                $ok = $ok && pnModAPIFunc($mod['title'], 'search', $function.'_check',
                                          array('datarow'     => &$datarow,
                                                'contenttype' => $contenttype));
            }
        }

        return $ok;
    }
}

/**
* Perform the search then show the results
*
* This function includes all the search plugins, then call every one passing
* an array that contains the string to search for, the boolean operators.
*
* @author Patrick Kellum
* @author Stefano Garuti
* @author Mark West
* @author Jorn Wildt
*
* @return string HTML string templated
*/
function search_user_search()
{
    // Security check
    if (!SecurityUtil::checkPermission('Search::', '::', ACCESS_READ)) {
        return LogUtil::registerPermissionError();
    }

    // get parameter from HTTP input
    $vars = array();
    $vars['q'] = strip_tags(FormUtil::getPassedValue('q', '', 'REQUEST'));
    $vars['searchtype'] = FormUtil::getPassedValue('searchtype', SessionUtil::getVar('searchtype'), 'REQUEST');
    $vars['searchorder'] = FormUtil::getPassedValue('searchorder', SessionUtil::getVar('searchorder'), 'REQUEST');
    $vars['numlimit'] = pnModGetVar('Search', 'itemsperpage', 25);
    $vars['page'] = (int)FormUtil::getPassedValue('page', 1, 'REQUEST');

    // $firstpage is used to identify the very first result page
    // - and to disable calls to plugins on the following pages
    $firstPage = !isset($_REQUEST['page']);

    // The modulename exists in this array as key, if the checkbox was filled
    $active = FormUtil::getPassedValue('active', SessionUtil::getVar('searchactive'), 'REQUEST');

    // All formular data from the modules search plugins is contained in:
    $modvar = FormUtil::getPassedValue('modvar', SessionUtil::getVar('searchmodvar'), 'REQUEST');

    // set some defaults
    if (!isset($vars['searchtype']) || empty($vars['searchtype'])) {
        $vars['searchtype'] = 'AND';
    } else {
        SessionUtil::setVar('searchtype', $vars['searchtype']);
    }
    if (!isset($vars['searchorder']) || empty($vars['searchorder'])) {
        $vars['searchorder'] = 'newest';
    } else {
        SessionUtil::setVar('searchorder', $vars['searchorder']);
    }
    if (!isset($active) || !is_array($active) || empty($active)) {
        $active = array();
    } else {
        SessionUtil::setVar('searchactive', $active);
    }
    if (!isset($modvar) || !is_array($modvar) || empty($modvar)) {
        $modvar = array();
    } else {
        SessionUtil::setVar('searchmodvar', $modvar);
    }

    // work out row index from page number
    $vars['startnum'] = (($vars['page'] - 1) * $vars['numlimit']) + 1;

    if (empty($vars['q'])) {
        LogUtil::registerError (__('Error! You did not enter any keywords to search for.'));
        return pnRedirect(pnModUrl('Search', 'user', 'main'));
    }

    // get all the search plugins
    $search_modules = pnModAPIFunc('Search', 'user', 'getallplugins');

    // Create output object and check caching
    $pnRender = & pnRender::getInstance('Search');
    $pnRender->cache_id = md5($vars['q'] . $vars['searchtype'] . $vars['searchorder'] . pnUserGetVar('uid')) . $vars['page'];
    // check if the contents are cached.
    if ($pnRender->is_cached('search_user_results.htm')) {
        return $pnRender->fetch('search_user_results.htm');
    }

    // Load database stuff
    pnModDBInfoLoad('Search');
    $pntable      = pnDBGetTables();
    $userId       = (int)pnUserGetVar('uid');
    $searchTable  = $pntable['search_result'];
    $searchColumn = $pntable['search_result_column'];

    // Create restriction on result table (so user only sees own results)
    $userResultWhere = "$searchColumn[session] = '" . session_id() . "'";

    // Do all the heavy database stuff on the first page only
    if ($firstPage) {
        // Clear current search result for current user - before showing the first page
        // Clear also older searches from other users.
        $dbType = DBConnectionStack::getConnectionDBType();
        $where  = $userResultWhere;
        if ($dbType=='postgres') {
            $where .= " OR $searchColumn[found] + INTERVAL '8 HOUR' < NOW()" ;
        } else {
            $where .= " OR DATE_ADD($searchColumn[found], INTERVAL 8 HOUR) < NOW()" ;
        }

        DBUtil::deleteWhere ('search_result', $where);

        // Ask active modules to find their items and put them into $searchTable for the current user
        // At the same time convert modules list from numeric index to modname index

        $searchModulesByName = array();
        foreach($search_modules as $mod) {
            // check we've a valid search plugin
            if (isset($mod['functions']) && (empty($active) || isset($active[$mod['title']]))) {
                foreach ($mod['functions'] as $contenttype => $function) {
                    if (isset($modvar[$mod['title']])) {
                        $param = array_merge($vars, $modvar[$mod['title']]);
                    } else {
                        $param = $vars;
                    }
                    $searchModulesByName[$mod['name']] = $mod;
                    $ok = pnModAPIFunc($mod['title'], 'search', $function, $param);
                    if (!$ok) {
                        LogUtil::registerError(__f('Error! \'%1$s\' module returned false in search function \'%2$s\'.', array($mod['title'], $function)));
                        return pnRedirect(pnModUrl('Search', 'user', 'main'));
                    }
                }
            }
        }

        // Count number of found results
        $resultCount = DBUtil::selectObjectCount ('search_result', $userResultWhere);
        SessionUtil::setVar('searchResultCount', $resultCount);
        SessionUtil::setVar('searchModulesByName', $searchModulesByName);
    } else {
        $resultCount = SessionUtil::getVar('searchResultCount');
        $searchModulesByName = SessionUtil::getVar('searchModulesByName');
    }

    // Fetch search result - do sorting and paging in database

    // Figure out what to sort by
    switch ($vars['searchorder']) {
        case 'alphabetical':
            $sort = 'title';
            break;
        case 'oldest':
            $sort = 'created';
            break;
        case 'newest':
            $sort = 'created DESC';
            break;
        default:
            $sort = 'title';
            break;
    }

    // Get next N results from the current user's result set
    // The "checker" object is used to:
    // 1) do secondary access control (deprecated more or less)
    // 2) let the modules add "url" to the found (and viewed) items
    $checker = new search_result_checker($searchModulesByName);
    $sqlResult = DBUtil::selectObjectArrayFilter('search_result', $userResultWhere, $sort,
                                                 $vars['startnum']-1, $vars['numlimit'], '',
                                                 $checker, null);
    // add displayname of modules found
    $cnt = count($sqlResult);
    for ($i=0; $i<$cnt; $i++) {
        $modinfo = pnModGetInfo(pnModGetIDFromName($sqlResult[$i]['module']));
        $sqlResult[$i]['displayname'] = $modinfo['displayname'];
    }

    // Get number of chars to display in search summaries
    $limitsummary = pnModGetVar('Search', 'limitsummary');
    if (empty($limitsummary)) {
        $limitsummary = 200;
    }

    $pnRender->assign('resultcount', $resultCount);
    $pnRender->assign('results', $sqlResult);
    $pnRender->assign(pnModGetVar('Search'));
    $pnRender->assign($vars);
    $pnRender->assign('limitsummary', $limitsummary);

    // log the search if on first page
    if ($firstPage) {
        pnModAPIFunc('Search', 'user', 'log', $vars);
    }

    // Return the output that has been generated by this function
    return $pnRender->fetch('search_user_results.htm');
}


/**
 * display a list of recent searches
 *
 * @author Jorg Napp
 */
function Search_user_recent()
{
    // security check
    if (!SecurityUtil::checkPermission('Search::', '::', ACCESS_READ)) {
        return LogUtil::registerPermissionError();
    }

    // Get parameters from whatever input we need.
    $startnum = (int)FormUtil::getPassedValue('startnum', null, 'GET');

    // we need this value multiple times, so we keep it
    $itemsperpage = pnModGetVar('Search', 'itemsperpage');

    // get the
    $items = pnModApiFunc('Search', 'user', 'getall', array('startnum' => $startnum, 'numitems' => $itemsperpage, 'sortorder' => 'date'));

    // Create output object - this object will store all of our output so that
    // we can return it easily when required
    $pnRender = & pnRender::getInstance('Search');

    // assign the results to the template
    $pnRender->assign('recentsearches', $items);

    // assign the values for the smarty plugin to produce a pager in case of there
    // being many items to display.
    $pnRender->assign('pager', array('numitems'     => pnModAPIFunc('Search', 'user', 'countitems'),
                                     'itemsperpage' => $itemsperpage));

    // Return the output that has been generated by this function
    return $pnRender->fetch('search_user_recent.htm');
}
