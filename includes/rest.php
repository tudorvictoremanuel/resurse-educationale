<?php
/**
 * API-ul folosit de convertorul din admin și de cititor.
 * Toate rutele cer cookie de autentificare + nonce wp_rest.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'rest_api_init', 'p45cd_rute_rest' );

function p45cd_rute_rest() {
	$ns    = 'p45/v1';
	$admin = function () {
		return current_user_can( 'p45_gestionare' );
	};

	register_rest_route( $ns, '/carti', array( 'methods' => 'POST', 'callback' => 'p45cd_rest_creeaza', 'permission_callback' => $admin ) );
	register_rest_route( $ns, '/carti/(?P<id>\d+)', array( 'methods' => 'POST', 'callback' => 'p45cd_rest_actualizeaza', 'permission_callback' => $admin ) );
	register_rest_route( $ns, '/carti/(?P<id>\d+)', array( 'methods' => 'DELETE', 'callback' => 'p45cd_rest_sterge', 'permission_callback' => $admin ) );
	register_rest_route( $ns, '/carti/(?P<id>\d+)/conversie', array( 'methods' => 'POST', 'callback' => 'p45cd_rest_conversie', 'permission_callback' => $admin ) );
	register_rest_route( $ns, '/carti/(?P<id>\d+)/pagina', array( 'methods' => 'POST', 'callback' => 'p45cd_rest_pagina', 'permission_callback' => $admin ) );
	register_rest_route( $ns, '/carti/(?P<id>\d+)/coperta', array( 'methods' => 'POST', 'callback' => 'p45cd_rest_coperta', 'permission_callback' => $admin ) );
	register_rest_route( $ns, '/carti/(?P<id>\d+)/finalizare', array( 'methods' => 'POST', 'callback' => 'p45cd_rest_finalizare', 'permission_callback' => $admin ) );

	register_rest_route(
		$ns,
		'/stare/(?P<id>\d+)',
		array(
			array(
				'methods'             => 'GET',
				'callback'            => 'p45cd_rest_stare_get',
				'permission_callback' => 'p45cd_rest_poate_citi',
			),
			array(
				'methods'             => 'POST',
				'callback'            => 'p45cd_rest_stare_post',
				'permission_callback' => 'p45cd_rest_poate_citi',
			),
		)
	);
}

function p45cd_rest_poate_citi( $req ) {
	return is_user_logged_in() && p45cd_poate_citi( get_current_user_id(), (int) $req['id'] );
}

function p45cd_rest_carte_sau_eroare( $id ) {
	$post = get_post( (int) $id );
	if ( ! $post || 'p45_carte' !== $post->post_type ) {
		return new WP_Error( 'p45_lipsa', 'Cartea nu există.', array( 'status' => 404 ) );
	}
	return $post;
}

/** Salvează detaliile cărții din cerere (titlu, autor, clase, cuprins…). */
function p45cd_salveaza_detalii( $id, $req ) {
	$p = $req->get_json_params();
	if ( ! is_array( $p ) || ! $p ) {
		$p = $req->get_body_params();
	}
	$upd = array( 'ID' => $id );
	if ( isset( $p['titlu'] ) ) {
		$t                 = sanitize_text_field( $p['titlu'] );
		$upd['post_title'] = '' !== $t ? $t : 'Carte fără titlu';
	}
	if ( isset( $p['status'] ) && in_array( $p['status'], array( 'publish', 'draft' ), true ) ) {
		$pagini = (int) get_post_meta( $id, '_p45_pages', true );
		$upd['post_status'] = ( 'publish' === $p['status'] && $pagini < 1 ) ? 'draft' : $p['status'];
	}
	if ( count( $upd ) > 1 ) {
		wp_update_post( $upd );
	}
	foreach ( array( 'autor' => '_p45_autor', 'isbn' => '_p45_isbn', 'an' => '_p45_an' ) as $k => $meta ) {
		if ( isset( $p[ $k ] ) ) {
			update_post_meta( $id, $meta, mb_substr( sanitize_text_field( $p[ $k ] ), 0, 200 ) );
		}
	}
	if ( isset( $p['clase'] ) ) {
		wp_set_object_terms( $id, array_values( array_filter( array_map( 'intval', (array) $p['clase'] ) ) ), 'p45_clasa' );
	}
	if ( isset( $p['discipline'] ) ) {
		wp_set_object_terms( $id, array_values( array_filter( array_map( 'intval', (array) $p['discipline'] ) ) ), 'p45_disciplina' );
	}
	if ( isset( $p['toc'] ) ) {
		$max = (int) get_post_meta( $id, '_p45_pages', true );
		if ( isset( $p['pagini_pdf'] ) ) {
			$max = max( $max, (int) $p['pagini_pdf'] );
		}
		update_post_meta( $id, '_p45_toc', wp_slash( wp_json_encode( p45cd_curata_cuprins( (array) $p['toc'], $max ) ) ) );
	}
	if ( isset( $p['cod'] ) ) {
		$cod = (int) $p['cod'];
		if ( $cod > 0 ) {
			update_post_meta( $id, '_p45_cod', $cod );
		} else {
			delete_post_meta( $id, '_p45_cod' );
		}
	}
	if ( isset( $p['sursa'] ) ) {
		update_post_meta( $id, '_p45_sursa', mb_substr( sanitize_file_name( $p['sursa'] ), 0, 200 ) );
	}
	if ( isset( $p['sursa_marime'] ) ) {
		update_post_meta( $id, '_p45_sursa_marime', (int) $p['sursa_marime'] );
	}
}

