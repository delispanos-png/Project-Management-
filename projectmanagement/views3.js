/* ═══════════ CloudOn Projects — Inbox / Ρυθμίσεις / ⌘K ═══════════ */
'use strict';
const {S, api, esc, fmtMin, fmtEur, dShort, tShort, today, toast, setTop,
  adminName, adminIni, dnd, I, openTask, closeDrawer, go, cnpConfirm, cnpPrompt, $, $$} = window.CNP;
const R = window.R;

/* ═════════ TICKET INBOX ═════════ */
R.inbox = async function (openId) {
  setTop('Tickets', 'Όλη η υποστήριξη σε ένα inbox');
  const c = $('#content');
  const st = R.inbox._st = R.inbox._st || {view: 'open', q: '', sel: null};
  if (openId) st.sel = +openId;
  const tabs = [['open', 'Ανοιχτά'], ['mine', 'Δικά μου'], ['unassigned', 'Χωρίς ανάθεση'],
    ['waiting', 'Περιμένουν'], ['closed', 'Κλειστά']];
  c.innerHTML = `
  <div class="inbox">
    <div class="ib-left">
      <div class="ib-tabs">${tabs.map(([k, l]) =>
        `<button class="ib-tab ${st.view === k ? 'on' : ''}" data-v="${k}">${l}</button>`).join('')}</div>
      <input class="inp" id="ibQ" placeholder="Αναζήτηση… (Enter)" value="${esc(st.q)}"
        style="margin:9px 10px;width:calc(100% - 20px)">
      <div style="display:flex;gap:6px;margin:0 10px 6px">
        <select class="inp" id="ibFArea" style="flex:1;font-size:11.5px;padding:5px 7px"><option value="">Όλες οι περιοχές</option></select>
        <select class="inp" id="ibFCause" style="flex:1;font-size:11.5px;padding:5px 7px"><option value="">Όλες οι ρίζες</option></select>
      </div>
      <div class="ib-list" id="ibList"><div class="skel" style="height:70px;margin:10px"></div></div>
    </div>
    <div class="ib-right" id="ibConv"><div class="empty" style="margin-top:80px"><div class="big">${I.ticket}</div>Διάλεξε ticket</div></div>
  </div>`;
  $$('.ib-tab').forEach(b => b.onclick = () => { st.view = b.dataset.v; st.sel = null; R.inbox(); });
  const fillCat = (el, list, sel) => { if (!el) return; list.forEach(x => el.insertAdjacentHTML('beforeend', `<option value="${x.id}" ${x.id == sel ? 'selected' : ''}>${esc(x.name)}</option>`)); };
  $('#ibQ').onkeydown = e => { if (e.key === 'Enter') { st.q = e.target.value.trim(); R.inbox(); } };
  const d = await api('tickets&view=' + st.view + (st.q ? '&q=' + encodeURIComponent(st.q) : '')
    + (st.area ? '&area=' + st.area : '') + (st.cause ? '&cause=' + st.cause : ''));
  if (d.cats) { fillCat($('#ibFArea'), d.cats.area, st.area); fillCat($('#ibFCause'), d.cats.cause, st.cause); }
  const fa = $('#ibFArea'), fc = $('#ibFCause');
  if (fa) fa.onchange = () => { st.area = +fa.value || 0; st.sel = null; R.inbox(); };
  if (fc) fc.onchange = () => { st.cause = +fc.value || 0; st.sel = null; R.inbox(); };
  const listEl = $('#ibList'); if (!listEl) return;
  listEl.innerHTML = d.tickets.length ? d.tickets.map(t => `
    <div class="ib-row ${st.sel === t.id ? 'on' : ''}" data-t="${t.id}">
      <div style="display:flex;gap:7px;align-items:baseline">
        ${t.unread ? '<span class="dot" style="background:var(--brand)"></span>' : ''}
        <b style="flex:1;font-size:12.5px">${esc(t.client || '—')}</b>
        <span class="mut" style="font-size:10.5px">${tShort(t.last)}</span></div>
      <div style="font-size:12px;margin:2px 0 4px;color:var(--txt)">${esc(t.title)}</div>
      <div style="display:flex;gap:5px;flex-wrap:wrap">
        <span class="pill pill-info" style="font-size:9.5px">${esc(t.status)}</span>
        ${t.waiting ? '<span class="pill pill-warn" style="font-size:9.5px">περιμένει</span>' : ''}
        ${t.slaDue ? `<span class="pill ${new Date(t.slaDue.replace(' ', 'T')) < new Date() ? 'pill-bad' : 'pill-warn'}" style="font-size:9.5px">SLA ${tShort(t.slaDue)}</span>` : ''}
        ${t.flag ? `<span class="ava" style="width:17px;height:17px;font-size:8.5px">${esc(adminIni(t.flag))}</span>` : ''}
      </div></div>`).join('')
    : '<div class="empty" style="padding:30px 10px">Κανένα ticket εδώ 🎉</div>';
  $$('.ib-row').forEach(r => r.onclick = () => { st.sel = +r.dataset.t;
    $$('.ib-row').forEach(x => x.classList.toggle('on', x === r)); loadConv(st.sel); });
  if (st.sel) loadConv(st.sel);

  async function loadConv(id) {
    const conv = $('#ibConv');
    conv.innerHTML = '<div class="skel" style="height:200px;margin:14px"></div>';
    const dd = await api('ticket&id=' + id).catch(() => null);
    if (!dd) { conv.innerHTML = '<div class="empty">Δεν έχεις πρόσβαση</div>'; return; }
    const t = dd.ticket;
    conv.innerHTML = `
    <div class="ib-head">
      <div style="flex:1;min-width:0">
        <b style="font-size:14.5px;color:var(--ink)">#${esc(t.tid)} — ${esc(t.title)}</b>
        <div class="mut" style="font-size:11.5px">${esc(t.client || '')} ${t.email ? '· ' + esc(t.email) : ''}
          ${t.slaDue ? ` · <span style="color:var(--bad);font-weight:700">SLA έως ${tShort(t.slaDue)}</span>` : ''}</div>
      </div>
      <select class="inp" id="ibStatus" style="width:auto;padding:6px 10px;font-size:12px">
        ${dd.statuses.map(s => `<option ${s === t.status ? 'selected' : ''}>${esc(s)}</option>`).join('')}
        <option value="Closed" ${t.status === 'Closed' ? 'selected' : ''}>Closed</option></select>
      ${S.boot.me.full ? `<select class="inp" id="ibFlag" style="width:auto;padding:6px 10px;font-size:12px">
        <option value="0">— ανάθεση —</option>
        ${S.boot.admins.map(a => `<option value="${a.id}" ${a.id === t.flag ? 'selected' : ''}>${esc(a.name)}</option>`).join('')}</select>` : ''}
      ${S.boot.me.canReply ? `<button class="btn btn-sm ${(dd.class && (dd.class.area || dd.class.cause)) ? 'btn-p' : 'btn-o'}" id="ibClassify" title="Κατηγοριοποίηση (ρίζα προβλήματος)${(dd.class && (dd.class.area || dd.class.cause)) ? ' — ✓ ταξινομημένο' : ''}">${I.tag}</button>` : ''}
      ${dd.quota ? `<span class="pill ${dd.quota.over ? 'pill-bad' : dd.quota.used > dd.quota.quota * 0.8 ? 'pill-warn' : 'pill-mut'}"
        title="Όριο ομάδας πελάτη — μήνας: ${dd.quota.used}/${dd.quota.quota}${dd.quota.email.q ? ' · email ' + dd.quota.email.u + '/' + dd.quota.email.q : ''}${dd.quota.phone.q ? ' · τηλ. ' + dd.quota.phone.u + '/' + dd.quota.phone.q : ''}">
        ${I.ticket} ${dd.quota.used}/${dd.quota.quota}${dd.quota.over ? ' ΥΠΕΡΒΑΣΗ' : ''}</span>` : ''}
      ${t.clientId ? `<button class="btn btn-sm btn-o" id="ibRemote" title="Remote συνεδρία με χρονομέτρηση">${I.monitor}</button>` : ''}
      ${dd.task ? `<button class="btn btn-sm btn-o" id="ibTask" title="Άνοιγμα task">${I.clipboard}</button>` : ''}
    </div>
    ${(dd.suggest && (dd.suggest.kb.length || dd.suggest.similar.length)) ? `
    <div style="margin:9px 13px 0;padding:10px 13px;border-radius:11px;background:color-mix(in srgb, var(--warn) 9%, transparent);border:1px solid color-mix(in srgb, var(--warn) 30%, transparent)">
      <b style="font-size:12px;color:var(--ink)">${I.bulb} Το έχουμε ξαναδεί — πιθανές λύσεις:</b>
      <div style="display:flex;flex-direction:column;gap:5px;margin-top:6px">
        ${dd.suggest.kb.map(k => `
          <details><summary style="cursor:pointer;font-size:12px"><b>${I.book} ${esc(k.title)}</b> <span class="mut">(τράπεζα λύσεων)</span></summary>
            <div style="white-space:pre-wrap;font-size:12px;padding:7px 4px;color:var(--txt)" data-kbuse="${k.id}">${esc(k.solution)}</div></details>`).join('')}
        ${dd.suggest.similar.map(x => `
          <div style="font-size:12px;cursor:pointer" data-simgo="${x.id}">${I.ticket} <b>#${esc(x.tid)}</b> ${esc(x.title)}
            <span class="mut">· ${esc(x.client || '')} · λύθηκε ${dShort(x.last)}</span> <span class="mut">→</span></div>`).join('')}
      </div>
    </div>` : ''}
    ${(dd.class && (dd.class.area || dd.class.cause)) ? (() => {
      const A = (dd.cats.area || []).find(x => x.id === dd.class.area);
      const C = (dd.cats.cause || []).find(x => x.id === dd.class.cause);
      return `<div style="padding:6px 13px 0">
        <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
          <span class="mut" style="font-size:11px">${I.tag} Κατηγορία:</span>
          ${A ? `<span class="pill" style="background:${A.color}22;color:${A.color}">${esc(A.name)}</span>` : ''}
          ${C ? `<span class="pill" style="background:${C.color}22;color:${C.color}">⟶ ${esc(C.name)}</span>` : ''}
          ${dd.class.by ? `<span class="mut" style="font-size:10px">· ${esc(dd.class.by)}</span>` : ''}</div>
        ${dd.class.note ? `<div class="sol-html" style="margin-top:6px;padding:9px 12px;background:var(--line);border-radius:9px">
          <b style="font-size:11px;color:var(--mut);text-transform:uppercase;letter-spacing:.3px">Λύση</b><div style="margin-top:3px">${dd.class.note}</div></div>` : ''}</div>`;
    })() : ''}
    <div class="ib-msgs" id="ibMsgs">
      ${dd.conv.map(m => `<div class="ib-msg ${m.admin ? 'me' : ''}">
        <div class="ib-msg-h">${esc(m.by || '—')}${m.admin ? ' <span class="pill pill-info" style="font-size:9px">team</span>' : ''}
          <span class="mut">${tShort(m.at)}</span></div>
        <div class="ib-msg-b">${esc(m.body).replace(/\n/g, '<br>')}</div>
        ${(m.att || []).length ? `<div style="margin-top:7px;display:flex;gap:6px;flex-wrap:wrap">${m.att.map(a =>
          `<a class="pill pill-info" style="text-decoration:none" href="api.php?a=ticket_att&ticket=${id}&rid=${a.rid}&i=${a.i}">${I.clip} ${esc(a.name)}</a>`).join('')}</div>` : ''}</div>`).join('')}
      ${dd.notes.length ? `<div class="ib-notes-sep">${I.lock} Εσωτερικές σημειώσεις — αόρατες στον πελάτη</div>` +
        dd.notes.map(n => `<div class="ib-msg note ${n.byId === S.boot.me.id ? 'me' : ''}">
          <div class="ib-msg-h">${esc(n.by)}${n.to !== null ? ` <span class="pill pill-warn" style="font-size:9px">προς: ${n.to === -1 ? 'Διαχειριστές' : esc(adminName(n.to))}</span>` : ''}
            <span class="mut">${tShort(n.at)}</span></div>
          <div class="ib-msg-b">${esc(n.body).replace(/\n/g, '<br>')}</div></div>`).join('') : ''}
    </div>
    <div class="ib-compose">
      <div class="ib-mode">
        ${S.boot.me.canReply ? `<button class="ib-mbtn on" data-m="reply">${I.send} Απάντηση στον πελάτη</button>` : ''}
        <button class="ib-mbtn ${S.boot.me.canReply ? '' : 'on'}" data-m="note">${I.lock} Εσωτερική σημείωση</button>
        ${!S.boot.me.canReply ? '<span class="mut" style="font-size:11px;align-self:center">Την απάντηση στον πελάτη τη στέλνει ο επικεφαλής/διαχειριστής</span>' : ''}
        <select class="inp" id="ibNoteTo" style="display:none;width:auto;padding:5px 9px;font-size:11.5px;margin-left:auto">
          <option value="">— όλους —</option><option value="-1">📣 Διαχειριστές</option>
          ${S.boot.admins.filter(a => a.id !== S.boot.me.id).map(a => `<option value="${a.id}">${esc(a.name)}</option>`).join('')}</select>
      </div>
      <textarea class="inp" id="ibBody" rows="3" placeholder="Γράψε απάντηση… (Ctrl+Enter για αποστολή)"></textarea>
      <div style="display:flex;gap:8px;margin-top:8px;align-items:center;flex-wrap:wrap">
        <button class="btn btn-p btn-sm" id="ibSend">Αποστολή</button>
        <select class="inp" id="ibCanned" style="width:auto;padding:5px 10px;font-size:12px">
          <option value="">Έτοιμες απαντήσεις…</option></select>
        <button class="btn btn-o btn-sm" id="ibAI" title="Πρόταση απάντησης με AI">${I.sparkle} AI απάντηση</button>
        <button class="btn btn-o btn-sm" id="ibAISum" title="Σύνοψη ticket με AI">${I.fileText} Σύνοψη</button>
        <label class="btn btn-o btn-sm" style="cursor:pointer" title="Επισύναψη αρχείων" id="ibAttWrap">${I.clip}
          <input type="file" id="ibFiles" multiple style="display:none"></label>
        <span id="ibAttInfo" class="mut" style="font-size:11px"></span>
        <label style="font-size:11.5px;display:flex;gap:5px;align-items:center;margin-left:auto" id="ibCloseWrap">
          <input type="checkbox" id="ibClose"> και κλείσιμο ticket</label>
      </div>
      <div id="ibAIBox" style="display:none;margin-top:9px;padding:11px 14px;border-radius:11px;background:#7b5cd61a;font-size:12.5px;white-space:pre-wrap"></div>
    </div>`;
    const msgs = $('#ibMsgs'); msgs.scrollTop = msgs.scrollHeight;
    const cbt = $('#ibClassify');
    if (cbt) cbt.onclick = () => classifyTicket(id, dd, () => loadConv(id));
    const rbt = $('#ibRemote');
    if (rbt) rbt.onclick = () => window.CNP.startRemote(t.clientId, t.client || ('Πελάτης #' + t.clientId), id,
      {ticketLabel: '#' + t.tid + ' — ' + (t.title || '').slice(0, 40), email: t.email || ''});
    if (!S.boot.me.canReply) {   // χειριστής: μόνο εσωτερικά
      mode = 'note';
      $('#ibNoteTo').style.display = '';
      $('#ibCloseWrap').style.display = 'none';
      $('#ibAttWrap').style.display = 'none';
      const ai = $('#ibAI'); if (ai) ai.style.display = 'none';
      $('#ibBody').placeholder = 'Σημείωση προς την ομάδα…';
    }
    $$('[data-simgo]', conv).forEach(x => x.onclick = () => { st.sel = +x.dataset.simgo; loadConv(st.sel); });
    $$('details [data-kbuse]', conv).forEach(x => {
      const det = x.closest('details');
      det.addEventListener('toggle', () => { if (det.open) api('kb_use', {id: +x.dataset.kbuse}); }, {once: true});
    });
    let mode = 'reply';
    $$('.ib-mbtn').forEach(b => b.onclick = () => { mode = b.dataset.m;
      $$('.ib-mbtn').forEach(x => x.classList.toggle('on', x === b));
      $('#ibNoteTo').style.display = mode === 'note' ? '' : 'none';
      $('#ibCloseWrap').style.display = mode === 'note' ? 'none' : '';
      $('#ibAttWrap').style.display = mode === 'note' ? 'none' : '';
      $('#ibBody').placeholder = mode === 'note' ? 'Σημείωση προς την ομάδα…' : 'Γράψε απάντηση…'; });
    const fInp = $('#ibFiles');
    fInp.onchange = () => {
      $('#ibAttInfo').textContent = fInp.files.length
        ? [...fInp.files].map(f => f.name).join(', ') : '';
    };
    const send = async () => {
      const body = $('#ibBody').value.trim(); if (!body) return;
      if (mode === 'reply') {
        let r;
        if (fInp.files.length) {
          const fd = new FormData();
          fd.append('ticket', id); fd.append('body', body);
          fd.append('status', $('#ibClose').checked ? 'Closed' : '');
          [...fInp.files].forEach(f => fd.append('files[]', f));
          r = await fetch('api.php?a=ticket_reply', {method: 'POST', body: fd, credentials: 'same-origin'})
            .then(x => x.json()).then(x => x.error ? {err: x.error} : x).catch(e => ({err: e.message}));
        } else {
          r = await api('ticket_reply', {ticket: id, body,
            status: $('#ibClose').checked ? 'Closed' : ''}).catch(e => ({err: e.message}));
        }
        if (r.err) { toast(r.err, true); return; }
        toast('Η απάντηση στάλθηκε στον πελάτη' + (fInp.files.length ? ' με ' + fInp.files.length + ' συνημμένα' : ''));
        if ($('#ibClose').checked) kbCapture(id);
      } else {
        await api('ticket_note', {ticket: id, body,
          to: $('#ibNoteTo').value === '' ? null : +$('#ibNoteTo').value});
        toast('Η σημείωση καταχωρήθηκε');
      }
      loadConv(id);
    };
    $('#ibSend').onclick = send;
    $('#ibBody').onkeydown = e => { if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) send(); };
    $('#ibStatus').onchange = async e => {
      await api('ticket_update', {ticket: id, status: e.target.value});
      toast('Κατάσταση: ' + e.target.value);
      if (e.target.value === 'Closed') kbCapture(id);
    };
    const fl = $('#ibFlag'); if (fl) fl.onchange = async e => {
      await api('ticket_update', {ticket: id, flag: +e.target.value});
      toast(+e.target.value ? 'Ανατέθηκε: ' + adminName(+e.target.value) : 'Αφαιρέθηκε η ανάθεση');
    };
    const tb = $('#ibTask'); if (tb) tb.onclick = () => openTask(dd.task);
    // canned responses (lazy cache)
    if (!R.inbox._canned) {
      R.inbox._canned = (await api('canned')).canned;
    }
    const cSel = $('#ibCanned');
    cSel.innerHTML += R.inbox._canned.map(x => `<option value="${x.id}">${esc(x.title)}</option>`).join('');
    cSel.onchange = () => {
      const cn = R.inbox._canned.find(x => x.id === +cSel.value);
      if (cn) {
        const me1 = S.boot.me.name.split(' ')[0];
        $('#ibBody').value += (($('#ibBody').value ? '\n' : '') +
          cn.body.replaceAll('{πελάτης}', t.client || '').replaceAll('{εγώ}', me1));
        $('#ibBody').focus();
      }
      cSel.value = '';
    };
    // AI
    $('#ibAI').onclick = async () => {
      $('#ibAI').textContent = '✨ Σκέφτομαι…'; $('#ibAI').disabled = true;
      const r = await api('ai_suggest', {ticket: id}).catch(e => ({err: e.message}));
      $('#ibAI').textContent = '✨ AI απάντηση'; $('#ibAI').disabled = false;
      if (r.err) { toast(r.err, true); return; }
      $('#ibBody').value = r.text; $('#ibBody').focus();
      toast('Η πρόταση μπήκε στο πεδίο — έλεγξέ την πριν τη στείλεις');
    };
    $('#ibAISum').onclick = async () => {
      const box = $('#ibAIBox');
      box.style.display = ''; box.textContent = 'Ετοιμάζω σύνοψη…';
      const r = await api('ai_summary', {ticket: id}).catch(e => ({err: e.message}));
      if (r.err) { box.style.display = 'none'; toast(r.err, true); return; }
      box.textContent = r.text;
    };
  }
};

