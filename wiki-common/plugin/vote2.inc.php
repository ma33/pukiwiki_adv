<?php
/////////////////////////////////////////////////
// PukiWiki - Yet another WikiWikiWeb clone.
//
// $Id: vote2.inc.php,v 0.12.10 2012/05/11 18:12:00 Logue Exp $
// based on vote.inc.php v1.14
//
// v0.2はインラインのリンクにtitleを付けた。
//
require_once(LIB_DIR.'barchart.cls.php');

defined('VOTE2_COOKIE_EXPIRED') or define('VOTE2_COOKIE_EXPIRED', 60*60*24*3);	// 連続投票禁止時間
defined('VOTE2_COLOR_BG')       or define('VOTE2_COLOR_BG',     '#d0d8e0');	// 棒グラフの背景色
defined('VOTE2_COLOR_BORDER')   or define('VOTE2_COLOR_BORDER', '#ccd5dd');	// 棒グラフの枠色
defined('VOTE2_COLOR_BAR')      or define('VOTE2_COLOR_BAR',    '#0000ff');	// 棒の表示色 (青)

function plugin_vote2_init()
{
	$messages = array(
		'_vote2_messages' => array(
			'arg_notimestamp' => 'notimestamp',
			'arg_nonumber'    => 'nonumber',
			'arg_nolabel'     => 'nolabel',
			'arg_notitle'     => 'notitle',
			'arg_barchart'    => 'barchart',
			'title_error'   => T_("Error in vote2"),
			'no_page_error' => T_("The page of $1 doesn't exist."),
			'attack_error'  => T_("It is not possible to vote continuously."),
			'update_failed' => T_("Vote failure: In $1, there was the address of the vote or the item was not corresponding."),
			'body_error'    => T_("An indispensable argument has not been passed or there is an error in the argument."),
			'msg_collided'  => T_("Other people seem to have updated the content of the same page while you are voting.") .'<br />' .
				T_("Therefore, there is a possibility of making a mistake in the position for which it votes.") .'<br /><br />' .
				T_("Your update was invalidated. Start ..be previous page.. reload.")
		),
		'_vote_plugin_choice' => T_('Selection'),
		'_vote_plugin_votes' => T_('Vote'),
	);
	set_plugin_messages($messages);
}
function plugin_vote2_action()
{
	global $vars, $_vote2_messages;
	$block_flag = 0;
	
	if ( ! is_page($vars['refer']) ){
		$error = str_replace('$1', $vars['refer'], $_vote2_messages['no_page_error']);
		return array(
			'msg'  => $_vote2_messages['title_error'], 
			'body' => $error,
		);
	}
//added by miko
	$refer = $vars['refer'];
	$vote_no = (isset($vars['vote_no'])) ? $vars['vote_no'] : 0;
	$vote_inno = (isset($vars['vote_inno'])) ? $vars['vote_inno'] : '';

	$votedkey = 'vote_'.$refer.'_'.$vote_no.'_'.$vote_inno;
	if (isset($_COOKIE[$votedkey])) {
		/*
		return array(
			'msg'  => $_vote2_messages['title_error'],
			'body' => $_vote2_messages['attack_error'],
		);
		*/
		error_msg( $_vote2_messages['title_error'], $_vote2_messages['attack_error']);
	}
	$_COOKIE[$votedkey] = 1;
	preg_match('!(.*/)!', $_SERVER['REQUEST_URI'], $matches);
	setcookie($votedkey,1,time()+VOTE2_COOKIE_EXPIRED,$matches[0]);
//added by miko
	if ( array_key_exists('vote_no', $vars) ) {
		$block_flag = 1;
	}
	else if ( array_key_exists('vote_inno', $vars) ){
		$vote_no = $vars['vote_inno'];
		$block_flag = 0;
	}
	if ( preg_match('/^(\d+)([ib]?)$/', $vote_no, $match) ){
		$vote_no = $match[1];
		switch ( $match[2] ){
			case 'i': $block_flag = 0; break;
			case 'b': $block_flag = 1; break;
			default: break;
		}
		switch ( $block_flag ) {
			case 1:
				return plugin_vote2_action_block($vote_no);
				break;
			case 0:
			default:
				return plugin_vote2_action_inline($vote_no);
				break;
		}
	}
	/*
	return array(
		'msg'  => $_vote2_messages['title_error'], 
		'body' => $_vote2_messages['body_error'],
	);
	*/
	error_msg( $_vote2_messages['title_error'], $_vote2_messages['body_error']);
}
function plugin_vote2_inline()
{
	global $vars, $digest;
	global $_vote2_messages;
	global $_vote_plugin_choice, $_vote_plugin_votes;
	static $numbers = array();
	static $notitle = FALSE;
//$_vote_plugin_choice = _("Selection");
//$_vote_plugin_votes  = _("Vote");
	$str_notimestamp = $_vote2_messages['arg_notimestamp'];
	$str_nonumber    = $_vote2_messages['arg_nonumber'];
	$str_nolabel     = $_vote2_messages['arg_nolabel'];
	$str_notitle     = $_vote2_messages['arg_notitle'];
	$str_barchart    = $_vote2_messages['arg_barchart'];

	$args = func_get_args();
	array_pop($args); // {}内の要素の削除
	$page = $vars['page'];
	if (!array_key_exists($page,$numbers))	$numbers[$page] = 0;
	$vote_inno = $numbers[$page]++;
	$o_vote_inno = $f_vote_inno = $vote_inno;

	$ndigest = $digest;
	$arg = '';
	$cnt = $total = 0;
	$nonumber = $nolabel = $barchart = FALSE;

	foreach ( $args as $opt ){
		$opt = trim($opt);

		switch ($opt) {
		case '':
		case $str_notimestamp:
			continue;
		case $str_nonumber:
			$nonumber = TRUE;
			continue;
		case $str_nolabel:
			$nolabel = TRUE;
			continue;
		case $str_notitle:
			$notitle = TRUE;
			continue;
		case $str_barchart:
			$barchart = TRUE;
			continue;
		}

		if ( preg_match('/^(.+(?==))=([+-]?\d+)([ibr]?)$/',$opt,$match) ) {
			list($page,$vote_inno,$f_vote_inno,$ndigest) 
				= plugin_vote2_address($match,$vote_inno,$page,$ndigest);
			continue;
		}

		if ( $arg == '' and preg_match("/^(.*)\[(\d+)\]$/",$opt,$match)){
			$arg = $match[1];
			$cnt = $match[2];
			$total += $match[2];
			continue;
		}

		if ( $arg == '' ) {
			$arg = $opt;
		}
	}

//	if ( $arg == ''  ) return '';
	$link = make_link($arg);
	$e_arg = encode($arg);
/*
	$f_page = rawurlencode($page);
	$f_digest = rawurlencode($ndigest);
	$f_vote_plugin_votes = rawurlencode($_vote_plugin_votes);
*/
	$f_cnf = '';
	if ( $nonumber == FALSE ) {
		$vote_title = $notitle ? '' : "title=\"$o_vote_inno\"";
		$f_cnt = "<span $title>" . $cnt . "</span>";
	}
	if ( $nolabel == FALSE ) {
		$vote_title = $notitle ? '' : " title=\"$f_vote_inno\"";
		$vote_uri = get_cmd_uri('vote2','','',array('refer'=>$page,'vote_inno'=>$vote_inno,'vote_'.$e_arg=>$_vote_plugin_votes,'digest'=>$ndigest));
		return '<a href="'.$vote_uri.'"'.$vote_title.'>'.$link.'</a>'.$f_cnt;
/*
		return <<<EOD
<a href="$script?plugin=vote2&amp;refer=$f_page&amp;vote_inno=$vote_inno&amp;vote_$e_arg=$f_vote_plugin_votes&amp;digest=$f_digest" $title>$link</a>$f_cnt
EOD;
*/
	}
	else {
		return $f_cnt;
	}
}
function plugin_vote2_address($match, $vote_no, $page, $ndigest)
{
	global $digests;

	$this_flag = FALSE;
	$npage          = trim($match[1]);
	$vote2_no_arg   = $match[2];
	$vote2_attr_arg = $match[3];

	if ( $npage == 'this' ) {
		$npage   = $page;
		$this_flag = TRUE;
	}
	else {
		$npage      = preg_replace('/^\[\[(.*)\]\]$/','$1', $npage);
		if ( $npage == $page ){
			$this_flag = TRUE;
		}
		else if ( ! is_page($npage) ) {
			$vote2_attr_arg = 'error';
		}
		else if ( array_key_exists($npage, $digests) ) {
			$ndigest = $digests[$npage];
		}
		else {
			$ndigest    = md5(join('',get_source($npage)));
			$digests[$npage] = $ndigest;
		}
	}
	switch ( $vote2_attr_arg ){
		case '': 
		case 'i': 
		case 'b': $vote_no  = $vote2_no_arg . $vote2_attr_arg; break;
		case 'r': 
			if ( $this_flag ) {
				$vote_no += $vote2_no_arg;
			}
			else {
				$vote_no = 'error';
			}
			 break;
		default:  $vote_no  = 'error'; break;
	}
	$f_vote_no = htmlsc($npage . '=' . $vote_no);
	return array($npage, $vote_no, $f_vote_no, $ndigest);
}
function plugin_vote2_convert()
{
	global $vars,$digest, $_vote2_messages;
	global $_vote_plugin_choice, $_vote_plugin_votes;
	global $digests;
	static $numbers = array();
	static $notitle = FALSE;
//$_vote_plugin_choice = _("Selection");
//$_vote_plugin_votes  = _("Vote");
	$str_notimestamp = $_vote2_messages['arg_notimestamp'];
	$str_nonumber    = $_vote2_messages['arg_nonumber'];
	$str_nolabel     = $_vote2_messages['arg_nolabel'];
	$str_notitle     = $_vote2_messages['arg_notitle'];
	$str_barchart    = $_vote2_messages['arg_barchart'];
	
	if (!array_key_exists($vars['page'],$numbers))
	{
		$numbers[$vars['page']] = 0;
	}
	$o_vote_no = $f_vote_no = $vote_no = $numbers[$vars['page']]++;
	
	if (!func_num_args())
	{
		return '';
	}

	$args = func_get_args();
	$page = $vars['page'];

	$ndigest = $digest;
	$tdcnt = 0;
	$body2 = '';
	$nonumber = $nolabel = $barchart = FALSE;
	$options = array();
	foreach($args as $arg)
	{
		$arg = trim($arg);
		switch ($arg) {
		case $str_notimestamp:
			continue;
		case $str_nonumber:
			$nonumber = TRUE;
			continue;
		case $str_nolabel:
			$nolabel = TRUE;
			continue;
		case $str_notitle:
			$notitle = TRUE;
			continue;
		case $str_barchart:
			$barchart = TRUE;
			continue;
		default:
			$options[] = $arg;
		}
	}

	// Total
	$total = 0;
	if ($barchart) {
		foreach($options as $arg) {
			if ( preg_match('/^(.+(?==))=([+-]?\d+)([bir]?)$/',$arg,$match) ) continue;
			if ( preg_match('/^(.*)\[(\d+)\]$/',$arg,$match)) {
				$total += $match[2];
				continue;
			}
		}

		if ($total > 0) {
			$bar = new BARCHART(0, 0, 100);
			$bar->setColorBg(VOTE2_COLOR_BG);
			$bar->setColorBorder(VOTE2_COLOR_BORDER);
			$bar->setColorCompound(VOTE2_COLOR_BAR);
		} else {
			$barchart = FALSE;
		}
	}

	foreach($options as $arg)
	{
		$cnt = 0;
		if ( preg_match('/^(.+(?==))=([+-]?\d+)([bir]?)$/',$arg,$match) ) {
			list($page,$vote_no,$f_vote_no,$ndigest) 
				= plugin_vote2_address($match,$vote_no,$page,$ndigest);
			continue;
		}
		if ( preg_match('/^(.*)\[(\d+)\]$/',$arg,$match)) {
			$arg = $match[1];
			$cnt = $match[2];
		}

		$e_arg = encode($arg);
		$f_cnf = '';
		if ( $nonumber == FALSE ) {
			$title = $notitle ? '' : "title=\"$o_vote_no\"";
			$f_cnt = "<span $title>" . $cnt . '</span>';
		}
		if ($barchart) {
			$Percentage = (int)(($cnt / $total) * 100);
			$bar->setCurrPoint($Percentage);
			$getBar = $bar->getBar();
			$barchart_style = 'style="width:95%;"';
		} else {
			$barchart_style = '';
		}
		$link = make_link($arg);
		
		$cls = ($tdcnt++ % 2)  ? 'vote_td1' : 'vote_td2';

		$body2 .= <<<EOD
	<tr>
		<td class="style_td style_vote_title">$link</td>

EOD;

		$body2 .= <<<EOD
		<td class="style_td style_vote_count">$f_cnt

EOD;

		if ( $nolabel == FALSE ) {
			$body2 .= <<<EOD
		<input type="submit" name="vote_$e_arg" value="$_vote_plugin_votes" class="submit" />

EOD;
		}

		$body2 .= "   </td>\n";

		if ($barchart) {
			$body2 .= <<<EOD
		<td class="style_td $cls" >$getBar</td>

EOD;
		}

		$body2 .= "	</tr>\n";
	}
	$script = get_script_uri();
	$s_page    = htmlsc($page);
	$s_digest  = htmlsc($ndigest);
	$body = <<<EOD
<form action="$script" method="post">
	<input type="hidden" name="cmd" value="vote2" />
	<input type="hidden" name="refer" value="$s_page" />
	<input type="hidden" name="digest" value="$s_digest" />
	<input type="hidden" name="vote_no" value="$vote_no" />
	<table class="style_table" $barchart_style summary="vote">
		<thead>
			<tr>
				<th class="style_th vote_label">$_vote_plugin_choice</th>

EOD;
	if ($barchart) {
		$body .= <<<EOD
				<th class="style_th vote_label"></th>

EOD;
	}

	$body .= <<<EOD
				<th class="style_th vote_label">$_vote_plugin_votes</th>
			</tr>
		</thead>
		<tbody>
$body2
		</tbody>
	</table>
</form>

EOD;
	
	return $body;
}

