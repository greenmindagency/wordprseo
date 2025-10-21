<?php
/**
 * Page hero template.
 *
 * @package WordPrSEO
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $post;

if ( ! ( $post instanceof WP_Post ) ) {
    return;
}

$thumbnail_id = get_post_thumbnail_id( $post );
$image_data   = $thumbnail_id ? wp_get_attachment_image_src( $thumbnail_id, 'medium' ) : array( '', 0, 0 );
$image_url    = $thumbnail_id ? get_the_post_thumbnail_url( $post, 'medium' ) : '';
$image_width  = isset( $image_data[1] ) ? (int) $image_data[1] : 0;
$image_height = isset( $image_data[2] ) ? (int) $image_data[2] : 0;

$clean_title = true;
$yoast_title = '';

if ( function_exists( 'YoastSEO' ) ) {
    $yoast = YoastSEO();

    if ( method_exists( $yoast, 'meta' ) && method_exists( $yoast->meta, 'for_current_page' ) ) {
        $yoast_meta = $yoast->meta->for_current_page();

        if ( isset( $yoast_meta->title ) ) {
            $yoast_title = $yoast_meta->title;
        }
    }
}

if ( '' === $yoast_title ) {
    $yoast_title = is_singular() ? get_the_title( $post ) : wp_get_document_title();
}

if ( $clean_title ) {
    $site_name   = get_bloginfo( 'name' );
    $pattern     = '/(\s*[\|\-\·]\s*' . preg_quote( $site_name, '/' ) . ')$/';
    $yoast_title = preg_replace( $pattern, '', $yoast_title );
}

$description = function_exists( 'get_field' ) ? get_field( 'description', $post->ID ) : '';

?>
<!-- jarallax image -->
<div data-jarallax data-speed="0.2" class="bg-secondary jarallax">
    <?php if ( $image_url ) : ?>
        <img
            loading="lazy"
            src="<?php echo esc_url( $image_url ); ?>"
            class="jarallax-img"
            alt="<?php echo esc_attr( get_the_title( $post ) ); ?>"
            width="<?php echo esc_attr( $image_width ); ?>"
            height="<?php echo esc_attr( $image_height ); ?>"
        >
    <?php endif; ?>

    <div class="container py-4">
        <div class="col-md-8 py-4 text-white">
            <h1 class="fw-bold h5"><?php echo esc_html( $yoast_title ); ?></h1>

            <h2 class="fw-bold card-title display-4 my-3"><?php the_title(); ?></h2>

            <?php if ( $description ) : ?>
                <p class="lead card-text"><?php echo wp_kses_post( $description ); ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>
<!-- jarallax image -->

<?php if ( function_exists( 'qt_should_display_breadcrumbs' ) && qt_should_display_breadcrumbs() ) : ?>
    <div class="border-bottom container-fluid bg-light">
        <div class="container">
            <div class="row">
                <div class="col">
                    <nav class="my-3 d-none d-sm-none d-md-block" aria-label="breadcrumb">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <?php echo do_shortcode( '[custom_breadcrumbs]' ); ?>
                            </div>

                            <?php
                            global $wp;
                            $share_url   = '';
                            $share_title = $yoast_title;

                            if ( isset( $wp ) && isset( $wp->request ) ) {
                                $share_url = home_url( add_query_arg( array(), $wp->request ) );
                            }
                            ?>

                            <div class="dropdown">
                                <button class="btn rounded-0 p-2" type="button" id="shareDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fs-5 fa-share-alt text-primary"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="shareDropdown">
                                    <li>
                                        <a class="dropdown-item d-flex align-items-center" href="mailto:?subject=<?php echo rawurlencode( $share_title ); ?>&amp;body=<?php echo rawurlencode( $share_url ); ?>">
                                            <i class="fas fa-envelope me-2 text-primary"></i> Email
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item d-flex align-items-center" href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo rawurlencode( $share_url ); ?>" target="_blank" rel="noopener">
                                            <i class="fab fa-linkedin me-2 text-primary"></i> LinkedIn
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item d-flex align-items-center" href="https://twitter.com/intent/tweet?url=<?php echo rawurlencode( $share_url ); ?>&amp;text=<?php echo rawurlencode( $share_title ); ?>" target="_blank" rel="noopener">
                                            <i class="fab fa-x-twitter me-2 text-primary"></i> X
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item d-flex align-items-center" href="https://www.facebook.com/sharer/sharer.php?u=<?php echo rawurlencode( $share_url ); ?>" target="_blank" rel="noopener">
                                            <i class="fab fa-facebook me-2 text-primary"></i> Facebook
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </nav>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
