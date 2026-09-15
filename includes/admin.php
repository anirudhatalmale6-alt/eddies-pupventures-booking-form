<?php
/**
 * The dashboard side: a list of signed agreements, a full record for each,
 * a CSV export and a small settings page.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function pupbf_admin_assets( $hook ) {
	$screen = get_current_screen();
	if ( ! $screen || PUPBF_CPT !== $screen->post_type ) {
		return;
	}
	wp_enqueue_style( 'pupbf-admin', PUPBF_URL . 'assets/css/admin.css', array(), PUPBF_VERSION );
}
add_action( 'admin_enqueue_scripts', 'pupbf_admin_assets' );

/* ---------------------------------------------------------------------------
 * List table
 * ------------------------------------------------------------------------- */
function pupbf_columns( $columns ) {
	return array(
		'cb'         => isset( $columns['cb'] ) ? $columns['cb'] : '',
		'title'      => 'Owner &amp; dog',
		'pup_phone'  => 'Phone',
		'pup_walk'   => 'Walk',
		'pup_days'   => 'Days',
		'pup_status' => 'Status',
		'pup_signed' => 'Signed',
	);
}
add_filter( 'manage_' . PUPBF_CPT . '_posts_columns', 'pupbf_columns' );

function pupbf_column_content( $column, $post_id ) {
	switch ( $column ) {
		case 'pup_phone':
			$phone = pupbf_get_answer( $post_id, 'phone' );
			if ( $phone ) {
				echo '<a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ) . '">' . esc_html( $phone ) . '</a>';
			} else {
				echo '—';
			}
			break;

		case 'pup_walk':
			$walk = pupbf_format_answer( $post_id, 'walk_type' );
			$n    = (int) pupbf_get_answer( $post_id, 'dog_count' );
			echo esc_html( $walk );
			if ( $n > 1 ) {
				echo '<br><span class="pupbf-dim">' . esc_html( $n . ' dogs' ) . '</span>';
			}
			break;

		case 'pup_days':
			$type = pupbf_get_answer( $post_id, 'schedule_type' );
			if ( 'adhoc' === $type ) {
				echo '<span class="pupbf-pill pupbf-pill-adhoc">Ad hoc</span>';
			} else {
				echo esc_html( pupbf_format_answer( $post_id, 'days' ) );
			}
			break;

		case 'pup_status':
			$status = get_post_meta( $post_id, '_pupbf_status', true );
			$label  = ( 'done' === $status ) ? 'Dealt with' : 'New';
			$class  = ( 'done' === $status ) ? 'pupbf-pill-done' : 'pupbf-pill-new';
			echo '<span class="pupbf-pill ' . esc_attr( $class ) . '">' . esc_html( $label ) . '</span>';
			break;

		case 'pup_signed':
			$signed = get_post_meta( $post_id, '_pupbf_signed_at', true );
			echo $signed ? esc_html( date_i18n( 'j M Y, g:ia', strtotime( $signed ) ) ) : '—';
			break;
	}
}
add_action( 'manage_' . PUPBF_CPT . '_posts_custom_column', 'pupbf_column_content', 10, 2 );

function pupbf_sortable_columns( $cols ) {
	$cols['pup_signed'] = 'date';
	return $cols;
}
add_filter( 'manage_edit-' . PUPBF_CPT . '_sortable_columns', 'pupbf_sortable_columns' );

/**
 * "Add New" makes no sense here — a booking form only exists because a client
 * filled one in. Remove the menu item, the button above the list, and the
 * screen itself for anyone who types the URL.
 */
function pupbf_hide_add_new() {
	global $submenu;
	if ( isset( $submenu[ 'edit.php?post_type=' . PUPBF_CPT ] ) ) {
		foreach ( $submenu[ 'edit.php?post_type=' . PUPBF_CPT ] as $i => $item ) {
			if ( isset( $item[2] ) && 'post-new.php?post_type=' . PUPBF_CPT === $item[2] ) {
				unset( $submenu[ 'edit.php?post_type=' . PUPBF_CPT ][ $i ] );
			}
		}
	}
}
add_action( 'admin_menu', 'pupbf_hide_add_new', 999 );

