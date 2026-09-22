/* ═══════════ CloudOn Projects — Προαγορά χρόνου ═══════════
   Ο χρεώσιμος χρόνος πληρώνεται από κάπου: εγκεκριμένη προσφορά για το έργο,
   ή προαγορά του πελάτη. Ό,τι δεν καλύφθηκε μένει ακάλυπτο και από εκεί βγαίνει
   η επόμενη προσφορά. Το μητρώο είναι του supportcontracts — εδώ γίνεται
   δουλεύσιμο από εκεί που ζει ο χρόνος που το αναλώνει. */
'use strict';
const {S, api, esc, cnpSearch, fChip, fSel, fAdd, fWire, fmtEur, dShort, dFull, today, toast, setTop, cnpConfirm, cnpDialog,
  cnpDenied, cnpCan, cnpPrompt, closeDrawer, openTask, adminName, adminIni, I, go, stPill, CNP_ST, $, $$} = window.CNP;
const R = window.R;

/* «2ω 30΄» — ίδια γραφή με τον server (Cover::fmt), για να διαβάζονται μαζί. */
const hm = m => {
  m = Math.round(+m || 0);
  const s = m < 0 ? '-' : '';
  m = Math.abs(m);
  const h = Math.floor(m / 60), r = m % 60;
  return s + (h && r ? h + 'ω ' + r + '΄' : h ? h + 'ω' : r + '΄');
};
const stat = (ic, n, l, col) => `<div class="su-stat"><div class="ic" style="background:${col}1a;color:${col}">${ic}</div>
  <div><div class="n">${n}</div><div class="l">${l}</div></div></div>`;

const covPill = c => c === 'prepaid' ? '<span class="pill pill-ok">προαγορά</span>'
  : c === 'offer' ? '<span class="pill pill-info">προσφορά</span>'
  : '<span class="pill pill-bad">ακάλυπτο</span>';

const FREQ = {monthly: 'μηνιαία', weekly: 'εβδομαδιαία', both: 'μηνιαία + εβδομαδιαία', off: 'καμία'};

/* Κοινό κέλυφος drawer — ίδιο μοτίβο με τα υπόλοιπα κυκλώματα. */
function drawer(title, html, wide) {
  closeDrawer();
  const ovl = document.createElement('div'); ovl.className = 'ovl';
  const dr = document.createElement('div'); dr.className = 'drawer' + (wide ? ' tk-modal' : '');
  dr.innerHTML = `<div class="drawer-h"><h2>${title}</h2><button class="drawer-x" id="dX">✕</button></div>
    <div class="drawer-b" id="ppBody">${html}</div>`;
  document.body.append(ovl, dr);
  requestAnimationFrame(() => { ovl.classList.add('show'); dr.classList.add('show'); });
  $('#dX', dr).onclick = () => closeDrawer();
  ovl.onclick = () => closeDrawer();
  return dr;
}

/* ─────────────────────── Υπόλοιπα πελατών ─────────────────────── */

R.prepaid = async function () {
  setTop('Προαγορά χρόνου', 'Πόσο έχουν αγοράσει, πόσο έμεινε, τι δεν καλύφθηκε');
  const c = $('#content');
  c.innerHTML = '<div class="skel" style="height:110px;margin-bottom:14px"></div><div class="skel" style="height:340px"></div>';
  let dErr = null;
  const d = await api('prepaid').catch(e => { dErr = e; return null; });
  if (!d) { c.innerHTML = cnpDenied(dErr); return; }
  const t = d.totals;

  c.innerHTML = `
  <div class="fbar">
    ${fChip('Αναζήτηση', `<input class="fchip-s" id="ppQ" placeholder="όνομα πελάτη…"
      autocomplete="off" style="width:240px">`, false, '')}
    <button type="button" class="fchip fchip-b" id="ppOpen">Μόνο με ακάλυπτο χρόνο</button>
    <span class="fbar-sp"></span>
    ${cnpCan('prepaid.contract') ? `<button class="fchip fchip-go" id="ppNew">${I.plus} Νέο συμβόλαιο</button>` : ''}
  </div>

  <div style="display:flex;gap:11px;flex-wrap:wrap;align-items:center;margin-bottom:12px">
    ${stat(I.clock, hm(t.balance), 'διαθέσιμη προαγορά', 'var(--ok)')}
    ${stat(I.doc, hm(t.offer), 'καλυμμένα από προσφορές', 'var(--info)')}
    ${stat(I.alert, hm(t.open), t.open ? 'ακάλυπτα — θέλουν προσφορά' : 'ακάλυπτος χρόνος', t.open ? 'var(--bad)' : 'var(--ok)')}
    ${stat(I.users, t.clients, t.low ? t.low + ' με χαμηλό υπόλοιπο' : 'πελάτες', t.low ? 'var(--warn)' : 'var(--brand)')}
  </div>

  <div class="mut" style="font-size:11.5px;margin-bottom:12px">${d.products.length
    ? I.box + ' Αυτόματη πίστωση με την εξόφληση: ' + d.products.map(p =>
        `<span class="pill ${p.exists ? 'pill-mut' : 'pill-bad'}" title="${p.exists ? 'Προϊόν WHMCS #' + p.id : 'Το προϊόν #' + p.id + ' δεν υπάρχει πια'}">${esc(p.name)} · ${p.hours}ω</span>`).join(' ')
    : I.alert + ' Δεν έχει οριστεί προϊόν αυτόματης πίστωσης — κάθε προαγορά μπαίνει με το χέρι.'}</div>

  <div id="ppRows"></div>`;

  const rowHtml = r => {
    const low = r.contract && r.balance <= d.low;
    return `<div class="card pp-row" data-c="${r.client}" style="padding:12px 15px;margin-bottom:8px;cursor:pointer;display:flex;gap:14px;align-items:center;flex-wrap:wrap">
      <div style="flex:1;min-width:190px">
        <div style="font-weight:600">${esc(r.name)}
          ${r.label ? `<span class="pill pill-mut">${esc(r.label)}</span>` : ''}
          ${!r.contract ? '<span class="pill pill-bad" title="Έχει χρεώσιμο χρόνο αλλά κανένα συμβόλαιο προαγοράς">χωρίς συμβόλαιο</span>'
            : (!r.enabled ? '<span class="pill pill-mut">ανενεργό</span>' : '')}
          ${low ? '<span class="pill pill-warn">χαμηλό υπόλοιπο</span>' : ''}</div>
        <div class="mut" style="font-size:11.5px;margin-top:2px">
          ${r.offers.length ? r.offers.length + (r.offers.length > 1 ? ' προσφορές' : ' προσφορά') + ' σε ισχύ · ' : ''}
          αναφορά: ${FREQ[r.reportFreq] || 'μηνιαία'}</div>
      </div>
      <div style="text-align:right;min-width:92px">
        <div style="font-weight:700;font-size:15px;color:${low ? 'var(--warn)' : 'inherit'}">${hm(r.balance)}</div>
        <div class="mut" style="font-size:10.5px">προαγορά</div></div>
      <div style="text-align:right;min-width:92px">
        <div style="font-weight:700;font-size:15px;color:${r.offerLeft ? 'var(--info)' : 'var(--mut)'}">${hm(r.offerLeft)}</div>
        <div class="mut" style="font-size:10.5px">από προσφορές</div></div>
      <div style="text-align:right;min-width:92px">
        <div style="font-weight:700;font-size:15px;color:${r.uncovered ? 'var(--bad)' : 'var(--mut)'}">${hm(r.uncovered)}</div>
        <div class="mut" style="font-size:10.5px">ακάλυπτα${r.pending ? ' · ' + hm(r.pending) + ' σε προσφορά' : ''}</div></div>
    </div>`;
  };

  const paint = () => {
    const q = ($('#ppQ').value || '').toLowerCase().trim();
    const only = $('#ppOpen').classList.contains('on');
    const rows = d.rows.filter(r => (!q || r.name.toLowerCase().includes(q)) && (!only || r.uncovered > 0));
    $('#ppRows').innerHTML = rows.length ? rows.map(rowHtml).join('')
      : `<div class="empty" style="padding:38px">${I.sparkle}Κανένας πελάτης με αυτά τα κριτήρια</div>`;
    $$('.pp-row').forEach(el => el.onclick = () => openPrepaid(+el.dataset.c));
  };
  $('#ppQ').oninput = paint;
  /* Κουμπί που ανάβει αντί για κουτάκι — κρατά τη δική του κατάσταση στην κλάση. */
  $('#ppOpen').onclick = e => { e.currentTarget.classList.toggle('on'); paint(); };
  const bN = $('#ppNew'); if (bN) { bN.onclick = pickClient; }
  paint();
};

/* Νέο συμβόλαιο: διάλεξε πελάτη, άνοιξε την καρτέλα του. */
function pickClient() {
  const dr = drawer('Νέο συμβόλαιο προαγοράς', `
    <div class="card"><div class="card-b">
      <label class="lbl">Πελάτης</label>
      <input class="inp" id="ppcQ" placeholder="Όνομα, επωνυμία ή email…" autocomplete="off">
      <div id="ppcRes" style="max-height:280px;overflow:auto;margin-top:8px"></div>
    </div></div>`);
  const res = $('#ppcRes', dr);
  let tmr = null;
  const q = $('#ppcQ', dr);
  q.oninput = () => {
    clearTimeout(tmr);
    const v = q.value.trim();
    if (v.length < 2) { res.innerHTML = ''; return; }
    tmr = setTimeout(async () => {
      const r = await api('client_search&q=' + encodeURIComponent(v)).catch(() => null);
      const list = (r && r.results) || [];
      res.innerHTML = list.length ? list.map(x =>
        `<div class="pp-pick" data-id="${x.id}" style="padding:9px 11px;border-radius:9px;cursor:pointer;border-bottom:1px solid var(--line)">
           <b>${esc(x.name)}</b> <span class="mut">#${x.id}${x.email ? ' · ' + esc(x.email) : ''}</span></div>`).join('')
        : '<div class="mut" style="padding:9px">Κανένα αποτέλεσμα</div>';
      $$('.pp-pick', res).forEach(el => el.onclick = () => openPrepaid(+el.dataset.id, true));
    }, 260);
  };
  q.focus();
}

/* ─────────────────────── Καρτέλα πελάτη ─────────────────────── */

