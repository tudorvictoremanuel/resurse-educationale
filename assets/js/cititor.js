/* Paralela 45 – Cărți digitale: cititorul (răsfoire, cuprins, semne, note, desen, Mod Tablă). */
(function () {
'use strict';
var D = window.P45;
if (!D || !window.St) { document.body.insertAdjacentHTML('afterbegin', '<p style="padding:16px">Cartea nu s-a putut încărca. Reîncarcă pagina.</p>'); return; }

var TOTAL = D.pagini, RATIO = D.ratio || 0.75;
var TOC = (D.toc || []).map(function (r) { return [r.t, r.s || '', r.p]; });
var COLORS = [['rosu', 'Roșu', '#E5341A'], ['albastru', 'Albastru', '#2563EB'], ['verde', 'Verde', '#16A34A'], ['galben', 'Galben', '#FACC15'], ['negru', 'Negru', '#111111']];

function $(id) { return document.getElementById(id); }
function urlP(n) { return D.imgP + n; }
function urlM(n) { return D.imgM + n; }
function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }

/* ---------- datele profesorului: salvate în cont, cu copie locală ---------- */
var stare = D.stare || {};
var marks = Array.isArray(stare.marks) ? stare.marks.slice() : [];
var notes = Array.isArray(stare.notes) ? stare.notes.slice() : [];
var ink = (stare.ink && typeof stare.ink === 'object' && !Array.isArray(stare.ink)) ? stare.ink : {};
var lastIdx = parseInt(stare.last, 10) || 0;
var LKEY = 'p45cd:' + D.user + ':' + D.id;

var saveTimer = null, saving = false, dirty = false, failCount = 0;
function payload() { return JSON.stringify({ marks: marks, notes: notes, ink: ink, last: lastIdx }); }
function backupLocal() { try { localStorage.setItem(LKEY, payload()); } catch (e) {} }
function scheduleSave(delay) {
  dirty = true; backupLocal();
  clearTimeout(saveTimer);
  saveTimer = setTimeout(saveNow, delay == null ? 1200 : delay);
}
function saveNow(keepalive) {
  if (!dirty) return;
  if (saving && !keepalive) { scheduleSave(800); return; }
  saving = true; dirty = false;
  fetch(D.rest, {
    method: 'POST', credentials: 'same-origin', keepalive: !!keepalive,
    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': D.nonce },
    body: payload()
  }).then(function (r) {
    saving = false;
    if (!r.ok) throw r;
    failCount = 0;
  }).catch(function (r) {
    saving = false; dirty = true; failCount++;
    if (r && r.status === 413) { toast('Desenele de pe această carte sunt prea multe. Șterge câteva.', true); return; }
    if (r && (r.status === 401 || r.status === 403)) { toast('Sesiunea a expirat. Reîncarcă pagina ca să salvezi.', true); return; }
    if (failCount === 1 || failCount % 5 === 0) toast('Nu am putut salva. Verifică internetul; încerc din nou.', true);
    scheduleSave(Math.min(30000, 2000 * failCount));
  });
}
document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'hidden') saveNow(true); });
window.addEventListener('pagehide', function () { saveNow(true); });

/* ---------- pagini ---------- */
var bookEl = $('book');
for (var i = 1; i <= TOTAL; i++) {
  var d = document.createElement('div');
  d.className = 'page';
  d.setAttribute('data-density', 'soft');
  d.innerHTML = '<img data-p="' + i + '" alt="Pagina ' + i + '" draggable="false"><span class="wm">' + esc(D.email) + ' · Editura Paralela 45</span>';
  bookEl.appendChild(d);
}
function ensureLoaded(idx) {
  for (var j = idx - 3; j <= idx + 6; j++) {
    if (j < 0 || j >= TOTAL) continue;
    var imgs = document.querySelectorAll('img[data-p="' + (j + 1) + '"]');
    for (var k = 0; k < imgs.length; k++) if (!imgs[k].getAttribute('src')) imgs[k].setAttribute('src', urlP(j + 1));
  }
}

