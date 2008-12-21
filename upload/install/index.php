<?php
/**
*
* @package automod
* @version $Id: install_automod.php 125 2008-12-17 19:27:04Z jelly_doughnut $
* @copyright (c) 2008 phpBB Group
* @license http://opensource.org/licenses/gpl-2.0.php GNU Public License
*
*/

/**
* @ignore
*/
define('IN_PHPBB', true);
define('DEBUG', true);
$phpbb_root_path = (defined('PHPBB_ROOT_PATH')) ? PHPBB_ROOT_PATH : '../';
$phpEx = substr(strrchr(__FILE__, '.'), 1);
include($phpbb_root_path . 'common.' . $phpEx);
include($phpbb_root_path . 'includes/functions_transfer.'.$phpEx);
include($phpbb_root_path . 'includes/functions_mods.'.$phpEx);
include($phpbb_root_path . 'install/install_automod.'.$phpEx);

// Start session management
$user->session_begin();
$auth->acl($user->data);
$user->setup(array('install', 'acp/mods'));

if ($user->data['user_type'] != USER_FOUNDER)
{
	trigger_error('NOT_AUTHORISED');
}

$mode = request_var('mode', '');
$sub = request_var('sub', 'intro');
$current_version = '1.0.0-b1';
$page_title = $user->lang['AUTOMOD_INSTALLATION'];
/*
if (isset($config['automod_version']) && $sub == 'intro')
{
	if (version_compare($config['automod_version'], $current_version, '<='))
	{
		trigger_error('AUTOMOD_CANNOT_INSTALL_OLD_VERSION');
	}
}*/

$template->set_custom_template($phpbb_root_path . 'adm/style', 'admin');
$install = new install_automod();

$method = basename(request_var('method', ''));
if (!$method || !class_exists($method))
{
	$method = 'ftp';
	$methods = transfer::methods();

	if (!in_array('ftp', $methods))
	{
		$method = $methods[0];
	}
}

$test_connection = false;
$test_ftp_connection = request_var('test_connection', '');
if (!empty($test_ftp_connection))
{
	test_ftp_connection($method, $test_ftp_connection, $test_connection);

	// Make sure the login details are correct before continuing
	if ($test_connection !== true || !empty($test_ftp_connection))
	{
		$sub = 'intro';
		$test_ftp_connection = true;
	}
}


switch ($sub)
{
	case 'intro':
		$can_proceed = true;

		if (!is_readable($phpbb_root_path . 'index.'.$phpEx))
		{
			$template->assign_vars('ROOT_NOT_READABLE', true);
			$can_proceed = false;
		}
		if (!is_writable($phpbb_root_path . 'store/'))
		{
			$template->assign_var('STORE_NOT_WRITABLE', true);
			$can_proceed = false;
		}

		$u_action = append_sid($phpbb_root_path . 'install/index.'.$phpEx, 'sub=' . (($can_proceed) ? 'create_table' : 'intro'));

		$template->assign_vars(array(
			'S_OVERVIEW'		=> true,
			'TITLE'				=> $user->lang['AUTOMOD_INSTALLATION'],
			'BODY'				=> $user->lang['AUTOMOD_INSTALLATION_EXPLAIN'],
			'L_SUBMIT'			=> $user->lang['NEXT_STEP'],
			'U_ACTION'			=> $u_action,
		));

		if (!is_writable($phpbb_root_path))
		{
			$s_hidden_fields = build_hidden_fields(array('method' => $method));

			$page_title = 'SELECT_FTP_SETTINGS';

			if (!class_exists($method))
			{
				trigger_error('Method does not exist.', E_USER_ERROR);
			}

			$requested_data = call_user_func(array($method, 'data'));
			foreach ($requested_data as $data => $default)
			{
				$template->assign_block_vars('data', array(
					'DATA'		=> $data,
					'NAME'		=> $user->lang[strtoupper($method . '_' . $data)],
					'EXPLAIN'	=> $user->lang[strtoupper($method . '_' . $data) . '_EXPLAIN'],
					'DEFAULT'	=> (!empty($_REQUEST[$data])) ? request_var($data, '') : $default
				));
			}

			$template->assign_vars(array(
				'S_CONNECTION_SUCCESS'		=> ($test_ftp_connection && $test_connection === true) ? true : false,
				'S_CONNECTION_FAILED'		=> ($test_ftp_connection && $test_connection !== true) ? true : false,
				'ERROR_MSG'					=> ($test_ftp_connection && $test_connection !== true) ? $user->lang[$test_connection] : '',

				'S_FTP_UPLOAD'		=> true,
				'UPLOAD_METHOD'		=> $method,
				'S_HIDDEN_FIELDS'	=> $s_hidden_fields)
			);
		}
	break;

	case 'create_table':
		$install->add_config($mode, $sub);
		$install->perform_sql($mode, $sub);
		$install->add_modules($mode, $sub);

		// Reset cache so we can actualy see the lovely new tab in the ACP
		$cache->purge();
	break;

	case 'final':
		$template->assign_vars(array(
			'S_FINAL'		=> true,
			'TITLE'			=> $user->lang['STAGE_FINAL'],
			'L_INDEX'		=> $user->lang['INDEX'],
			'L_INSTALLATION_SUCCESSFUL'	=> $user->lang['INSTALLATION_SUCCESSFUL'],
			'U_INDEX'		=> append_sid("{$phpbb_root_path}index.$phpEx"),
			'U_ACP_INDEX'	=> append_sid("{$phpbb_root_path}adm/index.$phpEx"),
		));
	break;
}

$template->set_filenames(array(
	'body'	=> 'install_mod.html',
));

page_header($page_title, false);
page_footer();

?>