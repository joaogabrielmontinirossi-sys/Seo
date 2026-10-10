<?php
/**
 * Configurações globais do plugin.
 *
 * @package SeoLegibilidade
 */

namespace SeoLegibilidade;

use SeoLegibilidade\Analysis\Language;

defined( 'ABSPATH' ) || exit;

class Settings {

	const OPTION = 'seol_settings';

	public static function defaults() {
		return array(
			'language'                 => 'auto',
			'separator'                => '-',
			'title_template_singular'  => '%%title%% %%sep%% %%sitename%%',
			'title_template_home'      => '%%sitename%% %%sep%% %%sitedesc%%',
			'title_template_archive'   => '%%term%% %%page%% %%sep%% %%sitename%%',
			'home_description'         => '',
			'entity_type'              => 'Organization',
			'entity_name'              => '',
			'entity_logo'              => '',
			'same_as'                  => '',
			'default_og_image'         => '',
			'twitter_site'             => '',
			'google_verification'      => '',
			'bing_verification'        => '',
			'article_type'             => 'BlogPosting',
			'noindex_date_archives'    => 1,
			'noindex_author_archives'  => 0,
			'redirect_attachments'     => 1,
			'auto_alt'                 => 1,
			'auto_description'         => 1,
			'min_words'                => 300,
		);
	}

	public static function all() {
		$saved = get_option( self::OPTION, array() );
		return array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
	}

	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Idioma das análises ('pt' ou 'en').
	 */
	public static function language() {
		$lang = self::get( 'language' );
		if ( in_array( $lang, array( 'pt', 'en' ), true ) ) {
			return $lang;
		}
		return Language::from_locale( get_locale() );
	}

	public static function sanitize( $input ) {
		$defaults = self::defaults();
		$input    = is_array( $input ) ? $input : array();
		$out      = array();
		foreach ( $defaults as $key => $default ) {
			$value = isset( $input[ $key ] ) ? $input[ $key ] : ( is_int( $default ) && ! in_array( $key, array( 'min_words' ), true ) ? 0 : $default );
			switch ( $key ) {
				case 'entity_logo':
				case 'default_og_image':
					$out[ $key ] = esc_url_raw( trim( $value ) );
					break;
				case 'same_as':
					$urls        = array_filter( array_map( 'esc_url_raw', array_map( 'trim', preg_split( '/\R/', (string) $value ) ) ) );
					$out[ $key ] = implode( "\n", $urls );
					break;
				case 'home_description':
					$out[ $key ] = sanitize_textarea_field( $value );
					break;
				case 'twitter_site':
					$out[ $key ] = ltrim( sanitize_text_field( $value ), '@' );
					break;
				case 'language':
					$out[ $key ] = in_array( $value, array( 'auto', 'pt', 'en' ), true ) ? $value : 'auto';
					break;
				case 'entity_type':
					$out[ $key ] = 'Person' === $value ? 'Person' : 'Organization';
					break;
				case 'article_type':
					$out[ $key ] = in_array( $value, array( 'Article', 'BlogPosting', 'NewsArticle' ), true ) ? $value : 'BlogPosting';
					break;
				case 'min_words':
					$out[ $key ] = max( 50, min( 5000, (int) $value ) );
					break;
				case 'google_verification':
				case 'bing_verification':
					// Aceita a tag inteira colada e extrai só o código.
					$value       = (string) $value;
					$out[ $key ] = sanitize_text_field( preg_match( '/content=["\']([^"\']+)/', $value, $m ) ? $m[1] : $value );
					break;
				default:
					$out[ $key ] = is_int( $default ) ? ( empty( $value ) ? 0 : 1 ) : sanitize_text_field( $value );
			}
		}
		return $out;
	}
}
