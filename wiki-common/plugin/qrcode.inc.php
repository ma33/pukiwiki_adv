<?php
/////////////////////////////////////////////////
// PukiWiki - Yet another WikiWikiWeb clone.
//
// $Id: qrcode.inc.php,v 0.9.1 2011/04/03 22:41:00 Logue Exp $
//
/*
*内容
QR画像を生成するプラグイン

注意：
このスクリプトは、みこ氏のqrcode.inc.php（Plus!付属品）と呼び出し方法に互換性がありますが、
中身の処理は完全に別物です。

オリジナルは、Y.Swetake氏のqrcode_php0.50beta12がベースになっていますが、
Adv.版は、 Dominik Dzienia氏のphpqrcodeをベースにしています。

*書式
 &qrcode{バーコード化する文字列};
 &qrcode(サイズ){バーコード化する文字列};
 &qrcode(サイズ,訂正方法){バーコード化する文字列};
 &qrcode(サイズ,訂正方法,バージョン){バーコード化する文字列};
 &qrcode(サイズ,訂正方法,バージョン,分割数){バーコード化する文字列};
*引数
|サイズ     |バーコードの最小ピクセル            | 1 |
|訂正方法   |訂正コードのレベルです(N/M/H/Q)     | M |
|バージョン |使用するQRコードのバージョン(1～40) |自動判別  |
|分割数     |分割バーコード数(2～16)             |分割しない|
|文字列     |バーコード化する文字列              |(省略不可)|
*著作権
phpqrcode
Copyright (C) 2010 by Dominik Dzienia 

Based on C libqrencode library (ver. 3.1.1) 
Copyright (C) 2006-2010 by Kentaro Fukuchi
http://megaui.net/fukuchi/works/qrencode/index.en.html

QR Code is registered trademarks of DENSO WAVE INCORPORATED in JAPAN and other
countries.

Reed-Solomon code encoder is written by Phil Karn, KA9Q.
Copyright (C) 2002, 2003, 2004, 2006 Phil Karn, KA9Q

*ライセンス
GPL
*コメント
分割数は悩んだ末にバージョンの後ろにしました。
これは分割するなら画像サイズ（＝バージョン）が
そろっていたほうがいいとおもったためです。
*/

define('QR_BASEDIR', LIB_DIR.'phpqrcode'.DIRECTORY_SEPARATOR);

define('QRCODE_MAX_SPLIT', 16);

// use cache - more disk reads but less CPU power, masks and format templates are stored there
define('QR_CACHEABLE', false);
// used when QR_CACHEABLE === true
define('QR_CACHE_DIR', CACHE_DIR.DIRECTORY_SEPARATOR);
// default error logs dir
define('QR_LOG_DIR', CACHE_DIR.DIRECTORY_SEPARATOR);
// if true, estimates best mask (spec. default, but extremally slow; set to false to significant performance boost but (propably) worst quality code
define('QR_FIND_BEST_MASK', false);
// if false, checks all masks available, otherwise value tells count of masks need to be checked, mask id are got randomly
define('QR_FIND_FROM_RANDOM', false);
// when QR_FIND_BEST_MASK === false
define('QR_DEFAULT_MASK', 2);
// maximum allowed png image width (in pixels), tune to make sure GD and PHP can handle such big images
define('QR_PNG_MAXIMUM_SIZE',  1024);

// インラインはアクション用のアドレスを作成するのみ

function plugin_qrcode_init(){
	require_once QR_BASEDIR."qrspec.php";
}

