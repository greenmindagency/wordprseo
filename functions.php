<?php

// Include theme lead management integrations.
require_once get_template_directory() . '/inc/leads.php';

// Load WooCommerce integration helpers.
require_once get_template_directory() . '/inc/woocommerce.php';

// remove "Private: " from titles
    add_filter( 'get_the_archive_title', function ($title) {
        if ( is_category() ) {
                $title = single_cat_title( '', false );
            } elseif ( is_tag() ) {
                $title = single_tag_title( '', false );
            } elseif ( is_author() ) {
                $title = '<span class="vcard">' . get_the_author() . '</span>' ;
            } elseif ( is_tax() ) { //for custom post types
                $title = sprintf( __( '%1$s' ), single_term_title( '', false ) );
            } elseif (is_post_type_archive()) {
                $title = post_type_archive_title( '', false );
            }
        return $title;
    });

if ( ! function_exists( 'qt_should_display_breadcrumbs' ) ) {
    /**
     * Determines whether the breadcrumb bar should be shown on the current view.
     *
     * Relies on an ACF checkbox called "hide_breadcrumbs" that can be attached to
     * posts, pages or taxonomy terms. When the checkbox is checked the breadcrumb
     * bar is suppressed for that specific object.
     *
     * @return bool
     */
    function qt_should_display_breadcrumbs() {
        $show_breadcrumbs = true;

        if ( function_exists( 'get_field' ) ) {
            if ( is_singular() ) {
                $post_id = get_queried_object_id();

                if ( $post_id ) {
                    $hide_breadcrumbs = get_field( 'hide_breadcrumbs', $post_id );

                    if ( ! empty( $hide_breadcrumbs ) ) {
                        $show_breadcrumbs = false;
                    }
                }
            } else {
                $term = get_queried_object();

                if ( $term instanceof WP_Term ) {
                    $term_key          = $term->taxonomy . '_' . $term->term_id;
                    $hide_breadcrumbs  = get_field( 'hide_breadcrumbs', $term_key );

                    if ( ! empty( $hide_breadcrumbs ) ) {
                        $show_breadcrumbs = false;
                    }
                }
            }
        }

        /**
         * Filter whether breadcrumbs should be displayed.
         *
         * @param bool $show_breadcrumbs Whether the breadcrumb bar is visible.
         */
        return apply_filters( 'qt_should_display_breadcrumbs', $show_breadcrumbs );
    }
}
      
    

// better pagination
function bittersweet_pagination() {

global $wp_query;

if ( $wp_query->max_num_pages <= 1 ) return; 

$big = 999999999; // need an unlikely integer

$pages = paginate_links( array(
        'base' => str_replace( $big, '%#%', esc_url( get_pagenum_link( $big ) ) ),
        'format' => '?paged=%#%',
        'current' => max( 1, get_query_var('paged') ),
        'total' => $wp_query->max_num_pages,
        'type'  => 'array',
        'prev_text' => '<',
        'next_text' => '>',
    ) );
    if( is_array( $pages ) ) {
        $paged = ( get_query_var('paged') == 0 ) ? 1 : get_query_var('paged');
        echo '<div class="pagination-wrap"><ul class="position-absolute top-50 start-50 translate-middle pagination">';
        foreach ( $pages as $page ) {
                echo "<li class='page-item'>$page</li>";
        }
       echo '</ul></div>';
        }
}
// better pagination


