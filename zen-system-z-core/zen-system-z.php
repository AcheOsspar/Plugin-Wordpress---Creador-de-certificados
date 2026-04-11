<?php
/**
 * Plugin Name:       Zen System Z (Core)
 * Description:       Núcleo modular: CPTs compatibles con datos existentes, meta boxes y verificador público. Sin generación PDF en este módulo.
 * Version:             1.0.0
 * Author:              Zen Activo
 * Text Domain:         zen-system-z
 * Requires at least:   5.8
 * Requires PHP:        7.4
 *
 * @package Zen_System_Z
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ZSZ_VERSION', '1.0.0' );
define( 'ZSZ_PATH', plugin_dir_path( __FILE__ ) );
define( 'ZSZ_URL', plugin_dir_url( __FILE__ ) );

spl_autoload_register(
	static function ( $class ) {
		if ( strpos( $class, 'ZSZ_', 0 ) !== 0 ) {
			return;
		}
		$map = array(
			'ZSZ_CPT_Registrar' => 'includes/class-zsz-cpt-registrar.php',
			'ZSZ_Verificador'   => 'includes/class-zsz-verificador.php',
		);
		if ( isset( $map[ $class ] ) ) {
			$file = ZSZ_PATH . $map[ $class ];
			if ( is_readable( $file ) ) {
				require_once $file;
			}
		}
	}
);

/**
 * Tras activar: reglas de rewrite para CPTs.
 */
function zsz_activate_plugin() {
	if ( class_exists( 'ZSZ_CPT_Registrar' ) ) {
		$reg = new ZSZ_CPT_Registrar();
		$reg->register_post_types();
	}
	flush_rewrite_rules();
}

/**
 * Al desactivar: limpiar reglas.
 */
function zsz_deactivate_plugin() {
	flush_rewrite_rules();
}

register_activation_hook( __FILE__, 'zsz_activate_plugin' );
register_deactivation_hook( __FILE__, 'zsz_deactivate_plugin' );

add_action(
	'plugins_loaded',
	static function () {
		load_plugin_textdomain( 'zen-system-z', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		if ( class_exists( 'ZSZ_CPT_Registrar' ) ) {
			( new ZSZ_CPT_Registrar() )->init();
		}
		if ( class_exists( 'ZSZ_Verificador' ) ) {
			( new ZSZ_Verificador() )->init();
		}
	},
	5
);
