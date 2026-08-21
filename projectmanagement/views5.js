/* ═══════════ CloudOn Projects — Gantt (GoodDay-style δομή) ═══════════ */
'use strict';
const {S, api, esc, fmtMin, fmtEur, suStat, dShort, dFull, today, toast, setTop, openTask, adminIni, adminName, cnpPrompt, cnpConfirm, cnpDialog, I, go, $, $$} = window.CNP;
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
