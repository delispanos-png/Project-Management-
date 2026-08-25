/* ═══════════ CloudOn Projects — views pack 2 (όλα τα κυκλώματα) ═══════════ */
'use strict';
const {S, api, esc, rteHtml, rteVal, fmtMin, fmtEur, dShort, tShort, dFull, cnpSetDate, today, toast, setTop,
  adminName, adminIni, statusOf, typeOf, dnd, I, go, openTask, closeDrawer, crmTabs, openLead, cnpConfirm, cnpPrompt, $, $$} = window.CNP;
const R = window.R;
const prioDot = p => ['#8595ac', '#eba63c', '#e2515f'][p] || '#8595ac';
const skel = (n, h) => `<div class="grid g4">${`<div class="skel" style="height:${h || 90}px"></div>`.repeat(n)}</div>`;

/* ═════════ ΛΙΣΤΑ TASKS ═════════ */
R.list = async function () {
  setTop('Λίστα tasks');
  const c = $('#content');
  const f = R.list._f = R.list._f || {open: 1};
  c.innerHTML = `
  <div class="card" style="padding:13px 16px;display:flex;gap:9px;flex-wrap:wrap;align-items:center">
    <select class="inp" id="lfP" style="width:auto"><option value="">— όλα τα projects —</option>
      ${S.boot.projects.map(p => `<option value="${p.id}" ${f.fp == p.id ? 'selected' : ''}>${esc(p.name)}</option>`).join('')}</select>
    <select class="inp" id="lfS" style="width:auto"><option value="">— status —</option>
      ${S.boot.statuses.map(s => `<option value="${s.id}" ${f.fs == s.id ? 'selected' : ''}>${esc(s.title)}</option>`).join('')}</select>
    <select class="inp" id="lfA" style="width:auto"><option value="">— χειριστής —</option>
      ${S.boot.admins.map(a => `<option value="${a.id}" ${f.fa == a.id ? 'selected' : ''}>${esc(a.name)}</option>`).join('')}</select>
    <input class="inp" id="lfQ" placeholder="αναζήτηση…" style="width:180px" value="${esc(f.q || '')}">
    <label style="display:flex;gap:5px;align-items:center;font-size:12.5px">
      <input type="checkbox" id="lfO" ${f.open ? 'checked' : ''}> μόνο ανοιχτά</label>
    <button class="btn btn-p btn-sm" id="lfGo">Φίλτρο</button>
  </div><div id="lRes">${skel(1, 300)}</div>`;
  $('#lfGo').onclick = () => {
    R.list._f = {fp: $('#lfP').value, fs: $('#lfS').value, fa: $('#lfA').value,
      q: $('#lfQ').value, open: $('#lfO').checked ? 1 : 0};
    R.list();
  };
  const qs = Object.entries(f).filter(([, v]) => v !== '' && v != null).map(([k, v]) => k + '=' + encodeURIComponent(v)).join('&');
  const d = await api('list&' + qs);
  $('#lRes').innerHTML = `<div class="card"><table class="tbl"><thead><tr>
    <th>Task</th><th>Project</th><th>Status</th><th>Χειριστής</th><th>Λήξη</th><th>Χρόνος</th></tr></thead><tbody>
    ${d.tasks.length ? d.tasks.map(t => {
      const st = statusOf(t.status), over = t.due && t.due < today() && !t.done;
      return `<tr data-task="${t.id}" style="cursor:pointer">
        <td><span class="dot" style="background:${prioDot(t.prio)};margin-right:7px"></span><b>${esc(t.title)}</b>
          ${t.ball ? `<span class="ball ${t.ball === S.boot.me.id ? 'me' : ''}">⚡${esc(adminIni(t.ball))}</span>` : ''}</td>
        <td><span class="dot" style="background:${t.pcolor};margin-right:5px"></span>${esc(t.pname)}</td>
        <td><span class="pill" style="background:${st.color}22;color:${st.color}">${esc(st.title)}</span></td>
        <td>${t.assignee ? esc(adminName(t.assignee)) : '—'}</td>
        <td class="${over ? 'pill pill-bad' : ''}">${t.due ? dShort(t.due) : '—'}</td>
        <td>${t.mins ? fmtMin(t.mins) : '—'}</td></tr>`;
    }).join('') : '<tr><td colspan="6" class="empty">Κανένα task</td></tr>'}</tbody></table></div>`;
  $$('#lRes tr[data-task]').forEach(r => r.onclick = () => openTask(+r.dataset.task));
};

/* ═════════ ΟΜΑΔΙΚΟ ΗΜΕΡΟΛΟΓΙΟ ═════════ */
const EV_KINDS = {meeting: ['🤝', 'Meeting', '#7b5cd6'], appointment: ['📅', 'Ραντεβού', '#0090dd'],
  leave: ['🌴', 'Άδεια', '#e2a33c'], other: ['📌', 'Άλλο', '#8595ac']};
/* Ελληνική μορφή ημερομηνίας/ώρας συμβάντος — «Παρασκευή 24 Ιουλίου · 10:00 – 11:00». */
function evWhen(ev) {
  const D = s => new Date(String(s).replace(' ', 'T'));
  const dOpt = {weekday: 'long', day: 'numeric', month: 'long'};
  if (!ev.start) { return ''; }
  const a = D(ev.start), b = ev.end ? D(ev.end) : null;
  const sameDay = b && a.toDateString() === b.toDateString();
  const day = a.toLocaleDateString((window.CNP_LOCALE||'el-GR'), dOpt);
  const hm = x => x.toLocaleTimeString((window.CNP_LOCALE||'el-GR'), {hour: '2-digit', minute: '2-digit', hour12: false});
  if (ev.allDay) {
    return sameDay || !b ? `${day} · ολοήμερο`
      : `${a.toLocaleDateString((window.CNP_LOCALE||'el-GR'), dOpt)} → ${b.toLocaleDateString((window.CNP_LOCALE||'el-GR'), dOpt)} · ολοήμερο`;
  }
  if (sameDay || !b) { return `${day} · ${hm(a)}${b ? ' – ' + hm(b) : ''}`; }
  return `${day} ${hm(a)} → ${b.toLocaleDateString((window.CNP_LOCALE||'el-GR'), dOpt)} ${hm(b)}`;
}

function openEvent(ev, ymRefresh) {
  closeDrawer();
  const isNew = !ev || !ev.id;
  ev = Object.assign({kind: 'meeting', attendees: [S.boot.me.id], allDay: false}, ev || {});
  const ovl = document.createElement('div'); ovl.className = 'ovl';   // κλικ έξω ΔΕΝ κλείνει
  const dr = document.createElement('div'); dr.className = 'drawer';
  const d0 = ev.start ? ev.start.slice(0, 10) : today();
  const t0 = ev.start ? ev.start.slice(11, 16) : '10:00';
  const d1 = ev.end ? ev.end.slice(0, 10) : today();
  const t1 = ev.end ? ev.end.slice(11, 16) : '11:00';
  const xmails = [];
  let editing = isNew;              // υπάρχον συμβάν → ΠΡΟΒΟΛΗ πρώτα (η φόρμα ανοίγει με «Επεξεργασία»)
  let kind = ev.kind;

  const isLink = !!(ev.location && /^https?:\/\//.test(ev.location));
  const isMeet = isLink && /\/meet\.php|\/project(management)?\/meet/.test(ev.location);
  const myRsvp = () => (ev.rsvp || {})['admin' + S.boot.me.id];

  /* ── συμμετέχοντες με κατάσταση RSVP ── */
  const whoHtml = () => `
    <div style="display:flex;gap:6px;flex-wrap:wrap">
      ${(ev.attendees || []).map(a => {
        const s = (ev.rsvp || {})['admin' + a];
        return `<span class="pill ${s === 'accepted' ? 'pill-ok' : s === 'declined' ? 'pill-bad' : 'pill-mut'}"
          title="${s === 'accepted' ? 'Αποδέχθηκε' : s === 'declined' ? 'Δεν μπορεί' : 'Δεν απάντησε ακόμη'}">
          ${s === 'accepted' ? '✅' : s === 'declined' ? '❌' : '⏳'} ${esc(adminName(a))}</span>`;
      }).join('')}
      ${ev.client ? (() => {
        const s = (ev.rsvp || {})['client' + ev.client];
        return `<span class="pill ${s === 'accepted' ? 'pill-ok' : s === 'declined' ? 'pill-bad' : 'pill-mut'}">
          ${s === 'accepted' ? '✅' : s === 'declined' ? '❌' : '⏳'} ${I.building} ${esc(ev.clientName || 'Πελάτης')}</span>`;
      })() : ''}
    </div>`;

  /* ══ ΠΡΟΒΟΛΗ — ό,τι χρειάζεσαι στην κορυφή, χωρίς scroll ══ */
  const viewHtml = () => {
    const [ico, klabel, kcol] = EV_KINDS[ev.kind] || EV_KINDS.other;
    return `
    <div class="ev-hero" style="--evc:${kcol}">
      <span class="ev-kind" style="background:${kcol}1a;color:${kcol}">${ico} ${klabel}</span>
      <div class="ev-when">${I.cal} ${esc(evWhen(ev))}</div>
      ${ev.location ? `<div class="ev-loc">${isLink ? I.video : I.pin} ${isLink
        ? `<a href="${esc(ev.location)}" target="_blank" rel="noopener">${isMeet ? 'CloudOn Meet' : esc(ev.location)}</a>`
        : esc(ev.location)}</div>` : ''}
    </div>

    ${isLink ? `<div class="ev-actions">
      <button class="btn ev-join" id="evJoin">${I.video} Συμμετοχή στο meeting</button>
      <button class="btn btn-o btn-ico" id="evCopy" title="Αντιγραφή συνδέσμου">${I.copy}</button>
    </div>` : ''}

    ${(ev.attendees || []).includes(S.boot.me.id) ? `<div class="ev-rsvp">
      <span class="mut">Θα παρευρεθείς;</span>
      <button class="btn btn-sm ${myRsvp() === 'accepted' ? 'btn-p' : 'btn-o'}" id="evAcc">✅ Θα είμαι εκεί</button>
      <button class="btn btn-sm ${myRsvp() === 'declined' ? 'btn-p' : 'btn-o'}" id="evDec">❌ Δεν μπορώ</button>
    </div>` : ''}

    <div class="card"><div class="card-h">${I.users} Ποιος θα είναι εκεί</div>
      <div class="card-b">${whoHtml()}</div></div>

    ${ev.notes ? `<div class="card"><div class="card-h">${I.fileText} Σημειώσεις</div>
      <div class="card-b" style="white-space:pre-wrap;font-size:13px;color:var(--txt)">${esc(ev.notes)}</div></div>` : ''}

    ${ev.canEdit ? `<div class="ev-foot">
      <button class="btn btn-o" id="evEdit">${I.edit} Επεξεργασία</button>
      <button class="btn btn-o" id="evDel" style="color:var(--bad)">${I.trash} Διαγραφή</button>
    </div>` : '<div class="mut" style="font-size:11.5px">Μόνο ο δημιουργός ή διαχειριστής μπορεί να το αλλάξει.</div>'}`;
  };

  /* ══ ΦΟΡΜΑ — νέο συμβάν ή «Επεξεργασία» ══ */
  const formHtml = () => `
  <div class="card"><div class="card-b">
    <label class="lbl">Τύπος</label>
    <div style="display:flex;gap:7px;flex-wrap:wrap" id="evKinds">
      ${Object.entries(EV_KINDS).map(([k, [ico, l, col]]) => `
        <button class="btn btn-sm ${kind === k ? 'btn-p' : 'btn-o'}" data-k="${k}" style="${kind === k ? '' : 'border-color:' + col}">${ico} ${l}</button>`).join('')}
    </div>
    <label class="lbl" style="margin-top:11px">Τίτλος</label>
    <input class="inp" id="evT" value="${esc(ev.title || '')}" placeholder="π.χ. Κλήση με PharmacyOne / Καλοκαιρινή άδεια">
    <div class="frow" style="margin-top:11px">
      <div><label class="lbl">Έναρξη</label><input type="date" class="inp" id="evD0" value="${d0}"></div>
      <div id="evT0w"><label class="lbl">Ώρα</label><input type="time" class="inp" id="evT0" value="${t0}"></div>
      <div><label class="lbl">Λήξη</label><input type="date" class="inp" id="evD1" value="${d1}"></div>
      <div id="evT1w"><label class="lbl">Ώρα</label><input type="time" class="inp" id="evT1" value="${t1}"></div>
    </div>
    <label style="display:flex;gap:6px;align-items:center;margin-top:9px;font-size:12.5px">
      <input type="checkbox" id="evAll" ${ev.allDay ? 'checked' : ''}> Ολοήμερο (για άδειες/πολυήμερα)</label>
    <label class="lbl" style="margin-top:11px">Συμμετέχοντες</label>
    <div class="ev-att">
      ${S.boot.admins.map(a => `<label><input type="checkbox" class="evA" value="${a.id}" ${(ev.attendees || []).includes(a.id) ? 'checked' : ''}> ${esc(a.name)}</label>`).join('')}
    </div>
    <div class="frow" style="margin-top:11px">
      <div><label class="lbl">Πελάτης (για ραντεβού)</label><input class="inp" id="evCli" list="evCliL" autocomplete="off"
        value="${esc(ev.clientName ? ev.clientName + ' (#' + ev.client + ')' : '')}"><datalist id="evCliL"></datalist>
        <input type="hidden" id="evCliId" value="${ev.client || ''}"></div>
      <div><label class="lbl">Τοποθεσία / link
          <button type="button" class="btn btn-sm btn-o" id="evMeet" style="margin-left:6px;padding:2px 8px;font-size:11px">${I.video} Meeting link</button></label>
        <input class="inp" id="evLoc" value="${esc(ev.location || '')}" placeholder="γραφείο / Meet / πελάτης"></div>
    </div>
    <label style="display:flex;gap:6px;align-items:center;margin-top:9px;font-size:12.5px" id="evInvW">
      <input type="checkbox" id="evInv" checked> ${I.mail} Αποστολή πρόσκλησης στον πελάτη (ημερομηνία, link, Add-to-Calendar)</label>
    <label class="lbl" style="margin-top:11px">Επιπλέον προσκεκλημένοι <span class="mut" style="font-weight:400">(εξωτερικοί — γράψε email και πάτα +)</span></label>
    <div id="evXmList" style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:7px"></div>
    <div style="display:flex;gap:7px">
      <input class="inp" id="evXm" placeholder="π.χ. giorgos@example.gr (Enter)" style="flex:1">
      <button type="button" class="btn btn-o btn-sm" id="evXmAdd">+ Προσθήκη</button></div>
    <div class="mut" style="font-size:11px;margin-top:5px">Οι συμμετέχοντες της ομάδας παίρνουν αυτόματα email στη διεύθυνση του προφίλ τους.</div>
    <label class="lbl" style="margin-top:11px">Σημειώσεις</label>
    ${rteHtml('evN', ev.notes || '', 'Ατζέντα, θέματα, σύνδεσμοι…', {min: 110})}
    <div class="ev-foot" style="margin-top:14px">
      ${isNew ? '' : '<button class="btn btn-o" id="evCancelEdit">Άκυρο</button>'}
      <button class="btn btn-p" id="evSave">${I.save} Αποθήκευση</button>
    </div>
  </div></div>`;

  const mount = () => {
    dr.innerHTML = `
      <div class="drawer-h"><h2>${isNew ? 'Νέο συμβάν' : esc(ev.title)}</h2><button class="drawer-x" id="dX">✕</button></div>
      <div class="drawer-b">${editing ? formHtml() : viewHtml()}</div>`;
    $('#dX', dr).onclick = () => cnpAskClose(dr);
    editing ? bindForm() : bindView();
  };

  const bindView = () => {
    const jb = $('#evJoin', dr); if (jb) { jb.onclick = () => window.open(ev.location, '_blank'); }
    const cp = $('#evCopy', dr); if (cp) {
      cp.onclick = () => navigator.clipboard.writeText(ev.location)
        .then(() => toast('Ο σύνδεσμος αντιγράφηκε')).catch(() => toast('Δεν έγινε αντιγραφή', true));
    }
    const acc = $('#evAcc', dr); if (acc) { acc.onclick = async () => {
      await api('event_rsvp', {id: ev.id, status: 'accepted'});
      ev.rsvp = Object.assign({}, ev.rsvp, {['admin' + S.boot.me.id]: 'accepted'});
      toast('✅ Δήλωσες συμμετοχή'); mount();
    }; }
    const dec = $('#evDec', dr); if (dec) { dec.onclick = async () => {
      await api('event_rsvp', {id: ev.id, status: 'declined'});
      ev.rsvp = Object.assign({}, ev.rsvp, {['admin' + S.boot.me.id]: 'declined'});
      toast('Καταγράφηκε ότι δεν μπορείς'); mount();
    }; }
    const ed = $('#evEdit', dr); if (ed) { ed.onclick = () => { editing = true; mount(); }; }
    const del = $('#evDel', dr); if (del) { del.onclick = async () => {
      if (!(await cnpConfirm('Διαγραφή συμβάντος;', {danger: true, ok: I.trash + ' Διαγραφή'}))) { return; }
      await api('event_del', {id: ev.id});
      toast('Διαγράφηκε'); closeDrawer(); R.calendar(ymRefresh);
    }; }
  };

  const bindForm = () => {
    clientAuto('evCli', 'evCliL', 'evCliId');
    $$('#evKinds [data-k]', dr).forEach(b => b.onclick = () => {
      kind = b.dataset.k;
      $$('#evKinds [data-k]', dr).forEach(x => x.className = 'btn btn-sm ' + (x === b ? 'btn-p' : 'btn-o'));
      if (kind === 'leave') { $('#evAll', dr).checked = true; toggleTimes(); }
    });
    const toggleTimes = () => {
      const off = $('#evAll', dr).checked;
      $('#evT0w', dr).style.visibility = off ? 'hidden' : '';
      $('#evT1w', dr).style.visibility = off ? 'hidden' : '';
    };
    $('#evAll', dr).onchange = toggleTimes; toggleTimes();
    const renderXm = () => {
      $('#evXmList', dr).innerHTML = xmails.map((m, i) => `
        <span class="pill pill-info" style="display:inline-flex;gap:5px;align-items:center">${I.mail} ${esc(m)}
          <b data-xmdel="${i}" style="cursor:pointer;opacity:.7">✕</b></span>`).join('');
      $$('[data-xmdel]', dr).forEach(b => b.onclick = () => { xmails.splice(+b.dataset.xmdel, 1); renderXm(); });
    };
    const addXm = () => {
      const v = $('#evXm', dr).value.trim().toLowerCase();
      if (!v) { return; }
      if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(v)) { toast('Μη έγκυρο email', true); return; }
      if (xmails.includes(v)) { toast('Υπάρχει ήδη στη λίστα', true); return; }
      xmails.push(v);
      $('#evXm', dr).value = '';
      renderXm();
    };
    renderXm();
    $('#evXmAdd', dr).onclick = addXm;
    $('#evXm', dr).onkeydown = e => { if (e.key === 'Enter') { e.preventDefault(); addXm(); } };
    $('#evMeet', dr).onclick = async () => {
      const r = await api('meet_room');
      $('#evLoc', dr).value = r.url;
      toast('🎥 Δημιουργήθηκε δωμάτιο CloudOn Meet — το link ισχύει και για πελάτες');
    };
    const ce = $('#evCancelEdit', dr); if (ce) { ce.onclick = () => { editing = false; mount(); }; }
    $('#evSave', dr).onclick = async () => {
      addXm();   // ό,τι έμεινε γραμμένο στο πεδίο email μπαίνει στη λίστα αυτόματα
      const allDay = $('#evAll', dr).checked;
      const r = await api('event_save', {id: ev.id || 0, kind, title: $('#evT', dr).value,
        start: $('#evD0', dr).value + (allDay ? ' 00:00' : ' ' + $('#evT0', dr).value),
        end: $('#evD1', dr).value + (allDay ? ' 23:59' : ' ' + $('#evT1', dr).value),
        allDay, attendees: $$('.evA:checked', dr).map(x => +x.value),
        client: +$('#evCliId', dr).value || 0, location: $('#evLoc', dr).value,
        inviteClient: $('#evInv', dr).checked, extraEmails: xmails.join(','),
        notes: rteVal('evN', dr)}).catch(e => ({err: e.message}));
      if (r.err) { toast(r.err, true); return; }
      toast('Αποθηκεύτηκε 📅'); closeDrawer(); R.calendar(ymRefresh);
    };
  };

  document.body.append(ovl, dr);
  mount();
  requestAnimationFrame(() => { ovl.classList.add('show'); dr.classList.add('show'); });
}

R.calendar = async function (ym) {
  setTop('Ημερολόγιο ομάδας', 'Meetings · ραντεβού · άδειες · λήξεις tasks — η διαθεσιμότητα όλων');
  const c = $('#content');
  c.innerHTML = skel(1, 400);
  const d = await api('calendar' + (ym ? '&ym=' + ym : ''));
  const [Y, M] = d.ym.split('-').map(Number);
  const first = new Date(Y, M - 1, 1), dim = new Date(Y, M, 0).getDate();
  const startDow = (first.getDay() + 6) % 7;
  const mn = ['Ιανουάριος', 'Φεβρουάριος', 'Μάρτιος', 'Απρίλιος', 'Μάιος', 'Ιούνιος', 'Ιούλιος', 'Αύγουστος', 'Σεπτέμβριος', 'Οκτώβριος', 'Νοέμβριος', 'Δεκέμβριος'][M - 1];
  const prev = new Date(Y, M - 2, 1), next = new Date(Y, M, 1);
  const fmtYm = dt => dt.getFullYear() + '-' + String(dt.getMonth() + 1).padStart(2, '0');
  const byDay = {};
  d.items.forEach(t => { (byDay[t.due] = byDay[t.due] || []).push(t); });
  // events ανά ημέρα (πολυήμερα απλώνονται)
  const evByDay = {};
  (d.events || []).forEach(ev => {
    let x = ev.start.slice(0, 10);
    const end = ev.end.slice(0, 10);
    let guard = 0;
    while (x <= end && guard++ < 62) {
      (evByDay[x] = evByDay[x] || []).push(ev);
      const nd = new Date(x + 'T12:00:00'); nd.setDate(nd.getDate() + 1);
      x = nd.toISOString().slice(0, 10);
    }
  });
  const todayEvs = evByDay[today()] || [];
  const away = todayEvs.filter(e => e.kind === 'leave');
  const meets = todayEvs.filter(e => e.kind !== 'leave');
  let cells = '<tr>' + '<td class="other"></td>'.repeat(startDow);
  let col = startDow;
  for (let day = 1; day <= dim; day++) {
    if (col === 7) { cells += '</tr><tr>'; col = 0; }
    const date = d.ym + '-' + String(day).padStart(2, '0');
    cells += `<td class="cal-cell ${date === today() ? 'today' : ''}" data-date="${date}"><div class="d">${day}</div>` +
      (evByDay[date] || []).map(ev => {
        const [ico, , col] = EV_KINDS[ev.kind] || EV_KINDS.other;
        const who = ev.attendees.map(a => adminIni(a)).join(',');
        const tm = ev.allDay ? '' : ev.start.slice(11, 16) + ' ';
        return `<a class="ev" data-event="${ev.id}" style="border-color:${col};background:${col}18"
          title="${esc(ev.title)} — ${esc(ev.attendees.map(a => adminName(a)).join(', '))}${ev.location ? ' @ ' + esc(ev.location) : ''}">
          ${ico} ${tm}${esc(ev.title)} <small style="opacity:.7">${esc(who)}</small></a>`;
      }).join('') +
      (byDay[date] || []).map(t => `<a class="ev ${t.done ? 'done' : date < today() ? 'over' : ''}"
        style="border-color:${t.color}" data-task="${t.id}" title="${esc(t.title + ' — ' + t.pname)}">
        ${t.prio === 2 ? '<b style="color:#e2515f">!</b> ' : ''}${esc(t.title)}</a>`).join('') + '</td>';
    col++;
  }
  while (col < 7) { cells += '<td class="other"></td>'; col++; }
  c.innerHTML = `
  ${(away.length || meets.length) ? `<div class="card" style="padding:11px 16px;margin-bottom:12px;display:flex;gap:16px;flex-wrap:wrap;align-items:center">
    ${away.length ? `<span style="font-size:12.5px">🌴 <b>Λείπουν σήμερα:</b> ${away.map(e => esc(e.attendees.map(a => adminName(a)).join(', '))).join(' · ')}</span>` : ''}
    ${meets.length ? `<span style="font-size:12.5px">${I.handshake} <b>Σήμερα:</b> ${meets.map(e => (e.allDay ? '' : e.start.slice(11, 16) + ' ') + esc(e.title)).join(' · ')}</span>` : ''}
  </div>` : ''}
  <div class="cal-top" style="display:flex;gap:9px;align-items:center;flex-wrap:wrap;margin-bottom:12px">
    <button class="btn btn-o btn-ico" id="calP" title="Προηγούμενος μήνας">←</button>
    <b style="font-size:17px;color:var(--ink);min-width:118px;text-align:center">${mn} ${Y}</b>
    <button class="btn btn-o btn-ico" id="calN" title="Επόμενος μήνας">→</button>
    ${d.ym !== today().slice(0, 7) ? '<button class="btn btn-o btn-sm" id="calT">Σήμερα</button>' : ''}
    <button class="btn btn-p cal-newev" id="evNew" style="margin-left:auto">${I.plus} Νέο συμβάν</button>
  </div>
  <div class="cal-legend">
    ${Object.entries(EV_KINDS).map(([, [ico, l, col]]) => `<span class="cal-leg" style="border-color:${col}40;background:${col}14"><span class="dot" style="background:${col}"></span>${ico} ${l}</span>`).join('')}
  </div>
    <table class="cpm-cal cnp-cal"><thead><tr>${['Δευ', 'Τρί', 'Τετ', 'Πέμ', 'Παρ', 'Σάβ', 'Κυρ'].map(x => `<th>${x}</th>`).join('')}</tr></thead>
    <tbody>${cells}</tr></tbody></table>
    <div id="calDay" class="cal-agenda"></div>`;
  $('#calP').onclick = () => R.calendar(fmtYm(prev));
  $('#calN').onclick = () => R.calendar(fmtYm(next));
  const t = $('#calT'); if (t) t.onclick = () => R.calendar();
  $('#evNew').onclick = () => openEvent(null, d.ym);
  // (τα in-cell events ΔΕΝ ανοίγουν με κλικ — το κλικ σε κελί κάνει απλώς select τη μέρα·
  //  τα events ανοίγουν από την αναλυτική agenda κάτω από το ημερολόγιο)
  // ── agenda επιλεγμένης μέρας (κάτω από το ημερολόγιο) ──
  const dayNames = ['Κυριακή', 'Δευτέρα', 'Τρίτη', 'Τετάρτη', 'Πέμπτη', 'Παρασκευή', 'Σάββατο'];
  const renderDay = date => {
    $$('.cal-cell').forEach(td => td.classList.toggle('sel', td.dataset.date === date));
    const box = $('#calDay'); if (!box) return;
    const dt = new Date(date + 'T12:00:00');
    const evs = (evByDay[date] || []).slice().sort((a, b) => (a.allDay ? '0' : a.start).localeCompare(b.allDay ? '0' : b.start));
    const tasks = byDay[date] || [];
    let body = '';
    evs.forEach(ev => {
      const [ico, , col] = EV_KINDS[ev.kind] || EV_KINDS.other;
      const tm = ev.allDay ? 'Ολοήμερο' : ev.start.slice(11, 16) + (ev.end && ev.end.slice(0, 10) === date ? '–' + ev.end.slice(11, 16) : '');
      const who = ev.attendees.map(a => adminName(a)).join(', ');
      body += `<div class="cal-ag-row" data-agevent="${ev.id}" style="border-left-color:${col}">
        <div class="cal-ag-ic" style="background:${col}20">${ico}</div>
        <div style="flex:1;min-width:0"><b>${esc(ev.title)}</b>
          <div class="cal-ag-meta"><span class="cal-ag-time">${tm}</span>${who ? ' · ' + esc(who) : ''}${ev.location ? ' · 📍 ' + esc(ev.location) : ''}</div></div></div>`;
    });
    tasks.forEach(tk => {
      const over = date < today() && !tk.done;
      body += `<div class="cal-ag-row" data-agtask="${tk.id}" style="border-left-color:${tk.color}">
        <div class="cal-ag-ic" style="background:${tk.color}20">${I.checkSquare || '✔'}</div>
        <div style="flex:1;min-width:0"><b style="${tk.done ? 'text-decoration:line-through;opacity:.6' : ''}">${tk.prio === 2 ? '❗ ' : ''}${esc(tk.title)}</b>
          <div class="cal-ag-meta">Λήξη task · ${esc(tk.pname || '—')}${over ? ' · <span style="color:var(--bad);font-weight:700">εκπρόθεσμο</span>' : ''}</div></div></div>`;
    });
    box.innerHTML = `<div class="cal-agenda-h"><b>${dayNames[dt.getDay()]} ${dt.getDate()} ${mn}</b>
        <button class="btn btn-p btn-sm" id="calDayNew">${I.plus} Νέο εδώ</button></div>
      ${body || '<div class="empty" style="padding:24px 12px">Καμία δραστηριότητα αυτή τη μέρα.</div>'}`;
    const nb = $('#calDayNew'); if (nb) nb.onclick = () => openEvent({start: date + 'T09:00', end: date + 'T10:00'}, d.ym);
    $$('[data-agevent]', box).forEach(r => r.onclick = () => { const ev = (d.events || []).find(x => x.id === +r.dataset.agevent); if (ev) openEvent(ev, d.ym); });
    $$('[data-agtask]', box).forEach(r => r.onclick = () => openTask(+r.dataset.agtask));
  };
  $$('.cal-cell').forEach(td => td.onclick = () => renderDay(td.dataset.date));
  renderDay(today().slice(0, 7) === d.ym ? today() : d.ym + '-01');
};

