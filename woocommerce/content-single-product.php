<?php
/**
 * Custom single product layout.
 *
 * @package WordPrSEO
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_single_product' );

if ( post_password_required() ) {
    echo get_the_password_form();
    return;
}

global $product;

$product_id    = get_the_ID();
$short_excerpt = apply_filters( 'woocommerce_short_description', get_the_excerpt() );
$price_html    = $product instanceof WC_Product ? $product->get_price_html() : '';
$category_html = function_exists( 'wc_get_product_category_list' )
    ? wc_get_product_category_list( $product_id, ', ' )
    : '';
$tag_html      = function_exists( 'wc_get_product_tag_list' )
    ? wc_get_product_tag_list( $product_id, ', ' )
    : '';

$category_count = 0;
$tag_count      = 0;

if ( function_exists( 'wp_get_post_terms' ) ) {
    $category_terms = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );

    if ( ! is_wp_error( $category_terms ) ) {
        $category_count = count( $category_terms );
    }

    $tag_terms = wp_get_post_terms( $product_id, 'product_tag', array( 'fields' => 'ids' ) );

    if ( ! is_wp_error( $tag_terms ) ) {
        $tag_count = count( $tag_terms );
    }
}

$category_label = _n( 'Category', 'Categories', $category_count ? $category_count : 1, 'woocommerce' );
$tag_label      = _n( 'Tag', 'Tags', $tag_count ? $tag_count : 1, 'woocommerce' );

$meta_lines = array();

if ( $product instanceof WC_Product && apply_filters( 'wc_product_sku_enabled', true ) ) {
    $sku_value = $product->get_sku();

    if ( ! $sku_value ) {
        $sku_value = esc_html__( 'N/A', 'woocommerce' );
    }

    $meta_lines[] = array(
        'label'   => __( 'SKU', 'woocommerce' ),
        'value'   => $sku_value,
        'is_html' => false,
    );
}

$meta_lines[] = array(
    'label'   => $category_label,
    'value'   => $category_html ? $category_html : esc_html__( 'N/A', 'woocommerce' ),
    'is_html' => ! empty( $category_html ),
);

$meta_lines[] = array(
    'label'   => $tag_label,
    'value'   => $tag_html ? $tag_html : esc_html__( 'N/A', 'woocommerce' ),
    'is_html' => ! empty( $tag_html ),
);

$featured_id  = $product instanceof WC_Product ? $product->get_image_id() : 0;
$gallery_ids  = $product instanceof WC_Product ? $product->get_gallery_image_ids() : array();
$carousel_ids = array();

if ( $featured_id ) {
    $carousel_ids[] = $featured_id;
}

foreach ( $gallery_ids as $image_id ) {
    if ( $image_id && $image_id !== $featured_id ) {
        $carousel_ids[] = $image_id;
    }
}

$carousel_ids = array_values( array_unique( array_filter( $carousel_ids ) ) );
$carousel_id      = 'productCarousel-' . $product_id;
$hero_image_data  = $featured_id ? wp_get_attachment_image_src( $featured_id, 'full' ) : false;
$hero_image_url   = $hero_image_data ? ( isset( $hero_image_data[0] ) ? $hero_image_data[0] : '' ) : '';
$hero_image_width = $hero_image_data ? ( isset( $hero_image_data[1] ) ? (int) $hero_image_data[1] : 0 ) : 0;
$hero_image_height = $hero_image_data ? ( isset( $hero_image_data[2] ) ? (int) $hero_image_data[2] : 0 ) : 0;
$hero_kicker      = $category_html ? wp_strip_all_tags( $category_html ) : __( 'Shop', 'woocommerce' );
$hero_summary     = $short_excerpt ? wp_strip_all_tags( $short_excerpt ) : '';

$tabs = apply_filters( 'woocommerce_product_tabs', array() );

$primary_tabs   = array();
$remaining_tabs = $tabs;
$reviews_tab    = null;

foreach ( array( 'additional_information', 'description' ) as $tab_key ) {
    if ( isset( $remaining_tabs[ $tab_key ] ) ) {
        $primary_tabs[ $tab_key ] = $remaining_tabs[ $tab_key ];
        unset( $remaining_tabs[ $tab_key ] );
    }
}

if ( isset( $remaining_tabs['reviews'] ) ) {
    $reviews_tab = array(
        'key' => 'reviews',
        'tab' => $remaining_tabs['reviews'],
    );

    unset( $remaining_tabs['reviews'] );
}

$sale_flash_html        = '';
$before_summary_extra   = '';
$after_summary_extra    = '';

if ( $product instanceof WC_Product ) {
    remove_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_sale_flash', 10 );
    remove_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_images', 20 );
    remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_title', 5 );
    remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_rating', 10 );
    remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 10 );
    remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20 );
    remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
    remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40 );
    remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_sharing', 50 );
    remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_product_data_tabs', 10 );
    remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_upsell_display', 15 );
    remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20 );

    ob_start();
    woocommerce_show_product_sale_flash();
    $sale_flash_html = trim( ob_get_clean() );

    ob_start();
    do_action( 'woocommerce_before_single_product_summary' );
    $before_summary_extra = trim( ob_get_clean() );

}

$related_products = array();

if ( function_exists( 'wc_get_related_products' ) && $product instanceof WC_Product ) {
    $related_ids = wc_get_related_products( $product_id, 3 );

    if ( ! empty( $related_ids ) ) {
        foreach ( $related_ids as $related_id ) {
            $related_product = wc_get_product( $related_id );

            if ( ! $related_product ) {
                continue;
            }

            $related_products[] = $related_product;
        }
    }
}

ob_start();
do_action( 'woocommerce_after_single_product_summary' );
$after_summary_extra = trim( ob_get_clean() );
?>

<main id="primary" class="site-main mt-4">
    <article id="product-<?php the_ID(); ?>" <?php wc_product_class( 'single-product', get_the_ID() ); ?>>
        <div class="container py-spacer-2">
            <section class="row g-5">
                <div class="col-md-6">
                    <div class="d-flex flex-column product-gallery-wrapper" data-carousel="<?php echo esc_attr( $carousel_id ); ?>">
                        <div
                            id="<?php echo esc_attr( $carousel_id ); ?>"
                            class="carousel slide shadow-sm overflow-hidden product-gallery-carousel"
                            data-bs-ride="carousel"
                            data-bs-interval="5000"
                        >
                            <div class="carousel-inner">
                                <?php if ( ! empty( $carousel_ids ) ) : ?>
                                    <?php foreach ( $carousel_ids as $index => $image_id ) : ?>
                                        <?php
                                        $image_attributes = array(
                                            'class'    => 'product-carousel-image img-fluid mx-auto d-block',
                                            'loading'  => 0 === $index ? 'eager' : 'lazy',
                                            'decoding' => 'async',
                                        );

                                        $image_html = wp_get_attachment_image( $image_id, 'large', false, $image_attributes );

                                        if ( ! $image_html ) {
                                            $image_html = sprintf(
                                                '<img src="%1$s" alt="%2$s" class="product-carousel-image img-fluid mx-auto d-block" loading="%3$s" decoding="async" />',
                                                esc_url( wc_placeholder_img_src( 'woocommerce_single' ) ),
                                                esc_attr( get_the_title( $product_id ) ),
                                                0 === $index ? 'eager' : 'lazy'
                                            );
                                        }
                                        ?>
                                        <div class="carousel-item<?php echo 0 === $index ? ' active' : ''; ?>">
                                            <div class="product-gallery-frame position-relative">
                                                <?php if ( $sale_flash_html && 0 === $index ) : ?>
                                                    <div class="position-absolute top-0 start-0 m-3">
                                                        <?php echo $sale_flash_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php echo $image_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <div class="carousel-item active">
                                        <div class="product-gallery-frame position-relative">
                                            <?php if ( $sale_flash_html ) : ?>
                                                <div class="position-absolute top-0 start-0 m-3">
                                                    <?php echo $sale_flash_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                                </div>
                                            <?php endif; ?>
                                            <img
                                                src="<?php echo esc_url( wc_placeholder_img_src( 'woocommerce_single' ) ); ?>"
                                                alt="<?php echo esc_attr( get_the_title( $product_id ) ); ?>"
                                                class="product-carousel-image img-fluid mx-auto d-block"
                                                loading="eager"
                                                decoding="async"
                                            >
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ( count( $carousel_ids ) > 1 ) : ?>
                            <div class="product-gallery-indicators d-flex justify-content-center mt-3 gap-2">
                                <?php foreach ( $carousel_ids as $index => $image_id ) : ?>
                                    <?php
                                    $thumbnail_attributes = array(
                                        'class'    => 'img-fluid rounded',
                                        'loading'  => 0 === $index ? 'eager' : 'lazy',
                                        'decoding' => 'async',
                                    );

                                    $thumbnail_html = wp_get_attachment_image( $image_id, 'thumbnail', false, $thumbnail_attributes );

                                    if ( ! $thumbnail_html ) {
                                        $thumbnail_html = '<span class="placeholder-thumbnail d-inline-block bg-light rounded" aria-hidden="true"></span>';
                                    }
                                    ?>
                                    <button
                                        type="button"
                                        data-bs-target="#<?php echo esc_attr( $carousel_id ); ?>"
                                        data-bs-slide-to="<?php echo esc_attr( $index ); ?>"
                                        class="image-indicator-btn btn btn-outline-secondary p-1<?php echo 0 === $index ? ' active' : ''; ?>"
                                        aria-label="<?php echo esc_attr( sprintf( __( 'View image %d', 'woocommerce' ), $index + 1 ) ); ?>"
                                        <?php if ( 0 === $index ) : ?>aria-current="true"<?php endif; ?>
                                    >
                                        <?php echo $thumbnail_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ( ! empty( $before_summary_extra ) ) : ?>
                            <div class="mt-4">
                                <?php echo $before_summary_extra; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <?php if ( $category_html ) : ?>
                        <p class="text-uppercase small fw-semibold mb-2 text-muted">
                            <?php echo wp_kses_post( $category_html ); ?>
                        </p>
                    <?php endif; ?>

                    <h1 class="display-5 fw-bold mb-0"><?php the_title(); ?></h1>

                    <?php if ( ! empty( $short_excerpt ) ) : ?>
                        <div class="text-muted lead mb-4"><?php echo wp_kses_post( $short_excerpt ); ?></div>
                    <?php endif; ?>

                    <?php if ( ! empty( $price_html ) ) : ?>
                        <h2 class="mb-4 text-primary fw-bold">
                            <?php echo wp_kses_post( $price_html ); ?>
                        </h2>
                    <?php endif; ?>

                    <?php woocommerce_template_single_rating(); ?>

                    <?php if ( ! empty( $primary_tabs ) ) : ?>
                        <?php
                        $primary_tab_index = 0;
                        $tab_prefix        = 'primary-product-tabs-' . $product_id;
                        ?>
                        <div class="product-summary-tabs my-4">
                            <ul class="nav nav-tabs" id="<?php echo esc_attr( $tab_prefix ); ?>" role="tablist">
                                <?php foreach ( $primary_tabs as $key => $tab ) : ?>
                                    <li class="nav-item" role="presentation">
                                        <button
                                            class="nav-link<?php echo 0 === $primary_tab_index ? ' active' : ''; ?>"
                                            id="<?php echo esc_attr( $tab_prefix . '-' . $key ); ?>-tab"
                                            data-bs-toggle="tab"
                                            data-bs-target="#<?php echo esc_attr( $tab_prefix . '-' . $key ); ?>"
                                            type="button"
                                            role="tab"
                                            aria-controls="<?php echo esc_attr( $tab_prefix . '-' . $key ); ?>"
                                            aria-selected="<?php echo 0 === $primary_tab_index ? 'true' : 'false'; ?>"
                                        >
                                            <?php echo esc_html( $tab['title'] ); ?>
                                        </button>
                                    </li>
                                    <?php $primary_tab_index++; ?>
                                <?php endforeach; ?>
                            </ul>
                            <div class="tab-content border border-top-0 p-3">
                                <?php
                                $primary_tab_index = 0;
                                foreach ( $primary_tabs as $key => $tab ) :
                                    $tab_panel_id = $tab_prefix . '-' . $key;
                                    ?>
                                    <div
                                        class="tab-pane fade<?php echo 0 === $primary_tab_index ? ' show active' : ''; ?>"
                                        id="<?php echo esc_attr( $tab_panel_id ); ?>"
                                        role="tabpanel"
                                        aria-labelledby="<?php echo esc_attr( $tab_panel_id ); ?>-tab"
                                        tabindex="0"
                                    >
                                        <?php
                                        if ( isset( $tab['callback'] ) ) {
                                            call_user_func( $tab['callback'], $tab, $key );
                                        }
                                        ?>
                                    </div>
                                    <?php
                                    $primary_tab_index++;
                                endforeach;
                                ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ( $product instanceof WC_Product ) : ?>
                        <div class="product-meta-lines mb-4">
                            <?php if ( wc_product_sku_enabled() ) : ?>
                                <p class="mb-2"><span class="fw-semibold"><?php esc_html_e( 'SKU:', 'woocommerce' ); ?></span> <?php echo esc_html( $product->get_sku() ? $product->get_sku() : __( 'N/A', 'woocommerce' ) ); ?></p>
                            <?php endif; ?>
                            <?php if ( $category_html ) : ?>
                                <p class="mb-2"><span class="fw-semibold"><?php esc_html_e( 'Category:', 'woocommerce' ); ?></span> <?php echo wp_kses_post( $category_html ); ?></p>
                            <?php endif; ?>
                            <?php
                            $tag_html = function_exists( 'wc_get_product_tag_list' )
                                ? wc_get_product_tag_list( $product_id, ', ' )
                                : '';

                            if ( $tag_html ) :
                                ?>
                                <p class="mb-0"><span class="fw-semibold"><?php esc_html_e( 'Tag:', 'woocommerce' ); ?></span> <?php echo wp_kses_post( $tag_html ); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="product-purchase-panel card border-0 shadow-sm p-4 mb-4 bg-light">
                        <?php woocommerce_template_single_add_to_cart(); ?>
                    </div>

                    <div class="product-meta small text-muted">
                        <?php woocommerce_template_single_sharing(); ?>
                    </div>

                    <?php
                    ob_start();
                    do_action( 'woocommerce_single_product_summary' );
                    $extra_summary_content = trim( ob_get_clean() );

                    if ( ! empty( $extra_summary_content ) ) :
                        ?>
                        <div class="product-extra-summary mt-4">
                            <?php echo $extra_summary_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <?php if ( ! empty( $remaining_tabs ) ) : ?>
                <?php
                $tab_index = 0;
                ?>
                <div class="mt-5">
                    <ul class="nav nav-tabs" id="productTabs" role="tablist">
                        <?php foreach ( $remaining_tabs as $key => $tab ) : ?>
                            <li class="nav-item" role="presentation">
                                <button
                                    class="nav-link<?php echo 0 === $tab_index ? ' active' : ''; ?>"
                                    id="<?php echo esc_attr( $key ); ?>-tab"
                                    data-bs-toggle="tab"
                                    data-bs-target="#<?php echo esc_attr( $key ); ?>-tab-pane"
                                    type="button"
                                    role="tab"
                                    aria-controls="<?php echo esc_attr( $key ); ?>-tab-pane"
                                    aria-selected="<?php echo 0 === $tab_index ? 'true' : 'false'; ?>"
                                >
                                    <?php echo esc_html( $tab['title'] ); ?>
                                </button>
                            </li>
                            <?php $tab_index++; ?>
                        <?php endforeach; ?>
                    </ul>

                    <div class="tab-content border border-top-0 p-3" id="productTabsContent">
                        <?php
                        $tab_index = 0;
                        foreach ( $remaining_tabs as $key => $tab ) :
                            $tab_panel_id = $key . '-tab-pane';
                            ?>
                            <div
                                class="tab-pane fade<?php echo 0 === $tab_index ? ' show active' : ''; ?>"
                                id="<?php echo esc_attr( $tab_panel_id ); ?>"
                                role="tabpanel"
                                aria-labelledby="<?php echo esc_attr( $key ); ?>-tab"
                                tabindex="0"
                            >
                                <?php
                                if ( isset( $tab['callback'] ) ) {
                                    call_user_func( $tab['callback'], $tab, $key );
                                }
                                ?>
                            </div>
                            <?php
                            $tab_index++;
                        endforeach;
                        ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ( ! empty( $reviews_tab ) && isset( $reviews_tab['tab']['callback'] ) ) : ?>
                <div class="mt-5">
                    <?php call_user_func( $reviews_tab['tab']['callback'], $reviews_tab['tab'], $reviews_tab['key'] ); ?>
                </div>
            <?php endif; ?>

            <?php if ( ! empty( $related_products ) ) : ?>
                
                <section class="container py-spacer">
				<hr class="my-5">
                    <h2 class="mb-4 text-center text-md-start"><?php esc_html_e( 'Explore Related Products', 'woocommerce' ); ?></h2>
                    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 g-4">
                        <?php foreach ( $related_products as $related_product ) : ?>
                            <?php
                            $related_id        = $related_product->get_id();
                            $related_title     = $related_product->get_name();
                            $related_link      = get_permalink( $related_id );
                            $related_price     = $related_product->get_price_html();
                            $related_categories = function_exists( 'wc_get_product_category_list' ) ? wc_get_product_category_list( $related_id, ', ' ) : '';
                            $related_image_id  = $related_product->get_image_id();
                            $related_image     = '';
                            $related_image_alt = wp_strip_all_tags( $related_title );

                            if ( $related_image_id ) {
                                $related_image = wp_get_attachment_image(
                                    $related_image_id,
                                    'woocommerce_single',
                                    false,
                                    array(
                                        'class'    => 'card-img-top img-fluid',
                                        'loading'  => 'lazy',
                                        'decoding' => 'async',
                                        'alt'      => $related_image_alt,
                                    )
                                );
                            }

                            if ( ! $related_image ) {
                                $related_image = sprintf(
                                    '<img src="%1$s" alt="%2$s" class="card-img-top img-fluid" loading="lazy" decoding="async" />',
                                    esc_url( wc_placeholder_img_src( 'woocommerce_single' ) ),
                                    esc_attr( $related_image_alt )
                                );
                            }

                            $related_excerpt = $related_product->get_short_description();

                            if ( empty( $related_excerpt ) ) {
                                $related_excerpt = get_post_field( 'post_excerpt', $related_id );
                            }

                            if ( empty( $related_excerpt ) ) {
                                $related_excerpt = get_post_field( 'post_content', $related_id );
                            }

                            $related_excerpt = $related_excerpt ? wp_trim_words( wp_strip_all_tags( $related_excerpt ), 24, '&hellip;' ) : '';

                            global $product;
                            $previous_product   = $product;
                            $product            = $related_product;
                            $add_to_cart_markup = '';

                            if ( function_exists( 'woocommerce_template_loop_add_to_cart' ) ) {
                                ob_start();
                                woocommerce_template_loop_add_to_cart();
                                $add_to_cart_markup = trim( ob_get_clean() );
                            }

                            $product = $previous_product;
                            ?>
                            <div class="col">
                                <div class="card shadow product-related-card h-100 d-flex flex-column">
                                    <a href="<?php echo esc_url( $related_link ); ?>" class="d-block position-relative">
                                        <?php echo $related_image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                    </a>
                                    <div class="card-body d-flex flex-column pb-4">
                                        <?php if ( $related_categories ) : ?>
                                            <small class="lh-lg text-muted"><?php echo wp_kses_post( $related_categories ); ?></small>
                                        <?php endif; ?>

                                        <a href="<?php echo esc_url( $related_link ); ?>" class="text-decoration-none text-dark">
                                            <p class="h3 fw-bold mt-2 card-title"><?php echo esc_html( $related_title ); ?></p>
                                        </a>

                                        <?php
                                        if ( wc_review_ratings_enabled() ) {
                                            $rating_html = wc_get_rating_html( $related_product->get_average_rating(), $related_product->get_rating_count() );

                                            if ( $rating_html ) {
                                                echo '<div class="mb-2">' . $rating_html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                            }
                                        }
                                        ?>

                                        <?php if ( ! empty( $related_price ) ) : ?>
                                            <p class="h5 fw-bold text-primary mb-3"><?php echo wp_kses_post( $related_price ); ?></p>
                                        <?php endif; ?>

                                        <?php if ( $related_excerpt ) : ?>
                                            <p class="card-text"><?php echo esc_html( $related_excerpt ); ?></p>
                                        <?php endif; ?>

                                        <?php if ( $add_to_cart_markup ) : ?>
                                            <div class="mt-auto pt-3">
                                                <?php echo $add_to_cart_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ( ! empty( $after_summary_extra ) ) : ?>
                <div class="product-after-summary mt-5">
                    <?php echo $after_summary_extra; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ( count( $carousel_ids ) > 1 ) : ?>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    var wrappers = document.querySelectorAll('.product-gallery-wrapper[data-carousel]');

                    if (!wrappers.length) {
                        return;
                    }

                    wrappers.forEach(function (wrapper) {
                        var carouselId = wrapper.getAttribute('data-carousel');

                        if (!carouselId) {
                            return;
                        }

                        var carouselElement = document.getElementById(carouselId);

                        if (!carouselElement) {
                            return;
                        }

                        var indicatorButtons = wrapper.querySelectorAll('.product-gallery-indicators [data-bs-slide-to]');

                        if (!indicatorButtons.length) {
                            return;
                        }

                        carouselElement.addEventListener('slid.bs.carousel', function (event) {
                            var targetIndex = typeof event.to === 'number' ? event.to : 0;

                            indicatorButtons.forEach(function (button, index) {
                                var isActive = index === targetIndex;

                                button.classList.toggle('active', isActive);

                                if (isActive) {
                                    button.setAttribute('aria-current', 'true');
                                } else {
                                    button.removeAttribute('aria-current');
                                }
                            });
                        });

                        indicatorButtons.forEach(function (button, index) {
                            button.addEventListener('click', function () {
                                indicatorButtons.forEach(function (btn, innerIndex) {
                                    var isActive = innerIndex === index;

                                    btn.classList.toggle('active', isActive);

                                    if (isActive) {
                                        btn.setAttribute('aria-current', 'true');
                                    } else {
                                        btn.removeAttribute('aria-current');
                                    }
                                });
                            });
                        });
                    });
                });
            </script>
        <?php endif; ?>

    </article>
</main>

<?php
/**
 * Output flexible content sections managed via ACF outside the main product container.
 */
$flexible_source = $product_id;

if ( function_exists( 'have_rows' ) && have_rows( 'body', $flexible_source ) ) :
    ?>
    <section class="product-flexible-content py-5">
        <article class="blog-post">
            <?php
            while ( have_rows( 'body', $flexible_source ) ) :
                the_row();
                include get_theme_file_path( '/flixable.php' );
            endwhile;
            ?>
        </article>
    </section>
<?php
endif;

/**
 * Hook: woocommerce_after_single_product.
 */
do_action( 'woocommerce_after_single_product' );
