<?php
/**
 * Plugin Name: Eddie's Pupventures — Booking Agreement
 * Plugin URI:  https://eddiespupventures.co.uk
 * Description: A mobile-friendly online Booking Agreement &amp; Terms form with digital signature. Submissions are stored securely inside WordPress, emailed to you, and can be printed or saved as PDF. Shortcode: <code>[pupventures_booking_form]</code>
 * Version:     1.1.0
 * Author:      Eddie's Pupventures
 * License:     GPL-2.0-or-later
 * Text Domain: pupventures-booking
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PUPBF_VERSION', '1.1.0' );
define( 'PUPBF_FILE', __FILE__ );
define( 'PUPBF_DIR', plugin_dir_path( __FILE__ ) );
define( 'PUPBF_URL', plugin_dir_url( __FILE__ ) );
define( 'PUPBF_CPT', 'pup_booking' );

/** The wording revision these submissions were signed against. */
define( 'PUPBF_AGREEMENT_VERSION', 'September 2026' );

require_once PUPBF_DIR . 'includes/schema.php';
require_once PUPBF_DIR . 'includes/crypto.php';
require_once PUPBF_DIR . 'includes/storage.php';
require_once PUPBF_DIR . 'includes/form.php';
require_once PUPBF_DIR . 'includes/submit.php';
require_once PUPBF_DIR . 'includes/admin.php';
require_once PUPBF_DIR . 'includes/print.php';
require_once PUPBF_DIR . 'includes/pdf.php';
require_once PUPBF_DIR . 'includes/privacy.php';

/* ---------------------------------------------------------------------------
 * Activation — register the post type, create the booking page.
 * ------------------------------------------------------------------------- */
function pupbf_activate() {
	pupbf_register_cpt();

	// Create the "Booking Form" page once, if it isn't already there.
	$existing = get_option( 'pupbf_page_id' );
	if ( $existing && 'publish' === get_post_status( $existing ) ) {
		flush_rewrite_rules();
		return;
	}

	$found = get_page_by_path( 'booking-form' );
	if ( $found ) {
		update_option( 'pupbf_page_id', $found->ID );
		flush_rewrite_rules();
		return;
	}

	$page_id = wp_insert_post(
		array(
			'post_title'   => 'Booking Form',
			'post_name'    => 'booking-form',
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_content' => '<!-- wp:shortcode -->[pupventures_booking_form]<!-- /wp:shortcode -->',
		)
	);

	if ( $page_id && ! is_wp_error( $page_id ) ) {
		update_option( 'pupbf_page_id', $page_id );
		update_post_meta( $page_id, '_pup_seo_title', "Booking Form | Eddie's Pupventures" );
		update_post_meta( $page_id, '_pup_meta_desc', 'Book dog walks with Eddie\'s Pupventures in Basingstoke. Complete and sign your booking agreement online — it only takes a few minutes on your phone.' );
	}

	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'pupbf_activate' );

function pupbf_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'pupbf_deactivate' );

/* ---------------------------------------------------------------------------
 * Front-end assets — only loaded on pages that actually show the form.
 * ------------------------------------------------------------------------- */
function pupbf_enqueue() {
	if ( ! is_singular() ) {
		return;
	}
	$post = get_post();
	if ( ! $post || ! has_shortcode( (string) $post->post_content, 'pupventures_booking_form' ) ) {
		return;
	}

	wp_enqueue_style( 'pupbf-form', PUPBF_URL . 'assets/css/form.css', array(), PUPBF_VERSION );
	wp_enqueue_script( 'pupbf-form', PUPBF_URL . 'assets/js/form.js', array(), PUPBF_VERSION, true );
	wp_localize_script(
		'pupbf-form',
		'PUPBF',
		array(
			'prices' => pupbf_prices(),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'pupbf_enqueue' );