/* ═════════ ΧΡΟΝΟΣ ═════════ */
R.time = async function () {
  setTop('Χρόνος', S.boot.me.full ? 'Αναφορές χρόνου ομάδας' : 'Ο χρόνος σου');
  const c = $('#content');
  const f = R.time._f = R.time._f || {};
  c.innerHTML = `
  <div class="card" style="padding:13px 16px;display:flex;gap:9px;flex-wrap:wrap;align-items:center">
    <input type="date" class="inp" id="tF" style="width:auto" value="${f.from || new Date().toISOString().slice(0, 8) + '01'}">
    <input type="date" class="inp" id="tT" style="width:auto" value="${f.to || today()}">
    <select class="inp" id="tP" style="width:auto"><option value="">— όλα τα projects —</option>
      ${S.boot.projects.map(p => `<option value="${p.id}" ${f.fp == p.id ? 'selected' : ''}>${esc(p.name)}</option>`).join('')}</select>
    ${S.boot.me.full ? `<select class="inp" id="tA" style="width:auto"><option value="">— χειριστής —</option>
      ${S.boot.admins.map(a => `<option value="${a.id}" ${f.fa == a.id ? 'selected' : ''}>${esc(a.name)}</option>`).join('')}</select>` : ''}
    <button class="btn btn-p btn-sm" id="tGo">Προβολή</button>
    <button class="btn btn-o btn-sm" id="tCsv">⬇ CSV</button></div><div id="tRes">${skel(4)}</div>`;
  $('#tGo').onclick = () => {
    R.time._f = {from: $('#tF').value, to: $('#tT').value, fp: $('#tP').value,
      fa: $('#tA') ? $('#tA').value : ''};
    R.time();
  };
  const qs = Object.entries(f).filter(([, v]) => v).map(([k, v]) => k + '=' + v).join('&');
  const d = await api('time' + (qs ? '&' + qs : ''));
  const aggTbl = (title, obj) => `<div class="card"><div class="card-h">${title}</div>
    <table class="tbl"><thead><tr><th></th><th>Σύνολο</th><th>Χρεώσιμα</th><th>Χρέωση</th></tr></thead><tbody>
    ${Object.keys(obj).length ? Object.entries(obj).map(([k, v]) =>
      `<tr><td><b>${esc(k)}</b></td><td>${fmtMin(v.w)}</td><td>${fmtMin(v.b)}</td><td>${fmtMin(v.c)}</td></tr>`).join('')
      : '<tr><td colspan="4" class="empty">—</td></tr>'}</tbody></table></div>`;
  $('#tRes').innerHTML = `
  <div class="grid g4" style="margin:14px 0 2px">
    <div class="stat info"><b>${fmtMin(d.totals.w)}</b><small>Σύνολο εργασίας</small></div>
    <div class="stat warn"><b>${fmtMin(d.totals.b)}</b><small>Χρεώσιμα</small></div>
    <div class="stat"><b>${fmtMin(d.totals.nb)}</b><small>Χωρίς χρέωση</small></div>
    <div class="stat bad"><b>${fmtMin(d.totals.c)}</b><small>Χρεώθηκαν (προαγορά)</small></div>
  </div>
  <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(280px,1fr))">
    ${aggTbl('Ανά project', d.agg.project)}${aggTbl('Ανά πελάτη', d.agg.client)}${aggTbl('Ανά χειριστή', d.agg.admin)}
  </div>
  <div class="card"><div class="card-h">Καταχωρήσεις (${d.entries.length})</div>
    <table class="tbl"><thead><tr><th>Πότε</th><th>Task</th><th>Πελάτης</th><th>Ποιος</th><th>Χρόνος</th><th>Χρέωση</th><th>Σημ.</th></tr></thead><tbody>
    ${d.entries.length ? d.entries.map(e => `<tr data-task="${e.task}" style="cursor:pointer">
      <td>${tShort(e.at)}</td><td><span class="dot" style="background:${e.pcolor};margin-right:5px"></span>${esc(e.title)}</td>
      <td>${esc(e.client || '—')}</td><td>${esc(e.by)}</td><td><b>${fmtMin(e.mins)}</b></td>
      <td>${e.billable ? `<span class="pill pill-warn">${fmtMin(e.charged)}</span>` : '—'}</td>
      <td class="mut">${esc(e.note || '')}</td></tr>`).join('')
      : '<tr><td colspan="7" class="empty">Καμία καταχώρηση</td></tr>'}</tbody></table></div>`;
  $$('#tRes tr[data-task]').forEach(r => r.onclick = () => openTask(+r.dataset.task));
  $('#tCsv').onclick = () => {
    const esc2 = v => '"' + String(v ?? '').replaceAll('"', '""') + '"';
    const rows = [['Ημερομηνία', 'Task', 'Πελάτης', 'Χειριστής', 'Λεπτά', 'Χρεώσιμο', 'Χρέωση', 'Σημείωση'].map(esc2).join(';')];
    d.entries.forEach(e => rows.push([e.at, e.title, e.client || '', e.by, e.mins, e.billable ? 'ΝΑΙ' : 'ΟΧΙ', e.charged || 0, e.note || ''].map(esc2).join(';')));
    const blob = new Blob(['\ufeff' + rows.join('\n')], {type: 'text/csv;charset=utf-8'});
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob); a.download = 'time.csv'; a.click();
  };
};

/* ═════════ ΠΡΟΣΦΟΡΕΣ ═════════ */
R.offers = async function () {
  setTop('Προσφορές', 'Pipeline προσφορών — δεμένο με WHMCS Quotes');
  const c = $('#content');
  c.innerHTML = skel(5, 280);
  const d = await api('offers');
  const openV = d.offers.filter(o => !d.stages.find(s => s.key === o.stage)?.closed).reduce((s, o) => s + o.value, 0);
  const won = d.offers.filter(o => o.stage === 'accepted');

  /* ── Κινητό: λίστα ανά στάδιο (το kanban 5 στηλών ήθελε ατέλειωτο swipe) ── */
  const MOB = matchMedia('(max-width:768px)').matches;
  const st = R.offers._st = R.offers._st || {q: '', stage: '', closed: {}, tab: 'pipe'};
  const norm = s => String(s || '').toLowerCase()
    .replace(/ά/g, 'α').replace(/έ/g, 'ε').replace(/ή/g, 'η').replace(/[ίϊΐ]/g, 'ι')
    .replace(/ό/g, 'ο').replace(/[ύϋΰ]/g, 'υ').replace(/ώ/g, 'ω').replace(/ς/g, 'σ');
  const hit = o => !st.q || norm([o.title, o.clientName, o.quote ? 'Q' + o.quote : ''].join(' ')).includes(norm(st.q));
  const shown = d.offers.filter(hit);

  const oChips = (o, sg) => `
    ${o.clientName ? `<span>${I.user} ${esc(o.clientName)}</span>` : ''}
    ${o.value > 0 ? `<b style="color:var(--ink)">${fmtEur(o.value)}</b>` : ''}
    ${o.quote ? `<span class="pill pill-info">Q${o.quote}</span>` : ''}
    ${o.expected ? `<span class="${o.expected < today() && !sg.closed ? 'pill pill-bad' : ''}">${I.cal} ${dShort(o.expected)}</span>` : ''}`;

  const listMob = () => (shown.length ? '' : `<div class="card"><div class="empty" style="padding:44px 20px">
      <div class="big">${I.doc}</div>
      <b style="color:var(--ink);font-size:15px">${d.offers.length ? 'Καμία προσφορά με αυτά τα φίλτρα' : 'Καμία προσφορά ακόμη'}</b>
      <div class="mut" style="font-size:12.5px;margin-top:6px">${d.offers.length
        ? 'Καθάρισε την αναζήτηση ή τα φίλτρα.' : 'Ξεκίνα φτιάχνοντας την πρώτη προσφορά.'}</div>
      <button class="btn btn-p" id="newOffer2" style="margin-top:14px">${I.plus} Νέα προσφορά</button></div></div>`)
    + d.stages.map(sg => {
      const list = shown.filter(o => o.stage === sg.key);
      const sum = list.reduce((s, o) => s + o.value, 0);
      if (st.stage !== '' && st.stage !== sg.key) { return ''; }
      if (!list.length && st.stage === '') { return ''; }
      return `<div class="card kb-group">
        <div class="card-h kb-ghead" data-ostage="${sg.key}">
          <span class="kb-gbar" style="background:${sg.color}"></span>${esc(sg.title)}
          <span class="kb-n">${list.length}</span>
          ${sum > 0 ? `<span class="mut" style="font-size:11px;margin-left:6px">${fmtEur(sum)}</span>` : ''}
          <span style="flex:1"></span><span class="kb-gchev ${st.closed[sg.key] ? '' : 'open'}">${I.chev}</span></div>
        <div class="card-b kb-gbody" ${st.closed[sg.key] ? 'style="display:none"' : ''}>
          ${list.length ? list.map(o => `<div class="kb-trow orow" data-offer="${o.id}">
              <span class="kb-dot" style="background:${sg.color}"></span>
              <b>${esc(o.title)}</b>
              <span class="kb-sum-meta">${oChips(o, sg)}</span></div>`).join('')
            : '<div class="mut" style="font-size:12.5px;padding:4px 2px">Καμία προσφορά σε αυτό το στάδιο.</div>'}
        </div></div>`;
    }).join('');

  /* Παρακολούθηση: η ζωή της προσφοράς μετά την αποστολή — πόσο περιμένουμε,
     αν απάντησαν, πότε ξαναχτυπάμε. Το kanban δείχνει στάδιο, όχι χρόνο. */
  const trackList = () => {
    const rank = o => {
      if (o.followupIn !== null && o.followupIn <= 0) { return 0; }            // follow-up σήμερα/χθες
      if (o.reply === null && o.waitDays !== null && o.waitDays >= 7) { return 1; }
      if (o.validIn !== null && o.validIn >= 0 && o.validIn <= 7 && !o.reply) { return 2; }
      if (o.reply === null && o.sentAt) { return 3; }
      if (!o.sentAt) { return 4; }
      return 5;
    };
    const rows = shown.slice().sort((a, b) => rank(a) - rank(b)
      || (b.waitDays || 0) - (a.waitDays || 0));
    const th = t => `<th style="text-align:left;padding:8px 12px;font-size:11px;text-transform:uppercase;color:var(--mut)">${t}</th>`;
    const dLbl = (n, past, future) => n === 0 ? 'σήμερα' : (n < 0 ? Math.abs(n) + ' ημ. ' + past : future + ' ' + n + ' ημ.');
    const replyPill = o => {
      if (o.reply === 'yes') { return '<span class="pill pill-ok">απάντησαν: ναι</span>'; }
      if (o.reply === 'no') { return '<span class="pill pill-bad">απάντησαν: όχι</span>'; }
      if (o.reply === 'thinking') { return '<span class="pill pill-warn">το σκέφτονται</span>'; }
      if (!o.sentAt) { return '<span class="mut">δεν στάλθηκε</span>'; }
      return `<span class="pill pill-warn">αναμονή ${o.waitDays} ημ.</span>`;
    };
    return `<div class="card">
      <div class="card-h">${I.clock} Παρακολούθηση προσφορών
        <span class="mut" style="font-weight:600;font-size:11.5px">— πρώτα όσες θέλουν κίνηση σήμερα</span></div>
      <div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse;min-width:900px">
        <thead><tr style="border-bottom:1px solid var(--line)">
          ${th('Προσφορά')}${th('Πελάτης')}${th('Αξία')}${th('Στάλθηκε')}${th('Απάντηση')}${th('Follow-up')}${th('Ισχύς')}${th('')}
        </tr></thead><tbody>
        ${rows.map(o => `<tr style="border-top:1px solid var(--line)">
          <td style="padding:8px 12px"><b class="lb-title" data-offer="${o.id}">${esc(o.title)}</b>
            ${o.quote ? `<span class="pill pill-info" style="margin-left:5px">Q${o.quote}</span>` : ''}
            <div class="mut" style="font-size:10.5px;margin-top:2px">
              ${o.leadName ? `<span data-goleadof="${o.lead}" style="cursor:pointer;color:var(--brand)">από lead: ${esc(o.leadName)}</span>` : ''}
              ${o.project ? `${o.leadName ? ' · ' : ''}<span data-goprojof="${o.project}" style="cursor:pointer;color:var(--brand)">→ έργο</span>` : ''}
            </div></td>
          <td style="padding:8px 12px">${esc(o.clientName || '—')}</td>
          <td style="padding:8px 12px;white-space:nowrap">${o.value > 0 ? fmtEur(o.value) : '<span class="mut">—</span>'}</td>
          <td style="padding:8px 12px;white-space:nowrap">${o.sentAt ? esc(dFull(o.sentAt)) : '<span class="mut">—</span>'}</td>
          <td style="padding:8px 12px">${replyPill(o)}</td>
          <td style="padding:8px 12px;white-space:nowrap">${o.followup
            ? `<span style="color:${o.followupIn <= 0 ? 'var(--bad)' : (o.followupIn <= 2 ? 'var(--warn)' : 'inherit')};font-weight:700">${esc(dLbl(o.followupIn, 'πριν', 'σε'))}</span>
               ${o.followupNote ? `<div class="mut" style="font-size:10.5px">${esc(o.followupNote)}</div>` : ''}`
            : '<span class="mut">—</span>'}</td>
          <td style="padding:8px 12px;white-space:nowrap">${o.validIn === null ? '<span class="mut">—</span>'
            : `<span style="color:${o.validIn < 0 ? 'var(--mut)' : (o.validIn <= 7 ? 'var(--warn)' : 'inherit')}">${esc(dLbl(o.validIn, 'έληξε', 'σε'))}</span>`}</td>
          <td style="padding:8px 12px;text-align:right"><button class="btn btn-sm btn-o" data-otrack="${o.id}">Καταγραφή</button></td>
        </tr>`).join('')}
        </tbody></table></div>
      ${rows.length ? '' : '<div class="empty" style="padding:34px">Καμία προσφορά</div>'}</div>`;
  };

  const listDesk = () => `<div class="kb" style="min-height:calc(100vh - 290px)">
    ${d.stages.map(sg => {
      const list = shown.filter(o => o.stage === sg.key);
      const sum = list.reduce((s, o) => s + o.value, 0);
      return `<div class="kb-col ocol" data-stage="${sg.key}">
        <div class="kb-h" style="border-color:${sg.color}">${esc(sg.title)}<span class="kb-n">${list.length}</span></div>
        ${sum > 0 ? `<div class="mut" style="padding:5px 15px;font-size:11px;font-weight:700">${fmtEur(sum)}</div>` : ''}
        <div class="kb-cards">${list.map(o => `
          <div class="tcard ocard" data-offer="${o.id}">
            <div class="tcard-t">${esc(o.title)}</div>
            <div class="tcard-m">${oChips(o, sg)}</div></div>`).join('')}</div></div>`;
    }).join('')}
  </div>`;

  c.innerHTML = `
  <div class="card kb-search">
    <div class="kb-srow">
      <div class="kb-sinput"><span class="kb-sico">${I.search}</span>
        <input class="inp" id="ofQ" placeholder="Ψάξε προσφορά — τίτλο, πελάτη, αριθμό quote…" value="${esc(st.q)}"></div>
      <button class="btn btn-p btn-sm" id="newOffer">${I.plus} Νέα προσφορά</button>
    </div>
    <div class="kb-filters">
      <span class="crm-goal">${I.doc} <b>${fmtEur(openV)}</b><span class="mut"> ανοιχτές</span></span>
      <span class="crm-goal" style="flex:0 1 auto">${I.trophy} <b>${won.length}</b><span class="mut"> κερδισμένες · ${fmtEur(won.reduce((s, o) => s + o.value, 0))}</span></span>
    </div>
    ${MOB ? `<div class="kb-filters" style="border-top:0;padding-top:0;margin-top:7px">
      <button class="kb-chip${st.stage === '' ? ' on' : ''}" data-ofstage="">Όλες <b>${shown.length}</b></button>
      ${d.stages.map(sg => { const n = shown.filter(o => o.stage === sg.key).length;
        return `<button class="kb-chip${st.stage === sg.key ? ' on' : ''}" data-ofstage="${sg.key}" style="--kc:${sg.color}">
          <span class="kb-dot" style="background:${sg.color}"></span>${esc(sg.title)} <b>${n}</b></button>`; }).join('')}
    </div>` : ''}
  </div>
  <div style="display:flex;gap:7px;margin:0 0 12px">
    <button class="btn btn-sm ${st.tab === 'pipe' ? 'btn-p' : 'btn-o'}" data-otab="pipe">${I.board} Pipeline</button>
    <button class="btn btn-sm ${st.tab === 'track' ? 'btn-p' : 'btn-o'}" data-otab="track">${I.clock} Παρακολούθηση</button>
  </div>
  ${st.tab === 'track' ? trackList() : (MOB ? listMob() : listDesk())}`;

  $$('[data-otab]').forEach(b => b.onclick = () => { st.tab = b.dataset.otab; R.offers(); });
  $$('[data-otrack]').forEach(b => b.onclick = () => openTrack(d.offers.find(o => o.id === +b.dataset.otrack)));
  $$('[data-goleadof]').forEach(b => b.onclick = e => { e.stopPropagation(); go('crm'); });
  $$('[data-goprojof]').forEach(b => b.onclick = e => { e.stopPropagation(); go('board', +b.dataset.goprojof); });
  $$('.lb-title[data-offer]').forEach(b => b.onclick = () => openOffer(d.offers.find(o => o.id === +b.dataset.offer), d));
  $('#newOffer').onclick = () => openOffer(null, d);
  const no2 = $('#newOffer2'); if (no2) { no2.onclick = () => openOffer(null, d); }
  let oqt;
  $('#ofQ').oninput = () => { clearTimeout(oqt); oqt = setTimeout(() => { st.q = $('#ofQ').value.trim(); R.offers(); }, 300); };
  $$('[data-ofstage]').forEach(b => b.onclick = () => { st.stage = b.dataset.ofstage; R.offers(); });
  $$('.kb-ghead[data-ostage]').forEach(h => h.onclick = () => {
    const k = h.dataset.ostage; st.closed[k] = !st.closed[k];
    h.nextElementSibling.style.display = st.closed[k] ? 'none' : '';
    h.querySelector('.kb-gchev').classList.toggle('open', !st.closed[k]);
  });
  $$('.orow[data-offer]').forEach(r => r.onclick = () => openOffer(d.offers.find(o => o.id === +r.dataset.offer), d));
  if (!MOB && !R.offers._dnd) {
    R.offers._dnd = 1;
    dnd('.ocard', '.ocol', async (card, col) => {
      const r = await api('move_offer', {offer: +card.dataset.offer, stage: col.dataset.stage}).catch(() => ({ok: 0}));
      if (r.ok) { col.querySelector('.kb-cards').appendChild(card);
        $$('.ocol').forEach(x => x.querySelector('.kb-n').textContent = x.querySelectorAll('.tcard').length);
      } else toast('Δεν επιτρέπεται', true);
    }, async el => { const dd = await api('offers'); openOffer(dd.offers.find(o => o.id === +el.dataset.offer), dd); });
  }
};
/* Καταγραφή παρακολούθησης: οι τρεις στιγμές που ξεχνιούνται — πότε έφυγε,
   τι απάντησαν, πότε ξαναχτυπάμε. Χωριστά από τη φόρμα της προσφοράς, γιατί
   γίνεται συχνά και θέλει να τελειώνει σε δέκα δευτερόλεπτα. */
function openTrack(o) {
  if (!o) { return; }
  const ovl = document.createElement('div'); ovl.className = 'ovl show';
  const d7 = n => { const x = new Date(); x.setDate(x.getDate() + n); return x.toISOString().slice(0, 10); };
  ovl.innerHTML = `<div class="pal-box" style="margin:9vh auto 0;max-width:520px;text-align:left" onclick="event.stopPropagation()">
    <div style="padding:20px 22px">
      <h2 style="margin:0 0 4px;font-size:16px;color:var(--ink)">${esc(o.title)}</h2>
      <div class="mut" style="font-size:12px;margin-bottom:14px">${esc(o.clientName || '—')}${o.quote ? ' · Q' + o.quote : ''}</div>

      <div class="frow">
        <div><label class="lbl">Στάλθηκε στον πελάτη</label>
          <input type="date" class="inp" id="tkSent" value="${o.sentAt || ''}"></div>
        <div><label class="lbl">Απάντηση</label>
          <select class="inp" id="tkReply">
            <option value="">— καμία ακόμη —</option>
            <option value="yes" ${o.reply === 'yes' ? 'selected' : ''}>Ναι — αποδέχτηκε</option>
            <option value="thinking" ${o.reply === 'thinking' ? 'selected' : ''}>Το σκέφτεται</option>
            <option value="no" ${o.reply === 'no' ? 'selected' : ''}>Όχι — απορρίφθηκε</option>
          </select></div>
      </div>
      <div class="frow" style="margin-top:11px">
        <div><label class="lbl">Ημ. απάντησης</label>
          <input type="date" class="inp" id="tkRepl" value="${o.repliedAt || ''}"></div>
        <div><label class="lbl">Επόμενο follow-up</label>
          <input type="date" class="inp" id="tkFup" value="${o.followup || ''}"></div>
      </div>
      <div style="display:flex;gap:6px;margin-top:8px;flex-wrap:wrap">
        <button class="btn btn-sm btn-o" data-fup="${d7(2)}">σε 2 ημέρες</button>
        <button class="btn btn-sm btn-o" data-fup="${d7(7)}">σε 1 εβδομάδα</button>
        <button class="btn btn-sm btn-o" data-fup="${d7(30)}">σε 1 μήνα</button>
        ${o.followup ? '<button class="btn btn-sm btn-o" id="tkDone" style="margin-left:auto">✔ Το έκανα</button>' : ''}
      </div>
      <label class="lbl" style="margin-top:11px">Σημείωση follow-up</label>
      <input class="inp" id="tkNote" maxlength="200" value="${esc(o.followupNote || '')}" placeholder="π.χ. να ρωτήσω αν πέρασε από το ΔΣ">

      <div style="margin-top:16px;display:flex;gap:8px">
        <button class="btn btn-p" id="tkSave">Αποθήκευση</button>
        <button class="btn btn-o" id="tkX">Άκυρο</button>
      </div>

      <div style="margin-top:18px;border-top:1px solid var(--line);padding-top:14px">
        <div class="lbl" style="margin-bottom:7px">${I.chat} Επικοινωνίες</div>
        <div style="display:flex;gap:7px;flex-wrap:wrap">
          <select class="inp" id="tkIKind" style="width:120px">
            <option value="call">Τηλέφωνο</option><option value="email">Email</option>
            <option value="meeting">Συνάντηση</option><option value="note">Σημείωση</option>
          </select>
          <input class="inp" id="tkISum" style="flex:1;min-width:150px" maxlength="255" placeholder="τι ειπώθηκε… (Enter)">
        </div>
        <div id="tkTl" style="margin-top:10px"><div class="mut" style="font-size:12px">Φόρτωση…</div></div>
      </div>
    </div></div>`;
  document.body.appendChild(ovl);
  const close = () => ovl.remove();
  ovl.onclick = close;
  $('#tkX', ovl).onclick = close;
  $$('[data-fup]', ovl).forEach(b => b.onclick = () => cnpSetDate($('#tkFup', ovl), b.dataset.fup));
  /* Αν δηλωθεί απάντηση χωρίς ημερομηνία, βάζουμε σήμερα — αλλιώς μένει κενή
     και δεν ξέρουμε πότε απάντησαν. */
  $('#tkReply', ovl).onchange = () => {
    if ($('#tkReply', ovl).value && !$('#tkRepl', ovl).value) { cnpSetDate($('#tkRepl', ovl), today()); }
  };
  const done = $('#tkDone', ovl);
  if (done) {
    done.onclick = async () => {
      await api('offer_track', {offer: o.id, followupDone: 1});
      toast('Καταγράφηκε'); close(); R.offers();
    };
  }
  /* Το νήμα της προσφοράς: κάθε επαφή με ημερομηνία, ώστε να θυμάσαι τι
     ειπώθηκε πριν ξαναπάρεις τηλέφωνο. */
  const kindLbl = {call: '📞', email: '✉️', meeting: '🤝', note: '📝',
    new: '🆕', sent: '📤', reply: '💬'};
  const loadTl = async () => {
    const box = $('#tkTl', ovl); if (!box) { return; }
    const t = await api('offer_timeline&offer=' + o.id).catch(() => ({events: []}));
    box.innerHTML = t.events.length ? t.events.map(e => `<div style="display:flex;gap:8px;padding:5px 0;border-bottom:1px dashed var(--line)">
      <span style="flex:none">${kindLbl[e.kind] || '•'}</span>
      <span style="flex:1;font-size:12.5px;line-height:1.45">${esc(e.text)}
        ${e.by ? `<span class="mut"> · ${esc(e.by)}</span>` : ''}
        ${e.fup ? `<span class="pill pill-warn" style="font-size:9px">follow-up ${esc(dShort(e.fup))}</span>` : ''}</span>
      <span class="mut" style="flex:none;font-size:11px">${esc(dShort(e.at))}</span></div>`).join('')
      : '<div class="mut" style="font-size:12px">Καμία επικοινωνία ακόμη.</div>';
  };
  loadTl();
  $('#tkISum', ovl).onkeydown = async e => {
    if (e.key !== 'Enter') { return; }
    const v = e.target.value.trim(); if (!v) { return; }
    e.target.value = '';
    await api('interaction', {offer: o.id, kind: $('#tkIKind', ovl).value, summary: v});
    loadTl();
  };

  $('#tkSave', ovl).onclick = async () => {
    await api('offer_track', {offer: o.id,
      sent: $('#tkSent', ovl).value || null, replied: $('#tkRepl', ovl).value || null,
      reply: $('#tkReply', ovl).value || null, followup: $('#tkFup', ovl).value || null,
      followupNote: $('#tkNote', ovl).value});
    toast('Αποθηκεύτηκε ✓'); close(); R.offers();
  };
}

