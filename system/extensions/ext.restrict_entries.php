<?php
//-----------------------------------------------
// Restrict Entries
// Restrict individual entries to groups from
// Publish page
// ----------------------------------------------
// This software is supplied without any warranty
// or liability by Purple Dogfish Ltd
//-----------------------------------------------

// Changes
//-----------------------------------------------

// Version 1.0.4
// Modded to work with Multi Site Manager by Nathan @ Nine Four (http://ninefour.co.uk)

// Version 1.0.3
// Changed language file to avoid conflict with
// Ping Servers 'select_all'

// Version 1.0.2
// Changed $EXT->script_ends variables to FALSE
// Modified check for $EXT->last_call in
// publish_form_new_tab_block

// Version 1.0.1
// Added $EXT->last_call variables to functions

if ( ! defined('EXT'))
{
    exit('Invalid file request');
}

class Restrict_entries
{
	var $settings        = array();

	var $name            = 'Restrict Entries';
	var $version         = '1.0.4';
	var $description     = 'Restrict individual entries to member groups irrespective of weblog or template';
	var $settings_exist  = 'y';
	var $docs_url        = 'http://www.purple-dogfish.co.uk/free-stuff/restrict-entries';
    
    // -------------------------------
    //   Constructor - Extensions use this for settings
    // -------------------------------
    
    function Restrict_entries($settings='')
    {
		global $DB;
		
        $this->settings = $settings;
    }
    // END

	// -------------------------------
    //   Settings
    // -------------------------------
	function settings()
	{
		global $DB;
		
		$settings = array();
		
		$sql = "SELECT w.weblog_id, s.site_id, s.site_label, w.blog_title FROM exp_weblogs AS w, exp_sites AS s WHERE (s.site_id=w.site_id) ORDER BY w.blog_title ASC";
		$query = $DB->query($sql);

		$options = array();
		
		foreach($query->result AS $key => $value)
		{
			  $options[$value['weblog_id']] = ($value['blog_title']." (".$value['site_label'].")");
		}
		
		$settings['select_all_groups']   = array('r', array('yes' => "yes", 'no' => "no"), 'no');
		$settings['weblog'] = array('ms', $options, '');
		
		return $settings;
	}
	// END
	
	// --------------------------------
	//  publish_form_new_tab
	// --------------------------------

	function publish_form_new_tab($publish_tabs, $weblog_id, $entry_id)
	{
		global $EXT, $LANG;

		$EXT->end_script = FALSE;
	
		if($EXT->last_call !== false)
		{
			$publish_tabs = $EXT->last_call;
		}
		
		if (!@in_array($weblog_id, $this->settings['weblog']) && @($this->settings['select_all_groups'] == 'no' || $this->settings['select_all_groups'] == ''))
		{
			return $publish_tabs;
		}
		else
		{
			$LANG->fetch_language_file('restrict_entries');
		
			$publish_tabs['groups'] = $LANG->line('groups');

			return $publish_tabs;
		}
	}
	// END
	
	// --------------------------------
	//  publish_form_new_tab_block
	// --------------------------------

	function publish_form_new_tab_block($weblog_id)
	{
		global $DSP, $DB, $IN, $EXT, $LANG, $PREFS;

		$EXT->end_script = FALSE;
		
		if($EXT->last_call !== false)
		{
			$r = $EXT->last_call;
		}
		else
		{
			$r = '';
		}
		
		$LANG->fetch_language_file('restrict_entries');
		
		$site_id = $PREFS->ini('site_id');
		$groups = array();
		
		$r .= '<div id="blockgroups" style="display: none; padding:0; margin:0;">';
		$r .= NL.'<div class="publishTabWrapper">';	
		$r .= NL.'<div class="publishBox">';
		$r .= NL.'<div class="publishInnerPad">';
		$r .= NL."<table class='clusterBox' border='0' cellpadding='0' cellspacing='0' style='width:99%'><tr>";	
		$r .= NL.'<td class="publishItemWrapper">'.BR;
		$r .= $DSP->heading($LANG->line('groups_description'), 5);
		$r .= $DSP->qdiv('',$LANG->line('explanation'));
		
		// Get the available member groups
		$sql = "SELECT group_id, group_title FROM exp_member_groups WHERE site_id='".$site_id."' AND can_view_online_system = 'y' AND group_id > '2' AND group_id != '4' ORDER BY group_id ASC";
		$query = $DB->query($sql);

		// Check we're not editing an existing entry
		if ($entry_id = $IN->GBL('entry_id', 'GET'))
		{	
			$sql = "SELECT member_groups FROM exp_entry_groups WHERE entry_id = '$entry_id'";
			$member_groups = $DB->query($sql);
		}
		
		if (isset($entry_id) && isset($member_groups->num_rows) && $member_groups->num_rows > 0)
		{
			$member_groups_array = unserialize($member_groups->row['member_groups']);	
		}
		else
		{
			$member_groups_array = array();
		}
		
		foreach($query->result AS $key)
		{
			$value = (!in_array($key['group_id'], $member_groups_array)) ? '' : $key['group_id'];
			$checked = ($value > 0) ? 'checked="checked"' : '';
			$r .= '<input type="checkbox" name="groups[]" ' . $checked . ' value="' . $key['group_id'] .'" /> <span>'.$key['group_title'] .'</span>'.BR;
		}
		
		$r .= NL.'</td>';
		$r .= '</tr>'.NL.'</table>';
		
		$r .= $DSP->div_c();
		$r .= $DSP->div_c();
		$r .= $DSP->div_c();   
		$r .= $DSP->div_c();
		
		return $r;
	}
	// END
	
