<?php
/**
 * Accesul profesorilor la cărți și datele lor (semne de carte, note, desene, ultima pagină).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Data de azi în fusul orar al site-ului (Y-m-d). */
function p45cd_azi() {
	return current_time( 'Y-m-d' );
}

/** Poate citi utilizatorul cartea? */
function p45cd_poate_citi( $user_id, $carte_id ) {
	$user_id  = (int) $user_id;
	$carte_id = (int) $carte_id;
	if ( ! $user_id || ! $carte_id ) {
		return false;
	}
	$post = get_post( $carte_id );
	if ( ! $post || 'p45_carte' !== $post->post_type ) {
		return false;
	}
	if ( p45cd_e_admin( $user_id ) ) {
		return true;
	}
	if ( 'publish' !== $post->post_status ) {
		return false;
	}
	global $wpdb;
	$t   = p45cd_tabel_acces();
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT expira FROM $t WHERE user_id = %d AND carte_id = %d", $user_id, $carte_id ) );
	if ( ! $row ) {
		return false;
	}
	return empty( $row->expira ) || '0000-00-00' === $row->expira || $row->expira >= p45cd_azi();
}

/** ID-urile cărților publicate pe care le poate citi utilizatorul. */
function p45cd_carti_utilizator( $user_id ) {
	$user_id = (int) $user_id;
	if ( p45cd_e_admin( $user_id ) ) {
		return get_posts(
			array(
				'post_type'      => 'p45_carte',
				'post_status'    => array( 'publish' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
	}
	global $wpdb;
	$t   = p45cd_tabel_acces();
	$ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT carte_id FROM $t WHERE user_id = %d AND (expira IS NULL OR expira = '0000-00-00' OR expira >= %s)",
			$user_id,
			p45cd_azi()
		)
	);
	$ids = array_map( 'intval', $ids );
	if ( ! $ids ) {
		return array();
	}
	return get_posts(
		array(
			'post_type'      => 'p45_carte',
			'post_status'    => 'publish',
			'post__in'       => $ids,
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'orderby'        => 'title',
			'order'          => 'ASC',
		)
	);
}

/** Dă acces (sau actualizează accesul existent). */
function p45cd_da_acces( $user_id, $carte_id, $comanda = '', $expira = null ) {
	global $wpdb;
	$t        = p45cd_tabel_acces();
	$user_id  = (int) $user_id;
	$carte_id = (int) $carte_id;
	$comanda  = mb_substr( sanitize_text_field( $comanda ), 0, 64 );
	$expira   = ( $expira && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $expira ) ) ? $expira : null;
	$exista   = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t WHERE user_id = %d AND carte_id = %d", $user_id, $carte_id ) );
	if ( $exista ) {
		$wpdb->update(
			$t,
			array( 'comanda' => $comanda, 'expira' => $expira ),
			array( 'id' => (int) $exista )
		);
		return 'actualizat';
	}
	$wpdb->insert(
		$t,
		array(
			'user_id'  => $user_id,
			'carte_id' => $carte_id,
			'comanda'  => $comanda,
			'expira'   => $expira,
			'creat'    => current_time( 'mysql' ),
		)
	);
	return 'nou';
}

function p45cd_scoate_acces( $user_id, $carte_id ) {
	global $wpdb;
	$wpdb->delete( p45cd_tabel_acces(), array( 'user_id' => (int) $user_id, 'carte_id' => (int) $carte_id ) );
}

/** Rândurile de acces ale unui utilizator (cu titlul cărții). */
function p45cd_acces_utilizator( $user_id ) {
	global $wpdb;
	$t = p45cd_tabel_acces();
	return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE user_id = %d ORDER BY creat DESC", (int) $user_id ) );
}

function p45cd_numar_acces_carte( $carte_id ) {
	global $wpdb;
	$t = p45cd_tabel_acces();
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE carte_id = %d", (int) $carte_id ) );
}

function p45cd_numar_acces_utilizator( $user_id ) {
	global $wpdb;
	$t = p45cd_tabel_acces();
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE user_id = %d", (int) $user_id ) );
}

/** Șterge tot ce ține de o carte (acces + date profesori). */
function p45cd_sterge_date_carte( $carte_id ) {
	global $wpdb;
	$wpdb->delete( p45cd_tabel_acces(), array( 'carte_id' => (int) $carte_id ) );
	$wpdb->delete( p45cd_tabel_date(), array( 'carte_id' => (int) $carte_id ) );
}

/* Când se șterge un utilizator, se șterg și accesul și datele lui. */
add_action(
	'deleted_user',
	function ( $user_id ) {
		global $wpdb;
		$wpdb->delete( p45cd_tabel_acces(), array( 'user_id' => (int) $user_id ) );
		$wpdb->delete( p45cd_tabel_date(), array( 'user_id' => (int) $user_id ) );
	}
);

/* ------------------------------------------------------------------ */
/* Datele profesorului pentru o carte                                  */
/* ------------------------------------------------------------------ */

function p45cd_stare_goala() {
	return array(
		'marks' => array(),
		'notes' => array(),
		'ink'   => new stdClass(),
		'last'  => 0,
	);
}

