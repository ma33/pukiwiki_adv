<?php
// PukiWiki - Yet another WikiWikiWeb clone
// $Id: pcomment.inc.php,v 1.48.26 2012/05/11 18:26:00 Logue Exp $
//
// pcomment plugin - Show/Insert comments into specified (another) page
//
// Usage: #pcomment([page][,max][,options])
//
//   page -- An another page-name that holds comments
//           (default:PLUGIN_PCOMMENT_PAGE)
//   max  -- Max number of recent comments to show
//           (0:Show all, default:PLUGIN_PCOMMENT_NUM_COMMENTS)
//
// Options:
//   above -- Comments are listed above the #pcomment (added by chronological order)
//   below -- Comments are listed below the #pcomment (by reverse order)
//   reply -- Show radio buttons allow to specify where to reply

// Default recording page name (%s = $vars['page'] = original page name)
define('PLUGIN_PCOMMENT_PAGE', T_('[[Comments/%s]]'));

define('PLUGIN_PCOMMENT_NUM_COMMENTS',     10); // Default 'latest N posts'
define('PLUGIN_PCOMMENT_DIRECTION_DEFAULT', 1); // 1: above 0: below
define('PLUGIN_PCOMMENT_SIZE_MSG',  68);
define('PLUGIN_PCOMMENT_SIZE_NAME', 15);

// Auto log rotation
define('PLUGIN_PCOMMENT_AUTO_LOG', 0); // 0:off 1-N:number of comments per page

// Update recording page's timestamp instead of parent's page itself
define('PLUGIN_PCOMMENT_TIMESTAMP', 0);

// ----
define('PLUGIN_PCOMMENT_FORMAT_NAME',	'[[$name]]');
define('PLUGIN_PCOMMENT_FORMAT_MSG',	'$msg');
define('PLUGIN_PCOMMENT_FORMAT_NOW',	'&epoch{'.MUTIME.',comment_date};');

// "\x01", "\x02", "\x03", and "\x08" are used just as markers
define('PLUGIN_PCOMMENT_FORMAT_STRING',
	"\x08" . 'MSG' . "\x08" . ' -- ' . "\x08" . 'NAME' . "\x08" . ' ' . "\x08" . 'DATE' . "\x08");

function plugin_pcomment_action()
{
	global $vars, $_string;

	// if (PKWK_READONLY) die_message('PKWK_READONLY prohibits editing');
	if (auth::check_role('readonly')) die_message(sprintf($_string['error_prohibit'], 'PKWK_READONLY'));

	// Petit SPAM Check (Client(Browser)-Server Ticket Check)
	$b = FALSE;
	if (!isset($vars['encode_hint']) && PKWK_ENCODING_HINT == '') {
		$b = TRUE;
	} elseif (isset($vars['encode_hint']) && $vars['encode_hint'] == PKWK_ENCODING_HINT) {
		$b = TRUE;
	}
	if ($b === FALSE) {
		honeypot_write();
		return array('msg'=>'', 'body'=>''); // Do nothing
	}

	if (! isset($vars['msg']) || $vars['msg'] == '') return array();

	// Validate
	if (is_spampost(array('msg'))) {
		honeypot_write();
		return array('msg'=>'', 'body'=>''); // Do nothing
	}

	$refer = isset($vars['refer']) ? $vars['refer'] : '';

	if (!is_page($refer) && auth::is_check_role(PKWK_CREATE_PAGE)) {
		die_message( sprintf($_string['error_prohibit'], 'PKWK_CREATE_PAGE') );
	}

	$retval = plugin_pcomment_insert();
	if ($retval['collided']) {
		$vars['page'] = $refer;
		return $retval;
	}

	pkwk_headers_sent();
	header('Location: ' . get_page_location_uri($refer));
	exit;
}

