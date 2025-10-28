<?php get_header(); // This fxn gets the header.php file and renders it ?>

<?php get_template_part( 'template-parts/page/hero' ); ?>

<!-- flexible content -->

<?php
$queried = get_queried_object();
$post_id = null;

// Determine a suitable ID/identifier for use with have_rows().
if ( is_object( $queried ) && isset( $queried->ID ) ) {
 $post_id = $queried->ID;
} else {
 $post_id = $queried;
 }

// If ACF is available and the flexible content exists for this object, use it.
if ( function_exists( 'have_rows' ) && have_rows( 'body', $post_id ) ) : ?>

<article class="blog-post"> 

<?php while( have_rows('body', $post_id) ): the_row(); ?>
<?php include get_theme_file_path( '/flixable.php' ); //load flexible content ?>
<?php endwhile; ?>

</article> 

<?php else : ?>

<!-- Fallback: output the normal page content (this will render WooCommerce shortcodes like [woocommerce_cart]) -->
<article class="blog-post">
 <?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
 <div class="entry-content">
 <?php the_content(); ?>
 </div>
 <?php endwhile; endif; ?>
</article>

<?php endif; ?>

<!-- flexible content --> 

<?php get_footer(); // This fxn gets the footer.php file and renders it ?>