function plugin_qrcode_inline()
{
	switch(func_num_args()){
		case 5:
			list($s,$e,$v,$n,$d) = func_get_args();
			break;
		case 4:
			list($s,$e,$v,$d) = func_get_args();
			break;
		case 3:
			list($s,$e,$d) = func_get_args();
			break;
		case 2:
			list($s,$d) = func_get_args();
			break;
		case 1:
			list($d) = func_get_args();
			break;
		default:
			return FALSE;
			break;
	}

	// thx, nanashi and customized
	$s = ( $s <= 0 ) ? intval($s) : 0;
	$v = (isset($v) && !( $v <= 0 && $v > QRSPEC_VERSION_MAX )) ? intval($v) : 0;
	$n = (isset($n) && !( $n <= 0 && $n > QRCODE_MAX_SPLIT )) ? intval($n) : 0;
	$e = htmlsc(isset($e) ? $e : 'M');

	// if no string, no display.
	if (empty($d)) return FALSE;

	// thx, nao-pon
	$d = str_replace('<br />',"\r\n",$d);
	$d = strip_tags($d);

	// docomo is s-jis encoding
	$d = mb_convert_encoding($d,'SJIS',SOURCE_ENCODING);

	$result = array();
	$result[] = '<figure class="qrcode">';
	if ($n < 2 || $n > 16) {
		$href = get_cmd_uri('qrcode', '', '', array(
			'd' => $d,
			's' => 9,
			'v' => $v,
			'e' => $e
		));
		$src = get_cmd_uri('qrcode', '', '', array(
			'd' => $d,
			's' => $s,
			'v' => $v,
			'e' => $e
		));
		$alt = (defined('UA_MOBILE') && UA_MOBILE != 0) ? 'Mobile' : rawurlencode($d);
		$result[] = '<a href="'.$href.'"><img src="'.$src.'" alt="'.$alt.'" title="'.$alt.'" /></a>';
	} else {
		// 並べる(本来ならPNGを合成するのがきれいでしょうけどね)
		$i=0;
		for ($j=1;$j<=$n;$j++) {
			$splitdata = substr($d,$i,ceil($l/$n));
			$i += ceil($l/$n);
			$src = get_cmd_uri('qrcode', '', '', array(
				'd' => $splitdata,
				's' => $s,
				'v' => $v,
				'e' => $e,
				'm' => $j
			));
			$alt = (defined('UA_MOBILE') && UA_MOBILE != 0) ? 'Mobile' : rawurlencode($splitdata);

			$result[] = '<img src="'.$src.'" alt="'.$alt.'" title="'.$alt.'" />';
			unset($src);
		}
	}
	$result[] = '</figure>';
	return join("\n",$result);
}

// アクションでは、実際の画像を作成
function plugin_qrcode_action()
{
	require_once QR_BASEDIR."qrconst.php";
	require_once QR_BASEDIR."qrtools.php";
	require_once QR_BASEDIR."qrimage.php";
	require_once QR_BASEDIR."qrinput.php";
	require_once QR_BASEDIR."qrbitstream.php";
	require_once QR_BASEDIR."qrsplit.php";
	require_once QR_BASEDIR."qrrscode.php";
	require_once QR_BASEDIR."qrmask.php";
	require_once QR_BASEDIR."qrencode.php";
	
	global $vars;
	if (empty($vars['d'])) {
		return FALSE;
	}
//	$parity = (empty($vars['p'])) ? 0	: $vars['p'];	// パリティ（使用しない）
	
	$qr = new QRcode();	// 宣言
	$qr->version	= (empty($vars['v'])) ? 0	: $vars['v'];				// バージョン
	$qr->mask		= (empty($vars['m'])) ? QR_DEFAULT_MASK	: $vars['m'];	// 分割数
	$qr->count		= (empty($vars['n'])) ? 1	: $vars['n'];				// 
	$qr->hint		= (empty($vars['h'])) ? QR_MODE_AN : $vars['h'];		// 文字コード
	
	pkwk_common_headers(0,null, false);
	print $qr->png(rawurldecode($vars['d']), false, (empty($vars['e'])) ? 'M' : $vars['e'], (empty($vars['s'])) ? 1 : $vars['s'], 2);
	pkwk_common_suffixes();
}
/* End of file qrcode.inc.php */
/* Location: ./wiki-common/plugin/qrcode.inc.php */