//qt_custom_breadcrumbs()
function custom_breadcrumbs()
{
    // Set variables for later use
    $here_text        = __( '' );
    $home_link        = home_url('/');
    $home_text        = __( 'Home' );
    $link_before      = '<li class="breadcrumb-item">';
    $link_after       = '</li>';
    $link_attr        = '';
    $link             = $link_before . '<a' . $link_attr . ' href="%1$s">%2$s</a>' . $link_after;
    $delimiter        = '';              // Delimiter between crumbs
    $before           = '<li class="breadcrumb-item active">'; // Tag before the current crumb
    $after            = '</li>';                // Tag after the current crumb
    $page_addon       = '';                       // Adds the page number if the query is paged
    $breadcrumb_trail = '';
    $category_links   = '';

    /** 
     * Set our own $wp_the_query variable. Do not use the global variable version due to 
     * reliability
     */
    $wp_the_query   = $GLOBALS['wp_the_query'];
    $queried_object = $wp_the_query->get_queried_object();

    // Handle single post requests which includes single pages, posts and attatchments
    if ( is_singular() ) 
    {
        /** 
         * Set our own $post variable. Do not use the global variable version due to 
         * reliability. We will set $post_object variable to $GLOBALS['wp_the_query']
         */
        $post_object = sanitize_post( $queried_object );

        // Set variables 
        $title          = apply_filters( 'the_title', $post_object->post_title );
        $parent         = $post_object->post_parent;
        $post_type      = $post_object->post_type;
        $post_id        = $post_object->ID;
        $post_link      = $before . $title . $after;
        $parent_string  = '';
        $post_type_link = '';

        if ( 'post' === $post_type ) 
        {
            // Get the post categories
            $categories = get_the_category( $post_id );
            if ( $categories ) {
                // Lets grab the first category
                $category  = $categories[0];

                $category_links = get_category_parents( $category, true, $delimiter );
                $category_links = str_replace( '<a',   $link_before . '<a' . $link_attr, $category_links );
                $category_links = str_replace( '</a>', '</a>' . $link_after,             $category_links );
            }
        }

        if ( !in_array( $post_type, ['post', 'page', 'attachment'] ) )
        {
            $post_type_object = get_post_type_object( $post_type );
            $archive_link     = esc_url( get_post_type_archive_link( $post_type ) );

            $post_type_link   = sprintf( $link, $archive_link, $post_type_object->labels->singular_name );
        }

        // Get post parents if $parent !== 0
        if ( 0 !== $parent ) 
        {
            $parent_links = [];
            while ( $parent ) {
                $post_parent = get_post( $parent );

                $parent_links[] = sprintf( $link, esc_url( get_permalink( $post_parent->ID ) ), get_the_title( $post_parent->ID ) );

                $parent = $post_parent->post_parent;
            }

            $parent_links = array_reverse( $parent_links );

            $parent_string = implode( $delimiter, $parent_links );
        }

        // Lets build the breadcrumb trail
        if ( $parent_string ) {
            $breadcrumb_trail = $parent_string . $delimiter . $post_link;
        } else {
            $breadcrumb_trail = $post_link;
        }

        if ( $post_type_link )
            $breadcrumb_trail = $post_type_link . $delimiter . $breadcrumb_trail;

        if ( $category_links )
            $breadcrumb_trail = $category_links . $breadcrumb_trail;
    }

    // Handle archives which includes category-, tag-, taxonomy-, date-, custom post type archives and author archives
    if( is_archive() )
    {
        if (    is_category()
             || is_tag()
             || is_tax()
        ) {
            // Set the variables for this section
            $term_object        = get_term( $queried_object );
            $taxonomy           = $term_object->taxonomy;
            $term_id            = $term_object->term_id;
            $term_name          = $term_object->name;
            $term_parent        = $term_object->parent;
            $taxonomy_object    = get_taxonomy( $taxonomy );
            $current_term_link  = $before . $term_name . $after;
            $parent_term_string = '';

            if ( 0 !== $term_parent )
            {
                // Get all the current term ancestors
                $parent_term_links = [];
                while ( $term_parent ) {
                    $term = get_term( $term_parent, $taxonomy );

                    $parent_term_links[] = sprintf( $link, esc_url( get_term_link( $term ) ), $term->name );

                    $term_parent = $term->parent;
                }

                $parent_term_links  = array_reverse( $parent_term_links );
                $parent_term_string = implode( $delimiter, $parent_term_links );
            }

            if ( $parent_term_string ) {
                $breadcrumb_trail = $parent_term_string . $delimiter . $current_term_link;
            } else {
                $breadcrumb_trail = $current_term_link;
            }

        } elseif ( is_author() ) {

            $breadcrumb_trail = __( 'Author archive for ') .  $before . $queried_object->data->display_name . $after;

        } elseif ( is_date() ) {
            // Set default variables
            $year     = $wp_the_query->query_vars['year'];
            $monthnum = $wp_the_query->query_vars['monthnum'];
            $day      = $wp_the_query->query_vars['day'];

            // Get the month name if $monthnum has a value
            if ( $monthnum ) {
                $date_time  = DateTime::createFromFormat( '!m', $monthnum );
                $month_name = $date_time->format( 'F' );
            }

            if ( is_year() ) {

                $breadcrumb_trail = $before . $year . $after;

            } elseif( is_month() ) {

                $year_link        = sprintf( $link, esc_url( get_year_link( $year ) ), $year );

                $breadcrumb_trail = $year_link . $delimiter . $before . $month_name . $after;

            } elseif( is_day() ) {

                $year_link        = sprintf( $link, esc_url( get_year_link( $year ) ),             $year       );
                $month_link       = sprintf( $link, esc_url( get_month_link( $year, $monthnum ) ), $month_name );

                $breadcrumb_trail = $year_link . $delimiter . $month_link . $delimiter . $before . $day . $after;
            }

        } elseif ( is_post_type_archive() ) {

            $post_type        = $wp_the_query->query_vars['post_type'];
            $post_type_object = get_post_type_object( $post_type );

            $breadcrumb_trail = $before . $post_type_object->labels->singular_name . $after;

        }
    }   

    // Handle the search page
    if ( is_search() ) {
        $breadcrumb_trail = __( 'Search query for: ' ) . $before . get_search_query() . $after;
    }

    // Handle 404's
    if ( is_404() ) {
        $breadcrumb_trail = $before . __( 'Error 404' ) . $after;
    }

    // Handle paged pages
    if ( is_paged() ) {
        $current_page = get_query_var( 'paged' ) ? get_query_var( 'paged' ) : get_query_var( 'page' );
        $page_addon   = $before . sprintf( __( ' ( Page %s )' ), number_format_i18n( $current_page ) ) . $after;
    }

    $breadcrumb_output_link  = '';
    $breadcrumb_output_link .= '<ul class="breadcrumb">';
    if (    is_home()
         || is_front_page()
    ) {
        // Do not show breadcrumbs on page one of home and frontpage
        if ( is_paged() ) {
            $breadcrumb_output_link .= $here_text . $delimiter;
            $breadcrumb_output_link .= '<li class="breadcrumb-item"><a href="' . $home_link . '">' . $home_text . '</a></li>';
            $breadcrumb_output_link .= $page_addon;
        }
    } else {
        $breadcrumb_output_link .= $here_text . $delimiter;
        $breadcrumb_output_link .= '<li class="breadcrumb-item active"><a href="' . $home_link . '" rel="v:url" property="v:title">' . $home_text . '</a></li>';
        $breadcrumb_output_link .= $delimiter;
        $breadcrumb_output_link .= $breadcrumb_trail;
        $breadcrumb_output_link .= $page_addon;
    }
    $breadcrumb_output_link .= '</ul><!-- .breadcrumbs -->';

    return $breadcrumb_output_link;
}
add_shortcode('custom_breadcrumbs','custom_breadcrumbs');
// qt_custom_breadcrumbs()


// Add theme support for Featured Images
add_theme_support('post-thumbnails', array(
'post',
'page',
'custom-post-type-name',
));
// Add theme support for Featured Images







/*********************************   set custom image sizes  *********************************/

