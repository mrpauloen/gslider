<?php
namespace GSlider\CoverSlider;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Init {
    public static function register(): void {
        add_action( 'init', [ self::class, 'register_assets_and_block' ] );
        add_action( 'enqueue_block_editor_assets', [ self::class, 'enqueue_editor_assets' ] );
    }

    public static function register_assets_and_block(): void {
        $plugin_dir = dirname( __DIR__ ) . '/';

        // Register front assets
        $front_js  = $plugin_dir . 'assets/gslider.js';
        $front_css = $plugin_dir . 'assets/gslider.css';

        if ( file_exists( $front_js ) ) {
            wp_register_script(
                'gslider.js',
                plugins_url( 'assets/gslider.js', GSLIDER_FILE ),
                [],
                filemtime( $front_js ),
                true
            );
        }

        if ( file_exists( $front_css ) ) {
            wp_register_style(
                'gslider.css',
                plugins_url( 'assets/gslider.css', GSLIDER_FILE ),
                [],
                filemtime( $front_css )
            );
        }

        // Editor assets (built by @wordpress/scripts into build/index.js)
        $editor_build = $plugin_dir . 'build/index.js';
        $editor_css   = $plugin_dir . 'assets/editor.css';

        if ( file_exists( $editor_build ) ) {
            $asset_file = $plugin_dir . 'build/index.asset.php';
            $deps = [];
            $ver = filemtime( $editor_build );
            if ( file_exists( $asset_file ) ) {
                $asset = require $asset_file;
                if ( isset( $asset['dependencies'] ) && is_array( $asset['dependencies'] ) ) {
                    $deps = $asset['dependencies'];
                }
                if ( isset( $asset['version'] ) ) {
                    $ver = $asset['version'];
                }
            }

            wp_register_script(
                'gslider-editor-js',
                plugins_url( 'build/index.js', GSLIDER_FILE ),
                $deps,
                $ver,
                true
            );
        }

        $editor_css_build = $plugin_dir . 'build/index.css';
        if ( file_exists( $editor_css_build ) ) {
            wp_register_style(
                'gslider-editor-css',
                plugins_url( 'build/index.css', GSLIDER_FILE ),
                [],
                filemtime( $editor_css_build )
            );
        } elseif ( file_exists( $editor_css ) ) {
            wp_register_style(
                'gslider-editor-css',
                plugins_url( 'assets/editor.css', GSLIDER_FILE ),
                [],
                filemtime( $editor_css )
            );
        }

        // Register blocks from build/ (build is the source for registered metadata)
        $build_dir = dirname( __DIR__ ) . '/build';
        $manifest_file = $build_dir . '/blocks-manifest.php';

        if ( file_exists( $manifest_file ) ) {
            if ( function_exists( 'wp_register_block_types_from_metadata_collection' ) ) {
                wp_register_block_types_from_metadata_collection( $build_dir, $manifest_file );
            } elseif ( function_exists( 'wp_register_block_metadata_collection' ) ) {
                wp_register_block_metadata_collection( $build_dir, $manifest_file );
            } else {
                $manifest_data = require $manifest_file;
                foreach ( array_keys( $manifest_data ) as $block_type ) {
                    register_block_type( $build_dir . "/{$block_type}" );
                }
            }

            // For our block we will NOT rely on automatic front enqueueing during
            // `wp_enqueue_scripts`. Previously scripts/styles could be enqueued
            // globally (for example via a wp_enqueue_scripts hook without
            // checking whether the block is present) which caused assets to load
            // on every page. That behavior typically stemmed from calling
            // enqueue during `wp_enqueue_scripts` with loose conditions or using
            // `viewScript`/`viewStyle` in block metadata without server-side
            // control. To ensure assets are loaded only when the block is actually
            // used (including when block is rendered inside templates / template
            // parts in FSE), we attach a server-side `render_callback` which
            // will enqueue assets and inject the block config (see `render_block`).

            $our_block_dir = $build_dir . '/block-cover-slider';
            if ( file_exists( $our_block_dir ) ) {
                register_block_type( $our_block_dir, [
                    // editor_script/style are handled by the build registration
                    // Keep a server-side render callback so we can enqueue and
                    // pass config when the block is present in the render flow.
                    'render_callback' => [ self::class, 'render_block' ],
                ] );
            }
        }
    }

