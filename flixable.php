

<?php if( get_row_layout() == 'articletitle' ): ?>

<div class="articletitle container contentfield custom-check<?php $center = get_sub_field('center'); if( $center && in_array('center', $center) ) { ?> text-center<?php } else { ?><?php } ?>">


<?php 
$icon_class = get_sub_field('articletitleicon'); 
$articletitle = get_sub_field('articletitle');
$description = get_sub_field('description'); 
$articlesubtitle = get_sub_field('articlesubtitle');
 
?>
<?php if ($articletitle): ?>

<h3 class="mb-4 fs-1 fw-bold">
    <?php if ($icon_class): ?>
        <i class="me-4 <?php echo esc_attr($icon_class); ?>"></i>
    <?php endif; ?>
    <?php if ($articletitle): ?>
        <?php echo esc_html($articletitle); ?>
    <?php endif; ?>
</h3>
<?php endif; ?>

<?php if ($articlesubtitle): ?>
    <p class="lead"><?php echo esc_html($articlesubtitle); ?></p>
<?php endif; ?>

<?php if ($description): ?>
   <?php echo wp_kses_post($description); ?>
<?php endif; ?>


</div>  



<?php elseif( get_row_layout() == 'articlevideogallery' ): ?>

<div class="articlevideogallery container contentfield">
  <?php 
  // Get the number of columns from the 'videocolumn' field
  $video_column = get_sub_field('videocolumn') ?>
  <!-- Masonry Layout -->
  <div class="row row-cols-md-<?php echo $video_column; ?> row-cols-1" data-masonry='{"percentPosition": true }'>
    <?php 
    // Loop through the rows of the repeater
    while ( have_rows('articlevideogallery') ) : the_row();
      $videoitem = get_sub_field('videoitem');
      $videosize = get_sub_field('videosize');
    ?>
      <div class="col-md-<?php echo intval(12 / $video_column); ?> col-12 pb-4">
        <div class="ratio ratio-<?php echo $videosize; ?>">
          <iframe data-src="//www.youtube.com/embed/<?php echo $videoitem; ?>" class="lazyload" allowfullscreen></iframe>
        </div>
      </div>
    <?php endwhile; ?>
  </div>
</div>



<?php elseif( get_row_layout() == 'articledescription' ): ?>
<div class="articledescription container contentfield<?php $center = get_sub_field('center'); if( $center && in_array('center', $center) ) { ?> text-center<?php } else { ?><?php } ?>">
<?php 
$articledescription = get_sub_field('articledescription'); 
if ($articledescription): 
    echo wp_kses_post($articledescription); 
endif; 
?>
</div>




<?php elseif( get_row_layout() == 'articlevideo' ): ?>
<?php 
$articlevideosize = get_sub_field('articlevideosize');
$articlevideo = get_sub_field('articlevideo');
?>

<div class="articlevideo container">
    <?php if ($articlevideosize && $articlevideo): ?>
        <div class="mt-3 mb-4 ratio ratio-<?php echo esc_attr($articlevideosize); ?> col-12">
            <iframe 
                data-src="//www.youtube.com/embed/<?php echo esc_attr($articlevideo); ?>" 
                class="my-2 lazyload" 
                allowfullscreen>
            </iframe>
        </div>
    <?php endif; ?>
</div>




<?php elseif( get_row_layout() == 'articleimages' ): //if has images do this?>


<div class="articleimages container contentfield">
<?php 
$layout = get_sub_field('layout');
$imagecolumn = get_sub_field('imagecolumn'); 
$images = get_sub_field('articleimages'); 
$imagesize = get_sub_field('imagesize');
$style = get_sub_field('style');
?>

<?php if( $layout && in_array('slider', $layout) && $images ): ?>

<?php 
global $sliderCounter;
if (!isset($sliderCounter)) {
  $sliderCounter = 1;
}
$total_slides = ceil(count($images) / $imagecolumn);
?>

<div id="slider<?php echo $sliderCounter; ?>" data-bs-pause="false" data-bs-interval="7000" class="carousel slide" data-bs-ride="carousel">

  <!-- Indicators -->
  <div class="carousel-indicators">
    <ul class="list-unstyled d-flex justify-content-center m-0 p-0">
      <?php for($i = 0; $i < $total_slides; $i++): ?>
        <li data-bs-target="#slider<?php echo $sliderCounter; ?>" data-bs-slide-to="<?php echo $i; ?>" <?php if($i == 0) echo 'class="active"'; ?>></li>
      <?php endfor; ?>
    </ul>
  </div>

  <!-- Inner -->
  <div class="carousel-inner">
    <?php foreach($images as $index => $image): ?>
      <?php 
      if ($index % $imagecolumn == 0): 
      ?>
        <div class="carousel-item <?php if($index == 0) echo 'active'; ?>">
          <div class="row row-cols-1 <?php $rowcol_sm = max(1, round($imagecolumn / 2)); ?> row-cols-<?php echo esc_attr($rowcol_sm); ?> row-cols-lg-<?php echo esc_attr($imagecolumn); ?> g-3 px-4 py-5">
      <?php endif; ?>

        <div class="col mb-4">
          <img 
            class="img-fluid imgwidthfull lazyload<?php if ($style) echo ' ' . esc_attr($style); ?>" 
            src="data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=" 
            data-src="<?php echo esc_url($image['sizes'][$imagesize]); ?>" 
            alt="<?php echo !empty($image['alt']) ? esc_attr($image['alt']) : get_the_title(); ?>" 
            width="<?php echo esc_attr($image['sizes'][$imagesize . '-width']); ?>" 
            height="<?php echo esc_attr($image['sizes'][$imagesize . '-height']); ?>" 
          />
        </div>

      <?php 
      if (($index + 1) % $imagecolumn == 0 || $index + 1 == count($images)): 
      ?>
          </div>
        </div>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>

</div>

<?php $sliderCounter++; ?>

<?php else: ?>

  <!-- Fallback: Masonry or Static Layout -->
  <div class="row mt-3 mb-4 row-cols-1 row-cols-sm-<?php echo esc_attr($imagecolumn); ?> row-cols-lg-<?php echo esc_attr($imagecolumn); ?>" <?php if (is_array($images) && count($images) > 1): ?> data-masonry='{"percentPosition": true }'<?php endif; ?>>
    <?php foreach($images as $image): ?>
      <div class="col mb-4">
        <img 
          class="img-fluid imgwidthfull lazyload<?php if ($style) echo ' ' . esc_attr($style); ?>" 
          src="data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=" 
          data-src="<?php echo esc_url($image['sizes'][$imagesize]); ?>" 
          alt="<?php echo !empty($image['alt']) ? esc_attr($image['alt']) : get_the_title(); ?>" 
          width="<?php echo esc_attr($image['sizes'][$imagesize . '-width']); ?>" 
          height="<?php echo esc_attr($image['sizes'][$imagesize . '-height']); ?>" 
        />
      </div>
    <?php endforeach; ?>
  </div>

<?php endif; ?>
</div>


<!-- articleslideshow -->

<?php elseif( get_row_layout() == 'articleslideshow' ): //if has images do this?>
<div class="articleslideshow container-fluid contentfield">

<?php 
$imagecolumn = get_sub_field('imagecolumn'); 
?>
  
  <div class="row row-cols-1 row-cols-sm-<?php echo esc_attr($imagecolumn); ?> row-cols-lg-<?php echo esc_attr($imagecolumn); ?>"  data-masonry='{"percentPosition": true }'>
  

<?php $images = get_sub_field('articleslideshow'); if( $images ): $alt = $image['alt']; ?>


<?php foreach ($images as $image):

    $imagesize = get_sub_field('imagesize'); // Fetch the image size from ACF
    $size = $imagesize;
    $width = $image['sizes'][$size . '-width'];
    $height = $image['sizes'][$size . '-height'];

    // Generate SVG placeholder
    $svg_placeholder = 'data:image/svg+xml;base64,' . base64_encode(
        '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="' . $height . '" viewBox="0 0 ' . $width . ' ' . $height . '">
            <rect width="100%" height="100%" fill="#f8f9fb"/>
        </svg>'
    );
?>
							
<div class="col m-0 p-0">
    <a class="articleslideshowgroup" href="<?php echo $image['sizes']['large']; ?>">
        <img class="m-0 p-0 img-fluid imgwidthfull lazyload<?php $style = get_sub_field('style'); if ($style) { echo ' ' . $style; } ?>" 
             src="<?php echo $svg_placeholder; ?>" 
             data-src="<?php echo $image['sizes'][$imagesize]; ?>" 
             alt="<?php echo !empty($image['alt']) ? $image['alt'] : the_title(); ?>" 
             width="<?php echo $width; ?>" 
             height="<?php echo $height; ?>">
    </a>
</div>
<?php endforeach; ?>

<?php endif; ?> 
  <?php wp_reset_query(); ?>                     
</div>
</div>

<!-- articleslideshow -->





<!-- accordion --> 

<?php elseif( get_row_layout() == 'accordion' ): $a = 0; ?>






<div class="accordion container-fluid bg-light <?php	while ( have_rows('column') ) : the_row(); $column_container = get_sub_field('column_container'); $column_width = get_sub_field('column_width'); ?><?php echo $column_container ?><?php echo $column_width ?> <?php endwhile; ?>">


<div class="py-spacer container">

<?php 
$accordion_title = get_sub_field('accordion_title'); 
if ($accordion_title): ?>
    <h3 class="fs-1 fw-bold mb-3"><?php echo esc_html($accordion_title); ?></h3>
<?php endif; ?>

<?php $accordion_subtitle = get_sub_field('accordion_subtitle'); ?>
<?php if ( !empty($accordion_subtitle) ): ?>
  <p class="lead mt-4 mb-5"><?php echo $accordion_subtitle; ?></p>
<?php endif; ?>

<div class="accordion my-4" id="<?php echo "acc" . ++$a . ""; ?>">

<?php 
$b = 0; // Initialize $b variable
$isFirst = true; // Flag to track the first iteration

while (have_rows('accordion')): the_row(); 
  $accordionitemtitle = get_sub_field('accordionitemtitle');
  $accordionitembody = get_sub_field('accordionitembody');
?>

  <div class="accordion-item">
    <h4 class="m-0 p-0 accordion-header" id="<?php echo "heading" . ++$b . ""; ?>">
      <button class="accordion-button text-start bg-secondary text-white shadow-none <?php echo $isFirst ? '' : 'collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $b ?>" aria-expanded="<?php echo $isFirst ? 'true' : 'false'; ?>" aria-controls="collapse<?php echo $b ?>">
        <strong><?php echo $accordionitemtitle; ?></strong>
		<i class="fa-solid fa-chevron-down ms-auto"></i>
      </button>
    </h4>
    <div id="<?php echo "collapse" . $b . ""; ?>" class="accordion-collapse collapse <?php echo $isFirst ? 'show' : ''; ?>" aria-labelledby="<?php echo "heading" . $b . ""; ?>" data-bs-parent="#<?php echo "acc" . $a . ""; ?>">
      <div class="accordion-body">
        <?php echo $accordionitembody; ?>
      </div>
    </div>
  </div>

<?php 
$isFirst = false; // Set isFirst to false after the first iteration
endwhile; 
?>

</div>

</div>

</div>

 
 
<!-- accordion --> 


<!-- verticaltabs --> 

<?php elseif( get_row_layout() == 'verticaltabs' ): ?>

<div class="verticaltabs container-fluid py-spacer">
<div class="my-5 container">


<?php 
$verticaltabs_title = get_sub_field('verticaltabs_title'); 
if ($verticaltabs_title): ?>
    <h3 class="fs-1 fw-bold mb-3"><?php echo esc_html($verticaltabs_title); ?></h3>
<?php endif; ?>

<?php $verticaltabs_subtitle = get_sub_field('verticaltabs_subtitle'); ?>
<?php if ( !empty($verticaltabs_subtitle) ): ?>
  <p class="lead mt-4 mb-5"><?php echo $verticaltabs_subtitle; ?></p>
<?php endif; ?>

