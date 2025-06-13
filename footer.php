<?php wp_footer(); 
// This fxn allows plugins to insert themselves/scripts/css/files (right here) into the footer of your website. 
// Removing this fxn call will disable all kinds of plugins. 
// Move it if you like, but keep it around.
?>


      
 
    <footer class="bg-dark py-5">
	
	
	
		<?php 
    $footer_image = get_field('footer_image', 2);
    if (!empty($footer_image)):
      $image_url = $footer_image['sizes']['large'];
      $size = 'large';
      $width = $footer_image['sizes'][$size . '-width'];
      $height = $footer_image['sizes'][$size . '-height'];
  ?>
    <img loading="lazy" class="img-fluid footer-bg-image" width="<?php echo $width; ?>" height="<?php echo $height; ?>" src="<?php echo $image_url; ?>" alt="<?php echo esc_attr($footer_image['alt']); ?>" />
  <?php endif; ?>
  
  
  <div class="container">
    <div class="row mt-5">
      <div class="col-md-4 mb-5">
	  
	  
	  
        
<?php 
$logolight = get_field('logo_light' , 2);


// Get the image size dynamically
$image = $logolight;
$size = 'medium';

// Ensure image data exists
if (!empty($image) && isset($image['sizes'][$size])) {
    $image_url = $image['sizes'][$size];
    $width = isset($image['sizes'][$size . '-width']) ? $image['sizes'][$size . '-width'] : null;
    $height = isset($image['sizes'][$size . '-height']) ? $image['sizes'][$size . '-height'] : null;
    $fixed_height = 50;

    // Calculate proportional width if dimensions are valid
    if ($width && $height) {
        $new_width = round(($fixed_height / $height) * $width);
    }
}




 ?>
				
				
				<img  loading="lazy" class="logo d-inline-block align-top mb-3" src="<?php echo $logolight['sizes']['medium']; ?>" width="<?php echo esc_attr($new_width); ?>" height="<?php echo esc_attr($fixed_height); ?>" title="<?php bloginfo('name'); ?> Logo" alt="<?php bloginfo('name'); ?> Logo" />
				
        
		
		<p class="text-light font-weight-bold"><?php $title = get_field('title', 2); if ($title) echo esc_html($title); ?></p>
		
		
        <small class="d-block my-3 text-light">© Copyright - <?php bloginfo('name'); ?>  - <?php echo date('Y'); ?></small>
              <ul class="list-inline align-items-center"> 
			  
			  
			  <?php	// repeater

if( have_rows('social_media', 2) ):
while( have_rows('social_media', 2) ) : the_row();
$icon = get_sub_field('icon');
$link = get_sub_field('link');
?>

 <li class="list-inline-item me-1">
			  
			  <a href="<?php echo $link ?>" target="_blank" aria-label="Social Media Link"> <i class="mb-2 me-2 fa-2x fab fa-<?php echo $icon ?>"></i></a>
			  
			  </li>



<?php endwhile; else : endif; // repeater ?>
			  
			  

		
      </ul>
	  
	  	<a href="https://greenmindagency.com" target="_blank">Created by Green Mind Agency</a>
		
		
      </div>
	  
 
	  <div class="col-md-4 mb-5 text-white">
	  
	  		

        
    
<div class="h5 fw-bold pb-2"><?php
$page_id = 320;
$page_title = get_the_title($page_id);
echo esc_html($page_title);
?></div>
                    
                 
                
                    
					
					
<?php if (have_rows('map_locations', 2)): ?>
<?php while (have_rows('map_locations', 2)): the_row(); ?>
  



  <div class="my-2">
    
  
      <?php if ($country = get_sub_field('country')): ?>
      <p class="mt-4 mb-2 fw-bold">
	  
        <?php echo esc_attr($country); ?>
      </p>
    <?php endif; ?>
	
	 
    <?php if ($telephone = get_sub_field('telephone', false)): ?>
      
        <i class="me-2 fa-solid fa-square-phone"></i> <a href="tel:+<?php echo wp_kses_post($telephone); ?>"> +<?php echo wp_kses_post($telephone); ?></a>
    
    <?php endif; ?>

    <?php if ($street_address = get_sub_field('street_address', false)): ?>
      
      <br>  <i class="me-2 fa-solid fa-location-dot"></i> <a href="<?php if ($url = get_sub_field('url')) echo esc_attr($url); ?>" target="blank"><?php echo wp_kses_post($street_address); ?></a>
      
    <?php endif; ?>

	</div>

	
  <?php endwhile; ?>
  
  
<?php endif; ?>

		




		
	  <?php if (have_rows('working_hours', 2)): ?>
<?php while (have_rows('working_hours', 2)): the_row(); ?>


      <div class="mt-5">
        <?php if ($title = get_sub_field('title', false)): ?>
		
		<p class="my-3 h5 fw-bold"><?php echo wp_kses_post($title); ?></p>

		<?php endif; ?>


       <?php if ($working_hours = get_sub_field('working_hours', false)): ?>
		
		<p class="mb-2"><i class="fa-solid fa-clock me-2"></i> <?php echo wp_kses_post($working_hours); ?></p>

		<?php endif; ?>
	  
	  
      </div>
  
	  
  <?php endwhile; ?>
  
 
<?php endif; ?>				
					
					
					
					
                       

	  </div>
	  

      <div class="col-md-4 col-sm-4">
        <p class="h5 fw-bold pb-2 text-white">Links</p>
<ul class="list-unstyled text-small">
    <?php $categories = get_categories(array('orderby' => 'id', 'hide_empty' => 0)); ?>
    <?php foreach ($categories as $category) : ?>
        <?php query_posts(array('category_name' => $category->slug, 'showposts' => '5')); ?>
        <li>
            <i class="text-light fa-solid fa-caret-right me-2"></i> <a class="link-light" href="<?php echo get_category_link($category->term_id); ?>">
                
                <?php echo $category->name; ?>
            </a>
        </li>
    <?php endforeach; ?>
    <?php wp_reset_query(); ?>
</ul>


        <p class="mt-5 h5 fw-bold pb-2 text-white"><?php
// Replace 1 with the actual category ID you want to get the name for
$category_id = 1;

$category_name = get_cat_name( $category_id );