/* 🏷️ Κατηγοριοποίηση ticket σε δική μας ταξινομία (περιοχή + ρίζα) */
function classifyTicket(id, dd, done) {
  const A = dd.cats.area || [], C = dd.cats.cause || [];
  const cur = dd.class || {};
  const ovl = document.createElement('div'); ovl.className = 'ovl show'; ovl.style.zIndex = 300;
  ovl.onclick = e => { if (e.target === ovl) ovl.remove(); };
  const opts = (list, sel) => '<option value="">—</option>' + list.map(x => `<option value="${x.id}" ${x.id === sel ? 'selected' : ''}>${esc(x.name)}</option>`).join('');
  const rteBtn = (cmd, label, title, arg) => `<button type="button" class="rte-b" data-cmd="${cmd}"${arg ? ` data-arg="${arg}"` : ''} title="${title}">${label}</button>`;
  ovl.innerHTML = `<div class="pal-box" style="margin:9vh auto 0;max-width:600px" onclick="event.stopPropagation()">
    <div style="padding:20px 22px;max-height:82vh;overflow:auto">
      <div style="display:flex;align-items:center;gap:8px">
        <b style="font-size:15.5px;color:var(--ink)">${I.tag} Κατηγοριοποίηση ticket</b>
        <button class="btn btn-sm btn-o" id="clAI" style="margin-left:auto">${I.sparkle} Πρόταση AI</button></div>
      <div class="mut" style="font-size:12px;margin-top:3px">#${esc(dd.ticket.tid)} — βάλ' το εκεί που ΠΡΕΠΕΙ, όχι όπου το έβαλε ο πελάτης. Βοηθά να βρίσκουμε ρίζες προβλημάτων.</div>
      <div class="frow" style="margin-top:13px">
        <div><label class="lbl">${I.box} Περιοχή / Προϊόν</label>
          <select class="inp" id="clA">${opts(A, cur.area)}</select></div>
        <div><label class="lbl">${I.lab} Ρίζα προβλήματος / Τρόπος επίλυσης</label>
          <select class="inp" id="clC">${opts(C, cur.cause)}</select></div>
      </div>
      <label class="lbl" style="margin-top:13px">Περιγραφή λύσης — τι φταίει & πώς λύθηκε</label>
      <div class="rte-wrap">
        <div class="rte-tb">
          ${rteBtn('bold', '<b>B</b>', 'Έντονα (Ctrl+B)')}
          ${rteBtn('italic', '<i>I</i>', 'Πλάγια (Ctrl+I)')}
          ${rteBtn('underline', '<u>U</u>', 'Υπογράμμιση (Ctrl+U)')}
          <span class="rte-sep"></span>
          ${rteBtn('insertUnorderedList', '&bull;&nbsp;Λίστα', 'Κουκκίδες')}
          ${rteBtn('insertOrderedList', '1.&nbsp;Λίστα', 'Αρίθμηση')}
          <span class="rte-sep"></span>
          ${rteBtn('formatBlock', 'H', 'Επικεφαλίδα', 'h3')}
          ${rteBtn('formatBlock', '&ldquo;&rdquo;', 'Παράθεση', 'blockquote')}
          ${rteBtn('__code', '&lt;/&gt;', 'Κώδικας')}
          <span class="rte-sep"></span>
          ${rteBtn('__link', I.link, 'Σύνδεσμος')}
          ${rteBtn('removeFormat', '✕', 'Καθαρισμός μορφοποίησης')}
        </div>
        <div class="rte" id="clN" contenteditable="true" data-ph="π.χ. Το πρόβλημα ήταν λάθος MX record στον registrar. Λύση: διόρθωση MX σε mail.cloudon.gr, propagation ~2h…">${cur.note || ''}</div>
      </div>
      <div id="clKb" style="margin-top:12px"></div>
      <div style="display:flex;gap:9px;margin-top:15px;justify-content:flex-end">
        <button class="btn btn-o" id="clNo">Άκυρο</button>
        <button class="btn btn-p" id="clGo">Αποθήκευση</button></div>
    </div></div>`;
  document.body.appendChild(ovl);
  const ed = ovl.querySelector('#clN');
  // rich-text toolbar
  ovl.querySelectorAll('.rte-b').forEach(b => b.onclick = () => {
    ed.focus();
    const cmd = b.dataset.cmd;
    if (cmd === '__link') {
      cnpPrompt('Διεύθυνση συνδέσμου (URL):', {ok: 'Εισαγωγή', placeholder: 'https://…'}).then(u => {
        if (u) { document.execCommand('createLink', false, /^https?:|^mailto:/.test(u) ? u : 'https://' + u); }
      });
    } else if (cmd === '__code') {
      document.execCommand('formatBlock', false, 'pre');
    } else {
      document.execCommand(cmd, false, b.dataset.arg || null);
    }
  });
  ovl.querySelector('#clNo').onclick = () => ovl.remove();
  const nmeOf = (list, id) => (list.find(x => x.id === id) || {}).name || '';
  const refreshKb = async () => {
    const aName = nmeOf(A, +ovl.querySelector('#clA').value);
    const cName = nmeOf(C, +ovl.querySelector('#clC').value);
    const box = ovl.querySelector('#clKb');
    if (!aName && !cName) { box.innerHTML = ''; return; }
    const r = await api('kb_match&q=' + encodeURIComponent(aName + ' ' + cName)).catch(() => ({items: []}));
    box.innerHTML = `<div class="mut" style="font-size:11px;margin-bottom:5px">${I.book} Σχετικές λύσεις γνώσης
      <a href="#" id="clNewKb" style="float:right;font-weight:700">+ Νέα λύση για αυτή τη ρίζα</a></div>`
      + (r.items.length ? r.items.map(k => `<details style="font-size:12px;margin-bottom:3px">
          <summary style="cursor:pointer"><b>${I.bulb} ${esc(k.title)}</b></summary>
          <div style="white-space:pre-wrap;padding:5px 4px;color:var(--txt)">${esc(k.solution)}</div></details>`).join('')
        : '<div class="mut" style="font-size:11.5px">Καμία ακόμη — γράψε την τώρα ώστε να βοηθήσει την επόμενη φορά.</div>');
    const nk = box.querySelector('#clNewKb');
    if (nk) nk.onclick = (e) => { e.preventDefault();
      window.CNP.go('knowledge');
      setTimeout(() => { const t = document.getElementById('knT'), tg = document.getElementById('knG');
        if (t) t.value = dd.ticket.title; if (tg) tg.value = cName || aName;
        const el = document.getElementById('knS'); if (el) el.focus(); }, 400);
      ovl.remove();
    };
  };
  ovl.querySelector('#clC').onchange = refreshKb;
  ovl.querySelector('#clA').onchange = refreshKb;
  refreshKb();
  ovl.querySelector('#clAI').onclick = async () => {
    const b = ovl.querySelector('#clAI'); b.innerHTML = '⏳…'; b.disabled = true;
    const r = await api('classify_suggest', {ticket: id}).catch(e => ({err: e.message}));
    b.innerHTML = I.sparkle + ' Πρόταση AI'; b.disabled = false;
    if (r.err) { toast(r.err, true); return; }
    if (r.area) ovl.querySelector('#clA').value = r.area;
    if (r.cause) ovl.querySelector('#clC').value = r.cause;
    refreshKb();
    toast('✨ Πρόταση AI — έλεγξε & αποθήκευσε');
  };
  ovl.querySelector('#clGo').onclick = async () => {
    let html = ed.innerHTML.trim();
    if (html === '<br>' || html === '<div><br></div>') { html = ''; }
    await api('ticket_classify', {ticket: id, area: +ovl.querySelector('#clA').value || 0,
      cause: +ovl.querySelector('#clC').value || 0, note: html});
    toast('Ταξινομήθηκε ✓'); ovl.remove(); done && done();
  };
}

