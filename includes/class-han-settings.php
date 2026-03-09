<?php
/**
 * 設定管理クラス
 *
 * プラグイン全体の設定値の取得・保存を管理する
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HAN_Settings {

    /** オプションキー */
    public const OPTION_KEY = 'han_settings';

    /**
     * デフォルト設定値
     */
    public static function get_defaults(): array {
        return [
            // 基本設定
            'site_name'              => get_bloginfo( 'name' ),
            'target_year'            => (int) date( 'Y' ),
            'timezone'               => wp_timezone_string(),

            // 自動運用
            'auto_operation'         => true,
            'auto_post'              => true,
            'day_display'            => true,
            'fixed_page_update'      => false,
            'fixed_page_id'          => 0,

            // 投稿設定
            'post_type'              => 'post',
            'category_id'            => 0,
            'author_id'              => 0,
            'post_status'            => 'publish',
            'days_before'            => 10,

            // 直前登録時の挙動
            'short_notice_action'    => 'immediate', // immediate, scheduled, skip
            'short_notice_time'      => '09:00',

            // 公開済み投稿の更新
            'update_published'       => false,

            // ログ
            'log_enabled'            => true,

            // 営業時間（短縮営業表示用）
            'business_hours'         => '10:00〜18:00',
        ];
    }

    /**
     * 初期設定を保存
     */
    public static function set_defaults(): void {
        if ( false === get_option( self::OPTION_KEY ) ) {
            update_option( self::OPTION_KEY, self::get_defaults() );
        }
    }

    /**
     * 設定値を取得
     */
    public static function get( string $key = '', mixed $default = null ): mixed {
        $settings = get_option( self::OPTION_KEY, [] );
        $defaults = self::get_defaults();
        $settings = wp_parse_args( $settings, $defaults );

        if ( '' === $key ) {
            return $settings;
        }

        return $settings[ $key ] ?? $default ?? ( $defaults[ $key ] ?? null );
    }

    /**
     * 設定値を保存
     */
    public static function save( array $settings ): bool {
        $current = self::get();
        $merged  = wp_parse_args( $settings, $current );

        // サニタイズ
        $merged['site_name']           = sanitize_text_field( $merged['site_name'] );
        $merged['target_year']         = absint( $merged['target_year'] );
        $merged['timezone']            = sanitize_text_field( $merged['timezone'] );
        $merged['auto_operation']      = (bool) $merged['auto_operation'];
        $merged['auto_post']           = (bool) $merged['auto_post'];
        $merged['day_display']         = (bool) $merged['day_display'];
        $merged['fixed_page_update']   = (bool) $merged['fixed_page_update'];
        $merged['fixed_page_id']       = absint( $merged['fixed_page_id'] );
        $merged['post_type']           = sanitize_key( $merged['post_type'] );
        $merged['category_id']         = absint( $merged['category_id'] );
        $merged['author_id']           = absint( $merged['author_id'] );
        $merged['post_status']         = sanitize_key( $merged['post_status'] );
        $merged['days_before']         = max( 1, absint( $merged['days_before'] ) );
        $merged['short_notice_action'] = in_array( $merged['short_notice_action'], [ 'immediate', 'scheduled', 'skip' ], true )
            ? $merged['short_notice_action'] : 'immediate';
        $merged['short_notice_time']   = sanitize_text_field( $merged['short_notice_time'] );
        $merged['update_published']    = (bool) $merged['update_published'];
        $merged['log_enabled']         = (bool) $merged['log_enabled'];
        $merged['business_hours']      = sanitize_text_field( $merged['business_hours'] );

        return update_option( self::OPTION_KEY, $merged );
    }

    /**
     * 営業ステータスラベル
     */
    public static function get_status_labels(): array {
        return [
            'normal'  => '通常営業',
            'holiday' => '休業',
            'short'   => '短縮営業',
            'special' => '特別営業',
        ];
    }

    /**
     * 休業種別ラベル
     */
    public static function get_holiday_type_labels(): array {
        return [
            'public_holiday' => '祝日',
            'regular'        => '祝日以外の休業',
            'obon'           => 'お盆',
            'yearend'        => '年末年始',
            'special_case'   => '個別特例',
        ];
    }

    /**
     * 直前登録時の挙動ラベル
     */
    public static function get_short_notice_labels(): array {
        return [
            'immediate' => '即時公開',
            'scheduled' => '指定時刻で公開',
            'skip'      => '自動投稿しない',
        ];
    }
}