function pupbf_remove_add_new_button() {
	$screen = get_current_screen();
	if ( $screen && PUPBF_CPT === $screen->post_type ) {
		echo '<style>.page-title-action{display:none !important;}</style>';
	}
}
add_action( 'admin_head', 'pupbf_remove_add_new_button' );

function pupbf_block_add_new_screen() {
	global $pagenow;
	if ( 'post-new.php' === $pagenow && isset( $_GET['post_type'] ) && PUPBF_CPT === $_GET['post_type'] ) {
		wp_safe_redirect( admin_url( 'edit.php?post_type=' . PUPBF_CPT ) );
		exit;
	}
}
add_action( 'admin_init', 'pupbf_block_add_new_screen' );

/* ---------------------------------------------------------------------------
 * The record itself
 * ------------------------------------------------------------------------- */
function pupbf_meta_boxes() {
	remove_meta_box( 'submitdiv', PUPBF_CPT, 'side' );
	remove_meta_box( 'slugdiv', PUPBF_CPT, 'normal' );

	add_meta_box( 'pupbf_record', 'Signed booking agreement', 'pupbf_render_record', PUPBF_CPT, 'normal', 'high' );
	add_meta_box( 'pupbf_actions', 'Actions', 'pupbf_render_actions', PUPBF_CPT, 'side', 'high' );
	add_meta_box( 'pupbf_audit', 'Signature record', 'pupbf_render_audit', PUPBF_CPT, 'side', 'default' );
}
add_action( 'add_meta_boxes', 'pupbf_meta_boxes' );

function pupbf_render_record( $post ) {
	echo '<div class="pupbf-record">';

	foreach ( pupbf_schema() as $skey => $section ) {
		echo '<h3 class="pupbf-record-h">' . wp_kses( $section['title'], array( 'amp' => array() ) ) . '</h3>'; // phpcs:ignore
		echo '<table class="pupbf-record-table"><tbody>';

		foreach ( $section['fields'] as $key => $def ) {
			$type = isset( $def['type'] ) ? $def['type'] : 'text';

			if ( 'signature' === $type ) {
				continue;
			}

			// Hide a conditional answer that never applied.
			if ( ! empty( $def['showif'] ) ) {
				$dep = pupbf_get_answer( $post->ID, $def['showif'][0] );
				if ( (string) $dep !== (string) $def['showif'][1] ) {
					continue;
				}
			}

			$label = wp_kses( $def['label'], array( 'amp' => array(), 'strong' => array(), 'em' => array() ) );
			$value = pupbf_format_answer( $post->ID, $key );

			$row_class = '';
			if ( ! empty( $def['sensitive'] ) ) {
				$row_class = 'pupbf-sensitive';
			}
			if ( 'checkbox' === $type && 'No' === $value && ! empty( $def['required'] ) ) {
				$row_class .= ' pupbf-warn';
			}
			if ( 'photo_optout' === $key && 'Yes' === $value ) {
				$row_class .= ' pupbf-warn';
			}

			echo '<tr class="' . esc_attr( trim( $row_class ) ) . '">';
			echo '<th scope="row">' . $label . '</th>'; // phpcs:ignore
			echo '<td>' . nl2br( esc_html( $value ) );
			if ( ! empty( $def['sensitive'] ) ) {
				$stored = get_post_meta( $post->ID, pupbf_meta_key( $key ), true );
				if ( pupbf_is_encrypted( $stored ) ) {
					echo ' <span class="pupbf-lock" title="Stored encrypted in the database">🔒 encrypted</span>';
				}
			}
			echo '</td></tr>';
		}

		echo '</tbody></table>';
	}

	$sig = pupbf_get_answer( $post->ID, 'signature' );
	echo '<h3 class="pupbf-record-h">Signature</h3>';
	if ( $sig ) {
		printf(
			'<div class="pupbf-sig-shown"><img src="%s" alt="Signature of %s" /><p class="pupbf-sig-name">%s</p></div>',
			esc_attr( $sig ),
			esc_attr( pupbf_get_answer( $post->ID, 'signed_name' ) ),
			esc_html( pupbf_get_answer( $post->ID, 'signed_name' ) )
		);
	} else {
		echo '<p>No signature stored.</p>';
	}

	echo '</div>';
}

