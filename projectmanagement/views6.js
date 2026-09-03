/* ═══════════ CloudOn Projects — Προαγορά χρόνου ═══════════
   Ο χρεώσιμος χρόνος πληρώνεται από κάπου: εγκεκριμένη προσφορά για το έργο,
   ή προαγορά του πελάτη. Ό,τι δεν καλύφθηκε μένει ακάλυπτο και από εκεί βγαίνει
   η επόμενη προσφορά. Το μητρώο είναι του supportcontracts — εδώ γίνεται
   δουλεύσιμο από εκεί που ζει ο χρόνος που το αναλώνει. */
'use strict';
const {S, api, esc, fmtEur, dShort, dFull, toast, setTop, cnpConfirm, cnpDialog,
  cnpDenied, cnpCan, cnpPrompt, closeDrawer, openTask, adminName, adminIni, I, go, $, $$} = window.CNP;
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
  <div style="display:flex;gap:11px;flex-wrap:wrap;align-items:center;margin-bottom:16px">
    ${stat(I.clock, hm(t.balance), 'διαθέσιμη προαγορά', 'var(--ok)')}
    ${stat(I.doc, hm(t.offer), 'καλυμμένα από προσφορές', 'var(--info)')}
    ${stat(I.alert, hm(t.open), t.open ? 'ακάλυπτα — θέλουν προσφορά' : 'ακάλυπτος χρόνος', t.open ? 'var(--bad)' : 'var(--ok)')}
    ${stat(I.users, t.clients, t.low ? t.low + ' με χαμηλό υπόλοιπο' : 'πελάτες', t.low ? 'var(--warn)' : 'var(--brand)')}
  </div>

  <div class="card" style="padding:12px 15px;display:flex;gap:9px;align-items:center;flex-wrap:wrap;margin-bottom:12px">
    <input class="inp" id="ppQ" placeholder="Φίλτρο πελάτη…" style="max-width:270px" autocomplete="off">
    <label class="mut" style="display:flex;align-items:center;gap:5px;font-size:12px">
      <input type="checkbox" id="ppOpen"> μόνο με ακάλυπτο χρόνο</label>
    <div style="flex:1"></div>
    ${cnpCan('prepaid.contract') ? `<button class="btn btn-o btn-sm" id="ppNew">${I.plus} Νέο συμβόλαιο</button>` : ''}
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
    const only = $('#ppOpen').checked;
    const rows = d.rows.filter(r => (!q || r.name.toLowerCase().includes(q)) && (!only || r.uncovered > 0));
    $('#ppRows').innerHTML = rows.length ? rows.map(rowHtml).join('')
      : `<div class="empty" style="padding:38px">${I.sparkle}Κανένας πελάτης με αυτά τα κριτήρια</div>`;
    $$('.pp-row').forEach(el => el.onclick = () => openPrepaid(+el.dataset.c));
  };
  $('#ppQ').oninput = paint;
  $('#ppOpen').onchange = paint;
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
  const rows = d.rows.filter(r => r.uncovered > 0).sort((a, b) => b.uncovered - a.uncovered);
  c.innerHTML = `
  <div style="display:flex;gap:11px;flex-wrap:wrap;margin-bottom:16px">
    ${stat(I.alert, hm(d.totals.open), `σε ${rows.length} πελάτ${rows.length === 1 ? 'η' : 'ες'}`,
      d.totals.open ? 'var(--bad)' : 'var(--ok)')}
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

const ACT_ST = {
  online:  ['Συνδεδεμένος', 'var(--ok)'],
  meeting: ['Σε meeting', 'var(--warn)'],
  away:    ['Απών', '#8595ac'],
  offline: ['Εκτός', '#5d6b85'],
};
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
      const [lbl, col] = ACT_ST[p.status] || ACT_ST.offline;
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
    <div style="display:flex;gap:11px;flex-wrap:wrap;align-items:center;margin-bottom:16px">
      ${actStat(I.users, s.online + '/' + s.team, 'στην εφαρμογή τώρα', s.online ? 'var(--ok)' : 'var(--mut)')}
      ${actStat(I.play, s.working, s.working === 1 ? 'δουλεύει αυτή τη στιγμή' : 'δουλεύουν αυτή τη στιγμή', s.working ? 'var(--brand)' : 'var(--mut)')}
      ${actStat(I.clock, actHm(s.minsToday), 'χρόνος σήμερα', 'var(--violet)')}
      ${actStat(I.ticket, s.repliesToday, 'απαντήσεις σήμερα', 'var(--info)')}
      ${actStat(I.checkSquare, s.doneToday, 'έκλεισαν σήμερα', s.doneToday ? 'var(--ok)' : 'var(--mut)')}
    </div>

    <div class="act-h">
      <b>Η ομάδα τώρα</b>
      ${busy.length ? `<span class="pill pill-ok">${busy.length} σε εξέλιξη</span>` : '<span class="mut" style="font-size:12px">κανένα χρονόμετρο σε εξέλιξη</span>'}
      <div style="flex:1"></div>
      <button class="btn btn-o btn-sm" id="acRef" title="Ανανέωση τώρα">↻</button>
    </div>
    ${live.map(card).join('') || '<div class="mut" style="padding:10px 2px">Κανείς ενεργός.</div>'}
    ${old.length ? `<button class="btn btn-o btn-sm" id="acOld" style="margin:4px 0 14px">
      ${st.stale ? 'Κρύψε' : 'Δείξε'} ${old.length} που δεν φάνηκαν εδώ και μέρες</button>
      ${st.stale ? old.map(card).join('') : ''}` : ''}

    <div class="act-h" style="margin-top:18px">
      <b>Ροή</b>
      ${st.who ? `<span class="pill pill-info">μόνο ${esc((d.people.find(p => p.id === st.who) || {}).name || '')}
        <button id="acAll" style="border:0;background:none;color:inherit;cursor:pointer;font-weight:800;padding:0 0 0 4px">✕</button></span>` : ''}
      <div style="flex:1"></div>
      <div class="td-seg">
        ${[[8, '8 ώρες'], [24, '24 ώρες'], [72, '3 μέρες']].map(([h, l]) =>
          `<button data-h="${h}" class="${st.h === h ? 'on' : ''}">${l}</button>`).join('')}
      </div>
    </div>
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
    <div class="mut" style="text-align:center;font-size:11px;margin-top:14px">ανανεώνεται μόνο του κάθε 30΄΄</div>`;

    $$('.act-p').forEach(el => el.onclick = () => {
      const id = +el.dataset.who;
      st.who = st.who === id ? 0 : id;
      paint(d);
    });
    $$('[data-h]').forEach(b => b.onclick = () => { st.h = +b.dataset.h; load(); });
    const ao = $('#acOld'); if (ao) { ao.onclick = () => { st.stale = !st.stale; paint(d); }; }
    const aa = $('#acAll'); if (aa) { aa.onclick = e => { e.stopPropagation(); st.who = 0; paint(d); }; }
    $('#acRef').onclick = () => load();
    $$('.act-row[data-task]').forEach(el => el.onclick = () => openTask(+el.dataset.task));
    $$('.act-row[data-tk]').forEach(el => el.onclick = () => go('#/inbox/' + el.dataset.tk));
  };

  const load = async () => {
    let e0 = null;
    const d = await api('activity&h=' + st.h).catch(e => { e0 = e; return null; });
    if (!d) { c.innerHTML = cnpDenied(e0); return false; }
    if (S.view !== 'activity') { return false; }   // άλλαξε οθόνη όσο φόρτωνε
    paint(d);
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

  c.innerHTML = `
  <div style="display:flex;gap:11px;flex-wrap:wrap;align-items:center;margin-bottom:14px">
    ${cxStat(I.alert, s.open, s.open === 1 ? 'ανοιχτό παράπονο' : 'ανοιχτά παράπονα', s.open ? 'var(--bad)' : 'var(--ok)')}
    ${cxStat(I.flag, s.critical, 'κρίσιμα σε εκκρεμότητα', s.critical ? 'var(--bad)' : 'var(--mut)')}
    ${cxStat(I.cal, s.month, 'φέτος τον μήνα', 'var(--brand)')}
    ${cxStat(I.clock, s.avgDays === null ? '—' : s.avgDays + ' μέρες', 'μέση επίλυση (90 ημερών)', 'var(--violet)')}
  </div>

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

  <div class="card" style="padding:11px 14px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:12px">
    <div class="td-seg">
      ${[['live', 'Ενεργά'], ['resolved', 'Λυμένα'], ['rejected', 'Αβάσιμα'], ['', 'Όλα']].map(([k, l]) =>
        `<button data-st="${k}" class="${st.status === k ? 'on' : ''}">${l}</button>`).join('')}
    </div>
    <select class="inp" id="cxCat" style="width:auto;max-width:200px;padding:6px 9px;font-size:12px">
      <option value="">— κάθε κατηγορία —</option>
      ${d.cats.map(x => `<option value="${x.id}" ${st.cat === x.id ? 'selected' : ''}>${esc(x.name)}</option>`).join('')}
    </select>
    <label class="mut" style="display:flex;align-items:center;gap:5px;font-size:12px">
      <input type="checkbox" id="cxMine" ${st.mine ? 'checked' : ''}> δικά μου</label>
    <div style="flex:1"></div>
    <button class="btn btn-p btn-sm" id="cxNew">${I.plus} Νέο παράπονο</button>
  </div>

  <div id="cxRows">${d.rows.length ? d.rows.map(row).join('')
    : `<div class="empty" style="padding:44px">${I.sparkle}Κανένα παράπονο με αυτά τα κριτήρια.
       <div class="mut" style="font-size:12.5px;margin-top:6px">Όταν ένας πελάτης εκφράσει δυσαρέσκεια, γράψ' την εδώ — αλλιώς χάνεται.</div></div>`}</div>`;

  $$('[data-st]').forEach(b => b.onclick = () => { st.status = b.dataset.st; R.complaints(); });
  $('#cxCat').onchange = e => { st.cat = e.target.value; R.complaints(); };
  $('#cxMine').onchange = e => { st.mine = e.target.checked; R.complaints(); };
  $('#cxNew').onclick = () => quickCx();
  $$('.cx-row').forEach(el => el.onclick = () => openCx(+el.dataset.cx));
  $$('.cx-bar').forEach(el => el.onclick = () => { st.cat = el.dataset.cat; R.complaints(); });
};
const cxStat = (ic, n, l, col) => `<div class="su-stat"><div class="ic" style="background:${col}1a;color:${col}">${ic}</div>
  <div><div class="n">${n}</div><div class="l">${l}</div></div></div>`;

/* ───────── Γρήγορη καταχώρηση ───────── */
function quickCx(pre) {
  if (!cnpCan('clients.complaints')) { toast('Δεν έχεις δικαίωμα καταχώρησης παραπόνου', true); return; }
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