function openOffer(o, d) {
  closeDrawer();
  const isNew = !o; o = o || {stage: 'new'};
  const ovl = document.createElement('div'); ovl.className = 'ovl';   // κλικ έξω ΔΕΝ κλείνει
  const dr = document.createElement('div'); dr.className = 'drawer';
  dr.innerHTML = `
  <div class="drawer-h"><h2>${isNew ? 'Νέα προσφορά' : esc(o.title)}</h2><button class="drawer-x" id="dX">✕</button></div>
  <div class="drawer-b">
    <div class="card"><div class="card-b">
      <label class="lbl">Τίτλος</label><input class="inp" id="oTitle" value="${esc(o.title || '')}">
      <div class="frow" style="margin-top:11px">
        <div><label class="lbl">Πελάτης</label><input class="inp" id="oClient" placeholder="αναζήτηση…" value="${esc(o.clientName || '')}" list="oCliL" autocomplete="off">
          <datalist id="oCliL"></datalist><input type="hidden" id="oClientId" value="${o.client || ''}"></div>
        <div><label class="lbl">Ποσό € (αν δεν έχει Quote)</label><input class="inp" id="oAmount" type="number" step="0.01" value="${o.amount ?? ''}"></div>
        <div><label class="lbl">Στάδιο</label><select class="inp" id="oStage">
          ${d.stages.map(s => `<option value="${s.key}" ${s.key === o.stage ? 'selected' : ''}>${esc(s.title)}</option>`).join('')}</select></div>
        <div><label class="lbl">Αναμ. κλείσιμο</label><input type="date" class="inp" id="oExp" value="${o.expected || ''}"></div>
      </div>
      <label class="lbl" style="margin-top:11px">Σημειώσεις</label>
      ${rteHtml('oDescr', o.descr || '', 'Τι περιλαμβάνει η προσφορά…', {min: 120})}
      <div style="margin-top:13px;display:flex;gap:9px;flex-wrap:wrap"><button class="btn btn-p" id="oSave">Αποθήκευση</button>
        ${!isNew && o.stage === 'accepted' && S.boot.me.full ? `<button class="btn btn-o" id="oProj">${I.rocket} Δημιουργία έργου</button>` : ''}</div>
    </div></div>
    ${!isNew ? `<div class="card"><div class="card-h">${I.fileText} WHMCS Quote</div><div class="card-b">
      ${o.quote && S.boot.me.full ? `<p>Δεμένη με το <a href="/cloudonadminpanel/quotes.php?action=manage&id=${o.quote}" target="_blank"><b>Quote #${o.quote}</b></a>
        <span class="pill pill-info">${esc(o.quoteStage || '—')}</span> — σύνολο <b>${fmtEur(o.value)}</b></p>
        <p class="mut" style="font-size:12px">Το στάδιο συγχρονίζεται αυτόματα από το Quote.</p>`
      : `<button class="btn btn-o" id="oQuote" ${o.client ? '' : 'disabled'}>+ Δημιουργία Quote από την προσφορά</button>
        ${o.client ? '' : '<div class="mut" style="font-size:12px;margin-top:6px">όρισε πελάτη πρώτα</div>'}`}
    </div></div>` : ''}
  </div>`;
  document.body.append(ovl, dr);
  requestAnimationFrame(() => { ovl.classList.add('show'); dr.classList.add('show'); });
  $('#dX').onclick = () => cnpAskClose(dr);
  clientAuto('oClient', 'oCliL', 'oClientId');
  $('#oSave', dr).onclick = async () => {
    await api('save_offer', {offer: o.id || 0, title: $('#oTitle').value, client: +$('#oClientId').value || 0,
      amount: $('#oAmount').value !== '' ? +$('#oAmount').value : null, stage: $('#oStage').value,
      expected: $('#oExp').value || null, descr: rteVal('oDescr')});
    toast('Αποθηκεύτηκε'); closeDrawer(); R.offers();
  };
  const opj = $('#oProj', dr); if (opj) opj.onclick = async () => {
    const r = await api('project_from_offer', {offer: o.id}).catch(e => ({err: e.message}));
    if (r.err) { toast(r.err, true); return; }
    toast(r.existing ? 'Υπάρχει ήδη έργο για την προσφορά' : 'Το έργο δημιουργήθηκε 🚀');
    closeDrawer();
    const b = await api('boot'); S.boot = b;
    window.CNP.go('projects');
  };
  const q = $('#oQuote', dr); if (q) q.onclick = async () => {
    const r = await api('create_quote', {offer: o.id}).catch(e => ({err: e.message}));
    if (r.ok) { toast('Quote #' + r.quote + ' δημιουργήθηκε');
      if (S.boot.me.full) window.open('/cloudonadminpanel/quotes.php?action=manage&id=' + r.quote, '_blank');
      closeDrawer(); R.offers();
    } else toast(r.err || 'Σφάλμα', true);
  };
}
window.CNP.clientAuto = clientAuto;   // το χρησιμοποιεί και το R.remotebook (app.js)
/* ── Επιλογή πελάτη ────────────────────────────────────────────────────────
   Το `<datalist>` δέχεται και ελεύθερο κείμενο: αν ο χρήστης έγραφε όνομα
   χωρίς να επιλέξει, το id έμενε κενό και η εγγραφή αποθηκευόταν σιωπηλά
   χωρίς πελάτη. Εδώ η τιμή ορίζεται ΜΟΝΟ με επιλογή από τη λίστα — γραφή
   χωρίς επιλογή δεν παράγει ποτέ πελάτη, και φαίνεται ότι δεν παράγει.
   Ίδια υπογραφή με πριν, ώστε να διορθωθούν και τα έξι σημεία που την καλούν. */
function clientAuto(inpId, listId, hidId, statusId) {
  const inp = $('#' + inpId), hid = $('#' + hidId);
  if (!inp || !hid || inp.dataset.cpick) return;
  inp.dataset.cpick = '1';
  const dl = listId ? $('#' + listId) : null;
  if (dl) dl.remove();                      // το datalist ήταν η αιτία
  inp.removeAttribute('list');
  inp.setAttribute('autocomplete', 'off');
  inp.setAttribute('role', 'combobox');
  inp.setAttribute('aria-expanded', 'false');
  const stEl = statusId ? $('#' + statusId) : null;

  const wrap = document.createElement('div');
  wrap.className = 'cpick';
  inp.parentNode.insertBefore(wrap, inp);
  wrap.appendChild(inp);
  const panel = document.createElement('div');
  panel.className = 'cpick-panel';
  panel.hidden = true;
  wrap.appendChild(panel);
  const clr = document.createElement('button');
  clr.type = 'button'; clr.className = 'cpick-x'; clr.textContent = '✕';
  clr.title = 'Καθάρισε τον πελάτη'; clr.hidden = true;
  wrap.appendChild(clr);

  let rows = [], cur = -1, t, seq = 0;
  const paint = () => {
    const on = !!hid.value;
    inp.classList.toggle('cpick-ok', on);
    inp.classList.toggle('cpick-bad', !on && !!inp.value.trim());
    clr.hidden = !on;
    if (!stEl) return;
    stEl.innerHTML = on
      ? `<span style="color:var(--ok)">✓ ${esc(inp.value)}</span>`
      : (inp.value.trim()
        ? '<span style="color:var(--bad)">⚠ διάλεξε πελάτη από τη λίστα — δεν αρκεί να γράψεις το όνομα</span>'
        : '');
  };
  const close = () => { panel.hidden = true; cur = -1; inp.setAttribute('aria-expanded', 'false'); };
  const pick = i => {
    const r = rows[i]; if (!r) return;
    hid.value = String(r.id);
    inp.value = r.label || (r.name + ' (#' + r.id + ')');
    close(); paint();
    inp.dispatchEvent(new CustomEvent('cpick', {detail: r, bubbles: true}));
  };
  const draw = () => {
    if (!rows.length) {
      panel.innerHTML = '<div class="cpick-empty">Κανένας πελάτης δεν ταιριάζει</div>';
    } else {
      panel.innerHTML = rows.map((r, i) => `<div class="cpick-row ${i === cur ? 'on' : ''}" data-i="${i}">
        <b>${esc(r.name || r.label)}</b>
        <span class="mut">#${r.id}${r.email ? ' · ' + esc(r.email) : ''}</span>
        ${r.status && r.status !== 'Active' ? `<span class="pill pill-mut">${esc(r.status)}</span>` : ''}
      </div>`).join('');
      $$('.cpick-row', panel).forEach(el =>
        el.addEventListener('mousedown', e => { e.preventDefault(); pick(+el.dataset.i); }));
    }
    panel.hidden = false;
    inp.setAttribute('aria-expanded', 'true');
  };
  const search = async q => {
    const my = ++seq;
    const r = await api('client_search&q=' + encodeURIComponent(q)).catch(() => null);
    if (!r || my !== seq) return;           // αγνόησε απαντήσεις που άργησαν
    rows = r.results || []; cur = rows.length ? 0 : -1; draw();
  };

  inp.addEventListener('input', () => {
    hid.value = '';                          // γραφή = ακύρωση της επιλογής
    paint();
    clearTimeout(t);
    const q = inp.value.trim();
    if (q.length < 2) { rows = []; close(); return; }
    t = setTimeout(() => search(q), 220);
  });
  inp.addEventListener('focus', () => { if (rows.length && !hid.value) draw(); });
  inp.addEventListener('keydown', e => {
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      if (panel.hidden) { if (rows.length) draw(); return; }
      e.preventDefault();
      cur = Math.max(0, Math.min(rows.length - 1, cur + (e.key === 'ArrowDown' ? 1 : -1)));
      draw();
      const el = $('.cpick-row.on', panel); if (el) el.scrollIntoView({block: 'nearest'});
    } else if (e.key === 'Enter') {
      if (!panel.hidden && cur >= 0) { e.preventDefault(); pick(cur); }
    } else if (e.key === 'Escape') {
      close();
    }
  });
  inp.addEventListener('blur', () => setTimeout(() => {
    close();
    /* Έμεινε κείμενο χωρίς επιλογή: αν ταιριάζει σε ΑΚΡΙΒΩΣ έναν, δέσε τον
       μόνος σου· αλλιώς σβήσε το κείμενο ώστε να μη μοιάζει με επιλεγμένο. */
    if (hid.value || !inp.value.trim()) { paint(); return; }
    const q = inp.value.trim().toLowerCase();
    const hits = rows.filter(r => String(r.name || r.label).toLowerCase().startsWith(q));
    if (hits.length === 1) { hid.value = String(hits[0].id); inp.value = hits[0].label; }
    paint();
  }, 160));
  clr.addEventListener('click', () => {
    hid.value = ''; inp.value = ''; rows = []; close(); paint(); inp.focus();
  });
  paint();
}

/* ═════════ ΕΠΑΦΕΣ ═════════ */
R.contacts = async function () {
  setTop('CRM', 'Επαφές — leads & πελάτες με CRM δραστηριότητα');
  const c = $('#content');
  c.innerHTML = crmTabs('contacts') + `<div class="card" style="padding:13px 16px;display:flex;gap:9px;align-items:center;flex-wrap:wrap">
    <input class="inp" id="ctQ" placeholder="Αναζήτηση σε leads & πελάτες… (Enter)" style="max-width:340px;flex:1">
    <button class="btn btn-p" id="ctNew">${I.plus} Νέα επαφή</button></div>
    <div id="ctRes">${skel(1, 300)}</div>`;
  const crm = await api('crm');   // stages για το lead drawer
  $('#ctNew').onclick = () => openLead(null, crm);
  const load = async q => {
    const d = await api('contacts' + (q ? '&q=' + encodeURIComponent(q) : ''));
    $('#ctRes').innerHTML = `<div class="card"><div class="tw" style="overflow-x:auto"><table class="tbl"><thead><tr>
      <th>Επαφή</th><th>Κατάσταση</th><th>Τηλέφωνο</th><th>Email</th><th>Τελ. επαφή</th><th>Follow-up</th><th>Χειριστής</th></tr></thead><tbody>
      ${d.rows.length ? d.rows.map(r => `<tr data-${r.kind}="${r.id}" style="cursor:pointer">
        <td><b>${esc(r.name)}</b>${r.sub ? ` <span class="mut">${esc(r.sub)}</span>` : ''}
          <span class="pill pill-mut" style="font-size:9px;margin-left:4px">${r.kind === 'lead' ? 'lead' : 'πελάτης'}</span></td>
        <td><span class="pill" style="background:${r.color}22;color:${r.color}">${esc(r.badge)}</span></td>
        <td>${esc(r.phone || '—')}</td><td>${esc(r.email || '—')}</td>
        <td>${r.last ? dShort(r.last) : '<span class="mut">ποτέ</span>'}</td>
        <td>${r.next ? `<span class="${r.next <= today() ? 'pill pill-bad' : ''}">${I.bell} ${dShort(r.next)}</span>` : '—'}</td>
        <td>${esc(r.who || '—')}</td></tr>`).join('')
        : '<tr><td colspan="7" class="empty">Καμία επαφή ακόμη — πάτα «+ Νέα επαφή»</td></tr>'}</tbody></table></div></div>`;
    $$('#ctRes tr[data-lead]').forEach(row => row.onclick = () => {
      const l = crm.leads.find(x => x.id === +row.dataset.lead);
      if (l) openLead(l, crm); else R.contacts();
    });
    $$('#ctRes tr[data-client]').forEach(row => row.onclick = () => go('client360', +row.dataset.client));
  };
  $('#ctQ').onkeydown = e => { if (e.key === 'Enter') load(e.target.value.trim()); };
  load('');
};

/* ═════════ ΕΠΙΚΟΙΝΩΝΙΕΣ ═════════ */
R.comms = async function () {
  setTop('CRM', 'Επικοινωνίες — ημερολόγιο επαφών & εκκρεμή follow-ups');
  const c = $('#content');
  c.innerHTML = crmTabs('comms') + skel(1, 300);
  const d = await api('comms');
  const kindIco = {call: '📞', email: '✉️', meeting: '🤝', note: '📝'};
  const pending = d.recent.filter(r => r.followup && !r.followupDone && r.followup <= today());
  c.innerHTML = crmTabs('comms') + `
  <div class="card"><div class="card-h">${I.phone} Καταγραφή επικοινωνίας</div>
    <div class="card-b" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">
      <div style="flex:1;min-width:180px"><label class="lbl">Πελάτης</label>
        <input class="inp" id="coCli" list="coCliL" autocomplete="off" placeholder="ψάξε πελάτη…"><datalist id="coCliL"></datalist>
        <input type="hidden" id="coCliId"></div>
      <div><label class="lbl">Τύπος</label><select class="inp" id="coKind" style="width:140px">
        <option value="call">Τηλεφώνημα</option><option value="email">Email</option>
        <option value="meeting">Συνάντηση</option><option value="note">Σημείωση</option></select></div>
      <div style="flex:2;min-width:200px"><label class="lbl">Τι ειπώθηκε</label><input class="inp" id="coSum" placeholder="σύνοψη…"></div>
      <div><label class="lbl">Follow-up</label><input type="date" class="inp" id="coFup" style="width:150px"></div>
      <button class="btn btn-p" id="coSave">Καταγραφή</button>
    </div></div>
  ${pending.length ? `<div class="card"><div class="card-h">${I.bell} Εκκρεμή follow-ups (${pending.length})</div>
    ${pending.map(r => `<div class="trow"><span>${kindIco[r.kind] || '📝'}</span>
      <div style="flex:1"><b>${esc(r.who || '—')}</b> — ${esc(r.followupNote || r.summary)}</div>
      <span class="pill pill-bad">${dShort(r.followup)}</span>
      <button class="btn btn-sm btn-ok" data-done="${r.id}">✓ Έγινε</button></div>`).join('')}</div>` : ''}
  <div class="card"><div class="card-h">Πρόσφατες επικοινωνίες</div>
    ${d.recent.length ? d.recent.map(r => `<div class="trow" style="cursor:default">
      <span>${kindIco[r.kind] || '📝'}</span>
      <div style="flex:1"><b>${esc(r.who || '—')}</b> — ${esc(r.summary)}
        <div class="mut" style="font-size:11px">${esc(r.by)} · ${tShort(r.at)}</div></div>
      ${r.followup && !r.followupDone ? `<span class="pill ${r.followup <= today() ? 'pill-bad' : 'pill-warn'}">${I.bell} ${dShort(r.followup)}</span>` : ''}
    </div>`).join('') : '<div class="empty">Καμία επικοινωνία ακόμη</div>'}</div>`;
  $$('[data-done]').forEach(b => b.onclick = async () => {
    await api('followup_done', {id: +b.dataset.done}); toast('Ολοκληρώθηκε'); R.comms();
  });
  clientAuto('coCli', 'coCliL', 'coCliId');
  $('#coSave').onclick = async () => {
    const cid = +$('#coCliId').value || 0, sum = $('#coSum').value.trim();
    if (!cid) { toast('Διάλεξε πελάτη', true); return; }
    if (!sum) { toast('Γράψε τι ειπώθηκε', true); return; }
    await api('interaction', {client: cid, kind: $('#coKind').value, summary: sum, followup: $('#coFup').value || null});
    toast('Καταγράφηκε'); R.comms();
  };
};

/* ═════════ ΣΤΟΧΟΙ ΠΡΟΪΟΝΤΩΝ ═════════ */
R.targets = async function (ym) {
  setTop('CRM', 'Στόχοι προϊόντων — ανά πωλητή, με πρόοδο');
  const c = $('#content');
  c.innerHTML = crmTabs('targets') + skel(1, 300);
  const d = await api('targets' + (ym ? '&ym=' + ym : '')).catch(() => null);
  if (!d) { c.innerHTML = crmTabs('targets') + `<div class="empty"><div class="big">${I.lock}</div>Μόνο για διαχειριστές</div>`; return; }
  const [Y, M] = d.ym.split('-').map(Number);
  const mn = ['Ιανουάριος', 'Φεβρουάριος', 'Μάρτιος', 'Απρίλιος', 'Μάιος', 'Ιούνιος', 'Ιούλιος', 'Αύγουστος', 'Σεπτέμβριος', 'Οκτώβριος', 'Νοέμβριος', 'Δεκέμβριος'][M - 1];
  const fmtYm = (y, m) => y + '-' + String(m).padStart(2, '0');
  const col4 = pct => pct === null ? 'var(--mut)' : pct >= 100 ? 'var(--ok)' : pct >= 60 ? 'var(--brand)' : 'var(--warn)';
  const ring = (pct, cl) => {
    const r = 24, circ = 2 * Math.PI * r, off = circ * (1 - (pct || 0) / 100);
    return `<div class="su-ring"><svg width="58" height="58" viewBox="0 0 58 58">
      <circle cx="29" cy="29" r="${r}" fill="none" stroke="var(--line)" stroke-width="6"/>
      <circle cx="29" cy="29" r="${r}" fill="none" stroke="${cl}" stroke-width="6" stroke-linecap="round" stroke-dasharray="${circ}" stroke-dashoffset="${off}"/>
    </svg><div class="v" style="font-size:12px">${pct === null ? '—' : pct + '%'}</div></div>`;
  };
  const bar = (pct, cl) => `<div class="bar" style="height:8px"><span style="width:${Math.min(100, pct || 0)}%;background:${cl};display:block;height:100%;border-radius:6px"></span></div>`;

  c.innerHTML = crmTabs('targets') + `
  <div style="display:flex;gap:11px;align-items:center;margin-bottom:16px;flex-wrap:wrap">
    <button class="btn btn-o btn-sm" id="tgP">←</button><b style="font-size:16px;color:var(--ink)">${mn} ${Y}</b>
    ${d.ym < today().slice(0, 7) ? '<button class="btn btn-o btn-sm" id="tgN">→</button>' : ''}
    <button class="btn btn-p btn-sm" id="tgAdd" style="margin-left:auto">${I.plus} Νέος στόχος</button>
  </div>
  <div class="grid" style="grid-template-columns:repeat(auto-fill,minmax(270px,1fr));gap:12px">
  ${d.cards.length ? d.cards.map(t => {
    const pct = t.tUnits > 0 ? Math.round(t.units / t.tUnits * 100) : null;
    const cl = col4(pct);
    return `<div class="su-proj" data-tgo="${t.product}" style="align-items:center">
      <div class="stripe" style="background:${cl}"></div>
      ${ring(pct === null ? 0 : Math.min(100, pct), cl)}
      <div class="body">
        <div class="title">${esc(t.name)}</div>
        <div class="meta">${t.units} πωλήσεις · <b style="color:var(--ink)">${fmtEur(t.value)}</b></div>
        <div style="margin-top:6px;font-size:12px">${t.tUnits > 0
          ? `Στόχος: <b>${t.tUnits}</b> τεμ.${t.tValue > 0 ? ' / ' + fmtEur(t.tValue) : ''} <span style="color:${cl};font-weight:800">${pct}%${pct >= 100 ? ' 🎉' : ''}</span>`
          : '<span class="mut">χωρίς στόχο — κλικ για ορισμό</span>'}</div>
        ${t.people.length ? `<div class="mut" style="font-size:11px;margin-top:5px;display:inline-flex;align-items:center;gap:4px">${I.users} ${t.people.length} πωλητ${t.people.length > 1 ? 'ές' : 'ής'}</div>` : ''}
      </div></div>`;
  }).join('') : '<div class="empty" style="grid-column:1/-1;padding:34px">Καμία πώληση/στόχος αυτόν τον μήνα — πάτα «Νέος στόχος»</div>'}
  </div>`;

  $('#tgP').onclick = () => R.targets(M === 1 ? fmtYm(Y - 1, 12) : fmtYm(Y, M - 1));
  const nb = $('#tgN'); if (nb) nb.onclick = () => R.targets(M === 12 ? fmtYm(Y + 1, 1) : fmtYm(Y, M + 1));
  $('#tgAdd').onclick = () => openTargetDrawer(null, d);
  $$('[data-tgo]').forEach(x => x.onclick = () => {
    const card = d.cards.find(t => t.product === +x.dataset.tgo);
    openTargetDrawer(card, d);
  });
};

// drill-in: στόχος προϊόντος + ανάλυση/ορισμός ανά πωλητή
function openTargetDrawer(card, d) {
  closeDrawer();
  const isNew = !card;
  const ovl = document.createElement('div'); ovl.className = 'ovl';   // κλικ έξω ΔΕΝ κλείνει
  const dr = document.createElement('div'); dr.className = 'drawer';
  const col4 = pct => pct === null ? 'var(--mut)' : pct >= 100 ? 'var(--ok)' : pct >= 60 ? 'var(--brand)' : 'var(--warn)';
  const bar = (pct, cl) => `<div class="bar" style="height:8px;flex:1"><span style="width:${Math.min(100, pct || 0)}%;background:${cl};display:block;height:100%;border-radius:6px"></span></div>`;
  const pid = card ? card.product : 0;
  const ovPct = card && card.tUnits > 0 ? Math.round(card.units / card.tUnits * 100) : null;
  // map πωλητών: actual ανά admin
  const actBy = {}; if (card) { card.people.forEach(p => actBy[p.admin] = p); }
  const tgtBy = {}; if (card) { card.people.forEach(p => { if (p.tUnits || p.tValue) tgtBy[p.admin] = p; }); }
  dr.innerHTML = `
  <div class="drawer-h"><h2>${isNew ? 'Νέος στόχος προϊόντος' : esc(card.name)}</h2><button class="drawer-x" id="dX">✕</button></div>
  <div class="drawer-b">
    ${isNew ? `<div class="card"><div class="card-b">
      <label class="lbl">Προϊόν</label>
      <select class="inp" id="tgProd">${d.products.map(p => `<option value="${p.id}">${esc(p.group ? p.group + ' › ' : '')}${esc(p.name)}</option>`).join('')}</select>
    </div></div>` : `<div class="card"><div class="card-b" style="display:flex;align-items:center;gap:16px">
      <div style="flex:1"><div class="mut" style="font-size:12px">Σύνολο μήνα</div>
        <div style="font-size:22px;font-weight:800;color:var(--ink)">${card.units} πωλήσεις · ${fmtEur(card.value)}</div>
        ${card.tUnits > 0 ? `<div style="display:flex;align-items:center;gap:9px;margin-top:8px">${bar(ovPct, col4(ovPct))}<b style="color:${col4(ovPct)}">${ovPct}%</b></div>
          <div class="mut" style="font-size:11px;margin-top:3px">Στόχος: ${card.tUnits} τεμ.${card.tValue > 0 ? ' / ' + fmtEur(card.tValue) : ''}</div>` : ''}</div>
    </div></div>`}

    <div class="card"><div class="card-h">${I.target} Εταιρικός στόχος (σύνολο)</div><div class="card-b">
      <div style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
        <div><label class="lbl">Τεμάχια/μήνα</label><input class="inp" type="number" id="ovU" value="${card ? (card.tUnits || '') : ''}" style="width:120px"></div>
        <div><label class="lbl">€/μήνα</label><input class="inp" type="number" step="0.01" id="ovV" value="${card ? (card.tValue || '') : ''}" style="width:130px"></div>
        <button class="btn btn-p" id="ovSave">Αποθήκευση</button>
        ${card && (card.tUnits || card.tValue) ? `<button class="btn btn-o" id="ovDel" style="color:var(--bad)">${I.trash} Διαγραφή</button>` : ''}</div>
    </div></div>

    ${!isNew ? `<div class="card"><div class="card-h">${I.users} Στόχος ανά πωλητή <span class="mut" style="font-weight:400;font-size:11px;margin-left:auto">απόδοση από το lead που έκλεισε</span></div>
      <div class="card-b" id="tgSellers">
      ${d.sellers.map(s => {
        const a = actBy[s.id] || {units: 0, value: 0};
        const tg = tgtBy[s.id] || {tUnits: 0, tValue: 0};
        const pct = tg.tUnits > 0 ? Math.round(a.units / tg.tUnits * 100) : null;
        const cl = col4(pct);
        return `<div style="display:flex;align-items:center;gap:9px;padding:8px 0;border-bottom:1px solid var(--line);flex-wrap:wrap">
          <b style="width:130px;font-size:12.5px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(s.name)}</b>
          <input class="inp" type="number" data-su="${s.id}" value="${tg.tUnits || ''}" placeholder="στόχος τεμ." style="width:90px;font-size:12px" title="στόχος τεμαχίων">
          <span class="mut" style="font-size:11.5px;white-space:nowrap">έκανε <b style="color:var(--ink)">${a.units}</b> · ${fmtEur(a.value)}</span>
          <div style="flex:1;min-width:80px;display:flex;align-items:center;gap:6px">${bar(pct, cl)}<span style="font-size:11px;font-weight:700;color:${cl};width:38px;text-align:right">${pct === null ? '' : pct + '%'}</span></div>
          <button class="btn btn-sm btn-o" data-susave="${s.id}" title="Αποθήκευση">${I.save}</button>
          ${tg.tUnits || tg.tValue ? `<button class="btn btn-sm btn-o" data-sudel="${s.id}" style="color:var(--bad)" title="Διαγραφή στόχου">✕</button>` : ''}</div>`;
      }).join('')}
      ${card.unattrUnits ? `<div class="mut" style="font-size:11.5px;margin-top:8px">${I.alert} <b>${card.unattrUnits}</b> πωλήσεις (${fmtEur(card.unattrValue)}) χωρίς πωλητή — δεν υπάρχει lead που να τις αποδίδει.</div>` : ''}
    </div></div>` : ''}
  </div>`;
  document.body.append(ovl, dr);
  requestAnimationFrame(() => { ovl.classList.add('show'); dr.classList.add('show'); });
  $('#dX').onclick = () => cnpAskClose(dr);
  const prodId = () => pid || +$('#tgProd', dr).value;
  $('#ovSave', dr).onclick = async () => {
    await api('save_ptarget', {product: prodId(), admin: 0, units: +$('#ovU', dr).value || 0, value: +$('#ovV', dr).value || 0});
    toast('Στόχος αποθηκεύτηκε'); closeDrawer(); R.targets(d.ym);
  };
  const ovd = $('#ovDel', dr);
  if (ovd) ovd.onclick = async () => {
    if (!(await cnpConfirm('Διαγραφή εταιρικού στόχου για αυτό το προϊόν;', {danger: true, ok: I.trash + ' Διαγραφή'}))) return;
    await api('save_ptarget', {product: prodId(), admin: 0, units: 0, value: 0});
    toast('Διαγράφηκε'); closeDrawer(); R.targets(d.ym);
  };
  $$('[data-susave]', dr).forEach(b => b.onclick = async () => {
    const u = +$(`[data-su="${b.dataset.susave}"]`, dr).value || 0;
    await api('save_ptarget', {product: prodId(), admin: +b.dataset.susave, units: u, value: 0});
    toast('Στόχος πωλητή αποθηκεύτηκε'); closeDrawer(); R.targets(d.ym);
  });
  $$('[data-sudel]', dr).forEach(b => b.onclick = async () => {
    await api('save_ptarget', {product: prodId(), admin: +b.dataset.sudel, units: 0, value: 0});
    toast('Στόχος πωλητή διαγράφηκε'); closeDrawer(); R.targets(d.ym);
  });
}

