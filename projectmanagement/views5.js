/* ═══════════ CloudOn Projects — Gantt (GoodDay-style δομή) ═══════════ */
'use strict';
const {S, api, esc, fmtMin, fmtEur, suStat, dShort, dFull, today, toast, setTop, openTask, adminIni, adminName, cnpPrompt, cnpConfirm, cnpDialog, closeDrawer, I, go, $, $$} = window.CNP;
const R = window.R;

const DAY = 86400000;
const CELL = 34;
const LEFT = 470;        // desktop: title 280 + duration 70 + start/end 120
const LEFT_MOB = 132;    // κινητό: μόνο τίτλος (470px δεν χωράει σε οθόνη 390 — έμενε αόρατο το χρονοδιάγραμμα)
const isMob = () => matchMedia('(max-width:768px)').matches;
const iso = d => d.toISOString().slice(0, 10);
const addD = (s, n) => iso(new Date(new Date(s + 'T12:00:00').getTime() + n * DAY));
const diffD = (a, b) => Math.round((new Date(b + 'T12:00:00') - new Date(a + 'T12:00:00')) / DAY);

R.gantt = async function () {
  setTop('Gantt', 'Χρονοδιάγραμμα projects & διαθεσιμότητα ομάδας');
  const c = $('#content');
  const st = R.gantt._st = R.gantt._st || {from: addD(today(), -7), weeks: 6, mode: 'project', open: {}};
  const MOB = isMob();
  const LEFTW = MOB ? LEFT_MOB : LEFT;
  /** Οι δύο βοηθητικές στήλες — στο κινητό δεν αποδίδονται καθόλου (χώρος για το χρονοδιάγραμμα). */
  const cols = (a, b) => MOB ? '' : `<div class="g-col">${a || ''}</div><div class="g-col g-col2">${b || ''}</div>`;
  c.innerHTML = '<div class="skel" style="height:420px"></div>';
  const to = addD(st.from, st.weeks * 7);
  const d = await api(`gantt&from=${st.from}&to=${to}`);
  const days = [];
  for (let x = d.from; x <= d.to; x = addD(x, 1)) days.push(x);
  const nDays = days.length;
  const mn = ['ΙΑΝΟΥΑΡΙΟΣ', 'ΦΕΒΡΟΥΑΡΙΟΣ', 'ΜΑΡΤΙΟΣ', 'ΑΠΡΙΛΙΟΣ', 'ΜΑΪΟΣ', 'ΙΟΥΝΙΟΣ', 'ΙΟΥΛΙΟΣ', 'ΑΥΓΟΥΣΤΟΣ', 'ΣΕΠΤΕΜΒΡΙΟΣ', 'ΟΚΤΩΒΡΙΟΣ', 'ΝΟΕΜΒΡΙΟΣ', 'ΔΕΚΕΜΒΡΙΟΣ'];
  const isWknd = x => { const n = new Date(x + 'T12:00:00').getDay(); return n === 0 || n === 6; };

  // headers
  let monthHtml = [], curM = -1, span = 0;
  days.forEach(x => { const m = +x.slice(5, 7) + (+x.slice(0, 4)) * 100;
    if (m !== curM) { if (span) monthHtml.push([curM, span]); curM = m; span = 1; } else span++; });
  if (span) monthHtml.push([curM, span]);
  const monthCells = monthHtml.map(([m, s]) =>
    `<div style="width:${s * CELL}px" class="g-month">${mn[(m % 100) - 1]} ${Math.floor(m / 100)}</div>`).join('');
  const dayHead = days.map(x => `<div class="g-day ${isWknd(x) ? 'wknd' : ''} ${x === today() ? 'today' : ''}">${+x.slice(8)}</div>`).join('');
  const bgCells = days.map(x => `<div class="g-bg ${isWknd(x) ? 'wknd' : ''} ${x === today() ? 'today' : ''}"></div>`).join('');

  const barPos = (s0, e0) => {
    let s = Math.max(0, diffD(d.from, s0));
    let w = Math.min(nDays - s, diffD(s0 < d.from ? d.from : s0, e0) + 1);
    if (w < 1) w = 1;
    return [s * CELL + 2, w * CELL - 4];
  };
  const fmtDates = (s, e) => `${dShort(s)} – ${dShort(e)}`;
  const dur = (s, e) => (diffD(s, e) + 1) + 'ημ';

  let rowsHtml = '';

  if (st.mode === 'project') {
    /* ── GoodDay-style: δέντρο projects ── */
    const tasksBy = {};
    d.tasks.forEach(t => (tasksBy[t.project] = tasksBy[t.project] || []).push(t));
    const kids = {};
    const roots = [];
    d.projects.forEach(p => { if (p.parent && d.projects.find(x => x.id === p.parent)) {
        (kids[p.parent] = kids[p.parent] || []).push(p);
      } else roots.push(p); });
    const span2 = list => {
      if (!list.length) return null;
      let s = list[0].start, e = list[0].end;
      list.forEach(t => { if (t.start < s) s = t.start; if (t.end > e) e = t.end; });
      return [s, e];
    };
    const allTasksOf = p => (tasksBy[p.id] || []).concat(...(kids[p.id] || []).map(k => tasksBy[k.id] || []));
    const projRow = (p, depth) => {
      const own = allTasksOf(p);
      const sp = span2(own);
      const isOpen = !!st.open[p.id];
      let bar = '';
      if (sp) {
        const [l, w] = barPos(sp[0], sp[1]);
        bar = `<div class="g-pbar" style="left:${l}px;width:${w}px"></div>
          <div class="g-after" style="left:${l + w + 8}px">
            <span class="ava" style="width:19px;height:19px;font-size:8.5px;background:${p.color}">${esc(p.name.slice(0, 2).toUpperCase())}</span>
            ${esc(p.name)}${p.client ? ` <span class="mut">· ${esc(p.client)}</span>` : ''}</div>`;
      }
      return `<div class="g-hrow g-projrow" data-toggle="${p.id}">
        <div class="g-label" style="padding-left:${12 + depth * 20}px">
          <span class="g-arrow ${isOpen ? 'open' : ''}">▸</span>
          <span class="dot" style="background:${p.color}"></span>
          <b style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(p.name)}</b>
          <span class="mut" style="margin-left:auto;font-size:10px">${own.length}</span></div>
        ${cols(sp ? dur(sp[0], sp[1]) : '', sp ? fmtDates(sp[0], sp[1]) : '')}
        <div class="g-cells" style="width:${nDays * CELL}px">${bgCells}${bar}</div></div>` +
        (isOpen ? (tasksBy[p.id] || []).map(t => taskRow(t, depth + 1)).join('')
          + (kids[p.id] || []).map(k => projRow(k, depth + 1)).join('') : '');
    };
    const taskRow = (t, depth) => {
      const [l, w] = barPos(t.start, t.end);
      return `<div class="g-hrow g-taskrow">
        <div class="g-label g-tlabel" data-open="${t.id}" style="padding-left:${26 + depth * 20}px">
          <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(t.title)}</span></div>
        ${cols(dur(t.start, t.end), fmtDates(t.start, t.end))}
        <div class="g-cells" style="width:${nDays * CELL}px">${bgCells}
          <div class="g-bar ${t.prio === 2 ? 'crit' : t.prio === 1 ? 'high' : ''}" data-task="${t.id}"
            data-start="${t.start}" data-end="${t.end}"
            style="left:${l}px;width:${w}px;background:${t.color}"><span class="g-handle"></span></div>
          <div class="g-after" style="left:${l + w + 8}px">
            ${t.assignee ? `<span class="ava" style="width:19px;height:19px;font-size:8.5px">${esc(adminIni(t.assignee))}</span>` : ''}
            ${t.blocked ? "⛓ " : ""}${esc(t.title)}${t.est ? ` <span class="mut">~${fmtMin(t.est)}</span>` : ""}</div>
        </div></div>`;
    };
    // πρώτη φορά με λίγα projects → άνοιξέ τα όλα
    if (!st._init) {
      st._init = 1;
      if (d.projects.length <= 4) d.projects.forEach(p => st.open[p.id] = true);
    }
    // 👥 ζώνη χωρητικότητας ομάδας ΠΑΝΩ από το δέντρο (η «καλύτερη λογική»: capacity + timeline μαζί)
    const loadCls2 = m => !m ? '' : m <= 240 ? 'g-free' : m <= 480 ? 'g-ok' : 'g-over';
    const capacity = Object.keys(d.load).length ? `
      <div class="g-hrow g-agent"><div class="g-label"><b>👥 Χωρητικότητα ομάδας</b></div>
        ${cols()}
        <div class="g-cells" style="width:${nDays * CELL}px">${bgCells}</div></div>` +
      Object.entries(d.load).map(([aid, ld]) => `
        <div class="g-hrow" style="height:26px"><div class="g-label" style="padding-left:26px;font-size:11.5px">
            <span class="ava" style="width:18px;height:18px;font-size:8px">${esc(adminIni(+aid))}</span>${esc(adminName(+aid))}</div>
          ${cols()}
          <div class="g-cells" style="width:${nDays * CELL}px">
            ${days.map(x => (d.leaves && d.leaves[aid] && d.leaves[aid][x])
              ? `<div class="g-load g-leave" style="height:25px" title="${esc(adminName(+aid))} · ${dShort(x)} · 🌴 Άδεια">🌴</div>`
              : `<div class="g-load ${isWknd(x) ? 'wknd' : ''} ${loadCls2(ld[x] || 0)}" style="height:25px"
              title="${esc(adminName(+aid))} · ${dShort(x)} · ${fmtMin(Math.round(ld[x] || 0))}"></div>`).join('')}</div></div>`).join('') : '';
    rowsHtml = capacity + (roots.map(p => projRow(p, 0)).join('') ||
      '<div class="empty" style="padding:40px">Κανένα προγραμματισμένο task — βάλε ημερομηνίες στα tasks</div>');
  } else {
    /* ── Διαθεσιμότητα: ανά χειριστή με ζώνη φόρτου ── */
    const byA = {};
    d.tasks.forEach(t => (byA[t.assignee || 0] = byA[t.assignee || 0] || []).push(t));
    const loadCls = m => !m ? '' : m <= 240 ? 'g-free' : m <= 480 ? 'g-ok' : 'g-over';
    rowsHtml = Object.keys(byA).sort((a, b) => (+a === 0) - (+b === 0)).map(aid => {
      const name = +aid ? adminName(+aid) : 'Χωρίς ανάθεση';
      const ld = d.load[aid] || {};
      return `<div class="g-hrow g-agent">
        <div class="g-label"><span class="ava">${+aid ? esc(adminIni(+aid)) : '—'}</span><b>${esc(name)}</b>
          <span class="mut" style="margin-left:auto;font-size:10px">${byA[aid].length}</span></div>
        ${cols()}
        <div class="g-cells" style="width:${nDays * CELL}px">
          ${days.map(x => (d.leaves && d.leaves[aid] && d.leaves[aid][x])
            ? `<div class="g-load g-leave" title="${esc(name)} · ${dShort(x)} · 🌴 Άδεια">🌴</div>`
            : `<div class="g-load ${isWknd(x) ? 'wknd' : ''} ${loadCls(ld[x] || 0)}"
            title="${esc(name)} · ${dShort(x)} · ${fmtMin(Math.round(ld[x] || 0))}"></div>`).join('')}</div></div>` +
        byA[aid].map(t => {
          const [l, w] = barPos(t.start, t.end);
          return `<div class="g-hrow g-taskrow">
            <div class="g-label g-tlabel" data-open="${t.id}" style="padding-left:30px">
              <span class="dot" style="background:${t.color}"></span>
              <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(t.title)}</span></div>
            ${cols(dur(t.start, t.end), fmtDates(t.start, t.end))}
            <div class="g-cells" style="width:${nDays * CELL}px">${bgCells}
              <div class="g-bar ${t.prio === 2 ? 'crit' : t.prio === 1 ? 'high' : ''}" data-task="${t.id}"
                data-start="${t.start}" data-end="${t.end}"
                style="left:${l}px;width:${w}px;background:${t.color}"><span class="g-handle"></span></div>
              <div class="g-after" style="left:${l + w + 8}px">${esc(t.pname)}</div>
            </div></div>`;
        }).join('');
    }).join('') || '<div class="empty" style="padding:40px">Τίποτα προγραμματισμένο</div>';
  }

  // Κενό χρονοδιάγραμμα → κανονική κάρτα ΕΚΤΟΣ του scroller (μέσα του θα ήταν κεντραρισμένο
  // σε πλάτος ~1900px, δηλαδή αόρατο στο κινητό — έμοιαζε «χαλασμένο»).
  const nothing = !d.tasks.length;

  c.innerHTML = `
  <div class="g-toolbar" style="display:flex;gap:8px;margin-bottom:12px;align-items:center;flex-wrap:wrap">
    <div style="display:flex;background:#8595ac22;border-radius:10px;padding:3px">
      <button class="btn btn-sm ${st.mode === 'project' ? 'btn-p' : ''}" id="gMp" style="box-shadow:none">📁 Projects</button>
      <button class="btn btn-sm ${st.mode === 'people' ? 'btn-p' : ''}" id="gMa" style="box-shadow:none">👥 Διαθεσιμότητα</button>
    </div>
    <div style="display:flex;gap:6px;align-items:center">
      <button class="btn btn-o btn-sm" id="gPrev">←</button>
      <button class="btn btn-o btn-sm" id="gToday">Σήμερα</button>
      <button class="btn btn-o btn-sm" id="gNext">→</button>
    </div>
    ${st.mode === 'project' ? '<button class="btn btn-o btn-sm" id="gAll">↕ Άνοιγμα όλων</button>' : `
      <span class="mut" style="font-size:11.5px"><span class="dot" style="background:#16a26a"></span>≤4ω
      <span class="dot" style="background:#eba63c"></span>≤8ω <span class="dot" style="background:#e2515f"></span>>8ω</span>`}
    <span class="mut g-hint" style="margin-left:auto;font-size:11.5px">Σύρε μπάρα = μετακίνηση · άκρη = διάρκεια · κλικ = άνοιγμα</span>
  </div>
  ${nothing ? `<div class="card"><div class="empty" style="padding:44px 22px">
      <div class="big">${I.gantt}</div>
      <b style="color:var(--ink);font-size:15px">Κανένα task στο χρονοδιάγραμμα</b>
      <div class="mut" style="font-size:12.5px;margin-top:6px;max-width:420px;margin-inline:auto;line-height:1.6">
        Στο Gantt εμφανίζονται μόνο τα ανοιχτά tasks που έχουν <b>ημερομηνία</b> — έναρξη, πλάνο ή προθεσμία.
        Βάλε ημερομηνία σε ένα task και θα εμφανιστεί εδώ.</div>
      <button class="btn btn-p" id="gToBoard" style="margin-top:14px">Πήγαινε στο Board</button></div></div>`
    : `<div class="gantt card" style="padding:0;overflow:hidden;--gl:${LEFTW}px">
    <div class="g-scroll"><div class="g-inner" style="width:${LEFTW + nDays * CELL}px">
      <div class="g-hrow g-sticky"><div class="g-label g-corner">ΤΙΤΛΟΣ</div>
        ${MOB ? '' : '<div class="g-col g-corner">ΔΙΑΡΚΕΙΑ</div><div class="g-col g-col2 g-corner">ΕΝΑΡΞΗ / ΛΗΞΗ</div>'}
        <div class="g-cells" style="width:${nDays * CELL}px">${monthCells}</div></div>
      <div class="g-hrow g-sticky2"><div class="g-label"></div>${cols()}
        <div class="g-cells" style="width:${nDays * CELL}px">${dayHead}</div></div>
      ${rowsHtml}
    </div></div>
  </div>`}`;

  const toB = $('#gToBoard');
  if (toB) toB.onclick = () => window.CNP.go('board');

  $('#gMp').onclick = () => { st.mode = 'project'; R.gantt(); };
  $('#gMa').onclick = () => { st.mode = 'people'; R.gantt(); };
  $('#gPrev').onclick = () => { st.from = addD(st.from, -7); R.gantt(); };
  $('#gNext').onclick = () => { st.from = addD(st.from, 7); R.gantt(); };
  $('#gToday').onclick = () => { st.from = addD(today(), -7); R.gantt(); };
  const gAll = $('#gAll');
  if (gAll) gAll.onclick = () => {
    const anyOpen = Object.values(st.open).some(Boolean);
    st.open = {};
    if (!anyOpen) d.projects.forEach(p => st.open[p.id] = true);
    R.gantt();
  };
  $$('.g-projrow').forEach(r => r.onclick = e => {
    if (e.target.closest('.g-bar,.g-after')) return;
    st.open[+r.dataset.toggle] = !st.open[+r.dataset.toggle];
    R.gantt();
  });
  $$('.g-tlabel').forEach(l => l.onclick = () => openTask(+l.dataset.open));
  const sc = $('.g-scroll');
  // στο κινητό η ορατή περιοχή είναι μικρή — κεντράρισε το σήμερα αντί για σταθερό offset 300px
  if (sc) sc.scrollLeft = Math.max(0, diffD(d.from, today()) * CELL - (MOB ? 60 : 300));

  /* ---- drag / resize ---- */
  let drag = null;
  c.onpointerdown = e => {
    const bar = e.target.closest('.g-bar');
    if (!bar || e.button !== 0) return;
    // δέσε τον δείκτη στη μπάρα: με αφή, αλλιώς ο browser «κλέβει» το gesture για scroll
    try { bar.setPointerCapture(e.pointerId); } catch (err) { /* ασήμαντο */ }
    drag = {bar, id: e.pointerId, x0: e.clientX, left0: parseFloat(bar.style.left), w0: parseFloat(bar.style.width),
      resize: !!e.target.closest('.g-handle'), moved: false};
  };
  // αν το gesture ακυρωθεί (scroll/κλήση/εναλλαγή εφαρμογής) → επανάφερε, μη μείνει κολλημένο
  c.onpointercancel = () => {
    if (!drag) return;
    drag.bar.style.left = drag.left0 + 'px';
    drag.bar.style.width = drag.w0 + 'px';
    drag.bar.classList.remove('dragging');
    drag = null;
  };
  c.onpointermove = e => {
    if (!drag) return;
    const dx = e.clientX - drag.x0;
    if (Math.abs(dx) > 4) drag.moved = true;
    if (!drag.moved) return;
    const snap = Math.round(dx / CELL) * CELL;
    if (drag.resize) drag.bar.style.width = Math.max(CELL - 4, drag.w0 + snap) + 'px';
    else drag.bar.style.left = (drag.left0 + snap) + 'px';
    drag.bar.classList.add('dragging');
  };
  c.onpointerup = async e => {
    if (!drag) return;
    const {bar, moved, resize, left0, w0} = drag;
    drag = null;
    bar.classList.remove('dragging');
    if (!moved) { openTask(+bar.dataset.task); return; }
    const dL = Math.round((parseFloat(bar.style.left) - left0) / CELL);
    const dW = Math.round((parseFloat(bar.style.width) - w0) / CELL);
    let ns = bar.dataset.start, ne = bar.dataset.end;
    if (resize) ne = addD(ne, dW); else { ns = addD(ns, dL); ne = addD(ne, dL); }
    if (ne < ns) ne = ns;
    const r = await api('gantt_move', {task: +bar.dataset.task, start: ns, end: ne}).catch(() => ({ok: 0}));
    if (r.ok) toast(`${dShort(ns)} – ${dShort(ne)}`);
    else toast('Δεν επιτρέπεται', true);
    R.gantt();
  };
};


