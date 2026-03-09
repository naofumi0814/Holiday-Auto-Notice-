<?php
/**
 * 投稿生成クラス
 *
 * 休業日に関するお知らせ記事の生成・更新・再計算を行う
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HAN_Post_Generator {

    /** カスタムメタキー */
    public const META_HOLIDAY_DATE = '_han_holiday_date';
    public const META_HOLIDAY_TYPE = '_han_holiday_type';
    public const META_GENERATED    = '_han_generated';

    /**
     * 休業日に対する投稿を生成
     */
    public static function generate_post( object $cal_entry ): int|false {
        // 既に投稿済みか確認
        if ( ! empty( $cal_entry->post_id ) ) {
            $existing = get_post( $cal_entry->post_id );
            if ( $existing ) {
                // 公開済み投稿の更新可否
                if ( $existing->post_status === 'publish' && ! HAN_Settings::get( 'update_published' ) ) {
                    return $existing->ID;
                }
                return self::update_post( $existing->ID, $cal_entry );
            }
        }

        // 重複チェック: 同じ休業日に対する投稿が既に存在しないか
        $duplicate = self::find_post_by_date( $cal_entry->cal_date );
        if ( $duplicate ) {
            HAN_Calendar::set_post_id( $cal_entry->cal_date, $duplicate );
            return $duplicate;
        }

        $settings     = HAN_Settings::get();
        $holiday_type = $cal_entry->holiday_type ?: 'regular';
        $vars         = HAN_Template::build_vars_from_calendar( $cal_entry );

        // タイトルと本文を決定（個別上書き優先）
        $title = $cal_entry->custom_title
            ?: HAN_Template::render(
                HAN_Template::get( 'advance', $holiday_type, 'title' ),
                $vars
            );

        $body = $cal_entry->custom_body
            ?: HAN_Template::render(
                HAN_Template::get( 'advance', $holiday_type, 'body' ),
                $vars
            );

        // 公開日を計算
        $publish_date = self::calculate_publish_date( $cal_entry->cal_date );

        $post_data = [
            'post_title'   => $title,
            'post_content' => wpautop( $body ),
            'post_type'    => $settings['post_type'],
            'post_status'  => $publish_date['status'],
            'post_date'    => $publish_date['date'],
            'post_author'  => $settings['author_id'] ?: get_current_user_id(),
        ];

        // カテゴリ設定
        if ( $settings['category_id'] && $settings['post_type'] === 'post' ) {
            $post_data['post_category'] = [ $settings['category_id'] ];
        }

        $post_id = wp_insert_post( $post_data, true );

        if ( is_wp_error( $post_id ) ) {
            HAN_Logger::error( 'post_generate', '投稿の生成に失敗: ' . $post_id->get_error_message(), [
                'date' => $cal_entry->cal_date,
            ] );
            return false;
        }

        // メタデータを保存
        update_post_meta( $post_id, self::META_HOLIDAY_DATE, $cal_entry->cal_date );
        update_post_meta( $post_id, self::META_HOLIDAY_TYPE, $holiday_type );
        update_post_meta( $post_id, self::META_GENERATED, '1' );

        // カレンダーテーブルに投稿IDを紐付け
        HAN_Calendar::set_post_id( $cal_entry->cal_date, $post_id );

        HAN_Logger::info( 'post_generate', '投稿を生成しました', [
            'date'    => $cal_entry->cal_date,
            'post_id' => $post_id,
            'status'  => $publish_date['status'],
        ] );

        return $post_id;
    }

    /**
     * 既存投稿を更新
     */
    public static function update_post( int $post_id, object $cal_entry ): int|false {
        $holiday_type = $cal_entry->holiday_type ?: 'regular';
        $vars         = HAN_Template::build_vars_from_calendar( $cal_entry );

        $title = $cal_entry->custom_title
            ?: HAN_Template::render(
                HAN_Template::get( 'advance', $holiday_type, 'title' ),
                $vars
            );

        $body = $cal_entry->custom_body
            ?: HAN_Template::render(
                HAN_Template::get( 'advance', $holiday_type, 'body' ),
                $vars
            );

        $publish_date = self::calculate_publish_date( $cal_entry->cal_date );

        $post_data = [
            'ID'           => $post_id,
            'post_title'   => $title,
            'post_content' => wpautop( $body ),
        ];

        // 未公開投稿は公開日も更新
        $existing = get_post( $post_id );
        if ( $existing && $existing->post_status !== 'publish' ) {
            $post_data['post_status'] = $publish_date['status'];
            $post_data['post_date']   = $publish_date['date'];
        }

        $result = wp_update_post( $post_data, true );

        if ( is_wp_error( $result ) ) {
            HAN_Logger::error( 'post_update', '投稿の更新に失敗: ' . $result->get_error_message(), [
                'post_id' => $post_id,
                'date'    => $cal_entry->cal_date,
            ] );
            return false;
        }

        // メタデータ更新
        update_post_meta( $post_id, self::META_HOLIDAY_TYPE, $holiday_type );

        HAN_Logger::info( 'post_update', '投稿を更新しました', [
            'date'    => $cal_entry->cal_date,
            'post_id' => $post_id,
        ] );

        return $post_id;
    }

    /**
     * 公開日を計算
     */
    public static function calculate_publish_date( string $holiday_date ): array {
        $settings    = HAN_Settings::get();
        $days_before = (int) $settings['days_before'];
        $today       = wp_date( 'Y-m-d' );

        $holiday_dt  = new DateTime( $holiday_date, wp_timezone() );
        $publish_dt  = clone $holiday_dt;
        $publish_dt->modify( "-{$days_before} days" );
        $publish_date = $publish_dt->format( 'Y-m-d' );

        // 直前登録チェック: 公開予定日が今日以前
        if ( $publish_date <= $today ) {
            $action = $settings['short_notice_action'];

            return match ( $action ) {
                'immediate' => [
                    'status' => $settings['post_status'],
                    'date'   => wp_date( 'Y-m-d H:i:s' ),
                ],
                'scheduled' => [
                    'status' => 'future',
                    'date'   => wp_date( 'Y-m-d' ) . ' ' . $settings['short_notice_time'] . ':00',
                ],
                'skip' => [
                    'status' => 'draft',
                    'date'   => wp_date( 'Y-m-d H:i:s' ),
                ],
                default => [
                    'status' => $settings['post_status'],
                    'date'   => wp_date( 'Y-m-d H:i:s' ),
                ],
            };
        }

        // 通常: 指定日数前に予約投稿
        $post_status = $settings['post_status'];
        if ( $publish_date > $today ) {
            $post_status = 'future';
        }

        return [
            'status' => $post_status,
            'date'   => $publish_dt->format( 'Y-m-d 09:00:00' ),
        ];
    }

    /**
     * 同じ休業日に対する既存投稿を検索
     */
    public static function find_post_by_date( string $holiday_date ): int {
        $args = [
            'post_type'      => HAN_Settings::get( 'post_type' ),
            'post_status'    => [ 'publish', 'future', 'draft', 'pending' ],
            'meta_key'       => self::META_HOLIDAY_DATE,
            'meta_value'     => $holiday_date,
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ];

        $posts = get_posts( $args );
        return $posts[0] ?? 0;
    }

    /**
     * 未公開投稿の再計算
     */
    public static function recalculate_pending_posts(): int {
        $args = [
            'post_type'      => HAN_Settings::get( 'post_type' ),
            'post_status'    => [ 'future', 'draft', 'pending' ],
            'meta_key'       => self::META_GENERATED,
            'meta_value'     => '1',
            'posts_per_page' => -1,
        ];

        $posts   = get_posts( $args );
        $updated = 0;

        foreach ( $posts as $post ) {
            $holiday_date = get_post_meta( $post->ID, self::META_HOLIDAY_DATE, true );
            if ( ! $holiday_date ) {
                continue;
            }

            $cal_entry = HAN_Calendar::get_date( $holiday_date );
            if ( ! $cal_entry ) {
                continue;
            }

            // 休業でなくなった場合は下書きに
            if ( $cal_entry->status !== 'holiday' ) {
                wp_update_post( [
                    'ID'          => $post->ID,
                    'post_status' => 'draft',
                ] );
                HAN_Logger::info( 'post_recalc', '休業取消により下書きに変更', [
                    'post_id' => $post->ID,
                    'date'    => $holiday_date,
                ] );
                $updated++;
                continue;
            }

            if ( self::update_post( $post->ID, $cal_entry ) ) {
                $updated++;
            }
        }

        return $updated;
    }

    /**
     * プレビュー用にテンプレート展開結果を返す
     */
    public static function preview( object $cal_entry ): array {
        $holiday_type = $cal_entry->holiday_type ?: 'regular';
        $vars         = HAN_Template::build_vars_from_calendar( $cal_entry );

        $title = $cal_entry->custom_title
            ?: HAN_Template::render(
                HAN_Template::get( 'advance', $holiday_type, 'title' ),
                $vars
            );

        $body = $cal_entry->custom_body
            ?: HAN_Template::render(
                HAN_Template::get( 'advance', $holiday_type, 'body' ),
                $vars
            );

        $day_message = $cal_entry->custom_day_message
            ?: HAN_Template::render(
                HAN_Template::get( 'day', $holiday_type, 'message' ),
                $vars
            );

        $publish_date = self::calculate_publish_date( $cal_entry->cal_date );

        return [
            'title'        => $title,
            'body'         => $body,
            'day_message'  => $day_message,
            'publish_date' => $publish_date,
        ];
    }
}
