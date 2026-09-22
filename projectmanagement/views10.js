/* ═══════════ ΑΔΕΙΕΣ ΠΡΟΣΩΠΙΚΟΥ ═══════════
   Δύο οθόνες, δύο διαφορετικές ερωτήσεις:
     «Οι άδειές μου»  → πόσες μου μένουν και τι έχω ζητήσει (προσωπική, χωρίς cap)
     «Άδειες»         → ποιος δικαιούται πόσες, τι κατανάλωσε, τι του μένει (hr.leave)

   ΤΙ ΓΡΑΦΕΤΑΙ ΚΑΙ ΤΙ ΥΠΟΛΟΓΙΖΕΤΑΙ: ο χειριστής περνά ΜΟΝΟ τις δικαιούμενες
   ημέρες και τη μεταφορά. Το «πήρε» και το «μένουν» ΔΕΝ είναι πεδία — βγαίνουν
   από τις εγγραφές. Στο Excel ήταν κελιά, και γι' αυτό ξέφευγαν.

   Τηρεί το docs/UI-STANDARD.md: τίτλος → fbar → πλακίδια → κάρτες → κενή
   κατάσταση με το κουμπί που τη λύνει. */
'use strict';
const {S, api, esc, toast, setTop, cnpConfirm, cnpDialog, cnpDenied, cnpCan, I,
  cnpSearch, cnpSkel, fChip, fSel, fAdd, fWire, $, $$} = window.CNP;
const R = window.R;

/* Αριθμός ημερών όπως τον λέει άνθρωπος: 12 και 12,5 — όχι 12.00. */
const lvN = n => {
  const x = Math.round((+n || 0) * 100) / 100;
  return Number.isInteger(x) ? String(x) : String(x).replace('.', ',');
};
const lvDate = s => s ? s.slice(8, 10) + '/' + s.slice(5, 7) + '/' + s.slice(0, 4) : '';

/* Η δήλωση ΕΡΓΑΝΗ για τις άδειες ενός έτους γίνεται τον ΙΑΝΟΥΑΡΙΟ του επόμενου.
   Μέχρι τότε δεν «λείπει» — εκκρεμεί. Κόκκινο μόνο όταν πέρασε η προθεσμία,
   αλλιώς η οθόνη ουρλιάζει για κάτι που δεν έχει καν ωριμάσει. */
const lvErgLate = year => new Date() > new Date((+year + 1) + '-01-31T23:59:59');

/* Η φάση που βλέπει ο χρήστης. «Ελήφθη» δεν το πατάει κανείς — το βάζει ο χρόνος. */
const LV_PH = {
  requested: ['Ζητήθηκε', 'var(--warn, #b45309)'],
  upcoming:  ['Εγκρίθηκε', 'var(--brand)'],
  running:   ['Σε εξέλιξη', 'var(--ok, #15803d)'],
  taken:     ['Ελήφθη', 'var(--mut, #64748b)'],
  rejected:  ['Απορρίφθηκε', 'var(--bad, #b91c1c)'],
  cancelled: ['Ακυρώθηκε', 'var(--mut, #64748b)'],
};
const lvPill = p => {
  const d = LV_PH[p] || ['—', 'var(--mut,#64748b)'];
  return `<span class="lv-pill" style="color:${d[1]};border-color:${d[1]}33;background:${d[1]}14">${d[0]}</span>`;
};
const lvTile = (n, l, col, title) =>
  `<div class="su-stat"${title ? ` title="${esc(title)}"` : ''}><div>
    <div class="n"${col ? ` style="color:${col}"` : ''}>${n}</div><div class="l">${esc(l)}</div></div></div>`;

/* Διαβάζει τα πεδία ΠΡΙΝ ο διάλογος αυτοκαταστραφεί: ο cnpDialog επιστρέφει
   μόνο true/false, οπότε πιάνουμε το κλικ στη φάση capture. */
function lvForm(opts, read) {
  const p = cnpDialog(opts);
  let snap = null;
  const ok = document.getElementById('cnpDlgOk');
  if (ok) { ok.addEventListener('click', () => { snap = read(); }, true); }
  /* Το τρίτο κουμπί (διαγραφή) γυρίζει σκέτο 'third' — δεν διαβάζει πεδία. */
  return p.then(v => (v === 'third' ? 'third' : (v ? snap : null)));
}

const lvTypeOpts = (types, sel) => (types || []).map(t =>
  `<option value="${t.code}" ${t.code === sel ? 'selected' : ''}>${esc(t.label)}${
    t.deducts ? '' : ' (δεν χρεώνει υπόλοιπο)'}</option>`).join('');

/* ───────────────── Η καρτέλα μιας άδειας ───────────────── */

