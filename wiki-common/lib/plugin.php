<?php
// PukiWiki - Yet another WikiWikiWeb clone.
// $Id: plugin.php,v 1.20.22 2012/01/22 16:23:00 Logue Exp $
// Copyright (C)
//   2010-2012 PukiWiki Advance Developers Team
//   2005-2006,2008 PukiWiki Plus! Team
//   2002-2005,2007,2011 PukiWiki Developers Team
//   2001-2002 Originally written by yu-ji
// License: GPL v2 or (at your option) any later version
//
// Plugin related functions

define('PKWK_PLUGIN_CALL_TIME_LIMIT', 768);

// Set global variables for plugins
function set_plugin_messages($messages)
{
	foreach ($messages as $name=>$val)
		if (! isset($GLOBALS[$name]))
			$GLOBALS[$name] = $val;
}

// Same as getopt for plugins
function get_plugin_option($args, &$params, $tolower=TRUE, $separator=':')
{
	if (empty($args)) {
		$params['_done'] = TRUE;
		return TRUE;
	}
	$keys = array_keys($params);

	foreach($args as $val) {
		list($_key, $_val) = array_pad(explode($separator, $val, 2), 2, TRUE);
		if ($tolower === TRUE) $_key = strtolower($_key);
		$_key = trim($_key);
		if (is_string($_val)) $_val = trim($_val);
		if (in_array($_key, $keys) && $params['_done'] !== TRUE) {
			$params[$_key] = $_val;    // Exist keys
		} elseif ($val != '') {
			$params['_args'][] = $val; // Not exist keys, in '_args'
			$params['_done'] = TRUE;
		}
	}
	$params['_done'] = TRUE;
	return TRUE;
}

// Check arguments for plugins
function check_plugin_option($val, &$params, $tolower=TRUE)
{
	if ($val != '') {
		if ($tolower === TRUE) $_val = strtolower($val);
		foreach (array_keys($params) as $key) {
			if (strpos($key, $_val) === 0) {
				$params[$key] = TRUE;
				return;
			}
		}
	}
	$params['_args'][] = $val;
}

// Check plugin limit
function limit_plugin($name)
{
	global $vars;
	static $count = array();

	$name = strtolower($name);
	if (!isset($count[$name])) {
		$count[$name] = 1;
	}
	if (++$count[$name] > PKWK_PLUGIN_CALL_TIME_LIMIT) {
		die_message( sprintf($_string['plugin_error'],  htmlsc($name), PKWK_PLUGIN_CALL_TIME_LIMIT). '<br />' . "\n" .
		'<a href="' . get_cmd_uri('edit',$vars['page']) .'">Try to edit this page</a><br />' . "\n" .
		'<a href="' . get_cmd_uri() . '">Return to frontpage</a>');
	}
	return TRUE;
}

// Check plugin '$name' is here
function exist_plugin($name)
{
	global $exclude_plugin, $plugin_lang_path;
	static $exist = array();

	$name = strtolower($name);

	// (plus!)added exclude plugin spec.
	if (in_array($name, $exclude_plugin)) {
		$exist[$name] = FALSE;
		return FALSE;
	}

	if (preg_match('/^\w{1,64}$/', $name)) {
		foreach(array(EXT_PLUGIN_DIR,PLUGIN_DIR) as $p_dir) {
			if (file_exists($p_dir . $name . '.inc.php')) {
				$plugin_lang_path[$name] = (PLUGIN_DIR == $p_dir) ? LANG_DIR : EXT_LANG_DIR;
				$exist[$name] = TRUE;
				load_init_value($name);
				require_once($p_dir . $name . '.inc.php');
				return TRUE;
			}
		}
	}
	$exist[$name] = FALSE;
	return FALSE;
}

// Check if plguin API exists
function exist_plugin_function($name, $func)
{
	if (function_exists($func)) {
		return limit_plugin($name);
	} elseif (exist_plugin($name) && function_exists($func)) {
		return limit_plugin($name);
	}
	return FALSE;
}

// Check if plugin API 'action' exists
function exist_plugin_action($name) {
	return exist_plugin_function($name, 'plugin_' . $name . '_action');
}
// Check if plugin API 'convert' exists
function exist_plugin_convert($name) {
	return exist_plugin_function($name, 'plugin_' . $name . '_convert');
}
// Check if plugin API 'inline' exists
function exist_plugin_inline($name) {
	return exist_plugin_function($name, 'plugin_' . $name . '_inline');
}

// Call 'init' function for the plugin
// NOTE: Returning FALSE means "An erorr occurerd"
function do_plugin_init($name)
{
	global $plugin_lang_path;
	static $done = array();

	if (empty($plugin_lang_path[$name])) {
		bindtextdomain($name, LANG_DIR);
		T_bindtextdomain($name,LANG_DIR);
	} else {
		bindtextdomain($name, $plugin_lang_path[$name]);
		T_bindtextdomain($name,$plugin_lang_path[$name]);
	}
	bind_textdomain_codeset($name, SOURCE_ENCODING);
	T_bind_textdomain_codeset($name,SOURCE_ENCODING); 
	
	// i18n (Plus!)
	$func = 'plugin_' . $name . '_init';
	if (function_exists($func)) {
		// TRUE or FALSE or NULL (return nothing)
		textdomain($name);
		T_textdomain($name);
		$done[$name] = call_user_func($func);
		textdomain(DOMAIN);
		T_textdomain(DOMAIN);
		if (!isset($checked[$name])) {
			$done[$name] = TRUE; // checked.
		}
	} else {
		$done[$name] = TRUE; // checked.
	}
/*
	if (! isset($done[$name])) {
		$func = 'plugin_' . $name . '_init';
		$done[$name] = (! function_exists($func) || call_user_func($func) !== FALSE);
	}
*/
	return $done[$name];
}

