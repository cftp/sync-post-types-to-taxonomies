<?php 

/*  Copyright 2012 Code for the People Ltd

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
 * Returns the post_type that the passed taxonomy is 
 * synced with. If no taxonomy is specified, and the
 * current page is a taxonomy term archive then that
 * taxonomy is used.
 *
 * @param string $post_type The taxonomy which we are checking 
 * @return string|boolean Either the name of the post_type, or false
 **/
function sptt_taxonomy_syncs_with( $taxonomy = null ) {
	global $sptt_sync;
	return $sptt_sync->taxonomy_syncs_with( $taxonomy );
}

/**
 * Returns the taxonomy that the passed post_type is 
 * synced with. If no post_type is specified, the method
 * tries to extract the post type from the current post in
 * the loop of associated with this single/singular page.
 *
 * @param string $post_type The post_type which we are checking 
 * @return string|boolean Either the name of the taxonomy, or false
 **/
function sptt_post_type_syncs_with( $post_type = null ) {
	global $sptt_sync;
	return $sptt_sync->post_type_syncs_with( $post_type );
}

/**
 * Get the post ID for the Post related to this Term.
 *
 * @param int $term_id The term_id of the Term to find the Post for
 * @return int The Post ID or 0 if nothing
 **/
function sptt_get_related_post_for_term( $term_id = null ) {
	global $sptt_sync;
	return $sptt_sync->get_related_post_for_term( $term_id );
}

/**
 * Get the term ID related to a post.
 *
 * @param int|object $post The post ID or post object
 * @return boolean|term False if a term can't be found or the term object
 **/
function sptt_get_related_term_for_post( $post = null ) {
	$_post = get_post( $post );
	$term_id = (int) get_post_meta( $_post->ID, '_sptt_term_id', true );
	if ( 0 == $term_id )
		return false;
	return $term_id;
}

 ?>