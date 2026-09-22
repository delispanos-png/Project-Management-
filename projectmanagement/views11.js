/* ═══════════ ΡΟΛΟΙ, ΕΙΔΙΚΟΤΗΤΕΣ ΚΑΙ Η ΕΙΚΟΝΑ ΤΗΣ ΗΜΕΡΑΣ ═══════════
   Δύο οθόνες, δύο διαφορετικές ερωτήσεις:
     «Ρόλοι & ειδικότητες» → ποιος παίζει και ποιος ξέρει τι   (hr.roles)
     «Οι χειριστές σήμερα» → τι κρατάει ο καθένας τώρα         (reports.pool)

   ΤΟ ΕΔΑΦΟΣ ΤΗΣ ΔΕΞΑΜΕΝΗΣ. Πριν ο μηχανισμός μοιράσει οτιδήποτε μόνος του,
   πρέπει να ξέρει τρία πράγματα: ποιος μπαίνει στο παιχνίδι, τι χαρακτήρα έχει
   η μέρα του, και ποιο προϊόν ξέρει. Αυτές οι οθόνες τα γράφουν — και μόνο αυτά.

   Η ΔΕΥΤΕΡΗ ΟΘΟΝΗ ΕΙΝΑΙ ΚΑΘΡΕΦΤΗΣ, ΟΧΙ ΧΕΙΡΙΣΤΗΡΙΟ. Τρέχει όλο το μυαλό της
   δεξαμενής και δείχνει τι ΘΑ έδινε σε ποιον, χωρίς να αναθέτει τίποτα. Έτσι
   φαίνεται αν ο χάρτης λέει αλήθεια, ΠΡΙΝ ανοίξει η βρύση.

   Η ΠΡΩΤΗ ΟΘΟΝΗ ΕΙΝΑΙ ΑΠΟΦΑΣΗ: ξεκινά με τα κενά του χάρτη — ποιο προϊόν το
   ξέρει ΕΝΑΣ άνθρωπος. Ο πίνακας 11×13 είναι το υλικό, όχι το μήνυμα. */
'use strict';
const {api, esc, toast, setTop, cnpConfirm, cnpDenied, cnpCan,
  cnpSkel, fChip, fSel, fAdd, fWire, $, $$} = window.CNP;
const R = window.R;

/* Ώρες όπως τις λέει άνθρωπος: 90 λεπτά → «1:30», 45 → «45΄». */
const plHm = m => {
  m = Math.max(0, Math.round(+m || 0));
  return m < 60 ? m + '΄' : Math.floor(m / 60) + ':' + String(m % 60).padStart(2, '0');
};

/* Ο βαθμός ειδικότητας ως χρώμα. Το κενό ΔΕΝ είναι βαθμός — είναι απουσία. */
const PL_LV = {
  main:  ['Κύριος',   'var(--ok, #15803d)',   'Κ'],
  can:   ['Μπορεί',   'var(--brand)',         'Μ'],
  learn: ['Μαθαίνει', 'var(--warn, #b45309)', 'Α'],
};


/* Πόσο επικίνδυνο είναι ένα προϊόν. Μετράει μόνο όποιος ΟΝΤΩΣ παίζει. */
const PL_RISK = {
  none:   ['Ακάλυπτο',    'var(--bad, #b91c1c)', 'δεν το ξέρει κανείς από όσους παίζουν'],
  single: ['Ένας μόνο',   'var(--bad, #b91c1c)', 'αν λείψει, δεν το παίρνει κανείς'],
  thin:   ['Οριακά δύο',  'var(--warn, #b45309)', 'αντέχει μία απουσία, όχι δύο'],
  ok:     ['Καλυμμένο',   'var(--ok, #15803d)',  ''],
};

/* Το δέντρο σε επίπεδη λίστα, με τα παιδιά αμέσως μετά τον γονέα τους. */
const plFlat = tree => {
  const out = [];
  (tree || []).forEach(p => { out.push(p); (p.kids || []).forEach(k => out.push(k)); });
  return out;
};

