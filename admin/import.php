<?php
/**
 * Admin: pagina „Import din platforma veche”.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function p45cd_pagina_import() {
	global $wp_version;
	$pachete = p45cd_pachete_migrare();
	$harta   = p45cd_harta_coduri();
	$date    = p45cd_import_date();
	$info    = get_option( 'p45cd_import' );
	$rez     = get_option( 'p45cd_import_rezultat' );
	$jwt_on  = in_array( 'simple-jwt-login/simple-jwt-login.php', (array) get_option( 'active_plugins', array() ), true );

	/* Cărțile din pachete, cu starea lor în platformă. */
	$carti_pachet = array();
	foreach ( $pachete as $p ) {
		if ( empty( $p['manifest'] ) ) {
			continue;
		}
		foreach ( $p['manifest']['carti'] as $c ) {
			$cod                  = (int) $c['cod'];
			$carti_pachet[ $cod ] = array(
				'pachet' => $p['nume'],
				'cod'    => $cod,
				'titlu'  => (string) $c['titlu'],
				'pagini' => (int) $c['pagini'],
				'clase'  => implode( ', ', (array) $c['clase'] ),
				'id'     => isset( $harta[ $cod ] ) ? $harta[ $cod ] : 0,
			);
		}
	}
	uasort(
		$carti_pachet,
		function ( $a, $b ) {
			return strcmp( $a['titlu'], $b['titlu'] );
		}
	);
	$de_importat = array_values( array_filter( $carti_pachet, function ( $c ) { return ! $c['id']; } ) );
	$publice     = array_filter( $pachete, function ( $p ) { return $p['public']; } );
	$dir_rel     = str_replace( wp_normalize_path( ABSPATH ), '', wp_normalize_path( p45cd_dir_baza() ) );
	?>
	<div class="wrap p45-admin">
		<div class="p45-cap"><div>
			<h1 class="p45-h1">Import din platforma veche</h1>
			<p class="p45-sub">Aduce manualele și profesorii de pe resurse.edituraparalela45.ro. Profesorii păstrează emailul, parola și manualele pe care le aveau.</p>
		</div></div>
		<?php p45cd_afiseaza_mesaj_admin(); ?>
		<?php if ( version_compare( $wp_version, '6.8', '<' ) ) : ?>
			<div class="p45-mesaj p45-mesaj-eroare"><p>Actualizează WordPress la versiunea 6.8 sau mai nouă înainte de import. Versiunile mai vechi nu recunosc parolele din platforma veche.</p></div>
		<?php endif; ?>

		<div class="p45-coloane">
			<div class="p45-col-mare">

				<section class="p45-card" id="manuale" aria-labelledby="h-manuale">
					<h2 class="p45-h2" id="h-manuale">1. Manualele</h2>
					<?php if ( ! $pachete && $harta ) : ?>
						<p><span class="p45-chip p45-chip-verde">gata</span> <?php echo (int) count( $harta ); ?> manuale din platforma veche sunt în platformă (au „Cod CRM”). <a href="<?php echo esc_url( admin_url( 'admin.php?page=p45-carti' ) ); ?>">Vezi cărțile</a></p>
						<p class="p45-gri p45-mic">Pentru alte manuale din platforma veche, urcă arhivele <code>p45-migrare-…zip</code> în <code><?php echo esc_html( $dir_rel ); ?>/</code> și reîncarcă pagina.</p>
					<?php elseif ( ! $pachete ) : ?>
						<p>Urcă arhivele <code>p45-migrare-1.zip</code>, <code>p45-migrare-2.zip</code>… pe server, în dosarul:</p>
						<p><code><?php echo esc_html( $dir_rel ); ?>/</code></p>
						<p class="p45-gri p45-mic">Din panoul hostingului: Manager de fișiere → <code><?php echo esc_html( $dir_rel ); ?></code> → Încarcă. Nu le dezarhiva. Apoi reîncarcă această pagină.</p>
					<?php else : ?>
						<?php if ( $publice ) : ?>
							<div class="p45-mesaj p45-mesaj-eroare"><p>Arhivele sunt într-un dosar public (<code>wp-content/uploads</code>), unde oricine le poate descărca. Mută-le în <code><?php echo esc_html( $dir_rel ); ?>/</code> sau șterge-le imediat după import.</p></div>
						<?php endif; ?>
						<?php foreach ( $pachete as $p ) : ?>
							<?php if ( $p['eroare'] || empty( $p['manifest'] ) ) : ?>
								<div class="p45-mesaj p45-mesaj-eroare"><p><?php echo esc_html( $p['nume'] . ': ' . ( $p['eroare'] ? $p['eroare'] : 'arhiva nu poate fi citită. Urc-o din nou; probabil nu s-a încărcat complet.' ) ); ?></p></div>
							<?php endif; ?>
						<?php endforeach; ?>
						<p><?php echo (int) count( $carti_pachet ); ?> manuale în <?php echo (int) count( $pachete ); ?> arhive · <strong><?php echo (int) ( count( $carti_pachet ) - count( $de_importat ) ); ?></strong> sunt deja în platformă.</p>
						<div class="p45-tabel-cutie">
							<table class="p45-tabel">
								<thead><tr><th>Manual</th><th>Clasa</th><th class="num">Pagini</th><th class="num">Cod CRM</th><th>Stare</th></tr></thead>
								<tbody>
								<?php foreach ( $carti_pachet as $c ) : ?>
									<tr data-cod="<?php echo (int) $c['cod']; ?>">
										<td><?php echo $c['id'] ? '<a class="p45-titlu-link" href="' . esc_url( admin_url( 'admin.php?page=p45-carte&id=' . $c['id'] ) ) . '">' . esc_html( $c['titlu'] ) . '</a>' : esc_html( $c['titlu'] ); ?></td>
										<td class="p45-gri"><?php echo esc_html( $c['clase'] ); ?></td>
										<td class="num"><?php echo (int) $c['pagini']; ?></td>
										<td class="num"><?php echo (int) $c['cod']; ?></td>
										<td class="p45-stare-carte"><?php echo $c['id'] ? '<span class="p45-chip p45-chip-verde">În platformă</span>' : '<span class="p45-chip p45-chip-gri">De importat</span>'; ?></td>
									</tr>
								<?php endforeach; ?>
								</tbody>
							</table>
						</div>
						<?php if ( $de_importat ) : ?>
							<div class="p45-progres" id="p45-progres-carti" hidden>
								<div class="p45-progres-rand"><strong id="p45-carti-text">Pregătesc…</strong><span id="p45-carti-nr" class="p45-gri"></span></div>
								<div class="p45-bara"><span id="p45-carti-bara"></span></div>
								<p class="p45-gri p45-mic">Nu închide această pagină până la final.</p>
							</div>
							<p class="p45-actiuni"><button type="button" class="p45-btn p45-btn-rosu" id="p45-importa-carti">Importă <?php echo (int) count( $de_importat ); ?> manuale</button></p>
						<?php else : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="p45-inline">
								<?php wp_nonce_field( 'p45_import_sterge_pachete' ); ?>
								<input type="hidden" name="action" value="p45_import_sterge_pachete">
								<p class="p45-gri">Toate manualele sunt în platformă. Arhivele nu mai sunt necesare pe server.</p>
								<button type="submit" class="p45-btn p45-btn-alb">Șterge arhivele de pe server</button>
							</form>
						<?php endif; ?>
					<?php endif; ?>
				</section>

				<section class="p45-card" id="profesori" aria-labelledby="h-prof">
					<h2 class="p45-h2" id="h-prof">2. Profesorii</h2>
					<?php if ( ! $date ) : ?>
						<p>Exportă baza platformei vechi din phpMyAdmin: baza <code>…ep45resurse</code> → Exportă → Rapid → SQL → Execută. Apoi încarcă aici fișierul <code>.sql</code>.</p>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="p45-form">
							<?php wp_nonce_field( 'p45_import_sql' ); ?>
							<input type="hidden" name="action" value="p45_import_sql">
							<label class="p45-camp">Exportul bazei vechi (.sql sau .sql.gz, cel mult <?php echo esc_html( size_format( wp_max_upload_size() ) ); ?>)
								<input type="file" name="sql" accept=".sql,.gz" required>
							</label>
							<div><button type="submit" class="p45-btn p45-btn-rosu">Încarcă și verifică</button></div>
						</form>
						<?php $pe_server = p45cd_sql_pe_server(); ?>
						<?php if ( $pe_server ) : ?>
							<p>Sau folosește un export urcat deja pe server:</p>
							<?php foreach ( $pe_server as $n => $cale ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="p45-form-rand" style="align-items:center;margin:8px 0">
									<?php wp_nonce_field( 'p45_import_sql_server' ); ?>
									<input type="hidden" name="action" value="p45_import_sql_server">
									<input type="hidden" name="fisier" value="<?php echo esc_attr( $n ); ?>">
									<code><?php echo esc_html( $n ); ?></code> <span class="p45-gri"><?php echo esc_html( size_format( (int) filesize( $cale ) ) ); ?></span>
									<button type="submit" class="p45-btn p45-btn-alb">Citește acest fișier</button>
								</form>
							<?php endforeach; ?>
						<?php else : ?>
							<p class="p45-gri p45-mic">Dacă fișierul e prea mare pentru încărcare, urcă-l din managerul de fișiere al hostingului în <code><?php echo esc_html( $dir_rel ); ?>/</code> și reîncarcă pagina.</p>
						<?php endif; ?>
						<p class="p45-gri p45-mic">Fișierul conține emailuri și parole criptate. Îl citesc o dată, păstrez doar ce trebuie pentru import și îl șterg de pe server la final.</p>
					<?php else : ?>
						<?php
						$prof      = $date['profesori'];
						$nr_acces  = 0;
						$fara_carte = 0;
						foreach ( $date['manuale'] as $mid => $m ) {
							if ( empty( $harta[ $mid ] ) ) {
								$fara_carte += (int) $m['profesori'];
							}
						}
						foreach ( $prof as $u ) {
							$nr_acces += count( $u['manuale'] );
						}
						$ign = $date['ignorati'];
						?>
						<p>Din <strong><?php echo esc_html( $info['original'] ); ?></strong>: <strong><?php echo (int) count( $prof ); ?> profesori</strong>, cu <?php echo (int) $nr_acces; ?> accese la manuale.
							<?php if ( $ign['administrare'] || $ign['fara_email'] ) : ?>
								<span class="p45-gri">Nu import <?php echo (int) $ign['administrare']; ?> conturi de administrare<?php echo $ign['fara_email'] ? ' și ' . (int) $ign['fara_email'] . ' conturi fără email valid' : ''; ?>.</span>
							<?php endif; ?>
						</p>
						<h3 class="p45-h3">Manualele vechi și cărțile din platformă</h3>
						<div class="p45-tabel-cutie">
							<table class="p45-tabel">
								<thead><tr><th class="num">Cod</th><th>Manual pe platforma veche</th><th class="num">Profesori</th><th>Carte în platforma nouă</th></tr></thead>
								<tbody>
								<?php foreach ( $date['manuale'] as $mid => $m ) : ?>
									<tr>
										<td class="num"><?php echo (int) $mid; ?></td>
										<td><?php echo esc_html( $m['titlu'] ); ?></td>
										<td class="num"><?php echo (int) $m['profesori']; ?></td>
										<td><?php echo ! empty( $harta[ $mid ] ) ? '<a href="' . esc_url( admin_url( 'admin.php?page=p45-carte&id=' . $harta[ $mid ] ) ) . '">' . esc_html( get_the_title( $harta[ $mid ] ) ) . '</a>' : '<span class="p45-chip p45-chip-rosu">lipsește</span>'; ?></td>
									</tr>
								<?php endforeach; ?>
								</tbody>
							</table>
						</div>
						<?php if ( $fara_carte ) : ?>
							<p class="p45-gri">Pentru manualele care lipsesc, profesorii nu primesc acces acum (<?php echo (int) $fara_carte; ?> accese). Importă întâi manualele sau completează „Cod CRM” la cartea potrivită, apoi rulează importul din nou: nu creează dubluri.</p>
						<?php endif; ?>
						<?php if ( ! empty( $date['jwt'] ) ) : ?>
							<label class="p45-bifa"><input type="checkbox" id="p45-jwt" <?php checked( true ); ?>> <span>Copiază și setările modulului <em>Simple JWT Login</em>, prin care CRM-ul creează conturile.<?php echo $jwt_on ? '' : ' <span class="p45-gri">Modulul nu e încă activ pe acest site; setările se folosesc după ce îl instalezi.</span>'; ?></span></label>
						<?php endif; ?>
						<div class="p45-progres" id="p45-progres-prof" hidden>
							<div class="p45-progres-rand"><strong id="p45-prof-text">Pregătesc…</strong><span id="p45-prof-nr" class="p45-gri"></span></div>
							<div class="p45-bara"><span id="p45-prof-bara"></span></div>
							<p class="p45-gri p45-mic">Nu închide această pagină până la final.</p>
						</div>
						<div class="p45-actiuni" id="p45-prof-butoane">
							<button type="button" class="p45-btn p45-btn-rosu" id="p45-importa-prof" data-total="<?php echo (int) count( $prof ); ?>">Importă <?php echo (int) count( $prof ); ?> profesori</button>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="p45-inline">
								<?php wp_nonce_field( 'p45_import_renunta' ); ?>
								<input type="hidden" name="action" value="p45_import_renunta">
								<button type="submit" class="p45-btn p45-btn-alb">Renunță și șterge datele</button>
							</form>
						</div>
						<p class="p45-gri p45-mic">Nu se trimite niciun email. Profesorii intră cu emailul și parola de pe platforma veche. Importul se poate rula din nou oricând, cu un export mai nou: adaugă doar ce lipsește.</p>
					<?php endif; ?>
					<div id="p45-prof-rezultat">
					<?php if ( is_array( $rez ) && ! $date ) : ?>
						<?php $s = isset( $rez['statistici'] ) ? (array) $rez['statistici'] : array(); ?>
						<div class="p45-mesaj"><p><strong>Ultimul import: <?php echo esc_html( p45cd_data( (int) $rez['data'] ) ); ?></strong> (<?php echo esc_html( $rez['original'] ); ?>)</p>
							<ul>
								<li><?php echo (int) ( ( $s['creat'] ?? 0 ) + ( $s['creat_fara_parola'] ?? 0 ) ); ?> conturi noi, <?php echo (int) ( $s['actualizat'] ?? 0 ); ?> actualizate, <?php echo (int) ( $s['existent'] ?? 0 ); ?> existau deja</li>
								<li><?php echo (int) ( $s['acces'] ?? 0 ); ?> accese noi la manuale</li>
								<?php if ( ! empty( $s['creat_fara_parola'] ) ) : ?><li><?php echo (int) $s['creat_fara_parola']; ?> conturi au o parolă într-un format necunoscut: profesorii își fac parolă nouă cu „Ai uitat parola?”</li><?php endif; ?>
								<?php if ( ! empty( $s['ignorat'] ) || ! empty( $s['eroare'] ) ) : ?><li><?php echo (int) ( ( $s['ignorat'] ?? 0 ) + ( $s['eroare'] ?? 0 ) ); ?> conturi nu au fost importate (conturi de administrare pe acest site sau erori)</li><?php endif; ?>
							</ul>
						</div>
					<?php endif; ?>
					</div>
				</section>
			</div>

			<aside class="p45-col-mica">
				<section class="p45-card">
					<h2 class="p45-h2">3. CRM-ul</h2>
					<p class="p45-mic">CRM-ul creează conturi prin modulul <em>Simple JWT Login</em> și dă acces la manuale prin <code>/wp-json/ep45/v1/utilizatori</code>, ca pe platforma veche.</p>
					<ul class="p45-lista-pasi p45-mic">
						<li><?php echo $jwt_on ? '<span class="p45-chip p45-chip-verde">activ</span>' : '<span class="p45-chip p45-chip-galben">de instalat</span>'; ?> Modulul Simple JWT Login</li>
						<li><?php echo p45cd_setare( 'cheie_api' ) ? '<span class="p45-chip p45-chip-verde">setată</span>' : '<span class="p45-chip p45-chip-galben">lipsește</span>'; ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=p45-setari#crm' ) ); ?>">Cheia CRM-ului</a></li>
					</ul>
					<p class="p45-gri p45-mic">Manualele importate păstrează numărul din platforma veche („Cod CRM”), deci CRM-ul trimite aceleași numere ca acum.</p>
				</section>
				<section class="p45-card">
					<h2 class="p45-h2">Ordinea recomandată</h2>
					<ol class="p45-lista-pasi p45-mic">
						<li>Importă manualele.</li>
						<li>Importă profesorii.</li>
						<li>În ziua mutării: exportă din nou baza veche și rulează importul profesorilor încă o dată.</li>
						<li>Mută adresa resurse.edituraparalela45.ro pe platforma nouă.</li>
					</ol>
				</section>
			</aside>
		</div>
	</div>
	<script>
	(function () {
		const api = <?php echo wp_json_encode( esc_url_raw( rest_url( 'p45/v1/' ) ) ); ?>;
		const nonce = <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?>;
		const carti = <?php echo wp_json_encode( array_map( function ( $c ) { return array( 'pachet' => $c['pachet'], 'cod' => $c['cod'], 'titlu' => $c['titlu'] ); }, $de_importat ) ); ?>;
		const el = (id) => document.getElementById(id);

		async function cere(cale, date) {
			for (let incercare = 1; ; incercare++) {
				try {
					const r = await fetch(api + cale, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce }, body: JSON.stringify(date) });
					const j = await r.json().catch(() => ({}));
					if (!r.ok) throw new Error(j.message || ('Eroare ' + r.status));
					return j;
				} catch (e) {
					if (incercare >= 3) throw e;
					await new Promise((ok) => setTimeout(ok, 2000 * incercare));
				}
			}
		}
		function progres(pref, gata, total, text) {
			el(pref + '-text').textContent = text;
			el(pref + '-nr').textContent = gata + ' din ' + total;
			el(pref + '-bara').style.width = (total ? Math.round(gata * 100 / total) : 0) + '%';
		}
		window.addEventListener('beforeunload', (e) => { if (window.p45Lucrez) { e.preventDefault(); e.returnValue = ''; } });

		const bc = el('p45-importa-carti');
		if (bc) bc.addEventListener('click', async () => {
			bc.disabled = true; window.p45Lucrez = true;
			el('p45-progres-carti').hidden = false;
			let ok = 0; const erori = [];
			for (let i = 0; i < carti.length; i++) {
				const c = carti[i];
				progres('p45-carti', i, carti.length, 'Import „' + c.titlu + '”…');
				const rand = document.querySelector('tr[data-cod="' + c.cod + '"] .p45-stare-carte');
				try {
					await cere('import/carte', { pachet: c.pachet, cod: c.cod });
					ok++;
					if (rand) rand.innerHTML = '<span class="p45-chip p45-chip-verde">În platformă</span>';
				} catch (e) {
					erori.push(c.titlu + ': ' + e.message);
					if (rand) rand.innerHTML = '<span class="p45-chip p45-chip-rosu">eroare</span>';
				}
			}
			window.p45Lucrez = false;
			progres('p45-carti', carti.length, carti.length, erori.length ? ('Gata, cu ' + erori.length + ' erori. Apasă din nou pentru cele rămase.') : ('Gata: ' + ok + ' manuale importate.'));
			if (erori.length) { alertaErori(erori); bc.disabled = false; } else { setTimeout(() => location.reload(), 1200); }
		});

		function alertaErori(erori) {
			const d = document.createElement('div');
			d.className = 'p45-mesaj p45-mesaj-eroare';
			d.innerHTML = '<p>Nu s-au putut importa:</p><ul></ul>';
			erori.forEach((x) => { const li = document.createElement('li'); li.textContent = x; d.querySelector('ul').appendChild(li); });
			el('p45-progres-carti').after(d);
		}

		const bp = el('p45-importa-prof');
		let de_la = 0;
		if (bp) bp.addEventListener('click', async () => {
			const total = parseInt(bp.dataset.total, 10) || 0;
			bp.disabled = true; window.p45Lucrez = true;
			el('p45-prof-butoane').hidden = true;
			el('p45-progres-prof').hidden = false;
			const jwt = el('p45-jwt') ? el('p45-jwt').checked : false;
			try {
				while (true) {
					progres('p45-prof', de_la, total, 'Import profesori…');
					const r = await cere('import/profesori', { de_la, lot: 40, jwt });
					de_la = r.urmatorul;
					if (r.gata) break;
				}
				window.p45Lucrez = false;
				progres('p45-prof', total, total, 'Gata.');
				setTimeout(() => location.reload(), 800);
			} catch (e) {
				window.p45Lucrez = false;
				progres('p45-prof', de_la, total, 'Importul s-a oprit: ' + e.message);
				el('p45-prof-butoane').hidden = false; bp.disabled = false;
				bp.textContent = 'Reia importul';
			}
		});
	})();
	</script>
	<?php
}
