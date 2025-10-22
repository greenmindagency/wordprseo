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

$review_count = $product ? $product->get_review_count() :0;

// Ensure the custom callback for product reviews is defined before it's used.
if ( ! function_exists( 'wordprseo_product_review' ) ) {
 /**
 * Custom callback for rendering product reviews in a simplified card layout (matches snippet).
 *
 * @param WP_Comment $comment Comment object.
 * @param array $args Arguments passed to wp_list_comments().
 * @param int $depth Comment depth.
 */
 function wordprseo_product_review( $comment, $args, $depth ) {
 $rating_enabled = wc_review_ratings_enabled();
 $rating = intval( get_comment_meta( $comment->comment_ID, 'rating', true ) );
 $product_id = $comment->comment_post_ID;

 // Prepare image (not used as main avatar here but kept if needed later).
 $image_id = get_post_thumbnail_id( $product_id );
 $image_src = '';
 if ( $image_id ) {
 $image_data = wp_get_attachment_image_src( $image_id, 'large' );
 if ( $image_data ) {
 $image_src = $image_data[0];
 }
 }
 if ( ! $image_src ) {
 $image_src = wc_placeholder_img_src();
 }

 do_action( 'woocommerce_review_before', $comment );
 ?>
 <div <?php comment_class( 'p-4 shadow-sm bg-white border border-light border-opacity-50 product-review-card mb-4' ); ?> id="comment-<?php comment_ID(); ?>">
 <div class="d-flex align-items-center mb-2">
 <div class="reviewer-avatar rounded-circle d-flex align-items-center justify-content-center me-3 flex-shrink-0">
 <?php echo get_avatar( $comment,40 ); ?>
 </div>

 <div>
 <div class="d-flex align-items-center">
 <span class="fw-bold me-2 text-dark" style="font-size:1rem;"><?php comment_author(); ?></span>
 <?php if ( $rating_enabled && $rating ) : ?>
 <span class="text-warning star-rating" title="<?php echo esc_attr( sprintf( __( 'Rated %s out of5', 'woocommerce' ), $rating ) ); ?>">
 <?php for ( $i =1; $i <=5; $i++ ) : ?>
 <i class="<?php echo $i <= $rating ? 'fas' : 'far'; ?> fa-star"></i>
 <?php endfor; ?>
 </span>
 <?php endif; ?>
 </div>

 <span class="text-muted review-meta"><?php echo esc_html( sprintf( __( 'Reviewed on %s', 'msbdtcp' ), get_comment_date( wc_date_format(), $comment ) ) ); ?></span>
 </div>
 </div>

 <div class="p-3 mt-3 bg-light rounded-0">
 <p class="mb-0 review-comment fst-italic text-dark">
 <?php comment_text(); ?>
 </p>
 </div>
 </div>
 <?php
 do_action( 'woocommerce_review_after', $comment );
 }
}
?>

