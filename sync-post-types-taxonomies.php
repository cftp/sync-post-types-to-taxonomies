<?php

/*
Plugin Name: Sync Post Types & Taxonomies
Plugin URI: http://wordpress.org/extend/plugins/sync-post-types-taxonomies/
Description: Provides an API for plugin developers to sync specified a post type with a specified taxonomy, multiple pairs of post types and taxonomies can be synced.
Version: 1.1
Author: Simon Wheatley
*/
 
/*  Copyright 2011 Simon Wheatley

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*/

/**
 * Main plugin information and requires.
 *
 * @package Sync Post Types & Taxonomies
 * @since 1.0
 */

require_once( 'class-plugin.php' );
require_once( 'class-sync.php' );
require_once( 'api.php' );

?>
