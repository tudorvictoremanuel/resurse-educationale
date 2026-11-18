/* Paralela 45 – Cărți digitale: convertorul PDF → carte digitală (rulează în browserul editurii). */
const C = window.P45A || {};
const $ = (id) => document.getElementById(id);
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

let pdfjs = null;
let pdfDoc = null;
let pdfFile = null;
let id = C.id || 0;
let coverFile = null;
let busy = false;
let stopRequested = false;
let tocRows = (window.P45A_TOC || []).map((r) => ({ t: r.t || '', s: r.s || '', p: r.p || 1 }));
let wakeLock = null;

/* ------------------------------------------------------------------ */
/* Ajutoare                                                            */
/* ------------------------------------------------------------------ */

function esc(s) {
  return String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
}

function mb(bytes) {
  return (bytes / 1048576).toLocaleString('ro-RO', { maximumFractionDigits: 1 }) + ' MB';
}

function showMsg(html, error) {
  const box = $('p45-rezultat');
  box.className = 'p45-mesaj' + (error ? ' p45-mesaj-eroare' : '');
  box.innerHTML = html;
  box.hidden = false;
  box.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

async function api(path, opts = {}) {
  const o = { method: opts.method || 'GET', credentials: 'same-origin', headers: { 'X-WP-Nonce': C.nonce } };
  if (opts.json) {
    o.headers['Content-Type'] = 'application/json';
    o.body = JSON.stringify(opts.json);
  }
  if (opts.form) o.body = opts.form;
  const r = await fetch(C.rest + path, o);
  let data = null;
  try { data = await r.json(); } catch (e) { data = null; }
  if (!r.ok) {
    let msg = (data && data.message) || ('Serverul a răspuns cu eroarea ' + r.status + '.');
    if (r.status === 413) msg = 'Serverul refuză imaginile prea mari. În Setări alege o lățime mai mică a paginilor sau cere firmei de hosting să mărească limita de încărcare.';
    if (r.status === 401 || r.status === 403) msg = (data && data.message) || 'Sesiunea a expirat sau nu ai permisiunea. Reîncarcă pagina și încearcă din nou.';
    const err = new Error(msg);
    err.status = r.status;
    throw err;
  }
  return data;
}

async function loadPdfjs() {
  if (!pdfjs) {
    pdfjs = await import(C.pdfjs);
    pdfjs.GlobalWorkerOptions.workerSrc = C.worker;
  }
  return pdfjs;
}

function webpOk() {
  try {
    const c = document.createElement('canvas');
    c.width = c.height = 2;
    return c.toDataURL('image/webp').indexOf('data:image/webp') === 0;
  } catch (e) {
    return false;
  }
}

function toBlob(canvas, mime, q) {
  return new Promise((res, rej) => canvas.toBlob((b) => (b ? res(b) : rej(new Error('Browserul nu a putut salva imaginea paginii.'))), mime, q));
}

/* ------------------------------------------------------------------ */
/* Cuprins (tabel editabil)                                            */
/* ------------------------------------------------------------------ */

function renderToc() {
  const tb = $('p45-toc-rows');
  tb.innerHTML = tocRows.map((r, i) => (
    '<tr data-i="' + i + '">' +
      '<td><label class="screen-reader-text" for="tt' + i + '">Titlu rând ' + (i + 1) + '</label><input id="tt' + i + '" type="text" data-k="t" value="' + esc(r.t) + '"></td>' +
      '<td><label class="screen-reader-text" for="ts' + i + '">Subtitlu rând ' + (i + 1) + '</label><input id="ts' + i + '" type="text" data-k="s" value="' + esc(r.s) + '"></td>' +
      '<td><label class="screen-reader-text" for="tp' + i + '">Pagina rând ' + (i + 1) + '</label><input id="tp' + i + '" type="number" min="1" data-k="p" value="' + esc(r.p) + '"></td>' +
      '<td><button type="button" class="p45-x" data-del="' + i + '" aria-label="Șterge rândul ' + (i + 1) + '">×</button></td>' +
    '</tr>'
  )).join('');
  $('p45-toc-gol').hidden = tocRows.length > 0;
}

$('p45-toc-rows').addEventListener('input', (e) => {
  const tr = e.target.closest('tr');
  const k = e.target.getAttribute('data-k');
  if (!tr || !k) return;
  const r = tocRows[+tr.getAttribute('data-i')];
  r[k] = k === 'p' ? Math.max(1, parseInt(e.target.value, 10) || 1) : e.target.value;
});
$('p45-toc-rows').addEventListener('click', (e) => {
  const b = e.target.closest('[data-del]');
  if (!b) return;
  tocRows.splice(+b.getAttribute('data-del'), 1);
  renderToc();
});
$('p45-toc-adauga').addEventListener('click', () => {
  const last = tocRows.length ? tocRows[tocRows.length - 1].p : 1;
  tocRows.push({ t: '', s: '', p: last });
  renderToc();
  const inputs = $('p45-toc-rows').querySelectorAll('input[data-k="t"]');
  if (inputs.length) inputs[inputs.length - 1].focus();
});
$('p45-toc-detecteaza').addEventListener('click', async () => {
  if (!pdfDoc || busy) return;
  if (tocRows.length && !confirm('Înlocuiesc cuprinsul de mai jos cu cel găsit în PDF?')) return;
  await detectToc(true);
});
renderToc();

/* ------------------------------------------------------------------ */
/* Citirea PDF-ului                                                    */
/* ------------------------------------------------------------------ */

const drop = $('p45-drop');
$('p45-pdf').addEventListener('change', (e) => { if (e.target.files[0]) openPdf(e.target.files[0]); });
['dragenter', 'dragover'].forEach((ev) => drop.addEventListener(ev, (e) => { e.preventDefault(); drop.classList.add('p45-drop-activ'); }));
['dragleave', 'drop'].forEach((ev) => drop.addEventListener(ev, (e) => { e.preventDefault(); drop.classList.remove('p45-drop-activ'); }));
drop.addEventListener('drop', (e) => {
  const f = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
  if (f) openPdf(f);
});
if ($('p45-inlocuieste')) {
  $('p45-inlocuieste').addEventListener('click', () => {
    $('p45-fisier-curent').hidden = true;
    drop.hidden = false;
  });
}
$('p45-alt-pdf').addEventListener('click', () => {
  if (busy) return;
  resetPdf();
  drop.hidden = false;
  $('p45-pdf').value = '';
  $('p45-pdf').click();
});

function resetPdf() {
  if (pdfDoc) { try { pdfDoc.destroy(); } catch (e) {} }
  pdfDoc = null;
  pdfFile = null;
  $('p45-analiza').hidden = true;
  $('p45-toc-detecteaza').hidden = true;
  updateSaveLabel();
}

function check(idc, text, kind) {
  let li = document.getElementById(idc);
  if (!li) {
    li = document.createElement('li');
    li.id = idc;
    $('p45-verificari').appendChild(li);
  }
  li.className = 'p45-v-' + (kind || 'ok');
  li.innerHTML = text;
}

function cleanTitle(name) {
  let t = name.replace(/\.pdf$/i, '');
  t = t.replace(/^(interior|int|tipar|final|print)[_\s-]+/i, '');
  t = t.replace(/[_\s-]*\d+([_,.]\d+)?\s*x\s*\d+([_,.]\d+)?[_\s-]*(cm|mm)?\b/ig, ' ');
  t = t.replace(/[_]+/g, ' ').replace(/\s+-\s+/g, ' – ').replace(/\s+/g, ' ').trim();
  return t ? t.charAt(0).toUpperCase() + t.slice(1) : '';
}

function usefulMeta(s) {
  if (!s || typeof s !== 'string') return '';
  const t = s.trim();
  if (t.length < 3 || /\.(indd|pdf|doc|qxp)|untitled|microsoft|adobe|^document/i.test(t)) return '';
  return t;
}

async function openPdf(file) {
  if (busy) return;
  if (!/\.pdf$/i.test(file.name) && file.type !== 'application/pdf') {
    showMsg('Alege un fișier PDF.', true);
    return;
  }
  $('p45-rezultat').hidden = true;
  drop.hidden = true;
  $('p45-analiza').hidden = false;
  $('p45-verificari').innerHTML = '';
  $('p45-nume-pdf').textContent = file.name;
  $('p45-info-pdf').textContent = mb(file.size) + ' · se citește…';
  try {
    const lib = await loadPdfjs();
    const data = new Uint8Array(await file.arrayBuffer());
    if (pdfDoc) { try { await pdfDoc.destroy(); } catch (e) {} }
    pdfDoc = await lib.getDocument({
      data,
      standardFontDataUrl: C.fonturi,
      wasmUrl: C.wasm,
      iccUrl: C.icc,
      isEvalSupported: false,
    }).promise;
    pdfFile = file;
    const p1 = await pdfDoc.getPage(1);
    const vp = p1.getViewport({ scale: 1 });
    const cm = (x) => (x / 72 * 2.54).toLocaleString('ro-RO', { maximumFractionDigits: 1 });
    $('p45-info-pdf').textContent = mb(file.size) + ' · ' + pdfDoc.numPages + ' pagini · ' + cm(vp.width) + ' × ' + cm(vp.height) + ' cm';
    check('chk-pag', '<strong>' + pdfDoc.numPages + ' pagini</strong> gata de convertit, la ' + C.latime + ' px lățime', 'ok');

    let meta = null;
    try { meta = await pdfDoc.getMetadata(); } catch (e) { meta = null; }
    const info = (meta && meta.info) || {};
    if (!$('p45-titlu').value.trim()) $('p45-titlu').value = usefulMeta(info.Title) || cleanTitle(file.name);
    if (!$('p45-autor').value.trim() && usefulMeta(info.Author)) $('p45-autor').value = usefulMeta(info.Author);

    $('p45-toc-detecteaza').hidden = false;
    updateSaveLabel();
    await detectToc(!tocRows.length);
  } catch (e) {
    console.error(e);
    resetPdf();
    drop.hidden = false;
    showMsg('Nu am putut citi PDF-ul. ' + esc(e && e.message ? e.message : e) + '<br>Dacă fișierul are parolă sau e foarte vechi, salvează-l din nou ca PDF și încearcă iar.', true);
  }
}

/** Textul unei pagini, grupat pe rânduri (de sus în jos). */
async function pageLines(n) {
  const page = await pdfDoc.getPage(n);
  const vp = page.getViewport({ scale: 1 });
  const tc = await page.getTextContent();
  const lines = [];
  for (const it of tc.items) {
    if (!it.str || !it.str.trim()) continue;
    const x = it.transform[4];
    const y = it.transform[5];
    const h = Math.abs(it.transform[3]) || it.height || 10;
    let line = null;
    for (const l of lines) { if (Math.abs(l.y - y) < Math.max(2, h * 0.45)) { line = l; break; } }
    if (!line) { line = { y, h, parts: [] }; lines.push(line); }
    line.h = Math.max(line.h, h);
    line.parts.push({ x, w: it.width || 0, s: it.str });
  }
  lines.sort((a, b) => b.y - a.y);
  for (const l of lines) {
    l.parts.sort((a, b) => a.x - b.x);
    let txt = '';
    let end = null;
    let prevS = '';
    for (const p of l.parts) {
      let s = p.s;
      /* Ligaturile (fi, fl) vin uneori ca bucăți separate urmate de un spațiu fals: „fi e” → „fie”. */
      if (/^(fi|fl|ff|ffi|ffl|ﬁ|ﬂ)$/.test(prevS) && /^\s/.test(s) && p.x - end < l.h * 0.25) s = s.replace(/^\s+/, '');
      if (end !== null && p.x - end > l.h * 0.18 && !/\s$/.test(txt) && !/^\s/.test(s)) txt += ' ';
      txt += s;
      end = p.x + p.w;
      prevS = p.s;
    }
    l.text = txt.replace(/\s+/g, ' ').trim();
  }
  page.cleanup();
  return { lines, height: vp.height };
}

const HEAD = /^(capitolul|capitol|unitatea( de învățare| de invatare)?|lec[țţt]ia|partea|modulul|tema)\s+([IVXLCDM]+|\d+)\b\.?/i;

function isCuprinsTitle(t) {
  return /^cuprins(ul)?$/i.test(String(t).replace(/[^A-Za-zĂÂÎȘȚăâîșțŞŢşţ]/g, ''));
}

/** Citește pagina „Cuprins” a cărții: rânduri de forma „Titlu . . . . . 12”. */
function parseCuprins(pagesText, N) {
  const cand = [];
  for (let i = 1; i <= Math.min(N, 14); i++) cand.push(i);
  for (let i = N; i > Math.max(14, N - 14); i--) cand.push(i);
  let start = null;
  for (const n of cand) {
    const L = pagesText[n];
    if (L && L.slice(0, 4).some((l) => isCuprinsTitle(l.text))) { start = n; break; }
  }
  if (!start) return [];
  const out = [];
  for (let n = start; n <= Math.min(N, start + 3); n++) {
    const L = pagesText[n];
    if (!L) break;
    const page = [];
    let pending = '';
    for (const l of L) {
      const t = l.text.trim();
      if (!t || isCuprinsTitle(t)) continue;
      /* „Titlu . . . . 12” (numărul poate fi scris cu un font care nu se citește ca cifre) sau „Titlu 12”. */
      const m = t.match(/^(.*?\S)\s*(?:[.·…_]\s*){3,}\s*(\S{0,6})$/) || t.match(/^(.*?[^\d\s.])\s+(\d{1,4})$/);
      if (m) {
        const title = ((pending ? pending + ' ' : '') + m[1].replace(/[.·…_\s]+$/, '')).replace(/\s+/g, ' ').trim();
        pending = '';
        const p = /^\d{1,4}$/.test(m[2]) ? parseInt(m[2], 10) : null;
        if (title.length >= 2) page.push({ t: title, s: '', p });
      } else if (!/^\S{1,4}$/.test(t) && t.length < 120) {
        pending = pending ? pending + ' ' + t : t;
        if (pending.length > 160) pending = '';
      }
    }
    if (n > start && page.length < 2) break;
    out.push(...page);
    out.cuprinsEnd = n;
  }
  out.cuprinsPage = start;
  return out;
}

/** Text fără diacritice, spații și semne, ca să comparăm titluri despărțite ciudat în PDF. */
function compact(s) {
  return String(s).toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '');
}