<div id="reviews" class="woocommerce-Reviews container my-5">
 <div class="row justify-content-center">
 <div class="col-lg-12 px-0">
 <div class="mb-5">
 <h2 class="fs-1 fw-bold mb-3"><?php esc_html_e( 'Reviews', 'woocommerce' ); ?></h2>
 <?php if ( $review_count >0 ) : ?>
 <p class="text-muted mb-0">
 <?php
 printf(
 /* translators:1: number of reviews */
 esc_html( _n( '%s review', '%s reviews', $review_count, 'woocommerce' ) ),
 esc_html( number_format_i18n( $review_count ) )
 );
 ?>
 </p>
 <?php else : ?>
 <p class="text-muted mb-0"><?php esc_html_e( 'There are no reviews yet.', 'woocommerce' ); ?></p>
 <?php endif; ?>
 </div>

 <?php if ( have_comments() ) : ?>
 <div class="product-review-list">
 <?php
 wp_list_comments(
 array(
 'per_page' => get_option( 'comments_per_page',10 ),
 'style' => 'div',
 // prevent WP from outputting a separate default avatar; our callback handles the avatar inside the card
 'avatar_size' =>0,
 'callback' => 'wordprseo_product_review',
 )
 );
 ?>
 </div>

 <?php if ( get_comment_pages_count() >1 && get_option( 'page_comments' ) ) : ?>
 <nav class="woocommerce-pagination text-center my-4" aria-label="<?php esc_attr_e( 'Product reviews navigation', 'woocommerce' ); ?>">
 <?php paginate_comments_links( array( 'prev_text' => '&laquo;', 'next_text' => '&raquo;' ) ); ?>
 </nav>
 <?php endif; ?>
 <?php endif; ?>

 <div id="review_form_wrapper" class="mt-5">
 <div id="review_form" class="product-review-form card border-0 shadow-sm bg-white rounded-4">
 <div class="card-body p-4 p-lg-5 bg-light">
 <?php
 $commenter = wp_get_current_commenter();
 $current_rating = isset( $_POST['rating'] ) ? absint( wp_unslash( $_POST['rating'] ) ) :0;
 $comment_form = array(
 'title_reply' => have_comments() ? esc_html__( 'Share your experience', 'msbdtcp' ) : sprintf( esc_html__( 'Be the first to review “%s”', 'woocommerce' ), get_the_title() ),
 'title_reply_to' => esc_html__( 'Leave a Reply to %s', 'woocommerce' ),
 'title_reply_before' => '<h3 class="fs-3 fw-bold mb-4">',
 'title_reply_after' => '</h3>',
 'comment_notes_after' => '',
 'comment_notes_before' => '',
 'label_submit' => esc_html__( 'Submit review', 'msbdtcp' ),
 'class_submit' => 'btn btn-primary px-4',
 'submit_button' => '<button name="%1$s" type="submit" id="%2$s" class="%3$s">%4$s</button>',
 'logged_in_as' => '',
 'fields' => array(
 'author' => sprintf(
 '<div class="mb-3"><label for="author" class="form-label fw-semibold">%1$s</label><input id="author" name="author" type="text" value="%2$s" class="form-control" required /></div>',
 esc_html__( 'Name', 'woocommerce' ),
 esc_attr( $commenter['comment_author'] )
 ),
 'email' => sprintf(
 '<div class="mb-3"><label for="email" class="form-label fw-semibold">%1$s</label><input id="email" name="email" type="email" value="%2$s" class="form-control" required /></div>',
 esc_html__( 'Email', 'woocommerce' ),
 esc_attr( $commenter['comment_author_email'] )
 ),
 ),
 'comment_field' => '',
 );

 if ( wc_review_ratings_enabled() ) {
 ob_start();
 $rating_input_id = 'rating-value-' . ( $product ? $product->get_id() : 'product' );
 ?>
 <div class="mb-3">
 <span id="rating-label" class="form-label d-block fw-semibold mb-2"><?php esc_html_e( 'Your rating', 'woocommerce' ); ?></span>
 <input
 type="number"
 id="<?php echo esc_attr( $rating_input_id ); ?>"
 class="star-rating-input__value"
 name="rating"
 min="1"
 max="5"
 step="1"
 aria-hidden="true"
 value="<?php echo $current_rating ? esc_attr( $current_rating ) : ''; ?>"
 <?php echo $current_rating ? '' : ' required'; ?>
 />
 <div class="star-rating-input" role="radiogroup" aria-labelledby="rating-label">
 <?php for ( $rating_value =5; $rating_value >=1; $rating_value-- ) :
 $rating_text = sprintf( _n( '%s star', '%s stars', $rating_value, 'woocommerce' ), number_format_i18n( $rating_value ) );
 ?>
 <input
 type="radio"
 id="<?php echo esc_attr( $rating_input_id . '-' . $rating_value ); ?>"
 name="rating-choice"
 value="<?php echo esc_attr( $rating_value ); ?>"
 data-selected-text="<?php echo esc_attr( $rating_text ); ?>"
 <?php checked( $current_rating, $rating_value ); ?>
 />
 <label for="<?php echo esc_attr( $rating_input_id . '-' . $rating_value ); ?>" title="<?php echo esc_attr( $rating_text ); ?>">
 <i class="fas fa-star" aria-hidden="true"></i>
 <span class="visually-hidden"><?php echo esc_html( $rating_text ); ?></span>
 </label>
 <?php endfor; ?>
 </div>
 <?php
 $default_rating_message = esc_html__( 'No rating selected.', 'msbdtcp' );
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
 <style>
 /* Hide any avatar output that appears before/around the review list (prevents duplicate avatar) */
 .product-review-list img.avatar { display: none !important; }
 /* Explicitly allow avatar inside our card to be visible */
 .product-review-card .reviewer-avatar img { display: inline-block !important; }

 .woocommerce-Reviews .star-rating-input {
 display: inline-flex;
 flex-direction: row-reverse;
 gap:0.35rem;
 font-size:2.25rem;
 margin-bottom:1rem;
 }

 .woocommerce-Reviews .star-rating-input__value {
 position: absolute;
 width:1px;
 height:1px;
 padding:0;
 margin: -1px;
 overflow: hidden;
 clip: rect(0,0,0,0);
 border:0;
 }

 .woocommerce-Reviews .star-rating-input input {
 position: absolute;
 opacity:0;
 pointer-events: none;
 }

 .woocommerce-Reviews .star-rating-input label {
 cursor: pointer;
 display: inline-flex;
 color: #ced4da;
 transition: color0.2s ease;
 }

 .woocommerce-Reviews .star-rating-input label:hover,
 .woocommerce-Reviews .star-rating-input label:hover ~ label {
 color: #ffc107;
 }

 .woocommerce-Reviews .star-rating-input input:checked ~ label {
 color: #ffc107;
 }

 .woocommerce-Reviews .star-rating-input input:checked ~ label:hover,
 .woocommerce-Reviews .star-rating-input input:checked ~ label:hover ~ label {
 color: #ffc107;
 }
 </style>
 <script>
 document.addEventListener('DOMContentLoaded', function () {
 var ratingGroups = document.querySelectorAll('.woocommerce-Reviews .star-rating-input');

 ratingGroups.forEach(function (group) {
 var inputs = Array.prototype.slice.call(group.querySelectorAll('input[name="rating-choice"]'));
 var hiddenInput = group.parentElement.querySelector('.star-rating-input__value');
 var display = group.parentElement.querySelector('.selected-rating-display');
 var defaultMessage = display ? display.getAttribute('data-default-message') : '';
 var selectedPrefix = display ? display.getAttribute('data-selected-prefix') : '';

 var updateDisplay = function () {
 if (!display) {
 return;
 }

 var checkedInput = group.querySelector('input[name="rating-choice"]:checked');

 if (!checkedInput && hiddenInput && hiddenInput.value) {
 checkedInput = group.querySelector('input[name="rating-choice"][value="' + hiddenInput.value + '"]');
 if (checkedInput) {
 checkedInput.checked = true;
 }
 }

 if (checkedInput) {
 var selectedText = checkedInput.getAttribute('data-selected-text') || '';
 var value = checkedInput.value;
 if (!selectedText) {
 var textTemplate = parseInt(value,10) ===1 ? '%s star' : '%s stars';
 selectedText = textTemplate.replace('%s', value);
 }
 var message = selectedPrefix ? selectedPrefix + selectedText : selectedText;
 display.textContent = message;
 display.classList.remove('text-muted', 'text-danger');
 display.classList.add('text-warning');
 if (hiddenInput) {
 hiddenInput.value = value;
 hiddenInput.required = false;
 hiddenInput.setCustomValidity('');
 }
 } else {
 display.textContent = defaultMessage;
 display.classList.remove('text-warning', 'text-danger');
 display.classList.add('text-muted');
 if (hiddenInput) {
 hiddenInput.value = '';
 hiddenInput.required = true;
 hiddenInput.setCustomValidity('');
 }
 }
 };

 inputs.forEach(function (input) {
 input.addEventListener('change', updateDisplay);
 });

 updateDisplay();

 var form = group.closest('form');

 if (form) {
 form.addEventListener('submit', function (event) {
 var checkedInput = group.querySelector('input[name="rating-choice"]:checked');

 if (checkedInput && (!hiddenInput || hiddenInput.value)) {
 if (display) {
 display.classList.remove('text-danger');
 }
 return;
 }

 event.preventDefault();

 if (display) {
 display.textContent = defaultMessage;
 display.classList.remove('text-warning', 'text-muted');
 display.classList.add('text-danger');
 }

 if (inputs.length) {
 inputs[0].focus();
 }
 });

 form.addEventListener('reset', function () {
 window.setTimeout(function () {
 updateDisplay();
 },0);
 });
 }
 });
 });
 </script>
<?php endif; ?>