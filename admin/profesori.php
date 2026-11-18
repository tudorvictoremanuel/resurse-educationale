<?php
/**
 * Admin: profesori și acces din comandă (inclusiv import CSV).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ------------------------------------------------------------------ */
/* Logica: creează contul (dacă nu există) și dă acces la cărți        */
/* ------------------------------------------------------------------ */

/**
 * @return array { ok, mesaj, nou, user }
 */
function p45cd_acorda( $email, $nume, $scoala, $comanda, $carti, $expira, $trimite ) {
	$brut  = trim( wp_strip_all_tags( (string) $email ) );
	$email = sanitize_email( $brut );
	if ( ! is_email( $email ) ) {
		return array( 'ok' => false, 'mesaj' => 'Emailul „' . mb_substr( $brut, 0, 80 ) . '” nu e valid.' );
	}
	$carti = array_values(
		array_filter(
			array_unique( array_map( 'intval', (array) $carti ) ),
			function ( $cid ) {
				$p = get_post( $cid );
				return $p && 'p45_carte' === $p->post_type;
			}
		)
	);
	if ( ! $carti ) {
		return array( 'ok' => false, 'mesaj' => 'Alege cel puțin o carte pentru ' . $email . '.' );
	}
	$nume   = trim( sanitize_text_field( $nume ) );
	$scoala = trim( sanitize_text_field( $scoala ) );
	$user   = get_user_by( 'email', $email );
	$nou    = false;

	if ( ! $user ) {
		$login = mb_substr( sanitize_user( $email, true ), 0, 60 );
		if ( ! $login || username_exists( $login ) ) {
			$login = 'profesor' . wp_rand( 100000, 999999 );
		}
		$parti = preg_split( '/\s+/', $nume, 2 );
		$uid   = wp_insert_user(
			array(
				'user_login'   => $login,
				'user_email'   => $email,
				'user_pass'    => wp_generate_password( 32, true, true ),
				'display_name' => $nume ? $nume : $email,
				'first_name'   => $nume ? $parti[0] : '',
				'last_name'    => ( $nume && isset( $parti[1] ) ) ? $parti[1] : '',
				'role'         => 'p45_profesor',
			)
		);
		if ( is_wp_error( $uid ) ) {
			return array( 'ok' => false, 'mesaj' => 'Nu am putut crea contul pentru ' . $email . ': ' . $uid->get_error_message() );
		}
		$user = get_user_by( 'id', $uid );
		$nou  = true;
	} elseif ( $nume && ( ! $user->display_name || $user->display_name === $user->user_email || $user->display_name === $user->user_login ) ) {
		$parti = preg_split( '/\s+/', $nume, 2 );
		wp_update_user( array( 'ID' => $user->ID, 'display_name' => $nume, 'first_name' => $parti[0], 'last_name' => isset( $parti[1] ) ? $parti[1] : '' ) );
		$user = get_user_by( 'id', $user->ID );
	}
	if ( $scoala ) {
		update_user_meta( $user->ID, 'p45_scoala', $scoala );
	}

	$adaugate = array();
	foreach ( $carti as $cid ) {
		if ( 'nou' === p45cd_da_acces( $user->ID, $cid, $comanda, $expira ) ) {
			$adaugate[] = $cid;
		}
	}

	$email_trimis = false;
	if ( $trimite ) {
		$niciodata = ! get_user_meta( $user->ID, 'p45_activat', true ) && ! get_user_meta( $user->ID, 'p45_ultima_intrare', true );
		if ( $nou || ( $niciodata && ! p45cd_e_admin( $user->ID ) ) ) {
			$toate        = wp_list_pluck( p45cd_acces_utilizator( $user->ID ), 'carte_id' );
			$email_trimis = p45cd_email_invitatie( $user, $toate, $comanda );
		} elseif ( $adaugate ) {
			$email_trimis = p45cd_email_carti_noi( $user, $adaugate, $comanda );
		}
	}

	$n     = count( $carti );
	$mesaj = ( $nou ? 'Cont nou pentru ' : 'Acces actualizat pentru ' ) . $email . ': ' . $n . ( 1 === $n ? ' carte' : ' cărți' );
	if ( $trimite ) {
		$mesaj .= $email_trimis ? ', email trimis.' : ( ( $nou || $adaugate ) ? ', dar emailul NU a putut fi trimis (verifică setările de email ale site-ului).' : ', nicio carte nouă, deci fără email.' );
	} else {
		$mesaj .= ', fără email.';
	}
	return array( 'ok' => true, 'mesaj' => $mesaj, 'nou' => $nou, 'user' => $user );
}

