<?php

/** @var string Directory containing all of the site's files */
$root_dir = dirname(__DIR__);

/** @var string Document Root */
$webroot_dir = $root_dir . '/web';

/**
 * Expose global env() function from oscarotero/env
 */
Env::init();

/**
 * Use Dotenv to set required environment variables and load .env file in root
 */
$dotenv = new Dotenv\Dotenv($root_dir);
if (file_exists($root_dir . '/.env')) {
    $dotenv->load();
    $dotenv->required(['DB_NAME', 'DB_USER', 'DB_PASSWORD', 'WP_HOME', 'WP_SITEURL']);
}

/**
 * Set up our global environment constant and load its config first
 * Default: production
 */
define('WP_ENV', env('WP_ENV') ?: 'production');

$env_config = __DIR__ . '/environments/' . WP_ENV . '.php';

if (file_exists($env_config)) {
    require_once $env_config;
}

/**
 * URLs
 */
define('WP_HOME', env('WP_HOME')?: 'http://yellowstudio.staging.theroguepixel.com');
define('WP_SITEURL', env('WP_SITEURL')?: 'http://yellowstudio.staging.theroguepixel.com/wp');

/**
 * Custom Content Directory
 */
define('CONTENT_DIR', '/app');
define('WP_CONTENT_DIR', $webroot_dir . CONTENT_DIR);
define('WP_CONTENT_URL', WP_HOME . CONTENT_DIR);

/**
 * DB settings
 */
define('DB_NAME', env('DB_NAME')?: 'therotvm_yellow_studio');
define('DB_USER', env('DB_USER')?: 'therotvm_ylostdo');
define('DB_PASSWORD', env('DB_PASSWORD')?: 'Gx%eT@do??oI');
define('DB_HOST', env('DB_HOST') ?: 'localhost');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');
$table_prefix = env('DB_PREFIX') ?: 'wp_';

/**
 * Authentication Unique Keys and Salts
 */
define('AUTH_KEY', env('AUTH_KEY')?: '9cCh>uc`rvS?30DF$&U3?urPPJL>#&81Mz(aM/OzYmF@/eyf@Q&RLtfWK|QH$$,*');
define('SECURE_AUTH_KEY', env('SECURE_AUTH_KEY')?: 'fAp<(1Hso:|IVB+QHQ4Z7??)*lmz+%wNx,w_|yTHj*0:=:c]UNIH>L03F2!a=3WA');
define('LOGGED_IN_KEY', env('LOGGED_IN_KEY')?: 'dCT5nd*?k_b`t6&J),D:c}=ty|yz>?3<@/O`D#8[;A_2h1D6qHvsLy2%7U$Z|1v,');
define('NONCE_KEY', env('NONCE_KEY')?: '1+g=Efqq<kU{_l7/?,<3vTCyHYkc`z5PaYyB.:_[R,I%{.OzN0eSx+qUWu1$,=|)');
define('AUTH_SALT', env('AUTH_SALT')?: 'NM5z31!}$q+[V;YA1hJ{bfy8@uyPr*#8uwh|sL;eLDw}?:bNBU.E4Ke^?8@43+Cx');
define('SECURE_AUTH_SALT', env('SECURE_AUTH_SALT')?: 'fsv6biK}nh%.i5Qf2x&uMzV/$VL54*Up=t$R4#D^Oh_57I-:u]iEhE{gdjh=qhj{');
define('LOGGED_IN_SALT', env('LOGGED_IN_SALT')?: '/WyX`ZoA,N47y3[*jbY+Tz7.%}OA@*=1k9LJ#gi9H/UWiet})8`q`698QlqLA=K_');
define('NONCE_SALT', env('NONCE_SALT')?: 'zTND7P@yB_}/x13HJf.:8.g$lN%^lQkE`h{Z,K:EcC-@-:4L#?K/18X9nMkc:8qW');

/**
 * Custom Settings
 */
define('AUTOMATIC_UPDATER_DISABLED', true);
define('DISABLE_WP_CRON', env('DISABLE_WP_CRON') ?: false);
define('DISALLOW_FILE_EDIT', true);

/**
 * Bootstrap WordPress
 */
if (!defined('ABSPATH')) {
    define('ABSPATH', $webroot_dir . '/wp/');
}
