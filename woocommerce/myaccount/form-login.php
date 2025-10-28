<?php
/**
 * Login form for the My Account page.
 *
 * @package WordPrSEO
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_customer_login_form' );

$registration_setting = get_option( 'woocommerce_enable_myaccount_registration' );
$registration_enabled = function_exists( 'wc_string_to_bool' )
    ? wc_string_to_bool( $registration_setting )
    : ( 'yes' === $registration_setting );
?>
<div class="container py-4 py-spacer-2">
    <div class="row justify-content-center">
        <div class="col-lg-10 col-xl-8">
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4">
                            <h2 class="h4 mb-4"><?php esc_html_e( 'Login', 'woocommerce' ); ?></h2>
                            <form class="woocommerce-form woocommerce-form-login login" method="post">
                                <?php do_action( 'woocommerce_login_form_start' ); ?>

                                <div class="mb-3">
                                    <label class="form-label" for="username">
                                        <?php esc_html_e( 'Username or email address', 'woocommerce' ); ?>
                                        <span class="text-danger">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        class="woocommerce-Input woocommerce-Input--text input-text form-control"
                                        name="username"
                                        id="username"
                                        autocomplete="username"
                                        value="<?php echo ( ! empty( $_POST['username'] ) ) ? esc_attr( wp_unslash( $_POST['username'] ) ) : ''; ?>"
                                    >
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="password">
                                        <?php esc_html_e( 'Password', 'woocommerce' ); ?>
                                        <span class="text-danger">*</span>
                                    </label>
                                    <input
                                        class="woocommerce-Input woocommerce-Input--text input-text form-control"
                                        type="password"
                                        name="password"
                                        id="password"
                                        autocomplete="current-password"
                                    >
                                </div>

                                <?php do_action( 'woocommerce_login_form' ); ?>

                                <div class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center justify-content-between gap-3 mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" name="rememberme" type="checkbox" id="rememberme" value="forever" <?php checked( ! empty( $_POST['rememberme'] ) ); ?>>
                                        <label class="form-check-label" for="rememberme"><?php esc_html_e( 'Remember me', 'woocommerce' ); ?></label>
                                    </div>

                                    <p class="woocommerce-LostPassword lost_password mb-0">
                                        <a class="text-decoration-none" href="<?php echo esc_url( wp_lostpassword_url() ); ?>">
                                            <?php esc_html_e( 'Lost your password?', 'woocommerce' ); ?>
                                        </a>
                                    </p>
                                </div>

                                <?php wp_nonce_field( 'woocommerce-login', 'woocommerce-login-nonce' ); ?>

                                <button type="submit" class="btn btn-primary w-100" name="login" value="<?php esc_attr_e( 'Log in', 'woocommerce' ); ?>">
                                    <?php esc_html_e( 'Log in', 'woocommerce' ); ?>
                                </button>

                                <?php do_action( 'woocommerce_login_form_end' ); ?>
                            </form>
                        </div>
                    </div>
                </div>

                <?php if ( $registration_enabled ) : ?>
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body p-4">
                                <h2 class="h4 mb-4"><?php esc_html_e( 'Register', 'woocommerce' ); ?></h2>
                                <form method="post" class="woocommerce-form woocommerce-form-register register">
                                    <?php do_action( 'woocommerce_register_form_start' ); ?>

                                    <?php if ( 'no' === get_option( 'woocommerce_registration_generate_username' ) ) : ?>
                                        <div class="mb-3">
                                            <label class="form-label" for="reg_username">
                                                <?php esc_html_e( 'Username', 'woocommerce' ); ?>
                                                <span class="text-danger">*</span>
                                            </label>
                                            <input
                                                type="text"
                                                class="woocommerce-Input woocommerce-Input--text input-text form-control"
                                                name="username"
                                                id="reg_username"
                                                autocomplete="username"
                                                value="<?php echo ( ! empty( $_POST['username'] ) ) ? esc_attr( wp_unslash( $_POST['username'] ) ) : ''; ?>"
                                            >
                                        </div>
                                    <?php endif; ?>

                                    <div class="mb-3">
                                        <label class="form-label" for="reg_email">
                                            <?php esc_html_e( 'Email address', 'woocommerce' ); ?>
                                            <span class="text-danger">*</span>
                                        </label>
                                        <input
                                            type="email"
                                            class="woocommerce-Input woocommerce-Input--text input-text form-control"
                                            name="email"
                                            id="reg_email"
                                            autocomplete="email"
                                            value="<?php echo ( ! empty( $_POST['email'] ) ) ? esc_attr( wp_unslash( $_POST['email'] ) ) : ''; ?>"
                                        >
                                    </div>

                                    <?php if ( 'no' === get_option( 'woocommerce_registration_generate_password' ) ) : ?>
                                        <div class="mb-3">
                                            <label class="form-label" for="reg_password">
                                                <?php esc_html_e( 'Password', 'woocommerce' ); ?>
                                                <span class="text-danger">*</span>
                                            </label>
                                            <input
                                                type="password"
                                                class="woocommerce-Input woocommerce-Input--text input-text form-control"
                                                name="password"
                                                id="reg_password"
                                                autocomplete="new-password"
                                            >
                                        </div>
                                    <?php else : ?>
                                        <p class="text-muted small">
                                            <?php esc_html_e( 'A password will be sent to your email address.', 'woocommerce' ); ?>
                                        </p>
                                    <?php endif; ?>

                                    <?php do_action( 'woocommerce_register_form' ); ?>

                                    <p class="woocommerce-FormRow form-row mt-4 mb-0">
                                        <?php wp_nonce_field( 'woocommerce-register', 'woocommerce-register-nonce' ); ?>
                                        <button type="submit" class="btn btn-outline-primary w-100" name="register" value="<?php esc_attr_e( 'Register', 'woocommerce' ); ?>">
                                            <?php esc_html_e( 'Register', 'woocommerce' ); ?>
                                        </button>
                                    </p>

                                    <?php do_action( 'woocommerce_register_form_end' ); ?>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php
do_action( 'woocommerce_after_customer_login_form' );