function p45cd_rest_raspuns_carte( $id, $extra = array() ) {
	$c = p45cd_carte( $id );
	return rest_ensure_response(
		array_merge(
			array(
				'id'        => $id,
				'titlu'     => $c['titlu'],
				'status'    => $c['status'],
				'pagini'    => $c['pagini'],
				'url'       => $c['url'],
				'url_admin' => admin_url( 'admin.php?page=p45-carte&id=' . $id ),
				'url_acces' => admin_url( 'admin.php?page=p45-profesori&carte=' . $id ),
			),
			$extra
		)
	);
}

/** Pornește o conversie: dosar nou cu token, șters orice conversie neterminată. */
function p45cd_porneste_conversie( $id ) {
	$vechi = get_post_meta( $id, '_p45_conv', true );
	if ( $vechi && p45cd_token_valid( $vechi ) && $vechi !== get_post_meta( $id, '_p45_dir', true ) ) {
		p45cd_sterge_dir( p45cd_dir_carte( $id, $vechi ) );
	}
	$token = p45cd_token_nou();
	if ( ! p45cd_creeaza_dir_carte( $id, $token ) ) {
		return new WP_Error( 'p45_dir', 'Nu am putut crea dosarul pentru pagini. Verifică permisiunile dosarului wp-content/uploads.', array( 'status' => 500 ) );
	}
	update_post_meta( $id, '_p45_conv', $token );
	return $token;
}

/** Codul CRM trebuie să fie unic: altfel CRM-ul ar da acces la altă carte. */
function p45cd_rest_cod_ocupat( $req, $id = 0 ) {
	$p = $req->get_json_params();
	if ( ! is_array( $p ) || ! isset( $p['cod'] ) || (int) $p['cod'] < 1 ) {
		return null;
	}
	$alt = p45cd_carte_dupa_cod( (int) $p['cod'] );
	if ( $alt && $alt !== (int) $id ) {
		return new WP_Error( 'p45_cod', 'Codul CRM ' . (int) $p['cod'] . ' e folosit deja la „' . get_the_title( $alt ) . '”. Alege alt cod.', array( 'status' => 400 ) );
	}
	return null;
}

