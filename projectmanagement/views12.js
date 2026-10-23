/* ═══════════ 📥 ΕΙΣΕΡΧΟΜΕΝΑ — «τι ήρθε σε μένα, από ποιον, πότε» ═══════════
   ΔΕΝ είναι λίστα εργασιών ανά κατάσταση: είναι ΡΟΗ. Κάθε εργασία που κρατάω εμφανίζεται
   ΜΙΑ φορά, στη θέση της στιγμής που ήρθε σε μένα (μπάλα, μήνυμα, κατάσταση, ανάθεση).

   ΙΔΙΑ ΜΗΧΑΝΗ VIEWS ΜΕ ΤΗ ΛΙΣΤΑ TASKS (23/10/2026): φίλτρα, ομαδοποίηση σε επίπεδα,
   ταξινόμηση, στήλες, προσωπικά/δημόσια views — εδώ περιγράφεται μόνο ό,τι είναι δικό της
   (views4.js → lvScreen). Προεπιλογή: χρονολόγιο (ομάδα ανά ημέρα, νεότερο πρώτο).

   ΠΟΙΟΣ ΤΗΝ ΚΡΑΤΑΕΙ το λέει ο server (cnp_scope_mine — ο κανόνας της μπάλας).
   ΤΟ LED = ΑΔΙΑΒΑΣΤΟ και είναι ΜΟΝΟ ΕΝΔΕΙΞΗ: ανάβει όταν έρθει κάτι νέο, σβήνει όταν
   ανοίξεις την εργασία (openTask → task&seen=1). Δεν ανάβει/σβήνει με το χέρι. */
'use strict';
const {S, api, esc, toast, adminName, adminIni, openTask, cnpLastLbl, today, lvScreen, LV_ENT, LV_FIELDS, LV_COLS, LV_LIST_KEYS, lvPick} = window.CNP;
const R = window.R;

/* Τι έγινε — από τη σκοπιά ΜΟΥ. Το «what» του server είναι η λεπτομέρεια (Κατάσταση: Α → Β). */
const FD_KIND = [
  ['ball', '⚡', 'Μπάλα', 'σου πέρασε τη μπάλα'],
  ['msg', '💬', 'Μήνυμα', 'έγραψε στην εργασία'],
  ['new', '✨', 'Νέα εργασία', 'σου έδωσε νέα εργασία'],
  ['assign', '👤', 'Ανάθεση', 'σου ανέθεσε την εργασία'],
  ['status', '⇄', 'Κατάσταση', 'άλλαξε την κατάσταση'],
  ['collab', '👥', 'Συνεργάτες', 'άλλαξε τους συνεργάτες'],
  ['edit', '✎', 'Αλλαγές', 'έκανε αλλαγές'],
  ['wait', '•', 'Σε περιμένει', 'άνοιξε την εργασία για σένα'],
];
const fdK = k => FD_KIND.find(x => x[0] === k) || FD_KIND[6];
const fdDay = at => String(at || '').slice(0, 10);
const fdAgo = d => d ? Math.round((new Date(today() + 'T00:00') - new Date(d + 'T00:00')) / 86400000) : 9999;
/* Χρονολόγιο: Σήμερα / Χθες / ημέρα της εβδομάδας / μετά σε εβδομάδες — όχι 15 τίτλοι με μία γραμμή. */
function fdDayLbl(d) {
  const diff = fdAgo(d);
  if (!d) { return 'Χωρίς ημερομηνία'; }
  if (diff <= 0) { return 'Σήμερα'; }
  if (diff === 1) { return 'Χθες'; }
  if (diff < 7) { return new Date(d + 'T00:00').toLocaleDateString('el-GR', {weekday: 'long'}) + ' ' + d.slice(8, 10) + '/' + d.slice(5, 7); }
  if (diff < 14) { return 'Την προηγούμενη εβδομάδα'; }
  if (diff < 31) { return 'Πριν από 2–4 εβδομάδες'; }
  return 'Πάνω από μήνα';
}
const fdAgoRank = d => { const n = fdAgo(d); return n < 7 ? n : n < 14 ? 7 : n < 31 ? 8 : 9; };
/* «Μπάλα: Χ → εσύ» το λέει ήδη ο τίτλος («σου πέρασε τη μπάλα») — δεν το ξαναγράφουμε. */
const fdWhat = ev => String((ev || {}).what || '').split(' · ').filter(p => p && !p.startsWith('Μπάλα:')).join(' · ');
const fdSentence = t => { const ev = t.ev || {}; const k = fdK(ev.kind);
  return (ev.by ? ev.by + ' ' + k[3] : (ev.kind === 'wait' ? 'Σε περιμένει' : k[3])) + (fdWhat(ev) ? ' · ' + fdWhat(ev) : ''); };