function p45cd_expira_din_post() {
	if ( isset( $_POST['valabil'] ) && 'pana' === $_POST['valabil'] && ! empty( $_POST['expira'] ) ) { // phpcs:ignore
		$d = sanitize_text_field( wp_unslash( $_POST['expira'] ) ); // phpcs:ignore
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ? $d : null;
	}
	return null;
}

function p45cd_sfarsit_an_scolar() {
	$an = (int) current_time( 'Y' );
	if ( current_time( 'Y-m-d' ) > $an . '-08-31' ) {
		$an++;
	}
	return $an . '-08-31';
}

/* ------------------------------------------------------------------ */
/* Acțiuni                                                             */
/* ------------------------------------------------------------------ */

add_action(
	'admin_post_p45_acces_nou',
	function () {
		p45cd_verifica_admin( 'p45_acces_nou' );
		$r = p45cd_acorda(
			isset( $_POST['email'] ) ? wp_unslash( $_POST['email'] ) : '',
			isset( $_POST['nume'] ) ? wp_unslash( $_POST['nume'] ) : '',
			isset( $_POST['scoala'] ) ? wp_unslash( $_POST['scoala'] ) : '',
			isset( $_POST['comanda'] ) ? sanitize_text_field( wp_unslash( $_POST['comanda'] ) ) : '',
			isset( $_POST['carti'] ) ? (array) $_POST['carti'] : array(),
			p45cd_expira_din_post(),
			! empty( $_POST['trimite'] )
		);
		p45cd_mesaj_admin( $r['ok'] ? 'ok' : 'eroare', $r['mesaj'] );
		$inapoi = ! empty( $_POST['inapoi_user'] ) ? admin_url( 'admin.php?page=p45-profesori&user=' . (int) $_POST['inapoi_user'] ) : admin_url( 'admin.php?page=p45-profesori' );
		wp_safe_redirect( $inapoi );
		exit;
	}
);

add_action(
	'admin_post_p45_import',
	function () {
		p45cd_verifica_admin( 'p45_import' );
		$inapoi = admin_url( 'admin.php?page=p45-profesori' );
		if ( empty( $_FILES['fisier']['tmp_name'] ) || ! is_uploaded_file( $_FILES['fisier']['tmp_name'] ) ) { // phpcs:ignore
			p45cd_mesaj_admin( 'eroare', 'Alege un fișier CSV.' );
			wp_safe_redirect( $inapoi );
			exit;
		}
		$text = file_get_contents( $_FILES['fisier']['tmp_name'] ); // phpcs:ignore
		$text = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $text );
		if ( ! mb_check_encoding( $text, 'UTF-8' ) ) {
			$text = mb_convert_encoding( $text, 'UTF-8', 'Windows-1250' );
		}
		$linii = preg_split( '/\r\n|\r|\n/', trim( $text ) );
		$sep   = ( substr_count( $linii[0], ';' ) > substr_count( $linii[0], ',' ) ) ? ';' : ',';
		$trimite = ! empty( $_POST['trimite'] );
		$expira  = p45cd_expira_din_post();

		/* Cărțile, ca să le găsim după ISBN, titlu sau ID. */
		$dupa_isbn  = array();
		$dupa_titlu = array();
		foreach ( get_posts( array( 'post_type' => 'p45_carte', 'post_status' => array( 'publish', 'draft' ), 'posts_per_page' => -1 ) ) as $p ) {
			$isbn = preg_replace( '/[^0-9Xx]/', '', (string) get_post_meta( $p->ID, '_p45_isbn', true ) );
			if ( $isbn ) {
				$dupa_isbn[ strtoupper( $isbn ) ] = $p->ID;
			}
			$dupa_titlu[ mb_strtolower( trim( $p->post_title ) ) ] = $p->ID;
		}

		$col     = array( 'email' => 0, 'nume' => 1, 'scoala' => 2, 'comanda' => 3, 'carti' => 4 );
		$rezumat = array();
		$ok      = 0;
		$erori   = 0;
		foreach ( $linii as $i => $linie ) {
			if ( '' === trim( $linie ) ) {
				continue;
			}
			$f = array_map( 'trim', str_getcsv( $linie, $sep ) );
			if ( 0 === $i && ! is_email( isset( $f[0] ) ? $f[0] : '' ) ) {
				/* Rând de antet: citim ordinea coloanelor. */
				foreach ( $f as $k => $h ) {
					$h = mb_strtolower( $h );
					if ( false !== strpos( $h, 'mail' ) ) {
						$col['email'] = $k;
					} elseif ( false !== strpos( $h, 'nume' ) ) {
						$col['nume'] = $k;
					} elseif ( false !== strpos( $h, 'coal' ) ) {
						$col['scoala'] = $k;
					} elseif ( false !== strpos( $h, 'comand' ) ) {
						$col['comanda'] = $k;
					} elseif ( false !== strpos( $h, 'cart' ) || false !== strpos( $h, 'cărț' ) || false !== strpos( $h, 'isbn' ) ) {
						$col['carti'] = $k;
					}
				}
				continue;
			}
			$get   = function ( $k ) use ( $f, $col ) {
				return isset( $f[ $col[ $k ] ] ) ? $f[ $col[ $k ] ] : '';
			};
			$ids   = array();
			$lipsa = array();
			foreach ( preg_split( '/[|;\n]+/', $get( 'carti' ) ) as $c ) {
				$c = trim( $c );
				if ( '' === $c ) {
					continue;
				}
				$cheie = strtoupper( preg_replace( '/[^0-9Xx]/', '', $c ) );
				if ( ctype_digit( $c ) && strlen( $c ) < 9 && get_post( (int) $c ) && 'p45_carte' === get_post_type( (int) $c ) ) {
					$ids[] = (int) $c;
				} elseif ( strlen( $cheie ) >= 10 && isset( $dupa_isbn[ $cheie ] ) ) {
					$ids[] = $dupa_isbn[ $cheie ];
				} elseif ( isset( $dupa_titlu[ mb_strtolower( $c ) ] ) ) {
					$ids[] = $dupa_titlu[ mb_strtolower( $c ) ];
				} else {
					$lipsa[] = $c;
				}
			}
			$r = p45cd_acorda( $get( 'email' ), $get( 'nume' ), $get( 'scoala' ), $get( 'comanda' ), $ids, $expira, $trimite );
			$rand = 'Rândul ' . ( $i + 1 ) . ': ' . $r['mesaj'];
			if ( $lipsa ) {
				$rand .= ' Nu am găsit: ' . implode( ', ', $lipsa ) . '.';
			}
			$rezumat[] = $rand;
			if ( $r['ok'] ) {
				$ok++;
			} else {
				$erori++;
			}
		}
		p45cd_mesaj_admin( $erori ? 'eroare' : 'ok', 'Import terminat: ' . $ok . ' rânduri reușite' . ( $erori ? ', ' . $erori . ' cu probleme.' : '.' ), $rezumat );
		wp_safe_redirect( $inapoi );
		exit;
	}
);