<?php $delay = 0; // Initialize delay counter for animation ?>

  <div class="row">
    <!-- Sidebar -->
    <div class="col-md-4 mb-4">
      <div class="nav flex-column nav-pills" id="v-pills-tab" role="tablist" aria-orientation="vertical">
        <?php $isActive = true; ?>
        <?php while( have_rows('verticaltabs') ): the_row(); ?>
          <?php $verticaltabsbutton = get_sub_field('verticaltabsitemtitle'); ?>
          <?php $sectionID = sanitize_title( $verticaltabsbutton ); ?>
          <button class="nav-link text-start btn-primary text-white mb-2 rounded-0 <?php if($isActive){ echo 'active'; $isActive = false; } ?>" id="v-pills-<?php echo $sectionID; ?>-tab" data-bs-toggle="pill" data-bs-target="#v-pills-<?php echo $sectionID; ?>" type="button" role="tab" aria-controls="v-pills-<?php echo $sectionID; ?>" aria-selected="true" data-aos="flip-up" data-aos-delay="<?php echo $delay; ?>">
            <i class="fa-solid fa-arrow-right me-2"></i> <?php echo $verticaltabsbutton; ?>
          </button>
		  
		  <?php $delay += 200; // Increase delay by 200ms for each item for animation ?>
		  
        <?php endwhile; ?>
      </div>
    </div>

    <!-- Content Area -->
	
	<div class="col-md-8" data-aos="fade-up" data-aos-delay="<?php echo $delay; ?>">
  <?php if( have_rows('verticaltabs') ): $isActive = true; ?>
    <div class="tab-content" id="v-pills-tabContent">
      <?php while( have_rows('verticaltabs') ) : the_row(); ?>
        <?php 
          $verticaltabsbutton = get_sub_field('verticaltabsitemtitle');
          $verticaltabscontent = get_sub_field('verticaltabsitembody');
          $verticaltabsitemimage = get_sub_field('verticaltabsitemimage');
          $sectionID = sanitize_title( $verticaltabsbutton );
        ?>

        <div class="tab-pane fade <?php if($isActive){ echo 'show active'; $isActive = false; } ?>" id="v-pills-<?php echo $sectionID; ?>" role="tabpanel" aria-labelledby="v-pills-<?php echo $sectionID; ?>-tab">
          
          <?php if (!empty($verticaltabsitemimage)):
              $size = 'large';
              $image_url = $verticaltabsitemimage['sizes'][$size];
              $width = $verticaltabsitemimage['sizes'][$size . '-width'];
              $height = $verticaltabsitemimage['sizes'][$size . '-height'];

              $svg_placeholder = 'data:image/svg+xml;base64,' . base64_encode(
                '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="' . $height . '" viewBox="0 0 ' . $width . ' ' . $height . '">
                    <rect width="100%" height="100%" fill="#f8f9fb"/>
                </svg>'
              );
          ?>
            <!-- IMAGE OUTSIDE BG -->
            <img class="lazyload img-full img-fluid border mb-4" 
                width="<?php echo $width; ?>" 
                height="<?php echo $height; ?>" 
                src="<?php echo $svg_placeholder; ?>" 
                data-src="<?php echo $image_url; ?>" 
                alt="<?php echo $verticaltabsitemimage['alt']; ?>" />
          <?php endif; ?>

          <!-- CONTENT WITH BG -->
          <div class="bg-light p-4">
            <h4 class="mt-0 mb-3 lh-sm"><?php echo $verticaltabsbutton; ?></h4>
            <?php echo $verticaltabscontent; ?>
          </div>

        </div>
       
	   
	   <?php endwhile; ?>
    </div>
  <?php endif; ?>
</div>
</div>


</div>
</div>

<!-- verticaltabs --> 


<?php elseif( get_row_layout() == 'pagecontent1' ): ?>
<div class="pagecontent1 container-fluid py-spacer">
<div class="my-5 container">

          
          
          <?php 
$title = get_sub_field('title'); 
$subtitle = get_sub_field('subtitle'); 
?>

<?php if ($title): ?>
    <h3 class="fs-1 fw-bold text-center"><?php echo esc_html($title); ?></h3>
<?php endif; ?>

<?php if ($subtitle): ?>
    <p class="mt-4 mb-5 text-center"><?php echo esc_html($subtitle); ?></p>
<?php endif; ?>

            
		  
<?php

// Check rows exists.
if( have_rows('columns') ): ?>

<div class="row justify-content-md-center">

<?php     // Loop through rows.
    while( have_rows('columns') ) : the_row();
 
 

 
        // Load sub field value.
        $title = get_sub_field('title');
		$description = get_sub_field('description');
		$icon = get_sub_field('icon');

        ?>
		
<div class="col-lg mb-5 mb-md-0">
<?php if ($icon) : ?><i class="text-primary fa-4x me-3 <?php echo ($icon); ?>"></i><?php endif; ?>
<h4 class="fw-bolder"><?php echo $title ?></h4>
<?php echo $description ?>

</div>		

    
<?php   endwhile;  ?>

</div>

<?php else : endif; ?>
       
</div>
</div>
 
 
 

<?php elseif( get_row_layout() == 'pagecontent2' ): ?>
<div class="pagecontent2 container-fluid bg-secondary py-5">
<div class="my-5 container">
          
		  
<?php

// Check rows exists.
if( have_rows('columns') ): ?>

<div class="row justify-content-center">

<?php

$delay = 0; // Initialize delay counter for animation

    while( have_rows('columns') ) : the_row();
 
        // Load sub field value.
        $icon = get_sub_field('icon');
		$number = get_sub_field('number');
		$description = get_sub_field('description');
        ?>
		
<div class="col-md-3 text-center my-3 my-md-0 mb-5" data-aos="flip-up" data-aos-delay="<?php echo $delay; ?>">
<i class="fa-3x fa fa-<?php echo $icon; ?>"></i><br>
<strong class="fs-1" ><?php echo $number ?></strong>
<p class="mb-0"><?php echo $description ?></p>

</div>

     <?php $delay += 200; // Increase delay by 200ms for each item for animation ?>
	 
<?php   endwhile; ?>

</div>

<?php  else : endif; ?>
       
</div>
</div>
      

<?php elseif( get_row_layout() == 'pagecontent3' ): ?>
<div class="pagecontent3 container-fluid bg-light">




 
 
 
<div class="row justify-content-between align-items-stretch">
    <div class="col-md-6 mb-4 px-spacer py-spacer custom-check" data-aos="fade-up">
        <?php 
$title = get_sub_field('title'); 
$subtitle = get_sub_field('subtitle'); 
?>

<?php if ($title): ?>
    <h3 class="mt-3 mb-4 lh-sm fs-1 fw-bold"><?php echo esc_html($title); ?></h3>
<?php endif; ?>

<?php if ($subtitle): ?>
    <p class="lead"><?php echo esc_html($subtitle); ?></p>
<?php endif; ?>
        <?php 
$description = get_sub_field('description'); 
if ($description): 
    echo wp_kses_post($description); 
endif; 
?>
    </div>
    <div class="col-md-6 p-0 jarallax-content">
	
	
	
<div data-jarallax data-speed="0.2" class="jarallax">
  <div class="jarallax-img">
  
   <?php 
            $image = get_sub_field('image');
            if (!empty($image)):
                $image_url = $image['url'];
                $size = 'full';
                $width = $image['width'];
				$height = $image['height'];

            ?>
			
			
    
    
	
	
                <img loading="lazy" 
                     src="<?php echo $image_url; ?>" 
                     alt="<?php echo $image['alt']; ?>" 
                     class="jarallax-img" 
                     width="<?php echo $width; ?>" 
                     height="<?php echo $height; ?>">
            <?php endif; ?>
			
			
  </div>
</div>

	
	
	
	
	</div>
	
	
	
</div>

</div> 

  

<?php elseif( get_row_layout() == 'pagecontent4' ): ?>
<div class="pagecontent4 container-fluid my-5">
<div class="my-5 py-5 container">
      


   
   
<div>


<h3 class="mt-0 mb-3 lh-sm fs-1 fw-bold" ><?php 
$title = get_sub_field('title'); 
if ($title): 
    echo wp_kses_post($title); 
endif; 
?></h3>
<p class="lead mb-5"><?php 
$subtitle = get_sub_field('subtitle'); 
if ($subtitle): 
    echo wp_kses_post($subtitle); 
endif; 
?></p>


<?php

// Check rows exists.
if( have_rows('squareimgwithdesc') ): ?>

<div class="row">

<?php     // Loop through rows.
    while( have_rows('squareimgwithdesc') ) : the_row();
 
 

 
        // Load sub field value.
        $title = get_sub_field('title');
        $subtitle = get_sub_field('subtitle');
        $description = get_sub_field('description');
        $linkedin = get_sub_field('linkedin');
        $image = get_sub_field('image');

        $col_class = !empty($image) ? 'col-md-9' : 'col-md-12';
        ?>

<div class="row mb-4">

<?php if (!empty($image)):
    $size = 'thumbnail'; // The selected image size
    $image_url = $image['sizes'][$size];
    $width = $image['sizes'][$size . '-width'];
    $height = $image['sizes'][$size . '-height'];

    // Generate SVG placeholder
    $svg_placeholder = 'data:image/svg+xml;base64,' . base64_encode(
        '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="' . $height . '" viewBox="0 0 ' . $width . ' ' . $height . '">
                <rect width="100%" height="100%" fill="#f8f9fb"/>
            </svg>'
    );
    ?>
    <div class="col-md-3 mb-4">
        <img class="lazyload img-full img-fluid border"
             width="<?php echo $width; ?>"
             height="<?php echo $height; ?>"
             src="<?php echo $svg_placeholder; ?>"
             data-src="<?php echo $image_url; ?>"
             alt="<?php echo $image['alt']; ?>" />
    </div>
<?php endif; ?>


<div class="<?php echo $col_class; ?> mb-4">

<div class="ps-3">

<h4 class="fw-bolder fs-2 mt-2" ><?php echo $title ?></h4>
<p><?php echo $subtitle ?></p>

<?php if ($description) : ?>
<p><?php echo ($description); ?></p><?php endif; ?>

<?php if ($linkedin) : ?>


<a target="_blank" href="<?php echo ($linkedin); ?>">
<i class="fa-2x fab fa-linkedin"></i> </a>


<?php endif; ?>

</div>
</div>

</div>


<?php   endwhile;  ?>

</div>

<?php else : endif; ?>




	 
	 
	 
</div>

 
		

<?php 
$description = get_sub_field('description'); 
if ($description): 
    echo wp_kses_post($description); 
endif; 
?>


 
   




       
</div>
</div>
       

      

<?php elseif( get_row_layout() == 'pagecontent5' ): ?>

<!-- //define if column start --> 
<?php while ( have_rows('column') ) : the_row(); $column_position = get_sub_field('column_position');?>
<?php if (strpos($column_position, 'start') !== false) { //if cond. ?><div class="container-fluid"> <div class="d-flex row align-items-stretch p-0"> 
<?php } ?>
<?php endwhile; ?>
<!-- //define if column start --> 


<div data-jarallax data-speed="0.2" class="<?php if (have_rows('column')) { ?> d-flex<?php } ?> jarallax pagecontent5 <?php while ( have_rows('column') ) : the_row(); $column_container = get_sub_field('column_container'); $column_width = get_sub_field('column_width'); ?><?php echo $column_container ?><?php echo $column_width ?> align-items-center p-0 <?php endwhile; ?> <?php $colored = get_sub_field('colored'); if ( $colored && is_array($colored) && in_array('colored', $colored) ) { ?>bg-secondary<?php } else { ?>bg-dark<?php } ?>">
<!-- jarallax image -->
 
   
	  
	   <?php 
        $image = get_sub_field('image');
		 $image_url = $image['sizes']['large'];
		 
		  $size = 'large';
		  $width = $image['sizes'][ $size . '-width' ];
            $height = $image['sizes'][ $size . '-height' ];
			
			
        if( !empty($image) ): ?>

            <img loading="lazy" class="lazyload jarallax-img" width="<?php echo $width; ?>" height="<?php echo $height; ?>" src="data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=" data-src="<?php echo $image_url; ?>" alt="<?php echo $image['alt']; ?>" />

    <?php endif; ?>



	  
	
  
  <!-- jarallax content -->  
  
      <div class="py-spacer px-4">
	  

		
<div class="text-white text-center">



