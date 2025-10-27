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

if ( ! function_exists( 'wordprseo_get_star_rating_html' ) ) {
    /**
     * Generates star rating markup using Font Awesome icons.
     *
     * @param float $average      Average rating between 0 and 5.
     * @param int   $rating_count Number of ratings recorded for the product.
     * @param array $args         Optional arguments for customizing the wrapper class.
     *
     * @return string
     */
    function wordprseo_get_star_rating_html( $average, $rating_count = 0, $args = array() ) {
        $average      = floatval( $average );
        $rating_count = intval( $rating_count );

        if ( $average <= 0 ) {
            return '';
        }

        $defaults = array(
            'class' => 'wordprseo-star-rating text-warning d-inline-flex align-items-center gap-1',
        );

        $args = wp_parse_args( $args, $defaults );

        $full_stars = (int) floor( $average );
        $remainder  = $average - $full_stars;
        $has_half   = $remainder >= 0.25 && $remainder < 0.75;

        if ( $remainder >= 0.75 ) {
            $full_stars++;
            $has_half = false;
        }

        if ( $full_stars > 5 ) {
            $full_stars = 5;
        }

        $empty_stars = max( 5 - $full_stars - ( $has_half ? 1 : 0 ), 0 );

        $icons = '';

        for ( $i = 0; $i < $full_stars; $i++ ) {
            $icons .= '<i class="fas fa-star" aria-hidden="true"></i>';
        }

        if ( $has_half ) {
            $icons .= '<i class="fas fa-star-half-alt" aria-hidden="true"></i>';
        }

        for ( $i = 0; $i < $empty_stars; $i++ ) {
            $icons .= '<i class="far fa-star" aria-hidden="true"></i>';
        }

        $aria_label = sprintf(
            /* translators: %s: average rating */
            esc_html__( 'Rated %s out of 5', 'woocommerce' ),
            esc_html( number_format_i18n( $average, 1 ) )
        );

        $display_count       = max( $rating_count, 1 );
        $screen_reader_text = sprintf(
            /* translators: 1: average rating, 2: number of ratings */
            esc_html(
                _n(
                    'Rated %1$s out of 5 based on %2$s customer rating',
                    'Rated %1$s out of 5 based on %2$s customer ratings',
                    $display_count,
                    'woocommerce'
                )
            ),
            esc_html( number_format_i18n( $average, 1 ) ),
            esc_html( number_format_i18n( $display_count ) )
        );

        return sprintf(
            '<span class="%1$s" role="img" aria-label="%2$s">%3$s<span class="visually-hidden">%4$s</span></span>',
            esc_attr( $args['class'] ),
            esc_attr( $aria_label ),
            $icons,
            esc_html( $screen_reader_text )
        );
    }
}

if ( wordprseo_is_woocommerce_active() ) {
    add_action( 'after_setup_theme', 'wordprseo_register_woocommerce_support', 20 );
    add_action( 'woocommerce_before_main_content', 'wordprseo_woocommerce_before_main_content', 5 );
    add_action( 'woocommerce_after_main_content', 'wordprseo_woocommerce_after_main_content', 50 );
    add_action( 'admin_notices', 'wordprseo_maybe_display_woocommerce_notice' );
    add_filter( 'woocommerce_product_single_add_to_cart_html', 'wordprseo_bootstrap_single_add_to_cart_html', 10, 2 );

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

if ( ! function_exists( 'wordprseo_bootstrap_single_add_to_cart_html' ) ) {
    /**
     * Replaces the default WooCommerce button classes with Bootstrap variants on single products.
     *
     * @param string      $html    The generated add to cart HTML.
     * @param WC_Product  $product The product object.
     *
     * @return string
     */
    function wordprseo_bootstrap_single_add_to_cart_html( $html, $product ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
        if ( false === strpos( $html, 'single_add_to_cart_button' ) ) {
            return $html;
        }

        $replacement_classes = 'single_add_to_cart_button btn btn-primary btn-lg w-100';
        $html                = preg_replace(
            '/single_add_to_cart_button\s+button\s+alt/',
            $replacement_classes,
            $html
        );

        if ( false === strpos( $html, 'btn btn-primary' ) ) {
            $html = str_replace( 'single_add_to_cart_button', $replacement_classes, $html );
        }

        return $html;
    }
}

if ( ! function_exists( 'wordprseo_woocommerce_before_main_content' ) ) {
    /**
     * Opens the theme wrapper around WooCommerce content.
     */
    function wordprseo_woocommerce_before_main_content() {
        if (
            ( function_exists( 'is_product' ) && is_product() )
            || ( function_exists( 'is_shop' ) && is_shop() )
            || ( function_exists( 'is_product_taxonomy' ) && is_product_taxonomy() )
        ) {
            return;
        }

        echo '<div class="container py-4 py-spacer-2"><div class="row"><div class="col-12">';
    }
}

if ( ! function_exists( 'wordprseo_woocommerce_after_main_content' ) ) {
    /**
     * Closes the theme wrapper around WooCommerce content.
     */
    function wordprseo_woocommerce_after_main_content() {
        if (
            ( function_exists( 'is_product' ) && is_product() )
            || ( function_exists( 'is_shop' ) && is_shop() )
            || ( function_exists( 'is_product_taxonomy' ) && is_product_taxonomy() )
        ) {
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
