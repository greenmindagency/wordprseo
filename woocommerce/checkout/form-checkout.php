<?php
/**
 * Checkout form template tailored for Bootstrap markup in the WordPrSEO theme.
 *
 * @package WordPrSEO
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @version 8.6.0
 */

defined( 'ABSPATH' ) || exit;

wc_print_notices();

do_action( 'woocommerce_before_checkout_form', $checkout );

if ( ! $checkout || ( ! $checkout->is_registration_enabled() && $checkout->is_registration_required() && ! is_user_logged_in() ) ) {
return;
}
?>
<form name="checkout" method="post" class="checkout woocommerce-checkout" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data">
<div class="row g-4">
<?php if ( $checkout->get_checkout_fields() ) : ?>
<div class="col-12 col-lg-7">
<?php do_action( 'woocommerce_checkout_before_customer_details' ); ?>

<div id="customer_details" class="card border-0 shadow-sm">
<div class="card-body">
<?php do_action( 'woocommerce_checkout_billing' ); ?>
<hr class="my-4" />
<?php do_action( 'woocommerce_checkout_shipping' ); ?>
</div>
</div>

<?php do_action( 'woocommerce_checkout_after_customer_details' ); ?>
</div>
<?php endif; ?>

<div class="col-12 col-lg-5">
<h3 class="h4 mb-3"><?php esc_html_e( 'Your order', 'woocommerce' ); ?></h3>
<?php do_action( 'woocommerce_checkout_before_order_review' ); ?>

<div id="order_review" class="woocommerce-checkout-review-order card border-0 shadow-sm">
<div class="card-body">
<?php do_action( 'woocommerce_checkout_order_review' ); ?>
</div>
</div>

<?php do_action( 'woocommerce_checkout_after_order_review' ); ?>
</div>
</div>
</form>

<?php do_action( 'woocommerce_after_checkout_form', $checkout ); ?>