<?php $home_page = get_sub_field('home_page'); if ( $home_page && is_array($home_page) && in_array('home_page', $home_page) ) { // if home page checked  ?>
   
<?php 
$title = get_field('title', get_option('page_on_front')); 
$description = get_field('description', get_option('page_on_front'));
?>

<h1 class="mt-0 mb-4 lh-sm fs-1 fw-bold"><?php echo $title; ?></h1>
<p class="lead col-8 mx-auto"><?php echo $description; ?></p>
 
	 
<?php } else { //if not home page checked ?>
   
<?php 
$title = get_sub_field('title'); 
$subtitle = get_sub_field('subtitle'); 
?>

<?php if ($title): ?>
    <h3 class="mt-0 mb-4 lh-sm fs-1 fw-bold"><?php echo esc_html($title); ?></h3>
<?php endif; ?>

<?php if ($subtitle): ?>
    <p class="lead col-8 mx-auto"><?php echo esc_html($subtitle); ?></p>
<?php endif; ?>

   
<?php } //end if home page checked ?>
          

      

            <?php $formcode = get_sub_field('formcode'); ?>
            <?php if ($formcode): ?>
                <div class="row justify-content-center mt-5">
				<div class="col-md-7">
                    <?php echo do_shortcode($formcode); ?>
					 </div>
                </div>
            <?php endif; ?>

            <?php $button = get_sub_field('button'); ?>
            <?php if ($button): ?>
                <a href="<?php $link = get_sub_field('link'); if ($link) echo esc_html($link); ?>" class="my-3 btn <?php $colored = get_sub_field('colored'); if ( $colored && is_array($colored) && in_array('colored', $colored) ) { ?>bg-dark text-light<?php } else { ?>btn-primary<?php } ?> mx-1"><?php $button = get_sub_field('button'); if ($button) echo esc_html($button); ?></a>
            <?php endif; ?>



            <?php $button1 = get_sub_field('button1'); ?>
            <?php if ($button1): ?>
                <a href="<?php $link1 = get_sub_field('link1'); if ($link1) echo esc_html($link1); ?>" class="my-3 btn btn-light mx-1"><?php $button1 = get_sub_field('button1'); if ($button1) echo esc_html($button1); ?></a>
            <?php endif; ?>
	
	
	

        </div>

   
  

 

	  </div>
	  
	<!-- jarallax content -->  
    

   
    <!-- jarallax image -->

</div>


<!-- //define if column end --> 
<?php while ( have_rows('column') ) : the_row(); $column_position = get_sub_field('column_position');?>
<?php if (strpos($column_position, 'end') !== false) { //if cond. ?></div> </div> 
<?php } ?>
<?php endwhile; ?>
<!-- //define if column end -->    



<?php elseif( get_row_layout() == 'pagecontent6' ): ?>
<div class="bg-dark pagecontent6">
<!-- jarallax image -->
 
 
 
  <div class="py-spacer container">
	  

<div class="row">	
<div class="col text-white text-center">

<h3 class="mt-0 mb-4 lh-sm fs-1 fw-bold" ><?php $title = get_sub_field('title'); if ($title) echo esc_html($title); ?></h3>
<p class="lead"><?php $subtitle = get_sub_field('subtitle'); if ($subtitle) echo esc_html($subtitle); ?></p>

</div>
</div>	
     </div>
	 
  <div class="ratio ratio-16x9">
       
     
     <div class="map"></div>
	 
	 
	 
</div>

 

	
	  





  

</div>




<?php elseif( get_row_layout() == 'pagecontent7' ): ?>
<div data-jarallax data-speed="0.2" class="<?php if (have_rows('column')) { ?> d-flex<?php } ?> jarallax pagecontent7 <?php while ( have_rows('column') ) : the_row(); $column_container = get_sub_field('column_container'); $column_width = get_sub_field('column_width'); ?><?php echo $column_container ?><?php echo $column_width ?> align-items-center p-0 <?php endwhile; ?> <?php $colored = get_sub_field('colored'); if ( $colored && is_array($colored) && in_array('colored', $colored) ) { ?>bg-secondary<?php } else { ?>bg-dark<?php } ?>">
<!-- jarallax image -->
 
   
	  
	   <?php 
        $image = get_sub_field('image');
		 $image_url = $image['sizes']['large'];
		 
		  $size = 'large';
		  $width = $image['sizes'][ $size . '-width' ];
            $height = $image['sizes'][ $size . '-height' ];
			
			
        if( !empty($image) ): ?>

            <img loading="lazy" class="lazyload jarallax-img" width="<?php echo $width; ?>" height="<?php echo $height; ?>" src="data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=" data-src="<?php echo $image_url; ?>" alt="<?php echo $image['alt']; ?>" />

    <?php endif; ?>



	  
	
  
  <!-- jarallax content -->  
  
      <div class="py-spacer px-4">
	  

		


<div class="row p-5 text-white">


<div class="col-md-6" data-aos="fade-up" data-aos-duration="2000">


<h3 class="mt-0 mb-4 lh-sm fs-1 fw-bold"><?php $title = get_sub_field('title'); if ($title) echo esc_html($title); ?></h3>
<p class="lead"><?php $subtitle = get_sub_field('subtitle'); if ($subtitle) echo esc_html($subtitle); ?></p>
   

            <?php $button = get_sub_field('button'); ?>
            <?php if ($button): ?>
                <a href="<?php $link = get_sub_field('link'); if ($link) echo esc_html($link); ?>" class="my-3 btn <?php $colored = get_sub_field('colored'); if ( $colored && is_array($colored) && in_array('colored', $colored) ) { ?>bg-dark text-light<?php } else { ?>btn-primary<?php } ?> mx-1"><?php $button = get_sub_field('button'); if ($button) echo esc_html($button); ?></a>
            <?php endif; ?>



            <?php $button1 = get_sub_field('button1'); ?>
            <?php if ($button1): ?>
                <a href="<?php $link1 = get_sub_field('link1'); if ($link1) echo esc_html($link1); ?>" class="my-3 btn btn-light mx-1"><?php $button1 = get_sub_field('button1'); if ($button1) echo esc_html($button1); ?></a>
            <?php endif; ?>
	
	

</div>



<div class="col-md-4 py-3 offset-md-1" data-aos="fade-down" data-aos-duration="2000">


<?php	// loop through the rows of data
			    while ( have_rows('progress') ) : the_row();

	 $title = get_sub_field('title');
   $percentage = get_sub_field('percentage');
   ?>
   
   

      <div class="pb-4">
    <p class="h6 pb-3 fs-3"><?php echo $title  ?> <strong><?php echo $percentage  ?>%</strong></p>
<div class="progress">
  
  
  
  <div class="progress-bar progress-bar-striped progress-bar-animated bg-secondary" role="progressbar" aria-valuenow="<?php echo $percentage  ?>" aria-valuemin="0" aria-valuemax="100" aria-label="<?php echo $title  ?>"></div>
  
</div>
</div>

<?php		endwhile; ?> 

</div>


</div>
   
	



   
  

 

	  </div>
	  
	<!-- jarallax content -->  
    

   
    <!-- jarallax image -->

</div>


<?php elseif( get_row_layout() == 'pagecontent8' ): ?>
<section class="pagecontent8 d-flex align-items-center position-relative overflow-hidden p-0">
  <div class="jarallax w-100 h-100 position-absolute top-0 start-0" data-jarallax data-speed="0.5">
  
  
  <!-- jarallax image -->
 
   
	  
<?php 
$image = get_sub_field('image');
$image_url = $image['url']; // Get the FULL size image directly
$width = $image['width'];   // Full image width
$height = $image['height']; // Full image height

if (!empty($image)): ?>
    <img 
        loading="lazy" 
        class="lazyload jarallax-img opacity-100 object-fit-cover"
        width="<?php echo esc_attr($width); ?>" 
        height="<?php echo esc_attr($height); ?>" 
        src="data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=" 
        data-src="<?php echo esc_url($image_url); ?>" 
        alt="<?php echo esc_attr($image['alt']); ?>" 
    />
<?php endif; ?>




	  
	
  
  <!-- jarallax content -->  

  </div>
  
  
  
  
  
  
    <!-- jarallax content -->  
  
<div class="container-fluid position-relative h-100"  data-aos="fade-up" data-aos-duration="1000" data-aos-easing="ease-in">
	  

		


<div class="row h-100">


<div class="col-lg-6 ms-auto my-5 bg-primary p-5 text-white" data-aos="fade-up" data-aos-duration="2000">


<h3 class="mt-0 mb-2 lh-sm fs-1 fw-bold"><?php $title = get_sub_field('title'); if ($title) echo esc_html($title); ?></h3>

<p class="lead"><?php $subtitle = get_sub_field('subtitle'); if ($subtitle) echo esc_html($subtitle); ?></p>




<div class="custom-check">



<?php $content = get_sub_field('content'); if ($content) echo wp_kses_post($content); ?>


<?php if (have_rows('accordion')): ?>
  <div class="accordion my-4" id="<?php echo "accB" . ++$a; ?>">

    <?php 
    $b = 0; 
    $isFirst = true; 

    while (have_rows('accordion')): the_row(); 
      $accordionitemtitle = get_sub_field('accordionitemtitle');
      $accordionitembody = get_sub_field('accordionitembody');
    ?>

      <div class="accordion-item border-0">
        <h4 class="m-0 p-0 accordion-header" id="<?php echo "headingB" . ++$b; ?>">
          <button class="accordion-button text-start bg-primary text-black shadow-none border border-white rounded-0 px-3 py-2 <?php echo $isFirst ? '' : 'collapsed'; ?>" 
                  type="button" 
                  data-bs-toggle="collapse" 
                  data-bs-target="#collapseB-collapse<?php echo $b; ?>" 
                  aria-expanded="<?php echo $isFirst ? 'true' : 'false'; ?>" 
                  aria-controls="collapseB-collapse<?php echo $b; ?>">
            <i class="fa-solid fa-chevron-down me-3"></i>
            <strong><?php echo $accordionitemtitle; ?></strong>
          </button>
        </h4>
        <div id="<?php echo "collapseB-collapse" . $b; ?>" 
             class="accordion-collapse collapse <?php echo $isFirst ? 'show' : ''; ?>" 
             aria-labelledby="<?php echo "headingB" . $b; ?>" 
             data-bs-parent="#<?php echo "accB" . $a; ?>">
          <div class="accordion-body bg-primary">
            <?php echo $accordionitembody; ?>
          </div>
        </div>
      </div>

    <?php 
    $isFirst = false;
    endwhile; 
    ?>

  </div>
<?php endif; ?>











<?php $contentend = get_sub_field('contentend'); if ($contentend) echo wp_kses_post($contentend); ?>



            <?php $button = get_sub_field('button'); ?>
            <?php if ($button): ?>
                <a href="<?php $link = get_sub_field('link'); if ($link) echo esc_html($link); ?>" class="my-3 btn btn-lg <?php $colored = get_sub_field('colored'); if ( $colored && is_array($colored) && in_array('colored', $colored) ) { ?>bg-dark text-light<?php } else { ?>btn-secondary<?php } ?> mx-1"><?php $button = get_sub_field('button'); if ($button) echo esc_html($button); ?> <i class="fas fa-long-arrow-alt-right ms-2"></i></a>
            <?php endif; ?>

	</div>
	

</div>




</div>


	  </div>
	  
	<!-- jarallax content -->  
	
	

</section>



<?php elseif( get_row_layout() == 'pagecontent9' ): ?>




<?php
$pagecontent9_slider = get_sub_field('slider'); // Checkbox
$pagecontent9_cards = get_sub_field('cards');
$pagecontent9_columns = get_sub_field('columns');
$pagecontent9_columns = $pagecontent9_columns ? intval($pagecontent9_columns) : 3;
$sameheight = get_sub_field('sameheight');
global $sliderCounter;
if (!isset($sliderCounter)) {
    $sliderCounter = 1;
}

if ($pagecontent9_cards):
?>
<div class="container py-spacer pagecontent9">
  <div class="col text-center">
    <h3 class="mt-0 mb-4 lh-sm fs-1 fw-bold">
      <?php $title = get_sub_field('title'); if ($title) echo esc_html($title); ?>
    </h3>
    <p class="lead mb-5">
      <?php $subtitle = get_sub_field('subtitle'); if ($subtitle) echo esc_html($subtitle); ?>
    </p>
  </div>

  <?php if ($pagecontent9_slider && in_array('slider', $pagecontent9_slider)) : ?>
    <div id="slider<?php echo $sliderCounter; ?>" class="carousel slide">
      <div class="carousel-inner">
        <?php $grouped_cards = wp_is_mobile() ? array_chunk($pagecontent9_cards, 1) : array_chunk($pagecontent9_cards, $pagecontent9_columns); foreach ($grouped_cards as $group_index => $card_group): ?>
          <div class="carousel-item <?php if ($group_index == 0) echo 'active'; ?>">
            <div class="row g-4 mb-5 pb-5 px-3<?php if($sameheight) echo ' align-items-stretch'; ?>">
              <?php foreach ($card_group as $card): ?>
                <div class="col-md-<?php echo intval(12 / $pagecontent9_columns); ?><?php if($sameheight) echo ' d-flex'; ?>">
                  <div class="hover-box bg-secondary text-white shadow-sm parallax-card<?php if($sameheight) echo ' h-100'; ?>">
                    <?php 
                      $image = $card['top_image'];
                      if (!empty($image)):
                        $image_url = $image['sizes']['large'];
                        $size = 'large';
                        $width = $image['sizes'][ $size . '-width' ];
                        $height = $image['sizes'][ $size . '-height' ];
						
						
						// Generate SVG placeholder with dynamic viewBox
