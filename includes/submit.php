<?php
/**
 * Validate, store, notify.
 *
 * The form is saved to the database first and emailed second, so a flaky mail
 * server can never lose a client's signed agreement.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function pupbf_client_ip() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';
	return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
}

/** A handful of submissions per hour from one address is plenty. */
function pupbf_rate_limited() {
	$ip = pupbf_client_ip();
	if ( ! $ip ) {
		return false;
	}
	$key   = 'pupbf_rl_' . md5( $ip );
	$count = (int) get_transient( $key );
	if ( $count >= 5 ) {
		return true;
	}
	set_transient( $key, $count + 1, HOUR_IN_SECONDS );
	return false;
}

function pupbf_fail( $redirect, $errors, $values, $notice ) {
	// Sensitive answers are deliberately not carried back — they would have to
	// sit in the options table while the visitor fixed their mistake.
	foreach ( pupbf_fields() as $key => $def ) {
		if ( ! empty( $def['sensitive'] ) ) {
			unset( $values[ $key ] );
		}
	}
	unset( $values['signature'] );

	pupbf_flash_set(
		array(
			'errors' => $errors,
			'values' => $values,
			'notice' => $notice,
		)
	);
	wp_safe_redirect( $redirect . '#pupbf-top' );
	exit;
}

function pupbf_handle_submit() {
	$redirect = isset( $_POST['pupbf_redirect'] ) ? esc_url_raw( wp_unslash( $_POST['pupbf_redirect'] ) ) : home_url( '/' );

	if ( ! isset( $_POST['pupbf_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pupbf_nonce'] ) ), 'pupbf_submit' ) ) {
		pupbf_fail( $redirect, array(), array(), 'Sorry, that took a little too long and the form timed out. Please check your answers and send it again.' );
	}

	// Honeypot: silently thank the bot and move on.
	if ( ! empty( $_POST['pupbf_hp'] ) ) {
		wp_safe_redirect( add_query_arg( 'pupbf', 'thanks', $redirect ) );
		exit;
	}

	if ( pupbf_rate_limited() ) {
		pupbf_fail( $redirect, array(), array(), 'That\'s a few forms in a row from this device. Please give it an hour, or ring me on 07366 308303.' );
	}

	$raw    = isset( $_POST['pupbf'] ) && is_array( $_POST['pupbf'] ) ? wp_unslash( $_POST['pupbf'] ) : array();
	$fields = pupbf_fields();
	$values = array();
	$errors = array();

	foreach ( $fields as $key => $def ) {
		$type = isset( $def['type'] ) ? $def['type'] : 'text';
		$in   = isset( $raw[ $key ] ) ? $raw[ $key ] : '';

		switch ( $type ) {
			case 'days':
				$days = array();
				foreach ( pupbf_weekdays() as $slug => $label ) {
					$on   = ! empty( $in[ $slug ]['on'] );
					$time = isset( $in[ $slug ]['time'] ) ? sanitize_text_field( (string) $in[ $slug ]['time'] ) : '';
					if ( $on ) {
						$days[ $slug ] = array( 'on' => 1, 'time' => $time );
					}
				}
				$values[ $key ] = $days;
				break;

			case 'checkbox':
				$values[ $key ] = ! empty( $in ) ? '1' : '';
				break;

			case 'radio':
				$in             = sanitize_text_field( (string) $in );
				$values[ $key ] = isset( $def['options'][ $in ] ) ? $in : '';
				break;

			case 'email':
				$values[ $key ] = sanitize_email( (string) $in );
				break;

			case 'number':
				$n = (int) $in;
				if ( isset( $def['min'] ) ) {
					$n = max( (int) $def['min'], $n );
				}
				if ( isset( $def['max'] ) ) {
					$n = min( (int) $def['max'], $n );
				}
				$values[ $key ] = (string) $n;
				break;

			case 'textarea':
				$values[ $key ] = sanitize_textarea_field( (string) $in );
				break;

			case 'signature':
				$values[ $key ] = pupbf_sanitize_signature( (string) $in );
				break;

			default:
				$values[ $key ] = sanitize_text_field( (string) $in );
				break;
		}
	}

	/* --- required-field checks ------------------------------------------ */
	foreach ( $fields as $key => $def ) {
		if ( empty( $def['required'] ) ) {
			continue;
		}
		// A hidden conditional field can't be required.
		if ( ! empty( $def['showif'] ) ) {
			list( $dep_key, $dep_val ) = $def['showif'];
			if ( ! isset( $values[ $dep_key ] ) || (string) $values[ $dep_key ] !== (string) $dep_val ) {
				continue;
			}
		}
		$v = $values[ $key ];
		if ( is_array( $v ) ? empty( $v ) : ( '' === trim( (string) $v ) ) ) {
			$errors[ $key ] = 'This one\'s needed before I can take the booking.';
		}
	}

	if ( '' !== $values['email'] && ! is_email( $values['email'] ) ) {
		$errors['email'] = 'That email address doesn\'t look quite right.';
	}
	if ( '' !== $values['phone'] && ! preg_match( '/[0-9]{5,}/', preg_replace( '/[^0-9]/', '', $values['phone'] ) ) ) {
		$errors['phone'] = 'Please give a phone number I can actually reach you on.';
	}
	if ( 'regular' === $values['schedule_type'] && empty( $values['days'] ) ) {
		$errors['days'] = 'Please tick at least one day, or choose "Ad hoc / as needed".';
	}
	if ( 'yes' === $values['recording'] && '' === trim( $values['recording_details'] ) ) {
		$errors['recording_details'] = 'Just a quick note on where they are, please.';
	}
	if ( '' === $values['signature'] ) {
		$errors['signature'] = 'Please add your signature — a finger on the box is all it takes.';
	}

	if ( $errors ) {
		pupbf_fail( $redirect, $errors, $values, 'Almost there — a couple of answers need a look. They\'re marked in red below.' );
	}

	$post_id = pupbf_store( $values );

	if ( is_wp_error( $post_id ) || ! $post_id ) {
		pupbf_fail( $redirect, array(), $values, 'Something went wrong saving your form. Please try once more, or ring me on 07366 308303.' );
	}

	pupbf_notify( $post_id, $values );

	wp_safe_redirect( add_query_arg( 'pupbf', 'thanks', $redirect ) . '#pupbf-top' );
	exit;
}
add_action( 'admin_post_nopriv_pupbf_submit', 'pupbf_handle_submit' );
add_action( 'admin_post_pupbf_submit', 'pupbf_handle_submit' );

