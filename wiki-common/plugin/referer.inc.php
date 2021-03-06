<?php
// PukiWiki Advance.
// $Id: referer.inc.php,v 1.10.15 2012/02/05 19:55:00 Logue Exp $
// Copyright (C)
//   2010-2012 PukiWiki Advance DevelopersTeam.
//   2007      PukiWiki Plus! Team
//   2003,2005-2008 Katsumi Saito <katsumi@jo1upk.ymt.prug.or.jp>
// License: GPL
//
// Referer Plugin(Show Related-Link Plugin)

define('CONFIG_REFERER', 'plugin/referer');
define('REFERE_TITLE_LENGTH',70);

// 検索エンジン
// Google
defined('SKEYLIST_SEARCH_URL') or define('SKEYLIST_SEARCH_URL', 'http://www.google.com/search?ie=utf8&amp;oe=utf8&amp;q=');
// Yahoo!
// defined('SKEYLIST_SEARCH_URL') or define('SKEYLIST_SEARCH_URL', 'http://search.yahoo.com/search?ei=UTF-8&p=');

defined('SKEYLIST_MIN_COUNTER') or define('SKEYLIST_MIN_COUNTER', 0);

function plugin_referer_init()
{
	$messages = array(
		'_referer_msg' => array(
			'msg_referer'			=> T_('Referer'),
			'msg_referer_list'		=> T_('Referer List'),
			'msg_no_data'			=> T_('No data'),
			'msg_H0_Refer'			=> T_('Referer'),
			'msg_Hed_LastUpdate'	=> T_('LastUpdate'),
			'msg_Hed_1stDate'		=> T_('First Register'),
			'msg_Hed_RefCounter'	=> T_('RefCounter'),
			'msg_Hed_Referer'		=> T_('Referer'),
			'msg_Fmt_Date'			=> T_('F j, Y, g:i A'),
			'msg_Chr_uarr'			=> T_('&uArr;'),
			'msg_Chr_darr'			=> T_('&dArr;'),
			'msg_disabled'			=> T_('Referer function is disabled.'),
			'msg_notfound'			=> T_('The page you requested was not found.'),
			'msg_searchkey'			=> T_('Search keys'),
			'msg_searchkey_title'	=> T_('All the Search Key of %s'),
			'msg_mutual'			=> T_('Mutual links'),
			'msg_mutual_title'		=> T_('Auto Mutual link of %s')
		)
	);
	
	global $config_referer;

	// config.php
	if (!isset($config_referer))
	{
		$config = new Config(CONFIG_REFERER);
		$config->read();
		$config_referer['spam'] = $config->get('SPAM');
		$config_referer['misc'] = $config->get('MISC');
		$config_referer['key']  = $config->get('KEY');
		unset($config);
	}
	set_plugin_messages($messages);
}

