<?php
/**
 * Custom archive template that mirrors the "postsrelatedproducts" flexible layout
 * for the shop page and product category archives.
 *
 * @package WordPrSEO
 */

if ( ! defined( 'ABSPATH' ) ) {
 exit; // Exit if accessed directly.
}

if ( function_exists( 'woocommerce_output_all_notices' ) ) {
 woocommerce_output_all_notices();
}

$imagesize = apply_filters( 'wordprseo_shop_card_image_size', 'medium_large' );
$column_classes = apply_filters( 'wordprseo_shop_card_column_classes', 'col-12 col-sm-6 col-lg-4 col-xl-3' );
$card_body_class = apply_filters( 'wordprseo_shop_card_body_class', 'p-4 d-flex flex-column flex-grow-1' );

$archive_title = function_exists( 'woocommerce_page_title' ) ? woocommerce_page_title( false ) : post_type_archive_title( '', false );

ob_start();
do_action( 'woocommerce_archive_description' );
$archive_description = trim( ob_get_clean() );

$row_classes = 'row g-4';
$row_attrs = '';

?>
<div class="postsrelatedcat woocommerce-archive-grid woocommerce-product-grid">
 <div class="container py-spacer-2">
 <div class="page-title-bar d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
 <div class="flex-grow-1">
 <?php if ( $archive_title ) : ?>
 <h1 class="page-title fs-3 fw-bold text-dark mb-0"><?php echo esc_html( $archive_title ); ?></h1>
 <?php endif; ?>

 <?php if ( $archive_description ) : ?>
 <div class="text-muted mt-2 lead">
 <?php echo wp_kses_post( $archive_description ); ?>
 </div>
 <?php endif; ?>
 </div>

 <?php if ( function_exists( 'woocommerce_catalog_ordering' ) ) : ?>
 <div class="ms-md-4">
 <?php
 ob_start();
 woocommerce_catalog_ordering();
 $ordering_markup = ob_get_clean();

 if ( $ordering_markup ) {
 $ordering_markup = str_replace(
 'class="woocommerce-ordering"',
 'class="woocommerce-ordering d-flex align-items-center gap-2"',
 $ordering_markup
 );

 $ordering_markup = str_replace(
 'class="orderby"',
 'class="orderby form-select w-auto shadow-sm rounded-0"',
 $ordering_markup
 );
 echo $ordering_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
 }
 ?>
 </div>
 <?php endif; ?>
 </div>

 <?php if ( woocommerce_product_loop() ) : ?>
 <div class="<?php echo esc_attr( $row_classes ); ?>"<?php echo $row_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
 <?php
 while ( have_posts() ) :
 the_post();

 global $product;
 $product = wc_get_product( get_the_ID() );

 if ( ! $product ) {
 continue;
 }

 $image_id = $product->get_image_id();

 if ( ! $image_id ) {
 $gallery_image_ids = $product->get_gallery_image_ids();

 if ( ! empty( $gallery_image_ids ) ) {
 $image_id = (int) reset( $gallery_image_ids );
 }
 }

 $image_html = '';

 if ( $image_id ) {
 $image_html = wp_get_attachment_image(
 $image_id,
 $imagesize,
 false,
 array(
 'class' => 'card-img-top w-100 h-100',
 'alt' => get_the_title(),
 'loading' => 'lazy',
 )
 );
 } elseif ( function_exists( 'wc_placeholder_img_src' ) ) {
 $image_html = sprintf(
 '<img src="%1$s" alt="%2$s" class="card-img-top w-100 h-100" loading="lazy" />',
 esc_url( wc_placeholder_img_src( $imagesize ) ),
 esc_attr( get_the_title() )
 );
 }

 if ( ! $image_html ) {
 $image_html = '<div class="card-img-top w-100 h-100 bg-light"></div>';
 }

 $category_list = wc_get_product_category_list( get_the_ID(), ', ' );
 $category_text = $category_list ? wp_strip_all_tags( $category_list ) : '';
 $rating_count = $product->get_rating_count();
 $average_rating = $product->get_average_rating();
 ?>
 <div class="<?php echo esc_attr( trim( $column_classes . ' d-flex' ) ); ?>">
 <div class="card h-100 border-0 custom-shadow transition hover-shadow w-100 d-flex flex-column">
 <div class="product-img-container position-relative">
 <a href="<?php the_permalink(); ?>">
 <?php echo $image_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
 </a>

 <?php if ( $product->is_on_sale() ) : ?>
 <span class="position-absolute top-0 start-0 m-3 badge text-bg-danger fw-semibold text-uppercase"><?php esc_html_e( 'Sale!', 'woocommerce' ); ?></span>
 <?php endif; ?>
 </div>

 <div class="card-body <?php echo esc_attr( $card_body_class ); ?>">
 <?php if ( $category_text ) : ?>
 <p class="text-uppercase text-muted fw-semibold mb-1 small"><?php echo esc_html( $category_text ); ?></p>
 <?php endif; ?>

 <a href="<?php the_permalink(); ?>" class="text-decoration-none text-dark">
 <h2 class="fs-5 fw-semibold text-dark mb-2"><?php the_title(); ?></h2>
 </a>

 <?php if ( function_exists( 'wc_review_ratings_enabled' ) && wc_review_ratings_enabled() ) : ?>
 <div class="d-flex align-items-center small mb-2 gap-2">
 <?php if ( $rating_count >0 ) : ?>
 <div class="woocommerce-star-rating">
 <?php echo wc_get_rating_html( $average_rating, $rating_count ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
 </div>
 <?php else : ?>
 <span class="text-warning">☆☆☆☆☆</span>
 <?php endif; ?>
 <span class="text-muted">(<?php echo esc_html( $rating_count ); ?>)</span>
 </div>
 <?php endif; ?>

 <div class="fs-5 fw-bold text-dark mt-2 product-price">
 <?php echo wp_kses_post( $product->get_price_html() ); ?>
 </div>

 <div class="mt-3">
 <?php woocommerce_template_loop_add_to_cart(); ?>
 </div>
 </div>
 </div>
 </div>
 <?php endwhile; ?>
 </div>

 <div class="mt-5 d-flex justify-content-center">
 <?php woocommerce_pagination(); ?>
 </div>
 <?php wc_reset_loop(); ?>
 <?php else : ?>
 <?php do_action( 'woocommerce_no_products_found' ); ?>
 <?php endif; ?>
 </div>
</div>
<?php
$flexible_source = null;
$flexible_post = null;

if ( is_shop() && function_exists( 'wc_get_page_id' ) ) {
 $shop_id = wc_get_page_id( 'shop' );

 if ( $shop_id && $shop_id >0 ) {
 $flexible_source = $shop_id;
 $maybe_post = get_post( $shop_id );

 if ( $maybe_post instanceof WP_Post ) {
 $flexible_post = $maybe_post;
 }
 }
} elseif ( is_product_taxonomy() ) {
 $flexible_source = get_queried_object();
}

if ( $flexible_source && function_exists( 'have_rows' ) && have_rows( 'body', $flexible_source ) ) :
 global $post;
 $original_post = isset( $post ) && $post instanceof WP_Post ? $post : null;

 if ( $flexible_post instanceof WP_Post ) {
 $post = $flexible_post;
 setup_postdata( $post );
 }
 ?>
 <section class="shop-flexible-content py-5">
 <div class="container">
 <article class="blog-post">
 <?php
 while ( have_rows( 'body', $flexible_source ) ) :
 the_row();
 include get_theme_file_path( '/flixable.php' );
 endwhile;
 ?>
 </article>
 </div>
 </section>
 <?php
 if ( $flexible_post instanceof WP_Post ) {
 if ( $original_post instanceof WP_Post ) {
 $post = $original_post;
 setup_postdata( $post );
 } else {
 wp_reset_postdata();
 }
 }
endif;