/* ═════════ ΠΕΛΑΤΗΣ 360° ═════════ */
R.client360 = async function (cid) {
  setTop('Πελάτης 360°', 'Το πλήρες ιστορικό ενός πελάτη');
  const c = $('#content');
  c.innerHTML = `<div class="card" style="padding:13px 16px;display:flex;gap:9px">
    <input class="inp" id="c3Q" placeholder="Πληκτρολόγησε ID, όνομα, επωνυμία ή email…" autocomplete="off" style="max-width:400px"></div>
    <div id="c3Res"></div>`;
  const typeIco = {task: '🟦', task_done: '✅', time: '⏱', time_bill: '💶', ticket: '🎫',
    sc_plus: '🔋', sc_minus: '🪫', offer: '📄', offer_won: '🏆', offer_lost: '❌', payment: '💰', contact: '💬'};
  const show = async (id, months) => {
    $('#c3Res').innerHTML = skel(4);
    const d = await api('client360&id=' + id + (months ? '&months=' + months : ''));
    let lastDay = '';
    const ini = (d.client.name || '?').trim().split(/\s+/).map(w => w[0] || '').slice(0, 2).join('').toUpperCase();
    // ── alerts: ό,τι ΠΡΕΠΕΙ να δει αμέσως ο χειριστής ──
    const alerts = [];
    if (d.owed.flag) alerts.push(['bad', '💰', d.full ? `Ανοιχτό υπόλοιπο ${fmtEur(d.owed.amount)} · ${d.owed.count} τιμ.` : 'Έχει ανοιχτό υπόλοιπο — παρέπεμψε στο λογιστήριο']);
    if (d.sla && d.sla.enabled && d.sla.priority === 'High') alerts.push(['warn', '⚡', 'Πελάτης προτεραιότητας — SLA Υψηλή']);
    if (d.sla && d.sla.enabled && d.sla.balance <= 0) alerts.push(['bad', '🪫', 'Εξαντλημένο υπόλοιπο ωρών υποστήριξης']);
    const expSoon = (d.services || []).filter(sv => sv.status === 'Active' && sv.due && ((new Date(sv.due) - Date.now()) / 86400000) < 15);
    if (expSoon.length) alerts.push(['warn', '⏰', `${expSoon.length === 1 ? 'Υπηρεσία λήγει' : expSoon.length + ' υπηρεσίες λήγουν'} σε <15 ημέρες`]);
    if (d.summary.openTickets) alerts.push(['info', '🎫', `${d.summary.openTickets} ανοιχτ${d.summary.openTickets === 1 ? 'ό ticket' : 'ά tickets'}`]);
    $('#c3Res').innerHTML = `
    <div class="c3-hero">
      <span class="c3-hero-ava">${esc(ini)}</span>
      <div class="c3-hero-main">
        <div class="c3-hero-name">${esc(d.client.name)}</div>
        <div class="c3-hero-meta">#${d.client.id}${d.client.email ? ' · ' + esc(d.client.email) : ''}${d.client.phone ? ' · ' + esc(d.client.phone) : ''}</div>
      </div>
      <div class="c3-hero-acts">
        ${d.client.phone ? `<a class="btn btn-o btn-sm" href="tel:${esc(d.client.phone)}">${I.phone || '📞'} Κλήση</a>` : ''}
        <button class="btn btn-p btn-sm" id="c3Rt">${I.monitor} Remote</button>
      </div>
    </div>
    ${alerts.length ? `<div class="c3-alerts">${alerts.map(([t, ic, txt]) => `<div class="c3-alert ${t}"><span style="font-size:16px">${ic}</span> ${txt}</div>`).join('')}</div>` : ''}
    <div class="grid g4">
      <div class="stat info"><b>${d.summary.services}</b><small>Ενεργές υπηρεσίες</small></div>
      <div class="stat ${d.summary.openTasks ? 'info' : ''}"><b>${d.summary.openTasks}</b><small>Ανοιχτά tasks</small></div>
      <div class="stat ${d.summary.openTickets ? 'warn' : 'ok'}"><b>${d.summary.openTickets}</b><small>Ανοιχτά tickets</small></div>
      ${d.summary.scBalance !== null ? `<div class="stat ${d.summary.scBalance > 0 ? 'ok' : 'bad'}"><b>${fmtMin(d.summary.scBalance)}</b><small>Υπόλοιπο προαγοράς</small></div>` : ''}
    </div>
    <div class="grid g2" style="margin-bottom:14px">
      <div class="card"><div class="card-h">${I.rocket} Έργα του πελάτη <span class="kb-n" style="margin-left:auto">${d.projects.length}</span></div>
        <div class="card-b">
          ${d.projects.length ? `<table class="tbl"><tbody>${d.projects.map(p => `<tr>
            <td><span class="dot" style="background:${p.color};width:9px;height:9px;margin-right:7px"></span>
              <a href="#/board/${p.id}" style="font-weight:700">${esc(p.name)}</a>
              ${p.manager ? `<span class="mut" style="font-size:11px"> · ${esc(p.manager)}</span>` : ''}</td>
            <td style="width:150px"><div class="bar"><span class="ok" style="width:${p.tasks ? Math.round((p.tasks - p.open) / p.tasks * 100) : 0}%"></span></div>
              <small class="mut">${p.tasks - p.open}/${p.tasks} εργασίες</small></td>
            <td style="width:110px" class="${p.due && p.due < today() ? 'pill pill-bad' : 'mut'}">${p.due ? dShort(p.due) : '—'}</td>
          </tr>`).join('')}</tbody></table>`
            : `<div class="empty" style="padding:16px">Κανένα έργο για αυτόν τον πελάτη.
               <div class="mut" style="font-size:11.5px;margin-top:5px">Φτιάξε ένα και μοίρασε τις εργασίες του στις ομάδες.</div></div>`}
          ${d.full ? `<div style="margin-top:10px"><button class="btn btn-o btn-sm" id="c3NewPj">${I.plus} Νέο έργο για ${esc(d.client.name)}</button></div>` : ''}
        </div></div>

      ${d.openTasks.length ? `<div class="card"><div class="card-h">${I.list} Ανοιχτές εργασίες <span class="kb-n" style="margin-left:auto">${d.openTasks.length}</span></div>
        <div class="card-b"><table class="tbl"><tbody>${d.openTasks.map(t => `<tr>
          <td><a href="javascript:" data-c3task="${t.id}" style="font-weight:600">${esc(t.title)}</a>
            <div class="mut" style="font-size:11px">${esc(t.project || '—')}
              ${t.dept ? `· <a href="#/unit/${t.deptId}">${esc(t.dept)}</a>` : '<span class="pill pill-warn" style="padding:0 5px">χωρίς ομάδα</span>'}</div></td>
          <td style="width:130px">${esc(t.assignee || '—')}</td>
          <td style="width:110px" class="${t.due && t.due < today() ? 'pill pill-bad' : 'mut'}">${t.due ? dShort(t.due) : '—'}</td>
        </tr>`).join('')}</tbody></table></div></div>` : ''}

      ${d.openTicketList.length ? `<div class="card"><div class="card-h">${I.ticket} Ανοιχτά tickets <span class="kb-n" style="margin-left:auto">${d.openTicketList.length}</span></div>
        <div class="card-b"><table class="tbl"><tbody>${d.openTicketList.map(t => `<tr>
          <td><a href="#/inbox/${t.id}" style="font-weight:600">${esc(t.title)}</a>
            <div class="mut" style="font-size:11px">#${esc(String(t.tid))}${t.dept ? ' · ' + esc(t.dept) : ''}</div></td>
          <td style="width:110px"><span class="pill pill-mut">${esc(t.status)}</span></td>
          <td style="width:120px" class="mut">${t.last ? dShort(t.last) : '—'}</td>
        </tr>`).join('')}</tbody></table></div></div>` : ''}

      <div class="card"><div class="card-h">${I.box} Υπηρεσίες & προγράμματα <span class="kb-n" style="margin-left:auto">${d.services.length}</span></div>
        <div class="card-b" style="display:flex;flex-direction:column;gap:8px">
          ${d.services.length ? d.services.map(sv => {
            const days = sv.due ? Math.round((new Date(sv.due) - Date.now()) / 86400000) : null;
            return `<div class="c3-svc">
              <div style="flex:1;min-width:0"><b style="font-size:13px;color:var(--ink);display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(sv.product)}</b>
                <span class="mut" style="font-size:11.5px">${esc(sv.domain || sv.ip || '—')}${d.full && sv.amount ? ' · ' + fmtEur(sv.amount) + '/' + (sv.cycle || '').slice(0, 3) : ''}</span></div>
              <div style="text-align:right;flex:none">
                <span class="pill ${sv.status === 'Active' ? 'pill-ok' : 'pill-warn'}">${sv.status === 'Active' ? 'Ενεργό' : 'Αναστολή'}</span>
                ${sv.due ? `<div class="mut" style="font-size:10.5px;margin-top:3px">λήγει ${dShort(sv.due)}${days !== null && days < 30 ? ` <b style="color:${days < 15 ? 'var(--bad)' : 'var(--warn)'}">(${days}ημ)</b>` : ''}</div>` : ''}
              </div></div>`;
          }).join('') : '<div class="empty" style="padding:20px">Καμία ενεργή υπηρεσία</div>'}
        </div></div>
      <div>
        <div class="card"><div class="card-h">${I.shield} SLA & Συμβόλαιο</div><div class="card-b">
          ${d.full ? `<div class="set-row"><b>${I.ticket} Πακέτο υποστήριξης</b>
            <select class="inp" id="c3Pk" style="width:auto;padding:5px 9px;font-size:12px">
              <option value="0">— χωρίς πακέτο —</option>
              ${d.packages.map(pk => `<option value="${pk.id}" ${pk.id === d.package ? 'selected' : ''}>${esc(pk.name)}</option>`).join('')}
            </select></div>` : (d.package ? '' : '')}
          ${d.sla ? `
            <div class="set-row"><b>Κατάσταση</b><span class="pill ${d.sla.enabled ? 'pill-ok' : 'pill-mut'}">${d.sla.enabled ? '✓ Ενεργό' : 'Ανενεργό'}${d.sla.label ? ' · ' + esc(d.sla.label) : ''}</span></div>
            <div class="set-row"><b>Προτεραιότητα</b><span class="pill ${d.sla.priority === 'High' ? 'pill-bad' : d.sla.priority === 'Medium' ? 'pill-warn' : 'pill-mut'}">${esc(d.sla.priority || '—')}</span></div>
            <div class="set-row"><b>Χρόνος απόκρισης</b><b style="font-size:13px">${esc(d.sla.response || '—')}</b></div>
            <div class="set-row"><b>Υπόλοιπο ωρών</b><span class="pill ${d.sla.balance > 0 ? 'pill-ok' : 'pill-bad'}">${fmtMin(d.sla.balance)}</span></div>
            ${d.sla.met90 !== null ? `<div class="set-row"><b>SLA επίδοση 90ημ</b><span class="pill ${d.sla.met90 >= 90 ? 'pill-ok' : 'pill-warn'}">${d.sla.met90}%</span></div>` : ''}`
            : '<div class="empty" style="padding:14px">Χωρίς συμβόλαιο υποστήριξης</div>'}
        </div></div>
        ${d.remote ? `<div class="card"><div class="card-h">${I.monitor} Remote υποστήριξη</div><div class="card-b">
          <div class="set-row"><b>Τρέχων μήνας</b><span class="pill pill-info">${fmtMin(d.remote.monthMins)}</span></div>
          <div class="set-row"><b>Τελευταίες 90 ημέρες</b><span class="mut" style="font-size:12.5px">${d.remote.sessions90} συνεδρίες · ${fmtMin(d.remote.mins90)}</span></div>
        </div></div>` : ''}
        <div class="card"><div class="card-h">${I.coin} Ανοιχτό υπόλοιπο</div><div class="card-b">
          <div class="set-row"><b>${d.owed.flag ? 'Έχει ανοιχτό υπόλοιπο' : 'Χωρίς οφειλές'}</b>
            <span class="pill ${d.owed.flag ? 'pill-bad' : 'pill-ok'}">${d.owed.flag ? (d.full ? fmtEur(d.owed.amount) + ' · ' + d.owed.count + ' τιμ.' : 'ΝΑΙ') : '✓ ΟΧΙ'}</span></div>
          ${!d.full && d.owed.flag ? '<div class="mut" style="font-size:11.5px">Ενημέρωσε τον πελάτη να απευθυνθεί στο λογιστήριο για λεπτομέρειες.</div>' : ''}
        </div></div>
        ${(d.client.phone || d.people.length) ? `<div class="card"><div class="card-h">${I.contact} Επικοινωνία</div><div class="card-b">
          ${d.client.phone ? `<div class="set-row"><b>Τηλέφωνο</b><a href="tel:${esc(d.client.phone)}">${esc(d.client.phone)}</a></div>` : ''}
          ${d.people.map(pp => `<div class="set-row"><div><b style="font-size:12.5px">${esc(pp.name)}</b>
            ${pp.title ? `<span class="mut" style="font-size:11px"> · ${esc(pp.title)}</span>` : ''}</div>
            <span class="mut" style="font-size:11.5px">${esc(pp.phone || pp.email || '')}</span></div>`).join('')}
        </div></div>` : ''}
      </div>
    </div>
    <div style="margin:4px 0 12px">${[3, 6, 12, 120].map(m =>
      `<button class="btn btn-sm ${m === d.months ? 'btn-p' : 'btn-o'}" data-m="${m}" style="margin-right:6px">${m === 120 ? 'όλα' : m + ' μήνες'}</button>`).join('')}</div>
    <div class="card"><div class="card-h">Ιστορικό (${d.timeline.length})</div><div class="card-b" style="max-height:560px;overflow:auto">
      ${d.timeline.map(e => {
        const day = dShort(e.ts);
        const hdr = day !== lastDay ? `<div style="font-weight:800;color:var(--ink);border-bottom:1px solid var(--line);margin:12px 0 6px;padding-bottom:3px">${day}</div>` : '';
        lastDay = day;
        return hdr + `<div style="display:flex;gap:9px;padding:4px 0;font-size:13px;align-items:baseline">
          <span>${typeIco[e.type] || '·'}</span><span style="flex:1">${esc(e.title)}
          ${e.meta ? `<span class="mut" style="font-size:11.5px"> — ${esc(e.meta)}</span>` : ''}</span></div>`;
      }).join('') || '<div class="empty">Καμία δραστηριότητα</div>'}
    </div></div>`;
    $$('#c3Res [data-m]').forEach(b => b.onclick = () => show(id, +b.dataset.m));
    $$('#c3Res [data-c3task]').forEach(a => a.onclick = () => openTask(+a.dataset.c3task));
    const npj = $('#c3NewPj');
    if (npj) npj.onclick = () => { R.projects._pre = {client: id, clientName: d.client.name}; go('projects'); };
    const rtb = $('#c3Rt');
    if (rtb) rtb.onclick = () => window.CNP.startRemote(id, d.client.name, 0, {email: d.client.email || ''});
    const pkSel = $('#c3Pk');
    if (pkSel) pkSel.onchange = async () => {
      await api('client_package_set', {client: id, package: +pkSel.value});
      toast('Το πακέτο του πελάτη ενημερώθηκε 🎟');
    };
  };
  // live auto-match: εμφανίζει αποτελέσματα καθώς πληκτρολογείς — επιλέγεις με κλικ (ή Enter για το 1ο)
  let c3t;
  const liveSearch = async q => {
    const d = await api('client360&q=' + encodeURIComponent(q)).catch(() => null);
    if (!d || $('#c3Q').value.trim() !== q) return;   // αγνόησε παλιά/άκυρα responses
    const matches = d.client ? [d.client] : (d.matches || []);
    $('#c3Res').innerHTML = matches.length ? `<div class="card" style="margin-top:2px;overflow:hidden">${matches.map(m =>
      `<div class="trow" data-c="${m.id}" style="gap:10px"><span class="ava" style="flex:none">${esc((m.name || '?').trim().split(/\s+/).map(w => w[0] || '').slice(0, 2).join('').toUpperCase())}</span>
        <div style="flex:1;min-width:0"><b style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(m.name)}</b>
        <span class="mut" style="font-size:11.5px">#${m.id}${m.email ? ' · ' + esc(m.email) : ''}</span></div></div>`).join('')}</div>`
      : '<div class="empty" style="padding:22px">Δεν βρέθηκε πελάτης</div>';
    // (το click χειρίζεται με delegation στο #c3Res → δεν χάνεται σε re-render)
  };
  const pick = id => { clearTimeout(c3t); const q = $('#c3Q'); if (q) q.blur(); show(id); };
  $('#c3Res').onclick = e => { const row = e.target.closest('[data-c]'); if (row) pick(+row.dataset.c); };
  $('#c3Q').oninput = e => {
    clearTimeout(c3t);
    const q = e.target.value.trim();
    if (q.length < 2) { $('#c3Res').innerHTML = ''; return; }
    c3t = setTimeout(() => liveSearch(q), 250);
  };
  $('#c3Q').onkeydown = e => { if (e.key === 'Enter') { const first = $('#c3Res [data-c]'); if (first) pick(+first.dataset.c); } };
  if (cid) show(+cid);
};

/* ═════════ ΚΕΡΔΟΦΟΡΙΑ ═════════ */
/* ── Συμφωνία πληρωμών ─────────────────────────────────────────────────────
   Ένας λογαριασμός PayPal μπορεί να πληρώνει πολλούς πελάτες WHMCS, και ένας
   πελάτης να πληρώνεται από πολλά πρόσωπα. Χειροκίνητα η αντιστοίχιση παίρνει
   ώρες· εδώ γίνεται με μία αναζήτηση. ------------------------------------ */
