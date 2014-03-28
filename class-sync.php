<?php

require_once( 'class-plugin.php' );

/**
 * This class handles the syncing between post types and taxonomies.
 * 
 * @package Sync Post Types & Taxonomies
 * @since 1.0
 **/
class SPTT_Sync extends SPTT_Plugin {

	/**
	 * A boolean flag indicating whether we are in the process 
	 * of syncing something. Used to avoid recursion.
	 *
	 * @var bool
	 **/
	var $syncing;

	/**
	 * The version of this file, for the purposes of rewrite
	 * refreshes, JS/CSS cache bursting, etc.
	 *
	 * @var int
	 **/
	var $version;

	/**
	 * Store a brief cache of what post_ids are associated with
	 * what term_ids.
	 *
	 * @var array
	 **/
	var $post_to_term;

	/**
	 * A flag to say when an import is happening.
	 *
	 * @var boolean
	 **/
	var $importing;

	/**
	 * Used to store the direction and data to sync for the admin screen.
	 *
	 * @var array|boolean
	 **/
	var $sync;

	/**
	 * Used to store the link term and post IDs for the admin screen.
	 *
	 * @var array|boolean
	 **/
	var $link;

	// HOOKS
	// =====
	
	/**
	 * Initiate!
	 *
	 * @return void
	 **/
	function __construct() {
		$this->setup( 'sptt', 'plugin' );
		$this->add_action( 'admin_menu' );
		$this->add_action( 'before_delete_post', 'cache_term_id_for_post' );
		$this->add_action( 'created_term', null, null, 3 );
		$this->add_action( 'delete_post' );
		$this->add_action( 'delete_term_taxonomy' );
		$this->add_action( 'edited_term', null, null, 3 );
		$this->add_action( 'import_end' );
		$this->add_action( 'import_start' );
		$this->add_action( 'save_post' );
		$this->add_action( 'trashed_post', 'delete_post' );
		$this->add_action( 'untrashed_post' );
		$this->add_action( 'wp_trash_post', 'cache_term_id_for_post' );
		$this->add_action( 'load-tools_page_resync_sptt', 'load_resync' );
		$this->add_action( 'load-tools_page_relink_sptt', 'load_relink' );
		
		$this->importing = false;
		$this->post_to_term = array();
		$this->sync = false;
		$this->link = false;
		$this->syncing = false;
		$this->version = 2;
	}

	// HOOKS
	// =====

	/**
	 * Hook the WP admin_menu action to add our re-sync menu item.
	 *
	 * @return void
	 **/
	function admin_menu() {
		add_management_page( __( 'Re-Sync Posts to Terms (or vice versa)', 'sptt' ), __( 'Re-sync', 'sptt' ), 'manage_options', 'resync_sptt', array( $this, 'management_page_resync' ) );
		add_management_page( __( 'Re-Link Individual Term to an Individual Post', 'sptt' ), __( 'Re-link', 'sptt' ), 'manage_options', 'relink_sptt', array( $this, 'management_page_relink' ) );
	}

