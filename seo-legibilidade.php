<?php
/**
 * Plugin Name:       SEO & Legibilidade
 * Plugin URI:        https://github.com/joaogabrielmontinirossi-sys/seo
 * Description:       Analisa e corrige SEO e legibilidade de posts e páginas: frase-chave, título, meta descrição, slug, imagens, links, Flesch (adaptado ao português), voz passiva, palavras de transição, além de meta tags, Open Graph, dados estruturados (JSON-LD), canonical, robots e sitemap XML.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            João Gabriel Montini Rossi
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       seo-legibilidade
 * Domain Path:       /languages
 *
 * @package SeoLegibilidade
 */

defined( 'ABSPATH' ) || exit;

define( 'SEOL_VERSION', '1.0.0' );
define( 'SEOL_FILE', __FILE__ );
define( 'SEOL_DIR', plugin_dir_path( __FILE__ ) );
define( 'SEOL_URL', plugin_dir_url( __FILE__ ) );

require_once SEOL_DIR . 'includes/analysis/class-text.php';
require_once SEOL_DIR . 'includes/analysis/class-language.php';
require_once SEOL_DIR . 'includes/analysis/class-result.php';
require_once SEOL_DIR . 'includes/analysis/class-readability-analyzer.php';
require_once SEOL_DIR . 'includes/analysis/class-seo-analyzer.php';
require_once SEOL_DIR . 'includes/class-settings.php';
require_once SEOL_DIR . 'includes/class-meta.php';
require_once SEOL_DIR . 'includes/class-templates.php';
require_once SEOL_DIR . 'includes/class-autofix.php';
require_once SEOL_DIR . 'includes/class-analysis-service.php';
require_once SEOL_DIR . 'includes/class-frontend.php';
require_once SEOL_DIR . 'includes/class-schema.php';
require_once SEOL_DIR . 'includes/class-sitemap.php';
require_once SEOL_DIR . 'includes/class-rest.php';
require_once SEOL_DIR . 'includes/class-admin.php';

add_action(
	'plugins_loaded',
	static function () {
		load_plugin_textdomain( 'seo-legibilidade', false, dirname( plugin_basename( SEOL_FILE ) ) . '/languages' );

		SeoLegibilidade\Meta::register();
		SeoLegibilidade\Autofix::register();
		SeoLegibilidade\Frontend::register();
		SeoLegibilidade\Schema::register();
		SeoLegibilidade\Sitemap::register();
		SeoLegibilidade\Rest::register();
		if ( is_admin() ) {
			SeoLegibilidade\Admin::register();
		}
	}
);

register_activation_hook(
	SEOL_FILE,
	static function () {
		if ( false === get_option( SeoLegibilidade\Settings::OPTION ) ) {
			add_option( SeoLegibilidade\Settings::OPTION, SeoLegibilidade\Settings::defaults() );
		}
		flush_rewrite_rules();
	}
);
