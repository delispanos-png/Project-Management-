/* ═══════════ CloudOn Projects — Gantt (GoodDay-style δομή) ═══════════ */
'use strict';
const {S, api, esc, fmtMin, dShort, today, toast, setTop, openTask, adminIni, adminName, I, go, $, $$} = window.CNP;
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

