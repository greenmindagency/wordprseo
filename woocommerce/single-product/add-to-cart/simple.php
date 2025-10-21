<?php
/**
 * Simple product add to cart template with Bootstrap friendly markup.
 *
 * @package WordPrSEO
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( ! $product instanceof WC_Product ) {
    return;
}

if ( ! $product->is_purchasable() ) {
    echo wc_get_stock_html( $product );
    return;
}

echo wc_get_stock_html( $product );

if ( ! $product->is_in_stock() ) {
    return;
}

$min_purchase_quantity = apply_filters( 'woocommerce_quantity_input_min', $product->get_min_purchase_quantity(), $product );
$max_purchase_quantity = apply_filters( 'woocommerce_quantity_input_max', $product->get_max_purchase_quantity(), $product );
$default_quantity      = isset( $_POST['quantity'] ) ? wc_stock_amount( wp_unslash( $_POST['quantity'] ) ) : $product->get_min_purchase_quantity();

do_action( 'woocommerce_before_add_to_cart_form' );
?>

<form class="cart product-add-to-cart-form d-flex flex-column flex-sm-row gap-3 align-items-stretch" action="<?php echo esc_url( apply_filters( 'woocommerce_add_to_cart_form_action', $product->get_permalink() ) ); ?>" method="post" enctype="multipart/form-data">
    <?php do_action( 'woocommerce_before_add_to_cart_button' ); ?>

    <div class="product-add-to-cart-inner d-flex flex-column flex-sm-row align-items-stretch gap-3 w-100">
        <?php do_action( 'woocommerce_before_add_to_cart_quantity' ); ?>

        <div class="product-quantity flex-sm-grow-0 w-100 w-sm-auto">
            <?php
            woocommerce_quantity_input(
                array(
                    'min_value'   => $min_purchase_quantity,
                    'max_value'   => $max_purchase_quantity,
                    'input_value' => $default_quantity,
                )
            );
            ?>
        </div>

        <?php do_action( 'woocommerce_after_add_to_cart_quantity' ); ?>

        <button type="submit" name="add-to-cart" value="<?php echo esc_attr( $product->get_id() ); ?>" class="single_add_to_cart_button button alt btn btn-primary btn-lg px-4 flex-sm-grow-0 w-100 w-sm-auto">
            <?php echo esc_html( apply_filters( 'woocommerce_product_single_add_to_cart_text', $product->single_add_to_cart_text(), $product ) ); ?>
        </button>

        <?php do_action( 'woocommerce_after_add_to_cart_button' ); ?>
    </div>
</form>

<?php do_action( 'woocommerce_after_add_to_cart_form' ); ?>
