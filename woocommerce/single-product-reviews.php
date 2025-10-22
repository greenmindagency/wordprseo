<?php
/**
 * The template for displaying product reviews.
 *
 * @package WordprSEO
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

global $product;

if ( ! comments_open() ) {
    return;
}

$rating_count = $product ? $product->get_rating_count() : 0;
$review_count = $product ? $product->get_review_count() : 0;
$average      = $product ? $product->get_average_rating() : 0;
?>

<div id="reviews" class="woocommerce-Reviews container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="text-center mb-5">
                <h2 class="fs-1 fw-bold mb-3"><?php esc_html_e( 'Reviews', 'woocommerce' ); ?></h2>
                <?php if ( $rating_count > 0 ) : ?>
                    <div class="d-flex flex-column flex-sm-row align-items-center justify-content-center gap-2 text-muted">
                        <div class="d-flex align-items-center gap-2">
                            <span class="fw-semibold fs-5 text-dark"><?php echo esc_html( number_format( $average, 1 ) ); ?></span>
                            <span class="product-review-card__stars text-warning d-inline-flex">
                                <?php echo wc_get_rating_html( $average ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </span>
                        </div>
                        <span class="small text-uppercase tracking-wide">
                            <?php
                            printf(
                                /* translators: 1: number of reviews */
                                esc_html( _n( '%s review', '%s reviews', $review_count, 'woocommerce' ) ),
                                esc_html( number_format_i18n( $review_count ) )
                            );
                            ?>
                        </span>
                    </div>
                <?php else : ?>
                    <p class="text-muted mb-0"><?php esc_html_e( 'There are no reviews yet.', 'woocommerce' ); ?></p>
                <?php endif; ?>
            </div>

            <?php if ( have_comments() ) : ?>
                <div class="product-review-list">
                    <?php
                    wp_list_comments(
                        array(
                            'per_page' => get_option( 'comments_per_page', 10 ),
                            'style'    => 'div',
                            'callback' => 'wordprseo_product_review',
                        )
                    );
                    ?>
                </div>

                <?php if ( get_comment_pages_count() > 1 && get_option( 'page_comments' ) ) : ?>
                    <nav class="woocommerce-pagination text-center my-4" aria-label="<?php esc_attr_e( 'Product reviews navigation', 'woocommerce' ); ?>">
                        <?php paginate_comments_links( array( 'prev_text' => '&laquo;', 'next_text' => '&raquo;' ) ); ?>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>

            <div id="review_form_wrapper" class="mt-5">
                <div id="review_form" class="product-review-form card border-0 shadow-sm bg-white rounded-4">
                    <div class="card-body p-4 p-lg-5">
                        <?php
                        $commenter    = wp_get_current_commenter();
                        $comment_form = array(
                            'title_reply'          => have_comments() ? esc_html__( 'Share your experience', 'msbdtcp' ) : sprintf( esc_html__( 'Be the first to review “%s”', 'woocommerce' ), get_the_title() ),
                            'title_reply_to'       => esc_html__( 'Leave a Reply to %s', 'woocommerce' ),
                            'title_reply_before'   => '<h3 class="fs-3 fw-bold mb-4">',
                            'title_reply_after'    => '</h3>',
                            'comment_notes_after'  => '',
                            'comment_notes_before' => '',
                            'label_submit'         => esc_html__( 'Submit review', 'msbdtcp' ),
                            'class_submit'         => 'btn btn-primary px-4',
                            'submit_button'        => '<button name="%1$s" type="submit" id="%2$s" class="%3$s">%4$s</button>',
                            'logged_in_as'         => '',
                            'fields'               => array(
                                'author' => '<div class="mb-3">'
                                    . '<label for="author" class="form-label fw-semibold">' . esc_html__( 'Name', 'woocommerce' ) . '</label>'
                                    . '<input id="author" name="author" type="text" value="' . esc_attr( $commenter['comment_author'] ) . '" class="form-control" required />'
                                    . '</div>',
                                'email'  => '<div class="mb-3">'
                                    . '<label for="email" class="form-label fw-semibold">' . esc_html__( 'Email', 'woocommerce' ) . '</label>'
                                    . '<input id="email" name="email" type="email" value="' . esc_attr( $commenter['comment_author_email'] ) . '" class="form-control" required />'
                                    . '</div>',
                            ),
                            'comment_field'        => '',
                        );

                        if ( wc_review_ratings_enabled() ) {
                            $comment_form['comment_field'] .= '<div class="mb-3">'
                                . '<label for="rating" class="form-label fw-semibold">' . esc_html__( 'Your rating', 'woocommerce' ) . '</label>'
                                . '<select name="rating" id="rating" class="form-select w-auto">'
                                . '<option value="">' . esc_html__( 'Rate…', 'woocommerce' ) . '</option>'
                                . '<option value="5">' . esc_html__( 'Perfect', 'woocommerce' ) . '</option>'
                                . '<option value="4">' . esc_html__( 'Good', 'woocommerce' ) . '</option>'
                                . '<option value="3">' . esc_html__( 'Average', 'woocommerce' ) . '</option>'
                                . '<option value="2">' . esc_html__( 'Not that bad', 'woocommerce' ) . '</option>'
                                . '<option value="1">' . esc_html__( 'Very poor', 'woocommerce' ) . '</option>'
                                . '</select>'
                                . '</div>';
                        }

                        $comment_form['comment_field'] .= '<div class="mb-4">'
                            . '<label for="comment" class="form-label fw-semibold">' . esc_html__( 'Your review', 'woocommerce' ) . '</label>'
                            . '<textarea id="comment" name="comment" cols="45" rows="6" class="form-control" required></textarea>'
                            . '</div>';

                        if ( get_option( 'comment_registration' ) && ! is_user_logged_in() ) {
                            $comment_form['must_log_in'] = '<p class="woocommerce-info mb-0">'
                                . sprintf(
                                    wp_kses(
                                        /* translators: %s is link to login page. */
                                        __( 'You must be <a href="%s" class="fw-semibold">logged in</a> to post a review.', 'woocommerce' ),
                                        array( 'a' => array( 'href' => array(), 'class' => array() ) )
                                    ),
                                    esc_url( wp_login_url( get_permalink() ) )
                                )
                                . '</p>';
                        }

                        comment_form( $comment_form );
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
if ( ! function_exists( 'wordprseo_product_review' ) ) {
    /**
     * Custom callback for rendering product reviews in a testimonial inspired layout.
     *
     * @param WP_Comment $comment Comment object.
     * @param array      $args    Arguments passed to wp_list_comments().
     * @param int        $depth   Comment depth.
     */
    function wordprseo_product_review( $comment, $args, $depth ) {
        $rating_enabled = wc_review_ratings_enabled();
        $rating         = intval( get_comment_meta( $comment->comment_ID, 'rating', true ) );
        $verified       = wc_review_is_from_verified_owner( $comment->comment_ID );
        $product_id     = $comment->comment_post_ID;
        $image_id       = get_post_thumbnail_id( $product_id );

        $image_src = '';
        $image_w   = '';
        $image_h   = '';

        if ( $image_id ) {
            $image_data = wp_get_attachment_image_src( $image_id, 'large' );
            if ( $image_data ) {
                $image_src = $image_data[0];
                $image_w   = $image_data[1];
                $image_h   = $image_data[2];
            }
        }

        if ( ! $image_src ) {
            $image_src = wc_placeholder_img_src();
        }

        do_action( 'woocommerce_review_before', $comment );
        ?>
        <div <?php comment_class( 'product-review-card shadow-sm bg-white rounded-4 overflow-hidden mb-4' ); ?> id="comment-<?php comment_ID(); ?>">
            <div class="row g-0 align-items-stretch">
                <div class="col-lg-5 col-md-4 review-card-media bg-light">
                    <img class="img-fluid w-100 h-100 object-fit-cover" src="<?php echo esc_url( $image_src ); ?>" alt="<?php echo esc_attr( get_the_title( $product_id ) ); ?>" <?php if ( $image_w ) : ?>width="<?php echo esc_attr( $image_w ); ?>"<?php endif; ?> <?php if ( $image_h ) : ?>height="<?php echo esc_attr( $image_h ); ?>"<?php endif; ?> loading="lazy" />
                </div>
                <div class="col-lg-7 col-md-8">
                    <div class="p-4 p-lg-5 h-100 d-flex flex-column justify-content-between">
                        <div>
                            <?php if ( '0' === $comment->comment_approved ) : ?>
                                <p class="text-info small mb-3"><?php esc_html_e( 'Your review is awaiting approval', 'woocommerce' ); ?></p>
                            <?php endif; ?>

                            <?php do_action( 'woocommerce_review_before_comment_meta', $comment ); ?>

                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                                <div>
                                    <p class="fs-3 fw-bold mb-1"><?php comment_author(); ?></p>
                                    <p class="text-muted small mb-0"><?php echo esc_html( sprintf( __( 'Reviewed on %s', 'msbdtcp' ), get_comment_date( wc_date_format(), $comment ) ) ); ?></p>
                                </div>
                                <?php if ( $rating_enabled && $rating ) : ?>
                                    <ul class="list-unstyled d-flex gap-1 text-warning fs-5 mb-0">
                                        <?php for ( $i = 1; $i <= 5; $i++ ) : ?>
                                            <li><i class="<?php echo $i <= $rating ? 'fas' : 'far'; ?> fa-star"></i></li>
                                        <?php endfor; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>

                            <?php if ( $verified ) : ?>
                                <span class="badge bg-primary-subtle text-primary text-uppercase fw-semibold mb-3 product-review-card__badge"><?php esc_html_e( 'Verified owner', 'woocommerce' ); ?></span>
                            <?php endif; ?>

                            <?php do_action( 'woocommerce_review_after_comment_meta', $comment ); ?>

                            <?php do_action( 'woocommerce_review_before_comment_text', $comment ); ?>

                            <div class="product-review-card__quote position-relative text-muted fs-5">
                                <i class="fas fa-quote-left text-primary me-2"></i>
                                <div class="product-review-card__quote-text">
                                    <?php comment_text(); ?>
                                </div>
                                <i class="fas fa-quote-right text-primary ms-2"></i>
                            </div>

                            <?php do_action( 'woocommerce_review_after_comment_text', $comment ); ?>
                        </div>

                    </div>
                </div>
            </div>
        </div>
        <?php do_action( 'woocommerce_review_after', $comment ); ?>
        <?php
    }
}
?>
