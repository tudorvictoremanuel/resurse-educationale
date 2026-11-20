<?php
/**
 * Migrarea de pe platforma veche (resurse.edituraparalela45.ro):
 * 1. manualele, din pachetele p45-migrare-*.zip (pagini deja convertite);
 * 2. profesorii, din exportul .sql al bazei vechi, cu aceeași parolă și același acces.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ------------------------------------------------------------------ */
/* Dosarul de lucru                                                    */
/* ------------------------------------------------------------------ */

/** Dosar protejat pentru exportul încărcat (date personale). */
function p45cd_dir_import() {
	$dir = p45cd_dir_baza() . '/import';
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	p45cd_protejeaza_dir( $dir );
	return $dir;
}

/** Șterge datele profesorilor încărcate din exportul vechi. */
function p45cd_import_sterge_date() {
	$info = get_option( 'p45cd_import' );
	if ( is_array( $info ) && ! empty( $info['fisier'] ) ) {
		$f = p45cd_dir_import() . '/' . basename( $info['fisier'] );
		if ( file_exists( $f ) ) {
			@unlink( $f );
		}
	}
	foreach ( (array) glob( p45cd_dir_import() . '/*.{json,sql}', GLOB_BRACE ) as $f ) {
		@unlink( $f );
	}
	delete_option( 'p45cd_import' );
}

/* ------------------------------------------------------------------ */
/* Citirea exportului .sql (phpMyAdmin sau mysqldump)                  */
/* ------------------------------------------------------------------ */

/** Citește rândurile „(…),(…);” de la poziția $i. Întoarce lista de rânduri sau false. */
function p45cd_sql_valori( $s, &$i ) {
	$n    = strlen( $s );
	$rows = array();
	$esc  = array( 'n' => "\n", 'r' => "\r", 't' => "\t", '0' => "\0", 'Z' => "\x1a", 'b' => "\x08" );
	while ( $i < $n ) {
		$i += strspn( $s, " \n\r\t,", $i );
		if ( $i >= $n ) {
			break;
		}
		if ( ';' === $s[ $i ] ) {
			$i++;
			break;
		}
		if ( '(' !== $s[ $i ] ) {
			return false;
		}
		$i++;
		$row = array();
		while ( true ) {
			$i += strspn( $s, " \n\r\t", $i );
			if ( $i >= $n ) {
				return false;
			}
			if ( "'" === $s[ $i ] ) {
				$i++;
				$buf = '';
				while ( true ) {
					$k    = strcspn( $s, "\\'", $i );
					$buf .= substr( $s, $i, $k );
					$i   += $k;
					if ( $i >= $n ) {
						return false;
					}
					if ( '\\' === $s[ $i ] ) {
						$nx   = isset( $s[ $i + 1 ] ) ? $s[ $i + 1 ] : '';
						$buf .= isset( $esc[ $nx ] ) ? $esc[ $nx ] : $nx;
						$i   += 2;
					} elseif ( isset( $s[ $i + 1 ] ) && "'" === $s[ $i + 1 ] ) {
						$buf .= "'";
						$i   += 2;
					} else {
						$i++;
						break;
					}
				}
				$row[] = $buf;
			} else {
				$k     = strcspn( $s, ',)', $i );
				$tok   = trim( substr( $s, $i, $k ) );
				$i    += $k;
				$row[] = ( 0 === strcasecmp( $tok, 'NULL' ) ) ? null : $tok;
			}
			$i += strspn( $s, " \n\r\t", $i );
			if ( $i >= $n ) {
				return false;
			}
			if ( ',' === $s[ $i ] ) {
				$i++;
				continue;
			}
			if ( ')' === $s[ $i ] ) {
				$i++;
				break;
			}
			return false;
		}
		$rows[] = $row;
	}
	return $rows;
}

/** Coloanele unui tabel, din CREATE TABLE (pentru exporturi fără nume de coloane). */
function p45cd_sql_coloane_create( $s, $tabel ) {
	if ( ! preg_match( '/CREATE TABLE (?:IF NOT EXISTS )?`' . preg_quote( $tabel, '/' ) . '`\s*\((.*?)\n\)/s', $s, $m ) ) {
		return array();
	}
	preg_match_all( '/^\s*`([^`]+)`\s+\w/m', $m[1], $c );
	return $c[1];
}

