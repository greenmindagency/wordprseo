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
                add_action( 'admin_post_theme_leads_delete', array( $this, 'handle_delete' ) );
                add_action( 'admin_post_theme_leads_template_save', array( $this, 'handle_template_save' ) );
                add_action( 'admin_post_theme_leads_template_delete', array( $this, 'handle_template_delete' ) );
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
         * Extract the first email address found within a text string.
         *
         * @param string $text Input text.
         * @return string
         */
        protected function extract_email_from_text( $text ) {
            if ( empty( $text ) || ! is_string( $text ) ) {
                return '';
            }

            $text = wp_strip_all_tags( $text );

            if ( is_email( $text ) ) {
                return sanitize_email( $text );
            }

            if ( preg_match( '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $text, $matches ) ) {
                $match = sanitize_email( $matches[0] );
                if ( ! empty( $match ) ) {
                    return $match;
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

            $form_slug      = isset( $_POST['form_slug'] ) ? sanitize_key( wp_unslash( $_POST['form_slug'] ) ) : '';
            $lead_id        = isset( $_POST['lead_id'] ) ? absint( $_POST['lead_id'] ) : 0;
            $status         = isset( $_POST['lead_status'] ) ? sanitize_text_field( wp_unslash( $_POST['lead_status'] ) ) : 'new';
            $note           = array_key_exists( 'lead_note', $_POST ) ? wp_kses_post( wp_unslash( $_POST['lead_note'] ) ) : null;
            $reply_body     = isset( $_POST['lead_reply'] ) ? wp_kses_post( wp_unslash( $_POST['lead_reply'] ) ) : '';
            $reply_subj     = isset( $_POST['lead_reply_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['lead_reply_subject'] ) ) : '';
            $client_name    = isset( $_POST['lead_client_name'] ) ? sanitize_text_field( wp_unslash( $_POST['lead_client_name'] ) ) : '';
            $client_phone   = isset( $_POST['lead_client_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['lead_client_phone'] ) ) : '';
            $template_key   = isset( $_POST['lead_template'] ) ? sanitize_key( wp_unslash( $_POST['lead_template'] ) ) : '';
            $submit_action  = isset( $_POST['lead_submit_action'] ) ? sanitize_key( wp_unslash( $_POST['lead_submit_action'] ) ) : 'save';
            $raw_emails     = isset( $_POST['lead_client_emails'] ) ? (array) $_POST['lead_client_emails'] : array();
            $recipient_list = array();

            foreach ( $raw_emails as $raw_email ) {
                $sanitised = sanitize_email( wp_unslash( $raw_email ) );
                if ( ! empty( $sanitised ) && is_email( $sanitised ) ) {
                    $recipient_list[] = $sanitised;
                }
            }

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

            $data   = array( 'status' => $status );
            $format = array( '%s' );

            $data['response_client_name'] = $client_name;
            $format[]                     = '%s';

            $data['response_subject'] = $reply_subj;
            $format[]                 = '%s';

            $data['response_phone'] = $client_phone;
            $format[]               = '%s';

            $data['response_template'] = $template_key;
            $format[]                  = '%s';

            $data['response_recipients'] = ! empty( $recipient_list ) ? wp_json_encode( $recipient_list ) : '';
            $format[]                    = '%s';

            if ( null !== $note ) {
                $data['note'] = $note;
                $format[]     = '%s';
            }

            $lead = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $lead_id ) );

            $has_response_field = false;

            if ( $lead ) {
                $payload = $this->normalise_payload( $lead->payload );

                if ( ! empty( $reply_body ) ) {
                    $data['response'] = $reply_body;
                    $format[]         = '%s';
                    $has_response_field = true;
                }

                if ( 'send' === $submit_action ) {
                    $recipient_emails = $recipient_list;

                    if ( empty( $recipient_emails ) && ! empty( $lead->email ) && is_email( $lead->email ) ) {
                        $recipient_emails[] = $lead->email;
                    }

                    if ( ! empty( $recipient_emails ) && ! empty( $reply_body ) ) {
                        $data['response_recipients'] = wp_json_encode( $recipient_emails );

                        $subject = ! empty( $reply_subj ) ? $reply_subj : __( 'Response to your enquiry', 'wordprseo' );
                        $message = $reply_body;

                        if ( ! empty( $template_key ) ) {
                            $template = $this->get_template( $template_key );
                            if ( $template ) {
                                $subject = ! empty( $reply_subj ) ? $reply_subj : $template['subject'];
                                $message = ! empty( $reply_body ) ? $reply_body : $template['body'];
                            }
                        }

                        $prepared_subject = $this->apply_template_placeholders( $subject, $lead, $payload, $client_name, $client_phone, $recipient_emails );
                        $prepared_message = $this->apply_template_placeholders( $message, $lead, $payload, $client_name, $client_phone, $recipient_emails );

                        $contact_form = $this->get_contact_form_by_slug( $form_slug );
                        $sender_email = '';
                        $sender_name  = get_bloginfo( 'name' );

                        if ( $contact_form && method_exists( $contact_form, 'prop' ) ) {
                            $mail_settings = $contact_form->prop( 'mail' );

                            if ( is_array( $mail_settings ) ) {
                                if ( ! empty( $mail_settings['recipient'] ) ) {
                                    $recipient_candidates = preg_split( '/[,;]/', $mail_settings['recipient'] );
                                    if ( is_array( $recipient_candidates ) ) {
                                        foreach ( $recipient_candidates as $candidate ) {
                                            $candidate_email = sanitize_email( trim( $candidate ) );
                                            if ( ! empty( $candidate_email ) ) {
                                                $sender_email = $candidate_email;
                                                break;
                                            }
                                        }
                                    }
                                }

                                if ( empty( $sender_email ) && ! empty( $mail_settings['sender'] ) ) {
                                    $maybe_email = $this->extract_email_from_text( $mail_settings['sender'] );
                                    if ( $maybe_email ) {
                                        $sender_email = $maybe_email;
                                    }

                                    $maybe_name = trim( preg_replace( '/<[^>]*>/', '', $mail_settings['sender'] ) );
                                    if ( ! empty( $maybe_name ) ) {
                                        $sender_name = $maybe_name;
                                    }
                                }
                            }
                        }

                        if ( empty( $sender_email ) ) {
                            $sender_email = get_option( 'admin_email' );
                        }

                        $headers   = array( 'Content-Type: text/html; charset=UTF-8' );
                        $from_line = $sender_name && $sender_email ? sprintf( '%1$s <%2$s>', $sender_name, $sender_email ) : $sender_email;

                        if ( ! empty( $sender_email ) ) {
                            $headers[] = 'From: ' . $from_line;
                            $headers[] = 'Reply-To: ' . $from_line;
                        }

                        if ( ! empty( $recipient_emails ) ) {
                            $to = array_shift( $recipient_emails );

                            foreach ( $recipient_emails as $cc_email ) {
                                $headers[] = 'Cc: ' . $cc_email;
                            }

                            $sent = wp_mail( $to, $prepared_subject, wpautop( $prepared_message ), $headers );

                            if ( $sent ) {
                                $data['response']      = $prepared_message;
                                $data['response_subject'] = $prepared_subject;
                                $data['response_sent'] = current_time( 'mysql' );
                                if ( ! $has_response_field ) {
                                    $format[]        = '%s';
                                    $has_response_field = true;
                                }
                                $format[]              = '%s';
                            }
                        }
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
         * Handle admin deletion of a lead entry.
         */
        public function handle_delete() {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'You do not have permission to access this page.', 'wordprseo' ) );
            }

            check_admin_referer( 'theme_leads_delete' );

            $form_slug = isset( $_POST['form_slug'] ) ? sanitize_key( wp_unslash( $_POST['form_slug'] ) ) : '';
            $lead_id   = isset( $_POST['lead_id'] ) ? absint( $_POST['lead_id'] ) : 0;

            if ( empty( $form_slug ) || ! $lead_id ) {
                wp_safe_redirect( admin_url( 'admin.php?page=theme-leads' ) );
                exit;
            }

            global $wpdb;

            $table = $this->get_table_name_from_slug( $form_slug );

            if ( $this->table_exists( $table ) ) {
                $wpdb->delete( $table, array( 'id' => $lead_id ), array( '%d' ) );
            }

            wp_safe_redirect( admin_url( 'admin.php?page=theme-leads&form=' . $form_slug ) );
            exit;
        }

        /**
         * Handle saving (creating/updating) a response template.
         */
        public function handle_template_save() {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'You do not have permission to access this page.', 'wordprseo' ) );
            }

            check_admin_referer( 'theme_leads_template_save' );

            $template_action = isset( $_POST['template_action'] ) ? sanitize_key( wp_unslash( $_POST['template_action'] ) ) : 'update';
            $label           = isset( $_POST['template_label'] ) ? sanitize_text_field( wp_unslash( $_POST['template_label'] ) ) : '';
            $subject         = isset( $_POST['template_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['template_subject'] ) ) : '';
            $body            = isset( $_POST['template_body'] ) ? wp_kses_post( wp_unslash( $_POST['template_body'] ) ) : '';
            $description     = isset( $_POST['template_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['template_description'] ) ) : '';
            $slug            = isset( $_POST['template_slug'] ) ? sanitize_key( wp_unslash( $_POST['template_slug'] ) ) : '';

            if ( empty( $label ) || empty( $subject ) || empty( $body ) ) {
                wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=theme-leads' ) );
                exit;
            }

            $templates = $this->get_templates();

            if ( 'create' === $template_action ) {
                if ( empty( $slug ) ) {
                    $slug = sanitize_key( $label );
                }

                if ( empty( $slug ) ) {
                    $slug = sanitize_key( uniqid( 'template_', true ) );
                }

                while ( isset( $templates[ $slug ] ) ) {
                    $slug = sanitize_key( $slug . '_' . wp_rand( 1, 9999 ) );
                }
            }

            if ( empty( $slug ) ) {
                $slug = sanitize_key( uniqid( 'template_', true ) );
            }

            $templates[ $slug ] = array(
                'label'       => $label,
                'subject'     => $subject,
                'body'        => $body,
                'description' => $description,
            );

            $this->save_templates( $templates );

            $redirect = wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=theme-leads' );
            wp_safe_redirect( $redirect );
            exit;
        }

        /**
         * Handle deleting a response template.
         */
        public function handle_template_delete() {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'You do not have permission to access this page.', 'wordprseo' ) );
            }

            check_admin_referer( 'theme_leads_template_delete' );

            $slug = isset( $_POST['template_slug'] ) ? sanitize_key( wp_unslash( $_POST['template_slug'] ) ) : '';

            if ( empty( $slug ) ) {
                wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=theme-leads' ) );
                exit;
            }

            $templates = $this->get_templates();

            if ( isset( $templates[ $slug ] ) ) {
                unset( $templates[ $slug ] );
                $this->save_templates( $templates );
            }

            $redirect = wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=theme-leads' );
            wp_safe_redirect( $redirect );
            exit;
        }

        /**
         * Render the admin leads management page.
         */
        public function render_admin_page() {
            $forms      = $this->get_contact_forms();
            $templates  = $this->get_templates();
            $form_slug  = isset( $_GET['form'] ) ? sanitize_key( wp_unslash( $_GET['form'] ) ) : '';

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

            echo '<div class="theme-leads-toolbar">';
            echo '<form method="get" action="" class="theme-leads-form-selector">';
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

            echo '<button type="button" class="button theme-leads-template-toggle" aria-expanded="false">';
            echo '<span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>';
            echo '<span class="screen-reader-text">' . esc_html__( 'Manage templates', 'wordprseo' ) . '</span>';
            echo '</button>';
            echo '</div>';

            echo '<div class="theme-leads-template-panel" aria-hidden="true">';
            echo '<div class="theme-leads-template-panel-inner">';
            echo '<button type="button" class="button-link theme-leads-template-close" aria-label="' . esc_attr__( 'Close template manager', 'wordprseo' ) . '"><span class="dashicons dashicons-no" aria-hidden="true"></span></button>';
            echo '<h2>' . esc_html__( 'Response templates', 'wordprseo' ) . '</h2>';
            echo '<p>' . esc_html__( 'Use placeholders like %name%, %email%, %phone%, %date%, and %form_title% to personalise messages automatically.', 'wordprseo' ) . '</p>';

            if ( ! empty( $templates ) ) {
                echo '<div class="theme-leads-template-list">';
                foreach ( $templates as $slug => $template ) {
                    $label       = isset( $template['label'] ) ? $template['label'] : $slug;
                    $subject_tpl = isset( $template['subject'] ) ? $template['subject'] : '';
                    $body_tpl    = isset( $template['body'] ) ? $template['body'] : '';
                    $desc        = isset( $template['description'] ) ? $template['description'] : '';

                    echo '<div class="theme-leads-template-card">';
                    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
                    wp_nonce_field( 'theme_leads_template_save' );
                    echo '<input type="hidden" name="action" value="theme_leads_template_save" />';
                    echo '<input type="hidden" name="template_action" value="update" />';
                    echo '<input type="hidden" name="template_slug" value="' . esc_attr( $slug ) . '" />';
                    echo '<p><label>' . esc_html__( 'Template name', 'wordprseo' ) . '<br />';
                    echo '<input type="text" name="template_label" class="widefat" value="' . esc_attr( $label ) . '" required /></label></p>';
                    echo '<p><label>' . esc_html__( 'Description', 'wordprseo' ) . '<br />';
                    echo '<textarea name="template_description" class="widefat" rows="2">' . esc_textarea( $desc ) . '</textarea></label></p>';
                    echo '<p><label>' . esc_html__( 'Subject template', 'wordprseo' ) . '<br />';
                    echo '<input type="text" name="template_subject" class="widefat" value="' . esc_attr( $subject_tpl ) . '" required /></label></p>';
                    echo '<p><label>' . esc_html__( 'Message template', 'wordprseo' ) . '<br />';
                    echo '<textarea name="template_body" class="widefat" rows="5" required>' . esc_textarea( $body_tpl ) . '</textarea></label></p>';
                    echo '<p class="theme-leads-template-actions">';
                    echo '<button type="submit" class="button button-primary">' . esc_html__( 'Save template', 'wordprseo' ) . '</button>';
                    echo '</p>';
                    echo '</form>';

                    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="theme-leads-template-delete-form">';
                    wp_nonce_field( 'theme_leads_template_delete' );
                    echo '<input type="hidden" name="action" value="theme_leads_template_delete" />';
                    echo '<input type="hidden" name="template_slug" value="' . esc_attr( $slug ) . '" />';
                    echo '<button type="submit" class="button-link" onclick="return confirm(\'' . esc_js( __( 'Delete this template?', 'wordprseo' ) ) . '\');">' . esc_html__( 'Delete template', 'wordprseo' ) . '</button>';
                    echo '</form>';
                    echo '</div>';
                }
                echo '</div>';
            }

            echo '<div class="theme-leads-template-card theme-leads-template-new">';
            echo '<h3>' . esc_html__( 'Add new template', 'wordprseo' ) . '</h3>';
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
            wp_nonce_field( 'theme_leads_template_save' );
            echo '<input type="hidden" name="action" value="theme_leads_template_save" />';
            echo '<input type="hidden" name="template_action" value="create" />';
            echo '<p><label>' . esc_html__( 'Template name', 'wordprseo' ) . '<br />';
            echo '<input type="text" name="template_label" class="widefat" required /></label></p>';
            echo '<p><label>' . esc_html__( 'Custom key (optional)', 'wordprseo' ) . '<br />';
            echo '<input type="text" name="template_slug" class="widefat" /></label></p>';
            echo '<p><label>' . esc_html__( 'Description', 'wordprseo' ) . '<br />';
            echo '<textarea name="template_description" class="widefat" rows="2"></textarea></label></p>';
            echo '<p><label>' . esc_html__( 'Subject template', 'wordprseo' ) . '<br />';
            echo '<input type="text" name="template_subject" class="widefat" required /></label></p>';
            echo '<p><label>' . esc_html__( 'Message template', 'wordprseo' ) . '<br />';
            echo '<textarea name="template_body" class="widefat" rows="5" required></textarea></label></p>';
            echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Create template', 'wordprseo' ) . '</button></p>';
            echo '</form>';
            echo '</div>';

            echo '</div>';
            echo '</div>';

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

            echo '<table class="widefat fixed striped theme-leads-table">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__( 'Submitted', 'wordprseo' ) . '</th>';
            echo '<th>' . esc_html__( 'Name', 'wordprseo' ) . '</th>';
            echo '<th>' . esc_html__( 'Email', 'wordprseo' ) . '</th>';
            echo '<th>' . esc_html__( 'Phone', 'wordprseo' ) . '</th>';
            echo '<th>' . esc_html__( 'Status', 'wordprseo' ) . '</th>';
            echo '<th>' . esc_html__( 'Update status', 'wordprseo' ) . '</th>';
            echo '<th>' . esc_html__( 'Actions', 'wordprseo' ) . '</th>';
            echo '</tr></thead><tbody>';

            foreach ( $leads as $lead ) {
                $payload      = $this->normalise_payload( $lead->payload );
                $lead_name    = $this->extract_contact_name( $payload );
                $lead_phone   = $this->extract_phone_from_payload( $payload );
                $formatted_at = mysql2date( 'd/m/Y - g:iA', $lead->submitted_at );
                $detail_id    = 'theme-lead-details-' . absint( $lead->id );
                $client_name_value = ! empty( $lead->response_client_name ) ? $lead->response_client_name : ( $lead_name ? $lead_name : '' );
                $client_phone_value = ! empty( $lead->response_phone ) ? $lead->response_phone : ( $lead_phone ? $lead_phone : '' );
                $stored_recipients = array();

                if ( ! empty( $lead->response_recipients ) ) {
                    $decoded = json_decode( $lead->response_recipients, true );
                    if ( is_array( $decoded ) ) {
                        foreach ( $decoded as $recipient_email ) {
                            $sanitised_email = sanitize_email( $recipient_email );
                            if ( ! empty( $sanitised_email ) ) {
                                $stored_recipients[] = $sanitised_email;
                            }
                        }
                    }
                }

                if ( empty( $stored_recipients ) && ! empty( $lead->email ) ) {
                    $stored_recipients[] = $lead->email;
                }

                $template_context_attrs = array(
                    'data-name'       => esc_attr( $client_name_value ? $client_name_value : $lead_name ),
                    'data-email'      => esc_attr( $lead->email ),
                    'data-phone'      => esc_attr( $lead_phone ? $lead_phone : $client_phone_value ),
                    'data-status'     => esc_attr( $lead->status ),
                    'data-date'       => esc_attr( mysql2date( 'Y-m-d\TH:i:sP', $lead->submitted_at ) ),
                    'data-date-short' => esc_attr( $formatted_at ),
                    'data-form-title' => esc_attr( $lead->form_title ),
                );

                echo '<tr class="theme-leads-summary" data-lead="' . absint( $lead->id ) . '">';
                echo '<td class="theme-leads-summary-submitted">';
                echo '<button type="button" class="theme-leads-toggle-button" aria-expanded="false" aria-controls="' . esc_attr( $detail_id ) . '"><span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__( 'Toggle lead details', 'wordprseo' ) . '</span></button>';
                echo '<span class="theme-leads-submitted-text">' . esc_html( $formatted_at ) . '</span>';
                echo '</td>';

                echo '<td class="theme-leads-summary-name">';
                echo '<span class="theme-leads-name-text">' . esc_html( $lead_name ? $lead_name : __( 'Unknown', 'wordprseo' ) ) . '</span>';
                echo '</td>';

                echo '<td>';
                if ( ! empty( $lead->email ) ) {
                    echo '<a href="mailto:' . esc_attr( $lead->email ) . '">' . esc_html( $lead->email ) . '</a>';
                } else {
                    echo '&mdash;';
                }

                if ( ! empty( $lead->response_sent ) ) {
                    echo '<div class="theme-leads-meta"><small>' . esc_html__( 'Last response', 'wordprseo' ) . ': ' . esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $lead->response_sent ) ) . '</small></div>';
                }
                echo '</td>';

                echo '<td>';
                if ( ! empty( $lead_phone ) ) {
                    $tel_href = preg_replace( '/[^0-9\+]/', '', $lead_phone );
                    echo '<a href="tel:' . esc_attr( $tel_href ? $tel_href : $lead_phone ) . '">' . esc_html( $lead_phone ) . '</a>';
                } else {
                    echo '&mdash;';
                }
                echo '</td>';

                echo '<td>' . esc_html( ucfirst( $lead->status ) ) . '</td>';

                echo '<td>';
                echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="theme-leads-status-form theme-leads-no-toggle">';
                wp_nonce_field( 'theme_leads_update' );
                echo '<input type="hidden" name="action" value="theme_leads_update" />';
                echo '<input type="hidden" name="form_slug" value="' . esc_attr( $form_slug ) . '" />';
                echo '<input type="hidden" name="lead_id" value="' . absint( $lead->id ) . '" />';
                echo '<label class="screen-reader-text" for="theme-lead-status-' . absint( $lead->id ) . '">' . esc_html__( 'Change status', 'wordprseo' ) . '</label>';
                echo '<select id="theme-lead-status-' . absint( $lead->id ) . '" name="lead_status" class="theme-leads-status-select">';
                $statuses = array( 'new', 'in-progress', 'won', 'lost', 'archived' );
                foreach ( $statuses as $status ) {
                    printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $status ), selected( $lead->status, $status, false ), esc_html( ucfirst( $status ) ) );
                }
                echo '</select>';
                echo '</form>';
                echo '</td>';

                echo '<td class="theme-leads-actions theme-leads-no-toggle">';
                if ( ! empty( $lead->email ) ) {
                    echo '<a class="theme-leads-action" href="mailto:' . esc_attr( $lead->email ) . '" title="' . esc_attr__( 'Send email', 'wordprseo' ) . '"><span class="dashicons dashicons-email-alt" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__( 'Send email', 'wordprseo' ) . '</span></a>';
                }

                if ( ! empty( $lead_phone ) ) {
                    $whatsapp_number = preg_replace( '/[^0-9]/', '', $lead_phone );
                    if ( ! empty( $whatsapp_number ) ) {
                        echo '<a class="theme-leads-action" href="' . esc_url( 'https://wa.me/' . $whatsapp_number ) . '" target="_blank" rel="noopener noreferrer" title="' . esc_attr__( 'Send WhatsApp message', 'wordprseo' ) . '"><span class="dashicons dashicons-format-chat" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__( 'Send WhatsApp message', 'wordprseo' ) . '</span></a>';
                    }
                }

                echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="theme-leads-delete-form theme-leads-no-toggle">';
                wp_nonce_field( 'theme_leads_delete' );
                echo '<input type="hidden" name="action" value="theme_leads_delete" />';
                echo '<input type="hidden" name="form_slug" value="' . esc_attr( $form_slug ) . '" />';
                echo '<input type="hidden" name="lead_id" value="' . absint( $lead->id ) . '" />';
                echo '<button type="submit" class="theme-leads-action theme-leads-delete" onclick="return confirm(\'' . esc_js( __( 'Delete this lead permanently?', 'wordprseo' ) ) . '\');" aria-label="' . esc_attr__( 'Delete lead', 'wordprseo' ) . '"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button>';
                echo '</form>';
                echo '</td>';
                echo '</tr>';

                echo '<tr class="theme-leads-details" id="' . esc_attr( $detail_id ) . '" data-lead="' . absint( $lead->id ) . '" aria-hidden="true">';
                echo '<td colspan="7">';
                echo '<div class="theme-leads-details-inner">';

                $context_attr_string = '';
                foreach ( $template_context_attrs as $attr_key => $attr_value ) {
                    if ( '' !== $attr_value ) {
                        $context_attr_string .= ' ' . $attr_key . '="' . $attr_value . '"';
                    }
                }

                echo '<div class="theme-leads-details-columns">';

                echo '<div class="theme-leads-details-column">';
                echo '<h3>' . esc_html__( 'Submission details', 'wordprseo' ) . '</h3>';

                if ( ! empty( $payload ) ) {
                    echo '<ul class="theme-leads-payload">';
                    foreach ( $payload as $key => $value ) {
                        if ( is_array( $value ) ) {
                            $value = implode( ', ', $value );
                        }
                        printf( '<li><strong>%1$s:</strong> %2$s</li>', esc_html( $key ), esc_html( $value ) );
                    }
                    echo '</ul>';
                } else {
                    echo '<p>' . esc_html__( 'No submission data recorded for this lead.', 'wordprseo' ) . '</p>';
                }

                if ( ! empty( $lead->note ) ) {
                    echo '<div class="theme-leads-note"><strong>' . esc_html__( 'Internal note:', 'wordprseo' ) . '</strong> ' . wp_kses_post( $lead->note ) . '</div>';
                }

                if ( ! empty( $lead->response_sent ) ) {
                    echo '<div class="theme-leads-response-history">';
                    echo '<strong>' . esc_html__( 'Last email sent:', 'wordprseo' ) . '</strong> ' . esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $lead->response_sent ) );

                    if ( ! empty( $lead->response_subject ) ) {
                        echo '<div><strong>' . esc_html__( 'Subject:', 'wordprseo' ) . '</strong> ' . esc_html( $lead->response_subject ) . '</div>';
                    }

                    if ( ! empty( $lead->response_recipients ) ) {
                        $history_recipients = json_decode( $lead->response_recipients, true );
                        if ( is_array( $history_recipients ) ) {
                            $clean_recipients = array();
                            foreach ( $history_recipients as $history_email ) {
                                $clean_email = sanitize_email( $history_email );
                                if ( ! empty( $clean_email ) ) {
                                    $clean_recipients[] = $clean_email;
                                }
                            }
                            if ( ! empty( $clean_recipients ) ) {
                                echo '<div><strong>' . esc_html__( 'Recipients:', 'wordprseo' ) . '</strong> ' . esc_html( implode( ', ', $clean_recipients ) ) . '</div>';
                            }
                        }
                    }

                    if ( ! empty( $lead->response ) ) {
                        echo '<div class="theme-leads-response-preview">' . wp_kses_post( wpautop( $lead->response ) ) . '</div>';
                    }

                    echo '</div>';
                }

                echo '</div>';

                echo '<div class="theme-leads-details-column">';
                echo '<h3>' . esc_html__( 'Update & respond', 'wordprseo' ) . '</h3>';
                echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="theme-leads-detail-form">';
                wp_nonce_field( 'theme_leads_update' );
                echo '<input type="hidden" name="action" value="theme_leads_update" />';
                echo '<input type="hidden" name="form_slug" value="' . esc_attr( $form_slug ) . '" />';
                echo '<input type="hidden" name="lead_id" value="' . absint( $lead->id ) . '" />';
                echo '<div class="theme-leads-template-context"' . $context_attr_string . '></div>';

                echo '<div class="theme-leads-form-group">';
                echo '<label>' . esc_html__( 'Status', 'wordprseo' );
                echo '<select name="lead_status" class="theme-leads-status-select">';
                foreach ( $statuses as $status ) {
                    printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $status ), selected( $lead->status, $status, false ), esc_html( ucfirst( $status ) ) );
                }
                echo '</select>';
                echo '</label>';
                echo '</div>';

                echo '<div class="theme-leads-form-group">';
                echo '<label>' . esc_html__( 'Internal note', 'wordprseo' );
                echo '<textarea name="lead_note" rows="3" class="large-text">' . esc_textarea( $lead->note ) . '</textarea>';
                echo '</label>';
                echo '</div>';

                if ( ! empty( $templates ) ) {
                    echo '<div class="theme-leads-form-group">';
                    echo '<label>' . esc_html__( 'Template', 'wordprseo' );
                    echo '<select name="lead_template" class="theme-leads-template-select">';
                    echo '<option value="">' . esc_html__( 'Select a template', 'wordprseo' ) . '</option>';
                    foreach ( $templates as $slug => $template ) {
                        $label = isset( $template['label'] ) ? $template['label'] : $slug;
                        printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $slug ), selected( $lead->response_template, $slug, false ), esc_html( $label ) );
                    }
                    echo '</select>';
                    echo '</label>';
                    echo '</div>';
                }

                echo '<div class="theme-leads-form-group">';
                echo '<label>' . esc_html__( 'Client name', 'wordprseo' );
                echo '<input type="text" name="lead_client_name" class="widefat" value="' . esc_attr( $client_name_value ) . '" />';
                echo '</label>';
                echo '</div>';

                echo '<div class="theme-leads-form-group theme-leads-email-group">';
                echo '<label>' . esc_html__( 'Client emails', 'wordprseo' );
                echo '<span class="theme-leads-field-help">' . esc_html__( 'Use + to add CC recipients.', 'wordprseo' ) . '</span>';
                echo '<div class="theme-leads-email-fields">';
                if ( empty( $stored_recipients ) ) {
                    $stored_recipients = array( '' );
                }
                foreach ( $stored_recipients as $index => $email_value ) {
                    echo '<div class="theme-leads-email-field">';
                    echo '<input type="email" name="lead_client_emails[]" value="' . esc_attr( $email_value ) . '" class="widefat" />';
                    $remove_hidden = 0 === $index ? ' hidden' : '';
                    echo '<button type="button" class="button-link theme-leads-email-remove" aria-label="' . esc_attr__( 'Remove email', 'wordprseo' ) . '"' . $remove_hidden . '><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>';
                    echo '</div>';
                }
                echo '</div>';
                echo '<button type="button" class="button theme-leads-email-add"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__( 'Add email', 'wordprseo' ) . '</span></button>';
                echo '</label>';
                echo '</div>';

                echo '<div class="theme-leads-form-group">';
                echo '<label>' . esc_html__( 'Phone number', 'wordprseo' );
                echo '<input type="text" name="lead_client_phone" class="widefat" value="' . esc_attr( $client_phone_value ) . '" />';
                echo '</label>';
                echo '</div>';

                echo '<div class="theme-leads-form-group">';
                echo '<label>' . esc_html__( 'Subject', 'wordprseo' );
                echo '<input type="text" name="lead_reply_subject" class="widefat" value="' . esc_attr( $lead->response_subject ) . '" />';
                echo '</label>';
                echo '</div>';

                echo '<div class="theme-leads-form-group">';
                echo '<label>' . esc_html__( 'Message', 'wordprseo' );
                echo '<textarea name="lead_reply" rows="6" class="large-text">' . esc_textarea( $lead->response ) . '</textarea>';
                echo '</label>';
                echo '</div>';

                echo '<div class="theme-leads-form-actions">';
                echo '<button type="submit" name="lead_submit_action" value="save" class="button">' . esc_html__( 'Save changes', 'wordprseo' ) . '</button>';
                echo '<button type="submit" name="lead_submit_action" value="send" class="button button-primary">' . esc_html__( 'Send email', 'wordprseo' ) . '</button>';
                echo '</div>';

                echo '</form>';
                echo '</div>';

                echo '</div>';
                echo '</div>';
                echo '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';

            echo '<style>
                .theme-leads-toolbar { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:16px; }
                .theme-leads-form-selector { display:flex; align-items:center; gap:8px; }
                .theme-leads-form-selector select { min-width:220px; }
                .theme-leads-template-toggle { display:flex; align-items:center; gap:6px; }
                .theme-leads-template-panel { position:fixed; top:64px; right:32px; width:420px; max-width:90vw; max-height:80vh; overflow:auto; background:#fff; border:1px solid #ccd0d4; box-shadow:0 20px 40px rgba(0,0,0,0.2); padding:24px; display:none; z-index:9999; }
                .theme-leads-template-panel[aria-hidden="false"] { display:block; }
                .theme-leads-template-panel-inner { position:relative; display:flex; flex-direction:column; gap:16px; }
                .theme-leads-template-close { position:absolute; top:8px; right:8px; color:#666; }
                .theme-leads-template-list { display:flex; flex-direction:column; gap:16px; }
                .theme-leads-template-card { border:1px solid #e2e4e7; padding:16px; border-radius:4px; background:#f9fafb; }
                .theme-leads-template-card form { margin:0; }
                .theme-leads-template-actions { display:flex; justify-content:flex-end; }
                .theme-leads-template-delete-form { margin-top:8px; }
                .theme-leads-template-new { background:#fff; }
                .theme-leads-table .theme-leads-summary-submitted { display:flex; align-items:center; gap:8px; white-space:nowrap; font-weight:600; }
                .theme-leads-table .theme-leads-summary-name { font-weight:500; }
                .theme-leads-toggle-button { border:0; background:transparent; cursor:pointer; padding:2px; margin-right:4px; }
                .theme-leads-toggle-button:focus { outline:2px solid #2271b1; outline-offset:2px; }
                .theme-leads-summary { cursor:pointer; }
                .theme-leads-summary.is-open .theme-leads-toggle-button .dashicons { transform:rotate(90deg); }
                .theme-leads-submitted-text { display:inline-block; min-width:160px; }
                .theme-leads-details { display:none; background:#f9f9f9; }
                .theme-leads-details.is-open { display:table-row; }
                .theme-leads-details-inner { padding:20px 16px; }
                .theme-leads-details-columns { display:flex; flex-wrap:wrap; gap:24px; }
                .theme-leads-details-column { flex:1 1 320px; background:#fff; border:1px solid #e2e4e7; padding:16px; border-radius:4px; }
                .theme-leads-payload { margin:0 0 16px; padding-left:20px; }
                .theme-leads-note { margin-top:12px; background:#fff8e5; padding:12px; border-left:4px solid #dba617; border-radius:3px; }
                .theme-leads-response-history { margin-top:16px; padding-top:12px; border-top:1px solid #ececec; font-size:13px; display:flex; flex-direction:column; gap:6px; }
                .theme-leads-response-preview { max-height:180px; overflow:auto; background:#f6f7f7; padding:12px; border-radius:3px; }
                .theme-leads-actions { display:flex; align-items:center; gap:8px; }
                .theme-leads-action { display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px; border-radius:50%; border:1px solid #ccd0d4; background:#fff; text-decoration:none; color:inherit; transition:all .2s ease; }
                .theme-leads-action:hover { border-color:#2271b1; color:#2271b1; }
                .theme-leads-delete { background:#fef2f2; border-color:#dc3232; color:#dc3232; }
                .theme-leads-delete:hover { background:#dc3232; color:#fff; }
                .theme-leads-delete-form { margin:0; }
                .theme-leads-status-form { margin:0; }
                .theme-leads-detail-form { display:flex; flex-direction:column; gap:12px; }
                .theme-leads-form-group label { display:flex; flex-direction:column; gap:4px; font-weight:500; }
                .theme-leads-field-help { display:block; font-size:12px; color:#666; margin-bottom:4px; }
                .theme-leads-email-fields { display:flex; flex-direction:column; gap:8px; margin-bottom:8px; }
                .theme-leads-email-field { display:flex; gap:8px; align-items:center; }
                .theme-leads-email-remove { color:#dc3232; }
                .theme-leads-email-remove[hidden] { display:none; }
                .theme-leads-form-actions { display:flex; gap:8px; margin-top:8px; }
                .theme-leads-template-context { display:none; }
            </style>';

            $templates_json = wp_json_encode( $this->prepare_templates_for_js( $templates ) );
            if ( $templates_json ) {
                echo '<script type="application/json" id="theme-leads-templates-data">' . str_replace( '</', '<\/', $templates_json ) . '</script>';
            }

            $remove_email_label_json = wp_json_encode( __( 'Remove email', 'wordprseo' ) );

            $script = <<<JS
<script>
document.addEventListener("DOMContentLoaded", function() {
    const templateDataElement = document.getElementById("theme-leads-templates-data");
    let templates = {};
    if (templateDataElement) {
        try {
            templates = JSON.parse(templateDataElement.textContent);
        } catch (e) {
            templates = {};
        }
    }

    const removeEmailLabel = {$remove_email_label_json};

    const templateToggle = document.querySelector(".theme-leads-template-toggle");
    const templatePanel = document.querySelector(".theme-leads-template-panel");
    const templateClose = document.querySelector(".theme-leads-template-close");

    function setTemplatePanel(open) {
        if (!templatePanel || !templateToggle) {
            return;
        }
        templateToggle.setAttribute("aria-expanded", open ? "true" : "false");
        templatePanel.setAttribute("aria-hidden", open ? "false" : "true");
    }

    if (templateToggle && templatePanel) {
        templateToggle.addEventListener("click", function(event) {
            event.preventDefault();
            const expanded = templateToggle.getAttribute("aria-expanded") === "true";
            setTemplatePanel(!expanded);
        });
    }

    if (templateClose) {
        templateClose.addEventListener("click", function(event) {
            event.preventDefault();
            setTemplatePanel(false);
        });
    }

    document.addEventListener("keydown", function(event) {
        if (event.key === "Escape") {
            setTemplatePanel(false);
        }
    });

    const rows = document.querySelectorAll(".theme-leads-summary");
    rows.forEach(function(row) {
        row.addEventListener("click", function(event) {
            if (event.target.closest(".theme-leads-no-toggle") || event.target.closest("a") || event.target.closest("button")) {
                return;
            }
            const leadId = row.getAttribute("data-lead");
            toggleLeadRow(leadId);
        });
    });

    document.querySelectorAll(".theme-leads-toggle-button").forEach(function(button) {
        button.addEventListener("click", function(event) {
            event.stopPropagation();
            const leadId = button.closest(".theme-leads-summary").getAttribute("data-lead");
            toggleLeadRow(leadId, button);
        });
    });

    document.querySelectorAll(".theme-leads-status-select").forEach(function(select) {
        const form = select.closest("form");
        if (form && form.classList.contains("theme-leads-status-form")) {
            select.addEventListener("change", function() {
                form.submit();
            });
        }
    });

    document.querySelectorAll(".theme-leads-detail-form").forEach(function(form) {
        const emailFields = form.querySelector(".theme-leads-email-fields");
        const addButton = form.querySelector(".theme-leads-email-add");
        const templateSelect = form.querySelector(".theme-leads-template-select");
        const subjectField = form.querySelector("input[name=\"lead_reply_subject\"]");
        const messageField = form.querySelector("textarea[name=\"lead_reply\"]");

        if (emailFields) {
            emailFields.addEventListener("click", function(event) {
                const removeButton = event.target.closest(".theme-leads-email-remove");
                if (removeButton) {
                    event.preventDefault();
                    const field = removeButton.closest(".theme-leads-email-field");
                    if (!field) {
                        return;
                    }
                    if (emailFields.children.length > 1) {
                        field.remove();
                    } else {
                        const input = field.querySelector("input");
                        if (input) {
                            input.value = "";
                        }
                    }
                    refreshEmailRemoveButtons(emailFields);
                }
            });

            refreshEmailRemoveButtons(emailFields);
        }

        if (addButton && emailFields) {
            addButton.addEventListener("click", function(event) {
                event.preventDefault();
                emailFields.appendChild(createEmailField());
                refreshEmailRemoveButtons(emailFields);
            });
        }

        if (templateSelect && subjectField && messageField) {
            templateSelect.addEventListener("change", function() {
                const selected = templateSelect.value;
                if (!selected || !templates[selected]) {
                    return;
                }
                const contextEl = form.querySelector(".theme-leads-template-context");
                const context = buildContext(contextEl, form);
                subjectField.value = applyTemplate(templates[selected].subject, context);
                messageField.value = applyTemplate(templates[selected].body, context);
            });
        }
    });

    function createEmailField(value) {
        const wrapper = document.createElement("div");
        wrapper.className = "theme-leads-email-field";

        const input = document.createElement("input");
        input.type = "email";
        input.name = "lead_client_emails[]";
        input.className = "widefat";
        input.value = value || "";

        const removeButton = document.createElement("button");
        removeButton.type = "button";
        removeButton.className = "button-link theme-leads-email-remove";
        removeButton.setAttribute("aria-label", removeEmailLabel);
        removeButton.innerHTML = "<span class=\"dashicons dashicons-no-alt\" aria-hidden=\"true\"></span>";

        wrapper.appendChild(input);
        wrapper.appendChild(removeButton);

        return wrapper;
    }

    function refreshEmailRemoveButtons(container) {
        const buttons = container.querySelectorAll(".theme-leads-email-remove");
        buttons.forEach(function(button, index) {
            button.hidden = index === 0;
        });
    }

    function buildContext(contextEl, form) {
        const data = {
            name: "",
            email: "",
            phone: "",
            status: "",
            date: "",
            form_title: "",
            date_short: ""
        };

        if (contextEl) {
            data.name = contextEl.dataset.name || "";
            data.email = contextEl.dataset.email || "";
            data.phone = contextEl.dataset.phone || "";
            data.status = contextEl.dataset.status || "";
            data.date = contextEl.dataset.date || "";
            data.form_title = contextEl.dataset.formTitle || "";
            data.date_short = contextEl.dataset.dateShort || "";
        }

        const emailInputs = form.querySelectorAll(".theme-leads-email-field input[name='lead_client_emails[]']");
        if (emailInputs.length) {
            data.email = emailInputs[0].value || data.email;
        }

        const phoneField = form.querySelector("input[name='lead_client_phone']");
        if (phoneField && phoneField.value) {
            data.phone = phoneField.value;
        }

        const nameField = form.querySelector("input[name='lead_client_name']");
        if (nameField && nameField.value) {
            data.name = nameField.value;
        }

        const statusField = form.querySelector("select[name='lead_status']");
        if (statusField && statusField.value) {
            data.status = statusField.value;
        }

        return data;
    }

    function applyTemplate(value, context) {
        if (!value || typeof value !== "string") {
            return value || "";
        }

        return value.replace(/%([a-z0-9_]+)%/gi, function(match, key) {
            const lookup = key.toLowerCase();
            if (Object.prototype.hasOwnProperty.call(context, lookup)) {
                return context[lookup] || "";
            }
            return match;
        });
    }

    function toggleLeadRow(leadId, button) {
        const summary = document.querySelector(".theme-leads-summary[data-lead='" + leadId + "']");
        const detail = document.querySelector(".theme-leads-details[data-lead='" + leadId + "']");
        if (!detail || !summary) {
            return;
        }
        const willOpen = !detail.classList.contains("is-open");
        summary.classList.toggle("is-open", willOpen);
        detail.classList.toggle("is-open", willOpen);
        detail.setAttribute("aria-hidden", willOpen ? "false" : "true");
        const toggleButton = button || summary.querySelector(".theme-leads-toggle-button");
        if (toggleButton) {
            toggleButton.setAttribute("aria-expanded", willOpen ? "true" : "false");
        }
    }
});
</script>
JS;

            echo $script;

            echo '</div>';
        }

        /**
         * Retrieve stored response templates.
         *
         * @return array
         */
        protected function get_templates() {
            $templates = get_option( 'theme_leads_templates', array() );

            if ( empty( $templates ) || ! is_array( $templates ) ) {
                return array();
            }

            foreach ( $templates as $slug => $template ) {
                if ( ! is_array( $template ) ) {
                    unset( $templates[ $slug ] );
                    continue;
                }

                $templates[ $slug ] = array(
                    'label'       => isset( $template['label'] ) ? sanitize_text_field( $template['label'] ) : $slug,
                    'subject'     => isset( $template['subject'] ) ? (string) $template['subject'] : '',
                    'body'        => isset( $template['body'] ) ? (string) $template['body'] : '',
                    'description' => isset( $template['description'] ) ? sanitize_textarea_field( $template['description'] ) : '',
                );
            }

            return $templates;
        }

        /**
         * Persist templates to the database.
         *
         * @param array $templates Templates to store.
         */
        protected function save_templates( $templates ) {
            update_option( 'theme_leads_templates', $templates );
        }

        /**
         * Retrieve a single template by slug.
         *
         * @param string $slug Template slug.
         * @return array|null
         */
        protected function get_template( $slug ) {
            if ( empty( $slug ) ) {
                return null;
            }

            $templates = $this->get_templates();

            return isset( $templates[ $slug ] ) ? $templates[ $slug ] : null;
        }

        /**
         * Prepare templates for use in client-side scripts.
         *
         * @param array $templates Templates array.
         * @return array
         */
        protected function prepare_templates_for_js( $templates ) {
            $prepared = array();

            if ( empty( $templates ) ) {
                return $prepared;
            }

            foreach ( $templates as $slug => $template ) {
                $prepared[ $slug ] = array(
                    'subject' => isset( $template['subject'] ) ? (string) $template['subject'] : '',
                    'body'    => isset( $template['body'] ) ? (string) $template['body'] : '',
                );
            }

            return $prepared;
        }

        /**
         * Apply placeholders to a template string.
         *
         * @param string $content           Template content.
         * @param object $lead              Lead database row.
         * @param array  $payload           Submission payload.
         * @param string $client_name       Client name override.
         * @param string $client_phone      Client phone override.
         * @param array  $recipient_emails  Recipient email list.
         * @return string
         */
        protected function apply_template_placeholders( $content, $lead, $payload, $client_name, $client_phone, $recipient_emails ) {
            if ( empty( $content ) ) {
                return $content;
            }

            $lead_email = ( $lead && ! empty( $lead->email ) ) ? $lead->email : '';

            if ( empty( $lead_email ) && ! empty( $recipient_emails ) ) {
                $lead_email = reset( $recipient_emails );
            }

            if ( empty( $client_name ) ) {
                $client_name = $lead && ! empty( $lead->response_client_name ) ? $lead->response_client_name : $this->extract_contact_name( $payload );
            }

            if ( empty( $client_phone ) ) {
                $client_phone = $this->extract_phone_from_payload( $payload );
            }

            $replacements = array(
                '%name%'        => $client_name,
                '%email%'       => $lead_email,
                '%phone%'       => $client_phone,
                '%status%'      => $lead ? $lead->status : '',
                '%date%'        => $lead ? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $lead->submitted_at ) : '',
                '%date_short%'  => $lead ? mysql2date( 'd/m/Y', $lead->submitted_at ) : '',
                '%form_title%'  => $lead ? $lead->form_title : '',
                '%site_name%'   => get_bloginfo( 'name' ),
            );

            if ( ! empty( $recipient_emails ) ) {
                $replacements['%cc%'] = implode( ', ', array_slice( $recipient_emails, 1 ) );
                $replacements['%recipient%'] = $recipient_emails[0];
            } else {
                $replacements['%cc%']        = '';
                $replacements['%recipient%'] = $lead_email;
            }

            if ( is_array( $payload ) ) {
                foreach ( $payload as $key => $value ) {
                    $sanitised_key = '%' . sanitize_key( $key ) . '%';
                    if ( is_array( $value ) ) {
                        $value = implode( ', ', array_map( 'wp_strip_all_tags', $value ) );
                    }

                    $value = $this->normalise_payload_value( $value );

                    if ( ! isset( $replacements[ $sanitised_key ] ) ) {
                        $replacements[ $sanitised_key ] = $value;
                    }
                }
            }

            return strtr( $content, $replacements );
        }

        /**
         * Convert stored payload JSON into an associative array.
         *
         * @param string $payload_json Stored payload JSON.
         * @return array
         */
        protected function normalise_payload( $payload_json ) {
            $payload = json_decode( $payload_json, true );

            if ( empty( $payload ) ) {
                return array();
            }

            if ( ! is_array( $payload ) ) {
                return array();
            }

            return $payload;
        }

        /**
         * Extract the contact name from a payload.
         *
         * @param array $payload Submission payload.
         * @return string
         */
        protected function extract_contact_name( $payload ) {
            if ( empty( $payload ) || ! is_array( $payload ) ) {
                return '';
            }

            $direct_candidates = array( 'name', 'your-name', 'fullname', 'full-name', 'contact-name', 'full_name', 'contact_name' );
            $value             = $this->find_payload_value( $payload, $direct_candidates );

            if ( $value ) {
                return $value;
            }

            $first = $this->find_payload_value( $payload, array( 'first-name', 'first_name', 'fname', 'given-name' ) );
            $last  = $this->find_payload_value( $payload, array( 'last-name', 'last_name', 'lname', 'surname', 'family-name' ) );

            if ( $first && $last ) {
                return trim( $first . ' ' . $last );
            }

            if ( $first ) {
                return $first;
            }

            if ( $last ) {
                return $last;
            }

            foreach ( $payload as $key => $item ) {
                if ( false !== strpos( strtolower( $key ), 'name' ) ) {
                    $normalised = $this->normalise_payload_value( $item );
                    if ( $normalised ) {
                        return $normalised;
                    }
                }
            }

            return '';
        }

        /**
         * Extract phone number from payload.
         *
         * @param array $payload Submission payload.
         * @return string
         */
        protected function extract_phone_from_payload( $payload ) {
            if ( empty( $payload ) || ! is_array( $payload ) ) {
                return '';
            }

            $candidates = array( 'phone', 'your-phone', 'telephone', 'mobile', 'tel', 'phone-number', 'phone_number', 'contact-number', 'contact_number', 'mobile-number', 'mobile_number', 'whatsapp', 'whatsapp-number', 'whatsapp_number', 'cellphone', 'cell-phone', 'cell_phone' );
            $value      = $this->find_payload_value( $payload, $candidates );

            if ( $value ) {
                return $value;
            }

            foreach ( $payload as $key => $item ) {
                if ( preg_match( '/phone|tel|mobile|contact|whats|cell/i', $key ) ) {
                    $normalised = $this->normalise_payload_value( $item );
                    if ( $normalised ) {
                        return $normalised;
                    }
                }

                if ( 'number' === strtolower( $key ) ) {
                    $normalised = $this->normalise_payload_value( $item );
                    if ( preg_match( '/\d{5,}/', $normalised ) ) {
                        return $normalised;
                    }
                }
            }

            return '';
        }

        /**
         * Find a payload value using a list of candidate keys.
         *
         * @param array $payload  Submission payload.
         * @param array $candidates Candidate keys.
         * @return string
         */
        protected function find_payload_value( $payload, $candidates ) {
            foreach ( $candidates as $candidate ) {
                if ( isset( $payload[ $candidate ] ) ) {
                    $value = $this->normalise_payload_value( $payload[ $candidate ] );
                    if ( $value ) {
                        return $value;
                    }
                }
            }

            foreach ( $payload as $key => $value ) {
                foreach ( $candidates as $candidate ) {
                    if ( false !== strpos( strtolower( $key ), strtolower( $candidate ) ) ) {
                        $normalised = $this->normalise_payload_value( $value );
                        if ( $normalised ) {
                            return $normalised;
                        }
                    }
                }
            }

            return '';
        }

        /**
         * Normalise payload values into a string representation.
         *
         * @param mixed $value Raw value.
         * @return string
         */
        protected function normalise_payload_value( $value ) {
            if ( is_array( $value ) ) {
                $value = implode( ', ', array_filter( array_map( array( $this, 'normalise_payload_value' ), $value ) ) );
            }

            if ( is_object( $value ) ) {
                $value = implode( ', ', array_filter( (array) $value ) );
            }

            $value = trim( (string) $value );

            return $value;
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
         * Retrieve a Contact Form 7 form by its derived slug.
         *
         * @param string $slug Form slug.
         * @return WPCF7_ContactForm|null
         */
        protected function get_contact_form_by_slug( $slug ) {
            if ( empty( $slug ) ) {
                return null;
            }

            $forms = $this->get_contact_forms();

            foreach ( $forms as $form ) {
                if ( $this->get_form_slug( $form ) === $slug ) {
                    return $form;
                }
            }

            return null;
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
                response_subject varchar(255) DEFAULT '',
                response_sent datetime DEFAULT NULL,
                response_client_name varchar(190) DEFAULT '',
                response_phone varchar(100) DEFAULT '',
                response_recipients longtext,
                response_template varchar(190) DEFAULT '',
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
