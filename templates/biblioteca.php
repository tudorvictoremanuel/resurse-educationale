<?php
/**
 * Biblioteca profesorului.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
require_once P45CD_DIR . 'templates/parts.php';

$user     = wp_get_current_user();
$admin    = p45cd_e_admin();
$ids      = p45cd_carti_utilizator( $user->ID );
$progres  = p45cd_progres_utilizator( $user->ID );
$carti    = array();
foreach ( $ids as $id ) {
	$c = p45cd_carte( $id );
	if ( $c && $c['gata'] ) {
		$carti[ $id ] = $c;
	}
}
$prenume = $user->first_name ? $user->first_name : strtok( $user->display_name ? $user->display_name : $user->user_email, ' @' );

/* Ultima carte deschisă. */
$ultima      = p45cd_ultima_carte( $user->ID, array_keys( $carti ) );
$ultima_c    = null;
$ultima_last = 0;
if ( $ultima && isset( $carti[ (int) $ultima->carte_id ] ) ) {
	$ultima_c    = $carti[ (int) $ultima->carte_id ];
	$d           = json_decode( $ultima->date, true );
	$ultima_last = is_array( $d ) && isset( $d['last'] ) ? (int) $d['last'] : 0;
	$ultima_stat = array(
		'semne' => is_array( $d ) && ! empty( $d['marks'] ) ? count( $d['marks'] ) : 0,
		'note'  => is_array( $d ) && ! empty( $d['notes'] ) ? count( $d['notes'] ) : 0,
	);
}

/* Filtre după clasă, doar pentru clasele care au cărți. */
$clase_folosite = array();
foreach ( $carti as $c ) {
	foreach ( $c['clase'] as $t ) {
		$clase_folosite[ $t->term_id ] = isset( $clase_folosite[ $t->term_id ] ) ? $clase_folosite[ $t->term_id ] + 1 : 1;
	}
}
$filtre = array();
foreach ( p45cd_clase_ordonate() as $t ) {
	if ( isset( $clase_folosite[ $t->term_id ] ) ) {
		$filtre[] = array( 'id' => $t->term_id, 'nume' => $t->name, 'nr' => $clase_folosite[ $t->term_id ] );
	}
}

function p45cd_pagina_text( $last, $pagini ) {
	return 'Pagina ' . min( $pagini, $last + 1 ) . ' din ' . $pagini;
}

