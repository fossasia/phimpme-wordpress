<?php

include_once( ABSPATH . WPINC . '/class-IXR.php' );
include_once( ABSPATH . WPINC . '/class-wp-xmlrpc-server.php' );
include_once( ABSPATH . WPINC . '/post-thumbnail-template.php' );

class wp_xmlrpc_server_ext extends wp_xmlrpc_server {

	function __construct() {
		// hook filter to add the new methods after the existing ones are added in the parent constructor
		add_filter( 'xmlrpc_methods' , array( &$this, 'wxm_xmlrpc_methods' ) );

		// add new options
		add_filter( 'xmlrpc_blog_options', array( &$this, 'wxm_blog_options' ) );

		parent::__construct();
	}

	function wxm_xmlrpc_methods( $methods ) {
		$new_methods = array();

		// user management
		$new_methods['wp.newUser']          = array( &$this, 'wp_newUser' );
		$new_methods['wp.editUser']         = array( &$this, 'wp_editUser' );
		$new_methods['wp.deleteUser']       = array( &$this, 'wp_deleteUser' );
		$new_methods['wp.getUser']          = array( &$this, 'wp_getUser' );
		$new_methods['wp.getUsers']         = array( &$this, 'wp_getUsers' );
		$new_methods['wp.getProfile']       = array( &$this, 'wp_getProfile' );
		$new_methods['wp.editProfile']      = array( &$this, 'wp_editProfile' );

		// custom post type management
		$new_methods['wp.newPost']          = array( &$this, 'wp_newPost' );
		$new_methods['wp.editPost']         = array( &$this, 'wp_editPost' );
		$new_methods['wp.deletePost']       = array( &$this, 'wp_deletePost' );
		$new_methods['wp.getPost']          = array( &$this, 'wp_getPost' );
		$new_methods['wp.getPosts']         = array( &$this, 'wp_getPosts' );
		$new_methods['wp.getPostType']      = array( &$this, 'wp_getPostType' );
		$new_methods['wp.getPostTypes']     = array( &$this, 'wp_getPostTypes' );
		$new_methods['wp.getRevisions']     = array( &$this, 'wp_getRevisions' );
		$new_methods['wp.restoreRevision']  = array( &$this, 'wp_restoreRevision' );

		// custom taxonomy management
		$new_methods['wp.newTerm']          = array( &$this, 'wp_newTerm' );
		$new_methods['wp.editTerm']         = array( &$this, 'wp_editTerm' );
		$new_methods['wp.deleteTerm']       = array( &$this, 'wp_deleteTerm' );
		$new_methods['wp.getTerm']          = array( &$this, 'wp_getTerm' );
		$new_methods['wp.getTerms']         = array( &$this, 'wp_getTerms' );
		$new_methods['wp.getTaxonomy']      = array( &$this, 'wp_getTaxonomy' );
		$new_methods['wp.getTaxonomies']    = array( &$this, 'wp_getTaxonomies' );

		global $wp_version;
		$version_bits = explode( '-', $wp_version );
		$version = $version_bits[0];
		if ( $version < 3.4 ) {
			// for pre-3.4, explicitly override the core implementation.
			$methods['wp.getMediaItem'] = array( &$this, 'wxm_wp_getMediaItem' );
			$methods['wp.getMediaLibrary'] = array( &$this, 'wxm_wp_getMediaLibrary' );
		}
		if ( $version < 3.5 ) {
			// for pre-3.5, explicitly override the core implementation of uploads and posts
			$methods['wp.uploadFile'] = array( &$this, 'wxm_wp_uploadFile' );
			$methods['metaWeblog.newMediaObject'] = array( &$this, 'wxm_wp_uploadFile' );

			$methods['wp.newPost'] = array( &$this, 'wxm_wp_newPost' );
			$methods['wp.editPost'] = array( &$this, 'wxm_wp_editPost' );
			$methods['wp.getPosts'] = array( &$this, 'wxm_wp_getPosts' );
			$methods['wp.getPost'] = array( &$this, 'wxm_wp_getPost' );
		}

		// array_merge will take the values defined in later arguments, so
		// the plugin will not overwrite any methods defined by WP core
		// (i.e., plugin will be forward-compatible with future releases of WordPress
		//  that include these methods built-in)
		return array_merge( $new_methods, $methods );
	}

	function __call( $method, $args ) {
		// depending on the version of WordPress, some methods in this class are already defined
		// by the parent class. to avoid overriding the core versions, all methods in this class are
		// namespaced. however, if the parent class is missing some methods, we intercept the call
		// here and instead pass to the namespaced plugin version.
		$wxm_method = 'wxm_' . ltrim( $method, "_" );
		if ( method_exists( $this, $wxm_method ) )
			return call_user_func_array( array( $this, $wxm_method ), $args );

		throw new Exception ( 'Call to undefined class method: ' . $method );
	}

	/**
	 * Checks if the method received at least the minimum number of arguments.
	 *
	 * @param string|array $args Sanitize single string or array of strings.
	 * @param int $count Minimum number of arguments.
	 * @return boolean if $args contains at least $count arguments.
	 */
	protected function wxm_minimum_args( $args, $count ) {
		if ( count( $args ) < $count ) {
			$this->error = new IXR_Error( 400, __( 'Insufficient arguments passed to this XML-RPC method.' ) );
			return false;
		}

		return true;
	}

	/**
	 * Prepares user data for return in an XML-RPC object.
	 *
	 * @access protected
	 *
	 * @param WP_User $user The unprepared user object
	 * @param array $fields The subset of user fields to return
	 * @return array The prepared user data
	 */
	protected function wxm_prepare_user( $user, $fields ) {
		$_user = array( 'user_id' => strval( $user->ID ) );

		$user_fields = array(
			'username'          => $user->user_login,
			'first_name'        => $user->user_firstname,
			'last_name'         => $user->user_lastname,
			'registered'        => $this->_convert_date( $user->user_registered ),
			'bio'               => $user->user_description,
			'email'             => $user->user_email,
			'nickname'          => $user->nickname,
			'nicename'          => $user->user_nicename,
			'url'               => $user->user_url,
			'display_name'      => $user->display_name,
			'roles'             => $user->roles,
		);

		if ( in_array( 'all', $fields ) ) {
			$_user = array_merge( $_user, $user_fields );
		} else {
			if ( in_array( 'basic', $fields ) ) {
				$basic_fields = array( 'username', 'email', 'registered', 'display_name', 'nicename' );
				$fields = array_merge( $fields, $basic_fields );
			}
			$requested_fields = array_intersect_key( $user_fields, array_flip( $fields ) );
			$_user = array_merge( $_user, $requested_fields );
		}

		return apply_filters( 'xmlrpc_prepare_user', $_user, $user, $fields );
	}

	/**
	 * Convert a WordPress date string to an IXR_Date object.
	 *
	 * @access protected
	 *
	 * @param $date
	 * @return IXR_Date
	 */
	protected function wxm_convert_date( $date ) {
		if ( $date === '0000-00-00 00:00:00' ) {
			return new IXR_Date( '00000000T00:00:00Z' );
		}
		return new IXR_Date( mysql2date( 'Ymd\TH:i:s', $date, false ) );
	}

	/**
	 * Convert a WordPress gmt date string to an IXR_Date object.
	 *
	 * @access protected
	 *
	 * @param $date
	 * @return IXR_Date
	 */
	protected function wxm_convert_date_gmt( $date_gmt, $date ) {
		if ( $date !== '0000-00-00 00:00:00' && $date_gmt === '0000-00-00 00:00:00' ) {
			return new IXR_Date( get_gmt_from_date( mysql2date( 'Y-m-d H:i:s', $date, false ), 'Ymd\TH:i:s' ) );
		}
		return $this->_convert_date( $date_gmt );
	}

	/**
	 * Prepares post data for return in an XML-RPC object.
	 *
	 * @access protected
	 *
	 * @param array $post The unprepared post data
	 * @param array $fields The subset of post type fields to return
	 * @return array The prepared post data
	 */
	protected function wxm_prepare_post( $post, $fields ) {
		// holds the data for this post. built up based on $fields
		$_post = array( 'post_id' => strval( $post['ID'] ) );

		// prepare common post fields
		$post_fields = array(
			'post_title'        => $post['post_title'],
			'post_date'         => $this->_convert_date( $post['post_date'] ),
			'post_date_gmt'     => $this->_convert_date_gmt( $post['post_date_gmt'], $post['post_date'] ),
			'post_modified'     => $this->_convert_date( $post['post_modified'] ),
			'post_modified_gmt' => $this->_convert_date_gmt( $post['post_modified_gmt'], $post['post_modified'] ),
			'post_status'       => $post['post_status'],
			'post_type'         => $post['post_type'],
			'post_name'         => $post['post_name'],
			'post_author'       => $post['post_author'],
			'post_password'     => $post['post_password'],
			'post_excerpt'      => $post['post_excerpt'],
			'post_content'      => $post['post_content'],
			'post_parent'       => strval( $post['post_parent'] ),
			'post_mime_type'    => $post['post_mime_type'],
			'link'              => post_permalink( $post['ID'] ),
			'guid'              => $post['guid'],
			'menu_order'        => intval( $post['menu_order'] ),
			'comment_status'    => $post['comment_status'],
			'ping_status'       => $post['ping_status'],
			'sticky'            => ( $post['post_type'] === 'post' && is_sticky( $post['ID'] ) ),
		);

		// Thumbnail
		$post_fields['post_thumbnail'] = array();
		$thumbnail_id = get_post_thumbnail_id( $post['ID'] );
		if ( $thumbnail_id ) {
			$thumbnail_size = current_theme_supports('post-thumbnail') ? 'post-thumbnail' : 'thumbnail';
			$post_fields['post_thumbnail'] = $this->_prepare_media_item( get_post( $thumbnail_id ), $thumbnail_size );
		}

		// Consider future posts as published
		if ( $post_fields['post_status'] === 'future' )
			$post_fields['post_status'] = 'publish';

		// Fill in blank post format
		$post_fields['post_format'] = get_post_format( $post['ID'] );
		if ( empty( $post_fields['post_format'] ) )
			$post_fields['post_format'] = 'standard';

		// Merge requested $post_fields fields into $_post
		if ( in_array( 'post', $fields ) ) {
			$_post = array_merge( $_post, $post_fields );
		} else {
			$requested_fields = array_intersect_key( $post_fields, array_flip( $fields ) );
			$_post = array_merge( $_post, $requested_fields );
		}

		$all_taxonomy_fields = in_array( 'taxonomies', $fields );

		if ( $all_taxonomy_fields || in_array( 'terms', $fields ) ) {
			$post_type_taxonomies = get_object_taxonomies( $post['post_type'], 'names' );
			$terms = wp_get_object_terms( $post['ID'], $post_type_taxonomies );
			$_post['terms'] = array();
			foreach ( $terms as $term ) {
				$_post['terms'][] = $this->_prepare_term( $term );
			}
		}

		if ( in_array( 'custom_fields', $fields ) )
			$_post['custom_fields'] = $this->get_custom_fields( $post['ID'] );

		if ( in_array( 'enclosure', $fields ) ) {
			$_post['enclosure'] = array();
			$enclosures = (array) get_post_meta( $post['ID'], 'enclosure' );
			if ( ! empty( $enclosures ) ) {
				$encdata = explode( "\n", $enclosures[0] );
				$_post['enclosure']['url'] = trim( htmlspecialchars( $encdata[0] ) );
				$_post['enclosure']['length'] = (int) trim( $encdata[1] );
				$_post['enclosure']['type'] = trim( $encdata[2] );
			}
		}

		return apply_filters( 'xmlrpc_prepare_post', $_post, $post, $fields );
	}