    public static function enqueue_editor_assets(): void {
        if ( wp_script_is( 'gslider-editor-js', 'registered' ) ) {
            wp_enqueue_script( 'gslider-editor-js' );
        }
        if ( wp_style_is( 'gslider-editor-css', 'registered' ) ) {
            wp_enqueue_style( 'gslider-editor-css' );
        }
    }

    // NOTE: front assets are enqueued by `render_block()` only when the block
    // is actually rendered. This guarantees scripts/styles load only for pages
    // that include the block (or when the block is rendered as part of a
    // template/template-part in FSE). See `render_block()` for details.


    public static function render_block( $attributes = [], $content = '' ) {
        // Block must not print visible HTML on the front — return empty string.
        // Instead enqueue assets and pass sanitized config to the front-end JS.
        static $did = false;

        // Only run on frontend render (not in admin or REST preview rendering)
        if ( is_admin() ) {
            return '';
        }

        // Defaults
        $defaults = [
            'enabled' => true,
            'intervalMs' => 10000,
            'fadeMs' => 900,
            'overlayDim' => 30,
            'targetCoverSelector' => '.wp-block-cover.WPBlockCoverSlider',
            'sourceSelector' => '.czik-hero-rotator__src',
            'debugHighlight' => true,
        ];

        $attrs = is_array( $attributes ) ? $attributes : [];

        // Sanitize and normalize
        $cfg = [];
        $cfg['enabled'] = isset( $attrs['enabled'] ) ? (bool) $attrs['enabled'] : (bool) $defaults['enabled'];
        $cfg['intervalMs'] = isset( $attrs['intervalMs'] ) ? absint( $attrs['intervalMs'] ) : $defaults['intervalMs'];
        if ( $cfg['intervalMs'] < 500 ) {
            $cfg['intervalMs'] = $defaults['intervalMs'];
        }
        $cfg['fadeMs'] = isset( $attrs['fadeMs'] ) ? absint( $attrs['fadeMs'] ) : $defaults['fadeMs'];
        if ( $cfg['fadeMs'] < 100 ) {
            $cfg['fadeMs'] = $defaults['fadeMs'];
        }
        $cfg['overlayDim'] = isset( $attrs['overlayDim'] ) ? absint( $attrs['overlayDim'] ) : $defaults['overlayDim'];
        if ( $cfg['overlayDim'] < 0 ) {
            $cfg['overlayDim'] = 0;
        } elseif ( $cfg['overlayDim'] > 100 ) {
            $cfg['overlayDim'] = 100;
        }
        $cfg['targetCoverSelector'] = isset( $attrs['targetCoverSelector'] ) ? sanitize_text_field( $attrs['targetCoverSelector'] ) : $defaults['targetCoverSelector'];
        $cfg['sourceSelector'] = isset( $attrs['sourceSelector'] ) ? sanitize_text_field( $attrs['sourceSelector'] ) : $defaults['sourceSelector'];
        $cfg['debugHighlight'] = isset( $attrs['debugHighlight'] ) ? (bool) $attrs['debugHighlight'] : (bool) $defaults['debugHighlight'];

        // Enqueue once per request — first block instance wins. If you need to
        // support multiple configs, this can be extended to collect instances
        // and pass an array to the frontend.
        if ( ! $did ) {
            if ( wp_style_is( 'gslider.css', 'registered' ) ) {
                wp_enqueue_style( 'gslider.css' );
            }
            if ( wp_script_is( 'gslider.js', 'registered' ) ) {
                // inject config before the script
                wp_add_inline_script( 'gslider.js', 'window.GSliderCoverSlider = ' . wp_json_encode( $cfg ) . ';', 'before' );
                wp_enqueue_script( 'gslider.js' );
            }
            $did = true;
        }

        return '';
    }
}
