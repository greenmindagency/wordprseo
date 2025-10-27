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
$column_classes  = apply_filters( 'wordprseo_shop_card_column_classes', 'col-12 col-sm-6 col-lg-4 col-xl-3' );
$card_body_class = apply_filters( 'wordprseo_shop_card_body_class', 'p-4 d-flex flex-column flex-grow-1' );

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
            'class'   => 'card-img-top w-100 h-100',
            'alt'     => get_the_title(),
            'loading' => 'lazy',
        )
    );
} elseif ( function_exists( 'wc_placeholder_img_src' ) ) {
    $image_html = sprintf(
        '<img src="%1$s" alt="%2$s" class="card-img-top w-100 h-100" loading="lazy" />',
        esc_url( wc_placeholder_img_src( $imagesize ) ),
        esc_attr( get_the_title() )
    );
}

if ( ! $image_html ) {
    $image_html = '<div class="card-img-top w-100 h-100 bg-light"></div>';
}

$category_list  = function_exists( 'wc_get_product_category_list' ) ? wc_get_product_category_list( get_the_ID(), ', ' ) : '';
$category_text  = $category_list ? wp_strip_all_tags( $category_list ) : '';
$rating_count   = $product->get_rating_count();
$average_rating = $product->get_average_rating();
?>

<li <?php wc_product_class( trim( $column_classes . ' d-flex' ) ); ?>>
    <div class="card h-100 border-0 custom-shadow transition hover-shadow w-100 d-flex flex-column">
        <div class="product-img-container position-relative">
            <a class="d-block" href="<?php the_permalink(); ?>">
                <?php echo $image_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </a>

            <?php if ( $product->is_on_sale() ) : ?>
                <span class="position-absolute top-0 start-0 m-3 badge text-bg-danger fw-semibold text-uppercase"><?php esc_html_e( 'Sale!', 'woocommerce' ); ?></span>
            <?php endif; ?>
        </div>

        <div class="card-body <?php echo esc_attr( $card_body_class ); ?>">
            <?php if ( $category_text ) : ?>
                <p class="text-uppercase text-muted fw-semibold mb-1 small"><?php echo esc_html( $category_text ); ?></p>
            <?php endif; ?>

            <a href="<?php the_permalink(); ?>" class="text-decoration-none text-dark">
                <h2 class="fs-5 fw-semibold text-dark mb-2"><?php the_title(); ?></h2>
            </a>

            <?php if ( function_exists( 'wc_review_ratings_enabled' ) && wc_review_ratings_enabled() && $rating_count > 0 ) : ?>
                <div class="d-flex align-items-center small mb-2 gap-2">
                    <?php
                    echo wordprseo_get_star_rating_html(
                        $average_rating,
                        $rating_count,
                        array(
                            'class' => 'wordprseo-star-rating text-warning d-inline-flex align-items-center gap-1 small'
                        )
                    );
                    ?>
                    <span class="text-muted">(<?php echo esc_html( $rating_count ); ?>)</span>
                </div>
            <?php endif; ?>

            <div class="fs-5 fw-bold text-dark mt-2 product-price">
                <?php echo wp_kses_post( $product->get_price_html() ); ?>
            </div>

            <div class="mt-3">
                <?php woocommerce_template_loop_add_to_cart(); ?>
            </div>
        </div>
    </div>
</li>