async function lvEditLeave(row, ctx) {
  const types = ctx.types || [];
  const isNew = !row;
  const r = row || {type: 'ANNUAL', from: '', to: '', days: '', year: '', ergani: '', note: '', flag: ''};
  const body = `
    <div class="lv-form">
      ${ctx.staffPick ? `<label>Εργαζόμενος
        <select class="inp" id="lvStaff">${(ctx.staff || []).map(s =>
          `<option value="${s.staffId}" ${s.staffId === (r.staffId || ctx.staffId) ? 'selected' : ''}>${esc(s.name)}</option>`).join('')}</select></label>` : ''}
      <label>Τύπος<select class="inp" id="lvType">${lvTypeOpts(types, r.type)}</select></label>
      <div class="lv-2">
        <label>Από<input class="inp" type="date" id="lvFrom" value="${esc(r.from || '')}"></label>
        <label>Έως<input class="inp" type="date" id="lvTo" value="${esc(r.to || '')}"></label>
      </div>
      <div class="lv-2">
        <label>Ημέρες που χρεώνονται
          <input class="inp" type="number" step="0.5" min="0" id="lvDays" value="${esc(String(r.days ?? ''))}" placeholder="αυτόματα"></label>
        <label>Έτος δικαιώματος
          <input class="inp" type="number" id="lvYear" value="${esc(String(r.year || ''))}" placeholder="αυτόματα"></label>
      </div>
      <label>Δήλωση ΕΡΓΑΝΗ<input class="inp" id="lvErg" value="${esc(r.ergani || '')}" placeholder="π.χ. 20/3-27/3/2026"></label>
      <label>Σημείωση<input class="inp" id="lvNote" value="${esc(r.note || '')}"></label>
    </div>
    <div class="mut" style="font-size:11.5px;margin-top:10px">
      Οι ημέρες <b>δεν</b> βγαίνουν μόνες τους από τις ημερομηνίες — μεσολαβούν αργίες και
      Σαββατοκύριακα. Άφησέ το κενό για να προταθούν οι εργάσιμες.<br>
      Το <b>έτος δικαιώματος</b> δεν είναι το έτος της ημερομηνίας: άδεια του 2025 λαμβάνεται
      νόμιμα ως τις 31/3/2026.</div>`;

  const vals = await lvForm({
    title: isNew ? 'Νέα άδεια' : 'Άδεια — ' + (r.name || ''),
    body, ok: isNew ? 'Καταχώριση' : 'Αποθήκευση', cancel: 'Άκυρο',
    /* Η διαγραφή ζει ΜΕΣΑ στην καρτέλα, όχι σε κάθε γραμμή της λίστας: μια
       κόκκινη στήλη σε πενήντα γραμμές τραβά το μάτι πάνω από τα νούμερα, και
       τα νούμερα είναι ο λόγος που υπάρχει η οθόνη. */
    third: (!isNew && ctx.canDel) ? 'Διαγραφή' : null,
  }, () => ({
    staffId: ctx.staffPick ? +($('#lvStaff') || {}).value : (r.staffId || ctx.staffId),
    type: ($('#lvType') || {}).value, from: ($('#lvFrom') || {}).value,
    to: ($('#lvTo') || {}).value, days: ($('#lvDays') || {}).value,
    year: ($('#lvYear') || {}).value, ergani: ($('#lvErg') || {}).value,
    note: ($('#lvNote') || {}).value,
  }));
  if (!vals) { return false; }
  if (vals === 'third') {
    if (!await cnpConfirm(`Οριστική διαγραφή: ${esc(r.name || '')} — ${esc(r.span || '')} `
      + `(${lvN(r.days)} ημέρες). Το υπόλοιπο του έτους ${r.year} θα αυξηθεί ανάλογα και η `
      + 'εγγραφή θα φύγει και από το κοινό ημερολόγιο.', {ok: 'Διαγραφή', danger: true})) { return false; }
    try { await api('leave_delete', {id: row.id}); toast('Διαγράφηκε'); return true; }
    catch (e) { toast(e.message, 1); return false; }
  }
  if (!vals.from) { toast('Βάλε ημερομηνία έναρξης', 1); return false; }
  try {
    const res = await api('leave_save', Object.assign({id: row ? row.id : 0}, vals));
    toast(`Καταχωρήθηκε — ${lvN(res.days)} ημέρες στο έτος ${res.year}`);
    return true;
  } catch (e) { toast(e.message, 1); return false; }
}

/* ───────────────── Οι άδειές μου ───────────────── */