/* ═════════ 🛑 ΑΝΑΣΤΟΛΕΣ — υπηρεσίες με ληξιπρόθεσμες οφειλές ═════════
   Ο αυτοματισμός του WHMCS εκτελείται μόνο όπου υπάρχει server module (15% των
   υπηρεσιών), οπότε τιμωρεί άνισα όποιον τυχαίνει να φιλοξενείται στη Hetzner.
   Μέχρι να ολοκληρωθεί η μετάβαση, η απόφαση παίρνεται εδώ — ανά ΠΕΛΑΤΗ, με τα
   πραγματικά ανοιχτά ποσά, και μένει ίχνος ποιος έκανε τι. */
R.suspend = async function () {
  setTop('Αναστολές', 'Υπηρεσίες που πρέπει να πέσουν — η ενέργεια αναγνωρίζεται από το WHMCS');
  const c = $('#content');
  if (!S.boot.me.full) { c.innerHTML = '<div class="empty" style="padding:44px">Χρειάζεσαι πλήρη πρόσβαση.</div>'; return; }
  c.innerHTML = '<div class="skel" style="height:220px"></div>';
  const st = R.suspend._s = R.suspend._s || {open: {}, machines: false, ripe: true};

  const load = async () => {
    const d = await api('suspend_queue&machines=' + (st.machines ? '1' : '0') + '&ripe=' + (st.ripe ? '1' : '0')).catch(() => null);
    if (!d) { c.innerHTML = '<div class="empty" style="padding:40px">Σφάλμα φόρτωσης</div>'; return; }

    // ομαδοποίηση ανά πελάτη — η οφειλή είναι του πελάτη, όχι της κάθε υπηρεσίας
    const by = {};
    d.rows.forEach(r => {
      (by[r.client] = by[r.client] || {name: r.name, debt: r.debt, days: r.days, ripe: r.ripe,
        invs: r.invs, badDue: r.badDue, notified: r.notified, svc: []}).svc.push(r);
    });
    /* Κλειστά εξ ορισμού: η λίστα διαβάζεται σαν σύνοψη ανά πελάτη και ανοίγεις
       μόνο όποιον κοιτάς. Ανοιχτά όλα, γίνεται σεντόνι εκατοντάδων γραμμών. */
    const groups = Object.entries(by).sort((a, b) => b[1].debt - a[1].debt);

    const badge = r => r.auto
      ? `<span class="pill pill-info" title="Το module ${esc(r.module)} εκτελεί την αναστολή">αυτόματο · ${esc(r.module)}</span>`
      : '<span class="pill pill-mut" title="Δεν υπάρχει module — γίνεται με το χέρι">χειροκίνητο</span>';
    /* Η κατάσταση είναι ΑΚΡΙΒΩΣ αυτή του WHMCS — η οθόνη τη δείχνει, δεν την
       αλλάζει. Δίπλα φαίνεται και η ωμή τιμή, για να μην υπάρχει αμφιβολία. */
    const stateBadge = r => {
      const raw = `<span class="mut" style="font-size:10.5px">WHMCS: ${esc(r.whmcsStatus)}</span>`;
      if (r.state === 'exempt') {
        return `<span class="pill pill-info" title="Override Auto-Suspend στο WHMCS — δεν αναστέλλεται">
          🛡 εξαιρείται${r.exempt && r.exempt !== '∞' ? ' ως ' + esc(dFull(r.exempt)) : ''}</span> ${raw}`;
      }
      if (r.state === 'terminated') { return `<span class="pill pill-mut">τερματίστηκε</span> ${raw}`; }
      if (r.state === 'suspended') { return `<span class="pill pill-bad">σε αναστολή</span> ${raw}`; }
      return `<span class="pill pill-warn">εκκρεμεί</span> ${raw}`;
    };
    const noteBadge = x => x
      ? `<span class="pill pill-info" title="${esc(x.note || '')}">${x.action === 'paid' ? 'πληρώθηκε' : 'εξαίρεση'} · ${esc(x.by)}</span>` : '';

    c.innerHTML = `
      <div class="grid g4" style="--n:5" style="margin-bottom:14px">
        ${suStat(I.users, d.sum.clients, 'πελάτες', d.sum.clients ? 'var(--bad)' : 'var(--ok)')}
        ${suStat(I.coin, fmtEur(d.sum.debt), 'ληξιπρόθεσμα', 'var(--bad)')}
        ${suStat(I.alert, d.sum.pending, 'εκκρεμούν', d.sum.pending ? '#e0a020' : 'var(--ok)')}
        ${suStat(I.check || I.checkSquare, d.sum.done, 'σε αναστολή', 'var(--ok)')}
        ${suStat(I.lock || I.eye, d.sum.exempt, 'εξαιρούνται (WHMCS)', 'var(--brand)')}
      </div>
      <div class="card" style="margin-bottom:12px"><div class="card-b">
        <div style="display:flex;gap:7px;flex-wrap:wrap;margin-bottom:9px">
          <button class="btn btn-sm ${st.machines ? 'btn-p' : 'btn-o'}" data-sfm>🖥 ${st.machines ? 'Μόνο μηχανήματα' : 'Όλες οι υπηρεσίες'}</button>
          <button class="btn btn-sm ${st.ripe ? 'btn-p' : 'btn-o'}" data-sfr>${st.ripe ? '⚠ Πέρασαν το όριο' : '⚠ Όλες οι καθυστερήσεις'}</button>
          <button class="btn btn-sm btn-o" data-sexp>${Object.values(st.open).some(Boolean) ? '⊟ Κλείσιμο όλων' : '⊞ Άνοιγμα όλων'}</button>
          <span class="mut" style="font-size:11.5px;align-self:center">
            ${st.machines ? 'Domains, DID, άδειες και συμβόλαια δεν εμφανίζονται.' : 'Όλα όσα πρέπει να πέσουν. Άλλαξε την κατάσταση στο WHMCS και ενημερώνεται μόνο του.'}
          </span>
        </div>
        <div class="mut" style="font-size:12px;line-height:1.5">
          Όριο WHMCS: <b>${d.grace} ημέρες</b> μετά τη λήξη. Τα ποσά είναι τα <b>πραγματικά ανοιχτά ανά παραστατικό</b> —
          όχι το «Unpaid» του WHMCS, που δεν μειώνεται στις μερικές πληρωμές.
          ${st.ripe && d.sum.allDebt > d.sum.debt ? `<br>Κρύβονται ${fmtEur(d.sum.allDebt - d.sum.debt)} από πελάτες που δεν έχουν φτάσει ακόμη το όριο.` : ''}
        </div>
      </div></div>
      ${groups.map(([cid, g]) => `
        <div class="card" style="margin-bottom:12px;border-left:4px solid ${g.ripe ? 'var(--bad)' : '#e0a020'}">
          <div class="card-h susp-h" data-sg="${cid}">
            <b style="color:var(--ink)">${esc(g.name)}</b>
            <a href="/cloudonadminpanel/clientssummary.php?userid=${cid}" target="_blank"
               class="mut" style="text-decoration:none;flex:none" title="Άνοιγμα πελάτη στο WHMCS">↗</a>
            <span class="pill ${g.ripe ? 'pill-bad' : 'pill-warn'}">${g.days} ημέρες</span>
            <b style="color:var(--bad)">${fmtEur(g.debt)}</b>
            <span class="mut" style="font-size:11.5px">${(() => {
              const p = g.svc.filter(x => x.state === 'pending').length;
              const e = g.svc.filter(x => x.state === 'exempt').length;
              const s2 = g.svc.length - p - e;
              return [`${g.svc.length} υπηρεσίες`, p ? `${p} εκκρεμούν` : '',
                e ? `${e} εξαιρούνται` : '', s2 ? `${s2} σε αναστολή` : ''].filter(Boolean).join(' · ');
            })()}</span>
            ${g.badDue ? '<span class="pill pill-warn" title="Παραστατικό με λανθασμένο έτος στην ημ. λήξης — διόρθωσέ το στο WHMCS">λάθος ημ. λήξης</span>' : ''}
            <span style="flex:1"></span>
            ${g.notified ? `<span class="pill pill-ok" title="Ειδοποιήθηκε ${esc(dFull(g.notified.at))} από ${esc(g.notified.by)}${g.notified.date ? ' — αναστολή ' + esc(dFull(g.notified.date)) : ''}">✉ ειδοποιήθηκε</span>` : ''}
            <button class="btn btn-sm ${g.notified ? 'btn-o' : 'btn-p'}" data-snotify="${cid}">✉ Ειδοποίηση πελάτη</button>
            <span class="kb-gchev ${st.open[cid] ? 'open' : ''}">${I.chev}</span>
          </div>
          <div class="card-b" ${st.open[cid] ? '' : 'style="display:none"'}>
            <div style="font-size:12.5px;margin-bottom:7px;padding:7px 11px;border-radius:9px;background:var(--bg2)">
              <b>Γιατί:</b> δεν πληρώθηκαν <b style="color:var(--bad)">${fmtEur(g.debt)}</b> ·
              ${g.days} ημέρες μετά τη λήξη ${g.ripe ? `<b>— πέρασε το όριο των ${d.grace} ημερών</b>` : `(όριο ${d.grace})`}
            </div>
            <div class="mut" style="font-size:11.5px;margin-bottom:9px">Ληξιπρόθεσμα:
              ${g.invs.map(i => `<a href="/cloudonadminpanel/index.php/billing/invoice/${i.id}" target="_blank" style="color:var(--brand);margin-right:9px">${esc(i.num)} · ${fmtEur(i.open)} · ${i.days} ημ.${i.badDue ? ' ⚠' : ''}</a>`).join('')}</div>
            ${g.svc.map(r => `<div class="susp-row ${r.state === 'exempt' ? 'exempt' : (r.state !== 'pending' ? 'done' : '')}">
              <span class="susp-t">${esc(r.domain || r.product)}
                <span class="mut">${esc(r.product)}${r.ip ? ' · ' + esc(r.ip) : ''} · ${fmtEur(r.amount)}/${esc(r.cycle)}</span></span>
              ${r.machine ? '<span class="pill pill-mut" title="Μηχάνημα — σβήνει">🖥</span>' : ''}
              ${badge(r)}
              ${stateBadge(r)}
              ${noteBadge(r.done)}
              <span style="flex:1"></span>
              ${r.state === 'pending' && r.auto
                /* Μόνο μέσω module: το WHMCS εκτελεί τη διακοπή και ενημερώνει
                   κατάσταση, τιμολόγηση και ιστορικό. Δεν γράφουμε εμείς status. */
                ? `<button class="btn btn-sm btn-danger" data-sdo="${r.service}" data-mode="module"
                     title="Εκτελεί την αναστολή μέσω ${esc(r.module)} — από το WHMCS">⏻ Αναστολή τώρα</button>` : ''}
              ${r.state === 'suspended' && r.auto
                ? `<button class="btn btn-sm btn-o" data-sundo="${r.service}" title="Επαναφορά μέσω ${esc(r.module)}">↻ Επαναφορά</button>` : ''}
              <a class="btn btn-sm ${r.state === 'pending' && !r.auto ? 'btn-p' : 'btn-o'}"
                 href="${esc(r.adminUrl)}" target="_blank"
                 title="${r.state === 'pending' && !r.auto ? 'Δεν έχει module — η αλλαγή κατάστασης γίνεται στο WHMCS' : 'Άνοιγμα στο WHMCS'}"
                 >${r.state === 'pending' && !r.auto ? 'Αλλαγή στο WHMCS ↗' : '↗'}</a>
              ${r.done
                ? `<button class="btn btn-sm btn-o" data-sclear="${r.service}">αναίρεση</button>`
                : (r.state === 'pending'
                  ? `<button class="btn btn-sm btn-o" data-smark="${r.service}" data-act="skipped" title="Δεν θα πέσει — π.χ. δώσαμε παράταση">Εξαίρεση</button>` : '')}
            </div>`).join('')}
          </div></div>`).join('')}
      ${groups.length ? '' : `<div class="empty" style="padding:44px">${st.ripe ? 'Κανένα μηχάνημα δεν έχει περάσει το όριο 🎉' : 'Καμία ληξιπρόθεσμη οφειλή 🎉'}</div>`}`;

    $$('[data-snotify]').forEach(b => b.onclick = e => {
      e.stopPropagation();
      const cid = +b.dataset.snotify;
      openNotice(cid, by[cid], load);
    });
    const ex = $('[data-sexp]');
    if (ex) {
      ex.onclick = () => {
        const anyOpen = Object.values(st.open).some(Boolean);
        st.open = {};
        if (!anyOpen) { groups.forEach(([cid]) => { st.open[cid] = true; }); }
        load();
      };
    }
    const fm = $('[data-sfm]'); if (fm) { fm.onclick = () => { st.machines = !st.machines; load(); }; }
    const fr = $('[data-sfr]'); if (fr) { fr.onclick = () => { st.ripe = !st.ripe; load(); }; }
    $$('.susp-h').forEach(h => h.onclick = e => {
      if (e.target.closest('a')) { return; }
      const k = h.dataset.sg; st.open[k] = !st.open[k];
      h.nextElementSibling.style.display = st.open[k] ? '' : 'none';
      h.querySelector('.kb-gchev').classList.toggle('open', !!st.open[k]);
    });
    /* Πραγματική διακοπή υπηρεσίας πελάτη: πάντα με επιβεβαίωση που ονομάζει
       ΤΙ θα συμβεί και σε ΠΟΙΟΝ. Δεν αρκεί ένα κλικ. */
    $$('[data-sdo]').forEach(b => b.onclick = async () => {
      const sid = +b.dataset.sdo, mode = b.dataset.mode;
      const r = d.rows.find(x => x.service === sid);
      const what = r.domain || r.product;
      /* Η διατύπωση ακολουθεί το είδος: «σβήνει το μηχάνημα» ισχύει για VM,
         όχι για SSL ή λογαριασμό φιλοξενίας. */
      const msg = `Θα ανασταλεί ΤΩΡΑ η υπηρεσία «${what}»${r.ip ? ' (' + r.ip + ')' : ''} του πελάτη ${r.name.trim()}.\n\n`
        + (r.machine
          ? `Το module ${r.module} θα σβήσει το μηχάνημα — ο πελάτης θα έχει διακοπή.`
          : `Το module ${r.module} θα εκτελέσει την αναστολή — η υπηρεσία θα πάψει να λειτουργεί για τον πελάτη.`)
        + `\n\nΗ ενέργεια γίνεται μέσω WHMCS, όπως αν πατούσες Suspend εκεί.`;
      if (!await cnpConfirm(msg, {title: '⏻ Αναστολή υπηρεσίας',
        ok: 'Ναι, αναστολή', cancel: 'Άκυρο', danger: true})) { return; }
      const reason = await cnpPrompt('Αιτιολογία (μπαίνει στο ιστορικό και στο WHMCS):',
        {title: 'Αιτιολογία', ok: 'Εκτέλεση', input: '', placeholder: 'π.χ. ληξιπρόθεσμη οφειλή, ειδοποιήθηκε 21/08'});
      if (reason === null) { return; }
      b.disabled = true; b.textContent = '…';
      const res = await api('suspend_do', {service: sid, mode, reason}).catch(e => ({err: e.message}));
      if (res.err) { toast(res.err, true); load(); return; }
      toast(mode === 'module' ? 'Η υπηρεσία ανεστάλη' : 'Σημάνθηκε ως ανεσταλμένη');
      load();
    });
    $$('[data-sundo]').forEach(b => b.onclick = async () => {
      const sid = +b.dataset.sundo;
      const r = d.rows.find(x => x.service === sid);
      if (!await cnpConfirm(`Επαναφορά της «${r.domain || r.product}» (${r.name}) σε ενεργή;`,
        {title: '↻ Επαναφορά', ok: 'Ναι, επαναφορά', cancel: 'Άκυρο'})) { return; }
      const res = await api('suspend_do', {service: sid, do: 'unsuspend'}).catch(e => ({err: e.message}));
      if (res.err) { toast(res.err, true); return; }
      toast('Επανήλθε σε ενεργή'); load();
    });
    $$('[data-smark]').forEach(b => b.onclick = async () => {
      const note = await cnpPrompt('Γιατί εξαιρείται;',
        {title: 'Εξαίρεση από τις αναστολές', ok: 'Καταγραφή', placeholder: 'π.χ. δώσαμε παράταση ως 30/08'});
      if (note === null) { return; }
      await api('suspend_mark', {service: +b.dataset.smark, action: b.dataset.act, note});
      toast('Καταγράφηκε'); load();
    });
    $$('[data-sclear]').forEach(b => b.onclick = async () => {
      await api('suspend_mark', {service: +b.dataset.sclear, clear: 1});
      toast('Αναιρέθηκε'); load();
    });
  };
  load();
};

