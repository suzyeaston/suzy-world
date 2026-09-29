<?php
declare(strict_types=1);

/**
 * Plugin Name: SUZY//WORLD
 * Description: Public, versioned knowledge graph and REST API for Suzy Easton's deliberately published worlds.
 * Version: 0.1.1
 * Author: Suzy Easton
 * Text Domain: suzy-world
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SUZY_WORLD_VERSION', '0.1.1');
define('SUZY_WORLD_DB_VERSION', '1');
define('SUZY_WORLD_FILE', __FILE__);
define('SUZY_WORLD_PATH', plugin_dir_path(__FILE__));
define('SUZY_WORLD_URL', plugin_dir_url(__FILE__));

require_once SUZY_WORLD_PATH . 'includes/Schema.php';
require_once SUZY_WORLD_PATH . 'includes/Repository.php';
require_once SUZY_WORLD_PATH . 'includes/Importer.php';
require_once SUZY_WORLD_PATH . 'includes/Api.php';
require_once SUZY_WORLD_PATH . 'includes/Admin.php';
require_once SUZY_WORLD_PATH . 'includes/Shortcode.php';

use SuzyWorld\Admin;
use SuzyWorld\Api;
use SuzyWorld\Schema;
use SuzyWorld\Shortcode;

register_activation_hook(
    __FILE__,
    static function (): void {
        Schema::install();
        flush_rewrite_rules(false);
    }
);

add_action(
    'plugins_loaded',
    static function (): void {
        if (version_compare((string) get_option('suzy_world_db_version', '0'), SUZY_WORLD_DB_VERSION, '<')) {
            Schema::install();
        }

        Api::bootstrap();
        Admin::bootstrap();
        Shortcode::bootstrap();
    },
    5
);
