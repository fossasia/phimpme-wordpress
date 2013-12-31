<?php
/*
 * Plugin Name: Drupal to WP XML-RPC
 * Plugin URI: http://grial.usal.es/agora/pfcgrial/drupaltowp-xmlrpc
 * Description: Enable Multisite specific functions to XML-RPC API
 * Version: 1.0
 * Requires at least: WordPress 3.0
 * Tested up to: WordPress 3.0.4
 * Author: Alicia García Holgado
 * Author URI: http://grial.usal.es/agora/mambanegra
 * License: GPL v2 - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
Network: true
*/

/*  Copyright 2010  Alicia García Holgado  (email : aliciagh@usal.es)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

if (!defined('DRUPALTOWP_KEY')) {
  define( 'DRUPALTOWP_KEY', 'OvceYl7HsydhtW-X%zXp' );
}

/**
 * Checks if the request's arguments are correct and returns the function parameters.
 * 
 * @param $username Login name
 * @param $password Password associated to the login name
 * @return mixed The login user or an error message
 */
function drupaltowp_check_arguments( $username, $password ) {
  global $wp_xmlrpc_server;
  
  $password = drupaltowp_decrypt_password( $password, DRUPALTOWP_KEY );

  if ( $password === false ) {
    return new IXR_Error( 700, __( "Decrypting the password error." ) );
  }
  
  if ( !$wp_xmlrpc_server->login( $username, $password )  ) {
    return new IXR_Error( 700, $wp_xmlrpc_server->error->message );
  }
  
  return true;
}

/**
 * Encrypt value with a key.
 * 
 * @param $value
 * @param $key
 * @return mixed encrypted vale or false
 */
function drupaltowp_encrypt_password( $value, $key ) {
  if ( isset($value) && !empty($key)) {
    $iv_size = mcrypt_get_iv_size( MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB );
    $iv = mcrypt_create_iv( $iv_size, MCRYPT_RAND );
    $value = base64_encode( mcrypt_encrypt( MCRYPT_RIJNDAEL_256, $key, $value, MCRYPT_MODE_ECB, $iv ) );
    
    return $value;
  } else {
    return false;
  }
}

/**
 * Decrypt value.
 * 
 * @param $value
 * @param $key
 * @return mixed decrypted value or false
 */
function drupaltowp_decrypt_password( $value, $key ) {
  if( isset( $value ) && !empty( $key ) ) {
    $iv_size = mcrypt_get_iv_size( MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB );
    $iv = mcrypt_create_iv( $iv_size, MCRYPT_RAND );
    $decrypttext = trim( mcrypt_decrypt( MCRYPT_RIJNDAEL_256, $key, base64_decode( $value ), MCRYPT_MODE_ECB, $iv ) );
    
    return $decrypttext;
  } else {
    return false;
  }
}

/**
 * Create a new blog.
 * Parameters:
 * $blog Array with the values for a new blog:
 *    domain  The domain of the new blog.
 *    path    The path of the new blog.
 *    title   The title of the new blog.
 *    user_id The user id of the user account who will be the blog admin.
 *    meta    (optional) Other meta information.
 *    site_id (optional) The site_id of the blog to be created.
 * $username User name with admin permissions
 * $password Password associated to the user name
 * 
 * @see wpmu_create_blog
 *
 * @param array $args Array with blog parameters, username and password
 * @return mixed The new blog id or an error message
 */
function drupaltowp_xmlrpc_create_blog( $args ) {
  global $wp_xmlrpc_server;
  
  $wp_xmlrpc_server->escape( $args );
  
  $blog     = $args[0];
  $username = $args[1];
  $password = $args[2];
  
  $error = drupaltowp_check_arguments( $username, $password );
  if ( $error !== true ) {
    return $error;
  }
  
  if ( get_blog_details( $blog, false ) !== false ) {
    return new IXR_Error( 500, __( "Site already exists." ) );
  }

  if ( !isset( $blog['meta'] ) )    $blog['meta']    = "";
  if ( !isset( $blog['site_id'] ) ) $blog['site_id'] = 1;

  $blog_id = wpmu_create_blog( $blog['domain'], $blog['path'], $blog['title'], $blog['user_id'], $blog['meta'], $blog['site_id'] );
  
  if ( is_wp_error( $blog_id ) ) {
    return new IXR_Error( 500, $error->get_error_message() );
  }
  
  return $blog_id;
}

