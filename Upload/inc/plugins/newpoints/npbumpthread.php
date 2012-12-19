<?php

/***************************************************************************
 *
 *   Newpoints Bump Thread plugin (/inc/plugins/newpoints/npbumpthread.php)
 *	 Author: Omar Gonzalez
 *   Copyright: © 2012 Omar Gonzalez
 *   
 *   Website: http://community.mybb.com/user-25096.html
 *
 *   Allows users to bump their own threads without postingon exchange of points.
 *
 ***************************************************************************
 
****************************************************************************
	This program is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.
	
	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.
	
	You should have received a copy of the GNU General Public License
	along with this program.  If not, see <http://www.gnu.org/licenses/>.
****************************************************************************/

// Disallow direct access to this file for security reasons
defined('IN_MYBB') or die('Direct initialization of this file is not allowed.');

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
	$plugins->add_hook('showthread_start', 'npbumpthread_run', -9);

	if(THIS_SCRIPT == 'showthread.php')
	{
		global $templatelist;

		if(isset($templatelist))
		{
			$templatelist .= ',';
		}
		else
		{
			$templatelist = '';
		}
		$templatelist .= 'npbumpthread';
	}
	elseif(THIS_SCRIPT == 'forumdisplay.php')
	{
		npbumpthread_control_object($GLOBALS['db'], '
			function query($string, $hide_errors=0, $write_query=0)
			{
				if(strpos($conditions, \'lastpost\'))
				{
					$string = str_replace(array(\'(lastpost\', \'(t.lastpost\'), array(\'(lastpostbump\', \'(t.lastpostbump\'), $string);
				}
				return parent::query($string, $hide_errors, $write_query);
			}
		');
	}
}

/*** Newpoints ACP side. ***/
function npbumpthread_info()
{
	global $mybb, $lang;
	isset($lang->bt_plugin) or newpoints_lang_load('npbumpthread');

	if($mybb->input['module'] == 'newpoints-plugins')
	{
		$lang->bt_plugin_d .= '<br/><br/><p style="padding-left:10px;margin:0;">'.$lang->bt_plugin_d2.'</p>';
	}
	return array(
		'name'			=> 'Bump Thread',
		'description'	=> $lang->bt_plugin_d,
		'website'		=> 'http://forums.mybb-plugins.com/Thread-Adapted-Bump-Threads',
		'author'		=> 'Omar Gonzalez',
		'authorsite'	=> 'http://community.mybb.com/user-25096.html',
		'version'		=> '1.2',
		'compatibility'	=> '19*'
	);
}

// _activate
function npbumpthread_activate()
{
	global $db, $lang;
	isset($lang->bt_plugin) or newpoints_lang_load('npbumpthread');

	// Add the template if not already exiting
	$template = $db->simple_select('templates', '*', 'title=\'npbumpthread\' AND sid=\'-1\'');
	if(!$db->num_rows($template))
	{
		newpoints_add_template('npbumpthread', '<a href="{$threadlink}" title="{$title}" rel="nofollow"><img src="{$theme[\'imglangdir\']}/npbumpthread.gif" alt="{$title}" title="{$title}" /></a>&nbsp;');
	}

	// Now we can insert our settings
	$settings = array(
		'interval'	=> array(
			'value'		=> 30,
			'disporder'	=> 1
		),
		'forums'	=> array(
			'value'		=> '2,3',
			'disporder'	=> 2
		),
		'groups'	=> array(
			'value'		=> '3,4',
			'disporder'	=> 3
		),
		'points'	=> array(
			'value'		=> 10,
			'disporder'	=> 4
		)
	);
	$db->update_query('newpoints_settings', array('description' => 'DELETE_ME'), 'plugin=\'npbumpthread\'');
	foreach($settings as $key => $setting)
	{
		$lang_val = 'bt_set_'.$key;
		$lang_val_d = $lang_val.'_d';
		$alreadyexists = $db->num_rows($db->simple_select('newpoints_settings', 'name', 'plugin=\'npbumpthread\' AND name=\'npbumpthread_'.$key.'\''));
		if($alreadyexists)
		{
			$db->update_query('newpoints_settings', array(
				'title'			=> $db->escape_string($lang->$lang_val),
				'description'	=> $db->escape_string($lang->$lang_val_d),
				'disporder'		=> $setting['disporder']
			), 'plugin=\'npbumpthread\' AND name=\'npbumpthread_'.$key.'\'');
		}
		else
		{
			newpoints_add_setting('npbumpthread_'.$key, 'npbumpthread', $lang->$lang_val, $lang->$lang_val_d, 'text', $setting['value'], $setting['disporder']);
		}
	}
	$db->delete_query('newpoints_settings', 'plugin=\'npbumpthread\' AND description=\'DELETE_ME\'');

	// Update some things
	$db->field_exists('lastpostbump', 'users') or $db->add_column('users', 'lastpostbump', 'int(10) NOT NULL DEFAULT \'0\'');
	$db->modify_column('threads', 'lastpostbump', 'int(10) NOT NULL DEFAULT \'0\'');
	$db->modify_column('newpoints_grouprules', 'bumps_rate', 'float NOT NULL default \'1\'');
	$db->modify_column('newpoints_forumrules', 'bumps_rate', 'float NOT NULL default \'1\'');
	$db->modify_column('newpoints_grouprules', 'bumps_forums', 'text NOT NULL');
	$db->modify_column('newpoints_forumrules', 'bumps_groups', 'text NOT NULL');
	$db->modify_column('newpoints_grouprules', 'bumps_interval', 'int(10) NOT NULL DEFAULT \'0\'');
	$db->modify_column('newpoints_forumrules', 'bumps_interval', 'int(10) NOT NULL DEFAULT \'0\'');

	// Add the button variable
	require_once MYBB_ROOT.'/inc/adminfunctions_templates.php';
	find_replace_templatesets('showthread', '#'.preg_quote('{$newreply}').'#', '{$npbumpthread}{$newreply}');
}

// _deactivate
function npbumpthread_deactivate()
{
	// Remove the button variable
	require_once MYBB_ROOT.'/inc/adminfunctions_templates.php';
	find_replace_templatesets('showthread', '#'.preg_quote('{$npbumpthread}').'#', '',0);
}

// _install
function npbumpthread_install()
{
	global $db;

	// Add the columns now and update the "lastpostbump" column
	$db->field_exists('lastpostbump', 'threads') or $db->add_column('threads', 'lastpostbump', 'int(10) NOT NULL DEFAULT \'0\'');
	$db->update_query('threads', array('lastpostbump' => '`lastpost`'), '', '', true);
	#$db->query('UPDATE '.TABLE_PREFIX.'threads SET lastpostbump=lastpost');

	$db->field_exists('bumps_rate', 'newpoints_grouprules') or $db->add_column('newpoints_grouprules', 'bumps_rate', 'float NOT NULL default \'1\'');
	$db->field_exists('bumps_rate', 'newpoints_forumrules') or $db->add_column('newpoints_forumrules', 'bumps_rate', 'float NOT NULL default \'1\'');
	$db->field_exists('bumps_forums', 'newpoints_grouprules') or $db->add_column('newpoints_grouprules', 'bumps_forums', 'text NOT NULL');
	$db->field_exists('bumps_groups', 'newpoints_forumrules') or $db->add_column('newpoints_forumrules', 'bumps_groups', 'text NOT NULL');
	$db->field_exists('bumps_interval', 'newpoints_grouprules') or $db->add_column('newpoints_grouprules', 'bumps_interval', 'int(10) NOT NULL DEFAULT \'0\'');
	$db->field_exists('bumps_interval', 'newpoints_forumrules') or $db->add_column('newpoints_forumrules', 'bumps_interval', 'int(10) NOT NULL DEFAULT \'0\'');
	$db->field_exists('lastpostbump', 'users') or $db->add_column('users', 'lastpostbump', 'int(10) NOT NULL DEFAULT \'0\'');
}

// _uninstall
function npbumpthread_uninstall()
{
	global $db, $mybb;

	// Remove the plugin settings.
	newpoints_remove_settings("'npbumpthread_interval', 'npbumpthread_forums', 'npbumpthread_groups', 'npbumpthread_points'");

	// Remove the plugin template.
	newpoints_remove_templates("'npbumpthread'");

	// Remove the plugin columns, if any...
	!$db->field_exists('lastpostbump', 'threads') or $db->drop_column('threads', 'lastpostbump');
	!$db->field_exists('bumps_rate', 'newpoints_grouprules') or $db->drop_column('newpoints_grouprules', 'bumps_rate');
	!$db->field_exists('bumps_rate', 'newpoints_forumrules') or $db->drop_column('newpoints_forumrules', 'bumps_rate');
	!$db->field_exists('bumps_forums', 'newpoints_grouprules') or $db->drop_column('newpoints_grouprules', 'bumps_forums');
	!$db->field_exists('bumps_groups', 'newpoints_forumrules') or $db->drop_column('newpoints_forumrules', 'bumps_groups');
	!$db->field_exists('bumps_interval', 'newpoints_grouprules') or $db->drop_column('newpoints_grouprules', 'bumps_interval');
	!$db->field_exists('bumps_interval', 'newpoints_forumrules') or $db->drop_column('newpoints_forumrules', 'bumps_interval');
	!$db->field_exists('lastpostbump', 'users') or $db->drop_column('users', 'lastpostbump');

	// Clean any logs from this plugin.
	newpoints_remove_log(array('bump'));
}

// _is_insalled
function npbumpthread_is_installed()
{
	global $db;

	return $db->field_exists('lastpostbump', 'threads');
}

// Add our containers to the group rles page
function npbumpthread_admin_grouprules(&$form_container)
{
	global $mybb;

	// If adding a group rule..
	if($mybb->input['action'] == 'add' || $mybb->input['action'] == 'edit')
	{
		global $lang, $form, $rule;
		isset($lang->bt_plugin) or newpoints_lang_load('npbumpthread');

		$form_container->output_row($lang->bt_rule_grouprate, $lang->bt_rule_grouprate_d, $form->generate_text_box('bumps_rate', (isset($rule['bumps_rate']) ? (float)$rule['bumps_rate'] : 1), array('id' => 'bumps_rate')), 'bumps_rate');
		$form_container->output_row($lang->bt_rule_groupforums, $lang->bt_rule_groupforums_d, $form->generate_text_box('bumps_forums', (isset($rule['bumps_forums']) ? npbumpthread_clean_array($rule['bumps_forums'], true) : ''), array('id' => 'bumps_forums')), 'bumps_forums');
		$form_container->output_row($lang->bt_rule_forumgroups_interval, $lang->bt_rule_forumgroups_interval_d, $form->generate_text_box('bumps_interval', (isset($rule['bumps_interval']) ? (int)$rule['bumps_interval'] : ''), array('id' => 'bumps_interval')), 'bumps_interval');
	}
}

// Update group rules
function npbumpthread_admin_grouprules_post(&$array)
{
	global $mybb;

	// Insert the value..?
	$array['bumps_rate'] = (float)$mybb->input['bumps_rate'];
	$array['bumps_forums'] = npbumpthread_clean_array($mybb->input['bumps_forums']);
	$array['bumps_interval'] = (int)$mybb->input['bumps_interval'];
}

// Add our containers to the forum rles page
function npbumpthread_admin_forumrules()
{
	global $mybb;

	// If adding a forum rule..
	if($mybb->input['action'] == 'add' || $mybb->input['action'] == 'edit')
	{
		global $mybb, $lang, $form, $rule, $form_container;
		isset($lang->bt_plugin) or newpoints_lang_load('npbumpthread');

		$form_container->output_row($lang->bt_rule_forumrate, $lang->bt_rule_forumrate_d, $form->generate_text_box('bumps_rate', (isset($rule['bumps_rate']) ? (float)$rule['bumps_rate'] : 1), array('id' => 'bumps_rate')), 'bumps_rate');
		$form_container->output_row($lang->bt_rule_forumgroups, $lang->bt_rule_forumgroups_d, $form->generate_text_box('bumps_groups', (isset($rule['bumps_groups']) ? npbumpthread_clean_array($rule['bumps_groups'], true) : ''), array('id' => 'bumps_groups')), 'bumps_groups');
		$form_container->output_row($lang->bt_rule_forumgroups_interval, $lang->bt_rule_forumgroups_interval_d, $form->generate_text_box('bumps_interval', (isset($rule['bumps_interval']) ? (int)$rule['bumps_interval'] : ''), array('id' => 'bumps_interval')), 'bumps_interval');
	}
}

// Update forum rules
function npbumpthread_admin_forumrules_post(&$array)
{
	global $mybb;

	// Insert the value..?
	$array['bumps_rate'] = (float)$mybb->input['bumps_rate'];
	$array['bumps_groups'] = npbumpthread_clean_array($mybb->input['bumps_groups']);
	$array['bumps_interval'] = (int)$mybb->input['bumps_interval'];
}

// Check if user meets user group memberships
function npbumpthread_check_groups($groups)
{
	if(empty($groups))
	{
		return true;
	}

	global $mybb;
	$usergroups = explode(',', $mybb->user['additionalgroups']);
	$usergroups[] = $mybb->user['usergroup'];

	return (bool)array_intersect(array_map('intval', explode(',', $groups)), array_map('intval', $usergroups));
}

// We need to insert the bump dateline before the thread is actually inserted
function npbumpthread_newthread(&$dh)
{
	$dh->thread_insert_data['lastpostbump'] = (int)$dh->data['dateline'];
}

// Update thread bump date when inserting a new reply
function npbumpthread_newpost(&$dh)
{
	global $db;

	$db->update_query('threads', array('lastpostbump' => TIME_NOW), 'tid='.(int)$dh->data['tid']);
}

function npbumpthread_run()
{
	global $thread, $mybb, $lang;
	isset($lang->bt_plugin) or newpoints_lang_load('npbumpthread');

	// Some primary checks, simple first, complicated follows
	if(!(bool)$mybb->settings['npbumpthread_on'] || $thread['closed'])
	{
		return;
	}

	// Get newpoints rules
	$forumrules = newpoints_getrules('forum', $thread['fid']);
	$groupsrules = newpoints_getrules('group', $mybb->user['usergroup']);

	// Allowed forums
	$allowed_forums = $mybb->settings['npbumpthread_forums'];
	if(!empty($groupsrules['bumps_forums']) || $groupsrules['bumps_forums'] == '0')
	{
		$allowed_forums = $groupsrules['bumps_forums'];
	}
	$allowed_forum = npbumpthread_clean_array($allowed_forums);

	// Allowed groups
	$allowed_groups = $mybb->settings['npbumpthread_groups'];
	if(!empty($forumrules['bumps_groups']) || $forumrules['bumps_groups'] == '0')
	{
		$allowed_groups = $forumrules['bumps_groups'];
	}
	$allowed_groups = npbumpthread_clean_array($allowed_groups);

	// Interval time
	// The issue here is, should we use the largest interval ratio or the lowest one? This is "easy" to solve, allowing administrators to make use of the "-" sign inside the value to determine how it should work.
	// The real issue, is if whether forum or groups rules should be checked before any other, the order can modify the end result. I decided to go with forum rule first.
	$interval = (int)$mybb->settings['npbumpthread_interval'];
	if(!empty($forumrules['bumps_interval']) || $forumrules['bumps_interval'] == '0')
	{
		$finterval = (int)$forumrules['bumps_interval'];
		if(my_strpos($forumrules['bumps_interval'], '-'))
		{
			$overwrite = ($finterval < $interval);
		}
		else
		{
			$overwrite = ($finterval > $interval);
		}

		if($overwrite)
		{
			$interval = $finterval;
		}
	}
	if(!empty($groupsrules['bumps_interval']) || $groupsrules['bumps_interval'] == '0')
	{
		$ginterval = (int)$groupsrules['bumps_interval'];
		if(my_strpos($groupsrules['bumps_interval'], '-'))
		{
			$overwrite = ($ginterval < $interval);
		}
		else
		{
			$overwrite = ($ginterval > $interval);
		}

		if($overwrite)
		{
			$interval = $ginterval;
		}
	}
	
	/*$db->modify_column('newpoints_grouprules', 'bumps_interval', 'text NOT NULL');
	$db->modify_column('newpoints_forumrules', 'bumps_interval', 'text NOT NULL');*/

	if(!npbumpthread_check_groups($allowed_groups) || !in_array($thread['fid'], $allowed_forum))
	{
		return;
	}

	$lastpostbump = my_date($mybb->settings['dateformat'], $thread['lastpostbump']).', '.my_date($mybb->settings['timeformat'], $thread['lastpostbump']);
	$threadlink = get_thread_link($thread['tid'], 0, 'bump');

	$permission = (is_moderator($thread['fid']) || $thread['uid'] == $mybb->user['uid']);
	// Show the button.
	if($permission)
	{
		global $templates, $theme, $npbumpthread;

		$title = $lang->bt_bumpthis;
		if($thread['lastpostbump']+$interval*60 > TIME_NOW)
		{
			$title = $lang->sprintf($lang->bt_lastbump, $lastpostbump);
		}
		eval('$npbumpthread = "'.$templates->get('npbumpthread').'";');
	}

	if($mybb->input['action'] != 'bump')
	{
		return;
	}

	// Request
	if($mybb->user['uid'])
	{
		// Set $points based in groupsrules and forumrules.
		$points = (float)$mybb->settings['npbumpthread_points']*(float)(isset($groupsrules['bumps_rate']) ? $groupsrules['bumps_rate'] : 1)*(float)(isset($forumrules['bumps_rate']) ? $forumrules['bumps_rate'] : 1);

		// If is not admin/global_mod/thread_author, show no permission page.
		$permission or error_no_permission();

		// If is thread author and required points are highter that current user points, show error page.
		if($thread['uid'] == $mybb->user['uid'] && $points > (float)$mybb->user['newpoints'])
		{
			error($lang->sprintf($lang->bt_no_enought_points, newpoints_format_points($points)));
		}

		// Is the last bump was not so long ago (from settings), show error.
		if($thread['lastpostbump']+$interval*60 > TIME_NOW || $mybb->user['lastpostbump']+$interval*60 > TIME_NOW)
		{
			error($lang->sprintf($lang->bt_bump_error, my_format_nymber($interval)));
		}

		// They passed trow here, so lets bump the thread!!
		global $db;
		$db->update_query('threads', array('lastpostbump' => TIME_NOW), 'tid='.(int)$thread['tid']);
		$db->delete_query('forumsread', 'fid=\''.(int)$thread['fid'].'\''); // someone might complain..
		$db->delete_query('threadsread', 'tid=\''.(int)$thread['tid'].'\'');
		// need we t modify search queries? may be..

		// If current user is thread author, remove points, otherwise, don't (so admins/global_mods can bump as much threads how they want, as long as they are not the original authors).
		if($thread['uid'] == $mybb->user['uid'])
		{
			newpoints_addpoints($mybb->user['uid'], -$points);
		}
		// Log it.
		newpoints_log('bump', $mybb->settings['bburl'].'/'.$threadlink, $mybb->user['username'], $mybb->user['uid']);

		redirect($threadlink, $lang->bt_bumped_message, $lang->bt_bumped_title);
	}

	error_no_permission();
}

// Clean an array, too lazy
function npbumpthread_clean_array($array, $implode=false, $delimiter=',')
{
	if(!is_array($array))
	{
		$array = explode($delimiter, $array);
	}

	foreach($array as &$val)
	{
		$val = (int)$val;
	}

	$array = array_unique($array);

	if($implode)
	{
		return implode($delimiter, $array);
	}

	return $array;
}

// control_object by Zinga Burga from MyBBHacks ( mybbhacks.zingaburga.com ), 1.62
function npbumpthread_control_object(&$obj, $code)
{
	static $cnt = 0;
	$newname = '_objcont_'.(++$cnt);
	$objserial = serialize($obj);
	$classname = get_class($obj);
	$checkstr = 'O:'.strlen($classname).':"'.$classname.'":';
	$checkstr_len = strlen($checkstr);
	if(substr($objserial, 0, $checkstr_len) == $checkstr)
	{
		$vars = array();
		// grab resources/object etc, stripping scope info from keys
		foreach((array)$obj as $k => $v)
		{
			if($p = strrpos($k, "\0"))
			{
				$k = substr($k, $p+1);
			}
			$vars[$k] = $v;
		}
		if(!empty($vars))
		{
			$code .= '
				function ___setvars(&$a) {
					foreach($a as $k => &$v)
						$this->$k = $v;
				}
			';
		}
		eval('class '.$newname.' extends '.$classname.' {'.$code.'}');
		$obj = unserialize('O:'.strlen($newname).':"'.$newname.'":'.substr($objserial, $checkstr_len));
		if(!empty($vars))
		{
			$obj->___setvars($vars);
		}
	}
	// else not a valid object or PHP serialize has changed
}