<?php
/**
 * Pagina de intrare în cont.
 *
 * @var string $eroare
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
require_once P45CD_DIR . 'templates/parts.php';

/* Coperte pentru colaj: cele trei coperte alese de editură (assets/img/coperta-1..3.webp).
   Nu le înlocuim cu copertele cărților din platformă: ultimele cărți adăugate nu sunt neapărat cele de prezentare. */
$coperte = apply_filters(
	'p45cd_coperte_intrare',
	array( P45CD_URL . 'assets/img/coperta-1.webp', P45CD_URL . 'assets/img/coperta-2.webp', P45CD_URL . 'assets/img/coperta-3.webp' )
);
$redirect = isset( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : '';
$email    = isset( $_POST['email'] ) ? sanitize_text_field( wp_unslash( $_POST['email'] ) ) : '';

p45cd_inceput( 'Intră în cont', 'pagina-intrare' );
?>
<div class="intrare">
	<section class="intrare-stanga" aria-label="Despre platformă">
		<?php p45cd_marca( false ); ?>
		<div class="intrare-titlu">
			<h2>Cărțile tale, pe tabla din clasă.</h2>
			<p>Fiecare carte comandată la Paralela 45 vine și în variantă digitală. O deschizi pe tabla interactivă, desenezi peste pagini și îți păstrezi notele de la o oră la alta.</p>
		</div>
		<div class="colaj" aria-hidden="true">
			<img class="c1" src="<?php echo esc_url( $coperte[0] ); ?>" alt="">
			<img class="c2" src="<?php echo esc_url( $coperte[1] ); ?>" alt="">
			<img class="c3" src="<?php echo esc_url( $coperte[2] ); ?>" alt="">
		</div>
	</section>

	<section class="intrare-dreapta" aria-labelledby="titlu-intrare">
		<div class="intrare-form">
			<div>
				<h1 id="titlu-intrare">Intră în cont</h1>
				<p class="muted">Folosește emailul cu care ai comandat cărțile.</p>
			</div>
			<?php if ( ! empty( $_GET['iesit'] ) && ! $eroare ) : ?>
				<p class="info" role="status">Ai ieșit din cont.</p>
			<?php endif; ?>
			<?php if ( $eroare ) : ?>
				<p class="eroare" role="alert"><?php echo esc_html( $eroare ); ?></p>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( p45cd_url( 'intra' ) ); ?>" class="form">
				<?php wp_nonce_field( 'p45_intra', 'p45_nonce' ); ?>
				<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect ); ?>">
				<label>Email
					<input type="text" inputmode="email" name="email" value="<?php echo esc_attr( $email ); ?>" autocomplete="username" autocapitalize="none" spellcheck="false" required autofocus>
				</label>
				<label>Parolă
					<span class="parola-camp">
						<input type="password" name="parola" id="parola" autocomplete="current-password" required>
						<button type="button" class="arata" data-tinta="parola">Arată</button>
					</span>
				</label>
				<div class="rand-mic">
					<label class="bifa"><input type="checkbox" name="tine_minte" value="1"> Ține-mă minte pe acest calculator</label>
					<a href="<?php echo esc_url( p45cd_url( 'parola' ) ); ?>">Ai uitat parola?</a>
				</div>
				<button type="submit" class="btn btn-rosu btn-mare">Intră în cont</button>
			</form>
			<p class="nota-gri">Contul îl creează editura, odată cu comanda cărților. Nu ai primit emailul cu datele de acces? Scrie-ne la <?php echo p45cd_email_suport_html(); // phpcs:ignore ?>.</p>
		</div>
	</section>
</div>
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
