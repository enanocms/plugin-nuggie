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

function nuggie_user_cp($section)
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  if ( $section != 'Blog' )
    return false;
  
  if ( getConfig('nuggie_installed') != '1' )
  {
    echo '<h3>Nuggie not installed</h3>';
    echo '<p>It looks like Nuggie isn\'t installed yet. You\'ll need to <a href="' . makeUrlNS('Special', 'NuggieInstall') . '">install Nuggie</a> before you can do anything more.</p>';
    return true;
  }
  
  $subsection = $paths->getParam(1);
  $initted = true;
  
  $q = $db->sql_query('SELECT blog_id, blog_name, blog_subtitle, blog_type, allowed_users FROM ' . table_prefix . "blogs WHERE user_id = {$session->user_id};");
  if ( !$q )
    $db->_die('Nuggie User CP selecting blog info');
  
  if ( $db->numrows() < 1 )
  {
    $subsection = 'Settings';
    $initted = false;
  }
  
  list(, $blog_name, $blog_desc, $blog_type, $allowed_users) = $db->fetchrow_num($q);
  
  switch($subsection)
  {
    case false:
    case 'Home':
      echo 'module Home';
      break;
    case 'Settings':
      
      switch ( isset($_POST['do_save']) )
      {
        // We're doing this so we can break out if we need to (if form validation fails)
        case true:
          
          $errors = array();
          
          $blog_name     = trim($_POST['blog_name']);
          $blog_desc     = trim($_POST['blog_desc']);
          $blog_access   = trim($_POST['blog_access']);
          $allowed_users = $_POST['allowed_users'];
          
          if ( empty($blog_name) )
            $errors[] = 'Please enter a name for your blog.';
          
          if ( !in_array($blog_access, array('public', 'private')) )
            $errors[] = 'Hacking attempt on blog_access: must be one of public, private.';
          
          if ( count($allowed_users) > 500 )
            $errors[] = 'You\'re asking that an unreasonable number of users have access to this blog. If you really have that many readers, you may want to ask the administrator of this site to make a usergroup with read access to your blog.';
          
          if ( count($allowed_users) < 1 && $blog_access == 'private' )
            $errors[] = 'Please enter at least one username that will have access to your blog. Note that your account always has access to your blog.';
          
          if ( $blog_access == 'public' )
          {
            $allowed_users = 'NULL';
          }
          else
          {
            if ( is_array($allowed_users) && count($errors) < 1 )
            {
              $allowed_users = array_values(array_unique($allowed_users));
              foreach ( $allowed_users as $i => $_ )
              {
                if ( empty( $allowed_users[$i] ) )
                {
                  unset($allowed_users[$i]);
                }
                else
                {
                  $allowed_users[$i] = $db->escape($allowed_users[$i]);
                }
              }
              $fragment = "username='" . implode("' OR username='", $allowed_users) . "'";
              $e = $db->sql_query('SELECT COUNT(username) AS num_valid FROM ' . table_prefix . "users WHERE $fragment;");
              if ( !$e )
                $db->_die('Nuggie user CP validating usernames');
              
              $row = $db->fetchrow();
              if ( intval($row['num_valid']) != count($allowed_users) )
                $errors[] = 'One or more of the usernames you entered does not exist.';
            }
            else
            {
              $errors[] = 'Invalid datatype on allowed_users.';
            }
          }
          
          if ( count($errors) > 0 )
          {
            $initted = true;
            echo '<div class="error-box" style="margin: 0 0 10px 0">
                    <b>The following problems prevented your blog settings from being saved:</b>
                    <ul>
                      <li>
                        ' . implode("</li>\n                      <li>", $errors) . '
                      </li>
                    </ul>
                  </div>';
            break;
          }
          else
          {
            // Save changes
            
            if ( !is_string($allowed_users) )
              $allowed_users = "'" . $db->escape( serialize($allowed_users) ) . "'";
            
            $blog_name = $db->escape($blog_name);
            $blog_desc = $db->escape($blog_desc);
            
            if ( $initted )
            {
              $sql = 'UPDATE ' . table_prefix . "blogs SET blog_name = '$blog_name', blog_subtitle = '$blog_desc', blog_type = '$blog_access', allowed_users = $allowed_users WHERE user_id = {$session->user_id};";
            }
            else
            {
              $sql = 'INSERT INTO ' . table_prefix . 'blogs(blog_name, blog_subtitle, blog_type, allowed_users, user_id)' .
                     "\n  VALUES ( '$blog_name', '$blog_desc', '$blog_access', $allowed_users, {$session->user_id} );";
            }
            
            if ( $db->sql_query($sql) )
            {
              echo '<div class="info-box" style="margin: 0 0 10px 0;">' .
                      ( $initted ? 'Your changes have been saved.' : 'Your blog has been created; you can now
                        <a href="' . makeUrlNS('Special', 'Preferences/Blog/Write', false, true) . '">start writing some posts</a> and
                        then <a href="' . makeUrlNS('Blog', $session->username, false, true) . '">view your blog</a>.' )
                 . '</div>';
            }
            else
            {
              $db->_die('Nuggie user CP saving settings');
            }
            
            // Re-select the blog data
            $db->free_result($q);
            
            $q = $db->sql_query('SELECT blog_id, blog_name, blog_subtitle, blog_type, allowed_users FROM ' . table_prefix . "blogs WHERE user_id = {$session->user_id};");
            if ( !$q )
              $db->_die('Nuggie User CP selecting blog info');
            
            list(, $blog_name, $blog_desc, $blog_type, $allowed_users) = $db->fetchrow_num($q);
          }
          
          $initted = true;
      }
      
      if ( !$initted )
      {
        echo '<div class="error-box" style="margin: 0 0 10px 0;">
                <b>It looks like your blog isn\'t set up yet.</b><br />
                You\'ll need to set up your blog by entering some basic information here before you can write any posts.
              </div>';
        $blog_name = htmlspecialchars($session->username) . "'s blog";
        $blog_desc = '';
      }
      else
      {
        $blog_name = htmlspecialchars(strtr($blog_name, array('"' => '&quot;')));
        $blog_desc = htmlspecialchars(strtr($blog_desc, array('"' => '&quot;')));
      }
      
      if ( !isset($blog_type) )
        $blog_type = 'public';
      
      if ( !isset($allowed_users) )
        $allowed_users = serialize(array());
      
      $form_action = makeUrlNS('Special', 'Preferences/Blog/Settings', false, true);
      echo "<form action=\"$form_action\" method=\"post\" enctype=\"multipart/form-data\">";
      
      ?>
      <div class="tblholder">
        <table border="0" cellspacing="1" cellpadding="4">
          <tr>
            <th colspan="2">
              <?php echo ( $initted ) ? 'Manage blog settings' : 'Create blog'; ?>
            </th>
          </tr>
          <tr>
            <td class="row2">
              Blog name:
            </td>
            <td class="row1">
              <input type="text" name="blog_name" size="60" value="<?php echo $blog_name; ?>" tabindex="1" />
            </td>
          </tr>
          <tr>
            <td class="row2">
              Blog description:<br />
              <small>You're best off keeping this short and sweet.</small>
            </td>
            <td class="row1">
              <input type="text" name="blog_desc" size="60" value="<?php echo $blog_desc; ?>" tabindex="2" />
            </td>
          </tr>
          <tr>
            <td class="row2">
              Blog access:
            </td>
            <td class="row1">
              <label><input onclick="$('nuggie_allowed_users').object.style.display='none';"  tabindex="3" type="radio" name="blog_access" value="public"<?php echo ( $blog_type == 'public' ) ? ' checked="checked"' : ''; ?> /> Let everyone read my blog</label><br />
              <label><input onclick="$('nuggie_allowed_users').object.style.display='block';" tabindex="4" type="radio" name="blog_access" value="private"<?php echo ( $blog_type == 'private' ) ? ' checked="checked"' : ''; ?> /> Only allow the users I list below</label><br />
              <small style="margin-left: 33px;">Administrators can always read all blogs, including private ones.</small>
              <div id="nuggie_allowed_users"<?php echo ( $blog_type == 'public' ) ? ' style="display: none;"' : ''; ?>>
                <?php
                if ( $initted )
                {
                  $allowed_users = unserialize($allowed_users);
                  foreach ( $allowed_users as $user )
                  {
                    echo '<input type="text" name="allowed_users[]" tabindex="5" value="' . $user . '" size="25" style="margin-bottom: 5px;" onkeyup="new AutofillUsername(this);" /><br />';
                  }
                  echo '<input type="text" name="allowed_users[]" tabindex="5" value="" size="25" style="margin-bottom: 5px;" onkeyup="new AutofillUsername(this);" /><br />';
                }
                else
                {
                  ?>
                  <input type="text" name="allowed_users[]" tabindex="5" value="" size="25" style="margin-bottom: 5px;" onkeyup="new AutofillUsername(this);" /><br />
                  <input type="text" name="allowed_users[]" tabindex="5" value="" size="25" style="margin-bottom: 5px;" onkeyup="new AutofillUsername(this);" /><br />
                  <input type="text" name="allowed_users[]" tabindex="5" value="" size="25" style="margin-bottom: 5px;" onkeyup="new AutofillUsername(this);" /><br />
                  <input type="text" name="allowed_users[]" tabindex="5" value="" size="25" style="margin-bottom: 5px;" onkeyup="new AutofillUsername(this);" /><br />
                  <input type="text" name="allowed_users[]" tabindex="5" value="" size="25" style="margin-bottom: 5px;" onkeyup="new AutofillUsername(this);" /><br />
                  <?php
                }
                ?>
                <input type="button" tabindex="6" onclick="var x = document.createElement('input'); x.tabindex = '5'; x.onkeyup = function() { new AutofillUsername(this); }; x.size='25'; x.style.marginBottom='5px'; x.type='text'; x.name='allowed_users[]'; $('nuggie_allowed_users').object.insertBefore(x, this); $('nuggie_allowed_users').object.insertBefore(document.createElement('br'), this); x.focus();" value="+ Add another" />
              </div>
            </td>
          </tr>
          <tr>
            <th class="subhead" colspan="2">
              <input tabindex="7" type="submit" name="do_save" value="<?php echo ( $initted ) ? 'Save changes' : 'Create my blog &raquo;' ?>" />
            </th>
          </tr>
        </table>
      </div>
      <?php
      
      echo '</form>';
      
      break;
    case 'Posts':
      if ( $paths->getParam(2) == 'AjaxHandler' )
      {
        ob_end_clean();
        
        if ( !isset($_POST['act']) )
          die();
        
        switch($_POST['act'])
        {
          case 'delete':
            header('Content-type: application/json');
            
            if ( !isset($_POST['post_id']) )
              die();
            
            if ( strval(intval($_POST['post_id'])) !== $_POST['post_id'] )
              die();
            
            // make sure it's ok
            $post_id =& $_POST['post_id'];
            $post_id = intval($post_id);
            $q = $db->sql_query('SELECT post_author FROM ' . table_prefix . 'blog_posts WHERE post_id = ' . $post_id . ';');
            if ( !$q )
              $db->die_json();
            if ( $db->numrows() < 1 )
              die('That post doesn\'t exist.');
            
            list($author) = $db->fetchrow_num();
            $author = intval($author);
            if ( $author !== $session->user_id && !$session->get_permissions('nuggie_edit_other') )
              die('No permissions');
            
            // try to delete the post...
            $q = $db->sql_query('DELETE FROM ' . table_prefix . 'blog_posts WHERE post_id = ' . $post_id . ';');
            if ( !$q )
              $db->die_json();
            
            echo '1';
            
            break;
          case 'publish':
            if ( !isset($_POST['post_id']) )
              die();
            
            if ( strval(intval($_POST['post_id'])) !== $_POST['post_id'] )
              die();
            
            if ( !in_array(@$_POST['state'], array('0', '1')) )
              die();
            
            $state = intval($_POST['state']);
            $post_id =& $_POST['post_id'];
            $post_id = intval($post_id);
            
            // validate permissions
            $q = $db->sql_query('SELECT post_author FROM ' . table_prefix . 'blog_posts WHERE post_id = ' . $post_id . ';');
            if ( !$q )
              $db->die_json();
            if ( $db->numrows() < 1 )
              die('That post doesn\'t exist.');
            
            list($author) = $db->fetchrow_num();
            $author = intval($author);
            if ( $author !== $session->user_id && !$session->get_permissions('nuggie_edit_other') )
              die('No permissions');
            
            // try to delete the post...
            $q = $db->sql_query('UPDATE ' . table_prefix . 'blog_posts SET post_published = ' . $state . ' WHERE post_id = ' . $post_id . ';');
            if ( !$q )
              $db->die_json();
            
            echo "good;$state";
             
            break;
        }
        
        $db->close();
        exit();
      }
      
      if ( isset($_POST['action']) )
      {
        $action =& $_POST['action'];
        // Parse parameters
        if ( strpos($action, ';') )
        {
          // Parameter section
          $parms = substr($action, strpos($action, ';') + 1);
          
          // Action name section
          $action = substr($action, 0, strpos($action, ';'));
          
          // Match all parameters
          preg_match_all('/([a-z0-9_]+)=(.+?)(;|$)/', $parms, $matches);
          $parms = array();
          
          // For each full parameter, assign $parms an associative value
          foreach ( $matches[0] as $i => $_ )
          {
            $parm = $matches[2][$i];
            
            // Is this parameter in the form of an integer?
            // (designed to ease validation later)
            if ( preg_match('/^[0-9]+$/', $parm) )
              // Yes, run intval(), this enabling is_int()-ish checks
              $parm = intval($parm);
            
            $parms[$matches[1][$i]] = $parm;
          }
        }
        switch ( $action )
        {
          case 'edit':
            if ( !is_int(@$parms['id']) )
              break;
            // This is hackish. Really, REALLY hackish.
            $_SERVER['PATH_INFO'] = '.../' . $paths->nslist['Special'] . 'Preferences/Blog/Write/' . $parms['id'];
            $_GET['title'] = $paths->nslist['Special'] . 'Preferences/Blog/Write/' . $parms['id'];
            nuggie_user_cp('Blog');
            return true;
            break;
          case 'delete':
            
            if ( !is_int(@$parms['id']) )
              break;
            
            // make sure it's ok
            $post_id = $parms['id'];
            $post_id = intval($post_id);
            $q = $db->sql_query('SELECT post_author FROM ' . table_prefix . 'blog_posts WHERE post_id = ' . $post_id . ';');
            if ( !$q )
              $db->_die();
            if ( $db->numrows() < 1 )
              die('That post doesn\'t exist.');
            
            list($author) = $db->fetchrow_num();
            $author = intval($author);
            if ( $author !== $session->user_id && !$session->get_permissions('nuggie_edit_other') )
              die('No permissions');
            
            // try to delete the post...
            $q = $db->sql_query('DELETE FROM ' . table_prefix . 'blog_posts WHERE post_id = ' . $post_id . ';');
            if ( !$q )
              $db->_die();
            
            echo '<div class="info-box" style="margin: 0 0 0 0;">Post deleted.</div>';
            
            break;
        }
      }
      
      // include some javascript for management
      echo '<script type="text/javascript" src="' . scriptPath . '/plugins/nuggie/client/usercp.js"></script>';
      
      // the form
      // +------------------+------------+------+-----+---------+----------------+
      // | Field            | Type       | Null | Key | Default | Extra          |
      // +------------------+------------+------+-----+---------+----------------+
      // | post_id          | int(15)    | NO   | PRI | NULL    | auto_increment | 
      // | post_title       | text       | NO   |     |         |                | 
      // | post_title_clean | text       | NO   |     |         |                | 
      // | post_author      | int(12)    | NO   |     | 1       |                | 
      // | post_text        | longtext   | NO   |     |         |                | 
      // | post_timestamp   | int(32)    | NO   |     | 0       |                | 
      // | post_published   | tinyint(1) | NO   |     | 0       |                | 
      // +------------------+------------+------+-----+---------+----------------+
      
      echo '<form action="' . makeUrlNS('Special', 'Preferences/Blog/Posts') . '" method="post">';
      
      $q = $db->sql_query('SELECT post_id, post_title, post_title_clean, post_timestamp, post_published FROM ' . table_prefix . 'blog_posts WHERE post_author = ' . $session->user_id . ' ORDER BY post_timestamp DESC;');
      if ( !$q )
        $db->_die();
      
      echo '<div class="tblholder">
              <table border="0" cellspacing="1" cellpadding="4" id="nuggie_postlist">';
              
      echo '<tr>
              <th style="width: 1px;">#</th>
              <th style="width: 80%;">Post title</th>
              <th>Published</th>
              <th>Time</th>
              <th colspan="2"></th>
            </tr>';
      
      if ( $row = $db->fetchrow() )
      {
        do
        {
          echo '<tr>';
          
          $uri = makeUrlNS('Blog', $session->username . date('/Y/n/j/', $row['post_timestamp']) . $row['post_title_clean'], false, true);
          
          echo '<td class="row2" style="text-align: center;">' . $row['post_id'] . '</td>';
          echo '<td class="row1">' . "<a href=\"$uri\">" . htmlspecialchars($row['post_title']) . '</a></td>';
          $cls = ( $row['post_published'] == 1 ) ? 'row3_green' : 'row3_red';
          echo '<td class="' . $cls . ' nuggie_publishbtn" onclick="ajaxNuggieTogglePublished(' . $row['post_id'] . ', this);" nuggie:published="' . $row['post_published'] . '" style="text-align: center;">' . ( ( $row['post_published'] == 1 ) ? '<b>Yes</b>' : 'No' ) . '</td>';
          echo '<td class="row3" style="white-space: nowrap;">' . ( function_exists('enano_date') ? enano_date('Y-m-d', $row['post_timestamp']) : date('Y-m-d h:i', $row['post_timestamp']) ) . '</td>';
          echo '<td class="row1" style="white-space: nowrap;"><button class="nuggie_edit" name="action" value="edit;id=' . $row['post_id'] . '">Edit</button> <button class="nuggie_delete" name="action" onclick="return ajaxNuggieDeletePost(' . $row['post_id'] . ', this.parentNode.parentNode);" value="delete;id=' . $row['post_id'] . '">Delete</button></td>';
          
          echo '</tr>';
        } while ( $row = $db->fetchrow() );
      }
      else
      {
        echo '<tr><td class="row3" colspan="6" style="text-align: center;">No posts.</td></tr>';
      }
      
      echo '  </table>
            </div>';
      
      echo '</form>';
      
      break;
    case 'Write':
      
      $post_text = '';
      $post_title = 'Post title';
      
      $post_id = $paths->getParam(2);
      if ( isset($_POST['post_id']) )
      {
        $post_id = $_POST['post_id'];
      }
      if ( $post_id )
      {
        /*
         * FIXME: Validate blog public/private status before sending text
         * FIXME: Avoid ambiguous post_title_cleans through appending numbers when needed
         */
        
        $post_id = intval($post_id);
        $q = $db->sql_query('SELECT p.post_id, p.post_title, p.post_title_clean, p.post_author, p.post_text, p.post_timestamp, u.username ' 
                            . 'FROM ' . table_prefix . 'blog_posts AS p'
                            . '  LEFT JOIN ' . table_prefix . 'users AS u'
                            . '    ON ( p.post_author = u.user_id )'
                            . '  WHERE post_id = ' . $post_id . ';');
        
        if ( !$q )
          $db->_die('Nuggie user CP obtaining post info');
        
        if ( $db->numrows() > 0 )
        {
          $row = $db->fetchrow();
          if ( $session->user_id != $row['post_author'] )
          {
            // We have a possible security issue on our hands - the user is trying
            // to edit someone else's post. Verify read and write permissions.
            $post_page_id = "{$row['post_timestamp']}_{$row['post_id']}";
            $perms = $session->fetch_page_acl($post_page_id, 'Blog');
            if ( !$perms->get_permissions('read') || !$perms->get_permissions('nuggie_edit_other') )
            {
              echo '<h3>Post editing error</h3>';
              echo '<p>You do not have permission to edit this blog post.</p>';
              
              unset($row);
              unset($row);
              
              $db->free_result();
              // Break out of this entire user CP module
              return true;
            }
          }
          else
          {
            $post_page_id = "{$row['post_timestamp']}_{$row['post_id']}";
            $perms = $session->fetch_page_acl($post_page_id, 'Blog');
            if ( !$perms->get_permissions('nuggie_edit_own') || !$perms->get_permissions('read') )
            {
              echo '<h3>Post editing error</h3>';
              echo '<p>You do not have permission to edit this blog post.</p>';
              
              unset($row);
              unset($row);
              
              $db->free_result();
              // Break out of this entire user CP module
              return true;
            }
          }
          // We have permission - load post
          $post_title = $row['post_title'];
          $post_text = $row['post_text'];
        }
      }
      
      if ( isset($_POST['submit']) )
      {
        switch($_POST['submit'])
        {
          case 'save_publish':
            $publish = '1';
          case 'save_draft':
            if ( !isset($publish) )
              $publish = '0';
            
            $save_post_text = $_POST['post_text'];
            $save_post_title = $db->escape($_POST['post_title']);
            $save_post_title_clean = $db->escape(nuggie_sanitize_title($_POST['post_title']));
            
            $save_post_text = RenderMan::preprocess_text($save_post_text, true, true);
            
            if ( $post_id )
            {
              $sql = 'UPDATE ' . table_prefix . "blog_posts SET post_title = '$save_post_title', post_title_clean = '$save_post_title_clean', post_text = '$save_post_text', post_published = $publish WHERE post_id = $post_id;";
            }
            else
            {
              $time = time();
              $sql = 'INSERT INTO ' . table_prefix . 'blog_posts ( post_title, post_title_clean, post_text, post_author, post_timestamp, post_published ) '
                      . "VALUES ( '$save_post_title', '$save_post_title_clean', '$save_post_text', {$session->user_id}, $time, $publish );";
            }
            
            if ( $db->sql_query($sql) )
            {
              echo '<div class="info-box" style="margin: 0 0 10px 0;">
                      ' . ( $publish == '1' ? 'Your post has been published.' : 'Your post has been saved.' ) . '
                    </div>';
            }
            else
            {
              $db->_die('Nuggie user CP running post-save query');
            }
            
            if ( !$post_id )
            {
              $post_id = $db->insert_id();
            }
            
            $post_title = $_POST['post_title'];
            $post_text = $_POST['post_text'];
            break;
          case 'preview':
            $preview_text = $_POST['post_text'];
            $preview_text = RenderMan::preprocess_text($preview_text, true, false);
            $preview_text = RenderMan::render($preview_text);
            
            /*
             * FIXME: Use the real post renderer (when it's ready)
             */
            
            echo '<div style="border: 1px solid #406080; background-color: #F0F0F0; margin: 0 0 10px 0; padding: 10px;
                              overflow: auto; max-height: 500px; clip: rect(0px, auto, auto, 0px);">';
            echo '<h2>Post preview</h2>';
            echo '<p style="color: red;">FIXME: This does not use the real post-display API, which is not yet implemented. Eventually this should look just like a real post.</p>';
            echo '<h3>' . htmlspecialchars($_POST['post_title']) . '</h3>';
            echo $preview_text;
            echo '</div>';
           
            $post_title = $_POST['post_title'];
            $post_text = $_POST['post_text'];
            break;
        }
      }
      
      $q = $db->sql_query('SELECT post_id, post_title FROM ' . table_prefix . "blog_posts WHERE post_published = 0 AND post_author = {$session->user_id};");
      if ( !$q )
        $db->_die('Nuggie user CP selecting draft posts');
      if ( $db->numrows() > 0 )
      {
        echo '<div class="mdg-infobox" style="margin: 0 0 10px 0;"><b>Your drafts:</b> ';
        $posts = array();
        while ( $row = $db->fetchrow() )
        {
          $posts[] = '<a href="' . makeUrlNS('Special', "Preferences/Blog/Write/{$row['post_id']}") . '">' . htmlspecialchars($row['post_title']) . '</a>';
        }
        echo implode(', ', $posts);
        echo '</div>';
      }
      
      echo '<form action="' . makeUrlNS('Special', 'Preferences/Blog/Write', false, true) . '" method="post">';
      
      $post_text = htmlspecialchars($post_text);
      $post_title = strtr(htmlspecialchars($post_title), array('"' => '&quot;'));
      
      echo '<input type="text" name="post_title" value="' . $post_title . '" style="font-size: 16pt; margin-bottom: 10px; width: 100%;' . ( $post_title == 'Post title' ? ' color: #808080;' : '' ) . '" onfocus="if ( this.value == \'Post title\' ) { this.value = \'\'; this.style.color = null; }" onblur="if ( this.value == \'\' ) { this.value = \'Post title\'; this.style.color = \'#808080\'; } else { this.style.color = null; }" />';
      echo $template->tinymce_textarea('post_text', $post_text);
      
      // Buttons!
      echo '<div style="margin-top: 10px;">';
      echo '<button name="submit" value="save_draft">Save draft</button>&nbsp;&nbsp;';
      echo '<button name="submit" value="preview">Show preview</button>&nbsp;&nbsp;';
      echo '<button name="submit" value="save_publish">Publish to blog</button>&nbsp;&nbsp;';
      echo '</div>';
      
      if ( $post_id )
      {
        echo '<input type="hidden" name="post_id" value="' . $post_id . '" />';
      }
      
      echo '</form>';
      
      break;
    case 'Planets':
      echo 'module Planets';
      break;
    default:
      return false;
  }
  return true;
}

$plugins->attachHook("userprefs_jbox", "
    userprefs_menu_add('My blog', 'Manage blog settings', makeUrlNS('Special', 'Preferences/Blog/Settings'));
    userprefs_menu_add('My blog', 'Manage posts', makeUrlNS('Special', 'Preferences/Blog/Posts'));
    userprefs_menu_add('My blog', 'Write new post', makeUrlNS('Special', 'Preferences/Blog/Write'));
    userprefs_menu_add('My blog', 'Manage my planets', makeUrlNS('Special', 'Preferences/Blog/Planets'));
    \$userprefs_menu_links['My blog'] = makeUrlNS('Blog', \$session->username);
  ");
$plugins->attachHook("userprefs_body", "return nuggie_user_cp(\$section);");
