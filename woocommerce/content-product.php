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

if ( ! isset( $woocommerce_loop['loop'] ) ) {
    $woocommerce_loop['loop'] = 0;
}

if ( ! isset( $woocommerce_loop['columns'] ) || ! $woocommerce_loop['columns'] ) {
    $woocommerce_loop['columns'] = apply_filters( 'loop_shop_columns', 4 );
}

$woocommerce_loop['loop']++;

$columns       = max( 1, (int) $woocommerce_loop['columns'] );
$lg_columns    = max( 1, min( 4, $columns ) );
$lg_span       = max( 1, intval( 12 / $lg_columns ) );
$base_classes  = array( 'product-card', 'mb-4', 'col-12', 'col-sm-6', sprintf( 'col-lg-%d', $lg_span ) );
$wrapper_class = implode( ' ', array_map( 'sanitize_html_class', $base_classes ) );
?>

<li <?php wc_product_class( $wrapper_class ); ?>>
    <div class="card h-100 shadow-sm border-0">
        <div class="position-relative">
            <a class="d-block" href="<?php the_permalink(); ?>">
                <?php woocommerce_template_loop_product_thumbnail(); ?>
            </a>
            <?php woocommerce_show_product_loop_sale_flash(); ?>
        </div>

        <div class="card-body">
            <?php
            $categories_list = function_exists( 'wc_get_product_category_list' )
                ? wc_get_product_category_list( get_the_ID(), ', ' )
                : '';

            if ( $categories_list ) :
                ?>
                <small class="text-muted d-block mb-2 text-uppercase"><?php echo wp_kses_post( $categories_list ); ?></small>
            <?php
            endif;

            woocommerce_template_loop_product_title();
            ?>

            <div class="product-card__meta mt-3">
                <?php woocommerce_template_loop_price(); ?>
            </div>
        </div>

        <div class="card-footer bg-transparent border-0 pt-0">
            <?php woocommerce_template_loop_rating(); ?>
            <div class="mt-3">
                <?php woocommerce_template_loop_add_to_cart(); ?>
            </div>
        </div>
    </div>
</li>