/* 📚 Μετά το κλείσιμο: πρόταση αποθήκευσης της λύσης στην τράπεζα γνώσης */
async function kbCapture(ticketId) {
  if (!(await cnpConfirm('Το ticket έκλεισε ✓\n\nΝα σώσουμε τη λύση στην τράπεζα γνώσης;', {title: I.book + ' Βάση γνώσης', ok: '✨ Ναι, ετοίμασε προσχέδιο', cancel: 'Όχι τώρα'}))) return;
  toast('Ετοιμάζω προσχέδιο…');
  const r = await api('kb_draft', {ticket: ticketId}).catch(e => ({err: e.message}));
  if (r.err) { toast(r.err, true); return; }
  const d = r.draft;
  const ovl = document.createElement('div'); ovl.className = 'ovl show';
  ovl.onclick = e => { if (e.target === ovl) ovl.remove(); };
  ovl.innerHTML = `<div class="pal-box" style="margin:8vh auto 0;max-width:640px" onclick="event.stopPropagation()">
    <div class="pop-h" style="padding:14px 18px">${I.book} Νέα λύση στην τράπεζα γνώσης</div>
    <div style="padding:4px 18px 18px">
      <label class="lbl">Τίτλος προβλήματος</label>
      <input class="inp" id="kcT" value="${esc(d.title)}">
      <label class="lbl" style="margin-top:9px">Λέξεις-κλειδιά</label>
      <input class="inp" id="kcK" value="${esc(d.keywords)}">
      <label class="lbl" style="margin-top:9px">Λύση</label>
      <textarea class="inp" id="kcS" rows="9">${esc(d.solution)}</textarea>
      <div style="display:flex;gap:9px;margin-top:12px">
        <button class="btn btn-p" id="kcSave">${I.book} Αποθήκευση στην τράπεζα</button>
        <button class="btn btn-o" id="kcSkip">Όχι αυτή τη φορά</button></div>
    </div></div>`;
  document.body.appendChild(ovl);
  ovl.querySelector('#kcSkip').onclick = () => ovl.remove();
  ovl.querySelector('#kcSave').onclick = async () => {
    const rr = await api('kb_save', {id: 0, title: ovl.querySelector('#kcT').value,
      keywords: ovl.querySelector('#kcK').value, tags: '',
      solution: ovl.querySelector('#kcS').value}).catch(e => ({err: e.message}));
    if (rr.err) { toast(rr.err, true); return; }
    toast('Η γνώση αποθηκεύτηκε — ευχαριστούμε! 🧠');
    ovl.remove();
  };
}

