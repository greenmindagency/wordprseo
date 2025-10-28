<?php
/**
 * Checkout form template tailored for Bootstrap markup in the WordPrSEO theme.
 *
 * @package WordPrSEO
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @version8.6.0
 */

defined( 'ABSPATH' ) || exit;

// Ensure default WooCommerce breadcrumb is not rendered on checkout
if ( function_exists( 'remove_action' ) ) {
 remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb',20 );
}

ob_start();
woocommerce_output_all_notices();
$notices_output = ob_get_clean();

ob_start();
do_action( 'woocommerce_before_checkout_form', $checkout );
$before_checkout_output = ob_get_clean();

if ( ! $checkout || ( ! $checkout->is_registration_enabled() && $checkout->is_registration_required() && ! is_user_logged_in() ) ) {
    echo '<div class="container py-spacer-2">' . $notices_output . $before_checkout_output . '</div>';
    return;
}
?>
<div class="container py-spacer-2">
    <?php
    echo $notices_output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo $before_checkout_output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    ?>
    <!-- Header (match shop page style, no breadcrumb) -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="fs-3 fw-bold text-dark"><?php esc_html_e( 'Checkout', 'woocommerce' ); ?></h1>
    </div>

<form name="checkout" method="post" class="checkout woocommerce-checkout" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data">
<div class="row g-4">
<?php if ( $checkout->get_checkout_fields() ) : ?>
<div class="col-12 col-lg-7">
<?php do_action( 'woocommerce_checkout_before_customer_details' ); ?>

<div id="customer_details" class="card border-0 shadow-sm">
<div class="card-header bg-transparent border-bottom py-3">
<h2 class="h5 mb-0"><?php esc_html_e( 'Billing &amp; Shipping', 'woocommerce' ); ?></h2>
</div>
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
<div class="card border-0 shadow-sm">
<div class="card-header bg-transparent border-bottom py-3">
<h2 class="h5 mb-0"><?php esc_html_e( 'Your order', 'woocommerce' ); ?></h2>
</div>
<div class="card-body">
<?php do_action( 'woocommerce_checkout_before_order_review' ); ?>

<div id="order_review" class="woocommerce-checkout-review-order">
<?php do_action( 'woocommerce_checkout_order_review' ); ?>
</div>

<?php do_action( 'woocommerce_checkout_after_order_review' ); ?>
</div>
</div>
</div>
</div>
</form>

<?php do_action( 'woocommerce_after_checkout_form', $checkout ); ?>
</div>