const fdTime = t => String(t.at || '').replace(' ', 'T');

/* ── Πεδία ΜΟΝΟ των Εισερχομένων (μπαίνουν στη μηχανή· η Λίστα δεν τα βλέπει) ── */
Object.assign(LV_FIELDS, {
  ev:     {label: 'Τι έγινε', kind: 'text', val: t => fdSentence(t)},
  evkind: {label: 'Είδος', kind: 'enum', val: t => fdK((t.ev || {}).kind)[2], rank: t => FD_KIND.indexOf(fdK((t.ev || {}).kind))},
  evby:   {label: 'Από', kind: 'enum', val: t => (t.ev || {}).by || 'Χωρίς αποστολέα', none: 'Χωρίς αποστολέα'},
  evat:   {label: 'Ήρθε', kind: 'date', val: t => fdDay(t.at), rank: t => +new Date(fdTime(t)) || 0},
  evday:  {label: 'Ημέρα', kind: 'enum', val: t => fdDayLbl(fdDay(t.at)), rank: t => fdAgoRank(fdDay(t.at))},
  unread: {label: 'Αδιάβαστο', kind: 'bool', val: t => !!t.unread},
});
const FD_KEYS = LV_LIST_KEYS.filter(k => !['running', 'mins', 'progress', 'done', 'subtask', 'est'].includes(k))
  .concat(['ev', 'evkind', 'evby', 'evat', 'evday', 'unread']);
const FD_COLS = ['title', 'ev', 'evby', 'evat', 'status', 'id', 'project', 'client', 'prio', 'type', 'due', 'sched', 'start', 'tkno', 'holder', 'dept', 'created', 'creator'];

/* ΕΛΑΧΙΣΤΗ γραμμή από προεπιλογή: τίτλος, τι έγινε, κατάσταση, πότε. Τα υπόλοιπα με το «⚙ Ρύθμιση view». */
const FD_DEF = {v: 1, open: 1, mine: 0, who: 'all', users: [], q: '', rules: [], groups: ['evday'],
  sort: [{f: 'evat', d: 'desc'}], cols: ['title', 'ev', 'status', 'evat'], display: 'list', density: 'detailed',
  color: 'status', subs: 'nest', prioShow: 'icon', typeShow: 'icon', widths: {}};

