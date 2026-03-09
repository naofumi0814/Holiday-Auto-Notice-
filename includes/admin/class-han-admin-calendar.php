<?php
/**
 * カレンダー管理画面クラス
 *
 * 年間カレンダーの表示と編集UIを提供する
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HAN_Admin_Calendar {

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

        // 月別保存処理
        if ( isset( $_POST['han_save_month'] ) ) {
            $this->handle_save_month();
        }

        $year  = absint( $_GET['year'] ?? HAN_Settings::get( 'target_year' ) );
        $month = absint( $_GET['month'] ?? (int) date( 'n' ) );

        if ( $month < 1 || $month > 12 ) {
            $month = (int) date( 'n' );
        }

        $days_in_month = cal_days_in_month( CAL_GREGORIAN, $month, $year );
        $cal_data      = HAN_Calendar::get_month( $year, $month );

        // 日付をキーにした連想配列に変換
        $day_map = [];
        foreach ( $cal_data as $row ) {
            $d = (int) date( 'j', strtotime( $row->cal_date ) );
            $day_map[ $d ] = $row;
        }

        $status_labels       = HAN_Settings::get_status_labels();
        $holiday_type_labels = HAN_Settings::get_holiday_type_labels();

        ?>
        <div class="wrap han-wrap">
            <h1>休業カレンダー管理</h1>
            <?php settings_errors( 'han' ); ?>

            <!-- 年月ナビゲーション -->
            <div class="han-nav">
                <div class="han-nav-year">
                    <?php for ( $y = $year - 1; $y <= $year + 1; $y++ ) : ?>
                        <a href="<?php echo esc_url( admin_url( "admin.php?page=han-calendar&year={$y}&month={$month}" ) ); ?>"
                           class="button <?php echo $y === $year ? 'button-primary' : ''; ?>">
                            <?php echo esc_html( $y ); ?>年
                        </a>
                    <?php endfor; ?>
                </div>
                <div class="han-nav-month">
                    <?php for ( $m = 1; $m <= 12; $m++ ) : ?>
                        <a href="<?php echo esc_url( admin_url( "admin.php?page=han-calendar&year={$year}&month={$m}" ) ); ?>"
                           class="button <?php echo $m === $month ? 'button-primary' : ''; ?>">
                            <?php echo esc_html( $m ); ?>月
                        </a>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- ツールバー -->
            <div class="han-toolbar">
                <div class="han-toolbar-left">
                    <h2><?php echo esc_html( "{$year}年{$month}月" ); ?></h2>
                </div>
                <div class="han-toolbar-right">
                    <button type="button" class="button" id="han-test-run">テスト実行</button>
                    <button type="button" class="button" id="han-manual-run">手動実行</button>

                    <a href="<?php echo esc_url( wp_nonce_url(
                        admin_url( "admin-ajax.php?action=han_export_csv&year={$year}&type=data" ),
                        'han_admin_nonce',
                        'nonce'
                    ) ); ?>" class="button">CSVエクスポート</a>

                    <a href="<?php echo esc_url( wp_nonce_url(
                        admin_url( "admin-ajax.php?action=han_export_csv&year={$year}&type=template" ),
                        'han_admin_nonce',
                        'nonce'
                    ) ); ?>" class="button">CSVテンプレート</a>
                </div>
            </div>

            <!-- CSVインポート -->
            <div class="han-import-section">
                <form method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field( 'han_csv_import' ); ?>
                    <input type="file" name="han_csv_file" accept=".csv">
                    <button type="submit" name="han_import_csv" class="button">CSVインポート</button>
                </form>

                <form method="post" style="display:inline-block; margin-left: 20px;">
                    <?php wp_nonce_field( 'han_copy_year' ); ?>
                    <input type="hidden" name="source_year" value="<?php echo esc_attr( $year ); ?>">
                    <button type="submit" name="han_copy_year" class="button"
                            onclick="return confirm('<?php echo esc_js( "{$year}年のデータを" . ( $year + 1 ) . "年に複製しますか？" ); ?>');">
                        <?php echo esc_html( ( $year + 1 ) . '年へ複製' ); ?>
                    </button>
                </form>
            </div>

            <!-- カレンダーテーブル -->
            <form method="post" id="han-month-form">
                <?php wp_nonce_field( 'han_save_month' ); ?>
                <input type="hidden" name="han_year" value="<?php echo esc_attr( $year ); ?>">
                <input type="hidden" name="han_month" value="<?php echo esc_attr( $month ); ?>">

                <table class="wp-list-table widefat fixed striped han-calendar-table">
                    <thead>
                        <tr>
                            <th class="han-col-date">日付</th>
                            <th class="han-col-weekday">曜日</th>
                            <th class="han-col-status">ステータス</th>
                            <th class="han-col-type">休業種別</th>
                            <th class="han-col-memo">メモ</th>
                            <th class="han-col-auto">自動投稿</th>
                            <th class="han-col-post">投稿</th>
                            <th class="han-col-actions">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for ( $d = 1; $d <= $days_in_month; $d++ ) :
                            $date_str = sprintf( '%04d-%02d-%02d', $year, $month, $d );
                            $weekday  = HAN_Calendar::get_weekday_ja( $date_str );
                            $w_num    = (int) date( 'w', strtotime( $date_str ) );
                            $entry    = $day_map[ $d ] ?? null;

                            $status       = $entry->status ?? 'normal';
                            $holiday_type = $entry->holiday_type ?? '';
                            $memo         = $entry->memo ?? '';
                            $auto_post    = $entry ? (int) $entry->auto_post : 1;
                            $post_id      = $entry->post_id ?? 0;

                            $row_class = 'han-day';
                            if ( $w_num === 0 ) $row_class .= ' han-sunday';
                            if ( $w_num === 6 ) $row_class .= ' han-saturday';
                            if ( $status === 'holiday' ) $row_class .= ' han-holiday-row';
                            if ( $status === 'short' ) $row_class .= ' han-short-row';
                            if ( $status === 'special' ) $row_class .= ' han-special-row';
                            if ( $date_str === wp_date( 'Y-m-d' ) ) $row_class .= ' han-today';
                        ?>
                        <tr class="<?php echo esc_attr( $row_class ); ?>" data-date="<?php echo esc_attr( $date_str ); ?>">
                            <td class="han-col-date">
                                <?php echo esc_html( "{$month}/{$d}" ); ?>
                            </td>
                            <td class="han-col-weekday han-weekday-<?php echo esc_attr( $w_num ); ?>">
                                <?php echo esc_html( $weekday ); ?>
                            </td>
                            <td class="han-col-status">
                                <select name="days[<?php echo $d; ?>][status]" class="han-status-select">
                                    <?php foreach ( $status_labels as $val => $label ) : ?>
                                        <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $status, $val ); ?>>
                                            <?php echo esc_html( $label ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td class="han-col-type">
                                <select name="days[<?php echo $d; ?>][holiday_type]" class="han-type-select"
                                        <?php echo $status !== 'holiday' ? 'disabled' : ''; ?>>
                                    <option value="">--</option>
                                    <?php foreach ( $holiday_type_labels as $val => $label ) : ?>
                                        <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $holiday_type, $val ); ?>>
                                            <?php echo esc_html( $label ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td class="han-col-memo">
                                <input type="text" name="days[<?php echo $d; ?>][memo]"
                                       value="<?php echo esc_attr( $memo ); ?>"
                                       class="han-memo-input" placeholder="メモ">
                            </td>
                            <td class="han-col-auto">
                                <input type="checkbox" name="days[<?php echo $d; ?>][auto_post]"
                                       value="1" <?php checked( $auto_post, 1 ); ?>>
                            </td>
                            <td class="han-col-post">
                                <?php if ( $post_id ) : ?>
                                    <a href="<?php echo esc_url( get_edit_post_link( $post_id ) ); ?>"
                                       target="_blank" class="han-post-link">
                                        #<?php echo esc_html( $post_id ); ?>
                                    </a>
                                    <span class="han-post-status">
                                        (<?php echo esc_html( get_post_status( $post_id ) ); ?>)
                                    </span>
                                <?php else : ?>
                                    <button type="button" class="button button-small han-generate-btn"
                                            data-date="<?php echo esc_attr( $date_str ); ?>"
                                            <?php echo $status !== 'holiday' ? 'disabled' : ''; ?>>
                                        生成
                                    </button>
                                <?php endif; ?>
                            </td>
                            <td class="han-col-actions">
                                <button type="button" class="button button-small han-edit-btn"
                                        data-date="<?php echo esc_attr( $date_str ); ?>">
                                    詳細
                                </button>
                                <button type="button" class="button button-small han-preview-btn"
                                        data-date="<?php echo esc_attr( $date_str ); ?>"
                                        <?php echo $status !== 'holiday' ? 'disabled' : ''; ?>>
                                    ﾌﾟﾚﾋﾞｭｰ
                                </button>
                            </td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>

                <p class="submit">
                    <button type="submit" name="han_save_month" class="button button-primary button-large">
                        この月を保存
                    </button>
                </p>
            </form>
        </div>

        <!-- 日付詳細モーダル -->
        <div id="han-day-modal" class="han-modal" style="display:none;">
            <div class="han-modal-content">
                <div class="han-modal-header">
                    <h2 id="han-modal-title">日付詳細</h2>
                    <button type="button" class="han-modal-close">&times;</button>
                </div>
                <div class="han-modal-body">
                    <input type="hidden" id="han-modal-date">

                    <table class="form-table">
                        <tr>
                            <th>個別タイトル</th>
                            <td><input type="text" id="han-modal-custom-title" class="large-text"
                                       placeholder="空欄の場合はテンプレートを使用"></td>
                        </tr>
                        <tr>
                            <th>個別本文</th>
                            <td><textarea id="han-modal-custom-body" rows="6" class="large-text"
                                          placeholder="空欄の場合はテンプレートを使用"></textarea></td>
                        </tr>
                        <tr>
                            <th>個別当日表示文</th>
                            <td><textarea id="han-modal-custom-day-message" rows="3" class="large-text"
                                          placeholder="空欄の場合はテンプレートを使用"></textarea></td>
                        </tr>
                    </table>
                </div>
                <div class="han-modal-footer">
                    <button type="button" class="button button-primary" id="han-modal-save">保存</button>
                    <button type="button" class="button han-modal-close">閉じる</button>
                </div>
            </div>
        </div>

        <!-- プレビューモーダル -->
        <div id="han-preview-modal" class="han-modal" style="display:none;">
            <div class="han-modal-content">
                <div class="han-modal-header">
                    <h2>投稿プレビュー</h2>
                    <button type="button" class="han-modal-close">&times;</button>
                </div>
                <div class="han-modal-body">
                    <div id="han-preview-content"></div>
                </div>
                <div class="han-modal-footer">
                    <button type="button" class="button han-modal-close">閉じる</button>
                </div>
            </div>
        </div>

        <!-- テスト/手動実行結果モーダル -->
        <div id="han-result-modal" class="han-modal" style="display:none;">
            <div class="han-modal-content">
                <div class="han-modal-header">
                    <h2 id="han-result-title">実行結果</h2>
                    <button type="button" class="han-modal-close">&times;</button>
                </div>
                <div class="han-modal-body">
                    <div id="han-result-content"></div>
                </div>
                <div class="han-modal-footer">
                    <button type="button" class="button han-modal-close">閉じる</button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * 月別保存処理
     */
    private function handle_save_month(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( '権限がありません。' );
        }

        check_admin_referer( 'han_save_month' );

        $year  = absint( $_POST['han_year'] ?? 0 );
        $month = absint( $_POST['han_month'] ?? 0 );
        $days  = $_POST['days'] ?? [];

        if ( ! $year || ! $month || ! is_array( $days ) ) {
            add_settings_error( 'han', 'save_error', '無効なデータです。', 'error' );
            return;
        }

        $days_in_month = cal_days_in_month( CAL_GREGORIAN, $month, $year );
        $saved = 0;

        for ( $d = 1; $d <= $days_in_month; $d++ ) {
            $day_data = $days[ $d ] ?? [];
            $date_str = sprintf( '%04d-%02d-%02d', $year, $month, $d );

            $data = [
                'cal_date'     => $date_str,
                'status'       => sanitize_key( $day_data['status'] ?? 'normal' ),
                'holiday_type' => isset( $day_data['holiday_type'] ) ? sanitize_key( $day_data['holiday_type'] ) : null,
                'memo'         => sanitize_textarea_field( $day_data['memo'] ?? '' ),
                'auto_post'    => isset( $day_data['auto_post'] ) ? 1 : 0,
            ];

            // 休業でない場合は休業種別をクリア
            if ( $data['status'] !== 'holiday' ) {
                $data['holiday_type'] = null;
            }

            if ( HAN_Calendar::save_date( $data ) ) {
                $saved++;
            }
        }

        // 保存後に未公開投稿を再計算
        HAN_Post_Generator::recalculate_pending_posts();

        HAN_Logger::info( 'calendar_save', "カレンダーを保存しました（{$year}年{$month}月: {$saved}日分）" );
        add_settings_error( 'han', 'save_success', "{$year}年{$month}月のカレンダーを保存しました（{$saved}日分）。", 'success' );
    }
}