/* Ειδοποίηση πελάτη για επικείμενη αναστολή.
   Στέλνεται ως ticket στο Λογιστήριο: ο πελάτης το λαμβάνει με email, μπορεί να
   απαντήσει, και μένει ίχνος — σε αντίθεση με ένα σκέτο email που χάνεται. */
function openNotice(cid, g, done) {
  const ovl = document.createElement('div'); ovl.className = 'ovl show';
  const d5 = (() => { const x = new Date(); x.setDate(x.getDate() + 5); return x.toISOString().slice(0, 10); })();
  const pend = g.svc.filter(x => x.state === 'pending');
  ovl.innerHTML = `<div class="pal-box nt-box" onclick="event.stopPropagation()">
    <div class="nt-h"><b>✉ Ειδοποίηση αναστολής</b>
      <span class="mut">${esc(g.name)}</span><span style="flex:1"></span>
      <button class="drawer-x" id="ntX">✕</button></div>
    <div class="nt-b">
      <div class="frow">
        <div><label class="lbl">Θα ανασταλούν στις</label><input type="date" class="inp" id="ntDate" value="${d5}"></div>
        <div><label class="lbl">Γλώσσα</label><select class="inp" id="ntLang">
          <option value="">— αυτόματα από τον πελάτη —</option>
          <option value="el">Ελληνικά</option><option value="en">English</option></select></div>
      </div>

      <label class="lbl" style="margin-top:12px">Υπηρεσίες που θα αναφερθούν <span class="mut">(${pend.length} εκκρεμείς)</span></label>
      <div class="nt-svc">
        ${g.svc.map(x => `<label class="nt-s ${x.state !== 'pending' ? 'off' : ''}">
          <input type="checkbox" class="ntS" value="${x.service}" ${x.state === 'pending' ? 'checked' : ''}>
          <span>${esc(x.domain || x.product)} <span class="mut">${esc(x.product)}</span></span>
          ${x.state !== 'pending' ? '<span class="pill pill-mut">ήδη σε αναστολή</span>' : ''}</label>`).join('')}
      </div>
      <div style="display:flex;gap:6px;margin-top:6px">
        <button class="btn btn-sm btn-o" id="ntAll">Όλες</button>
        <button class="btn btn-sm btn-o" id="ntNone">Καμία</button>
      </div>

      <label class="lbl" style="margin-top:14px">Κείμενο</label>
      <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:7px">
        <button class="btn btn-sm btn-p" id="ntTpl">📄 Πάγιο κείμενο</button>
        <button class="btn btn-sm btn-o" id="ntAi">✨ Με AI</button>
        <span class="mut" style="font-size:11.5px;align-self:center">Το AI κρατά ακριβώς τα ποσά και τις ημερομηνίες.</span>
      </div>
      <input class="inp" id="ntSubj" placeholder="Θέμα" style="margin-bottom:7px">
      <textarea class="inp" id="ntBody" rows="14" style="width:100%;resize:vertical;font-family:inherit"
        placeholder="Πάτα «Πάγιο κείμενο» ή «Με AI» για να συνταχθεί…"></textarea>
      <div class="mut" style="font-size:11.5px;margin-top:6px" id="ntInfo"></div>
    </div>
    <div class="nt-f">
      <button class="btn btn-o" id="ntCopy">⧉ Αντιγραφή</button>
      <span style="flex:1"></span>
      <button class="btn btn-o" id="ntCancel">Άκυρο</button>
      <button class="btn btn-p" id="ntSend" disabled>Αποστολή ως ticket</button>
    </div></div>`;
  document.body.appendChild(ovl);
  const close = () => ovl.remove();
  $('#ntX', ovl).onclick = close; $('#ntCancel', ovl).onclick = close; ovl.onclick = close;
  $('#ntAll', ovl).onclick = () => $$('.ntS', ovl).forEach(x => x.checked = true);
  $('#ntNone', ovl).onclick = () => $$('.ntS', ovl).forEach(x => x.checked = false);

  const picked = () => $$('.ntS', ovl).filter(x => x.checked).map(x => +x.value);
  const compose = async mode => {
    const ids = picked();
    if (!ids.length) { toast('Διάλεξε τουλάχιστον μία υπηρεσία', true); return; }
    let draft = '';
    if (mode === 'ai') {
      draft = await cnpDialog({title: '✨ AI', body: 'Θέλεις να προσθέσεις κάτι δικό σου; (προαιρετικό)',
        input: $('#ntBody', ovl).value.trim(), rows: 4, max: 1500, ok: 'Σύνταξη', cancel: 'Άκυρο',
        placeholder: 'π.χ. να αναφέρω ότι μιλήσαμε τηλεφωνικά και ζήτησαν παράταση'});
      if (draft === null) { return; }
    }
    $('#ntBody', ovl).value = 'Σύνταξη…';
    const r = await api('suspend_notice', {client: cid, services: ids, date: $('#ntDate', ovl).value,
      lang: $('#ntLang', ovl).value, mode, draft}).catch(e => ({err: e.message}));
    if (r.err) { $('#ntBody', ovl).value = ''; toast(r.err, true); return; }
    $('#ntSubj', ovl).value = r.subject;
    $('#ntBody', ovl).value = r.body;
    $('#ntInfo', ovl).textContent = `Θα σταλεί στο ${r.email} · συνολική οφειλή ${fmtEur(r.total)} · ${r.services.length} υπηρεσίες`;
    $('#ntSend', ovl).disabled = false;
  };
  $('#ntTpl', ovl).onclick = () => compose('template');
  $('#ntAi', ovl).onclick = () => compose('ai');
  $('#ntCopy', ovl).onclick = async () => {
    await navigator.clipboard.writeText($('#ntSubj', ovl).value + '\n\n' + $('#ntBody', ovl).value);
    toast('Αντιγράφηκε');
  };
  $('#ntSend', ovl).onclick = async () => {
    /* Φεύγει προς πελάτη — επιβεβαίωση, όχι κατά λάθος κλικ. */
    if (!await cnpConfirm(`Να σταλεί στον πελάτη «${g.name}»; Θα δημιουργηθεί ticket και θα λάβει email.`,
      {ok: 'Αποστολή', cancel: 'Όχι'})) { return; }
    const r = await api('suspend_notice_send', {client: cid, services: picked(),
      date: $('#ntDate', ovl).value, subject: $('#ntSubj', ovl).value, body: $('#ntBody', ovl).value})
      .catch(e => ({err: e.message}));
    if (r.err) { toast(r.err, true); return; }
    toast('Στάλθηκε — ticket #' + (r.tid || r.ticket));
    close(); done && done();
  };
  compose('template');
}