R.paytrace = async function () {
  setTop('Συμφωνία πληρωμών', 'Πού πήγε κάθε είσπραξη — σε ποιον πελάτη και σε ποιο παραστατικό');
  const c = $('#content');
  const st = R.paytrace._s = R.paytrace._s || {q: ''};
  // Ορίζεται ΠΡΙΝ από κάθε χρήση: οι έλεγχοι τρέχουν νωρίς και χωρίς αυτό
  // πέφτουν σε «Cannot access before initialization».
  const money = v => (v || 0).toFixed(2).replace('.', ',') + ' €';

  const form = `
    <div class="card" style="padding:13px 15px;margin-bottom:12px">
      <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <input class="inp" id="ptQ" style="flex:1;min-width:260px"
          placeholder="email πληρωτή, transaction ID, ποσό ή όνομα…" value="${esc(st.q)}">
        <button class="btn btn-p" id="ptGo">${I.search || ''} Αναζήτηση</button>
        <span style="width:1px;height:22px;background:var(--line)"></span>
        <span class="mut" style="font-size:12px">Από</span>
        <input class="inp" id="ptFrom" type="date" style="width:auto" value="${esc(st.from || '')}">
        <span class="mut" style="font-size:12px">έως</span>
        <input class="inp" id="ptTo" type="date" style="width:auto" value="${esc(st.to || '')}">
        <button class="btn btn-o" id="ptCsv" title="Κατέβασμα για το λογιστήριο">${I.download || '⤓'} CSV</button>
        <button class="btn btn-o" id="ptCsvAll" title="Όλες οι εισπράξεις, ανεξάρτητα από αναζήτηση">⤓ Όλα</button>
      </div>
      <div class="mut" style="font-size:12px;margin-top:7px">
        Ψάχνει σε όλους τους πελάτες μαζί — και μέσα στα IPN των gateway, εκεί όπου ζει το email του πληρωτή.
        Το «Ποσό παραστατικού» είναι η <b>πραγματική αξία</b> — το WHMCS αποθηκεύει στη θέση του το υπόλοιπο
        μετά την πίστωση, γι' αυτό αλλού φαίνεται 0. Η <b>«Υπερπληρωμή πήγε σε»</b> δείχνει πού πήγε το πλεόνασμα.
      </div>
    </div>`;

  const auditBox = '<div id="ptAudit"><div class="skel" style="height:120px;margin-bottom:12px"></div></div>';

  if (!st.q) {
    c.innerHTML = form + auditBox;
    bind();
    audit();
    return;
  }

  c.innerHTML = form + auditBox + skel(3);
  // ΟΧΙ audit() εδώ: θα έτρεχε παράλληλα με την αναζήτηση και όποιο απαντούσε
  // δεύτερο θα έσβηνε το αποτέλεσμα του άλλου. Καλείται αφού χτιστεί η σελίδα.
  const d = await api('pay_trace', {q: st.q}).catch(e => ({err: e.message}));
  if (d.err || !d.rows) {
    c.innerHTML = form + auditBox + `<div class="empty" style="margin-top:30px">${esc(d.err || 'Καμία εγγραφή.')}</div>`;
    bind(); audit();
    return;
  }
  if (!d.rows.length) {
    c.innerHTML = form + auditBox + `<div class="empty" style="margin-top:30px">Καμία πληρωμή δεν ταιριάζει με «${esc(st.q)}».</div>`;
    bind(); audit();
    return;
  }


  c.innerHTML = form + auditBox + `
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px">
      <div class="card" style="padding:13px 15px;flex:1;min-width:150px">
        <div class="mut" style="font-size:11.5px;text-transform:uppercase;font-weight:700">Πληρωμές</div>
        <div style="font-size:25px;font-weight:800">${d.rows.length}</div></div>
      <div class="card" style="padding:13px 15px;flex:1;min-width:150px">
        <div class="mut" style="font-size:11.5px;text-transform:uppercase;font-weight:700">Σύνολο</div>
        <div style="font-size:25px;font-weight:800;color:var(--brand)">${money(d.total)}</div></div>
      <div class="card" style="padding:13px 15px;flex:2;min-width:220px">
        <div class="mut" style="font-size:11.5px;text-transform:uppercase;font-weight:700;margin-bottom:5px">Ανά πελάτη</div>
        ${d.byClient.map(b => `<div style="display:flex;gap:8px;font-size:12.5px;margin-bottom:2px;align-items:center">
          <span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(b.client)}</span>
          <span class="mut">${b.n}×</span><b>${money(b.sum)}</b>
          ${b.id ? `<button class="btn btn-p" data-stmt="${b.id}" style="padding:4px 12px;font-size:12px;font-weight:700;white-space:nowrap">${I.fileText || '▤'} Καρτέλα</button>` : ''}</div>`).join('')}
      </div>
    </div>

    <div class="card" style="padding:0;overflow:hidden">
      <div style="overflow-x:auto">
      <table style="width:100%;border-collapse:collapse;font-size:13px;min-width:900px">
        <thead><tr style="background:var(--bg2)">
          ${['Ημερομηνία','Ποσό','Πληρωτής','Τύπος','Πελάτης WHMCS','Παραστατικό','Υπερπληρωμή πήγε σε','Transaction ID']
            .map(h => `<th style="text-align:left;padding:9px 12px;font-size:11px;text-transform:uppercase;color:var(--mut)">${h}</th>`).join('')}
        </tr></thead>
        <tbody>
        ${d.rows.map(r => `<tr style="border-top:1px solid var(--line)">
          <td style="padding:8px 12px;white-space:nowrap">${esc(String(r.date).slice(0, 16))}</td>
          <td style="padding:8px 12px;font-weight:700;white-space:nowrap">${r.refunded ? `<s style="opacity:.55">${money(r.amount)}</s>` : money(r.amount)}${r.fees ? `<span class="mut" style="font-weight:400;font-size:11px"> −${money(r.fees)}</span>` : ''}${
            r.refunded ? '<div style="color:#16a26a;font-size:10.5px;font-weight:700">επιστράφηκε</div>' : ''}</td>
          <td style="padding:8px 12px">${r.payer ? esc(r.payer) : '<span class="mut">—</span>'}</td>
          <td style="padding:8px 12px"><span class="pill ${r.ptype === 'subscr_payment' ? 'pill-info' : 'pill-mut'}" style="font-size:9.5px">${
            r.ptype === 'subscr_payment' ? 'συνδρομή' : (r.ptype === 'web_accept' ? 'χειροκίνητη' : esc(r.gateway || r.kind || '—'))}</span></td>
          <td style="padding:8px 12px">${r.clientId
            ? `<a href="/cloudonadminpanel/clientssummary.php?userid=${r.clientId}" target="_blank" style="color:var(--brand)">${esc(r.client)}</a>
               <div class="mut" style="font-size:11px">${esc(r.person || '')}</div>`
            : '<span class="mut">—</span>'}</td>
          <td style="padding:8px 12px;white-space:nowrap">${r.invoiceId
            ? `<a href="/cloudonadminpanel/index.php/billing/invoice/${r.invoiceId}" target="_blank" style="color:var(--brand)">${esc(r.invoice || '#' + r.invoiceId)}</a>
               <div class="mut" style="font-size:11px">${r.invTotal !== null ? money(r.invTotal) + ' · ' + esc(r.invStatus || '') : ''}${
                 r.invCredit ? `<br><span style="color:var(--warn)">πίστωση ${money(r.invCredit)}</span>` : ''}</div>`
            : '<span class="mut">—</span>'}</td>
          <td style="padding:8px 12px">${
            (r.onward && r.onward.length)
              ? r.onward.map(o => `<div style="white-space:nowrap"><a href="/cloudonadminpanel/index.php/billing/invoice/${o.invoice}" target="_blank" style="color:var(--brand)">${esc(o.num)}</a>
                  <span class="mut" style="font-size:11px">${money(o.amount)}</span></div>`).join('')
              : (r.invoice ? `<span class="mut" style="font-size:11.5px">—</span>` : '<span class="mut">—</span>')}</td>
          <td style="padding:8px 12px;font-family:ui-monospace,monospace;font-size:11px">${esc(r.transid || '—')}</td>
        </tr>`).join('')}
        </tbody>
      </table></div>
    </div>
    <div id="ptStmt"></div>`;
  bind();
  audit();

  /* ── Οικονομικοί έλεγχοι ────────────────────────────────────────────────
     Τέσσερα σημεία όπου τα χρήματα διαφεύγουν χωρίς να το προσέξει κανείς.
     Κάθε πλακίδιο ανοίγει τη λίστα του. ---------------------------------- */
  async function audit() {
    if (!$('#ptAudit')) return;
    const a = await api('fin_audit', {}).catch(() => null);
    /* ΚΡΙΣΙΜΟ: ξαναβρίσκουμε το στοιχείο ΜΕΤΑ την αναμονή. Στη διαδρομή με
       αναζήτηση, το περιεχόμενο της σελίδας ξαναχτίζεται όσο περιμένουμε —
       το παλιό στοιχείο έχει αποσπαστεί και η εγγραφή σε αυτό χάνεται. */
    const host = $('#ptAudit');
    if (!host) return;
    if (!a) { host.innerHTML = ''; return; }
    const S2 = a.summary;
    const tile = (k, icon, title, sub, col) => `
      <button class="card" data-aud="${k}" style="padding:14px 16px;flex:1;min-width:190px;text-align:left;
        border:1px solid var(--line);cursor:pointer;background:var(--card)">
        <div class="mut" style="font-size:11.5px;font-weight:700;text-transform:uppercase;letter-spacing:.3px">${icon} ${title}</div>
        <div style="font-size:26px;font-weight:800;color:${S2[k].n ? col : 'var(--mut)'};line-height:1.15;margin-top:3px">${S2[k].n}</div>
        <div class="mut" style="font-size:11.5px">${sub} · ${money(S2[k].sum)}</div>
      </button>`;

    host.innerHTML = `
      <div style="font-weight:700;font-size:13.5px;margin:4px 0 9px">Οικονομικοί έλεγχοι</div>
      <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px">
        ${tile('mismatch', '⚖', 'Ασυμφωνία βιβλίων', 'πελάτες', 'var(--bad)')}
        ${tile('overpaid', '↑', 'Υπερπληρωμένα', 'παραστατικά', '#e0a020')}
        ${tile('zombie', '👻', 'Ζόμπι συνδρομές', 'υπηρεσίες', 'var(--bad)')}
        ${tile('debt', '€', 'Πραγματικές οφειλές', 'πελάτες', '#e0a020')}
        ${tile('legacy', '⌛', 'Πληρωμένα χωρίς αντιστοίχιση', 'παραστατικά', 'var(--mut)')}
      </div>
      <div id="ptAudList"></div>`;

    $$('[data-aud]').forEach(b => b.onclick = () => audList(b.dataset.aud));
  }

  async function audList(sec) {
    const host = $('#ptAudList');
    host.innerHTML = '<div class="skel" style="height:180px"></div>';
    const a = await api('fin_audit', {section: sec}).catch(() => null);
    if (!a || !a.rows) { host.innerHTML = '<div class="empty">—</div>'; return; }

    const link = (id, txt) => `<a href="/cloudonadminpanel/clientssummary.php?userid=${id}" target="_blank" style="color:var(--brand)">${esc(txt)}</a>`;
    const th = h => `<th style="text-align:left;padding:8px 12px;font-size:11px;text-transform:uppercase;color:var(--mut)">${h}</th>`;
    let head = '', body = '', title = '', hint = '';

    if (sec === 'mismatch') {
      title = 'Ασυμφωνία βιβλίων';
      hint = 'Ο ανεξάρτητος υπολογισμός (τιμολογημένα − εισπράξεις − πιστωτικά) δεν συμφωνεί με τα ανεξόφλητα που δηλώνει το WHMCS. Από κάτω φαίνεται ΠΟΙΑ παραστατικά τη φέρνουν — σχεδόν πάντα παλιά, σημασμένα «Paid» χωρίς καταχωρημένη συναλλαγή.';
      head = th('Πελάτης') + th('Υπολογισμός') + th('WHMCS') + th('Διαφορά') + th('Από πού');
      body = a.rows.map(r => {
        const bad = (r.bad || []).map(x => `<span style="display:inline-block;margin:0 14px 3px 0">
            <a href="/cloudonadminpanel/index.php/billing/invoice/${x.invoice}" target="_blank" style="color:var(--brand)">${esc(x.num)}</a>
            <span class="mut">${esc(dFull(x.date))}</span> <b>${money(x.diff)}</b>
            <span style="color:${x.diff > 0 ? 'var(--bad)' : '#e0a020'}">${esc(x.why)}</span></span>`).join('');
        return `<tr style="border-top:1px solid var(--line)">
        <td style="padding:7px 12px">${link(r.client, r.name)}</td>
        <td style="padding:7px 12px">${money(r.mine)}</td>
        <td style="padding:7px 12px">${money(r.whmcs)}</td>
        <td style="padding:7px 12px;font-weight:700;color:var(--bad)">${money(r.diff)}</td>
        <td style="padding:7px 12px">${r.badN ? r.badN + (r.badN === 1 ? ' παραστατικό' : ' παραστατικά') : '<span class="mut">—</span>'}
          ${r.oldest ? `<div class="mut" style="font-size:10.5px">παλαιότερο ${esc(dFull(r.oldest))}</div>` : ''}
          ${r.unalloc ? `<div style="font-size:10.5px;color:#e0a020">${money(r.unalloc)} πληρωμές χωρίς παραστατικό</div>` : ''}
          ${r.onCancelled ? `<div style="font-size:10.5px;color:#e0a020">${money(r.onCancelled)} σε ${r.onCancelledN} ακυρωμένα παραστατικά</div>` : ''}</td></tr>
        ${bad ? `<tr><td colspan="5" style="padding:0 12px 8px 12px;font-size:11.5px" class="mut">${bad}${r.badN > (r.bad || []).length ? ` <span class="mut">…και ${r.badN - r.bad.length} ακόμη</span>` : ''}</td></tr>` : ''}`;
      }).join('');
    } else if (sec === 'overpaid') {
      title = 'Υπερπληρωμένα παραστατικά';
      hint = 'Εισπράχθηκαν περισσότερα από την αξία του παραστατικού. Όσα το WHMCS μετέτρεψε μόνο του σε πίστωση δεν εκκρεμούν — το πλακίδιο μετράει μόνο τα ατακτοποίητα, που θέλουν επιστροφή ή πίστωση.';
      head = th('Παραστατικό') + th('Πελάτης') + th('Αξία') + th('Εισπράχθηκαν') + th('Πληρωμές') + th('Διαφορά') + th('Τακτοποίηση');
      body = a.rows.map(r => `<tr style="border-top:1px solid var(--line)">
        <td style="padding:7px 12px"><a href="/cloudonadminpanel/index.php/billing/invoice/${r.invoice}" target="_blank" style="color:var(--brand)">${esc(r.num)}</a></td>
        <td style="padding:7px 12px">${link(r.client, r.name)}</td>
        <td style="padding:7px 12px">${money(r.value)}</td>
        <td style="padding:7px 12px">${money(r.paid)}</td>
        <td style="padding:7px 12px">${r.n}×</td>
        <td style="padding:7px 12px;font-weight:700;color:${r.credited > 0 ? 'var(--mut)' : '#e0a020'}">+${money(r.over)}</td>
        <td style="padding:7px 12px;font-size:11px">${r.credited > 0
            ? '<span style="color:#16a26a">μετατράπηκε σε πίστωση</span>'
            : '<span style="color:var(--bad);font-weight:700">εκκρεμεί</span>'}</td></tr>`).join('');
    } else if (sec === 'zombie') {
      title = 'Ζόμπι συνδρομές';
      hint = 'Ακυρωμένη ή τερματισμένη υπηρεσία που κρατά αναγνωριστικό συνδρομής. Το εύρημα δεν είναι η ίδια η συνδρομή — είναι τα χρήματα που ΕΙΣΠΡΑΧΘΗΚΑΝ μετά την ακύρωση και δεν επιστράφηκαν. Οι πληρωμές αντιστοιχίζονται στη συνδρομή μέσω του subscr_id στα IPN της PayPal.';
      head = th('Υπηρεσία') + th('Πελάτης') + th('Ποσό') + th('Συνδρομή') + th('Ακύρωση') + th('Εισπράξεις') + th('Αχρεωστήτως');
      body = a.rows.map(r => {
        const bad = (r.openAmt || 0) + (r.orphan || 0);
        const pay = (r.pays || []).map(p => `<span style="display:inline-block;margin:0 12px 3px 0;${p.refunded ? 'opacity:.55;text-decoration:line-through' : ''}">
            ${esc(dFull(p.date))} <b>${money(p.amount)}</b>
            ${p.refunded ? '<span style="color:#16a26a">επιστράφηκε</span>'
              : p.invoice ? `<a href="/cloudonadminpanel/index.php/billing/invoice/${p.invoice}" target="_blank" style="color:var(--brand)">παρ. ${p.invoice}</a>`
              : '<span style="color:var(--bad);font-weight:700">χωρίς παραστατικό</span>'}</span>`).join('');
        return `<tr style="border-top:1px solid var(--line)">
        <td style="padding:7px 12px">#${r.service}<div class="mut" style="font-size:11px">${esc(r.domain || '')}</div></td>
        <td style="padding:7px 12px">${link(r.client, r.name)}</td>
        <td style="padding:7px 12px">${money(r.amount)}<span class="mut" style="font-size:11px">/${esc(r.cycle)}</span></td>
        <td style="padding:7px 12px;font-family:ui-monospace,monospace;font-size:11px">${esc(r.sub)}
          ${r.realSub ? '' : '<div class="mut" style="font-family:inherit;font-size:10.5px">κατάλοιπο, όχι συνδρομή PayPal</div>'}</td>
        <td style="padding:7px 12px">${r.cancel ? esc(dFull(r.cancel)) + `<div class="mut" style="font-size:10.5px">${esc(r.cancelSrc || '')}</div>` : '<span class="mut">άγνωστη</span>'}</td>
        <td style="padding:7px 12px">${r.payN ? r.payN + '×' : '<span class="mut">—</span>'}</td>
        <td style="padding:7px 12px;font-weight:700;color:${bad ? 'var(--bad)' : 'var(--mut)'}">${bad ? money(bad) : '—'}
          ${r.orphan ? '<div style="font-weight:600;font-size:10.5px">χωρίς παραστατικό</div>' : ''}</td></tr>
        ${pay ? `<tr><td colspan="7" style="padding:0 12px 8px 12px;font-size:11.5px" class="mut">${pay}</td></tr>` : ''}`;
      }).join('');
    } else if (sec === 'legacy') {
      title = 'Πληρωμένα χωρίς αντιστοίχιση πληρωμής';
      hint = 'Παραστατικά σημασμένα «Paid» που δεν έχουν (ή έχουν λιγότερη) καταχωρημένη είσπραξη — αφού μετρηθεί και η πίστωση που εφαρμόστηκε πάνω τους. ΔΕΝ είναι οφειλή — η πλατφόρμα δουλευόταν χειροκίνητα επί χρόνια. Μετριούνται ως εισπραγμένα στους άλλους ελέγχους· η λίστα υπάρχει για να τακτοποιηθούν σιγά σιγά, ξεκινώντας από τα πρόσφατα.';
      head = th('Παραστατικό') + th('Πελάτης') + th('Ημερομηνία') + th('Εξοφλήθηκε') + th('Τρόπος') + th('Αξία') + th('Καταχωρημένα') + th('Πίστωση') + th('Λείπει');
      body = a.rows.map(r => `<tr style="border-top:1px solid var(--line)">
        <td style="padding:7px 12px"><a href="/cloudonadminpanel/index.php/billing/invoice/${r.invoice}" target="_blank" style="color:var(--brand)">${esc(r.num)}</a></td>
        <td style="padding:7px 12px">${link(r.client, r.name)}</td>
        <td style="padding:7px 12px">${esc(dFull(r.date))}</td>
        <td style="padding:7px 12px">${r.datepaid ? esc(dFull(r.datepaid)) : '—'}</td>
        <td style="padding:7px 12px" class="mut">${esc(r.method || '—')}</td>
        <td style="padding:7px 12px">${money(r.gross)}</td>
        <td style="padding:7px 12px">${r.paid ? money(r.paid) : '<span class="mut">—</span>'}</td>
        <td style="padding:7px 12px" class="mut">${r.credit ? money(r.credit) : '—'}</td>
        <td style="padding:7px 12px;font-weight:700">${money(r.gap)}</td></tr>`).join('');
    } else {
      title = 'Πραγματικές οφειλές';
      hint = 'Τιμολογημένα μείον όσα εισπράχθηκαν πραγματικά — ανεξάρτητα από το πώς τα εμφανίζει το WHMCS.';
      head = th('Πελάτης') + th('Οφειλή') + th('Ανεξόφλητα κατά WHMCS');
      body = a.rows.map(r => `<tr style="border-top:1px solid var(--line)">
        <td style="padding:7px 12px">${link(r.client, r.name)}</td>
        <td style="padding:7px 12px;font-weight:700;color:var(--bad)">${money(r.balance)}</td>
        <td style="padding:7px 12px">${money(r.unpaid)}</td></tr>`).join('');
    }

    host.innerHTML = `
      <div class="card" style="padding:0;overflow:hidden">
        <div class="card-h" style="display:flex;align-items:center;gap:9px;flex-wrap:wrap">
          <b>${title}</b><span class="mut" style="font-size:12px">${a.rows.length} εγγραφές</span>
          <span style="flex:1"></span>
          <a class="btn btn-o btn-sm" href="api.php?a=fin_audit_csv&section=${sec}">⤓ CSV</a>
          <button class="btn btn-o btn-sm" id="ptAudX" title="Κλείσιμο" style="padding:2px 9px;font-size:15px">×</button>
        </div>
        <div class="mut" style="padding:9px 15px 0;font-size:12.5px;line-height:1.5">${hint}</div>
        <div style="overflow-x:auto;margin-top:8px">
          <table style="width:100%;border-collapse:collapse;font-size:13px;min-width:640px">
            <thead><tr style="background:var(--bg2)">${head}</tr></thead>
            <tbody>${body}</tbody>
          </table></div>
      </div>`;
    const x = $('#ptAudX');
    if (x) x.onclick = () => { host.innerHTML = ''; };
    host.scrollIntoView({behavior: 'smooth', block: 'nearest'});
  }

  /* Καρτέλα πελάτη — η μόνη μορφή που διαβάζεται λογιστικά:
     ΧΡΕΩΣΗ = αξία παραστατικού, ΠΙΣΤΩΣΗ = χρήματα που μπήκαν, τρέχον υπόλοιπο. */
  async function statement(cid) {
    const host = $('#ptStmt');
    host.innerHTML = '<div class="skel" style="height:220px;margin-top:12px"></div>';
    const st2 = await api('pay_statement', {client: cid}).catch(e => ({err: e.message}));
    if (st2.err || !st2.rows) { host.innerHTML = `<div class="empty">${esc(st2.err || '—')}</div>`; return; }
    const neg = v => v < -0.005;
    host.innerHTML = `
      <div class="card" style="margin-top:14px;padding:0;overflow:hidden">
        <div class="card-h" style="display:flex;align-items:center;gap:9px;flex-wrap:wrap">
          <b>Καρτέλα — ${esc(st2.client.name)}</b>
          <span class="mut" style="font-size:12px">${esc(st2.client.person)}</span>
          <span style="flex:1"></span>
          <span class="pill ${st2.balance > 0.005 ? 'pill-bad' : (neg(st2.balance) ? 'pill-info' : 'pill-mut')}">
            ${st2.balance > 0.005 ? 'οφειλή ' + money(st2.balance)
              : (neg(st2.balance) ? 'προπληρωμή ' + money(-st2.balance) : 'μηδενικό υπόλοιπο')}</span>
          <a class="btn btn-o btn-sm" href="api.php?a=pay_statement_csv&client=${cid}">⤓ CSV</a>
          <button class="btn btn-o btn-sm" id="ptStmtX" title="Κλείσιμο καρτέλας" aria-label="Κλείσιμο"
            style="padding:2px 9px;font-size:15px;line-height:1.2">×</button>
        </div>
        <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;font-size:13px;min-width:640px">
          <thead><tr style="background:var(--bg2)">
            <th style="text-align:left;padding:8px 12px;font-size:11px;text-transform:uppercase;color:var(--mut)">Ημερομηνία</th>
            <th style="text-align:left;padding:8px 12px;font-size:11px;text-transform:uppercase;color:var(--mut)">Κίνηση</th>
            <th style="text-align:right;padding:8px 12px;font-size:11px;text-transform:uppercase;color:var(--mut)">Χρέωση</th>
            <th style="text-align:right;padding:8px 12px;font-size:11px;text-transform:uppercase;color:var(--mut)">Πίστωση</th>
            <th style="text-align:right;padding:8px 14px;font-size:11px;text-transform:uppercase;color:var(--mut)">Υπόλοιπο</th>
          </tr></thead>
          <tbody>
          ${st2.rows.map(r => `<tr style="border-top:1px solid var(--line)">
            <td style="padding:7px 12px;white-space:nowrap">${esc(dFull(r.date))}</td>
            <td style="padding:7px 12px">${r.ref
                ? `<a href="/cloudonadminpanel/index.php/billing/invoice/${r.ref}" target="_blank" style="color:var(--brand)">${esc(r.label)}</a>`
                : esc(r.label)}</td>
            <td style="padding:7px 12px;text-align:right${r.kind === 'refund' ? ';color:#e0a020' : ''}">${r.debit ? money(r.debit) : ''}</td>
            <td style="padding:7px 12px;text-align:right;color:${r.kind === 'assumed' ? 'var(--mut)' : '#16a26a'}">${r.credit ? money(r.credit) : ''}${
              r.kind === 'assumed' ? '<div style="font-size:10px">χωρίς παραστατικό πληρωμής</div>' : ''}</td>
            <td style="padding:7px 14px;text-align:right;font-weight:700;color:${r.balance > 0.005 ? 'var(--bad)' : (neg(r.balance) ? 'var(--brand)' : 'var(--mut)')}">${money(r.balance)}</td>
          </tr>`).join('')}
          </tbody>
          <tfoot><tr style="border-top:2px solid var(--line);background:var(--bg2)">
            <td colspan="2" style="padding:9px 12px;font-weight:700">ΣΥΝΟΛΑ</td>
            <td style="padding:9px 12px;text-align:right;font-weight:700">${money(st2.debit)}</td>
            <td style="padding:9px 12px;text-align:right;font-weight:700;color:#16a26a">${money(st2.credit)}</td>
            <td style="padding:9px 14px;text-align:right;font-weight:800">${money(st2.balance)}</td>
          </tr></tfoot>
        </table></div>
      </div>`;
    const x = $('#ptStmtX');
    if (x) x.onclick = () => { host.innerHTML = ''; };
    host.scrollIntoView({behavior: 'smooth', block: 'start'});
  }

  function bind() {
    $$('[data-stmt]').forEach(b => b.onclick = () => statement(+b.dataset.stmt));
    const q = $('#ptQ'), go = $('#ptGo');
    if (!q) return;
    const grab = () => {
      st.q = q.value.trim();
      st.from = ($('#ptFrom') || {}).value || '';
      st.to = ($('#ptTo') || {}).value || '';
    };
    const run = () => { grab(); R.paytrace(); };
    if (go) go.onclick = run;
    q.onkeydown = e => { if (e.key === 'Enter') run(); };

    // Η λήψη γίνεται με απευθείας πλοήγηση: το αρχείο κατεβαίνει, η σελίδα μένει.
    const dl = all => {
      grab();
      const p = new URLSearchParams({a: 'pay_trace_export'});
      if (all) { p.set('all', '1'); } else if (st.q) { p.set('q', st.q); }
      if (st.from) { p.set('from', st.from); }
      if (st.to) { p.set('to', st.to); }
      window.location = 'api.php?' + p.toString();
    };
    const b1 = $('#ptCsv'), b2 = $('#ptCsvAll');
    if (b1) b1.onclick = () => dl(false);
    if (b2) b2.onclick = () => dl(true);
    q.focus();
  }
};

R.profit = async function () {
  setTop('Κερδοφορία', 'Έσοδα − κόστος εργασίας − έξοδα, ανά πελάτη');
  const c = $('#content');
  const f = R.profit._f = R.profit._f || {};
  c.innerHTML = skel(4);
  const qs = Object.entries(f).filter(([, v]) => v).map(([k, v]) => k + '=' + v).join('&');
  const d = await api('profit' + (qs ? '&' + qs : '')).catch(() => null);
  if (!d) { c.innerHTML = `<div class="empty"><div class="big">${I.lock}</div>Μόνο για διαχειριστές</div>`; return; }
  const tot = d.clients.reduce((a, x) => ({rev: a.rev + x.rev, labor: a.labor + x.labor, exp: a.exp + x.exp}), {rev: 0, labor: 0, exp: 0});
  const net = tot.rev - tot.labor - tot.exp;
  c.innerHTML = `
  <div class="card" style="padding:13px 16px;display:flex;gap:9px;flex-wrap:wrap;align-items:center">
    <input type="date" class="inp" id="pF" style="width:auto" value="${d.from}">
    <input type="date" class="inp" id="pT" style="width:auto" value="${d.to}">
    <button class="btn btn-p btn-sm" id="pGo">Προβολή</button>
    <span class="mut" style="margin-left:auto">Κόστος ώρας: <b>${fmtEur(d.costH)}</b>${d.costH <= 0 ? ' — όρισέ το στις ρυθμίσεις του module' : ''}</span></div>
  <div class="grid g4">
    <div class="stat ok"><b>${fmtEur(tot.rev)}</b><small>Έσοδα (πελάτες με έργο)</small></div>
    <div class="stat warn"><b>${fmtEur(tot.labor)}</b><small>Κόστος εργασίας</small></div>
    <div class="stat warn"><b>${fmtEur(tot.exp)}</b><small>Έξοδα projects</small></div>
    <div class="stat ${net >= 0 ? 'ok' : 'bad'}"><b>${fmtEur(net)}</b><small>Καθαρό</small></div>
  </div>
  <div class="card"><div class="card-h">Ανά πελάτη <span class="mut" style="font-weight:600">(οι ζημιογόνοι πρώτα)</span></div>
    <table class="tbl"><thead><tr><th>Πελάτης</th><th>Χρόνος</th><th>Κόστος</th><th>Έξοδα</th><th>Έσοδα</th><th>Κέρδος</th><th>Περιθώριο</th></tr></thead><tbody>
    ${d.clients.length ? d.clients.map(x => `<tr>
      <td><b>${esc(x.name)}</b></td><td>${fmtMin(x.mins)}</td><td>${fmtEur(x.labor)}</td><td>${fmtEur(x.exp)}</td>
      <td>${fmtEur(x.rev)}</td><td style="color:${x.profit >= 0 ? 'var(--ok)' : 'var(--bad)'}"><b>${fmtEur(x.profit)}</b></td>
      <td>${x.rev > 0 ? Math.round(x.profit / x.rev * 100) + '%' : '—'}</td></tr>`).join('')
      : '<tr><td colspan="7" class="empty">Καμία εργασία/έξοδο στην περίοδο</td></tr>'}</tbody></table></div>
  <div class="card"><div class="card-h">${I.receipt} Έξοδα projects</div><div class="card-b" style="padding-bottom:8px">
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px">
      <select class="inp" id="eP" style="width:auto">${d.projects.map(p => `<option value="${p.id}">${esc(p.name)}</option>`).join('')}</select>
      <input class="inp" id="eD" placeholder="περιγραφή" style="flex:1;min-width:150px">
      <input class="inp" id="eA" type="number" step="0.01" placeholder="ποσό €" style="width:110px">
      <input class="inp" id="eAt" type="date" value="${today()}" style="width:auto">
      <button class="btn btn-p btn-sm" id="eAdd">Καταχώρηση</button></div></div>
    ${d.expenses.length ? `<table class="tbl"><tbody>${d.expenses.map(e => `<tr>
      <td>${dShort(e.at)}</td><td>${esc(e.project)}</td><td>${esc(e.client || '—')}</td>
      <td>${esc(e.descr)}</td><td><b>${fmtEur(e.amount)}</b></td>
      <td><button class="btn btn-sm btn-o" data-de="${e.id}">✕</button></td></tr>`).join('')}</tbody></table>` : ''}
  </div>`;
  $('#pGo').onclick = () => { R.profit._f = {from: $('#pF').value, to: $('#pT').value}; R.profit(); };
  $('#eAdd').onclick = async () => {
    if (!+$('#eA').value) return;
    await api('add_expense', {project: +$('#eP').value, descr: $('#eD').value, amount: +$('#eA').value, at: $('#eAt').value});
    toast('Καταχωρήθηκε'); R.profit();
  };
  $$('[data-de]').forEach(b => b.onclick = async () => { await api('del_expense', {id: +b.dataset.de}); toast('Διαγράφηκε'); R.profit(); });
};

/* ═════════ ΟΜΑΔΕΣ ═════════ */
R.teams = async function () {
  setTop('Ομάδες', 'Οργανόγραμμα — ποιος ανήκει πού');
  const c = $('#content');
  c.innerHTML = skel(3, 200);
  const d = await api('teams');
  c.innerHTML = `
  ${d.canManage ? `<div class="card" style="padding:13px 16px;display:flex;gap:9px;flex-wrap:wrap">
    <input class="inp" id="tmName" placeholder="Όνομα νέας ομάδας" style="max-width:240px">
    <input class="inp" type="color" id="tmColor" value="#0090dd" style="width:52px;padding:4px">
    <button class="btn btn-p btn-sm" id="tmAdd">${I.plus} Νέα ομάδα</button></div>` : ''}
  <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(300px,1fr))">
    ${d.teams.map(t => `<div class="card" style="margin:0;border-top:4px solid ${t.color}">
      <div class="card-h">${esc(t.name)}<span class="kb-n" style="margin-left:auto">${t.members.length}</span></div>
      <div class="card-b">
        ${t.members.map(m => `<div style="display:flex;gap:9px;align-items:center;padding:5px 0;border-bottom:1px dashed var(--line)">
          <span class="ava" style="background:${t.color}">${esc(m.ini)}</span>
          <div style="flex:1"><b>${m.leader ? (I.crown + ' ') : ''}${esc(m.name)}</b>
            ${m.role ? `<div class="mut" style="font-size:11px">${esc(m.role)}</div>` : ''}</div>
          ${d.canManage ? `<button class="btn btn-sm btn-o" data-rm="${t.id}:${m.id}">✕</button>` : ''}</div>`).join('') ||
          '<div class="mut" style="font-size:12.5px">Χωρίς μέλη</div>'}
        ${t.projects.length ? `<div class="mut" style="font-size:11px;margin-top:8px">${I.folder} ${esc(t.projects.slice(0, 4).join(' · '))}${t.projects.length > 4 ? ' +' + (t.projects.length - 4) : ''}</div>` : ''}
        ${d.canManage ? `<div style="display:flex;gap:6px;margin-top:10px;border-top:1px solid var(--line);padding-top:9px;flex-wrap:wrap">
          <select class="inp" data-madm="${t.id}" style="flex:1;min-width:110px;padding:6px 9px;font-size:12px">
            ${S.boot.admins.map(a => `<option value="${a.id}">${esc(a.name)}</option>`).join('')}</select>
          <select class="inp" data-mrole="${t.id}" style="width:120px;padding:6px 9px;font-size:12px">
            <option value="">— ρόλος —</option>${d.roles.map(r => `<option>${esc(r)}</option>`).join('')}</select>
          <label style="font-size:11px;display:flex;align-items:center;gap:3px"><input type="checkbox" data-mlead="${t.id}">${I.crown} </label>
          <button class="btn btn-sm btn-o" data-mgo="${t.id}">+</button>
          <button class="btn btn-sm btn-o" data-tdel="${t.id}" style="margin-left:auto;color:var(--bad)">Διαγραφή</button></div>` : ''}
      </div></div>`).join('')}
    ${d.solo.length ? `<div class="card" style="margin:0;border:1.5px dashed var(--line);box-shadow:none">
      <div class="card-h mut">Χωρίς ομάδα</div><div class="card-b">
      ${d.solo.map(s => `<div style="padding:4px 0">${esc(s.name)}${s.full ? ' <span class="pill pill-info">διαχειριστής</span>' : ''}</div>`).join('')}</div></div>` : ''}
  </div>`;
  if (d.canManage) {
    $('#tmAdd').onclick = async () => {
      if (!$('#tmName').value.trim()) return;
      await api('save_team', {id: 0, name: $('#tmName').value.trim(), color: $('#tmColor').value});
      toast('Δημιουργήθηκε'); R.teams();
    };
    $$('[data-mgo]').forEach(b => b.onclick = async () => {
      const t = b.dataset.mgo;
      await api('team_member_add', {team: +t, admin: +$(`[data-madm="${t}"]`).value,
        role: $(`[data-mrole="${t}"]`).value, leader: $(`[data-mlead="${t}"]`).checked});
      toast('Προστέθηκε'); R.teams();
    });
    $$('[data-rm]').forEach(b => b.onclick = async () => {
      const [t, a] = b.dataset.rm.split(':');
      await api('team_member_del', {team: +t, admin: +a}); R.teams();
    });
    $$('[data-tdel]').forEach(b => b.onclick = async () => {
      if (!(await cnpConfirm('Διαγραφή ομάδας; (τα μέλη δεν διαγράφονται)', {danger: true, ok: I.trash + ' Διαγραφή'}))) return;
      await api('del_team', {id: +b.dataset.tdel}); toast('Διαγράφηκε'); R.teams();
    });
  }
};

