<?php
/**
 * Funcții ajutătoare: setări, stocarea paginilor, datele unei cărți, URL-uri.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ------------------------------------------------------------------ */
/* Setări                                                              */
/* ------------------------------------------------------------------ */

function p45cd_setari_implicite() {
	return array(
		'email_suport' => '',
		'zile_link'    => 7,
		'latime'       => 1400,
		'calitate'     => 80,
		'cheie_api'    => '',
	);
}

function p45cd_setare( $cheie ) {
	$s = wp_parse_args( (array) get_option( 'p45cd_setari', array() ), p45cd_setari_implicite() );
	return isset( $s[ $cheie ] ) ? $s[ $cheie ] : null;
}

/* ------------------------------------------------------------------ */
/* Permisiuni                                                          */
/* ------------------------------------------------------------------ */

/** Poate administra cărțile și profesorii (editura). */
function p45cd_e_admin( $user_id = null ) {
	if ( null === $user_id ) {
		return current_user_can( 'p45_gestionare' );
	}
	return user_can( $user_id, 'p45_gestionare' );
}

/* ------------------------------------------------------------------ */
/* URL-uri pentru paginile platformei                                  */
/* ------------------------------------------------------------------ */

function p45cd_permalinkuri() {
	return (bool) get_option( 'permalink_structure' );
}

/**
 * URL pentru o pagină a platformei: intra, parola, biblioteca, iesire, carte.
 */
function p45cd_url( $view, $args = array() ) {
	if ( 'carte' === $view ) {
		$id   = isset( $args['id'] ) ? (int) $args['id'] : 0;
		$slug = $id ? sanitize_title( get_the_title( $id ) ) : '';
		unset( $args['id'] );
		if ( p45cd_permalinkuri() ) {
			$url = home_url( '/carte/' . $id . ( $slug ? '-' . $slug : '' ) . '/' );
		} else {
			$url = add_query_arg( array( 'p45_view' => 'carte', 'p45_id' => $id ), home_url( '/' ) );
		}
		return $args ? add_query_arg( $args, $url ) : $url;
	}
	if ( p45cd_permalinkuri() ) {
		$url = home_url( '/' . $view . '/' );
	} else {
		$url = add_query_arg( 'p45_view', $view, home_url( '/' ) );
	}
	return $args ? add_query_arg( $args, $url ) : $url;
}

/* ------------------------------------------------------------------ */
/* Stocarea paginilor                                                  */
/* ------------------------------------------------------------------ */

/** Dosarul în care stau toate cărțile, protejat de accesul direct. */
function p45cd_dir_baza() {
	$up   = wp_upload_dir( null, false );
	$baza = trailingslashit( $up['basedir'] ) . 'p45-carti';
	if ( ! is_dir( $baza ) ) {
		wp_mkdir_p( $baza );
	}
	p45cd_protejeaza_dir( $baza );
	return $baza;
}

/** Pune .htaccess (Apache) și index.php gol într-un dosar. */
function p45cd_protejeaza_dir( $dir ) {
	if ( ! is_dir( $dir ) ) {
		return;
	}
	$ht = $dir . '/.htaccess';
	if ( ! file_exists( $ht ) ) {
		@file_put_contents( $ht, "# Paralela 45 – Cărți digitale: paginile se servesc doar prin platformă\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n" );
	}
	$ix = $dir . '/index.php';
	if ( ! file_exists( $ix ) ) {
		@file_put_contents( $ix, "<?php\n// Silence is golden.\n" );
	}
}

/** Token nou, folosit ca nume de dosar greu de ghicit. */
function p45cd_token_nou() {
	return strtolower( wp_generate_password( 24, false, false ) );
}

function p45cd_token_valid( $token ) {
	return is_string( $token ) && (bool) preg_match( '/^[a-z0-9]{12,40}$/', $token );
}

/** Dosarul unei cărți pentru un anumit token (versiune a paginilor). */
function p45cd_dir_carte( $carte_id, $token = null ) {
	if ( null === $token ) {
		$token = get_post_meta( $carte_id, '_p45_dir', true );
	}
	if ( ! p45cd_token_valid( $token ) ) {
		return '';
	}
	return p45cd_dir_baza() . '/' . (int) $carte_id . '-' . $token;
}

/** Creează structura de dosare pentru o versiune nouă de pagini. */
function p45cd_creeaza_dir_carte( $carte_id, $token ) {
	$dir = p45cd_dir_carte( $carte_id, $token );
	if ( ! $dir ) {
		return '';
	}
	wp_mkdir_p( $dir . '/pagini' );
	wp_mkdir_p( $dir . '/mini' );
	p45cd_protejeaza_dir( $dir );
	p45cd_protejeaza_dir( $dir . '/pagini' );
	p45cd_protejeaza_dir( $dir . '/mini' );
	return $dir;
}