	/**
	 * Prepares taxonomy data for return in an XML-RPC object.
	 *
	 * @access protected
	 *
	 * @param object $taxonomy The unprepared taxonomy data
	 * @param array $fields The subset of taxonomy fields to return
	 * @return array The prepared taxonomy data
	 */
	protected function wxm_prepare_taxonomy( $taxonomy, $fields ) {
		$_taxonomy = array(
			'name' => $taxonomy->name,
			'label' => $taxonomy->label,
			'hierarchical' => (bool) $taxonomy->hierarchical,
			'public' => (bool) $taxonomy->public,
			'show_ui' => (bool) $taxonomy->show_ui,
			'_builtin' => (bool) $taxonomy->_builtin,
		);

		if ( in_array( 'labels', $fields ) )
			$_taxonomy['labels'] = (array) $taxonomy->labels;

		if ( in_array( 'cap', $fields ) )
			$_taxonomy['cap'] = (array) $taxonomy->cap;

		if ( in_array( 'object_type', $fields ) )
			$_taxonomy['object_type'] = array_unique( (array) $taxonomy->object_type );

		return apply_filters( 'xmlrpc_prepare_taxonomy', $_taxonomy, $taxonomy, $fields );
	}

	/**
	 * Prepares term data for return in an XML-RPC object.
	 *
	 * @access protected
	 *
	 * @param array|object $term The unprepared term data
	 * @return array The prepared term data
	 */
	protected function wxm_prepare_term( $term ) {
		$_term = $term;
		if ( ! is_array( $_term) )
			$_term = get_object_vars( $_term );

		// For Intergers which may be largeer than XMLRPC supports ensure we return strings.
		$_term['term_id'] = strval( $_term['term_id'] );
		$_term['term_group'] = strval( $_term['term_group'] );
		$_term['term_taxonomy_id'] = strval( $_term['term_taxonomy_id'] );
		$_term['parent'] = strval( $_term['parent'] );

		// Count we are happy to return as an Integer because people really shouldn't use Terms that much.
		$_term['count'] = intval( $_term['count'] );

		return apply_filters( 'xmlrpc_prepare_term', $_term, $term );
	}

	/**
	 * Get all the post type features
	 *
	 * @param string $post_type The post type
	 * @return array
	 */
	private function wxm_get_all_post_type_supports( $post_type ) {
		if ( function_exists( 'get_all_post_type_supports' ) )
			return get_all_post_type_supports( $post_type );

		global $_wp_post_type_features;

		if ( isset( $_wp_post_type_features[$post_type] ) )
			return $_wp_post_type_features[$post_type];

		return array();
	}

	/**
	 * Prepares post data for return in an XML-RPC object.
	 *
	 * @access protected
	 *
	 * @param object $post_type Post type object
	 * @param array $fields The subset of post fields to return
	 * @return array The prepared post type data
	 */
	protected function wxm_prepare_post_type( $post_type, $fields ) {
		$_post_type = array(
			'name' => $post_type->name,
			'label' => $post_type->label,
			'hierarchical' => (bool) $post_type->hierarchical,
			'public' => (bool) $post_type->public,
			'show_ui' => (bool) $post_type->show_ui,
			'_builtin' => (bool) $post_type->_builtin,
			'has_archive' => (bool) $post_type->has_archive,
			'supports' => $this->wxm_get_all_post_type_supports( $post_type->name ),
		);

		if ( in_array( 'labels', $fields ) ) {
			$_post_type['labels'] = (array) $post_type->labels;
		}

		if ( in_array( 'cap', $fields ) ) {
			$_post_type['cap'] = (array) $post_type->cap;
			$_post_type['map_meta_cap'] = (bool) $post_type->map_meta_cap;
		}

		if ( in_array( 'menu', $fields ) ) {
			$_post_type['menu_position'] = (int) $post_type->menu_position;
			$_post_type['menu_icon'] = $post_type->menu_icon;
			$_post_type['show_in_menu'] = (bool) $post_type->show_in_menu;
		}

		if ( in_array( 'taxonomies', $fields ) )
			$_post_type['taxonomies'] = get_object_taxonomies( $post_type->name, 'names' );

		return apply_filters( 'xmlrpc_prepare_post_type', $_post_type, $post_type );
	}

	/**
	 * Prepares media item data for return in an XML-RPC object.
	 *
	 * @access protected
	 *
	 * @param object $media_item The unprepared media item data
	 * @param string $thumbnail_size The image size to use for the thumbnail URL
	 * @return array The prepared media item data
	 */
	protected function wxm_prepare_media_item( $media_item, $thumbnail_size='thumbnail' ) {
		$_media_item = array(
			'attachment_id'    => strval( $media_item->ID ),
			'date_created_gmt' => $this->_convert_date_gmt( $media_item->post_date_gmt, $media_item->post_date ),
			'parent'           => $media_item->post_parent,
			'link'             => wp_get_attachment_url( $media_item->ID ),
			'title'            => $media_item->post_title,
			'caption'          => $media_item->post_excerpt,
			'description'      => $media_item->post_content,
			'metadata'         => wp_get_attachment_metadata( $media_item->ID ),
		);

		$thumbnail_src = image_downsize( $media_item->ID, $thumbnail_size );
		if ( $thumbnail_src )
			$_media_item['thumbnail'] = $thumbnail_src[0];
		else
			$_media_item['thumbnail'] = $_media_item['link'];

		return apply_filters( 'xmlrpc_prepare_media_item', $_media_item, $media_item, $thumbnail_size );
	}

	/**
	 * Create a new user.
	 *
	 * @uses wp_insert_user()
	 * @param array $args Method parameters. Contains:
	 *  - int     $blog_id
	 *  - string  $username
	 *  - string  $password
	 *  - array   $content_struct
	 *      The $content_struct must contain:
	 *      - 'username'
	 *      - 'password'
	 *      - 'email'
	 *      Also, it can optionally contain:
	 *      - 'role'
	 *      - 'first_name'
	 *      - 'last_name'
	 *      - 'url'
	 *      - 'display_name'
	 *      - 'nickname'
	 *      - 'nicename'
	 *      - 'bio'
	 *  - boolean $send_mail optional. Defaults to false
	 * @return int user_id
	 */
	function wxm_wp_newUser( $args ) {
		if ( ! $this->minimum_args( $args, 4 ) )
			return $this->error;

		$this->escape( $args );

		$blog_id        = (int) $args[0];
		$username       = $args[1];
		$password       = $args[2];
		$content_struct = $args[3];
		$send_mail      = isset( $args[4] ) ? $args[4] : false;

		if ( ! $user = $this->login( $username, $password ) )
			return $this->error;

		do_action( 'xmlrpc_call', 'wp.newUser' );

		if ( ! current_user_can( 'create_users' ) )
			return new IXR_Error( 401, __( 'You are not allowed to create users.' ) );

		// this hold all the user data
		$user_data = array();

		if ( empty( $content_struct['username'] ) )
			return new IXR_Error( 403, __( 'Username cannot be empty.' ) );
		$user_data['user_login'] = $content_struct['username'];

		if ( empty( $content_struct['password'] ) )
			return new IXR_Error( 403, __( 'Password cannot be empty.' ) );
		$user_data['user_pass'] = $content_struct['password'];

		if ( empty( $content_struct['email'] ) )
			return new IXR_Error( 403, __( 'Email cannot be empty.' ) );

		if ( ! is_email( $content_struct['email'] ) )
			return new IXR_Error( 403, __( 'This email address is not valid.' ) );

		if ( email_exists( $content_struct['email'] ) )
			return new IXR_Error( 403, __( 'This email address is already registered.' ) );

		$user_data['user_email'] = $content_struct['email'];

		if ( isset( $content_struct['role'] ) ) {
			if ( get_role( $content_struct['role'] ) === null )
				return new IXR_Error( 403, __( 'The role specified is not valid.' ) );

			$user_data['role'] = $content_struct['role'];
		}

		if ( isset( $content_struct['first_name'] ) )
			$user_data['first_name'] = $content_struct['first_name'];

		if ( isset( $content_struct['last_name'] ) )
			$user_data['last_name'] = $content_struct['last_name'];

		if ( isset( $content_struct['url'] ) )
			$user_data['user_url'] = $content_struct['url'];

		if ( isset( $content_struct['display_name'] ) )
			$user_data['display_name'] = $content_struct['display_name'];

		if ( isset( $content_struct['nickname'] ) )
			$user_data['nickname'] = $content_struct['nickname'];

		if ( isset( $content_struct['nicename'] ) )
			$user_data['user_nicename'] = $content_struct['nicename'];

		if ( isset( $content_struct['bio'] ) )
			$user_data['description'] = $content_struct['bio'];

		$user_id = wp_insert_user( $user_data );

		if ( is_wp_error( $user_id ) )
			return new IXR_Error( 500, $user_id->get_error_message() );

		if ( ! $user_id )
			return new IXR_Error( 500, __( 'Sorry, the new user creation failed.' ) );

		if ( $send_mail ) {
			wp_new_user_notification( $user_id, $user_data['user_pass'] );
		}

		return $user_id;
	}

