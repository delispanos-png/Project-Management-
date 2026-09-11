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
      q: Object.assign({}, defs.defaults.q || {}), rd: Object.assign({}, defs.defaults.rd || {}),
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
        ${[['setup', 'Παράμετροι'], ['modules', 'Modules'], ['rates', 'Τιμοκατάλογος'],
           ['pay', 'Πληρωμή'], ['doc', 'Έγγραφο']]
          .map(([k, l]) => `<button data-tab="${k}" class="${st.tab === k ? 'on' : ''}">${l}</button>`).join('')}
      </div>
    </div>
    <div id="phCards" class="ph-cards"${st.tab === 'doc' ? ' hidden' : ''}></div>
    <div id="phPane"></div>
    <div class="ph-foot">
      <div class="mut ph-sum" id="phSum"></div>
      <button class="btn btn-o" id="phPrint">${I.doc} Εκτύπωση</button>
      <button class="btn btn-o" id="phEmail" title="Αποστολή της προσφοράς στον πελάτη ως PDF">✉ Αποστολή</button>
      ${st.offer ? '<button class="btn btn-o" id="phComments" title="Ερωτήσεις & σχόλια πελάτη">💬 Ερωτήσεις</button>' : ''}
      ${st.offer && cnpCan('clients.offers.delete') ? '<button class="btn btn-danger" id="phDel">🗑 Διαγραφή</button>' : ''}
      <button class="btn btn-p" id="phSave">${st.offer ? 'Ενημέρωση προσφοράς' : 'Δημιουργία προσφοράς'}</button>
    </div>`;
    $$('[data-tab]', body).forEach(b => b.onclick = () => { st.tab = b.dataset.tab; shell(); paintNumbers(); });
    wireWho();
    $('#phPrint', body).onclick = printDoc;
    { const eb = $('#phEmail', body); if (eb) eb.onclick = openEmailDialog; }
    { const cb = $('#phComments', body); if (cb && window.CNP.openOfferComments) cb.onclick = () => window.CNP.openOfferComments(st.offer); }
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

  /* ── Αποστολή προσφοράς στον πελάτη (PDF συνημμένο) ── */
  function openEmailDialog() {
    if (!st.clientName.trim()) { toast('Δώσε πρώτα επωνυμία πελάτη', true); $('#phWho', body).focus(); return; }
    const o = st.cfg.o || {};
    const proto = o.protocol || '';
    const defSubj = 'Οικονομική προσφορά CloudOn' + (proto ? ' — ' + proto : '');
    const ov = document.createElement('div'); ov.className = 'ovl show';
    ov.style.zIndex = '60';
    ov.innerHTML = `<div class="pal-box ai-box" onclick="event.stopPropagation()">
      <div class="ai-h"><b>✉ Αποστολή προσφοράς</b>
        <span class="mut" style="font-size:11.5px">η προσφορά φεύγει ως PDF συνημμένο</span></div>
      <div class="ai-b">
        <label class="lbl">Email παραλήπτη</label>
        <input class="inp" id="emTo" type="email" value="${esc(o.cemail || '')}" placeholder="π.χ. pharmacy@example.gr">
        <label class="lbl" style="margin-top:9px">Θέμα</label>
        <input class="inp" id="emSubj" value="${esc(defSubj)}">
        <label class="lbl" style="margin-top:9px">Μήνυμα <span class="mut" style="font-weight:400">— προαιρετικό· αν το αφήσεις κενό μπαίνει τυπικό συνοδευτικό</span></label>
        <textarea class="inp" id="emMsg" rows="4" placeholder="Αγαπητοί συνεργάτες, σας αποστέλλουμε συνημμένα την οικονομική μας προσφορά…"></textarea>
        <div id="emErr" class="ai-msg" hidden></div>
      </div>
      <div class="ai-f"><button class="btn btn-o" id="emX">Άκυρο</button>
        <button class="btn btn-p" id="emOk">✉ Αποστολή</button></div></div>`;
    document.body.appendChild(ov);
    const q = sel => ov.querySelector(sel);
    const onEsc = e => { if (e.key === 'Escape') { e.stopPropagation(); close(); } };
    const close = () => { ov.remove(); document.removeEventListener('keydown', onEsc, true); };
    document.addEventListener('keydown', onEsc, true);
    ov.onclick = close; q('#emX').onclick = close;
    setTimeout(() => q('#emTo').focus(), 60);
    q('#emOk').onclick = async () => {
      const to = q('#emTo').value.trim();
      if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(to)) {
        const m = q('#emErr'); m.hidden = false; m.textContent = 'Δώσε έγκυρο email παραλήπτη'; q('#emTo').focus(); return;
      }
      const btn = q('#emOk'); btn.disabled = true; btn.textContent = '✉ Δημιουργία PDF & αποστολή…';
      const r = await api('pharmacy_email', {offer: st.offer || 0, config: st.cfg,
        to, subject: q('#emSubj').value.trim(), message: q('#emMsg').value.trim()})
        .catch(e => ({err: e && e.message}));
      if (!r || r.err) {
        btn.disabled = false; btn.textContent = '✉ Αποστολή';
        const m = q('#emErr'); m.hidden = false; m.textContent = (r && r.err) || 'Δεν στάλθηκε — δοκίμασε ξανά'; return;
      }
      close();
      toast('✉ Η προσφορά στάλθηκε στον πελάτη (' + to + ')');
      if (r.accountCreated) {
        toast(r.credentialsSent
          ? '🔑 Δημιουργήθηκε λογαριασμός MyCloudOn — οι κωδικοί στάλθηκαν στον πελάτη'
          : '⚠ Δημιουργήθηκε λογαριασμός, αλλά οι κωδικοί ΔΕΝ στάλθηκαν — έλεγξέ το', !r.credentialsSent);
      }
      /* Αν ήταν αποθηκευμένη, το backend τη μετακίνησε σε «Εστάλη» — ανανέωσε τη λίστα. */
      if (st.offer && S.view === 'offers') { R.offers(); }
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
    /* Οι ώρες τηλεφωνικής υποστήριξης δείχνουν αμέσως τι κοστίζουν — αλλιώς έπρεπε να
       κατέβεις στο έντυπο για να δεις το αποτέλεσμα μιας αλλαγής. */
    const sup = $('[data-hint="M6"]', body);
    if (sup && st.calc.buckets) {
      /* Ταίριασμα στην ΕΤΙΚΕΤΑ, όχι στο k: το k=29 υπάρχει και στις εφάπαξ γραμμές
         (Παραμετροποίηση) — η αρίθμηση εφάπαξ/ετήσιων είναι ανεξάρτητη. */
      let v = null;
      st.calc.buckets.forEach(b => (b.rows || []).forEach(r => {
        if (/Τηλεφωνικ/i.test(r.lab || '')) { v = +r.amount; }
      }));
      if (v !== null) {
        const h = +st.cfg.p.M6 || 0;
        const rate = h ? v / h : (+(st.cfg.r && st.cfg.r.S37) || 0);
        sup.textContent = `${h} ώρες × ${phMoney(rate)} / ώρα → ${phMoney(v)} / έτος`;
      }
    }
    if (st.tab === 'doc') { docPane(); }
    if (st.tab === 'pay') { planSum(); }
  }

  /* Μια περιοχή ανά τίτλο, με τη σειρά που έρχονται τα πεδία. */
  const byGroup = (list, key, dflt) => {
    const out = [];
    list.forEach(d => {
      const t = (typeof key === 'function' ? key(d) : d[key]) || dflt;
      let g = out.find(x => x.t === t);
      if (!g) { g = {t, items: []}; out.push(g); }
      g.items.push(d);
    });
    return out;
  };

  /* ── τα φύλλα ── */
  function pane() {
    const el = $('#phPane', body);
    if (st.tab === 'setup') {
      const fld = d => {
        const v = st.cfg.p[d.cell];
        const val = d.type === 'pct' ? Math.round(v * 1000) / 10 : v;
        return `<div class="field"><label>${esc(d.lab)}</label>
          <input class="inp" type="number" min="0" step="1"
            data-p="${d.cell}" data-k="${d.type}" value="${val}">${d.type === 'pct' ? '<span class="ph-pct">%</span>' : ''}
          ${d.hint ? `<span class="ph-hint" data-hint="${d.cell}">${esc(d.hint)}</span>` : ''}</div>`;
      };
      el.innerHTML = `<div class="card"><div class="card-b">${
        byGroup(defs.params, 'grp', 'Παράμετροι εγκατάστασης').map(g =>
          `<label class="lbl ph-sec">${esc(g.t)}</label>
           <div class="ph-fields">${g.items.map(fld).join('')}</div>`).join('')
      }</div></div>`;
      $$('[data-p]', el).forEach(inp => inp.oninput = () => {
        const v = parseFloat(inp.value); const n = isFinite(v) ? v : 0;
        const cell = inp.dataset.p;
        st.cfg.p[cell] = inp.dataset.k === 'pct' ? n / 100 : n;
        /* Οι ώρες τηλεφωνικής ΔΕΝ ακολουθούν πια τους χρήστες — τις ορίζει
           αποκλειστικά ο χρήστης (απόφαση 11/9/2026). */
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
          <input class="ph-mini ph-modq" type="number" min="0" step="1" data-q="${it.cell}"
            placeholder="τεμ" title="Τεμάχια — 0 κλείνει την υπηρεσία"
            value="${st.cfg.yn[it.cell] ? (((st.cfg.q && +st.cfg.q[it.cell]) > 0) ? st.cfg.q[it.cell] : 1) : ''}">
          <span class="switch"><input type="checkbox" data-yn="${it.cell}" data-grp="${esc(g.title)}"
            ${st.cfg.yn[it.cell] ? 'checked' : ''}><span></span></span></label>`).join('')}
      </div></div>`; }).join('')}</div>`;
      /* Τα τεμάχια δίπλα στον διακόπτη: η στήλη «Τεμ» του φύλλου. Κενό = ό,τι λέει ο
         τύπος (π.χ. ανά εταιρεία), ώστε οι παλιές προσφορές να μένουν ακριβώς ίδιες. */
      $$('[data-q]', el).forEach(inp => {
        inp.onclick = e => e.stopPropagation();
        inp.oninput = () => {
          const v = parseFloat(inp.value); const n = isFinite(v) && v > 0 ? v : 0;
          const cell = inp.dataset.q;
          if (!st.cfg.q) { st.cfg.q = {}; }
          st.cfg.q[cell] = n;
          /* Η ποσότητα είναι ο διακόπτης: 0 τεμάχια σημαίνει «δεν το δίνουμε». */
          const want = n > 0 ? 1 : 0;
          if (st.cfg.yn[cell] !== want && inp.value !== '') {
            st.cfg.yn[cell] = want;
            const ch = $(`[data-yn="${cell}"]`, el);
            if (ch) {
              ch.checked = !!want;
              ch.closest('.ph-mod').classList.toggle('on', !!want);
              const box = ch.closest('.card-b'); const cnt = $('.ph-gn', box);
              if (cnt) { cnt.textContent = $$('[data-yn]:checked', box).length + '/' + $$('[data-yn]', box).length; }
            }
          }
          touch();
        };
      });
      $$('[data-yn]', el).forEach(ch => ch.onchange = () => {
        st.cfg.yn[ch.dataset.yn] = ch.checked ? 1 : 0;
        ch.closest('.ph-mod').classList.toggle('on', ch.checked);
        /* Ανοίγεις μια υπηρεσία → μία άδεια. Θες κι άλλες, ανεβάζεις τον αριθμό. */
        if (!st.cfg.q) { st.cfg.q = {}; }
        const qi = $(`[data-q="${ch.dataset.yn}"]`, el);
        if (ch.checked) {
          if (!(+st.cfg.q[ch.dataset.yn] > 0)) { st.cfg.q[ch.dataset.yn] = 1; if (qi) { qi.value = 1; } }
        } else {
          st.cfg.q[ch.dataset.yn] = 0; if (qi) { qi.value = ''; }
        }
        const box = ch.closest('.card-b');
        const cnt = $('.ph-gn', box);
        if (cnt) { cnt.textContent = $$('[data-yn]:checked', box).length + '/' + $$('[data-yn]', box).length; }
        touch();
      });
    } else if (st.tab === 'rates') {
      el.innerHTML = `<div class="card"><div class="card-b">
        <label class="lbl" style="margin:0 0 4px">Τιμοκατάλογος</label>
        <div class="mut" style="font-size:11.5px;margin-bottom:11px;line-height:1.5">
          Οι αλλαγές ισχύουν μόνο για αυτή την προσφορά — ο γενικός τιμοκατάλογος δεν πειράζεται.<br>
          <b>Ετήσια αναπροσαρμογή</b>: το ποσοστό της τιμής που ξαναχρεώνεται κάθε χρόνο ως υποστήριξη
          (Courier 550 € → 30% = 165 € / έτος). <b>Έκπτωση</b>: μειώνει την ίδια την τιμή.
        </div>
        <div class="ph-rates r4">
          <div class="ph-rh">Περιγραφή</div><div class="ph-rh n">Τιμή €</div>
          <div class="ph-rh n">Ετήσια αναπροσαρμογή %</div><div class="ph-rh n">Έκπτωση %</div>
          ${defs.rates.map(d => `
            <div class="ph-rl">${esc(d.lab)}</div>
            <div class="n"><input class="ph-mini" type="number" min="0" step="1"
              data-r="${d.cell}" data-k="num" value="${st.cfg.r[d.cell]}"></div>
            <div class="n">${d.adj
              ? `<input class="ph-mini" type="number" min="0" max="100" step="1"
                  data-r="${d.adj}" data-k="pct" title="Ετήσια αναπροσαρμογή — % της τιμής, κάθε χρόνο"
                  value="${Math.round(((st.cfg.r[d.adj]) || 0) * 1000) / 10}">`
              : '<span class="mut">—</span>'}</div>
            <div class="n">${d.noDisc
              ? `<span class="mut" title="Η γραμμή αυτή χρεώνεται πάντα στην τιμή της — καμία εκπτωτική πολιτική δεν την επηρεάζει">—</span>`
              : `<input class="ph-mini" type="number" min="0" max="99" step="1"
                  data-rd="${d.cell}" title="Έκπτωση % επί της τιμής"
                  value="${Math.round(((st.cfg.rd && st.cfg.rd[d.cell]) || 0) * 1000) / 10}">`}</div>`).join('')}
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
        ${defs.canEditBase ? `<div style="margin-top:16px;padding-top:14px;border-top:1px solid var(--line);display:flex;gap:9px;align-items:center;flex-wrap:wrap">
          <button class="btn btn-p btn-sm" id="phBaseSave">💾 Αποθήκευση ως βασικός τιμοκατάλογος</button>
          <button class="btn btn-o btn-sm" id="phBaseReset">↺ Εργοστασιακές τιμές</button>
          <span class="mut" style="font-size:11px">Ορίζει τις προεπιλεγμένες τιμές για <b>όλες τις νέες</b> προσφορές (οι ήδη αποθηκευμένες δεν αλλάζουν).</span>
        </div>` : ''}
      </div></div>`;
      $$('[data-r]', el).forEach(inp => inp.oninput = () => {
        const v = parseFloat(inp.value); const n = isFinite(v) ? v : 0;
        st.cfg.r[inp.dataset.r] = inp.dataset.k === 'pct' ? n / 100 : n;
        touch();
      });
      $$('[data-rd]', el).forEach(inp => inp.oninput = () => {
        const v = parseFloat(inp.value); let n = isFinite(v) ? v : 0;
        n = Math.max(0, Math.min(99, n));
        if (!st.cfg.rd) { st.cfg.rd = {}; }
        st.cfg.rd[inp.dataset.rd] = n / 100;
        touch();
      });
      $$('[data-ed]', el).forEach(inp => inp.oninput = () => {
        const v = parseFloat(inp.value);
        st.cfg.ed[+inp.dataset.ed][inp.dataset.f] = isFinite(v) ? v : 0;
        touch();
      });
      /* Ο τιμοκατάλογος είναι μακρύς και οι τιμές είναι δεξιά, το είδος αριστερά: όταν
         μπαίνεις σε ένα κουτί, φωτίζεται όλη η γραμμή για να ξέρεις τι αλλάζεις. */
      const rowOf = inp => {
        let c = inp.parentElement;
        while (c && !c.classList.contains('ph-rl')) { c = c.previousElementSibling; }
        const cells = [];
        while (c) {
          cells.push(c);
          c = c.nextElementSibling;
          if (!c || c.classList.contains('ph-rl') || c.classList.contains('ph-rh')) { break; }
        }
        return cells;
      };
      $$('.ph-rates input', el).forEach(inp => {
        inp.onfocus = () => rowOf(inp).forEach(c => c.classList.add('hot'));
        inp.onblur = () => $$('.ph-rates .hot', el).forEach(c => c.classList.remove('hot'));
      });
      /* ── Βασικός (γενικός) τιμοκατάλογος — μόνο διαχειριστής ── */
      { const bs = $('#phBaseSave', el); if (bs) bs.onclick = async () => {
        if (!(await window.CNP.cnpConfirm('Να γίνουν οι ΤΡΕΧΟΥΣΕΣ τιμές ΚΑΙ οι εκπτώσεις (CloudOn / υπηρεσιών / Soft1) ο βασικός τιμοκατάλογος για ΟΛΕΣ τις νέες προσφορές;\n\nΟι ήδη αποθηκευμένες προσφορές ΔΕΝ αλλάζουν.',
          {ok: '💾 Αποθήκευση', cancel: 'Άκυρο'}))) { return; }
        bs.disabled = true;
        /* Η πολιτική εκπτώσεων ταξιδεύει μαζί με τις τιμές: αλλιώς κάθε νέα προσφορά
           ξεκινούσε με άλλα ποσοστά από αυτά που μόλις όρισε ο διαχειριστής. */
        const disc = {L4: st.cfg.p.L4, J8: st.cfg.p.J8, K8: st.cfg.p.K8};
        const r = await api('pharmacy_catalog_save',
          {rates: st.cfg.r, editions: st.cfg.ed, disc, rd: st.cfg.rd || {}}).catch(e => ({err: e && e.message}));
        bs.disabled = false;
        if (r && r.err) { toast(r.err, true); return; }
        PH = null;                       // η επόμενη προσφορά ξεκινά με τις νέες προεπιλογές
        toast('✅ Ο βασικός τιμοκατάλογος αποθηκεύτηκε');
      }; }
      { const br = $('#phBaseReset', el); if (br) br.onclick = async () => {
        if (!(await window.CNP.cnpConfirm('Επαναφορά στις εργοστασιακές τιμές;\n\nΜηδενίζονται και οι εκπτώσεις: ανά γραμμή τιμοκαταλόγου, και τα τρία ποσοστά (αδειών CloudOn, υπηρεσιών, αδειών Soft1) γυρνούν στα εργοστασιακά.', {ok: '↺ Επαναφορά', cancel: 'Άκυρο', danger: true}))) { return; }
        const r = await api('pharmacy_catalog_reset', {}).catch(e => ({err: e && e.message}));
        if (r && r.err) { toast(r.err, true); return; }
        PH = null;
        const nd = await phDefs();       // φρέσκα εργοστασιακά defs
        Object.assign(defs, nd);
        st.cfg.r = Object.assign({}, nd.defaults.r);
        st.cfg.ed = nd.editions.map(e => ({price: e.price, extraUser: e.extraUser}));
        st.cfg.rd = Object.assign({}, nd.defaults.rd || {});   // εκπτώσεις ανά γραμμή
        ['L4', 'J8', 'K8'].forEach(k => { st.cfg.p[k] = nd.defaults.p[k]; });
        pane(); await recalc();
        toast('↺ Επαναφορά εργοστασιακών τιμών & εκπτώσεων');
      }; }
    } else if (st.tab === 'pay') {
      payPane();
    } else {
      docPane();
    }
  }

  /* [κλειδί, ετικέτα, τύπος, επιλογές|null, περιοχή] — οι περιοχές είναι ο λόγος που ο
     διακανονισμός πληρωμής βρίσκεται με μια ματιά και δεν χάνεται μέσα σε 18 κουτιά. */
  const F_CLIENT = 'Στοιχεία πελάτη', F_DOC = 'Στοιχεία εγγράφου', F_PAY = 'Οικονομικοί όροι & πληρωμή';
  const DOC_FIELDS = [
    ['attn', 'Υπόψη (ονοματεπώνυμο)', 'text', null, F_CLIENT],
    ['cphone', 'Τηλέφωνο πελάτη', 'text', null, F_CLIENT],
    ['cemail', 'Email πελάτη', 'text', null, F_CLIENT],
    ['address', 'Διεύθυνση έδρας', 'text', null, F_CLIENT],
    ['doy', 'Δ.Ο.Υ.', 'text', null, F_CLIENT],
    ['city', 'Πόλη', 'text', null, F_CLIENT],

    ['protocol', 'Αριθμός πρωτοκόλλου', 'text', null, F_DOC],
    ['date', 'Ημερομηνία', 'date', null, F_DOC],
    ['seller', 'Υπογράφων', 'text', null, F_DOC],
    ['acceptAttn', 'Υπόψη — έντυπο αποδοχής', 'text', null, F_DOC],
    ['greeting', 'Χαιρετισμός επιστολής', 'text', null, F_DOC],
    ['validDays', 'Ισχύς (ημέρες)', 'num', null, F_DOC],

    ['discount', 'Επιπλέον έκπτωση (€)', 'num', null, F_PAY],
    ['vat', 'ΦΠΑ %', 'num', null, F_PAY],
    ['payMethod', 'Τρόπος εξόφλησης', 'sel',
      ['Τραπεζική κατάθεση', 'Μετρητά', 'Κάρτα (POS / e-banking)', 'Επιταγή'], F_PAY],
    ['subCycle', 'Χρέωση ετήσιας συνδρομής', 'sel',
      ['Ετησίως προκαταβολικά', 'Ανά εξάμηνο', 'Μηνιαία'], F_PAY],
  ];

  /* ── Διακανονισμός πληρωμής ──
     Κάθε συμφωνία έχει άλλο ρυθμό: 50/50, προκαταβολή και τρεις δόσεις, ένα σταθερό ποσό
     στην παράδοση. Ο πίνακας δέχεται ό,τι κλείσει ο πωλητής και το γράφει στο έντυπο. */
  const planRow = (r, x) => `<div class="ph-plan-r" data-x="${x}">
    <select class="ph-mini" data-pl="t">
      <option value="pct"${r.t !== 'eur' ? ' selected' : ''}>%</option>
      <option value="eur"${r.t === 'eur' ? ' selected' : ''}>€</option>
    </select>
    <input class="ph-mini" type="number" min="0" step="any" data-pl="v" value="${+r.v || 0}">
    <input class="inp" type="text" data-pl="w" value="${esc(r.w || '')}"
      placeholder="π.χ. 60 ημέρες μετά την παράδοση">
    <button type="button" class="ph-plan-x" title="Διαγραφή δόσης">✕</button>
  </div>`;

  const planBlock = () => `<div class="ph-plan" id="phPlan">
    <div class="ph-plan-h"><span>Δόσεις πληρωμής</span><span class="ph-plan-sum" id="phPlanSum"></span></div>
    <div id="phPlanRows">${(st.cfg.o.plan || []).map(planRow).join('')}</div>
    <button type="button" class="btn btn-o btn-sm" id="phPlanAdd">+ Δόση</button>
  </div>`;

  /* Τι αθροίζουν οι δόσεις — πράσινο όταν καλύπτουν ακριβώς την αξία της προσφοράς. */
  function planSum() {
    const el = $('#phPlanSum', body);
    if (!el) { return; }
    const total = (st.calc && st.calc.amount) || 0;
    let sum = 0;
    (st.cfg.o.plan || []).forEach(r => { sum += r.t === 'eur' ? (+r.v || 0) : total * (+r.v || 0) / 100; });
    const rest = total - sum;
    el.textContent = phMoney(sum) + (Math.abs(rest) > 0.01 ? ' · υπόλοιπο ' + phMoney(rest) : ' · καλύπτει το σύνολο');
    el.classList.toggle('ok', Math.abs(rest) <= 0.01);
  }

  function wirePlan(el) {
    const read = () => {
      st.cfg.o.plan = $$('.ph-plan-r', el).map(r => ({
        t: $('[data-pl="t"]', r).value === 'eur' ? 'eur' : 'pct',
        v: parseFloat($('[data-pl="v"]', r).value) || 0,
        w: $('[data-pl="w"]', r).value,
      }));
      planSum();
    };
    const redraw = () => {
      const box = $('#phPlanRows', el);
      if (box) { box.innerHTML = (st.cfg.o.plan || []).map(planRow).join(''); wirePlan(el); }
      planSum();
      clearTimeout(phTimer);
      phTimer = setTimeout(async () => { await recalc(); renderDoc(); }, 320);
    };
    $$('.ph-plan-r [data-pl]', el).forEach(inp => {
      inp.oninput = () => { read(); clearTimeout(phTimer);
        phTimer = setTimeout(async () => { await recalc(); renderDoc(); }, 400); };
      inp.onchange = inp.oninput;
    });
    $$('.ph-plan-x', el).forEach(b => b.onclick = () => {
      const x = +b.closest('.ph-plan-r').dataset.x;
      st.cfg.o.plan = (st.cfg.o.plan || []).filter((r, i) => i !== x);
      redraw();
    });
    const add = $('#phPlanAdd', el);
    if (add) { add.onclick = () => {
      st.cfg.o.plan = (st.cfg.o.plan || []).concat([{t: 'pct', v: 0, w: ''}]);
      redraw();
    }; }
  }

  const dfld = ([k, lab, t, opts]) => {
    const v = st.cfg.o[k] === undefined ? '' : st.cfg.o[k];
    const ctl = t === 'sel'
      ? `<select class="inp" data-o="${k}" data-k="sel">${opts.map(o =>
          `<option${String(o) === String(v) ? ' selected' : ''}>${esc(o)}</option>`).join('')}</select>`
      : `<input class="inp" type="${t === 'date' ? 'date' : (t === 'num' ? 'number' : 'text')}"
          data-o="${k}" data-k="${t}" value="${esc(v)}">`;
    return `<div class="field"><label>${esc(lab)}</label>${ctl}</div>`;
  };

  /* Κάθε πεδίο του εγγράφου ξαναγράφει τη ρύθμιση και ανανεώνει την προεπισκόπηση. */
  function wireDocFields(el) {
    $$('[data-o]', el).forEach(inp => {
      const upd = () => {
        const k = inp.dataset.k;
        st.cfg.o[inp.dataset.o] = k === 'num' ? (parseFloat(inp.value) || 0) : inp.value;
        clearTimeout(phTimer);
        phTimer = setTimeout(async () => { await recalc(); renderDoc(); }, 320);
      };
      inp.oninput = upd;
      inp.onchange = upd;          // τα select δεν στέλνουν πάντα input
    });
  }

  /* ── Η καρτέλα «Πληρωμή»: όροι και δόσεις μαζί, εκεί που τα ψάχνεις ── */
  function payPane() {
    const el = $('#phPane', body);
    el.innerHTML = `<div class="card"><div class="card-b">
      <label class="lbl ph-sec">Οικονομικοί όροι</label>
      <div class="ph-fields">${DOC_FIELDS.filter(d => d[4] === F_PAY).map(dfld).join('')}</div>
      ${planBlock()}
    </div></div>`;
    wireDocFields(el);
    wirePlan(el);
    planSum();
  }

  async function docPane() {
    const el = $('#phPane', body);
    if (!el.querySelector('#phDocFields')) {
      el.innerHTML = `<details class="card ph-docf">
        <summary>Στοιχεία εγγράφου &amp; οικονομικοί όροι — πάτα για επεξεργασία</summary>
        <div class="card-b" id="phDocFields">${
        byGroup(DOC_FIELDS.filter(d => d[4] !== F_PAY), d => d[4], 'Στοιχεία εγγράφου').map(g =>
          `<label class="lbl ph-sec">${esc(g.t)}</label>
           <div class="ph-fields">${g.items.map(dfld).join('')}</div>`).join('')
      }</div></details>
        <div class="ph-doc" id="phDoc"><div class="skel" style="height:300px"></div></div>`;
      { const dt = el.querySelector('.ph-docf');
        if (dt) { dt.ontoggle = () => renderDoc(); } }
      wireDocFields(el);
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
    /* Δεκαοκτώ σελίδες Α4 δεν ξετυλίγονται μέσα σε πίνακα — το πλαίσιο γίνεται
       αναγνώστης εγγράφου με δική του κύλιση, ώστε τα κουμπιά να μένουν ορατά. Με τα
       πεδία κλειστά παίρνει σχεδόν όλο το ύψος: εκεί διαβάζεις την προσφορά. */
    const openF = !!body.querySelector('.ph-docf[open]');
    const f = window.innerWidth < 700 ? (openF ? 0.6 : 0.72) : (openF ? 0.58 : 0.82);
    fr.style.height = Math.round(window.innerHeight * f) + 'px';
    /* Το srcdoc φορτώνει ασύγχρονα — περίμενέ το, αλλιώς η εκτύπωση βρίσκει άδειο πλαίσιο. */
    await new Promise(done => {
      let fired = false;
      const ok = () => { if (!fired) { fired = true; done(); } };
      fr.addEventListener('load', ok, {once: true});
      setTimeout(ok, 1500);
    });
  }

  async function printDoc() {
    /* Αν τυπώσεις από άλλη καρτέλα, το πλαίσιο κρατά παλιό έγγραφο: ξαναφτιάξ' το. */
    if (st.tab !== 'doc') { st.tab = 'doc'; shell(); paintNumbers(); }
    await recalc();
    await renderDoc();
    const fr = body.querySelector('.ph-frame');
    if (!fr || !fr.contentWindow) { toast('Δεν παρήχθη το έγγραφο — δοκίμασε ξανά', true); return; }
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
