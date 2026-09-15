<?php
/**
 * The front-end form: one step per screen, built from pupbf_schema().
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function pupbf_field_id( $key ) {
	return 'pupbf-' . str_replace( '_', '-', $key );
}

/** Re-fill the form from the last attempt when validation sent them back. */
function pupbf_old( $key, $fallback = '' ) {
	$old = pupbf_flash_get( 'values' );
	if ( is_array( $old ) && array_key_exists( $key, $old ) ) {
		return $old[ $key ];
	}
	return $fallback;
}

function pupbf_render_field( $key, $def ) {
	$type     = isset( $def['type'] ) ? $def['type'] : 'text';
	$id       = pupbf_field_id( $key );
	$required = ! empty( $def['required'] );
	$name     = 'pupbf[' . $key . ']';
	$errors   = pupbf_flash_get( 'errors' );
	$error    = ( is_array( $errors ) && isset( $errors[ $key ] ) ) ? $errors[ $key ] : '';

	$wrap_class = 'pupbf-field pupbf-type-' . $type;
	if ( ! empty( $def['class'] ) ) {
		$wrap_class .= ' ' . $def['class'];
	}
	if ( $error ) {
		$wrap_class .= ' pupbf-has-error';
	}

	$data_attrs = '';
	if ( ! empty( $def['showif'] ) ) {
		$data_attrs .= ' data-showif-field="' . esc_attr( $def['showif'][0] ) . '"';
		$data_attrs .= ' data-showif-value="' . esc_attr( $def['showif'][1] ) . '"';
		$wrap_class .= ' pupbf-conditional';
	}

	echo '<div class="' . esc_attr( $wrap_class ) . '" data-field="' . esc_attr( $key ) . '"' . $data_attrs . '>'; // phpcs:ignore

	$label_html = wp_kses( $def['label'], array( 'em' => array(), 'strong' => array(), 'br' => array() ) );
	$req_mark   = $required ? ' <span class="pupbf-req" aria-hidden="true">*</span>' : '';

	switch ( $type ) {

		case 'checkbox':
			echo '<label class="pupbf-check" for="' . esc_attr( $id ) . '">';
			echo '<input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1"';
			checked( '1', (string) pupbf_old( $key ) );
			if ( $required ) {
				echo ' data-required="1"';
			}
			echo ' />';
			echo '<span class="pupbf-check-box" aria-hidden="true"></span>';
			echo '<span class="pupbf-check-text">' . $label_html . $req_mark . '</span>'; // phpcs:ignore
			echo '</label>';
			break;

		case 'radio':
			echo '<fieldset class="pupbf-radios"' . ( $required ? ' data-required="1"' : '' ) . '>';
			echo '<legend class="pupbf-label">' . $label_html . $req_mark . '</legend>';
			$old = (string) pupbf_old( $key );
			$i   = 0;
			foreach ( (array) $def['options'] as $value => $label ) {
				$rid = $id . '-' . ( ++$i );
				echo '<label class="pupbf-radio" for="' . esc_attr( $rid ) . '">';
				echo '<input type="radio" id="' . esc_attr( $rid ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"';
				checked( (string) $value, $old );
				echo ' />';
				echo '<span class="pupbf-radio-dot" aria-hidden="true"></span>';
				echo '<span class="pupbf-radio-text">' . wp_kses( $label, array( 'em' => array(), 'strong' => array() ) ) . '</span>'; // phpcs:ignore
				echo '</label>';
			}
			echo '</fieldset>';
			break;

		case 'days':
			echo '<fieldset class="pupbf-days">';
			echo '<legend class="pupbf-label">' . $label_html . '</legend>';
			$old = pupbf_old( $key, array() );
			foreach ( pupbf_weekdays() as $slug => $label ) {
				$on   = ! empty( $old[ $slug ]['on'] );
				$time = isset( $old[ $slug ]['time'] ) ? $old[ $slug ]['time'] : '';
				$did  = $id . '-' . $slug;
				echo '<div class="pupbf-day' . ( $on ? ' is-on' : '' ) . '">';
				echo '<label class="pupbf-check pupbf-day-check" for="' . esc_attr( $did ) . '">';
				echo '<input type="checkbox" id="' . esc_attr( $did ) . '" name="pupbf[' . esc_attr( $key ) . '][' . esc_attr( $slug ) . '][on]" value="1"';
				checked( true, $on );
				echo ' />';
				echo '<span class="pupbf-check-box" aria-hidden="true"></span>';
				echo '<span class="pupbf-check-text">' . esc_html( $label ) . '</span>';
				echo '</label>';
				echo '<input class="pupbf-day-time" type="text" inputmode="text" placeholder="e.g. 11am" aria-label="' . esc_attr( $label . ' time' ) . '" name="pupbf[' . esc_attr( $key ) . '][' . esc_attr( $slug ) . '][time]" value="' . esc_attr( $time ) . '" />';
				echo '</div>';
			}
			echo '</fieldset>';
			break;

		case 'textarea':
			echo '<label class="pupbf-label" for="' . esc_attr( $id ) . '">' . $label_html . $req_mark . '</label>';
			echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="' . esc_attr( isset( $def['rows'] ) ? $def['rows'] : 3 ) . '"';
			if ( ! empty( $def['autocomplete'] ) ) {
				echo ' autocomplete="' . esc_attr( $def['autocomplete'] ) . '"';
			}
			if ( $required ) {
				echo ' data-required="1"';
			}
			echo '>' . esc_textarea( (string) pupbf_old( $key ) ) . '</textarea>';
			break;

		case 'signature':
			echo '<span class="pupbf-label">' . $label_html . $req_mark . '</span>';
			echo '<div class="pupbf-sig">';
			echo '<canvas class="pupbf-sig-pad" width="600" height="200" role="img" aria-label="Signature area"></canvas>';
			echo '<span class="pupbf-sig-hint">Sign here</span>';
			echo '<button type="button" class="pupbf-sig-clear">Clear and try again</button>';
			echo '</div>';
			echo '<input type="hidden" name="' . esc_attr( $name ) . '" class="pupbf-sig-data" value="" />';
			break;

		case 'number':
			echo '<label class="pupbf-label" for="' . esc_attr( $id ) . '">' . $label_html . $req_mark . '</label>';
			$val = pupbf_old( $key, isset( $def['default'] ) ? $def['default'] : '' );
			echo '<input type="number" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $val ) . '"';
			echo ' min="' . esc_attr( isset( $def['min'] ) ? $def['min'] : 0 ) . '"';
			if ( isset( $def['max'] ) ) {
				echo ' max="' . esc_attr( $def['max'] ) . '"';
			}
			echo ' inputmode="numeric"';
			if ( $required ) {
				echo ' data-required="1"';
			}
			echo ' />';
			break;

		default:
			echo '<label class="pupbf-label" for="' . esc_attr( $id ) . '">' . $label_html . $req_mark . '</label>';
			echo '<input type="' . esc_attr( $type ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) pupbf_old( $key ) ) . '"';
			if ( ! empty( $def['autocomplete'] ) ) {
				echo ' autocomplete="' . esc_attr( $def['autocomplete'] ) . '"';
			}
			if ( ! empty( $def['inputmode'] ) ) {
				echo ' inputmode="' . esc_attr( $def['inputmode'] ) . '"';
			}
			if ( $required ) {
				echo ' data-required="1"';
			}
			echo ' />';
			break;
	}

	if ( ! empty( $def['help'] ) ) {
		echo '<p class="pupbf-help">' . wp_kses( $def['help'], array( 'em' => array(), 'strong' => array(), 'a' => array( 'href' => array() ) ) ) . '</p>';
	}
	if ( $error ) {
		echo '<p class="pupbf-error" role="alert">' . esc_html( $error ) . '</p>';
	}

	echo '</div>';
}

