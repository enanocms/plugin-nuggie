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

function page_Special_NuggieInstall()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  
  if ( getConfig('nuggie_installed') == '1' )
  {
    die_friendly('Nuggie already installed', '<p>Nuggie is already installed - you can\'t reinstall it from here. To upgrade a Nuggie installation, use the upgrade tool.</p>');
  }
  
  if ( $session->auth_level < USER_LEVEL_ADMIN )
  {
    redirect(makeUrlNS('Special', 'Login/' . $paths->page, 'level=' . USER_LEVEL_ADMIN), 'Login required', 'You need to be an administrator with elevated auth to install Nuggie. You will now be transferred to the login page.');
    exit;
  }
  
  $mode = ( $x = $paths->getParam(0) ) ? $x : 'welcome';
  switch ( $mode )
  {
    default:
      die_friendly('Invalid action', '<p>Invalid installer action</p>');
      break;
    case 'welcome':
      $template->header();
      $q = $db->sql_query('SELECT group_id, group_name FROM ' . table_prefix . 'groups ORDER BY group_name ASC;');
      if ( !$q )
        $db->_die('plugins/nuggie/install.php selecting group information');
      $groups = array();
      while ( (list($group_id, $group_name) = $db->fetchrow_num()) )
      {
        $groups[$group_id] = $group_name;
      }
      ?>
      <script type="text/javascript">
        function nuggie_click(value)
        {
          var theform = document.forms['nuggieinstall'];
          if ( !theform )
            return false;
          switch(value)
          {
            case 'everyone':
            case 'noone':
              $('nuggieinstall_use_group').object.style.display = 'none';
              $('nuggieinstall_create_group').object.style.display = 'none';
              break;
            case 'use_group':
              $('nuggieinstall_use_group').object.style.display = 'block';
              $('nuggieinstall_create_group').object.style.display = 'none';
              break;
            case 'create_group':
              $('nuggieinstall_use_group').object.style.display = 'none';
              $('nuggieinstall_create_group').object.style.display = 'block';
              break;
          }
        }
      </script>
      <?php
      echo '<form action="' . makeUrlNS('Special', 'NuggieInstall/install_base') . '" method="post" name="nuggieinstall">';
      echo '<h3>Welcome to Nuggie - the only blogging engine you\'ll ever need.</h3>';
      echo '<p>Before you can start blogging, we\'ll need to perform a couple of short steps to set up Nuggie on your server. Since
               you\'re running Nuggie on top of Enano, you won\'t need to re-enter database information &ndash; we just need to create a
               few extra tables in your database.</p>';
      echo '<p>To get started, who would you like to give posting abilities to?</p>';
      echo '<p><label><input onclick="nuggie_click(this.value);" type="radio" name="blog_perms" value="everyone" checked="checked" /> Let everybody with an account create their own blog</label></p>';
      echo '<p><label><input onclick="nuggie_click(this.value);" type="radio" name="blog_perms" value="use_group" /> Only people in the following group can have blogs:</label></p>';
      echo '<p id="nuggieinstall_use_group" style="display: none; margin-left: 46px;"><select name="use_group_id">';
      foreach ( $groups as $group_id => $group_name )
      {
        echo "<option value=\"$group_id\">" . htmlspecialchars($group_name) . "</option>";
      }
      echo '</select></p>';
      echo '<p><label><input onclick="nuggie_click(this.value);" type="radio" name="blog_perms" value="create_group" /> Create a new group and only allow people in that group to have a blog:</label></p>';
      echo '<p id="nuggieinstall_create_group" style="display: none; margin-left: 46px;">Group name: <input type="text" name="create_group_name" size="30" /><br />
              <small>You\'ll be added to this group automatically.</small>
            </p>';
      echo '<p><label><input onclick="nuggie_click(this.value);" type="radio" name="blog_perms" value="noone" /> Don\'t allow anyone to have a blog yet - I\'ll set up permissions myself. <small>(advanced)</small></label></p>';
      echo '<p style="text-align: center;"><button><big>Next &raquo;</big></button></p>';
      echo '</form>';
      $template->footer();
      break;
    case 'install_base':
      if ( !file_exists( ENANO_ROOT . '/plugins/nuggie/schema.sql' ) )
      {
        die_friendly('Can\'t load schema file', '<p>Can\'t find the schema.sql file that should be in /plugins/nuggie. Check your Nuggie setup.</p>');
      }
      $schema = @file_get_contents( ENANO_ROOT . '/plugins/nuggie/schema.sql' );
      if ( empty($schema) )
      {
        die_friendly('Can\'t load schema file', '<p>Can\'t read the schema.sql file that should be in /plugins/nuggie. Check your file permissions.</p>');
      }
      
      if ( !isset($_POST['blog_perms']) )
        die('Missing essential form field');
      
      if ( !in_array($_POST['blog_perms'], array('everyone', 'use_group', 'create_group', 'noone')) )
        die('You tried to hack the form');
      
      if ( $_POST['blog_perms'] == 'use_group' && strval(intval($_POST['use_group_id'])) !== $_POST['use_group_id'] )
        die('You tried to hack the form');
      
      if ( $_POST['blog_perms'] == 'create_group' && !isset($_POST['create_group_name']) )
        die('You tried to hack the form');
      
      //
      // PARSE SCHEMA
      //
      
      // Step 1: remove comments and blank lines
      $schema = str_replace("\r", '', $schema);
      $schema = explode("\n", $schema);
      foreach ( $schema as $i => $_ )
      {
        $line =& $schema[$i];
        $line = preg_replace('/--(.*)$/', '', $line);
        $line = trim($line);
        if ( empty($line) )
          unset($schema[$i]);
      }
      $schema = array_values($schema);
      
      // Step 2: Split into separate queries
      
      $queries = array('');
      $query =& $queries[0];
      foreach ( $schema as $line )
      {
        if ( preg_match('/;$/', $line) )
        {
          $query .= "\n  $line";
          $queries[] = '';
          unset($query);
          $query =& $queries[count($queries) - 1];
        }
        else
        {
          $query .= "\n  $line";
        }
      }
      unset($query);
      foreach ( $queries as $i => $query )
      {
        $query = trim($query);
        if ( empty($query) )
          unset($queries[$i]);
        else
          $queries[$i] = $query;
      }
      $schema = array_values($queries);
      unset($queries, $query, $i);
      
      // Step 3: Assign variables
      
      foreach ( $schema as $i => $_ )
      {
        $sql =& $schema[$i];
        $sql = str_replace('{{TABLE_PREFIX}}', table_prefix, $sql);
        unset($sql);
      }
      unset($sql);

      // Step 4: Check queries
      foreach ( $schema as $sql )
      {
        if ( !$db->check_query($sql) )
        {
          die_friendly('Error during installation', '<p>DBAL rejected query citing syntax errors. This is probably a bug.</p>');
        }
      }
      
      // echo '<pre>' . htmlspecialchars(print_r($schema, true)) . '</pre>';
      
      // Step 5: Install
      foreach ( $schema as $sql )
      {
        if ( !$db->sql_query($sql) )
        {
          $db->_die('Nuggie during mainstream installation');
        }
      }
      
      $template->header(true);
      echo '<h3>Base install complete</h3>';
      echo '<p>The base install has completed. Please click Next to continue with the setup of ACL rules.</p>';
      echo '<form action="' . makeUrlNS('Special', 'NuggieInstall/install_acl') . '" method="post">';
      $group_name = htmlspecialchars($_POST['create_group_name']);
      $group_name = str_replace('"', '&quot;', $group_name);
      // This is SAFE! It's verified against a whitelist
      echo '<input type="hidden" name="blog_perms" value="' . $_POST['blog_perms'] . '" />';
      echo "<input type=\"hidden\" name=\"use_group_id\" value=\"{$_POST['use_group_id']}\" />";
      echo "<input type=\"hidden\" name=\"create_group_name\" value=\"{$group_name}\" />";
      echo '<p style="text-align: center;"><button><big>Next &raquo;</big></button></p>';
      echo '</form>';
      $template->footer(true);
      
      break;
    case 'install_acl':
      
      if ( !isset($_POST['blog_perms']) )
        die('Missing essential form field');
      
      if ( !in_array($_POST['blog_perms'], array('everyone', 'use_group', 'create_group', 'noone')) )
        die('You tried to hack the form');
      
      if ( $_POST['blog_perms'] == 'use_group' && strval(intval($_POST['use_group_id'])) !== $_POST['use_group_id'] )
        die('You tried to hack the form');
      
      if ( $_POST['blog_perms'] == 'create_group' && !isset($_POST['create_group_name']) )
        die('You tried to hack the form');
      
      switch ( $_POST['blog_perms'] )
      {
        case 'everyone':
          $q = $db->sql_query('SELECT rules,rule_id FROM ' . table_prefix . 'acl WHERE target_type = ' . ACL_TYPE_GROUP . ' AND target_id = 1 AND page_id IS NULL AND namespace IS NULL;');
          if ( !$q )
            $db->_die('Nuggie installer selecting existing ACL rules');
          if ( $db->numrows() < 1 )
          {
            // The rule doesn't exist, create it
            $rule = $session->perm_to_string(array(
                'nuggie_post' => AUTH_ALLOW,
                'nuggie_edit_own' => AUTH_ALLOW,
                'nuggie_edit_other' => AUTH_DISALLOW,
                'nuggie_create_planet' => AUTH_ALLOW,
                'nuggie_publicize_planet' => AUTH_WIKIMODE,
                'nuggie_protect_planet' => AUTH_DISALLOW,
                'nuggie_edit_planet_own' => AUTH_ALLOW,
                'nuggie_edit_planet_other' => AUTH_DISALLOW,
                'nuggie_even_when_protected' => AUTH_DISALLOW,
                'nuggie_see_non_public' => AUTH_DISALLOW
              ));
            $q = $db->sql_query('INSERT INTO ' . table_prefix . 'acl(rules, target_type, target_id, page_id, namespace)' .
                              "\n  VALUES( '$rule', " . ACL_TYPE_GROUP . ", 1, NULL, NULL );");
            if ( !$q )
              $db->_die('Nuggie installer setting up permissions');
          }
          else
          {
            list($rule, $rule_id) = $db->fetchrow_num();
            $rule = $session->string_to_perm($rule);
            $rule = $session->acl_merge_complete($rule, array(
                'nuggie_post' => AUTH_ALLOW,
                'nuggie_edit_own' => AUTH_ALLOW,
                'nuggie_edit_other' => AUTH_DISALLOW,
                'nuggie_create_planet' => AUTH_ALLOW,
                'nuggie_publicize_planet' => AUTH_WIKIMODE,
                'nuggie_protect_planet' => AUTH_DISALLOW,
                'nuggie_edit_planet_own' => AUTH_ALLOW,
                'nuggie_edit_planet_other' => AUTH_DISALLOW,
                'nuggie_even_when_protected' => AUTH_DISALLOW,
                'nuggie_see_non_public' => AUTH_DISALLOW
              ));
            $rule = $session->perm_to_string($rule);
            $q = $db->sql_query('UPDATE ' . table_prefix . 'acl' .
                              "\n  SET rules='$rule'\n"
                              . "     WHERE rule_id = $rule_id;");
            if ( !$q )
              $db->_die('Nuggie installer setting up permissions');
          }
          break;
        case "create_group":
          $group_name = $db->escape($_POST['create_group_name']);
          
          $q = $db->sql_query('INSERT INTO ' . table_prefix . "groups ( group_name ) VALUES ( '$group_name' );");
          if ( !$q )
            $db->_die('Nuggie installer creating group');
          
          $group_id = $db->insert_id();
          $q = $db->sql_query('INSERT INTO ' . table_prefix . "group_members( group_id, user_id ) VALUES ( $group_id, {$session->user_id} );");
          if ( !$q )
            $db->_die('Nuggie installer adding user to new group');
          
        case "use_group":
          if ( !isset($group_id) )
          {
            $group_id = intval($_POST['use_group_id']);
            $q = $db->sql_query('SELECT group_name, group_id FROM ' . table_prefix . "groups WHERE group_id = $group_id;");
            if ( !$q )
              $db->_die('Nuggie installer determining group information');
            if ( $db->numrows() < 1 )
              die('Hacking attempt');
            list($group_name, $group_id) = $db->fetchrow_num();
          }
          
          $q = $db->sql_query('SELECT rules,rule_id FROM ' . table_prefix . 'acl WHERE target_type = ' . ACL_TYPE_GROUP . " AND target_id = $group_id AND page_id IS NULL AND namespace IS NULL;");
          if ( !$q )
            $db->_die('Nuggie installer selecting existing ACL rules');
          if ( $db->numrows() < 1 )
          {
            // The rule doesn't exist, create it
            $rule = $session->perm_to_string(array(
                'nuggie_post' => AUTH_ALLOW,
                'nuggie_edit_own' => AUTH_ALLOW,
                'nuggie_edit_other' => AUTH_DISALLOW,
                'nuggie_create_planet' => AUTH_ALLOW,
                'nuggie_publicize_planet' => AUTH_WIKIMODE,
                'nuggie_protect_planet' => AUTH_DISALLOW,
                'nuggie_edit_planet_own' => AUTH_ALLOW,
                'nuggie_edit_planet_other' => AUTH_DISALLOW,
                'nuggie_even_when_protected' => AUTH_DISALLOW,
                'nuggie_see_non_public' => AUTH_DISALLOW
              ));
            $q = $db->sql_query('INSERT INTO ' . table_prefix . 'acl(rules, target_type, target_id, page_id, namespace)' .
                              "\n  VALUES( '$rule', " . ACL_TYPE_GROUP . ", $group_id, NULL, NULL );");
            if ( !$q )
              $db->_die('Nuggie installer setting up permissions');
          }
          else
          {
            list($rule, $rule_id) = $db->fetchrow_num();
            $rule = $session->string_to_perm($rule);
            $rule = $session->acl_merge_complete($rule, array(
                'nuggie_post' => AUTH_ALLOW,
                'nuggie_edit_own' => AUTH_ALLOW,
                'nuggie_edit_other' => AUTH_DISALLOW,
                'nuggie_create_planet' => AUTH_ALLOW,
                'nuggie_publicize_planet' => AUTH_WIKIMODE,
                'nuggie_protect_planet' => AUTH_DISALLOW,
                'nuggie_edit_planet_own' => AUTH_ALLOW,
                'nuggie_edit_planet_other' => AUTH_DISALLOW,
                'nuggie_even_when_protected' => AUTH_DISALLOW,
                'nuggie_see_non_public' => AUTH_DISALLOW
              ));
            $rule = $session->perm_to_string($rule);
            $q = $db->sql_query('UPDATE ' . table_prefix . 'acl' .
                              "\n  SET rules='$rule'\n"
                              . "     WHERE rule_id = $rule_id;");
            if ( !$q )
              $db->_die('Nuggie installer setting up permissions');
          }
          
          break;
        case "noone":
          // Don't touch permissions, let the webmaster handle it
          break;
        default:
          die('PHP = douche bag');
          break;
      }
      
      // Mark it as installed to prevent installer module from loading
      setConfig('nuggie_installed', '1');
      
      $template->header(true);
      echo '<h3>Nuggie has been installed.</h3>';
      echo '<p>You\'ve successfully installed Nuggie. Congratulations!</p>';
      echo '<form action="' . makeUrlNS('Special', 'Preferences/Blog') . '" method="post">';
      echo '<p style="text-align: center;"><big><button>Start blogging &raquo;</button></big>';
      echo '</form>';
      $template->footer(true);
      
      break;
  }
}