function plugin_vote2_action_inline($vote_no)
{
	global $get,$vars,$cols,$rows, $_vote2_messages;
//	global $_title_collided,$_msg_collided,$_title_updated;
	global $_vote_plugin_choice, $_vote_plugin_votes;
$_title_collided   = _("On updating $1, a collision has occurred.");
$_title_updated    = _("$1 was updated");
$_msg_collided = _("It seems that someone has already updated this page while you were editing it.<br />") .
		 _(" + is placed at the beginning of a line that was newly added.<br />") .
		 _(" ! is placed at the beginning of a line that has possibly been updated.<br />") .
		 _(" Edit those lines, and submit again.");
//$_vote_plugin_choice = _("Selection");
//$_vote_plugin_votes  = _("Vote");
	$str_notimestamp = $_vote2_messages['arg_notimestamp'];
	$str_nonumber    = $_vote2_messages['arg_nonumber'];
	$str_nolabel     = $_vote2_messages['arg_nolabel'];
	$str_notitle     = $_vote2_messages['arg_notitle'];
	
	$str_plugin = 'vote2';
	$len_plugin = strlen($str_plugin) + 1;
	$title = $body = $postdata = '';
	$vote_ct = $skipflag = 0;
	$page = $vars['page'];
	$postdata_old  = get_source($vars['refer']);

	$ic = new InlineConverter(array('plugin'));
	$notimestamp = $update_flag = FALSE;
	foreach($postdata_old as $line)
	{
		if ( $skipflag || substr($line,0,1) == ' ' || substr($line,0,2) == '//' ) {
			$postdata .= $line;
			continue;
		}
		$pos = 0;
		$arr = $ic->get_objects($line,$page);
		while ( count($arr) ) {
			$obj = array_shift($arr);
			if ( $obj->name != $str_plugin ) continue;
			$pos = strpos($line, '&' . $str_plugin, $pos);
			if ( $vote_ct++ < $vote_no ) {
				$pos += $len_plugin;
				continue;
			}
			$l_line = substr($line,0,$pos);
			$r_line = substr($line,$pos + strlen($obj->text));
			$options = explode(',', $obj->param);
			$cnt = 0;
			$name = '';
			$vote = array();
			foreach ( $options as $opt ){
				$arg = trim($opt);
				if ( $arg == '' ) continue;
				if ( $arg == $str_notimestamp ) {
					$notimestamp = TRUE;
					$vote[] = $arg;
					continue;
				}
				if ( $arg == $str_nonumber || $arg == $str_nolabel || $arg == $str_notitle ) {
					$vote[] = $arg;
					continue;
				}
				if (preg_match("/^.+(?==)=[+-]?\d+[bir]?$/",$arg,$match)) {
					$vote[] = $arg;
					continue;
				}
				if ( $name == '' and preg_match("/^(.*)\[(\d+)\]$/",$arg,$match)) {
					$name = $match[1];
					$cnt  = $match[2];
					continue;
				}
				else if ( $name == '' ){
					$name = $arg;
					continue;
				}
				$vote[] = $arg;
			}
			array_unshift($vote, $name .'['.($cnt+1).']');
			$vote_str = "&$str_plugin(".join(',',$vote).');';
			$pline = $l_line . $vote_str . $r_line;
			if ( $pline !== $line ) $update_flag = TRUE;
			$postdata_input = $line = $pline;
			$skipflag = 1;
			break;
		}
		$postdata .= $line;
	}

	// if ( md5(@join('',get_source($vars['refer']))) != $vars['digest'])
	if ( md5(@join('',$postdata_old)) != $vars['digest'])
	{
		$title = $_title_collided;
		$body  = $_vote2_messages['msg_collided'] . make_pagelink($vars['refer']) . 
				"<hr />\n $postdata_input";
	}
	else if ( $update_flag == TRUE ) 
	{
		page_write($vars['refer'],$postdata,$notimestamp);
		$title = $_title_updated;

//$body = convert_html($postdata . "\n----\n"). $postdata_input . "/" . $vote_str . "/" . $vote . "/" . $name;
//$title = "debug for vote2";
	}
	else {
		$title = $_vote2_messages['update_failed'];
	}

	$retvars['msg'] = $title;
	$retvars['body'] = $body;

	$get['page'] = $vars['refer'];
	$vars['page'] = $vars['refer'];

	unset($postdata_old,$postdata);
	return $retvars;
}
function plugin_vote2_action_block($vote_no)
{
	global $post,$vars,$cols,$rows, $_vote2_messages;
//	global $_title_collided,$_msg_collided,$_title_updated;
	global $_vote_plugin_choice, $_vote_plugin_votes;
$_title_collided   = _("On updating $1, a collision has occurred.");
$_title_updated    = _("$1 was updated");
$_msg_collided = _("It seems that someone has already updated this page while you were editing it.<br />") .
		 _(" + is placed at the beginning of a line that was newly added.<br />") .
		 _(" ! is placed at the beginning of a line that has possibly been updated.<br />") .
		 _(" Edit those lines, and submit again.");
//$_vote_plugin_choice = _("Selection");
//$_vote_plugin_votes  = _("Vote");
	$str_notimestamp = $_vote2_messages['arg_notimestamp'];
	$str_nonumber    = $_vote2_messages['arg_nonumber'];
	$str_nolabel     = $_vote2_messages['arg_nolabel'];
	$str_notitle     = $_vote2_messages['arg_notitle'];
	$str_barchart    = $_vote2_messages['arg_barchart'];
	$notimestamp = $update_flag = FALSE;

	$postdata_old  = get_source($vars['refer']);
	$vote_ct = 0;
	$title = $body = $postdata = '';

	foreach($postdata_old as $line)
	{
		if (!preg_match("/^#vote2\((.*)\)\s*$/",$line,$arg))
		{
			$postdata .= $line;
			continue;
		}
		
		if ($vote_ct++ != $vote_no)
		{
			$postdata .= $line;
			continue;
		}
		$args = explode(',',$arg[1]);
		
		foreach($args as $arg)
		{
			$arg = trim($arg);
			$cnt = 0;
			if ( $arg == $str_notimestamp ){
				$notimestamp = TRUE;
				$votes[] = $arg;
				continue;
			}
			else if ( $arg == '' ) {
				continue;
			} 
			else if ( $arg == $str_nonumber || $arg == $str_nolabel || $arg == $str_notitle || $arg == $str_barchart ){
				$votes[] =  $arg;
				continue;
			}
			else if (preg_match("/^.+(?==)=[+-]?\d+[bir]?$/",$arg,$match)){
				$votes[] = $arg;
				continue;
			}
			else if (preg_match("/^(.*)\[(\d+)\]$/",$arg,$match))
			{
				$arg = $match[1];
				$cnt = $match[2];
			}
			$e_arg = encode($arg);
			if (!empty($vars["vote_$e_arg"]) and $vars["vote_$e_arg"] == $_vote_plugin_votes)
			{
				$cnt++;
				$update_flag = TRUE;
			}
			$votes[] =  $arg.'['.$cnt.']';
		}
		$vote_str = '#vote2('.@join(',',$votes).")\n";
		
		$postdata_input = $vote_str;
		$postdata .= $vote_str;
	}

	// if ( md5(@join('',get_source($vars['refer']))) != $vars['digest'] )
	if ( md5(@join('',$postdata_old)) != $vars['digest'] )
	{
		$title = $_title_collided;
		$body  = $_vote2_messages['msg_collided'] . make_pagelink($vars['refer']) . 
				"<hr />\n $postdata_input";
	}
	else if ( $update_flag == TRUE ) 
	{
		$title = $_title_updated;
		page_write($vars['refer'],$postdata,$notimestamp);
	}
	else {
		$title = $_vote2_messages['update_failed'];
	}

	$retvars['msg'] = $title;
	$retvars['body'] = $body;

	$post['page'] = $vars['refer'];
	$vars['page'] = $vars['refer'];

	unset($postdata_old,$postdata);
	return $retvars;
}
/* End of file vote2.inc.php */
/* Location: ./wiki-common/plugin/vote2.inc.php */
