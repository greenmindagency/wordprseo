<?php
/**
 * Cart page template with Bootstrap-friendly markup for the WordPrSEO theme.
 *
 * @package WordPrSEO
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @version8.6.0
 */

defined( 'ABSPATH' ) || exit;

// Ensure the default WooCommerce breadcrumb is not rendered on this page.
if ( function_exists( 'remove_action' ) ) {
 remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb',20 );
}

$cart_url = wc_get_cart_url();

woocommerce_output_all_notices();

do_action( 'woocommerce_before_cart' );
?>
<div class="container my-5">
 <!-- Header (match shop page style, no breadcrumb) -->
 <div class="d-flex justify-content-between align-items-center mb-4">
 <h1 class="fs-3 fw-bold text-dark"><?php esc_html_e( 'Cart', 'woocommerce' ); ?></h1>
 </div>

<form class="woocommerce-cart-form" action="<?php echo esc_url( $cart_url ); ?>" method="post">
<?php do_action( 'woocommerce_before_cart_table' ); ?>

<div class="row g-4">
<div class="col-12 col-lg-8">
<div class="card border-0 shadow-sm h-100">
<div class="card-body p-0">
<div class="table-responsive">
<table class="shop_table shop_table_responsive cart table align-middle mb-0">
<thead class="table-light">
<tr>
<th class="product-remove text-center">&nbsp;</th>
<th class="product-thumbnail">&nbsp;</th>
<th class="product-name"><?php esc_html_e( 'Product', 'woocommerce' ); ?></th>
<th class="product-price text-end"><?php esc_html_e( 'Price', 'woocommerce' ); ?></th>
<th class="product-quantity text-end"><?php esc_html_e( 'Quantity', 'woocommerce' ); ?></th>
<th class="product-subtotal text-end"><?php esc_html_e( 'Subtotal', 'woocommerce' ); ?></th>
</tr>
</thead>
<tbody>
<?php do_action( 'woocommerce_before_cart_contents' ); ?>

<?php foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) : ?>
<?php
if ( empty( $cart_item['data'] ) || ! $cart_item['data']->exists() || $cart_item['quantity'] <=0 ) {
continue;
}

$product = $cart_item['data'];
$product_id = $cart_item['product_id'];
$product_permalink = apply_filters( 'woocommerce_cart_item_permalink', $product->is_visible() ? $product->get_permalink( $cart_item ) : '', $cart_item, $cart_item_key );
?>
<tr class="woocommerce-cart-form__cart-item <?php echo esc_attr( apply_filters( 'woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key ) ); ?>">
<td class="product-remove text-center">
<?php
echo apply_filters( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
'woocommerce_cart_item_remove_link',
sprintf(
'<a href="%s" class="btn btn-sm btn-outline-danger" aria-label="%s" data-product_id="%s" data-product_sku="%s">&times;</a>',
esc_url( wc_get_cart_remove_url( $cart_item_key ) ),
esc_attr__( 'Remove this item', 'woocommerce' ),
esc_attr( $product_id ),
esc_attr( $product->get_sku() )
),
$cart_item_key
);
?>
</td>

<td class="product-thumbnail" data-title="<?php esc_attr_e( 'Product', 'woocommerce' ); ?>">
<?php
$thumbnail = apply_filters( 'woocommerce_cart_item_thumbnail', $product->get_image( 'woocommerce_thumbnail' ), $cart_item, $cart_item_key );

