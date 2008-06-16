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
 * Class for displaying a Nuggie blog post.
 * @package Enano
 * @subpackage Nuggie
 */

class NuggiePostbit
{
  /**
   * The unique row ID of the post. This can be false if
   * the post is being displayed as part of a preview or
   * otherwise doesn't actually exist in the database.
   * @var int
   */
  
  var $post_id = false;
  
  /**
   * The title of the post.
   * @var string
   */
  
  var $post_title;
  
  /**
   * The cleaned title of the post. This is calculated
   * internally and need not be set.
   * @var string
   */
  
  var $post_title_clean;
  
  /**
   * Who wrote this post (user ID).
   * @var int
   */
  
  var $post_author = 1;
  
  /**
   * When the post was posted. UNIX timestamp.
   * @var int
   */
  
  var $post_timestamp = 1;
  
  /**
   * The actual content of the post.
   * @var string
   */
  
  var $post_text = '';
  
  /**
   * Whether the user can edit the post or not.
   * @var bool
   */
  
  var $auth_edit = false;
  
  /**
   * The number of comments on the post
   * @var int
   */
  
  var $num_comments = 0;
  
  /**
   * The master permission set for the blog. Only used during pagination, don't worry about this
   * @var object
   */
  
  var $blog_perms;
  
  /**
   * Renders the post.
   */
  
  function render_post()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    if ( empty($template->theme) )
      $template->load_theme();
    
    if ( file_exists( ENANO_ROOT . "/themes/{$template->theme}/blog_post.tpl" ) )
    {
      $parser = $template->makeParser('blog_post.tpl');
    }
    else
    {
      $tpl_code = <<<TPLBLOCK

      <!-- Start of blog post -->
      
      <div class="blog-post">
        <div class="blog-post-header">
          <div class="blog-post-datemark">
            {DATE_D} {DATE_j}<br />
            {DATE_M} {DATE_Y}
          </div>
          <h3><a href="{PERMALINK}">{POST_TITLE}</a></h3>
          <div class="blog-post-author">
            Posted by {USER_LINK} on {TIMESTAMP}
          </div>
          <div class="blog-post-buttons">
          <a href="{PERMALINK}#do:comments" onclick="ajaxComments();">{COMMENT_STRING}</a>
          <!-- BEGIN auth_edit -->
          &bull;
          <a href="{EDIT_LINK}">Edit this post</a>
          <!-- END auth_edit -->
          </div>
        </div>
        <div class="blog-post-body">
          {POST_TEXT}
        </div>
      </div>
      
      <!-- Finish blog post -->
      
TPLBLOCK;
      $parser = $template->makeParserText($tpl_code);
    }
    
    $this->post_title_clean = nuggie_sanitize_title($this->post_title);
    
    // List of valid characters for date()
    $date_chars = 'dDjlNSwzWFmMntLoYyaABgGhHiseIOTZcrU';
    $date_chars = enano_str_split($date_chars);
    
    $strings = array();
    foreach ( $date_chars as $char )
    {
      $strings["DATE_$char"] = date($char, $this->post_timestamp);
    }
    
    $strings['POST_TITLE'] = htmlspecialchars($this->post_title);
    $strings['POST_TEXT'] = RenderMan::render($this->post_text);
    $strings['PERMALINK'] = makeUrlNS('Blog', $this->post_author . date('/Y/n/j/', $this->post_timestamp) . $this->post_title_clean, false, true);
    $strings['EDIT_LINK'] = makeUrlNS('Special', "Preferences/Blog/Write/{$this->post_id}", false, true);
    
    // if we're on an enano with user rank support, cool. if not, just don't link
    if ( method_exists($session, 'get_user_rank') )
    {
      $rank_data = $session->get_user_rank($this->post_author);
      $strings['USER_LINK'] = '<a href="' . makeUrlNS('User', $this->post_author, false, true) . '" style="' . $rank_data['rank_style'] . '" title="' . htmlspecialchars($rank_data['rank_title']) . '">' . htmlspecialchars($this->post_author) . '</a>';
    }
    else
    {
      $strings['USER_LINK'] = '<a href="' . makeUrlNS('User', $this->post_author, false, true) . '" style="' . $rank_data['rank_style'] . '">' . htmlspecialchars($this->post_author) . '</a>';
    }
    
