<?php
/**
 * Template for displaying single WooCommerce products.
 *
 * @package WordPrSEO
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

get_header();

if ( have_posts() ) :
    while ( have_posts() ) :
        the_post();

        global $product;

        /**
         * Hook: woocommerce_before_single_product.
         */
        do_action( 'woocommerce_before_single_product' );

        if ( post_password_required() ) {
            echo get_the_password_form();
            continue;
        }

        $product_id = get_the_ID();
        ?>

        <main id="primary" class="site-main">
            <article id="product-<?php the_ID(); ?>" <?php wc_product_class( 'single-product', get_the_ID() ); ?>>
                <div class="container py-5">
                    <div class="row g-5 align-items-start">
                        <div class="col-lg-6">
                            <?php
                            /**
                             * Hook: woocommerce_before_single_product_summary.
                             *
                             * @hooked woocommerce_show_product_sale_flash - 10
                             * @hooked woocommerce_show_product_images - 20
                             */
                            do_action( 'woocommerce_before_single_product_summary' );
                            ?>
                        </div>

                        <div class="col-lg-6">
                            <div class="summary entry-summary">
                                <?php
                                /**
                                 * Hook: woocommerce_single_product_summary.
                                 *
                                 * @hooked woocommerce_template_single_title - 5
                                 * @hooked woocommerce_template_single_rating - 10
                                 * @hooked woocommerce_template_single_price - 10
                                 * @hooked woocommerce_template_single_excerpt - 20
                                 * @hooked woocommerce_template_single_add_to_cart - 30
                                 * @hooked woocommerce_template_single_meta - 40
                                 * @hooked woocommerce_template_single_sharing - 50
                                 */
                                do_action( 'woocommerce_single_product_summary' );
                                ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ( function_exists( 'have_rows' ) && have_rows( 'body', $product_id ) ) : ?>
                    <div class="product-flexible-content py-5">
                        <article class="blog-post">
                            <?php
                            while ( have_rows( 'body', $product_id ) ) :
                                the_row();
                                include get_theme_file_path( '/flixable.php' );
                            endwhile;
                            ?>
                        </article>
                    </div>
                <?php endif; ?>
            </article>
        </main>

        <?php
        /**
         * Hook: woocommerce_after_single_product.
         */
        do_action( 'woocommerce_after_single_product' );

    endwhile;
endif;

get_footer();
