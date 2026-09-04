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
        protocol: 'CLD-' + new Date().getFullYear() + '-', date: new Date().toISOString().slice(0, 10)}},
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

  /* ── σκελετός ── */
  const shell = () => {
    body.innerHTML = `
    <div class="ph-top">
      <div class="ph-who">
        <input class="inp" id="phWho" placeholder="Επωνυμία πελάτη…" value="${esc(st.clientName)}" autocomplete="off">
        <input class="inp ph-afm" id="phAfm" placeholder="ΑΦΜ" maxlength="9" inputmode="numeric"
          value="${esc(st.cfg.o.afm || '')}">
        <button class="btn btn-o btn-sm" id="phAade" title="Άντληση στοιχείων από το μητρώο ΑΑΔΕ">Άντληση ΑΑΔΕ</button>
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
      <button class="btn btn-p" id="phSave">${st.offer ? 'Ενημέρωση προσφοράς' : 'Δημιουργία προσφοράς'}</button>
    </div>`;
    $$('[data-tab]', body).forEach(b => b.onclick = () => { st.tab = b.dataset.tab; shell(); paintNumbers(); });
    wireWho();
    $('#phPrint', body).onclick = printDoc;
    $('#phSave', body).onclick = save;
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
    $('#phAade', body).onclick = async () => {
      const afm = (st.cfg.o.afm || '').replace(/\D/g, '');
      const stEl = $('#phAfmSt', body);
      if (afm.length !== 9) { stEl.textContent = 'Δώσε 9ψήφιο ΑΦΜ'; return; }
      stEl.textContent = 'Αναζήτηση στο μητρώο…';
      /* Το ίδιο endpoint που τροφοδοτεί και τη φόρμα εγγραφής — ένα σημείο επαφής με την ΑΑΔΕ. */
      const r = await fetch('afm.php?afm=' + afm, {credentials: 'same-origin'})
        .then(x => x.json()).catch(() => null);
      if (!r || !r.ok) { stEl.textContent = 'ΑΑΔΕ: ' + ((r && r.error) || 'χωρίς αποτέλεσμα'); return; }
      const dd = r.data || {};
      if (dd.name) { st.clientName = dd.name; st.cfg.o.client = dd.name; $('#phWho', body).value = dd.name; }
      if (dd.doy) { st.cfg.o.doy = dd.doy; }
      const addr = [dd.street, dd.postcode, dd.city].filter(Boolean).join(', ');
      if (addr) { st.cfg.o.address = addr; }
      if (dd.kad) { st.cfg.o.activity = dd.kad; }
      stEl.textContent = (dd.active === false ? '⚠ ανενεργό ΑΦΜ — ' : '✓ ') + (dd.name || '');
      if (st.tab === 'doc') { pane(); }
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
      el.innerHTML = `<div class="ph-mods">${defs.groups.map(g => `<div class="card"><div class="card-b">
        <label class="lbl" style="margin:0 0 8px">${esc(g.title)}</label>
        ${g.items.map(it => `<label class="ph-mod${st.cfg.yn[it.cell] ? ' on' : ''}">
          <input type="checkbox" data-yn="${it.cell}" ${st.cfg.yn[it.cell] ? 'checked' : ''}>
          <span>${esc(it.lab)}</span></label>`).join('')}
      </div></div>`).join('')}</div>`;
      $$('[data-yn]', el).forEach(ch => ch.onchange = () => {
        st.cfg.yn[ch.dataset.yn] = ch.checked ? 1 : 0;
        ch.closest('.ph-mod').classList.toggle('on', ch.checked);
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
    ['attn', 'Υπόψη (ονοματεπώνυμο)', 'text'], ['cphone', 'Τηλέφωνο πελάτη', 'text'],
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
    fr.srcdoc = '<!doctype html><html lang="el"><head><meta charset="utf-8">'
      + '<title>' + esc('Προσφορά — ' + (st.clientName || 'PharmacyOne')) + '</title>'
      + '<style>' + PH_DOC_CSS + '</style></head><body>' + r.html + '</body></html>';
    fr.onload = () => {
      const d2 = fr.contentDocument;
      if (!d2 || !d2.body) { return; }
      /* Σε υπολογιστή το έγγραφο ξετυλίγεται ολόκληρο (μία κύλιση)· σε κινητό κόβεται
         σε παράθυρο, αλλιώς επτά σελίδες Α4 θάβουν τα κουμπιά. */
      const full = d2.body.scrollHeight + 24;
      fr.style.height = (window.innerWidth < 700 ? Math.min(full, Math.round(window.innerHeight * 0.7)) : full) + 'px';
    };
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

/* Το στυλ του εγγράφου — μπαίνει και στην προεπισκόπηση και στο παράθυρο εκτύπωσης,
   ώστε αυτό που βλέπεις να είναι αυτό που τυπώνεται. */
const PH_DOC_CSS = `
body{margin:0;background:#eef1f5;color:#1b2430;font:13px/1.62 "Segoe UI",system-ui,sans-serif;padding:16px}
@media print{body{background:#fff;padding:0}}
.page{background:#fff;color:#1b2430;border:1px solid #d3dae1;border-radius:4px;
  padding:38px 44px 44px;margin:0 auto 18px;max-width:820px}
.page h3,.page h4,.page h5{color:#14507d;margin:0}
.page h3{font-size:20px;font-weight:700;margin:0 0 14px;padding-bottom:7px;border-bottom:2px solid #14507d}
.page h4{font-size:15px;font-weight:700;margin:22px 0 9px}
.page h5{font-size:12.5px;font-weight:700;margin:18px 0 7px;text-transform:uppercase;letter-spacing:.05em}
.page p{margin:0 0 10px;max-width:70ch}
.page ul{margin:0 0 10px;padding-left:20px}
.page ul.ticks{list-style:none;padding-left:2px}
.page ul.ticks li::before{content:"✓";color:#00a94f;font-weight:700;margin-right:8px}
.cover{text-align:center;padding:62px 44px 54px}
.cover .kicker{font-size:11px;font-weight:700;letter-spacing:.18em;text-transform:uppercase;color:#4a5763}
.cover .ctitle{font-size:26px;font-weight:800;line-height:1.25;margin:16px auto 6px;max-width:20ch;color:#14507d}
.cover .cprod{font-size:19px;font-weight:600;margin-bottom:34px}
.cover .cclient{font-size:22px;font-weight:700;padding:18px 20px;border-top:2px solid #14507d;
  border-bottom:2px solid #14507d;max-width:34ch;margin:0 auto 36px}
.cover .cmeta{font-size:13px;color:#4a5763;line-height:1.9}
.letterhead{display:flex;justify-content:space-between;gap:30px;flex-wrap:wrap;margin-bottom:26px;font-size:12.5px}
.letterhead div{line-height:1.75}
.letterhead .lab{color:#4a5763}
.sig{margin-top:26px;font-size:13px}
.sig b{display:block;margin-top:26px;font-weight:600}
table.doc{border-collapse:collapse;width:100%;font-size:12.5px;margin:10px 0 4px}
table.doc th,table.doc td{border:1px solid #d3dae1;padding:7px 10px;vertical-align:top}
table.doc thead th{background:#eef4f9;color:#14507d;font-size:11px;font-weight:700;
  letter-spacing:.05em;text-transform:uppercase;text-align:left}
table.doc td.n,table.doc th.n{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap;width:1%}
table.doc tr.sum td{background:#e6f6ed;font-weight:700;border-top:2px solid #00a94f}
table.doc tr.grand td{background:#14507d;color:#fff;font-weight:800;font-size:13.5px;border-color:#14507d}
table.doc .qty{display:block;font-size:11px;color:#4a5763;margin-top:2px}
table.doc tr.inc td{color:#4a5763}
.docnote{font-size:11.5px;color:#4a5763;margin-top:12px}
.terms{margin-top:16px;font-size:12.5px}
.terms b{color:#14507d}
.banks{display:grid;gap:14px;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));margin-top:12px}
.bank{border:1px solid #d3dae1;border-radius:4px;padding:11px 13px;font-size:12px;line-height:1.7}
.bank b{display:block;color:#14507d;font-size:12.5px;margin-bottom:3px}
.acc{display:grid;gap:10px 26px;grid-template-columns:1fr 1fr;margin-top:16px;font-size:12.5px}
.acc .row{border-bottom:1px solid #d3dae1;padding:9px 0 4px;color:#4a5763}
.signbox{margin-top:40px;border:1px solid #d3dae1;border-radius:4px;height:120px;position:relative}
.signbox span{position:absolute;bottom:8px;left:0;right:0;text-align:center;font-size:11.5px;color:#4a5763}
.remarks{margin-top:20px;border:1px solid #d3dae1;border-radius:4px;min-height:96px;padding:9px 11px}
.remarks span{font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#4a5763}
@page{size:A4;margin:14mm}
@media print{
  body{background:#fff}
  .page{border:none;border-radius:0;padding:0 0 8mm;margin:0;break-after:page;page-break-after:always;font-size:11.5px}
  .page:last-child{break-after:auto;page-break-after:auto}
  .cover{padding:20mm 0 0}
  table.doc tr{break-inside:avoid}
}`;

window.openPharmacy = openPharmacy;
window.CNP.openPharmacy = openPharmacy;