/** Toate rândurile unui tabel, ca liste asociative coloană => valoare. */
function p45cd_sql_tabel( $s, $tabel ) {
	$rez  = array();
	$poz  = 0;
	$re   = '/INSERT INTO `' . preg_quote( $tabel, '/' ) . '`\s*(?:\(([^)]*)\))?\s*VALUES\s*/i';
	$cols = null;
	while ( preg_match( $re, $s, $m, PREG_OFFSET_CAPTURE, $poz ) ) {
		if ( ! empty( $m[1][0] ) ) {
			$cols = array_map(
				function ( $c ) {
					return trim( $c, " `\n\r\t" );
				},
				explode( ',', $m[1][0] )
			);
		} elseif ( null === $cols ) {
			$cols = p45cd_sql_coloane_create( $s, $tabel );
		}
		$i    = $m[0][1] + strlen( $m[0][0] );
		$rows = p45cd_sql_valori( $s, $i );
		if ( false === $rows ) {
			return new WP_Error( 'p45_sql', 'Nu am putut citi tabelul ' . $tabel . ' din export.' );
		}
		foreach ( $rows as $r ) {
			if ( count( $r ) === count( $cols ) ) {
				$rez[] = array_combine( $cols, $r );
			}
		}
		$poz = $i;
	}
	return $rez;
}

/** Prefixul tabelelor din export (de obicei „wp_”). */
function p45cd_sql_prefix( $s ) {
	if ( preg_match_all( '/(?:CREATE TABLE (?:IF NOT EXISTS )?|INSERT INTO )`(\w*?)users`/i', $s, $m ) ) {
		foreach ( array_unique( $m[1] ) as $p ) {
			if ( false !== strpos( $s, '`' . $p . 'usermeta`' ) ) {
				return $p;
			}
		}
	}
	return null;
}

/** Hash de parolă pe care WordPress îl poate verifica. */
function p45cd_hash_acceptat( $h ) {
	return is_string( $h ) && (bool) preg_match( '/^(\$wp\$2y\$|\$P\$|\$H\$|\$2[aby]\$|\$argon2i?d?\$|[a-f0-9]{32}$)/', $h );
}

/**
 * Citește exportul bazei vechi și păstrează doar ce trebuie pentru import:
 * profesorii (email, nume, parola criptată, manualele) și titlurile manualelor.
 */
