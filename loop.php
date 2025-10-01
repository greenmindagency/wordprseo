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
        
        <?php
        $categories = get_the_category();
        $primary_category = !empty($categories) ? $categories[0] : null;
        $category_link = $primary_category ? get_category_link($primary_category->term_id) : '';

        $taxonomy_icon_markup = '';
        if ($primary_category) {
            $term_icon_class = get_field('icon', $primary_category);
            $term_icon_image = get_field('icon_image', $primary_category);

            if (!empty($term_icon_image)) {
                $icon_image_url = '';
                if (!empty($term_icon_image['sizes']['thumbnail'])) {
                    $icon_image_url = $term_icon_image['sizes']['thumbnail'];
                } elseif (!empty($term_icon_image['url'])) {
                    $icon_image_url = $term_icon_image['url'];
                }

                if ($icon_image_url) {
                    $icon_image_alt = !empty($term_icon_image['alt']) ? $term_icon_image['alt'] : $primary_category->name;
                    $taxonomy_icon_markup = '<span class="text-dark me-1 taxonomy-icon-wrapper taxonomy-icon-wrapper--small"><img class="taxonomy-icon-image" src="' . esc_url($icon_image_url) . '" alt="' . esc_attr($icon_image_alt) . '"></span>';
                }
            }

            if (!$taxonomy_icon_markup && !empty($term_icon_class)) {
                $taxonomy_icon_markup = '<i class="text-dark me-1 fa ' . esc_attr($term_icon_class) . '"></i>';
            }
        }

        $category_names = !empty($categories) ? wp_list_pluck($categories, 'cat_name') : [];
        $category_names_output = !empty($category_names) ? esc_html(implode(', ', $category_names)) : '';
        ?>
        <?php if ($category_names_output) : ?>
            <small class="text-muted">
                <?php if ($category_link) : ?><a href="<?php echo esc_url($category_link); ?>"><?php endif; ?>
                    <?php echo $taxonomy_icon_markup; ?><?php echo $category_names_output; ?>
                <?php if ($category_link) : ?></a><?php endif; ?>
            </small>
        <?php endif; ?>
        
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
      