/* ΜΙΑ ΕΙΔΙΚΟΤΗΤΑ ΤΟΥ ΑΝΘΡΩΠΟΥ, ως ετικέτα.
   Ο πίνακας «όλοι επί όλα» δεν αντέχει: 18 προϊόντα με υποκατηγορίες κάνουν
   σαράντα στήλες, και οι 36 στις 40 είναι κενές. Δείχνουμε ΜΟΝΟ ό,τι ισχύει —
   οι περισσότεροι ξέρουν δύο-τρία πράγματα, οπότε η γραμμή μένει κοντή. */
const plChip = (pid, lv, full, canEdit) => `<span class="pl-chip" data-sk="${pid}"
  style="--c:${PL_LV[lv][1]}">
  <button type="button" class="pl-chip-l" data-cyc="${pid}" data-lv="${lv}"${canEdit ? '' : ' disabled'}
    title="${esc(PL_LV[lv][0])} — κλικ για αλλαγή">${PL_LV[lv][2]}</button>
  <span class="pl-chip-n">${esc(full)}</span>
  ${canEdit ? `<button type="button" class="pl-chip-x" data-rm="${pid}" title="Αφαίρεση">✕</button>` : ''}
</span>`;

const plPill = (txt, color, title) =>
  `<span class="pl-pill" style="--c:${color}"${title ? ` title="${esc(title)}"` : ''}>${esc(txt)}</span>`;

/* ═══════════════════ 1. ΡΟΛΟΙ & ΕΙΔΙΚΟΤΗΤΕΣ ═══════════════════ */

const PL_F = {
  pool: {label: 'Μόνο όσοι παίζουν', bool: 1},
  mode: {label: 'Χαρακτήρας', opts: d => Object.keys(d.modes || {}).map(k => [k, d.modes[k]])},
  q:    {label: 'Αναζήτηση', text: 1, id: 'plQ', ph: 'όνομα, ομάδα…', w: 190},
};