function set_custom_image_sizes() {
    // Set thumbnail size
    update_option('thumbnail_size_w', 512); // Width
    update_option('thumbnail_size_h', 512); // Height

    // Set medium size
    update_option('medium_size_w', 800); // Width
    update_option('medium_size_h', 800); // Height

    // Set large size
    update_option('large_size_w', 1024); // Width
    update_option('large_size_h', 1024); // Height
}
add_action('init', 'set_custom_image_sizes');


/* sizes
Thumbnail size (512px square)
Medium size (maximum 800px width and height)
Large size (maximum 1024px width and height)
Full size (full/original image size you uploaded)
*/

/*********************************   set custom image sizes  *********************************/





/*********************************   allow menu and custome structure *********************************/
function wpb_custom_new_menu() {
  register_nav_menu('primary', __( 'primary' ));
  register_nav_menu('footer-menu', __( 'Footer Menu' ));
}
add_action( 'init', 'wpb_custom_new_menu' );

	
/**
 * WP Bootstrap Navwalker
 *
 * @package WP-Bootstrap-Navwalker
 *
 * @wordpress-plugin
 * Plugin Name: WP Bootstrap Navwalker
 * Plugin URI:  https://github.com/wp-bootstrap/wp-bootstrap-navwalker
 * Description: A custom WordPress nav walker class to implement the Bootstrap 4 navigation style in a custom theme using the WordPress built in menu manager.
 * Author: Edward McIntyre - @twittem, WP Bootstrap, William Patton - @pattonwebz
 * Version: 4.3.0
 * Author URI: https://github.com/wp-bootstrap
 * GitHub Plugin URI: https://github.com/wp-bootstrap/wp-bootstrap-navwalker
 * GitHub Branch: master
 * License: GPL-3.0+
 * License URI: https://www.gnu.org/licenses/gpl-3.0.txt
 */