/**
 * Retrive all users.
 * Parameters:
 * $username User name with admin permissions
 * $password Password associated to the user name
 * 
 * @param $args Array with username and password
 * @return mixed Array with all users or an error message
 */
function drupaltowp_xmlrpc_get_users( $args ) {
  global $wp_xmlrpc_server, $wpdb;
  
  $wp_xmlrpc_server->escape( $args );
  
  $username = $args[0];
  $password = $args[1];
  
  $error = drupaltowp_check_arguments( $username, $password );
  if ($error !== true ) {
    return $error;
  }
  
  $request = $wpdb->prepare( "SELECT ID, user_login FROM {$wpdb->users} WHERE deleted=0" );
  
  return $wpdb->get_results( $request, ARRAY_A );
}

/**
 * Retrieve the blogs of a user.
 * Parameters:
 * $user_id  The user id of the user account
 * $username User name with admin permissions
 * $password Password associated to the user name
 * 
 * @see get_blogs_of_user
 * 
 * @param $args Array with user_id, username and password
 * @return mixed Array with blogs or an error message
 */
function drupaltowp_xmlrpc_get_users_blogs( $args ) {
  global $wp_xmlrpc_server, $current_site;
  
  $user_id  = (int) $args[0];
  $username = $args[1];
  $password = $args[2];
  
  $error = drupaltowp_check_arguments( $username, $password );
  if ($error !== true ) {
    return $error;
  }
  
  $blogs = (array) get_blogs_of_user( $user_id );
  
  $struct = array();

  foreach ( $blogs as $blog ) {
    // Don't include blogs that aren't hosted at this site
    if ( $blog->site_id != $current_site->id )
      continue;

    $blog_id = $blog->userblog_id;
    switch_to_blog( $blog_id );
    $is_admin = current_user_can( 'manage_options' );

    $struct[] = array(
      'isAdmin'       => $is_admin,
      'url'           => get_option( 'home' ) . '/',
      'blogid'        => $blog_id,
      'blogName'      => get_option( 'blogname' ),
      'xmlrpc'        => site_url( 'xmlrpc.php' )
    );

    restore_current_blog();
  }

  return $struct;
}

/**
 * Return the id of blog given domain and path.
 * Parameters:
 * $domain   The domain of the blog
 * $path     The path of the blog
 * $username User name with admin permissions
 * $password Password associated to the user name
 *
 * @param string $args Array with username, password, domain and path
 * @return mixed Returns the blog_id or an error message
 */
function drupaltowp_xmlrpc_get_blog_id( $args ) {
  global $wp_xmlrpc_server;
  
  $wp_xmlrpc_server->escape( $args );
  
  $domain   = $args[0];
  $path     = $args[1];
  $username = $args[2];
  $password = $args[3];
  
  $error = drupaltowp_check_arguments( $username, $password );
  if ($error !== true ) {
    return $error;
  }
  
  $blog_id = get_blog_details( array('domain' => $domain, 'path' => $path), false );
  if ( !$blog_id ) {
    return new IXR_Error( 500, __( "No sites found." ) );
  }
  
  return $blog_id->blog_id;
}

/**
 * Retrieve the list of categories on a given blog.
 * Parameters:
 * $blod_id  The blog id
 * $username User name with admin permissions
 * $password Password associated to the user name
 * 
 * @see mw_getCategories
 * 
 * @param $args Array with blog_id, username and password
 * @return mixed Array with categories or an error message
 */