R.roles = async function () {
  if (!cnpCan('hr.roles')) {
    setTop('Ρόλοι & ειδικότητες');
    $('#content').innerHTML = cnpDenied({message: 'Χρειάζεται «Προσωπικό → Ρόλοι & ειδικότητες»'});
    return;
  }
  const st = R.roles._s = R.roles._s || {view: 'now', pool: 0, mode: '', q: '', shown: []};
  setTop('Ρόλοι & ειδικότητες', st.view === 'now'
    ? 'ποια ειδικότητα κρέμεται από έναν άνθρωπο'
    : 'ο χάρτης: ποιος ξέρει τι, και πόσο');
  const c = $('#content');
  Object.keys(PL_F).forEach(k => { if (st[k] && !st.shown.includes(k)) { st.shown.push(k); } });
  cnpSkel(c, '<div class="skel" style="height:70px;margin-bottom:14px"></div><div class="skel" style="height:420px"></div>');

  const [d, cov] = await Promise.all([
    api('roles').catch(() => null),
    api('roles_coverage').catch(() => ({rows: []})),
  ]);
  if (!d) { c.innerHTML = '<div class="card"><div class="card-b mut">Δεν φορτώθηκε.</div></div>'; return; }

  const canEdit = cnpCan('hr.roles.edit');
  const prods = d.products || [];
  const flat  = plFlat(prods);
  let rows = d.rows || [];
  if (st.pool) { rows = rows.filter(r => r.in_pool); }
  if (st.mode) { rows = rows.filter(r => r.day_mode === st.mode); }
  if (st.q) {
    const q = st.q.toLowerCase();
    rows = rows.filter(r => (r.name || '').toLowerCase().includes(q)
      || (r.teams || []).some(t => (t.team || '').toLowerCase().includes(q)));
  }
  const inPool = (d.rows || []).filter(r => r.in_pool);
  const gaps = (cov.rows || []).filter(r => r.risk === 'none' || r.risk === 'single');

  const bar = `
  <div class="fbar">
    ${st.shown.map(k => {
      const F = PL_F[k];
      if (F.bool) { return `<button type="button" class="fchip fchip-b${st[k] ? ' on' : ''}" data-fb="${k}">${
        st[k] ? '✓ ' : ''}${esc(F.label)}<span class="fchip-x" data-fx="${k}" title="Αφαίρεση">✕</span></button>`; }
      if (F.text) { return fChip(F.label, `<input class="fchip-s" id="${F.id}" data-fk="${k}" value="${esc(st[k] || '')}"
        placeholder="${esc(F.ph)}" style="width:${F.w}px">`, !!st[k], k); }
      return fChip(F.label, fSel(k, F.opts(d), st[k]), !!st[k], k);
    }).join('')}${fAdd(PL_F, st.shown)}
    <span class="fbar-sp"></span>
    <button class="fchip${st.view === 'map' ? ' on' : ''}" id="plMap">${
      st.view === 'map' ? '← Κάλυψη' : 'Ο χάρτης «ποιος ξέρει τι»'}</button>
  </div>`;

  /* ── Η ΑΠΟΦΑΣΗ: τι κρέμεται από έναν άνθρωπο ── */
  const nowView = () => `
  <div class="pl-tiles">
    <div class="pl-tile"><b>${inPool.length}</b><span>παίζουν στη δεξαμενή</span></div>
    <div class="pl-tile${gaps.length ? ' bad' : ''}"><b>${gaps.length}</b><span>ειδικότητες σε κίνδυνο</span></div>
    <div class="pl-tile"><b>${(cov.rows || []).length}</b><span>ειδικότητες συνολικά</span></div>
  </div>

  <div class="card">
    <div class="card-h"><b>Πού κρέμεται η δουλειά</b>
      <span class="mut"> — ανά ειδικότητα, ποιος την ξέρει από όσους παίζουν</span></div>
    <div class="card-b">
      ${!(cov.rows || []).length ? '<div class="mut">Δεν υπάρχουν ενεργές ειδικότητες.</div>' : `
      <table class="tbl pl-cov">
        <thead><tr><th>Ειδικότητα</th><th>Κάλυψη</th><th>Κύριοι</th><th>Μπορούν</th><th>Μαθαίνουν</th></tr></thead>
        <tbody>${(cov.rows || []).map(r => {
          const [lbl, col, why] = PL_RISK[r.risk] || PL_RISK.ok;
          const nm = a => a.length ? a.map(x => esc(x.name)).join(', ') : '<span class="mut">—</span>';
          return `<tr>
            <td data-l="Ειδικότητα" class="${r.parent_id ? 'pl-kid' : ''}"><span class="pl-dot"
              style="background:${esc(r.color)}"></span>${esc(r.name)}</td>
            <td data-l="Κάλυψη">${plPill(lbl, col, why)}</td>
            <td data-l="Κύριοι">${nm(r.main)}</td>
            <td data-l="Μπορούν">${nm(r.can)}</td>
            <td data-l="Μαθαίνουν">${nm(r.learn)}</td>
          </tr>`;
        }).join('')}</tbody>
      </table>`}
    </div>
  </div>`;

  /* ── Ο ΧΑΡΤΗΣ: η κάρτα του καθενός + τα κελιά ── */
  const mapView = () => `
  <div class="card">
    <div class="card-h"><b>Ο χάρτης</b>
      <span class="mut"> — η κάρτα κάθε χειριστή και τι ξέρει${
        canEdit ? '' : ' (μόνο ανάγνωση)'}</span></div>
    <div class="card-b" style="overflow-x:auto">
      ${!rows.length ? '<div class="mut">Κανένας χειριστής με αυτά τα φίλτρα.</div>' : `
      <table class="tbl pl-map">
        <thead><tr>
          <th class="pl-nm">Χειριστής</th>
          <th>Παίζει</th><th>Χαρακτήρας</th><th>Ώρες</th><th>Μικρό ως</th>
          <th class="pl-sk">Ειδικότητες</th>
        </tr></thead>
        <tbody>${rows.map(r => `<tr data-a="${r.admin_id}">
          <td class="pl-nm" data-l="Χειριστής"><b>${esc(r.name)}</b>${
            (r.teams || []).length ? `<div class="mut sm">${
              r.teams.map(t => esc(t.team) + (t.leader ? ' ★' : '')).join(' · ')}</div>` : ''}</td>
          <td data-l="Παίζει"><label class="pl-sw"><input type="checkbox" data-k="in_pool"${
            r.in_pool ? ' checked' : ''}${canEdit ? '' : ' disabled'}><span></span></label></td>
          <td data-l="Χαρακτήρας">
            <select data-k="day_mode" class="pl-in"${canEdit ? '' : ' disabled'}>${
              Object.keys(d.modes).map(k => `<option value="${k}"${
                r.day_mode === k ? ' selected' : ''}>${esc(d.modes[k])}</option>`).join('')}</select>
            <input type="time" data-k="deep_from" class="pl-in pl-df" value="${esc(r.deep_from)}"${
              r.day_mode === 'mixed' ? '' : ' hidden'}${canEdit ? '' : ' disabled'}
              title="από πότε δουλεύει σε βάθος">
          </td>
          <td data-l="Ώρες"><input type="number" data-k="hours_day" class="pl-in pl-num" min="0.5" max="12"
            step="0.5" value="${r.hours_day}"${canEdit ? '' : ' disabled'} title="ωφέλιμες ώρες την ημέρα"></td>
          <td data-l="Μικρό ως"><input type="number" data-k="small_min" class="pl-in pl-num" min="5" max="480"
            step="5" value="${r.small_min}"${canEdit ? '' : ' disabled'} title="λεπτά — τι θεωρείται μικρό"></td>
          <td class="pl-sk" data-l="Ειδικότητες">
            <div class="pl-chips">${
              flat.filter(p => (r.skills || {})[p.id])
                  .map(p => plChip(p.id, r.skills[p.id], p.full, canEdit)).join('')
              || '<span class="mut sm">καμία — δεν θα του δοθεί τίποτα</span>'}
            </div>
            ${canEdit ? `<select class="pl-add" data-add="${r.admin_id}">
              <option value="">＋ προσθήκη ειδικότητας…</option>
              ${(prods || []).map(p => {
                const kids = (p.kids || []).filter(k => !(r.skills || {})[k.id]);
                const self = !(r.skills || {})[p.id];
                if (!self && !kids.length) { return ''; }
                /* Ο γονέας και τα παιδιά του μαζί: «ξέρει PharmacyOne» είναι
                   άλλο από «ξέρει μόνο τη συνταγογράφηση». */
                return `<optgroup label="${esc(p.name)}">
                  ${self ? `<option value="${p.id}">${esc(p.name)} — όλο</option>` : ''}
                  ${kids.map(k => `<option value="${k.id}">${esc(k.name)}</option>`).join('')}
                </optgroup>`;
              }).join('')}
            </select>` : ''}
          </td>
        </tr>`).join('')}</tbody>
      </table>`}
    </div>
  </div>
  <div class="pl-legend">
    <span class="mut">Κλικ στο γράμμα αλλάζει βαθμό, το ✕ αφαιρεί.</span>
    ${Object.keys(PL_LV).map(k => plPill(PL_LV[k][2] + ' · ' + PL_LV[k][0], PL_LV[k][1])).join(' ')}
    <span class="mut">Το «Μαθαίνει» δεν σερβίρεται αυτόματα — το δίνει ο επικεφαλής με το χέρι.</span>
  </div>`;

  c.innerHTML = bar + (st.view === 'map' ? mapView() : nowView());
  fWire(st, PL_F, () => R.roles());
  const map = $('#plMap');
  if (map) { map.onclick = () => { st.view = st.view === 'map' ? 'now' : 'map'; R.roles(); }; }

  if (st.view !== 'map' || !canEdit) { return; }

  /* Η αποθήκευση είναι ανά κελί, χωρίς κουμπί: ο χάρτης γεμίζεται σε μία
     καθιστή και δεν έχει νόημα να χάνεται επειδή ξέχασε κανείς «Αποθήκευση». */
  const saveCard = async tr => {
    const a = +tr.dataset.a;
    const g = k => tr.querySelector(`[data-k="${k}"]`);
    const body = {
      admin_id: a,
      in_pool: g('in_pool').checked ? 1 : 0,
      day_mode: g('day_mode').value,
      deep_from: g('deep_from').value,
      hours_day: g('hours_day').value,
      small_min: g('small_min').value,
    };
    const r = await api('role_save', body).catch(() => null);
    if (!r || r.error) { toast(r && r.error ? r.error : 'Δεν αποθηκεύτηκε', 'bad'); return; }
    g('deep_from').hidden = body.day_mode !== 'mixed';
    tr.classList.add('pl-ok');
    setTimeout(() => tr.classList.remove('pl-ok'), 700);
  };

  $$('#content .pl-map tbody tr').forEach(tr => {
    tr.querySelectorAll('[data-k]').forEach(el => { el.onchange = () => saveCard(tr); });
    const aid = +tr.dataset.a;
    /* Γράφει, και μόνο αν πετύχει αλλάζει η οθόνη: ο χάρτης δεν επιτρέπεται να
       δείχνει κάτι που δεν αποθηκεύτηκε — πάνω του θα στηθεί η μοιρασιά. */
    const put = async (pid, lv) => {
      const r = await api('skill_save', {admin_id: aid, product_id: pid, level: lv}).catch(() => null);
      if (!r || r.error) { toast(r && r.error ? r.error : 'Δεν αποθηκεύτηκε', 'bad'); return false; }
      return true;
    };

    /* Κλικ στο γράμμα: Κύριος → Μπορεί → Μαθαίνει → Κύριος. Η αφαίρεση έχει
       δικό της κουμπί — δεν κρύβεται μέσα στον κύκλο. */
    tr.querySelectorAll('[data-cyc]').forEach(btn => {
      btn.onclick = async () => {
        const order = ['main', 'can', 'learn'];
        const was = btn.dataset.lv;
        const lv = order[(order.indexOf(was) + 1) % order.length];
        if (!await put(+btn.dataset.cyc, lv)) { return; }
        btn.dataset.lv = lv;
        btn.textContent = PL_LV[lv][2];
        btn.title = PL_LV[lv][0] + ' — κλικ για αλλαγή';
        btn.closest('.pl-chip').style.setProperty('--c', PL_LV[lv][1]);
      };
    });

    tr.querySelectorAll('[data-rm]').forEach(btn => {
      btn.onclick = async () => {
        if (!await put(+btn.dataset.rm, '')) { return; }
        R.roles();
      };
    });

    const add = tr.querySelector('[data-add]');
    if (add) {
      add.onchange = async () => {
        const pid = +add.value;
        if (!pid) { return; }
        if (!await put(pid, 'main')) { add.value = ''; return; }
        R.roles();
      };
    }
  });
};