R.myleave = async function () {
  setTop('Οι άδειές μου', 'πόσες μου μένουν, τι έχω ζητήσει, τι λήγει');
  const c = $('#content');
  cnpSkel(c, '<div class="skel" style="height:70px;margin-bottom:14px"></div><div class="skel" style="height:360px"></div>');
  const d = await api('leave_me').catch(() => null);
  if (!d) { c.innerHTML = '<div class="card"><div class="card-b mut">Δεν φορτώθηκε.</div></div>'; return; }
  if (!d.enrolled) {
    c.innerHTML = `<div class="card"><div class="card-b"><div class="empty">
      <b>Δεν είσαι στο μητρώο αδειών.</b>
      <div class="mut" style="margin-top:6px">${esc(d.note || '')}</div></div></div></div>`;
    return;
  }
  const yNow = new Date().getFullYear();
  const cur = (d.years || []).find(y => y.year === yNow);
  const exp = d.expiring || [];

  c.innerHTML = `
  <div class="fbar">
    ${fChip('Υπόλοιπο', `<b style="padding:0 6px">${lvN(d.remaining)} ημέρες</b>`, false, '')}
    <span class="fbar-sp"></span>
    <button class="fchip fchip-go" id="lvAsk">${I.plus} Νέο αίτημα</button>
  </div>

  <div class="cl-tiles">
    ${lvTile(lvN(d.remaining), 'μένουν συνολικά', d.remaining > 0 ? 'var(--brand)' : '')}
    ${lvTile(lvN(cur ? cur.taken : 0), 'πήρα φέτος')}
    ${lvTile(lvN(cur ? cur.entitled : 0), 'δικαιούμαι φέτος')}
    ${lvTile(lvN(d.sickThisYear), 'αναρρωτικές φέτος', d.sickThisYear > 0 ? 'var(--warn,#b45309)' : '')}
  </div>

  ${exp.length ? `<div class="card lv-warn"><div class="card-b">
    <b>Προσοχή στην προθεσμία.</b>
    <div style="margin-top:6px;font-size:13px">${exp.map(e => e.left < 0
      ? `Οι <b>${lvN(e.days)}</b> ημέρες του <b>${e.year}</b> έπρεπε να είχαν ληφθεί ως ${lvDate(e.deadline)}.`
      : `<b>${lvN(e.days)}</b> ημέρες του <b>${e.year}</b> πρέπει να ληφθούν ως <b>${lvDate(e.deadline)}</b> (σε ${e.left} ημέρες).`
      ).join('<br>')}</div>
    <div class="mut" style="font-size:11.5px;margin-top:6px">Μετά την προθεσμία η άδεια δεν χάνεται, αλλά
      μετατρέπεται σε χρηματική αξίωση — δεν την παίρνεις ως ρεπό.</div>
  </div></div>` : ''}

  <div class="card"><div class="card-h">Ανά έτος δικαιώματος</div><div class="card-b" style="padding:0">
    <table class="lv-t"><thead><tr>
      <th>Έτος</th><th class="r">Δικαιούμαι</th><th class="r">Μεταφορά</th>
      <th class="r">Πήρα</th><th class="r">Μένουν</th><th>Προθεσμία</th></tr></thead>
    <tbody>${(d.years || []).slice().reverse().map(y => `<tr>
      <td><b>${y.year}</b></td>
      <td class="r">${lvN(y.entitled)}</td>
      <td class="r">${y.carried ? lvN(y.carried) : '<span class="mut">—</span>'}</td>
      <td class="r">${lvN(y.taken)}</td>
      <td class="r"><b style="color:${y.remaining > 0 ? 'var(--brand)' : 'var(--mut,#64748b)'}">${lvN(y.remaining)}</b></td>
      <td class="mut">${y.remaining > 0 ? lvDate(y.deadline) : ''}</td></tr>`).join('')}
    </tbody></table>
  </div></div>

  <div class="card"><div class="card-h">Οι άδειές μου</div><div class="card-b" style="padding:0">
    ${(d.rows || []).length ? `<table class="lv-t"><thead><tr>
        <th>Περίοδος</th><th>Τύπος</th><th class="r">Ημέρες</th><th>Έτος</th><th>Κατάσταση</th><th></th></tr></thead>
      <tbody>${d.rows.map(r => `<tr>
        <td>${esc(r.span)}</td>
        <td>${esc(r.typeLabel)}${r.deducts ? '' : ' <span class="mut">(δεν χρεώνει)</span>'}</td>
        <td class="r">${lvN(r.days)}</td>
        <td class="mut">${r.year}</td>
        <td>${lvPill(r.phase)}</td>
        <td class="r">${r.phase === 'requested'
          ? `<button class="btn btn-o btn-sm" data-lvw="${r.id}">Ανάκληση</button>` : ''}</td>
      </tr>`).join('')}</tbody></table>`
    : `<div class="empty">Καμία άδεια ακόμη.
        <div class="mut" style="margin-top:6px">Πάτα «Νέο αίτημα» για να ζητήσεις την πρώτη σου.</div></div>`}
  </div></div>`;

  $('#lvAsk').onclick = async () => {
    const ok = await lvAskDialog(d);
    if (ok) { R.myleave(); }
  };
  $$('[data-lvw]').forEach(b => b.onclick = async () => {
    if (!await cnpConfirm('Ανάκληση του αιτήματος; Θα σβηστεί και δεν θα φτάσει στον υπεύθυνο.',
      {ok: 'Ανάκληση', danger: true})) { return; }
    try { await api('leave_withdraw', {id: +b.dataset.lvw}); toast('Ανακλήθηκε'); R.myleave(); }
    catch (e) { toast(e.message, 1); }
  });
};