function p45cd_citeste_baza_veche( $cale ) {
	$s = @file_get_contents( $cale );
	if ( false === $s || '' === $s ) {
		return new WP_Error( 'p45_sql', 'Fișierul nu a putut fi citit.' );
	}
	if ( "\x1f\x8b" === substr( $s, 0, 2 ) ) {
		$s = function_exists( 'gzdecode' ) ? gzdecode( $s ) : false;
		if ( false === $s ) {
			return new WP_Error( 'p45_sql', 'Nu am putut dezarhiva fișierul .sql.gz. Exportă baza fără compresie.' );
		}
	}
	$p = p45cd_sql_prefix( $s );
	if ( null === $p ) {
		return new WP_Error( 'p45_sql', 'În fișier nu găsesc tabelele de utilizatori WordPress. Verifică dacă ai exportat baza platformei vechi, cu structură și date.' );
	}

	$users = p45cd_sql_tabel( $s, $p . 'users' );
	$meta  = p45cd_sql_tabel( $s, $p . 'usermeta' );
	$posts = p45cd_sql_tabel( $s, $p . 'posts' );
	$opt   = p45cd_sql_tabel( $s, $p . 'options' );
	foreach ( array( $users, $meta, $posts, $opt ) as $t ) {
		if ( is_wp_error( $t ) ) {
			return $t;
		}
	}
	if ( ! $users ) {
		return new WP_Error( 'p45_sql', 'Exportul nu conține utilizatori. Verifică dacă ai bifat „Date” la export.' );
	}

	$chei = array( 'first_name', 'last_name', $p . 'capabilities', 'acces_manuale' );
	$um   = array();
	foreach ( $meta as $m ) {
		if ( in_array( $m['meta_key'], $chei, true ) ) {
			$um[ (int) $m['user_id'] ][ $m['meta_key'] ] = (string) $m['meta_value'];
		}
	}
	unset( $meta );

	$manuale = array();
	foreach ( $posts as $po ) {
		if ( 'manual' === $po['post_type'] && in_array( $po['post_status'], array( 'publish', 'draft', 'private' ), true ) ) {
			$manuale[ (int) $po['ID'] ] = array( 'titlu' => wp_strip_all_tags( $po['post_title'] ), 'profesori' => 0 );
		}
	}
	unset( $posts );

	$jwt = '';
	foreach ( $opt as $o ) {
		if ( 'simple_jwt_login_settings' === $o['option_name'] ) {
			$jwt = (string) $o['option_value'];
		}
	}
	unset( $opt );

	$profesori = array();
	$ignorati  = array( 'administrare' => 0, 'fara_email' => 0 );
	$admin_rol = array( 'administrator', 'editor', 'author', 'contributor', 'shop_manager' );
	foreach ( $users as $u ) {
		$id  = (int) $u['ID'];
		$m   = isset( $um[ $id ] ) ? $um[ $id ] : array();
		$cap = isset( $m[ $p . 'capabilities' ] ) ? @unserialize( $m[ $p . 'capabilities' ], array( 'allowed_classes' => false ) ) : array();
		$rol = is_array( $cap ) ? array_keys( array_filter( $cap ) ) : array();
		if ( array_intersect( $rol, $admin_rol ) ) {
			$ignorati['administrare']++;
			continue;
		}
		$email = sanitize_email( $u['user_email'] );
		if ( ! is_email( $email ) ) {
			$ignorati['fara_email']++;
			continue;
		}
		$acc = array();
		if ( ! empty( $m['acces_manuale'] ) ) {
			$v = @unserialize( $m['acces_manuale'], array( 'allowed_classes' => false ) );
			if ( is_array( $v ) ) {
				foreach ( $v as $x ) {
					if ( is_scalar( $x ) && (int) $x > 0 ) {
						$acc[] = (int) $x;
					}
				}
			}
		}
		$acc = array_values( array_unique( $acc ) );
		foreach ( $acc as $mid ) {
			if ( ! isset( $manuale[ $mid ] ) ) {
				$manuale[ $mid ] = array( 'titlu' => 'Manualul #' . $mid . ' (șters din platforma veche)', 'profesori' => 0 );
			}
			$manuale[ $mid ]['profesori']++;
		}
		$profesori[] = array(
			'id'      => $id,
			'login'   => (string) $u['user_login'],
			'email'   => $email,
			'parola'  => (string) $u['user_pass'],
			'inreg'   => (string) $u['user_registered'],
			'afisat'  => wp_strip_all_tags( (string) $u['display_name'] ),
			'prenume' => isset( $m['first_name'] ) ? wp_strip_all_tags( $m['first_name'] ) : '',
			'nume'    => isset( $m['last_name'] ) ? wp_strip_all_tags( $m['last_name'] ) : '',
			'manuale' => $acc,
		);
	}
	ksort( $manuale );
	return array(
		'profesori' => $profesori,
		'manuale'   => $manuale,
		'ignorati'  => $ignorati,
		'jwt'       => $jwt,
	);
}

/** Datele încărcate din export (sau null). */
function p45cd_import_date() {
	$info = get_option( 'p45cd_import' );
	if ( ! is_array( $info ) || empty( $info['fisier'] ) ) {
		return null;
	}
	$f = p45cd_dir_import() . '/' . basename( $info['fisier'] );
	if ( ! file_exists( $f ) ) {
		return null;
	}
	$d = json_decode( (string) file_get_contents( $f ), true );
	return is_array( $d ) ? $d : null;
}