// Call API 'action' of the plugin
function do_plugin_action($name)
{
	global $vars, $_string;
	if (! exist_plugin_action($name)) return array();

	if (do_plugin_init($name) === FALSE) {
		die_message('Plugin init failed: ' . htmlsc($name));
	}

	// check postid
	if ( isset($vars['postid']) && !check_postid($vars['postid']) )
		die_message($_string['postid_error']);
	
	if ( isset($vars['encode_hint']) && $vars['encode_hint'] !== PKWK_ENCODING_HINT )
		die_message($_string['encode_error']);

	textdomain($name);
	T_textdomain($name);
	$retvar = call_user_func('plugin_' . $name . '_action');
	textdomain(DOMAIN);
	T_textdomain(DOMAIN);

	$retvar['body'] = isset($retvar['body']) ? add_hidden_field($retvar['body'], $name) : '';

	return $retvar;
}

// Call API 'convert' of the plugin
function do_plugin_convert($name, $args = '')
{
	global $digest;

	if (do_plugin_init($name) === FALSE) {
		return '[Plugin init failed: ' . htmlsc($name) . ']';
	}

	if (! PKWKEXP_DISABLE_MULTILINE_PLUGIN_HACK) {
		// Multiline plugin?
		$pos  = strpos($args, "\r"); // "\r" is just a delimiter
		if ($pos !== FALSE) {
			$body = substr($args, $pos + 1);
			$args = substr($args, 0, $pos);
		}
	}

	$aryargs = empty($args) ? array() : csv_explode(',', $args);

	if (! PKWKEXP_DISABLE_MULTILINE_PLUGIN_HACK) {
		if (isset($body)) $aryargs[] = & $body;     // #plugin(){{body}}
	}

	$_digest = $digest;
	textdomain($name);
	T_textdomain($name);
	$retvar  = call_user_func_array('plugin_' . $name . '_convert', $aryargs);
	textdomain(DOMAIN);
	T_textdomain(DOMAIN);
	$digest  = $_digest; // Revert

	return ($retvar === FALSE) ?
		htmlsc('#' . $name . ($args != '' ? '(' . $args . ')' : '')) : add_hidden_field($retvar, $name);
}

// Call API 'inline' of the plugin
function do_plugin_inline($name, $args='', $body='')
{
	global $digest;

	if (do_plugin_init($name) === FALSE) {
		return '[Plugin init failed: ' . htmlsc($name) . ']';
	}

	$aryargs = empty($args) ? array() : csv_explode(',', $args);

	// NOTE: A reference of $body is always the last argument
	$aryargs[] = & $body; // func_num_args() != 0

	$_digest = $digest;
	textdomain($name);
	T_textdomain($name);
	$retvar  = call_user_func_array('plugin_' . $name . '_inline', $aryargs);
	textdomain(DOMAIN);
	T_textdomain(DOMAIN);
	$digest  = $_digest; // Revert

	if($retvar === FALSE) {
		// Do nothing
		return htmlsc('&' . $name . ($args ? '(' . $args . ')' : '') . ';');
	} else {
		return add_hidden_field($retvar, $name);
	}
}

// Used Plugin?
function use_plugin($plugin, $lines)
{
	if (!is_array($lines)) {
		$delim = array("\r\n", "\r");
		$lines = str_replace($delim, "\n", $lines);
		$lines = explode("\n", $lines);
	}

	foreach ($lines as $line) {
		if (substr($line, 0, 2) == '//') continue;
		// Diff data
		if (substr($line, 0, 1) == '+' || substr($line, 0, 1) == '-') {
			$line = substr($line, 1);
		}
		if (preg_match('/^[#|&]' . $plugin . '[^a-zA-Z]*$/', $line, $matches)) {
			return $matches[0];
		}
	}
	return FALSE;
}

function add_hidden_field($retvar, $name){
	if (preg_match('/<form.+method="?(get|post)"[^>]*>/i', $retvar, $matches) !== 0){
		// Insert a hidden field, supports idenrtifying text enconding
		$hidden_field[] = ( PKWK_ENCODING_HINT ) ? '<input type="hidden" name="encode_hint" value="' . PKWK_ENCODING_HINT . '" />' : '';
		
		if (preg_match('/menu|side|header|footer|full|read|include|calendar|login/',$name) !== 1 && $matches[1] !== 'get'){
			// from PukioWikio
			$hidden_field[] = '<input type="hidden" name="postid" value="'.generate_postid($name).'" />';
		}
		$retvar = preg_replace('/<form[^>]*>/', '$0'. "\n".join("\n",$hidden_field), $retvar);
	}
	return $retvar;
}

/* End of file plugin.php */
/* Location: ./wiki-common/lib/plugin.php */