function plugin_pcomment_convert()
{
	global $vars;
//	global $_pcmt_messages;
	$_pcmt_messages = array(
		'msg_name'       => T_('Name: '),
		'btn_comment'    => T_('Post Comment'),
		'msg_comment'    => T_('Comment: '),
		'msg_recent'     => T_('Show recent %d comments.'),
		'msg_all'        => T_('Go to the comment page.'),
		'msg_none'       => T_('No comment.'),
		'err_pagename'   => T_('[[%s]] : not a valid page name.'),
	);

	$params = array(
		'noname'=>FALSE,
		'nodate'=>FALSE,
		'below' =>FALSE,
		'above' =>FALSE,
		'reply' =>FALSE,
		'_args' =>array()
	);

	foreach(func_get_args() as $arg)
		check_plugin_option($arg, $params);

	$vars_page = isset($vars['page']) ? $vars['page'] : '';
	$page  = (isset($params['_args'][0]) && $params['_args'][0] != '') ? $params['_args'][0] :
		sprintf(PLUGIN_PCOMMENT_PAGE, strip_bracket($vars_page));
	$count = (isset($params['_args'][1])) ? intval($params['_args'][1]) : 0;
	if ($count == 0) $count = PLUGIN_PCOMMENT_NUM_COMMENTS;

	$_page = get_fullname(strip_bracket($page), $vars_page);
	if (!is_pagename($_page))
		return sprintf($_pcmt_messages['err_pagename'], htmlsc($_page));

	$dir = PLUGIN_PCOMMENT_DIRECTION_DEFAULT;
	if ($params['below']) {
		$dir = 0;
	} elseif ($params['above']) {
		$dir = 1;
	}

	list($comments, $digest) = plugin_pcomment_get_comments($_page, $count, $dir, $params['reply']);

	$auth_guide = '';
	if (PKWK_READONLY == ROLE_AUTH) {
		exist_plugin('login');
		$auth_guide = do_plugin_inline('login');
	}

	// if (PKWK_READONLY) {
	if (auth::check_role('readonly')) {
		$form_start = $form = $form_end = '';
	} else {
		// Show a form

//		if ($params['noname']) {
//			$title = $_pcmt_messages['msg_comment'];
			$name = '';
//		} else {
//			$title = $_pcmt_messages['msg_name'];
			// $name = '<input type="text" name="name" size="' . PLUGIN_PCOMMENT_SIZE_NAME . '" placeholder="'.$_pcmt_messages['msg_name'].'" />';
			list($nick,$link,$disabled) = plugin_pcomment_get_nick();
			$name = '<input type="text" name="name" value="'.$nick.'" '.$disabled.' size="' . PLUGIN_PCOMMENT_SIZE_NAME . '" placeholder="'.$_pcmt_messages['msg_name'].'" />';
//		}

		$radio   = $params['reply'] ?
			'<input type="radio" name="reply" value="0" tabindex="0" checked="checked" />' : '';
		$comment = '<input type="text" name="msg" size="' . PLUGIN_PCOMMENT_SIZE_MSG . '" placeholder="'.$_pcmt_messages['msg_comment'].'" />';

		$s_page   = htmlsc($page);
		$s_refer  = htmlsc($vars_page);
		$s_nodate = htmlsc($params['nodate']);

		$form_start = '<form action="' . get_script_uri() . '" method="post" class="pcomment_form">' . "\n";
		$form = <<<EOD
	<input type="hidden" name="digest" value="$digest" />
	<input type="hidden" name="cmd" value="pcomment" />
	<input type="hidden" name="refer"  value="$s_refer" />
	<input type="hidden" name="page"   value="$s_page" />
	<input type="hidden" name="nodate" value="$s_nodate" />
	<input type="hidden" name="dir"    value="$dir" />
	<input type="hidden" name="count"  value="$count" />
	<div class="comment_form">
	$radio $name $comment
		<input type="submit" value="{$_pcmt_messages['btn_comment']}" />
	</div>
EOD;
		$form_end = '</form>' . "\n";
	}

	if (! is_page($_page)) {
		$link   = make_pagelink($_page);
		$recent = $_pcmt_messages['msg_none'];
	} else {
		$msg    = ($_pcmt_messages['msg_all'] != '') ? $_pcmt_messages['msg_all'] : $_page;
		$link   = make_pagelink($_page, $msg);
		$recent = ! empty($count) ? sprintf($_pcmt_messages['msg_recent'], $count) : '';
	}

	if ($dir) {
		$string = $auth_guide .
			'<p>' . $recent . ' ' . $link . '</p>' . "\n" .
			$form_start .
				$comments . "\n" .
				$form .
			$form_end . "\n";
	} else {
		$string = $form_start .
				$form .
				$comments. "\n" .
			$form_end .
			'<p>' . $recent . ' ' . $link . '</p>' . "\n" .
			$auth_guide . "\n";
	}
	return (IS_MOBILE) ? '<div data-role="collapsible" data-theme="b" data-content-theme="d"><h4>'.$_pcmt_messages['msg_comment'].'</h4>'.$string.'</div>' : '<div class="pcomment">' . $string . '</div>';
}