$svg_placeholder = 'data:image/svg+xml;base64,' . base64_encode(
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $width . ' ' . $height . '" preserveAspectRatio="xMidYMid slice">
        <rect width="100%" height="100%" fill="#f8f9fb"/>
    </svg>'
);


                    ?>
                      <img loading="lazy" class="lazyload img-fluid logo-overlay border border-light" width="<?php echo $width; ?>" height="<?php echo $height; ?>" src="<?php echo $svg_placeholder; ?>" data-src="<?php echo $image_url; ?>" alt="<?php echo esc_attr($image['alt']); ?>" />
					  
					  
					  
                    <?php endif; ?>

                    <?php 
                      $image = $card['background_image'];
                      if (!empty($image)):
                        $image_url = $image['sizes']['large'];
                        $size = 'large';
                        $width = $image['sizes'][ $size . '-width' ];
                        $height = $image['sizes'][ $size . '-height' ];
                    ?>
                    <div class="jarallax">
                      <img loading="lazy" class="lazyload jarallax-img" width="<?php echo $width; ?>" height="<?php echo $height; ?>" src="data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=" data-src="<?php echo $image_url; ?>" alt="<?php echo esc_attr($image['alt']); ?>" />
                    </div>
                    <?php endif; ?>
                    
                    <div class="d-flex flex-column justify-content-start p-4 card-overlay">
                      <h4 class="mt-3 fw-bold fs-4"><?php echo esc_html($card['title']); ?></h4>
                      <p class="mb-0 lead"><?php echo esc_html($card['subtitle']); ?></p>
                      <div class="divider-line"></div>
                      <p class="hover-paragraph"><?php echo esc_html($card['content']); ?></p>
                      <?php if (!empty($card['read_more_link']) && !empty($card['read_more'])): ?>
                      <div class="hover-opacity hover-link-container">
                        <a href="<?php echo esc_url($card['read_more_link']); ?>" class="btn btn-primary mt-3 me-auto hover-opacity hover-link-container">
  <?php echo esc_html($card['read_more']); ?> <i class="fas fa-angle-double-right"></i>
</a>
                      </div>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="carousel-indicators mt-4">
        <ul class="list-unstyled d-flex justify-content-center m-0 p-0">
          <?php for ($i = 0; $i < (wp_is_mobile() ? count($pagecontent9_cards) : ceil(count($pagecontent9_cards) / $pagecontent9_columns)); $i++): ?>
            <li data-bs-target="#slider<?php echo $sliderCounter; ?>" data-bs-slide-to="<?php echo $i; ?>" <?php if ($i == 0) echo 'class="active"'; ?>></li>
          <?php endfor; ?>
        </ul>
      </div>


    </div>
    <?php $sliderCounter++; ?>
  <?php else: ?>
    <div class="row g-4 px-3<?php if($sameheight) echo ' align-items-stretch'; ?>">
      <?php foreach ($pagecontent9_cards as $card): ?>
        <div class="col-md-<?php echo intval(12 / $pagecontent9_columns); ?><?php if($sameheight) echo ' d-flex'; ?>">
          <div class="hover-box bg-secondary text-white shadow-sm parallax-card<?php if($sameheight) echo ' h-100'; ?>">
		  
		  
            <?php 
                      $image = $card['top_image'];
                      if (!empty($image)):
                        $image_url = $image['sizes']['large'];
                        $size = 'large';
                        $width = $image['sizes'][ $size . '-width' ];
                        $height = $image['sizes'][ $size . '-height' ];
						
						
						// Generate SVG placeholder with dynamic viewBox
$svg_placeholder = 'data:image/svg+xml;base64,' . base64_encode(
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $width . ' ' . $height . '" preserveAspectRatio="xMidYMid slice">
        <rect width="100%" height="100%" fill="#f8f9fb"/>
    </svg>'
);


                    ?>
                      <img loading="lazy" class="lazyload img-fluid logo-overlay border border-light" width="<?php echo $width; ?>" height="<?php echo $height; ?>" src="<?php echo $svg_placeholder; ?>" data-src="<?php echo $image_url; ?>" alt="<?php echo esc_attr($image['alt']); ?>" />
					  
					  
					  
                    <?php endif; ?>
            
			
			
			
			
			<?php 
              $image = $card['background_image'];
              if (!empty($image)):
                $image_url = $image['sizes']['large'];
                $size = 'large';
                $width = $image['sizes'][ $size . '-width' ];
                $height = $image['sizes'][ $size . '-height' ];
            ?>
            <div class="jarallax">
              <img loading="lazy" class="lazyload jarallax-img" width="<?php echo $width; ?>" height="<?php echo $height; ?>" src="data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=" data-src="<?php echo $image_url; ?>" alt="<?php echo esc_attr($image['alt']); ?>" />
            </div>
            <?php endif; ?>
            <div class="d-flex flex-column justify-content-start p-4 card-overlay">
              <h4 class="mt-3 fw-bold fs-4"><?php echo esc_html($card['title']); ?></h4>
              <p class="mb-0 lead"><?php echo esc_html($card['subtitle']); ?></p>
              <div class="divider-line"></div>
              <p class="hover-paragraph"><?php echo esc_html($card['content']); ?></p>
              <?php if (!empty($card['read_more_link']) && !empty($card['read_more'])): ?>
              <div class="hover-opacity hover-link-container">
                <a href="<?php echo esc_url($card['read_more_link']); ?>" class="btn btn-primary mt-3 me-auto hover-opacity hover-link-container">
  <?php echo esc_html($card['read_more']); ?> <i class="fas fa-angle-double-right"></i>
</a>
              </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php endif; ?>



<?php elseif( get_row_layout() == 'contacts' ): ?>

  <section class="contacts py-5">
    <div class="container">
      <div class="row">
        <!-- Contact Info -->
        <div class="col-md-6 mb-4">
          
<?php if ($pretitle = get_sub_field('pretitle')): ?>
  <p class="small"><?php echo nl2br(esc_html($pretitle)); ?></p>
<?php endif; ?>

<?php if ($title = get_sub_field('title')): ?>
  <h3 class="mb-4 fs-1 fw-bold"><?php echo esc_html($title); ?></h3>
<?php endif; ?>

<?php if ($subtitle = get_sub_field('subtitle')): ?>
  <?php echo wp_kses_post($subtitle); ?>
<?php endif; ?>




          <?php if (have_rows('map_locations', 2)): ?>
		  
		    <div class="row">
			
  <?php while (have_rows('map_locations', 2)): the_row(); ?>
  

<?php
$locations = get_field('map_locations', 2);
if ($locations):
  $col_class = count($locations) === 1 ? 'col-md-12' : 'col-md-5 me-4';
?>


  
    <div class="<?php echo $col_class; ?>">
  
      <?php if ($country = get_sub_field('country')): ?>
      <h4 class="my-3 fw-bold">
	  
        <?php echo esc_attr($country); ?>
      </h4>
    <?php endif; ?>
	
		      <?php if ($city = get_sub_field('city')): ?>
      <h5 class="mb-3">
	  
        <?php echo esc_attr($city); ?>
      </h5>
    <?php endif; ?>
	
	 
    <?php if ($telephone = get_sub_field('telephone', false)): ?>
      <p>
        <i class="me-2 fa-solid fa-square-phone"></i> <a href="tel:+<?php echo wp_kses_post($telephone); ?>"> +<?php echo wp_kses_post($telephone); ?></a>
      </p>
    <?php endif; ?>

    <?php if ($street_address = get_sub_field('street_address', false)): ?>
      <p>
        <i class="me-2 fa-solid fa-location-dot"></i> <a href="<?php if ($url = get_sub_field('url')) echo esc_attr($url); ?>" target="blank"><?php echo wp_kses_post($street_address); ?></a>
      </p>
    <?php endif; ?>

	</div>
	
<?php endif; ?>
	
  <?php endwhile; ?>
  
  	</div>
<?php endif; ?>


  <?php if (have_rows('working_hours', 2)): ?>
<?php while (have_rows('working_hours', 2)): the_row(); ?>


      <div class="mt-4">
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

        <!-- Contact Form -->
		
		<?php $formcode = get_sub_field('formcode'); ?>
		<div class="col-md-6">
            
			
                <div class="row justify-content-center">
				
				
				
				
				
				<?php if ($formcode): ?>
				
				<div class="col-md-10">
				
				
				<?php $form_title = get_sub_field('form_title'); ?>
				<?php if ($form_title): ?>
				
				<h3 class="lh-sm mb-5 fs-1 fw-bold" ><?php $form_title = get_sub_field('form_title'); if ($form_title) echo esc_html($form_title); ?></h3>
				
				<?php endif; ?>
				
				
                    <?php echo do_shortcode($formcode); ?>
					 </div>
					 
					 <?php endif; ?>
					 
					 
                </div>
                    
							

				



				
					
					
			
		
		
			</div>
		
		
        

		
		
		
		
      </div>
    </div>
  </section>










<?php elseif( get_row_layout() == 'categorylist' ): ?>
<div class="categorylist container-fluid bg-light py-spacer">
<div class="py-spacer container">
          
          <div class="text-center">
           <h3 class="fs-1 fw-bold" > <?php $title = get_sub_field('title'); if ($title) echo esc_html($title); ?></h3>
           <p class="mt-4 mb-5"><?php $subtitle = get_sub_field('subtitle'); if ($subtitle) echo esc_html($subtitle); ?></p>
          </div>  
		  
<!-- show selected categories -->
<div class="justify-content-center row">            
<?php $catss = get_sub_field('categories'); ?> 
<?php foreach ($catss as $cat) { // let the loop understand the values you would like to echo  ?>


<div class="mb-4 col-sm-6 col-md-6 col-lg-6">

<div class="row">
<div class="text-center col-3"><?php $icon = get_field('icon', $cat);?><i class="me-2 fa-3x fa fa-<?php echo $icon; ?>"></i></div>

<div class="col-9"><h3 class="fw-bold m-0 mb-2"><a href="<?php echo get_tag_link($cat); ?>"><?php  echo $cat->name; ?></a></h3>

<?php $description = get_field('description', $cat);?><?php if( get_field('description', $cat) ): ?><p class="card-text mb-5"><?php echo $description; ?></p><?php endif; ?>  
</div>
</div>
</div>

<?php }  wp_reset_query(); // end of let the loop understand the values you would like to echo ?>    
   </div> 
<!-- show selected categories -->
      
</div>
</div>


<?php elseif( get_row_layout() == 'tagslist' ): ?>
<div class="tagslist container-fluid bg-light py-spacer">
<div class="my-5 container">
          
          <div class="text-center">
           <h3 class="fs-1 fw-bold" > <?php $title = get_sub_field('title'); if ($title) echo esc_html($title); ?></h3>
           <p class="mt-4 mb-5"><?php $subtitle = get_sub_field('subtitle'); if ($subtitle) echo esc_html($subtitle); ?></p>
          </div>  
		  
<!-- show selected categories -->
<div class="justify-content-center row">            
<?php

$delay = 0; // Initialize delay counter for animation (Place this before the loop)

$catss = get_sub_field('tags'); ?>

 
<?php foreach ($catss as $cat) { // let the loop understand the values you would like to echo  ?>


<div class="mb-4 col-sm-6 col-md-6 col-lg-6" data-aos="zoom-in" data-aos-delay="<?php echo $delay; ?>">

<div class="row">
<div class="text-center col-3"><?php $icon = get_field('icon', $cat);?><i class="me-2 fa-3x fa fa-<?php echo $icon; ?>"></i></div>

<div class="col-9"><h3 class="fw-bold m-0 mb-2"><a href="<?php echo get_tag_link($cat); ?>"><?php  echo $cat->name; ?></a></h3>

<?php $description = get_field('description', $cat);?><?php if( get_field('description', $cat) ): ?><p class="card-text mb-5"><?php echo $description; ?></p><?php endif; ?>  
</div>
</div>
</div>

<?php $delay += 200; // Increase delay by 200ms for each item ?>

<?php }  wp_reset_query(); // end of let the loop understand the values you would like to echo ?>    
   </div> 
