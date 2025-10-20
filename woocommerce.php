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
    $rendered_flexible = false;

    if ( function_exists( 'wc_get_page_id' ) ) {
        $shop_id = wc_get_page_id( 'shop' );
    } else {
        $shop_id = 0;
    }

    if ( $shop_id && $shop_id > 0 && function_exists( 'have_rows' ) && have_rows( 'body', $shop_id ) ) {
        global $post;

        $previous_post = isset( $post ) ? $post : null;
        $shop_post     = get_post( $shop_id );

        if ( $shop_post instanceof WP_Post ) {
            $post = $shop_post;
            setup_postdata( $post );
        }

        $term = $shop_post instanceof WP_Post ? $shop_post : $shop_id;

        echo '<article class="blog-post">';

        while ( have_rows( 'body', $shop_id ) ) {
            the_row();
            include get_theme_file_path( '/flixable.php' );
        }

        echo '</article>';

        if ( $shop_post instanceof WP_Post ) {
            wp_reset_postdata();
            if ( $previous_post instanceof WP_Post ) {
                $post = $previous_post;
                setup_postdata( $post );
            }
        }

        $rendered_flexible = true;
    }

    if ( ! $rendered_flexible ) {
        woocommerce_content();
    }
}

get_footer();