/** The scrollable terms panel shown on the agreement step. */
function pupbf_render_terms() {
	echo '<div class="pupbf-terms" tabindex="0" aria-label="Booking agreement terms and conditions">';
	foreach ( pupbf_terms_blocks() as $heading => $lines ) {
		echo '<h3>' . wp_kses( $heading, array( 'em' => array(), 'amp' => array() ) ) . '</h3>'; // phpcs:ignore
		echo '<ul>';
		foreach ( $lines as $line ) {
			echo '<li>' . wp_kses( $line, array( 'em' => array(), 'strong' => array() ) ) . '</li>'; // phpcs:ignore
		}
		echo '</ul>';
	}
	echo '</div>';
	echo '<p class="pupbf-terms-note">Scroll the box above to read the full agreement. You\'ll get your own signed copy by email once you\'ve sent this form.</p>';
}

/**
 * [pupventures_booking_form]
 */
function pupbf_form_shortcode() {
	// Arrived without the private link? Show a friendly dead end instead.
	if ( ! pupbf_has_link_code() ) {
		return pupbf_locked_notice();
	}

	ob_start();

	// Success screen.
	if ( isset( $_GET['pupbf'] ) && 'thanks' === sanitize_key( wp_unslash( $_GET['pupbf'] ) ) ) {
		echo '<div class="pupbf-done">';
		echo '<div class="pupbf-done-paw" aria-hidden="true">🐾</div>';
		echo '<h2>All done — thank you!</h2>';
		echo '<p>Your booking agreement is signed and safely with me. I\'ve emailed you a copy for your records, and I\'ll be in touch shortly to confirm your first walk.</p>';
		echo '<p class="pupbf-done-sub">If your copy doesn\'t arrive in the next few minutes, do check your junk folder.</p>';
		echo '</div>';
		return ob_get_clean();
	}

	$schema  = pupbf_schema();
	$notice  = pupbf_flash_get( 'notice' );
	$errors  = pupbf_flash_get( 'errors' );
	$sections = array_keys( $schema );
	$total   = count( $sections );

	if ( $notice ) {
		echo '<div class="pupbf-notice pupbf-notice-err" role="alert">' . esc_html( $notice ) . '</div>';
	}

	?>
	<form class="pupbf-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" novalidate>
		<input type="hidden" name="action" value="pupbf_submit" />
		<?php wp_nonce_field( 'pupbf_submit', 'pupbf_nonce' ); ?>
		<input type="hidden" name="pupbf_redirect" value="<?php echo esc_url( get_permalink() ); ?>" />
		<input type="hidden" name="pupbf_started" value="<?php echo esc_attr( time() ); ?>" />
		<div class="pupbf-hp" aria-hidden="true">
			<label>Leave this field empty<input type="text" name="pupbf_hp" tabindex="-1" autocomplete="off" /></label>
		</div>

		<div class="pupbf-progress" role="group" aria-label="Form progress">
			<div class="pupbf-progress-bar"><span class="pupbf-progress-fill"></span></div>
			<p class="pupbf-progress-text">Step <span class="pupbf-step-now">1</span> of <?php echo (int) $total; ?></p>
		</div>

		<?php
		$i = 0;
		foreach ( $schema as $skey => $section ) :
			$i++;
			?>
			<section class="pupbf-step<?php echo 1 === $i ? ' is-active' : ''; ?>" data-step="<?php echo (int) $i; ?>" data-section="<?php echo esc_attr( $skey ); ?>">
				<header class="pupbf-step-head">
					<h2><?php echo wp_kses( $section['title'], array( 'amp' => array() ) ); // phpcs:ignore ?></h2>
					<?php if ( ! empty( $section['blurb'] ) ) : ?>
						<p><?php echo esc_html( $section['blurb'] ); ?></p>
					<?php endif; ?>
				</header>

				<?php if ( 'terms' === $skey ) { pupbf_render_terms(); } ?>

				<?php
				foreach ( $section['fields'] as $fkey => $fdef ) {
					pupbf_render_field( $fkey, $fdef );
				}
				?>

				<?php if ( 'walks' === $skey ) : ?>
					<div class="pupbf-estimate" hidden>
						<h3>Your estimate</h3>
						<p class="pupbf-estimate-line"></p>
						<p class="pupbf-estimate-note">A guide only, based on the weekday price list — I'll confirm the exact amount when we chat. Weekend and bank holiday walks are charged at the higher rate.</p>
					</div>
				<?php endif; ?>

				<?php if ( 'sign' === $skey ) : ?>
					<p class="pupbf-legal">By signing you agree to the Eddie's Pupventures Booking Agreement and Terms &amp; Conditions (<?php echo esc_html( PUPBF_AGREEMENT_VERSION ); ?>). The date, and a record that you signed, are stored with your form.</p>
				<?php endif; ?>

				<div class="pupbf-nav">
					<?php if ( $i > 1 ) : ?>
						<button type="button" class="pupbf-btn pupbf-back">Back</button>
					<?php endif; ?>
					<?php if ( $i < $total ) : ?>
						<button type="button" class="pupbf-btn pupbf-next">Continue</button>
					<?php else : ?>
						<button type="submit" class="pupbf-btn pupbf-submit">Send my booking form</button>
					<?php endif; ?>
				</div>
			</section>
		<?php endforeach; ?>

		<noscript>
			<p class="pupbf-notice pupbf-notice-err">This form works best with JavaScript switched on. With it off you'll see every question at once — that still works, just scroll down and press "Send my booking form" at the bottom. The signature box needs JavaScript, so if you can't sign, give me a ring on 07366 308303 instead.</p>
		</noscript>
	</form>
	<?php

	pupbf_flash_clear();
	return ob_get_clean();
}
add_shortcode( 'pupventures_booking_form', 'pupbf_form_shortcode' );

