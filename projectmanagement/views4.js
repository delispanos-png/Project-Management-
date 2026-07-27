/* ═══════════ CloudOn Projects — keyboard-first + views (Κύμα 1) ═══════════ */
'use strict';
const {S, api, esc, rteHtml, rteVal, suStat, fmtMin, dShort, tShort, today, toast, setTop, go,
  adminName, adminIni, statusOf, typeOf, openTask, closeDrawer, cnpConfirm, cnpPrompt, I, $, $$} = window.CNP;
const R = window.R;

/* ═════════ Keyboard shortcuts ═════════ */
(function keys() {
  let gPending = false, gTimer;
  const map = {m: 'myday', i: 'inbox', b: 'board', l: 'list', c: 'crm', o: 'offers',
    t: 'time', k: 'kpi', p: 'projects', s: 'settings'};
  document.addEventListener('keydown', e => {
    const tag = (e.target.tagName || '').toLowerCase();
    if (tag === 'input' || tag === 'textarea' || tag === 'select' || e.target.isContentEditable) return;
    if (e.ctrlKey || e.metaKey || e.altKey) return;
    const k = e.key.toLowerCase();
    if (gPending) {
      gPending = false; clearTimeout(gTimer);
      if (map[k]) { e.preventDefault(); go(map[k]); }
      return;
    }
    if (k === 'g') { gPending = true; gTimer = setTimeout(() => gPending = false, 900); return; }
    if (k === 'n') { e.preventDefault(); quickNew(); }
    if (k === '?') { e.preventDefault(); showKeys(); }
  });
  function showKeys() {
    closeDrawer();
    const ovl = document.createElement('div'); ovl.className = 'ovl show';
    ovl.innerHTML = `<div class="pal-box" style="margin:14vh auto 0;max-width:420px" onclick="event.stopPropagation()">
      <div class="pop-h" style="padding:14px 18px">⌨️ Συντομεύσεις</div>
      <div style="padding:12px 18px 18px;font-size:13px;line-height:2">
        <b>Ctrl+K</b> — αναζήτηση παντού<br><b>n</b> — νέο task<br>
        <b>g</b> μετά <b>m</b> — Η μέρα μου · <b>g i</b> — Tickets · <b>g b</b> — Board<br>
        <b>g l</b> — Λίστα · <b>g c</b> — CRM · <b>g o</b> — Προσφορές · <b>g t</b> — Χρόνος<br>
        <b>g k</b> — KPI · <b>g p</b> — Projects · <b>g s</b> — Ρυθμίσεις<br>
        <b>Esc</b> — κλείσιμο πάνελ · <b>?</b> — αυτή η λίστα</div></div>`;
    document.body.appendChild(ovl);
  }
  window.CNP.showKeys = showKeys;
})();

/* ═════════ Quick «Νέο task» (πλήκτρο n) ═════════ */
function quickNew() {
  closeDrawer();
  if (!S.boot.projects.length) { toast('Δεν έχεις projects', true); return; }
  const ovl = document.createElement('div'); ovl.className = 'ovl show'; 
  ovl.innerHTML = `<div class="pal-box" style="margin:16vh auto 0;max-width:520px" onclick="event.stopPropagation()">
    <div style="padding:16px 18px">
      <input class="inp" id="qnT" placeholder="Τι πρέπει να γίνει; (Enter)" style="font-size:15px;margin-bottom:10px">
      <div style="display:flex;gap:8px">
        <select class="inp" id="qnP" style="flex:1">${S.boot.projects.map(p =>
          `<option value="${p.id}" ${p.id === S.project ? 'selected' : ''}>${esc(p.name)}</option>`).join('')}</select>
        <button class="btn btn-p" id="qnGo">Δημιουργία</button>
      </div></div></div>`;
  document.body.appendChild(ovl);
  const inp = $('#qnT'); inp.focus();
  const create = async () => {
    if (!inp.value.trim()) return;
    const r = await api('quick_task', {project: +$('#qnP').value, title: inp.value.trim(), status: 0});
    ovl.remove(); toast('Δημιουργήθηκε');
    openTask(r.id);
  };
  inp.onkeydown = e => { if (e.key === 'Enter') create(); };
  $('#qnGo').onclick = create;
}
window.CNP.quickNew = quickNew;

/* ═════════ Λίστα v2 — grouping + saved views ═════════ */
R.list = async function () {
  setTop('Λίστα tasks', 'Όλα τα tasks ομαδοποιημένα — g+l');
  const c = $('#content');
  // ίδια δομή με τη Βιβλιοθήκη γνώσης: search + chips + ομαδοποίηση + φόρμα πίσω από κουμπί
  const f = R.list._f = R.list._f || {open: 1, group: 'project', proj: '', q: '', fs: '', fa: '', mine: false, closed: {}};
  const views = JSON.parse(localStorage.cnpViews || '[]');
  let D = {tasks: []};
  const GROUPS = {project: 'Ανά project', status: 'Ανά στήλη', assignee: 'Ανά χειριστή', prio: 'Ανά προτεραιότητα', '': 'Χωρίς ομαδοποίηση'};
  const prioDot = p => ['#8595ac', '#eba63c', '#e2515f'][p] || '#8595ac';
  const prioName = p => ['Κανονική', 'Υψηλή', 'Κρίσιμη'][p] || 'Κανονική';

  c.innerHTML = `
  <div class="card kb-search">
    <div class="kb-srow">
      <div class="kb-sinput"><span class="kb-sico">${I.search}</span>
        <input class="inp" id="lfQ" placeholder="Ψάξε τα πάντα — τίτλο, project, χειριστή, κατάσταση…" value="${esc(f.q || '')}"></div>
      <button class="btn btn-o btn-sm" id="lfCsv" title="Εξαγωγή CSV">${I.download} CSV</button>
      <button class="btn btn-p btn-sm" id="lfNew">${I.plus} Νέο task</button>
    </div>
    <div class="kb-filters">
      <button class="kb-chip${f.proj === '' ? ' on' : ''}" data-lproj="">Όλα <b id="lcAll"></b></button>
      <span class="chipwrap" id="lProjChips"></span>
      <select class="inp kb-sort" id="lfG">${Object.entries(GROUPS).map(([k, l]) => `<option value="${k}" ${f.group === k ? 'selected' : ''}>${l}</option>`).join('')}</select>
      <label class="kb-mine"><input type="checkbox" id="lfO" ${f.open ? 'checked' : ''}> Μόνο ανοιχτά</label>
      <label class="kb-mine"><input type="checkbox" id="lfM" ${f.mine ? 'checked' : ''}> Μόνο δικά μου</label>
      <button class="btn btn-o btn-sm" id="lfSave" title="Αποθήκευση αυτών των φίλτρων ως view">${I.pin}</button>
    </div>
    ${views.length ? `<div class="kb-filters" style="border-top:0;padding-top:0;margin-top:7px">
      ${views.map((v, i) => `<span class="kb-chip" style="padding-right:4px">${I.pin}
        <span data-view="${i}" style="cursor:pointer">${esc(v.name)}</span>
        <b data-viewdel="${i}" style="cursor:pointer;padding:0 5px;opacity:.5">✕</b></span>`).join('')}</div>` : ''}
  </div>
  <div id="lForm"></div>
  <div id="lRes"><div class="skel" style="height:220px"></div></div>`;

  /* ── γραμμή task (ίδιο ύφος με τις καταχωρήσεις γνώσης) ── */
  const row = t => {
    const stt = statusOf(t.status), over = t.due && t.due < today() && !t.done;
    return `<div class="kb-item kb-trow" data-task="${t.id}">
      <span class="kb-dot" style="background:${prioDot(t.prio)}" title="Προτεραιότητα: ${prioName(t.prio)}"></span>
      <b>${esc(t.title)}</b>
      <span class="kb-sum-meta">
        ${t.ball ? `<span class="ball ${t.ball === S.boot.me.id ? 'me' : ''}">⚡${esc(adminIni(t.ball))}</span>` : ''}
        ${f.group !== 'project' ? `<span class="kb-tag" style="background:${t.pcolor}18;color:${t.pcolor}">${esc(t.pname)}</span>` : ''}
        <span class="kb-tag" style="background:${stt.color}18;color:${stt.color}">${esc(stt.title)}</span>
        ${t.assignee ? `<span class="mut">${esc(adminName(t.assignee))}</span>` : '<span class="mut">χωρίς ανάθεση</span>'}
        ${t.due ? `<span class="${over ? 'kb-tag' : 'mut'}" ${over ? 'style="background:#e2515f18;color:#e2515f"' : ''}>${dShort(t.due)}</span>` : ''}
        ${t.mins ? `<span class="mut">${fmtMin(t.mins)}</span>` : ''}
      </span></div>`;
  };

  const norm = s => String(s || '').toLowerCase()
    .replace(/ά/g, 'α').replace(/έ/g, 'ε').replace(/ή/g, 'η').replace(/[ίϊΐ]/g, 'ι')
    .replace(/ό/g, 'ο').replace(/[ύϋΰ]/g, 'υ').replace(/ώ/g, 'ω').replace(/ς/g, 'σ');
  const match = t => !f.q || norm([t.title, t.pname, statusOf(t.status).title,
    t.assignee ? adminName(t.assignee) : '', prioName(t.prio)].join(' ')).includes(norm(f.q));

  const render = () => {
    let list = D.tasks.filter(match);
    if (f.proj !== '') list = list.filter(t => t.project == f.proj);
    if (f.mine) list = list.filter(t => t.assignee === S.boot.me.id);

    // μετρητές ανά project για τα chips
    const cnt = {};
    D.tasks.filter(match).forEach(t => { cnt[t.project] = (cnt[t.project] || 0) + 1; });
    $('#lcAll').textContent = D.tasks.filter(match).length;
    $('#lProjChips').innerHTML = S.boot.projects.filter(p => cnt[p.id]).map(p =>
      `<button class="kb-chip${f.proj == p.id ? ' on' : ''}" data-lproj="${p.id}" style="--kc:${p.color}">
        <span class="kb-dot" style="background:${p.color}"></span>${esc(p.name)} <b>${cnt[p.id]}</b></button>`).join('');

    const el = $('#lRes');
    if (!list.length) {
      el.innerHTML = `<div class="card"><div class="empty" style="padding:40px">
        <div class="big">${I.list}</div>
        <b style="color:var(--ink);font-size:15px">${D.tasks.length ? 'Κανένα task με αυτά τα φίλτρα' : 'Καμία εργασία ακόμη'}</b>
        <div class="mut" style="font-size:12.5px;margin-top:6px">${D.tasks.length ? 'Καθάρισε την αναζήτηση ή τα φίλτρα.' : 'Ξεκίνα προσθέτοντας το πρώτο task.'}</div>
        <button class="btn btn-p" id="lfNew2" style="margin-top:14px">${I.plus} Νέο task</button></div></div>`;
      bindRows();
      return;
    }
    if (!f.group) {
      el.innerHTML = `<div class="card kb-group"><div class="card-b kb-gbody">${list.map(row).join('')}</div></div>`;
      bindRows();
      return;
    }
    const keyOf = t => f.group === 'status' ? statusOf(t.status).title
      : f.group === 'assignee' ? (t.assignee ? adminName(t.assignee) : 'Χωρίς ανάθεση')
        : f.group === 'project' ? t.pname : prioName(t.prio);
    const colOf = t => f.group === 'status' ? statusOf(t.status).color
      : f.group === 'project' ? t.pcolor : f.group === 'prio' ? prioDot(t.prio) : '#8595ac';
    const groups = {};
    list.forEach(t => { const k = keyOf(t); (groups[k] = groups[k] || {col: colOf(t), rows: []}).rows.push(t); });
    el.innerHTML = Object.entries(groups).map(([g, o]) => `
      <div class="card kb-group">
        <div class="card-h kb-ghead" data-lgrp="${esc(g)}">
          <span class="kb-gbar" style="background:${o.col}"></span>${esc(g)}
          <span class="kb-n">${o.rows.length}</span><span style="flex:1"></span>
          <span class="kb-gchev ${f.closed[g] ? '' : 'open'}">${I.chev}</span>
        </div>
        <div class="card-b kb-gbody" ${f.closed[g] ? 'style="display:none"' : ''}>${o.rows.map(row).join('')}</div>
      </div>`).join('');
    bindRows();
  };

  const bindRows = () => {
    $$('[data-task]').forEach(r => r.onclick = () => openTask(+r.dataset.task));
    $$('[data-lproj]').forEach(b => b.onclick = () => { f.proj = b.dataset.lproj; render(); });
    $$('.kb-ghead').forEach(h => h.onclick = () => {
      const g = h.dataset.lgrp; f.closed[g] = !f.closed[g];
      h.nextElementSibling.style.display = f.closed[g] ? 'none' : '';
      h.querySelector('.kb-gchev').classList.toggle('open', !f.closed[g]);
    });
    const n2 = $('#lfNew2'); if (n2) n2.onclick = () => openForm();
    $$('[data-view]').forEach(b => b.onclick = () => { Object.assign(R.list._f, views[+b.dataset.view].f); R.list(); });
    $$('[data-viewdel]').forEach(b => b.onclick = e => {
      e.stopPropagation();
      views.splice(+b.dataset.viewdel, 1);
      localStorage.cnpViews = JSON.stringify(views); R.list();
    });
  };

  /* ── φόρμα νέου task: ΚΛΕΙΣΤΗ by default, ανοίγει με «Νέο task» ── */
  const openForm = () => {
    if (!S.boot.projects.length) { toast('Δεν έχεις projects', true); return; }
    const FULL = S.boot.me.full;
    $('#lForm').innerHTML = `<div class="card kb-form">
      <div class="card-h">${I.plus} Νέο task</div>
      <div class="card-b">
        <label>Τι πρέπει να γίνει;</label>
        <input class="inp" id="ntT" placeholder="Τίτλος του task">
        <div class="frow" style="margin-top:11px">
          <div><label>Project</label><select class="inp" id="ntP">${S.boot.projects.map(p =>
            `<option value="${p.id}" ${p.id == (f.proj || S.project) ? 'selected' : ''}>${esc(p.name)}</option>`).join('')}</select></div>
          <div><label>Κατάσταση</label><select class="inp" id="ntS">${S.boot.statuses.map(s =>
            `<option value="${s.id}">${esc(s.title)}</option>`).join('')}</select></div>
        </div>
        <div class="frow" style="margin-top:11px">
          <div><label>Χειριστής${FULL ? '' : ' <span class="mut">(αυτο-ανάθεση)</span>'}</label>
            ${FULL ? `<select class="inp" id="ntA"><option value="">— χωρίς ανάθεση —</option>
              ${S.boot.admins.map(a => `<option value="${a.id}">${esc(a.name)}</option>`).join('')}</select>`
              : `<input class="inp" value="${esc(S.boot.me.name)}" disabled>`}</div>
          <div><label>Προθεσμία</label><input class="inp" type="date" id="ntD"></div>
        </div>
        ${FULL ? `<label style="margin-top:11px;display:block">Προτεραιότητα</label>
          <select class="inp" id="ntR" style="max-width:220px">
            <option value="0">Κανονική</option><option value="1">Υψηλή</option><option value="2">Κρίσιμη</option></select>` : ''}
        <label style="margin-top:11px;display:block">Περιγραφή <span class="mut">(προαιρετικά)</span></label>
        ${rteHtml('ntX', '', 'Λεπτομέρειες, βήματα, σύνδεσμοι…', {min: 110})}
        <div style="display:flex;gap:9px;margin-top:14px;justify-content:flex-end">
          <button class="btn btn-o" id="ntCancel">Άκυρο</button>
          <button class="btn btn-p" id="ntGo">${I.save} Δημιουργία</button></div>
      </div></div>`;
    $('#lForm').scrollIntoView({behavior: 'smooth', block: 'nearest'});
    setTimeout(() => $('#ntT').focus(), 40);
    $('#ntCancel').onclick = () => { $('#lForm').innerHTML = ''; };
    $('#ntT').onkeydown = e => { if (e.key === 'Enter') { $('#ntGo').click(); } };
    $('#ntGo').onclick = async () => {
      const title = $('#ntT').value.trim();
      if (!title) { toast('Γράψε τίτλο', true); return; }
      const r = await api('quick_task', {project: +$('#ntP').value, title, status: +$('#ntS').value})
        .catch(e => ({err: e.message}));
      if (r.err || !r.id) { toast(r.err || 'Δεν δημιουργήθηκε', true); return; }
      // τα υπόλοιπα πεδία με δεύτερη κλήση (το quick_task φτιάχνει μόνο τίτλο/project/status)
      const extra = {task: r.id, descr: rteVal('ntX'), due: $('#ntD').value};
      if (S.boot.me.full) {
        extra.assignee = +(($('#ntA') || {}).value || 0);
        extra.prio = +(($('#ntR') || {}).value || 0);
      }
      await api('save_task', extra).catch(() => {});
      toast('Το task δημιουργήθηκε');
      $('#lForm').innerHTML = '';
      await load();
    };
  };

  // Το q ΔΕΝ πάει στον server: το tasksFiltered ψάχνει μόνο title/descr, οπότε αναζήτηση
  // κατά χειριστή/project/κατάσταση θα γύριζε 0. Ό,τι αφορά κείμενο γίνεται client-side (match).
  const load = async () => {
    const qs = ['fs=' + encodeURIComponent(f.fs || ''), 'fa=' + encodeURIComponent(f.fa || ''),
      'open=' + (f.open ? 1 : 0)].join('&');
    D = await api('list&' + qs).catch(() => ({tasks: []}));
    render();
  };

  let qt;
  $('#lfQ').oninput = () => { clearTimeout(qt); qt = setTimeout(() => { f.q = $('#lfQ').value.trim(); render(); }, 180); };
  $('#lfG').onchange = () => { f.group = $('#lfG').value; render(); };
  $('#lfO').onchange = () => { f.open = $('#lfO').checked ? 1 : 0; load(); };
  $('#lfM').onchange = () => { f.mine = $('#lfM').checked; render(); };
  $('#lfNew').onclick = openForm;
  $('#lfSave').onclick = async () => {
    const name = await cnpPrompt('Όνομα view:', {title: I.pin + ' Αποθήκευση view', placeholder: 'π.χ. Bugs Τεχνικού', ok: 'Αποθήκευση'});
    if (!name) { return; }
    views.push({name, f: Object.assign({}, f, {closed: {}})});
    localStorage.cnpViews = JSON.stringify(views);
    toast('Το view αποθηκεύτηκε'); R.list();
  };
  $('#lfCsv').onclick = () => {
    const esc2 = v => '"' + String(v == null ? '' : v).replaceAll('"', '""') + '"';
    const rows = [['Task', 'Project', 'Status', 'Χειριστής', 'Λήξη', 'Λεπτά'].map(esc2).join(';')];
    D.tasks.filter(match).forEach(t => rows.push([t.title, t.pname, statusOf(t.status).title,
      t.assignee ? adminName(t.assignee) : '', t.due || '', t.mins || 0].map(esc2).join(';')));
    const blob = new Blob(['﻿' + rows.join('\n')], {type: 'text/csv;charset=utf-8'});
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob); a.download = 'tasks.csv'; a.click();
  };
  await load();
};