    if ( $this->num_comments == 0 )
      $comment_string = 'No comments';
    else if ( $this->num_comments == 1 )
      $comment_string = '1 comment';
    else
      $comment_string = intval($this->num_comments) . ' comments';
      
    $strings['COMMENT_STRING'] = $comment_string;
    $strings['TIMESTAMP'] = date('l, F j, Y \a\t h:i <\s\m\a\l\l>A</\s\m\a\l\l>', $this->post_timestamp);
    
    $parser->assign_vars($strings);
    $parser->assign_bool(array(
        'auth_edit' => ( $this->auth_edit )
      ));
    
    return $parser->run();
  }
  /**
   * Don't worry about this, it's only called from the paginator.
   * @access private
   */
   
  function paginate_handler($_, $row)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    if ( !is_object($this->blog_perms) )
    {
      $this->blog_perms = $session->fetch_page_acl($row['username'], 'Blog');
    }
    
    $perms = $session->fetch_page_acl("{$row['post_timestamp']}_{$row['post_id']}", 'Blog');
    $perms->perms = $session->acl_merge($this->blog_perms->perms, $perms->perms);
    
    /*
    if ( !$perms->get_permissions('read') )
    {
      return "POST {$this->post_id} DENIED";
    }
    */
    
    $this->post_id = intval($row['post_id']);
    $this->post_title = $row['post_title'];
    $this->post_text = $row['post_text'];
    $this->post_author = $row['username'];
    $this->post_timestamp = intval($row['post_timestamp']);
    $this->num_comments = intval($row['num_comments']);
    
    return $this->render_post();
  }
}

function nuggie_blog_uri_handler($page)
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  
  $uri = $page->page_id;
  
  $template->add_header('<link rel="stylesheet" type="text/css" href="' . scriptPath . '/plugins/nuggie/style.css" />');
  if ( strstr($uri, '/') )
  {
    //
    // Permalinked post
    //
    
    // Split and parse URI
    $particles = explode('/', $uri);
    if ( count($particles) < 5 )
      return false;
    $sz = count($particles);
    for ( $i = 5; $i < $sz; $i++ )
    {
      $particles[4] .= '/' . $particles[$i];
      unset($particles[$i]);
    }
    
    $particles[4] = nuggie_sanitize_title($particles[4]);
    $poster =& $particles[0];
    $year =& $particles[1];
    $month =& $particles[2];
    $day =& $particles[3];
    $post_title_clean =& $particles[4];
    
    $particlecomp = $db->escape(implode('/', $particles));
    
    $year = intval($year);
    $month = intval($month);
    $day = intval($day);
    
    $time_min = mktime(0, 0, 0, $month, $day, $year);
    $time_max = $time_min + 86400;
    
    $ptc = $db->escape($post_title_clean);
    $uname = $db->escape(dirtify_page_id($poster));
    
    $q = $db->sql_query("SELECT p.post_id\n"
                      . "      FROM " . table_prefix . "blog_posts AS p\n"
                      . "  LEFT JOIN " . table_prefix . "users AS u\n"
                      . "    ON ( u.user_id = p.post_author )\n"
                      . "  WHERE p.post_timestamp >= $time_min AND p.post_timestamp <= $time_max\n"
                      . "    AND p.post_title_clean = '$ptc' AND u.username = '$uname'\n"
                      . "  GROUP BY p.post_id;");
    if ( !$q )
      $db->_die('Nuggie post handler doing name- and date-based lookup');
    
    if ( $db->numrows() < 1 )
      return false;
    
    if ( $db->numrows() > 1 )
    {
      die_friendly('Ambiguous blog posts', '<p>FIXME: You have two posts with the same title posted on the same day by the same user. I was
                                               not able to distinguish which post you wish to view.</p>');
    }
    
    $row = $db->fetchrow();
    
    $realpost = new PageProcessor($row['post_id'], 'BlogPost');
    
    // huge hack
    // the goal here is to fool the page metadata system into thinking that comments are enabled.
    $paths->cpage['comments_on'] = 1;
    if ( !isset($paths->pages[$paths->nslist['BlogPost'] . $row['post_id']]) )
    {
      $paths->pages[$paths->nslist['BlogPost'] . $row['post_id']] = array(
          'urlname' => $paths->nslist['BlogPost'] . $row['post_id'],
          'urlname_nons' => $row['post_id'],
          'name' => 'determined at runtime',
          'comments_on' => 1,
          'special' => 0,
          'wiki_mode' => 0,
          'protected' => 1,
          'delvotes' => 0
        );
    }
    $realpost->page_exists = true;
    // end huge hack
      
    $template->init_vars($realpost);
    $realpost->send();
    
    return true;
  }
  else
  {
    return nuggie_blog_index($uri);
  }
}

