<?php get_header(); // This fxn gets the header.php file and renders it ?>



 

      
<div class="bg-primary">
<div class="container py-spacer">
    <div class="col-md-8 py-spacer text-white">
    <h1 class="fw-bold card-title display-4 my-3">Search Results</h1>
    <h2 class="fw-bold h5">Get all relevant results for your query</h2>
</div>
</div>
</div>



	

<div class="container">


  <div class="row p-0 my-5">
      
        <div class="col-md-12">
      
          
      
    <?php if ( have_posts() ) : ?> 
   
   <h2 class="border-bottom border-3 pb-3 mb-3">Search Results</h2>
          
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
         
            
            <?php foreach((get_the_category()) as $category) {  //load category?> <?php echo $category->cat_name //echo the name?><?php } ?></a></small>
        
        <a href="<?php echo get_permalink($post->ID) // get the loop-item the link ?>"><h3 class=" text-dark card-title"><?php the_title(); ?></h3></a>
        
        <small class="font-italic text-muted"><?php echo get_the_time('jS', $post->ID); // get the loop-item the time ?> <?php echo get_the_time('M, Y', $post->ID); // get the loop-item the time  ?></small>
        	<?php 
$description = get_field('description'); 
if ($description): ?>
    <p class="lead card-text"><?php echo $description; ?></p>
<?php endif; ?>
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
  
     
   <?php else : // if can't find any search resultes ?>

 <h2 class="border-bottom border-3 pb-3 mb-3"><i class="fa fa-times me-2"></i>Nothing was found for your search results</h2>
          
   <h3 class="pb-3 mb-3">Check out our latest posts instead</h3>
          
            
       <?php query_posts('showposts=12'); ?>   
       <?php while ( have_posts() ) : the_post(); ?>
    <?php include get_theme_file_path( '/loop.php' ); //load loop.php ?>
  
    
    <?php endwhile; ?>
   
   <?php bittersweet_pagination(); // get the pagination ?> 

          
 	<!-- REALLY stop The Loop. -->
 <?php endif; ?>
          
    </div>        
          

</div>
</div>


<?php get_footer(); // This fxn gets the footer.php file and renders it ?>