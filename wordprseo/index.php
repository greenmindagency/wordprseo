<?php get_header(); // This fxn gets the header.php file and renders it ?>

	  
	  
           
<!-- flixable content -->

<?php $term = get_queried_object(); // get the current taxonomy term ?>
<?php if( have_rows('body', 2) ): ?>

<article class="blog-post">	

<?php while( have_rows('body', 2) ): the_row(); ?>
<?php include get_theme_file_path( '/flixable.php' ); //load sidebar.php ?>
<?php endwhile; ?>

</article>   

<?php endif; ?>

<!-- flixable content -->    
	  
	  
	  
         

    

<?php get_footer(); // This fxn gets the footer.php file and renders it ?>