function pupbf_render_actions( $post ) {
	$print = wp_nonce_url(
		admin_url( 'admin-post.php?action=pupbf_print&booking=' . $post->ID ),
		'pupbf_print_' . $post->ID
	);
	$status = get_post_meta( $post->ID, '_pupbf_status', true );

	$download = wp_nonce_url(
		admin_url( 'admin-post.php?action=pupbf_pdf&booking=' . $post->ID ),
		'pupbf_pdf_' . $post->ID
	);

	echo '<p><a class="button button-primary button-large pupbf-full" href="' . esc_url( $download ) . '">Download PDF</a></p>';
	echo '<p><a class="button pupbf-full" href="' . esc_url( $print ) . '" target="_blank" rel="noopener">Print / view on screen</a></p>';

	echo '<p>';
	$toggle = wp_nonce_url(
		admin_url( 'admin-post.php?action=pupbf_toggle&booking=' . $post->ID ),
		'pupbf_toggle_' . $post->ID
	);
	echo '<a class="button pupbf-full" href="' . esc_url( $toggle ) . '">';
	echo ( 'done' === $status ) ? 'Mark as new again' : 'Mark as dealt with';
	echo '</a></p>';

	$email = pupbf_get_answer( $post->ID, 'email' );
	$phone = pupbf_get_answer( $post->ID, 'phone' );
	if ( $email ) {
		echo '<p><a class="button pupbf-full" href="mailto:' . esc_attr( $email ) . '">Email ' . esc_html( pupbf_get_answer( $post->ID, 'owner_name' ) ) . '</a></p>';
	}
	if ( $phone ) {
		echo '<p><a class="button pupbf-full" href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ) . '">Call ' . esc_html( $phone ) . '</a></p>';
	}

	echo '<hr />';
	echo '<p class="pupbf-dim">Deleting this record deletes the signed agreement. Consider printing a copy first.</p>';
	echo '<p><a class="submitdelete" href="' . esc_url( get_delete_post_link( $post->ID ) ) . '">Move to bin</a></p>';
}

function pupbf_render_audit( $post ) {
	$rows = array(
		'Signed'            => get_post_meta( $post->ID, '_pupbf_signed_at', true ),
		'Agreement version' => get_post_meta( $post->ID, '_pupbf_agreement_version', true ),
		'IP address'        => get_post_meta( $post->ID, '_pupbf_ip', true ),
		'Your copy emailed' => '1' === get_post_meta( $post->ID, '_pupbf_admin_email_sent', true ) ? 'Yes' : 'No',
		'Client copy emailed' => '1' === get_post_meta( $post->ID, '_pupbf_client_email_sent', true ) ? 'Yes' : 'No',
	);
	echo '<table class="pupbf-audit"><tbody>';
	foreach ( $rows as $label => $value ) {
		echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . esc_html( $value ? $value : '—' ) . '</td></tr>';
	}
	echo '</tbody></table>';
	$ua = get_post_meta( $post->ID, '_pupbf_user_agent', true );
	if ( $ua ) {
		echo '<p class="pupbf-dim pupbf-ua">' . esc_html( $ua ) . '</p>';
	}
	echo '<p class="pupbf-dim">Kept so the signature can be evidenced later if it is ever questioned.</p>';
}

