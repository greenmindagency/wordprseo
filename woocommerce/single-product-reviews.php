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
        <div class="col-lg-12 px-0">
            <div class="mb-5">
                <h2 class="fs-1 fw-bold mb-3"><?php esc_html_e( 'Reviews', 'woocommerce' ); ?></h2>
                <?php if ( $rating_count > 0 ) : ?>
                    <div class="d-flex flex-column flex-sm-row align-items-sm-center gap-3 text-muted">
                        <div class="d-flex align-items-center gap-3">
                            <span class="fw-semibold fs-5 text-dark"><?php echo esc_html( number_format_i18n( $average, 1 ) ); ?></span>
                            <ul class="list-unstyled d-flex gap-1 text-warning fs-5 mb-0">
                                <?php
                                printf(
                                    '<li class="visually-hidden">%s</li>',
                                    esc_html( sprintf( __( 'Rated %s out of 5', 'woocommerce' ), number_format_i18n( $average, 1 ) ) )
                                );

                                $full_stars  = floor( $average );
                                $fraction    = $average - $full_stars;
                                $has_half    = $fraction >= 0.25 && $fraction < 0.75;
                                $full_stars += $fraction >= 0.75 ? 1 : 0;

                                for ( $i = 1; $i <= 5; $i++ ) {
                                    if ( $i <= $full_stars ) {
                                        echo '<li><i class="fas fa-star"></i></li>';
                                    } elseif ( $has_half && $i === $full_stars + 1 ) {
                                        echo '<li><i class="fas fa-star-half-alt"></i></li>';
                                    } else {
                                        echo '<li><i class="far fa-star"></i></li>';
                                    }
                                }
                                ?>
                            </ul>
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
                    <div class="card-body p-4 p-lg-5 bg-light">
                        <?php
                        $commenter       = wp_get_current_commenter();
                        $current_rating  = isset( $_POST['rating'] ) ? absint( wp_unslash( $_POST['rating'] ) ) : 0;
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
                                'author' => sprintf(
                                    '<div class="mb-3"><label for="author" class="form-label fw-semibold">%1$s</label><input id="author" name="author" type="text" value="%2$s" class="form-control" required /></div>',
                                    esc_html__( 'Name', 'woocommerce' ),
                                    esc_attr( $commenter['comment_author'] )
                                ),
                                'email'  => sprintf(
                                    '<div class="mb-3"><label for="email" class="form-label fw-semibold">%1$s</label><input id="email" name="email" type="email" value="%2$s" class="form-control" required /></div>',
                                    esc_html__( 'Email', 'woocommerce' ),
                                    esc_attr( $commenter['comment_author_email'] )
                                ),
                            ),
                            'comment_field'        => '',
                        );

                        if ( wc_review_ratings_enabled() ) {
                            ob_start();
                            $rating_input_id = 'rating-value-' . ( $product ? $product->get_id() : 'product' );
                            ?>
                            <div class="mb-3">
                                <span id="rating-label" class="form-label d-block fw-semibold mb-2"><?php esc_html_e( 'Your rating', 'woocommerce' ); ?></span>
                                <div class="star-rating-input" data-max-rating="5">
                                    <input type="number" id="<?php echo esc_attr( $rating_input_id ); ?>" class="star-rating-input__value" name="rating" min="1" max="5" value="<?php echo $current_rating ? esc_attr( $current_rating ) : ''; ?>"<?php echo $current_rating ? '' : ' required'; ?> />
                                    <div class="star-rating-input__stars" role="radiogroup" aria-labelledby="rating-label">
                                        <?php
                                        for ( $rating_value = 1; $rating_value <= 5; $rating_value++ ) :
                                            $rating_text = sprintf( _n( '%s star', '%s stars', $rating_value, 'woocommerce' ), number_format_i18n( $rating_value ) );
                                            $is_selected = $current_rating >= $rating_value;
                                            $is_checked  = $current_rating === $rating_value;
                                            ?>
                                            <button
                                                type="button"
                                                class="star-rating-input__star<?php echo $is_selected ? ' is-selected' : ''; ?>"
                                                data-value="<?php echo esc_attr( $rating_value ); ?>"
                                                data-selected-text="<?php echo esc_attr( $rating_text ); ?>"
                                                role="radio"
                                                aria-checked="<?php echo $is_checked ? 'true' : 'false'; ?>"
                                                aria-label="<?php echo esc_attr( $rating_text ); ?>"
                                                tabindex="0"
                                            >
                                                <i class="fas fa-star" aria-hidden="true"></i>
                                                <span class="visually-hidden"><?php echo esc_html( $rating_text ); ?></span>
                                            </button>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <?php
                                $default_rating_message  = esc_html__( 'No rating selected.', 'msbdtcp' );
                                $selected_rating_message = $current_rating
                                    ? sprintf(
                                        /* translators: %s: rating description (e.g. "3 stars"). */
                                        esc_html__( 'Selected rating: %s', 'msbdtcp' ),
                                        sprintf( _n( '%s star', '%s stars', $current_rating, 'woocommerce' ), number_format_i18n( $current_rating ) )
                                    )
                                    : '';
                                ?>
                                <div class="selected-rating-display fw-semibold <?php echo $current_rating ? 'text-warning' : 'text-muted'; ?>" data-default-message="<?php echo esc_attr( $default_rating_message ); ?>" data-selected-prefix="<?php echo esc_attr( esc_html__( 'Selected rating: ', 'msbdtcp' ) ); ?>">
                                    <?php echo $current_rating ? esc_html( $selected_rating_message ) : esc_html( $default_rating_message ); ?>
                                </div>
                            </div>
                            <?php
                            $comment_form['comment_field'] .= ob_get_clean();
                        }

                        $comment_value = isset( $_POST['comment'] ) ? wp_unslash( $_POST['comment'] ) : '';
                        $comment_form['comment_field'] .= '<div class="mb-4">'
                            . '<label for="comment" class="form-label fw-semibold">' . esc_html__( 'Your review', 'woocommerce' ) . '</label>'
                            . '<textarea id="comment" name="comment" cols="45" rows="6" class="form-control" required>' . esc_textarea( $comment_value ) . '</textarea>'
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
<?php if ( wc_review_ratings_enabled() ) : ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var ratingComponents = document.querySelectorAll('.woocommerce-Reviews .star-rating-input');

        ratingComponents.forEach(function (component) {
            var hiddenInput = component.querySelector('.star-rating-input__value');
            var stars = Array.from(component.querySelectorAll('.star-rating-input__star'));
            var display = component.parentElement.querySelector('.selected-rating-display');
            var defaultMessage = display ? display.getAttribute('data-default-message') : '';
            var selectedPrefix = display ? display.getAttribute('data-selected-prefix') : '';
            var currentRating = parseInt(hiddenInput && hiddenInput.value ? hiddenInput.value : 0, 10) || 0;

            var clearHoverState = function () {
                stars.forEach(function (star) {
                    star.classList.remove('is-hover');
                });
            };

            var applyHoverState = function (rating) {
                stars.forEach(function (star) {
                    var starValue = parseInt(star.getAttribute('data-value'), 10);
                    star.classList.toggle('is-hover', rating >= starValue);
                });
            };

            var updateDisplay = function (rating, selectedText) {
                if (!display) {
                    return;
                }

                if (rating > 0) {
                    var message = selectedPrefix ? selectedPrefix + selectedText : selectedText;
                    display.textContent = message;
                    display.classList.remove('text-muted', 'text-danger');
                    display.classList.add('text-warning');
                } else {
                    display.textContent = defaultMessage;
                    display.classList.remove('text-warning', 'text-danger');
                    display.classList.add('text-muted');
                }
            };

            var applySelectionState = function (rating) {
                var activeIndex = rating > 0 ? rating - 1 : 0;

                stars.forEach(function (star, index) {
                    var starValue = parseInt(star.getAttribute('data-value'), 10);
                    var isSelected = rating > 0 && rating >= starValue;
                    star.classList.toggle('is-selected', isSelected);
                    star.setAttribute('aria-checked', rating === starValue ? 'true' : 'false');
                    star.setAttribute('tabindex', index === activeIndex ? '0' : '-1');
                });
            };

            var setRating = function (rating, selectedText) {
                currentRating = rating;

                if (hiddenInput) {
                    if (rating > 0) {
                        hiddenInput.value = rating;
                        hiddenInput.required = false;
                        hiddenInput.setCustomValidity('');
                    } else {
                        hiddenInput.value = '';
                        hiddenInput.required = true;
                    }
                }

                applySelectionState(currentRating);
                updateDisplay(currentRating, selectedText || '');
                clearHoverState();
            };

            stars.forEach(function (star, index) {
                var value = parseInt(star.getAttribute('data-value'), 10);
                var selectedText = star.getAttribute('data-selected-text') || '';

                star.addEventListener('click', function (event) {
                    event.preventDefault();
                    setRating(value, selectedText);
                });

                star.addEventListener('mouseenter', function () {
                    applyHoverState(value);
                });

                star.addEventListener('mouseleave', function () {
                    clearHoverState();
                });

                star.addEventListener('focus', function () {
                    clearHoverState();
                });

                star.addEventListener('keydown', function (event) {
                    if (event.key === 'ArrowRight' || event.key === 'ArrowUp') {
                        event.preventDefault();
                        var nextIndex = Math.min(stars.length - 1, index + 1);
                        stars[nextIndex].focus();
                    } else if (event.key === 'ArrowLeft' || event.key === 'ArrowDown') {
                        event.preventDefault();
                        var prevIndex = Math.max(0, index - 1);
                        stars[prevIndex].focus();
                    } else if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        setRating(value, selectedText);
                    }
                });
            });

            component.addEventListener('mouseleave', function () {
                clearHoverState();
            });

            var initialText = currentRating > 0 && stars[currentRating - 1]
                ? stars[currentRating - 1].getAttribute('data-selected-text')
                : '';
            setRating(currentRating, initialText);

            var form = component.closest('form');
            if (form) {
                form.addEventListener('submit', function (event) {
                    if (!hiddenInput || hiddenInput.value) {
                        if (display) {
                            display.classList.remove('text-danger');
                        }
                        return;
                    }

                    event.preventDefault();
                    if (display) {
                        display.textContent = defaultMessage;
                        display.classList.remove('text-warning');
                        display.classList.add('text-danger');
                    }

                    if (stars.length) {
                        stars[0].focus();
                    }
                });

                form.addEventListener('reset', function () {
                    setRating(0, '');
                });
            }
        });
    });
    </script>
<?php endif; ?>
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
