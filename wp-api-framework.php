<?php

/*
  Plugin Name: WordPress API Framework
  Description: A simple API plugin for WordPress that is easily extendable.
  Version: 1.0
  Author: Michael Dearman
  Author URI: http://mickeyuk.github.io
 */

// If plugin called directly
if (!function_exists('add_action')) {
    echo 'Here be dragons...';
    exit;
}

/**
 * Plugin version.
 */
define('WPAPI_VERSION', '1.0');

/**
 * Plugin filename.
 */
define('WPAPI_FILE', __FILE__);

/**
 * Plugin slug.
 */
define('WPAPI_SLUG', 'wpapi');

/** 
 * Plugin directory.
 */
define('WPAPI_DIR', plugin_dir_path(__FILE__));

/**
 * Text Domain
 */
define('WPAPI_DOMAIN', 'wp-api-framework');

// Authentication_JWT
if (!class_exists('JWT')) {
    require_once(WPAPI_DIR . 'inc/jwt.class.php');
}

// Base class
require_once(WPAPI_DIR . 'inc/wpapi.class.php');

// Plugin activation
register_activation_hook(WPAPI_FILE, array('WPAPI', 'plugin_activation'));
register_deactivation_hook(WPAPI_FILE, array('WPAPI', 'plugin_deactivation'));

// Initiate
add_action('init',array('WPAPI', 'init'));

// If logged in to admin dashboard
if (is_admin()) {
    
    // Admin class
    require_once(WPAPI_DIR . 'inc/wpapi-admin.class.php');
    
    // Admin hooks
    add_action('init',array('WPAPI_Admin', 'admin_hooks'));
    
}