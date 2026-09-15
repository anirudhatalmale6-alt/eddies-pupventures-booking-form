<?php
/**
 * A very small PDF writer — just enough for one signed booking agreement.
 *
 * No external library: the agreement is laid out in the base-14 Helvetica
 * fonts every PDF reader already has, and images are flattened to raw RGB
 * with GD before embedding, which sidesteps PNG predictors and alpha
 * channels entirely.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PUPBF_PDF {

	const PAGE_W = 595.28; // A4 portrait, in points
	const PAGE_H = 841.89;

	private $margin      = 48.0;
	private $bottom_stop = 64.0;

	private $objects = array();   // object number => raw body
	private $pages   = array();   // page content streams
	private $images  = array();   // name => array( w, h, data )
	private $content = '';
	private $y;

	/** Colours, straight from the site palette. */
	private $coral = array( 1.0, 0.42, 0.29 );
	private $ink   = array( 0.17, 0.15, 0.14 );
	private $grey  = array( 0.43, 0.40, 0.38 );

	private static $widths = array(
		'helvetica' => array(0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,278,278,355,556,556,889,667,191,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,278,278,584,584,584,556,1015,667,667,722,722,667,611,778,722,278,500,667,556,833,722,778,667,778,722,667,611,722,667,944,667,667,611,278,278,278,469,556,333,556,556,500,556,556,278,556,556,222,222,500,222,833,556,556,556,556,333,500,278,556,500,722,500,500,500,334,260,334,584,350,556,350,222,556,333,1000,556,556,333,1000,667,333,1000,350,611,350,350,222,222,333,333,350,556,1000,333,1000,500,333,944,350,500,667,278,333,556,556,556,556,260,556,333,737,370,556,584,333,737,333,400,584,333,333,333,556,537,278,333,333,365,556,834,834,834,611,667,667,667,667,667,667,1000,722,667,667,667,667,278,278,278,278,722,722,778,778,778,778,778,584,778,722,722,722,722,667,667,611,556,556,556,556,556,556,889,500,556,556,556,556,278,278,278,278,556,556,556,556,556,556,556,584,611,556,556,556,556,500,556,500),
		'helvetica_bold' => array(0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,278,333,474,556,556,889,722,238,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,333,333,584,584,584,611,975,722,722,722,722,667,611,778,722,278,556,722,611,833,722,778,667,778,722,667,611,722,667,944,667,667,611,333,278,333,584,556,333,556,611,556,611,556,333,611,611,278,278,556,278,889,611,611,611,611,389,556,333,611,556,778,556,556,500,389,280,389,584,350,556,350,278,556,500,1000,556,556,333,1000,667,333,1000,350,611,350,350,278,278,500,500,350,556,1000,333,1000,556,333,944,350,500,667,278,333,556,556,556,556,280,556,333,737,370,556,584,333,737,333,400,584,333,333,333,611,556,278,333,333,365,556,834,834,834,611,722,722,722,722,722,722,1000,722,667,667,667,667,278,278,278,278,722,722,778,778,778,778,778,584,778,722,722,722,722,667,667,611,556,556,556,556,556,556,889,556,556,556,556,556,278,278,278,278,611,611,611,611,611,611,611,584,611,611,611,611,611,556,611,556),
		'helvetica_oblique' => array(0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,278,278,355,556,556,889,667,191,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,278,278,584,584,584,556,1015,667,667,722,722,667,611,778,722,278,500,667,556,833,722,778,667,778,722,667,611,722,667,944,667,667,611,278,278,278,469,556,333,556,556,500,556,556,278,556,556,222,222,500,222,833,556,556,556,556,333,500,278,556,500,722,500,500,500,334,260,334,584,350,556,350,222,556,333,1000,556,556,333,1000,667,333,1000,350,611,350,350,222,222,333,333,350,556,1000,333,1000,500,333,944,350,500,667,278,333,556,556,556,556,260,556,333,737,370,556,584,333,737,333,400,584,333,333,333,556,537,278,333,333,365,556,834,834,834,611,667,667,667,667,667,667,1000,722,667,667,667,667,278,278,278,278,722,722,778,778,778,778,778,584,778,722,722,722,722,667,667,611,556,556,556,556,556,556,889,500,556,556,556,556,278,278,278,278,556,556,556,556,556,556,556,584,611,556,556,556,556,500,556,500),
	);

	public function __construct() {
		$this->new_page();
	}

	/* ------------------------------------------------------------------ *
	 * Layout
	 * ------------------------------------------------------------------ */

	public function new_page() {
		if ( '' !== $this->content ) {
			$this->pages[] = $this->content;
		}
		$this->content = '';
		$this->y       = self::PAGE_H - $this->margin;
	}

	private function need( $height ) {
		if ( $this->y - $height < $this->bottom_stop ) {
			$this->new_page();
			return true;
		}
		return false;
	}

	public function get_y() {
		return $this->y;
	}

	public function set_y( $y ) {
		$this->y = $y;
	}

	public function at( $text, $x, $y, $font = 'helvetica', $size = 9.5, $rgb = null ) {
		$this->draw( self::win( $text ), $x, $y, $font, $size, $rgb ? $rgb : $this->ink );
	}

	public function space( $h ) {
		$this->need( $h );
		$this->y -= $h;
	}

	private function content_width() {
		return self::PAGE_W - ( 2 * $this->margin );
	}

	/* ------------------------------------------------------------------ *
	 * Text
	 * ------------------------------------------------------------------ */

	/** UTF-8 in, WinAnsi out — so £ and — survive. */
	private static function win( $text ) {
		$text = (string) $text;
		if ( function_exists( 'iconv' ) ) {
			$converted = @iconv( 'UTF-8', 'CP1252//TRANSLIT', $text );
			if ( false !== $converted ) {
				$text = $converted;
			}
		} else {
			$text = utf8_decode( $text );
		}
		return $text;
	}

	private static function esc( $winansi ) {
		return strtr( $winansi, array( '\\' => '\\\\', '(' => '\\(', ')' => '\\)', "\r" => '' ) );
	}

	private static function text_width( $winansi, $font, $size ) {
		// The width tables are keyed helvetica / helvetica_bold — underscores,
		// not hyphens. Getting this wrong silently measures bold text as
		// regular, which is narrower, and the column overflows.
		$key   = str_replace( '-', '_', $font );
		$table = isset( self::$widths[ $key ] ) ? self::$widths[ $key ] : self::$widths['helvetica'];
		$total = 0;
		$len   = strlen( $winansi );
		for ( $i = 0; $i < $len; $i++ ) {
			$w = $table[ ord( $winansi[ $i ] ) ];
			$total += $w ? $w : 500;
		}
		return $total * $size / 1000.0;
	}

	/** Greedy word wrap. Returns an array of WinAnsi lines. */
	private static function wrap( $winansi, $font, $size, $max_width ) {
		$lines = array();
		foreach ( preg_split( "/\n/", $winansi ) as $paragraph ) {
			$words = preg_split( '/ +/', $paragraph );
			$line  = '';
			foreach ( $words as $word ) {
				$try = ( '' === $line ) ? $word : $line . ' ' . $word;
				if ( self::text_width( $try, $font, $size ) <= $max_width || '' === $line ) {
					$line = $try;
				} else {
					$lines[] = $line;
					$line    = $word;
				}
			}
			$lines[] = $line;
		}
		return $lines;
	}

	private function set_fill( $rgb ) {
		$this->content .= sprintf( "%.3F %.3F %.3F rg\n", $rgb[0], $rgb[1], $rgb[2] );
	}

	/**
	 * Draw one already-wrapped line at an absolute x, without moving $y.
	 */
	private function draw( $winansi, $x, $y, $font, $size, $rgb ) {
		$this->set_fill( $rgb );
		$this->content .= sprintf(
			"BT /%s %.2F Tf 1 0 0 1 %.2F %.2F Tm (%s) Tj ET\n",
			'helvetica-bold' === $font ? 'F2' : 'F1',
			$size,
			$x,
			$y,
			self::esc( $winansi )
		);
	}

	/**
	 * Flowing text. Returns the height consumed.
	 */
	public function text( $text, $font = 'helvetica', $size = 9.5, $rgb = null, $indent = 0.0, $leading = null ) {
		$rgb     = $rgb ? $rgb : $this->ink;
		$leading = $leading ? $leading : $size * 1.42;
		$lines   = self::wrap( self::win( $text ), $font, $size, $this->content_width() - $indent );
		foreach ( $lines as $line ) {
			$this->need( $leading );
			$this->y -= $leading;
			$this->draw( $line, $this->margin + $indent, $this->y, $font, $size, $rgb );
		}
	}

	public function section( $title ) {
		$this->need( 34 );
		$this->space( 13 );
		$this->text( strtoupper( $title ), 'helvetica-bold', 8.6, $this->coral, 0, 11 );
		$this->space( 3 );
		$this->rule( $this->coral, 0.9 );
		$this->space( 5 );
	}

	public function rule( $rgb = null, $weight = 0.5 ) {
		$rgb = $rgb ? $rgb : array( 0.90, 0.88, 0.86 );
		$this->need( 3 );
		$this->content .= sprintf(
			"%.3F %.3F %.3F RG %.2F w %.2F %.2F m %.2F %.2F l S\n",
			$rgb[0], $rgb[1], $rgb[2], $weight,
			$this->margin, $this->y,
			self::PAGE_W - $this->margin, $this->y
		);
	}

	/**
	 * A label/value row. The label column is fixed; the value wraps beside it.
	 */
	public function row( $label, $value ) {
		$size      = 9.5;
		$leading   = 13.0;
		$label_w   = 168.0;
		$value_x   = $this->margin + $label_w + 16;
		$value_w   = self::PAGE_W - $this->margin - $value_x;

		$label_lines = self::wrap( self::win( $label ), 'helvetica-bold', $size, $label_w );
		$value_lines = self::wrap( self::win( '' === trim( (string) $value ) ? "\xE2\x80\x94" : $value ), 'helvetica', $size, $value_w );
		$rows        = max( count( $label_lines ), count( $value_lines ) );

		$block = $rows * $leading + 5;
		if ( $this->need( $block ) ) {
			// carried to a fresh page
		}

		$top = $this->y;
		foreach ( $label_lines as $i => $line ) {
			$this->draw( $line, $this->margin, $top - ( ( $i + 1 ) * $leading ), 'helvetica-bold', $size, $this->grey );
		}
		foreach ( $value_lines as $i => $line ) {
			$this->draw( $line, $value_x, $top - ( ( $i + 1 ) * $leading ), 'helvetica', $size, $this->ink );
		}
		$this->y = $top - ( $rows * $leading ) - 5;
		$this->rule();
	}

	public function bullet( $text ) {
		$size    = 8.6;
		$leading = 11.6;
		$lines   = self::wrap( self::win( $text ), 'helvetica', $size, $this->content_width() - 14 );
		foreach ( $lines as $i => $line ) {
			$this->need( $leading );
			$this->y -= $leading;
			if ( 0 === $i ) {
				$this->draw( "\xB7", $this->margin + 3, $this->y, 'helvetica-bold', $size, $this->coral );
			}
			$this->draw( $line, $this->margin + 14, $this->y, 'helvetica', $size, $this->grey );
		}
	}

	/* ------------------------------------------------------------------ *
	 * Images — flattened to raw RGB by GD, so no PNG decoding here.
	 * ------------------------------------------------------------------ */

	/**
	 * @return bool False when GD is unavailable or the file can't be read;
	 *              the caller carries on without the picture.
	 */
	public function image( $source, $max_w, $max_h, $x = null, $align_top = true ) {
		if ( ! function_exists( 'imagecreatefromstring' ) ) {
			return false;
		}

		$raw = $this->load_bytes( $source );
		if ( ! $raw ) {
			return false;
		}

		$im = @imagecreatefromstring( $raw );
		if ( ! $im ) {
			return false;
		}

		$w = imagesx( $im );
		$h = imagesy( $im );
		if ( ! $w || ! $h ) {
			imagedestroy( $im );
			return false;
		}

		// Flatten onto white so transparency never becomes a black box.
		$flat = imagecreatetruecolor( $w, $h );
		imagefilledrectangle( $flat, 0, 0, $w - 1, $h - 1, imagecolorallocate( $flat, 255, 255, 255 ) );
		imagecopy( $flat, $im, 0, 0, 0, 0, $w, $h );
		imagedestroy( $im );

		$rgb = '';
		for ( $yy = 0; $yy < $h; $yy++ ) {
			for ( $xx = 0; $xx < $w; $xx++ ) {
				$c    = imagecolorat( $flat, $xx, $yy );
				$rgb .= chr( ( $c >> 16 ) & 0xFF ) . chr( ( $c >> 8 ) & 0xFF ) . chr( $c & 0xFF );
			}
		}
		imagedestroy( $flat );

		$scale = min( $max_w / $w, $max_h / $h, 1.0 );
		$draw_w = $w * $scale;
		$draw_h = $h * $scale;

		$this->need( $draw_h + 6 );
		$this->y -= $draw_h;

		$name = 'Im' . ( count( $this->images ) + 1 );
		$this->images[ $name ] = array(
			'w'    => $w,
			'h'    => $h,
			'data' => function_exists( 'gzcompress' ) ? gzcompress( $rgb, 6 ) : $rgb,
			'flate' => function_exists( 'gzcompress' ),
		);

		$px = ( null === $x ) ? $this->margin : $x;
		$this->content .= sprintf(
			"q %.2F 0 0 %.2F %.2F %.2F cm /%s Do Q\n",
			$draw_w, $draw_h, $px, $this->y, $name
		);

		return true;
	}

	/** Accepts raw bytes, a local path, or a data: URL. */
	private function load_bytes( $source ) {
		if ( 0 === strpos( $source, 'data:' ) ) {
			$comma = strpos( $source, ',' );
			if ( false === $comma ) {
				return '';
			}
			return base64_decode( substr( $source, $comma + 1 ), true );
		}
		if ( strlen( $source ) < 512 && @is_readable( $source ) ) {
			return (string) @file_get_contents( $source );
		}
		return $source;
	}

	/* ------------------------------------------------------------------ *
	 * Assembly
	 * ------------------------------------------------------------------ */

	public function output() {
		$this->pages[] = $this->content;
		$this->content = '';

		$n_pages   = count( $this->pages );
		$obj       = array();
		$next      = 1;

		$catalog_n = $next++;
		$pages_n   = $next++;
		$font1_n   = $next++;
		$font2_n   = $next++;

		$image_ns = array();
		foreach ( $this->images as $name => $img ) {
			$image_ns[ $name ] = $next++;
		}

		$page_ns    = array();
		$content_ns = array();
		for ( $i = 0; $i < $n_pages; $i++ ) {
			$page_ns[ $i ]    = $next++;
			$content_ns[ $i ] = $next++;
		}

		$xobjects = '';
		foreach ( $image_ns as $name => $n ) {
			$xobjects .= sprintf( '/%s %d 0 R ', $name, $n );
		}
		$resources = sprintf(
			'<< /Font << /F1 %d 0 R /F2 %d 0 R >> %s >>',
			$font1_n,
			$font2_n,
			$xobjects ? '/XObject << ' . $xobjects . '>>' : ''
		);

		$kids = array();
		foreach ( $page_ns as $n ) {
			$kids[] = $n . ' 0 R';
		}

		$obj[ $catalog_n ] = '<< /Type /Catalog /Pages ' . $pages_n . ' 0 R >>';
		$obj[ $pages_n ]   = sprintf(
			'<< /Type /Pages /Count %d /Kids [ %s ] /MediaBox [0 0 %.2F %.2F] >>',
			$n_pages,
			implode( ' ', $kids ),
			self::PAGE_W,
			self::PAGE_H
		);
		$obj[ $font1_n ] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
		$obj[ $font2_n ] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

		foreach ( $this->images as $name => $img ) {
			$obj[ $image_ns[ $name ] ] = sprintf(
				"<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceRGB /BitsPerComponent 8 %s /Length %d >>\nstream\n%s\nendstream",
				$img['w'],
				$img['h'],
				$img['flate'] ? '/Filter /FlateDecode' : '',
				strlen( $img['data'] ),
				$img['data']
			);
		}

		for ( $i = 0; $i < $n_pages; $i++ ) {
			$stream = $this->pages[ $i ];
			$obj[ $page_ns[ $i ] ] = sprintf(
				'<< /Type /Page /Parent %d 0 R /Resources %s /Contents %d 0 R >>',
				$pages_n,
				$resources,
				$content_ns[ $i ]
			);
			$obj[ $content_ns[ $i ] ] = sprintf(
				"<< /Length %d >>\nstream\n%s\nendstream",
				strlen( $stream ),
				$stream
			);
		}

		ksort( $obj );

		$pdf     = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
		$offsets = array();
		foreach ( $obj as $n => $body ) {
			$offsets[ $n ] = strlen( $pdf );
			$pdf .= $n . " 0 obj\n" . $body . "\nendobj\n";
		}

		$xref_at = strlen( $pdf );
		$count   = count( $obj ) + 1;
		$pdf    .= "xref\n0 " . $count . "\n0000000000 65535 f \n";
		for ( $n = 1; $n < $count; $n++ ) {
			$pdf .= sprintf( "%010d 00000 n \n", isset( $offsets[ $n ] ) ? $offsets[ $n ] : 0 );
		}
		$pdf .= sprintf(
			"trailer\n<< /Size %d /Root %d 0 R >>\nstartxref\n%d\n%%%%EOF\n",
			$count,
			$catalog_n,
			$xref_at
		);

		return $pdf;
	}
}