async function openPrepaid(clientId, forceEdit) {
  const dr = drawer('Προαγορά χρόνου', '<div class="skel" style="height:300px"></div>', true);
  const body = $('#ppBody', dr);
  let dErr = null;
  let d = await api('prepaid_client&client=' + clientId).catch(e => { dErr = e; return null; });
  if (!d) { body.innerHTML = cnpDenied(dErr); return; }

  const render = () => {
    const st = d.state;
    const bd = d.breakdown;
    const T = bd.totals;
    body.innerHTML = `
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:13px">
      <div style="flex:1;min-width:190px">
        <div style="font-size:16.5px;font-weight:700">${esc(d.name)}</div>
        <div class="mut" style="font-size:11.5px">#${d.client}${st.label ? ' · ' + esc(st.label) : ''}
          ${st.contract ? ' · αναφορά ' + (FREQ[st.reportFreq] || 'μηνιαία') : ''}</div>
      </div>
      ${cnpCan('prepaid.contract') ? `<button class="btn btn-o btn-sm" id="ppEdit">${I.gear} Συμβόλαιο</button>` : ''}
      ${cnpCan('prepaid.move') ? `<button class="btn btn-o btn-sm" id="ppAdd">${I.plus} Πίστωση / διόρθωση</button>` : ''}
    </div>

    <div style="display:flex;gap:11px;flex-wrap:wrap;margin-bottom:14px">
      ${stat(I.clock, hm(st.balance), 'διαθέσιμη προαγορά', 'var(--ok)')}
      ${stat(I.doc, hm(st.offerLeft), 'από προσφορές', 'var(--info)')}
      ${stat(I.alert, hm(st.uncovered), 'ακάλυπτα', st.uncovered ? 'var(--bad)' : 'var(--ok)')}
      ${st.pending ? stat(I.doc, hm(st.pending), 'σε προσφορά που εκκρεμεί', 'var(--warn)') : ''}
    </div>

    ${!st.contract ? `<div class="card" style="margin-bottom:13px;border-left:3px solid var(--warn)"><div class="card-b">
      <b>Δεν υπάρχει συμβόλαιο προαγοράς.</b> Ο χρεώσιμος χρόνος καταγράφεται κανονικά αλλά δεν αφαιρείται
      από πουθενά — μένει ακάλυπτος. Πάτα «Συμβόλαιο» για να το ανοίξεις.</div></div>` : ''}

    ${st.offers.length ? `<div class="card" style="margin-bottom:13px"><div class="card-b">
      <label class="lbl" style="margin:0 0 8px">${I.doc} Προσφορές σε ισχύ</label>
      ${st.offers.map(o => `<div style="display:flex;gap:10px;align-items:center;padding:7px 0;border-bottom:1px solid var(--line)">
        <div style="flex:1;min-width:150px"><b>${esc(o.title)}</b>
          ${o.project ? `<span class="mut" style="font-size:11.5px"> · ${esc(o.project)}</span>`
            : '<span class="pill pill-warn" title="Δεν έχει συνδεθεί με έργο — δεν θα τραβήξει χρόνο αυτόματα">χωρίς έργο</span>'}
          <div class="mut" style="font-size:11px">${fmtEur(o.amount)} · καλύπτει ${hm(o.covered)}</div></div>
        <div style="text-align:right"><b style="color:${o.left ? 'var(--info)' : 'var(--mut)'}">${hm(o.left)}</b>
          <div class="mut" style="font-size:10.5px">απομένουν</div></div>
      </div>`).join('')}</div></div>` : ''}

    <div class="card" style="margin-bottom:13px"><div class="card-b">
      <div style="display:flex;gap:8px;align-items:center;margin-bottom:10px;flex-wrap:wrap">
        <label class="lbl" style="margin:0">${I.chart} Ανάλυση περιόδου</label>
        <input class="inp" type="date" id="ppFrom" value="${d.from}" style="width:150px;padding:5px 9px">
        <input class="inp" type="date" id="ppTo" value="${d.to}" style="width:150px;padding:5px 9px">
        <button class="btn btn-o btn-sm" id="ppGo">Προβολή</button>
        <div style="flex:1"></div>
        ${cnpCan('prepaid.report') ? `<button class="btn btn-o btn-sm" id="ppPrev">${I.mail} Προεπισκόπηση αναφοράς</button>` : ''}
      </div>
      ${bd.groups.length ? bd.groups.map(g => `
        <details style="border-bottom:1px solid var(--line)">
          <summary style="padding:9px 0;cursor:pointer;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <b style="flex:1;min-width:130px">${esc(g.name)}</b>
            ${g.prepaid ? `<span class="pill pill-ok">${hm(g.prepaid)} προαγορά</span>` : ''}
            ${g.offer ? `<span class="pill pill-info">${hm(g.offer)} προσφορά</span>` : ''}
            ${g.open ? `<span class="pill pill-bad">${hm(g.open)} ακάλυπτα</span>` : ''}
            ${g.free ? `<span class="pill pill-mut">${hm(g.free)} δωρεάν</span>` : ''}
          </summary>
          <table class="tbl" style="margin-bottom:8px"><tbody>
            ${g.items.map(i => `<tr>
              <td style="width:88px" class="mut">${dShort(i.at)}</td>
              <td>${esc(i.what)}${i.note ? ` <span class="mut">· ${esc(i.note)}</span>` : ''}</td>
              <td style="width:104px" align="right">${i.billable ? hm(i.charged) : `<span class="mut">${hm(i.worked)} δωρεάν</span>`}</td>
              <td style="width:92px" align="right">${i.billable ? covPill(i.open > 0 ? (i.cover === 'none' ? null : i.cover) : i.cover) : ''}</td>
            </tr>`).join('')}
          </tbody></table>
        </details>`).join('')
        : '<div class="mut" style="padding:12px 0">Καμία καταχώρηση χρόνου σε αυτή την περίοδο.</div>'}
      ${bd.groups.length ? `<div style="display:flex;gap:14px;justify-content:flex-end;padding-top:10px;font-size:12.5px;flex-wrap:wrap">
        <span class="mut">δουλεμένος <b>${hm(T.worked)}</b></span>
        <span class="mut">χρεώσιμος <b>${hm(T.charged)}</b></span>
        <span style="color:var(--ok)">προαγορά <b>${hm(T.prepaid)}</b></span>
        <span style="color:var(--info)">προσφορά <b>${hm(T.offer)}</b></span>
        <span style="color:var(--bad)">ακάλυπτα <b>${hm(T.open)}</b></span></div>` : ''}
    </div></div>

    ${d.uncovered.length ? `<div class="card" style="margin-bottom:13px;border-left:3px solid var(--bad)"><div class="card-b">
      <div style="display:flex;gap:8px;align-items:center;margin-bottom:9px;flex-wrap:wrap">
        <label class="lbl" style="margin:0">${I.alert} Ακάλυπτος χρόνος — ${hm(st.uncovered)}</label>
        <div style="flex:1"></div>
        <label class="mut" style="font-size:11.5px;display:flex;align-items:center;gap:4px">
          <input type="checkbox" id="ppAll"> όλα</label>
        ${cnpCan('prepaid.offer') ? `<button class="btn btn-sm" id="ppMakeOffer">${I.doc} Δημιουργία προσφοράς</button>` : ''}
      </div>
      <table class="tbl"><tbody>
        ${d.uncovered.map(u => `<tr>
          <td style="width:24px"><input type="checkbox" class="pp-u" value="${u.id}"></td>
          <td style="width:88px" class="mut">${dShort(u.at)}</td>
          <td><a href="#" data-task="${u.task}">${esc(u.title || 'Εργασία #' + u.task)}</a>
            ${u.project ? `<span class="mut" style="font-size:11px"> · ${esc(u.project)}</span>` : ''}</td>
          <td style="width:78px" class="mut" align="right">${esc(u.by)}</td>
          <td style="width:82px" align="right"><b style="color:var(--bad)">${hm(u.open)}</b></td>
        </tr>`).join('')}
      </tbody></table></div></div>` : ''}

    <div class="card"><div class="card-b">
      <label class="lbl" style="margin:0 0 8px">${I.list} Κινήσεις υπολοίπου</label>
      ${d.ledger.length ? `<table class="tbl"><tbody>${d.ledger.map(l => `<tr>
        <td style="width:118px" class="mut">${dFull(l.at)}</td>
        <td>${({topup: 'Αγορά', usage: 'Ανάλωση', adjust: 'Διόρθωση'})[l.type] || esc(l.type)}
          <span class="mut">${esc(l.note || '')}</span>${l.by ? `<span class="mut"> · ${esc(l.by)}</span>` : ''}</td>
        <td style="width:78px" align="right"><b style="color:${l.minutes < 0 ? 'var(--bad)' : 'var(--ok)'}">${l.minutes > 0 ? '+' : ''}${hm(l.minutes)}</b></td>
        <td style="width:78px" align="right" class="mut">${hm(l.after)}</td>
      </tr>`).join('')}</tbody></table>` : '<div class="mut">Καμία κίνηση ακόμη.</div>'}
    </div></div>`;

    const bE = $('#ppEdit', body); if (bE) { bE.onclick = () => editContract(d); }
    const bA = $('#ppAdd', body); if (bA) { bA.onclick = () => moveBalance(d); }
    $('#ppGo', body).onclick = async () => {
      const nd = await api(`prepaid_client&client=${clientId}&from=${$('#ppFrom', body).value}&to=${$('#ppTo', body).value}`)
        .catch(() => null);
      if (nd) { d = nd; render(); }
    };
    const bP = $('#ppPrev', body); if (bP) { bP.onclick = () => reportPreview(clientId, $('#ppFrom', body).value, $('#ppTo', body).value); }
    const all = $('#ppAll', body);
    if (all) { all.onchange = () => $$('.pp-u', body).forEach(x => { x.checked = all.checked; }); }
    const mk = $('#ppMakeOffer', body);
    if (mk) { mk.onclick = () => makeOffer(d, body); }
    $$('a[data-task]', body).forEach(a => { a.onclick = e => {
      e.preventDefault(); closeDrawer(); openTask(+a.dataset.task);
    }; });
  };

  const reload = async () => {
    const nd = await api('prepaid_client&client=' + clientId).catch(() => null);
    if (nd) { d = nd; render(); }
  };
  openPrepaid._reload = reload;
  render();
  if (forceEdit && !d.state.contract) { editContract(d); }
}

/* ─────────────────────── Το συμβόλαιο ─────────────────────── */

function editContract(d) {
  const ct = d.contract || {enabled: 1, priority: 0, report_freq: 'monthly', sla_value: 8, sla_unit: 'hours'};
  const dr = drawer(d.contract ? 'Συμβόλαιο — ' + esc(d.name) : 'Νέο συμβόλαιο — ' + esc(d.name), `
  <div class="card"><div class="card-b">
    <div class="frow">
      <div><label class="lbl">Ονομασία συμβολαίου</label>
        <input class="inp" id="ctL" value="${esc(ct.label || '')}" placeholder="π.χ. SLA VIP"></div>
      <div><label class="lbl">Προτεραιότητα</label>
        <select class="inp" id="ctP">${['Κανονική', 'Υψηλή', 'Κρίσιμη'].map((n, i) =>
          `<option value="${i}" ${+ct.priority === i ? 'selected' : ''}>${n}</option>`).join('')}</select></div>
    </div>
    <div class="frow" style="margin-top:11px">
      <div><label class="lbl">Χρόνος πρώτης απάντησης</label>
        <div style="display:flex;gap:6px">
          <input class="inp" id="ctSV" type="number" min="1" value="${+ct.sla_value || 8}" style="width:88px">
          <select class="inp" id="ctSU">
            <option value="hours" ${ct.sla_unit !== 'days' ? 'selected' : ''}>ώρες</option>
            <option value="days" ${ct.sla_unit === 'days' ? 'selected' : ''}>ημέρες</option>
          </select></div></div>
      <div><label class="lbl">Αναφορά προς τον πελάτη</label>
        <select class="inp" id="ctF">${Object.entries(FREQ).map(([k, v]) =>
          `<option value="${k}" ${(ct.report_freq || 'monthly') === k ? 'selected' : ''}>${v[0].toUpperCase() + v.slice(1)}</option>`).join('')}</select></div>
    </div>
    <label class="lbl" style="margin-top:11px">Παραλήπτες αναφοράς <span class="mut" style="font-weight:400">— κενό = το email του λογαριασμού</span></label>
    <input class="inp" id="ctM" value="${esc(ct.report_email || '')}" placeholder="logistirio@pelatis.gr, it@pelatis.gr">
    <label class="lbl" style="margin-top:11px">Τι καλύπτει <span class="mut" style="font-weight:400">— το βλέπει ο χειριστής όταν ανοίγει ticket</span></label>
    <textarea class="inp" id="ctC" rows="3">${esc(ct.covered || '')}</textarea>
    <label class="lbl" style="margin-top:11px">Εσωτερικές σημειώσεις</label>
    <textarea class="inp" id="ctN" rows="2">${esc(ct.notes || '')}</textarea>
    <label class="mut" style="display:flex;align-items:center;gap:6px;margin-top:12px">
      <input type="checkbox" id="ctE" ${+ct.enabled ? 'checked' : ''}> Ενεργό συμβόλαιο</label>
    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:15px">
      <button class="btn btn-o" id="ctX">Άκυρο</button>
      <button class="btn btn-p" id="ctS">Αποθήκευση</button>
    </div>
  </div></div>`);
  $('#ctX', dr).onclick = () => { closeDrawer(); openPrepaid(d.client); };
  $('#ctS', dr).onclick = async () => {
    const r = await api('prepaid_save', {client: d.client,
      label: $('#ctL', dr).value, priority: +$('#ctP', dr).value, enabled: $('#ctE', dr).checked,
      sla_value: +$('#ctSV', dr).value, sla_unit: $('#ctSU', dr).value, report_freq: $('#ctF', dr).value,
      report_email: $('#ctM', dr).value, covered: $('#ctC', dr).value, notes: $('#ctN', dr).value})
      .catch(e => ({ok: false, error: e && e.message}));
    if (!r.ok) { toast(r.error || 'Δεν αποθηκεύτηκε', true); return; }
    toast('Το συμβόλαιο αποθηκεύτηκε');
    closeDrawer(); openPrepaid(d.client);
  };
}

/* ─────────────────────── Πίστωση / διόρθωση ─────────────────────── */

async function moveBalance(d) {
  const v = await cnpDialog({title: 'Κίνηση υπολοίπου — ' + esc(d.name),
    body: 'Θετικές ώρες = πίστωση στον πελάτη. Αρνητικές = διόρθωση προς τα κάτω.\nΟι αγορές μέσω τιμολογίου πιστώνονται μόνες τους.',
    input: '', placeholder: 'π.χ. 10 ή -1.5', ok: 'Συνέχεια',
    hint: 'Ώρες — δεκαδικά επιτρέπονται (0.25 = 15΄)'});
  if (v === null || v === '') { return; }
  const mins = Math.round(parseFloat(String(v).replace(',', '.')) * 60);
  if (!mins || !isFinite(mins)) { toast('Δώσε αριθμό ωρών', true); return; }
  const note = await cnpDialog({title: (mins > 0 ? 'Πίστωση ' : 'Διόρθωση ') + hmAbs(mins),
    body: 'Γιατί; Θα φαίνεται στις κινήσεις και στην αναφορά του πελάτη.',
    input: '', placeholder: 'π.χ. Προαγορά 10ω — τιμολόγιο 20261234', ok: 'Καταχώρηση'});
  if (note === null) { return; }
  const r = await api('prepaid_move', {client: d.client, minutes: mins, note})
    .catch(e => ({ok: false, error: e && e.message}));
  if (!r.ok) { toast(r.error || 'Δεν καταχωρήθηκε', true); return; }
  toast('Νέο υπόλοιπο: ' + hm(r.balance));
  closeDrawer(); openPrepaid(d.client);
}
const hmAbs = m => hm(Math.abs(m));

/* ─────────────────────── Ακάλυπτα → προσφορά ─────────────────────── */

async function makeOffer(d, scope) {
  const ids = $$('.pp-u', scope).filter(x => x.checked).map(x => +x.value);
  if (!ids.length) { toast('Διάλεξε ποιες καταχωρήσεις μπαίνουν στην προσφορά', true); return; }
  const mins = d.uncovered.filter(u => ids.includes(u.id)).reduce((a, u) => a + u.open, 0);
  const rate = await cnpDialog({title: 'Προσφορά για ακάλυπτο χρόνο',
    body: `${ids.length} καταχωρήσεις · ${hm(mins)} ακάλυπτος χρόνος.\n\n`
      + 'Η προσφορά ανοίγει σε πρόχειρο — τη στέλνεις από τις Προσφορές. Μόλις γίνει '
      + 'αποδεκτή και συνδεθεί με έργο, ο χρόνος θα αντλείται από αυτήν.',
    input: d.rate ? String(d.rate) : '', placeholder: 'π.χ. 45', ok: 'Δημιουργία',
    hint: 'Τιμή ανά ώρα σε €'});
  if (rate === null || rate === '') { return; }
  const r = await api('prepaid_offer', {client: d.client, entries: ids,
    rate: parseFloat(String(rate).replace(',', '.'))})
    .catch(e => ({ok: false, error: e && e.message}));
  if (!r.ok) { toast(r.error || 'Δεν δημιουργήθηκε', true); return; }
  toast('Προσφορά ' + fmtEur(r.amount) + ' για ' + hm(r.minutes));
  closeDrawer();
  go('#/offers');
}

/* ─────────────────────── Ακάλυπτος χρόνος (όλοι) ─────────────────────── */

R.uncovered = async function () {
  setTop('Ακάλυπτος χρόνος', 'Χρεώσιμη δουλειά που δεν καλύφθηκε από προαγορά ή προσφορά');
  const c = $('#content');
  c.innerHTML = '<div class="skel" style="height:300px"></div>';
  let dErr = null;
  const d = await api('prepaid').catch(e => { dErr = e; return null; });
  if (!d) { c.innerHTML = cnpDenied(dErr); return; }
  const st = R.uncovered._s = R.uncovered._s || {q: ''};
  const all = d.rows.filter(r => r.uncovered > 0).sort((a, b) => b.uncovered - a.uncovered);
  /* Το φίλτρο δουλεύει πάνω στα ΗΔΗ φορτωμένα: η αναφορά δεν ξαναζητιέται για
     κάθε γράμμα, και το πλακίδιο μετράει ό,τι δείχνει η λίστα — όχι άλλο. */
  const rows = st.q
    ? all.filter(r => (r.name || '').toLowerCase().includes(st.q.toLowerCase()))
    : all;
  c.innerHTML = `
  <div class="fbar">
    ${fChip('Αναζήτηση', `<input class="fchip-s" id="ucQ" value="${esc(st.q)}"
      placeholder="όνομα πελάτη…" style="width:240px">`, !!st.q, '')}
    <span class="fbar-sp"></span>
    <span class="fbar-note">${rows.length} από ${all.length} πελάτες</span>
  </div>
  <div style="display:flex;gap:11px;flex-wrap:wrap;margin-bottom:16px">
    ${stat(I.alert, hm(rows.reduce((a, r) => a + r.uncovered, 0)), `σε ${rows.length} πελάτ${rows.length === 1 ? 'η' : 'ες'}`,
      rows.length ? 'var(--bad)' : 'var(--ok)')}
  </div>
  ${rows.length ? rows.map(r => `
    <div class="card pp-row" data-c="${r.client}" style="padding:12px 15px;margin-bottom:8px;cursor:pointer;display:flex;gap:12px;align-items:center;flex-wrap:wrap">
      <div style="flex:1;min-width:180px"><b>${esc(r.name)}</b>
        ${!r.contract ? '<span class="pill pill-bad">χωρίς συμβόλαιο</span>' : ''}
        <div class="mut" style="font-size:11.5px">διαθέσιμα: ${hm(r.balance)} προαγορά${r.offerLeft ? ' · ' + hm(r.offerLeft) + ' από προσφορές' : ''}</div></div>
      <div style="text-align:right"><b style="color:var(--bad);font-size:16px">${hm(r.uncovered)}</b>
        <div class="mut" style="font-size:10.5px">προς τιμολόγηση</div></div>
    </div>`).join('')
    : `<div class="empty" style="padding:44px">${I.sparkle}Τίποτα ακάλυπτο — όλη η χρεώσιμη δουλειά καλύπτεται.</div>`}`;
  $$('.pp-row').forEach(el => el.onclick = () => openPrepaid(+el.dataset.c));
  cnpSearch('ucQ', v => { st.q = v; return R.uncovered(); }, 220);
};