/* ═════════ PROJECTS (PORTFOLIO) ═════════ */
R.projects = async function () {
  setTop('Projects', 'Portfolio — κατάσταση, υγεία, πρόοδος');
  const c = $('#content');
  c.innerHTML = skel(1, 340);
  const d = await api('portfolio');
  const roots = d.projects.filter(p => !p.parent && p.kind !== 'client');
  const clientPjs = d.projects.filter(p => p.kind === 'client');
  const kids = pid => d.projects.filter(p => p.parent === pid && p.kind !== 'client');
  const hC = {green: 'var(--ok)', yellow: 'var(--warn)', red: 'var(--bad)'};
  const psL = {new: 'Νέο', active: 'Σε εξέλιξη', hold: 'Σε αναμονή', done: 'Ολοκληρωμένο'};
  const burnBar = p => {
    if (!p.estHours) return p.spentMins ? `<small class="mut">${fmtMin(p.spentMins)}</small>` : '—';
    const pctB = Math.round(p.spentMins / (p.estHours * 60) * 100);
    return `<div class="bar"><span class="${pctB > 100 ? '' : 'ok'}" style="width:${Math.min(100, pctB)}%;${pctB > 100 ? 'background:var(--bad)' : ''}"></span></div>
      <small class="${pctB > 100 ? '' : 'mut'}" style="${pctB > 100 ? 'color:var(--bad);font-weight:700' : ''}">${fmtMin(p.spentMins)} / ${p.estHours}ω (${pctB}%)</small>`;
  };
  const cRow = p => `<tr class="${p.archived ? 'mut' : ''}">
    <td><span class="dot" style="background:${p.health ? hC[p.health] : p.color};width:11px;height:11px;margin-right:8px"></span>
      <a href="#/board/${p.id}" style="font-weight:700">${esc(p.name)}</a>
      ${p.offerId ? `<span class="pill pill-mut" title="Από προσφορά">${I.briefcase} </span>` : ''}</td>
    <td>${esc(p.clientName || '—')}</td>
    <td>${p.pstatus ? `<span class="pill pill-info">${psL[p.pstatus]}</span>` : '—'}</td>
    <td class="${p.due && p.due < today() && p.pstatus !== 'done' ? 'pill pill-bad' : ''}">${p.due ? dShort(p.due) : '—'}</td>
    <td>${p.budget ? fmtEur(p.budget) : '—'}</td>
    <td>${p.todos ? `<span class="pill ${p.todos[0] === p.todos[1] ? 'pill-ok' : 'pill-info'}">☑ ${p.todos[0]}/${p.todos[1]}</span>` : '—'}</td>
    <td style="min-width:130px">${burnBar(p)}</td>
    <td style="min-width:110px"><div class="bar"><span class="ok" style="width:${p.pct}%"></span></div>
      <small class="mut">${p.done}/${p.total}</small></td>
    ${d.canManage ? `<td><button class="btn btn-sm btn-o" data-edit="${p.id}">${I.edit} </button>
      <button class="btn btn-sm btn-o" data-arch="${p.id}">${p.archived ? '↩' : (I.box)}</button></td>` : ''}</tr>`;
  const row = (p, depth) => `<tr class="${p.archived ? 'mut' : ''}">
    <td style="padding-left:${14 + depth * 24}px">
      <span class="dot" style="background:${p.health ? hC[p.health] : p.color};width:11px;height:11px;margin-right:8px"></span>
      <a href="#/board/${p.id}" style="font-weight:700">${depth ? '↳ ' : ''}${esc(p.name)}</a></td>
    <td>${esc(p.clientName || '—')}</td>
    <td>${p.pstatus ? `<span class="pill pill-info">${psL[p.pstatus]}</span>` : '—'}</td>
    <td>${p.total - p.done}</td>
    <td style="min-width:130px"><div class="bar"><span class="ok" style="width:${p.pct}%"></span></div>
      <small class="mut">${p.done}/${p.total} (${p.pct}%)</small></td>
    <td>${p.trend === null ? '—' : p.trend > 0 ? `<b style="color:var(--bad)">▲ +${p.trend}</b>` : p.trend < 0 ? `<b style="color:var(--ok)">▼ ${p.trend}</b>` : '='}</td>
    ${d.canManage ? `<td><button class="btn btn-sm btn-o" data-edit="${p.id}">${I.edit} </button>
      <button class="btn btn-sm btn-o" data-arch="${p.id}">${p.archived ? '↩' : (I.box)}</button></td>` : ''}</tr>`;
  /* ── Κινητό: κάρτες αντί για πίνακες 8-9 στηλών (που γίνονταν οριζόντιο scroll) ── */
  const MOB = matchMedia('(max-width:768px)').matches;
  const st = R.projects._st = R.projects._st || {q: '', closed: {}};
  const norm = s => String(s || '').toLowerCase()
    .replace(/ά/g, 'α').replace(/έ/g, 'ε').replace(/ή/g, 'η').replace(/[ίϊΐ]/g, 'ι')
    .replace(/ό/g, 'ο').replace(/[ύϋΰ]/g, 'υ').replace(/ώ/g, 'ω').replace(/ς/g, 'σ');
  const hit = p => !st.q || norm([p.name, p.clientName, psL[p.pstatus] || ''].join(' ')).includes(norm(st.q));

  const pjCard = (p, depth) => `<div class="pj-card${p.archived ? ' mut' : ''}">
    <div class="pj-top">
      <span class="kb-dot" style="background:${p.health ? hC[p.health] : p.color};width:10px;height:10px"
        title="Υγεία: ${p.health || '—'}"></span>
      <a href="#/board/${p.id}" class="pj-name">${depth ? '↳ ' : ''}${esc(p.name)}</a>
      ${p.pstatus ? `<span class="kb-tag" style="background:#0090dd18;color:#0374b0">${psL[p.pstatus]}</span>` : ''}
      ${p.offerId ? `<span class="kb-tag kb-tag-mut" title="Από προσφορά">${I.briefcase}</span>` : ''}
    </div>
    <div class="pj-meta">
      ${p.clientName ? `<span>${I.user} ${esc(p.clientName)}</span>` : ''}
      ${p.due ? `<span class="${p.due < today() && p.pstatus !== 'done' ? 'pill pill-bad' : ''}">${I.cal} ${dShort(p.due)}</span>` : ''}
      ${p.budget ? `<span>${fmtEur(p.budget)}</span>` : ''}
      ${p.todos ? `<span class="pill ${p.todos[0] === p.todos[1] ? 'pill-ok' : 'pill-info'}">☑ ${p.todos[0]}/${p.todos[1]}</span>` : ''}
      ${p.estHours ? `<span>${fmtMin(p.spentMins)} / ${p.estHours}ω</span>` : (p.spentMins ? `<span>${fmtMin(p.spentMins)}</span>` : '')}
      ${p.trend === null || p.trend === undefined ? '' : p.trend > 0 ? `<span style="color:var(--bad);font-weight:700">▲ +${p.trend}</span>`
        : p.trend < 0 ? `<span style="color:var(--ok);font-weight:700">▼ ${p.trend}</span>` : ''}
    </div>
    <div class="pj-prog"><div class="bar"><span class="ok" style="width:${p.pct}%"></span></div>
      <small class="mut">${p.done}/${p.total} (${p.pct}%)</small></div>
    ${d.canManage ? `<div class="pj-acts">
      <button class="btn btn-sm btn-o" data-edit="${p.id}">${I.edit} Επεξεργασία</button>
      <button class="btn btn-sm btn-o" data-arch="${p.id}">${p.archived ? '↩ Επαναφορά' : I.box + ' Αρχειοθέτηση'}</button></div>` : ''}
  </div>`;

  const group = (key, icon, title, sub, cards, emptyTxt) => `
    <div class="card kb-group">
      <div class="card-h kb-ghead" data-pgrp="${key}">
        <span class="kb-gbar" style="background:${key === 'client' ? '#7b5cd6' : '#0090dd'}"></span>
        ${icon} ${title} <span class="kb-n">${cards.length}</span>
        <span style="flex:1"></span><span class="kb-gchev ${st.closed[key] ? '' : 'open'}">${I.chev}</span></div>
      <div class="card-b kb-gbody" ${st.closed[key] ? 'style="display:none"' : ''}>
        ${sub ? `<div class="mut" style="font-size:11.5px;margin:-4px 0 10px">${sub}</div>` : ''}
        ${cards.length ? cards.join('') : `<div class="mut" style="font-size:12.5px;padding:4px 2px">${emptyTxt}</div>`}
      </div></div>`;

  /* Ένας πελάτης → τα έργα του. Η ιεραρχία φαίνεται στον ίδιο πίνακα, με
     γραμμή-κεφαλίδα ανά πελάτη που οδηγεί στην καρτέλα του. */
  const byClient = list => {
    const m = new Map();
    list.forEach(p => {
      const k = p.client || 0;
      if (!m.has(k)) m.set(k, {name: p.clientName || '— χωρίς πελάτη —', items: []});
      m.get(k).items.push(p);
    });
    return [...m.entries()].sort((a, b) => a[1].name.localeCompare(b[1].name, 'el'))
      .map(([cid, g]) => {
        const tot = g.items.reduce((a, x) => a + x.total, 0);
        const dn = g.items.reduce((a, x) => a + x.done, 0);
        return `<tr class="pj-cgrp"><td colspan="${d.canManage ? 9 : 8}">
          ${cid ? `<a href="#/client360/${cid}">${I.user} ${esc(g.name)}</a>` : `<span>${esc(g.name)}</span>`}
          <span class="kb-n">${g.items.length} έργα</span>
          <span class="mut" style="font-weight:400;font-size:11.5px">· ${dn}/${tot} εργασίες</span></td></tr>`
          + g.items.map(cRow).join('');
      }).join('');
  };
  const cliList = clientPjs.filter(hit);
  const opsList = [];
  roots.filter(hit).forEach(p => { opsList.push(pjCard(p, 0)); kids(p.id).filter(hit).forEach(k => opsList.push(pjCard(k, 1))); });

  c.innerHTML = `
  <div class="card kb-search">
    <div class="kb-srow">
      <div class="kb-sinput"><span class="kb-sico">${I.search}</span>
        <input class="inp" id="prQ" placeholder="Ψάξε έργο — όνομα, πελάτη, κατάσταση…" value="${esc(st.q)}"></div>
      ${d.canManage ? `<button class="btn btn-o btn-sm" id="prRec">${I.repeat} Επαναλαμβανόμενα</button>
        <button class="btn btn-p btn-sm" id="prNew">${I.plus} Νέο project</button>` : ''}
    </div>
  </div>
  ${MOB
    ? group('client', I.rocket, 'Έργα πελατών', 'με budget, εκτίμηση & deadline', cliList.map(p => pjCard(p, 0)),
        'Κανένα έργο πελάτη — φτιάξε ένα με «Νέο project» ή από κερδισμένη προσφορά.')
      + group('ops', I.building, 'Λειτουργικά projects', 'τμήματα & καθημερινή λειτουργία (tickets)', opsList,
        'Κανένα λειτουργικό project.')
    : `<div class="card"><div class="card-h">${I.rocket} Έργα πελατών <span class="mut" style="font-weight:400;font-size:11.5px">ένας πελάτης → τα έργα του → οι εργασίες τους στις ομάδες</span></div>
    <table class="tbl"><thead><tr>
    <th>Έργο</th><th>Πελάτης</th><th>Κατάσταση</th><th>Deadline</th><th>Budget</th><th>Παραδοτέα</th><th>Χρόνος / εκτίμηση</th><th>Πρόοδος</th>${d.canManage ? '<th></th>' : ''}</tr></thead>
    <tbody>${cliList.length ? byClient(cliList) : `<tr><td colspan="9" class="empty">Κανένα έργο πελάτη — φτιάξε ένα με «Νέο project» ή από κερδισμένη προσφορά 💼</td></tr>`}</tbody></table></div>
  <div class="card"><div class="card-h">${I.building} Λειτουργικά projects <span class="mut" style="font-weight:400;font-size:11.5px">τμήματα & καθημερινή λειτουργία (tickets)</span></div>
    <table class="tbl"><thead><tr>
    <th>Project</th><th>Πελάτης</th><th>Κατάσταση</th><th>Ανοιχτά</th><th>Πρόοδος</th><th>Τάση 7ημ</th>${d.canManage ? '<th></th>' : ''}</tr></thead>
    <tbody>${roots.filter(hit).map(p => row(p, 0) + kids(p.id).filter(hit).map(k => row(k, 1)).join('')).join('')}</tbody></table></div>`}
  <div id="prExtra"></div>`;
  let pqt;
  $('#prQ').oninput = () => { clearTimeout(pqt); pqt = setTimeout(() => { st.q = $('#prQ').value.trim(); R.projects(); }, 300); };
  $$('.kb-ghead[data-pgrp]').forEach(h => h.onclick = e => {
    if (e.target.closest('a,button')) { return; }
    const k = h.dataset.pgrp; st.closed[k] = !st.closed[k];
    h.nextElementSibling.style.display = st.closed[k] ? 'none' : '';
    h.querySelector('.kb-gchev').classList.toggle('open', !st.closed[k]);
  });
  if (!d.canManage) return;
  const openProj = p => {
    closeDrawer();
    p = p || {visible: true, members: [], teams: []};
    const ovl = document.createElement('div'); ovl.className = 'ovl';   // κλικ έξω ΔΕΝ κλείνει
    const dr = document.createElement('div'); dr.className = 'drawer';
    dr.innerHTML = `
    <div class="drawer-h"><h2>${p.id ? esc(p.name) : 'Νέο project'}</h2><button class="drawer-x" id="dX">✕</button></div>
    <div class="drawer-b"><div class="card"><div class="card-b">
      <label class="lbl">Όνομα</label><input class="inp" id="pjName" value="${esc(p.name || '')}">
      <div class="frow" style="margin-top:11px">
        <div><label class="lbl">Πελάτης <span class="mut" style="font-weight:400">— γράψε και <b>διάλεξε</b> από τη λίστα</span></label>
          <input class="inp" id="pjCli" list="pjCliL" autocomplete="off" placeholder="όνομα, επωνυμία ή email…"
          value="${esc(p.clientName ? p.clientName + ' (#' + p.client + ')' : '')}"><datalist id="pjCliL"></datalist>
          <input type="hidden" id="pjCliId" value="${p.client || ''}">
          <div id="pjCliS" class="mut" style="font-size:11px;margin-top:3px"></div></div>
        <div><label class="lbl">Υπο-έργο του</label><select class="inp" id="pjPar"><option value="">— αυτοτελές έργο —</option>
          ${clientPjs.filter(r => r.id !== p.id).map(r => `<option value="${r.id}" ${r.id === p.parent ? 'selected' : ''}>${esc(r.name)}${r.clientName ? ' — ' + esc(r.clientName) : ''}</option>`).join('')}</select></div>
        <div><label class="lbl">Χρώμα</label><input class="inp" type="color" id="pjColor" value="${p.color || '#0090dd'}" style="height:40px;padding:4px"></div>
        <div><label class="lbl">Κατάσταση</label><select class="inp" id="pjPs"><option value="">—</option>
          ${Object.entries(psL).map(([k, v]) => `<option value="${k}" ${k === p.pstatus ? 'selected' : ''}>${v}</option>`).join('')}</select></div>
        <div><label class="lbl">Υγεία</label><select class="inp" id="pjH"><option value="">—</option>
          <option value="green" ${p.health === 'green' ? 'selected' : ''}>🟢 Καλά</option>
          <option value="yellow" ${p.health === 'yellow' ? 'selected' : ''}>🟡 Προσοχή</option>
          <option value="red" ${p.health === 'red' ? 'selected' : ''}>🔴 Πρόβλημα</option></select></div>
        <div><label class="lbl">Τύπος</label><select class="inp" id="pjKind">
          <option value="client" ${p.kind !== 'dept' ? 'selected' : ''}>Έργο πελάτη</option>
          <option value="dept" ${p.kind === 'dept' ? 'selected' : ''}>Ουρά ομάδας (παλαιό)</option></select></div>
        ${p.kind === 'dept' ? `<div><label class="lbl">Τροφοδοτείται από</label><select class="inp" id="pjDept"><option value="">—</option>
          ${d.depts.map(dp => `<option value="${dp.id}" ${dp.id === p.dept ? 'selected' : ''}>${esc(dp.name)}</option>`).join('')}</select>
          <div class="mut" style="font-size:11px;margin-top:3px">Τα tickets αυτής της ομάδας γίνονται εργασίες εδώ.</div></div>` : ''}
        <div><label class="lbl">${I.coin} Budget €</label><input class="inp" id="pjBud" value="${p.budget ?? ''}" placeholder="π.χ. 3000"></div>
        <div><label class="lbl">⏱ Εκτίμηση ωρών</label><input class="inp" id="pjEst" value="${p.estHours ?? ''}" placeholder="π.χ. 40"></div>
        <div><label class="lbl">Έναρξη</label><input type="date" class="inp" id="pjStart" value="${p.start || ''}"></div>
        <div><label class="lbl">Deadline</label><input type="date" class="inp" id="pjDue" value="${p.due || ''}"></div>
        <div><label class="lbl">Υπεύθυνος έργου <span class="mut" style="font-weight:400">— σε αυτόν κλιμακώνουν οι χαμένες προθεσμίες</span></label>
          <select class="inp" id="pjMgr"><option value="">— κανείς —</option>
          ${S.boot.admins.map(a => `<option value="${a.id}" ${a.id === p.manager ? 'selected' : ''}>${esc(a.name)}</option>`).join('')}</select></div>
      </div>
      ${p.id && p.kind === 'client' ? (() => {
        const cost = +S.boot.costPerHour || 0;
        const spentCost = cost ? p.spentMins / 60 * cost : null;
        return `<div style="margin-top:12px;padding:12px 15px;border-radius:11px;background:var(--line)">
        <b style="font-size:12.5px;color:var(--ink)">${I.chart} Πορεία έργου</b>
        <div class="mut" style="font-size:12px;margin-top:5px;line-height:1.9">
          Tasks: <b>${p.done}/${p.total}</b> (${p.pct}%) · Χρόνος: <b>${fmtMin(p.spentMins)}</b>${p.estHours ? ` από εκτίμηση <b>${p.estHours}ω</b>` : ''}<br>
          ${p.budget ? `Budget: <b>${fmtEur(p.budget)}</b>` : ''}${spentCost !== null && p.budget ? ` · Κόστος ως τώρα: <b style="${spentCost > p.budget ? 'color:var(--bad)' : ''}">${fmtEur(spentCost)}</b> · Περιθώριο: <b style="${p.budget - spentCost < 0 ? 'color:var(--bad)' : 'color:var(--ok)'}">${fmtEur(p.budget - spentCost)}</b>` : ''}
        </div></div>`;
      })() : ''}
      <label style="display:flex;gap:6px;align-items:center;margin-top:11px;font-size:13px">
        <input type="checkbox" id="pjVis" ${p.visible ? 'checked' : ''}> Ορατό στον πελάτη (portal)</label>
      <label class="lbl" style="margin-top:12px">Μέλη (agents με πρόσβαση)</label>
      <div style="display:flex;gap:10px;flex-wrap:wrap">${S.boot.admins.filter(a => !a.full).map(a =>
        `<label style="font-size:12.5px;display:flex;gap:4px"><input type="checkbox" class="pjM" value="${a.id}"
          ${p.members.includes(a.id) ? 'checked' : ''}> ${esc(a.name)}</label>`).join('') || '<span class="mut">όλοι είναι διαχειριστές</span>'}</div>
      <label class="lbl" style="margin-top:10px">Ομάδες με πρόσβαση</label>
      <div style="display:flex;gap:10px;flex-wrap:wrap">${d.teams.map(t =>
        `<label style="font-size:12.5px;display:flex;gap:4px"><input type="checkbox" class="pjT" value="${t.id}"
          ${p.teams.includes(t.id) ? 'checked' : ''}> <span class="dot" style="background:${t.color}"></span>${esc(t.name)}</label>`).join('') || '<span class="mut">—</span>'}</div>
      <div style="margin-top:14px;display:flex;gap:9px;align-items:center">
        <button class="btn btn-p" id="pjSave">Αποθήκευση</button>
        ${p.id ? `<button class="btn btn-o" id="pjArch">${p.archived ? '↩ Επαναφορά' : I.box + ' Αρχειοθέτηση'}</button>
          <button class="btn btn-o" id="pjDel" style="color:var(--bad);margin-left:auto">${I.trash} Διαγραφή έργου</button>` : ''}</div>
    </div></div>
    ${p.id ? `<div class="card"><div class="card-h">${I.clipboard} Τι περιλαμβάνει το έργο — παραδοτέα</div>
      <div class="card-b" id="pjTodos"><div class="skel" style="height:50px"></div></div></div>` : ''}
    ${p.id ? `<div class="card"><div class="card-h">${I.link} Δημόσιο link για τον πελάτη</div>
      <div class="card-b" id="pjShare"><div class="skel" style="height:50px"></div></div></div>` : ''}</div>`;
    document.body.append(ovl, dr);
    requestAnimationFrame(() => { ovl.classList.add('show'); dr.classList.add('show'); });
    $('#dX').onclick = () => cnpAskClose(dr);
    clientAuto('pjCli', 'pjCliL', 'pjCliId', 'pjCliS');
    const loadTodos = async () => {
      const box = $('#pjTodos', dr); if (!box) return;
      const dd = await api('ptodos&project=' + p.id);
      const done = dd.todos.filter(x => x.done).length;
      box.innerHTML = `
        ${dd.todos.length ? `<div class="bar" style="margin-bottom:11px"><span class="ok" style="width:${Math.round(done / dd.todos.length * 100)}%"></span></div>` : ''}
        ${dd.todos.map(x => `<div class="set-row" style="gap:9px">
          <input type="checkbox" data-tdid="${x.id}" ${x.done ? 'checked' : ''} style="width:17px;height:17px;cursor:pointer">
          <div style="flex:1;min-width:0;${x.done ? 'text-decoration:line-through;opacity:.55' : ''}">${esc(x.title)}
            ${x.done ? `<div class="mut" style="font-size:10.5px">✓ ${esc(x.doneBy || '')} · ${dShort(x.doneAt)}</div>` : ''}</div>
          <button class="btn btn-sm btn-o" data-tddel="${x.id}">✕</button></div>`).join('')
          || '<div class="empty" style="padding:14px">Γράψε τι περιλαμβάνει το έργο — ένα παραδοτέο τη φορά</div>'}
        <div style="display:flex;gap:7px;margin-top:10px">
          <input class="inp" id="tdNew" placeholder="π.χ. Στήσιμο eshop + σύνδεση Soft1 (Enter)" style="flex:1">
          <button class="btn btn-p btn-sm" id="tdAdd">+</button></div>`;
      $$('[data-tdid]', box).forEach(c => c.onchange = async () => {
        await api('ptodo_toggle', {id: +c.dataset.tdid}); loadTodos();
      });
      $$('[data-tddel]', box).forEach(b => b.onclick = async () => {
        if (!(await cnpConfirm('Διαγραφή παραδοτέου;', {danger: true, ok: I.trash + ' Διαγραφή'}))) return;
        await api('ptodo_del', {id: +b.dataset.tddel}); loadTodos();
      });
      const add = async () => {
        const v = $('#tdNew', box).value.trim(); if (!v) return;
        await api('ptodo_add', {project: p.id, title: v});
        loadTodos();
      };
      $('#tdAdd', box).onclick = add;
      $('#tdNew', box).onkeydown = e => { if (e.key === 'Enter') add(); };
    };
    loadTodos();
    const loadShare = async () => {
      const box = $('#pjShare', dr); if (!box) return;
      const s = await api('share_info&project=' + p.id).catch(() => null);
      if (!s) { box.innerHTML = '<div class="mut" style="font-size:12.5px">Μη διαθέσιμο</div>'; return; }
      if (!s.exists || s.revoked) {
        box.innerHTML = `<div class="mut" style="font-size:12.5px;margin-bottom:10px">
          Δημιούργησε έναν σύνδεσμο που δείχνει στον πελάτη την πρόοδο του έργου — <b>χωρίς κωδικούς</b>, μόνο για ανάγνωση.</div>
          <label class="lbl">Λήξη (προαιρετικά — κενό = μέχρι το κλείσιμο)</label>
          <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <input type="date" class="inp" id="shExp" style="width:auto">
            <label style="font-size:12.5px;display:flex;gap:5px;align-items:center"><input type="checkbox" id="shCm"> Να μπορεί να στέλνει μηνύματα</label>
            <button class="btn btn-p btn-sm" id="shCreate">${I.link} Δημιουργία link</button></div>`;
        $('#shCreate', box).onclick = async () => {
          const r = await api('share_save', {project: p.id, expires_at: $('#shExp', box).value || '', can_comment: $('#shCm', box).checked});
          if (r && r.url) { toast('Ο σύνδεσμος δημιουργήθηκε'); loadShare(); }
        };
        return;
      }
      const dShort2 = s.last_view ? dShort(s.last_view) : null;
      box.innerHTML = `
        <div style="display:flex;gap:7px;margin-bottom:8px">
          <input class="inp" id="shUrl" readonly value="${esc(s.url)}" style="flex:1;font-size:12px;background:var(--line)">
          <button class="btn btn-p btn-sm" id="shCopy">${I.clipboard} Αντιγραφή</button></div>
        <div class="mut" style="font-size:12px;line-height:1.9;margin-bottom:10px">
          ${I.eye} <b>${s.views}</b> προβολές${dShort2 ? ` · τελευταία: ${dShort2}` : ' · καμία ακόμη'}
          ${s.expires_at ? ` · λήγει <b>${s.expires_at}</b>` : ' · <b>χωρίς λήξη</b> (μέχρι το κλείσιμο)'}
          ${s.can_comment ? ' · ' + I.chat + ' μηνύματα: <b>ναι</b>' : ' · ' + I.chat + ' μηνύματα: όχι'}</div>
        ${(s.comments && s.comments.length) ? `<div style="border-top:1px solid var(--line);padding-top:9px;margin-bottom:9px">
          <b style="font-size:12px">${I.chat} Μηνύματα πελάτη</b>
          ${s.comments.map(c => `<div style="font-size:12.5px;margin-top:6px;padding:7px 10px;border-radius:9px;background:${c.team ? '#eef7ff' : 'var(--line)'}">
            <b>${c.team ? '🛟 ' : ''}${esc(c.author)}</b> <span class="mut" style="font-size:10.5px">${dShort(c.at)}</span><br>${esc(c.body)}</div>`).join('')}
          <div style="display:flex;gap:7px;margin-top:8px">
            <input class="inp" id="shReply" placeholder="Απάντηση στον πελάτη…" style="flex:1;font-size:12.5px">
            <button class="btn btn-p btn-sm" id="shReplyBtn">↩</button></div></div>` : ''}
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;border-top:1px solid var(--line);padding-top:9px">
          <label class="lbl" style="margin:0">Λήξη</label><input type="date" class="inp" id="shExp" value="${s.expires_at || ''}" style="width:auto">
          <label style="font-size:12.5px;display:flex;gap:5px;align-items:center"><input type="checkbox" id="shCm" ${s.can_comment ? 'checked' : ''}> μηνύματα</label>
          <button class="btn btn-o btn-sm" id="shUpd">Ενημέρωση</button>
          <button class="btn btn-o btn-sm" id="shRot">${I.repeat} Νέο token</button>
          <button class="btn btn-o btn-sm" id="shRev" style="color:var(--bad);margin-left:auto">Ανάκληση</button></div>`;
      $('#shCopy', box).onclick = () => {
        const i = $('#shUrl', box); i.select(); navigator.clipboard ? navigator.clipboard.writeText(i.value).then(() => toast('Αντιγράφηκε')) : document.execCommand('copy');
      };
      $('#shUpd', box).onclick = async () => {
        await api('share_save', {project: p.id, expires_at: $('#shExp', box).value || '', can_comment: $('#shCm', box).checked});
        toast('Ενημερώθηκε'); loadShare();
      };
      $('#shRot', box).onclick = async () => {
        if (!(await cnpConfirm('Νέο token; Ο παλιός σύνδεσμος θα πάψει να ισχύει.', {ok: I.repeat + ' Νέο token'}))) return;
        await api('share_save', {project: p.id, expires_at: $('#shExp', box).value || '', can_comment: $('#shCm', box).checked, rotate: 1});
        toast('Νέος σύνδεσμος'); loadShare();
      };
      $('#shRev', box).onclick = async () => {
        if (!(await cnpConfirm('Ανάκληση του συνδέσμου; Ο πελάτης δεν θα έχει πλέον πρόσβαση.', {danger: true, ok: 'Ανάκληση'}))) return;
        await api('share_revoke', {project: p.id}); toast('Ανακλήθηκε'); loadShare();
      };
      const rb = $('#shReplyBtn', box);
      if (rb) {
        rb.onclick = async () => {
          const v = $('#shReply', box).value.trim(); if (!v) return;
          await api('share_reply', {project: p.id, body: v}); loadShare();
        };
      }
    };
    loadShare();
    const arb = $('#pjArch', dr);
    if (arb) arb.onclick = async () => {
      await api('archive_project', {id: p.id});
      toast(p.archived ? 'Επανήλθε' : 'Αρχειοθετήθηκε'); closeDrawer(); R.projects();
    };
    /* Η διαγραφή παίρνει μαζί εργασίες, χρόνο, σχόλια και ιστορικό. Δεύτερη
       επιβεβαίωση όταν υπάρχουν εργασίες — το API αρνείται χωρίς αυτήν. */
    const dlb = $('#pjDel', dr);
    if (dlb) dlb.onclick = async () => {
      const n = p.total || 0;
      const msg = n
        ? `Διαγραφή του «${p.name}»; Θα σβηστούν και οι ${n} εργασίες του μαζί με τον χρόνο, τα σχόλια και το ιστορικό τους. Δεν αναιρείται.`
        : `Διαγραφή του «${p.name}»; Δεν αναιρείται.`;
      if (!(await cnpConfirm(msg, {danger: true, ok: I.trash + ' Διαγραφή'}))) return;
      if (n && !(await cnpConfirm(`Επιβεβαίωσε ξανά: ${n} εργασίες θα χαθούν οριστικά.`,
        {danger: true, ok: 'Ναι, διάγραψέ το'}))) return;
      await api('project_delete', {id: p.id, withTasks: n ? 1 : 0})
        .then(async r => { toast(`Διαγράφηκε${r.tasks ? ' μαζί με ' + r.tasks + ' εργασίες' : ''}`);
          closeDrawer(); S.boot = await api('boot'); R.projects(); })
        .catch(e => toast(e.message, true));
    };
    $('#pjSave', dr).onclick = async () => {
      const kind = $('#pjKind').value, cid = +$('#pjCliId').value || 0;
      /* Έργο πελάτη χωρίς πελάτη είναι αόρατο: δεν βγαίνει στην καρτέλα του,
         δεν χρεώνεται πουθενά. Το μπλοκάρουμε αντί να αποθηκευτεί μισό. */
      if (kind === 'client' && !cid) {
        toast('Διάλεξε πελάτη από τη λίστα — δεν αρκεί να γράψεις το όνομα', true);
        $('#pjCli').focus(); return;
      }
      await api('save_project', {id: p.id || 0, name: $('#pjName').value, client: cid,
        // Έργο πελάτη δεν ανήκει σε ομάδα — το department κρέμεται στις εργασίες.
        dept: kind === 'client' ? 0 : ($('#pjDept') ? +$('#pjDept').value || 0 : (p.dept || 0)),
        parent: +$('#pjPar').value || 0, color: $('#pjColor').value,
        pstatus: $('#pjPs').value, health: $('#pjH').value, visible: $('#pjVis').checked,
        kind: $('#pjKind').value, budget: $('#pjBud').value.trim(), estHours: $('#pjEst').value.trim(),
        start: $('#pjStart').value || null, due: $('#pjDue').value || null,
        manager: +$('#pjMgr').value || 0,
        members: $$('.pjM:checked', dr).map(x => +x.value), teams: $$('.pjT:checked', dr).map(x => +x.value)});
      toast('Αποθηκεύτηκε'); closeDrawer();
      const b = await api('boot'); S.boot = b; R.projects();
    };
  };
  $('#prNew').onclick = () => openProj(null);
  /* Έρχεσαι από την καρτέλα πελάτη με «Νέο έργο» — ο πελάτης είναι ήδη γνωστός. */
  if (R.projects._pre) {
    const pre = R.projects._pre; R.projects._pre = null;
    openProj({visible: true, members: [], teams: [], kind: 'client',
      client: pre.client, clientName: pre.clientName});
  }
  $$('[data-edit]').forEach(b => b.onclick = () => openProj(d.projects.find(p => p.id === +b.dataset.edit)));
  $$('[data-arch]').forEach(b => b.onclick = async () => {
    await api('archive_project', {id: +b.dataset.arch}); R.projects();
  });
  $('#prRec').onclick = async () => {
    const rec = await api('recurring');
    const freqL = {daily: 'ημέρες', weekly: 'εβδομάδες', monthly: 'μήνες', yearly: 'έτη'};
    $('#prExtra').innerHTML = `<div class="card"><div class="card-h">${I.repeat} Επαναλαμβανόμενα tasks (συντηρήσεις)</div>
      <div class="card-b" style="display:flex;gap:8px;flex-wrap:wrap;border-bottom:1px solid var(--line)">
        <input class="inp" id="rcT" placeholder="Τίτλος" style="flex:1;min-width:150px">
        <select class="inp" id="rcP" style="width:auto">${S.boot.projects.map(p => `<option value="${p.id}">${esc(p.name)}</option>`).join('')}</select>
        κάθε <input class="inp" id="rcE" type="number" value="1" min="1" style="width:60px">
        <select class="inp" id="rcF" style="width:auto">${Object.entries(freqL).map(([k, v]) => `<option value="${k}" ${k === 'monthly' ? 'selected' : ''}>${v}</option>`).join('')}</select>
        από <input class="inp" id="rcN" type="date" value="${today()}" style="width:auto">
        <select class="inp" id="rcA" style="width:auto"><option value="">— χειριστής —</option>
          ${S.boot.admins.map(a => `<option value="${a.id}">${esc(a.name)}</option>`).join('')}</select>
        <button class="btn btn-p btn-sm" id="rcGo">Προσθήκη</button></div>
      ${rec.recurring.map(r => `<div class="trow" style="cursor:default">
        <span class="dot" style="background:${r.pcolor}"></span>
        <div style="flex:1"><b>${esc(r.title)}</b> <span class="mut">(${esc(r.pname)})</span>
          <div class="mut" style="font-size:11px">κάθε ${r.every} ${freqL[r.freq]} · επόμενο: ${dShort(r.next)} · ${r.assignee ? esc(adminName(r.assignee)) : '—'}</div></div>
        ${r.active ? '<span class="pill pill-ok">Ενεργό</span>' : '<span class="pill pill-mut">Ανενεργό</span>'}
        <button class="btn btn-sm btn-o" data-rcDel="${r.id}">✕</button></div>`).join('') || '<div class="empty" style="padding:18px">Κανένα πρόγραμμα</div>'}
    </div>`;
    $('#rcGo').onclick = async () => {
      if (!$('#rcT').value.trim()) return;
      await api('save_recurring', {id: 0, title: $('#rcT').value.trim(), project: +$('#rcP').value,
        every: +$('#rcE').value, freq: $('#rcF').value, next: $('#rcN').value,
        assignee: +$('#rcA').value || 0, dueDays: 3, active: true});
      toast('Προστέθηκε'); $('#prRec').click();
    };
    $$('[data-rcDel]').forEach(b => b.onclick = async () => {
      await api('del_recurring', {id: +b.dataset.rcDel}); $('#prRec').click();
    });
  };
};