/* ═════════ ΡΥΘΜΙΣΕΙΣ ═════════ */
R.settings = async function (sub) {
  const st = R.settings._st = R.settings._st || {sub: 'general'};
  if (typeof sub === 'string' && sub) st.sub = sub;
  const SUBS = [
    ['general', I.gear, 'Γενικά'],
    ['board', I.board, 'Board & Tasks'],
    ['canned', I.chat, 'Απαντήσεις'],
    ['autos', I.bot, 'Automations'],
    ['crm', I.funnel, 'CRM'],
    ['users', I.users, 'Χρήστες & πρόσβαση'],
    ['tquotas', I.ticket, 'Πακέτα υποστήριξης'],
    ['tcats', I.tag, 'Κατηγορίες tickets'],
    ['whticket', I.folder, 'Τμήματα & Status'],
  ];
  const cur = SUBS.find(x => x[0] === st.sub) || SUBS[0];
  st.sub = cur[0];
  setTop('Ρυθμίσεις', cur[2] + ' — όλα χωρίς WHMCS admin');
  const c = $('#content');
  c.innerHTML = '<div class="skel" style="height:300px"></div>';
  const d = await api('settings_get').catch(() => null);
  if (!d) { c.innerHTML = `<div class="empty"><div class="big">${I.lock}</div>Μόνο για διαχειριστές</div>`; return; }
  const s = d.settings;
  const onoff = (k, label, descr) => `
    <div class="set-row"><div><b>${label}</b><div class="mut" style="font-size:12px">${descr}</div></div>
      <label class="switch"><input type="checkbox" data-set="${k}" ${s[k] === 'on' ? 'checked' : ''}><span></span></label></div>`;
  const txt = (k, label, descr, w) => `
    <div class="set-row"><div><b>${label}</b><div class="mut" style="font-size:12px">${descr}</div></div>
      <input class="inp" data-set="${k}" value="${esc(s[k])}" style="width:${w || '110px'}"></div>`;
  const tabsHtml = `<div class="ib-tabs" style="margin-bottom:16px;flex-wrap:wrap;border:0;background:0">
    ${SUBS.map(([k, ic, l]) => `<button class="ib-tab ${st.sub === k ? 'on' : ''}" data-sub="${k}"><span class="tico">${ic}</span>${l}</button>`).join('')}</div>`;

  let body = '';
  if (st.sub === 'general') {
    body = `<div class="grid g2"><div>
      <div class="card"><div class="card-h">${I.gear} Λειτουργία</div><div class="card-b">
        ${onoff('auto_task', 'Auto-task από tickets', 'Κάθε νέο ticket δημιουργεί task στο project του τμήματος')}
        ${onoff('notify_email', 'Ειδοποιήσεις email', 'Αναθέσεις, σχόλια, digest, follow-ups')}
        ${onoff('request_form', 'Δημόσια φόρμα αιτημάτων', 'index.php?m=cloudonprojects&action=request → lead')}
      </div></div>
      <div class="card"><div class="card-h">${I.brain} AI βοηθός</div><div class="card-b">
        ${txt('ai_api_key', 'Anthropic API key', 'Για ✨ AI απάντηση & σύνοψη στα tickets (console.anthropic.com)', '100%')}
      </div></div></div>
      <div><div class="card"><div class="card-h">${I.coin} Οικονομικά</div><div class="card-b">
        ${txt('sales_target', 'Μηνιαίος στόχος πωλήσεων €', 'Πρόοδος από κερδισμένες προσφορές')}
        ${txt('cost_per_hour', 'Κόστος ώρας εργασίας €', 'Για τον υπολογισμό κερδοφορίας')}
      </div></div>
      <button class="btn btn-p" id="setSave">Αποθήκευση ρυθμίσεων</button></div></div>`;
  } else if (st.sub === 'board') {
    body = `<div class="grid g2"><div>
      <div class="card"><div class="card-h">${I.chart} Στήλες Board</div><div class="card-b" id="setSts">
        ${d.statuses.map(x => `<div class="set-row" data-st="${x.id}">
          <input class="inp" value="${esc(x.title)}" data-f="title" style="flex:1">
          <input class="inp" type="color" value="${x.color}" data-f="color" style="width:46px;padding:3px">
          <label style="font-size:11px;display:flex;gap:4px;align-items:center">
            <input type="checkbox" data-f="done" ${x.done ? 'checked' : ''}>✓τέλος</label>
          <button class="btn btn-sm btn-o" data-stSave="${x.id}">${I.save} </button>
          <button class="btn btn-sm btn-o" data-stDel="${x.id}" ${x.tasks ? 'disabled title="έχει ' + x.tasks + ' tasks"' : ''}>✕</button>
        </div>`).join('')}
        <div class="set-row"><input class="inp" id="stNew" placeholder="Νέα στήλη…" style="flex:1">
          <input class="inp" type="color" id="stNewC" value="#0090dd" style="width:46px;padding:3px">
          <button class="btn btn-sm btn-p" id="stAdd">+</button></div>
      </div></div></div><div>
      <div class="card"><div class="card-h">${I.tag} Τύποι tasks</div><div class="card-b">
        ${d.types.map(ty => `<div class="set-row" data-ty="${ty.id}">
          <input class="inp" value="${esc(ty.name)}" data-f="name" style="flex:1">
          <input class="inp" value="${esc(ty.icon)}" data-f="icon" style="width:110px" title="fa-εικονίδιο">
          <input class="inp" type="color" value="${ty.color}" data-f="color" style="width:46px;padding:3px">
          <span style="font-size:10px;display:flex;gap:6px">
            <label title="απαιτεί ανάθεση"><input type="checkbox" data-f="reqA" ${ty.reqA ? 'checked' : ''}>${I.user} </label>
            <label title="απαιτεί λήξη"><input type="checkbox" data-f="reqD" ${ty.reqD ? 'checked' : ''}>${I.cal} </label>
            <label title="απαιτεί εκτίμηση"><input type="checkbox" data-f="reqE" ${ty.reqE ? 'checked' : ''}>⏱</label></span>
          <button class="btn btn-sm btn-o" data-tySave="${ty.id}">${I.save} </button>
          <button class="btn btn-sm btn-o" data-tyDel="${ty.id}">✕</button>
        </div>`).join('')}
        <div class="set-row"><input class="inp" id="tyNew" placeholder="Νέος τύπος…" style="flex:1">
          <button class="btn btn-sm btn-p" id="tyAdd">+</button></div>
      </div></div></div></div>`;
  } else if (st.sub === 'canned') {
    body = `<div class="card" style="max-width:760px"><div class="card-h">${I.clipboard} Έτοιμες απαντήσεις tickets</div>
      <div class="card-b" id="setCanned"><div class="skel" style="height:60px"></div></div></div>`;
  } else if (st.sub === 'autos') {
    body = `<div class="card"><div class="card-h">${I.bot} Automations — κανόνες «όταν… τότε…»</div>
      <div class="card-b" id="setAutos"><div class="skel" style="height:60px"></div></div></div>`;
  } else if (st.sub === 'crm') {
    body = `<div class="card" style="max-width:760px"><div class="card-h">${I.puzzle} Πεδία CRM (leads)</div>
      <div class="card-b" id="setLf"><div class="skel" style="height:40px"></div></div></div>`;
  } else if (st.sub === 'tquotas') {
    body = `<div class="card" style="max-width:820px"><div class="card-h">${I.ticket} Πακέτα υποστήριξης — όρια tickets (μηνιαία)</div>
      <div class="card-b" id="setTq"><div class="skel" style="height:80px"></div></div></div>`;
  } else if (st.sub === 'tcats') {
    body = `<div class="grid g2">
      <div class="card"><div class="card-h">${I.box} Περιοχές / Προϊόντα</div><div class="card-b" id="setCatA"><div class="skel" style="height:60px"></div></div></div>
      <div class="card"><div class="card-h">${I.lab} Ρίζες προβλήματος / Επίλυσης</div><div class="card-b" id="setCatC"><div class="skel" style="height:60px"></div></div></div>
    </div>`;
  } else if (st.sub === 'whticket') {
    body = `<div class="grid g2">
      <div class="card"><div class="card-h">${I.folder} Τμήματα (departments)</div><div class="card-b" id="setDepts"><div class="skel" style="height:60px"></div></div></div>
      <div class="card"><div class="card-h">${I.ticket} Status tickets <span class="mut" style="font-weight:400;font-size:11px;margin-left:auto">σύρε χρώμα · τα βασικά κλειδωμένα</span></div><div class="card-b" id="setStatuses" style="max-height:65vh;overflow:auto"><div class="skel" style="height:60px"></div></div></div>
    </div>`;
  } else if (st.sub === 'users') {
    body = `<div class="grid g2"><div>
      <div class="card"><div class="card-h">${I.user} Χρήστες & δικαιώματα</div><div class="card-b" id="setUsers">
        <div class="skel" style="height:60px"></div></div></div></div><div>
      <div class="card"><div class="card-h">${I.lock} Πρόσβαση</div><div class="card-b">
        ${txt('full_access_roles', 'Ρόλοι πλήρους πρόσβασης', 'IDs ρόλων WHMCS admin (π.χ. 1) — αυτοί είναι «Διαχειριστές»', '110px')}
        ${txt('team_roles', 'Ρόλοι μελών ομάδων', 'Χωρισμένοι με κόμμα — εμφανίζονται στις Ομάδες', '100%')}
      </div></div>
      <button class="btn btn-p" id="setSave">Αποθήκευση ρυθμίσεων</button></div></div>`;
  }
  c.innerHTML = tabsHtml + body;
  $$('[data-sub]').forEach(b => b.onclick = () => R.settings(b.dataset.sub));

  if ($('#setSave')) $('#setSave').onclick = async () => {
    const settings = {};
    $$('[data-set]').forEach(el => settings[el.dataset.set] =
      el.type === 'checkbox' ? (el.checked ? 'on' : '') : el.value);
    await api('settings_save', {settings});
    toast('Οι ρυθμίσεις αποθηκεύτηκαν');
  };

  if (st.sub === 'board') {
    const stRow = id => $(`[data-st="${id}"]`);
    $$('[data-stSave]').forEach(b => b.onclick = async () => {
      const r = stRow(b.dataset.stSave);
      await api('status_save', {id: +b.dataset.stSave, title: $('[data-f=title]', r).value,
        color: $('[data-f=color]', r).value, done: $('[data-f=done]', r).checked});
      toast('Αποθηκεύτηκε');
    });
    $$('[data-stDel]').forEach(b => b.onclick = async () => {
      const r = await api('status_del', {id: +b.dataset.stDel}).catch(e => ({err: e.message}));
      if (r.err) toast(r.err, true); else { toast('Διαγράφηκε'); R.settings(); }
    });
    $('#stAdd').onclick = async () => {
      if (!$('#stNew').value.trim()) return;
      await api('status_save', {id: 0, title: $('#stNew').value.trim(), color: $('#stNewC').value});
      toast('Προστέθηκε'); R.settings();
    };
    const tyRow = id => $(`[data-ty="${id}"]`);
    $$('[data-tySave]').forEach(b => b.onclick = async () => {
      const r = tyRow(b.dataset.tySave);
      await api('type_save', {id: +b.dataset.tySave, name: $('[data-f=name]', r).value,
        icon: $('[data-f=icon]', r).value, color: $('[data-f=color]', r).value,
        reqA: $('[data-f=reqA]', r).checked, reqD: $('[data-f=reqD]', r).checked, reqE: $('[data-f=reqE]', r).checked});
      toast('Αποθηκεύτηκε');
    });
    $$('[data-tyDel]').forEach(b => b.onclick = async () => {
      if (!(await cnpConfirm('Διαγραφή τύπου; (τα tasks του μένουν χωρίς τύπο)', {danger: true, ok: I.trash + ' Διαγραφή'}))) return;
      await api('type_del', {id: +b.dataset.tyDel}); toast('Διαγράφηκε'); R.settings();
    });
    $('#tyAdd').onclick = async () => {
      if (!$('#tyNew').value.trim()) return;
      await api('type_save', {id: 0, name: $('#tyNew').value.trim()});
      toast('Προστέθηκε'); R.settings();
    };
  }

  if (st.sub === 'canned') {
    const cn = await api('canned');
    $('#setCanned').innerHTML = cn.canned.map(x => `<div class="set-row" data-cn="${x.id}" style="align-items:flex-start">
        <div style="flex:1"><input class="inp" data-f="title" value="${esc(x.title)}" style="margin-bottom:6px">
          <textarea class="inp" data-f="body" rows="2">${esc(x.body)}</textarea></div>
        <button class="btn btn-sm btn-o" data-cnSave="${x.id}">${I.save} </button>
        <button class="btn btn-sm btn-o" data-cnDel="${x.id}">✕</button></div>`).join('') +
      `<div class="set-row" style="align-items:flex-start">
        <div style="flex:1"><input class="inp" id="cnNewT" placeholder="Τίτλος (π.χ. Καλωσόρισμα)" style="margin-bottom:6px">
          <textarea class="inp" id="cnNewB" rows="2" placeholder="Κείμενο… ({πελάτης} και {εγώ} αντικαθίστανται αυτόματα)"></textarea></div>
        <button class="btn btn-sm btn-p" id="cnAdd">+</button></div>`;
    $$('[data-cnSave]').forEach(b => b.onclick = async () => {
      const r = $(`[data-cn="${b.dataset.cnSave}"]`);
      await api('canned_save', {id: +b.dataset.cnSave, title: $('[data-f=title]', r).value, body: $('[data-f=body]', r).value});
      if (R.inbox) R.inbox._canned = null;
      toast('Αποθηκεύτηκε');
    });
    $$('[data-cnDel]').forEach(b => b.onclick = async () => {
      await api('canned_del', {id: +b.dataset.cnDel});
      if (R.inbox) R.inbox._canned = null;
      toast('Διαγράφηκε'); R.settings();
    });
    $('#cnAdd').onclick = async () => {
      if (!$('#cnNewT').value.trim() || !$('#cnNewB').value.trim()) return;
      await api('canned_save', {id: 0, title: $('#cnNewT').value.trim(), body: $('#cnNewB').value.trim()});
      if (R.inbox) R.inbox._canned = null;
      toast('Προστέθηκε'); R.settings();
    };
  }

  if (st.sub === 'autos') {
    const au = await api('autos');
    const trigL = {task_status: 'Task μπήκε σε στήλη', ticket_status: 'Ticket άλλαξε κατάσταση',
      lead_stage: 'Lead μπήκε σε στάδιο', sla_breach: 'Παραβίαση SLA (χωρίς απάντηση)'};
    const actL = {notify: 'Ειδοποίησε', assign_task: 'Ανάθεσε το task σε', ball: 'Η μπάλα στον',
      set_prio: 'Όρισε προτεραιότητα task', assign_ticket: 'Ανάθεσε το ticket σε', escalate: 'Κλιμάκωσε ticket σε High'};
    const tvOpts = trig => {
      if (trig === 'task_status') return S.boot.statuses.map(x => `<option value="${x.id}">${esc(x.title)}</option>`).join('');
      if (trig === 'ticket_status') return au.ticketStatuses.map(x => `<option value="${esc(x)}">${esc(x)}</option>`).join('');
      if (trig === 'lead_stage') return au.leadStages.map(x => `<option value="${x.key}">${esc(x.title)}</option>`).join('');
      return '<option value="">—</option>';
    };
    const avOpts = act => {
      if (act === 'set_prio') return ['Κανονική', 'Υψηλή', 'Κρίσιμη'].map((p, i) => `<option value="${i}">${p}</option>`).join('');
      if (act === 'escalate') return '<option value="">—</option>';
      const admins = S.boot.admins.map(a => `<option value="${a.id}">${esc(a.name)}</option>`).join('');
      return (act === 'notify' ? '<option value="-1">📣 Διαχειριστές</option>' : '') + admins;
    };
    const autoRow = (a) => `<div class="set-row" data-au="${a.id || 0}" style="flex-wrap:wrap;gap:6px">
      <input class="inp" data-f="name" value="${esc(a.name || '')}" placeholder="Όνομα κανόνα" style="width:150px">
      <span class="mut" style="font-size:11px">Όταν</span>
      <select class="inp" data-f="trigger" style="width:auto;font-size:12px">${Object.entries(trigL).map(([k, v]) =>
        `<option value="${k}" ${k === a.trigger ? 'selected' : ''}>${v}</option>`).join('')}</select>
      <select class="inp" data-f="tvalue" style="width:auto;font-size:12px">${tvOpts(a.trigger || 'task_status')}</select>
      <span class="mut" style="font-size:11px">τότε</span>
      <select class="inp" data-f="action" style="width:auto;font-size:12px">${Object.entries(actL).map(([k, v]) =>
        `<option value="${k}" ${k === a.action ? 'selected' : ''}>${v}</option>`).join('')}</select>
      <select class="inp" data-f="avalue" style="width:auto;font-size:12px">${avOpts(a.action || 'notify')}</select>
      <label class="switch" title="Ενεργό"><input type="checkbox" data-f="active" ${a.active !== false && a.active !== 0 ? 'checked' : ''}><span></span></label>
      <button class="btn btn-sm btn-o" data-auSave="${a.id || 0}">${I.save} </button>
      ${a.id ? `<button class="btn btn-sm btn-o" data-auDel="${a.id}">✕</button>` : ''}</div>`;
    $('#setAutos').innerHTML = au.autos.map(autoRow).join('') + autoRow({trigger: 'task_status', action: 'notify', active: true});
    $$('#setAutos [data-f=trigger]').forEach(sel => sel.onchange = () => {
      $('[data-f=tvalue]', sel.closest('.set-row')).innerHTML = tvOpts(sel.value);
    });
    $$('#setAutos [data-f=action]').forEach(sel => sel.onchange = () => {
      $('[data-f=avalue]', sel.closest('.set-row')).innerHTML = avOpts(sel.value);
    });
    $$('[data-auSave]').forEach(b => b.onclick = async () => {
      const r = b.closest('.set-row');
      await api('auto_save', {id: +b.dataset.auSave, name: $('[data-f=name]', r).value,
        trigger: $('[data-f=trigger]', r).value, tvalue: $('[data-f=tvalue]', r).value,
        action: $('[data-f=action]', r).value, avalue: $('[data-f=avalue]', r).value,
        active: $('[data-f=active]', r).checked});
      toast('Ο κανόνας αποθηκεύτηκε'); R.settings();
    });
    $$('[data-auDel]').forEach(b => b.onclick = async () => {
      await api('auto_del', {id: +b.dataset.auDel}); toast('Διαγράφηκε'); R.settings();
    });
    $$('#setAutos .set-row').forEach((r, i) => {
      const a = au.autos[i];
      if (a) { $('[data-f=tvalue]', r).value = a.tvalue ?? ''; $('[data-f=avalue]', r).value = a.avalue ?? ''; }
    });
  }

  if (st.sub === 'crm') {
    const lf = await api('lead_fields');
    $('#setLf').innerHTML = lf.fields.map(f => `<div class="set-row" data-lfr="${f.id}">
        <input class="inp" data-f="label" value="${esc(f.label)}" style="flex:1">
        <select class="inp" data-f="type" style="width:auto">
          <option value="text" ${f.type === 'text' ? 'selected' : ''}>Κείμενο</option>
          <option value="select" ${f.type === 'select' ? 'selected' : ''}>Επιλογή</option>
          <option value="date" ${f.type === 'date' ? 'selected' : ''}>Ημερομηνία</option></select>
        <input class="inp" data-f="options" value="${esc(f.options.join('|'))}" placeholder="επιλογές με |" style="width:160px">
        <button class="btn btn-sm btn-o" data-lfSave="${f.id}">${I.save} </button>
        <button class="btn btn-sm btn-o" data-lfDel="${f.id}">✕</button></div>`).join('') +
      `<div class="set-row"><input class="inp" id="lfNewL" placeholder="Νέο πεδίο (π.χ. Μέγεθος εταιρείας)" style="flex:1">
        <select class="inp" id="lfNewT" style="width:auto"><option value="text">Κείμενο</option>
          <option value="select">Επιλογή</option><option value="date">Ημερομηνία</option></select>
        <input class="inp" id="lfNewO" placeholder="επιλογές με |" style="width:160px">
        <button class="btn btn-sm btn-p" id="lfAdd">+</button></div>`;
    $$('[data-lfSave]').forEach(b => b.onclick = async () => {
      const r = $(`[data-lfr="${b.dataset.lfSave}"]`);
      await api('lead_field_save', {id: +b.dataset.lfSave, label: $('[data-f=label]', r).value,
        type: $('[data-f=type]', r).value, options: $('[data-f=options]', r).value});
      toast('Αποθηκεύτηκε');
    });
    $$('[data-lfDel]').forEach(b => b.onclick = async () => {
      if (!(await cnpConfirm('Διαγραφή πεδίου και των τιμών του;', {danger: true, ok: I.trash + ' Διαγραφή'}))) return;
      await api('lead_field_del', {id: +b.dataset.lfDel}); toast('Διαγράφηκε'); R.settings();
    });
    $('#lfAdd').onclick = async () => {
      if (!$('#lfNewL').value.trim()) return;
      await api('lead_field_save', {id: 0, label: $('#lfNewL').value.trim(),
        type: $('#lfNewT').value, options: $('#lfNewO').value});
      toast('Προστέθηκε'); R.settings();
    };
  }

  if (st.sub === 'users') {
    const uu = await api('users');
    const roleSel = (rid, attr) => `<select class="inp" ${attr} style="width:auto;padding:5px 8px;font-size:12px">
        ${uu.roles.map(r => `<option value="${r.id}" ${r.id === rid ? 'selected' : ''}>${esc(r.name)}${r.full ? ' ★' : ''}</option>`).join('')}</select>`;
    $('#setUsers').innerHTML = `
      <div class="mut" style="font-size:11.5px;margin-bottom:9px">★ = ρόλος πλήρους πρόσβασης (διαχειριστής). Οι υπόλοιποι βλέπουν μόνο ό,τι τους έχει ανατεθεί.</div>` +
      uu.users.map(u => `<div style="border-bottom:1px solid var(--line);padding-bottom:5px;margin-bottom:5px;${u.disabled ? 'opacity:.45' : ''}">
       <div class="set-row" data-ur="${u.id}" style="border:0">
        <div style="flex:1;min-width:0"><b>${esc(u.username)}</b> <span class="pill ${u.full ? 'pill-info' : ''}" style="font-size:9.5px">${u.full ? 'Διαχειριστής' : 'Χειριστής'}</span>
          <div class="mut" style="font-size:11px">${esc(u.name || '—')} · ${esc(u.email || '—')}</div></div>
        ${roleSel(u.roleid, `data-urole="${u.id}"`)}
        <button class="btn btn-sm btn-o" data-upass="${u.id}" title="Νέος κωδικός">${I.key} </button>
        <button class="btn btn-sm btn-o" data-utog="${u.id}" title="${u.disabled ? 'Ενεργοποίηση' : 'Απενεργοποίηση'}">${u.disabled ? '▶' : '⏸'}</button>
        <button class="btn btn-sm btn-o" data-udel="${u.id}" title="Διαγραφή" style="color:var(--bad)">${I.trash}</button>
       </div>
       ${u.full ? '<div class="mut" style="font-size:10.5px;padding:0 0 3px 4px">Διαχειριστής — βλέπει όλα τα κυκλώματα</div>'
         : `<div style="display:flex;gap:5px;padding:0 0 3px 4px;flex-wrap:wrap;align-items:center">
           <span class="mut" style="font-size:10.5px">Ειδικότητες:</span>
           ${[['sales', 'Πωλήσεις'], ['support', 'Υποστήριξη'], ['projects', 'Έργα'], ['hr', 'HR/Προσλήψεις']].map(([k, l]) =>
             `<label class="pill ${u.areas.includes(k) ? 'pill-info' : 'pill-mut'}" style="font-size:10px;cursor:pointer;display:inline-flex;gap:3px;align-items:center">
               <input type="checkbox" data-uarea="${u.id}:${k}" ${u.areas.includes(k) ? 'checked' : ''} style="width:12px;height:12px">${l}</label>`).join('')}</div>`}
      </div>`).join('') + `
      <div class="set-row" style="flex-wrap:wrap;gap:7px;border-top:2px solid var(--line);padding-top:12px">
        <input class="inp" id="unUser" placeholder="username" style="width:110px">
        <input class="inp" id="unFirst" placeholder="Όνομα" style="width:100px">
        <input class="inp" id="unLast" placeholder="Επώνυμο" style="width:110px">
        <input class="inp" id="unEmail" placeholder="email" style="flex:1;min-width:150px">
        ${roleSel(3, 'id="unRole"')}
        <button class="btn btn-sm btn-p" id="unAdd">+ Νέος χρήστης</button></div>
      <div id="unPassBox" style="display:none;margin-top:10px;padding:12px 15px;border-radius:11px;background:#2dbd6e1a;font-size:13px"></div>`;
    const showPass = (uname, pass) => {
      const b = $('#unPassBox'); b.style.display = '';
      b.innerHTML = `${I.key} Κωδικός για <b>${esc(uname)}</b>: <code style="font-size:15px;user-select:all">${esc(pass)}</code>
        <div class="mut" style="font-size:11px;margin-top:4px">Εμφανίζεται ΜΟΝΟ τώρα — αντίγραψέ τον και δώσ' τον στον χρήστη. Σύνδεση: my.cloudon.gr/project</div>`;
      b.scrollIntoView({behavior: 'smooth', block: 'center'});
    };
    $$('[data-urole]').forEach(sel => sel.onchange = async () => {
      const u = uu.users.find(x => x.id === +sel.dataset.urole);
      await api('user_save', {id: u.id, roleid: +sel.value, email: u.email, first: (u.name || '').split(' ')[0] || '', last: (u.name || '').split(' ').slice(1).join(' ')});
      toast('Ο ρόλος άλλαξε'); R.settings();
    });
    $$('[data-uarea]').forEach(chk => chk.onchange = async () => {
      const uid = +chk.dataset.uarea.split(':')[0];
      const areas = $$(`[data-uarea^="${uid}:"]`).filter(x => x.checked).map(x => x.dataset.uarea.split(':')[1]);
      await api('user_areas_save', {id: uid, areas});
      toast('Οι ειδικότητες ενημερώθηκαν — ο χρήστης θα τις δει στην επόμενη είσοδο/refresh');
    });
    $$('[data-upass]').forEach(b => b.onclick = async () => {
      const u = uu.users.find(x => x.id === +b.dataset.upass);
      if (!(await cnpConfirm(`Νέος κωδικός για τον χρήστη «${u.username}»; Ο παλιός παύει να ισχύει.`, {title: I.key + ' Reset κωδικού', ok: 'Δημιουργία νέου'}))) return;
      const r = await api('user_pass', {id: u.id});
      showPass(u.username, r.password);
    });
    $$('[data-utog]').forEach(b => b.onclick = async () => {
      const r = await api('user_toggle', {id: +b.dataset.utog}).catch(e => ({err: e.message}));
      if (r.err) { toast(r.err, true); return; }
      toast(r.disabled ? 'Απενεργοποιήθηκε' : 'Ενεργοποιήθηκε'); R.settings();
    });
    $$('[data-udel]').forEach(b => b.onclick = async () => {
      const u = uu.users.find(x => x.id === +b.dataset.udel);
      const typed = await cnpPrompt(`ΟΡΙΣΤΙΚΗ διαγραφή του χρήστη «${u.username}». Το ιστορικό του διατηρείται.\n\nΓράψε το username για επιβεβαίωση:`, {title: I.trash + ' Διαγραφή χρήστη', placeholder: u.username, ok: 'Οριστική διαγραφή', danger: true});
      if (typed === null) return;
      if (typed.trim() !== u.username) { toast('Δεν ταιριάζει το username — ακυρώθηκε', true); return; }
      const r = await api('user_del', {id: u.id}).catch(e => ({err: e.message}));
      if (r.err) { toast(r.err, true); return; }
      toast('Ο χρήστης διαγράφηκε'); R.settings();
    });
    $('#unAdd').onclick = async () => {
      const uname = $('#unUser').value.trim();
      if (!uname) { toast('Δώσε username', true); return; }
      const r = await api('user_save', {id: 0, username: uname, first: $('#unFirst').value,
        last: $('#unLast').value, email: $('#unEmail').value, roleid: +$('#unRole').value}).catch(e => ({err: e.message}));
      if (r.err) { toast(r.err, true); return; }
      toast('Ο χρήστης δημιουργήθηκε');
      ['#unUser', '#unFirst', '#unLast', '#unEmail'].forEach(x => $(x).value = '');
      showPass(uname, r.password);   // μένει στην οθόνη — refresh μόνο όταν ξαναμπείς στις Ρυθμίσεις
    };
  }

  if (st.sub === 'tquotas') {
    const tq = await api('tquotas');
    $('#setTq').innerHTML = `
      <div class="mut" style="font-size:12px;margin-bottom:10px">Δικά σας πακέτα υποστήριξης — ονόμασέ τα όπως θες (π.χ. SLA VIP).
        <b>0 = χωρίς όριο.</b> Email = τα ανοίγει ο πελάτης · Τηλεφωνικά = τα ανοίγει χειριστής εκ μέρους του.
        Ανάθεση πελάτη σε πακέτο: από το <b>Πελάτης 360°</b>. Πολιτική: κάθε ticket = <b>ένα θέμα</b>.</div>
      <table class="tbl"><thead><tr><th>Πακέτο</th><th>Πελάτες</th><th>Tickets/μήνα</th><th>Email</th><th>Τηλ.</th><th></th></tr></thead><tbody>
      ${tq.packages.map(q => `<tr data-tq="${q.id}">
        <td><input class="inp" data-f="name" value="${esc(q.name)}" style="width:170px;font-weight:700"></td>
        <td class="mut">${q.clients}</td>
        <td><input class="inp" data-f="t" type="number" min="0" value="${q.t}" style="width:80px"></td>
        <td><input class="inp" data-f="email" type="number" min="0" value="${q.email}" style="width:80px"></td>
        <td><input class="inp" data-f="phone" type="number" min="0" value="${q.phone}" style="width:80px"></td>
        <td style="white-space:nowrap"><button class="btn btn-sm btn-o" data-tqsave="${q.id}">${I.save} </button>
          <button class="btn btn-sm btn-o" data-tqdel="${q.id}" ${q.clients ? 'disabled title="έχει ' + q.clients + ' πελάτες"' : ''}>✕</button></td></tr>`).join('')}
      <tr><td><input class="inp" id="tqNewN" placeholder="Νέο πακέτο… (π.χ. SLA Gold)" style="width:170px"></td>
        <td></td>
        <td><input class="inp" id="tqNewT" type="number" min="0" value="0" style="width:80px"></td>
        <td><input class="inp" id="tqNewE" type="number" min="0" value="0" style="width:80px"></td>
        <td><input class="inp" id="tqNewP" type="number" min="0" value="0" style="width:80px"></td>
        <td><button class="btn btn-sm btn-p" id="tqAdd">+</button></td></tr>
      </tbody></table>`;
    $$('[data-tqsave]').forEach(b => b.onclick = async () => {
      const r = $(`[data-tq="${b.dataset.tqsave}"]`);
      const rr = await api('tquota_save', {id: +b.dataset.tqsave, name: $('[data-f=name]', r).value,
        t: +$('[data-f=t]', r).value, email: +$('[data-f=email]', r).value,
        phone: +$('[data-f=phone]', r).value}).catch(e => ({err: e.message}));
      if (rr.err) { toast(rr.err, true); return; }
      toast('Το πακέτο αποθηκεύτηκε');
    });
    $$('[data-tqdel]').forEach(b => b.onclick = async () => {
      if (!(await cnpConfirm('Διαγραφή πακέτου;', {danger: true, ok: I.trash + ' Διαγραφή'}))) return;
      await api('tquota_del', {id: +b.dataset.tqdel});
      toast('Διαγράφηκε'); R.settings();
    });
    $('#tqAdd').onclick = async () => {
      const rr = await api('tquota_save', {id: 0, name: $('#tqNewN').value,
        t: +$('#tqNewT').value, email: +$('#tqNewE').value, phone: +$('#tqNewP').value}).catch(e => ({err: e.message}));
      if (rr.err) { toast(rr.err, true); return; }
      toast('Το πακέτο δημιουργήθηκε'); R.settings();
    };
  }

  if (st.sub === 'tcats') {
    const tc = await api('tcats');
    const render = (box, kind, list) => {
      $(box).innerHTML = list.map(c => `<div class="set-row" data-tc="${c.id}">
          <input class="inp" type="color" data-f="color" value="${c.color}" style="width:42px;padding:3px">
          <input class="inp" data-f="name" value="${esc(c.name)}" style="flex:1">
          <span class="mut" style="font-size:11px;white-space:nowrap">${c.used}×</span>
          <button class="btn btn-sm btn-o" data-tcsave="${c.id}">${I.save} </button>
          <button class="btn btn-sm btn-o" data-tcdel="${c.id}" style="color:var(--bad)">✕</button></div>`).join('') + `
        <div class="set-row"><input class="inp" type="color" id="${kind}NewC" value="#0090dd" style="width:42px;padding:3px">
          <input class="inp" id="${kind}NewN" placeholder="Νέα κατηγορία…" style="flex:1">
          <button class="btn btn-sm btn-p" id="${kind}Add">+</button></div>`;
      $(box).querySelectorAll('[data-tcsave]').forEach(b => b.onclick = async () => {
        const r = $(`[data-tc="${b.dataset.tcsave}"]`, $(box));
        await api('tcat_save', {id: +b.dataset.tcsave, kind, name: $('[data-f=name]', r).value, color: $('[data-f=color]', r).value});
        toast('Αποθηκεύτηκε');
      });
      $(box).querySelectorAll('[data-tcdel]').forEach(b => b.onclick = async () => {
        if (!(await cnpConfirm('Διαγραφή κατηγορίας; (τα tickets ξε-ταξινομούνται από αυτήν)', {danger: true, ok: I.trash + ' Διαγραφή'}))) return;
        await api('tcat_del', {id: +b.dataset.tcdel}); toast('Διαγράφηκε'); R.settings();
      });
      $('#' + kind + 'Add', $(box)).onclick = async () => {
        const n = $('#' + kind + 'NewN', $(box)).value.trim(); if (!n) return;
        await api('tcat_save', {id: 0, kind, name: n, color: $('#' + kind + 'NewC', $(box)).value});
        toast('Προστέθηκε'); R.settings();
      };
    };
    render('#setCatA', 'area', tc.area);
    render('#setCatC', 'cause', tc.cause);
  }

  if (st.sub === 'whticket') {
    const w = await api('wh_ticket_manage').catch(() => null);
    if (!w) { $('#setDepts').innerHTML = '<div class="mut">Μόνο για διαχειριστές</div>'; return; }
    // ── Τμήματα ──
    $('#setDepts').innerHTML = w.depts.map(dp => `<div class="set-row" data-dp="${dp.id}" style="gap:6px">
        <input class="inp" data-f="name" value="${esc(dp.name)}" style="flex:1;min-width:90px">
        <input class="inp" data-f="email" value="${esc(dp.email || '')}" placeholder="email" style="flex:1;min-width:90px;font-size:11.5px">
        <span class="mut" style="font-size:10.5px;white-space:nowrap" title="tickets">${dp.tickets}${I.ticket}</span>
        <button class="btn btn-sm btn-o" data-dpsave="${dp.id}">${I.save}</button>
        <button class="btn btn-sm btn-o" data-dpdel="${dp.id}" style="color:var(--bad)"${dp.tickets ? ' disabled title="έχει tickets"' : ''}>✕</button></div>`).join('') + `
      <div class="set-row" style="gap:6px"><input class="inp" id="dpNewN" placeholder="Νέο τμήμα…" style="flex:1">
        <input class="inp" id="dpNewE" placeholder="email (προαιρετικό)" style="flex:1;font-size:11.5px">
        <button class="btn btn-sm btn-p" id="dpAdd">+</button></div>`;
    $$('[data-dpsave]', $('#setDepts')).forEach(b => b.onclick = async () => {
      const r = $(`[data-dp="${b.dataset.dpsave}"]`);
      await api('wh_dept_save', {id: +b.dataset.dpsave, name: $('[data-f=name]', r).value, email: $('[data-f=email]', r).value});
      toast('Αποθηκεύτηκε');
    });
    $$('[data-dpdel]', $('#setDepts')).forEach(b => b.onclick = async () => {
      if (b.disabled) return;
      if (!(await cnpConfirm('Διαγραφή τμήματος;', {danger: true, ok: I.trash + ' Διαγραφή'}))) return;
      await api('wh_dept_del', {id: +b.dataset.dpdel}).then(() => { toast('Διαγράφηκε'); R.settings(); }).catch(e => toast(e.message, true));
    });
    $('#dpAdd').onclick = async () => {
      const n = $('#dpNewN').value.trim(); if (!n) return;
      await api('wh_dept_save', {id: 0, name: n, email: $('#dpNewE').value.trim()});
      toast('Προστέθηκε'); R.settings();
    };
    // ── Status tickets ──
    $('#setStatuses').innerHTML = w.statuses.map(s => `<div class="set-row" data-ws="${s.id}" style="gap:6px">
        <input class="inp" type="color" data-f="color" value="${s.color}" style="width:40px;padding:3px">
        <input class="inp" data-f="title" value="${esc(s.title)}" ${s.core ? 'readonly title="βασικό WHMCS"' : ''} style="flex:1;${s.core ? 'opacity:.7' : ''}">
        <span class="mut" style="font-size:10px;white-space:nowrap">${s.used}×</span>
        ${s.core ? `<span class="pill pill-mut" style="font-size:9px">${I.lock}</span>` : ''}
        <button class="btn btn-sm btn-o" data-wssave="${s.id}">${I.save}</button>
        <button class="btn btn-sm btn-o" data-wsdel="${s.id}" style="color:var(--bad)"${s.core || s.used ? ' disabled' : ''}>✕</button></div>`).join('') + `
      <div class="set-row" style="gap:6px"><input class="inp" type="color" id="wsNewC" value="#0090dd" style="width:40px;padding:3px">
        <input class="inp" id="wsNewT" placeholder="Νέο status…" style="flex:1">
        <button class="btn btn-sm btn-p" id="wsAdd">+</button></div>`;
    $$('[data-wssave]', $('#setStatuses')).forEach(b => b.onclick = async () => {
      const r = $(`[data-ws="${b.dataset.wssave}"]`);
      await api('wh_tstatus_save', {id: +b.dataset.wssave, title: $('[data-f=title]', r).value, color: $('[data-f=color]', r).value});
      toast('Αποθηκεύτηκε');
    });
    $$('[data-wsdel]', $('#setStatuses')).forEach(b => b.onclick = async () => {
      if (b.disabled) return;
      if (!(await cnpConfirm('Διαγραφή status;', {danger: true, ok: I.trash + ' Διαγραφή'}))) return;
      await api('wh_tstatus_del', {id: +b.dataset.wsdel}).then(() => { toast('Διαγράφηκε'); R.settings(); }).catch(e => toast(e.message, true));
    });
    $('#wsAdd').onclick = async () => {
      const t = $('#wsNewT').value.trim(); if (!t) return;
      await api('wh_tstatus_save', {id: 0, title: t, color: $('#wsNewC').value});
      toast('Προστέθηκε'); R.settings();
    };
  }
};