	/**
	 * Hooks the WP load action for the resync page.
	 *
	 * @return void
	 **/
	function load_resync() {
		global $wpdb;

		wp_enqueue_style( 'sptt-admin', $this->url( '/css/admin.css' ), array(), $this->version, 'screen' );
		
		$action = isset( $_POST[ 'action' ] ) ? $_POST[ 'action' ] : false;
		if ( ! $action )
			return;
		if ( 'check_sync' == $action ) {
			check_admin_referer( 'check', '_sptt_nonce' );
			$sync_direction = isset( $_POST[ 'sync_direction' ] ) ? $_POST[ 'sync_direction' ] : false;
			$bits = explode( '|', $sync_direction );
			$this->sync = array();
			$this->sync[ 'from' ] = array( 
				'type' => ( 'PT' == substr( $bits[0], 0, 2 ) ) ? 'post_type' : 'taxonomy',
				'name' => substr( $bits[0], 2 ),
			);
			$this->sync[ 'to' ] = array( 
				'type' => ( 'PT' == substr( $bits[1], 0, 2 ) ) ? 'post_type' : 'taxonomy',
				'name' => substr( $bits[1], 2 ),
			);
			
		} else if ( 'do_sync' == $action ) {
			check_admin_referer( 'sync', '_sptt_nonce' );

			// Get the tax and post type we are syncing
			$sync = array( 'to' => array(), 'from' => array() );
			$sync[ 'to' ][ 'type' ] = isset( $_POST[ 'to_type' ] ) ? $_POST[ 'to_type' ] : false;
			$sync[ 'to' ][ 'name' ] = isset( $_POST[ 'to_name' ] ) ? $_POST[ 'to_name' ] : false;
			$sync[ 'from' ][ 'type' ] = isset( $_POST[ 'from_type' ] ) ? $_POST[ 'from_type' ] : false;
			$sync[ 'from' ][ 'name' ] = isset( $_POST[ 'from_name' ] ) ? $_POST[ 'from_name' ] : false;

			// Check the tax and post type are valid for syncing, and
			// sync with each other

			$die_message = sprintf( 
				_x( 'Sorry, you cannot synchronise the %1$s called "%2$s" with the %3$s called "%4$s".', '', 'sptt' ), 
				( 'taxonomy' == $sync[ 'from' ][ 'type' ] ) ? __( 'taxonomy', 'sptt' ) : __( 'post type', 'sptt' ), 
				$this->get_label_name( $sync[ 'from' ][ 'name' ], $sync[ 'from' ][ 'type' ] ), 
				( 'taxonomy' == $sync[ 'to' ][ 'type' ] ) ? __( 'taxonomy', 'sptt' ) : __( 'post type', 'sptt' ),
				$this->get_label_name( $sync[ 'to' ][ 'name' ], $sync[ 'to' ][ 'type' ] ) 
			);
			
			if ( 'taxonomy' == $sync[ 'to' ][ 'type' ] ) {

				// Syncing from a post type, to a taxonomy

				// Deleting the taxonomy and syncing new terms from the posts
				if ( $sync[ 'from' ][ 'name' ] != $this->taxonomy_syncs_with( $sync[ 'to' ][ 'name' ] ) ) {
					wp_die( $die_message );
				}

				// Clear out the taxonomy
				$terms = get_terms( $sync[ 'to' ][ 'name' ], array( 'hide_empty' => false ) );
				$this->set_admin_notice( sprintf( __( 'Preparing to delete %d terms', 'sptt' ), count( $terms ) ) );
				foreach ( $terms as $term ) {
					$result = wp_delete_term( $term->term_id, $term->taxonomy );
					if ( is_wp_error( $result ) )
						error_log( "SPTT: Error deleting $term->name: " . print_r( $result, true ) );
				}
				$this->set_admin_notice( sprintf( __( 'Deleted %d terms', 'sptt' ), count( $terms ) ) );
				// Clear some memory
				unset( $terms );
				
				// Resync, re-creating the terms
				$post_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type = %s ", $sync[ 'from' ][ 'name' ] ) );
				$this->set_admin_notice( sprintf( __( 'Preparing to create %d terms synced from the posts', 'sptt' ), count( $post_ids ) ) );
				foreach ( $post_ids as $post_id ) {
					$this->process_post( $post_id, true );
					// Clear some memory in cache
					wp_cache_delete( $post_id, 'posts' );
				}

				$success_msg = sprintf( 
					__( 'Re-synchronisation of %1$d items from the %2$s called "%3$s" to the %4$s called "%5$s".', 'sptt' ), 
					count( $post_ids ),
					( 'taxonomy' == $sync[ 'from' ][ 'type' ] ) ? __( 'taxonomy', 'sptt' ) : __( 'post type', 'sptt' ), 
					$this->get_label_name( $sync[ 'from' ][ 'name' ], $sync[ 'from' ][ 'type' ] ), 
					( 'taxonomy' == $sync[ 'to' ][ 'type' ] ) ? __( 'taxonomy', 'sptt' ) : __( 'post type', 'sptt' ),
					$this->get_label_name( $sync[ 'to' ][ 'name' ], $sync[ 'to' ][ 'type' ] ) 
				);
				$this->set_admin_notice( $success_msg );
				
			} else {

				// Syncing from a taxonomy, to a post type

				// Deleting the posts in the post type and syncing new posts from the terms
				if ( $sync[ 'from' ][ 'name' ] != $this->post_type_syncs_with( $sync[ 'to' ][ 'name' ] ) ) {
					wp_die( $die_message );
				}

				// Clear out the post type, then resync
				$post_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type = %s ", $sync[ 'to' ][ 'name' ] ) );
				$this->set_admin_notice( sprintf( __( 'Preparing to delete %d posts', 'sptt' ), count( $post_ids ) ) );
				foreach ( $post_ids as $post_id )
					wp_delete_post( $post_id, true );
				$this->set_admin_notice( sprintf( __( 'Deleted %d posts', 'sptt' ), count( $post_ids ) ) );

				// Resync, re-creating the posts
				$terms = get_terms( $sync[ 'from' ][ 'name' ], array( 'hide_empty' => false ) );
				foreach ( $terms as $term ) {
					$this->process_term( $term->term_id, $term->taxonomy );
				}

				$success_msg = sprintf( 
					__( 'Re-synchronisation of %1$d items from the %2$s called "%3$s" to the %4$s called "%5$s".', 'sptt' ), 
					count( $terms ),
					( 'taxonomy' == $sync[ 'from' ][ 'type' ] ) ? __( 'taxonomy', 'sptt' ) : __( 'post type', 'sptt' ), 
					$this->get_label_name( $sync[ 'from' ][ 'name' ], $sync[ 'from' ][ 'type' ] ), 
					( 'taxonomy' == $sync[ 'to' ][ 'type' ] ) ? __( 'taxonomy', 'sptt' ) : __( 'post type', 'sptt' ),
					$this->get_label_name( $sync[ 'to' ][ 'name' ], $sync[ 'to' ][ 'type' ] ) 
				);
				$this->set_admin_notice( $success_msg );
			}
			
			wp_redirect( admin_url( '/tools.php?page=resync_sptt	' ) );
			exit;
		}
	}

