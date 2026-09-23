/* ═══════════ ΑΔΕΙΕΣ ΠΡΟΣΩΠΙΚΟΥ ═══════════
   Δύο οθόνες, δύο διαφορετικές ερωτήσεις:
     «Οι άδειές μου»  → πόσες μου μένουν και τι έχω ζητήσει (προσωπική, χωρίς cap)
     «Άδειες»         → τι πρέπει να αποφασίσω και ποιος πού βρίσκεται (hr.leave)

   Η ΠΡΩΤΗ ΟΘΟΝΗ ΕΙΝΑΙ ΑΠΟΦΑΣΗ, ΟΧΙ ΑΡΧΕΙΟ. Δείχνει τι ζητάει έγκριση, τι λήγει
   και πόσες μένουν στον καθένα. Οι 322 εγγραφές είναι ιστορικό: ζουν ένα κλικ
   πιο μέσα (καρτέλα εργαζομένου) ή στη δική τους προβολή. Λίστα που δεν
   ζητάει τίποτα από εσένα δεν έχει λόγο να κάθεται πρώτη.

   ΤΙ ΓΡΑΦΕΤΑΙ ΚΑΙ ΤΙ ΥΠΟΛΟΓΙΖΕΤΑΙ: ο χειριστής περνά ΜΟΝΟ τις δικαιούμενες
   ημέρες και τη μεταφορά. Το «πήρε» και το «μένουν» ΔΕΝ είναι πεδία — βγαίνουν
   από τις εγγραφές. Στο Excel ήταν κελιά, και γι' αυτό ξέφευγαν.

   ΔΟΜΗ: κάθε φόρμα χωρίζεται σε ενότητες που απαντούν μία ερώτηση η καθεμία.
   Κάθε πίνακας δηλώνει `data-l` σε κάθε κελί ώστε στο κινητό να γίνεται κάρτα
   με ετικέτες αντί να κυλά πλάγια. */