if ( $category_name ) {
  echo "" . $category_name;
} else {
  echo "Category not found.";
}
?></p>
       
          
<?php

$tags = get_terms( array(
    'taxonomy' => 'post_tag',
    'hide_empty' => false,
) );

if ( !empty($tags) && !is_wp_error($tags) ) {
    echo '<ul class="list-unstyled">';
    foreach ( $tags as $tag ) {
        echo '<li><i class="text-light fa-solid fa-caret-right me-2"></i> <a class="link-light" href="' . get_tag_link( $tag->term_id ) . '">' . $tag->name . '</a></li>';
    }
    echo '</ul>';
}
?>

      
	  
	  
      </div>

    </div>
	
	</div>
	
	  <!-- Scroll Top -->
  <a href="#" id="scroll-top" class="scroll-top d-flex align-items-center justify-content-center"><i class="fas fa-chevron-up"></i></a>
	
	
  </footer>
  
  
  

<!-- onload css -->  



<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jquery.colorbox/1.4.31/example1/colorbox.min.css" integrity="sha512-qDmL8zJf49wqgbTQEr0nsThYpyQkjc+ulm2zAjRXd/MCoUBuvd19fP2ugx7dnxtvMOzSJ1weNdSE+jbSnA4eWw==" crossorigin="anonymous" referrerpolicy="no-referrer" />

  
  
<style>


body { <?php 

    $menu_color = get_field('menu_color'); // Retrieve the value of the 'menu_color' field

    if ($menu_color == 'black') { 
        echo 'padding-top: 70px;'; // Example for black background
    } elseif ($menu_color == 'transparent') { 
        echo ''; // Example for transparent background
		} elseif ($menu_color == 'white') { 
        echo 'padding-top: 70px;'; // Example for white background
    } else { 
        echo ''; // Default for white or other
    } 
 
?> font-family: 'Cairo', sans-serif !important;}




:root {
  --bs-primary: <?php $Color = get_field('Color', 2); if ($Color) echo esc_html($Color); ?> !important;
  --bs-secondary: <?php $darkercolor = get_field('darker-color', 2); if ($darkercolor) echo esc_html($darkercolor); ?> !important;
  --bs-success: #198754;
  --bs-info: #0dcaf0;
  --bs-warning: #ffc107;
  --bs-danger: #dc3545;
  --bs-light: #f8f9fa;
  --bs-dark: #212529;

  /* Optional - text colors */
  --bs-body-color: #212529;
  --bs-body-bg: #ffffff;

  /* Optional - link colors */
  --bs-link-color: var(--bs-primary);
  --bs-link-hover-color: var(--bs-secondary);
}





a:link { text-decoration: none;}
a:visited { text-decoration: none;}
a:hover {text-decoration: none; color: var(--bs-secondary) !important}
a:active {text-decoration: none;} 
h1,h2,h3,h4,h5,h6 {font-weight:600 !important} 
     
.card-overlay img, .jarallax-img {opacity: 0.1} 
.jarallax iframe {opacity: 0.5 }

.video-jarallax-content {padding: 8rem 3rem}

.video-jarallax [id^="jarallax-container-"] > div {
    opacity: 0.3 !important; /* fix image of video before playing */
}

.custom-check ul {
  list-style-type: none;   /* Remove bullet */
  padding-left: 0;         /* Remove default UL padding */
  margin: 20px 0;               /* Optional: to clean up spacing */
}

.custom-check ul li {
  position: relative;
  padding-left: 1.3rem;    /* Space for the icon */
  margin-bottom: 5px;
}

.custom-check ul li::before {
  content: "\f0da";        /* FontAwesome check icon */
  font-family: "Font Awesome 6 Free";
  font-weight: 900;        /* Solid icon */
  position: absolute;
  left: 0;
  top: 0.15rem;
  
}

  
/*fix jarallax for pasgecotnet3 */


.jarallax-content .jarallax{
    height: 100%; /* or set a fixed height like 500px */
    position: relative; /* Ensure proper positioning for child elements */
}

.jarallax-content .jarallax-img {
    object-fit: cover; /* Ensures the image covers the container */
    width: 100%; /* Makes the image responsive */
    height: 100%; /* Ensures it fills the parent container */
    position: absolute; /* Makes the image a background-style element */
    top: 0;
    left: 0;
	opacity: 1
}

/*fix jarallax for pasgecotnet3 */



/*fix image height for cat,post,tag connections */


.image-cover-container {
    width: 100%;
    height: 100%; /* Ensures it matches the height of the parent */
    overflow: hidden; /* Prevents image overflow */
    position: relative; /* Keeps the image positioned correctly */
    min-height: 200px; /* Fallback height for smaller screens */
}

.image-cover {
    width: 100%; /* Makes the image span the full width */
    height: 100%; /* Ensures the image spans the full height */
    object-fit: cover; /* Keeps the image proportional while filling the container */
    position: absolute; /* Keeps the image aligned within the container */
    top: 0;
    left: 0;
}

/* Optional: Adjust for smaller screens */
@media (max-width: 768px) {
    .image-cover-container {
        height: auto; /* Allows the container to adjust based on content */
        min-height: 150px; /* Ensures a minimum height for small screens */
    }
    .image-cover {
        position: static; /* Makes the image behave normally for smaller screens */
        height: auto; /* Ensures the image scales naturally */
        object-fit: contain; /* Optional: Ensures the entire image is visible */
    }
}

/*fix image height for cat,post,tag connections */