	/**
	 * Hooks the WP load action for the resync page.
	 *
	 * @return void
	 **/
	function load_relink() {
		global $wpdb;

		wp_enqueue_style( 'sptt-admin', $this->url( '/css/admin.css' ), array(), $this->version, 'screen' );
		
		$action = isset( $_POST[ 'action' ] ) ? $_POST[ 'action' ] : false;
		if ( ! $action )
			return;

		if ( 'check_link' == $action ) {

			check_admin_referer( 'check', '_sptt_nonce' );

			$this->link = array();
			$this->link[ 'post_id' ] = isset( $_POST[ 'post_id' ] ) ? (int) $_POST[ 'post_id' ] : false;
			$this->link[ 'term_id' ] = isset( $_POST[ 'term_id' ] ) ? (int) $_POST[ 'term_id' ] : false;

			// Find existing links
			$this->link[ 'existing_term_ids' ] = get_post_meta( $this->link[ 'post_id' ], '_sptt_term_id' );
			$this->link[ 'existing_term_ids' ] = array_map( 'absint', (array) $this->link[ 'existing_term_ids' ] );
			$this->link[ 'existing_post_ids' ] = $this->get_related_post_for_term( $this->link[ 'term_id' ], false );

		} else if ( 'do_link' == $action ) {

			check_admin_referer( 'link', '_sptt_nonce' );

			$post_id = isset( $_POST[ 'post_id' ] ) ? (int) $_POST[ 'post_id' ] : false;
			$term_id = isset( $_POST[ 'term_id' ] ) ? (int) $_POST[ 'term_id' ] : false;

			// Delete existing post meta against this post ID
			$mids = $wpdb->get_col( $wpdb->prepare( " SELECT meta_id FROM $wpdb->postmeta WHERE meta_key = '_sptt_term_id' AND post_id = %d ", $post_id ) );
			foreach ( $mids as $mid )
				delete_meta( $mid );
			
			// Delete existing post meta linking to this term ID
			$sql = " SELECT post_ID FROM $wpdb->postmeta WHERE $wpdb->postmeta.meta_key = '_sptt_term_id' AND CAST( $wpdb->postmeta.meta_value AS CHAR ) = %d ";
			$mids = $wpdb->get_col( $wpdb->prepare( " SELECT meta_id FROM $wpdb->postmeta WHERE $wpdb->postmeta.meta_key = '_sptt_term_id' AND CAST( $wpdb->postmeta.meta_value AS CHAR ) = %d ", $term_id ) );
			foreach ( $mids as $mid )
				delete_meta( $mid );

			// Insert new postmeta
			add_post_meta( $post_id, '_sptt_term_id', $term_id, true );
			
			// @TODO: Do a sync at this point? Which way would we do it?

			$post = get_post( $post_id );
			$taxonomy = sptt_post_type_syncs_with( $post->post_type );
			$term = get_term( $term_id, $taxonomy );

			$success_msg = sprintf( 
				__( 'Successfully linked post "%1$s" (ID %2$d) with term %3$s (ID %4$d). When you next edit either the term or post, the data will overwrite the post or term which it is synchronised with.', 'sptt' ), 
				get_the_title( $post_id ),
				$post_id, 
				$term->name, 
				$term_id 
			);
			$this->set_admin_notice( $success_msg );
			
			wp_redirect( admin_url( '/tools.php?page=relink_sptt	' ) );
			exit;
		}
	}

