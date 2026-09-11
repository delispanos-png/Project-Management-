/* ═══════════ CloudOn Projects — κοστολόγηση & προσφορά ΤΗΛΕΦΩΝΙΚΟΥ ΚΕΝΤΡΟΥ (3CX / Yeastar) ═══════════
   Ήρθε από τον αυτόνομο «Διαμορφωτή Προσφορών» (HTML): πλατφόρμα, μέγεθος εγκατάστασης,
   είδη ανά κατηγορία, τιμοκατάλογος, έγγραφο. Τώρα ζει μέσα στο κύκλωμα Προσφορών:
   η κοστολόγηση γεννά ΚΑΝΟΝΙΚΗ προσφορά (στάδια, αποστολή, follow-up, portal πελάτη).

   Ξεχωριστό από το PharmacyOne (views7.js / lib/Pharmacy.php) — δεν μοιράζεται τίποτα
   πέρα από τα κοινά στυλ του drawer. Ο υπολογισμός γίνεται στον server (lib/Pbx.php)·
   η οθόνη μόνο ρωτάει, ώστε ποσό προσφοράς και έγγραφο να μη διαφέρουν ποτέ. */
'use strict';
const {S, api, esc, fmtEur, toast, cnpDenied, cnpCan, closeDrawer, I, go, $, $$} = window.CNP;
const R = window.R;

let PX = null;           // ο κατάλογος (πλατφόρμες/είδη) — φορτώνεται μία φορά
let pxTimer = null;      // debounce ζωντανής προεπισκόπησης

async function pxDefs() {
  if (PX) { return PX; }
  PX = await api('pbx_defs');
  return PX;
}
const pxMoney = v => fmtEur(Math.round((+v || 0) * 100) / 100);
const pxNum = v => new Intl.NumberFormat('el-GR', {minimumFractionDigits: 2, maximumFractionDigits: 2}).format(+v || 0);

/**
 * @param {number|null} offerId  υπάρχουσα προσφορά προς αναθεώρηση
 * @param {object|null} pre      {client, name} όταν ξεκινά από πελάτη
 */
