<?php
/////////////////////////////////////////////////
// PukiWiki - Yet another WikiWikiWeb clone.
//
// $Id: cache_ref.inc.php,v 1.48.3.4 2012/06/08 22:38:00 Logue Exp $
//
// copy ref.inc.php

function plugin_cache_ref_action()
{
	global $vars;

	$usage = 'Usage: cmd=cache_ref&amp;src=filename';

	if (! isset($vars['src']))
		return array('msg'=>'Invalid argument', 'body'=>$usage);

	$filename = $vars['src'] ;

	$ref = CACHE_DIR . $filename;
	if(! file_exists($ref))
		return array('msg'=>'Cache file not found', 'body'=>$usage);

	$got = @getimagesize($ref);
	if (! isset($got[2])) $got[2] = FALSE;
	switch ($got[2]) {
	case 1: $type = 'image/gif' ; break;
	case 2: $type = 'image/jpeg'; break;
	case 3: $type = 'image/png' ; break;
	case 4: $type = 'application/x-shockwave-flash'; break;
	default:
		return array('msg'=>'Seems not an image', 'body'=>$usage);
	}

	// Care for Japanese-character-included file name
	if (LANG == 'ja_JP') {
		switch(UA_NAME . '/' . UA_PROFILE){
		case 'Opera/default':
			// Care for using _auto-encode-detecting_ function
			$filename = mb_convert_encoding($filename, 'UTF-8', 'auto');
			break;
		case 'MSIE/default':
			$filename = mb_convert_encoding($filename, 'SJIS', 'auto');
			break;
		}
	}
	$file = htmlsc($filename);
	$size = filesize($ref);
	$date = filemtime($ref);

	// Output
	pkwk_common_headers($date, null, false);
	header('Content-Disposition: inline; filename="' . $filename . '"');
	header('Content-Length: ' . $size);
	header('Content-Type: '   . $type);

	// for reduce server load
	$sendfile = realpath($ref);
	if (function_exists('apache_get_modules') && in_array( 'mod_xsendfile', apache_get_modules()) ){
		// for Apache mod_xsendfile
		header('X-Sendfile: '.$sendfile);
	}else if (stristr(getenv('SERVER_SOFTWARE'), 'lighttpd') ){
		// for lighttpd
		header('X-Lighttpd-Sendfile: '.$sendfile);
	}else if(stristr(getenv('SERVER_SOFTWARE'), 'nginx') || stristr(getenv('SERVER_SOFTWARE'), 'cherokee')){
		// nginx
		header('X-Accel-Redirect: '.$sendfile);
	}

	// @readfile($ref);
	plus_readfile($ref);
	exit;
}

/* End of file bugtrack_list.inc.php */
/* Location: ./wiki-common/plugin/bugtrack_list.inc.php */