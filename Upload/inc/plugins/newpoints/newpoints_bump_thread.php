<?php

/***************************************************************************
 *
 *	Newpoints Bump Thread plugin (/inc/plugins/newpoints/newpoints_bump_thread.php)
 *	Author: Omar Gonzalez
 *	Copyright: © 2012-2015 Omar Gonzalez
 *
 *	Website: http://omarg.me
 *
 *	Allows users to bump their own threads without postingon exchange of points.
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
	$plugins->add_hook("newpoints_admin_grouprules_add", "newpoints_bump_thread_admin_grouprules");
	$plugins->add_hook("newpoints_admin_grouprules_edit", "newpoints_bump_thread_admin_grouprules");
	$plugins->add_hook("newpoints_admin_grouprules_add_insert", "newpoints_bump_thread_admin_grouprules_post");
	$plugins->add_hook("newpoints_admin_grouprules_edit_update", "newpoints_bump_thread_admin_grouprules_post");
	$plugins->add_hook("newpoints_admin_forumrules_add", "newpoints_bump_thread_admin_forumrules");
	$plugins->add_hook("newpoints_admin_forumrules_edit", "newpoints_bump_thread_admin_forumrules");
	$plugins->add_hook("newpoints_admin_forumrules_add_insert", "newpoints_bump_thread_admin_forumrules_post");
	$plugins->add_hook("newpoints_admin_forumrules_edit_update", "newpoints_bump_thread_admin_forumrules_post");
	$plugins->add_hook('newpoints_rebuild_templates', 'newpoints_bump_thread_rebuild_templates');
}
else
{
	$plugins->add_hook('datahandler_post_insert_post', 'newpoints_bump_thread_newpost');
	$plugins->add_hook('datahandler_post_insert_thread', 'newpoints_bump_thread_newthread');
	$plugins->add_hook('showthread_start', 'newpoints_bump_thread_run', -9);
	$plugins->add_hook('forumdisplay_start', 'newpoints_bump_thread_forumdisplay', -9);

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
		$templatelist .= 'newpoints_bump_thread';
	}
}

/*** Newpoints ACP side. ***/
function newpoints_bump_thread_info()
{
	global $mybb, $lang;
	isset($lang->newpoints_bump_thread) or newpoints_lang_load('newpoints_bump_thread');

	if(!$mybb->get_input('module') || $mybb->get_input('module') == 'newpoints' || $mybb->get_input('module') == 'newpoints-plugins')
	{
		$lang->newpoints_bump_thread_desc .= '<br/><br/><p style="padding-left:10px;margin:0;">'.$lang->newpoints_bump_thread_credits.'</p>';
	}

	return array(
		'name'			=> 'Newpoints Bump Thread',
		'description'	=> $lang->newpoints_bump_thread_desc,
		'website'		=> 'http://omarg.me',
		'author'		=> 'Omar G.',
		'authorsite'	=> 'http://omarg.me',
		'version'		=> '2.0',
		'versioncode'	=> 2000,
		'compatibility'	=> '2*'
	);
}

// _activate() routine
function newpoints_bump_thread_activate()
{
	global $db, $lang, $cache;
	isset($lang->newpoints_bump_thread) or newpoints_lang_load('newpoints_bump_thread');

	// rebuild templates
	newpoints_rebuild_templates();

	// Now we can insert our settings
	newpoints_add_settings('newpoints_bump_thread', array(
			'interval'	=> array(
				'title'			=> $lang->setting_newpoints_bump_thread_interval,
				'description'	=> $lang->setting_newpoints_bump_thread_interval_desc,
				'type'			=> 'numeric',
				'value'			=> 30
			),
			'forums'	=> array(
				'title'			=> $lang->setting_newpoints_bump_thread_forums,
				'description'	=> $lang->setting_newpoints_bump_thread_forums_desc,
				'type'			=> 'forumselect',
				'value'			=> -1
			),
			'groups'	=> array(
				'title'			=> $lang->setting_newpoints_bump_thread_groups,
				'description'	=> $lang->setting_newpoints_bump_thread_groups_desc,
				'type'			=> 'groupselect',
				'value'			=> -1
			),
			'points'	=> array(
				'title'			=> $lang->setting_newpoints_bump_thread_points,
				'description'	=> $lang->setting_newpoints_bump_thread_points_desc,
				'type'			=> 'text',
				'value'			=> 10
			),
	));

	// Add the button variable
	require_once MYBB_ROOT.'/inc/adminfunctions_templates.php';
	find_replace_templatesets('showthread', '#'.preg_quote('{$newreply}').'#', '{$newpoints_bump_thread}{$newreply}');

	// Insert/update version into cache
	$plugins = $cache->read('ougc_plugins');
	if(!$plugins)
	{
		$plugins = array();
	}

	$info = newpoints_bump_thread_info();

	if(!isset($plugins['newpoints_bump_thread']))
	{
		$plugins['newpoints_bump_thread'] = $info['versioncode'];
	}

	/*~*~* RUN UPDATES START *~*~*/
	if($plugins['newpoints_bump_thread'] <= 2000)
	{
		//
	}
	/*~*~* RUN UPDATES END *~*~*/

	$plugins['newpoints_bump_thread'] = $info['versioncode'];
	$cache->update('ougc_plugins', $plugins);
}