/* ═════════ 📊 ΑΠΟΔΟΣΗ ΧΕΙΡΙΣΤΩΝ ═════════
   Πόσο δουλεύει ο καθένας σε tickets και tasks. Το «πόσο» μετριέται από τα
   ίχνη που όντως υπάρχουν: απαντήσεις σε tickets, χρόνος πρώτης απάντησης,
   ολοκληρωμένες εργασίες και συνέπεια στις προθεσμίες. */
R.perf = async function () {
  setTop('Απόδοση χειριστών', 'Tickets & tasks ανά άτομο — τι έγινε στην περίοδο');
  const c = $('#content');
  if (!S.boot.me.full) { c.innerHTML = '<div class="empty" style="padding:44px">Χρειάζεσαι πλήρη πρόσβαση.</div>'; return; }
  const st = R.perf._s = R.perf._s || {p: 'month'};
  c.innerHTML = '<div class="skel" style="height:240px"></div>';

  const load = async () => {
    const d = await api('perf&p=' + st.p).catch(() => null);
    if (!d) { c.innerHTML = '<div class="empty" style="padding:40px">Σφάλμα φόρτωσης</div>'; return; }
    const per = [['week', 'Εβδομάδα'], ['month', 'Μήνας'], ['q', '90 ημέρες']];
    const dur = m => m === null ? '—' : (m < 90 ? m + '΄' : (m < 2880 ? Math.round(m / 60) + 'ω' : Math.round(m / 1440) + ' ημ.'));

    /* Ραβδόγραμμα ανά ημέρα: δείχνει ΡΥΘΜΟ, όχι μόνο σύνολο — ποιος δουλεύει
       σταθερά και ποιος σε εκρήξεις. Κοινή κλίμακα για να συγκρίνονται. */
    const peak = Math.max(1, ...d.rows.map(r => Math.max(0, ...Object.values(r.days || {}))));
    const spark = r => `<div class="pf-spark" title="Απαντήσεις ανά ημέρα">${d.days.map(x => {
      const v = (r.days || {})[x] || 0;
      const h = v ? Math.max(3, Math.round(v / peak * 26)) : 0;
      return `<i style="height:${h}px" title="${esc(dFull(x))}: ${v}"></i>`;
    }).join('')}</div>`;

    /* Ανάλυση ανά ημέρα με φατσούλες: μια ματιά και ξέρεις πώς πήγε η κάθε μέρα.
       Η βαθμίδα βγαίνει από απαντήσεις + ολοκληρωμένες εργασίες μαζί, γιατί
       άλλος δουλεύει tickets και άλλος tasks — δεν είναι δίκαιο να μετράει μόνο
       το ένα. Το Σαββατοκύριακο δεν «κατηγορείται» για μηδέν. */
    const WD = ['Κυρ', 'Δευ', 'Τρι', 'Τετ', 'Πεμ', 'Παρ', 'Σαβ'];
    const last7 = d.days.slice(-7);
    const face = n => n === 0 ? '😴' : (n <= 2 ? '🙂' : (n <= 5 ? '💪' : '🔥'));
    const dayStrip = r => `<div class="pf-days">${last7.map(x => {
      const rep = (r.days || {})[x] || 0;
      const tsk = (r.daysTasks || {})[x] || 0;
      const n = rep + tsk;
      const dt = new Date(x + 'T12:00:00');
      const wknd = dt.getDay() === 0 || dt.getDay() === 6;
      const tip = `${dFull(x)} — ${rep} απαντήσεις, ${tsk} εργασίες`;
      return `<div class="pf-day ${n ? 'on' : ''} ${wknd ? 'wk' : ''}" title="${esc(tip)}">
        <span class="pf-face">${wknd && !n ? '·' : face(n)}</span>
        <span class="pf-wd">${WD[dt.getDay()]}</span>
        <span class="pf-n">${n || ''}</span></div>`;
    }).join('')}</div>`;

    c.innerHTML = `
      <div class="card" style="margin-bottom:13px"><div class="card-b" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        ${per.map(([k, l]) => `<button class="btn btn-sm ${st.p === k ? 'btn-p' : 'btn-o'}" data-pp="${k}">${l}</button>`).join('')}
        <span class="mut" style="font-size:12px">${esc(dFull(d.from))} – ${esc(dFull(d.to))}</span>
        <span style="flex:1"></span>
        <span class="mut" style="font-size:12px">${d.totals.replies} απαντήσεις · ${d.totals.tasksDone} εργασίες</span>
      </div></div>

      ${d.rows.map(r => `<div class="card pf-card">
        <div class="card-b">
          <div class="pf-h">
            <span class="ava" style="width:32px;height:32px;font-size:12px">${esc(adminIni(r.id))}</span>
            <b style="font-size:14.5px">${esc(r.name)}</b>
            ${r.overdueTasks ? `<span class="pill pill-bad">${r.overdueTasks} εκπρόθεσμες</span>` : ''}
            ${r.ball ? `<span class="pill pill-warn">⚡ ${r.ball} στη μπάλα του</span>` : ''}
            <span style="flex:1"></span>
            ${spark(r)}
          </div>
          ${dayStrip(r)}
          <div class="pf-grid">
            ${[['Απαντήσεις', r.replies, 'σε tickets μέσα στην περίοδο'],
               ['Tickets', r.tickets, 'διαφορετικά tickets που άγγιξε'],
               ['1η απάντηση', dur(r.frtMed), r.frtN ? 'διάμεσος σε ' + r.frtN + ' tickets' : 'χωρίς δείγμα'],
               ['Εργασίες', r.tasksDone, 'ολοκληρώθηκαν στην περίοδο'],
               ['Στην ώρα τους', r.onTimePct === null ? '—' : r.onTimePct + '%', r.onTimePct === null ? 'χωρίς προθεσμίες' : r.onTime + ' στην ώρα · ' + r.late + ' αργά'],
               ['Ανοιχτά τώρα', r.openTasks + r.ticketsOpenNow, r.openTasks + ' εργασίες · ' + r.ticketsOpenNow + ' tickets']]
              .map(([k, v, sub]) => `<div class="pf-m"><b>${esc(String(v))}</b><span>${k}</span><i>${esc(sub)}</i></div>`).join('')}
          </div>
        </div></div>`).join('')}
      ${d.rows.length ? '' : '<div class="empty" style="padding:44px">Καμία δραστηριότητα στην περίοδο</div>'}
      <div class="mut" style="font-size:11.5px;padding:4px 2px">${esc(d.note)}</div>`;

    $$('[data-pp]').forEach(b => b.onclick = () => { st.p = b.dataset.pp; load(); });
  };
  load();
};

