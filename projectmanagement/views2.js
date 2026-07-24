/* ═══════════ CloudOn Projects — views pack 2 (όλα τα κυκλώματα) ═══════════ */
'use strict';
const {S, api, esc, fmtMin, fmtEur, dShort, tShort, today, toast, setTop,
  adminName, adminIni, statusOf, typeOf, dnd, I, openTask, closeDrawer, crmTabs, openLead, cnpConfirm, cnpPrompt, $, $$} = window.CNP;
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
function openEvent(ev, ymRefresh) {
  closeDrawer();
  const isNew = !ev;
  ev = ev || {kind: 'meeting', attendees: [S.boot.me.id], allDay: false};
  const ovl = document.createElement('div'); ovl.className = 'ovl'; ovl.onclick = closeDrawer;
  const dr = document.createElement('div'); dr.className = 'drawer';
  const d0 = ev.start ? ev.start.slice(0, 10) : today();
  const t0 = ev.start ? ev.start.slice(11, 16) : '10:00';
  const d1 = ev.end ? ev.end.slice(0, 10) : today();
  const t1 = ev.end ? ev.end.slice(11, 16) : '11:00';
  dr.innerHTML = `
  <div class="drawer-h"><h2>${isNew ? 'Νέο συμβάν' : esc(ev.title)}</h2><button class="drawer-x" id="dX">✕</button></div>
  <div class="drawer-b"><div class="card"><div class="card-b">
    <label class="lbl">Τύπος</label>
    <div style="display:flex;gap:7px;flex-wrap:wrap" id="evKinds">
      ${Object.entries(EV_KINDS).map(([k, [ico, l, col]]) => `
        <button class="btn btn-sm ${ev.kind === k ? 'btn-p' : 'btn-o'}" data-k="${k}" style="${ev.kind === k ? '' : 'border-color:' + col}">${ico} ${l}</button>`).join('')}
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
    <div style="display:flex;gap:9px;flex-wrap:wrap">
      ${S.boot.admins.map(a => `<label style="font-size:12.5px;display:flex;gap:4px;align-items:center">
        <input type="checkbox" class="evA" value="${a.id}" ${(ev.attendees || []).includes(a.id) ? 'checked' : ''}> ${esc(a.name)}</label>`).join('')}
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
    <textarea class="inp" id="evN" rows="3">${esc(ev.notes || '')}</textarea>
    ${!isNew ? `<div style="margin-top:13px;padding:11px 14px;border-radius:11px;background:var(--line)">
      <b style="font-size:12px;color:var(--ink)">Ποιος θα είναι εκεί</b>
      <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:7px">
        ${(ev.attendees || []).map(a => {
          const st = (ev.rsvp || {})['admin' + a];
          return `<span class="pill ${st === 'accepted' ? 'pill-ok' : st === 'declined' ? 'pill-bad' : 'pill-mut'}"
            title="${st === 'accepted' ? 'Αποδέχθηκε' : st === 'declined' ? 'Δεν μπορεί' : 'Δεν απάντησε ακόμη'}">
            ${st === 'accepted' ? '✅' : st === 'declined' ? '❌' : '⏳'} ${esc(adminName(a))}</span>`;
        }).join('')}
        ${ev.client ? (() => {
          const st = (ev.rsvp || {})['client' + ev.client];
          return `<span class="pill ${st === 'accepted' ? 'pill-ok' : st === 'declined' ? 'pill-bad' : 'pill-mut'}">
            ${st === 'accepted' ? '✅' : st === 'declined' ? '❌' : '⏳'} ${I.building} ${esc(ev.clientName || 'Πελάτης')}</span>`;
        })() : ''}
      </div>
      ${(ev.attendees || []).includes(S.boot.me.id) ? `<div style="display:flex;gap:7px;margin-top:9px">
        <button class="btn btn-sm ${(ev.rsvp || {})['admin' + S.boot.me.id] === 'accepted' ? 'btn-p' : 'btn-o'}" id="evAcc">✅ Θα είμαι εκεί</button>
        <button class="btn btn-sm btn-o" id="evDec">❌ Δεν μπορώ</button></div>` : ''}
    </div>` : ''}
    <div style="display:flex;gap:9px;margin-top:13px;flex-wrap:wrap">
      ${!isNew && ev.location && /^https?:\/\//.test(ev.location) ? `<button class="btn" id="evJoin" style="background:var(--ok);color:#fff">${I.video} Συμμετοχή στο meeting</button>` : ''}
      <button class="btn btn-p" id="evSave">Αποθήκευση</button>
      ${!isNew && ev.canEdit ? '<button class="btn btn-o" id="evDel" style="color:var(--bad)">${I.trash} Διαγραφή</button>' : ''}
    </div>
    ${!isNew && !ev.canEdit ? '<div class="mut" style="font-size:11.5px;margin-top:8px">Μόνο ο δημιουργός ή διαχειριστής μπορεί να το αλλάξει.</div>' : ''}
  </div></div></div>`;
  document.body.append(ovl, dr);
  requestAnimationFrame(() => { ovl.classList.add('show'); dr.classList.add('show'); });
  $('#dX').onclick = closeDrawer;
  clientAuto('evCli', 'evCliL', 'evCliId');
  let kind = ev.kind;
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
  const xmails = [];
  const renderXm = () => {
    $('#evXmList', dr).innerHTML = xmails.map((m, i) => `
      <span class="pill pill-info" style="display:inline-flex;gap:5px;align-items:center">${I.mail} ${esc(m)}
        <b data-xmdel="${i}" style="cursor:pointer;opacity:.7">✕</b></span>`).join('');
    $$('[data-xmdel]', dr).forEach(b => b.onclick = () => { xmails.splice(+b.dataset.xmdel, 1); renderXm(); });
  };
  const addXm = () => {
    const v = $('#evXm', dr).value.trim().toLowerCase();
    if (!v) return;
    if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(v)) { toast('Μη έγκυρο email', true); return; }
    if (xmails.includes(v)) { toast('Υπάρχει ήδη στη λίστα', true); return; }
    xmails.push(v);
    $('#evXm', dr).value = '';
    renderXm();
  };
  $('#evXmAdd', dr).onclick = addXm;
  $('#evXm', dr).onkeydown = e => { if (e.key === 'Enter') { e.preventDefault(); addXm(); } };
  $('#evMeet', dr).onclick = async () => {
    const r = await api('meet_room');
    $('#evLoc', dr).value = r.url;
    toast('🎥 Δημιουργήθηκε δωμάτιο CloudOn Meet — το link ισχύει και για πελάτες');
  };
  $('#evSave', dr).onclick = async () => {
    addXm();   // ό,τι έμεινε γραμμένο στο πεδίο email μπαίνει στη λίστα αυτόματα
    const allDay = $('#evAll', dr).checked;
    const r = await api('event_save', {id: ev.id || 0, kind, title: $('#evT', dr).value,
      start: $('#evD0', dr).value + (allDay ? ' 00:00' : ' ' + $('#evT0', dr).value),
      end: $('#evD1', dr).value + (allDay ? ' 23:59' : ' ' + $('#evT1', dr).value),
      allDay, attendees: $$('.evA:checked', dr).map(x => +x.value),
      client: +$('#evCliId', dr).value || 0, location: $('#evLoc', dr).value,
      inviteClient: $('#evInv', dr).checked, extraEmails: xmails.join(','),
      notes: $('#evN', dr).value}).catch(e => ({err: e.message}));
    if (r.err) { toast(r.err, true); return; }
    toast('Αποθηκεύτηκε 📅'); closeDrawer(); R.calendar(ymRefresh);
  };
  const jb = $('#evJoin', dr); if (jb) jb.onclick = () => window.open(ev.location, '_blank');
  const acc = $('#evAcc', dr); if (acc) acc.onclick = async () => {
    await api('event_rsvp', {id: ev.id, status: 'accepted'});
    toast('✅ Δήλωσες συμμετοχή'); closeDrawer(); R.calendar(ymRefresh);
  };
  const dec = $('#evDec', dr); if (dec) dec.onclick = async () => {
    await api('event_rsvp', {id: ev.id, status: 'declined'});
    toast('Καταγράφηκε ότι δεν μπορείς'); closeDrawer(); R.calendar(ymRefresh);
  };
  const del = $('#evDel', dr); if (del) del.onclick = async () => {
    if (!(await cnpConfirm('Διαγραφή συμβάντος;', {danger: true, ok: I.trash + ' Διαγραφή'}))) return;
    await api('event_del', {id: ev.id});
    toast('Διαγράφηκε'); closeDrawer(); R.calendar(ymRefresh);
  };
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
    cells += `<td class="${date === today() ? 'today' : ''}"><div class="d">${day}</div>` +
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
  <div style="display:flex;gap:11px;align-items:center;margin-bottom:14px">
    <button class="btn btn-o btn-sm" id="calP">←</button>
    <b style="font-size:17px;color:var(--ink)">${mn} ${Y}</b>
    <button class="btn btn-o btn-sm" id="calN">→</button>
    ${d.ym !== today().slice(0, 7) ? '<button class="btn btn-sm" id="calT">Σήμερα</button>' : ''}
    <button class="btn btn-p btn-sm" id="evNew" style="margin-left:auto">${I.plus} Νέο συμβάν</button>
    <span class="mut" style="font-size:11px">${Object.entries(EV_KINDS).map(([, [ico, l]]) => ico + ' ' + l).join(' · ')}</span></div>
    <table class="cpm-cal cnp-cal"><thead><tr>${['Δευ', 'Τρί', 'Τετ', 'Πέμ', 'Παρ', 'Σάβ', 'Κυρ'].map(x => `<th>${x}</th>`).join('')}</tr></thead>
    <tbody>${cells}</tr></tbody></table>`;
  $('#calP').onclick = () => R.calendar(fmtYm(prev));
  $('#calN').onclick = () => R.calendar(fmtYm(next));
  const t = $('#calT'); if (t) t.onclick = () => R.calendar();
  $('#evNew').onclick = () => openEvent(null, d.ym);
  $$('.ev[data-task]').forEach(a => a.onclick = () => openTask(+a.dataset.task));
  $$('.ev[data-event]').forEach(a => a.onclick = () => {
    const ev = (d.events || []).find(x => x.id === +a.dataset.event);
    if (ev) openEvent(ev, d.ym);
  });
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
  c.innerHTML = `
  <div style="display:flex;gap:12px;margin-bottom:14px;flex-wrap:wrap;align-items:center">
    <div class="stat info" style="margin:0;flex:1;min-width:170px"><b>${fmtEur(openV)}</b><small>Ανοιχτές προσφορές</small></div>
    <div class="stat ok" style="margin:0;flex:1;min-width:170px"><b>${won.length} / ${fmtEur(won.reduce((s, o) => s + o.value, 0))}</b><small>Κερδισμένες</small></div>
    <button class="btn btn-p" id="newOffer">${I.plus} Νέα προσφορά</button>
  </div>
  <div class="kb" style="min-height:calc(100vh - 290px)">
    ${d.stages.map(sg => {
      const list = d.offers.filter(o => o.stage === sg.key);
      const sum = list.reduce((s, o) => s + o.value, 0);
      return `<div class="kb-col ocol" data-stage="${sg.key}">
        <div class="kb-h" style="border-color:${sg.color}">${esc(sg.title)}<span class="kb-n">${list.length}</span></div>
        ${sum > 0 ? `<div class="mut" style="padding:5px 15px;font-size:11px;font-weight:700">${fmtEur(sum)}</div>` : ''}
        <div class="kb-cards">${list.map(o => `
          <div class="tcard ocard" data-offer="${o.id}">
            <div class="tcard-t">${esc(o.title)}</div>
            <div class="tcard-m">
              ${o.clientName ? `<span>${I.user} ${esc(o.clientName)}</span>` : ''}
              ${o.value > 0 ? `<b style="color:var(--ink)">${fmtEur(o.value)}</b>` : ''}
              ${o.quote ? `<span class="pill pill-info">Q${o.quote}</span>` : ''}
              ${o.expected ? `<span class="${o.expected < today() && !sg.closed ? 'pill pill-bad' : ''}">${I.cal} ${dShort(o.expected)}</span>` : ''}
            </div></div>`).join('')}</div></div>`;
    }).join('')}
  </div>`;
  $('#newOffer').onclick = () => openOffer(null, d);
  if (!R.offers._dnd) {
    R.offers._dnd = 1;
    dnd('.ocard', '.ocol', async (card, col) => {
      const r = await api('move_offer', {offer: +card.dataset.offer, stage: col.dataset.stage}).catch(() => ({ok: 0}));
      if (r.ok) { col.querySelector('.kb-cards').appendChild(card);
        $$('.ocol').forEach(x => x.querySelector('.kb-n').textContent = x.querySelectorAll('.tcard').length);
      } else toast('Δεν επιτρέπεται', true);
    }, async el => { const dd = await api('offers'); openOffer(dd.offers.find(o => o.id === +el.dataset.offer), dd); });
  }
};
function openOffer(o, d) {
  closeDrawer();
  const isNew = !o; o = o || {stage: 'new'};
  const ovl = document.createElement('div'); ovl.className = 'ovl'; ovl.onclick = closeDrawer;
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
      <textarea class="inp" id="oDescr" rows="3">${esc(o.descr || '')}</textarea>
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
  $('#dX').onclick = closeDrawer;
  clientAuto('oClient', 'oCliL', 'oClientId');
  $('#oSave', dr).onclick = async () => {
    await api('save_offer', {offer: o.id || 0, title: $('#oTitle').value, client: +$('#oClientId').value || 0,
      amount: $('#oAmount').value !== '' ? +$('#oAmount').value : null, stage: $('#oStage').value,
      expected: $('#oExp').value || null, descr: $('#oDescr').value});
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
function clientAuto(inpId, listId, hidId) {
  const inp = $('#' + inpId), dl = $('#' + listId), hid = $('#' + hidId);
  if (!inp) return;
  let t;
  inp.addEventListener('input', () => {
    const m = inp.value.match(/\(#(\d+)\)\s*$/);
    hid.value = m ? m[1] : '';
    clearTimeout(t);
    const q = inp.value.trim();
    if (q.length < 2 || m) return;
    t = setTimeout(async () => {
      const r = await api('client_search&q=' + encodeURIComponent(q));
      dl.innerHTML = r.results.map(x => `<option value="${esc(x.label)}">`).join('');
    }, 250);
  });
}

/* ═════════ ΕΠΑΦΕΣ ═════════ */
R.contacts = async function () {
  setTop('CRM', 'Επαφές — leads & πελάτες με CRM δραστηριότητα');
  const c = $('#content');
  c.innerHTML = crmTabs('contacts') + `<div class="card" style="padding:13px 16px;display:flex;gap:9px">
    <input class="inp" id="ctQ" placeholder="Αναζήτηση σε leads & πελάτες… (Enter)" style="max-width:340px"></div>
    <div id="ctRes">${skel(1, 300)}</div>`;
  const load = async q => {
    const d = await api('contacts' + (q ? '&q=' + encodeURIComponent(q) : ''));
    $('#ctRes').innerHTML = `<div class="card"><table class="tbl"><thead><tr>
      <th>Επαφή</th><th>Κατάσταση</th><th>Τηλέφωνο</th><th>Email</th><th>Τελ. επαφή</th><th>Follow-up</th><th>Χειριστής</th></tr></thead><tbody>
      ${d.rows.length ? d.rows.map(r => `<tr ${r.kind === 'lead' ? `data-lead="${r.id}" style="cursor:pointer"` : ''}>
        <td><b>${esc(r.name)}</b>${r.sub ? ` <span class="mut">${esc(r.sub)}</span>` : ''}</td>
        <td><span class="pill" style="background:${r.color}22;color:${r.color}">${esc(r.badge)}</span></td>
        <td>${esc(r.phone || '—')}</td><td>${esc(r.email || '—')}</td>
        <td>${r.last ? dShort(r.last) : '<span class="mut">ποτέ</span>'}</td>
        <td>${r.next ? `<span class="${r.next <= today() ? 'pill pill-bad' : ''}">${I.bell} ${dShort(r.next)}</span>` : '—'}</td>
        <td>${esc(r.who || '—')}</td></tr>`).join('')
        : '<tr><td colspan="7" class="empty">Τίποτα ακόμη</td></tr>'}</tbody></table></div>`;
    $$('#ctRes tr[data-lead]').forEach(row => row.onclick = async () => {
      const dd = await api('crm'); const l = dd.leads.find(x => x.id === +row.dataset.lead);
      if (l && window.CNP) { window.CNP.go('crm'); setTimeout(() => {}, 0); }
    });
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
};

/* ═════════ ΣΤΟΧΟΙ ΠΡΟΪΟΝΤΩΝ ═════════ */
R.targets = async function (ym) {
  setTop('CRM', 'Στόχοι προϊόντων — μηνιαίοι στόχοι vs πραγματικές πωλήσεις');
  const c = $('#content');
  c.innerHTML = crmTabs('targets') + skel(1, 300);
  const d = await api('targets' + (ym ? '&ym=' + ym : '')).catch(() => null);
  if (!d) { c.innerHTML = '<div class="empty"><div class="big">${I.lock}</div>Μόνο για διαχειριστές</div>'; return; }
  const [Y, M] = d.ym.split('-').map(Number);
  const mn = ['Ιανουάριος', 'Φεβρουάριος', 'Μάρτιος', 'Απρίλιος', 'Μάιος', 'Ιούνιος', 'Ιούλιος', 'Αύγουστος', 'Σεπτέμβριος', 'Οκτώβριος', 'Νοέμβριος', 'Δεκέμβριος'][M - 1];
  const fmtYm = (y, m) => y + '-' + String(m).padStart(2, '0');
  c.innerHTML = crmTabs('targets') + `
  <div style="display:flex;gap:11px;align-items:center;margin-bottom:14px">
    <button class="btn btn-o btn-sm" id="tgP">←</button><b style="font-size:16px;color:var(--ink)">${mn} ${Y}</b>
    ${d.ym < today().slice(0, 7) ? '<button class="btn btn-o btn-sm" id="tgN">→</button>' : ''}
  </div>
  <div class="card"><div class="card-b" style="display:flex;gap:9px;flex-wrap:wrap;align-items:center">
    <select class="inp" id="tgProd" style="max-width:320px">
      ${d.products.map(p => `<option value="${p.id}">${esc(p.group ? p.group + ' › ' : '')}${esc(p.name)}</option>`).join('')}</select>
    <input class="inp" type="number" id="tgU" placeholder="τεμ./μήνα" style="width:110px">
    <input class="inp" type="number" step="0.01" id="tgV" placeholder="€/μήνα" style="width:120px">
    <button class="btn btn-p btn-sm" id="tgSave">Ορισμός στόχου</button></div></div>
  <div class="card"><div class="card-h">Πρόοδος</div>
    <table class="tbl"><thead><tr><th>Προϊόν</th><th>Στόχος</th><th>Πωλήσεις</th><th style="min-width:160px">Πρόοδος</th><th>Αξία</th><th></th></tr></thead><tbody>
    ${d.targets.length ? d.targets.map(t => {
      const pct = t.tUnits > 0 ? Math.round(t.units / t.tUnits * 100) : null;
      return `<tr><td><b>${esc(t.name)}</b></td><td>${t.tUnits}${t.tValue > 0 ? ` <span class="mut">/ ${fmtEur(t.tValue)}</span>` : ''}</td>
        <td><b>${t.units}</b></td>
        <td><div class="bar"><span class="${pct >= 100 ? 'ok' : pct >= 60 ? '' : 'warn'}" style="width:${Math.min(100, pct || 0)}%"></span></div>
          <small class="${pct >= 100 ? 'pill pill-ok' : 'mut'}">${pct === null ? '—' : pct + '%' + (pct >= 100 ? ' 🎉' : '')}</small></td>
        <td>${fmtEur(t.value)}</td>
        <td><button class="btn btn-sm btn-o" data-del="${t.id}">✕</button></td></tr>`;
    }).join('') : '<tr><td colspan="6" class="empty">Δεν έχεις ορίσει στόχους — διάλεξε προϊόν από πάνω</td></tr>'}</tbody></table></div>
  ${d.other.length ? `<div class="card"><div class="card-h">Πωλήσεις μήνα χωρίς στόχο</div>
    ${d.other.map(o => `<div class="trow" style="cursor:default"><span style="flex:1">${esc(o.name)}</span>
      <b>${o.units} τεμ.</b><span class="mut">${fmtEur(o.value)}</span></div>`).join('')}</div>` : ''}`;
  $('#tgP').onclick = () => R.targets(M === 1 ? fmtYm(Y - 1, 12) : fmtYm(Y, M - 1));
  const n = $('#tgN'); if (n) n.onclick = () => R.targets(M === 12 ? fmtYm(Y + 1, 1) : fmtYm(Y, M + 1));
  $('#tgSave').onclick = async () => {
    await api('save_ptarget', {product: +$('#tgProd').value, units: +$('#tgU').value || 0, value: +$('#tgV').value || 0});
    toast('Ο στόχος ορίστηκε'); R.targets(d.ym);
  };
  $$('[data-del]').forEach(b => b.onclick = async () => {
    await api('del_ptarget', {id: +b.dataset.del}); toast('Διαγράφηκε'); R.targets(d.ym);
  });
};

/* ═════════ ΠΕΛΑΤΗΣ 360° ═════════ */
R.client360 = async function (cid) {
  setTop('Πελάτης 360°', 'Το πλήρες ιστορικό ενός πελάτη');
  const c = $('#content');
  c.innerHTML = `<div class="card" style="padding:13px 16px;display:flex;gap:9px">
    <input class="inp" id="c3Q" placeholder="ID, όνομα, επωνυμία ή email… (Enter)" style="max-width:340px"></div>
    <div id="c3Res"></div>`;
  const typeIco = {task: '🟦', task_done: '✅', time: '⏱', time_bill: '💶', ticket: '🎫',
    sc_plus: '🔋', sc_minus: '🪫', offer: '📄', offer_won: '🏆', offer_lost: '❌', payment: '💰', contact: '💬'};
  const show = async (id, months) => {
    $('#c3Res').innerHTML = skel(4);
    const d = await api('client360&id=' + id + (months ? '&months=' + months : ''));
    let lastDay = '';
    $('#c3Res').innerHTML = `
    <h3 style="margin:14px 0 10px;color:var(--ink);display:flex;align-items:center;gap:10px">${esc(d.client.name)}
      <span class="mut" style="font-weight:600;font-size:13px">#${d.client.id} · ${esc(d.client.email)}</span>
      <button class="btn btn-sm btn-o" id="c3Rt" style="margin-left:auto">${I.monitor} Remote συνεδρία</button></h3>
    <div class="grid g4">
      <div class="stat"><b>${d.summary.services}</b><small>Ενεργές υπηρεσίες</small></div>
      <div class="stat ${d.summary.openTasks ? 'info' : ''}"><b>${d.summary.openTasks}</b><small>Ανοιχτά tasks</small></div>
      <div class="stat ${d.summary.openTickets ? 'warn' : ''}"><b>${d.summary.openTickets}</b><small>Ανοιχτά tickets</small></div>
      ${d.summary.scBalance !== null ? `<div class="stat ${d.summary.scBalance > 0 ? 'ok' : 'bad'}"><b>${fmtMin(d.summary.scBalance)}</b><small>Υπόλοιπο προαγοράς</small></div>` : ''}
    </div>
    <div class="grid g2" style="margin-bottom:14px">
      <div class="card"><div class="card-h">${I.box} Υπηρεσίες & προγράμματα <span class="kb-n" style="margin-left:auto">${d.services.length}</span></div>
        <div class="tw" style="overflow-x:auto"><table class="tbl"><thead><tr>
          <th>Πρόγραμμα</th><th>Domain / IP</th><th>Κατάσταση</th><th>Λήγει</th>${d.full ? '<th>Ποσό</th>' : ''}</tr></thead><tbody>
          ${d.services.length ? d.services.map(sv => {
            const days = sv.due ? Math.round((new Date(sv.due) - new Date()) / 86400000) : null;
            return `<tr>
              <td><b style="font-size:12.5px">${esc(sv.product)}</b></td>
              <td class="mut" style="font-size:12px">${esc(sv.domain || sv.ip || '—')}</td>
              <td><span class="pill ${sv.status === 'Active' ? 'pill-ok' : 'pill-warn'}">${sv.status === 'Active' ? 'Ενεργό' : 'Σε αναστολή'}</span></td>
              <td>${sv.due ? `<span class="${days < 15 ? 'pill pill-bad' : days < 30 ? 'pill pill-warn' : ''}">${dShort(sv.due)}${days !== null && days < 30 ? ' (' + days + 'ημ)' : ''}</span>` : '—'}</td>
              ${d.full ? `<td class="mut">${sv.amount ? fmtEur(sv.amount) + '/' + (sv.cycle || '').slice(0, 3) : '—'}</td>` : ''}</tr>`;
          }).join('') : '<tr><td colspan="9" class="empty">Καμία ενεργή υπηρεσία</td></tr>'}</tbody></table></div></div>
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
    const rtb = $('#c3Rt');
    if (rtb) rtb.onclick = () => window.CNP.startRemote(id, d.client.name, 0, {email: d.client.email || ''});
    const pkSel = $('#c3Pk');
    if (pkSel) pkSel.onchange = async () => {
      await api('client_package_set', {client: id, package: +pkSel.value});
      toast('Το πακέτο του πελάτη ενημερώθηκε 🎟');
    };
  };
  $('#c3Q').onkeydown = async e => {
    if (e.key !== 'Enter') return;
    const d = await api('client360&q=' + encodeURIComponent(e.target.value.trim()));
    if (d.client) { show(d.client.id); return; }
    $('#c3Res').innerHTML = d.matches.length ? `<div class="card">${d.matches.map(m =>
      `<div class="trow" data-c="${m.id}"><b>${esc(m.name)}</b><span class="mut">#${m.id} · ${esc(m.email)}</span></div>`).join('')}</div>`
      : '<div class="empty">Δεν βρέθηκε πελάτης</div>';
    $$('#c3Res [data-c]').forEach(r => r.onclick = () => show(+r.dataset.c));
  };
  if (cid) show(+cid);
};

