/* ═══════════ CloudOn Projects — keyboard-first + views (Κύμα 1) ═══════════ */
'use strict';
const {S, api, esc, rteHtml, rteVal, suStat, fmtMin, dShort, tShort, dFull, today, toast, setTop, go,
  adminName, adminIni, statusOf, typeOf, openTask, closeDrawer, cnpConfirm, cnpPrompt, cnpDenied, cnpCan,
  cnpMsgHtml, cnpWireMsgLinks, cnpIsMine, cnpHolder, cnpSearch, cnpSkel, fChip, fSel, fAdd, fWire, fOne, I, stPill, $, $$} = window.CNP;
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
    if (k === 'v') { e.preventDefault(); quickCall(); }   // v = τηλεφωνική επικοινωνία
    if (k === '?') { e.preventDefault(); showKeys(); }
  });
  function showKeys() {
    closeDrawer();
    const ovl = document.createElement('div'); ovl.className = 'ovl show';
    ovl.innerHTML = `<div class="pal-box" style="margin:14vh auto 0;max-width:420px" onclick="event.stopPropagation()">
      <div class="pop-h" style="padding:14px 18px">⌨️ Συντομεύσεις</div>
      <div style="padding:12px 18px 18px;font-size:13px;line-height:2">
        <b>Ctrl+K</b> — αναζήτηση παντού<br><b>n</b> — νέο task · <b>v</b> — καταγραφή κλήσης<br>
        <b>g</b> μετά <b>m</b> — Η μέρα μου · <b>g i</b> — Tickets · <b>g b</b> — Board<br>
        <b>g l</b> — Λίστα · <b>g c</b> — CRM · <b>g o</b> — Προσφορές · <b>g t</b> — Χρόνος<br>
        <b>g k</b> — KPI · <b>g p</b> — Projects · <b>g s</b> — Ρυθμίσεις<br>
        <b>Esc</b> — κλείσιμο πάνελ · <b>?</b> — αυτή η λίστα</div></div>`;
    document.body.appendChild(ovl);
  }
  window.CNP.showKeys = showKeys;
})();

/* ═════════ Quick «Νέο task» (πλήκτρο n) ═════════ */
/* ═════════ ⌘ ΝΕΑ ΕΡΓΑΣΙΑ — πρώτα το θέμα, μετά η πρόθεση ═════════
   Η παλιά φόρμα ρωτούσε «σε ποιο έργο / σε ποιο department» πριν προλάβεις να
   γράψεις τι θέλεις. Λάθος σειρά: πρώτα έρχεται η σκέψη, μετά το πού μπαίνει.
   Τώρα γράφεις το θέμα και από κάτω διαλέγεις ΤΙ γίνεται με αυτό — και μόνο
   τότε, αν χρειάζεται, διαλέγεις έργο / πελάτη / συνάδελφο. */
function quickNew() {
  closeDrawer();
  const me = S.boot.me;
  const projects = S.boot.projects || [];
  const depts = (S.boot.depts || []).filter(d => d.id);
  /* Το «δικό μου» department: αυτό όπου ανήκω — αλλιώς το πρώτο διαθέσιμο. */
  const myDept = depts.find(d => (d.members || []).includes(me.id)) || depts[0] || null;
  const curProject = S.view === 'board' && S.project
    ? projects.find(p => p.id === S.project) : null;

  /* ── Δύο κόσμοι: δουλειά ΓΙΑ πελάτη ή εσωτερική διαδικασία / R&D / βελτίωση.
     Ο διακόπτης πάνω-πάνω καθορίζει τι προσφέρεται από κάτω: στο «εσωτερικό» δεν
     υπάρχει «κάλεσε πελάτη» και τα έργα είναι μόνο τα δικά μας. Θυμάται. ── */
  let scope = 'client';
  try { scope = localStorage.getItem('cnpQnScope') === 'internal' ? 'internal' : 'client'; } catch (e) {}
  const isInternalProj = p => !p.client;   // το boot δεν φέρνει kind — εσωτερικό = χωρίς πελάτη
  /* Πελάτες: τους αντλούμε από τα έργα — έτσι ξέρουμε πάντα πού θα μπει η κλήση. */
  const clients = [];
  { const seen = {};
    projects.filter(p => p.client && p.clientName).forEach(p => {
      if (!seen[p.client]) { seen[p.client] = {id: p.client, name: p.clientName, projects: []}; clients.push(seen[p.client]); }
      seen[p.client].projects.push(p);
    });
    clients.sort((a, b) => a.name.localeCompare(b.name, 'el'));
  }
  const mates = (S.boot.admins || []).filter(a => a.id !== me.id
    && !/support team|\bbot\b/i.test(a.name) && String(a.name).trim() !== 'Cloud On');

  /* ── Οι προθέσεις ───────────────────────────────────────────────────────── */
  const INTENTS = [
    {k: 'project', ic: I.folder, col: '#0090dd', title: 'Εργασία σε έργο',
     hint: () => (curProject && (scope === 'internal') === isInternalProj(curProject)) ? 'προτείνεται: ' + curProject.name
       : (scope === 'internal' ? 'διάλεξε εσωτερικό έργο (R&D, βελτίωση, διαδικασία)' : 'διάλεξε έργο πελάτη'), need: 'project'},
    {k: 'call', ic: I.phone, col: '#16a26a', title: 'Κάλεσε πελάτη γι᾽ αυτό',
     hint: () => 'διάλεξε πελάτη', need: 'client'},
    {k: 'mine', ic: I.play, col: '#e0552b', title: 'Θα το κάνω εγώ, τώρα',
     hint: () => 'ανατίθεται σε σένα και ξεκινά ο χρόνος', need: null},
    {k: 'todo', ic: I.checkSquare, col: '#7b5cd6', title: 'Κράτα το για μένα',
     hint: () => 'δική σου εργασία, χωρίς χρονόμετρο', need: null},
    {k: 'assign', ic: I.users, col: '#e0a020', title: 'Ανάθεσέ το σε συνάδελφο',
     hint: () => 'διάλεξε ποιον', need: 'mate'},
  ];
  /* Τι ΔΕΝ γίνεται και γιατί — φαίνεται γκρι με τον λόγο, δεν εξαφανίζεται σιωπηλά (QA C3/A5).
     Εργασία σε έργο ή ανάθεση σε άλλον = «Board: επεξεργασία»· δική μου = για όλους. */
  const canBoard = cnpCan('projects.board.edit');
  const blocked = it => {
    if (!canBoard && (it.k === 'project' || it.k === 'assign' || it.k === 'call')) { return 'χρειάζεται «Board: επεξεργασία» — μπορείς μόνο δική σου εργασία'; }
    if (it.k === 'call' && scope !== 'client') { return 'μόνο σε «Έργο πελάτη»'; }
    if (it.k === 'call' && !clients.length) { return 'δεν υπάρχει έργο πελάτη για να δεθεί η κλήση — φτιάξε πρώτα έργο'; }
    return '';
  };
  const intentsFor = () => INTENTS.filter(x => x.k !== 'call' || scope === 'client');

  const ovl = document.createElement('div');
  ovl.className = 'ovl show';
  ovl.innerHTML = `<div class="qn-box" onclick="event.stopPropagation()" role="dialog" aria-label="Νέα εργασία">
    <div class="qn-top">
      <span class="qn-ic">${I.sparkle}</span>
      <input class="qn-t" id="qnT" placeholder="Τι πρέπει να γίνει;" autocomplete="off" maxlength="200">
    </div>
    <div class="qn-scope" id="qnScope">
      <button type="button" class="kp qn-kp${scope === 'client' ? ' on' : ''}" data-scope="client">
        <span class="kp-h">${I.rocket}<b>Έργο πελάτη</b></span>
        <span class="kp-d">Δουλειά ΓΙΑ πελάτη — μπαίνει στην καρτέλα του, χρεώνεται</span></button>
      <button type="button" class="kp qn-kp${scope === 'internal' ? ' on' : ''}" data-scope="internal">
        <span class="kp-h">${I.box}<b>Εσωτερικό / R&amp;D</b></span>
        <span class="kp-d">Δική μας διαδικασία, βελτίωση, επένδυση — χωρίς πελάτη</span></button>
    </div>
    <div class="qn-crumb" id="qnCrumb" hidden></div>
    <div class="qn-lbl" id="qnLbl">Τι γίνεται με αυτό;</div>
    <div class="qn-list" id="qnList"></div>
    <div class="qn-foot">
      <span><kbd>↑</kbd><kbd>↓</kbd> κινήσου</span>
      <span><kbd>⏎</kbd> διάλεξε</span>
      <span><kbd>esc</kbd> πίσω</span>
      <span class="qn-f-r" id="qnErr"></span>
    </div>
  </div>`;
  document.body.appendChild(ovl);
  ovl.onclick = () => close();
  const close = () => { ovl.remove(); document.removeEventListener('keydown', onKey, true); };

  const inp = $('#qnT', ovl), listEl = $('#qnList', ovl), lblEl = $('#qnLbl', ovl),
    crumbEl = $('#qnCrumb', ovl), errEl = $('#qnErr', ovl);
  let step = 'intent';      // intent → pick → (pickProject, αν ο πελάτης έχει πολλά έργα)
  let intent = null;
  let pickedClient = null;  // όταν έχουμε μπει στα έργα ενός πελάτη
  let sub2 = '';            // δεύτερο ψίχουλο (π.χ. το όνομα του πελάτη)
  let rows = [];            // {label, sub, ic, col, on}
  let cur = 0;
  let filter = '';

  const say = (m, bad) => { errEl.textContent = m || ''; errEl.className = 'qn-f-r' + (bad ? ' bad' : ''); };
  $$('[data-scope]', ovl).forEach(b => b.onclick = () => {
    scope = b.dataset.scope;
    try { localStorage.setItem('cnpQnScope', scope); } catch (e) {}
    $$('[data-scope]', ovl).forEach(x => x.classList.toggle('on', x.dataset.scope === scope));
    if (step === 'intent') { const keep = inp.value; showIntents(); inp.value = keep; }
    else { inp.value = subject; showIntents(); }
  });

  const paint = () => {
    listEl.innerHTML = rows.length ? rows.map((r, i) => `
      <button type="button" class="qn-row${i === cur ? ' on' : ''}${r.dis ? ' dis' : ''}" data-i="${i}" ${r.dis ? 'title="' + esc(r.sub) + '"' : ''}>
        <span class="qn-r-ic" style="--c:${r.col || '#8595ac'}">${r.ic || ''}</span>
        <span class="qn-r-t"><b>${esc(r.label)}</b>${r.sub ? `<span class="mut">${esc(r.sub)}</span>` : ''}</span>
        ${r.tag ? `<span class="qn-r-tag">${esc(r.tag)}</span>` : ''}
        <span class="qn-r-k">⏎</span>
      </button>`).join('')
      : `<div class="qn-empty">Κανένα αποτέλεσμα για «${esc(filter)}»</div>`;
    $$('.qn-row', listEl).forEach(b => {
      b.onmouseenter = () => { cur = +b.dataset.i; markCur(); };
      b.onclick = () => { cur = +b.dataset.i; run(); };
    });
    const on = listEl.querySelector('.qn-row.on');
    if (on) { on.scrollIntoView({block: 'nearest'}); }
  };
  const markCur = () => $$('.qn-row', listEl).forEach((b, i) => b.classList.toggle('on', i === cur));

  /* ── Βήμα 1: οι προθέσεις ── */
  const showIntents = () => {
    step = 'intent'; intent = null; filter = ''; cur = 0;
    crumbEl.hidden = true;
    lblEl.textContent = 'Τι γίνεται με αυτό;';
    rows = intentsFor().map(it => { const why = blocked(it); return {label: it.title, sub: why || it.hint(), ic: it.ic, col: why ? '#8595ac' : it.col, dis: !!why, it}; });
    paint();
    inp.focus();
  };

  /* ── Βήμα 2: ο στόχος (έργο / πελάτης / συνάδελφος) ── */
  const showPick = (it, client) => {
    step = 'pick'; intent = it; filter = ''; cur = 0;
    pickedClient = client || null;
    sub2 = client ? client.name : '';
    crumbEl.hidden = false;
    crumbEl.innerHTML = `<span class="qn-cr" style="--c:${it.col}">${it.ic} ${esc(it.title)}</span>
      ${sub2 ? `<span class="qn-cr2">${esc(sub2)}</span>` : ''}
      <span class="qn-subj" title="${esc(subject)}">${esc(subject)}</span>
      <button type="button" class="qn-cr-x" id="qnBack">✕ άλλαξε</button>`;
    $('#qnBack', ovl).onclick = () => { inp.value = subject; showIntents(); };
    lblEl.textContent = pickedClient ? 'Σε ποιο έργο του πελάτη;'
      : it.need === 'project' ? 'Σε ποιο έργο;'
      : it.need === 'client' ? 'Ποιον πελάτη;' : 'Σε ποιον;';
    inp.placeholder = pickedClient ? 'Ψάξε έργο…'
      : it.need === 'project' ? 'Ψάξε έργο ή πελάτη…'
      : it.need === 'client' ? 'Ψάξε πελάτη…' : 'Ψάξε συνάδελφο…';
    inp.value = '';
    inp.focus();
    buildPick();
  };

  const norm = x => String(x || '').toLowerCase();
  const buildPick = () => {
    const q = norm(filter);
    if (intent.need === 'project') {
      /* Στο «εσωτερικό» μόνο τα δικά μας έργα· στο «έργο πελάτη» μόνο έργα πελατών. */
      let list = projects.filter(p => scope === 'internal' ? isInternalProj(p) : !isInternalProj(p));
      if (!list.length && scope === 'internal') { list = projects.filter(isInternalProj); }
      /* Το έργο που κοιτάς τώρα πάει πρώτο — τις περισσότερες φορές αυτό θέλεις. */
      if (curProject && !q && list.some(p => p.id === curProject.id)) { list = [curProject].concat(list.filter(p => p.id !== curProject.id)); }
      rows = list
        .filter(p => !q || norm(p.name).includes(q) || norm(p.clientName).includes(q))
        .slice(0, 60)
        .map(p => ({label: p.name, sub: p.clientName || 'εσωτερικό / R&D', ic: I.folder, col: p.color || '#0090dd',
          tag: (curProject && p.id === curProject.id && !q) ? 'εδώ είσαι' : '',
          go: {project: p.id}}));
    } else if (intent.need === 'client') {
      if (pickedClient) {
        /* Με πολλά έργα δεν μαντεύουμε — ρωτάμε. Ένα «θα μπει στο 3CX» επειδή
           ήταν πρώτο αλφαβητικά είναι χειρότερο από μια ερώτηση. */
        rows = pickedClient.projects
          .filter(pr => !q || norm(pr.name).includes(q))
          .map(pr => ({label: pr.name, sub: pickedClient.name, ic: I.folder, col: pr.color || '#0090dd',
            go: {project: pr.id, client: pickedClient.name}}));
      } else {
        rows = clients
          .filter(c => !q || norm(c.name).includes(q))
          .slice(0, 60)
          .map(c => ({label: c.name, ic: I.building, col: '#16a26a',
            sub: c.projects.length === 1 ? 'έργο: ' + c.projects[0].name
              : c.projects.length + ' έργα — θα ρωτήσω σε ποιο',
            client: c,
            go: c.projects.length === 1 ? {project: c.projects[0].id, client: c.name} : null}));
      }
    } else {
      rows = mates
        .filter(a => !q || norm(a.name).includes(q))
        .slice(0, 60)
        .map(a => ({label: a.name, ic: I.user, col: '#e0a020', go: {assignee: a.id}}));
    }
    cur = 0;
    paint();
  };

  /* ── Δημιουργία ── */
  let creating = false;   // διπλό ⏎ = ΜΙΑ εργασία, όχι δύο
  const create = async (go, opts) => {
    if (creating) { return; }
    const title = inp0();
    if (!title) { say('Γράψε πρώτα τι πρέπει να γίνει', true); inp.focus(); return; }
    const body = {title, status: 0, internal: scope === 'internal' ? 1 : 0};
    if (go.project) { body.project = go.project; }
    if (go.assignee) { body.assignee = go.assignee; }
    if (opts && opts.mine) { body.assignee = me.id; }
    if (opts && opts.start) { body.start = 1; }
    if (go.client) { body.title = '☎ Κάλεσε ' + go.client + ' — ' + title; }
    /* Χωρίς έργο, η εργασία πρέπει τουλάχιστον να ανήκει σε department. */
    if (!body.project) {
      if (!myDept) { say('Δεν ανήκεις σε department — διάλεξε έργο', true); return; }
      body.dept = myDept.id;
    }
    say('Δημιουργία…'); creating = true;
    const r = await api('quick_task', body).catch(e => ({err: e.message}));
    creating = false;
    if (r.err) { say(r.err, true); return; }
    close();
    toast(r.started ? '▶ Δημιουργήθηκε — ο χρόνος τρέχει' : 'Δημιουργήθηκε ✓');
    /* Ανοίγει ως ΠΡΟΧΕΙΡΟ: αν κλείσει με ✕ χωρίς αποθήκευση, ρωτά «να κρατηθεί;» — «Όχι» τη σβήνει. */
    openTask(r.id, 0, {fresh: true});
  };
  /* Ο τίτλος κρατιέται χωριστά: στο βήμα 2 το ίδιο πεδίο γίνεται αναζήτηση. */
  let subject = '';
  const inp0 = () => (step === 'intent' ? inp.value.trim() : subject);

  const run = () => {
    const r = rows[cur];
    if (!r) { return; }
    if (step === 'intent') {
      if (r.dis) { say(r.sub, true); return; }
      if (!inp.value.trim()) { say('Γράψε πρώτα τι πρέπει να γίνει', true); inp.focus(); return; }
      subject = inp.value.trim();
      const it = r.it;
      if (!it.need) {
        create({}, {mine: true, start: it.k === 'mine'});
        return;
      }
      /* «Εργασία σε έργο» ενώ είσαι μέσα σε έργο: δεν έχει νόημα δεύτερη ερώτηση
         αν δεν τη θέλεις — πατάς ⏎ δύο φορές και τελείωσε. */
      showPick(it);
      return;
    }
    /* Πελάτης με πολλά έργα → ένα ακόμη σκαλί, δεν μαντεύουμε. */
    if (!r.go && r.client) { showPick(intent, r.client); return; }
    create(r.go, {});
  };

  const onKey = e => {
    if (!document.body.contains(ovl)) { document.removeEventListener('keydown', onKey, true); return; }
    if (e.key === 'Escape') {
      e.preventDefault(); e.stopPropagation();
      if (step === 'pick' && pickedClient) { showPick(intent); }
      else if (step === 'pick') { inp.value = subject; showIntents(); }
      else { close(); }
      return;
    }
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      e.preventDefault();
      if (!rows.length) { return; }
      cur = (cur + (e.key === 'ArrowDown' ? 1 : -1) + rows.length) % rows.length;
      markCur();
      const on = listEl.querySelector('.qn-row.on'); if (on) { on.scrollIntoView({block: 'nearest'}); }
      return;
    }
    if (e.key === 'Enter') { e.preventDefault(); run(); }
  };
  document.addEventListener('keydown', onKey, true);

  inp.oninput = () => {
    say('');
    if (step === 'pick') { filter = inp.value.trim(); buildPick(); }
    else { rows = rows.map(r => Object.assign(r, {sub: r.dis ? r.sub : r.it.hint()})); paint(); }
  };
  showIntents();
}
window.CNP.quickNew = quickNew;

/* ═════════ ☎ Καταγραφή κλήσης (πλήκτρο τ / t με shift) ═════════
   Χτύπησε το τηλέφωνο, το έκλεισες. Σε δεκαπέντε δευτερόλεπτα μένει γραπτό
   ποιος πήρε, τι ζήτησε, και **τι γίνεται με αυτό** — γιατί μια κλήση χωρίς
   συνέχεια είναι σημείωση που θα χαθεί. Ο χρόνος της κλήσης, αν είναι
   χρεώσιμος, περνάει από την ίδια μηχανή κάλυψης με κάθε άλλη εργασία. */