/* Το αίτημα του ίδιου του εργαζομένου: λιγότερα πεδία από την καταχώριση του
   γραφείου προσωπικού — δεν ορίζει ούτε έτος δικαιώματος ούτε ΕΡΓΑΝΗ. */
async function lvAskDialog(d) {
  const body = `
    <div class="lv-form">
      <label>Τύπος<select class="inp" id="lvType">
        <option value="ANNUAL">Κανονική άδεια</option>
        <option value="SICK">Ασθένεια</option>
        <option value="SCHOOL">Σχολική άδεια</option>
        <option value="PARENTAL">Γονική άδεια</option>
        <option value="MARRIAGE">Άδεια γάμου</option>
        <option value="BEREAVEMENT">Άδεια θανάτου</option>
        <option value="UNPAID">Άνευ αποδοχών</option>
      </select></label>
      <div class="lv-2">
        <label>Από<input class="inp" type="date" id="lvFrom"></label>
        <label>Έως<input class="inp" type="date" id="lvTo"></label>
      </div>
      <label>Ημέρες <span class="mut">(κενό = εργάσιμες της περιόδου)</span>
        <input class="inp" type="number" step="0.5" min="0" id="lvDays" placeholder="αυτόματα"></label>
      <label>Λόγος / σημείωση<input class="inp" id="lvNote" placeholder="προαιρετικό"></label>
    </div>
    <div class="mut" style="font-size:11.5px;margin-top:10px">
      Υπόλοιπο τώρα: <b>${lvN(d.remaining)}</b> ημέρες. Το αίτημα πηγαίνει για έγκριση —
      μέχρι τότε δεν εμφανίζεται στο κοινό ημερολόγιο.</div>`;
  const vals = await lvForm({title: 'Αίτημα άδειας', body, ok: 'Στείλε για έγκριση', cancel: 'Άκυρο'},
    () => ({type: ($('#lvType') || {}).value, from: ($('#lvFrom') || {}).value,
      to: ($('#lvTo') || {}).value, days: ($('#lvDays') || {}).value, note: ($('#lvNote') || {}).value}));
  if (!vals) { return false; }
  if (!vals.from) { toast('Βάλε ημερομηνία έναρξης', 1); return false; }
  try {
    const res = await api('leave_request', vals);
    toast(`Στάλθηκε — ${lvN(res.days)} ημέρες, έτος δικαιώματος ${res.year}`);
    return true;
  } catch (e) { toast(e.message, 1); return false; }
}

/* ───────────────── Άδειες (διαχείριση) ───────────────── */

const LV_F = {
  staffId: {label: 'Εργαζόμενος', opts: d => [['', '— όλοι —']]
              .concat((d.rows || []).map(r => [String(r.staffId), r.name]))},
  type:    {label: 'Τύπος', opts: d => [['', '— κάθε —']]
              .concat((d.types || []).map(t => [t.code, t.label]))},
  status:  {label: 'Κατάσταση', opts: () => [['', '— κάθε —'], ['requested', 'ζητήθηκε'],
              ['approved', 'εγκεκριμένη'], ['taken', 'ελήφθη'], ['rejected', 'απορρίφθηκε'],
              ['cancelled', 'ακυρωμένη']]},
  erganiMissing: {label: 'Λείπει από ΕΡΓΑΝΗ', bool: 1},
  q:       {label: 'Αναζήτηση', text: 1, id: 'lvQ', ph: 'σημείωση, περίοδος, ΕΡΓΑΝΗ…', w: 210},
};

