<?php
// PukiWiki - Yet another WikiWikiWeb clone.
// $Id: touchgraph.inc.php,v 1.11.1 2007/01/21 14:32:39 miko Exp $
// Copyright (C)
//   2005-2007 PukiWiki Plus! Team
//   2003-2005 PukiWiki Developers Team
// License: GPL v2 or (at your option) any later version
//
// Output an index for 'TouchGraph WikiBrowser'
// http://www.touchgraph.com/
//
// Usage: (Check also TGWikiBrowser's sample)
//    java -Dfile.encoding=EUC-JP \
//    -cp TGWikiBrowser.jar;BrowserLauncher.jar com.touchgraph.wikibrowser.TGWikiBrowser \
//    http://<pukiwiki site>/index.php?plugin=touchgraph \
//    http://<pukiwiki site>/index.php? FrontPage 2 true
//
// Note: -Dfile.encoding=EUC-JP (or UTF-8) may not work with Windows OS
//   http://www.simeji.com/wiki/pukiwiki.php?Java%A4%CE%CD%AB%DD%B5 (in Japanese)


function plugin_touchgraph_action()
{
	global $vars;

	pkwk_headers_sent();
	header('Content-type: text/plain');
	if (isset($vars['reverse'])) {
		plugin_touchgraph_ref();
	} else {
		plugin_touchgraph_rel();
	}
	exit;
}

// Normal
function plugin_touchgraph_rel()
{
	foreach (auth::get_existpages() as $page) {
		if (check_non_list($page)) continue;

		$file = CACHE_DIR . encode($page) . '.rel';
		if (file_exists($file)) {
			echo $page;
			$data = file($file);
			foreach(explode("\t", trim($data[0])) as $name) {
				if (check_non_list($name)) continue;
				echo ' ', $name;
			}
			echo "\n";
		}
	}
}

// Reverse
function plugin_touchgraph_ref()
{
	foreach (auth::get_existpages() as $page) {
		if (check_non_list($page)) continue;

		$file = CACHE_DIR . encode($page) . '.ref';
		if (file_exists($file)) {
			echo $page;
			foreach (file($file) as $line) {
				list($name) = explode("\t", $line);
				if (check_non_list($name)) continue;
				echo ' ', $name;
			}
			echo "\n";
		}
	}
}
/* End of file touchgraph.inc.php */
/* Location: ./wiki-common/plugin/touchgraph.inc.php */