/* ---------- dimensionare ---------- */
var stage = $('stage'), wrap = $('bookWrap');
var MINW = 330;
function fit() {
  var r = stage.getBoundingClientRect();
  var tabla = document.body.classList.contains('tabla');
  var narrow = r.width < 860;
  var padX = tabla ? (narrow ? 24 : 340) : (narrow ? 16 : 160);
  var padTop = tabla ? 100 : (narrow ? 12 : 24);
  var padBot = tabla ? 124 : (document.body.classList.contains('drawing') ? 96 : (narrow ? 12 : 24));
  var aw = Math.max(200, r.width - padX), ah = Math.max(200, r.height - padTop - padBot);
  var land = Math.min(aw, ah * 2 * RATIO);
  var portrait = narrow || land < MINW * 2 || TOTAL < 2;
  var w = portrait ? Math.min(aw, ah * RATIO, MINW * 2 - 2) : land;
  wrap.style.width = Math.floor(w) + 'px';
  wrap.style.marginTop = (padTop - padBot) + 'px';
}
window.addEventListener('resize', fit);
if (D.tabla) document.body.classList.add('tabla');
fit();

/* ---------- carte ---------- */
var startIdx = Math.min(TOTAL - 1, Math.max(0, lastIdx));
var pf = new St.PageFlip(bookEl, {
  width: 700, height: Math.round(700 / RATIO), size: 'stretch',
  minWidth: MINW, maxWidth: 2000, minHeight: 300, maxHeight: Math.round(2000 / RATIO),
  showCover: true, usePortrait: true, autoSize: true,
  mobileScrollSupport: false, maxShadowOpacity: 0.45, flippingTime: 650,
  startPage: startIdx, disableFlipByClick: false, swipeDistance: 30
});
ensureLoaded(startIdx);
pf.loadFromHTML(document.querySelectorAll('#book .page'));

function cur() { return pf.getCurrentPageIndex(); }
function visible() {
  var c = cur(), o = pf.getOrientation();
  if (o === 'portrait') return [{ i: c, side: 's' }];
  if (c === 0) return [{ i: 0, side: 'r' }];
  var l = (c % 2 === 1) ? c : c - 1, out = [{ i: l, side: 'l' }];
  if (l + 1 < TOTAL) out.push({ i: l + 1, side: 'r' });
  return out;
}
function rects() {
  var b = pf.getBoundsRect(), o = pf.getOrientation(), pw = b.pageWidth;
  return visible().map(function (v) {
    var x = (o === 'portrait') ? b.left + pw : (v.side === 'l' ? b.left : b.left + pw);
    return { i: v.i, x: x, y: b.top, w: pw, h: b.height };
  });
}
function mainPage() { var v = visible(); return v[v.length - 1].i + 1; }
function label() { var v = visible(); return v.length > 1 ? (v[0].i + 1) + '–' + (v[1].i + 1) : String(v[0].i + 1); }
function chapterOf(p) { var c = null; for (var k = 0; k < TOC.length; k++) if (TOC[k][2] <= p) c = TOC[k]; return c; }

/* ---------- miniaturi ---------- */
var thumbsEl = $('thumbs');
(function () {
  var h = '';
  for (var p = 1; p <= TOTAL; p++) {
    h += '<button class="th' + ((p % 2 === 1) ? ' gap' : '') + '" data-p="' + p + '" aria-label="Pagina ' + p + '"><img loading="lazy" src="' + urlM(p) + '" alt=""><span>' + p + '</span></button>';
  }
  thumbsEl.innerHTML = h;
})();
thumbsEl.addEventListener('click', function (e) { var b = e.target.closest('.th'); if (b) goTo(+b.getAttribute('data-p')); });