	/**
	 * Edit a user.
	 *
	 * @uses wp_update_user()
	 * @param array $args Method parameters. Contains:
	 *  - int     $blog_id
	 *  - string  $username
	 *  - string  $password
	 *  - int     $user_id
	 *  - array   $content_struct
	 *      It can optionally contain:
	 *      - 'email'
	 *      - 'first_name'
	 *      - 'last_name'
	 *      - 'website'
	 *      - 'role'
	 *      - 'display_name'
	 *      - 'nickname'
	 *      - 'nicename'
	 *      - 'bio'
	 *      - 'usercontacts'
	 *      - 'password'
	 *  - boolean $send_mail optional. Defaults to false
	 * @return bool True, on success.
	 */
	function wxm_wp_editUser( $args ) {
		if ( ! $this->minimum_args( $args, 5 ) )
			return $this->error;

		$this->escape( $args );

		$blog_id        = (int) $args[0];
		$username       = $args[1];
		$password       = $args[2];
		$user_id        = (int) $args[3];
		$content_struct = $args[4];

		if ( ! $user = $this->login( $username, $password ) )
			return $this->error;

		do_action( 'xmlrpc_call', 'wp.editUser' );

		$user_info = get_userdata( $user_id );

		if ( ! $user_info )
			return new IXR_Error( 404, __( 'Invalid user ID.' ) );

		if ( ! ( $user_id == $user->ID || current_user_can( 'edit_users' ) ) )
			return new IXR_Error( 401, __( 'Sorry, you cannot edit this user.' ) );

		// holds data of the user
		$user_data = array();
		$user_data['ID'] = $user_id;

		if ( isset( $content_struct['username'] ) && $content_struct['username'] !== $user_info->user_login )
			return new IXR_Error( 401, __( 'Username cannot be changed.' ) );

		if ( isset( $content_struct['email'] ) ) {
			if ( ! is_email( $content_struct['email'] ) )
				return new IXR_Error( 403, __( 'This email address is not valid.' ) );

			// check whether it is already registered
			if ( $content_struct['email'] !== $user_info->user_email && email_exists( $content_struct['email'] ) )
				return new IXR_Error( 403, __( 'This email address is already registered.' ) );

			$user_data['user_email'] = $content_struct['email'];
		}

		if ( isset( $content_struct['role'] ) ) {
			if ( ! current_user_can( 'edit_users' ) )
				return new IXR_Error( 401, __( 'You are not allowed to change roles for this user.' ) );

			if ( get_role( $content_struct['role'] ) === null )
				return new IXR_Error( 403, __( 'The role specified is not valid' ) );

			$user_data['role'] = $content_struct['role'];
		}

		// only set the user details if it was given
		if ( isset( $content_struct['first_name'] ) )
			$user_data['first_name'] = $content_struct['first_name'];

		if ( isset( $content_struct['last_name'] ) )
			$user_data['last_name'] = $content_struct['last_name'];

		if ( isset( $content_struct['website'] ) )
			$user_data['user_url'] = $content_struct['url'];

		if ( isset( $content_struct['display_name'] ) )
			$user_data['display_name'] = $content_struct['display_name'];

		if ( isset( $content_struct['nickname'] ) )
			$user_data['nickname'] = $content_struct['nickname'];

		if ( isset( $content_struct['nicename'] ) )
			$user_data['user_nicename'] = $content_struct['nicename'];

		if ( isset( $content_struct['bio'] ) )
			$user_data['description'] = $content_struct['bio'];

		if ( isset( $content_struct['user_contacts'] ) ) {
			$user_contacts = _wp_get_user_contactmethods( $user_data );
			foreach ( $content_struct['user_contacts'] as $key => $value ) {
				if ( ! array_key_exists( $key, $user_contacts ) )
					return new IXR_Error( 403, __( 'One of the contact method specified is not valid' ) );

				$user_data[ $key ] = $value;
			}
		}

		if ( isset ( $content_struct['password'] ) )
			$user_data['user_pass'] = $content_struct['password'];

		$result = wp_update_user( $user_data );

		if ( is_wp_error( $result ) )
			return new IXR_Error( 500, $result->get_error_message() );

		if ( ! $result )
			return new IXR_Error( 500, __( 'Sorry, the user cannot be updated.' ) );

		return true;
	}

	/**
	 * Delete a user.
	 *
	 * @uses wp_delete_user()
	 * @param array $args Method parameters. Contains:
	 *  - int     $blog_id
	 *  - string  $username
	 *  - string  $password
	 *  - int     $user_id
	 * @return True when user is deleted.
	 */
	function wxm_wp_deleteUser( $args ) {
		if ( ! $this->minimum_args( $args, 4 ) )
			return $this->error;

		$this->escape( $args );

		$blog_id    = (int) $args[0];
		$username   = $args[1];
		$password   = $args[2];
		$user_id    = (int) $args[3];

		if ( ! $user = $this->login( $username, $password ) )
			return $this->error;

		do_action( 'xmlrpc_call', 'wp.deleteUser' );

		if ( ! current_user_can( 'delete_users' ) )
			return new IXR_Error( 401, __( 'You are not allowed to delete users.' ) );

		if ( ! get_userdata( $user_id ) )
			return new IXR_Error( 404, __( 'Invalid user ID.' ) );

		if ( $user->ID == $user_id )
			return new IXR_Error( 401, __( 'You cannot delete yourself.' ) );

		$reassign_id = 'novalue';
		if ( isset( $args[4] ) ) {
			$reassign_id = (int) $args[4];

			if ( ! get_userdata( $reassign_id ) )
				return new IXR_Error( 404, __( 'Invalid reassign user ID.' ) );

			if ( $reassign_id === $user_id )
				return new IXR_Error( 401, __( 'You cannot reassign to the user being deleted.' ) );
		}

		return wp_delete_user( $user_id, $reassign_id );
	}

	/**
	 * Retrieve a user.
	 *
	 * The optional $fields parameter specifies what fields will be included
	 * in the response array. This should be a list of field names. 'user_id' will
	 * always be included in the response regardless of the value of $fields.
	 *
	 * Instead of, or in addition to, individual field names, conceptual group
	 * names can be used to specify multiple fields. The available conceptual
	 * groups are 'basic' and 'all'.
	 *
	 * @uses get_userdata()
	 * @param array $args Method parameters. Contains:
	 *  - int     $blog_id
	 *  - string  $username
	 *  - string  $password
	 *  - int     $user_id
	 *  - array   $fields optional
	 * @return array contains (based on $fields parameter):
	 *  - 'user_id'
	 *  - 'username'
	 *  - 'first_name'
	 *  - 'last_name'
	 *  - 'registered'
	 *  - 'bio'
	 *  - 'email'
	 *  - 'nickname'
	 *  - 'nicename'
	 *  - 'url'
	 *  - 'display_name'
	 *  - 'roles'
	 */
	function wxm_wp_getUser( $args ) {
		if ( ! $this->minimum_args( $args, 4 ) )
			return $this->error;

		$this->escape( $args );

		$blog_id    = (int) $args[0];
		$username   = $args[1];
		$password   = $args[2];
		$user_id    = (int) $args[3];

		if ( isset( $args[4] ) )
			$fields = $args[4];
		else
			$fields = apply_filters( 'xmlrpc_default_user_fields', array( 'all' ), 'wp.getUser' );

		if ( ! $user = $this->login( $username, $password ) )
			return $this->error;

		do_action( 'xmlrpc_call', 'wp.getUser' );

		if ( ! current_user_can( 'edit_user', $user_id ) )
			return new IXR_Error( 401, __( 'Sorry, you cannot edit users.' ) );

		$user_data = get_userdata( $user_id );

		if ( ! $user_data )
			return new IXR_Error( 404, __( 'Invalid user ID' ) );

		return $this->_prepare_user( $user_data, $fields );
	}

	/**
	 * Retrieve users.
	 *
	 * The optional $filter parameter modifies the query used to retrieve users.
	 * Accepted keys are 'number' (default: 50), 'offset' (default: 0), 'role',
	 * 'who', 'orderby', and 'order'.
	 *
	 * The optional $fields parameter specifies what fields will be included
	 * in the response array.
	 *
	 * @uses get_users()
	 * @see wp_getUser() for more on $fields and return values
	 *
	 * @param array $args Method parameters. Contains:
	 *  - int     $blog_id
	 *  - string  $username
	 *  - string  $password
	 *  - array   $filter optional
	 *  - array   $fields optional
	 * @return array users data
	 */
	function wxm_wp_getUsers( $args ) {
		if ( ! $this->minimum_args( $args, 3 ) )
			return $this->error;

		$this->escape( $args );

		$blog_id    = (int) $args[0];
		$username   = $args[1];
		$password   = $args[2];
		$filter     = isset( $args[3] ) ? $args[3] : array();

		if ( isset( $args[4] ) )
			$fields = $args[4];
		else
			$fields = apply_filters( 'xmlrpc_default_user_fields', array( 'all' ), 'wp.getUsers' );

		if ( ! $user = $this->login( $username, $password ) )
			return $this->error;

		do_action( 'xmlrpc_call', 'wp.getUsers' );

		if ( ! current_user_can( 'list_users' ) )
			return new IXR_Error( 401, __( 'Sorry, you cannot list users.' ) );

		$query = array( 'fields' => 'all_with_meta' );

		$query['number'] = ( isset( $filter['number'] ) ) ? absint( $filter['number'] ) : 50;
		$query['offset'] = ( isset( $filter['offset'] ) ) ? absint( $filter['offset'] ) : 0;

		if ( isset( $filter['orderby'] ) ) {
			$query['orderby'] = $filter['orderby'];

			if ( isset( $filter['order'] ) )
				$query['order'] = $filter['order'];
		}

		if ( isset( $filter['role'] ) ) {
			if ( get_role( $filter['role'] ) === null )
				return new IXR_Error( 403, __( 'The role specified is not valid' ) );

			$query['role'] = $filter['role'];
		}

		if ( isset( $filter['who'] ) ) {
			$query['who'] = $filter['who'];
		}

		$users = get_users( $query );

		$_users = array();
		foreach ( $users as $user_data ) {
			if ( current_user_can( 'edit_user', $user_data->ID ) )
				$_users[] = $this->_prepare_user( $user_data, $fields );
		}
		return $_users;
	}

