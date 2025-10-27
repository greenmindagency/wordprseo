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

$reviews_link = get_permalink( $product->get_id() ) . '#reviews';
?>
<div class="woocommerce-product-rating">
    <?php
    echo wordprseo_get_star_rating_html(
        $average,
        $rating_count,
        array(
            'class' => 'wordprseo-star-rating text-warning d-inline-flex align-items-center gap-1 me-2'
        )
    );
    ?>
    <?php if ( comments_open() ) : ?>
        <a class="woocommerce-review-link" href="<?php echo esc_url( $reviews_link ); ?>" rel="nofollow">
            <?php
            printf(
                /* translators: %s: number of reviews */
                esc_html( _n( '%s customer review', '%s customer reviews', $review_count, 'woocommerce' ) ),
                esc_html( number_format_i18n( $review_count ) )
            );
            ?>
        </a>
    <?php endif; ?>
</div>
