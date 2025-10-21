<?php
/**
 * Additional information tab template override for Bootstrap styling.
 *
 * @package WordPrSEO
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( ! $product instanceof WC_Product ) {
    return;
}

$attributes = array_filter( $product->get_attributes(), 'wc_attributes_array_filter_visible' );
$display_dimensions = $product->has_weight() || $product->has_dimensions();

if ( empty( $attributes ) && ! $display_dimensions ) {
    return;
}

ob_start();
wc_display_product_attributes( $product );
$attributes_html = ob_get_clean();

if ( $attributes_html ) {
    $attributes_html = str_replace(
        'class="woocommerce-product-attributes shop_attributes"',
        'class="woocommerce-product-attributes shop_attributes list-group list-group-flush mb-4 border mt-3"',
        $attributes_html
    );

    $attributes_html = preg_replace(
        '/class="woocommerce-product-attributes-item woocommerce-product-attributes-item--([^"]+)"/i',
        'class="woocommerce-product-attributes-item woocommerce-product-attributes-item--$1 list-group-item d-flex justify-content-between align-items-center"',
        $attributes_html
    );

    $attributes_html = str_replace(
        'class="woocommerce-product-attributes-item__label"',
        'class="woocommerce-product-attributes-item__label fw-semibold mb-0 me-3 text-dark"',
        $attributes_html
    );

    $attributes_html = str_replace(
        'class="woocommerce-product-attributes-item__value"',
        'class="woocommerce-product-attributes-item__value text-end mb-0"',
        $attributes_html
    );
}

echo $attributes_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