function drupaltowp_xmlrpc_get_categories( $args ) {
  global $wp_xmlrpc_server;
  
  $wp_xmlrpc_server->escape( $args );
  
  $blog_id  = (int) $args[0];
  $username = $args[1];
  $password = $args[2];
  
  // Decrypt password
  $password = drupaltowp_decrypt_password( $password, DRUPALTOWP_KEY );
  if ( $password === false ) {
    return new IXR_Error( 700, __( "Decrypting the password error." ) );
  }
  $args[2] = $password;
  
  // Switch to the selected blog
  switch_to_blog( $blog_id );
  
  $categories = $wp_xmlrpc_server->mw_getCategories( $args );
  
  restore_current_blog();
  
  return $categories;
}

/**
 * Create a new user.
 * Parameters:
 * $new_user The username of the user to be created
 * $email    The email address of the user to be created
 * $username User name with admin permissions
 * $password Password associated to the user name
 * 
 * @see wpmu_create_user
 * 
 * @param $args Array with domain, path, username and password
 * @return mixed The new user id or an error message
 */
function drupaltowp_xmlrpc_create_user( $args ) {
  global $wp_xmlrpc_server;
  
  $wp_xmlrpc_server->escape( $args );
  
  $new_user = $args[0];
  $email    = $args[1];
  $username = $args[2];
  $password = $args[3];
  
  $error = drupaltowp_check_arguments( $username, $password );
  if ($error !== true ) {
    return $error;
  }
  
  // Check if username or email already exist
  if ( !( $user_id = get_user_id_from_string( $email ) ) ) {
    $error = wpmu_validate_user_signup( $new_user, $email );

    if ( is_wp_error( $error ) ) {
      return new IXR_Error( 500, $error->get_error_message() );
    }

    $user_id = wpmu_create_user( $new_user, wp_generate_password(), $email );
    if ( is_wp_error( $user_id ) ) {
      return new IXR_Error( 500, $error->get_error_message() );
    }
    
    return $user_id;
  }
  
  return new IXR_Error( 500,  __( "User already exists." ) );
}

/**
 * Delete a user.
 * Parameters:
 * $user_id  User ID
 * $reassign (optional) Reassign posts and links to new User ID
 * $username User name with admin permissions
 * $password Password associated to the user name
 * 
 * @see wp_delete_user, wpmu_delete_user
 * 
 * @param $args Array with user_id, reassign, username and password
 * @return boolean True when finished or an error message
 */
function drupaltowp_xmlrpc_delete_user( $args ) {
  global $wp_xmlrpc_server;
  
  $wp_xmlrpc_server->escape( $args );
  
  $user_id  = (int) $args[0];
  $reassign = (int) $args[1];
  $username = $args[2];
  $password = $args[3];
  
  $error = drupaltowp_check_arguments( $username, $password );
  if ($error !== true ) {
    return $error;
  }
  
  if ( $user_id == 1) {
    return new IXR_Error( 500,  __( "Can't remove the super admin." ) );
  }
  
  // Reassign post and links to another user.
  if ( !empty( $fields['reassign'] ) ) {
    $blogs = (array) get_blogs_of_user( $user_id );
    foreach ( $blogs as $blog ) {
      switch_to_blog( $blog->userblog_id );
      wp_delete_user( $user_id, $reassign );
    }
  }
  
  restore_current_blog();
  
  return wpmu_delete_user($user_id);
}

/**
 * Append Multisite functions to the XML-RPC Interface.
 * 
 * @param array $methods XML-RPC allowed methods
 * returns array
 */
function drupaltowp_xmlrpc_methods( $methods ) {
  $methods['drupal.newBlog']       = 'drupaltowp_xmlrpc_create_blog';
  $methods['drupal.getUsers']      = 'drupaltowp_xmlrpc_get_users';
  $methods['drupal.getUsersBlogs'] = 'drupaltowp_xmlrpc_get_users_blogs';
  $methods['drupal.getBlogId']     = 'drupaltowp_xmlrpc_get_blog_id';
  $methods['drupal.getCategories'] = 'drupaltowp_xmlrpc_get_categories';
  $methods['drupal.newUser']       = 'drupaltowp_xmlrpc_create_user';
  $methods['drupal.deleteUser']    = 'drupaltowp_xmlrpc_delete_user';
  
  return $methods;
}

add_filter( 'xmlrpc_methods', 'drupaltowp_xmlrpc_methods' );
