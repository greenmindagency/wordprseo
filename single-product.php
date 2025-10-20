<?php
/**
 * Template for displaying single WooCommerce products.
 *
 * @package WordPrSEO
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

get_header( 'shop' );

if ( function_exists( 'wc_get_template_part' ) ) {
    while ( have_posts() ) {
        the_post();
        wc_get_template_part( 'content', 'single-product' );
    }
} else {
    while ( have_posts() ) {
        the_post();
        the_content();
    }
}

get_footer( 'shop' );