function pupbf_toggle_status() {
	$id = isset( $_GET['booking'] ) ? (int) $_GET['booking'] : 0;
	if ( ! $id || ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'pupbf_toggle_' . $id ) ) {
		wp_die( 'Sorry, that link has expired.', 403 );
	}
	$now = get_post_meta( $id, '_pupbf_status', true );
	update_post_meta( $id, '_pupbf_status', 'done' === $now ? 'new' : 'done' );
	wp_safe_redirect( admin_url( 'post.php?post=' . $id . '&action=edit' ) );
	exit;
}
add_action( 'admin_post_pupbf_toggle', 'pupbf_toggle_status' );

/* ---------------------------------------------------------------------------
 * Settings
 * ------------------------------------------------------------------------- */
function pupbf_settings_menu() {
	add_submenu_page(
		'edit.php?post_type=' . PUPBF_CPT,
		'Booking form settings',
		'Settings',
		'manage_options',
		'pupbf-settings',
		'pupbf_settings_page'
	);
	add_submenu_page(
		'edit.php?post_type=' . PUPBF_CPT,
		'Export bookings',
		'Export',
		'manage_options',
		'pupbf-export',
		'pupbf_export_page'
	);
}
add_action( 'admin_menu', 'pupbf_settings_menu' );

function pupbf_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Nope.', 403 );
	}

	$saved    = false;
	$new_code = false;
	if ( isset( $_POST['pupbf_settings_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pupbf_settings_nonce'] ) ), 'pupbf_settings' ) ) {
		$email = isset( $_POST['pupbf_notify_email'] ) ? sanitize_email( wp_unslash( $_POST['pupbf_notify_email'] ) ) : '';
		if ( $email && is_email( $email ) ) {
			update_option( 'pupbf_notify_email', $email );
		}
		update_option( 'pupbf_send_client_copy', empty( $_POST['pupbf_send_client_copy'] ) ? 'no' : 'yes' );
		update_option( 'pupbf_attach_pdf', empty( $_POST['pupbf_attach_pdf'] ) ? 'no' : 'yes' );
		update_option( 'pupbf_pdf_sensitive', empty( $_POST['pupbf_pdf_sensitive'] ) ? 'no' : 'yes' );
		update_option( 'pupbf_require_code', empty( $_POST['pupbf_require_code'] ) ? 'no' : 'yes' );

		if ( ! empty( $_POST['pupbf_new_code'] ) ) {
			pupbf_new_link_code();
			$new_code = true;
		}

		$prices = array();
		foreach ( array_keys( pupbf_prices() ) as $k ) {
			if ( isset( $_POST[ 'price_' . $k ] ) && '' !== $_POST[ 'price_' . $k ] ) {
				$prices[ $k ] = max( 0, (float) wp_unslash( $_POST[ 'price_' . $k ] ) );
			}
		}
		update_option( 'pupbf_prices', $prices );
		$saved = true;
	}

	$prices = pupbf_prices();
	$labels = array(
		'weekday_30'   => 'Weekday — 30 minutes',
		'weekday_60'   => 'Weekday — 1 hour',
		'weekend_30'   => 'Weekend / bank holiday — 30 minutes',
		'weekend_60'   => 'Weekend / bank holiday — 1 hour',
		'extra_dog_30' => 'Additional dog, same household — 30 minutes',
		'extra_dog_60' => 'Additional dog, same household — 1 hour',
	);
	$page_id = (int) get_option( 'pupbf_page_id' );
	?>
	<div class="wrap pupbf-settings">
		<h1>Booking form settings</h1>
		<?php if ( $saved ) : ?>
			<div class="notice notice-success is-dismissible"><p>Saved.</p></div>
		<?php endif; ?>

		<?php if ( $new_code ) : ?>
			<div class="notice notice-warning"><p><strong>New link code generated.</strong> Any link you sent out before now has stopped working — send the new one below.</p></div>
		<?php endif; ?>

		<div class="pupbf-linkbox">
			<h2 style="margin-top:0;">The link to send people</h2>
			<p><input type="text" class="large-text code" readonly onclick="this.select();" value="<?php echo esc_attr( pupbf_private_link() ); ?>" /></p>
			<p class="description">
				Click to select, then copy. This page is never listed in your menu, never shown in search results, and is hidden from Google.
			</p>
		</div>

		<form method="post">
			<?php wp_nonce_field( 'pupbf_settings', 'pupbf_settings_nonce' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="pupbf_notify_email">Send new bookings to</label></th>
					<td>
						<input type="email" class="regular-text" id="pupbf_notify_email" name="pupbf_notify_email" value="<?php echo esc_attr( pupbf_notify_email() ); ?>" />
						<p class="description">Where the "you have a new booking" email goes.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Client's copy</th>
					<td>
						<label>
							<input type="checkbox" name="pupbf_send_client_copy" value="1" <?php checked( 'no' !== get_option( 'pupbf_send_client_copy', 'yes' ) ); ?> />
							Email each client a copy of what they signed
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row">PDF by email</th>
					<td>
						<label>
							<input type="checkbox" name="pupbf_attach_pdf" value="1" <?php checked( 'no' !== get_option( 'pupbf_attach_pdf', 'yes' ) ); ?> />
							Attach the completed agreement as a PDF
						</label>
						<p class="description">Goes to you, and to the client with their copy. Save it straight from your inbox.</p>

						<p style="margin-top:.8rem;">
							<label>
								<input type="checkbox" name="pupbf_pdf_sensitive" value="1" <?php checked( 'yes' === get_option( 'pupbf_pdf_sensitive', 'no' ) ); ?> />
								Include home access details and key safe codes on <em>your</em> PDF
							</label>
						</p>
						<p class="description">
							Off by default, and deliberately so — an email attachment gets forwarded, backed up and
							synced to places you don't control. The client's copy never includes them either way.
							Your dashboard always shows the full record.
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Private link</th>
					<td>
						<label>
							<input type="checkbox" name="pupbf_require_code" value="1" <?php checked( pupbf_link_code_enabled() ); ?> />
							Require a code in the link
						</label>
						<p class="description">
							With this on, anyone who reaches the page without your link sees a polite note asking
							them to get in touch first — so nobody fills it in cold and assumes they're booked.
						</p>
						<p style="margin-top:.8rem;">
							<label>
								<input type="checkbox" name="pupbf_new_code" value="1" />
								Generate a brand new code when I save
							</label>
						</p>
						<p class="description">Only tick this if a link has gone somewhere it shouldn't — it stops every link you've already sent.</p>
					</td>
				</tr>
			</table>

			<h2>Price list</h2>
			<p class="description">These appear in the terms on the form, and drive the estimate a client sees before they sign.</p>
			<table class="form-table" role="presentation">
				<?php foreach ( $labels as $k => $label ) : ?>
					<tr>
						<th scope="row"><label for="price_<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $label ); ?></label></th>
						<td>£ <input type="number" step="0.01" min="0" id="price_<?php echo esc_attr( $k ); ?>" name="price_<?php echo esc_attr( $k ); ?>" value="<?php echo esc_attr( $prices[ $k ] ); ?>" /></td>
					</tr>
				<?php endforeach; ?>
			</table>

			<?php submit_button( 'Save settings' ); ?>
		</form>

		<h2>Data &amp; privacy</h2>
		<p>
			<?php if ( pupbf_can_encrypt() ) : ?>
				Home access details (including key safe codes) are encrypted before they are stored, and are never included in any email. 🔒
			<?php else : ?>
				<strong>Note:</strong> this server has no OpenSSL support, so access details are stored as ordinary text. They are still kept out of every email.
			<?php endif; ?>
		</p>
		<p>Deleting a booking from the bin permanently removes that client's data, which is what UK GDPR expects when someone asks to be forgotten.</p>
	</div>
	<?php
}

