<?php
/**
 * Pregătește demo-ul din WordPress Playground (rulează o singură dată, din demo/blueprint.json):
 * - două cărți demo, cu primele pagini din „Aventurile lui Pinocchio” și „Morcoveață”;
 * - un cont de profesor cu acces la ele și câteva adnotări pe Pinocchio;
 * - bannerul cu datele de intrare (mu-plugin).
 *
 * Nu face parte din plugin și nu intră în arhiva de instalare.
 */

if ( ! defined( 'ABSPATH' ) ) {
	require_once '/wordpress/wp-load.php';
}

$p45_demo_sursa = '/tmp/p45-demo';
$p45_demo_aici  = __DIR__;

if ( ! function_exists( 'p45cd_creeaza_dir_carte' ) ) {
	throw new Exception( 'Pluginul Cărți digitale nu este activ.' );
}
if ( ! file_exists( $p45_demo_sursa . '/carti.json' ) ) {
	throw new Exception( 'Lipsește conținutul demo (' . $p45_demo_sursa . '/carti.json).' );
}

update_option( 'blogname', 'Cărți digitale Paralela 45' );
update_option( 'blogdescription', 'Demo' );
update_option( 'timezone_string', 'Europe/Bucharest' );
update_option( 'p45cd_setari', array_merge( (array) get_option( 'p45cd_setari', array() ), array( 'email_suport' => 'editura@exemplu.ro' ) ) );

// Contul de profesor.
$p45_demo_email = 'profesor@demo.ro';
$p45_demo_uid   = email_exists( $p45_demo_email );
if ( ! $p45_demo_uid ) {
	$p45_demo_uid = wp_insert_user(
		array(
			'user_login'   => $p45_demo_email,
			'user_email'   => $p45_demo_email,
			'user_pass'    => 'Demo1234',
			'display_name' => 'Elena Stan',
			'first_name'   => 'Elena',
			'last_name'    => 'Stan',
			'role'         => 'p45_profesor',
		)
	);
	if ( is_wp_error( $p45_demo_uid ) ) {
		throw new Exception( $p45_demo_uid->get_error_message() );
	}
}
update_user_meta( $p45_demo_uid, 'p45_scoala', 'Școala Gimnazială nr. 7' );

// Cărțile.
$p45_demo_carti = json_decode( file_get_contents( $p45_demo_sursa . '/carti.json' ), true );
foreach ( $p45_demo_carti as $p45_i => $c ) {
	$id = wp_insert_post(
		array(
			'post_type'   => 'p45_carte',
			'post_status' => 'publish',
			'post_title'  => $c['titlu'],
			'menu_order'  => $p45_i,
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		throw new Exception( $id->get_error_message() );
	}
	wp_set_object_terms( $id, $c['clase'], 'p45_clasa' );
	wp_set_object_terms( $id, $c['discipline'], 'p45_disciplina' );

	$token = p45cd_token_nou();
	$dir   = p45cd_creeaza_dir_carte( $id, $token );
	$din   = $p45_demo_sursa . '/' . $c['dosar'];
	for ( $n = 1; $n <= (int) $c['pagini']; $n++ ) {
		$f = sprintf( '%03d.%s', $n, $c['ext'] );
		copy( "$din/pagini/$f", "$dir/pagini/$f" );
		copy( "$din/mini/$f", "$dir/mini/$f" );
	}
	if ( file_exists( "$din/coperta.webp" ) ) {
		copy( "$din/coperta.webp", "$dir/coperta.webp" );
	}

	update_post_meta( $id, '_p45_autor', $c['autor'] );
	update_post_meta( $id, '_p45_isbn', $c['isbn'] );
	update_post_meta( $id, '_p45_an', $c['an'] );
	update_post_meta( $id, '_p45_toc', wp_slash( wp_json_encode( p45cd_curata_cuprins( $c['cuprins'], (int) $c['pagini'] ) ) ) );
	update_post_meta( $id, '_p45_sursa', $c['sursa'] );
	update_post_meta( $id, '_p45_sursa_marime', 0 );
	update_post_meta( $id, '_p45_pages', (int) $c['pagini'] );
	update_post_meta( $id, '_p45_ext', $c['ext'] );
	update_post_meta( $id, '_p45_w', (int) $c['w'] );
	update_post_meta( $id, '_p45_h', (int) $c['h'] );
	update_post_meta( $id, '_p45_dir', $token );
	update_post_meta( $id, '_p45_cover_v', time() );

	p45cd_da_acces( $p45_demo_uid, $id, '10001' );
	if ( ! empty( $c['stare'] ) ) {
		p45cd_salveaza_stare( $p45_demo_uid, $id, $c['stare'], (int) $c['pagini'] );
	}
}

// Bannerul cu datele de intrare.
wp_mkdir_p( WP_CONTENT_DIR . '/mu-plugins' );
copy( $p45_demo_aici . '/p45-demo.php', WP_CONTENT_DIR . '/mu-plugins/p45-demo.php' );

// Conținutul demo nu mai e nevoie să stea în memorie.
function p45_demo_sterge( $cale ) {
	if ( ! is_dir( $cale ) ) {
		return;
	}
	$it = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $cale, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ( $it as $f ) {
		$f->isDir() ? @rmdir( $f->getPathname() ) : @unlink( $f->getPathname() );
	}
	@rmdir( $cale );
}
p45_demo_sterge( $p45_demo_sursa );
if ( false !== strpos( $p45_demo_aici, '/wp-content/plugins/' ) ) {
	p45_demo_sterge( dirname( $p45_demo_aici ) . '/docs' );
	p45_demo_sterge( $p45_demo_aici );
}

flush_rewrite_rules();
