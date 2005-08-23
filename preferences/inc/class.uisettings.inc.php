<?php
	/**************************************************************************\
	* eGroupWare - Preferences                                                 *
	* http://www.egroupware.org                                                *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	class uisettings
	{
		var $public_functions = array('index' => True);
		var $t;
		var $list_shown = False;
		var $show_help;
		var $has_help;
		var $prefix = '';

		function uisettings()
		{
			$this->bo =& CreateObject('preferences.bosettings',$_GET['appname']);

			if($GLOBALS['egw']->acl->check('run',1,'admin'))
			{
				/* Don't use a global variable for this ... */
				define('HAS_ADMIN_RIGHTS',1);
			}
		}

		function index()
		{
			if($_POST['cancel'])
			{
				$GLOBALS['egw']->redirect_link('/preferences/index.php');
			}
			if (substr($_SERVER['PHP_SELF'],-15) == 'preferences.php')
			{
				$pref_link = '/preferences/preferences.php';
				$link_params = array(
					'appname'    => $_GET['appname'],
				);
			}
			else
			{
				$pref_link = '/index.php';
				$link_params = array(
					'menuaction' => 'preferences.uisettings.index',
					'appname'    => $_GET['appname'],
				);
			}
			$user    = get_var('user',Array('POST'));
			$forced  = get_var('forced',Array('POST'));
			$default = get_var('default',Array('POST'));

			$this->t =& CreateObject('phpgwapi.Template',$GLOBALS['egw']->common->get_tpl_dir('preferences'));
			$this->t->set_file(array(
				'preferences' => 'preferences.tpl'
			));
			$this->t->set_block('preferences','list','lists');
			$this->t->set_block('preferences','row','rowhandle');
			$this->t->set_block('preferences','help_row','help_rowhandle');
			$this->t->set_var(array('rowhandle' => '','help_rowhandle' => '','messages' => ''));

			$this->prefix = get_var('prefix',array('GET'),$this->bo->session_data['appname'] == $_GET['appname'] ? $this->bo->session_data['prefix'] : '');

			if($this->is_admin())
			{
				/* This is where we will keep track of our postion. */
				/* Developers won't have to pass around a variable then */

				$GLOBALS['type'] = get_var('type',Array('GET','POST'),$this->bo->session_data['type']);

				if(empty($GLOBALS['type']))
				{
					$GLOBALS['type'] = 'user';
				}
			}
			else
			{
				$GLOBALS['type'] = 'user';
			}
			$this->show_help = $this->bo->session_data['show_help'] != '' && $this->bo->session_data['appname'] == $_GET['appname']
				? $this->bo->session_data['show_help']
				: (int)$GLOBALS['egw_info']['user']['preferences']['common']['show_help'];

			if($toggle_help = get_var('toggle_help','POST'))
			{
				$this->show_help = (int)(!$this->show_help);
			}
			$this->has_help = 0;

			if($_POST['save'] || $_POST['apply'])
			{
				/* Don't use a switch here, we need to check some permissions during the ifs */
				if($GLOBALS['type'] == 'user' || !($GLOBALS['type']))
				{
					$error = $this->bo->process_array($GLOBALS['egw']->preferences->user,$user,$this->bo->session_data['notifies'],$GLOBALS['type'],$this->prefix);
				}

				if($GLOBALS['type'] == 'default' && $this->is_admin())
				{
					$error = $this->bo->process_array($GLOBALS['egw']->preferences->default, $default,$this->bo->session_data['notifies'],$GLOBALS['type']);
				}

				if($GLOBALS['type'] == 'forced' && $this->is_admin())
				{
					$error = $this->bo->process_array($GLOBALS['egw']->preferences->forced, $forced,$this->bo->session_data['notifies'],$GLOBALS['type']);
				}

				if(!$this->is_admin() || $error)
				{
					$GLOBALS['egw']->redirect_link('/preferences/index.php');
				}

				if($GLOBALS['type'] == 'user' && $_GET['appname'] == 'preferences' && $user['show_help'] != '')
				{
					$this->show_help = $user['show_help'];	// use it, if admin changes his help-prefs
				}
				if($_POST['save'])
				{
					$GLOBALS['egw']->redirect_link('/preferences/index.php');
				}
			}

			// save our state in the app-session
			$this->bo->save_session($_GET['appname'],$GLOBALS['type'],$this->show_help,$this->prefix);

			// changes for the admin itself, should have immediate feedback ==> redirect
			if(!$error && ($_POST['save'] || $_POST['apply']) && $GLOBALS['type'] == 'user' && $_GET['appname'] == 'preferences')
			{
				$GLOBALS['egw']->redirect_link($pref_link,$link_params);
			}

			$this->t->set_var('messages',$error);
			$this->t->set_var('action_url',$GLOBALS['egw']->link($pref_link,$link_params));
			$this->t->set_var('th_bg',  $GLOBALS['egw_info']['theme']['th_bg']);
			$this->t->set_var('th_text',$GLOBALS['egw_info']['theme']['th_text']);
			$this->t->set_var('row_on', $GLOBALS['egw_info']['theme']['row_on']);
			$this->t->set_var('row_off',$GLOBALS['egw_info']['theme']['row_off']);

			$this->bo->read($this->check_app(),$this->prefix,$GLOBALS['type']);
			//echo "prefs=<pre>"; print_r($this->bo->prefs); echo "</pre>\n";

			$this->notifies = array();
			if(!$this->bo->call_hook($_GET['appname']))
			{
				$this->t->set_block('preferences','form','formhandle');	// skip the form
				$this->t->set_var('formhandle','');

				$this->t->set_var('messages',lang('Error: There was a problem finding the preference file for %1 in %2',
					$GLOBALS['egw_info']['apps'][$_GET['appname']]['title'],
					EGW_SERVER_ROOT . SEP . $_GET['appname'] . SEP . 'inc' . SEP . 'hook_settings.inc.php'
				));
			}

			foreach($this->bo->settings as $key => $valarray)
			{
				if(!$this->is_admin())
				{
					if($valarray['admin'])
					{
						continue;
					}
				}
				switch($valarray['type'])
				{
					case 'section':
						$this->create_section($valarray['title']);
						break;
					case 'input':
						$this->create_input_box(
							$valarray['label'],
							$valarray['name'],
							$valarray['help'],
							$valarray['default'],
							$valarray['size'],
							$valarray['maxsize'],
							$valarray['type'],
							$valarray['run_lang']	// if run_lang is set and false $valarray['help'] is run through lang()
						);
						break;
					case 'password':
						$this->create_password_box(
							$valarray['label'],
							$valarray['name'],
							$valarray['help'],
							$valarray['size'],
							$valarray['maxsize'],
							$valarray['run_lang']
						);
						break;
					case 'text':
						$this->create_text_area(
							$valarray['label'],
							$valarray['name'],
							$valarray['rows'],
							$valarray['cols'],
							$valarray['help'],
							$valarray['default'],
							$valarray['run_lang']
						);
						break;
					case 'select':
						$this->create_select_box(
							$valarray['label'],
							$valarray['name'],
							$valarray['values'],
							$valarray['help'],
							$valarray['default']
						);
						break;
					case 'check':
						$this->create_check_box(
							$valarray['label'],
							$valarray['name'],
							$valarray['help'],
							$valarray['default'],
							$valarray['run_lang']
						);
						break;
					case 'notify':
						$this->create_notify(
							$valarray['label'],
							$valarray['name'],
							$valarray['rows'],
							$valarray['cols'],
							$valarray['help'],
							$valarray['default'],
							$valarray['values'],
							$valarray['subst_help'],
							$valarray['run_lang']
						);
						break;
				}
			}

			$GLOBALS['egw_info']['flags']['app_header'] = $_GET['appname'] == 'preferences' ?
				lang('Preferences') : lang('%1 - Preferences',$GLOBALS['egw_info']['apps'][$_GET['appname']]['title']);
			$GLOBALS['egw']->common->egw_header();
			echo parse_navbar();

			if(count($this->notifies))	// there have been notifies in the hook, we need to save in the session
			{
				$this->bo->save_session($_GET['appname'],$GLOBALS['type'],$this->show_help,$this->prefix,$this->notifies);
				//echo "notifies="; _debug_array($this->notifies);
			}
			if($this->is_admin())
			{
				$tabs[] = array(
					'label' => lang('Your preferences'),
					'link'  => $GLOBALS['egw']->link($pref_link,$link_params+array('type'=>'user')),
				);
				$tabs[] = array(
					'label' => lang('Default preferences'),
					'link'  => $GLOBALS['egw']->link($pref_link,$link_params+array('type'=>'default')),
				);
				$tabs[] = array(
					'label' => lang('Forced preferences'),
					'link'  => $GLOBALS['egw']->link($pref_link,$link_params+array('type'=>'forced')),
				);

				switch($GLOBALS['type'])
				{
					case 'user':    $selected = 0; break;
					case 'default': $selected = 1; break;
					case 'forced':  $selected = 2; break;
				}
				$this->t->set_var('tabs',$GLOBALS['egw']->common->create_tabs($tabs,$selected));
			}
			$this->t->set_var('lang_save', lang('save'));
			$this->t->set_var('lang_apply', lang('apply'));
			$this->t->set_var('lang_cancel', lang('cancel'));
			$this->t->set_var('show_help',(int)$this->show_help);
			$this->t->set_var('help_button',$this->has_help ? '<input type="submit" name="toggle_help" value="'.
			($this->show_help ? lang('help off') : lang('help')).'">' : '');

			if(!$this->list_shown)
			{
				$this->show_list();
			}
			$this->t->pfp('phpgw_body','preferences');

			//echo '<pre style="text-align: left;">'; print_r($GLOBALS['egw']->preferences->data); echo "</pre>\n";

			$GLOBALS['egw']->common->egw_footer();
		}

		/* Make things a little easier to follow */
		/* Some places we will need to change this if they're in common */
		function check_app()
		{
			if($_GET['appname'] == 'preferences')
			{
				return 'common';
			}
			else
			{
				return $_GET['appname'];
			}
		}

		function is_forced_value($_appname,$preference_name)
		{
			if(isset($GLOBALS['egw']->preferences->forced[$_appname][$preference_name]) && $GLOBALS['type'] != 'forced')
			{
				return True;
			}
			else
			{
				return False;
			}
		}

		function create_password_box($label_name,$preference_name,$help='',$size='',$max_size='',$run_lang=True)
		{
			$_appname = $this->check_app();
			if($this->is_forced_value($_appname,$preference_name))
			{
				return True;
			}
			$this->create_input_box($label_name,$preference_name.'][pw',$help,'',$size,$max_size,'password',$run_lang);
		}

		function create_input_box($label,$name,$help='',$default='',$size='',$max_size='',$type='',$run_lang=True)
		{
			$charSet = $GLOBALS['egw']->translation->charset();

			$_appname = $this->check_app();
			if($this->is_forced_value($_appname,$name))
			{
				return True;
			}

			if($type)	// used to specify password
			{
				$options = " TYPE='$type'";
			}
			if($size)
			{
				$options .= " SIZE='$size'";
			}
			if($maxsize)
			{
				$options .= " MAXSIZE='$maxsize'";
			}

			if(isset($this->bo->prefs[$name]) || $GLOBALS['type'] != 'user')
			{
				$default = $this->bo->prefs[$name];
			}

			if($GLOBALS['type'] == 'user')
			{
				$def_text = !$GLOBALS['egw']->preferences->user[$_appname][$name] ? $GLOBALS['egw']->preferences->data[$_appname][$name] : $GLOBALS['egw']->preferences->default[$_appname][$name];

				if(isset($this->notifies[$name]))	// translate the substitution names
				{
					$def_text = $GLOBALS['egw']->preferences->lang_notify($def_text,$this->notifies[$name]);
				}
				$def_text = $def_text != '' ? ' <i><font size="-1">'.lang('default').':&nbsp;'.$def_text.'</font></i>' : '';
			}
			$this->t->set_var('row_value',"<input name=\"${GLOBALS[type]}[$name]\"value=\"".
			@htmlspecialchars($default,ENT_COMPAT,$charSet)."\"$options>$def_text");
			$this->t->set_var('row_name',$run_lang !== -1 ? lang($label) : $label);
			$GLOBALS['egw']->nextmatchs->template_alternate_row_color($this->t);

			$this->t->fp('rows',$this->process_help($help,$run_lang) ? 'help_row' : 'row',True);
		}

		function process_help($help,$run_lang=True)
		{
			if(!empty($help))
			{
				$this->has_help = True;

				if($this->show_help)
				{
					$this->t->set_var('help_value',is_null($run_lang) || $run_lang ? lang($help) : $help);

					return True;
				}
			}
			return False;
		}

		function create_check_box($label,$name,$help='',$default='',$run_lang=True)
		{
			// checkboxes itself can't be use as they return nothing if uncheckt !!!

			if($GLOBALS['type'] != 'user')
			{
				$default = '';	// no defaults for default or forced prefs
			}
			if(isset($this->bo->prefs[$name]))
			{
				$this->bo->prefs[$name] = (int)(!!$this->bo->prefs[$name]);	// to care for '' and 'True'
			}

			return $this->create_select_box($label,$name,array(
				'0' => lang('No'),
				'1' => lang('Yes')
			),$help,$default,$run_lang);
		}

		function create_option_string($selected,$values)
		{
			while(is_array($values) && list($var,$value) = each($values))
			{
				$s .= '<option value="' . $var . '"';
				if("$var" == "$selected")	// the "'s are necessary to force a string-compare
				{
					$s .= ' selected="1"';
				}
				$s .= '>' . $value . '</option>';
			}
			return $s;
		}

		/* for creating different sections with a title */
		function create_section($title='')
		{
			$this->t->set_var('row_value','');
			$this->t->set_var('row_name','<span class="prefSection">'.lang($title).'</span>');
			$GLOBALS['egw']->nextmatchs->template_alternate_row_color($this->t);

			$this->t->fp('rows',$this->process_help($help) ? 'help_row' : 'row',True);
		}

		/* for creating different sections with a title */
		function create_subsection($title='')
		{
			$this->t->set_var('row_value','');
			$this->t->set_var('row_name','<span class="prefSubSection">'.lang($title).'</span>');
			$GLOBALS['egw']->nextmatchs->template_alternate_row_color($this->t);

			$this->t->fp('rows',$this->process_help($help) ? 'help_row' : 'row',True);
		}

		function create_select_box($label,$name,$values,$help='',$default='',$run_lang=True)
		{
			$_appname = $this->check_app();
			if($this->is_forced_value($_appname,$name))
			{
				return True;
			}

			if(isset($this->bo->prefs[$name]) || $GLOBALS['type'] != 'user')
			{
				$default = $this->bo->prefs[$name];
			}

			switch($GLOBALS['type'])
			{
				case 'user':
					$s = '<option value="">' . lang('Use default') . '</option>';
					break;
				case 'default':
					$s = '<option value="">' . lang('No default') . '</option>';
					break;
				case 'forced':
					$s = '<option value="**NULL**">' . lang('Users choice') . '</option>';
					break;
			}
			$s .= $this->create_option_string($default,$values);
			if($GLOBALS['type'] == 'user')
			{
				$def_text = $GLOBALS['egw']->preferences->default[$_appname][$name];
				$def_text = $def_text != '' ? ' <i><font size="-1">'.lang('default').':&nbsp;'.$values[$def_text].'</font></i>' : '';
			}
			$this->t->set_var('row_value',"<select name=\"${GLOBALS[type]}[$name]\">$s</select>$def_text");
			$this->t->set_var('row_name',$run_lang !== -1 ? lang($label) : $label);
			$GLOBALS['egw']->nextmatchs->template_alternate_row_color($this->t);

			$this->t->fp('rows',$this->process_help($help,$run_lang) ? 'help_row' : 'row',True);
		}

		/**
		* creates text-area or inputfield with subtitution-variables
		*
		* @param string $label untranslated label
		* @param string $name name of the pref
		* @param int $rows of the textarea or input-box ($rows==1)
		* @param int $cols of the textarea or input-box ($rows==1)
		* @param string $help='' untranslated help-text, run through lang if $run_lang != false
		* @param string $default='' default-value
		* @param array $vars2='' array with extra substitution-variables of the form key => help-text
		* @param boolean $subst_help=true show help about substitues
		* @param boolean $run_lang=true should $help help be run through lang()
		*/
		function create_notify($label,$name,$rows,$cols,$help='',$default='',$vars2='',$subst_help=True,$run_lang=True)
		{
			$vars = $GLOBALS['egw']->preferences->vars;
			if(is_array($vars2))
			{
				$vars += $vars2;
			}
			$this->bo->prefs[$name] = $GLOBALS['egw']->preferences->lang_notify($this->bo->prefs[$name],$vars);

			$this->notifies[$name] = $vars;	// this gets saved in the app_session for re-translation

			$help = $help && ($run_lang || is_null($run_lang)) ? lang($help) : $help;
			if($subst_help || is_null($subst_help))
			{
				$help .= '<p><b>'.lang('Substitutions and their meanings:').'</b>';
				foreach($vars as $var => $var_help)
				{
					$lname = ($lname = lang($var)) == $var.'*' ? $var : $lname;
					$help .= "<br>\n".'<b>$$'.$lname.'$$</b>: '.$var_help;
				}
				$help .= "</p>\n";
			}
			if($row == 1)
			{
				$this->create_input_box($label,$name,$help,$default,$cols,'','',False);
			}
			else
			{
				$this->create_text_area($label,$name,$rows,$cols,$help,$default,False);
			}
		}

		function create_text_area($label,$name,$rows,$cols,$help='',$default='',$run_lang=True)
		{
			$charSet = $GLOBALS['egw']->translation->charset();

			$_appname = $this->check_app();
			if($this->is_forced_value($_appname,$name))
			{
				return True;
			}

			if(isset($this->bo->prefs[$name]) || $GLOBALS['type'] != 'user')
			{
				$default = $this->bo->prefs[$name];
			}

			if($GLOBALS['type'] == 'user')
			{
				$def_text = !$GLOBALS['egw']->preferences->user[$_appname][$name] ? $GLOBALS['egw']->preferences->data[$_appname][$name] : $GLOBALS['egw']->preferences->default[$_appname][$name];

				if(isset($this->notifies[$name]))	// translate the substitution names
				{
					$def_text = $GLOBALS['egw']->preferences->lang_notify($def_text,$this->notifies[$name]);
				}
				$def_text = $def_text != '' ? '<br><i><font size="-1"><b>'.lang('default').'</b>:<br>'.nl2br($def_text).'</font></i>' : '';
			}
			$this->t->set_var('row_value',"<textarea rows=\"$rows\" cols=\"$cols\" name=\"${GLOBALS[type]}[$name]\">".
			htmlentities($default,ENT_COMPAT,$charSet)."</textarea>$def_text");
			$this->t->set_var('row_name',lang($label));
			$GLOBALS['egw']->nextmatchs->template_alternate_row_color($this->t);

			$this->t->fp('rows',$this->process_help($help,$run_lang) ? 'help_row' : 'row',True);
		}

		/* Makes the ifs a little nicer, plus ... this will change once the ACL manager is in place */
		/* and is able to create less powerfull admins.  This will handle the ACL checks for that (jengo) */
		function is_admin()
		{
			if(HAS_ADMIN_RIGHTS == 1 && empty($this->prefix))	// tabs only without prefix
			{
				return True;
			}
			else
			{
				return False;
			}
		}

		function show_list($header='&nbsp;')
		{
			$this->t->set_var('list_header',$header);
			$this->t->parse('lists','list',$this->list_shown);

			$this->t->set_var('rows','');
			$this->list_shown = True;
		}
	}