/* ---------------------------------------------------------------------------
 * The booking agreement, as a PDF.
 * ------------------------------------------------------------------------- */

/**
 * @param int  $post_id           The booking.
 * @param bool $include_sensitive Put home access details on the page.
 * @return string Raw PDF bytes.
 */
function pupbf_build_pdf( $post_id, $include_sensitive = false ) {
	$pdf = new PUPBF_PDF();

	$coral = array( 1.0, 0.42, 0.29 );
	$grey  = array( 0.43, 0.40, 0.38 );

	/* --- masthead ---------------------------------------------------- */
	$logo = get_stylesheet_directory() . '/assets/images/beagle-logo.png';
	$top  = $pdf->get_y();
	$has_logo = false;

	if ( file_exists( $logo ) ) {
		$has_logo = $pdf->image( $logo, 46, 56 );
	}

	$text_x = $has_logo ? 48.0 + 46 + 14 : 48.0;
	$pdf->at( "Eddie's Pupventures", $text_x, $top - 17, 'helvetica-bold', 15 );
	$pdf->at( 'Dog Walking Booking Agreement', $text_x, $top - 32, 'helvetica', 10.5, $grey );
	$pdf->at(
		'Basingstoke, Hampshire  ·  Agreement version ' . get_post_meta( $post_id, '_pupbf_agreement_version', true ),
		$text_x,
		$top - 45,
		'helvetica',
		8.4,
		$grey
	);

	if ( ! $has_logo ) {
		$pdf->set_y( $top - 52 );
	} else {
		$pdf->set_y( min( $pdf->get_y(), $top - 52 ) );
	}

	$pdf->space( 8 );
	$pdf->rule( $coral, 1.6 );

	/* --- the answers -------------------------------------------------- */
	foreach ( pupbf_schema() as $section ) {
		$rows = array();
		foreach ( $section['fields'] as $key => $def ) {
			if ( 'signature' === ( isset( $def['type'] ) ? $def['type'] : '' ) ) {
				continue;
			}
			if ( ! empty( $def['showif'] ) ) {
				$dep = pupbf_get_answer( $post_id, $def['showif'][0] );
				if ( (string) $dep !== (string) $def['showif'][1] ) {
					continue;
				}
			}
			$rows[ $key ] = $def;
		}
		if ( ! $rows ) {
			continue;
		}

		$pdf->section( html_entity_decode( wp_strip_all_tags( $section['title'] ), ENT_QUOTES, 'UTF-8' ) );

		foreach ( $rows as $key => $def ) {
			$label = html_entity_decode( wp_strip_all_tags( $def['label'] ), ENT_QUOTES, 'UTF-8' );
			$value = html_entity_decode( pupbf_format_answer( $post_id, $key, ! $include_sensitive ), ENT_QUOTES, 'UTF-8' );
			$pdf->row( $label, $value );
		}
	}

	/* --- terms --------------------------------------------------------- */
	$pdf->section( 'Terms & conditions agreed' );
	foreach ( pupbf_terms_blocks() as $heading => $lines ) {
		$pdf->space( 5 );
		$pdf->text(
			html_entity_decode( wp_strip_all_tags( $heading ), ENT_QUOTES, 'UTF-8' ),
			'helvetica-bold',
			9,
			null,
			0,
			11
		);
		foreach ( $lines as $line ) {
			$pdf->bullet( html_entity_decode( wp_strip_all_tags( $line ), ENT_QUOTES, 'UTF-8' ) );
		}
	}

	/* --- signature ------------------------------------------------------ */
	$pdf->section( 'Signature' );
	$sig = pupbf_get_answer( $post_id, 'signature' );
	if ( $sig ) {
		$pdf->space( 4 );
		$pdf->image( $sig, 230, 76 );
		$pdf->space( 4 );
	}
	$pdf->text( (string) pupbf_get_answer( $post_id, 'signed_name' ), 'helvetica-bold', 10 );

	$signed = get_post_meta( $post_id, '_pupbf_signed_at', true );
	$pdf->text(
		'Signed ' . ( $signed ? date_i18n( 'j F Y \a\t g:ia', strtotime( $signed ) ) : '—' ),
		'helvetica',
		8.6,
		$grey
	);

	$pdf->space( 10 );
	$pdf->rule();
	$pdf->space( 2 );
	$pdf->text(
		'Signature record. Submitted '
		. ( $signed ? date_i18n( 'j F Y \a\t g:ia', strtotime( $signed ) ) : 'unknown' )
		. ' from IP ' . ( get_post_meta( $post_id, '_pupbf_ip', true ) ? get_post_meta( $post_id, '_pupbf_ip', true ) : 'unknown' )
		. ', against agreement version ' . get_post_meta( $post_id, '_pupbf_agreement_version', true )
		. '. Reference #' . (int) $post_id . '.',
		'helvetica',
		7.8,
		$grey
	);

	if ( ! $include_sensitive ) {
		$raw = get_post_meta( $post_id, pupbf_meta_key( 'access_info' ), true );
		if ( '' !== $raw ) {
			$pdf->text(
				'Home access details were given on this form. They are deliberately left off this copy and are held on the website only.',
				'helvetica',
				7.8,
				$grey
			);
		}
	}

	return $pdf->output();
}