/** Începe vreun rând de sus al paginii (singur sau cu următoarele) cu titlul dat? */
function pageStartsWith(L, key) {
  for (let i = 0; i < Math.min(L.length, 5); i++) {
    const txt = compact(L.slice(i, i + 3).map((l) => l.text).join(' '));
    if (txt.indexOf(key) === 0) {
      const first = compact(L[i].text);
      /* Primul rând trebuie să fie chiar titlul (sau începutul lui): „Capitolul I” nu se potrivește pe „Capitolul II”. */
      if (first.length <= key.length || (key.length >= 40 && first.indexOf(key) === 0)) return true;
    }
  }
  return false;
}

/**
 * Pune fiecare rând din cuprins pe pagina din PDF unde începe de fapt (titlul apare sus pe pagină).
 * Dacă titlul nu se găsește, folosește numărul din cuprins corectat cu diferența observată la celelalte.
 */
function locateCuprins(entries, pagesText, N) {
  const skip = entries.cuprinsPage || 0;
  const skipEnd = entries.cuprinsEnd || skip;
  let from = 1;
  let offset = null;
  const out = [];
  for (const e of entries) {
    const key = compact(e.t).slice(0, 40);
    let found = 0;
    if (key.length >= 3) {
      for (let q = from; q <= N && !found; q++) {
        if (skip && q >= skip && q <= skipEnd) continue;
        const L = pagesText[q];
        if (L && pageStartsWith(L, key)) found = q;
      }
    }
    if (found) {
      if (e.p && offset === null) offset = found - e.p;
      out.push({ t: e.t, s: e.s, p: found });
      from = found;
    } else if (e.p) {
      const p = e.p + (offset || 0);
      if (p >= 1 && p <= N) out.push({ t: e.t, s: e.s, p });
    }
  }
  return out;
}

