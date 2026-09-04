/* ═══════════ CloudOn Projects — κοστολόγηση & προσφορά PharmacyOne ═══════════
   Ήταν αυτόνομο αρχείο HTML: έφτιαχνες την κοστολόγηση, τύπωνες PDF, και μετά
   η προσφορά χανόταν — κανείς δεν ήξερε αν στάλθηκε, αν απάντησαν, αν χάθηκε.
   Τώρα ο κοστολογητής γεννά **κανονική προσφορά** στο κύκλωμα, με στάδια,
   παρακολούθηση αποστολής και follow-up όπως κάθε άλλη.

   Ο υπολογισμός γίνεται στον server (lib/Pharmacy.php) — η οθόνη μόνο ρωτάει.
   Έτσι το ποσό της προσφοράς και οι αριθμοί του εγγράφου δεν γίνεται να
   διαφέρουν. */
'use strict';
const {S, api, esc, fmtEur, toast, cnpDenied, cnpCan, closeDrawer, I, go, $, $$} = window.CNP;
const R = window.R;

let PH = null;          // ο κατάλογος (params/modules/rates/editions) — φορτώνεται μία φορά
let phTimer = null;     // debounce της ζωντανής προεπισκόπησης

async function phDefs() {
  if (PH) { return PH; }
  PH = await api('pharmacy_defs');
  return PH;
}
const phMoney = v => fmtEur(Math.round((+v || 0) * 100) / 100);

/**
 * @param {number|null} offerId  υπάρχουσα προσφορά προς αναθεώρηση
 * @param {object|null} pre      {client, name} όταν ξεκινά από πελάτη
 */
