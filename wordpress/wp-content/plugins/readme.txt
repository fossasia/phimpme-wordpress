=== XML-RPC Extended Media Upload ===
Contributors: rvencu
Donate link: 
Tags: XML-RPC, API, extension, media, upload
Requires at least: 3.0
Tested up to: 3.3.2
Stable tag: 0.1
License: GPLv2

New XML-RPC Method: Upload a new media file with specified author and parent post.

== Description ==

This plugin extends the default XML-RPC server API with one new method for specifying author and parent post of remotely uploaded attachment files.

1. preserved backward compatibility with the basic method wp.uploadFile
1. simple usage, just replace with wp_extended.uploadFile and add 2 OPTIONAL parameters to the data struct: author (string, user_id of author) and parent (string, post_id of parent post)
1. without the optional parameters the new method is identical in behaviour with the basic method found in Wordpress API of XML-RPC

== Installation ==

1. Upload the zip file to the `/wp-content/plugins/` directory
1. Unzip the archive
1. Activate the plugin through the 'Plugins' menu in WordPress


== Screenshots ==

No screenshots available

== Frequently Asked Questions ==

none yet

== Changelog ==

= 0.1 =
Incipient version


== Upgrade Notice ==

Nothing here yet
