/* ═══════════ CloudOn Projects — Προαγορασμένος χρόνος ═══════════
   Ο χρεώσιμος χρόνος πληρώνεται από κάπου: εγκεκριμένη προσφορά για το έργο,
   ή προαγορά του πελάτη. Ό,τι δεν καλύφθηκε μένει ακάλυπτο και από εκεί βγαίνει
   η επόμενη προσφορά. Το μητρώο είναι του supportcontracts — εδώ γίνεται
   δουλεύσιμο από εκεί που ζει ο χρόνος που το αναλώνει. */
'use strict';
const {S, api, esc, fmtEur, dShort, dFull, toast, setTop, cnpConfirm, cnpDialog,
  cnpDenied, closeDrawer, openTask, I, go, $, $$} = window.CNP;
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
  setTop('Προαγορασμένος χρόνος', 'Πόσο έχουν αγοράσει, πόσο έμεινε, τι δεν καλύφθηκε');
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
    <button class="btn btn-o btn-sm" id="ppNew">${I.plus} Νέο συμβόλαιο</button>
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
  $('#ppNew').onclick = pickClient;
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
  const dr = drawer('Προαγορασμένος χρόνος', '<div class="skel" style="height:300px"></div>', true);
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
      <button class="btn btn-o btn-sm" id="ppEdit">${I.gear} Συμβόλαιο</button>
      <button class="btn btn-o btn-sm" id="ppAdd">${I.plus} Πίστωση / διόρθωση</button>
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
        <button class="btn btn-o btn-sm" id="ppPrev">${I.mail} Προεπισκόπηση αναφοράς</button>
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
        <button class="btn btn-sm" id="ppMakeOffer">${I.doc} Δημιουργία προσφοράς</button>
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

    $('#ppEdit', body).onclick = () => editContract(d);
    $('#ppAdd', body).onclick = () => moveBalance(d);
    $('#ppGo', body).onclick = async () => {
      const nd = await api(`prepaid_client&client=${clientId}&from=${$('#ppFrom', body).value}&to=${$('#ppTo', body).value}`)
        .catch(() => null);
      if (nd) { d = nd; render(); }
    };
    $('#ppPrev', body).onclick = () => reportPreview(clientId, $('#ppFrom', body).value, $('#ppTo', body).value);
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
