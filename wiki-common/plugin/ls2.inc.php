<?php
// PukiWiki - Yet another WikiWikiWeb clone.
// $Id: ls2.inc.php,v 1.29.7 2011/02/05 11:04:00 Logue Exp $
// Copyright (C)
//   2010-2011  PukiWiki Advance Developers Team
//   2005-2008  PukiWiki Plus! Team
//   2002-2004, 2006-2007,2011 PukiWiki Developers Team
//   2002       panda  http://home.arino.jp/?ls2.inc.php 
//   2002       Y.MASUI GPL2 http://masui.net/pukiwiki/ masui@masui.net (ls.inc.php)
// License: GPL version 2
//
// List plugin 2

// Adv. is not use $_ul_left_margin, $_ul_margin, $_list_pad_str params. USE STYLESHEET!

/*
 * 配下のページや、その見出し(*,**,***)の一覧を表示する
 * Usage
 *  #ls2(pattern[,title|include|link|reverse|compact, ...],heading title)
 *
 * pattern  : 省略するときもカンマが必要
 * 'title'  : 見出しの一覧を表示する
 * 'include': インクルードしているページの見出しを再帰的に列挙する
 * 'link   ': actionプラグインを呼び出すリンクを表示
 * 'reverse': ページの並び順を反転し、降順にする
 * 'compact': 見出しレベルを調整する
 *     PLUGIN_LS2_LIST_COMPACTがTRUEの時は無効(変化しない)
 * heading title: 見出しのタイトルを指定する (linkを指定した時のみ)
 */

// 見出しアンカーの書式
define('PLUGIN_LS2_ANCHOR_PREFIX', '#content_1_');

// 見出しアンカーの開始番号
define('PLUGIN_LS2_ANCHOR_ORIGIN', 0);

// 見出しレベルを調整する(デフォルト値)
define('PLUGIN_LS2_LIST_COMPACT', FALSE);

function plugin_ls2_action()
{
	global $vars;

	$params = array();
	$keys   = array('title', 'include', 'reverse');
	foreach ($keys as $key)
		$params[$key] = isset($vars[$key]);

	$prefix = isset($vars['prefix']) ? $vars['prefix'] : '';
	$body = plugin_ls2_show_lists($prefix, $params);

	return array('body'=>$body,
		'msg'=>str_replace('$1', htmlsc($prefix), T_("List of pages which begin with ' $1'")));
}

function plugin_ls2_convert()
{
	global $vars;

	$params = array(
		'link'    => FALSE,
		'title'   => FALSE,
		'include' => FALSE,
		'reverse' => FALSE,
		'compact' => PLUGIN_LS2_LIST_COMPACT,
		'_args'   => array(),
		'_done'   => FALSE
	);

	$args = func_get_args();
	$prefix = '';
	if (! empty($args)) $prefix = array_shift($args);
	if ($prefix == '')  $prefix = strip_bracket($vars['page']) . '/';

	foreach ($args as $arg)
		plugin_ls2_check_arg($arg, $params);

	$title = (! empty($params['_args'])) ? join(',', $params['_args']) :   // Manual
		str_replace('$1', htmlsc($prefix), T_("List of pages which begin with ' $1'")); // Auto

	if (! $params['link'])
		return plugin_ls2_show_lists($prefix, $params);

	$tmp = array();
	if (isset($params['title']))   $tmp[] = 'title=1';
	if (isset($params['include'])) $tmp[] = 'include=1';

	return '<p><a href="' . get_cmd_uri('ls2',	$prefix) . join('&amp;', $tmp) . '">' .
		$title . '</a></p>' . "\n";
}

function plugin_ls2_show_lists($prefix, & $params)
{
//	global $_ls2_err_nopages;

	$pages = array();
	if ($prefix != '') {
		foreach (auth::get_existpages() as $_page){
			if (strpos($_page, $prefix) === 0)
				$pages[] = $_page;
		}
	} else {
		$pages = auth::get_existpages();
	}

	natcasesort($pages);
	if ($params['reverse']) $pages = array_reverse($pages);

	foreach ($pages as $page) $params['page_ ' . $page] = 0;

	if (empty($pages)) {
		return str_replace('$1', htmlsc($prefix), '<p>' . T_("There is no child page in ' $1'") . '</p>');
	} else {
		$params['result'] = $params['saved'] = array();
		foreach ($pages as $page)
			plugin_ls2_get_headings($page, $params, 1);
		return join("\n", $params['result']) . join("\n", $params['saved']);
	}
}

