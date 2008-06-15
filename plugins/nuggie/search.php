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

$plugins->attachHook('search_global_inner', 'nuggie_search($query, $query_phrase, $scores, $page_data, $case_sensitive, $word_list);');

/**
 * Searches the site's blog database for the specified search terms. Called from a hook.
 * @access private
 */

function nuggie_search(&$query, &$query_phrase, &$scores, &$page_data, &$case_sensitive, &$word_list)
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  
  require_once( ENANO_ROOT . '/plugins/nuggie/postbit.php' );
  
  // Based on the search functions from Snapr and Decir
  
  // Let's do this all in one query
  $terms = array(
      'any' => array_merge($query['any'], $query_phrase['any']),
      'req' => array_merge($query['req'], $query_phrase['req']),
      'not' => $query['not']
    );
  $where = array('any' => array(), 'req' => array(), 'not' => array());
  $where_any =& $where['any'];
  $where_req =& $where['req'];
  $where_not =& $where['not'];
  $title_col = ( $case_sensitive ) ? 'p.post_title' : 'lcase(p.post_title)';
  $desc_col = ( $case_sensitive ) ? 'p.post_text' : 'lcase(p.post_text)';
  foreach ( $terms['any'] as $term )
  {
    $term = escape_string_like($term);
    if ( !$case_sensitive )
      $term = strtolower($term);
    $where_any[] = "( $title_col LIKE '%{$term}%' OR $desc_col LIKE '%{$term}%' )";
  }
  foreach ( $terms['req'] as $term )
  {
    $term = escape_string_like($term);
    if ( !$case_sensitive )
      $term = strtolower($term);
    $where_req[] = "( $title_col LIKE '%{$term}%' OR $desc_col LIKE '%{$term}%' )";
  }
  foreach ( $terms['not'] as $term )
  {
    $term = escape_string_like($term);
    if ( !$case_sensitive )
      $term = strtolower($term);
    $where_not[] = "$title_col NOT LIKE '%{$term}%' AND $desc_col NOT LIKE '%{$term}%'";
  }
  if ( empty($where_any) )
    unset($where_any, $where['any']);
  if ( empty($where_req) )
    unset($where_req, $where['req']);
  if ( empty($where_not) )
    unset($where_not, $where['not']);
  
  $where_any = '(' . implode(' OR ', $where_any) . '' . ( isset($where['req']) || isset($where['not']) ? ' OR 1 = 1' : '' ) . ')';
  
  if ( isset($where_req) )
    $where_req = implode(' AND ', $where_req);
  if ( isset($where_not) )
  $where_not = implode( 'AND ', $where_not);
  
  $where = implode(' AND ', $where);
  $sql = "SELECT p.post_id, p.post_title, p.post_title_clean, p.post_text, p.post_author, u.username, p.post_timestamp, u.username\n"
         . "    FROM " . table_prefix . "blog_posts AS p\n"
         . "  LEFT JOIN " . table_prefix . "users AS u\n"
         . "    ON ( u.user_id = p.post_author )\n"
         . "  WHERE ( $where )\n"
         . "  GROUP BY p.post_id;";
  
  if ( !($q = $db->sql_unbuffered_query($sql)) )
  {
    $db->_die('Error is in auto-generated SQL query in the Nuggie plugin search module');
  }
  
  if ( $row = $db->fetchrow() )
  {
    do
    {
      $day = enano_date('d', $row['post_timestamp']);
      $year = enano_date('Y', $row['post_timestamp']);
      $month = enano_date('n', $row['post_timestamp']);
      $username = sanitize_page_id($row['username']);
      $post_url = "{$username}/$year/$month/$day/{$row['post_title_clean']}";
      $idstring = "ns=Blog;pid=$post_url";
      foreach ( $word_list as $term )
      {
        $func = ( $case_sensitive ) ? 'strstr' : 'stristr';
        $inc = ( $func($row['post_title'], $term) ? 1.5 : ( $func($row['post_text'], $term) ? 1 : 0 ) );
        ( isset($scores[$idstring]) ) ? $scores[$idstring] = $scores[$idstring] + $inc : $scores[$idstring] = $inc;
      }
      // Generate text...
      $post_text = highlight_and_clip_search_result($row['post_text'], $word_list);
      $post_length = strlen($post_text);
      
      // Inject result
      
      if ( isset($scores[$idstring]) )
      {
        // echo('adding image "' . $row['img_title'] . '" to results<br />');
        $page_data[$idstring] = array(
          'page_name' => highlight_search_result(htmlspecialchars($row['post_title']), $word_list),
          'page_text' => $post_text,
          'score' => $scores[$idstring],
          'page_note' => '[Blog post]',
          'page_id' => $post_url,
          'namespace' => 'Blog',
          'page_length' => $post_length,
        );
      }
    }
    while ( $row = $db->fetchrow() );
  }
}
