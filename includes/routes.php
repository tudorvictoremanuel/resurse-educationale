<?php
/**
 * Adresele platformei (/intra, /parola, /biblioteca, /carte/…, /iesire),
 * servirea protejată a paginilor și regulile de acces.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function p45cd_reguli_adrese() {
	add_rewrite_rule( '^intra/?$', 'index.php?p45_view=intra', 'top' );
	add_rewrite_rule( '^parola/?$', 'index.php?p45_view=parola', 'top' );
	add_rewrite_rule( '^biblioteca/?$', 'index.php?p45_view=biblioteca', 'top' );
	add_rewrite_rule( '^iesire/?$', 'index.php?p45_view=iesire', 'top' );
	add_rewrite_rule( '^carte/([0-9]+)(?:-[^/]*)?/?$', 'index.php?p45_view=carte&p45_id=$matches[1]', 'top' );
}
add_action( 'init', 'p45cd_reguli_adrese' );

add_filter(
	'query_vars',
	function ( $v ) {
		return array_merge( $v, array( 'p45_view', 'p45_id', 'p45_img', 'p45_t', 'p45_n' ) );
	}
);

/* Dacă regulile nu sunt încă salvate (de ex. după o actualizare), le salvăm o dată. */
add_action(
	'wp_loaded',
	function () {
		$rules = get_option( 'rewrite_rules' );
		if ( p45cd_permalinkuri() && is_array( $rules ) && ! isset( $rules['^biblioteca/?$'] ) ) {
			flush_rewrite_rules( false );
		}
	}
);

/* ------------------------------------------------------------------ */
/* Servirea imaginilor (pagini, miniaturi, coperte)                    */
/* ------------------------------------------------------------------ */

add_action(
	'parse_request',
	function ( $wp ) {
		if ( empty( $wp->query_vars['p45_img'] ) ) {
			return;
		}
		$id = (int) $wp->query_vars['p45_img'];
		$t  = isset( $wp->query_vars['p45_t'] ) ? (string) $wp->query_vars['p45_t'] : 'p';
		$n  = isset( $wp->query_vars['p45_n'] ) ? (int) $wp->query_vars['p45_n'] : 0;
		p45cd_serveste_imagine( $id, $t, $n );
		exit;
	},
	1
);

function p45cd_eroare_imagine( $cod ) {
	status_header( $cod );
	nocache_headers();
	header( 'Content-Type: text/plain; charset=utf-8' );
	echo 403 === $cod ? 'Acces interzis.' : 'Imaginea nu există.';
}

function p45cd_serveste_imagine( $id, $t, $n ) {
	if ( ! in_array( $t, array( 'p', 'm', 'c' ), true ) ) {
		p45cd_eroare_imagine( 404 );
		return;
	}
	$post = get_post( $id );
	if ( ! $post || 'p45_carte' !== $post->post_type ) {
		p45cd_eroare_imagine( 404 );
		return;
	}
	$uid   = get_current_user_id();
	$admin = $uid && p45cd_e_admin( $uid );

	if ( 'c' === $t ) {
		/* Copertele cărților publicate sunt publice (apar pe pagina de intrare). */
		if ( 'publish' !== $post->post_status && ! $admin ) {
			p45cd_eroare_imagine( 403 );
			return;
		}
		$cale = p45cd_cale_imagine( $id, 'c' );
	} else {
		if ( ! $uid || ! p45cd_poate_citi( $uid, $id ) ) {
			p45cd_eroare_imagine( 403 );
			return;
		}
		$pagini = (int) get_post_meta( $id, '_p45_pages', true );
		if ( $n < 1 || $n > $pagini ) {
			p45cd_eroare_imagine( 404 );
			return;
		}
		$cale = p45cd_cale_imagine( $id, $t, $n );
	}

	if ( ! $cale || ! is_file( $cale ) ) {
		p45cd_eroare_imagine( 404 );
		return;
	}

	$ext  = strtolower( pathinfo( $cale, PATHINFO_EXTENSION ) );
	$mime = array(
		'webp' => 'image/webp',
		'jpg'  => 'image/jpeg',
		'png'  => 'image/png',
	);
	$mt   = filemtime( $cale );
	$size = filesize( $cale );
	$etag = '"' . md5( $cale . '|' . $mt . '|' . $size ) . '"';

	if ( function_exists( 'session_write_close' ) ) {
		@session_write_close();
	}
	while ( ob_get_level() ) {
		ob_end_clean();
	}

	header_remove( 'Pragma' );
	header_remove( 'Expires' );
	header( 'Cache-Control: ' . ( 'c' === $t ? 'public, max-age=86400' : 'private, max-age=2592000' ) );
	header( 'ETag: ' . $etag );
	header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', $mt ) . ' GMT' );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'X-Robots-Tag: noindex, nofollow' );

	$inm = isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) ? trim( wp_unslash( $_SERVER['HTTP_IF_NONE_MATCH'] ) ) : '';
	if ( $inm && $inm === $etag ) {
		status_header( 304 );
		return;
	}
	status_header( 200 );
	header( 'Content-Type: ' . ( isset( $mime[ $ext ] ) ? $mime[ $ext ] : 'application/octet-stream' ) );
	header( 'Content-Length: ' . $size );
	header( 'Content-Disposition: inline' );
	readfile( $cale );
}