/* ═══════════ Departments ═══════════
   Τα ticket departments του WHMCS: πού απευθύνεται το αίτημα. Κάθε εργασία
   ανήκει σε ένα department, το department το εξυπηρετούν ΟΜΑΔΕΣ ειδικότητας,
   και την εργασία την εκτελεί ένας άνθρωπος αυτών των ομάδων. Εδώ βλέπεις το
   φορτίο κάθε department και ποιον πελάτη / έργο κρατάει πίσω. */
R.units = async function () {
  setTop('Departments', 'Πού απευθύνεται κάθε εργασία — και ποιες ομάδες το καλύπτουν');
  const c = $('#content');
  c.innerHTML = '<div class="skel" style="height:260px"></div>';
  const d = await api('depts_load');
  const tile = u => {
    const pct = u.total ? Math.round((u.total - u.open) / u.total * 100) : 0;
    return `<a class="un-card" href="#/unit/${u.id}" style="--uc:${u.color}">
      <div class="un-top">
        <span class="un-badge" style="background:${u.color}">${esc(u.icon)}</span>
        <span class="un-name">${esc(u.name)}${u.hidden ? ' <span class="pill pill-mut">κρυφό</span>' : ''}</span>
        ${u.late ? `<span class="pill pill-bad">${u.late} εκπρόθεσμ${u.late === 1 ? 'η' : 'ες'}</span>` : ''}
      </div>
      <div class="un-teams">${u.teams.length
        ? u.teams.map(t => `<span class="pill pill-mut" style="border-left:3px solid ${t.color}">${esc(t.name)}</span>`).join('')
        : '<span class="pill pill-warn">καμία ομάδα δεν το εξυπηρετεί</span>'}</div>
      <div class="un-nums">
        <span><b>${u.open}</b><small>εργασίες</small></span>
        <span><b>${u.tickets}</b><small>tickets</small></span>
        <span><b>${u.projects}</b><small>έργα</small></span>
        <span><b>${u.clients}</b><small>πελάτες</small></span>
      </div>
      <div class="bar"><span class="${u.total ? 'ok' : ''}" style="width:${pct}%"></span></div>
      <small class="mut">${u.total ? `${u.total - u.open}/${u.total} ολοκληρωμένες` : 'καμία εργασία ακόμη'}${u.email ? ' · ' + esc(u.email) : ''}</small>
    </a>`;
  };
  c.innerHTML = `
    <div class="card"><div class="card-b" style="font-size:12.5px;color:var(--mut);padding:12px 16px;line-height:1.6">
      Το <b>department</b> είναι <b>πού απευθύνεται</b> το αίτημα — το ίδιο που επιλέγει ο πελάτης
      όταν ανοίγει ticket. Το εξυπηρετούν μία ή περισσότερες <a href="#/teams">ομάδες ειδικότητας</a>,
      και κάθε εργασία την <b>εκτελεί ένας άνθρωπος</b> αυτών των ομάδων.
      Το έργο παραδίδεται όταν κλείσουν οι εργασίες <b>όλων</b> των departments που το αγγίζουν.
    </div></div>
    ${d.orphan ? `<div class="card"><div class="card-b"><span class="pill pill-warn">⚠ ${d.orphan} εργασίες χωρίς department</span>
      <span class="mut" style="font-size:12px;margin-left:8px">Δεν τις χρεώνεται κανείς — άνοιξέ τες και όρισε department.</span></div></div>` : ''}
    <div class="un-grid">${d.depts.map(tile).join('')}</div>
    ${d.canManage ? `<div style="margin-top:12px"><a class="btn btn-o btn-sm" href="#/settings" id="unNew">${I.gear} Διαχείριση departments</a>
      <span class="mut" style="font-size:11.5px;margin-left:8px">ίδια λίστα με το WHMCS — ό,τι αλλάξεις το βλέπει και ο πελάτης</span></div>` : ''}`;
  const nb = $('#unNew');
  if (nb) nb.onclick = () => { (R.settings._st = R.settings._st || {}).sub = 'whticket'; };
};