async function detectToc(replace) {
  check('chk-toc', 'Caut capitolele în PDF…', 'info');
  const N = pdfDoc.numPages;
  let found = [];

  /* 1. Semnele de carte din PDF, dacă există. */
  try {
    const outline = await pdfDoc.getOutline();
    if (outline && outline.length >= 2) {
      for (const o of outline) {
        let dest = o.dest;
        if (typeof dest === 'string') dest = await pdfDoc.getDestination(dest);
        if (Array.isArray(dest) && dest[0]) {
          const idx = await pdfDoc.getPageIndex(dest[0]);
          found.push({ t: (o.title || '').trim(), s: '', p: idx + 1 });
        }
      }
      found = found.filter((r) => r.t);
    }
  } catch (e) { found = []; }

  /* 2. Altfel, titluri de forma „Capitolul IV”, „Unitatea 2”, „Lecția 5”. */
  let isbn = '';
  let an = '';
  const scanCount = found.length >= 2 ? Math.min(N, 10) : N;
  const head = [];
  const pagesText = {};
  for (let n = 1; n <= scanCount; n++) {
    if (n % 8 === 0) check('chk-toc', 'Caut capitolele în PDF… ' + n + ' din ' + N, 'info');
    let pl;
    try { pl = await pageLines(n); } catch (e) { continue; }
    pagesText[n] = pl.lines.map((l) => ({ text: l.text }));
    const all = pl.lines.map((l) => l.text).join('\n');
    if (!isbn) {
      const m = all.match(/ISBN[:\s]*((?:97[89][-\s]?)?\d{1,5}[-\s]\d{1,7}[-\s]\d{1,7}[-\s][\dXx])/);
      if (m) isbn = m[1].replace(/\s+/g, '-');
    }
    if (!an && n <= 8) {
      const m = all.match(/(?:©|Copyright|\(c\))[^\n]{0,60}?\b((?:19|20)\d\d)\b/i);
      if (m) an = m[1];
    }
    if (found.length >= 2) continue;
    const top = pl.lines.filter((l) => l.y > pl.height * 0.45);
    for (let i = 0; i < top.length; i++) {
      const l = top[i];
      if (l.text.length > 60) continue;
      const m = l.text.match(HEAD);
      if (!m) continue;
      let title = l.text.replace(/\s+/g, ' ').trim();
      let sub = [];
      let subH = null;
      let prevY = l.y;
      for (let j = pl.lines.indexOf(l) + 1; j < pl.lines.length && sub.length < 3; j++) {
        const nx = pl.lines[j];
        if (!nx.text || nx.text.length < 3) break;
        if (subH !== null && Math.abs(nx.h - subH) > subH * 0.2) break;
        if (HEAD.test(nx.text)) break;
        /* Subtitlul are rândurile apropiate; textul capitolului începe după un spațiu mai mare. */
        const gap = prevY - nx.y;
        if (gap > nx.h * (sub.length ? 2.0 : 4.5)) break;
        subH = subH === null ? nx.h : subH;
        sub.push(nx.text);
        prevY = nx.y;
        if (sub.join(' ').length > 170) break;
      }
      let s = sub.join(' ').replace(/\s+[A-ZĂÂÎȘȚ]$/, '').replace(/[-‐]\s+/g, '').trim();
      if (s.length > 220) s = s.slice(0, 217).replace(/\s+\S*$/, '') + '…';
      if (title === s) s = '';
      head.push({ t: title, s, p: n });
      break;
    }
  }
  if (found.length < 2) {
    /* Păstrăm doar prima apariție a fiecărui titlu (unele cărți repetă „Capitolul I” în antet). */
    const seen = new Set();
    const heads = head.filter((r) => {
      const k = r.t.toLowerCase();
      if (seen.has(k)) return false;
      seen.add(k);
      return true;
    });
    /* 3. Pagina „Cuprins” a cărții. Folosim titlurile găsite în pagini doar dacă sunt aproape la fel de multe. */
    let cupr = [];
    try {
      cupr = parseCuprins(pagesText, N);
      cupr = cupr.length >= 3 ? locateCuprins(cupr, pagesText, N) : [];
    } catch (e) { cupr = []; }
    if (cupr.length >= 3 && heads.length < cupr.length * 0.8) found = cupr;
    else found = heads.length ? heads : cupr;
  }

  if (isbn && !$('p45-isbn').value.trim()) $('p45-isbn').value = isbn;
  if (an && !$('p45-an').value.trim()) $('p45-an').value = an;
  if (isbn) check('chk-isbn', '<strong>ISBN găsit:</strong> ' + esc(isbn), 'ok');

  if (found.length) {
    if (replace) {
      tocRows = found;
      renderToc();
    }
    check('chk-toc', '<strong>Cuprins găsit:</strong> ' + found.length + (found.length === 1 ? ' capitol' : ' capitole') + (replace ? '. Verifică titlurile mai jos.' : '. Apasă „Caută din nou în PDF” ca să-l folosești.'), 'ok');
  } else {
    check('chk-toc', 'Nu am găsit capitole în PDF. Poți adăuga cuprinsul de mână (e opțional).', 'info');
  }
}