function quickCall(pre) {
  if (!cnpCan('clients.calls')) { toast('Δεν έχεις δικαίωμα καταγραφής κλήσης', true); return; }
  closeDrawer();
  const canTask = cnpCan('projects.board');
  const canTk = cnpCan('support.tickets');
  const who = {type: null, id: 0, name: '', phone: ''};
  let picked = 0;               // η πραγματική κλήση του PBX, αν διαλέχθηκε
  const depts = (S.boot.depts || []).filter(d => d.id);
  const ovl = document.createElement('div');
  ovl.className = 'ovl show';
  ovl.innerHTML = `<div class="pal-box qc-box" onclick="event.stopPropagation()">
    <div class="qc-h">
      <b>${I.phone} Καταγραφή κλήσης</b>
      <div class="td-seg qc-dir">
        <button data-dir="in" class="on">Εισερχόμενη</button>
        <button data-dir="out">Εξερχόμενη</button>
      </div>
    </div>
    <div class="qc-b">
      <div id="qcReal" hidden></div>
      <label class="lbl">Ποιος πήρε</label>
      <input class="inp" id="qcWho" placeholder="Όνομα, επωνυμία ή αριθμός τηλεφώνου…" autocomplete="off">
      <div id="qcPick"></div>
      <div id="qcSel" class="qc-sel" hidden></div>

      <label class="lbl" style="margin-top:12px">Τι ζήτησε <span class="mut" style="font-weight:400">— μία γραμμή</span></label>
      <input class="inp" id="qcSum" placeholder="π.χ. Δεν στέλνει email από το τιμολόγιο" autocomplete="off">

      <label class="lbl" style="margin-top:12px">Λεπτομέρειες <span class="mut" style="font-weight:400">— προαιρετικά</span></label>
      <textarea class="inp" id="qcDet" rows="2" placeholder="Ό,τι ειπώθηκε και αξίζει να θυμάσαι"></textarea>

      <div class="qc-row">
        <div><label class="lbl">Διάρκεια</label>
          <div style="display:flex;gap:5px;align-items:center">
            <input class="inp" id="qcMin" type="number" min="0" max="600" value="0" style="width:74px">
            <span class="mut" style="font-size:12px">λεπτά</span>
            ${[5, 10, 15, 30].map(m => `<button class="btn btn-o btn-sm qc-m" data-m="${m}">${m}΄</button>`).join('')}
          </div></div>
        <label class="qc-bill mut"><input type="checkbox" id="qcBill"> χρεώσιμος χρόνος</label>
      </div>

      <label class="lbl" style="margin-top:12px">Και μετά;</label>
      <div class="qc-then">
        <button class="qc-t on" data-then="none">${I.eye} Μόνο καταγραφή</button>
        ${canTask ? `<button class="qc-t" data-then="task">${I.checkSquare} Εργασία</button>` : ''}
        ${canTk ? `<button class="qc-t" data-then="ticket">${I.ticket} Ticket</button>` : ''}
      </div>
      <div id="qcExtra"></div>

      <label class="lbl" style="margin-top:12px">Υπενθύμιση <span class="mut" style="font-weight:400">— προαιρετικά</span></label>
      <input class="inp" id="qcFup" type="date" style="width:170px">
    </div>
    <div class="qc-f">
      <span class="mut" id="qcHint" style="font-size:11.5px;flex:1"></span>
      <button class="btn btn-o" id="qcX">Άκυρο</button>
      <button class="btn btn-p" id="qcOk">Καταχώρηση</button>
    </div></div>`;
  document.body.appendChild(ovl);
  const $q = s => ovl.querySelector(s);
  const close = () => { ovl.remove(); document.removeEventListener('keydown', onEsc, true); };
  const onEsc = e => { if (e.key === 'Escape') { e.stopPropagation(); close(); } };
  document.addEventListener('keydown', onEsc, true);
  ovl.onclick = close;
  $q('#qcX').onclick = close;

  /* ── ποιος καλεί ── */
  const showSel = () => {
    const s = $q('#qcSel');
    if (!who.type && !who.name) { s.hidden = true; return; }
    s.hidden = false;
    s.innerHTML = `<span class="pill ${who.type ? 'pill-ok' : 'pill-warn'}">${
      who.type === 'lead' ? I.target : (who.type ? I.user : I.alert)} ${esc(who.name)}${
      /* Όταν δεν ξέρουμε όνομα, το «όνομα» ΕΙΝΑΙ ο αριθμός — μην τον γράψεις
         δύο φορές στην ίδια πινακίδα. */
      who.phone && who.phone !== who.name ? ` <span class="mut">${esc(who.phone)}</span>` : ''}${
      who.type ? '' : ' — άγνωστος'}</span>
      <button class="qc-clr" title="Καθάρισμα">✕</button>`;
    s.querySelector('.qc-clr').onclick = () => {
      who.type = null; who.id = 0; who.name = ''; who.phone = '';
      $q('#qcWho').value = ''; showSel(); thenTabs();
    };
  };
  let tmr = null;
  $q('#qcWho').oninput = () => {
    clearTimeout(tmr);
    const v = $q('#qcWho').value.trim();
    who.type = null; who.id = 0;
    /* Άγνωστος καλών: κρατάμε ό,τι έγραψες — αριθμό ή όνομα. */
    if (/^[\d\s+().-]{6,}$/.test(v)) { who.name = v; who.phone = v; } else { who.name = v; who.phone = ''; }
    showSel(); thenTabs();
    if (v.length < 3) { $q('#qcPick').innerHTML = ''; return; }
    tmr = setTimeout(async () => {
      const r = await api('call_who&q=' + encodeURIComponent(v)).catch(() => null);
      const list = (r && r.results) || [];
      $q('#qcPick').innerHTML = list.length ? `<div class="qc-list">${list.map(x =>
        `<div class="qc-opt" data-t="${x.type}" data-i="${x.id}" data-n="${esc(x.name)}" data-p="${esc(x.phone || '')}">
          ${x.type === 'lead' ? I.target : I.user}<b>${esc(x.name)}</b>
          ${x.phone ? `<span class="mut">${esc(x.phone)}</span>` : ''}
          <span class="pill pill-mut">${esc(x.why)}</span></div>`).join('')}</div>` : '';
      $$('.qc-opt', ovl).forEach(el => el.onclick = () => {
        who.type = el.dataset.t; who.id = +el.dataset.i;
        who.name = el.dataset.n; who.phone = el.dataset.p;
        $q('#qcWho').value = who.name;
        $q('#qcPick').innerHTML = '';
        showSel(); thenTabs();
        $q('#qcSum').focus();
      });
    }, 240);
  };

  /* ── διάρκεια ── */
  $$('.qc-m', ovl).forEach(b => b.onclick = () => {
    $q('#qcMin').value = +$q('#qcMin').value === +b.dataset.m ? 0 : +b.dataset.m;
    hint();
  });
  $q('#qcMin').oninput = hint;
  $q('#qcBill').onchange = hint;

  /* ── τι γίνεται μετά ── */
  let then = 'none';
  const thenTabs = () => {
    $$('.qc-t', ovl).forEach(b => b.classList.toggle('on', b.dataset.then === then));
    const ex = $q('#qcExtra');
    if (then === 'task') {
      ex.innerHTML = `<div class="qc-row" style="margin-top:9px">
        <div><label class="lbl">Έργο</label>
          <select class="inp" id="qcPj"><option value="">— χωρίς έργο —</option>
            ${(S.boot.projects || []).map(p => `<option value="${p.id}">${esc(p.name)}</option>`).join('')}</select></div>
        <div><label class="lbl">Ανάθεση</label>
          <select class="inp" id="qcAs">${(S.boot.admins || []).map(a =>
            `<option value="${a.id}" ${a.id === S.boot.me.id ? 'selected' : ''}>${esc(a.name)}</option>`).join('')}</select></div>
      </div>`;
    } else if (then === 'ticket') {
      ex.innerHTML = who.type === 'client'
        ? `<div style="margin-top:9px"><label class="lbl">Department</label>
             <select class="inp" id="qcDept" style="max-width:260px">${depts.map(d =>
               `<option value="${d.id}">${esc(d.name)}</option>`).join('')}</select></div>`
        : `<div class="qc-warn">${I.alert} Για ticket χρειάζεται υπαρκτός πελάτης — διάλεξέ τον παραπάνω.</div>`;
    } else {
      ex.innerHTML = '';
    }
    hint();
  };
  $$('.qc-t', ovl).forEach(b => b.onclick = () => { then = b.dataset.then; thenTabs(); });
  $$('[data-dir]', ovl).forEach(b => b.onclick = () => {
    $$('[data-dir]', ovl).forEach(x => x.classList.toggle('on', x === b));
  });

  function hint() {
    const m = +$q('#qcMin').value || 0;
    const bill = $q('#qcBill').checked;
    let t = '';
    if (m && bill && then !== 'task') {
      t = `<span style="color:var(--warn)">${I.alert} Ο χρεώσιμος χρόνος χρειάζεται εργασία για να καταγραφεί.</span>`;
    } else if (m && bill) {
      t = `${m}΄ χρεώσιμα — θα αφαιρεθούν από την προαγορά ή θα μείνουν ακάλυπτα.`;
    } else if (m) {
      t = `${m}΄ χωρίς χρέωση.`;
    }
    $q('#qcHint').innerHTML = t;
  }

  /* ── καταχώρηση ── */
  const save = async () => {
    const sum = $q('#qcSum').value.trim();
    if (!sum) { toast('Γράψε τι ζήτησε', true); $q('#qcSum').focus(); return; }
    if (then === 'ticket' && who.type !== 'client') { toast('Για ticket διάλεξε υπαρκτό πελάτη', true); return; }
    const body = {
      summary: sum, detail: $q('#qcDet').value.trim(),
      direction: $q('[data-dir].on', ovl).dataset.dir,
      minutes: +$q('#qcMin').value || 0, billable: $q('#qcBill').checked,
      caller: who.type ? '' : who.name, phone: who.phone,
      client: who.type === 'client' ? who.id : 0,
      lead: who.type === 'lead' ? who.id : 0,
      then, followup: $q('#qcFup').value || '',
      pbxCall: picked,
    };
    if (then === 'task') {
      body.project = +($q('#qcPj') || {}).value || 0;
      body.assignee = +($q('#qcAs') || {}).value || 0;
    }
    if (then === 'ticket') { body.dept = +($q('#qcDept') || {}).value || 0; }
    const btn = $q('#qcOk'); btn.disabled = true; btn.textContent = '…';
    const r = await api('call_log', body).catch(e => ({ok: false, error: e && e.message}));
    btn.disabled = false; btn.textContent = 'Καταχώρηση';
    if (!r.ok) { toast(r.error || 'Δεν καταχωρήθηκε', true); return; }
    close();
    if (r.task) {
      toast('Καταγράφηκε — άνοιξε εργασία' + (r.timed ? ' με τον χρόνο' : ''));
      openTask(r.task);
    } else if (r.ticket) {
      toast('Καταγράφηκε — άνοιξε ticket');
      go('#/inbox/' + r.ticket);
    } else {
      toast('Η κλήση καταγράφηκε' + (r.billNeedsTask ? ' — ο χρεώσιμος χρόνος δεν μπήκε, χρειαζόταν εργασία' : ''));
    }
  };
  $q('#qcOk').onclick = save;
  $q('#qcSum').onkeydown = e => { if (e.key === 'Enter') { e.preventDefault(); save(); } };

  if (pre && pre.client) {
    who.type = 'client'; who.id = pre.client; who.name = pre.name || ''; who.phone = pre.phone || '';
    $q('#qcWho').value = who.name; showSel();
    $q('#qcSum').focus();
  } else {
    $q('#qcWho').focus();
  }
  thenTabs();

  /* ── ΟΙ ΠΡΑΓΜΑΤΙΚΕΣ ΣΟΥ ΚΛΗΣΕΙΣ ──
     Το τηλεφωνικό κέντρο ξέρει ήδη ποιον πήρες, πότε και πόση ώρα. Δεν έχει
     νόημα να τα ξαναγράφεις — ούτε να χρειάζεται να μπεις στη λίστα κλήσεων για
     να καταγράψεις. Διάλεξε την κλήση και γράψε μόνο τι έγινε. */
  if (!pre || !pre.client) {
    api('my_calls_open&days=3').then(r => {
      const list = (r && r.items) || [];
      if (!list.length) { return; }
      const box = $q('#qcReal');
      const hm = s => s >= 60 ? Math.round(s / 60) + '΄' : s + '΄΄';
      box.hidden = false;
      box.innerHTML = `<div class="qc-real">
        <div class="qc-real-h">Οι κλήσεις σου — διάλεξε και γράψε μόνο τι έγινε</div>
        ${list.map(c => `<button type="button" class="qc-real-i" data-rc="${c.id}">
          <span class="qc-real-d ${c.dir}">${c.dir === 'out' ? '↗' : '↙'}</span>
          <span class="qc-real-t">${esc((c.at || '').slice(5, 16).replace('-', '/'))}</span>
          <span class="qc-real-w">${c.clientName ? `<b>${esc(c.clientName)}</b>`
            : c.anon ? '<i class="mut">απόκρυψη αριθμού</i>' : esc(c.other || '—')}</span>
          <span class="qc-real-m">${c.answered ? hm(c.talk) : '<span class="cl-miss">αναπάντητη</span>'}</span>
        </button>`).join('')}</div>`;
      $$('.qc-real-i', box).forEach(el => el.onclick = () => {
        const c = list.find(x => x.id === +el.dataset.rc);
        if (!c) { return; }
        picked = c.id;
        $$('.qc-real-i', box).forEach(o => o.classList.toggle('on', o === el));
        /* Κατεύθυνση, συνομιλητής και λεπτά έρχονται από το κέντρο. */
        $$('[data-dir]', ovl).forEach(b => b.classList.toggle('on', b.dataset.dir === c.dir));
        if (c.client) { who.type = 'client'; who.id = c.client; who.name = c.clientName; who.phone = c.other || ''; }
        else { who.type = null; who.id = 0; who.name = c.anon ? 'απόκρυψη αριθμού' : (c.other || ''); who.phone = c.other || ''; }
        $q('#qcWho').value = who.name;
        showSel();
        $q('#qcMin').value = c.answered ? Math.max(1, Math.round(c.talk / 60)) : 0;
        $q('#qcSum').focus();
      });
    }).catch(() => {});
  }
}
window.CNP.quickCall = quickCall;


/* ═════════ Λίστα v2 — grouping + saved views ═════════ */
/* Τα πρόσθετα φίλτρα της λίστας tasks. Το «μόνο ανοιχτά» ξεκινά ανοιχτό: είναι
   η προεπιλογή της οθόνης, οπότε πρέπει να ΦΑΙΝΕΤΑΙ ότι ισχύει. */
const LF_F = {
  open: {label: 'Ανοιχτά', bool: 1},
  mine: {label: 'Δικά μου', bool: 1},        // ανάθεση ή μπάλα — cnpIsMine
  proj: {label: 'Έργο', opts: d => [['', '— κάθε —']]
           .concat((d.projects || []).map(n => [n, n]))},
};

/* ═════════ 🗂 Κάρτες διαχείρισης — η ουρά αποφάσεων (22/9/2026) ═════════
   Δεν είναι dashboard. Κάθε κάρτα είναι ΜΙΑ ερώτηση με κουμπιά που εκτελούν επί τόπου:
   νέα ημερομηνία, ανάθεση, «ρώτα τον Χ» (γίνεται αίτημα), κλείσιμο, αναβολή, «δεν ισχύει».
   Καμία κάρτα δεν φεύγει σιωπηλά — ή απαντιέται ή αναβάλλεται ρητά. */
R.cards = async function () {
  setTop('Κάρτες διαχείρισης', 'Τι θέλει απόφαση σήμερα — και τι γίνεται με ένα κλικ');
  const c = $('#content');
  const f = R.cards._f = R.cards._f || {state: 'open', scope: 'mine'};
  c.innerHTML = '<div class="skel" style="height:90px;margin-bottom:14px"></div><div class="skel" style="height:320px"></div>';
  const d = await api('cards&state=' + f.state + '&scope=' + f.scope).catch(e => ({err: e.message}));
  if (!d || d.err) { c.innerHTML = `<div class="card"><div class="card-b mut">${esc((d && d.err) || 'Δεν φορτώθηκε.')}</div></div>`; return; }

  const SEV = [['#74839a', 'πληροφορία'], ['#eba63c', 'προσοχή'], ['#e2515f', 'κρίσιμο']];
  const KIND = {
    unassigned: ['Αζήτητη', I.alert], overdue: ['Εκπρόθεσμη', I.clock], project_late: ['Παράδοση έργου', I.folder],
    idle: ['Ακίνητη', I.clock], age: ['Ξεχασμένη', I.clock], flow: ['Ροή ουράς', I.chart || I.list],
    no_estimate: ['Χωρίς εκτίμηση', I.list], no_deadline: ['Χωρίς παράδοση', I.cal],
  };
  const ACT = {
    schedule: ['Νέα ημερομηνία', 'date'], schedule_project: ['Νέα παράδοση', 'date'],
    assign: ['Ανάθεσε σε…', 'who'], ask: ['Ρώτα', 'ask'], close: ['Έκλεισε ήδη', 'plain'],
    open_list: ['Δες τη λίστα', 'go:list'], open_projects: ['Δες τα έργα', 'go:projects'],
  };
  const ago = at => { if (!at) { return ''; } const h = Math.floor((Date.now() - new Date(String(at).replace(' ', 'T')).getTime()) / 3600000);
    return h < 1 ? 'μόλις τώρα' : h < 24 ? 'πριν ' + h + 'ω' : 'πριν ' + Math.floor(h / 24) + ' ημ.'; };

  const card = k => {
    const [col] = SEV[k.sev] || SEV[0];
    const kd = KIND[k.kind] || [k.kind, I.alert];
    const mine = !k.owner;
    return `<div class="dc dc-s${k.sev}${k.state === 'snoozed' ? ' dc-snz' : ''}${['done', 'dismissed'].includes(k.state) ? ' dc-off' : ''}" data-dc="${k.id}">
      <div class="dc-h">
        <span class="dc-kind" style="--c:${col}">${kd[1]} ${esc(kd[0])}</span>
        ${mine ? '<span class="pill pill-warn" title="Δεν έχει ιδιοκτήτη — όποιος το πιάσει">αζήτητη</span>'
          : `<span class="pill pill-mut">${esc(k.ownerName)}</span>`}
        ${k.escalated ? '<span class="pill pill-bad" title="Δεν απαντήθηκε στην ώρα της — το είδαν οι Manager">κλιμακώθηκε</span>' : ''}
        ${k.state === 'snoozed' ? `<span class="pill pill-info">${k.helpId ? 'περιμένει απάντηση' : 'αναβλήθηκε'}</span>` : ''}
        <span style="flex:1"></span>
        <span class="mut" style="font-size:11px">${esc(ago(k.at))}</span></div>
      <div class="dc-t">${esc(k.title)}</div>
      <div class="dc-b">${esc(k.body)}</div>
      ${k.note ? `<div class="dc-note">${esc(k.note)}${k.resolvedBy ? ' · ' + esc(k.resolvedBy) : ''}</div>` : ''}
      ${['done', 'dismissed'].includes(k.state) ? '' : `<div class="dc-a">
        ${(k.acts || []).filter(a => a !== 'dismiss').map(a => {
          const def = ACT[a]; if (!def) { return ''; }
          return `<button class="btn btn-sm ${a === 'ask' ? 'btn-p' : 'btn-o'}" data-dcact="${a}" data-dcid="${k.id}">${esc(def[0])}</button>`;
        }).join('')}
        ${k.refType === 'task' && k.refId ? `<button class="btn btn-sm btn-o" data-dcopen="${k.refId}">Άνοιξέ την</button>` : ''}
        ${k.refType === 'project' && k.refId ? `<button class="btn btn-sm btn-o" data-dcproj="${k.refId}">Board</button>` : ''}
        <span style="flex:1"></span>
        <button class="btn btn-sm btn-o" data-dcact="snooze" data-dcid="${k.id}" title="Ξανά αύριο το πρωί">Όχι τώρα</button>
        <button class="btn btn-sm btn-o dc-x" data-dcact="dismiss" data-dcid="${k.id}" title="Δεν ισχύει — θα ζητηθεί λόγος">Δεν ισχύει</button>
        <button class="btn btn-sm btn-ok" data-dcact="done" data-dcid="${k.id}">Τακτοποιήθηκε</button></div>`}
    </div>`;
  };

  const crit = d.cards.filter(x => x.sev >= 2).length;
  c.innerHTML = `
  <div class="dc-hero">
    <div><div class="dc-hero-n" style="color:${crit ? 'var(--bad)' : 'var(--ok)'}">${d.cards.length}</div>
      <div class="dc-hero-l">${f.state === 'open' ? 'θέλουν απόφαση' : 'κάρτες'}</div></div>
    <div><div class="dc-hero-n">${crit}</div><div class="dc-hero-l">κρίσιμες</div></div>
    <div><div class="dc-hero-n">${d.counts.pool}</div><div class="dc-hero-l">αζήτητες</div></div>
    <div><div class="dc-hero-n" style="color:var(--ok)">${d.counts.todayDone}</div><div class="dc-hero-l">τακτοποιήθηκαν<br>σήμερα</div></div>
    <span style="flex:1"></span>
    <div class="dc-hero-x mut">${d.isPm ? 'Οι κάρτες σου και η κοινή δεξαμενή. Ό,τι δεν απαντηθεί ως το τέλος της ημέρας ανεβαίνει στους Manager.'
      : 'Εποπτεία: βλέπεις τι δόθηκε στην ομάδα project management και τι απαντήθηκε.'}</div>
  </div>
  <div class="fbar">
    <span class="fseg" id="dcState">
      <button class="fchip${f.state === 'open' ? ' on' : ''}" data-s="open">Ανοιχτές</button>
      <button class="fchip${f.state === 'done' ? ' on' : ''}" data-s="done">Τακτοποιημένες</button>
      <button class="fchip${f.state === 'all' ? ' on' : ''}" data-s="all">Όλες</button></span>
    ${d.isEsc ? `<span class="fseg" id="dcScope">
      <button class="fchip${f.scope === 'mine' ? ' on' : ''}" data-sc="mine">Δικές μου</button>
      <button class="fchip${f.scope === 'all' ? ' on' : ''}" data-sc="all">Όλης της ομάδας</button></span>` : ''}
    <span class="fbar-sp"></span>
    ${S.boot.me.full ? `<button class="fchip" id="dcBuild" title="Ξαναϋπολογισμός τώρα (γίνεται αυτόματα κάθε πρωί)">${I.repeat} Ανανέωση ουράς</button>` : ''}
  </div>
  ${(d.perf || []).length && f.scope === 'all' ? `<div class="card" style="margin-bottom:12px"><div class="card-h">${I.chart || I.list} Πώς απαντά η διοίκηση <span class="mut" style="font-weight:600;font-size:11.5px">— τελευταίες 14 ημέρες</span></div>
    <div class="card-b" style="padding:4px 10px"><table class="tbl dc-perf"><thead><tr>
      <th>Ποιος</th><th>Πήρε</th><th>Απάντησε</th><th>Απέρριψε</th><th>Εκκρεμούν</th><th>Κλιμακώθηκαν</th><th>Μέσος χρόνος</th></tr></thead><tbody>
      ${d.perf.map(x => `<tr><td><b>${esc(x.name)}</b></td><td>${x.got}</td>
        <td style="color:var(--ok);font-weight:700">${x.done}</td>
        <td>${x.dismissed || '—'}</td>
        <td${x.open ? ' style="color:var(--warn);font-weight:700"' : ''}>${x.open || '—'}</td>
        <td${x.escalated ? ' style="color:var(--bad);font-weight:700"' : ''}>${x.escalated || '—'}</td>
        <td>${x.avgH === null ? '—' : (x.avgH < 1 ? Math.round(x.avgH * 60) + '΄' : x.avgH + 'ω')}</td></tr>`).join('')}
    </tbody></table></div></div>` : ''}
  <div id="dcList">${d.cards.length ? d.cards.map(card).join('')
    : `<div class="card"><div class="empty" style="padding:44px 16px"><div class="big">✅</div>
        <b style="color:var(--ink);font-size:15px">${f.state === 'open' ? 'Καμία εκκρεμής απόφαση' : 'Τίποτα εδώ'}</b>
        <div class="mut" style="font-size:12.5px;margin-top:6px">${f.state === 'open' ? 'Καθαρή ουρά. Η επόμενη παρτίδα βγαίνει αύριο το πρωί.' : ''}</div></div></div>`}</div>`;

  $$('#dcState [data-s]').forEach(b => b.onclick = () => { f.state = b.dataset.s; R.cards(); });
  $$('#dcScope [data-sc]').forEach(b => b.onclick = () => { f.scope = b.dataset.sc; R.cards(); });
  $$('#dcList [data-dcopen]').forEach(b => b.onclick = () => openTask(+b.dataset.dcopen));
  $$('#dcList [data-dcproj]').forEach(b => b.onclick = () => go('board', +b.dataset.dcproj));
  { const bb = $('#dcBuild'); if (bb) { bb.onclick = async () => { bb.disabled = true;
      const r = await api('cards_build').catch(e => ({err: e.message}));
      if (r && r.err) { toast(r.err, true); bb.disabled = false; return; }
      toast('Η ουρά ανανεώθηκε — ' + (r.created || 0) + ' νέες'); R.cards(); }; } }

  const run = async (id, act, extra) => {
    const r = await api('card_act', Object.assign({id, act}, extra || {})).catch(e => ({err: e.message}));
    if (r && r.err) { toast(r.err, true); return false; }
    toast(r.state === 'snoozed' ? 'Θα ξαναεμφανιστεί' : '✔ Τακτοποιήθηκε');
    R.cards();
    return true;
  };
  $$('#dcList [data-dcact]').forEach(b => b.onclick = async () => {
    const id = +b.dataset.dcid, act = b.dataset.dcact;
    const k = d.cards.find(x => x.id === id) || {};
    if (act === 'open_list') { go('list'); return; }
    if (act === 'open_projects') { go('projects'); return; }
    if (act === 'snooze' || act === 'done') { run(id, act); return; }
    if (act === 'dismiss') {
      const why = await window.CNP.cnpDialog({title: 'Γιατί δεν ισχύει;', body: 'Ο λόγος καταγράφεται. Αν ο ίδιος κανόνας απορριφθεί τρεις φορές, σταματά να σου εμφανίζεται.',
        input: '', rows: 2, max: 255, ok: 'Απόρριψη', cancel: 'Άκυρο'});
      if (why === null || why === false || !String(why).trim()) { return; }
      run(id, 'dismiss', {note: String(why).trim()}); return;
    }
    if (act === 'schedule' || act === 'schedule_project') {
      const dflt = new Date(Date.now() + 3 * 86400000).toISOString().slice(0, 10);
      const v = await window.CNP.cnpDialog({title: act === 'schedule' ? 'Νέα ημερομηνία λήξης' : 'Νέα ημερομηνία παράδοσης',
        body: k.title || '', input: dflt, inputType: 'date', ok: 'Αποθήκευση', cancel: 'Άκυρο'});
      if (!v) { return; }
      run(id, act, {date: String(v).slice(0, 10)}); return;
    }
    if (act === 'close') { run(id, 'close'); return; }
    if (act === 'assign') {
      const items = (d.admins || []).map(a => ({icon: I.user, label: a.name, on: () => run(id, 'assign', {to: a.id})}));
      window.CNP.miniMenu(b, items); return;
    }
    if (act === 'ask') {
      const msg = await window.CNP.cnpDialog({title: '💬 Ρώτα τι γίνεται',
        body: 'Θα σταλεί ως αίτημα και θα το δει στο «σε ζητούν». Μόλις απαντήσει, η κάρτα κλείνει μόνη της.',
        input: 'Τι γίνεται με «' + String(k.title || '').slice(0, 80) + '»;', rows: 3, max: 255, ok: 'Στείλε', cancel: 'Άκυρο'});
      if (msg === null || msg === false) { return; }
      run(id, 'ask', {note: String(msg).trim()}); return;
    }
  });
};

/* ═════════ 📨 Αιτήματα: το «σε ζητούν» με ιστορικό και αλληλογραφία (21/9/2026) ═════════
   Πριν, ό,τι ερχόταν ως popup (βοήθεια, «τι γίνεται;», @αναφορά, προσφορά, φωνή) χανόταν μόλις
   το έκλεινες. Εδώ ζει όλο: εισερχόμενα / απεσταλμένα, ανοιχτά / τακτοποιημένα, με νήμα
   απαντήσεων ανά αίτημα — σαν αλληλογραφία. */