function p45cd_stare( $user_id, $carte_id ) {
	global $wpdb;
	$t   = p45cd_tabel_date();
	$raw = $wpdb->get_var( $wpdb->prepare( "SELECT date FROM $t WHERE user_id = %d AND carte_id = %d", (int) $user_id, (int) $carte_id ) );
	$d   = $raw ? json_decode( $raw, true ) : null;
	if ( ! is_array( $d ) ) {
		return p45cd_stare_goala();
	}
	$out = p45cd_stare_goala();
	if ( isset( $d['marks'] ) && is_array( $d['marks'] ) ) {
		$out['marks'] = array_values( array_map( 'intval', $d['marks'] ) );
	}
	if ( isset( $d['notes'] ) && is_array( $d['notes'] ) ) {
		$out['notes'] = array_values( $d['notes'] );
	}
	if ( isset( $d['ink'] ) && is_array( $d['ink'] ) && $d['ink'] ) {
		$out['ink'] = $d['ink'];
	}
	if ( isset( $d['last'] ) ) {
		$out['last'] = (int) $d['last'];
	}
	return $out;
}

/** Validează și salvează datele trimise de cititor. */
function p45cd_salveaza_stare( $user_id, $carte_id, $d, $pagini ) {
	$pagini = max( 1, (int) $pagini );
	$out    = p45cd_stare_goala();

	if ( isset( $d['marks'] ) && is_array( $d['marks'] ) ) {
		$m = array();
		foreach ( $d['marks'] as $p ) {
			$p = (int) $p;
			if ( $p >= 1 && $p <= $pagini ) {
				$m[ $p ] = $p;
			}
		}
		$out['marks'] = array_values( $m );
	}

	if ( isset( $d['notes'] ) && is_array( $d['notes'] ) ) {
		$n = array();
		foreach ( array_slice( $d['notes'], 0, 500 ) as $x ) {
			if ( ! is_array( $x ) || empty( $x['text'] ) ) {
				continue;
			}
			$p = isset( $x['p'] ) ? (int) $x['p'] : 0;
			if ( $p < 1 || $p > $pagini ) {
				continue;
			}
			$n[] = array(
				'id'   => isset( $x['id'] ) ? (float) $x['id'] : 0,
				'p'    => $p,
				'text' => mb_substr( sanitize_textarea_field( (string) $x['text'] ), 0, 4000 ),
				'd'    => isset( $x['d'] ) ? (float) $x['d'] : 0,
			);
		}
		$out['notes'] = $n;
	}

	if ( isset( $d['ink'] ) && is_array( $d['ink'] ) ) {
		$ink = array();
		foreach ( $d['ink'] as $p => $strokes ) {
			$p = (int) $p;
			if ( $p < 1 || $p > $pagini || ! is_array( $strokes ) ) {
				continue;
			}
			$lista = array();
			foreach ( array_slice( $strokes, 0, 400 ) as $s ) {
				if ( ! is_array( $s ) || empty( $s['pts'] ) || ! is_array( $s['pts'] ) ) {
					continue;
				}
				$t   = ( isset( $s['t'] ) && 'marker' === $s['t'] ) ? 'marker' : 'pen';
				$c   = ( isset( $s['c'] ) && preg_match( '/^#[0-9a-fA-F]{6}$/', $s['c'] ) ) ? $s['c'] : '#E5341A';
				$pts = array();
				foreach ( array_slice( $s['pts'], 0, 3000 ) as $pt ) {
					if ( is_array( $pt ) && count( $pt ) >= 2 ) {
						$pts[] = array( round( (float) $pt[0], 4 ), round( (float) $pt[1], 4 ) );
					}
				}
				if ( $pts ) {
					$lista[] = array( 't' => $t, 'c' => $c, 'pts' => $pts );
				}
			}
			if ( $lista ) {
				$ink[ $p ] = $lista;
			}
		}
		$out['ink'] = $ink ? $ink : new stdClass();
	}

	if ( isset( $d['last'] ) ) {
		$out['last'] = min( $pagini - 1, max( 0, (int) $d['last'] ) );
	}

	$json = wp_json_encode( $out );
	if ( strlen( $json ) > 4 * 1024 * 1024 ) {
		return new WP_Error( 'p45_prea_mare', 'Desenele de pe această carte sunt prea multe pentru a fi salvate. Șterge câteva și încearcă din nou.' );
	}

	global $wpdb;
	$t = p45cd_tabel_date();
	$wpdb->replace(
		$t,
		array(
			'user_id'    => (int) $user_id,
			'carte_id'   => (int) $carte_id,
			'date'       => $json,
			'actualizat' => current_time( 'mysql' ),
		)
	);
	return $out;
}

/** Ultima carte deschisă de utilizator (dintre cele la care are acces). */
function p45cd_ultima_carte( $user_id, $permise ) {
	if ( ! $permise ) {
		return null;
	}
	global $wpdb;
	$t   = p45cd_tabel_date();
	$ids = implode( ',', array_map( 'intval', $permise ) );
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT carte_id, date FROM $t WHERE user_id = %d AND carte_id IN ($ids) ORDER BY actualizat DESC LIMIT 1", (int) $user_id ) ); // phpcs:ignore
	return $row ? $row : null;
}

/** Ultima pagină citită pentru fiecare carte a utilizatorului. */
function p45cd_progres_utilizator( $user_id ) {
	global $wpdb;
	$t   = p45cd_tabel_date();
	$out = array();
	foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT carte_id, date FROM $t WHERE user_id = %d", (int) $user_id ) ) as $r ) {
		$d                            = json_decode( $r->date, true );
		$out[ (int) $r->carte_id ] = is_array( $d ) && isset( $d['last'] ) ? (int) $d['last'] : 0;
	}
	return $out;
}