add_action(
	'admin_post_p45_retrimite',
	function () {
		$uid = isset( $_POST['user'] ) ? (int) $_POST['user'] : 0;
		p45cd_verifica_admin( 'p45_retrimite_' . $uid );
		$user = get_user_by( 'id', $uid );
		if ( $user ) {
			$carti = wp_list_pluck( p45cd_acces_utilizator( $uid ), 'carte_id' );
			$ok    = p45cd_email_invitatie( $user, $carti );
			p45cd_mesaj_admin( $ok ? 'ok' : 'eroare', $ok ? 'Am retrimis emailul cu linkul de parolă către ' . $user->user_email . '.' : 'Emailul nu a putut fi trimis. Verifică setările de email ale site-ului.' );
		}
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=p45-profesori' ) );
		exit;
	}
);

add_action(
	'admin_post_p45_scoate_acces',
	function () {
		$uid = isset( $_POST['user'] ) ? (int) $_POST['user'] : 0;
		$cid = isset( $_POST['carte'] ) ? (int) $_POST['carte'] : 0;
		p45cd_verifica_admin( 'p45_scoate_' . $uid . '_' . $cid );
		p45cd_scoate_acces( $uid, $cid );
		p45cd_mesaj_admin( 'ok', 'Accesul la „' . get_the_title( $cid ) . '” a fost scos.' );
		wp_safe_redirect( admin_url( 'admin.php?page=p45-profesori&user=' . $uid ) );
		exit;
	}
);

add_action(
	'admin_post_p45_salveaza_profesor',
	function () {
		$uid = isset( $_POST['user'] ) ? (int) $_POST['user'] : 0;
		p45cd_verifica_admin( 'p45_profesor_' . $uid );
		$user = get_user_by( 'id', $uid );
		if ( $user ) {
			$nume  = isset( $_POST['nume'] ) ? sanitize_text_field( wp_unslash( $_POST['nume'] ) ) : '';
			$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
			$parti = preg_split( '/\s+/', $nume, 2 );
			$date  = array( 'ID' => $uid );
			if ( $nume ) {
				$date['display_name'] = $nume;
				$date['first_name']   = $parti[0];
				$date['last_name']    = isset( $parti[1] ) ? $parti[1] : '';
			}
			$eroare = '';
			if ( $email && $email !== $user->user_email ) {
				$alt = get_user_by( 'email', $email );
				if ( ! is_email( $email ) ) {
					$eroare = 'Emailul nou nu e valid.';
				} elseif ( $alt && $alt->ID !== $uid ) {
					$eroare = 'Există deja un cont cu emailul ' . $email . '.';
				} else {
					$date['user_email'] = $email;
				}
			}
			$r = wp_update_user( $date );
			update_user_meta( $uid, 'p45_scoala', isset( $_POST['scoala'] ) ? sanitize_text_field( wp_unslash( $_POST['scoala'] ) ) : '' );
			if ( $eroare || is_wp_error( $r ) ) {
				p45cd_mesaj_admin( 'eroare', $eroare ? $eroare : $r->get_error_message() );
			} else {
				p45cd_mesaj_admin( 'ok', 'Datele profesorului au fost salvate.' );
			}
		}
		wp_safe_redirect( admin_url( 'admin.php?page=p45-profesori&user=' . $uid ) );
		exit;
	}
);