add_action(
	'admin_post_p45_import_sql',
	function () {
		p45cd_verifica_admin( 'p45_import_sql' );
		$inapoi = admin_url( 'admin.php?page=p45-import#profesori' );
		if ( empty( $_FILES['sql']['tmp_name'] ) || ! empty( $_FILES['sql']['error'] ) ) {
			$err = isset( $_FILES['sql']['error'] ) ? (int) $_FILES['sql']['error'] : 4;
			p45cd_mesaj_admin( 'eroare', in_array( $err, array( 1, 2 ), true ) ? 'Fișierul e mai mare decât permite serverul (' . size_format( wp_max_upload_size() ) . '). Urcă fișierul .sql din managerul de fișiere al hostingului, în dosarul indicat mai jos, sau exportă baza cu compresie gzip (.sql.gz).' : 'Alege fișierul .sql exportat din phpMyAdmin.' );
			wp_safe_redirect( $inapoi );
			exit;
		}
		$nume = sanitize_file_name( wp_unslash( $_FILES['sql']['name'] ) );
		$date = p45cd_import_incarca_sql( $_FILES['sql']['tmp_name'], $nume );
		@unlink( $_FILES['sql']['tmp_name'] );
		if ( is_wp_error( $date ) ) {
			p45cd_mesaj_admin( 'eroare', $date->get_error_message() );
			wp_safe_redirect( $inapoi );
			exit;
		}
		p45cd_mesaj_admin( 'ok', 'Am citit exportul: ' . count( $date['profesori'] ) . ' profesori și ' . count( $date['manuale'] ) . ' manuale. Verifică potrivirea manualelor, apoi pornește importul.' );
		wp_safe_redirect( $inapoi );
		exit;
	}
);

/** Exporturi .sql urcate direct pe server (pentru când limita de încărcare e prea mică). */
function p45cd_sql_pe_server() {
	$rez = array();
	foreach ( array( p45cd_dir_baza(), p45cd_dir_import() ) as $dir ) {
		foreach ( (array) glob( $dir . '/*.{sql,gz}', GLOB_BRACE ) as $f ) {
			if ( is_file( $f ) && preg_match( '/\.(sql|sql\.gz)$/i', $f ) ) {
				$rez[ basename( $f ) ] = $f;
			}
		}
	}
	ksort( $rez );
	return $rez;
}

/** Citește exportul și păstrează datele pentru import. */
function p45cd_import_incarca_sql( $cale, $nume ) {
	@set_time_limit( 300 );
	@ini_set( 'memory_limit', '512M' );
	$date = p45cd_citeste_baza_veche( $cale );
	if ( is_wp_error( $date ) ) {
		return $date;
	}
	p45cd_import_sterge_date();
	$fisier = 'baza-veche-' . p45cd_token_nou() . '.json';
	file_put_contents( p45cd_dir_import() . '/' . $fisier, wp_json_encode( $date ) );
	update_option(
		'p45cd_import',
		array(
			'fisier'    => $fisier,
			'original'  => $nume,
			'incarcat'  => time(),
			'profesori' => count( $date['profesori'] ),
		),
		false
	);
	return $date;
}

add_action(
	'admin_post_p45_import_sql_server',
	function () {
		p45cd_verifica_admin( 'p45_import_sql_server' );
		$inapoi = admin_url( 'admin.php?page=p45-import#profesori' );
		$nume   = isset( $_POST['fisier'] ) ? basename( sanitize_file_name( wp_unslash( $_POST['fisier'] ) ) ) : '';
		$toate  = p45cd_sql_pe_server();
		if ( ! isset( $toate[ $nume ] ) ) {
			p45cd_mesaj_admin( 'eroare', 'Fișierul nu mai e pe server.' );
			wp_safe_redirect( $inapoi );
			exit;
		}
		$date = p45cd_import_incarca_sql( $toate[ $nume ], $nume );
		if ( is_wp_error( $date ) ) {
			p45cd_mesaj_admin( 'eroare', $date->get_error_message() );
			wp_safe_redirect( $inapoi );
			exit;
		}
		@unlink( $toate[ $nume ] );
		p45cd_mesaj_admin( 'ok', 'Am citit exportul: ' . count( $date['profesori'] ) . ' profesori și ' . count( $date['manuale'] ) . ' manuale. Fișierul .sql a fost șters de pe server. Verifică potrivirea manualelor, apoi pornește importul.' );
		wp_safe_redirect( $inapoi );
		exit;
	}
);

add_action(
	'admin_post_p45_import_renunta',
	function () {
		p45cd_verifica_admin( 'p45_import_renunta' );
		p45cd_import_sterge_date();
		p45cd_mesaj_admin( 'ok', 'Am șters datele încărcate din exportul vechi.' );
		wp_safe_redirect( admin_url( 'admin.php?page=p45-import#profesori' ) );
		exit;
	}
);

/* ------------------------------------------------------------------ */
/* Importul profesorilor                                               */
/* ------------------------------------------------------------------ */

