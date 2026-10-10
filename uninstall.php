<?php
/**
 * Remove as configurações e os metadados do plugin ao desinstalar.
 *
 * @package SeoLegibilidade
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

delete_option( 'seol_settings' );
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '\\_seol\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DELETE FROM {$wpdb->termmeta} WHERE meta_key LIKE '\\_seol\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
