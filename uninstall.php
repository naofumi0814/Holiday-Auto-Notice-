<?php
/**
 * Holiday Auto Notice アンインストール処理
 *
 * プラグイン削除時にデータを完全に削除する
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// オプション削除
delete_option( 'han_settings' );
delete_option( 'han_templates' );
delete_option( 'han_db_version' );

// 独自テーブル削除
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}han_calendar" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}han_logs" );

// 自動生成投稿のメタデータを削除（投稿自体は残す）
$wpdb->delete( $wpdb->postmeta, [ 'meta_key' => '_han_holiday_date' ] );
$wpdb->delete( $wpdb->postmeta, [ 'meta_key' => '_han_holiday_type' ] );
$wpdb->delete( $wpdb->postmeta, [ 'meta_key' => '_han_generated' ] );

// Cronイベント削除
$timestamp = wp_next_scheduled( 'han_daily_check' );
if ( $timestamp ) {
    wp_unschedule_event( $timestamp, 'han_daily_check' );
}
