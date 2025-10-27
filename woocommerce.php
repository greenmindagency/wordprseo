<?php
/**
 * Default WooCommerce template wrapper for the WordPrSEO theme.
 *
 * @package WordPrSEO
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

get_header();

$is_product        = function_exists( 'is_product' ) && is_product();
$is_shop_archive   = function_exists( 'is_shop' ) && is_shop();
$is_product_term   = function_exists( 'is_product_taxonomy' ) && is_product_taxonomy();
$should_wrap_outer = ! $is_product && ( $is_shop_archive || $is_product_term );

echo '<main id="primary" class="site-main">';

if ( ! wordprseo_is_woocommerce_active() ) {
    get_template_part( 'template-parts/woocommerce/notice', 'plugin-inactive' );
} elseif ( ! wordprseo_is_woocommerce_ready() && $is_shop_archive ) {
    get_template_part( 'template-parts/woocommerce/notice', 'setup-incomplete' );
} else {
    if ( $should_wrap_outer ) {
        echo '<div class="container py-4 py-md-5">';
    }

    woocommerce_content();

    if ( $should_wrap_outer ) {
        echo '</div>';
    }
}

echo '</main>';

get_footer();