	/**
	 * Retrieve information about the requesting user.
	 *
	 * @uses get_userdata()
	 * @param array $args Method parameters. Contains:
	 *  - int     $blog_id
	 *  - string  $username
	 *  - string  $password
	 *  - array   $fields optional
	 * @return array (@see wp_getUser)
	 */
	function wxm_wp_getProfile( $args ) {
		if ( ! $this->minimum_args( $args, 3 ) )
			return $this->error;

		$this->escape( $args );

		$blog_id    = (int) $args[0];
		$username   = $args[1];
		$password   = $args[2];

		if ( isset( $args[3] ) )
			$fields = $args[3];
		else
			$fields = apply_filters( 'xmlrpc_default_user_fields', array( 'all' ), 'wp.getProfile' );

		if ( ! $user = $this->login( $username, $password ) )
			return $this->error;

		do_action( 'xmlrpc_call', 'wp.getProfile' );

		if ( ! current_user_can( 'edit_user', $user->ID ) )
			return new IXR_Error( 401, __( 'Sorry, you cannot edit your profile.' ) );

		$user_data = get_userdata( $user->ID );

		return $this->_prepare_user( $user_data, $fields );
	}

	/**
	 * Edit user's profile.
	 *
	 * @uses wp_update_user()
	 * @param array $args Method parameters. Contains:
	 *  - int     $blog_id
	 *  - string  $username
	 *  - string  $password
	 *  - array   $content_struct
	 *      It can optionally contain:
	 *      - 'first_name'
	 *      - 'last_name'
	 *      - 'website'
	 *      - 'display_name'
	 *      - 'nickname'
	 *      - 'nicename'
	 *      - 'bio'
	 * @return bool True, on success.
	 */
	function wxm_wp_editProfile( $args ) {
		if ( ! $this->minimum_args( $args, 4 ) )
			return $this->error;

		$this->escape( $args );

		$blog_id        = (int) $args[0];
		$username       = $args[1];
		$password       = $args[2];
		$content_struct = $args[3];

		if ( ! $user = $this->login( $username, $password ) )
			return $this->error;

		do_action( 'xmlrpc_call', 'wp.editProfile' );

		if ( ! current_user_can( 'edit_user', $user->ID ) )
			return new IXR_Error( 401, __( 'Sorry, you cannot edit your profile.' ) );

		// holds data of the user
		$user_data = array();
		$user_data['ID'] = $user->ID;

		// only set the user details if it was given
		if ( isset( $content_struct['first_name'] ) )
			$user_data['first_name'] = $content_struct['first_name'];

		if ( isset( $content_struct['last_name'] ) )
			$user_data['last_name'] = $content_struct['last_name'];

		if ( isset( $content_struct['url'] ) )
			$user_data['user_url'] = $content_struct['url'];

		if ( isset( $content_struct['display_name'] ) )
			$user_data['display_name'] = $content_struct['display_name'];

		if ( isset( $content_struct['nickname'] ) )
			$user_data['nickname'] = $content_struct['nickname'];

		if ( isset( $content_struct['nicename'] ) )
			$user_data['user_nicename'] = $content_struct['nicename'];

		if ( isset( $content_struct['bio'] ) )
			$user_data['description'] = $content_struct['bio'];

		$result = wp_update_user( $user_data );

		if ( is_wp_error( $result ) )
			return new IXR_Error( 500, $result->get_error_message() );

		if ( ! $result )
			return new IXR_Error( 500, __( 'Sorry, the user cannot be updated.' ) );

		return true;
	}

	/**
	 * Create a new post for any registered post type.
	 *
	 * @uses wp_insert_post()
	 * @param array $args Method parameters. Contains:
	 *  - int     $blog_id
	 *  - string  $username
	 *  - string  $password
	 *  - array   $content_struct
	 *      $content_struct can contain:
	 *      - post_type (default: 'post')
	 *      - post_status (default: 'draft')
	 *      - post_title
	 *      - post_author
	 *      - post_excerpt
	 *      - post_content
	 *      - post_date_gmt | post_date
	 *      - post_format
	 *      - post_password
	 *      - comment_status - can be 'open' | 'closed'
	 *      - ping_status - can be 'open' | 'closed'
	 *      - sticky
	 *      - post_thumbnail - ID of a media item to use as the post thumbnail/featured image
	 *      - custom_fields - array, with each element containing 'key' and 'value'
	 *      - terms - array, with taxonomy names as keys and arrays of term IDs as values
	 *      - terms_names - array, with taxonomy names as keys and arrays of term names as values
	 *      - enclosure
	 *      - any other fields supported by wp_insert_post()
	 * @return string post_id
	 */
	function wxm_wp_newPost( $args ) {
		if ( ! $this->minimum_args( $args, 4 ) )
			return $this->error;

		$this->escape( $args );

		$blog_id        = (int) $args[0];
		$username       = $args[1];
		$password       = $args[2];
		$content_struct = $args[3];

		if ( ! $user = $this->login( $username, $password ) )
			return $this->error;

		do_action( 'xmlrpc_call', 'wp.newPost' );

		unset( $content_struct['ID'] );

		return $this->wxm_insert_post( $user, $content_struct );
	}

	/*
	 * Helper method for filtering out elements from an array.
	 */
	function wxm_is_greater_than_one( $count ) {
		return $count > 1;
	}