R.requests = async function (openId) {
  setTop('Αιτήματα', 'Ποιος σε ζήτησε, τι απαντήθηκε, τι εκκρεμεί — τίποτα δεν χάνεται');
  const c = $('#content');
  const f = R.requests._f = R.requests._f || {box: 'in', state: 'open', q: '', who: 0};
  const {cnpKpis, cnpPeopleBar} = window.CNP;
  /* Τα φίλτρα ΕΙΝΑΙ τα νούμερα. Έξι κουμπιά «Προς εμένα / Από εμένα / Όλα / Ανοιχτά /
     Τακτοποιημένα / Όλα» έλεγαν ακριβώς ό,τι λένε τα πλακίδια από πάνω — και ο χρήστης
     έπρεπε να μεταφράσει μόνος του «σε περιμένουν» = «Προς εμένα + Ανοιχτά». Έμεινε ένα
     κουμπί «Ιστορικό» για να μη γίνει τίποτα απρόσιτο. */
  const all = f.box === 'all' && f.state === 'all';
  c.innerHTML = `<div class="fbar">
    ${fChip('Αναζήτηση', `<input class="fchip-s" id="rqQ" value="${esc(f.q)}" placeholder="κείμενο αιτήματος…" style="width:230px">`, !!f.q, '')}
    <button class="fchip${all ? ' on' : ''}" id="rqAll" title="Όλα τα αιτήματα — ανοιχτά και τακτοποιημένα, προς εμένα και από εμένα">${I.clock} Ιστορικό</button>
    <span class="fbar-sp"></span>
    <button class="fchip fchip-go" id="rqNew">${I.plus} Ζήτα βοήθεια</button>
  </div>
  <div id="rqDash"></div>
  <div class="rq-split"><div id="rqList"><div class="skel" style="height:200px"></div></div>
    <div id="rqPane" class="rq-pane"><div class="empty" style="padding:50px 16px"><div class="big">${I.chat}</div>Διάλεξε αίτημα για να δεις τη συζήτηση</div></div></div>`;
  const d = await api('requests&box=' + f.box + '&state=' + f.state + '&q=' + encodeURIComponent(f.q)).catch(() => ({items: [], counts: {}, people: [], mates: []}));

  const ago = at => { if (!at) return ''; const h = Math.floor((Date.now() - new Date(String(at).replace(' ', 'T')).getTime()) / 3600000); return h < 1 ? 'μόλις τώρα' : h < 24 ? 'πριν ' + h + 'ω' : 'πριν ' + Math.floor(h / 24) + ' ημ.'; };
  const days = at => Math.floor((Date.now() - new Date(String(at).replace(' ', 'T')).getTime()) / 86400000);
  const dLbl = n => n === 0 ? 'σήμερα' : n === 1 ? '1 ημέρα' : n + ' ημέρες';

  /* ── Τα νούμερα που αλλάζουν συμπεριφορά ──
     «Πόσοι σε περιμένουν» και «πόσο αργείς» — και τα δύο κλικαρίσιμα, γιατί ένας
     αριθμός που δεν σε πάει κάπου είναι διακόσμηση. Ο μέσος χρόνος βγαίνει από τα
     τακτοποιημένα των 14 ημερών· με λιγότερα από 3 δείγματα δεν λέγεται καθόλου. */
  const cn = d.counts || {};
  const hAbs = h => h === null || h === undefined ? '—'
    : h < 1 ? Math.round(h * 60) + '΄' : h < 24 ? (Math.round(h * 10) / 10) + 'ω' : Math.round(h / 24) + ' ημ.';
  const avgOk = (cn.avgN || 0) >= 3;
  const dash = `<div class="dbar">${cnpKpis([
    {n: cn.in || 0, label: 'σε περιμένουν', color: cn.in ? 'var(--bad)' : 'var(--ok)',
      tip: 'Ανοιχτά αιτήματα προς εσένα — αυτά χρωστάς', act: 'in', on: f.box === 'in' && f.state === 'open' && !all},
    {n: cn.out || 0, label: 'περιμένεις<br>άλλους', tip: 'Ό,τι ζήτησες εσύ και δεν έχει απαντηθεί',
      act: 'out', on: f.box === 'out' && f.state === 'open' && !all},
    {n: cn.oldestDays || 0, label: 'ημέρες το<br>παλαιότερο',
      color: (cn.oldestDays || 0) >= 3 ? 'var(--bad)' : (cn.oldestDays || 0) >= 1 ? 'var(--warn)' : null,
      tip: 'Πόσο περιμένει το πιο παλιό ανοιχτό αίτημα προς εσένα'},
    {n: cn.answeredToday || 0, label: 'απάντησες<br>σήμερα', color: cn.answeredToday ? 'var(--ok)' : null,
      tip: 'Αιτήματα που τακτοποίησες σήμερα — κλικ για όλα τα τακτοποιημένα',
      act: 'done', on: f.state === 'done' && !all},
    {n: avgOk ? hAbs(cn.avgH) : '—', label: 'μέσος χρόνος<br>απάντησης',
      tip: avgOk ? 'Μέσος όρος στα ' + cn.avgN + ' αιτήματα που τακτοποίησες τις τελευταίες 14 ημέρες'
        : 'Χρειάζονται τουλάχιστον 3 τακτοποιημένα αιτήματα σε 14 ημέρες για να βγει μέσος όρος'},
  ])}</div>`;

  /* ── Ποιος περιμένει, από πότε, και αν είναι ΤΩΡΑ μέσα ──
     Το πράσινο δαχτυλίδι είναι η χρήσιμη πληροφορία: μια ερώτηση τριών ημερών σε κάποιον
     που είναι αυτή τη στιγμή μέσα λύνεται με ένα μήνυμα, όχι με άλλη μια αναμονή. */
  const peep = (d.people || []).map(x => Object.assign({}, x, {sel: f.who === x.id, hot: x.days >= 2}));
  const pbar = cnpPeopleBar(peep, {attr: 'rqwho',
    title: f.box === 'out' ? 'Ποιους περιμένεις' : 'Ποιος σε περιμένει',
    hint: peep.length ? peep.length + (peep.length === 1 ? ' άτομο' : ' άτομα') + ' · το παλαιότερο ' + dLbl(peep[0].days)
      + (f.who ? ' · φίλτρο ενεργό' : '') : '',
    badge: x => dLbl(x.days),
    tip: x => x.name + ' — ' + x.n + (x.n === 1 ? ' ανοιχτό' : ' ανοιχτά') + ', το παλαιότερο ' + dLbl(x.days) + ' · ' + x.label,
    right: f.who ? '<button class="btn btn-sm btn-o" id="rqWhoX">✕ όλοι</button>' : ''});
  $('#rqDash').innerHTML = dash + pbar;

  const ctxOf = r => r.taskTitle ? '#' + r.taskId + ' ' + r.taskTitle : (r.projectName || '');
  const agePill = r => { if (r.status !== 'open') { return ''; } const n = days(r.at);
    return `<span class="pill ${n >= 3 ? 'pill-bad' : n >= 1 ? 'pill-warn' : 'pill-mut'}" title="Ανοιχτό από ${esc(tShort(r.at))}">${dLbl(n)}</span>`; };
  const row = r => `<div class="rq-row${r.status === 'open' ? ' open' : ''}${r.forMe && !r.seen ? ' unread' : ''}" data-rq="${r.id}">
    <span class="rq-ic">${r.icon}</span>
    <span class="rq-t">
      <b>${esc(r.forMe ? r.from : 'προς ' + r.to)}</b>
      <span class="rq-kind">${esc(r.kindLbl)}</span>
      ${r.status === 'done' ? '<span class="pill pill-ok">τακτοποιήθηκε</span>' : agePill(r)}
      <span class="rq-msg">${esc((r.message || '').slice(0, 150))}</span>
      ${ctxOf(r) ? `<span class="rq-ctx">${I.checkSquare} ${esc(ctxOf(r).slice(0, 70))}</span>` : ''}
    </span>
    <span class="rq-meta">${r.replies ? `<span class="pill pill-mut">${I.chat} ${r.replies}</span>` : ''}<span class="mut">${esc(ago(r.lastAt || r.at))}</span></span>
    ${/* ΟΙ ΕΝΕΡΓΕΙΕΣ ΠΑΝΩ ΣΤΗ ΓΡΑΜΜΗ. Ό,τι έστειλες εσύ πρέπει να μπορείς να το
         σκουντήσεις, να το κλείσεις ή να το σβήσεις χωρίς να ανοίξεις το καθένα
         ξεχωριστά — οκτώ ξεχασμένα αιτήματα θέλουν οκτώ κλικ, όχι είκοσι τέσσερα. */''}
    ${r.mine ? `<span class="rq-act">
      ${r.status === 'open' ? `<button class="btn btn-sm btn-o" data-rqresend="${r.id}" title="Ξαναστείλ' το">↻</button>
      <button class="btn btn-sm btn-o" data-rqclose="${r.id}" title="Κλείσ' το — δεν είναι πια εκκρεμότητα">✓</button>` : ''}
      <button class="btn btn-sm btn-o" data-rqdel="${r.id}" title="Διαγραφή" style="color:var(--bad)">${I.trash}</button>
    </span>` : ''}</div>`;

  /* Τα ανοιχτά μπαίνουν ΠΑΛΑΙΟΤΕΡΟ ΠΡΩΤΟ: αυτό που ξεχάστηκε είναι το πρόβλημα,
     όχι αυτό που μόλις ήρθε. Τα τακτοποιημένα μένουν νεότερα πρώτα, ως ιστορικό. */
  let items = (d.items || []).slice();
  if (f.who) { items = items.filter(r => (r.forMe ? r.fromId : r.toId) === f.who); }
  items.sort((a, b) => (a.status === 'open' ? 0 : 1) - (b.status === 'open' ? 0 : 1)
    || (a.status === 'open' ? String(a.at).localeCompare(String(b.at)) : String(b.at).localeCompare(String(a.at))));

  const listEl = $('#rqList');
  listEl.innerHTML = items.length
    ? `<div class="card"><div class="card-b" style="padding:4px 8px">${items.map(row).join('')}</div></div>`
    : `<div class="card"><div class="empty" style="padding:40px 16px"><div class="big">✅</div><b style="color:var(--ink)">Κανένα αίτημα εδώ</b>
        <div class="mut" style="font-size:12.5px;margin-top:6px">${f.who ? 'Κανένα από αυτό το άτομο — πάτα «✕ όλοι» για να τα δεις όλα.'
          : f.q ? 'Καμία αντιστοιχία στην αναζήτηση.'
          : f.box === 'in' && f.state === 'open' ? 'Δεν σε περιμένει κανείς — καθαρό τραπέζι.'
          : f.box === 'out' && f.state === 'open' ? 'Δεν περιμένεις κανέναν.'
          : 'Τίποτα εδώ — πάτα «Ιστορικό» για να τα δεις όλα.'}</div></div></div>`;

  /* ── Καρτέλα αιτήματος: το ζητούμενο, το νήμα, και το πεδίο απάντησης ── */
  const openRq = async id => {
    const pane = $('#rqPane'); if (!pane) { return; }
    pane.innerHTML = '<div class="skel" style="height:220px"></div>';
    $$('#rqList .rq-row').forEach(x => x.classList.toggle('sel', +x.dataset.rq === +id));
    const r = await api('request_get&id=' + id).catch(() => null);
    if (!r) { pane.innerHTML = '<div class="empty" style="padding:40px">Δεν βρέθηκε</div>'; return; }
    const q = r.req;
    const canReply = q.forMe || q.mine;
    pane.innerHTML = `<div class="card rq-card">
      <div class="card-h" style="gap:8px"><span style="font-size:17px">${q.icon}</span>
        <b>${esc(q.kindLbl)}</b>
        <span class="mut" style="font-weight:600;font-size:12px">${esc(q.from)} → ${esc(q.to)}</span>
        ${q.status === 'done' ? '<span class="pill pill-ok">τακτοποιήθηκε</span>' : '<span class="pill pill-warn">ανοιχτό</span>'}
        <span style="flex:1"></span>
        <button class="btn btn-sm btn-o" id="rqX" title="Κλείσιμο">✕</button></div>
      <div class="card-b">
        <div class="rq-q">${esc(q.message)}</div>
        <div class="rq-sub">${esc(tShort(q.at))}${q.taskId ? ` · <a href="javascript:" data-rqtask="${q.taskId}">${I.checkSquare} #${q.taskId} ${esc(q.taskTitle)}</a>` : ''}${q.projectId ? ` · <a href="javascript:" data-rqproj="${q.projectId}">${I.folder} ${esc(q.projectName)}</a>` : ''}</div>
        ${q.answer && q.answer !== 'reply' ? `<div class="rq-ans ${q.answer === 'help' ? 'bad' : 'ok'}">${q.answer === 'help' ? '🆘 Απάντησε: χρειάζομαι βοήθεια' : '✅ Απάντησε: όλα καλά'}${q.answerNote ? ' — ' + esc(q.answerNote) : ''}</div>` : ''}
        <div class="rq-thread">${r.msgs.map(m => `<div class="rq-m${m.mine ? ' me' : ''}">
          <div class="rq-m-h">${esc(m.byName)} · ${esc(tShort(m.at))}</div>${esc(m.body)}</div>`).join('')
          || '<div class="mut" style="font-size:12.5px;padding:8px 0">Καμία απάντηση ακόμη.</div>'}</div>
        ${canReply ? `<div class="rq-reply">
          <textarea class="inp" id="rqTxt" rows="2" placeholder="Γράψε την απάντησή σου…"></textarea>
          <div class="rq-reply-a">
            ${q.forMe && q.status === 'open' ? '<label class="mut" style="font-size:11.5px;display:inline-flex;gap:5px;align-items:center"><input type="checkbox" id="rqKeep"> κράτα το ανοιχτό</label>' : ''}
            <span style="flex:1"></span>
            ${q.status === 'open' ? `<button class="btn btn-sm btn-o" id="rqDone">✓ Τακτοποιήθηκε</button>` : `<button class="btn btn-sm btn-o" id="rqReopen">↩ Ξανάνοιξέ το</button>`}
            <button class="btn btn-sm btn-p" id="rqSend">${I.send} Απάντηση</button></div></div>` : ''}
      </div></div>`;
    const tx = $('#rqTxt', pane);
    const send = async () => {
      const body = tx.value.trim(); if (!body) { tx.focus(); return; }
      const x = await api('help_reply', {id: q.id, body, keepOpen: $('#rqKeep', pane) && $('#rqKeep', pane).checked ? 1 : 0}).catch(e => ({err: e.message}));
      if (x && x.err) { toast(x.err, true); return; }
      toast('✔ Στάλθηκε'); R.requests(q.id);
    };
    { const b2 = $('#rqSend', pane); if (b2) b2.onclick = send; }
    if (tx) tx.onkeydown = e => { if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); send(); } };
    { const b2 = $('#rqDone', pane); if (b2) b2.onclick = async () => { await api('help_done', {id: q.id}).catch(() => {}); toast('Τακτοποιήθηκε'); R.requests(q.id); }; }
    { const b2 = $('#rqReopen', pane); if (b2) b2.onclick = async () => { await api('request_reopen', {id: q.id}).catch(() => {}); toast('Ξανάνοιξε'); R.requests(q.id); }; }
    { const b2 = $('#rqX', pane); if (b2) b2.onclick = () => { pane.innerHTML = `<div class="empty" style="padding:50px 16px"><div class="big">${I.chat}</div>Διάλεξε αίτημα</div>`; $$('#rqList .rq-row').forEach(x => x.classList.remove('sel')); document.body.classList.remove('rq-open'); }; }
    $$('[data-rqtask]', pane).forEach(a => a.onclick = () => openTask(+a.dataset.rqtask));
    $$('[data-rqproj]', pane).forEach(a => a.onclick = () => go('board', +a.dataset.rqproj));
    document.body.classList.add('rq-open');   // κινητό: η καρτέλα παίρνει την οθόνη
  };
  $$('#rqList .rq-row').forEach(el => el.onclick = () => openRq(+el.dataset.rq));

  /* Τα κουμπιά της γραμμής δεν ανοίγουν το αίτημα — σταματούν εκεί. */
  const rqAct = (attr, fn) => $$('#rqList [data-' + attr + ']').forEach(b => b.onclick = async e => {
    e.stopPropagation();
    await fn(+b.dataset[attr]);
  });
  rqAct('rqresend', async id => {
    const x = await api('help_resend', {id}).catch(e => ({err: e.message}));
    if (x && (x.err || x.error)) { toast(x.err || x.error, true); return; }
    toast('Ξαναστάλθηκε');
    R.requests();
  });
  rqAct('rqclose', async id => {
    await api('help_done', {id}).catch(() => {});
    toast('Κλείστηκε');
    R.requests();
  });
  rqAct('rqdel', async id => {
    if (!(await cnpConfirm('Να διαγραφεί το αίτημα και όλο το νήμα του; Δεν αναιρείται.',
      {danger: true, ok: I.trash + ' Διαγραφή'}))) { return; }
    const x = await api('help_del', {id}).catch(e => ({err: e.message}));
    if (x && (x.err || x.error)) { toast(x.err || x.error, true); return; }
    toast('Διαγράφηκε');
    R.requests();
  });
  $$('#rqDash [data-dkact]').forEach(b => b.onclick = () => {
    const a = b.dataset.dkact;
    if (a === 'in') { f.box = 'in'; f.state = 'open'; }
    else if (a === 'out') { f.box = 'out'; f.state = 'open'; }
    else { f.box = 'all'; f.state = 'done'; }
    f.who = 0; R.requests();
  });
  $$('#rqDash [data-rqwho]').forEach(b => b.onclick = () => { const id = +b.dataset.rqwho; f.who = f.who === id ? 0 : id; R.requests(); });
  { const wx = $('#rqWhoX'); if (wx) { wx.onclick = e => { e.stopPropagation(); f.who = 0; R.requests(); }; } }
  $('#rqAll').onclick = () => {
    if (all) { f.box = 'in'; f.state = 'open'; } else { f.box = 'all'; f.state = 'all'; }
    f.who = 0; R.requests();
  };
  { let t0; const qi = $('#rqQ'); if (qi) qi.oninput = () => { clearTimeout(t0); t0 = setTimeout(() => { f.q = qi.value.trim(); R.requests(); }, 400); }; }
  $('#rqNew').onclick = () => { if (window.CNP.quickHelp) { window.CNP.quickHelp({}); } };
  if (openId) { openRq(+openId); }
};

/* ═════════ 👁 Επιβλέπω: οι εργασίες που άνοιξα εγώ ═════════
   Ο επιβλέπων = όποιος δημιούργησε την εργασία. Εδώ βλέπει πώς πάνε: ποιος τις έχει, πού
   είναι η μπάλα, πότε κινήθηκαν τελευταία. Φίλτρα ανοιχτές / ολοκληρωμένες / όλες. */
R.supervised = async function () {
  setTop('Επιβλέπω', 'Οι εργασίες που άνοιξες εσύ — πώς πάνε, ποιος τις έχει, πού κόλλησαν');
  const c = $('#content');
  const f = R.supervised._f = R.supervised._f || {which: 'open', q: '', group: 'project'};
  c.innerHTML = `<div class="fbar">
    ${fChip('Αναζήτηση', `<input class="fchip-s" id="svQ" value="${esc(f.q)}" placeholder="τίτλο, έργο, χειριστή…" style="width:230px">`, !!f.q, '')}
    <span class="fseg" id="svWhich">
      <button class="fchip${f.which === 'open' ? ' on' : ''}" data-w="open">Ανοιχτές <b id="svNo"></b></button>
      <button class="fchip${f.which === 'done' ? ' on' : ''}" data-w="done">Ολοκληρωμένες <b id="svNd"></b></button>
      <button class="fchip${f.which === 'all' ? ' on' : ''}" data-w="all">Όλες</button></span>
    ${fChip('Ομαδοποίηση', fSel('group', [['project', 'Ανά έργο'], ['assignee', 'Ανά χειριστή'], ['status', 'Ανά κατάσταση'], ['', 'Χωρίς']], f.group), false, '')}
    <span class="fbar-sp"></span>
    <button class="fchip fchip-go" id="svNew">${I.plus} Νέα εργασία</button>
  </div><div id="svRes"><div class="skel" style="height:220px"></div></div>`;
  const d = await api('supervised&which=' + f.which).catch(() => ({tasks: [], counts: {open: 0, done: 0}}));
  const no = $('#svNo'), nd = $('#svNd'); if (no) no.textContent = d.counts.open; if (nd) nd.textContent = d.counts.done;
  const me = S.boot.me.id;
  const norm = x => String(x || '').toLowerCase().replace(/ά/g, 'α').replace(/έ/g, 'ε').replace(/ή/g, 'η').replace(/[ίϊΐ]/g, 'ι').replace(/ό/g, 'ο').replace(/[ύϋΰ]/g, 'υ').replace(/ώ/g, 'ω').replace(/ς/g, 'σ');
  const ago = at => { if (!at) return ''; const h = Math.floor((Date.now() - new Date(String(at).replace(' ', 'T')).getTime()) / 3600000); return h < 1 ? 'μόλις τώρα' : h < 24 ? 'πριν ' + h + 'ω' : 'πριν ' + Math.floor(h / 24) + ' ημ.'; };
  const stale = t => !t.isDone && (Date.now() - new Date(String(t.lastAt).replace(' ', 'T')).getTime()) > 5 * 86400000;
  const row = t => { const over = t.due && t.due < today() && !t.isDone; return `<div class="kb-item kb-trow${t.isDone ? ' done' : ''}" data-task="${t.id}">
      <span class="kb-dot" style="background:${['#8595ac', '#eba63c', '#e2515f'][t.prio] || '#8595ac'}"></span>
      <span class="tk-idc">#${t.id}</span>
      <b style="${t.isDone ? 'text-decoration:line-through;opacity:.7' : ''}">${esc(t.title)}</b>
      ${stale(t) ? '<span class="tk-flag tk-flag-tk" title="Καμία κίνηση πάνω από 5 ημέρες">⏸ στάσιμη</span>' : ''}
      <span class="kb-sum-meta">
        ${t.ball ? `<span class="ball ${t.ball === me ? 'me' : ''}" title="Η μπάλα: ${esc(adminName(t.ball))}">⚡${esc(adminIni(t.ball))}</span>` : ''}
        ${f.group !== 'project' ? `<span class="kb-tag" style="background:${t.pcolor}18;color:${t.pcolor}">${esc(t.pname || 'Χωρίς έργο')}</span>` : ''}
        ${t.clientName ? `<span class="kb-tag kb-tag-mut">${esc(t.clientName)}</span>` : ''}
        ${stPill(t.status)}
        <span class="mut"${cnpHolder(t) !== +(t.assignee || 0) && t.assignee
          ? ` title="ανάθεση: ${esc(adminName(t.assignee))}"` : ''}>${
          cnpHolder(t) ? esc(adminName(cnpHolder(t))) : 'χωρίς χειριστή'}</span>
        ${t.due ? `<span class="${over ? 'kb-tag' : 'mut'}" ${over ? 'style="background:#e2515f18;color:#e2515f"' : ''}>${dShort(t.due)}</span>` : ''}
        <span class="mut" title="Τελευταία κίνηση ${esc(tShort(t.lastAt))}">${esc(ago(t.lastAt))}</span>
      </span></div>`; };
  const render = () => {
    const el = $('#svRes');
    const list = d.tasks.filter(t => !f.q || norm([t.title, t.pname, t.clientName, statusOf(t.status).title,
      cnpHolder(t) ? adminName(cnpHolder(t)) : '', t.assignee ? adminName(t.assignee) : ''].join(' ')).includes(norm(f.q)));
    if (!list.length) { el.innerHTML = `<div class="card"><div class="empty" style="padding:40px"><div class="big">${I.eye}</div><b style="color:var(--ink);font-size:15px">${d.tasks.length ? 'Τίποτα με αυτά τα φίλτρα' : (f.which === 'done' ? 'Καμία ολοκληρωμένη ακόμη' : 'Δεν έχεις ανοίξει εργασίες που να εκκρεμούν')}</b></div></div>`; return; }
    /* ΑΝΑ ΧΕΙΡΙΣΤΗ = ανά αυτόν που ΚΡΑΤΑΕΙ την εργασία, όχι ανά ανάθεση. */
    const keyOf = t => f.group === 'assignee' ? (cnpHolder(t) ? adminName(cnpHolder(t)) : 'Χωρίς χειριστή') : f.group === 'status' ? statusOf(t.status).title : (t.pname || 'Χωρίς έργο');
    const groups = {}; list.forEach(t => { (groups[f.group ? keyOf(t) : 'Όλες'] = groups[f.group ? keyOf(t) : 'Όλες'] || []).push(t); });
    el.innerHTML = Object.entries(groups).map(([k, ts]) => `<div class="card kb-group"><div class="card-h" style="font-size:13px">${esc(k)} <span class="kb-n">${ts.length}</span></div><div class="card-b kb-gbody">${ts.map(row).join('')}</div></div>`).join('');
    $$('#svRes [data-task]').forEach(r => r.onclick = () => openTask(+r.dataset.task));
  };
  render();
  $('#svQ').oninput = () => { f.q = $('#svQ').value.trim(); render(); };
  $$('#svWhich [data-w]').forEach(b => b.onclick = () => { f.which = b.dataset.w; R.supervised(); });
  $('#content [data-fk="group"]').onchange = e => { f.group = e.target.value; render(); };
  $('#svNew').onclick = () => window.CNP.quickNew && window.CNP.quickNew();
};

R.list = async function () {
  setTop('Λίστα tasks', 'Τα δικά σου πρώτα — βγάλε το φίλτρο για όλη την ομάδα · g+l');
  const c = $('#content');
  // ίδια δομή με τη Βιβλιοθήκη γνώσης: search + chips + ομαδοποίηση + φόρμα πίσω από κουμπί
  /* ΑΝΟΙΓΕΙ ΣΤΑ ΔΙΚΑ ΣΟΥ (23/09/2026). Η ορατότητα ανά έργο είναι τόσο πλατιά που
     πρακτικά όλοι είναι μέλη σε όλα τα έργα: η λίστα άνοιγε με 70 ανοιχτές
     εργασίες όλης της εταιρείας, με κόκκινες ημερομηνίες δίπλα σε ονόματα άλλων.
     Ο Βάκρινος διάβασε ως δική του καθυστέρηση εργασία που ούτε του είχε ανατεθεί
     ούτε κρατούσε τη μπάλα της. Το φίλτρο υπήρχε — απλώς ήταν σβηστό και κρυμμένο.
     Τώρα είναι αναμμένο και φαίνεται, με ✕ για να δεις τα πάντα με ένα κλικ. */
  const f = R.list._f = R.list._f || {open: 1, group: 'project', proj: '', q: '', fs: '', fa: '',
    mine: 1, closed: {}, shown: ['open', 'mine']};
  if (!f.shown) { f.shown = ['open']; }
  Object.keys(LF_F).forEach(k => { if (f[k] && !f.shown.includes(k)) { f.shown.push(k); } });
  const views = JSON.parse(localStorage.cnpViews || '[]');
  let D = {tasks: []};
  const GROUPS = {project: 'Ανά project', status: 'Ανά στήλη', assignee: 'Ανά χειριστή', prio: 'Ανά προτεραιότητα', '': 'Χωρίς ομαδοποίηση'};
  const prioDot = p => ['#8595ac', '#eba63c', '#e2515f'][p] || '#8595ac';
  const prioName = p => ['Κανονική', 'Υψηλή', 'Κρίσιμη'][p] || 'Κανονική';

  c.innerHTML = `
  ${/* Ήταν τρεις σειρές μέσα σε κάρτα: αναζήτηση, μετά chips έργων + ομαδοποίηση
       + δύο κουτάκια, μετά τα αποθηκευμένα views. Τώρα μία γραμμή κατά
       docs/UI-STANDARD.md· τα έργα έγιναν φίλτρο, τα views μένουν ξεχωριστά
       γιατί ΔΕΝ είναι φίλτρα — είναι συντομεύσεις σε σύνολα φίλτρων. */''}
  <div class="fbar">
    ${fChip('Αναζήτηση', `<input class="fchip-s" id="lfQ" data-fk="q" value="${esc(f.q || '')}"
      placeholder="τίτλο, project, χειριστή, #αριθμό…" style="width:180px">`, !!f.q, '')}
    ${fChip('Ομαδοποίηση', fSel('group', Object.entries(GROUPS), f.group), false, '')}
    <span id="lfMore"></span>
    <span class="fbar-sp"></span>
    <button class="fchip" id="lfSave" title="Αποθήκευση αυτών των φίλτρων ως view">${I.pin} Αποθήκευση view</button>
    <button class="fchip" id="lfCsv">${I.download} CSV</button>
    <button class="fchip fchip-go" id="lfNew">${I.plus} Νέο task</button>
  </div>
  ${views.length ? `<div class="fviews">${I.pin}
    ${views.map((v, i) => `<span class="fview"><span data-view="${i}">${esc(v.name)}</span>
      <b data-viewdel="${i}" title="Διαγραφή view">✕</b></span>`).join('')}</div>` : ''}
  <div id="lRes"><div class="skel" style="height:220px"></div></div>`;

  /* ── γραμμή task (ίδιο ύφος με τις καταχωρήσεις γνώσης) ── */
  const row = t => {
    const stt = statusOf(t.status), over = t.due && t.due < today() && !t.done;
    return `<div class="kb-item kb-trow" data-task="${t.id}">
      <span class="kb-dot" style="background:${prioDot(t.prio)}" title="Προτεραιότητα: ${prioName(t.prio)}"></span>
      <span class="tk-idc" title="Αριθμός εργασίας">#${t.id}</span>
      <b>${esc(t.title)}</b>
      ${t.ticket ? `<span class="tk-flag tk-flag-tk" title="Από ticket — προτεραιότητα">${I.ticket} ticket</span>` : ''}
      ${t.isOffer ? `<span class="tk-flag tk-flag-of" title="Αφορά προσφορά — προτεραιότητα">${I.doc} προσφορά</span>` : ''}
      <span class="kb-sum-meta">
        ${t.ball ? `<span class="ball ${t.ball === S.boot.me.id ? 'me' : ''}" title="Η μπάλα: περιμένει ενέργεια από ${esc(adminName(t.ball))}">⚡${esc(adminIni(t.ball))}</span>` : ''}
        ${f.group !== 'project' ? `<span class="kb-tag" style="background:${t.pcolor}18;color:${t.pcolor}">${esc(t.pname)}</span>` : ''}
        ${t.clientName ? `<span class="kb-tag kb-tag-mut" title="Πελάτης">${esc(t.clientName)}</span>` : ''}
        ${stPill(t.status)}
        ${cnpHolder(t) ? `<span class="mut"${cnpHolder(t) !== +(t.assignee || 0) && t.assignee
          ? ` title="ανάθεση: ${esc(adminName(t.assignee))}"` : ''}>${esc(adminName(cnpHolder(t)))}</span>`
          : '<span class="mut">χωρίς χειριστή</span>'}
        ${t.due ? `<span class="${over ? 'kb-tag' : 'mut'}" ${over ? 'style="background:#e2515f18;color:#e2515f"' : ''}>${dShort(t.due)}</span>` : ''}
        ${t.mins ? `<span class="mut">${fmtMin(t.mins)}</span>` : ''}
      </span></div>`;
  };

  const norm = s => String(s || '').toLowerCase()
    .replace(/ά/g, 'α').replace(/έ/g, 'ε').replace(/ή/g, 'η').replace(/[ίϊΐ]/g, 'ι')
    .replace(/ό/g, 'ο').replace(/[ύϋΰ]/g, 'υ').replace(/ώ/g, 'ω').replace(/ς/g, 'σ');
  /* ΑΝΑΖΗΤΗΣΗ ΜΕ ΑΡΙΘΜΟ. Ο αριθμός είναι ο τρόπος που αναφερόμαστε σε μια
     εργασία μεταξύ μας («δες το #105») — και ήταν ο μόνος που δεν έβρισκε.

     «#105» θεωρείται ΡΗΤΑ αριθμός και ψάχνει ΜΟΝΟ ταυτότητα: αλλιώς ένα ticket
     με τίτλο «[#105…]» θα γέμιζε το αποτέλεσμα. Σκέτο «105» ψάχνει και τα δύο,
     γιατί δεν ξέρουμε αν εννοείς εργασία ή κείμενο. */
  const match = t => {
    if (!f.q) { return true; }
    const q = f.q.trim();
    const hash = /^#\s*(\d+)$/.exec(q);
    if (hash) { return String(t.id) === hash[1]; }
    const text = norm([t.title, t.pname, statusOf(t.status).title,
      cnpHolder(t) ? adminName(cnpHolder(t)) : '', t.assignee ? adminName(t.assignee) : '',
      prioName(t.prio)].join(' ')).includes(norm(q));
    return /^\d+$/.test(q) ? (String(t.id) === q || text) : text;
  };

  const render = () => {
    /* ΠΡΩΤΑ η γραμμή. Ήταν στο τέλος, αλλά το render γυρίζει νωρίς όταν η λίστα
       είναι άδεια — οπότε ακριβώς τη στιγμή που ένα φίλτρο έκοβε τα πάντα, το
       κουμπάκι του δεν ανανεωνόταν και έδειχνε ανενεργό. */
    paintBar();
    let list = D.tasks.filter(match);
    if (f.proj !== '') { list = list.filter(t => (t.pname || 'Χωρίς έργο') === f.proj); }
    /* Ο κανόνας της μπάλας, όχι σκέτος ανάδοχος: όταν η εργασία περιμένει άλλον,
       δεν είναι δική μου — είναι δική ΤΟΥ. Δες cnpIsMine στο app.js. */
    if (f.mine) list = list.filter(t => cnpIsMine(t));

    /* Τα έργα ομαδοποιούνται ανά ΟΝΟΜΑ, όχι ανά id: η ίδια γραμμή δουλειάς
       («e-Commerce», «Marketplaces») υπάρχει ως ξεχωριστό έργο σε κάθε πελάτη,
       και πέντε πανομοιότυπες επιλογές δεν είναι φίλτρο, είναι θόρυβος.
       Ήταν σειρά από chips· τώρα είναι φίλτρο της γραμμής (βλ. paintBar). */

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
      : f.group === 'assignee' ? (cnpHolder(t) ? adminName(cnpHolder(t)) : 'Χωρίς χειριστή')
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

  /* Η ΛΙΣΤΑ ΕΡΓΩΝ ΕΡΧΕΤΑΙ ΜΕΤΑ ΤΗ ΓΡΑΜΜΗ. Ξαναχτίζουμε μόνο τα πρόσθετα
     κουμπάκια, και μόνο όταν όντως άλλαξε κάτι μέσα τους — αλλιώς κάθε
     πληκτρολόγηση στην αναζήτηση θα τα ξανάγραφε χωρίς λόγο. */
  let barKey = '';
  const paintBar = () => {
    const box = $('#lfMore'); if (!box) { return; }
    const projs = [...new Set(D.tasks.map(t => t.pname || 'Χωρίς έργο'))]
      .sort((a, b) => a.localeCompare(b, 'el'));
    const key = [f.shown.join('~'), projs.join('~'), f.open, f.mine, f.proj].join('|');
    if (key === barKey) { return; }
    barKey = key;
    box.innerHTML = f.shown.map(k => fOne(k, LF_F[k], f, {projects: projs})).join('')
      + fAdd(LF_F, f.shown);
    fWire(f, LF_F, () => load());
  };

  const bindRows = () => {
    $$('[data-task]').forEach(r => r.onclick = () => openTask(+r.dataset.task));
    $$('.kb-ghead').forEach(h => h.onclick = () => {
      const g = h.dataset.lgrp; f.closed[g] = !f.closed[g];
      h.nextElementSibling.style.display = f.closed[g] ? 'none' : '';
      h.querySelector('.kb-gchev').classList.toggle('open', !f.closed[g]);
    });
    const n2 = $('#lfNew2'); if (n2) n2.onclick = () => window.CNP.quickNew();
    $$('[data-view]').forEach(b => b.onclick = () => {
      const vf = Object.assign({}, views[+b.dataset.view].f);
      /* Παλιές όψεις κρατούσαν id έργου· τώρα το φίλτρο είναι όνομα. Χωρίς αυτό
         θα άνοιγαν άδειες. */
      if (vf.proj !== '' && /^\d+$/.test(String(vf.proj))) {
        const pr = (S.boot.projects || []).find(x => x.id === +vf.proj);
        vf.proj = pr ? pr.name : '';
      }
      Object.assign(R.list._f, vf); R.list();
    });
    $$('[data-viewdel]').forEach(b => b.onclick = e => {
      e.stopPropagation();
      views.splice(+b.dataset.viewdel, 1);
      localStorage.cnpViews = JSON.stringify(views); R.list();
    });
  };

  /* Η δημιουργία εργασίας γίνεται ΑΠΟ ΕΝΑ ΣΗΜΕΙΟ: «+ Νέο → Νέο task».
     Εδώ υπήρχε δεύτερη, ξεχωριστή φόρμα με δικά της πεδία (project, κατάσταση,
     χειριστής, προθεσμία…) — άλλη λογική από την υπόλοιπη εφαρμογή, που ρωτούσε
     πράγματα πριν προλάβεις να γράψεις τι θέλεις. Αφαιρέθηκε. */

  // Το q ΔΕΝ πάει στον server: το tasksFiltered ψάχνει μόνο title/descr, οπότε αναζήτηση
  // κατά χειριστή/project/κατάσταση θα γύριζε 0. Ό,τι αφορά κείμενο γίνεται client-side (match).
  const load = async () => {
    const qs = ['fs=' + encodeURIComponent(f.fs || ''), 'fa=' + encodeURIComponent(f.fa || ''),
      'open=' + (f.open ? 1 : 0), 'mine=' + (f.mine ? 1 : 0)].join('&');
    D = await api('list&' + qs).catch(() => ({tasks: []}));
    render();
  };

  cnpSearch('lfQ', v => { f.q = v; render(); }, 180);
  $('[data-fk="group"]').onchange = e => { f.group = e.target.value; render(); };
  $('#lfNew').onclick = () => window.CNP.quickNew();
  $('#lfSave').onclick = async () => {
    const name = await cnpPrompt('Όνομα view:', {title: I.pin + ' Αποθήκευση view', placeholder: 'π.χ. Bugs Τεχνικού', ok: 'Αποθήκευση'});
    if (!name) { return; }
    views.push({name, f: Object.assign({}, f, {closed: {}})});
    localStorage.cnpViews = JSON.stringify(views);
    toast('Το view αποθηκεύτηκε'); R.list();
  };
  $('#lfCsv').onclick = () => {
    const esc2 = v => '"' + String(v == null ? '' : v).replaceAll('"', '""') + '"';
    /* ΚΑΙ ΤΑ ΔΥΟ, σε χωριστές στήλες: «Χειριστής» είναι ποιος το κρατάει τώρα,
       «Ανάθεση» ποιανού είναι στα χαρτιά. Σε εξαγωγή δεν διαλέγουμε — όποιος
       ανοίξει το αρχείο μπορεί να θέλει το ένα ή το άλλο. */
    const rows = [['Task', 'Project', 'Status', 'Χειριστής', 'Ανάθεση', 'Λήξη', 'Λεπτά'].map(esc2).join(';')];
    D.tasks.filter(match).forEach(t => rows.push([t.title, t.pname, statusOf(t.status).title,
      cnpHolder(t) ? adminName(cnpHolder(t)) : '',
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
  cnpSkel(c, '<div class="skel" style="height:340px"></div>');
  let dErr = null;
  const d = await api('triage').catch(e => { dErr = e; return null; });
  if (!d) { c.innerHTML = cnpDenied(dErr); return; }
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
          <div class="mut" style="font-size:10.5px">${cH.tickets90} tickets/90ημ${cH.open ? ` · ${cH.open} ανοιχτά` : ''}${cH.slaBreaches ? ` · ${cH.slaBreaches} SLA σπασμένα` : ''}${cH.owed !== null && cH.owed ? ` · οφείλει ${cH.owed}€` : (cH.owedFlag ? ' · έχει οφειλή' : '')}</div></div>
        <span class="mut">→</span></div>`).join('') : '<div class="empty" style="padding:16px">—</div>';
    $$('#trHealth [data-cgo]').forEach(x => x.onclick = () => { window.CNP.go('client360'); });
  }).catch(() => {});
};

