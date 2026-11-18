<?php
/**
 * Cititorul: carte care se răsfoiește, cu Mod Tablă.
 *
 * @var array $carte
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
require_once P45CD_DIR . 'templates/parts.php';

$user  = wp_get_current_user();
$ratio = ( $carte['w'] && $carte['h'] ) ? $carte['w'] / $carte['h'] : 0.75;
$date  = array(
	'id'         => $carte['id'],
	'titlu'      => $carte['titlu'],
	'autor'      => $carte['autor'],
	'pagini'     => $carte['pagini'],
	'ratio'      => round( $ratio, 5 ),
	'toc'        => $carte['toc'],
	/* URL-ul se termină în „p45_n=1”; cititorul pune la final numărul paginii. */
	'imgP'       => substr( p45cd_url_imagine( $carte['id'], 'p', 1 ), 0, -1 ),
	'imgM'       => substr( p45cd_url_imagine( $carte['id'], 'm', 1 ), 0, -1 ),
	'stare'      => p45cd_stare( $user->ID, $carte['id'] ),
	'rest'       => esc_url_raw( rest_url( 'p45/v1/stare/' . $carte['id'] ) ),
	'nonce'      => wp_create_nonce( 'wp_rest' ),
	'email'      => $user->user_email,
	'biblioteca' => p45cd_url( 'biblioteca' ),
	'tabla'      => ! empty( $_GET['tabla'] ),
	'user'       => $user->ID,
);

p45cd_inceput( $carte['titlu'], 'cititor', array( 'css/cititor.css' ) );
?>
<div class="app" id="app">

<header class="top">
	<a class="ib inapoi" href="<?php echo esc_url( p45cd_url( 'biblioteca' ) ); ?>" aria-label="Înapoi la bibliotecă" title="Biblioteca"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M12 19l-7-7 7-7"/></svg></a>
	<span class="titles"><b><?php echo esc_html( $carte['titlu'] ); ?></b><span id="chapterLbl"><?php echo esc_html( $carte['autor'] ); ?></span></span>
	<span class="grow"></span>
	<nav class="pager" aria-label="Navigare pagini">
		<button class="ib" id="prevTop" aria-label="Pagina anterioară"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg></button>
		<label><span class="lbl">Pagina</span> <input id="pageInput" type="text" inputmode="numeric" aria-label="Pagina curentă; scrie un număr și apasă Enter"></label>
		<span class="lbl">din <?php echo (int) $carte['pagini']; ?></span>
		<button class="ib" id="nextTop" aria-label="Pagina următoare"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg></button>
	</nav>
	<span class="grow"></span>
	<div class="tools" role="toolbar" aria-label="Instrumente">
		<button class="ib" id="btnPanel" aria-pressed="false" aria-label="Cuprins, semne de carte și note" title="Cuprins"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg></button>
		<button class="ib" id="btnMark" aria-pressed="false" aria-label="Pune semn de carte" title="Semn de carte"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg></button>
		<button class="ib" id="btnNote" aria-label="Adaugă o notă" title="Notă"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V9z"/><path d="M15 3v6h6M12 12v6M9 15h6"/></svg></button>
		<button class="ib" id="btnDraw" aria-pressed="false" aria-label="Desenează pe pagină" title="Desen"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 3a2.8 2.8 0 0 1 4 4L7.5 20.5 2 22l1.5-5.5z"/></svg></button>
		<span class="sep hideS"></span>
		<button class="ib hideS" id="zoomOut" aria-label="Micșorează" title="Micșorează"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3M8 11h6"/></svg></button>
		<span class="zoomlbl hideS" id="zoomLbl">100%</span>
		<button class="ib hideS" id="zoomIn" aria-label="Mărește" title="Mărește"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3M11 8v6M8 11h6"/></svg></button>
		<button class="ib hideXS" id="btnFull" aria-label="Ecran complet" title="Ecran complet"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 3H5a2 2 0 0 0-2 2v3M21 8V5a2 2 0 0 0-2-2h-3M3 16v3a2 2 0 0 0 2 2h3M16 21h3a2 2 0 0 0 2-2v-3"/></svg></button>
		<button class="btn primary" id="btnTabla" aria-label="Mod Tablă"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="3" width="20" height="13" rx="2"/><path d="M12 16v4M8 21h8"/></svg><span class="lbl">Mod Tablă</span></button>
	</div>
</header>

