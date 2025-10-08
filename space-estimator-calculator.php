<?php
/**
 * Plugin Name: Space Estimator Calculator
 * Plugin URI: https://nubevest.com.au/
 * Description: A comprehensive space estimator tool with design matching the provided image, including full CRUD for rooms and items, image support, and shortcode embedding.
 * Version: 1.0.0
 * Author: Anurag Singh
 * Author URI: https://anuragsingk.com
 * License: GPL v2 or later
 * Text Domain: space-estimator
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Tested up to: 6.6
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'SPACE_ESTIMATOR_VERSION', '1.0.0' );
define( 'SPACE_ESTIMATOR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SPACE_ESTIMATOR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

class Space_Estimator_Calculator {

    public function __construct() {
        add_action( 'init', array( $this, 'init' ) );
        add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
        add_action( 'admin_menu', array( $this, 'admin_menu' ) );
        add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
        add_action( 'save_post', array( $this, 'save_meta' ) );
        add_action( 'wp_ajax_delete_item', array( $this, 'ajax_delete_item' ) );
        add_action( 'wp_ajax_delete_room', array( $this, 'ajax_delete_room' ) );
        add_action('wp_ajax_get_items_by_room', function() {
            check_ajax_referer('space_estimator_nonce', 'nonce');
            $room_id = intval($_POST['room_id'] ?? 0);
            $plugin = new Space_Estimator_Calculator();
            $items = $plugin->get_items_by_room($room_id);
            $symbol = get_option('space_estimator_currency', 'm³');
            ob_start();
            if (empty($items)) {
                echo '<p>' . __('No items for selected room. Please add items in admin.', 'space-estimator') . '</p>';
            } else {
                foreach ($items as $item) {
                    $space_value = get_post_meta($item->ID, '_space_value', true);
                    $image_id = get_post_meta($item->ID, '_image_id', true);
                    $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : '';
                    ?>
                    <div class="item-row" data-item-id="<?php echo esc_attr($item->ID); ?>">
                        <div class="item-image">
                            <?php if ($image_url) : ?>
                                <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($item->post_title); ?>" />
                            <?php endif; ?>
                        </div>
                        <div class="item-name"><?php echo esc_html($item->post_title); ?> (<?php echo esc_html($space_value); ?>)</div>
                        <div class="item-quantity">
                            <button class="qty-minus">-</button>
                            <input type="number" class="qty-input" value="0" min="0" data-item="<?php echo esc_attr($item->ID); ?>">
                            <button class="qty-plus">+</button>
                        </div>
                    </div>
                    <?php
                }
            }
            $html = ob_get_clean();
            wp_send_json_success(['html' => $html]);
        });
        add_action('wp_ajax_nopriv_get_items_by_room', function() {
            check_ajax_referer('space_estimator_nonce', 'nonce');
            $room_id = intval($_POST['room_id'] ?? 0);
            $plugin = new Space_Estimator_Calculator();
            $items = $plugin->get_items_by_room($room_id);
            $symbol = get_option('space_estimator_currency', 'm³');
            ob_start();
            if (empty($items)) {
                echo '<p>' . __('No items for selected room. Please add items in admin.', 'space-estimator') . '</p>';
            } else {
                foreach ($items as $item) {
                    $space_value = get_post_meta($item->ID, '_space_value', true);
                    $image_id = get_post_meta($item->ID, '_image_id', true);
                    $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : '';
                    ?>
                    <div class="item-row" data-item-id="<?php echo esc_attr($item->ID); ?>">
                        <div class="item-image">
                            <?php if ($image_url) : ?>
                                <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($item->post_title); ?>" />
                            <?php endif; ?>
                        </div>
                        <div class="item-name"><?php echo esc_html($item->post_title); ?> (<?php echo esc_html($space_value); ?>)</div>
                        <div class="item-quantity">
                            <button class="qty-minus">-</button>
                            <input type="number" class="qty-input" value="0" min="0" data-item="<?php echo esc_attr($item->ID); ?>">
                            <button class="qty-plus">+</button>
                        </div>
                    </div>
                    <?php
                }
            }
            $html = ob_get_clean();
            wp_send_json_success(['html' => $html]);
        });
        add_shortcode( 'space_estimator', array( $this, 'shortcode' ) );
    }

    public function init() {
        $this->register_post_types();
    }

    public function load_textdomain() {
        load_plugin_textdomain( 'space-estimator', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }

    public function enqueue_scripts() {
        wp_enqueue_style( 'space-estimator-style', SPACE_ESTIMATOR_PLUGIN_URL . 'assets/css/style.css', array(), SPACE_ESTIMATOR_VERSION );
        wp_enqueue_script( 'space-estimator-script', SPACE_ESTIMATOR_PLUGIN_URL . 'assets/js/script.js', array( 'jquery' ), SPACE_ESTIMATOR_VERSION, true );
        wp_localize_script( 'space-estimator-script', 'spaceEstimator', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'space_estimator_nonce' ),
        ) );
    }

    public function admin_enqueue_scripts( $hook ) {
        if ( strpos( $hook, 'space-estimator' ) !== false || in_array( $hook, array( 'post.php', 'post-new.php' ) ) ) {
            wp_enqueue_media();
            wp_enqueue_style( 'space-estimator-admin-style', SPACE_ESTIMATOR_PLUGIN_URL . 'assets/css/admin.css', array(), SPACE_ESTIMATOR_VERSION );
            wp_enqueue_script( 'space-estimator-admin-script', SPACE_ESTIMATOR_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery', 'media-upload' ), SPACE_ESTIMATOR_VERSION, true );
            wp_localize_script( 'space-estimator-admin-script', 'spaceEstimatorAdmin', array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'space_estimator_admin_nonce' ),
                'rooms'    => $this->get_rooms(),
            ) );
        }
    }

    private function register_post_types() {
        register_post_type( 'room', array(
            'labels' => array(
                'name'          => __( 'Rooms', 'space-estimator' ),
                'singular_name' => __( 'Room', 'space-estimator' ),
                'add_new'       => __( 'Add New Room', 'space-estimator' ),
                'add_new_item'  => __( 'Add New Room', 'space-estimator' ),
                'edit_item'     => __( 'Edit Room', 'space-estimator' ),
                'new_item'      => __( 'New Room', 'space-estimator' ),
                'view_item'     => __( 'View Room', 'space-estimator' ),
                'search_items'  => __( 'Search Rooms', 'space-estimator' ),
                'not_found'     => __( 'No rooms found', 'space-estimator' ),
                'not_found_in_trash' => __( 'No rooms found in trash', 'space-estimator' ),
            ),
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => false,
            'query_var'           => false,
            'rewrite'             => false,
            'capability_type'     => 'post',
            'has_archive'         => false,
            'hierarchical'        => false,
            'supports'            => array( 'title' ),
            'menu_position'       => 20,
        ) );

        register_post_type( 'item', array(
            'labels' => array(
                'name'          => __( 'Items', 'space-estimator' ),
                'singular_name' => __( 'Item', 'space-estimator' ),
                'add_new'       => __( 'Add New Item', 'space-estimator' ),
                'add_new_item'  => __( 'Add New Item', 'space-estimator' ),
                'edit_item'     => __( 'Edit Item', 'space-estimator' ),
                'new_item'      => __( 'New Item', 'space-estimator' ),
                'view_item'     => __( 'View Item', 'space-estimator' ),
                'search_items'  => __( 'Search Items', 'space-estimator' ),
                'not_found'     => __( 'No items found', 'space-estimator' ),
                'not_found_in_trash' => __( 'No items found in trash', 'space-estimator' ),
            ),
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => false,
            'query_var'           => false,
            'rewrite'             => false,
            'capability_type'     => 'post',
            'has_archive'         => false,
            'hierarchical'        => false,
            'supports'            => array( 'title' ),
            'menu_position'       => 21,
        ) );
    }

    public function admin_menu() {
        add_menu_page(
            __( 'Space Estimator', 'space-estimator' ),
            __( 'Space Estimator', 'space-estimator' ),
            'manage_options',
            'space-estimator',
            array( $this, 'admin_page' ),
            'dashicons-calculator',
            25
        );

        add_submenu_page(
            'space-estimator',
            __( 'Rooms', 'space-estimator' ),
            __( 'Rooms', 'space-estimator' ),
            'manage_options',
            'edit.php?post_type=room'
        );

        add_submenu_page(
            'space-estimator',
            __( 'Items', 'space-estimator' ),
            __( 'Items', 'space-estimator' ),
            'manage_options',
            'edit.php?post_type=item'
        );

        add_submenu_page(
            'space-estimator',
            __( 'Settings', 'space-estimator' ),
            __( 'Settings', 'space-estimator' ),
            'manage_options',
            'space-estimator-settings',
            array( $this, 'settings_page' )
        );
    }

    public function admin_page() {
        ?>
        <div class="wrap">
            <h1><?php _e( 'Space Estimator Dashboard', 'space-estimator' ); ?></h1>
            <p><?php _e( 'Manage rooms and items here. Use the shortcode [space_estimator] to embed the calculator.', 'space-estimator' ); ?></p>
            <a href="<?php echo admin_url( 'post-new.php?post_type=room' ); ?>" class="button button-primary"><?php _e( 'Add New Room', 'space-estimator' ); ?></a>
            <a href="<?php echo admin_url( 'post-new.php?post_type=item' ); ?>" class="button"><?php _e( 'Add New Item', 'space-estimator' ); ?></a>
        </div>
        <?php
    }

    public function settings_page() {
        if ( isset( $_POST['submit'] ) && wp_verify_nonce( $_POST['space_estimator_settings_nonce'], 'space_estimator_settings' ) ) {
            update_option( 'space_estimator_currency', sanitize_text_field( $_POST['currency'] ) );
            update_option( 'space_estimator_unit', sanitize_text_field( $_POST['unit'] ) );
            echo '<div class="notice notice-success"><p>' . __( 'Settings saved.', 'space-estimator' ) . '</p></div>';
        }
        $currency = get_option( 'space_estimator_currency', 'm³' );
        $unit = get_option( 'space_estimator_unit', 'Cubic Meters' );
        ?>
        <div class="wrap">
            <h1><?php _e( 'Settings', 'space-estimator' ); ?></h1>
            <form method="post">
                <?php wp_nonce_field( 'space_estimator_settings', 'space_estimator_settings_nonce' ); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="unit"><?php _e( 'Unit Label', 'space-estimator' ); ?></label></th>
                        <td><input type="text" id="unit" name="unit" value="<?php echo esc_attr( $unit ); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th><label for="currency"><?php _e( 'Unit Symbol', 'space-estimator' ); ?></label></th>
                        <td><input type="text" id="currency" name="currency" value="<?php echo esc_attr( $currency ); ?>" class="regular-text" /></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function add_meta_boxes() {
        add_meta_box(
            'room_meta',
            __( 'Room Details', 'space-estimator' ),
            array( $this, 'room_meta_callback' ),
            'room',
            'normal',
            'high'
        );

        add_meta_box(
            'item_meta',
            __( 'Item Details', 'space-estimator' ),
            array( $this, 'item_meta_callback' ),
            'item',
            'normal',
            'high'
        );
    }

    public function room_meta_callback( $post ) {
        wp_nonce_field( 'space_estimator_room_meta', 'space_estimator_room_nonce' );
        $default_space = get_post_meta( $post->ID, '_default_space', true );
        ?>
        <p>
            <label for="default_space"><?php _e( 'Default Space (m³)', 'space-estimator' ); ?></label><br>
            <input type="number" id="default_space" name="default_space" value="<?php echo esc_attr( $default_space ); ?>" step="0.01" class="regular-text" />
        </p>
        <?php
    }

    public function item_meta_callback( $post ) {
        wp_nonce_field( 'space_estimator_item_meta', 'space_estimator_item_nonce' );
        $space_value = get_post_meta( $post->ID, '_space_value', true );
        $room_id = get_post_meta( $post->ID, '_room_id', true );
        $image_id = get_post_meta( $post->ID, '_image_id', true );
        $image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '';
        $rooms = $this->get_rooms();
        ?>
        <p>
            <label for="space_value"><?php _e( 'Space Value (m³)', 'space-estimator' ); ?></label><br>
            <input type="number" id="space_value" name="space_value" value="<?php echo esc_attr( $space_value ); ?>" step="0.01" class="regular-text" required />
        </p>
        <p>
            <label for="room_id"><?php _e( 'Assign to Room', 'space-estimator' ); ?></label><br>
            <select id="room_id" name="room_id" class="regular-text" required>
                <option value=""><?php _e( 'Select a Room', 'space-estimator' ); ?></option>
                <?php foreach ( $rooms as $room ) : ?>
                    <option value="<?php echo esc_attr( $room->ID ); ?>" <?php selected( $room_id, $room->ID ); ?>><?php echo esc_html( $room->post_title ); ?></option>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <label for="image_id"><?php _e( 'Item Image', 'space-estimator' ); ?></label><br>
            <div class="image-preview">
                <?php if ( $image_url ) : ?>
                    <img src="<?php echo esc_url( $image_url ); ?>" style="max-width: 100px;" />
                <?php endif; ?>
            </div>
            <input type="hidden" id="image_id" name="image_id" value="<?php echo esc_attr( $image_id ); ?>" />
            <button type="button" class="button upload-image"><?php _e( 'Upload Image', 'space-estimator' ); ?></button>
            <button type="button" class="button remove-image" style="display: <?php echo $image_url ? 'inline' : 'none'; ?>;"><?php _e( 'Remove Image', 'space-estimator' ); ?></button>
        </p>
        <?php
    }

    public function save_meta( $post_id ) {
        // Fix: Use OR instead of AND for nonce check
        if ( ! isset( $_POST['space_estimator_item_nonce'] ) && ! isset( $_POST['space_estimator_room_nonce'] ) ) {
            return;
        }

        if ( isset( $_POST['space_estimator_room_nonce'] ) && ! wp_verify_nonce( $_POST['space_estimator_room_nonce'], 'space_estimator_room_meta' ) ) {
            return;
        }

        if ( isset( $_POST['space_estimator_item_nonce'] ) && ! wp_verify_nonce( $_POST['space_estimator_item_nonce'], 'space_estimator_item_meta' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        if ( get_post_type( $post_id ) === 'room' ) {
            update_post_meta( $post_id, '_default_space', sanitize_text_field( $_POST['default_space'] ?? 0 ) );
        }

        if ( get_post_type( $post_id ) === 'item' ) {
            update_post_meta( $post_id, '_space_value', floatval( $_POST['space_value'] ?? 0 ) );
            update_post_meta( $post_id, '_room_id', intval( $_POST['room_id'] ?? 0 ) );
            update_post_meta( $post_id, '_image_id', intval( $_POST['image_id'] ?? 0 ) );
        }
    }

    public function ajax_delete_item() {
        check_ajax_referer( 'space_estimator_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'delete_post' ) ) {
            // Fix: Use wp_send_json_error instead of wp_die
            wp_send_json_error( __( 'Unauthorized', 'space-estimator' ) );
        }

        $post_id = intval( $_POST['post_id'] ?? 0 );
        if ( $post_id && get_post_type( $post_id ) === 'item' ) {
            wp_delete_post( $post_id, true );
            wp_send_json_success( __( 'Item deleted.', 'space-estimator' ) );
        } else {
            wp_send_json_error( __( 'Invalid item.', 'space-estimator' ) );
        }
    }

    public function ajax_delete_room() {
        check_ajax_referer( 'space_estimator_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'delete_post' ) ) {
            // Fix: Use wp_send_json_error instead of wp_die
            wp_send_json_error( __( 'Unauthorized', 'space-estimator' ) );
        }

        $post_id = intval( $_POST['post_id'] ?? 0 );
        if ( $post_id && get_post_type( $post_id ) === 'room' ) {
            $items = get_posts( array(
                'post_type'   => 'item',
                'meta_query'  => array(
                    array(
                        'key'     => '_room_id',
                        'value'   => $post_id,
                        'compare' => '=',
                    ),
                ),
                'posts_per_page' => -1,
                'post_status'    => 'any',
            ) );
            foreach ( $items as $item ) {
                wp_delete_post( $item->ID, true );
            }
            wp_delete_post( $post_id, true );
            wp_send_json_success( __( 'Room and associated items deleted.', 'space-estimator' ) );
        } else {
            wp_send_json_error( __( 'Invalid room.', 'space-estimator' ) );
        }
    }

    private function get_rooms() {
        return get_posts( array(
            'post_type'      => 'room',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ) );
    }

    private function get_items_by_room( $room_id = 0 ) {
        $meta_query = array();
        if ( $room_id ) {
            $meta_query[] = array(
                'key'     => '_room_id',
                'value'   => $room_id,
                'compare' => '=',
            );
        }

        return get_posts( array(
            'post_type'      => 'item',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => $meta_query,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ) );
    }

    public function shortcode( $atts ) {
        $atts = shortcode_atts( array(
            'room' => 0,
        ), $atts );

        $rooms = $this->get_rooms();
        $selected_room = intval( $atts['room'] );
        $items = $selected_room ? $this->get_items_by_room( $selected_room ) : array();

        $unit = get_option( 'space_estimator_unit', 'Cubic Meters' );
        $symbol = get_option( 'space_estimator_currency', 'm³' );

        ob_start();
        ?>
        <div class="space-estimator">
            <div class="estimator-steps">
                <div class="step step-1">1. Select a room</div>
                <div class="step-arrow"></div>
                <div class="step step-2">2. Select items you need to store</div>
            </div>
            
            <div class="estimator-content">
                <div class="step step-1">
                    <div id="rooms">
                        <?php if ( empty( $rooms ) ) : ?>
                            <p><?php _e( 'No rooms available. Please add rooms in admin.', 'space-estimator' ); ?></p>
                        <?php else : ?>
                            <?php foreach ( $rooms as $room ) : 
                                $default_space = get_post_meta( $room->ID, '_default_space', true );
                            ?>
                                <div class="room <?php echo $selected_room === $room->ID ? 'selected' : ''; ?>" data-room-id="<?php echo esc_attr( $room->ID ); ?>">
                                    <?php echo esc_html( $room->post_title ); ?> <span class="room-space"><?php echo esc_html( $default_space ); ?> <?php echo esc_html( $symbol ); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="step step-2">
                    <div id="items">
                        <?php if ( empty( $items ) ) : ?>
                            <p><?php _e( 'No items for selected room. Please add items in admin.', 'space-estimator' ); ?></p>
                        <?php else : ?>
                            <?php foreach ( $items as $item ) : 
                                $space_value = get_post_meta( $item->ID, '_space_value', true );
                                $image_id = get_post_meta( $item->ID, '_image_id', true );
                                $image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '';
                            ?>
                                <div class="item-row" data-item-id="<?php echo esc_attr( $item->ID ); ?>">
                                    <div class="item-image">
                                        <?php if ( $image_url ) : ?>
                                            <img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $item->post_title ); ?>" />
                                        <?php endif; ?>
                                    </div>
                                    <div class="item-name"><?php echo esc_html( $item->post_title ); ?> (<?php echo esc_html( $space_value ); ?>)</div>
                                    <div class="item-quantity">
                                        <button class="qty-minus">-</button>
                                        <input type="number" class="qty-input" value="0" min="0" data-item="<?php echo esc_attr( $item->ID ); ?>">
                                        <button class="qty-plus">+</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="total-space">
                <?php _e( 'You will need', 'space-estimator' ); ?> <span id="total-space"><?php echo esc_html( $selected_room ? get_post_meta( $selected_room, '_default_space', true ) : 0 ); ?></span> <?php echo esc_html( $symbol ); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    // Make activate static and fix get_posts by title
    public static function activate() {
        if ( ! get_posts( array( 'post_type' => 'room', 'numberposts' => 1 ) ) ) {
            $rooms = array(
                array( 'title' => 'Family', 'default_space' => 0 ),
                array( 'title' => 'Dining', 'default_space' => 0 ),
                array( 'title' => 'Lounge', 'default_space' => 3.25 ),
                array( 'title' => 'Laundry', 'default_space' => 0 ),
                array( 'title' => 'Sundries', 'default_space' => 0 ),
                array( 'title' => 'Outside', 'default_space' => 0 ),
                array( 'title' => 'Bedroom', 'default_space' => 0 ),
                array( 'title' => 'Kitchen', 'default_space' => 0 ),
                array( 'title' => 'Hall', 'default_space' => 0 ),
            );

            foreach ( $rooms as $room_data ) {
                $room_id = wp_insert_post( array(
                    'post_title'   => $room_data['title'],
                    'post_type'    => 'room',
                    'post_status'  => 'publish',
                ) );
                update_post_meta( $room_id, '_default_space', $room_data['default_space'] );
            }
        }

        if ( ! get_posts( array( 'post_type' => 'item', 'numberposts' => 1 ) ) ) {
            // Fix: get_posts does not support 'title', so fetch all and filter
            $lounge = get_posts( array(
                'post_type' => 'room',
                'posts_per_page' => -1,
                'post_status' => 'publish',
            ) );
            $lounge_id = 0;
            foreach ( $lounge as $room ) {
                if ( strtolower( $room->post_title ) === 'lounge' ) {
                    $lounge_id = $room->ID;
                    break;
                }
            }
            $items = array(
                array( 'title' => 'Bar', 'space' => 1.00, 'room_id' => $lounge_id ),
                array( 'title' => 'Bookshelf', 'space' => 0.50, 'room_id' => $lounge_id ),
                array( 'title' => 'Bureau', 'space' => 0.60, 'room_id' => $lounge_id ),
                array( 'title' => 'Chair Arm', 'space' => 0.80, 'room_id' => $lounge_id ),
                array( 'title' => 'Chair Other', 'space' => 0.30, 'room_id' => $lounge_id ),
                array( 'title' => 'China Cabinet', 'space' => 0.70, 'room_id' => $lounge_id ),
                array( 'title' => 'Coffee Table', 'space' => 0.20, 'room_id' => $lounge_id ),
                array( 'title' => 'Desk', 'space' => 0.85, 'room_id' => $lounge_id ),
                array( 'title' => 'Divan', 'space' => 1.20, 'room_id' => $lounge_id ),
                array( 'title' => 'Heater', 'space' => 1.00, 'room_id' => $lounge_id ),
                array( 'title' => 'Lampshade', 'space' => 0.20, 'room_id' => $lounge_id ),
                array( 'title' => 'Organ', 'space' => 2.00, 'room_id' => $lounge_id ),
                array( 'title' => 'Piano', 'space' => 2.00, 'room_id' => $lounge_id ),
                array( 'title' => 'Stereo', 'space' => 0.40, 'room_id' => $lounge_id ),
                array( 'title' => 'TV', 'space' => 0.40, 'room_id' => $lounge_id ),
                array( 'title' => 'Video Recorder', 'space' => 0.10, 'room_id' => $lounge_id ),
                array( 'title' => 'Wall Unit', 'space' => 1.00, 'room_id' => $lounge_id ),
                array( 'title' => 'Standard Carton', 'space' => 0.15, 'room_id' => $lounge_id ),
                array( 'title' => 'Book & Wine Carton', 'space' => 0.10, 'room_id' => $lounge_id ),
            );

            foreach ( $items as $item_data ) {
                $item_id = wp_insert_post( array(
                    'post_title'   => $item_data['title'],
                    'post_type'    => 'item',
                    'post_status'  => 'publish',
                ) );
                update_post_meta( $item_id, '_space_value', $item_data['space'] );
                update_post_meta( $item_id, '_room_id', $item_data['room_id'] );
                // Images not seeded; admin can add.
            }
        }

        flush_rewrite_rules();
    }
}

// Instantiate the class
$space_estimator_calculator = new Space_Estimator_Calculator();

// Register activation hook outside the class, using static method
register_activation_hook( __FILE__, array( 'Space_Estimator_Calculator', 'activate' ) );