<!-- show selected categories -->
      
</div>
</div>

















<!-- postsrelatedcat  -->



<?php elseif( get_row_layout() == 'postsrelatedcat' ): ?>




<?php
// Get the values
$imagesize = get_sub_field('imagesize'); // Fetch the image size selected in ACF
$infinite = get_sub_field('infinite'); // Replace 'infinite' with your actual checkbox field name
$columns = get_sub_field('columns');
$postscount = get_sub_field('postscount');
$addmore = get_sub_field('addmore');
$sameheight = get_sub_field('sameheight');


// Check if the checkbox is checked
if( $infinite ) {
    // Code to display if the infinite checkbox is checked
    ?>







<div class="postsrelatedcat">

<?php
  // 1) Retrieve selected categories from ACF
  $catinfinite = get_sub_field('postsrelatedcat');
  
  // If categories exist...
  if ( $catinfinite ) :
    echo '<div class="postsrelatedcat container-fluid">';
    echo '<div class="container py-spacer container">';
?>

    <div class="text-center">
        <h3 class="fs-1 fw-bold">
            <?php
                $title = get_sub_field('title');
                if ($title) echo esc_html($title);
            ?>
        </h3>
        <p class="mt-4 mb-5">
            <?php
                $subtitle = get_sub_field('subtitle');
                if ($subtitle) echo esc_html($subtitle);
            ?>
        </p>
    </div>  

    <div class="container-fluid p-0">
      <div class="row p-0">
        <div class="col-md-12 mb-5">
            <div class="grid row<?php if($sameheight) echo ' align-items-stretch'; ?>" <?php if(!$sameheight) echo "data-masonry='{\"percentPosition\": true }'"; ?>>

            <?php
              // 2) Setup $paged the same for front page or other
              global $paged;
if ( is_front_page() && ! is_home() ) {
    // Because front-page as a static page can use 'page'
    $paged = ( get_query_var('page') ) ? get_query_var('page') : 1;
} else {
    $paged = ( get_query_var('paged') ) ? get_query_var('paged') : 1;
}

              // Get tags if any
              $related_tags = get_sub_field('relatedtag');

              // 3) Combine all category IDs into a single query
              $args = array(
                  'cat'            => implode(',', $catinfinite),
                  'paged'          => $paged,
                  'posts_per_page' => get_option('posts_per_page'), // Pulls from WP Reading Settings
				  'post_status'    => array( 'publish', 'private' ) // Include private posts
              );

              if (!empty($related_tags)) {
                  $args['tag__in'] = $related_tags;
              }

              $the_query = new WP_Query($args);

              // 4) The Loop
              if ($the_query->have_posts()) :
                  // Define columns or fallback if not set
                  $columns   = isset($columns) ? $columns : 3;
                  // Define image size or fallback
                  $imagesize = isset($imagesize) ? $imagesize : 'medium';

                  while ($the_query->have_posts()) : $the_query->the_post();
            ?>

            <!-- Single Post Layout -->
            <div class="grid-item mb-4 col-md-<?php echo intval(12 / $columns); ?><?php if($sameheight) echo ' d-flex'; ?>">
              <div class="shadow card<?php if($sameheight) echo ' h-100'; ?>">

                <?php
                  if (has_post_thumbnail()) {
                      $image_data   = wp_get_attachment_image_src(get_post_thumbnail_id($post->ID), $imagesize);
                      $image_width  = $image_data[1];
                      $image_height = $image_data[2];

                      // SVG placeholder
                      $svgPlaceholder = 'data:image/svg+xml;base64,' . base64_encode(
                        '<svg xmlns="http://www.w3.org/2000/svg" width="' . $image_width . '" height="' . $image_height . '" viewBox="0 0 ' . $image_width . ' ' . $image_height . '">
                           <rect width="100%" height="100%" fill="#f8f9fb"/>
                         </svg>'
                      );
                ?>
                    <a href="<?php echo get_permalink($post->ID); ?>">
                      <img
                        src="<?php echo $svgPlaceholder; ?>"
                        data-src="<?php echo get_the_post_thumbnail_url($post->ID, $imagesize); ?>"
                        class="border-bottom m-0 img-fluid card-img-top lazyload"
                        alt="<?php the_title(); ?>"
                        width="<?php echo $image_width; ?>"
                        height="<?php echo $image_height; ?>"
                      >
                    </a>
                <?php } ?>

                <div class="card-body pb-4">
                  <small class="lh-lg text-muted">
                    <a href="<?php
                      foreach((get_the_category()) as $category) {
                          if($category->parent != 1) {
                              echo get_category_link($category->cat_ID);
                          }
                      }
                    ?>">
                      <?php
                        foreach((get_the_category()) as $category) {
                            if($category->parent != 1) {
                                echo $category->cat_name . ' ';
                            }
                        }
                      ?>
                    </a>
                  </small>

                  <a href="<?php echo get_permalink($post->ID); ?>">
                    <?php if ( is_single() || is_page() || is_tag() ) : ?>
                      <p class="h3 mt-2 fw-bold text-dark card-title"><?php the_title(); ?></p>
                    <?php else : ?>
                      <h3 class="mt-2 fw-bold text-dark card-title"><?php the_title(); ?></h3>
                    <?php endif; ?>
                  </a>

                  <small class="font-italic text-muted">
                    <?php echo get_the_time('jS', $post->ID); ?> <?php echo get_the_time('M, Y', $post->ID); ?>
                  </small>

                  <?php
                    $description = get_field('description'); 
                    if ($description):
                      echo '<p class="my-3 card-text">' . esc_html($description) . '</p>';
                    endif;
                  ?>

                  <a href="<?php echo get_permalink($post->ID); ?>" class="btn btn-primary mt-3" aria-label="<?php the_title(); ?>">
                    <i class="text-white fa-solid fa-arrow-right-long"></i>
                  </a>
                </div>
              </div><!-- /.card -->
            </div><!-- /.grid-item -->

            <?php
                  endwhile; // end while have_posts
              endif; // end if have_posts
              wp_reset_postdata();
            ?>

          </div><!-- /.grid row -->

          
		  
		  <?php
		  
		  if ( $the_query->max_num_pages > 1 ) { ?>
		  <div class="scroller-status">
		      <div class="loader-ellips infinite-scroll-request col-1 ps-5 my-5 position-absolute start-50 translate-middle">
              <span class="loader-ellips__dot"></span>
              <span class="loader-ellips__dot"></span>
              <span class="loader-ellips__dot"></span>
              <span class="loader-ellips__dot"></span>
            </div>
    
            <p class="infinite-scroll-last"></p>
            <p class="infinite-scroll-error"></p>
          </div>
<?php } else { ?>
    
<?php } ?>


		  
        
			
			

          <p class="pagination">
            <?php
              // If more pages remain, show next link
              if ( isset($the_query) && $the_query->max_num_pages > 1 ) {
                  $total_pages = $the_query->max_num_pages;
                  if ( $paged < $total_pages ) {
                      $next_page_link = get_pagenum_link($paged + 1);
                      echo '<a href="' . esc_url($next_page_link) . '" class="pagination__next"></a>';
                  }
              }
            ?>
          </p>

        </div><!-- /.col-md-12 -->
      </div><!-- /.row -->
    </div><!-- /.container-fluid -->

<?php
    // Close main containers
    echo '</div><!-- /.container -->';
    echo '</div><!-- /.postsrelatedcat container-fluid -->';

  // If there’s no category chosen, fallback
  else :
    echo '<p>No categories selected.</p>';
  endif;
?>
</div> <!-- /.postsrelatedcat -->











    <?php
} else {
    // Code to display if the infinite checkbox is NOT checked
    ?>









<div class="postsrelatedcat">

<?php $postscount = get_sub_field('postscount', $term); //register how many posts ?>


<?php $term = get_queried_object(); $postsrelatedcat = get_sub_field('postsrelatedcat', $term); // get related posts?>
<?php if( get_sub_field('postsrelatedcat', $term) ): ?>

<div class="container-fluid bg-light">
<div class="py-spacer container">

<div class="text-center">
<h3 class="fs-1 fw-bold" ><?php $title = get_sub_field('title'); if ($title) echo esc_html($title); ?></h3>
<p class="mt-4 mb-5"><?php $subtitle = get_sub_field('subtitle'); if ($subtitle) echo esc_html($subtitle); ?></p>
</div>  


		  
<div class="row mt-3<?php if($sameheight) echo ' align-items-stretch'; ?>" <?php if(!$sameheight) echo "data-masonry='{\"percentPosition\": true }'"; ?>>


    <?php 
	

$delay = 0; // Initialize delay counter for animation (Place this before the loop)

  global $wp_query;
        $args = array(
      
        'cat' => $postsrelatedcat, 
        'posts_per_page' => -1
		
		); //get all posts

		// Retrieve the selected category and tags
$related_tags = get_sub_field('relatedtag');

// If related tags are selected, add them to the query
if (!empty($related_tags)) {
    $args['tag__in'] = $related_tags;
}


$posts = get_posts($args);

$count=0; // define count to stop the loop in a certen number of posts
if ($posts)  { // define if to start counting
	
foreach ($posts as $post) : ?>

<div class="pb-4 col-md-<?php echo intval(12 / $columns); ?><?php if($sameheight) echo ' d-flex'; ?>" data-aos="fade-up" data-aos-delay="<?php echo $delay; ?>">
    <div class="shadow card<?php if($sameheight) echo ' h-100'; ?>">
      <?php
                  if (has_post_thumbnail()) {
                      $image_data   = wp_get_attachment_image_src(get_post_thumbnail_id($post->ID), $imagesize);
                      $image_width  = $image_data[1];
                      $image_height = $image_data[2];

                      // SVG placeholder
                      $svgPlaceholder = 'data:image/svg+xml;base64,' . base64_encode(
                        '<svg xmlns="http://www.w3.org/2000/svg" width="' . $image_width . '" height="' . $image_height . '" viewBox="0 0 ' . $image_width . ' ' . $image_height . '">
                           <rect width="100%" height="100%" fill="#f8f9fb"/>
                         </svg>'
                      );
                ?>
                    <a href="<?php echo get_permalink($post->ID); ?>">
                      <img
                        src="<?php echo $svgPlaceholder; ?>"
                        data-src="<?php echo get_the_post_thumbnail_url($post->ID, $imagesize); ?>"
                        class="border-bottom m-0 img-fluid card-img-top lazyload"
                        alt="<?php the_title(); ?>"
                        width="<?php echo $image_width; ?>"
                        height="<?php echo $image_height; ?>"
                      >
                    </a>
                <?php } ?>
            
      
      
      <div class="card-body pb-4">
        
        <small class="lh-lg text-muted"><a href="<?php foreach((get_the_category()) as $category) { if($category->parent != 1){  //load category?> <?php echo get_category_link($category->cat_ID) //echo the link?><?php } } ?>"><?php foreach((get_the_category()) as $category) { if($category->parent != 1){ echo $category->cat_name . ' '; } } //echo the first category name ?></a></small>
        
        <a href="<?php echo get_permalink($post->ID) // get the loop-item the link ?>"><p class="h3 fw-bold mt-2 text-dark card-title"><?php the_title(); ?></p></a>
        
        <small class="font-italic text-muted"><?php echo get_the_time('jS', $post->ID); // get the loop-item the time ?> <?php echo get_the_time('M, Y', $post->ID); // get the loop-item the time  ?></small>
        
        
        	         <?php 
$description = get_field('description'); 
if ($description): ?>
    <p class="my-3 card-text"><?php echo $description; ?></p>
<?php endif; ?>
        
		<a href="<?php echo get_permalink($post->ID) ?>" class="btn btn-primary mt-3" aria-label="<?php the_title(); ?>"><i class="text-white fa-solid fa-arrow-right-long"></i></a>
		
         </div>
    </div>
  </div>




<?php 
$count++;
if( $count == $postscount ) break; //change the number to adjust the count ?>
 
<?php $delay += 400; // Increase delay by 200ms for each item ?>
 
<?php endforeach; ?>

 




 
<?php } // end if to end counting ?>


</div>
 

<?php 
if ($addmore) { // Code to display if the addmore checkbox is checked 
    if (!empty(get_sub_field('postsrelatedcat'))) { 
    $category_id = is_array(get_sub_field('postsrelatedcat')) ? intval(get_sub_field('postsrelatedcat')[0]) : intval(get_sub_field('postsrelatedcat')); 
    $category = get_category($category_id);
?>
<div class="my-4 justify-content-center text-center">
    <a class="btn btn-primary" href="<?php echo get_category_link($category_id); ?>">
        Full List Of <?php echo esc_html($category->name); ?>
    </a>
</div>
<?php
    }
}
?>
 
<?php wp_reset_query(); ?>        
</div>
</div>
<?php else: //if there not fire default ?>
<?php endif; // end get related posts?>



</div>










    <?php
}
?>