/* ═════════ ΚΕΡΔΟΦΟΡΙΑ ═════════ */
R.profit = async function () {
  setTop('Κερδοφορία', 'Έσοδα − κόστος εργασίας − έξοδα, ανά πελάτη');
  const c = $('#content');
  const f = R.profit._f = R.profit._f || {};
  c.innerHTML = skel(4);
  const qs = Object.entries(f).filter(([, v]) => v).map(([k, v]) => k + '=' + v).join('&');
  const d = await api('profit' + (qs ? '&' + qs : '')).catch(() => null);
  if (!d) { c.innerHTML = '<div class="empty"><div class="big">${I.lock}</div>Μόνο για διαχειριστές</div>'; return; }
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
          <select class="inp" data-mAdm="${t.id}" style="flex:1;min-width:110px;padding:6px 9px;font-size:12px">
            ${S.boot.admins.map(a => `<option value="${a.id}">${esc(a.name)}</option>`).join('')}</select>
          <select class="inp" data-mRole="${t.id}" style="width:120px;padding:6px 9px;font-size:12px">
            <option value="">— ρόλος —</option>${d.roles.map(r => `<option>${esc(r)}</option>`).join('')}</select>
          <label style="font-size:11px;display:flex;align-items:center;gap:3px"><input type="checkbox" data-mLead="${t.id}">${I.crown} </label>
          <button class="btn btn-sm btn-o" data-mGo="${t.id}">+</button>
          <button class="btn btn-sm btn-o" data-tDel="${t.id}" style="margin-left:auto;color:var(--bad)">Διαγραφή</button></div>` : ''}
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
    $$('[data-mGo]').forEach(b => b.onclick = async () => {
      const t = b.dataset.mGo;
      await api('team_member_add', {team: +t, admin: +$(`[data-mAdm="${t}"]`).value,
        role: $(`[data-mRole="${t}"]`).value, leader: $(`[data-mLead="${t}"]`).checked});
      toast('Προστέθηκε'); R.teams();
    });
    $$('[data-rm]').forEach(b => b.onclick = async () => {
      const [t, a] = b.dataset.rm.split(':');
      await api('team_member_del', {team: +t, admin: +a}); R.teams();
    });
    $$('[data-tDel]').forEach(b => b.onclick = async () => {
      if (!(await cnpConfirm('Διαγραφή ομάδας; (τα μέλη δεν διαγράφονται)', {danger: true, ok: I.trash + ' Διαγραφή'}))) return;
      await api('del_team', {id: +b.dataset.tDel}); toast('Διαγράφηκε'); R.teams();
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
      ${p.offerId ? '<span class="pill pill-mut" title="Από προσφορά">${I.briefcase} </span>' : ''}</td>
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
  c.innerHTML = `
  ${d.canManage ? `<div style="margin-bottom:12px"><button class="btn btn-p" id="prNew">${I.plus} Νέο project</button>
    <button class="btn btn-o" id="prRec" style="margin-left:8px">${I.repeat} Επαναλαμβανόμενα</button></div>` : ''}
  <div class="card"><div class="card-h">${I.rocket} Έργα πελατών <span class="mut" style="font-weight:400;font-size:11.5px">σχεδιασμένα για συγκεκριμένη απαίτηση — με budget, εκτίμηση & deadline</span></div>
    <table class="tbl"><thead><tr>
    <th>Έργο</th><th>Πελάτης</th><th>Κατάσταση</th><th>Deadline</th><th>Budget</th><th>Παραδοτέα</th><th>Χρόνος / εκτίμηση</th><th>Πρόοδος</th>${d.canManage ? '<th></th>' : ''}</tr></thead>
    <tbody>${clientPjs.length ? clientPjs.map(cRow).join('') : `<tr><td colspan="9" class="empty">Κανένα έργο πελάτη — φτιάξε ένα με «Νέο project» ή από κερδισμένη προσφορά 💼</td></tr>`}</tbody></table></div>
  <div class="card"><div class="card-h">${I.building} Λειτουργικά projects <span class="mut" style="font-weight:400;font-size:11.5px">τμήματα & καθημερινή λειτουργία (tickets)</span></div>
    <table class="tbl"><thead><tr>
    <th>Project</th><th>Πελάτης</th><th>Κατάσταση</th><th>Ανοιχτά</th><th>Πρόοδος</th><th>Τάση 7ημ</th>${d.canManage ? '<th></th>' : ''}</tr></thead>
    <tbody>${roots.map(p => row(p, 0) + kids(p.id).map(k => row(k, 1)).join('')).join('')}</tbody></table></div>
  <div id="prExtra"></div>`;
  if (!d.canManage) return;
  const openProj = p => {
    closeDrawer();
    p = p || {visible: true, members: [], teams: []};
    const ovl = document.createElement('div'); ovl.className = 'ovl'; ovl.onclick = closeDrawer;
    const dr = document.createElement('div'); dr.className = 'drawer';
    dr.innerHTML = `
    <div class="drawer-h"><h2>${p.id ? esc(p.name) : 'Νέο project'}</h2><button class="drawer-x" id="dX">✕</button></div>
    <div class="drawer-b"><div class="card"><div class="card-b">
      <label class="lbl">Όνομα</label><input class="inp" id="pjName" value="${esc(p.name || '')}">
      <div class="frow" style="margin-top:11px">
        <div><label class="lbl">Πελάτης</label><input class="inp" id="pjCli" list="pjCliL" autocomplete="off"
          value="${esc(p.clientName ? p.clientName + ' (#' + p.client + ')' : '')}"><datalist id="pjCliL"></datalist>
          <input type="hidden" id="pjCliId" value="${p.client || ''}"></div>
        <div><label class="lbl">Τμήμα (auto-tasks)</label><select class="inp" id="pjDept"><option value="">—</option>
          ${d.depts.map(dp => `<option value="${dp.id}" ${dp.id === p.dept ? 'selected' : ''}>${esc(dp.name)}</option>`).join('')}</select></div>
        <div><label class="lbl">Γονικό</label><select class="inp" id="pjPar"><option value="">— κορυφαίο —</option>
          ${roots.filter(r => r.id !== p.id).map(r => `<option value="${r.id}" ${r.id === p.parent ? 'selected' : ''}>${esc(r.name)}</option>`).join('')}</select></div>
        <div><label class="lbl">Χρώμα</label><input class="inp" type="color" id="pjColor" value="${p.color || '#0090dd'}" style="height:40px;padding:4px"></div>
        <div><label class="lbl">Κατάσταση</label><select class="inp" id="pjPs"><option value="">—</option>
          ${Object.entries(psL).map(([k, v]) => `<option value="${k}" ${k === p.pstatus ? 'selected' : ''}>${v}</option>`).join('')}</select></div>
        <div><label class="lbl">Υγεία</label><select class="inp" id="pjH"><option value="">—</option>
          <option value="green" ${p.health === 'green' ? 'selected' : ''}>🟢 Καλά</option>
          <option value="yellow" ${p.health === 'yellow' ? 'selected' : ''}>🟡 Προσοχή</option>
          <option value="red" ${p.health === 'red' ? 'selected' : ''}>🔴 Πρόβλημα</option></select></div>
        <div><label class="lbl">Τύπος</label><select class="inp" id="pjKind">
          <option value="dept" ${p.kind !== 'client' ? 'selected' : ''}>Λειτουργικό (τμήμα)</option>
          <option value="client" ${p.kind === 'client' ? 'selected' : ''}>Έργο πελάτη</option></select></div>
        <div><label class="lbl">${I.coin} Budget €</label><input class="inp" id="pjBud" value="${p.budget ?? ''}" placeholder="π.χ. 3000"></div>
        <div><label class="lbl">⏱ Εκτίμηση ωρών</label><input class="inp" id="pjEst" value="${p.estHours ?? ''}" placeholder="π.χ. 40"></div>
        <div><label class="lbl">Έναρξη</label><input type="date" class="inp" id="pjStart" value="${p.start || ''}"></div>
        <div><label class="lbl">Deadline</label><input type="date" class="inp" id="pjDue" value="${p.due || ''}"></div>
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
      <div style="margin-top:14px"><button class="btn btn-p" id="pjSave">Αποθήκευση</button></div>
    </div></div>
    ${p.id ? `<div class="card"><div class="card-h">${I.clipboard} Τι περιλαμβάνει το έργο — παραδοτέα</div>
      <div class="card-b" id="pjTodos"><div class="skel" style="height:50px"></div></div></div>` : ''}
    ${p.id ? `<div class="card"><div class="card-h">${I.link} Δημόσιο link για τον πελάτη</div>
      <div class="card-b" id="pjShare"><div class="skel" style="height:50px"></div></div></div>` : ''}</div>`;
    document.body.append(ovl, dr);
    requestAnimationFrame(() => { ovl.classList.add('show'); dr.classList.add('show'); });
    $('#dX').onclick = closeDrawer;
    clientAuto('pjCli', 'pjCliL', 'pjCliId');
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
    $('#pjSave', dr).onclick = async () => {
      await api('save_project', {id: p.id || 0, name: $('#pjName').value, client: +$('#pjCliId').value || 0,
        dept: +$('#pjDept').value || 0, parent: +$('#pjPar').value || 0, color: $('#pjColor').value,
        pstatus: $('#pjPs').value, health: $('#pjH').value, visible: $('#pjVis').checked,
        kind: $('#pjKind').value, budget: $('#pjBud').value.trim(), estHours: $('#pjEst').value.trim(),
        start: $('#pjStart').value || null, due: $('#pjDue').value || null,
        members: $$('.pjM:checked', dr).map(x => +x.value), teams: $$('.pjT:checked', dr).map(x => +x.value)});
      toast('Αποθηκεύτηκε'); closeDrawer();
      const b = await api('boot'); S.boot = b; R.projects();
    };
  };
  $('#prNew').onclick = () => openProj(null);
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
  const maxC = Math.max(1, ...d.pipe.map(p => p.count));
  const leadRow = l => `<div class="set-row" data-lgo="${l.id}" style="cursor:pointer">
    <div style="flex:1;min-width:0"><b style="font-size:12.5px">${esc(l.name)}</b>
      <span class="mut" style="font-size:11px">${l.assignee ? '· ' + esc(adminName(l.assignee)) : ''}</span></div>
    ${l.value ? `<span class="pill pill-ok">${fmtEur(l.value)}</span>` : ''}
    ${l.next ? `<span class="pill pill-bad">${I.bell} ${dShort(l.next)}</span>` : '<span class="pill pill-warn">${I.snow} χωρίς ενέργεια</span>'}</div>`;
  const pctT = d.target > 0 ? Math.min(100, Math.round(d.wonValueMonth / d.target * 100)) : 0;
  c.innerHTML = crmTabs('crmov') + `
  <div class="grid g4" style="margin-bottom:16px">
    <div class="stat info"><b>${d.openCount}</b><small>Ανοιχτά leads στο pipeline</small></div>
    <div class="stat ok"><b>${fmtEur(d.openValue)}</b><small>Αξία ανοιχτού pipeline</small></div>
    <div class="stat ${d.winRate === null ? 'info' : d.winRate >= 50 ? 'ok' : 'warn'}"><b>${d.winRate === null ? '—' : d.winRate + '%'}</b><small>Win rate (κερδ. / κλεισμένα)</small></div>
    <div class="stat ${pctT >= 100 ? 'ok' : 'info'}"><b>${fmtEur(d.wonValueMonth)}</b>
      <small>Πωλήσεις μήνα${d.target > 0 ? ' · ' + pctT + '% στόχου' : ''}</small></div>
  </div>
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
  $$('[data-pgo]').forEach(r => r.onclick = () => {
    S.project = +r.dataset.pgo;
    window.CNP.go('board');
  });
};