// Check if Class Exists.
if ( ! class_exists( 'WP_Bootstrap_Navwalker' ) ) :
	/**
	 * WP_Bootstrap_Navwalker class.
	 */
	class WP_Bootstrap_Navwalker extends Walker_Nav_Menu {

		/**
		 * Whether the items_wrap contains schema microdata or not.
		 *
		 * @since 4.2.0
		 * @var boolean
		 */
		private $has_schema = false;

		/**
		 * Ensure the items_wrap argument contains microdata.
		 *
		 * @since 4.2.0
		 */
		public function __construct() {
			if ( ! has_filter( 'wp_nav_menu_args', array( $this, 'add_schema_to_navbar_ul' ) ) ) {
				add_filter( 'wp_nav_menu_args', array( $this, 'add_schema_to_navbar_ul' ) );
			}
		}

		/**
		 * Starts the list before the elements are added.
		 *
		 * @since WP 3.0.0
		 *
		 * @see Walker_Nav_Menu::start_lvl()
		 *
		 * @param string           $output Used to append additional content (passed by reference).
		 * @param int              $depth  Depth of menu item. Used for padding.
		 * @param WP_Nav_Menu_Args $args   An object of wp_nav_menu() arguments.
		 */
		public function start_lvl( &$output, $depth = 0, $args = null ) {
			if ( isset( $args->item_spacing ) && 'discard' === $args->item_spacing ) {
				$t = '';
				$n = '';
			} else {
				$t = "\t";
				$n = "\n";
			}
			$indent = str_repeat( $t, $depth );
			// Default class to add to the file.
			$classes = array( 'dropdown-menu' );
			/**
			 * Filters the CSS class(es) applied to a menu list element.
			 *
			 * @since WP 4.8.0
			 *
			 * @param array    $classes The CSS classes that are applied to the menu `<ul>` element.
			 * @param stdClass $args    An object of `wp_nav_menu()` arguments.
			 * @param int      $depth   Depth of menu item. Used for padding.
			 */
			$class_names = join( ' ', apply_filters( 'nav_menu_submenu_css_class', $classes, $args, $depth ) );
			$class_names = $class_names ? ' class="' . esc_attr( $class_names ) . '"' : '';

			/*
			 * The `.dropdown-menu` container needs to have a labelledby
			 * attribute which points to it's trigger link.
			 *
			 * Form a string for the labelledby attribute from the the latest
			 * link with an id that was added to the $output.
			 */
			$labelledby = '';
			// Find all links with an id in the output.
			preg_match_all( '/(<a.*?id=\"|\')(.*?)\"|\'.*?>/im', $output, $matches );
			// With pointer at end of array check if we got an ID match.
			if ( end( $matches[2] ) ) {
				// Build a string to use as aria-labelledby.
				$labelledby = 'aria-labelledby="' . esc_attr( end( $matches[2] ) ) . '"';
			}
			$output .= "{$n}{$indent}<ul$class_names $labelledby>{$n}";
		}

		/**
		 * Starts the element output.
		 * https://github.com/wp-bootstrap/wp-bootstrap-navwalker
		 * @since WP 3.0.0
		 * @since WP 4.4.0 The {@see 'nav_menu_item_args'} filter was added.
		 *
		 * @see Walker_Nav_Menu::start_el()
		 *
		 * @param string           $output Used to append additional content (passed by reference).
		 * @param WP_Nav_Menu_Item $item   Menu item data object.
		 * @param int              $depth  Depth of menu item. Used for padding.
		 * @param WP_Nav_Menu_Args $args   An object of wp_nav_menu() arguments.
		 * @param int              $id     Current item ID.
		 */
		public function start_el( &$output, $item, $depth = 0, $args = null, $id = 0 ) {
			if ( isset( $args->item_spacing ) && 'discard' === $args->item_spacing ) {
				$t = '';
				$n = '';
			} else {
				$t = "\t";
				$n = "\n";
			}
			$indent = ( $depth ) ? str_repeat( $t, $depth ) : '';

			if ( false !== strpos( $args->items_wrap, 'itemscope' ) && false === $this->has_schema ) {
				$this->has_schema  = true;
				$args->link_before = '<span itemprop="name">' . $args->link_before;
				$args->link_after .= '</span>';
			}

			$classes = empty( $item->classes ) ? array() : (array) $item->classes;

			// Updating the CSS classes of a menu item in the WordPress Customizer preview results in all classes defined
			// in that particular input box to come in as one big class string.
			$split_on_spaces = function ( $class ) {
				return preg_split( '/\s+/', $class );
			};
			$classes         = $this->flatten( array_map( $split_on_spaces, $classes ) );

			/*
			 * Initialize some holder variables to store specially handled item
			 * wrappers and icons.
			 */
			$linkmod_classes = array();
			$icon_classes    = array();

			/*
			 * Get an updated $classes array without linkmod or icon classes.
			 *
			 * NOTE: linkmod and icon class arrays are passed by reference and
			 * are maybe modified before being used later in this function.
			 */
			$classes = self::separate_linkmods_and_icons_from_classes( $classes, $linkmod_classes, $icon_classes, $depth );

			// Join any icon classes plucked from $classes into a string.
			$icon_class_string = join( ' ', $icon_classes );

			/**
			 * Filters the arguments for a single nav menu item.
			 *
			 * @since WP 4.4.0
			 *
			 * @param WP_Nav_Menu_Args $args  An object of wp_nav_menu() arguments.
			 * @param WP_Nav_Menu_Item $item  Menu item data object.
			 * @param int              $depth Depth of menu item. Used for padding.
			 *
			 * @var WP_Nav_Menu_Args
			 */
			$args = apply_filters( 'nav_menu_item_args', $args, $item, $depth );

			// Add .dropdown or .active classes where they are needed.
			if ( $this->has_children ) {
				$classes[] = 'dropdown';
			}
			if ( in_array( 'current-menu-item', $classes, true ) || in_array( 'current-menu-parent', $classes, true ) ) {
				$classes[] = 'active';
			}

			// Add some additional default classes to the item.
			$classes[] = 'menu-item-' . $item->ID;
			$classes[] = 'nav-item';

			// Allow filtering the classes.
			$classes = apply_filters( 'nav_menu_css_class', array_filter( $classes ), $item, $args, $depth );

			// Form a string of classes in format: class="class_names".
			$class_names = join( ' ', $classes );
			$class_names = $class_names ? ' class="' . esc_attr( $class_names ) . '"' : '';

			/**
			 * Filters the ID applied to a menu item's list item element.
			 *
			 * @since WP 3.0.1
			 * @since WP 4.1.0 The `$depth` parameter was added.
			 *
			 * @param string           $menu_id The ID that is applied to the menu item's `<li>` element.
			 * @param WP_Nav_Menu_Item $item    The current menu item.
			 * @param WP_Nav_Menu_Args $args    An object of wp_nav_menu() arguments.
			 * @param int              $depth   Depth of menu item. Used for padding.
			 */
			$id = apply_filters( 'nav_menu_item_id', 'menu-item-' . $item->ID, $item, $args, $depth );
			$id = $id ? ' id="' . esc_attr( $id ) . '"' : '';

			$output .= $indent . '<li ' . $id . $class_names . '>';

			// Initialize array for holding the $atts for the link item.
			$atts           = array();
			$atts['title']  = ! empty( $item->attr_title ) ? $item->attr_title : '';
			$atts['target'] = ! empty( $item->target ) ? $item->target : '';
			if ( '_blank' === $item->target && empty( $item->xfn ) ) {
				$atts['rel'] = 'noopener noreferrer';
			} else {
				$atts['rel'] = ! empty( $item->xfn ) ? $item->xfn : '';
			}

			// If the item has_children add atts to <a>.
			if ( $this->has_children && 0 === $depth ) {
				$atts['href']          = '#';
				$atts['data-bs-toggle']   = 'dropdown';
				$atts['aria-haspopup'] = 'true';
				$atts['aria-expanded'] = 'false';
				$atts['class']         = 'dropdown-toggle nav-link';
				$atts['id']            = 'navbarDropdown' . $item->ID;
			} else {
				if ( true === $this->has_schema ) {
					$atts['itemprop'] = 'url';
				}

				$atts['href'] = ! empty( $item->url ) ? $item->url : '#';
				// For items in dropdowns use .dropdown-item instead of .nav-link.
				if ( $depth > 0 ) {
					$atts['class'] = 'dropdown-item';
				} else {
					$atts['class'] = 'nav-link';
				}
			}

			$atts['aria-current'] = $item->current ? 'page' : '';

			// Update atts of this item based on any custom linkmod classes.
			$atts = self::update_atts_for_linkmod_type( $atts, $linkmod_classes );

			// Allow filtering of the $atts array before using it.
			$atts = apply_filters( 'nav_menu_link_attributes', $atts, $item, $args, $depth );

			// Build a string of html containing all the atts for the item.
			$attributes = '';
			foreach ( $atts as $attr => $value ) {
				if ( ! empty( $value ) ) {
					$value       = ( 'href' === $attr ) ? esc_url( $value ) : esc_attr( $value );
					$attributes .= ' ' . $attr . '="' . $value . '"';
				}
			}

			// Set a typeflag to easily test if this is a linkmod or not.
			$linkmod_type = self::get_linkmod_type( $linkmod_classes );

			// START appending the internal item contents to the output.
			$item_output = isset( $args->before ) ? $args->before : '';

			/*
			 * This is the start of the internal nav item. Depending on what
			 * kind of linkmod we have we may need different wrapper elements.
			 */
			if ( '' !== $linkmod_type ) {
				// Is linkmod, output the required element opener.
				$item_output .= self::linkmod_element_open( $linkmod_type, $attributes );
			} else {
				// With no link mod type set this must be a standard <a> tag.
				$item_output .= '<a' . $attributes . '>';
			}

			/*
			 * Initiate empty icon var, then if we have a string containing any
			 * icon classes form the icon markup with an <i> element. This is
			 * output inside of the item before the $title (the link text).
			 */
			$icon_html = '';
			if ( ! empty( $icon_class_string ) ) {
				// Append an <i> with the icon classes to what is output before links.
				$icon_html = '<i class="' . esc_attr( $icon_class_string ) . '" aria-hidden="true"></i> ';
			}

			/** This filter is documented in wp-includes/post-template.php */
			$title = apply_filters( 'the_title', $item->title, $item->ID );

			/**
			 * Filters a menu item's title.
			 *
			 * @since WP 4.4.0
			 *
			 * @param string           $title The menu item's title.
			 * @param WP_Nav_Menu_Item $item  The current menu item.
			 * @param WP_Nav_Menu_Args $args  An object of wp_nav_menu() arguments.
			 * @param int              $depth Depth of menu item. Used for padding.
			 */
			$title = apply_filters( 'nav_menu_item_title', $title, $item, $args, $depth );

			// If the .sr-only class was set apply to the nav items text only.
			if ( in_array( 'sr-only', $linkmod_classes, true ) ) {
				$title         = self::wrap_for_screen_reader( $title );
				$keys_to_unset = array_keys( $linkmod_classes, 'sr-only', true );
				foreach ( $keys_to_unset as $k ) {
					unset( $linkmod_classes[ $k ] );
				}
			}

			// Put the item contents into $output.
			$item_output .= isset( $args->link_before ) ? $args->link_before . $icon_html . $title . $args->link_after : '';

			/*
			 * This is the end of the internal nav item. We need to close the
			 * correct element depending on the type of link or link mod.
			 */
			if ( '' !== $linkmod_type ) {
				// Is linkmod, output the required closing element.
				$item_output .= self::linkmod_element_close( $linkmod_type );
			} else {
				// With no link mod type set this must be a standard <a> tag.
				$item_output .= '</a>';
			}

			$item_output .= isset( $args->after ) ? $args->after : '';

			// END appending the internal item contents to the output.
			$output .= apply_filters( 'walker_nav_menu_start_el', $item_output, $item, $depth, $args );
		}

		/**
		 * Menu fallback.
		 *
		 * If this function is assigned to the wp_nav_menu's fallback_cb variable
		 * and a menu has not been assigned to the theme location in the WordPress
		 * menu manager the function will display nothing to a non-logged in user,
		 * and will add a link to the WordPress menu manager if logged in as an admin.
		 *
		 * @param array $args passed from the wp_nav_menu function.
		 * @return string|void String when echo is false.
		 */
		public static function fallback( $args ) {
			if ( ! current_user_can( 'edit_theme_options' ) ) {
				return;
			}

			// Initialize var to store fallback html.
			$fallback_output = '';

			// Menu container opening tag.
			$show_container = false;
			if ( $args['container'] ) {
				/**
				 * Filters the list of HTML tags that are valid for use as menu containers.
				 *
				 * @since WP 3.0.0
				 *
				 * @param array $tags The acceptable HTML tags for use as menu containers.
				 *                    Default is array containing 'div' and 'nav'.
				 */
				$allowed_tags = apply_filters( 'wp_nav_menu_container_allowedtags', array( 'div', 'nav' ) );
				if ( is_string( $args['container'] ) && in_array( $args['container'], $allowed_tags, true ) ) {
					$show_container   = true;
					$class            = $args['container_class'] ? ' class="menu-fallback-container ' . esc_attr( $args['container_class'] ) . '"' : ' class="menu-fallback-container"';
					$id               = $args['container_id'] ? ' id="' . esc_attr( $args['container_id'] ) . '"' : '';
					$fallback_output .= '<' . $args['container'] . $id . $class . '>';
				}
			}

			// The fallback menu.
			$class            = $args['menu_class'] ? ' class="menu-fallback-menu ' . esc_attr( $args['menu_class'] ) . '"' : ' class="menu-fallback-menu"';
			$id               = $args['menu_id'] ? ' id="' . esc_attr( $args['menu_id'] ) . '"' : '';
			$fallback_output .= '<ul' . $id . $class . '>';
			$fallback_output .= '<li class="nav-item"><a href="' . esc_url( admin_url( 'nav-menus.php' ) ) . '" class="nav-link" title="' . esc_attr__( 'Add a menu', 'wp-bootstrap-navwalker' ) . '">' . esc_html__( 'Add a menu', 'wp-bootstrap-navwalker' ) . '</a></li>';
			$fallback_output .= '</ul>';

			// Menu container closing tag.
			if ( $show_container ) {
				$fallback_output .= '</' . $args['container'] . '>';
			}

			// if $args has 'echo' key and it's true echo, otherwise return.
			if ( array_key_exists( 'echo', $args ) && $args['echo'] ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $fallback_output;
			} else {
				return $fallback_output;
			}
		}

		/**
		 * Filter to ensure the items_Wrap argument contains microdata.
		 *
		 * @since 4.2.0
		 *
		 * @param  array $args The nav instance arguments.
		 * @return array $args The altered nav instance arguments.
		 */
		public function add_schema_to_navbar_ul( $args ) {
			$wrap = $args['items_wrap'];
			if ( strpos( $wrap, 'SiteNavigationElement' ) === false ) {
				$args['items_wrap'] = preg_replace( '/(>).*>?\%3\$s/', ' itemscope itemtype="https://www.schema.org/SiteNavigationElement"$0', $wrap );
			}

			return $args;
		}

		/**
		 * Find any custom linkmod or icon classes and store in their holder
		 * arrays then remove them from the main classes array.
		 *
		 * Supported linkmods: .disabled, .dropdown-header, .dropdown-divider, .sr-only
		 * Supported iconsets: Font Awesome 4/5, Glypicons
		 *
		 * NOTE: This accepts the linkmod and icon arrays by reference.
		 *
		 * @since 4.0.0
		 *
		 * @param array   $classes         an array of classes currently assigned to the item.
		 * @param array   $linkmod_classes an array to hold linkmod classes.
		 * @param array   $icon_classes    an array to hold icon classes.
		 * @param integer $depth           an integer holding current depth level.
		 *
		 * @return array  $classes         a maybe modified array of classnames.
		 */
		private function separate_linkmods_and_icons_from_classes( $classes, &$linkmod_classes, &$icon_classes, $depth ) {
			// Loop through $classes array to find linkmod or icon classes.
			foreach ( $classes as $key => $class ) {
				/*
				 * If any special classes are found, store the class in it's
				 * holder array and and unset the item from $classes.
				 */
				if ( preg_match( '/^disabled|^sr-only/i', $class ) ) {
					// Test for .disabled or .sr-only classes.
					$linkmod_classes[] = $class;
					unset( $classes[ $key ] );
				} elseif ( preg_match( '/^dropdown-header|^dropdown-divider|^dropdown-item-text/i', $class ) && $depth > 0 ) {
					/*
					 * Test for .dropdown-header or .dropdown-divider and a
					 * depth greater than 0 - IE inside a dropdown.
					 */
					$linkmod_classes[] = $class;
					unset( $classes[ $key ] );
				} elseif ( preg_match( '/^fa-(\S*)?|^fa(s|r|l|b)?(\s?)?$/i', $class ) ) {
					// Font Awesome.
					$icon_classes[] = $class;
					unset( $classes[ $key ] );
				} elseif ( preg_match( '/^glyphicon-(\S*)?|^glyphicon(\s?)$/i', $class ) ) {
					// Glyphicons.
					$icon_classes[] = $class;
					unset( $classes[ $key ] );
				}
			}

			return $classes;
		}

		/**
		 * Return a string containing a linkmod type and update $atts array
		 * accordingly depending on the decided.
		 *
		 * @since 4.0.0
		 *
		 * @param array $linkmod_classes array of any link modifier classes.
		 *
		 * @return string                empty for default, a linkmod type string otherwise.
		 */
		private function get_linkmod_type( $linkmod_classes = array() ) {
			$linkmod_type = '';
			// Loop through array of linkmod classes to handle their $atts.
			if ( ! empty( $linkmod_classes ) ) {
				foreach ( $linkmod_classes as $link_class ) {
					if ( ! empty( $link_class ) ) {

						// Check for special class types and set a flag for them.
						if ( 'dropdown-header' === $link_class ) {
							$linkmod_type = 'dropdown-header';
						} elseif ( 'dropdown-divider' === $link_class ) {
							$linkmod_type = 'dropdown-divider';
						} elseif ( 'dropdown-item-text' === $link_class ) {
							$linkmod_type = 'dropdown-item-text';
						}
					}
				}
			}
			return $linkmod_type;
		}

		/**
		 * Update the attributes of a nav item depending on the limkmod classes.
		 *
		 * @since 4.0.0
		 *
		 * @param array $atts            array of atts for the current link in nav item.
		 * @param array $linkmod_classes an array of classes that modify link or nav item behaviors or displays.
		 *
		 * @return array                 maybe updated array of attributes for item.
		 */
		private function update_atts_for_linkmod_type( $atts = array(), $linkmod_classes = array() ) {
			if ( ! empty( $linkmod_classes ) ) {
				foreach ( $linkmod_classes as $link_class ) {
					if ( ! empty( $link_class ) ) {
						/*
						 * Update $atts with a space and the extra classname
						 * so long as it's not a sr-only class.
						 */
						if ( 'sr-only' !== $link_class ) {
							$atts['class'] .= ' ' . esc_attr( $link_class );
						}
						// Check for special class types we need additional handling for.
						if ( 'disabled' === $link_class ) {
							// Convert link to '#' and unset open targets.
							$atts['href'] = '#';
							unset( $atts['target'] );
						} elseif ( 'dropdown-header' === $link_class || 'dropdown-divider' === $link_class || 'dropdown-item-text' === $link_class ) {
							// Store a type flag and unset href and target.
							unset( $atts['href'] );
							unset( $atts['target'] );
						}
					}
				}
			}
			return $atts;
		}

		/**
		 * Wraps the passed text in a screen reader only class.
		 *
		 * @since 4.0.0
		 *
		 * @param string $text the string of text to be wrapped in a screen reader class.
		 * @return string      the string wrapped in a span with the class.
		 */
		private function wrap_for_screen_reader( $text = '' ) {
			if ( $text ) {
				$text = '<span class="sr-only">' . $text . '</span>';
			}
			return $text;
		}

		/**
		 * Returns the correct opening element and attributes for a linkmod.
		 *
		 * @since 4.0.0
		 *
		 * @param string $linkmod_type a sting containing a linkmod type flag.
		 * @param string $attributes   a string of attributes to add to the element.
		 *
		 * @return string              a string with the openign tag for the element with attribibutes added.
		 */
		private function linkmod_element_open( $linkmod_type, $attributes = '' ) {
			$output = '';
			if ( 'dropdown-item-text' === $linkmod_type ) {
				$output .= '<span class="dropdown-item-text"' . $attributes . '>';
			} elseif ( 'dropdown-header' === $linkmod_type ) {
				/*
				 * For a header use a span with the .h6 class instead of a real
				 * header tag so that it doesn't confuse screen readers.
				 */
				$output .= '<span class="dropdown-header h6"' . $attributes . '>';
			} elseif ( 'dropdown-divider' === $linkmod_type ) {
				// This is a divider.
				$output .= '<div class="dropdown-divider"' . $attributes . '>';
			}
			return $output;
		}

		/**
		 * Return the correct closing tag for the linkmod element.
		 *
		 * @since 4.0.0
		 *
		 * @param string $linkmod_type a string containing a special linkmod type.
		 *
		 * @return string              a string with the closing tag for this linkmod type.
		 */
		private function linkmod_element_close( $linkmod_type ) {
			$output = '';
			if ( 'dropdown-header' === $linkmod_type || 'dropdown-item-text' === $linkmod_type ) {
				/*
				 * For a header use a span with the .h6 class instead of a real
				 * header tag so that it doesn't confuse screen readers.
				 */
				$output .= '</span>';
			} elseif ( 'dropdown-divider' === $linkmod_type ) {
				// This is a divider.
				$output .= '</div>';
			}
			return $output;
		}

		/**
		 * Flattens a multidimensional array to a simple array.
		 *
		 * @param array $array a multidimensional array.
		 *
		 * @return array a simple array
		 */
		public function flatten( $array ) {
			$result = array();
			foreach ( $array as $element ) {
				if ( is_array( $element ) ) {
					array_push( $result, ...$this->flatten( $element ) );
				} else {
					$result[] = $element;
				}
			}
			return $result;
		}

	}

