<?php

/* Original "Bump Thread" plugin by Zinga Burga (Yumi).
 * Modified/adapted by Sama34 (Omar U.) to work alogn with MyBB's points plugin, "Newpoins" by Piarata Nervo.
 * One function from "HTML In Posts" plugin by Pirata Nervo as well.
 * Most thanks to those developers for their hard work!!!
 *
 * Zinga Burga (Yumi): http://mybbhacks.zingaburga.com/
 * Pirata Nervo: http://forums.mybb-plugins.com/
 * Sama34 (Omar U.): http://udezain.com.ar/
*/

// Disallow direct access to this file for security reasons
if(!defined('IN_MYBB'))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

// Add the hooks we are going to use.
if(defined("IN_ADMINCP"))
{
	$plugins->add_hook("newpoints_admin_grouprules_add", "npbumpthread_admin_grouprules");
	$plugins->add_hook("newpoints_admin_grouprules_edit", "npbumpthread_admin_grouprules");
	$plugins->add_hook("newpoints_admin_grouprules_add_insert", "npbumpthread_admin_grouprules_post");
	$plugins->add_hook("newpoints_admin_grouprules_edit_update", "npbumpthread_admin_grouprules_post");
	$plugins->add_hook("newpoints_admin_forumrules_add", "npbumpthread_admin_forumrules");
	$plugins->add_hook("newpoints_admin_forumrules_edit", "npbumpthread_admin_forumrules");
	$plugins->add_hook("newpoints_admin_forumrules_add_insert", "npbumpthread_admin_forumrules_post");
	$plugins->add_hook("newpoints_admin_forumrules_edit_update", "npbumpthread_admin_forumrules_post");
}
else
{
	$plugins->add_hook('datahandler_post_insert_post', 'npbumpthread_newpost');
	$plugins->add_hook('datahandler_post_insert_thread', 'npbumpthread_newthread');
	$plugins->add_hook('showthread_start', 'npbumpthread_run');
	$plugins->add_hook('forumdisplay_start', 'npbumpthread_foruminject');
	$plugins->add_hook('global_start', 'npbumpthread_cachetemplate');
}