	/**
	 * Hooks the WP save_post action to create or
	 * sync the appropriate term.
	 *
	 * @param int $post_id The ID of a Post
	 * @return void
	 * @author Simon Wheatley
	 **/
	function save_post( $post_id ) {
		$this->process_post( $post_id );
	}

	/**
	 * Hooks the WP untrash_post action to (re)create 
	 * the appropriate term.
	 *
	 * @param int $post_id The ID of a Post
	 * @param object $post An actual WP Post object
	 * @return void
	 * @author Simon Wheatley
	 **/
	function untrashed_post( $post_id ) {
		$post = get_post( $post_id );
		$this->process_post( $post_id );
	}

	/**
	 * Hooks the WP before_delete_post and wp_trash_post actions to store
	 * a brief cache of post_ids related to term_ids, as
	 * the meta we rely on will be deleted before the 
	 * delete_post action is fired.
	 *
	 * @param int $post_id The ID of the WP post being deleted
	 * @return void
	 **/
	function cache_term_id_for_post( $post_id ) {
		$this->post_to_term[ $post_id ] = (int) get_post_meta( $post_id, '_sptt_term_id', true );
	}

	/**
	 * Hooks the WP delete_post action to delete any 
	 * synced term.
	 *
	 * @param int $post_id The ID of the WP post being deleted
	 * @return void
	 * @author Simon Wheatley
	 **/
	function delete_post( $post_id ) {
		global $wpdb;

		if ( ! $this->do_sync() )
			return;

		$post = get_post( $post_id );

		$taxonomy = $this->post_type_syncs_with( $post->post_type );
		if ( ! $taxonomy )
			return;

		if ( $term_id = $this->post_to_term[ $post_id ] ) {
			$this->syncing = true;
			wp_delete_term( $term_id, $taxonomy );
			delete_post_meta( $post_id, '_sptt_term_id' ); // Only necessary when trashing
			$this->syncing = false;
		}
	}