/** Șterge recursiv un dosar din stocarea cărților (doar din interiorul ei). */
function p45cd_sterge_dir( $dir ) {
	$baza = realpath( p45cd_dir_baza() );
	$real = realpath( $dir );
	if ( ! $baza || ! $real || 0 !== strpos( $real, $baza . DIRECTORY_SEPARATOR ) ) {
		return;
	}
	$it = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $real, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ( $it as $f ) {
		if ( $f->isDir() ) {
			@rmdir( $f->getPathname() );
		} else {
			@unlink( $f->getPathname() );
		}
	}
	@rmdir( $real );
}

/** Calea fișierului pentru o pagină (p), miniatură (m) sau copertă (c). */
function p45cd_cale_imagine( $carte_id, $tip, $n = 0, $token = null, $ext = null ) {
	$dir = p45cd_dir_carte( $carte_id, $token );
	if ( ! $dir ) {
		return '';
	}
	if ( 'c' === $tip ) {
		foreach ( array( 'webp', 'jpg', 'png' ) as $e ) {
			if ( file_exists( $dir . '/coperta.' . $e ) ) {
				return $dir . '/coperta.' . $e;
			}
		}
		return '';
	}
	if ( null === $ext ) {
		$ext = get_post_meta( $carte_id, '_p45_ext', true );
	}
	$ext = in_array( $ext, array( 'webp', 'jpg' ), true ) ? $ext : 'webp';
	$sub = ( 'm' === $tip ) ? 'mini' : 'pagini';
	return sprintf( '%s/%s/%03d.%s', $dir, $sub, (int) $n, $ext );
}

/** URL protejat pentru o imagine a cărții. */
function p45cd_url_imagine( $carte_id, $tip, $n = 0 ) {
	$token = (string) get_post_meta( $carte_id, '_p45_dir', true );
	$v     = substr( md5( $token . '|' . get_post_meta( $carte_id, '_p45_cover_v', true ) ), 0, 10 );
	$args  = array(
		'p45_img' => (int) $carte_id,
		'p45_t'   => $tip,
		'v'       => $v,
	);
	if ( 'c' !== $tip ) {
		$args['p45_n'] = (int) $n;
	}
	return add_query_arg( $args, home_url( '/' ) );
}

/** Mărimea totală a stocării, în octeți. */
function p45cd_marime_stocare() {
	$baza  = p45cd_dir_baza();
	$total = 0;
	if ( ! is_dir( $baza ) ) {
		return 0;
	}
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $baza, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $f ) {
		if ( $f->isFile() ) {
			$total += $f->getSize();
		}
	}
	return $total;
}

/* ------------------------------------------------------------------ */
/* Datele unei cărți                                                   */
/* ------------------------------------------------------------------ */

function p45cd_cuprins( $carte_id ) {
	$raw = get_post_meta( $carte_id, '_p45_toc', true );
	$toc = is_string( $raw ) ? json_decode( $raw, true ) : array();
	if ( ! is_array( $toc ) ) {
		return array();
	}
	$out = array();
	foreach ( $toc as $r ) {
		if ( ! is_array( $r ) || empty( $r['t'] ) ) {
			continue;
		}
		$out[] = array(
			't' => (string) $r['t'],
			's' => isset( $r['s'] ) ? (string) $r['s'] : '',
			'p' => max( 1, (int) ( isset( $r['p'] ) ? $r['p'] : 1 ) ),
		);
	}
	usort(
		$out,
		function ( $a, $b ) {
			return $a['p'] - $b['p'];
		}
	);
	return $out;
}

function p45cd_curata_cuprins( $toc, $max_pagini = 0 ) {
	$out = array();
	if ( ! is_array( $toc ) ) {
		return $out;
	}
	foreach ( $toc as $r ) {
		if ( ! is_array( $r ) ) {
			continue;
		}
		$t = isset( $r['t'] ) ? sanitize_text_field( wp_unslash( $r['t'] ) ) : '';
		if ( '' === $t ) {
			continue;
		}
		$p = isset( $r['p'] ) ? (int) $r['p'] : 1;
		if ( $max_pagini > 0 && $p > $max_pagini ) {
			continue; /* rând care trimite dincolo de ultima pagină */
		}
		$out[] = array(
			't' => mb_substr( $t, 0, 200 ),
			's' => isset( $r['s'] ) ? mb_substr( sanitize_text_field( wp_unslash( $r['s'] ) ), 0, 300 ) : '',
			'p' => max( 1, $p ),
		);
		if ( count( $out ) >= 500 ) {
			break;
		}
	}
	return $out;
}