function nuggie_blogpost_uri_handler($page)
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  
  if ( !preg_match('/^[0-9]+$/', $page->page_id) )
  {
    return $page->err_page_not_existent();
  }
  
  // using page_id is SAFE. It's checked with a regex above.
  $q = $db->sql_query("SELECT p.post_id, p.post_title, p.post_title_clean, p.post_author, p.post_timestamp, p.post_text, b.blog_name,\n"
                    . "       b.blog_subtitle, b.blog_type, b.allowed_users, u.username, u.user_level, COUNT(c.comment_id) AS num_comments\n"
                    . "      FROM " . table_prefix . "blog_posts AS p\n"
                    . "  LEFT JOIN " . table_prefix . "blogs AS b\n"
                    . "    ON ( b.user_id = p.post_author )\n"
                    . "  LEFT JOIN " . table_prefix . "users AS u\n"
                    . "    ON ( u.user_id = p.post_author )\n"
                    . "  LEFT JOIN " . table_prefix . "comments AS c\n"
                    . "    ON ( ( c.page_id = '{$page->page_id}' AND c.namespace = 'BlogPost' ) OR ( c.page_id IS NULL AND c.namespace IS NULL ) )\n"
                    . "  WHERE p.post_id = {$page->page_id}\n"
                    . "  GROUP BY p.post_id;");
  if ( !$q )
    $db->_die('Nuggie post handler selecting main post data');
  
  if ( $db->numrows() < 1 )
    return false;
  
  $row = $db->fetchrow();
  
  //
  // Determine permissions
  //
  
  // The way we're doing this is first fetching permissions for the blog, and then merging them
  // with permissions specific to the post. This way the admin can set custom permissions for the
  // entire blog, and they'll be inherited unless individual posts have overriding permissions.
  $perms_blog = $session->fetch_page_acl($row['username'], 'Blog');
  $perms = $session->fetch_page_acl("{$row['post_timestamp']}_{$row['post_id']}", 'Blog');
  $perms->perms = $session->acl_merge($perms->perms, $perms_blog->perms);
  unset($perms_blog);
  
  if ( $row['blog_type'] == 'private' )
  {
    $allowed_users = unserialize($row['allowed_users']);
    if ( !in_array($session->username, $allowed_users) && !$perms->get_permissions('nuggie_see_non_public') && $row['username'] != $session->username )
    {
      return $page->err_access_denied();
    }
  }
  
  $acl_type = ( $row['post_author'] == $session->user_id ) ? 'nuggie_edit_own' : 'nuggie_edit_other';
  
  if ( !$perms->get_permissions('read') )
    return $page->err_access_denied();
  
  // enable comments
  $paths->cpage['comments_on'] = 1;
  // disable editing
  $session->acl_merge_with_current(array(
      'edit_page' => AUTH_DENY
    ));
  
  // We're validated - display post
  $postbit = new NuggiePostbit();
  $postbit->post_id = intval($row['post_id']);
  $postbit->post_title = $row['post_title'];
  $postbit->post_text = $row['post_text'];
  $postbit->post_author = $row['username'];
  $postbit->post_timestamp = intval($row['post_timestamp']);
  $postbit->auth_edit = $perms->get_permissions($acl_type);
  $postbit->num_comments = intval($row['num_comments']);
  
  $page_name = htmlspecialchars($row['post_title']) . ' &laquo; ' . htmlspecialchars($row['blog_name']);
  if ( method_exists($template, 'assign_vars') )
  {
    $template->assign_vars(array(
        'PAGE_NAME' => $page_name
      ));
  }
  else
  {
    $template->tpl_strings['PAGE_NAME'] = $page_name;
  }
  
  $template->header();
  echo '&lt; <a href="' . makeUrlNS('Blog', $row['username']) . '">' . htmlspecialchars($row['blog_name']) . '</a>';
  echo $postbit->render_post();
  display_page_footers();
  $template->footer();
}

