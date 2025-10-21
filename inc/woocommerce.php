<?php
/**
 * WooCommerce integration helpers for the WordPrSEO theme.
 *
 * @package WordPrSEO
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if ( ! function_exists( 'wordprseo_is_woocommerce_active' ) ) {
    /**
     * Checks whether the WooCommerce plugin is active.
     *
     * @return bool
     */
    function wordprseo_is_woocommerce_active() {
        return class_exists( 'WooCommerce' );
    }
}

if ( ! function_exists( 'wordprseo_flush_woocommerce_setup_cache' ) ) {
    /**
     * Clears the cached WooCommerce setup status.
     */
    function wordprseo_flush_woocommerce_setup_cache( ...$unused ) {
        delete_transient( 'wordprseo_wc_setup_status' );
    }
}

if ( ! function_exists( 'wordprseo_get_woocommerce_setup_status' ) ) {
    /**
     * Retrieves the cached WooCommerce setup status, recalculating when required.
     *
     * @return array{
     *     active: bool,
     *     has_products: bool,
     *     has_categories: bool
     * }
     */
    function wordprseo_get_woocommerce_setup_status() {
        $status = get_transient( 'wordprseo_wc_setup_status' );

        if ( false === $status ) {
            $status = array(
                'active'         => wordprseo_is_woocommerce_active(),
                'has_products'   => false,
                'has_categories' => false,
            );

            if ( $status['active'] ) {
                if ( function_exists( 'wc_get_products' ) ) {
                    $products = wc_get_products(
                        array(
                            'limit'  => 1,
                            'status' => 'publish',
                        )
                    );

                    $status['has_products'] = ! empty( $products );
                }

                $categories = get_terms(
                    array(
                        'taxonomy'   => 'product_cat',
                        'hide_empty' => false,
                        'number'     => 1,
                        'fields'     => 'ids',
                    )
                );

                if ( ! is_wp_error( $categories ) ) {
                    $status['has_categories'] = ! empty( $categories );
                }
            }

            set_transient( 'wordprseo_wc_setup_status', $status, HOUR_IN_SECONDS );
        }

        return $status;
    }
}

if ( ! function_exists( 'wordprseo_is_woocommerce_ready' ) ) {
    /**
     * Determines whether the WooCommerce store has the required initial data.
     *
     * @return bool
     */
    function wordprseo_is_woocommerce_ready() {
        $status = wordprseo_get_woocommerce_setup_status();

        return ( $status['active'] && $status['has_products'] && $status['has_categories'] );
    }
}

if ( wordprseo_is_woocommerce_active() ) {
    add_action( 'after_setup_theme', 'wordprseo_register_woocommerce_support', 20 );
    add_action( 'woocommerce_before_main_content', 'wordprseo_woocommerce_before_main_content', 5 );
    add_action( 'woocommerce_after_main_content', 'wordprseo_woocommerce_after_main_content', 50 );
    add_action( 'admin_notices', 'wordprseo_maybe_display_woocommerce_notice' );

    // Flush cached setup data when products or categories change.
    add_action( 'save_post_product', 'wordprseo_flush_woocommerce_setup_cache' );
    add_action( 'untrashed_post_product', 'wordprseo_flush_woocommerce_setup_cache' );
    add_action( 'created_product_cat', 'wordprseo_flush_woocommerce_setup_cache' );
    add_action( 'edited_product_cat', 'wordprseo_flush_woocommerce_setup_cache' );
    add_action( 'delete_product_cat', 'wordprseo_flush_woocommerce_setup_cache' );
    add_action( 'deleted_post', 'wordprseo_maybe_flush_cache_for_deleted_post' );
}

if ( ! function_exists( 'wordprseo_maybe_flush_cache_for_deleted_post' ) ) {
    /**
     * Flushes cached setup data when a product is permanently deleted.
     *
     * @param int $post_id The deleted post ID.
     */
    function wordprseo_maybe_flush_cache_for_deleted_post( $post_id ) {
        if ( 'product' !== get_post_type( $post_id ) ) {
            return;
        }

        wordprseo_flush_woocommerce_setup_cache();
    }
}

if ( ! function_exists( 'wordprseo_register_woocommerce_support' ) ) {
    /**
     * Registers WooCommerce theme support features.
     */
    function wordprseo_register_woocommerce_support() {
        add_theme_support( 'woocommerce' );
        add_theme_support( 'wc-product-gallery-zoom' );
        add_theme_support( 'wc-product-gallery-lightbox' );
        add_theme_support( 'wc-product-gallery-slider' );
    }
}

if ( ! function_exists( 'wordprseo_woocommerce_before_main_content' ) ) {
    /**
     * Opens the theme wrapper around WooCommerce content.
     */
    function wordprseo_woocommerce_before_main_content() {
        if ( function_exists( 'is_product' ) && is_product() ) {
            return;
        }

        echo '<div class="container py-spacer"><div class="row"><div class="col-12">';
    }
}

if ( ! function_exists( 'wordprseo_woocommerce_after_main_content' ) ) {
    /**
     * Closes the theme wrapper around WooCommerce content.
     */
    function wordprseo_woocommerce_after_main_content() {
        if ( function_exists( 'is_product' ) && is_product() ) {
            return;
        }

        echo '</div></div></div>';
    }
}

if ( ! function_exists( 'wordprseo_maybe_display_woocommerce_notice' ) ) {
    /**
     * Displays an admin notice when WooCommerce is active but the store is not ready.
     */
    function wordprseo_maybe_display_woocommerce_notice() {
        if ( ! is_admin() || ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

        if ( $screen && isset( $screen->id ) && false === strpos( $screen->id, 'woocommerce' ) ) {
            // Only show the notice on WooCommerce and product screens to avoid noise.
            return;
        }

        if ( wordprseo_is_woocommerce_ready() ) {
            return;
        }

        echo '<div class="notice notice-warning"><p>' . wp_kses_post( __( 'WooCommerce is active, but your store setup is not finished yet. Please add at least one published product and one product category before the WordPrSEO theme displays the storefront.', 'wordprseo' ) ) . '</p></div>';
    }
}