'use strict';
const {S, api, esc, toast, setTop, cnpConfirm, cnpDenied, cnpCan, I,
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

/* Δομικά στοιχεία φόρμας — μία ενότητα, ένα πεδίο. */
const lvSec = (title, inner) => `<div class="lv-sec"><div class="lv-sec-h">${esc(title)}</div>${inner}</div>`;
const lvF = (label, inner) => `<label class="lv-f"><span>${esc(label)}</span>${inner}</label>`;

/**
 * Παράθυρο φόρμας.
 *
 * ΓΙΑΤΙ ΔΙΚΟ ΤΟΥ ΚΑΙ ΟΧΙ ο κοινός διάλογος: ο cnpDialog φτιάχτηκε για ερώτηση
 * μιας γραμμής και περιορίζει το σώμα σε 46vh με εσωτερικό scroll. Μια φόρμα
 * οκτώ πεδίων εκεί μέσα έκοβε το βοηθητικό κείμενο στη μέση και έκρυβε τα
 * κουμπιά. Εδώ κυλά ΜΟΝΟ το σώμα, τα κουμπιά μένουν πάντα ορατά, και στο
 * κινητό γίνεται φύλλο πλήρους οθόνης.
 *
 * @returns Promise<'ok'|'third'|null>
 */
function lvModal({title, body, ok = 'Αποθήκευση', cancel = 'Άκυρο', third = null}) {
  return new Promise(resolve => {
    const ovl = document.createElement('div');
    ovl.className = 'lv-ovl';
    ovl.innerHTML = `<div class="lv-modal" role="dialog" aria-modal="true" aria-label="${esc(title)}">
      <div class="lv-modal-h"><b>${esc(title)}</b>
        <button class="lv-x" data-lvclose aria-label="Κλείσιμο">✕</button></div>
      <div class="lv-modal-b">${body}</div>
      <div class="lv-modal-f">
        ${third ? `<button class="btn btn-o" data-lvthird style="color:var(--bad,#b91c1c)">${esc(third)}</button>` : ''}
        <span class="lv-sp"></span>
        ${cancel ? `<button class="btn btn-o" data-lvcancel>${esc(cancel)}</button>` : ''}
        <button class="btn btn-p" data-lvok>${esc(ok)}</button>
      </div></div>`;
    document.body.appendChild(ovl);
    const prev = lvModal._el;
    lvModal._el = ovl;
    const done = v => {
      ovl.remove(); document.removeEventListener('keydown', onKey);
      lvModal._el = prev || null; resolve(v);
    };
    const onKey = e => { if (e.key === 'Escape') { e.stopPropagation(); done(null); } };
    document.addEventListener('keydown', onKey);
    ovl.querySelector('[data-lvok]').onclick = () => done('ok');
    const cb = ovl.querySelector('[data-lvcancel]'); if (cb) { cb.onclick = () => done(null); }
    ovl.querySelector('[data-lvclose]').onclick = () => done(null);
    const tb = ovl.querySelector('[data-lvthird]'); if (tb) { tb.onclick = () => done('third'); }
    /* Κλικ στο φόντο κλείνει — αλλά ΟΧΙ κλικ μέσα στο παράθυρο. */
    ovl.onclick = e => { if (e.target === ovl) { done(null); } };
    const first = ovl.querySelector('input,select,textarea');
    setTimeout(() => { if (first) { first.focus(); } }, 40);
  });
}
/** Διαβάζει πεδίο του ανοιχτού παραθύρου. */
const lvV = id => {
  const el = lvModal._el && lvModal._el.querySelector('#' + id);
  return el ? el.value : '';
};

const lvTypeOpts = (types, sel) => (types || []).map(t =>
  `<option value="${t.code}" ${t.code === sel ? 'selected' : ''}>${esc(t.label)}${
    t.deducts ? '' : ' — δεν χρεώνει υπόλοιπο'}</option>`).join('');

/* ───────────────── Η καρτέλα μιας άδειας ───────────────── */

async function lvEditLeave(row, ctx) {
  const isNew = !row;
  const r = row || {type: 'ANNUAL', from: '', to: '', days: '', year: '', ergani: '', note: ''};
  const body =
    (ctx.staffPick ? lvSec('Ποιος', `<div class="lv-grid one">${lvF('Εργαζόμενος',
      `<select class="inp" id="lvStaff">${(ctx.staff || []).map(s =>
        `<option value="${s.staffId}" ${s.staffId === (r.staffId || ctx.staffId) ? 'selected' : ''}>${esc(s.name)}</option>`
      ).join('')}</select>`)}</div>`) : '')
    + lvSec('Τι άδεια', `<div class="lv-grid one">${lvF('Τύπος',
        `<select class="inp" id="lvType">${lvTypeOpts(ctx.types, r.type)}</select>`)}</div>`)
    + lvSec('Πότε', `<div class="lv-grid">
        ${lvF('Από', `<input class="inp" type="date" id="lvFrom" value="${esc(r.from || '')}">`)}
        ${lvF('Έως', `<input class="inp" type="date" id="lvTo" value="${esc(r.to || '')}">`)}
      </div>`)
    + lvSec('Πόσο χρεώνεται', `<div class="lv-grid">
        ${lvF('Ημέρες', `<input class="inp" type="number" step="0.5" min="0" id="lvDays"
            value="${esc(String(r.days == null ? '' : r.days))}" placeholder="αυτόματα">`)}
        ${lvF('Έτος δικαιώματος', `<input class="inp" type="number" id="lvYear"
            value="${esc(String(r.year || ''))}" placeholder="αυτόματα">`)}
      </div>
      <div class="lv-hint">Οι ημέρες <b>δεν</b> βγαίνουν μόνες τους από τις ημερομηνίες — μεσολαβούν
        αργίες και Σαββατοκύριακα. Άφησέ το κενό για να προταθούν οι εργάσιμες.<br>
        Το <b>έτος δικαιώματος</b> δεν είναι το έτος της ημερομηνίας: άδεια του 2025 λαμβάνεται
        νόμιμα ως τις 31/3/2026.</div>`)
    + lvSec('Δηλώσεις & σημειώσεις', `<div class="lv-grid">
        ${lvF('Δήλωση ΕΡΓΑΝΗ', `<input class="inp" id="lvErg" value="${esc(r.ergani || '')}"
            placeholder="π.χ. 20/3-27/3/2026">`)}
        ${lvF('Σημείωση', `<input class="inp" id="lvNote" value="${esc(r.note || '')}">`)}
      </div>`);

  const act = await lvModal({
    title: isNew ? 'Νέα άδεια' : 'Άδεια — ' + (r.name || ''),
    body, ok: isNew ? 'Καταχώριση' : 'Αποθήκευση',
    /* Η διαγραφή ζει ΜΕΣΑ στην καρτέλα, όχι σε κάθε γραμμή της λίστας: μια
       κόκκινη στήλη σε πενήντα γραμμές τραβά το μάτι πάνω από τα νούμερα. */
    third: (!isNew && ctx.canDel) ? 'Διαγραφή' : null,
  });
  if (!act) { return false; }

  if (act === 'third') {
    if (!await cnpConfirm(`Οριστική διαγραφή: ${esc(r.name || '')} — ${esc(r.span || '')} `
      + `(${lvN(r.days)} ημέρες). Το υπόλοιπο του ${r.year} θα αυξηθεί ανάλογα και η εγγραφή `
      + 'θα φύγει και από το κοινό ημερολόγιο.', {ok: 'Διαγραφή', danger: true})) { return false; }
    try { await api('leave_delete', {id: row.id}); toast('Διαγράφηκε'); return true; }
    catch (e) { toast(e.message, 1); return false; }
  }

  const vals = {
    staffId: ctx.staffPick ? +lvV('lvStaff') : (r.staffId || ctx.staffId),
    type: lvV('lvType'), from: lvV('lvFrom'), to: lvV('lvTo'),
    days: lvV('lvDays'), year: lvV('lvYear'), ergani: lvV('lvErg'), note: lvV('lvNote'),
  };
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
  let dErr = null;
  const d = await api('leave_me').catch(e => { dErr = e; return null; });
  if (!d) { c.innerHTML = cnpDenied(dErr); return; }
  if (!d.enrolled) {
    c.innerHTML = `<div class="card"><div class="card-b"><div class="empty">
      <b>Δεν είσαι στο μητρώο αδειών.</b>
      <div class="mut" style="margin-top:6px">${esc(d.note || '')}</div></div></div></div>`;
    return;
  }
  const yNow = new Date().getFullYear();
  const cur = (d.years || []).find(y => y.year === yNow);
  const exp = d.expiring || [];
  const open = (d.rows || []).filter(r => r.phase === 'requested' || r.phase === 'upcoming' || r.phase === 'running');
  const past = (d.rows || []).filter(r => !open.includes(r));

  const tbl = arr => `<table class="lv-t"><thead><tr>
      <th>Περίοδος</th><th>Τύπος</th><th class="r">Ημέρες</th><th>Έτος</th><th>Κατάσταση</th><th></th></tr></thead>
    <tbody>${arr.map(r => `<tr>
      <td class="lv-hd"><b>${esc(r.span)}</b></td>
      <td data-l="Τύπος">${esc(r.typeLabel)}</td>
      <td class="r" data-l="Ημέρες">${lvN(r.days)}</td>
      <td class="mut" data-l="Έτος">${r.year}</td>
      <td data-l="Κατάσταση">${lvPill(r.phase)}</td>
      <td class="r">${r.phase === 'requested'
        ? `<button class="btn btn-o btn-sm" data-lvw="${r.id}">Ανάκληση</button>` : ''}</td>
    </tr>`).join('')}</tbody></table>`;

  c.innerHTML = `
  <div class="fbar">
    ${fChip('Υπόλοιπο', `<b style="padding:0 6px">${lvN(d.remaining)} ημέρες</b>`, false, '')}
    <span class="fbar-sp"></span>
    <button class="fchip fchip-go" id="lvAsk">${I.plus} Νέο αίτημα</button>
  </div>

  <div class="cl-tiles lv-tiles">
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

  ${open.length ? `<div class="card"><div class="card-h">Σε εξέλιξη & προγραμματισμένες</div>
    <div class="card-b" style="padding:0">${tbl(open)}</div></div>` : ''}

  <div class="card"><div class="card-h">Ανά έτος δικαιώματος</div><div class="card-b" style="padding:0">
    <table class="lv-t"><thead><tr>
      <th>Έτος</th><th class="r">Δικαιούμαι</th><th class="r">Μεταφορά</th>
      <th class="r">Πήρα</th><th class="r">Μένουν</th><th>Προθεσμία</th></tr></thead>
    <tbody>${(d.years || []).slice().reverse().map(y => `<tr>
      <td class="lv-hd"><b>${y.year}</b></td>
      <td class="r" data-l="Δικαιούμαι">${lvN(y.entitled)}</td>
      <td class="r" data-l="Μεταφορά">${y.carried ? lvN(y.carried) : '<span class="mut">—</span>'}</td>
      <td class="r" data-l="Πήρα">${lvN(y.taken)}</td>
      <td class="r" data-l="Μένουν"><b style="color:${y.remaining > 0 && !y.settled ? 'var(--brand)' : 'var(--mut,#64748b)'}">${lvN(y.remaining)}</b></td>
      <td class="mut" data-l="Προθεσμία">${y.remaining > 0 && !y.settled ? lvDate(y.deadline) : '—'}</td></tr>`).join('')}
    </tbody></table>
  </div></div>

  ${past.length ? `<details class="card lv-fold"><summary class="card-h">Ιστορικό — ${past.length} άδειες</summary>
    <div class="card-b" style="padding:0">${tbl(past)}</div></details>`
  : (open.length ? '' : `<div class="card"><div class="card-b"><div class="empty">Καμία άδεια ακόμη.
      <div class="mut" style="margin-top:6px">Πάτα «Νέο αίτημα» για να ζητήσεις την πρώτη σου.</div></div></div></div>`)}`;

  $('#lvAsk').onclick = async () => { if (await lvAsk(d)) { R.myleave(); } };
  $$('[data-lvw]').forEach(b => b.onclick = async () => {
    if (!await cnpConfirm('Ανάκληση του αιτήματος; Θα σβηστεί και δεν θα φτάσει στον υπεύθυνο.',
      {ok: 'Ανάκληση', danger: true})) { return; }
    try { await api('leave_withdraw', {id: +b.dataset.lvw}); toast('Ανακλήθηκε'); R.myleave(); }
    catch (e) { toast(e.message, 1); }
  });
};

/* Το αίτημα του ίδιου του εργαζομένου: λιγότερα πεδία από την καταχώριση του
   γραφείου προσωπικού — δεν ορίζει ούτε έτος δικαιώματος ούτε ΕΡΓΑΝΗ. */
async function lvAsk(d) {
  const body =
    lvSec('Τι άδεια', `<div class="lv-grid one">${lvF('Τύπος', `<select class="inp" id="lvType">
      <option value="ANNUAL">Κανονική άδεια</option>
      <option value="SICK">Ασθένεια — δεν χρεώνει υπόλοιπο</option>
      <option value="SCHOOL">Σχολική άδεια — δεν χρεώνει υπόλοιπο</option>
      <option value="PARENTAL">Γονική άδεια — δεν χρεώνει υπόλοιπο</option>
      <option value="MARRIAGE">Άδεια γάμου — δεν χρεώνει υπόλοιπο</option>
      <option value="BEREAVEMENT">Άδεια θανάτου — δεν χρεώνει υπόλοιπο</option>
      <option value="UNPAID">Άνευ αποδοχών</option>
    </select>`)}</div>`)
    + lvSec('Πότε', `<div class="lv-grid">
        ${lvF('Από', '<input class="inp" type="date" id="lvFrom">')}
        ${lvF('Έως', '<input class="inp" type="date" id="lvTo">')}
      </div>`)
    + lvSec('Πόσες ημέρες', `<div class="lv-grid one">
        ${lvF('Ημέρες', '<input class="inp" type="number" step="0.5" min="0" id="lvDays" placeholder="κενό = οι εργάσιμες της περιόδου">')}
      </div>
      <div class="lv-hint">Υπόλοιπο τώρα: <b>${lvN(d.remaining)}</b> ημέρες.</div>`)
    + lvSec('Λόγος', `<div class="lv-grid one">
        ${lvF('Σημείωση', '<input class="inp" id="lvNote" placeholder="προαιρετικό">')}
      </div>
      <div class="lv-hint">Το αίτημα πηγαίνει για έγκριση — μέχρι τότε δεν εμφανίζεται
        στο κοινό ημερολόγιο.</div>`);

  if (await lvModal({title: 'Αίτημα άδειας', body, ok: 'Στείλε για έγκριση'}) !== 'ok') { return false; }
  const vals = {type: lvV('lvType'), from: lvV('lvFrom'), to: lvV('lvTo'),
    days: lvV('lvDays'), note: lvV('lvNote')};
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
  erganiMissing: {label: 'Χωρίς ΕΡΓΑΝΗ', bool: 1},
  q:       {label: 'Αναζήτηση', text: 1, id: 'lvQ', ph: 'σημείωση, περίοδος, ΕΡΓΑΝΗ…', w: 210},
};

R.leave = async function () {
  if (!cnpCan('hr.leave')) {
    setTop('Άδειες');
    $('#content').innerHTML = cnpDenied({message: 'Χρειάζεται «Προσωπικό → Άδειες προσωπικού»'});
    return;
  }
  const st = R.leave._s = R.leave._s || {view: 'now', year: String(new Date().getFullYear()),
    staffId: '', type: '', status: '', erganiMissing: 0, q: '', shown: []};
  setTop('Άδειες', st.view === 'now'
    ? 'τι ζητάει έγκριση, τι λήγει, πόσες μένουν στον καθένα'
    : 'όλο το ιστορικό αδειών, με φίλτρα');
  const c = $('#content');
  Object.keys(LV_F).forEach(k => { if (st[k] && !st.shown.includes(k)) { st.shown.push(k); } });
  cnpSkel(c, '<div class="skel" style="height:70px;margin-bottom:14px"></div><div class="skel" style="height:420px"></div>');

  const wantRows = st.view === 'log';
  let dErr = null;
  const [d, l] = await Promise.all([
    api('leave_staff', {year: +st.year}).catch(e => { dErr = e; return null; }),
    api('leave_list', wantRows
      ? {year: +st.year, staffId: st.staffId, type: st.type, status: st.status,
         erganiMissing: st.erganiMissing, q: st.q}
      /* Στην οθόνη απόφασης δεν κατεβάζουμε 322 εγγραφές: μόνο ό,τι ζητάει
         ενέργεια. Το ιστορικό είναι δική του προβολή. */
      : {year: 0, status: 'requested'}).catch(() => ({rows: []})),
  ]);
  if (!d) { c.innerHTML = cnpDenied(dErr); return; }

  const canEdit = cnpCan('hr.leave.edit');
  const canOk   = cnpCan('hr.leave.approve');
  const canDel  = cnpCan('hr.leave.delete');
  const rows    = d.rows || [];
  const totRem  = rows.reduce((s, r) => s + r.remaining, 0);
  const sick    = rows.reduce((s, r) => s + r.sick, 0);
  const offLaw  = rows.filter(r => r.offLaw);
  const expiring = rows.filter(r => (r.expiring || []).length);
  const pend    = wantRows ? (l.rows || []).filter(r => r.phase === 'requested') : (l.rows || []);

  const bar = `
  <div class="fbar">
    ${fChip('Έτος', fSel('year', (d.years || []).map(y => [String(y), String(y)]), st.year), false, '')}
    ${wantRows ? st.shown.map(k => {
      const F = LV_F[k];
      if (F.bool) { return `<button type="button" class="fchip fchip-b${st[k] ? ' on' : ''}" data-fb="${k}">${
        st[k] ? '✓ ' : ''}${esc(F.label)}<span class="fchip-x" data-fx="${k}" title="Αφαίρεση">✕</span></button>`; }
      if (F.text) { return fChip(F.label, `<input class="fchip-s" id="${F.id}" data-fk="${k}" value="${esc(st[k] || '')}"
        placeholder="${esc(F.ph)}" style="width:${F.w}px">`, !!st[k], k); }
      return fChip(F.label, fSel(k, F.opts(d), st[k]), !!st[k], k);
    }).join('') + fAdd(LV_F, st.shown) : ''}
    <span class="fbar-sp"></span>
    <button class="fchip${wantRows ? ' on' : ''}" id="lvLog">${wantRows ? '← Υπόλοιπα' : 'Ιστορικό εγγραφών'}</button>
    ${canEdit ? `<button class="fchip" id="lvAddStaff">Εργαζόμενοι</button>
      <button class="fchip fchip-go" id="lvNew">${I.plus} Νέα καταχώριση</button>` : ''}
  </div>`;

  if (wantRows) {
    c.innerHTML = bar + `
    <div class="card"><div class="card-h">Εγγραφές — ${(l.rows || []).length}</div>
      <div class="card-b" style="padding:0">
      ${(l.rows || []).length ? `<table class="lv-t"><thead><tr>
          <th>Εργαζόμενος</th><th>Τύπος</th><th class="r">Ημέρες</th>
          <th>Έτος</th><th>Κατάσταση</th><th>ΕΡΓΑΝΗ</th><th></th></tr></thead>
        <tbody>${l.rows.map(r => `<tr>
          <td class="lv-hd"><b>${esc(r.name)}</b> <span class="mut">${esc(r.span)}</span></td>
          <td data-l="Τύπος">${esc(r.typeLabel)}</td>
          <td class="r" data-l="Ημέρες">${lvN(r.days)}</td>
          <td class="mut" data-l="Έτος">${r.year}</td>
          <td data-l="Κατάσταση">${lvPill(r.phase)}${r.flagLabel ? ` <span class="lv-flag">${esc(r.flagLabel)}</span>` : ''}</td>
          <td data-l="ΕΡΓΑΝΗ">${r.ergani ? esc(r.ergani)
            : (lvErgLate(r.year) ? '<span class="lv-miss">λείπει</span>'
               : '<span class="mut">εκκρεμεί</span>')}</td>
          <td class="r">${canEdit ? `<button class="btn btn-o btn-sm" data-lve="${r.id}">Άλλαξε</button>` : ''}</td>
        </tr>`).join('')}</tbody></table>`
      : `<div class="empty">Καμία εγγραφή με αυτά τα φίλτρα.
          <div class="mut" style="margin-top:6px">Άλλαξε το έτος ή καθάρισε τα φίλτρα από τα ✕.</div></div>`}
    </div></div>`;
  } else {
    c.innerHTML = bar + `
    <div class="cl-tiles lv-tiles">
      ${lvTile(pend.length, 'ζητούν έγκριση', pend.length ? 'var(--warn,#b45309)' : '')}
      ${lvTile(expiring.length, 'με ημέρες που λήγουν', expiring.length ? 'var(--bad,#b91c1c)' : '')}
      ${lvTile(lvN(totRem), 'ημέρες μένουν συνολικά', 'var(--brand)')}
      ${lvTile(lvN(sick), 'ημέρες ασθενείας ' + st.year, sick ? 'var(--warn,#b45309)' : '')}
      ${offLaw.length ? lvTile(offLaw.length, 'εκτός κλίμακας νόμου', 'var(--bad,#b91c1c)',
        'Οι δικαιούμενες ημέρες διαφέρουν από όσες προβλέπει η κλίμακα για την προϋπηρεσία τους') : ''}
    </div>

    ${pend.length ? `<div class="card lv-warn"><div class="card-h">Θέλουν απόφαση</div>
      <div class="card-b" style="padding:0"><table class="lv-t"><tbody>
      ${pend.map(r => `<tr>
        <td class="lv-hd"><b>${esc(r.name)}</b> <span class="mut">${esc(r.span)}</span></td>
        <td data-l="Τύπος">${esc(r.typeLabel)}</td>
        <td class="r" data-l="Ημέρες">${lvN(r.days)}</td>
        <td class="mut" data-l="Έτος δικαιώματος">${r.year}</td>
        <td class="r">${canOk
          ? `<button class="btn btn-p btn-sm" data-lvok="${r.id}">Έγκριση</button>
             <button class="btn btn-o btn-sm" data-lvno="${r.id}">Απόρριψη</button>`
          : '<span class="mut">χρειάζεται δικαίωμα έγκρισης</span>'}</td>
      </tr>`).join('')}</tbody></table></div></div>` : ''}

    ${expiring.length ? `<div class="card lv-warn"><div class="card-h">Λήγουν — προθεσμία 31 Μαρτίου</div>
      <div class="card-b"><div style="font-size:13px;line-height:1.7">
        ${expiring.map(r => r.expiring.map(e => `<div><b>${esc(r.name)}</b>: ${lvN(e.days)} ημέρες του ${e.year}
          ${e.left < 0 ? '<span style="color:var(--bad,#b91c1c)">πέρασαν την προθεσμία (' + lvDate(e.deadline) + ')</span>'
            : 'ως ' + lvDate(e.deadline) + ' — σε ' + e.left + ' ημέρες'}</div>`).join('')).join('')}
      </div>
      <div class="mut" style="font-size:11.5px;margin-top:8px">Μετά την προθεσμία η αξίωση γίνεται
        χρηματική με προσαύξηση 100% συν επίδομα αδείας — κοστίζει διπλά.</div></div></div>` : ''}

    <div class="card"><div class="card-h">Υπόλοιπα ${st.year}</div><div class="card-b" style="padding:0">
      <table class="lv-t lv-flow"><thead><tr>
        <th>Εργαζόμενος</th>
        <th class="r">Δικαιούται</th><th class="r lv-op">+ από πέρσι</th>
        <th class="r lv-eq">= διαθέσιμες</th><th class="r lv-op">− πήρε</th>
        <th class="r lv-eq">= έμειναν</th><th class="r lv-op">→ στο ${+st.year + 1}</th>
        <th class="r">Ασθένεια</th></tr></thead>
      <tbody>${rows.map(r => {
        /* Ό,τι έμεινε και ΔΕΝ μεταφέρθηκε: χάθηκε ή αποζημιώθηκε. Για τρέχον
           έτος δεν είναι συμπέρασμα — η χρονιά δεν τελείωσε. */
        const lost = Math.round((r.remaining - r.carriedOut) * 100) / 100;
        const past = +st.year < new Date().getFullYear();
        return `<tr class="pick" data-lvs="${r.staffId}" role="button" tabindex="0">
        <td class="lv-hd"><b>${esc(r.name)}</b>${r.offLaw
          ? ` <span class="lv-warn-dot" title="Ο νόμος δίνει ${lvN(r.suggest)} — ${esc(r.suggestWhy)}">!</span>` : ''}${
          ''}</td>
        <td class="r" data-l="Δικαιούται">${lvN(r.entitled)}</td>
        <td class="r lv-op" data-l="Από πέρσι">${r.carried ? '+' + lvN(r.carried) : '<span class="mut">—</span>'}</td>
        <td class="r lv-eq" data-l="Διαθέσιμες"><b>${lvN(r.available)}</b></td>
        <td class="r lv-op" data-l="Πήρε">${r.settled
          ? '<span class="mut" title="Το έτος ήταν σημειωμένο ως τακτοποιημένο στο Excel, χωρίς αναλυτικές εγγραφές">—</span>'
          : '−' + lvN(r.taken)}</td>
        <td class="r lv-eq" data-l="Έμειναν">${r.settled
          ? '<span class="mut" title="Δεν καταγράφηκε αναλυτικά — το έτος είχε κλείσει">τακτοποιημένο</span>'
          : `<b style="color:${r.remaining > 0 ? 'var(--brand)' : 'var(--mut,#64748b)'}">${lvN(r.remaining)}</b>`}</td>
        <td class="r lv-op" data-l="Στο επόμενο">${r.carriedOut ? '→' + lvN(r.carriedOut)
          : (past && lost > 0.01 && !r.settled
             ? `<span class="lv-miss" title="Έμειναν ${lvN(lost)} ημέρες που δεν μεταφέρθηκαν — χάθηκαν ή αποζημιώθηκαν">δεν μεταφέρθηκαν</span>`
             : '<span class="mut">—</span>')}</td>
        <td class="r" data-l="Ασθένεια">${r.sick ? lvN(r.sick) : '<span class="mut">—</span>'}</td>
      </tr>`; }).join('')}</tbody></table>
      <div class="mut" style="font-size:11.5px;padding:9px 12px">
        Η γραμμή διαβάζεται από αριστερά: <b>δικαιούται</b> + ό,τι κράτησε από πέρσι =
        <b>διαθέσιμες</b>, μείον όσες πήρε = <b>έμειναν</b>, και από αυτές όσες κρατήθηκαν
        για το ${+st.year + 1}. Κλικ σε εργαζόμενο για την πλήρη καρτέλα του.${
        d.notYet ? ` <b>${d.notYet}</b> ${d.notYet === 1 ? 'εργαζόμενος δεν είχε' : 'εργαζόμενοι δεν είχαν'}
          προσληφθεί ακόμη το ${st.year} και δεν εμφανίζονται.` : ''}</div>
    </div></div>`;
  }

  /* Το έτος είναι μόνιμο φίλτρο, δεν περνά από το fWire (δεν έχει ✕). */
  { const ys = $('[data-fk="year"]'); if (ys) { ys.onchange = e => { st.year = e.target.value; R.leave(); }; } }
  if (wantRows) { fWire(st, LV_F, () => R.leave()); cnpSearch('lvQ', v => { st.q = v; return R.leave(); }, 320); }
  $('#lvLog').onclick = () => { st.view = wantRows ? 'now' : 'log'; R.leave(); };

  const nb = $('#lvNew');
  if (nb) { nb.onclick = async () => {
    if (await lvEditLeave(null, {types: d.types, staff: rows, staffPick: true,
      staffId: rows[0] && rows[0].staffId})) { R.leave(); }
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

  const sub = (title, arr) => arr.length ? `<div class="lv-sub">${esc(title)} — ${arr.length}</div>
    <table class="lv-t lv-t-sm"><tbody>${arr.map(r => `<tr>
      <td class="lv-hd"><b>${esc(r.span)}</b></td>
      <td data-l="Τύπος" class="mut">${esc(r.typeLabel)}</td>
      <td class="r" data-l="Ημέρες">${lvN(r.days)}</td>
      <td class="mut" data-l="Έτος">${r.year}</td>
      <td data-l="Κατάσταση">${lvPill(r.phase)}</td>
      <td data-l="ΕΡΓΑΝΗ">${r.ergani ? '<span class="mut">' + esc(r.ergani) + '</span>'
        : (lvErgLate(r.year) ? '<span class="lv-miss">λείπει</span>' : '<span class="mut">εκκρεμεί</span>')}</td>
    </tr>`).join('')}</tbody></table>` : '';

  const draw = () => {
    const years = (d.years || []).slice().reverse();
    /* Η μεταφορά ΠΡΟΣ τα εμπρός είναι η «από πέρσι» του επόμενου έτους. */
    const carryOut = y => {
      const nx = (d.years || []).find(x => x.year === y + 1);
      return nx ? nx.carried : 0;
    };
    const yearRows = years.length ? years.map(y => `<tr>
        <td class="lv-hd"><b>${y.year}</b>${y.settled ? ' <span class="mut">τακτοποιημένο</span>' : ''}</td>
        <td class="r" data-l="Δικαιούται">${canEdit
          ? `<input class="inp lv-mini" type="number" step="0.5" min="0" data-lvent="${y.year}" value="${lvN(y.entitled)}">`
          : lvN(y.entitled)}</td>
        <td class="r lv-op" data-l="Από πέρσι">${canEdit
          ? `<input class="inp lv-mini" type="number" step="0.5" min="0" data-lvcar="${y.year}" value="${lvN(y.carried)}">`
          : (y.carried ? '+' + lvN(y.carried) : '<span class="mut">—</span>')}</td>
        <td class="r lv-eq" data-l="Διαθέσιμες"><b>${lvN(y.available)}</b></td>
        <td class="r lv-op" data-l="Πήρε">${y.settled ? '<span class="mut">—</span>' : '−' + lvN(y.taken)}</td>
        <td class="r lv-eq" data-l="Έμειναν">${y.settled
          ? '<span class="mut">τακτοποιημένο</span>'
          : `<b style="color:${y.remaining > 0 ? 'var(--brand)' : 'var(--mut,#64748b)'}">${lvN(y.remaining)}</b>`}</td>
        <td class="r lv-op" data-l="Στο επόμενο">${carryOut(y.year) ? '→' + lvN(carryOut(y.year)) : '<span class="mut">—</span>'}</td>
        <td class="mut" data-l="Προθεσμία">${y.remaining > 0 && !y.settled ? lvDate(y.deadline) : '—'}</td>
        <td class="r">${canEdit ? `<button class="btn btn-p btn-sm" data-lvsave="${y.year}">Αποθήκευση</button>` : ''}</td>
      </tr>`).join('')
      : '<tr><td colspan="9" class="mut" style="padding:12px">Κανένα έτος δικαιώματος ακόμη.</td></tr>';

    const hist = sub('Κανονική άδεια', (d.rows || []).filter(r => r.type === 'ANNUAL'))
      + sub('Αναρρωτικές', (d.rows || []).filter(r => r.type === 'SICK'))
      + sub('Λοιπές άδειες', (d.rows || []).filter(r => r.type !== 'SICK' && r.type !== 'ANNUAL'));

    return lvSec('Ποιος', `<div class="lv-hint" style="margin:0">
        Πρόσληψη <b>${d.hire ? lvDate(d.hire) : '—'}</b>${d.afm ? ' · ΑΦΜ ' + esc(d.afm) : ''}${
        d.priorMonths ? ' · προϋπηρεσία πριν από εμάς <b>' + Math.round(d.priorMonths / 12) + '</b> έτη' : ''}
        · συνολικό υπόλοιπο <b>${lvN(d.remaining)}</b> ημέρες</div>`)
      + ((d.expiring || []).length ? `<div class="lv-inline-warn">${d.expiring.map(e => e.left < 0
          ? `<b>${lvN(e.days)}</b> ημέρες του ${e.year} πέρασαν την προθεσμία (${lvDate(e.deadline)}) — γίνονται χρηματική αξίωση.`
          : `<b>${lvN(e.days)}</b> ημέρες του ${e.year} λήγουν ${lvDate(e.deadline)} (σε ${e.left} ημέρες).`).join('<br>')}</div>` : '')
      + lvSec('Δικαίωμα ανά έτος', `<table class="lv-t lv-t-sm lv-flow"><thead><tr>
          <th>Έτος</th><th class="r">Δικαιούται</th><th class="r lv-op">+ από πέρσι</th>
          <th class="r lv-eq">= διαθέσιμες</th><th class="r lv-op">− πήρε</th>
          <th class="r lv-eq">= έμειναν</th><th class="r lv-op">→ επόμενο</th>
          <th>Προθεσμία</th><th></th></tr></thead>
          <tbody>${yearRows}</tbody></table>
        <div class="lv-hint">Γράφονται μόνο οι <b>δικαιούμενες</b> και η <b>μεταφορά</b>.
          Το «πήρε» και το «μένουν» προκύπτουν από τις εγγραφές — δεν είναι πεδία,
          γι' αυτό δεν ξεφεύγουν.</div>`)
      + lvSec('Ιστορικό', hist || '<div class="mut">Καμία εγγραφή.</div>');
  };

  const p = lvModal({title: d.name, body: `<div id="lvPB">${draw()}</div>`,
    ok: 'Κλείσιμο', cancel: null});
  const wire = () => {
    const host = lvModal._el && lvModal._el.querySelector('#lvPB');
    if (!host) { return; }
    host.querySelectorAll('[data-lvsave]').forEach(b => b.onclick = async () => {
      const y = +b.dataset.lvsave;
      const ent = host.querySelector(`[data-lvent="${y}"]`);
      const car = host.querySelector(`[data-lvcar="${y}"]`);
      try {
        await api('leave_year_save', {staffId, year: y, type: 'ANNUAL',
          entitled: ent ? ent.value : 0, carried: car ? car.value : 0});
        toast(`Αποθηκεύτηκε το ${y}`);
        d = await api('leave_person', {staffId});
        host.innerHTML = draw();
        wire();
      } catch (e) { toast(e.message, 1); }
    });
  };
  wire();
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
  if (!free.length) { toast('Όλοι οι χειριστές είναι ήδη στο μητρώο'); return false; }

  const body =
    lvSec('Ποιος', `<div class="lv-grid one">${lvF('Χειριστής',
      `<select class="inp" id="lvNewAdm">${free.map(a =>
        `<option value="${a.id}">${esc(a.name)}</option>`).join('')}</select>`)}</div>`)
    + lvSec('Στοιχεία εργασίας', `<div class="lv-grid">
        ${lvF('Ημερομηνία πρόσληψης', '<input class="inp" type="date" id="lvNewHire">')}
        ${lvF('ΑΦΜ', '<input class="inp" id="lvNewAfm" inputmode="numeric">')}
      </div>`)
    + lvSec('Προϋπηρεσία', `<div class="lv-grid one">
        ${lvF('Έτη πριν από εμάς', '<input class="inp" type="number" min="0" max="50" id="lvNewPrior" value="0">')}
      </div>
      <div class="lv-hint">Καθορίζει πόσες ημέρες δικαιούται: με <b>12 έτη</b> συνολικά πάει
        στις 25, με <b>25 έτη</b> στις 26. Αν δεν την ξέρεις ακόμη, βάλε 0 και διόρθωσέ την
        όταν βρεθεί η σύμβαση — τα δικαιώματα ξαναϋπολογίζονται.</div>`);

  if (await lvModal({title: 'Προσθήκη στο μητρώο αδειών', body, ok: 'Προσθήκη'}) !== 'ok') { return false; }
  const vals = {adminId: +lvV('lvNewAdm'), hire: lvV('lvNewHire'), afm: lvV('lvNewAfm'),
    priorMonths: Math.round((+lvV('lvNewPrior') || 0) * 12)};
  if (!vals.hire) {
    toast('Η ημερομηνία πρόσληψης είναι απαραίτητη — χωρίς αυτήν δεν υπολογίζεται δικαίωμα', 1);
    return false;
  }
  try { await api('leave_staff_save', vals); toast('Προστέθηκε στο μητρώο'); return true; }
  catch (e) { toast(e.message, 1); return false; }
}

window.R = R;