	/**
	 * Hooks the WP created_term action, which is fired when a term is 
	 * created in the 'organisation' taxonomy.
	 *
	 * @param int $term_id The term_id of the Term 
	 * @param int $tt_id The term_taxonomy_id of the Term 
	 * @return void
	 * @author Simon Wheatley
	 **/
	function created_term( $term_id, $tt_id, $taxonomy ) {
		$this->process_term( $term_id, $taxonomy );
	}

	/**
	 * Hooks the WP edited_term action, which is fired when a term is 
	 * edited in the 'organisation' taxonomy.
	 *
	 * @param int $term_id The term_id of the Term 
	 * @param int $tt_id The term_taxonomy_id of the Term 
	 * @return void
	 * @author Simon Wheatley
	 **/
	function edited_term( $term_id, $tt_id, $taxonomy ) {
		$this->process_term( $term_id, $taxonomy );
	}

	/**
	 * Hooks the WP delete_term_taxonomy action, which is fired just before a term is 
	 * deleted in the 'organisation' taxonomy.
	 *
	 * @param int $tt_id The term_taxonomy_id of the Term 
	 * @return void
	 * @author Simon Wheatley
	 **/
	function delete_term_taxonomy( $tt_id ) {
		global $wpdb;

		if ( ! $this->do_sync() )
			return;

		// Work out which term we're dealing with
		$result = $wpdb->get_row( $wpdb->prepare( " SELECT term_id, taxonomy FROM $wpdb->term_taxonomy WHERE term_taxonomy_id = %d ", $tt_id ), ARRAY_A );
		extract( $result ); // Gets the $taxonomy and, critically, $term_id

		$term = get_term( $term_id, $taxonomy );

		$post_type = $this->taxonomy_syncs_with( $taxonomy );
		if ( ! $post_type )
			return;

		// Check if a organisation post exists with this slug
		$post_id = $this->get_related_post_for_term( $term_id );
		if ( $post_id ) {
			// @TODO: Deal with posts coming out of trash (i.e. recreate term)
			$this->syncing = true;
			wp_delete_post( $post_id );
			$this->syncing = false;
		}
	}

	/**
	 * Hooks the WordPress Importer import_start action to 
	 * note when the import starts (natch).
	 *
	 * @return void
	 **/
	function import_start() {
		$this->importing = true;
	}

	/**
	 * Hooks the WordPress Importer import_end action to 
	 * note when the import ends (obvs).
	 *
	 * @return void
	 **/
	function import_end() {
		$this->importing = false;
	}

	// CALLBACKS
	// =========

	/**
	 * A callback function providing HTML for the Re-sync 
	 * management page.
	 *
	 * @return void
	 **/
	function management_page_resync() {
		$vars = array();
		$vars[ 'synced' ] = $this->get_synced();
		if ( $this->sync )
			$this->render_admin( 'management-resync-check.php', $vars );
		else
			$this->render_admin( 'management-resync.php', $vars );
	}

	/**
	 * A callback function providing HTML for the Re-sync 
	 * management page.
	 *
	 * @return void
	 **/
	function management_page_relink() {
		$vars = array();
		if ( $this->link ) {
			$this->render_admin( 'management-relink-check.php', $vars );
		} else {
			$this->render_admin( 'management-relink.php', $vars );
		}
	}
	
	// METHODS
	// =======

