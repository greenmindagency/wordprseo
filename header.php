<?php
	/*-----------------------------------------------------------------------------------*/
	/* This template will be called by all other template files to begin 
	/* rendering the page and display the header/nav
	/*-----------------------------------------------------------------------------------*/
?>
<!DOCTYPE html>
<html lang="en-US">
<head>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">





<?php wp_head(); ?>





<?php if (have_rows('map_locations',2)) : ?>
 <?php while (have_rows('map_locations',2)) : the_row(); ?>
 <?php 
		
		$telephone = get_sub_field('telephone');
		$price_range = get_sub_field('price_range');
		$street_address = get_sub_field('street_address');
		$city = get_sub_field('city');
		$region = get_sub_field('country');
		$country = get_sub_field('country');
?>
 
		
				
<script type="application/ld+json">{"@context":"https://schema.org","@type":"Organization","name":"<?php bloginfo('name'); ?>","url":"<?php echo esc_url(get_home_url()); ?>/","logo":"<?php $image = get_field('logo' ,2); if( !empty($image) ): echo $image['sizes']['large']; endif; ?>","contactPoint":{"@type":"ContactPoint","telephone":"<?php echo esc_html($telephone); ?>","contactType":"Customer Service"},"address":{"@type":"PostalAddress","streetAddress":"<?php echo esc_html($street_address); ?>","addressLocality":"<?php echo esc_html($city); ?>","addressRegion":"<?php echo esc_html($region); ?>"}, "sameAs":[<?php if( have_rows('social_media',2) ) : $links = []; while( have_rows('social_media',2) ) : the_row(); $links[] = get_sub_field('link'); endwhile; echo '"' . implode('", "', $links) . '"'; endif; ?>]}</script>


 
<script type="application/ld+json">{"@context":"https://schema.org","@graph":[{"@type":"LocalBusiness","name":"<?php bloginfo('name'); ?>","image":"<?php $image = get_field('logo' ,2); if( !empty($image) ): echo $image['sizes']['large']; endif; ?>","telephone":"<?php echo esc_html($telephone); ?>","priceRange":"<?php echo esc_html($price_range); ?>","address":{"@type":"PostalAddress","streetAddress":"<?php echo esc_html($street_address); ?>","addressLocality":"<?php echo esc_html($city); ?>","addressRegion":"<?php echo esc_html($region); ?>"}}]}</script>
		
 
 <?php endwhile; ?>
<?php endif; ?>



<?php	// get the tracking repeater

if( have_rows('tracking',2) ):
while( have_rows('tracking',2) ) : the_row();
$headcode = get_sub_field('head_code');
$bodycode = get_sub_field('body_code');
?>

<?php echo $headcode ?>

<?php endwhile; else : endif; //get the tracking repeater ?>




<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.bundle.min.css" rel="stylesheet" xintegrity="sha384-4Q6Gf2aSP4eDXB8Miphtr37CMZZQ5oXLH2yaXMJ2w8e2ZtHTl7GptT4jmndRuHDT" crossorigin="anonymous">




 </head>
 <body>

<?php	// get the tracking repeater

if( have_rows('tracking',2) ):
while( have_rows('tracking',2) ) : the_row();
$headcode = get_sub_field('head_code');
$bodycode = get_sub_field('body_code');
?>

<?php echo $bodycode ?>

<?php endwhile; else : endif; //get the tracking repeater ?>

 	
 	 
 	 
<?php 
// Compute menu_color once and make cart/checkout detection more robust using WC page IDs as fallback.
$menu_color = get_field('menu_color',2); // Get from current page/post

// New variables for classes, styles, and console debugging
$debug_message = '';
$nav_classes = '';
$nav_style = '';
$is_wc_white_page = 'false'; // JS variable for footer

// --- FIX START: Consolidated and hardened WooCommerce page detection to force 'white' menu ---
if ( function_exists( 'is_woocommerce' ) ) {
    $force_white = false;

    // 1. Check for standard WC pages (Shop, Product, Category)
    if (is_shop() || is_product() || is_product_taxonomy()) {
        $force_white = true;
        $debug_message = 'WC Menu: Forced White (Standard Shop/Product/Taxonomy)';
    }
    
    // 2. Check for Cart/Checkout using WooCommerce functions
    if (!$force_white && (is_cart() || is_checkout())) {
        $force_white = true;
        $debug_message = 'WC Menu: Forced White (is_cart() or is_checkout() function)';
    }
    
    // 3. Robust fallback: Check if the current page ID matches the Cart or Checkout page IDs set in WooCommerce settings.
    if (!$force_white && function_exists( 'wc_get_page_id' ) && is_page() ) {
        $cart_id = wc_get_page_id( 'cart' );
        $checkout_id = wc_get_page_id( 'checkout' );
        $current_id = get_the_ID();
        
        if ($current_id == $cart_id || $current_id == $checkout_id) {
            $force_white = true;
            $debug_message = 'WC Menu: Forced White (WC Page ID Match)';
        }
    }
    
    // 4. ULTIMATE FALLBACK: Check the server request URI for /cart/ or /checkout/ slugs.
    // This is the most reliable check as it bypasses potential WordPress query issues.
    if (!$force_white) {
        $request_uri = strtolower( esc_url_raw( $_SERVER['REQUEST_URI'] ) );
        if (strpos($request_uri, '/cart/') !== false || strpos($request_uri, '/checkout/') !== false) {
             $force_white = true;
             $debug_message = 'WC Menu: Forced White (REQUEST_URI Slug Check)';
        }
    }


    if ($force_white) {
        $menu_color = 'white';
        $is_wc_white_page = 'true'; // Set JS variable here
    }
}
// --- FIX END ---

