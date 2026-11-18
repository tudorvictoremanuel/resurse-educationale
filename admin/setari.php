<?php
/**
 * Admin: setări.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'admin_post_p45_setari',
	function () {
		p45cd_verifica_admin( 'p45_setari' );
		$s                 = wp_parse_args( (array) get_option( 'p45cd_setari', array() ), p45cd_setari_implicite() );
		$s['email_suport'] = isset( $_POST['email_suport'] ) ? sanitize_email( wp_unslash( $_POST['email_suport'] ) ) : '';
		$s['zile_link']    = max( 1, min( 60, isset( $_POST['zile_link'] ) ? (int) $_POST['zile_link'] : 7 ) );
		$lat               = isset( $_POST['latime'] ) ? (int) $_POST['latime'] : 1400;
		$s['latime']       = in_array( $lat, array( 1000, 1200, 1400, 1600, 1800 ), true ) ? $lat : 1400;
		$s['calitate']     = max( 50, min( 95, isset( $_POST['calitate'] ) ? (int) $_POST['calitate'] : 80 ) );
		$s['cheie_api']    = isset( $_POST['cheie_api'] ) ? mb_substr( trim( sanitize_text_field( wp_unslash( $_POST['cheie_api'] ) ) ), 0, 200 ) : '';
		update_option( 'p45cd_setari', $s );
		p45cd_mesaj_admin( 'ok', 'Setările au fost salvate.' );
		wp_safe_redirect( admin_url( 'admin.php?page=p45-setari' ) );
		exit;
	}
);

add_action(
	'admin_post_p45_test_email',
	function () {
		p45cd_verifica_admin( 'p45_test_email' );
		$u  = wp_get_current_user();
		$ok = p45cd_trimite( $u->user_email, 'Test – cărți digitale Paralela 45', p45cd_email_html( '<p>Emailurile platformei ajung la destinatari.</p>' ) );
		p45cd_mesaj_admin( $ok ? 'ok' : 'eroare', $ok ? 'Am trimis un email de test la ' . $u->user_email . '. Verifică și dosarul Spam.' : 'Emailul nu a putut fi trimis. Instalează un modul SMTP (de exemplu WP Mail SMTP) și configurează-l cu datele de email ale editurii.' );
		wp_safe_redirect( admin_url( 'admin.php?page=p45-setari' ) );
		exit;
	}
);

function p45cd_pagina_setari() {
	$s = wp_parse_args( (array) get_option( 'p45cd_setari', array() ), p45cd_setari_implicite() );
	$nr_carti = (int) wp_count_posts( 'p45_carte' )->publish;
	?>
	<div class="wrap p45-admin">
		<div class="p45-cap"><div><h1 class="p45-h1">Setări</h1></div></div>
		<?php p45cd_afiseaza_mesaj_admin(); ?>
		<div class="p45-coloane">
			<section class="p45-card p45-col-mare">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="p45-form">
					<?php wp_nonce_field( 'p45_setari' ); ?>
					<input type="hidden" name="action" value="p45_setari">
					<h2 class="p45-h2">Ajutor pentru profesori</h2>
					<label class="p45-camp p45-camp-scurt">Email de suport
						<input type="email" name="email_suport" value="<?php echo esc_attr( $s['email_suport'] ); ?>">
						<span class="p45-gri">Apare pe pagina de intrare, în bibliotecă și în emailuri.</span>
					</label>
					<label class="p45-camp p45-camp-scurt">Linkul pentru parolă e valabil (zile)
						<input type="number" name="zile_link" min="1" max="60" value="<?php echo (int) $s['zile_link']; ?>">
					</label>

					<h2 class="p45-h2">Calitatea paginilor</h2>
					<p class="p45-gri">Se aplică la cărțile convertite de acum înainte.</p>
					<label class="p45-camp p45-camp-scurt">Lățimea paginilor
						<select name="latime">
							<?php
							$opt = array(
								1000 => '1000 px – fișiere mici, pentru internet slab',
								1200 => '1200 px',
								1400 => '1400 px – recomandat pentru tablă',
								1600 => '1600 px',
								1800 => '1800 px – cea mai bună calitate, fișiere mari',
							);
							foreach ( $opt as $k => $t ) {
								echo '<option value="' . (int) $k . '" ' . selected( (int) $s['latime'], $k, false ) . '>' . esc_html( $t ) . '</option>';
							}
							?>
						</select>
					</label>
					<label class="p45-camp p45-camp-scurt">Calitatea imaginilor (50–95)
						<input type="number" name="calitate" min="50" max="95" value="<?php echo (int) $s['calitate']; ?>">
						<span class="p45-gri">80 e un echilibru bun între claritate și mărime.</span>
					</label>

					<h2 class="p45-h2" id="crm">Legătura cu CRM-ul</h2>
					<p class="p45-gri">CRM-ul dă acces profesorilor prin <code><?php echo esc_html( rest_url( 'ep45/v1/utilizatori/' ) ); ?></code>, ca pe platforma veche. Manualele se recunosc după câmpul „Cod CRM” al fiecărei cărți.</p>
					<label class="p45-camp">Cheia CRM-ului (AUTH_KEY)
						<input type="text" name="cheie_api" value="<?php echo esc_attr( $s['cheie_api'] ); ?>" autocomplete="off" spellcheck="false">
						<span class="p45-gri">Aceeași cheie pe care o trimite CRM-ul acum. Lasă gol ca să oprești legătura.</span>
					</label>
					<div><button type="submit" class="p45-btn p45-btn-rosu">Salvează setările</button></div>
				</form>
			</section>
			<aside class="p45-col-mica">
				<section class="p45-card">
					<h2 class="p45-h2">Adresele platformei</h2>
					<ul class="p45-lista-pasi">
						<li>Intrare: <a href="<?php echo esc_url( p45cd_url( 'intra' ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( p45cd_url( 'intra' ) ); ?></a></li>
						<li>Biblioteca: <a href="<?php echo esc_url( p45cd_url( 'biblioteca' ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( p45cd_url( 'biblioteca' ) ); ?></a></li>
					</ul>
				</section>
				<section class="p45-card">
					<h2 class="p45-h2">Stocare</h2>
					<p><?php echo (int) $nr_carti; ?> cărți publicate · <?php echo esc_html( p45cd_marime( p45cd_marime_stocare() ) ); ?> ocupați</p>
					<p class="p45-gri p45-mic">Paginile stau în <code>wp-content/uploads/p45-carti/</code> și se servesc doar profesorilor cu acces.</p>
				</section>
				<section class="p45-card">
					<h2 class="p45-h2">Emailuri</h2>
					<p class="p45-gri p45-mic">Invitațiile pleacă prin emailul site-ului. Dacă nu ajung, instalează un modul SMTP.</p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'p45_test_email' ); ?>
						<input type="hidden" name="action" value="p45_test_email">
						<button type="submit" class="p45-btn p45-btn-alb">Trimite-mi un email de test</button>
					</form>
				</section>
			</aside>
		</div>
	</div>
	<?php
}
