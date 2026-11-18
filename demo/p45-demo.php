<?php
/**
 * Plugin Name: Cărți digitale – mod demo
 * Description: Datele de intrare pentru demo-ul din WordPress Playground. Nu se folosește pe un site real.
 */

defined( 'ABSPATH' ) || exit;

/** Caseta cu datele de intrare, pe pagina de intrare a profesorilor. */
add_action(
	'init',
	function () {
		if ( is_admin() || wp_doing_ajax() || isset( $_GET['p45_img'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		if ( ! preg_match( '~/intra/?(\?|$)~', $uri ) ) {
			return;
		}
		ob_start(
			function ( $html ) {
				if ( false === strpos( $html, '<form method="post"' ) ) {
					return $html;
				}
				$panou = esc_attr( admin_url( 'admin.php?page=p45-carti' ) );
				$caseta = <<<HTML
<div id="p45-demo" role="note" aria-label="Date de intrare pentru demo">
	<div class="d-cap"><span class="d-tag">Demo</span> Alege cu ce cont intri</div>
	<div class="d-rand">
		<div><b>Profesor</b><code>profesor@demo.ro</code> · <code>Demo1234</code></div>
		<button type="button" data-u="profesor@demo.ro" data-p="Demo1234" data-r="">Completează</button>
	</div>
	<div class="d-rand">
		<div><b>Editura</b> · panoul WordPress<br><code>admin</code> · <code>password</code></div>
		<button type="button" class="d-inchis" data-u="admin" data-p="password" data-r="{$panou}">Completează</button>
	</div>
	<p>Demo-ul rulează în browserul tău și se resetează când închizi pagina.</p>
</div>
<style>
#p45-demo{box-sizing:border-box;padding:14px 16px;border-radius:14px;background:#fff;color:#1F2A44;font:14px/1.45 Inter,system-ui,sans-serif;border:1px solid #F1C7C2;box-shadow:0 8px 24px rgba(213,43,30,.08)}
#p45-demo .d-cap{font-weight:700;font-size:14px;margin-bottom:6px;display:flex;align-items:center;gap:8px}
#p45-demo .d-tag{font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#fff;background:#D52B1E;border-radius:999px;padding:3px 8px}
#p45-demo .d-rand{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:8px 0;border-top:1px solid #ECEAE5}
#p45-demo .d-rand div{font-size:12px;color:#5B6478}
#p45-demo .d-rand b{color:#1F2A44;font-size:13px;margin-right:6px}
#p45-demo code{font:600 12px ui-monospace,SFMono-Regular,Menlo,monospace;background:#F4F2EE;border-radius:6px;padding:1px 6px}
#p45-demo button{flex:none;font:600 13px Inter,system-ui,sans-serif;color:#fff;background:#D52B1E;border:0;border-radius:10px;padding:8px 12px;cursor:pointer;white-space:nowrap}
#p45-demo button.d-inchis{background:#1F2A44}
#p45-demo p{margin:4px 0 0;font-size:12px;color:#5B6478}
</style>
<script>
document.querySelectorAll('#p45-demo button').forEach(function (b) {
	b.addEventListener('click', function () {
		var f = document.querySelector('form.form');
		if (!f) { return; }
		f.querySelector('[name=email]').value = b.getAttribute('data-u');
		f.querySelector('[name=parola]').value = b.getAttribute('data-p');
		var r = f.querySelector('[name=redirect_to]');
		if (r && b.getAttribute('data-r')) { r.value = b.getAttribute('data-r'); }
		var s = f.querySelector('[type=submit]');
		if (s) { s.focus(); }
	});
});
</script>
HTML;
				$poz = strpos( $html, '<form method="post"' );
				return substr_replace( $html, $caseta . "\n", $poz, 0 );

			}
		);
	},
	1
);

/** Datele de intrare și pe pagina de autentificare WordPress. */
add_filter(
	'login_message',
	function ( $m ) {
		return $m . '<p class="message"><strong>Demo:</strong> utilizator <code>admin</code>, parolă <code>password</code></p>';
	}
);

/** Un sfat în panoul editurii. */
add_action(
	'admin_notices',
	function () {
		$ecran = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $ecran || false === strpos( (string) $ecran->id, 'p45' ) ) {
			return;
		}
		echo '<div class="notice notice-info"><p><strong>Mod demo.</strong> Poți adăuga o carte din PDF: alege un PDF de câteva zeci de pagini, pentru că demo-ul ține totul în memoria browserului. Contul de profesor este <code>profesor@demo.ro</code> / <code>Demo1234</code>.</p></div>';
	}
);
