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
  
  $planet_id = dirtify_page_id($page->page_id);
  $offset = 0;
  if ( preg_match('#/start=([0-9]+)$#', $planet_id, $match) )
  {
    $planet_id = substr($planet_id, 0, (strlen($planet_id) - strlen($match[0])));
    $offset = intval($match[1]);
  }
  
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
    return $page->err_page_not_existent();
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
  
  // Set page title
  $page_title = dirtify_page_id($planet_data['planet_name']);
  $template->assign_vars(array(
      'PAGE_NAME' => htmlspecialchars($page_title)
    ));
  
  // Try to grab the posts. The SQL tricks here are rather interesting, you'll have to look at it from a distance to grasp it.
  // Basically just using MySQL to apply all the filters in one go. Nuggie doesn't do PostgreSQL yet.
  $sql_base = "SELECT <columns>\n"
       . "  FROM " . table_prefix . "blog_posts AS p\n"
       . "  LEFT JOIN " . table_prefix . "blogs AS b\n"
       . "    ON ( b.user_id = p.post_author )\n"
       . "  LEFT JOIN " . table_prefix . "users AS u\n"
       . "    ON ( u.user_id = p.post_author )\n"
       . "  LEFT JOIN " . table_prefix . "comments AS c\n"
       . "    ON ( c.page_id = CAST(p.post_id AS char) AND c.namespace = 'BlogPost' )\n"
       . "  LEFT JOIN " . table_prefix . "tags AS t\n"
       . "    ON ( t.page_id = CAST(p.post_id AS char) AND t.namespace = 'BlogPost' )\n"
       . "  LEFT JOIN " . table_prefix . "planets_mapping AS m\n"
       . "    ON (\n"
       . "         ( m.mapping_type = " . NUGGIE_PLANET_FILTER_TAG . " AND m.mapping_value = t.tag_name  ) OR\n"
       . "         ( m.mapping_type = " . NUGGIE_PLANET_FILTER_AUTHOR . " AND CAST(m.mapping_value AS unsigned integer) = p.post_author ) OR\n"
       . "         ( m.mapping_type = " . NUGGIE_PLANET_FILTER_KEYWORD . " AND ( p.post_text LIKE CONCAT('%', m.mapping_value, '%') OR p.post_title LIKE CONCAT('%', m.mapping_value, '%') ) )\n"
       . "       )\n"
       . "  WHERE m.planet_id = {$planet_data['planet_id']}\n"
       . "    AND p.post_published = 1\n"
       . "  GROUP BY p.post_id\n"
       . "  ORDER BY p.post_timestamp DESC\n"
       . "  <limit>;";
       
  // pass 1: a test run to count the number of results
  $sql = str_replace('<columns>', 'p.post_id', $sql_base);
  $sql = str_replace('<limit>', "", $sql);
  $q = $db->sql_query($sql);
  if ( !$q )
    $db->_die();
  
  $count = $db->numrows();
  
  $db->free_result($sql);
  
  // RSS check - do we have support for Feed Me and did the user request an RSS feed?
  $do_rss = defined('ENANO_FEEDBURNER_INCLUDED') && ( isset($_GET['feed']) && $_GET['feed'] === 'rss2' );
  $query_limit = $do_rss ? 50 : 10;
  if ( $do_rss )
  {
    $offset = 0;
  }
  
  // pass 2: production run
  $columns = 'p.post_id, p.post_title, p.post_title_clean, p.post_author, p.post_timestamp, p.post_text, b.blog_name, b.blog_subtitle, b.blog_type, b.allowed_users, u.username, u.user_level, COUNT(c.comment_id) AS num_comments, \'' . $db->escape($planet_id) . '\' AS referring_planet';
  $sql = str_replace('<columns>', $columns, $sql_base);
  $sql = str_replace('<limit>', "LIMIT $offset, $query_limit", $sql);
  
  // yea. that was one query.
  $q = $db->sql_query($sql);
  if ( !$q )
    $db->_die();
  
  // RSS feed?
  if ( $do_rss )
  {
    header('Content-type: text/xml; charset=utf-8');
    global $aggressive_optimize_html;
    $aggressive_optimize_html = false;
    $rss = new RSS(
      getConfig('site_name') . ': ' . $planet_data['planet_name'],
      $planet_data['planet_subtitle'],
      makeUrlComplete('Planet', $planet_id)
    );
    while ( $row = $db->fetchrow($q) )
    {
      $permalink = makeUrlNS('Blog', sanitize_page_id($row['username']) . date('/Y/n/j/', intval($row['post_timestamp'])) . $row['post_title_clean'], false, true);
      $post = RenderMan::render($row['post_text']);
      $rss->add_item($row['post_title'], $permalink, $post, intval($row['post_timestamp']));
    }
    echo $rss->render();
    return;
  }
  
  // Add the link to the feed
  $rss_link = '';
  if ( defined('ENANO_FEEDBURNER_INCLUDED') )
  {
    $rss_link = '<p style="float: left;">
                   <a class="abutton" href="' . makeUrlNS('Planet', $planet_id, 'feed=rss2', true) . '">
                     <img alt=" " src="' . scriptPath . '/plugins/nuggie/images/feed.png" />
                     RSS feed
                   </a>
                 </p>';
  }
  
  // just let the paginator do the rest
  $postbit = new NuggiePostbit();
  // $q, $tpl_text, $num_results, $result_url, $start = 0, $perpage = 10, $callers = Array(), $header = '', $footer = ''
  $html = paginate(
      $q,
      '{post_id}',
      $count,
      makeUrlNS('Planet', "$planet_id/start=%s", true),
      0,
      10,
      array( 'post_id' => array($postbit, 'paginate_handler') ),
      '<span class="menuclear"></span>',
      $rss_link
    );
  $db->free_result($q);
  
  $template->add_header('<link rel="stylesheet" type="text/css" href="' . scriptPath . '/plugins/nuggie/style.css" />');
  $template->header();
  echo $planet_data['planet_subtitle'];
  echo $html;
  $template->footer();
}
 
?>