/* ─────────────────────── Προεπισκόπηση αναφοράς ─────────────────────── */

async function reportPreview(clientId, from, to) {
  const dr = drawer('Αναφορά προς τον πελάτη', '<div class="skel" style="height:300px"></div>', true);
  const body = $('#ppBody', dr);
  const d = await api(`prepaid_report&client=${clientId}&from=${from}&to=${to}`)
    .catch(e => ({error: e && e.message}));
  if (!d || d.error) { body.innerHTML = cnpDenied(d); return; }
  body.innerHTML = `
    <div class="mut" style="font-size:12px;margin-bottom:9px">Προς: <b>${esc(d.to.join(', ') || '— κανένας παραλήπτης —')}</b><br>Θέμα: ${esc(d.subject)}</div>
    <div style="border:1px solid var(--line);border-radius:10px;padding:14px;background:#fff;color:#222;max-height:50vh;overflow:auto">${d.html}</div>
    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:13px">
      <button class="btn btn-o" id="rpBack">Πίσω</button>
      <button class="btn btn-p" id="rpSend" ${d.to.length ? '' : 'disabled title="Ο πελάτης δεν έχει email"'}>${I.send} Αποστολή τώρα</button>
    </div>`;
  $('#rpBack', body).onclick = () => { closeDrawer(); openPrepaid(clientId); };
  const sn = $('#rpSend', body);
  if (sn) { sn.onclick = async () => {
    if (!await cnpConfirm('Να σταλεί η αναφορά στον πελάτη;')) { return; }
    const r = await api('prepaid_report_send', {client: clientId, from, to})
      .catch(e => ({ok: false, error: e && e.message}));
    if (!r.ok) { toast(r.error || 'Δεν στάλθηκε', true); return; }
    toast('Η αναφορά στάλθηκε');
    closeDrawer(); openPrepaid(clientId);
  }; }
}

window.openPrepaid = openPrepaid;

/* ═════════ ΔΡΑΣΤΗΡΙΟΤΗΤΑ — τι κάνει η ομάδα τώρα ═════════
   Δύο ερωτήσεις, δύο μέρη: «ποιος είναι μπροστά στην οθόνη και με τι
   καταπιάνεται αυτή τη στιγμή» και «τι έγινε πραγματικά». Η σελίδα ανανεώνεται
   μόνη της κάθε μισό λεπτό — παγώνει όταν φύγεις από την καρτέλα, για να μη
   χτυπάει τον server χωρίς λόγο. */

/* Τα ίδια χρώματα/ετικέτες με το chat και την πάνω μπάρα — η ετικέτα έρχεται
   έτοιμη από τον server (p.label), εδώ κρατάμε μόνο το χρώμα ως εφεδρεία. */
/* Ίδιες λέξεις και χρώματα με το μενού παρουσίας (CNP_ST) — όχι δεύτερο λεξιλόγιο. */
const ACT_ST = Object.fromEntries(CNP_ST.map(x => [x[0], [x[1], x[2]]]));
ACT_ST.busy = ['Απασχολημένος', '#e0552b'];
const ACT_ICO = {plus: 'plus', board: 'board', doc: 'doc', user: 'user', chat: 'chat',
  clock: 'clock', play: 'play', zap: 'zap', mail: 'mail', ticket: 'ticket'};
/** «πριν 3΄», «πριν 2ω», «χθες» */
function actAgo(s) {
  if (!s) { return '—'; }
  const d = Math.floor((Date.now() - new Date(String(s).replace(' ', 'T')).getTime()) / 1000);
  if (d < 60) { return 'μόλις τώρα'; }
  if (d < 3600) { return 'πριν ' + Math.floor(d / 60) + '΄'; }
  if (d < 86400) { return 'πριν ' + Math.floor(d / 3600) + 'ω'; }
  const days = Math.floor(d / 86400);
  return days === 1 ? 'χθες' : 'πριν ' + days + ' μέρες';
}
const actHm = m => {
  m = Math.round(+m || 0);
  const h = Math.floor(m / 60), r = m % 60;
  return h && r ? h + 'ω ' + r + '΄' : h ? h + 'ω' : r + '΄';
};

