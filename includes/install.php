<?php
/**
 * Instalare: tabele, roluri, termeni impliciți, reguli de adresă.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function p45cd_tabel_acces() {
	global $wpdb;
	return $wpdb->prefix . 'p45_acces';
}

function p45cd_tabel_date() {
	global $wpdb;
	return $wpdb->prefix . 'p45_date';
}

function p45cd_creeaza_tabele() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$charset = $wpdb->get_charset_collate();
	$acces   = p45cd_tabel_acces();
	$date    = p45cd_tabel_date();

	dbDelta(
		"CREATE TABLE $acces (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		user_id bigint(20) unsigned NOT NULL,
		carte_id bigint(20) unsigned NOT NULL,
		comanda varchar(64) NOT NULL DEFAULT '',
		expira date NULL DEFAULT NULL,
		creat datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
		PRIMARY KEY  (id),
		UNIQUE KEY user_carte (user_id,carte_id),
		KEY carte_id (carte_id)
		) $charset;"
	);

	dbDelta(
		"CREATE TABLE $date (
		user_id bigint(20) unsigned NOT NULL,
		carte_id bigint(20) unsigned NOT NULL,
		date longtext NOT NULL,
		actualizat datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
		PRIMARY KEY  (user_id,carte_id)
		) $charset;"
	);

	update_option( 'p45cd_db_version', P45CD_DB_VERSION );
}

function p45cd_roluri() {
	if ( ! get_role( 'p45_profesor' ) ) {
		add_role( 'p45_profesor', 'Profesor (cărți digitale)', array( 'read' => true ) );
	}
	foreach ( array( 'administrator', 'editor' ) as $r ) {
		$rol = get_role( $r );
		if ( $rol && ! $rol->has_cap( 'p45_gestionare' ) ) {
			$rol->add_cap( 'p45_gestionare' );
		}
	}
}

function p45cd_termeni_impliciti() {
	$clase = array( 'Clasa pregătitoare', 'Clasa I', 'Clasa a II-a', 'Clasa a III-a', 'Clasa a IV-a' );
	foreach ( $clase as $i => $c ) {
		if ( ! term_exists( $c, 'p45_clasa' ) ) {
			$t = wp_insert_term( $c, 'p45_clasa' );
			if ( ! is_wp_error( $t ) ) {
				update_term_meta( $t['term_id'], 'ordine', $i + 1 );
			}
		}
	}
	$disc = array(
		'Comunicare în limba română',
		'Matematică și explorarea mediului',
		'Matematică',
		'Limba și literatura română',
		'Dezvoltare personală',
		'Muzică și mișcare',
		'Arte vizuale și abilități practice',
		'Lectură',
		'Evaluare națională',
	);
	foreach ( $disc as $d ) {
		if ( ! term_exists( $d, 'p45_disciplina' ) ) {
			wp_insert_term( $d, 'p45_disciplina' );
		}
	}
}

function p45cd_activate() {
	p45cd_creeaza_tabele();
	p45cd_roluri();
	p45cd_inregistreaza_tipuri();
	p45cd_termeni_impliciti();
	p45cd_dir_baza();
	if ( false === get_option( 'p45cd_setari', false ) ) {
		$s                 = p45cd_setari_implicite();
		$s['email_suport'] = get_option( 'admin_email' );
		add_option( 'p45cd_setari', $s );
	}
	if ( ! get_option( 'permalink_structure' ) ) {
		update_option( 'permalink_structure', '/%postname%/' );
	}
	p45cd_reguli_adrese();
	flush_rewrite_rules();
}

function p45cd_deactivate() {
	flush_rewrite_rules();
}

/* Actualizare automată a tabelelor dacă se schimbă versiunea. */
add_action(
	'plugins_loaded',
	function () {
		if ( get_option( 'p45cd_db_version' ) !== P45CD_DB_VERSION ) {
			p45cd_creeaza_tabele();
			p45cd_roluri();
		}
	}
);