/* ---------------------------------------------------------------------------
 * Flash storage — carries validation errors and typed answers back to the form
 * after a failed submission, without using the session or a login cookie.
 * ------------------------------------------------------------------------- */

function pupbf_flash_cookie() {
	return 'pupbf_flash';
}

function pupbf_flash_set( $data ) {
	$token = wp_generate_password( 20, false, false );
	set_transient( 'pupbf_flash_' . $token, $data, 15 * MINUTE_IN_SECONDS );
	setcookie( pupbf_flash_cookie(), $token, time() + ( 15 * MINUTE_IN_SECONDS ), COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );
}

function pupbf_flash_all() {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}
	$cache = array();
	if ( ! empty( $_COOKIE[ pupbf_flash_cookie() ] ) ) {
		$token = preg_replace( '/[^A-Za-z0-9]/', '', wp_unslash( $_COOKIE[ pupbf_flash_cookie() ] ) );
		if ( $token ) {
			$data = get_transient( 'pupbf_flash_' . $token );
			if ( is_array( $data ) ) {
				$cache = $data;
			}
		}
	}
	return $cache;
}

function pupbf_flash_get( $key ) {
	$all = pupbf_flash_all();
	return isset( $all[ $key ] ) ? $all[ $key ] : null;
}

function pupbf_flash_clear() {
	if ( empty( $_COOKIE[ pupbf_flash_cookie() ] ) ) {
		return;
	}
	$token = preg_replace( '/[^A-Za-z0-9]/', '', wp_unslash( $_COOKIE[ pupbf_flash_cookie() ] ) );
	if ( $token ) {
		delete_transient( 'pupbf_flash_' . $token );
	}
	if ( ! headers_sent() ) {
		setcookie( pupbf_flash_cookie(), '', time() - 3600, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );
	}
}
