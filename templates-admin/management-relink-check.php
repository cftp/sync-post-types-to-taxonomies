<?php if ( ! defined( 'ABSPATH' ) ) { header( 'HTTP/1.0 403 Forbidden' ); die( 'Viewing separately is forbidden.' ); } ?>

<div class="wrap">
<?php screen_icon(); ?><h2><?php _e( 'Re-Link Individual Term to an Individual Post', 'sptt' ); ?></h2>

<h3><?php _e( 'Please check and approve your action below', 'sptt' ); ?></h3>

<div>
	<form action="" method="post" accept-charset="utf-8">
		
		<?php wp_nonce_field( 'link', '_sptt_nonce' ); ?>
		
		<input type="hidden" name="action"  value="do_link" />
		<input type="text" name="post_id" value="<?php echo esc_attr( $this->link[ 'post_id' ] ); ?>" />
		<input type="text" name="term_id" value="<?php echo esc_attr( $this->link[ 'term_id' ] ); ?>" />
		
		<?php if ( ! $post = get_post( $this->link[ 'post_id' ] ) ) : ?>
			
			<p><?php
					printf( 
						__( 
							'Please check the post ID %d exists before proceeding.', 
							'sptt' 
						), 
						$this->link[ 'post_id' ]
					);
			?></p>
			
		<?php endif; $taxonomy = sptt_post_type_syncs_with( $post->post_type ); ?>

		<?php 
			$taxonomy = sptt_post_type_syncs_with( $post->post_type );
			$term = get_term( $this->link[ 'term_id' ], $taxonomy );
			if ( is_wp_error( $term ) ) : ?>
			
				<p><?php
						printf( 
							__( 
								'Please check the term ID %1$d exists in taxonomy "%2$s" before proceeding, the error message given was "%3$s".', 
								'sptt' 
							), 
							$this->link[ 'term_id' ],
							$taxonomy,
							$term->get_error_message()
						);
				?></p>
			
		<?php elseif ( ! $term ) : ?>
		
			<p><?php
					printf( 
						__( 
							'Please check the term ID %1$d exists in taxonomy "%2$s" before proceeding.', 
							'sptt' 
						), 
						$this->link[ 'term_id' ],
						$taxonomy
					);
					$taxonomy = sptt_post_type_syncs_with( $post->post_type );
			?></p>
		
		<?php endif; ?>
		
		<?php if ( count( $this->link[ 'existing_term_ids' ] ) ) : ?>
		<p>
			<?php
				printf( 
					__( 
						'The existing post %1$s (ID, %2$d) is synchronised to these terms, <strong>these links will be broken</strong>:', 
						'sptt' 
					), 
					get_the_title( $this->link[ 'post_id' ] ),
					$this->link[ 'post_id' ]
				);
				$post = get_post( $this->link[ 'post_id' ] );
				$taxonomy = sptt_post_type_syncs_with( $post->post_type );
			?>
		</p>
		<ul class="sptt-links-to">
			<?php foreach ( $this->link[ 'existing_term_ids' ] as $term_id ) : $term = get_term( (int) $term_id, $taxonomy ); ?>
				<?php if ( is_wp_error( $term ) ) : ?>
					<li><?php printf( __( 'Could not retrieve term ID %1$d from taxonomy "%2$s", error was: %3$s', 'sptt' ), $term_id, $taxonomy, $term->get_error_message() ); ?></li>
				<?php else : ?>
					<li><?php printf( __( 'Term ID %1$d: %2$s (<a href="%3$s">edit</a>)', 'sptt' ), $term_id, $term->name, get_edit_term_link( $term_id, $taxonomy ) ); ?></li>
				<?php endif; ?>
			<?php endforeach; ?>
		</ul>
		<?php else : ?>
			<p><?php 
					printf(
						__( 'The existing post %1$s (ID, %2$d) is not synchronised to any terms.', 'sptt' ),
						get_the_title( $this->link[ 'post_id' ] ),
						$this->link[ 'post_id' ]
					);
				?></p>
		<?php endif; ?>

		<?php if ( count( $this->link[ 'existing_post_ids' ] ) ) : ?>
			<p>
				<?php
					$term = get_term( $this->link[ 'term_id' ], $taxonomy );
					printf( 
						__( 
							'The existing term %1$s (ID, %2$d) is synchronised to these posts, <strong>these links will be broken</strong>:', 
							'sptt' 
						), 
						$term->name,
						$this->link[ 'term_id' ]
					);
				?>
			</p>
			<ul class="sptt-links-to">
				<?php foreach ( $this->link[ 'existing_post_ids' ] as $post_id ) : $linked_post = get_post( $post_id ); ?>
					<?php if ( is_wp_error( $linked_post ) ) : ?>
						<li><?php printf( __( 'Could not retrieve post ID %1$d, error was: %2$s', 'sptt' ), $post_id, $linked_post->get_error_message() ); ?></li>
					<?php else : ?>
						<li><?php printf( __( 'Post ID %1$d: %2$s (<a href="%3$s">edit</a>)', 'sptt' ), $post_id, get_the_title( $post_id ), get_edit_post_link( $post_id ) ); ?></li>
					<?php endif; ?>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<p><?php 
					printf(
						__( 'The existing post %1$s (ID, %2$d) is not synchronised to any terms.', 'sptt' ),
						get_the_title( $this->link[ 'post_id' ] ),
						$this->link[ 'post_id' ]
					);
				?></p>
		<?php endif; ?>

		<p>
			<?php submit_button( __( 'Yes, re-link', 'sptt' ), 'primary', 'submit', false ); ?>
			&nbsp;
			<a href="<?php echo admin_url( '/tools.php?page=relink_sptt' ); ?>" class="button"><?php _e( 'No, do NOT re-link', 'sptt' ); ?></a>
		</p>

	</form>
</div>

</div><!-- .wrap -->