/* ---------------------------------------------------------------------------
 * CSV export
 * ------------------------------------------------------------------------- */
function pupbf_export_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Nope.', 403 );
	}
	$count = wp_count_posts( PUPBF_CPT );
	?>
	<div class="wrap">
		<h1>Export bookings</h1>
		<p>Download every booking form as a spreadsheet (CSV) — opens straight in Excel, Numbers or Google Sheets.</p>
		<p><strong><?php echo (int) ( isset( $count->publish ) ? $count->publish : 0 ); ?></strong> booking form(s) stored.</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="pupbf_export" />
			<?php wp_nonce_field( 'pupbf_export', 'pupbf_export_nonce' ); ?>
			<p>
				<label>
					<input type="checkbox" name="include_sensitive" value="1" />
					Include home access details and key safe codes
				</label>
				<br />
				<span class="description">Left out by default — a spreadsheet of key safe codes is an easy thing to email to the wrong person.</span>
			</p>
			<?php submit_button( 'Download CSV' ); ?>
		</form>
	</div>
	<?php
}

function pupbf_do_export() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Nope.', 403 );
	}
	check_admin_referer( 'pupbf_export', 'pupbf_export_nonce' );

	$include_sensitive = ! empty( $_POST['include_sensitive'] );

	$posts = get_posts(
		array(
			'post_type'      => PUPBF_CPT,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);

	$fields  = pupbf_fields();
	$headers = array( 'Signed' );
	foreach ( $fields as $key => $def ) {
		if ( 'signature' === ( isset( $def['type'] ) ? $def['type'] : '' ) ) {
			continue;
		}
		$headers[] = html_entity_decode( wp_strip_all_tags( $def['label'] ), ENT_QUOTES, 'UTF-8' );
	}
	$headers[] = 'Agreement version';

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="pupventures-bookings-' . gmdate( 'Y-m-d' ) . '.csv"' );

	$out = fopen( 'php://output', 'w' );
	fwrite( $out, "\xEF\xBB\xBF" ); // BOM, so Excel doesn't mangle the £ signs.
	fputcsv( $out, $headers );

	foreach ( $posts as $p ) {
		$row = array( get_post_meta( $p->ID, '_pupbf_signed_at', true ) );
		foreach ( $fields as $key => $def ) {
			if ( 'signature' === ( isset( $def['type'] ) ? $def['type'] : '' ) ) {
				continue;
			}
			$row[] = html_entity_decode( pupbf_format_answer( $p->ID, $key, ! $include_sensitive ), ENT_QUOTES, 'UTF-8' );
		}
		$row[] = get_post_meta( $p->ID, '_pupbf_agreement_version', true );
		fputcsv( $out, $row );
	}
	fclose( $out );
	exit;
}
add_action( 'admin_post_pupbf_export', 'pupbf_do_export' );

/* ---------------------------------------------------------------------------
 * A nudge on the dashboard when something new comes in.
 * ------------------------------------------------------------------------- */
function pupbf_new_count() {
	$q = new WP_Query(
		array(
			'post_type'      => PUPBF_CPT,
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'     => '_pupbf_status',
					'value'   => 'new',
					'compare' => '=',
				),
			),
		)
	);
	return (int) $q->found_posts;
}

function pupbf_menu_bubble() {
	global $menu;
	$new = pupbf_new_count();
	if ( ! $new ) {
		return;
	}
	foreach ( $menu as $i => $item ) {
		if ( isset( $item[2] ) && 'edit.php?post_type=' . PUPBF_CPT === $item[2] ) {
			$menu[ $i ][0] .= ' <span class="awaiting-mod"><span class="pending-count">' . (int) $new . '</span></span>';
			break;
		}
	}
}
add_action( 'admin_menu', 'pupbf_menu_bubble', 1000 );
