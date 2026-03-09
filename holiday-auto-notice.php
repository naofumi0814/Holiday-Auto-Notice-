<?php
/**
 * Plugin Name: Holiday Auto Notice
 * Plugin URI:  https://example.com/holiday-auto-notice
 * Description: 年間休業カレンダーを管理し、休業前に自動でお知らせ記事を投稿、当日はサイト上の営業ステータスを自動切替するプラグイン
 * Version:     1.0.0
 * Author:      Holiday Auto Notice Team
 * Author URI:  https://example.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: holiday-auto-notice
 * Domain Path: /languages
 * Requires PHP: 8.1
 * Requires at least: 6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// プラグイン定数
define( 'HAN_VERSION', '1.0.0' );
define( 'HAN_PLUGIN_FILE', __FILE__ );
define( 'HAN_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'HAN_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'HAN_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * メインプラグインクラス（シングルトン）
 */
final class Holiday_Auto_Notice {

    private static ?self $instance = null;

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * 依存ファイルの読み込み
     */
    private function load_dependencies(): void {
        $includes = HAN_PLUGIN_DIR . 'includes/';

        require_once $includes . 'class-han-settings.php';
        require_once $includes . 'class-han-calendar.php';
        require_once $includes . 'class-han-template.php';
        require_once $includes . 'class-han-post-generator.php';
        require_once $includes . 'class-han-cron.php';
        require_once $includes . 'class-han-shortcode.php';
        require_once $includes . 'class-han-logger.php';
        require_once $includes . 'class-han-csv.php';

        if ( is_admin() ) {
            require_once $includes . 'admin/class-han-admin.php';
            require_once $includes . 'admin/class-han-admin-calendar.php';
            require_once $includes . 'admin/class-han-admin-templates.php';
            require_once $includes . 'admin/class-han-admin-settings.php';
            require_once $includes . 'admin/class-han-admin-logs.php';
        }
    }

    /**
     * フック登録
     */
    private function init_hooks(): void {
        register_activation_hook( HAN_PLUGIN_FILE, [ $this, 'activate' ] );
        register_deactivation_hook( HAN_PLUGIN_FILE, [ $this, 'deactivate' ] );

        add_action( 'init', [ $this, 'load_textdomain' ] );
        add_action( 'init', [ $this, 'init_components' ] );
    }

    /**
     * コンポーネント初期化
     */
    public function init_components(): void {
        HAN_Cron::get_instance();
        HAN_Shortcode::get_instance();

        if ( is_admin() ) {
            HAN_Admin::get_instance();
        }
    }

    /**
     * テキストドメイン読み込み
     */
    public function load_textdomain(): void {
        load_plugin_textdomain(
            'holiday-auto-notice',
            false,
            dirname( HAN_PLUGIN_BASENAME ) . '/languages'
        );
    }

    /**
     * プラグイン有効化
     */
    public function activate(): void {
        $this->create_tables();
        HAN_Settings::set_defaults();
        HAN_Template::set_defaults();
        HAN_Cron::schedule_events();
        flush_rewrite_rules();
    }

    /**
     * プラグイン無効化
     */
    public function deactivate(): void {
        HAN_Cron::unschedule_events();
        flush_rewrite_rules();
    }

    /**
     * データベーステーブル作成
     */
    private function create_tables(): void {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // カレンダーテーブル
        $table_calendar = $wpdb->prefix . 'han_calendar';
        $sql_calendar = "CREATE TABLE {$table_calendar} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            cal_date DATE NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'normal',
            holiday_type VARCHAR(20) DEFAULT NULL,
            memo TEXT DEFAULT NULL,
            custom_title TEXT DEFAULT NULL,
            custom_body TEXT DEFAULT NULL,
            custom_day_message TEXT DEFAULT NULL,
            auto_post TINYINT(1) NOT NULL DEFAULT 1,
            post_id BIGINT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_cal_date (cal_date),
            KEY idx_status (status),
            KEY idx_post_id (post_id)
        ) {$charset_collate};";

        // ログテーブル
        $table_logs = $wpdb->prefix . 'han_logs';
        $sql_logs = "CREATE TABLE {$table_logs} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            log_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            log_level VARCHAR(10) NOT NULL DEFAULT 'info',
            log_type VARCHAR(50) NOT NULL,
            message TEXT NOT NULL,
            context TEXT DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_log_date (log_date),
            KEY idx_log_type (log_type)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql_calendar );
        dbDelta( $sql_logs );

        update_option( 'han_db_version', HAN_VERSION );
    }
}

// プラグイン起動
Holiday_Auto_Notice::get_instance();