LV_ENT.feed = {
  key: 'feed', title: 'Εισερχόμενα', sub: 'Τι ήρθε σε σένα, από ποιον και πότε — το νεότερο πρώτο',
  get F() { return lvPick(FD_KEYS); }, cols: FD_COLS,
  groupable: ['evday', 'evkind', 'evby', 'unread', 'status', 'prio', 'project', 'client', 'duebucket', 'type', 'dept', 'overdue', 'ticket'],
  def: FD_DEF, who: 0, pins: 0, drag: 0, noun: 'εργασίες',
  presets: {
    fd_time:    {name: 'Χρονολόγιο', cfg: {}},
    fd_unread:  {name: 'Μόνο αδιάβαστα', cfg: {rules: [{f: 'unread', op: 'is', val: true}]}},
    fd_from:    {name: 'Ανά αποστολέα', cfg: {groups: ['evby']}},
    fd_kind:    {name: 'Ανά είδος', cfg: {groups: ['evkind', 'evday']}},
    fd_project: {name: 'Ανά έργο', cfg: {groups: ['project']}},
    fd_status:  {name: 'Ανά κατάσταση', cfg: {groups: ['status']}},
  },
  quick: [
    {k: 'fu', label: '● Αδιάβαστα', rule: {f: 'unread', op: 'is', val: true}},
    {k: 'fb', label: '⚡ Μπάλα σε μένα', rule: {f: 'evkind', op: 'in', val: ['Μπάλα']}},
    {k: 'fm', label: '💬 Μηνύματα', rule: {f: 'evkind', op: 'in', val: ['Μήνυμα']}},
    {k: 'fo', label: '⏰ Εκπρόθεσμες', rule: {f: 'due', op: 'overdue', val: null}},
  ],
  fetch: async () => { const d = await api('feed'); return {tasks: d.items || [], unread: d.unread || 0}; },
  loaded: nd => fdBadge(nd.unread),
  /* LED: ΜΟΝΟ ένδειξη — span, όχι κουμπί. */
  lead: t => `<span class="fd-led${t.unread ? ' on' : ''}" aria-label="${t.unread ? 'Αδιάβαστο' : 'Διαβασμένο'}"${t.unread ? ' title="Κάτι νέο — σβήνει μόλις ανοίξεις την εργασία"' : ''}></span>`,
  rowCls: t => ' fd-row' + (t.unread ? ' fd-unread' : ''),
  cell: (k, t, c) => {
    const ev = t.ev || {};
    if (k === 'ev') {
      const kk = fdK(ev.kind), what = fdWhat(ev);
      return `<span class="fd-ev"><span class="fd-ei" title="${esc(kk[2])}">${kk[1]}</span>${ev.by ? `<b>${esc(ev.by)}</b> ${esc(kk[3])}` : esc(ev.kind === 'wait' ? 'Σε περιμένει' : kk[3])}${
        what ? ` <span class="fd-what">· ${esc(what)}</span>` : ''}${ev.excerpt && c.density !== 'compact' ? ` <span class="fd-exc">«${esc(ev.excerpt)}»</span>` : ''}</span>`;
    }
    if (k === 'evby') { return ev.byId ? `<span class="fd-by"><span class="ava fd-ava">${esc(adminIni(ev.byId))}</span>${esc(ev.by)}</span>` : ''; }
    if (k === 'evat') {
      const lbl = fdDay(t.at) === today() ? String(t.at).slice(11, 16) : cnpLastLbl(t.at);
      return `<span class="fd-at" title="${esc(String(t.at || '').slice(0, 16))}">${esc(lbl)}</span>`;
    }
    if (k === 'evkind') { const kk = fdK(ev.kind); return `<span class="mut">${kk[1]} ${esc(kk[2])}</span>`; }
    if (k === 'evday') { return `<span class="mut">${esc(fdDayLbl(fdDay(t.at)))}</span>`; }
    if (k === 'unread') { return t.unread ? '<span class="fd-led on"></span>' : ''; }
    return '';
  },
  /* Άνοιγμα = διαβάστηκε: ο server το γράφει (task&seen=1)· εδώ σβήνει το LED αμέσως. */
  open: (id, D, renderRes) => {
    const t = D.tasks.find(x => x.id === id);
    if (t && t.unread) { t.unread = false; fdBadge(D.tasks.filter(x => x.unread).length); renderRes(); }
    openTask(id);
  },
  mainBtn: () => `<button class="fchip fchip-go" data-lvact="allread" title="Σβήνει όλα τα LED — οι εργασίες μένουν εδώ όσο τις κρατάς">✓ Όλα διαβασμένα</button>`,
  act: async (a, ctx) => {
    if (a !== 'allread') { return false; }
    const D = ctx.D();
    if (!D.tasks.some(t => t.unread)) { toast('Δεν υπάρχει τίποτα αδιάβαστο'); return true; }
    const r = await api('feed_read', {all: 1}).catch(e => ({err: e.message}));
    if (r && r.err) { toast(r.err, true); return true; }
    D.tasks.forEach(t => { t.unread = false; });
    fdBadge(0); ctx.render(); toast('Όλα διαβασμένα');
    return true;
  },
  empty: n => `<div class="card"><div class="empty" style="padding:46px 16px"><div class="big">${n ? '✅' : '📭'}</div>
    <b style="color:var(--ink);font-size:15px">${n ? 'Τίποτα με αυτά τα φίλτρα' : 'Καθαρό τραπέζι — δεν σε περιμένει τίποτα'}</b>
    <div class="mut" style="font-size:12.5px;margin-top:6px">${n ? 'Βγάλε κάποιο φίλτρο ή διάλεξε άλλο view — π.χ. «Χρονολόγιο».'
      : 'Όταν κάποιος σου περάσει μια εργασία, σου γράψει ή αλλάξει κάτι σε ό,τι κρατάς, θα εμφανιστεί εδώ.'}</div></div></div>`,
};

R.feed = function (arg) { return lvScreen(LV_ENT.feed, arg); };

/* Ο μετρητής στο πλαϊνό μενού — ίδιο στυλ με το Chat. Τον ενημερώνει και ο σφυγμός (app.js). */
function fdBadge(n) {
  if (window.CNP.cnpFeedBadge) { window.CNP.cnpFeedBadge(n); }
}
