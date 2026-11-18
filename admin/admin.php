<?php
/**
 * Panoul de administrare „Cărți digitale”.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once P45CD_DIR . 'admin/carti.php';
require_once P45CD_DIR . 'admin/profesori.php';
require_once P45CD_DIR . 'admin/setari.php';

add_action( 'admin_menu', 'p45cd_meniu_admin' );

function p45cd_meniu_admin() {
	add_menu_page( 'Cărți digitale', 'Cărți digitale', 'p45_gestionare', 'p45-carti', 'p45cd_pagina_carti', 'dashicons-book-alt', 3 );
	add_submenu_page( 'p45-carti', 'Cărți', 'Cărți', 'p45_gestionare', 'p45-carti', 'p45cd_pagina_carti' );
	add_submenu_page( 'p45-carti', 'Adaugă o carte din PDF', 'Adaugă carte din PDF', 'p45_gestionare', 'p45-carte', 'p45cd_pagina_carte' );
	add_submenu_page( 'p45-carti', 'Profesori și acces', 'Profesori și acces', 'p45_gestionare', 'p45-profesori', 'p45cd_pagina_profesori' );
	add_submenu_page( 'p45-carti', 'Clase', 'Clase', 'p45_gestionare', 'edit-tags.php?taxonomy=p45_clasa&post_type=p45_carte' );
	add_submenu_page( 'p45-carti', 'Discipline', 'Discipline', 'p45_gestionare', 'edit-tags.php?taxonomy=p45_disciplina&post_type=p45_carte' );
	add_submenu_page( 'p45-carti', 'Setări', 'Setări', 'p45_gestionare', 'p45-setari', 'p45cd_pagina_setari' );
}

/* Meniul rămâne deschis pe paginile Clase / Discipline. */
add_filter(
	'parent_file',
	function ( $parent ) {
		$s = get_current_screen();
		if ( $s && in_array( $s->taxonomy, array( 'p45_clasa', 'p45_disciplina' ), true ) ) {
			return 'p45-carti';
		}
		return $parent;
	}
);
add_filter(
	'submenu_file',
	function ( $sub ) {
		$s = get_current_screen();
		if ( $s && in_array( $s->taxonomy, array( 'p45_clasa', 'p45_disciplina' ), true ) ) {
			return 'edit-tags.php?taxonomy=' . $s->taxonomy . '&post_type=p45_carte';
		}
		return $sub;
	}
);

/* Stiluri și scripturi doar pe paginile noastre. */
add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		$pagina = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
		$s      = get_current_screen();
		$tax    = $s && in_array( $s->taxonomy, array( 'p45_clasa', 'p45_disciplina' ), true );
		if ( 0 !== strpos( $pagina, 'p45-' ) && ! $tax ) {
			return;
		}
		wp_enqueue_style( 'p45cd-fonturi', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap', array(), null );
		wp_enqueue_style( 'p45cd-admin', P45CD_URL . 'assets/css/admin.css', array(), P45CD_VERSION . '.' . filemtime( P45CD_DIR . 'assets/css/admin.css' ) );
	}
);

/* ------------------------------------------------------------------ */
/* Mesaje după acțiuni (afișate o singură dată)                        */
/* ------------------------------------------------------------------ */

function p45cd_mesaj_admin( $tip, $text, $detalii = array() ) {
	set_transient( 'p45cd_msg_' . get_current_user_id(), array( 'tip' => $tip, 'text' => $text, 'detalii' => $detalii ), 120 );
}

function p45cd_afiseaza_mesaj_admin() {
	$m = get_transient( 'p45cd_msg_' . get_current_user_id() );
	if ( ! $m ) {
		return;
	}
	delete_transient( 'p45cd_msg_' . get_current_user_id() );
	$cls = 'eroare' === $m['tip'] ? 'p45-mesaj p45-mesaj-eroare' : 'p45-mesaj';
	echo '<div class="' . esc_attr( $cls ) . '" role="status"><p>' . esc_html( $m['text'] ) . '</p>';
	if ( ! empty( $m['detalii'] ) ) {
		echo '<ul>';
		foreach ( array_slice( (array) $m['detalii'], 0, 50 ) as $d ) {
			echo '<li>' . esc_html( $d ) . '</li>';
		}
		echo '</ul>';
	}
	echo '</div>';
}

function p45cd_verifica_admin( $nonce_action ) {
	if ( ! current_user_can( 'p45_gestionare' ) ) {
		wp_die( 'Nu ai permisiunea pentru această acțiune.', 403 );
	}
	check_admin_referer( $nonce_action );
}

/* ------------------------------------------------------------------ */
/* Ordinea claselor (câmp „Ordine” la Clase)                          */
/* ------------------------------------------------------------------ */

add_action(
	'p45_clasa_add_form_fields',
	function () {
		echo '<div class="form-field"><label for="p45-ordine">Ordine în listă</label><input type="number" name="p45_ordine" id="p45-ordine" value="" min="0" step="1"><p>Clasele se afișează în ordinea acestui număr (1 = prima).</p></div>';
	}
);
add_action(
	'p45_clasa_edit_form_fields',
	function ( $term ) {
		$v = get_term_meta( $term->term_id, 'ordine', true );
		echo '<tr class="form-field"><th scope="row"><label for="p45-ordine">Ordine în listă</label></th><td><input type="number" name="p45_ordine" id="p45-ordine" value="' . esc_attr( $v ) . '" min="0" step="1"><p class="description">Clasele se afișează în ordinea acestui număr (1 = prima).</p></td></tr>';
	}
);
foreach ( array( 'created_p45_clasa', 'edited_p45_clasa' ) as $hook ) {
	add_action(
		$hook,
		function ( $term_id ) {
			if ( isset( $_POST['p45_ordine'] ) && current_user_can( 'p45_gestionare' ) ) { // phpcs:ignore
				update_term_meta( $term_id, 'ordine', (int) $_POST['p45_ordine'] ); // phpcs:ignore
			}
		}
	);
}

/* Linkul „Setări” din lista de module. */
add_filter(
	'plugin_action_links_' . plugin_basename( P45CD_FILE ),
	function ( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=p45-setari' ) ) . '">Setări</a>' );
		return $links;
	}
);
