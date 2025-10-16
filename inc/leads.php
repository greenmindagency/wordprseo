<?php
/**
 * Lead tracking integration for Contact Form 7 submissions.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'Theme_Leads_Manager' ) ) {
    class Theme_Leads_Manager {

        /**
         * Bootstraps hooks.
         */
        public function __construct() {
            add_action( 'after_setup_theme', array( $this, 'init' ) );
        }

        /**
         * Initialise actions/filters when Contact Form 7 is available.
         */
        public function init() {
            if ( ! defined( 'WPCF7_VERSION' ) ) {
                return;
            }

            add_action( 'wpcf7_before_send_mail', array( $this, 'capture_submission' ), 10, 3 );

            if ( is_admin() ) {
                add_action( 'admin_menu', array( $this, 'register_menu' ) );
                add_action( 'admin_post_theme_leads_update', array( $this, 'handle_status_update' ) );
            }
        }

        /**
         * Capture Contact Form 7 submissions before they are mailed.
         *
         * @param WPCF7_ContactForm $contact_form The submitted form.
         */
        public function capture_submission( $contact_form ) {
            $submission = class_exists( 'WPCF7_Submission' ) ? WPCF7_Submission::get_instance() : null;

            if ( ! $submission ) {
                return;
            }

            $posted_data = $submission->get_posted_data();
            $email       = $this->extract_email_from_submission( $posted_data );

            global $wpdb;

            $table = $this->get_table_name( $contact_form );

            $this->maybe_create_table( $table );

            $wpdb->insert(
                $table,
                array(
                    'submitted_at'  => current_time( 'mysql' ),
                    'status'        => 'new',
                    'email'         => $email,
                    'payload'       => wp_json_encode( $posted_data ),
                    'form_title'    => $contact_form->title(),
                ),
                array( '%s', '%s', '%s', '%s', '%s' )
            );
        }

        /**
         * Extract email from a submission payload.
         *
         * @param array $posted_data Form submission data.
         * @return string
         */
        protected function extract_email_from_submission( $posted_data ) {
            if ( empty( $posted_data ) || ! is_array( $posted_data ) ) {
                return '';
            }

            foreach ( $posted_data as $value ) {
                if ( is_string( $value ) ) {
                    $filtered = sanitize_email( $value );
                    if ( ! empty( $filtered ) && is_email( $filtered ) ) {
                        return $filtered;
                    }
                }
            }

            return '';
        }

        /**
         * Register the leads management page in the admin area.
         */
        public function register_menu() {
            add_menu_page(
                __( 'Leads', 'wordprseo' ),
                __( 'Leads', 'wordprseo' ),
                'manage_options',
                'theme-leads',
                array( $this, 'render_admin_page' ),
                'dashicons-id-alt',
                58
            );
        }

        /**
         * Handle admin form submission for status updates and replies.
         */
        public function handle_status_update() {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'You do not have permission to access this page.', 'wordprseo' ) );
            }

            check_admin_referer( 'theme_leads_update' );

            $form_slug  = isset( $_POST['form_slug'] ) ? sanitize_key( wp_unslash( $_POST['form_slug'] ) ) : '';
            $lead_id    = isset( $_POST['lead_id'] ) ? absint( $_POST['lead_id'] ) : 0;
            $status     = isset( $_POST['lead_status'] ) ? sanitize_text_field( wp_unslash( $_POST['lead_status'] ) ) : 'new';
            $note       = isset( $_POST['lead_note'] ) ? wp_kses_post( wp_unslash( $_POST['lead_note'] ) ) : '';
            $reply_body = isset( $_POST['lead_reply'] ) ? wp_kses_post( wp_unslash( $_POST['lead_reply'] ) ) : '';
            $reply_subj = isset( $_POST['lead_reply_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['lead_reply_subject'] ) ) : '';

            if ( empty( $form_slug ) || ! $lead_id ) {
                wp_safe_redirect( admin_url( 'admin.php?page=theme-leads' ) );
                exit;
            }

            global $wpdb;

            $table = $this->get_table_name_from_slug( $form_slug );

            if ( ! $this->table_exists( $table ) ) {
                wp_safe_redirect( admin_url( 'admin.php?page=theme-leads' ) );
                exit;
            }

            $data = array(
                'status' => $status,
                'note'   => $note,
            );
            $format = array( '%s', '%s' );

            if ( ! empty( $reply_body ) ) {
                $lead = $wpdb->get_row( $wpdb->prepare( "SELECT email FROM {$table} WHERE id = %d", $lead_id ) );

                if ( $lead && ! empty( $lead->email ) && is_email( $lead->email ) ) {
                    $headers = array( 'Content-Type: text/html; charset=UTF-8' );
                    $sent    = wp_mail( $lead->email, $reply_subj ? $reply_subj : __( 'Response to your enquiry', 'wordprseo' ), wpautop( $reply_body ), $headers );

                    if ( $sent ) {
                        $data['response']      = $reply_body;
                        $data['response_sent'] = current_time( 'mysql' );
                        $format[]              = '%s';
                        $format[]              = '%s';
                    }
                }
            }

            $wpdb->update(
                $table,
                $data,
                array( 'id' => $lead_id ),
                $format,
                array( '%d' )
            );

            wp_safe_redirect( admin_url( 'admin.php?page=theme-leads&form=' . $form_slug ) );
            exit;
        }

        /**
         * Render the admin leads management page.
         */
        public function render_admin_page() {
            $forms = $this->get_contact_forms();
            $form_slug = isset( $_GET['form'] ) ? sanitize_key( wp_unslash( $_GET['form'] ) ) : '';

            if ( empty( $form_slug ) && ! empty( $forms ) ) {
                $first_form = reset( $forms );
                $form_slug  = $this->get_form_slug( $first_form );
            }

            echo '<div class="wrap">';
            echo '<h1>' . esc_html__( 'Leads', 'wordprseo' ) . '</h1>';

            if ( empty( $forms ) ) {
                echo '<p>' . esc_html__( 'No Contact Form 7 forms were found.', 'wordprseo' ) . '</p>';
                echo '</div>';
                return;
            }

            echo '<form method="get" action="">';
            echo '<input type="hidden" name="page" value="theme-leads" />';
            echo '<label for="theme-leads-form">' . esc_html__( 'Select form:', 'wordprseo' ) . '</label> ';
            echo '<select name="form" id="theme-leads-form" onchange="this.form.submit()">';

            foreach ( $forms as $form ) {
                $slug     = $this->get_form_slug( $form );
                $selected = selected( $form_slug, $slug, false );
                printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $slug ), $selected, esc_html( $form->title() ) );
            }

            echo '</select>';
            echo '</form>';

            if ( empty( $form_slug ) ) {
                echo '<p>' . esc_html__( 'Please choose a form to view leads.', 'wordprseo' ) . '</p>';
                echo '</div>';
                return;
            }

            $table = $this->get_table_name_from_slug( $form_slug );

            if ( ! $this->table_exists( $table ) ) {
                echo '<p>' . esc_html__( 'No leads have been captured for the selected form yet.', 'wordprseo' ) . '</p>';
                echo '</div>';
                return;
            }

            global $wpdb;

            $leads = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY submitted_at DESC" );

            if ( empty( $leads ) ) {
                echo '<p>' . esc_html__( 'No leads found for this form.', 'wordprseo' ) . '</p>';
                echo '</div>';
                return;
            }

            echo '<table class="widefat fixed striped">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__( 'Submitted', 'wordprseo' ) . '</th>';
            echo '<th>' . esc_html__( 'Email', 'wordprseo' ) . '</th>';
            echo '<th>' . esc_html__( 'Status', 'wordprseo' ) . '</th>';
            echo '<th>' . esc_html__( 'Details', 'wordprseo' ) . '</th>';
            echo '<th>' . esc_html__( 'Actions', 'wordprseo' ) . '</th>';
            echo '</tr></thead><tbody>';

            foreach ( $leads as $lead ) {
                $payload = json_decode( $lead->payload, true );
                echo '<tr>';
                echo '<td>' . esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $lead->submitted_at ) ) . '</td>';
                echo '<td>' . esc_html( $lead->email );
                if ( ! empty( $lead->response_sent ) ) {
                    echo '<br /><small>' . esc_html__( 'Last response sent:', 'wordprseo' ) . ' ' . esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $lead->response_sent ) ) . '</small>';
                }
                echo '</td>';
                echo '<td>' . esc_html( ucfirst( $lead->status ) ) . '</td>';
                echo '<td>';

                if ( ! empty( $payload ) && is_array( $payload ) ) {
                    echo '<ul class="theme-leads-payload">';
                    foreach ( $payload as $key => $value ) {
                        if ( is_array( $value ) ) {
                            $value = implode( ', ', $value );
                        }
                        printf( '<li><strong>%1$s:</strong> %2$s</li>', esc_html( $key ), esc_html( $value ) );
                    }
                    echo '</ul>';
                }

                if ( ! empty( $lead->note ) ) {
                    echo '<p><strong>' . esc_html__( 'Notes:', 'wordprseo' ) . '</strong> ' . wp_kses_post( $lead->note ) . '</p>';
                }

                echo '</td>';
                echo '<td>';
                echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
                wp_nonce_field( 'theme_leads_update' );
                echo '<input type="hidden" name="action" value="theme_leads_update" />';
                echo '<input type="hidden" name="form_slug" value="' . esc_attr( $form_slug ) . '" />';
                echo '<input type="hidden" name="lead_id" value="' . absint( $lead->id ) . '" />';
                echo '<p><label>' . esc_html__( 'Status', 'wordprseo' ) . '<br />';
                echo '<select name="lead_status">';
                $statuses = array( 'new', 'in-progress', 'won', 'lost', 'archived' );
                foreach ( $statuses as $status ) {
                    printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $status ), selected( $lead->status, $status, false ), esc_html( ucfirst( $status ) ) );
                }
                echo '</select></label></p>';
                echo '<p><label>' . esc_html__( 'Internal note', 'wordprseo' ) . '<br />';
                echo '<textarea name="lead_note" rows="3" class="large-text">' . esc_textarea( $lead->note ) . '</textarea></label></p>';
                echo '<p><label>' . esc_html__( 'Reply subject', 'wordprseo' ) . '<br />';
                echo '<input type="text" name="lead_reply_subject" class="widefat" /></label></p>';
                echo '<p><label>' . esc_html__( 'Reply message (HTML allowed)', 'wordprseo' ) . '<br />';
                echo '<textarea name="lead_reply" rows="5" class="large-text"></textarea></label></p>';
                echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Update lead', 'wordprseo' ) . '</button></p>';
                echo '</form>';
                echo '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
            echo '</div>';
        }

        /**
         * Retrieve available Contact Form 7 forms using the most reliable API available.
         *
         * @return array
         */
        protected function get_contact_forms() {
            if ( function_exists( 'wpcf7_contact_forms' ) ) {
                $forms = wpcf7_contact_forms();
                if ( ! empty( $forms ) ) {
                    return $forms;
                }
            }

            if ( function_exists( 'wpcf7' ) ) {
                $service = wpcf7();
                if ( $service && method_exists( $service, 'get_contact_forms' ) ) {
                    $forms = $service->get_contact_forms();
                    if ( ! empty( $forms ) ) {
                        return $forms;
                    }
                }
            }

            if ( class_exists( 'WPCF7_ContactForm' ) && method_exists( 'WPCF7_ContactForm', 'find' ) ) {
                $forms = WPCF7_ContactForm::find( array() );
                if ( ! empty( $forms ) ) {
                    return $forms;
                }
            }

            return array();
        }

        /**
         * Get the database table name for a specific form.
         *
         * @param WPCF7_ContactForm $contact_form Contact form instance.
         * @return string
         */
        protected function get_table_name( $contact_form ) {
            return $this->get_table_name_from_slug( $this->get_form_slug( $contact_form ) );
        }

        /**
         * Get the table name from a pre-generated slug.
         *
         * @param string $slug Form slug.
         * @return string
         */
        protected function get_table_name_from_slug( $slug ) {
            global $wpdb;

            return $wpdb->prefix . 'leads_' . $slug;
        }

        /**
         * Generate a stable slug for a form instance.
         *
         * @param WPCF7_ContactForm $contact_form Contact form instance.
         * @return string
         */
        protected function get_form_slug( $contact_form ) {
            if ( method_exists( $contact_form, 'name' ) ) {
                $name = $contact_form->name();
            } else {
                $name = $contact_form->title();
            }

            $slug = sanitize_key( $name );

            if ( empty( $slug ) && method_exists( $contact_form, 'id' ) ) {
                $slug = 'form_' . absint( $contact_form->id() );
            }

            return $slug;
        }

        /**
         * Create the table if it does not already exist.
         *
         * @param string $table Table name.
         */
        protected function maybe_create_table( $table ) {
            global $wpdb;

            if ( $this->table_exists( $table ) ) {
                return;
            }

            $charset_collate = $wpdb->get_charset_collate();

            $sql = "CREATE TABLE {$table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                submitted_at datetime NOT NULL,
                status varchar(50) NOT NULL DEFAULT 'new',
                email varchar(190) DEFAULT '',
                payload longtext,
                form_title varchar(255) DEFAULT '',
                note longtext,
                response longtext,
                response_sent datetime DEFAULT NULL,
                PRIMARY KEY  (id),
                KEY status (status),
                KEY email (email)
            ) {$charset_collate};";

            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta( $sql );
        }

        /**
         * Check whether a table exists in the database.
         *
         * @param string $table Table name.
         * @return bool
         */
        protected function table_exists( $table ) {
            global $wpdb;

            $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

            return $exists === $table;
        }
    }

    new Theme_Leads_Manager();
}
