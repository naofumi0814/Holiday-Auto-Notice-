<?php
/**
 * ログ管理クラス
 *
 * 独自テーブル han_logs にログを記録・取得する
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HAN_Logger {

    /**
     * テーブル名を取得
     */
    public static function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'han_logs';
    }

    /**
     * ログを記録
     */
    public static function log( string $level, string $type, string $message, mixed $context = null ): bool {
        if ( ! HAN_Settings::get( 'log_enabled' ) ) {
            return false;
        }

        global $wpdb;

        return (bool) $wpdb->insert(
            self::table_name(),
            [
                'log_level' => $level,
                'log_type'  => sanitize_key( $type ),
                'message'   => sanitize_text_field( $message ),
                'context'   => $context ? wp_json_encode( $context, JSON_UNESCAPED_UNICODE ) : null,
            ]
        );
    }

    /**
     * 情報ログ
     */
    public static function info( string $type, string $message, mixed $context = null ): bool {
        return self::log( 'info', $type, $message, $context );
    }

    /**
     * 警告ログ
     */
    public static function warning( string $type, string $message, mixed $context = null ): bool {
        return self::log( 'warning', $type, $message, $context );
    }

    /**
     * エラーログ
     */
    public static function error( string $type, string $message, mixed $context = null ): bool {
        return self::log( 'error', $type, $message, $context );
    }

    /**
     * ログ一覧を取得
     */
    public static function get_logs( array $args = [] ): array {
        global $wpdb;

        $defaults = [
            'per_page' => 50,
            'page'     => 1,
            'type'     => '',
            'level'    => '',
            'order'    => 'DESC',
        ];
        $args = wp_parse_args( $args, $defaults );

        $where   = [];
        $prepare = [];

        if ( $args['type'] ) {
            $where[]   = 'log_type = %s';
            $prepare[] = $args['type'];
        }

        if ( $args['level'] ) {
            $where[]   = 'log_level = %s';
            $prepare[] = $args['level'];
        }

        $where_clause = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';
        $order        = $args['order'] === 'ASC' ? 'ASC' : 'DESC';
        $offset       = ( max( 1, $args['page'] ) - 1 ) * $args['per_page'];
        $table        = self::table_name();

        $sql = "SELECT * FROM {$table} {$where_clause} ORDER BY log_date {$order} LIMIT %d OFFSET %d";
        $prepare[] = $args['per_page'];
        $prepare[] = $offset;

        if ( $prepare ) {
            $sql = $wpdb->prepare( $sql, ...$prepare );
        }

        return $wpdb->get_results( $sql );
    }

    /**
     * ログ件数を取得
     */
    public static function count_logs( array $args = [] ): int {
        global $wpdb;

        $where   = [];
        $prepare = [];

        if ( ! empty( $args['type'] ) ) {
            $where[]   = 'log_type = %s';
            $prepare[] = $args['type'];
        }

        if ( ! empty( $args['level'] ) ) {
            $where[]   = 'log_level = %s';
            $prepare[] = $args['level'];
        }

        $where_clause = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';
        $table        = self::table_name();
        $sql          = "SELECT COUNT(*) FROM {$table} {$where_clause}";

        if ( $prepare ) {
            return (int) $wpdb->get_var( $wpdb->prepare( $sql, ...$prepare ) );
        }

        return (int) $wpdb->get_var( $sql );
    }

    /**
     * 古いログを削除
     */
    public static function cleanup( int $days = 90 ): int {
        global $wpdb;
        $table    = self::table_name();
        $cutoff   = wp_date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

        return (int) $wpdb->query(
            $wpdb->prepare( "DELETE FROM {$table} WHERE log_date < %s", $cutoff )
        );
    }

    /**
     * 全ログ削除
     */
    public static function clear_all(): bool {
        global $wpdb;
        $table = self::table_name();
        return false !== $wpdb->query( "TRUNCATE TABLE {$table}" );
    }
}
