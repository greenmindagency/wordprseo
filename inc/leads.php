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
         * Mailer settings currently being applied to PHPMailer.
         *
         * @var array|null
         */
        protected $active_mailer_settings = null;

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

            $this->maybe_create_templates_table();
            $this->maybe_create_options_table();

            add_action( 'wpcf7_before_send_mail', array( $this, 'capture_submission' ), 10, 3 );

            if ( is_admin() ) {
                add_action( 'admin_menu', array( $this, 'register_menu' ) );
                add_action( 'admin_post_theme_leads_update', array( $this, 'handle_status_update' ) );
                add_action( 'admin_post_theme_leads_delete', array( $this, 'handle_delete' ) );
                add_action( 'admin_post_theme_leads_bulk', array( $this, 'handle_bulk_action' ) );
                add_action( 'admin_post_theme_leads_template_save', array( $this, 'handle_template_save' ) );
                add_action( 'admin_post_theme_leads_template_delete', array( $this, 'handle_template_delete' ) );
                add_action( 'admin_post_theme_leads_statuses_save', array( $this, 'handle_statuses_save' ) );
                add_action( 'admin_post_theme_leads_columns_save', array( $this, 'handle_columns_save' ) );
                add_action( 'admin_post_theme_leads_default_cc_save', array( $this, 'handle_default_cc_save' ) );
                add_action( 'admin_post_theme_leads_mailer_save', array( $this, 'handle_mailer_settings_save' ) );
                add_action( 'admin_post_theme_leads_download', array( $this, 'handle_uploaded_file_download' ) );
                add_action( 'wp_ajax_theme_leads_update', array( $this, 'handle_ajax_update_lead' ) );
                add_action( 'wp_ajax_theme_leads_send_email', array( $this, 'handle_ajax_send_email' ) );
                add_action( 'wp_ajax_theme_leads_template_save', array( $this, 'handle_ajax_template_save' ) );
                add_action( 'wp_ajax_theme_leads_template_delete', array( $this, 'handle_ajax_template_delete' ) );
                add_action( 'wp_ajax_theme_leads_statuses_save', array( $this, 'handle_ajax_statuses_save' ) );
                add_action( 'wp_ajax_theme_leads_columns_save', array( $this, 'handle_ajax_columns_save' ) );
                add_action( 'wp_ajax_theme_leads_default_cc_save', array( $this, 'handle_ajax_default_cc_save' ) );
                add_action( 'wp_ajax_theme_leads_mailer_save', array( $this, 'handle_ajax_mailer_settings_save' ) );
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

            $form_slug   = $this->get_form_slug( $contact_form );
            $posted_data = $submission->get_posted_data();
            $posted_data = $this->enrich_payload_with_uploaded_files( $posted_data, $submission, $form_slug );
            $email       = $this->extract_email_from_submission( $posted_data );

            global $wpdb;

            $table     = $this->get_table_name( $contact_form );

            $this->maybe_create_table( $table, $form_slug );

            $default_status = $this->get_default_status_slug( $form_slug );

            $wpdb->insert(
                $table,
                array(
                    'submitted_at'  => current_time( 'mysql' ),
                    'status'        => $default_status,
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
         * Merge uploaded file information into the stored payload.
         *
         * @param mixed             $posted_data Submission payload.
         * @param WPCF7_Submission  $submission  Submission instance.
         * @param string            $form_slug   Current form slug.
         * @return array
         */
        protected function enrich_payload_with_uploaded_files( $posted_data, $submission, $form_slug = '' ) {
            if ( ! is_array( $posted_data ) ) {
                $posted_data = array();
            }

            $uploaded_files = $this->get_uploaded_files_payload( $submission, $form_slug );

            if ( empty( $uploaded_files ) ) {
                return $posted_data;
            }

            foreach ( $uploaded_files as $field_key => $file_entries ) {
                if ( isset( $posted_data[ $field_key ] ) ) {
                    $posted_data[ $field_key ] = array(
                        'value' => $posted_data[ $field_key ],
                        'files' => $file_entries,
                    );
                } else {
                    $posted_data[ $field_key ] = array(
                        'files' => $file_entries,
                    );
                }
            }

            return $posted_data;
        }

        /**
         * Extract uploaded file information from the submission.
         *
         * @param WPCF7_Submission $submission Submission instance.
         * @param string           $form_slug  Current form slug.
         * @return array
         */
        protected function get_uploaded_files_payload( $submission, $form_slug = '' ) {
            if ( ! $submission || ! is_callable( array( $submission, 'uploaded_files' ) ) ) {
                return array();
            }

            $uploaded_files = $submission->uploaded_files();

            if ( empty( $uploaded_files ) || ! is_array( $uploaded_files ) ) {
                return array();
            }

            $prepared = array();

            foreach ( $uploaded_files as $field_key => $file_paths ) {
                if ( empty( $file_paths ) ) {
                    continue;
                }

                $paths = is_array( $file_paths ) ? $file_paths : array( $file_paths );
                $paths = array_filter( array_map( 'trim', $paths ) );

                if ( empty( $paths ) ) {
                    continue;
                }

                $entries = array();

                foreach ( $paths as $path ) {
                    $entry = $this->normalise_uploaded_file_entry( $path, $form_slug );

                    if ( ! empty( $entry ) ) {
                        $entries[] = $entry;
                    }
                }

                if ( empty( $entries ) ) {
                    continue;
                }

                $prepared[ $field_key ] = $entries;
            }

            return $prepared;
        }

        /**
         * Normalise a file path into stored payload data.
         *
         * @param string $file_path Absolute file path.
         * @param string $form_slug Current form slug.
         * @return array|null
         */
        protected function normalise_uploaded_file_entry( $file_path, $form_slug = '' ) {
            if ( empty( $file_path ) || ! is_string( $file_path ) ) {
                return null;
            }

            $file_path = wp_normalize_path( $file_path );
            $file_path = $this->ensure_uploaded_file_persisted( $file_path, $form_slug );

            if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
                return null;
            }

            $file_name = wp_basename( $file_path );

            if ( '' === $file_name ) {
                return null;
            }

            $file_data = array(
                'file_name' => sanitize_text_field( $file_name ),
            );

            $uploads = wp_upload_dir();

            if ( empty( $uploads['error'] ) && ! empty( $uploads['basedir'] ) && ! empty( $uploads['baseurl'] ) ) {
                $basedir = wp_normalize_path( trailingslashit( $uploads['basedir'] ) );
                $baseurl = trailingslashit( $uploads['baseurl'] );

                if ( strpos( $file_path, $basedir ) === 0 ) {
                    $relative = ltrim( substr( $file_path, strlen( $basedir ) ), '/' );
                    $relative = str_replace( '\\', '/', $relative );
                    $relative = preg_replace( '#/+#', '/', $relative );

                    if ( false === strpos( $relative, '..' ) ) {
                        $file_data['relative_path'] = sanitize_text_field( $relative );
                    }

                    $file_data['file_url'] = esc_url_raw( $baseurl . $relative );
                }
            }

            return $file_data;
        }

        /**
         * Ensure an uploaded file is copied into the persistent lead uploads directory.
         *
         * @param string $file_path Absolute file path provided by Contact Form 7.
         * @param string $form_slug Current form slug.
         * @return string Normalised absolute path within the uploads directory when possible.
         */
        protected function ensure_uploaded_file_persisted( $file_path, $form_slug = '' ) {
            if ( empty( $file_path ) || ! is_string( $file_path ) ) {
                return '';
            }

            $file_path = wp_normalize_path( $file_path );

            if ( '' === $file_path ) {
                return '';
            }

            $uploads = wp_upload_dir();

            if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
                return $file_path;
            }

            $basedir = wp_normalize_path( trailingslashit( $uploads['basedir'] ) );

            if ( '' === $basedir ) {
                return $file_path;
            }

            if ( strpos( $file_path, $basedir ) === 0 ) {
                $relative_path = substr( $file_path, strlen( $basedir ) );
                $relative_path = ltrim( wp_normalize_path( $relative_path ), '/' );

                if ( '' !== $relative_path && false === strpos( $relative_path, '..' ) && 0 === strpos( $relative_path, 'theme-leads/' ) ) {
                    return $file_path;
                }
            }

            if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
                return '';
            }

            $storage_dir = $basedir . 'theme-leads/';
            $form_slug   = $this->normalise_option_form_slug( $form_slug );

            if ( '' !== $form_slug ) {
                $storage_dir .= $form_slug . '/';
            }

            wp_mkdir_p( $storage_dir );

            if ( ! is_dir( $storage_dir ) || ! wp_is_writable( $storage_dir ) ) {
                return $file_path;
            }

            $file_name = wp_basename( $file_path );

            if ( '' === $file_name ) {
                return '';
            }

            $unique_name = wp_unique_filename( $storage_dir, $file_name );
            $target_path = wp_normalize_path( trailingslashit( $storage_dir ) . $unique_name );

            if ( ! @copy( $file_path, $target_path ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
                return $file_path;
            }

            return $target_path;
        }

        /**
         * Prepare payload markup for display in the admin table.
         *
         * @param mixed $value Payload value.
         * @return string
         */
        protected function get_payload_value_display_markup( $value, $lead = null, $form_slug = '', $field_key = '' ) {
            if ( is_array( $value ) && isset( $value['files'] ) && is_array( $value['files'] ) ) {
                $parts = array();

                if ( array_key_exists( 'value', $value ) ) {
                    $text_value = $this->normalise_payload_value( $value['value'] );

                    if ( '' !== $text_value && ! $this->payload_value_is_uploaded_file_placeholder( $text_value ) ) {
                        $parts[] = esc_html( $text_value );
                    }
                }

                $files_markup = $this->build_uploaded_file_links_markup( $value['files'], $lead, $form_slug, $field_key );

                if ( '' !== $files_markup ) {
                    $parts[] = $files_markup;
                }

                if ( ! empty( $parts ) ) {
                    return implode( ' ', $parts );
                }

                return esc_html( $this->normalise_payload_value( $value ) );
            }

            if ( $this->payload_value_is_uploaded_file( $value ) ) {
                $link_markup = $this->build_uploaded_file_link_markup( $value, $lead, $form_slug, $field_key );

                if ( '' !== $link_markup ) {
                    return $link_markup;
                }
            }

            return esc_html( $this->normalise_payload_value( $value ) );
        }

        /**
         * Build markup for a list of uploaded files.
         *
         * @param array $files Uploaded files.
         * @return string
         */
        protected function build_uploaded_file_links_markup( $files, $lead = null, $form_slug = '', $field_key = '' ) {
            if ( empty( $files ) ) {
                return '';
            }

            if ( ! is_array( $files ) ) {
                $files = array( $files );
            }

            $links = array();

            foreach ( $files as $file_entry ) {
                $link_markup = $this->build_uploaded_file_link_markup( $file_entry, $lead, $form_slug, $field_key );

                if ( '' !== $link_markup ) {
                    $links[] = $link_markup;
                }
            }

            if ( empty( $links ) ) {
                return '';
            }

            return implode( ', ', $links );
        }

        /**
         * Build markup for a single uploaded file entry.
         *
         * @param array $file_entry Uploaded file entry.
         * @return string
         */
        protected function build_uploaded_file_link_markup( $file_entry, $lead = null, $form_slug = '', $field_key = '' ) {
            if ( ! $this->payload_value_is_uploaded_file( $file_entry ) ) {
                return '';
            }

            $url = $this->get_uploaded_file_download_url( $file_entry, $lead, $form_slug, $field_key );

            if ( '' === $url && isset( $file_entry['file_url'] ) ) {
                $url = $file_entry['file_url'];
            }

            if ( '' === $url ) {
                return '';
            }

            $label = isset( $file_entry['file_name'] ) && '' !== $file_entry['file_name'] ? $file_entry['file_name'] : $url;

            return '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $label ) . '</a>';
        }

        /**
         * Determine if the provided payload value represents an uploaded file entry.
         *
         * @param mixed $value Payload value.
         * @return bool
         */
        protected function payload_value_is_uploaded_file( $value ) {
            if ( ! is_array( $value ) ) {
                return false;
            }

            if ( ! empty( $value['relative_path'] ) && is_string( $value['relative_path'] ) ) {
                return true;
            }

            return ! empty( $value['file_url'] ) && is_string( $value['file_url'] );
        }

        /**
         * Determine whether a payload value represents the Contact Form 7 upload placeholder hash.
         *
         * @param mixed $value Payload value.
         * @return bool
         */
        protected function payload_value_is_uploaded_file_placeholder( $value ) {
            if ( ! is_string( $value ) ) {
                return false;
            }

            $candidate = trim( $value );

            if ( '' === $candidate ) {
                return false;
            }

            if ( strpbrk( $candidate, '/\\' ) !== false ) {
                return false;
            }

            $length = strlen( $candidate );

            if ( $length < 32 || $length > 128 ) {
                return false;
            }

            return (bool) preg_match( '/^[a-f0-9]{32,}$/i', $candidate );
        }

        /**
         * Generate a secure download URL for an uploaded file entry.
         *
         * @param array       $file_entry Uploaded file entry.
         * @param object|null $lead       Lead object.
         * @param string      $form_slug  Form slug.
         * @param string      $field_key  Submission field key.
         * @return string
         */
        protected function get_uploaded_file_download_url( $file_entry, $lead = null, $form_slug = '', $field_key = '' ) {
            $relative_path = $this->get_uploaded_file_relative_path_from_entry( $file_entry );

            if ( '' === $relative_path ) {
                return '';
            }

            $lead_id = ( is_object( $lead ) && isset( $lead->id ) ) ? absint( $lead->id ) : 0;

            if ( ! $lead_id || '' === $form_slug ) {
                return '';
            }

            $args = array(
                'action'  => 'theme_leads_download',
                'form'    => $form_slug,
                'lead_id' => $lead_id,
                'field'   => $field_key,
                'file'    => $relative_path,
                '_wpnonce' => wp_create_nonce( 'theme_leads_download_' . $lead_id ),
            );

            return add_query_arg( $args, admin_url( 'admin-post.php' ) );
        }

        /**
         * Determine the stored relative upload path for an entry.
         *
         * @param array $file_entry Uploaded file entry.
         * @return string
         */
        protected function get_uploaded_file_relative_path_from_entry( $file_entry ) {
            if ( isset( $file_entry['relative_path'] ) && '' !== $file_entry['relative_path'] ) {
                $relative = wp_normalize_path( $file_entry['relative_path'] );
                $relative = ltrim( $relative, '/' );

                if ( false === strpos( $relative, '..' ) ) {
                    return $relative;
                }
            }

            if ( isset( $file_entry['file_url'] ) && '' !== $file_entry['file_url'] ) {
                $relative = $this->convert_upload_url_to_relative_path( $file_entry['file_url'] );

                if ( '' !== $relative ) {
                    return $relative;
                }
            }

            return '';
        }

        /**
         * Convert a full upload URL into a relative path.
         *
         * @param string $url Upload URL.
         * @return string
         */
        protected function convert_upload_url_to_relative_path( $url ) {
            if ( empty( $url ) || ! is_string( $url ) ) {
                return '';
            }

            $uploads = wp_upload_dir();

            if ( ! empty( $uploads['error'] ) || empty( $uploads['baseurl'] ) ) {
                return '';
            }

            $relative_url = wp_make_link_relative( $url );
            $base_relative = wp_make_link_relative( trailingslashit( $uploads['baseurl'] ) );

            if ( empty( $relative_url ) || empty( $base_relative ) ) {
                return '';
            }

            if ( strpos( $relative_url, $base_relative ) !== 0 ) {
                return '';
            }

            $relative_path = substr( $relative_url, strlen( $base_relative ) );
            $relative_path = ltrim( $relative_path, '/' );
            $relative_path = wp_normalize_path( $relative_path );
            $relative_path = str_replace( '\\', '/', $relative_path );
            $relative_path = preg_replace( '#/+#', '/', $relative_path );

            if ( '' === $relative_path || false !== strpos( $relative_path, '..' ) ) {
                return '';
            }

            return $relative_path;
        }

        /**
         * Gather uploaded file entries from a payload value.
         *
         * @param mixed $payload Submission payload.
         * @return array
         */
        protected function extract_uploaded_file_entries_from_payload( $payload ) {
            $collected = array();

            if ( empty( $payload ) || ! is_array( $payload ) ) {
                return $collected;
            }

            foreach ( $payload as $value ) {
                $this->collect_uploaded_file_entries( $value, $collected );
            }

            return $collected;
        }

        /**
         * Recursively collect uploaded file entries from a value.
         *
         * @param mixed $value      Payload value.
         * @param array $collected  Reference to collected entries.
         */
        protected function collect_uploaded_file_entries( $value, &$collected ) {
            if ( $this->payload_value_is_uploaded_file( $value ) ) {
                $collected[] = $value;
                return;
            }

            if ( ! is_array( $value ) ) {
                return;
            }

            if ( isset( $value['files'] ) && is_array( $value['files'] ) ) {
                foreach ( $value['files'] as $file_entry ) {
                    $this->collect_uploaded_file_entries( $file_entry, $collected );
                }
                return;
            }

            foreach ( $value as $nested_value ) {
                $this->collect_uploaded_file_entries( $nested_value, $collected );
            }
        }

        /**
         * Delete all uploaded files referenced within a payload.
         *
         * @param mixed $payload Submission payload.
         */
        protected function delete_uploaded_files_from_payload( $payload ) {
            $entries = $this->extract_uploaded_file_entries_from_payload( $payload );

            if ( empty( $entries ) ) {
                return;
            }

            foreach ( $entries as $entry ) {
                $this->delete_single_uploaded_file_entry( $entry );
            }
        }

        /**
         * Delete a specific uploaded file entry from disk.
         *
         * @param array $file_entry Uploaded file entry.
         */
        protected function delete_single_uploaded_file_entry( $file_entry ) {
            $relative_path = $this->get_uploaded_file_relative_path_from_entry( $file_entry );

            if ( '' === $relative_path ) {
                return;
            }

            $absolute_path = $this->resolve_absolute_upload_path( $relative_path );

            if ( '' === $absolute_path || ! file_exists( $absolute_path ) ) {
                return;
            }

            wp_delete_file( $absolute_path );
        }

        /**
         * Resolve a relative upload path to an absolute file path.
         *
         * @param string $relative_path Relative upload path.
         * @return string
         */
        protected function resolve_absolute_upload_path( $relative_path ) {
            if ( '' === $relative_path ) {
                return '';
            }

            $uploads = wp_upload_dir();

            if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
                return '';
            }

            $normalized_relative = wp_normalize_path( $relative_path );
            $normalized_relative = ltrim( $normalized_relative, '/' );

            if ( '' === $normalized_relative || false !== strpos( $normalized_relative, '..' ) ) {
                return '';
            }

            $basedir = wp_normalize_path( trailingslashit( $uploads['basedir'] ) );
            $absolute_path = $basedir . $normalized_relative;

            if ( strpos( $absolute_path, $basedir ) !== 0 ) {
                return '';
            }

            return $absolute_path;
        }

        /**
         * Find a specific uploaded file entry within a payload.
         *
         * @param mixed  $payload       Submission payload.
         * @param string $field_key     Submission field key.
         * @param string $relative_path Relative file path.
         * @return array|null
         */
        protected function locate_uploaded_file_entry_in_payload( $payload, $field_key, $relative_path ) {
            if ( empty( $payload ) ) {
                return null;
            }

            if ( is_array( $payload ) && '' !== $field_key && isset( $payload[ $field_key ] ) ) {
                $match = $this->find_uploaded_file_entry_in_value( $payload[ $field_key ], $relative_path );

                if ( ! empty( $match ) ) {
                    return $match;
                }
            }

            if ( is_array( $payload ) ) {
                foreach ( $payload as $value ) {
                    $match = $this->find_uploaded_file_entry_in_value( $value, $relative_path );

                    if ( ! empty( $match ) ) {
                        return $match;
                    }
                }
            }

            return null;
        }

        /**
         * Recursively search for an uploaded file entry within a payload value.
         *
         * @param mixed  $value         Payload value.
         * @param string $relative_path Relative file path.
         * @return array|null
         */
        protected function find_uploaded_file_entry_in_value( $value, $relative_path ) {
            if ( $this->payload_value_is_uploaded_file( $value ) ) {
                $entry_relative = $this->get_uploaded_file_relative_path_from_entry( $value );

                if ( '' !== $entry_relative && $entry_relative === $relative_path ) {
                    return $value;
                }
            }

            if ( ! is_array( $value ) ) {
                return null;
            }

            if ( isset( $value['files'] ) && is_array( $value['files'] ) ) {
                foreach ( $value['files'] as $file_entry ) {
                    $match = $this->find_uploaded_file_entry_in_value( $file_entry, $relative_path );

                    if ( ! empty( $match ) ) {
                        return $match;
                    }
                }

                return null;
            }

            foreach ( $value as $nested_value ) {
                $match = $this->find_uploaded_file_entry_in_value( $nested_value, $relative_path );

                if ( ! empty( $match ) ) {
                    return $match;
                }
            }

            return null;
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
         * Retrieve the capability required to manage leads.
         *
         * @return string
         */
        protected function get_required_capability() {
            /**
             * Filters the capability required to access and manage leads.
             *
             * @param string $capability Capability name.
             */
            return apply_filters( 'theme_leads_required_capability', 'edit_posts' );
        }

        /**
         * Retrieve the default set of lead statuses.
         *
         * @return array
         */
        protected function get_default_status_definitions() {
            return array(
                array(
                    'slug'  => 'new',
                    'label' => __( 'New', 'wordprseo' ),
                ),
                array(
                    'slug'  => 'in-progress',
                    'label' => __( 'In progress', 'wordprseo' ),
                ),
                array(
                    'slug'  => 'won',
                    'label' => __( 'Won', 'wordprseo' ),
                ),
                array(
                    'slug'  => 'lost',
                    'label' => __( 'Lost', 'wordprseo' ),
                ),
                array(
                    'slug'  => 'archived',
                    'label' => __( 'Archived', 'wordprseo' ),
                ),
            );
        }

        /**
         * Retrieve the configured lead statuses.
         *
         * @return array
         */
        protected function get_status_definitions( $form_slug = '' ) {
            $stored      = $this->get_option_for_form( 'statuses', array(), $form_slug );
            $definitions = array();

            if ( is_array( $stored ) ) {
                $used_slugs = array();

                foreach ( $stored as $entry ) {
                    $label = '';
                    $slug  = '';

                    if ( is_array( $entry ) ) {
                        $label = isset( $entry['label'] ) ? sanitize_text_field( $entry['label'] ) : '';
                        $slug  = isset( $entry['slug'] ) ? sanitize_title_with_dashes( $entry['slug'] ) : '';
                    } elseif ( is_string( $entry ) ) {
                        $label = sanitize_text_field( $entry );
                        $slug  = sanitize_title_with_dashes( $entry );
                    }

                    $label = trim( preg_replace( '/\s+/', ' ', $label ) );

                    if ( '' === $label ) {
                        continue;
                    }

                    if ( '' === $slug ) {
                        $slug = sanitize_title_with_dashes( $label );
                    }

                    if ( '' === $slug ) {
                        continue;
                    }

                    $original_slug = $slug;
                    $counter       = 2;

                    while ( in_array( $slug, $used_slugs, true ) ) {
                        $slug = $original_slug . '-' . $counter;
                        $counter++;
                    }

                    $used_slugs[]  = $slug;
                    $definitions[] = array(
                        'slug'  => $slug,
                        'label' => $label,
                    );
                }
            }

            if ( empty( $definitions ) ) {
                $definitions = $this->get_default_status_definitions();
            }

            return array_values( $definitions );
        }

        /**
         * Persist lead status definitions.
         *
         * @param array $definitions Status definitions.
         * @param string $form_slug   Form slug.
         */
        protected function save_status_definitions( $definitions, $form_slug = '' ) {
            $this->update_module_option( 'statuses', array_values( $definitions ), $form_slug );
        }

        /**
         * Build a map of status slugs to labels.
         *
         * @param array|null $definitions Optional status definition list.
         * @param string     $form_slug   Form slug.
         * @return array
         */
        protected function get_status_labels_map( $definitions = null, $form_slug = '' ) {
            if ( null === $definitions ) {
                $definitions = $this->get_status_definitions( $form_slug );
            }

            $map = array();

            foreach ( $definitions as $definition ) {
                $slug  = isset( $definition['slug'] ) ? sanitize_title_with_dashes( $definition['slug'] ) : '';
                $label = isset( $definition['label'] ) ? sanitize_text_field( $definition['label'] ) : $slug;

                if ( '' === $slug ) {
                    continue;
                }

                $map[ $slug ] = $label;
            }

            return $map;
        }

        /**
         * Retrieve the human readable label for a status slug.
         *
         * @param string $status Status slug.
         * @return string
         */
        protected function get_status_label( $status, $form_slug = '' ) {
            $status = sanitize_title_with_dashes( $status );

            if ( '' === $status ) {
                return '';
            }

            $map = $this->get_status_labels_map( null, $form_slug );

            if ( isset( $map[ $status ] ) ) {
                return $map[ $status ];
            }

            return ucwords( str_replace( array( '-', '_' ), ' ', $status ) );
        }

        /**
         * Determine the default status slug.
         *
         * @return string
         */
        protected function get_default_status_slug( $form_slug = '' ) {
            $definitions = $this->get_status_definitions( $form_slug );

            if ( ! empty( $definitions ) ) {
                $first = reset( $definitions );
                if ( isset( $first['slug'] ) && '' !== $first['slug'] ) {
                    return sanitize_title_with_dashes( $first['slug'] );
                }
            }

            return 'new';
        }

        /**
         * Normalise raw status labels into definition objects.
         *
         * @param array $raw_statuses Raw status label list.
         * @return array
         */
        protected function normalise_status_definitions( $raw_statuses ) {
            if ( empty( $raw_statuses ) || ! is_array( $raw_statuses ) ) {
                return array();
            }

            $definitions = array();
            $used_slugs  = array();

            foreach ( $raw_statuses as $raw_status ) {
                $label = is_string( $raw_status ) ? sanitize_text_field( $raw_status ) : '';
                $label = trim( preg_replace( '/\s+/', ' ', $label ) );

                if ( '' === $label ) {
                    continue;
                }

                $slug = sanitize_title_with_dashes( $label );

                if ( '' === $slug ) {
                    continue;
                }

                $base_slug = $slug;
                $counter   = 2;

                while ( in_array( $slug, $used_slugs, true ) ) {
                    $slug = $base_slug . '-' . $counter;
                    $counter++;
                }

                $used_slugs[]  = $slug;
                $definitions[] = array(
                    'slug'  => $slug,
                    'label' => $label,
                );
            }

            return $definitions;
        }

        /**
         * Prepare status data for client-side scripts.
         *
         * @param array $definitions Status definitions.
         * @return array
         */
        protected function prepare_statuses_for_js( $definitions ) {
            $items = array();
            $map   = array();

            foreach ( $definitions as $definition ) {
                $slug  = isset( $definition['slug'] ) ? sanitize_title_with_dashes( $definition['slug'] ) : '';
                $label = isset( $definition['label'] ) ? sanitize_text_field( $definition['label'] ) : $slug;

                if ( '' === $slug ) {
                    continue;
                }

                $items[]     = array(
                    'slug'  => $slug,
                    'label' => $label,
                );
                $map[ $slug ] = $label;
            }

            $default = ! empty( $items ) ? $items[0]['slug'] : 'new';

            return array(
                'items'   => $items,
                'map'     => $map,
                'default' => $default,
            );
        }

        /**
         * Generate textarea content for the status form.
         *
         * @param array $definitions Status definitions.
         * @return string
         */
        protected function get_status_textarea_value( $definitions ) {
            $labels = array();

            foreach ( $definitions as $definition ) {
                if ( isset( $definition['label'] ) ) {
                    $labels[] = sanitize_text_field( $definition['label'] );
                }
            }

            return implode( "\n", $labels );
        }

        /**
         * Ensure a status value is valid, falling back to the default slug when required.
         *
         * @param string $status Raw status value.
         * @return string
         */
        protected function sanitise_status_value( $status, $form_slug = '' ) {
            $status = sanitize_title_with_dashes( $status );

            $map = $this->get_status_labels_map( null, $form_slug );

            if ( isset( $map[ $status ] ) ) {
                return $status;
            }

            return $this->get_default_status_slug( $form_slug );
        }

        /**
         * Retrieve the configured default CC addresses.
         *
         * @return array
         */
        protected function get_default_cc_addresses( $form_slug = '' ) {
            $stored = $this->get_option_for_form( 'default_cc', array(), $form_slug );

            if ( empty( $stored ) ) {
                return array();
            }

            if ( ! is_array( $stored ) ) {
                $stored = array( $stored );
            }

            return $this->parse_email_list( $stored );
        }

        /**
         * Persist the default CC address list.
         *
         * @param array|string $addresses Address list.
         */
        protected function save_default_cc_addresses( $addresses, $form_slug = '' ) {
            $this->update_module_option( 'default_cc', $this->parse_email_list( $addresses ), $form_slug );
        }

        /**
         * Prepare default CC data for client-side scripts.
         *
         * @param array $addresses Address list.
         * @return array
         */
        protected function prepare_default_cc_for_js( $addresses ) {
            $emails = $this->parse_email_list( $addresses );

            return array(
                'list'    => $emails,
                'display' => implode( ', ', $emails ),
            );
        }

        /**
         * Combine two email lists and ensure they are unique.
         *
         * @param array $primary   Primary list.
         * @param array $secondary Secondary list.
         * @return array
         */
        protected function merge_email_lists( $primary, $secondary ) {
            $merged = array();

            foreach ( array_merge( (array) $primary, (array) $secondary ) as $email ) {
                $clean = sanitize_email( $email );
                if ( ! empty( $clean ) && ! in_array( $clean, $merged, true ) ) {
                    $merged[] = $clean;
                }
            }

            return $merged;
        }

        /**
         * Normalise a list of email addresses from mixed input.
         *
         * @param array|string $value Raw list.
         * @return array
         */
        protected function parse_email_list( $value ) {
            if ( is_array( $value ) ) {
                $parts = $value;
            } else {
                $parts = preg_split( '/[\r\n,;]+/', (string) $value );
            }

            $emails = array();

            if ( empty( $parts ) || ! is_array( $parts ) ) {
                return $emails;
            }

            foreach ( $parts as $part ) {
                $email = sanitize_email( trim( wp_strip_all_tags( (string) $part ) ) );
                if ( ! empty( $email ) && is_email( $email ) && ! in_array( $email, $emails, true ) ) {
                    $emails[] = $email;
                }
            }

            return array_values( $emails );
        }

        /**
         * Retrieve stored SMTP mailer settings.
         *
         * @return array
         */
        protected function get_mailer_settings( $form_slug = '' ) {
            $stored = $this->get_option_for_form( 'mailer_settings', array(), $form_slug );

            if ( ! is_array( $stored ) ) {
                $stored = array();
            }

            $defaults = array(
                'host'       => '',
                'port'       => '',
                'encryption' => '',
                'username'   => '',
                'password'   => '',
                'from_email' => '',
                'from_name'  => '',
            );

            $settings = wp_parse_args( $stored, $defaults );

            return $this->sanitise_mailer_settings( $settings );
        }

        /**
         * Persist SMTP mailer settings.
         *
         * @param array $settings Raw settings.
         */
        protected function save_mailer_settings( $settings, $form_slug = '' ) {
            $sanitised = $this->sanitise_mailer_settings( $settings );
            $this->update_module_option( 'mailer_settings', $sanitised, $form_slug );
        }

        /**
         * Sanitise SMTP settings input.
         *
         * @param array $raw_settings Raw settings.
         * @return array
         */
        protected function sanitise_mailer_settings( $raw_settings ) {
            if ( ! is_array( $raw_settings ) ) {
                $raw_settings = array();
            }

            $host = isset( $raw_settings['host'] ) ? sanitize_text_field( wp_unslash( $raw_settings['host'] ) ) : '';
            $port = isset( $raw_settings['port'] ) ? absint( $raw_settings['port'] ) : 0;
            $encryption = isset( $raw_settings['encryption'] ) ? sanitize_key( wp_unslash( $raw_settings['encryption'] ) ) : '';

            if ( ! in_array( $encryption, array( 'ssl', 'tls' ), true ) ) {
                $encryption = '';
            }

            $username_raw  = isset( $raw_settings['username'] ) ? wp_unslash( $raw_settings['username'] ) : '';
            $username_email = sanitize_email( $username_raw );
            $username       = $username_email ? $username_email : sanitize_text_field( $username_raw );

            $password = isset( $raw_settings['password'] ) ? $this->sanitise_mailer_password( $raw_settings['password'] ) : '';

            $from_email_raw = isset( $raw_settings['from_email'] ) ? wp_unslash( $raw_settings['from_email'] ) : '';
            $from_email     = sanitize_email( $from_email_raw );

            $from_name = isset( $raw_settings['from_name'] ) ? sanitize_text_field( wp_unslash( $raw_settings['from_name'] ) ) : '';

            return array(
                'host'       => $host,
                'port'       => $port ? (string) $port : '',
                'encryption' => $encryption,
                'username'   => $username,
                'password'   => $password,
                'from_email' => $from_email,
                'from_name'  => $from_name,
            );
        }

        /**
         * Sanitise the SMTP password while retaining special characters.
         *
         * @param string $password Raw password input.
         * @return string
         */
        protected function sanitise_mailer_password( $password ) {
            $password = (string) wp_unslash( $password );
            $password = preg_replace( '/[\r\n]+/', '', $password );
            $password = wp_strip_all_tags( $password );

            return trim( $password );
        }

        /**
         * Prepare mailer settings for client-side scripts.
         *
         * @param array $settings Raw settings.
         * @return array
         */
        protected function prepare_mailer_settings_for_js( $settings ) {
            $sanitised = $this->sanitise_mailer_settings( $settings );

            return array(
                'host'       => $sanitised['host'],
                'port'       => $sanitised['port'],
                'encryption' => $sanitised['encryption'],
                'username'   => $sanitised['username'],
                'password'   => $sanitised['password'],
                'from_email' => $sanitised['from_email'],
                'from_name'  => $sanitised['from_name'],
            );
        }

        /**
         * Determine whether custom SMTP settings should be applied.
         *
         * @param array $settings Mailer settings.
         * @return bool
         */
        protected function should_use_custom_mailer( $settings ) {
            $sanitised = $this->sanitise_mailer_settings( $settings );

            return ! empty( $sanitised['host'] ) && ! empty( $sanitised['username'] ) && ! empty( $sanitised['password'] );
        }

        /**
         * Configure PHPMailer with custom SMTP details for lead responses.
         *
         * @param PHPMailer $phpmailer PHPMailer instance.
         */
        public function configure_phpmailer( $phpmailer ) {
            if ( empty( $this->active_mailer_settings ) || ! is_object( $phpmailer ) ) {
                return;
            }

            $settings = $this->sanitise_mailer_settings( $this->active_mailer_settings );

            if ( empty( $settings['host'] ) || empty( $settings['username'] ) || empty( $settings['password'] ) ) {
                return;
            }

            $phpmailer->isSMTP();
            $phpmailer->Host       = $settings['host'];
            $phpmailer->SMTPAuth   = true;
            $phpmailer->Username   = $settings['username'];
            $phpmailer->Password   = $settings['password'];
            $phpmailer->Port       = $settings['port'] ? absint( $settings['port'] ) : 587;
            $phpmailer->SMTPAutoTLS = true;

            if ( 'ssl' === $settings['encryption'] ) {
                if ( class_exists( '\PHPMailer\PHPMailer\PHPMailer' ) ) {
                    $phpmailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                } else {
                    $phpmailer->SMTPSecure = 'ssl';
                }
            } elseif ( 'tls' === $settings['encryption'] ) {
                if ( class_exists( '\PHPMailer\PHPMailer\PHPMailer' ) ) {
                    $phpmailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                } else {
                    $phpmailer->SMTPSecure = 'tls';
                }
            } else {
                $phpmailer->SMTPSecure = '';
                $phpmailer->SMTPAutoTLS = false;
            }

            $from_email = '';

            if ( ! empty( $settings['from_email'] ) && is_email( $settings['from_email'] ) ) {
                $from_email = $settings['from_email'];
            } elseif ( ! empty( $settings['username'] ) && is_email( $settings['username'] ) ) {
                $from_email = $settings['username'];
            }

            $from_name = $settings['from_name'];

            if ( $from_email ) {
                try {
                    $phpmailer->setFrom( $from_email, $from_name, false );
                } catch ( Exception $e ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                    // If PHPMailer throws, continue with existing values.
                }
                $phpmailer->From = $from_email;
                if ( ! empty( $from_name ) ) {
                    $phpmailer->FromName = $from_name;
                }
            }
        }

        /**
         * Register the leads management page in the admin area.
         */
        public function register_menu() {
            add_menu_page(
                __( 'Leads', 'wordprseo' ),
                __( 'Leads', 'wordprseo' ),
                $this->get_required_capability(),
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
            if ( ! current_user_can( $this->get_required_capability() ) ) {
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
         * Handle generic AJAX lead updates.
         */
        public function handle_ajax_update_lead() {
            if ( ! current_user_can( $this->get_required_capability() ) ) {
                wp_send_json_error(
                    array( 'message' => esc_html__( 'You do not have permission to perform this action.', 'wordprseo' ) ),
                    403
                );
            }

            check_ajax_referer( 'theme_leads_update' );

            $request = $_POST;

            if ( empty( $request['lead_submit_action'] ) ) {
                $request['lead_submit_action'] = 'save';
            }

            $result = $this->process_lead_update( $request );

            if ( is_wp_error( $result ) ) {
                wp_send_json_error( array( 'message' => $result->get_error_message() ) );
            }

            wp_send_json_success( $result );
        }

        /**
         * Handle AJAX email sends without a page refresh.
         */
        public function handle_ajax_send_email() {
            $_POST['lead_submit_action'] = 'send';
            $this->handle_ajax_update_lead();
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

            $table = $this->resolve_table_name_for_slug( $form_slug );

            if ( empty( $table ) || ! $this->table_exists( $table ) ) {
                return new WP_Error( 'theme_leads_missing_table', __( 'The lead storage table could not be found.', 'wordprseo' ) );
            }

            // Ensure the table schema is up to date before attempting to store additional lead details.
            $this->maybe_create_table( $table, $form_slug );

            $contact_form    = $this->get_contact_form_by_slug( $form_slug );
            $form_field_keys = $this->get_form_field_keys( $contact_form );

            $contact_form    = $this->get_contact_form_by_slug( $form_slug );
            $form_field_keys = $this->get_form_field_keys( $contact_form );

            $status_input  = isset( $request['lead_status'] ) ? wp_unslash( $request['lead_status'] ) : '';
            $status        = $this->sanitise_status_value( $status_input, $form_slug );
            $submit_action = isset( $request['lead_submit_action'] ) ? sanitize_key( wp_unslash( $request['lead_submit_action'] ) ) : 'save';

            $has_client_name  = array_key_exists( 'lead_client_name', $request );
            $has_client_phone = array_key_exists( 'lead_client_phone', $request );
            $has_client_link  = array_key_exists( 'lead_client_link', $request );
            $has_brand        = array_key_exists( 'lead_brand', $request );
            $has_subject      = array_key_exists( 'lead_reply_subject', $request );
            $has_message      = array_key_exists( 'lead_reply', $request );
            $has_template     = array_key_exists( 'lead_template', $request );
            $has_recipients   = array_key_exists( 'lead_client_emails', $request );

            $client_name  = $has_client_name ? sanitize_text_field( wp_unslash( $request['lead_client_name'] ) ) : null;
            $client_phone = $has_client_phone ? sanitize_text_field( wp_unslash( $request['lead_client_phone'] ) ) : null;
            $client_link  = $has_client_link ? $this->normalise_link_value( wp_unslash( $request['lead_client_link'] ) ) : null;
            $client_brand = $has_brand ? sanitize_text_field( wp_unslash( $request['lead_brand'] ) ) : null;
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
            $resolved_brand        = null !== $client_brand ? $client_brand : ( ! empty( $lead->response_brand ) ? $lead->response_brand : $this->extract_brand_from_payload( $payload ) );
            $resolved_link         = null !== $client_link ? $client_link : ( ! empty( $lead->response_link ) ? $lead->response_link : $this->extract_link_from_payload( $payload ) );
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

            if ( $has_client_link ) {
                $data['response_link'] = $client_link;
                $format[]              = '%s';
            }

            if ( $has_brand ) {
                $data['response_brand'] = $client_brand;
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

                $template = ! empty( $resolved_template ) ? $this->get_template( $resolved_template, $form_slug ) : null;

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

                $default_cc_addresses = $this->get_default_cc_addresses( $form_slug );
                if ( ! empty( $default_cc_addresses ) ) {
                    foreach ( $default_cc_addresses as $cc_email ) {
                        if ( ! in_array( $cc_email, $recipient_emails, true ) ) {
                            $recipient_emails[] = $cc_email;
                        }
                    }
                }

                $prepared_subject    = $this->apply_template_placeholders( $resolved_subject, $lead, $payload, $resolved_client_name, $resolved_client_phone, $resolved_brand, $resolved_link, $recipient_emails, $form_slug );
                $prepared_message    = $this->apply_template_placeholders( $resolved_message, $lead, $payload, $resolved_client_name, $resolved_client_phone, $resolved_brand, $resolved_link, $recipient_emails, $form_slug );
                $prepared_recipients = $recipient_emails;

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

                $mailer_settings   = $this->get_mailer_settings( $form_slug );
                $use_custom_mailer = $this->should_use_custom_mailer( $mailer_settings );

                if ( $use_custom_mailer ) {
                    if ( ! empty( $mailer_settings['from_email'] ) && is_email( $mailer_settings['from_email'] ) ) {
                        $sender_email = $mailer_settings['from_email'];
                    } elseif ( ! empty( $mailer_settings['username'] ) && is_email( $mailer_settings['username'] ) ) {
                        $sender_email = $mailer_settings['username'];
                    }

                    if ( ! empty( $mailer_settings['from_name'] ) ) {
                        $sender_name = $mailer_settings['from_name'];
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

                $custom_mailer_applied = false;

                if ( $use_custom_mailer ) {
                    $this->active_mailer_settings = $mailer_settings;
                    add_action( 'phpmailer_init', array( $this, 'configure_phpmailer' ) );
                    $custom_mailer_applied = true;
                }

                $email_sent = wp_mail( $to, $prepared_subject, wpautop( $prepared_message ), $headers );

                if ( $custom_mailer_applied ) {
                    remove_action( 'phpmailer_init', array( $this, 'configure_phpmailer' ) );
                    $this->active_mailer_settings = null;
                }

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
            $updated_context     = $this->build_template_context_data( $updated_lead, $updated_payload, $form_field_keys, $form_slug );
            $response_history    = $this->get_response_history_markup( $updated_lead );
            $summary_name        = ! empty( $updated_lead->response_client_name ) ? $updated_lead->response_client_name : $this->extract_contact_name( $updated_payload );
            $summary_status      = $this->get_status_label( $updated_lead->status, $form_slug );
            $summary_last_reply  = ! empty( $updated_lead->response_sent ) ? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $updated_lead->response_sent ) : '';
            $recipients_display  = $this->format_recipient_list_for_display( $updated_lead->response_recipients );
            $response_sent_value = ! empty( $updated_lead->response_sent ) ? $updated_lead->response_sent : $response_sent_at;

            return array(
                'form_slug'      => $form_slug,
                'lead_id'        => $lead_id,
                'status'         => $updated_lead->status,
                'note'           => $updated_lead->note,
                'brand'          => $updated_lead->response_brand,
                'link'           => $updated_lead->response_link,
                'template'       => $updated_lead->response_template,
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
                    'status_slug'   => $updated_lead->status,
                    'last_response' => $summary_last_reply,
                ),
                'history_markup' => $response_history,
                'email_sent'     => $email_sent,
            );
        }

        /**
         * Handle admin deletion of a lead entry.
         */
        public function handle_bulk_action() {
            if ( ! current_user_can( $this->get_required_capability() ) ) {
                wp_die( esc_html__( 'You do not have permission to access this page.', 'wordprseo' ) );
            }

            check_admin_referer( 'theme_leads_bulk' );

            $form_slug = isset( $_POST['form_slug'] ) ? sanitize_key( wp_unslash( $_POST['form_slug'] ) ) : '';
            $action    = isset( $_POST['bulk_action'] ) ? sanitize_key( wp_unslash( $_POST['bulk_action'] ) ) : '';
            $lead_ids  = isset( $_POST['selected_leads'] ) ? wp_parse_id_list( wp_unslash( $_POST['selected_leads'] ) ) : array();

            if ( '' === $form_slug || empty( $lead_ids ) ) {
                wp_safe_redirect( admin_url( 'admin.php?page=theme-leads' ) );
                exit;
            }

            $redirect = admin_url( 'admin.php?page=theme-leads&form=' . $form_slug );

            if ( 'delete' === $action ) {
                $this->bulk_delete_leads( $form_slug, $lead_ids );
                wp_safe_redirect( $redirect );
                exit;
            }

            if ( 'status' === $action ) {
                $status_input = isset( $_POST['bulk_status'] ) ? wp_unslash( $_POST['bulk_status'] ) : '';
                $status_slug  = sanitize_title_with_dashes( $status_input );
                $labels_map   = $this->get_status_labels_map( null, $form_slug );

                if ( '' === $status_slug || ! isset( $labels_map[ $status_slug ] ) ) {
                    wp_safe_redirect( $redirect );
                    exit;
                }

                $this->bulk_update_lead_statuses( $form_slug, $lead_ids, $status_slug );
            }

            wp_safe_redirect( $redirect );
            exit;
        }

        /**
         * Handle admin deletion of a lead entry.
         */
        public function handle_delete() {
            if ( ! current_user_can( $this->get_required_capability() ) ) {
                wp_die( esc_html__( 'You do not have permission to access this page.', 'wordprseo' ) );
            }

            check_admin_referer( 'theme_leads_delete' );

            $form_slug = isset( $_POST['form_slug'] ) ? sanitize_key( wp_unslash( $_POST['form_slug'] ) ) : '';
            $lead_id   = isset( $_POST['lead_id'] ) ? absint( $_POST['lead_id'] ) : 0;

            if ( empty( $form_slug ) || ! $lead_id ) {
                wp_safe_redirect( admin_url( 'admin.php?page=theme-leads' ) );
                exit;
            }

            $this->bulk_delete_leads( $form_slug, array( $lead_id ) );

            wp_safe_redirect( admin_url( 'admin.php?page=theme-leads&form=' . $form_slug ) );
            exit;
        }

        /**
         * Delete one or more leads associated with a form slug.
         *
         * @param string $form_slug Form slug.
         * @param array  $lead_ids  Lead IDs to delete.
         */
        protected function bulk_delete_leads( $form_slug, $lead_ids ) {
            global $wpdb;

            $form_slug = sanitize_key( $form_slug );
            $lead_ids  = wp_parse_id_list( $lead_ids );

            if ( empty( $form_slug ) || empty( $lead_ids ) ) {
                return;
            }

            $table = $this->resolve_table_name_for_slug( $form_slug );

            if ( empty( $table ) || ! $this->table_exists( $table ) ) {
                return;
            }

            $this->maybe_create_table( $table, $form_slug );

            $placeholders = implode( ',', array_fill( 0, count( $lead_ids ), '%d' ) );

            $select_query = $wpdb->prepare( "SELECT id, payload FROM {$table} WHERE id IN ({$placeholders})", $lead_ids );
            $results      = $select_query ? $wpdb->get_results( $select_query ) : array();

            if ( ! empty( $results ) ) {
                foreach ( $results as $lead ) {
                    if ( isset( $lead->payload ) ) {
                        $payload = $this->normalise_payload( $lead->payload );
                        $this->delete_uploaded_files_from_payload( $payload );
                    }
                }
            }

            $delete_query = $wpdb->prepare( "DELETE FROM {$table} WHERE id IN ({$placeholders})", $lead_ids );

            if ( $delete_query ) {
                $wpdb->query( $delete_query );
            }
        }

        /**
         * Bulk update lead statuses for a form slug.
         *
         * @param string $form_slug Form slug.
         * @param array  $lead_ids  Lead IDs to update.
         * @param string $status    Status slug to apply.
         */
        protected function bulk_update_lead_statuses( $form_slug, $lead_ids, $status ) {
            global $wpdb;

            $form_slug = sanitize_key( $form_slug );
            $status    = sanitize_title_with_dashes( $status );
            $lead_ids  = wp_parse_id_list( $lead_ids );

            if ( empty( $form_slug ) || empty( $lead_ids ) || '' === $status ) {
                return;
            }

            $table = $this->resolve_table_name_for_slug( $form_slug );

            if ( empty( $table ) || ! $this->table_exists( $table ) ) {
                return;
            }

            $this->maybe_create_table( $table, $form_slug );

            $placeholders = implode( ',', array_fill( 0, count( $lead_ids ), '%d' ) );
            $params       = array_merge( array( $status ), $lead_ids );

            $update_query = $wpdb->prepare( "UPDATE {$table} SET status = %s WHERE id IN ({$placeholders})", $params );

            if ( $update_query ) {
                $wpdb->query( $update_query );
            }
        }

        /**
         * Stream an uploaded file to the browser for authorised users.
         */
        public function handle_uploaded_file_download() {
            if ( ! current_user_can( $this->get_required_capability() ) ) {
                wp_die( esc_html__( 'You do not have permission to access this page.', 'wordprseo' ) );
            }

            $lead_id   = isset( $_GET['lead_id'] ) ? absint( $_GET['lead_id'] ) : 0;
            $form_slug = isset( $_GET['form'] ) ? sanitize_key( wp_unslash( $_GET['form'] ) ) : '';
            $field_key = isset( $_GET['field'] ) ? sanitize_text_field( wp_unslash( $_GET['field'] ) ) : '';
            $nonce     = isset( $_GET['_wpnonce'] ) ? wp_unslash( $_GET['_wpnonce'] ) : '';
            $file_arg  = isset( $_GET['file'] ) ? wp_unslash( $_GET['file'] ) : '';

            if ( ! $lead_id || '' === $form_slug || '' === $file_arg ) {
                wp_die( esc_html__( 'The requested file could not be found.', 'wordprseo' ) );
            }

            if ( ! wp_verify_nonce( $nonce, 'theme_leads_download_' . $lead_id ) ) {
                wp_die( esc_html__( 'The requested file could not be found.', 'wordprseo' ) );
            }

            $relative_path = wp_normalize_path( $file_arg );
            $relative_path = ltrim( $relative_path, '/' );
            $relative_path = preg_replace( '#/+#', '/', $relative_path );

            if ( '' === $relative_path || false !== strpos( $relative_path, '..' ) ) {
                wp_die( esc_html__( 'The requested file could not be found.', 'wordprseo' ) );
            }

            global $wpdb;

            $table = $this->resolve_table_name_for_slug( $form_slug );

            if ( empty( $table ) || ! $this->table_exists( $table ) ) {
                wp_die( esc_html__( 'The requested file could not be found.', 'wordprseo' ) );
            }

            $lead = $wpdb->get_row( $wpdb->prepare( "SELECT payload FROM {$table} WHERE id = %d", $lead_id ) );

            if ( ! $lead || ! isset( $lead->payload ) ) {
                wp_die( esc_html__( 'The requested file could not be found.', 'wordprseo' ) );
            }

            $payload = $this->normalise_payload( $lead->payload );
            $file_entry = $this->locate_uploaded_file_entry_in_payload( $payload, $field_key, $relative_path );

            if ( empty( $file_entry ) ) {
                wp_die( esc_html__( 'The requested file could not be found.', 'wordprseo' ) );
            }

            $absolute_path = $this->resolve_absolute_upload_path( $relative_path );

            if ( '' === $absolute_path || ! file_exists( $absolute_path ) || ! is_readable( $absolute_path ) ) {
                wp_die( esc_html__( 'The requested file could not be found.', 'wordprseo' ) );
            }

            $file_name = isset( $file_entry['file_name'] ) && '' !== $file_entry['file_name'] ? $file_entry['file_name'] : wp_basename( $absolute_path );
            $file_name = sanitize_file_name( $file_name );

            if ( '' === $file_name ) {
                $file_name = wp_basename( $absolute_path );
            }

            $filetype    = wp_check_filetype( $absolute_path );
            $content_type = ! empty( $filetype['type'] ) ? $filetype['type'] : 'application/octet-stream';

            if ( ob_get_length() ) {
                ob_end_clean();
            }

            $sanitised_name = str_replace( '"', '', $file_name );

            nocache_headers();
            status_header( 200 );
            header( 'Content-Type: ' . $content_type );
            header( 'Content-Disposition: inline; filename="' . $sanitised_name . '"' );

            $file_size = @filesize( $absolute_path );

            if ( false !== $file_size ) {
                header( 'Content-Length: ' . $file_size );
            }

            readfile( $absolute_path );
            exit;
        }

        /**
         * Handle saving (creating/updating) a response template.
         */
        public function handle_template_save() {
            if ( ! current_user_can( $this->get_required_capability() ) ) {
                wp_die( esc_html__( 'You do not have permission to access this page.', 'wordprseo' ) );
            }

            check_admin_referer( 'theme_leads_template_save' );

            $result = $this->process_template_save_request( $_POST );

            $redirect = wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=theme-leads' );

            if ( is_wp_error( $result ) ) {
                wp_safe_redirect( $redirect );
                exit;
            }

            wp_safe_redirect( $redirect );
            exit;
        }

        /**
         * Handle deleting a response template.
         */
        public function handle_template_delete() {
            if ( ! current_user_can( $this->get_required_capability() ) ) {
                wp_die( esc_html__( 'You do not have permission to access this page.', 'wordprseo' ) );
            }

            check_admin_referer( 'theme_leads_template_delete' );

            $result = $this->process_template_delete_request( $_POST );

            $redirect = wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=theme-leads' );

            if ( is_wp_error( $result ) ) {
                wp_safe_redirect( $redirect );
                exit;
            }

            wp_safe_redirect( $redirect );
            exit;
        }

        /**
         * Handle AJAX template save requests.
         */
        public function handle_ajax_template_save() {
            if ( ! current_user_can( $this->get_required_capability() ) ) {
                wp_send_json_error(
                    array( 'message' => esc_html__( 'You do not have permission to perform this action.', 'wordprseo' ) ),
                    403
                );
            }

            check_ajax_referer( 'theme_leads_template_save' );

            $result = $this->process_template_save_request( $_POST );

            if ( is_wp_error( $result ) ) {
                wp_send_json_error( array( 'message' => $result->get_error_message() ) );
            }

            wp_send_json_success( $result );
        }

        /**
         * Handle AJAX template delete requests.
         */
        public function handle_ajax_template_delete() {
            if ( ! current_user_can( $this->get_required_capability() ) ) {
                wp_send_json_error(
                    array( 'message' => esc_html__( 'You do not have permission to perform this action.', 'wordprseo' ) ),
                    403
                );
            }

            check_ajax_referer( 'theme_leads_template_delete' );

            $result = $this->process_template_delete_request( $_POST );

            if ( is_wp_error( $result ) ) {
                wp_send_json_error( array( 'message' => $result->get_error_message() ) );
            }

            wp_send_json_success( $result );
        }

        /**
         * Handle default CC form submissions.
         */
        public function handle_default_cc_save() {
            if ( ! current_user_can( $this->get_required_capability() ) ) {
                wp_die( esc_html__( 'You do not have permission to access this page.', 'wordprseo' ) );
            }

            check_admin_referer( 'theme_leads_default_cc_save' );

            $result = $this->process_default_cc_save_request( $_POST );

            $args = array( 'page' => 'theme-leads' );

            $current_form = isset( $_POST['current_form'] ) ? sanitize_key( wp_unslash( $_POST['current_form'] ) ) : '';
            if ( ! empty( $current_form ) ) {
                $args['form'] = $current_form;
            }

            if ( is_wp_error( $result ) ) {
                $args['theme_leads_notice']         = 'default_cc_error';
                $args['theme_leads_notice_message'] = rawurlencode( $result->get_error_message() );
            } else {
                $args['theme_leads_notice'] = 'default_cc_saved';
            }

            wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
            exit;
        }

        /**
         * Handle AJAX requests for default CC updates.
         */
        public function handle_ajax_default_cc_save() {
            if ( ! current_user_can( $this->get_required_capability() ) ) {
                wp_send_json_error(
                    array( 'message' => esc_html__( 'You do not have permission to perform this action.', 'wordprseo' ) ),
                    403
                );
            }

            check_ajax_referer( 'theme_leads_default_cc_save' );

            $result = $this->process_default_cc_save_request( $_POST );

            if ( is_wp_error( $result ) ) {
                wp_send_json_error( array( 'message' => $result->get_error_message() ) );
            }

            $result['save_nonce'] = wp_create_nonce( 'theme_leads_default_cc_save' );

            wp_send_json_success( $result );
        }

        /**
         * Normalise and persist default CC address updates.
         *
         * @param array $request Raw request data.
         * @return array|WP_Error
         */
        protected function process_default_cc_save_request( $request ) {
            $raw_input = isset( $request['lead_default_cc'] ) ? wp_unslash( $request['lead_default_cc'] ) : '';
            $form_slug = '';

            if ( isset( $request['current_form'] ) ) {
                $form_slug = sanitize_key( wp_unslash( $request['current_form'] ) );
            } elseif ( isset( $request['form_slug'] ) ) {
                $form_slug = sanitize_key( wp_unslash( $request['form_slug'] ) );
            }

            $addresses = $this->parse_email_list( $raw_input );

            $this->save_default_cc_addresses( $addresses, $form_slug );

            return array(
                'cc'        => $this->prepare_default_cc_for_js( $addresses ),
                'textarea'  => implode( "\n", $addresses ),
                'form_slug' => $form_slug,
            );
        }

        /**
         * Normalise and persist table column selections.
         *
         * @param array $request Raw request data.
         * @return array|WP_Error
         */
        protected function process_columns_save_request( $request ) {
            $form_slug = '';

            if ( isset( $request['current_form'] ) ) {
                $form_slug = sanitize_key( wp_unslash( $request['current_form'] ) );
            } elseif ( isset( $request['form_slug'] ) ) {
                $form_slug = sanitize_key( wp_unslash( $request['form_slug'] ) );
            }

            $raw_columns = array();

            if ( isset( $request['table_columns'] ) ) {
                $raw_columns = wp_unslash( $request['table_columns'] );
            }

            if ( ! is_array( $raw_columns ) ) {
                $raw_columns = array( $raw_columns );
            }

            $normalised_columns = array();

            foreach ( $raw_columns as $column_key ) {
                $normalised_key = $this->normalise_table_column_key( $column_key );

                if ( '' !== $normalised_key ) {
                    $normalised_columns[] = $normalised_key;
                }
            }

            $contact_form    = $this->get_contact_form_by_slug( $form_slug );
            $form_field_keys = $this->get_form_field_keys( $contact_form );
            $allowed_keys    = $this->get_table_column_candidate_keys( $form_field_keys );

            $columns = array_values( array_intersect( array_unique( $normalised_columns ), $allowed_keys ) );

            if ( empty( $columns ) ) {
                return new WP_Error( 'theme_leads_columns_empty', __( 'Please choose at least one column to display.', 'wordprseo' ) );
            }

            $this->save_table_columns_selection( $columns, $form_slug );

            return array(
                'columns'   => $columns,
                'form_slug' => $form_slug,
            );
        }

        /**
         * Handle table column configuration submissions.
         */
        public function handle_columns_save() {
            if ( ! current_user_can( $this->get_required_capability() ) ) {
                wp_die( esc_html__( 'You do not have permission to access this page.', 'wordprseo' ) );
            }

            check_admin_referer( 'theme_leads_columns_save' );

            $result = $this->process_columns_save_request( $_POST );

            $args = array( 'page' => 'theme-leads' );

            $current_form = isset( $_POST['current_form'] ) ? sanitize_key( wp_unslash( $_POST['current_form'] ) ) : '';
            if ( ! empty( $current_form ) ) {
                $args['form'] = $current_form;
            }

            if ( is_wp_error( $result ) ) {
                $args['theme_leads_notice']         = 'columns_error';
                $args['theme_leads_notice_message'] = rawurlencode( $result->get_error_message() );
            } else {
                $args['theme_leads_notice'] = 'columns_saved';
            }

            wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
            exit;
        }

        /**
         * Handle AJAX table column configuration updates.
         */
        public function handle_ajax_columns_save() {
            if ( ! current_user_can( $this->get_required_capability() ) ) {
                wp_send_json_error(
                    array( 'message' => esc_html__( 'You do not have permission to perform this action.', 'wordprseo' ) ),
                    403
                );
            }

            check_ajax_referer( 'theme_leads_columns_save' );

            $result = $this->process_columns_save_request( $_POST );

            if ( is_wp_error( $result ) ) {
                wp_send_json_error( array( 'message' => $result->get_error_message() ) );
            }

            $result['save_nonce'] = wp_create_nonce( 'theme_leads_columns_save' );

            wp_send_json_success( $result );
        }

        /**
         * Handle status management form submissions.
         */
        public function handle_statuses_save() {
            if ( ! current_user_can( $this->get_required_capability() ) ) {
                wp_die( esc_html__( 'You do not have permission to access this page.', 'wordprseo' ) );
            }

            check_admin_referer( 'theme_leads_statuses_save' );

            $result = $this->process_statuses_save_request( $_POST );

            $args = array( 'page' => 'theme-leads' );

            $current_form = isset( $_POST['current_form'] ) ? sanitize_key( wp_unslash( $_POST['current_form'] ) ) : '';
            if ( ! empty( $current_form ) ) {
                $args['form'] = $current_form;
            }

            if ( is_wp_error( $result ) ) {
                $args['theme_leads_notice']         = 'statuses_error';
                $args['theme_leads_notice_message'] = rawurlencode( $result->get_error_message() );
            } else {
                $args['theme_leads_notice'] = 'statuses_saved';
            }

            wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
            exit;
        }

        /**
         * Handle AJAX status management requests.
         */
        public function handle_ajax_statuses_save() {
            if ( ! current_user_can( $this->get_required_capability() ) ) {
                wp_send_json_error(
                    array( 'message' => esc_html__( 'You do not have permission to perform this action.', 'wordprseo' ) ),
                    403
                );
            }

            check_ajax_referer( 'theme_leads_statuses_save' );

            $result = $this->process_statuses_save_request( $_POST );

            if ( is_wp_error( $result ) ) {
                wp_send_json_error( array( 'message' => $result->get_error_message() ) );
            }

            $result['save_nonce'] = wp_create_nonce( 'theme_leads_statuses_save' );

            wp_send_json_success( $result );
        }

        /**
         * Normalise and persist status updates.
         *
         * @param array $request Raw request data.
         * @return array|WP_Error
         */
        protected function process_statuses_save_request( $request ) {
            $raw_input = isset( $request['lead_statuses'] ) ? wp_unslash( $request['lead_statuses'] ) : '';
            $lines     = preg_split( '/[\r\n]+/', (string) $raw_input );
            $form_slug = '';

            if ( isset( $request['current_form'] ) ) {
                $form_slug = sanitize_key( wp_unslash( $request['current_form'] ) );
            } elseif ( isset( $request['form_slug'] ) ) {
                $form_slug = sanitize_key( wp_unslash( $request['form_slug'] ) );
            }

            $definitions = $this->normalise_status_definitions( $lines );

            if ( empty( $definitions ) ) {
                return new WP_Error( 'theme_leads_statuses_empty', __( 'Please enter at least one status.', 'wordprseo' ) );
            }

            $this->save_status_definitions( $definitions, $form_slug );

            return array(
                'statuses' => $this->prepare_statuses_for_js( $definitions ),
                'textarea' => $this->get_status_textarea_value( $definitions ),
                'form_slug'=> $form_slug,
            );
        }

        /**
         * Handle SMTP mailer settings submissions.
         */
        public function handle_mailer_settings_save() {
            if ( ! current_user_can( $this->get_required_capability() ) ) {
                wp_die( esc_html__( 'You do not have permission to access this page.', 'wordprseo' ) );
            }

            check_admin_referer( 'theme_leads_mailer_save' );

            $result = $this->process_mailer_settings_save_request( $_POST );

            $args = array( 'page' => 'theme-leads' );

            $current_form = isset( $_POST['current_form'] ) ? sanitize_key( wp_unslash( $_POST['current_form'] ) ) : '';
            if ( ! empty( $current_form ) ) {
                $args['form'] = $current_form;
            }

            if ( is_wp_error( $result ) ) {
                $args['theme_leads_notice']         = 'mailer_error';
                $args['theme_leads_notice_message'] = rawurlencode( $result->get_error_message() );
            } else {
                $args['theme_leads_notice'] = 'mailer_saved';
            }

            wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
            exit;
        }

        /**
         * Handle AJAX requests for SMTP mailer updates.
         */
        public function handle_ajax_mailer_settings_save() {
            if ( ! current_user_can( $this->get_required_capability() ) ) {
                wp_send_json_error(
                    array( 'message' => esc_html__( 'You do not have permission to perform this action.', 'wordprseo' ) ),
                    403
                );
            }

            check_ajax_referer( 'theme_leads_mailer_save' );

            $result = $this->process_mailer_settings_save_request( $_POST );

            if ( is_wp_error( $result ) ) {
                wp_send_json_error( array( 'message' => $result->get_error_message() ) );
            }

            $result['save_nonce'] = wp_create_nonce( 'theme_leads_mailer_save' );

            wp_send_json_success( $result );
        }

        /**
         * Normalise and persist SMTP mailer updates.
         *
         * @param array $request Raw request.
         * @return array|WP_Error
         */
        protected function process_mailer_settings_save_request( $request ) {
            $settings = array(
                'host'       => isset( $request['mailer_host'] ) ? wp_unslash( $request['mailer_host'] ) : '',
                'port'       => isset( $request['mailer_port'] ) ? wp_unslash( $request['mailer_port'] ) : '',
                'encryption' => isset( $request['mailer_encryption'] ) ? wp_unslash( $request['mailer_encryption'] ) : '',
                'username'   => isset( $request['mailer_username'] ) ? wp_unslash( $request['mailer_username'] ) : '',
                'password'   => isset( $request['mailer_password'] ) ? wp_unslash( $request['mailer_password'] ) : '',
                'from_email' => isset( $request['mailer_from_email'] ) ? wp_unslash( $request['mailer_from_email'] ) : '',
                'from_name'  => isset( $request['mailer_from_name'] ) ? wp_unslash( $request['mailer_from_name'] ) : '',
            );
            $form_slug = '';

            if ( isset( $request['current_form'] ) ) {
                $form_slug = sanitize_key( wp_unslash( $request['current_form'] ) );
            } elseif ( isset( $request['form_slug'] ) ) {
                $form_slug = sanitize_key( wp_unslash( $request['form_slug'] ) );
            }

            $sanitised = $this->sanitise_mailer_settings( $settings );

            $this->save_mailer_settings( $sanitised, $form_slug );

            return array(
                'mailer'    => $this->prepare_mailer_settings_for_js( $sanitised ),
                'form_slug' => $form_slug,
            );
        }

        /**
         * Normalise and persist a template save request.
         *
         * @param array $request Raw request data.
         * @return array|WP_Error
         */
        protected function process_template_save_request( $request ) {
            $template_action = isset( $request['template_action'] ) ? sanitize_key( wp_unslash( $request['template_action'] ) ) : 'update';
            $label           = isset( $request['template_label'] ) ? sanitize_text_field( wp_unslash( $request['template_label'] ) ) : '';
            $subject         = isset( $request['template_subject'] ) ? sanitize_text_field( wp_unslash( $request['template_subject'] ) ) : '';
            $body            = isset( $request['template_body'] ) ? wp_kses_post( wp_unslash( $request['template_body'] ) ) : '';
            $has_description = array_key_exists( 'template_description', $request );
            $description     = $has_description ? sanitize_textarea_field( wp_unslash( $request['template_description'] ) ) : null;
            $slug            = isset( $request['template_slug'] ) ? sanitize_key( wp_unslash( $request['template_slug'] ) ) : '';
            $form_slug       = '';

            if ( isset( $request['current_form'] ) ) {
                $form_slug = sanitize_key( wp_unslash( $request['current_form'] ) );
            } elseif ( isset( $request['form_slug'] ) ) {
                $form_slug = sanitize_key( wp_unslash( $request['form_slug'] ) );
            }

            if ( empty( $label ) || empty( $subject ) || empty( $body ) ) {
                return new WP_Error( 'theme_leads_template_missing_fields', __( 'Please complete the required template fields.', 'wordprseo' ) );
            }

            $templates = $this->get_templates( $form_slug );

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

            $previous_description = isset( $templates[ $slug ]['description'] ) ? $templates[ $slug ]['description'] : '';

            $templates[ $slug ] = array(
                'label'   => $label,
                'subject' => $subject,
                'body'    => $body,
            );

            if ( null !== $description ) {
                $templates[ $slug ]['description'] = $description;
            } elseif ( '' !== $previous_description ) {
                $templates[ $slug ]['description'] = $previous_description;
            }

            $this->save_templates( $templates, $form_slug );

            return array(
                'slug'      => $slug,
                'template'  => $templates[ $slug ],
                'templates' => $this->prepare_templates_for_js( $templates ),
                'form_slug' => $form_slug,
                'save_nonce'   => wp_create_nonce( 'theme_leads_template_save' ),
                'delete_nonce' => wp_create_nonce( 'theme_leads_template_delete' ),
            );
        }

        /**
         * Normalise and persist a template delete request.
         *
         * @param array $request Raw request data.
         * @return array|WP_Error
         */
        protected function process_template_delete_request( $request ) {
            $slug = isset( $request['template_slug'] ) ? sanitize_key( wp_unslash( $request['template_slug'] ) ) : '';
            $form_slug = '';

            if ( isset( $request['current_form'] ) ) {
                $form_slug = sanitize_key( wp_unslash( $request['current_form'] ) );
            } elseif ( isset( $request['form_slug'] ) ) {
                $form_slug = sanitize_key( wp_unslash( $request['form_slug'] ) );
            }

            if ( empty( $slug ) ) {
                return new WP_Error( 'theme_leads_template_missing_slug', __( 'Template reference missing.', 'wordprseo' ) );
            }

            $templates = $this->get_templates( $form_slug );

            if ( ! isset( $templates[ $slug ] ) ) {
                return new WP_Error( 'theme_leads_template_not_found', __( 'Template not found.', 'wordprseo' ) );
            }

            unset( $templates[ $slug ] );

            $this->save_templates( $templates, $form_slug );

            return array(
                'slug'      => $slug,
                'templates' => $this->prepare_templates_for_js( $templates ),
                'form_slug' => $form_slug,
                'save_nonce'   => wp_create_nonce( 'theme_leads_template_save' ),
                'delete_nonce' => wp_create_nonce( 'theme_leads_template_delete' ),
            );
        }

        /**
         * Render the admin leads management page.
         */
        public function render_admin_page() {
            $forms     = $this->get_contact_forms();
            $form_slug = isset( $_GET['form'] ) ? sanitize_key( wp_unslash( $_GET['form'] ) ) : '';

            if ( empty( $form_slug ) && ! empty( $forms ) ) {
                $first_form = reset( $forms );
                $form_slug  = $this->get_form_slug( $first_form );
            }

            $templates             = $this->get_templates( $form_slug );
            $statuses              = $this->get_status_definitions( $form_slug );
            $status_textarea_value = $this->get_status_textarea_value( $statuses );
            $statuses_for_js       = $this->prepare_statuses_for_js( $statuses );
            $default_cc_addresses  = $this->get_default_cc_addresses( $form_slug );
            $default_cc_textarea   = implode( "\n", $default_cc_addresses );
            $default_cc_for_js     = $this->prepare_default_cc_for_js( $default_cc_addresses );
            $mailer_settings       = $this->get_mailer_settings( $form_slug );
            $mailer_settings_for_js = $this->prepare_mailer_settings_for_js( $mailer_settings );

            echo '<div class="wrap">';
            echo '<h1>' . esc_html__( 'Leads', 'wordprseo' ) . '</h1>';

            $notice_key     = isset( $_GET['theme_leads_notice'] ) ? sanitize_key( wp_unslash( $_GET['theme_leads_notice'] ) ) : '';
            $notice_message = isset( $_GET['theme_leads_notice_message'] ) ? wp_unslash( $_GET['theme_leads_notice_message'] ) : '';
            if ( $notice_message ) {
                $notice_message = rawurldecode( $notice_message );
            }

            if ( $notice_key ) {
                $notices = array(
                    'statuses_saved'   => array(
                        'class'   => 'notice-success',
                        'message' => __( 'Statuses updated.', 'wordprseo' ),
                    ),
                    'statuses_error'   => array(
                        'class'   => 'notice-error',
                        'message' => $notice_message ? $notice_message : __( 'The statuses could not be updated.', 'wordprseo' ),
                    ),
                    'default_cc_saved' => array(
                        'class'   => 'notice-success',
                        'message' => __( 'Default CC recipients updated.', 'wordprseo' ),
                    ),
                    'default_cc_error' => array(
                        'class'   => 'notice-error',
                        'message' => $notice_message ? $notice_message : __( 'The default CC list could not be updated.', 'wordprseo' ),
                    ),
                    'columns_saved'    => array(
                        'class'   => 'notice-success',
                        'message' => __( 'Table columns updated.', 'wordprseo' ),
                    ),
                    'columns_error'    => array(
                        'class'   => 'notice-error',
                        'message' => $notice_message ? $notice_message : __( 'The table columns could not be updated.', 'wordprseo' ),
                    ),
                    'mailer_saved'     => array(
                        'class'   => 'notice-success',
                        'message' => __( 'Email sender settings updated.', 'wordprseo' ),
                    ),
                    'mailer_error'     => array(
                        'class'   => 'notice-error',
                        'message' => $notice_message ? $notice_message : __( 'The email sender settings could not be updated.', 'wordprseo' ),
                    ),
                );

                if ( isset( $notices[ $notice_key ] ) ) {
                    $notice = $notices[ $notice_key ];
                    printf( '<div class="notice %1$s"><p>%2$s</p></div>', esc_attr( $notice['class'] ), esc_html( $notice['message'] ) );
                }
            }

            if ( empty( $forms ) ) {
                echo '<p>' . esc_html__( 'No Contact Form 7 forms were found.', 'wordprseo' ) . '</p>';
                echo '</div>';
                return;
            }

            $contact_form       = $this->get_contact_form_by_slug( $form_slug );
            $form_field_keys    = $this->get_form_field_keys( $contact_form );
            $form_placeholder_tokens = $this->prepare_form_placeholder_tokens( $form_field_keys );
            $table_column_candidates = $this->get_table_column_candidates( $form_field_keys );
            $selected_table_columns  = $this->get_selected_table_columns( $form_slug, $form_field_keys );

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

            echo '<div class="theme-leads-toolbar-actions">';
            echo '<button type="button" class="button theme-leads-columns-toggle" aria-expanded="false" aria-controls="theme-leads-columns-panel">';
            echo '<span class="dashicons dashicons-visibility" aria-hidden="true"></span>';
            echo '<span class="screen-reader-text">' . esc_html__( 'Choose table columns', 'wordprseo' ) . '</span>';
            echo '</button>';
            echo '<button type="button" class="button theme-leads-status-toggle" aria-expanded="false" aria-controls="theme-leads-status-panel">';
            echo '<span class="dashicons dashicons-category" aria-hidden="true"></span>';
            echo '<span class="screen-reader-text">' . esc_html__( 'Manage statuses', 'wordprseo' ) . '</span>';
            echo '</button>';
            echo '<button type="button" class="button theme-leads-defaults-toggle" aria-expanded="false" aria-controls="theme-leads-defaults-panel">';
            echo '<span class="dashicons dashicons-email-alt2" aria-hidden="true"></span>';
            echo '<span class="screen-reader-text">' . esc_html__( 'Manage default CC recipients', 'wordprseo' ) . '</span>';
            echo '</button>';
            echo '<button type="button" class="button theme-leads-mailer-toggle" aria-expanded="false" aria-controls="theme-leads-mailer-panel">';
            echo '<span class="dashicons dashicons-email" aria-hidden="true"></span>';
            echo '<span class="screen-reader-text">' . esc_html__( 'Configure email sender', 'wordprseo' ) . '</span>';
            echo '</button>';
            echo '<button type="button" class="button theme-leads-template-toggle" aria-expanded="false" aria-controls="theme-leads-template-panel">';
            echo '<span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>';
            echo '<span class="screen-reader-text">' . esc_html__( 'Manage templates', 'wordprseo' ) . '</span>';
            echo '</button>';
            echo '</div>';
            echo '</div>';

            echo '<div id="theme-leads-columns-panel" class="theme-leads-panel theme-leads-columns-panel" role="dialog" aria-modal="false" aria-hidden="true" hidden>';
            echo '<div class="theme-leads-panel-inner">';
            echo '<button type="button" class="button-link theme-leads-panel-close theme-leads-columns-close" aria-label="' . esc_attr__( 'Close column manager', 'wordprseo' ) . '"><span class="dashicons dashicons-no" aria-hidden="true"></span></button>';
            echo '<h2>' . esc_html__( 'Table columns', 'wordprseo' ) . '</h2>';
            echo '<p>' . esc_html__( 'Select the lead details and form fields you want to display in the leads table.', 'wordprseo' ) . '</p>';
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="theme-leads-columns-form">';
            wp_nonce_field( 'theme_leads_columns_save' );
            echo '<input type="hidden" name="action" value="theme_leads_columns_save" />';
            echo '<input type="hidden" name="current_form" value="' . esc_attr( $form_slug ) . '" />';

            $column_groups = array();
            foreach ( $table_column_candidates as $candidate ) {
                $group = isset( $candidate['group'] ) ? $candidate['group'] : 'form';
                if ( ! isset( $column_groups[ $group ] ) ) {
                    $column_groups[ $group ] = array();
                }
                $column_groups[ $group ][] = $candidate;
            }

            $group_labels = array(
                'lead' => __( 'Lead details', 'wordprseo' ),
                'form' => __( 'Form fields', 'wordprseo' ),
            );

            echo '<div class="theme-leads-columns-options">';
            foreach ( $column_groups as $group => $items ) {
                if ( empty( $items ) ) {
                    continue;
                }

                $group_label = isset( $group_labels[ $group ] ) ? $group_labels[ $group ] : __( 'Fields', 'wordprseo' );
                echo '<fieldset class="theme-leads-columns-fieldset">';
                echo '<legend>' . esc_html( $group_label ) . '</legend>';
                echo '<div class="theme-leads-columns-list">';

                foreach ( $items as $candidate ) {
                    $key   = isset( $candidate['key'] ) ? $candidate['key'] : '';
                    $label = isset( $candidate['label'] ) ? $candidate['label'] : $key;

                    if ( '' === $key ) {
                        continue;
                    }

                    $input_id = 'theme-leads-column-' . sanitize_html_class( $key );
                    $checked  = in_array( $key, $selected_table_columns, true );

                    printf(
                        '<label class="theme-leads-columns-option" for="%1$s"><input type="checkbox" id="%1$s" name="table_columns[]" value="%2$s" %3$s /> %4$s</label>',
                        esc_attr( $input_id ),
                        esc_attr( $key ),
                        checked( $checked, true, false ),
                        esc_html( $label )
                    );
                }

                echo '</div>';
                echo '</fieldset>';
            }
            echo '</div>';

            echo '<p class="submit"><button type="submit" class="button button-primary">' . esc_html__( 'Save columns', 'wordprseo' ) . '</button></p>';
            echo '<div class="theme-leads-panel-feedback theme-leads-form-feedback theme-leads-columns-feedback" aria-live="polite"></div>';
            echo '</form>';
            echo '</div>';
            echo '</div>';

            echo '<div id="theme-leads-status-panel" class="theme-leads-panel theme-leads-status-panel" role="dialog" aria-modal="false" aria-hidden="true" hidden>';
            echo '<div class="theme-leads-panel-inner">';
            echo '<button type="button" class="button-link theme-leads-panel-close theme-leads-status-close" aria-label="' . esc_attr__( 'Close status manager', 'wordprseo' ) . '"><span class="dashicons dashicons-no" aria-hidden="true"></span></button>';
            echo '<h2>' . esc_html__( 'Lead statuses', 'wordprseo' ) . '</h2>';
            echo '<p>' . esc_html__( 'Enter one status per line. The first status will be applied to new leads.', 'wordprseo' ) . '</p>';
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="theme-leads-statuses-form">';
            wp_nonce_field( 'theme_leads_statuses_save' );
            echo '<input type="hidden" name="action" value="theme_leads_statuses_save" />';
            echo '<input type="hidden" name="current_form" value="' . esc_attr( $form_slug ) . '" />';
            echo '<textarea name="lead_statuses" rows="6" class="large-text">' . esc_textarea( $status_textarea_value ) . '</textarea>';
            echo '<p class="description">' . esc_html__( 'Statuses are saved in the order provided.', 'wordprseo' ) . '</p>';
            echo '<p class="submit"><button type="submit" class="button button-primary">' . esc_html__( 'Save statuses', 'wordprseo' ) . '</button></p>';
            echo '<div class="theme-leads-panel-feedback theme-leads-form-feedback theme-leads-statuses-feedback" aria-live="polite"></div>';
            echo '</form>';
            echo '</div>';
            echo '</div>';

            echo '<div id="theme-leads-defaults-panel" class="theme-leads-panel theme-leads-defaults-panel" role="dialog" aria-modal="false" aria-hidden="true" hidden>';
            echo '<div class="theme-leads-panel-inner">';
            echo '<button type="button" class="button-link theme-leads-panel-close theme-leads-defaults-close" aria-label="' . esc_attr__( 'Close default CC manager', 'wordprseo' ) . '"><span class="dashicons dashicons-no" aria-hidden="true"></span></button>';
            echo '<h2>' . esc_html__( 'Default CC recipients', 'wordprseo' ) . '</h2>';
            echo '<p>' . esc_html__( 'Recipients listed here will be copied on every email you send from a lead.', 'wordprseo' ) . '</p>';
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="theme-leads-default-cc-form">';
            wp_nonce_field( 'theme_leads_default_cc_save' );
            echo '<input type="hidden" name="action" value="theme_leads_default_cc_save" />';
            echo '<input type="hidden" name="current_form" value="' . esc_attr( $form_slug ) . '" />';
            echo '<textarea name="lead_default_cc" rows="5" class="large-text" placeholder="team@example.com">' . esc_textarea( $default_cc_textarea ) . '</textarea>';
            echo '<p class="description">' . esc_html__( 'Separate addresses with commas or line breaks.', 'wordprseo' ) . '</p>';
            echo '<p class="submit"><button type="submit" class="button button-primary">' . esc_html__( 'Save CC list', 'wordprseo' ) . '</button></p>';
            echo '<div class="theme-leads-panel-feedback theme-leads-form-feedback theme-leads-default-cc-feedback" aria-live="polite"></div>';
            echo '</form>';
            echo '</div>';
            echo '</div>';

            echo '<div id="theme-leads-mailer-panel" class="theme-leads-panel theme-leads-mailer-panel" role="dialog" aria-modal="false" aria-hidden="true" hidden>';
            echo '<div class="theme-leads-panel-inner">';
            echo '<button type="button" class="button-link theme-leads-panel-close theme-leads-mailer-close" aria-label="' . esc_attr__( 'Close email sender settings', 'wordprseo' ) . '"><span class="dashicons dashicons-no" aria-hidden="true"></span></button>';
            echo '<h2>' . esc_html__( 'Email sender', 'wordprseo' ) . '</h2>';
            echo '<p>' . esc_html__( 'Configure SMTP details for replies sent from the leads screen. Leave fields blank to use Contact Form 7 defaults.', 'wordprseo' ) . '</p>';
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="theme-leads-mailer-form">';
            wp_nonce_field( 'theme_leads_mailer_save' );
            echo '<input type="hidden" name="action" value="theme_leads_mailer_save" />';
            echo '<input type="hidden" name="current_form" value="' . esc_attr( $form_slug ) . '" />';

            echo '<div class="theme-leads-form-group">';
            echo '<label for="theme-leads-mailer-host">' . esc_html__( 'SMTP host', 'wordprseo' ) . '</label>';
            echo '<input type="text" class="widefat" id="theme-leads-mailer-host" name="mailer_host" value="' . esc_attr( $mailer_settings['host'] ) . '" />';
            echo '</div>';

            echo '<div class="theme-leads-form-group">';
            echo '<label for="theme-leads-mailer-port">' . esc_html__( 'SMTP port', 'wordprseo' ) . '</label>';
            echo '<input type="number" class="small-text" id="theme-leads-mailer-port" name="mailer_port" min="0" step="1" value="' . esc_attr( $mailer_settings['port'] ) . '" />';
            echo '</div>';

            echo '<div class="theme-leads-form-group">';
            echo '<label for="theme-leads-mailer-encryption">' . esc_html__( 'Encryption', 'wordprseo' ) . '</label>';
            echo '<select id="theme-leads-mailer-encryption" name="mailer_encryption" class="widefat">';
            $encryption_options = array(
                ''    => __( 'None', 'wordprseo' ),
                'tls' => __( 'STARTTLS', 'wordprseo' ),
                'ssl' => __( 'SMTPS', 'wordprseo' ),
            );
            foreach ( $encryption_options as $value => $label ) {
                printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $value ), selected( $mailer_settings['encryption'], $value, false ), esc_html( $label ) );
            }
            echo '</select>';
            echo '</div>';

            echo '<div class="theme-leads-form-group">';
            echo '<label for="theme-leads-mailer-username">' . esc_html__( 'Username', 'wordprseo' ) . '</label>';
            echo '<input type="text" class="widefat" id="theme-leads-mailer-username" name="mailer_username" autocomplete="username" value="' . esc_attr( $mailer_settings['username'] ) . '" />';
            echo '</div>';

            echo '<div class="theme-leads-form-group">';
            echo '<label for="theme-leads-mailer-password">' . esc_html__( 'Password', 'wordprseo' ) . '</label>';
            echo '<input type="password" class="widefat" id="theme-leads-mailer-password" name="mailer_password" autocomplete="new-password" value="' . esc_attr( $mailer_settings['password'] ) . '" />';
            echo '</div>';

            echo '<div class="theme-leads-form-group">';
            echo '<label for="theme-leads-mailer-from-email">' . esc_html__( 'From email address', 'wordprseo' ) . '</label>';
            echo '<input type="email" class="widefat" id="theme-leads-mailer-from-email" name="mailer_from_email" value="' . esc_attr( $mailer_settings['from_email'] ) . '" />';
            echo '</div>';

            echo '<div class="theme-leads-form-group">';
            echo '<label for="theme-leads-mailer-from-name">' . esc_html__( 'From name', 'wordprseo' ) . '</label>';
            echo '<input type="text" class="widefat" id="theme-leads-mailer-from-name" name="mailer_from_name" value="' . esc_attr( $mailer_settings['from_name'] ) . '" />';
            echo '</div>';

            echo '<p class="description">' . esc_html__( 'If the credentials are left blank, the Contact Form 7 sender will be used instead.', 'wordprseo' ) . '</p>';
            echo '<p class="submit"><button type="submit" class="button button-primary">' . esc_html__( 'Save email sender', 'wordprseo' ) . '</button></p>';
            echo '<div class="theme-leads-panel-feedback theme-leads-form-feedback theme-leads-mailer-feedback" aria-live="polite"></div>';
            echo '</form>';
            echo '</div>';
            echo '</div>';

            echo '<div id="theme-leads-template-panel" class="theme-leads-panel theme-leads-template-panel" role="dialog" aria-modal="false" aria-hidden="true" hidden>';
            echo '<div class="theme-leads-panel-inner theme-leads-template-panel-inner">';
            echo '<button type="button" class="button-link theme-leads-panel-close theme-leads-template-close" aria-label="' . esc_attr__( 'Close template manager', 'wordprseo' ) . '"><span class="dashicons dashicons-no" aria-hidden="true"></span></button>';
            echo '<h2>' . esc_html__( 'Response templates', 'wordprseo' ) . '</h2>';

            echo '<div class="theme-leads-template-list" data-role="template-list" data-current-form="' . esc_attr( $form_slug ) . '">';

            if ( ! empty( $templates ) ) {
                foreach ( $templates as $slug => $template ) {
                    $label       = isset( $template['label'] ) ? $template['label'] : $slug;
                    $subject_tpl = isset( $template['subject'] ) ? $template['subject'] : '';
                    $body_tpl    = isset( $template['body'] ) ? $template['body'] : '';

                    echo '<details class="theme-leads-template-card" data-template="' . esc_attr( $slug ) . '">';
                    echo '<summary class="theme-leads-template-card-summary">';
                    echo '<span class="theme-leads-template-card-title">' . esc_html( $label ) . '</span>';
                    echo '<span class="theme-leads-template-card-toggle-icon dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>';
                    echo '</summary>';
                    echo '<div class="theme-leads-template-card-body">';
                    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="theme-leads-template-form" data-template="' . esc_attr( $slug ) . '">';
                    wp_nonce_field( 'theme_leads_template_save' );
                    echo '<input type="hidden" name="action" value="theme_leads_template_save" />';
                    echo '<input type="hidden" name="template_action" value="update" />';
                    echo '<input type="hidden" name="template_slug" value="' . esc_attr( $slug ) . '" />';
                    echo '<input type="hidden" name="current_form" value="' . esc_attr( $form_slug ) . '" />';
                    echo '<div class="theme-leads-template-placeholders" data-role="placeholder-list">';
                    echo '<span class="theme-leads-template-placeholders-label">' . esc_html__( 'Placeholders', 'wordprseo' ) . '</span>';
                    echo '<div class="theme-leads-template-placeholder-buttons" data-role="placeholder-buttons"></div>';
                    echo '</div>';
                    echo '<p><label>' . esc_html__( 'Template name', 'wordprseo' ) . '<br />';
                    echo '<input type="text" name="template_label" class="widefat" value="' . esc_attr( $label ) . '" required /></label></p>';
                    echo '<p><label>' . esc_html__( 'Subject template', 'wordprseo' ) . '<br />';
                    echo '<input type="text" name="template_subject" class="widefat" value="' . esc_attr( $subject_tpl ) . '" required /></label></p>';
                    echo '<p><label>' . esc_html__( 'Message template', 'wordprseo' ) . '<br />';
                    echo '<textarea name="template_body" class="widefat" rows="5" required>' . esc_textarea( $body_tpl ) . '</textarea></label></p>';
                    echo '<p class="theme-leads-template-actions">';
                    echo '<button type="submit" class="button button-primary theme-leads-template-save">' . esc_html__( 'Save template', 'wordprseo' ) . '</button>';
                    echo '</p>';
                    echo '<div class="theme-leads-template-feedback" aria-live="polite"></div>';
                    echo '</form>';
                    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="theme-leads-template-delete-form" data-template="' . esc_attr( $slug ) . '">';
                    wp_nonce_field( 'theme_leads_template_delete' );
                    echo '<input type="hidden" name="action" value="theme_leads_template_delete" />';
                    echo '<input type="hidden" name="template_slug" value="' . esc_attr( $slug ) . '" />';
                    echo '<input type="hidden" name="current_form" value="' . esc_attr( $form_slug ) . '" />';
                    echo '<button type="submit" class="button-link theme-leads-template-delete">' . esc_html__( 'Delete template', 'wordprseo' ) . '</button>';
                    echo '</form>';
                    echo '</div>';
                    echo '</details>';
                }
            }

            $new_card_open = empty( $templates ) ? ' open' : '';
            echo '<details class="theme-leads-template-card theme-leads-template-new" data-template="new"' . $new_card_open . '>';
            echo '<summary class="theme-leads-template-card-summary">';
            echo '<span class="theme-leads-template-card-title">' . esc_html__( 'Add new template', 'wordprseo' ) . '</span>';
            echo '<span class="theme-leads-template-card-toggle-icon dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>';
            echo '</summary>';
            echo '<div class="theme-leads-template-card-body">';
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="theme-leads-template-form" data-template="new">';
            wp_nonce_field( 'theme_leads_template_save' );
            echo '<input type="hidden" name="action" value="theme_leads_template_save" />';
            echo '<input type="hidden" name="template_action" value="create" />';
            echo '<input type="hidden" name="current_form" value="' . esc_attr( $form_slug ) . '" />';
            echo '<div class="theme-leads-template-placeholders" data-role="placeholder-list">';
            echo '<span class="theme-leads-template-placeholders-label">' . esc_html__( 'Placeholders', 'wordprseo' ) . '</span>';
            echo '<div class="theme-leads-template-placeholder-buttons" data-role="placeholder-buttons"></div>';
            echo '</div>';
            echo '<p><label>' . esc_html__( 'Template name', 'wordprseo' ) . '<br />';
            echo '<input type="text" name="template_label" class="widefat" required /></label></p>';
            echo '<p><label>' . esc_html__( 'Subject template', 'wordprseo' ) . '<br />';
            echo '<input type="text" name="template_subject" class="widefat" required /></label></p>';
            echo '<p><label>' . esc_html__( 'Message template', 'wordprseo' ) . '<br />';
            echo '<textarea name="template_body" class="widefat" rows="5" required></textarea></label></p>';
            echo '<p class="theme-leads-template-actions">';
            echo '<button type="submit" class="button button-primary theme-leads-template-save">' . esc_html__( 'Create template', 'wordprseo' ) . '</button>';
            echo '</p>';
            echo '<div class="theme-leads-template-feedback" aria-live="polite"></div>';
            echo '</form>';
            echo '</div>';
            echo '</details>';

            echo '</div>';
            echo '</div>';
            echo '</div>';

            if ( empty( $form_slug ) ) {
                echo '<p>' . esc_html__( 'Please choose a form to view leads.', 'wordprseo' ) . '</p>';
                echo '</div>';
                return;
            }

            $table        = $contact_form ? $this->get_table_name( $contact_form ) : $this->get_table_name_from_slug( $form_slug );

            global $wpdb;

            $table_exists = false;
            $leads        = array();

            if ( ! empty( $table ) ) {
                // Make sure legacy installations upgrade their lead tables when the admin page is viewed.
                $this->maybe_create_table( $table, $form_slug );

                $table_exists = $this->table_exists( $table );

                if ( $table_exists ) {
                    $leads = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY submitted_at DESC" );
                }
            }

            if ( ! $table_exists ) {
                echo '<p>' . esc_html__( 'No leads have been captured for the selected form yet.', 'wordprseo' ) . '</p>';
            } elseif ( empty( $leads ) ) {
                echo '<p>' . esc_html__( 'No leads found for this form.', 'wordprseo' ) . '</p>';
            } else {
                echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" id="theme-leads-bulk-form" class="theme-leads-bulk-form">';
                wp_nonce_field( 'theme_leads_bulk' );
                echo '<input type="hidden" name="action" value="theme_leads_bulk" />';
                echo '<input type="hidden" name="form_slug" value="' . esc_attr( $form_slug ) . '" />';
                echo '<fieldset class="theme-leads-bulk-fieldset">';
                echo '<legend>' . esc_html__( 'Bulk actions', 'wordprseo' ) . '</legend>';
                echo '<div class="theme-leads-bulk-groups">';
                echo '<div class="theme-leads-bulk-group">';
                echo '<label for="theme-leads-bulk-status">' . esc_html__( 'Change status to', 'wordprseo' ) . '</label>';
                echo '<select name="bulk_status" id="theme-leads-bulk-status" class="theme-leads-no-toggle">';
                echo '<option value="">' . esc_html__( 'Select status', 'wordprseo' ) . '</option>';
                foreach ( $statuses as $status_definition ) {
                    $status_slug  = isset( $status_definition['slug'] ) ? $status_definition['slug'] : '';
                    $status_label = isset( $status_definition['label'] ) ? $status_definition['label'] : $status_slug;
                    if ( '' === $status_slug ) {
                        continue;
                    }
                    printf( '<option value="%1$s">%2$s</option>', esc_attr( $status_slug ), esc_html( $status_label ) );
                }
                echo '</select>';
                echo '<button type="submit" class="button button-primary" name="bulk_action" value="status" disabled>' . esc_html__( 'Update status', 'wordprseo' ) . '</button>';
                echo '</div>';
                echo '<div class="theme-leads-bulk-group">';
                echo '<button type="submit" class="button button-secondary theme-leads-bulk-delete" name="bulk_action" value="delete" disabled>' . esc_html__( 'Delete selected', 'wordprseo' ) . '</button>';
                echo '</div>';
                echo '</div>';
                echo '</fieldset>';
                echo '</form>';

                echo '<table class="widefat fixed striped theme-leads-table">';
                echo '<thead><tr>';
                echo '<th scope="col" class="manage-column check-column"><label class="screen-reader-text theme-leads-no-toggle" for="theme-leads-select-all">' . esc_html__( 'Select all leads', 'wordprseo' ) . '</label><input type="checkbox" id="theme-leads-select-all" class="theme-leads-no-toggle" form="theme-leads-bulk-form" /></th>';
                echo '<th>' . esc_html__( 'Submitted', 'wordprseo' ) . '</th>';
                foreach ( $selected_table_columns as $column_key ) {
                    echo '<th>' . esc_html( $this->get_table_column_header_label( $column_key ) ) . '</th>';
                }
                echo '<th>' . esc_html__( 'Status', 'wordprseo' ) . '</th>';
                echo '<th>' . esc_html__( 'Update status', 'wordprseo' ) . '</th>';
                echo '<th>' . esc_html__( 'Actions', 'wordprseo' ) . '</th>';
                echo '</tr></thead><tbody>';

                $summary_column_count = 4 + count( $selected_table_columns );

                foreach ( $leads as $lead ) {
                $payload      = $this->normalise_payload( $lead->payload );
                $lead_name    = $this->extract_contact_name( $payload );
                $lead_phone   = $this->extract_phone_from_payload( $payload );
                $formatted_at = mysql2date( 'd/m/Y - g:iA', $lead->submitted_at );
                $detail_id    = 'theme-lead-details-' . absint( $lead->id );
                $client_name_value = ! empty( $lead->response_client_name ) ? $lead->response_client_name : ( $lead_name ? $lead_name : '' );
                $client_phone_value = ! empty( $lead->response_phone ) ? $lead->response_phone : ( $lead_phone ? $lead_phone : '' );
                $client_brand_value = ! empty( $lead->response_brand ) ? $lead->response_brand : $this->extract_brand_from_payload( $payload );
                $client_link_value  = ! empty( $lead->response_link ) ? $lead->response_link : $this->extract_link_from_payload( $payload );
                $client_link_value  = $this->normalise_link_value( $client_link_value );
                $column_context      = array(
                    'lead_name'    => $lead_name,
                    'lead_phone'   => $lead_phone,
                    'client_name'  => $client_name_value,
                    'client_phone' => $client_phone_value,
                    'client_brand' => $client_brand_value,
                    'client_link'  => $client_link_value,
                );
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

                $template_context_attrs = $this->build_template_context_attributes( $lead, $payload, $client_name_value, $client_phone_value, $client_brand_value, $client_link_value, $form_field_keys, $form_slug );
                $context_attr_string    = '';

                foreach ( $template_context_attrs as $attr_key => $attr_value ) {
                    if ( '' !== $attr_value ) {
                        $context_attr_string .= ' ' . $attr_key . '="' . $attr_value . '"';
                    }
                }

                $history_markup = $this->get_response_history_markup( $lead );

                echo '<tr class="theme-leads-summary" data-lead="' . absint( $lead->id ) . '">';
                $checkbox_id = 'theme-leads-select-' . absint( $lead->id );
                echo '<th scope="row" class="check-column">';
                echo '<label class="screen-reader-text theme-leads-no-toggle" for="' . esc_attr( $checkbox_id ) . '">' . esc_html__( 'Select this lead', 'wordprseo' ) . '</label>';
                echo '<input type="checkbox" id="' . esc_attr( $checkbox_id ) . '" class="theme-leads-select theme-leads-no-toggle" name="selected_leads[]" value="' . absint( $lead->id ) . '" form="theme-leads-bulk-form" />';
                echo '</th>';
                echo '<td class="theme-leads-summary-submitted">';
                echo '<button type="button" class="theme-leads-toggle-button" aria-expanded="false" aria-controls="' . esc_attr( $detail_id ) . '"><span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__( 'Toggle lead details', 'wordprseo' ) . '</span></button>';
                echo '<span class="theme-leads-submitted-text">' . esc_html( $formatted_at ) . '</span>';
                echo '</td>';

                foreach ( $selected_table_columns as $column_key ) {
                    echo $this->render_summary_table_column_cell( $column_key, $lead, $payload, $column_context, $form_slug );
                }

                echo '<td class="theme-leads-summary-status" data-status="' . esc_attr( $lead->status ) . '">' . esc_html( $this->get_status_label( $lead->status, $form_slug ) ) . '</td>';

                echo '<td>';
                echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="theme-leads-status-form theme-leads-no-toggle">';
                wp_nonce_field( 'theme_leads_update' );
                echo '<input type="hidden" name="action" value="theme_leads_update" />';
                echo '<input type="hidden" name="form_slug" value="' . esc_attr( $form_slug ) . '" />';
                echo '<input type="hidden" name="lead_id" value="' . absint( $lead->id ) . '" />';
                echo '<label class="screen-reader-text" for="theme-lead-status-' . absint( $lead->id ) . '">' . esc_html__( 'Change status', 'wordprseo' ) . '</label>';
                echo '<select id="theme-lead-status-' . absint( $lead->id ) . '" name="lead_status" class="theme-leads-status-select">';
                foreach ( $statuses as $status_definition ) {
                    $status_slug  = isset( $status_definition['slug'] ) ? $status_definition['slug'] : '';
                    $status_label = isset( $status_definition['label'] ) ? $status_definition['label'] : $status_slug;
                    if ( '' === $status_slug ) {
                        continue;
                    }
                    printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $status_slug ), selected( $lead->status, $status_slug, false ), esc_html( $status_label ) );
                }
                echo '</select>';
                echo '</form>';
                echo '</td>';

                echo '<td class="theme-leads-actions theme-leads-no-toggle">';
                if ( ! empty( $lead->email ) ) {
                    echo '<a class="theme-leads-action" href="mailto:' . esc_attr( $lead->email ) . '" title="' . esc_attr__( 'Send email', 'wordprseo' ) . '"><span class="dashicons dashicons-email-alt" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__( 'Send email', 'wordprseo' ) . '</span></a>';
                }

                if ( ! empty( $lead_phone ) ) {
                    $whatsapp_number  = preg_replace( '/[^0-9]/', '', $lead_phone );
                    $whatsapp_message = ! empty( $lead->response ) ? wp_strip_all_tags( $lead->response ) : '';
                    if ( ! empty( $whatsapp_number ) ) {
                        $whatsapp_href = 'https://wa.me/' . $whatsapp_number;
                        if ( '' !== $whatsapp_message ) {
                            $whatsapp_href .= '?text=' . rawurlencode( $whatsapp_message );
                        }
                        echo '<a class="theme-leads-action theme-leads-whatsapp" href="' . esc_url( $whatsapp_href ) . '" data-number="' . esc_attr( $whatsapp_number ) . '" data-message="' . esc_attr( $whatsapp_message ) . '" target="_blank" rel="noopener noreferrer" title="' . esc_attr__( 'Send WhatsApp message', 'wordprseo' ) . '"><span class="dashicons dashicons-format-chat" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__( 'Send WhatsApp message', 'wordprseo' ) . '</span></a>';
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
                echo '<td class="check-column theme-leads-no-toggle"></td>';
                echo '<td colspan="' . absint( $summary_column_count ) . '">';
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
                        printf(
                            '<li><strong>%1$s:</strong> %2$s</li>',
                            esc_html( $key ),
                            $this->get_payload_value_display_markup( $value, $lead, $form_slug, $key )
                        );
                    }
                    echo '</ul>';
                } else {
                    echo '<p>' . esc_html__( 'No submission data recorded for this lead.', 'wordprseo' ) . '</p>';
                }

                echo '<div class="theme-leads-form-group theme-leads-form-group-status">';
                echo '<label>' . esc_html__( 'Status', 'wordprseo' );
                echo '<select name="lead_status" class="theme-leads-status-select">';
                foreach ( $statuses as $status_definition ) {
                    $status_slug  = isset( $status_definition['slug'] ) ? $status_definition['slug'] : '';
                    $status_label = isset( $status_definition['label'] ) ? $status_definition['label'] : $status_slug;
                    if ( '' === $status_slug ) {
                        continue;
                    }
                    printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $status_slug ), selected( $lead->status, $status_slug, false ), esc_html( $status_label ) );
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
                echo '<label>' . esc_html__( 'Brand name', 'wordprseo' );
                echo '<input type="text" name="lead_brand" class="widefat" value="' . esc_attr( $client_brand_value ) . '" />';
                echo '</label>';
                echo '</div>';

                echo '<div class="theme-leads-form-group">';
                echo '<label>' . esc_html__( 'Client name', 'wordprseo' );
                echo '<input type="text" name="lead_client_name" class="widefat" value="' . esc_attr( $client_name_value ) . '" />';
                echo '</label>';
                echo '</div>';

                echo '<div class="theme-leads-form-group">';
                echo '<label>' . esc_html__( 'Link', 'wordprseo' );
                echo '<input type="url" name="lead_client_link" class="widefat" value="' . esc_attr( $client_link_value ) . '" placeholder="https://" />';
                echo '</label>';
                echo '</div>';

                echo '<div class="theme-leads-form-group theme-leads-email-group">';
                echo '<label>' . esc_html__( 'Client emails', 'wordprseo' );
                echo '<span class="theme-leads-field-help">' . esc_html__( 'Use + to add CC recipients. Default CC recipients configured in the toolbar are added automatically.', 'wordprseo' ) . '</span>';
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

                echo '<hr class="theme-leads-field-divider" />';

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
            }

            echo '<style>
                .theme-leads-toolbar { display:flex; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:16px; }
                .theme-leads-toolbar-actions { margin-left:auto; display:flex; align-items:center; gap:8px; }
                .theme-leads-toolbar-actions .button { display:flex; align-items:center; gap:6px; }
                .theme-leads-form-selector { display:flex; align-items:center; gap:8px; }
                .theme-leads-form-selector select { min-width:220px; }
                .theme-leads-template-toggle { display:flex; align-items:center; gap:6px; }
                .theme-leads-panel { position:fixed; top:64px; right:32px; width:420px; max-width:90vw; max-height:80vh; overflow:auto; background:#fff; border:1px solid #ccd0d4; box-shadow:0 20px 40px rgba(0,0,0,0.2); padding:24px; display:none; z-index:9999; }
                .theme-leads-panel[aria-hidden="false"] { display:block; }
                .theme-leads-panel-inner { position:relative; display:flex; flex-direction:column; gap:16px; }
                .theme-leads-spinner { display:inline-block; width:16px; height:16px; margin-left:6px; border:2px solid currentColor; border-top-color:transparent; border-radius:50%; animation:themeLeadsSpin 1s linear infinite; vertical-align:middle; }
                .theme-leads-spinner--inline { margin-left:8px; }
                .theme-leads-panel-close { position:absolute; top:8px; right:8px; color:#666; }
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
                .theme-leads-submitted-text { display:inline-flex; align-items:center; min-width:160px; }
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
                .theme-leads-field-divider { border:0; border-top:1px solid #dcdcde; margin:8px 0 16px; }
                .theme-leads-form-feedback { min-height:20px; font-size:13px; font-weight:500; color:#2271b1; }
                .theme-leads-form-feedback.is-error { color:#b32d2e; }
                .theme-leads-form-feedback.is-success { color:#017c3c; }
                .theme-leads-template-placeholder-buttons { display:flex; flex-direction:column; gap:16px; }
                .theme-leads-placeholder-section { display:flex; flex-direction:column; gap:8px; }
                .theme-leads-placeholder-section-title { font-size:11px; font-weight:600; text-transform:uppercase; color:#4f5969; letter-spacing:0.04em; }
                .theme-leads-placeholder-buttons-grid { display:flex; flex-wrap:wrap; gap:8px; }
                .theme-leads-placeholder-button { display:inline-flex; align-items:center; padding:4px 10px; border-radius:999px; border:1px solid #ccd0d4; background:#f6f7f7; cursor:pointer; font-size:12px; line-height:1.4; color:#1d2327; transition:all .2s ease; font-family:inherit; }
                .theme-leads-placeholder-button:hover, .theme-leads-placeholder-button:focus { background:#2271b1; border-color:#2271b1; color:#fff; outline:0; }
                .theme-leads-placeholder-empty { font-size:12px; color:#666; }
                .theme-leads-bulk-form { margin-bottom:16px; }
                .theme-leads-bulk-fieldset { border:1px solid #dcdcde; border-radius:4px; padding:12px 16px; margin:0; background:#fff; display:flex; flex-direction:column; gap:12px; }
                .theme-leads-bulk-fieldset legend { font-size:12px; font-weight:600; text-transform:uppercase; color:#4f5969; letter-spacing:0.04em; padding:0 10px; margin-left:16px; background:#fff; }
                .theme-leads-bulk-groups { display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end; }
                .theme-leads-bulk-group { display:flex; flex-wrap:wrap; gap:8px; align-items:flex-end; }
                .theme-leads-bulk-group label { font-weight:600; }
                .theme-leads-bulk-group select { min-width:200px; }
                .theme-leads-bulk-delete { color:#dc3232; border-color:#dc3232; }
                .theme-leads-bulk-delete:disabled { color:#a7aaad; border-color:#dcdcde; }
                .theme-leads-columns-options { display:flex; flex-direction:column; gap:16px; }
                .theme-leads-columns-fieldset { border:1px solid #e2e4e7; border-radius:4px; padding:12px 16px; margin:0; background:#f9fafb; }
                .theme-leads-columns-fieldset legend { font-size:12px; font-weight:600; text-transform:uppercase; color:#4f5969; letter-spacing:0.04em; padding:0 4px; }
                .theme-leads-columns-list { display:flex; flex-direction:column; gap:8px; margin:0; }
                .theme-leads-columns-option { display:flex; align-items:center; gap:8px; font-weight:500; }
                .theme-leads-columns-option input { margin:0; }
                .theme-leads-table .check-column { width:40px; padding:0; text-align:center; vertical-align:middle; }
                .theme-leads-table .check-column input[type="checkbox"] { margin:0 auto; display:block; }
                .theme-leads-template-context { display:none; }
                @keyframes themeLeadsSpin { to { transform:rotate(360deg); } }
            </style>';

            $templates_json = wp_json_encode( $this->prepare_templates_for_js( $templates ) );
            if ( $templates_json ) {
                echo '<script type="application/json" id="theme-leads-templates-data">' . str_replace( '</', '<\/', $templates_json ) . '</script>';
            }

            $form_placeholders_json = wp_json_encode( $form_placeholder_tokens );
            if ( false !== $form_placeholders_json ) {
                echo '<script type="application/json" id="theme-leads-form-placeholders">' . str_replace( '</', '<\/', $form_placeholders_json ) . '</script>';
            }

            $statuses_json = wp_json_encode( $statuses_for_js );
            if ( $statuses_json ) {
                echo '<script type="application/json" id="theme-leads-statuses-data">' . str_replace( '</', '<\/', $statuses_json ) . '</script>';
            }

            $default_cc_json = wp_json_encode( $default_cc_for_js );
            if ( $default_cc_json ) {
                echo '<script type="application/json" id="theme-leads-default-cc-data">' . str_replace( '</', '<\/', $default_cc_json ) . '</script>';
            }

            $mailer_settings_json = wp_json_encode( $mailer_settings_for_js );
            if ( $mailer_settings_json ) {
                echo '<script type="application/json" id="theme-leads-mailer-data">' . str_replace( '</', '<\/', $mailer_settings_json ) . '</script>';
            }

            $remove_email_label_json      = wp_json_encode( __( 'Remove email', 'wordprseo' ) );
            $sending_label_json           = wp_json_encode( __( 'Sending…', 'wordprseo' ) );
            $saving_label_json            = wp_json_encode( __( 'Saving…', 'wordprseo' ) );
            $send_success_message_json    = wp_json_encode( __( 'Email sent successfully.', 'wordprseo' ) );
            $send_error_message_json      = wp_json_encode( __( 'Unable to send email. Please try again.', 'wordprseo' ) );
            $last_response_label_json     = wp_json_encode( __( 'Last response', 'wordprseo' ) );
            $select_template_label_json   = wp_json_encode( __( 'Select a template', 'wordprseo' ) );
            $no_placeholders_label_json   = wp_json_encode( __( 'No placeholders yet – open a lead to load submission details.', 'wordprseo' ) );
            $template_saved_label_json    = wp_json_encode( __( 'Template saved.', 'wordprseo' ) );
            $template_deleted_label_json  = wp_json_encode( __( 'Template deleted.', 'wordprseo' ) );
            $template_error_label_json    = wp_json_encode( __( 'Unable to save template. Please try again.', 'wordprseo' ) );
            $template_save_button_json    = wp_json_encode( __( 'Save template', 'wordprseo' ) );
            $template_delete_button_json  = wp_json_encode( __( 'Delete template', 'wordprseo' ) );
            $placeholders_label_json      = wp_json_encode( __( 'Placeholders', 'wordprseo' ) );
            $template_name_label_json     = wp_json_encode( __( 'Template name', 'wordprseo' ) );
            $template_subject_label_json  = wp_json_encode( __( 'Subject template', 'wordprseo' ) );
            $template_message_label_json  = wp_json_encode( __( 'Message template', 'wordprseo' ) );
            $whatsapp_label_json          = wp_json_encode( __( 'Send WhatsApp message', 'wordprseo' ) );
            $changes_saved_message_json   = wp_json_encode( __( 'Changes saved.', 'wordprseo' ) );
            $form_placeholders_heading_json   = wp_json_encode( __( 'Form fields', 'wordprseo' ) );
            $system_placeholders_heading_json = wp_json_encode( __( 'Lead details', 'wordprseo' ) );
            $statuses_saved_label_json        = wp_json_encode( __( 'Statuses saved.', 'wordprseo' ) );
            $statuses_error_label_json        = wp_json_encode( __( 'Unable to save statuses. Please try again.', 'wordprseo' ) );
            $default_cc_saved_label_json      = wp_json_encode( __( 'Default CC recipients saved.', 'wordprseo' ) );
            $default_cc_error_label_json      = wp_json_encode( __( 'Unable to save the default CC list. Please try again.', 'wordprseo' ) );
            $mailer_saved_label_json          = wp_json_encode( __( 'Email sender settings saved.', 'wordprseo' ) );
            $mailer_error_label_json          = wp_json_encode( __( 'Unable to save the email sender settings. Please try again.', 'wordprseo' ) );
            $columns_saved_label_json         = wp_json_encode( __( 'Table columns saved. Reloading…', 'wordprseo' ) );
            $columns_error_label_json         = wp_json_encode( __( 'Unable to save the table columns. Please try again.', 'wordprseo' ) );
            $columns_missing_label_json       = wp_json_encode( __( 'Select at least one column.', 'wordprseo' ) );
            $bulk_no_selection_label_json     = wp_json_encode( __( 'Select at least one lead to continue.', 'wordprseo' ) );
            $bulk_delete_confirm_label_json   = wp_json_encode( __( 'Delete the selected leads permanently?', 'wordprseo' ) );
            $system_placeholder_definitions   = array(
                '%name%'       => __( 'Client name', 'wordprseo' ),
                '%brand%'      => __( 'Brand name', 'wordprseo' ),
                '%link%'       => __( 'Website URL', 'wordprseo' ),
                '%status%'     => __( 'Status label', 'wordprseo' ),
                '%site_title%' => __( 'Site title', 'wordprseo' ),
            );
            $system_placeholder_definitions_json = wp_json_encode( $system_placeholder_definitions );
            if ( false === $system_placeholder_definitions_json ) {
                $system_placeholder_definitions_json = '{}';
            }
            $email_split_pattern_json         = wp_json_encode( '[\\r\\n,;]+' );
            if ( false === $email_split_pattern_json ) {
                $email_split_pattern_json = '"[\\\\r\\\\n,;]+"';
            }

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

    const statusDataElement = document.getElementById("theme-leads-statuses-data");
    let statusData = { items: [], map: {}, default: "" };
    if (statusDataElement) {
        try {
            const parsedStatuses = JSON.parse(statusDataElement.textContent);
            if (parsedStatuses && typeof parsedStatuses === "object") {
                statusData = parsedStatuses;
            }
        } catch (error) {
            statusData = { items: [], map: {}, default: "" };
        }
    }
    if (!Array.isArray(statusData.items)) {
        statusData.items = [];
    }
    if (!statusData.map || typeof statusData.map !== "object") {
        statusData.map = {};
    }
    if (typeof statusData.default !== "string") {
        statusData.default = "";
    }
    let statusLabelsMap = statusData.map || {};

    const defaultCcDataElement = document.getElementById("theme-leads-default-cc-data");
    let defaultCcData = { list: [], display: "" };
    if (defaultCcDataElement) {
        try {
            const parsedDefaultCc = JSON.parse(defaultCcDataElement.textContent);
            if (parsedDefaultCc && typeof parsedDefaultCc === "object") {
                defaultCcData = parsedDefaultCc;
            }
        } catch (error) {
            defaultCcData = { list: [], display: "" };
        }
    }
    if (!Array.isArray(defaultCcData.list)) {
        defaultCcData.list = [];
    }
    if (typeof defaultCcData.display !== "string") {
        defaultCcData.display = defaultCcData.list.join(", ") || "";
    }
    let defaultCcList = defaultCcData.list.slice();

    const mailerDefaults = { host: "", port: "", encryption: "", username: "", password: "", from_email: "", from_name: "" };
    const mailerDataElement = document.getElementById("theme-leads-mailer-data");
    let mailerData = Object.assign({}, mailerDefaults);
    if (mailerDataElement) {
        try {
            const parsedMailer = JSON.parse(mailerDataElement.textContent);
            if (parsedMailer && typeof parsedMailer === "object") {
                mailerData = Object.assign({}, mailerDefaults, parsedMailer);
            }
        } catch (error) {
            mailerData = Object.assign({}, mailerDefaults);
        }
    }
    Object.keys(mailerDefaults).forEach(function(key) {
        if (typeof mailerData[key] !== "string") {
            mailerData[key] = "";
        }
    });

    const removeEmailLabel = {$remove_email_label_json};
    const sendingLabel = {$sending_label_json};
    const savingLabel = {$saving_label_json};
    const sendSuccessMessage = {$send_success_message_json};
    const sendErrorMessage = {$send_error_message_json};
    const lastResponseLabel = {$last_response_label_json};
    const selectTemplateLabel = {$select_template_label_json};
    const noPlaceholdersLabel = {$no_placeholders_label_json};
    const templateSavedMessage = {$template_saved_label_json};
    const templateDeletedMessage = {$template_deleted_label_json};
    const templateErrorMessage = {$template_error_label_json};
    const changesSavedMessage = {$changes_saved_message_json};
    const templateSaveLabel = {$template_save_button_json};
    const templateDeleteLabel = {$template_delete_button_json};
    const placeholdersLabel = {$placeholders_label_json};
    const formPlaceholdersLabel = {$form_placeholders_heading_json};
    const systemPlaceholdersLabel = {$system_placeholders_heading_json};
    const templateNameLabel = {$template_name_label_json};
    const templateSubjectLabel = {$template_subject_label_json};
    const templateMessageLabel = {$template_message_label_json};
    const whatsappLabel = {$whatsapp_label_json};
    const ajaxUrl = typeof window.ajaxurl !== "undefined" ? window.ajaxurl : "";
    const spinnerClass = "theme-leads-spinner";
    const spinnerInlineClass = "theme-leads-spinner--inline";
    const statusesSavedMessage = {$statuses_saved_label_json};
    const statusesErrorMessage = {$statuses_error_label_json};
    const defaultCcSavedMessage = {$default_cc_saved_label_json};
    const defaultCcErrorMessage = {$default_cc_error_label_json};
    const mailerSavedMessage = {$mailer_saved_label_json};
    const mailerErrorMessage = {$mailer_error_label_json};
    const columnsSavedMessage = {$columns_saved_label_json};
    const columnsErrorMessage = {$columns_error_label_json};
    const columnsMissingMessage = {$columns_missing_label_json};
    const bulkNoSelectionMessage = {$bulk_no_selection_label_json} || "";
    const bulkDeleteConfirmMessage = {$bulk_delete_confirm_label_json} || "";
    let systemPlaceholderDefinitions = {$system_placeholder_definitions_json};
    if (!systemPlaceholderDefinitions || typeof systemPlaceholderDefinitions !== "object") {
        systemPlaceholderDefinitions = {};
    }
    const emailSplitRegex = new RegExp({$email_split_pattern_json});

    const formSelect = document.getElementById("theme-leads-form");
    let currentFormSlug = formSelect && typeof formSelect.value === "string" ? formSelect.value : "";

    const columnsToggle = document.querySelector(".theme-leads-columns-toggle");
    const columnsPanel = document.querySelector(".theme-leads-columns-panel");
    const columnsForm = document.querySelector(".theme-leads-columns-form");
    const templateToggle = document.querySelector(".theme-leads-template-toggle");
    const templatePanel = document.querySelector(".theme-leads-template-panel");
    const templateList = document.querySelector(".theme-leads-template-list");

    if (templateList) {
        if (typeof templateList.dataset.currentForm === "string" && templateList.dataset.currentForm) {
            currentFormSlug = templateList.dataset.currentForm;
        }
        templateList.dataset.currentForm = currentFormSlug;
    }

    syncNewTemplateFormCurrentSlug();
    const statusToggle = document.querySelector(".theme-leads-status-toggle");
    const statusPanel = document.querySelector(".theme-leads-status-panel");
    const defaultToggle = document.querySelector(".theme-leads-defaults-toggle");
    const defaultPanel = document.querySelector(".theme-leads-defaults-panel");
    const mailerToggle = document.querySelector(".theme-leads-mailer-toggle");
    const mailerPanel = document.querySelector(".theme-leads-mailer-panel");
    let activeTemplateField = null;
    const statusForm = document.querySelector(".theme-leads-statuses-form");
    const defaultCcForm = document.querySelector(".theme-leads-default-cc-form");
    const mailerForm = document.querySelector(".theme-leads-mailer-form");

    const panelConfigs = [
        { toggle: columnsToggle, panel: columnsPanel },
        { toggle: statusToggle, panel: statusPanel },
        { toggle: defaultToggle, panel: defaultPanel },
        { toggle: mailerToggle, panel: mailerPanel },
        { toggle: templateToggle, panel: templatePanel, onOpen: refreshTemplatePlaceholderButtons }
    ];

    function closestElement(node, selector) {
        if (!node) {
            return null;
        }
        if (typeof node.closest === "function") {
            return node.closest(selector);
        }
        let element = node.nodeType === 1 ? node : node.parentElement;
        while (element) {
            if (element.matches && element.matches(selector)) {
                return element;
            }
            element = element.parentElement;
        }
        return null;
    }

    panelConfigs.forEach(function(config) {
        if (!config.toggle || !config.panel) {
            return;
        }

        const closeButton = config.panel.querySelector(".theme-leads-panel-close");
        config.closeButton = closeButton;

        config.toggle.addEventListener("click", function(event) {
            event.preventDefault();
            const expanded = config.toggle.getAttribute("aria-expanded") === "true";
            setPanelState(config, !expanded);
        });

        if (closeButton) {
            closeButton.addEventListener("click", function(event) {
                event.preventDefault();
                setPanelState(config, false);
            });
        }
    });

    document.addEventListener("keydown", function(event) {
        if (event.key === "Escape") {
            closeAllPanels();
        }
    });

    closeAllPanels();

    function setPanelState(config, open) {
        panelConfigs.forEach(function(entry) {
            if (!entry.panel || !entry.toggle) {
                return;
            }

            const shouldOpen = entry === config && open;
            entry.toggle.setAttribute("aria-expanded", shouldOpen ? "true" : "false");
            entry.panel.setAttribute("aria-hidden", shouldOpen ? "false" : "true");
            entry.panel.setAttribute("aria-modal", shouldOpen ? "true" : "false");

            if (shouldOpen) {
                entry.panel.removeAttribute("hidden");
                if (typeof entry.onOpen === "function") {
                    entry.onOpen();
                }
            } else {
                entry.panel.setAttribute("hidden", "hidden");
            }
        });
    }

    function closeAllPanels() {
        panelConfigs.forEach(function(entry) {
            if (!entry.panel || !entry.toggle) {
                return;
            }
            entry.toggle.setAttribute("aria-expanded", "false");
            entry.panel.setAttribute("aria-hidden", "true");
            entry.panel.setAttribute("aria-modal", "false");
            entry.panel.setAttribute("hidden", "hidden");
        });
    }

    if (columnsForm) {
        columnsForm.addEventListener("submit", function(event) {
            if (!ajaxUrl) {
                return;
            }

            event.preventDefault();

            if (columnsForm._themeLeadsSubmitting) {
                return;
            }

            const feedbackEl = columnsForm.querySelector(".theme-leads-columns-feedback");

            if (feedbackEl) {
                feedbackEl.textContent = "";
                feedbackEl.classList.remove("is-error", "is-success");
            }

            const selected = columnsForm.querySelectorAll("input[name='table_columns[]']:checked");
            if (!selected.length) {
                if (feedbackEl) {
                    feedbackEl.textContent = columnsMissingMessage;
                    feedbackEl.classList.remove("is-success");
                    feedbackEl.classList.add("is-error");
                } else {
                    window.alert(columnsMissingMessage);
                }
                return;
            }

            columnsForm._themeLeadsSubmitting = true;

            const submitButton = columnsForm.querySelector("button[type='submit']");

            if (submitButton) {
                setBusyState(submitButton, true);
            } else {
                setBusyState(columnsForm, true);
            }

            const formData = new FormData(columnsForm);
            formData.set("action", "theme_leads_columns_save");
            const nonceValue = formData.get("_wpnonce");
            if (nonceValue && !formData.get("_ajax_nonce")) {
                formData.set("_ajax_nonce", nonceValue);
            }

            fetch(ajaxUrl, {
                method: "POST",
                credentials: "same-origin",
                body: formData
            })
                .then(function(response) {
                    if (!response.ok) {
                        throw new Error(columnsErrorMessage);
                    }
                    return response.json();
                })
                .then(function(payload) {
                    if (!payload || typeof payload !== "object") {
                        throw new Error(columnsErrorMessage);
                    }
                    if (payload.success) {
                        handleColumnsSaveSuccess(payload.data || {}, columnsForm, feedbackEl);
                    } else {
                        const message = payload.data && payload.data.message ? payload.data.message : columnsErrorMessage;
                        throw new Error(message);
                    }
                })
                .catch(function(error) {
                    if (feedbackEl) {
                        feedbackEl.textContent = error && error.message ? error.message : columnsErrorMessage;
                        feedbackEl.classList.remove("is-success");
                        feedbackEl.classList.add("is-error");
                    } else {
                        window.alert(error && error.message ? error.message : columnsErrorMessage);
                    }
                })
                .finally(function() {
                    if (submitButton) {
                        setBusyState(submitButton, false);
                    } else {
                        setBusyState(columnsForm, false);
                    }
                    columnsForm._themeLeadsSubmitting = false;
                });
        });
    }

    if (statusForm) {
        statusForm.addEventListener("submit", function(event) {
            if (!ajaxUrl) {
                return;
            }

            event.preventDefault();

            if (statusForm._themeLeadsSubmitting) {
                return;
            }

            statusForm._themeLeadsSubmitting = true;

            const submitButton = statusForm.querySelector("button[type='submit']");
            const feedbackEl = statusForm.querySelector(".theme-leads-statuses-feedback");

            if (feedbackEl) {
                feedbackEl.textContent = "";
                feedbackEl.classList.remove("is-error", "is-success");
            }

            if (submitButton) {
                setBusyState(submitButton, true);
            } else {
                setBusyState(statusForm, true);
            }

            const formData = new FormData(statusForm);
            formData.set("action", "theme_leads_statuses_save");
            const nonceValue = formData.get("_wpnonce");
            if (nonceValue && !formData.get("_ajax_nonce")) {
                formData.set("_ajax_nonce", nonceValue);
            }

            fetch(ajaxUrl, {
                method: "POST",
                credentials: "same-origin",
                body: formData
            })
                .then(function(response) {
                    if (!response.ok) {
                        throw new Error(statusesErrorMessage);
                    }
                    return response.json();
                })
                .then(function(payload) {
                    if (!payload || typeof payload !== "object") {
                        throw new Error(statusesErrorMessage);
                    }
                    if (payload.success) {
                        handleStatusesSaveSuccess(payload.data || {}, statusForm, feedbackEl);
                    } else {
                        const message = payload.data && payload.data.message ? payload.data.message : statusesErrorMessage;
                        throw new Error(message);
                    }
                })
                .catch(function(error) {
                    if (feedbackEl) {
                        feedbackEl.textContent = error && error.message ? error.message : statusesErrorMessage;
                        feedbackEl.classList.remove("is-success");
                        feedbackEl.classList.add("is-error");
                    } else {
                        window.alert(error && error.message ? error.message : statusesErrorMessage);
                    }
                })
                .finally(function() {
                    if (submitButton) {
                        setBusyState(submitButton, false);
                    } else {
                        setBusyState(statusForm, false);
                    }
                    statusForm._themeLeadsSubmitting = false;
                });
        });
    }

    if (defaultCcForm) {
        defaultCcForm.addEventListener("submit", function(event) {
            if (!ajaxUrl) {
                return;
            }

            event.preventDefault();

            if (defaultCcForm._themeLeadsSubmitting) {
                return;
            }

            defaultCcForm._themeLeadsSubmitting = true;

            const submitButton = defaultCcForm.querySelector("button[type='submit']");
            const feedbackEl = defaultCcForm.querySelector(".theme-leads-default-cc-feedback");

            if (feedbackEl) {
                feedbackEl.textContent = "";
                feedbackEl.classList.remove("is-error", "is-success");
            }

            if (submitButton) {
                setBusyState(submitButton, true);
            } else {
                setBusyState(defaultCcForm, true);
            }

            const formData = new FormData(defaultCcForm);
            formData.set("action", "theme_leads_default_cc_save");
            const nonceValue = formData.get("_wpnonce");
            if (nonceValue && !formData.get("_ajax_nonce")) {
                formData.set("_ajax_nonce", nonceValue);
            }

            fetch(ajaxUrl, {
                method: "POST",
                credentials: "same-origin",
                body: formData
            })
                .then(function(response) {
                    if (!response.ok) {
                        throw new Error(defaultCcErrorMessage);
                    }
                    return response.json();
                })
                .then(function(payload) {
                    if (!payload || typeof payload !== "object") {
                        throw new Error(defaultCcErrorMessage);
                    }
                    if (payload.success) {
                        handleDefaultCcSaveSuccess(payload.data || {}, defaultCcForm, feedbackEl);
                    } else {
                        const message = payload.data && payload.data.message ? payload.data.message : defaultCcErrorMessage;
                        throw new Error(message);
                    }
                })
                .catch(function(error) {
                    if (feedbackEl) {
                        feedbackEl.textContent = error && error.message ? error.message : defaultCcErrorMessage;
                        feedbackEl.classList.remove("is-success");
                        feedbackEl.classList.add("is-error");
                    } else {
                        window.alert(error && error.message ? error.message : defaultCcErrorMessage);
                    }
                })
                .finally(function() {
                    if (submitButton) {
                        setBusyState(submitButton, false);
                    } else {
                        setBusyState(defaultCcForm, false);
                    }
                    defaultCcForm._themeLeadsSubmitting = false;
                });
        });
    }

    if (mailerForm) {
        updateMailerForm(mailerForm, mailerData);

        mailerForm.addEventListener("submit", function(event) {
            if (!ajaxUrl) {
                return;
            }

            event.preventDefault();

            if (mailerForm._themeLeadsSubmitting) {
                return;
            }

            mailerForm._themeLeadsSubmitting = true;

            const submitButton = mailerForm.querySelector("button[type='submit']");
            const feedbackEl = mailerForm.querySelector(".theme-leads-mailer-feedback");

            if (feedbackEl) {
                feedbackEl.textContent = "";
                feedbackEl.classList.remove("is-error", "is-success");
            }

            if (submitButton) {
                setBusyState(submitButton, true);
            } else {
                setBusyState(mailerForm, true);
            }

            const formData = new FormData(mailerForm);
            formData.set("action", "theme_leads_mailer_save");
            const nonceValue = formData.get("_wpnonce");
            if (nonceValue && !formData.get("_ajax_nonce")) {
                formData.set("_ajax_nonce", nonceValue);
            }

            fetch(ajaxUrl, {
                method: "POST",
                credentials: "same-origin",
                body: formData
            })
                .then(function(response) {
                    if (!response.ok) {
                        throw new Error(mailerErrorMessage);
                    }
                    return response.json();
                })
                .then(function(payload) {
                    if (!payload || typeof payload !== "object") {
                        throw new Error(mailerErrorMessage);
                    }
                    if (payload.success) {
                        handleMailerSaveSuccess(payload.data || {}, mailerForm, feedbackEl);
                    } else {
                        const message = payload.data && payload.data.message ? payload.data.message : mailerErrorMessage;
                        throw new Error(message);
                    }
                })
                .catch(function(error) {
                    if (feedbackEl) {
                        feedbackEl.textContent = error && error.message ? error.message : mailerErrorMessage;
                        feedbackEl.classList.remove("is-success");
                        feedbackEl.classList.add("is-error");
                    } else {
                        window.alert(error && error.message ? error.message : mailerErrorMessage);
                    }
                })
                .finally(function() {
                    if (submitButton) {
                        setBusyState(submitButton, false);
                    } else {
                        setBusyState(mailerForm, false);
                    }
                    mailerForm._themeLeadsSubmitting = false;
                });
        });
    }

    function handleColumnsSaveSuccess(data, form, feedbackEl) {
        if (feedbackEl) {
            feedbackEl.textContent = columnsSavedMessage;
            feedbackEl.classList.remove("is-error");
            feedbackEl.classList.add("is-success");
        }

        if (form && data && typeof data === "object" && typeof data.save_nonce === "string") {
            const nonceField = form.querySelector("input[name='_wpnonce']");
            if (nonceField) {
                nonceField.value = data.save_nonce;
            }
        }

        window.setTimeout(function() {
            window.location.reload();
        }, 600);
    }

    function handleStatusesSaveSuccess(data, form, feedbackEl) {
        if (data && typeof data === "object" && data.statuses && typeof data.statuses === "object") {
            const items = Array.isArray(data.statuses.items) ? data.statuses.items : [];
            const map = data.statuses.map && typeof data.statuses.map === "object" ? data.statuses.map : {};
            const defaultSlug = typeof data.statuses.default === "string" ? data.statuses.default : "";
            statusData = {
                items: items,
                map: map,
                default: defaultSlug
            };
            statusLabelsMap = map;
            refreshStatusSelects();
            refreshStatusDisplays();
        }

        if (data && typeof data === "object" && data.textarea) {
            const textarea = form ? form.querySelector("textarea[name='lead_statuses']") : null;
            if (textarea) {
                textarea.value = data.textarea;
            }
        }

        if (data && typeof data === "object" && data.save_nonce) {
            const nonceField = form ? form.querySelector("input[name='_wpnonce']") : null;
            if (nonceField) {
                nonceField.value = data.save_nonce;
            }
        }

        if (feedbackEl) {
            feedbackEl.textContent = statusesSavedMessage;
            feedbackEl.classList.remove("is-error");
            feedbackEl.classList.add("is-success");
        }
    }

    function handleDefaultCcSaveSuccess(data, form, feedbackEl) {
        if (data && typeof data === "object" && data.cc && typeof data.cc === "object") {
            const list = Array.isArray(data.cc.list) ? data.cc.list : [];
            const display = typeof data.cc.display === "string" ? data.cc.display : list.join(", ") || "";
            defaultCcData = {
                list: list,
                display: display
            };
            defaultCcList = list.slice();
            refreshDefaultCcDisplay(defaultCcList);
        }

        if (data && typeof data === "object" && data.textarea) {
            const textarea = form ? form.querySelector("textarea[name='lead_default_cc']") : null;
            if (textarea) {
                textarea.value = data.textarea;
            }
        }

        if (data && typeof data === "object" && data.save_nonce) {
            const nonceField = form ? form.querySelector("input[name='_wpnonce']") : null;
            if (nonceField) {
                nonceField.value = data.save_nonce;
            }
        }

        if (feedbackEl) {
            feedbackEl.textContent = defaultCcSavedMessage;
            feedbackEl.classList.remove("is-error");
            feedbackEl.classList.add("is-success");
        }
    }

    function handleMailerSaveSuccess(data, form, feedbackEl) {
        if (data && typeof data === "object" && data.mailer && typeof data.mailer === "object") {
            mailerData = Object.assign({}, mailerDefaults, data.mailer);
            Object.keys(mailerDefaults).forEach(function(key) {
                if (typeof mailerData[key] !== "string") {
                    mailerData[key] = "";
                }
            });
            updateMailerForm(form, mailerData);
        }

        if (data && typeof data === "object" && data.save_nonce) {
            const nonceField = form ? form.querySelector("input[name='_wpnonce']") : null;
            if (nonceField) {
                nonceField.value = data.save_nonce;
            }
        }

        if (feedbackEl) {
            feedbackEl.textContent = mailerSavedMessage;
            feedbackEl.classList.remove("is-error");
            feedbackEl.classList.add("is-success");
        }
    }

    function refreshStatusSelects() {
        const items = Array.isArray(statusData.items) ? statusData.items : [];
        const defaultSlug = typeof statusData.default === "string" && statusData.default ? statusData.default : (items.length ? items[0].slug : "");

        document.querySelectorAll(".theme-leads-status-select").forEach(function(select) {
            const previousValue = select.value;

            while (select.firstChild) {
                select.removeChild(select.firstChild);
            }

            const values = [];

            items.forEach(function(item) {
                if (!item || typeof item !== "object") {
                    return;
                }
                const slug = item.slug || "";
                if (!slug) {
                    return;
                }
                const label = item.label || slug;
                const option = document.createElement("option");
                option.value = slug;
                option.textContent = label;
                select.appendChild(option);
                values.push(slug);
            });

            let targetValue = previousValue && values.indexOf(previousValue) !== -1 ? previousValue : "";
            if (!targetValue) {
                targetValue = defaultSlug && values.indexOf(defaultSlug) !== -1 ? defaultSlug : "";
            }
            if (!targetValue && values.length) {
                targetValue = values[0];
            }

            if (targetValue) {
                select.value = targetValue;
            }

            select.disabled = values.length === 0;
        });
    }

    function refreshStatusDisplays() {
        document.querySelectorAll(".theme-leads-summary-status").forEach(function(cell) {
            const slug = cell.getAttribute("data-status") || "";
            if (slug && statusLabelsMap[slug]) {
                cell.textContent = statusLabelsMap[slug];
            } else if (!slug) {
                cell.textContent = "";
            } else {
                cell.textContent = slug;
            }
        });

        document.querySelectorAll(".theme-leads-template-context").forEach(function(contextEl) {
            const statusSlug = contextEl.dataset.statusSlug || contextEl.dataset.status || "";
            const label = statusSlug && statusLabelsMap[statusSlug] ? statusLabelsMap[statusSlug] : (contextEl.dataset.status || statusSlug);
            contextEl.dataset.statusSlug = statusSlug || "";
            contextEl.dataset.status = label || "";
        });
    }

    function refreshDefaultCcDisplay(list) {
        const ccList = Array.isArray(list) ? list.slice() : [];
        const ccString = ccList.join(", ");

        document.querySelectorAll(".theme-leads-template-context").forEach(function(contextEl) {
            const baseList = parseEmailList(contextEl.dataset.ccBase || "");
            contextEl.dataset.defaultCc = ccString;
            const combined = mergeEmailLists(baseList, ccList);
            contextEl.dataset.cc = combined.join(", ");
        });
    }

    function updateMailerForm(form, data) {
        if (!form) {
            return;
        }

        const mapping = {
            mailer_host: "host",
            mailer_port: "port",
            mailer_encryption: "encryption",
            mailer_username: "username",
            mailer_password: "password",
            mailer_from_email: "from_email",
            mailer_from_name: "from_name"
        };

        Object.keys(mapping).forEach(function(fieldName) {
            const field = form.querySelector('[name="' + fieldName + '"]');
            if (!field) {
                return;
            }

            const key = mapping[fieldName];
            const value = data && typeof data === "object" && typeof data[key] === "string" ? data[key] : "";

            if (field.tagName === "SELECT") {
                field.value = value;
                if (field.value !== value && field.options.length) {
                    field.selectedIndex = 0;
                }
            } else {
                field.value = value;
            }
        });
    }

    function parseEmailList(value) {
        if (!value) {
            return [];
        }
        if (Array.isArray(value)) {
            return value.filter(function(item) { return !!item; }).map(function(item) { return String(item).trim(); }).filter(Boolean);
        }
        return String(value)
            .split(emailSplitRegex)
            .map(function(item) { return item.trim(); })
            .filter(Boolean);
    }

    function mergeEmailLists(primary, secondary) {
        const seen = new Set();
        const merged = [];

        [].concat(primary || [], secondary || []).forEach(function(item) {
            if (!item) {
                return;
            }
            const email = String(item).trim();
            if (!email) {
                return;
            }
            if (!seen.has(email.toLowerCase())) {
                seen.add(email.toLowerCase());
                merged.push(email);
            }
        });

        return merged;
    }

    refreshStatusSelects();
    refreshStatusDisplays();
    refreshDefaultCcDisplay(defaultCcList);

    const rows = document.querySelectorAll(".theme-leads-summary");
    rows.forEach(function(row) {
        row.addEventListener("click", function(event) {
            if (closestElement(event.target, ".theme-leads-no-toggle") || closestElement(event.target, "a") || closestElement(event.target, "button")) {
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

    const bulkForm = document.getElementById("theme-leads-bulk-form");
    const bulkSelectAll = document.getElementById("theme-leads-select-all");
    const bulkStatusSelect = document.getElementById("theme-leads-bulk-status");

    function getBulkCheckboxes() {
        return Array.prototype.slice.call(document.querySelectorAll(".theme-leads-select"));
    }

    function updateBulkActionState() {
        if (!bulkForm) {
            return;
        }

        const checkboxes = getBulkCheckboxes();
        const selected = checkboxes.filter(function(box) { return box.checked; });
        const hasSelection = selected.length > 0;

        const statusButton = bulkForm.querySelector("button[name='bulk_action'][value='status']");
        const deleteButton = bulkForm.querySelector("button[name='bulk_action'][value='delete']");

        if (statusButton) {
            const statusValue = bulkStatusSelect && typeof bulkStatusSelect.value === "string" ? bulkStatusSelect.value : "";
            statusButton.disabled = !hasSelection || !statusValue;
        }

        if (deleteButton) {
            deleteButton.disabled = !hasSelection;
        }

        if (bulkSelectAll) {
            const total = checkboxes.length;
            bulkSelectAll.indeterminate = hasSelection && selected.length < total;
            bulkSelectAll.checked = hasSelection && selected.length === total;
        }
    }

    function bindBulkCheckboxes() {
        getBulkCheckboxes().forEach(function(checkbox) {
            checkbox.addEventListener("change", updateBulkActionState);
        });
    }

    bindBulkCheckboxes();
    updateBulkActionState();

    if (bulkSelectAll) {
        bulkSelectAll.addEventListener("change", function() {
            const checkboxes = getBulkCheckboxes();
            checkboxes.forEach(function(box) {
                box.checked = bulkSelectAll.checked;
            });
            updateBulkActionState();
        });
    }

    if (bulkStatusSelect) {
        bulkStatusSelect.addEventListener("change", updateBulkActionState);
    }

    if (bulkForm) {
        bulkForm.addEventListener("submit", function(event) {
            const submitter = event.submitter || null;
            const selected = getBulkCheckboxes().filter(function(box) { return box.checked; });

            if (!selected.length) {
                event.preventDefault();
                if (bulkNoSelectionMessage) {
                    window.alert(bulkNoSelectionMessage);
                }
                return;
            }

            if (submitter && submitter.name === "bulk_action" && submitter.value === "delete") {
                if (bulkDeleteConfirmMessage && !window.confirm(bulkDeleteConfirmMessage)) {
                    event.preventDefault();
                }
            }
        });
    }

    document.querySelectorAll(".theme-leads-status-select").forEach(function(select) {
        const form = select.closest("form");
        if (!form) {
            return;
        }

        if (form.classList.contains("theme-leads-status-form")) {
            select.addEventListener("change", function() {
                if (!ajaxUrl) {
                    form.submit();
                    return;
                }
                submitLeadViaAjax(form, select, null, "save");
            });
        } else if (form.classList.contains("theme-leads-detail-form")) {
            select.addEventListener("change", function() {
                if (!ajaxUrl) {
                    return;
                }
                const feedbackEl = form.querySelector(".theme-leads-form-feedback");
                submitLeadViaAjax(form, select, feedbackEl, "save");
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
        const submitButtons = form.querySelectorAll("button[name='lead_submit_action']");

        if (submitButtons.length) {
            const defaultButton = Array.prototype.find.call(submitButtons, function(button) {
                return button.value === "save";
            });
            if (defaultButton) {
                form._themeLeadsLastSubmitter = defaultButton;
            }

            submitButtons.forEach(function(button) {
                button.addEventListener("click", function(event) {
                    form._themeLeadsLastSubmitter = button;

                    if (!ajaxUrl) {
                        return;
                    }

                    if (form._themeLeadsSubmitting) {
                        event.preventDefault();
                        return;
                    }

                    const actionValue = button.value === "send" ? "send" : "save";
                    event.preventDefault();
                    submitLeadViaAjax(form, button, feedbackEl, actionValue);
                });
            });
        }

        if (emailFields) {
            emailFields.addEventListener("click", function(event) {
                const removeButton = closestElement(event.target, ".theme-leads-email-remove");
                if (removeButton) {
                    event.preventDefault();
                    const field = closestElement(removeButton, ".theme-leads-email-field");
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
            if (!ajaxUrl) {
                return;
            }

            let submitter = event.submitter || document.activeElement;
            if (!submitter || submitter.form !== form) {
                submitter = null;
            }

            if (!submitter || submitter.name !== "lead_submit_action") {
                submitter = form._themeLeadsLastSubmitter || form.querySelector("button[name='lead_submit_action'][value='save']");
            }

            if (!submitter) {
                return;
            }

            if (form._themeLeadsSubmitting) {
                event.preventDefault();
                return;
            }

            event.preventDefault();
            const actionValue = submitter.value === "send" ? "send" : "save";
            submitLeadViaAjax(form, submitter, feedbackEl, actionValue);
        });
    });

    initializeTemplateManager();

    document.addEventListener("focusin", function(event) {
        const field = event.target;
        if (!field) {
            return;
        }
        if ((field.tagName === "INPUT" || field.tagName === "TEXTAREA") && (field.closest(".theme-leads-template-form") || field.closest(".theme-leads-detail-form"))) {
            activeTemplateField = field;
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

    function setBusyState(element, busy) {
        if (!element) {
            return;
        }

        const isButton = element.tagName === "BUTTON";
        const spinnerContainer = isButton ? element : (element.closest(".theme-leads-form-group") || element.parentElement || element);

        if (busy) {
            element.classList.add("is-busy");
            element.setAttribute("aria-busy", "true");
            if ("disabled" in element) {
                element.disabled = true;
            }

            if (!element._themeLeadsSpinner && spinnerContainer) {
                const spinner = document.createElement("span");
                spinner.className = isButton ? spinnerClass : spinnerClass + " " + spinnerInlineClass;
                spinner.setAttribute("aria-hidden", "true");
                spinnerContainer.appendChild(spinner);
                element._themeLeadsSpinner = spinner;
            }
        } else {
            element.classList.remove("is-busy");
            element.removeAttribute("aria-busy");
            if ("disabled" in element) {
                element.disabled = false;
            }

            if (element._themeLeadsSpinner) {
                if (element._themeLeadsSpinner.parentNode) {
                    element._themeLeadsSpinner.parentNode.removeChild(element._themeLeadsSpinner);
                }
                delete element._themeLeadsSpinner;
            }
        }
    }

    function initializeTemplateManager() {
        if (templatePanel) {
            templatePanel.addEventListener("click", function(event) {
                const placeholderButton = closestElement(event.target, ".theme-leads-placeholder-button");
                if (placeholderButton) {
                    event.preventDefault();
                    insertPlaceholder(placeholderButton.dataset.placeholder || "");
                }
            });
        }

        document.querySelectorAll(".theme-leads-template-form").forEach(function(form) {
            bindTemplateForm(form);
        });

        document.querySelectorAll(".theme-leads-template-delete-form").forEach(function(form) {
            bindTemplateDeleteForm(form);
        });

        refreshTemplatePlaceholderButtons();
        refreshTemplateSelects();
    }

    function bindTemplateForm(form) {
        if (!form || form.dataset.templateFormBound === "true") {
            return;
        }
        form.dataset.templateFormBound = "true";

        form.addEventListener("submit", function(event) {
            event.preventDefault();
            const submitter = event.submitter || form.querySelector(".theme-leads-template-save");
            submitTemplateForm(form, submitter);
        });
    }

    function bindTemplateDeleteForm(form) {
        if (!form || form.dataset.templateDeleteBound === "true") {
            return;
        }
        form.dataset.templateDeleteBound = "true";

        form.addEventListener("submit", function(event) {
            event.preventDefault();
            const submitter = form.querySelector('.theme-leads-template-delete') || form.querySelector('button[type="submit"]');
            submitTemplateDelete(form, submitter);
        });
    }

    function submitTemplateForm(form, submitter) {
        if (!ajaxUrl) {
            form.submit();
            return;
        }

        const formData = new FormData(form);
        formData.set("action", "theme_leads_template_save");
        formData.set("current_form", getCurrentFormSlug());

        const feedbackEl = form.querySelector(".theme-leads-template-feedback");
        if (feedbackEl) {
            feedbackEl.textContent = "";
            feedbackEl.classList.remove("is-error", "is-success");
        }

        if (submitter) {
            if (submitter.tagName === "BUTTON") {
                submitter.dataset.originalLabel = submitter.textContent;
                submitter.textContent = savingLabel;
            }
            setBusyState(submitter, true);
        }

        fetch(ajaxUrl, {
            method: "POST",
            credentials: "same-origin",
            body: formData
        })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error(templateErrorMessage);
                }
                return response.json();
            })
            .then(function(payload) {
                if (!payload || typeof payload !== "object") {
                    throw new Error(templateErrorMessage);
                }

                if (payload.success) {
                    templates = payload.data && payload.data.templates ? payload.data.templates : templates;
                    handleTemplateSaveSuccess(form, payload.data || {}, feedbackEl);
                } else {
                    const message = payload.data && payload.data.message ? payload.data.message : templateErrorMessage;
                    throw new Error(message);
                }
            })
            .catch(function(error) {
                const message = error && error.message ? error.message : templateErrorMessage;
                if (feedbackEl) {
                    feedbackEl.textContent = message;
                    feedbackEl.classList.remove("is-success");
                    feedbackEl.classList.add("is-error");
                } else {
                    window.alert(message);
                }
            })
            .finally(function() {
                if (submitter) {
                    if (submitter.tagName === "BUTTON" && submitter.dataset.originalLabel) {
                        submitter.textContent = submitter.dataset.originalLabel;
                        delete submitter.dataset.originalLabel;
                    }
                    setBusyState(submitter, false);
                }
            });
    }

    function submitTemplateDelete(form, submitter) {
        if (!ajaxUrl) {
            form.submit();
            return;
        }

        const formData = new FormData(form);
        formData.set("action", "theme_leads_template_delete");
        formData.set("current_form", getCurrentFormSlug());

        const card = form.closest(".theme-leads-template-card");

        if (submitter) {
            setBusyState(submitter, true);
        }

        fetch(ajaxUrl, {
            method: "POST",
            credentials: "same-origin",
            body: formData
        })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error(templateErrorMessage);
                }
                return response.json();
            })
            .then(function(payload) {
                if (!payload || typeof payload !== "object") {
                    throw new Error(templateErrorMessage);
                }

                if (payload.success) {
                    templates = payload.data && payload.data.templates ? payload.data.templates : templates;
                    handleTemplateDeleteSuccess(form.dataset.template, payload.data || {});
                } else {
                    const message = payload.data && payload.data.message ? payload.data.message : templateErrorMessage;
                    throw new Error(message);
                }
            })
            .catch(function(error) {
                const message = error && error.message ? error.message : templateErrorMessage;
                window.alert(message);
            })
            .finally(function() {
                if (submitter) {
                    setBusyState(submitter, false);
                }
            });
    }

    function handleTemplateSaveSuccess(form, data, feedbackEl) {
        if (feedbackEl) {
            feedbackEl.textContent = templateSavedMessage;
            feedbackEl.classList.remove("is-error");
            feedbackEl.classList.add("is-success");
        }

        let responseFormSlug = getCurrentFormSlug();
        if (data && typeof data.form_slug === "string") {
            responseFormSlug = data.form_slug;
        } else {
            const currentFormInput = form.querySelector("input[name='current_form']");
            if (currentFormInput && typeof currentFormInput.value === "string") {
                responseFormSlug = currentFormInput.value;
            }
        }

        updateCurrentFormSlug(responseFormSlug);
        syncNewTemplateFormCurrentSlug();

        const formCurrentInput = form.querySelector("input[name='current_form']");
        if (formCurrentInput) {
            formCurrentInput.value = responseFormSlug;
            formCurrentInput.defaultValue = responseFormSlug;
        }

        if (!data || !data.slug) {
            refreshTemplateSelects();
            refreshTemplatePlaceholderButtons();
            return;
        }

        const slug = data.slug;
        const templateData = data.template || (templates && templates[slug]) || {};
        const saveNonce = data.save_nonce || "";
        const deleteNonce = data.delete_nonce || "";

        if (form.dataset.template === "new") {
            addTemplateCard(slug, templateData, saveNonce, deleteNonce);
            form.reset();
        } else {
            const card = form.closest(".theme-leads-template-card");
            if (card) {
                card.dataset.template = slug;
                updateTemplateSummary(card, templateData);
                const slugInput = form.querySelector("input[name='template_slug']");
                if (slugInput) {
                    slugInput.value = slug;
                }
                const nonceInput = form.querySelector("input[name='_wpnonce']");
                if (nonceInput && saveNonce) {
                    nonceInput.value = saveNonce;
                }
                const deleteNonceInput = card.querySelector(".theme-leads-template-delete-form input[name='_wpnonce']");
                if (deleteNonceInput && deleteNonce) {
                    deleteNonceInput.value = deleteNonce;
                }
                const deleteForm = card.querySelector(".theme-leads-template-delete-form");
                if (deleteForm) {
                    deleteForm.dataset.template = slug;
                    const deleteCurrentFormInput = deleteForm.querySelector("input[name='current_form']");
                    if (deleteCurrentFormInput) {
                        deleteCurrentFormInput.value = responseFormSlug;
                        deleteCurrentFormInput.defaultValue = responseFormSlug;
                    }
                }
            }
        }

        if (data.templates) {
            templates = data.templates;
        }

        refreshTemplateSelects();
        refreshTemplatePlaceholderButtons();
    }

    function handleTemplateDeleteSuccess(slug, data) {
        let responseFormSlug = getCurrentFormSlug();
        if (data && typeof data.form_slug === "string") {
            responseFormSlug = data.form_slug;
        }
        updateCurrentFormSlug(responseFormSlug);
        syncNewTemplateFormCurrentSlug();

        if (slug) {
            const card = document.querySelector('.theme-leads-template-card[data-template="' + slug + '"]');
            if (card) {
                card.remove();
            }
        }

        if (data && data.templates) {
            templates = data.templates;
        }

        refreshTemplateSelects();
        refreshTemplatePlaceholderButtons();

        const targetFeedback = templateList ? templateList.querySelector(".theme-leads-template-new .theme-leads-template-feedback") : null;
        if (targetFeedback) {
            targetFeedback.textContent = templateDeletedMessage;
            targetFeedback.classList.remove("is-error");
            targetFeedback.classList.add("is-success");
        }
    }

    function addTemplateCard(slug, templateData, saveNonce, deleteNonce) {
        if (!templateList) {
            return;
        }

        const card = createTemplateCard(slug, templateData, saveNonce, deleteNonce);
        card.open = false;
        const newCard = templateList.querySelector(".theme-leads-template-card.theme-leads-template-new");
        if (newCard) {
            templateList.insertBefore(card, newCard);
        } else {
            templateList.appendChild(card);
        }

        const form = card.querySelector(".theme-leads-template-form");
        if (form) {
            bindTemplateForm(form);
        }
        const deleteForm = card.querySelector(".theme-leads-template-delete-form");
        if (deleteForm) {
            bindTemplateDeleteForm(deleteForm);
        }
    }

    function updateTemplateSummary(card, templateData) {
        if (!card || !templateData) {
            return;
        }
        const title = card.querySelector(".theme-leads-template-card-title");
        if (title) {
            title.textContent = templateData.label || card.dataset.template || "";
        }
        const labelField = card.querySelector("input[name='template_label']");
        if (labelField) {
            labelField.value = templateData.label || "";
        }
        const subjectField = card.querySelector("input[name='template_subject']");
        if (subjectField) {
            subjectField.value = templateData.subject || "";
        }
        const bodyField = card.querySelector("textarea[name='template_body']");
        if (bodyField) {
            bodyField.value = templateData.body || "";
        }
    }

    function createTemplateCard(slug, templateData, saveNonce, deleteNonce) {
        const details = document.createElement("details");
        details.className = "theme-leads-template-card";
        details.dataset.template = slug;
        details.open = false;

        const summary = document.createElement("summary");
        summary.className = "theme-leads-template-card-summary";

        const title = document.createElement("span");
        title.className = "theme-leads-template-card-title";
        title.textContent = templateData.label || slug;
        summary.appendChild(title);

        const icon = document.createElement("span");
        icon.className = "theme-leads-template-card-toggle-icon dashicons dashicons-arrow-down-alt2";
        icon.setAttribute("aria-hidden", "true");
        summary.appendChild(icon);

        details.appendChild(summary);

        const body = document.createElement("div");
        body.className = "theme-leads-template-card-body";

        const form = createTemplateForm(slug, templateData, saveNonce);
        body.appendChild(form);

        const deleteForm = document.createElement("form");
        deleteForm.className = "theme-leads-template-delete-form";
        deleteForm.dataset.template = slug;
        deleteForm.setAttribute("method", "post");
        appendHiddenInput(deleteForm, "action", "theme_leads_template_delete");
        appendHiddenInput(deleteForm, "template_slug", slug);
        appendHiddenInput(deleteForm, "_wpnonce", deleteNonce || "");
        appendHiddenInput(deleteForm, "current_form", getCurrentFormSlug());

        const deleteButton = document.createElement("button");
        deleteButton.type = "submit";
        deleteButton.className = "button-link theme-leads-template-delete";
        deleteButton.textContent = templateDeleteLabel;
        deleteForm.appendChild(deleteButton);

        body.appendChild(deleteForm);
        details.appendChild(body);

        return details;
    }

    function createTemplateForm(slug, templateData, saveNonce) {
        const form = document.createElement("form");
        form.className = "theme-leads-template-form";
        form.dataset.template = slug;
        form.setAttribute("method", "post");

        appendHiddenInput(form, "action", "theme_leads_template_save");
        appendHiddenInput(form, "template_action", "update");
        appendHiddenInput(form, "template_slug", slug);
        appendHiddenInput(form, "_wpnonce", saveNonce || "");
        appendHiddenInput(form, "current_form", getCurrentFormSlug());

        const placeholders = document.createElement("div");
        placeholders.className = "theme-leads-template-placeholders";
        placeholders.setAttribute("data-role", "placeholder-list");

        const placeholdersLabelEl = document.createElement("span");
        placeholdersLabelEl.className = "theme-leads-template-placeholders-label";
        placeholdersLabelEl.textContent = placeholdersLabel;
        placeholders.appendChild(placeholdersLabelEl);

        const buttonsWrapper = document.createElement("div");
        buttonsWrapper.className = "theme-leads-template-placeholder-buttons";
        buttonsWrapper.setAttribute("data-role", "placeholder-buttons");
        placeholders.appendChild(buttonsWrapper);

        form.appendChild(placeholders);

        const nameInput = document.createElement("input");
        nameInput.type = "text";
        nameInput.name = "template_label";
        nameInput.className = "widefat";
        nameInput.required = true;
        nameInput.value = templateData.label || "";
        form.appendChild(createLabeledField(templateNameLabel, nameInput));

        const subjectInput = document.createElement("input");
        subjectInput.type = "text";
        subjectInput.name = "template_subject";
        subjectInput.className = "widefat";
        subjectInput.required = true;
        subjectInput.value = templateData.subject || "";
        form.appendChild(createLabeledField(templateSubjectLabel, subjectInput));

        const bodyField = document.createElement("textarea");
        bodyField.name = "template_body";
        bodyField.className = "widefat";
        bodyField.rows = 5;
        bodyField.required = true;
        bodyField.value = templateData.body || "";
        form.appendChild(createLabeledField(templateMessageLabel, bodyField));

        const actions = document.createElement("p");
        actions.className = "theme-leads-template-actions";
        const saveButton = document.createElement("button");
        saveButton.type = "submit";
        saveButton.className = "button button-primary theme-leads-template-save";
        saveButton.textContent = templateSaveLabel;
        actions.appendChild(saveButton);
        form.appendChild(actions);

        const feedback = document.createElement("div");
        feedback.className = "theme-leads-template-feedback";
        feedback.setAttribute("aria-live", "polite");
        form.appendChild(feedback);

        return form;
    }

    function appendHiddenInput(form, name, value) {
        const input = document.createElement("input");
        input.type = "hidden";
        input.name = name;
        input.value = typeof value === "string" ? value : "";
        input.defaultValue = input.value;
        form.appendChild(input);
        return input;
    }

    function createLabeledField(labelText, fieldElement) {
        const wrapper = document.createElement("p");
        const label = document.createElement("label");
        label.appendChild(document.createTextNode(labelText));
        label.appendChild(document.createElement("br"));
        label.appendChild(fieldElement);
        wrapper.appendChild(label);
        return wrapper;
    }

    function getCurrentFormSlug() {
        return typeof currentFormSlug === "string" ? currentFormSlug : "";
    }

    function updateCurrentFormSlug(slug) {
        currentFormSlug = typeof slug === "string" ? slug : "";
        if (templateList) {
            templateList.dataset.currentForm = currentFormSlug;
        }
    }

    function syncNewTemplateFormCurrentSlug() {
        if (!templateList) {
            return;
        }
        const newTemplateForm = templateList.querySelector(".theme-leads-template-card.theme-leads-template-new .theme-leads-template-form");
        if (!newTemplateForm) {
            return;
        }
        const currentInput = newTemplateForm.querySelector("input[name='current_form']");
        if (currentInput) {
            const slug = getCurrentFormSlug();
            currentInput.value = slug;
            currentInput.defaultValue = slug;
        }
    }

    function refreshTemplateSelects() {
        if (!templates || typeof templates !== "object") {
            return;
        }

        const entries = Object.entries(templates);
        entries.sort(function(a, b) {
            const labelA = (a[1] && a[1].label ? a[1].label : a[0]).toLowerCase();
            const labelB = (b[1] && b[1].label ? b[1].label : b[0]).toLowerCase();
            if (labelA < labelB) {
                return -1;
            }
            if (labelA > labelB) {
                return 1;
            }
            return 0;
        });

        document.querySelectorAll(".theme-leads-template-select").forEach(function(select) {
            const currentValue = select.value;
            while (select.firstChild) {
                select.removeChild(select.firstChild);
            }

            const defaultOption = document.createElement("option");
            defaultOption.value = "";
            defaultOption.textContent = selectTemplateLabel;
            select.appendChild(defaultOption);

            entries.forEach(function(entry) {
                const slug = entry[0];
                const template = entry[1] || {};
                const option = document.createElement("option");
                option.value = slug;
                option.textContent = template.label || slug;
                if (slug === currentValue) {
                    option.selected = true;
                }
                select.appendChild(option);
            });
        });
    }

    function gatherPlaceholderTokens() {
        const defaultSystemTokens = ['%name%', '%email%', '%phone%', '%brand%', '%link%', '%status%', '%status_slug%', '%date%', '%date_short%', '%form_title%', '%site_name%', '%site_title%', '%recipient%', '%cc%', '%default_cc%'];
        const displaySystemTokens = ['%name%', '%brand%', '%link%', '%status%', '%site_title%'];
        const systemTokens = new Set(defaultSystemTokens);
        const formTokens = new Set();

        const formPlaceholdersElement = document.getElementById("theme-leads-form-placeholders");
        if (formPlaceholdersElement) {
            try {
                const parsedFormPlaceholders = JSON.parse(formPlaceholdersElement.textContent);
                if (Array.isArray(parsedFormPlaceholders)) {
                    parsedFormPlaceholders.forEach(function(token) {
                        if (token) {
                            formTokens.add(token);
                        }
                    });
                }
            } catch (error) {
                // Ignore invalid placeholder data.
            }
        }

        document.querySelectorAll(".theme-leads-template-context").forEach(function(contextEl) {
            if (!contextEl.dataset.placeholders) {
                return;
            }

            try {
                const parsed = JSON.parse(contextEl.dataset.placeholders);
                if (Array.isArray(parsed)) {
                    parsed.forEach(function(token) {
                        if (token) {
                            systemTokens.add(token);
                        }
                    });
                } else if (parsed && typeof parsed === "object") {
                    if (Array.isArray(parsed.system)) {
                        parsed.system.forEach(function(token) {
                            if (token) {
                                systemTokens.add(token);
                            }
                        });
                    }
                    if (Array.isArray(parsed.form)) {
                        parsed.form.forEach(function(token) {
                            if (token) {
                                formTokens.add(token);
                            }
                        });
                    }
                }
            } catch (error) {
                // Ignore invalid placeholder sets.
            }
        });

        const sections = [];
        const systemList = displaySystemTokens.reduce(function(list, token) {
            if (!systemTokens.has(token)) {
                return list;
            }

            const definition = systemPlaceholderDefinitions && Object.prototype.hasOwnProperty.call(systemPlaceholderDefinitions, token)
                ? systemPlaceholderDefinitions[token]
                : '';

            list.push({ token: token, label: definition || token });
            return list;
        }, []);
        if (systemList.length) {
            sections.push({ id: "system", label: systemPlaceholdersLabel, tokens: systemList });
        }

        const formList = Array.from(formTokens);
        if (formList.length) {
            sections.push({ id: "form", label: formPlaceholdersLabel, tokens: formList });
        }

        return sections;
    }

    function refreshTemplatePlaceholderButtons() {
        const sections = gatherPlaceholderTokens();
        document.querySelectorAll(".theme-leads-template-placeholders").forEach(function(container) {
            const buttonsWrapper = container.querySelector("[data-role='placeholder-buttons']");
            if (!buttonsWrapper) {
                return;
            }
            buttonsWrapper.innerHTML = "";

            if (!sections.length) {
                const empty = document.createElement("span");
                empty.className = "theme-leads-placeholder-empty";
                empty.textContent = noPlaceholdersLabel;
                buttonsWrapper.appendChild(empty);
                return;
            }

            sections.forEach(function(section) {
                if (!section || !Array.isArray(section.tokens) || !section.tokens.length) {
                    return;
                }

                const sectionEl = document.createElement("div");
                sectionEl.className = "theme-leads-placeholder-section";

                if (section.label) {
                    const titleEl = document.createElement("div");
                    titleEl.className = "theme-leads-placeholder-section-title";
                    titleEl.textContent = section.label;
                    sectionEl.appendChild(titleEl);
                }

                const listEl = document.createElement("div");
                listEl.className = "theme-leads-placeholder-buttons-grid";

                section.tokens.forEach(function(token) {
                    if (!token) {
                        return;
                    }
                    let tokenValue = token;
                    let tokenLabel = token;
                    if (token && typeof token === "object") {
                        tokenValue = token.token || "";
                        tokenLabel = token.label || tokenValue;
                    }
                    if (!tokenValue) {
                        return;
                    }
                    const button = document.createElement("button");
                    button.type = "button";
                    button.className = "theme-leads-placeholder-button";
                    button.dataset.placeholder = tokenValue;
                    button.textContent = tokenLabel;
                    if (tokenLabel !== tokenValue) {
                        button.title = tokenValue;
                    }
                    listEl.appendChild(button);
                });

                if (listEl.children.length) {
                    sectionEl.appendChild(listEl);
                    buttonsWrapper.appendChild(sectionEl);
                }
            });
        });
    }

    function insertPlaceholder(token) {
        if (!token) {
            return;
        }

        if (activeTemplateField && typeof activeTemplateField.selectionStart === "number") {
            const start = activeTemplateField.selectionStart;
            const end = activeTemplateField.selectionEnd;
            const value = activeTemplateField.value;
            activeTemplateField.value = value.slice(0, start) + token + value.slice(end);
            activeTemplateField.selectionStart = activeTemplateField.selectionEnd = start + token.length;
            activeTemplateField.focus();
        } else if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(token).catch(function() {
                // Ignore clipboard errors.
            });
        }
    }

    function buildContext(contextEl, form) {
        const data = {
            name: "",
            email: "",
            phone: "",
            brand: "",
            link: "",
            status: "",
            date: "",
            form_title: "",
            date_short: "",
            site_name: "",
            site_title: "",
            recipient: "",
            cc: "",
            placeholders: {
                system: [],
                form: []
            }
        };

        if (contextEl) {
            data.name = contextEl.dataset.name || "";
            data.email = contextEl.dataset.email || "";
            data.phone = contextEl.dataset.phone || "";
            data.brand = contextEl.dataset.brand || "";
            data.link = contextEl.dataset.link || "";
            const datasetStatusSlug = contextEl.dataset.statusSlug || "";
            const datasetStatusLabel = contextEl.dataset.status || "";
            data.status_slug = datasetStatusSlug;
            if (datasetStatusSlug && statusLabelsMap[datasetStatusSlug]) {
                data.status = statusLabelsMap[datasetStatusSlug];
            } else if (datasetStatusLabel) {
                data.status = datasetStatusLabel;
            } else {
                data.status = datasetStatusSlug;
            }
            data.date = contextEl.dataset.date || "";
            data.form_title = contextEl.dataset.formTitle || "";
            data.date_short = contextEl.dataset.dateShort || "";
            data.site_name = contextEl.dataset.siteName || "";
            data.site_title = contextEl.dataset.siteTitle || contextEl.dataset.siteName || "";
            data.recipient = contextEl.dataset.recipient || "";
            data.cc = contextEl.dataset.cc || "";
            data.default_cc = contextEl.dataset.defaultCc || "";
            data.cc_base = contextEl.dataset.ccBase || "";

            if (contextEl.dataset.placeholders) {
                try {
                    const parsedPlaceholders = JSON.parse(contextEl.dataset.placeholders);
                    if (Array.isArray(parsedPlaceholders)) {
                        data.placeholders = { system: parsedPlaceholders, form: [] };
                    } else if (parsedPlaceholders && typeof parsedPlaceholders === "object") {
                        const system = Array.isArray(parsedPlaceholders.system) ? parsedPlaceholders.system : [];
                        const form = Array.isArray(parsedPlaceholders.form) ? parsedPlaceholders.form : [];
                        data.placeholders = { system: system, form: form };
                    }
                } catch (error) {
                    data.placeholders = { system: [], form: [] };
                }
            }

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
        let formCcAddresses = [];
        if (emailInputs.length) {
            const addresses = Array.from(emailInputs)
                .map(function(input) { return input.value || ""; })
                .filter(function(value) { return value !== ""; });

            if (addresses.length) {
                data.email = addresses[0];
                data.recipient = addresses[0];
                formCcAddresses = addresses.slice(1);
            }
        }

        const baseCcList = parseEmailList(contextEl ? contextEl.dataset.ccBase || "" : data.cc_base || "");
        const defaultCcListLocal = parseEmailList(contextEl ? contextEl.dataset.defaultCc || "" : data.default_cc || "");
        let combinedCc = mergeEmailLists(formCcAddresses, baseCcList);
        combinedCc = mergeEmailLists(combinedCc, defaultCcListLocal);
        data.cc = combinedCc.join(", ");
        data.default_cc = defaultCcListLocal.join(", ");
        data.cc_base = baseCcList.join(", ");

        const phoneField = form.querySelector("input[name='lead_client_phone']");
        if (phoneField && phoneField.value) {
            data.phone = phoneField.value;
        }

        const brandField = form.querySelector("input[name='lead_brand']");
        if (brandField && brandField.value) {
            data.brand = brandField.value;
        }

        const linkField = form.querySelector("input[name='lead_client_link']");
        if (linkField && linkField.value) {
            data.link = linkField.value;
        }

        const nameField = form.querySelector("input[name='lead_client_name']");
        if (nameField && nameField.value) {
            data.name = nameField.value;
        }

        const statusField = form.querySelector("select[name='lead_status']");
        if (statusField && statusField.value) {
            data.status_slug = statusField.value;
            data.status = statusLabelsMap[data.status_slug] || data.status_slug || "";
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

    function submitLeadViaAjax(form, submitter, feedbackEl, submitAction) {
        form._themeLeadsSubmitting = true;

        let formData;
        if (submitter) {
            try {
                formData = new FormData(form, submitter);
            } catch (error) {
                formData = new FormData(form);
            }
        } else {
            formData = new FormData(form);
        }
        formData.set("action", "theme_leads_update");
        if (submitAction) {
            formData.set("lead_submit_action", submitAction);
        } else if (!formData.get("lead_submit_action")) {
            formData.set("lead_submit_action", "save");
        }

        const nonceValue = formData.get("_wpnonce");
        if (nonceValue && !formData.get("_ajax_nonce")) {
            formData.set("_ajax_nonce", nonceValue);
        }

        if (feedbackEl) {
            feedbackEl.textContent = "";
            feedbackEl.classList.remove("is-error", "is-success");
        }

        if (submitter) {
            const busyLabel = submitAction === "send" ? (submitter.getAttribute("data-busy-label") || sendingLabel) : savingLabel;
            if (submitter.tagName === "BUTTON") {
                submitter.dataset.originalLabel = submitter.textContent;
                submitter.textContent = busyLabel;
            }
            setBusyState(submitter, true);
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
                    handleLeadUpdateSuccess(form, payload.data || {}, feedbackEl, submitAction);
                } else {
                    const message = payload.data && payload.data.message ? payload.data.message : sendErrorMessage;
                    throw new Error(message);
                }
            })
            .catch(function(error) {
                handleLeadUpdateError(feedbackEl, error && error.message ? error.message : sendErrorMessage);
            })
            .finally(function() {
                if (submitter) {
                    if (submitter.tagName === "BUTTON" && submitter.dataset.originalLabel) {
                        submitter.textContent = submitter.dataset.originalLabel;
                        delete submitter.dataset.originalLabel;
                    }
                    setBusyState(submitter, false);
                }
                form._themeLeadsSubmitting = false;
            });
    }

    function handleLeadUpdateSuccess(form, data, feedbackEl, submitAction) {
        const action = submitAction || "save";
        const leadIdField = form.querySelector("input[name='lead_id']");
        const leadId = data && data.lead_id ? String(data.lead_id) : (leadIdField ? leadIdField.value : "");

        if (feedbackEl) {
            const successMessage = action === "send" ? sendSuccessMessage : changesSavedMessage;
            feedbackEl.textContent = successMessage;
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

        if (Object.prototype.hasOwnProperty.call(data, "brand")) {
            const brandField = form.querySelector("input[name='lead_brand']");
            if (brandField) {
                brandField.value = data.brand || "";
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

        if (data.template) {
            const templateSelect = form.querySelector("select[name='lead_template']");
            if (templateSelect) {
                templateSelect.value = data.template || "";
            }
        }

        if (data.context && Object.prototype.hasOwnProperty.call(data.context, "phone")) {
            const phoneField = form.querySelector("input[name='lead_client_phone']");
            if (phoneField) {
                phoneField.value = data.context.phone || "";
            }
        }

        if (data.context && Object.prototype.hasOwnProperty.call(data.context, "name")) {
            const nameField = form.querySelector("input[name='lead_client_name']");
            if (nameField) {
                nameField.value = data.context.name || "";
            }
        }

        if (data.context && Object.prototype.hasOwnProperty.call(data.context, "link")) {
            const linkField = form.querySelector("input[name='lead_client_link']");
            if (linkField) {
                linkField.value = data.context.link || "";
            }
        }

        if (data.context) {
            updateContextElement(form, data.context);
            refreshTemplatePlaceholderButtons();
        }

        if (Object.prototype.hasOwnProperty.call(data, "history_markup")) {
            updateHistoryBlock(form, data.history_markup);
        }

        updateSummaryRow(leadId, data);
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
        if (Object.prototype.hasOwnProperty.call(context, "brand")) {
            contextEl.dataset.brand = context.brand || "";
        }
        if (Object.prototype.hasOwnProperty.call(context, "link")) {
            contextEl.dataset.link = context.link || "";
        }
        if (Object.prototype.hasOwnProperty.call(context, "status")) {
            contextEl.dataset.status = context.status || "";
        }
        if (Object.prototype.hasOwnProperty.call(context, "status_slug")) {
            contextEl.dataset.statusSlug = context.status_slug || "";
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
        if (Object.prototype.hasOwnProperty.call(context, "site_title")) {
            contextEl.dataset.siteTitle = context.site_title || "";
        }
        if (Object.prototype.hasOwnProperty.call(context, "recipient")) {
            contextEl.dataset.recipient = context.recipient || "";
        }
        if (Object.prototype.hasOwnProperty.call(context, "cc")) {
            contextEl.dataset.cc = context.cc || "";
        }
        if (Object.prototype.hasOwnProperty.call(context, "default_cc")) {
            contextEl.dataset.defaultCc = context.default_cc || "";
        }
        if (Object.prototype.hasOwnProperty.call(context, "cc_base")) {
            contextEl.dataset.ccBase = context.cc_base || "";
        }
        if (context.payload) {
            try {
                contextEl.dataset.payload = JSON.stringify(context.payload);
            } catch (error) {
                // Ignore JSON errors when updating context payload.
            }
        }
        if (context.placeholders) {
            try {
                contextEl.dataset.placeholders = JSON.stringify(context.placeholders);
            } catch (error) {
                // Ignore serialization errors.
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
                    const statusSlugValue = data.summary.status_slug || data.status || "";
                    if (statusSlugValue) {
                        statusCell.setAttribute("data-status", statusSlugValue);
                    }
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

        if (summaryRow && data.context) {
            const phoneCell = summaryRow.querySelector("[data-role='lead-phone']");
            if (phoneCell) {
                const phoneValue = data.context.phone || "";
                if (phoneValue) {
                    const telValue = phoneValue.replace(/[^0-9+]/g, '') || phoneValue;
                    let phoneLink = phoneCell.querySelector(".theme-leads-phone-link");
                    if (!phoneLink) {
                        phoneCell.textContent = "";
                        phoneLink = document.createElement("a");
                        phoneLink.className = "theme-leads-phone-link";
                        phoneCell.appendChild(phoneLink);
                    }
                    phoneLink.href = "tel:" + telValue;
                    phoneLink.dataset.number = telValue;
                    phoneLink.textContent = phoneValue;
                } else {
                    phoneCell.textContent = "—";
                }
            }

            const actionsCell = summaryRow.querySelector(".theme-leads-actions");
            if (actionsCell) {
                const whatsappNumber = (data.context.phone || "").replace(/[^0-9]/g, "");
                const messageText = normaliseWhatsappMessage(data.response && data.response.message ? data.response.message : "");
                let whatsappLink = actionsCell.querySelector(".theme-leads-whatsapp");

                if (whatsappNumber) {
                    if (!whatsappLink) {
                        whatsappLink = document.createElement("a");
                        whatsappLink.className = "theme-leads-action theme-leads-whatsapp";
                        whatsappLink.target = "_blank";
                        whatsappLink.rel = "noopener noreferrer";
                        whatsappLink.title = whatsappLabel;
                        whatsappLink.innerHTML = '<span class="dashicons dashicons-format-chat" aria-hidden="true"></span><span class="screen-reader-text">' + whatsappLabel + '</span>';
                        const deleteForm = actionsCell.querySelector(".theme-leads-delete-form");
                        if (deleteForm) {
                            actionsCell.insertBefore(whatsappLink, deleteForm);
                        } else {
                            actionsCell.appendChild(whatsappLink);
                        }
                    }
                    whatsappLink.href = buildWhatsappHref(whatsappNumber, messageText);
                    whatsappLink.dataset.number = whatsappNumber;
                    whatsappLink.dataset.message = messageText;
                } else if (whatsappLink) {
                    whatsappLink.remove();
                }
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

    function normaliseWhatsappMessage(message) {
        if (!message) {
            return "";
        }
        const temp = document.createElement("div");
        temp.innerHTML = message;
        return temp.textContent || temp.innerText || "";
    }

    function buildWhatsappHref(number, message) {
        if (!number) {
            return "";
        }
        let href = "https://wa.me/" + number;
        if (message) {
            href += "?text=" + encodeURIComponent(message);
        }
        return href;
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
        protected function get_templates( $form_slug = '' ) {
            global $wpdb;

            $table = $this->get_templates_table_name();
            $this->maybe_create_templates_table();

            $form_slug = $this->normalise_option_form_slug( $form_slug );

            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT slug, label, subject, body, description FROM {$table} WHERE form_slug = %s ORDER BY label ASC",
                    $form_slug
                )
            );

            if ( empty( $rows ) && '' !== $form_slug ) {
                $rows = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT slug, label, subject, body, description FROM {$table} WHERE form_slug = %s ORDER BY label ASC",
                        ''
                    )
                );
            }

            if ( empty( $rows ) ) {
                return array();
            }

            $templates = array();

            foreach ( $rows as $row ) {
                $slug = sanitize_key( $row->slug );

                if ( empty( $slug ) ) {
                    continue;
                }

                $templates[ $slug ] = array(
                    'label'       => sanitize_text_field( $row->label ),
                    'subject'     => isset( $row->subject ) ? (string) $row->subject : '',
                    'body'        => isset( $row->body ) ? (string) $row->body : '',
                    'description' => isset( $row->description ) ? sanitize_textarea_field( $row->description ) : '',
                );
            }

            return $templates;
        }

        /**
         * Persist templates to the database.
         *
         * @param array $templates Templates to store.
         */
        protected function save_templates( $templates, $form_slug = '' ) {
            global $wpdb;

            $table = $this->get_templates_table_name();
            $this->maybe_create_templates_table();

            $form_slug = $this->normalise_option_form_slug( $form_slug );

            if ( empty( $templates ) || ! is_array( $templates ) ) {
                $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE form_slug = %s", $form_slug ) );
                return;
            }

            $existing_rows = $wpdb->get_results(
                $wpdb->prepare( "SELECT slug FROM {$table} WHERE form_slug = %s", $form_slug )
            );
            $existing      = array();

            if ( ! empty( $existing_rows ) ) {
                foreach ( $existing_rows as $row ) {
                    $existing[ $row->slug ] = true;
                }
            }

            foreach ( $templates as $slug => $template ) {
                $slug = sanitize_key( $slug );

                if ( empty( $slug ) ) {
                    continue;
                }

                $label       = isset( $template['label'] ) ? sanitize_text_field( $template['label'] ) : $slug;
                $subject     = isset( $template['subject'] ) ? (string) $template['subject'] : '';
                $body        = isset( $template['body'] ) ? (string) $template['body'] : '';
                $description = isset( $template['description'] ) ? sanitize_textarea_field( $template['description'] ) : '';

                if ( isset( $existing[ $slug ] ) ) {
                    $wpdb->update(
                        $table,
                        array(
                            'form_slug'   => $form_slug,
                            'label'       => $label,
                            'subject'     => $subject,
                            'body'        => $body,
                            'description' => $description,
                            'updated_at'  => current_time( 'mysql' ),
                        ),
                        array(
                            'slug'      => $slug,
                            'form_slug' => $form_slug,
                        ),
                        array( '%s', '%s', '%s', '%s', '%s', '%s' ),
                        array( '%s', '%s' )
                    );

                    unset( $existing[ $slug ] );
                } else {
                    $wpdb->insert(
                        $table,
                        array(
                            'form_slug'   => $form_slug,
                            'slug'        => $slug,
                            'label'       => $label,
                            'subject'     => $subject,
                            'body'        => $body,
                            'description' => $description,
                            'created_at'  => current_time( 'mysql' ),
                            'updated_at'  => current_time( 'mysql' ),
                        ),
                        array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
                    );
                }
            }

            if ( ! empty( $existing ) ) {
                $slugs_to_delete = array_keys( $existing );
                $placeholders    = implode( ', ', array_fill( 0, count( $slugs_to_delete ), '%s' ) );

                $wpdb->query(
                    $wpdb->prepare(
                        "DELETE FROM {$table} WHERE form_slug = %s AND slug IN ({$placeholders})",
                        array_merge( array( $form_slug ), $slugs_to_delete )
                    )
                );
            }
        }

        /**
         * Retrieve a single template by slug.
         *
         * @param string $slug Template slug.
         * @return array|null
         */
        protected function get_template( $slug, $form_slug = '' ) {
            if ( empty( $slug ) ) {
                return null;
            }

            $templates = $this->get_templates( $form_slug );

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
                    'label'       => isset( $template['label'] ) ? (string) $template['label'] : $slug,
                    'subject'     => isset( $template['subject'] ) ? (string) $template['subject'] : '',
                    'body'        => isset( $template['body'] ) ? (string) $template['body'] : '',
                    'description' => isset( $template['description'] ) ? (string) $template['description'] : '',
                );
            }

            return $prepared;
        }

        /**
         * Build template context data for server and client usage.
         *
         * @param object $lead            Lead database row.
         * @param array  $payload         Submission payload.
         * @param array  $form_field_keys Contact Form 7 field keys.
         * @return array
         */
        protected function build_template_context_data( $lead, $payload, $form_field_keys = array(), $form_slug = '' ) {
            $submitted_at = ! empty( $lead->submitted_at ) ? $lead->submitted_at : current_time( 'mysql' );

            $stored_link = ! empty( $lead->response_link ) ? $lead->response_link : $this->extract_link_from_payload( $payload );
            $stored_link = $this->normalise_link_value( $stored_link );

            $status_slug           = ! empty( $lead->status ) ? $lead->status : $this->get_default_status_slug( $form_slug );
            $status_label          = $this->get_status_label( $status_slug, $form_slug );
            $default_cc_addresses  = $this->get_default_cc_addresses( $form_slug );

            $context = array(
                'name'       => ! empty( $lead->response_client_name ) ? $lead->response_client_name : $this->extract_contact_name( $payload ),
                'email'      => ! empty( $lead->email ) ? sanitize_email( $lead->email ) : '',
                'phone'      => ! empty( $lead->response_phone ) ? $lead->response_phone : $this->extract_phone_from_payload( $payload ),
                'brand'      => ! empty( $lead->response_brand ) ? $lead->response_brand : $this->extract_brand_from_payload( $payload ),
                'link'       => $stored_link,
                'status'     => $status_label,
                'status_slug'=> $status_slug,
                'date'       => mysql2date( 'Y-m-d\TH:i:sP', $submitted_at ),
                'date_short' => mysql2date( 'd/m/Y', $submitted_at ),
                'form_title' => ! empty( $lead->form_title ) ? $lead->form_title : '',
                'site_name'  => get_bloginfo( 'name' ),
                'site_title' => get_bloginfo( 'name' ),
                'payload'    => $this->prepare_payload_for_js( $payload ),
                'recipient'  => '',
                'cc'         => '',
                'cc_base'    => '',
                'default_cc' => implode( ', ', $default_cc_addresses ),
            );

            $stored_recipients = array();
            if ( ! empty( $lead->response_recipients ) ) {
                $decoded = json_decode( $lead->response_recipients, true );
                if ( is_array( $decoded ) && ! empty( $decoded ) ) {
                    foreach ( $decoded as $email ) {
                        $sanitised = sanitize_email( $email );
                        if ( ! empty( $sanitised ) ) {
                            $stored_recipients[] = $sanitised;
                        }
                    }
                }
            }

            if ( ! empty( $stored_recipients ) ) {
                $context['recipient'] = $stored_recipients[0];
            }

            $stored_cc = array();
            if ( count( $stored_recipients ) > 1 ) {
                $stored_cc = array_slice( $stored_recipients, 1 );
            }

            if ( ! empty( $stored_cc ) && ! empty( $default_cc_addresses ) ) {
                $default_lookup = array();
                foreach ( $default_cc_addresses as $default_cc_email ) {
                    $default_lookup[ strtolower( $default_cc_email ) ] = true;
                }

                $stored_cc = array_values(
                    array_filter(
                        $stored_cc,
                        function ( $email ) use ( $default_lookup ) {
                            $key = strtolower( $email );
                            return ! isset( $default_lookup[ $key ] );
                        }
                    )
                );
            }

            $combined_cc          = $this->merge_email_lists( $stored_cc, $default_cc_addresses );

            if ( ! empty( $stored_cc ) ) {
                $context['cc_base'] = implode( ', ', $stored_cc );
            }

            if ( ! empty( $default_cc_addresses ) ) {
                $context['default_cc'] = implode( ', ', $default_cc_addresses );
            }

            if ( ! empty( $combined_cc ) ) {
                $context['cc'] = implode( ', ', $combined_cc );
            }

            $context['placeholders'] = $this->build_placeholder_tokens( $context, $payload, $form_field_keys );

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
         * Extract Contact Form 7 field keys for placeholder suggestions.
         *
         * @param WPCF7_ContactForm|null $contact_form Contact form instance.
         * @return array
         */
        protected function get_form_field_keys( $contact_form ) {
            if ( empty( $contact_form ) || ! is_object( $contact_form ) ) {
                return array();
            }

            $tags = array();

            if ( method_exists( $contact_form, 'scan_form_tags' ) ) {
                $tags = $contact_form->scan_form_tags();
            } elseif ( method_exists( $contact_form, 'form_tags_manager' ) ) {
                $manager = $contact_form->form_tags_manager();
                if ( $manager && method_exists( $manager, 'scan' ) ) {
                    $tags = $manager->scan();
                }
            }

            if ( empty( $tags ) ) {
                return array();
            }

            $keys = array();

            foreach ( $tags as $tag ) {
                $name = '';

                if ( is_object( $tag ) && isset( $tag->name ) ) {
                    $name = $tag->name;
                } elseif ( is_array( $tag ) && isset( $tag['name'] ) ) {
                    $name = $tag['name'];
                }

                $normalised = strtolower( (string) $name );
                $normalised = preg_replace( '/[^a-z0-9_\-]/', '', $normalised );

                if ( '' === $normalised ) {
                    continue;
                }

                $keys[] = $normalised;
            }

            if ( empty( $keys ) ) {
                return array();
            }

            return array_values( array_unique( $keys ) );
        }

        /**
         * Prepare placeholder tokens for Contact Form 7 field keys.
         *
         * @param array $form_field_keys Contact Form 7 field keys.
         * @return array
         */
        protected function prepare_form_placeholder_tokens( $form_field_keys ) {
            if ( empty( $form_field_keys ) || ! is_array( $form_field_keys ) ) {
                return array();
            }

            $tokens = array();

            foreach ( $form_field_keys as $field_key ) {
                $normalised_key = strtolower( (string) $field_key );
                $normalised_key = preg_replace( '/[^a-z0-9_\-]/', '', $normalised_key );

                if ( '' === $normalised_key ) {
                    continue;
                }

                $tokens = array_merge( $tokens, $this->expand_placeholder_variants( $normalised_key ) );
            }

            return array_values( array_unique( $tokens ) );
        }

        /**
         * Build a list of placeholder tokens available for templates.
         *
         * @param array $context         Template context data.
         * @param array $payload         Submission payload data.
         * @param array $form_field_keys Contact Form 7 field keys.
         * @return array
         */
        protected function build_placeholder_tokens( $context, $payload = array(), $form_field_keys = array() ) {
            $system_keys   = array( 'name', 'email', 'phone', 'brand', 'link', 'status', 'status_slug', 'date', 'date_short', 'form_title', 'site_name', 'site_title', 'recipient', 'cc', 'default_cc' );
            $system_tokens = array();

            foreach ( $system_keys as $key ) {
                $system_tokens = array_merge( $system_tokens, $this->expand_placeholder_variants( $key ) );
            }

            $form_tokens = array();

            if ( ! empty( $payload ) && is_array( $payload ) ) {
                foreach ( $payload as $field_key => $value ) {
                    $normalised_key = strtolower( (string) $field_key );
                    $normalised_key = preg_replace( '/[^a-z0-9_\-]/', '', $normalised_key );

                    if ( '' === $normalised_key ) {
                        continue;
                    }

                    $form_tokens = array_merge( $form_tokens, $this->expand_placeholder_variants( $normalised_key ) );
                }
            }

            if ( ! empty( $form_field_keys ) && is_array( $form_field_keys ) ) {
                foreach ( $form_field_keys as $field_key ) {
                    $normalised_key = strtolower( (string) $field_key );
                    $normalised_key = preg_replace( '/[^a-z0-9_\-]/', '', $normalised_key );

                    if ( '' === $normalised_key ) {
                        continue;
                    }

                    $form_tokens = array_merge( $form_tokens, $this->expand_placeholder_variants( $normalised_key ) );
                }
            }

            $system_tokens = array_values( array_unique( $system_tokens ) );
            $form_tokens   = array_values( array_unique( $form_tokens ) );

            return array(
                'system' => $system_tokens,
                'form'   => $form_tokens,
            );
        }

        /**
         * Generate placeholder variants for a given key.
         *
         * @param string $key Base placeholder key.
         * @return array
         */
        protected function expand_placeholder_variants( $key ) {
            $variants = array();

            if ( '' === $key ) {
                return $variants;
            }

            $variants[] = '%' . $key . '%';

            if ( false !== strpos( $key, '_' ) ) {
                $variants[] = '%' . str_replace( '_', '-', $key ) . '%';
            }

            if ( false !== strpos( $key, '-' ) ) {
                $variants[] = '%' . str_replace( '-', '_', $key ) . '%';
            }

            return $variants;
        }

        /**
         * Build data attributes for the template context container.
         *
         * @param object $lead              Lead database row.
         * @param array  $payload           Submission payload.
         * @param string $client_name_value Client name override.
         * @param string $client_phone      Client phone override.
         * @param string $client_brand      Client brand override.
         * @param string $client_link       Client link override.
         * @param array  $form_field_keys   Contact Form 7 field keys.
         * @return array
         */
        protected function build_template_context_attributes( $lead, $payload, $client_name_value, $client_phone, $client_brand = '', $client_link = '', $form_field_keys = array(), $form_slug = '' ) {
            $context = $this->build_template_context_data( $lead, $payload, $form_field_keys, $form_slug );

            if ( ! empty( $client_name_value ) ) {
                $context['name'] = $client_name_value;
            }

            if ( ! empty( $client_phone ) ) {
                $context['phone'] = $client_phone;
            }

            if ( ! empty( $client_brand ) ) {
                $context['brand'] = $client_brand;
            }

            if ( ! empty( $client_link ) ) {
                $context['link'] = $this->normalise_link_value( $client_link );
            }

            $attrs = array(
                'data-name'       => esc_attr( $context['name'] ),
                'data-email'      => esc_attr( $context['email'] ),
                'data-phone'      => esc_attr( $context['phone'] ),
                'data-brand'      => esc_attr( $context['brand'] ),
                'data-link'       => esc_attr( $context['link'] ),
                'data-status'     => esc_attr( $context['status'] ),
                'data-status-slug'=> esc_attr( $context['status_slug'] ),
                'data-date'       => esc_attr( $context['date'] ),
                'data-date-short' => esc_attr( $context['date_short'] ),
                'data-form-title' => esc_attr( $context['form_title'] ),
                'data-site-name'  => esc_attr( $context['site_name'] ),
                'data-site-title' => esc_attr( $context['site_title'] ),
                'data-recipient'  => esc_attr( $context['recipient'] ),
                'data-cc'         => esc_attr( $context['cc'] ),
                'data-cc-base'    => esc_attr( $context['cc_base'] ),
                'data-default-cc' => esc_attr( $context['default_cc'] ),
            );

            if ( ! empty( $context['payload'] ) ) {
                $attrs['data-payload'] = esc_attr( wp_json_encode( $context['payload'] ) );
            }

            if ( ! empty( $context['placeholders'] ) ) {
                $attrs['data-placeholders'] = esc_attr( wp_json_encode( $context['placeholders'] ) );
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
         * @param string $client_brand      Client brand override.
         * @param string $client_link       Client link override.
         * @param array  $recipient_emails  Recipient email list.
         * @return string
         */
        protected function apply_template_placeholders( $content, $lead, $payload, $client_name, $client_phone, $client_brand, $client_link, $recipient_emails, $form_slug = '' ) {
            if ( empty( $content ) ) {
                return $content;
            }

            $lead_email = ( $lead && ! empty( $lead->email ) ) ? $lead->email : '';
            $status_slug  = $lead && ! empty( $lead->status ) ? $lead->status : $this->get_default_status_slug( $form_slug );
            $status_label = $this->get_status_label( $status_slug, $form_slug );

            if ( empty( $lead_email ) && ! empty( $recipient_emails ) ) {
                $lead_email = reset( $recipient_emails );
            }

            if ( empty( $client_name ) ) {
                $client_name = $lead && ! empty( $lead->response_client_name ) ? $lead->response_client_name : $this->extract_contact_name( $payload );
            }

            if ( empty( $client_phone ) ) {
                $client_phone = $this->extract_phone_from_payload( $payload );
            }

            if ( empty( $client_brand ) ) {
                $client_brand = $lead && ! empty( $lead->response_brand ) ? $lead->response_brand : $this->extract_brand_from_payload( $payload );
            }

            if ( empty( $client_link ) ) {
                $client_link = $lead && ! empty( $lead->response_link ) ? $lead->response_link : $this->extract_link_from_payload( $payload );
            }

            $replacements = array(
                '%name%'        => $client_name,
                '%email%'       => $lead_email,
                '%phone%'       => $client_phone,
                '%brand%'       => $client_brand,
                '%link%'        => $client_link,
                '%status%'      => $status_label,
                '%status_slug%' => $status_slug,
                '%date%'        => $lead ? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $lead->submitted_at ) : '',
                '%date_short%'  => $lead ? mysql2date( 'd/m/Y', $lead->submitted_at ) : '',
                '%form_title%'  => $lead ? $lead->form_title : '',
                '%site_name%'   => get_bloginfo( 'name' ),
                '%site_title%'  => get_bloginfo( 'name' ),
                '%default_cc%'  => implode( ', ', $this->get_default_cc_addresses( $form_slug ) ),
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
         * Attempt to extract a link or website URL from the payload.
         *
         * @param array $payload Submission payload.
         * @return string
         */
        protected function extract_link_from_payload( $payload ) {
            if ( empty( $payload ) || ! is_array( $payload ) ) {
                return '';
            }

            $candidates = array(
                'link',
                'link_url',
                'website',
                'website_url',
                'website-link',
                'site',
                'site_url',
                'site-link',
                'url',
                'page_url',
                'portfolio',
                'portfolio_url',
                'profile',
                'profile_url',
            );

            $value = $this->find_payload_value( $payload, $candidates );

            if ( $value ) {
                $normalised = $this->normalise_link_value( $value );
                if ( $normalised ) {
                    return $normalised;
                }
            }

            foreach ( $payload as $key => $item ) {
                if ( preg_match( '/link|url|site|web|page|portfolio|profile/i', $key ) ) {
                    $normalised = $this->normalise_link_value( $item );
                    if ( $normalised ) {
                        return $normalised;
                    }
                }
            }

            return '';
        }

        /**
         * Attempt to extract a brand or company name from the payload.
         *
         * @param array $payload Submission payload.
         * @return string
         */
        protected function extract_brand_from_payload( $payload ) {
            if ( empty( $payload ) || ! is_array( $payload ) ) {
                return '';
            }

            $candidates = array(
                'brand',
                'brand-name',
                'brand_name',
                'company',
                'company-name',
                'company_name',
                'business',
                'business-name',
                'business_name',
                'organisation',
                'organization',
                'store',
                'shop',
                'agency',
            );

            $value = $this->find_payload_value( $payload, $candidates );

            if ( $value ) {
                return $value;
            }

            foreach ( $payload as $key => $item ) {
                if ( preg_match( '/brand|company|business|agency|store|shop/i', $key ) ) {
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
         * Normalise a user-supplied link value into a clean URL string.
         *
         * @param mixed $value Raw value.
         * @return string
         */
        protected function normalise_link_value( $value ) {
            if ( is_array( $value ) ) {
                $value = implode( ' ', array_filter( $value ) );
            }

            $value = wp_strip_all_tags( (string) $value );
            $value = trim( $value );
            $value = trim( $value, "\"'<>[]()" );

            if ( '' === $value ) {
                return '';
            }

            if ( 0 === strpos( $value, '//' ) ) {
                $value = 'https:' . $value;
            }

            if ( preg_match( '#^[a-z][a-z0-9+\-.]*://#i', $value ) ) {
                return esc_url_raw( $value );
            }

            if ( preg_match( '/^[A-Za-z0-9][A-Za-z0-9.-]*\.[A-Za-z]{2,}(\/.*)?$/', $value ) ) {
                $value = 'https://' . $value;
                return esc_url_raw( $value );
            }

            return esc_url_raw( $value );
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
         * Resolve the storage table name for a given form slug.
         *
         * @param string $slug Form slug.
         * @return string
         */
        protected function resolve_table_name_for_slug( $slug ) {
            if ( empty( $slug ) ) {
                return '';
            }

            $normalised_slug = $this->normalise_form_slug_candidate( $slug );

            $contact_form = $this->get_contact_form_by_slug( $slug );

            if ( ! $contact_form && $normalised_slug !== $slug ) {
                $contact_form = $this->get_contact_form_by_slug( $normalised_slug );
            }

            if ( $contact_form ) {
                return $this->get_table_name( $contact_form );
            }

            if ( $normalised_slug !== $slug ) {
                $normalised_table = $this->get_table_name_from_slug( $normalised_slug );

                if ( $this->table_exists( $normalised_table ) ) {
                    return $normalised_table;
                }
            }

            return $this->get_table_name_from_slug( $slug );
        }

        /**
         * Get the database table name for a specific form.
         *
         * @param WPCF7_ContactForm $contact_form Contact form instance.
         * @return string
         */
        protected function get_table_name( $contact_form ) {
            $slug  = $this->get_form_slug( $contact_form );
            $table = $this->get_table_name_from_slug( $slug );
            $legacy_slugs = array();

            if ( method_exists( $contact_form, 'name' ) ) {
                $legacy_slugs[] = sanitize_key( $contact_form->name() );
            }

            if ( method_exists( $contact_form, 'title' ) ) {
                $legacy_slugs[] = sanitize_key( $contact_form->title() );
            }

            if ( method_exists( $contact_form, 'id' ) ) {
                $legacy_slugs[] = 'form_' . absint( $contact_form->id() );
            }

            $legacy_slugs = array_unique( array_filter( $legacy_slugs ) );

            foreach ( $legacy_slugs as $legacy_slug ) {
                $variants = array_unique(
                    array(
                        $legacy_slug,
                        str_replace( '-', '_', $legacy_slug ),
                    )
                );

                foreach ( $variants as $variant_slug ) {
                    if ( $variant_slug === $slug ) {
                        continue;
                    }

                    $legacy_table = $this->get_table_name_from_slug( $variant_slug );
                    $this->maybe_migrate_legacy_table( $legacy_table, $table );
                }
            }

            return $table;
        }

        /**
         * Attempt to migrate data from a legacy table into the target table.
         *
         * @param string $legacy_table Legacy table name.
         * @param string $target_table Target table name.
         */
        protected function maybe_migrate_legacy_table( $legacy_table, $target_table ) {
            if ( empty( $legacy_table ) || empty( $target_table ) || $legacy_table === $target_table ) {
                return;
            }

            if ( ! $this->table_exists( $legacy_table ) ) {
                return;
            }

            global $wpdb;

            if ( ! $this->table_exists( $target_table ) ) {
                $wpdb->query( "ALTER TABLE {$legacy_table} RENAME TO {$target_table}" );
                return;
            }

            $legacy_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$legacy_table}" );

            if ( $legacy_count <= 0 ) {
                $wpdb->query( "DROP TABLE {$legacy_table}" );
                return;
            }

            $target_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$target_table}" );

            if ( 0 === $target_count ) {
                $wpdb->query( "DROP TABLE {$target_table}" );
                $wpdb->query( "ALTER TABLE {$legacy_table} RENAME TO {$target_table}" );
                return;
            }

            $this->copy_legacy_rows_to_table( $legacy_table, $target_table );
        }

        /**
         * Copy rows from a legacy table into the target table when both already exist.
         *
         * @param string $legacy_table Legacy table name.
         * @param string $target_table Target table name.
         */
        protected function copy_legacy_rows_to_table( $legacy_table, $target_table ) {
            global $wpdb;

            $legacy_columns = $this->get_table_columns( $legacy_table );
            $target_columns = $this->get_table_columns( $target_table );

            if ( empty( $legacy_columns ) || empty( $target_columns ) ) {
                return;
            }

            $common = array_values( array_intersect( $legacy_columns, $target_columns ) );
            $common = array_filter(
                $common,
                function ( $column ) {
                    return 'id' !== $column && preg_match( '/^[A-Za-z0-9_]+$/', $column );
                }
            );

            if ( empty( $common ) ) {
                return;
            }

            $column_list = implode( ', ', array_map( array( $this, 'quote_identifier' ), $common ) );

            if ( empty( $column_list ) ) {
                return;
            }

            $wpdb->query( "INSERT IGNORE INTO {$target_table} ({$column_list}) SELECT {$column_list} FROM {$legacy_table}" );
            $wpdb->query( "DROP TABLE {$legacy_table}" );
        }

        /**
         * Retrieve a list of column names for a table.
         *
         * @param string $table Table name.
         * @return array
         */
        protected function get_table_columns( $table ) {
            if ( empty( $table ) ) {
                return array();
            }

            global $wpdb;

            $columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );

            if ( empty( $columns ) ) {
                return array();
            }

            return array_map( 'strval', $columns );
        }

        /**
         * Quote a database identifier (such as a column name).
         *
         * @param string $identifier Identifier to quote.
         * @return string
         */
        protected function quote_identifier( $identifier ) {
            $sanitised = preg_replace( '/[^A-Za-z0-9_]/', '', (string) $identifier );

            if ( '' === $sanitised ) {
                return '';
            }

            return '`' . $sanitised . '`';
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
         * Retrieve the database table name for response templates.
         *
         * @return string
         */
        protected function get_templates_table_name() {
            global $wpdb;

            return $wpdb->prefix . 'leads_templates';
        }

        /**
         * Ensure the response templates table exists.
         */
        protected function maybe_create_templates_table() {
            global $wpdb;

            $table           = $this->get_templates_table_name();
            $legacy_table    = $wpdb->prefix . 'lead_templates';

            if ( $legacy_table !== $table && $this->table_exists( $legacy_table ) ) {
                $this->maybe_migrate_legacy_table( $legacy_table, $table );
            }

            $charset_collate = $wpdb->get_charset_collate();
            $sql             = "CREATE TABLE {$table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                form_slug varchar(191) NOT NULL DEFAULT '',
                slug varchar(191) NOT NULL,
                label varchar(191) NOT NULL DEFAULT '',
                subject varchar(255) NOT NULL DEFAULT '',
                body longtext,
                description text,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY template_slug (form_slug, slug),
                KEY form_slug (form_slug)
            ) {$charset_collate};";

            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta( $sql );
        }

        /**
         * Retrieve the database table name for module options.
         *
         * @return string
         */
        protected function get_options_table_name() {
            global $wpdb;

            return $wpdb->prefix . 'leads_options';
        }

        /**
         * Ensure the module options table exists and legacy options are migrated.
         */
        protected function maybe_create_options_table() {
            global $wpdb;

            $table           = $this->get_options_table_name();
            $charset_collate = $wpdb->get_charset_collate();
            $sql             = "CREATE TABLE {$table} (
                option_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                form_slug varchar(191) NOT NULL DEFAULT '',
                option_name varchar(191) NOT NULL,
                option_value longtext NOT NULL,
                autoload varchar(20) NOT NULL DEFAULT 'no',
                PRIMARY KEY  (option_id),
                UNIQUE KEY option_name (option_name, form_slug),
                KEY form_slug (form_slug)
            ) {$charset_collate};";

            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta( $sql );

            $this->maybe_migrate_options_from_wp_options();
        }

        /**
         * Determine whether a module option already exists.
         *
         * @param string $name Option name.
         * @return bool
         */
        protected function normalise_option_form_slug( $form_slug ) {
            $slug = sanitize_key( $form_slug );

            if ( empty( $slug ) ) {
                return '';
            }

            return $slug;
        }

        /**
         * Determine whether a module option already exists.
         *
         * @param string $name      Option name.
         * @param string $form_slug Form slug.
         * @return bool
         */
        protected function module_option_exists( $name, $form_slug = '' ) {
            global $wpdb;

            $table = $this->get_options_table_name();

            if ( ! $this->table_exists( $table ) ) {
                return false;
            }

            $form_slug = $this->normalise_option_form_slug( $form_slug );

            $existing = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT option_name FROM {$table} WHERE option_name = %s AND form_slug = %s LIMIT 1",
                    $name,
                    $form_slug
                )
            );

            return null !== $existing;
        }

        /**
         * Migrate legacy options stored in wp_options into the module options table.
         */
        protected function maybe_migrate_options_from_wp_options() {
            global $wpdb;

            $table = $this->get_options_table_name();

            if ( ! $this->table_exists( $table ) ) {
                return;
            }

            $mappings = array(
                'theme_leads_statuses'        => 'statuses',
                'theme_leads_default_cc'      => 'default_cc',
                'theme_leads_mailer_settings' => 'mailer_settings',
            );

            foreach ( $mappings as $legacy_key => $new_key ) {
                if ( $this->module_option_exists( $new_key ) ) {
                    continue;
                }

                $value = get_option( $legacy_key, '__theme_leads_missing__' );

                if ( '__theme_leads_missing__' === $value ) {
                    continue;
                }

                $wpdb->replace(
                    $table,
                    array(
                        'form_slug'    => '',
                        'option_name'  => $new_key,
                        'option_value' => maybe_serialize( $value ),
                        'autoload'     => 'no',
                    ),
                    array( '%s', '%s', '%s', '%s' )
                );

                delete_option( $legacy_key );
            }
        }

        /**
         * Retrieve a module option from the dedicated table.
         *
         * @param string $name    Option name.
         * @param mixed  $default Default value when the option is missing.
         * @param string $form_slug Form slug.
         * @param bool   $found   Whether the option exists.
         * @return mixed
         */
        protected function get_module_option( $name, $default = null, $form_slug = '', &$found = null ) {
            global $wpdb;

            $found = false;
            $table = $this->get_options_table_name();

            if ( ! $this->table_exists( $table ) ) {
                $this->maybe_create_options_table();

                if ( ! $this->table_exists( $table ) ) {
                    return $default;
                }
            }

            $form_slug = $this->normalise_option_form_slug( $form_slug );

            $value = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT option_value FROM {$table} WHERE option_name = %s AND form_slug = %s LIMIT 1",
                    $name,
                    $form_slug
                )
            );

            if ( null === $value ) {
                return $default;
            }

            $found = true;

            return maybe_unserialize( $value );
        }

        /**
         * Persist a module option to the dedicated table.
         *
         * @param string $name  Option name.
         * @param mixed  $value Option value.
         * @param string $form_slug Form slug.
         */
        protected function update_module_option( $name, $value, $form_slug = '' ) {
            global $wpdb;

            $table = $this->get_options_table_name();

            if ( ! $this->table_exists( $table ) ) {
                $this->maybe_create_options_table();
            }

            $form_slug = $this->normalise_option_form_slug( $form_slug );

            $wpdb->replace(
                $table,
                array(
                    'form_slug'    => $form_slug,
                    'option_name'  => $name,
                    'option_value' => maybe_serialize( $value ),
                    'autoload'     => 'no',
                ),
                array( '%s', '%s', '%s', '%s' )
            );
        }

        /**
         * Delete a module option from the dedicated table.
         *
         * @param string $name      Option name.
         * @param string $form_slug Form slug.
         */
        protected function delete_module_option( $name, $form_slug = '' ) {
            global $wpdb;

            $table = $this->get_options_table_name();

            if ( ! $this->table_exists( $table ) ) {
                return;
            }

            $form_slug = $this->normalise_option_form_slug( $form_slug );

            $wpdb->delete(
                $table,
                array(
                    'option_name' => $name,
                    'form_slug'   => $form_slug,
                ),
                array( '%s', '%s' )
            );
        }

        /**
         * Format a Contact Form 7 field key into a human readable label.
         *
         * @param string $field_key Field key.
         * @return string
         */
        protected function format_table_column_field_label( $field_key ) {
            $label = strtolower( (string) $field_key );
            $label = str_replace( array( '_', '-' ), ' ', $label );
            $label = preg_replace( '/\s+/', ' ', trim( $label ) );

            if ( '' === $label ) {
                return (string) $field_key;
            }

            return ucwords( $label );
        }

        /**
         * Normalise a table column key for storage and comparison.
         *
         * @param string $key Raw column key.
         * @return string
         */
        protected function normalise_table_column_key( $key ) {
            $key = strtolower( (string) $key );
            $key = trim( $key );

            if ( '' === $key ) {
                return '';
            }

            if ( 0 === strpos( $key, '__' ) ) {
                $core = substr( $key, 2 );
                $core = preg_replace( '/[^a-z0-9_\-]/', '', $core );

                return '' === $core ? '' : '__' . $core;
            }

            $key = preg_replace( '/[^a-z0-9_\-]/', '', $key );

            return $key;
        }

        /**
         * Retrieve the available table column candidates for a form.
         *
         * @param array $form_field_keys Contact Form 7 field keys.
         * @return array
         */
        protected function get_table_column_candidates( $form_field_keys = array() ) {
            $candidates = array();
            $seen       = array();

            $special_columns = array(
                array(
                    'key'   => '__name',
                    'label' => __( 'Name', 'wordprseo' ),
                    'group' => 'lead',
                ),
                array(
                    'key'   => '__email',
                    'label' => __( 'Email', 'wordprseo' ),
                    'group' => 'lead',
                ),
                array(
                    'key'   => '__phone',
                    'label' => __( 'Phone', 'wordprseo' ),
                    'group' => 'lead',
                ),
                array(
                    'key'   => '__brand',
                    'label' => __( 'Brand', 'wordprseo' ),
                    'group' => 'lead',
                ),
                array(
                    'key'   => '__link',
                    'label' => __( 'Website', 'wordprseo' ),
                    'group' => 'lead',
                ),
            );

            foreach ( $special_columns as $column ) {
                $normalised_key = $this->normalise_table_column_key( $column['key'] );

                if ( '' === $normalised_key || isset( $seen[ $normalised_key ] ) ) {
                    continue;
                }

                $seen[ $normalised_key ] = true;
                $candidates[]            = array(
                    'key'   => $normalised_key,
                    'label' => $column['label'],
                    'group' => $column['group'],
                );
            }

            if ( ! empty( $form_field_keys ) && is_array( $form_field_keys ) ) {
                foreach ( $form_field_keys as $field_key ) {
                    $normalised_key = $this->normalise_table_column_key( $field_key );

                    if ( '' === $normalised_key || isset( $seen[ $normalised_key ] ) ) {
                        continue;
                    }

                    $seen[ $normalised_key ] = true;
                    $candidates[]            = array(
                        'key'   => $normalised_key,
                        'label' => $this->format_table_column_field_label( $field_key ),
                        'group' => 'form',
                    );
                }
            }

            return $candidates;
        }

        /**
         * Retrieve the default table column keys.
         *
         * @return array
         */
        protected function get_default_table_column_keys() {
            return array( '__name', '__email', '__phone' );
        }

        /**
         * Retrieve the valid table column keys for a form.
         *
         * @param array $form_field_keys Contact Form 7 field keys.
         * @return array
         */
        protected function get_table_column_candidate_keys( $form_field_keys = array() ) {
            $candidates = $this->get_table_column_candidates( $form_field_keys );

            if ( empty( $candidates ) ) {
                return array();
            }

            $keys = array();

            foreach ( $candidates as $candidate ) {
                if ( empty( $candidate['key'] ) ) {
                    continue;
                }

                $keys[] = $candidate['key'];
            }

            return array_values( array_unique( $keys ) );
        }

        /**
         * Persist the selected table columns for a form.
         *
         * @param array  $columns   Selected columns.
         * @param string $form_slug Form slug.
         */
        protected function save_table_columns_selection( $columns, $form_slug = '' ) {
            if ( ! is_array( $columns ) ) {
                $columns = array( $columns );
            }

            $normalised = array();

            foreach ( $columns as $column_key ) {
                $normalised_key = $this->normalise_table_column_key( $column_key );

                if ( '' !== $normalised_key ) {
                    $normalised[] = $normalised_key;
                }
            }

            $normalised = array_values( array_unique( $normalised ) );

            $this->update_module_option( 'table_columns', $normalised, $form_slug );
        }

        /**
         * Retrieve the table columns selected for a form.
         *
         * @param string $form_slug       Form slug.
         * @param array  $form_field_keys Field keys for the form.
         * @return array
         */
        protected function get_selected_table_columns( $form_slug, $form_field_keys = array() ) {
            $stored = $this->get_option_for_form( 'table_columns', null, $form_slug );

            if ( ! is_array( $stored ) ) {
                $stored = array();
            }

            $stored = array_map( array( $this, 'normalise_table_column_key' ), $stored );
            $stored = array_filter( $stored );

            $allowed_keys = $this->get_table_column_candidate_keys( $form_field_keys );

            $selected = array_values( array_intersect( $stored, $allowed_keys ) );

            if ( empty( $selected ) ) {
                $fallback = array_values( array_intersect( $this->get_default_table_column_keys(), $allowed_keys ) );

                if ( empty( $fallback ) ) {
                    $fallback = $allowed_keys;
                }

                $selected = $fallback;
            }

            return $selected;
        }

        /**
         * Retrieve the display label for a table column header.
         *
         * @param string $column_key Column key.
         * @return string
         */
        protected function get_table_column_header_label( $column_key ) {
            $column_key = $this->normalise_table_column_key( $column_key );

            switch ( $column_key ) {
                case '__name':
                    return __( 'Name', 'wordprseo' );
                case '__email':
                    return __( 'Email', 'wordprseo' );
                case '__phone':
                    return __( 'Phone', 'wordprseo' );
                case '__brand':
                    return __( 'Brand', 'wordprseo' );
                case '__link':
                    return __( 'Website', 'wordprseo' );
            }

            return $this->format_table_column_field_label( $column_key );
        }

        /**
         * Locate a raw payload value for a table column.
         *
         * @param array  $payload    Submission payload.
         * @param string $column_key Column key.
         * @return mixed|null
         */
        protected function find_raw_payload_value_for_column( $payload, $column_key ) {
            if ( empty( $payload ) || ! is_array( $payload ) ) {
                return null;
            }

            if ( array_key_exists( $column_key, $payload ) ) {
                return $payload[ $column_key ];
            }

            foreach ( $payload as $key => $value ) {
                if ( ! is_string( $key ) ) {
                    continue;
                }

                if ( $this->normalise_table_column_key( $key ) === $column_key ) {
                    return $value;
                }
            }

            return null;
        }

        /**
         * Render a summary table cell for a lead and column key.
         *
         * @param string $column_key Column key.
         * @param object $lead       Lead record.
         * @param array  $payload    Submission payload.
         * @param array  $context    Contextual values.
         * @param string $form_slug  Form slug.
         * @return string
         */
        protected function render_summary_table_column_cell( $column_key, $lead, $payload, $context = array(), $form_slug = '' ) {
            $column_key = $this->normalise_table_column_key( $column_key );

            $classes = array( 'theme-leads-column' );
            $base_class = str_replace( '__', '', $column_key );
            $base_class = str_replace( array( '_', '-' ), '-', $base_class );

            if ( '' !== $base_class ) {
                $classes[] = 'theme-leads-column--' . sanitize_html_class( $base_class );
            }

            $attributes = array();
            $content    = '&mdash;';

            switch ( $column_key ) {
                case '__name':
                    $classes[] = 'theme-leads-summary-name';
                    $name_value = '';

                    if ( isset( $context['client_name'] ) && '' !== $context['client_name'] ) {
                        $name_value = $context['client_name'];
                    } elseif ( isset( $context['lead_name'] ) && '' !== $context['lead_name'] ) {
                        $name_value = $context['lead_name'];
                    }

                    if ( '' === $name_value ) {
                        $name_value = __( 'Unknown', 'wordprseo' );
                    }

                    $content = '<span class="theme-leads-name-text">' . esc_html( $name_value ) . '</span>';
                    break;

                case '__email':
                    $email_value = '';

                    if ( ! empty( $lead->email ) ) {
                        $email_value = sanitize_email( $lead->email );
                    }

                    if ( empty( $email_value ) ) {
                        $email_value = sanitize_email( $this->find_payload_value( $payload, array( 'email', 'your-email', 'contact-email' ) ) );
                    }

                    $parts = array();

                    if ( ! empty( $email_value ) ) {
                        $parts[] = '<a href="mailto:' . esc_attr( $email_value ) . '">' . esc_html( $email_value ) . '</a>';
                    } else {
                        $parts[] = '&mdash;';
                    }

                    if ( ! empty( $lead->response_sent ) ) {
                        $parts[] = '<div class="theme-leads-meta theme-leads-last-response"><small>' . esc_html__( 'Last response', 'wordprseo' ) . ': ' . esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $lead->response_sent ) ) . '</small></div>';
                    }

                    $content = implode( '', $parts );
                    break;

                case '__phone':
                    $classes[]               = 'theme-leads-summary-phone';
                    $attributes['data-role'] = 'lead-phone';
                    $phone_value             = '';

                    if ( isset( $context['client_phone'] ) && '' !== $context['client_phone'] ) {
                        $phone_value = $context['client_phone'];
                    } elseif ( isset( $context['lead_phone'] ) && '' !== $context['lead_phone'] ) {
                        $phone_value = $context['lead_phone'];
                    }

                    if ( '' !== $phone_value ) {
                        $tel_href = preg_replace( '/[^0-9\+]/', '', $phone_value );
                        $tel_href = $tel_href ? $tel_href : $phone_value;
                        $content  = '<a class="theme-leads-phone-link" href="tel:' . esc_attr( $tel_href ) . '" data-number="' . esc_attr( $tel_href ) . '">' . esc_html( $phone_value ) . '</a>';
                    }
                    break;

                case '__brand':
                    $brand_value = isset( $context['client_brand'] ) ? $context['client_brand'] : '';
                    $content     = '' !== $brand_value ? esc_html( $brand_value ) : '&mdash;';
                    break;

                case '__link':
                    $link_value = isset( $context['client_link'] ) ? $context['client_link'] : '';

                    if ( ! empty( $link_value ) ) {
                        $content = '<a href="' . esc_url( $link_value ) . '" target="_blank" rel="noreferrer noopener">' . esc_html( $link_value ) . '</a>';
                    } else {
                        $content = '&mdash;';
                    }
                    break;

                default:
                    $raw_value = $this->find_raw_payload_value_for_column( $payload, $column_key );

                    if ( null !== $raw_value ) {
                        $content = $this->get_payload_value_display_markup( $raw_value, $lead, $form_slug, $column_key );
                    } else {
                        $fallback = $this->find_payload_value( $payload, array( $column_key ) );
                        $content  = $fallback ? esc_html( $fallback ) : '&mdash;';
                    }
                    break;
            }

            $attributes_string = '';

            if ( ! empty( $classes ) ) {
                $attributes_string .= ' class="' . esc_attr( implode( ' ', array_filter( $classes ) ) ) . '"';
            }

            if ( ! empty( $attributes ) ) {
                foreach ( $attributes as $attr_key => $attr_value ) {
                    if ( '' === $attr_value ) {
                        continue;
                    }

                    $attributes_string .= ' ' . $attr_key . '="' . esc_attr( $attr_value ) . '"';
                }
            }

            return '<td' . $attributes_string . '>' . $content . '</td>';
        }

        /**
         * Retrieve a module option with fallback to the global scope.
         *
         * @param string $name      Option name.
         * @param mixed  $default   Default value.
         * @param string $form_slug Form slug.
         * @return mixed
         */
        protected function get_option_for_form( $name, $default = null, $form_slug = '' ) {
            $form_slug = $this->normalise_option_form_slug( $form_slug );

            $value = $this->get_module_option( $name, null, $form_slug, $found );

            if ( $found ) {
                return null === $value ? $default : $value;
            }

            if ( '' !== $form_slug ) {
                $fallback = $this->get_module_option( $name, null, '', $fallback_found );

                if ( $fallback_found ) {
                    return null === $fallback ? $default : $fallback;
                }
            }

            return $default;
        }

        /**
         * Generate a stable slug for a form instance.
         *
         * @param WPCF7_ContactForm $contact_form Contact form instance.
         * @return string
         */
        protected function get_form_slug( $contact_form ) {
            $candidates = array();

            if ( method_exists( $contact_form, 'title' ) ) {
                $candidates[] = $contact_form->title();
            }

            if ( method_exists( $contact_form, 'name' ) ) {
                $candidates[] = $contact_form->name();
            }

            foreach ( $candidates as $candidate ) {
                $slug = $this->normalise_form_slug_candidate( $candidate );

                if ( ! empty( $slug ) && 'untitled' !== $slug ) {
                    return $slug;
                }
            }

            if ( method_exists( $contact_form, 'id' ) ) {
                return 'form_' . absint( $contact_form->id() );
            }

            return 'form_legacy';
        }

        /**
         * Normalise a slug candidate into a safe table identifier fragment.
         *
         * @param string $candidate Raw candidate value.
         * @return string
         */
        protected function normalise_form_slug_candidate( $candidate ) {
            $slug = sanitize_key( $candidate );

            if ( empty( $slug ) ) {
                return '';
            }

            $slug = str_replace( '-', '_', $slug );

            return $slug;
        }

        /**
         * Create the table if it does not already exist.
         *
         * @param string $table Table name.
         */
        protected function maybe_create_table( $table, $form_slug = '' ) {
            global $wpdb;

            $form_slug = $this->normalise_option_form_slug( $form_slug );

            $charset_collate = $wpdb->get_charset_collate();
            $default_status  = esc_sql( $this->get_default_status_slug( $form_slug ) );

            $sql = "CREATE TABLE {$table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                submitted_at datetime NOT NULL,
                status varchar(50) NOT NULL DEFAULT '{$default_status}',
                email varchar(190) DEFAULT '',
                payload longtext,
                form_title varchar(255) DEFAULT '',
                note longtext,
                response longtext,
                response_subject varchar(255) DEFAULT '',
                response_sent datetime DEFAULT NULL,
                response_client_name varchar(190) DEFAULT '',
                response_link varchar(255) DEFAULT '',
                response_phone varchar(100) DEFAULT '',
                response_brand varchar(190) DEFAULT '',
                response_recipients longtext,
                response_template varchar(190) DEFAULT '',
                PRIMARY KEY  (id),
                KEY status (status),
                KEY email (email)
            ) {$charset_collate};";

            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            if ( ! $this->table_exists( $table ) ) {
                dbDelta( $sql );
                $this->drop_empty_copy_table( $table . '_copy' );
                return;
            }

            $required_columns = array(
                'note',
                'response',
                'response_subject',
                'response_sent',
                'response_client_name',
                'response_link',
                'response_phone',
                'response_brand',
                'response_recipients',
                'response_template',
            );
            $needs_update      = false;

            foreach ( $required_columns as $column_name ) {
                $column_exists = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", $column_name ) );
                if ( empty( $column_exists ) ) {
                    $needs_update = true;
                    break;
                }
            }

            if ( $needs_update ) {
                dbDelta( $sql );
            }

            $this->drop_empty_copy_table( $table . '_copy' );
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

        /**
         * Drop an empty manually duplicated copy table when detected.
         *
         * @param string $table Table name to inspect.
         */
        protected function drop_empty_copy_table( $table ) {
            if ( empty( $table ) || '_copy' !== substr( $table, -5 ) ) {
                return;
            }

            if ( ! $this->table_exists( $table ) ) {
                return;
            }

            $quoted_table = $this->quote_identifier( $table );

            if ( '' === $quoted_table ) {
                return;
            }

            global $wpdb;

            $row_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$quoted_table}" );

            if ( 0 === $row_count ) {
                $wpdb->query( "DROP TABLE {$quoted_table}" );
            }
        }
    }

    new Theme_Leads_Manager();
}
