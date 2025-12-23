<?php
/**
 * Plugin Name: GSlider
 * Description: Cover slider switch block (editor UX + front enqueue).
 * Version: 0.1.0
 * Author: Paweł Nowak
 * Text Domain: gslider
 * Requires PHP: 7.4
 * Requires at least: 6.9
 */

namespace GSlider\CoverSlider;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const GSLIDER_FILE = __FILE__;
const GSLIDER_DIR  = __DIR__ . '/';

require_once __DIR__ . '/src/Init.php';

add_action( 'plugins_loaded', function () {
    Init::register();
} );