	/*
	 * Helper method for wp_newPost and wp_editPost, containing shared logic.
	 */
	protected function wxm_insert_post( $user, $content_struct ) {
		$defaults = array( 'post_status' => 'draft', 'post_type' => 'post', 'post_author' => 0,
			'post_password' => '', 'post_excerpt' => '', 'post_content' => '', 'post_title' => '' );

		$post_data = wp_parse_args( $content_struct, $defaults );

		$post_type = get_post_type_object( $post_data['post_type'] );
		if ( ! $post_type )
			return new IXR_Error( 403, __( 'Invalid post type' ) );

		$update = ! empty( $post_data['ID'] );

		if ( $update ) {
			if ( ! get_post( $post_data['ID'] ) )
				return new IXR_Error( 401, __( 'Invalid post ID.' ) );
			if ( ! current_user_can( $post_type->cap->edit_post, $post_data['ID'] ) )
				return new IXR_Error( 401, __( 'Sorry, you are not allowed to edit this post.' ) );
			if ( $post_data['post_type'] != get_post_type( $post_data['ID'] ) )
				return new IXR_Error( 401, __( 'The post type may not be changed.' ) );
		} else {
			if ( ! current_user_can( $post_type->cap->edit_posts ) )
				return new IXR_Error( 401, __( 'Sorry, you are not allowed to post on this site.' ) );
		}

		switch ( $post_data['post_status'] ) {
			case 'draft':
			case 'pending':
				break;
			case 'private':
				if ( ! current_user_can( $post_type->cap->publish_posts ) )
					return new IXR_Error( 401, __( 'Sorry, you are not allowed to create private posts in this post type' ) );
				break;
			case 'publish':
			case 'future':
				if ( ! current_user_can( $post_type->cap->publish_posts ) )
					return new IXR_Error( 401, __( 'Sorry, you are not allowed to publish posts in this post type' ) );
				break;
			default:
				if ( ! get_post_status_object( $post_data['post_status'] ) )
					$post_data['post_status'] = 'draft';
				break;
		}

		if ( ! empty( $post_data['post_password'] ) && ! current_user_can( $post_type->cap->publish_posts ) )
			return new IXR_Error( 401, __( 'Sorry, you are not allowed to create password protected posts in this post type' ) );

		$post_data['post_author'] = absint( $post_data['post_author'] );
		if ( ! empty( $post_data['post_author'] ) && $post_data['post_author'] != $user->ID ) {
			if ( ! current_user_can( $post_type->cap->edit_others_posts ) )
				return new IXR_Error( 401, __( 'You are not allowed to create posts as this user.' ) );

			$author = get_userdata( $post_data['post_author'] );

			if ( ! $author )
				return new IXR_Error( 404, __( 'Invalid author ID.' ) );
		} else {
			$post_data['post_author'] = $user->ID;
		}

		if ( isset( $post_data['comment_status'] ) && $post_data['comment_status'] != 'open' && $post_data['comment_status'] != 'closed' )
			unset( $post_data['comment_status'] );

		if ( isset( $post_data['ping_status'] ) && $post_data['ping_status'] != 'open' && $post_data['ping_status'] != 'closed' )
			unset( $post_data['ping_status'] );

		// Do some timestamp voodoo
		if ( ! empty( $post_data['post_date_gmt'] ) ) {
			// We know this is supposed to be GMT, so we're going to slap that Z on there by force
			$dateCreated = rtrim( $post_data['post_date_gmt']->getIso(), 'Z' ) . 'Z';
		} elseif ( ! empty( $post_data['post_date'] ) ) {
			$dateCreated = $post_data['post_date']->getIso();
		}

		if ( ! empty( $dateCreated ) ) {
			$post_data['post_date'] = get_date_from_gmt( iso8601_to_datetime( $dateCreated ) );
			$post_data['post_date_gmt'] = iso8601_to_datetime( $dateCreated, 'GMT' );
		}

		if ( ! isset( $post_data['ID'] ) )
			$post_data['ID'] = get_default_post_to_edit( $post_data['post_type'], true )->ID;
		$post_ID = $post_data['ID'];

		if ( $post_data['post_type'] == 'post' ) {
			// Private and password-protected posts cannot be stickied.
			if ( $post_data['post_status'] == 'private' || ! empty( $post_data['post_password'] ) ) {
				// Error if the client tried to stick the post, otherwise, silently unstick.
				if ( ! empty( $post_data['sticky'] ) )
					return new IXR_Error( 401, __( 'Sorry, you cannot stick a private post.' ) );
				if ( $update )
					unstick_post( $post_ID );
			} elseif ( isset( $post_data['sticky'] ) )  {
				if ( ! current_user_can( $post_type->cap->edit_others_posts ) )
					return new IXR_Error( 401, __( 'Sorry, you are not allowed to stick this post.' ) );
				if ( $post_data['sticky'] )
					stick_post( $post_ID );
				else
					unstick_post( $post_ID );
			}
		}

		if ( isset( $post_data['post_thumbnail'] ) ) {
			// empty value deletes, non-empty value adds/updates
			if ( ! $post_data['post_thumbnail'] )
				delete_post_thumbnail( $post_ID );
			elseif ( ! get_post( absint( $post_data['post_thumbnail'] ) ) )
				return new IXR_Error( 404, __( 'Invalid attachment ID.' ) );
			set_post_thumbnail( $post_ID, $post_data['post_thumbnail'] );
			unset( $content_struct['post_thumbnail'] );
		}

		if ( isset( $post_data['custom_fields'] ) )
			$this->set_custom_fields( $post_ID, $post_data['custom_fields'] );

		if ( isset( $post_data['terms'] ) || isset( $post_data['terms_names'] ) ) {
			$post_type_taxonomies = get_object_taxonomies( $post_data['post_type'], 'objects' );

			// accumulate term IDs from terms and terms_names
			$terms = array();

			// first validate the terms specified by ID
			if ( isset( $post_data['terms'] ) && is_array( $post_data['terms'] ) ) {
				$taxonomies = array_keys( $post_data['terms'] );

				// validating term ids
				foreach ( $taxonomies as $taxonomy ) {
					if ( ! array_key_exists( $taxonomy , $post_type_taxonomies ) )
						return new IXR_Error( 401, __( 'Sorry, one of the given taxonomies is not supported by the post type.' ) );

					if ( ! current_user_can( $post_type_taxonomies[$taxonomy]->cap->assign_terms ) )
						return new IXR_Error( 401, __( 'Sorry, you are not allowed to assign a term to one of the given taxonomies.' ) );

					$term_ids = $post_data['terms'][$taxonomy];
					foreach ( $term_ids as $term_id ) {
						$term = get_term_by( 'id', $term_id, $taxonomy );

						if ( ! $term )
							return new IXR_Error( 403, __( 'Invalid term ID' ) );

						$terms[$taxonomy][] = (int) $term_id;
					}
				}
			}

			// now validate terms specified by name
			if ( isset( $post_data['terms_names'] ) && is_array( $post_data['terms_names'] ) ) {
				$taxonomies = array_keys( $post_data['terms_names'] );

				foreach ( $taxonomies as $taxonomy ) {
					if ( ! array_key_exists( $taxonomy , $post_type_taxonomies ) )
						return new IXR_Error( 401, __( 'Sorry, one of the given taxonomies is not supported by the post type.' ) );

					if ( ! current_user_can( $post_type_taxonomies[$taxonomy]->cap->assign_terms ) )
						return new IXR_Error( 401, __( 'Sorry, you are not allowed to assign a term to one of the given taxonomies.' ) );

					// for hierarchical taxonomies, we can't assign a term when multiple terms in the hierarchy share the same name
					$ambiguous_terms = array();
					if ( is_taxonomy_hierarchical( $taxonomy ) ) {
						$tax_term_names = get_terms( $taxonomy, array( 'fields' => 'names', 'hide_empty' => false ) );

						// count the number of terms with the same name
						$tax_term_names_count = array_count_values( $tax_term_names );

						// filter out non-ambiguous term names
						$ambiguous_tax_term_counts = array_filter( $tax_term_names_count, array( $this, '_is_greater_than_one') );

						$ambiguous_terms = array_keys( $ambiguous_tax_term_counts );
					}

					$term_names = $post_data['terms_names'][$taxonomy];
					foreach ( $term_names as $term_name ) {
						if ( in_array( $term_name, $ambiguous_terms ) )
							return new IXR_Error( 401, __( 'Ambiguous term name used in a hierarchical taxonomy. Please use term ID instead.' ) );

						$term = get_term_by( 'name', $term_name, $taxonomy );

						if ( ! $term ) {
							// term doesn't exist, so check that the user is allowed to create new terms
							if ( ! current_user_can( $post_type_taxonomies[$taxonomy]->cap->edit_terms ) )
								return new IXR_Error( 401, __( 'Sorry, you are not allowed to add a term to one of the given taxonomies.' ) );

							// create the new term
							$term_info = wp_insert_term( $term_name, $taxonomy );
							if ( is_wp_error( $term_info ) )
								return new IXR_Error( 500, $term_info->get_error_message() );

							$terms[$taxonomy][] = (int) $term_info['term_id'];
						} else {
							$terms[$taxonomy][] = (int) $term->term_id;
						}
					}
				}
			}

			$post_data['tax_input'] = $terms;
			unset( $post_data['terms'], $post_data['terms_names'] );
		} else {
			// do not allow direct submission of 'tax_input', clients must use 'terms' and/or 'terms_names'
			unset( $post_data['tax_input'], $post_data['post_category'], $post_data['tags_input'] );
		}

		if ( isset( $post_data['post_format'] ) ) {
			$format = set_post_format( $post_ID, $post_data['post_format'] );

			if ( is_wp_error( $format ) )
				return new IXR_Error( 500, $format->get_error_message() );

			unset( $post_data['post_format'] );
		}

		// Handle enclosures
		$enclosure = isset( $post_data['enclosure'] ) ? $post_data['enclosure'] : null;
		$this->add_enclosure_if_new( $post_ID, $enclosure );

		$this->attach_uploads( $post_ID, $post_data['post_content'] );

		$post_data = apply_filters( 'xmlrpc_wp_insert_post_data', $post_data, $content_struct );

		$post_ID = $update ? wp_update_post( $post_data, true ) : wp_insert_post( $post_data, true );
		if ( is_wp_error( $post_ID ) )
			return new IXR_Error( 500, $post_ID->get_error_message() );

		if ( ! $post_ID )
			return new IXR_Error( 401, __( 'Sorry, your entry could not be posted. Something wrong happened.' ) );

		return strval( $post_ID );
	}

	/**
	 * Edit a post for any registered post type.
	 *
	 * The $content_struct parameter only needs to contain fields that
	 * should be changed. All other fields will retain their existing values.
	 *
	 * @uses wp_insert_post()
	 * @param array $args Method parameters. Contains:
	 *  - int     $blog_id
	 *  - string  $username
	 *  - string  $password
	 *  - int     $post_id
	 *  - array   $content_struct
	 * @return true on success
	 */
	function wxm_wp_editPost( $args ) {
		if ( ! $this->minimum_args( $args, 5 ) )
			return $this->error;

		$this->escape( $args );

		$blog_id        = (int) $args[0];
		$username       = $args[1];
		$password       = $args[2];
		$post_id        = (int) $args[3];
		$content_struct = $args[4];

		if ( ! $user = $this->login( $username, $password ) )
			return $this->error;

		do_action( 'xmlrpc_call', 'wp.editPost' );

		$post = get_post( $post_id, ARRAY_A );

		if ( empty( $post['ID'] ) )
			return new IXR_Error( 404, __( 'Invalid post ID.' ) );

		if ( isset( $content_struct['if_not_modified_since'] ) ) {
			// If the post has been modified since the date provided, return an error.
			if ( mysql2date( 'U', $post['post_modified_gmt'] ) > $content_struct['if_not_modified_since']->getTimestamp() ) {
				return new IXR_Error( 409, __( 'There is a revision of this post that is more recent.' ) );
			}
		}

		// convert the date field back to IXR form
		$post['post_date'] = $this->_convert_date( $post['post_date'] );

		// ignore the existing GMT date if it is empty or a non-GMT date was supplied in $content_struct,
		// since _insert_post will ignore the non-GMT date if the GMT date is set
		if ( $post['post_date_gmt'] == '0000-00-00 00:00:00' || isset( $content_struct['post_date'] ) )
			unset( $post['post_date_gmt'] );
		else
			$post['post_date_gmt'] = $this->_convert_date( $post['post_date_gmt'] );

		$this->escape( $post );
		$merged_content_struct = array_merge( $post, $content_struct );

		$retval = $this->wxm_insert_post( $user, $merged_content_struct );
		if ( $retval instanceof IXR_Error )
			return $retval;

		return true;
	}

	/**
	 * Delete a post for any registered post type.
	 *
	 * @uses wp_delete_post()
	 * @param array $args Method parameters. Contains:
	 *  - int     $blog_id
	 *  - string  $username
	 *  - string  $password
	 *  - int     $post_id
	 * @return true on success
	 */
	function wxm_wp_deletePost( $args ) {
		if ( ! $this->minimum_args( $args, 4 ) )
			return $this->error;

		$this->escape( $args );

		$blog_id    = (int) $args[0];
		$username   = $args[1];
		$password   = $args[2];
		$post_id    = (int) $args[3];

		if ( ! $user = $this->login( $username, $password ) )
			return $this->error;

		do_action( 'xmlrpc_call', 'wp.deletePost' );

		$post = wp_get_single_post( $post_id, ARRAY_A );
		if ( empty( $post['ID'] ) )
			return new IXR_Error( 404, __( 'Invalid post ID.' ) );

		$post_type = get_post_type_object( $post['post_type'] );
		if ( ! current_user_can( $post_type->cap->delete_post, $post_id ) )
			return new IXR_Error( 401, __( 'Sorry, you are not allowed to delete this post.' ) );

		$result = wp_delete_post( $post_id );

		if ( ! $result )
			return new IXR_Error( 500, __( 'The post cannot be deleted.' ) );

		return true;
	}

	/**
	 * Retrieve a post.
	 *
	 * The optional $fields parameter specifies what fields will be included
	 * in the response array. This should be a list of field names. 'post_id' will
	 * always be included in the response regardless of the value of $fields.
	 *
	 * Instead of, or in addition to, individual field names, conceptual group
	 * names can be used to specify multiple fields. The available conceptual
	 * groups are 'post' (all basic fields), 'taxonomies', 'custom_fields',
	 * and 'enclosure'.
	 *
	 * @uses wp_get_single_post()
	 * @param array $args Method parameters. Contains:
	 *  - int     $post_id
	 *  - string  $username
	 *  - string  $password
	 *  - array   $fields optional
	 * @return array contains (based on $fields parameter):
	 *  - 'post_id'
	 *  - 'post_title'
	 *  - 'post_date'
	 *  - 'post_date_gmt'
	 *  - 'post_modified'
	 *  - 'post_modified_gmt'
	 *  - 'post_status'
	 *  - 'post_type'
	 *  - 'post_name'
	 *  - 'post_author'
	 *  - 'post_password'
	 *  - 'post_excerpt'
	 *  - 'post_content'
	 *  - 'link'
	 *  - 'comment_status'
	 *  - 'ping_status'
	 *  - 'sticky'
	 *  - 'custom_fields'
	 *  - 'terms'
	 *  - 'categories'
	 *  - 'tags'
	 *  - 'enclosure'
	 */
	function wxm_wp_getPost( $args ) {
		if ( ! $this->minimum_args( $args, 4 ) )
			return $this->error;

		$this->escape( $args );

		$blog_id            = (int) $args[0];
		$username           = $args[1];
		$password           = $args[2];
		$post_id            = (int) $args[3];

		if ( isset( $args[4] ) )
			$fields = $args[4];
		else
			$fields = apply_filters( 'xmlrpc_default_post_fields', array( 'post', 'terms', 'custom_fields' ), 'wp.getPost' );

		if ( ! $user = $this->login( $username, $password ) )
			return $this->error;

		do_action( 'xmlrpc_call', 'wp.getPost' );

		$post = wp_get_single_post( $post_id, ARRAY_A );

		if ( empty( $post['ID'] ) )
			return new IXR_Error( 404, __( 'Invalid post ID.' ) );

		$post_type = get_post_type_object( $post['post_type'] );
		if ( ! current_user_can( $post_type->cap->edit_posts, $post_id ) )
			return new IXR_Error( 401, __( 'Sorry, you cannot edit this post.' ) );

		return $this->wxm_prepare_post( $post, $fields );
	}