R.unit = async function (id) {
  const c = $('#content');
  c.innerHTML = '<div class="skel" style="height:300px"></div>';
  const d = await api('dept_view&id=' + (+id || 0));
  setTop(d.dept.name, `${d.open} ανοιχτές εργασίες`);
  const prioT = ['', '⬆', '🔥'];
  const grp = g => `<div class="card">
    <div class="card-h" style="gap:8px">
      <span class="dot" style="background:${g.color || d.dept.color};width:10px;height:10px"></span>
      ${g.projectId ? `<a href="#/board/${g.projectId}" style="font-weight:700">${esc(g.project)}</a>`
        : '<b>Χωρίς έργο</b>'}
      ${g.clientId ? `<a class="pill pill-info" href="#/client360/${g.clientId}" title="Καρτέλα πελάτη">${I.user} ${esc(g.client)}</a>`
        : '<span class="pill pill-mut">χωρίς πελάτη</span>'}
      ${g.projectDue ? `<span class="pill ${g.projectDue < today() ? 'pill-bad' : 'pill-mut'}">παράδοση ${dShort(g.projectDue)}</span>` : ''}
      <span class="kb-n" style="margin-left:auto">${g.tasks.length}</span>
    </div>
    <table class="tbl"><tbody>
      ${g.tasks.map(t => `<tr>
        <td><a href="javascript:" data-task="${t.id}" style="font-weight:600">${prioT[t.prio] || ''} ${esc(t.title)}</a>
          ${t.ticket ? `<a class="pill pill-mut" href="#/inbox/${t.ticket}" title="Από ticket">${I.ticket}</a>` : ''}</td>
        <td style="width:120px"><span class="pill pill-mut">${esc(t.status)}</span></td>
        <td style="width:130px">${esc(t.assignee || '—')}</td>
        <td style="width:110px" class="${t.due && t.due < today() ? 'pill pill-bad' : 'mut'}">${t.due ? dShort(t.due) : '—'}</td>
      </tr>`).join('')}
    </tbody></table></div>`;
  c.innerHTML = `<div class="card"><div class="card-b" style="display:flex;gap:9px;align-items:center;padding:11px 16px;flex-wrap:wrap">
      <a class="btn btn-sm btn-o" href="#/units">← Όλα τα departments</a>
      <a class="btn btn-sm btn-o" href="#/inbox">${I.ticket} Tickets</a>
      <span class="mut" style="font-size:12.5px">Ομαδοποίηση ανά πελάτη &amp; έργο — τα έργα πελατών πρώτα.</span></div>
      <div class="card-b" style="padding:0 16px 12px;font-size:12.5px">
        <span class="mut">${I.tree} Το εξυπηρετούν:</span>
        ${d.dept.teams && d.dept.teams.length
          ? d.dept.teams.map(t => `<a class="pill pill-mut" href="#/teams" style="border-left:3px solid ${t.color}">${esc(t.name)}</a>`).join(' ')
          : '<span class="pill pill-warn">καμία ομάδα — οι υποψήφιοι για ανάθεση βγαίνουν από το WHMCS</span>'}
      </div></div>
    ${d.groups.length ? d.groups.map(grp).join('')
      : '<div class="card"><div class="card-b empty">Καμία ανοιχτή εργασία σε αυτό το department 🎉</div></div>'}`;
  $$('[data-task]').forEach(a => a.onclick = () => openTask(+a.dataset.task));
};

/* ═══════════ Πρότυπα υλοποίησης ═══════════
   Κάθε υλοποίηση έχει τα ίδια βήματα κάθε φορά. Τα γράφουμε μία φορά — με
   ομάδα, υπεύθυνο, ημέρες και ελέγχους ανά βήμα — και τα κλωνοποιούμε σε
   πελάτη όταν ξεκινά η δουλειά. Οι ημερομηνίες είναι ΜΕΡΕΣ ΑΠΟ ΤΗΝ ΕΝΑΡΞΗ,
   ώστε το ίδιο πρότυπο να δουλεύει οποιαδήποτε στιγμή. */
R.templates = async function () {
  setTop('Modules', 'Τα δικά μας προϊόντα — το καθένα με το checklist παράδοσής του');
  const c = $('#content');
  c.innerHTML = '<div class="skel" style="height:280px"></div>';
  const d = await api('templates');
  const st = R.templates._st = R.templates._st || {open: null};
  const depName = id => (d.depts.find(x => x.id === id) || {}).name || '—';
  const admName = id => (d.admins.find(x => x.id === id) || {}).name || '—';
  const days = n => n === 0 ? 'ημέρα 1' : `+${n} ημ.`;

  const stepRow = (tp, s2, i) => `<tr data-step="${s2.id}">
    <td style="width:26px" class="mut">${i + 1}</td>
    <td><b>${esc(s2.title)}</b>
      ${s2.descr ? `<div class="mut" style="font-size:11.5px;white-space:pre-wrap;margin-top:2px">${esc(s2.descr.slice(0, 160))}${s2.descr.length > 160 ? '…' : ''}</div>` : ''}
      ${s2.checks.length ? `<div class="tpl-checks">${s2.checks.map(x => `<span>☑ ${esc(x)}</span>`).join('')}</div>` : ''}</td>
    <td style="width:130px">${s2.dept ? `<span class="pill pill-mut">${esc(depName(s2.dept))}</span>` : '<span class="mut">—</span>'}</td>
    <td style="width:130px" class="mut">${s2.assignee ? esc(admName(s2.assignee)) : '—'}</td>
    <td style="width:150px" class="mut" style="white-space:nowrap">${days(s2.offStart)} → ${days(s2.offDeadline !== null ? s2.offDeadline : s2.offDue)}</td>
    <td style="width:70px" class="mut">${s2.est ? fmtMin(s2.est) : '—'}</td>
    ${d.canManage ? `<td style="width:80px">
      <button class="btn btn-sm btn-o" data-sedit="${tp.id}:${s2.id}">${I.edit}</button>
      <button class="btn btn-sm btn-o" data-sdel="${s2.id}" style="color:var(--bad)">✕</button></td>` : ''}</tr>`;

  const card = tp => `<div class="card" style="border-top:4px solid ${tp.color}">
    <div class="card-h" style="gap:9px">
      <b>${esc(tp.name)}</b>
      ${tp.productName ? `<span class="pill pill-info" title="Προϊόν WHMCS">${I.box} ${esc(tp.productName)}</span>` : '<span class="pill pill-mut" title="Δεν έχει δεθεί με προϊόν WHMCS">χωρίς προϊόν</span>'}
      ${tp.category ? `<span class="pill pill-mut">${esc(tp.category)}</span>` : ''}
      ${tp.active ? '' : '<span class="pill pill-mut">ανενεργό</span>'}
      <span class="kb-n">${tp.steps.length} βήματα</span>
      ${tp.checks.length ? `<span class="pill pill-ok" title="Ενέργειες παράδοσης">☑ ${tp.checks.length}</span>` : ''}
      ${tp.used ? `<span class="pill pill-mut" title="Σε πόσα έργα έχει μπει">${tp.used} υλοποιήσεις</span>` : ''}
      ${tp.budget ? `<span class="mut" style="font-size:11.5px">${fmtEur(tp.budget)}</span>` : ''}
      <span style="flex:1"></span>
      ${d.canClone && tp.active ? `<button class="btn btn-sm btn-p" data-clone="${tp.id}">${I.rocket} Έναρξη σε πελάτη</button>` : ''}
      ${d.canManage ? `<button class="btn btn-sm btn-o" data-tedit="${tp.id}">${I.edit}</button>` : ''}
      <button class="btn btn-sm btn-o" data-topen="${tp.id}">${st.open === tp.id ? '▴' : '▾'}</button>
    </div>
    ${st.open === tp.id ? `<div class="card-b">
      ${tp.descr ? `<div class="mut" style="font-size:12.5px;margin-bottom:9px;white-space:pre-wrap">${esc(tp.descr)}</div>` : ''}
      ${tp.checks.length ? `<div class="dlv-box">
        <b style="font-size:12px">${I.checkSquare} Checklist παράδοσης <span class="mut" style="font-weight:400">— υποχρεωτικό πριν κλείσει</span></b>
        <div class="tpl-checks" style="margin-top:6px">${tp.checks.map(x => `<span>☑ ${esc(x)}</span>`).join('')}</div></div>`
        : `<div class="mut" style="font-size:11.5px;margin-bottom:9px">Χωρίς checklist παράδοσης — πρόσθεσέ το από την επεξεργασία.</div>`}
      ${tp.steps.length ? `<table class="tbl"><thead><tr><th></th><th>Βήμα &amp; έλεγχοι</th><th>Ομάδα</th><th>Υπεύθυνος</th><th>Χρονισμός</th><th>Εκτ.</th>${d.canManage ? '<th></th>' : ''}</tr></thead>
        <tbody>${tp.steps.map((s2, i) => stepRow(tp, s2, i)).join('')}</tbody></table>`
        : '<div class="mut" style="font-size:12.5px">Κανένα βήμα ακόμη.</div>'}
      ${d.canManage ? `<button class="btn btn-o btn-sm" data-sadd="${tp.id}" style="margin-top:10px">${I.plus} Νέο βήμα</button>` : ''}
    </div>` : ''}</div>`;

  c.innerHTML = `
    <div class="card"><div class="card-b" style="font-size:12.5px;color:var(--mut);padding:12px 16px;line-height:1.7">
      <b>Module</b> = δικό μας προϊόν ή υποδομή (PharmacyOne, e-shop, VPS setup…) με τα βήματα που
      χρειάζονται για να παραδοθεί σε πελάτη. Κάθε βήμα έχει <b>ομάδα, υπεύθυνο, ημέρες από την έναρξη</b>
      και τους <b>ελέγχους</b> του. Ένα έργο πελάτη περιλαμβάνει <b>ένα ή πολλά</b> modules — και μέσα στο
      έργο, κάτω από κάθε module, ανοίγει το checklist του.
    </div></div>
    <div style="margin-bottom:12px;display:flex;gap:8px;flex-wrap:wrap">
      ${d.canManage ? `<button class="btn btn-p btn-sm" id="tplNew">${I.plus} Νέο module</button>` : ''}
      ${d.canClone && d.templates.some(t => t.active) ? `<button class="btn btn-o btn-sm" id="tplStart">${I.rocket} Νέα υλοποίηση σε πελάτη — πολλά modules</button>` : ''}
    </div>
    ${d.templates.length ? d.templates.map(card).join('')
      : '<div class="card"><div class="card-b empty">Κανένα module ακόμη — φτιάξε το πρώτο.</div></div>'}`;

  $$('[data-topen]').forEach(b => b.onclick = () => {
    st.open = st.open === +b.dataset.topen ? null : +b.dataset.topen; R.templates();
  });
  $$('[data-clone]').forEach(b => b.onclick = () => openClone(d.templates.find(x => x.id === +b.dataset.clone), d));
  const tplStart0 = $('#tplStart'); if (tplStart0) tplStart0.onclick = () => openClone(null, d);
  if (!d.canManage) return;

  const tplStart = $('#tplStart'); if (tplStart) tplStart.onclick = () => openClone(null, d);
  $('#tplNew').onclick = () => openTpl(null, d);
  $$('[data-tedit]').forEach(b => b.onclick = () => openTpl(d.templates.find(x => x.id === +b.dataset.tedit), d));
  $$('[data-sadd]').forEach(b => b.onclick = () => openStep(+b.dataset.sadd, null, d));
  $$('[data-sedit]').forEach(b => b.onclick = () => {
    const [tp, sid] = b.dataset.sedit.split(':').map(Number);
    openStep(tp, (d.templates.find(x => x.id === tp).steps.find(y => y.id === sid)), d);
  });
  $$('[data-sdel]').forEach(b => b.onclick = async () => {
    if (!(await cnpConfirm('Διαγραφή βήματος;', {danger: true, ok: I.trash + ' Διαγραφή'}))) return;
    await api('template_step_del', {id: +b.dataset.sdel}); toast('Διαγράφηκε'); R.templates();
  });
};

