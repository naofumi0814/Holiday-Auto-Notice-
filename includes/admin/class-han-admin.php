<?php
/**
 * 管理画面統括クラス
 *
 * メニュー登録、共通アセット読み込み、AJAXハンドラの統括
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HAN_Admin {

    private static ?self $instance = null;

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', [ $this, 'register_menus' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'admin_init', [ $this, 'handle_actions' ] );

        // AJAX
        add_action( 'wp_ajax_han_save_day', [ $this, 'ajax_save_day' ] );
        add_action( 'wp_ajax_han_preview', [ $this, 'ajax_preview' ] );
        add_action( 'wp_ajax_han_manual_run', [ $this, 'ajax_manual_run' ] );
        add_action( 'wp_ajax_han_test_run', [ $this, 'ajax_test_run' ] );
        add_action( 'wp_ajax_han_generate_single', [ $this, 'ajax_generate_single' ] );
        add_action( 'wp_ajax_han_export_csv', [ $this, 'ajax_export_csv' ] );
    }

    /**
     * メニュー登録
     */
    public function register_menus(): void {
        add_menu_page(
            'Holiday Auto Notice',
            '休業カレンダー',
            'manage_options',
            'han-calendar',
            [ HAN_Admin_Calendar::get_instance(), 'render_page' ],
            'dashicons-calendar-alt',
            30
        );

        add_submenu_page(
            'han-calendar',
            'カレンダー管理',
            'カレンダー管理',
            'manage_options',
            'han-calendar',
            [ HAN_Admin_Calendar::get_instance(), 'render_page' ]
        );

        add_submenu_page(
            'han-calendar',
            'テンプレート管理',
            'テンプレート管理',
            'manage_options',
            'han-templates',
            [ HAN_Admin_Templates::get_instance(), 'render_page' ]
        );

        add_submenu_page(
            'han-calendar',
            '設定',
            '設定',
            'manage_options',
            'han-settings',
            [ HAN_Admin_Settings::get_instance(), 'render_page' ]
        );

        add_submenu_page(
            'han-calendar',
            'ログ',
            'ログ',
            'manage_options',
            'han-logs',
            [ HAN_Admin_Logs::get_instance(), 'render_page' ]
        );
    }

    /**
     * 管理画面アセット読み込み
     */
    public function enqueue_assets( string $hook ): void {
        // プラグインページのみ
        if ( ! str_contains( $hook, 'han-' ) ) {
            return;
        }

        wp_enqueue_style(
            'han-admin',
            HAN_PLUGIN_URL . 'assets/css/admin.css',
            [],
            HAN_VERSION
        );

        wp_enqueue_script(
            'han-admin',
            HAN_PLUGIN_URL . 'assets/js/admin.js',
            [ 'jquery' ],
            HAN_VERSION,
            true
        );

        wp_localize_script( 'han-admin', 'hanAdmin', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'han_admin_nonce' ),
        ] );
    }

    /**
     * フォーム送信処理
     */
    public function handle_actions(): void {
        // CSVインポート
        if ( isset( $_POST['han_import_csv'] ) ) {
            $this->handle_csv_import();
        }

        // カレンダー複製
        if ( isset( $_POST['han_copy_year'] ) ) {
            $this->handle_copy_year();
        }
    }

    /**
     * CSVインポート処理
     */
    private function handle_csv_import(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( '権限がありません。' );
        }

        check_admin_referer( 'han_csv_import' );

        if ( empty( $_FILES['han_csv_file']['tmp_name'] ) ) {
            add_settings_error( 'han', 'csv_error', 'ファイルを選択してください。', 'error' );
            return;
        }

        $file   = $_FILES['han_csv_file']['tmp_name'];
        $result = HAN_CSV::import( $file );

        if ( $result['errors'] ) {
            $msg = "インポート完了: {$result['imported']}件成功、{$result['skipped']}件スキップ。\nエラー: " . implode( ', ', $result['errors'] );
            add_settings_error( 'han', 'csv_warning', $msg, 'warning' );
        } else {
            add_settings_error( 'han', 'csv_success', "{$result['imported']}件のデータをインポートしました。", 'success' );
        }
    }

    /**
     * カレンダー複製処理
     */
    private function handle_copy_year(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( '権限がありません。' );
        }

        check_admin_referer( 'han_copy_year' );

        $source_year = absint( $_POST['source_year'] ?? 0 );
        if ( ! $source_year ) {
            add_settings_error( 'han', 'copy_error', '複製元の年度を指定してください。', 'error' );
            return;
        }

        $copied = HAN_Calendar::copy_to_next_year( $source_year );
        $target = $source_year + 1;
        add_settings_error( 'han', 'copy_success', "{$source_year}年から{$target}年へ{$copied}件を複製しました。", 'success' );
    }

    /**
     * AJAX: 日付データ保存
     */
    public function ajax_save_day(): void {
        check_ajax_referer( 'han_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( '権限がありません。' );
        }

        $data = [
            'cal_date'           => sanitize_text_field( $_POST['cal_date'] ?? '' ),
            'status'             => sanitize_key( $_POST['status'] ?? 'normal' ),
            'holiday_type'       => sanitize_key( $_POST['holiday_type'] ?? '' ),
            'memo'               => sanitize_textarea_field( $_POST['memo'] ?? '' ),
            'custom_title'       => sanitize_text_field( $_POST['custom_title'] ?? '' ),
            'custom_body'        => wp_kses_post( $_POST['custom_body'] ?? '' ),
            'custom_day_message' => sanitize_textarea_field( $_POST['custom_day_message'] ?? '' ),
            'auto_post'          => absint( $_POST['auto_post'] ?? 1 ),
        ];

        if ( HAN_Calendar::save_date( $data ) ) {
            // 休業日が変更された場合の再計算
            $entry = HAN_Calendar::get_date( $data['cal_date'] );
            if ( $entry && $entry->post_id ) {
                HAN_Post_Generator::update_post( (int) $entry->post_id, $entry );
            }

            wp_send_json_success( [ 'message' => '保存しました。' ] );
        } else {
            wp_send_json_error( '保存に失敗しました。' );
        }
    }

    /**
     * AJAX: プレビュー
     */
    public function ajax_preview(): void {
        check_ajax_referer( 'han_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( '権限がありません。' );
        }

        $date = sanitize_text_field( $_POST['cal_date'] ?? '' );
        $entry = HAN_Calendar::get_date( $date );

        if ( ! $entry ) {
            // ダミーエントリでプレビュー
            $entry = (object) [
                'cal_date'           => $date,
                'status'             => sanitize_key( $_POST['status'] ?? 'holiday' ),
                'holiday_type'       => sanitize_key( $_POST['holiday_type'] ?? 'regular' ),
                'memo'               => sanitize_textarea_field( $_POST['memo'] ?? '' ),
                'custom_title'       => sanitize_text_field( $_POST['custom_title'] ?? '' ),
                'custom_body'        => wp_kses_post( $_POST['custom_body'] ?? '' ),
                'custom_day_message' => sanitize_textarea_field( $_POST['custom_day_message'] ?? '' ),
                'auto_post'          => 1,
                'post_id'            => null,
            ];
        }

        $preview = HAN_Post_Generator::preview( $entry );
        wp_send_json_success( $preview );
    }

    /**
     * AJAX: 手動実行
     */
    public function ajax_manual_run(): void {
        check_ajax_referer( 'han_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( '権限がありません。' );
        }

        $results = HAN_Cron::run_manual();
        wp_send_json_success( $results );
    }

    /**
     * AJAX: テスト実行
     */
    public function ajax_test_run(): void {
        check_ajax_referer( 'han_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( '権限がありません。' );
        }

        $results = HAN_Cron::run_test();
        wp_send_json_success( $results );
    }

    /**
     * AJAX: 個別投稿生成
     */
    public function ajax_generate_single(): void {
        check_ajax_referer( 'han_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( '権限がありません。' );
        }

        $date  = sanitize_text_field( $_POST['cal_date'] ?? '' );
        $entry = HAN_Calendar::get_date( $date );

        if ( ! $entry ) {
            wp_send_json_error( '指定された日付のデータが見つかりません。' );
        }

        $post_id = HAN_Post_Generator::generate_post( $entry );

        if ( $post_id ) {
            wp_send_json_success( [
                'message' => '投稿を生成しました。',
                'post_id' => $post_id,
                'edit_url' => get_edit_post_link( $post_id, 'raw' ),
            ] );
        } else {
            wp_send_json_error( '投稿の生成に失敗しました。' );
        }
    }

    /**
     * AJAX: CSVエクスポート
     */
    public function ajax_export_csv(): void {
        check_ajax_referer( 'han_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( '権限がありません。' );
        }

        $year = absint( $_GET['year'] ?? date( 'Y' ) );
        $type = sanitize_key( $_GET['type'] ?? 'data' );

        $csv = ( $type === 'template' )
            ? HAN_CSV::generate_template( $year )
            : HAN_CSV::export( $year );

        $filename = ( $type === 'template' )
            ? "han_template_{$year}.csv"
            : "han_calendar_{$year}.csv";

        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        echo $csv;
        exit;
    }
}