	/**
	 * Retrieve posts.
	 *
	 * The optional $filter parameter modifies the query used to retrieve posts.
	 * Accepted keys are 'post_type', 'post_status', 'number', 'offset',
	 * 'orderby', 'order', and 's'.
	 *
	 * The optional $fields parameter specifies what fields will be included
	 * in the response array.
	 *
	 * @uses wp_get_recent_posts()
	 * @see wp_getPost() for more on $fields
	 * @see get_posts() for more on $filter values
	 *
	 * @param array $args Method parameters. Contains:
	 *  - int     $blog_id
	 *  - string  $username
	 *  - string  $password
	 *  - array   $filter optional
	 *  - array   $fields optional
	 * @return array contains a collection of posts.
	 */
	function wxm_wp_getPosts( $args ) {
		if ( ! $this->minimum_args( $args, 3 ) )
			return $this->error;

		$this->escape( $args );

		$blog_id    = (int) $args[0];
		$username   = $args[1];
		$password   = $args[2];
		$filter     = isset( $args[3] ) ? $args[3] : array();

		if ( isset( $args[4] ) )
			$fields = $args[4];
		else
			$fields = apply_filters( 'xmlrpc_default_post_fields', array( 'post', 'terms', 'custom_fields' ), 'wp.getPosts' );

		if ( ! $user = $this->login( $username, $password ) )
			return $this->error;

		do_action( 'xmlrpc_call', 'wp.getPosts' );

		$query = array();

		if ( isset( $filter['post_type'] ) ) {
			$post_type = get_post_type_object( $filter['post_type'] );
			if ( ! ( (bool) $post_type ) )
				return new IXR_Error( 403, __( 'The post type specified is not valid' ) );

			if ( ! current_user_can( $post_type->cap->edit_posts ) )
				return new IXR_Error( 401, __( 'Sorry, you are not allowed to edit posts in this post type' ));

			$query['post_type'] = $filter['post_type'];
		}

		if ( isset( $filter['post_status'] ) )
			$query['post_status'] = $filter['post_status'];

		if ( isset( $filter['number'] ) )
			$query['numberposts'] = absint( $filter['number'] );

		if ( isset( $filter['offset'] ) )
			$query['offset'] = absint( $filter['offset'] );

		if ( isset( $filter['orderby'] ) ) {
			$query['orderby'] = $filter['orderby'];

			if ( isset( $filter['order'] ) )
				$query['order'] = $filter['order'];
		}

		if ( isset( $filter['s'] ) ) {
			$query['s'] = $filter['s'];
		}

		$posts_list = wp_get_recent_posts( $query );

		if ( ! $posts_list )
			return array();

		// holds all the posts data
		$struct = array();

		foreach ( $posts_list as $post ) {
			$post_type = get_post_type_object( $post['post_type'] );
			if ( ! current_user_can( $post_type->cap->edit_posts, $post['ID'] ) )
				continue;

			$struct[] = $this->wxm_prepare_post( $post, $fields );
		}

		return $struct;
	}

	/**
	 * Retrieves a post type
	 *
	 * @uses get_post_type_object()
	 * @param array $args Method parameters. Contains:
	 *  - int     $blog_id
	 *  - string  $username
	 *  - string  $password
	 *  - string  $post_type_name
	 *  - array   $fields
	 * @return array contains:
	 *  - 'labels'
	 *  - 'description'
	 *  - 'capability_type'
	 *  - 'cap'
	 *  - 'map_meta_cap'
	 *  - 'hierarchical'
	 *  - 'menu_position'
	 *  - 'taxonomies'
	 *  - 'supports'
	 */
	function wxm_wp_getPostType( $args ) {
		if ( ! $this->minimum_args( $args, 4 ) )
			return $this->error;

		$this->escape( $args );

		$blog_id        = (int) $args[0];
		$username       = $args[1];
		$password       = $args[2];
		$post_type_name = $args[3];

		if ( isset( $args[4] ) )
			$fields = $args[4];
		else
			$fields = apply_filters( 'xmlrpc_default_posttype_fields', array( 'labels', 'cap', 'taxonomies' ), 'wp.getPostType' );

		if ( !$user = $this->login( $username, $password ) )
			return $this->error;

		do_action( 'xmlrpc_call', 'wp.getPostType' );

		if( ! post_type_exists( $post_type_name ) )
			return new IXR_Error( 403, __( 'Invalid post type.' ) );

		$post_type = get_post_type_object( $post_type_name );

		if( ! current_user_can( $post_type->cap->edit_posts ) )
			return new IXR_Error( 401, __( 'Sorry, you are not allowed to edit this post type.' ) );

		return $this->_prepare_post_type( $post_type, $fields );
	}

	/**
	 * Retrieves a post types
	 *
	 * @uses get_post_types()
	 * @param array $args Method parameters. Contains:
	 *  - int     $blog_id
	 *  - string  $username
	 *  - string  $password
	 *  - array   $filter
	 *  - array   $fields
	 * @return array
	 */
	function wxm_wp_getPostTypes( $args ) {
		if ( ! $this->minimum_args( $args, 3 ) )
			return $this->error;

		$this->escape( $args );

		$blog_id            = (int) $args[0];
		$username           = $args[1];
		$password           = $args[2];
		$filter             = isset( $args[3] ) ? $args[3] : array( 'public' => true );

		if ( isset( $args[4] ) )
			$fields = $args[4];
		else
			$fields = apply_filters( 'xmlrpc_default_posttype_fields', array( 'labels', 'cap', 'taxonomies' ), 'wp.getPostTypes' );

		if ( ! $user = $this->login( $username, $password ) )
			return $this->error;

		do_action( 'xmlrpc_call', 'wp.getPostTypes' );

		$post_types = get_post_types( $filter, 'objects' );

		$struct = array();

		foreach( $post_types as $post_type ) {
			if( ! current_user_can( $post_type->cap->edit_posts ) )
				continue;

			$struct[$post_type->name] = $this->_prepare_post_type( $post_type, $fields );
		}

		return $struct;
	}

	/**
	 * Create a new term.
	 *
	 * @uses wp_insert_term()
	 * @param array $args Method parameters. Contains:
	 *  - int     $blog_id
	 *  - string  $username
	 *  - string  $password
	 *  - array   $content_struct
	 *      The $content_struct must contain:
	 *      - 'name'
	 *      - 'taxonomy'
	 *      Also, it can optionally contain:
	 *      - 'parent'
	 *      - 'description'
	 *      - 'slug'
	 * @return string term_id
	 */
	function wxm_wp_newTerm( $args ) {
		if ( ! $this->minimum_args( $args, 4 ) )
			return $this->error;

		$this->escape( $args );

		$blog_id            = (int) $args[0];
		$username           = $args[1];
		$password           = $args[2];
		$content_struct     = $args[3];

		if ( ! $user = $this->login( $username, $password ) )
			return $this->error;

		do_action( 'xmlrpc_call', 'wp.newTerm' );

		if ( ! taxonomy_exists( $content_struct['taxonomy'] ) )
			return new IXR_Error( 403, __( 'Invalid taxonomy.' ) );

		$taxonomy = get_taxonomy( $content_struct['taxonomy'] );

		if ( ! current_user_can( $taxonomy->cap->manage_terms ) )
			return new IXR_Error( 401, __( 'You are not allowed to create terms in this taxonomy.' ) );

		$taxonomy = (array) $taxonomy;

		// hold the data of the term
		$term_data = array();

		$term_data['name'] = trim( $content_struct['name'] );
		if ( empty( $term_data['name'] ) )
			return new IXR_Error( 403, __( 'The term name cannot be empty.' ) );

		if ( isset( $content_struct['parent'] ) ) {
			if ( ! $taxonomy['hierarchical'] )
				return new IXR_Error( 403, __( 'This taxonomy is not hierarchical.' ) );

			$parent_term_id = (int) $content_struct['parent'];
			$parent_term = get_term( $parent_term_id , $taxonomy['name'] );

			if ( is_wp_error( $parent_term ) )
				return new IXR_Error( 500, $parent_term->get_error_message() );

			if ( ! $parent_term )
				return new IXR_Error( 403, __( 'Parent term does not exist.' ) );

			$term_data['parent'] = $content_struct['parent'];
		}

		if ( isset( $content_struct['description'] ) )
			$term_data['description'] = $content_struct['description'];

		if ( isset( $content_struct['slug'] ) )
			$term_data['slug'] = $content_struct['slug'];

		$term = wp_insert_term( $term_data['name'] , $taxonomy['name'] , $term_data );

		if ( is_wp_error( $term ) )
			return new IXR_Error( 500, $term->get_error_message() );

		if ( ! $term )
			return new IXR_Error( 500, __( 'Sorry, your term could not be created. Something wrong happened.' ) );

		return strval( $term['term_id'] );
	}

