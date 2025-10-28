<?php
/**
 * My Account page.
 *
 * @package WordPrSEO
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_my_account' );
?>
<div class="container py-4 py-spacer-2">
    <div class="row g-4">
        <div class="col-lg-4 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <?php do_action( 'woocommerce_account_navigation' ); ?>
                </div>
            </div>
        </div>
        <div class="col-lg-8 col-xl-9">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <div class="woocommerce-MyAccount-content">
                        <?php do_action( 'woocommerce_account_content' ); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php

do_action( 'woocommerce_after_my_account' );