/**
 * Accept only a PNG data URL of a sensible size.
 *
 * @return string The data URL, or '' if it isn't one.
 */
function pupbf_sanitize_signature( $data_url ) {
	$prefix = 'data:image/png;base64,';
	if ( 0 !== strpos( $data_url, $prefix ) ) {
		return '';
	}
	$b64 = substr( $data_url, strlen( $prefix ) );
	if ( strlen( $b64 ) > 400000 ) { // ~300KB of PNG, far more than a signature needs.
		return '';
	}
	$bin = base64_decode( $b64, true );
	if ( false === $bin || strlen( $bin ) < 100 ) {
		return '';
	}
	// Must actually be a PNG.
	if ( "\x89PNG\r\n\x1a\n" !== substr( $bin, 0, 8 ) ) {
		return '';
	}
	return $prefix . base64_encode( $bin );
}

function pupbf_store( $values ) {
	$title = trim( $values['owner_name'] );
	if ( '' !== trim( $values['dog_names'] ) ) {
		$title .= ' — ' . trim( $values['dog_names'] );
	}

	$post_id = wp_insert_post(
		array(
			'post_type'   => PUPBF_CPT,
			'post_status' => 'publish',
			'post_title'  => $title ? $title : 'Booking form',
		),
		true
	);

	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}

	$fields = pupbf_fields();
	foreach ( $values as $key => $value ) {
		if ( ! empty( $fields[ $key ]['sensitive'] ) && is_string( $value ) && '' !== $value ) {
			$value = pupbf_encrypt( $value );
		}
		update_post_meta( $post_id, pupbf_meta_key( $key ), $value );
	}

	// The audit trail that makes an electronic signature worth having.
	update_post_meta( $post_id, '_pupbf_signed_at', current_time( 'mysql' ) );
	update_post_meta( $post_id, '_pupbf_signed_at_gmt', current_time( 'mysql', true ) );
	update_post_meta( $post_id, '_pupbf_agreement_version', PUPBF_AGREEMENT_VERSION );
	update_post_meta( $post_id, '_pupbf_ip', pupbf_client_ip() );
	update_post_meta( $post_id, '_pupbf_user_agent', isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '' );
	update_post_meta( $post_id, '_pupbf_status', 'new' );

	return $post_id;
}

function pupbf_notify_email() {
	$to = get_option( 'pupbf_notify_email' );
	if ( ! $to || ! is_email( $to ) ) {
		$to = get_option( 'admin_email' );
	}
	return $to;
}

/**
 * Build the body shared by both emails.
 *
 * @param bool $redact Blank out sensitive answers (always true for email).
 */
