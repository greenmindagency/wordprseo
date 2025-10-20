<?php
/**
 * Related products template override.
 *
 * @package WordPrSEO
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if ( empty( $related_products ) ) {
    return;
}

$heading = ! empty( $args['heading'] ) ? $args['heading'] : __( 'You may also like', 'woocommerce' );
$columns = ! empty( $args['columns'] ) ? (int) $args['columns'] : 4;

if ( function_exists( 'wc_set_loop_prop' ) ) {
    wc_set_loop_prop( 'name', 'related' );
    wc_set_loop_prop( 'columns', $columns );
}
?>

<section class="related-products py-5 bg-light">
    <div class="container">
        <header class="mb-5 text-center text-md-start">
            <span class="text-muted text-uppercase small d-block fw-semibold"><?php esc_html_e( 'Related products', 'woocommerce' ); ?></span>
            <h2 class="display-6 fw-bold mt-2"><?php echo esc_html( $heading ); ?></h2>
        </header>

        <?php woocommerce_product_loop_start(); ?>

        <?php foreach ( $related_products as $related_product ) : ?>
            <?php
            $post_object = get_post( $related_product->get_id() );

            if ( ! $post_object ) {
                continue;
            }

            setup_postdata( $GLOBALS['post'] =& $post_object );

            wc_get_template_part( 'content', 'product' );
            ?>
        <?php endforeach; ?>

        <?php woocommerce_product_loop_end(); ?>
    </div>
</section>

<?php
wp_reset_postdata();