function plugin_pcomment_insert()
{
	global $vars, $now, $_no_name;
//	global $vars, $now, $_title_updated, $_no_name, $_pcmt_messages, $_string;

	$refer = isset($vars['refer']) ? $vars['refer'] : '';
	$page  = isset($vars['page'])  ? $vars['page']  : '';
	$page  = get_fullname($page, $refer);

	if (! is_pagename($page))
		return array(
			'msg' => T_('Invalid page name'),
			'body'=> T_('Cannot add comment'),
			'collided'=>TRUE
		);

	check_editable($page, true, true);

	$ret = array('msg' => $_string['update'], 'collided' => FALSE);

	$msg = str_replace('$msg', rtrim($vars['msg']), PLUGIN_PCOMMENT_FORMAT_MSG);
	$name = (! isset($vars['name']) || $vars['name'] == '') ? $_no_name : $vars['name'];

	$name = ($name == '') ? '' : str_replace('$name', $name, PLUGIN_PCOMMENT_FORMAT_NAME);
	$date = (! isset($vars['nodate']) || $vars['nodate'] != '1') ?
		str_replace('$now', $now, PLUGIN_PCOMMENT_FORMAT_NOW) : '';

	list($nick,$link) = plugin_pcomment_get_nick();
	if (! empty($link)) $name = $link;

	$name = ($name == '') ? '' : str_replace('$name', $name, PLUGIN_PCOMMENT_FORMAT_NAME);
	$date = (! isset($vars['nodate']) || $vars['nodate'] != '1') ?
		str_replace('$now', $now, PLUGIN_PCOMMENT_FORMAT_NOW) : '';
	if ($date != '' || $name != '') {
		$msg = str_replace("\x08" . 'MSG'  . "\x08", $msg,  PLUGIN_PCOMMENT_FORMAT_STRING);
		$msg = str_replace("\x08" . 'NAME' . "\x08", $name, $msg);
		$msg = str_replace("\x08" . 'DATE' . "\x08", $date, $msg);
	}

	$reply_hash = isset($vars['reply']) ? $vars['reply'] : '';
	if ($reply_hash || ! is_page($page)) {
		$msg = preg_replace('/^\-+/', '', $msg);
	}
	$msg = rtrim($msg);

	if (! is_page($page)) {
		$postdata = '[[' . htmlsc(strip_bracket($refer)) . ']]' . "\n\n" .
			'-' . $msg . "\n";
	} else {
		$postdata = get_source($page);
		$count    = count($postdata);

		$digest = isset($vars['digest']) ? $vars['digest'] : '';
		if (md5(join('', $postdata)) != $digest) {
			$ret['msg']  = $_string['title_collided'];
			$ret['body'] = $_string['comment_collided'];
		}

		$start_position = 0;
		while ($start_position < $count) {
			if (preg_match('/^\-/', $postdata[$start_position])) break;
			++$start_position;
		}
		$end_position = $start_position;

		$dir = isset($vars['dir']) ? $vars['dir'] : '';

		// Find the comment to reply
		$level   = 1;
		$b_reply = FALSE;
		if ($reply_hash != '') {
			while ($end_position < $count) {
				$matches = array();
				if (preg_match('/^(\-{1,2})(?!\-)(.*)$/', $postdata[$end_position++], $matches)
					&& md5($matches[2]) == $reply_hash)
				{
					$b_reply = TRUE;
					$level   = strlen($matches[1]) + 1;

					while ($end_position < $count) {
						if (preg_match('/^(\-{1,3})(?!\-)/', $postdata[$end_position], $matches)
							&& strlen($matches[1]) < $level) break;
						++$end_position;
					}
					break;
				}
			}
		}

		if ($b_reply == FALSE)
			$end_position = ($dir == '0') ? $start_position : $count;

		// Insert new comment
		array_splice($postdata, $end_position, 0, str_repeat('-', $level) . $msg . "\n");

		if (PLUGIN_PCOMMENT_AUTO_LOG) {
			$_count = isset($vars['count']) ? $vars['count'] : '';
			plugin_pcomment_auto_log($page, $dir, $_count, $postdata);
		}

		$postdata = join('', $postdata);
	}
	page_write($page, $postdata, PLUGIN_PCOMMENT_TIMESTAMP);

	if (PLUGIN_PCOMMENT_TIMESTAMP) {
		if ($refer !== '') pkwk_touch_file(get_filename($refer));
		put_lastmodified();
	}

	return $ret;
}

