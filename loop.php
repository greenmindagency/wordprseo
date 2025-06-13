<div class="row"  data-masonry='{"percentPosition": true }'>
         
  
 
  
           <?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
          
             
  <div class="mb-4 col-md-6">
    <div class="shadow card">
      <?php if ( has_post_thumbnail() ) { ?>
   
<?php $image_data = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), "large" ); ?><?php $image_width = $image_data[1]; ?><?php $image_height = $image_data[2];  // get the featuered images width and height ?>  
      
      <a href="<?php echo get_permalink($post->ID) // get the loop-item the link ?>"><img src="data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=" data-src="<?php the_post_thumbnail_url('large'); ?>" class="img-fluid card-img-top lazyload" data-expand="-100" alt="<?php the_title(); ?>" width="<?php echo $image_width; ?>" height="<?php echo $image_height; ?>">
      </a>
      <?php } else { ?>

<?php } ?>
            
      
      
      <div class="card-body ">
        
        <small class="text-muted"><a href="<?php foreach((get_the_category()) as $category) {  //load category?> <?php echo get_category_link($category->cat_ID) //echo the link?><?php } ?>">
         
            
            <i class="text-dark me-1 fa <?php $terms = get_the_terms( get_the_ID(), 'category'); // get parent category ACF items
if( !empty($terms) ) { 
	$term = array_pop($terms);
	$custom_field = get_field('icon', $term ); // define the fields
?>
	<?php echo $custom_field; //echo the field?>
<?php } ?>"></i><?php foreach((get_the_category()) as $category) {  //load category?> <?php echo $category->cat_name //echo the name?><?php } ?></a></small>
        
        <a href="<?php echo get_permalink($post->ID) // get the loop-item the link ?>"><h3 class=" text-dark card-title"><?php the_title(); ?></h3></a>
        
        <small class="font-italic text-muted"><?php echo get_the_time('jS', $post->ID); // get the loop-item the time ?> <?php echo get_the_time('M, Y', $post->ID); // get the loop-item the time  ?></small>
        <p class="card-text"><?php $description = get_sub_field('description'); if ($description) echo esc_html($description); ?></p>
        <div class="d-flex justify-content-between align-items-center">
              
                  <a class="btn btn-sm btn-primary" href="<?php echo get_permalink($post->ID) // get the loop-item the link ?>">Read More</a>

               
                  
                  <?php
  
  $posttags = get_the_tags();
$count=0;
if ($posttags) {
  foreach($posttags as $tag) {
    $count++;
    if (1 == $count) {
      echo '<a class="btn btn-sm  btn-outline-primary" href='.get_tag_link($tag->term_id) .'>'.$tag->name . '</a>';
    }
  }
}
 // get first related tag ?>
                  
                  
                  
           
          
          
         

   
              </div>
      </div>
    </div>
  </div>

  <?php endwhile; endif; ?>  
  
  
      </div>     
           
           <hr class="my-5">
           
           <div class="mb-5 pb-4">
           

             
             
<nav class="position-relative" aria-label="Page navigation example">


	<?php bittersweet_pagination(); ?> 							
</nav>
             
             <?php wp_reset_query(); ?>
           
           </div> 
      