<?php
/**
 * ショートコード・ブロック出力クラス
 *
 * サイト上の営業ステータス表示を制御する
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HAN_Shortcode {

    private static ?self $instance = null;

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // ショートコード登録
        add_shortcode( 'han_status', [ $this, 'render_status' ] );
        add_shortcode( 'han_next_holiday', [ $this, 'render_next_holiday' ] );

        // ブロック登録
        add_action( 'init', [ $this, 'register_blocks' ] );

        // ウィジェット登録
        add_action( 'widgets_init', [ $this, 'register_widget' ] );
    }

    /**
     * [han_status] ショートコード
     *
     * 当日の営業ステータスを表示する
     */
    public function render_status( array $atts = [] ): string {
        $atts = shortcode_atts( [
            'wrapper'       => 'div',
            'class'         => 'han-status',
            'show_normal'   => 'true',
            'normal_text'   => '',
        ], $atts, 'han_status' );

        $today_entry = HAN_Calendar::get_today();
        $message     = self::get_today_message( $today_entry );

        // 通常営業の表示制御
        if ( ! $message && $atts['show_normal'] !== 'true' ) {
            return '';
        }

        if ( ! $message ) {
            $message = $atts['normal_text'] ?: HAN_Template::render(
                HAN_Template::get( 'day', 'normal', 'message' ),
                [ 'business_hours' => HAN_Settings::get( 'business_hours' ) ]
            );
        }

        // ステータスCSSクラスを決定
        $status_class = 'han-normal';
        if ( $today_entry ) {
            $status_class = match ( $today_entry->status ) {
                'holiday' => 'han-holiday',
                'short'   => 'han-short',
                'special' => 'han-special',
                default   => 'han-normal',
            };
        }

        $tag = tag_escape( $atts['wrapper'] );
        $class = esc_attr( $atts['class'] . ' ' . $status_class );

        return sprintf(
            '<%1$s class="%2$s">%3$s</%1$s>',
            $tag,
            $class,
            esc_html( $message )
        );
    }

    /**
     * [han_next_holiday] ショートコード
     *
     * 次の休業日情報を表示する
     */
    public function render_next_holiday( array $atts = [] ): string {
        $atts = shortcode_atts( [
            'wrapper' => 'div',
            'class'   => 'han-next-holiday',
            'format'  => '次回の休業日: {date}（{weekday}）',
        ], $atts, 'han_next_holiday' );

        global $wpdb;
        $today = wp_date( 'Y-m-d' );

        $next_holiday = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM %i WHERE cal_date > %s AND status = 'holiday' ORDER BY cal_date ASC LIMIT 1",
                HAN_Calendar::table_name(),
                $today
            )
        );

        if ( ! $next_holiday ) {
            return '';
        }

        $vars = HAN_Template::build_vars_from_calendar( $next_holiday );
        $message = HAN_Template::render( $atts['format'], $vars );

        $tag   = tag_escape( $atts['wrapper'] );
        $class = esc_attr( $atts['class'] );

        return sprintf(
            '<%1$s class="%2$s">%3$s</%1$s>',
            $tag,
            $class,
            esc_html( $message )
        );
    }

    /**
     * 当日メッセージを取得
     *
     * 優先順位:
     * 1. 個別上書き
     * 2. 年末年始
     * 3. お盆
     * 4. 祝日
     * 5. 祝日以外の休業
     * 6. 短縮営業
     * 7. 特別営業
     * 8. 通常営業
     */
    public static function get_today_message( ?object $today_entry ): string {
        if ( ! HAN_Settings::get( 'day_display' ) ) {
            return '';
        }

        $vars = $today_entry
            ? HAN_Template::build_vars_from_calendar( $today_entry )
            : [ 'business_hours' => HAN_Settings::get( 'business_hours' ) ];

        // データがない場合は通常営業
        if ( ! $today_entry ) {
            return HAN_Template::render(
                HAN_Template::get( 'day', 'normal', 'message' ),
                $vars
            );
        }

        // 1. 個別上書き
        if ( ! empty( $today_entry->custom_day_message ) ) {
            return HAN_Template::render( $today_entry->custom_day_message, $vars );
        }

        // 2-5. 休業の場合: 種別で分岐
        if ( $today_entry->status === 'holiday' ) {
            // 優先順位に従って種別を判定
            $type = $today_entry->holiday_type;
            $priority_order = [ 'yearend', 'obon', 'public_holiday', 'regular', 'special_case' ];

            if ( $type && in_array( $type, $priority_order, true ) ) {
                return HAN_Template::render(
                    HAN_Template::get( 'day', $type, 'message' ),
                    $vars
                );
            }

            // 種別未設定の休業
            return HAN_Template::render(
                HAN_Template::get( 'day', 'regular', 'message' ),
                $vars
            );
        }

        // 6. 短縮営業
        if ( $today_entry->status === 'short' ) {
            return HAN_Template::render(
                HAN_Template::get( 'day', 'short', 'message' ),
                $vars
            );
        }

        // 7. 特別営業
        if ( $today_entry->status === 'special' ) {
            return HAN_Template::render(
                HAN_Template::get( 'day', 'special_business', 'message' ),
                $vars
            );
        }

        // 8. 通常営業
        return HAN_Template::render(
            HAN_Template::get( 'day', 'normal', 'message' ),
            $vars
        );
    }

    /**
     * Gutenbergブロック登録
     */
    public function register_blocks(): void {
        // サーバーサイドレンダリングブロック
        if ( function_exists( 'register_block_type' ) ) {
            register_block_type( 'holiday-auto-notice/status', [
                'render_callback' => [ $this, 'render_status_block' ],
                'attributes'      => [
                    'showNormal' => [
                        'type'    => 'boolean',
                        'default' => true,
                    ],
                    'className' => [
                        'type'    => 'string',
                        'default' => '',
                    ],
                ],
            ] );
        }
    }

    /**
     * ブロックレンダリング
     */
    public function render_status_block( array $attributes ): string {
        $atts = [
            'show_normal' => $attributes['showNormal'] ? 'true' : 'false',
            'class'       => 'han-status ' . ( $attributes['className'] ?? '' ),
        ];
        return $this->render_status( $atts );
    }

    /**
     * ウィジェット登録
     */
    public function register_widget(): void {
        register_widget( 'HAN_Status_Widget' );
    }
}

