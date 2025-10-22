<?php
/**
 * The template for displaying product rating on the single product page.
 *
 * @package WordPrSEO
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $product;

if ( ! $product instanceof WC_Product || ! wc_review_ratings_enabled() ) {
    return;
}

$rating_count = $product->get_rating_count();
$review_count = $product->get_review_count();
$average      = $product->get_average_rating();

if ( $rating_count <= 0 ) {
    return;
}

$average_display = number_format_i18n( $average, 1 );
$reviews_link    = get_permalink( $product->get_id() ) . '#reviews';
$rating_text_template = _n(
    'Rated %1$s out of 5 based on %2$s customer rating',
    'Rated %1$s out of 5 based on %2$s customer ratings',
    $rating_count,
    'woocommerce'
);
$rating_text = sprintf(
    $rating_text_template,
    $average_display,
    number_format_i18n( $rating_count )
);

$review_text_template = _n( '%s review', '%s reviews', $review_count, 'woocommerce' );
$review_text          = sprintf(
    $review_text_template,
    number_format_i18n( $review_count )
);

$fraction   = $average - floor( $average );
$full_stars = floor( $average );
$has_half   = $fraction >= 0.25 && $fraction < 0.75;

if ( $fraction >= 0.75 ) {
    $full_stars++;
}
?>
<div class="woocommerce-product-rating product-rating d-flex flex-column flex-sm-row align-items-sm-center gap-3 text-muted" aria-label="<?php echo esc_attr( $rating_text ); ?>">
    <div class="product-rating__summary d-flex align-items-center gap-3">
        <span class="product-rating__average fw-semibold fs-4 text-dark">
            <?php echo esc_html( $average_display ); ?>
        </span>
        <ul class="product-rating__stars list-unstyled d-flex gap-1 text-warning fs-5 mb-0">
            <li class="visually-hidden"><?php echo esc_html( $rating_text ); ?></li>
            <?php for ( $i = 1; $i <= 5; $i++ ) : ?>
                <li>
                    <?php if ( $i <= $full_stars ) : ?>
                        <i class="fas fa-star" aria-hidden="true"></i>
                    <?php elseif ( $has_half && $i === $full_stars + 1 ) : ?>
                        <i class="fas fa-star-half-alt" aria-hidden="true"></i>
                    <?php else : ?>
                        <i class="far fa-star" aria-hidden="true"></i>
                    <?php endif; ?>
                </li>
            <?php endfor; ?>
        </ul>
    </div>
    <a class="woocommerce-review-link product-rating__reviews small text-uppercase tracking-wide text-decoration-none" href="<?php echo esc_url( $reviews_link ); ?>">
        <?php echo esc_html( $review_text ); ?>
    </a>
</div>