if ( $product_permalink ) {
echo '<a href="' . esc_url( $product_permalink ) . '" class="d-inline-block">' . $thumbnail . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
} else {
echo $thumbnail; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
?>
</td>

<td class="product-name" data-title="<?php esc_attr_e( 'Product', 'woocommerce' ); ?>">
<?php
$name_html = apply_filters( 'woocommerce_cart_item_name', $product->get_name(), $cart_item, $cart_item_key );

if ( $product_permalink ) {
echo '<a href="' . esc_url( $product_permalink ) . '" class="text-decoration-none">' . wp_kses_post( $name_html ) . '</a>';
} else {
echo wp_kses_post( $name_html );
}

echo wc_get_formatted_cart_item_data( $cart_item );

if ( $product->backorders_require_notification() && $product->is_on_backorder( $cart_item['quantity'] ) ) {
echo '<p class="text-warning small mb-0">' . esc_html__( 'Available on backorder', 'woocommerce' ) . '</p>';
}
?>
</td>

<td class="product-price text-end" data-title="<?php esc_attr_e( 'Price', 'woocommerce' ); ?>">
<?php echo apply_filters( 'woocommerce_cart_item_price', WC()->cart->get_product_price( $product ), $cart_item, $cart_item_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</td>

<td class="product-quantity text-end" data-title="<?php esc_attr_e( 'Quantity', 'woocommerce' ); ?>">
<?php
if ( $product->is_sold_individually() ) {
echo '1';
} else {
echo woocommerce_quantity_input( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
array(
'input_name' => "cart[{$cart_item_key}][qty]",
'input_value' => $cart_item['quantity'],
'classes' => array( 'input-text', 'qty', 'form-control', 'text-end' ),
'inputmode' => 'numeric',
),
$product,
false
);
}
?>
</td>

<td class="product-subtotal text-end" data-title="<?php esc_attr_e( 'Subtotal', 'woocommerce' ); ?>">
<?php echo apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $product, $cart_item['quantity'] ), $cart_item, $cart_item_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</td>
</tr>
<?php endforeach; ?>

<?php do_action( 'woocommerce_cart_contents' ); ?>

<tr>
<td colspan="6" class="actions">
<div class="border-top p-4">
<div class="row g-3 justify-content-between align-items-center">
<?php if ( wc_coupons_enabled() ) : ?>
<div class="col-12 col-md-6">
<label for="coupon_code" class="form-label mb-1"><?php esc_html_e( 'Coupon', 'woocommerce' ); ?></label>
<div class="input-group">
<input type="text" name="coupon_code" class="input-text form-control" id="coupon_code" value="" placeholder="<?php esc_attr_e( 'Coupon code', 'woocommerce' ); ?>" />
<button type="submit" class="btn btn-outline-secondary" name="apply_coupon" value="<?php esc_attr_e( 'Apply coupon', 'woocommerce' ); ?>"><?php esc_html_e( 'Apply coupon', 'woocommerce' ); ?></button>
</div>
<?php do_action( 'woocommerce_cart_coupon' ); ?>
</div>
<?php endif; ?>

<div class="col-12 col-md-auto text-md-end">
<button type="submit" class="btn btn-primary" name="update_cart" value="<?php esc_attr_e( 'Update cart', 'woocommerce' ); ?>"><?php esc_html_e( 'Update cart', 'woocommerce' ); ?></button>
<?php do_action( 'woocommerce_cart_actions' ); ?>
<?php wp_nonce_field( 'woocommerce-cart', 'woocommerce-cart-nonce' ); ?>
</div>
</div>
</div>
</td>
</tr>

<?php do_action( 'woocommerce_after_cart_contents' ); ?>
</tbody>
</table>
</div>
</div>
</div>

<?php do_action( 'woocommerce_after_cart_table' ); ?>
</div>

<div class="col-12 col-lg-4">
<div class="card border-0 shadow-sm h-100">
<div class="card-body">
<h2 class="h5 mb-4"><?php esc_html_e( 'Cart totals', 'woocommerce' ); ?></h2>
<?php do_action( 'woocommerce_before_cart_collaterals' ); ?>
<div class="woocommerce-cart-collaterals">
<?php woocommerce_cart_totals(); ?>
</div>
<?php do_action( 'woocommerce_after_cart_collaterals' ); ?>
</div>
</div>
</div>
</div>
</form>

<?php if ( WC()->cart->get_cross_sells() ) : ?>
<div class="mt-5">
<h2 class="h4 mb-4"><?php esc_html_e( 'You may also like…', 'woocommerce' ); ?></h2>
<div class="woocommerce-cart-cross-sells">
<?php woocommerce_cross_sell_display(); ?>
</div>
</div>
<?php endif; ?>
</div>

<?php do_action( 'woocommerce_after_cart' ); ?>