/* ═════════ 📚 ΓΝΩΣΗ — «το έχω ξαναλύσει;» ═════════ */
R.knowledge = async function () {
  setTop('Βάση γνώσης', 'Έχει ξαναλυθεί αυτό; Και ποιος ξέρει πώς');
  const c = $('#content');
  const st = R.knowledge._st = R.knowledge._st || {q: '', prod: '', sort: 'uses', mine: false, closed: {}, page: {}};
  const PER = 25;   // πόσα άρθρα ανά ομάδα πριν το «Περισσότερα»
  let D = {items: [], products: [], unfiled: 0};
  const prod = id => D.products.find(p => p.id === +id);
  const KSORT = {uses: 'Πιο χρήσιμα', recent: 'Πιο πρόσφατα', title: 'Αλφαβητικά'};

  c.innerHTML = `
  <div class="fbar">
    ${fChip('Αναζήτηση', `<input class="fchip-s" id="kQ" value="${esc(st.q)}"
      placeholder="τίτλο, λέξεις-κλειδιά, κείμενο λύσης, προϊόν…" style="width:300px">`, !!st.q, '')}
    ${fChip('Ταξινόμηση', `<select class="fchip-s" id="kSort">${Object.entries(KSORT).map(([k, l]) =>
      `<option value="${k}" ${st.sort === k ? 'selected' : ''}>${l}</option>`).join('')}</select>`, false, '')}
    <button type="button" class="fchip fchip-b${st.mine ? ' on' : ''}" id="kMine">${
      st.mine ? '✓ ' : ''}Μόνο δικά μου</button>
    <span class="fbar-sp"></span>
    <button class="fchip" id="kDeep" title="Ψάξε και στο ιστορικό των tickets">${I.ticket} Και στα tickets</button>
    <button class="fchip" id="kImp" title="Εισαγωγή από online τεκμηρίωση/εγχειρίδιο">${I.download} Εισαγωγή από URL</button>
    <button class="fchip fchip-go" id="kNew">${I.plus} Προσθήκη γνώσης</button>
  </div>
  <div class="fchips">
    <button class="kb-chip${st.prod === '' ? ' on' : ''}" data-kprod="">Όλα <b id="kcAll"></b></button>
    <span class="chipwrap" id="kProdChips"></span>
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
          ${cnpCan('support.kb_edit') ? `<button class="btn btn-sm btn-o" data-kedit="${k.id}">${I.edit} Επεξεργασία</button>
          <button class="btn btn-sm btn-o" style="color:var(--bad)" data-kdel="${k.id}">${I.trash}</button>` : ''}
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
  cnpSearch('kQ', v => { st.q = v; st.page = {}; render(); }, 180);
  $('#kQ').onkeydown = e => { if (e.key === 'Enter') deep(); };
  $('#kDeep').onclick = deep;
  $('#kNew').onclick = () => openForm(null);
  $('#kImp').onclick = () => openImport(D.products, load);
  $('#kSort').onchange = () => { st.sort = $('#kSort').value; render(); };
  /* Κουμπί που ανάβει αντί για κουτάκι μέσα σε κουμπάκι: το φίλτρο ΕΙΝΑΙ η
     τιμή του, δεν έχει ετικέτα+τιμή σαν τα υπόλοιπα. Ξαναζωγραφίζουμε τη
     γραμμή για να αλλάξει η όψη του. */
  $('#kMine').onclick = () => { st.mine = !st.mine; st.page = {}; R.knowledge(); };
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
    cnpSkel(res, '<div class="skel" style="height:120px"></div>');
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
const MIC_SVG = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg>';
/* Player φωνητικού μηνύματος: ▶/❚❚, μπάρα με seek, χρόνος. Η διάρκεια έρχεται απ' έξω (secs)
   γιατί τα webm ηχογράφησης δεν την έχουν στα metadata. */
function chVoiceHtml(url, secs, name) {
  const f = s => Math.floor(s / 60) + ':' + String(Math.floor(s % 60)).padStart(2, '0');
  return `<div class="ch-voice" data-secs="${secs || 0}">
    <button type="button" class="ch-vp" title="Αναπαραγωγή">▶</button>
    <div class="ch-vbar" title="Κλικ για μετάβαση"><span></span></div>
    <span class="ch-vt">${f(secs || 0)}</span>
    <audio preload="metadata" src="${url}"></audio>
    ${name ? `<a class="ch-vdl" href="${url}${url.indexOf('?') > 0 ? '&' : '?'}dl=1" download="${esc(name)}" title="Λήψη">${I.download}</a>` : ''}
  </div>`;
}
/* ✓✓ Αποδείξεις ανάγνωσης κάτω από τα ΔΙΚΑ ΜΟΥ μηνύματα. DM: «Διαβάστηκε HH:MM» / «Στάλθηκε».
   Ομάδα: «Διαβάστηκε από 3/5» με ονόματα στο tooltip. Η ώρα είναι η στιγμή που ο άλλος
   άνοιξε τη συνομιλία και είδε ως εκεί — γι' αυτό μπαίνει μόνο στο τελευταίο διαβασμένο. */
function chPaintReads(reads, members) {
  const mine = [...document.querySelectorAll('#chMsgs .ch-m.me:not(.deleted)')];
  if (!mine.length) { return; }
  const lastReadId = Math.max(0, ...reads.map(r => r.lastId));
  mine.forEach(el => {
    const id = +el.dataset.mid;
    const who = reads.filter(r => r.lastId >= id);
    let txt, cls;
    if (members <= 1) {
      const rd = who[0];
      const hm = at => { const d = new Date(String(at).replace(' ', 'T')); return isNaN(d) ? '' : d.toLocaleTimeString('el-GR', {hour: '2-digit', minute: '2-digit', hour12: false}); };
      txt = rd ? '✓✓ Διαβάστηκε' + (id === lastReadId && rd.at ? ' ' + hm(rd.at) : '') : '✓ Στάλθηκε';
      cls = rd ? 'read' : '';
    } else {
      txt = who.length ? '✓✓ Διαβάστηκε από ' + who.length + '/' + members : '✓ Στάλθηκε';
      cls = who.length ? 'read' : '';
    }
    let f = el.querySelector('.ch-rcpt');
    if (!f) { f = document.createElement('div'); f.className = 'ch-rcpt'; const rx = el.querySelector('.ch-reacts'); if (rx) { rx.before(f); } else { el.appendChild(f); } }
    if (f.textContent !== txt) { f.textContent = txt; }
    f.className = 'ch-rcpt ' + cls;
    f.title = who.length ? who.map(r => r.name).join(', ') : 'Δεν το έχει δει ακόμη κανείς';
  });
}
const CH_REACT = {up: '👍', ok: '✅', heart: '❤️', party: '🎉', eyes: '👀', think: '🤔', down: '👎'};
/* Μπάρα αντιδράσεων κάτω από το μήνυμα: {code: [{name, me}]} → pills· η δική μου έχει περίγραμμα. */
function chReactsHtml(reacts) {
  const codes = Object.keys(reacts || {}).filter(c => (reacts[c] || []).length);
  if (!codes.length) { return ''; }
  return `<div class="ch-reacts">${codes.map(c => { const who = reacts[c]; const mine = who.some(w => w.me);
    return `<button type="button" class="ch-react${mine ? ' mine' : ''}" data-chreact="${c}" title="${esc(who.map(w => w.name).join(', '))}">${CH_REACT[c] || c} ${who.length}</button>`; }).join('')}</div>`;
}
function chQuoteHtml(q) {
  if (!q) { return ''; }
  return `<div class="ch-quote" data-chgoto="${q.id}" title="Πήγαινε στο αρχικό μήνυμα"><b>${esc(q.name)}</b><span>${esc(q.text || '')}</span></div>`;
}
/* Διπλό κλικ σε μήνυμα = μεγέθυνση: το ίδιο περιεχόμενο σε παράθυρο, μεγάλα γράμματα, εικόνα πλήρους μεγέθους. */
function chZoom(div) {
  const body = div.querySelector('.ch-body'), img = div.querySelector('img'), voice = div.querySelector('.ch-voice'), h = div.querySelector('.h');
  const ovl = document.createElement('div'); ovl.className = 'ovl show ch-zoom-ovl';
  ovl.innerHTML = `<div class="ch-zoom" onclick="event.stopPropagation()">
    <button class="drawer-x ch-zoom-x" title="Κλείσιμο (Esc)">✕</button>
    <div class="ch-zoom-h">${h ? h.innerHTML : ''}</div>
    ${div.querySelector('.ch-quote') ? div.querySelector('.ch-quote').outerHTML : ''}
    ${body && body.innerHTML.trim() ? `<div class="ch-zoom-b">${body.innerHTML}</div>` : ''}
    ${img ? `<img src="${img.src}" class="ch-zoom-img">` : ''}
    ${voice ? voice.outerHTML.replace(/ class="ch-voice/, ' class="ch-voice big') : ''}
  </div>`;
  document.body.appendChild(ovl);
  const close = () => { ovl.remove(); document.removeEventListener('keydown', onKey); };
  const onKey = e => { if (e.key === 'Escape') { e.stopPropagation(); close(); } };
  document.addEventListener('keydown', onKey);
  ovl.onclick = close; ovl.querySelector('.ch-zoom-x').onclick = close;
  const v = ovl.querySelector('.ch-voice'); if (v) { v._wired = false; chWireVoice(ovl); }
  cnpWireMsgLinks(ovl);
}
function chWireVoice(root) {
  root.querySelectorAll('.ch-voice').forEach(v => {
    if (v._wired) { return; } v._wired = true;
    const a = v.querySelector('audio'), b = v.querySelector('.ch-vp'), bar = v.querySelector('.ch-vbar'), fill = bar.querySelector('span'), t = v.querySelector('.ch-vt');
    const f = s => Math.floor(s / 60) + ':' + String(Math.floor(s % 60)).padStart(2, '0');
    const dur = () => (isFinite(a.duration) && a.duration > 0) ? a.duration : (+v.dataset.secs || 0);
    b.onclick = () => {
      if (a.paused) { document.querySelectorAll('.ch-voice audio').forEach(o => { if (o !== a) { o.pause(); } }); a.play().catch(() => toast('Δεν παίζει ο ήχος', true)); }
      else { a.pause(); }
    };
    a.onplay = () => { b.textContent = '❚❚'; v.classList.add('playing'); };
    a.onpause = () => { b.textContent = '▶'; v.classList.remove('playing'); };
    a.onended = () => { b.textContent = '▶'; v.classList.remove('playing'); fill.style.width = '0'; t.textContent = f(dur()); };
    a.ontimeupdate = () => { const d = dur(); if (d) { fill.style.width = Math.min(100, a.currentTime / d * 100) + '%'; } t.textContent = f(a.currentTime); };
    bar.onclick = e => { const d = dur(); if (!d) { return; } const r = bar.getBoundingClientRect(); a.currentTime = Math.max(0, Math.min(d, (e.clientX - r.left) / r.width * d)); };
  });
}
R.chat = async function () {
  /* Φρουρός κυκλώματος «Η ομάδα» (12/9/2026): ό,τι κόβει ο server, δεν ανοίγει καν. */
  if (!cnpCan('team.chat')) { setTop('Chat'); $('#content').innerHTML = cnpDenied({message: 'Η συνομιλία της ομάδας δίνεται από το κύκλωμα «Η ομάδα → Chat»'}); return; }
  setTop('Chat', 'Εσωτερική επικοινωνία ομάδας — με αρχεία');
  const c = $('#content');
  const st = R.chat._st = R.chat._st || {ch: 'team', lastId: 0};
  clearInterval(R.chat._t);
  clearInterval(R.chat._vt);
  clearInterval(R.chat._p);   // ο σφυγμός παρουσίας — αλλιώς διπλασιάζεται σε κάθε επαναφόρτωση
  const d = await api('chat_channels');
  const ini = n => (n || '?').trim().split(/\s+/).map(w => w[0] || '').slice(0, 2).join('').toUpperCase();
  const chAva = ch => `<span class="ch-av ${ch.kind !== 'dm' ? 'ch-av-grp' : ''}">${ch.kind === 'team' ? I.users : ch.kind === 'group' ? '#' : esc(ini(ch.name))}${ch.kind === 'dm' ? `<span class="ch-av-dot ${ch.status || 'online'}"></span>` : ''}</span>`;
  /* Η ετικέτα έρχεται έτοιμη από τον server (μία πηγή αλήθειας) — εδώ μόνο
     προσθέτουμε τον λόγο, τη λήξη και το ότι το δήλωσε ο ίδιος. */
  const chLbl = ch => esc(ch.label || 'Διαθέσιμος')
    + (ch.hint ? ' · ' + esc(ch.hint) : '')
    + (ch.untilTxt ? ' · ' + esc(ch.untilTxt) : '');
  const chPresence = ch => chLbl(ch) + (ch.manual ? ' <span class="ch-manual">το δήλωσε</span>' : '');
  /* Πότε μιλήσατε τελευταία φορά — η αιτιολόγηση της σειράς. Σήμερα μόνο ώρα,
     παλιότερα ημερομηνία· χωρίς κουβέντα, τίποτα. */
  const chWhen = ch => {
    if (!ch.lastAt) { return ''; }
    const d = new Date(ch.lastAt * 1000);
    const sameDay = d.toDateString() === new Date().toDateString();
    const txt = sameDay
      ? d.toLocaleTimeString('el-GR', {hour: '2-digit', minute: '2-digit'})
      : d.toLocaleDateString('el-GR', {day: '2-digit', month: '2-digit'});
    return `<span class="ch-row-t" title="Τελευταίο μήνυμα">${esc(txt)}</span>`;
  };
  const cur = d.channels.find(x => x.id === st.ch) || d.channels[0] || {name: 'Chat', kind: 'team'};
  c.innerHTML = `
  <div class="voicebar">
    <div class="vb-l"><span class="vb-ic">🔊</span><b>Φωνή ομάδας</b>
      <span class="vb-pres" id="vbPres"><span class="mut">…</span></span></div>
    <div class="vb-r">
      <button class="btn btn-o btn-sm" id="vbCall" title="Στείλε «έλα τώρα» σε όλη την ομάδα">🔔 Κάλεσε την ομάδα</button>
      <button class="btn btn-p btn-sm" id="vbJoin">🎙 Μπες στη φωνή</button>
    </div>
  </div>
  <div class="chat${st.mobileConv ? ' conv-open' : ''}">
    ${/* Η ΚΑΤΑΣΤΑΣΗ ΑΛΛΑΖΕΙ ΑΠΟ ΕΝΑ ΣΗΜΕΙΟ: την πάνω μπάρα.
         Εδώ υπήρχε δεύτερος επιλογέας — ίδιο πράγμα, δύο θέσεις. Δεν πρόσθετε
         τίποτα (η πάνω μπάρα φαίνεται και μέσα στο chat) και μπέρδευε: άλλαζες
         εδώ, άλλαζες εκεί, και δεν ήξερες ποιο μετράει. Αφαιρέθηκε. */''}
    <div class="ch-left">
      <div class="ch-list">
      ${d.channels.map(ch => `
        <div class="ch-row ${st.ch === ch.id ? 'on' : ''}" data-ch="${ch.id}">
          ${chAva(ch)}
          <span class="ch-row-body">
            <span class="ch-row-name">${esc(ch.name)}${ch.kind === 'group' ? ` <span class="mut" style="font-size:10.5px;font-weight:500">· ${ch.members} μέλη</span>` : ''}</span>
            <span class="ch-row-sub">${ch.kind === 'dm' ? (ch.mute ? '🔕 ' : '') + chLbl(ch) : ch.kind === 'team' ? 'Όλη η ομάδα' : 'Ομαδική συνομιλία'}</span>
          </span>
          ${chWhen(ch)}
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
        ${/* ΠΟΙΟΙ ΕΙΝΑΙ ΜΕΣΑ. Σε ομαδική συνομιλία έγραφε μόνο «4 μέλη» — δεν
             ήξερες ποιοι, άρα ούτε ποιος διαβάζει ό,τι γράφεις. */''}
        ${cur.kind === 'group' ? `<button class="btn btn-o btn-sm" id="chMem"
          title="Ποιοι είναι στην ομάδα">${I.users || I.user} Μέλη</button>` : ''}
      </div>
      <div class="ch-msgs" id="chMsgs"><div class="skel" style="height:60px"></div></div>
      <div class="ch-comp-wrap">
        <div class="ch-paste" id="chPaste" hidden></div>
        <div class="ch-comp">
          <label class="btn btn-o btn-sm" style="cursor:pointer" title="Αρχείο">${I.clip}<input type="file" id="chFile" style="display:none"></label>
          <span id="chFn" class="mut" style="font-size:11px"></span>
          <input class="inp" id="chIn" placeholder="Μήνυμα… (Enter) — ή επικόλλησε εικόνα με Ctrl+V" style="flex:1">
          <div class="ch-rec" id="chRec" hidden></div>
          <button class="btn btn-o btn-sm" id="chMic" title="Φωνητικό μήνυμα — κράτα πατημένο όσο μιλάς, άφησέ το για να σταλεί">${MIC_SVG}</button>
          <button class="btn btn-p btn-sm" id="chSend">${I.send}</button>
        </div>
      </div>
    </div>
  </div>`;
  /* ── Μπάρα φωνής ομάδας: μόνιμο δωμάτιο (πάνω στο CloudOn Meet) + παρουσία ── */
  const vbJoin = $('#vbJoin'); if (vbJoin) { vbJoin.onclick = () => window.open(VOICE_URL, '_blank'); }
  const vbCall = $('#vbCall'); if (vbCall) { vbCall.onclick = () => voiceCallDialog(); }
  const paintVoice = async () => {
    const box = $('#vbPres'); if (!box) { clearInterval(R.chat._vt); return; }
    const r = await api('voice_presence').catch(() => null);
    const list = (r && r.in) || [];
    box.innerHTML = list.length
      ? list.map(p => `<span class="vb-ava" title="${esc(p.name)}">${esc(adminIni(p.adminId) || (p.name || '?').slice(0, 2))}</span>`).join('')
        + `<span class="vb-cnt">${list.length} μέσα</span>`
      : '<span class="mut">κανείς μέσα τώρα</span>';
  };
  paintVoice();
  R.chat._vt = setInterval(paintVoice, 10000);

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
  { const mb = $('#chMem'); if (mb) { mb.onclick = () => chatGroupCard(cur.groupId); } }

  /**
   * Η καρτέλα της ομάδας: ποιοι είναι μέσα, και — αν σου ανήκει — ποιοι θα είναι.
   *
   * Δεν είναι δεύτερη φόρμα «νέας ομάδας»: ανοίγει με τα πραγματικά μέλη
   * τσεκαρισμένα, ώστε να βλέπεις την αλλαγή που κάνεις. Ο δημιουργός δεν
   * ξετσεκάρεται — αν έφευγε, η ομάδα θα έμενε χωρίς κανέναν να τη διαχειρίζεται.
   */
  async function chatGroupCard(gid) {
    const g = await api('chat_group_get&id=' + gid).catch(e => ({err: e.message}));
    if (!g || g.err) { toast((g && g.err) || 'Δεν φορτώθηκε η ομάδα', true); return; }
    const inIt = id => g.members.some(m => m.id === id);
    const ovl = document.createElement('div'); ovl.className = 'ovl show'; ovl.style.zIndex = 300;
    ovl.innerHTML = `<div class="pal-box" style="margin:12vh auto 0;max-width:470px" onclick="event.stopPropagation()">
      <div style="padding:20px 22px">
        <b style="font-size:15.5px;color:var(--ink)"># ${esc(g.name)}</b>
        <div class="mut" style="font-size:12px;margin-top:3px">
          ${g.members.length} μέλη · την έφτιαξε ο/η ${esc(g.createdByName)}
          ${g.createdAt ? ' · ' + esc(String(g.createdAt).slice(0, 10)) : ''}</div>

        ${g.canEdit ? `<label class="lbl" style="margin-top:14px">Όνομα</label>
          <input class="inp" id="gcName" maxlength="80" value="${esc(g.name)}">` : ''}

        <label class="lbl" style="margin-top:13px">Μέλη${g.canEdit ? ' — βάλε ή βγάλε' : ''}</label>
        <div class="gc-mem">
          ${g.people.map(a => {
            const locked = a.id === g.createdBy;
            return `<label class="gc-m${inIt(a.id) ? ' on' : ''}${locked ? ' lock' : ''}"
              title="${locked ? 'Ο δημιουργός της ομάδας μένει πάντα μέσα' : ''}">
              <input type="checkbox" class="gcM" value="${a.id}" ${inIt(a.id) ? 'checked' : ''}
                ${(!g.canEdit || locked) ? 'disabled' : ''}> ${esc(a.name)}</label>`;
          }).join('')}
        </div>

        <div style="display:flex;gap:9px;margin-top:16px;justify-content:flex-end">
          <button class="btn btn-o" id="gcNo">${g.canEdit ? 'Άκυρο' : 'Κλείσιμο'}</button>
          ${g.canEdit ? '<button class="btn btn-p" id="gcGo">Αποθήκευση</button>' : ''}</div>
        ${g.canEdit ? '' : '<div class="mut" style="font-size:11.5px;margin-top:9px">Την ομάδα την αλλάζει όποιος τη δημιούργησε.</div>'}
      </div></div>`;
    document.body.appendChild(ovl);
    const kill = () => ovl.remove();
    ovl.onclick = kill;
    ovl.querySelector('#gcNo').onclick = kill;
    $$('.gc-m input:not(:disabled)', ovl).forEach(cb => cb.onchange = () =>
      cb.closest('.gc-m').classList.toggle('on', cb.checked));
    const go = ovl.querySelector('#gcGo');
    if (go) { go.onclick = async () => {
      const members = [...ovl.querySelectorAll('.gcM:checked')].map(x => +x.value);
      const name = ovl.querySelector('#gcName').value.trim();
      go.disabled = true;
      const r = await api('chat_group_save', {id: g.id, name, members}).catch(e => ({err: e.message}));
      if (!r || r.err) { toast((r && r.err) || 'Δεν αποθηκεύτηκε', true); go.disabled = false; return; }
      kill();
      const bits = [];
      if (r.added)   { bits.push('+' + r.added); }
      if (r.removed) { bits.push('−' + r.removed); }
      toast('Η ομάδα ενημερώθηκε' + (bits.length ? ' (' + bits.join(' / ') + ')' : ''));
      R.chat();
    }; }
  }

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
    /* Ο ΗΧΟΣ ΜΕΣΑ ΣΤΟ CHAT. Ο γενικός σφυγμός δεν τον παίζει εδώ: η οθόνη
       μαρκάρει τα μηνύματα διαβασμένα πριν προλάβει, οπότε δεν του φτάνουν
       ποτέ. Τον παίζουμε εκεί που όντως φτάνει το μήνυμα — και μόνο για ξένα
       και μόνο σε ανανέωση, όχι στο πρώτο γέμισμα της οθόνης. */
    const firstPaint = st.lastId === 0;
    let heard = false;
    msgs.forEach(m => {
      if (m.id <= st.lastId) return;   // ήδη ζωγραφισμένο (προστασία από race με το poll)
      st.lastId = m.id;
      if (!firstPaint && !heard && m.by !== S.boot.me.id && !m.deleted) {
        heard = true;
        window.CNP.chatBeep();
      }
      const div = document.createElement('div');
      div.className = 'ch-m' + (m.by === S.boot.me.id ? ' me' : '');
      div.dataset.mid = m.id;
      if (m.deleted) { div.classList.add('deleted'); div.innerHTML = `<div class="h">${esc(adminName(m.by))} · ${tShort(m.at)}</div><span class="mut">🚫 Το μήνυμα διαγράφηκε</span>`; box.appendChild(div); return; }
      /* Διαγραφή: δικά μου μηνύματα (ο Full: όλα). Εμφανίζεται στο hover / πάτημα στο κινητό. */
      const canDel = m.by === S.boot.me.id || S.boot.me.full;
      const canEdit = m.by === S.boot.me.id && !m.file && !!m.body && (m.editLeft || 0) > 0;
      const acts = `<span class="ch-acts"><button class="ch-del" data-chrx="${m.id}" title="Αντίδραση">🙂</button><button class="ch-del" data-chreply="${m.id}" title="Απάντηση με παράθεμα">↩</button>${canEdit ? `<button class="ch-del" data-chedit="${m.id}" title="Επεξεργασία">${I.edit}</button>` : ''}${canDel ? `<button class="ch-del" data-chdel="${m.id}" title="Διαγραφή μηνύματος">${I.trash}</button>` : ''}</span>`;
      div.innerHTML = acts + `<div class="h">${esc(adminName(m.by))} · ${tShort(m.at)}${m.edited ? ' <i class="ch-edited">· επεξεργάστηκε</i>' : ''}</div>
        ${chQuoteHtml(m.reply)}
        <div class="ch-body">${m.body ? cnpMsgHtml(m.body) : ''}</div>
        ${m.file ? (() => { const fu = m.file.url || ('api.php?a=chat_file&id=' + m.file.id); return `<div style="margin-top:4px"><a href="${fu}" target="_blank" style="font-weight:700">${m.file.kind === 'video' ? '🎬' : m.file.kind === 'image' ? '🖼️' : I.clip} ${esc(m.file.name)}</a>
          <span class="mut" style="font-size:10px">(${Math.round(m.file.size / 1024)} KB)</span>
          <a class="ch-dl" href="${fu}&dl=1" download="${esc(m.file.name)}" title="Λήψη αρχείου">${I.download} Λήψη</a>
          ${m.file.kind === 'video' ? `<video src="${fu}" controls preload="metadata" style="width:100%;max-width:340px;max-height:240px;border-radius:8px;background:#000;margin-top:5px"></video>` : m.file.kind === 'image' ? `<img src="${fu}" loading="lazy" style="max-width:100%;max-height:200px;border-radius:8px;margin-top:5px;display:block">` : ''}</div>`; })() : ''}`;
      /* 🎙 Φωνητικό: αντί για «voice-….webm» + Λήψη, ένας player σαν μήνυμα (WhatsApp-style). */
      if (m.file && m.file.kind === 'audio') {
        const fu = m.file.url || ('api.php?a=chat_file&id=' + m.file.id);
        const secs = (() => { const x = /(\d+):(\d\d)/.exec(m.body || ''); return x ? (+x[1]) * 60 + (+x[2]) : 0; })();
        div.innerHTML = `<span class="ch-acts"><button class="ch-del" data-chrx="${m.id}" title="Αντίδραση">🙂</button><button class="ch-del" data-chreply="${m.id}" title="Απάντηση με παράθεμα">↩</button>${canDel ? `<button class="ch-del" data-chdel="${m.id}" title="Διαγραφή μηνύματος">${I.trash}</button>` : ''}</span>` + `<div class="h">${esc(adminName(m.by))} · ${tShort(m.at)}</div>` + chQuoteHtml(m.reply) + chVoiceHtml(fu, secs, m.file.name);
      }
      div.insertAdjacentHTML('beforeend', chReactsHtml(m.reacts));
      div._m = m;
      box.appendChild(div);
      cnpWireMsgLinks(div);
      chWireVoice(div);
      chWireMsg(div);
      const eb = div.querySelector('[data-chedit]');
      if (eb) { eb.title = 'Επεξεργασία (μόνο το πρώτο λεπτό)'; setTimeout(() => eb.remove(), Math.max(1000, (m.editLeft || 0) * 1000)); }
      if (eb) eb.onclick = async e => {
        e.stopPropagation();
        const cur = div._body !== undefined ? div._body : (m.body || '');
        const txt = await window.CNP.cnpDialog({title: I.edit + ' Επεξεργασία μηνύματος', input: cur, rows: 4, max: 4000, ok: 'Αποθήκευση', cancel: 'Άκυρο'});
        if (txt === null || txt === false) return;
        const r = await api('chat_edit', {id: m.id, body: String(txt)}).catch(err => ({err: err.message}));
        if (r && r.err) { toast(r.err, true); return; }
        div._body = r.body; const bd = div.querySelector('.ch-body'); if (bd) { bd.innerHTML = cnpMsgHtml(r.body); cnpWireMsgLinks(bd); }
        const h = div.querySelector('.h'); if (h && !h.querySelector('.ch-edited')) { h.insertAdjacentHTML('beforeend', ' <i class="ch-edited">· επεξεργάστηκε</i>'); }
      };
      const db = div.querySelector('[data-chdel]');
      if (db) db.onclick = async e => {
        e.stopPropagation();
        if (!(await cnpConfirm('Να διαγραφεί το μήνυμα για όλους;', {ok: I.trash + ' Διαγραφή', cancel: 'Άκυρο', danger: true}))) return;
        const r = await api('chat_del', {id: m.id}).catch(err => ({err: err.message}));
        if (r && r.err) { toast(r.err, true); return; }
        div.classList.add('deleted'); div.innerHTML = `<div class="h">${esc(adminName(m.by))} · ${tShort(m.at)}</div><span class="mut">🚫 Το μήνυμα διαγράφηκε</span>`;
      };
    });
    if (msgs.length && (stick || st.lastId === msgs[msgs.length - 1].id)) box.scrollTop = box.scrollHeight;
  };
  let loading = false;
  const load = async () => {
    if (loading) return;
    loading = true;
    const r = await api('chat_msgs&channel=' + st.ch + '&after=' + Math.max(0, st.lastId)).catch(() => null);
    loading = false;
    /* Διαγραφές από άλλους (ή από άλλη συσκευή μου): ό,τι είναι ήδη στην οθόνη γίνεται «διαγράφηκε». */
    if (r && Array.isArray(r.reactUpd)) r.reactUpd.forEach(x => { const el = document.querySelector('#chMsgs .ch-m[data-mid="' + x.id + '"]:not(.deleted)'); if (el) { chSetReacts(el, x.reacts); } });
    if (r && Array.isArray(r.edited)) r.edited.forEach(x => { const el = document.querySelector('#chMsgs .ch-m[data-mid="' + x.id + '"]:not(.deleted)'); if (!el || el._body === x.body) return; const bd = el.querySelector('.ch-body'); if (bd) { el._body = x.body; bd.innerHTML = cnpMsgHtml(x.body); cnpWireMsgLinks(bd); const h = el.querySelector('.h'); if (h && !h.querySelector('.ch-edited')) { h.insertAdjacentHTML('beforeend', ' <i class="ch-edited">· επεξεργάστηκε</i>'); } } });
    if (r && Array.isArray(r.deleted)) r.deleted.forEach(id => { const el = document.querySelector('#chMsgs .ch-m[data-mid="' + id + '"]:not(.deleted)'); if (el) { const h = el.querySelector('.h'); el.classList.add('deleted'); el.innerHTML = (h ? h.outerHTML : '') + '<span class="mut">🚫 Το μήνυμα διαγράφηκε</span>'; } });
    if (r && r.messages.length) render(r.messages);
    if (r && Array.isArray(r.reads)) chPaintReads(r.reads, r.members || 0);
    if (r && r.messages.length) { /* ήδη ζωγραφισμένα */ }
    else if (st.lastId === 0) { const b = $('#chMsgs'); if (b) b.innerHTML = '<div class="empty" style="margin:auto">Καμία συζήτηση ακόμη — πες ένα γεια 👋</div>'; st.lastId = -1; }
  };
  /* ── Επικόλληση στιγμιότυπου (Ctrl+V) ────────────────────────────────────
     Ό,τι κόβεις από την οθόνη μπαίνει εδώ ως μικρογραφία και φεύγει με το
     μήνυμα. Δέχεται και σύρσιμο αρχείων. Το ✕ τη διώχνει πριν σταλεί. */
  const pend = [];
  const paintPend = () => {
    const box = $('#chPaste'); if (!box) { return; }
    box.hidden = !pend.length;
    box.innerHTML = pend.map((f, i) => `<div class="ch-thumb">
      <img src="${f._url}" alt="${esc(f.name)}">
      <span class="nm">${esc(f.name)}</span>
      <button class="x" data-rm="${i}" title="Αφαίρεση">✕</button></div>`).join('');
    $$('[data-rm]', box).forEach(b => b.onclick = () => {
      const i = +b.dataset.rm;
      URL.revokeObjectURL(pend[i]._url); pend.splice(i, 1); paintPend();
    });
  };
  const addPend = file => {
    if (!file || !/^image\//.test(file.type)) { return false; }
    if (file.size > 50 * 1024 * 1024) { toast('Μέγιστο 50MB', true); return true; }
    const ext = (file.type.split('/')[1] || 'png').replace('jpeg', 'jpg').replace(/[^a-z0-9]/g, '');
    const z = n => String(n).padStart(2, '0');
    const d0 = new Date();
    const stamp = d0.getFullYear() + z(d0.getMonth() + 1) + z(d0.getDate())
      + '-' + z(d0.getHours()) + z(d0.getMinutes()) + z(d0.getSeconds());
    const nf = new File([file], file.name && file.name !== 'image.png' ? file.name
      : 'screenshot-' + stamp + '.' + ext, {type: file.type});
    nf._url = URL.createObjectURL(nf);
    pend.push(nf); paintPend();
    return true;
  };
  /* ── ↩ Απάντηση με παράθεμα: μπάρα πάνω από τη σύνθεση, φεύγει με ✕ ή μετά την αποστολή ── */
  let replyTo = 0;
  const paintReply = () => {
    let bar = $('#chReplyBar');
    if (!replyTo) { if (bar) bar.remove(); return; }
    const src = document.querySelector('#chMsgs .ch-m[data-mid="' + replyTo + '"]'), m = src && src._m;
    if (!bar) { bar = document.createElement('div'); bar.id = 'chReplyBar'; bar.className = 'ch-replybar'; $('.ch-comp').before(bar); }
    const txt = m ? (m.body ? m.body.slice(0, 120) : (m.file && m.file.kind === 'audio' ? '🎙 φωνητικό' : '📎 ' + ((m.file || {}).name || ''))) : '';
    bar.innerHTML = `<span class="ch-replybar-l">↩ Απάντηση σε <b>${esc(m ? adminName(m.by) : '')}</b><span class="mut"> ${esc(txt)}</span></span><button class="btn btn-o btn-sm" id="chReplyX" title="Χωρίς παράθεμα">✕</button>`;
    $('#chReplyX').onclick = () => { replyTo = 0; paintReply(); };
  };
  const chSetReacts = (el, reacts) => {
    const old = el.querySelector('.ch-reacts'); if (old) old.remove();
    const rc = el.querySelector('.ch-rcpt');
    if (rc) { rc.insertAdjacentHTML('beforebegin', chReactsHtml(reacts)); } else { el.insertAdjacentHTML('beforeend', chReactsHtml(reacts)); }
    if (el._m) el._m.reacts = reacts;
    el.querySelectorAll('[data-chreact]').forEach(b => b.onclick = e => { e.stopPropagation(); chToggleReact(el, b.dataset.chreact); });
  };
  const chToggleReact = async (el, code) => {
    const m = el._m; if (!m) return;
    const r = await api('chat_react', {id: m.id, code}).catch(err => ({err: err.message}));
    if (r && r.err) { toast(r.err, true); return; }
    const reacts = Object.assign({}, m.reacts || {});
    const me = S.boot.me;
    const list = (reacts[code] || []).filter(w => !w.me);
    if (r.on) list.push({id: me.id, name: me.name, me: true});
    if (list.length) reacts[code] = list; else delete reacts[code];
    chSetReacts(el, reacts);
  };
  const chWireMsg = div => {
    const m = div._m; if (!m) return;
    const rx = div.querySelector('[data-chrx]');
    if (rx) rx.onclick = e => {
      e.stopPropagation();
      document.querySelectorAll('.ch-rxpick').forEach(x => x.remove());
      const pk = document.createElement('div'); pk.className = 'ch-rxpick';
      pk.innerHTML = Object.keys(CH_REACT).map(c => `<button type="button" data-c="${c}">${CH_REACT[c]}</button>`).join('');
      div.appendChild(pk);
      pk.querySelectorAll('[data-c]').forEach(b => b.onclick = ev => { ev.stopPropagation(); pk.remove(); chToggleReact(div, b.dataset.c); });
      setTimeout(() => document.addEventListener('click', () => pk.remove(), {once: true}), 0);
    };
    const rp = div.querySelector('[data-chreply]');
    if (rp) rp.onclick = e => { e.stopPropagation(); replyTo = m.id; paintReply(); $('#chIn').focus(); };
    div.querySelectorAll('[data-chreact]').forEach(b => b.onclick = e => { e.stopPropagation(); chToggleReact(div, b.dataset.chreact); });
    const q = div.querySelector('[data-chgoto]');
    if (q) q.onclick = e => { e.stopPropagation(); const t = document.querySelector('#chMsgs .ch-m[data-mid="' + q.dataset.chgoto + '"]'); if (!t) { toast('Το αρχικό μήνυμα είναι πιο παλιά'); return; } t.scrollIntoView({block: 'center', behavior: 'smooth'}); t.classList.add('flash'); setTimeout(() => t.classList.remove('flash'), 1600); };
    div.ondblclick = e => { if (e.target.closest('button,a,input,audio,video')) return; const sel = window.getSelection(); if (sel) sel.removeAllRanges(); chZoom(div); };
  };
  const sendOne = async (body, file) => {
    const fd = new FormData();
    fd.append('channel', st.ch); fd.append('body', body); fd.append('file', file);
    if (replyTo) { fd.append('reply_to', replyTo); replyTo = 0; paintReply(); }
    const r = await fetch('api.php?a=chat_send', {method: 'POST', body: fd, credentials: 'same-origin'})
      .then(x => x.json()).catch(() => ({error: 'Απέτυχε η αποστολή'}));
    if (r.error) { toast(r.error, true); return false; }
    return true;
  };
  const send = async () => {
    const body = $('#chIn').value.trim();
    const f = $('#chFile').files[0];
    if (!body && !f && !pend.length) return;
    const btn = $('#chSend'); if (btn) { btn.disabled = true; }
    try {
      let txt = body;
      for (const p of pend.slice()) {          // το κείμενο πάει με την πρώτη εικόνα
        if (!await sendOne(txt, p)) { return; }
        txt = '';
        URL.revokeObjectURL(p._url);
      }
      pend.length = 0; paintPend();
      if (f) {
        if (!await sendOne(txt, f)) { return; }
        txt = '';
        $('#chFile').value = ''; $('#chFn').textContent = '';
      } else if (txt) {
        const rt = replyTo; replyTo = 0; paintReply();
        await api('chat_send', {channel: st.ch, body: txt, reply_to: rt || 0});
      }
      $('#chIn').value = '';
      if (st.lastId === -1) st.lastId = 0;
      load();
    } finally { if (btn) { btn.disabled = false; } }
  };
  $('#chSend').onclick = send;
  $('#chIn').onkeydown = e => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(); } if (e.key === 'Escape' && replyTo) { replyTo = 0; paintReply(); } };
  $('#chFile').onchange = () => { $('#chFn').textContent = $('#chFile').files[0]?.name || ''; };
  /* ── 🎙 Φωνητικό μήνυμα: ΚΡΑΤΑΣ πατημένο το μικρόφωνο → ηχογραφεί· το αφήνεις → φεύγει αμέσως.
     Χωρίς «Στοπ», χωρίς «Στείλε». Πολύ σύντομο πάτημα (<0,7΄΄) = τίποτα, με υπόδειξη. Esc = άκυρο.
     Η διάρκεια μπαίνει στο κείμενο («🎙 0:12») γιατί το webm ηχογράφησης δεν την έχει στα metadata. */
  const recUi = $('#chRec'), micBtn = $('#chMic');
  let rec = null, recChunks = [], recT0 = 0, recTimer = null, recMime = '', recStream = null, recCancel = false, recBusy = false;
  const recFmt = s => Math.floor(s / 60) + ':' + String(Math.floor(s % 60)).padStart(2, '0');
  const recStopTracks = () => { if (recStream) { recStream.getTracks().forEach(t => t.stop()); recStream = null; } clearInterval(recTimer); recTimer = null; };
  const recShow = on => { recUi.hidden = !on; const inp = $('#chIn'); if (inp) { inp.style.display = on ? 'none' : ''; } };
  const recReset = () => { recStopTracks(); rec = null; recChunks = []; recShow(false); recUi.innerHTML = ''; micBtn.classList.remove('on'); };
  const recStart = async () => {
    if (recBusy || (rec && rec.state === 'recording')) { return; }
    if (!navigator.mediaDevices || !window.MediaRecorder) { toast('Ο browser δεν υποστηρίζει ηχογράφηση', true); return; }
    recBusy = true; recCancel = false;
    micBtn.classList.add('on'); recShow(true);
    recUi.innerHTML = `<span class="ch-rec-dot"></span><b>Ηχογράφηση…</b> <span class="ch-rec-t" id="chRecT">0:00</span>
      <span class="mut ch-rec-hint">· άφησε το μικρόφωνο για να σταλεί · Esc = άκυρο</span>`;
    try { recStream = await navigator.mediaDevices.getUserMedia({audio: true}); }
    catch (e) { toast('Δεν δόθηκε πρόσβαση στο μικρόφωνο', true); recBusy = false; recReset(); return; }
    if (recCancel) { recBusy = false; recReset(); return; }   // το άφησε πριν προλάβει να ανοίξει το μικρόφωνο
    recMime = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4', 'audio/ogg;codecs=opus'].find(t => MediaRecorder.isTypeSupported(t)) || '';
    try { rec = new MediaRecorder(recStream, recMime ? {mimeType: recMime, audioBitsPerSecond: 48000} : undefined); }
    catch (e) { toast('Δεν ξεκίνησε η ηχογράφηση', true); recBusy = false; recReset(); return; }
    recChunks = []; recT0 = Date.now();
    rec.ondataavailable = e => { if (e.data && e.data.size) { recChunks.push(e.data); } };
    rec.onstop = async () => {
      const secs = Math.round((Date.now() - recT0) / 1000);
      const blob = new Blob(recChunks, {type: (rec.mimeType || recMime || 'audio/webm').split(';')[0]});
      const cancelled = recCancel, tooShort = (Date.now() - recT0) < 700 || blob.size < 1000;
      recReset(); recBusy = false;
      if (cancelled) { toast('Η ηχογράφηση ακυρώθηκε'); return; }
      if (tooShort) { toast('Κράτα πατημένο το μικρόφωνο όσο μιλάς — άφησέ το για να σταλεί'); return; }
      const ext = /mp4/.test(blob.type) ? 'm4a' : /ogg/.test(blob.type) ? 'ogg' : 'webm';
      const d = new Date(), pad = n => String(n).padStart(2, '0');
      const file = new File([blob], 'voice-' + d.getFullYear() + pad(d.getMonth() + 1) + pad(d.getDate()) + '-' + pad(d.getHours()) + pad(d.getMinutes()) + pad(d.getSeconds()) + '.' + ext, {type: blob.type});
      recShow(true); recUi.innerHTML = '<span class="mut" style="font-size:12px">🎙 Αποστολή φωνητικού (' + recFmt(Math.max(1, secs)) + ')…</span>';
      const ok = await sendOne('🎙 Φωνητικό μήνυμα · ' + recFmt(Math.max(1, secs)), file);
      recShow(false); recUi.innerHTML = '';
      if (ok) { if (st.lastId === -1) st.lastId = 0; load(); }
    };
    rec.start(250);
    recBusy = false;
    recTimer = setInterval(() => { const t = $('#chRecT'); if (t) { t.textContent = recFmt(Math.round((Date.now() - recT0) / 1000)); } if (Date.now() - recT0 > 5 * 60000 && rec && rec.state === 'recording') { rec.stop(); } }, 300);
  };
  const recRelease = () => {
    if (rec && rec.state === 'recording') { try { rec.stop(); } catch (e) { recReset(); } }
    else if (recBusy) { recCancel = true; }   // ακόμη περιμένει άδεια μικροφώνου
  };
  micBtn.style.touchAction = 'none';
  micBtn.onpointerdown = e => { e.preventDefault(); try { micBtn.setPointerCapture(e.pointerId); } catch (x) {} recStart(); };
  micBtn.onpointerup = e => { e.preventDefault(); recRelease(); };
  micBtn.onpointercancel = () => { recRelease(); };
  micBtn.oncontextmenu = e => e.preventDefault();   // long-press σε κινητό δεν ανοίγει μενού
  document.addEventListener('keydown', e => { if (e.key === 'Escape' && rec && rec.state === 'recording') { recCancel = true; rec.stop(); } });
  window.addEventListener('blur', () => { if (rec && rec.state === 'recording') { recRelease(); } });
  $('#chIn').onpaste = e => {
    const items = [...((e.clipboardData || {}).items || [])];
    let took = false;
    items.forEach(it => {
      if (it.kind === 'file') { took = addPend(it.getAsFile()) || took; }
    });
    if (took) { e.preventDefault(); }
  };
  { /* σύρσιμο εικόνας πάνω στη συνομιλία */
    const drop = $('.ch-comp-wrap');
    if (drop) {
      drop.ondragover = e => { e.preventDefault(); drop.style.background = 'var(--hover)'; };
      drop.ondragleave = () => { drop.style.background = ''; };
      drop.ondrop = e => {
        e.preventDefault(); drop.style.background = '';
        [...(e.dataTransfer.files || [])].forEach(addPend);
      };
    }
  }
  if (st.lastId === -1) st.lastId = 0;
  st.lastId = 0;
  load();
  R.chat._t = setInterval(() => {
    if (S.view !== 'chat') { clearInterval(R.chat._t); return; }
    if (st.lastId > 0) load();
  }, 5000);

  /* Οι κουκκίδες παρουσίας πάγωναν στη στιγμή που άνοιξε το chat: ο σφυγμός
     ανανέωνε ΜΟΝΟ τα μηνύματα. Έτσι κάποιος που μπήκε πριν λίγο φαινόταν «Away»
     επ' αόριστον. Ανανεώνουμε τη λίστα κάθε 20΄΄ — αλλά ΕΠΙ ΤΟΠΟΥ, χωρίς να
     ξαναχτίσουμε το DOM, ώστε να μη χάνεται επιλογή/κύλιση/γραφή. */
  const refreshPresence = async () => {
    if (S.view !== 'chat') { clearInterval(R.chat._p); return; }
    const nd = await api('chat_channels').catch(() => null);
    if (!nd) { return; }
    nd.channels.forEach(ch => {
      const row = document.querySelector(`.ch-row[data-ch="${ch.id}"]`);
      if (!row) { return; }
      if (ch.kind === 'dm') {
        const dot = row.querySelector('.ch-av-dot');
        if (dot) { dot.className = 'ch-av-dot ' + (ch.status || 'online'); }
        const sub = row.querySelector('.ch-row-sub');
        if (sub) { sub.innerHTML = (ch.mute ? '🔕 ' : '') + chLbl(ch); }
      }
      /* Τα αδιάβαστα αλλάζουν κι αυτά ενώ κοιτάς — ενημέρωσέ τα μαζί. */
      let n = row.querySelector('.chat-n');
      if (ch.unread) {
        if (!n) { n = document.createElement('span'); n.className = 'chat-n'; row.appendChild(n); }
        n.textContent = ch.unread;
      } else if (n) { n.remove(); }
      /* Και η ώρα της τελευταίας κουβέντας, που δικαιολογεί τη θέση στη λίστα. */
      let tEl = row.querySelector('.ch-row-t');
      if (ch.lastAt) {
        if (!tEl) { row.insertAdjacentHTML('beforeend', chWhen(ch)); }
        else { tEl.outerHTML = chWhen(ch); }
      } else if (tEl) { tEl.remove(); }
    });
    /* ΚΑΙ Η ΣΕΙΡΑ. Χωρίς αυτό, όποιος σου γράφει ενώ κοιτάς το chat θα έμενε εκεί
       που ήταν μέχρι να ξαναμπείς. Μετακινούμε τους ΙΔΙΟΥΣ κόμβους — δεν τους
       ξαναχτίζουμε — ώστε να μη χαθεί επιλογή, κύλιση ή ό,τι γράφεις. */
    const list = document.querySelector('.ch-list');
    if (list) {
      const order = nd.channels.map(ch => document.querySelector(`.ch-row[data-ch="${ch.id}"]`)).filter(Boolean);
      const now = [...list.querySelectorAll('.ch-row[data-ch]')];
      if (order.length === now.length && order.some((el, i) => el !== now[i])) {
        order.forEach(el => list.insertBefore(el, list.querySelector('#chNewGrp')));
      }
    }
  };
  R.chat._p = setInterval(refreshPresence, 20000);
};


/* ═════════ 🔬 ΑΝΑΛΥΣΗ ΡΙΖΩΝ (root-cause analytics) ═════════ */
R.rootcause = async function (days) {
  setTop('Ανάλυση ριζών', 'Πού «πονάει» πραγματικά — ομαδοποίηση προβλημάτων & χρόνου ανά ρίζα');
  const c = $('#content');
  const st = R.rootcause._d = days || R.rootcause._d || 90;
  cnpSkel(c, '<div class="grid g4">' + '<div class="skel" style="height:90px"></div>'.repeat(4) + '</div>');
  let dErr = null;
  const d = await api('rootcause&days=' + st).catch(e => { dErr = e; return null; });
  if (!d) { c.innerHTML = cnpDenied(dErr); return; }
  const pct = d.allTickets ? Math.round(d.totalClassified / d.allTickets * 100) : 0;
  const maxC = Math.max(1, ...d.topCauses.map(x => x.count));
  const aById = {}; d.areas.forEach(a => aById[a.id] = a);
  const cById = {}; d.causes.forEach(c2 => cById[c2.id] = c2);
  const MOB = matchMedia('(max-width:768px)').matches;
  c.innerHTML = `
  <div class="fbar">
    <span class="fbar-sp"></span>
    <span class="fbar-note">${I.tag} <b>${d.totalClassified}</b> / ${d.allTickets} ταξινομημένα (${pct}%)</span>
  </div>
  <div class="fchips">
    ${[30, 90, 180, 365].map(dd => `<button class="kb-chip${dd === st ? ' on' : ''}" data-days="${dd}">${dd === 365 ? '1 έτος' : dd + ' ημέρες'}</button>`).join('')}
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
  /* Φρουρός κυκλώματος «Η ομάδα» (12/9/2026): ό,τι κόβει ο server, δεν ανοίγει καν. */
  if (!cnpCan('team.standup')) { setTop('Standup'); $('#content').innerHTML = cnpDenied({message: 'Το standup δίνεται από το κύκλωμα «Η ομάδα → Standup»'}); return; }
  setTop('Standup', 'Ανοιχτά projects & tickets — τι είναι, πού ανήκει, τι πρέπει να ξέρεις');
  const c = $('#content');
  cnpSkel(c, '<div class="grid g4">' + '<div class="skel" style="height:120px"></div>'.repeat(2) + '</div>');
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
  <div class="fbar">
    ${fChip('Αναζήτηση', `<input class="fchip-s" id="lbQ" value="${esc(st.q)}"
      placeholder="τίτλους, κείμενο, ετικέτες, αρχεία…" style="width:280px">`, !!st.q, '')}
    <span class="fbar-sp"></span>
    <button class="fchip" id="lbNote">${I.edit} Σημείωση</button>
    <button class="fchip" id="lbLink">${I.link} Link</button>
    <label class="fchip fchip-go" style="cursor:pointer">${I.download} Αρχείο<input type="file" id="lbFile" style="display:none"></label>
  </div>
  <div id="lbScope" class="fchips"></div>
  <div id="lbCats" class="fchips"></div>
  <div id="lbBox">${'<div class="skel" style="height:70px;margin-bottom:10px"></div>'.repeat(3)}</div>`;
  const kindIco = {note: I.edit, link: I.link, file: I.download};
  /* Η προεπισκόπηση ήταν το ίδιο το HTML κομμένο στα 66px: τίτλοι και λίστες
     μισοφαίνονταν. Κρατάμε σκέτο κείμενο — η μορφοποίηση ανήκει στο άνοιγμα. */
  const plain = (html, sep) => {
    const t = document.createElement('div');
    // Χωρίς διαχωριστή, ο τίτλος ενότητας κολλάει στην προηγούμενη πρόταση.
    t.innerHTML = (html || '').replace(/<\/(p|h[1-6]|li|div|tr|blockquote)>/gi, sep === false ? ' ' : ' · ');
    return (t.textContent || '').replace(/\s*·\s*(·\s*)+/g, ' · ')
      .replace(/\s+/g, ' ').replace(/^\s*·\s*|\s*·\s*$/g, '').trim();
  };
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
        <div style="display:flex;align-items:center;gap:7px;flex-wrap:wrap">
          <b class="lb-title" data-lbopen="${it.id}" style="font-size:13.5px">${esc(it.title)}</b>
          ${it.kind === 'file' ? `<span class="mut" style="font-size:10.5px">${esc(it.filename)} · ${_libSize(it.size)}</span>` : ''}
          ${expBadge(it)}
          ${it.shared ? `<span class="pill" style="background:var(--ok)1a;color:var(--ok);font-size:9px">${I.users} κοινό</span>` : ''}
          ${st.scope === 'shared' && !it.canEdit ? `<span class="mut" style="font-size:10px">· ${esc(it.ownerName)}</span>` : ''}</div>
        ${it.kind === 'note' && it.body ? `<div class="lb-prev">${esc(plain(it.body))}</div>` : ''}
        ${it.kind === 'link' && it.url ? `<a href="${esc(it.url)}" target="_blank" rel="noopener" style="font-size:12px;color:var(--brand);word-break:break-all">${esc(it.url)}</a>` : ''}
        ${it.updated ? `<div class="mut" style="font-size:10.5px;margin-top:3px">ενημερώθηκε ${esc(dFull(it.updated))}</div>` : ''}
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
    $$('[data-lbopen]', box).forEach(b => b.onclick = () => {
      const it = d.items.find(x => x.id === +b.dataset.lbopen);
      if (!it) { return; }
      if (it.kind === 'link' && it.url) { window.open(it.url, '_blank', 'noopener'); return; }
      if (it.kind === 'file') { window.open('api.php?a=lib_get&id=' + it.id, '_blank'); return; }
      openLibView(it);
    });
    $$('[data-lbget]', box).forEach(b => b.onclick = () => window.open('api.php?a=lib_get&id=' + b.dataset.lbget, '_blank'));
    $$('[data-lbcopy]', box).forEach(b => b.onclick = async () => { await navigator.clipboard.writeText(b.dataset.lbcopy); toast('Αντιγράφηκε'); });
    $$('[data-lbcopyn]', box).forEach(b => b.onclick = async () => { const it = d.items.find(x => x.id === +b.dataset.lbcopyn); const tmp = document.createElement('div'); tmp.innerHTML = it.body || ''; await navigator.clipboard.writeText(tmp.textContent || ''); toast('Κείμενο αντιγράφηκε'); });
    $$('[data-lbpin]', box).forEach(b => b.onclick = async () => { await api('lib_pin', {id: +b.dataset.lbpin}); load(); });
    $$('[data-lbedit]', box).forEach(b => b.onclick = () => { const it = d.items.find(x => x.id === +b.dataset.lbedit); openLibForm(it.kind, it); });
    $$('[data-lbdel]', box).forEach(b => b.onclick = async () => { if (!await cnpConfirm('Διαγραφή;')) { return; } await api('lib_del', {id: +b.dataset.lbdel}); load(); });
  };
  await load();
  let qt;
  cnpSearch('lbQ', v => { st.q = v; return load(); }, 300);
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
  /* Ανάγνωση: εδώ το κείμενο έχει τη μορφοποίησή του ολόκληρη. Πριν, ο μόνος
     τρόπος να διαβάσεις μια σημείωση ήταν να πατήσεις «επεξεργασία». */
  function openLibView(it) {
    const ovl = document.createElement('div'); ovl.className = 'ovl show';
    ovl.innerHTML = `<div class="pal-box lb-read" onclick="event.stopPropagation()">
      <div class="lb-read-h">
        <b>${esc(it.title)}</b>
        <span style="flex:1"></span>
        ${it.canEdit ? `<button class="btn btn-sm btn-o" id="lvEdit">${I.edit} Επεξεργασία</button>` : ''}
        <button class="btn btn-sm btn-o" id="lvCopy" title="αντιγραφή κειμένου">⧉</button>
        <button class="drawer-x" id="lvX">✕</button>
      </div>
      <div class="lb-read-m">
        ${it.category ? `<span class="pill">${esc(it.category)}</span>` : ''}
        ${it.shared ? `<span class="pill" style="background:var(--ok)1a;color:var(--ok)">${I.users} κοινό</span>` : ''}
        ${expBadge(it)}
        ${it.updated ? `<span class="mut" style="font-size:11.5px">ενημερώθηκε ${esc(dFull(it.updated))}</span>` : ''}
        ${(it.tags || '').split(',').filter(x => x.trim()).map(t => `<span class="pill" style="font-size:9.5px">${esc(t.trim())}</span>`).join('')}
      </div>
      <div class="lb-doc">${it.body || '<span class="mut">Χωρίς κείμενο.</span>'}</div>
      <div id="lvFiles" class="lb-read-f"></div>
    </div>`;
    document.body.appendChild(ovl);
    const close = () => ovl.remove();
    $('#lvX', ovl).onclick = close;
    ovl.onclick = close;
    document.addEventListener('keydown', function esc2(e) {
      if (e.key === 'Escape') { close(); document.removeEventListener('keydown', esc2); }
    });
    $('#lvCopy', ovl).onclick = async () => {
      await navigator.clipboard.writeText(plain(it.body, false)); toast('Κείμενο αντιγράφηκε');
    };
    const ed = $('#lvEdit', ovl);
    if (ed) { ed.onclick = () => { close(); openLibForm(it.kind, it); }; }
    if (window.cnpAttachments) {
      window.cnpAttachments($('#lvFiles', ovl), {module: 'library', refType: 'library', refId: it.id});
    }
  }

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
/* ═════════ ✅ ΤΟ ΠΛΑΝΟ ΜΟΥ — to-do list ═════════
   Πρώτα γράφεις τι έχεις να κάνεις, μετά (αν χρειάζεται) το χρεώνεις σε έργο.
   Η παλιά οθόνη ήταν ανάποδα: έπρεπε να βρεις καρτέλα έργου για να γράψεις μία
   αράδα. Το έργο είναι πια ετικέτα, και η σειρά βγαίνει από τον χρόνο —
   εκπρόθεσμα, σήμερα, αύριο, μετά. Η ομαδοποίηση ανά έργο μένει ως δεύτερη
   προβολή, μαζί με τις σημειώσεις «πού έμεινα». */

const TD_BUCKETS = [
  ['over',  'Εκπρόθεσμα',        'var(--bad)'],
  ['today', 'Σήμερα',            'var(--brand)'],
  ['tom',   'Αύριο',             'var(--ink)'],
  ['week',  'Μέσα στην εβδομάδα', 'var(--ink)'],
  ['later', 'Αργότερα',          'var(--mut)'],
  ['none',  'Χωρίς ημερομηνία',  'var(--mut)'],
];
const tdMid = d => { const x = new Date(d); x.setHours(0, 0, 0, 0); return x.getTime(); };
/** Σε ποιον κάδο πέφτει ένα to-do, με βάση την υπενθύμισή του. */
function tdBucket(t) {
  if (!t.remind) { return 'none'; }
  const when = new Date(t.remind.replace(' ', 'T')).getTime();
  const d0 = tdMid(new Date());
  if (when < Date.now()) { return 'over'; }
  const day = tdMid(new Date(when));
  if (day === d0) { return 'today'; }
  if (day === d0 + 86400000) { return 'tom'; }
  return day <= d0 + 7 * 86400000 ? 'week' : 'later';
}
/** «σήμερα 18:00», «Δευ 09:00», «12/09 14:00» — όσο χρειάζεται, όχι παραπάνω. */
function tdWhen(s) {
  const d = new Date(s.replace(' ', 'T'));
  const hm = d.toLocaleTimeString((window.CNP_LOCALE || 'el-GR'), {hour: '2-digit', minute: '2-digit', hour12: false});
  const day = tdMid(d), d0 = tdMid(new Date());
  if (day === d0) { return 'σήμερα ' + hm; }
  if (day === d0 + 86400000) { return 'αύριο ' + hm; }
  if (day === d0 - 86400000) { return 'χθες ' + hm; }
  if (day > d0 && day <= d0 + 7 * 86400000) {
    return d.toLocaleDateString((window.CNP_LOCALE || 'el-GR'), {weekday: 'short'}) + ' ' + hm;
  }
  return d.toLocaleDateString((window.CNP_LOCALE || 'el-GR'), {day: '2-digit', month: '2-digit'}) + ' ' + hm;
}
const tdSql = d => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')} `
  + `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
/** Γρήγορες επιλογές ημερομηνίας — αυτό που θα διάλεγες στο 90% των περιπτώσεων. */
function tdQuick() {
  const now = new Date();
  const at = (plus, h) => { const d = new Date(); d.setDate(d.getDate() + plus); d.setHours(h, 0, 0, 0); return d; };
  const mon = new Date(); mon.setDate(mon.getDate() + ((8 - mon.getDay()) % 7 || 7)); mon.setHours(9, 0, 0, 0);
  const out = [];
  if (now.getHours() < 17) { out.push(['Σήμερα το απόγευμα', at(0, 18)]); }
  out.push(['Αύριο πρωί', at(1, 9)], ['Δευτέρα πρωί', mon], ['Σε μία εβδομάδα', at(7, 9)]);
  return out;
}

R.todos = async function () {
  setTop('Το πλάνο μου', 'Τι έχεις να κάνεις — και πού έμεινες');
  const c = $('#content');
  cnpSkel(c, '<div class="skel" style="height:70px;margin-bottom:12px"></div><div class="skel" style="height:340px"></div>');
  const st = R.todos._s = R.todos._s || {view: localStorage.cnpTodoView || 'date', showDone: false, notes: false, proj: 0};
  let d = null;

  const load = async () => {
    d = R.todos._d = await api('todos_list').catch(() => null);
    if (!d) { c.innerHTML = '<div class="empty" style="padding:44px">Δεν φορτώθηκε το πλάνο</div>'; return; }
    st.view === 'proj' ? paintByProject() : paintByDate();
  };

  /* ───────── κοινά κομμάτια ───────── */

  const addBar = () => `
    <div class="card" style="padding:11px 13px;margin-bottom:12px;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <input class="inp" id="tdNew" placeholder="Τι πρέπει να κάνεις;" autocomplete="off"
        style="flex:1;min-width:220px;font-size:14px;border:none;background:transparent;padding-left:2px">
      <select class="inp" id="tdNewP" title="Σε ποιο έργο" style="width:auto;max-width:190px;padding:6px 9px;font-size:12px">
        <option value="0">— χωρίς έργο —</option>
        ${d.projects.map(p => `<option value="${p.id}" ${st.proj === p.id ? 'selected' : ''}>${esc(p.name)}</option>`).join('')}
      </select>
      <button class="btn btn-o btn-sm" id="tdNewD" title="Πότε">${I.clock} <span id="tdNewDL">Πότε;</span></button>
      <button class="btn btn-p btn-sm" id="tdAdd">${I.plus} Προσθήκη</button>
    </div>`;

  /* Η γραμμή της οθόνης: πόσα ανοιχτά, οι δύο ομαδοποιήσεις και οι ενέργειες
     καθαρίσματος. Το πεδίο προσθήκης ΔΕΝ μπαίνει εδώ — δεν είναι φίλτρο, είναι
     σύνθεση, και ζει ακριβώς πάνω από τη λίστα που γεμίζει. */
  const viewToggle = (open, done) => `
    <div class="fbar">
      <span class="fbar-note"><b>${open}</b> ${open === 1 ? 'ανοιχτό' : 'ανοιχτά'}${
        done ? ` · ${done} ολοκληρωμέν${done === 1 ? 'ο' : 'α'}` : ''}</span>
      <span class="fbar-sp"></span>
      ${d.tasks.length ? `<button class="fchip" id="tdSeed" title="Πρόσθεσε στη λίστα τα ανοιχτά tasks που σου έχουν ανατεθεί">${I.zap || I.plus} ${d.tasks.length === 1 ? 'Φέρε το ανοιχτό task μου' : 'Φέρε τα ' + d.tasks.length + ' ανοιχτά tasks μου'}</button>` : ''}
      ${done ? `<button class="fchip" id="tdClear">Καθάρισε ολοκληρωμένα</button>` : ''}
    </div>
    <div class="fchips">
      <button class="kb-chip${st.view === 'date' ? ' on' : ''}" data-v="date">Κατά ημερομηνία</button>
      <button class="kb-chip${st.view === 'proj' ? ' on' : ''}" data-v="proj">Κατά έργο</button>
    </div>`;

  /* Οι σημειώσεις «πού έμεινα» — μαζεμένες πάνω, όχι μία φόρμα ανά έργο. */
  const notesCard = () => {
    if (!d.notes.length && !st.notes) {
      return `<button class="btn btn-o btn-sm" id="tdNoteOpen" style="margin-bottom:12px">${I.doc} Πού έμεινα…</button>`;
    }
    return `<div class="card" style="margin-bottom:12px">
      <div class="card-h" style="cursor:pointer" id="tdNoteH">
        ${I.doc} Πού έμεινα
        ${d.notes.length ? `<span class="kb-n" style="margin-left:8px">${d.notes.length}</span>` : ''}
        <span style="margin-left:auto;color:var(--mut);font-size:12px">${st.notes ? '▾' : '▸'}</span>
      </div>
      ${st.notes ? `<div class="card-b">
        ${d.notes.map(n => `<div class="td-note" data-np="${n.project}" style="padding:7px 0;border-bottom:1px solid var(--line);cursor:pointer">
          <div style="display:flex;gap:7px;align-items:center">
            <span class="dot" style="background:${n.color}"></span>
            <b style="font-size:12.5px">${esc(n.name)}</b>
            <span class="mut" style="font-size:11px;margin-left:auto">${n.at ? dShort(n.at) : ''}</span></div>
          <div style="font-size:12.5px;color:var(--txt);white-space:pre-wrap;margin-top:3px">${esc(n.note)}</div>
        </div>`).join('')}
        <div style="display:flex;gap:7px;margin-top:10px;flex-wrap:wrap">
          <select class="inp" id="tdNoteP" style="width:auto;max-width:210px;padding:6px 9px;font-size:12px">
            <option value="0">Γενικά</option>
            ${d.projects.map(p => `<option value="${p.id}">${esc(p.name)}</option>`).join('')}
          </select>
          <button class="btn btn-o btn-sm" id="tdNoteAdd">${I.plus} Σημείωση</button>
        </div>
      </div>` : ''}
    </div>`;
  };

  const row = t => `<div class="td-row" data-tid="${t.id}">
    <span class="td-grip" title="σύρε για αλλαγή σειράς">⋮⋮</span>
    <button class="td-box${t.done ? ' on' : ''}" data-ttog="${t.id}" aria-label="${t.done ? 'Αναίρεση' : 'Ολοκλήρωση'}">${t.done ? '✓' : ''}</button>
    <span class="td-txt${t.done ? ' done' : ''}" data-tedit="${t.id}">${esc(t.text)}</span>
    ${t.pname ? `<span class="pill pill-mut td-tag" data-tproj="${t.id}" title="Άλλαξε έργο"><span class="dot" style="background:${t.pcolor}"></span>${esc(t.pname)}</span>` : ''}
    <button class="td-when${t.remind ? (tdBucket(t) === 'over' && !t.done ? ' over' : ' set') : ''}" data-tdate="${t.id}">
      ${t.remind ? I.clock + ' ' + tdWhen(t.remind) : I.clock}</button>
    <button class="td-x" data-tdel="${t.id}" title="Διαγραφή">✕</button>
  </div>`;

  /* ───────── προβολή κατά ημερομηνία ───────── */

  function paintByDate() {
    const open = d.items.filter(t => !t.done);
    const done = d.items.filter(t => t.done);
    const by = {};
    open.forEach(t => (by[tdBucket(t)] = by[tdBucket(t)] || []).push(t));
    Object.values(by).forEach(a => a.sort((x, y) =>
      (x.remind && y.remind ? x.remind.localeCompare(y.remind) : 0) || x.sort - y.sort || x.id - y.id));

    c.innerHTML = viewToggle(open.length, done.length) + addBar() + notesCard()
      + (open.length ? TD_BUCKETS.filter(b => (by[b[0]] || []).length).map(([k, lbl, col]) => `
        <div class="td-grp">
          <div class="td-grp-h" style="color:${col}">${lbl}
            <span class="mut" style="font-weight:600">${by[k].length}</span></div>
          <div class="td-list" data-bucket="${k}">${by[k].map(row).join('')}</div>
        </div>`).join('')
        : `<div class="empty" style="padding:40px">${I.checkSquare}<div style="margin-top:8px">Καθαρή λίστα.</div>
           <div class="mut" style="font-size:12.5px;margin-top:5px">Γράψε πάνω τι έχεις να κάνεις${d.tasks.length ? (d.tasks.length === 1 ? ', ή φέρε το ανοιχτό task σου' : ', ή φέρε τα ' + d.tasks.length + ' ανοιχτά tasks σου') : ''}.</div></div>`)
      + (done.length ? `<div class="td-grp">
          <div class="td-grp-h" id="tdDoneH" style="cursor:pointer;color:var(--mut)">
            ✓ Ολοκληρωμέν${done.length === 1 ? 'ο' : 'α'} <span class="mut" style="font-weight:600">${done.length}</span>
            <span style="font-weight:400">${st.showDone ? '▾' : '▸'}</span></div>
          ${st.showDone ? `<div class="td-list">${done.slice(0, 60).map(row).join('')}</div>` : ''}
        </div>` : '');
    wire();
    const h = $('#tdDoneH'); if (h) { h.onclick = () => { st.showDone = !st.showDone; paintByDate(); }; }
    $$('.td-list[data-bucket]').forEach(el => tdDrag(el));
  }

  /* ───────── προβολή κατά έργο ───────── */

  function paintByProject() {
    const groups = {};
    d.items.forEach(t => {
      const k = t.project || 0;
      (groups[k] = groups[k] || {id: k, name: t.pname || 'Χωρίς έργο', color: t.pcolor || '#8291a9', items: []}).items.push(t);
    });
    d.projects.forEach(p => { if (!groups[p.id]) { groups[p.id] = {id: p.id, name: p.name, color: p.color, items: []}; } });
    const list = Object.values(groups).sort((a, b) =>
      (b.items.filter(x => !x.done).length - a.items.filter(x => !x.done).length) || a.name.localeCompare(b.name));
    const open = d.items.filter(t => !t.done).length;
    const done = d.items.filter(t => t.done).length;

    c.innerHTML = viewToggle(open, done) + addBar() + notesCard()
      + list.map(g => {
        const o = g.items.filter(t => !t.done);
        const dn = g.items.filter(t => t.done);
        const show = st.showDone ? g.items : o;
        return `<div class="card td-grp" style="border-left:3px solid ${g.color};margin-bottom:12px">
          <div class="card-h"><span class="dot" style="background:${g.color}"></span> ${esc(g.name)}
            <span class="kb-n" style="margin-left:auto">${o.length}</span></div>
          <div class="card-b" style="padding-top:4px">
            ${show.length ? `<div class="td-list" data-proj="${g.id}">${show.map(row).join('')}</div>`
              : `<div class="mut" style="font-size:12.5px;padding:6px 0">Τίποτα ανοιχτό${dn.length ? ' · ' + dn.length + (dn.length === 1 ? ' ολοκληρωμένο' : ' ολοκληρωμένα') : ''}</div>`}
          </div></div>`;
      }).join('')
      + (done ? `<div style="text-align:center;margin-top:6px">
          <button class="btn btn-o btn-sm" id="tdDoneT">${st.showDone ? 'Κρύψε' : 'Δείξε'} ${done === 1 ? 'το ολοκληρωμένο' : 'τα ' + done + ' ολοκληρωμένα'}</button></div>` : '');
    wire();
    const t2 = $('#tdDoneT'); if (t2) { t2.onclick = () => { st.showDone = !st.showDone; paintByProject(); }; }
    $$('.td-list[data-proj]').forEach(el => tdDrag(el));
  }

  /* ───────── συμπεριφορές ───────── */

  function wire() {
    let pend = null;   // ημερομηνία που περιμένει το νέο to-do
    const nd = $('#tdNewD');
    if (nd) {
      nd.onclick = e => tdDateMenu(e.currentTarget, pend, v => {
        pend = v;
        $('#tdNewDL').textContent = v ? tdWhen(v) : 'Πότε;';
        nd.classList.toggle('on', !!v);
      });
    }
    const add = async () => {
      const inp = $('#tdNew');
      const txt = inp.value.trim();
      if (!txt) { inp.focus(); return; }
      st.proj = +$('#tdNewP').value || 0;
      await api('todo_add', {text: txt, project: st.proj, remind: pend || ''});
      inp.value = ''; pend = null;
      await load();
      const again = $('#tdNew'); if (again) { again.focus(); }
    };
    if ($('#tdAdd')) { $('#tdAdd').onclick = add; }
    if ($('#tdNew')) { $('#tdNew').onkeydown = e => { if (e.key === 'Enter') { e.preventDefault(); add(); } }; }

    $$('.fchips [data-v]').forEach(b => b.onclick = () => {
      st.view = b.dataset.v; localStorage.cnpTodoView = st.view;
      st.view === 'proj' ? paintByProject() : paintByDate();
    });
    const sd = $('#tdSeed');
    if (sd) { sd.onclick = async () => {
      const r = await api('todo_seed', {tasks: d.tasks.map(t => t.id)});
      toast(r.added ? r.added + (r.added === 1 ? ' προστέθηκε' : ' προστέθηκαν') : 'Ήταν ήδη όλα στη λίστα');
      load();
    }; }
    const cl = $('#tdClear');
    if (cl) { cl.onclick = async () => {
      if (!await cnpConfirm('Να διαγραφούν τα ολοκληρωμένα από τη λίστα;', {ok: 'Διαγραφή'})) { return; }
      const r = await api('todo_clear_done', {all: true});
      toast(r.removed === 1 ? 'Διαγράφηκε 1' : r.removed + ' διαγράφηκαν'); load();
    }; }

    $$('[data-ttog]').forEach(b => b.onclick = async () => {
      const el = b.closest('.td-row');
      el.classList.add('td-fade');
      await api('todo_toggle', {id: +b.dataset.ttog});
      load();
    });
    $$('[data-tdel]').forEach(b => b.onclick = async () => {
      await api('todo_del', {id: +b.dataset.tdel}); load();
    });
    $$('[data-tdate]').forEach(b => b.onclick = e => {
      const id = +b.dataset.tdate;
      const cur = (d.items.find(x => x.id === id) || {}).remind;
      tdDateMenu(e.currentTarget, cur, async v => {
        await api('todo_update', {id, remind: v || ''}); load();
      });
    });
    $$('[data-tproj]').forEach(el => el.onclick = e => {
      const id = +el.dataset.tproj;
      tdProjMenu(e.currentTarget, async pid => { await api('todo_update', {id, project: pid}); load(); });
    });
    $$('[data-tedit]').forEach(sp => sp.onclick = () => tdInlineEdit(sp, load));

    /* σημειώσεις «πού έμεινα» */
    const nh = $('#tdNoteH'); if (nh) { nh.onclick = () => { st.notes = !st.notes; st.view === 'proj' ? paintByProject() : paintByDate(); }; }
    const no = $('#tdNoteOpen'); if (no) { no.onclick = () => { st.notes = true; st.view === 'proj' ? paintByProject() : paintByDate(); }; }
    $$('.td-note').forEach(el => el.onclick = () => tdNote(+el.dataset.np, d, load));
    const na = $('#tdNoteAdd'); if (na) { na.onclick = () => tdNote(+$('#tdNoteP').value, d, load); }
  }

  await load();
};

/* Επεξεργασία κειμένου πάνω στη γραμμή — χωρίς popup. */
function tdInlineEdit(span, done) {
  const id = +span.dataset.tedit;
  const old = span.textContent;
  const inp = document.createElement('input');
  inp.className = 'inp td-inline';
  inp.value = old;
  span.replaceWith(inp);
  inp.focus(); inp.setSelectionRange(old.length, old.length);
  let closed = false;
  const finish = async save => {
    if (closed) { return; } closed = true;
    const v = inp.value.trim();
    if (save && v && v !== old) { await api('todo_update', {id, text: v}); done(); } else { done(); }
  };
  inp.onblur = () => finish(true);
  inp.onkeydown = e => {
    if (e.key === 'Enter') { e.preventDefault(); finish(true); }
    if (e.key === 'Escape') { e.preventDefault(); finish(false); }
  };
}

/* Μικρό μενού ημερομηνίας δίπλα στο κουμπί — όχι πληκτρολόγηση μορφής. */
function tdDateMenu(anchor, current, onPick) {
  document.querySelectorAll('.td-pop').forEach(x => x.remove());
  const p = document.createElement('div');
  p.className = 'td-pop';
  p.innerHTML = tdQuick().map(([lbl, dt]) =>
      `<button data-v="${tdSql(dt)}">${lbl}<span>${tdWhen(tdSql(dt))}</span></button>`).join('')
    + `<div class="td-pop-sep"></div>
       <div class="td-pop-row"><input type="date" id="tdPD"><input type="time" id="tdPT" value="09:00"></div>
       <button data-ok="1" class="td-pop-ok">Ορισμός</button>
       ${current ? '<button data-v="" class="td-pop-clr">Αφαίρεση ημερομηνίας</button>' : ''}`;
  document.body.appendChild(p);
  const r = anchor.getBoundingClientRect();
  p.style.top = Math.min(r.bottom + 6, window.innerHeight - p.offsetHeight - 10) + 'px';
  p.style.left = Math.max(8, Math.min(r.left, window.innerWidth - p.offsetWidth - 10)) + 'px';
  if (current) {
    const dt = new Date(current.replace(' ', 'T'));
    p.querySelector('#tdPD').value = `${dt.getFullYear()}-${String(dt.getMonth() + 1).padStart(2, '0')}-${String(dt.getDate()).padStart(2, '0')}`;
    p.querySelector('#tdPT').value = `${String(dt.getHours()).padStart(2, '0')}:${String(dt.getMinutes()).padStart(2, '0')}`;
  }
  const close = () => { p.remove(); document.removeEventListener('mousedown', out, true); };
  const out = e => { if (!p.contains(e.target) && e.target !== anchor) { close(); } };
  setTimeout(() => document.addEventListener('mousedown', out, true), 0);
  p.querySelectorAll('button[data-v]').forEach(b => b.onclick = () => { close(); onPick(b.dataset.v); });
  p.querySelector('[data-ok]').onclick = () => {
    const dd = p.querySelector('#tdPD').value;
    if (!dd) { return; }
    close(); onPick(dd + ' ' + (p.querySelector('#tdPT').value || '09:00'));
  };
}

/* Αλλαγή έργου από την ετικέτα. */
function tdProjMenu(anchor, onPick) {
  document.querySelectorAll('.td-pop').forEach(x => x.remove());
  const list = (R.todos._d && R.todos._d.projects) || [];
  const p = document.createElement('div');
  p.className = 'td-pop';
  p.innerHTML = '<button data-p="0">— χωρίς έργο —</button>'
    + list.map(x => `<button data-p="${x.id}"><span class="dot" style="background:${x.color}"></span>${esc(x.name)}</button>`).join('');
  document.body.appendChild(p);
  const r = anchor.getBoundingClientRect();
  p.style.top = (r.bottom + 6) + 'px';
  p.style.left = Math.max(8, Math.min(r.left, window.innerWidth - p.offsetWidth - 10)) + 'px';
  const close = () => { p.remove(); document.removeEventListener('mousedown', out, true); };
  const out = e => { if (!p.contains(e.target) && e.target !== anchor) { close(); } };
  setTimeout(() => document.addEventListener('mousedown', out, true), 0);
  p.querySelectorAll('button[data-p]').forEach(b => b.onclick = () => { close(); onPick(+b.dataset.p); });
}

/* «Πού έμεινα» για ένα έργο. */
async function tdNote(pid, d, done) {
  const cur = (d.notes.find(n => n.project === pid) || {}).note || '';
  const name = pid ? ((d.projects.find(p => p.id === pid) || {}).name || '—') : 'Γενικά';
  const v = await cnpPrompt('Πού έμεινες; Τι περιμένεις; Τι να θυμάσαι όταν το ξαναπιάσεις;', {
    title: I.doc + ' Πού έμεινα — ' + esc(name), input: cur, rows: 5, max: 4000,
    placeholder: 'π.χ. έμεινα στη ρύθμιση DNS, περιμένω απάντηση πελάτη…', ok: 'Αποθήκευση'});
  if (v === null) { return; }
  await api('worknote_save', {project: pid, note: v.trim().replace(/\n/g, '<br>')});
  toast(v.trim() ? 'Η σημείωση αποθηκεύτηκε' : 'Η σημείωση αφαιρέθηκε');
  done();
}

/* Σύρσιμο μέσα στον ίδιο κάδο — η σειρά μετράει όταν δεν υπάρχει ημερομηνία. */
function tdDrag(cont) {
  let drag = null;
  cont.querySelectorAll('.td-row').forEach(row => {
    const grip = row.querySelector('.td-grip');
    if (!grip) { return; }
    row.draggable = true;
    row.ondragstart = () => { drag = row; row.classList.add('dragging'); };
    row.ondragend = async () => {
      row.classList.remove('dragging');
      drag = null;
      await api('todo_reorder', {ids: [...cont.querySelectorAll('.td-row')].map(x => +x.dataset.tid)});
    };
  });
  cont.ondragover = e => {
    if (!drag) { return; }
    e.preventDefault();
    const after = [...cont.querySelectorAll('.td-row:not(.dragging)')].reduce((cl, ch) => {
      const b = ch.getBoundingClientRect(); const off = e.clientY - b.top - b.height / 2;
      return (off < 0 && off > cl.offset) ? {offset: off, el: ch} : cl;
    }, {offset: -Infinity, el: null}).el;
    if (after == null) { cont.appendChild(drag); } else { cont.insertBefore(drag, after); }
  };
}

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
  cnpSkel(c, '<div class="skel" style="height:60px;margin-bottom:12px"></div><div class="skel" style="height:420px"></div>');
  let jErr = null;
  const jd = await api('cv_jobs').catch(e => { jErr = e; return null; });
  if (!jd) { c.innerHTML = cnpDenied(jErr); return; }
  const statuses = jd.statuses; window._cvStatuses = statuses;
  window._cvModels = jd.models || {}; window._cvDefaultModel = jd.defaultModel || '';
  const activeJobs = jd.jobs.filter(j => j.active).length;
  c.innerHTML = `
  <div class="ib-tabs set-subtabs" style="margin-bottom:14px;flex-wrap:wrap;border:0;background:0">
    <button class="ib-tab rtab" data-rview="cvs">${I.users || ''} Υποψήφιοι</button>
    ${cnpCan('hr.jobs') ? `<button class="ib-tab rtab" data-rview="jobs">${I.briefcase || I.folder} Θέσεις / Αγγελίες <span class="kb-n" style="margin-left:2px">${activeJobs}</span></button>` : ''}
    <button class="ib-tab rtab" data-rview="traffic">${I.chart || I.pie || '📈'} Επισκεψιμότητα</button>
  </div>
  <div id="cvPane">
    <div class="fbar">
      ${fChip('Αναζήτηση', `<input class="fchip-s" id="cvQ" value="${esc(st.q)}"
        placeholder="όνομα, email, τηλέφωνο…" style="width:230px">`, !!st.q, '')}
      ${fChip('Θέση', `<select class="fchip-s" id="cvJob" style="max-width:240px"><option value="">— κάθε —</option>
        ${jd.jobs.filter(j => j.count).map(j => `<option value="${j.id}" ${st.job == j.id ? 'selected' : ''}>${esc(j.title)} (${j.count})</option>`).join('')}</select>`, !!st.job, '')}
      <button type="button" class="fchip fchip-b${st.dups ? ' on' : ''}" id="cvDups"
        title="Δείξε μόνο όσους υπέβαλαν πολλές φορές (ίδιο email)">${st.dups ? '✓ ' : ''}Διπλότυπα</button>
      <span class="fbar-sp"></span>
      <button class="fchip fchip-go" id="cvAdd">${I.plus} Νέος υποψήφιος</button>
    </div>
    <div id="cvTabs" class="fchips"></div>
    <div id="cvList">${'<div class="skel" style="height:56px;margin-bottom:8px"></div>'.repeat(5)}</div>
    <div id="cvPager" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-top:14px"></div>
  </div>
  <div id="jobsPane" style="display:none"></div>
  <div id="trafficPane" style="display:none"></div>`;
  const setView = v => {
    st.view = v;
    $('#cvPane').style.display = v === 'cvs' ? '' : 'none';
    $('#jobsPane').style.display = v === 'jobs' ? '' : 'none';
    $('#trafficPane').style.display = v === 'traffic' ? '' : 'none';
    /* Η ενεργή καρτέλα δηλώνεται με κλάση, όχι με inline χρώματα — έτσι την
       αναλαμβάνει το ίδιο CSS με όλες τις άλλες υπο-καρτέλες του εργαλείου. */
    $$('.rtab').forEach(b => b.classList.toggle('on', b.dataset.rview === v));
    if (v === 'jobs') { renderJobsPanel($('#jobsPane'), () => { R.recruit(); }); }
    if (v === 'traffic') { renderTrafficPanel($('#trafficPane')); }
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
  cnpSearch('cvQ', v => { st.q = v; st.page = 1; return load(); }, 300);
  $('#cvAdd').onclick = () => openCvAdd(jd.jobs, load);
  $('#cvDups').onclick = () => { st.dups = !st.dups; st.page = 1; load(); };
  setView(st.view || 'cvs');
};

/* ── Επισκεψιμότητα αγγελιών ───────────────────────────────────────────────
   Δείχνει πόσοι είδαν κάθε θέση, ανεξάρτητα από το αν έκαναν αίτηση. Χωρίς
   αυτό δεν ξεχωρίζεις τη θέση που δεν τη βλέπει κανείς από τη θέση που τη
   βλέπουν πολλοί αλλά δεν τους πείθει. ------------------------------------ */
async function renderTrafficPanel(host) {
  const st = renderTrafficPanel._s = renderTrafficPanel._s || {days: 30};

  const spark = (arr, col) => {
    if (!arr || !arr.length) return '';
    const max = Math.max(1, ...arr), w = 3, gap = 1, h = 26;
    return `<svg width="${arr.length * (w + gap)}" height="${h}" style="vertical-align:middle">` +
      arr.map((v, i) => {
        const bh = Math.max(v ? 2 : 1, Math.round(v / max * h));
        return `<rect x="${i * (w + gap)}" y="${h - bh}" width="${w}" height="${bh}" rx="1" fill="${v ? col : 'var(--line)'}"></rect>`;
      }).join('') + '</svg>';
  };
  const pct = (a, b) => b > 0 ? Math.round(a / b * 100) : 0;
  const ago = s => {
    if (!s) return '—';
    const d = Math.floor((Date.now() - new Date(s.replace(' ', 'T')).getTime()) / 86400000);
    return d <= 0 ? 'σήμερα' : (d === 1 ? 'χθες' : `πριν ${d} ημέρες`);
  };

  cnpSkel(host, '<div class="skel" style="height:96px;margin-bottom:12px"></div><div class="skel" style="height:340px"></div>');
  const d = await api('cv_job_views', {days: st.days}).catch(() => null);
  if (!d) { host.innerHTML = `<div class="empty"><div class="big">${I.lock || ''}</div>Δεν ήταν δυνατή η φόρτωση.</div>`; return; }

  const rows = d.rows.slice().sort((a, b) => b.views - a.views || b.uniques - a.uniques);
  const totViews = rows.reduce((s, r) => s + r.views, 0);
  const totUniq = rows.reduce((s, r) => s + r.uniques, 0);
  const totApps = rows.reduce((s, r) => s + r.apps, 0);
  const totForms = rows.reduce((s, r) => s + r.forms, 0);

  const tile = (label, value, sub, col) => `
    <div class="card" style="padding:13px 15px;flex:1;min-width:135px">
      <div class="mut" style="font-size:11.5px;font-weight:700;letter-spacing:.3px;text-transform:uppercase">${label}</div>
      <div style="font-size:25px;font-weight:800;color:${col || 'var(--ink)'};line-height:1.15;margin-top:3px">${value}</div>
      <div class="mut" style="font-size:11.5px;margin-top:1px">${sub}</div>
    </div>`;

  const periods = [[7, '7 ημέρες'], [30, '30 ημέρες'], [90, '3 μήνες'], [365, '1 έτος']];

  host.innerHTML = `
  <div class="card" style="padding:12px 15px;display:flex;gap:9px;align-items:center;flex-wrap:wrap;margin-bottom:12px">
    <span style="font-weight:700;font-size:13.5px">Περίοδος</span>
    ${periods.map(([n, l]) => `<button class="btn btn-sm ${st.days === n ? 'btn-p' : 'btn-o'}" data-tdays="${n}">${l}</button>`).join('')}
    <span style="flex:1"></span>
    <span class="mut" style="font-size:12px">Δεν καταγράφονται IP· μόνο ανώνυμοι μετρητές.</span>
  </div>

  <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px">
    ${tile('Επισκέψεις σελίδας', d.page.views, `${d.page.uniques} μοναδικοί`, 'var(--brand)')}
    ${tile('Προβολές αγγελιών', totViews, `${totUniq} μοναδικοί αναγνώστες`)}
    ${tile('Άνοιγμα φόρμας', totForms, `${pct(totForms, totViews)}% όσων διάβασαν`, '#e0a020')}
    ${tile('Αιτήσεις', totApps, `${pct(totApps, totViews)}% όσων διάβασαν`, '#16a26a')}
  </div>

  <div class="card" style="padding:0;overflow:hidden;margin-bottom:12px">
    <div style="overflow-x:auto">
      <table style="width:100%;border-collapse:collapse;font-size:13.5px;min-width:640px">
        <thead><tr style="background:var(--bg2)">
          <th style="text-align:left;padding:10px 14px;font-size:11.5px;text-transform:uppercase;letter-spacing:.3px;color:var(--mut)">Θέση</th>
          <th style="text-align:center;padding:10px 8px;font-size:11.5px;text-transform:uppercase;color:var(--mut)">Τάση</th>
          <th style="text-align:right;padding:10px 10px;font-size:11.5px;text-transform:uppercase;color:var(--mut)">Προβολές</th>
          <th style="text-align:right;padding:10px 10px;font-size:11.5px;text-transform:uppercase;color:var(--mut)">Μοναδικοί</th>
          <th style="text-align:right;padding:10px 10px;font-size:11.5px;text-transform:uppercase;color:var(--mut)">Φόρμα</th>
          <th style="text-align:right;padding:10px 10px;font-size:11.5px;text-transform:uppercase;color:var(--mut)">Αιτήσεις</th>
          <th style="text-align:right;padding:10px 10px;font-size:11.5px;text-transform:uppercase;color:var(--mut)">Μετατροπή</th>
          <th style="text-align:right;padding:10px 14px;font-size:11.5px;text-transform:uppercase;color:var(--mut)">Τελευταία</th>
        </tr></thead>
        <tbody>
        ${rows.map(r => {
          const conv = pct(r.apps, r.views);
          const col = conv >= 10 ? '#16a26a' : (conv >= 3 ? '#e0a020' : (r.views ? '#e2515f' : 'var(--mut)'));
          return `<tr style="border-top:1px solid var(--line)">
            <td style="padding:10px 14px">
              <span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:${r.active ? '#16a26a' : 'var(--mut)'};margin-right:7px"></span>
              <b>${esc(r.title)}</b>
              ${r.active ? '' : '<span class="mut" style="font-size:11.5px;margin-left:5px">ανενεργή</span>'}
            </td>
            <td style="padding:6px 8px;text-align:center">${spark(r.series, 'var(--brand)')}</td>
            <td style="padding:10px;text-align:right;font-weight:700">${r.views || '—'}</td>
            <td style="padding:10px;text-align:right">${r.uniques || '—'}</td>
            <td style="padding:10px;text-align:right">${r.forms || '—'}</td>
            <td style="padding:10px;text-align:right">${r.apps || '—'}</td>
            <td style="padding:10px;text-align:right;font-weight:800;color:${col}">${r.views ? conv + '%' : '—'}</td>
            <td style="padding:10px 14px;text-align:right;font-size:12.5px" class="mut">${ago(r.lastAt)}</td>
          </tr>`;
        }).join('')}
        </tbody>
      </table>
    </div>
  </div>

  <div style="display:flex;gap:10px;flex-wrap:wrap">
    <div class="card" style="padding:13px 15px;flex:1;min-width:250px">
      <div style="font-weight:700;font-size:13.5px;margin-bottom:9px">Από πού έρχονται</div>
      ${d.breakdown.sources.length
        ? d.breakdown.sources.map(s => {
            const mx = Math.max(...d.breakdown.sources.map(x => x.n));
            return `<div style="display:flex;align-items:center;gap:9px;margin-bottom:6px">
              <span style="flex:0 0 110px;font-size:12.5px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(s.name)}</span>
              <span style="flex:1;height:7px;background:var(--line);border-radius:4px;overflow:hidden"><span style="display:block;height:100%;width:${Math.round(s.n / mx * 100)}%;background:var(--brand)"></span></span>
              <b style="font-size:12.5px;min-width:26px;text-align:right">${s.n}</b></div>`;
          }).join('')
        : '<div class="mut" style="font-size:13px">Δεν υπάρχουν ακόμη δεδομένα.</div>'}
    </div>
    <div class="card" style="padding:13px 15px;flex:1;min-width:250px">
      <div style="font-weight:700;font-size:13.5px;margin-bottom:9px">Συσκευή</div>
      ${d.breakdown.devices.length
        ? d.breakdown.devices.map(s => {
            const tot = d.breakdown.devices.reduce((a, x) => a + x.n, 0) || 1;
            const nm = {mobile: '📱 Κινητό', desktop: '💻 Υπολογιστής', tablet: '▭ Tablet'}[s.name] || esc(s.name);
            return `<div style="display:flex;align-items:center;gap:9px;margin-bottom:6px">
              <span style="flex:0 0 110px;font-size:12.5px">${nm}</span>
              <span style="flex:1;height:7px;background:var(--line);border-radius:4px;overflow:hidden"><span style="display:block;height:100%;width:${Math.round(s.n / tot * 100)}%;background:#7b5cd6"></span></span>
              <b style="font-size:12.5px;min-width:38px;text-align:right">${Math.round(s.n / tot * 100)}%</b></div>`;
          }).join('')
        : '<div class="mut" style="font-size:13px">Δεν υπάρχουν ακόμη δεδομένα.</div>'}
    </div>
  </div>

  ${totViews === 0 ? `<div class="card" style="padding:14px 16px;margin-top:12px;border-left:3px solid var(--brand)">
    <b style="font-size:13.5px">Η μέτρηση μόλις ξεκίνησε</b>
    <div class="mut" style="font-size:13px;margin-top:4px;line-height:1.55">Τα νούμερα θα γεμίσουν καθώς οι επισκέπτες ανοίγουν τις αγγελίες στη δημόσια σελίδα.
    Οι αιτήσεις που φαίνονται είναι όλες όσες έχουν καταγραφεί· οι προβολές μετρούν μόνο από σήμερα και μετά.</div>
  </div>` : ''}`;

  $$('[data-tdays]').forEach(b => b.onclick = () => { st.days = +b.dataset.tdays; renderTrafficPanel(host); });
}

function renderJobsPanel(host, reload) {
  cnpSkel(host, '<div class="skel" style="height:260px"></div>');
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
      let customImgs = (d.customImages || []).slice();
      const renderImgs = () => {
        const box = f.querySelector('#jfImgs'); if (!box) { return; }
        const tile = (v, label, style, thumb) => `<button type="button" data-img="${v}" title="${esc(label)}" style="width:108px;height:66px;border-radius:9px;border:2px solid ${curImg === v ? 'var(--brand)' : 'var(--line)'};cursor:pointer;overflow:hidden;padding:0;position:relative;${style}">
          ${thumb ? `<img src="${imgBase}${v}.jpg" style="width:100%;height:100%;object-fit:cover;display:block" loading="lazy">` : '<div style="font-size:20px;padding-top:8px">✨</div>'}
          <span style="position:absolute;left:0;right:0;bottom:0;background:rgba(9,20,38,.66);color:#fff;font-size:9px;font-weight:700;padding:2px 3px;text-align:center;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${esc(label)}</span>
          ${curImg === v ? '<span style="position:absolute;top:3px;right:3px;background:var(--brand);color:#fff;border-radius:50%;width:16px;height:16px;font-size:10px;display:flex;align-items:center;justify-content:center">✓</span>' : ''}</button>`;
        // πλακίδιο ανεβάσματος + δικές μας εικόνες (με κουμπάκι διαγραφής)
        const upTile = `<button type="button" id="jfImgUp" title="Ανέβασε δική σου φωτογραφία"
          style="width:108px;height:66px;border-radius:9px;border:2px dashed var(--brand);cursor:pointer;overflow:hidden;padding:0;position:relative;background:color-mix(in srgb,var(--brand) 8%,transparent);color:var(--brand)">
          <div style="font-size:19px;padding-top:9px">⬆</div>
          <span style="position:absolute;left:0;right:0;bottom:0;background:var(--brand);color:#fff;font-size:9px;font-weight:700;padding:2px 3px;text-align:center">Ανέβασμα</span>
          </button><input type="file" id="jfImgFile" accept="image/jpeg,image/png,image/webp" style="display:none">
          <span class="mut" style="flex-basis:100%;font-size:11px;margin-top:2px">📐 Ιδανικό μέγεθος: <b>1600×900</b> (16:9). Κόβεται αυτόματα με κεντράρισμα — κράτα το θέμα στο κέντρο. JPG/PNG/WebP έως 8 MB.</span>`;
        const customTiles = customImgs.map(v => tile(v, 'Δική μου', 'background:#eef2f7', true)
          .replace('</button>', `<span data-imgdel="${v}" title="Διαγραφή" style="position:absolute;top:3px;left:3px;background:rgba(226,81,95,.92);color:#fff;border-radius:50%;width:16px;height:16px;font-size:10px;display:flex;align-items:center;justify-content:center">✕</span></button>`)).join('');
        box.innerHTML = tile('', 'Αυτόματη', 'background:linear-gradient(135deg,#e8f6ff,#d3ecff)', false)
          + upTile + customTiles
          + Object.entries(imgPresets).map(([k, l]) => tile(k, l, 'background:#eef2f7', true)).join('');

        box.querySelectorAll('[data-img]').forEach(b => b.onclick = e => {
          if (e.target.dataset && e.target.dataset.imgdel) { return; }   // το ✕ έχει δικό του handler
          curImg = b.dataset.img; renderImgs();
        });
        // διαγραφή ανεβασμένης
        box.querySelectorAll('[data-imgdel]').forEach(x => x.onclick = async e => {
          e.stopPropagation();
          const v = x.dataset.imgdel;
          if (!await cnpConfirm('Διαγραφή αυτής της φωτογραφίας;', {danger: true, ok: 'Διαγραφή'})) { return; }
          const r = await api('cv_job_image_delete', {image: v}).catch(er => ({err: er.message}));
          if (r.err) { toast(r.err, true); return; }
          customImgs = customImgs.filter(z => z !== v);
          if (curImg === v) { curImg = ''; }
          toast('Διαγράφηκε'); renderImgs();
        });
        // ανέβασμα
        const upBtn = box.querySelector('#jfImgUp'), upInp = box.querySelector('#jfImgFile');
        if (upBtn && upInp) {
          upBtn.onclick = () => upInp.click();
          upInp.onchange = async () => {
            const file = upInp.files && upInp.files[0];
            if (!file) { return; }
            if (file.size > 8 * 1024 * 1024) { toast('Μέγιστο μέγεθος 8 MB', true); return; }
            upBtn.disabled = true; upBtn.style.opacity = '.6';
            try {
              const fd = new FormData();
              fd.append('file', file);
              const res = await fetch('api.php?a=cv_job_image_upload', {method: 'POST', body: fd, credentials: 'same-origin'});
              const j = await res.json();
              if (!j || !j.ok) { throw new Error((j && (j.error || j.err)) || 'Αποτυχία ανεβάσματος'); }
              customImgs.unshift(j.image);
              curImg = j.image;
              toast('Η φωτογραφία ανέβηκε ✓');
            } catch (err) {
              toast(err.message || 'Αποτυχία ανεβάσματος', true);
            }
            upInp.value = '';
            renderImgs();
          };
        }
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
    /* Ο καλών μαθαίνει πόσα αρχεία υπάρχουν — αλλιώς ένα κλειστό «Συνημμένα» δεν
       προδίδει τίποτα και το αρχείο μένει αόρατο (δες task #120). */
    if (typeof opts.onCount === 'function') { try { opts.onCount(files.length); } catch (e) { /* η οθόνη δεν πέφτει για ένα μετρητή */ } }
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
      <div class="cnpa-drop-main" style="font-size:13px;color:var(--mut)">📎 <b style="color:var(--brand)">Επισύναψη</b> — έγγραφα, εικόνες, <b>βίντεο</b><span class="cnpa-drop-drag"> (ή σύρε εδώ${opts.paste ? ', ή <b>Ctrl+V</b> για screenshot' : ''})</span></div>
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
  /* Snipping tool → Ctrl+V. Η εικόνα του προχείρου δεν έχει όνομα αρχείου· της δίνουμε
     ώρα και λεπτό, γιατί «image.png» τρεις φορές δεν λέει τίποτα σε κανέναν. */
  if (opts.paste) {
    const zone = host.closest('.drawer') || host.closest('.ovl') || host;
    const onPaste = e => {
      if (!host.isConnected) { document.removeEventListener('paste', onPaste, true); return; }
      if (!zone.contains(e.target)) { return; }
      const items = [...((e.clipboardData || {}).items || [])].filter(i => i.type.startsWith('image/'));
      if (!items.length) { return; }
      e.preventDefault();
      items.forEach(it => {
        const blob = it.getAsFile(); if (!blob) { return; }
        const ext = (blob.type.split('/')[1] || 'png').replace('jpeg', 'jpg');
        const d = new Date();
        const nm = 'screenshot-' + d.toISOString().slice(0, 10) + '-'
          + String(d.getHours()).padStart(2, '0') + String(d.getMinutes()).padStart(2, '0')
          + String(d.getSeconds()).padStart(2, '0') + '.' + ext;
        uploadOne(new File([blob], nm, {type: blob.type}));
      });
    };
    document.addEventListener('paste', onPaste, true);
  }
  api('file_list&module=' + encodeURIComponent(opts.module) + '&ref_type=' + encodeURIComponent(opts.refType || '') + '&ref_id=' + (opts.refId || 0)).then(d => { files = d.files || []; renderList(); }).catch(() => {});
}
window.cnpAttachments = cnpAttachments;   // reusable σε όλα τα views (ES modules)

async function openCv(id) {
  closeDrawer();
  const statuses = window._cvStatuses || {};
  const ovl = document.createElement('div'); ovl.className = 'ovl';   // κλικ έξω ΔΕΝ κλείνει
  const dr = document.createElement('div'); dr.className = 'drawer'; dr.style.width = 'min(780px,96vw)';
  cnpSkel(dr, '<div class="drawer-b"><div class="skel" style="height:340px"></div></div>');
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

/* ═════════ 🆘 Ζήτα βοήθεια από συνάδελφο ═════════
   Μια επείγουσα προσωπική έκκληση — «κάλεσέ με, θέλω τη βοήθειά σου σε αυτό».
   Δεν είναι task ούτε ticket· ανοίγει δυνατή ειδοποίηση στην οθόνη του συναδέλφου. */
const HELP_TPL = 'Κάλεσέ με, θέλω να το συζητήσουμε — χρειάζομαι τη βοήθειά σου σε αυτό το κομμάτι.';

function quickHelp(pre) {
  pre = pre || {};
  closeDrawer();
  const mates = (S.boot.admins || []).filter(a => a.id !== S.boot.me.id);
  const ovl = document.createElement('div');
  ovl.className = 'ovl show';
  ovl.innerHTML = `<div class="pal-box qh-box" onclick="event.stopPropagation()">
    <div class="qh-h"><b>${I.sos} Ζήτα βοήθεια</b>
      <span class="mut" style="font-size:11.5px">θα «χτυπήσει» δυνατά στην οθόνη του συναδέλφου</span></div>
    <div class="qh-b">
      ${pre.taskTitle ? `<div class="qh-ctx">${I.checkSquare} Σχετικά με: <b>${esc(pre.taskTitle)}</b></div>` : ''}
      <label class="lbl">Ποιον χρειάζεσαι</label>
      <select class="inp" id="qhTo"><option value="">— διάλεξε συνάδελφο —</option>
        ${mates.map(a => `<option value="${a.id}">${esc(a.name)}</option>`).join('')}</select>
      <label class="lbl" style="margin-top:12px">Το μήνυμα</label>
      <textarea class="inp" id="qhMsg" rows="4">${esc(HELP_TPL)}</textarea>
      <div class="mut" style="font-size:11.5px;margin-top:6px">Γράψε συγκεκριμένα τι θέλεις να συζητήσετε.</div>
    </div>
    <div class="qh-f">
      <button class="btn btn-o" id="qhX">Άκυρο</button>
      <button class="btn btn-p" id="qhOk">${I.sos} Στείλε την έκκληση</button>
    </div></div>`;
  document.body.appendChild(ovl);
  const q = s => ovl.querySelector(s);
  const close = () => { ovl.remove(); document.removeEventListener('keydown', onEsc, true); };
  const onEsc = e => { if (e.key === 'Escape') { e.stopPropagation(); close(); } };
  document.addEventListener('keydown', onEsc, true);
  ovl.onclick = () => close();
  q('#qhX').onclick = close;
  if (pre.to) { q('#qhTo').value = String(pre.to); }
  setTimeout(() => (pre.to ? q('#qhMsg') : q('#qhTo')).focus(), 60);
  q('#qhOk').onclick = async () => {
    const to = +q('#qhTo').value;
    const message = q('#qhMsg').value.trim();
    if (!to) { toast('Διάλεξε συνάδελφο', true); q('#qhTo').focus(); return; }
    if (!message) { toast('Γράψε τι χρειάζεσαι', true); q('#qhMsg').focus(); return; }
    q('#qhOk').disabled = true;
    const r = await api('help_ask', {to, message, task: pre.task || 0}).catch(e => ({err: e && e.message}));
    if (!r || r.err) { q('#qhOk').disabled = false; toast((r && r.err) || 'Δεν στάλθηκε', true); return; }
    toast('Η έκκληση στάλθηκε — θα το δει αμέσως');
    close();
  };
}

/* Το δυνατό «μπαμ» στον παραλήπτη: μόλις το version φέρει ανοιχτή έκκληση.
   Εμφανίζεται μία φορά (κρατάμε τα id που δείξαμε + το σημειώνουμε στον server). */
const HELP_SHOWN = new Set();
let helpTitleTimer = null;
const HELP_TITLE0 = document.title;

function helpBeep() {
  try {
    const AC = window.AudioContext || window.webkitAudioContext; if (!AC) { return; }
    const ac = new AC();
    [0, 0.28].forEach(t => {
      const o = ac.createOscillator(), g = ac.createGain();
      o.type = 'sine'; o.frequency.value = 880;
      o.connect(g); g.connect(ac.destination);
      g.gain.setValueAtTime(0.0001, ac.currentTime + t);
      g.gain.exponentialRampToValueAtTime(0.15, ac.currentTime + t + 0.02);
      g.gain.exponentialRampToValueAtTime(0.0001, ac.currentTime + t + 0.22);
      o.start(ac.currentTime + t); o.stop(ac.currentTime + t + 0.24);
    });
    setTimeout(() => ac.close().catch(() => {}), 800);
  } catch (e) {}
}

const VOICE_URL = 'https://my.cloudon.gr/projectmanagement/meet.php?room=mteamvoice';

function showHelpAlert(a, force) {
  /* force = ξανάνοιγμα από το καμπανάκι: το ίδιο popup, με τα ίδια κουμπιά. */
  if (!a || (!force && HELP_SHOWN.has(a.id))) { return; }
  HELP_SHOWN.add(a.id);
  if (force) { document.querySelectorAll('.help-ovl').forEach(x => x.remove()); }
  api('help_seen', {id: a.id}).catch(() => {});     // μη ξαναχτυπήσει από επόμενο poll
  const voice = a.kind === 'voice';
  const checkin = a.kind === 'checkin';
  const ovl = document.createElement('div');
  ovl.className = 'ovl show help-ovl';
  if (a.kind === 'mention') {
    /* @Όνομα μέσα σε ενέργεια εργασίας που ΔΕΝ είναι δική του: ερώτηση που περιμένει
       απάντηση. Απαντά εδώ (γράφεται ως ενέργεια στην εργασία → η εκκρεμότητα κλείνει
       μόνη της και ο ερωτών ειδοποιείται) ή ανοίγει την εργασία. */
    ovl.innerHTML = `<div class="help-alert checkin" onclick="event.stopPropagation()">
      <div class="help-ring">💬</div>
      <div class="help-who"><b>${esc(a.from)}</b> σε ρωτά σε εργασία — περιμένει απάντηση</div>
      <div class="help-msg">${esc(a.message)}</div>
      ${a.taskTitle ? `<div class="help-ctx">${I.checkSquare} ${esc(a.taskTitle)}</div>` : ''}
      <input class="inp" id="mnReply" placeholder="Απάντησε εδώ — γράφεται ως ενέργεια στην εργασία" style="margin-top:10px">
      <div class="help-f">
        ${a.taskId ? `<button class="btn btn-o" id="haOpen">${I.checkSquare} Άνοιξε την εργασία</button>` : ''}
        <button class="btn btn-o" id="haDone" title="Το είδα, δεν χρειάζεται απάντηση — φεύγει από τις εκκρεμότητες">✓ Το είδα</button>
        <button class="btn btn-p" id="mnSend">Απάντησε</button>
      </div></div>`;
    document.body.appendChild(ovl);
    helpBeep();
    const close = () => ovl.remove();
    const inp = ovl.querySelector('#mnReply');
    const send = async () => {
      const txt = inp.value.trim(); if (!txt) { inp.focus(); return; }
      const r = await api('check_add', {task: a.taskId, title: 'Απάντηση σε ' + a.from + ': ' + txt}).catch(e => ({err: e && e.message}));
      if (r && r.err) { toast(r.err, true); return; }
      toast('✅ Απάντησες στον ' + a.from); close();
    };
    ovl.querySelector('#mnSend').onclick = send;
    inp.onkeydown = e => { if (e.key === 'Enter') { e.preventDefault(); send(); } };
    ovl.querySelector('#haDone').onclick = async () => { await api('help_done', {id: a.id}).catch(() => {}); toast('Τακτοποιήθηκε'); close(); };
    const op = ovl.querySelector('#haOpen'); if (op) { op.onclick = () => { close(); openTask(a.taskId); }; }
    setTimeout(() => inp.focus(), 50);
    return;
  }
  if (checkin) {
    /* «Τι γίνεται;» από τον επικεφαλή: δύο κουμπιά και προαιρετική γραμμή — η απάντηση
       γυρίζει σε αυτόν που ρώτησε. «Χρειάζομαι βοήθεια» γίνεται κανονικό 🆘 προς αυτόν. */
    ovl.innerHTML = `<div class="help-alert checkin" onclick="event.stopPropagation()">
      <div class="help-ring">💬</div>
      <div class="help-who"><b>${esc(a.from)}</b> ρωτά τι γίνεται</div>
      <div class="help-msg">${esc(a.message)}</div>
      ${a.taskTitle ? `<div class="help-ctx">${I.checkSquare} ${esc(a.taskTitle)}</div>` : ''}
      <input class="inp" id="ckNote" placeholder="Δυο λόγια (προαιρετικά) — π.χ. «τελειώνω σε 1 ώρα» ή «κόλλησα στο X»" style="margin-top:10px">
      <div class="help-f">
        ${a.taskId ? `<button class="btn btn-o" id="haOpen">${I.checkSquare} Άνοιξέ την</button>` : ''}
        <button class="btn btn-danger" id="ckHelp">🆘 Χρειάζομαι βοήθεια</button>
        <button class="btn btn-p" id="ckOk">✅ Όλα καλά</button>
      </div></div>`;
    document.body.appendChild(ovl);
    helpBeep();
    const close = () => ovl.remove();
    const reply = async ans => {
      const r = await api('checkin_reply', {id: a.id, answer: ans, note: ovl.querySelector('#ckNote').value.trim()}).catch(e => ({err: e && e.message}));
      if (r && r.err) { toast(r.err, true); return; }
      toast(ans === 'help' ? '🆘 Ζητήθηκε βοήθεια από τον ' + a.from : '✅ Απάντησες «όλα καλά»'); close();
    };
    ovl.querySelector('#ckOk').onclick = () => reply('ok');
    ovl.querySelector('#ckHelp').onclick = () => reply('help');
    const op = ovl.querySelector('#haOpen'); if (op) { op.onclick = () => { openTask(a.taskId); }; }
    return;
  }
  ovl.innerHTML = `<div class="help-alert${voice ? ' voice' : ''}" onclick="event.stopPropagation()">
    <div class="help-ring">${voice ? '🔊' : '🆘'}</div>
    <div class="help-who"><b>${esc(a.from)}</b> ${voice ? 'σε καλεί στη φωνή' : (a.kind === 'offer' ? 'ζητά να φτιάξεις ΠΡΟΣΦΟΡΑ' : 'χρειάζεται τη βοήθειά σου')}</div>
    <div class="help-msg">${esc(a.message)}</div>
    ${a.taskTitle ? `<div class="help-ctx">${I.checkSquare} ${esc(a.taskTitle)}</div>` : ''}
    <div class="help-f">
      ${voice
        ? `<button class="btn btn-o" id="haOk">Όχι τώρα</button><button class="btn btn-p" id="haVoice">🎙 Μπες στη φωνή</button>`
        : (a.kind === 'offer' && a.taskId ? `<button class="btn btn-o" id="haOpen">${I.checkSquare} Άνοιξε την εργασία</button><button class="btn btn-p" id="haOffer">${I.doc} Νέα προσφορά</button>` : '')
          + (a.kind !== 'offer' ? (a.taskId ? `<button class="btn btn-o" id="haOpen">${I.checkSquare} Άνοιξε το θέμα</button>` : `<button class="btn btn-o" id="haChat">${I.chat} Άνοιξε chat</button>`) : '')
          + `<button class="btn btn-p" id="haOk">Το είδα — καλώ τώρα</button>
             <button class="btn btn-o" id="haDone" title="Τακτοποιήθηκε — φεύγει από τις εκκρεμότητες">✓ Τακτοποιήθηκε</button>`}
    </div></div>`;
  document.body.appendChild(ovl);
  helpBeep();
  // αναβοσβήνει ο τίτλος της καρτέλας μέχρι να το κλείσει (ορατό ακόμη κι σε άλλη καρτέλα)
  if (!helpTitleTimer) {
    let on = false;
    helpTitleTimer = setInterval(() => { on = !on; document.title = on ? '🆘 Βοήθεια!' : HELP_TITLE0; }, 900);
  }
  const close = () => {
    ovl.remove();
    if (!document.querySelector('.help-ovl') && helpTitleTimer) {
      clearInterval(helpTitleTimer); helpTitleTimer = null; document.title = HELP_TITLE0;
    }
  };
  ovl.onclick = close;
  const ok = ovl.querySelector('#haOk'); if (ok) { ok.onclick = close; }
  const dn = ovl.querySelector('#haDone'); if (dn) { dn.onclick = async () => { await api('help_done', {id: a.id}).catch(() => {}); toast('Τακτοποιήθηκε'); close(); }; }
  const op = ovl.querySelector('#haOpen'); if (op) { op.onclick = () => { close(); openTask(a.taskId); }; }
  const ch = ovl.querySelector('#haChat'); if (ch) { ch.onclick = () => { close(); go('chat'); }; }
  const ho = ovl.querySelector('#haOffer'); if (ho) { ho.onclick = () => { close();
    const items = [{icon: I.doc, label: 'Γενική προσφορά', on: () => window.CNP.newOfferFor({client: a.clientId || 0, name: '', task: a.taskId, kind: 'plain'})},
      {icon: I.doc, label: 'PharmacyOne', on: () => window.CNP.newOfferFor({client: a.clientId || 0, name: '', task: a.taskId, kind: 'pharmacyone'})},
      {icon: I.phone, label: 'Τηλεφωνικό κέντρο', on: () => window.CNP.newOfferFor({client: a.clientId || 0, name: '', task: a.taskId, kind: 'pbx'})}];
    window.CNP.miniMenu(ho, items); }; }
  const hv = ovl.querySelector('#haVoice'); if (hv) { hv.onclick = () => { close(); window.open(VOICE_URL, '_blank'); go('chat'); }; }
}

/* Ποιους να καλέσω στη φωνή — pop-up επιλογής (αντί για «όλους» τυφλά). */
async function voiceCallDialog() {
  closeDrawer();
  const meId = S.boot.me.id;
  const isBot = n => /support team|\bbot\b/i.test(n) || String(n).trim() === 'Cloud On';
  const mates = (S.boot.admins || []).filter(a => a.id !== meId && !isBot(a.name));
  const inRoom = new Set();
  const pr = await api('voice_presence').catch(() => null);
  if (pr && pr.in) { pr.in.forEach(p => p.adminId && inRoom.add(p.adminId)); }
  const ovl = document.createElement('div'); ovl.className = 'ovl show';
  ovl.innerHTML = `<div class="pal-box qh-box vcall-box" onclick="event.stopPropagation()">
    <div class="qh-h"><b>🔊 Κάλεσε στη φωνή</b>
      <span class="mut" style="font-size:11.5px">όσους διαλέξεις θα λάβουν δυνατό «έλα τώρα»</span></div>
    <div class="qh-b">
      <div class="vc-tools"><label class="vc-all"><input type="checkbox" id="vcAll"> Επίλεξε όλους</label>
        <span class="mut" id="vcCnt" style="font-size:12px"></span></div>
      <div class="vc-list">
        ${mates.length ? mates.map(a => `<label class="vc-item${inRoom.has(a.id) ? ' in' : ''}">
          <input type="checkbox" class="vc-chk" value="${a.id}" ${inRoom.has(a.id) ? 'disabled' : ''}>
          <span class="vc-ava">${esc(adminIni(a.id) || (a.name || '?').slice(0, 2))}</span>
          <span class="vc-nm">${esc(a.name)}</span>
          ${inRoom.has(a.id) ? '<span class="vc-badge">μέσα</span>' : ''}</label>`).join('')
          : '<div class="mut" style="padding:14px;text-align:center">Κανένας άλλος στην ομάδα.</div>'}
      </div>
      <label class="lbl" style="margin-top:12px">Μήνυμα <span class="mut" style="font-weight:400">— προαιρετικά</span></label>
      <input class="inp" id="vcMsg" value="Έλα στη φωνή της ομάδας — σε περιμένουμε.">
    </div>
    <div class="qh-f"><button class="btn btn-o" id="vcX">Άκυρο</button>
      <button class="btn btn-p" id="vcOk" disabled>Κάλεσε</button></div></div>`;
  document.body.appendChild(ovl);
  const q = sel => ovl.querySelector(sel);
  const close = () => { ovl.remove(); document.removeEventListener('keydown', onEsc, true); };
  const onEsc = e => { if (e.key === 'Escape') { e.stopPropagation(); close(); } };
  document.addEventListener('keydown', onEsc, true);
  ovl.onclick = close; q('#vcX').onclick = close;
  const boxes = () => [...ovl.querySelectorAll('.vc-chk')].filter(c => !c.disabled);
  const picked = () => boxes().filter(c => c.checked).map(c => +c.value);
  const refresh = () => { const n = picked().length;
    q('#vcOk').disabled = !n; q('#vcOk').textContent = n ? `Κάλεσε (${n})` : 'Κάλεσε';
    q('#vcCnt').textContent = n ? `${n} επιλεγμένοι` : ''; };
  ovl.querySelectorAll('.vc-chk').forEach(c => c.onchange = refresh);
  q('#vcAll').onchange = e => { boxes().forEach(c => c.checked = e.target.checked); refresh(); };
  q('#vcOk').onclick = async () => {
    const to = picked(); if (!to.length) { return; }
    q('#vcOk').disabled = true;
    const r = await api('voice_call', {to, message: q('#vcMsg').value.trim()}).catch(() => null);
    toast(r && r.ok ? `Κάλεσα ${r.called} άτομα στη φωνή` : 'Δεν στάλθηκε', !(r && r.ok));
    close();
  };
  setTimeout(() => q('#vcAll') && q('#vcAll').focus(), 60);
}

window.CNP.quickHelp = quickHelp;
window.CNP.showHelpAlert = showHelpAlert;
window.CNP.voiceCallDialog = voiceCallDialog;
