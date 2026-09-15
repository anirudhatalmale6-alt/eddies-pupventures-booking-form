<?php
/**
 * Keeping the booking form off the open web.
 *
 * It isn't a marketing page — it's a document you hand to someone you've
 * already spoken to. A stranger who fills it in cold walks away believing
 * they have a booking, which is worse than having no form at all.
 *
 * Two layers:
 *   1. Always on — no indexing, no site search, no sitemap.
 *   2. Optional  — a code in the link, so a guessed URL shows nothing.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The page holding the form, if we know it. */
function pupbf_page_id() {
	return (int) get_option( 'pupbf_page_id' );
}

function pupbf_is_booking_page( $post_id = null ) {
	$page_id = pupbf_page_id();
	if ( ! $page_id ) {
		return false;
	}
	if ( null === $post_id ) {
		if ( ! is_singular() ) {
			return false;
		}
		$post_id = get_queried_object_id();
	}
	return (int) $post_id === $page_id;
}

/* ---------------------------------------------------------------------------
 * Layer 1 — invisible to search engines and to site search.
 * ------------------------------------------------------------------------- */

function pupbf_noindex() {
	if ( ! pupbf_is_booking_page() ) {
		return;
	}
	echo '<meta name="robots" content="noindex, nofollow, noarchive, nosnippet" />' . "\n";
}
add_action( 'wp_head', 'pupbf_noindex', 0 );

/** Belt and braces: WordPress 5.7+ also emits its own robots directives. */
function pupbf_robots_filter( $robots ) {
	if ( pupbf_is_booking_page() ) {
		$robots['noindex']  = true;
		$robots['nofollow'] = true;
		$robots['noarchive'] = true;
	}
	return $robots;
}
add_filter( 'wp_robots', 'pupbf_robots_filter' );

/** Keep it out of the XML sitemap. */
function pupbf_exclude_from_sitemap( $args, $post_type ) {
	if ( 'page' !== $post_type ) {
		return $args;
	}
	$page_id = pupbf_page_id();
	if ( $page_id ) {
		$args['post__not_in'] = array_merge(
			isset( $args['post__not_in'] ) ? (array) $args['post__not_in'] : array(),
			array( $page_id )
		);
	}
	return $args;
}
add_filter( 'wp_sitemaps_posts_query_args', 'pupbf_exclude_from_sitemap', 10, 2 );

/** And out of the site's own search results. */
function pupbf_exclude_from_search( $query ) {
	if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
		return;
	}
	$page_id = pupbf_page_id();
	if ( $page_id ) {
		$not_in = (array) $query->get( 'post__not_in' );
		$not_in[] = $page_id;
		$query->set( 'post__not_in', $not_in );
	}
}
add_action( 'pre_get_posts', 'pupbf_exclude_from_search' );

/** Don't let it appear in page lists that are generated automatically. */
add_filter( 'wp_list_pages_excludes', function ( $excludes ) {
	$page_id = pupbf_page_id();
	if ( $page_id ) {
		$excludes[] = $page_id;
	}
	return $excludes;
} );

/* ---------------------------------------------------------------------------
 * Layer 2 — the optional private link.
 * ------------------------------------------------------------------------- */

function pupbf_link_code_enabled() {
	return 'yes' === get_option( 'pupbf_require_code', 'no' );
}

/**
 * The code that unlocks the form. Generated once and kept until she asks for
 * a new one — regenerating quietly invalidates every link already sent out,
 * so it is always a deliberate click.
 */
function pupbf_link_code() {
	$code = get_option( 'pupbf_link_code' );
	if ( ! $code ) {
		$code = pupbf_new_link_code();
	}
	return $code;
}

function pupbf_new_link_code() {
	$code = strtolower( wp_generate_password( 10, false, false ) );
	update_option( 'pupbf_link_code', $code );
	return $code;
}

function pupbf_private_link() {
	$page_id = pupbf_page_id();
	$url     = $page_id ? get_permalink( $page_id ) : home_url( '/booking-form/' );
	if ( ! pupbf_link_code_enabled() ) {
		return $url;
	}
	return add_query_arg( 'k', pupbf_link_code(), $url );
}

/**
 * Has this visitor been given the link?
 *
 * The code may arrive in the URL, or in the cookie we set the first time —
 * otherwise a failed submission would bounce them back to a locked page.
 */
function pupbf_has_link_code() {
	if ( ! pupbf_link_code_enabled() ) {
		return true;
	}
	if ( current_user_can( 'manage_options' ) ) {
		return true;
	}

	$expected = pupbf_link_code();

	// An empty secret would make hash_equals() true for everyone.
	if ( ! is_string( $expected ) || strlen( $expected ) < 8 ) {
		return false;
	}

	if ( isset( $_GET['k'] ) ) {
		$given = preg_replace( '/[^a-z0-9]/', '', strtolower( sanitize_text_field( wp_unslash( $_GET['k'] ) ) ) );
		if ( hash_equals( $expected, $given ) ) {
			if ( ! headers_sent() ) {
				setcookie(
					'pupbf_key',
					$expected,
					time() + DAY_IN_SECONDS,
					COOKIEPATH ? COOKIEPATH : '/',
					COOKIE_DOMAIN,
					is_ssl(),
					true
				);
			}
			return true;
		}
		return false;
	}

	if ( ! empty( $_COOKIE['pupbf_key'] ) ) {
		$given = preg_replace( '/[^a-z0-9]/', '', strtolower( wp_unslash( $_COOKIE['pupbf_key'] ) ) );
		return hash_equals( $expected, $given );
	}

	return false;
}

/** Shown in place of the form when someone arrives without the code. */
function pupbf_locked_notice() {
	ob_start();
	?>
	<div class="pupbf-locked">
		<div class="pupbf-locked-paw" aria-hidden="true">🐾</div>
		<h2>This form is by invitation</h2>
		<p>The booking agreement is sent out personally once we've had a chat about your dog and what you need.</p>
		<p>If you'd like to book a walk, give me a ring on <a href="tel:+447366308303">07366&nbsp;308303</a> or drop me a line through the <a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">contact page</a> and I'll send you the link.</p>
	</div>
	<?php
	return ob_get_clean();
}