R.leave = async function () {
  if (!cnpCan('hr.leave')) {
    setTop('Άδειες');
    $('#content').innerHTML = cnpDenied({message: 'Χρειάζεται «Προσωπικό → Άδειες προσωπικού»'});
    return;
  }
  setTop('Άδειες', 'ποιος δικαιούται πόσες, τι κατανάλωσε, τι του μένει');
  const c = $('#content');
  const st = R.leave._s = R.leave._s || {year: String(new Date().getFullYear()),
    staffId: '', type: '', status: '', erganiMissing: 0, q: '', shown: []};
  Object.keys(LV_F).forEach(k => { if (st[k] && !st.shown.includes(k)) { st.shown.push(k); } });
  cnpSkel(c, '<div class="skel" style="height:70px;margin-bottom:14px"></div><div class="skel" style="height:420px"></div>');

  const [d, l] = await Promise.all([
    api('leave_staff', {year: +st.year}).catch(() => null),
    api('leave_list', {year: +st.year, staffId: st.staffId, type: st.type,
      status: st.status, erganiMissing: st.erganiMissing, q: st.q}).catch(() => ({rows: []})),
  ]);
  if (!d) { c.innerHTML = '<div class="card"><div class="card-b mut">Δεν φορτώθηκε.</div></div>'; return; }
  R.leave._d = d;

  const canEdit = cnpCan('hr.leave.edit');
  const canOk   = cnpCan('hr.leave.approve');
  const canDel  = cnpCan('hr.leave.delete');
  const rows    = d.rows || [];
  const totRem  = rows.reduce((s, r) => s + r.remaining, 0);
  const pending = (l.rows || []).filter(r => r.phase === 'requested').length;
  const noErg   = (l.rows || []).filter(r => !r.ergani && lvErgLate(r.year)
                    && ['taken', 'upcoming', 'running'].includes(r.phase)).length;
  const sick    = rows.reduce((s, r) => s + r.sick, 0);
  const offLaw  = rows.filter(r => r.offLaw).length;

  c.innerHTML = `
  <div class="fbar">
    ${fChip('Έτος', fSel('year', (d.years || []).map(y => [String(y), String(y)]), st.year), false, '')}
    ${st.shown.map(k => {
      const F = LV_F[k];
      if (F.bool) { return `<button type="button" class="fchip fchip-b${st[k] ? ' on' : ''}" data-fb="${k}">${
        st[k] ? '✓ ' : ''}${esc(F.label)}<span class="fchip-x" data-fx="${k}" title="Αφαίρεση">✕</span></button>`; }
      if (F.text) { return fChip(F.label, `<input class="fchip-s" id="${F.id}" data-fk="${k}" value="${esc(st[k] || '')}"
        placeholder="${esc(F.ph)}" style="width:${F.w}px">`, !!st[k], k); }
      return fChip(F.label, fSel(k, F.opts(d), st[k]), !!st[k], k);
    }).join('')}
    ${fAdd(LV_F, st.shown)}
    <span class="fbar-sp"></span>
    <span class="fbar-note">${(l.rows || []).length} εγγραφές</span>
    ${canEdit ? `<button class="fchip" id="lvAddStaff">Εργαζόμενοι</button>
      <button class="fchip fchip-go" id="lvNew">${I.plus} Νέα καταχώριση</button>` : ''}
  </div>

  <div class="cl-tiles">
    ${lvTile(lvN(totRem), 'ημέρες μένουν συνολικά', 'var(--brand)')}
    ${lvTile(pending, 'αιτήματα προς έγκριση', pending ? 'var(--warn,#b45309)' : '')}
    ${lvTile(noErg, 'εκπρόθεσμες στην ΕΡΓΑΝΗ', noErg ? 'var(--bad,#b91c1c)' : '',
      'Ο εργοδότης δηλώνει κάθε Ιανουάριο τις άδειες του προηγούμενου έτους — αλλιώς πρόστιμο')}
    ${lvTile(lvN(sick), 'ημέρες ασθενείας', sick ? 'var(--warn,#b45309)' : '')}
    ${offLaw ? lvTile(offLaw, 'εκτός κλίμακας νόμου', 'var(--bad,#b91c1c)',
      'Οι δικαιούμενες ημέρες διαφέρουν από όσες προβλέπει η κλίμακα για την προϋπηρεσία τους') : ''}
  </div>

  <div class="card"><div class="card-h">Υπόλοιπα ${st.year}</div><div class="card-b" style="padding:0">
    <table class="lv-t"><thead><tr>
      <th>Εργαζόμενος</th><th class="r">Δικαιούται</th><th class="r">Μεταφορά</th>
      <th class="r">Πήρε</th><th class="r">Μένουν</th><th class="r">Ασθένεια</th>
      <th class="r">Λοιπές</th><th>Σύνολο υπολοίπου</th></tr></thead>
    <tbody>${rows.map(r => `<tr class="pick" data-lvs="${r.staffId}" role="button" tabindex="0">
      <td><b>${esc(r.name)}</b>${r.offLaw
        ? ` <span class="lv-warn-dot" title="Ο νόμος δίνει ${lvN(r.suggest)} — ${esc(r.suggestWhy)}">!</span>` : ''}</td>
      <td class="r">${lvN(r.entitled)}</td>
      <td class="r">${r.carried ? lvN(r.carried) : '<span class="mut">—</span>'}</td>
      <td class="r">${lvN(r.taken)}</td>
      <td class="r"><b style="color:${r.remaining > 0 ? 'var(--brand)' : 'var(--mut,#64748b)'}">${lvN(r.remaining)}</b></td>
      <td class="r">${r.sick ? lvN(r.sick) : '<span class="mut">—</span>'}</td>
      <td class="r">${r.other ? lvN(r.other) : '<span class="mut">—</span>'}</td>
      <td class="mut">${lvN(r.totalRemaining)} σε όλα τα έτη${
        (r.expiring || []).length ? ` · <span style="color:var(--bad,#b91c1c)">λήγουν ${lvN(r.expiring[0].days)}</span>` : ''}</td>
    </tr>`).join('')}</tbody></table>
  </div></div>

  <div class="card"><div class="card-h">Εγγραφές</div><div class="card-b" style="padding:0">
    ${(l.rows || []).length ? `<table class="lv-t"><thead><tr>
        <th>Εργαζόμενος</th><th>Περίοδος</th><th>Τύπος</th><th class="r">Ημέρες</th>
        <th>Έτος</th><th>Κατάσταση</th><th>ΕΡΓΑΝΗ</th><th></th></tr></thead>
      <tbody>${l.rows.map(r => `<tr>
        <td>${esc(r.name)}</td>
        <td>${esc(r.span)}</td>
        <td>${esc(r.typeLabel)}</td>
        <td class="r">${lvN(r.days)}</td>
        <td class="mut">${r.year}</td>
        <td>${lvPill(r.phase)}${r.flagLabel ? ` <span class="lv-flag">${esc(r.flagLabel)}</span>` : ''}</td>
        <td>${r.ergani ? esc(r.ergani)
          : (lvErgLate(r.year) ? '<span class="lv-miss">λείπει</span>'
             : '<span class="mut" title="Δηλώνεται τον Ιανουάριο του ' + (r.year + 1) + '">εκκρεμεί</span>')}</td>
        <td class="r" style="white-space:nowrap">
          ${canOk && r.phase === 'requested'
            ? `<button class="btn btn-p btn-sm" data-lvok="${r.id}">Έγκριση</button>
               <button class="btn btn-o btn-sm" data-lvno="${r.id}">Απόρριψη</button>` : ''}
          ${canEdit ? `<button class="btn btn-o btn-sm" data-lve="${r.id}">Άλλαξε</button>` : ''}
        </td></tr>`).join('')}</tbody></table>`
    : `<div class="empty">Καμία εγγραφή με αυτά τα φίλτρα.
        <div class="mut" style="margin-top:6px">Άλλαξε το έτος ή καθάρισε τα φίλτρα από τα ✕.</div></div>`}
  </div></div>`;

  /* Το έτος είναι μόνιμο φίλτρο, δεν περνά από το fWire (δεν έχει ✕). */
  { const ys = $('[data-fk="year"]'); if (ys) { ys.onchange = e => { st.year = e.target.value; R.leave(); }; } }
  fWire(st, LV_F, () => R.leave());
  cnpSearch('lvQ', v => { st.q = v; return R.leave(); }, 320);

  const nb = $('#lvNew');
  if (nb) { nb.onclick = async () => {
    if (await lvEditLeave(null, {types: d.types, staff: rows, staffPick: true, staffId: rows[0] && rows[0].staffId})) { R.leave(); }
  }; }
  const asb = $('#lvAddStaff');
  if (asb) { asb.onclick = async () => { if (await lvEnrol(rows)) { R.leave(); } }; }
  $$('[data-lvs]').forEach(el => el.onclick = () => lvPerson(+el.dataset.lvs));
  $$('[data-lve]').forEach(b => b.onclick = async e => {
    e.stopPropagation();
    const row = (l.rows || []).find(x => x.id === +b.dataset.lve);
    if (await lvEditLeave(row, {types: d.types, staff: rows, staffPick: true, canDel})) { R.leave(); }
  });
  $$('[data-lvok]').forEach(b => b.onclick = () => lvDecide(+b.dataset.lvok, 'approved'));
  $$('[data-lvno]').forEach(b => b.onclick = () => lvDecide(+b.dataset.lvno, 'rejected'));
};