function plugin_ls2_get_headings($page, & $params, $level, $include = FALSE)
{
	static $_ls2_anchor = 0;

	// ページが未表示のとき
	$is_done = (isset($params["page_$page"]) && $params["page_$page"] > 0);
	if (! $is_done) $params["page_$page"] = ++$_ls2_anchor;

	$s_page = htmlsc($page);
	$title  = $s_page . ' ' . get_pg_passage($page, FALSE);
	$href   = get_page_uri($page);

	plugin_ls2_list_push($params, $level);
	$ret = $include ? '<li>include ' : '<li>';

	if ($params['title'] && $is_done) {
		$ret .= '<a href="' . $href . '" title="' . $title . '">' . $s_page . '</a> ';
		$ret .= '<a href="#list_' . $params["page_$page"] . '"><sup>&uarr;</sup></a>';
		array_push($params['result'], $ret);
		return;
	}

	$ret .= '<a id="list_' . $params["page_$page"] . '" href="' . $href .
		'" title="' . $title . '">' . $s_page . '</a>';
		
	array_push($params['result'], $ret);

	$anchor = PLUGIN_LS2_ANCHOR_ORIGIN;
	$matches = array();
	foreach (get_source($page) as $line) {
		if ($params['title'] && preg_match('/^(\*{1,3})/', $line, $matches)) {
			$id    = make_heading($line);
			$level = strlen($matches[1]);
			$id    = PLUGIN_LS2_ANCHOR_PREFIX . $anchor++;
			plugin_ls2_list_push($params, $level + strlen($level));
			array_push($params['result'],
				'<li><a href="' . $href . $id . '">' . $line . '</a>');
		} else if ($params['include'] &&
			preg_match('/^#include\((.+)\)/', $line, $matches) &&
			is_page($matches[1]))
		{
			plugin_ls2_get_headings($matches[1], $params, $level + 1, TRUE);
		}
	}
}

//リスト構造を構築する
function plugin_ls2_list_push(& $params, $level)
{
	// global $_ul_left_margin, $_ul_margin, $_list_pad_str;

	$result = & $params['result'];
	$saved  = & $params['saved'];
	$cont   = TRUE;
//	$open   = '<ul%s>';
	$close  = '</li></ul>';

	while (count($saved) > $level || (! empty($saved) && $saved[0] != $close))
		array_push($result, array_shift($saved));

	$margin = $level - count($saved);

	// count($saved)を増やす
	while (count($saved) < ($level - 1)) array_unshift($saved, '');

	if (count($saved) < $level) {
		$cont = FALSE;
		array_unshift($saved, $close);

/*
		$left = ($level == $margin) ? $_ul_left_margin : 0;
		if ($params['compact']) {
			$left  += $_ul_margin;   // マージンを固定
			$level -= ($margin - 1); // レベルを修正
		} else {
			$left += $margin * $_ul_margin;
		}
		$str = sprintf($_list_pad_str, $level, $left, $left);
		array_push($result, sprintf($open, $str));
*/
		array_push($result,'<ul>');
	}

	if ($cont) array_push($result, '</li>');
}

// オプションを解析する
function plugin_ls2_check_arg($value, $params)
{
	if ($value == '') {
		$params['_done'] = TRUE;
		return;
	}

	if (! $params['_done']) {
		foreach (array_keys($params) as $param) {
			if (strtolower($value)  == $param &&
			    preg_match('/^[a-z]/', $param)) {
				$params[$param] = TRUE;
				return;
			}
		}
		$params['_done'] = TRUE;
	}

	$params['_args'][] = htmlsc($value); // Link title
}
/* End of file ls2.inc.php */
/* Location: ./wiki-common/plugin/ls2.inc.php */