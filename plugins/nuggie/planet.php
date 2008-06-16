<?php

/*
 * Nuggie
 * Version 0.1
 * Copyright (C) 2007 Dan Fuhry
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

/**
 * Displays a planet as requested by a PageProcessor instance.
 * @param string The page_id from PageProcessor.
 */

function nuggie_planet_uri_handler($page)
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  
  $planet_id = $page->page_id;
  
  //
  // VALIDATION
  //
  
  // Fetch ACLs
  $perms = $session->fetch_page_acl($planet_id, 'Planet');
  
  // Obtain planet info
  $q = $db->sql_query('SELECT p.planet_id, p.planet_name, p.planet_subtitle, p.planet_creator, p.planet_public, p.planet_visible, m.mapping_type, m.mapping_value ' . "\n"
                    . '  FROM ' . table_prefix . "planets AS p\n"
                    . "  LEFT JOIN " . table_prefix . "planets_mapping AS m\n"
                    . "    ON ( p.planet_id = m.planet_id )\n"
                    . "  WHERE p.planet_name = '" . $db->escape(sanitize_page_id($planet_id)) . "';");
  if ( !$q )
    $db->_die();
  
  if ( $db->numrows() < 1 )
  {
    // planet not found, fall out
    return false;
  }
  
  // fetch first row, then seek back to the first result to allow mapping fetching later
  $planet_data = $db->fetchrow();
  $db->sql_data_seek(0);
  
  // check author and publicity
  if ( $planet_data['planet_creator'] != $session->user_id && !$planet_data['planet_public'] )
  {
    return $page->err_access_denied();
  }
  
  // ACL check
  if ( !$perms->get_permissions('read') )
  {
    return $page->err_access_denied();
  }
  
  // fetch mappings to prepare to select the actual blog data
  echo 'WiP';
}
 
?>