var firstRefresh = true;
function refresh() {
  var v = visible(), first = v[0].i + 1, last = v[v.length - 1].i + 1;
  ensureLoaded(v[0].i);
  $('pageInput').value = label();
  $('pageTablaLbl').textContent = label();
  var ch = chapterOf(first);
  $('chapterLbl').textContent = ch ? (ch[0] + (ch[1] ? ' · ' + ch[1] : '')) : (D.autor || '');
  $('tablaChapter').textContent = ch ? ch[0] : '';
  var olds = thumbsEl.querySelectorAll('.th.cur');
  for (var k = 0; k < olds.length; k++) olds[k].classList.remove('cur');
  var target = null;
  for (var p = first; p <= last; p++) { var t = thumbsEl.querySelector('.th[data-p="' + p + '"]'); if (t) { t.classList.add('cur'); target = target || t; } }
  if (target) thumbsEl.scrollTo({ left: target.offsetLeft - thumbsEl.clientWidth / 2 + 60, behavior: firstRefresh ? 'auto' : 'smooth' });
  var mp = mainPage(), on = marks.indexOf(mp) !== -1;
  $('btnMark').setAttribute('aria-pressed', on ? 'true' : 'false');
  $('btnMark').setAttribute('aria-label', on ? 'Scoate semnul de carte de pe pagina ' + mp : 'Pune semn de carte pe pagina ' + mp);
  $('navPrev').disabled = v[0].i === 0;
  $('navNext').disabled = v[v.length - 1].i >= TOTAL - 1;
  if (v[0].i !== lastIdx) { lastIdx = v[0].i; if (!firstRefresh) scheduleSave(4000); }
  firstRefresh = false;
  renderOverlay();
  if (panelTab === 'toc' && !$('panel').hidden) markTocCurrent();
}

/* ---------- navigare ---------- */
function busy() { return pf.getState() === 'flipping'; }
function next() { if (!busy()) pf.flipNext('bottom'); }
function prev() { if (!busy()) pf.flipPrev('bottom'); }
function goTo(p) {
  p = Math.max(1, Math.min(TOTAL, p | 0));
  var idx = p - 1, c = cur();
  if (Math.abs(idx - c) <= 4) pf.flip(idx, 'bottom'); else { ensureLoaded(idx); pf.turnToPage(idx); refresh(); }
}
$('prevTop').onclick = prev; $('nextTop').onclick = next;
$('navPrev').onclick = prev; $('navNext').onclick = next;
$('pageInput').addEventListener('keydown', function (e) {
  if (e.key === 'Enter') { var n = parseInt(this.value, 10); if (!isNaN(n)) goTo(n); this.blur(); }
});
$('pageInput').addEventListener('focus', function () { this.select(); });
$('pageTabla').onclick = function () { openPanel('toc'); };

document.addEventListener('keydown', function (e) {
  var tag = (e.target && e.target.tagName) || '';
  if (tag === 'INPUT' || tag === 'TEXTAREA') return;
  if ($('modalBack').classList.contains('on')) { if (e.key === 'Escape') closeNote(); return; }
  if (e.key === 'ArrowRight' || e.key === 'PageDown') { e.preventDefault(); next(); }
  else if (e.key === 'ArrowLeft' || e.key === 'PageUp') { e.preventDefault(); prev(); }
  else if (e.key === 'Home') goTo(1);
  else if (e.key === 'End') goTo(TOTAL);
  else if (e.key === 'Escape') {
    if (zoom > 1) setZoom(1);
    else if (!$('panel').hidden && (document.body.classList.contains('tabla') || window.innerWidth <= 1180)) closePanel();
    else if (document.body.classList.contains('tabla') && !document.fullscreenElement) exitTabla();
  }
});

pf.on('flip', function () { refresh(); });
pf.on('changeOrientation', function () { setTimeout(refresh, 0); });
pf.on('changeState', function (e) {
  var hide = e.data === 'flipping' || e.data === 'user_fold' || e.data === 'fold_corner';
  $('overlay').classList.toggle('hide', hide);
  if (e.data === 'read') renderOverlay();
});
window.addEventListener('resize', function () { setTimeout(renderOverlay, 30); });