	/**
	 * Handles posts being created or edited.
	 *
	 * @param int $post_id The ID of a Post
	 * @param boolean $force Whether to force the creation of a new term
	 * @return void
	 **/
	function process_post( $post_id, $force = false ) {
		if ( ! $this->do_sync() )
			return;
		$post = get_post( $post_id );

		$taxonomy = $this->post_type_syncs_with( $post->post_type );
		if ( ! $taxonomy || ! $this->post_status_synced( $post->post_status, $post->post_status ) )
			return;
		
		$edit_url = add_query_arg( array( 'post' => $post_id, 'action' => 'edit' ), admin_url( 'post.php' ) );
		$term_id = (int) get_post_meta( $post_id, '_sptt_term_id', true );
		$this->syncing = true;
		if ( $term_id && term_exists( $term_id, $taxonomy ) && ! $force ) {
			$term = get_term( $term_id, $taxonomy );
			$term_args = array();

			// if ( $term->name != $post->post_name )
				$term_args[ 'name' ] = $post->post_title;

			// We need to be sure the slug doesn't already exist, or we
			// will get an error when we update the term
			if ( $term->slug != $post->post_name )
				$term_args[ 'slug' ] = wp_unique_term_slug( $post->post_name, $term );

			$result = wp_update_term( $term_id, $taxonomy, $term_args );
			if ( is_wp_error( $result ) ) {
				error_log( "SPTT ERROR Updating Term $term_id ($term->name) for '$post->post_title' in $taxonomy. Error: " . $result->get_error_message() . " and Args: " . print_r( $term_args, true ) );
			}
		} else {
			delete_post_meta( $post_id, '_sptt_term_id' );
			// We need to be sure the slug doesn't already exist, or we
			// will get an error when we update the term
			if ( ! $post_slug = $post->post_name )
				$post_slug = sanitize_title ( $post->post_title );
			$pseudo_term = new stdClass();
			$pseudo_term->parent = 0;
			$pseudo_term->taxonomy = $taxonomy;
			$term_args = array( 'slug' => wp_unique_term_slug( $post_slug, $pseudo_term ) );
			$result = wp_insert_term( $post->post_title, $taxonomy, $term_args );
			if ( is_wp_error( $result ) ) {
				error_log( "SPTT ERROR Creating Term for '$post->post_title' in $taxonomy. Error: " . $result->get_error_message() . " and Args: " . print_r( $term_args, true ) );
			} else {
				update_post_meta( $post_id, '_sptt_term_id', $result[ 'term_id' ] );
			}
		}
		$this->syncing = false;
	}

	/**
	 * Handles terms being created or edited.
	 *
	 * @param int $term_id The term_id of the Term 
	 * @return void
	 * @author Simon Wheatley
	 **/
	function process_term( $term_id, $taxonomy ) {
		global $wpdb;
		if ( ! $this->do_sync() )
			return;
		$term = get_term( $term_id, $taxonomy );

		$post_id = $this->get_related_post_for_term( $term_id );
	
		$post_type = $this->taxonomy_syncs_with( $taxonomy );
		if ( ! $post_type )
			return;
		
		$post_status = apply_filters( 'sptt_post_status', 'publish', $term, $post_type, $post_id );
		$post_data = array(
			'post_name' => $term->slug,
			'post_status' => $post_status,
			'post_title' => $term->name,
			'post_type' => $post_type,
		);
		$this->syncing = true;
		if ( $post_id ) {
			// @TODO: If it does exist, make it unorphaned and notify the admin
			$post_data[ 'ID' ] = $post_id;
			$result = wp_update_post( $post_data );
			if ( ! $result )
				error_log( "SPTT Re-Publishing Error: with this post data: " . print_r( $post_data, true ) );
		} else {
			$result = wp_insert_post( $post_data, true );
			if ( is_wp_error( $result ) ) {
				error_log( "SPTT Creation Error: " . print_r( $result->get_error_messages(), true ) . " with this post data: " . print_r( $post_data, true ) );
			} else {
				add_post_meta( $result, '_sptt_term_id', $term_id );
			}
		}
		$this->syncing = false;
	}