function nuggie_blog_index($username)
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  
  $username_esc = $db->escape($username);
  
  // First look for the user's blog so we can get permissions
  $q = $db->sql_query('SELECT b.blog_type, b.allowed_users, b.user_id, b.blog_name, b.blog_subtitle FROM ' . table_prefix . "blogs AS b LEFT JOIN " . table_prefix . "users AS u ON ( u.user_id = b.user_id ) WHERE u.username = '$username_esc';");
  if ( !$q )
    $db->_die('Nuggie main blog page doing preliminary security check');
  
  if ( $db->numrows() < 1 )
    return false;
  
  list($blog_type, $allowed_users, $user_id, $blog_name, $blog_subtitle) = $db->fetchrow_num();
  $db->free_result();
  
  $perms = $session->fetch_page_acl($username, 'Blog');
  
  if ( $blog_type == 'private' )
  {
    $allowed_users = unserialize($allowed_users);
    if ( !in_array($session->username, $allowed_users) && !$perms->get_permissions('nuggie_see_non_public') && $username != $session->username )
    {
      return '_err_access_denied';
    }
  }
  
  // Determine number of posts and prefetch ACL info
  $q = $db->sql_query('SELECT post_timestamp, post_id FROM ' . table_prefix . 'blog_posts WHERE post_author = ' . $user_id . ' AND post_published = 1;');
  if ( !$q )
    $db->_die('Nuggie main blog page doing rowcount of blog posts');
  
  $count = $db->numrows();
  
  while ( $row = $db->fetchrow($q) )
  {
    $session->fetch_page_acl("{$row['post_timestamp']}_{$row['post_id']}", 'Blog');
  }
  
  $db->free_result($q);
  
  $q = $db->sql_unbuffered_query("SELECT p.post_id, p.post_title, p.post_title_clean, p.post_author, p.post_timestamp, p.post_text, b.blog_name,\n"
                    . "       b.blog_subtitle, u.username, u.user_level, COUNT(c.comment_id) AS num_comments\n"
                    . "      FROM " . table_prefix . "blog_posts AS p\n"
                    . "  LEFT JOIN " . table_prefix . "blogs AS b\n"
                    . "    ON ( b.user_id = p.post_author )\n"
                    . "  LEFT JOIN " . table_prefix . "users AS u\n"
                    . "    ON ( u.user_id = p.post_author )\n"
                    . "  LEFT JOIN " . table_prefix . "comments AS c\n"
                    . "    ON ( ( c.page_id = CAST(p.post_id AS char) AND c.namespace = 'BlogPost' ) OR ( c.page_id IS NULL AND c.namespace IS NULL ) )\n"
                    . "  WHERE p.post_author = $user_id AND p.post_published = 1\n"
                    . "  GROUP BY p.post_id\n"
                    . "  ORDER BY p.post_timestamp DESC;");
  if ( !$q )
    $db->_die('Nuggie main blog page selecting the whole shebang');
    
  if ( $count < 1 )
  {
    // Either the user hasn't created a blog yet, or he isn't even registered
    return false;
  }
  
  $page_name = htmlspecialchars($blog_name) . ' &raquo; ' . htmlspecialchars($blog_subtitle);
  if ( method_exists($template, 'assign_vars') )
  {
    $template->assign_vars(array(
        'PAGE_NAME' => $page_name
      ));
  }
  else
  {
    $template->tpl_strings['PAGE_NAME'] = $page_name;
  }
  
  $postbit = new NuggiePostbit();
  // $q, $tpl_text, $num_results, $result_url, $start = 0, $perpage = 10, $callers = Array(), $header = '', $footer = ''
  $html = paginate(
      $q,
      '{post_id}',
      $count,
      makeUrlNS('Blog', $username, "start=%s", true),
      0,
      10,
      array( 'post_id' => array($postbit, 'paginate_handler') ),
      '<span class="menuclear"></span>'
    );
  
  $template->header();
  
  echo $html;
  
  $template->footer();
  
  return true;
}

?>