function plugin_referer_action()
{
	global $vars, $referer;
	global $_referer_msg;

	// Setting: Off
	if (! $referer) return array('msg'=>$_referer_msg['msg_referer'],'body'=>$_referer_msg['msg_disabled']);
	
	$page = isset($vars['page']) ? $vars['page'] : null;
	$kind = isset($vars['kind']) ? $vars['kind'] : '';
	$max = isset($vars['max']) ? (int)$vars['max'] :  -1;

	if (is_page($page)) {
		check_readable($page, false);
		$data = ref_get_data($page);
		if (!isset($data)) return '<p>'.$_referer_msg['msg_no_data'].'</p>';

		switch ($kind){
			case 'skeylist':	// searchkeylist.inc.phpのなごり
			case 'searchkey':
				return array(
					'msg' => sprintf($_referer_msg['msg_searchkey_title'],$page),
					'body'=> plugin_referer_searchkeylist($data, $max)
				);
			break;
			case 'linklist':	// linklist.inc.phpのなごり
			case 'mutual':
				return array(
					'msg' => sprintf($_referer_msg['msg_mutual_title'],$page),
					'body'=> plugin_referer_mutual($data, $max)
				);
			break;
			case 'referer':
				return array(
					'msg'  => $_referer_msg['msg_H0_Refer'],
					'body' => plugin_referer_body($data)
				);
			break;
			default:
				return array(
					'msg'  => $_referer_msg['msg_H0_Refer'],
					'body' => 
						'<div class="tabs" role="application">'."\n".'<ul role="tablist">'."\n".
						'<li role="tab"><a href="'.get_cmd_uri('referer',$page,null,array('kind'=>'referer')).'">'.$_referer_msg['msg_referer'].'</a></li>'."\n".
						'<li role="tab"><a href="'.get_cmd_uri('referer',$page,null,array('kind'=>'searchkey')).'">'.$_referer_msg['msg_searchkey'].'</a></li>'."\n".
						'<li role="tab"><a href="'.get_cmd_uri('referer',$page,null,array('kind'=>'mutual')).'">'.$_referer_msg['msg_mutual'].'</a></li>'."\n".
						'</ul>'."\n".'</div>'
				);
		}
	}
	$pages = auth::get_existpages(REFERER_DIR, '.ref');

	if (empty($pages)) {
		return array('msg'=>$_referer_msg['msg_referer'], 'body'=>$_referer_msg['msg_notfound']);
	} else {
		return array(
			'msg'  => $_referer_msg['msg_referer_list'],
			'body' => page_list($pages, 'referer', FALSE)
		);
	}
}

// Referer 明細行編集
function plugin_referer_body($data)
{
	global $_referer_msg;
	global $referer;
	global $config_referer;

	// 構成定義ファイル読込
	$IgnoreHost = array_merge($config_referer['spam'], $config_referer['misc']);

	$sort_last = '0d';
	$sort_1st  = '1d';
	$sort_ctr  = '2d';
		usort($data, create_function('$a,$b', 'return $b[0] - $a[0];'));
		$arrow_last = $_referer_msg['msg_Chr_darr'];
		$sort_last = '0a';

	$body = '';
	$ctr = 0;
	foreach ($data as $x) {
		// 'scheme', 'host', 'port', 'user', 'pass', 'path', 'query', 'fragment'
		// 0:最終更新日時, 1:初回登録日時, 2:参照カウンタ, 3:Referer ヘッダ, 4:利用可否フラグ(1は有効)
		@list($ltime, $stime, $count, $url, $enable) = $x;

		$uri = isset($url) ? parse_url($url) : null;
		
		if (strpos($uri['host'],'.') == '') continue; // ホスト名にピリオドが１つもない
		if (plugin_referer_ignore_check($uri['host'])) continue;

		$sw = 0;
		foreach ($IgnoreHost as $y) {
			if (strpos($uri['host'],$y) !== FALSE) {
				$sw = 1;
				continue;
			}
		}
		if ($sw) continue;
		if ($count < 0) continue;

		// 項目不正の場合の対応
		// カウンタが数値ではない場合は、表示を抑止
		if (! is_numeric($count)) continue;

		$sw_ignore = plugin_referer_ignore_check($url);
		if ($sw_ignore && $referer > 1) continue;

		// 非ASCIIキャラクタ(だけ)をURLエンコードしておく BugTrack/440
		$e_url = htmlsc(preg_replace('/([" \x80-\xff]+)/e', 'rawurlencode("$1")', $url));
		$s_url = @mb_convert_encoding(rawurldecode($url), SOURCE_ENCODING);
		$s_url = htmlsc(mb_strimwidth($s_url,0,REFERE_TITLE_LENGTH,'...'));

		$lpass = get_passage($ltime, FALSE); // 最終更新日時からの経過時間
		$spass = get_passage($stime, FALSE); // 初回登録日時からの経過時間
		$ldate = get_date($_referer_msg['msg_Fmt_Date'], $ltime); // 最終更新日時文字列
		$sdate = get_date($_referer_msg['msg_Fmt_Date'], $stime); // 初回登録日時文字列

		$body .=
			'		<tr>' . "\n" .
			'			<td class="style_td">' . $ldate . ' ('. $lpass .')</td>' . "\n";

		$body .= ($count == 1) ?
			'			<td class="style_td">N/A</td>' . "\n" :
			'			<td class="style_td">' . $sdate .' ('. $spass .')</td>' . "\n";

		$body .= '			<td class="style_td" style="text-align:right;">' . $count . '</td>' . "\n";

		// 適用不可データのときはアンカーをつけない
		$body .= ($sw_ignore) ?
			'			<td class="style_td">' . $s_url . '</td>' . "\n" :
			'			<td class="style_td"><a href="' . $e_url . '" rel="nofollow noreferer">' . $s_url . '</a></td>' . "\n";

		$body .= '		</tr>' . "\n";
		$ctr++;
	}

	if ($ctr === 0) return '<p>'.$_referer_msg['msg_no_data'].'</p>';

	return <<<EOD
<table summary="Referer" class="style_table">
	<thead>
		<tr>
			<th class="style_th">{$_referer_msg['msg_Hed_LastUpdate']}</th>
			<th class="style_th">{$_referer_msg['msg_Hed_1stDate']}</th>
			<th class="style_th" style="text-align:right">{$_referer_msg['msg_Hed_RefCounter']}</th>
			<th class="style_th">{$_referer_msg['msg_Hed_Referer']}</th>
		</tr>
	</thead>
	<tbody>
$body
	</tbody>
</table>
EOD;
}

