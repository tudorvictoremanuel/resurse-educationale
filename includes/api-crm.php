<?php
/**
 * Legătura cu CRM-ul editurii, compatibilă cu platforma veche:
 *   GET  /wp-json/ep45/v1/utilizatori/?AUTH_KEY=…&email=…                    → manualele profesorului
 *   POST /wp-json/ep45/v1/utilizatori/  AUTH_KEY, email, manuale_acces=280,302 → setează manualele
 * Manualele se identifică prin „Cod CRM” (numărul manualului din platforma veche).
 * Răspunsurile au aceeași formă ca pe platforma veche, ca CRM-ul să nu trebuiască modificat.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route( 'ep45/v1', '/utilizatori/', array( 'methods' => 'GET', 'callback' => 'p45cd_crm_get', 'permission_callback' => '__return_true' ) );
		register_rest_route( 'ep45/v1', '/utilizatori/', array( 'methods' => 'POST', 'callback' => 'p45cd_crm_post', 'permission_callback' => '__return_true' ) );
	}
);

function p45cd_crm_raspuns( $status, $data ) {
	return new WP_REST_Response( array( 'status' => $status, 'data' => $data ) );
}

/** Cheia trimisă de CRM e cea din setări (fără cheie setată, legătura e oprită). */
function p45cd_crm_cheie_ok( $req ) {
	$cheie = (string) p45cd_setare( 'cheie_api' );
	$dat   = (string) $req->get_param( 'AUTH_KEY' );
	return '' !== $cheie && '' !== $dat && hash_equals( $cheie, $dat );
}

/** Codurile CRM ale cărților la care profesorul are acces. */
function p45cd_crm_coduri_profesor( $user_id ) {
	global $wpdb;
	$t   = p45cd_tabel_acces();
	$ids = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT carte_id FROM $t WHERE user_id = %d AND ( expira IS NULL OR expira >= %s )", $user_id, current_time( 'Y-m-d' ) ) ) );
	$rez = array();
	foreach ( p45cd_harta_coduri() as $cod => $cid ) {
		if ( in_array( $cid, $ids, true ) ) {
			$rez[] = $cod;
		}
	}
	sort( $rez );
	return $rez;
}

function p45cd_crm_utilizator( $user, $coduri ) {
	return array(
		'ID'            => (int) $user->ID,
		'email'         => $user->user_email,
		'manuale_acces' => $coduri,
	);
}

function p45cd_crm_get( WP_REST_Request $req ) {
	if ( ! p45cd_crm_cheie_ok( $req ) ) {
		return p45cd_crm_raspuns( 400, 'Invalid Auth Code ( AUTH_KEY ) provided.' );
	}
	$email = sanitize_email( (string) $req->get_param( 'email' ) );
	if ( '' === $email ) {
		return p45cd_crm_raspuns( 400, 'No email provided.' );
	}
	$user = get_user_by( 'email', $email );
	if ( ! $user ) {
		return p45cd_crm_raspuns( 400, 'No user found.' );
	}
	return p45cd_crm_raspuns( 200, p45cd_crm_utilizator( $user, p45cd_crm_coduri_profesor( $user->ID ) ) );
}

function p45cd_crm_post( WP_REST_Request $req ) {
	global $wpdb;
	if ( ! p45cd_crm_cheie_ok( $req ) ) {
		return p45cd_crm_raspuns( 400, 'Invalid Auth Code ( AUTH_KEY ) provided.' );
	}
	$email = sanitize_email( (string) $req->get_param( 'email' ) );
	if ( '' === $email ) {
		return p45cd_crm_raspuns( 400, 'No email provided.' );
	}
	$lista = (string) $req->get_param( 'manuale_acces' );
	if ( '' === trim( $lista ) ) {
		return p45cd_crm_raspuns( 400, 'Invalid manuale_acces provided.' );
	}
	$user = get_user_by( 'email', $email );
	if ( ! $user ) {
		return p45cd_crm_raspuns( 400, 'No user found.' );
	}

	$coduri = array_values( array_unique( array_filter( array_map( 'intval', explode( ',', $lista ) ) ) ) );
	$harta  = p45cd_harta_coduri();
	$t      = p45cd_tabel_acces();
	$are    = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT carte_id FROM $t WHERE user_id = %d", $user->ID ) ) );

	/* Ca pe platforma veche, lista trimisă e lista completă a manualelor din CRM:
	   dăm acces la cele din listă și îl scoatem la celelalte cărți cu cod CRM.
	   Cărțile fără cod CRM (date din platformă) nu sunt atinse. */
	foreach ( $harta as $cod => $cid ) {
		if ( in_array( $cod, $coduri, true ) ) {
			if ( ! in_array( $cid, $are, true ) ) {
				p45cd_da_acces( $user->ID, $cid, 'CRM' );
			} else {
				/* Accesul expirat se reînnoiește. */
				$wpdb->query( $wpdb->prepare( "UPDATE $t SET expira = NULL WHERE user_id = %d AND carte_id = %d AND expira IS NOT NULL AND expira < %s", $user->ID, $cid, current_time( 'Y-m-d' ) ) );
			}
		} elseif ( in_array( $cid, $are, true ) ) {
			$wpdb->delete( $t, array( 'user_id' => $user->ID, 'carte_id' => $cid ) );
		}
	}

	if ( ! user_can( $user, 'p45_gestionare' ) && ! in_array( 'p45_profesor', (array) $user->roles, true ) ) {
		$user->add_role( 'p45_profesor' );
	}
	return p45cd_crm_raspuns( 200, p45cd_crm_utilizator( $user, p45cd_crm_coduri_profesor( $user->ID ) ) );
}
