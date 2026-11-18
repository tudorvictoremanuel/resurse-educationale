<?php
/**
 * Admin: lista cărților și pagina „Adaugă / editează carte” (conversie PDF în browser).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ------------------------------------------------------------------ */
/* Lista cărților                                                      */
/* ------------------------------------------------------------------ */

function p45cd_pagina_carti() {
	$q     = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
	$posts = get_posts(
		array(
			'post_type'      => 'p45_carte',
			'post_status'    => array( 'publish', 'draft' ),
			'posts_per_page' => -1,
			's'              => $q,
			'orderby'        => 'title',
			'order'          => 'ASC',
		)
	);
	?>
	<div class="wrap p45-admin">
		<div class="p45-cap">
			<div>
				<h1 class="p45-h1">Cărți</h1>
				<p class="p45-sub">Cărțile digitale din platformă. Profesorii văd doar cărțile publicate la care au acces.</p>
			</div>
			<a class="p45-btn p45-btn-rosu" href="<?php echo esc_url( admin_url( 'admin.php?page=p45-carte' ) ); ?>">Adaugă carte din PDF</a>
		</div>
		<?php p45cd_afiseaza_mesaj_admin(); ?>

		<?php if ( ! $posts && ! $q ) : ?>
			<div class="p45-card p45-gol">
				<span class="dashicons dashicons-book-alt" aria-hidden="true"></span>
				<h2>Nu ai încă nicio carte</h2>
				<p>Încarcă PDF-ul unei cărți: platforma îl transformă în carte digitală, cu pagini care se răsfoiesc și cuprins.</p>
				<a class="p45-btn p45-btn-rosu" href="<?php echo esc_url( admin_url( 'admin.php?page=p45-carte' ) ); ?>">Adaugă prima carte</a>
			</div>
		<?php else : ?>
			<form method="get" class="p45-cauta-form">
				<input type="hidden" name="page" value="p45-carti">
				<label class="screen-reader-text" for="p45-s">Caută cărți</label>
				<input type="search" id="p45-s" name="s" value="<?php echo esc_attr( $q ); ?>" placeholder="Caută după titlu">
				<button class="p45-btn p45-btn-alb" type="submit">Caută</button>
			</form>
			<div class="p45-card p45-fara-padding">
				<div class="p45-tabel-cutie">
				<table class="p45-tabel">
					<thead>
						<tr>
							<th scope="col" style="width:64px"><span class="screen-reader-text">Copertă</span></th>
							<th scope="col">Titlu</th>
							<th scope="col">Clasă</th>
							<th scope="col">Disciplină</th>
							<th scope="col" class="num">Pagini</th>
							<th scope="col" class="num">Profesori</th>
							<th scope="col">Stare</th>
							<th scope="col"><span class="screen-reader-text">Acțiuni</span></th>
						</tr>
					</thead>
					<tbody>
					<?php if ( ! $posts ) : ?>
						<tr><td colspan="8" class="p45-gol-rand">Nicio carte nu se potrivește căutării.</td></tr>
					<?php endif; ?>
					<?php
					foreach ( $posts as $p ) :
						$c = p45cd_carte( $p );
						if ( ! $c['gata'] ) {
							$stare = array( 'Conversie neterminată', 'p45-chip-rosu' );
						} elseif ( 'publish' === $c['status'] ) {
							$stare = array( 'Publicată', 'p45-chip-verde' );
						} else {
							$stare = array( 'Ciornă', 'p45-chip-gri' );
						}
						$edit = admin_url( 'admin.php?page=p45-carte&id=' . $c['id'] );
						?>
						<tr>
							<td>
								<?php if ( $c['coperta'] ) : ?>
									<img class="p45-mini-coperta" src="<?php echo esc_url( $c['coperta'] ); ?>" alt="">
								<?php else : ?>
									<span class="p45-mini-coperta p45-mini-gol" aria-hidden="true"></span>
								<?php endif; ?>
							</td>
							<td>
								<a class="p45-titlu-link" href="<?php echo esc_url( $edit ); ?>"><?php echo esc_html( $c['titlu'] ); ?></a>
								<div class="p45-gri"><?php echo esc_html( implode( ' · ', array_filter( array( $c['autor'], $c['isbn'] ? 'ISBN ' . $c['isbn'] : '', $c['cod'] ? 'Cod CRM ' . $c['cod'] : '' ) ) ) ); ?></div>
							</td>
							<td><?php echo esc_html( p45cd_nume_termeni( $c['clase'] ) ); ?></td>
							<td><?php echo esc_html( p45cd_nume_termeni( $c['discipline'] ) ); ?></td>
							<td class="num"><?php echo $c['pagini'] ? (int) $c['pagini'] : '—'; ?></td>
							<td class="num"><a href="<?php echo esc_url( admin_url( 'admin.php?page=p45-profesori&carte=' . $c['id'] ) ); ?>"><?php echo (int) p45cd_numar_acces_carte( $c['id'] ); ?></a></td>
							<td><span class="p45-chip <?php echo esc_attr( $stare[1] ); ?>"><?php echo esc_html( $stare[0] ); ?></span></td>
							<td class="p45-actiuni">
								<?php if ( $c['gata'] ) : ?>
									<a href="<?php echo esc_url( $c['url'] ); ?>" target="_blank" rel="noopener">Deschide</a>
								<?php endif; ?>
								<a href="<?php echo esc_url( $edit ); ?>">Editează</a>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="p45-inline" onsubmit="return confirm('Ștergi definitiv cartea „<?php echo esc_js( $c['titlu'] ); ?>”? Profesorii nu o vor mai vedea, iar notele și desenele lor de pe ea se pierd.');">
									<?php wp_nonce_field( 'p45_sterge_carte_' . $c['id'] ); ?>
									<input type="hidden" name="action" value="p45_sterge_carte">
									<input type="hidden" name="id" value="<?php echo (int) $c['id']; ?>">
									<button type="submit" class="p45-link-rosu">Șterge</button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				</div>
			</div>
		<?php endif; ?>
	</div>
	<?php
}