/* ═════════ 🎯 ΠΛΑΝΟ ΗΜΕΡΑΣ (managers) ═════════ */
R.triage = async function () {
  setTop('Πλάνο ημέρας', 'Πρόταση: με ποια tickets ασχολούμαστε σήμερα — κρισιμότητα · αναμονή · SLA');
  const c = $('#content');
  c.innerHTML = '<div class="skel" style="height:340px"></div>';
  const d = await api('triage').catch(() => null);
  if (!d) { c.innerHTML = `<div class="empty"><div class="big">${I.lock}</div>Μόνο για διαχειριστές</div>`; return; }
  const sm = d.summary;
  const whyChip = (label, v, cls) => v > 0 ? `<span class="pill ${cls}" title="${label}">${label} +${v}</span>` : '';
  c.innerHTML = `
  <div class="grid g4" style="margin-bottom:16px">
    ${suStat(I.ticket, sm.open, 'Ανοιχτά tickets', sm.open ? 'var(--brand)' : 'var(--ok)')}
    ${suStat(I.chat, sm.waiting, 'Περιμένουν απάντησή μας', sm.waiting ? 'var(--warn)' : 'var(--ok)')}
    ${suStat(I.alert, sm.slaRisk, 'SLA σε κίνδυνο / εκτός', sm.slaRisk ? 'var(--bad)' : 'var(--ok)')}
    ${suStat(I.user, sm.unassigned, 'Χωρίς ανάθεση', sm.unassigned ? 'var(--warn)' : 'var(--ok)')}
  </div>
  <div class="card"><div class="card-h">${I.target} Πρόταση ημέρας — με σειρά προτεραιότητας
    <span class="mut" style="margin-left:auto;font-size:11px;font-weight:400">σκορ = κρισιμότητα + αναμονή + SLA + συμβόλαιο + παλαιότητα</span></div>
  ${d.plan.map((t, i) => `
    <div class="set-row" data-tgo="${t.id}" style="cursor:pointer;gap:11px;${i < 3 ? 'background:color-mix(in srgb, var(--warn) 6%, transparent)' : ''}">
      <b style="font-size:15px;color:${i < 3 ? 'var(--bad)' : 'var(--mut)'};width:26px;text-align:center">${i + 1}</b>
      <div style="flex:1;min-width:0">
        <b style="font-size:13px">#${esc(t.tid)} — ${esc(t.title)}</b>
        <div class="mut" style="font-size:11.5px">${esc(t.client || '—')}
          ${t.flag ? ' · ' + esc(adminName(t.flag)) : ' · <b style="color:var(--warn)">χωρίς ανάθεση</b>'}
          ${t.waiting ? ` · περιμένει ${t.waitH < 24 ? t.waitH + 'ω' : Math.round(t.waitH / 24) + 'ημ'}` : ''}</div>
        <div style="display:flex;gap:5px;flex-wrap:wrap;margin-top:4px">
          ${whyChip('Κρισιμότητα', t.why.urgency, t.urgency === 'High' ? 'pill-bad' : 'pill-mut')}
          ${whyChip('Αναμονή', t.why.wait, 'pill-warn')}
          ${whyChip('SLA', t.why.sla, t.why.sla >= 30 ? 'pill-bad' : 'pill-warn')}
          ${whyChip('Συμβόλαιο', t.why.contract, 'pill-info')}
          ${whyChip('Παλαιότητα', t.why.age, 'pill-mut')}
        </div>
      </div>
      ${t.suggestAssignee ? `<button class="btn btn-sm btn-o" data-assign="${t.id}" data-aid="${t.suggestAssignee.id}"
        title="Έχει λύσει ${t.suggestAssignee.solved} παρόμοια" onclick="event.stopPropagation()">${I.bulb} ${esc(t.suggestAssignee.name.split(' ')[0])} ${t.suggestAssignee.solved}×</button>` : ''}
      <div style="text-align:right">
        <b style="font-size:19px;color:${t.score >= 60 ? 'var(--bad)' : t.score >= 35 ? 'var(--warn)' : 'var(--ok)'}">${t.score}</b>
        <div class="mut" style="font-size:10px">σκορ</div>
      </div>
    </div>`).join('') || '<div class="empty" style="padding:30px">Κανένα ανοιχτό ticket 🎉</div>'}
  </div>
  <div class="grid g2">
    <div class="card"><div class="card-h">${I.repeat} Επαναλαμβανόμενα προβλήματα (90 ημ.)</div>
      <div class="card-b" id="trRec"><div class="skel" style="height:60px"></div></div></div>
    <div class="card"><div class="card-h">${I.heart} Υγεία πελατών — χαμηλότερο σκορ πρώτα</div>
      <div class="card-b" id="trHealth"><div class="skel" style="height:60px"></div></div></div>
  </div>`;
  $$('[data-tgo]').forEach(r => r.onclick = () => go('inbox', +r.dataset.tgo));
  $$('[data-assign]').forEach(b => b.onclick = async e => {
    e.stopPropagation();
    if (!(await cnpConfirm(`Ανάθεση στον/στην ${b.textContent.trim().slice(2)};`, {title: I.bulb + ' Έξυπνη ανάθεση', ok: 'Ανάθεση'}))) return;
    await api('ticket_update', {ticket: +b.dataset.assign, flag: +b.dataset.aid});
    toast('Ανατέθηκε ✓'); R.triage();
  });
  // lazy: επαναλαμβανόμενα + υγεία
  api('recurrent').then(r => {
    $('#trRec').innerHTML = r.clusters.length ? r.clusters.map(cl => `
      <details class="set-row" style="display:block">
        <summary style="cursor:pointer;display:flex;gap:8px;align-items:baseline">
          <span class="pill pill-bad">${cl.count}×</span>
          <b style="font-size:12.5px;flex:1">${esc(cl.label)}</b>
          <span class="mut" style="font-size:10.5px">${cl.clients.length} πελάτες</span></summary>
        <div style="padding:7px 4px;font-size:12px">
          ${cl.tickets.map(t => `<div data-tgo2="${t.id}" style="cursor:pointer;padding:2px 0">
            ${I.ticket} <b>#${esc(t.tid)}</b> ${esc(t.title)} <span class="mut">· ${esc(t.client || '')} · ${dShort(t.date)} · ${esc(t.status)}</span></div>`).join('')}
          <div class="mut" style="margin-top:5px;font-size:11px">${I.bulb} Υποψήφιο για μόνιμη λύση / εγγραφή στη Γνώση / έργο πελάτη</div>
        </div>
      </details>`).join('') : '<div class="empty" style="padding:16px">Κανένα μοτίβο — καλό σημάδι 🎉</div>';
    $$('#trRec [data-tgo2]').forEach(x => x.onclick = () => go('inbox', +x.dataset.tgo2));
  }).catch(() => {});
  api('client_health').then(r => {
    $('#trHealth').innerHTML = r.clients.length ? r.clients.map(cH => `
      <div class="set-row" data-cgo="${cH.client}" style="cursor:pointer">
        <b style="font-size:15px;width:36px;text-align:center;color:${cH.score < 50 ? 'var(--bad)' : cH.score < 75 ? 'var(--warn)' : 'var(--ok)'}">${cH.score}</b>
        <div style="flex:1;min-width:0"><b style="font-size:12.5px">${esc(cH.name)}</b>
          <div class="mut" style="font-size:10.5px">${cH.tickets90} tickets/90ημ${cH.open ? ` · ${cH.open} ανοιχτά` : ''}${cH.slaBreaches ? ` · ${cH.slaBreaches} SLA σπασμένα` : ''}${cH.owed ? ` · οφείλει ${cH.owed}€` : ''}</div></div>
        <span class="mut">→</span></div>`).join('') : '<div class="empty" style="padding:16px">—</div>';
    $$('#trHealth [data-cgo]').forEach(x => x.onclick = () => { window.CNP.go('client360'); });
  }).catch(() => {});
};

/* ═════════ 📚 ΓΝΩΣΗ — «το έχω ξαναλύσει;» ═════════ */
R.knowledge = async function () {
  setTop('Γνώση', 'Βιβλιοθήκη γνώσης ανά προϊόν — ψάξε αν το πρόβλημα έχει ξαναλυθεί');
  const c = $('#content');
  const st = R.knowledge._st = R.knowledge._st || {q: '', prod: '', sort: 'uses', mine: false, closed: {}, page: {}};
  const PER = 25;   // πόσα άρθρα ανά ομάδα πριν το «Περισσότερα»
  let D = {items: [], products: [], unfiled: 0};
  const prod = id => D.products.find(p => p.id === +id);
  const KSORT = {uses: 'Πιο χρήσιμα', recent: 'Πιο πρόσφατα', title: 'Αλφαβητικά'};

  c.innerHTML = `
  <div class="card kb-search">
    <div class="kb-srow">
      <div class="kb-sinput"><span class="kb-sico">${I.search}</span>
        <input class="inp" id="kQ" placeholder="Ψάξε τα πάντα — τίτλο, λέξεις-κλειδιά, κείμενο λύσης, προϊόν…" value="${esc(st.q)}"></div>
      <button class="btn btn-o btn-sm" id="kDeep" title="Ψάξε και στο ιστορικό των tickets">${I.ticket} Και στα tickets</button>
      <button class="btn btn-o btn-sm" id="kImp" title="Εισαγωγή από online τεκμηρίωση/εγχειρίδιο">${I.download} Εισαγωγή από URL</button>
      <button class="btn btn-p btn-sm" id="kNew">${I.plus} Προσθήκη γνώσης</button>
    </div>
    <div class="kb-filters">
      <button class="kb-chip${st.prod === '' ? ' on' : ''}" data-kprod="">Όλα <b id="kcAll"></b></button>
      <span class="chipwrap" id="kProdChips"></span>
      <select class="inp kb-sort" id="kSort">${Object.entries(KSORT).map(([k, l]) => `<option value="${k}" ${st.sort === k ? 'selected' : ''}>${l}</option>`).join('')}</select>
      <label class="kb-mine"><input type="checkbox" id="kMine" ${st.mine ? 'checked' : ''}> Μόνο δικά μου</label>
    </div>
  </div>
  <div id="kForm"></div>
  <div id="kRes"></div>
  <div id="kbBulk" class="kb-bulk" style="display:none"></div>
  <div id="kbList"><div class="skel" style="height:120px"></div></div>`;

  /* ── κάρτα γνώσης ── */
  const kbBox = k => {
    const p = prod(k.areaId);
    return `<details class="kb-item" data-kbid="${k.id}">
      <summary>
        <input type="checkbox" class="kb-pick" value="${k.id}" title="Επιλογή για μαζική ενέργεια"
          onclick="event.stopPropagation()">
        <span class="kb-dot" style="background:${p ? p.color : '#8595ac'}"></span>
        <b>${esc(k.title)}</b>
        <span class="kb-sum-meta">
          ${(k.relAreas || []).map(r => { const rp = prod(r); return rp ? `<span class="kb-tag" style="background:${rp.color}18;color:${rp.color}">${esc(rp.name)}</span>` : ''; }).join('')}
          ${k.tags ? `<span class="kb-tag kb-tag-mut">${esc(k.tags)}</span>` : ''}
          ${k.uses ? `<span class="mut">${k.uses}× χρήση</span>` : ''}
        </span>
      </summary>
      <div class="kb-body">
        <div class="kb-sol" data-kbsol="${k.id}">${esc(k.excerpt || '')}${(k.excerpt || '').length >= 400 ? '…' : ''}</div>
        <div class="kb-foot">
          <span class="mut">${k.by ? esc(k.by) : ''}${k.at ? ' · ' + dShort(k.at) : ''}${k.keywords ? ' · ' + esc(k.keywords) : ''}</span>
          <span style="flex:1"></span>
          <button class="btn btn-sm btn-o" data-kcopy="${k.id}" title="Αντιγραφή λύσης">${I.copy}</button>
          <button class="btn btn-sm btn-o" data-kedit="${k.id}">${I.edit} Επεξεργασία</button>
          ${S.boot.me.full ? `<button class="btn btn-sm btn-o" style="color:var(--bad)" data-kdel="${k.id}">${I.trash}</button>` : ''}
        </div>
      </div>
    </details>`;
  };

  /* ── καθολικό φιλτράρισμα (τίτλος + λέξεις + ετικέτες + λύση + όνομα προϊόντος) ── */
  const norm = s => String(s || '').toLowerCase()
    .replace(/[άἀ]/g, 'α').replace(/έ/g, 'ε').replace(/ή/g, 'η').replace(/[ίϊΐ]/g, 'ι')
    .replace(/ό/g, 'ο').replace(/[ύϋΰ]/g, 'υ').replace(/ώ/g, 'ω').replace(/ς/g, 'σ');
  const match = k => {
    if (!st.q) return true;
    const p = prod(k.areaId), rel = (k.relAreas || []).map(r => (prod(r) || {}).name || '').join(' ');
    // excerpt αντί για ολόκληρη τη λύση (η λίστα δεν την κατεβάζει πια)· για βαθιά
    // αναζήτηση μέσα στο πλήρες κείμενο υπάρχει το «Και στα tickets» (server-side).
    return norm([k.title, k.keywords, k.tags, k.excerpt, p ? p.name : '', rel].join(' ')).includes(norm(st.q));
  };

  const render = () => {
    let list = D.items.filter(match);
    if (st.prod !== '') {
      const pid = +st.prod;
      list = st.prod === 'none' ? D.items.filter(k => !k.areaId && match(k))
        : list.filter(k => k.areaId === pid || (k.relAreas || []).includes(pid));
    }
    if (st.mine) list = list.filter(k => k.byId === S.boot.me.id);
    list.sort(st.sort === 'title' ? (a, b) => a.title.localeCompare(b.title, 'el')
      : st.sort === 'recent' ? (a, b) => (b.at || '').localeCompare(a.at || '') || b.id - a.id
        : (a, b) => b.uses - a.uses || b.id - a.id);

    // ομαδοποίηση ανά κύριο προϊόν
    const groups = [];
    D.products.forEach(p => {
      const items = list.filter(k => k.areaId === p.id);
      const related = list.filter(k => k.areaId !== p.id && (k.relAreas || []).includes(p.id));
      if (items.length || (st.prod !== '' && related.length)) groups.push({p, items, related});
    });
    const none = list.filter(k => !k.areaId);
    if (none.length) groups.push({p: {id: 0, name: 'Χωρίς προϊόν', color: '#8595ac'}, items: none, related: []});

    $('#kbList').innerHTML = list.length ? groups.map(g => `
      <div class="card kb-group">
        <div class="card-h kb-ghead" data-kgrp="${g.p.id}">
          <span class="kb-gbar" style="background:${g.p.color}"></span>
          ${esc(g.p.name)}
          <span class="kb-n">${g.items.length}</span>
          <span style="flex:1"></span>
          <span class="kb-gchev ${st.closed[g.p.id] ? '' : 'open'}">${I.chev || '⌄'}</span>
        </div>
        <div class="card-b kb-gbody" ${st.closed[g.p.id] ? 'style="display:none"' : ''}>
          ${(() => {
            // σελιδοποίηση ανά ομάδα: κάθε σελίδα ΑΝΤΙΚΑΘΙΣΤΑ την προηγούμενη —
            // το ύψος της ενότητας μένει σταθερό, όσα άρθρα κι αν έχει το προϊόν.
            const pages = Math.max(1, Math.ceil(g.items.length / PER));
            const page = Math.min(Math.max(1, st.page[g.p.id] || 1), pages);
            const from = (page - 1) * PER;
            const slice = g.items.slice(from, from + PER);
            return (slice.map(kbBox).join('') || '<div class="mut" style="font-size:12.5px;padding:4px 2px">Καμία δική του καταχώρηση.</div>')
              + (pages > 1 ? `<div class="kb-pager">
                  <span class="mut"><b>${from + 1}–${from + slice.length}</b> από <b>${g.items.length}</b></span>
                  <span style="flex:1"></span>
                  <button class="btn btn-o btn-sm" data-kpg="${g.p.id}:1" ${page === 1 ? 'disabled' : ''} title="Πρώτη">«</button>
                  <button class="btn btn-o btn-sm" data-kpg="${g.p.id}:${page - 1}" ${page === 1 ? 'disabled' : ''}>‹ Προηγούμενη</button>
                  <span class="kb-pgn">${page} / ${pages}</span>
                  <button class="btn btn-o btn-sm" data-kpg="${g.p.id}:${page + 1}" ${page === pages ? 'disabled' : ''}>Επόμενη ›</button>
                  <button class="btn btn-o btn-sm" data-kpg="${g.p.id}:${pages}" ${page === pages ? 'disabled' : ''} title="Τελευταία">»</button>
                </div>` : '');
          })()}
          ${g.related.length ? `<div class="kb-rel"><div class="kb-rel-h">${I.link} Συναφή από άλλα προϊόντα <span class="kb-n">${g.related.length}</span></div>
            ${g.related.slice(0, PER).map(kbBox).join('')}</div>` : ''}
        </div>
      </div>`).join('')
      : `<div class="card"><div class="empty" style="padding:40px">
          <div class="big">${I.book}</div>
          <b style="color:var(--ink);font-size:15px">${st.q || st.prod !== '' || st.mine ? 'Κανένα αποτέλεσμα' : 'Η βιβλιοθήκη είναι άδεια'}</b>
          <div class="mut" style="font-size:12.5px;margin-top:6px">${st.q || st.prod !== '' || st.mine
            ? 'Δοκίμασε άλλη λέξη ή καθάρισε τα φίλτρα.'
            : 'Κάθε λύση που καταγράφεις εδώ γλιτώνει χρόνο στην επόμενη φορά.'}</div>
          <button class="btn btn-p" id="kNew2" style="margin-top:14px">${I.plus} Προσθήκη γνώσης</button></div></div>`;

    $('#kcAll').textContent = D.items.length;
    $('#kProdChips').innerHTML = D.products.filter(p => p.count).map(p =>
      `<button class="kb-chip${st.prod == p.id ? ' on' : ''}" data-kprod="${p.id}" style="--kc:${p.color}">
        <span class="kb-dot" style="background:${p.color}"></span>${esc(p.name)} <b>${p.count}</b></button>`).join('')
      + (D.unfiled ? `<button class="kb-chip${st.prod === 'none' ? ' on' : ''}" data-kprod="none">Χωρίς προϊόν <b>${D.unfiled}</b></button>` : '');
    bindList();
  };

  const bindList = () => {
    $$('[data-kprod]').forEach(b => b.onclick = () => { st.prod = b.dataset.kprod; st.page = {}; render(); });
    $$('.kb-ghead').forEach(h => h.onclick = () => {
      const id = h.dataset.kgrp; st.closed[id] = !st.closed[id];
      const body = h.nextElementSibling;
      body.style.display = st.closed[id] ? 'none' : '';
      h.querySelector('.kb-gchev').classList.toggle('open', !st.closed[id]);
    });
    // πλήρες κείμενο ΜΟΝΟ όταν ανοίξει το άρθρο (η λίστα φέρνει μόνο απόσπασμα)
    $$('.kb-item').forEach(d => d.addEventListener('toggle', async () => {
      if (!d.open) { return; }
      const box = d.querySelector('.kb-sol');
      if (!box || box.dataset.loaded) { return; }
      box.dataset.loaded = '1';
      const r = await api('kb_get&id=' + box.dataset.kbsol).catch(() => null);
      if (r && r.solution) {
        box.innerHTML = r.solution;
        // κάθε πίνακας σε δικό του scroller — αλλιώς οι στήλες στριμώχνονται και
        // οι επικεφαλίδες σπάνε στη μέση σε στενές οθόνες
        box.querySelectorAll('table').forEach(t => {
          if (t.parentElement && t.parentElement.classList.contains('kb-tw')) { return; }
          const w = document.createElement('div');
          w.className = 'kb-tw';
          t.replaceWith(w);
          w.appendChild(t);
        });
        const k = D.items.find(x => x.id === +box.dataset.kbsol);
        if (k) { k.solution = r.solution; }
      }
    }));
    $$('[data-kedit]').forEach(b => b.onclick = async e => {
      e.preventDefault(); e.stopPropagation();
      const k = D.items.find(x => x.id === +b.dataset.kedit);
      if (k && !k.solution) {                       // η φόρμα χρειάζεται το πλήρες κείμενο
        const r = await api('kb_get&id=' + k.id).catch(() => null);
        if (r) { k.solution = r.solution; }
      }
      openForm(k);
    });
    $$('[data-kcopy]').forEach(b => b.onclick = async e => {
      e.preventDefault(); e.stopPropagation();
      const k = D.items.find(x => x.id === +b.dataset.kcopy);
      if (k && !k.solution) {
        const r = await api('kb_get&id=' + k.id).catch(() => null);
        if (r) { k.solution = r.solution; }
      }
      navigator.clipboard.writeText(k.solution || '').then(() => { toast('Η λύση αντιγράφηκε'); api('kb_use', {id: k.id}).catch(() => {}); });
    });
    $$('[data-kdel]').forEach(b => b.onclick = async e => {
      e.preventDefault(); e.stopPropagation();
      if (!(await cnpConfirm('Διαγραφή αυτής της γνώσης από τη βιβλιοθήκη;', {danger: true, ok: 'Διαγραφή'}))) return;
      await api('kb_del', {id: +b.dataset.kdel}); toast('Διαγράφηκε'); load();
    });
    $$('[data-kpg]').forEach(b => b.onclick = () => {
      if (b.disabled) { return; }
      const parts = b.dataset.kpg.split(':');
      st.page[parts[0]] = +parts[1];
      render();
      // φέρε την κορυφή της ενότητας στο οπτικό πεδίο — βλέπεις αμέσως τα νέα αποτελέσματα
      const head = document.querySelector('.kb-ghead[data-kgrp="' + parts[0] + '"]');
      if (head) { head.scrollIntoView({behavior: 'smooth', block: 'start'}); }
    });
    const n2 = $('#kNew2'); if (n2) n2.onclick = () => openForm(null);
    $$('.kb-pick').forEach(c => c.onchange = bulkBar);
    bulkBar();
  };

  /* ── μαζικές ενέργειες: εμφανίζεται μόλις επιλέξεις έστω ένα ── */
  const picked = () => $$('.kb-pick').filter(c => c.checked).map(c => +c.value);
  const bulkBar = () => {
    const bar = $('#kbBulk');
    if (!bar) { return; }
    const ids = picked();
    if (!ids.length) { bar.style.display = 'none'; bar.innerHTML = ''; return; }
    bar.style.display = '';
    bar.innerHTML = `
      <b>${ids.length}</b> επιλεγμένα
      <button class="btn btn-o btn-sm" id="kbAll">Επιλογή όλων (${$$('.kb-pick').length})</button>
      <button class="btn btn-o btn-sm" id="kbNone">Καθαρισμός</button>
      <span style="flex:1"></span>
      <select class="inp" id="kbArea" style="width:auto;min-width:150px">
        <option value="">— ορισμός προϊόντος —</option>
        <option value="0">χωρίς προϊόν</option>
        ${D.products.map(p => `<option value="${p.id}">${esc(p.name)}</option>`).join('')}</select>
      <input class="inp" id="kbTags" placeholder="ετικέτες…" style="width:130px">
      <button class="btn btn-o btn-sm" id="kbTagGo">Ορισμός ετικετών</button>
      ${S.boot.me.full ? `<button class="btn btn-sm" id="kbDel"
        style="background:var(--bad);color:#fff">${I.trash} Διαγραφή ${ids.length}</button>` : ''}`;
    $('#kbAll').onclick = () => { $$('.kb-pick').forEach(c => c.checked = true); bulkBar(); };
    $('#kbNone').onclick = () => { $$('.kb-pick').forEach(c => c.checked = false); bulkBar(); };
    $('#kbArea').onchange = async () => {
      const v = $('#kbArea').value;
      if (v === '') { return; }
      const r = await api('kb_bulk', {op: 'area', ids: picked(), areaId: +v}).catch(e => ({err: e.message}));
      if (r.err) { toast(r.err, true); return; }
      toast(`Ορίστηκε προϊόν σε ${r.n} άρθρα`);
      await load();
    };
    $('#kbTagGo').onclick = async () => {
      const r = await api('kb_bulk', {op: 'tags', ids: picked(), tags: $('#kbTags').value}).catch(e => ({err: e.message}));
      if (r.err) { toast(r.err, true); return; }
      toast(`Ενημερώθηκαν ${r.n} άρθρα`);
      await load();
    };
    const del = $('#kbDel');
    if (del) {
      del.onclick = async () => {
        const ids2 = picked();
        if (!(await cnpConfirm(`Οριστική διαγραφή ${ids2.length} άρθρων από τη βιβλιοθήκη;`,
          {title: I.alert + ' Μαζική διαγραφή', danger: true, ok: 'Διαγραφή ' + ids2.length}))) { return; }
        const r = await api('kb_bulk', {op: 'delete', ids: ids2}).catch(e => ({err: e.message}));
        if (r.err) { toast(r.err, true); return; }
        toast(`Διαγράφηκαν ${r.n} άρθρα`);
        await load();
      };
    }
  };

  /* ── φόρμα: ΚΛΕΙΣΤΗ by default, ανοίγει με κουμπί ── */
  const openForm = (k) => {
    const sel = k ? (k.relAreas || []) : [];
    $('#kForm').innerHTML = `<div class="card kb-form">
      <div class="card-h">${k ? I.edit + ' Επεξεργασία γνώσης' : I.plus + ' Νέα καταχώρηση γνώσης'}</div>
      <div class="card-b">
        <input type="hidden" id="knId" value="${k ? k.id : 0}">
        <label>Τίτλος προβλήματος</label>
        <input class="inp" id="knT" placeholder="π.χ. 3CX δεν στέλνει voicemail email" value="${esc(k ? k.title : '')}">
        <div class="frow" style="margin-top:11px">
          <div><label>Προϊόν</label>
            <select class="inp" id="knA"><option value="0">— χωρίς προϊόν —</option>
              ${D.products.map(p => `<option value="${p.id}" ${k && k.areaId === p.id ? 'selected' : ''}>${esc(p.name)}</option>`).join('')}</select></div>
          <div><label>Λέξεις-κλειδιά <span class="mut">(βοηθούν την αναζήτηση)</span></label>
            <input class="inp" id="knK" placeholder="3cx, voicemail, smtp" value="${esc(k ? k.keywords : '')}"></div>
        </div>
        <label style="margin-top:11px;display:block">Συναφή προϊόντα <span class="mut">(εμφανίζεται και στις δικές τους ενότητες)</span></label>
        <div class="kb-rel-pick">${D.products.map(p => `<label class="kb-relchk"><input type="checkbox" class="knR" value="${p.id}" ${sel.includes(p.id) ? 'checked' : ''}>
          <span class="kb-dot" style="background:${p.color}"></span>${esc(p.name)}</label>`).join('')}</div>
        <label style="margin-top:11px;display:block">Ετικέτες <span class="mut">(ελεύθερες)</span></label>
        <input class="inp" id="knG" placeholder="π.χ. voip, urgent" style="max-width:280px" value="${esc(k ? k.tags : '')}">
        <label style="margin-top:11px;display:block">Η λύση — βήμα-βήμα</label>
        ${rteHtml('knS', k ? k.solution : '', '1. Πρώτο βήμα · 2. Δεύτερο βήμα · …', {min: 190})}
        <div style="display:flex;gap:9px;margin-top:14px;justify-content:flex-end">
          <button class="btn btn-o" id="knCancel">Άκυρο</button>
          <button class="btn btn-p" id="knAdd">${I.save} Αποθήκευση</button></div>
      </div></div>`;
    $('#kForm').scrollIntoView({behavior: 'smooth', block: 'nearest'});
    setTimeout(() => $('#knT').focus(), 40);
    $('#knCancel').onclick = () => { $('#kForm').innerHTML = ''; };
    $('#knAdd').onclick = async () => {
      const r2 = await api('kb_save', {id: +$('#knId').value, title: $('#knT').value,
        keywords: $('#knK').value, tags: $('#knG').value, solution: rteVal('knS'),
        areaId: +$('#knA').value, relAreas: $$('.knR').filter(x => x.checked).map(x => +x.value)}).catch(e => ({err: e.message}));
      if (r2.err) { toast(r2.err, true); return; }
      toast('Αποθηκεύτηκε στη βιβλιοθήκη');
      $('#kForm').innerHTML = '';
      load();
    };
  };

  /* ── βαθιά αναζήτηση: και στο ιστορικό tickets ── */
  const deep = async () => {
    const q = $('#kQ').value.trim();
    if (q.length < 3) { toast('Γράψε τουλάχιστον 3 χαρακτήρες', true); return; }
    $('#kRes').innerHTML = '<div class="skel" style="height:120px"></div>';
    const r = await api('ksearch&q=' + encodeURIComponent(q)).catch(() => ({kb: [], tickets: []}));
    $('#kRes').innerHTML = `<div class="card"><div class="card-h">${I.ticket} Παρόμοια tickets στο ιστορικό
        <span class="kb-n">${r.tickets.length}</span><span style="flex:1"></span>
        <button class="btn btn-sm btn-o" id="kResX">Κλείσιμο</button></div>
      <div class="card-b">${r.tickets.length ? r.tickets.map(t => `
        <div class="set-row" data-tgo="${t.id}" style="cursor:pointer">
          <span class="pill ${t.status === 'Closed' ? 'pill-ok' : 'pill-info'}">${t.status === 'Closed' ? '✓ λύθηκε' : esc(t.status)}</span>
          <div style="flex:1;min-width:0"><b style="font-size:12.5px">#${esc(t.tid)} — ${esc(t.title)}</b>
            <span class="mut" style="font-size:11px"> · ${esc(t.client || '—')} · ${dShort(t.last)}</span></div>
          <span class="mut">→</span></div>`).join('')
        : '<div class="empty" style="padding:16px">Τίποτα παρόμοιο — ίσως είναι η πρώτη φορά. Μόλις το λύσεις, καταχώρησέ το εδώ!</div>'}</div></div>`;
    $$('#kRes [data-tgo]').forEach(x => x.onclick = () => go('inbox', +x.dataset.tgo));
    $('#kResX').onclick = () => { $('#kRes').innerHTML = ''; };
  };

  const load = async () => {
    D = await api('kb_list').catch(() => ({items: [], products: [], unfiled: 0}));
    render();
  };

  let qt;
  $('#kQ').oninput = () => { clearTimeout(qt); qt = setTimeout(() => { st.q = $('#kQ').value.trim(); st.page = {}; render(); }, 180); };
  $('#kQ').onkeydown = e => { if (e.key === 'Enter') deep(); };
  $('#kDeep').onclick = deep;
  $('#kNew').onclick = () => openForm(null);
  $('#kImp').onclick = () => openImport(D.products, load);
  $('#kSort').onchange = () => { st.sort = $('#kSort').value; render(); };
  $('#kMine').onchange = () => { st.mine = $('#kMine').checked; render(); };
  await load();
};


/* ═════════ 🌐 Εισαγωγή γνώσης από online τεκμηρίωση ═════════
   Δίνεις το URL ενός εγχειριδίου· αν το site είναι WordPress (π.χ. BetterDocs)
   κατεβαίνει ΟΛΟΣ ο κατάλογος άρθρων και διαλέγεις τι θα μπει στην τράπεζα. */
function openImport(products, reload) {
  const ovl = document.createElement('div'); ovl.className = 'ovl show'; ovl.style.zIndex = 300;
  ovl.innerHTML = `<div class="pal-box" style="margin:6vh auto 0;max-width:760px" onclick="event.stopPropagation()">
    <div style="padding:20px 22px" id="impBody">
      <b style="font-size:16px;color:var(--ink);display:flex;align-items:center;gap:9px">${I.download} Εισαγωγή γνώσης από URL</b>
      <div class="mut" style="font-size:12.5px;margin-top:4px">
        Δώσε τη διεύθυνση ενός online εγχειριδίου. Αν βρεθεί κατάλογος άρθρων, θα τα δεις όλα και θα διαλέξεις.</div>
      <div style="display:flex;gap:8px;margin-top:14px">
        <input class="inp" id="impUrl" placeholder="https://example.com/docs/εγχειρίδιο-χρήσης/" style="flex:1">
        <button class="btn btn-p" id="impGo">${I.search} Ανάλυση</button>
      </div>
      <div id="impRes" style="margin-top:14px"></div>
    </div></div>`;
  document.body.appendChild(ovl);
  const box = ovl.querySelector('.pal-box');
  setTimeout(() => $('#impUrl', ovl).focus(), 40);

  const probe = async () => {
    const url = $('#impUrl', ovl).value.trim();
    if (!url) { toast('Δώσε URL', true); return; }
    const res = $('#impRes', ovl);
    res.innerHTML = '<div class="skel" style="height:120px"></div>';
    const d = await api('kb_import_probe', {url}).catch(e => ({err: e.message}));
    if (d.err) { res.innerHTML = `<div class="mut" style="color:var(--bad);font-size:13px">${esc(d.err)}</div>`; return; }
    const cats = d.cats || {};
    const byCat = {};
    d.items.forEach(it => { (byCat[it.catName || '—'] = byCat[it.catName || '—'] || []).push(it); });
    res.innerHTML = `
      <div class="set-row" style="border:0;padding:0 0 10px">
        <div><b style="color:var(--ink)">${d.items.length} άρθρα</b>
          <span class="mut" style="font-size:12px"> · ${esc(d.site)}${d.mode === 'wp' ? ' · WordPress' : ''}</span></div>
        <button class="btn btn-o btn-sm" id="impAll">Επιλογή όλων</button>
        <button class="btn btn-o btn-sm" id="impNone">Καμία</button>
      </div>
      <div class="frow" style="margin-bottom:10px">
        <div><label class="lbl">Προϊόν για όλα</label>
          <select class="inp" id="impArea"><option value="0">— χωρίς προϊόν —</option>
            ${products.map(p => `<option value="${p.id}">${esc(p.name)}</option>`).join('')}</select></div>
        <div><label class="lbl">Ετικέτες</label><input class="inp" id="impTags" placeholder="π.χ. PharmacyOne, εγχειρίδιο"></div>
      </div>
      <div class="imp-list">
        ${Object.entries(byCat).map(([cat, list]) => `
          <div class="imp-cat">
            <label class="imp-cathead"><input type="checkbox" class="impCat" data-cat="${esc(cat)}" checked>
              <b>${esc(cat)}</b> <span class="kb-n">${list.length}</span></label>
            ${list.map(it => `<label class="imp-row${it.exists ? ' has' : ''}">
              <input type="checkbox" class="impIt" data-cat="${esc(cat)}" ${it.exists ? '' : 'checked'}
                data-it='${esc(JSON.stringify({id: it.id, type: it.type || '', title: it.title, link: it.link}))}'>
              <span class="imp-t">${esc(it.title)}</span>
              ${it.exists ? '<span class="pill pill-mut">υπάρχει ήδη</span>' : ''}</label>`).join('')}
          </div>`).join('')}
      </div>
      <label class="kb-mine" style="margin-top:10px"><input type="checkbox" id="impOver"> Ενημέρωση όσων υπάρχουν ήδη</label>
      <div style="display:flex;gap:9px;margin-top:14px;justify-content:flex-end">
        <button class="btn btn-o" id="impCancel">Άκυρο</button>
        <button class="btn btn-p" id="impSave" data-save>${I.download} Εισαγωγή <span id="impN"></span></button></div>`;

    const items = () => $$('.impIt', ovl);
    const count = () => { const n = items().filter(x => x.checked).length; $('#impN', ovl).textContent = '(' + n + ')'; };
    $('#impAll', ovl).onclick = () => { items().forEach(x => x.checked = true); $$('.impCat', ovl).forEach(c => c.checked = true); count(); };
    $('#impNone', ovl).onclick = () => { items().forEach(x => x.checked = false); $$('.impCat', ovl).forEach(c => c.checked = false); count(); };
    $$('.impCat', ovl).forEach(c => c.onchange = () => {
      items().filter(x => x.dataset.cat === c.dataset.cat).forEach(x => x.checked = c.checked); count();
    });
    items().forEach(x => x.onchange = count);
    $('#impOver', ovl).onchange = () => {
      if ($('#impOver', ovl).checked) { items().forEach(x => x.checked = true); count(); }
    };
    count();
    $('#impCancel', ovl).onclick = () => cnpAskClose(box);
    $('#impSave', ovl).onclick = async () => {
      const sel = items().filter(x => x.checked).map(x => JSON.parse(x.dataset.it));
      if (!sel.length) { toast('Δεν διάλεξες άρθρα', true); return; }
      const btn = $('#impSave', ovl);
      btn.disabled = true; btn.innerHTML = '<span class="rte-spin"></span> Εισαγωγή…';
      const r = await api('kb_import_commit', {items: sel, areaId: +$('#impArea', ovl).value,
        tags: $('#impTags', ovl).value, overwrite: $('#impOver', ovl).checked ? 1 : 0}).catch(e => ({err: e.message}));
      btn.disabled = false; btn.innerHTML = 'Εισαγωγή';
      if (r.err) { toast(r.err, true); return; }
      box.dataset.dirty = '';
      ovl.remove();
      toast(`Μπήκαν ${r.imported} άρθρα` + (r.skipped ? ` · ${r.skipped} υπήρχαν ήδη` : '') + (r.failed ? ` · ${r.failed} απέτυχαν` : ''));
      reload();
    };
  };
  $('#impGo', ovl).onclick = probe;
  $('#impUrl', ovl).onkeydown = e => { if (e.key === 'Enter') { probe(); } };
}

/* ═════════ 💬 ΕΣΩΤΕΡΙΚΟ CHAT ═════════ */
R.chat = async function () {
  setTop('Chat', 'Εσωτερική επικοινωνία ομάδας — με αρχεία');
  const c = $('#content');
  const st = R.chat._st = R.chat._st || {ch: 'team', lastId: 0};
  clearInterval(R.chat._t);
  const d = await api('chat_channels');
  const ini = n => (n || '?').trim().split(/\s+/).map(w => w[0] || '').slice(0, 2).join('').toUpperCase();
  const chAva = ch => `<span class="ch-av ${ch.kind !== 'dm' ? 'ch-av-grp' : ''}">${ch.kind === 'team' ? I.users : ch.kind === 'group' ? '#' : esc(ini(ch.name))}${ch.kind === 'dm' ? `<span class="ch-av-dot ${ch.status || 'online'}"></span>` : ''}</span>`;
  const chPresence = ch => ch.status === 'offline' ? '⚫ Offline' + (ch.reason ? ' · ' + esc(ch.reason) : '') : ch.status === 'away' ? '🟡 Away' + (ch.reason ? ' · ' + esc(ch.reason) : '') : '🟢 Online';
  const cur = d.channels.find(x => x.id === st.ch) || d.channels[0] || {name: 'Chat', kind: 'team'};
  c.innerHTML = `
  <div class="chat${st.mobileConv ? ' conv-open' : ''}">
    <div class="ch-left">
      <div class="ch-mystatus">
        <span class="ch-dot ${d.myStatus === 'offline' ? 'offline' : 'online'}"></span>
        <select class="inp" id="chSt" style="flex:1;padding:5px 9px;font-size:12.5px;font-weight:600">
          <option value="online" ${d.myStatus !== 'offline' ? 'selected' : ''}>🟢 Είμαι Online</option>
          <option value="offline" ${d.myStatus === 'offline' ? 'selected' : ''}>⚫ Είμαι Offline</option>
        </select>
      </div>
      ${d.myStatus === 'offline' && d.myReason ? `<div class="mut" style="font-size:11px;padding:6px 15px;cursor:pointer" id="chReasonEdit" title="Αλλαγή λόγου">${I.chat} ${esc(d.myReason)} <span style="opacity:.6">· αλλαγή</span></div>` : ''}
      <div class="ch-list">
      ${d.channels.map(ch => `
        <div class="ch-row ${st.ch === ch.id ? 'on' : ''}" data-ch="${ch.id}">
          ${chAva(ch)}
          <span class="ch-row-body">
            <span class="ch-row-name">${esc(ch.name)}${ch.kind === 'group' ? ` <span class="mut" style="font-size:10.5px;font-weight:500">· ${ch.members} μέλη</span>` : ''}</span>
            <span class="ch-row-sub">${ch.reason ? I.chat + ' ' + esc(ch.reason) : ch.kind === 'dm' ? (ch.status === 'offline' ? 'Offline' : ch.status === 'away' ? 'Away' : 'Online') : ch.kind === 'team' ? 'Όλη η ομάδα' : 'Ομαδική συνομιλία'}</span>
          </span>
          ${ch.unread ? `<span class="chat-n">${ch.unread}</span>` : ''}
          ${ch.kind === 'group' ? `<span data-gdel="${ch.groupId}" data-gmine="${ch.mine ? 1 : 0}" title="${ch.mine ? 'Διαγραφή ομάδας' : 'Αποχώρηση'}" class="ch-row-x">✕</span>` : ''}
        </div>`).join('')}
      <div class="ch-row ch-newgrp" id="chNewGrp"><span class="ch-av ch-av-grp">＋</span><span class="ch-row-name" style="color:var(--brand);font-weight:700">Νέα ομάδα</span></div>
      </div>
    </div>
    <div class="ch-main">
      <div class="ch-head">
        <button class="ch-back" id="chBack" aria-label="Πίσω στις συζητήσεις"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg></button>
        ${chAva(cur)}
        <div style="min-width:0;flex:1">
          <b style="font-size:14.5px;color:var(--ink);display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(cur.name)}</b>
          <div class="mut" style="font-size:11px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${cur.kind === 'dm' ? chPresence(cur) : cur.kind === 'group' ? (cur.members || 0) + ' μέλη' : 'Όλη η ομάδα'}</div>
        </div>
      </div>
      <div class="ch-msgs" id="chMsgs"><div class="skel" style="height:60px"></div></div>
      <div class="ch-comp">
        <label class="btn btn-o btn-sm" style="cursor:pointer" title="Αρχείο">${I.clip}<input type="file" id="chFile" style="display:none"></label>
        <span id="chFn" class="mut" style="font-size:11px"></span>
        <input class="inp" id="chIn" placeholder="Μήνυμα… (Enter)" style="flex:1">
        <button class="btn btn-p btn-sm" id="chSend">${I.send}</button>
      </div>
    </div>
  </div>`;
  // picker λόγου offline — έτοιμες επιλογές + ελεύθερο κείμενο
  const OFFLINE_REASONS = [
    ['🍽️', 'Διάλειμμα φαγητού'], ['☕', 'Σύντομο διάλειμμα'], ['📞', 'Σε meeting / κλήση'],
    ['🎧', 'Deep work — μη με ενοχλείτε'], ['🏠', 'Εκτός γραφείου'], ['🚗', 'Σε μετακίνηση'],
    ['🧑‍💻', 'Σε άλλον πελάτη / task'], ['🤒', 'Άδεια / ασθένεια'], ['🌙', 'Τέλος ωραρίου'],
  ];
  const pickReason = (current) => new Promise(resolve => {
    const ovl = document.createElement('div'); ovl.className = 'ovl show'; ovl.style.zIndex = 320;
    ovl.innerHTML = `<div class="pal-box" style="margin:12vh auto 0;max-width:420px" role="dialog">
      <div style="padding:18px 20px 8px"><b style="font-size:15px;color:var(--ink)">Γιατί είσαι offline;</b>
        <div class="mut" style="font-size:12px;margin-top:3px">Η ομάδα θα βλέπει τον λόγο δίπλα στο όνομά σου.</div></div>
      <div style="display:flex;flex-wrap:wrap;gap:7px;padding:4px 20px 12px">
        ${OFFLINE_REASONS.map(([em, txt]) => `<button class="btn btn-o btn-sm rBtn" data-r="${esc(txt)}" style="font-size:12px">${em} ${esc(txt)}</button>`).join('')}
      </div>
      <div style="padding:0 20px 16px">
        <input class="inp" id="rCustom" maxlength="80" placeholder="…ή γράψε δικό σου λόγο" value="${esc(current || '')}" style="font-size:13px">
        <div style="display:flex;gap:8px;margin-top:12px;justify-content:flex-end">
          <button class="btn btn-o" id="rSkip">Χωρίς λόγο</button>
          <button class="btn btn-o" id="rCancel">Άκυρο</button>
          <button class="btn btn-p" id="rOk">Θέσε offline</button></div></div></div>`;
    document.body.appendChild(ovl);
    const done = v => { ovl.remove(); resolve(v); };
    $$('.rBtn', ovl).forEach(b => b.onclick = () => done(b.dataset.r));
    $('#rOk', ovl).onclick = () => done($('#rCustom', ovl).value.trim());
    $('#rSkip', ovl).onclick = () => done('');
    $('#rCancel', ovl).onclick = () => done(null);

    setTimeout(() => $('#rCustom', ovl).focus(), 30);
  });
  const goOffline = async (current) => {
    const reason = await pickReason(current);
    if (reason === null) { R.chat(); return; }   // άκυρο → επαναφορά
    await api('chat_status', {status: 'offline', reason});
    toast(reason ? '⚫ Offline · ' + reason : '⚫ Είσαι offline');
    R.chat();
  };
  $('#chSt').onchange = async e => {
    if (e.target.value === 'offline') { goOffline(d.myReason); return; }
    await api('chat_status', {status: 'online'});
    toast('Είσαι online 🟢');
    R.chat();
  };
  const re = $('#chReasonEdit'); if (re) { re.onclick = () => goOffline(d.myReason); }
  $$('.ch-row[data-ch]').forEach(r => r.onclick = e => {
    if (e.target.closest('[data-gdel]')) return;
    st.ch = r.dataset.ch; st.lastId = 0; st.mobileConv = true; R.chat();   // mobile: άνοιξε τη συνομιλία full-screen
  });
  document.body.classList.toggle('detail-open', !!st.mobileConv);
  { const bk = $('#chBack'); if (bk) bk.onclick = () => { st.mobileConv = false; const cw = $('.chat'); if (cw) cw.classList.remove('conv-open'); document.body.classList.remove('detail-open'); }; }
  $$('[data-gdel]').forEach(x => x.onclick = async e => {
    e.stopPropagation();
    const mine = x.dataset.gmine === '1';
    if (!(await cnpConfirm(mine ? 'Διαγραφή της ομάδας και της συνομιλίας της;' : 'Αποχώρηση από την ομάδα;',
      {danger: mine, ok: mine ? '🗑 Διαγραφή' : 'Αποχώρηση'}))) return;
    await api('chat_group_del', {id: +x.dataset.gdel});
    if (st.ch === 'g' + x.dataset.gdel) st.ch = 'team';
    toast(mine ? 'Η ομάδα διαγράφηκε' : 'Αποχώρησες'); R.chat();
  });
  $('#chNewGrp').onclick = () => {
    const ovl = document.createElement('div'); ovl.className = 'ovl show'; ovl.style.zIndex = 300;
    
    ovl.innerHTML = `<div class="pal-box" style="margin:16vh auto 0;max-width:460px" onclick="event.stopPropagation()">
      <div style="padding:20px 22px">
        <b style="font-size:15.5px;color:var(--ink)"># Νέα ομάδα συνομιλίας</b>
        <input class="inp" id="ngName" placeholder="Όνομα (π.χ. Έργο PharmacyOne)" style="margin-top:12px">
        <label class="lbl" style="margin-top:11px">Μέλη</label>
        <div style="display:flex;gap:9px;flex-wrap:wrap;margin-top:5px">
          ${S.boot.admins.filter(a => a.id !== S.boot.me.id).map(a => `
            <label style="font-size:12.5px;display:flex;gap:4px;align-items:center">
              <input type="checkbox" class="ngM" value="${a.id}"> ${esc(a.name)}</label>`).join('')}
        </div>
        <div style="display:flex;gap:9px;margin-top:15px;justify-content:flex-end">
          <button class="btn btn-o" id="ngNo">Άκυρο</button>
          <button class="btn btn-p" id="ngGo">Δημιουργία</button></div>
      </div></div>`;
    document.body.appendChild(ovl);
    ovl.querySelector('#ngNo').onclick = () => ovl.remove();
    ovl.querySelector('#ngName').focus();
    ovl.querySelector('#ngGo').onclick = async () => {
      const r = await api('chat_group_save', {name: ovl.querySelector('#ngName').value,
        members: [...ovl.querySelectorAll('.ngM:checked')].map(x => +x.value)}).catch(e => ({err: e.message}));
      if (r.err) { toast(r.err, true); return; }
      ovl.remove();
      st.ch = 'g' + r.id; st.lastId = 0;
      toast('Η ομάδα δημιουργήθηκε #'); R.chat();
    };
  };

  const render = msgs => {
    const box = $('#chMsgs'); if (!box) return;
    const stick = box.scrollTop + box.clientHeight >= box.scrollHeight - 60;
    if (st.lastId === 0) box.innerHTML = '';
    msgs.forEach(m => {
      if (m.id <= st.lastId) return;   // ήδη ζωγραφισμένο (προστασία από race με το poll)
      st.lastId = m.id;
      const div = document.createElement('div');
      div.className = 'ch-m' + (m.by === S.boot.me.id ? ' me' : '');
      div.innerHTML = `<div class="h">${esc(adminName(m.by))} · ${tShort(m.at)}</div>
        ${m.body ? esc(m.body).replace(/\n/g, '<br>') : ''}
        ${m.file ? `<div style="margin-top:4px"><a href="${m.file.url || ('api.php?a=chat_file&id=' + m.file.id)}" target="_blank" style="font-weight:700">${m.file.kind === 'video' ? '🎬' : m.file.kind === 'image' ? '🖼️' : I.clip} ${esc(m.file.name)}</a>
          <span class="mut" style="font-size:10px">(${Math.round(m.file.size / 1024)} KB)</span>
          ${m.file.kind === 'video' ? `<video src="${m.file.url}" controls preload="metadata" style="width:100%;max-width:340px;max-height:240px;border-radius:8px;background:#000;margin-top:5px"></video>` : m.file.kind === 'image' ? `<img src="${m.file.url}" loading="lazy" style="max-width:100%;max-height:200px;border-radius:8px;margin-top:5px;display:block">` : ''}</div>` : ''}`;
      box.appendChild(div);
    });
    if (msgs.length && (stick || st.lastId === msgs[msgs.length - 1].id)) box.scrollTop = box.scrollHeight;
  };
  let loading = false;
  const load = async () => {
    if (loading) return;
    loading = true;
    const r = await api('chat_msgs&channel=' + st.ch + '&after=' + Math.max(0, st.lastId)).catch(() => null);
    loading = false;
    if (r && r.messages.length) render(r.messages);
    else if (st.lastId === 0) { const b = $('#chMsgs'); if (b) b.innerHTML = '<div class="empty" style="margin:auto">Καμία συζήτηση ακόμη — πες ένα γεια 👋</div>'; st.lastId = -1; }
  };
  const send = async () => {
    const body = $('#chIn').value.trim();
    const f = $('#chFile').files[0];
    if (!body && !f) return;
    if (f) {
      const fd = new FormData();
      fd.append('channel', st.ch); fd.append('body', body); fd.append('file', f);
      const r = await fetch('api.php?a=chat_send', {method: 'POST', body: fd, credentials: 'same-origin'}).then(x => x.json());
      if (r.error) { toast(r.error, true); return; }
      $('#chFile').value = ''; $('#chFn').textContent = '';
    } else {
      await api('chat_send', {channel: st.ch, body});
    }
    $('#chIn').value = '';
    if (st.lastId === -1) st.lastId = 0;
    load();
  };
  $('#chSend').onclick = send;
  $('#chIn').onkeydown = e => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(); } };
  $('#chFile').onchange = () => { $('#chFn').textContent = $('#chFile').files[0]?.name || ''; };
  if (st.lastId === -1) st.lastId = 0;
  st.lastId = 0;
  load();
  R.chat._t = setInterval(() => {
    if (S.view !== 'chat') { clearInterval(R.chat._t); return; }
    if (st.lastId > 0) load();
  }, 5000);
};


/* ═════════ 🔬 ΑΝΑΛΥΣΗ ΡΙΖΩΝ (root-cause analytics) ═════════ */
R.rootcause = async function (days) {
  setTop('Ανάλυση ριζών', 'Πού «πονάει» πραγματικά — ομαδοποίηση προβλημάτων & χρόνου ανά ρίζα');
  const c = $('#content');
  const st = R.rootcause._d = days || R.rootcause._d || 90;
  c.innerHTML = '<div class="grid g4">' + '<div class="skel" style="height:90px"></div>'.repeat(4) + '</div>';
  const d = await api('rootcause&days=' + st).catch(() => null);
  if (!d) { c.innerHTML = `<div class="empty"><div class="big">${I.lock}</div>Μόνο για διαχειριστές</div>`; return; }
  const pct = d.allTickets ? Math.round(d.totalClassified / d.allTickets * 100) : 0;
  const maxC = Math.max(1, ...d.topCauses.map(x => x.count));
  const aById = {}; d.areas.forEach(a => aById[a.id] = a);
  const cById = {}; d.causes.forEach(c2 => cById[c2.id] = c2);
  const MOB = matchMedia('(max-width:768px)').matches;
  c.innerHTML = `
  <div class="card kb-search">
    <div class="kb-filters" style="border-top:0;padding-top:0;margin-top:0">
      ${[30, 90, 180, 365].map(dd => `<button class="kb-chip${dd === st ? ' on' : ''}" data-days="${dd}">${dd === 365 ? '1 έτος' : dd + ' ημέρες'}</button>`).join('')}
      <span class="crm-goal" style="margin-left:auto">${I.tag} <b>${d.totalClassified}</b><span class="mut"> / ${d.allTickets} ταξινομημένα (${pct}%)</span></span>
    </div>
  </div>
  ${pct < 40 ? `<div class="card" style="border-left:4px solid var(--warn);margin-bottom:14px"><div class="card-b" style="font-size:12.5px">
    ${I.bulb} Μόνο το ${pct}% των tickets είναι ταξινομημένα. Όσο περισσότερα ταξινομείτε (${I.tag} στο ticket), τόσο πιο ακριβής η ανάλυση.</div></div>` : ''}
  <div class="grid g2">
    <div class="card"><div class="card-h">${I.lab} Κορυφαίες ρίζες προβλημάτων</div><div class="card-b">
      ${d.topCauses.length ? d.topCauses.map(x => `<div class="rc-row" data-cgo="${x.id}">
        <span class="rc-name">${esc(x.name)}</span>
        <div class="rc-track">
          <div class="rc-fill" style="width:${Math.round(x.count / maxC * 100)}%;background:${x.color}">${x.count}</div></div>
        <span class="rc-delta" style="color:${x.delta > 0 ? 'var(--bad)' : x.delta < 0 ? 'var(--ok)' : 'var(--mut)'}">${x.delta > 0 ? '▲+' + x.delta : x.delta < 0 ? '▼' + x.delta : '='}</span>
        <span class="rc-min mut">${x.minutes ? fmtMin(x.minutes) : ''}</span></div>`).join('')
        : '<div class="empty" style="padding:20px">Καμία ταξινόμηση ακόμη</div>'}
      <div class="mut" style="font-size:11px;margin-top:6px">Δεξιά = συνολικός χρόνος υποστήριξης που «κόστισε» η κάθε ρίζα.</div>
    </div></div>
    <div class="card"><div class="card-h">${I.box} Ανά περιοχή / προϊόν</div><div class="card-b">
      ${d.topAreas.length ? d.topAreas.map(x => `<div data-ago="${x.id}" style="cursor:pointer" class="set-row">
        <span class="dot" style="background:${x.color}"></span><b style="flex:1;font-size:12.5px">${esc(x.name)}</b><span class="kb-n">${x.count}</span></div>`).join('')
        : '<div class="empty" style="padding:20px">—</div>'}
    </div></div>
  </div>
  ${(d.series && d.series.length > 1) ? `<div class="card"><div class="card-h">${I.trendUp} Τάση ριζών ανά μήνα <span class="mut" style="font-weight:400;font-size:11px;margin-left:auto">top ${d.top5.length} ρίζες</span></div>
    <div class="card-b"><div class="tw" style="overflow-x:auto">
      <table class="tbl" style="font-size:11.5px"><thead><tr><th>Μήνας</th>
        ${d.top5.map(cid => { const c2 = cById[cid]; return `<th><span class="dot" style="background:${c2 ? c2.color : '#888'}"></span> ${esc(c2 ? c2.name : '?')}</th>`; }).join('')}</tr></thead><tbody>
        ${d.series.map(row => `<tr><td style="font-weight:700">${row.ym}</td>
          ${d.top5.map(cid => { const n = row[cid] || 0; const c2 = cById[cid];
            return `<td align="center" style="${n ? 'background:' + (c2 ? c2.color : '#888') + (n >= 5 ? '55' : n >= 2 ? '2e' : '14') + ';font-weight:700' : 'color:var(--mut)'}">${n || ''}</td>`; }).join('')}</tr>`).join('')}
      </tbody></table></div>
      <div class="mut" style="font-size:11px;margin-top:6px">▲ κόκκινο = αυξάνεται vs προηγούμενη περίοδος · ▼ πράσινο = μειώνεται.</div>
    </div></div>` : ''}
  ${MOB
    /* Κινητό: ο pivot Περιοχή×Ρίζα (9 στήλες με κάθετους τίτλους) ήταν αδιάβαστος — γίνεται
       λίστα ανά περιοχή, με chip ανά ρίζα που έχει έστω 1 ticket. */
    ? d.areas.map(a => {
        const cells = d.causes.map(c2 => ({c2, n: (d.matrix[a.id] || {})[c2.id] || 0})).filter(x => x.n)
          .sort((x, y) => y.n - x.n);
        if (!cells.length) { return ''; }
        const tot = cells.reduce((s, x) => s + x.n, 0);
        return `<div class="card kb-group"><div class="card-h">
            <span class="kb-gbar" style="background:${a.color}"></span>${esc(a.name)}
            <span class="kb-n">${tot}</span></div>
          <div class="card-b" style="display:flex;flex-wrap:wrap;gap:7px">
            ${cells.map(({c2, n}) => `<button class="kb-chip" data-mgo="${a.id}_${c2.id}" style="--kc:${c2.color}">
              <span class="kb-dot" style="background:${c2.color}"></span>${esc(c2.name)} <b>${n}</b></button>`).join('')}
          </div></div>`;
      }).join('') || `<div class="card"><div class="empty" style="padding:34px">
          <div class="big">${I.puzzle}</div>Καμία ταξινόμηση σε αυτή την περίοδο</div></div>`
    : `<div class="card"><div class="card-h">${I.puzzle} Πίνακας: Περιοχή × Ρίζα <span class="mut" style="font-weight:400;font-size:11px;margin-left:auto">κλικ σε αριθμό → τα tickets</span></div>
    <div class="tw" style="overflow-x:auto"><table class="tbl" style="font-size:11.5px"><thead><tr><th></th>
      ${d.causes.map(c2 => `<th style="writing-mode:vertical-rl;transform:rotate(180deg);white-space:nowrap;max-height:120px">${esc(c2.name)}</th>`).join('')}</tr></thead><tbody>
      ${d.areas.map(a => `<tr><td style="font-weight:700;white-space:nowrap"><span class="dot" style="background:${a.color}"></span> ${esc(a.name)}</td>
        ${d.causes.map(c2 => { const n = (d.matrix[a.id] || {})[c2.id] || 0;
          return `<td align="center" ${n ? `data-mgo="${a.id}_${c2.id}" style="cursor:pointer;background:${c2.color}${n >= 5 ? '55' : n >= 2 ? '2e' : '14'};font-weight:700"` : 'class="mut"'}>${n || ''}</td>`; }).join('')}</tr>`).join('')}
    </tbody></table></div></div>`}`;
  $$('[data-days]').forEach(b => b.onclick = () => R.rootcause(+b.dataset.days));
  const goInbox = (area, cause) => { R.inbox._st = {view: 'closed', q: '', sel: null, area: area || 0, cause: cause || 0}; go('inbox'); };
  $$('[data-cgo]').forEach(x => x.onclick = () => goInbox(0, +x.dataset.cgo));
  $$('[data-ago]').forEach(x => x.onclick = () => goInbox(+x.dataset.ago, 0));
  $$('[data-mgo]').forEach(x => x.onclick = () => { const p = x.dataset.mgo.split('_'); goInbox(+p[0], +p[1]); });
};

// ═══════════════ 🏃 STANDUP DASHBOARD — απασχόληση περιόδου + on-time ═══════════════
R.standup = async function () {
  setTop('Standup', 'Ανοιχτά projects & tickets — τι είναι, πού ανήκει, τι πρέπει να ξέρεις');
  const c = $('#content');
  c.innerHTML = '<div class="grid g4">' + '<div class="skel" style="height:120px"></div>'.repeat(2) + '</div>';
  const d = await api('agenda').catch(() => null);
  if (!d) { c.innerHTML = `<div class="empty"><div class="big">${I.lock}</div>Δεν φορτώθηκε</div>`; return; }
  const hc = { green: 'var(--ok)', yellow: 'var(--warn)', red: 'var(--bad)' };
  const hLabel = { green: 'Καλά', yellow: 'Προσοχή', red: 'Πρόβλημα' };
  const chip = (txt, col) => `<span class="su-chip" style="background:${col}18;color:${col};border:1px solid ${col}33">${txt}</span>`;
  // κυκλικό progress ring
  const ring = (pct, col) => {
    const r = 24, circ = 2 * Math.PI * r, off = circ * (1 - (pct || 0) / 100);
    return `<div class="su-ring"><svg width="58" height="58" viewBox="0 0 58 58">
      <circle cx="29" cy="29" r="${r}" fill="none" stroke="var(--line)" stroke-width="6"/>
      <circle cx="29" cy="29" r="${r}" fill="none" stroke="${col}" stroke-width="6" stroke-linecap="round" stroke-dasharray="${circ}" stroke-dashoffset="${off}"/>
    </svg><div class="v">${pct}%</div></div>`;
  };
  const projNotes = p => {
    const n = [];
    if (p.daysLeft !== null && p.daysLeft < 0) { n.push(chip(I.alert + ' Καθυστερεί ' + Math.abs(p.daysLeft) + 'μ', 'var(--bad)')); }
    else if (p.daysLeft !== null && p.daysLeft <= 3) { n.push(chip(I.clock + ' Λήγει ' + (p.daysLeft === 0 ? 'σήμερα' : 'σε ' + p.daysLeft + 'μ'), 'var(--warn)')); }
    if (p.staleDays !== null && p.staleDays >= 7) { n.push(chip('🐌 Στάσιμο ' + p.staleDays + 'μ', 'var(--warn)')); }
    if (p.health === 'red') { n.push(chip(I.alert + ' Πρόβλημα', 'var(--bad)')); }
    if (p.total === 0 && !p.todoTotal) { n.push(chip(I.box + ' Καμία εργασία', 'var(--mut)')); }
    if (!n.length) { n.push(chip('✓ Σε καλό δρόμο', 'var(--ok)')); }
    return n.join('');
  };
  const dueBlock = p => p.due
    ? `<div style="font-size:13px;font-weight:800;color:${p.daysLeft < 0 ? 'var(--bad)' : p.daysLeft <= 3 ? 'var(--warn)' : 'var(--ok)'}">${
        p.daysLeft < 0 ? Math.abs(p.daysLeft) + 'μ πίσω' : p.daysLeft === 0 ? 'σήμερα' : p.daysLeft + ' μέρες'}</div>
       <div class="mut" style="font-size:10px">${p.daysLeft < 0 ? 'καθυστέρηση' : 'ως προθεσμία'}</div>`
    : '<div class="mut" style="font-size:11px">χωρίς<br>προθεσμία</div>';
  const stat = (ic, n, l, col) => `<div class="su-stat"><div class="ic" style="background:${col}1a;color:${col}">${ic}</div>
    <div><div class="n">${n}</div><div class="l">${l}</div></div></div>`;

  c.innerHTML = `
  <div style="display:flex;gap:11px;flex-wrap:wrap;align-items:center;margin-bottom:18px">
    ${stat(I.rocket, d.counts.projects, 'ανοιχτά projects', 'var(--brand)')}
    ${stat(I.ticket, d.counts.tickets, 'ανοιχτά tickets', 'var(--violet)')}
    ${stat(I.alert, d.counts.waitUs, 'περιμένουν εμάς', d.counts.waitUs ? 'var(--bad)' : 'var(--ok)')}
    <button class="btn btn-o btn-sm" id="agRef" style="margin-left:auto">↻ Ανανέωση</button>
  </div>

  <div class="card" style="margin-bottom:18px"><div class="card-h">${I.rocket} Ανοιχτά Projects <span class="mut" style="font-weight:600">(${d.projects.length})</span>
    <span class="mut" style="font-weight:400;font-size:11px;margin-left:auto">νωρίτερη προθεσμία πρώτη · κλικ → Board</span></div>
    <div class="card-b" style="display:flex;flex-direction:column;gap:11px">
    ${d.projects.length ? d.projects.map(p => `
      <div class="su-proj" data-pgo="${p.id}">
        <div class="stripe" style="background:${hc[p.health] || 'var(--mut)'}"></div>
        ${ring(p.pct, p.health === 'red' ? 'var(--bad)' : p.health === 'yellow' ? 'var(--warn)' : 'var(--brand)')}
        <div class="body">
          <div class="title">${esc(p.name)}</div>
          <div class="meta">
            <span>${p.kind === 'client' ? I.rocket + ' Έργο πελάτη' : I.building + ' Λειτουργικό'}</span>
            ${p.client ? '<span style="opacity:.5">·</span><span>' + esc(p.client) + '</span>' : ''}
            ${p.owners.length ? '<span style="opacity:.5">·</span><span>' + I.user + ' ' + p.owners.map(esc).join(', ') + '</span>' : ''}
            <span style="opacity:.5">·</span><span>${p.done}/${p.total} tasks${p.spentMins ? ' · ' + fmtMin(p.spentMins) : ''}</span>
            ${p.lastUpdate ? '<span style="opacity:.5">·</span><span>ενημ. ' + p.lastUpdate + '</span>' : ''}
          </div>
          <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:8px">${projNotes(p)}</div>
          ${p.next ? `<div class="next"><b style="color:var(--ink)">▶ Επόμενο:</b> ${esc(p.next.title)}${p.next.who ? ` <span class="mut">— ${esc(p.next.who)}</span>` : '<span class="mut"> — αχρέωτο</span>'}${p.next.due ? ` <span class="mut">(έως ${p.next.due})</span>` : ''}</div>` : ''}
          ${p.pendingTodos.length ? `<div style="font-size:11px;margin-top:7px;color:var(--mut)">${I.box} Εκκρεμή (${p.todoTotal - p.todoDone}/${p.todoTotal}):
            ${p.pendingTodos.map(t => `<span style="display:inline-block;background:var(--line);border-radius:6px;padding:1px 8px;margin:2px 3px 0 0;color:var(--txt)">${esc(t)}</span>`).join('')}</div>` : ''}
        </div>
        <div class="due">${dueBlock(p)}</div>
      </div>`).join('') : `<div class="empty" style="padding:28px"><div class="big">${I.rocket}</div>Κανένα ανοιχτό project 🎉</div>`}
    </div></div>

  <div class="card"><div class="card-h">${I.ticket} Ανοιχτά Tickets <span class="mut" style="font-weight:600">(${d.tickets.length})</span>
    <span class="mut" style="font-weight:400;font-size:11px;margin-left:auto">επείγοντα & αναπάντητα πρώτα · κλικ → ticket</span></div>
    <div class="card-b" style="display:flex;flex-direction:column;gap:10px">
    ${d.tickets.length ? d.tickets.map(t => {
      const wc = t.waitUs ? 'var(--bad)' : 'var(--ok)';
      return `<div class="su-tk" data-ibgo="${t.id}">
        <div class="stripe" style="background:${wc}"></div>
        <div class="wait" style="background:${wc};color:${wc}"></div>
        <div style="flex:1;min-width:0">
          <div style="font-weight:700;font-size:13.5px;color:var(--ink)">#${esc(t.tid)} — ${esc(t.title)}</div>
          <div class="mut" style="font-size:11.5px;margin-top:2px">${I.user} ${esc(t.client)} <span style="opacity:.5">·</span> ${I.folder} ${esc(t.dept)} <span style="opacity:.5">·</span> ${t.assignee ? 'χειριστής: ' + esc(t.assignee) : '<b style="color:var(--warn)">χωρίς χειριστή</b>'}</div>
          <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:8px">
            ${t.urgency === 'High' ? chip(I.fire + ' Υψηλή', 'var(--bad)') : t.urgency === 'Low' ? chip('Χαμηλή', 'var(--mut)') : chip('Μεσαία', 'var(--warn)')}
            <span class="su-kbadge" style="background:var(--brand)1a;color:var(--brand)">${esc(t.status)}</span>
            ${t.area ? chip(I.box + ' ' + esc(t.area.name), t.area.color) : ''}
            ${t.cause ? chip(I.lab + ' ' + esc(t.cause.name), t.cause.color) : ''}
            ${!t.area && !t.cause ? chip(I.tag + ' αταξινόμητο', 'var(--mut)') : ''}
          </div>
        </div>
        <div style="text-align:right;flex:none">
          <div style="font-size:12px;font-weight:800;color:${wc}">${t.waitUs ? 'Περιμένει εμάς' : 'Περιμένει πελάτη'}</div>
          <div class="mut" style="font-size:10.5px;margin-top:3px">${t.idle === 0 ? 'σήμερα' : t.idle + 'μ αναπάντητο'}<br>${t.age}μ ζωή</div>
        </div>
      </div>`;
    }).join('') : `<div class="empty" style="padding:28px"><div class="big">${I.ticket}</div>Κανένα ανοιχτό ticket 🎉</div>`}
    </div></div>`;
  $('#agRef').onclick = () => R.standup();
  $$('[data-pgo]').forEach(x => x.onclick = () => go('board', +x.dataset.pgo));
  $$('[data-ibgo]').forEach(x => x.onclick = () => go('inbox', +x.dataset.ibgo));
};

/* ═════════ 📚 Η ΒΙΒΛΙΟΘΗΚΗ ΜΟΥ (ιδιωτική) ═════════ */
const _libSize = s => s > 1048576 ? (s / 1048576).toFixed(1) + 'MB' : Math.round(s / 1024) + 'KB';
R.library = async function () {
  setTop('Η βιβλιοθήκη μου', 'Έγγραφα, σημειώσεις & links — ιδιωτικά ή κοινά ομάδας');
  const c = $('#content');
  const st = R.library._s = R.library._s || {q: '', cat: '', scope: 'mine'};
  c.innerHTML = `
  <div id="lbScope" style="display:flex;gap:7px;margin-bottom:12px"></div>
  <div class="card" style="padding:12px 15px;display:flex;gap:9px;align-items:center;flex-wrap:wrap;margin-bottom:13px">
    <input class="inp" id="lbQ" placeholder="Αναζήτηση σε τίτλους, κείμενο, ετικέτες, αρχεία…" style="flex:1;min-width:200px" value="${esc(st.q)}">
    <button class="btn btn-o btn-sm" id="lbNote">${I.edit} Σημείωση</button>
    <button class="btn btn-o btn-sm" id="lbLink">${I.link} Link</button>
    <label class="btn btn-p btn-sm" style="cursor:pointer;margin:0">${I.download} Αρχείο<input type="file" id="lbFile" style="display:none"></label>
  </div>
  <div id="lbCats" style="display:flex;gap:7px;flex-wrap:wrap;margin-bottom:13px"></div>
  <div id="lbBox">${'<div class="skel" style="height:70px;margin-bottom:10px"></div>'.repeat(3)}</div>`;
  const kindIco = {note: I.edit, link: I.link, file: I.download};
  const expBadge = it => {
    if (!it.expires) { return ''; }
    const d = it.expDays;
    const col = d < 0 ? 'var(--bad)' : d <= 7 ? 'var(--warn)' : 'var(--mut)';
    const lbl = d < 0 ? 'Έληξε' : d === 0 ? 'Λήγει σήμερα' : 'Λήγει σε ' + d + ' ημ.';
    return `<span class="pill" style="background:${col}1a;color:${col};font-size:9px">${I.clock} ${lbl}</span>`;
  };
  const load = async () => {
    const d = await api(`lib_list&scope=${st.scope}&q=${encodeURIComponent(st.q)}` + (st.cat ? '&cat=' + encodeURIComponent(st.cat) : ''));
    $('#lbScope').innerHTML = `<button class="btn btn-sm ${st.scope === 'mine' ? 'btn-p' : 'btn-o'}" data-scope="mine">${I.user} Δικά μου</button>
      <button class="btn btn-sm ${st.scope === 'shared' ? 'btn-p' : 'btn-o'}" data-scope="shared">${I.users} Ομάδας${d.sharedN ? ' (' + d.sharedN + ')' : ''}</button>`;
    $('#lbCats').innerHTML = d.cats.length ? `<button class="btn btn-sm ${st.cat === '' ? 'btn-p' : 'btn-o'}" data-cat="">Όλα</button>` +
      d.cats.map(cat => `<button class="btn btn-sm ${st.cat === cat ? 'btn-p' : 'btn-o'}" data-cat="${esc(cat)}">${esc(cat)}</button>`).join('') : '';
    const box = $('#lbBox');
    if (!d.items.length) { box.innerHTML = `<div class="empty" style="padding:40px"><div class="big">${I.book}</div>${st.q || st.cat ? 'Κανένα αποτέλεσμα' : (st.scope === 'shared' ? 'Δεν υπάρχουν κοινά έγγραφα ομάδας' : 'Κενή βιβλιοθήκη — πρόσθεσε σημείωση, link ή αρχείο')}</div>`; return; }
    const rowHtml = it => `<div style="display:flex;gap:10px;align-items:flex-start;padding:9px 0;border-bottom:1px dashed var(--line)">
      <span style="color:var(--brand);flex:none;margin-top:2px">${kindIco[it.kind] || I.book}</span>
      <div style="flex:1;min-width:0">
        <div style="display:flex;align-items:center;gap:7px;flex-wrap:wrap"><b style="font-size:13.5px">${esc(it.title)}</b>
          ${it.kind === 'file' ? `<span class="mut" style="font-size:10.5px">${esc(it.filename)} · ${_libSize(it.size)}</span>` : ''}
          ${expBadge(it)}
          ${it.shared ? `<span class="pill" style="background:var(--ok)1a;color:var(--ok);font-size:9px">${I.users} κοινό</span>` : ''}
          ${st.scope === 'shared' && !it.canEdit ? `<span class="mut" style="font-size:10px">· ${esc(it.ownerName)}</span>` : ''}</div>
        ${it.kind === 'note' && it.body ? `<div class="mut" style="font-size:12px;margin-top:3px;max-height:66px;overflow:hidden">${it.body}</div>` : ''}
        ${it.kind === 'link' && it.url ? `<a href="${esc(it.url)}" target="_blank" rel="noopener" style="font-size:12px;color:var(--brand);word-break:break-all">${esc(it.url)}</a>` : ''}
        ${it.tags ? `<div style="margin-top:4px;display:flex;gap:4px;flex-wrap:wrap">${it.tags.split(',').filter(x => x.trim()).map(t => `<span class="pill" style="font-size:9px">${esc(t.trim())}</span>`).join('')}</div>` : ''}
      </div>
      <div style="flex:none;display:flex;gap:3px">
        ${it.kind === 'file' ? `<button class="btn btn-sm btn-o" data-lbget="${it.id}" title="κατέβασμα" style="padding:3px 7px">${I.download}</button>` : ''}
        ${it.kind === 'link' ? `<button class="btn btn-sm btn-o" data-lbcopy="${esc(it.url)}" title="αντιγραφή" style="padding:3px 7px">⧉</button>` : ''}
        ${it.kind === 'note' ? `<button class="btn btn-sm btn-o" data-lbcopyn="${it.id}" title="αντιγραφή κειμένου" style="padding:3px 7px">⧉</button>` : ''}
        ${it.canEdit ? `<button class="btn btn-sm btn-o" data-lbpin="${it.id}" title="${it.pinned ? 'ξεκαρφίτσωμα' : 'καρφίτσωμα'}" style="padding:3px 7px">${it.pinned ? '★' : '☆'}</button>
        <button class="btn btn-sm btn-o" data-lbedit="${it.id}" style="padding:3px 7px">${I.edit}</button>
        <button class="btn btn-sm btn-o" data-lbdel="${it.id}" style="padding:3px 7px;color:var(--bad)">${I.trash}</button>` : ''}
      </div></div>`;
    const pinned = d.items.filter(x => x.pinned);
    const rest = d.items.filter(x => !x.pinned);
    const groups = {};
    rest.forEach(it => { const g = it.category || 'Χωρίς κατηγορία'; (groups[g] = groups[g] || []).push(it); });
    box.innerHTML =
      (pinned.length ? `<div class="card" style="margin-bottom:12px"><div class="card-h">★ Καρφιτσωμένα</div><div class="card-b">${pinned.map(rowHtml).join('')}</div></div>` : '') +
      Object.entries(groups).map(([g, items]) => `<div class="card" style="margin-bottom:12px"><div class="card-h">${I.folder} ${esc(g)} <span class="kb-n" style="margin-left:auto">${items.length}</span></div><div class="card-b">${items.map(rowHtml).join('')}</div></div>`).join('');
    $$('[data-lbget]', box).forEach(b => b.onclick = () => window.open('api.php?a=lib_get&id=' + b.dataset.lbget, '_blank'));
    $$('[data-lbcopy]', box).forEach(b => b.onclick = async () => { await navigator.clipboard.writeText(b.dataset.lbcopy); toast('Αντιγράφηκε'); });
    $$('[data-lbcopyn]', box).forEach(b => b.onclick = async () => { const it = d.items.find(x => x.id === +b.dataset.lbcopyn); const tmp = document.createElement('div'); tmp.innerHTML = it.body || ''; await navigator.clipboard.writeText(tmp.textContent || ''); toast('Κείμενο αντιγράφηκε'); });
    $$('[data-lbpin]', box).forEach(b => b.onclick = async () => { await api('lib_pin', {id: +b.dataset.lbpin}); load(); });
    $$('[data-lbedit]', box).forEach(b => b.onclick = () => { const it = d.items.find(x => x.id === +b.dataset.lbedit); openLibForm(it.kind, it); });
    $$('[data-lbdel]', box).forEach(b => b.onclick = async () => { if (!await cnpConfirm('Διαγραφή;')) { return; } await api('lib_del', {id: +b.dataset.lbdel}); load(); });
  };
  await load();
  let qt;
  $('#lbQ').oninput = () => { clearTimeout(qt); qt = setTimeout(() => { st.q = $('#lbQ').value.trim(); load(); }, 300); };
  $('#lbNote').onclick = () => openLibForm('note', null);
  $('#lbLink').onclick = () => openLibForm('link', null);
  $('#lbFile').onchange = async e => {
    const file = e.target.files[0]; if (!file) { return; }
    const fd = new FormData(); fd.append('file', file); fd.append('title', file.name);
    toast('Ανεβαίνει…');
    const r = await fetch('api.php?a=lib_upload', {method: 'POST', body: fd, credentials: 'same-origin'}).then(x => x.json());
    if (r.ok) { toast('Ανέβηκε ✓ — πάτα ✎ για κατηγορία/λήξη/κοινό'); load(); } else { toast(r.error || 'Σφάλμα', true); }
    e.target.value = '';
  };
  $('#lbScope').onclick = e => { const b = e.target.closest('[data-scope]'); if (!b) { return; } st.scope = b.dataset.scope; st.cat = ''; load(); };
  $('#lbCats').onclick = e => { const b = e.target.closest('[data-cat]'); if (!b) { return; } st.cat = b.dataset.cat; load(); };
  function openLibForm(kind, item) {
    const isNew = !item;
    const ovl = document.createElement('div'); ovl.className = 'ovl show'; 
    const kindTitle = kind === 'link' ? 'link' : kind === 'file' ? 'αρχείο' : 'σημείωση';
    ovl.innerHTML = `<div class="pal-box" style="margin:6vh auto 0;max-width:580px;text-align:left" onclick="event.stopPropagation()">
      <div style="padding:20px 22px">
        <h2 style="margin:0 0 15px;font-size:17px;color:var(--ink);display:flex;align-items:center;gap:8px">${kind === 'link' ? I.link : kind === 'file' ? I.download : I.edit} ${isNew ? 'Νέα ' + kindTitle : 'Επεξεργασία'}</h2>
        <label class="lbl">Τίτλος *</label><input class="inp" id="lfT" value="${isNew ? '' : esc(item.title)}">
        ${kind === 'link' ? `<label class="lbl" style="margin-top:11px">URL</label><input class="inp" id="lfU" value="${isNew ? '' : esc(item.url)}" placeholder="https://…">` :
          kind === 'note' ? `<label class="lbl" style="margin-top:11px">Κείμενο</label>${rteHtml('lfB', isNew ? '' : (item.body || ''), 'Η σημείωσή σου…', {min: 150})}` :
          `<div class="mut" style="font-size:12px;margin-top:8px">${I.download} ${esc(item.filename)} · ${_libSize(item.size)}</div>`}
        <div class="frow" style="margin-top:11px">
          <div><label class="lbl">Κατηγορία</label><input class="inp" id="lfC" list="lfCL" value="${isNew ? '' : esc(item.category)}" placeholder="π.χ. Δίκτυα"><datalist id="lfCL"></datalist></div>
          <div><label class="lbl">Ετικέτες (κόμμα)</label><input class="inp" id="lfTg" value="${isNew ? '' : esc(item.tags)}" placeholder="vpn, φορητός"></div>
        </div>
        <div class="frow" style="margin-top:11px">
          <div><label class="lbl">${I.clock} Ημ. λήξης <span class="mut" style="font-weight:400">(συμβόλαιο/άδεια)</span></label><input class="inp" type="date" id="lfExp" value="${isNew ? '' : (item.expires || '')}"></div>
          <div style="display:flex;align-items:flex-end"><label style="display:flex;gap:8px;align-items:center;font-size:13px;cursor:pointer;padding-bottom:9px"><input type="checkbox" id="lfSh" ${!isNew && item.shared ? 'checked' : ''} style="width:17px;height:17px">Κοινό για την ομάδα</label></div>
        </div>
        <div id="lfFiles" style="margin-top:14px"></div>
        <div style="margin-top:16px;display:flex;gap:8px"><button class="btn btn-p" id="lfSave">Αποθήκευση</button><button class="btn btn-o" id="lfX">Άκυρο</button></div>
      </div></div>`;
    document.body.appendChild(ovl);
    api('lib_list').then(dd => { const dl = $('#lfCL', ovl); if (dl) { dl.innerHTML = (dd.cats || []).map(x => `<option value="${esc(x)}">`).join(''); } });

    /* 📎 Συνημμένα — ζουν πάνω στο τεκμήριο, άρα χρειάζονται id. Σε νέα καταχώρηση
       ενεργοποιούνται μόλις γίνει η πρώτη αποθήκευση (το popup μένει ανοιχτό). */
    let curId = isNew ? 0 : item.id;
    const mountFiles = () => {
      const box = $('#lfFiles', ovl);
      if (!box) { return; }
      if (!curId) {
        box.innerHTML = `<div class="lbl">${I.clip} Συνημμένα</div>
          <div class="mut" style="font-size:12px;padding:9px 11px;background:var(--canvas);border-radius:9px">
            Αποθήκευσε πρώτα την καταχώρηση και μετά πρόσθεσε αρχεία εδώ.</div>`;
        return;
      }
      box.innerHTML = `<div class="lbl">${I.clip} Συνημμένα</div><div id="lfFilesW"></div>`;
      window.cnpAttachments($('#lfFilesW', ovl), {module: 'library', refType: 'library', refId: curId});
    };
    mountFiles();

    $('#lfX', ovl).onclick = () => cnpAskClose(ovl.querySelector('.pal-box'));
    $('#lfSave', ovl).onclick = async () => {
      const title = $('#lfT', ovl).value.trim(); if (!title) { toast('Δώσε τίτλο', true); return; }
      const payload = {id: curId, kind, title, category: $('#lfC', ovl).value, tags: $('#lfTg', ovl).value,
        expires: $('#lfExp', ovl).value, shared: $('#lfSh', ovl).checked ? 1 : 0};
      if (kind === 'link') { payload.url = $('#lfU', ovl).value; } else if (kind === 'note') { payload.body = rteVal('lfB', ovl); }
      const r = await api('lib_save', payload).catch(e => ({err: e.message}));
      if (r.err) { toast(r.err, true); return; }
      const box = ovl.querySelector('.pal-box');
      if (box) { box.dataset.dirty = ''; }
      load();
      if (!curId && r.id) {          // νέα καταχώρηση → μείνε ανοιχτός για συνημμένα
        curId = r.id;
        mountFiles();
        toast('Αποθηκεύτηκε ✓ — μπορείς τώρα να προσθέσεις αρχεία');
        return;
      }
      ovl.remove(); toast('Αποθηκεύτηκε ✓');
    };
  }
};

/* ═════════ ✅ ΤΟ ΠΛΑΝΟ ΜΟΥ (ανά project — «πού έμεινα») ═════════ */
R.todos = async function () {
  setTop('Το πλάνο μου', 'Ανά project — τι έχεις να κάνεις & πού έμεινες');
  const c = $('#content');
  c.innerHTML = '<div class="skel" style="height:130px;margin-bottom:12px"></div>'.repeat(3);
  const remLbl = r => new Date(r.replace(' ', 'T')).toLocaleString((window.CNP_LOCALE||'el-GR'), {day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit'});
  const load = async () => {
    const d = await api('todos_list');
    if (!d.groups.length) { c.innerHTML = `<div class="empty" style="padding:44px"><div class="big">${I.checkSquare}</div>Δεν έχεις ανοιχτά project ακόμη</div>`; return; }
    c.innerHTML = d.groups.map(g => {
      const openN = g.todos.filter(t => !t.done).length;
      const doneN = g.todos.filter(t => t.done).length;
      return `<div class="card" style="margin-bottom:14px;border-left:3px solid ${g.color}">
      <div class="card-h" style="align-items:center">
        <span class="dot" style="background:${g.color}"></span> ${esc(g.name)}
        ${g.tasks.length ? `<span class="mut" style="font-weight:400;font-size:11px;margin-left:8px">${g.tasks.length} ανοιχτά tasks</span>` : ''}
        <span class="kb-n" style="margin-left:auto">${openN}</span>
      </div>
      <div class="card-b">
        <div style="margin-bottom:12px">
          <label class="lbl">📍 Πού έμεινα / σημειώσεις</label>
          <textarea class="inp" data-wn="${g.id}" rows="2" placeholder="π.χ. έμεινα στη ρύθμιση DNS, περιμένω απάντηση πελάτη…" style="font-size:12.5px">${esc((g.note || '').replace(/<br\s*\/?>/gi, '\n').replace(/<[^>]+>/g, ''))}</textarea>
          <div style="text-align:right;margin-top:4px"><button class="btn btn-sm btn-o" data-wnsave="${g.id}">${I.save} Αποθήκευση σημείωσης</button></div>
        </div>
        <div class="todo-items" data-proj="${g.id}">
          ${g.todos.length ? g.todos.map(t => `<div class="todo-row" data-tid="${t.id}" style="display:flex;gap:8px;align-items:center;padding:5px 0;border-bottom:1px dashed var(--line)">
            <span class="drag-h" title="σύρε για αλλαγή σειράς" style="cursor:grab;color:var(--mut);flex:none;font-size:13px">⋮⋮</span>
            <input type="checkbox" data-ttog="${t.id}" ${t.done ? 'checked' : ''} style="width:17px;height:17px;cursor:pointer;flex:none">
            <span style="flex:1;font-size:13px;${t.done ? 'text-decoration:line-through;color:var(--mut)' : ''}">${esc(t.text)}${t.remind ? ` <span class="pill" style="font-size:9px;margin-left:4px;background:${t.overdue ? 'var(--bad)' : 'var(--warn)'}1a;color:${t.overdue ? 'var(--bad)' : 'var(--warn)'}">${I.clock} ${remLbl(t.remind)}</span>` : ''}</span>
            <button class="btn btn-sm btn-o" data-trem="${t.id}" title="υπενθύμιση με ώρα" style="padding:2px 6px">${I.clock}</button>
            <button class="btn btn-sm btn-o" data-tdel="${t.id}" style="padding:2px 7px;color:var(--bad)">✕</button></div>`).join('') : '<div class="mut" style="font-size:12px;padding:6px 0">Καμία υπενθύμιση ακόμη</div>'}
        </div>
        <div style="display:flex;gap:6px;margin-top:10px;flex-wrap:wrap">
          <input class="inp" data-tadd="${g.id}" placeholder="+ Προσθήκη υπενθύμισης (Enter)" style="flex:1;min-width:160px">
          ${g.tasks.length ? `<button class="btn btn-o btn-sm" data-tseed="${g.id}" title="Δημιούργησε λίστα από τα ανοιχτά σου tasks">${I.zap} Auto από tasks</button>` : ''}
          ${doneN ? `<button class="btn btn-o btn-sm" data-tclear="${g.id}">Καθάρισε ✓ (${doneN})</button>` : ''}
        </div>
        ${g.tasks.length ? `<details style="margin-top:10px"><summary class="mut" style="font-size:11.5px;cursor:pointer">Δες τα ${g.tasks.length} ανοιχτά tasks του project</summary>
          <div style="margin-top:6px;display:flex;flex-direction:column;gap:4px">${g.tasks.map(t => `<div style="display:flex;gap:7px;align-items:center;font-size:12px;cursor:pointer" data-tgo="${t.id}"><span style="color:var(--brand)">${I.checkSquare}</span>${esc(t.title)}</div>`).join('')}</div></details>` : ''}
      </div></div>`;
    }).join('');
    $$('[data-ttog]').forEach(ch => ch.onclick = async () => { await api('todo_toggle', {id: +ch.dataset.ttog}); load(); });
    $$('[data-tdel]').forEach(b => b.onclick = async () => { await api('todo_del', {id: +b.dataset.tdel}); load(); });
    $$('[data-tadd]').forEach(inp => inp.onkeydown = async e => { if (e.key === 'Enter' && inp.value.trim()) { await api('todo_add', {project: +inp.dataset.tadd, text: inp.value.trim()}); inp.value = ''; load(); } });
    $$('[data-tseed]').forEach(b => b.onclick = async () => { const r = await api('todo_seed', {project: +b.dataset.tseed}); toast(r.added ? r.added + ' προστέθηκαν' : 'Όλα ήδη στη λίστα'); load(); });
    $$('[data-tclear]').forEach(b => b.onclick = async () => { await api('todo_clear_done', {project: +b.dataset.tclear}); load(); });
    $$('[data-wnsave]').forEach(b => b.onclick = async () => { const ta = document.querySelector(`textarea[data-wn="${b.dataset.wnsave}"]`); await api('worknote_save', {project: +b.dataset.wnsave, note: ta.value.replace(/\n/g, '<br>')}); toast('Σημείωση αποθηκεύτηκε ✓'); });
    $$('[data-tgo]').forEach(x => x.onclick = () => openTask(+x.dataset.tgo));
    $$('[data-trem]').forEach(b => b.onclick = async () => {
      const d0 = new Date(Date.now() + 86400000);
      const def = `${d0.getFullYear()}-${String(d0.getMonth() + 1).padStart(2, '0')}-${String(d0.getDate()).padStart(2, '0')} 09:00`;
      const v = await cnpPrompt('Πότε να σου θυμίσω; (μορφή: ΕΕΕΕ-ΜΜ-ΗΗ ΩΩ:ΛΛ) — κενό για αφαίρεση', {title: I.clock + ' Υπενθύμιση', input: def, placeholder: '2026-07-26 09:30', ok: 'Ορισμός'});
      if (v === null) { return; }
      await api('todo_update', {id: +b.dataset.trem, remind: v.trim()});
      toast(v.trim() ? 'Υπενθύμιση ⏰ ορίστηκε' : 'Υπενθύμιση αφαιρέθηκε'); load();
    });
    $$('.todo-items').forEach(cont => enableTodoDrag(cont));
  };
  function enableTodoDrag(cont) {
    let drag = null;
    cont.querySelectorAll('.todo-row').forEach(row => {
      row.draggable = true;
      row.ondragstart = () => { drag = row; row.classList.add('dragging'); row.style.opacity = '.4'; };
      row.ondragend = async () => {
        row.classList.remove('dragging'); row.style.opacity = '';
        const ids = [...cont.querySelectorAll('.todo-row')].map(x => +x.dataset.tid);
        await api('todo_reorder', {ids});
      };
    });
    cont.ondragover = e => {
      if (!drag) { return; }
      e.preventDefault();
      const after = [...cont.querySelectorAll('.todo-row:not(.dragging)')].reduce((closest, child) => {
        const box = child.getBoundingClientRect(); const offset = e.clientY - box.top - box.height / 2;
        return (offset < 0 && offset > closest.offset) ? {offset, el: child} : closest;
      }, {offset: -Infinity, el: null}).el;
      if (after == null) { cont.appendChild(drag); } else { cont.insertBefore(drag, after); }
    };
  }
  await load();
};

/* ═════════ 🧑‍💼 ΠΡΟΣΛΗΨΕΙΣ / ΒΙΟΓΡΑΦΙΚΑ ═════════ */
const _cvStatusCol = {new: '#0097e4', review: '#e0a020', shortlist: '#7b5cd6', interview: '#16a26a', rejected: '#e2515f', hired: '#0a8a4f'};
const _cvDecision = {shortlist: ['Shortlist', '#7b5cd6'], interview: ['Συνέντευξη', '#16a26a'], maybe: ['Ίσως', '#e0a020'], reject: ['Απόρριψη', '#e2515f']};
const _cvScoreCol = n => n === null ? 'var(--mut)' : n >= 75 ? '#16a26a' : n >= 55 ? '#0090dd' : n >= 35 ? '#e0a020' : '#e2515f';
const _cvDate = d => d ? new Date(d.replace(' ', 'T')).toLocaleDateString((window.CNP_LOCALE||'el-GR'), {day: '2-digit', month: '2-digit', year: 'numeric'}) : '';
function _cvRing(n) {
  const r = 24, circ = 2 * Math.PI * r, off = circ * (1 - (n || 0) / 100), cl = _cvScoreCol(n);
  return `<div style="position:relative;width:58px;height:58px;flex:none"><svg width="58" height="58" viewBox="0 0 58 58">
    <circle cx="29" cy="29" r="${r}" fill="none" stroke="var(--line)" stroke-width="6"/>
    <circle cx="29" cy="29" r="${r}" fill="none" stroke="${cl}" stroke-width="6" stroke-linecap="round" stroke-dasharray="${circ}" stroke-dashoffset="${off}" transform="rotate(-90 29 29)"/></svg>
    <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:15px;color:var(--ink)">${n === null ? '—' : n}</div></div>`;
}

R.recruit = async function () {
  setTop('Προσλήψεις', 'Βιογραφικά υποψηφίων — αξιολόγηση με AI co-pilot');
  const c = $('#content');
  const st = R.recruit._s = R.recruit._s || {job: '', status: '', q: '', page: 1, per: 50, dups: false};
  c.innerHTML = '<div class="skel" style="height:60px;margin-bottom:12px"></div><div class="skel" style="height:420px"></div>';
  const jd = await api('cv_jobs').catch(() => null);
  if (!jd) { c.innerHTML = `<div class="empty"><div class="big">${I.lock}</div>Χρειάζεσαι ειδικότητα «HR» για αυτή την ενότητα.</div>`; return; }
  const statuses = jd.statuses; window._cvStatuses = statuses;
  window._cvModels = jd.models || {}; window._cvDefaultModel = jd.defaultModel || '';
  const activeJobs = jd.jobs.filter(j => j.active).length;
  c.innerHTML = `
  <div style="display:flex;gap:8px;margin-bottom:14px;border-bottom:1px solid var(--line);padding-bottom:0">
    <button class="rtab" data-rview="cvs" style="background:none;border:0;border-bottom:2.5px solid transparent;padding:9px 4px;margin-right:14px;font-size:14.5px;font-weight:700;color:var(--mut);cursor:pointer">${I.users || ''} Υποψήφιοι</button>
    <button class="rtab" data-rview="jobs" style="background:none;border:0;border-bottom:2.5px solid transparent;padding:9px 4px;font-size:14.5px;font-weight:700;color:var(--mut);cursor:pointer">${I.briefcase || I.folder} Θέσεις / Αγγελίες <span class="kb-n" style="margin-left:2px">${activeJobs}</span></button>
  </div>
  <div id="cvPane">
    <div class="card" style="padding:12px 15px;display:flex;gap:9px;align-items:center;flex-wrap:wrap;margin-bottom:12px">
      <select class="inp" id="cvJob" style="width:auto;max-width:280px"><option value="">Όλες οι θέσεις</option>
        ${jd.jobs.filter(j => j.count).map(j => `<option value="${j.id}" ${st.job == j.id ? 'selected' : ''}>${esc(j.title)} (${j.count})</option>`).join('')}</select>
      <input class="inp" id="cvQ" placeholder="Αναζήτηση ονόματος / email / τηλεφώνου…" style="flex:1;min-width:180px" value="${esc(st.q)}">
      <button class="btn btn-sm ${st.dups ? 'btn-p' : 'btn-o'}" id="cvDups" title="Δείξε μόνο όσους υπέβαλαν πολλές φορές (ίδιο email)">⧉ Διπλότυπα</button>
      <button class="btn btn-p btn-sm" id="cvAdd">${I.plus} Νέος υποψήφιος</button>
    </div>
    <div id="cvTabs" style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px"></div>
    <div id="cvList">${'<div class="skel" style="height:56px;margin-bottom:8px"></div>'.repeat(5)}</div>
    <div id="cvPager" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-top:14px"></div>
  </div>
  <div id="jobsPane" style="display:none"></div>`;
  const setView = v => {
    st.view = v;
    $('#cvPane').style.display = v === 'cvs' ? '' : 'none';
    $('#jobsPane').style.display = v === 'jobs' ? '' : 'none';
    $$('.rtab').forEach(b => { const on = b.dataset.rview === v; b.style.color = on ? 'var(--brand)' : 'var(--mut)'; b.style.borderBottomColor = on ? 'var(--brand)' : 'transparent'; });
    if (v === 'jobs') { renderJobsPanel($('#jobsPane'), () => { R.recruit(); }); }
  };
  $$('.rtab').forEach(b => b.onclick = () => setView(b.dataset.rview));
  const cvAva = x => x.photo
    ? `<img src="api.php?a=cv_photo&id=${x.id}" style="width:36px;height:36px;border-radius:50%;object-fit:cover;flex:none;border:1px solid var(--line)" loading="lazy">`
    : `<span class="ava" style="width:36px;height:36px;font-size:12px;flex:none">${esc((x.name || '?').trim().split(/\s+/).map(w => w[0] || '').slice(0, 2).join('').toUpperCase())}</span>`;
  const cvRow = x => `<div class="cvrow" data-cvo="${x.id}">
    <div class="cv-score">${x.aiScore !== null ? `<span style="display:inline-block;min-width:34px;padding:3px 0;border-radius:8px;font-weight:800;font-size:13px;color:#fff;background:${_cvScoreCol(x.aiScore)}">${x.aiScore}</span>` : '<span class="mut" style="font-size:16px">·</span>'}</div>
    ${cvAva(x)}
    <div class="cv-main"><b style="font-size:13.5px">${esc(x.name)}</b>
      <div class="mut" style="font-size:11.5px">${esc(x.jobTitle || '—')}${x.category ? ' · ' + esc(x.category) : ''}${x.seniority ? ' · ' + esc(x.seniority) : ''}</div></div>
    <div class="cv-tags">
    ${x.aiGen === 'ai' ? `<span class="pill" style="background:#e2515f1a;color:#e2515f;font-size:9px" title="πιθανό AI-generated">🤖 AI</span>` : x.aiGen === 'mixed' ? `<span class="pill" style="background:#e0a0201a;color:#e0a020;font-size:9px" title="μερικώς AI">🤖 ~</span>` : ''}
    ${x.dup > 1 ? `<span class="pill" style="background:#8291a91a;color:#8291a9;font-size:9px" title="υπέβαλε ${x.dup} φορές (ίδιο email)">⧉ ×${x.dup}</span>` : ''}
    ${x.appliedAt ? `<span class="mut" style="font-size:11px;white-space:nowrap" title="ημ. υποβολής">${_cvDate(x.appliedAt)}</span>` : ''}
    ${x.decision && _cvDecision[x.decision] ? `<span class="pill" style="background:${_cvDecision[x.decision][1]}1a;color:${_cvDecision[x.decision][1]};font-size:9px">${_cvDecision[x.decision][0]}</span>` : ''}
    ${x.rating ? `<span style="color:#e0a020;font-size:11px">${'★'.repeat(x.rating)}</span>` : ''}
    <span class="pill" style="background:${_cvStatusCol[x.status]}1a;color:${_cvStatusCol[x.status]};font-size:9px">${esc(statuses[x.status] || x.status)}</span>
    ${x.hasCv ? `<span class="mut" title="έχει CV" style="display:inline-flex">${I.doc}</span>` : ''}</div></div>`;
  const load = async () => {
    const d = await api('cv_list&job=' + st.job + '&status=' + st.status + '&q=' + encodeURIComponent(st.q) + '&page=' + st.page + '&per=' + st.per + (st.dups ? '&dups=1' : ''));
    st.page = d.page;
    const dupsBtn = $('#cvDups'); if (dupsBtn) { dupsBtn.className = 'btn btn-sm ' + (st.dups ? 'btn-p' : 'btn-o'); dupsBtn.innerHTML = '⧉ Διπλότυπα' + (d.dupTotal ? ' <span class="kb-n" style="margin-left:3px">' + d.dupTotal + '</span>' : ''); }
    const tabs = [['', 'Όλες', d.totalAll]].concat(Object.entries(statuses).map(([k, l]) => [k, l, d.counts[k] || 0]));
    $('#cvTabs').innerHTML = tabs.map(([k, l, n]) => `<button class="btn btn-sm ${st.status === k ? 'btn-p' : 'btn-o'}" data-cvstatus="${k}">${l}${n ? ` <span class="kb-n" style="margin-left:3px">${n}</span>` : ''}</button>`).join('');
    const box = $('#cvList');
    box.innerHTML = d.items.length ? d.items.map(cvRow).join('') : '<div class="empty" style="padding:36px">Κανένας υποψήφιος</div>';
    // pagination
    const from = d.filtered ? (d.page - 1) * d.per + 1 : 0;
    const to = Math.min(d.page * d.per, d.filtered);
    $('#cvPager').innerHTML = `
      <span class="mut" style="font-size:12.5px">${from}–${to} από ${d.filtered}</span>
      <div style="display:flex;gap:5px;align-items:center;margin-left:auto">
        <button class="btn btn-o btn-sm" data-pg="1" ${d.page <= 1 ? 'disabled' : ''}>«</button>
        <button class="btn btn-o btn-sm" data-pg="${d.page - 1}" ${d.page <= 1 ? 'disabled' : ''}>‹</button>
        <span style="font-size:12.5px;padding:0 6px">Σελ. ${d.page}/${d.pages}</span>
        <button class="btn btn-o btn-sm" data-pg="${d.page + 1}" ${d.page >= d.pages ? 'disabled' : ''}>›</button>
        <button class="btn btn-o btn-sm" data-pg="${d.pages}" ${d.page >= d.pages ? 'disabled' : ''}>»</button>
      </div>
      <select class="inp" id="cvPer" style="width:auto;font-size:12.5px;padding:5px 8px">${[25, 50, 100, 200].map(n => `<option value="${n}" ${st.per === n ? 'selected' : ''}>${n} / σελίδα</option>`).join('')}</select>`;
    $$('[data-cvstatus]').forEach(b => b.onclick = () => { st.status = b.dataset.cvstatus; st.page = 1; load(); });
    $$('[data-cvo]').forEach(r => r.onclick = () => openCv(+r.dataset.cvo));
    $$('#cvPager [data-pg]').forEach(b => b.onclick = () => { if (!b.disabled) { st.page = +b.dataset.pg; load(); window.scrollTo(0, 0); const cc = $('.content'); if (cc) { cc.scrollTop = 0; } } });
    const perSel = $('#cvPer'); if (perSel) { perSel.onchange = () => { st.per = +perSel.value; st.page = 1; load(); }; }
  };
  await load();
  $('#cvJob').onchange = () => { st.job = $('#cvJob').value; st.page = 1; load(); };
  let qt; $('#cvQ').oninput = () => { clearTimeout(qt); qt = setTimeout(() => { st.q = $('#cvQ').value.trim(); st.page = 1; load(); }, 300); };
  $('#cvAdd').onclick = () => openCvAdd(jd.jobs, load);
  $('#cvDups').onclick = () => { st.dups = !st.dups; st.page = 1; load(); };
  setView(st.view || 'cvs');
};

function renderJobsPanel(host, reload) {
  host.innerHTML = '<div class="skel" style="height:260px"></div>';
  const render = async () => {
    const d = await api('cv_jobs');
    host.innerHTML = `
      <div style="background:linear-gradient(120deg,#e8f6ff,#f4f7fb);border:1px solid var(--line);border-radius:12px;padding:14px 16px;margin-bottom:16px;display:flex;gap:14px;align-items:center;flex-wrap:wrap">
        <div style="flex:1;min-width:220px;font-size:12.5px">🔗 <b>Δημόσια σελίδα καριέρας:</b><br><a href="${esc(d.applyUrl)}" target="_blank" style="color:var(--brand);word-break:break-all">${esc(d.applyUrl)}</a>
          <div class="mut" style="font-size:11px;margin-top:3px">Οι ενδιαφερόμενοι βλέπουν & κάνουν αίτηση μόνο στις <b>ενεργές</b> θέσεις.</div></div>
        <button class="btn btn-o btn-sm" id="jmCopy">⧉ Αντιγραφή link</button>
        <a class="btn btn-o btn-sm" href="${esc(d.applyUrl)}" target="_blank">↗ Προεπισκόπηση</a>
        <button class="btn btn-p" id="jmNew">${I.plus} Νέα θέση</button></div>
      <div id="jmForm"></div>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:12px">
        ${d.jobs.length ? d.jobs.map(j => `<div class="card" style="padding:14px 16px;display:flex;flex-direction:column;gap:8px${j.active ? '' : ';opacity:.72'}">
          <div style="height:86px;margin:-14px -16px 8px;border-radius:15px 15px 0 0;background:#eef2f7 url('${(d.imageBase || 'apply-assets/jobs/')}${esc(j.imageResolved || 'office')}.jpg') center/cover"></div>
          <div style="display:flex;align-items:center;gap:8px">
            <span style="width:9px;height:9px;border-radius:50%;background:${j.active ? 'var(--ok)' : 'var(--mut)'};flex:none" title="${j.active ? 'ενεργή' : 'ανενεργή'}"></span>
            <b style="font-size:14px;flex:1;min-width:0">${esc(j.title)}</b>
            <span class="pill" style="font-size:9px;background:${j.active ? 'var(--ok)' : 'var(--mut)'}1a;color:${j.active ? 'var(--ok)' : 'var(--mut)'}">${j.active ? 'ενεργή' : 'ανενεργή'}</span></div>
          <div class="mut" style="font-size:11.5px">👥 ${j.count} υποψήφιοι${j.location ? ' · 📍 ' + esc(j.location) : ''}${j.emptype ? ' · ' + esc(j.emptype) : ''}</div>
          ${j.skills ? `<div style="display:flex;gap:4px;flex-wrap:wrap">${j.skills.split(/[,\n·]+/).map(s => s.trim()).filter(Boolean).slice(0, 6).map(s => `<span class="pill" style="font-size:9px">${esc(s)}</span>`).join('')}</div>` : ''}
          <div style="display:flex;gap:6px;margin-top:auto;padding-top:4px">
            <button class="btn btn-sm btn-o" data-jmedit="${j.id}" style="flex:1">${I.edit} Επεξεργασία</button>
            <button class="btn btn-sm btn-o" data-jmdel="${j.id}" style="color:var(--bad)">${I.trash}</button></div></div>`).join('')
          : '<div class="empty" style="padding:30px;grid-column:1/-1">Καμία θέση ακόμη — πάτα «Νέα θέση» ή άσε την AI να συντάξει μία.</div>'}
      </div>`;
    host.querySelector('#jmCopy').onclick = async () => { await navigator.clipboard.writeText(d.applyUrl); toast('Αντιγράφηκε ✓'); };
    host.querySelector('#jmNew').onclick = () => jobForm(null);
    host.querySelectorAll('[data-jmedit]').forEach(b => b.onclick = () => jobForm(d.jobs.find(x => x.id === +b.dataset.jmedit)));
    host.querySelectorAll('[data-jmdel]').forEach(b => b.onclick = async () => { const j = d.jobs.find(x => x.id === +b.dataset.jmdel); if (!await cnpConfirm('Διαγραφή θέσης «' + j.title + '»;')) { return; } const r = await api('cv_job_del', {id: j.id}); toast(r.archived ? r.msg : 'Διαγράφηκε'); render(); });
    function jobForm(j) {
      const isNew = !j; const f = host.querySelector('#jmForm');
      let curImg = isNew ? '' : (j.image || '');
      const asText = v => Array.isArray(v) ? v.join('\n') : (v == null ? '' : String(v));
      const S = (j && j.sections) ? j.sections : null;
      const el = S && S.el ? S.el : {}, en = S && S.en ? S.en : {};
      // descr_json αποθηκεύεται με keys resp/req/ben (readSec)· δεχόμαστε & AI-shape ως fallback
      const gv = (o, k, k2) => asText(o[k] != null && o[k] !== '' ? o[k] : (o[k2] ?? ''));
      const V = {
        el: {intro: asText(el.intro) || (!S && j ? asText(j.descr) : ''), resp: gv(el, 'resp', 'responsibilities'), req: gv(el, 'req', 'requirements'), ben: gv(el, 'ben', 'benefits'), skills: (S ? asText(el.skills) : (j ? asText(j.skills) : ''))},
        en: {intro: asText(en.intro) || (!S && j ? asText(j.descrEn) : ''), resp: gv(en, 'resp', 'responsibilities'), req: gv(en, 'req', 'requirements'), ben: gv(en, 'ben', 'benefits'), skills: (S ? asText(en.skills) : (j ? asText(j.skillsEn) : ''))},
      };
      const LBL = {el: {intro: 'Η ΘΕΣΗ', resp: 'ΑΡΜΟΔΙΟΤΗΤΕΣ', req: 'ΤΙ ΖΗΤΑΜΕ', ben: 'ΤΙ ΠΡΟΣΦΕΡΟΥΜΕ'}, en: {intro: 'THE ROLE', resp: 'RESPONSIBILITIES', req: 'REQUIREMENTS', ben: 'WHAT WE OFFER'}};
      const compose = (s, L) => { const out = []; const add = (h, txt, bul) => { const v = (txt || '').trim(); if (!v) { return; } out.push(h); if (bul) { v.split('\n').map(x => x.replace(/^[-•·]\s*/, '').trim()).filter(Boolean).forEach(x => out.push('- ' + x)); } else { out.push(v); } out.push(''); }; add(L.intro, s.intro, false); add(L.resp, s.resp, true); add(L.req, s.req, true); add(L.ben, s.ben, true); return out.join('\n').trim(); };
      const pane = (lg, v, ph) => `<div id="pane_${lg}" style="${lg === 'en' ? 'display:none' : ''}">
        ${lg === 'en' ? `<label class="lbl">Τίτλος (EN)</label><input class="inp" id="jfTen" value="${isNew ? '' : esc(j.titleEn || '')}" placeholder="e.g. IT Help Desk Technician">
        <div class="frow" style="gap:14px;margin-top:9px"><div><label class="lbl">Τύπος (EN)</label><input class="inp" id="jfTypeEn" value="${isNew ? '' : esc(j.emptypeEn || '')}" placeholder="Full-time / Part-time / Remote"></div><div></div></div>` : ''}
        <label class="lbl" style="margin-top:11px">📝 ${lg === 'en' ? 'Overview' : 'Εισαγωγή / περίληψη θέσης'}</label>
        <textarea class="inp" id="jfIntro_${lg}" rows="3" placeholder="${ph.intro}">${esc(v.intro)}</textarea>
        <label class="lbl" style="margin-top:10px">✅ ${lg === 'en' ? 'Responsibilities' : 'Αρμοδιότητες'} <span class="mut" style="text-transform:none;font-weight:400">— ${lg === 'en' ? 'one per line' : 'μία ανά γραμμή'}</span></label>
        <textarea class="inp" id="jfResp_${lg}" rows="5" placeholder="${ph.list}">${esc(v.resp)}</textarea>
        <label class="lbl" style="margin-top:10px">🎯 ${lg === 'en' ? 'Requirements' : 'Τι ζητάμε (προσόντα)'} <span class="mut" style="text-transform:none;font-weight:400">— ${lg === 'en' ? 'one per line' : 'μία ανά γραμμή'}</span></label>
        <textarea class="inp" id="jfReq_${lg}" rows="5" placeholder="${ph.list}">${esc(v.req)}</textarea>
        <label class="lbl" style="margin-top:10px">🎁 ${lg === 'en' ? 'What we offer' : 'Τι προσφέρουμε'} <span class="mut" style="text-transform:none;font-weight:400">— ${lg === 'en' ? 'one per line' : 'μία ανά γραμμή'}</span></label>
        <textarea class="inp" id="jfBen_${lg}" rows="4" placeholder="${ph.list}">${esc(v.ben)}</textarea>
        <label class="lbl" style="margin-top:10px">🧩 Skills <span class="mut" style="text-transform:none;font-weight:400">— comma separated</span></label>
        <textarea class="inp" id="jfSkills_${lg}" rows="2" placeholder="${lg === 'en' ? 'e.g. Windows/Linux, TCP/IP, ticketing, English' : 'π.χ. Windows/Linux, δίκτυα TCP/IP, ticketing, Αγγλικά'}">${esc(v.skills)}</textarea>
      </div>`;
      f.innerHTML = `<div class="card" style="border:1.5px solid var(--brand);padding:18px 20px;margin-bottom:16px">
        <h3 style="margin:0 0 12px;font-size:15px">${isNew ? '➕ Νέα θέση' : '✏️ Επεξεργασία: ' + esc(j.title)}</h3>
        <div class="frow" style="gap:14px"><div style="flex:2"><label class="lbl">Τίτλος θέσης (EL) *</label><input class="inp" id="jfT" value="${isNew ? '' : esc(j.title)}" placeholder="π.χ. IT Help Desk Technician"></div>
          <div><label class="lbl">Τοποθεσία</label><input class="inp" id="jfLoc" value="${isNew ? '' : esc(j.location || '')}" placeholder="π.χ. Αθήνα / Remote"></div></div>
        <div class="frow" style="gap:14px;margin-top:11px"><div><label class="lbl">Τύπος απασχόλησης</label><input class="inp" id="jfType" list="jfTypeL" value="${isNew ? '' : esc(j.emptype || '')}" placeholder="Πλήρης / Μερική / Remote"><datalist id="jfTypeL"><option value="Πλήρης απασχόληση"></option><option value="Μερική απασχόληση"></option><option value="Remote"></option><option value="Σύμβαση έργου"></option><option value="Πρακτική"></option></datalist></div>
          <div style="display:flex;align-items:flex-end"><label style="display:flex;gap:8px;align-items:center;font-size:13px;padding-bottom:9px;cursor:pointer"><input type="checkbox" id="jfActive" ${isNew || j.active ? 'checked' : ''} style="width:17px;height:17px">Ενεργή (ορατή δημόσια)</label></div></div>
        <label class="lbl" style="margin-top:14px">🖼️ Φωτογραφία θέσης <span class="mut" style="text-transform:none;font-weight:400">— εμφανίζεται στην αγγελία (δημόσια)</span></label>
        <div id="jfImgs" style="display:flex;gap:8px;flex-wrap:wrap"></div>
        <div style="display:flex;align-items:center;gap:8px;margin-top:16px;flex-wrap:wrap">
          <button class="btn btn-sm" id="jfDraft" style="background:linear-gradient(135deg,#7c5cff,#5a8dee);color:#fff;border:0" title="Γράφει αναλυτική αγγελία σε EL & EN">✨ Σύνταξη με AI (EL + EN)</button>
          <button class="btn btn-sm btn-o" id="jfTranslate" title="Μεταφράζει το ελληνικό κείμενο στα Αγγλικά">🌐 Μετάφραση EL→EN</button>
          <span class="mut" id="jfDraftHint" style="font-size:11px;flex:1;min-width:120px">💡 Δώσε τίτλο & skills και άσε την AI να γράψει δομημένη αγγελία και στις δύο γλώσσες.</span></div>
        <div style="display:flex;gap:4px;margin:16px 0 12px;border-bottom:1px solid var(--line)">
          <button class="btn btn-sm jltab" data-lg="el" style="border:0;border-bottom:2.5px solid var(--brand);border-radius:0;background:none;color:var(--brand);font-weight:700">🇬🇷 Ελληνικά</button>
          <button class="btn btn-sm jltab" data-lg="en" style="border:0;border-bottom:2.5px solid transparent;border-radius:0;background:none;color:var(--mut);font-weight:700">🇬🇧 English</button></div>
        ${pane('el', V.el, {intro: 'Σύντομη περιγραφή της θέσης & της ομάδας…', list: '- π.χ. Υποστήριξη χρηστών\n- Διαχείριση αιτημάτων'})}
        ${pane('en', V.en, {intro: 'Short description of the role & the team…', list: '- e.g. User support\n- Ticket handling'})}
        <div style="margin-top:16px;display:flex;gap:8px"><button class="btn btn-p" id="jfSave">${I.save} Αποθήκευση</button><button class="btn btn-o" id="jfCancel">Άκυρο</button></div></div>`;
      f.scrollIntoView({behavior: 'smooth', block: 'nearest'});
      const val = id => { const x = f.querySelector('#' + id); return x ? x.value.trim() : ''; };
      const setVal = (id, v) => { const x = f.querySelector('#' + id); if (x) { x.value = v; } };
      // picker φωτογραφίας θέσης
      const imgPresets = d.imagePresets || {}, imgBase = d.imageBase || 'apply-assets/jobs/';
      const renderImgs = () => {
        const box = f.querySelector('#jfImgs'); if (!box) { return; }
        const tile = (v, label, style, thumb) => `<button type="button" data-img="${v}" title="${esc(label)}" style="width:108px;height:66px;border-radius:9px;border:2px solid ${curImg === v ? 'var(--brand)' : 'var(--line)'};cursor:pointer;overflow:hidden;padding:0;position:relative;${style}">
          ${thumb ? `<img src="${imgBase}${v}.jpg" style="width:100%;height:100%;object-fit:cover;display:block" loading="lazy">` : '<div style="font-size:20px;padding-top:8px">✨</div>'}
          <span style="position:absolute;left:0;right:0;bottom:0;background:rgba(9,20,38,.66);color:#fff;font-size:9px;font-weight:700;padding:2px 3px;text-align:center;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${esc(label)}</span>
          ${curImg === v ? '<span style="position:absolute;top:3px;right:3px;background:var(--brand);color:#fff;border-radius:50%;width:16px;height:16px;font-size:10px;display:flex;align-items:center;justify-content:center">✓</span>' : ''}</button>`;
        box.innerHTML = tile('', 'Αυτόματη', 'background:linear-gradient(135deg,#e8f6ff,#d3ecff)', false)
          + Object.entries(imgPresets).map(([k, l]) => tile(k, l, 'background:#eef2f7', true)).join('');
        box.querySelectorAll('[data-img]').forEach(b => b.onclick = () => { curImg = b.dataset.img; renderImgs(); });
      };
      renderImgs();
      f.querySelectorAll('.jltab').forEach(b => b.onclick = () => {
        const lg = b.dataset.lg;
        f.querySelector('#pane_el').style.display = lg === 'el' ? '' : 'none';
        f.querySelector('#pane_en').style.display = lg === 'en' ? '' : 'none';
        f.querySelectorAll('.jltab').forEach(x => { const on = x.dataset.lg === lg; x.style.color = on ? 'var(--brand)' : 'var(--mut)'; x.style.borderBottomColor = on ? 'var(--brand)' : 'transparent'; });
      });
      const readSec = lg => ({intro: val('jfIntro_' + lg), resp: val('jfResp_' + lg), req: val('jfReq_' + lg), ben: val('jfBen_' + lg), skills: val('jfSkills_' + lg)});
      const fillLang = (lg, s) => { if (!s) { return; } setVal('jfIntro_' + lg, asText(s.intro)); setVal('jfResp_' + lg, asText(s.responsibilities)); setVal('jfReq_' + lg, asText(s.requirements)); setVal('jfBen_' + lg, asText(s.benefits)); if (s.skills) { setVal('jfSkills_' + lg, asText(s.skills)); } };
      f.querySelector('#jfCancel').onclick = () => { f.innerHTML = ''; };
      f.querySelector('#jfDraft').onclick = async () => {
        const btn = f.querySelector('#jfDraft'); const title = val('jfT');
        if (!title) { toast('Δώσε πρώτα τίτλο θέσης', true); return; }
        const filled = ['jfIntro_el', 'jfResp_el', 'jfIntro_en'].some(id => val(id));
        if (filled && !await cnpConfirm('Υπάρχει ήδη περιεχόμενο — να αντικατασταθεί από την AI;')) { return; }
        btn.disabled = true; btn.textContent = '✨ Σύνταξη…'; f.querySelector('#jfDraftHint').textContent = 'Η AI γράφει την αγγελία σε EL & EN…';
        const r = await api('cv_job_draft', {title, skills: val('jfSkills_el') || val('jfSkills_en'), location: val('jfLoc'), emptype: val('jfType')}).catch(e => ({error: (e && e.message) || 'σφάλμα'}));
        btn.disabled = false; btn.innerHTML = '✨ Σύνταξη με AI (EL + EN)';
        if (r && r.ok && r.sections) { fillLang('el', r.sections.el); fillLang('en', r.sections.en); if (r.sections.en && r.sections.en.title && !val('jfTen')) { setVal('jfTen', r.sections.en.title); } f.querySelector('#jfDraftHint').textContent = '✓ Έτοιμο σε EL & EN — έλεγξε/προσάρμοσε και αποθήκευσε.'; toast('Η AI συνέταξε την αγγελία ✓'); }
        else { f.querySelector('#jfDraftHint').textContent = '⚠ ' + ((r && r.error) || 'Σφάλμα AI'); toast((r && r.error) || 'Σφάλμα AI', true); }
      };
      f.querySelector('#jfTranslate').onclick = async () => {
        const btn = f.querySelector('#jfTranslate'); const title = val('jfT');
        const elText = compose(readSec('el'), LBL.el);
        if (!elText) { toast('Συμπλήρωσε πρώτα το ελληνικό κείμενο', true); return; }
        if (['jfIntro_en', 'jfResp_en'].some(id => val(id)) && !await cnpConfirm('Υπάρχει ήδη αγγλικό κείμενο — να αντικατασταθεί;')) { return; }
        btn.disabled = true; btn.textContent = '🌐 Μετάφραση…'; f.querySelector('#jfDraftHint').textContent = 'Μετάφραση στα Αγγλικά…';
        const r = await api('cv_job_draft', {mode: 'translate', title, descr: elText}).catch(e => ({error: (e && e.message) || 'σφάλμα'}));
        btn.disabled = false; btn.innerHTML = '🌐 Μετάφραση EL→EN';
        if (r && r.ok && r.sections && r.sections.en) { fillLang('en', r.sections.en); if (r.sections.en.title && !val('jfTen')) { setVal('jfTen', r.sections.en.title); } f.querySelector('#pane_el').style.display = 'none'; f.querySelector('#pane_en').style.display = ''; f.querySelectorAll('.jltab').forEach(x => { const on = x.dataset.lg === 'en'; x.style.color = on ? 'var(--brand)' : 'var(--mut)'; x.style.borderBottomColor = on ? 'var(--brand)' : 'transparent'; }); f.querySelector('#jfDraftHint').textContent = '✓ Μεταφράστηκε — έλεγξε το αγγλικό κείμενο.'; toast('Μεταφράστηκε ✓'); }
        else { f.querySelector('#jfDraftHint').textContent = '⚠ ' + ((r && r.error) || 'Σφάλμα AI'); toast((r && r.error) || 'Σφάλμα AI', true); }
      };
      f.querySelector('#jfSave').onclick = async () => {
        const title = val('jfT'); if (!title) { toast('Δώσε τίτλο (EL)', true); return; }
        const secEl = readSec('el'), secEn = readSec('en');
        await api('cv_job_save', {id: isNew ? 0 : j.id, title, titleEn: val('jfTen'), location: val('jfLoc'), emptype: val('jfType'), emptypeEn: val('jfTypeEn'),
          skills: secEl.skills, skillsEn: secEn.skills, descr: compose(secEl, LBL.el), descrEn: compose(secEn, LBL.en),
          image: curImg, sections: {el: secEl, en: secEn}, active: f.querySelector('#jfActive').checked ? 1 : 0});
        toast('Αποθηκεύτηκε ✓'); render();
      };
    }
  };
  render();
}

function openCvAdd(jobs, reload) {
  const ovl = document.createElement('div'); ovl.className = 'ovl show'; 
  ovl.innerHTML = `<div class="pal-box" style="margin:6vh auto 0;max-width:640px;text-align:left" onclick="event.stopPropagation()">
    <div style="padding:22px 26px">
      <h2 style="margin:0 0 6px;font-size:18px;color:var(--ink);display:flex;align-items:center;gap:9px">${I.contact || I.users} Νέος υποψήφιος</h2>
      <p class="mut" style="font-size:12.5px;margin:0 0 16px">Για βιογραφικά που παραλάβαμε με άλλο τρόπο (email, από κοντά κ.λπ.). Θα αξιολογείται κι αυτό με AI.</p>
      <div class="frow" style="gap:14px"><div><label class="lbl">Ονοματεπώνυμο *</label><input class="inp" id="caName" placeholder="π.χ. Μαρία Παπαδοπούλου"></div>
        <div><label class="lbl">Θέση</label><select class="inp" id="caJob"><option value="">— επίλεξε θέση —</option>${jobs.map(j => `<option value="${j.id}">${esc(j.title)}</option>`).join('')}</select></div></div>
      <div class="frow" style="gap:14px;margin-top:12px"><div><label class="lbl">Email</label><input class="inp" id="caEmail" placeholder="name@example.com"></div>
        <div><label class="lbl">Τηλέφωνο</label><input class="inp" id="caPhone" placeholder="+30…"></div></div>
      <label class="lbl" style="margin-top:12px">Σημείωση / συνοδευτικό</label><textarea class="inp" id="caLetter" rows="2" placeholder="π.χ. σύσταση, πηγή, σχόλια…"></textarea>
      <label class="lbl" style="margin-top:12px">${I.doc} Αρχείο CV (PDF προτιμότερο — για AI ανάλυση)</label>
      <input class="inp" type="file" id="caFile" accept=".pdf,.doc,.docx,.txt,.rtf,image/*">
      <div style="margin-top:18px;display:flex;gap:8px"><button class="btn btn-p" id="caSave">Αποθήκευση</button><button class="btn btn-o" id="caX">Άκυρο</button></div>
    </div></div>`;
  document.body.appendChild(ovl);
  $('#caX', ovl).onclick = () => ovl.remove();
  $('#caSave', ovl).onclick = async () => {
    const name = $('#caName', ovl).value.trim(); if (!name) { toast('Δώσε ονοματεπώνυμο', true); return; }
    const fd = new FormData();
    fd.append('name', name); fd.append('email', $('#caEmail', ovl).value); fd.append('phone', $('#caPhone', ovl).value);
    fd.append('job', $('#caJob', ovl).value); fd.append('letter', $('#caLetter', ovl).value);
    const js = $('#caJob', ovl); if (js.value) { fd.append('job_title', js.options[js.selectedIndex].text); }
    const file = $('#caFile', ovl).files[0]; if (file) { fd.append('file', file); }
    const sv = $('#caSave', ovl); sv.disabled = true; toast('Αποθήκευση…');
    const r = await fetch('api.php?a=cv_add', {method: 'POST', body: fd, credentials: 'same-origin'}).then(x => x.json()).catch(() => ({error: 'δίκτυο'}));
    if (r.ok) { ovl.remove(); toast('Προστέθηκε ✓'); reload(); if (r.id) { openCv(r.id); } } else { toast(r.error || 'Σφάλμα', true); sv.disabled = false; }
  };
}

/* ═══ Reusable widget: Συνημμένα αρχεία & βίντεο (Storage layer, direct-to-S3) ═══
   cnpAttachments(host, {module, refType, refId, canDelete=true, accept}) */
function cnpAttachments(host, opts) {
  const fmtBytes = b => { b = +b || 0; if (b < 1024) return b + ' B'; if (b < 1048576) return (b / 1024).toFixed(0) + ' KB'; if (b < 1073741824) return (b / 1048576).toFixed(1) + ' MB'; return (b / 1073741824).toFixed(2) + ' GB'; };
  const kindIco = k => ({video: '🎬', image: '🖼️', audio: '🎵', doc: '📄'}[k] || '📎');
  let files = [];
  const putToS3 = (url, file, headers, onProg) => new Promise((res, rej) => {
    const xhr = new XMLHttpRequest(); xhr.open('PUT', url);
    if (headers) Object.entries(headers).forEach(([k, v]) => xhr.setRequestHeader(k, v));
    xhr.upload.onprogress = e => { if (e.lengthComputable) onProg(Math.round(e.loaded / e.total * 100)); };
    xhr.onload = () => (xhr.status >= 200 && xhr.status < 300) ? res() : rej(new Error('S3 upload HTTP ' + xhr.status));
    xhr.onerror = () => rej(new Error('S3 upload error')); xhr.send(file);
  });
  const setProg = (pct, label) => { const p = host.querySelector('#cnpaProg'); if (!p) return; if (pct == null) { p.style.display = 'none'; return; } p.style.display = 'block'; p.innerHTML = `<div style="font-size:11.5px;color:var(--mut);margin-bottom:4px">${esc(label || '')} — ${pct}%</div><div style="height:6px;background:var(--line);border-radius:3px;overflow:hidden"><div style="height:100%;width:${pct}%;background:var(--brand);transition:width .2s"></div></div>`; };
  const renderList = () => {
    const box = host.querySelector('#cnpaList'); if (!box) return;
    box.innerHTML = files.length ? files.map(f => {
      const prev = f.kind === 'video' ? `<video src="${f.url}" controls preload="metadata" style="width:100%;max-height:280px;border-radius:8px;background:#000;margin-top:7px"></video>`
        : f.kind === 'image' ? `<img src="${f.url}" loading="lazy" style="max-width:100%;max-height:220px;border-radius:8px;margin-top:7px;display:block">` : '';
      return `<div style="border:1px solid var(--line);border-radius:10px;padding:9px 11px">
        <div style="display:flex;gap:9px;align-items:center">
          <span style="font-size:18px">${kindIco(f.kind)}</span>
          <div style="flex:1;min-width:0"><div style="font-size:12.5px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${esc(f.name)}</div>
            <div class="mut" style="font-size:11px">${fmtBytes(f.size)}${f.driver === 's3' ? ' · ☁️ cloud' : ''}</div></div>
          <a class="btn btn-o btn-sm" href="${f.url}&dl=1" title="Λήψη">${I.download}</a>
          ${opts.canDelete !== false ? `<button class="btn btn-o btn-sm" data-fdel="${f.id}" style="color:var(--bad)" title="Διαγραφή">${I.trash}</button>` : ''}
        </div>${prev}</div>`;
    }).join('') : '<div class="mut" style="font-size:12px">Κανένα συνημμένο ακόμη.</div>';
    box.querySelectorAll('[data-fdel]').forEach(b => b.onclick = async () => { if (!await cnpConfirm('Διαγραφή αρχείου;', {danger: true})) return; await api('file_delete', {id: +b.dataset.fdel}); files = files.filter(x => x.id != b.dataset.fdel); renderList(); toast('Διαγράφηκε'); });
  };
  async function uploadOne(file) {
    try {
      setProg(0, file.name);
      const pre = await api('file_presign_put', {module: opts.module, ref_type: opts.refType, ref_id: opts.refId, filename: file.name, mime: file.type || 'application/octet-stream', size: file.size});
      let rec;
      if (pre.mode === 'direct') {
        await putToS3(pre.uploadUrl, file, pre.headers, p => setProg(p, file.name));
        const c = await api('file_confirm', {module: opts.module, ref_type: opts.refType, ref_id: opts.refId, key: pre.key, orig_name: file.name, mime: file.type || 'application/octet-stream', size: file.size});
        rec = c.file;
      } else {
        const fd = new FormData(); fd.append('module', opts.module); fd.append('ref_type', opts.refType || ''); fd.append('ref_id', opts.refId || 0); fd.append('file', file);
        const r = await fetch('api.php?a=file_upload', {method: 'POST', body: fd, credentials: 'same-origin'}).then(x => x.json());
        if (!r.ok) throw new Error(r.error || 'σφάλμα'); rec = r.file;
      }
      setProg(null); files.unshift(rec); renderList(); toast('Ανέβηκε ✓');
    } catch (e) { setProg(null); toast((e && e.message) || 'Σφάλμα ανεβάσματος', true); }
  }
  host.innerHTML = `
    <div id="cnpaList" style="display:flex;flex-direction:column;gap:8px;margin-bottom:12px"></div>
    <div id="cnpaDrop" class="cnpa-drop" style="border:1.5px dashed var(--line);border-radius:12px;padding:16px;text-align:center;cursor:pointer;transition:.15s">
      <input type="file" id="cnpaInput" multiple style="display:none" ${opts.accept ? `accept="${opts.accept}"` : ''}>
      <div class="cnpa-drop-main" style="font-size:13px;color:var(--mut)">📎 <b style="color:var(--brand)">Επισύναψη</b> — έγγραφα, εικόνες, <b>βίντεο</b><span class="cnpa-drop-drag"> (ή σύρε εδώ)</span></div>
      <div class="cnpa-drop-sub mut" style="font-size:11px;margin-top:3px">Τα βίντεο/μεγάλα ανεβαίνουν κατευθείαν στο cloud storage</div>
    </div>
    <div id="cnpaProg" style="display:none;margin-top:10px"></div>`;
  renderList();
  const drop = host.querySelector('#cnpaDrop'), input = host.querySelector('#cnpaInput');
  drop.onclick = () => input.click();
  input.onchange = () => { [...input.files].forEach(uploadOne); input.value = ''; };
  ['dragover', 'dragenter'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.style.borderColor = 'var(--brand)'; drop.style.background = '#0090dd0a'; }));
  ['dragleave', 'drop'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.style.borderColor = 'var(--line)'; drop.style.background = ''; }));
  drop.addEventListener('drop', e => { [...(e.dataTransfer && e.dataTransfer.files || [])].forEach(uploadOne); });
  api('file_list&module=' + encodeURIComponent(opts.module) + '&ref_type=' + encodeURIComponent(opts.refType || '') + '&ref_id=' + (opts.refId || 0)).then(d => { files = d.files || []; renderList(); }).catch(() => {});
}
window.cnpAttachments = cnpAttachments;   // reusable σε όλα τα views (ES modules)

