<?php
/**
 * Template for rendering products within loops.
 *
 * @package WordPrSEO
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

global $product, $woocommerce_loop;

if ( empty( $product ) || ! $product->is_visible() ) {
    return;
}

$imagesize       = apply_filters( 'wordprseo_shop_card_image_size', 'medium_large' );
$column_classes  = apply_filters( 'wordprseo_shop_card_column_classes', 'col-12 col-sm-6 col-md-3' );
$card_body_class = apply_filters( 'wordprseo_shop_card_body_class', 'p-4' );

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

$category_list  = function_exists( 'wc_get_product_category_list' ) ? wc_get_product_category_list( get_the_ID(), ', ' ) : '';
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

<li <?php wc_product_class( trim( $column_classes ) ); ?>>
    <div class="card h-100 border-0 shadow-sm bg-light rounded-0">
        <div class="position-relative">
            <a class="d-block" href="<?php the_permalink(); ?>">
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

            <div class="fs-5 fw-bold text-dark mt-2 product-price">
                <?php echo wp_kses_post( $price_markup ); ?>
            </div>
        </div>
    </div>
</li>