<div class="body">
	<aside class="panel" id="panel" hidden aria-label="Cuprins, semne de carte și note">
		<div class="tabs" role="tablist">
			<button role="tab" id="tab-toc" aria-selected="true" data-tab="toc">Cuprins</button>
			<button role="tab" id="tab-marks" aria-selected="false" data-tab="marks">Semne</button>
			<button role="tab" id="tab-notes" aria-selected="false" data-tab="notes">Note</button>
		</div>
		<div class="pbody" id="pbody"></div>
	</aside>

	<main class="stage" id="stage">
		<div class="tablaTop">
			<span class="chip"><b><?php echo esc_html( $carte['titlu'] ); ?></b><span id="tablaChapter"></span></span>
			<span class="grow"></span>
			<button class="chip" id="btnNotesTabla" aria-pressed="false">
				<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V9z"/><path d="M15 3v6h6"/></svg>
				<span id="notesTablaLbl">Notele mele: ascunse</span>
			</button>
			<button class="chip" id="btnFullTabla"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 3H5a2 2 0 0 0-2 2v3M21 8V5a2 2 0 0 0-2-2h-3M3 16v3a2 2 0 0 0 2 2h3M16 21h3a2 2 0 0 0 2-2v-3"/></svg><span>Ecran complet</span></button>
			<button class="btn light" id="btnExitTabla"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>Ieși din Tablă</button>
		</div>

		<button class="nav prev" id="navPrev" aria-label="Pagina anterioară"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg></button>
		<button class="nav next" id="navNext" aria-label="Pagina următoare"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg></button>

		<div class="bookWrap" id="bookWrap">
			<div id="book"></div>
			<div class="overlay" id="overlay">
				<canvas id="ink"></canvas>
				<div id="marks"></div>
			</div>
		</div>

		<div class="drawbar" id="drawbar" role="toolbar" aria-label="Instrumente de desen">
			<button class="tool" data-tool="hand" aria-pressed="false"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 11V6a2 2 0 0 0-4 0v5M14 10V4a2 2 0 0 0-4 0v6M10 10.5V6a2 2 0 0 0-4 0v8"/><path d="M18 8a2 2 0 1 1 4 0v6a8 8 0 0 1-8 8h-2c-2.8 0-4.5-.9-6-2.4l-3.6-3.6a2 2 0 0 1 2.8-2.8L7 15"/></svg>Răsfoire</button>
			<button class="tool" data-tool="pen" aria-pressed="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 3a2.8 2.8 0 0 1 4 4L7.5 20.5 2 22l1.5-5.5z"/></svg>Creion</button>
			<button class="tool" data-tool="marker" aria-pressed="false"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 11-6 6v3h9l3-3"/><path d="m22 12-4.6 4.6a2 2 0 0 1-2.8 0l-5.2-5.2a2 2 0 0 1 0-2.8L14 4"/></svg>Marker</button>
			<button class="tool" data-tool="eraser" aria-pressed="false"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m7 21-4.3-4.3a1 1 0 0 1 0-1.4l10-10a1 1 0 0 1 1.4 0l5.6 5.6a1 1 0 0 1 0 1.4L11 21z"/><path d="M22 21H7M5 11l9 9"/></svg>Radieră</button>
			<span class="dsep"></span>
			<div role="group" aria-label="Culoare" class="swatches" id="swatches"></div>
			<span class="dsep"></span>
			<button class="tool" id="undo"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 14 4 9l5-5"/><path d="M4 9h10.5a5.5 5.5 0 0 1 0 11H11"/></svg>Anulează</button>
			<button class="tool" id="clearInk"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/></svg>Șterge tot</button>
			<span class="dsep tablaOnly"></span>
			<button class="tool tablaOnly" id="zoomTabla"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3M11 8v6M8 11h6"/></svg>Mărește</button>
			<button class="tool tablaOnly" id="tocTabla"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>Cuprins</button>
			<button class="tool pagebtn tablaOnly" id="pageTabla" aria-label="Alege pagina"><b id="pageTablaLbl">1</b><span>din <?php echo (int) $carte['pagini']; ?></span></button>
			<button class="tool" id="closeDraw" aria-label="Închide instrumentele de desen"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>Gata</button>
		</div>

		<div class="zoomView" id="zoomView" aria-label="Pagini mărite; trage ca să te miști"><div class="zoomInner" id="zoomInner"></div></div>
		<button class="btn light zoomClose" id="zoomClose">Înapoi la 100%</button>
		<div class="toast" id="toast" role="status" aria-live="polite"></div>
	</main>
</div>

<nav class="thumbs" id="thumbs" aria-label="Miniaturi pagini"></nav>
</div>

<div class="modalBack" id="modalBack">
	<div class="modal" role="dialog" aria-modal="true" aria-labelledby="noteTitle">
		<h2 id="noteTitle">Notă nouă</h2>
		<div class="row pg" id="notePages"></div>
		<label class="sr" for="noteText">Textul notei</label>
		<textarea id="noteText" placeholder="De exemplu: întrebare pentru clasă, idee de activitate…"></textarea>
		<span class="muted">Notele sunt private. Pe tablă rămân ascunse până le afișezi tu.</span>
		<div class="row">
			<button class="btn danger" id="noteDelete">Șterge nota</button>
			<span class="grow"></span>
			<button class="btn secondary" id="noteCancel">Anulează</button>
			<button class="btn primary" id="noteSave">Salvează</button>
		</div>
	</div>
</div>

<script>window.P45 = <?php echo wp_json_encode( $date ); ?>;</script>
<?php
p45cd_sfarsit( array( 'vendor/page-flip/page-flip.browser.js', 'js/cititor.js' ) );