/* ═════════ CRM ΕΠΙΣΚΟΠΗΣΗ (pipeline analytics) ═════════ */
R.crmov = async function () {
  setTop('CRM', 'Επισκόπηση pipeline — αριθμοί & εκκρεμότητες');
  const c = $('#content');
  c.innerHTML = crmTabs('crmov') + skel(4);
  const d = await api('crm_overview');
  const mt = await api('my_crm_tasks').catch(() => ({tasks: []}));
  const hl = await api('hot_leads').catch(() => ({leads: [], hot: 0, warm: 0, cold: 0}));
  const tempCol = {hot: '#e0552b', warm: '#e0a020', cold: '#0097e4'};
  const maxC = Math.max(1, ...d.pipe.map(p => p.count));
  const leadRow = l => `<div class="set-row" data-lgo="${l.id}" style="cursor:pointer">
    <div style="flex:1;min-width:0"><b style="font-size:12.5px">${esc(l.name)}</b>
      <span class="mut" style="font-size:11px">${l.assignee ? '· ' + esc(adminName(l.assignee)) : ''}</span></div>
    ${l.value ? `<span class="pill pill-ok">${fmtEur(l.value)}</span>` : ''}
    ${l.next ? `<span class="pill pill-bad">${I.bell} ${dShort(l.next)}</span>` : `<span class="pill pill-warn">${I.snow} χωρίς ενέργεια</span>`}</div>`;
  const pctT = d.target > 0 ? Math.min(100, Math.round(d.wonValueMonth / d.target * 100)) : 0;
  c.innerHTML = crmTabs('crmov') + `
  <div class="grid g4" style="margin-bottom:16px">
    <div class="stat info"><b>${d.openCount}</b><small>Ανοιχτά leads στο pipeline</small></div>
    <div class="stat ok"><b>${fmtEur(d.openValue)}</b><small>Αξία ανοιχτού pipeline</small></div>
    <div class="stat ${d.winRate === null ? 'info' : d.winRate >= 50 ? 'ok' : 'warn'}"><b>${d.winRate === null ? '—' : d.winRate + '%'}</b><small>Win rate (κερδ. / κλεισμένα)</small></div>
    <div class="stat ${pctT >= 100 ? 'ok' : 'info'}"><b>${fmtEur(d.wonValueMonth)}</b>
      <small>Πωλήσεις μήνα${d.target > 0 ? ' · ' + pctT + '% στόχου' : ''}</small></div>
  </div>
  <div class="card" style="margin-bottom:16px"><div class="card-h">${I.checkSquare} Οι εργασίες μου <span class="kb-n" style="margin-left:auto">${mt.tasks.length}</span></div>
    <div class="card-b" style="display:flex;flex-direction:column;gap:2px">
    ${mt.tasks.length ? mt.tasks.map(t => `<div style="display:flex;gap:9px;align-items:center;padding:6px 0;border-bottom:1px dashed var(--line)">
      <input type="checkbox" data-mtog="${t.id}" style="width:17px;height:17px;cursor:pointer">
      <div style="flex:1;min-width:0;cursor:pointer" data-mgo="${t.lead}"><b style="font-size:13px">${esc(t.title)}</b>
        <div class="mut" style="font-size:11px">${esc(t.leadName)}${t.due ? ` · <span style="${t.overdue ? 'color:var(--bad);font-weight:700' : ''}">έως ${dShort(t.due)}</span>` : ''}${t.who ? ' · ' + esc(t.who) : ''}</div></div>
      ${t.overdue ? `<span class="pill pill-bad" style="font-size:9px">εκπρόθεσμο</span>` : ''}</div>`).join('')
      : '<div class="empty" style="padding:18px">Καμία ανοιχτή εργασία 🎉</div>'}
    </div></div>
  <div class="card" style="margin-bottom:16px"><div class="card-h">${I.fire} Θερμότερα leads
    <span class="mut" style="font-weight:400;font-size:11px;margin-left:auto">${hl.hot} θερμά · ${hl.warm} χλιαρά · ${hl.cold} ψυχρά</span></div>
    <div class="card-b" style="display:flex;flex-direction:column;gap:2px">
    ${hl.leads.length ? hl.leads.slice(0, 12).map(l => `<div class="set-row" data-lgo="${l.id}" style="cursor:pointer">
      <span style="width:34px;height:24px;flex:none;border-radius:6px;display:inline-flex;align-items:center;justify-content:center;font-weight:800;font-size:12px;color:#fff;background:${tempCol[l.temp]}">${l.score}</span>
      <div style="flex:1;min-width:0"><b style="font-size:12.5px">${esc(l.company || l.contact || 'Lead #' + l.id)}</b>
        <span class="mut" style="font-size:11px">· ${esc(l.stageLbl)}${l.assignee ? ' · ' + esc(adminName(l.assignee)) : ''}</span></div>
      ${l.value ? `<span class="pill pill-ok">${fmtEur(l.value)}</span>` : ''}</div>`).join('')
      : '<div class="empty" style="padding:18px">Κανένα ανοιχτό lead</div>'}
    </div></div>
  <div class="card"><div class="card-h">${I.target} Pipeline ανά στάδιο</div><div class="card-b">
    ${d.pipe.map(p => `<div style="display:flex;gap:10px;align-items:center;margin:7px 0">
      <span style="width:120px;font-size:12.5px;font-weight:700;color:var(--ink)">${esc(p.title)}</span>
      <div style="flex:1;background:var(--line);border-radius:7px;height:20px;overflow:hidden">
        <div style="width:${Math.round(p.count / maxC * 100)}%;min-width:${p.count ? 26 : 0}px;height:100%;border-radius:7px;background:${p.color};display:flex;align-items:center;justify-content:flex-end;padding-right:7px;color:#fff;font-size:11px;font-weight:700">${p.count || ''}</div></div>
      <span class="mut" style="width:90px;text-align:right;font-size:12px">${p.value ? fmtEur(p.value) : ''}</span></div>`).join('')}
    <div class="mut" style="font-size:11px;margin-top:6px">Μήνας: ${d.wonMonth} κερδισμένα · ${d.lostMonth} χαμένα</div>
  </div></div>
  <div class="grid g2">
    <div class="card"><div class="card-h">${I.fire} Εκπρόθεσμα follow-ups <span class="kb-n" style="margin-left:auto">${d.overdue.length}</span></div>
      <div class="card-b">${d.overdue.length ? d.overdue.map(leadRow).join('') : '<div class="empty" style="padding:18px">Κανένα 🎉</div>'}</div></div>
    <div class="card"><div class="card-h">${I.snow} Χωρίς επόμενη ενέργεια <span class="kb-n" style="margin-left:auto">${d.rotting.length}</span></div>
      <div class="card-b">${d.rotting.length ? d.rotting.map(leadRow).join('') : '<div class="empty" style="padding:18px">Όλα έχουν πλάνο 👏</div>'}</div></div>
    <div class="card"><div class="card-h">${I.megaphone} Ανοιχτά ανά πηγή</div><div class="card-b">
      ${Object.keys(d.bySource).length ? Object.entries(d.bySource).map(([k, v]) =>
        `<div class="set-row"><b style="flex:1;font-size:12.5px">${esc(k)}</b><span class="kb-n">${v}</span></div>`).join('') : '<div class="empty" style="padding:18px">—</div>'}</div></div>
    <div class="card"><div class="card-h">${I.user} Ανοιχτά ανά χειριστή</div><div class="card-b">
      ${Object.keys(d.byAssignee).length ? Object.entries(d.byAssignee).map(([k, v]) =>
        `<div class="set-row"><b style="flex:1;font-size:12.5px">${+k ? esc(adminName(+k)) : '— χωρίς ανάθεση —'}</b><span class="kb-n">${v}</span></div>`).join('') : '<div class="empty" style="padding:18px">—</div>'}</div></div>
  </div>
  ${d.lostReasons.length ? `<div class="card"><div class="card-h">${I.chat} Πρόσφατες αιτίες απώλειας</div><div class="card-b">
    ${d.lostReasons.map(x => `<div class="set-row"><b style="font-size:12.5px">${esc(x.name)}</b>
      <span class="mut" style="flex:1;font-size:12px">${esc(x.reason)}</span>
      <span class="mut" style="font-size:11px">${dShort(x.at)}</span></div>`).join('')}</div></div>` : ''}`;
  $$('[data-lgo]').forEach(r => r.onclick = async () => {
    const dd = await api('crm');
    const l = dd.leads.find(x => x.id === +r.dataset.lgo);
    if (l) openLead(l, dd);
  });
  $$('[data-mtog]').forEach(ch => ch.onclick = async () => { await api('lead_task_toggle', {id: +ch.dataset.mtog}); R.crmov(); });
  $$('[data-mgo]').forEach(x => x.onclick = async () => {
    const dd = await api('crm'); const l = dd.leads.find(y => y.id === +x.dataset.mgo); if (l) openLead(l, dd);
  });
};

/* ═════════ ΚΑΜΠΑΝΙΕΣ (Phase 6) ═════════ */
R.campaigns = async function () {
  setTop('CRM', 'Καμπάνιες — μέλη & απόδοση');
  const c = $('#content');
  c.innerHTML = crmTabs('campaigns') + skel(1, 200);
  const d = await api('campaigns').catch(() => null);
  if (!d) { c.innerHTML = crmTabs('campaigns') + `<div class="empty"><div class="big">${I.megaphone}</div>Σφάλμα φόρτωσης</div>`; return; }
  const chIco = {email: I.mail, phone: I.phone, event: I.pin, social: I.megaphone, ads: I.megaphone, other: I.tag};
  const stBadge = {draft: ['Πρόχειρη', 'var(--mut)'], active: ['Ενεργή', 'var(--ok)'], done: ['Ολοκληρωμένη', 'var(--brand)']};
  const col = pct => pct === null ? 'var(--mut)' : pct >= 50 ? 'var(--ok)' : pct >= 20 ? 'var(--brand)' : 'var(--warn)';
  const ring = (pct, cl) => {
    const r = 22, circ = 2 * Math.PI * r, off = circ * (1 - (pct || 0) / 100);
    return `<div class="su-ring"><svg width="54" height="54" viewBox="0 0 54 54">
      <circle cx="27" cy="27" r="${r}" fill="none" stroke="var(--line)" stroke-width="6"/>
      <circle cx="27" cy="27" r="${r}" fill="none" stroke="${cl}" stroke-width="6" stroke-linecap="round" stroke-dasharray="${circ}" stroke-dashoffset="${off}"/>
    </svg><div class="v" style="font-size:11px">${pct === null ? '—' : pct + '%'}</div></div>`;
  };
  c.innerHTML = crmTabs('campaigns') + `
  <div style="display:flex;align-items:center;margin-bottom:14px">
    <b style="font-size:15px;color:var(--ink)">${d.campaigns.length} καμπάνιες</b>
    <button class="btn btn-p btn-sm" id="cpAdd" style="margin-left:auto">${I.plus} Νέα καμπάνια</button></div>
  <div class="grid" style="grid-template-columns:repeat(auto-fill,minmax(290px,1fr));gap:12px">
  ${d.campaigns.length ? d.campaigns.map(x => {
    const [sl, scol] = stBadge[x.status] || stBadge.draft;
    const cl = col(x.conv);
    return `<div class="su-proj" data-cpo="${x.id}" style="align-items:center">
      <div class="stripe" style="background:${scol}"></div>
      ${ring(x.conv, cl)}
      <div class="body">
        <div class="title" style="display:flex;align-items:center;gap:6px">${chIco[x.channel] || I.tag} ${esc(x.name)}</div>
        <div class="meta"><span class="pill" style="background:${scol}1a;color:${scol};font-size:9.5px">${sl}</span> · ${esc(x.channelLbl)}</div>
        <div style="margin-top:6px;font-size:12px"><b>${x.members}</b> μέλη · <b style="color:var(--ok)">${x.won}</b> έκλεισαν · ${x.open} ανοιχτά</div>
        <div class="mut" style="font-size:11.5px;margin-top:4px">Έσοδα: <b style="color:var(--ink)">${fmtEur(x.wonValue)}</b>${x.roi !== null ? ' · ROI ' + '<b style="color:' + (x.roi >= 0 ? 'var(--ok)' : 'var(--bad)') + '">' + x.roi + '%</b>' : ''}</div>
      </div></div>`;
  }).join('') : `<div class="empty" style="grid-column:1/-1;padding:34px">Καμία καμπάνια — πάτα «Νέα καμπάνια»</div>`}
  </div>`;
  $('#cpAdd').onclick = () => openCampaign(null, d);
  $$('[data-cpo]').forEach(x => x.onclick = () => openCampaign(+x.dataset.cpo, d));
};

async function openCampaign(id, listD) {
  closeDrawer();
  const isNew = !id;
  const chOpts = Object.entries(listD.channels);
  const ovl = document.createElement('div'); ovl.className = 'ovl';   // κλικ έξω ΔΕΝ κλείνει
  const dr = document.createElement('div'); dr.className = 'drawer';
  let d = {name: '', channel: 'email', status: 'draft', budget: '', goal: '', start: '', end: '', notes: '', members: [], candidates: []};
  if (!isNew) { d = await api('campaign_detail&id=' + id); }
  dr.innerHTML = `
  <div class="drawer-h"><h2>${isNew ? 'Νέα καμπάνια' : esc(d.name)}</h2><button class="drawer-x" id="dX">✕</button></div>
  <div class="drawer-b">
    <div class="card"><div class="card-b">
      <label class="lbl">Όνομα</label><input class="inp" id="cpN" value="${esc(d.name)}">
      <div class="frow" style="margin-top:10px">
        <div><label class="lbl">Κανάλι</label><select class="inp" id="cpCh">${chOpts.map(([k, v]) => `<option value="${k}" ${d.channel === k ? 'selected' : ''}>${esc(v)}</option>`).join('')}</select></div>
        <div><label class="lbl">Κατάσταση</label><select class="inp" id="cpSt">${[['draft', 'Πρόχειρη'], ['active', 'Ενεργή'], ['done', 'Ολοκληρωμένη']].map(([k, v]) => `<option value="${k}" ${d.status === k ? 'selected' : ''}>${v}</option>`).join('')}</select></div>
      </div>
      <div class="frow" style="margin-top:10px">
        <div><label class="lbl">Προϋπολογισμός (€)</label><input class="inp" type="number" step="0.01" id="cpB" value="${d.budget || ''}"></div>
        <div><label class="lbl">Στόχος</label><input class="inp" id="cpG" value="${esc(d.goal || '')}" placeholder="π.χ. 20 νέοι πελάτες"></div>
      </div>
      <div class="frow" style="margin-top:10px">
        <div><label class="lbl">Έναρξη</label><input class="inp" type="date" id="cpStart" value="${d.start || ''}"></div>
        <div><label class="lbl">Λήξη</label><input class="inp" type="date" id="cpEnd" value="${d.end || ''}"></div>
      </div>
      <label class="lbl" style="margin-top:10px">Σημειώσεις</label><textarea class="inp" id="cpNotes" rows="2">${esc(d.notes || '')}</textarea>
      <div style="margin-top:12px;display:flex;gap:8px;align-items:center">
        <button class="btn btn-p" id="cpSave">Αποθήκευση</button>
        ${!isNew ? `<button class="btn btn-o" id="cpDel" style="color:var(--bad);margin-left:auto">${I.trash} Διαγραφή</button>` : ''}
      </div>
    </div></div>
    ${!isNew ? `<div class="card"><div class="card-h">${I.users} Μέλη (${d.members.length})</div><div class="card-b" id="cpMembers">
      ${d.members.length ? d.members.map(m => `
        <div style="display:flex;align-items:center;gap:8px;padding:6px 0;border-bottom:1px solid var(--line)">
          <div style="flex:1;min-width:0"><b style="font-size:12.5px">${esc(m.company || m.contact || 'Lead #' + m.id)}</b>
            <span class="pill" style="background:${m.stageCol}1a;color:${m.stageCol};font-size:9px;margin-left:5px">${esc(m.stageLbl)}</span></div>
          ${m.value > 0 ? `<span class="mut" style="font-size:11.5px">${fmtEur(m.value)}</span>` : ''}
          <button class="btn btn-sm btn-o" data-cprm="${m.id}" style="color:var(--bad)">✕</button></div>`).join('') : '<div class="mut" style="font-size:12px">Κανένα μέλος ακόμη</div>'}
      <div style="display:flex;gap:6px;margin-top:10px">
        <select class="inp" id="cpCand" style="flex:1"><option value="">— πρόσθεσε lead —</option>${d.candidates.map(l => `<option value="${l.id}">${esc(l.company || l.contact || 'Lead #' + l.id)} (${esc(l.stageLbl)})</option>`).join('')}</select>
        <button class="btn btn-p btn-sm" id="cpAddLead">+</button></div>
    </div></div>` : '<div class="mut" style="font-size:12px;padding:2px 4px">Αποθήκευσε πρώτα την καμπάνια για να προσθέσεις μέλη.</div>'}
  </div>`;
  document.body.append(ovl, dr);
  requestAnimationFrame(() => { ovl.classList.add('show'); dr.classList.add('show'); });
  $('#dX', dr).onclick = () => cnpAskClose(dr);
  $('#cpSave', dr).onclick = async () => {
    const name = $('#cpN', dr).value.trim(); if (!name) { toast('Δώσε όνομα'); return; }
    const r = await api('campaign_save', {id: id || 0, name, channel: $('#cpCh', dr).value, status: $('#cpSt', dr).value,
      budget: +$('#cpB', dr).value || 0, goal: $('#cpG', dr).value, start: $('#cpStart', dr).value, end: $('#cpEnd', dr).value, notes: $('#cpNotes', dr).value});
    toast('Αποθηκεύτηκε');
    if (isNew && r.id) { openCampaign(r.id, listD); } else { closeDrawer(); R.campaigns(); }
  };
  const dbtn = $('#cpDel', dr); if (dbtn) { dbtn.onclick = async () => {
    if (!await cnpConfirm('Διαγραφή καμπάνιας;')) return;
    await api('campaign_del', {id}); toast('Διαγράφηκε'); closeDrawer(); R.campaigns();
  }; }
  const al = $('#cpAddLead', dr); if (al) { al.onclick = async () => {
    const lid = +$('#cpCand', dr).value; if (!lid) return;
    await api('campaign_add_lead', {campaign: id, lead: lid}); toast('Προστέθηκε'); openCampaign(id, listD);
  }; }
  $$('[data-cprm]', dr).forEach(b => b.onclick = async () => {
    await api('campaign_remove_lead', {campaign: id, lead: +b.dataset.cprm}); openCampaign(id, listD);
  });
}

/* ═════════ REPORTS ΠΩΛΗΣΕΩΝ (Phase 7) ═════════ */
R.reports = async function () {
  setTop('CRM', 'Reports — αναλυτικά πωλήσεων');
  const c = $('#content');
  c.innerHTML = crmTabs('reports') + skel(4);
  const d = await api('crm_reports').catch(() => null);
  if (!d) { c.innerHTML = crmTabs('reports') + `<div class="empty"><div class="big">${I.lock}</div>Μόνο για διαχειριστές</div>`; return; }
  const suStat = window.CNP.suStat;
  const maxF = Math.max(1, ...d.funnel.map(f => f.count));
  const maxM = Math.max(1, ...d.byMonth.map(m => m.value));
  const mn = ['Ιαν', 'Φεβ', 'Μαρ', 'Απρ', 'Μαϊ', 'Ιουν', 'Ιουλ', 'Αυγ', 'Σεπ', 'Οκτ', 'Νοε', 'Δεκ'];
  const monLbl = ym => mn[+ym.split('-')[1] - 1];
  const tblHead = '<tr><th style="text-align:left">.</th><th>Leads</th><th>Won</th><th>Conv</th><th style="text-align:right">Αξία</th></tr>';
  const row = (label, s) => `<tr><td style="text-align:left">${esc(label)}</td><td style="text-align:center">${s.leads}</td><td style="text-align:center">${s.won}</td><td style="text-align:center"><b style="color:${s.conv >= 30 ? 'var(--ok)' : 'var(--mut)'}">${s.conv}%</b></td><td style="text-align:right">${fmtEur(s.value)}</td></tr>`;
  c.innerHTML = crmTabs('reports') + `
  <div class="grid" style="grid-template-columns:repeat(auto-fill,minmax(175px,1fr));gap:11px;margin-bottom:16px">
    ${suStat(I.trophy, (d.winRate === null ? '—' : d.winRate + '%'), 'Win rate', '#1f9d57')}
    ${suStat(I.funnel, fmtEur(d.pipeline), 'Pipeline (ανοιχτά)', '#0097e4')}
    ${suStat(I.receipt, fmtEur(d.wonValue), 'Έσοδα (won)', '#7b5cd6')}
    ${suStat(I.clock, (d.avgCloseDays === null ? '—' : d.avgCloseDays + ' ημ.'), 'Μ.Ο. κλεισίματος', '#e0a020')}
    ${suStat(I.users, d.open, 'Ανοιχτά leads', '#8291a9')}
  </div>
  <div class="grid g2" style="gap:14px">
    <div class="card"><div class="card-h">${I.funnel} Funnel ανά στάδιο</div><div class="card-b">
      ${d.funnel.map(f => `<div style="margin-bottom:9px">
        <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:3px"><span>${esc(f.label)}</span><b>${f.count}${f.value > 0 ? ' · ' + fmtEur(f.value) : ''}</b></div>
        <div class="bar" style="height:9px"><span style="width:${Math.round(f.count / maxF * 100)}%;background:${f.color};display:block;height:100%;border-radius:6px"></span></div></div>`).join('')}
    </div></div>
    <div class="card"><div class="card-h">${I.trendUp} Τάση 6 μηνών (έσοδα won)</div><div class="card-b">
      <div style="display:flex;align-items:flex-end;gap:8px;height:140px;padding-top:8px">
        ${d.byMonth.map(m => `<div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px;height:100%;justify-content:flex-end">
          <div style="font-size:10px;font-weight:700;color:var(--ink)">${m.won || ''}</div>
          <div style="width:70%;background:var(--brand);border-radius:5px 5px 0 0;height:${Math.round(m.value / maxM * 100)}%;min-height:${m.value > 0 ? '4px' : '0'}" title="${fmtEur(m.value)}"></div>
          <div class="mut" style="font-size:10px">${monLbl(m.ym)}</div></div>`).join('')}
      </div></div></div>
  </div>
  <div class="grid g2" style="gap:14px;margin-top:14px">
    <div class="card"><div class="card-h">${I.megaphone} Ανά πηγή</div><div class="card-b" style="overflow-x:auto">
      ${d.bySource.length ? `<table class="tbl" style="width:100%;font-size:12px"><thead>${tblHead.replace('>.<', '>Πηγή<')}</thead><tbody>${d.bySource.map(s => row(s.source, s)).join('')}</tbody></table>` : '<div class="mut" style="font-size:12px">Χωρίς δεδομένα</div>'}
    </div></div>
    <div class="card"><div class="card-h">${I.trophy} Ανά πωλητή</div><div class="card-b" style="overflow-x:auto">
      ${d.bySeller.length ? `<table class="tbl" style="width:100%;font-size:12px"><thead>${tblHead.replace('>.<', '>Πωλητής<')}</thead><tbody>${d.bySeller.map(s => row(s.name, s)).join('')}</tbody></table>` : '<div class="mut" style="font-size:12px">Χωρίς δεδομένα</div>'}
    </div></div>
  </div>`;
};

