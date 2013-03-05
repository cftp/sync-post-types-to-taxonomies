<?php if ( ! defined( 'ABSPATH' ) ) { header( 'HTTP/1.0 403 Forbidden' ); die( 'Viewing separately is forbidden.' ); } ?>

<div class="wrap">
<?php screen_icon(); ?><h2><?php _e( 'Re-Sync Posts to Terms (or vice versa)', 'sptt' ) ?></h2>

<h3><?php _e( 'Please check and approve your action below', 'sptt' ); ?></h3>

<p><?php _e( 'This screen allows you to completely resynchronise one of your post type to taxonomy (or vice versa) relationships. <strong>WARNING: this is a destructive action. The post type or taxonomy that you synchronise TO will be completely wiped and rebuilt from scratch, i.e. EVERYTHING in the post type or taxonomy you synchronise to will be deleted and YOU WILL LOSE ANY CHANGES YOU HAVE MADE FOREVER.</strong>', 'sptt' ); ?></p>

<div>
	<form action="" method="post" accept-charset="utf-8">
		
		<?php wp_nonce_field( 'check', '_sptt_nonce' ); ?>
		
		<input type="hidden" name="action" value="check_sync" id="action" />
		
		<p>
			<select name="sync_direction" id="sync_direction">
				<option value=""><?php _e( 'Please select your synchronisation:', 'sptt' ); ?></option>
				<?php foreach ( $synced as $post_type => $taxonomy ) : ?>
					<?php
						$pt_object = get_post_type_object( $post_type );
						$pt_name = $pt_object->labels->name;
						$tax_object = get_taxonomy( $taxonomy );
						$tax_name = $tax_object->labels->name;
					?>
					<option value="<?php echo esc_attr( "PT$post_type|TX$taxonomy" ); ?>"><?php printf( _x( '%1$s (post type) to %2$s (taxonomy)', 'For example, where you are syncing the "movie" post type to the "movie" taxonomy: "From movie (post type) to movie (taxonomy)"', 'sptt' ), $pt_name, $tax_name ); ?></option>
					<option value="<?php echo esc_attr( "TX$taxonomy|PT$post_type" ); ?>"><?php printf( _x( '%1$s (taxonomy) to %2$s (post type)', 'For example, where you are syncing the "movie" taxonomy to the "movie" post type: "From movie (taxonomy) to movie (post type)" (i.e. the vice versa of the previous translation)', 'sptt' ), $tax_name, $pt_name ); ?></option>
				<?php endforeach; ?>
			</select>

			<?php submit_button( __( 'Do sync', 'sptt' ), 'primary', 'submit', false ); ?>
		</p>

	</form>
</div>

</div><!-- .wrap -->