	/**
	 * Edit a term.
	 *
	 * @uses wp_update_term()
	 * @param array $args Method parameters. Contains:
	 *  - int     $blog_id
	 *  - string  $username
	 *  - string  $password
	 *  - string  $term_id
	 *  - array   $content_struct
	 *      The $content_struct must contain:
	 *      - 'taxonomy'
	 *      Also, it can optionally contain:
	 *      - 'name'
	 *      - 'parent'
	 *      - 'description'
	 *      - 'slug'
	 * @return bool True, on success.
	 */
	function wxm_wp_editTerm( $args ) {
		if ( ! $this->minimum_args( $args, 5 ) )
			return $this->error;

		$this->escape( $args );

		$blog_id            = (int) $args[0];
		$username           = $args[1];
		$password           = $args[2];
		$term_id            = (int) $args[3];
		$content_struct     = $args[4];

		if ( ! $user = $this->login( $username, $password ) )
			return $this->error;

		do_action( 'xmlrpc_call', 'wp.editTerm' );

		if ( ! taxonomy_exists( $content_struct['taxonomy'] ) )
			return new IXR_Error( 403, __( 'Invalid taxonomy.' ) );

		$taxonomy = get_taxonomy( $content_struct['taxonomy'] );

		if ( ! current_user_can( $taxonomy->cap->edit_terms ) )
			return new IXR_Error( 401, __( 'You are not allowed to edit terms in this taxonomy.' ) );

		$taxonomy = (array) $taxonomy;

		// hold the data of the term
		$term_data = array();

		$term = get_term( $term_id , $content_struct['taxonomy'] );

		if ( is_wp_error( $term ) )
			return new IXR_Error( 500, $term->get_error_message() );

		if ( ! $term )
			return new IXR_Error( 404, __( 'Invalid term ID.' ) );

		if ( isset( $content_struct['name'] ) ) {
			$term_data['name'] = trim( $content_struct['name'] );

			if ( empty( $term_data['name'] ) )
				return new IXR_Error( 403, __( 'The term name cannot be empty.' ) );
		}

		if ( isset( $content_struct['parent'] ) ) {
			if ( ! $taxonomy['hierarchical'] )
				return new IXR_Error( 403, __( "This taxonomy is not hierarchical so you can't set a parent." ) );

			$parent_term_id = (int) $content_struct['parent'];
			$parent_term = get_term( $parent_term_id , $taxonomy['name'] );

			if ( is_wp_error( $parent_term ) )
				return new IXR_Error( 500, $parent_term->get_error_message() );

			if ( ! $parent_term )
				return new IXR_Error( 403, __( 'Parent term does not exist.' ) );

			$term_data['parent'] = $content_struct['parent'];
		}

		if ( isset( $content_struct['description'] ) )
			$term_data['description'] = $content_struct['description'];

		if ( isset( $content_struct['slug'] ) )
			$term_data['slug'] = $content_struct['slug'];

		$term = wp_update_term( $term_id , $taxonomy['name'] , $term_data );

		if ( is_wp_error( $term ) )
			return new IXR_Error( 500, $term->get_error_message() );

		if ( ! $term )
			return new IXR_Error( 500, __( 'Sorry, editing the term failed.' ) );

		return true;
	}

	/**
	 * Delete a term.
	 *
	 * @uses wp_delete_term()
	 * @param array $args Method parameters. Contains:
	 *  - int     $blog_id
	 *  - string  $username
	 *  - string  $password
	 *  - string  $taxnomy_name
	 *  - string     $term_id
	 * @return boolean|IXR_Error If it suceeded true else a reason why not
	 */
	function wxm_wp_deleteTerm( $args ) {
		if ( ! $this->minimum_args( $args, 5 ) )
			return $this->error;

		$this->escape( $args );

		$blog_id            = (int) $args[0];
		$username           = $args[1];
		$password           = $args[2];
		$taxonomy           = $args[3];
		$term_id            = (int) $args[4];

		if ( ! $user = $this->login( $username, $password ) )
			return $this->error;

		do_action( 'xmlrpc_call', 'wp.deleteTerm' );

		if ( ! taxonomy_exists( $taxonomy ) )
			return new IXR_Error( 403, __( 'Invalid taxonomy.' ) );

		$taxonomy = get_taxonomy( $taxonomy );

		if ( ! current_user_can( $taxonomy->cap->delete_terms ) )
			return new IXR_Error( 401, __( 'You are not allowed to delete terms in this taxonomy.' ) );

		$term = get_term( $term_id, $taxonomy->name );

		if ( is_wp_error( $term ) )
			return new IXR_Error( 500, $term->get_error_message() );

		if ( ! $term )
			return new IXR_Error( 404, __( 'Invalid term ID.' ) );

		$result = wp_delete_term( $term_id, $taxonomy->name );

		if ( is_wp_error( $result ) )
			return new IXR_Error( 500, $term->get_error_message() );

		if ( ! $result )
			return new IXR_Error( 500, __( 'Sorry, deleting the term failed.' ) );

		return $result;
	}

	/**
	 * Retrieve a term.
	 *
	 * @uses get_term()
	 * @param array $args Method parameters. Contains:
	 *  - int     $blog_id
	 *  - string  $username
	 *  - string  $password
	 *  - string  $taxonomy
	 *  - string  $term_id
	 * @return array contains:
	 *  - 'term_id'
	 *  - 'name'
	 *  - 'slug'
	 *  - 'term_group'
	 *  - 'term_taxonomy_id'
	 *  - 'taxonomy'
	 *  - 'description'
	 *  - 'parent'
	 *  - 'count'
	 */
	function wxm_wp_getTerm( $args ) {
		if ( ! $this->minimum_args( $args, 5 ) )
			return $this->error;

		$this->escape( $args );

		$blog_id            = (int) $args[0];
		$username           = $args[1];
		$password           = $args[2];
		$taxonomy           = $args[3];
		$term_id            = (int) $args[4];

		if ( ! $user = $this->login( $username, $password ) )
			return $this->error;

		do_action( 'xmlrpc_call', 'wp.getTerm' );

		if ( ! taxonomy_exists( $taxonomy ) )
			return new IXR_Error( 403, __( 'Invalid taxonomy.' ) );

		$taxonomy = get_taxonomy( $taxonomy );

		if ( ! current_user_can( $taxonomy->cap->assign_terms ) )
			return new IXR_Error( 401, __( 'You are not allowed to assign terms in this taxonomy.' ) );

		$term = get_term( $term_id , $taxonomy->name, ARRAY_A );

		if ( is_wp_error( $term ) )
			return new IXR_Error( 500, $term->get_error_message() );

		if ( ! $term )
			return new IXR_Error( 404, __( 'Invalid term ID.' ) );

		return $this->_prepare_term( $term );
	}

	/**
	 * Retrieve all terms for a taxonomy.
	 *
	 * The optional $filter parameter modifies the query used to retrieve terms.
	 * Accepted keys are 'number', 'offset', 'orderby', 'order', 'hide_empty', and 'search'.
	 *
	 * @uses get_terms()
	 * @param array $args Method parameters. Contains:
	 *  - int     $blog_id
	 *  - string  $username
	 *  - string  $password
	 *  - string  $taxonomy
	 *  - array   $filter optional
	 * @return array terms
	 */
	function wxm_wp_getTerms( $args ) {
		if ( ! $this->minimum_args( $args, 4 ) )
			return $this->error;

		$this->escape( $args );

		$blog_id        = (int) $args[0];
		$username       = $args[1];
		$password       = $args[2];
		$taxonomy       = $args[3];
		$filter         = isset( $args[4] ) ? $args[4] : array();

		if ( ! $user = $this->login( $username, $password ) )
			return $this->error;

		do_action( 'xmlrpc_call', 'wp.getTerms' );

		if ( ! taxonomy_exists( $taxonomy ) )
			return new IXR_Error( 403, __( 'Invalid taxonomy.' ) );

		$taxonomy = get_taxonomy( $taxonomy );

		if ( ! current_user_can( $taxonomy->cap->assign_terms ) )
			return new IXR_Error( 401, __( 'You are not allowed to assign terms in this taxonomy.' ) );

		$query = array();

		if ( isset( $filter['number'] ) )
			$query['number'] = absint( $filter['number'] );

		if ( isset( $filter['offset'] ) )
			$query['offset'] = absint( $filter['offset'] );

		if ( isset( $filter['orderby'] ) ) {
			$query['orderby'] = $filter['orderby'];

			if ( isset( $filter['order'] ) )
				$query['order'] = $filter['order'];
		}

		if ( isset( $filter['hide_empty'] ) )
			$query['hide_empty'] = $filter['hide_empty'];
		else
			$query['get'] = 'all';

		if ( isset( $filter['search'] ) )
			$query['search'] = $filter['search'];

		$terms = get_terms( $taxonomy->name, $query );

		if ( is_wp_error( $terms ) )
			return new IXR_Error( 500, $terms->get_error_message() );

		$struct = array();

		foreach ( $terms as $term ) {
			$struct[] = $this->_prepare_term( $term );
		}

		return $struct;
	}

	/**
	 * Retrieve a taxonomy.
	 *
	 * @uses get_taxonomy()
	 * @param array $args Method parameters. Contains:
	 *  - int     $blog_id
	 *  - string  $username
	 *  - string  $password
	 *  - string  $taxonomy
	 * @return array (@see get_taxonomy())
	 */
	function wxm_wp_getTaxonomy( $args ) {
		if ( ! $this->minimum_args( $args, 4 ) )
			return $this->error;

		$this->escape( $args );

		$blog_id        = (int) $args[0];
		$username       = $args[1];
		$password       = $args[2];
		$taxonomy       = $args[3];

		if ( isset( $args[4] ) )
			$fields = $args[4];
		else
			$fields = apply_filters( 'xmlrpc_default_taxonomy_fields', array( 'labels', 'cap', 'object_type' ), 'wp.getTaxonomy' );

		if ( ! $user = $this->login( $username, $password ) )
			return $this->error;

		do_action( 'xmlrpc_call', 'wp.getTaxonomy' );

		if ( ! taxonomy_exists( $taxonomy ) )
			return new IXR_Error( 403, __( 'Invalid taxonomy.' ) );

		$taxonomy = get_taxonomy( $taxonomy );

		if ( ! current_user_can( $taxonomy->cap->assign_terms ) )
			return new IXR_Error( 401, __( 'You are not allowed to assign terms in this taxonomy.' ) );

		return $this->_prepare_taxonomy( $taxonomy, $fields );
	}