async function openCv(id) {
  closeDrawer();
  const statuses = window._cvStatuses || {};
  const ovl = document.createElement('div'); ovl.className = 'ovl';   // κλικ έξω ΔΕΝ κλείνει
  const dr = document.createElement('div'); dr.className = 'drawer'; dr.style.width = 'min(780px,96vw)';
  dr.innerHTML = '<div class="drawer-b"><div class="skel" style="height:340px"></div></div>';
  document.body.append(ovl, dr);
  requestAnimationFrame(() => { ovl.classList.add('show'); dr.classList.add('show'); });
  const d = await api('cv_get&id=' + id);
  const renderAi = ai => !ai ? '<div class="mut" style="font-size:12.5px">Δεν έχει γίνει αξιολόγηση ακόμη — πάτα «✨ Αξιολόγηση με AI».</div>' : `
    <div style="display:flex;gap:15px;align-items:center;margin-bottom:11px">${_cvRing(d.aiScore)}
      <div><div class="mut" style="font-size:11.5px">Καταλληλότητα θέσης</div><b style="font-size:17px;color:${_cvScoreCol(ai.fit ?? null)}">${ai.fit ?? '—'}%</b>
        <div style="margin-top:5px;display:flex;gap:5px;flex-wrap:wrap">${ai.category ? `<span class="pill" style="font-size:9.5px">${esc(ai.category)}</span>` : ''}${ai.seniority ? `<span class="pill" style="font-size:9.5px">${esc(ai.seniority)}</span>` : ''}${typeof ai.yearsExp !== 'undefined' ? `<span class="pill" style="font-size:9.5px">${esc(String(ai.yearsExp))} έτη</span>` : ''}${ai.decision && _cvDecision[ai.decision] ? `<span class="pill" style="background:${_cvDecision[ai.decision][1]}1a;color:${_cvDecision[ai.decision][1]};font-size:9.5px">${_cvDecision[ai.decision][0]}</span>` : ''}</div></div></div>
    ${ai.aiGenerated ? (() => { const v = ai.aiGenerated.verdict; const col = v === 'ai' ? '#e2515f' : v === 'mixed' ? '#e0a020' : '#16a26a'; const lbl = v === 'ai' ? 'Πιθανό AI-generated' : v === 'mixed' ? 'Μερικώς AI' : 'Γραμμένο από άνθρωπο'; return `<div style="margin-bottom:9px;padding:8px 11px;border-radius:9px;background:${col}12;border-left:3px solid ${col}"><b style="font-size:12px;color:${col}">🤖 ${lbl}${ai.aiGenerated.confidence ? ' · ' + ai.aiGenerated.confidence + '%' : ''}</b>${ai.aiGenerated.reason ? `<div class="mut" style="font-size:11.5px;margin-top:2px">${esc(ai.aiGenerated.reason)}</div>` : ''}</div>`; })() : ''}
    <p style="font-size:13px;line-height:1.55">${esc(ai.summary || '')}</p>
    ${ai.strengths && ai.strengths.length ? `<div style="margin-top:8px"><b style="font-size:12px;color:var(--ok)">✔ Δυνατά σημεία</b><ul style="margin:4px 0 0;font-size:12.5px;padding-left:20px">${ai.strengths.map(s => `<li>${esc(s)}</li>`).join('')}</ul></div>` : ''}
    ${ai.concerns && ai.concerns.length ? `<div style="margin-top:7px"><b style="font-size:12px;color:var(--warn)">⚠ Σημεία προσοχής</b><ul style="margin:4px 0 0;font-size:12.5px;padding-left:20px">${ai.concerns.map(s => `<li>${esc(s)}</li>`).join('')}</ul></div>` : ''}
    ${ai.skills && ai.skills.length ? `<div style="margin-top:8px;display:flex;gap:4px;flex-wrap:wrap">${ai.skills.map(s => `<span class="pill" style="font-size:9px">${esc(s)}</span>`).join('')}</div>` : ''}
    ${ai.interviewQuestions && ai.interviewQuestions.length ? `<div style="margin-top:9px"><b style="font-size:12px">💬 Ερωτήσεις συνέντευξης</b><ol style="margin:4px 0 0;font-size:12.5px;padding-left:20px">${ai.interviewQuestions.map(s => `<li style="margin-bottom:3px">${esc(s)}</li>`).join('')}</ol></div>` : ''}`;
  const models = window._cvModels || {}; const defModel = d.aiModel || window._cvDefaultModel || Object.keys(models)[0] || '';
  const cvAvaBig = d.photo
    ? `<img src="api.php?a=cv_photo&id=${id}" style="width:42px;height:42px;border-radius:50%;object-fit:cover;flex:none;border:1px solid var(--line)">`
    : `<span class="ava" style="width:42px;height:42px;font-size:15px;flex:none">${esc((d.name || '?').trim().split(/\s+/).map(w => w[0] || '').slice(0, 2).join('').toUpperCase())}</span>`;
  dr.innerHTML = `
  <div class="drawer-h" style="display:flex;align-items:center;gap:11px">${cvAvaBig}<h2 style="font-size:17px;flex:1">${esc(d.name)}</h2><button class="drawer-x" id="dX">✕</button></div>
  <div class="drawer-b">
    <div class="mut" style="font-size:12.5px;margin-bottom:12px">${esc(d.jobTitle || '—')} · υποβλήθηκε ${d.appliedAt ? _cvDate(d.appliedAt) : '—'}${d.source === 'form' ? ' · φόρμα CloudOn' : d.source === 'manual' ? ' · χειροκίνητα' : ''}</div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px">
      ${d.email ? `<a class="btn btn-o btn-sm" href="mailto:${esc(d.email)}">${I.mail} ${esc(d.email)}</a>` : ''}
      ${d.phone ? `<a class="btn btn-o btn-sm" href="tel:${esc(d.phone)}">${I.phone} ${esc(d.phone)}</a>` : ''}
    </div>
    ${(d.others && d.others.length) ? `<div style="margin-bottom:14px;padding:9px 12px;border-radius:10px;background:#8291a912;border-left:3px solid #8291a9">
      <b style="font-size:12px">⧉ Άλλες αιτήσεις του ίδιου ατόμου (${d.others.length}) — ίδιο email</b>
      ${d.others.map(o => `<div style="display:flex;gap:8px;align-items:center;font-size:12px;padding:4px 0;cursor:pointer" data-otherid="${o.id}"><span class="mut">→</span><b>${esc(o.name || '—')}</b><span class="mut">· ${esc(o.jobTitle || '—')}${o.appliedAt ? ' · ' + _cvDate(o.appliedAt) : ''}${o.aiScore !== null ? ' · score ' + o.aiScore : ''}</span><span class="pill" style="font-size:8.5px;margin-left:auto">${esc((window._cvStatuses || {})[o.status] || o.status)}</span></div>`).join('')}
      ${(() => { const names = [d.name].concat(d.others.map(o => o.name)); const uniq = [...new Set(names.map(n => (n || '').toLowerCase().replace(/\s+/g, ' ').trim()))]; return uniq.length > 1 ? `<div class="mut" style="font-size:10.5px;margin-top:5px">ℹ️ Διαφορετική γραφή ονόματος (ελληνικά/λατινικά, υποκοριστικό κ.λπ.) — πιθανώς το ίδιο άτομο. Επιβεβαίωσε.</div>` : ''; })()}
    </div>` : ''}
    <div class="card"><div class="card-h">${I.mail} Επικοινωνία & προγραμματισμός</div><div class="card-b" id="cvCommsBox"></div></div>
    <div class="card"><div class="card-h" style="flex-wrap:wrap;gap:6px">${I.brain || I.bulb} AI co-pilot
      <select class="inp" id="cvModel" style="width:auto;font-size:11.5px;padding:4px 8px;margin-left:auto">${Object.entries(models).map(([k, l]) => `<option value="${k}" ${k === defModel ? 'selected' : ''}>${esc(l)}</option>`).join('')}</select>
      <button class="btn btn-p btn-sm" id="cvAiBtn">✨ ${d.ai ? 'Επαναξιολόγηση' : 'Αξιολόγηση'}</button></div>
      <div class="card-b" id="cvAiBox">${renderAi(d.ai)}</div></div>
    <div class="card"><div class="card-h">${I.doc} Βιογραφικό</div><div class="card-b">
      ${d.hasCv ? `<div style="display:flex;gap:8px;margin-bottom:10px"><a class="btn btn-o btn-sm" href="api.php?a=cv_file&id=${id}" target="_blank">${I.search} Άνοιγμα</a><a class="btn btn-o btn-sm" href="api.php?a=cv_file&id=${id}&dl=1">${I.download} Λήψη</a></div>
        ${(d.cvMime === 'application/pdf') ? `<iframe src="api.php?a=cv_file&id=${id}" style="width:100%;height:520px;border:1px solid var(--line);border-radius:10px"></iframe>` : `<div class="mut" style="font-size:12px">${esc(d.cvName || 'αρχείο')} — προεπισκόπηση μη διαθέσιμη, κατέβασέ το.</div>`}` : '<div class="mut" style="font-size:12px">Χωρίς συνημμένο CV.</div>'}
      ${d.letter ? `<div style="margin-top:12px"><b style="font-size:12px">Συνοδευτική επιστολή</b><div class="mut" style="font-size:12.5px;white-space:pre-wrap;margin-top:4px">${esc(d.letter)}</div></div>` : ''}
    </div></div>
    <div class="card"><div class="card-h">${I.download} Συνημμένα <span class="mut" style="font-weight:400;font-size:11px;margin-left:8px">αρχεία & βίντεο (π.χ. καταγραφή συνέντευξης, portfolio)</span></div><div class="card-b" id="cvFilesBox"></div></div>
    <div class="card"><div class="card-h">${I.chat || I.users} Συνέντευξη <span class="mut" style="font-weight:400;font-size:11px;margin-left:8px">χαρακτήρας + επαλήθευση γνώσεων</span></div><div class="card-b" id="cvIvBox"></div></div>
    <div class="card"><div class="card-h">${I.checkSquare} Αξιολόγηση & κατάσταση</div><div class="card-b">
      <label class="lbl">Στάδιο</label>
      <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px" id="cvStatusBtns">
        ${Object.entries(statuses).map(([k, l]) => `<button class="btn btn-sm ${d.status === k ? 'btn-p' : 'btn-o'}" data-cvst="${k}">${l}</button>`).join('')}</div>
      <label class="lbl">Βαθμολογία (δική σου)</label>
      <div style="font-size:22px;color:#e0a020;cursor:pointer;margin-bottom:12px" id="cvStars">${[1, 2, 3, 4, 5].map(n => `<span data-star="${n}">${n <= d.rating ? '★' : '☆'}</span>`).join('')}</div>
      <label class="lbl">Υπεύθυνος</label>
      <select class="inp" id="cvAssignee" style="margin-bottom:12px"><option value="">— κανείς —</option>
        ${S.boot.admins.map(a => `<option value="${a.id}" ${d.assignee == a.id ? 'selected' : ''}>${esc(a.name)}</option>`).join('')}</select>
      <label class="lbl">Σημειώσεις</label>
      <textarea class="inp" id="cvNotes" rows="3" placeholder="σχόλια για τον υποψήφιο…">${esc((d.notes || '').replace(/<br\s*\/?>/gi, '\n').replace(/<[^>]+>/g, ''))}</textarea>
      <div style="text-align:right;margin-top:8px"><button class="btn btn-o btn-sm" id="cvNotesSave">${I.save} Αποθήκευση σημειώσεων</button></div>
    </div></div>
  </div>`;
  $('#dX', dr).onclick = () => cnpAskClose(dr);
  cnpAttachments($('#cvFilesBox', dr), {module: 'cv', refType: 'cv', refId: id});
  $$('[data-otherid]', dr).forEach(b => b.onclick = () => openCv(+b.dataset.otherid));
  $('#cvAiBtn', dr).onclick = async () => {
    const btn = $('#cvAiBtn', dr); btn.disabled = true; btn.textContent = '✨ Ανάλυση…';
    const r = await api('cv_ai', {id, model: $('#cvModel', dr).value}).catch(e => ({err: e.message}));
    btn.disabled = false;
    if (r.err) { toast(r.err, true); btn.textContent = '✨ Αξιολόγηση'; return; }
    d.ai = r.ai; d.aiScore = r.score; d.aiModel = r.model;
    $('#cvAiBox', dr).innerHTML = renderAi(r.ai); btn.textContent = '✨ Επαναξιολόγηση'; toast('Έτοιμη η αξιολόγηση ✓');
  };
  $$('[data-cvst]', dr).forEach(b => b.onclick = async () => {
    await api('cv_update', {id, status: b.dataset.cvst});
    $$('[data-cvst]', dr).forEach(x => { x.classList.toggle('btn-p', x === b); x.classList.toggle('btn-o', x !== b); });
    toast('Ενημερώθηκε');
  });
  $$('[data-star]', dr).forEach(s => s.onclick = async () => {
    const n = +s.dataset.star; await api('cv_update', {id, rating: n});
    $$('[data-star]', dr).forEach(x => x.textContent = +x.dataset.star <= n ? '★' : '☆'); toast('Βαθμολογήθηκε');
  });
  $('#cvAssignee', dr).onchange = async () => { await api('cv_update', {id, assignee: +$('#cvAssignee', dr).value || 0}); toast('Ανατέθηκε'); };
  $('#cvNotesSave', dr).onclick = async () => { await api('cv_update', {id, notes: $('#cvNotes', dr).value.replace(/\n/g, '<br>')}); toast('Αποθηκεύτηκε ✓'); };

  // ── Συνέντευξη ──
  const catIco = {Γνώσεις: '🧠', Χαρακτήρας: '🎭', Εμπειρία: '💼', Κίνητρα: '🎯'};
  function renderIvEval(ev) {
    if (!ev) { return ''; }
    const kv = ({verified: ['Επαληθεύτηκαν', '#16a26a'], partial: ['Μερικώς', '#e0a020'], not: ['Δεν επαληθεύτηκαν', '#e2515f'], unclear: ['Ασαφές', '#8291a9']})[ev.knowledgeVerified] || ['—', '#8291a9'];
    const rec = ({proceed: ['Προχώρα', '#16a26a'], hold: ['Αναμονή', '#e0a020'], reject: ['Απόρριψη', '#e2515f']})[ev.recommendation] || ['—', '#8291a9'];
    return `<div style="border-top:1px solid var(--line);padding-top:11px;margin-top:4px">
      <div style="display:flex;gap:9px;align-items:center;flex-wrap:wrap;margin-bottom:8px">
        <span style="font-weight:800;font-size:16px;color:${_cvScoreCol(ev.score ?? null)}">${ev.score ?? '—'}/100</span>
        <span class="pill" style="background:${kv[1]}1a;color:${kv[1]};font-size:9.5px">Γνώσεις: ${kv[0]}</span>
        <span class="pill" style="background:${rec[1]}1a;color:${rec[1]};font-size:9.5px">${rec[0]}</span></div>
      ${ev.character ? `<div style="font-size:12.5px;margin-bottom:6px"><b>Χαρακτήρας:</b> ${esc(ev.character)}</div>` : ''}
      ${ev.knowledgeNote ? `<div class="mut" style="font-size:12px;margin-bottom:6px">${esc(ev.knowledgeNote)}</div>` : ''}
      ${ev.strengths && ev.strengths.length ? `<div style="font-size:12px"><b style="color:var(--ok)">Δυνατά:</b> ${ev.strengths.map(esc).join(' · ')}</div>` : ''}
      ${ev.redFlags && ev.redFlags.length ? `<div style="font-size:12px;margin-top:3px"><b style="color:var(--bad)">Red flags:</b> ${ev.redFlags.map(esc).join(' · ')}</div>` : ''}
      ${ev.summary ? `<p style="font-size:12.5px;margin-top:6px">${esc(ev.summary)}</p>` : ''}</div>`;
  }
  async function ivGenerate() {
    const box = $('#cvIvBox', dr); const btn = $('#ivKit', box); if (btn) { btn.disabled = true; btn.textContent = '✨ Δημιουργία…'; }
    const r = await api('cv_interview_kit', {id, regen: (d.interview && d.interview.questions) ? 1 : 0}).catch(e => ({err: e.message}));
    if (r.err) { toast(r.err, true); if (btn) { btn.disabled = false; btn.textContent = '✨ Δημιουργία ερωτήσεων'; } return; }
    d.interview = r.kit; renderInterview(); toast('Ερωτήσεις έτοιμες ✓');
  }
  function renderInterview() {
    const box = $('#cvIvBox', dr); if (!box) { return; }
    const iv = d.interview; const ev = d.interviewEval; const models = window._cvModels || {};
    const kitBtn = `<button class="btn btn-o btn-sm" id="ivKit">${iv && iv.questions ? '↻ Νέες ερωτήσεις' : '✨ Δημιουργία ερωτήσεων'}</button>`;
    if (!iv || !iv.questions || !iv.questions.length) {
      box.innerHTML = `<p class="mut" style="font-size:12.5px;margin:0 0 10px">Ο AI δημιουργεί στοχευμένες ερωτήσεις (χαρακτήρα + επαλήθευσης γνώσεων) βάσει του CV. Κατέγραψε τις απαντήσεις και αξιολόγησέ τες.</p>${kitBtn}`;
      $('#ivKit', box).onclick = ivGenerate; return;
    }
    const cats = {}; iv.questions.forEach(q => { const cat = q.category || 'Άλλο'; (cats[cat] = cats[cat] || []).push(q); });
    const ans = iv.answers || {};
    const ratings = {}; iv.questions.forEach(q => { ratings[q.id] = (ans[q.id] && ans[q.id].rating) || 0; });
    box.innerHTML = `<div style="display:flex;gap:8px;align-items:center;margin-bottom:10px">${kitBtn}<span class="mut" style="font-size:11px">${iv.questions.length} ερωτήσεις</span></div>
      ${Object.entries(cats).map(([cat, qs]) => `<div style="margin-bottom:10px"><b style="font-size:13px;color:var(--ink)">${catIco[cat] || ''} ${esc(cat)}</b>
        ${qs.map(q => `<div style="margin:8px 0 11px">
          <div style="font-size:12.5px;font-weight:600">${esc(q.q)}</div>
          ${q.purpose ? `<div class="mut" style="font-size:10.5px;margin-bottom:4px">↳ ${esc(q.purpose)}</div>` : ''}
          <textarea class="inp iv-ans" data-q="${q.id}" rows="2" style="font-size:12.5px" placeholder="Τι απάντησε ο υποψήφιος…">${esc((ans[q.id] && ans[q.id].text) || '')}</textarea>
          <div style="margin-top:3px;color:#e0a020;cursor:pointer;font-size:15px" data-ivstars="${q.id}">${[1, 2, 3, 4, 5].map(n => `<span data-s="${n}">${(n <= ratings[q.id]) ? '★' : '☆'}</span>`).join('')}</div>
        </div>`).join('')}</div>`).join('')}
      <label class="lbl">Γενικές σημειώσεις συνέντευξης</label>
      <textarea class="inp" id="ivNotes" rows="2" style="font-size:12.5px">${esc(iv.notes || '')}</textarea>
      <div style="display:flex;gap:8px;margin-top:11px;flex-wrap:wrap;align-items:center">
        <button class="btn btn-o btn-sm" id="ivSave">${I.save} Αποθήκευση</button>
        <select class="inp" id="ivModel" style="width:auto;font-size:11.5px;padding:4px 8px;margin-left:auto">${Object.entries(models).map(([k, l]) => `<option value="${k}">${esc(l)}</option>`).join('')}</select>
        <button class="btn btn-p btn-sm" id="ivEval">✨ Αξιολόγηση συνέντευξης</button></div>
      <div id="ivEvalBox" style="margin-top:12px">${renderIvEval(ev)}</div>`;
    $$('[data-ivstars]', box).forEach(row => { const qid = row.dataset.ivstars; row.querySelectorAll('[data-s]').forEach(s => s.onclick = () => { ratings[qid] = +s.dataset.s; row.querySelectorAll('[data-s]').forEach(x => x.textContent = +x.dataset.s <= ratings[qid] ? '★' : '☆'); }); });
    const collect = () => { const a = {}; $$('.iv-ans', box).forEach(t => { const qid = t.dataset.q; a[qid] = {text: t.value, rating: ratings[qid] || 0}; }); return a; };
    $('#ivKit', box).onclick = ivGenerate;
    $('#ivSave', box).onclick = async () => { const answers = collect(); const notes = $('#ivNotes', box).value; await api('cv_interview_save', {id, answers, notes}); d.interview.answers = answers; d.interview.notes = notes; toast('Αποθηκεύτηκε ✓'); };
    $('#ivEval', box).onclick = async () => {
      const answers = collect(); const notes = $('#ivNotes', box).value;
      await api('cv_interview_save', {id, answers, notes}); d.interview.answers = answers; d.interview.notes = notes;
      const btn = $('#ivEval', box); btn.disabled = true; btn.textContent = '✨ Ανάλυση…';
      const r = await api('cv_interview_eval', {id, model: $('#ivModel', box).value}).catch(e => ({err: e.message}));
      btn.disabled = false; btn.textContent = '✨ Αξιολόγηση συνέντευξης';
      if (r.err) { toast(r.err, true); return; }
      d.interviewEval = r.eval; $('#ivEvalBox', box).innerHTML = renderIvEval(r.eval); toast('Έτοιμη η αξιολόγηση ✓');
    };
  }
  renderInterview();

  // ── Επικοινωνία & προγραμματισμός ──
  function renderComms() {
    const box = $('#cvCommsBox', dr); if (!box) { return; }
    const company = 'CloudOn';
    const first = ((d.name || '').trim().split(/\s+/)[0]) || d.name || '';
    const templates = {
      invite: {s: 'Πρόσκληση για συνέντευξη — ' + d.jobTitle, b: 'Αγαπητέ/ή ' + first + ',\n\nΣας ευχαριστούμε για το ενδιαφέρον σας για τη θέση «' + d.jobTitle + '». Θα θέλαμε να σας καλέσουμε σε συνέντευξη.\n\nΗμερομηνία & ώρα: [συμπλήρωσε]\nΤρόπος: [δια ζώσης / τηλεδιάσκεψη]\n\nΠαρακαλούμε επιβεβαιώστε τη διαθεσιμότητά σας.\n\nΜε εκτίμηση,\nΟμάδα ' + company},
      reject: {s: 'Ενημέρωση για την αίτησή σας — ' + d.jobTitle, b: 'Αγαπητέ/ή ' + first + ',\n\nΣας ευχαριστούμε θερμά για το ενδιαφέρον σας και τον χρόνο που αφιερώσατε. Μετά από προσεκτική αξιολόγηση, αποφασίσαμε να προχωρήσουμε με άλλους υποψηφίους για τη θέση «' + d.jobTitle + '».\n\nΘα διατηρήσουμε το βιογραφικό σας για μελλοντικές ευκαιρίες που ταιριάζουν στο προφίλ σας.\n\nΣας ευχόμαστε κάθε επιτυχία.\n\nΜε εκτίμηση,\nΟμάδα ' + company},
      info: {s: 'Αίτημα για επιπλέον στοιχεία — ' + d.jobTitle, b: 'Αγαπητέ/ή ' + first + ',\n\nΣχετικά με την αίτησή σας για τη θέση «' + d.jobTitle + '», θα θέλαμε κάποιες επιπλέον πληροφορίες:\n\n- [ερώτηση 1]\n- [ερώτηση 2]\n\nΣας ευχαριστούμε.\n\nΜε εκτίμηση,\nΟμάδα ' + company},
    };
    box.innerHTML = `
      <label class="lbl">📅 Προγραμματισμός συνέντευξης</label>
      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <input class="inp" type="datetime-local" id="cvWhen" value="${d.interviewAt ? d.interviewAt.replace(' ', 'T').slice(0, 16) : ''}" style="width:auto">
        <button class="btn btn-p btn-sm" id="cvSched">Όρισε & ειδοποίησε</button>
        ${d.interviewAt ? `<span class="pill pill-info">Ορισμένη: ${_cvDate(d.interviewAt)} ${esc(d.interviewAt.slice(11, 16))}</span>` : ''}
      </div>
      <div style="border-top:1px solid var(--line);margin:14px 0 10px"></div>
      <label class="lbl">✉️ Email προς υποψήφιο ${d.email ? '' : '<span style="color:var(--bad)">— χωρίς email</span>'}</label>
      <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:8px">
        <button class="btn btn-o btn-sm" data-tpl="invite">Πρόσκληση συνέντευξης</button>
        <button class="btn btn-o btn-sm" data-tpl="reject">Ευγενική απόρριψη</button>
        <button class="btn btn-o btn-sm" data-tpl="info">Αίτημα στοιχείων</button>
      </div>
      <input class="inp" id="cvEmSubj" placeholder="Θέμα email" style="margin-bottom:7px">
      <textarea class="inp" id="cvEmBody" rows="7" placeholder="Κείμενο email…" style="font-size:12.5px"></textarea>
      <div style="text-align:right;margin-top:8px"><button class="btn btn-p btn-sm" id="cvEmSend" ${d.email ? '' : 'disabled'}>${I.mail} Αποστολή email</button></div>
      ${(d.comms && d.comms.length) ? `<div style="border-top:1px solid var(--line);margin-top:12px;padding-top:10px"><b style="font-size:12px">Ιστορικό επικοινωνίας</b>
        ${d.comms.map(cm => `<div style="font-size:11.5px;padding:5px 0;border-bottom:1px dashed var(--line)"><b>${cm.kind === 'email' ? '✉️' : cm.kind === 'interview' ? '📅' : '📝'} ${esc(cm.subject)}</b> <span class="mut">· ${esc(cm.by || '')} · ${cm.at ? _cvDate(cm.at) : ''}</span></div>`).join('')}</div>` : ''}`;
    $('#cvSched', box).onclick = async () => {
      const w = $('#cvWhen', box).value; if (!w) { toast('Διάλεξε ημ/ώρα', true); return; }
      await api('cv_schedule', {id, when: w}); toast('Ορίστηκε ✓ — ειδοποιήθηκαν οι υπεύθυνοι'); openCv(id);
    };
    box.querySelectorAll('[data-tpl]').forEach(b => b.onclick = () => { const t = templates[b.dataset.tpl]; $('#cvEmSubj', box).value = t.s; $('#cvEmBody', box).value = t.b; });
    $('#cvEmSend', box).onclick = async () => {
      const subject = $('#cvEmSubj', box).value.trim(), body = $('#cvEmBody', box).value.trim();
      if (!subject || !body) { toast('Θέμα & κείμενο', true); return; }
      const btn = $('#cvEmSend', box); btn.disabled = true;
      const r = await api('cv_email', {id, subject, body}).catch(e => ({err: e.message}));
      btn.disabled = false;
      if (r.err) { toast(r.err, true); return; }
      toast(r.sent ? 'Το email στάλθηκε ✓' : 'Καταγράφηκε (η αποστολή απέτυχε)', !r.sent); openCv(id);
    };
  }
  renderComms();
}
