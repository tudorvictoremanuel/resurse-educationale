<?php
/**
 * Tipul de conținut „Carte digitală” și taxonomiile Clasă și Disciplină.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function p45cd_inregistreaza_tipuri() {
	register_post_type(
		'p45_carte',
		array(
			'labels'          => array(
				'name'          => 'Cărți digitale',
				'singular_name' => 'Carte digitală',
			),
			'public'          => false,
			'show_ui'         => false,
			'show_in_rest'    => false,
			'supports'        => array( 'title' ),
			'capability_type' => 'post',
			'map_meta_cap'    => true,
			'rewrite'         => false,
			'query_var'       => false,
		)
	);

	$comun = array(
		'public'            => false,
		'show_ui'           => true,
		'show_in_menu'      => false,
		'show_admin_column' => false,
		'show_in_rest'      => false,
		'hierarchical'      => false,
		'rewrite'           => false,
		'query_var'         => false,
		'capabilities'      => array(
			'manage_terms' => 'p45_gestionare',
			'edit_terms'   => 'p45_gestionare',
			'delete_terms' => 'p45_gestionare',
			'assign_terms' => 'p45_gestionare',
		),
	);

	register_taxonomy(
		'p45_clasa',
		'p45_carte',
		array_merge(
			$comun,
			array(
				'labels' => array(
					'name'          => 'Clase',
					'singular_name' => 'Clasă',
					'add_new_item'  => 'Adaugă o clasă',
					'search_items'  => 'Caută clase',
					'not_found'     => 'Nu există clase.',
				),
			)
		)
	);

	register_taxonomy(
		'p45_disciplina',
		'p45_carte',
		array_merge(
			$comun,
			array(
				'labels' => array(
					'name'          => 'Discipline',
					'singular_name' => 'Disciplină',
					'add_new_item'  => 'Adaugă o disciplină',
					'search_items'  => 'Caută discipline',
					'not_found'     => 'Nu există discipline.',
				),
			)
		)
	);
}
add_action( 'init', 'p45cd_inregistreaza_tipuri' );

/** Clasele, în ordinea firească (pregătitoare, I, II...). */
function p45cd_clase_ordonate( $args = array() ) {
	$termeni = get_terms( array_merge( array( 'taxonomy' => 'p45_clasa', 'hide_empty' => false ), $args ) );
	if ( is_wp_error( $termeni ) ) {
		return array();
	}
	usort(
		$termeni,
		function ( $a, $b ) {
			$oa = (int) get_term_meta( $a->term_id, 'ordine', true );
			$ob = (int) get_term_meta( $b->term_id, 'ordine', true );
			$oa = $oa ? $oa : 999;
			$ob = $ob ? $ob : 999;
			if ( $oa === $ob ) {
				return strcmp( $a->name, $b->name );
			}
			return $oa - $ob;
		}
	);
	return $termeni;
}
