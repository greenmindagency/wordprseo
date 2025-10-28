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

if ( ! function_exists( 'wordprseo_render_header_customer_tools' ) ) {
    /**
     * Renders the account/cart tools that live in the site header.
     *
     * @return string
     */
    function wordprseo_render_header_customer_tools() {
        if ( ! wordprseo_is_woocommerce_active() ) {
            return '';
        }

        if ( ! function_exists( 'wc_get_cart_url' ) ) {
            return '';
        }

        $cart = WC()->cart;

        if ( null === $cart && function_exists( 'wc_load_cart' ) ) {
            wc_load_cart();
            $cart = WC()->cart;
        }

        if ( ! $cart ) {
            return '';
        }

        $cart_count = max( 0, intval( $cart->get_cart_contents_count() ) );
        $cart_total = $cart->get_cart_subtotal();

        if ( empty( $cart_total ) ) {
            $cart_total = wc_price( 0 );
        }

        $cart_total = html_entity_decode( wp_strip_all_tags( $cart_total, true ), ENT_QUOTES, get_bloginfo( 'charset' ) );

        $account_page_id = get_option( 'woocommerce_myaccount_page_id' );
        $account_url     = $account_page_id ? get_permalink( $account_page_id ) : '';
        $account_label   = '';
        $is_logged_in    = is_user_logged_in();

        if ( $is_logged_in ) {
            $current_user = wp_get_current_user();
            $display_name = $current_user instanceof WP_User ? $current_user->display_name : '';

            if ( empty( $display_name ) && $current_user instanceof WP_User ) {
                $display_name = $current_user->user_login;
            }

            if ( ! empty( $display_name ) ) {
                $account_label = $display_name;
            }
        } elseif ( $account_url ) {
            $account_label = esc_html__( 'Sign in', 'woocommerce' );
        }

        ob_start();
        ?>
        <div class="woocommerce-header-tools d-flex align-items-center ms-3 bg-light px-2 ">
            <span class="woocommerce-header-account d-flex align-items-center text-dark small me-3">
                <i class="fa-regular fa-user me-2" aria-hidden="true"></i>
                <?php if ( $is_logged_in && ! empty( $account_label ) ) : ?>
                    <?php if ( $account_url ) : ?>
                        <a class="text-decoration-none text-dark fw-semibold" href="<?php echo esc_url( $account_url ); ?>"><?php echo esc_html( $account_label ); ?></a>
                    <?php else : ?>
                        <span class="fw-semibold"><?php echo esc_html( $account_label ); ?></span>
                    <?php endif; ?>
                <?php elseif ( $account_url && ! empty( $account_label ) ) : ?>
                    <a class="text-decoration-none text-dark" href="<?php echo esc_url( $account_url ); ?>"><?php echo esc_html( $account_label ); ?></a>
                <?php endif; ?>
            </span>
            <a class="woocommerce-header-cart btn btn-warning text-dark fw-semibold py-1 px-3 d-flex align-items-center shadow-none" href="<?php echo esc_url( wc_get_cart_url() ); ?>">
                <i class="fa-solid fa-bag-shopping me-2" aria-hidden="true"></i>
                <span class="small"><?php echo esc_html( $cart_total ); ?></span>
                <span class="badge rounded-pill bg-light text-dark ms-2 border border-dark px-2 py-0 small">
                    <?php echo esc_html( (string) $cart_count ); ?>
                </span>
            </a>
        </div>
        <?php

        return trim( ob_get_clean() );
    }
}

if ( ! function_exists( 'wordprseo_header_customer_tools_fragment' ) ) {
    /**
     * Ensures the header customer tools stay in sync via WooCommerce fragments.
     *
     * @param array $fragments Existing AJAX fragments.
     *
     * @return array
     */
    function wordprseo_header_customer_tools_fragment( $fragments ) {
        if ( ! wordprseo_is_woocommerce_active() ) {
            return $fragments;
        }

        $markup = wordprseo_render_header_customer_tools();

        if ( ! empty( $markup ) ) {
            $fragments['.woocommerce-header-tools'] = $markup;
        }

        return $fragments;
    }
}

if ( wordprseo_is_woocommerce_active() ) {
    add_filter( 'woocommerce_add_to_cart_fragments', 'wordprseo_header_customer_tools_fragment' );
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

if ( ! function_exists( 'wordprseo_show_all_products_on_archives' ) ) {
    /**
     * Shows all products on shop and product taxonomy archives.
     *
     * @param \WP_Query $query Query instance.
     */
    function wordprseo_show_all_products_on_archives( $query ) {
        if ( ! $query instanceof \WP_Query ) {
            return;
        }

        if ( is_admin() || ! $query->is_main_query() ) {
            return;
        }

        if ( ! function_exists( 'is_shop' ) || ! function_exists( 'is_product_taxonomy' ) ) {
            return;
        }

        if ( ! is_shop() && ! is_product_taxonomy() ) {
            return;
        }

        $query->set( 'posts_per_page', -1 );
    }
}

if ( wordprseo_is_woocommerce_active() ) {
    add_action( 'after_setup_theme', 'wordprseo_register_woocommerce_support', 20 );
    add_action( 'woocommerce_before_main_content', 'wordprseo_woocommerce_before_main_content', 5 );
    add_action( 'woocommerce_after_main_content', 'wordprseo_woocommerce_after_main_content', 50 );
    add_action( 'admin_notices', 'wordprseo_maybe_display_woocommerce_notice' );
    add_filter( 'woocommerce_product_single_add_to_cart_html', 'wordprseo_bootstrap_single_add_to_cart_html', 10, 2 );
    add_action( 'pre_get_posts', 'wordprseo_show_all_products_on_archives', 20 );
    add_filter( 'render_block', 'wordprseo_wrap_cart_checkout_blocks', 10, 2 );

    // Remove the default WooCommerce catalog ordering dropdown from all archive/shop pages.
    // WooCommerce registers the catalog ordering markup via the 'woocommerce_catalog_ordering' function
    // on the 'woocommerce_before_shop_loop' hook (priority 30). Remove it after WooCommerce has initialized.
    add_action( 'init', function() {
        if ( function_exists( 'remove_action' ) ) {
            remove_action( 'woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30 );
        }
    }, 20 );

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

if ( ! function_exists( 'wordprseo_wrap_cart_checkout_blocks' ) ) {
    /**
     * Wraps the cart and checkout blocks with the theme container spacing classes.
     *
     * Ensures the block-based cart and checkout pages share the same layout spacing as
     * the classic templates by surrounding their rendered markup with the
     * `container py-spacer-2` wrapper used throughout the theme.
     *
     * @param string $block_content The block HTML.
     * @param array  $block         The block data.
     *
     * @return string
     */
    function wordprseo_wrap_cart_checkout_blocks( $block_content, $block ) {
        if ( is_admin() && ! wp_doing_ajax() ) {
            return $block_content;
        }

        if ( empty( $block['blockName'] ) ) {
            return $block_content;
        }

        $target_blocks = array(
            'woocommerce/cart',
            'woocommerce/checkout',
        );

        if ( ! in_array( $block['blockName'], $target_blocks, true ) ) {
            return $block_content;
        }

        $trimmed_content = trim( $block_content );

        if ( '' === $trimmed_content ) {
            return $block_content;
        }

        if ( false !== strpos( $trimmed_content, 'container py-spacer-2' ) ) {
            return $block_content;
        }

        return sprintf(
            '<div class="container py-spacer-2">%s</div>',
            $trimmed_content
        );
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