/* ------------------------------------------------------------------ */
/* Copertă                                                             */
/* ------------------------------------------------------------------ */

$('p45-coperta').addEventListener('change', (e) => {
  const f = e.target.files[0];
  if (!f) return;
  if (!/^image\/(jpeg|png|webp)$/.test(f.type)) {
    showMsg('Coperta trebuie să fie JPG, PNG sau WebP.', true);
    e.target.value = '';
    return;
  }
  coverFile = f;
  const url = URL.createObjectURL(f);
  $('p45-coperta-prev').innerHTML = '<img src="' + url + '" alt="Coperta aleasă">';
  $('p45-coperta-hint').textContent = f.name + ' · se urcă la salvare.';
});

/* ------------------------------------------------------------------ */
/* Salvare și conversie                                                */
/* ------------------------------------------------------------------ */

function checkedValues(boxId) {
  return Array.from(document.querySelectorAll('#' + boxId + ' input:checked')).map((i) => parseInt(i.value, 10));
}

function details() {
  const st = document.querySelector('input[name="p45-status"]:checked');
  return {
    titlu: $('p45-titlu').value.trim(),
    autor: $('p45-autor').value.trim(),
    isbn: $('p45-isbn').value.trim(),
    an: $('p45-an').value.trim(),
    clase: checkedValues('p45-clase'),
    discipline: checkedValues('p45-discipline'),
    toc: tocRows.filter((r) => String(r.t).trim()).map((r) => ({ t: String(r.t).trim(), s: String(r.s || '').trim(), p: parseInt(r.p, 10) || 1 })),
    status: st ? st.value : 'publish',
  };
}

