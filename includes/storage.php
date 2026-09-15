<?php
/**
 * Where a signed agreement lives: a private custom post type, one post per
 * submission, answers in post meta.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function pupbf_register_cpt() {
	register_post_type(
		PUPBF_CPT,
		array(
			'labels'              => array(
				'name'               => 'Booking Forms',
				'singular_name'      => 'Booking Form',
				'menu_name'          => 'Booking Forms',
				'all_items'          => 'All Bookings',
				'edit_item'          => 'Booking form',
				'view_item'          => 'Booking form',
				'search_items'       => 'Search bookings',
				'not_found'          => 'No booking forms yet.',
				'not_found_in_trash' => 'No booking forms in the bin.',
			),
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_rest'        => false,
			'menu_icon'           => 'dashicons-clipboard',
			'menu_position'       => 26,
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
			'supports'            => array( 'title' ),
			'has_archive'         => false,
			'rewrite'             => false,
			'can_export'          => true,
		)
	);
}
add_action( 'init', 'pupbf_register_cpt' );

/** Meta keys are prefixed and hidden from the generic custom-fields box. */
function pupbf_meta_key( $field_key ) {
	return '_pupbf_' . $field_key;
}

/**
 * Read one answer back, decrypting if needed.
 *
 * @return mixed String, array (for `days`), or false if a sensitive value
 *               could not be decrypted.
 */
function pupbf_get_answer( $post_id, $field_key ) {
	$fields = pupbf_fields();
	$def    = isset( $fields[ $field_key ] ) ? $fields[ $field_key ] : array();
	$raw    = get_post_meta( $post_id, pupbf_meta_key( $field_key ), true );

	if ( ! empty( $def['sensitive'] ) && is_string( $raw ) && '' !== $raw ) {
		return pupbf_decrypt( $raw );
	}
	return $raw;
}

/**
 * Human-readable version of an answer, for the admin screen, the printed
 * record, the CSV and the emails.
 *
 * @param bool $redact_sensitive Blank out anything marked sensitive.
 */
function pupbf_format_answer( $post_id, $field_key, $redact_sensitive = false ) {
	$fields = pupbf_fields();
	$def    = isset( $fields[ $field_key ] ) ? $fields[ $field_key ] : array();
	$type   = isset( $def['type'] ) ? $def['type'] : 'text';

	if ( ! empty( $def['sensitive'] ) && $redact_sensitive ) {
		$raw = get_post_meta( $post_id, pupbf_meta_key( $field_key ), true );
		return '' === $raw ? '—' : '[stored securely on the website — not sent by email]';
	}

	$value = pupbf_get_answer( $post_id, $field_key );

	if ( false === $value ) {
		return '[could not be decrypted — the site\'s security keys have changed]';
	}

	switch ( $type ) {
		case 'days':
			if ( ! is_array( $value ) || ! $value ) {
				return '—';
			}
			$parts = array();
			foreach ( pupbf_weekdays() as $slug => $label ) {
				if ( ! empty( $value[ $slug ]['on'] ) ) {
					$time    = isset( $value[ $slug ]['time'] ) ? trim( (string) $value[ $slug ]['time'] ) : '';
					$parts[] = $label . ( '' !== $time ? ' at ' . $time : '' );
				}
			}
			return $parts ? implode( ', ', $parts ) : '—';

		case 'radio':
			if ( '' === $value || null === $value ) {
				return '—';
			}
			return isset( $def['options'][ $value ] ) ? wp_strip_all_tags( $def['options'][ $value ] ) : (string) $value;

		case 'checkbox':
			return ( '1' === (string) $value ) ? 'Yes' : 'No';

		case 'signature':
			return $value ? 'Signed' : 'Not signed';

		default:
			$value = (string) $value;
			return '' === trim( $value ) ? '—' : $value;
	}
}

/**
 * Only users who can manage the site should read submissions. WordPress's
 * default `edit_posts` would let a Contributor read home access details.
 */
function pupbf_restrict_admin_access() {
	if ( ! is_admin() ) {
		return;
	}
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || PUPBF_CPT !== $screen->post_type ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to view booking forms.', 'pupventures-booking' ), 403 );
	}
}
add_action( 'current_screen', 'pupbf_restrict_admin_access' );