async function openPharmacy(offerId, pre) {
  if (!cnpCan('clients.offers')) { toast('Δεν έχεις δικαίωμα στις Προσφορές', true); return; }
  closeDrawer();
  const ovl = document.createElement('div'); ovl.className = 'ovl';
  const dr = document.createElement('div'); dr.className = 'drawer tk-modal ph-dr';
  dr.innerHTML = `<div class="drawer-h"><h2>${I.doc} Κοστολόγηση PharmacyOne</h2>
    <button class="drawer-x" id="dX">✕</button></div>
    <div class="drawer-b" id="phBody"><div class="skel" style="height:340px"></div></div>`;
  document.body.append(ovl, dr);
  requestAnimationFrame(() => { ovl.classList.add('show'); dr.classList.add('show'); });
  $('#dX', dr).onclick = () => closeDrawer();

  const body = $('#phBody', dr);
  let defs;
  try { defs = await phDefs(); } catch (e) { body.innerHTML = cnpDenied(e); return; }

  /* ── η ρύθμιση ── */
  const st = {
    offer: offerId || 0,
    client: (pre && pre.client) || 0,
    clientName: (pre && pre.name) || '',
    tab: 'setup',
    cfg: {p: Object.assign({}, defs.defaults.p), yn: Object.assign({}, defs.defaults.yn),
      r: Object.assign({}, defs.defaults.r), sel: 2,
      ed: defs.editions.map(e => ({price: e.price, extraUser: e.extraUser})),
      o: {seller: defs.me || '', city: 'Αθήνα', vat: 24, validDays: 30, prepay: 50, discount: 0,
        protocol: defs.nextProtocol || ('CLD-' + new Date().getFullYear() + '-'), date: new Date().toISOString().slice(0, 10)}},
    calc: null,
  };
  if (offerId) {
    const ex = await api('pharmacy_doc&offer=' + offerId).catch(() => null);
    if (ex && ex.cfg) {
      st.cfg = ex.cfg;
      st.client = ex.client || 0;
      st.clientName = ex.cfg.o.client || '';
    }
  }
  /* Ο server επιστρέφει την κανονικοποιημένη ρύθμιση — τη δεχόμαστε ως αλήθεια. */
  const recalc = async () => {
    const r = await api('pharmacy_calc', {config: st.cfg}).catch(() => null);
    if (!r) { return; }
    st.cfg = r.cfg;
    st.calc = r;
    paintNumbers();
  };
  const touch = () => { clearTimeout(phTimer); phTimer = setTimeout(recalc, 220); };
  /* Μετά από ΑΑΔΕ/AI, γράψε τα αντλημένα στοιχεία στα ορατά πεδία (χωρίς rerender). */
  const syncDoc = () => {
    $$('[data-o]', body).forEach(inp => {
      const k = inp.dataset.o;
      if (k in st.cfg.o) { inp.value = st.cfg.o[k] === undefined || st.cfg.o[k] === null ? '' : st.cfg.o[k]; }
    });
    const w = $('#phWho', body); if (w) { w.value = st.clientName || ''; }
    const a = $('#phAfm', body); if (a) { a.value = st.cfg.o.afm || ''; }
  };

  /* ── σκελετός ── */
  const shell = () => {
    body.innerHTML = `
    <div class="ph-top">
      <div class="ph-who">
        <input class="inp" id="phWho" placeholder="Επωνυμία πελάτη…" value="${esc(st.clientName)}" autocomplete="off">
        <input class="inp ph-afm" id="phAfm" placeholder="ΑΦΜ" maxlength="9" inputmode="numeric"
          value="${esc(st.cfg.o.afm || '')}">
        <button class="btn btn-o btn-sm" id="phAade" title="Άντληση στοιχείων από το μητρώο ΑΑΔΕ">Άντληση ΑΑΔΕ</button>
        <button class="btn btn-p btn-sm" id="phAI" title="Περίγραψε τι θέλει ο πελάτης — ο Copilot συμπληρώνει έκδοση, modules, παραμέτρους και στοιχεία">✨ Από περιγραφή</button>
        <span class="mut ph-afmst" id="phAfmSt"></span>
      </div>
      <div id="phPick"></div>
      <div class="td-seg ph-tabs">
        ${[['setup', 'Παράμετροι'], ['modules', 'Modules'], ['rates', 'Τιμοκατάλογος'], ['doc', 'Έγγραφο']]
          .map(([k, l]) => `<button data-tab="${k}" class="${st.tab === k ? 'on' : ''}">${l}</button>`).join('')}
      </div>
    </div>
    <div id="phCards" class="ph-cards"></div>
    <div id="phPane"></div>
    <div class="ph-foot">
      <div class="mut ph-sum" id="phSum"></div>
      <button class="btn btn-o" id="phPrint">${I.doc} Εκτύπωση</button>
      ${st.offer && cnpCan('clients.offer_delete') ? '<button class="btn btn-danger" id="phDel">🗑 Διαγραφή</button>' : ''}
      <button class="btn btn-p" id="phSave">${st.offer ? 'Ενημέρωση προσφοράς' : 'Δημιουργία προσφοράς'}</button>
    </div>`;
    $$('[data-tab]', body).forEach(b => b.onclick = () => { st.tab = b.dataset.tab; shell(); paintNumbers(); });
    wireWho();
    $('#phPrint', body).onclick = printDoc;
    $('#phSave', body).onclick = save;
    const pdl = $('#phDel', body); if (pdl) { pdl.onclick = async () => {
      if (!(await window.CNP.cnpConfirm('Να διαγραφεί οριστικά αυτή η προσφορά PharmacyOne;', {ok: '🗑 Διαγραφή', cancel: 'Άκυρο'}))) { return; }
      const r = await api('delete_offer', {offer: st.offer}).catch(e => ({err: e && e.message}));
      if (r && r.err) { toast(r.err, true); return; }
      toast('Η προσφορά διαγράφηκε'); closeDrawer();
      if (S.view === 'offers') { R.offers(); } else { go('offers'); }
    }; }
    pane();
  };

  /* ── ποιος πελάτης ── */
  function wireWho() {
    const w = $('#phWho', body);
    let t = null;
    w.oninput = () => {
      st.clientName = w.value.trim();
      st.client = 0;
      st.cfg.o.client = st.clientName;
      clearTimeout(t);
      if (w.value.trim().length < 3) { $('#phPick', body).innerHTML = ''; return; }
      t = setTimeout(async () => {
        const r = await api('call_who&q=' + encodeURIComponent(w.value.trim())).catch(() => null);
        const list = ((r && r.results) || []).filter(x => x.type === 'client');
        $('#phPick', body).innerHTML = list.length ? `<div class="qc-list">${list.map(x =>
          `<div class="qc-opt" data-i="${x.id}" data-n="${esc(x.name)}">${I.user}<b>${esc(x.name)}</b></div>`).join('')}</div>` : '';
        $$('.qc-opt', body).forEach(el => el.onclick = () => {
          st.client = +el.dataset.i; st.clientName = el.dataset.n;
          st.cfg.o.client = st.clientName;
          w.value = st.clientName; $('#phPick', body).innerHTML = '';
        });
      }, 250);
    };
    const a = $('#phAfm', body);
    a.oninput = () => { st.cfg.o.afm = a.value.replace(/\D/g, '').slice(0, 9); a.value = st.cfg.o.afm; };
    /* Άντληση ΑΑΔΕ — κοινή, ώστε να την καλεί και το κουμπί και ο Copilot όταν βρει ΑΦΜ. */
    const aade = async afm => {
      const stEl = $('#phAfmSt', body);
      afm = String(afm || '').replace(/\D/g, '');
      if (afm.length !== 9) { if (stEl) { stEl.textContent = 'Δώσε 9ψήφιο ΑΦΜ'; } return; }
      if (stEl) { stEl.textContent = 'Αναζήτηση στο μητρώο…'; }
      const r = await fetch('afm.php?afm=' + afm, {credentials: 'same-origin'}).then(x => x.json()).catch(() => null);
      if (!r || !r.ok) { if (stEl) { stEl.textContent = 'ΑΑΔΕ: ' + ((r && r.error) || 'χωρίς αποτέλεσμα'); } return; }
      const dd = r.data || {};
      if (dd.name) { st.clientName = dd.name; st.cfg.o.client = dd.name; const w = $('#phWho', body); if (w) { w.value = dd.name; } }
      if (dd.doy) { st.cfg.o.doy = dd.doy; }
      const addr = [dd.street, dd.postcode, dd.city].filter(Boolean).join(', ');
      if (addr) { st.cfg.o.address = addr; }
      if (dd.kad) { st.cfg.o.activity = dd.kad; }
      if (dd.city) { st.cfg.o.city = dd.city; }
      if (stEl) { stEl.textContent = (dd.active === false ? '⚠ ανενεργό ΑΦΜ — ' : '✓ ') + (dd.name || ''); }
      syncDoc();
      if (st.tab === 'doc') { renderDoc(); }
    };
    $('#phAade', body).onclick = () => aade(st.cfg.o.afm || '');
    $('#phAI', body).onclick = () => openAiDraft(aade);
  }

  /* ✨ Copilot: από ελεύθερη περιγραφή → έκδοση, modules, παράμετροι, στοιχεία πελάτη.
     Το AI δεν αγγίζει τιμές — μόνο ρυθμίσεις· ο έλεγχος μένει στον χειριστή. */
  function openAiDraft(aade) {
    const ov = document.createElement('div'); ov.className = 'ovl show';
    ov.style.zIndex = '60';   // πάνω από το drawer του κοστολογητή (z-index 51)
    ov.innerHTML = `<div class="pal-box ai-box" onclick="event.stopPropagation()">
      <div class="ai-h"><b>✨ Δημιουργία από περιγραφή</b>
        <span class="mut" style="font-size:11.5px">έκδοση, modules, παράμετροι & στοιχεία πελάτη — αυτόματα</span></div>
      <div class="ai-b">
        <textarea class="inp" id="aiTxt" rows="6" placeholder="π.χ. Φαρμακείο «ΥΓΕΙΑ ΕΕ», ΑΦΜ 123456789, 3 χρήστες, 1 υποκατάστημα. Θέλει σύνδεση Skroutz &amp; myData, courier ACS, και εκπαίδευση. Υπόψη κας Παπαδοπούλου, 2101234567."></textarea>
        <div class="mut" style="font-size:11.5px;margin-top:6px">Γράψε ελεύθερα ό,τι ξέρεις — όσα περισσότερα, τόσο καλύτερα.</div>
        <div id="aiMsg" class="ai-msg" hidden></div>
      </div>
      <div class="ai-f"><button class="btn btn-o" id="aiX">Άκυρο</button>
        <button class="btn btn-p" id="aiOk">✨ Συμπλήρωσε</button></div></div>`;
    document.body.appendChild(ov);
    const q = sel => ov.querySelector(sel);
    const close = () => { ov.remove(); document.removeEventListener('keydown', esc, true); };
    const esc = e => { if (e.key === 'Escape') { e.stopPropagation(); close(); } };
    document.addEventListener('keydown', esc, true);
    ov.onclick = close; q('#aiX').onclick = close;
    setTimeout(() => q('#aiTxt').focus(), 60);
    q('#aiOk').onclick = async () => {
      const text = q('#aiTxt').value.trim();
      if (text.length < 5) { q('#aiTxt').focus(); return; }
      const okBtn = q('#aiOk'); okBtn.disabled = true; okBtn.textContent = '✨ Σκέφτομαι…';
      const r = await api('pharmacy_ai_draft', {text}).catch(e => ({err: e && e.message}));
      if (!r || r.err) { okBtn.disabled = false; okBtn.textContent = '✨ Συμπλήρωσε';
        const m = q('#aiMsg'); m.hidden = false; m.textContent = (r && r.err) || 'Δεν κατάλαβα — δοκίμασε πιο συγκεκριμένα'; return; }
      /* Ο server επιστρέφει κανονικοποιημένη & επικυρωμένη ρύθμιση — τη δεχόμαστε ως αλήθεια. */
      st.cfg = r.cfg;
      if (!st.offer && defs.nextProtocol) { st.cfg.o.protocol = defs.nextProtocol; }
      st.clientName = r.cfg.o.client || '';
      st.tab = 'setup';
      close();
      shell();
      await recalc();
      if (r.summary) { toast('✨ ' + r.summary); }
      /* Βρέθηκε ΑΦΜ → άντλησε επίσημα στοιχεία από την ΑΑΔΕ (υπερισχύουν). */
      if (r.afm) { aade(r.afm); }
    };
  }

  /* ── οι τέσσερις εκδόσεις ── */
  function paintNumbers() {
    if (!st.calc) { return; }
    const cards = $('#phCards', body);
    if (cards) {
      cards.innerHTML = defs.editions.map((e, i) => {
        const t = st.calc.totals[i];
        return `<button class="ph-card${st.cfg.sel === i ? ' on' : ''}" data-sel="${i}">
          ${st.cfg.sel === i ? '<span class="ph-rib">Στην προσφορά</span>' : ''}
          <div class="ph-name">${esc(e.name)}</div>
          <div class="ph-soft">${esc(e.soft1)}</div>
          <div class="ph-big">${phMoney(t.first)}</div>
          <div class="ph-lab">πρώτο έτος</div>
          <dl class="ph-kv">
            <div><dt>Ετήσιο</dt><dd>${phMoney(t.annual)}</dd></div>
            <div><dt>Εφάπαξ</dt><dd>${phMoney(t.oneoff)}</dd></div>
            <div class="ac"><dt>Μηνιαίο / χρήστη</dt><dd>${phMoney(t.monthlyPerUser)}</dd></div>
          </dl></button>`;
      }).join('');
      $$('.ph-card', body).forEach(b => b.onclick = () => { st.cfg.sel = +b.dataset.sel; recalc(); if (st.tab === 'doc') { pane(); } });
    }
    const sum = $('#phSum', body);
    if (sum) {
      const t = st.calc.totals[st.cfg.sel];
      const d = +st.cfg.o.discount || 0;
      sum.innerHTML = `<b>${esc(defs.editions[st.cfg.sel].name)}</b> · πρώτο έτος ${phMoney(t.first)}`
        + (d ? ` − έκπτωση ${phMoney(d)}` : '')
        + ` → <b style="color:var(--ink)">${phMoney(st.calc.amount)}</b> προ ΦΠΑ`;
    }
    if (st.tab === 'doc') { docPane(); }
  }

  /* ── τα φύλλα ── */
  function pane() {
    const el = $('#phPane', body);
    if (st.tab === 'setup') {
      el.innerHTML = `<div class="card"><div class="card-b">
        <label class="lbl" style="margin:0 0 9px">Παράμετροι εγκατάστασης</label>
        <div class="ph-fields">${defs.params.map(d => {
          const v = st.cfg.p[d.cell];
          const val = d.type === 'pct' ? Math.round(v * 1000) / 10 : v;
          return `<div class="field"><label>${esc(d.lab)}</label>
            <input class="inp" type="number" min="0" step="${d.type === 'pct' ? '1' : '1'}"
              data-p="${d.cell}" data-k="${d.type}" value="${val}">${d.type === 'pct' ? '<span class="ph-pct">%</span>' : ''}</div>`;
        }).join('')}</div></div></div>`;
      $$('[data-p]', el).forEach(inp => inp.oninput = () => {
        const v = parseFloat(inp.value); const n = isFinite(v) ? v : 0;
        st.cfg.p[inp.dataset.p] = inp.dataset.k === 'pct' ? n / 100 : n;
        touch();
      });
    } else if (st.tab === 'modules') {
      /* Διακόπτης, όχι κουτάκι: εδώ δηλώνεις τι μπαίνει μέσα στην προσφορά, και το
         «μέσα / έξω» θέλει να διαβάζεται με μια ματιά — και από απόσταση. */
      el.innerHTML = `<div class="ph-mods">${defs.groups.map(g => {
        const on = g.items.filter(it => st.cfg.yn[it.cell]).length;
        return `<div class="card"><div class="card-b">
        <div class="ph-gh"><label class="lbl" style="margin:0">${esc(g.title)}</label>
          <span class="ph-gn" data-gn="${esc(g.title)}">${on}/${g.items.length}</span></div>
        ${g.items.map(it => `<label class="ph-mod${st.cfg.yn[it.cell] ? ' on' : ''}">
          <span class="ph-modn">${esc(it.lab)}</span>
          <span class="switch"><input type="checkbox" data-yn="${it.cell}" data-grp="${esc(g.title)}"
            ${st.cfg.yn[it.cell] ? 'checked' : ''}><span></span></span></label>`).join('')}
      </div></div>`; }).join('')}</div>`;
      $$('[data-yn]', el).forEach(ch => ch.onchange = () => {
        st.cfg.yn[ch.dataset.yn] = ch.checked ? 1 : 0;
        ch.closest('.ph-mod').classList.toggle('on', ch.checked);
        const box = ch.closest('.card-b');
        const cnt = $('.ph-gn', box);
        if (cnt) { cnt.textContent = $$('[data-yn]:checked', box).length + '/' + $$('[data-yn]', box).length; }
        touch();
      });
    } else if (st.tab === 'rates') {
      el.innerHTML = `<div class="card"><div class="card-b">
        <label class="lbl" style="margin:0 0 4px">Τιμοκατάλογος</label>
        <div class="mut" style="font-size:11.5px;margin-bottom:11px">Οι αλλαγές ισχύουν μόνο για αυτή την προσφορά — ο γενικός τιμοκατάλογος δεν πειράζεται.</div>
        <div class="ph-rates">
          <div class="ph-rh">Περιγραφή</div><div class="ph-rh n">Τιμή €</div><div class="ph-rh n">Έκπτωση %</div>
          ${defs.rates.map(d => `
            <div class="ph-rl">${esc(d.lab)}</div>
            <div class="n"><input class="ph-mini" type="number" min="0" step="1"
              data-r="${d.cell}" data-k="num" value="${st.cfg.r[d.cell]}"></div>
            <div class="n">${d.adj
              ? `<input class="ph-mini" type="number" min="0" step="1" data-r="${d.adj}" data-k="pct"
                   value="${Math.round(st.cfg.r[d.adj] * 1000) / 10}">` : '<span class="mut">—</span>'}</div>`).join('')}
        </div>
        <label class="lbl" style="margin-top:16px">Τιμή έκδοσης & επιπλέον χρήστη</label>
        <div class="ph-rates">
          <div class="ph-rh">Έκδοση</div><div class="ph-rh n">Άδεια €</div><div class="ph-rh n">Extra user €</div>
          ${defs.editions.map((e, i) => `
            <div class="ph-rl">${esc(e.name)} <span class="mut">· ${esc(e.soft1)}</span></div>
            <div class="n"><input class="ph-mini" type="number" min="0" step="10"
              data-ed="${i}" data-f="price" value="${st.cfg.ed[i].price}"></div>
            <div class="n"><input class="ph-mini" type="number" min="0" step="5"
              data-ed="${i}" data-f="extraUser" value="${st.cfg.ed[i].extraUser}"></div>`).join('')}
        </div>
      </div></div>`;
      $$('[data-r]', el).forEach(inp => inp.oninput = () => {
        const v = parseFloat(inp.value); const n = isFinite(v) ? v : 0;
        st.cfg.r[inp.dataset.r] = inp.dataset.k === 'pct' ? n / 100 : n;
        touch();
      });
      $$('[data-ed]', el).forEach(inp => inp.oninput = () => {
        const v = parseFloat(inp.value);
        st.cfg.ed[+inp.dataset.ed][inp.dataset.f] = isFinite(v) ? v : 0;
        touch();
      });
    } else {
      docPane();
    }
  }

  const DOC_FIELDS = [
    ['attn', 'Υπόψη (ονοματεπώνυμο)', 'text'],
    ['greeting', 'Χαιρετισμός επιστολής', 'text'], ['cphone', 'Τηλέφωνο πελάτη', 'text'],
    ['cemail', 'Email πελάτη', 'text'], ['address', 'Διεύθυνση έδρας', 'text'],
    ['doy', 'Δ.Ο.Υ.', 'text'], ['protocol', 'Αριθμός πρωτοκόλλου', 'text'],
    ['date', 'Ημερομηνία', 'date'], ['city', 'Πόλη', 'text'],
    ['seller', 'Υπογράφων', 'text'], ['acceptAttn', 'Υπόψη — έντυπο αποδοχής', 'text'],
    ['discount', 'Επιπλέον έκπτωση (€)', 'num'], ['vat', 'ΦΠΑ %', 'num'],
    ['validDays', 'Ισχύς (ημέρες)', 'num'], ['prepay', 'Προκαταβολή %', 'num'],
  ];

  async function docPane() {
    const el = $('#phPane', body);
    if (!el.querySelector('#phDocFields')) {
      el.innerHTML = `<div class="card"><div class="card-b">
        <label class="lbl" style="margin:0 0 9px">Στοιχεία εγγράφου</label>
        <div class="ph-fields" id="phDocFields">${DOC_FIELDS.map(([k, lab, t]) =>
          `<div class="field"><label>${esc(lab)}</label>
            <input class="inp" type="${t === 'date' ? 'date' : (t === 'num' ? 'number' : 'text')}"
              data-o="${k}" data-k="${t}" value="${esc(st.cfg.o[k] === undefined ? '' : st.cfg.o[k])}"></div>`).join('')}
        </div></div></div>
        <div class="ph-doc" id="phDoc"><div class="skel" style="height:300px"></div></div>`;
      $$('[data-o]', el).forEach(inp => inp.oninput = () => {
        const k = inp.dataset.k;
        st.cfg.o[inp.dataset.o] = k === 'num' ? (parseFloat(inp.value) || 0) : inp.value;
        clearTimeout(phTimer);
        phTimer = setTimeout(async () => { await recalc(); renderDoc(); }, 320);
      });
    }
    renderDoc();
  }
  /* Το έγγραφο ζει σε iframe: το στυλ του δεν συγκρούεται με του πίνακα, δεν αλλάζει
     με το θέμα της εφαρμογής, και τυπώνεται ακριβώς όπως φαίνεται. */
  async function renderDoc() {
    const box = $('#phDoc', body);
    if (!box) { return; }
    const r = await api('pharmacy_doc', {config: st.cfg}).catch(() => null);
    if (!r) { box.innerHTML = '<div class="empty">Δεν παρήχθη το έγγραφο</div>'; return; }
    let fr = box.querySelector('iframe');
    if (!fr) {
      box.innerHTML = '';
      fr = document.createElement('iframe');
      fr.className = 'ph-frame';
      fr.title = 'Προεπισκόπηση προσφοράς';
      box.append(fr);
    }
    /* Το στυλ έρχεται από τον server μαζί με το περιεχόμενο: ένα έντυπο, μία πηγή. */
    fr.srcdoc = '<!doctype html><html lang="el"><head><meta charset="utf-8">'
      + '<base href="/project/">'
      + '<title>' + esc('Προσφορά — ' + (st.clientName || 'PharmacyOne')) + '</title>'
      + '<style>' + (r.css || '') + '</style></head><body>' + r.html + '</body></html>';
    /* Δεκατρείς σελίδες Α4 δεν ξετυλίγονται μέσα σε πίνακα — το πλαίσιο γίνεται
       αναγνώστης εγγράφου με δική του κύλιση, ώστε τα κουμπιά να μένουν ορατά. */
    fr.style.height = Math.round(window.innerHeight * (window.innerWidth < 700 ? 0.66 : 0.6)) + 'px';
  }

  function printDoc() {
    const fr = body.querySelector('.ph-frame');
    if (!fr || !fr.contentWindow) {
      toast('Άνοιξε πρώτα την καρτέλα «Έγγραφο»', true);
      st.tab = 'doc'; shell(); paintNumbers(); return;
    }
    fr.contentWindow.focus();
    fr.contentWindow.print();
  }

  async function save() {
    if (!st.clientName.trim()) { toast('Δώσε επωνυμία πελάτη', true); $('#phWho', body).focus(); return; }
    const btn = $('#phSave', body); btn.disabled = true;
    const r = await api('pharmacy_save', {offer: st.offer, client: st.client,
      clientName: st.clientName, config: st.cfg})
      .catch(e => ({ok: false, error: e && e.message}));
    btn.disabled = false;
    if (!r.ok) { toast(r.error || 'Δεν αποθηκεύτηκε', true); return; }
    st.offer = r.offer;
    btn.textContent = 'Ενημέρωση προσφοράς';
    toast(offerId ? 'Η προσφορά ενημερώθηκε' : 'Δημιουργήθηκε προσφορά ' + phMoney(r.amount));
    closeDrawer();
    if (S.view === 'offers') { R.offers(); } else { go('offers'); }
  }

  shell();
  await recalc();
}

window.openPharmacy = openPharmacy;
window.CNP.openPharmacy = openPharmacy;