/*** Newpoints ACP side. ***/
function npbumpthread_info()
{
	global $mybb, $lang;
	newpoints_lang_load("npbumpthread"); // Load ./inc/plugins/newpoints/languages/LANG/admin/npbumpthread.lang.php

	if($mybb->input['module'] == 'newpoints-plugins')
	{
		$lang->bt_acp_plugind .= '<br/><br/><p style="padding-left:10px;margin:0;">'.$lang->bt_acp_plugind2.'</p>';
	}
	return array(
		'name'			=> $lang->bt_acp_plugint,
		'description'	=> $lang->bt_acp_plugind,
		//'website'		=> 'http://mybbhacks.zingaburga.com/',
		'website'		=> 'http://forums.mybb-plugins.com/Thread-Adapted-Bump-Threads',
		'author'		=> 'ZiNgA BuRgA',
		'authorsite'	=> 'http://zingaburga.com/',
		'version'		=> '1.1',
		'compatibility'	=> '16*',
		'codename'		=> 'npbumpthread',
	);
}
function npbumpthread_activate()
{
	global $mybb;
	// Add the plugin template.
	newpoints_add_template("npbumpthread", '<a href="{$threadlink}?action=bump" title="{$bt_title}" rel="nofollow"><img src="{$theme[\'imglangdir\']}/npbumpthread.gif" alt="{$bt_title}" title="{$bt_title}" /></a>&nbsp;', "-1");
	// Modify the showthread template to add the button variable.
	require_once MYBB_ROOT.'/inc/adminfunctions_templates.php';
	find_replace_templatesets('showthread', '#'.preg_quote('{$newreply}').'#', '{$npbumpthread}{$newreply}');
}
function npbumpthread_deactivate()
{
	global $mybb;
	// Remove the plugin template.
	newpoints_remove_templates("'npbumpthread'");
	// Modify the showthread template to remove the button variable.
	require_once MYBB_ROOT.'/inc/adminfunctions_templates.php';
	find_replace_templatesets('showthread', '#'.preg_quote('{$npbumpthread}').'#', '',0);

}
function npbumpthread_install()
{
	global $db, $mybb, $lang;

	// First we need to remove the plugin settings.
	newpoints_remove_settings("'npbumpthread_on', 'npbumpthread_interval', 'npbumpthread_forums', 'npbumpthread_groups', 'npbumpthread_points'");
	// Now we can insert them so everything is clean.
	newpoints_lang_load("npbumpthread");
	newpoints_add_setting("npbumpthread_on", "npbumpthread", $lang->bt_acp_setting_on_n, $lang->bt_acp_setting_on_d, "onoff", "0", "1");
	newpoints_add_setting("npbumpthread_interval", "npbumpthread", $lang->bt_acp_setting_time_n, $lang->bt_acp_setting_time_d, "text", "30", "2");
	newpoints_add_setting("npbumpthread_forums", "npbumpthread", $lang->bt_acp_setting_forums_n, $lang->bt_acp_setting_forums_d, "text", "2,3", "3");
	newpoints_add_setting("npbumpthread_groups", "npbumpthread", $lang->bt_acp_setting_groups_n, $lang->bt_acp_setting_groups_d, "text", "3,4", "4");
	newpoints_add_setting("npbumpthread_points", "npbumpthread", $lang->bt_acp_setting_points_n, $lang->bt_acp_setting_points_d, "text", "10", "6");
	rebuild_settings();

	// We need to check is our plugin columns exist, if they do, then drop them...
	if($db->field_exists('lastpostbump', 'threads'))
	{
		$db->drop_column("threads", "lastpostbump");
	}
	if($db->field_exists('bumps_rate', 'newpoints_grouprules'))
	{
		$db->drop_column("newpoints_grouprules", "bumps_rate");
	}
	if($db->field_exists('bumps_rate', 'newpoints_forumrules'))
	{
		$db->drop_column("newpoints_forumrules", "bumps_rate");
	}
	if($db->field_exists('bumps_forums', 'newpoints_grouprules'))
	{
		$db->drop_column("newpoints_grouprules", "bumps_forums");
	}
	if($db->field_exists('bumps_groups', 'newpoints_forumrules'))
	{
		$db->drop_column("newpoints_forumrules", "bumps_groups");
	}
	// Add the columns now amd update the "lastpostbump" column at least.
	$db->add_column("threads", "lastpostbump", "BIGINT(30) UNSIGNED NOT NULL DEFAULT '0'");
	$db->add_column("newpoints_grouprules", "bumps_rate", "float NOT NULL default '1'");
	$db->add_column("newpoints_forumrules", "bumps_rate", "float NOT NULL default '1'");
	$db->add_column("newpoints_grouprules", "bumps_forums", "text NOT NULL default ''");
	$db->add_column("newpoints_forumrules", "bumps_groups", "text NOT NULL default ''");
	$db->query('UPDATE '.$db->table_prefix.'threads SET lastpostbump=lastpost');
}
function npbumpthread_uninstall()
{
	global $db, $mybb;

	// Remove the plugin settings.
	newpoints_remove_settings("'npbumpthread_on', 'npbumpthread_interval', 'npbumpthread_forums', 'npbumpthread_groups', 'npbumpthread_points'");
	rebuild_settings();

	// Remove the plugin columns, if any...
	if($db->field_exists('lastpostbump', 'threads'))
	{
		$db->drop_column("threads", "lastpostbump");
	}
	if($db->field_exists('bumps_rate', 'newpoints_grouprules'))
	{
		$db->drop_column("newpoints_grouprules", "bumps_rate");
	}
	if($db->field_exists('bumps_rate', 'newpoints_forumrules'))
	{
		$db->drop_column("newpoints_forumrules", "bumps_rate");
	}
	if($db->field_exists('bumps_forums', 'newpoints_grouprules'))
	{
		$db->drop_column("newpoints_grouprules", "bumps_forums");
	}
	if($db->field_exists('bumps_groups', 'newpoints_forumrules'))
	{
		$db->drop_column("newpoints_forumrules", "bumps_groups");
	}

	// Clean any logs from this plugin.
	newpoints_remove_log(array("bump"));
}
function npbumpthread_is_installed()
{
	global $db;
	if($db->field_exists('lastpostbump', 'threads') || $db->field_exists('bumps_rate', 'newpoints_grouprules') || $db->field_exists('bumps_rate', 'newpoints_forumrules'))
	{
		return true;
	}
	return false;
}