/* ------------------------------------------------------------------ */
/* Paginile platformei                                                 */
/* ------------------------------------------------------------------ */

add_action( 'template_redirect', 'p45cd_ruteaza', 0 );

function p45cd_ruteaza() {
	if ( is_robots() || ( function_exists( 'is_favicon' ) && is_favicon() ) ) {
		return;
	}
	$view = get_query_var( 'p45_view' );
	$uid  = get_current_user_id();

	if ( ! $view ) {
		/* Site-ul e doar platforma: orice altă pagină duce la bibliotecă sau la intrare. */
		wp_safe_redirect( $uid ? p45cd_url( 'biblioteca' ) : p45cd_url( 'intra' ) );
		exit;
	}

	nocache_headers();
	header( 'X-Robots-Tag: noindex, nofollow' );

	switch ( $view ) {
		case 'intra':
			if ( $uid ) {
				wp_safe_redirect( p45cd_url( 'biblioteca' ) );
				exit;
			}
			$eroare = p45cd_proceseaza_intrare();
			include P45CD_DIR . 'templates/intra.php';
			exit;

		case 'parola':
			$stare = p45cd_proceseaza_parola();
			include P45CD_DIR . 'templates/parola.php';
			exit;

		case 'iesire':
			wp_logout();
			wp_safe_redirect( p45cd_url( 'intra', array( 'iesit' => 1 ) ) );
			exit;

		case 'biblioteca':
			p45cd_cere_cont();
			include P45CD_DIR . 'templates/biblioteca.php';
			exit;

		case 'carte':
			p45cd_cere_cont();
			$carte_id = (int) get_query_var( 'p45_id' );
			$carte    = p45cd_carte( $carte_id );
			if ( ! $carte || ! p45cd_poate_citi( $uid, $carte_id ) || ! $carte['gata'] ) {
				status_header( $carte ? 403 : 404 );
				$mesaj_titlu = $carte ? 'Nu ai acces la această carte' : 'Cartea nu există';
				$mesaj_text  = $carte && ! $carte['gata'] && p45cd_e_admin()
					? 'Cartea nu are încă pagini. Termină conversia PDF-ului din panoul de administrare.'
					: 'Dacă ai cumpărat cartea și nu o vezi în bibliotecă, scrie-ne și o adăugăm în contul tău.';
				include P45CD_DIR . 'templates/mesaj.php';
				exit;
			}
			include P45CD_DIR . 'templates/cititor.php';
			exit;
	}

	wp_safe_redirect( $uid ? p45cd_url( 'biblioteca' ) : p45cd_url( 'intra' ) );
	exit;
}