endif;
	
/*********************************   allow menu and custome structure *********************************/			





/////////////////Category Template Based on Category Level
function is_category_level($depth){
$current_category = get_query_var('cat');
$my_category  = get_categories('include='.$current_category);
$cat_depth=0;

 if ($my_category[0]->category_parent == 0){
 	$cat_depth = 0;
 	} else {

 while( $my_category[0]->category_parent != 0 ) {
  $my_category = get_categories('include='.$my_category[0]->category_parent);
  $cat_depth++;
  }
  }
  if ($cat_depth == intval($depth)) { return true; }
  return null;
}


// ** hide category column in the wp-admin
function removecategorydescrption($columns)
{


 return $columns;
}
add_filter('manage_edit-category_columns','removecategorydescrption');



//get the category Parent ID function
function getParentCatID(){
$thiscat =  get_query_var('cat'); // The id of the current category
$catobject = get_category($thiscat,false); // Get the Category object by the id of current category
$parentcat = $catobject->category_parent; // the id of the parent category 
}


// Remove category base
add_filter('category_link', 'no_category_parents',1000,2);
function no_category_parents($catlink, $category_id) {
    $category = &get_category( $category_id );
    if ( is_wp_error( $category ) )
        return $category;
    $category_nicename = $category->slug;

    $catlink = trailingslashit(get_option( 'home' )) . user_trailingslashit( $category_nicename, 'category' );
    return $catlink;
}