// Auto log rotation
function plugin_pcomment_auto_log($page, $dir, $count, & $postdata)
{
	if (! PLUGIN_PCOMMENT_AUTO_LOG) return;

	$keys = array_keys(preg_grep('/(?:^-(?!-).*$)/m', $postdata));
	if (count($keys) < (PLUGIN_PCOMMENT_AUTO_LOG + $count)) return;

	if ($dir) {
		// Top N comments (N = PLUGIN_PCOMMENT_AUTO_LOG)
		$old = array_splice($postdata, $keys[0], $keys[PLUGIN_PCOMMENT_AUTO_LOG] - $keys[0]);
	} else {
		// Bottom N comments
		$old = array_splice($postdata, $keys[count($keys) - PLUGIN_PCOMMENT_AUTO_LOG]);
	}

	// Decide new page name
	$i = 0;
	do {
		++$i;
		$_page = $page . '/' . $i;
	} while (is_page($_page));

	page_write($_page, '[[' . $page . ']]' . "\n\n" . join('', $old));

	// Recurse :)
	plugin_pcomment_auto_log($page, $dir, $count, $postdata);
}

function plugin_pcomment_get_comments($page, $count, $dir, $reply)
{
//	global $_msg_pcomment_restrict;

	if (! check_readable($page, false, false))
		return array(str_replace('$1', $page, T_('Due to the blocking, no comments could be read from  $1 at all.')));

	// $reply = (! PKWK_READONLY && $reply); // Suprress radio-buttons
	$reply = (! auth::check_role('readonly') && $reply); // Suprress radio-buttons

	$data = get_source($page);
	$data = preg_replace('/^#pcomment\(?.*/i', '', $data);	// Avoid eternal recurse

	if (! is_array($data)) return array('', 0);

	$digest = md5(join('', $data));

	// Get latest N comments
	$num  = $cnt     = 0;
	$cmts = $matches = array();
	if ($dir) $data = array_reverse($data);
	foreach ($data as $line) {
		if ($count > 0 && $dir && $cnt == $count) break;

		if (preg_match('/^(\-{1,2})(?!\-)(.+)$/', $line, $matches)) {
			if ($count > 0 && strlen($matches[1]) == 1 && ++$cnt > $count) break;

			// Ready for radio-buttons
			if ($reply) {
				++$num;
				$cmts[] = $matches[1] . "\x01" . $num . "\x02" .
					md5($matches[2]) . "\x03" . $matches[2] . "\n";
				continue;
			}
		}
		$cmts[] = $line;
	}
	$data = $cmts;
	if ($dir) $data = array_reverse($data);
	unset($cmts, $matches);

	// Remove lines before comments
	while (! empty($data) && substr($data[0], 0, 1) != '-')
		array_shift($data);

	$comments = convert_html($data);
	unset($data);

	// Add radio buttons
	if ($reply)
		$comments = preg_replace('/<li>' . "\x01" . '(\d+)' . "\x02" . '(.*)' . "\x03" . '/',
			'<li class="pcomment_comment"><input class="pcmt" type="radio" name="reply" value="$2" tabindex="$1" />',
			$comments);

	return array($comments, $digest);
}

function plugin_pcomment_get_nick()
{
	global $vars, $_no_name;

	$name = (empty($vars['name'])) ? $_no_name : $vars['name'];
	if (PKWK_READONLY != ROLE_AUTH) return array($name,$name,'');

	$auth_key = auth::get_user_name();
	if (empty($auth_key['nick'])) return array($name,$name,'');
	if (auth::get_role_level() < ROLE_AUTH) return array($auth_key['nick'],$name,'');
	$link = (empty($auth_key['profile'])) ? $auth_key['nick'] : $auth_key['nick'].'>'.$auth_key['profile'];
	return array($auth_key['nick'], $link, "disabled=\"disabled\"");
}
/* End of file pcomment.inc.php */
/* Location: ./wiki-common/plugin/pcomment.inc.php */
