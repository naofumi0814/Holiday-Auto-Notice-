<?php
/**
 * 設定画面クラス
 *
 * プラグインの各種設定UIを提供する
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HAN_Admin_Settings {

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

        // 保存処理
        if ( isset( $_POST['han_save_settings'] ) ) {
            $this->handle_save();
        }

        $settings = HAN_Settings::get();
        $short_notice_labels = HAN_Settings::get_short_notice_labels();

        ?>
        <div class="wrap han-wrap">
            <h1>Holiday Auto Notice 設定</h1>
            <?php settings_errors( 'han' ); ?>

            <form method="post">
                <?php wp_nonce_field( 'han_save_settings' ); ?>

                <!-- 基本設定 -->
                <h2>基本設定</h2>
                <table class="form-table">
                    <tr>
                        <th>サイト名</th>
                        <td>
                            <input type="text" name="settings[site_name]"
                                   value="<?php echo esc_attr( $settings['site_name'] ); ?>"
                                   class="regular-text">
                            <p class="description">テンプレートの {site_name} に使用されます</p>
                        </td>
                    </tr>
                    <tr>
                        <th>対象年度</th>
                        <td>
                            <select name="settings[target_year]">
                                <?php for ( $y = (int) date( 'Y' ) - 1; $y <= (int) date( 'Y' ) + 2; $y++ ) : ?>
                                    <option value="<?php echo esc_attr( $y ); ?>"
                                            <?php selected( $settings['target_year'], $y ); ?>>
                                        <?php echo esc_html( $y ); ?>年
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>タイムゾーン</th>
                        <td>
                            <input type="text" name="settings[timezone]"
                                   value="<?php echo esc_attr( $settings['timezone'] ); ?>"
                                   class="regular-text" readonly>
                            <p class="description">WordPress設定に従います</p>
                        </td>
                    </tr>
                    <tr>
                        <th>営業時間</th>
                        <td>
                            <input type="text" name="settings[business_hours]"
                                   value="<?php echo esc_attr( $settings['business_hours'] ); ?>"
                                   class="regular-text">
                            <p class="description">短縮営業表示の {business_hours} に使用されます</p>
                        </td>
                    </tr>
                </table>

                <!-- 自動運用設定 -->
                <h2>自動運用設定</h2>
                <table class="form-table">
                    <tr>
                        <th>自動運用</th>
                        <td>
                            <label>
                                <input type="checkbox" name="settings[auto_operation]" value="1"
                                       <?php checked( $settings['auto_operation'] ); ?>>
                                自動運用を有効にする
                            </label>
                            <p class="description">OFFにすると全ての自動処理が停止します</p>
                        </td>
                    </tr>
                    <tr>
                        <th>自動投稿</th>
                        <td>
                            <label>
                                <input type="checkbox" name="settings[auto_post]" value="1"
                                       <?php checked( $settings['auto_post'] ); ?>>
                                休業日の事前告知を自動投稿する
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th>当日表示</th>
                        <td>
                            <label>
                                <input type="checkbox" name="settings[day_display]" value="1"
                                       <?php checked( $settings['day_display'] ); ?>>
                                当日の営業ステータス表示を有効にする
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th>固定ページ更新</th>
                        <td>
                            <label>
                                <input type="checkbox" name="settings[fixed_page_update]" value="1"
                                       <?php checked( $settings['fixed_page_update'] ); ?>>
                                指定した固定ページのプレースホルダを自動更新する
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th>対象固定ページ</th>
                        <td>
                            <?php
                            wp_dropdown_pages( [
                                'name'              => 'settings[fixed_page_id]',
                                'selected'          => $settings['fixed_page_id'],
                                'show_option_none'  => '-- 選択してください --',
                                'option_none_value' => '0',
                            ] );
                            ?>
                            <p class="description">本文に <code>&lt;!-- han_status --&gt;&lt;!-- /han_status --&gt;</code> を記述してください</p>
                        </td>
                    </tr>
                </table>

                <!-- 投稿設定 -->
                <h2>投稿設定</h2>
                <table class="form-table">
                    <tr>
                        <th>投稿タイプ</th>
                        <td>
                            <select name="settings[post_type]">
                                <?php
                                $post_types = get_post_types( [ 'public' => true ], 'objects' );
                                foreach ( $post_types as $pt ) :
                                    if ( $pt->name === 'attachment' ) continue;
                                ?>
                                    <option value="<?php echo esc_attr( $pt->name ); ?>"
                                            <?php selected( $settings['post_type'], $pt->name ); ?>>
                                        <?php echo esc_html( $pt->label ); ?> (<?php echo esc_html( $pt->name ); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>カテゴリ</th>
                        <td>
                            <?php
                            wp_dropdown_categories( [
                                'name'              => 'settings[category_id]',
                                'selected'          => $settings['category_id'],
                                'show_option_none'  => '-- 指定なし --',
                                'option_none_value' => '0',
                                'hide_empty'        => false,
                            ] );
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th>投稿者</th>
                        <td>
                            <?php
                            wp_dropdown_users( [
                                'name'              => 'settings[author_id]',
                                'selected'          => $settings['author_id'],
                                'show_option_none'  => '-- 現在のユーザー --',
                                'option_none_value' => '0',
                            ] );
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th>公開ステータス</th>
                        <td>
                            <select name="settings[post_status]">
                                <option value="publish" <?php selected( $settings['post_status'], 'publish' ); ?>>公開</option>
                                <option value="draft" <?php selected( $settings['post_status'], 'draft' ); ?>>下書き</option>
                                <option value="pending" <?php selected( $settings['post_status'], 'pending' ); ?>>レビュー待ち</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>何日前に公開するか</th>
                        <td>
                            <input type="number" name="settings[days_before]"
                                   value="<?php echo esc_attr( $settings['days_before'] ); ?>"
                                   min="1" max="90" class="small-text">
                            <span>日前</span>
                        </td>
                    </tr>
                </table>

                <!-- 例外処理設定 -->
                <h2>直前登録時の挙動</h2>
                <table class="form-table">
                    <tr>
                        <th>直前登録時の挙動</th>
                        <td>
                            <?php foreach ( $short_notice_labels as $val => $label ) : ?>
                                <label style="display:block; margin-bottom:5px;">
                                    <input type="radio" name="settings[short_notice_action]"
                                           value="<?php echo esc_attr( $val ); ?>"
                                           <?php checked( $settings['short_notice_action'], $val ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </label>
                            <?php endforeach; ?>
                            <p class="description">休業日まで指定日数未満の場合の動作</p>
                        </td>
                    </tr>
                    <tr>
                        <th>指定時刻</th>
                        <td>
                            <input type="time" name="settings[short_notice_time]"
                                   value="<?php echo esc_attr( $settings['short_notice_time'] ); ?>">
                            <p class="description">「指定時刻で公開」を選んだ場合の公開時刻</p>
                        </td>
                    </tr>
                </table>

                <!-- その他設定 -->
                <h2>その他</h2>
                <table class="form-table">
                    <tr>
                        <th>公開済み投稿の更新</th>
                        <td>
                            <label>
                                <input type="checkbox" name="settings[update_published]" value="1"
                                       <?php checked( $settings['update_published'] ); ?>>
                                カレンダー変更時に公開済み投稿も更新する
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th>ログ保存</th>
                        <td>
                            <label>
                                <input type="checkbox" name="settings[log_enabled]" value="1"
                                       <?php checked( $settings['log_enabled'] ); ?>>
                                更新ログを保存する
                            </label>
                        </td>
                    </tr>
                </table>

                <!-- ショートコード案内 -->
                <h2>ショートコード</h2>
                <table class="form-table">
                    <tr>
                        <th>営業ステータス表示</th>
                        <td>
                            <code>[han_status]</code>
                            <p class="description">当日の営業ステータスを表示します</p>
                            <br>
                            <code>[han_status show_normal="false"]</code>
                            <p class="description">通常営業日は非表示にする場合</p>
                        </td>
                    </tr>
                    <tr>
                        <th>次回休業日表示</th>
                        <td>
                            <code>[han_next_holiday]</code>
                            <p class="description">次回の休業日情報を表示します</p>
                        </td>
                    </tr>
                    <tr>
                        <th>固定ページ用</th>
                        <td>
                            <code>&lt;!-- han_status --&gt;&lt;!-- /han_status --&gt;</code>
                            <p class="description">固定ページの本文にこのコメントを記述すると、中身が自動置換されます</p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" name="han_save_settings" class="button button-primary">
                        設定を保存
                    </button>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * 設定保存処理
     */
    private function handle_save(): void {
        check_admin_referer( 'han_save_settings' );

        $input = $_POST['settings'] ?? [];
        if ( ! is_array( $input ) ) {
            add_settings_error( 'han', 'save_error', '無効なデータです。', 'error' );
            return;
        }

        // チェックボックスの処理（未チェック時はPOSTに含まれない）
        $checkboxes = [ 'auto_operation', 'auto_post', 'day_display', 'fixed_page_update', 'update_published', 'log_enabled' ];
        foreach ( $checkboxes as $key ) {
            $input[ $key ] = isset( $input[ $key ] ) ? 1 : 0;
        }

        if ( HAN_Settings::save( $input ) ) {
            HAN_Logger::info( 'settings_save', '設定を保存しました' );
            add_settings_error( 'han', 'save_success', '設定を保存しました。', 'success' );
        } else {
            add_settings_error( 'han', 'save_error', '設定の保存に失敗しました。', 'error' );
        }
    }
}
