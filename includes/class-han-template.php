<?php
/**
 * テンプレート管理クラス
 *
 * 休業種別ごとのテンプレート管理と変数展開を行う
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HAN_Template {

    /** テンプレートオプションキー */
    public const OPTION_KEY = 'han_templates';

    /**
     * テンプレート種別
     * - advance: 事前告知投稿用
     * - day:     当日表示用
     * - reopen:  営業再開案内用
     */
    public const TEMPLATE_TYPES = [ 'advance', 'day', 'reopen' ];

    /**
     * 休業種別キー
     */
    public const HOLIDAY_TYPES = [ 'public_holiday', 'regular', 'obon', 'yearend', 'special_case' ];

    /**
     * デフォルトテンプレートを取得
     */
    public static function get_defaults(): array {
        return [
            // --- 事前告知テンプレート ---
            'advance' => [
                'public_holiday' => [
                    'title' => '{month}月{day}日 祝日休業のお知らせ',
                    'body'  => "平素より{site_name}をご利用いただきありがとうございます。\n{date}（{weekday}）は、祝日のため休業とさせていただきます。\n次回の営業日は {next_business_date}（{next_business_weekday}）です。\nご不便をおかけいたしますが、何卒よろしくお願いいたします。",
                ],
                'regular' => [
                    'title' => '{month}月{day}日 休業のお知らせ',
                    'body'  => "平素より{site_name}をご利用いただきありがとうございます。\n{date}（{weekday}）は、都合により休業とさせていただきます。\n次回の営業日は {next_business_date}（{next_business_weekday}）です。\nご迷惑をおかけいたしますが、よろしくお願いいたします。",
                ],
                'obon' => [
                    'title' => 'お盆休業のお知らせ',
                    'body'  => "平素より{site_name}をご利用いただきありがとうございます。\n{date}（{weekday}）は、お盆休業のため営業をお休みいたします。\n営業再開日は {next_business_date}（{next_business_weekday}）を予定しております。\n何卒よろしくお願いいたします。",
                ],
                'yearend' => [
                    'title' => '年末年始休業のお知らせ',
                    'body'  => "平素より{site_name}をご利用いただきありがとうございます。\n{date}（{weekday}）は、年末年始休業のため営業をお休みいたします。\n営業再開日は {next_business_date}（{next_business_weekday}）を予定しております。\n何卒よろしくお願いいたします。",
                ],
                'special_case' => [
                    'title' => '{month}月{day}日 臨時休業のお知らせ',
                    'body'  => "平素より{site_name}をご利用いただきありがとうございます。\n{date}（{weekday}）は、誠に勝手ながら臨時休業とさせていただきます。\n次回の営業日は {next_business_date}（{next_business_weekday}）です。\nご不便をおかけいたしますが、よろしくお願いいたします。",
                ],
            ],

            // --- 当日表示テンプレート ---
            'day' => [
                'public_holiday' => [
                    'message' => '本日は祝日のため休業しております。',
                ],
                'regular' => [
                    'message' => '本日は休業しております。',
                ],
                'obon' => [
                    'message' => '本日はお盆休業となっております。',
                ],
                'yearend' => [
                    'message' => '本日は年末年始休業となっております。',
                ],
                'special_case' => [
                    'message' => '本日は臨時休業しております。',
                ],
                'short' => [
                    'message' => '本日は短縮営業です。営業時間は {business_hours} です。',
                ],
                'special_business' => [
                    'message' => '本日は特別営業です。',
                ],
                'normal' => [
                    'message' => '本日は通常営業しております。',
                ],
            ],

            // --- 営業再開案内テンプレート ---
            'reopen' => [
                'public_holiday' => [
                    'title' => '営業再開のお知らせ',
                    'body'  => "本日より通常営業を再開いたしました。\n引き続き{site_name}をよろしくお願いいたします。",
                ],
                'regular' => [
                    'title' => '営業再開のお知らせ',
                    'body'  => "本日より通常営業を再開いたしました。\n引き続き{site_name}をよろしくお願いいたします。",
                ],
                'obon' => [
                    'title' => 'お盆休業明け 営業再開のお知らせ',
                    'body'  => "本日よりお盆休業を終え、通常営業を再開いたしました。\n引き続き{site_name}をよろしくお願いいたします。",
                ],
                'yearend' => [
                    'title' => '年始営業開始のお知らせ',
                    'body'  => "新年あけましておめでとうございます。\n本日より通常営業を開始いたしました。\n本年も{site_name}をよろしくお願いいたします。",
                ],
                'special_case' => [
                    'title' => '営業再開のお知らせ',
                    'body'  => "本日より通常営業を再開いたしました。\n引き続き{site_name}をよろしくお願いいたします。",
                ],
            ],
        ];
    }

    /**
     * 初期テンプレートを保存
     */
    public static function set_defaults(): void {
        if ( false === get_option( self::OPTION_KEY ) ) {
            update_option( self::OPTION_KEY, self::get_defaults() );
        }
    }

    /**
     * テンプレートを取得
     */
    public static function get_all(): array {
        $templates = get_option( self::OPTION_KEY, [] );
        return wp_parse_args( $templates, self::get_defaults() );
    }

    /**
     * 特定のテンプレートを取得
     */
    public static function get( string $type, string $holiday_type, string $field = '' ): mixed {
        $templates = self::get_all();
        $template  = $templates[ $type ][ $holiday_type ] ?? [];

        if ( $field ) {
            return $template[ $field ] ?? '';
        }

        return $template;
    }

    /**
     * テンプレートを保存
     */
    public static function save( array $templates ): bool {
        // サニタイズ
        $sanitized = [];
        foreach ( $templates as $type => $types ) {
            if ( ! is_array( $types ) ) {
                continue;
            }
            foreach ( $types as $holiday_type => $fields ) {
                if ( ! is_array( $fields ) ) {
                    continue;
                }
                foreach ( $fields as $field => $value ) {
                    $sanitized[ $type ][ $holiday_type ][ $field ] = wp_kses_post( $value );
                }
            }
        }

        return update_option( self::OPTION_KEY, $sanitized );
    }

    /**
     * テンプレート変数を展開
     */
    public static function render( string $template, array $vars = [] ): string {
        $replacements = [
            '{site_name}'             => $vars['site_name'] ?? HAN_Settings::get( 'site_name' ),
            '{date}'                  => $vars['date'] ?? '',
            '{year}'                  => $vars['year'] ?? '',
            '{month}'                 => $vars['month'] ?? '',
            '{day}'                   => $vars['day'] ?? '',
            '{weekday}'               => $vars['weekday'] ?? '',
            '{holiday_name}'          => $vars['holiday_name'] ?? '',
            '{next_business_date}'    => $vars['next_business_date'] ?? '',
            '{next_business_weekday}' => $vars['next_business_weekday'] ?? '',
            '{business_hours}'        => $vars['business_hours'] ?? HAN_Settings::get( 'business_hours' ),
            '{notice_days_before}'    => $vars['notice_days_before'] ?? HAN_Settings::get( 'days_before' ),
            '{note}'                  => $vars['note'] ?? '',
        ];

        /**
         * フィルター: テンプレート変数を拡張可能にする
         */
        $replacements = apply_filters( 'han_template_variables', $replacements, $vars );

        return str_replace(
            array_keys( $replacements ),
            array_values( $replacements ),
            $template
        );
    }

    /**
     * カレンダーデータからテンプレート変数を生成
     */
    public static function build_vars_from_calendar( object $cal_entry ): array {
        $date     = $cal_entry->cal_date;
        $dt       = new DateTime( $date );
        $next_biz = HAN_Calendar::get_next_business_date( $date );

        $holiday_type_labels = HAN_Settings::get_holiday_type_labels();
        $holiday_name = $holiday_type_labels[ $cal_entry->holiday_type ?? '' ] ?? '';

        return [
            'site_name'             => HAN_Settings::get( 'site_name' ),
            'date'                  => wp_date( 'Y年n月j日', $dt->getTimestamp() ),
            'year'                  => $dt->format( 'Y' ),
            'month'                 => ltrim( $dt->format( 'm' ), '0' ),
            'day'                   => ltrim( $dt->format( 'd' ), '0' ),
            'weekday'               => HAN_Calendar::get_weekday_ja( $date ),
            'holiday_name'          => $holiday_name,
            'next_business_date'    => $next_biz ? wp_date( 'Y年n月j日', strtotime( $next_biz ) ) : '',
            'next_business_weekday' => $next_biz ? HAN_Calendar::get_weekday_ja( $next_biz ) : '',
            'business_hours'        => HAN_Settings::get( 'business_hours' ),
            'notice_days_before'    => HAN_Settings::get( 'days_before' ),
            'note'                  => $cal_entry->memo ?? '',
        ];
    }
}
