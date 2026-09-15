<?php
/**
 * A clean, self-contained printable copy of one signed agreement — the thing
 * that replaces the paper in the filing cabinet. "Save as PDF" in the browser's
 * print dialog produces a proper PDF with no extra software.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function pupbf_print_view() {
	$id = isset( $_GET['booking'] ) ? (int) $_GET['booking'] : 0;

	if ( ! $id || ! current_user_can( 'manage_options' ) ) {
		wp_die( 'You do not have permission to view this booking form.', 403 );
	}
	check_admin_referer( 'pupbf_print_' . $id );

	$post = get_post( $id );
	if ( ! $post || PUPBF_CPT !== $post->post_type ) {
		wp_die( 'Booking form not found.', 404 );
	}

	$owner  = pupbf_get_answer( $id, 'owner_name' );
	$signed = get_post_meta( $id, '_pupbf_signed_at', true );
	$sig    = pupbf_get_answer( $id, 'signature' );
	$logo   = get_stylesheet_directory_uri() . '/assets/images/beagle-logo.png';
	$logo_f = get_stylesheet_directory() . '/assets/images/beagle-logo.png';

	header( 'Content-Type: text/html; charset=utf-8' );
	nocache_headers();
	?>
<!doctype html>
<html lang="en-GB">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta name="robots" content="noindex, nofollow" />
<title>Booking agreement — <?php echo esc_html( $owner ); ?></title>
<style>
	*, *::before, *::after { box-sizing: border-box; }
	body {
		margin: 0; padding: 32px 24px 64px;
		font-family: -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
		font-size: 13px; line-height: 1.5; color: #2C2724; background: #f6f6f6;
	}
	.sheet { max-width: 800px; margin: 0 auto; background: #fff; padding: 40px; border-radius: 8px; }
	.head { display: flex; align-items: center; gap: 16px; border-bottom: 3px solid #FF6B4A; padding-bottom: 18px; margin-bottom: 24px; }
	.head img { width: 62px; height: auto; }
	.head h1 { margin: 0; font-size: 20px; letter-spacing: -0.01em; }
	.head p { margin: 2px 0 0; color: #6d635c; font-size: 12px; }
	h2 { font-size: 13px; text-transform: uppercase; letter-spacing: 0.07em; color: #FF6B4A; margin: 26px 0 8px; }
	table { width: 100%; border-collapse: collapse; }
	th, td { text-align: left; vertical-align: top; padding: 6px 8px; border-bottom: 1px solid #eee; }
	th { width: 40%; font-weight: 600; color: #4a433e; }
	.terms h3 { font-size: 12px; margin: 14px 0 4px; }
	.terms ul { margin: 0 0 0 18px; padding: 0; }
	.terms li { margin-bottom: 3px; color: #4a433e; }
	.sigbox { margin-top: 10px; border: 1px solid #ddd; border-radius: 6px; padding: 12px; display: inline-block; background: #fff; }
	.sigbox img { display: block; max-width: 320px; height: auto; }
	.sigline { margin-top: 6px; font-size: 11px; color: #6d635c; }
	.audit { margin-top: 26px; padding: 12px 14px; background: #FFFBF3; border: 1px solid #f0e3d2; border-radius: 6px; font-size: 11px; color: #6d635c; }
	.audit strong { color: #2C2724; }
	.bar { position: fixed; top: 0; left: 0; right: 0; background: #2C2724; color: #fff; padding: 10px 16px; display: flex; gap: 10px; align-items: center; justify-content: center; }
	.bar button, .bar a { font: inherit; padding: 7px 16px; border-radius: 999px; border: 0; cursor: pointer; background: #FF6B4A; color: #fff; text-decoration: none; font-weight: 600; }
	.bar a { background: transparent; border: 1px solid rgba(255,255,255,.4); }
	body { padding-top: 76px; }
	@media print {
		body { padding: 0; background: #fff; }
		.sheet { max-width: none; padding: 0; border-radius: 0; }
		.bar { display: none; }
		h2 { color: #000; }
		.head { border-bottom-color: #000; }
		a { color: inherit; text-decoration: none; }
	}
</style>
</head>
<body>
<div class="bar">
	<button type="button" onclick="window.print()">Print / save as PDF</button>
	<a href="<?php echo esc_url( admin_url( 'post.php?post=' . $id . '&action=edit' ) ); ?>">Back to the booking</a>
</div>

<div class="sheet">
	<div class="head">
		<?php if ( file_exists( $logo_f ) ) : ?>
			<img src="<?php echo esc_url( $logo ); ?>" alt="" />
		<?php endif; ?>
		<div>
			<h1>Eddie's Pupventures — Booking Agreement</h1>
			<p>Dog walking · Basingstoke, Hampshire · Agreement version <?php echo esc_html( get_post_meta( $id, '_pupbf_agreement_version', true ) ); ?></p>
		</div>
	</div>

	<?php
	foreach ( pupbf_schema() as $section ) :
		$rows = array();
		foreach ( $section['fields'] as $key => $def ) {
			if ( 'signature' === ( isset( $def['type'] ) ? $def['type'] : '' ) ) {
				continue;
			}
			if ( ! empty( $def['showif'] ) ) {
				$dep = pupbf_get_answer( $id, $def['showif'][0] );
				if ( (string) $dep !== (string) $def['showif'][1] ) {
					continue;
				}
			}
			$rows[ $key ] = $def;
		}
		if ( ! $rows ) {
			continue;
		}
		?>
		<h2><?php echo wp_kses( $section['title'], array( 'amp' => array() ) ); // phpcs:ignore ?></h2>
		<table><tbody>
		<?php foreach ( $rows as $key => $def ) : ?>
			<tr>
				<th><?php echo wp_kses( $def['label'], array( 'amp' => array(), 'em' => array(), 'strong' => array() ) ); // phpcs:ignore ?></th>
				<td><?php echo nl2br( esc_html( pupbf_format_answer( $id, $key ) ) ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody></table>
	<?php endforeach; ?>

	<h2>Terms &amp; conditions agreed</h2>
	<div class="terms">
		<?php foreach ( pupbf_terms_blocks() as $heading => $lines ) : ?>
			<h3><?php echo wp_kses( $heading, array( 'amp' => array() ) ); // phpcs:ignore ?></h3>
			<ul>
				<?php foreach ( $lines as $line ) : ?>
					<li><?php echo wp_kses( $line, array( 'em' => array(), 'strong' => array() ) ); // phpcs:ignore ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endforeach; ?>
	</div>

	<h2>Signature</h2>
	<?php if ( $sig ) : ?>
		<div class="sigbox">
			<img src="<?php echo esc_attr( $sig ); ?>" alt="Signature" />
			<p class="sigline">
				<?php echo esc_html( pupbf_get_answer( $id, 'signed_name' ) ); ?><br />
				Signed <?php echo esc_html( $signed ? date_i18n( 'j F Y \a\t g:ia', strtotime( $signed ) ) : '—' ); ?>
			</p>
		</div>
	<?php else : ?>
		<p>No signature stored.</p>
	<?php endif; ?>

	<div class="audit">
		<strong>Signature record.</strong>
		Submitted <?php echo esc_html( $signed ? date_i18n( 'j F Y \a\t g:ia', strtotime( $signed ) ) : '—' ); ?>
		from IP <?php echo esc_html( get_post_meta( $id, '_pupbf_ip', true ) ? get_post_meta( $id, '_pupbf_ip', true ) : 'unknown' ); ?>,
		against agreement version <?php echo esc_html( get_post_meta( $id, '_pupbf_agreement_version', true ) ); ?>.
		Reference #<?php echo (int) $id; ?>.
	</div>
</div>
</body>
</html>
	<?php
	exit;
}
add_action( 'admin_post_pupbf_print', 'pupbf_print_view' );