function updateSaveLabel() {
  const b = $('p45-salveaza');
  if (!id) b.textContent = 'Convertește și salvează';
  else b.textContent = pdfDoc ? 'Convertește din nou și salvează' : 'Salvează modificările';
}

function setBusy(on) {
  busy = on;
  $('p45-salveaza').hidden = on;
  $('p45-progres').hidden = !on;
  document.querySelectorAll('#p45-editor input, #p45-editor button').forEach((el) => {
    if (el.id !== 'p45-opreste') el.disabled = on;
  });
}

function progress(n, total, t0, label) {
  $('p45-progres-text').textContent = label || ('Pagina ' + n + ' din ' + total);
  $('p45-progres-nr').textContent = Math.round(100 * n / total) + '%';
  $('p45-bara').style.width = (100 * n / total) + '%';
  if (n >= 3 && n < total) {
    const per = (performance.now() - t0) / n;
    const left = Math.round(per * (total - n) / 1000);
    $('p45-progres-eta').textContent = 'Mai durează ' + (left < 60 ? 'sub un minut' : 'cam ' + Math.round(left / 60) + ' min') + '. Nu închide această pagină.';
  }
}

window.addEventListener('beforeunload', (e) => {
  if (busy) { e.preventDefault(); e.returnValue = ''; }
});
$('p45-opreste').addEventListener('click', () => {
  if (confirm('Oprești conversia? Cartea rămâne cum era înainte.')) {
    stopRequested = true;
    $('p45-progres-text').textContent = 'Se oprește…';
  }
});

