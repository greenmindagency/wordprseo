<?php
/**
 * Notice displayed when WooCommerce setup is incomplete.
 *
 * @package WordPrSEO
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

$status = wordprseo_get_woocommerce_setup_status();
?>
<section class="container py-spacer">
    <div class="alert alert-info shadow-sm" role="alert">
        <h2 class="h5 mb-3"><?php esc_html_e( 'Finish setting up your store', 'wordprseo' ); ?></h2>
        <p>
            <?php esc_html_e( 'Before we display the storefront, make sure WooCommerce has at least one published product and one product category.', 'wordprseo' ); ?>
        </p>
        <ul class="mb-3">
            <li>
                <?php echo wp_kses_post( $status['has_products'] ? __( '✓ Products detected.', 'wordprseo' ) : __( '• No published products found yet.', 'wordprseo' ) ); ?>
            </li>
            <li>
                <?php echo wp_kses_post( $status['has_categories'] ? __( '✓ Product categories detected.', 'wordprseo' ) : __( '• No product categories found yet.', 'wordprseo' ) ); ?>
            </li>
        </ul>
        <a class="btn btn-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=wc-admin' ) ); ?>">
            <?php esc_html_e( 'Open WooCommerce setup', 'wordprseo' ); ?>
        </a>
    </div>
</section>