R.activity = async function () {
  setTop('Δραστηριότητα', 'Τι κάνει η ομάδα αυτή τη στιγμή');
  const c = $('#content');
  const st = R.activity._s = R.activity._s || {h: 24, who: 0, stale: false};
  c.innerHTML = '<div class="skel" style="height:80px;margin-bottom:14px"></div><div class="skel" style="height:400px"></div>';

  const paint = d => {
    const s = d.summary;
    const live = d.people.filter(p => !p.stale);
    const old = d.people.filter(p => p.stale);
    const busy = d.people.filter(p => p.timer || p.remote);

    const card = p => {
      const [lbl0, col] = ACT_ST[p.status] || ACT_ST.offline;
      const lbl = (p.label || lbl0) + (p.manual ? ' (το δήλωσε)' : '');
      const doing = p.timer
        ? `<div class="act-doing"><span class="act-live"></span>${I.clock}
             <b>${esc(p.timer.title || 'Εργασία')}</b>
             ${p.timer.project ? `<span class="mut">· ${esc(p.timer.project)}</span>` : ''}
             <span class="act-min">${actHm(p.timer.mins)}</span></div>`
        : p.remote
        ? `<div class="act-doing"><span class="act-live"></span>${I.monitor}
             <b>Απομακρυσμένη σύνδεση</b>
             ${p.remote.client ? `<span class="mut">· ${esc(p.remote.client)}</span>` : ''}
             <span class="act-min">${actAgo(p.remote.since)}</span></div>`
        : p.status === 'online'
        ? `<div class="act-doing idle">${I.eye} Στην εφαρμογή, χωρίς χρονόμετρο</div>`
        : p.lastAt
        ? `<div class="act-doing idle">${I.clock} Τελευταία κίνηση ${actAgo(p.lastAt)}${p.lastWhat ? ' · ' + esc(p.lastWhat) : ''}</div>`
        : '';
      return `<div class="card act-p${p.timer || p.remote ? ' busy' : ''}" data-who="${p.id}"
        style="padding:12px 14px;margin-bottom:9px;cursor:pointer${st.who === p.id ? ';border-color:var(--brand)' : ''}">
        <div style="display:flex;gap:11px;align-items:center;flex-wrap:wrap">
          <span class="act-ava" style="--sc:${col}">${esc(p.ini || '?')}</span>
          <div style="flex:1;min-width:150px">
            <div style="font-weight:650;font-size:13.5px">${esc(p.name)}
              <span class="mut" style="font-weight:500;font-size:11.5px">· ${lbl}${p.reason ? ' — ' + esc(p.reason) : ''}</span></div>
            ${doing}
          </div>
          <div class="act-nums">
            ${p.openTasks ? `<span title="ανοιχτές εργασίες">${I.checkSquare} ${p.openTasks}</span>` : ''}
            ${p.ball ? `<span title="περιμένουν ενέργειά του" style="color:var(--warn)">${I.zap} ${p.ball}</span>` : ''}
            ${p.tickets ? `<span title="tickets που χειρίζεται">${I.ticket} ${p.tickets}</span>` : ''}
            ${p.minsToday ? `<span title="χρόνος σήμερα" style="color:var(--brand)">${I.clock} ${actHm(p.minsToday)}</span>` : ''}
            ${p.doneToday ? `<span title="έκλεισε σήμερα" style="color:var(--ok)">✓ ${p.doneToday}</span>` : ''}
          </div>
        </div></div>`;
    };

    const feed = st.who ? d.feed.filter(e => e.admin === st.who) : d.feed;
    const byDay = {};
    feed.forEach(e => (byDay[e.at.slice(0, 10)] = byDay[e.at.slice(0, 10)] || []).push(e));
    const dayLbl = k => {
      const t = new Date().toISOString().slice(0, 10);
      const y = new Date(Date.now() - 86400000).toISOString().slice(0, 10);
      return k === t ? 'Σήμερα' : k === y ? 'Χθες'
        : new Date(k + 'T00:00').toLocaleDateString((window.CNP_LOCALE || 'el-GR'), {weekday: 'long', day: '2-digit', month: '2-digit'});
    };

    c.innerHTML = `
    <div class="fbar">
      ${fChip('Διάστημα', fSel('h', [['8', '8 ώρες'], ['24', '24 ώρες'], ['72', '3 μέρες']],
        String(st.h)), false, '')}
      ${st.who ? fChip('Χειριστής', `<b style="padding:0 6px">${esc((d.people.find(p => p.id === st.who) || {}).name || '')}</b>
        <span class="fchip-x" id="acAll" title="Όλοι">✕</span>`, true, '') : ''}
      ${old.length ? `<button type="button" class="fchip fchip-b${st.stale ? ' on' : ''}" id="acOld"
        title="Άτομα χωρίς καμία κίνηση εδώ και μέρες">${st.stale ? '✓ ' : ''}Και όσοι λείπουν
        <b>${old.length}</b></button>` : ''}
      <span class="fbar-sp"></span>
      <span class="fbar-note">ανανεώνεται μόνο του κάθε 30΄΄</span>
      <button class="fchip" id="acRef" title="Ανανέωση τώρα">↻ Ανανέωση</button>
    </div>
    <div style="display:flex;gap:11px;flex-wrap:wrap;align-items:center;margin-bottom:16px">
      ${actStat(I.users, s.online + '/' + s.team, 'στην εφαρμογή τώρα', s.online ? 'var(--ok)' : 'var(--mut)')}
      ${actStat(I.play, s.working, s.working === 1 ? 'δουλεύει αυτή τη στιγμή' : 'δουλεύουν αυτή τη στιγμή', s.working ? 'var(--brand)' : 'var(--mut)')}
      ${actStat(I.clock, actHm(s.minsToday), 'χρόνος σήμερα', 'var(--violet)')}
      ${actStat(I.ticket, s.repliesToday, 'απαντήσεις σήμερα', 'var(--info)')}
      ${actStat(I.checkSquare, s.doneToday, 'έκλεισαν σήμερα', s.doneToday ? 'var(--ok)' : 'var(--mut)')}
    </div>
    <div id="actPhone" hidden></div>

    <div class="act-h">
      <b>Η ομάδα τώρα</b>
      ${busy.length ? `<span class="pill pill-ok">${busy.length} σε εξέλιξη</span>` : '<span class="mut" style="font-size:12px">κανένα χρονόμετρο σε εξέλιξη</span>'}
    </div>
    ${live.map(card).join('') || '<div class="mut" style="padding:10px 2px">Κανείς ενεργός.</div>'}
    ${st.stale && old.length ? old.map(card).join('') : ''}

    <div class="act-h" style="margin-top:18px"><b>Ροή</b></div>
    ${Object.keys(byDay).length ? Object.entries(byDay).map(([k, list]) => `
      <div class="act-day">${dayLbl(k)}</div>
      ${list.map(e => `<div class="act-row"${e.task ? ` data-task="${e.task}"` : (e.ticket ? ` data-tk="${e.ticket}"` : '')}>
        <span class="act-time">${e.at.slice(11, 16)}</span>
        <span class="act-i">${I[ACT_ICO[e.icon]] || I.doc}</span>
        <span class="act-txt"><b>${esc(e.who)}</b> ${esc(e.verb)}
          ${e.what ? `<span class="act-what">${esc(e.what)}</span>` : ''}
          ${e.tnum ? `<span class="mut">#${e.tnum}</span>` : ''}
          ${e.project ? `<span class="mut">· ${esc(e.project)}</span>` : ''}
          ${e.note && e.note !== e.what ? `<span class="mut">— ${esc(e.note)}</span>` : ''}</span>
      </div>`).join('')}`).join('')
      : `<div class="empty" style="padding:36px">${I.sparkle}Καμία κίνηση ${st.who ? 'από αυτό το άτομο ' : ''}στο διάστημα που διάλεξες</div>`}
`;

    $$('.act-p').forEach(el => el.onclick = () => {
      const id = +el.dataset.who;
      st.who = st.who === id ? 0 : id;
      paint(d);
    });
    /* Το διάστημα ξαναφορτώνει μόνο τα δεδομένα (load), δεν ξαναστήνει την
       οθόνη — το φίλτρο χειριστή και η κύλιση μένουν όπου ήταν. */
    { const hs = $('[data-fk="h"]'); if (hs) { hs.onchange = e => { st.h = +e.target.value; load(); }; } }
    const ao = $('#acOld'); if (ao) { ao.onclick = () => { st.stale = !st.stale; paint(d); }; }
    const aa = $('#acAll'); if (aa) { aa.onclick = e => { e.stopPropagation(); st.who = 0; paint(d); }; }
    $('#acRef').onclick = () => load();
    $$('.act-row[data-task]').forEach(el => el.onclick = () => openTask(+el.dataset.task));
    $$('.act-row[data-tk]').forEach(el => el.onclick = () => go('#/inbox/' + el.dataset.tk));
  };

  /* ── Στο τηλέφωνο τώρα ─────────────────────────────────────────────────
     Η τηλεφωνική δραστηριότητα μπαίνει ΕΔΩ, μαζί με την υπόλοιπη δουλειά της
     ομάδας — όχι σε ξεχωριστή οθόνη. Μια κλήση είναι δουλειά όπως κάθε άλλη.
     Αν το κέντρο δεν απαντά, η λωρίδα απλώς δεν εμφανίζεται· η Δραστηριότητα
     δεν εξαρτάται ποτέ από το τηλεφωνικό κέντρο. */
  const paintPhone = async () => {
    const box = $('#actPhone');
    if (!box) { return; }
    const r = await api('pbx_live').catch(() => null);
    if (!r || !r.on) { box.hidden = true; return; }
    const cs = r.calls || [];
    box.hidden = false;
    const mmss = x => Math.floor(x / 60) + '΄' + String(x % 60).padStart(2, '0') + '΄΄';
    box.innerHTML = `<div class="card"><div class="card-h">${I.phone} Στο τηλέφωνο τώρα
      <span class="pill ${cs.length ? 'pill-ok' : 'pill-mut'}" style="margin-left:6px">${cs.length}</span></div>
      <div class="card-b">${cs.length ? `<div class="ph-list">${cs.map(x => `
        <div class="ph-row">
          <span class="ph-dot ${x.answered ? 'on' : 'ring'}"></span>
          <span class="ph-who">${x.adminName
            ? `<b>${esc(x.adminName)}</b>` : `<span class="mut">άγνωστο extension</span>`}</span>
          <span class="ph-arrow">↔</span>
          <span class="ph-other">${esc(x.other || '—')}</span>
          <span class="ph-st">${x.answered ? 'σε συνομιλία' : esc(x.status || 'κουδουνίζει')}</span>
          <span class="ph-t">${x.seconds ? mmss(x.seconds) : ''}</span>
        </div>`).join('')}</div>`
        : '<div class="mut" style="font-size:12.5px">Κανείς στο τηλέφωνο αυτή τη στιγμή.</div>'}
      </div></div>`;
  };

  const load = async () => {
    let e0 = null;
    const d = await api('activity&h=' + st.h).catch(e => { e0 = e; return null; });
    if (!d) { c.innerHTML = cnpDenied(e0); return false; }
    if (S.view !== 'activity') { return false; }   // άλλαξε οθόνη όσο φόρτωνε
    paint(d);
    paintPhone();
    return true;
  };

  await load();
  /* Ένα μόνο χρονόμετρο, που σβήνει όταν φύγεις από την οθόνη. */
  clearInterval(R.activity._t);
  R.activity._t = setInterval(() => {
    if (S.view !== 'activity') { clearInterval(R.activity._t); return; }
    if (document.hidden) { return; }
    load();
  }, 30000);
};
const actStat = (ic, n, l, col) => `<div class="su-stat"><div class="ic" style="background:${col}1a;color:${col}">${ic}</div>
  <div><div class="n">${n}</div><div class="l">${l}</div></div></div>`;

/* ═════════ ⚠ ΠΑΡΑΠΟΝΑ ΠΕΛΑΤΩΝ ═════════
   Όποιος μιλάει με πελάτη ακούει και παράπονα. Αν δεν γραφτούν εκείνη τη
   στιγμή χάνονται — και μαζί τους το μοτίβο που τα γεννάει. Η οθόνη έχει δύο
   δουλειές: να καταχωρείς σε δεκαπέντε δευτερόλεπτα, και να βλέπεις τι
   επαναλαμβάνεται. */

const CX_CAT = {delay: ['Καθυστέρηση', '#e0a020'], quality: ['Ποιότητα δουλειάς', '#c0392b'],
  comm: ['Επικοινωνία / ενημέρωση', '#0090dd'], billing: ['Χρέωση / τιμολόγηση', '#7b5cd6'],
  outage: ['Διακοπή / βλάβη', '#e2515f'], attitude: ['Συμπεριφορά', '#d95f9a'],
  other: ['Άλλο', '#8595ac']};
const CX_SRC = {call: 'Τηλεφωνικά', email: 'Email', ticket: 'Μέσω ticket',
  meeting: 'Σε συνάντηση', visit: 'Επιτόπου', other: 'Άλλο'};
const CX_ST = {open: ['Ανοιχτό', 'var(--bad)'], progress: ['Σε χειρισμό', 'var(--warn)'],
  resolved: ['Λύθηκε', 'var(--ok)'], rejected: ['Αβάσιμο', 'var(--mut)']};
const CX_SEV = {1: ['Ήπιο', 'var(--mut)'], 2: ['Σοβαρό', 'var(--warn)'], 3: ['Κρίσιμο', 'var(--bad)']};
const cxCat = c => CX_CAT[c] || CX_CAT.other;

R.complaints = async function () {
  setTop('Παράπονα πελατών', 'Τι μας είπαν, τι κάναμε, τι επαναλαμβάνεται');
  const c = $('#content');
  const st = R.complaints._s = R.complaints._s || {status: 'live', cat: '', mine: false};
  c.innerHTML = '<div class="skel" style="height:100px;margin-bottom:14px"></div><div class="skel" style="height:340px"></div>';
  let dErr = null;
  const qs = `complaints&status=${st.status}${st.cat ? '&cat=' + st.cat : ''}${st.mine ? '&mine=1' : ''}`;
  const d = await api(qs).catch(e => { dErr = e; return null; });
  if (!d) { c.innerHTML = cnpDenied(dErr); return; }
  const s = d.summary;

  const row = r => {
    const [sl, sc] = CX_ST[r.status] || CX_ST.open;
    const [cn, cc] = cxCat(r.category);
    const live = r.status === 'open' || r.status === 'progress';
    return `<div class="card cx-row" data-cx="${r.id}">
      <span class="cx-sev s${r.severity}" title="${CX_SEV[r.severity][0]}"></span>
      <div class="cx-main">
        <div class="cx-top"><b>${esc(r.summary)}</b></div>
        <div class="cx-meta">
          <span class="cx-cat" style="--c:${cc}">${esc(cn)}</span>
          <span>${esc(r.name)}${r.contact && r.client ? ' · ' + esc(r.contact) : ''}</span>
          <span class="mut">${esc(d.sources[r.source] || r.source)}</span>
          <span class="mut">${dShort(r.at)}${live && r.ageDays > 2 ? ` · <b style="color:var(--warn)">${r.ageDays} μέρες ανοιχτό</b>` : ''}</span>
          ${r.by ? `<span class="mut">κατέγραψε ${esc(r.by)}</span>` : ''}
        </div>
      </div>
      <div class="cx-right">
        <span class="pill" style="background:${sc}1e;color:${sc}">${sl}</span>
        ${r.ownerName ? `<span class="mut" style="font-size:11px">${esc(r.ownerName)}</span>`
          : (live ? '<span class="pill pill-warn" style="font-size:9.5px">χωρίς υπεύθυνο</span>' : '')}
        ${r.informed ? `<span class="mut" style="font-size:10.5px" title="Γυρίσαμε στον πελάτη">✓ ενημερώθηκε</span>` : ''}
      </div></div>`;
  };

  /* Τα νούμερα ΕΙΝΑΙ τα φίλτρα (docs/UI-STANDARD.md §8β): η σειρά κουμπιών
     «Ενεργά / Λυμένα / Αβάσιμα / Όλα» έλεγε ό,τι λένε τα πλακίδια από πάνω. */
  const {cnpKpis} = window.CNP;
  c.innerHTML = `
  <div class="fbar">
    ${fChip('Κατηγορία', fSel('cat', [['', '— κάθε —']]
      .concat(d.cats.map(x => [String(x.id), x.name])), st.cat ? String(st.cat) : ''), !!st.cat, '')}
    <button type="button" class="fchip fchip-b${st.mine ? ' on' : ''}" id="cxMine">${
      st.mine ? '✓ ' : ''}Δικά μου</button>
    <button type="button" class="fchip${st.status === '' ? ' on' : ''}" id="cxAll" title="Όλα τα παράπονα, σε κάθε κατάσταση">${I.clock} Ιστορικό</button>
    <span class="fbar-sp"></span>
    <button class="fchip fchip-go" id="cxNew">${I.plus} Νέο παράπονο</button>
  </div>
  <div class="dbar">${cnpKpis([
    {n: s.open, label: s.open === 1 ? 'ανοιχτό<br>παράπονο' : 'ανοιχτά<br>παράπονα', color: s.open ? 'var(--bad)' : 'var(--ok)',
      tip: 'Παράπονα που δεν έχουν κλείσει — κλικ για να τα δεις', act: 'live', on: st.status === 'live'},
    {n: s.critical, label: 'κρίσιμα σε<br>εκκρεμότητα', color: s.critical ? 'var(--bad)' : null,
      tip: 'Ανοιχτά με υψηλή σοβαρότητα'},
    {n: s.resolved, label: 'λύθηκαν', color: s.resolved ? 'var(--ok)' : null,
      tip: 'Παράπονα που έκλεισαν λυμένα', act: 'resolved', on: st.status === 'resolved'},
    {n: s.rejected, label: 'αβάσιμα', tip: 'Κρίθηκαν αβάσιμα', act: 'rejected', on: st.status === 'rejected'},
    {n: s.avgDays === null ? '—' : s.avgDays, label: 'μέρες μέση<br>επίλυση',
      tip: s.avgDays === null ? 'Δεν έχει κλείσει κανένα παράπονο τις τελευταίες 90 ημέρες' : 'Μέσος όρος στα λυμένα των 90 ημερών'},
    {n: s.month, label: 'νέα αυτόν<br>τον μήνα', tip: 'Πόσα μπήκαν από την 1η του μήνα'},
  ])}</div>

  ${s.byCat.length ? `<div class="card cx-pat"><div class="card-b">
    <label class="lbl" style="margin:0 0 9px">${I.chart} Τι επαναλαμβάνεται — τελευταίο εξάμηνο</label>
    <div class="cx-bars">${(() => { const mx = Math.max(...s.byCat.map(x => x.n));
      return s.byCat.map(x => `<div class="cx-bar" data-cat="${x.id}" title="Φίλτρο σε «${esc(x.name)}»">
        <span class="cx-bar-l">${esc(x.name)}</span>
        <span class="cx-bar-t"><i style="width:${Math.round(x.n / mx * 100)}%;background:${x.color}"></i></span>
        <b>${x.n}</b></div>`).join(''); })()}</div>
    ${s.repeat.length ? `<div class="cx-rep">${I.alert} Επαναλαμβανόμενα ανά πελάτη:
      ${s.repeat.map(x => `<a class="pill pill-bad" href="#/client360/${x.client}">${esc(x.name)} · ${x.n}</a>`).join(' ')}</div>` : ''}
  </div></div>` : ''}



  <div id="cxRows">${d.rows.length ? d.rows.map(row).join('')
    : `<div class="empty" style="padding:44px">${I.sparkle}Κανένα παράπονο με αυτά τα κριτήρια.
       <div class="mut" style="font-size:12.5px;margin-top:6px">Όταν ένας πελάτης εκφράσει δυσαρέσκεια, γράψ' την εδώ — αλλιώς χάνεται.</div></div>`}</div>`;

  $$('[data-dkact]').forEach(b => b.onclick = () => { st.status = b.dataset.dkact; R.complaints(); });
  $('#cxAll').onclick = () => { st.status = st.status === '' ? 'live' : ''; R.complaints(); };
  { const cc = $('[data-fk="cat"]'); if (cc) { cc.onchange = e => { st.cat = e.target.value; R.complaints(); }; } }
  /* Κουμπί που ανάβει αντί για κουτάκι — δες docs/UI-STANDARD.md §3. */
  $('#cxMine').onclick = () => { st.mine = !st.mine; R.complaints(); };
  $('#cxNew').onclick = () => quickCx();
  $$('.cx-row').forEach(el => el.onclick = () => openCx(+el.dataset.cx));
  $$('.cx-bar').forEach(el => el.onclick = () => { st.cat = el.dataset.cat; R.complaints(); });
};
const cxStat = (ic, n, l, col) => `<div class="su-stat"><div class="ic" style="background:${col}1a;color:${col}">${ic}</div>
  <div><div class="n">${n}</div><div class="l">${l}</div></div></div>`;

/* ───────── Γρήγορη καταχώρηση ───────── */
function quickCx(pre) {
  if (!cnpCan('support.complaints')) { toast('Δεν έχεις δικαίωμα καταχώρησης παραπόνου', true); return; }
  closeDrawer();
  const who = {id: 0, name: '', type: null};
  const ovl = document.createElement('div');
  ovl.className = 'ovl show';
  ovl.innerHTML = `<div class="pal-box qc-box" onclick="event.stopPropagation()">
    <div class="qc-h"><b>${I.alert} Παράπονο πελάτη</b></div>
    <div class="qc-b">
      <label class="lbl">Ποιος πελάτης</label>
      <input class="inp" id="cxWho" placeholder="Όνομα, επωνυμία ή αριθμός τηλεφώνου…" autocomplete="off">
      <div id="cxPick"></div>
      <div id="cxSel" class="qc-sel" hidden></div>
      <input class="inp" id="cxContact" placeholder="Ποιος μίλησε μαζί σου (όνομα ατόμου) — προαιρετικό"
        style="margin-top:7px;font-size:12.5px">

      <label class="lbl" style="margin-top:12px">Το παράπονο <span class="mut" style="font-weight:400">— με τα δικά του λόγια, μία γραμμή</span></label>
      <input class="inp" id="cxSum" placeholder="π.χ. Περίμενα τρεις μέρες για απάντηση και δεν με πήρε κανείς" autocomplete="off">

      <label class="lbl" style="margin-top:12px">Λεπτομέρειες</label>
      <textarea class="inp" id="cxDet" rows="2" placeholder="Τι ακριβώς έγινε, πότε, ποιον αφορά"></textarea>

      <label class="lbl" style="margin-top:12px">Τι αφορά</label>
      <div class="cx-cats">${Object.entries(CX_CAT).map(([k, v]) =>
        `<button class="cx-pick ${k === 'other' ? 'on' : ''}" data-cat="${k}" style="--c:${v[1]}">${esc(v[0])}</button>`).join('')}</div>

      <div class="qc-row">
        <div><label class="lbl">Σοβαρότητα</label>
          <div class="td-seg" id="cxSev">
            <button data-sev="1">Ήπιο</button><button data-sev="2" class="on">Σοβαρό</button>
            <button data-sev="3">Κρίσιμο</button></div></div>
        <div><label class="lbl">Πώς μας το είπε</label>
          <select class="inp" id="cxSrc">${Object.entries(CX_SRC).map(([k, v]) =>
            `<option value="${k}">${esc(v)}</option>`).join('')}</select></div>
      </div>

      <label class="lbl" style="margin-top:12px">Ποιος το αναλαμβάνει <span class="mut" style="font-weight:400">— προαιρετικό</span></label>
      <select class="inp" id="cxOwn" style="max-width:260px"><option value="">— κανείς ακόμη —</option>
        ${(S.boot.admins || []).map(a => `<option value="${a.id}">${esc(a.name)}</option>`).join('')}</select>
      <div class="cx-note" id="cxSevHint"></div>
    </div>
    <div class="qc-f">
      <div style="flex:1"></div>
      <button class="btn btn-o" id="cxX">Άκυρο</button>
      <button class="btn btn-p" id="cxOk">Καταχώρηση</button>
    </div></div>`;
  document.body.appendChild(ovl);
  const $q = s2 => ovl.querySelector(s2);
  const close = () => { ovl.remove(); document.removeEventListener('keydown', onEsc, true); };
  const onEsc = e => { if (e.key === 'Escape') { e.stopPropagation(); close(); } };
  document.addEventListener('keydown', onEsc, true);
  ovl.onclick = close;
  $q('#cxX').onclick = close;

  const showSel = () => {
    const el = $q('#cxSel');
    if (!who.id && !who.name) { el.hidden = true; return; }
    el.hidden = false;
    el.innerHTML = `<span class="pill ${who.id ? 'pill-ok' : 'pill-warn'}">${who.id ? I.user : I.alert} ${esc(who.name)}${who.id ? '' : ' — δεν βρέθηκε στο μητρώο'}</span>
      <button class="qc-clr">✕</button>`;
    el.querySelector('.qc-clr').onclick = () => { who.id = 0; who.name = ''; $q('#cxWho').value = ''; showSel(); };
  };
  let tmr = null;
  $q('#cxWho').oninput = () => {
    clearTimeout(tmr);
    const v = $q('#cxWho').value.trim();
    who.id = 0; who.name = v; showSel();
    if (v.length < 3) { $q('#cxPick').innerHTML = ''; return; }
    tmr = setTimeout(async () => {
      const r = await api('call_who&q=' + encodeURIComponent(v)).catch(() => null);
      const list = ((r && r.results) || []).filter(x => x.type === 'client');
      $q('#cxPick').innerHTML = list.length ? `<div class="qc-list">${list.map(x =>
        `<div class="qc-opt" data-i="${x.id}" data-n="${esc(x.name)}">${I.user}<b>${esc(x.name)}</b>
          ${x.phone ? `<span class="mut">${esc(x.phone)}</span>` : ''}</div>`).join('')}</div>` : '';
      $$('.qc-opt', ovl).forEach(el => el.onclick = () => {
        who.id = +el.dataset.i; who.name = el.dataset.n;
        $q('#cxWho').value = who.name; $q('#cxPick').innerHTML = '';
        showSel(); $q('#cxSum').focus();
      });
    }, 240);
  };
  let cat = 'other', sev = 2;
  $$('.cx-pick', ovl).forEach(b => b.onclick = () => {
    cat = b.dataset.cat;
    $$('.cx-pick', ovl).forEach(x => x.classList.toggle('on', x === b));
  });
  $$('[data-sev]', ovl).forEach(b => b.onclick = () => {
    sev = +b.dataset.sev;
    $$('[data-sev]', ovl).forEach(x => x.classList.toggle('on', x === b));
    $q('#cxSevHint').innerHTML = sev === 3
      ? `<span style="color:var(--bad)">${I.alert} Κρίσιμο — ειδοποιούνται αμέσως όσοι χειρίζονται παράπονα.</span>` : '';
  });

  const save = async () => {
    const sum = $q('#cxSum').value.trim();
    if (!sum) { toast('Γράψε το παράπονο', true); $q('#cxSum').focus(); return; }
    const btn = $q('#cxOk'); btn.disabled = true; btn.textContent = '…';
    const r = await api('complaint_save', {client: who.id, contact: $q('#cxContact').value.trim(),
      summary: sum, detail: $q('#cxDet').value.trim(), category: cat, severity: sev,
      source: $q('#cxSrc').value, owner: +$q('#cxOwn').value || 0})
      .catch(e => ({ok: false, error: e && e.message}));
    btn.disabled = false; btn.textContent = 'Καταχώρηση';
    if (!r.ok) { toast(r.error || 'Δεν καταχωρήθηκε', true); return; }
    close();
    toast('Το παράπονο καταγράφηκε');
    if (S.view === 'complaints') { R.complaints(); } else { go('#/complaints'); }
  };
  $q('#cxOk').onclick = save;
  $q('#cxSum').onkeydown = e => { if (e.key === 'Enter') { e.preventDefault(); save(); } };
  if (pre && pre.client) { who.id = pre.client; who.name = pre.name || ''; $q('#cxWho').value = who.name; showSel(); $q('#cxSum').focus(); }
  else { $q('#cxWho').focus(); }
}
window.CNP.quickCx = quickCx;
/* Ο κανόνας των shared helpers: ό,τι χρειάζεται άλλο view module περνά ΜΟΝΟ
   από το window.CNP. Το drawer() το θέλει και ο τηλεφωνικός κατάλογος. */
window.CNP.drawer = drawer;

/* ───────── Η καρτέλα του παραπόνου ───────── */
async function openCx(id) {
  closeDrawer();
  const ovl = document.createElement('div'); ovl.className = 'ovl';
  const dr = document.createElement('div'); dr.className = 'drawer tk-modal';
  dr.innerHTML = `<div class="drawer-h"><h2>${I.alert} Παράπονο</h2><button class="drawer-x" id="dX">✕</button></div>
    <div class="drawer-b" id="cxBody"><div class="skel" style="height:280px"></div></div>`;
  document.body.append(ovl, dr);
  requestAnimationFrame(() => { ovl.classList.add('show'); dr.classList.add('show'); });
  $('#dX', dr).onclick = () => closeDrawer();
  ovl.onclick = () => closeDrawer();
  const body = $('#cxBody', dr);
  let dErr = null;
  const d = await api('complaint&id=' + id).catch(e => { dErr = e; return null; });
  if (!d) { body.innerHTML = cnpDenied(dErr); return; }
  const x = d.cx;
  const [sl, sc] = CX_ST[x.status] || CX_ST.open;
  const [cn, cc] = cxCat(x.category);
  const live = x.status === 'open' || x.status === 'progress';

  body.innerHTML = `
    <div style="display:flex;gap:9px;align-items:center;flex-wrap:wrap;margin-bottom:11px">
      <span class="pill" style="background:${sc}1e;color:${sc}">${sl}</span>
      <span class="cx-cat" style="--c:${cc}">${esc(cn)}</span>
      <span class="pill" style="background:${CX_SEV[x.severity][1]}1e;color:${CX_SEV[x.severity][1]}">${CX_SEV[x.severity][0]}</span>
      <span class="mut" style="font-size:12px">${esc(CX_SRC[x.source] || x.source)} · ${dFull(x.at)}</span>
    </div>
    <div style="font-size:15.5px;font-weight:650;line-height:1.4">${esc(x.summary)}</div>
    <div class="mut" style="font-size:12.5px;margin-top:4px">
      ${x.client ? `<a href="#/client360/${x.client}">${esc(x.name)}</a>` : esc(x.name)}
      ${x.contact ? ' · ' + esc(x.contact) : ''}${x.by ? ' · κατέγραψε ' + esc(x.by) : ''}</div>
    ${x.detail ? `<div class="cx-detail">${esc(x.detail)}</div>` : ''}

    <div class="card" style="margin-top:13px"><div class="card-b">
      <div class="qc-row" style="margin-top:0">
        <div><label class="lbl">Υπεύθυνος</label>
          <select class="inp" id="cxOwner"><option value="">— κανείς —</option>
            ${d.cx && (window.__cxAdmins || []).length ? '' : ''}
            ${(S.boot.admins || []).map(a => `<option value="${a.id}" ${a.id === x.owner ? 'selected' : ''}>${esc(a.name)}</option>`).join('')}</select></div>
        <div><label class="lbl">Κατάσταση</label>
          <div class="td-seg" id="cxLive">
            <button data-s="open" class="${x.status === 'open' ? 'on' : ''}" ${live ? '' : 'disabled'}>Ανοιχτό</button>
            <button data-s="progress" class="${x.status === 'progress' ? 'on' : ''}" ${live ? '' : 'disabled'}>Σε χειρισμό</button>
          </div></div>
      </div>
      <label class="mut" style="display:flex;align-items:center;gap:6px;margin-top:11px;font-size:12.5px">
        <input type="checkbox" id="cxInf" ${x.informed ? 'checked' : ''}> Γυρίσαμε στον πελάτη και τον ενημερώσαμε</label>
    </div></div>

    ${!live ? `<div class="card" style="margin-top:12px;border-left:3px solid ${sc}"><div class="card-b">
      <label class="lbl" style="margin:0 0 6px">Έκβαση</label>
      <div style="font-size:13px;white-space:pre-wrap">${esc(x.resolution || '—')}</div>
      ${x.cause ? `<div class="mut" style="font-size:12px;margin-top:6px">Αιτία: <b>${esc(x.cause)}</b></div>` : ''}
      <div class="mut" style="font-size:11.5px;margin-top:5px">${dFull(x.resolvedAt)}</div>
    </div></div>` : ''}

    <div class="card" style="margin-top:12px"><div class="card-b">
      <label class="lbl" style="margin:0 0 8px">${I.list} Χειρισμός</label>
      ${d.notes.map(n => `<div class="cx-n">
        <span class="cx-n-k k-${n.kind}"></span>
        <div><div style="font-size:12.5px;white-space:pre-wrap">${esc(n.body)}</div>
          <div class="mut" style="font-size:11px">${esc(n.who)} · ${dFull(n.at)}</div></div></div>`).join('')}
      <div style="display:flex;gap:6px;margin-top:9px">
        <input class="inp" id="cxNoteIn" placeholder="Τι έγινε; (Enter)" style="flex:1">
      </div>
    </div></div>

    ${live && d.canClose ? `<div style="display:flex;gap:8px;justify-content:flex-end;margin-top:13px;flex-wrap:wrap">
      <button class="btn btn-o" id="cxRej">Αβάσιμο</button>
      <button class="btn btn-p" id="cxRes">${I.check || ''} Κλείσιμο με έκβαση</button></div>`
      : (live ? '<div class="mut" style="font-size:11.5px;margin-top:12px;text-align:right">Το κλείσιμο το κάνει όποιος έχει το δικαίωμα «Κλείσιμο παραπόνου».</div>' : '')}`;

  const reload = () => { closeDrawer(); openCx(id); };
  $('#cxOwner', body).onchange = async e => {
    await api('complaint_status', {id, owner: +e.target.value || 0});
    toast('Ενημερώθηκε ο υπεύθυνος'); reload();
  };
  $$('[data-s]', body).forEach(b => b.onclick = async () => {
    if (b.disabled) { return; }
    await api('complaint_status', {id, status: b.dataset.s}); reload();
  });
  $('#cxInf', body).onchange = async e => {
    await api('complaint_status', {id, informed: e.target.checked});
    toast(e.target.checked ? 'Σημειώθηκε ότι ενημερώθηκε' : 'Αναιρέθηκε'); reload();
  };
  $('#cxNoteIn', body).onkeydown = async e => {
    if (e.key !== 'Enter' || !e.target.value.trim()) { return; }
    await api('complaint_note', {id, body: e.target.value.trim()}); reload();
  };
  const close2 = async rejected => {
    const res = await cnpPrompt(rejected
      ? 'Γιατί κρίνεται αβάσιμο; Θα το διαβάσει όποιος το κατέγραψε.'
      : 'Τι έγινε τελικά; Η έκβαση μένει στο ιστορικό του πελάτη.',
      {title: (rejected ? 'Αβάσιμο' : 'Κλείσιμο') + ' παραπόνου', input: '', rows: 3, max: 4000,
        ok: rejected ? 'Καταχώρηση' : 'Κλείσιμο'});
    if (res === null || !res.trim()) { return; }
    let cause = '';
    if (!rejected) {
      cause = await cnpPrompt('Τι το προκάλεσε; Μία-δυο λέξεις — έτσι βλέπεις τι επαναλαμβάνεται.',
        {title: 'Αιτία', input: '', placeholder: 'π.χ. λάθος εκτίμηση χρόνου', ok: 'Αποθήκευση'}) || '';
    }
    const r = await api('complaint_resolve', {id, status: rejected ? 'rejected' : 'resolved',
      resolution: res.trim(), cause: (cause || '').trim(), informed: $('#cxInf', body).checked})
      .catch(e => ({ok: false, error: e && e.message}));
    if (!r.ok) { toast(r.error || 'Δεν έκλεισε', true); return; }
    toast(rejected ? 'Καταχωρήθηκε ως αβάσιμο' : 'Το παράπονο έκλεισε');
    closeDrawer();
    if (S.view === 'complaints') { R.complaints(); }
  };
  const br = $('#cxRes', body); if (br) { br.onclick = () => close2(false); }
  const bj = $('#cxRej', body); if (bj) { bj.onclick = () => close2(true); }
}
window.openCx = openCx;

/* ═══════════ Η μέρα της ομάδας ═══════════
   Η ερώτηση είναι «με τι ασχολείται σήμερα η ομάδα» και έχει τέσσερις όψεις,
   όχι μία: τι ορίστηκε για σήμερα, τι διαρκεί μέσα από το σήμερα, τι άνοιξε
   σήμερα, και — το πιο αποκαλυπτικό — τι κουβαλιέται από προηγούμενες μέρες.
   Μια εργασία μπορεί να είναι σε δύο όψεις ταυτόχρονα· αυτό ΕΙΝΑΙ η αλήθεια. */
R.teamday = async function () {
  setTop('Η μέρα της ομάδας', 'Τι είναι στο τραπέζι σήμερα — και τι κουβαλιέται από πριν');
  const c = $('#content');
  /* Ανοιχτά by default μόνο όσα ζητούν ενέργεια. Τα «άνοιξαν σήμερα» και τα
     «περνάνε από το σήμερα» είναι συμφραζόμενα — ο αριθμός τους φαίνεται στην
     κεφαλίδα και ανοίγουν με ένα κλικ. Αλλιώς η σελίδα γίνεται τοίχος. */
  const st = R.teamday._s = R.teamday._s || {closed: {spanning: 1, opened: 1}, who: 0};
  c.innerHTML = '<div class="skel" style="height:90px;margin-bottom:14px"></div><div class="skel" style="height:420px"></div>';
  const d = await api('teamday').catch(() => null);
  if (!d) { c.innerHTML = '<div class="card"><div class="card-b mut">Δεν φορτώθηκε.</div></div>'; return; }

  const BUCKETS = [
    ['running',  I.play,        'Δουλεύονται τώρα',   'ανοιχτό χρονόμετρο',              '#16a26a'],
    /* ΠΡΟΣΟΧΗ στην ετικέτα: ο server χτίζει αυτόν τον κουβά από schedule_date
       παλιότερης μέρας — «ήταν στο πλάνο και δεν έγινε». ΔΕΝ είναι «πέρασε το
       deadline» (αυτό φαίνεται στη στήλη ημερομηνίας, με κόκκινο). */
    ['carried',  I.alert,       'Κουβαλιούνται από πριν', 'ήταν σε πλάνο παλιότερης μέρας και μένουν ανοιχτά', '#e2515f'],
    ['planned',  I.checkSquare, 'Για σήμερα',         'deadline ή λήξη σήμερα',          '#0090dd'],
    ['spanning', I.gantt || I.chart, 'Περνάνε από σήμερα', 'ξεκίνησαν πριν, λήγουν μετά', '#7b5cd6'],
    ['opened',   I.plus,        'Άνοιξαν σήμερα',     'γεννήθηκαν μέσα στη μέρα',        '#8595ac'],
  ];
  const KEY = Object.fromEntries(BUCKETS.map(b => [b[0], b]));

  const hit = t => !st.who || t.whoId === st.who;
  const ini = n => (n || '?').trim().split(/\s+/).map(w => w[0] || '').slice(0, 2).join('').toUpperCase();

  /* ── Γραμμή εργασίας: σταθερές στήλες, ώστε το μάτι να κατεβαίνει ίσια ──
     Πριν ήταν στοιβαγμένα chips δεξιά, με διαφορετικό πλάτος σε κάθε γραμμή —
     τίποτα δεν στοιχιζόταν και η σάρωση ήταν αδύνατη. */
  const line = t => {
    const late = t.due && t.due < d.date;
    const timeChip = t.running !== null && t.running !== undefined && t.running !== false
      ? `<span class="td-t run" title="Τρέχει τώρα">▶ ${hm(t.running)}</span>`
      : (t.spent ? `<span class="td-t" title="Χρόνος σήμερα">${hm(t.spent)}</span>` : '');
    return `<div class="td-row" data-tgo="${t.id}">
      <span class="td-dot" style="background:${t.color || '#8595ac'}"></span>
      <span class="td-title${t.done ? ' done' : ''}" title="${esc(t.title)}">${esc(t.title)}</span>
      ${t.statusId ? stPill(t.statusId) : ''}
      <span class="td-time">${timeChip}</span>
      <span class="td-proj">${t.project ? `<span class="td-tag" title="${esc(t.project)}">${esc(t.project)}</span>` : ''}${t.internal ? '<span class="td-tag rnd">R&D</span>' : ''}</span>
      <span class="td-who">${t.who
        ? `<span class="td-av" title="${esc(t.who)}">${esc(ini(t.who))}</span><span class="td-wn">${esc(t.who)}</span>`
        : '<span class="mut" style="font-size:11.5px">χωρίς ανάθεση</span>'}</span>
      <span class="td-ball">${t.ball && t.ball !== t.whoId
        ? `<span class="td-wait" title="Ανατεθειμένη αλλού, αλλά η μπάλα είναι σε αυτόν">${I.zap} ${esc(t.ballName)}</span>` : ''}</span>
      <span class="td-due${late ? ' late' : ''}">${t.due ? esc(dShort(t.due)) : ''}</span>
    </div>`;
  };

  const group = k => {
    const [, ic, title, sub, col] = KEY[k];
    const list = (d[k] || []).filter(hit);
    const open = !st.closed[k] && list.length;
    return `<section class="td-grp${open ? ' open' : ''}" style="--gc:${col}">
      <button type="button" class="td-ghead" data-tgrp="${k}">
        <span class="td-gchev">${I.chev}</span>
        <span class="td-gic">${ic}</span>
        <b>${title}</b>
        <span class="td-gn${list.length ? '' : ' zero'}">${list.length}</span>
        <span class="mut td-gsub">${sub}</span>
      </button>
      <div class="td-gbody"${open ? '' : ' hidden'}>
        ${list.length ? list.map(line).join('')
          : '<div class="td-empty">Τίποτα εδώ' + (st.who ? ' για τον επιλεγμένο χειριστή' : '') + '.</div>'}
      </div></section>`;
  };

  /* ── Πλακίδια: ιεραρχία, όχι πέντε ίσα κουτιά. Το «πέρασε το deadline»
     ξεχωρίζει γιατί είναι το μόνο που ζητάει ενέργεια σήμερα. Κλικ = πάει
     στην ενότητα και την ανοίγει. ── */
  const tile = k => {
    const [, ic, title, , col] = KEY[k];
    const n = (d[k] || []).length;
    return `<button type="button" class="td-tile${n ? '' : ' zero'}${k === 'carried' && n ? ' hot' : ''}"
      data-ttile="${k}" style="--tc:${col}">
      <span class="td-ti">${ic}</span>
      <span class="td-tn">${n}</span>
      <span class="td-tl">${title}</span></button>`;
  };

  /* ── Ποιος έχει τι: μπάρα φόρτου αντί για πίνακα με παύλες ──
     Έξι στήλες αριθμών με «—» παντού δεν απαντούν «ποιος είναι φορτωμένος».
     Μια στοιβαγμένη μπάρα το απαντά με μια ματιά· οι αριθμοί μένουν από κάτω. */
  const maxLoad = Math.max(1, ...d.people.map(p => (p.planned || 0) + (p.spanning || 0) + (p.carried || 0)));
  const person = p => {
    const load = (p.planned || 0) + (p.spanning || 0) + (p.carried || 0);
    const seg = (v, cls) => v ? `<i class="${cls}" style="flex:${v}"></i>` : '';
    return `<div class="td-p${p.id ? '' : ' none'}${st.who && st.who === p.id ? ' on' : ''}"${p.id ? ` data-pwho="${p.id}"` : ''}>
      <span class="td-av big">${esc(ini(p.name))}</span>
      <span class="td-pmain">
        <b>${esc(p.name)}</b>
        ${p.now ? `<span class="td-now">▶ ${esc(p.now.title)} · ${hm(p.now.mins)}</span>`
          : `<span class="td-pnums">${load ? `${load} ${load === 1 ? 'εργασία' : 'εργασίες'}` : 'καμία εργασία σήμερα'}${p.opened ? ` · ${p.opened} νέα` : ''}</span>`}
      </span>
      <span class="td-load" title="Για σήμερα ${p.planned || 0} · Περνάνε από σήμερα ${p.spanning || 0} · Κουβαλιούνται από πριν ${p.carried || 0}">
        <span class="td-bar" style="width:${Math.round(load / maxLoad * 100)}%">
          ${seg(p.carried, 'late')}${seg(p.planned, 'plan')}${seg(p.spanning, 'span')}
        </span></span>
      ${p.carried ? `<span class="td-late" title="Ήταν σε πλάνο παλιότερης μέρας">${p.carried} από πριν</span>` : '<span class="td-late empty"></span>'}
      <span class="td-spent">${p.spent ? `<b>${hm(p.spent)}</b>` : '<span class="mut">—</span>'}</span>
    </div>`;
  };

  const ORDER = ['running', 'carried', 'planned', 'spanning', 'opened'];
  c.innerHTML = `
  ${d.truncated ? `<div class="card" style="margin-bottom:12px;border-color:var(--warn)"><div class="card-b" style="padding:10px 14px;font-size:12.5px;color:var(--warn)">${I.alert} Η αναφορά έφτασε στο όριο εγγραφών — δείχνονται τα πιο πρόσφατα. Τα σύνολα δεν είναι πλήρη.</div></div>` : ''}
  <div class="td-tiles">${ORDER.map(tile).join('')}</div>
  <div class="card td-people"><div class="card-h">${I.users || I.user} Ποιος έχει τι
    <span class="mut td-phint">${st.who ? 'φίλτρο ενεργό — κλικ ξανά για καθάρισμα' : 'κλικ σε άτομο για φιλτράρισμα'}</span>
    <span class="td-legend">
      <span><i style="background:#e2515f"></i>από πριν</span>
      <span><i style="background:#0090dd"></i>για σήμερα</span>
      <span><i style="background:#7b5cd6"></i>περνάνε</span></span></div>
    <div class="card-b td-plist">${d.people.map(person).join('') || '<div class="mut">—</div>'}</div></div>
  ${ORDER.map(group).join('')}`;

  $$('[data-tgrp]').forEach(h => h.onclick = () => {
    const k = h.dataset.tgrp; st.closed[k] = !st.closed[k]; R.teamday();
  });
  $$('[data-ttile]').forEach(b => b.onclick = () => {
    const k = b.dataset.ttile;
    st.closed[k] = false;
    R.teamday();
    setTimeout(() => {
      const el = document.querySelector(`[data-tgrp="${k}"]`);
      if (el) { el.scrollIntoView({behavior: 'smooth', block: 'start'}); }
    }, 60);
  });
  $$('[data-tgo]').forEach(r => r.onclick = () => openTask(+r.dataset.tgo));
  $$('[data-pwho]').forEach(r => r.onclick = () => {
    st.who = st.who === +r.dataset.pwho ? 0 : +r.dataset.pwho; R.teamday();
  });
};

/* ═══════════ Αναπρογραμματισμοί έργων ═══════════
   Η μετάθεση παράδοσης ήταν αόρατη: γραφόταν πάνω στην παλιά ημερομηνία και
   δεν το μάθαινε κανείς. Εδώ φαίνεται ποια έργα μετατίθενται, πόσο, πόσες
   φορές και από ποιον — το «πόσες φορές» είναι που δείχνει το πρόβλημα. */
R.reschedules = async function () {
  setTop('Αναπρογραμματισμοί', 'Ποια έργα μετατέθηκαν, πόσο και πόσες φορές');
  const c = $('#content');
  const st = R.reschedules._s = R.reschedules._s || {d: 90};
  c.innerHTML = '<div class="skel" style="height:80px;margin-bottom:14px"></div><div class="skel" style="height:380px"></div>';
  const d = await api('reschedules&d=' + st.d).catch(() => null);
  if (!d) { c.innerHTML = '<div class="card"><div class="card-b mut">Δεν φορτώθηκε.</div></div>'; return; }

  const totalDays = d.items.reduce((a, x) => a + Math.abs(x.days), 0);
  const back = d.items.filter(x => x.days > 0).length;
  const dd = x => x ? dShort(x) : '—';
  const arrow = x => {
    if (x.oldDue !== x.newDue) {
      return x.newDue ? `παράδοση <b>${dd(x.oldDue)}</b> → <b>${dd(x.newDue)}</b>`
        : `<b style="color:var(--bad)">αφαιρέθηκε η προθεσμία</b> <span class="mut">(ήταν ${dd(x.oldDue)})</span>`;
    }
    return x.newStart ? `έναρξη <b>${dd(x.oldStart)}</b> → <b>${dd(x.newStart)}</b>`
      : `<b style="color:var(--bad)">αφαιρέθηκε η έναρξη</b> <span class="mut">(ήταν ${dd(x.oldStart)})</span>`;
  };

  const row = x => `<tr>
    <td><span class="kb-dot" style="background:${x.color}"></span> <b>${esc(x.name)}</b>
      ${x.client ? `<div class="mut" style="font-size:11px">${esc(x.client)}</div>` : ''}</td>
    <td>${arrow(x)}</td>
    <td style="text-align:center"><span class="pill ${x.days > 0 ? 'pill-bad' : 'pill-ok'}">${x.days > 0 ? '+' : ''}${x.days} ημ.</span></td>
    <td>${esc(x.by)}</td>
    <td class="mut" style="font-size:11.5px">${x.reason ? esc(x.reason) : '—'}</td>
    <td class="mut" style="white-space:nowrap;font-size:11.5px">${dShort(x.at)}</td></tr>`;

  const proj = p => `<tr data-rgo="${p.id}" style="cursor:pointer">
    <td><span class="kb-dot" style="background:${p.color}"></span> <b>${esc(p.name)}</b></td>
    <td style="text-align:center"><span class="pill ${p.times > 2 ? 'pill-bad' : 'pill-mut'}">${p.times}×</span></td>
    <td style="text-align:center;${p.days > 0 ? 'color:var(--bad);font-weight:700' : ''}">${p.days > 0 ? '+' : ''}${p.days} ημ.</td></tr>`;

  c.innerHTML = `
  ${d.truncated ? `<div class="card" style="margin-bottom:12px;border-color:var(--warn)"><div class="card-b" style="padding:10px 14px;font-size:12.5px;color:var(--warn)">${I.alert} Η αναφορά έφτασε στο όριο εγγραφών — δείχνονται τα πιο πρόσφατα. Τα σύνολα δεν είναι πλήρη.</div></div>` : ''}
  <div class="fbar">
    <span class="fbar-sp"></span>
    <span class="fbar-note"><b>${d.items.length}</b> μεταθέσεις · <b>${totalDays}</b> ημέρες συνολικά${
      back ? ` · <b>${back}</b> προς τα πίσω` : ''}</span>
  </div>
  <div class="fchips">
    ${[30, 90, 180, 365].map(n => `<button class="kb-chip${st.d === n ? ' on' : ''}" data-rd="${n}">${n === 365 ? '1 έτος' : n + ' ημέρες'}</button>`).join('')}
  </div>
  <div class="g4 grid" style="margin-bottom:14px">
    <div class="su-stat"><span class="pc-ic" style="color:#e0552b">${I.cal}</span>
      <div><div class="n">${d.items.length}</div><div class="mut" style="font-size:11.5px">μεταθέσεις</div></div></div>
    <div class="su-stat"><span class="pc-ic" style="color:#0090dd">${I.folder}</span>
      <div><div class="n">${d.projects.length}</div><div class="mut" style="font-size:11.5px">έργα</div></div></div>
    <div class="su-stat"><span class="pc-ic" style="color:#e0a020">${I.clock}</span>
      <div><div class="n">${totalDays}</div><div class="mut" style="font-size:11.5px">ημέρες συνολικά</div></div></div>
    <div class="su-stat"><span class="pc-ic" style="color:#7b5cd6">${I.alert}</span>
      <div><div class="n">${back}</div><div class="mut" style="font-size:11.5px">προς τα πίσω</div></div></div>
  </div>
  ${d.projects.length ? `<div class="card" style="margin-bottom:14px">
    <div class="card-h">${I.chart} Πόσο ελαστικά είναι τα έργα
      <span class="mut" style="font-weight:400;font-size:11px;margin-left:auto">όσα μετατέθηκαν πάνω από 2 φορές θέλουν κουβέντα</span></div>
    <div class="card-b" style="padding:0"><table class="tbl"><thead><tr>
      <th>Έργο</th><th style="text-align:center">Φορές</th><th style="text-align:center">Συνολική μετατόπιση</th>
    </tr></thead><tbody>${d.projects.map(proj).join('')}</tbody></table></div></div>` : ''}
  <div class="card"><div class="card-h">${I.list} Κάθε μετάθεση ξεχωριστά</div>
    <div class="card-b" style="padding:0">${d.items.length ? `<table class="tbl"><thead><tr>
      <th>Έργο</th><th>Αλλαγή</th><th style="text-align:center">Μετατόπιση</th><th>Από</th><th>Αιτιολογία</th><th>Πότε</th>
    </tr></thead><tbody>${d.items.map(row).join('')}</tbody></table>`
    : '<div class="mut" style="padding:16px">Καμία μετάθεση σε αυτή την περίοδο.</div>'}</div></div>`;

  $$('[data-rd]').forEach(b => b.onclick = () => { st.d = +b.dataset.rd; R.reschedules(); });
  $$('[data-rgo]').forEach(r => r.onclick = () => go('board', +r.dataset.rgo));
};

/* ═══════════ Η ομάδα μου — η οθόνη του επικεφαλής ═══════════
   Ο επικεφαλής δεν χρειάζεται «όλες τις αναφορές»: χρειάζεται τρία πράγματα
   για να οργανώσει τη μέρα του — τι παίζει σήμερα, ποιος αντέχει άλλο, και τι
   έχει ήδη ξεφύγει. Ξεκλειδώνει από το is_leader της ομάδας, όχι από cap:
   ένας επικεφαλής μπορεί να μην έχει καθόλου δικαιώματα «Αναφορές». */
R.myteam = async function () {
  const st = R.myteam._s = R.myteam._s || {team: 0, tab: 'day'};
  setTop('Η ομάδα μου', 'Τι παίζει σήμερα, ποιος αντέχει άλλο, τι έχει ξεφύγει');
  const c = $('#content');
  c.innerHTML = '<div class="skel" style="height:90px;margin-bottom:14px"></div><div class="skel" style="height:420px"></div>';
  const d = await api('myteam' + (st.team ? '&team=' + st.team : '')).catch(e => ({err: e.message}));
  if (!d || d.err) {
    c.innerHTML = `<div class="card"><div class="card-b mut" style="padding:18px">${esc((d && d.err) || 'Δεν φορτώθηκε.')}</div></div>`;
    return;
  }
  st.team = d.team.id;

  const TABS = [
    ['day',   I.sun,   'Η μέρα της ομάδας', d.planned.length + d.carried.length],
    ['load',  I.chart, 'Φόρτος & κατανομή', d.load.length],
    ['late',  I.alert, 'Καθυστερήσεις & μεταθέσεις', d.late.length + d.reschedules.length],
  ];

  const taskLine = t => `<div class="kb-item kb-trow" data-mtgo="${t.id}" style="cursor:pointer">
    <span class="kb-dot" style="background:${t.color}"></span>
    <b>${esc(t.title)}</b> ${t.statusId ? stPill(t.statusId) : ''}
    <span class="kb-sum-meta">
      ${t.project ? `<span class="kb-tag">${esc(t.project)}</span>` : ''}
      <span class="mut">${t.who ? esc(t.who) : 'χωρίς ανάθεση'}</span>
      ${t.due ? `<span class="${t.due < d.date ? 'pill pill-bad' : 'mut'}" style="font-size:11px">${dShort(t.due)}</span>` : ''}
    </span></div>`;

  const bucket = (key, ic, title, sub, col) => `<div class="kb-group">
    <div class="kb-ghead" style="border-left:3px solid ${col};cursor:default">
      <b>${ic} ${title}</b><span class="pill pill-mut" style="margin-left:6px">${d[key].length}</span>
      <span class="mut" style="font-weight:400;font-size:11.5px;margin-left:8px">${sub}</span></div>
    <div class="kb-gbody">${d[key].length ? d[key].map(taskLine).join('')
      : '<div class="mut" style="font-size:12.5px;padding:8px 4px">—</div>'}</div></div>`;

  /* Ο φόρτος διαβάζεται σε σχέση με τον πιο φορτωμένο — ένα «12 ανοιχτά» δεν
     λέει τίποτα χωρίς το «ο διπλανός έχει 2». */
  const maxOpen = Math.max(1, ...d.load.map(l => l.open));
  const loadRow = l => `<tr>
    <td><b>${esc(l.name)}</b>${l.now ? `<div class="mut" style="font-size:11px">▶ τρέχει χρονόμετρο · ${hm(l.now.mins)}</div>` : ''}</td>
    <td style="min-width:120px"><div class="bar"><span class="${l.late ? 'bad' : 'ok'}" style="width:${Math.round(l.open / maxOpen * 100)}%"></span></div>
      <small class="mut">${l.open} ανοιχτά</small></td>
    <td style="text-align:center;${l.late ? 'color:var(--bad);font-weight:700' : ''}">${l.late || '—'}</td>
    <td style="text-align:center">${l.today || '—'}</td>
    <td style="text-align:center;${l.noDate ? 'color:var(--warn)' : ''}">${l.noDate || '—'}</td>
    <td style="text-align:right">${l.est ? hm(l.est) : '—'}</td>
    <td style="text-align:right">${l.weekMins ? hm(l.weekMins) : '—'}</td></tr>`;

  const lateRow = t => `<tr data-mtgo="${t.id}" style="cursor:pointer">
    <td><span class="kb-dot" style="background:${t.color}"></span> <b>${esc(t.title)}</b>
      ${t.project ? `<div class="mut" style="font-size:11px">${esc(t.project)}</div>` : ''}</td>
    <td>${esc(t.who)}</td>
    <td style="text-align:center">${dShort(t.due)}</td>
    <td style="text-align:center"><span class="pill pill-bad">${t.days} ημ.</span></td></tr>`;

  const rescRow = r => `<tr data-mtproj="${r.project}" style="cursor:pointer">
    <td><span class="kb-dot" style="background:${r.color}"></span> <b>${esc(r.name)}</b></td>
    <td>${r.oldDue !== r.newDue
      ? (r.newDue ? `παράδοση <b>${dShort(r.oldDue)}</b> → <b>${dShort(r.newDue)}</b>`
                  : '<b style="color:var(--bad)">αφαιρέθηκε η προθεσμία</b>')
      : (r.newStart ? `έναρξη <b>${dShort(r.oldStart)}</b> → <b>${dShort(r.newStart)}</b>`
                    : '<b style="color:var(--bad)">αφαιρέθηκε η έναρξη</b>')}</td>
    <td style="text-align:center"><span class="pill ${r.days > 0 ? 'pill-bad' : 'pill-ok'}">${r.days > 0 ? '+' : ''}${r.days} ημ.</span></td>
    <td class="mut" style="font-size:11.5px">${r.reason ? esc(r.reason) : '—'}</td>
    <td class="mut" style="white-space:nowrap;font-size:11.5px">${esc(r.by)} · ${dShort(r.at)}</td></tr>`;

  /* ── Η μέρα ανά άνθρωπο ──────────────────────────────────────────────────
     Ο επικεφαλής δεν ρωτά «πόσες εργασίες είναι σημερινές» αλλά «τι κάνει ο
     καθένας». Άρα μία γραμμή ζωής ανά μέλος, και μέσα της κάθε εργασία με
     πότε ΞΕΚΙΝΗΣΕ πραγματικά και πότε τελειώνει. */
  const TAGS = {
    now:      ['δουλεύεται τώρα', '#16a26a'],
    carried:  ['πέρασε deadline', '#e0552b'],
    today:    ['σήμερα',    '#0090dd'],
    spanning: ['διαρκεί',   '#7b5cd6'],
    new:      ['νέα',       '#16a26a'],
  };
  const KIND = {work: 'δουλεύει από', plan: 'πλάνο από', born: 'άνοιξε'};

  /* Η μπάρα δείχνει πού πέφτει το ΣΗΜΕΡΑ ανάμεσα σε έναρξη και λήξη. Χωρίς
     λήξη δεν υπάρχει «πόσο μένει» — δείχνουμε ανοιχτό άκρο, δεν το φαντάζομαι. */
  const laneBar = t => {
    const d0 = new Date(t.startAt + 'T12:00:00').getTime();
    const now = new Date(d.date + 'T12:00:00').getTime();
    if (!t.due) { return ''; }
    const d1 = new Date(t.due + 'T12:00:00').getTime();
    const span = Math.max(1, d1 - d0);
    const pct = Math.max(0, Math.min(100, Math.round((now - d0) / span * 100)));
    const late = t.left < 0;
    return `<div class="ln-bar" title="${dShort(t.startAt)} → ${dShort(t.due)}">
      <span class="ln-fill" style="width:${pct}%;background:${late ? 'var(--bad)' : (pct > 80 ? 'var(--warn)' : 'var(--ok)')}"></span>
      <span class="ln-dot" style="left:${pct}%"></span>
      <span class="ln-a">${dShort(t.startAt)}</span><span class="ln-b">${dShort(t.due)}</span></div>`;
  };

  const laneTask = t => {
    const [tl, tc] = TAGS[t.tag] || TAGS.today;
    const when = t.left === null ? '<span class="ln-nodue">χωρίς ημερομηνία λήξης</span>'
      : t.left < 0 ? `<b style="color:var(--bad)">${Math.abs(t.left)} ημ. πίσω</b> (λήξη ${dShort(t.due)})`
      : t.left === 0 ? '<b style="color:var(--warn)">λήγει σήμερα</b>'
      : `λήγει <b>${dShort(t.due)}</b> — σε ${t.left} ημ.`;
    return `<div class="ln-task" data-mtgo="${t.id}">
      <div class="ln-t1">
        <span class="kb-dot" style="background:${t.color}"></span>
        <b>${esc(t.title)}</b>
        <span class="ln-tag" style="background:${tc}18;color:${tc}">${tl}</span>
        ${t.running !== null ? `<span class="ln-tag" style="background:#e0a02018;color:#e0a020">▶ ${hm(t.running)}</span>` : ''}
        ${t.ball && t.ball !== t.whoId ? `<span class="ln-tag" style="background:#7b5cd618;color:#7b5cd6" title="Δεν κάθεται σε αυτόν — περιμένει ενέργεια από τον/την ${esc(t.ballName)}">${I.zap} περιμένει ${esc(t.ballName)}</span>` : ''}
        <span style="flex:1"></span>
        ${t.project ? `<span class="mut" style="font-size:11px">${esc(t.project)}</span>` : ''}
      </div>
      <div class="ln-t2">
        ${KIND[t.startKind]} <b>${dShort(t.startAt)}</b>${t.age ? ` <span class="mut">(${t.age} ημ.)</span>` : ''}
        · ${when}
        ${t.spent ? ` · <b>${hm(t.spent)}</b> καταγεγραμμένα` : ' · <span class="mut">χωρίς καταγεγραμμένο χρόνο</span>'}
        ${t.todayMins ? ` · <b style="color:var(--ok)">${hm(t.todayMins)} σήμερα</b>` : ''}
      </div>
      ${laneBar(t)}</div>`;
  };

  const lane = l => `<div class="card ln${l.now ? ' busy' : ''}" style="margin-bottom:11px">
    <div class="ln-h">
      <span class="act-ava" style="--sc:${l.now ? 'var(--ok)' : 'var(--mut)'}">${esc(l.ini || '?')}</span>
      <div style="flex:1;min-width:140px">
        <b style="font-size:13.5px">${esc(l.name)}</b>
        ${l.now
          ? `<div class="ln-now"><span class="act-live"></span>τώρα: <b>${esc(l.now.title)}</b> · ${hm(l.now.mins)}</div>`
          : `<div class="mut" style="font-size:11.5px">${l.tasks.length ? 'χωρίς ενεργό χρονόμετρο' : 'δεν έχει τίποτα για σήμερα'}</div>`}
      </div>
      <span class="pill pill-mut">${l.tasks.length} ${l.tasks.length === 1 ? 'εργασία' : 'εργασίες'}</span>
      ${l.todayMins ? `<span class="pill" style="background:var(--ok);color:#fff">${hm(l.todayMins)} σήμερα</span>` : ''}
    </div>
    ${l.tasks.length ? `<div class="ln-body">${l.tasks.map(laneTask).join('')}</div>` : ''}
  </div>`;

  const counts = [['planned', 'σήμερα', '#0090dd'], ['spanning', 'διαρκούν', '#7b5cd6'],
    ['opened', 'νέα', '#16a26a'], ['carried', 'πέρασε deadline', '#e0552b']];

  const body = st.tab === 'day'
    ? `<div class="ln-sum">${counts.map(([k, lb, col]) =>
        `<span><b style="color:${col}">${d[k].length}</b> ${lb}</span>`).join('')}
        <span class="mut" style="margin-left:auto;font-size:11.5px">κλικ σε εργασία για άνοιγμα</span></div>
       ${d.lanes.map(lane).join('') || '<div class="card"><div class="card-b mut">Η ομάδα δεν έχει μέλη.</div></div>'}`
    : st.tab === 'load'
    ? `<div class="card"><div class="card-h">${I.chart} Ποιος αντέχει άλλο
        <span class="mut" style="font-weight:400;font-size:11px;margin-left:auto">η μπάρα συγκρίνει με τον πιο φορτωμένο της ομάδας</span></div>
        <div class="card-b" style="padding:0"><table class="tbl"><thead><tr>
          <th>Μέλος</th><th>Ανοιχτά</th><th style="text-align:center">Καθυστ.</th>
          <th style="text-align:center">Σήμερα</th><th style="text-align:center">Χωρίς ημ/νία</th>
          <th style="text-align:right">Εκτίμηση</th><th style="text-align:right">Χρόνος 7ημ</th>
        </tr></thead><tbody>${d.load.map(loadRow).join('') || '<tr><td colspan="7" class="mut">Η ομάδα δεν έχει μέλη.</td></tr>'}</tbody></table></div></div>`
    : `<div class="card" style="margin-bottom:14px"><div class="card-h">${I.alert} Ξεπερασμένες προθεσμίες</div>
        <div class="card-b" style="padding:0">${d.late.length ? `<table class="tbl"><thead><tr>
          <th>Εργασία</th><th>Ποιος</th><th style="text-align:center">Προθεσμία</th><th style="text-align:center">Πόσο πίσω</th>
        </tr></thead><tbody>${d.late.map(lateRow).join('')}</tbody></table>`
        : '<div class="mut" style="padding:16px">Καμία ξεπερασμένη προθεσμία. 👌</div>'}</div></div>
       <div class="card"><div class="card-h">${I.cal} Έργα της ομάδας που μετατέθηκαν
          <span class="mut" style="font-weight:400;font-size:11px;margin-left:auto">τελευταίοι 6 μήνες</span></div>
        <div class="card-b" style="padding:0">${d.reschedules.length ? `<table class="tbl"><thead><tr>
          <th>Έργο</th><th>Αλλαγή</th><th style="text-align:center">Μετατόπιση</th><th>Αιτιολογία</th><th>Από</th>
        </tr></thead><tbody>${d.reschedules.map(rescRow).join('')}</tbody></table>`
        : '<div class="mut" style="padding:16px">Κανένα έργο της ομάδας δεν μετατέθηκε.</div>'}</div></div>`;

  c.innerHTML = `
  ${d.truncated ? `<div class="card" style="margin-bottom:12px;border-color:var(--warn)"><div class="card-b" style="padding:10px 14px;font-size:12.5px;color:var(--warn)">${I.alert} Η αναφορά έφτασε στο όριο εγγραφών — δείχνονται τα πιο πρόσφατα. Τα σύνολα δεν είναι πλήρη.</div></div>` : ''}
  <div class="fbar">
    ${d.teams.length > 1
      ? fChip('Ομάδα', `<select class="fchip-s" id="mtTeam" style="min-width:170px">${d.teams.map(t =>
          `<option value="${t.id}" ${t.id === d.team.id ? 'selected' : ''}>${esc(t.name)}</option>`).join('')}</select>`, false, '')
      /* Μία ομάδα: δεν είναι φίλτρο, είναι ΤΟ ΥΠΟΚΕΙΜΕΝΟ της οθόνης — ολόκληρο
         το όνομα, γεμάτο φόντο, χωρίς αποσιωπητικά (docs/UI-STANDARD.md §3). */
      : `<span class="fchip fchip-who"><span class="kb-dot" style="background:${d.team.color}"></span> ${esc(d.team.name)}</span>`}
  </div>
  <div class="fchips">
    ${TABS.map(([k, ic, lb, n]) => `<button class="kb-chip${st.tab === k ? ' on' : ''}" data-mttab="${k}">${ic} ${lb}${n ? ` <b>${n}</b>` : ''}</button>`).join('')}
  </div>
  ${body}`;

  const sel = $('#mtTeam');
  if (sel) { sel.onchange = () => { st.team = +sel.value; R.myteam(); }; }
  $$('[data-mttab]').forEach(b => b.onclick = () => { st.tab = b.dataset.mttab; R.myteam(); });
  $$('[data-mtgo]').forEach(r => r.onclick = () => openTask(+r.dataset.mtgo));
  $$('[data-mtproj]').forEach(r => r.onclick = () => go('board', +r.dataset.mtproj));
};

/* ═══════════ Πρόγραμμα ομάδας (scheduler) ═══════════
   Λωρίδα ανά ΑΝΘΡΩΠΟ, μπάρα ανά εργασία πάνω στον χρόνο — όπως ένας στόλος
   οχημάτων με τα συμβόλαιά τους. Σύρσιμο οριζόντια = αλλάζει το διάστημα,
   σύρσιμο σε άλλη λωρίδα = αλλάζει ο άνθρωπος. Η ίδια κίνηση που κάνεις στο
   μυαλό σου όταν «στρώνεις» τη βδομάδα. */
R.scheduler = async function () {
  const st = R.scheduler._s = R.scheduler._s || {days: 21, team: 0, from: null};
  setTop('Πρόγραμμα ομάδας', 'Ποιος δουλεύει τι και πότε — σύρε για να το στρώσεις');
  const c = $('#content');
  c.innerHTML = '<div class="skel" style="height:80px;margin-bottom:14px"></div><div class="skel" style="height:420px"></div>';

  const qs = ['days=' + st.days, st.team ? 'team=' + st.team : '', st.from ? 'from=' + st.from : ''].filter(Boolean).join('&');
  const d = await api('scheduler&' + qs).catch(e => ({err: e && e.message}));
  if (!d || d.err) { c.innerHTML = `<div class="card"><div class="card-b mut">${esc((d && d.err) || 'Δεν φορτώθηκε.')}</div></div>`; return; }

  const CELL = 40, LEFT = 190;
  const day0 = new Date(d.from + 'T12:00:00');
  const days = [];
  for (let i = 0; i < d.days; i++) {
    const x = new Date(day0.getTime() + i * 86400000);
    days.push({iso: x.toISOString().slice(0, 10), d: x.getDate(), dow: x.getDay(),
      m: x.getMonth() + 1, today: x.toISOString().slice(0, 10) === today()});
  }
  const idx = iso => days.findIndex(x => x.iso === iso);
  const W = d.days * CELL;

  const head = `<div class="sc-head" style="width:${LEFT + W}px">
    <div class="sc-corner" style="width:${LEFT}px">Χειριστής</div>
    ${days.map(x => `<div class="sc-day${x.today ? ' now' : ''}${x.dow === 0 || x.dow === 6 ? ' we' : ''}" style="width:${CELL}px">
      <b>${x.d}</b><small>${['Κυ', 'Δε', 'Τρ', 'Τε', 'Πε', 'Πα', 'Σα'][x.dow]}</small></div>`).join('')}</div>`;

  /* Υπο-λωρίδες: εργασίες του ίδιου ατόμου που επικαλύπτονται χρονικά ΔΕΝ πέφτουν η μία πάνω
     στην άλλη — η καθεμιά παίρνει τη δική της σειρά (greedy: η πρώτη σειρά που έχει αδειάσει).
     Η γραμμή του ατόμου ψηλώνει ανάλογα. */
  const ROW = 34, PAD = 7;
  const TODAY = today();
  const stack = tasks => {
    const sorted = tasks.slice().sort((a, b) => a.start.localeCompare(b.start) || b.end.localeCompare(a.end));
    const ends = [];   // ανά υπο-λωρίδα: πότε τελειώνει η τελευταία εργασία της
    sorted.forEach(t => {
      let k = ends.findIndex(e => e < t.start);
      if (k < 0) { k = ends.length; }
      ends[k] = t.end; t._row = k;
    });
    return {tasks: sorted, rows: Math.max(1, ends.length)};
  };
  const bar = (t, laneId) => {
    const liveHere = t.runBy && t.runBy === laneId;      // ο ίδιος ο χειριστής της γραμμής δουλεύει τώρα
    const liveOther = t.runBy && t.runBy !== laneId;     // κάποιος ΑΛΛΟΣ τρέχει χρόνο σε δική του εργασία
    let s = idx(t.start), e = idx(t.end);
    const clipL = s < 0, clipR = e < 0;
    if (clipL) { s = 0; }
    if (clipR) { e = days.length - 1; }
    const w = Math.max(1, e - s + 1);
    const now = t.start <= TODAY && t.end >= TODAY;
    return `<div class="sc-bar${t.late ? ' late' : ''}${clipL ? ' clipL' : ''}${clipR ? ' clipR' : ''}${liveHere ? ' live' : ''}${now ? ' now' : ' off'}"
      data-sct="${t.id}" data-s="${t.start}" data-e="${t.end}" data-ball="${t.ball || 0}"
      style="left:${s * CELL + 2}px;width:${w * CELL - 4}px;top:${PAD + (t._row || 0) * ROW}px;background:${t.color}"
      title="#${t.id} ${esc(t.title)}${t.project ? ' · ' + esc(t.project) : ''}\n${dShort(t.start)} → ${dShort(t.end)}${t.deadline ? '\ndeadline ' + dShort(t.deadline) : ''}${liveHere ? '\n▶ τρέχει χρονόμετρο τώρα' : ''}${liveOther ? '\n▶ τρέχει χρόνο ο/η ' + esc(t.runByName) + ' (όχι ο ανάδοχος)' : ''}${t.status ? '\n' + esc(t.status) : ''}">
      <span class="sc-grip l" data-grip="l"></span>
      ${/* ball-rule: ok — ο προγραμματιστής χειρίζεται ρητά τη διάκριση: η λωρίδα
           είναι αυτού που ΚΡΑΤΑΕΙ, και το ⚡ λέει όταν ο ανάδοχος είναι άλλος. */''}
      <span class="sc-t">${liveHere ? '<span class="sc-live">▶</span> ' : ''}${liveOther ? `<span class="sc-runby" title="Τρέχει χρόνο ο/η ${esc(t.runByName)} — όχι ο ανάδοχος">▶ ${esc(t.runByName.split(' ')[0])}</span> ` : ''}${t.ball && t.ball === laneId && t.assignee !== laneId ? `<span class="sc-runby" title="Έχεις τη μπάλα — ανάδοχος: ${esc(adminName(t.assignee))}">⚡</span> ` : ''}${esc(t.title)}</span>
      <span class="sc-grip r" data-grip="r"></span></div>`;
  };

  const lane = l => {
    const st2 = stack(l.tasks);
    const h = PAD * 2 + st2.rows * ROW;
    const todayN = l.tasks.filter(t => t.start <= TODAY && t.end >= TODAY).length;
    /* ▶ στο όνομα ΜΟΝΟ αν ο ίδιος τρέχει χρόνο (σε οποιαδήποτε γραμμή) — όχι επειδή κάποιος άλλος δουλεύει δική του εργασία. */
    const live = d.lanes.some(x => x.tasks.some(t => t.runBy === l.id));
    const off = l.presence === 'offline' || l.presence === 'away';
    return `<div class="sc-lane${off ? ' off' : ''}" data-lane="${l.id}" style="width:${LEFT + W}px;min-height:${h}px">
    <div class="sc-who" style="width:${LEFT}px" title="${esc(l.presenceLbl || '')}">
      <span class="act-ava" style="--sc:${esc(l.presenceCol || 'var(--mut)')}">${esc(l.ini || '?')}</span>
      <span class="sc-nm">${esc(l.name)}${live ? ' <span class="sc-live" title="Τρέχει χρονόμετρο τώρα">▶</span>' : ''}<small class="sc-pr">${esc(l.presenceLbl || '')}</small></span>
      <span class="pill ${todayN > 2 ? 'pill-warn' : 'pill-mut'}" title="${todayN} σήμερα · ${l.tasks.length} στο διάστημα">${todayN > 2 ? '⚠ ' : ''}${todayN}/${l.tasks.length}</span></div>
    <div class="sc-track" style="width:${W}px;height:${h}px">
      ${days.map((x, i) => `<span class="sc-cell${x.today ? ' now' : ''}${x.dow === 0 || x.dow === 6 ? ' we' : ''}" style="left:${i * CELL}px;width:${CELL}px"></span>`).join('')}
      ${st2.tasks.map(t => bar(t, l.id)).join('')}
    </div></div>`; };

  c.innerHTML = `
  <div class="fbar">
    <button class="fchip" id="scPrev" title="Προηγούμενη περίοδος">‹</button>
    <button class="fchip" id="scToday">Σήμερα</button>
    <button class="fchip" id="scNext" title="Επόμενη περίοδος">›</button>
    ${fChip('Από', `<input type="date" class="fchip-s" id="scFrom" value="${d.from}">`, false, '')}
    ${fChip('Έως', `<input type="date" class="fchip-s" id="scTo" value="${d.to}">`, false, '')}
    ${fChip('Ομάδα', `<select class="fchip-s" id="scTeam" style="min-width:140px">
      <option value="0">— όλη η ομάδα —</option>
      ${d.teams.map(t => `<option value="${t.id}" ${t.id === d.team ? 'selected' : ''}>${esc(t.name)}</option>`).join('')}</select>`, !!d.team, '')}
    ${fChip('Εύρος', `<select class="fchip-s" id="scDays">
      ${[7, 14, 21, 35, 60, 90].concat([7, 14, 21, 35, 60, 90].includes(d.days) ? [] : [d.days]).sort((a, b) => a - b).map(n => `<option value="${n}" ${n === d.days ? 'selected' : ''}>${n} ημέρες</option>`).join('')}</select>`, false, '')}
  </div>

  <div class="card"><div class="sc-wrap" id="scWrap">${head}
    ${d.lanes.map(lane).join('') || '<div class="mut" style="padding:20px">Καμία λωρίδα.</div>'}
  </div></div>

  ${d.unscheduled.length ? `<div class="card" style="margin-top:12px">
    <div class="card-h">${I.alert} Χωρίς διάστημα <span class="pill pill-mut">${d.unscheduled.length}</span>
      <span class="mut" style="font-weight:400;font-size:11px;margin-left:auto">δεν μπαίνουν στο πρόγραμμα όσο δεν έχουν έναρξη και λήξη</span></div>
    <div class="card-b" style="display:flex;flex-wrap:wrap;gap:7px">
      ${d.unscheduled.map(u => `<a class="sc-un" href="javascript:" data-scun="${u.id}" title="${esc(u.project || '')}">
        <span class="kb-dot" style="background:${u.color}"></span>${esc(u.title)}
        <span class="mut">· ${esc(u.whoName)}</span></a>`).join('')}
    </div></div>` : ''}`;

  $('#scTeam').onchange = () => { st.team = +$('#scTeam').value; R.scheduler(); };
  $('#scDays').onchange = () => { st.days = +$('#scDays').value; R.scheduler(); };
  const shift = n => { st.from = new Date(day0.getTime() + n * 86400000).toISOString().slice(0, 10); R.scheduler(); };
  $('#scPrev').onclick = () => shift(-7);
  $('#scNext').onclick = () => shift(7);
  $('#scToday').onclick = () => { st.from = null; st.days = 21; R.scheduler(); };
  /* Από → έως: ελεύθερο διάστημα (3–120 ημέρες), όχι μόνο βήματα εβδομάδας. */
  const applyRange = () => {
    const f0 = $('#scFrom').value, t0 = $('#scTo').value; if (!f0 || !t0) return;
    const n = Math.round((new Date(t0 + 'T12:00:00') - new Date(f0 + 'T12:00:00')) / 86400000) + 1;
    if (n < 3) { toast('Το διάστημα πρέπει να είναι τουλάχιστον 3 ημέρες', true); return; }
    if (n > 120) { toast('Έως 120 ημέρες τη φορά', true); return; }
    st.from = f0; st.days = n; R.scheduler();
  };
  $('#scFrom').onchange = applyRange; $('#scTo').onchange = applyRange;
  $$('[data-scun]').forEach(a => a.onclick = () => openTask(+a.dataset.scun));

  scDrag(CELL, LEFT, days, () => R.scheduler());
};

/* Σύρσιμο μπάρας: οριζόντια = μετακίνηση/αλλαγή διάρκειας, κάθετα = αλλαγή
   χειριστή. Χρησιμοποιούμε pointer events ώστε να δουλεύει και με αφή. */
function scDrag(CELL, LEFT, days, reload) {
  let drag = null;
  const wrap = $('#scWrap');
  if (!wrap) { return; }

  wrap.addEventListener('pointerdown', e => {
    const bar = e.target.closest('.sc-bar');
    if (!bar) { return; }
    e.preventDefault();
    bar.setPointerCapture(e.pointerId);
    drag = {bar, id: +bar.dataset.sct, x0: e.clientX, y0: e.clientY,
      s: bar.dataset.s, e: bar.dataset.e,
      left0: parseFloat(bar.style.left), w0: parseFloat(bar.style.width),
      mode: e.target.dataset.grip || 'move',
      lane0: bar.closest('.sc-lane'), moved: false};
    bar.classList.add('dragging');
  });

  wrap.addEventListener('pointermove', e => {
    if (!drag) { return; }
    const dx = e.clientX - drag.x0;
    const step = Math.round(dx / CELL);
    if (Math.abs(dx) > 3 || Math.abs(e.clientY - drag.y0) > 3) { drag.moved = true; }
    if (drag.mode === 'move') {
      drag.bar.style.left = (drag.left0 + step * CELL) + 'px';
      /* Κάθετα: υπογραμμίζουμε τη λωρίδα στην οποία θα πέσει. */
      const lane = document.elementFromPoint(e.clientX, e.clientY);
      const target = lane && lane.closest ? lane.closest('.sc-lane') : null;
      $$('.sc-lane').forEach(l => l.classList.toggle('drop', l === target && target !== drag.lane0));
    } else if (drag.mode === 'r') {
      drag.bar.style.width = Math.max(CELL - 4, drag.w0 + step * CELL) + 'px';
    } else {
      drag.bar.style.left = (drag.left0 + step * CELL) + 'px';
      drag.bar.style.width = Math.max(CELL - 4, drag.w0 - step * CELL) + 'px';
    }
  });

  wrap.addEventListener('pointerup', async e => {
    if (!drag) { return; }
    const D = drag; drag = null;
    D.bar.classList.remove('dragging');
    $$('.sc-lane').forEach(l => l.classList.remove('drop'));
    if (!D.moved) { openTask(D.id); return; }        // κλικ χωρίς σύρσιμο = άνοιγμα

    const step = Math.round((e.clientX - D.x0) / CELL);
    const add = (iso, n) => new Date(new Date(iso + 'T12:00:00').getTime() + n * 86400000).toISOString().slice(0, 10);
    let ns = D.s, ne = D.e;
    if (D.mode === 'move') { ns = add(D.s, step); ne = add(D.e, step); }
    else if (D.mode === 'r') { ne = add(D.e, step); }
    else { ns = add(D.s, step); }
    if (ne < ns) { toast('Η λήξη δεν μπορεί να είναι πριν την έναρξη', true); reload(); return; }

    /* Σε ποια λωρίδα έπεσε; */
    const over = document.elementFromPoint(e.clientX, e.clientY);
    const lane = over && over.closest ? over.closest('.sc-lane') : null;
    const newWho = lane && lane !== D.lane0 ? +lane.dataset.lane : 0;

    let r;
    /* Η λωρίδα είναι «ποιος τη δουλεύει τώρα»: αν η εργασία έχει μπάλα, η μεταφορά αλλάζει τη μπάλα·
       αλλιώς αλλάζει την ανάθεση (όπως πριν). */
    const hasBall = D.bar.dataset.ball && +D.bar.dataset.ball;
    if (newWho && hasBall) {
      r = await api('save_task', {task: D.id, ball: newWho, start: ns, due: ne})
        .then(() => ({ok: true})).catch(er => ({ok: false, error: er && er.message}));
    } else if (newWho) {
      r = await api('save_task', {task: D.id, assignee: newWho, start: ns, due: ne})
        .then(() => ({ok: true})).catch(er => ({ok: false, error: er && er.message, data: er && er.data}));
      if (!r.ok && r.data && r.data.need === 'conflict') {
        const go2 = await cnpConfirm(r.error, {
          body: 'Θέλεις να ανατεθεί έτσι κι αλλιώς;', ok: 'Ναι, ανάθεσέ το', cancel: 'Άκυρο', danger: true});
        if (!go2) { reload(); return; }
        r = await api('save_task', {task: D.id, assignee: newWho, start: ns, due: ne, force: 1})
          .then(() => ({ok: true})).catch(er => ({ok: false, error: er && er.message}));
      }
    } else {
      r = await api('gantt_move', {task: D.id, start: ns, end: ne})
        .then(() => ({ok: true})).catch(er => ({ok: false, error: er && er.message}));
    }
    if (!r.ok) { toast(r.error || 'Δεν αποθηκεύτηκε', true); }
    else { toast(newWho ? 'Μεταφέρθηκε' : `${dShort(ns)} → ${dShort(ne)}`); }
    reload();
  });
}
