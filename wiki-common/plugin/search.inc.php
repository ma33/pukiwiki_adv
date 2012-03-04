<?php
// PukiWiki - Yet another WikiWikiWeb clone.
// $Id: search.inc.php,v 1.14.4 2011/02/05 12:37:00 Logue Exp $
//
// Search plugin

// Allow search via GET method 'index.php?plugin=search&word=keyword'
// NOTE: Also allows DoS to your site more easily by SPAMbot or worm or ...
define('PLUGIN_SEARCH_DISABLE_GET_ACCESS', 0); // 1, 0

define('PLUGIN_SEARCH_MAX_LENGTH', 80);
define('PLUGIN_SEARCH_MAX_BASE',   16); // #search(1,2,3,...,15,16)

function plugin_search_init()
{
	$msg = array(
		'_search_msg' => array(
			'title_search'	=> T_('Search for word(s)'),
			'title_result'	=> T_('Search result of  $1'),
			'msg_searching'	=> T_('Key words are case-insenstive, and are searched for in all pages.'),
			'btn_search'	=> T_('Search'),
			'btn_and'		=> T_('AND'),
			'btn_or'		=> T_('OR'),
			'search_pages'	=> T_('Search for page starts from $1'),
			'search_all'	=> T_('Search for all pages'),
			'search_words'	=> T_('Search words')
		)
	);
	set_plugin_messages($msg);
}


// Show a search box on a page
function plugin_search_convert()
{
	static $done;

	if (isset($done)) {
		return '#search(): You already view a search box<br />' . "\n";
	} else {
		$done = TRUE;
		$args = func_get_args();
		return plugin_search_search_form('', '', $args);
	}
}

function plugin_search_action()
{
	global $post, $vars;
	global $_search_msg;

	if (PLUGIN_SEARCH_DISABLE_GET_ACCESS) {
		$s_word = isset($post['word']) ? htmlsc($post['word']) : '';
	} else {
		$s_word = isset($vars['word']) ? htmlsc($vars['word']) : '';
	}
	if (strlen($s_word) > PLUGIN_SEARCH_MAX_LENGTH) {
		unset($vars['word']); // Stop using $_msg_word at lib/html.php
		die_message('Search words too long');
	}

	$type = isset($vars['type']) ? $vars['type'] : '';
	$base = isset($vars['base']) ? $vars['base'] : '';

	if ($s_word !== '') {
		// Search
		$msg  = str_replace('$1', $s_word, $_search_msg['title_result']);
		$body = do_search($vars['word'], $type, FALSE, $base);
	} else {
		// Init
		unset($vars['word']); // Stop using $_msg_word at lib/html.php
		$msg  = $_search_msg['title_search'];
		$body = '<p>'.$_search_msg['msg_searching'].'</p>' . "\n";
	}

	// Show search form
	$bases = ($base == '') ? array() : array($base);
	$body .= plugin_search_search_form($s_word, $type, $bases);

	return array('msg'=>$msg, 'body'=>$body);
}

function plugin_search_search_form($s_word = '', $type = '', $bases = array())
{
	global $script;
	global $_search_msg;

	$and_check = $or_check = '';
	if ($type == 'OR') {
		$or_check  = ' checked="checked"';
	} else {
		$and_check = ' checked="checked"';
	}

	$base_option = '';
	if (!empty($bases)) {
		$base_msg = '';
		$_num = 0;
		$check = ' checked="checked"';
		foreach($bases as $base) {
			++$_num;
			if (PLUGIN_SEARCH_MAX_BASE < $_num) break;
			$label_id = '_p_search_base_id_' . $_num;
			$s_base   = htmlsc($base);
			$base_str = '<strong>' . $s_base . '</strong>';
			$base_label = '<p>'.str_replace('$1', $base_str, $_search_msg['search_pages']).'</p>';
			$base_msg  .=<<<EOD
	<div>
		<input type="radio" name="base" id="$label_id" value="$s_base" $check />
		<label for="$label_id">$base_label</label>
	</div>
EOD;
			$check = '';
		}
		$base_msg .=<<<EOD
		<input type="radio" name="base" id="_p_search_base_id_all" value="" />
		<label for="_p_search_base_id_all">{$_search_msg['search_all']}</label>
EOD;
		$base_option = '<p>' . $base_msg . '</p>';
	}

	if (! PLUGIN_SEARCH_DISABLE_GET_ACCESS) {
		$method = "get";
	}else{
		$method = "post";
	}
	$maxlength = PLUGIN_SEARCH_MAX_LENGTH;
	
	if (IS_MOBILE){
		return <<<EOD
<form action="$script" method="{$method}" class="search_form">
	<input type="hidden" name="cmd" value="search" />
	<input type="search"  name="word" value="$s_word" size="20" maxlength="$maxlength" id="search_word" results="5" autosave="tangerine" placeholder="{$_search_msg['search_words']}"/>
	<fieldset data-role="controlgroup"  data-mini="true">
		<input type="radio" name="type" id="_p_search_AND" value="AND" $and_check />
		<label for="_p_search_AND">{$_search_msg['btn_and']}</label>
		<input type="radio" name="type" id="_p_search_OR" value="OR"  $or_check />
		<label for="_p_search_OR">{$_search_msg['btn_or']}</label>
	</fieldset>
	<input type="submit" value="{$_search_msg['btn_search']}" />
$base_option
</form>
EOD;
	}else{
		return <<<EOD
<form action="$script" method="{$method}" class="search_form">
	<input type="hidden" name="cmd" value="search" />
	<input type="search"  name="word" value="$s_word" size="20" maxlength="$maxlength" id="search_word" results="5" autosave="tangerine" placeholder="{$_search_msg['search_words']}"/>
	<input type="radio" name="type" id="_p_search_AND" value="AND" $and_check />
	<label for="_p_search_AND">{$_search_msg['btn_and']}</label>
	<input type="radio" name="type" id="_p_search_OR" value="OR"  $or_check />
	<label for="_p_search_OR">{$_search_msg['btn_or']}</label>
	<input type="submit" value="{$_search_msg['btn_search']}" />
$base_option
</form>
EOD;
	}
}
?>
