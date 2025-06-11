          
          
 <div class="mb-5 ">
         <h2 class="border-bottom display-5 mb-3 pb-3 ">Featured</h2>
                            
                    
         
    <ol class="list-unstyled mb-0">
      
      
<?php //get featured posts from check box that named featured

    
    
      
$posts = get_posts(array( 'meta_query' => array( array( 'key' => 'featured', 'value' => '"Featured"', 'compare' => 'LIKE' ) )));

if( $posts ) { ?>
           
     <?php
  query_posts(array( 'meta_query' => array( array( 'key' => 'featured', 'value' => '"Featured"', 'compare' => 'LIKE' ) )));
  if ( have_posts() ) : while ( have_posts() ) : the_post();
?>
       

       <li><a href="<?php echo get_permalink($post->ID) // get the loop-item the link ?>"><?php the_title(); ?></a> <i class=" px-2  fa fa-arrow-right "></i>
          
           <p class="text-muted"><?php echo get_the_time('jS', $post->ID); // get the loop-item the time ?> <?php echo get_the_time('M, Y', $post->ID); // get the loop-item the time  ?></p>
          </li>
      
      
      
       <?php endwhile; endif; ?>  
        
<?php }

?>
      
   </ol>
      
    </div>
       

          
    <?php if ( current_user_can( 'activate_plugins' ) ) {// check for a capability that only admins have - admin only ?>
    
    
 <div class="mb-5">
         <h3 class="h4 border-bottom pb-3 mb-3">Internal</h3>
                         
                            
      
                         
    <ol class="list-unstyled mb-0">
      
      
      <?php query_posts('cat=10&showposts=-1'); ?>
        <?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
      
      
          <li><a href="<?php echo get_permalink($post->ID) // get the loop-item the link ?>"><?php the_title(); ?></a> <i class=" px-2  fa fa-arrow-right "></i>
</li>
        
       <?php endwhile; endif; ?>
        <?php wp_reset_query(); ?>
      
   </ol>
         
     
  </div>

 <?php  } else {  // can't get nothing at all?>
    
    
 
        <?php } // end of user capability check - admin only ?>   
    
            
     <div class="mb-5">
         <h3 class="h4 border-bottom pb-3 mb-3">Topics</h3>
                         
                            
      
                    <?php 
$tags = get_tags();

foreach ( $tags as $tag ) {
    $tag_link = get_tag_link( $tag->term_id );

    $html .= " <a href='{$tag_link}' title='{$tag->name} Tag' class='mb-1 btn btn-primary {$tag->slug}'>";
    $html .= "{$tag->name}</a>";
}

echo $html;
?>
         
     
  </div>
      