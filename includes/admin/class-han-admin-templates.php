<?php
/**
 * テンプレート管理画面クラス
 *
 * 事前告知・当日表示・営業再開案内のテンプレート編集UIを提供する
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HAN_Admin_Templates {

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
        if ( isset( $_POST['han_save_templates'] ) ) {
            $this->handle_save();
        }

        // リセット処理
        if ( isset( $_POST['han_reset_templates'] ) ) {
            check_admin_referer( 'han_save_templates' );
            delete_option( HAN_Template::OPTION_KEY );
            HAN_Template::set_defaults();
            HAN_Logger::info( 'template_reset', 'テンプレートを初期値にリセットしました' );
            add_settings_error( 'han', 'reset_success', 'テンプレートを初期値にリセットしました。', 'success' );
        }

        $templates           = HAN_Template::get_all();
        $holiday_type_labels = HAN_Settings::get_holiday_type_labels();

        // テンプレート種別タブ
        $active_tab = sanitize_key( $_GET['tab'] ?? 'advance' );
        if ( ! in_array( $active_tab, [ 'advance', 'day', 'reopen' ], true ) ) {
            $active_tab = 'advance';
        }

        $tab_labels = [
            'advance' => '事前告知投稿用',
            'day'     => '当日表示用',
            'reopen'  => '営業再開案内用',
        ];

        ?>
        <div class="wrap han-wrap">
            <h1>テンプレート管理</h1>
            <?php settings_errors( 'han' ); ?>

            <p class="description">
                利用可能な変数:
                <code>{site_name}</code>
                <code>{date}</code>
                <code>{year}</code>
                <code>{month}</code>
                <code>{day}</code>
                <code>{weekday}</code>
                <code>{holiday_name}</code>
                <code>{next_business_date}</code>
                <code>{next_business_weekday}</code>
                <code>{business_hours}</code>
                <code>{notice_days_before}</code>
                <code>{note}</code>
            </p>

            <!-- タブ -->
            <h2 class="nav-tab-wrapper">
                <?php foreach ( $tab_labels as $tab_key => $tab_label ) : ?>
                    <a href="<?php echo esc_url( admin_url( "admin.php?page=han-templates&tab={$tab_key}" ) ); ?>"
                       class="nav-tab <?php echo $active_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html( $tab_label ); ?>
                    </a>
                <?php endforeach; ?>
            </h2>

            <form method="post">
                <?php wp_nonce_field( 'han_save_templates' ); ?>

                <?php if ( $active_tab === 'advance' ) : ?>
                    <?php foreach ( $holiday_type_labels as $type_key => $type_label ) : ?>
                        <div class="han-template-section">
                            <h3><?php echo esc_html( $type_label ); ?></h3>
                            <table class="form-table">
                                <tr>
                                    <th>タイトルテンプレート</th>
                                    <td>
                                        <input type="text"
                                               name="templates[advance][<?php echo esc_attr( $type_key ); ?>][title]"
                                               value="<?php echo esc_attr( $templates['advance'][ $type_key ]['title'] ?? '' ); ?>"
                                               class="large-text">
                                    </td>
                                </tr>
                                <tr>
                                    <th>本文テンプレート</th>
                                    <td>
                                        <textarea name="templates[advance][<?php echo esc_attr( $type_key ); ?>][body]"
                                                  rows="6" class="large-text"><?php
                                            echo esc_textarea( $templates['advance'][ $type_key ]['body'] ?? '' );
                                        ?></textarea>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    <?php endforeach; ?>

                <?php elseif ( $active_tab === 'day' ) : ?>
                    <?php
                    $day_types = array_merge(
                        $holiday_type_labels,
                        [
                            'short'             => '短縮営業',
                            'special_business'  => '特別営業',
                            'normal'            => '通常営業',
                        ]
                    );
                    ?>
                    <?php foreach ( $day_types as $type_key => $type_label ) : ?>
                        <div class="han-template-section">
                            <h3><?php echo esc_html( $type_label ); ?></h3>
                            <table class="form-table">
                                <tr>
                                    <th>表示メッセージ</th>
                                    <td>
                                        <textarea name="templates[day][<?php echo esc_attr( $type_key ); ?>][message]"
                                                  rows="3" class="large-text"><?php
                                            echo esc_textarea( $templates['day'][ $type_key ]['message'] ?? '' );
                                        ?></textarea>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    <?php endforeach; ?>

                <?php elseif ( $active_tab === 'reopen' ) : ?>
                    <?php foreach ( $holiday_type_labels as $type_key => $type_label ) : ?>
                        <div class="han-template-section">
                            <h3><?php echo esc_html( $type_label ); ?></h3>
                            <table class="form-table">
                                <tr>
                                    <th>タイトルテンプレート</th>
                                    <td>
                                        <input type="text"
                                               name="templates[reopen][<?php echo esc_attr( $type_key ); ?>][title]"
                                               value="<?php echo esc_attr( $templates['reopen'][ $type_key ]['title'] ?? '' ); ?>"
                                               class="large-text">
                                    </td>
                                </tr>
                                <tr>
                                    <th>本文テンプレート</th>
                                    <td>
                                        <textarea name="templates[reopen][<?php echo esc_attr( $type_key ); ?>][body]"
                                                  rows="6" class="large-text"><?php
                                            echo esc_textarea( $templates['reopen'][ $type_key ]['body'] ?? '' );
                                        ?></textarea>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <p class="submit">
                    <button type="submit" name="han_save_templates" class="button button-primary">
                        テンプレートを保存
                    </button>
                    <button type="submit" name="han_reset_templates" class="button"
                            onclick="return confirm('テンプレートを初期値にリセットしますか？');">
                        初期値にリセット
                    </button>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * テンプレート保存処理
     */
    private function handle_save(): void {
        check_admin_referer( 'han_save_templates' );

        $templates = $_POST['templates'] ?? [];
        if ( ! is_array( $templates ) ) {
            add_settings_error( 'han', 'save_error', '無効なデータです。', 'error' );
            return;
        }

        // 既存テンプレートとマージ（送信されたタブのデータのみ更新）
        $current = HAN_Template::get_all();
        foreach ( $templates as $type => $types ) {
            if ( is_array( $types ) ) {
                foreach ( $types as $holiday_type => $fields ) {
                    if ( is_array( $fields ) ) {
                        foreach ( $fields as $field => $value ) {
                            $current[ $type ][ $holiday_type ][ $field ] = $value;
                        }
                    }
                }
            }
        }

        if ( HAN_Template::save( $current ) ) {
            HAN_Logger::info( 'template_save', 'テンプレートを保存しました' );
            add_settings_error( 'han', 'save_success', 'テンプレートを保存しました。', 'success' );
        } else {
            add_settings_error( 'han', 'save_error', 'テンプレートの保存に失敗しました。', 'error' );
        }
    }
}
