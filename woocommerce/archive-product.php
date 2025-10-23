<?php
/**
 * Custom archive template that mirrors the "postsrelatedproducts" flexible layout
 * for the shop page and product category archives.
 *
 * @package WordPrSEO
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if ( function_exists( 'woocommerce_output_all_notices' ) ) {
    woocommerce_output_all_notices();
}

$columns    = max( 1, (int) apply_filters( 'wordprseo_shop_card_columns', 3 ) );
$imagesize  = apply_filters( 'wordprseo_shop_card_image_size', 'medium_large' );
$sameheight = (bool) apply_filters( 'wordprseo_shop_card_equal_height', true );

$column_width = (int) floor( 12 / $columns );
if ( $column_width < 1 ) {
    $column_width = 1;
}

$archive_title = function_exists( 'woocommerce_page_title' ) ? woocommerce_page_title( false ) : post_type_archive_title( '', false );

ob_start();
do_action( 'woocommerce_archive_description' );
$archive_description = trim( ob_get_clean() );

$row_classes = 'row mt-3';
$row_attrs   = '';

if ( $sameheight ) {
    $row_classes .= ' align-items-stretch';
} else {
    $row_attrs = ' data-masonry=\'{"percentPosition": true }\'';
}

?>
<section class="postsrelatedcat container-fluid bg-light">
    <div class="py-spacer container">
        <div class="text-center">
            <?php if ( $archive_title ) : ?>
                <h1 class="fs-1 fw-bold"><?php echo esc_html( $archive_title ); ?></h1>
            <?php endif; ?>

            <?php if ( $archive_description ) : ?>
                <div class="mt-4 mb-5 lead">
                    <?php echo wp_kses_post( $archive_description ); ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="d-flex justify-content-end mb-4">
            <?php if ( function_exists( 'woocommerce_catalog_ordering' ) ) : ?>
                <?php woocommerce_catalog_ordering(); ?>
            <?php endif; ?>
        </div>

        <?php if ( woocommerce_product_loop() ) : ?>
            <div class="row">
                <div class="col-12">
                    <div class="<?php echo esc_attr( $row_classes ); ?>"<?php echo $row_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
                        <?php
                        while ( have_posts() ) :
                            the_post();

                            global $product;
                            $product = wc_get_product( get_the_ID() );

                            if ( ! $product ) {
                                continue;
                            }

                            $image_id = $product->get_image_id();

                            if ( ! $image_id ) {
                                $gallery_image_ids = $product->get_gallery_image_ids();

                                if ( ! empty( $gallery_image_ids ) ) {
                                    $image_id = (int) reset( $gallery_image_ids );
                                }
                            }

                            $image = $image_id ? wp_get_attachment_image_src( $image_id, $imagesize ) : false;

                            $cta_classes = $sameheight ? 'mt-auto pt-3' : 'mt-3';
                            ?>
                            <div class="mb-4 col-lg-<?php echo esc_attr( $column_width ); ?> col-sm-6 col-12<?php echo $sameheight ? ' d-flex' : ''; ?>">
                                <div class="shadow card d-flex flex-column<?php echo $sameheight ? ' h-100' : ''; ?>">
                                    <?php if ( $image ) :
                                        $svg_placeholder = 'data:image/svg+xml;base64,' . base64_encode(
                                            '<svg xmlns="http://www.w3.org/2000/svg" width="' . intval( $image[1] ) . '" height="' . intval( $image[2] ) . '" viewBox="0 0 ' . intval( $image[1] ) . ' ' . intval( $image[2] ) . '"><rect width="100%" height="100%" fill="#f8f9fb"/></svg>'
                                        );
                                        ?>
                                        <a href="<?php the_permalink(); ?>">
                                            <img
                                                src="<?php echo esc_url( $svg_placeholder ); ?>"
                                                data-src="<?php echo esc_url( $image[0] ); ?>"
                                                class="border-bottom m-0 img-fluid card-img-top lazyload"
                                                alt="<?php the_title_attribute(); ?>"
                                                width="<?php echo intval( $image[1] ); ?>"
                                                height="<?php echo intval( $image[2] ); ?>"
                                            />
                                        </a>
                                    <?php else : ?>
                                        <a href="<?php the_permalink(); ?>" class="card-img-top placeholder-glow">
                                            <span class="placeholder col-12" style="min-height:200px;"></span>
                                        </a>
                                    <?php endif; ?>

                                    <div class="card-body pb-4 d-flex flex-column flex-grow-1">
                                        <small class="lh-lg text-muted"><?php echo wp_kses_post( wc_get_product_category_list( get_the_ID(), ', ' ) ); ?></small>

                                        <a href="<?php the_permalink(); ?>" class="text-decoration-none">
                                            <p class="h3 fw-bold mt-2 text-dark card-title"><?php the_title(); ?></p>
                                        </a>

                                        <?php if ( function_exists( 'wc_review_ratings_enabled' ) && wc_review_ratings_enabled() ) : ?>
                                            <div class="mb-2">
                                                <?php echo wc_get_rating_html( $product->get_average_rating(), $product->get_rating_count() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                            </div>
                                        <?php endif; ?>

                                        <p class="h5 fw-bold text-primary mb-3"><?php echo wp_kses_post( $product->get_price_html() ); ?></p>

                                        <?php
                                        $short_description = $product->get_short_description();

                                        if ( empty( $short_description ) ) {
                                            $short_description = get_the_excerpt();
                                        }

                                        if ( $short_description ) :
                                            ?>
                                            <p class="card-text"><?php echo wp_kses_post( wp_trim_words( $short_description, 24, '&hellip;' ) ); ?></p>
                                        <?php endif; ?>

                                        <div class="<?php echo esc_attr( $cta_classes ); ?>">
                                            <?php woocommerce_template_loop_add_to_cart(); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>

                    <div class="mt-4 d-flex justify-content-center">
                        <?php woocommerce_pagination(); ?>
                    </div>
                </div>
            </div>
            <?php wc_reset_loop(); ?>
        <?php else : ?>
            <?php do_action( 'woocommerce_no_products_found' ); ?>
        <?php endif; ?>
    </div>
</section>
<?php
$flexible_source = null;
$flexible_post   = null;

if ( is_shop() && function_exists( 'wc_get_page_id' ) ) {
    $shop_id = wc_get_page_id( 'shop' );

    if ( $shop_id && $shop_id > 0 ) {
        $flexible_source = $shop_id;
        $maybe_post      = get_post( $shop_id );

        if ( $maybe_post instanceof WP_Post ) {
            $flexible_post = $maybe_post;
        }
    }
} elseif ( is_product_taxonomy() ) {
    $flexible_source = get_queried_object();
}

if ( $flexible_source && function_exists( 'have_rows' ) && have_rows( 'body', $flexible_source ) ) :
    global $post;
    $original_post = isset( $post ) && $post instanceof WP_Post ? $post : null;

    if ( $flexible_post instanceof WP_Post ) {
        $post = $flexible_post;
        setup_postdata( $post );
    }
    ?>
    <section class="shop-flexible-content py-5">
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
    <?php
    if ( $flexible_post instanceof WP_Post ) {
        if ( $original_post instanceof WP_Post ) {
            $post = $original_post;
            setup_postdata( $post );
        } else {
            wp_reset_postdata();
        }
    }
endif;