/* ---------- panou: cuprins / semne / note ---------- */
var panelTab = TOC.length ? 'toc' : 'marks';
function openPanel(tab) {
  if (tab) panelTab = tab;
  $('panel').hidden = false;
  $('btnPanel').setAttribute('aria-pressed', 'true');
  renderPanel(); relayout();
}
function closePanel() { $('panel').hidden = true; $('btnPanel').setAttribute('aria-pressed', 'false'); relayout(); }
$('btnPanel').onclick = function () { if ($('panel').hidden) openPanel(); else closePanel(); };
$('tocTabla').onclick = function () { if ($('panel').hidden) openPanel('toc'); else closePanel(); };
var tabBtns = document.querySelectorAll('.tabs button');
for (var tb = 0; tb < tabBtns.length; tb++) tabBtns[tb].onclick = function () { panelTab = this.getAttribute('data-tab'); renderPanel(); };
function renderPanel() {
  for (var k = 0; k < tabBtns.length; k++) tabBtns[k].setAttribute('aria-selected', tabBtns[k].getAttribute('data-tab') === panelTab ? 'true' : 'false');
  $('tab-marks').textContent = 'Semne' + (marks.length ? ' · ' + marks.length : '');
  $('tab-notes').textContent = 'Note' + (notes.length ? ' · ' + notes.length : '');
  var h = '';
  if (panelTab === 'toc') {
    if (!TOC.length) h = '<p class="empty">Cartea nu are cuprins. Folosește miniaturile de jos sau scrie numărul paginii sus.</p>';
    else {
      h += '<ol class="plist toc">';
      TOC.forEach(function (c) { h += '<li><a href="#" data-p="' + c[2] + '"><span class="tx"><span class="t">' + esc(c[0]) + '</span>' + (c[1] ? '<span class="s">' + esc(c[1]) + '</span>' : '') + '</span><span class="n">' + c[2] + '</span></a></li>'; });
      h += '</ol>';
    }
  } else if (panelTab === 'marks') {
    if (!marks.length) h = '<p class="empty">Nu ai semne de carte. Apasă butonul cu semn de carte de sus ca să marchezi pagina.</p>';
    else {
      h = '<ul class="plist" style="gap:8px">';
      marks.slice().sort(function (a, b) { return a - b; }).forEach(function (p) {
        var ch = chapterOf(p);
        h += '<li><button class="card" data-p="' + p + '"><img src="' + urlM(p) + '" alt=""><span class="tx"><b>Pagina ' + p + '</b><span class="m">' + esc(ch ? ch[0] : '') + '</span></span><svg width="18" height="18" viewBox="0 0 24 24" fill="#E5341A" stroke="#E5341A" stroke-width="2" aria-hidden="true"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg></button></li>';
      });
      h += '</ul>';
    }
  } else {
    if (!notes.length) h = '<p class="empty">Nu ai note. Apasă butonul de notă de sus ca să scrii una pe pagina deschisă.</p>';
    else {
      h = '<ul class="plist" style="gap:8px">';
      notes.slice().sort(function (a, b) { return a.p - b.p; }).forEach(function (n) {
        h += '<li><button class="card" data-p="' + n.p + '" style="flex-direction:column;align-items:flex-start;gap:4px"><span class="m">Pagina ' + n.p + '</span><span>' + esc(n.text) + '</span></button></li>';
      });
      h += '</ul><p class="empty" style="padding-top:0">Notele sunt private. Le vezi doar tu.</p>';
    }
  }
  $('pbody').innerHTML = h;
  if (panelTab === 'toc') markTocCurrent(true);
}
function markTocCurrent(scroll) {
  var ch = chapterOf(visible()[0].i + 1);
  var links = $('pbody').querySelectorAll('.toc a');
  for (var k = 0; k < links.length; k++) {
    var is = ch && +links[k].getAttribute('data-p') === ch[2];
    links[k].classList.toggle('cur', !!is);
    if (is && scroll) links[k].scrollIntoView({ block: 'center' });
  }
}
$('pbody').addEventListener('click', function (e) {
  var a = e.target.closest('[data-p]');
  if (!a) return;
  e.preventDefault();
  goTo(+a.getAttribute('data-p'));
  if (document.body.classList.contains('tabla') || window.innerWidth <= 1180) closePanel();
});

