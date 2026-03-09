<?php
/**
 * WP-Cron 処理クラス
 *
 * 定期実行による自動投稿生成と当日表示切替を管理する
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HAN_Cron {

    /** Cronイベント名 */
    public const EVENT_DAILY = 'han_daily_check';

    private static ?self $instance = null;

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( self::EVENT_DAILY, [ $this, 'run_daily_check' ] );
    }

    /**
     * Cronイベントをスケジュール
     */
    public static function schedule_events(): void {
        if ( ! wp_next_scheduled( self::EVENT_DAILY ) ) {
            // 毎日午前1時に実行
            $timezone   = wp_timezone();
            $next_run   = new DateTime( 'tomorrow 01:00:00', $timezone );
            wp_schedule_event( $next_run->getTimestamp(), 'daily', self::EVENT_DAILY );
        }
    }

    /**
     * Cronイベントを解除
     */
    public static function unschedule_events(): void {
        $timestamp = wp_next_scheduled( self::EVENT_DAILY );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::EVENT_DAILY );
        }
    }

    /**
     * 日次チェック処理
     */
    public function run_daily_check(): void {
        $settings = HAN_Settings::get();

        // 自動運用がOFFなら終了
        if ( ! $settings['auto_operation'] ) {
            return;
        }

        HAN_Logger::info( 'cron', '日次チェックを開始しました' );

        // 1. 自動投稿処理
        if ( $settings['auto_post'] ) {
            $this->process_auto_posts();
        }

        // 2. 未公開投稿の再計算
        HAN_Post_Generator::recalculate_pending_posts();

        // 3. 固定ページ更新
        if ( $settings['fixed_page_update'] && $settings['fixed_page_id'] ) {
            $this->update_fixed_page( $settings['fixed_page_id'] );
        }

        HAN_Logger::info( 'cron', '日次チェックが完了しました' );
    }

    /**
     * 自動投稿処理
     */
    private function process_auto_posts(): void {
        $pending = HAN_Calendar::get_pending_holidays();
        $count   = 0;

        foreach ( $pending as $entry ) {
            // auto_postがONの場合のみ
            if ( ! $entry->auto_post ) {
                continue;
            }

            // 直前登録でスキップ設定の場合
            $publish_info = HAN_Post_Generator::calculate_publish_date( $entry->cal_date );
            if ( $publish_info['status'] === 'draft' ) {
                $settings = HAN_Settings::get();
                if ( $settings['short_notice_action'] === 'skip' ) {
                    continue;
                }
            }

            $result = HAN_Post_Generator::generate_post( $entry );
            if ( $result ) {
                $count++;
            }
        }

        if ( $count > 0 ) {
            HAN_Logger::info( 'cron', "{$count}件の投稿を自動生成しました" );
        }
    }

    /**
     * 固定ページの本文内プレースホルダを置換
     */
    private function update_fixed_page( int $page_id ): void {
        $page = get_post( $page_id );
        if ( ! $page ) {
            return;
        }

        $today_entry = HAN_Calendar::get_today();
        $message     = HAN_Shortcode::get_today_message( $today_entry );

        // プレースホルダ置換
        $content = $page->post_content;
        if ( str_contains( $content, '<!-- han_status -->' ) ) {
            // プレースホルダの中身を置換
            $content = preg_replace(
                '/<!-- han_status -->.*?<!-- \/han_status -->/s',
                '<!-- han_status -->' . esc_html( $message ) . '<!-- /han_status -->',
                $content
            );

            wp_update_post( [
                'ID'           => $page_id,
                'post_content' => $content,
            ] );
        }
    }

    /**
     * 手動実行（管理画面から呼び出し）
     */
    public static function run_manual(): array {
        $instance = self::get_instance();
        $settings = HAN_Settings::get();

        $results = [
            'posts_generated' => 0,
            'posts_updated'   => 0,
            'messages'        => [],
        ];

        // 自動投稿処理
        if ( $settings['auto_post'] ) {
            $pending = HAN_Calendar::get_pending_holidays();
            foreach ( $pending as $entry ) {
                if ( ! $entry->auto_post ) {
                    continue;
                }
                $result = HAN_Post_Generator::generate_post( $entry );
                if ( $result ) {
                    $results['posts_generated']++;
                }
            }
            $results['messages'][] = "{$results['posts_generated']}件の投稿を生成しました。";
        }

        // 未公開投稿の再計算
        $updated = HAN_Post_Generator::recalculate_pending_posts();
        $results['posts_updated'] = $updated;
        $results['messages'][]    = "{$updated}件の投稿を再計算しました。";

        // 固定ページ更新
        if ( $settings['fixed_page_update'] && $settings['fixed_page_id'] ) {
            $instance->update_fixed_page( $settings['fixed_page_id'] );
            $results['messages'][] = '固定ページを更新しました。';
        }

        HAN_Logger::info( 'manual_run', '手動実行が完了しました', $results );

        return $results;
    }

    /**
     * テスト実行（実際の投稿は行わない）
     */
    public static function run_test(): array {
        $settings = HAN_Settings::get();
        $results  = [
            'would_generate' => [],
            'would_update'   => [],
            'today_message'  => '',
        ];

        // 投稿対象の確認
        if ( $settings['auto_post'] ) {
            $pending = HAN_Calendar::get_pending_holidays();
            foreach ( $pending as $entry ) {
                if ( ! $entry->auto_post ) {
                    continue;
                }
                $preview = HAN_Post_Generator::preview( $entry );
                $results['would_generate'][] = [
                    'date'         => $entry->cal_date,
                    'title'        => $preview['title'],
                    'publish_date' => $preview['publish_date'],
                ];
            }
        }

        // 当日メッセージ
        $today_entry = HAN_Calendar::get_today();
        $results['today_message'] = HAN_Shortcode::get_today_message( $today_entry );

        HAN_Logger::info( 'test_run', 'テスト実行が完了しました' );

        return $results;
    }
}
