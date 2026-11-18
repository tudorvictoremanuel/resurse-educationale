<?php
/**
 * Pagina pentru parolă: cerere link nou sau setarea parolei din linkul primit pe email.
 *
 * @var array $stare
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
require_once P45CD_DIR . 'templates/parts.php';

$titlu = 'seteaza' === $stare['mod'] ? 'Alege parola' : 'Ai uitat parola?';
p45cd_inceput( $titlu, 'pagina-simpla' );
?>
<main class="simplu">
	<div class="card-simplu">
		<?php p45cd_marca( false ); ?>
		<?php if ( 'seteaza' === $stare['mod'] ) : ?>
			<h1>Alege parola</h1>
			<p class="muted">Pentru contul <strong><?php echo esc_html( $stare['email'] ); ?></strong>. Folosește cel puțin 8 caractere.</p>
			<?php if ( $stare['eroare'] ) : ?>
				<p class="eroare" role="alert"><?php echo esc_html( $stare['eroare'] ); ?></p>
			<?php endif; ?>
			<form method="post" class="form" action="<?php echo esc_url( p45cd_url( 'parola' ) ); ?>">
				<?php wp_nonce_field( 'p45_parola', 'p45_nonce' ); ?>
				<input type="hidden" name="key" value="<?php echo esc_attr( $stare['key'] ); ?>">
				<input type="hidden" name="login" value="<?php echo esc_attr( $stare['login'] ); ?>">
				<label>Parola nouă
					<span class="parola-camp">
						<input type="password" name="parola1" id="p1" minlength="8" autocomplete="new-password" required autofocus>
						<button type="button" class="arata" data-tinta="p1">Arată</button>
					</span>
				</label>
				<label>Scrie parola încă o dată
					<input type="password" name="parola2" minlength="8" autocomplete="new-password" required>
				</label>
				<button type="submit" class="btn btn-rosu btn-mare">Salvează parola și intră</button>
			</form>
		<?php elseif ( 'trimis' === $stare['mod'] ) : ?>
			<h1>Verifică emailul</h1>
			<p class="info" role="status"><?php echo esc_html( $stare['mesaj'] ); ?></p>
			<p><a href="<?php echo esc_url( p45cd_url( 'intra' ) ); ?>">Înapoi la intrare</a></p>
		<?php else : ?>
			<h1>Ai uitat parola?</h1>
			<p class="muted">Scrie emailul contului și îți trimitem un link pentru o parolă nouă.</p>
			<?php if ( $stare['eroare'] ) : ?>
				<p class="eroare" role="alert"><?php echo esc_html( $stare['eroare'] ); ?></p>
			<?php endif; ?>
			<form method="post" class="form" action="<?php echo esc_url( p45cd_url( 'parola' ) ); ?>">
				<?php wp_nonce_field( 'p45_parola_cere', 'p45_nonce' ); ?>
				<label>Email
					<input type="email" name="email" autocomplete="username" required autofocus>
				</label>
				<button type="submit" class="btn btn-rosu btn-mare">Trimite-mi linkul</button>
			</form>
			<p><a href="<?php echo esc_url( p45cd_url( 'intra' ) ); ?>">Înapoi la intrare</a></p>
		<?php endif; ?>
	</div>
</main>
<script>
document.querySelectorAll('.arata').forEach(function (b) {
	b.addEventListener('click', function () {
		var i = document.getElementById(b.getAttribute('data-tinta'));
		var vizibil = i.type === 'text';
		i.type = vizibil ? 'password' : 'text';
		b.textContent = vizibil ? 'Arată' : 'Ascunde';
	});
});
</script>
<?php
p45cd_sfarsit();