/* ---------- semne de carte ---------- */
$('btnMark').onclick = function () {
  var p = mainPage(), k = marks.indexOf(p);
  if (k === -1) { marks.push(p); toast('Semn de carte pus pe pagina ' + p); } else { marks.splice(k, 1); toast('Semn de carte scos de pe pagina ' + p); }
  scheduleSave(); refresh(); if (!$('panel').hidden) renderPanel();
};

/* ---------- note ---------- */
var editing = null, notePage = null;
function openNote(note) {
  editing = note || null;
  var v = visible();
  notePage = note ? note.p : mainPage();
  $('noteTitle').textContent = note ? 'Notă · pagina ' + note.p : 'Notă nouă';
  var h = '';
  if (!note && v.length > 1) v.forEach(function (x) { var p = x.i + 1; h += '<label><input type="radio" name="np" value="' + p + '"' + (p === notePage ? ' checked' : '') + '> Pagina ' + p + '</label>'; });
  $('notePages').innerHTML = h;
  $('notePages').style.display = h ? 'flex' : 'none';
  $('noteText').value = note ? note.text : '';
  $('noteDelete').style.visibility = note ? 'visible' : 'hidden';
  $('modalBack').classList.add('on');
  setTimeout(function () { $('noteText').focus(); }, 30);
}
function closeNote() { $('modalBack').classList.remove('on'); editing = null; }
$('btnNote').onclick = function () { openNote(null); };
$('noteCancel').onclick = closeNote;
$('modalBack').addEventListener('click', function (e) { if (e.target === this) closeNote(); });
$('noteSave').onclick = function () {
  var t = $('noteText').value.trim();
  if (!t) { $('noteText').focus(); return; }
  var r = document.querySelector('input[name="np"]:checked');
  if (editing) editing.text = t;
  else notes.push({ id: Date.now(), p: r ? +r.value : notePage, text: t, d: Date.now() });
  scheduleSave(300); closeNote(); renderOverlay(); if (!$('panel').hidden) renderPanel(); toast('Nota a fost salvată');
};
$('noteDelete').onclick = function () {
  if (!editing) return;
  notes = notes.filter(function (n) { return n !== editing; });
  scheduleSave(300); closeNote(); renderOverlay(); if (!$('panel').hidden) renderPanel(); toast('Nota a fost ștearsă');
};

/* ---------- strat peste pagini: semne, note, desen ---------- */
var canvas = $('ink'), ctx = canvas.getContext('2d'), dpr = 1;
function renderOverlay() {
  var W = wrap.clientWidth, H = wrap.clientHeight;
  dpr = Math.min(2, window.devicePixelRatio || 1);
  if (canvas.width !== Math.round(W * dpr) || canvas.height !== Math.round(H * dpr)) {
    canvas.width = Math.round(W * dpr); canvas.height = Math.round(H * dpr);
    canvas.style.width = W + 'px'; canvas.style.height = H + 'px';
  }
  ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
  ctx.clearRect(0, 0, W, H);
  var h = '';
  rects().forEach(function (r) {
    (ink[r.i + 1] || []).forEach(function (s) { drawStroke(s, r); });
    if (marks.indexOf(r.i + 1) !== -1) {
      h += '<svg class="ribbon" viewBox="0 0 30 52" aria-hidden="true" style="left:' + (r.x + r.w - r.w * 0.09 - 26) + 'px;top:' + (r.y - 4) + 'px"><path d="M0 0h30v52l-15-11L0 52z" fill="#E5341A"/></svg>';
    }
    notes.filter(function (n) { return n.p === r.i + 1; }).forEach(function (n, k) {
      h += '<button class="pin private" data-note="' + n.id + '" aria-label="Notă pe pagina ' + n.p + '" style="left:' + (r.x + r.w * 0.62 - k * 44) + 'px;top:' + (r.y + r.h * 0.026) + 'px"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V9z"/><path d="M15 3v6h6"/></svg></button>';
    });
  });
  $('marks').innerHTML = h;
}
$('marks').addEventListener('click', function (e) {
  var b = e.target.closest('.pin'); if (!b) return;
  var id = +b.getAttribute('data-note');
  var n = notes.filter(function (x) { return x.id === id; })[0];
  if (n) openNote(n);
});
function drawStroke(s, r) {
  if (!s.pts || !s.pts.length) return;
  ctx.save();
  ctx.lineCap = 'round'; ctx.lineJoin = 'round';
  ctx.strokeStyle = s.c;
  ctx.globalAlpha = s.t === 'marker' ? 0.42 : 1;
  ctx.lineWidth = (s.t === 'marker' ? 0.034 : 0.0065) * r.w;
  ctx.beginPath();
  ctx.moveTo(r.x + s.pts[0][0] * r.w, r.y + s.pts[0][1] * r.h);
  if (s.pts.length === 1) ctx.lineTo(r.x + s.pts[0][0] * r.w + 0.1, r.y + s.pts[0][1] * r.h);
  for (var k = 1; k < s.pts.length; k++) ctx.lineTo(r.x + s.pts[k][0] * r.w, r.y + s.pts[k][1] * r.h);
  ctx.stroke();
  ctx.restore();
}

