<?php

/*
 * Plugin Name: wp-xmlrpc-modernization
 * Description: This plugin extends the basic XML-RPC API exposed by WordPress. Derived from GSoC '11 project.
 * Version: 0.9
 * Author: Max Cutler
 * Author URI: http://www.maxcutler.com
 *
*/

add_filter( 'wp_xmlrpc_server_class', 'replace_xmlrpc_server_class' );

function replace_xmlrpc_server_class( $class_name ) {
	// only replace the default XML-RPC class if another plug-in hasn't already changed it
	if ( $class_name === 'wp_xmlrpc_server' ) {
		include_once( 'class-wp-xmlrpc-server-ext.php' );
		return 'wp_xmlrpc_server_ext';
	} else {
		return $class_name;
	}
}

?>