add_action(
	'admin_post_p45_sterge_profesor',
	function () {
		$uid = isset( $_POST['user'] ) ? (int) $_POST['user'] : 0;
		p45cd_verifica_admin( 'p45_sterge_profesor_' . $uid );
		$user = get_user_by( 'id', $uid );
		if ( $user && in_array( 'p45_profesor', (array) $user->roles, true ) && ! user_can( $user, 'p45_gestionare' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
			wp_delete_user( $uid );
			p45cd_mesaj_admin( 'ok', 'Contul ' . $user->user_email . ' a fost șters.' );
		} else {
			p45cd_mesaj_admin( 'eroare', 'Contul nu poate fi șters de aici (nu e un cont de profesor).' );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=p45-profesori' ) );
		exit;
	}
);

/* ------------------------------------------------------------------ */
/* Pagina                                                              */
/* ------------------------------------------------------------------ */

function p45cd_stare_profesor( $user ) {
	if ( get_user_meta( $user->ID, 'p45_ultima_intrare', true ) ) {
		return array( 'Activ', 'p45-chip-verde', false );
	}
	if ( get_user_meta( $user->ID, 'p45_import', true ) ) {
		return array( 'Din platforma veche', 'p45-chip-gri', false );
	}
	$inv = (int) get_user_meta( $user->ID, 'p45_invitat', true );
	if ( ! $inv ) {
		return array( 'Fără email trimis', 'p45-chip-gri', true );
	}
	$zile = max( 1, (int) p45cd_setare( 'zile_link' ) );
	if ( time() - $inv > $zile * DAY_IN_SECONDS ) {
		return array( 'Link expirat', 'p45-chip-rosu', true );
	}
	return array( 'Invitație trimisă', 'p45-chip-galben', true );
}

/** Lista de cărți cu bife (folosită la acces nou și la editarea profesorului). */
function p45cd_bife_carti( $selectate = array(), $exclude = array() ) {
	$posts = get_posts( array( 'post_type' => 'p45_carte', 'post_status' => array( 'publish', 'draft' ), 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
	$posts = array_filter(
		$posts,
		function ( $p ) use ( $exclude ) {
			return ! in_array( $p->ID, $exclude, true );
		}
	);
	if ( ! $posts ) {
		echo '<p class="p45-gri">' . ( $exclude ? 'Profesorul are deja acces la toate cărțile.' : 'Nu există încă nicio carte. <a href="' . esc_url( admin_url( 'admin.php?page=p45-carte' ) ) . '">Adaugă una din PDF</a>.' ) . '</p>';
		return;
	}
	echo '<label class="p45-cauta-carti"><span class="screen-reader-text">Caută în listă</span><input type="search" placeholder="Caută după titlu sau ISBN" data-filtru-carti></label>';
	echo '<div class="p45-lista-carti" data-lista-carti>';
	foreach ( $posts as $p ) {
		$c    = p45cd_carte( $p );
		$meta = array_filter( array( p45cd_nume_termeni( $c['clase'] ), p45cd_nume_termeni( $c['discipline'] ), $c['isbn'] ? 'ISBN ' . $c['isbn'] : '', 'publish' !== $c['status'] ? 'ciornă' : '' ) );
		echo '<label class="p45-carte-bifa" data-cauta="' . esc_attr( mb_strtolower( $c['titlu'] . ' ' . $c['isbn'] . ' ' . $c['autor'] ) ) . '">';
		echo '<input type="checkbox" name="carti[]" value="' . (int) $c['id'] . '" ' . checked( in_array( $c['id'], $selectate, true ), true, false ) . '>';
		if ( $c['coperta'] ) {
			echo '<img src="' . esc_url( $c['coperta'] ) . '" alt="">';
		} else {
			echo '<span class="p45-mini-gol" aria-hidden="true"></span>';
		}
		echo '<span><strong>' . esc_html( $c['titlu'] ) . '</strong><span class="p45-gri">' . esc_html( implode( ' · ', $meta ) ) . '</span></span>';
		echo '</label>';
	}
	echo '</div>';
}

function p45cd_campuri_valabilitate() {
	?>
	<fieldset class="p45-radio">
		<legend>Acces valabil</legend>
		<label><input type="radio" name="valabil" value="nelimitat" checked> Nelimitat</label>
		<label class="p45-pana"><input type="radio" name="valabil" value="pana"> Până la <input type="date" name="expira" value="<?php echo esc_attr( p45cd_sfarsit_an_scolar() ); ?>" aria-label="Data până la care e valabil accesul"></label>
	</fieldset>
	<?php
}

function p45cd_pagina_profesori() {
	if ( ! empty( $_GET['user'] ) ) {
		p45cd_pagina_un_profesor( (int) $_GET['user'] );
		return;
	}
	$q        = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
	$carte_id = isset( $_GET['carte'] ) ? (int) $_GET['carte'] : 0;
	$pag      = max( 1, isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 1 );
	$per      = 25;

	/* Cine apare în listă. */
	$args = array(
		'role__in' => array( 'p45_profesor' ),
		'number'   => $per,
		'paged'    => $pag,
		'orderby'  => 'registered',
		'order'    => 'DESC',
	);
	global $wpdb;
	$include = null;
	if ( $carte_id ) {
		$t       = p45cd_tabel_acces();
		$include = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT user_id FROM $t WHERE carte_id = %d", $carte_id ) ) );
		unset( $args['role__in'] );
	}
	if ( '' !== $q ) {
		$like  = '%' . $wpdb->esc_like( $q ) . '%';
		$ids   = get_users( array( 'search' => '*' . $q . '*', 'search_columns' => array( 'user_email', 'display_name', 'user_login' ), 'fields' => 'ID', 'number' => 2000 ) );
		$t     = p45cd_tabel_acces();
		$ids   = array_merge( $ids, $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT user_id FROM $t WHERE comanda LIKE %s", $like ) ) );
		$ids   = array_merge( $ids, $wpdb->get_col( $wpdb->prepare( "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'p45_scoala' AND meta_value LIKE %s", $like ) ) );
		$ids   = array_map( 'intval', array_unique( $ids ) );
		$include = null === $include ? $ids : array_values( array_intersect( $include, $ids ) );
	}
	if ( null !== $include ) {
		$args['include'] = $include ? $include : array( 0 );
	}
	$uq    = new WP_User_Query( $args );
	$users = $uq->get_results();
	$total = (int) $uq->get_total();
	?>
	<div class="wrap p45-admin">
		<div class="p45-cap">
			<div>
				<h1 class="p45-h1">Profesori și acces</h1>
				<p class="p45-sub">Profesorul cumpără cartea tipărită și primește aici varianta digitală, pentru tabla din clasă.</p>
			</div>
			<button type="button" class="p45-btn p45-btn-alb" id="p45-arata-import" aria-expanded="false" aria-controls="p45-import">Importă mai multe comenzi (CSV)</button>
		</div>
		<?php p45cd_afiseaza_mesaj_admin(); ?>

		<section class="p45-card" id="p45-import" hidden aria-labelledby="imp-titlu">
			<h2 id="imp-titlu" class="p45-h2">Import din fișier CSV</h2>
			<p>Un rând pe profesor, cu coloanele: <code>email, nume, scoala, comanda, carti</code>. La <code>carti</code> pui ISBN-uri sau titluri exacte, despărțite prin <code>|</code>. Poți salva fișierul din Excel ca „CSV UTF-8”. <a href="<?php echo esc_url( P45CD_URL . 'assets/model-import.csv' ); ?>">Descarcă un model</a>.</p>
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="p45-form-rand">
				<?php wp_nonce_field( 'p45_import' ); ?>
				<input type="hidden" name="action" value="p45_import">
				<label class="p45-camp">Fișier CSV <input type="file" name="fisier" accept=".csv,text/csv" required></label>
				<?php p45cd_campuri_valabilitate(); ?>
				<label class="p45-bifa"><input type="checkbox" name="trimite" value="1" checked> Trimite emailurile profesorilor</label>
				<button class="p45-btn p45-btn-rosu" type="submit">Importă</button>
			</form>
		</section>

		<div class="p45-coloane">
			<section class="p45-card p45-col-mare" aria-labelledby="nou-titlu">
				<h2 id="nou-titlu" class="p45-h2">Acces nou din comandă</h2>
				<p class="p45-gri">Profesorul primește un email cu link pentru parolă. Dacă are deja cont, cărțile se adaugă în contul lui.</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="p45-form">
					<?php wp_nonce_field( 'p45_acces_nou' ); ?>
					<input type="hidden" name="action" value="p45_acces_nou">
					<div class="p45-grila-campuri">
						<label class="p45-camp">Nr. comandă <input type="text" name="comanda" maxlength="64"></label>
						<label class="p45-camp"><span>Email profesor <span class="p45-oblig">obligatoriu</span></span><input type="email" name="email" required></label>
						<label class="p45-camp">Nume <input type="text" name="nume"></label>
						<label class="p45-camp"><span>Școala <span class="p45-oblig">opțional</span></span><input type="text" name="scoala"></label>
					</div>
					<fieldset class="p45-camp">
						<legend>Cărți din comandă <span class="p45-oblig">cel puțin una</span></legend>
						<?php p45cd_bife_carti( $carte_id ? array( $carte_id ) : array() ); ?>
					</fieldset>
					<?php p45cd_campuri_valabilitate(); ?>
					<label class="p45-bifa"><input type="checkbox" name="trimite" value="1" checked> Trimite emailul profesorului</label>
					<div><button class="p45-btn p45-btn-rosu" type="submit">Dă acces și trimite emailul</button></div>
				</form>
			</section>
			<aside class="p45-card p45-col-mica">
				<h2 class="p45-h2">Ce primește profesorul</h2>
				<ul class="p45-lista-pasi">
					<li><strong>Cont nou:</strong> email cu lista cărților și butonul „Setează parola și intră”. Linkul e valabil <?php echo (int) p45cd_setare( 'zile_link' ); ?> zile.</li>
					<li><strong>Cont existent:</strong> email că i s-au adăugat cărți noi în bibliotecă.</li>
					<li>Dacă linkul expiră, profesorul folosește „Ai uitat parola?” sau îi retrimiți tu emailul din listă.</li>
				</ul>
			</aside>
		</div>

		<section class="p45-card p45-fara-padding" aria-labelledby="lista-titlu">
			<div class="p45-cap-sec p45-padding">
				<h2 id="lista-titlu" class="p45-h2">
					<?php
					if ( $carte_id ) {
						echo 'Profesori cu acces la „' . esc_html( get_the_title( $carte_id ) ) . '” ';
						echo '<a class="p45-mic" href="' . esc_url( admin_url( 'admin.php?page=p45-profesori' ) ) . '">(arată toți)</a>';
					} else {
						echo 'Profesori';
					}
					?>
					<span class="p45-gri">· <?php echo (int) $total; ?></span>
				</h2>
				<form method="get" class="p45-cauta-form">
					<input type="hidden" name="page" value="p45-profesori">
					<?php if ( $carte_id ) : ?><input type="hidden" name="carte" value="<?php echo (int) $carte_id; ?>"><?php endif; ?>
					<label class="screen-reader-text" for="p45-cauta-prof">Caută profesor</label>
					<input type="search" id="p45-cauta-prof" name="s" value="<?php echo esc_attr( $q ); ?>" placeholder="Nume, email, școală sau nr. comandă">
					<button class="p45-btn p45-btn-alb" type="submit">Caută</button>
				</form>
			</div>
			<div class="p45-tabel-cutie">
			<table class="p45-tabel">
				<thead><tr><th scope="col">Profesor</th><th scope="col">Școala</th><th scope="col" class="num">Cărți</th><th scope="col">Comenzi</th><th scope="col">Ultima intrare</th><th scope="col">Stare</th><th scope="col"><span class="screen-reader-text">Acțiuni</span></th></tr></thead>
				<tbody>
				<?php if ( ! $users ) : ?>
					<tr><td colspan="7" class="p45-gol-rand"><?php echo $q || $carte_id ? 'Niciun profesor nu se potrivește.' : 'Încă nu ai dat acces niciunui profesor.'; ?></td></tr>
				<?php endif; ?>
				<?php
				foreach ( $users as $u ) :
					$st      = p45cd_stare_profesor( $u );
					$acc     = p45cd_acces_utilizator( $u->ID );
					$comenzi = array_unique( array_filter( wp_list_pluck( $acc, 'comanda' ) ) );
					$ult     = (int) get_user_meta( $u->ID, 'p45_ultima_intrare', true );
					$edit    = admin_url( 'admin.php?page=p45-profesori&user=' . $u->ID );
					?>
					<tr>
						<td><a class="p45-titlu-link" href="<?php echo esc_url( $edit ); ?>"><?php echo esc_html( $u->display_name ); ?></a><div class="p45-gri"><?php echo esc_html( $u->user_email ); ?></div></td>
						<td><?php echo esc_html( get_user_meta( $u->ID, 'p45_scoala', true ) ); ?></td>
						<td class="num"><?php echo count( $acc ); ?></td>
						<td><?php echo esc_html( implode( ', ', array_slice( $comenzi, 0, 4 ) ) . ( count( $comenzi ) > 4 ? '…' : '' ) ); ?></td>
						<td><?php echo $ult ? esc_html( p45cd_data_ora( $ult ) ) : '<span class="p45-gri">Încă nu a intrat</span>'; ?></td>
						<td><span class="p45-chip <?php echo esc_attr( $st[1] ); ?>"><?php echo esc_html( $st[0] ); ?></span></td>
						<td class="p45-actiuni">
							<a href="<?php echo esc_url( $edit ); ?>">Editează</a>
							<?php if ( $st[2] ) : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="p45-inline">
								<?php wp_nonce_field( 'p45_retrimite_' . $u->ID ); ?>
								<input type="hidden" name="action" value="p45_retrimite">
								<input type="hidden" name="user" value="<?php echo (int) $u->ID; ?>">
								<button type="submit" class="p45-link">Retrimite emailul</button>
							</form>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			</div>
			<?php
			$pagini = (int) ceil( $total / $per );
			if ( $pagini > 1 ) {
				echo '<div class="p45-paginare p45-padding">';
				echo paginate_links( // phpcs:ignore
					array(
						'base'      => add_query_arg( 'paged', '%#%' ),
						'format'    => '',
						'current'   => $pag,
						'total'     => $pagini,
						'prev_text' => '‹ Înapoi',
						'next_text' => 'Înainte ›',
					)
				);
				echo '</div>';
			}
			?>
		</section>
	</div>
	<?php p45cd_script_liste(); ?>
	<?php
}

function p45cd_pagina_un_profesor( $uid ) {
	$u = get_user_by( 'id', $uid );
	if ( ! $u ) {
		echo '<div class="wrap p45-admin"><h1 class="p45-h1">Profesorul nu există</h1><p><a href="' . esc_url( admin_url( 'admin.php?page=p45-profesori' ) ) . '">Înapoi</a></p></div>';
		return;
	}
	$acc = p45cd_acces_utilizator( $uid );
	$st  = p45cd_stare_profesor( $u );
	$ult = (int) get_user_meta( $uid, 'p45_ultima_intrare', true );
	?>
	<div class="wrap p45-admin">
		<div class="p45-cap">
			<div>
				<p class="p45-cale"><a href="<?php echo esc_url( admin_url( 'admin.php?page=p45-profesori' ) ); ?>">Profesori și acces</a> / <?php echo esc_html( $u->display_name ); ?></p>
				<h1 class="p45-h1"><?php echo esc_html( $u->display_name ); ?> <span class="p45-chip <?php echo esc_attr( $st[1] ); ?>"><?php echo esc_html( $st[0] ); ?></span></h1>
				<p class="p45-sub"><?php echo esc_html( $u->user_email ); ?> · <?php echo $ult ? 'ultima intrare: ' . esc_html( p45cd_data_ora( $ult ) ) : 'încă nu a intrat în platformă'; ?></p>
			</div>
		</div>
		<?php p45cd_afiseaza_mesaj_admin(); ?>

		<div class="p45-coloane">
			<div class="p45-col-mare">
				<section class="p45-card p45-fara-padding" aria-labelledby="acc-titlu">
					<h2 id="acc-titlu" class="p45-h2 p45-padding">Cărți la care are acces · <?php echo count( $acc ); ?></h2>
					<div class="p45-tabel-cutie">
					<table class="p45-tabel">
						<thead><tr><th scope="col">Carte</th><th scope="col">Comanda</th><th scope="col">Valabil până</th><th scope="col">Adăugată</th><th scope="col"><span class="screen-reader-text">Acțiuni</span></th></tr></thead>
						<tbody>
						<?php if ( ! $acc ) : ?>
							<tr><td colspan="5" class="p45-gol-rand">Nu are acces la nicio carte.</td></tr>
						<?php endif; ?>
						<?php foreach ( $acc as $a ) : ?>
							<tr>
								<td><?php echo esc_html( get_the_title( $a->carte_id ) ); ?></td>
								<td><?php echo esc_html( $a->comanda ); ?></td>
								<td><?php echo ( $a->expira && '0000-00-00' !== $a->expira ) ? esc_html( p45cd_data( strtotime( $a->expira . ' 12:00:00' ) ) ) . ( $a->expira < p45cd_azi() ? ' <span class="p45-chip p45-chip-rosu">expirat</span>' : '' ) : 'nelimitat'; ?></td>
								<td><?php echo esc_html( p45cd_data( strtotime( $a->creat ) ) ); ?></td>
								<td class="p45-actiuni">
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="p45-inline" onsubmit="return confirm('Scoți accesul profesorului la această carte?');">
										<?php wp_nonce_field( 'p45_scoate_' . $uid . '_' . $a->carte_id ); ?>
										<input type="hidden" name="action" value="p45_scoate_acces">
										<input type="hidden" name="user" value="<?php echo (int) $uid; ?>">
										<input type="hidden" name="carte" value="<?php echo (int) $a->carte_id; ?>">
										<button type="submit" class="p45-link-rosu">Scoate accesul</button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					</div>
				</section>

				<section class="p45-card" aria-labelledby="adauga-titlu">
					<h2 id="adauga-titlu" class="p45-h2">Adaugă cărți</h2>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="p45-form">
						<?php wp_nonce_field( 'p45_acces_nou' ); ?>
						<input type="hidden" name="action" value="p45_acces_nou">
						<input type="hidden" name="email" value="<?php echo esc_attr( $u->user_email ); ?>">
						<input type="hidden" name="inapoi_user" value="<?php echo (int) $uid; ?>">
						<label class="p45-camp p45-camp-scurt">Nr. comandă <input type="text" name="comanda" maxlength="64"></label>
						<?php p45cd_bife_carti( array(), array_map( 'intval', wp_list_pluck( $acc, 'carte_id' ) ) ); ?>
						<?php p45cd_campuri_valabilitate(); ?>
						<label class="p45-bifa"><input type="checkbox" name="trimite" value="1" checked> Trimite emailul profesorului</label>
						<div><button class="p45-btn p45-btn-rosu" type="submit">Adaugă cărțile</button></div>
					</form>
				</section>
			</div>

			<aside class="p45-col-mica">
				<section class="p45-card" aria-labelledby="date-titlu">
					<h2 id="date-titlu" class="p45-h2">Date profesor</h2>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="p45-form">
						<?php wp_nonce_field( 'p45_profesor_' . $uid ); ?>
						<input type="hidden" name="action" value="p45_salveaza_profesor">
						<input type="hidden" name="user" value="<?php echo (int) $uid; ?>">
						<label class="p45-camp">Nume <input type="text" name="nume" value="<?php echo esc_attr( $u->display_name ); ?>"></label>
						<label class="p45-camp">Email <input type="email" name="email" value="<?php echo esc_attr( $u->user_email ); ?>"></label>
						<label class="p45-camp">Școala <input type="text" name="scoala" value="<?php echo esc_attr( get_user_meta( $uid, 'p45_scoala', true ) ); ?>"></label>
						<div><button class="p45-btn p45-btn-alb" type="submit">Salvează</button></div>
					</form>
				</section>
				<section class="p45-card">
					<h2 class="p45-h2">Parolă</h2>
					<p class="p45-gri p45-mic">Trimite din nou emailul cu linkul de setare a parolei (de exemplu, dacă linkul a expirat sau profesorul și-a uitat parola).</p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'p45_retrimite_' . $uid ); ?>
						<input type="hidden" name="action" value="p45_retrimite">
						<input type="hidden" name="user" value="<?php echo (int) $uid; ?>">
						<button type="submit" class="p45-btn p45-btn-alb">Retrimite emailul cu linkul</button>
					</form>
				</section>
				<?php if ( in_array( 'p45_profesor', (array) $u->roles, true ) && ! user_can( $u, 'p45_gestionare' ) ) : ?>
				<section class="p45-card">
					<h2 class="p45-h2">Ștergere cont</h2>
					<p class="p45-gri p45-mic">Se șterg contul, accesul la cărți și notele profesorului.</p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Ștergi definitiv contul <?php echo esc_js( $u->user_email ); ?>?');">
						<?php wp_nonce_field( 'p45_sterge_profesor_' . $uid ); ?>
						<input type="hidden" name="action" value="p45_sterge_profesor">
						<input type="hidden" name="user" value="<?php echo (int) $uid; ?>">
						<button type="submit" class="p45-btn p45-btn-alb p45-rosu-text">Șterge contul</button>
					</form>
				</section>
				<?php endif; ?>
			</aside>
		</div>
	</div>
	<?php p45cd_script_liste(); ?>
	<?php
}

/** Căutare în lista de cărți + arată/ascunde importul. */
function p45cd_script_liste() {
	?>
	<script>
	(function () {
		document.querySelectorAll('[data-filtru-carti]').forEach(function (inp) {
			var lista = inp.closest('fieldset, form').querySelector('[data-lista-carti]');
			inp.addEventListener('input', function () {
				var q = inp.value.trim().toLowerCase();
				lista.querySelectorAll('[data-cauta]').forEach(function (l) {
					l.hidden = q && l.getAttribute('data-cauta').indexOf(q) === -1 && !l.querySelector('input').checked;
				});
			});
		});
		var b = document.getElementById('p45-arata-import'), box = document.getElementById('p45-import');
		if (b && box) b.addEventListener('click', function () {
			box.hidden = !box.hidden;
			b.setAttribute('aria-expanded', box.hidden ? 'false' : 'true');
			if (!box.hidden) box.scrollIntoView({ behavior: 'smooth', block: 'start' });
		});
	})();
	</script>
	<?php
}