function p45cd_cere_cont() {
	if ( is_user_logged_in() ) {
		return;
	}
	$aici = ( is_ssl() ? 'https://' : 'http://' ) . ( isset( $_SERVER['HTTP_HOST'] ) ? wp_unslash( $_SERVER['HTTP_HOST'] ) : '' ) . ( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '' );
	wp_safe_redirect( p45cd_url( 'intra', array( 'redirect_to' => rawurlencode( $aici ) ) ) );
	exit;
}

function p45cd_ip() {
	return isset( $_SERVER['REMOTE_ADDR'] ) ? preg_replace( '/[^0-9a-fA-F:.]/', '', wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0';
}

/** Formularul de intrare. Întoarce mesajul de eroare (sau ''). */
function p45cd_proceseaza_intrare() {
	if ( 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) ) {
		return '';
	}
	if ( ! isset( $_POST['p45_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['p45_nonce'] ) ), 'p45_intra' ) ) {
		return 'Sesiunea a expirat. Reîncarcă pagina și încearcă din nou.';
	}
	$cheie    = 'p45_inc_' . md5( p45cd_ip() );
	$incercari = (int) get_transient( $cheie );
	if ( $incercari >= 8 ) {
		return 'Prea multe încercări greșite. Încearcă din nou peste 15 minute.';
	}
	$email = isset( $_POST['email'] ) ? sanitize_text_field( wp_unslash( $_POST['email'] ) ) : '';
	$pass  = isset( $_POST['parola'] ) ? (string) wp_unslash( $_POST['parola'] ) : '';
	if ( '' === $email || '' === $pass ) {
		return 'Scrie emailul și parola.';
	}
	$user = wp_signon(
		array(
			'user_login'    => $email,
			'user_password' => $pass,
			'remember'      => ! empty( $_POST['tine_minte'] ),
		),
		is_ssl()
	);
	if ( is_wp_error( $user ) ) {
		set_transient( $cheie, $incercari + 1, 15 * MINUTE_IN_SECONDS );
		return 'Emailul sau parola nu sunt corecte.';
	}
	delete_transient( $cheie );
	$dest = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : '';
	$dest = wp_validate_redirect( $dest, p45cd_url( 'biblioteca' ) );
	if ( ! $dest || false !== strpos( $dest, '/intra' ) ) {
		$dest = p45cd_url( 'biblioteca' );
	}
	wp_safe_redirect( $dest );
	exit;
}

/**
 * Pagina /parola: cerere de link nou sau setarea parolei cu linkul din email.
 * Întoarce starea pentru șablon.
 */
function p45cd_proceseaza_parola() {
	$stare = array(
		'mod'    => 'cere',
		'mesaj'  => '',
		'eroare' => '',
		'key'    => '',
		'login'  => '',
	);
	$metoda = isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : 'GET';
	$key    = isset( $_REQUEST['key'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['key'] ) ) : '';
	$login  = isset( $_REQUEST['login'] ) ? sanitize_user( rawurldecode( wp_unslash( $_REQUEST['login'] ) ) ) : '';

	if ( $key && $login ) {
		$user = check_password_reset_key( $key, $login );
		if ( is_wp_error( $user ) ) {
			$stare['eroare'] = 'Linkul a expirat sau a fost deja folosit. Cere mai jos un link nou.';
			return $stare;
		}
		$stare['mod']   = 'seteaza';
		$stare['key']   = $key;
		$stare['login'] = $login;
		$stare['email'] = $user->user_email;

		if ( 'POST' === $metoda ) {
			if ( ! isset( $_POST['p45_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['p45_nonce'] ) ), 'p45_parola' ) ) {
				$stare['eroare'] = 'Sesiunea a expirat. Reîncarcă pagina și încearcă din nou.';
				return $stare;
			}
			$p1 = isset( $_POST['parola1'] ) ? (string) wp_unslash( $_POST['parola1'] ) : '';
			$p2 = isset( $_POST['parola2'] ) ? (string) wp_unslash( $_POST['parola2'] ) : '';
			if ( mb_strlen( $p1 ) < 8 ) {
				$stare['eroare'] = 'Parola trebuie să aibă cel puțin 8 caractere.';
				return $stare;
			}
			if ( $p1 !== $p2 ) {
				$stare['eroare'] = 'Cele două parole nu sunt la fel.';
				return $stare;
			}
			reset_password( $user, $p1 );
			wp_set_current_user( $user->ID );
			wp_set_auth_cookie( $user->ID, true, is_ssl() );
			update_user_meta( $user->ID, 'p45_ultima_intrare', time() );
			if ( ! get_user_meta( $user->ID, 'p45_activat', true ) ) {
				update_user_meta( $user->ID, 'p45_activat', time() );
			}
			wp_safe_redirect( p45cd_url( 'biblioteca', array( 'bun_venit' => 1 ) ) );
			exit;
		}
		return $stare;
	}

	if ( 'POST' === $metoda ) {
		if ( ! isset( $_POST['p45_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['p45_nonce'] ) ), 'p45_parola_cere' ) ) {
			$stare['eroare'] = 'Sesiunea a expirat. Reîncarcă pagina și încearcă din nou.';
			return $stare;
		}
		$cheie = 'p45_res_' . md5( p45cd_ip() );
		$nr    = (int) get_transient( $cheie );
		if ( $nr >= 6 ) {
			$stare['eroare'] = 'Ai cerut prea multe linkuri. Încearcă din nou peste o oră.';
			return $stare;
		}
		set_transient( $cheie, $nr + 1, HOUR_IN_SECONDS );
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$user  = $email ? get_user_by( 'email', $email ) : false;
		if ( $user ) {
			p45cd_email_resetare( $user );
		}
		$stare['mod']   = 'trimis';
		$stare['mesaj'] = 'Dacă adresa există în platformă, ți-am trimis un email cu linkul pentru parola nouă. Verifică și dosarul Spam.';
	}
	return $stare;
}

/* ------------------------------------------------------------------ */
/* Reguli pentru conturi                                               */
/* ------------------------------------------------------------------ */

/* Profesorii nu intră în wp-admin. */
add_action(
	'admin_init',
	function () {
		if ( wp_doing_ajax() || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
			return;
		}
		if ( is_user_logged_in() && ! current_user_can( 'p45_gestionare' ) && ! current_user_can( 'edit_posts' ) ) {
			wp_safe_redirect( p45cd_url( 'biblioteca' ) );
			exit;
		}
	}
);

add_filter(
	'show_admin_bar',
	function ( $show ) {
		return current_user_can( 'p45_gestionare' ) ? $show : false;
	}
);

/* Intrarea și resetarea parolei trec prin paginile platformei. */
add_filter(
	'login_url',
	function ( $url, $redirect ) {
		return p45cd_url( 'intra', $redirect ? array( 'redirect_to' => rawurlencode( $redirect ) ) : array() );
	},
	10,
	2
);

add_filter(
	'lostpassword_url',
	function () {
		return p45cd_url( 'parola' );
	}
);

add_filter(
	'login_redirect',
	function ( $redirect, $requested, $user ) {
		if ( $user instanceof WP_User && ! user_can( $user, 'p45_gestionare' ) && ! user_can( $user, 'edit_posts' ) ) {
			return p45cd_url( 'biblioteca' );
		}
		return $redirect;
	},
	10,
	3
);

add_action(
	'wp_login',
	function ( $login, $user ) {
		update_user_meta( $user->ID, 'p45_ultima_intrare', time() );
		if ( ! get_user_meta( $user->ID, 'p45_activat', true ) ) {
			update_user_meta( $user->ID, 'p45_activat', time() );
		}
	},
	10,
	2
);

/* Nu trimitem administratorului email la fiecare parolă schimbată de un profesor. */
remove_action( 'after_password_reset', 'wp_password_change_notification' );