// Add our custom category rewrite rules
add_filter('category_rewrite_rules', 'no_category_parents_rewrite_rules');
function no_category_parents_rewrite_rules($category_rewrite) {
    //print_r($category_rewrite); // For Debugging

    $category_rewrite=array();
    $categories=get_categories(array('hide_empty'=>false));
    foreach($categories as $category) {
        $category_nicename = $category->slug;
        $category_rewrite['('.$category_nicename.')/(?:feed/)?(feed|rdf|rss|rss2|atom)/?$'] = 'index.php?category_name=$matches[1]&feed=$matches[2]';
        $category_rewrite['('.$category_nicename.')/page/?([0-9]{1,})/?$'] = 'index.php?category_name=$matches[1]&paged=$matches[2]';
        $category_rewrite['('.$category_nicename.')/?$'] = 'index.php?category_name=$matches[1]';
    }
    // Redirect support from Old Category Base
    global $wp_rewrite;
    $old_base = $wp_rewrite->get_category_permastruct();
    $old_base = str_replace( '%category%', '(.+)', $old_base );
    $old_base = trim($old_base, '/');
    $category_rewrite[$old_base.'$'] = 'index.php?category_redirect=$matches[1]';

    //print_r($category_rewrite); // For Debugging
    return $category_rewrite;
}

