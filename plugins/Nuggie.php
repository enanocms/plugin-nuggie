<?php
/*
Plugin Name: Nuggie
Plugin URI: http://enanocms.org/Nuggie
Description: Nuggie provides a complete blogging suite for Enano-based websites. Named after Scottish water sprites.
Author: Dan Fuhry
Version: 0.1
Author URI: http://enanocms.org/
*/

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

global $db, $session, $paths, $template, $plugins; // Common objects

if ( getConfig('nuggie_installed') != '1' )
{
  $plugins->attachHook('base_classes_initted', '
      $paths->add_page(Array(
        \'name\'=>\'Install Nuggie\',
        \'urlname\'=>\'NuggieInstall\',
        \'namespace\'=>\'Special\',
        \'special\'=>0,\'visible\'=>0,\'comments_on\'=>0,\'protected\'=>1,\'delvotes\'=>0,\'delvote_ips\'=>\'\',
        ));
    ');
  require( ENANO_ROOT . '/plugins/nuggie/install.php' );
}

$plugins->attachHook('base_classes_initted', '
    list($page_id, $namespace) = RenderMan::strToPageId($paths->get_pageid_from_url());
    
    if ( $page_id == "Preferences" && $namespace == "Special" )
    {
      require( ENANO_ROOT . "/plugins/nuggie/usercp.php" );
    }
    else if ( $page_id == "Search" && $namespace == "Special" )
    {
      require( ENANO_ROOT . "/plugins/nuggie/search.php" );
    }
  ');

$plugins->attachHook('acl_rule_init', 'nuggie_namespace_setup();');

function nuggie_namespace_setup()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  
  // Insert additional namespaces
  
  $paths->create_namespace('Blog', 'Blog:');
  $paths->create_namespace('Planet', 'Planet:');
  
  // Create custom permissions for Nuggie
  
  $session->register_acl_type('nuggie_post', AUTH_DISALLOW, 'Post blog entries or create blog', Array(), 'Blog');
  $session->register_acl_type('nuggie_edit_own', AUTH_DISALLOW, 'Edit own blog posts', Array(), 'Blog');
  $session->register_acl_type('nuggie_edit_other', AUTH_DISALLOW, 'Edit others\' blog posts', Array(), 'Blog');
  $session->register_acl_type('nuggie_create_planet', AUTH_DISALLOW, 'Create new planets', Array(), 'Planet');
  $session->register_acl_type('nuggie_publicize_planet', AUTH_DISALLOW, 'Make own planets searchable', Array('nuggie_create_planet'), 'Planet');
  $session->register_acl_type('nuggie_protect_planet', AUTH_DISALLOW, 'Protect planets from public modification', Array(), 'Planet');
  $session->register_acl_type('nuggie_edit_planet_own', AUTH_DISALLOW, 'Edit own planets', Array(), 'Planet');
  $session->register_acl_type('nuggie_edit_planet_other', AUTH_DISALLOW, 'Edit others\' planets', Array(), 'Planet');
  $session->register_acl_type('nuggie_even_when_protected', AUTH_DISALLOW, 'Override protection on editing planets', Array(), 'Planet');
  $session->register_acl_type('nuggie_see_non_public', AUTH_DISALLOW, 'See non-public blogs', Array(), 'Blog');
  
  // Extend the core permission set
  
  $session->acl_extend_scope('read', 'Blog|Planet', $paths);
  $session->acl_extend_scope('edit_comments', 'Blog', $paths);
  $session->acl_extend_scope('post_comments', 'Blog', $paths);
  $session->acl_extend_scope('mod_comments', 'Blog', $paths);
}

$plugins->attachHook('page_type_string_set', 'nuggie_set_page_string();');

function nuggie_set_page_string()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  
  if ( $paths->namespace == 'Blog' )
  {
    $paths->cpage['comments_on'] = 0;
    $template->namespace_string = 'blog';
    if ( strstr($paths->cpage['urlname_nons'], '/') )
    {
      $paths->cpage['comments_on'] = 1;
      $template->namespace_string = 'blog post';
    }
  }
  else if ( $paths->namespace == 'Planet' )
  {
    $paths->cpage['comments_on'] = 0;
    $template->namespace_string = 'planet';
  }
}

$plugins->attachHook('page_not_found', 'nuggie_handle_namespace($this);');

function nuggie_handle_namespace($processor)
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  
  if ( $processor->namespace == 'Blog' )
  {
    require( ENANO_ROOT . '/plugins/nuggie/postbit.php' );
    $result = nuggie_blog_uri_handler($processor->page_id);
    if ( $result === '_err_access_denied' )
    {
      $processor->err_access_denied();
      return true;
    }
  }
  else if ( $processor->namespace == 'Planet' )
  {
    $result = nuggie_planet_uri_handler($processor->page_id);
    if ( $result === '_err_access_denied' )
    {
      $processor->err_access_denied();
      return true;
    }
  }
}

/**
 * Sanitizes a string for a Nuggie permalink.
 * @param string String to sanitize
 * @return string
 */

function nuggie_sanitize_title($title)
{
  // Placeholder for now - this may become more elaborate in the future, we'll see
  return sanitize_page_id($title);
}

?>