var tool = 'pen', color = '#E5341A', live = null, liveRect = null, undoStack = [];
var sw = $('swatches');
COLORS.forEach(function (c) {
  var b = document.createElement('button');
  b.className = 'sw'; b.setAttribute('aria-label', c[1]); b.setAttribute('data-c', c[2]);
  b.setAttribute('aria-pressed', c[2] === color ? 'true' : 'false');
  b.innerHTML = '<span style="background:' + c[2] + '"></span>';
  b.onclick = function () { color = c[2]; if (tool === 'hand' || tool === 'eraser') tool = 'pen'; updateTools(); };
  sw.appendChild(b);
});
var toolBtns = document.querySelectorAll('.tool[data-tool]');
for (var q = 0; q < toolBtns.length; q++) toolBtns[q].onclick = function () { setTool(this.getAttribute('data-tool')); };
function setTool(t) { tool = t; if (t === 'marker' && color === '#E5341A') color = '#FACC15'; updateTools(); }
function updateTools() {
  for (var k = 0; k < toolBtns.length; k++) toolBtns[k].setAttribute('aria-pressed', toolBtns[k].getAttribute('data-tool') === tool ? 'true' : 'false');
  var s = sw.querySelectorAll('.sw');
  for (var m = 0; m < s.length; m++) s[m].setAttribute('aria-pressed', s[m].getAttribute('data-c') === color ? 'true' : 'false');
  var drawingOn = document.body.classList.contains('drawing') || document.body.classList.contains('tabla');
  canvas.classList.toggle('active', drawingOn && tool !== 'hand');
  canvas.classList.toggle('eraser', tool === 'eraser');
}
function pageAt(x, y) {
  var rs = rects();
  for (var k = 0; k < rs.length; k++) { var r = rs[k]; if (x >= r.x && x <= r.x + r.w && y >= r.y && y <= r.y + r.h) return r; }
  return null;
}
function local(e) { var b = canvas.getBoundingClientRect(); return [e.clientX - b.left, e.clientY - b.top]; }
function eraseAt(x, y) {
  var R = 14, changed = false;
  rects().forEach(function (r) {
    var list = ink[r.i + 1]; if (!list) return;
    for (var k = list.length - 1; k >= 0; k--) {
      var s = list[k], hit = false;
      for (var m = 0; m < s.pts.length && !hit; m++) {
        var px = r.x + s.pts[m][0] * r.w, py = r.y + s.pts[m][1] * r.h;
        if ((px - x) * (px - x) + (py - y) * (py - y) < R * R) hit = true;
      }
      if (hit) { undoStack.push({ type: 'erase', p: r.i + 1, s: s, at: k }); list.splice(k, 1); changed = true; }
    }
  });
  if (changed) { scheduleSave(); renderOverlay(); }
}
canvas.addEventListener('pointerdown', function (e) {
  if (!canvas.classList.contains('active')) return;
  e.preventDefault();
  try { canvas.setPointerCapture(e.pointerId); } catch (err) {}
  var pt = local(e);
  if (tool === 'eraser') { live = { erasing: true }; eraseAt(pt[0], pt[1]); return; }
  var r = pageAt(pt[0], pt[1]); if (!r) return;
  liveRect = r;
  live = { t: tool, c: color, pts: [[(pt[0] - r.x) / r.w, (pt[1] - r.y) / r.h]] };
});
canvas.addEventListener('pointermove', function (e) {
  if (!live) return;
  var pt = local(e);
  if (live.erasing) { eraseAt(pt[0], pt[1]); return; }
  var r = liveRect, last = live.pts[live.pts.length - 1];
  var nx = (pt[0] - r.x) / r.w, ny = (pt[1] - r.y) / r.h;
  if (Math.abs(nx - last[0]) + Math.abs(ny - last[1]) < 0.002) return;
  live.pts.push([Math.round(nx * 10000) / 10000, Math.round(ny * 10000) / 10000]);
  renderOverlay(); drawStroke(live, r);
});
function endStroke() {
  if (!live) return;
  if (!live.erasing && liveRect) {
    var p = liveRect.i + 1;
    (ink[p] = ink[p] || []).push(live);
    undoStack.push({ type: 'add', p: p });
    scheduleSave();
  }
  live = null; liveRect = null; renderOverlay();
}
canvas.addEventListener('pointerup', endStroke);
canvas.addEventListener('pointercancel', endStroke);
$('undo').onclick = function () {
  var a = undoStack.pop(); if (!a) { toast('Nu e nimic de anulat'); return; }
  if (a.type === 'add') { var l = ink[a.p]; if (l) l.pop(); }
  else if (a.type === 'erase') { (ink[a.p] = ink[a.p] || []).splice(a.at, 0, a.s); }
  else if (a.type === 'clear') { a.items.forEach(function (it) { ink[it.p] = it.list; }); }
  scheduleSave(); renderOverlay();
};
$('clearInk').onclick = function () {
  var items = [];
  visible().forEach(function (v) { var p = v.i + 1; if (ink[p] && ink[p].length) { items.push({ p: p, list: ink[p] }); ink[p] = []; } });
  if (!items.length) { toast('Nu e niciun desen pe aceste pagini'); return; }
  undoStack.push({ type: 'clear', items: items }); scheduleSave(); renderOverlay(); toast('Desenele de pe aceste pagini au fost șterse');
};