function plugin_referer_set_color()
{
	static $color;

	if (! isset($color)) {
		// Default color
		$color = array('cur' => '#99CCFF', 'etc' => 'transparent');

		$config = new Config(CONFIG_REFERER);
		$config->read();
		$pconfig_color = $config->get('COLOR');
		unset($config);

		$matches = array();
		foreach ($pconfig_color as $x)
			$color[$x[0]] = htmlsc(
				preg_match('/BGCOLOR\(([^)]+)\)/si', $x[1], $matches) ?
					$matches[1] : $x[1]);
	}
	return $color;
}

function plugin_referer_ignore_check($url)
{
	static $ignore_url;

	// config.php
	if (! isset($ignore_url)) {
		$config = new Config(CONFIG_REFERER);
		$config->read();
		$ignore_url = $config->get('IGNORE');
		unset($config);
	}

	foreach ($ignore_url as $x)
		if (strpos($url, $x) !== FALSE)
			return 1;
	
	return 0;
}

function parse_query($query) {
	$queryParts = explode('&', $query);

	$params = array();
	foreach ($queryParts as $param) {
		$item = explode('=', $param);
		if (isset($item[1])){
			$params[$item[0]] = $item[1];
		}
	}
	return $params;
} 

/** searchkeylist **************************************************************************************/
function plugin_referer_searchkeylist($data, $max){
	global $_referer_msg;
	$data = searchkeylist_analysis($data);

	// 0:検索キー 1:参照カウンタ
	usort($data,create_function('$a,$b','return $b[1] - $a[1];'));
	$data = searchkeylist_print($data,$max);

	return (empty($data)) ? $_referer_msg['msg_no_data'] : $data;
}