p45cd_inceput( 'Biblioteca mea', 'pagina-biblioteca' );
?>
<header class="antet">
	<div class="antet-in">
		<?php p45cd_marca(); ?>
		<nav class="antet-nav" aria-label="Meniu principal">
			<a href="<?php echo esc_url( p45cd_url( 'biblioteca' ) ); ?>" aria-current="page">Biblioteca mea</a>
			<?php if ( $admin ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=p45-carti' ) ); ?>">Administrare</a>
			<?php endif; ?>
		</nav>
		<span class="spatiu"></span>
		<?php if ( count( $carti ) > 4 ) : ?>
		<label class="cauta">
			<span class="sr">Caută o carte</span>
			<?php echo p45cd_icon( 'cauta', 18 ); // phpcs:ignore ?>
			<input type="search" id="cauta" placeholder="Caută o carte">
		</label>
		<?php endif; ?>
		<div class="cont">
			<span class="avatar" aria-hidden="true"><?php echo esc_html( p45cd_initiale( $user->display_name ? $user->display_name : $user->user_email ) ); ?></span>
			<span class="cont-nume"><?php echo esc_html( $user->display_name ? $user->display_name : $user->user_email ); ?></span>
			<a class="btn btn-alb btn-mic" href="<?php echo esc_url( p45cd_url( 'iesire' ) ); ?>"><?php echo p45cd_icon( 'iesire', 16 ); // phpcs:ignore ?>Ieși</a>
		</div>
	</div>
</header>

<main class="continut">
	<?php if ( ! empty( $_GET['bun_venit'] ) ) : ?>
		<p class="info" role="status">Parola a fost salvată. Bine ai venit!</p>
	<?php endif; ?>
	<?php if ( $admin ) : ?>
		<p class="info">Ești în contul editurii: vezi toate cărțile publicate. Profesorii văd doar cărțile la care le-ai dat acces.</p>
	<?php endif; ?>

	<section class="salut" aria-labelledby="salut">
		<h1 id="salut">Bună, <?php echo esc_html( $prenume ); ?>!</h1>
		<?php if ( $carti ) : ?>
			<p class="muted mare">Ai <?php echo count( $carti ) === 1 ? 'o carte digitală' : count( $carti ) . ' cărți digitale'; ?>. Le poți deschide pe calculator sau direct pe tabla din clasă.</p>
		<?php endif; ?>
	</section>

	<?php if ( ! $carti ) : ?>
		<section class="gol">
			<?php echo p45cd_icon( 'carte', 40 ); // phpcs:ignore ?>
			<?php if ( $admin ) : ?>
				<h2>Nu ai publicat încă nicio carte</h2>
				<p class="muted">Încarcă un PDF din panoul de administrare și platforma îl transformă în carte digitală.</p>
				<a class="btn btn-rosu" href="<?php echo esc_url( admin_url( 'admin.php?page=p45-carte' ) ); ?>">Adaugă o carte</a>
			<?php else : ?>
				<h2>Încă nu ai cărți în bibliotecă</h2>
				<p class="muted">Cărțile apar aici după ce editura îți dă acces, odată cu comanda. Dacă ai comandat deja și nu le vezi, scrie-ne la <?php echo p45cd_email_suport_html(); // phpcs:ignore ?>.</p>
			<?php endif; ?>
		</section>
	<?php else : ?>

	<div class="rand-sus">
		<?php if ( $ultima_c ) : ?>
		<article class="continua">
			<?php echo p45cd_coperta_html( $ultima_c, 'continua-coperta' ); // phpcs:ignore ?>
			<div class="continua-info">
				<span class="eticheta">Continuă de unde ai rămas</span>
				<h2><?php echo esc_html( $ultima_c['titlu'] ); ?></h2>
				<?php
				$cap = null;
				foreach ( $ultima_c['toc'] as $r ) {
					if ( $r['p'] <= $ultima_last + 1 ) {
						$cap = $r;
					}
				}
				if ( $cap ) :
					?>
					<p class="muted"><?php echo esc_html( $cap['t'] . ( $cap['s'] ? ' · ' . $cap['s'] : '' ) ); ?></p>
				<?php endif; ?>
				<div class="progres">
					<span class="bara"><span style="width:<?php echo (int) round( 100 * ( $ultima_last + 1 ) / max( 1, $ultima_c['pagini'] ) ); ?>%"></span></span>
					<span class="muted mic"><?php echo esc_html( p45cd_pagina_text( $ultima_last, $ultima_c['pagini'] ) ); ?></span>
				</div>
				<?php if ( $ultima_stat['semne'] || $ultima_stat['note'] ) : ?>
				<div class="cipuri">
					<?php if ( $ultima_stat['semne'] ) : ?>
						<span class="cip"><?php echo p45cd_icon( 'semn', 14 ); // phpcs:ignore ?><?php echo (int) $ultima_stat['semne']; ?> <?php echo 1 === $ultima_stat['semne'] ? 'semn de carte' : 'semne de carte'; ?></span>
					<?php endif; ?>
					<?php if ( $ultima_stat['note'] ) : ?>
						<span class="cip"><?php echo p45cd_icon( 'nota', 14 ); // phpcs:ignore ?><?php echo (int) $ultima_stat['note']; ?> <?php echo 1 === $ultima_stat['note'] ? 'notă' : 'note'; ?></span>
					<?php endif; ?>
				</div>
				<?php endif; ?>
				<div class="butoane">
					<a class="btn btn-rosu" href="<?php echo esc_url( $ultima_c['url_tabla'] ); ?>"><?php echo p45cd_icon( 'tabla' ); // phpcs:ignore ?>Deschide pe tablă</a>
					<a class="btn btn-alb" href="<?php echo esc_url( $ultima_c['url'] ); ?>"><?php echo p45cd_icon( 'carte' ); // phpcs:ignore ?>Citește pe calculator</a>
				</div>
			</div>
		</article>
		<?php endif; ?>
		<aside class="sfat" aria-labelledby="sfat-titlu">
			<span class="sfat-icon"><?php echo p45cd_icon( 'tabla', 24 ); // phpcs:ignore ?></span>
			<h2 id="sfat-titlu">Lucrezi la tablă?</h2>
			<ol>
				<li><span>1</span>Intră în cont pe calculatorul legat la tablă.</li>
				<li><span>2</span>Deschide cartea și apasă „Mod Tablă”.</li>
				<li><span>3</span>Pagina ocupă tot ecranul: răsfoiești cu săgețile mari și scrii peste ea cu creionul sau markerul.</li>
			</ol>
		</aside>
	</div>

	<section aria-labelledby="cartile-mele" class="sectiune">
		<div class="sectiune-cap">
			<h2 id="cartile-mele">Cărțile mele</h2>
		</div>
		<?php if ( count( $filtre ) > 1 ) : ?>
		<div class="filtre" role="group" aria-label="Filtrează după clasă">
			<button type="button" class="filtru" aria-pressed="true" data-clasa="">Toate <span><?php echo count( $carti ); ?></span></button>
			<?php foreach ( $filtre as $f ) : ?>
				<button type="button" class="filtru" aria-pressed="false" data-clasa="<?php echo (int) $f['id']; ?>"><?php echo esc_html( $f['nume'] ); ?> <span><?php echo (int) $f['nr']; ?></span></button>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>

		<div class="grila" id="grila">
			<?php
			foreach ( $carti as $c ) :
				$last  = isset( $progres[ $c['id'] ] ) ? (int) $progres[ $c['id'] ] : -1;
				$inc   = $last > 0;
				$meta  = array_filter( array( p45cd_nume_termeni( $c['clase'] ), p45cd_nume_termeni( $c['discipline'] ), $c['pagini'] . ' pagini' ) );
				$cl    = implode( ' ', wp_list_pluck( $c['clase'], 'term_id' ) );
				?>
			<article class="card-carte" data-clase=" <?php echo esc_attr( $cl ); ?> " data-titlu="<?php echo esc_attr( mb_strtolower( $c['titlu'] . ' ' . $c['autor'] ) ); ?>">
				<a href="<?php echo esc_url( $c['url'] ); ?>" class="card-coperta" tabindex="-1" aria-hidden="true"><?php echo p45cd_coperta_html( $c ); // phpcs:ignore ?></a>
				<div class="card-text">
					<h3><a href="<?php echo esc_url( $c['url'] ); ?>"><?php echo esc_html( $c['titlu'] ); ?></a></h3>
					<?php if ( $c['autor'] ) : ?>
						<p class="autor"><?php echo esc_html( $c['autor'] ); ?></p>
					<?php endif; ?>
					<p class="muted mic"><?php echo esc_html( implode( ' · ', $meta ) ); ?></p>
				</div>
				<?php if ( $inc ) : ?>
				<div class="progres">
					<span class="bara"><span style="width:<?php echo (int) round( 100 * ( $last + 1 ) / max( 1, $c['pagini'] ) ); ?>%"></span></span>
					<span class="muted mic">p. <?php echo (int) min( $c['pagini'], $last + 1 ); ?> / <?php echo (int) $c['pagini']; ?></span>
				</div>
				<?php endif; ?>
				<div class="card-butoane">
					<a class="btn btn-alb" href="<?php echo esc_url( $c['url'] ); ?>"><?php echo $inc ? 'Continuă' : 'Deschide'; ?></a>
					<a class="btn-tabla" href="<?php echo esc_url( $c['url_tabla'] ); ?>" aria-label="Deschide pe tablă: <?php echo esc_attr( $c['titlu'] ); ?>" title="Deschide pe tablă"><?php echo p45cd_icon( 'tabla' ); // phpcs:ignore ?></a>
				</div>
			</article>
			<?php endforeach; ?>
		</div>
		<p class="gol-filtru muted" id="golFiltru" hidden>Nicio carte nu se potrivește.</p>
	</section>
	<?php endif; ?>

	<footer class="subsol">
		<span>Ai nevoie de ajutor? Scrie-ne la <?php echo p45cd_email_suport_html(); // phpcs:ignore ?></span>
		<span>© Editura Paralela 45</span>
	</footer>
</main>
<script>
(function () {
	var grila = document.getElementById('grila');
	if (!grila) return;
	var clasa = '', text = '';
	function aplica() {
		var vizibile = 0;
		grila.querySelectorAll('.card-carte').forEach(function (c) {
			var ok = (!clasa || c.getAttribute('data-clase').indexOf(' ' + clasa + ' ') !== -1) && (!text || c.getAttribute('data-titlu').indexOf(text) !== -1);
			c.hidden = !ok;
			if (ok) vizibile++;
		});
		document.getElementById('golFiltru').hidden = vizibile > 0;
	}
	document.querySelectorAll('.filtru').forEach(function (b) {
		b.addEventListener('click', function () {
			document.querySelectorAll('.filtru').forEach(function (x) { x.setAttribute('aria-pressed', 'false'); });
			b.setAttribute('aria-pressed', 'true');
			clasa = b.getAttribute('data-clasa');
			aplica();
		});
	});
	var c = document.getElementById('cauta');
	if (c) c.addEventListener('input', function () { text = c.value.trim().toLowerCase(); aplica(); });
})();
</script>
<?php
p45cd_sfarsit();
