<?php

/***************************************************************************
 *
 *   Newpoints Bump Thread plugin (/inc/plugins/newpoints/languages/english/admin/npbumpthread.php)
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

$l['bt_plugin'] = 'Bump Thread';
$l['bt_plugin_d'] = 'Allows users to bump their own threads without posting on exchange of points.';

$l['bt_plugin_d2'] = 'Original plugin coded by Zinga Burga from MyBBHacks ( mybbhacks.zingaburga.com ).';
$l['bt_set_interval'] = 'Time Between Bumps';
$l['bt_acp_setting_interval_d'] = 'The time (in minutes) a user must wait before they are allowed to (re) bump their thread.';
$l['bt_set_forums'] = 'Allowed Forums';
$l['bt_set_forums_d'] = 'Insert a comma separated list of forums where this feature can be use in. Empty = all.';
$l['bt_set_groups'] = 'Allowed Groups';
$l['bt_set_groups_d'] = 'Insert a comma separated list of groups that can use this feature. Empty = all.';
$l['bt_set_points'] = 'Points to Subtract';
$l['bt_set_points_d'] = 'Points to subtract when users bump their threads.';

$l['bt_rule_forumrate'] = 'Bump: Rate';
$l['bt_rule_forumrate_d'] = 'Enter the bump rate for the this forum. Default is 1';
$l['bt_rule_grouprate'] = 'Bump: Rate';
$l['bt_rule_grouprate_d'] = 'Enter the bump rate for the this group. Default is 1';
$l['bt_rule_groupforums'] = 'Bump: Allowed Forums';
$l['bt_rule_groupforums_d'] = 'Insert a comma separated list of forums where this group can use this feature in. Default is empty.';
$l['bt_rule_forumgroups'] = 'Bump: Allowed Groups';
$l['bt_rule_forumgroups_d'] = 'Insert a comma separated list of groups that can use this feature in this forum. Default is empty.';
$l['bt_rule_forumgroups_interval'] = 'Bump: Time Between Bumps';
$l['bt_rule_forumgroups_interval_d'] = 'Enter the time users must wait before bumping threads.';