async function lvDecide(id, decision) {
  try {
    await api('leave_decide', {id, decision});
    toast(decision === 'approved' ? 'Εγκρίθηκε — μπήκε στο ημερολόγιο' : 'Απορρίφθηκε');
    R.leave();
  } catch (e) {
    /* Υπέρβαση υπολοίπου: δεν την μπλοκάρουμε, τη ΛΕΜΕ. Υπάρχουν λόγοι να
       εγκρίνεις άδεια χωρίς υπόλοιπο — αλλά όχι κατά λάθος. */
    if (/force=1/.test(e.message)) {
      if (await cnpConfirm(e.message.replace(' Στείλε force=1 αν το εγκρίνεις εν γνώσει σου.', '')
        + ' Να εγκριθεί έτσι;', {ok: 'Έγκριση', danger: true})) {
        try { await api('leave_decide', {id, decision, force: 1}); toast('Εγκρίθηκε'); R.leave(); }
        catch (e2) { toast(e2.message, 1); }
      }
      return;
    }
    toast(e.message, 1);
  }
}

/* ───────────────── Η καρτέλα ενός εργαζομένου ───────────────── */

/**
 * Εδώ περνιούνται οι ΔΙΚΑΙΟΥΜΕΝΕΣ ημέρες και η μεταφορά, ανά έτος.
 *
 * Το «πήρε» και το «μένουν» είναι ΥΠΟΛΟΓΙΣΜΟΣ, όχι πεδία. Στο Excel ήταν κελιά
 * και γι' αυτό δύο έτη είχαν αθροίσματα που δεν έβγαιναν — εδώ δεν μπορούν να
 * ξεφύγουν, γιατί κανείς δεν τα γράφει.
 */
