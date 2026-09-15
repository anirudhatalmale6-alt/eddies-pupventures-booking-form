<?php
/**
 * At-rest encryption for the handful of genuinely sensitive answers
 * (right now: the free-text home access answer).
 *
 * The form no longer asks for keycodes — clients are told to pass those on
 * separately — but people type them in anyway, so the field stays protected.
 *
 * The key is derived from this site's WordPress salts, which live in
 * wp-config.php rather than the database — so a stolen database dump on its
 * own does not hand anyone a client's home access details.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PUPBF_ENC_PREFIX = 'pupbf1:';

function pupbf_can_encrypt() {
	return function_exists( 'openssl_encrypt' )
		&& function_exists( 'openssl_decrypt' )
		&& in_array( 'aes-256-cbc', array_map( 'strtolower', openssl_get_cipher_methods() ), true );
}

function pupbf_enc_key() {
	$material = '';
	foreach ( array( 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT' ) as $c ) {
		if ( defined( $c ) ) {
			$material .= constant( $c );
		}
	}
	if ( '' === $material ) {
		// Extremely unusual (salts missing) — fall back to a stored random key
		// so the data is at least not stored as plain text.
		$material = get_option( 'pupbf_fallback_key' );
		if ( ! $material ) {
			$material = wp_generate_password( 64, true, true );
			add_option( 'pupbf_fallback_key', $material, '', 'no' );
		}
	}
	return hash( 'sha256', 'pupbf|' . $material, true );
}

/**
 * @return string Encrypted, prefixed payload — or the original string if this
 *                server has no OpenSSL (the data is still stored, just not
 *                encrypted; the admin screen says so plainly).
 */
function pupbf_encrypt( $plain ) {
	if ( '' === $plain || null === $plain ) {
		return '';
	}
	if ( ! pupbf_can_encrypt() ) {
		return $plain;
	}
	$iv     = openssl_random_pseudo_bytes( 16 );
	$cipher = openssl_encrypt( $plain, 'aes-256-cbc', pupbf_enc_key(), OPENSSL_RAW_DATA, $iv );
	if ( false === $cipher ) {
		return $plain;
	}
	return PUPBF_ENC_PREFIX . base64_encode( $iv . $cipher );
}

/**
 * @return string|false Plain text, or false when the payload cannot be read
 *                      (for example if the site's salts were rotated).
 */
function pupbf_decrypt( $stored ) {
	if ( '' === $stored || null === $stored ) {
		return '';
	}
	if ( 0 !== strpos( $stored, PUPBF_ENC_PREFIX ) ) {
		return $stored; // Stored before encryption was available.
	}
	if ( ! pupbf_can_encrypt() ) {
		return false;
	}
	$raw = base64_decode( substr( $stored, strlen( PUPBF_ENC_PREFIX ) ), true );
	if ( false === $raw || strlen( $raw ) <= 16 ) {
		return false;
	}
	$iv    = substr( $raw, 0, 16 );
	$plain = openssl_decrypt( substr( $raw, 16 ), 'aes-256-cbc', pupbf_enc_key(), OPENSSL_RAW_DATA, $iv );
	return ( false === $plain ) ? false : $plain;
}

function pupbf_is_encrypted( $stored ) {
	return is_string( $stored ) && 0 === strpos( $stored, PUPBF_ENC_PREFIX );
}
