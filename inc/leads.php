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
            $note       = array_key_exists( 'lead_note', $_POST ) ? wp_kses_post( wp_unslash( $_POST['lead_note'] ) ) : null;
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

            $data   = array( 'status' => $status );
            $format = array( '%s' );

            if ( null !== $note ) {
                $data['note'] = $note;
                $format[]     = '%s';
            }

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

            echo '<table class="widefat fixed striped theme-leads-table">';
            echo '<thead><tr>';
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
                $formatted_at = mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $lead->submitted_at );
                $detail_id    = 'theme-lead-details-' . absint( $lead->id );

                echo '<tr class="theme-leads-summary" data-lead="' . absint( $lead->id ) . '">';
                echo '<td class="theme-leads-summary-name">';
                echo '<button type="button" class="theme-leads-toggle-button" aria-expanded="false" aria-controls="' . esc_attr( $detail_id ) . '"><span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__( 'Toggle lead details', 'wordprseo' ) . '</span></button>';
                echo '<span class="theme-leads-name-text">' . esc_html( $lead_name ? $lead_name : __( 'Unknown', 'wordprseo' ) ) . '</span>';
                echo '<div class="theme-leads-submitted"><small>' . esc_html__( 'Submitted', 'wordprseo' ) . ': ' . esc_html( $formatted_at ) . '</small></div>';
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
                echo '<td colspan="6">';
                echo '<div class="theme-leads-details-inner">';

                if ( ! empty( $payload ) ) {
                    echo '<h3>' . esc_html__( 'Submission details', 'wordprseo' ) . '</h3>';
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
                    echo '<p class="theme-leads-note"><strong>' . esc_html__( 'Internal note:', 'wordprseo' ) . '</strong> ' . wp_kses_post( $lead->note ) . '</p>';
                }

                echo '<h3>' . esc_html__( 'Update lead', 'wordprseo' ) . '</h3>';
                echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="theme-leads-detail-form">';
                wp_nonce_field( 'theme_leads_update' );
                echo '<input type="hidden" name="action" value="theme_leads_update" />';
                echo '<input type="hidden" name="form_slug" value="' . esc_attr( $form_slug ) . '" />';
                echo '<input type="hidden" name="lead_id" value="' . absint( $lead->id ) . '" />';
                echo '<p><label>' . esc_html__( 'Status', 'wordprseo' ) . '<br />';
                echo '<select name="lead_status" class="theme-leads-status-select">';
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
                echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Save changes', 'wordprseo' ) . '</button></p>';
                echo '</form>';

                echo '</div>';
                echo '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';

            echo '<style>
                .theme-leads-table .theme-leads-summary-name { display:flex; align-items:center; gap:8px; }
                .theme-leads-toggle-button { border:0; background:transparent; cursor:pointer; padding:2px; margin-right:4px; }
                .theme-leads-toggle-button:focus { outline:2px solid #2271b1; outline-offset:2px; }
                .theme-leads-summary { cursor:pointer; }
                .theme-leads-summary .theme-leads-submitted { margin-top:4px; }
                .theme-leads-summary.is-open .theme-leads-toggle-button .dashicons { transform:rotate(90deg); }
                .theme-leads-details { display:none; background:#f9f9f9; }
                .theme-leads-details.is-open { display:table-row; }
                .theme-leads-details-inner { padding:16px 12px; }
                .theme-leads-payload { margin:0 0 16px; padding-left:18px; }
                .theme-leads-actions { display:flex; align-items:center; gap:8px; }
                .theme-leads-action { display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px; border-radius:50%; border:1px solid #ccd0d4; background:#fff; text-decoration:none; color:inherit; }
                .theme-leads-action:hover { border-color:#2271b1; color:#2271b1; }
                .theme-leads-delete { background:#fef2f2; border-color:#dc3232; color:#dc3232; }
                .theme-leads-delete:hover { background:#dc3232; color:#fff; }
                .theme-leads-delete-form { margin:0; }
                .theme-leads-status-form { margin:0; }
            </style>';

            echo '<script>
                document.addEventListener("DOMContentLoaded", function() {
                    const rows = document.querySelectorAll(".theme-leads-summary");
                    rows.forEach(function(row) {
                        row.addEventListener("click", function(event) {
                            if (event.target.closest(".theme-leads-no-toggle") || event.target.closest("a")) {
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

                    function toggleLeadRow(leadId, button) {
                        const summary = document.querySelector(".theme-leads-summary[data-lead=\'" + leadId + "\']");
                        const detail = document.querySelector(".theme-leads-details[data-lead=\'" + leadId + "\']");
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
            </script>';

            echo '</div>';
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

            $candidates = array( 'phone', 'your-phone', 'telephone', 'mobile', 'tel', 'phone-number', 'phone_number', 'contact-number', 'contact_number' );
            $value      = $this->find_payload_value( $payload, $candidates );

            if ( $value ) {
                return $value;
            }

            foreach ( $payload as $key => $item ) {
                if ( preg_match( '/phone|tel|mobile|contact/i', $key ) ) {
                    $normalised = $this->normalise_payload_value( $item );
                    if ( $normalised ) {
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