/* Πρότυπο: ταυτότητα */
function openTpl(tp, d) {
  closeDrawer();
  tp = tp || {name: '', descr: '', color: '#0090dd', budget: null, active: true, steps: [], productId: null, category: '', checks: []};
  const ovl = document.createElement('div'); ovl.className = 'ovl';
  const dr = document.createElement('div'); dr.className = 'drawer';
  dr.innerHTML = `
  <div class="drawer-h"><h2>${tp.id ? esc(tp.name) : 'Νέο module'}</h2><button class="drawer-x" id="dX">✕</button></div>
  <div class="drawer-b"><div class="card"><div class="card-b">
    <label class="lbl">Όνομα module</label>
    <input class="inp" id="tpN" value="${esc(tp.name)}" placeholder="π.χ. PharmacyOne, Στήσιμο e-shop, VPS setup">
    <div class="frow" style="margin-top:11px">
      <div><label class="lbl">Προϊόν WHMCS <span class="mut" style="font-weight:400">— τι το πουλάμε</span></label>
        <select class="inp" id="tpP"><option value="">— χωρίς σύνδεση —</option>
          ${(() => { let g = ''; return d.products.map(pr => {
            const head = pr.group !== g ? `${g ? '</optgroup>' : ''}<optgroup label="${esc(pr.group)}">` : '';
            g = pr.group;
            return head + `<option value="${pr.id}" ${pr.id === tp.productId ? 'selected' : ''}>${esc(pr.name)}</option>`;
          }).join('') + (g ? '</optgroup>' : ''); })()}</select></div>
      <div><label class="lbl">Κατηγορία <span class="mut" style="font-weight:400">— ελεύθερη</span></label>
        <input class="inp" id="tpK" value="${esc(tp.category || '')}" placeholder="π.χ. Λογισμικό, Υποδομή, Υπηρεσία"></div>
    </div>
    <label class="lbl" style="margin-top:11px">Τι περιλαμβάνει</label>
    <textarea class="inp" id="tpD" rows="4" placeholder="Σύντομη περιγραφή — τι παραδίδουμε και σε τι κατάσταση">${esc(tp.descr || '')}</textarea>
    <div class="frow" style="margin-top:11px">
      <div><label class="lbl">Χρώμα</label><input type="color" class="inp" id="tpC" value="${tp.color}" style="height:40px;padding:4px"></div>
      <div><label class="lbl">Ενδεικτικό budget €</label><input class="inp" id="tpB" value="${tp.budget ?? ''}" placeholder="π.χ. 2500"></div>
    </div>
    <label class="lbl" style="margin-top:14px">${I.checkSquare} Checklist παράδοσης — ένα ανά γραμμή</label>
    <div class="mut" style="font-size:11.5px;margin-bottom:5px">Οι ενέργειες που πρέπει να επιβεβαιωθούν για να θεωρηθεί παραδοτέο.
      Όταν το module ανατεθεί σε έργο, γίνονται εργασία <b>«Παράδοση: ${esc(tp.name || 'module')}»</b>
      που <b>δεν κλείνει</b> όσο μένει ατσέκαρη ενέργεια.</div>
    <textarea class="inp" id="tpCk" rows="7" placeholder="π.χ.&#10;Εγκατάσταση εφαρμογής&#10;Παραμετροποίηση αυτόματης ενημέρωσης Google&#10;Διασύνδεση με φαρμακαποθήκη&#10;Ρύθμιση αποστολής email&#10;Εκπαίδευση χρήστη & υπογραφή παράδοσης">${esc((tp.checks || []).join('\n'))}</textarea>
    <label style="display:flex;gap:7px;align-items:center;margin-top:11px;font-size:13px">
      <input type="checkbox" id="tpA" ${tp.active !== false ? 'checked' : ''}> Ενεργό — εμφανίζεται στην έναρξη νέας υλοποίησης</label>
    <div style="display:flex;gap:9px;margin-top:14px">
      <button class="btn btn-p" id="tpSave">Αποθήκευση</button>
      ${tp.id ? `<button class="btn btn-o" id="tpDel" style="color:var(--bad);margin-left:auto">${I.trash} Διαγραφή προτύπου</button>` : ''}
    </div>
  </div></div></div>`;
  document.body.append(ovl, dr);
  requestAnimationFrame(() => { ovl.classList.add('show'); dr.classList.add('show'); });
  $('#dX').onclick = () => closeDrawer();
  $('#tpSave', dr).onclick = async () => {
    if (!$('#tpN', dr).value.trim()) { toast('Δώσε όνομα', true); return; }
    await api('template_save', {id: tp.id || 0, name: $('#tpN', dr).value, descr: $('#tpD', dr).value,
      color: $('#tpC', dr).value, budget: $('#tpB', dr).value.trim(), off: !$('#tpA', dr).checked,
      product: +$('#tpP', dr).value || 0, category: $('#tpK', dr).value,
      checks: $('#tpCk', dr).value});
    toast('Αποθηκεύτηκε'); closeDrawer(); R.templates();
  };
  const del = $('#tpDel', dr);
  if (del) del.onclick = async () => {
    if (!(await cnpConfirm(`Διαγραφή του module «${tp.name}»; Τα έργα που το έχουν ήδη δεν επηρεάζονται.`,
      {danger: true, ok: I.trash + ' Διαγραφή'}))) return;
    await api('template_del', {id: tp.id}); toast('Διαγράφηκε'); closeDrawer(); R.templates();
  };
}

/* Πρότυπο: ένα βήμα, με τους ελέγχους του */
function openStep(tplId, s2, d) {
  closeDrawer();
  s2 = s2 || {title: '', descr: '', dept: null, assignee: null, offStart: 0, offDue: 1,
    offDeadline: null, est: null, prio: 0, checks: []};
  const ovl = document.createElement('div'); ovl.className = 'ovl';
  const dr = document.createElement('div'); dr.className = 'drawer';
  dr.innerHTML = `
  <div class="drawer-h"><h2>${s2.id ? 'Βήμα' : 'Νέο βήμα'}</h2><button class="drawer-x" id="dX">✕</button></div>
  <div class="drawer-b"><div class="card"><div class="card-b">
    <label class="lbl">Τι πρέπει να γίνει</label>
    <input class="inp" id="sT" value="${esc(s2.title)}" placeholder="π.χ. Στήσιμο περιβάλλοντος & DNS">
    <label class="lbl" style="margin-top:11px">Λεπτομέρειες εκτέλεσης</label>
    <textarea class="inp" id="sD" rows="6" placeholder="Αναλυτικά βήματα, παράμετροι, τι προσέχουμε…">${esc(s2.descr || '')}</textarea>
    <div class="frow" style="margin-top:11px">
      <div><label class="lbl">Ομάδα (department)</label><select class="inp" id="sDep"><option value="">— καμία —</option>
        ${d.depts.map(x => `<option value="${x.id}" ${x.id === s2.dept ? 'selected' : ''}>${esc(x.name)}</option>`).join('')}</select></div>
      <div><label class="lbl">Προτεινόμενος υπεύθυνος</label><select class="inp" id="sA"><option value="">— κανείς —</option>
        ${d.admins.map(x => `<option value="${x.id}" ${x.id === s2.assignee ? 'selected' : ''}>${esc(x.name)}</option>`).join('')}</select></div>
      <div><label class="lbl">Προτεραιότητα</label><select class="inp" id="sP">
        ${['Κανονική', 'Υψηλή', 'Κρίσιμη'].map((x, i) => `<option value="${i}" ${i === s2.prio ? 'selected' : ''}>${x}</option>`).join('')}</select></div>
      <div><label class="lbl">Εκτίμηση (λεπτά)</label><input class="inp" id="sE" type="number" min="0" value="${s2.est ?? ''}"></div>
    </div>
    <div class="mut" style="font-size:11.5px;margin-top:12px">Χρονισμός σε <b>ημέρες από την έναρξη</b> του έργου — έτσι το πρότυπο δουλεύει σε οποιαδήποτε ημερομηνία.</div>
    <div class="frow" style="margin-top:6px">
      <div><label class="lbl">Έναρξη (ημέρα)</label><input class="inp" id="sS" type="number" min="0" value="${s2.offStart}"></div>
      <div><label class="lbl">Λήξη (ημέρα)</label><input class="inp" id="sU" type="number" min="0" value="${s2.offDue}"></div>
      <div><label class="lbl">Deadline (ημέρα) <span class="mut" style="font-weight:400">— προαιρετικό</span></label>
        <input class="inp" id="sL" type="number" min="0" value="${s2.offDeadline ?? ''}"></div>
    </div>
    <label class="lbl" style="margin-top:13px">${I.checkSquare} Έλεγχοι στο τέλος — ένας ανά γραμμή</label>
    <div class="mut" style="font-size:11.5px;margin-bottom:5px">Γίνονται checklist της εργασίας· τα συμπληρώνει αυτός που ελέγχει.</div>
    <textarea class="inp" id="sC" rows="5" placeholder="π.χ.&#10;Επιβεβαιώθηκε πρόσβαση από τον πελάτη&#10;Πάρθηκε backup&#10;Ενημερώθηκε η τεκμηρίωση">${esc((s2.checks || []).join('\n'))}</textarea>
    <div style="display:flex;gap:9px;margin-top:14px"><button class="btn btn-p" id="sSave">Αποθήκευση</button></div>
  </div></div></div>`;
  document.body.append(ovl, dr);
  requestAnimationFrame(() => { ovl.classList.add('show'); dr.classList.add('show'); });
  $('#dX').onclick = () => closeDrawer();
  $('#sSave', dr).onclick = async () => {
    if (!$('#sT', dr).value.trim()) { toast('Δώσε τίτλο στο βήμα', true); return; }
    await api('template_step_save', {id: s2.id || 0, template: tplId,
      title: $('#sT', dr).value, descr: $('#sD', dr).value,
      dept: +$('#sDep', dr).value || 0, assignee: +$('#sA', dr).value || 0,
      prio: +$('#sP', dr).value, est: +$('#sE', dr).value || 0,
      offStart: +$('#sS', dr).value || 0, offDue: +$('#sU', dr).value || 0,
      offDeadline: $('#sL', dr).value, checks: $('#sC', dr).value});
    toast('Αποθηκεύτηκε'); closeDrawer(); R.templates();
  };
}