/* ---------- mod desen / Tablă ---------- */
function relayout() { fit(); pf.update(); setTimeout(renderOverlay, 20); }
$('btnDraw').onclick = function () {
  var on = !document.body.classList.contains('drawing');
  document.body.classList.toggle('drawing', on);
  this.setAttribute('aria-pressed', on ? 'true' : 'false');
  if (on && tool === 'hand') tool = 'pen';
  updateTools(); relayout();
};
$('closeDraw').onclick = function () {
  if (document.body.classList.contains('tabla')) { setTool('hand'); return; }
  document.body.classList.remove('drawing'); $('btnDraw').setAttribute('aria-pressed', 'false'); updateTools(); relayout();
};
function goFull() {
  try {
    if (!document.fullscreenElement && document.documentElement.requestFullscreen) {
      document.documentElement.requestFullscreen().catch(function () { toast('Browserul nu a permis ecranul complet'); });
    }
  } catch (e) {}
}
function enterTabla(fromClick) {
  document.body.classList.add('tabla');
  closePanel();
  tool = 'hand';
  if (fromClick) goFull();
  updateTools(); relayout();
  toast(fromClick ? 'Mod Tablă: atinge marginile paginii sau săgețile mari ca să răsfoiești' : 'Mod Tablă: apasă „Ecran complet” ca pagina să ocupe toată tabla');
}
function exitTabla() {
  document.body.classList.remove('tabla', 'shownotes');
  $('btnNotesTabla').setAttribute('aria-pressed', 'false'); $('notesTablaLbl').textContent = 'Notele mele: ascunse';
  try { if (document.fullscreenElement && document.exitFullscreen) document.exitFullscreen().catch(function () {}); } catch (e) {}
  updateTools(); relayout();
}
$('btnTabla').onclick = function () { enterTabla(true); };
$('btnExitTabla').onclick = exitTabla;
$('btnFullTabla').onclick = function () {
  if (document.fullscreenElement) { try { document.exitFullscreen(); } catch (e) {} } else goFull();
};
$('btnNotesTabla').onclick = function () {
  var on = !document.body.classList.contains('shownotes');
  document.body.classList.toggle('shownotes', on);
  this.setAttribute('aria-pressed', on ? 'true' : 'false');
  $('notesTablaLbl').textContent = on ? 'Notele mele: vizibile' : 'Notele mele: ascunse';
};
$('btnFull').onclick = function () {
  if (document.fullscreenElement) { try { document.exitFullscreen(); } catch (e) {} } else goFull();
};
document.addEventListener('fullscreenchange', function () {
  var l = document.querySelector('#btnFullTabla span');
  if (l) l.textContent = document.fullscreenElement ? 'Ieși din ecran complet' : 'Ecran complet';
  setTimeout(relayout, 60);
});