async function openPbx(offerId, pre) {
  if (!cnpCan('clients.offers')) { toast('Δεν έχεις δικαίωμα στις Προσφορές', true); return; }
  closeDrawer();
  const ovl = document.createElement('div'); ovl.className = 'ovl';
  const dr = document.createElement('div'); dr.className = 'drawer tk-modal ph-dr';
  dr.innerHTML = `<div class="drawer-h"><h2>${I.phone} Κοστολόγηση τηλεφωνικού κέντρου</h2>
    <button class="drawer-x" id="dX">✕</button></div>
    <div class="drawer-b" id="pxBody"><div class="skel" style="height:340px"></div></div>`;
  document.body.append(ovl, dr);
  requestAnimationFrame(() => { ovl.classList.add('show'); dr.classList.add('show'); });
  $('#dX', dr).onclick = () => closeDrawer();

  const body = $('#pxBody', dr);
  let defs;
  try { defs = await pxDefs(); } catch (e) { body.innerHTML = cnpDenied(e); return; }

  /* ── η ρύθμιση ── */
  const st = {
    offer: offerId || 0,
    client: (pre && pre.client) || 0,
    clientName: (pre && pre.name) || '',
    tab: 'setup',
    cfg: {plat: '3cx', ext: 5, sc: 8, disc: 0, qty: {}, on: {}, price: {},
      o: {seller: defs.me || '', city: 'Αθήνα', vat: 24, validDays: 30, intro: defs.intro || '', full: 0,
        payMethod: (defs.payMethods || [])[0] || 'Τραπεζική κατάθεση', delivery: defs.delivery || '',
        protocol: defs.nextProtocol || ('CLD-' + new Date().getFullYear() + '-'),
        date: new Date().toISOString().slice(0, 10), plan: []}},
    calc: null,
  };
  if (offerId) {
    const ex = await api('pbx_doc&offer=' + offerId).catch(() => null);
    if (ex && ex.cfg) {
      st.cfg = ex.cfg;
      st.client = ex.client || 0;
      st.clientName = ex.cfg.o.client || '';
    }
  }

  /* Ο server επιστρέφει την κανονικοποιημένη ρύθμιση — τη δεχόμαστε ως αλήθεια. */
  const recalc = async () => {
    const r = await api('pbx_calc', {config: st.cfg}).catch(() => null);
    if (!r) { return; }
    st.cfg = r.cfg;
    st.calc = r;
    paintNumbers();
  };
  const touch = () => { clearTimeout(pxTimer); pxTimer = setTimeout(recalc, 220); };
  const syncDoc = () => {
    $$('[data-o]', body).forEach(inp => {
      const k = inp.dataset.o;
      if (k in st.cfg.o) { inp.value = st.cfg.o[k] === undefined || st.cfg.o[k] === null ? '' : st.cfg.o[k]; }
    });
    const w = $('#pxWho', body); if (w) { w.value = st.clientName || ''; }
    const a = $('#pxAfm', body); if (a) { a.value = st.cfg.o.afm || ''; }
  };

  /* ── σκελετός ── */
  const shell = () => {
    body.innerHTML = `
    <div class="ph-top">
      <div class="ph-who">
        <input class="inp" id="pxWho" placeholder="Επωνυμία πελάτη…" value="${esc(st.clientName)}" autocomplete="off">
        <input class="inp ph-afm" id="pxAfm" placeholder="ΑΦΜ" maxlength="9" inputmode="numeric" value="${esc(st.cfg.o.afm || '')}">
        <button class="btn btn-o btn-sm" id="pxAade" title="Άντληση στοιχείων από το μητρώο ΑΑΔΕ">Άντληση ΑΑΔΕ</button>
        <span class="mut ph-afmst" id="pxAfmSt"></span>
      </div>
      <div id="pxPick"></div>
      <div class="td-seg ph-tabs">
        ${[['setup', 'Διαμόρφωση'], ['rates', 'Τιμοκατάλογος'], ['pay', 'Πληρωμή'], ['doc', 'Έγγραφο']]
          .map(([k, l]) => `<button data-tab="${k}" class="${st.tab === k ? 'on' : ''}">${l}</button>`).join('')}
      </div>
    </div>
    <div id="pxCards" class="ph-cards pbx-tiles"${st.tab === 'doc' ? ' hidden' : ''}></div>
    <div id="pxWarn"></div>
    <div id="pxPane"></div>
    <div class="ph-foot">
      <div class="mut ph-sum" id="pxSum"></div>
      <button class="btn btn-o" id="pxPrint">${I.doc} Εκτύπωση</button>
      <button class="btn btn-o" id="pxEmail" title="Αποστολή της προσφοράς στον πελάτη ως PDF">✉ Αποστολή</button>
      ${st.offer ? '<button class="btn btn-o" id="pxComments" title="Ερωτήσεις & σχόλια πελάτη">💬 Ερωτήσεις</button>' : ''}
      ${st.offer && cnpCan('clients.offers.delete') ? '<button class="btn btn-danger" id="pxDel">🗑 Διαγραφή</button>' : ''}
      <button class="btn btn-p" id="pxSave">${st.offer ? 'Ενημέρωση προσφοράς' : 'Δημιουργία προσφοράς'}</button>
    </div>`;
    $$('[data-tab]', body).forEach(b => b.onclick = () => { st.tab = b.dataset.tab; shell(); paintNumbers(); });
    wireWho();
    $('#pxPrint', body).onclick = printDoc;
    $('#pxEmail', body).onclick = openEmailDialog;
    { const cb = $('#pxComments', body); if (cb && window.CNP.openOfferComments) cb.onclick = () => window.CNP.openOfferComments(st.offer); }
    $('#pxSave', body).onclick = save;
    const pdl = $('#pxDel', body); if (pdl) { pdl.onclick = async () => {
      if (!(await window.CNP.cnpConfirm('Να διαγραφεί οριστικά αυτή η προσφορά τηλεφωνικού κέντρου;', {ok: '🗑 Διαγραφή', cancel: 'Άκυρο'}))) { return; }
      const r = await api('delete_offer', {offer: st.offer}).catch(e => ({err: e && e.message}));
      if (r && r.err) { toast(r.err, true); return; }
      toast('Η προσφορά διαγράφηκε'); closeDrawer();
      if (S.view === 'offers') { R.offers(); } else { go('offers'); }
    }; }
    pane();
  };

  /* ── ποιος πελάτης (ίδια εμπειρία με τις άλλες προσφορές) ── */
  function wireWho() {
    const w = $('#pxWho', body);
    let t = null;
    w.oninput = () => {
      st.clientName = w.value.trim();
      st.client = 0;
      st.cfg.o.client = st.clientName;
      clearTimeout(t);
      if (w.value.trim().length < 3) { $('#pxPick', body).innerHTML = ''; return; }
      t = setTimeout(async () => {
        const r = await api('call_who&q=' + encodeURIComponent(w.value.trim())).catch(() => null);
        const list = ((r && r.results) || []).filter(x => x.type === 'client');
        $('#pxPick', body).innerHTML = list.length ? `<div class="qc-list">${list.map(x =>
          `<div class="qc-opt" data-i="${x.id}" data-n="${esc(x.name)}">${I.user}<b>${esc(x.name)}</b></div>`).join('')}</div>` : '';
        $$('.qc-opt', body).forEach(el => el.onclick = () => {
          st.client = +el.dataset.i; st.clientName = el.dataset.n;
          st.cfg.o.client = st.clientName;
          w.value = st.clientName; $('#pxPick', body).innerHTML = '';
        });
      }, 250);
    };
    const a = $('#pxAfm', body);
    a.oninput = () => { st.cfg.o.afm = a.value.replace(/\D/g, '').slice(0, 9); a.value = st.cfg.o.afm; };
    $('#pxAade', body).onclick = async () => {
      const stEl = $('#pxAfmSt', body);
      const afm = String(st.cfg.o.afm || '').replace(/\D/g, '');
      if (afm.length !== 9) { stEl.textContent = 'Δώσε 9ψήφιο ΑΦΜ'; return; }
      stEl.textContent = 'Αναζήτηση στο μητρώο…';
      const r = await fetch('afm.php?afm=' + afm, {credentials: 'same-origin'}).then(x => x.json()).catch(() => null);
      if (!r || !r.ok) { stEl.textContent = 'ΑΑΔΕ: ' + ((r && r.error) || 'χωρίς αποτέλεσμα'); return; }
      const dd = r.data || {};
      if (dd.name) { st.clientName = dd.name; st.cfg.o.client = dd.name; w.value = dd.name; }
      if (dd.doy) { st.cfg.o.doy = dd.doy; }
      const addr = [dd.street, dd.postcode, dd.city].filter(Boolean).join(', ');
      if (addr) { st.cfg.o.address = addr; }
      if (dd.city) { st.cfg.o.city = dd.city; }
      stEl.textContent = (dd.active === false ? '⚠ ανενεργό ΑΦΜ — ' : '✓ ') + (dd.name || '');
      syncDoc();
      if (st.tab === 'doc') { renderDoc(); }
    };
  }

  /* ── Αποστολή προσφοράς στον πελάτη (PDF συνημμένο) ── */
  function openEmailDialog() {
    if (!st.clientName.trim()) { toast('Δώσε πρώτα επωνυμία πελάτη', true); $('#pxWho', body).focus(); return; }
    if (st.calc && st.calc.missing && st.calc.missing.length) {
      toast('Λείπουν τιμές από είδη της προσφοράς — συμπλήρωσέ τις πριν την αποστολή', true); return;
    }
    const o = st.cfg.o || {};
    const proto = o.protocol || '';
    const defSubj = 'Οικονομική προσφορά τηλεφωνικού κέντρου CloudOn' + (proto ? ' — ' + proto : '');
    const ov = document.createElement('div'); ov.className = 'ovl show';
    ov.style.zIndex = '60';
    ov.innerHTML = `<div class="pal-box ai-box" onclick="event.stopPropagation()">
      <div class="ai-h"><b>✉ Αποστολή προσφοράς</b>
        <span class="mut" style="font-size:11.5px">η προσφορά φεύγει ως PDF συνημμένο</span></div>
      <div class="ai-b">
        <label class="lbl">Email παραλήπτη</label>
        <input class="inp" id="emTo" type="email" value="${esc(o.cemail || '')}" placeholder="π.χ. info@example.gr">
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
      const r = await api('pbx_email', {offer: st.offer || 0, config: st.cfg,
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
      if (st.offer && S.view === 'offers') { R.offers(); }
    };
  }

  /* ── οι αριθμοί: πλακίδια, γραμμές ειδών, προειδοποίηση τιμών, υποσέλιδο ── */
  function paintNumbers() {
    if (!st.calc) { return; }
    const t = st.calc.totals;
    const cards = $('#pxCards', body);
    if (cards) {
      cards.innerHTML = [
        ['Ετήσια (καθαρό)', pxMoney(t.rec), 'επαναλαμβανόμενα κάθε χρόνο'],
        ['Εφάπαξ (καθαρό)', pxMoney(t.once), 'υπηρεσίες & εξοπλισμός'],
        ['1ο έτος με ΦΠΑ', pxMoney(t.gross), t.discAmt > 0 ? 'μετά από έκπτωση ' + pxMoney(t.discAmt) : 'ετήσια + εφάπαξ'],
        ['2ο έτος και μετά', pxMoney(t.y2), 'με ΦΠΑ · μόνο ετήσια'],
      ].map(([k, v, n], i) => `<div class="ph-card pbx-tile${i === 2 ? ' on' : ''}">
        <div class="ph-lab">${esc(k)}</div><div class="ph-big">${v}</div><div class="ph-soft">${esc(n)}</div></div>`).join('');
    }
    /* γραμμές ειδών: ποσά και «λείπει τιμή» χωρίς rerender (κρατάμε το focus) */
    const byId = {}; (st.calc.lines || []).forEach(l => { byId[l.id] = l; });
    $$('.pbx-row[data-id]', body).forEach(row => {
      const l = byId[row.dataset.id]; if (!l) { return; }
      const tot = $('.pbx-tot', row); if (tot) { tot.textContent = l.on ? pxNum(l.total) : '—'; }
      const up = $('.pbx-up', row); if (up) { up.textContent = pxNum(l.price); }
      const qd = $('.pbx-qext', row); if (qd) { qd.textContent = l.qty; }
      row.classList.toggle('off', !l.on);
      row.classList.toggle('needs', !!l.needs);
    });
    const warn = $('#pxWarn', body);
    if (warn) {
      const miss = st.calc.missing || [];
      warn.innerHTML = miss.length ? `<div class="pbx-alert"><b>Λείπουν τιμές.</b> ${miss.length} ${miss.length === 1 ? 'είδος έχει' : 'είδη έχουν'} ποσότητα αλλά τιμή 0,00 € και δεν μετρούν στο σύνολο:
        ${miss.map(esc).join(' · ')}. <a data-gorates>Συμπλήρωσέ τις στον τιμοκατάλογο.</a></div>` : '';
      const g = $('[data-gorates]', warn); if (g) { g.onclick = () => { st.tab = 'rates'; shell(); paintNumbers(); }; }
    }
    const sum = $('#pxSum', body);
    if (sum) {
      const P = defs.platforms[st.cfg.plat] || {name: st.cfg.plat};
      sum.innerHTML = `<b>${esc(P.name)}</b> · ${st.cfg.ext} εσωτ. · καθαρό 1ο έτος <b style="color:var(--ink)">${pxMoney(t.net)}</b>`
        + (t.discAmt > 0 ? ` <span class="mut">(έκπτωση ${pxMoney(t.discAmt)})</span>` : '') + ` · με ΦΠΑ ${pxMoney(t.gross)}`;
    }
    if (st.tab === 'doc') { docPane(); }
    if (st.tab === 'pay') { planSum(); }
  }

  const itemsOf = plat => (defs.items || []).filter(it => it.plat === plat);

  /* ── τα φύλλα ── */
  function pane() {
    const el = $('#pxPane', body);
    if (st.tab === 'setup') {
      const plats = Object.entries(defs.platforms || {});
      el.innerHTML = `<div class="card"><div class="card-b">
        <label class="lbl ph-sec">Πλατφόρμα τηλεφωνικού κέντρου</label>
        <div class="pbx-plats">${plats.map(([k, p]) => `<label class="pbx-plat${st.cfg.plat === k ? ' sel' : ''}">
          <input type="radio" name="pxPlat" value="${k}"${st.cfg.plat === k ? ' checked' : ''}>
          <span class="pbx-plat-n"><span class="pbx-dot"></span>${esc(p.name)}</span>
          <span class="pbx-plat-d">${esc(p.desc)}</span></label>`).join('')}</div>
        <label class="lbl ph-sec" style="margin-top:18px">Μέγεθος εγκατάστασης</label>
        <div class="ph-fields">
          <div class="field"><label>Εσωτερικά</label><input class="inp" type="number" min="0" step="1" data-c="ext" value="${st.cfg.ext}">
            <span class="ph-hint">Οδηγεί τα είδη «ανά εσωτερικό»</span></div>
          <div class="field"><label>Ταυτόχρονες κλήσεις</label><input class="inp" type="number" min="0" step="1" data-c="sc" value="${st.cfg.sc}">
            <span class="ph-hint">Καθορίζει την άδεια</span></div>
          <div class="field"><label>Έκπτωση επί του συνόλου</label><input class="inp" type="number" min="0" max="100" step="0.5" data-c="disc" value="${st.cfg.disc}"><span class="ph-pct">%</span>
            <span class="ph-hint">Μειώνει ετήσια και εφάπαξ μαζί</span></div>
        </div>
      </div></div>
      <div class="card"><div class="card-b">
        <label class="lbl ph-sec">Είδη προσφοράς</label>
        <div class="pbx-items">
          <div class="pbx-row head"><div>Είδος</div><div class="n">Ποσότητα</div><div class="n">Τιμή μον.</div><div class="n">Σύνολο</div></div>
          ${(defs.cats || []).map((cat, ci) => {
            const g = itemsOf(st.cfg.plat).filter(it => it.cat === ci);
            if (!g.length) { return ''; }
            return `<div class="pbx-grp">${esc(cat)}</div>` + g.map(it => {
              const on = st.cfg.on[it.id] === undefined ? it.on : !!st.cfg.on[it.id];
              const q = it.perExt ? st.cfg.ext : (st.cfg.qty[it.id] === undefined ? it.qty : st.cfg.qty[it.id]);
              const p = st.cfg.price[it.id] === undefined ? it.price : st.cfg.price[it.id];
              return `<div class="pbx-row${on ? '' : ' off'}" data-id="${esc(it.id)}">
                <div class="pbx-nm"><label><input type="checkbox" data-on="${esc(it.id)}"${on ? ' checked' : ''}>
                  <span>${esc(it.name)}${it.perExt ? '<span class="pbx-perext">ανά εσωτερικό</span>' : ''}${it.rec ? '' : '<span class="pbx-once">εφάπαξ</span>'}
                  ${it.note ? `<small>${esc(it.note)}</small>` : ''}<small class="pbx-needs">Χρειάζεται τιμή στον τιμοκατάλογο</small></span></label></div>
                <div class="n">${it.perExt ? `<span class="pbx-qext">${q}</span>` : `<input class="ph-mini" type="number" min="0" step="1" data-qty="${esc(it.id)}" value="${q}">`}</div>
                <div class="n pbx-up">${pxNum(p)}</div>
                <div class="n pbx-tot"><b>—</b></div>
              </div>`;
            }).join('');
          }).join('')}
        </div>
      </div></div>`;
      $$('input[name=pxPlat]', el).forEach(r => r.onchange = () => { st.cfg.plat = r.value; shell(); recalc(); });
      $$('[data-c]', el).forEach(inp => inp.oninput = () => {
        const v = parseFloat(inp.value); const n = isFinite(v) ? v : 0;
        st.cfg[inp.dataset.c] = n;
        if (inp.dataset.c === 'ext') { $$('.pbx-qext', el).forEach(s => { s.textContent = Math.max(0, n | 0); }); }
        touch();
      });
      $$('[data-qty]', el).forEach(inp => inp.oninput = () => {
        const v = parseFloat(inp.value); st.cfg.qty[inp.dataset.qty] = isFinite(v) && v > 0 ? v : 0; touch();
      });
      $$('[data-on]', el).forEach(ch => ch.onchange = () => {
        st.cfg.on[ch.dataset.on] = ch.checked ? 1 : 0;
        ch.closest('.pbx-row').classList.toggle('off', !ch.checked);
        touch();
      });
    } else if (st.tab === 'rates') {
      const P = defs.platforms[st.cfg.plat] || {name: st.cfg.plat};
      el.innerHTML = `<div class="card"><div class="card-b">
        <label class="lbl" style="margin:0 0 4px">Τιμοκατάλογος — ${esc(P.name)}</label>
        <div class="mut" style="font-size:11.5px;margin-bottom:11px;line-height:1.5">
          Οι αλλαγές ισχύουν <b>μόνο για αυτή την προσφορά</b> — ο γενικός τιμοκατάλογος δεν πειράζεται.
          Οι τιμές 3CX προέρχονται από την επαληθευμένη προσφορά της 11ης Αυγούστου 2026·
          οι τιμές Yeastar είναι κενές επίτηδες μέχρι να οριστούν από τον διαχειριστή.
        </div>
        <div class="ph-rates">
          <div class="ph-rh">Είδος</div><div class="ph-rh n">Τιμή €</div><div class="ph-rh n">Τύπος</div>
          ${itemsOf(st.cfg.plat).map(it => {
            const p = st.cfg.price[it.id] === undefined ? it.price : st.cfg.price[it.id];
            return `<div class="ph-rl${p > 0 ? '' : ' pbx-zero'}">${esc(it.name)}${it.note ? ` <span class="mut">· ${esc(it.note)}</span>` : ''}</div>
            <div class="n"><input class="ph-mini" type="number" min="0" step="0.01" data-price="${esc(it.id)}" value="${p}"></div>
            <div class="n mut" style="font-size:11.5px">${it.rec ? 'ετήσιο' : 'εφάπαξ'}${it.perExt ? ' · ανά εσωτ.' : ''}</div>`;
          }).join('')}
        </div>
        ${defs.canEditBase ? `<div style="margin-top:16px;padding-top:14px;border-top:1px solid var(--line);display:flex;gap:9px;align-items:center;flex-wrap:wrap">
          <button class="btn btn-p btn-sm" id="pxBaseSave">💾 Αποθήκευση ως βασικός τιμοκατάλογος</button>
          <button class="btn btn-o btn-sm" id="pxBaseReset">↺ Εργοστασιακές τιμές</button>
          <span class="mut" style="font-size:11px">Ορίζει τις προεπιλεγμένες τιμές (και των δύο πλατφορμών) για <b>όλες τις νέες</b> προσφορές (οι ήδη αποθηκευμένες δεν αλλάζουν).</span>
        </div>` : ''}
      </div></div>`;
      $$('[data-price]', el).forEach(inp => inp.oninput = () => {
        const v = parseFloat(inp.value); st.cfg.price[inp.dataset.price] = isFinite(v) && v >= 0 ? v : 0;
        inp.closest('.n').previousElementSibling.classList.toggle('pbx-zero', !(v > 0));
        touch();
      });
      const rowOf = inp => {
        let c = inp.parentElement;
        while (c && !c.classList.contains('ph-rl')) { c = c.previousElementSibling; }
        const cells = [];
        while (c) { cells.push(c); c = c.nextElementSibling; if (!c || c.classList.contains('ph-rl') || c.classList.contains('ph-rh')) { break; } }
        return cells;
      };
      $$('.ph-rates input', el).forEach(inp => {
        inp.onfocus = () => rowOf(inp).forEach(c => c.classList.add('hot'));
        inp.onblur = () => $$('.ph-rates .hot', el).forEach(c => c.classList.remove('hot'));
      });
      { const bs = $('#pxBaseSave', el); if (bs) bs.onclick = async () => {
        if (!(await window.CNP.cnpConfirm('Να γίνουν οι ΤΡΕΧΟΥΣΕΣ τιμές (3CX και Yeastar) ο βασικός τιμοκατάλογος τηλεφωνικών κέντρων για ΟΛΕΣ τις νέες προσφορές;\n\nΟι ήδη αποθηκευμένες προσφορές ΔΕΝ αλλάζουν.', {ok: '💾 Αποθήκευση', cancel: 'Άκυρο'}))) { return; }
        bs.disabled = true;
        const items = {}; Object.keys(st.cfg.price || {}).forEach(id => { items[id] = {price: st.cfg.price[id]}; });
        const r = await api('pbx_catalog_save', {items}).catch(e => ({err: e && e.message}));
        bs.disabled = false;
        if (r && r.err) { toast(r.err, true); return; }
        PX = null;
        toast('✅ Ο βασικός τιμοκατάλογος τηλεφωνικών κέντρων αποθηκεύτηκε');
      }; }
      { const br = $('#pxBaseReset', el); if (br) br.onclick = async () => {
        if (!(await window.CNP.cnpConfirm('Επαναφορά στις εργοστασιακές τιμές;\n\nΤα 3CX γυρνούν στην προσφορά 11/8/2026 και τα Yeastar σε 0,00 €.', {ok: '↺ Επαναφορά', cancel: 'Άκυρο', danger: true}))) { return; }
        const r = await api('pbx_catalog_reset', {}).catch(e => ({err: e && e.message}));
        if (r && r.err) { toast(r.err, true); return; }
        PX = null;
        const nd = await pxDefs(); Object.assign(defs, nd);
        st.cfg.price = {}; (nd.items || []).forEach(it => { st.cfg.price[it.id] = it.price; });
        pane(); await recalc();
        toast('↺ Επαναφορά εργοστασιακών τιμών');
      }; }
    } else if (st.tab === 'pay') {
      payPane();
    } else {
      docPane();
    }
  }

  /* [κλειδί, ετικέτα, τύπος, επιλογές|null, περιοχή] */
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
    ['validDays', 'Ισχύς (ημέρες)', 'num', null, F_DOC],
    ['intro', 'Εισαγωγικό κείμενο', 'area', null, F_DOC],
    ['full', 'Τεχνική περιγραφή στην προσφορά', 'chk', null, F_DOC],

    ['vat', 'ΦΠΑ %', 'num', null, F_PAY],
    ['payMethod', 'Τρόπος εξόφλησης', 'sel', defs.payMethods || ['Τραπεζική κατάθεση'], F_PAY],
    ['delivery', 'Χρόνος παράδοσης', 'text', null, F_PAY],
  ];

  const planRow = (r, x) => `<div class="ph-plan-r" data-x="${x}">
    <select class="ph-mini" data-pl="t">
      <option value="pct"${r.t !== 'eur' ? ' selected' : ''}>%</option>
      <option value="eur"${r.t === 'eur' ? ' selected' : ''}>€</option>
    </select>
    <input class="ph-mini" type="number" min="0" step="any" data-pl="v" value="${+r.v || 0}">
    <input class="inp" type="text" data-pl="w" value="${esc(r.w || '')}" placeholder="π.χ. Με την παράδοση">
    <button type="button" class="ph-plan-x" title="Διαγραφή δόσης">✕</button>
  </div>`;
  const planBlock = () => `<div class="ph-plan" id="pxPlan">
    <div class="ph-plan-h"><span>Δόσεις πληρωμής <span class="mut" style="font-weight:400">— πληρωτέα ποσά, με ΦΠΑ</span></span><span class="ph-plan-sum" id="pxPlanSum"></span></div>
    <div id="pxPlanRows">${(st.cfg.o.plan || []).map(planRow).join('')}</div>
    <button type="button" class="btn btn-o btn-sm" id="pxPlanAdd">+ Δόση</button>
  </div>`;
  function planSum() {
    const el = $('#pxPlanSum', body);
    if (!el) { return; }
    const total = (st.calc && st.calc.totals && st.calc.totals.gross) || 0;
    let sum = 0;
    (st.cfg.o.plan || []).forEach(r => { sum += r.t === 'eur' ? (+r.v || 0) : total * (+r.v || 0) / 100; });
    const rest = total - sum;
    el.textContent = pxMoney(sum) + (Math.abs(rest) > 0.01 ? ' · υπόλοιπο ' + pxMoney(rest) : ' · καλύπτει το σύνολο με ΦΠΑ');
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
      const box = $('#pxPlanRows', el);
      if (box) { box.innerHTML = (st.cfg.o.plan || []).map(planRow).join(''); wirePlan(el); }
      planSum();
      clearTimeout(pxTimer); pxTimer = setTimeout(async () => { await recalc(); renderDoc(); }, 320);
    };
    $$('.ph-plan-r [data-pl]', el).forEach(inp => {
      inp.oninput = () => { read(); clearTimeout(pxTimer); pxTimer = setTimeout(async () => { await recalc(); renderDoc(); }, 400); };
      inp.onchange = inp.oninput;
    });
    $$('.ph-plan-x', el).forEach(b => b.onclick = () => {
      const x = +b.closest('.ph-plan-r').dataset.x;
      st.cfg.o.plan = (st.cfg.o.plan || []).filter((r, i) => i !== x);
      redraw();
    });
    const add = $('#pxPlanAdd', el);
    if (add) { add.onclick = () => { st.cfg.o.plan = (st.cfg.o.plan || []).concat([{t: 'pct', v: 0, w: ''}]); redraw(); }; }
  }

  const dfld = ([k, lab, t, opts]) => {
    const v = st.cfg.o[k] === undefined ? '' : st.cfg.o[k];
    let ctl;
    if (t === 'sel') {
      ctl = `<select class="inp" data-o="${k}" data-k="sel">${opts.map(o => `<option${String(o) === String(v) ? ' selected' : ''}>${esc(o)}</option>`).join('')}</select>`;
    } else if (t === 'area') {
      ctl = `<textarea class="inp" rows="3" data-o="${k}" data-k="text">${esc(v)}</textarea>`;
    } else if (t === 'chk') {
      ctl = `<label class="pbx-chk"><input type="checkbox" data-o="${k}" data-k="chk"${v ? ' checked' : ''}> Ξεδιπλώνει δυνατότητες και απαιτήσεις χώρου</label>`;
    } else {
      ctl = `<input class="inp" type="${t === 'date' ? 'date' : (t === 'num' ? 'number' : 'text')}" data-o="${k}" data-k="${t}" value="${esc(v)}">`;
    }
    return `<div class="field${t === 'area' ? ' pbx-wide' : ''}"><label>${esc(lab)}</label>${ctl}</div>`;
  };
  function wireDocFields(el) {
    $$('[data-o]', el).forEach(inp => {
      const upd = () => {
        const k = inp.dataset.k;
        st.cfg.o[inp.dataset.o] = k === 'num' ? (parseFloat(inp.value) || 0) : (k === 'chk' ? (inp.checked ? 1 : 0) : inp.value);
        clearTimeout(pxTimer);
        pxTimer = setTimeout(async () => { await recalc(); renderDoc(); }, 320);
      };
      inp.oninput = upd; inp.onchange = upd;
    });
  }
  const byGroup = (list, key, dflt) => {
    const out = [];
    list.forEach(d => {
      const t = d[key] || dflt;
      let g = out.find(x => x.t === t);
      if (!g) { g = {t, items: []}; out.push(g); }
      g.items.push(d);
    });
    return out;
  };

  function payPane() {
    const el = $('#pxPane', body);
    el.innerHTML = `<div class="card"><div class="card-b">
      <label class="lbl ph-sec">Οικονομικοί όροι</label>
      <div class="ph-fields">${DOC_FIELDS.filter(d => d[4] === F_PAY).map(dfld).join('')}</div>
      ${planBlock()}
    </div></div>`;
    wireDocFields(el); wirePlan(el); planSum();
  }

  async function docPane() {
    const el = $('#pxPane', body);
    if (!el.querySelector('#pxDocFields')) {
      el.innerHTML = `<details class="card ph-docf">
        <summary>Στοιχεία εγγράφου &amp; πελάτη — πάτα για επεξεργασία</summary>
        <div class="card-b" id="pxDocFields">${
        byGroup(DOC_FIELDS.filter(d => d[4] !== F_PAY), 4, F_DOC).map(g =>
          `<label class="lbl ph-sec">${esc(g.t)}</label>
           <div class="ph-fields">${g.items.map(dfld).join('')}</div>`).join('')
      }</div></details>
        <div class="ph-doc" id="pxDoc"><div class="skel" style="height:300px"></div></div>`;
      { const dt = el.querySelector('.ph-docf'); if (dt) { dt.ontoggle = () => renderDoc(); } }
      wireDocFields(el);
    }
    renderDoc();
  }
  /* Το έγγραφο ζει σε iframe: δικό του στυλ, τυπώνεται ακριβώς όπως φαίνεται. */
  async function renderDoc() {
    const box = $('#pxDoc', body);
    if (!box) { return; }
    const r = await api('pbx_doc', {config: st.cfg}).catch(() => null);
    if (!r) { box.innerHTML = '<div class="empty">Δεν παρήχθη το έγγραφο</div>'; return; }
    let fr = box.querySelector('iframe');
    if (!fr) {
      box.innerHTML = '';
      fr = document.createElement('iframe'); fr.className = 'ph-frame'; fr.title = 'Προεπισκόπηση προσφοράς';
      box.append(fr);
    }
    fr.srcdoc = '<!doctype html><html lang="el"><head><meta charset="utf-8"><base href="/project/">'
      + '<title>' + esc('Προσφορά — ' + (st.clientName || 'Τηλεφωνικό κέντρο')) + '</title>'
      + '<style>' + (r.css || '') + '</style></head><body>' + r.html + '</body></html>';
    const openF = !!body.querySelector('.ph-docf[open]');
    const f = window.innerWidth < 700 ? (openF ? 0.6 : 0.72) : (openF ? 0.58 : 0.82);
    fr.style.height = Math.round(window.innerHeight * f) + 'px';
    await new Promise(done => {
      let fired = false;
      const ok = () => { if (!fired) { fired = true; done(); } };
      fr.addEventListener('load', ok, {once: true});
      setTimeout(ok, 1500);
    });
  }

  async function printDoc() {
    if (st.tab !== 'doc') { st.tab = 'doc'; shell(); paintNumbers(); }
    await recalc();
    await renderDoc();
    const fr = body.querySelector('.ph-frame');
    if (!fr || !fr.contentWindow) { toast('Δεν παρήχθη το έγγραφο — δοκίμασε ξανά', true); return; }
    fr.contentWindow.focus();
    fr.contentWindow.print();
  }

  async function save() {
    if (!st.clientName.trim()) { toast('Δώσε επωνυμία πελάτη', true); $('#pxWho', body).focus(); return; }
    const btn = $('#pxSave', body); btn.disabled = true;
    const r = await api('pbx_save', {offer: st.offer, client: st.client, clientName: st.clientName, config: st.cfg})
      .catch(e => ({ok: false, error: e && e.message}));
    btn.disabled = false;
    if (!r.ok) { toast(r.error || 'Δεν αποθηκεύτηκε', true); return; }
    st.offer = r.offer;
    btn.textContent = 'Ενημέρωση προσφοράς';
    toast(offerId ? 'Η προσφορά ενημερώθηκε' : 'Δημιουργήθηκε προσφορά ' + pxMoney(r.amount));
    closeDrawer();
    if (S.view === 'offers') { R.offers(); } else { go('offers'); }
  }

  shell();
  await recalc();
}

window.openPbx = openPbx;
window.CNP.openPbx = openPbx;
