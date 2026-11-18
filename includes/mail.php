<?php
/**
 * Emailurile platformei: invitație, cărți noi, resetare parolă.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Linkurile de setare a parolei sunt valabile câte zile alege editura. */
add_filter(
	'password_reset_expiration',
	function ( $exp ) {
		$zile = (int) p45cd_setare( 'zile_link' );
		return $zile > 0 ? $zile * DAY_IN_SECONDS : $exp;
	}
);

function p45cd_link_parola( $user ) {
	$key = get_password_reset_key( $user );
	if ( is_wp_error( $key ) ) {
		return '';
	}
	return p45cd_url( 'parola', array( 'key' => $key, 'login' => rawurlencode( $user->user_login ) ) );
}

/** Învelișul HTML comun al emailurilor. */
function p45cd_email_html( $continut ) {
	$logo  = esc_url( P45CD_URL . 'assets/img/logo.png' );
	$suport = p45cd_setare( 'email_suport' );
	$subsol = 'Editura Paralela 45';
	if ( $suport ) {
		$subsol .= ' · Ajutor: ' . esc_html( $suport );
	}
	return '<!doctype html><html lang="ro"><head><meta charset="utf-8"></head><body style="margin:0;background:#F6F5F2;font-family:Arial,Helvetica,sans-serif;color:#1A1D24">'
		. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F6F5F2;padding:24px 12px"><tr><td align="center">'
		. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#FFFFFF;border-radius:16px;border:1px solid #E7E4DE">'
		. '<tr><td style="padding:28px 28px 8px"><img src="' . $logo . '" alt="Paralela 45" width="40" style="display:block"></td></tr>'
		. '<tr><td style="padding:8px 28px 28px;font-size:15px;line-height:1.55">' . $continut . '</td></tr>'
		. '</table>'
		. '<p style="font-size:12px;color:#5B6270;margin:16px 0 0">' . $subsol . '</p>'
		. '</td></tr></table></body></html>';
}

function p45cd_buton_email( $url, $text ) {
	return '<p style="margin:22px 0"><a href="' . esc_url( $url ) . '" style="display:inline-block;background:#CF2C14;color:#FFFFFF;text-decoration:none;font-weight:bold;padding:13px 22px;border-radius:12px">' . esc_html( $text ) . '</a></p>';
}

function p45cd_trimite( $catre, $subiect, $html ) {
	$tip = function () {
		return 'text/html';
	};
	add_filter( 'wp_mail_content_type', $tip );
	$ok = wp_mail( $catre, $subiect, $html );
	remove_filter( 'wp_mail_content_type', $tip );
	return $ok;
}

function p45cd_lista_carti_html( $carti_ids ) {
	$li = '';
	foreach ( (array) $carti_ids as $id ) {
		$li .= '<li style="margin:2px 0">' . esc_html( get_the_title( $id ) ) . '</li>';
	}
	return $li ? '<ul style="padding-left:20px;margin:8px 0 14px">' . $li . '</ul>' : '';
}

/** Invitație pentru un cont nou: link de setare a parolei + lista cărților. */
function p45cd_email_invitatie( $user, $carti_ids, $comanda = '' ) {
	$link = p45cd_link_parola( $user );
	if ( ! $link ) {
		return false;
	}
	$nume = $user->display_name ? $user->display_name : $user->user_email;
	$zile = (int) p45cd_setare( 'zile_link' );
	$c    = '<p>Bună ziua, ' . esc_html( $nume ) . ',</p>';
	if ( $comanda ) {
		$c .= '<p>Odată cu comanda nr. ' . esc_html( $comanda ) . ' ați primit și variantele digitale ale cărților:</p>';
	} else {
		$c .= '<p>Ați primit acces la variantele digitale ale cărților:</p>';
	}
	$c .= p45cd_lista_carti_html( $carti_ids );
	$c .= '<p>Le puteți deschide pe tabla interactivă din clasă, pe calculator sau pe telefon.</p>';
	$c .= p45cd_buton_email( $link, 'Setează parola și intră' );
	$c .= '<p style="font-size:13px;color:#5B6270">Contul dumneavoastră este: ' . esc_html( $user->user_email ) . '. Linkul este valabil ' . $zile . ' zile. Dacă expiră, folosiți „Ai uitat parola?” pe pagina de intrare.</p>';
	$c .= '<p style="font-size:13px;color:#5B6270">Adresa platformei: <a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html( preg_replace( '#^https?://#', '', home_url() ) ) . '</a></p>';
	update_user_meta( $user->ID, 'p45_invitat', time() );
	return p45cd_trimite( $user->user_email, 'Cărțile dumneavoastră digitale Paralela 45 sunt gata', p45cd_email_html( $c ) );
}

/** Profesor cu cont deja existent: i s-au adăugat cărți noi. */
function p45cd_email_carti_noi( $user, $carti_ids, $comanda = '' ) {
	$nume = $user->display_name ? $user->display_name : $user->user_email;
	$c    = '<p>Bună ziua, ' . esc_html( $nume ) . ',</p>';
	$c   .= '<p>' . ( $comanda ? 'Odată cu comanda nr. ' . esc_html( $comanda ) . ' au' : 'Au' ) . ' fost adăugate în contul dumneavoastră cărțile digitale:</p>';
	$c   .= p45cd_lista_carti_html( $carti_ids );
	$c   .= p45cd_buton_email( p45cd_url( 'biblioteca' ), 'Deschide biblioteca' );
	return p45cd_trimite( $user->user_email, 'Cărți digitale noi în contul dumneavoastră', p45cd_email_html( $c ) );
}

/** Resetare parolă, cerută de profesor. */
function p45cd_email_resetare( $user ) {
	$link = p45cd_link_parola( $user );
	if ( ! $link ) {
		return false;
	}
	$c  = '<p>Bună ziua,</p>';
	$c .= '<p>Am primit o cerere de schimbare a parolei pentru contul ' . esc_html( $user->user_email ) . '.</p>';
	$c .= p45cd_buton_email( $link, 'Alege o parolă nouă' );
	$c .= '<p style="font-size:13px;color:#5B6270">Dacă nu ați cerut dumneavoastră schimbarea, ignorați acest email: parola rămâne aceeași.</p>';
	return p45cd_trimite( $user->user_email, 'Schimbarea parolei – cărți digitale Paralela 45', p45cd_email_html( $c ) );
}