async function uploadPage(token, n, big, small, ext) {
  const name = String(n).padStart(3, '0') + '.' + ext;
  for (let a = 1; a <= 4; a++) {
    try {
      const f = new FormData();
      f.append('token', token);
      f.append('n', String(n));
      f.append('pagina', big, name);
      f.append('mini', small, name);
      return await api('carti/' + id + '/pagina', { method: 'POST', form: f });
    } catch (e) {
      if (a === 4 || [401, 403, 409, 413, 415].indexOf(e.status) !== -1) throw e;
      await sleep(1500 * a);
    }
  }
  return null;
}

async function renderPage(n, width, canvas) {
  const page = await pdfDoc.getPage(n);
  const vp1 = page.getViewport({ scale: 1 });
  const vp = page.getViewport({ scale: width / vp1.width });
  canvas.width = Math.round(vp.width);
  canvas.height = Math.round(vp.height);
  const ctx = canvas.getContext('2d', { alpha: false });
  ctx.fillStyle = '#ffffff';
  ctx.fillRect(0, 0, canvas.width, canvas.height);
  await page.render({ canvasContext: ctx, viewport: vp, background: '#ffffff' }).promise;
  page.cleanup();
}

async function convertPages(token) {
  const N = pdfDoc.numPages;
  const fmt = webpOk() ? { ext: 'webp', mime: 'image/webp' } : { ext: 'jpg', mime: 'image/jpeg' };
  const big = document.createElement('canvas');
  const mini = document.createElement('canvas');
  const t0 = performance.now();
  let first = null;
  let pending = null;
  for (let n = 1; n <= N; n++) {
    if (stopRequested) throw new Error('STOP');
    await renderPage(n, C.latime, big);
    if (!first) first = { w: big.width, h: big.height };
    const bigBlob = await toBlob(big, fmt.mime, C.calitate);
    const mw = 180;
    const mh = Math.round(big.height * mw / big.width);
    mini.width = mw;
    mini.height = mh;
    const mctx = mini.getContext('2d', { alpha: false });
    mctx.fillStyle = '#ffffff';
    mctx.fillRect(0, 0, mw, mh);
    mctx.imageSmoothingQuality = 'high';
    mctx.drawImage(big, 0, 0, mw, mh);
    const miniBlob = await toBlob(mini, fmt.mime, 0.72);
    if (pending) await pending;
    pending = uploadPage(token, n, bigBlob, miniBlob, fmt.ext);
    progress(n, N, t0);
  }
  if (pending) await pending;
  return { n: N, w: first.w, h: first.h, ext: fmt.ext };
}

