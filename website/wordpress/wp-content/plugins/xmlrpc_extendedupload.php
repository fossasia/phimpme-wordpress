<?php
/*
Plugin Name:    XML-RPC Extended Media Upload
Plugin URI:     http://richardconsulting.ro/blog/2012/05/xml-rpc-extended-media-upload-plugin/
Description:    New XML-RPC Method: Upload a new media file with specified author and parent post.
Version:        0.1
Author:         Richard Vencu
Author URI:     http://richardconsulting.ro/
 */

add_filter( 'xmlrpc_methods', 'rv_rc_new_xmlrpc_methods' );
function rv_rc_new_xmlrpc_methods( $methods ) {
    $methods['wp_extended.uploadFile'] = 'rv_uploadFile';
    return $methods;
}
 
 /**
	 * Uploads a file, following your settings, //RV: Attaches the file to specified post_id, sets the author to specified user_id.
	 *
	 * Adapted from a patch by Johann Richard. //RV: Additions by Richard Vencu.
	 *
	 * @link http://mycvs.org/archives/2004/06/30/file-upload-to-wordpress-in-ecto/
	 *
	 * @since 3.x
	 *
	 * @param array $args Method parameters.
	 * @return array
	 */
	function rv_uploadFile($args) {
		global $wpdb;

		$blog_ID     = (int) $args[0];
		$username  = $wpdb->escape($args[1]);
		$password   = $wpdb->escape($args[2]);
		$data        = $args[3];

		$name = sanitize_file_name( $data['name'] );
		$type = $data['type'];
		$bits = $data['bits'];
		$author = $data['author']; //RV: added author (user_id of the author)
		$post_id = $data['parent']; //RV: added parent post to attach to (post_id of the parent)

		logIO('O', '(MW) Received '.strlen($bits).' bytes');

		if ( !$user = rv_login($username, $password) )
			return new IXR_Error(403, __('Bad login/pass combination.')); //RV: moved error message here
			
		//RV: if $author is empty fall back to logged in user
		if (empty($author))
			$author = $user->user_id;

		do_action('xmlrpc_call', 'metaWeblog.newMediaObject'); //RV: keeps backwards compatibility with the old method
		do_action('xmlrpc_call', 'wp_extended.uploadFile'); //RV: added new action hook for the extended method

		if ( !current_user_can('upload_files') ) {
			logIO('O', '(MW) User does not have upload_files capability');
			$error = new IXR_Error(401, __('You are not allowed to upload files to this site.'));
			return $error;
		}

		if ( $upload_err = apply_filters( 'pre_upload_error', false ) )
			return new IXR_Error(500, $upload_err);

		if ( !empty($data['overwrite']) && ($data['overwrite'] == true) ) {
			// Get postmeta info on the object.
			$old_file = $wpdb->get_row("
				SELECT ID
				FROM {$wpdb->posts}
				WHERE post_title = '{$name}'
					AND post_type = 'attachment'
			");

			// Delete previous file.
			wp_delete_attachment($old_file->ID);

			// Make sure the new name is different by pre-pending the
			// previous post id.
			$filename = preg_replace('/^wpid\d+-/', '', $name);
			$name = "wpid{$old_file->ID}-{$filename}";
		}

		$upload = wp_upload_bits($name, NULL, $bits);
		if ( ! empty($upload['error']) ) {
			$errorString = sprintf(__('Could not write file %1$s (%2$s)'), $name, $upload['error']);
			logIO('O', '(MW) ' . $errorString);
			return new IXR_Error(500, $errorString);
		}
		// Construct the attachment array
		// attach to post_id 0 //RV: only if parent is empty
		if ( empty ($post_id) ) //RV: 
			$post_id = 0;
		$attachment = array(
			'post_title' => $name,
			'post_content' => '',
			'post_type' => 'attachment',
			'post_parent' => $post_id,
			'post_mime_type' => $type,
			'guid' => $upload[ 'url' ],
			'post_author' => $author //RV: explicitly specifies the author
		);

		// Save the data
		$id = wp_insert_attachment( $attachment, $upload[ 'file' ], $post_id );
		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $upload['file'] ) );

		return apply_filters( 'wp_handle_upload', array( 'file' => $name, 'url' => $upload[ 'url' ], 'type' => $type ), 'upload' );
	}
	
	function rv_login($username, $password) {
		if ( !get_option( 'enable_xmlrpc' ) ) {
			$error = new IXR_Error( 405, sprintf( __( 'XML-RPC services are disabled on this site.  An admin user can enable them at %s'),  admin_url('options-writing.php') ) );
			return false;
		}

		$user = wp_authenticate($username, $password);

		if (is_wp_error($user)) {
			return false;
		}

		wp_set_current_user( $user->ID );
		return $user;
	}
	
	?>