/** Toate datele unei cărți, gata de folosit în pagini. */
function p45cd_carte( $post ) {
	$post = get_post( $post );
	if ( ! $post || 'p45_carte' !== $post->post_type ) {
		return null;
	}
	$id     = $post->ID;
	$clase  = wp_get_post_terms( $id, 'p45_clasa', array( 'fields' => 'all' ) );
	$disc   = wp_get_post_terms( $id, 'p45_disciplina', array( 'fields' => 'all' ) );
	$pagini = (int) get_post_meta( $id, '_p45_pages', true );
	$token  = (string) get_post_meta( $id, '_p45_dir', true );
	$are_c  = $token && p45cd_cale_imagine( $id, 'c' );
	return array(
		'id'         => $id,
		'titlu'      => get_the_title( $id ),
		'autor'      => (string) get_post_meta( $id, '_p45_autor', true ),
		'isbn'       => (string) get_post_meta( $id, '_p45_isbn', true ),
		'an'         => (string) get_post_meta( $id, '_p45_an', true ),
		'cod'        => (int) get_post_meta( $id, '_p45_cod', true ),
		'pagini'     => $pagini,
		'w'          => (int) get_post_meta( $id, '_p45_w', true ),
		'h'          => (int) get_post_meta( $id, '_p45_h', true ),
		'ext'        => (string) get_post_meta( $id, '_p45_ext', true ),
		'sursa'      => (string) get_post_meta( $id, '_p45_sursa', true ),
		'toc'        => p45cd_cuprins( $id ),
		'clase'      => is_wp_error( $clase ) ? array() : $clase,
		'discipline' => is_wp_error( $disc ) ? array() : $disc,
		'status'     => $post->post_status,
		'gata'       => $pagini > 0 && p45cd_token_valid( $token ),
		'coperta'    => $are_c ? p45cd_url_imagine( $id, 'c' ) : '',
		'url'        => p45cd_url( 'carte', array( 'id' => $id ) ),
		'url_tabla'  => p45cd_url( 'carte', array( 'id' => $id, 'tabla' => 1 ) ),
	);
}

function p45cd_nume_termeni( $termeni ) {
	return implode( ', ', wp_list_pluck( (array) $termeni, 'name' ) );
}

/** Formatare mărime fișier, în română. */
function p45cd_marime( $octeti ) {
	$octeti = (float) $octeti;
	if ( $octeti >= 1073741824 ) {
		return number_format_i18n( $octeti / 1073741824, 1 ) . ' GB';
	}
	if ( $octeti >= 1048576 ) {
		return number_format_i18n( $octeti / 1048576, 1 ) . ' MB';
	}
	return number_format_i18n( max( 1, $octeti / 1024 ), 0 ) . ' KB';
}

/** Dată în română, scurt (ex.: 17 noiembrie 2026). */
function p45cd_data( $timestamp ) {
	if ( ! $timestamp ) {
		return '';
	}
	$luni = array( 1 => 'ianuarie', 'februarie', 'martie', 'aprilie', 'mai', 'iunie', 'iulie', 'august', 'septembrie', 'octombrie', 'noiembrie', 'decembrie' );
	$t    = (int) $timestamp + (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
	return gmdate( 'j', $t ) . ' ' . $luni[ (int) gmdate( 'n', $t ) ] . ' ' . gmdate( 'Y', $t );
}

function p45cd_data_ora( $timestamp ) {
	if ( ! $timestamp ) {
		return '';
	}
	$t = (int) $timestamp + (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
	return p45cd_data( $timestamp ) . ', ' . gmdate( 'H:i', $t );
}

/** Inițialele unui nume, pentru avatar. */
function p45cd_initiale( $nume ) {
	$parti = preg_split( '/\s+/', trim( (string) $nume ) );
	$i     = '';
	foreach ( array_slice( $parti, 0, 2 ) as $p ) {
		$i .= mb_strtoupper( mb_substr( $p, 0, 1 ) );
	}
	return $i ? $i : '?';
}

/* ------------------------------------------------------------------ */
/* Codul cărții în CRM (numărul manualului din platforma veche)        */
/* ------------------------------------------------------------------ */

/** Cartea care are codul dat sau 0. */
function p45cd_carte_dupa_cod( $cod ) {
	$cod = (int) $cod;
	if ( $cod < 1 ) {
		return 0;
	}
	$ids = get_posts(
		array(
			'post_type'      => 'p45_carte',
			'post_status'    => 'any',
			'fields'         => 'ids',
			'posts_per_page' => 1,
			'meta_key'       => '_p45_cod',
			'meta_value'     => (string) $cod,
		)
	);
	return $ids ? (int) $ids[0] : 0;
}

/** Toate perechile cod => id carte. */
function p45cd_harta_coduri() {
	global $wpdb;
	$rez = array();
	$rows = $wpdb->get_results(
		"SELECT pm.post_id, pm.meta_value FROM {$wpdb->postmeta} pm
		 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id AND p.post_type = 'p45_carte' AND p.post_status <> 'trash'
		 WHERE pm.meta_key = '_p45_cod'"
	);
	foreach ( $rows as $r ) {
		if ( (int) $r->meta_value > 0 ) {
			$rez[ (int) $r->meta_value ] = (int) $r->post_id;
		}
	}
	return $rez;
}