// _deactivate
function newpoints_bump_thread_deactivate()
{
	// Remove the button variable
	require_once MYBB_ROOT.'/inc/adminfunctions_templates.php';
	find_replace_templatesets('showthread', '#'.preg_quote('{$newpoints_bump_thread}').'#', '',0);
}

// _install
function newpoints_bump_thread_install()
{
	global $db;

	$db->field_exists('lastpostbump', 'threads') or $db->add_column('threads', 'lastpostbump', 'int(10) NOT NULL DEFAULT \'0\'');
	$db->update_query('threads', array('lastpostbump' => '`lastpost`'), '', '', true);

	$db->field_exists('bumps_rate', 'newpoints_grouprules') or $db->add_column('newpoints_grouprules', 'bumps_rate', 'float NOT NULL default \'1\'');
	$db->field_exists('bumps_rate', 'newpoints_forumrules') or $db->add_column('newpoints_forumrules', 'bumps_rate', 'float NOT NULL default \'1\'');
	$db->field_exists('bumps_forums', 'newpoints_grouprules') or $db->add_column('newpoints_grouprules', 'bumps_forums', 'text NOT NULL');
	$db->field_exists('bumps_groups', 'newpoints_forumrules') or $db->add_column('newpoints_forumrules', 'bumps_groups', 'text NOT NULL');
	$db->field_exists('bumps_interval', 'newpoints_grouprules') or $db->add_column('newpoints_grouprules', 'bumps_interval', 'int(10) NOT NULL DEFAULT \'0\'');
	$db->field_exists('bumps_interval', 'newpoints_forumrules') or $db->add_column('newpoints_forumrules', 'bumps_interval', 'int(10) NOT NULL DEFAULT \'0\'');
	$db->field_exists('lastpostbump', 'users') or $db->add_column('users', 'lastpostbump', 'int(10) NOT NULL DEFAULT \'0\'');
}

// _uninstall
function newpoints_bump_thread_uninstall()
{
	global $db, $cache;

	// Remove the plugin settings.
	newpoints_remove_settings("'newpoints_bump_thread_interval', 'newpoints_bump_thread_forums', 'newpoints_bump_thread_groups', 'newpoints_bump_thread_points'");

	// Remove the plugin template.
	newpoints_remove_templates("'newpoints_bump_thread'");

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

	// Delete version from cache
	$plugins = (array)$cache->read('ougc_plugins');

	if(isset($plugins['newpoints_bump_thread']))
	{
		unset($plugins['newpoints_bump_thread']);
	}

	if(!empty($plugins))
	{
		$cache->update('ougc_plugins', $plugins);
	}
	else
	{
		$cache->delete('ougc_plugins');
	}
}

// _is_insalled
function newpoints_bump_thread_is_installed()
{
	global $db;

	return $db->field_exists('lastpostbump', 'threads');
}

// Insert our template
function newpoints_bump_thread_rebuild_templates(&$args)
{
	$args['bump_thread'] = '<a href="{$mybb->settings[\'bburl\']}/{$threadlink}" class="button new_reply_button" title="{$title}" rel="nofollow"><span>{$lang->newpoints_bump_thread}</span></a>&nbsp;';
}

// Add our containers to the group rules page
function newpoints_bump_thread_admin_grouprules(&$form_container)
{
	global $mybb;

	// If adding a group rule..
	if($mybb->get_input('action') == 'add' || $mybb->get_input('action') == 'edit')
	{
		global $lang, $form, $rule;
		isset($lang->newpoints_bump_thread) or newpoints_lang_load('newpoints_bump_thread');

		$form_container->output_row($lang->newpoints_bump_thread_grouprate, $lang->newpoints_bump_thread_grouprate_desc, $form->generate_text_box('bumps_rate', (isset($rule['bumps_rate']) ? (float)$rule['bumps_rate'] : 1), array('id' => 'bumps_rate')), 'bumps_rate');
		$form_container->output_row($lang->newpoints_bump_groupforums, $lang->newpoints_bump_groupforums_desc, $form->generate_text_box('bumps_forums', (isset($rule['bumps_forums']) ? newpoints_bump_thread_clean_array($rule['bumps_forums'], true) : ''), array('id' => 'bumps_forums')), 'bumps_forums');
		$form_container->output_row($lang->newpoints_bump_thread_interval, $lang->newpoints_bump_thread_interval_desc, $form->generate_text_box('bumps_interval', (isset($rule['bumps_interval']) ? (int)$rule['bumps_interval'] : ''), array('id' => 'bumps_interval')), 'bumps_interval');
	}
}