/*** Newpoints ACP side. ***/
function npbumpthread_admin_grouprules(&$form_container)
{
	global $mybb, $db, $lang, $form, $rule;
	newpoints_lang_load("npbumpthread");

	// If adding a group rule..
	if($mybb->input['action'] == 'add')
	{
		$form_container->output_row($lang->bt_acp_rulerate, $lang->bt_acp_grouprule, $form->generate_text_box('bumps_rate', '1', array('id' => 'bumps_rate')), 'bumps_rate');
		$form_container->output_row($lang->bt_acp_rule_forums_n, $lang->bt_acp_rule_forums_d, $form->generate_text_box('bumps_forums', '', array('id' => 'bumps_forums')), 'bumps_forums');
	}
	// If editing a group rule..
	elseif($mybb->input['action'] == 'edit')
	{
		$form_container->output_row($lang->bt_acp_rulerate, $lang->bt_acp_grouprule, $form->generate_text_box('bumps_rate', $rule['bumps_rate'], array('id' => 'bumps_rate')), 'bumps_rate');
		$form_container->output_row($lang->bt_acp_rule_forums_n, $lang->bt_acp_rule_forums_d, $form->generate_text_box('bumps_forums', $rule['bumps_forums'], array('id' => 'bumps_forums')), 'bumps_forums');
	}
}
function npbumpthread_admin_grouprules_post(&$array)
{
	global $mybb, $db, $lang, $form, $rule;

	// Insert the value..?
	$array['bumps_rate'] = floatval($mybb->input['bumps_rate']);
	$array['bumps_forums'] = $mybb->input['bumps_forums'];
}
function npbumpthread_admin_forumrules()
{
	global $mybb, $db, $lang, $form, $rule, $form_container;
	newpoints_lang_load("npbumpthread");

	// If adding a forum rule..
	if($mybb->input['action'] == 'add')
	{
		$form_container->output_row($lang->bt_acp_rulerate, $lang->bt_acp_forumrule, $form->generate_text_box('bumps_rate', '1', array('id' => 'bumps_rate')), 'bumps_rate');
		$form_container->output_row($lang->bt_acp_rule_groups_n, $lang->bt_acp_rule_groups_d, $form->generate_text_box('bumps_groups', '', array('id' => 'bumps_groups')), 'bumps_rate');
	}
	// If editing a forum rule..
	elseif($mybb->input['action'] == 'edit')
	{
		$form_container->output_row($lang->bt_acp_rulerate, $lang->bt_acp_forumrule, $form->generate_text_box('bumps_rate', $rule['bumps_rate'], array('id' => 'bumps_rate')), 'bumps_rate');
		$form_container->output_row($lang->bt_acp_rule_groups_n, $lang->bt_acp_rule_groups_d, $form->generate_text_box('bumps_groups', $rule['bumps_groups'], array('id' => 'bumps_groups')), 'bumps_groups');
	}
}
function npbumpthread_admin_forumrules_post(&$array)
{
	global $mybb, $db, $lang, $form, $rule;

	// Insert the value..?
	$array['bumps_rate'] = floatval($mybb->input['bumps_rate']);
	$array['bumps_groups'] = $mybb->input['bumps_groups'];
}