/* Νέα υλοποίηση σε πελάτη: ένα ή πολλά modules → έργο με τα βήματά τους.
   Με tp δοσμένο, το module είναι προεπιλεγμένο· χωρίς, διαλέγεις από όλα. */
function openClone(tp, d) {
  closeDrawer();
  const active = d.templates.filter(x => x.active && x.steps.length);
  const ovl = document.createElement('div'); ovl.className = 'ovl';
  const dr = document.createElement('div'); dr.className = 'drawer';
  dr.innerHTML = `
  <div class="drawer-h"><h2>${I.rocket} Νέα υλοποίηση σε πελάτη</h2><button class="drawer-x" id="dX">✕</button></div>
  <div class="drawer-b"><div class="card"><div class="card-b">
    <label class="lbl">Πελάτης <span class="mut" style="font-weight:400">— γράψε και <b>διάλεξε</b> από τη λίστα</span></label>
    <input class="inp" id="clCli" autocomplete="off" placeholder="όνομα, επωνυμία ή email…">
    <input type="hidden" id="clCliId"><div id="clCliS" class="mut" style="font-size:11px;margin-top:3px"></div>

    <label class="lbl" style="margin-top:13px">${I.box} Modules που περιλαμβάνει η υλοποίηση</label>
    <div class="mut" style="font-size:11.5px;margin-bottom:6px">Κάθε module φέρνει τα βήματά του ως εργασίες, με το checklist του.</div>
    <div class="mod-pick">${active.map(m => `<label class="mod-opt ${tp && tp.id === m.id ? 'on' : ''}">
      <input type="checkbox" data-mod="${m.id}" ${tp && tp.id === m.id ? 'checked' : ''}>
      <span class="dot" style="background:${m.color};width:9px;height:9px"></span>
      <b>${esc(m.name)}</b><small class="mut">${m.steps.length} βήματα${m.checks.length ? ' · ☑ ' + m.checks.length + ' παράδοσης' : ''}${m.budget ? ' · ' + fmtEur(m.budget) : ''}</small></label>`).join('')
      || '<div class="mut">Κανένα ενεργό module με βήματα.</div>'}</div>

    <div class="frow" style="margin-top:12px">
      <div><label class="lbl">Ονομασία έργου <span class="mut" style="font-weight:400">— κενό = αυτόματη</span></label><input class="inp" id="clName" placeholder="<modules> — <πελάτης>"></div>
      <div><label class="lbl">Ημερομηνία έναρξης</label><input type="date" class="inp" id="clStart" value="${today()}"></div>
      <div><label class="lbl">Υπεύθυνος έργου</label><select class="inp" id="clMgr">
        ${d.admins.map(x => `<option value="${x.id}" ${x.id === S.boot.me.id ? 'selected' : ''}>${esc(x.name)}</option>`).join('')}</select></div>
    </div>
    <div class="mut" style="font-size:11.5px;margin-top:9px" id="clSum">—</div>
    <div style="display:flex;gap:9px;margin-top:14px"><button class="btn btn-p" id="clGo">${I.rocket} Δημιουργία έργου</button></div>
  </div></div></div>`;
  document.body.append(ovl, dr);
  requestAnimationFrame(() => { ovl.classList.add('show'); dr.classList.add('show'); });
  $('#dX').onclick = () => closeDrawer();
  window.CNP.clientAuto('clCli', null, 'clCliId', 'clCliS');
  const picked = () => $$('[data-mod]', dr).filter(x => x.checked).map(x => +x.dataset.mod);
  const summary = () => {
    const ids = picked(), ms = active.filter(m => ids.includes(m.id));
    const steps = ms.reduce((a, m) => a + m.steps.length + (m.checks.length ? 1 : 0), 0);
    const last = ms.reduce((a, m) => Math.max(a, ...m.steps.map(s2 => s2.offDeadline !== null ? s2.offDeadline : s2.offDue)), 0);
    const s0 = $('#clStart', dr).value;
    const end = s0 && ms.length ? dFull(new Date(new Date(s0 + 'T12:00:00').getTime() + last * 86400000).toISOString().slice(0, 10)) : '—';
    $('#clSum', dr).innerHTML = ms.length
      ? `<b>${ms.length}</b> module${ms.length === 1 ? '' : 's'} · <b>${steps}</b> εργασίες · παράδοση <b>${end}</b> (+${last} ημ.)`
      : '<span style="color:var(--warn)">Διάλεξε τουλάχιστον ένα module.</span>';
    $$('.mod-opt', dr).forEach(l => l.classList.toggle('on', l.querySelector('input').checked));
  };
  $$('[data-mod]', dr).forEach(c => c.onchange = summary);
  $('#clStart', dr).onchange = summary; summary();
  $('#clGo', dr).onclick = async () => {
    const cid = +$('#clCliId', dr).value || 0, ids = picked();
    if (!cid) { toast('Διάλεξε πελάτη από τη λίστα', true); $('#clCli', dr).focus(); return; }
    if (!ids.length) { toast('Διάλεξε τουλάχιστον ένα module', true); return; }
    const r = await api('project_add_modules', {project: 0, client: cid, modules: ids, start: $('#clStart', dr).value,
      name: $('#clName', dr).value.trim(), manager: +$('#clMgr', dr).value || 0}).catch(e => ({err: e.message}));
    if (r.err) { toast(r.err, true); return; }
    toast(`Έργο με ${r.modules} module${r.modules === 1 ? '' : 's'} και ${r.tasks} εργασίες`);
    closeDrawer(); S.boot = await api('boot'); go('board', r.project);
  };
}

/* Ανάθεση δικών μας προϊόντων/modules σε υπάρχον έργο πελάτη.
   Καλείται από την κεφαλίδα του board και από τη φόρμα του έργου, ώστε η
   ανάθεση να γίνεται εκεί που δουλεύεις και όχι μόνο στην επεξεργασία. */
async function openAssignModules(projectId, onDone) {
  closeDrawer();
  const md = await api('project_modules&project=' + projectId).catch(() => null);
  if (!md) { toast('Δεν φορτώθηκαν τα modules', true); return; }
  const have = md.modules.map(m => m.id);
  const free = md.available.filter(a => !have.includes(a.id));
  const ovl = document.createElement('div'); ovl.className = 'ovl';
  const dr = document.createElement('div'); dr.className = 'drawer';
  dr.innerHTML = `
  <div class="drawer-h"><h2>${I.box} Ανάθεση προϊόντων στο έργο</h2><button class="drawer-x" id="dX">✕</button></div>
  <div class="drawer-b"><div class="card"><div class="card-b">
    ${md.modules.length ? `<label class="lbl">Ήδη ανατεθειμένα</label>
      <div class="mod-pick" style="margin-bottom:14px">${md.modules.map(m => `<div class="mod-opt on" style="cursor:default">
        <span class="dot" style="background:${m.color};width:9px;height:9px"></span>
        <b>${esc(m.name)}</b>
        ${m.product ? `<small class="mut">${esc(m.product)}</small>` : ''}
        <span class="pill ${m.done === m.total && m.total ? 'pill-ok' : 'pill-mut'}">${m.done}/${m.total}</span></div>`).join('')}</div>` : ''}
    <label class="lbl">${free.length ? 'Πρόσθεσε προϊόντα / modules' : 'Δεν μένει άλλο module'}</label>
    <div class="mut" style="font-size:11.5px;margin-bottom:6px">Κάθε module φέρνει τα βήματα παράδοσής του ως εργασίες, με το checklist του.</div>
    <div class="mod-pick">${free.map(a => `<label class="mod-opt">
      <input type="checkbox" data-am="${a.id}">
      <span class="dot" style="background:${a.color};width:9px;height:9px"></span>
      <b>${esc(a.name)}</b></label>`).join('') || '<div class="mut">Όλα τα ενεργά modules είναι ήδη μέσα.</div>'}</div>
    ${free.length ? `<div class="frow" style="margin-top:12px">
      <div><label class="lbl">Έναρξη των βημάτων</label><input type="date" class="inp" id="amStart" value="${today()}"></div>
    </div>
    <div style="display:flex;gap:9px;margin-top:12px"><button class="btn btn-p" id="amGo">${I.plus} Ανάθεση</button></div>` : ''}
  </div></div></div>`;
  document.body.append(ovl, dr);
  requestAnimationFrame(() => { ovl.classList.add('show'); dr.classList.add('show'); });
  $('#dX').onclick = () => closeDrawer();
  $$('[data-am]', dr).forEach(c => c.onchange = () => c.closest('.mod-opt').classList.toggle('on', c.checked));
  const go = $('#amGo', dr);
  if (go) go.onclick = async () => {
    const ids = $$('[data-am]', dr).filter(x => x.checked).map(x => +x.dataset.am);
    if (!ids.length) { toast('Διάλεξε τουλάχιστον ένα', true); return; }
    const r = await api('project_add_modules', {project: projectId, modules: ids,
      start: $('#amStart', dr).value}).catch(e => ({err: e.message}));
    if (r.err) { toast(r.err, true); return; }
    toast(`Ανατέθηκαν ${r.modules} module${r.modules === 1 ? '' : 's'} — ${r.tasks} εργασίες`);
    closeDrawer();
    if (onDone) { onDone(); }
  };
}
window.openAssignModules = openAssignModules;