// Update group rules
function newpoints_bump_thread_admin_grouprules_post(&$array)
{
	global $mybb;

	// Insert the value..?
	$array['bumps_rate'] = $mybb->get_input('bumps_rate', 3);
	$array['bumps_forums'] = newpoints_bump_thread_clean_array($mybb->get_input('bumps_forums', 2));
	$array['bumps_interval'] = $mybb->get_input('bumps_interval', 1);
}

// Add our containers to the forum rles page
function newpoints_bump_thread_admin_forumrules()
{
	global $mybb;

	// If adding a forum rule..
	if($mybb->get_input('action') == 'add' || $mybb->get_input('action') == 'edit')
	{
		global $mybb, $lang, $form, $rule, $form_container;
		isset($lang->newpoints_bump_thread) or newpoints_lang_load('newpoints_bump_thread');

		$form_container->output_row($lang->newpoints_bump_thread_forumrate, $lang->newpoints_bump_thread_forumrate_desc, $form->generate_text_box('bumps_rate', (isset($rule['bumps_rate']) ? (float)$rule['bumps_rate'] : 1), array('id' => 'bumps_rate')), 'bumps_rate');
		$form_container->output_row($lang->newpoints_bump_forumgroups, $lang->newpoints_bump_forumgroups_desc, $form->generate_text_box('bumps_groups', (isset($rule['bumps_groups']) ? newpoints_bump_thread_clean_array($rule['bumps_groups'], true) : ''), array('id' => 'bumps_groups')), 'bumps_groups');
		$form_container->output_row($lang->newpoints_bump_thread_interval, $lang->newpoints_bump_thread_interval_desc, $form->generate_text_box('bumps_interval', (isset($rule['bumps_interval']) ? (int)$rule['bumps_interval'] : ''), array('id' => 'bumps_interval')), 'bumps_interval');
	}
}

// Update forum rules
function newpoints_bump_thread_admin_forumrules_post(&$array)
{
	global $mybb;

	// Insert the value..?
	$array['bumps_rate'] = $mybb->get_input('bumps_rate', 3);
	$array['bumps_groups'] = newpoints_bump_thread_clean_array($mybb->get_input('bumps_groups', 2));
	$array['bumps_interval'] = $mybb->get_input('bumps_interval', 1);
}

// Check if user meets user group memberships
function newpoints_bump_thread_check_groups($groups)
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
function newpoints_bump_thread_newthread(&$dh)
{
	$dh->thread_insert_data['lastpostbump'] = (int)$dh->data['dateline'];
}

// Update thread bump date when inserting a new reply
function newpoints_bump_thread_newpost(&$dh)
{
	global $db;

	$db->update_query('threads', array('lastpostbump' => TIME_NOW), 'tid='.(int)$dh->data['tid']);
}

