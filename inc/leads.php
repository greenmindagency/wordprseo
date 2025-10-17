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
                add_action( 'wp_ajax_theme_leads_send_email', array( $this, 'handle_ajax_send_email' ) );
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

            $result = $this->process_lead_update( $_POST );

            if ( is_wp_error( $result ) ) {
                wp_safe_redirect( admin_url( 'admin.php?page=theme-leads' ) );
                exit;
            }

            $redirect = admin_url( 'admin.php?page=theme-leads&form=' . $result['form_slug'] );
            wp_safe_redirect( $redirect );
            exit;
        }

        /**
         * Handle AJAX email sends without a page refresh.
         */
        public function handle_ajax_send_email() {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_send_json_error(
                    array( 'message' => esc_html__( 'You do not have permission to perform this action.', 'wordprseo' ) ),
                    403
                );
            }

            check_ajax_referer( 'theme_leads_update' );

            $request                     = $_POST;
            $request['lead_submit_action'] = 'send';

            $result = $this->process_lead_update( $request );

            if ( is_wp_error( $result ) ) {
                wp_send_json_error( array( 'message' => $result->get_error_message() ) );
            }

            wp_send_json_success( $result );
        }

        /**
         * Process a lead update submission.
         *
         * @param array $request Raw request data.
         * @return array|WP_Error
         */
        protected function process_lead_update( $request ) {
            global $wpdb;

            $form_slug = isset( $request['form_slug'] ) ? sanitize_key( wp_unslash( $request['form_slug'] ) ) : '';
            $lead_id   = isset( $request['lead_id'] ) ? absint( $request['lead_id'] ) : 0;

            if ( empty( $form_slug ) || ! $lead_id ) {
                return new WP_Error( 'theme_leads_invalid_request', __( 'Invalid lead update request.', 'wordprseo' ) );
            }

            $table = $this->get_table_name_from_slug( $form_slug );

            if ( ! $this->table_exists( $table ) ) {
                return new WP_Error( 'theme_leads_missing_table', __( 'The lead storage table could not be found.', 'wordprseo' ) );
            }

            $status        = isset( $request['lead_status'] ) ? sanitize_text_field( wp_unslash( $request['lead_status'] ) ) : 'new';
            $submit_action = isset( $request['lead_submit_action'] ) ? sanitize_key( wp_unslash( $request['lead_submit_action'] ) ) : 'save';

            $has_client_name  = array_key_exists( 'lead_client_name', $request );
            $has_client_phone = array_key_exists( 'lead_client_phone', $request );
            $has_subject      = array_key_exists( 'lead_reply_subject', $request );
            $has_message      = array_key_exists( 'lead_reply', $request );
            $has_template     = array_key_exists( 'lead_template', $request );
            $has_recipients   = array_key_exists( 'lead_client_emails', $request );

            $client_name  = $has_client_name ? sanitize_text_field( wp_unslash( $request['lead_client_name'] ) ) : null;
            $client_phone = $has_client_phone ? sanitize_text_field( wp_unslash( $request['lead_client_phone'] ) ) : null;
            $reply_subj   = $has_subject ? sanitize_text_field( wp_unslash( $request['lead_reply_subject'] ) ) : null;
            $reply_body   = $has_message ? wp_kses_post( wp_unslash( $request['lead_reply'] ) ) : null;
            $template_key = $has_template ? sanitize_key( wp_unslash( $request['lead_template'] ) ) : null;
            $note         = array_key_exists( 'lead_note', $request ) ? wp_kses_post( wp_unslash( $request['lead_note'] ) ) : null;

            $raw_emails = $has_recipients ? (array) $request['lead_client_emails'] : array();

            $recipient_list = array();
            foreach ( $raw_emails as $raw_email ) {
                $sanitised = sanitize_email( wp_unslash( $raw_email ) );
                if ( ! empty( $sanitised ) && is_email( $sanitised ) ) {
                    $recipient_list[] = $sanitised;
                }
            }

            $lead = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $lead_id ) );

            if ( ! $lead ) {
                return new WP_Error( 'theme_leads_missing_lead', __( 'The requested lead could not be found.', 'wordprseo' ) );
            }

            $payload = $this->normalise_payload( $lead->payload );

            $resolved_client_name  = null !== $client_name ? $client_name : ( ! empty( $lead->response_client_name ) ? $lead->response_client_name : $this->extract_contact_name( $payload ) );
            $resolved_client_phone = null !== $client_phone ? $client_phone : ( ! empty( $lead->response_phone ) ? $lead->response_phone : $this->extract_phone_from_payload( $payload ) );
            $resolved_subject      = null !== $reply_subj ? $reply_subj : ( ! empty( $lead->response_subject ) ? $lead->response_subject : '' );
            $resolved_message      = null !== $reply_body ? $reply_body : ( ! empty( $lead->response ) ? $lead->response : '' );
            $resolved_template     = null !== $template_key ? $template_key : ( ! empty( $lead->response_template ) ? sanitize_key( $lead->response_template ) : '' );

            $data   = array( 'status' => $status );
            $format = array( '%s' );

            if ( $has_client_name ) {
                $data['response_client_name'] = $client_name;
                $format[]                     = '%s';
            }

            $has_response_subject_field = false;
            if ( $has_subject ) {
                $data['response_subject'] = $reply_subj;
                $format[]                 = '%s';
                $has_response_subject_field = true;
            }

            if ( $has_client_phone ) {
                $data['response_phone'] = $client_phone;
                $format[]               = '%s';
            }

            if ( $has_template ) {
                $data['response_template'] = $template_key;
                $format[]                  = '%s';
            }

            $has_response_recipients_field = false;
            if ( $has_recipients ) {
                $data['response_recipients'] = ! empty( $recipient_list ) ? wp_json_encode( $recipient_list ) : '';
                $format[]                    = '%s';
                $has_response_recipients_field = true;
            }

            if ( null !== $note ) {
                $data['note'] = $note;
                $format[]     = '%s';
            }

            $has_response_field = false;
            if ( $has_message && '' !== trim( (string) $reply_body ) ) {
                $data['response'] = $reply_body;
                $format[]         = '%s';
                $has_response_field = true;
            }

            $recipient_emails = $recipient_list;

            if ( empty( $recipient_emails ) && ! empty( $lead->response_recipients ) ) {
                $decoded = json_decode( $lead->response_recipients, true );
                if ( is_array( $decoded ) ) {
                    foreach ( $decoded as $stored_email ) {
                        $clean = sanitize_email( $stored_email );
                        if ( ! empty( $clean ) ) {
                            $recipient_emails[] = $clean;
                        }
                    }
                }
            }

            $email_sent            = false;
            $prepared_subject      = $resolved_subject;
            $prepared_message      = $resolved_message;
            $prepared_recipients   = $recipient_emails;
            $response_sent_at      = '';
            $response_sent_display = '';

            if ( 'send' === $submit_action ) {
                if ( empty( $recipient_emails ) && ! empty( $lead->email ) && is_email( $lead->email ) ) {
                    $recipient_emails[] = $lead->email;
                }

                $template = ! empty( $resolved_template ) ? $this->get_template( $resolved_template ) : null;

                if ( empty( $resolved_subject ) && $template && ! empty( $template['subject'] ) ) {
                    $resolved_subject = $template['subject'];
                }

                if ( empty( $resolved_message ) && $template && ! empty( $template['body'] ) ) {
                    $resolved_message = $template['body'];
                }

                if ( empty( $resolved_subject ) ) {
                    $resolved_subject = __( 'Response to your enquiry', 'wordprseo' );
                }

                if ( empty( trim( wp_strip_all_tags( $resolved_message ) ) ) ) {
                    return new WP_Error( 'theme_leads_missing_message', __( 'Please enter a message before sending.', 'wordprseo' ) );
                }

                if ( empty( $recipient_emails ) ) {
                    return new WP_Error( 'theme_leads_missing_recipient', __( 'Please provide at least one email address.', 'wordprseo' ) );
                }

                $prepared_subject    = $this->apply_template_placeholders( $resolved_subject, $lead, $payload, $resolved_client_name, $resolved_client_phone, $recipient_emails );
                $prepared_message    = $this->apply_template_placeholders( $resolved_message, $lead, $payload, $resolved_client_name, $resolved_client_phone, $recipient_emails );
                $prepared_recipients = $recipient_emails;

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

                $to = array_shift( $recipient_emails );

                foreach ( $recipient_emails as $cc_email ) {
                    $headers[] = 'Cc: ' . $cc_email;
                }

                $email_sent = wp_mail( $to, $prepared_subject, wpautop( $prepared_message ), $headers );

                if ( $email_sent ) {
                    $response_sent_at      = current_time( 'mysql' );
                    $response_sent_display = mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $response_sent_at );

                    $data['response'] = $prepared_message;
                    if ( ! $has_response_field ) {
                        $format[]           = '%s';
                        $has_response_field = true;
                    }

                    $data['response_subject'] = $prepared_subject;
                    if ( ! $has_response_subject_field ) {
                        $format[]                   = '%s';
                        $has_response_subject_field = true;
                    }

                    $data['response_sent'] = $response_sent_at;
                    $format[]               = '%s';

                    $data['response_template'] = $resolved_template;
                    if ( ! $has_template ) {
                        $format[] = '%s';
                    }

                    $data['response_recipients'] = wp_json_encode( $prepared_recipients );
                    if ( ! $has_response_recipients_field ) {
                        $format[]                        = '%s';
                        $has_response_recipients_field = true;
                    }
                } else {
                    return new WP_Error( 'theme_leads_send_failed', __( 'The email could not be sent. Please try again.', 'wordprseo' ) );
                }
            }

            $wpdb->update(
                $table,
                $data,
                array( 'id' => $lead_id ),
                $format,
                array( '%d' )
            );

            $updated_lead = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $lead_id ) );

            if ( ! $updated_lead ) {
                return new WP_Error( 'theme_leads_update_failed', __( 'The lead could not be updated.', 'wordprseo' ) );
            }

            $updated_payload     = $this->normalise_payload( $updated_lead->payload );
            $updated_context     = $this->build_template_context_data( $updated_lead, $updated_payload );
            $response_history    = $this->get_response_history_markup( $updated_lead );
            $summary_name        = ! empty( $updated_lead->response_client_name ) ? $updated_lead->response_client_name : $this->extract_contact_name( $updated_payload );
            $summary_status      = ucfirst( $updated_lead->status );
            $summary_last_reply  = ! empty( $updated_lead->response_sent ) ? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $updated_lead->response_sent ) : '';
            $recipients_display  = $this->format_recipient_list_for_display( $updated_lead->response_recipients );
            $response_sent_value = ! empty( $updated_lead->response_sent ) ? $updated_lead->response_sent : $response_sent_at;

            return array(
                'form_slug'      => $form_slug,
                'lead_id'        => $lead_id,
                'status'         => $updated_lead->status,
                'note'           => $updated_lead->note,
                'context'        => $updated_context,
                'response'       => array(
                    'subject'   => $updated_lead->response_subject,
                    'message'   => $updated_lead->response,
                    'sent'      => $response_sent_value,
                    'sent_formatted' => $summary_last_reply,
                    'recipients' => $recipients_display,
                ),
                'summary'        => array(
                    'name'          => $summary_name,
                    'status_label'  => $summary_status,
                    'last_response' => $summary_last_reply,
                ),
                'history_markup' => $response_history,
                'email_sent'     => $email_sent,
            );
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
            echo '<p>' . esc_html__( 'Use placeholders like %name%, %email%, %phone%, %date%, %form_title%, or any Contact Form 7 field key (for example %your-name%) to personalise messages automatically.', 'wordprseo' ) . '</p>';

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

                $template_context_attrs = $this->build_template_context_attributes( $lead, $payload, $client_name_value, $client_phone_value );
                $context_attr_string    = '';

                foreach ( $template_context_attrs as $attr_key => $attr_value ) {
                    if ( '' !== $attr_value ) {
                        $context_attr_string .= ' ' . $attr_key . '="' . $attr_value . '"';
                    }
                }

                $history_markup = $this->get_response_history_markup( $lead );

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
                    echo '<div class="theme-leads-meta theme-leads-last-response"><small>' . esc_html__( 'Last response', 'wordprseo' ) . ': ' . esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $lead->response_sent ) ) . '</small></div>';
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

                echo '<td class="theme-leads-summary-status">' . esc_html( ucfirst( $lead->status ) ) . '</td>';

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
                echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="theme-leads-detail-form">';
                wp_nonce_field( 'theme_leads_update' );
                echo '<input type="hidden" name="action" value="theme_leads_update" />';
                echo '<input type="hidden" name="form_slug" value="' . esc_attr( $form_slug ) . '" />';
                echo '<input type="hidden" name="lead_id" value="' . absint( $lead->id ) . '" />';
                echo '<div class="theme-leads-template-context"' . $context_attr_string . '></div>';

                echo '<div class="theme-leads-details-columns">';

                echo '<div class="theme-leads-details-column theme-leads-details-column--left">';
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

                echo '<div class="theme-leads-form-group theme-leads-form-group-status">';
                echo '<label>' . esc_html__( 'Status', 'wordprseo' );
                echo '<select name="lead_status" class="theme-leads-status-select">';
                foreach ( $statuses as $status ) {
                    printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $status ), selected( $lead->status, $status, false ), esc_html( ucfirst( $status ) ) );
                }
                echo '</select>';
                echo '</label>';
                echo '</div>';

                echo '<div class="theme-leads-form-group theme-leads-form-group-note">';
                echo '<label>' . esc_html__( 'Internal note', 'wordprseo' );
                echo '<textarea name="lead_note" rows="3" class="large-text">' . esc_textarea( $lead->note ) . '</textarea>';
                echo '</label>';
                echo '</div>';

                if ( $history_markup ) {
                    echo $history_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                }

                echo '</div>';

                echo '<div class="theme-leads-details-column theme-leads-details-column--right">';
                echo '<h3>' . esc_html__( 'Update & respond', 'wordprseo' ) . '</h3>';

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
                echo '<label>' . esc_html__( 'Subject', 'wordprseo' );
                echo '<input type="text" name="lead_reply_subject" class="widefat" value="' . esc_attr( $lead->response_subject ) . '" />';
                echo '</label>';
                echo '</div>';

                echo '<div class="theme-leads-form-group">';
                echo '<label>' . esc_html__( 'Message', 'wordprseo' );
                echo '<textarea name="lead_reply" rows="6" class="large-text">' . esc_textarea( $lead->response ) . '</textarea>';
                echo '</label>';
                echo '</div>';

                echo '<div class="theme-leads-form-feedback" aria-live="polite"></div>';

                echo '<div class="theme-leads-form-actions">';
                echo '<button type="submit" name="lead_submit_action" value="save" class="button">' . esc_html__( 'Save changes', 'wordprseo' ) . '</button>';
                echo '<button type="submit" name="lead_submit_action" value="send" class="button button-primary" data-busy-label="' . esc_attr__( 'Sending…', 'wordprseo' ) . '">' . esc_html__( 'Send email', 'wordprseo' ) . '</button>';
                echo '</div>';

                echo '</div>';

                echo '</div>';

                echo '</form>';
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
                .theme-leads-form-feedback { min-height:20px; font-size:13px; font-weight:500; color:#2271b1; }
                .theme-leads-form-feedback.is-error { color:#b32d2e; }
                .theme-leads-form-feedback.is-success { color:#2271b1; }
                .theme-leads-template-context { display:none; }
            </style>';

            $templates_json = wp_json_encode( $this->prepare_templates_for_js( $templates ) );
            if ( $templates_json ) {
                echo '<script type="application/json" id="theme-leads-templates-data">' . str_replace( '</', '<\/', $templates_json ) . '</script>';
            }

            $remove_email_label_json   = wp_json_encode( __( 'Remove email', 'wordprseo' ) );
            $sending_label_json        = wp_json_encode( __( 'Sending…', 'wordprseo' ) );
            $send_success_message_json = wp_json_encode( __( 'Email sent successfully.', 'wordprseo' ) );
            $send_error_message_json   = wp_json_encode( __( 'Unable to send email. Please try again.', 'wordprseo' ) );
            $last_response_label_json  = wp_json_encode( __( 'Last response', 'wordprseo' ) );

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
    const sendingLabel = {$sending_label_json};
    const sendSuccessMessage = {$send_success_message_json};
    const sendErrorMessage = {$send_error_message_json};
    const lastResponseLabel = {$last_response_label_json};
    const ajaxUrl = typeof window.ajaxurl !== "undefined" ? window.ajaxurl : "";

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
        const feedbackEl = form.querySelector(".theme-leads-form-feedback");

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

        form.addEventListener("submit", function(event) {
            const submitter = event.submitter || document.activeElement;
            if (!submitter || submitter.name !== "lead_submit_action") {
                return;
            }

            if (submitter.value === "send" && ajaxUrl) {
                event.preventDefault();
                sendLeadViaAjax(form, submitter, feedbackEl);
            }
        });
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
            date_short: "",
            site_name: "",
            recipient: "",
            cc: ""
        };

        if (contextEl) {
            data.name = contextEl.dataset.name || "";
            data.email = contextEl.dataset.email || "";
            data.phone = contextEl.dataset.phone || "";
            data.status = contextEl.dataset.status || "";
            data.date = contextEl.dataset.date || "";
            data.form_title = contextEl.dataset.formTitle || "";
            data.date_short = contextEl.dataset.dateShort || "";
            data.site_name = contextEl.dataset.siteName || "";
            data.recipient = contextEl.dataset.recipient || "";
            data.cc = contextEl.dataset.cc || "";

            if (contextEl.dataset.payload) {
                try {
                    const payloadData = JSON.parse(contextEl.dataset.payload);
                    Object.keys(payloadData).forEach(function(key) {
                        const value = payloadData[key];
                        const lookupKey = key.toLowerCase();
                        data[lookupKey] = value;
                        data[key] = value;
                    });
                } catch (error) {
                    // Ignore malformed payload data.
                }
            }
        }

        const emailInputs = form.querySelectorAll(".theme-leads-email-field input[name='lead_client_emails[]']");
        if (emailInputs.length) {
            const addresses = Array.from(emailInputs)
                .map(function(input) { return input.value || ""; })
                .filter(function(value) { return value !== ""; });

            if (addresses.length) {
                data.email = addresses[0];
                data.recipient = addresses[0];
                data.cc = addresses.slice(1).join(", ");
            }
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

    function sendLeadViaAjax(form, submitter, feedbackEl) {
        const formData = new FormData(form);
        formData.set("lead_submit_action", "send");
        formData.set("action", "theme_leads_send_email");

        const originalLabel = submitter.textContent;
        const busyLabel = submitter.getAttribute("data-busy-label") || sendingLabel;
        submitter.disabled = true;
        submitter.dataset.originalLabel = originalLabel;
        submitter.textContent = busyLabel;

        if (feedbackEl) {
            feedbackEl.textContent = "";
            feedbackEl.classList.remove("is-error", "is-success");
        }

        fetch(ajaxUrl, {
            method: "POST",
            credentials: "same-origin",
            body: formData
        })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error(sendErrorMessage);
                }
                return response.json();
            })
            .then(function(payload) {
                if (!payload || typeof payload !== "object") {
                    throw new Error(sendErrorMessage);
                }

                if (payload.success) {
                    handleLeadUpdateSuccess(form, payload.data || {}, feedbackEl);
                } else {
                    const message = payload.data && payload.data.message ? payload.data.message : sendErrorMessage;
                    throw new Error(message);
                }
            })
            .catch(function(error) {
                handleLeadUpdateError(feedbackEl, error && error.message ? error.message : sendErrorMessage);
            })
            .finally(function() {
                submitter.disabled = false;
                submitter.textContent = submitter.dataset.originalLabel || originalLabel;
            });
    }

    function handleLeadUpdateSuccess(form, data, feedbackEl) {
        if (feedbackEl) {
            feedbackEl.textContent = sendSuccessMessage;
            feedbackEl.classList.remove("is-error");
            feedbackEl.classList.add("is-success");
        }

        if (!data || typeof data !== "object") {
            return;
        }

        if (data.status) {
            const statusSelect = form.querySelector("select[name='lead_status']");
            if (statusSelect) {
                statusSelect.value = data.status;
            }
        }

        if (Object.prototype.hasOwnProperty.call(data, "note")) {
            const noteField = form.querySelector("textarea[name='lead_note']");
            if (noteField) {
                noteField.value = data.note || "";
            }
        }

        if (data.response) {
            const subjectField = form.querySelector("input[name='lead_reply_subject']");
            const messageField = form.querySelector("textarea[name='lead_reply']");
            if (subjectField && Object.prototype.hasOwnProperty.call(data.response, "subject")) {
                subjectField.value = data.response.subject || "";
            }
            if (messageField && Object.prototype.hasOwnProperty.call(data.response, "message")) {
                messageField.value = data.response.message || "";
            }
        }

        if (data.context) {
            updateContextElement(form, data.context);
        }

        if (Object.prototype.hasOwnProperty.call(data, "history_markup")) {
            updateHistoryBlock(form, data.history_markup);
        }

        updateSummaryRow(data.lead_id, data);
    }

    function handleLeadUpdateError(feedbackEl, message) {
        if (feedbackEl) {
            feedbackEl.textContent = message || sendErrorMessage;
            feedbackEl.classList.remove("is-success");
            feedbackEl.classList.add("is-error");
        } else {
            window.alert(message || sendErrorMessage);
        }
    }

    function updateHistoryBlock(form, markup) {
        const existing = form.querySelector('[data-role="response-history"]');
        if (markup) {
            if (existing) {
                existing.outerHTML = markup;
            } else {
                const leftColumn = form.querySelector('.theme-leads-details-column--left');
                if (leftColumn) {
                    leftColumn.insertAdjacentHTML('beforeend', markup);
                }
            }
        } else if (existing) {
            existing.remove();
        }
    }

    function updateContextElement(form, context) {
        const contextEl = form.querySelector(".theme-leads-template-context");
        if (!contextEl || !context) {
            return;
        }

        if (Object.prototype.hasOwnProperty.call(context, "name")) {
            contextEl.dataset.name = context.name || "";
        }
        if (Object.prototype.hasOwnProperty.call(context, "email")) {
            contextEl.dataset.email = context.email || "";
        }
        if (Object.prototype.hasOwnProperty.call(context, "phone")) {
            contextEl.dataset.phone = context.phone || "";
        }
        if (Object.prototype.hasOwnProperty.call(context, "status")) {
            contextEl.dataset.status = context.status || "";
        }
        if (Object.prototype.hasOwnProperty.call(context, "date")) {
            contextEl.dataset.date = context.date || "";
        }
        if (Object.prototype.hasOwnProperty.call(context, "date_short")) {
            contextEl.dataset.dateShort = context.date_short || "";
        }
        if (Object.prototype.hasOwnProperty.call(context, "form_title")) {
            contextEl.dataset.formTitle = context.form_title || "";
        }
        if (Object.prototype.hasOwnProperty.call(context, "site_name")) {
            contextEl.dataset.siteName = context.site_name || "";
        }
        if (Object.prototype.hasOwnProperty.call(context, "recipient")) {
            contextEl.dataset.recipient = context.recipient || "";
        }
        if (Object.prototype.hasOwnProperty.call(context, "cc")) {
            contextEl.dataset.cc = context.cc || "";
        }
        if (context.payload) {
            try {
                contextEl.dataset.payload = JSON.stringify(context.payload);
            } catch (error) {
                // Ignore JSON errors when updating context payload.
            }
        }
    }

    function updateSummaryRow(leadId, data) {
        if (!leadId) {
            return;
        }

        const summaryRow = document.querySelector(".theme-leads-summary[data-lead='" + leadId + "']");
        if (summaryRow && data.summary) {
            if (data.summary.status_label) {
                const statusCell = summaryRow.querySelector(".theme-leads-summary-status");
                if (statusCell) {
                    statusCell.textContent = data.summary.status_label;
                }
            }

            if (data.summary.name) {
                const nameEl = summaryRow.querySelector(".theme-leads-name-text");
                if (nameEl) {
                    nameEl.textContent = data.summary.name;
                }
            }

            const emailCell = summaryRow.querySelector("td:nth-child(3)");
            let lastResponseEl = summaryRow.querySelector(".theme-leads-last-response");

            if (data.summary.last_response) {
                if (!lastResponseEl && emailCell) {
                    lastResponseEl = document.createElement("div");
                    lastResponseEl.className = "theme-leads-meta theme-leads-last-response";
                    lastResponseEl.innerHTML = "<small>" + lastResponseLabel + ": " + data.summary.last_response + "</small>";
                    emailCell.appendChild(lastResponseEl);
                } else if (lastResponseEl) {
                    const text = lastResponseLabel + ": " + data.summary.last_response;
                    const small = lastResponseEl.querySelector("small");
                    if (small) {
                        small.textContent = text;
                    } else {
                        lastResponseEl.textContent = text;
                    }
                }
            } else if (lastResponseEl) {
                lastResponseEl.remove();
            }
        }

        if (data.status) {
            const detailRow = document.querySelector(".theme-leads-details[data-lead='" + leadId + "']");
            if (detailRow) {
                const detailSelect = detailRow.querySelector("select[name='lead_status']");
                if (detailSelect) {
                    detailSelect.value = data.status;
                }
            }

            const quickLeadInput = document.querySelector('.theme-leads-status-form input[name="lead_id"][value="' + leadId + '"]');
            if (quickLeadInput) {
                const quickSelect = quickLeadInput.closest("form").querySelector("select[name='lead_status']");
                if (quickSelect) {
                    quickSelect.value = data.status;
                }
            }
        }
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
         * Build template context data for server and client usage.
         *
         * @param object $lead    Lead database row.
         * @param array  $payload Submission payload.
         * @return array
         */
        protected function build_template_context_data( $lead, $payload ) {
            $submitted_at = ! empty( $lead->submitted_at ) ? $lead->submitted_at : current_time( 'mysql' );

            $context = array(
                'name'       => ! empty( $lead->response_client_name ) ? $lead->response_client_name : $this->extract_contact_name( $payload ),
                'email'      => ! empty( $lead->email ) ? sanitize_email( $lead->email ) : '',
                'phone'      => ! empty( $lead->response_phone ) ? $lead->response_phone : $this->extract_phone_from_payload( $payload ),
                'status'     => ! empty( $lead->status ) ? $lead->status : 'new',
                'date'       => mysql2date( 'Y-m-d\TH:i:sP', $submitted_at ),
                'date_short' => mysql2date( 'd/m/Y', $submitted_at ),
                'form_title' => ! empty( $lead->form_title ) ? $lead->form_title : '',
                'site_name'  => get_bloginfo( 'name' ),
                'payload'    => $this->prepare_payload_for_js( $payload ),
                'recipient'  => '',
                'cc'         => '',
            );

            if ( ! empty( $lead->response_recipients ) ) {
                $decoded = json_decode( $lead->response_recipients, true );
                if ( is_array( $decoded ) && ! empty( $decoded ) ) {
                    $clean = array();
                    foreach ( $decoded as $email ) {
                        $sanitised = sanitize_email( $email );
                        if ( ! empty( $sanitised ) ) {
                            $clean[] = $sanitised;
                        }
                    }

                    if ( ! empty( $clean ) ) {
                        $context['recipient'] = $clean[0];
                        if ( count( $clean ) > 1 ) {
                            $context['cc'] = implode( ', ', array_slice( $clean, 1 ) );
                        }
                    }
                }
            }

            return $context;
        }

        /**
         * Convert payload data to a JSON-safe placeholder map.
         *
         * @param array $payload Submission payload.
         * @return array
         */
        protected function prepare_payload_for_js( $payload ) {
            if ( empty( $payload ) || ! is_array( $payload ) ) {
                return array();
            }

            $prepared = array();

            foreach ( $payload as $key => $value ) {
                $normalised_key = strtolower( (string) $key );
                $normalised_key = preg_replace( '/[^a-z0-9_\-]/', '', $normalised_key );

                if ( empty( $normalised_key ) ) {
                    continue;
                }

                $string_value = $this->normalise_payload_value( $value );

                $prepared[ $normalised_key ] = $string_value;

                if ( strpos( $normalised_key, '-' ) !== false ) {
                    $prepared[ str_replace( '-', '_', $normalised_key ) ] = $string_value;
                }

                if ( strpos( $normalised_key, '_' ) !== false ) {
                    $prepared[ str_replace( '_', '-', $normalised_key ) ] = $string_value;
                }
            }

            return $prepared;
        }

        /**
         * Build data attributes for the template context container.
         *
         * @param object $lead              Lead database row.
         * @param array  $payload           Submission payload.
         * @param string $client_name_value Client name override.
         * @param string $client_phone      Client phone override.
         * @return array
         */
        protected function build_template_context_attributes( $lead, $payload, $client_name_value, $client_phone ) {
            $context = $this->build_template_context_data( $lead, $payload );

            if ( ! empty( $client_name_value ) ) {
                $context['name'] = $client_name_value;
            }

            if ( ! empty( $client_phone ) ) {
                $context['phone'] = $client_phone;
            }

            $attrs = array(
                'data-name'       => esc_attr( $context['name'] ),
                'data-email'      => esc_attr( $context['email'] ),
                'data-phone'      => esc_attr( $context['phone'] ),
                'data-status'     => esc_attr( $context['status'] ),
                'data-date'       => esc_attr( $context['date'] ),
                'data-date-short' => esc_attr( $context['date_short'] ),
                'data-form-title' => esc_attr( $context['form_title'] ),
                'data-site-name'  => esc_attr( get_bloginfo( 'name' ) ),
                'data-recipient'  => esc_attr( $context['recipient'] ),
                'data-cc'         => esc_attr( $context['cc'] ),
            );

            if ( ! empty( $context['payload'] ) ) {
                $attrs['data-payload'] = esc_attr( wp_json_encode( $context['payload'] ) );
            }

            return $attrs;
        }

        /**
         * Format stored recipient JSON into a printable list.
         *
         * @param string $recipients_json Stored recipient JSON.
         * @return string
         */
        protected function format_recipient_list_for_display( $recipients_json ) {
            if ( empty( $recipients_json ) ) {
                return '';
            }

            $decoded = json_decode( $recipients_json, true );

            if ( empty( $decoded ) || ! is_array( $decoded ) ) {
                return '';
            }

            $clean = array();

            foreach ( $decoded as $email ) {
                $sanitised = sanitize_email( $email );
                if ( ! empty( $sanitised ) ) {
                    $clean[] = $sanitised;
                }
            }

            return implode( ', ', $clean );
        }

        /**
         * Generate the response history markup for a lead.
         *
         * @param object $lead Lead database row.
         * @return string
         */
        protected function get_response_history_markup( $lead ) {
            if ( empty( $lead->response_sent ) ) {
                return '';
            }

            $output  = '<div class="theme-leads-response-history" data-role="response-history">';
            $output .= '<strong>' . esc_html__( 'Last email sent:', 'wordprseo' ) . '</strong> ' . esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $lead->response_sent ) );

            if ( ! empty( $lead->response_subject ) ) {
                $output .= '<div><strong>' . esc_html__( 'Subject:', 'wordprseo' ) . '</strong> ' . esc_html( $lead->response_subject ) . '</div>';
            }

            if ( ! empty( $lead->response_recipients ) ) {
                $recipients = $this->format_recipient_list_for_display( $lead->response_recipients );
                if ( ! empty( $recipients ) ) {
                    $output .= '<div><strong>' . esc_html__( 'Recipients:', 'wordprseo' ) . '</strong> ' . esc_html( $recipients ) . '</div>';
                }
            }

            if ( ! empty( $lead->response ) ) {
                $output .= '<div class="theme-leads-response-preview">' . wp_kses_post( wpautop( $lead->response ) ) . '</div>';
            }

            $output .= '</div>';

            return $output;
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
                    if ( is_array( $value ) ) {
                        $value = implode( ', ', array_map( 'wp_strip_all_tags', $value ) );
                    }

                    $value = $this->normalise_payload_value( $value );

                    if ( '' === $value ) {
                        continue;
                    }

                    $raw_key = strtolower( (string) $key );
                    $raw_key = preg_replace( '/[^a-z0-9_\-]/', '', $raw_key );

                    if ( ! empty( $raw_key ) ) {
                        $raw_token = '%' . $raw_key . '%';
                        if ( ! isset( $replacements[ $raw_token ] ) ) {
                            $replacements[ $raw_token ] = $value;
                        }

                        if ( false !== strpos( $raw_key, '-' ) ) {
                            $alt_token = '%' . str_replace( '-', '_', $raw_key ) . '%';
                            if ( ! isset( $replacements[ $alt_token ] ) ) {
                                $replacements[ $alt_token ] = $value;
                            }
                        }

                        if ( false !== strpos( $raw_key, '_' ) ) {
                            $alt_token = '%' . str_replace( '_', '-', $raw_key ) . '%';
                            if ( ! isset( $replacements[ $alt_token ] ) ) {
                                $replacements[ $alt_token ] = $value;
                            }
                        }
                    }

                    $sanitised = sanitize_key( $key );
                    if ( ! empty( $sanitised ) ) {
                        $sanitised_token = '%' . $sanitised . '%';
                        if ( ! isset( $replacements[ $sanitised_token ] ) ) {
                            $replacements[ $sanitised_token ] = $value;
                        }
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