function p45cd_rest_creeaza( $req ) {
	$ocupat = p45cd_rest_cod_ocupat( $req );
	if ( $ocupat ) {
		return $ocupat;
	}
	$id = wp_insert_post(
		array(
			'post_type'   => 'p45_carte',
			'post_status' => 'draft',
			'post_title'  => 'Carte nouă',
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	p45cd_salveaza_detalii( $id, $req );
	$token = p45cd_porneste_conversie( $id );
	if ( is_wp_error( $token ) ) {
		return $token;
	}
	return p45cd_rest_raspuns_carte( $id, array( 'token' => $token ) );
}

function p45cd_rest_actualizeaza( $req ) {
	$post = p45cd_rest_carte_sau_eroare( $req['id'] );
	if ( is_wp_error( $post ) ) {
		return $post;
	}
	$ocupat = p45cd_rest_cod_ocupat( $req, $post->ID );
	if ( $ocupat ) {
		return $ocupat;
	}
	p45cd_salveaza_detalii( $post->ID, $req );
	return p45cd_rest_raspuns_carte( $post->ID );
}

function p45cd_rest_conversie( $req ) {
	$post = p45cd_rest_carte_sau_eroare( $req['id'] );
	if ( is_wp_error( $post ) ) {
		return $post;
	}
	p45cd_salveaza_detalii( $post->ID, $req );
	$token = p45cd_porneste_conversie( $post->ID );
	if ( is_wp_error( $token ) ) {
		return $token;
	}
	return p45cd_rest_raspuns_carte( $post->ID, array( 'token' => $token ) );
}

/** Verifică și mută o imagine încărcată. Întoarce extensia sau WP_Error. */
function p45cd_muta_imagine( $fisier, $dest_fara_ext, $max_octeti = 12582912 ) {
	if ( ! is_array( $fisier ) || ! isset( $fisier['tmp_name'] ) || UPLOAD_ERR_OK !== (int) $fisier['error'] ) {
		return new WP_Error( 'p45_upload', 'Imaginea nu a ajuns pe server.', array( 'status' => 400 ) );
	}
	if ( (int) $fisier['size'] <= 0 || (int) $fisier['size'] > $max_octeti ) {
		return new WP_Error( 'p45_upload', 'Imaginea e prea mare.', array( 'status' => 413 ) );
	}
	$info = @getimagesize( $fisier['tmp_name'] );
	$tip  = $info && isset( $info['mime'] ) ? $info['mime'] : '';
	$map  = array(
		'image/webp' => 'webp',
		'image/jpeg' => 'jpg',
		'image/png'  => 'png',
	);
	if ( ! isset( $map[ $tip ] ) ) {
		return new WP_Error( 'p45_upload', 'Fișierul nu e o imagine acceptată (WebP, JPG sau PNG).', array( 'status' => 415 ) );
	}
	$ext  = $map[ $tip ];
	$dest = $dest_fara_ext . '.' . $ext;
	if ( ! @move_uploaded_file( $fisier['tmp_name'], $dest ) ) {
		if ( ! @rename( $fisier['tmp_name'], $dest ) ) {
			return new WP_Error( 'p45_upload', 'Nu am putut salva imaginea pe server.', array( 'status' => 500 ) );
		}
	}
	@chmod( $dest, 0644 );
	return $ext;
}

function p45cd_rest_pagina( $req ) {
	$post = p45cd_rest_carte_sau_eroare( $req['id'] );
	if ( is_wp_error( $post ) ) {
		return $post;
	}
	$token = (string) $req->get_param( 'token' );
	if ( ! p45cd_token_valid( $token ) || get_post_meta( $post->ID, '_p45_conv', true ) !== $token ) {
		return new WP_Error( 'p45_token', 'Conversia nu mai e valabilă. Pornește-o din nou.', array( 'status' => 409 ) );
	}
	$n = (int) $req->get_param( 'n' );
	if ( $n < 1 || $n > 2000 ) {
		return new WP_Error( 'p45_n', 'Număr de pagină greșit.', array( 'status' => 400 ) );
	}
	$dir   = p45cd_dir_carte( $post->ID, $token );
	$files = $req->get_file_params();
	foreach ( array( 'pagina' => 'pagini', 'mini' => 'mini' ) as $camp => $sub ) {
		if ( empty( $files[ $camp ] ) ) {
			return new WP_Error( 'p45_upload', 'Lipsește imaginea paginii.', array( 'status' => 400 ) );
		}
		foreach ( array( 'webp', 'jpg', 'png' ) as $e ) {
			$vechi = sprintf( '%s/%s/%03d.%s', $dir, $sub, $n, $e );
			if ( file_exists( $vechi ) ) {
				@unlink( $vechi );
			}
		}
		$ext = p45cd_muta_imagine( $files[ $camp ], sprintf( '%s/%s/%03d', $dir, $sub, $n ) );
		if ( is_wp_error( $ext ) ) {
			return $ext;
		}
	}
	return rest_ensure_response( array( 'ok' => true, 'n' => $n ) );
}

function p45cd_rest_coperta( $req ) {
	$post = p45cd_rest_carte_sau_eroare( $req['id'] );
	if ( is_wp_error( $post ) ) {
		return $post;
	}
	$token = (string) $req->get_param( 'token' );
	if ( $token ) {
		if ( ! p45cd_token_valid( $token ) || get_post_meta( $post->ID, '_p45_conv', true ) !== $token ) {
			return new WP_Error( 'p45_token', 'Conversia nu mai e valabilă. Pornește-o din nou.', array( 'status' => 409 ) );
		}
	} else {
		$token = (string) get_post_meta( $post->ID, '_p45_dir', true );
		if ( ! p45cd_token_valid( $token ) ) {
			return new WP_Error( 'p45_token', 'Cartea nu are încă pagini. Convertește întâi PDF-ul.', array( 'status' => 409 ) );
		}
	}
	$dir   = p45cd_dir_carte( $post->ID, $token );
	$files = $req->get_file_params();
	if ( empty( $files['coperta'] ) ) {
		return new WP_Error( 'p45_upload', 'Lipsește imaginea copertei.', array( 'status' => 400 ) );
	}
	foreach ( array( 'webp', 'jpg', 'png' ) as $e ) {
		if ( file_exists( $dir . '/coperta.' . $e ) ) {
			@unlink( $dir . '/coperta.' . $e );
		}
	}
	$ext = p45cd_muta_imagine( $files['coperta'], $dir . '/coperta', 8388608 );
	if ( is_wp_error( $ext ) ) {
		return $ext;
	}
	/* Copertele foarte mari se micșorează la 800 px lățime. */
	$cale = $dir . '/coperta.' . $ext;
	$info = @getimagesize( $cale );
	if ( $info && $info[0] > 1000 ) {
		$ed = wp_get_image_editor( $cale );
		if ( ! is_wp_error( $ed ) ) {
			$ed->resize( 800, null );
			$ed->save( $cale );
		}
	}
	update_post_meta( $post->ID, '_p45_cover_v', time() );
	return rest_ensure_response( array( 'ok' => true, 'coperta' => p45cd_url_imagine( $post->ID, 'c' ) ) );
}

function p45cd_rest_finalizare( $req ) {
	$post = p45cd_rest_carte_sau_eroare( $req['id'] );
	if ( is_wp_error( $post ) ) {
		return $post;
	}
	$id    = $post->ID;
	$token = (string) $req->get_param( 'token' );
	if ( ! p45cd_token_valid( $token ) || get_post_meta( $id, '_p45_conv', true ) !== $token ) {
		return new WP_Error( 'p45_token', 'Conversia nu mai e valabilă. Pornește-o din nou.', array( 'status' => 409 ) );
	}
	$pagini = (int) $req->get_param( 'pagini' );
	$ext    = (string) $req->get_param( 'ext' );
	$ext    = in_array( $ext, array( 'webp', 'jpg' ), true ) ? $ext : 'webp';
	$dir    = p45cd_dir_carte( $id, $token );
	if ( $pagini < 1 ) {
		return new WP_Error( 'p45_pagini', 'Nu a fost convertită nicio pagină.', array( 'status' => 400 ) );
	}
	for ( $n = 1; $n <= $pagini; $n++ ) {
		if ( ! file_exists( sprintf( '%s/pagini/%03d.%s', $dir, $n, $ext ) ) || ! file_exists( sprintf( '%s/mini/%03d.%s', $dir, $n, $ext ) ) ) {
			return new WP_Error( 'p45_lipsa_pagina', sprintf( 'Lipsește pagina %d. Pornește conversia din nou.', $n ), array( 'status' => 409 ) );
		}
	}

	/* Dacă nu s-a trimis o copertă nouă, o păstrăm pe cea veche. */
	$vechi_token = (string) get_post_meta( $id, '_p45_dir', true );
	$are_coperta = false;
	foreach ( array( 'webp', 'jpg', 'png' ) as $e ) {
		if ( file_exists( $dir . '/coperta.' . $e ) ) {
			$are_coperta = true;
		}
	}
	if ( ! $are_coperta && p45cd_token_valid( $vechi_token ) ) {
		$vc = p45cd_cale_imagine( $id, 'c', 0, $vechi_token );
		if ( $vc ) {
			@copy( $vc, $dir . '/' . basename( $vc ) );
		}
	}

	update_post_meta( $id, '_p45_dir', $token );
	update_post_meta( $id, '_p45_pages', $pagini );
	update_post_meta( $id, '_p45_ext', $ext );
	update_post_meta( $id, '_p45_w', max( 1, (int) $req->get_param( 'w' ) ) );
	update_post_meta( $id, '_p45_h', max( 1, (int) $req->get_param( 'h' ) ) );
	update_post_meta( $id, '_p45_cover_v', time() );
	delete_post_meta( $id, '_p45_conv' );

	/* Ștergem versiunile vechi ale paginilor acestei cărți. */
	foreach ( (array) glob( p45cd_dir_baza() . '/' . $id . '-*', GLOB_ONLYDIR ) as $d ) {
		if ( basename( $d ) !== $id . '-' . $token ) {
			p45cd_sterge_dir( $d );
		}
	}

	/* Cuprinsul nu poate trimite la pagini care nu există. */
	$toc = p45cd_cuprins( $id );
	update_post_meta( $id, '_p45_toc', wp_slash( wp_json_encode( p45cd_curata_cuprins( $toc, $pagini ) ) ) );

	if ( $req->get_param( 'publica' ) ) {
		wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
	}
	return p45cd_rest_raspuns_carte( $id );
}

function p45cd_rest_sterge( $req ) {
	$post = p45cd_rest_carte_sau_eroare( $req['id'] );
	if ( is_wp_error( $post ) ) {
		return $post;
	}
	p45cd_sterge_carte( $post->ID );
	return rest_ensure_response( array( 'ok' => true ) );
}

/** Șterge complet o carte: pagini, acces, date profesori, articol. */
function p45cd_sterge_carte( $id ) {
	foreach ( (array) glob( p45cd_dir_baza() . '/' . (int) $id . '-*', GLOB_ONLYDIR ) as $d ) {
		p45cd_sterge_dir( $d );
	}
	p45cd_sterge_date_carte( $id );
	wp_delete_post( $id, true );
}

/* ------------------------------------------------------------------ */
/* Datele profesorului în cititor                                      */
/* ------------------------------------------------------------------ */

function p45cd_rest_stare_get( $req ) {
	return rest_ensure_response( p45cd_stare( get_current_user_id(), (int) $req['id'] ) );
}

function p45cd_rest_stare_post( $req ) {
	$d = $req->get_json_params();
	if ( ! is_array( $d ) ) {
		return new WP_Error( 'p45_date', 'Date greșite.', array( 'status' => 400 ) );
	}
	$id  = (int) $req['id'];
	$res = p45cd_salveaza_stare( get_current_user_id(), $id, $d, (int) get_post_meta( $id, '_p45_pages', true ) );
	if ( is_wp_error( $res ) ) {
		$res->add_data( array( 'status' => 413 ) );
		return $res;
	}
	return rest_ensure_response( array( 'ok' => true ) );
}
