<?php
/**
 * Start of the product loop container.
 *
 * @package WordPrSEO
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

$columns = isset( $args['columns'] ) ? (int) $args['columns'] : apply_filters( 'loop_shop_columns', 4 );
$classes = array(
    'products',
    'list-unstyled',
    'row',
    'g-4',
    sprintf( 'columns-%d', max( 1, $columns ) ),
);
?>

<ul class="<?php echo esc_attr( implode( ' ', array_map( 'sanitize_html_class', $classes ) ) ); ?>">