/* ---------- zoom ---------- */
var ZL = [1, 1.5, 2, 3], zoom = 1;
function setZoom(z) {
  zoom = z;
  $('zoomLbl').textContent = Math.round(z * 100) + '%';
  var zv = $('zoomView');
  if (z <= 1) { zv.classList.remove('on'); return; }
  var r = stage.getBoundingClientRect();
  var h = Math.round((r.height - 48) * z), hh = '';
  visible().forEach(function (v) { hh += '<img src="' + urlP(v.i + 1) + '" alt="Pagina ' + (v.i + 1) + '" style="height:' + h + 'px;width:' + Math.round(h * RATIO) + 'px">'; });
  $('zoomInner').innerHTML = hh;
  var wasOn = zv.classList.contains('on');
  zv.classList.add('on');
  if (!wasOn) { zv.scrollLeft = (zv.scrollWidth - zv.clientWidth) / 2; zv.scrollTop = 0; }
}
$('zoomIn').onclick = function () { var k = ZL.indexOf(zoom); setZoom(ZL[Math.min(ZL.length - 1, k + 1)]); };
$('zoomOut').onclick = function () { var k = ZL.indexOf(zoom); setZoom(ZL[Math.max(0, k - 1)]); };
$('zoomTabla').onclick = function () { var k = ZL.indexOf(zoom); setZoom(k >= 2 ? 1 : ZL[k + 1]); };
$('zoomClose').onclick = function () { setZoom(1); };
(function () {
  var zv = $('zoomView'), drag = null;
  zv.addEventListener('pointerdown', function (e) { drag = { x: e.clientX, y: e.clientY, l: zv.scrollLeft, t: zv.scrollTop }; try { zv.setPointerCapture(e.pointerId); } catch (err) {} zv.classList.add('dragging'); });
  zv.addEventListener('pointermove', function (e) { if (!drag) return; zv.scrollLeft = drag.l - (e.clientX - drag.x); zv.scrollTop = drag.t - (e.clientY - drag.y); });
  function end() { drag = null; zv.classList.remove('dragging'); }
  zv.addEventListener('pointerup', end); zv.addEventListener('pointercancel', end);
})();

/* ---------- mesaje scurte ---------- */
var tt = null;
function toast(msg, err) {
  var t = $('toast'); t.textContent = msg; t.classList.toggle('err', !!err); t.classList.add('on');
  clearTimeout(tt); tt = setTimeout(function () { t.classList.remove('on'); }, err ? 4500 : 2600);
}

/* ---------- pornire ---------- */
if (!D.tabla && window.innerWidth > 1180 && TOC.length) openPanel('toc');
if (D.tabla) tool = 'hand';
updateTools();
setTimeout(function () { relayout(); refresh(); }, 50);
if (D.tabla) setTimeout(function () { toast('Mod Tablă: apasă „Ecran complet” ca pagina să ocupe toată tabla'); }, 700);
else setTimeout(function () { toast(window.innerWidth < 860 ? 'Glisează ca să dai pagina' : 'Trage de colțul paginii, apasă săgețile sau tastele ← →'); }, 700);
})();
