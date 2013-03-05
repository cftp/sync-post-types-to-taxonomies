<?php if ( ! defined( 'ABSPATH' ) ) { header( 'HTTP/1.0 403 Forbidden' ); die( 'Viewing separately is forbidden.' ); } ?>

<div class="wrap">
<?php screen_icon(); ?><h2><?php _e( 'Re-Link Individual Term to an Individual Post', 'sptt' ); ?></h2>

<h3><?php _e( 'Please set the term and post IDs below', 'sptt' ); ?></h3>

<p><?php _e( 'This screen allows you to re-link a term to a post. This is on a one by one basis, and any existing links on either the term or the post will be deleted and replaced with the term/post relationship you specify.', 'sptt' ); ?></p>

<div>
	<form action="" method="post" accept-charset="utf-8">
		
		<?php wp_nonce_field( 'check', '_sptt_nonce' ); ?>

		<input type="hidden" name="action" value="check_link" id="action" />

		<p>
			<span class="fake-label">Link the </span>
			<label for="post_id">post ID <input type="text" name="post_id" value="" id="post_id" class="small-text" /></label>
			<span class="fake-label">to the </span>
			<label for="term_id">term ID <input type="text" name="term_id" value="" id="term_id" class="small-text" /></label>
			<?php submit_button( __( 'Break existing link(s) and re-link', 'sptt' ), 'primary', 'submit', false ); ?>
		</p>

	</form>
</div>

</div><!-- .wrap -->