/** Un login nou, unic, pentru un cont importat. */
function p45cd_login_import( $login, $email ) {
	$login = sanitize_user( $login, true );
	if ( $login && strlen( $login ) <= 60 && ! username_exists( $login ) ) {
		return $login;
	}
	$login = mb_substr( sanitize_user( $email, true ), 0, 60 );
	if ( $login && ! username_exists( $login ) ) {
		return $login;
	}
	do {
		$login = 'profesor' . wp_rand( 100000, 999999 );
	} while ( username_exists( $login ) );
	return $login;
}

/** Importă un profesor. Întoarce [stare, acces_nou]. */
function p45cd_importa_profesor( $u, $harta ) {
	global $wpdb;
	$user  = get_user_by( 'email', $u['email'] );
	$stare = 'existent';
	$hash  = p45cd_hash_acceptat( $u['parola'] ) ? $u['parola'] : '';

	if ( $user && ( user_can( $user, 'p45_gestionare' ) || user_can( $user, 'edit_posts' ) ) ) {
		return array( 'ignorat', 0 );
	}

	if ( ! $user ) {
		$uid = wp_insert_user(
			array(
				'user_login'   => p45cd_login_import( $u['login'], $u['email'] ),
				'user_email'   => $u['email'],
				'user_pass'    => wp_generate_password( 32, true, true ),
				'display_name' => $u['afisat'] ? $u['afisat'] : trim( $u['prenume'] . ' ' . $u['nume'] ),
				'first_name'   => $u['prenume'],
				'last_name'    => $u['nume'],
				'role'         => 'p45_profesor',
			)
		);
		if ( is_wp_error( $uid ) ) {
			return array( 'eroare', 0 );
		}
		$campuri = array();
		if ( $hash ) {
			$campuri['user_pass'] = $hash;
		}
		if ( preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $u['inreg'] ) && '0000-00-00 00:00:00' !== $u['inreg'] ) {
			$campuri['user_registered'] = $u['inreg'];
		}
		if ( $campuri ) {
			$wpdb->update( $wpdb->users, $campuri, array( 'ID' => $uid ) );
			clean_user_cache( $uid );
		}
		update_user_meta( $uid, 'p45_import', time() );
		update_user_meta( $uid, 'p45_import_id', (int) $u['id'] );
		if ( $hash ) {
			update_user_meta( $uid, 'p45_activat', time() );
		}
		$user  = get_user_by( 'id', $uid );
		$stare = $hash ? 'creat' : 'creat_fara_parola';
	} elseif ( get_user_meta( $user->ID, 'p45_import', true ) && ! get_user_meta( $user->ID, 'p45_ultima_intrare', true ) ) {
		/* Importat anterior, încă nefolosit aici: luăm parola cea mai nouă din platforma veche. */
		if ( $hash && $hash !== $user->user_pass ) {
			$wpdb->update( $wpdb->users, array( 'user_pass' => $hash ), array( 'ID' => $user->ID ) );
			clean_user_cache( $user->ID );
		}
		$stare = 'actualizat';
	}

	if ( ! in_array( 'p45_profesor', (array) $user->roles, true ) ) {
		$user->add_role( 'p45_profesor' );
	}

	$t      = p45cd_tabel_acces();
	$are    = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT carte_id FROM $t WHERE user_id = %d", $user->ID ) ) );
	$nou    = 0;
	$acum   = current_time( 'mysql' );
	foreach ( (array) $u['manuale'] as $mid ) {
		if ( empty( $harta[ (int) $mid ] ) ) {
			continue;
		}
		$cid = (int) $harta[ (int) $mid ];
		if ( in_array( $cid, $are, true ) ) {
			continue;
		}
		$wpdb->insert(
			$t,
			array(
				'user_id'  => $user->ID,
				'carte_id' => $cid,
				'comanda'  => 'Platforma veche',
				'expira'   => null,
				'creat'    => $acum,
			)
		);
		$are[] = $cid;
		$nou++;
	}
	return array( $stare, $nou );
}

/* ------------------------------------------------------------------ */
/* Manualele din pachetele de migrare                                  */
/* ------------------------------------------------------------------ */

