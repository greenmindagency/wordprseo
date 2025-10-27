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

$imagesize = apply_filters( 'wordprseo_shop_card_image_size', 'medium_large' );
$column_classes = apply_filters( 'wordprseo_shop_card_column_classes', 'col-12 col-sm-6 col-md-3' );
$card_body_class = apply_filters( 'wordprseo_shop_card_body_class', 'p-4' );

$archive_title = function_exists( 'woocommerce_page_title' ) ? woocommerce_page_title( false ) : post_type_archive_title( '', false );

ob_start();
do_action( 'woocommerce_archive_description' );
$archive_description = trim( ob_get_clean() );

$row_classes = 'row g-4';
$row_attrs = '';

?>
<div class="container-fluid postsrelatedcat woocommerce-archive-grid woocommerce-product-grid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <?php if ( $archive_title ) : ?>
            <h1 class="page-title fs-3 fw-bold text-dark mb-0"><?php echo esc_html( $archive_title ); ?></h1>
        <?php endif; ?>

        <?php
        $can_show_ordering = true;

        if ( function_exists( 'wc_get_loop_prop' ) ) {
            $is_paginated = wc_get_loop_prop( 'is_paginated' );

            if ( null !== $is_paginated ) {
                $can_show_ordering = (bool) $is_paginated;
            }
        }

        if ( function_exists( 'woocommerce_products_will_display' ) ) {
            $can_show_ordering = $can_show_ordering && woocommerce_products_will_display();
        }

        if ( $can_show_ordering && function_exists( 'wc_clean' ) && function_exists( 'wc_query_string_form_fields' ) ) :
            $show_default_orderby = 'menu_order' === apply_filters( 'woocommerce_default_catalog_orderby', get_option( 'woocommerce_default_catalog_orderby', 'menu_order' ) );

            $catalog_orderby_options = apply_filters(
                'woocommerce_catalog_orderby',
                array(
                    'menu_order' => __( 'Default sorting', 'woocommerce' ),
                    'popularity' => __( 'Sort by popularity', 'woocommerce' ),
                    'rating'     => __( 'Sort by average rating', 'woocommerce' ),
                    'date'       => __( 'Sort by latest', 'woocommerce' ),
                    'price'      => __( 'Sort by price: low to high', 'woocommerce' ),
                    'price-desc' => __( 'Sort by price: high to low', 'woocommerce' ),
                )
            );

            $default_orderby = function_exists( 'wc_get_loop_prop' ) && wc_get_loop_prop( 'is_search' )
                ? 'relevance'
                : apply_filters( 'woocommerce_default_catalog_orderby', get_option( 'woocommerce_default_catalog_orderby', '' ) );

            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $orderby = isset( $_GET['orderby'] ) ? wc_clean( wp_unslash( $_GET['orderby'] ) ) : $default_orderby;

            if ( function_exists( 'wc_get_loop_prop' ) && wc_get_loop_prop( 'is_search' ) ) {
                $catalog_orderby_options = array_merge( array( 'relevance' => __( 'Relevance', 'woocommerce' ) ), $catalog_orderby_options );
                unset( $catalog_orderby_options['menu_order'] );
            }

            if ( ! $show_default_orderby ) {
                unset( $catalog_orderby_options['menu_order'] );
            }

            if ( function_exists( 'wc_review_ratings_enabled' ) && ! wc_review_ratings_enabled() ) {
                unset( $catalog_orderby_options['rating'] );
            }

            if ( is_array( $orderby ) ) {
                $orderby = current( array_intersect( $orderby, array_keys( $catalog_orderby_options ) ) );
            }

            if ( ! array_key_exists( $orderby, $catalog_orderby_options ) ) {
                $orderby = current( array_keys( $catalog_orderby_options ) );
            }

            ?>
            <div class="ms-auto">
                <form class="woocommerce-ordering mb-0" method="get">
                    <fieldset class="m-0 p-0 border-0">
                        <legend class="visually-hidden"><?php esc_html_e( 'Sort products', 'woocommerce' ); ?></legend>
                        <?php
                        $orderby_select_id = 'woocommerce-ordering-' . uniqid();
                        ?>
                        <label class="visually-hidden" for="<?php echo esc_attr( $orderby_select_id ); ?>">
                            <?php esc_html_e( 'Sort products', 'woocommerce' ); ?>
                        </label>
                        <select
                            name="orderby"
                            id="<?php echo esc_attr( $orderby_select_id ); ?>"
                            class="form-control form-control-sm w-auto shadow-sm rounded-0"
                            aria-label="<?php esc_attr_e( 'Shop order', 'woocommerce' ); ?>"
                        >
                            <?php foreach ( $catalog_orderby_options as $id => $name ) : ?>
                                <option value="<?php echo esc_attr( $id ); ?>" <?php selected( $orderby, $id ); ?>>
                                    <?php echo esc_html( $name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </fieldset>
                    <input type="hidden" name="paged" value="1" />
                    <?php wc_query_string_form_fields( null, array( 'orderby', 'submit', 'paged', 'product-page' ) ); ?>
                </form>
            </div>
        <?php endif; ?>
        </div>

    <?php if ( $archive_description ) : ?>
        <div class="text-muted mb-4 lead"><?php echo wp_kses_post( $archive_description ); ?></div>
    <?php endif; ?>

    <?php if ( woocommerce_product_loop() ) : ?>
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

                $image_html = '';

                if ( $image_id ) {
                    $image_html = wp_get_attachment_image(
                        $image_id,
                        $imagesize,
                        false,
                        array(
                            'class'   => 'card-img-top img-fluid rounded-0',
                            'alt'     => get_the_title(),
                            'loading' => 'lazy',
                        )
                    );
                } elseif ( function_exists( 'wc_placeholder_img_src' ) ) {
                    $image_html = sprintf(
                        '<img src="%1$s" alt="%2$s" class="card-img-top img-fluid rounded-0" loading="lazy" />',
                        esc_url( wc_placeholder_img_src( $imagesize ) ),
                        esc_attr( get_the_title() )
                    );
                }

                if ( ! $image_html ) {
                    $image_html = '<div class="card-img-top img-fluid rounded-0 bg-light"></div>';
                }

                $category_list  = wc_get_product_category_list( get_the_ID(), ', ' );
                $category_text  = $category_list ? wp_strip_all_tags( $category_list ) : '';
                $rating_count   = $product->get_rating_count();
                $average_rating = $product->get_average_rating();

                $star_icons = '<span class="text-warning me-1 fa-lg">';
                $rating     = floatval( $average_rating );

                for ( $i = 1; $i <= 5; $i++ ) {
                    if ( $rating >= $i ) {
                        $star_icons .= '<i class="fas fa-star" aria-hidden="true"></i>';
                    } elseif ( $rating >= ( $i - 0.5 ) ) {
                        $star_icons .= '<i class="fas fa-star-half-alt" aria-hidden="true"></i>';
                    } else {
                        $star_icons .= '<i class="far fa-star" aria-hidden="true"></i>';
                    }
                }

                $star_icons .= '</span>';

                if ( $rating <= 0 ) {
                    $rating_count = 0;
                }

                $price_markup = '';

                if ( $product->is_type( array( 'simple', 'variation' ) ) ) {
                    $regular_price = $product->get_regular_price();
                    $sale_price    = $product->get_sale_price();
                    $display_price = $product->get_price();

                    if ( $product->is_on_sale() && '' !== $sale_price && '' !== $regular_price ) {
                        $regular_display = wc_price( wc_get_price_to_display( $product, array( 'price' => (float) $regular_price ) ) );
                        $sale_display    = wc_price( wc_get_price_to_display( $product, array( 'price' => (float) $sale_price ) ) );

                        $price_markup  = '<span class="text-muted text-decoration-line-through me-2 fw-normal fs-6">' . wp_kses_post( $regular_display ) . '</span>';
                        $price_markup .= '<span class="text-danger">' . wp_kses_post( $sale_display ) . '</span>';
                    } elseif ( '' !== $display_price ) {
                        $display_value = wc_price( wc_get_price_to_display( $product ) );
                        $price_markup  = '<span>' . wp_kses_post( $display_value ) . '</span>';
                    }
                }

                if ( ! $price_markup ) {
                    $price_markup = wp_kses_post( $product->get_price_html() );
                }
?>
            <div class="<?php echo esc_attr( $column_classes ); ?>">
                <div class="card h-100 border-0 shadow-sm bg-light rounded-0">
                    <div class="position-relative">
                        <a href="<?php the_permalink(); ?>" class="d-block">
                            <?php echo $image_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </a>

                        <?php if ( $product->is_on_sale() ) : ?>
                            <span class="position-absolute top-0 start-0 m-3 badge text-bg-danger fw-semibold rounded-0"><?php esc_html_e( 'Sale!', 'woocommerce' ); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="card-body <?php echo esc_attr( $card_body_class ); ?>">
                        <?php if ( $category_text ) : ?>
                            <p class="text-uppercase text-muted fw-semibold mb-1 small"><?php echo esc_html( $category_text ); ?></p>
                        <?php endif; ?>

                        <a href="<?php the_permalink(); ?>" class="text-decoration-none text-dark">
                            <h2 class="fs-5 fw-semibold text-dark mb-2"><?php the_title(); ?></h2>
                        </a>

                        <div class="d-flex align-items-center small mb-2">
                            <?php echo wp_kses_post( $star_icons ); ?>
                            <span class="text-muted">(<?php echo esc_html( number_format_i18n( max( $rating_count, 0 ) ) ); ?>)</span>
                        </div>

                        <div class="fs-5 fw-bold text-dark mt-2">
                            <?php echo wp_kses_post( $price_markup ); ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php
            endwhile;
            ?>
        </div>

        <div class="mt-5 d-flex justify-content-center">
            <?php woocommerce_pagination(); ?>
        </div>
        <?php wc_reset_loop(); ?>
    <?php else : ?>
        <?php do_action( 'woocommerce_no_products_found' ); ?>
    <?php endif; ?>
</div>
</div>
<?php
$flexible_source = null;
$flexible_post = null;

if ( is_shop() && function_exists( 'wc_get_page_id' ) ) {
 $shop_id = wc_get_page_id( 'shop' );

 if ( $shop_id && $shop_id >0 ) {
 $flexible_source = $shop_id;
 $maybe_post = get_post( $shop_id );

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