	/**
	 * Get the post ID for the Post related to this Term.
	 *
	 * @param int $term_id The term_id of the Term to find the Post for
	 * @param boolean $single Whether to return one or many values
	 * @return int|array The Post ID or 0 if nothing, or an array of values if allowed by the $single param
	 * @author Simon Wheatley
	 **/
	function get_related_post_for_term( $term_id, $single = true ) {
		global $wpdb;

		// @TODO: Cache this value, as it's a fairly ugly DB query (with that CAST in there)

		$sql = " SELECT post_ID FROM $wpdb->postmeta WHERE $wpdb->postmeta.meta_key = '_sptt_term_id' AND CAST( $wpdb->postmeta.meta_value AS CHAR ) = %d ";
		$prepared_sql = $wpdb->prepare( $sql, $term_id );

		if ( $single )
			return (int) $wpdb->get_var( $prepared_sql );

		$values = (array) $wpdb->get_col( $prepared_sql );
		return array_map( 'absint', $values );
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
	function post_type_syncs_with( $post_type = null ) {
		if ( is_null( $post_type ) && ( is_singular() || is_single() || in_the_loop() ) ) {
			$post = get_post( get_the_ID() );
			$post_type = $post->post_type;
		}
		if ( is_null( $post_type ) )
			return false;
		$post_type = strtolower( $post_type );
		$synced = $this->get_synced();
		if ( isset( $synced[ $post_type ] ) )
			return $synced[ $post_type ];
		return false;
	}

	/**
	 * Returns the post_type that the passed taxonomy is 
	 * synced with.
	 *
	 * @param string $post_type The taxonomy which we are checking 
	 * @return string|boolean Either the name of the post_type, or false
	 **/
	function taxonomy_syncs_with( $taxonomy = null ) {
		if ( is_null( $taxonomy ) && is_tax() ) {
			if ( $term = get_queried_object() ) {
				$taxonomy = $term->taxonomy;
			}
		}
		if ( is_null( $taxonomy ) )
			return false;
		$taxonomy = strtolower( $taxonomy );
		$synced = $this->get_synced();
		if ( $post_type = array_search( $taxonomy, $synced ) )
			return $post_type;
		return false;
	}

	/**
	 * Determines whether a particular post_status for a particular
	 * post_type is synced.
	 *
	 * @param string $post_status The name of the post_status 
	 * @param string $post_type The name of the post_type 
	 * @return boolean True if it is synced
	 **/
	function post_status_synced( $post_status, $post_type ) {
		// The following filter is untested…
		$not_synced = apply_filters( 'sptt_post_status_synced', array( 'publish', 'draft' ), $post_type );
		return in_array( $post_status, $not_synced );
	}

	/**
	 * Returns an array where the keys represent a post type
	 * and the item a taxonomy to sync said post type to. All
	 * keys and items are lowercased.
	 *
	 * @return array The array of [ $post_type ] => $taxonomy
	 **/
	function get_synced() {
		$synced = apply_filters( 'sptt_sync', array() );
		foreach ( $synced as $post_type => $taxonomy ) {
			unset( $synced[ $post_type ] );
			$synced[ strtolower( $post_type ) ] = strtolower( $taxonomy );
		}
		return $synced;
	}

	/**
	 * Whether to do the sync or not. Checks for recursion and
	 * whether the WordPress importer is running.
	 *
	 * @return boolean True if it's OK to sync
	 **/
	function do_sync() {
		if ( $this->syncing )
			return false;
		if ( $this->importing )
			return false;
		return true;
	}

	/**
	 * Return the name of a post type of taxonomy.
	 *
	 * @param string $name The post type or taxonomy name to get the name label for 
	 * @param string $type Either 'post_type' or 'taxonomy'
	 * @return string The name from the labels for the taxonomy or post type
	 **/
	function get_label_name( $name, $type ) {
		if ( 'post_type' == $type ) {
			$pt_object = get_post_type_object( $name );
			return $pt_object->labels->name;
		}
		if ( 'taxonomy' == $type ) {
			$tax_object = get_taxonomy( $name );
			return $tax_object->labels->name;
		}
	}

} // END SPTT_Sync class 

global $sptt_sync;
$sptt_sync = new SPTT_Sync();

?>
