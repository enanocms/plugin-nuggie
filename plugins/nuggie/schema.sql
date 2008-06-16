-- Nuggie
-- Version 0.1
-- Copyright (C) 2007 Dan Fuhry

-- This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
-- as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.

-- This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
-- warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.

CREATE TABLE {{TABLE_PREFIX}}blogs(
  blog_id int(12) NOT NULL auto_increment,
  blog_name varchar(255) NOT NULL,
  blog_subtitle text NOT NULL,
  user_id int(12) NOT NULL,
  blog_type ENUM('private', 'public') NOT NULL DEFAULT 'public',
  allowed_users text,
  PRIMARY KEY ( blog_id )
) ENGINE = MyISAM CHARACTER SET utf8 COLLATE utf8_bin;

CREATE TABLE {{TABLE_PREFIX}}planets(
  planet_id smallint(6) NOT NULL auto_increment,
  planet_name varchar(255) NOT NULL,
  planet_subtitle text NOT NULL,
  planet_creator int(12) NOT NULL DEFAULT 1,
  planet_public tinyint(1) NOT NULL DEFAULT 0,
  planet_visible tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY ( planet_id )
) ENGINE = MyISAM CHARACTER SET utf8 COLLATE utf8_bin;

CREATE TABLE {{TABLE_PREFIX}}blog_posts(
  post_id int(15) NOT NULL auto_increment,
  post_title text NOT NULL,
  post_title_clean text NOT NULL,
  post_author int(12) NOT NULL DEFAULT 1,
  post_text longtext NOT NULL,
  post_timestamp int(32) NOT NULL DEFAULT 0,
  post_published tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY ( post_id )
) ENGINE = MyISAM CHARACTER SET utf8 COLLATE utf8_bin;

CREATE TABLE {{TABLE_PREFIX}}planets_mapping(
  mapping_id int(15) NOT NULL auto_increment,
  planet_id smallint(6) NOT NULL,
  mapping_type smallint(3) NOT NULL DEFAULT 1,
  mapping_value text NOT NULL,
  PRIMARY KEY ( mapping_id )
) ENGINE = MyISAM CHARACTER SET utf8 COLLATE utf8_bin;