/** A tidy, unambiguous filename for the attachment. */
function pupbf_pdf_filename( $post_id ) {
	$owner = sanitize_file_name( (string) pupbf_get_answer( $post_id, 'owner_name' ) );
	$signed = get_post_meta( $post_id, '_pupbf_signed_at', true );
	$date   = $signed ? gmdate( 'Y-m-d', strtotime( $signed ) ) : gmdate( 'Y-m-d' );
	$owner  = $owner ? $owner : 'booking';
	return 'Booking-Agreement-' . $owner . '-' . $date . '.pdf';
}

/* ---------------------------------------------------------------------------
 * Download one agreement as a PDF straight from the dashboard.
 * ------------------------------------------------------------------------- */
function pupbf_pdf_download() {
	$id = isset( $_GET['booking'] ) ? (int) $_GET['booking'] : 0;

	if ( ! $id || ! current_user_can( 'manage_options' ) ) {
		wp_die( 'You do not have permission to download this booking form.', 403 );
	}
	check_admin_referer( 'pupbf_pdf_' . $id );

	$post = get_post( $id );
	if ( ! $post || PUPBF_CPT !== $post->post_type ) {
		wp_die( 'Booking form not found.', 404 );
	}

	// From her own dashboard, the full record — access details and all.
	$bytes = pupbf_build_pdf( $id, true );

	nocache_headers();
	header( 'Content-Type: application/pdf' );
	header( 'Content-Disposition: attachment; filename="' . pupbf_pdf_filename( $id ) . '"' );
	header( 'Content-Length: ' . strlen( $bytes ) );
	echo $bytes; // phpcs:ignore WordPress.Security.EscapeOutput
	exit;
}
add_action( 'admin_post_pupbf_pdf', 'pupbf_pdf_download' );
