<?php
/**
 * ログ管理画面クラス
 *
 * 更新ログと投稿履歴の確認UIを提供する
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HAN_Admin_Logs {

    private static ?self $instance = null;

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * ページ描画
     */
    public function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( '権限がありません。' );
        }

        // ログクリア処理
        if ( isset( $_POST['han_clear_logs'] ) ) {
            check_admin_referer( 'han_clear_logs' );
            HAN_Logger::clear_all();
            add_settings_error( 'han', 'clear_success', 'ログを全件削除しました。', 'success' );
        }

        // 古いログ削除
        if ( isset( $_POST['han_cleanup_logs'] ) ) {
            check_admin_referer( 'han_cleanup_logs' );
            $days    = absint( $_POST['cleanup_days'] ?? 90 );
            $deleted = HAN_Logger::cleanup( $days );
            add_settings_error( 'han', 'cleanup_success', "{$days}日より前のログ{$deleted}件を削除しました。", 'success' );
        }

        $page      = max( 1, absint( $_GET['paged'] ?? 1 ) );
        $per_page  = 50;
        $type      = sanitize_key( $_GET['log_type'] ?? '' );
        $level     = sanitize_key( $_GET['log_level'] ?? '' );

        $filter = [
            'per_page' => $per_page,
            'page'     => $page,
            'type'     => $type,
            'level'    => $level,
        ];

        $logs       = HAN_Logger::get_logs( $filter );
        $total      = HAN_Logger::count_logs( $filter );
        $total_pages = ceil( $total / $per_page );

        // タブ: ログ / 投稿履歴
        $active_tab = sanitize_key( $_GET['tab'] ?? 'logs' );

        ?>
        <div class="wrap han-wrap">
            <h1>ログ・履歴</h1>
            <?php settings_errors( 'han' ); ?>

            <h2 class="nav-tab-wrapper">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=han-logs&tab=logs' ) ); ?>"
                   class="nav-tab <?php echo $active_tab === 'logs' ? 'nav-tab-active' : ''; ?>">
                    更新ログ
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=han-logs&tab=posts' ) ); ?>"
                   class="nav-tab <?php echo $active_tab === 'posts' ? 'nav-tab-active' : ''; ?>">
                    投稿履歴
                </a>
            </h2>

            <?php if ( $active_tab === 'logs' ) : ?>
                <?php $this->render_logs_tab( $logs, $total, $total_pages, $page, $type, $level ); ?>
            <?php else : ?>
                <?php $this->render_posts_tab(); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * ログタブ
     */
    private function render_logs_tab( array $logs, int $total, int $total_pages, int $page, string $type, string $level ): void {
        ?>
        <!-- フィルター -->
        <div class="han-log-filters">
            <form method="get">
                <input type="hidden" name="page" value="han-logs">
                <input type="hidden" name="tab" value="logs">

                <select name="log_type">
                    <option value="">-- 種別 --</option>
                    <option value="cron" <?php selected( $type, 'cron' ); ?>>Cron</option>
                    <option value="post_generate" <?php selected( $type, 'post_generate' ); ?>>投稿生成</option>
                    <option value="post_update" <?php selected( $type, 'post_update' ); ?>>投稿更新</option>
                    <option value="post_recalc" <?php selected( $type, 'post_recalc' ); ?>>再計算</option>
                    <option value="calendar_save" <?php selected( $type, 'calendar_save' ); ?>>カレンダー保存</option>
                    <option value="settings_save" <?php selected( $type, 'settings_save' ); ?>>設定保存</option>
                    <option value="template_save" <?php selected( $type, 'template_save' ); ?>>テンプレート保存</option>
                    <option value="csv_import" <?php selected( $type, 'csv_import' ); ?>>CSVインポート</option>
                    <option value="manual_run" <?php selected( $type, 'manual_run' ); ?>>手動実行</option>
                    <option value="test_run" <?php selected( $type, 'test_run' ); ?>>テスト実行</option>
                </select>

                <select name="log_level">
                    <option value="">-- レベル --</option>
                    <option value="info" <?php selected( $level, 'info' ); ?>>Info</option>
                    <option value="warning" <?php selected( $level, 'warning' ); ?>>Warning</option>
                    <option value="error" <?php selected( $level, 'error' ); ?>>Error</option>
                </select>

                <button type="submit" class="button">フィルター</button>
            </form>
        </div>

        <!-- ログテーブル -->
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:160px;">日時</th>
                    <th style="width:70px;">レベル</th>
                    <th style="width:120px;">種別</th>
                    <th>メッセージ</th>
                    <th style="width:200px;">詳細</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $logs ) ) : ?>
                    <tr><td colspan="5">ログがありません。</td></tr>
                <?php else : ?>
                    <?php foreach ( $logs as $log ) : ?>
                        <tr class="han-log-<?php echo esc_attr( $log->log_level ); ?>">
                            <td><?php echo esc_html( $log->log_date ); ?></td>
                            <td>
                                <span class="han-log-level han-log-level-<?php echo esc_attr( $log->log_level ); ?>">
                                    <?php echo esc_html( strtoupper( $log->log_level ) ); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html( $log->log_type ); ?></td>
                            <td><?php echo esc_html( $log->message ); ?></td>
                            <td>
                                <?php if ( $log->context ) : ?>
                                    <code class="han-log-context"><?php echo esc_html( $log->context ); ?></code>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- ページネーション -->
        <?php if ( $total_pages > 1 ) : ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <span class="displaying-num"><?php echo esc_html( $total ); ?> 件</span>
                    <?php
                    echo paginate_links( [
                        'base'    => add_query_arg( 'paged', '%#%' ),
                        'format'  => '',
                        'current' => $page,
                        'total'   => $total_pages,
                    ] );
                    ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- ログ管理 -->
        <div class="han-log-actions" style="margin-top:20px;">
            <form method="post" style="display:inline-block;">
                <?php wp_nonce_field( 'han_cleanup_logs' ); ?>
                <input type="number" name="cleanup_days" value="90" min="1" max="365" class="small-text">
                <span>日より前のログを</span>
                <button type="submit" name="han_cleanup_logs" class="button">削除</button>
            </form>

            <form method="post" style="display:inline-block; margin-left:20px;">
                <?php wp_nonce_field( 'han_clear_logs' ); ?>
                <button type="submit" name="han_clear_logs" class="button"
                        onclick="return confirm('全てのログを削除しますか？');">
                    全ログ削除
                </button>
            </form>
        </div>
        <?php
    }

    /**
     * 投稿履歴タブ
     */
    private function render_posts_tab(): void {
        $args = [
            'post_type'      => HAN_Settings::get( 'post_type' ),
            'post_status'    => [ 'publish', 'future', 'draft', 'pending', 'trash' ],
            'meta_key'       => HAN_Post_Generator::META_GENERATED,
            'meta_value'     => '1',
            'posts_per_page' => 50,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        $query = new WP_Query( $args );
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:60px;">ID</th>
                    <th>タイトル</th>
                    <th style="width:120px;">休業日</th>
                    <th style="width:120px;">休業種別</th>
                    <th style="width:100px;">ステータス</th>
                    <th style="width:160px;">公開日</th>
                    <th style="width:120px;">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( ! $query->have_posts() ) : ?>
                    <tr><td colspan="7">自動生成された投稿はありません。</td></tr>
                <?php else : ?>
                    <?php while ( $query->have_posts() ) : $query->the_post(); ?>
                        <?php
                        $post_id      = get_the_ID();
                        $holiday_date = get_post_meta( $post_id, HAN_Post_Generator::META_HOLIDAY_DATE, true );
                        $holiday_type = get_post_meta( $post_id, HAN_Post_Generator::META_HOLIDAY_TYPE, true );
                        $type_labels  = HAN_Settings::get_holiday_type_labels();
                        ?>
                        <tr>
                            <td><?php echo esc_html( $post_id ); ?></td>
                            <td>
                                <a href="<?php echo esc_url( get_edit_post_link( $post_id ) ); ?>">
                                    <?php the_title(); ?>
                                </a>
                            </td>
                            <td><?php echo esc_html( $holiday_date ); ?></td>
                            <td><?php echo esc_html( $type_labels[ $holiday_type ] ?? $holiday_type ); ?></td>
                            <td><?php echo esc_html( get_post_status( $post_id ) ); ?></td>
                            <td><?php echo esc_html( get_the_date( 'Y-m-d H:i' ) ); ?></td>
                            <td>
                                <a href="<?php echo esc_url( get_edit_post_link( $post_id ) ); ?>" class="button button-small">編集</a>
                                <a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>" class="button button-small" target="_blank">表示</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
                <?php wp_reset_postdata(); ?>
            </tbody>
        </table>
        <?php
    }
}
