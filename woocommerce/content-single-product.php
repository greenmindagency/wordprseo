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
$carousel_id  = 'productCarousel-' . $product_id;

$tabs = apply_filters( 'woocommerce_product_tabs', array() );

$attributes             = array();
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

    foreach ( $product->get_attributes() as $attribute ) {
        if ( $attribute->is_taxonomy() ) {
            $values = wc_get_product_terms(
                $product_id,
                $attribute->get_name(),
                array(
                    'fields' => 'names',
                )
            );

            if ( is_wp_error( $values ) ) {
                continue;
            }
        } else {
            $values = $attribute->get_options();
        }

        $values = array_filter( array_map( 'wp_strip_all_tags', (array) $values ) );

        if ( empty( $values ) ) {
            continue;
        }

        $attributes[] = array(
            'label' => wc_attribute_label( $attribute->get_name() ),
            'value' => implode( ', ', $values ),
        );
    }
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

<main id="primary" class="site-main">
    <article id="product-<?php the_ID(); ?>" <?php wc_product_class( 'single-product', get_the_ID() ); ?>>
        <div class="container my-5">
            <section class="row g-5">
                <div class="col-md-6">
                    <div class="d-flex flex-column">
                        <div
                            id="<?php echo esc_attr( $carousel_id ); ?>"
                            class="carousel slide shadow-sm overflow-hidden"
                            data-bs-ride="carousel"
                            data-bs-interval="5000"
                        >
                            <div class="carousel-inner">
                                <?php if ( ! empty( $carousel_ids ) ) : ?>
                                    <?php foreach ( $carousel_ids as $index => $image_id ) : ?>
                                        <?php
                                        $image_html = wp_get_attachment_image(
                                            $image_id,
                                            'large',
                                            false,
                                            array(
                                                'class' => 'd-block w-100 object-fit-contain bg-white',
                                                'style' => 'min-height:400px;',
                                            )
                                        );

                                        if ( ! $image_html ) {
                                            $image_html = sprintf(
                                                '<img src="%1$s" alt="%2$s" class="d-block w-100 object-fit-contain bg-white" style="min-height:400px;" />',
                                                esc_url( wc_placeholder_img_src( 'woocommerce_single' ) ),
                                                esc_attr( get_the_title( $product_id ) )
                                            );
                                        }
                                        ?>
                                        <div class="carousel-item<?php echo 0 === $index ? ' active' : ''; ?>">
                                            <div class="bg-light d-flex align-items-center justify-content-center border p-5 position-relative" style="min-height: 400px;">
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
                                        <div class="bg-light d-flex align-items-center justify-content-center border p-5 position-relative" style="min-height: 400px;">
                                            <?php if ( $sale_flash_html ) : ?>
                                                <div class="position-absolute top-0 start-0 m-3">
                                                    <?php echo $sale_flash_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                                </div>
                                            <?php endif; ?>
                                            <img
                                                src="<?php echo esc_url( wc_placeholder_img_src( 'woocommerce_single' ) ); ?>"
                                                alt="<?php echo esc_attr( get_the_title( $product_id ) ); ?>"
                                                class="d-block w-100 object-fit-contain bg-white"
                                                style="min-height:400px;"
                                            >
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if ( count( $carousel_ids ) > 1 ) : ?>
                                <button class="carousel-control-prev" type="button" data-bs-target="#<?php echo esc_attr( $carousel_id ); ?>" data-bs-slide="prev">
                                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                    <span class="visually-hidden"><?php esc_html_e( 'Previous', 'woocommerce' ); ?></span>
                                </button>
                                <button class="carousel-control-next" type="button" data-bs-target="#<?php echo esc_attr( $carousel_id ); ?>" data-bs-slide="next">
                                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                    <span class="visually-hidden"><?php esc_html_e( 'Next', 'woocommerce' ); ?></span>
                                </button>
                            <?php endif; ?>
                        </div>

                        <?php if ( count( $carousel_ids ) > 1 ) : ?>
                            <div class="d-flex justify-content-center mt-3">
                                <?php foreach ( $carousel_ids as $index => $image_id ) : ?>
                                    <button
                                        type="button"
                                        data-bs-target="#<?php echo esc_attr( $carousel_id ); ?>"
                                        data-bs-slide-to="<?php echo esc_attr( $index ); ?>"
                                        class="image-indicator-btn mx-1 btn btn-outline-dark p-0<?php echo 0 === $index ? ' active' : ''; ?>"
                                        aria-label="<?php echo esc_attr( sprintf( __( 'View image %d', 'woocommerce' ), $index + 1 ) ); ?>"
                                        <?php if ( 0 === $index ) : ?>aria-current="true"<?php endif; ?>
                                        style="width: 12px; height: 12px;"
                                    ></button>
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

                    <?php if ( ! empty( $attributes ) ) : ?>
                        <ul class="list-group list-group-flush mb-4 border">
                            <?php foreach ( $attributes as $attribute ) : ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span class="fw-medium"><?php echo esc_html( $attribute['label'] ); ?>:</span>
                                    <span><?php echo esc_html( $attribute['value'] ); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <div class="mb-4 p-3 bg-light shadow-sm">
                        <?php woocommerce_template_single_add_to_cart(); ?>
                    </div>

                    <div class="product-meta small text-muted">
                        <?php woocommerce_template_single_meta(); ?>
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

            <?php if ( ! empty( $tabs ) ) : ?>
                <?php
                $tab_index = 0;
                ?>
                <div class="mt-5">
                    <ul class="nav nav-tabs" id="productTabs" role="tablist">
                        <?php foreach ( $tabs as $key => $tab ) : ?>
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
                        foreach ( $tabs as $key => $tab ) :
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

            <?php if ( ! empty( $related_products ) ) : ?>
                <hr class="my-5">
                <section>
                    <h2 class="mb-4 text-center text-md-start"><?php esc_html_e( 'Explore Related Products', 'woocommerce' ); ?></h2>
                    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 g-4">
                        <?php foreach ( $related_products as $related_product ) : ?>
                            <?php
                            $related_id    = $related_product->get_id();
                            $related_title = $related_product->get_name();
                            $related_link  = get_permalink( $related_id );
                            $related_price = $related_product->get_price_html();
                            $related_image = $related_product->get_image( 'woocommerce_thumbnail', array( 'class' => 'img-fluid' ) );

                            if ( ! $related_image ) {
                                $related_image = sprintf(
                                    '<img src="%1$s" alt="%2$s" class="img-fluid" />',
                                    esc_url( wc_placeholder_img_src( 'woocommerce_thumbnail' ) ),
                                    esc_attr( $related_title )
                                );
                            }
                            ?>
                            <div class="col">
                                <div class="card h-100 shadow-sm">
                                    <a href="<?php echo esc_url( $related_link ); ?>" class="bg-light d-flex align-items-center justify-content-center p-4 border-bottom" style="height: 200px;">
                                        <?php echo $related_image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                    </a>
                                    <div class="card-body d-flex flex-column">
                                        <h5 class="card-title fw-bold">
                                            <a href="<?php echo esc_url( $related_link ); ?>" class="text-decoration-none text-dark">
                                                <?php echo esc_html( $related_title ); ?>
                                            </a>
                                        </h5>
                                        <?php if ( ! empty( $related_price ) ) : ?>
                                            <p class="card-text text-success fw-bold flex-grow-1"><?php echo wp_kses_post( $related_price ); ?></p>
                                        <?php else : ?>
                                            <div class="flex-grow-1"></div>
                                        <?php endif; ?>
                                        <a href="<?php echo esc_url( $related_link ); ?>" class="btn btn-sm btn-outline-primary mt-auto">
                                            <?php esc_html_e( 'View Details', 'woocommerce' ); ?>
                                        </a>
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

        <?php
        $flexible_source = $product_id;

        if ( function_exists( 'have_rows' ) && have_rows( 'body', $flexible_source ) ) :
            ?>
            <section class="product-flexible-content py-5">
                <div class="container">
                    <article class="blog-post">
                        <?php
                        while ( have_rows( 'body', $flexible_source ) ) :
                            the_row();
                            include get_theme_file_path( '/flixable.php' );
                        endwhile;
                        ?>
                    </article>
                </div>
            </section>
        <?php endif; ?>
    </article>
</main>

<?php
/**
 * Hook: woocommerce_after_single_product.
 */
do_action( 'woocommerce_after_single_product' );