add_action(
	'admin_post_p45_sterge_carte',
	function () {
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		p45cd_verifica_admin( 'p45_sterge_carte_' . $id );
		$post = get_post( $id );
		if ( $post && 'p45_carte' === $post->post_type ) {
			$t = get_the_title( $id );
			p45cd_sterge_carte( $id );
			p45cd_mesaj_admin( 'ok', 'Cartea „' . $t . '” a fost ștearsă.' );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=p45-carti' ) );
		exit;
	}
);

/* ------------------------------------------------------------------ */
/* Adaugă / editează carte                                             */
/* ------------------------------------------------------------------ */

function p45cd_pagina_carte() {
	$id    = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
	$carte = $id ? p45cd_carte( $id ) : null;
	if ( $id && ! $carte ) {
		echo '<div class="wrap p45-admin"><h1 class="p45-h1">Cartea nu există</h1><p><a href="' . esc_url( admin_url( 'admin.php?page=p45-carti' ) ) . '">Înapoi la cărți</a></p></div>';
		return;
	}
	$clase = p45cd_clase_ordonate();
	$disc  = get_terms( array( 'taxonomy' => 'p45_disciplina', 'hide_empty' => false, 'orderby' => 'name' ) );
	$disc  = is_wp_error( $disc ) ? array() : $disc;
	$sel_c = $carte ? wp_list_pluck( $carte['clase'], 'term_id' ) : array();
	$sel_d = $carte ? wp_list_pluck( $carte['discipline'], 'term_id' ) : array();
	$v     = P45CD_VERSION;
	$cfg   = array(
		'rest'     => esc_url_raw( rest_url( 'p45/v1/' ) ),
		'nonce'    => wp_create_nonce( 'wp_rest' ),
		'id'       => $id,
		'gata'     => $carte ? $carte['gata'] : false,
		'areCoperta' => $carte && $carte['coperta'] ? true : false,
		'latime'   => (int) p45cd_setare( 'latime' ),
		'calitate' => max( 50, min( 95, (int) p45cd_setare( 'calitate' ) ) ) / 100,
		'pdfjs'    => P45CD_URL . 'assets/vendor/pdfjs/pdf.min.mjs?v=' . $v,
		'worker'   => P45CD_URL . 'assets/vendor/pdfjs/pdf.worker.min.mjs?v=' . $v,
		'fonturi'  => P45CD_URL . 'assets/vendor/pdfjs/standard_fonts/',
		'wasm'     => P45CD_URL . 'assets/vendor/pdfjs/wasm/',
		'icc'      => P45CD_URL . 'assets/vendor/pdfjs/iccs/',
		'lista'    => admin_url( 'admin.php?page=p45-carti' ),
		'editare'  => admin_url( 'admin.php?page=p45-carte&id=' ),
		'uploadMax' => (int) wp_max_upload_size(),
	);
	$titlu_pagina = $carte ? 'Editează cartea' : 'Adaugă o carte din PDF';
	?>
	<div class="wrap p45-admin" id="p45-editor">
		<div class="p45-cap">
			<div>
				<p class="p45-cale"><a href="<?php echo esc_url( admin_url( 'admin.php?page=p45-carti' ) ); ?>">Cărți</a> / <?php echo $carte ? esc_html( $carte['titlu'] ) : 'Carte nouă'; ?></p>
				<h1 class="p45-h1"><?php echo esc_html( $titlu_pagina ); ?></h1>
			</div>
			<?php if ( $carte && $carte['gata'] ) : ?>
				<a class="p45-btn p45-btn-alb" href="<?php echo esc_url( $carte['url'] ); ?>" target="_blank" rel="noopener">Deschide în cititor</a>
			<?php endif; ?>
		</div>
		<?php p45cd_afiseaza_mesaj_admin(); ?>
		<?php if ( $carte && ! empty( $_GET['salvat'] ) ) : ?>
			<div class="p45-mesaj" role="status">
				<p><strong>Cartea a fost salvată.</strong> <?php echo 'publish' === $carte['status'] ? 'E publicată: o văd profesorii cărora le dai acces.' : 'E ciornă: o vezi doar tu.'; ?></p>
				<p class="p45-linkuri">
					<?php if ( $carte['gata'] ) : ?><a href="<?php echo esc_url( $carte['url'] ); ?>" target="_blank" rel="noopener">Deschide în cititor</a><?php endif; ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=p45-profesori&carte=' . $carte['id'] ) ); ?>">Dă acces profesorilor</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=p45-carte' ) ); ?>">Adaugă altă carte</a>
				</p>
			</div>
		<?php endif; ?>
		<div id="p45-rezultat" class="p45-mesaj" hidden></div>

		<div class="p45-coloane">
			<div class="p45-col-mare">

				<section class="p45-card" aria-labelledby="sec-pdf">
					<h2 id="sec-pdf" class="p45-h2">1. Fișierul PDF</h2>
					<?php if ( $carte && $carte['gata'] ) : ?>
						<div class="p45-fisier" id="p45-fisier-curent">
							<span class="p45-pdf-icon">PDF</span>
							<div class="p45-fisier-txt">
								<strong><?php echo esc_html( $carte['sursa'] ? $carte['sursa'] : 'PDF convertit' ); ?></strong>
								<span class="p45-gri"><?php echo (int) $carte['pagini']; ?> pagini în cartea digitală</span>
							</div>
							<button type="button" class="p45-btn p45-btn-alb" id="p45-inlocuieste">Înlocuiește PDF-ul</button>
						</div>
					<?php endif; ?>
					<label class="p45-drop" id="p45-drop" <?php echo ( $carte && $carte['gata'] ) ? 'hidden' : ''; ?>>
						<input type="file" id="p45-pdf" accept="application/pdf,.pdf">
						<span class="dashicons dashicons-upload" aria-hidden="true"></span>
						<strong>Alege PDF-ul cărții sau trage-l aici</strong>
						<span class="p45-gri">PDF-ul de tipar (interiorul). Fișierul nu se urcă pe server: paginile se pregătesc aici, în browser, și se urcă doar imaginile lor.</span>
					</label>
					<div id="p45-analiza" hidden>
						<div class="p45-fisier">
							<span class="p45-pdf-icon">PDF</span>
							<div class="p45-fisier-txt">
								<strong id="p45-nume-pdf"></strong>
								<span class="p45-gri" id="p45-info-pdf"></span>
							</div>
							<button type="button" class="p45-btn p45-btn-alb" id="p45-alt-pdf">Alt fișier</button>
						</div>
						<ul class="p45-verificari" id="p45-verificari"></ul>
					</div>
				</section>

				<section class="p45-card" aria-labelledby="sec-det">
					<h2 id="sec-det" class="p45-h2">2. Detalii</h2>
					<div class="p45-grila-campuri">
						<label class="p45-camp p45-camp-lat"><span>Titlu <span class="p45-oblig">obligatoriu</span></span>
							<input type="text" id="p45-titlu" value="<?php echo esc_attr( $carte ? $carte['titlu'] : '' ); ?>" required>
						</label>
						<label class="p45-camp">Autor / autori
							<input type="text" id="p45-autor" value="<?php echo esc_attr( $carte ? $carte['autor'] : '' ); ?>">
						</label>
						<label class="p45-camp">ISBN
							<input type="text" id="p45-isbn" value="<?php echo esc_attr( $carte ? $carte['isbn'] : '' ); ?>">
						</label>
						<label class="p45-camp">An apariție
							<input type="text" id="p45-an" inputmode="numeric" value="<?php echo esc_attr( $carte ? $carte['an'] : '' ); ?>">
						</label>
						<label class="p45-camp">Cod CRM
							<input type="number" id="p45-cod" min="1" step="1" value="<?php echo esc_attr( $carte && $carte['cod'] ? $carte['cod'] : '' ); ?>">
							<span class="p45-gri p45-mic">Numărul cu care CRM-ul dă acces la carte. Opțional.</span>
						</label>
					</div>
					<div class="p45-grila-campuri">
						<fieldset class="p45-camp">
							<legend>Clasă</legend>
							<div class="p45-bife" id="p45-clase">
								<?php foreach ( $clase as $t ) : ?>
									<label><input type="checkbox" value="<?php echo (int) $t->term_id; ?>" <?php checked( in_array( $t->term_id, $sel_c, true ) ); ?>> <?php echo esc_html( $t->name ); ?></label>
								<?php endforeach; ?>
							</div>
							<a class="p45-mic" href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=p45_clasa&post_type=p45_carte' ) ); ?>">Adaugă sau redenumește clase</a>
						</fieldset>
						<fieldset class="p45-camp">
							<legend>Disciplină</legend>
							<div class="p45-bife" id="p45-discipline">
								<?php foreach ( $disc as $t ) : ?>
									<label><input type="checkbox" value="<?php echo (int) $t->term_id; ?>" <?php checked( in_array( $t->term_id, $sel_d, true ) ); ?>> <?php echo esc_html( $t->name ); ?></label>
								<?php endforeach; ?>
							</div>
							<a class="p45-mic" href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=p45_disciplina&post_type=p45_carte' ) ); ?>">Adaugă sau redenumește discipline</a>
						</fieldset>
					</div>
				</section>

				<section class="p45-card" aria-labelledby="sec-toc">
					<div class="p45-cap-sec">
						<div>
							<h2 id="sec-toc" class="p45-h2">3. Cuprins</h2>
							<p class="p45-gri" id="p45-toc-hint"><?php echo $carte ? 'Profesorii sar la capitol din cuprins. Poți corecta orice rând.' : 'Se completează singur după ce alegi PDF-ul. Poți corecta orice rând.'; ?></p>
						</div>
						<div class="p45-butoane-mici">
							<button type="button" class="p45-btn p45-btn-alb" id="p45-toc-detecteaza" hidden>Caută din nou în PDF</button>
							<button type="button" class="p45-btn p45-btn-alb" id="p45-toc-adauga">Adaugă un rând</button>
						</div>
					</div>
					<div class="p45-tabel-cutie">
						<table class="p45-tabel p45-toc">
							<thead><tr><th scope="col" style="width:30%">Titlu</th><th scope="col">Subtitlu</th><th scope="col" style="width:90px">Pagina</th><th scope="col" style="width:48px"><span class="screen-reader-text">Șterge</span></th></tr></thead>
							<tbody id="p45-toc-rows"></tbody>
						</table>
					</div>
					<p class="p45-gri p45-mic" id="p45-toc-gol">Nu are încă rânduri. Fără cuprins, profesorii navighează din miniaturi.</p>
				</section>
			</div>

			<aside class="p45-col-mica">
				<section class="p45-card" aria-labelledby="sec-cop">
					<h2 id="sec-cop" class="p45-h2">Copertă</h2>
					<div class="p45-coperta-prev" id="p45-coperta-prev">
						<?php if ( $carte && $carte['coperta'] ) : ?>
							<img src="<?php echo esc_url( $carte['coperta'] ); ?>" alt="Coperta actuală">
						<?php else : ?>
							<span class="p45-gri">Fără copertă</span>
						<?php endif; ?>
					</div>
					<label class="p45-btn p45-btn-alb p45-btn-fisier">
						<input type="file" id="p45-coperta" accept="image/jpeg,image/png,image/webp">
						Alege imaginea copertei
					</label>
					<p class="p45-gri p45-mic" id="p45-coperta-hint"><?php echo ( $carte && $carte['coperta'] ) ? 'JPG, PNG sau WebP.' : 'JPG, PNG sau WebP. Dacă nu alegi una, folosim prima pagină din PDF.'; ?></p>
				</section>

				<section class="p45-card" aria-labelledby="sec-pub">
					<h2 id="sec-pub" class="p45-h2">Publicare</h2>
					<fieldset class="p45-radio">
						<legend class="screen-reader-text">Stare</legend>
						<label><input type="radio" name="p45-status" value="publish" <?php checked( ! $carte || 'publish' === $carte['status'] ); ?>> Publicată <span class="p45-gri">(o văd profesorii cu acces)</span></label>
						<label><input type="radio" name="p45-status" value="draft" <?php checked( $carte && 'draft' === $carte['status'] ); ?>> Ciornă <span class="p45-gri">(o vezi doar tu)</span></label>
					</fieldset>
					<button type="button" class="p45-btn p45-btn-rosu p45-btn-lat" id="p45-salveaza"><?php echo $carte ? 'Salvează modificările' : 'Convertește și salvează'; ?></button>
					<div class="p45-progres" id="p45-progres" hidden>
						<div class="p45-progres-rand"><strong id="p45-progres-text">Pregătesc…</strong><span id="p45-progres-nr" class="p45-gri"></span></div>
						<div class="p45-bara"><span id="p45-bara"></span></div>
						<p class="p45-gri p45-mic" id="p45-progres-eta">Nu închide această pagină până la final.</p>
						<button type="button" class="p45-btn p45-btn-alb" id="p45-opreste">Oprește</button>
					</div>
					<?php if ( $carte ) : ?>
						<p class="p45-mic"><a href="<?php echo esc_url( admin_url( 'admin.php?page=p45-profesori&carte=' . $carte['id'] ) ); ?>">Dă acces unui profesor la această carte</a></p>
					<?php endif; ?>
				</section>

				<?php if ( $carte ) : ?>
				<section class="p45-card">
					<h2 class="p45-h2">Ștergere</h2>
					<p class="p45-gri p45-mic">Cartea dispare din biblioteca tuturor profesorilor.</p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Ștergi definitiv cartea? Profesorii nu o vor mai vedea, iar notele și desenele lor de pe ea se pierd.');">
						<?php wp_nonce_field( 'p45_sterge_carte_' . $carte['id'] ); ?>
						<input type="hidden" name="action" value="p45_sterge_carte">
						<input type="hidden" name="id" value="<?php echo (int) $carte['id']; ?>">
						<button type="submit" class="p45-btn p45-btn-alb p45-rosu-text">Șterge cartea</button>
					</form>
				</section>
				<?php endif; ?>
			</aside>
		</div>
	</div>
	<script>window.P45A = <?php echo wp_json_encode( $cfg ); ?>; window.P45A_TOC = <?php echo wp_json_encode( $carte ? $carte['toc'] : array() ); ?>;</script>
	<script type="module" src="<?php echo esc_url( P45CD_URL . 'assets/js/convertor.js?v=' . P45CD_VERSION . '.' . filemtime( P45CD_DIR . 'assets/js/convertor.js' ) ); ?>"></script>
	<?php
}