async function lvPerson(staffId) {
  let d = await api('leave_person', {staffId}).catch(() => null);
  if (!d) { toast('Δεν φορτώθηκε η καρτέλα', 1); return; }
  const canEdit = cnpCan('hr.leave.edit');
  const yNow = new Date().getFullYear();

  const draw = () => {
    const years = (d.years || []).slice().reverse();
    const rowsHtml = years.length ? years.map(y => `<tr>
        <td><b>${y.year}</b></td>
        <td class="r">${canEdit
          ? `<input class="inp lv-mini" type="number" step="0.5" min="0" data-lvent="${y.year}" value="${lvN(y.entitled)}">`
          : lvN(y.entitled)}</td>
        <td class="r">${canEdit
          ? `<input class="inp lv-mini" type="number" step="0.5" min="0" data-lvcar="${y.year}" value="${lvN(y.carried)}">`
          : (y.carried ? lvN(y.carried) : '<span class="mut">—</span>')}</td>
        <td class="r">${lvN(y.taken)}</td>
        <td class="r"><b style="color:${y.remaining > 0 ? 'var(--brand)' : 'var(--mut,#64748b)'}">${lvN(y.remaining)}</b></td>
        <td class="mut">${y.remaining > 0 ? lvDate(y.deadline) : ''}</td>
        <td class="r">${canEdit ? `<button class="btn btn-p btn-sm" data-lvsave="${y.year}">Αποθήκευση</button>` : ''}</td>
      </tr>`).join('')
      : '<tr><td colspan="7" class="mut" style="padding:12px">Κανένα έτος δικαιώματος ακόμη.</td></tr>';

    const sick = (d.rows || []).filter(r => r.type === 'SICK');
    const annual = (d.rows || []).filter(r => r.type === 'ANNUAL');
    const other = (d.rows || []).filter(r => r.type !== 'SICK' && r.type !== 'ANNUAL');

    const list = (t, arr) => arr.length ? `<div class="lv-sub">${t}</div>
      <table class="lv-t lv-t-sm"><tbody>${arr.map(r => `<tr>
        <td>${esc(r.span)}</td><td class="mut">${esc(r.typeLabel)}</td>
        <td class="r">${lvN(r.days)}</td><td class="mut">${r.year}</td>
        <td>${lvPill(r.phase)}</td>
        <td>${r.ergani ? '<span class="mut">ΕΡΓΑΝΗ ' + esc(r.ergani) + '</span>'
          : (lvErgLate(r.year) ? '<span class="lv-miss">χωρίς ΕΡΓΑΝΗ</span>' : '<span class="mut">εκκρεμεί</span>')}</td>
      </tr>`).join('')}</tbody></table>` : '';

    return `
      <div class="mut" style="font-size:12px;margin-bottom:10px">
        Πρόσληψη ${d.hire ? lvDate(d.hire) : '—'}${d.afm ? ' · ΑΦΜ ' + esc(d.afm) : ''}
        ${d.priorMonths ? ' · προϋπηρεσία ' + Math.round(d.priorMonths / 12) + ' έτη' : ''}
        · συνολικό υπόλοιπο <b>${lvN(d.remaining)}</b> ημέρες</div>
      ${(d.expiring || []).length ? `<div class="lv-inline-warn">
        ${d.expiring.map(e => e.left < 0
          ? `<b>${lvN(e.days)}</b> ημέρες του ${e.year} πέρασαν την προθεσμία (${lvDate(e.deadline)}) — γίνονται χρηματική αξίωση.`
          : `<b>${lvN(e.days)}</b> ημέρες του ${e.year} λήγουν ${lvDate(e.deadline)} (σε ${e.left} ημέρες).`).join('<br>')}
      </div>` : ''}
      <table class="lv-t lv-t-sm"><thead><tr>
        <th>Έτος</th><th class="r">Δικαιούται</th><th class="r">Μεταφορά</th>
        <th class="r">Πήρε</th><th class="r">Μένουν</th><th>Προθεσμία</th><th></th></tr></thead>
        <tbody>${rowsHtml}</tbody></table>
      <div class="mut" style="font-size:11.5px;margin:8px 0 14px">
        Γράφονται μόνο οι <b>δικαιούμενες</b> και η <b>μεταφορά</b>. Το «πήρε» και το «μένουν»
        προκύπτουν από τις εγγραφές — δεν είναι πεδία, γι' αυτό δεν ξεφεύγουν.</div>
      ${list('Κανονική άδεια', annual)}
      ${list('Αναρρωτικές', sick)}
      ${list('Λοιπές άδειες', other)}`;
  };

  const p = cnpDialog({title: d.name, body: '<div id="lvPersonBody"></div>', ok: 'Κλείσιμο', cancel: null});
  const host = document.getElementById('lvPersonBody');

  const paint = () => {
    if (!host) { return; }
    host.innerHTML = draw();
    host.querySelectorAll('[data-lvsave]').forEach(b => b.onclick = async () => {
      const y = +b.dataset.lvsave;
      const ent = host.querySelector(`[data-lvent="${y}"]`);
      const car = host.querySelector(`[data-lvcar="${y}"]`);
      try {
        await api('leave_year_save', {staffId, year: y, type: 'ANNUAL',
          entitled: ent ? ent.value : 0, carried: car ? car.value : 0});
        toast(`Αποθηκεύτηκε το ${y}`);
        d = await api('leave_person', {staffId});
        paint();
      } catch (e) { toast(e.message, 1); }
    });
  };
  paint();
  await p;
  if (R.leave._s) { R.leave(); }
}