// Map $menu_color to final classes and styles
if ($menu_color == 'black') { 
 $nav_classes = 'menu-dynamic bg-dark text-white';
} elseif ($menu_color == 'transparent') { 
 $nav_classes = 'menu-dynamic bg-transparent text-white';
} elseif ($menu_color == 'white') { 
 $nav_classes = 'menu-dynamic shadow bg-light';
 $nav_style = 'style="background-color: #fff !important;"'; // Hard background color override to fix transparency issues
} else { 
 $nav_classes = '';
}

// START DEBUG LOGGING & JS VARIABLE DEFINITION
echo '<script>';
// Define global flag for footer script
echo 'const isWcWhitePage = ' . $is_wc_white_page . ';';
if (!empty($debug_message)) {
    // Escaping ensures the PHP variable can be safely used within JavaScript.
    echo 'console.warn("[Header Debug] ' . esc_js($debug_message) . '");';
}
echo '</script>';
// END DEBUG LOGGING
?>
<nav class="navbar fixed-top navbar-expand-lg <?php echo $nav_classes; ?>" <?php echo $nav_style; ?>>

	 	 	 	 	 
 	<div class="container-fluid">
 <a class="me-5 navbar-brand my-2" href="<?php bloginfo( 'url' ); ?>">
 
 
<?php 
$logoblack = get_field('logo' ,2);
$logolight = get_field('logo_light' ,2);
 ?>
 
<?php
// Get the image size dynamically
$image = $logoblack;
$size = 'medium';

// Allow logo height to be controlled via ACF
$logo_height = get_field('logo_height',2);
$fixed_height = $logo_height ? intval($logo_height) :40;

// Ensure image data exists
if (!empty($image) && isset($image['sizes'][$size])) {
 $image_url = $image['sizes'][$size];
 $width = isset($image['sizes'][$size . '-width']) ? $image['sizes'][$size . '-width'] : null;
 $height = isset($image['sizes'][$size . '-height']) ? $image['sizes'][$size . '-height'] : null;

 // Calculate proportional width if dimensions are valid
 if ($width && $height) {
 $new_width = round(($fixed_height / $height) * $width);
 }
}

?>


<?php 

// Use the already-computed $menu_color instead of recalculating

if ($menu_color == 'black') { ?>
 
<img class="logo d-inline-block align-top" src="<?php echo $logolight['sizes']['medium']; ?>" width="<?php echo esc_attr($new_width); ?>" height="<?php echo esc_attr($fixed_height); ?>" title="<?php bloginfo('name'); ?> Logo" alt="<?php bloginfo('name'); ?> Logo" />

 <?php } elseif ($menu_color == 'transparent') { ?>
 
 <img class="logo d-inline-block align-top" src="<?php echo $logolight['sizes']['medium']; ?>" width="<?php echo esc_attr($new_width); ?>" height="<?php echo esc_attr($fixed_height); ?>" title="<?php bloginfo('name'); ?> Logo" alt="<?php bloginfo('name'); ?> Logo" />
 
 <?php } else { ?>
 
 <img class="logo d-inline-block align-top" src="<?php echo $logoblack['sizes']['medium']; ?>" width="<?php echo esc_attr($new_width); ?>" height="<?php echo esc_attr($fixed_height); ?>" title="<?php bloginfo('name'); ?> Logo" alt="<?php bloginfo('name'); ?> Logo" />
 
 
 <?php } ?>


 
 
 
 </a>
 <button class="navbar-toggler bg-light" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
 <span class="navbar-toggler-icon"></span>
 </button>
 <div class="collapse navbar-collapse" id="navbarSupportedContent">


 <?php wp_nav_menu( array(
 'theme_location' => 'my-custom-menu',
 'depth' =>2, //1 = no dropdowns,2 = with dropdowns.
 'container' => 'ul',
 'container_class' => 'collapse navbar-collapse',
 'container_id' => 'bs-example-navbar-collapse-1',
 'menu_class' => 'navbar-nav me-auto mb-2 mb-lg-0',
 'fallback_cb' => 'WP_Bootstrap_Navwalker::fallback',
 'walker' => new WP_Bootstrap_Navwalker(),
) ); ?>
	 
	 
 <?php
 $display_alt_language_link = get_field('language',2);
 $display_alt_language_link = $display_alt_language_link === null ? true : (bool) $display_alt_language_link;
 ?>

 <div class="d-flex">
 
 
<form class="is-search-form is-ajax-search me-3" action="<?php echo esc_url(home_url('/')); ?>" method="get" role="search">
 <div class="input-group">
 <input type="search" name="s" class="form-control p-1 ps-3 is-search-input" placeholder="Search" autocomplete="on">
 <button type="submit" class="btn btn-secondary">
 <span>
<i class="text-white fa fa-search"></i>
 </span>
 </button>
 </div>
</form>
 
 
 
 
 
 <!-- shorten url -->
<a class="btn copy-to-clipboard btn-outline-primary bg-light" data-clipboard-text='<?php
function tiny_url($url){
 return file_get_contents("https://tinyurl.com/api-create.php?url=" . $url);
}
$url = "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
echo tiny_url($url);
?>'><i class=" text-dark fa fa-link"></i></a>
<!-- shorten url -->


 <?php if (function_exists('wordprseo_render_header_customer_tools')) : ?>
 <?php echo wordprseo_render_header_customer_tools(); ?>
 <?php endif; ?>

 <?php if ($display_alt_language_link) : ?>
 <a href="<?php bloginfo( 'url' ); ?>/ar/" class="ms-3 btn btn-primary">ع</a>
 <?php endif; ?>


 </div>

 </div>
 </div>




</nav>