	// --------------------------------
	//  submit_new_entry
	// --------------------------------

	function submit_new_entry($entry_id, $data)
	{
		global $EXT, $LANG, $DB, $IN;

		$EXT->end_script = FALSE;
		
		if($EXT->last_call !== false)
		{
			$data = $EXT->last_call;
		}
		
		if (isset($_POST['groups']))
		{
			$groups = serialize($_POST['groups']);
			$insert_data = array('entry_id' => $entry_id, 'member_groups' => $groups);
		}
		
		// Does the entry already exist in exp_entry_groups?
		$sql = "SELECT entry_id FROM exp_entry_groups WHERE entry_id = '".$entry_id."'";
		
		$query = $DB->query($sql);
		
		if ($query->num_rows > 0 && @is_array($insert_data))
		{
			$sql = $DB->update_string('exp_entry_groups', $insert_data, "entry_id = '".$entry_id."'");
		}
		elseif ($query->num_rows > 0 && !@is_array($insert_data))
		{
			$sql = "DELETE FROM exp_entry_groups WHERE entry_id = '$entry_id'";
		}
		elseif ($query->num_rows == 0 && @is_array($insert_data))
		{
			$sql = $DB->insert_string('exp_entry_groups', $insert_data);
		}
		
		$DB->query($sql);
	
	}
	// END
	
	// --------------------------------
	//  entries_tagdata
	// --------------------------------

	// Because we are using $this (named $rows in this function) minimum version is 1.50
	function entries_tagdata($tagdata, $row, $rows)
	{
		global $EXT, $LANG, $DB, $IN, $SESS;

		$EXT->end_script = FALSE;
		
		if($EXT->last_call !== false)
		{
			$tagdata = $EXT->last_call;
		}
		
		if(!isset($SESS->cache['restricted_member_groups']))
		{
			$entry_ids = array();
	
			foreach($rows->query->result AS $key)
			{
				$entry_ids[] = $key['entry_id'];
			}
		
			$entry_ids = "'".implode("','", $entry_ids)."'";
		
			$sql = "SELECT entry_id, member_groups FROM exp_entry_groups WHERE entry_id IN ({$entry_ids})";
		
			$SESS->cache['restricted_member_group_data'] = $DB->query($sql);
		}
		
		foreach($SESS->cache['restricted_member_group_data']->result AS $key)
		{			
			if ($key['entry_id'] == $row['entry_id'])
			{
				$groups = unserialize($key['member_groups']);
				
				foreach($groups AS $key => $value)
				{
					if($SESS->userdata['group_id'] == $value)
					{
						$tagdata = '';
						break;
					}
				}
			}
		}
	
		return $tagdata;
	
	}
	// END
	
	// --------------------------------
	//  additional_tableheader
	// --------------------------------
	function additional_tableheader($o)
	{
		global $DSP, $LANG, $EXT;
		
		$EXT->end_script = FALSE;
		
		if($EXT->last_call !== false)
		{
			$o = $EXT->last_call;
		}
		
		$LANG->fetch_language_file('restrict_entries');

		$o = $DSP->table_qcell('tableHeadingAlt', $LANG->line('restricted'));
		
		return $o;
	}
	// END
	
