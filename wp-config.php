<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * Localized language
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'local' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', 'root' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',          '-N/ptA)3W|UO.~szS51Mr(QP9/(p;pzf*~4,tx1>D2GgTV]$r*{=jmdu=Sq7FHhv' );
define( 'SECURE_AUTH_KEY',   'M@d*JfWkYj.wDO-Fg}7|xC25Z e1~>r>eHYQ-$bfbd=>h,`DSwi:*RwknYmXqu<5' );
define( 'LOGGED_IN_KEY',     'R4%uu[1;`=H %N&ksC%Ml.M+CkL)Yd6V0f:=c90:Si65pID{=+(%%30t(2*_maf^' );
define( 'NONCE_KEY',         'n/^obLlC3L&95<C|Sg=o)jc=pA#,a@:F=t p: SlQM|_3bMLBo2;-R~5rzCNPSOY' );
define( 'AUTH_SALT',         'A1kTsUgu,|c%nu=sDY/MVVK!1l*<|-BtQ^Q4`=)RD[nua=t`mLq?b3R^5Q{<(4gs' );
define( 'SECURE_AUTH_SALT',  'iUQ[+64[&M}GR@T~M*W!LSsZ^5c-(Q4-1{;$9jxKavG?%Oe3=7Z+;c;:]R^Qd{z2' );
define( 'LOGGED_IN_SALT',    '}$VR8H(nx/;MdIf2/Wi,g]n%&k<e-R^`,zV wVL<I@[!q{BNl^TJSWf?A3&aCoix' );
define( 'NONCE_SALT',        'wgIffH|`&#AS/q|Klb8-t#B66u6G&(U1Fi-QCfcd!>U=]{@G-j}aVs._Jz<XPibt' );
define( 'WP_CACHE_KEY_SALT', 'T!3DxQ3hEGBtbX-jLp1wg+}d6ksF7mf3%pApSY{m?@|`dAl-O!hrOxi(2~X*bcB.' );


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';


/* Add any custom values between this line and the "stop editing" line. */



/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', false );
}

define( 'WP_ENVIRONMENT_TYPE', 'local' );
/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
