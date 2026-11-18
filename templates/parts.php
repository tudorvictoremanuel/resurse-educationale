<?php
/**
 * Părți comune ale paginilor platformei (antet HTML, logo, subsol).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function p45cd_asset( $cale ) {
	$fisier = P45CD_DIR . 'assets/' . $cale;
	$v      = file_exists( $fisier ) ? P45CD_VERSION . '.' . filemtime( $fisier ) : P45CD_VERSION;
	return esc_url( P45CD_URL . 'assets/' . $cale . '?v=' . $v );
}

function p45cd_inceput( $titlu, $clasa_body = '', $css = array( 'css/app.css' ) ) {
	$site = get_bloginfo( 'name' );
	?><!DOCTYPE html>
<html lang="ro">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo esc_html( $titlu . ( $site ? ' – ' . $site : '' ) ); ?></title>
<link rel="icon" href="<?php echo p45cd_asset( 'img/logo.png' ); ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;family=Plus+Jakarta+Sans:wght@600;700;800&amp;display=swap" rel="stylesheet">
	<?php foreach ( $css as $c ) : ?>
<link rel="stylesheet" href="<?php echo p45cd_asset( $c ); ?>">
	<?php endforeach; ?>
</head>
<body class="<?php echo esc_attr( $clasa_body ); ?>">
	<?php
}

function p45cd_sfarsit( $js = array() ) {
	foreach ( $js as $j ) {
		echo '<script src="' . p45cd_asset( $j ) . '"></script>' . "\n";
	}
	echo "</body>\n</html>\n";
}

function p45cd_marca( $link = true ) {
	$tag = $link ? 'a href="' . esc_url( p45cd_url( 'biblioteca' ) ) . '"' : 'span';
	$end = $link ? 'a' : 'span';
	echo '<' . $tag . ' class="marca"><img src="' . p45cd_asset( 'img/logo.png' ) . '" alt="" width="36" height="32"><span class="marca-txt"><b>Paralela 45</b><span>Cărți digitale</span></span></' . $end . '>'; // phpcs:ignore
}

function p45cd_icon( $nume, $marime = 20 ) {
	$p = array(
		'tabla'    => '<rect x="2" y="3" width="20" height="13" rx="2"/><path d="M12 16v4M8 21h8"/>',
		'carte'    => '<path d="M2 4h6a4 4 0 0 1 4 4v12a3 3 0 0 0-3-3H2z"/><path d="M22 4h-6a4 4 0 0 0-4 4v12a3 3 0 0 1 3-3h7z"/>',
		'cauta'    => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
		'iesire'   => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
		'semn'     => '<path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>',
		'nota'     => '<path d="M15 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V9z"/><path d="M15 3v6h6"/>',
		'creion'   => '<path d="M17 3a2.8 2.8 0 0 1 4 4L7.5 20.5 2 22l1.5-5.5z"/>',
		'setari'   => '<path d="M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M1 14h6M9 8h6M17 16h6"/>',
		'dreapta'  => '<path d="m9 18 6-6-6-6"/>',
	);
	return '<svg width="' . (int) $marime . '" height="' . (int) $marime . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ( isset( $p[ $nume ] ) ? $p[ $nume ] : '' ) . '</svg>';
}

/** Coperta unei cărți sau, dacă lipsește, o copertă simplă cu titlul. */
function p45cd_coperta_html( $carte, $clasa = 'coperta' ) {
	if ( ! empty( $carte['coperta'] ) ) {
		return '<img class="' . esc_attr( $clasa ) . '" src="' . esc_url( $carte['coperta'] ) . '" alt="Coperta: ' . esc_attr( $carte['titlu'] ) . '" loading="lazy">';
	}
	return '<span class="' . esc_attr( $clasa ) . ' coperta-goala" role="img" aria-label="' . esc_attr( $carte['titlu'] ) . '"><span>' . esc_html( $carte['titlu'] ) . '</span></span>';
}

function p45cd_email_suport_html() {
	$e = p45cd_setare( 'email_suport' );
	return $e ? '<a href="mailto:' . esc_attr( $e ) . '">' . esc_html( $e ) . '</a>' : 'editură';
}