/* ═══════════════════ 2. ΟΙ ΧΕΙΡΙΣΤΕΣ ΣΗΜΕΡΑ ═══════════════════ */

const PD_F = {
  date: {label: 'Ημερομηνία', date: 1, id: 'pdDate'},
  at:   {label: 'Ώρα', time: 1, id: 'pdAt'},
};

R.pool = async function () {
  if (!cnpCan('reports.pool')) {
    setTop('Οι χειριστές σήμερα');
    $('#content').innerHTML = cnpDenied({message: 'Χρειάζεται «Αναφορές → Οι χειριστές σήμερα»'});
    return;
  }
  const st = R.pool._s = R.pool._s || {date: '', at: '', shown: []};
  setTop('Οι χειριστές σήμερα', 'ποιος παίζει, τι κρατάει, τι θα του σερβίραμε');
  const c = $('#content');
  Object.keys(PD_F).forEach(k => { if (st[k] && !st.shown.includes(k)) { st.shown.push(k); } });
  cnpSkel(c, '<div class="skel" style="height:70px;margin-bottom:14px"></div><div class="skel" style="height:420px"></div>');

  const d = await api('pool_today', {date: st.date, at: st.at}).catch(() => null);
  if (!d) { c.innerHTML = '<div class="card"><div class="card-b mut">Δεν φορτώθηκε.</div></div>'; return; }

  const rows = d.rows || [];
  const free = rows.filter(r => !r.away && !r.holding).length;
  const away = rows.filter(r => r.away).length;

  const bar = `
  <div class="fbar">
    ${st.shown.map(k => {
      const F = PD_F[k];
      const t = F.time ? 'time' : 'date';
      return fChip(F.label, `<input type="${t}" class="fchip-s" id="${F.id}" data-fk="${k}"
        value="${esc(st[k] || (F.time ? d.at : d.date))}">`, !!st[k], k);
    }).join('')}${fAdd(PD_F, st.shown)}
    <span class="fbar-sp"></span>
    <span class="mut sm">καθρέφτης — δείχνει, δεν αναθέτει</span>
  </div>`;

  c.innerHTML = bar + `
  <div class="pl-tiles">
    <div class="pl-tile"><b>${rows.length}</b><span>παίζουν σήμερα</span></div>
    <div class="pl-tile"><b>${free}</b><span>ελεύθεροι τώρα</span></div>
    <div class="pl-tile"><b>${away}</b><span>λείπουν</span></div>
    <div class="pl-tile"><b>${d.pool}</b><span>στη δεξαμενή</span></div>
    <div class="pl-tile${d.unlabelled ? ' warn' : ''}"><b>${d.unlabelled}</b><span>χωρίς ειδικότητα</span></div>
  </div>

  ${!rows.length ? `<div class="card"><div class="card-b mut">
    Κανένας χειριστής δεν έχει μπει ακόμη στη δεξαμενή.
    ${cnpCan('hr.roles') ? 'Άνοιξε «Προσωπικό → Ρόλοι &amp; ειδικότητες» και δήλωσε ποιος παίζει.' : ''}
  </div></div>` : `
  <div class="card">
    <div class="card-b">
      <table class="tbl pl-day">
        <thead><tr><th>Χειριστής</th><th>Χαρακτήρας</th><th>Η μέρα του</th>
          <th>Κρατάει τώρα</th><th>Σειρά</th></tr></thead>
        <tbody>${rows.map(r => {
          const pct = r.cap ? Math.min(100, Math.round(r.booked * 100 / r.cap)) : 0;
          const full = r.cap && r.booked >= r.cap;
          return `<tr${r.away ? ' class="pl-away"' : ''}>
            <td data-l="Χειριστής"><b>${esc(r.name)}</b>${
              r.skills ? '' : ' <span class="mut sm" title="δεν έχει δηλωθεί καμία ειδικότητα">χωρίς χάρτη</span>'}</td>
            <td data-l="Χαρακτήρας">${esc(r.mode_lbl)}${
              r.mode === 'mixed' ? `<div class="mut sm">βάθος από ${esc(r.deep_from)}</div>` : ''}</td>
            <td data-l="Η μέρα του">${r.away
              ? plPill(r.away, 'var(--mut, #64748b)')
              : `<div class="pl-bar${full ? ' full' : ''}"><i style="width:${pct}%"></i></div>
                 <span class="mut sm">${plHm(r.booked)} / ${plHm(r.cap)}</span>`}</td>
            <td data-l="Κρατάει τώρα">${r.holding
              ? `<a href="#/task/${r.holding.id}">#${r.holding.id} ${esc(r.holding.title)}</a>${
                  r.holding.product ? `<div class="mut sm">${esc(r.holding.product)}</div>` : ''}`
              : '<span class="mut">—</span>'}</td>
            <td data-l="Σειρά">${r.away ? '<span class="mut">—</span>'
              : (r.queue || []).length
                /* Η σειρά υπολογίζεται πάντα· το αν δίνεται ΤΩΡΑ είναι άλλο.
                   Όσο κρατάει μπάλα δεν παίρνει δεύτερη — μία κάθε φορά. */
                ? `<div class="mut sm pl-qh">${r.serving
                    ? 'θα του δινόταν τώρα' : 'τα επόμενα — κρατάει ήδη μία'}</div>
                   <ol class="pl-q${r.serving ? '' : ' next'}">${r.queue.map(t => `<li>
                    <a href="#/task/${t.id}">#${t.id} ${esc(t.title)}</a>
                    ${plPill(PL_LV[t.level][0], PL_LV[t.level][1], t.why)}
                    <span class="mut sm">${esc(t.product)}${t.minutes ? ' · ' + plHm(t.minutes) : ''}</span>
                  </li>`).join('')}</ol>`
                : `<span class="mut">${full ? 'γεμάτος' : 'τίποτα δεν ταιριάζει τώρα'}</span>`}</td>
          </tr>`;
        }).join('')}</tbody>
      </table>
    </div>
  </div>`}

  ${d.unlabelled ? `<div class="card pl-note">
    <div class="card-b"><b>${d.unlabelled}</b> εργασίες στη δεξαμενή δεν λένε <b>ποια ειδικότητα</b> ζητούν,
    οπότε ο μηχανισμός δεν ξέρει σε ποιον ταιριάζουν. Χωρίς προϊόν, μια εργασία δεν δρομολογείται.
    ${cnpCan('projects.board.edit')
      ? '<div style="margin-top:8px"><button class="btn sm" id="plLabel">Συμπλήρωσε όσες προκύπτουν μόνες τους</button></div>'
      : ''}</div>
  </div>` : ''}`;

  fWire(st, PD_F, () => R.pool());

  /* Η συμπλήρωση γίνεται σε δύο χρόνους: πρώτα λέει ΤΙ θα κάνει και από πού το
     βγάζει, και μόνο αν το εγκρίνεις γράφει. Λάθος ειδικότητα στέλνει τη δουλειά
     σε λάθος άνθρωπο — χειρότερο από το να λείπει. */
  const lab = $('#plLabel');
  if (lab) {
    lab.onclick = async () => {
      lab.disabled = true;
      const dry = await api('tasks_label').catch(() => null);
      lab.disabled = false;
      if (!dry || dry.error) { toast(dry && dry.error ? dry.error : 'Δεν έγινε', 'bad'); return; }
      if (!dry.n) { toast('Καμία εργασία δεν παίρνει ειδικότητα χωρίς αμφιβολία', 'warn'); return; }
      const ok = await cnpConfirm(
        `Θα μπει ειδικότητα σε <b>${dry.n}</b> εργασίες:<ul>
          <li><b>${dry.ticket}</b> από το ticket — το είπε ο ίδιος ο πελάτης</li>
          <li><b>${dry.project}</b> από το έργο τους</li>
          <li><b>${dry.client}</b> από τον πελάτη, όπου έχει <b>ένα μόνο</b> προϊόν</li>
          </ul>Όσες μένουν αμφίβολες δεν αγγίζονται.`,
        {title: 'Συμπλήρωση ειδικότητας', ok: 'Συμπλήρωσε', cancel: 'Άκυρο'});
      if (!ok) { return; }
      const r = await api('tasks_label', {apply: 1}).catch(() => null);
      if (!r || r.error) { toast(r && r.error ? r.error : 'Δεν αποθηκεύτηκε', 'bad'); return; }
      toast(`Μπήκε ειδικότητα σε ${r.n} εργασίες`, 'ok');
      R.pool();
    };
  }
};