async function uploadCover(file, token) {
  const f = new FormData();
  if (token) f.append('token', token);
  f.append('coperta', file, file.name || 'coperta');
  return api('carti/' + id + '/coperta', { method: 'POST', form: f });
}

async function coverFromFirstPage(ext) {
  const c = document.createElement('canvas');
  await renderPage(1, 700, c);
  const mime = ext === 'webp' ? 'image/webp' : 'image/jpeg';
  const b = await toBlob(c, mime, 0.82);
  return new File([b], 'coperta.' + ext, { type: mime });
}

$('p45-salveaza').addEventListener('click', save);

async function save() {
  if (busy) return;
  const det = details();
  if (!det.titlu) {
    showMsg('Scrie titlul cărții.', true);
    $('p45-titlu').focus();
    return;
  }
  if (!id && !pdfDoc) {
    showMsg('Alege întâi PDF-ul cărții (pasul 1).', true);
    return;
  }
  stopRequested = false;
  setBusy(true);
  $('p45-rezultat').hidden = true;
  try { if (navigator.wakeLock) wakeLock = await navigator.wakeLock.request('screen'); } catch (e) { wakeLock = null; }
  try {
    if (pdfDoc) {
      progress(0, 1, performance.now(), 'Pregătesc conversia…');
      const extra = { sursa: pdfFile.name, sursa_marime: pdfFile.size, pagini_pdf: pdfDoc.numPages };
      const res = id
        ? await api('carti/' + id + '/conversie', { method: 'POST', json: Object.assign({}, det, extra) })
        : await api('carti', { method: 'POST', json: Object.assign({}, det, extra) });
      const isNew = !id;
      id = res.id;
      if (isNew) {
        try { history.replaceState(null, '', C.editare + id); } catch (e) {}
      }
      const out = await convertPages(res.token);
      progress(1, 1, performance.now(), 'Salvez coperta…');
      if (coverFile) await uploadCover(coverFile, res.token);
      else if (!C.areCoperta) await uploadCover(await coverFromFirstPage(out.ext), res.token);
      progress(1, 1, performance.now(), 'Finalizez…');
      await api('carti/' + id + '/finalizare', {
        method: 'POST',
        json: { token: res.token, pagini: out.n, w: out.w, h: out.h, ext: out.ext, publica: det.status === 'publish' },
      });
      await api('carti/' + id, { method: 'POST', json: { status: det.status } });
    } else {
      await api('carti/' + id, { method: 'POST', json: det });
      if (coverFile) await uploadCover(coverFile, null);
    }
    busy = false;
    window.location.href = C.editare + id + '&salvat=1';
    return;
  } catch (e) {
    console.error(e);
    if (e && e.message === 'STOP') {
      showMsg(id && C.gata ? 'Conversia a fost oprită. Cartea a rămas cu paginile de dinainte.' : 'Conversia a fost oprită. Cartea a rămas ciornă, fără pagini; o poți converti din nou oricând.', true);
    } else {
      showMsg('Nu am putut termina: ' + esc(e && e.message ? e.message : e) + '<br>Poți apăsa din nou pe buton: conversia o ia de la capăt, iar cartea rămâne cum era până atunci.', true);
    }
  } finally {
    if (wakeLock) { try { await wakeLock.release(); } catch (e) {} wakeLock = null; }
    if (busy) setBusy(false);
    updateSaveLabel();
  }
}

updateSaveLabel();
