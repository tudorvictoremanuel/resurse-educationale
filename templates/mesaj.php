<?php
/**
 * Pagină de mesaj (fără acces, carte inexistentă).
 *
 * @var string $mesaj_titlu
 * @var string $mesaj_text
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
require_once P45CD_DIR . 'templates/parts.php';

p45cd_inceput( $mesaj_titlu, 'pagina-simpla' );
?>
<main class="simplu">
	<div class="card-simplu">
		<?php p45cd_marca(); ?>
		<h1><?php echo esc_html( $mesaj_titlu ); ?></h1>
		<p class="muted"><?php echo esc_html( $mesaj_text ); ?></p>
		<p class="muted">Ajutor: <?php echo p45cd_email_suport_html(); // phpcs:ignore ?></p>
		<a class="btn btn-rosu" href="<?php echo esc_url( p45cd_url( 'biblioteca' ) ); ?>">Înapoi la bibliotecă</a>
	</div>
</main>
<?php
p45cd_sfarsit();