// データを解析
function searchkeylist_analysis($data)
{
	global $config_referer;
	$sum = array();

	// 0:最終更新日時 1:初回登録日時 2:参照カウンタ 3:Referer ヘッダ 4:利用可否フラグ(1は有効)
	foreach ($data as $x)
	{
		if (isset($x[4]) && $x[4] === 1) continue;
		// 'scheme', 'host', 'port', 'user', 'pass', 'path', 'query', 'fragment'
		$url = isset($x[3]) ? parse_url($x[3]) : null;
		if (empty($url['host'])) continue;
		if (strpos($url['host'],'.') == '') continue; // ホスト名にピリオドが１つもない
		if (plugin_referer_ignore_check($url['host'])) continue;

		if (!empty($url['query'])){
			// querystringの解析
			$q = parse_query($url['query']);
			// 検索キーかの判定
			foreach ($config_referer['key'] as $y){
				if( array_key_exists($y[0],$q) ) {	// キーが含まれている場合
					$term = rawurldecode($q[$y[0]]);
					if ( (strpos($term,'cache:') === 0 )) continue; // google のキャッシュなどの場合
					
					if ($url['host'] === 'www.baidu.com'){
						$parm = mb_convert_encoding($term, SOURCE_ENCODING ,
							((isset($q['ie']) && $q['ie'] === 'utf-8') ? 'auto' : 'GB2312'));	// Baiduは通常GB2312で処理しているため
					}else{
						$parm = mb_convert_encoding($term, SOURCE_ENCODING,'auto');
					}
					$parm = searchkeylist_convert_key($parm); // 検索キーを名寄せする
					
					if (!isset($sum[$parm])){ $sum[$parm] = 0; }
					$sum[$parm] += $x[2]; // 参照カウンタ

					break;
				}
			}
		}
	}
	$rc = array();
	$i = 0;
	foreach ($sum as $key=>$val)
	{
		if ($key !== ''){
			$rc[$i][0] = $key;	// 検索キー
			$rc[$i][1] = $val;	// 参照カウンタ
			$i++;
		}
	}
	return $rc;
}

// 検索キーを整形する
function searchkeylist_convert_key($x)
{
	$rc = '';

	// "K" : 「半角片仮名」を「全角片仮名」に変換
	// "V" :  濁点付きの文字を一文字に変換
	// "a" : 「全角」英数字を「半角」に変換
	// "s" : 「全角」スペースを「半角」に変換
	$x = mb_convert_kana($x, 'KVas');

	// Yahooなど他のエンジン対応
	$x = str_replace(
		array('+', '#', '*', ' and ', ' AND ', '|', '?'),
		' ',
		$x
	);
	$x = str_replace('"', '', $x);  // 	"は除去		"

	// 文字の途中に入っている連続するスペースを１つにする
	$tok = strtok($x,' ');
	while($tok) {
		$rc .= $tok.' ';
		$tok = strtok(' ');
	}

	// 前後のスペースを取り除く
	$rc = trim($rc);
	return $rc;
}

// データを加工
function searchkeylist_print($data,$max)
{
	$rc = array();

	if ($max > 0) {
		$rc[] = '<h2>'.sprintf($_searchkeylist_msg['h5_title'],$max).'</h2>';
		$data = array_splice($data,0,$max);
	}
	$rc[] = '<ul class="referer_searchkey_list">';

	foreach ($data as $x)
	{
		if (SKEYLIST_MIN_COUNTER > $x[1]) continue;
		if ( !strcasecmp('utf-8',SOURCE_ENCODING) ) {
			$key = $x[0];
		} else {
			$key = mb_convert_encoding($x[0],'utf-8',SOURCE_ENCODING);
		}
		$rc[] = '<li><a href="' . SKEYLIST_SEARCH_URL.rawurlencode($key).'" rel="nofollow noreferer">'.$x[0].'</a> <var>('.$x[1].')</var></li>';
	}

	$rc[] = '</ul>';
	return join("\n",$rc);
}

/** linklist.inc.php ******************************************************************************/
function plugin_referer_mutual($data, $max){
	global $_referer_msg;
	$data = linklist_analysis($data);
	// 0:検索キー 1:参照カウンタ
	usort($data,create_function('$a,$b','return $b[1] - $a[1];'));
	$data = linklist_print($data,$max,0);
	return (empty($data)) ? $_referer_msg['msg_no_data'] : $data;
}