// Add 'category_redirect' query variable
add_filter('query_vars', 'no_category_parents_query_vars');
function no_category_parents_query_vars($public_query_vars) {
    $public_query_vars[] = 'category_redirect';
    return $public_query_vars;
}
// Redirect if 'category_redirect' is set
add_filter('request', 'no_category_parents_request');
function no_category_parents_request($query_vars) {
    //print_r($query_vars); // For Debugging
    if(isset($query_vars['category_redirect'])) {
        $catlink = trailingslashit(get_option( 'home' )) . user_trailingslashit( $query_vars['category_redirect'], 'category' );
        status_header(301);
        header("Location: $catlink");
        exit();
    }
    return $query_vars;
}


//this code is giving a new permlink fresh every refresh

flush_rewrite_rules(true);

/* this code will only flush when category updated

add_action('create_category', 'my_theme_do_something', 10, 2);

function my_theme_do_something($term_id, $taxonomy_term_id){
	flush_rewrite_rules(true);
}

*/


//this code is giving a new permlink fresh every refresh

/////////////////Category Template Based on Category Level





// add editor the privilege to edit theme
// get the the role object
$role_object = get_role( 'editor' );
// add $cap capability to this role object
$role_object->add_cap( 'edit_theme_options' );




/*********************************   replace css on the admin panel  *********************************/
add_action('admin_head', 'my_custom_style');

