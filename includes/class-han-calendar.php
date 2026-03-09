<?php
/**
 * カレンダーデータ管理クラス
 *
 * 独自テーブル han_calendar に対する CRUD 操作を提供する
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HAN_Calendar {

    /**
     * テーブル名を取得
     */
    public static function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'han_calendar';
    }

    /**
     * 指定日のデータを取得
     */
    public static function get_date( string $date ): ?object {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM %i WHERE cal_date = %s",
                self::table_name(),
                $date
            )
        );
        return $row ?: null;
    }

    /**
     * 指定年のデータ一覧を取得
     */
    public static function get_year( int $year ): array {
        global $wpdb;
        $start = sprintf( '%04d-01-01', $year );
        $end   = sprintf( '%04d-12-31', $year );

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM %i WHERE cal_date BETWEEN %s AND %s ORDER BY cal_date ASC",
                self::table_name(),
                $start,
                $end
            )
        );
    }

    /**
     * 指定月のデータ一覧を取得
     */
    public static function get_month( int $year, int $month ): array {
        global $wpdb;
        $start = sprintf( '%04d-%02d-01', $year, $month );
        $end   = date( 'Y-m-t', strtotime( $start ) );

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM %i WHERE cal_date BETWEEN %s AND %s ORDER BY cal_date ASC",
                self::table_name(),
                $start,
                $end
            )
        );
    }

    /**
     * 日付データを保存（UPSERT）
     */
    public static function save_date( array $data ): bool {
        global $wpdb;

        $date = sanitize_text_field( $data['cal_date'] ?? '' );
        if ( ! $date || ! strtotime( $date ) ) {
            return false;
        }

        $row = [
            'cal_date'           => $date,
            'status'             => sanitize_key( $data['status'] ?? 'normal' ),
            'holiday_type'       => isset( $data['holiday_type'] ) ? sanitize_key( $data['holiday_type'] ) : null,
            'memo'               => isset( $data['memo'] ) ? sanitize_textarea_field( $data['memo'] ) : null,
            'custom_title'       => isset( $data['custom_title'] ) ? sanitize_text_field( $data['custom_title'] ) : null,
            'custom_body'        => isset( $data['custom_body'] ) ? wp_kses_post( $data['custom_body'] ) : null,
            'custom_day_message' => isset( $data['custom_day_message'] ) ? sanitize_textarea_field( $data['custom_day_message'] ) : null,
            'auto_post'          => isset( $data['auto_post'] ) ? absint( $data['auto_post'] ) : 1,
        ];

        // post_idがある場合のみセット
        if ( isset( $data['post_id'] ) ) {
            $row['post_id'] = $data['post_id'] ? absint( $data['post_id'] ) : null;
        }

        $existing = self::get_date( $date );

        if ( $existing ) {
            $result = $wpdb->update(
                self::table_name(),
                $row,
                [ 'cal_date' => $date ],
                null,
                [ '%s' ]
            );
        } else {
            $result = $wpdb->insert( self::table_name(), $row );
        }

        return false !== $result;
    }

    /**
     * 月単位で一括保存
     */
    public static function save_month( int $year, int $month, array $days_data ): int {
        $saved = 0;
        foreach ( $days_data as $day => $data ) {
            $data['cal_date'] = sprintf( '%04d-%02d-%02d', $year, $month, (int) $day );
            if ( self::save_date( $data ) ) {
                $saved++;
            }
        }
        return $saved;
    }

    /**
     * 今日のデータを取得
     */
    public static function get_today(): ?object {
        return self::get_date( wp_date( 'Y-m-d' ) );
    }

    /**
     * 休業日一覧を取得
     */
    public static function get_holidays( int $year ): array {
        global $wpdb;
        $start = sprintf( '%04d-01-01', $year );
        $end   = sprintf( '%04d-12-31', $year );

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM %i WHERE cal_date BETWEEN %s AND %s AND status = 'holiday' ORDER BY cal_date ASC",
                self::table_name(),
                $start,
                $end
            )
        );
    }

    /**
     * 投稿未生成の休業日を取得（自動投稿対象）
     */
    public static function get_pending_holidays(): array {
        global $wpdb;
        $today = wp_date( 'Y-m-d' );

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM %i WHERE status = 'holiday' AND auto_post = 1 AND post_id IS NULL AND cal_date >= %s ORDER BY cal_date ASC",
                self::table_name(),
                $today
            )
        );
    }

    /**
     * 次の営業日を取得
     */
    public static function get_next_business_date( string $from_date ): ?string {
        global $wpdb;

        $result = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT cal_date FROM %i WHERE cal_date > %s AND status = 'normal' ORDER BY cal_date ASC LIMIT 1",
                self::table_name(),
                $from_date
            )
        );

        // テーブルにデータがなければ翌日以降で土日を飛ばす簡易ロジック
        if ( ! $result ) {
            $date = new DateTime( $from_date );
            for ( $i = 0; $i < 30; $i++ ) {
                $date->modify( '+1 day' );
                $check = self::get_date( $date->format( 'Y-m-d' ) );
                if ( ! $check || $check->status === 'normal' || $check->status === 'special' ) {
                    return $date->format( 'Y-m-d' );
                }
            }
            // 30日以内に営業日がなければ翌日を返す
            $fallback = new DateTime( $from_date );
            $fallback->modify( '+1 day' );
            return $fallback->format( 'Y-m-d' );
        }

        return $result;
    }

    /**
     * 翌年度へのカレンダー複製
     */
    public static function copy_to_next_year( int $source_year ): int {
        $data  = self::get_year( $source_year );
        $copied = 0;

        foreach ( $data as $row ) {
            $source_date = new DateTime( $row->cal_date );
            $target_date = clone $source_date;
            $target_date->modify( '+1 year' );

            // 同じ日付が既に存在する場合はスキップ
            if ( self::get_date( $target_date->format( 'Y-m-d' ) ) ) {
                continue;
            }

            $new_data = [
                'cal_date'           => $target_date->format( 'Y-m-d' ),
                'status'             => $row->status,
                'holiday_type'       => $row->holiday_type,
                'memo'               => $row->memo,
                'custom_title'       => $row->custom_title,
                'custom_body'        => $row->custom_body,
                'custom_day_message' => $row->custom_day_message,
                'auto_post'          => $row->auto_post,
            ];

            if ( self::save_date( $new_data ) ) {
                $copied++;
            }
        }

        return $copied;
    }

    /**
     * 日付データを削除
     */
    public static function delete_date( string $date ): bool {
        global $wpdb;
        return (bool) $wpdb->delete(
            self::table_name(),
            [ 'cal_date' => $date ],
            [ '%s' ]
        );
    }

    /**
     * 指定年のデータ件数を取得
     */
    public static function count_year( int $year ): int {
        global $wpdb;
        $start = sprintf( '%04d-01-01', $year );
        $end   = sprintf( '%04d-12-31', $year );

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM %i WHERE cal_date BETWEEN %s AND %s",
                self::table_name(),
                $start,
                $end
            )
        );
    }

    /**
     * 投稿IDを紐付け
     */
    public static function set_post_id( string $date, int $post_id ): bool {
        global $wpdb;
        return (bool) $wpdb->update(
            self::table_name(),
            [ 'post_id' => $post_id ],
            [ 'cal_date' => $date ],
            [ '%d' ],
            [ '%s' ]
        );
    }

    /**
     * 曜日を日本語で取得
     */
    public static function get_weekday_ja( string $date ): string {
        $weekdays = [ '日', '月', '火', '水', '木', '金', '土' ];
        $w = (int) date( 'w', strtotime( $date ) );
        return $weekdays[ $w ];
    }
}