/**
 * Μπαίνει κάποιος στο μητρώο αδειών.
 *
 * Η ΠΡΟΫΠΗΡΕΣΙΑ ΔΕΝ ΕΙΝΑΙ ΛΕΠΤΟΜΕΡΕΙΑ: δύο άνθρωποι με την ίδια ημερομηνία
 * πρόσληψης δικαιούνται διαφορετικές ημέρες αν ο ένας ήρθε με 25 χρόνια πίσω
 * του. Χωρίς αυτήν, η κλίμακα του νόμου βγάζει λάθος νούμερο.
 */
async function lvEnrol(existing) {
  const taken = new Set((existing || []).map(r => r.adminId));
  const free = (S.boot.admins || []).filter(a => !taken.has(a.id));
  if (!free.length) {
    toast('Όλοι οι χειριστές είναι ήδη στο μητρώο');
    return false;
  }
  const body = `
    <div class="lv-form">
      <label>Χειριστής<select class="inp" id="lvNewAdm">${free.map(a =>
        `<option value="${a.id}">${esc(a.name)}</option>`).join('')}</select></label>
      <div class="lv-2">
        <label>Ημερομηνία πρόσληψης<input class="inp" type="date" id="lvNewHire"></label>
        <label>ΑΦΜ<input class="inp" id="lvNewAfm" inputmode="numeric"></label>
      </div>
      <label>Προϋπηρεσία πριν από εμάς <span class="mut">(έτη)</span>
        <input class="inp" type="number" min="0" max="50" id="lvNewPrior" value="0"></label>
    </div>
    <div class="mut" style="font-size:11.5px;margin-top:10px">
      Η προϋπηρεσία καθορίζει πόσες ημέρες δικαιούται: με 12 έτη συνολικά πάει στις 25,
      με 25 έτη στις 26. Αν δεν την ξέρεις ακόμη, βάλε 0 και διόρθωσέ την όταν βρεθεί
      η σύμβαση — τα δικαιώματα ξαναϋπολογίζονται.</div>`;
  const vals = await lvForm({title: 'Προσθήκη στο μητρώο αδειών', body, ok: 'Προσθήκη', cancel: 'Άκυρο'},
    () => ({adminId: +($('#lvNewAdm') || {}).value,
      hire: ($('#lvNewHire') || {}).value, afm: ($('#lvNewAfm') || {}).value,
      priorMonths: Math.round((+($('#lvNewPrior') || {}).value || 0) * 12)}));
  if (!vals) { return false; }
  if (!vals.hire) { toast('Η ημερομηνία πρόσληψης είναι απαραίτητη — χωρίς αυτήν δεν υπολογίζεται δικαίωμα', 1); return false; }
  try { await api('leave_staff_save', vals); toast('Προστέθηκε στο μητρώο'); return true; }
  catch (e) { toast(e.message, 1); return false; }
}

window.R = R;