	// --------------------------------
	//  additional_celldata
	// --------------------------------
	function additional_celldata($row)
	{
		global $DSP, $LANG, $DB, $SESS, $EXT;
		
		$EXT->end_script = FALSE;
		
		if($EXT->last_call !== false)
		{
			$row = $EXT->last_call;
		}

		$LANG->fetch_language_file('restrict_entries');
		
		$rest = '';
		global $row_count;
		if (empty($row_count)) {
			$row_count = 0;
		}
		
		$style = ($row_count % 2) ? 'tableCellOne' : 'tableCellTwo'; $row_count++;
		$tc = $DSP->td($style);
		
		if (!isset($SESS->cache['restricted_edit_rows_data']))
		{
			$sql = "SELECT entry_id FROM exp_entry_groups";
			$query = $DB->query($sql);
			$SESS->cache['restricted_edit_rows_data'] = $query->result;
		} 
		
		foreach($SESS->cache['restricted_edit_rows_data'] AS $key)
		{
			if ($row['entry_id'] == $key['entry_id'])
			{
				$rest = TRUE;
			}
			elseif (is_array($key))
			{
				if (in_array($row['entry_id'], $key) === TRUE) $rest = TRUE;
			}
			
		}
		
		if ($rest)
		{
			$restricted = $LANG->line('yes');
			$colour = 'f00;';
		}
		else
		{
			$restricted = $LANG->line('no');
			$colour = '009933';
		}
		
		$tc .= "<div style='color: #" . $colour . "'>".$restricted.'</div>';
		
		$tc .= $DSP->td_c();
		
		return $tc;
	}
	// END
	
	// --------------------------------
	//  Activate Extension
	// --------------------------------

	function activate_extension()
	{
	    global $DB;

	    $DB->query($DB->insert_string('exp_extensions',
	                                  array(
	                                        'extension_id' => '',
	                                        'class'        => "Restrict_entries",
	                                        'method'       => "publish_form_new_tab",
	                                        'hook'         => "publish_form_new_tabs",
	                                        'settings'     => "",
	                                        'priority'     => 9,
	                                        'version'      => $this->version,
	                                        'enabled'      => "y"
	                                      )
	                                 )
	              );
		
		$DB->query($DB->insert_string('exp_extensions',
		                              array(
		                                    'extension_id' => '',
		                                    'class'        => "Restrict_entries",
		                                    'method'       => "publish_form_new_tab_block",
		                                    'hook'         => "publish_form_new_tabs_block",
		                                    'settings'     => "",
		                                    'priority'     => 9,
		                                    'version'      => $this->version,
		                                    'enabled'      => "y"
		                                  )
		                             )
		          );
		
		$DB->query($DB->insert_string('exp_extensions',
		                              array(
		                                    'extension_id' => '',
		                                    'class'        => "Restrict_entries",
		                                    'method'       => "submit_new_entry",
		                                    'hook'         => "submit_new_entry_absolute_end",
		                                    'settings'     => "",
		                                    'priority'     => 10,
		                                    'version'      => $this->version,
		                                    'enabled'      => "y"
		                                  )
		                             )
		          );
		
		$DB->query($DB->insert_string('exp_extensions',
		                              array(
		                                    'extension_id' => '',
		                                    'class'        => "Restrict_entries",
		                                    'method'       => "entries_tagdata",
		                                    'hook'         => "weblog_entries_tagdata",
		                                    'settings'     => "",
		                                    'priority'     => 10,
		                                    'version'      => $this->version,
		                                    'enabled'      => "y"
		                                  )
		                             )
		          );
		
		$DB->query($DB->insert_string('exp_extensions',
		                              array(
		                                    'extension_id' => '',
		                                    'class'        => "Restrict_entries",
		                                    'method'       => "additional_tableheader",
		                                    'hook'         => "edit_entries_additional_tableheader",
		                                    'settings'     => "",
		                                    'priority'     => 10,
		                                    'version'      => $this->version,
		                                    'enabled'      => "y"
		                                  )
		                             )
		          );
		
		$DB->query($DB->insert_string('exp_extensions',
		                              array(
		                                    'extension_id' => '',
		                                    'class'        => "Restrict_entries",
		                                    'method'       => "additional_celldata",
		                                    'hook'         => "edit_entries_additional_celldata",
		                                    'settings'     => "",
		                                    'priority'     => 10,
		                                    'version'      => $this->version,
		                                    'enabled'      => "y"
		                                  )
		                             )
		          );
		
		$sql = "CREATE TABLE IF NOT EXISTS 	exp_entry_groups (
											`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
											`entry_id` INT NOT NULL,
											`member_groups` VARCHAR(100)
											)";
											
		
		$DB->query($sql);			
	}
	// END
	
	// --------------------------------
	//  Update Extension
	// --------------------------------  

	function update_extension($current='')
	{
	    global $DB;

	    if ($current == '' OR $current == $this->version)
	    {
	        return FALSE;
	    }

	    $DB->query("UPDATE exp_extensions 
	                SET version = '".$DB->escape_str($this->version)."' 
	                WHERE class = 'Restrict_entries'");
	}
	// END
	
	// --------------------------------
	//  Disable Extension
	// --------------------------------

	function disable_extension()
	{
	    global $DB;

	    $DB->query("DELETE FROM exp_extensions WHERE class = 'Restrict_entries'");
	
		$sql = "DROP TABLE IF EXISTS exp_entry_groups";
		
		$DB->query($sql);
	}
	// END

}
// END CLASS
?>