function my_custom_style() {
  echo '<style>
        .term-description-wrap, .update-nag {display:none; visibility:hidden}
        .acf-fc-layout-handle  { background-color:#ccc}
  </style>';
}
/*********************************   replace css on the admin panel  *********************************/


/**
 * Include the comments list walker class
 */
require get_template_directory() . '/class-msbdtcp-walker-comment.php';

add_filter( 'woocommerce_product_description_heading', '__return_empty_string' );
add_filter( 'woocommerce_product_description_tab_title', 'wordprseo_description_tab_title' );

function wordprseo_description_tab_title( $title ) {
  return __( 'Details', 'woocommerce' );
}








/************   adding class to posts_link_attributes to make infinite loop work   ******************/

add_filter('next_posts_link_attributes', 'posts_link_attributes');
add_filter('previous_posts_link_attributes', 'posts_link_attributes');

function posts_link_attributes() {
  return 'class="pagination__next"';
}

/************   adding class to posts_link_attributes to make infinite loop work   ******************/




/**remove_core_updates


function remove_core_updates(){
    global $wp_version; 
    return (object) array(
        'last_checked'=> time(),
        'version_checked'=> $wp_version,
    );
}
add_filter('pre_site_transient_update_core', 'remove_core_updates');
add_filter('pre_site_transient_update_plugins', 'remove_core_updates');
add_filter('pre_site_transient_update_themes', 'remove_core_updates');

function hide_update_notice() {
    remove_action( 'admin_notices', 'update_nag', 3 );
}
add_action( 'admin_menu', 'hide_update_notice' );

remove_core_updates**/


/**show empty categories or tags in loops by default**/

function include_empty_taxonomies_in_queries($args, $taxonomy) {
    // Modify only if 'hide_empty' is set to true
    if (isset($args['hide_empty']) && $args['hide_empty'] === true) {
        $args['hide_empty'] = false;
    }
    return $args;
}
add_filter('get_terms_args', 'include_empty_taxonomies_in_queries', 10, 2);

/**show empty categories or tags in loops by default**/


/**Redirects attachment pages to their parent post or homepage, ensuring media files are not accessible as standalone pages.**/

function redirect_attachment_page() {
    if (is_attachment()) {
        global $post;
        if ($post && $post->post_parent) {
            wp_redirect(get_permalink($post->post_parent), 301);
            exit;
        } else {
            wp_redirect(home_url(), 301);
            exit;
        }
    }
}
add_action('template_redirect', 'redirect_attachment_page');
/**Redirects attachment pages to their parent post or homepage, ensuring media files are not accessible as standalone pages.**/

/**
 * Automatically adds an alt tag to images upon upload if not already provided.
 * The alt tag is set to the image title, or a default text if the title is empty.
 */
 
function add_default_alt_text($post_ID) {
    $image = get_post($post_ID);
    $alt_text = get_post_meta($post_ID, '_wp_attachment_image_alt', true);

    // If alt text is empty, set it to the image title
    if (empty($alt_text)) {
        $alt_text = $image->post_title;

        // You can also set a custom alt text if the title is empty
        if (empty($alt_text)) {
            $alt_text = 'Default Alt Text'; // Replace this with your preferred default alt text
        }

        // Update the alt text
        update_post_meta($post_ID, '_wp_attachment_image_alt', $alt_text);
    }
}
add_action('add_attachment', 'add_default_alt_text');

/**
 * Automatically adds an alt tag to images upon upload if not already provided.
 * The alt tag is set to the image title, or a default text if the title is empty.
 */


//block new editor for posts and pages/

add_filter('use_block_editor_for_post', '__return_false', 10);

//block new editor for posts and pages/

//control the width of the #edittag div using CSS/

function custom_admin_styles() {
echo '<style>
#edittag {
width: 100% !important;
max-width: none !important;
}
</style>';
}
add_action('admin_head', 'custom_admin_styles');

//control the width of the #edittag div using CSS/

//modify the script output and remove the type attribute

function remove_script_type_attribute( $tag, $handle, $src ) {
    // Remove 'type="text/javascript"' from the script tag
    return str_replace( ' type="text/javascript"', '', $tag );
}
add_filter( 'script_loader_tag', 'remove_script_type_attribute', 10, 3 );

//modify the script output and remove the type attribute



// Display images for each flexible content layout option in the ACF popup.
function gm_acf_flexible_layout_images_script() {
    $base = get_template_directory_uri() . '/acf-images/';
    ?>
    <script type="text/javascript">
    (function($){
        function addLayoutImages(){
            $('.acf-fc-popup:visible a[data-layout]').each(function(){
                var $link = $(this);
                if($link.find('img.acf-layout-thumb').length){
                    return;
                }
                var layout = $link.data('layout');
                var img = $('<img>', {
                    class: 'acf-layout-thumb',
                    src: '<?php echo $base; ?>' + layout + '.jpg',
                    css: { width: '100px', 'margin-right': '5px', 'vertical-align': 'middle' }
                }).on('error', function(){
                    $(this).attr('src', '<?php echo $base; ?>' + layout + '.png');
                });
                $link.prepend(img);
            });
        }
        $(document).on('click', '.acf-flexible-content [data-name="add-layout"]', function(){
            setTimeout(addLayoutImages, 1);
        });
    })(jQuery);
    </script>
    <?php
}
add_action('acf/input/admin_footer', 'gm_acf_flexible_layout_images_script');

if ( ! class_exists( 'Footer_Menu_Walker' ) ) {
    class Footer_Menu_Walker extends Walker_Nav_Menu {
        public function start_lvl( &$output, $depth = 0, $args = null ) {
            if ( 0 === $depth ) {
                $output .= "<ul class=\"list-unstyled text-small mb-5\">\n";
            } else {
                $output .= "<ul>\n";
            }
        }

        public function end_lvl( &$output, $depth = 0, $args = null ) {
            $output .= "</ul>\n";
        }

        public function start_el( &$output, $item, $depth = 0, $args = null, $id = 0 ) {
            if ( 0 === $depth ) {
                $output .= '<p class="h5 fw-bold pb-2 text-white">' . esc_html( $item->title ) . "</p>\n";
            } else {
                $output .= '<li><i class="text-light fa-solid fa-caret-right me-2"></i> <a class="link-light" href="' . esc_url( $item->url ) . '">' . esc_html( $item->title ) . '</a>';
            }
        }

        public function end_el( &$output, $item, $depth = 0, $args = null ) {
            if ( $depth > 0 ) {
                $output .= "</li>\n";
            }
        }
    }
}
?>
