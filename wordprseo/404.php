<?php get_header(); // This fxn gets the header.php file and renders it ?>



 
<div class="bg-primary">
<div class="container py-spacer">
    <div class="col-md-8 py-spacer text-white">
    <h1 class="fw-bold card-title display-4 my-3">404 Page Not Found</h1>
    <h2 class="fw-bold h5">It seems the page you're trying to reach doesn't exist.</h2>
</div>
</div>
</div>
	


    




 <div class="container">
      <div class="row">

<h2 class="display-5 my-5 ">Latest Posts</h2>
           
          </div>
		  
	
       <?php query_posts('showposts=12'); ?>   
       <?php while ( have_posts() ) : the_post(); ?>
    <?php include get_theme_file_path( '/loop.php' ); //load loop.php ?>
  
    
    <?php endwhile; ?>
   
   <?php bittersweet_pagination(); // get the pagination ?>  
 
</div>
        
      
	  
             
    
        
        
        




<?php get_footer(); // This fxn gets the footer.php file and renders it ?>