// データを解析
function linklist_analysis($data)
{
	global $config_referer;

	// 構成定義ファイル読込
	$IgnoreHost = array_merge($config_referer['spam'], $config_referer['misc']);

	$rc = array();
	$i = 0;

	// 自サイトの特定
	$my = parse_url(get_script_uri());
	$my = $my['host'];

	// 0:最終更新日時 1:初回登録日時 2:参照カウンタ 3:Referer ヘッダ 4:利用可否フラグ(1は有効)
	foreach ($data as $x)
	{
		if (isset($x[4]) && $x[4] === 1) continue;
		// 'scheme', 'host', 'port', 'user', 'pass', 'path', 'query', 'fragment'
		$url = isset($x[3]) ? parse_url($x[3]) : null;
		if (empty($url['host'])) continue;
		if (strpos($url['host'],'.') == '') continue; // ホスト名にピリオドが１つもない
		if (plugin_referer_ignore_check($url['host'])) continue;

		$sw = 0;
		foreach ($IgnoreHost as $y) {
			if (strpos($url['host'],$y) !== FALSE) {
				$sw = 1;
				continue;
			}
		}
		if ($sw) continue;

		if (strpos($url['host'],$my) !== FALSE) continue;

		$sw = 0;
		// queryストリングの解析
		
		if (isset($url['query'])){
			$tok = strtok($url['query'],'&');
			while($tok) {
				list($key,$parm)= preg_split ('/=/', $tok); // キーと値に分割
				$tok = strtok('&'); // 次の処理の準備

				// 検索キーかの判定
				$skey = '';

				foreach ($config_referer['key'] as $y)
				{
					if ( (strpos($key,$y[0]) === 0 )) {
						$skey = $y[0];
						continue;
					}
				}
				if ($skey !== $key) continue;
				if (empty($parm)) continue; // 値が入っていない場合

				// 検索エンジンからきたもの
				$sw = 1;
				break;
			}
		}

		// 検索エンジン以外 かつ 架空ホスト以外 の場合は蓄積
		// if (!$sw and linklist_testipaddress($url['host']) )
		// 検索エンジン以外の場合は蓄積
		if (!$sw)
		{
			$rc[$i][0] = $x[3];	// 3:Referer ヘッダ
			$rc[$i][1] = $x[2];	// 参照カウンタ
			$i++;
		}
		
	}
	return $rc;
}

// データを加工
function linklist_print($data,$max,$title)
{
	global $_linklist_msg;

	// 無制限は、-1 のために判断
	if ($max > 0)
	{
		$data = array_splice($data,0,$max);
	}
	$i = count($data);
	if ($i == 0) return;

	$rc = '';
	if ($title)
	{
		$rc .= '<h2>'.$_linklist_msg['h5_title'].' ';
		$rc .= ($max > 0) ? $max : $i;
		$rc .= "</h2>\n";
	}

	$rc .= '<ul class="linklist">'."\n";
	foreach ($data as $x)
	{
		$str = rawurldecode($x[0]);
		$str = mb_convert_encoding($str,SOURCE_ENCODING,'auto');
		$tmp = '<a href="'.$x[0].'" rel="nofollow noreferer">'.$str.'</a><span class="linklist_counter">('.$x[1].')</span>';
		$rc .= '<li>'.$tmp."</li>\n";
	}
	$rc .= "</ul>\n";
	return $rc;
}



// ホスト名からIPアドレスに変換して評価する
function linklist_testipaddress ($host)
{
	$ip = gethostbyname($host); // ホスト名からIPアドレスを得る
	if ($ip == $host)
	{
		// そもそも IPアドレスが指定されている場合の考慮
		$name = @gethostbyaddr($host);	// IPアドレスからホスト名を得る
		if (!empty($name)) return 1;	// lookup できた
		return 0; // 変換不能
	}
	return 1; // IP アドレス変換できた
}


/* End of file referer.inc.php */
/* Location: ./wiki-common/plugin/referer.inc.php */