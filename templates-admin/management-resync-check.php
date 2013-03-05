<?php if ( ! defined( 'ABSPATH' ) ) { header( 'HTTP/1.0 403 Forbidden' ); die( 'Viewing separately is forbidden.' ); } ?>

<div class="wrap">
<?php screen_icon(); ?><h2><?php _e( 'Re-Sync Posts to Terms (or vice versa)', 'sptt' ); ?></h2>

<h3><?php _e( 'Please check and approve your action below', 'sptt' ); ?></h3>

<div>
	<form action="" method="post" accept-charset="utf-8">
		
		<?php wp_nonce_field( 'sync', '_sptt_nonce' ); ?>
		
		<input type="hidden" name="action"  value="do_sync" />
		<input type="hidden" name="from_type" value="<?php echo $this->sync[ 'from' ][ 'type' ]; ?>" />
		<input type="hidden" name="from_name" value="<?php echo $this->sync[ 'from' ][ 'name' ]; ?>" />
		<input type="hidden" name="to_type" value="<?php echo $this->sync[ 'to' ][ 'type' ]; ?>" />
		<input type="hidden" name="to_name" value="<?php echo $this->sync[ 'to' ][ 'name' ]; ?>" />
		
		<p>
			<?php
				printf( 
					_x( 
						'Wipe the %1$s called "%2$s", and re-synchronise it with the %3$s called "%4$s"?', 
						'For example: "Wipe the taxonomy called "Trees" and re-synchronise it with the post type called "Trees"?" or "Wipe the post type called "Mushrooms" and re-synchronise it with the taxonomy called "Fungi"?"',
						'sptt' 
					), 
					( 'taxonomy' == $this->sync[ 'to' ][ 'type' ] ) ? __( 'taxonomy', 'sptt' ) : __( 'post type', 'sptt' ),
					$this->get_label_name( $this->sync[ 'from' ][ 'name' ], $this->sync[ 'from' ][ 'type' ] ), 
					( 'taxonomy' == $this->sync[ 'from' ][ 'type' ] ) ? __( 'taxonomy', 'sptt' ) : __( 'post type', 'sptt' ), 
					$this->get_label_name( $this->sync[ 'to' ][ 'name' ], $this->sync[ 'to' ][ 'type' ] ) 
				);
			?>
			&nbsp;
			<?php submit_button( __( 'Yes, synchronise', 'sptt' ), 'primary', 'submit', false ); ?>
			&nbsp;
			<a href="<?php echo admin_url( '/tools.php?page=resync_sptt' ); ?>" class="button"><?php _e( 'No, do NOT synchronise', 'sptt' ); ?></a>
		</p>

	</form>
</div>

</div><!-- .wrap -->