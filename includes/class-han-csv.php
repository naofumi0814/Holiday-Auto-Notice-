<?php
/**
 * CSV入出力クラス
 *
 * カレンダーデータのCSVインポート・エクスポートを行う
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HAN_CSV {

    /** CSVヘッダー定義 */
    public const HEADERS = [
        'cal_date',
        'status',
        'holiday_type',
        'memo',
        'custom_title',
        'custom_body',
        'custom_day_message',
        'auto_post',
    ];

    /** CSVヘッダー日本語 */
    public const HEADERS_JA = [
        '日付',
        '営業ステータス',
        '休業種別',
        'メモ',
        '個別タイトル',
        '個別本文',
        '個別当日表示文',
        '自動投稿',
    ];

    /**
     * 指定年のカレンダーデータをCSVエクスポート
     */
    public static function export( int $year ): string {
        $data = HAN_Calendar::get_year( $year );

        $output = fopen( 'php://temp', 'r+' );

        // BOM付きUTF-8
        fwrite( $output, "\xEF\xBB\xBF" );

        // ヘッダー行
        fputcsv( $output, self::HEADERS_JA );

        // データ行
        foreach ( $data as $row ) {
            fputcsv( $output, [
                $row->cal_date,
                $row->status,
                $row->holiday_type ?? '',
                $row->memo ?? '',
                $row->custom_title ?? '',
                $row->custom_body ?? '',
                $row->custom_day_message ?? '',
                $row->auto_post,
            ] );
        }

        rewind( $output );
        $csv = stream_get_contents( $output );
        fclose( $output );

        return $csv;
    }

    /**
     * CSVファイルからインポート
     *
     * @return array{imported: int, skipped: int, errors: string[]}
     */
    public static function import( string $file_path ): array {
        $result = [
            'imported' => 0,
            'skipped'  => 0,
            'errors'   => [],
        ];

        if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
            $result['errors'][] = 'ファイルが読み取れません。';
            return $result;
        }

        $handle = fopen( $file_path, 'r' );
        if ( ! $handle ) {
            $result['errors'][] = 'ファイルを開けません。';
            return $result;
        }

        // BOM除去
        $bom = fread( $handle, 3 );
        if ( $bom !== "\xEF\xBB\xBF" ) {
            rewind( $handle );
        }

        // ヘッダー行を読み飛ばし
        $header = fgetcsv( $handle );
        if ( ! $header ) {
            fclose( $handle );
            $result['errors'][] = 'CSVヘッダーが見つかりません。';
            return $result;
        }

        // ヘッダーマッピング（日本語/英語の両方に対応）
        $col_map = self::build_column_map( $header );

        $line = 1;
        while ( ( $row = fgetcsv( $handle ) ) !== false ) {
            $line++;

            if ( count( $row ) < 2 ) {
                $result['skipped']++;
                continue;
            }

            $data = self::map_row( $row, $col_map );

            // 日付バリデーション
            if ( empty( $data['cal_date'] ) || ! strtotime( $data['cal_date'] ) ) {
                $result['errors'][] = "{$line}行目: 無効な日付です。";
                $result['skipped']++;
                continue;
            }

            // ステータスバリデーション
            $valid_statuses = [ 'normal', 'holiday', 'short', 'special' ];
            if ( ! empty( $data['status'] ) && ! in_array( $data['status'], $valid_statuses, true ) ) {
                $result['errors'][] = "{$line}行目: 無効なステータスです（{$data['status']}）。";
                $result['skipped']++;
                continue;
            }

            if ( HAN_Calendar::save_date( $data ) ) {
                $result['imported']++;
            } else {
                $result['errors'][] = "{$line}行目: 保存に失敗しました。";
                $result['skipped']++;
            }
        }

        fclose( $handle );

        HAN_Logger::info( 'csv_import', 'CSVインポート完了', $result );

        return $result;
    }

    /**
     * ヘッダーからカラムマッピングを構築
     */
    private static function build_column_map( array $header ): array {
        $map = [];
        $header = array_map( 'trim', $header );

        foreach ( $header as $idx => $col ) {
            // 英語ヘッダー
            if ( in_array( $col, self::HEADERS, true ) ) {
                $map[ $col ] = $idx;
                continue;
            }

            // 日本語ヘッダー
            $ja_idx = array_search( $col, self::HEADERS_JA, true );
            if ( $ja_idx !== false ) {
                $map[ self::HEADERS[ $ja_idx ] ] = $idx;
            }
        }

        return $map;
    }

    /**
     * CSVの行をカラムマッピングに従ってデータに変換
     */
    private static function map_row( array $row, array $col_map ): array {
        $data = [];
        foreach ( self::HEADERS as $key ) {
            if ( isset( $col_map[ $key ] ) && isset( $row[ $col_map[ $key ] ] ) ) {
                $data[ $key ] = trim( $row[ $col_map[ $key ] ] );
            }
        }
        return $data;
    }

    /**
     * CSVテンプレートを生成（空のCSV）
     */
    public static function generate_template( int $year ): string {
        $output = fopen( 'php://temp', 'r+' );

        fwrite( $output, "\xEF\xBB\xBF" );
        fputcsv( $output, self::HEADERS_JA );

        // 1年分の日付を出力
        $start = new DateTime( "{$year}-01-01" );
        $end   = new DateTime( "{$year}-12-31" );
        $interval = new DateInterval( 'P1D' );
        $period = new DatePeriod( $start, $interval, $end->modify( '+1 day' ) );

        foreach ( $period as $date ) {
            fputcsv( $output, [
                $date->format( 'Y-m-d' ),
                'normal',
                '',
                '',
                '',
                '',
                '',
                '1',
            ] );
        }

        rewind( $output );
        $csv = stream_get_contents( $output );
        fclose( $output );

        return $csv;
    }
}
