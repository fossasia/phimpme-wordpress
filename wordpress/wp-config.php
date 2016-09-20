<?php
/**
 * The base configurations of the WordPress.
 *
 * This file has the following configurations: MySQL settings, Table Prefix,
 * Secret Keys, WordPress Language, and ABSPATH. You can find more information
 * by visiting {@link http://codex.wordpress.org/Editing_wp-config.php Editing
 * wp-config.php} Codex page. You can get the MySQL settings from your web host.
 *
 * This file is used by the wp-config.php creation script during the
 * installation. You don't have to use the web site, you can just copy this file
 * to "wp-config.php" and fill in the values.
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define('DB_NAME', 'phimpme_wordpress');

/** MySQL database username */
define('DB_USER', 'your mysql username');

/** MySQL database password */
define('DB_PASSWORD', 'your mysql password');

/** MySQL hostname */
define('DB_HOST', 'localhost');

/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8');

/** The Database Collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

define('WP_HOME','http://'.$_SERVER['HTTP_HOST'].'/wordpress');
define('WP_SITEURL','http://'.$_SERVER['HTTP_HOST'].'/wordpress');
/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         '+CND.;/tD^WGHt&uyjP=6%3?)}!N7;O!QPj7t6W!{YR+v`kcXX|II|DUxyE pm !');
define('SECURE_AUTH_KEY',  '56VrZVFUg!jW}-Hmx6Dw>`_3z|L#-$DBX`,){%E6xvB@O)FDh`.2}/L=biw$2.s9');
define('LOGGED_IN_KEY',    'VOugvHv|0j8hiSXT2j<8|<!}qoH$;.=InIRr1c70>PepXgWY+zf- $?6b9M<}J[A');
define('NONCE_KEY',        '$DGd+F-:D>l3^^gD41vyMGyc~.yAd8l<ZFsl9YdVlta+u-J(j8UQ}Lk.pK-d ,CV');
define('AUTH_SALT',        '{3x{yq_p`mQ#me]ETc &UmcF5h8oM?fTUb3Y=&%,jWB~RPGVqxD]Pg|nS&;668=x');
define('SECURE_AUTH_SALT', '<6)H8D+6j#~dx?H(v.Q)M4jf7-3|rbB.`)JtPY70:K-Wle_Y+2z8agTcwaKCq=<L');
define('LOGGED_IN_SALT',   '1s.ZtP--[lOc.+5AGP*k&04|F}iC7S9D^`TA<HI=A4,Ver?qCHb]U>pw+=Z-3/ZX');
define('NONCE_SALT',       'f:uDPeqNZO2{q7Ocqke]!A@leQUqx1k~no/U%X_YCB3l~<WKgW ?``JM;v-?:e{o');

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each a unique
 * prefix. Only numbers, letters, and underscores please!
 */
$table_prefix  = 'wp_';

/**
 * WordPress Localized Language, defaults to English.
 *
 * Change this to localize WordPress. A corresponding MO file for the chosen
 * language must be installed to wp-content/languages. For example, install
 * de_DE.mo to wp-content/languages and set WPLANG to 'de_DE' to enable German
 * language support.
 */
define('WPLANG', '');

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 */
define('WP_DEBUG', false);

/* That's all, stop editing! Happy blogging. */

/** Absolute path to the WordPress directory. */

if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');