<!-- postsrelatedcat  -->






<!-- postsrelatedcatslider -->

<?php elseif( get_row_layout() == 'postsrelatedcatslider' ): ?>


<div class="postsrelatedtagslider py-spacer bg-light">

<div class="text-center">
<h3 class="fs-1 fw-bold" ><?php $title = get_sub_field('title'); if ($title) echo esc_html($title); ?></h3>
<p class="mt-4 mb-3"><?php $subtitle = get_sub_field('subtitle'); if ($subtitle) echo esc_html($subtitle); ?></p>
</div>  

<?php
// Global variable to track carousel instances across multiple includes (if not already defined elsewhere)
global $sliderCounter;
if (!isset($sliderCounter)) {
  $sliderCounter = 1;
}
?>

<div id="slider<?php echo $sliderCounter; ?>" data-bs-pause="false" data-bs-interval="7000" class="lazy-load carousel keyboard touch slide d-flex align-items-center slider-height-<?php echo get_sub_field('height'); ?> p-0 <?php while ( have_rows('column') ) : the_row(); $column_container = get_sub_field('column_container'); $column_width = get_sub_field('column_width'); ?><?php echo $column_container ?><?php echo $column_width ?><?php endwhile; ?> <?php $center = get_sub_field('center'); if( $center && in_array('center', $center) ) { ?> text-center<?php } else { ?><?php } ?> mx-4" data-bs-ride="carousel">

  <div class="carousel-indicators">
    <ul class="list-unstyled d-flex justify-content-center m-0 p-0">
      <?php 
      $termss = get_sub_field('postsrelatedcat'); // Make sure this is a field containing category IDs
      $total_posts = 0;

      $displayed_post_ids = array(); // Initialize the array to store displayed post IDs

      foreach ($termss as $postsrelatedcat) {
        $args = array(
          'cat' => $postsrelatedcat, // Fetch posts by category ID
          'posts_per_page' => get_sub_field('postscount') // Number of posts per slide
        );
		
			// Retrieve the selected category and tags
$related_tags = get_sub_field('relatedtag');
// If related tags are selected, add them to the query
if (!empty($related_tags)) {
    $args['tag__in'] = $related_tags;
}


        $posts = get_posts($args);

        foreach ($posts as $post) {
            if (!in_array($post->ID, $displayed_post_ids)) {
                $displayed_post_ids[] = $post->ID; // Add the post ID to the array
                $total_posts++;
            }
        }
      }

      for($i = 0; $i < $total_posts; $i++) { ?>
        <li data-bs-target="#slider<?php echo $sliderCounter; ?>" data-bs-slide-to="<?php echo $i; ?>" <?php if($i == 0) echo 'class="active"'; ?>></li>
      <?php } ?>
    </ul>
  </div>


<div class="carousel-inner my-3">
    <?php
    $post_index = 0;
    foreach ($displayed_post_ids as $post_id) {
      $post = get_post($post_id);
      setup_postdata($post);

      $imagesize = get_sub_field('imagesize'); // Get the image size option
      $image_data = wp_get_attachment_image_src(get_post_thumbnail_id($post->ID), $imagesize);
      $image_url = $image_data[0];
      $image_width = $image_data[1];
      $image_height = $image_data[2];
      $title = get_the_title();
      $description = get_field('description'); // Use the ACF field for description
      $link = get_permalink($post->ID);

      // Generate SVG placeholder
      $svg_placeholder = 'data:image/svg+xml;base64,' . base64_encode(
          '<svg xmlns="http://www.w3.org/2000/svg" width="' . $image_width . '" height="' . $image_height . '" viewBox="0 0 ' . $image_width . ' ' . $image_height . '">
              <rect width="100%" height="100%" fill="#f8f9fb"/>
          </svg>'
      );
    ?>
      <div class="carousel-item mb-5 <?php if($post_index == 0) echo 'active'; ?>">
        <div class="container shadow bg-white p-0 my-3">
          <div class="row justify-content-between d-flex">
		  
		  
            <div class="col-md-5 d-flex align-items-stretch">
			
              <img class="img-full image-fill img-fluid lazyload" 
                   alt="<?php echo $title; ?>" 
                   src="<?php echo $svg_placeholder; ?>" 
                   data-src="<?php echo $image_url; ?>" 
                   width="<?php echo $image_width; ?>" 
                   height="<?php echo $image_height; ?>">
				  
            </div>
			
			
            <div class="col-md-7 my-5">
              <div class="p-4">
                <a href="<?php echo $link; ?>">
                  <p class="fs-2 fw-bold"><?php echo $title; ?></p>
                </a>
                <p><?php echo $description; ?></p>
                <a href="<?php echo $link; ?>" class="btn btn-primary mt-3">
                  <i class="text-white fa-solid fa-arrow-right-long"></i>
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>
    <?php
        $post_index++;
    }
    wp_reset_postdata(); 
    ?>
</div>


</div>

<?php $sliderCounter++; // Increment counter for next slider instance ?>

</div>

<!-- postsrelatedcatslider -->













<!-- connections -->

<?php elseif( get_row_layout() == 'tagconnection' ): ?>
<?php $termsss = get_sub_field('tagconnection'); ?> 
<?php foreach ($termsss as $tagconnection) { // let the loop understand the values you would like to echo  ?>

 <div class="tagconnection container-fluid bg-secondary">

   
   <div class="row justify-content-between d-flex">
   
   
   <div class="p-0 mb-md-0 col-md-6 d-flex align-items-stretch">
   
    
	 <div class="image-cover-container">
     <a href="<?php echo get_tag_link($tagconnection); ?>">
    <?php 
        $image = get_field('image', $tagconnection);
        $size = 'large';
        if (!empty($image)) {
            $image_url = $image['sizes'][$size];
            $width = $image['sizes'][$size . '-width'];
            $height = $image['sizes'][$size . '-height'];

            // Generate SVG placeholder
            $svg_placeholder = 'data:image/svg+xml;base64,' . base64_encode(
                '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="' . $height . '" viewBox="0 0 ' . $width . ' ' . $height . '">
                    <rect width="100%" height="100%" fill="#f8f9fb"/>
                </svg>'
            );
    ?>
            <img class="img-full lazyload image-fill image-cover" 
                 width="<?php echo $width; ?>" 
                 height="<?php echo $height; ?>" 
                 src="<?php echo $svg_placeholder; ?>" 
                 data-src="<?php echo $image_url; ?>" 
                 alt="<?php echo $image['alt']; ?>" />
    <?php } ?>
</a>
</div>
   
     </div>
   
   <div class="col-md-6 text-white px-spacer py-spacer">



<p class="fs-4 lh-sm" ><?php $title = get_sub_field('title'); if ($title) echo esc_html($title); ?></p>

    <h3 class="fw-bold fs-1 lh-sm">
      
    <?php $icon = get_field('icon', $tagconnection);?><i class="me-2 fa-1x fa fa-<?php echo $icon; ?>"></i>
      
      <strong> <a class="text-white" href="<?php echo get_tag_link($tagconnection); ?>"><?php  echo $tagconnection->name; ?></a></strong></h3>


     <?php $description = get_field('description', $tagconnection);?><?php if( get_field('description', $tagconnection) ): ?><p class="mt-4"><?php echo $description; ?></p><?php endif; ?> 
     
	 
           <p class="lead mt-4 mb-4"><?php $subtitle = get_sub_field('subtitle'); if ($subtitle) echo esc_html($subtitle); ?></p>
    
	


    <a href="<?php echo get_tag_link($tagconnection); ?>" class="btn btn-light">More Details</a>

     </div>
   
   </div>
   
   

</div>
<?php }  wp_reset_query(); // end of let the loop understand the values you would like to echo ?>   


<?php elseif( get_row_layout() == 'catconnection' ): ?>
<?php $termsss = get_sub_field('catconnection'); ?> 
<?php foreach ($termsss as $catconnection) { // let the loop understand the values you would like to echo  ?>

 <div class="catconnection container-fluid bg-secondary">

   
   <div class="row justify-content-between d-flex">
   
   
   <div class="p-0 mb-md-0 col-md-6 d-flex align-items-stretch">
   
    
	  <div class="image-cover-container">
     <a href="<?php echo get_tag_link($catconnection); ?>">
    <?php 
        $image = get_field('image', $catconnection);
        $size = 'large';
        if (!empty($image)) {
            $image_url = $image['sizes'][$size];
            $width = $image['sizes'][$size . '-width'];
            $height = $image['sizes'][$size . '-height'];

            // Generate SVG placeholder
            $svg_placeholder = 'data:image/svg+xml;base64,' . base64_encode(
                '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="' . $height . '" viewBox="0 0 ' . $width . ' ' . $height . '">
                    <rect width="100%" height="100%" fill="#f8f9fb"/>
                </svg>'
            );
    ?>
            <img class="img-full lazyload image-fill image-cover" 
                 width="<?php echo $width; ?>" 
                 height="<?php echo $height; ?>" 
                 src="<?php echo $svg_placeholder; ?>" 
                 data-src="<?php echo $image_url; ?>" 
                 alt="<?php echo $image['alt']; ?>" />
    <?php } ?>
</a>
</div>
   
     </div>
   
   <div class="col-md-6 text-white px-spacer py-spacer" data-aos="fade-up">



<p class="fs-4 lh-sm" ><?php $title = get_sub_field('title'); if ($title) echo esc_html($title); ?></p>

    <h3 class="fw-bold fs-1 lh-sm">
      
    <?php $icon = get_field('icon', $catconnection);?><i class="me-2 fa-1x fa fa-<?php echo $icon; ?>"></i>
      
      <strong> <a class="text-white" href="<?php echo get_tag_link($catconnection); ?>"><?php  echo $catconnection->name; ?></a></strong></h3>


     <?php $description = get_field('description', $catconnection);?><?php if( get_field('description', $catconnection) ): ?><p class="mt-4"><?php echo $description; ?></p><?php endif; ?> 
     
	 
           <p class="lead mt-4 mb-4"><?php $subtitle = get_sub_field('subtitle'); if ($subtitle) echo esc_html($subtitle); ?></p>
    
	


    <a href="<?php echo get_tag_link($catconnection); ?>" class="btn btn-light">More Details</a>

     </div>
   
   </div>
   
   

</div>
<?php }  wp_reset_query(); // end of let the loop understand the values you would like to echo ?>   


<?php elseif( get_row_layout() == 'postconnection' ): ?>
<?php $page =  get_sub_field('postconnection');  ?>   
<?php foreach( $page as $post ): ?>
 <div class="postconnection container-fluid bg-secondary">







<div class="row justify-content-between d-flex">
    <div class="p-0 mb-md-0 col-md-6 d-flex align-items-stretch">
        <?php 
        $image_data = wp_get_attachment_image_src(get_post_thumbnail_id($post->ID), "large");
        if ($image_data): 
            $image_url = $image_data[0];
            $image_width = $image_data[1];
            $image_height = $image_data[2];

            // Generate SVG placeholder
            $svg_placeholder = 'data:image/svg+xml;base64,' . base64_encode(
                '<svg xmlns="http://www.w3.org/2000/svg" width="' . $image_width . '" height="' . $image_height . '" viewBox="0 0 ' . $image_width . ' ' . $image_height . '">
                    <rect width="100%" height="100%" fill="#f8f9fb"/>
                </svg>'
            );
        ?>
        <div class="image-cover-container">
            <a href="<?php echo get_permalink($post->ID); ?>">
                <img src="<?php echo $svg_placeholder; ?>" 
                     data-src="<?php echo $image_url; ?>" 
                     class="lazyload image-cover" 
                     alt="<?php the_title(); ?>">
            </a>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-md-6 text-white px-spacer py-spacer">
        <p class="fs-4 lh-sm"><?php $title = get_sub_field('title'); if ($title) echo esc_html($title); ?></p>
        <h3 class="mt-2 fw-bold fs-1 lh-sm">
            <strong><a class="text-white" href="<?php echo get_permalink($post->ID); ?>"><?php the_title(); ?></a></strong>
        </h3>
        <?php $description = get_field('description'); ?>
        <?php if ($description): ?>
            <p class="card-text"><?php echo $description; ?></p>
        <?php endif; ?>
        <a href="<?php echo get_permalink($post->ID); ?>" class="mt-3 btn btn-light">See Details</a>
    </div>
