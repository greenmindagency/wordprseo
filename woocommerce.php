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

if ( ! wordprseo_is_woocommerce_active() ) {
    get_template_part( 'template-parts/woocommerce/notice', 'plugin-inactive' );
} elseif ( ! wordprseo_is_woocommerce_ready() ) {
    get_template_part( 'template-parts/woocommerce/notice', 'setup-incomplete' );
} else {
    woocommerce_content();
}

get_footer();