/*** Forum side. ***/
function npbumpthread_cachetemplate()
{
	global $templatelist, $current_page;
	if($current_page == 'showthread.php' && isset($templatelist))
	{
		$templatelist = str_replace(',index_whosonline,', ',index_whosonline,index_whosonline_today,', $templatelist);
	}
}
function npbumpthread_check_permissions($groups_comma)
{
	global $mybb;

	if($groups_comma == '')
	{
		return false;
	}
	$groups = explode(",", $groups_comma);
	
	$ourgroups = explode(",", $mybb->user['additionalgroups']);
	$ourgroups[] = $mybb->user['usergroup'];

	if(count(array_intersect($ourgroups, $groups)) == 0)
	{
		return false;
	}
	else
	{
		return true;
	}
}
function npbumpthread_newthread(&$ph)
{
	$ph->thread_insert_data['lastpostbump'] = $ph->data['dateline'];
}
function npbumpthread_newpost(&$ph)
{
	global $db;
	$db->update_query('threads', array('lastpostbump' => TIME_NOW), 'tid='.$ph->data['tid']);
}
function npbumpthread_run()
{
	global $mybb, $thread, $lang;
	newpoints_lang_load("npbumpthread");

	//*** Check if there are rules for this forum...
	$forumrules = newpoints_getrules('forum', $thread['fid']);
		// Allowed groups is global plugin groups.
		$allowed_groups = $mybb->settings['npbumpthread_groups'];
		// There are forum rules and 'bumps_groups' is not empty, overwrite global plugin allowed usergroups...
		if($forumrules && !empty($forumrules['bumps_groups']))
		{
			$allowed_groups = $forumrules['bumps_groups']; // Allowed groups is forum rules allowed groups.
		}
	//*** Check if there are rules for this usergroup...
	$groupsrules = newpoints_getrules('group', $mybb->user['usergroup']);
		// Allowed forums is global plugin forums.
		$allowed_forums = $mybb->settings['npbumpthread_forums'];
		// There are group rules and 'bumps_forums' is not empty, overwrite global plugin allowed forums...
		if($groupsrules && !empty($groupsrules['bumps_forums']))
		{
			$allowed_forums = $groupsrules['bumps_forums']; // Allowed forums is group rules allowed forums.
		}
		$allowed_forum = explode(",", $allowed_forums);

	// Basic information to know if continue or just ignore current user/location/plugin status.
	if($mybb->settings['npbumpthread_on'] != 0 && $thread['closed'] != 1 && $mybb->user['uid'] != '0' && (npbumpthread_check_permissions($allowed_groups) || empty($allowed_groups)) && (in_array($thread['fid'], $allowed_forum) || empty($allowed_forums)))
	{
		$lastpostbump = $thread['lastpostbump'];
		$npbt_cancp = $mybb->usergroup['cancp'];
		$npbt_smod = $mybb->usergroup['issupermod'];
		$npbt_interval = intval($mybb->settings['npbumpthread_interval']);
		$points = intval($mybb->settings['npbumpthread_points']);
		$threadlink = get_thread_link($thread['tid']);
		// If action is "bump"...
		if($mybb->input['action'] == "bump")
		{
			//If there are not forumrules, set default 'bumps_rate'...
			if(!$forumrules)
			{
				$forumrules['bumps_rate'] = 1.0;
			}
			//If there are not grouprules, set default 'bumps_rate'...
			if(!$groupsrules)
			{
				$groupsrules['bumps_rate'] = 1.0;
			}
			// Set $points based in groupsrules and forumrules.
			$points = $points*floatval($groupsrules['bumps_rate'])*floatval($forumrules['bumps_rate']);
			/*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_**_*_*_*_*/
			// If is not admin/global_mod/thread_author, show no permission page.
			if($npbt_cancp != 1 && $npbt_smod != 1 && $thread['uid'] != $mybb->user['uid']){
				error_no_permission();
			}
			// If is thread author and required points are highter that current user points, show error page.
			elseif($thread['uid'] == $mybb->user['uid'] && $points > $mybb->user['newpoints']){
				$points = newpoints_format_points($points);
				error($lang->sprintf($lang->bt_no_enought_points, $points));
			}
			// Is the last bump was not so long ago (from settings), show error.
			elseif($lastpostbump + $npbt_interval*60 > TIME_NOW){
				error($lang->sprintf($lang->bt_bump_error, $npbt_interval));
			}
			// They passed trow here, so lets bump the thread!!
			else
			{
				global $db;
				$db->update_query('threads', array('lastpostbump' => TIME_NOW), 'tid='.$thread['tid']);
				// If current user is thread author, remove points, otherwise, don't (so admins/global_mods can bump as much threads how they want, as long as they are not the original authors).
				if($thread['uid'] == $mybb->user['uid']){
					newpoints_addpoints($mybb->user['uid'], -(floatval($points)));
				}
				// Now log it...
				newpoints_log("bump", $mybb->settings['bburl'].'/'.$threadlink, $mybb->user['username'], $mybb->user['uid']);
				// GOOD!! Going back to thread...
				redirect($threadlink, $lang->bt_bumped_message, $lang->bt_bumped_title);
			}
		}
		// If it is not  admin/global_mod/thread_author, show the button.
		elseif($npbt_cancp == 1 || $npbt_smod == 1 || $thread['uid'] == $mybb->user['uid'])
		{
				global $templates, $theme, $npbumpthread;
				$bt_title = $lang->bt_bumpthis;
				if($lastpostbump + $npbt_interval*60 > TIME_NOW){
					$lastpostbump = my_date($mybb->settings['dateformat'], $lastpostbump).', '.my_date($mybb->settings['timeformat'], $lastpostbump);
					$bt_title = $lang->sprintf($lang->bt_lastbump, $lastpostbump);
				}
				eval('$npbumpthread = "'.$templates->get("npbumpthread").'";');
		}
	}
	elseif($mybb->input['action'] == "bump"){
		error_no_permission();
	}
}
function npbumpthread_foruminject()
{
	global $db;
	eval('
		class BumpThreadDummyDB extends '.get_class($db).'
		{
			function BumpThreadDummyDB(&$olddb)
			{
				$vars = get_object_vars($olddb);
				foreach($vars as $var => $val)
					$this->$var = $val;
			}
			
			function query($string, $hideerr=0)
			{
				$string = str_replace(\'t.lastpost\', \'t.lastpostbump\', $string);
				return parent::query($string, $hideerr);
			}
		}
	');
	$db = new BumpThreadDummyDB($db);
}
?>