/* ═════════ ⌘K COMMAND PALETTE ═════════ */
(function palette() {
  let open = false, t;
  function close() { const p = $('#cnpPal'); if (p) p.remove(); open = false; }
  function show() {
    if (open) { close(); return; }
    open = true;
    const p = document.createElement('div');
    p.id = 'cnpPal';
    p.innerHTML = `<div class="pal-box">
      <input class="pal-inp" id="palQ" placeholder="Αναζήτηση σε tasks, tickets, leads, πελάτες… ή εντολή">
      <div class="pal-res" id="palRes">
        <div class="pal-g">Γρήγορες ενέργειες</div>
        <div class="pal-row" data-nav="board">${I.clipboard} Πήγαινε στο Board</div>
        <div class="pal-row" data-nav="inbox">${I.ticket} Πήγαινε στα Tickets</div>
        <div class="pal-row" data-nav="crm">${I.target} Πήγαινε στο CRM</div>
        <div class="pal-row" data-nav="myday">${I.sun} Η μέρα μου</div>
      </div></div>`;
    document.body.appendChild(p);
    p.onclick = e => { if (e.target === p) close(); };
    const q = $('#palQ'); q.focus();
    q.oninput = () => {
      clearTimeout(t);
      const v = q.value.trim();
      if (v.length < 2) return;
      t = setTimeout(async () => {
        const d = await api('search&q=' + encodeURIComponent(v));
        const g = (title, rows) => rows.length ? `<div class="pal-g">${title}</div>` + rows.join('') : '';
        $('#palRes').innerHTML =
          g('Tasks', d.tasks.map(x => `<div class="pal-row" data-task="${x.id}">
            <span class="dot" style="background:${x.pcolor}"></span> ${esc(x.title)} <span class="mut" style="margin-left:auto;font-size:11px">${esc(x.pname)}</span></div>`)) +
          g('Tickets', d.tickets.map(x => `<div class="pal-row" data-ticket="${x.id}">${I.ticket} #${esc(x.tid)} ${esc(x.title)}
            <span class="pill pill-info" style="margin-left:auto;font-size:9.5px">${esc(x.status)}</span></div>`)) +
          g('Leads', d.leads.map(x => `<div class="pal-row" data-lead="${x.id}">${I.target} ${esc(x.name)}</div>`)) +
          g('Πελάτες', d.clients.map(x => `<div class="pal-row" data-client="${x.id}">${I.user} ${esc(x.name)}</div>`)) +
          (!d.tasks.length && !d.tickets.length && !d.leads.length && !d.clients.length
            ? '<div class="empty" style="padding:18px">Τίποτα δεν βρέθηκε</div>' : '');
        bindRows();
      }, 220);
    };
    function bindRows() {
      $$('#cnpPal .pal-row').forEach(r => r.onclick = () => {
        close();
        if (r.dataset.nav) go(r.dataset.nav);
        else if (r.dataset.task) openTask(+r.dataset.task);
        else if (r.dataset.ticket) go('inbox', r.dataset.ticket);
        else if (r.dataset.lead) go('crm');
        else if (r.dataset.client) go('client360', r.dataset.client);
      });
    }
    bindRows();
  }
  document.addEventListener('keydown', e => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); show(); }
    if (e.key === 'Escape') close();
  });
  window.CNP.palette = show;
})();