/** Pachetele găsite: p45-migrare*.zip sau dosare p45-migrare* cu manifest.json. */
function p45cd_pachete_migrare() {
	$up    = wp_upload_dir();
	$locuri = array(
		array( p45cd_dir_baza(), false ),
		array( untrailingslashit( $up['basedir'] ), true ),
	);
	$rez = array();
	foreach ( $locuri as $loc ) {
		list( $dir, $public ) = $loc;
		foreach ( (array) glob( $dir . '/p45-migrare*' ) as $cale ) {
			$tip = is_dir( $cale ) ? 'dir' : ( preg_match( '/\.zip$/i', $cale ) ? 'zip' : '' );
			if ( ! $tip ) {
				continue;
			}
			$man = p45cd_pachet_citeste( array( 'cale' => $cale, 'tip' => $tip ), 'manifest.json' );
			$man = $man ? json_decode( $man, true ) : null;
			$rez[ basename( $cale ) ] = array(
				'nume'     => basename( $cale ),
				'cale'     => $cale,
				'tip'      => $tip,
				'public'   => $public,
				'marime'   => 'zip' === $tip ? (int) @filesize( $cale ) : 0,
				'manifest' => ( is_array( $man ) && ! empty( $man['carti'] ) ) ? $man : null,
				'eroare'   => ( 'zip' === $tip && ! class_exists( 'ZipArchive' ) ) ? 'Serverul nu are extensia PHP „zip”. Dezarhivează pachetul în același loc și reîncarcă pagina.' : '',
			);
		}
	}
	ksort( $rez );
	return $rez;
}

/** Conținutul unui fișier din pachet (sau false). */
function p45cd_pachet_citeste( $pachet, $intern ) {
	if ( false !== strpos( $intern, '..' ) ) {
		return false;
	}
	if ( 'dir' === $pachet['tip'] ) {
		$f = $pachet['cale'] . '/' . $intern;
		return file_exists( $f ) ? file_get_contents( $f ) : false;
	}
	static $zipuri = array();
	if ( ! class_exists( 'ZipArchive' ) ) {
		return false;
	}
	if ( ! isset( $zipuri[ $pachet['cale'] ] ) ) {
		$z = new ZipArchive();
		if ( true !== $z->open( $pachet['cale'] ) ) {
			return false;
		}
		$zipuri[ $pachet['cale'] ] = $z;
	}
	return $zipuri[ $pachet['cale'] ]->getFromName( $intern );
}

/** Id-ul unui termen după nume (îl creează dacă lipsește). */
function p45cd_termen_dupa_nume( $nume, $tax ) {
	$nume = trim( wp_strip_all_tags( (string) $nume ) );
	if ( '' === $nume ) {
		return 0;
	}
	$t = term_exists( $nume, $tax );
	if ( ! $t ) {
		$t = wp_insert_term( $nume, $tax );
	}
	return is_wp_error( $t ) ? 0 : (int) ( is_array( $t ) ? $t['term_id'] : $t );
}

