<?php
/**
 * Quantity input template with Bootstrap styling.
 *
 * @package WordPrSEO
 */

defined( 'ABSPATH' ) || exit;

?>
<?php
$quantity_label = isset( $product_name ) && $product_name
    ? sprintf( __( '%s quantity', 'woocommerce' ), $product_name )
    : __( 'Quantity', 'woocommerce' );
?>
<div class="quantity quantity-input-group">
    <?php do_action( 'woocommerce_before_quantity_input_field' ); ?>

    <label class="form-label visually-hidden" for="<?php echo esc_attr( $input_id ); ?>">
        <?php echo esc_html( $quantity_label ); ?>
    </label>

    <?php if ( $max_value && $min_value === $max_value ) : ?>
        <input
            type="hidden"
            id="<?php echo esc_attr( $input_id ); ?>"
            class="qty"
            name="<?php echo esc_attr( $input_name ); ?>"
            value="<?php echo esc_attr( $min_value ); ?>"
        />
    <?php else : ?>
        <input
            type="number"
            id="<?php echo esc_attr( $input_id ); ?>"
            class="form-control text-center input-text qty"
            step="<?php echo esc_attr( $step ); ?>"
            min="<?php echo esc_attr( $min_value ); ?>"
            <?php if ( $max_value ) : ?>max="<?php echo esc_attr( $max_value ); ?>"<?php endif; ?>
            name="<?php echo esc_attr( $input_name ); ?>"
            value="<?php echo esc_attr( $input_value ); ?>"
            title="<?php echo esc_attr_x( 'Qty', 'Product quantity input tooltip', 'woocommerce' ); ?>"
            size="4"
            pattern="<?php echo esc_attr( $pattern ); ?>"
            placeholder="<?php echo esc_attr( $placeholder ); ?>"
            inputmode="<?php echo esc_attr( $inputmode ); ?>"
        />
    <?php endif; ?>

    <?php do_action( 'woocommerce_after_quantity_input_field' ); ?>
</div>