</div>



 </div>
<?php endforeach; wp_reset_query(); ?>    



 <!-- connections -->





<!-- imgcarousel -->

<?php elseif( get_row_layout() == 'imgcarousel' ): ?>



<div class="imgcarousel container contentfield<?php $center = get_sub_field('center'); if( $center && in_array('center', $center) ) { ?> text-center<?php } else { ?><?php } ?>">







<?php

// Global variable to track carousel instances across multiple includes
global $carouselCounter;
if (!isset($carouselCounter)) {
  $carouselCounter = 1;
}

$imgcarousel = get_sub_field('imgcarousel');
if ($imgcarousel) :
  $isFirst = true;
  $startInterval = 5000;
  $intervalIncrement = $startInterval + (1000 * ($carouselCounter - 1)); // Adjust this value as needed

  ?>

  <div id="imgcarousel<?php echo $carouselCounter; ?>" data-bs-pause="false" data-bs-interval="<?php echo $intervalIncrement; ?>" class="imgcarousel carousel slide my-3 <?php while (have_rows('column')) : the_row(); $column_container = get_sub_field('column_container'); $column_width = get_sub_field('column_width'); ?><?php echo $column_container ?><?php echo $column_width ?> <?php endwhile; ?>" data-bs-ride="carousel">



 
  
  
<div class="carousel-indicators">
  <ul class="list-unstyled d-flex justify-content-center m-0 p-0">  <?php if (have_rows('imgcarousel')): $counter = 0; ?>
      <?php while (have_rows('imgcarousel')): the_row(); ?>

        <?php if ($counter == 0) { ?>
          <li data-bs-target="#imgcarousel<?php echo $carouselCounter; ?>" data-bs-slide-to="0" class="active" title="Slide 1"></li>
          <?php $i = 1; ?>
        <?php } ?>

        <?php if ($counter > 0) { ?>
          <li data-bs-target="#imgcarousel<?php echo $carouselCounter; ?>" data-bs-slide-to="<?php echo $i++; ?>" title="Slide <?php echo $i; ?>"></li>
        <?php } ?>

        <?php $counter++; endwhile; ?>
    <?php endif; ?>
  </ul>
</div>



    <div class="carousel-inner">

      <?php foreach ($imgcarousel as $image) : ?>
        <?php
          $size = 'large';
          $width = $image['sizes'][$size . '-width'];
          $height = $image['sizes'][$size . '-height'];

          // Add this line to set the $title variable
          $title = $image['title'];

          $lazyClass = $isFirst ? '' : 'lazyload';
          $lazyDataSrc = $isFirst ? $image['sizes']['large'] : 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=';
        ?>
        <div class="carousel-item <?php echo $isFirst ? 'active' : ''; ?>">
          <img class="<?php echo $lazyClass; ?> img-fluid m-0" data-expand="-100" width="<?php echo $width; ?>" height="<?php echo $height; ?>" alt="<?php echo $title; ?>" src="<?php echo $lazyDataSrc; ?>" data-src="<?php echo $image['sizes']['large']; ?>">
        </div>
        <?php
          $isFirst = false;
        endforeach;
        ?>

    </div>
    <button class="carousel-control-prev" type="button" data-bs-target="#imgcarousel<?php echo $carouselCounter; ?>" data-bs-slide="prev">
      <span aria-hidden="true"><i class="fas fa-chevron-circle-left text-white fa-lg"></i></span>
      <span class="visually-hidden">Previous</span>
    </button>
    <button class="carousel-control-next" type="button" data-bs-target="#imgcarousel<?php echo $carouselCounter; ?>" data-bs-slide="next">
      <span aria-hidden="true"><i class="fas fa-chevron-circle-right text-white fa-lg"></i></span>
      <span class="visually-hidden">Next</span>
    </button>
  </div>

  <?php
  $carouselCounter++;
endif;
?>



</div>

<!-- imgcarousel -->  




<!-- slider --> 

<?php elseif( get_row_layout() == 'slider' ): ?>


<!-- //define if column start --> 
<?php while ( have_rows('column') ) : the_row(); $column_position = get_sub_field('column_position');?>
<?php if (strpos($column_position, 'start') !== false) { //if cond. ?><div class="container-fluid"> <div class="d-flex row align-items-stretch p-0"> 
<?php } ?>
<?php endwhile; ?>
<!-- //define if column start --> 


<?php
// Global variable to track carousel instances across multiple includes (if not already defined elsewhere)
global $sliderCounter;
if (!isset($sliderCounter)) {
  $sliderCounter = 1;
}
?>

<div id="slider<?php echo $sliderCounter; ?>" data-bs-pause="false" data-bs-interval="7000" class="bg-dark px-0 slider lazy-load carousel keyboard touch slide slider-animation carousel-fade d-flex align-items-center <?php echo "slider-height-" . get_sub_field('height'); ?> p-0 <?php while ( have_rows('column') ) : the_row(); $column_container = get_sub_field('column_container'); $column_width = get_sub_field('column_width'); ?><?php echo $column_container ?><?php echo $column_width ?><?php endwhile; ?> <?php $center = get_sub_field('center'); if( $center && in_array('center', $center) ) { ?> text-center<?php } else { ?><?php } ?>" data-bs-ride="carousel">

  <div class="carousel-indicators">
    <ul class="list-unstyled d-flex justify-content-center m-0 p-0">

<?php // Get the value of 'home_page' checkbox
$home_page_checked = get_sub_field('home_page');
?>

      <?php if( have_rows('slider') ): $counter = 0; ?>
      <?php $i = 0; ?>
      <?php while( have_rows('slider') ): the_row(); ?>

        <?php if( $counter == 0 ) { ?>
          <li data-bs-target="#slider<?php echo $sliderCounter; ?>" data-bs-slide-to="0" class="active"></li>
          <?php $i = 1; ?>
        <?php } ?>

        <?php if( $counter > 0 ) { ?>
          <li data-bs-target="#slider<?php echo $sliderCounter; ?>" data-bs-slide-to="<?php echo $i++; ?>"></li>

        <?php } ?>


        <?php $counter++; endwhile; ?>
      <?php endif; ?>


    </ul>
  </div>


  <div class="carousel-inner">


    <?php if( have_rows('slider') ): $counter = 0; ?>
    <?php while( have_rows('slider') ): the_row();

      $title = get_sub_field('title');
      $description = get_sub_field('description');
      $image = get_sub_field('image');
      $link = get_sub_field('link');	

      ?>


      <?php
$size = 'full';
if ($size === 'full') {
    $url = $image['url']; // Use 'url' for full size
    $width = $image['width']; // Top-level width
    $height = $image['height']; // Top-level height
} else {
    $url = $image['sizes'][$size]; // Use resized version
    $width = $image['sizes'][$size . '-width']; // Resized width
    $height = $image['sizes'][$size . '-height']; // Resized height
}


?>  <?php if( $counter == 0 ) { ?>

      <div class="carousel-item active">

        <img class="img-fluid" width="<?php echo $width; ?>" height="<?php echo $height; ?>" alt="<?php echo $title; ?>" src="<?php echo $url; ?>">


<?php if ($link && $link !== 'none') : ?>
    <a href="<?php echo get_tag_link($link); ?>">
<?php endif; ?>

    <div class="carousel-caption">
	
	
	
<?php // Check if 'home_page' is checked
                if ($home_page_checked) { ?>
                    <?php 
$title = get_field('title', get_option('page_on_front')); 
?>

<h1 class="slider-title display-3 fw-bold"><?php echo $title; ?></h1>
                <?php } else { // If 'home_page' is not checked ?>
                    <h2 class="slider-title display-3 fw-bold"><?php echo $title; ?></h2>
<?php } ?>
	
	
	
	
        
        <p class="slider-subtitle fs-3"><?php echo $description; ?></p>
    </div>

<?php if ($link && $link !== 'none') : ?>
    </a>
<?php endif; ?>

      </div>

      <?php } ?>



    



    <?php if( $counter > 0 ) { ?>

      <div class="carousel-item">
	  

        <img class="lazyload img-fluid" data-expand="-100" width="<?php echo $width; ?>" height="<?php echo $height; ?>" alt="<?php echo $title; ?>" src="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIxIiBoZWlnaHQ9IjEiPjxyZWN0IHdpZHRoPSIxIiBoZWlnaHQ9IjEiIGZpbGw9ImJsYWNrIiAvPjwvc3ZnPg==" data-src="<?php echo $url; ?>">



<?php if ($link && $link !== 'none') : ?>
    <a href="<?php echo get_tag_link($link); ?>">
<?php endif; ?>

    <div class="carousel-caption">
        <h2 class="slider-title display-3 fw-bold"><?php echo $title; ?></h2>
        <p class="slider-subtitle fs-3"><?php echo $description; ?></p>
    </div>

<?php if ($link && $link !== 'none') : ?>
    </a>
<?php endif; ?>
		
		
      </div>
      

    <?php } ?>


  
    <?php $counter++; endwhile; ?>
  <?php endif; ?>



  </div>
  <a class="carousel-control-prev" href="#slider<?php echo $sliderCounter; ?>" role="button" data-bs-slide="prev">
    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
    <span class="visually-hidden">Previous</span>
  </a>
  <a class="carousel-control-next" href="#slider<?php echo $sliderCounter; ?>" role="button" data-bs-slide="next">
    <span class="carousel-control-next-icon" aria-hidden="true"></span>
    <span class="visually-hidden">Next</span>
  </a>
</div>

<?php $sliderCounter++; // Increment counter for next slider instance ?>



<!-- //define if column end --> 
<?php while ( have_rows('column') ) : the_row(); $column_position = get_sub_field('column_position');?>
<?php if (strpos($column_position, 'end') !== false) { //if cond. ?></div> </div> 
<?php } ?>
<?php endwhile; ?>
<!-- //define if column end -->   


<!-- slider -->




<!-- hero video -->
 
<?php elseif( get_row_layout() == 'hero-video' ): ?>
   
   
<!-- //define if column start --> 
<?php while ( have_rows('column') ) : the_row(); $column_position = get_sub_field('column_position');?>
<?php if (strpos($column_position, 'start') !== false) { //if cond. ?><div class="container-fluid"> <div class="d-flex row align-items-stretch p-0"> 
<?php } ?>
<?php endwhile; ?>
<!-- //define if column start --> 




   
   
   <div class="hero-video <?php while ( have_rows('column') ) : the_row(); $column_container = get_sub_field('column_container'); $column_width = get_sub_field('column_width'); ?><?php echo $column_container ?><?php echo $column_width ?> align-items-center p-0 <?php endwhile; ?>">
   
    <div data-jarallax data-speed="0.2" class="jarallax video-jarallax bg-dark" data-jarallax-video="https://www.youtube.com/watch?v=<?php $video = get_sub_field('video'); if ($video) echo esc_html($video); ?>">
    
	
	
	
	
    <div class="video-jarallax-content bg-dark-trans text-white text-center">
	
	

	

   
    <h3 class="mt-0 mb-4 display-3 fw-bold lh-sm"><?php $title = get_sub_field('title'); if ($title) echo esc_html($title); ?></h3>
	 <p class="mt-0 mb-5 h3 lh-sm"><?php $subtitle = get_sub_field('subtitle'); if ($subtitle) echo esc_html($subtitle); ?></p>
   
	
	




            <?php $button1 = get_sub_field('button1'); ?>
            <?php if ($button1): ?>
                <a href="<?php $link1 = get_sub_field('link1'); if ($link1) echo esc_html($link1); ?>" class="my-3 btn btn-secondary btn-lg m-2"><?php $button1 = get_sub_field('button1'); if ($button1) echo esc_html($button1); ?> <i class="fas fa-long-arrow-alt-right ms-2"></i></a>
            <?php endif; ?>
	
	
     <?php $button2 = get_sub_field('button2'); ?>
            <?php if ($button2): ?>
                <a href="<?php $link2 = get_sub_field('link2'); if ($link2) echo esc_html($link2); ?>" class="my-3 btn btn-light btn-lg m-2"><?php $button2 = get_sub_field('button2'); if ($button2) echo esc_html($button2); ?> <i class="fas fa-long-arrow-alt-right ms-2"></i></a>
            <?php endif; ?>
  
    
	
	
	
    </div>