function pupbf_summary_text( $post_id, $redact = true ) {
	$out = '';
	foreach ( pupbf_schema() as $section ) {
		$lines = array();
		foreach ( $section['fields'] as $key => $def ) {
			if ( 'signature' === ( isset( $def['type'] ) ? $def['type'] : '' ) ) {
				continue;
			}
			$label   = html_entity_decode( wp_strip_all_tags( $def['label'] ), ENT_QUOTES, 'UTF-8' );
			$lines[] = $label . ': ' . html_entity_decode( pupbf_format_answer( $post_id, $key, $redact ), ENT_QUOTES, 'UTF-8' );
		}
		if ( $lines ) {
			$out .= "\n" . html_entity_decode( wp_strip_all_tags( $section['title'] ), ENT_QUOTES, 'UTF-8' ) . "\n";
			$out .= str_repeat( '-', 40 ) . "\n";
			$out .= implode( "\n", $lines ) . "\n";
		}
	}
	return $out;
}

function pupbf_notify( $post_id, $values ) {
	$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
	$owner   = $values['owner_name'];
	$dogs    = $values['dog_names'];

	/* --- to Dee --------------------------------------------------------- */
	$admin_body  = "A new booking agreement has just been signed on the website.\n\n";
	$admin_body .= 'From: ' . $owner . "\n";
	$admin_body .= 'Dog(s): ' . $dogs . "\n";
	$admin_body .= 'Phone: ' . $values['phone'] . "\n";
	$admin_body .= 'Email: ' . $values['email'] . "\n";
	$admin_body .= "\nView the full signed form, including the signature and any access details:\n";
	$admin_body .= admin_url( 'post.php?post=' . $post_id . '&action=edit' ) . "\n";
	$admin_body .= "\nHome access details are stored on the website only and are deliberately left out of this email.\n";
	$admin_body .= "\n" . str_repeat( '=', 40 ) . "\n";
	$admin_body .= pupbf_summary_text( $post_id, true );
	$admin_body .= "\nSigned: " . get_post_meta( $post_id, '_pupbf_signed_at', true );
	$admin_body .= "\nAgreement version: " . PUPBF_AGREEMENT_VERSION . "\n";

	$admin_headers = $headers;
	if ( is_email( $values['email'] ) ) {
		$admin_headers[] = 'Reply-To: ' . $owner . ' <' . $values['email'] . '>';
	}

	$admin_sent = wp_mail(
		pupbf_notify_email(),
		'New booking form — ' . $owner . ( $dogs ? ' (' . $dogs . ')' : '' ),
		$admin_body,
		$admin_headers
	);

	/* --- their copy ------------------------------------------------------ */
	$client_sent = false;
	if ( is_email( $values['email'] ) && 'no' !== get_option( 'pupbf_send_client_copy', 'yes' ) ) {
		$body  = 'Hi ' . $owner . ",\n\n";
		$body .= "Thank you — your booking agreement with Eddie's Pupventures is signed and safely received. Here's a copy for your records.\n";
		$body .= "\nI'll be in touch shortly to confirm your first walk. If anything below needs changing, just reply to this email or ring me on 07366 308303.\n";
		$body .= "\n" . str_repeat( '=', 40 ) . "\n";
		$body .= pupbf_summary_text( $post_id, true );
		$body .= "\nSigned: " . get_post_meta( $post_id, '_pupbf_signed_at', true );
		$body .= "\nAgreement version: " . PUPBF_AGREEMENT_VERSION . "\n";
		$body .= "\nFor your security, any home access details you gave me are stored on the website rather than repeated in this email.\n";
		$body .= "\nThe full terms you agreed to are always on the website: " . home_url( '/booking-form/' ) . "\n";
		$body .= "\nWith wags,\nDee & Eddie\nEddie's Pupventures, Basingstoke\n";

		$client_headers   = $headers;
		$client_headers[] = 'Reply-To: ' . pupbf_notify_email();

		$client_sent = wp_mail(
			$values['email'],
			'Your booking agreement with Eddie\'s Pupventures',
			$body,
			$client_headers
		);
	}

	update_post_meta( $post_id, '_pupbf_admin_email_sent', $admin_sent ? '1' : '0' );
	update_post_meta( $post_id, '_pupbf_client_email_sent', $client_sent ? '1' : '0' );

	if ( ! $admin_sent ) {
		// The form is already saved; this is a heads-up, not a lost booking.
		error_log( '[Pupventures Booking] Notification email to ' . pupbf_notify_email() . ' was not accepted for sending. Booking #' . $post_id . ' is still saved in WordPress.' );
	}
}
