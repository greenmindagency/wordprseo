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

        $product_id   = get_the_ID();
        $gallery_ids  = ( $product instanceof WC_Product ) ? $product->get_gallery_image_ids() : array();
        $hero_image   = 0;
        $image_size   = 'large';
        $hero_caption = $product instanceof WC_Product ? wp_strip_all_tags( $product->get_short_description() ) : '';

        if ( ! empty( $gallery_ids ) ) {
            $hero_image = isset( $gallery_ids[1] ) ? $gallery_ids[1] : $gallery_ids[0];
        }

        if ( ! $hero_image ) {
            $hero_image = get_post_thumbnail_id( $product_id );
        }

        $hero_image_data = $hero_image ? wp_get_attachment_image_src( $hero_image, $image_size ) : array( '', 0, 0 );
        $hero_image_url  = $hero_image_data ? ( isset( $hero_image_data[0] ) ? $hero_image_data[0] : '' ) : '';
        $hero_image_w    = $hero_image_data ? ( isset( $hero_image_data[1] ) ? (int) $hero_image_data[1] : 0 ) : 0;
        $hero_image_h    = $hero_image_data ? ( isset( $hero_image_data[2] ) ? (int) $hero_image_data[2] : 0 ) : 0;

        $product_cats = function_exists( 'wc_get_product_category_list' )
            ? wc_get_product_category_list( $product_id, ', ' )
            : ''; ?>

        <main id="primary" class="site-main">
            <article id="product-<?php the_ID(); ?>" <?php wc_product_class( 'single-product', get_the_ID() ); ?>>
                <div data-jarallax data-speed="0.2" class="bg-secondary jarallax">
                    <?php if ( $hero_image_url ) : ?>
                        <img
                            loading="lazy"
                            src="<?php echo esc_url( $hero_image_url ); ?>"
                            class="jarallax-img"
                            alt="<?php echo esc_attr( get_the_title( $product_id ) ); ?>"
                            width="<?php echo esc_attr( $hero_image_w ); ?>"
                            height="<?php echo esc_attr( $hero_image_h ); ?>"
                        >
                    <?php endif; ?>

                    <div class="container py-4">
                        <div class="row">
                            <div class="col-lg-8 py-4 text-white">
                                <?php if ( $product_cats ) : ?>
                                    <p class="text-uppercase small fw-semibold mb-3">
                                        <?php echo wp_kses_post( $product_cats ); ?>
                                    </p>
                                <?php endif; ?>

                                <h1 class="fw-bold display-5 mb-4"><?php the_title(); ?></h1>

                                <?php if ( $hero_caption ) : ?>
                                    <p class="lead mb-0"><?php echo esc_html( $hero_caption ); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ( function_exists( 'qt_should_display_breadcrumbs' ) && qt_should_display_breadcrumbs() ) : ?>
                    <div class="border-bottom container-fluid bg-light">
                        <div class="container">
                            <div class="row">
                                <div class="col">
                                    <nav class="my-3 d-none d-sm-none d-md-block" aria-label="breadcrumb">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <?php echo do_shortcode( '[custom_breadcrumbs]' ); ?>
                                            </div>

                                            <?php
                                            global $wp;
                                            $share_url   = '';
                                            $share_title = get_the_title();

                                            if ( isset( $wp ) && isset( $wp->request ) ) {
                                                $share_url = home_url( add_query_arg( array(), $wp->request ) );
                                            }
                                            ?>

                                            <div class="dropdown">
                                                <button class="btn rounded-0 p-2" type="button" id="shareDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                                    <i class="fas fs-5 fa-share-alt text-primary"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="shareDropdown">
                                                    <li>
                                                        <a class="dropdown-item d-flex align-items-center" href="mailto:?subject=<?php echo rawurlencode( $share_title ); ?>&amp;body=<?php echo rawurlencode( $share_url ); ?>">
                                                            <i class="fas fa-envelope me-2 text-primary"></i> Email
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item d-flex align-items-center" href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo rawurlencode( $share_url ); ?>" target="_blank" rel="noopener">
                                                            <i class="fab fa-linkedin me-2 text-primary"></i> LinkedIn
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item d-flex align-items-center" href="https://twitter.com/intent/tweet?url=<?php echo rawurlencode( $share_url ); ?>&amp;text=<?php echo rawurlencode( $share_title ); ?>" target="_blank" rel="noopener">
                                                            <i class="fab fa-x-twitter me-2 text-primary"></i> X
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item d-flex align-items-center" href="https://www.facebook.com/sharer/sharer.php?u=<?php echo rawurlencode( $share_url ); ?>" target="_blank" rel="noopener">
                                                            <i class="fab fa-facebook me-2 text-primary"></i> Facebook
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </nav>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <section class="product-overview py-5 bg-light">
                    <div class="container">
                        <div class="card shadow-sm border-0 overflow-hidden">
                            <div class="card-body p-4 p-lg-5">
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
                        </div>
                    </div>
                </section>

                <?php
                $flexible_source = get_queried_object();

                if ( function_exists( 'have_rows' ) && have_rows( 'body', $flexible_source ) ) :
                    ?>
                    <section class="product-flexible-content py-5">
                        <div class="container">
                            <article class="blog-post">
                                <?php
                                while ( have_rows( 'body', $flexible_source ) ) :
                                    the_row();
                                    include get_theme_file_path( '/flixable.php' );
                                endwhile;
                                ?>
                            </article>
                        </div>
                    </section>
                <?php endif; ?>

                <section class="product-secondary-sections py-5 bg-white">
                    <div class="container">
                        <?php
                        /**
                         * Hook: woocommerce_after_single_product_summary.
                         *
                         * @hooked woocommerce_output_product_data_tabs - 10
                         * @hooked woocommerce_upsell_display - 15
                         * @hooked woocommerce_output_related_products - 20
                         */
                        do_action( 'woocommerce_after_single_product_summary' );
                        ?>
                    </div>
                </section>
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