/** Adaugă în platformă o carte din pachet. */
function p45cd_importa_carte_din_pachet( $pachet, $c ) {
	$cod = isset( $c['cod'] ) ? (int) $c['cod'] : 0;
	$n   = isset( $c['pagini'] ) ? (int) $c['pagini'] : 0;
	$ext = ( isset( $c['ext'] ) && in_array( $c['ext'], array( 'webp', 'jpg' ), true ) ) ? $c['ext'] : '';
	$sub = isset( $c['dosar'] ) ? (string) $c['dosar'] : '';
	if ( $cod < 1 || $n < 1 || $n > 3000 || ! $ext || ! preg_match( '#^[A-Za-z0-9_\-/]+$#', $sub ) ) {
		return new WP_Error( 'p45_pachet', 'Date incomplete în pachet pentru „' . ( isset( $c['titlu'] ) ? $c['titlu'] : $cod ) . '”.' );
	}
	$exista = p45cd_carte_dupa_cod( $cod );
	if ( $exista ) {
		return array( 'id' => $exista, 'stare' => 'exista' );
	}

	$id = wp_insert_post(
		array(
			'post_type'   => 'p45_carte',
			'post_status' => 'draft',
			'post_title'  => sanitize_text_field( $c['titlu'] ),
			'menu_order'  => isset( $c['ordine'] ) ? (int) $c['ordine'] : 0,
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	$token = p45cd_token_nou();
	$dir   = p45cd_creeaza_dir_carte( $id, $token );
	$ok    = (bool) $dir;
	for ( $i = 1; $ok && $i <= $n; $i++ ) {
		$f = sprintf( '%03d.%s', $i, $ext );
		foreach ( array( 'pagini', 'mini' ) as $d ) {
			$b = p45cd_pachet_citeste( $pachet, $sub . '/' . $d . '/' . $f );
			if ( false === $b || '' === $b || false === file_put_contents( $dir . '/' . $d . '/' . $f, $b ) ) {
				$ok = false;
				break;
			}
		}
	}
	if ( $ok ) {
		foreach ( array( 'webp', 'jpg', 'png' ) as $e ) {
			$b = p45cd_pachet_citeste( $pachet, $sub . '/coperta.' . $e );
			if ( $b ) {
				file_put_contents( $dir . '/coperta.' . $e, $b );
				break;
			}
		}
	}
	if ( ! $ok ) {
		if ( $dir ) {
			p45cd_sterge_dir( $dir );
		}
		wp_delete_post( $id, true );
		return new WP_Error( 'p45_pachet', 'Nu am putut copia paginile pentru „' . $c['titlu'] . '”. Verifică spațiul liber pe server și dacă arhiva e întreagă.' );
	}

	$clase = array_filter( array_map( function ( $x ) { return p45cd_termen_dupa_nume( $x, 'p45_clasa' ); }, (array) ( isset( $c['clase'] ) ? $c['clase'] : array() ) ) );
	$disc  = array_filter( array_map( function ( $x ) { return p45cd_termen_dupa_nume( $x, 'p45_disciplina' ); }, (array) ( isset( $c['discipline'] ) ? $c['discipline'] : array() ) ) );
	wp_set_object_terms( $id, array_values( $clase ), 'p45_clasa' );
	wp_set_object_terms( $id, array_values( $disc ), 'p45_disciplina' );

	update_post_meta( $id, '_p45_cod', $cod );
	update_post_meta( $id, '_p45_pages', $n );
	update_post_meta( $id, '_p45_ext', $ext );
	update_post_meta( $id, '_p45_w', isset( $c['w'] ) ? (int) $c['w'] : 0 );
	update_post_meta( $id, '_p45_h', isset( $c['h'] ) ? (int) $c['h'] : 0 );
	update_post_meta( $id, '_p45_toc', wp_slash( wp_json_encode( p45cd_curata_cuprins( isset( $c['cuprins'] ) ? (array) $c['cuprins'] : array(), $n ) ) ) );
	update_post_meta( $id, '_p45_sursa', mb_substr( sanitize_text_field( isset( $c['sursa'] ) ? $c['sursa'] : 'Platforma veche' ), 0, 200 ) );
	update_post_meta( $id, '_p45_sursa_marime', 0 );
	foreach ( array( '_p45_autor', '_p45_isbn', '_p45_an' ) as $k ) {
		update_post_meta( $id, $k, '' );
	}
	update_post_meta( $id, '_p45_dir', $token );
	update_post_meta( $id, '_p45_cover_v', time() );
	wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
	return array( 'id' => $id, 'stare' => 'creat' );
}

add_action(
	'admin_post_p45_import_sterge_pachete',
	function () {
		p45cd_verifica_admin( 'p45_import_sterge_pachete' );
		$sterse = 0;
		foreach ( p45cd_pachete_migrare() as $p ) {
			if ( 'zip' === $p['tip'] && @unlink( $p['cale'] ) ) {
				$sterse++;
			} elseif ( 'dir' === $p['tip'] && 0 === strpos( realpath( $p['cale'] ), realpath( p45cd_dir_baza() ) ) ) {
				p45cd_sterge_dir( $p['cale'] );
				$sterse++;
			}
		}
		p45cd_mesaj_admin( 'ok', $sterse ? 'Am șters arhivele pachetului de pe server.' : 'Nu am putut șterge arhivele. Șterge-le din managerul de fișiere al hostingului.' );
		wp_safe_redirect( admin_url( 'admin.php?page=p45-import' ) );
		exit;
	}
);

/* ------------------------------------------------------------------ */
/* REST: pașii importului (apelați din pagina de import, pe rând)      */
/* ------------------------------------------------------------------ */

add_action(
	'rest_api_init',
	function () {
		$admin = function () {
			return current_user_can( 'p45_gestionare' );
		};
		register_rest_route( 'p45/v1', '/import/carte', array( 'methods' => 'POST', 'callback' => 'p45cd_rest_import_carte', 'permission_callback' => $admin ) );
		register_rest_route( 'p45/v1', '/import/profesori', array( 'methods' => 'POST', 'callback' => 'p45cd_rest_import_profesori', 'permission_callback' => $admin ) );
	}
);

function p45cd_rest_import_carte( $req ) {
	@set_time_limit( 300 );
	$nume    = basename( (string) $req->get_param( 'pachet' ) );
	$cod     = (int) $req->get_param( 'cod' );
	$pachete = p45cd_pachete_migrare();
	if ( empty( $pachete[ $nume ]['manifest'] ) ) {
		return new WP_Error( 'p45_pachet', 'Pachetul ' . $nume . ' nu mai e pe server.', array( 'status' => 404 ) );
	}
	foreach ( $pachete[ $nume ]['manifest']['carti'] as $c ) {
		if ( (int) $c['cod'] === $cod ) {
			$r = p45cd_importa_carte_din_pachet( $pachete[ $nume ], $c );
			if ( is_wp_error( $r ) ) {
				$r->add_data( array( 'status' => 500 ) );
				return $r;
			}
			$r['titlu'] = get_the_title( $r['id'] );
			$r['url']   = admin_url( 'admin.php?page=p45-carte&id=' . $r['id'] );
			return rest_ensure_response( $r );
		}
	}
	return new WP_Error( 'p45_pachet', 'Cartea cu codul ' . $cod . ' nu e în pachet.', array( 'status' => 404 ) );
}

function p45cd_rest_import_profesori( $req ) {
	@set_time_limit( 300 );
	$date = p45cd_import_date();
	if ( ! $date ) {
		return new WP_Error( 'p45_import', 'Încarcă din nou exportul .sql: datele nu mai sunt pe server.', array( 'status' => 400 ) );
	}
	$de_la = max( 0, (int) $req->get_param( 'de_la' ) );
	$lot   = max( 1, min( 200, (int) $req->get_param( 'lot' ) ? (int) $req->get_param( 'lot' ) : 40 ) );
	$harta = p45cd_harta_coduri();
	$total = count( $date['profesori'] );

	if ( 0 === $de_la ) {
		delete_option( 'p45cd_import_progres' );
		if ( $req->get_param( 'copiaza_setari_jwt' ) && ! empty( $date['jwt'] ) ) {
			update_option( 'simple_jwt_login_settings', $date['jwt'] );
		}
	}

	$st = array( 'creat' => 0, 'creat_fara_parola' => 0, 'actualizat' => 0, 'existent' => 0, 'ignorat' => 0, 'eroare' => 0, 'acces' => 0 );
	foreach ( array_slice( $date['profesori'], $de_la, $lot ) as $u ) {
		list( $stare, $nou ) = p45cd_importa_profesor( $u, $harta );
		$st[ $stare ]++;
		$st['acces'] += $nou;
	}
	$sum = (array) get_option( 'p45cd_import_progres', array() );
	foreach ( $st as $k => $v ) {
		$sum[ $k ] = ( isset( $sum[ $k ] ) ? (int) $sum[ $k ] : 0 ) + $v;
	}
	update_option( 'p45cd_import_progres', $sum, false );

	$urm  = min( $total, $de_la + $lot );
	$gata = $urm >= $total;
	if ( $gata ) {
		$info = (array) get_option( 'p45cd_import', array() );
		update_option(
			'p45cd_import_rezultat',
			array(
				'data'       => time(),
				'profesori'  => $total,
				'original'   => isset( $info['original'] ) ? (string) $info['original'] : '',
				'statistici' => $sum,
				'jwt'        => (bool) ( $req->get_param( 'copiaza_setari_jwt' ) && ! empty( $date['jwt'] ) ),
			),
			false
		);
		delete_option( 'p45cd_import_progres' );
		/* Datele personale nu mai sunt necesare după import. */
		p45cd_import_sterge_date();
	}
	return rest_ensure_response(
		array(
			'de_la'      => $de_la,
			'urmatorul'  => $urm,
			'total'      => $total,
			'gata'       => $gata,
			'statistici' => $st,
		)
	);
}

