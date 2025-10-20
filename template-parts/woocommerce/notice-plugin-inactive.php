<?php
/**
 * Notice displayed when WooCommerce is not active.
 *
 * @package WordPrSEO
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}
?>
<section class="container py-spacer">
    <div class="alert alert-warning shadow-sm" role="alert">
        <h2 class="h5 mb-2"><?php esc_html_e( 'WooCommerce not detected', 'wordprseo' ); ?></h2>
        <p class="mb-0">
            <?php esc_html_e( 'The WordPrSEO theme storefront is available once the WooCommerce plugin is installed and activated.', 'wordprseo' ); ?>
        </p>
    </div>
</section>