.carousel-item img { width: 100%; }
       
       /*coloring*/
      body .bg-primary, body .btn-outline-primary:hover, body .btn-primary { color: #fff !important; background-color: <?php $Color = get_field('Color', 2); if ($Color) echo esc_html($Color); ?> !important; border-color: <?php $darkercolor = get_field('darker-color', 2); if ($darkercolor) echo esc_html($darkercolor); ?> !important;}
       .btn-primary:hover, .btn-primary:active, .btn-primary:focus { color: #fff !important; background-color: <?php $darkercolor = get_field('darker-color', 2); if ($darkercolor) echo esc_html($darkercolor); ?> !important; border-color: <?php $darkercolor = get_field('darker-color', 2); if ($darkercolor) echo esc_html($darkercolor); ?> !important; box-shadow:none !important}
       .btn-outline-primary {border-color: <?php $Color = get_field('Color', 2); if ($Color) echo esc_html($Color); ?> !important; color:<?php $darkercolor = get_field('darker-color', 2); if ($darkercolor) echo esc_html($darkercolor); ?> !important}
	   
	   body .bg-secondary, body .btn-outline-secondary:hover, body .btn-secondary { color: #fff !important; background-color: <?php $darkercolor = get_field('darker-color', 2); if ($darkercolor) echo esc_html($darkercolor); ?> !important; border-color: <?php $darkercolor = get_field('darker-color', 2); if ($darkercolor) echo esc_html($darkercolor); ?> !important;}
       .btn-secondary:hover, .btn-secondary:active, .btn-secondary:focus { color: #fff !important; background-color: <?php $Color = get_field('Color', 2); if ($Color) echo esc_html($Color); ?> !important; border-color: <?php $darkercolor = get_field('darker-color', 2); if ($darkercolor) echo esc_html($darkercolor); ?> !important; box-shadow:none !important}
       .btn-outline-secondary {border-color: <?php $Color = get_field('Color', 2); if ($Color) echo esc_html($Color); ?> !important; color:<?php $darkercolor = get_field('darker-color', 2); if ($darkercolor) echo esc_html($darkercolor); ?>}
       
	   
	   .btn-light { color: <?php $darkercolor = get_field('darker-color', 2); if ($darkercolor) echo esc_html($darkercolor); ?> !important;}
	   
       
      a, .page-link, .text-primary, .dropdown .text-primary, .dropdown button, div .text-primary { color: <?php $Color = get_field('Color', 2); if ($Color) echo esc_html($Color); ?> !important;}
      
  
  

  
  
    body .bg-dark {background-color:#000 !important}
  .navbar-light  {background-color:#fff !important}
  
  .dropdown-item.active, .dropdown-item:active { color: #fff  !important; background-color: <?php $Color = get_field('Color', 2); if ($Color) echo esc_html($Color); ?> !important; }
  
   /*nav coloring*/
  .bg-dark .nav-link, .bg-transparent .nav-link { color:#fff!important }
  .bg-light .nav-link { color:#000!important }
  a.nav-link:hover {background-color: var(--bs-primary) !important; color: #fff !important}
 /*nav coloring*/
 
.accordion-button::after {
  display: none !important; /* Completely remove Bootstrap's default arrow */
}

.accordion-button:not(.collapsed) .fa-chevron-down {
  transform: rotate(180deg); /* Flip the icon when open */
  transition: transform 0.3s ease; /* Smooth animation */
}

.accordion-button .fa-chevron-down {
  color: #fff; /* White icon */
}

a.text-white:hover {
  color: var(--bs-primary) !important;
}

.btn, .form-control, .progress, .accordion-button  {border-radius: 0 !important; }


       /*coloring*/
  
  

.blog-post h3, .blog-post h4, .blog-post h5, .blog-post h6 {line-height: 2.1rem;  margin-top: 2rem; margin-bottom: .8rem;} 
  .imgwidthfull {width:100%}
  a.anchor { display: block; position: relative;  top: -70px; visibility: hidden}
  
  
blockquote { margin: 50px 0 !important; line-height: 2.1rem;font-size: calc(1.3rem);} 
blockquote:before { font-family: "Font Awesome 5 Free"; content: "\f10d"; display: inline-block; padding-right: 3px; vertical-align: middle; font-weight: 900; font-size: 2.1rem }
blockquote p {margin:1rem 0} 
  
  
  
  
.jarallax { position: relative; z-index: 0; }
.jarallax > .jarallax-img {  position: absolute;  object-fit: cover; font-family: 'object-fit: cover;'; top: 0; left: 0; width: 100%; height: 100%; z-index: -1; }

.py-spacer {padding-top: 5rem !important; padding-bottom: 5rem !important}  
.px-spacer {padding-left: 5rem !important; padding-right: 5rem !important}  

.image-fill {object-fit: cover; width: 100%; height: 100%;}

.cover-font {    font-size: calc(.8rem + 3.4vw); }
.carousel img, .slider img {opacity:1}
.img-full {width: 100%;}
.breadcrumb { margin-bottom: 0rem !important }

.screen-reader-response {  display: none; }
.wpcf7-mail-sent-ok {background-color: #32CD32; padding: 1rem; color: #fff;font-weight: bold;}
	
	
	
	//////////////* infinite scroll  
  
  .scroller-status{ position: fixed;  bottom: 0; display:none; z-index:1 }
  /* reveal grid after images loaded */
.grid.are-images-unloaded {
  opacity: 0;
}

.grid__col-sizer {
  width: 33.333333% !important 
}

.grid__col-sizer-wide {
  width: 50% !important 
}


.grid__gutter-sizer { width: 1% !important }

/* hide by default */
.grid.are-images-unloaded .image-grid-item {
  opacity: 0;
}

.grid-item {
  
  float: left;
}

*/
  
  /* loader-ellips
------------------------- */

.loader-ellips {
	
  font-size: 20px; /* change size here */
  position: relative;
  width: 4em;
  height: 1em;
  margin: 10px auto;
}

.loader-ellips__dot {
  display: block;
  width: 1em;
  height: 1em;
  border-radius: 0.5em;
  background: #555; /* change color here */
  position: absolute;
  animation-duration: 0.5s;
  animation-timing-function: ease;
  animation-iteration-count: infinite;
}

.loader-ellips__dot:nth-child(1),
.loader-ellips__dot:nth-child(2) {
  left: 0;
}
.loader-ellips__dot:nth-child(3) { left: 1.5em; }
.loader-ellips__dot:nth-child(4) { left: 3em; }

@keyframes reveal {
  from { transform: scale(0.001); }
  to { transform: scale(1); }
}

@keyframes slide {
  to { transform: translateX(1.5em) }
}

.loader-ellips__dot:nth-child(1) {
  animation-name: reveal;
}

.loader-ellips__dot:nth-child(2),
.loader-ellips__dot:nth-child(3) {
  animation-name: slide;
}

.loader-ellips__dot:nth-child(4) {
  animation-name: reveal;
  animation-direction: reverse;
}
 //////////////* infinite scroll */  
 
  .imgcarousel .carousel img {       -webkit-mask-image: none !important;
    mask-image: none !important; }
	
	
 .imgcarousel .carousel-control-prev, .imgcarousel .carousel-control-next {text-shadow: 0px 0px 10px rgba(0, 0, 0, 1);}
 
 .imgcarousel .carousel img {
    opacity: 1;
}
 
 .imgcarousel .carousel-item img {
    /* Override the mask-image with the desired gradient */
    -webkit-mask-image: linear-gradient(to top, transparent 0%, #000 0%) !important;
    mask-image: linear-gradient(to top, transparent 0%, #000 0%) !important;
}

.slider-animation .carousel-item::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(to top, rgba(0, 0, 0, 1) 0%, rgba(0, 0, 0, .3) 30%, transparent 100%);
    z-index: 1;
    pointer-events: none; /* Ensure the overlay does not interfere with interactions */
}

.slider-animation .carousel-item::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(to bottom, rgba(0, 0, 0, .5) 0%, rgba(0, 0, 0, .1) 20%, transparent 100%);
    z-index: 1;
    pointer-events: none; /* Ensure the overlay does not interfere with interactions */
}


 .slider-animation .carousel-caption { z-index: 2;}


.slider-height-large img {
    object-fit: cover; height: 48rem; object-position: center center;
}

.slider-height-medium img {
    object-fit: cover; height: 34rem; object-position: center center;
}

.slider-height-small img {
    object-fit: cover; height: 21rem; object-position: center center;
}




/*--------------------------------------------------------------
# Scroll Top Button
--------------------------------------------------------------*/
.scroll-top {
  position: fixed;
  visibility: hidden;
  opacity: 0;
  right: 15px;
  bottom: -15px;
  z-index: 99999;
  background-color: <?php $darkercolor = get_field('darker-color', 2); if ($darkercolor) echo esc_html($darkercolor); ?> !important;
  width: 44px;
  height: 44px;
  transition: all 0.4s;
}

.scroll-top i {
  font-size: 24px;
  color: #fff !important;
  line-height: 0;
}

.scroll-top:hover {
  background-color: <?php $Color = get_field('Color', 2); if ($Color) echo esc_html($Color); ?> !important;
  color: #fff;
}

.scroll-top.active {
  visibility: visible;
  opacity: 1;
  bottom: 15px;
}


.postsrelatedtagslider .carousel-indicators .active, .testimonial .carousel-indicators .active, .articleimages  .carousel-indicators active, .pagecontent9 .carousel-indicators active {
    background-color: black; /* Change the active indicator to black */
}

.postsrelatedtagslider .carousel-indicators li, .testimonial .carousel-indicators li, .articleimages  .carousel-indicators li, .pagecontent9 .carousel-indicators li   {
    background-color: rgba(0, 0, 0, 1); /* Change the inactive indicators to a semi-transparent black */
}
	
	.postsrelatedtagslider .carousel-item img, .testimonial .carousel-item img {
    -webkit-mask-image: none !important; opacity:1 !important
}

.wpcf7-spinner {display:none}
	
	
.ratio-9x16 {
    --bs-aspect-ratio: 177.78%; /* (16/9) * 100 */
}
	
	/* page content8 */	
	
	.pagecontent8 .accordion-button {border: 1px solid #fff !important;}
	
		/* page content8 */
	
	/* page content9 */
	
	.pagecontent9 .hover-opacity {
      transition: transform 0.4s ease-in-out, opacity 0.4s ease-in-out;
    }
    .pagecontent9 .hover-box:hover .hover-opacity {
      transform: translateX(10px);
    }
    .pagecontent9 .parallax-card {
      height: auto;
      position: relative;
      overflow: hidden;
    }
    .pagecontent9 .jarallax {
      position: absolute;
      inset: 0;
      z-index: 0;
    }
    .pagecontent9 .jarallax-img {
      object-fit: cover;
      width: 100%;
      height: 100%;
    }
    .pagecontent9 .hover-box .card-overlay {
      position: relative;
      z-index: 1;
      background-color: var(--bs-secondary);
      opacity: 0.85;
      transition: opacity 0.3s ease-in-out;
    }
    .pagecontent9 .hover-box:hover .card-overlay {
      opacity: 0.95;
    }
    .pagecontent9 .hover-paragraph {
      opacity: 0;
      max-height: 0;
      overflow: hidden;
      transition: all 0.5s ease-in-out;
    }
    .pagecontent9 .hover-box:hover .hover-paragraph {
      opacity: 1;
      max-height: 1000px;
      margin-top: 0.5rem;
    }
    .pagecontent9 .hover-link-container {
      position: relative;
      margin-top: auto;
    }
    .pagecontent9 .divider-line {
      width: 0;
      height: 5px;
      background: var(--bs-primary);
      transition: width 0.5s ease;
      margin: 0.75rem 0;
    }
    .pagecontent9 .hover-box:hover .divider-line {
      width: 60px;
    }
    .pagecontent9 .logo-overlay {
      position: relative;
      z-index: 2;
      display: block;
    }
    .pagecontent9 a.text-warning {
      color: var(--primary) !important;
    }
	
	/* page content9 */
	
	
	
/* Dynamic slider  Animations */
        .slider-animation .carousel-item img {
            animation: var(--animation) 7s ease-in-out forwards;
        }

        /* Keyframes for animations */
        @keyframes zoomInRight {
            0% {
                transform: scale(1) translateX(0);
            }
            100% {
                transform: scale(1.1) translateX(20px);
            }
        }

        @keyframes zoomInLeft {
            0% {
                transform: scale(1) translateX(0);
            }
            100% {
                transform: scale(1.1) translateX(-20px);
            }
        }

        @keyframes zoomInUp {
            0% {
                transform: scale(1) translateY(0);
            }
            100% {
                transform: scale(1.1) translateY(-20px);
            }
        }

        @keyframes zoomInDown {
            0% {
                transform: scale(1) translateY(0);
            }
            100% {
                transform: scale(1.1) translateY(20px);
            }
        }

        /* Smooth sequential animation for text */
        .slider-animation .slider-title {
            animation: fadeInTitle 1.5s ease-in-out forwards;
        }

        .slider-animation .slider-subtitle {
            animation: fadeInSubtitle 2.5s ease-in-out forwards;
        }

        @keyframes fadeInTitle {
            0% {
                opacity: 0;
                transform: translateY(-20px);
            }
            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInSubtitle {
            0% {
                opacity: 0;
                transform: translateY(20px);
            }
            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }
		
/* Dynamic slider  Animations */	


.carousel-caption {
    
    bottom: 8rem  !important ;
    left: 15%  !important ;
        text-align: left !important;
    width: 60%; !important
}

.slider .carousel-indicators {
    
    bottom: 30px  !important ;
    
}

/* styling the dropdown */

.dropdown-menu {
    border-radius: 0 !important;  /* Removes curvy edges, making them sharp */
    box-shadow: none !important; /* Removes any shadow for a flat look */
    border: 0px      /* Optional: Add a subtle border for definition */
}


.dropdown-menu li:not(:last-child) {
    border-bottom: 1px solid #f4f4f4;  /* Applies border to all except the last item */
    padding: 5px 5px;
}
.dropdown-menu li:last-child {
    padding: 5px 5px;
}

/* styling the dropdown */

/* card styling */	

.card, .card img {border: 0px !important; border-radius: 0px !important; }

/* card styling */

	/* CSS for hover dropdown on desktop */
@media (min-width: 992px) {
  .navbar .dropdown:hover > .dropdown-menu {
    display: block;
  }
}

/* CSS for toggling dropdown on mobile */
@media (max-width: 991px) {
  .navbar .dropdown-menu {
    display: none;
  }
  .navbar .dropdown.show .dropdown-menu {
    display: block;
  }
}

/* CSS for toggling dropdown on mobile */

  .verticaltabs .nav-pills .active {
    background-color: <?php $darkercolor = get_field('darker-color', 2); if ($darkercolor) echo esc_html($darkercolor); ?> !important;
    color: white !important;
  }
  
  
    .verticaltabs img {
    width: 100%;
    height: 200px; /* Adjust the height as needed for cropping */
    object-fit: cover;
  }
  
  
  .btn, .form-control {border-radius: 0 !important; }
  
  
  /** footer **/
  
  footer {position: relative; overflow: hidden;}
  
  .footer-bg-image {
      position: absolute;
      top: 2rem;
      right: -7rem;
      width: 40rem;
      opacity: 0.1;
      object-fit: contain;
      z-index: 0;
      pointer-events: none;
    }

  
  /** footer **/
  
	
/* isotope mobile filter sidebar */

  .filter-sidebar {
    overflow-y: auto;
    -webkit-overflow-scrolling: touch; /* Smooth scrolling on iOS */
    position: fixed;
    top: 80px;
    right: -250px;
    width: 250px;
    height: 100%;
    background: #fff;
    box-shadow: -2px 0 5px rgba(0, 0, 0, 0.5);
    transition: right 0.3s ease;
    z-index: 1200;
  }

  .filter-sidebar.active {
    right: 0;
  }

  .filter-sidebar .close-btn {
    background: none;
    border: none;
    font-size: 24px;
    padding: 10px;
    cursor: pointer;
  }

/* isotope mobile filter sidebar */
	
	
	

	
</style>
   
<?php if ( is_user_logged_in() ) { // Fixes the top bar position for logged-in users ?>
   
  <style>
    body { padding-top: 55px; }
    .navbar { margin-top: 30px; }
    #wpadminbar { height: 30px !important; min-width: auto !important; position: fixed !important;  }
#wpadminbar a {color: white !important;}
  </style>

<?php } ?> 

  
<!-- fonts and icons -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@200..1000&display=swap" rel="stylesheet">

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css" integrity="sha512-1cK78a1o+ht2JcaW6g8OXYwqpev9+6GqOkz9xmBN9iUUhIndKtxwILGWYOSibOKjLsEdjyjZvYDq/cZwNeak0w==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <!-- Bootstrap CSS -->
<script src="<?php bloginfo('template_directory'); ?>/style.css"></script>


    <!-- jQuery (necessary for Bootstrap's JavaScript plugins) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js" integrity="sha512-bLT0Qm9VnAYZDflyKcBaQ2gg0hSYNQrJ8RilYldYQ1FxQYoCLtUjuuRuZo+fjqhx/qtq/1itJ0C2ejDxltZVFg==" crossorigin="anonymous"></script>
  
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js" integrity="sha384-j1CDi7MgGQ12Z7Qab0qlWQ/Qqz24Gc6BM0thvEMVjHnfYGF0rmFCozFSxQBxwHKO" crossorigin="anonymous"></script>

    
<script src="https://cdnjs.cloudflare.com/ajax/libs/masonry/4.2.2/masonry.pkgd.min.js" integrity="sha512-JRlcvSZAXT8+5SQQAvklXGJuxXTouyq8oIMaYERZQasB8SBDHZaUbeASsJWpk0UUrf89DP3/aefPPrlMR1h1yQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    

<script src="https://cdnjs.cloudflare.com/ajax/libs/clipboard.js/1.4.0/clipboard.min.js" integrity="sha512-iJh0F10blr9SC3d0Ow1ZKHi9kt12NYa+ISlmCdlCdNZzFwjH1JppRTeAnypvUez01HroZhAmP4ro4AvZ/rG0UQ==" crossorigin="anonymous"></script>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jarallax/1.12.5/jarallax.min.js" integrity="sha512-DI98Iva+99hqdsd+etSf/W9iJcmz5jornxiWr5nkr/kcKWlaCDwIsWW6AGxXu/X5u/yylsLYJowdPzIcLUDklw==" crossorigin="anonymous"></script>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jarallax/1.12.5/jarallax-video.min.js" integrity="sha512-lI/p03BLuV8qpkrf/tGCwqJSLwRmPTPvsOEv8LVs2gaSS17/fNiaCxowRK2zooDdf5r7C04rRmnPAMnbXWcVPA==" crossorigin="anonymous"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/lazysizes/5.3.0/lazysizes.min.js" integrity="sha512-JrL1wXR0TeToerkl6TPDUa9132S3PB1UeNpZRHmCe6TxS43PFJUcEYUhjJb/i63rSd+uRvpzlcGOtvC/rDQcDg==" crossorigin="anonymous"></script>
	
	
	<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.colorbox/1.4.31/jquery.colorbox-min.js" integrity="sha512-vIQFGcvtyG7utDhKtdtJB8gZLnFdQramGsSaRNQ7yNWAnvRYAks5H2IQgvXzR8xzaSeqdcss6ZrzebbHdLECLQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
	
	<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-infinitescroll/4.0.1/infinite-scroll.pkgd.min.js" integrity="sha512-R50vU2SAXi65G4oojvP1NIAC+0WsUN2s+AbkXqoX48WN/ysG3sJLiHuB1yBGTggOu8dl/w2qsm8x4Xr8D/6Hug==" crossorigin="anonymous"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.isotope/3.0.6/isotope.pkgd.min.js" integrity="sha512-Zq2BOxyhvnRFXu0+WE6ojpZLOU2jdnqbrM1hmVdGzyeCa1DgM3X5Q4A/Is9xA1IkbUeDd7755dNNI/PzSf2Pew==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js" integrity="sha512-A7AYk1fGKX6S2SsHywmPkrnzTZHrgiVT7GcQkLGDe2ev0aWb8zejytzS8wjo7PGEXKqJOrjQ4oORtnimIRZBtw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

<script src="https://unpkg.com/lenis@1.3.1/dist/lenis.min.js"></script> 


    <script>
      
// carousel_homepage lazy load
$(function() {
    $('.carousel.lazy-load').bind('slide.bs.carousel', function (e) {
        var image = $(e.relatedTarget).find('img[data-src]');
        image.attr('src', image.data('src'));
        image.removeAttr('data-src');
    });
});
      
// add color to the images that has lazyloading with lazysizes
$("img.lazyload").css("background-color","#f8f9fa");

// fix each image height and width to stop the CLS
$('img.lazyload').each(function(i,j){
    var h = $(j).attr( 'height' );
    var w = $(j).attr( 'width' );
    var at = `data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 ${w} ${h}'%3E%3C/svg%3E`;
    $(j).attr('src', at);
});      
      
// copy to clipboard
 var clipboard = new Clipboard('.btn.copy-to-clipboard');
    clipboard.on('success', function(e) {
        console.log(e);
    });
    clipboard.on('error', function(e) {
        console.log(e);
    });
      
      
// adding class to fix the pagination
$(document).ready(function() {
$('.pagination .page-item a, .pagination .page-item span ').addClass('page-link');
});


// TOC generator
jQuery.tableOfContents =                                                                   
function (tocList) {                                                                   
    jQuery(tocList).empty();                                                            
    var prevH2Item = null;                                                             
    var prevH2List = null;                                                             
    
    var index = 0;                                                                     
    jQuery(".blog-post h3, .blog-post h4, .blog-post h5, .blog-post h6").each(function() {                                                      
    
        var anchor = "<a class='anchor' id=" + jQuery(this).text().replace(/\ /g, '-') + "></a>";                                   
        jQuery(this).before(anchor);                                                        
        
        var li     = "<li><a href='#" + jQuery(this).text().replace(/\ /g, '-') + "'>" + 
                     jQuery(this).text() + "</a></li>";   
        
        if( jQuery(this).is(".blog-post h3") ){                                                        
            prevH2List = jQuery("<ul></ul>");                                               
            prevH2Item = jQuery(li);                                                        
            prevH2Item.append(prevH2List);                                             
            prevH2Item.appendTo(tocList);                                              
        } else {                                                                       
            prevH2List.append(li);                                                     
        }                                                                              
        index++;                                                                       
    });                                                                                
}    
$(document).ready(function() { //run the toc generator 
    jQuery.tableOfContents("#tocList"); 
});  


// This script is fixing the scroll to # from url
if ( window.location.hash ) scroll(0,0);
// fixing the scroll to # from url void some browsers issue
setTimeout( function() { scroll(0,0); }, 1);
$(function() {

    // your current click function
    $('.scroll').on('click', function(e) {
        e.preventDefault();
        $('html, body').animate({
            scrollTop: $($(this).attr('href')).offset().top + 'px'
        }, 1000, 'swing');
    });

    // *only* if we have anchor on the url
    if(window.location.hash) {

        // smooth scroll to the anchor id
        $('html, body').animate({
            scrollTop: $(window.location.hash).offset().top + 'px'
        }, 1000, 'swing');
    }

});      
     

// get-img-alt-tag-and-display-under-image-as-html
$.each($(".img"), function() {
    var altText = $(this).attr('alt');
    
    $(this).next().html(altText);
});


// Greenmind custom script for Contact Form 7 and Google conversions

document.addEventListener('DOMContentLoaded', function () {
  document.addEventListener('wpcf7mailsent', function (event) {
    // Optional: Check for a specific form ID if you have multiple forms
    if (event.detail.contactFormId == 229) { 
      window.location.href = "/contacts-thank-you";
      console.log("conversion count");
    }
  }, false);
});







//colorbox

// Image links displayed as a group
$('.articleslideshowgroup').colorbox({rel:'articleslideshowgroup', transition:"fade", slideshow:true, maxWidth:'95%', maxHeight:'95%'});





///infinite scroll
   
// Initialize Masonry
let $grid = $('.grid').masonry({
  itemSelector: '.grid-item',
  percentPosition: true,
  visibleStyle: { transform: 'translateY(0)', opacity: 1 },
  hiddenStyle: { transform: 'translateY(100px)', opacity: 0 },
});

// Initial items reveal
$grid.imagesLoaded(function () {
  $grid.masonry('layout'); // Ensure the initial layout is correct
});

// Initialize Infinite Scroll
$grid.infiniteScroll({
  path: '.pagination__next', // Define pagination path
  append: '.grid-item',      // Define appended items
  outlayer: $grid.data('masonry'), // Pass the Masonry instance
  status: '.scroller-status', // Add loading status
  history: false              // Disable browser history updates
});

// Fix layout after appending new items
$grid.on('append.infiniteScroll', function (event, response, path, items) {
  // Ensure images are loaded before layout
  $(items).imagesLoaded(function () {
    $grid.masonry('appended', items); // Notify Masonry of new items
  });
});


///infinite scroll





// add form control to file in the career page 

$(document).ready(function() {
  $('.wpcf7-file').addClass('form-control');
});


  /**
   * Scroll top button
   */
  let scrollTop = document.querySelector('.scroll-top');

  function toggleScrollTop() {
    if (scrollTop) {
      window.scrollY > 100 ? scrollTop.classList.add('active') : scrollTop.classList.remove('active');
    }
  }
  scrollTop.addEventListener('click', (e) => {
    e.preventDefault();
    window.scrollTo({
      top: 0,
      behavior: 'smooth'
    });
  });

  window.addEventListener('load', toggleScrollTop);
  document.addEventListener('scroll', toggleScrollTop);
  
  
  
             
    /****** progress-bar	******/	


document.addEventListener('DOMContentLoaded', function() {
    var progressBars = document.querySelectorAll('.progress-bar');

    progressBars.forEach(function(progressBar) {
      var valueNow = progressBar.getAttribute('aria-valuenow');
      var valueMin = progressBar.getAttribute('aria-valuemin');
      var valueMax = progressBar.getAttribute('aria-valuemax');

      // Calculate width percentage
      var widthPercentage = ((valueNow - valueMin) / (valueMax - valueMin)) * 100;

      // Set the width of the progress bar
      progressBar.style.width = widthPercentage + '%';
    });
  });

/****** progress-bar	******/	


// Adds spacing classes for white background content

jQuery(document).ready(function($) {
    // Select all .contentfield elements
    const contentfields = $('.contentfield');

    // Variables to track the sequence of consecutive contentfields
    let group = [];
    contentfields.each(function(index, element) {
        const $el = $(element);
        const isPreviousContentfield = $el.prev('.contentfield').length > 0;

        if (isPreviousContentfield || group.length === 0) {
            // Add the current element to the group
            group.push($el);
        } else {
            // Process the group and reset it
            processGroup(group);
            group = [$el];
        }
    });

    // Process the last group if it exists
    if (group.length) {
        processGroup(group);
    }

    // Function to process a group of consecutive contentfields
    function processGroup(group) {
        group.forEach(($el, i) => {
            $el.removeClass('mt-5 my-3 mb-5'); // Clean up existing classes

            if (group.length === 1) {
                // If there's only one element in the group, add both start and end classes
                $el.addClass('mt-5 pt-3 mb-5 pb-3');
            } else if (i === 0) {
                $el.addClass('mt-5 pt-3 mb-5'); // First element
            } else if (i === group.length - 1) {
                $el.addClass('mb-5 pb-3'); // Last element
            } else {
                $el.addClass('my-5'); // Middle elements
            }
        });
    }
});


// Adds spacing classes for white background content
 

      /** Dynamic slider  Animations */
	  
	  
	  
document.addEventListener("DOMContentLoaded", () => {
            const slides = document.querySelectorAll(".slider-animation .carousel-item img");
            const animations = ["zoomInRight", "zoomInLeft", "zoomInUp", "zoomInDown"];

            slides.forEach((slide) => {
                const randomAnimation = animations[Math.floor(Math.random() * animations.length)];
                slide.style.setProperty("--animation", randomAnimation);
            });
        });
      
/** Dynamic slider  Animations */

/** Isotope Filtering with Active Button Styling */
jQuery(document).ready(function($) {
    var $container = $('.posts-container').isotope({
        itemSelector: '.post-item',
        layoutMode: 'fitRows'
    });

    $('.filter-buttons button').on('click', function() {
        var filterValue = $(this).attr('data-filter');

        // Isotope filtering
        $container.isotope({ filter: filterValue });

        // Button class toggle
        $('.filter-buttons button').removeClass('btn-primary').addClass('btn-outline-primary');
        $(this).addClass('btn-primary').removeClass('btn-outline-primary');
    });
});



//mobile filter sidebar

  jQuery(document).ready(function($) {
    $('#openFilter').on('click', function() {
      $('#filterSidebar').addClass('active');
    });

    $('#closeFilter').on('click', function() {
      $('#filterSidebar').removeClass('active');
    });

    $(document).on('click', function(e) {
      if (!$(e.target).closest('#filterSidebar, #openFilter').length) {
        $('#filterSidebar').removeClass('active');
      }
    });
  });
  
  
    //insure masonry 
  
jQuery(document).ready(function($) {
    var $container = $('.posts-container').isotope({
        itemSelector: '.post-item',
        layoutMode: 'masonry',
        percentPosition: true,
    });

    // Filter functionality
    $('.filter-buttons button').on('click', function() {
        var filterValue = $(this).attr('data-filter');
        $container.isotope({ filter: filterValue });

        // Ensure Masonry re-layouts after filtering
        $container.imagesLoaded().progress(function() {
            $container.isotope('layout');
        });
    });
});
  //insure masonry 
  
  
/** Isotope Filtering with Active Button Styling */

/** add the highlight effect for transperent nav bar */

<?php 
    $menu_color = get_field('menu_color', 2); // Retrieve the value of the 'menu_color' field

    if ($menu_color == 'transparent') { 
?>


document.addEventListener('DOMContentLoaded', function () {
    const navbar = document.querySelector('.navbar');
    const logo = document.querySelector('.logo');

    // Define the URLs directly using PHP
    const lightLogoSrc = "<?php $logolight = get_field('logo_light', 2); echo $logolight['sizes']['medium']; ?>";
    const blackLogoSrc = "<?php $logoblack = get_field('logo', 2); echo $logoblack['sizes']['medium']; ?>";

    // Preload both images
    const lightLogoImg = new Image();
    lightLogoImg.src = lightLogoSrc;

    const blackLogoImg = new Image();
    blackLogoImg.src = blackLogoSrc;

    // Add smooth transition for navbar only
    navbar.style.transition = 'background-color 0.3s ease-in-out, box-shadow 0.3s ease-in-out';

    // Function to switch navbar and logo styles on scroll
    function handleScroll() {
        if (window.scrollY > 50) {
            navbar.classList.add('shadow', 'bg-light');
            navbar.classList.remove('bg-dark', 'text-white', 'bg-transparent');
            if (logo.src !== blackLogoImg.src) {
                logo.src = blackLogoImg.src;  // Switch to black logo without fade
            }
        } else {
            navbar.classList.remove('shadow', 'bg-light');
            navbar.classList.add('text-white', 'bg-transparent');
            if (logo.src !== lightLogoImg.src) {
                logo.src = lightLogoImg.src;  // Switch to light logo without fade
            }
        }
    }

    // Function to handle hover effect
    function handleMouseEnter() {
        navbar.classList.add('shadow', 'bg-light');
        navbar.classList.remove('bg-dark', 'text-white', 'bg-transparent');
        if (logo.src !== blackLogoImg.src) {
            logo.src = blackLogoImg.src;  // Switch to black logo on hover without fade
        }
    }

    function handleMouseLeave() {
        if (window.scrollY <= 50) {
            navbar.classList.remove('shadow', 'bg-light');
            navbar.classList.add('text-white', 'bg-transparent');
            if (logo.src !== lightLogoImg.src) {
                logo.src = lightLogoImg.src;  // Revert to light logo when not scrolled without fade
            }
        }
    }

    // Attach scroll event listener
    window.addEventListener('scroll', handleScroll);

    // Attach hover event listeners
    navbar.addEventListener('mouseenter', handleMouseEnter);
    navbar.addEventListener('mouseleave', handleMouseLeave);
});




<?php 
    } // End of 'transparent' condition

?>

/** add the highlight effect for transperent nav bar */


/** submenu items open when hover */


document.addEventListener('DOMContentLoaded', function() {
  const navLinks = document.querySelectorAll('.navbar .dropdown');
  const isMobile = () => window.innerWidth <= 991;

  navLinks.forEach(link => {
    const toggle = link.querySelector('a.nav-link');

    toggle.addEventListener('click', function(e) {
      if (isMobile()) {
        e.preventDefault();
        const menu = link.querySelector('.dropdown-menu');
        if (link.classList.contains('show')) {
          link.classList.remove('show');
          menu.style.display = 'none';
        } else {
          link.classList.add('show');
          menu.style.display = 'block';
        }
      }
    });
  });
});

/** submenu items open when hover */
		


/** AOS (Animate On Scroll) */

AOS.init({
  
  once: true,   // To prevent repeated animations

});

/** AOS (Animate On Scroll) */
	


/** Lenis */

// Initialize Lenis
const lenis = new Lenis({
  autoRaf: true,
});

/** Lenis */



/** google maps */


(g => {
      var h, a, k, p = "The Google Maps JavaScript API", c = "google", l = "importLibrary", q = "__ib__", m = document, b = window;
      b = b[c] || (b[c] = {});
      var d = b.maps || (b.maps = {}), r = new Set, e = new URLSearchParams, u = () => h || (h = new Promise(async (f, n) => {
        await (a = m.createElement("script"));
        e.set("libraries", [...r] + "");
        for (k in g) e.set(k.replace(/[A-Z]/g, t => "_" + t[0].toLowerCase()), g[k]);
        e.set("callback", c + ".maps." + q);
        a.src = `https://maps.${c}apis.com/maps/api/js?` + e;
        d[q] = f;
        a.onerror = () => {
          h = n(Error(p + " could not load."));
          console.error("Google Maps script failed to load.");
        };
        a.nonce = m.querySelector("script[nonce]")?.nonce || "";
        m.head.append(a);
      }));
      d[l] ? console.warn(p + " only loads once. Ignoring:", g) : d[l] = (f, ...n) => r.add(f) && u().then(() => d[l](f, ...n));
    })({ key: "AIzaSyDCcN7YSVH-DXRBtmOTLTskLvN2LBAee9o", v: "weekly" });

    async function initMap() {
      try {
        const { Map } = await google.maps.importLibrary("maps");
        const { AdvancedMarkerElement } = await google.maps.importLibrary("marker");

        const maps = document.querySelectorAll('.map');

        if (!maps.length) {
  return;
}




<?php
$locations = get_field('map_locations', 2);
$total = is_array($locations) ? count($locations) : 0;
$sum_lat = 0;
$sum_lng = 0;
?>

maps.forEach(mapElement => {
  const locations = [
    <?php if ($total):
      $i = 0;
      foreach ($locations as $row):
        $i++;
        $lat = (float) $row['lat'];
        $lng = (float) $row['lng'];
        $country = $row['country'] ?? '';
        $url = $row['url'] ?? '';

        $sum_lat += $lat;
        $sum_lng += $lng;
    ?>
    {
      position: { lat: <?php echo $lat; ?>, lng: <?php echo $lng; ?> },
      icon: "https://cdn-icons-png.flaticon.com/512/484/484167.png",
      url: "<?php echo esc_url($url); ?>",
      title: "<?php echo esc_js($country); ?>"
    }<?php echo $i < $total ? ',' : ''; ?>
    <?php endforeach; endif; ?>
  ];

  const mapCenter = {
    lat: <?php echo $total ? round($sum_lat / $total, 6) : 24.7136; ?>,
    lng: <?php echo $total ? round($sum_lng / $total, 6) : 46.6753; ?>
  };

  const map = new google.maps.Map(mapElement, {
    center: mapCenter,
    zoom: 5,
    mapId: "DEMO_MAP_ID"
  });

  locations.forEach(loc => {
    const iconImage = document.createElement("img");
    iconImage.src = loc.icon;
    iconImage.style.width = "40px";
    iconImage.style.height = "40px";
    iconImage.alt = loc.title || "Map Marker";

    const marker = new google.maps.marker.AdvancedMarkerElement({
      map,
      position: loc.position,
      content: iconImage,
      title: loc.title
    });

    marker.addListener("gmp-click", () => {
      if (loc.url) {
        window.open(loc.url, '_blank');
      }
    });
  });
});





      } catch (error) {
        console.error("Error in initMap:", error);
      }
    }

    function waitForGoogleMaps(retries = 10, delay = 500) {
      if (typeof google !== 'undefined' && google.maps && google.maps.importLibrary) {
        initMap();
      } else if (retries > 0) {
        setTimeout(() => waitForGoogleMaps(retries - 1, delay), delay);
      } else {
        console.error("Google Maps API failed to load after multiple retries.");
      }
    }

    window.addEventListener("load", waitForGoogleMaps);

/** google maps */


    </script>

  </body>
</html>