/* ═════════ IMPORT / EXPORT & ΔΙΠΛΟΤΥΠΑ (Phase 9) ═════════ */
R.crmdata = async function () {
  setTop('CRM', 'Import / Export leads & διπλότυπα');
  const c = $('#content');
  if (!S.boot.me.full) { c.innerHTML = crmTabs('crmdata') + `<div class="empty"><div class="big">${I.lock}</div>Μόνο για διαχειριστές</div>`; return; }
  let preview = [];
  c.innerHTML = crmTabs('crmdata') + `
  <div class="grid g2" style="gap:14px">
    <div class="card"><div class="card-h">${I.download} Εξαγωγή leads (CSV)</div><div class="card-b">
      <p class="mut" style="font-size:12.5px;margin:0 0 11px">Κατέβασε όλα τα leads σε αρχείο CSV (ανοίγει σε Excel / Google Sheets).</p>
      <button class="btn btn-p" id="cdExport">${I.download} Κατέβασμα CSV</button>
    </div></div>
    <div class="card"><div class="card-h">${I.alert} Έλεγχος διπλότυπων</div><div class="card-b">
      <p class="mut" style="font-size:12.5px;margin:0 0 11px">Βρες leads που μοιάζουν (ίδιο email / τηλέφωνο / εταιρεία) και συγχώνευσέ τα σε ένα.</p>
      <button class="btn btn-o" id="cdDupes">${I.search} Σάρωση για διπλότυπα</button>
      <div id="cdDupesBox" style="margin-top:12px"></div>
    </div></div>
  </div>
  <div class="card" style="margin-top:14px"><div class="card-h">${I.save} Εισαγωγή leads (CSV)</div><div class="card-b">
    <p class="mut" style="font-size:12.5px;margin:0 0 9px">Επικόλλησε CSV με στήλες <b>company, contact, email, phone, source, stage, value, next_action, descr</b> (η 1η γραμμή μπορεί να είναι κεφαλίδα). Θα εντοπίσουμε διπλότυπα πριν αποθηκεύσεις.</p>
    <textarea class="inp" id="cdCsv" rows="6" style="font-family:monospace;font-size:12px" placeholder="company,contact,email,phone,source,stage,value,next_action,descr
Acme,Γιάννης,info@acme.gr,2101234567,Referral,contacted,500,,"></textarea>
    <div style="margin-top:10px"><button class="btn btn-o" id="cdPrev">${I.search} Προεπισκόπηση</button></div>
    <div id="cdPrevBox" style="margin-top:14px"></div>
  </div></div>`;
  $('#cdExport').onclick = async () => {
    const r = await api('leads_export');
    const b = new Blob(['﻿' + r.csv], {type: 'text/csv;charset=utf-8'});
    const url = URL.createObjectURL(b);
    const a = document.createElement('a'); a.href = url; a.download = r.filename; document.body.append(a); a.click(); a.remove(); URL.revokeObjectURL(url);
    toast(r.count + ' leads εξήχθησαν');
  };
  $('#cdPrev').onclick = async () => {
    const csv = $('#cdCsv').value.trim(); if (!csv) { toast('Επικόλλησε CSV'); return; }
    const r = await api('leads_import_preview', {csv}).catch(() => null);
    if (!r) { toast('Σφάλμα ανάλυσης CSV'); return; }
    preview = r.rows;
    const box = $('#cdPrevBox');
    if (!r.total) { box.innerHTML = '<div class="mut" style="font-size:12px">Καμία έγκυρη γραμμή.</div>'; return; }
    box.innerHTML = `<div style="margin-bottom:9px;font-size:13px"><b>${r.total}</b> γραμμές · <b style="color:var(--ok)">${r.newN}</b> νέες · <b style="color:var(--warn)">${r.dupN}</b> πιθανά διπλότυπα</div>
    <div style="overflow-x:auto"><table class="tbl" style="width:100%;font-size:12px"><thead><tr><th style="text-align:left">Εταιρεία</th><th style="text-align:left">Επαφή</th><th style="text-align:left">Email</th><th>Στάδιο</th><th>Ενέργεια</th></tr></thead><tbody>
    ${preview.map((p, i) => `<tr>
      <td style="text-align:left">${esc(p.rec.company || '—')}${p.dup ? `<div class="mut" style="font-size:10.5px;color:var(--warn)">${I.alert} ίδιο ${esc(p.dup.by)} με #${p.dup.id} ${esc(p.dup.company || '')}</div>` : ''}</td>
      <td style="text-align:left">${esc(p.rec.contact || '')}</td>
      <td style="text-align:left">${esc(p.rec.email || '')}</td>
      <td style="text-align:center">${esc(p.rec.stage)}</td>
      <td style="text-align:center"><select class="inp" data-cdact="${i}" style="font-size:11px;padding:3px 5px;width:auto">
        <option value="new">Νέο</option>
        ${p.dup ? `<option value="update" selected>Ενημέρωση #${p.dup.id}</option>` : ''}
        <option value="skip">Παράλειψη</option>
      </select></td></tr>`).join('')}
    </tbody></table></div>
    <div style="margin-top:12px"><button class="btn btn-p" id="cdCommit">${I.save} Εισαγωγή</button></div>`;
    $('#cdCommit').onclick = async () => {
      preview.forEach((p, i) => { const sel = $(`[data-cdact="${i}"]`); p.action = sel ? sel.value : 'new'; });
      const rr = await api('leads_import_commit', {rows: preview});
      toast(`${rr.inserted} νέα · ${rr.updated} ενημερώθηκαν · ${rr.skipped} παραλείφθηκαν`);
      $('#cdCsv').value = ''; box.innerHTML = '';
    };
  };
  $('#cdDupes').onclick = async () => {
    const box = $('#cdDupesBox'); box.innerHTML = '<div class="mut" style="font-size:12px">Σάρωση…</div>';
    const r = await api('leads_dupes');
    if (!r.count) { box.innerHTML = '<div class="mut" style="font-size:12px">Δεν βρέθηκαν διπλότυπα 👏</div>'; return; }
    box.innerHTML = `<div style="font-size:12.5px;margin-bottom:8px"><b>${r.count}</b> ομάδες διπλότυπων</div>` + r.clusters.map((cl, ci) => `<div style="border:1px solid var(--line);border-left:3px solid var(--warn);border-radius:9px;padding:10px 12px;margin-bottom:9px">
      <div class="mut" style="font-size:11px;margin-bottom:6px">Ίδιο ${esc(cl.by)} — επίλεξε ποιο θα κρατηθεί:</div>
      ${cl.leads.map((l, li) => `<label style="display:flex;align-items:center;gap:8px;padding:4px 0;cursor:pointer">
        <input type="radio" name="keep${ci}" value="${l.id}" ${li === 0 ? 'checked' : ''}>
        <div style="flex:1;min-width:0"><b style="font-size:12.5px">${esc(l.company || l.contact || 'Lead #' + l.id)}</b>
          <span class="mut" style="font-size:11px">· #${l.id} · ${esc(l.stageLbl)}${l.value ? ' · ' + fmtEur(l.value) : ''}${l.email ? ' · ' + esc(l.email) : ''}</span></div></label>`).join('')}
      <div style="margin-top:7px"><button class="btn btn-sm btn-o" data-cdmerge="${ci}">${I.repeat} Συγχώνευση</button></div>
    </div>`).join('');
    $$('[data-cdmerge]').forEach(b => b.onclick = async () => {
      const ci = +b.dataset.cdmerge; const cl = r.clusters[ci];
      const sel = $(`input[name="keep${ci}"]:checked`);
      const keep = +(sel ? sel.value : cl.leads[0].id);
      const drops = cl.leads.map(l => l.id).filter(id => id !== keep);
      if (!drops.length) { toast('Επίλεξε ποιο να κρατήσεις'); return; }
      if (!await cnpConfirm(`Συγχώνευση ${drops.length + 1} leads σε ένα (#${keep}); Οι επικοινωνίες/tasks/προϊόντα μεταφέρονται.`)) { return; }
      for (const dId of drops) { await api('lead_merge', {keep, drop: dId}); }
      toast('Συγχωνεύτηκαν'); $('#cdDupes').click();
    });
  };
};

/* ═════════ ΤΟ ΠΡΟΦΙΛ ΜΟΥ (κάθε χρήστης) ═════════ */
R.profile = async function () {
  setTop('Το προφίλ μου', 'Στοιχεία, κωδικός, ειδοποιήσεις & δικαιώματα');
  const c = $('#content');
  c.innerHTML = skel(4);
  const d = await api('profile');
  const p = d.profile;
  const sw = (k, label, descr) => `
    <div class="set-row"><div><b>${label}</b><div class="mut" style="font-size:12px">${descr}</div></div>
      <label class="switch"><input type="checkbox" data-pref="${k}" ${d.prefs[k] === 'on' ? 'checked' : ''}><span></span></label></div>`;
  c.innerHTML = `
  <div class="grid g2">
    <div>
      <div class="card"><div class="card-h">${I.user} Τα στοιχεία μου</div><div class="card-b">
        <div style="display:flex;gap:13px;align-items:center;margin-bottom:14px">
          <span class="ava" style="width:46px;height:46px;font-size:17px">${esc(S.boot.me.ini)}</span>
          <div><b style="font-size:15px;color:var(--ink)">${esc(p.username)}</b>
            <div class="mut" style="font-size:12px">${esc(p.role)} · <span class="pill ${p.full ? 'pill-info' : ''}" style="font-size:9.5px">${p.full ? 'Διαχειριστής' : 'Χειριστής'}</span>
            ${p.since ? ' · μέλος από ' + dShort(p.since) : ''}</div></div></div>
        <div class="frow">
          <div><label class="lbl">Όνομα</label><input class="inp" id="prF" value="${esc(p.first || '')}"></div>
          <div><label class="lbl">Επώνυμο</label><input class="inp" id="prL" value="${esc(p.last || '')}"></div>
        </div>
        <label class="lbl" style="margin-top:11px">Email <span class="mut" style="font-weight:400">(εδώ έρχονται οι ειδοποιήσεις)</span></label>
        <input class="inp" id="prE" value="${esc(p.email || '')}">
        <label class="lbl" style="margin-top:11px">${I.video} Προσωπικό meeting link <span class="mut" style="font-weight:400">(3CX Meet ή άλλο — μπαίνει αυτόματα στα meetings σου)</span></label>
        <input class="inp" id="prM" value="${esc(d.prefs.meet_link || '')}" placeholder="https://cloudon.3cx.gr/meet/…">
        <div style="margin-top:13px"><button class="btn btn-p" id="prSave">Αποθήκευση</button></div>
      </div></div>
      <div class="card"><div class="card-h">${I.key} Αλλαγή κωδικού</div><div class="card-b">
        <label class="lbl">Τρέχων κωδικός</label><input type="password" class="inp" id="pwCur" autocomplete="current-password">
        <div class="frow" style="margin-top:11px">
          <div><label class="lbl">Νέος κωδικός (8+)</label><input type="password" class="inp" id="pwNew" autocomplete="new-password"></div>
          <div><label class="lbl">Επιβεβαίωση</label><input type="password" class="inp" id="pwCnf" autocomplete="new-password"></div>
        </div>
        <div style="margin-top:13px"><button class="btn btn-o" id="pwSave">Αλλαγή κωδικού</button></div>
      </div></div>
    </div>
    <div>
      <div class="card"><div class="card-h">${I.monitor} Απομακρυσμένη υποστήριξη — στήσιμο</div><div class="card-b">
        <div class="mut" style="font-size:12.5px;margin-bottom:10px">Εγκατέστησε <b>μία φορά</b> το CloudOn Remote στον υπολογιστή σου, για να ανοίγει αυτόματα το κουμπί «${I.monitor} Σύνδεση» από τα tickets.</div>
        <a class="btn btn-p" href="${S.boot.rustdeskDl || 'https://remote.cloudon.gr/download/CloudOn-Remote.exe'}" target="_blank" style="text-decoration:none">⬇ Κατέβασμα CloudOn Remote (Windows)</a>
        <div style="margin-top:13px;font-size:12.5px;line-height:2.1">
          <b style="color:var(--ink)">Βήματα:</b><br>
          <b>1.</b> Κατέβασε & άνοιξε το αρχείο<br>
          <b>2.</b> Πάτα <b>«Install»</b> (κάτω στο παράθυρο)<br>
          <b>3.</b> Έτοιμο — τώρα το κουμπί «${I.monitor} Σύνδεση» στα tickets ανοίγει αυτόματα τον πελάτη</div>
        <div style="margin-top:11px;padding:10px 13px;border-radius:10px;background:color-mix(in srgb, var(--brand) 7%, transparent);font-size:12px;color:var(--txt)">
          ${I.bulb} <b>Για δικούς μας servers:</b> εγκατέστησέ το και εκεί με <b>μόνιμο κωδικό</b> (Settings → Security → «Set permanent password») για πρόσβαση όποτε θες, χωρίς να είναι κανείς μπροστά (unattended).</div>
        <div class="mut" style="font-size:11px;margin-top:9px">Ο πελάτης ΔΕΝ χρειάζεται εγκατάσταση — απλά τρέχει το ίδιο αρχείο και σου διαβάζει το ID.</div>
      </div></div>
      <div class="card"><div class="card-h">${I.gear} Οι προτιμήσεις μου</div><div class="card-b">
        <div class="set-row"><div><b>Γλώσσα διεπαφής / Interface language</b>
          <div class="mut" style="font-size:12px">Σε ποια γλώσσα θα βλέπεις την εφαρμογή όταν συνδέεσαι — ισχύει μόνο για εσένα, σε κάθε συσκευή.</div></div>
          <select class="inp" id="prefLang" style="width:auto;min-width:150px">
            <option value="el" ${(d.prefs.lang || 'el') !== 'en' ? 'selected' : ''}>🇬🇷 Ελληνικά</option>
            <option value="en" ${(d.prefs.lang || 'el') === 'en' ? 'selected' : ''}>🇬🇧 English</option>
          </select></div>
      </div></div>
      <div class="card"><div class="card-h">${I.bell} Οι ειδοποιήσεις μου</div><div class="card-b">
        ${sw('notify_email', 'Ειδοποιήσεις email', 'Αναθέσεις, σχόλια, μπάλες, υπενθυμίσεις — το καμπανάκι μένει πάντα ενεργό')}
        ${sw('digest', 'Πρωινό daily digest', 'Καθημερινό email 07:30 με εκπρόθεσμα & follow-ups')}
      </div></div>
      <div class="card"><div class="card-h">${I.shield} Τα δικαιώματά μου</div><div class="card-b">
        <div class="set-row"><b>Ρόλος</b><span class="pill pill-info">${esc(p.role)}</span></div>
        <div class="set-row"><b>Πρόσβαση</b><span class="mut" style="font-size:12px">${p.full
          ? 'Πλήρης — όλα τα projects, KPI, διαχείριση' : 'Βλέπεις τα projects όπου είσαι μέλος και ό,τι σου έχει ανατεθεί'}</span></div>
        ${d.teams.length ? `<div class="set-row" style="align-items:flex-start"><b>Ομάδες</b>
          <div style="display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end">${d.teams.map(t =>
            `<span class="pill" style="background:${t.color}22;color:${t.color}">${t.leader ? (I.crown + ' ') : ''}${esc(t.name)}${t.role ? ' · ' + esc(t.role) : ''}</span>`).join('')}</div></div>` : ''}
      </div></div>
      <div class="card"><div class="card-h">${I.folder} Τα projects μου ${d.allProjects ? '<span class="mut" style="font-weight:400;font-size:11px">(πλήρης πρόσβαση)</span>' : ''}</div><div class="card-b">
        ${d.projects.length ? d.projects.map(pr => `<div class="set-row" data-pgo="${pr.id}" style="cursor:pointer">
          <span class="dot" style="background:${pr.color}"></span><b style="flex:1;font-size:12.5px">${esc(pr.name)}</b><span class="mut">→</span></div>`).join('')
          : '<div class="empty" style="padding:18px">Κανένα project ακόμα</div>'}
      </div></div>
    </div>
  </div>
  <div class="card" style="margin-top:16px"><div class="card-h">${I.key} Θυρίδα κωδικών
    <span class="mut" style="font-weight:400;font-size:11px;margin-left:8px">κρυπτογραφημένα (AES-256) · ${p.full ? 'ως διαχειριστής βλέπεις όλων των χειριστών' : 'ιδιωτικά — μόνο εσύ'}</span>
    <button class="btn btn-p btn-sm" id="vAdd" style="margin-left:auto">${I.plus} Νέος κωδικός</button></div>
    <div class="card-b" id="vaultBox"><div class="mut" style="font-size:12px">Φόρτωση…</div></div>
  </div>`;
  $('#prSave').onclick = async () => {
    const r = await api('profile_save', {first: $('#prF').value, last: $('#prL').value,
      email: $('#prE').value, meetLink: $('#prM').value}).catch(e => ({err: e.message}));
    if (r.err) { toast(r.err, true); return; }
    toast('Τα στοιχεία αποθηκεύτηκαν — θα φανούν πλήρως στην επόμενη σύνδεση');
  };
  $('#pwSave').onclick = async () => {
    const r = await api('profile_pass', {current: $('#pwCur').value, new: $('#pwNew').value,
      confirm: $('#pwCnf').value}).catch(e => ({err: e.message}));
    if (r.err) { toast(r.err, true); return; }
    toast('Ο κωδικός άλλαξε ✓');
    ['#pwCur', '#pwNew', '#pwCnf'].forEach(x => $(x).value = '');
  };
  $$('[data-pref]').forEach(sw2 => sw2.onchange = async () => {
    await api('profile_pref', {key: sw2.dataset.pref, value: sw2.checked ? 'on' : 'off'});
    toast(sw2.checked ? 'Ενεργοποιήθηκε' : 'Απενεργοποιήθηκε');
  });
  { const lg = $('#prefLang');
    if (lg) { lg.onchange = async () => {
      await api('profile_pref', {key: 'lang', value: lg.value});
      toast(lg.value === 'en' ? 'Language set to English' : 'Η γλώσσα άλλαξε σε Ελληνικά');
      if (window.CNP_I18N) { window.CNP_I18N.set(lg.value); }   // εφαρμογή άμεσα (reload)
    }; } }
  $$('[data-pgo]').forEach(r => r.onclick = () => {
    S.project = +r.dataset.pgo;
    window.CNP.go('board');
  });

  /* ── 🔐 Θυρίδα κωδικών (κοινή λογική με το nav «Κωδικοί») ── */
  mountVault();
};

/* ═════════ 🔐 ΘΥΡΙΔΑ ΚΩΔΙΚΩΝ — standalone view + shared functions ═════════ */
R.vault = async function () {
  setTop('Κωδικοί', 'Η θυρίδα κωδικών σου — κρυπτογραφημένα (AES-256)');
  const full = S.boot.me.full;
  $('#content').innerHTML = `<div class="card"><div class="card-h">${I.key} Θυρίδα κωδικών
    <span class="mut" style="font-weight:400;font-size:11px;margin-left:8px">κρυπτογραφημένα · ${full ? 'ως διαχειριστής βλέπεις όλων των χειριστών' : 'ιδιωτικά — μόνο εσύ'}</span>
    <button class="btn btn-p btn-sm" id="vAdd" style="margin-left:auto">${I.plus} Νέος κωδικός</button></div>
    <div class="card-b" id="vaultBox"><div class="mut" style="font-size:12px">Φόρτωση…</div></div></div>`;
  mountVault();
};
let _vaultMine = false;
function mountVault() {
  window._vaultKinds = null; window._vaultItems = [];
  const add = $('#vAdd'); if (add) { add.onclick = () => openVaultForm(null); }
  loadVault();
}
async function loadVault() {
    const box = $('#vaultBox'); if (!box) { return; }
    const d = await api('vault_list' + (_vaultMine ? '&mine=1' : '')).catch(() => null);
    if (!d) { box.innerHTML = '<div class="mut" style="font-size:12px">Σφάλμα φόρτωσης</div>'; return; }
    window._vaultKinds = d.kinds; window._vaultItems = d.items;
    const scope = d.full ? `<div style="display:flex;gap:6px;margin-bottom:11px">
      <button class="btn btn-sm ${_vaultMine ? 'btn-o' : 'btn-p'}" data-vscope="0">Όλων των χειριστών</button>
      <button class="btn btn-sm ${_vaultMine ? 'btn-p' : 'btn-o'}" data-vscope="1">Μόνο δικά μου</button></div>` : '';
    box.innerHTML = scope + (d.items.length ? `<div style="overflow-x:auto"><table class="tbl" style="width:100%;font-size:12.5px">
      <thead><tr><th style="text-align:left">Περιγραφή</th><th style="text-align:left">Τύπος</th><th style="text-align:left">User</th><th style="text-align:left">Κωδικός</th><th style="text-align:left">IP / URL</th><th style="text-align:left">Πελάτης / Χρήση</th>${d.full ? '<th style="text-align:left">Χειριστής</th>' : ''}<th></th></tr></thead><tbody>
      ${d.items.map(v => `<tr>
        <td style="text-align:left"><b>${esc(v.descr)}</b>${v.shared ? ` <span class="pill" style="background:var(--ok)1a;color:var(--ok);font-size:8.5px">${I.users} κοινή</span>` : ''}${v.location || (v.shared && !v.canEdit) ? `<div class="mut" style="font-size:10.5px">${v.location ? I.pin + ' ' + esc(v.location) : ''}${v.shared && !v.canEdit ? (v.location ? ' · ' : '') + esc(v.ownerName) : ''}</div>` : ''}</td>
        <td style="text-align:left"><span class="pill" style="font-size:9.5px">${esc(v.kindLbl)}</span></td>
        <td style="text-align:left">${v.username ? `<span style="font-family:monospace">${esc(v.username)}</span> <button class="btn btn-sm btn-o" data-vcopyu="${esc(v.username)}" title="αντιγραφή" style="padding:2px 6px">⧉</button>` : '<span class="mut">—</span>'}</td>
        <td style="text-align:left;white-space:nowrap"><span class="vpw" data-vid="${v.id}" style="font-family:monospace">••••••••</span>
          <button class="btn btn-sm btn-o" data-vreveal="${v.id}" title="εμφάνιση" style="padding:2px 6px">👁</button>
          <button class="btn btn-sm btn-o" data-vcopy="${v.id}" title="αντιγραφή" style="padding:2px 6px">⧉</button></td>
        <td style="text-align:left;font-size:11px">${v.ips ? esc(v.ips.split(/[,\n]/)[0].trim()) + (v.ips.split(/[,\n]/).filter(x => x.trim()).length > 1 ? ' <span class="mut">+' + (v.ips.split(/[,\n]/).filter(x => x.trim()).length - 1) + '</span>' : '') : ''}${v.url ? `<div><a href="${esc(v.url)}" target="_blank" rel="noopener" style="color:var(--brand)">${esc(v.url.replace(/^https?:\/\//, '').slice(0, 30))}</a></div>` : ''}</td>
        <td style="text-align:left;font-size:11px">${v.clientName ? esc(v.clientName) : ''}${v.purpose ? `<div class="mut">${esc(v.purpose)}</div>` : ''}</td>
        ${d.full ? `<td style="text-align:left;font-size:11px" class="mut">${esc(v.ownerName)}</td>` : ''}
        <td style="text-align:right;white-space:nowrap">${v.canEdit ? `<button class="btn btn-sm btn-o" data-vedit="${v.id}" style="padding:3px 7px">${I.edit}</button>
          <button class="btn btn-sm btn-o" data-vdel="${v.id}" style="padding:3px 7px;color:var(--bad)">${I.trash}</button>` : '<span class="mut" style="font-size:10px">κοινό</span>'}</td></tr>`).join('')}
      </tbody></table></div>` : '<div class="empty" style="padding:22px">Καμία καταχώρηση ακόμη — πάτα «Νέος κωδικός»</div>');
    $$('[data-vscope]', box).forEach(b => b.onclick = () => { _vaultMine = b.dataset.vscope === '1'; loadVault(); });
    $$('[data-vreveal]', box).forEach(b => b.onclick = async () => {
      const sp = box.querySelector(`.vpw[data-vid="${b.dataset.vreveal}"]`);
      if (sp.dataset.shown) { sp.textContent = '••••••••'; delete sp.dataset.shown; return; }
      const r = await api('vault_reveal', {id: +b.dataset.vreveal}); sp.textContent = r.password; sp.dataset.shown = '1';
    });
    $$('[data-vcopy]', box).forEach(b => b.onclick = async () => { const r = await api('vault_reveal', {id: +b.dataset.vcopy}); await navigator.clipboard.writeText(r.password); toast('Κωδικός αντιγράφηκε ✓'); });
    $$('[data-vcopyu]', box).forEach(b => b.onclick = async () => { await navigator.clipboard.writeText(b.dataset.vcopyu); toast('User αντιγράφηκε'); });
    $$('[data-vedit]', box).forEach(b => b.onclick = () => openVaultForm(window._vaultItems.find(x => x.id === +b.dataset.vedit)));
    $$('[data-vdel]', box).forEach(b => b.onclick = async () => { if (!await cnpConfirm('Διαγραφή καταχώρησης;')) { return; } await api('vault_del', {id: +b.dataset.vdel}); toast('Διαγράφηκε'); loadVault(); });
  }
  function vaultGen(len) {
    len = len || 18;
    const cc = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%^&*-_=+';
    const a = crypto.getRandomValues(new Uint32Array(len));
    return Array.from(a, x => cc[x % cc.length]).join('');
  }
  function openVaultForm(item) {
    const box = $('#vaultBox'); if (!box) { return; }
    const isNew = !item;
    const kinds = window._vaultKinds || {other: 'Άλλο'};
    const isShared = !isNew && item.shared;
    box.innerHTML = `<div class="vault-form">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px">
        <h3 style="margin:0;font-size:18px;color:var(--ink);display:flex;align-items:center;gap:9px">${I.key} ${isNew ? 'Νέος κωδικός' : 'Επεξεργασία καταχώρησης'}</h3>
        <button class="btn btn-o btn-sm" id="vBack" style="margin-left:auto">← Πίσω στη λίστα</button>
      </div>
      <div class="frow" style="gap:18px">
        <div style="flex:2"><label class="lbl">Περιγραφή *</label><input class="inp" id="vDescr" value="${isNew ? '' : esc(item.descr)}" placeholder="π.χ. Firewall γραφείου"></div>
        <div style="flex:1"><label class="lbl">Τύπος <span class="mut" style="font-weight:400">(εξοπλισμός ή λογισμικό)</span></label>
          <input class="inp" id="vKind" list="vKindL" value="${isNew ? '' : esc(kinds[item.kind] || item.kind)}" placeholder="π.χ. SoftOne, Server, Microsoft 365…" autocomplete="off">
          <datalist id="vKindL">${Object.values(kinds).map(l => `<option value="${esc(l)}">`).join('')}</datalist></div>
      </div>
      <div class="frow" style="margin-top:18px;gap:18px">
        <div><label class="lbl">User</label><input class="inp" id="vUser" value="${isNew ? '' : esc(item.username)}" autocomplete="off"></div>
        <div><label class="lbl">Κωδικός ${isNew ? '' : '<span class="mut" style="font-weight:400">(κενό = ίδιος)</span>'}</label>
          <div style="display:flex;gap:7px"><input class="inp" id="vPass" type="text" autocomplete="off" placeholder="${isNew ? 'γράψε ή πάτα 🎲' : '••••••'}" style="font-family:monospace;flex:1">
            <button class="btn btn-o" id="vGen" title="Παραγωγή ισχυρού" type="button">🎲</button></div></div>
      </div>
      <label class="lbl" style="margin-top:18px">IP <span class="mut" style="font-weight:400">(μία ή περισσότερες — κόμμα ή νέα γραμμή)</span></label>
      <textarea class="inp" id="vIps" rows="3" placeholder="192.168.1.1, 10.0.0.5">${isNew ? '' : esc(item.ips)}</textarea>
      <div class="frow" style="margin-top:18px;gap:18px">
        <div><label class="lbl">URL</label><input class="inp" id="vUrl" value="${isNew ? '' : esc(item.url)}" placeholder="https://…"></div>
        <div><label class="lbl">${I.pin} Τοποθεσία</label><input class="inp" id="vLoc" value="${isNew ? '' : esc(item.location)}" placeholder="π.χ. Rack A2 / γραφείο"></div>
      </div>
      <div class="frow" style="margin-top:18px;gap:18px">
        <div><label class="lbl">Για ποιον πελάτη</label><input class="inp" id="vCli" list="vCliL" placeholder="αναζήτηση πελάτη…" value="${isNew || !item.clientId ? '' : esc(item.clientName + ' (#' + item.clientId + ')')}"><datalist id="vCliL"></datalist><input type="hidden" id="vCliId" value="${isNew ? '' : (item.clientId || '')}"></div>
        <div><label class="lbl">…ή για ποια χρήση</label><input class="inp" id="vPurp" value="${isNew ? '' : esc(item.purpose)}" placeholder="π.χ. εσωτερικό backup"></div>
      </div>
      <label class="lbl" style="margin-top:20px">Ορατότητα</label>
      <div style="display:flex;gap:12px;flex-wrap:wrap">
        <label class="vis-opt ${isShared ? '' : 'on'}"><input type="radio" name="vShared" value="0" ${isShared ? '' : 'checked'}><span>${I.shield} <b>Προσωπική</b> — μόνο εσύ (& διαχειριστής)</span></label>
        <label class="vis-opt ${isShared ? 'on' : ''}"><input type="radio" name="vShared" value="1" ${isShared ? 'checked' : ''}><span>${I.users} <b>Κοινή (ομάδας)</b> — όλη η ομάδα βλέπει/χρησιμοποιεί</span></label>
      </div>
      <div style="margin-top:26px;display:flex;gap:8px">
        <button class="btn btn-p" id="vSave">${I.save} Αποθήκευση</button>
        <button class="btn btn-o" id="vCancel">Άκυρο</button></div>
    </div>`;
    box.scrollIntoView({behavior: 'smooth', block: 'nearest'});
    clientAuto('vCli', 'vCliL', 'vCliId');
    $$('.vis-opt', box).forEach(l => l.querySelector('input').onchange = () => {
      $$('.vis-opt', box).forEach(x => x.classList.toggle('on', x.querySelector('input').checked));
    });
    $('#vGen', box).onclick = () => { $('#vPass', box).value = vaultGen(); };
    $('#vBack', box).onclick = () => loadVault();
    $('#vCancel', box).onclick = () => loadVault();
    $('#vSave', box).onclick = async () => {
      const descr = $('#vDescr', box).value.trim(); if (!descr) { toast('Δώσε περιγραφή', true); return; }
      const sh = box.querySelector('input[name="vShared"]:checked');
      await api('vault_save', {id: isNew ? 0 : item.id, descr, kind: $('#vKind', box).value, username: $('#vUser', box).value,
        password: $('#vPass', box).value, ips: $('#vIps', box).value, url: $('#vUrl', box).value, location: $('#vLoc', box).value,
        client: +($('#vCliId', box).value || 0), purpose: $('#vPurp', box).value, shared: sh ? +sh.value : 0}).catch(e => ({err: e.message}));
      toast('Αποθηκεύτηκε ✓'); loadVault();
    };
  }