	/**
	 * Retrieve all taxonomies.
	 *
	 * @uses get_taxonomies()
	 * @param array $args Method parameters. Contains:
	 *  - int     $blog_id
	 *  - string  $username
	 *  - string  $password
	 * @return array taxonomies
	 */
	function wxm_wp_getTaxonomies( $args ) {
		if ( ! $this->minimum_args( $args, 3 ) )
			return $this->error;

		$this->escape( $args );

		$blog_id            = (int) $args[0];
		$username           = $args[1];
		$password           = $args[2];
		$filter             = isset( $args[3] ) ? $args[3] : array( 'public' => true );

		if ( isset( $args[4] ) )
			$fields = $args[4];
		else
			$fields = apply_filters( 'xmlrpc_default_taxonomy_fields', array( 'labels', 'cap', 'object_type' ), 'wp.getTaxonomies' );

		if ( ! $user = $this->login( $username, $password ) )
			return $this->error;

		do_action( 'xmlrpc_call', 'wp.getTaxonomies' );

		$taxonomies = get_taxonomies( $filter, 'objects' );

		// holds all the taxonomy data
		$struct = array();

		foreach ( $taxonomies as $taxonomy ) {
			// capability check for post_types
			if ( ! current_user_can( $taxonomy->cap->assign_terms ) )
				continue;

			$struct[] = $this->_prepare_taxonomy( $taxonomy, $fields );
		}

		return $struct;
	}

	/**
	 * Retrieve revisions for a specific post.
	 *
	 * The optional $fields parameter specifies what fields will be included
	 * in the response array.
	 *
	 * @uses wp_get_post_revisions()
	 * @see wp_getPost() for more on $fields
	 *
	 * @param array $args Method parameters. Contains:
	 *  - int     $blog_id
	 *  - string  $username
	 *  - string  $password
	 *  - int     $post_id
	 *  - array   $fields
	 * @return array contains a collection of posts.
	 */
	function wxm_wp_getRevisions( $args ) {
		if ( ! $this->minimum_args( $args, 4 ) )
			return $this->error;

		$this->escape( $args );

		$blog_id    = (int) $args[0];
		$username   = $args[1];
		$password   = $args[2];
		$post_id    = (int) $args[3];

		if ( isset( $args[4] ) )
			$fields = $args[4];
		else
			$fields = apply_filters( 'xmlrpc_default_revision_fields', array( 'post_date', 'post_date_gmt' ), 'wp.getRevisions' );

		if ( ! $user = $this->login( $username, $password ) )
			return $this->error;

		do_action( 'xmlrpc_call', 'wp.getRevisions' );

		if ( ! $post = get_post( $post_id ) )
			return new IXR_Error( 404, __( 'Invalid post ID' ) );

		if ( ! current_user_can( 'edit_post', $post_id ) )
			return new IXR_Error( 401, __( 'Sorry, you are not allowed to edit posts.' ) );

		// Check if revisions are enabled.
		if ( ! WP_POST_REVISIONS || ! post_type_supports( $post->post_type, 'revisions' ) )
			return new IXR_Error( 401, __( 'Sorry, revisions are disabled.' ) );

		$revisions = wp_get_post_revisions( $post_id );

		if ( ! $revisions )
			return array();

		$struct = array();

		foreach ( $revisions as $revision ) {
			if ( ! current_user_can( 'read_post', $revision->ID ) )
				continue;

			// Skip autosaves
			if ( wp_is_post_autosave( $revision ) )
				continue;

			$struct[] = $this->wxm_prepare_post( get_object_vars( $revision ), $fields );
		}

		return $struct;
	}

	/**
	 * Restore a post revision
	 *
	 * @uses wp_restore_post_revision()
	 *
	 * @param array $args Method parameters. Contains:
	 *  - int     $blog_id
	 *  - string  $username
	 *  - string  $password
	 *  - int     $post_id
	 * @return bool false if there was an error restoring, true if success.
	 */
	function wxm_wp_restoreRevision( $args ) {
		if ( ! $this->minimum_args( $args, 3 ) )
			return $this->error;

		$this->escape( $args );

		$blog_id     = (int) $args[0];
		$username    = $args[1];
		$password    = $args[2];
		$revision_id = (int) $args[3];

		if ( ! $user = $this->login( $username, $password ) )
			return $this->error;

		do_action( 'xmlrpc_call', 'wp.restoreRevision' );

		if ( ! $revision = wp_get_post_revision( $revision_id ) )
			return new IXR_Error( 404, __( 'Invalid post ID' ) );

		if ( wp_is_post_autosave( $revision ) )
			return new IXR_Error( 404, __( 'Invalid post ID' ) );

		if ( ! $post = get_post( $revision->post_parent ) )
			return new IXR_Error( 404, __( 'Invalid post ID' ) );

		if ( ! current_user_can( 'edit_post', $revision->post_parent ) )
			return new IXR_Error( 401, __( 'Sorry, you cannot edit this post.' ) );

		// Check if revisions are disabled.
		if ( ! WP_POST_REVISIONS || ! post_type_supports( $post->post_type, 'revisions' ) )
			return new IXR_Error( 401, __( 'Sorry, revisions are disabled.' ) );

		$post = wp_restore_post_revision( $revision_id );

		return (bool) $post;
	}

	/**
	 * Uploads a file, following your settings.
	 *
	 * @param array $args Method parameters.
	 * @return array
	 */
	function wxm_wp_uploadFile($args) {
		global $wpdb;

		$blog_ID     = (int) $args[0];
		$username  = $wpdb->escape($args[1]);
		$password   = $wpdb->escape($args[2]);
		$data        = $args[3];

		$name = sanitize_file_name( $data['name'] );
		$type = $data['type'];
		$bits = $data['bits'];

		if ( !$user = $this->login($username, $password) )
			return $this->error;

		do_action('xmlrpc_call', 'metaWeblog.newMediaObject');

		if ( !current_user_can('upload_files') ) {
			$this->error = new IXR_Error( 401, __( 'You do not have permission to upload files.' ) );
			return $this->error;
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

		$upload = wp_upload_bits($name, null, $bits);
		if ( ! empty($upload['error']) ) {
			$errorString = sprintf(__('Could not write file %1$s (%2$s)'), $name, $upload['error']);
			return new IXR_Error(500, $errorString);
		}
		// Construct the attachment array
		$post_id = 0;
		if ( ! empty( $data['post_id'] ) ) {
			$post_id = (int) $data['post_id'];

			if ( ! current_user_can( 'edit_post', $post_id ) )
				return new IXR_Error( 401, __( 'Sorry, you cannot edit this post.' ) );
		}
		$attachment = array(
			'post_title' => $name,
			'post_content' => '',
			'post_type' => 'attachment',
			'post_parent' => $post_id,
			'post_mime_type' => $type,
			'guid' => $upload[ 'url' ]
		);

		// Save the data
		$id = wp_insert_attachment( $attachment, $upload[ 'file' ], $post_id );
		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $upload['file'] ) );

		do_action( 'xmlrpc_call_success_mw_newMediaObject', $id, $args );

		$struct = array(
			'id'   => strval( $id ),
			'file' => $name,
			'url'  => $upload[ 'url' ],
			'type' => $type
		);
		return apply_filters( 'wp_handle_upload', $struct, 'upload' );
	}

	function wxm_blog_options( $options ) {
		$wp34_options = array(
			// Read only options
			'home_url'          => array(
				 'desc'         => __( 'Home URL' ),
				'readonly'      => true,
				'option'        => 'home'
			),
			'image_default_link_type' => array(
				'desc'          => __( 'Image default link type' ),
				'readonly'      => true,
				'option'        => 'image_default_link_type'
			),
			'image_default_size' => array(
				'desc'          => __( 'Image default size' ),
				'readonly'      => true,
				'option'        => 'image_default_size'
			),
			'image_default_align' => array(
				'desc'          => __( 'Image default align' ),
				'readonly'      => true,
				'option'        => 'image_default_align'
			),
			'template'          => array(
				'desc'          => __( 'Template' ),
				'readonly'      => true,
				'option'        => 'template'
			),
			'stylesheet'        => array(
				'desc'          => __( 'Stylesheet' ),
				'readonly'      => true,
				'option'        => 'stylesheet'
			),
			'post_thumbnail'    => array(
				'desc'          => __('Post Thumbnail'),
				'readonly'      => true,
				'value'         => current_theme_supports( 'post-thumbnails' )
			),

			// Updatable options
			'default_comment_status' => array(
				'desc'          => __( 'Allow people to post comments on new articles' ),
				'readonly'      => false,
				'option'        => 'default_comment_status'
			),
			'default_ping_status' => array(
				'desc'          => __( 'Allow link notifications from other blogs (pingbacks and trackbacks)' ),
				'readonly'      => false,
				'option'        => 'default_ping_status'
			)
		);

		return array_merge( $wp34_options, $options );
	}

	// need to totally replace these old implementations
	function wxm_wp_getMediaItem($args) {
		$this->escape($args);

		$blog_id		= (int) $args[0];
		$username		= $args[1];
		$password		= $args[2];
		$attachment_id	= (int) $args[3];

		if ( !$user = $this->login($username, $password) )
			return $this->error;

		if ( !current_user_can( 'upload_files' ) )
			return new IXR_Error( 403, __( 'You are not allowed to upload files to this site.' ) );

		do_action('xmlrpc_call', 'wp.getMediaItem');

		if ( ! $attachment = get_post($attachment_id) )
			return new IXR_Error( 404, __( 'Invalid attachment ID.' ) );

		return $this->_prepare_media_item( $attachment );
	}

	function wxm_wp_getMediaLibrary($args) {
		$this->escape($args);

		$blog_id	= (int) $args[0];
		$username	= $args[1];
		$password	= $args[2];
		$struct		= isset( $args[3] ) ? $args[3] : array() ;

		if ( !$user = $this->login($username, $password) )
			return $this->error;

		if ( !current_user_can( 'upload_files' ) )
			return new IXR_Error( 401, __( 'Sorry, you cannot upload files.' ) );

		do_action('xmlrpc_call', 'wp.getMediaLibrary');

		$parent_id = ( isset($struct['parent_id']) ) ? absint($struct['parent_id']) : '' ;
		$mime_type = ( isset($struct['mime_type']) ) ? $struct['mime_type'] : '' ;
		$offset = ( isset($struct['offset']) ) ? absint($struct['offset']) : 0 ;
		$number = ( isset($struct['number']) ) ? absint($struct['number']) : -1 ;

		$attachments = get_posts( array('post_type' => 'attachment', 'post_parent' => $parent_id, 'offset' => $offset, 'numberposts' => $number, 'post_mime_type' => $mime_type ) );

		$attachments_struct = array();

		foreach ($attachments as $attachment )
			$attachments_struct[] = $this->_prepare_media_item( $attachment );

		return $attachments_struct;
	}
}

?>