</div>
       </div>
  <!-- hero video -->


<!-- //define if column end --> 
<?php while ( have_rows('column') ) : the_row(); $column_position = get_sub_field('column_position');?>
<?php if (strpos($column_position, 'end') !== false) { //if cond. ?></div> </div> 
<?php } ?>
<?php endwhile; ?>
<!-- //define if column end -->   




<!-- Testimonial Slider Section -->

<?php elseif( get_row_layout() == 'testimonial' ): ?>

<div class="testimonial py-spacer bg-light">

<div class="text-center">
<h3 class="fs-1 fw-bold" ><?php $title = get_sub_field('title'); if ($title) echo esc_html($title); ?></h3>
<p class="mt-4 mb-3"><?php $subtitle = get_sub_field('subtitle'); if ($subtitle) echo esc_html($subtitle); ?></p>
</div>  
<?php
// Global variable to track carousel instances across multiple includes (if not already defined elsewhere)
global $testimonialSliderCounter;
if (!isset($testimonialSliderCounter)) {
  $testimonialSliderCounter = 1;
}
?>

<div id="testimonial-slider<?php echo $testimonialSliderCounter; ?>" class="lazy-load carousel keyboard touch slide d-flex align-items-center mx-4" data-bs-ride="carousel">

  <!-- Carousel Indicators -->
  <div class="carousel-indicators">
    <ul class="list-unstyled d-flex justify-content-center m-0 p-0">
      <?php
      $args = array(
        'post_type' => 'post',
        'meta_query' => array(
          array(
            'key' => 'testimonial',
            'compare' => '!=',
            'value' => '',
          ),
        ),
        'posts_per_page' => get_sub_field('number_of_testimonials')
      );

      $testimonial_query = new WP_Query($args);
      $testimonialIndex = 0;

      if ($testimonial_query->have_posts()) :
        while ($testimonial_query->have_posts()) :
          $testimonial_query->the_post();
          ?>
          <li data-bs-target="#testimonial-slider<?php echo $testimonialSliderCounter; ?>" data-bs-slide-to="<?php echo $testimonialIndex; ?>" <?php if($testimonialIndex == 0) echo 'class="active"'; ?>></li>
          <?php
          $testimonialIndex++;
        endwhile;
        wp_reset_postdata();
      endif;
      ?>
    </ul>
  </div>

  <!-- Carousel Items -->
  <div class="carousel-inner my-3">
    <?php
    $testimonialIndex = 0;
    $testimonial_query = new WP_Query($args);

    if ($testimonial_query->have_posts()) :
      while ($testimonial_query->have_posts()) :
        $testimonial_query->the_post();

        if (have_rows('testimonial')) :
          while (have_rows('testimonial')) : the_row();
            $name = get_sub_field('name');
            $position = get_sub_field('position');
            $testimonial_text = get_sub_field('testimonial_text');
            $stars = get_sub_field('stars');
            $client_logo = get_field('client_logo');
            ?>
            <div class="carousel-item mb-5 <?php if($testimonialIndex == 0) echo 'active'; ?>">
              <div class="container shadow bg-white p-0 my-3">
                <div class="row justify-content-between d-flex">
                  <div class="col-md-5 d-flex align-items-stretch text-center">
  <?php 
  $image_data = wp_get_attachment_image_src(get_post_thumbnail_id($post->ID), "medium");
  if ($image_data): 
    $image_url = $image_data[0];
    $image_width = $image_data[1];
    $image_height = $image_data[2];

    // Generate SVG placeholder
    $svg_placeholder = 'data:image/svg+xml;base64,' . base64_encode(
        '<svg xmlns="http://www.w3.org/2000/svg" width="' . $image_width . '" height="' . $image_height . '" viewBox="0 0 ' . $image_width . ' ' . $image_height . '">
            <rect width="100%" height="100%" fill="#f8f9fb"/>
        </svg>'
    );
  ?>
    <img class="img-full image-fill img-fluid lazyload" 
         alt="<?php echo $name; ?>" 
         src="<?php echo $svg_placeholder; ?>" 
         data-src="<?php echo $image_url; ?>" 
         width="<?php echo $image_width; ?>" 
         height="<?php echo $image_height; ?>">
  <?php endif; ?>
</div>

                  <div class="col-md-7 my-5">
                    <div class="p-4">
                      <p class="fs-2 fw-bold mb-1"><?php echo $name; ?></p>
					  <p class="h6 mb-3"><?php echo $position; ?></p>
					   <a href="<?php the_permalink(); ?>"><p class="fs-4 mb-3 fw-bold"><?php the_title(); ?></p></a>
                      <p class="fs-5 text-muted">
                        <i class="fas fa-quote-left pe-2"></i>
                        <?php echo $testimonial_text; ?>
                        <i class="fas fa-quote-right ps-2"></i>
                      </p>
                      <ul class="list-unstyled d-flex text-warning mb-0">
                        <?php for ($i = 1; $i <= 5; $i++) {
                          echo '<li><i class="' . ($i <= $stars ? 'fas' : 'far') . ' fa-star fa-sm"></i></li>';
                        } ?>
                      </ul>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <?php
            $testimonialIndex++;
          endwhile;
        endif;

      endwhile;
      wp_reset_postdata();
    endif;
    ?>
  </div>


</div>

<?php $testimonialSliderCounter++; // Increment counter for next testimonial slider ?>
</div>
<!-- Testimonial Slider Section -->




<!-- postsrelatedwithfilter  -->



<?php elseif( get_row_layout() == 'postsrelatedwithfilter' ): ?>


<div class="postsrelatedwithfilter container-fluid bg-light">
  <?php
  // Get the values
  $imagesize = get_sub_field('imagesize'); // Fetch the image size selected in ACF
  $columns = get_sub_field('columns');
  $postscount = get_sub_field('postscount');
  $related_tags = get_sub_field('relatedtag'); // Fetch related tags from ACF
  ?>

  <div class="py-spacer container">
    <div class="text-center">
      <h3 class="fs-1 fw-bold">
        <?php $title = get_sub_field('title'); if ($title) echo esc_html($title); ?>
      </h3>
      <p class="mt-4 mb-5">
        <?php $subtitle = get_sub_field('subtitle'); if ($subtitle) echo esc_html($subtitle); ?>
      </p>
    </div>

    <?php $postsrelatedcat = get_sub_field('postsrelatedcat'); ?>

    <div class="row">
      <!-- Mobile Filter Button -->

<div class="col-12 d-grid">      
<button role='button' class="btn btn-dark d-md-none mb-3" id="openFilter">
  <i class="fa-solid fa-sliders me-2"></i> Filter
</button>
</div>
      <!-- Sidebar Filter -->
      <div class="filter-sidebar d-md-none" id="filterSidebar">
        <button class="close-btn my-3" id="closeFilter"><i class="fa-solid fa-xmark me-2"></i> Close</button>
        <div class="filter-buttons p-3">
          <button class="btn btn-primary mb-2 me-2" data-filter="*">All</button>
           <?php 
          if (!empty($related_tags)) {
    foreach ($related_tags as $tag_id) {
        $tag = get_tag($tag_id);
        echo '<button class="btn btn-outline-primary mb-2" data-filter=".tag-' . $tag->term_id . '">' . esc_html($tag->name) . '</button>';
    }
} else {
    $all_tags = get_tags(array('hide_empty' => false));
    foreach ($all_tags as $tag) {
        echo '<button class="btn btn-outline-primary mb-2" data-filter=".tag-' . $tag->term_id . '">' . esc_html($tag->name) . '</button>';
    }
}
          ?>
        </div>
      </div>

      <!-- Filtering Buttons Column for Desktop -->
      <div class="col-md-3 d-none d-md-block">
        <div class="filter-buttons">
          <button class="btn btn-primary mb-2" data-filter="*">All</button>
          <?php 
          if (!empty($related_tags)) {
    foreach ($related_tags as $tag_id) {
        $tag = get_tag($tag_id);
        echo '<button class="btn btn-outline-primary mb-2" data-filter=".tag-' . $tag->term_id . '">' . esc_html($tag->name) . '</button>';
    }
} else {
    $all_tags = get_tags(array('hide_empty' => false));
    foreach ($all_tags as $tag) {
        echo '<button class="btn btn-outline-primary mb-2" data-filter=".tag-' . $tag->term_id . '">' . esc_html($tag->name) . '</button>';
    }
}
          ?>
        </div>
      </div>

      <!-- Posts Column -->
      <div class="col-md-9">
        <div class="row mt-3 posts-container" data-masonry='{"percentPosition": true }'>
          <?php 
          $args = array(
            'cat' => $postsrelatedcat, 
            'posts_per_page' => -1
          );

          $related_tags = get_sub_field('relatedtag');
          if (!empty($related_tags)) {
            $args['tag__in'] = $related_tags;
          }

          $posts = get_posts($args);
          $count = 0;
          if ($posts) { 
            foreach ($posts as $post) : 
              $post_tags = get_the_tags($post->ID);
              $tag_classes = '';
              if ($post_tags) {
                foreach ($post_tags as $post_tag) {
                  $tag_classes .= ' tag-' . $post_tag->term_id;
                }
              }
          ?>
          <div class="pb-4 col-md-<?php echo intval(12 / $columns); ?> post-item <?php echo $tag_classes; ?>">
            <div class="shadow card">
              <?php
                if (has_post_thumbnail()) {
                    $image_data = wp_get_attachment_image_src(get_post_thumbnail_id($post->ID), $imagesize);
                    $image_width = $image_data[1];
                    $image_height = $image_data[2];

                    $svgPlaceholder = 'data:image/svg+xml;base64,' . base64_encode(
                      '<svg xmlns="http://www.w3.org/2000/svg" width="' . $image_width . '" height="' . $image_height . '" viewBox="0 0 ' . $image_width . ' ' . $image_height . '">
                         <rect width="100%" height="100%" fill="#f8f9fb"/>
                       </svg>'
                    );
              ?>
                  <a href="<?php echo get_permalink($post->ID); ?>">
                    <img
                      src="<?php echo $svgPlaceholder; ?>"
                      data-src="<?php echo get_the_post_thumbnail_url($post->ID, $imagesize); ?>"
                      class="border-bottom m-0 img-fluid card-img-top lazyload"
                      alt="<?php the_title(); ?>"
                      width="<?php echo $image_width; ?>"
                      height="<?php echo $image_height; ?>"
                    >
                  </a>
              <?php } ?>

              <div class="card-body pb-4">
                <small class="lh-lg text-muted">
                  <a href="<?php
                    foreach((get_the_category()) as $category) {
                        if($category->parent != 1) {
                            echo get_category_link($category->cat_ID);
                        }
                    }
                  ?>">
                    <?php
                      foreach((get_the_category()) as $category) {
                          if($category->parent != 1) {
                              echo $category->cat_name . ' ';
                          }
                      }
                    ?>
                  </a>
                </small>

                <a href="<?php echo get_permalink($post->ID); ?>">
                  <?php if ( is_single() || is_page() || is_tag() ) : ?>
                    <p class="h3 mt-2 fw-bold text-dark card-title"><?php the_title(); ?></p>
                  <?php else : ?>
                    <h3 class="mt-2 fw-bold text-dark card-title"><?php the_title(); ?></h3>
                  <?php endif; ?>
                </a>

                <small class="font-italic text-muted">
                  <?php echo get_the_time('jS', $post->ID); ?> <?php echo get_the_time('M, Y', $post->ID); ?>
                </small>

                <?php
                  $description = get_field('description'); 
                  if ($description):
                    echo '<p class="my-3 card-text">' . esc_html($description) . '</p>';
                  endif;
                ?>

                <a href="<?php echo get_permalink($post->ID); ?>" class="btn btn-primary mt-3">
                  <i class="text-white fa-solid fa-arrow-right-long"></i>
                </a>
              </div>
            </div>
          </div>
          <?php 
            $count++;
            if ($count == $postscount) break;
            endforeach; 
          } ?>
        </div>
      </div>
    </div>
  </div>
</div>

 

<!-- postsrelatedwithfilter  -->



<?php endif; //end for all get_row_layout?> 