/**
 * 営業ステータスウィジェット
 */
class HAN_Status_Widget extends WP_Widget {

    public function __construct() {
        parent::__construct(
            'han_status_widget',
            '営業ステータス（Holiday Auto Notice）',
            [ 'description' => '当日の営業ステータスを表示します' ]
        );
    }

    public function widget( $args, $instance ): void {
        $title = apply_filters( 'widget_title', $instance['title'] ?? '' );

        echo $args['before_widget'];

        if ( $title ) {
            echo $args['before_title'] . esc_html( $title ) . $args['after_title'];
        }

        $shortcode = new HAN_Shortcode();
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ショートコード内でエスケープ済み
        echo $shortcode->render_status( [
            'show_normal' => $instance['show_normal'] ?? 'true',
        ] );

        echo $args['after_widget'];
    }

    public function form( $instance ): void {
        $title       = $instance['title'] ?? '営業ステータス';
        $show_normal = $instance['show_normal'] ?? 'true';
        ?>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">タイトル:</label>
            <input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
                   name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"
                   type="text" value="<?php echo esc_attr( $title ); ?>">
        </p>
        <p>
            <input type="checkbox" id="<?php echo esc_attr( $this->get_field_id( 'show_normal' ) ); ?>"
                   name="<?php echo esc_attr( $this->get_field_name( 'show_normal' ) ); ?>"
                   value="true" <?php checked( $show_normal, 'true' ); ?>>
            <label for="<?php echo esc_attr( $this->get_field_id( 'show_normal' ) ); ?>">通常営業日も表示する</label>
        </p>
        <?php
    }

    public function update( $new_instance, $old_instance ): array {
        return [
            'title'       => sanitize_text_field( $new_instance['title'] ?? '' ),
            'show_normal' => isset( $new_instance['show_normal'] ) ? 'true' : 'false',
        ];
    }
}