function newpoints_bump_thread_run()
{
	global $thread, $mybb, $lang;
	isset($lang->newpoints_bump_thread) or newpoints_lang_load('newpoints_bump_thread');

	// Some primary checks, simple first, complicated follows
	if($thread['closed'])
	{
		return;
	}

	// Get newpoints rules
	$forumrules = newpoints_getrules('forum', $thread['fid']);
	$groupsrules = newpoints_getrules('group', $mybb->user['usergroup']);

	if(!empty($groupsrules['bumps_forums']) || $groupsrules['bumps_forums'] == '0')
	{
		$mybb->settings['newpoints_bump_thread_forums'] = $groupsrules['bumps_forums'];
	}

	if($mybb->settings['newpoints_bump_thread_forums'] != -1 && !strpos(','.$mybb->settings['newpoints_bump_thread_forums'].',', ','.$thread['fid'].','))
	{
		return;
	}

	if(!empty($forumrules['bumps_groups']) || $forumrules['bumps_groups'] == '0')
	{
		$mybb->settings['newpoints_bump_thread_groups'] = $forumrules['bumps_groups'];
	}

	if($mybb->settings['newpoints_bump_thread_groups'] != -1 && !is_member($mybb->settings['newpoints_bump_thread_groups']))
	{
		return;
	}

	// Interval time
	// The issue here is, should we use the largest interval ratio or the lowest one? This is "easy" to solve, allowing administrators to make use of the "-" sign inside the value to determine how it should work.
	// The real issue, is if whether forum or groups rules should be checked before any other, the order can modify the end result. I decided to go with forum rule first.
	$interval = (int)$mybb->settings['newpoints_bump_thread_interval'];
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

	$lastpostbump = my_date('relative', $thread['lastpostbump']);
	$threadlink = get_thread_link($thread['tid'], 0, 'bump');

	// Show the button.
	if($permission = (is_moderator($thread['fid']) || ($mybb->user['uid'] && $thread['uid'] == $mybb->user['uid'])))
	{
		global $templates, $theme, $newpoints_bump_thread;

		$title = $lang->newpoints_bump_thread;
		if($thread['lastpostbump']+$interval*60 > TIME_NOW)
		{
			$title = $lang->sprintf($lang->newpoints_bump_thread_last, $lastpostbump);
		}
		eval('$newpoints_bump_thread = "'.$templates->get('newpoints_bump_thread').'";');
	}

	if($mybb->get_input('action') != 'bump')
	{
		return;
	}

	// Request
	if($permission)
	{
		// Set $points based in groupsrules and forumrules.
		$points = (float)$mybb->settings['newpoints_bump_thread_points']*(float)(isset($groupsrules['bumps_rate']) ? $groupsrules['bumps_rate'] : 1)*(float)(isset($forumrules['bumps_rate']) ? $forumrules['bumps_rate'] : 1);

		// If is thread author and required points are higher that current user points, show error page.
		if($thread['uid'] == $mybb->user['uid'] && $points > (float)$mybb->user['newpoints'])
		{
			error($lang->sprintf($lang->newpoints_bump_thread_error_points, newpoints_format_points($points)));
		}

		// Is the last bump was not so long ago (from settings), show error.
		if($thread['lastpostbump']+$interval*60 > TIME_NOW || $mybb->user['lastpostbump']+$interval*60 > TIME_NOW)
		{
			error($lang->sprintf($lang->newpoints_bump_thread_error_interval, my_format_nymber($interval)));
		}

		// They passed trow here, so lets bump the thread!!
		global $db;
		$db->update_query('threads', array('lastpostbump' => TIME_NOW), 'tid='.(int)$thread['tid']);
		$db->update_query('users', array('lastpostbump' => TIME_NOW), 'uid='.(int)$mybb->user['uid']);
		$db->delete_query('forumsread', 'fid=\''.(int)$thread['fid'].'\''); // someone might complain..
		$db->delete_query('threadsread', 'tid=\''.(int)$thread['tid'].'\'');
		// need we to modify search queries? may be..

		// If current user is thread author, remove points, otherwise, don't (so admins/global_mods can bump as much threads how they want, as long as they are not the original authors).
		if($thread['uid'] == $mybb->user['uid'])
		{
			newpoints_addpoints($mybb->user['uid'], -$points);
		}

		$threadlink = get_thread_link($thread['tid']);

		// Log it.
		newpoints_log('bump', $mybb->settings['bburl'].'/'.$threadlink, $mybb->user['username'], $mybb->user['uid']);

		redirect($threadlink, $lang->newpoints_bump_thread_success_message, $lang->newpoints_bump_thread_success_title);
	}

	error_no_permission();
}

function newpoints_bump_thread_forumdisplay()
{
	global $mybb;

	if(!isset($mybb->input['sortby']) && !empty($foruminfo['defaultsortby']))
	{
		$mybb->input['sortby'] = $foruminfo['defaultsortby'];
	}

	switch($mybb->get_input('sortby'))
	{
		case 'subject':
		case 'replies':
		case 'views':
		case 'starter':
		case 'rating':
		case 'started':
			break;
		default:
			control_object($GLOBALS['db'], '
				function query($string, $hide_errors=0, $write_query=0)
				{
					if(!$done && strpos($string, \'t.sticky\') !== false && strpos($string, \'lastpost\') !== false)
					{
						$string = str_replace(array("lastpost ", "t.lastpost "), array("lastpostbump ", "t.lastpostbump "), $string);
					}

					return parent::query($string, $hide_errors, $write_query);
				}
			');
			break;
	}
}

// Clean an array, too lazy
function newpoints_bump_thread_clean_array($array, $implode=false, $delimiter=',')
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
if(!function_exists('control_object'))
{
	function control_object(&$obj, $code)
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
}