/* ═══════════ CloudOn Agent — ΔΙΑΣΥΝΔΕΣΗ 3CX ═══════════
   Φάση 0–2: πού είναι το PBX, ποιος είμαστε απέναντί του, τι υποστηρίζει
   πραγματικά, και πόσο κοστίζει η ώρα του καθενός.
   Ο έλεγχος σύνδεσης ΔΕΝ είναι διακοσμητικός: ρωτάει το ίδιο το PBX και
   «παγώνει το συμβόλαιο» — τι υπάρχει, τι όχι, πόσο γρήγορα απαντά. */
'use strict';
const {S, api, esc, toast, setTop, cnpConfirm, cnpDenied, cnpCan, dShort, drawer, closeDrawer, I, $, $$} = window.CNP;
const R = window.R;

R.pbx = async function () {
  if (!cnpCan('comms.pbx')) {
    setTop('Διασύνδεση 3CX');
    $('#content').innerHTML = cnpDenied({message: 'Χρειάζεται το κύκλωμα «Επικοινωνίες → Διασύνδεση 3CX»'});
    return;
  }
  setTop('Διασύνδεση 3CX', 'CloudOn Agent — σύνδεση με το τηλεφωνικό κέντρο');
  const c = $('#content');
  c.innerHTML = '<div class="skel" style="height:220px;margin-bottom:14px"></div><div class="skel" style="height:300px"></div>';
  const [d, m, bp, ac] = await Promise.all([
    api('pbx_settings').catch(() => null),
    api('pbx_map').catch(() => null),
    api('pbx_plan').catch(() => null),
    api('pbx_ai_calls').catch(() => null),
  ]);
  if (!d) { c.innerHTML = '<div class="card"><div class="card-b mut">Δεν φορτώθηκε.</div></div>'; return; }
  const ed = d.canEdit;

  const probe = d.probe;
  const state = !d.configured ? ['idle', 'Δεν έχει ρυθμιστεί', 'Συμπλήρωσε τα στοιχεία και πάτα «Έλεγχος σύνδεσης»']
    : !probe ? ['idle', 'Ρυθμισμένο — ανέλεγκτο', 'Πάτα «Έλεγχος σύνδεσης» για να δεις τι υποστηρίζει']
    : probe.ok === probe.total ? ['ok', 'Συνδεδεμένο', 'Όλοι οι έλεγχοι πέρασαν']
    : probe.ok === 0 ? ['bad', 'Δεν συνδέεται', 'Κανένας έλεγχος δεν πέρασε']
    : ['warn', probe.ok + '/' + probe.total + ' έλεγχοι', 'Μερική λειτουργία — δες τι λείπει'];

  const rateRow = p => `<div class="pbx-r">
    <span class="pbx-rn">${esc(p.name)}</span>
    <span class="pbx-rv">${p.rate === null
      ? `<span class="mut">γενικό ${d.fallbackRate}€</span>`
      : `<b>${p.rate.toFixed(2)}€</b><span class="mut">/ώρα</span>`}</span>
    <span class="pbx-rf mut">${p.from ? 'από ' + dShort(p.from) : ''}</span>
    ${ed ? `<button class="btn btn-o btn-sm" data-rate="${p.id}" data-name="${esc(p.name)}"
      data-cur="${p.rate === null ? '' : p.rate}">${p.rate === null ? 'Όρισε' : 'Αλλαγή'}</button>` : ''}
  </div>`;

  c.innerHTML = `
  <div class="card pbx-state ${state[0]}">
    <div class="card-b">
      <div class="pbx-sh"><span class="pbx-dot"></span><b>${esc(state[1])}</b>
        <span class="mut">${esc(state[2])}</span>
        ${probe ? `<span class="mut pbx-when">έλεγχος: ${esc(probe.at)}</span>` : ''}
      </div>
      ${probe ? `<div class="pbx-checks">${Object.entries(probe.checks).map(([k, ch]) => `
        <div class="pbx-c ${ch.ok ? 'ok' : (ch.critical === false ? 'opt' : 'bad')}">
          <span class="pbx-ci">${ch.ok ? '✔' : (ch.critical === false ? '–' : '✕')}</span>
          <span class="pbx-cl"><b>${esc(ch.label)}${ch.critical === false ? '<span class="pbx-opt">προαιρετικό</span>' : ''}</b><span class="mut">${esc(ch.info || '')}</span></span>
          <span class="mut pbx-cm">${ch.ms}ms</span>
        </div>`).join('')}</div>` : ''}
    </div>
  </div>

  <div class="card"><div class="card-h">${I.gear} Στοιχεία σύνδεσης</div>
    <div class="card-b">
      <div class="frow">
        <div><label class="lbl">URL του PBX</label>
          <input class="inp" id="pxUrl" value="${esc(d.url || '')}" placeholder="https://cloudon.3cx.gr" ${ed ? '' : 'disabled'}></div>
        <div><label class="lbl">Client ID</label>
          <input class="inp" id="pxCid" value="${esc(d.clientId || '')}" placeholder="από 3CX → Admin → Integrations → API" ${ed ? '' : 'disabled'}></div>
      </div>
      <label class="lbl" style="margin-top:11px">Client Secret</label>
      <input class="inp" id="pxSec" type="password" autocomplete="new-password"
        placeholder="${d.hasSecret ? '•••••••• — αποθηκευμένο· άφησέ το κενό για να μείνει' : 'επικόλλησε το secret'}" ${ed ? '' : 'disabled'}>
      <div class="mut" style="font-size:11.5px;margin-top:6px">
        🔒 Το secret αποθηκεύεται κρυπτογραφημένο (AES-256-GCM) και <b>δεν επιστρέφει ποτέ</b> στην οθόνη.
        Δώσε ρόλο <b>μόνο ανάγνωσης</b> — αυτή η έκδοση δεν κάνει καμία ενέργεια στο κέντρο.
      </div>
      ${ed ? `<div style="display:flex;gap:9px;margin-top:14px;flex-wrap:wrap">
        <button class="btn btn-p" id="pxSave">${I.save || ''} Αποθήκευση</button>
        <button class="btn btn-o" id="pxTest">${I.zap} Έλεγχος σύνδεσης</button>
        <span style="flex:1"></span>
        <button class="btn btn-o btn-sm" id="pxLog">Τεχνικό ημερολόγιο</button>
      </div>` : '<div class="mut" style="margin-top:12px">Μόνο προβολή — χρειάζεται «Ρύθμιση & κόστη».</div>'}
      <div id="pxMsg" class="mut" style="font-size:12px;margin-top:10px"></div>
    </div></div>

  ${m ? `<div class="card"><div class="card-h">${I.tree || I.users} Χάρτης τηλεφωνικού κέντρου
    <span class="mut" style="font-weight:400;font-size:11.5px;margin-left:auto">
      ${m.lastSync ? 'τελευταίος συγχρονισμός: ' + esc(m.lastSync.at) : 'δεν έχει συγχρονιστεί ποτέ'}</span></div>
    <div class="card-b">
      <div class="mut" style="font-size:12px;margin-bottom:10px">
        Ποιο extension ανήκει σε ποιον. Η αντιστοίχιση γίνεται αυτόματα με το <b>email</b> —
        τα ονόματα διαφέρουν («Βασίλης Βάκρινος» vs «Vasilis Vakrinos») και τα DN αλλάζουν.
        Ό,τι ορίσεις χειροκίνητα <b>δεν το ξαναγράφει</b> ο συγχρονισμός.
      </div>
      ${ed ? `<button class="btn btn-o btn-sm" id="pxSync" style="margin-bottom:12px">${I.repeat || I.zap} Συγχρονισμός τώρα</button>` : ''}
      <div class="pbx-map">
        ${m.items.length ? m.items.map(it => `<div class="pbx-m${it.active ? '' : ' off'}">
          <span class="pbx-mt ${esc(it.type)}">${it.type === 'extension' ? 'DN' : it.type === 'queue' ? 'ΟΥΡΑ' : 'ΟΜΑΔΑ'}</span>
          <span class="pbx-md">${esc(it.dn)}</span>
          <span class="pbx-mn">${esc(it.name || '—')}${it.email ? `<span class="mut">${esc(it.email)}</span>` : ''}</span>
          ${ed ? `<select class="inp pbx-ms" data-map="${it.id}">
            <option value="0">— κανένας —</option>
            ${m.admins.map(a => `<option value="${a.id}" ${a.id === it.admin ? 'selected' : ''}>${esc(a.name)}</option>`).join('')}
          </select>` : `<span class="pbx-mn">${esc(it.adminName || '—')}</span>`}
          <span class="pbx-mb ${esc(it.by)}">${it.by === 'email' ? 'από email' : it.by === 'manual' ? 'χειροκίνητα' : ''}</span>
        </div>`).join('') : '<div class="mut" style="font-size:12.5px">Κανένα δεδομένο — πάτα «Συγχρονισμός τώρα».</div>'}
      </div>
    </div></div>` : ''}

  ${bp && bp.plan ? `<div class="card"><div class="card-h">${I.tree || I.list || I.gear} Δομή κέντρου
    <span class="mut" style="font-weight:400;font-size:11.5px;margin-left:auto">έλεγχος: ${esc(bp.plan.at)}</span></div>
    <div class="card-b">
      <div class="mut" style="font-size:12px;margin-bottom:10px">
        Η επιθυμητή δομή (τμήματα, ωράρια, ουρές, AI ρεσεψιόν) είναι γραμμένη στον κώδικα. Εδώ φαίνεται
        <b>τι διαφέρει</b> στο ζωντανό κέντρο. Η <b>δρομολόγηση</b> των γραμμών αλλάζει μόνο με το δικό της κουμπί.</div>
      <div class="pbx-bp">${bp.plan.steps.map(s => `<div class="pbx-bs ${esc(s.state)} ${esc(s.risk)}">
        <span class="pbx-bi">${s.state === 'ok' ? '✔' : s.state === 'change' ? '●' : '✕'}</span>
        <span class="pbx-bl"><b>${esc(s.label)}</b><span class="mut">${esc(s.detail)}</span></span>
        ${ed && s.risk === 'route' && s.state === 'change'
          ? `<button class="btn btn-o btn-sm" data-bp="${esc(s.key)}">${s.key === 'route_ai' ? 'Στείλε τις γραμμές στην AI' : 'Πίσω στο script'}</button>` : ''}
      </div>`).join('')}</div>
      ${ed ? `<div style="display:flex;gap:9px;margin-top:12px;flex-wrap:wrap;align-items:center">
        <button class="btn btn-p" id="pxApply" ${bp.plan.pending ? '' : 'disabled'}>${I.zap} Εφαρμογή ${bp.plan.pending} αλλαγών</button>
        <button class="btn btn-o btn-sm" id="pxPlan">Ξανά έλεγχος</button>
        <span class="mut" style="font-size:11.5px">Κάθε βήμα καταγράφεται στο τεχνικό ημερολόγιο.</span></div>` : ''}
    </div></div>` : ''}

  ${ac ? `<div class="card"><div class="card-h">${I.zap} AI ρεσεψιόν — τελευταίες κλήσεις
    <span class="pbx-mode ${esc(ac.mode)}">${esc(ac.modeLabel)}</span>
    ${ac.canEdit ? `<select class="inp pbx-voice" id="pxVoice" title="Φωνή — αλλάζει αμέσως, κάλεσε το 902 για να την ακούσεις">${Object.entries(ac.voices || {}).map(([k, v]) => `<option value="${esc(k)}" ${k === ac.voice ? 'selected' : ''}>${esc(v)}</option>`).join('')}</select>` : `<span class="mut" style="font-size:11.5px;margin-left:8px">φωνή: ${esc(ac.voice || '')}</span>`}
    <span class="mut" style="font-weight:400;font-size:11.5px;margin-left:auto">η εκπαίδευση ξεκινά από εδώ: διάβασε τι είπε, σημείωσε τι θα έλεγε καλύτερα</span></div>
    <div class="card-b">
      ${ac.items.length ? `<div class="pbx-ai">${ac.items.map(x => `<details class="pbx-aic">
        <summary>
          <span class="pbx-ait mut">${esc(x.at)}</span>
          <span class="pbx-ain">${esc(x.otherName || x.other || '—')}${x.otherName && x.other ? `<span class="mut">${esc(x.other)}</span>` : ''}</span>
          <span class="mut">${callHm(x.seconds)}</span>
          <span class="pbx-ais">${x.summary ? esc(x.summary.slice(0, 140)) : (x.transcribed ? '<span class="mut">χωρίς περίληψη</span>' : '<span class="mut">χωρίς κείμενο</span>')}</span>
        </summary>
        ${x.summary ? `<div class="pbx-aisum">${esc(x.summary)}</div>` : ''}
        <pre class="pbx-aitr">${esc(x.transcript || 'Χωρίς απομαγνητοφώνηση. Ενεργοποιείται από το βήμα «Ηχογράφηση και απομαγνητοφώνηση» στη Δομή κέντρου — ισχύει για τις επόμενες κλήσεις.')}</pre>
      </details>`).join('')}</div>` : '<div class="mut" style="font-size:12.5px">Καμία κλήση στην AI ρεσεψιόν ακόμη. Κάλεσε το 902 από το εσωτερικό σου.</div>'}
    </div></div>` : ''}

  <div class="card"><div class="card-h">${I.coin} Κόστος ανά χειριστή
    <span class="mut" style="font-weight:400;font-size:11.5px;margin-left:auto">
      όπου δεν έχει οριστεί, ισχύει το γενικό ${d.fallbackRate}€/ώρα</span></div>
    <div class="card-b">
      <div class="mut" style="font-size:12px;margin-bottom:10px">
        Κάθε αλλαγή ισχύει <b>από ημερομηνία</b>, ώστε μια αύξηση να μην ξαναγράψει
        αναδρομικά το κόστος παλιών κλήσεων.</div>
      <div class="pbx-rates">${d.people.map(rateRow).join('')}</div>
    </div></div>

  <div class="card" id="pxLogCard" hidden><div class="card-h">${I.list || I.doc} Τεχνικό ημερολόγιο</div>
    <div class="card-b" id="pxLogBody"></div></div>`;

  const msg = (t, bad) => { const m = $('#pxMsg'); if (m) { m.textContent = t; m.style.color = bad ? 'var(--bad)' : ''; } };

  if (ed) {
    $('#pxSave').onclick = async () => {
      const r = await api('pbx_save', {url: $('#pxUrl').value.trim(), clientId: $('#pxCid').value.trim(),
        secret: $('#pxSec').value}).catch(e => ({err: e.message}));
      if (r.err) { msg(r.err, true); return; }
      toast('Αποθηκεύτηκε'); R.pbx();
    };
    $('#pxTest').onclick = async () => {
      const b = $('#pxTest'); b.disabled = true; b.textContent = 'Έλεγχος…';
      msg('Ρωτάω το PBX…');
      const r = await api('pbx_probe', {}).catch(e => ({err: e.message}));
      b.disabled = false; b.innerHTML = I.zap + ' Έλεγχος σύνδεσης';
      if (r.err) { msg(r.err, true); return; }
      toast(r.probe.ok === r.probe.total ? 'Όλα καλά' : r.probe.ok + '/' + r.probe.total + ' έλεγχοι πέρασαν',
        r.probe.ok !== r.probe.total);
      R.pbx();
    };
    $('#pxLog').onclick = async () => {
      const card = $('#pxLogCard');
      if (!card.hidden) { card.hidden = true; return; }
      const r = await api('pbx_log').catch(() => ({items: []}));
      $('#pxLogBody').innerHTML = r.items.length
        ? r.items.map(x => `<div class="pbx-lg ${esc(x.status)}">
            <span class="pbx-lt mut">${esc(x.at)}</span>
            <span class="pbx-lc">${esc(x.channel)}</span>
            <span>${esc(x.message)}</span></div>`).join('')
        : '<div class="mut" style="font-size:12.5px">Καμία εγγραφή ακόμη.</div>';
      card.hidden = false;
    };
    const sb = $('#pxSync');
    if (sb) { sb.onclick = async () => {
      sb.disabled = true; sb.textContent = 'Συγχρονισμός…';
      const r = await api('pbx_sync', {}).catch(e => ({err: e.message}));
      sb.disabled = false;
      if (r.err) { toast(r.err, true); R.pbx(); return; }
      const s2 = r.sync;
      toast(s2.errors && s2.errors.length
        ? 'Με σφάλματα: ' + s2.errors[0]
        : `Νέα ${s2.new} · ενημερώθηκαν ${s2.updated} · αντιστοιχισμένα ${s2.matched}`,
        !!(s2.errors && s2.errors.length));
      R.pbx();
    }; }
    $$('[data-map]').forEach(sel => sel.onchange = async () => {
      const r = await api('pbx_map_save', {id: +sel.dataset.map, admin: +sel.value})
        .catch(e => ({err: e.message}));
      if (r.err) { toast(r.err, true); return; }
      toast('Αποθηκεύτηκε — ο συγχρονισμός δεν θα το αλλάξει');
      R.pbx();
    });
    const vs = $('#pxVoice');
    if (vs) { vs.onchange = async () => {
      vs.disabled = true;
      const r = await api('pbx_ai_voice', {voice: vs.value}).catch(e => ({err: e.message}));
      vs.disabled = false;
      if (r.err) { toast(r.err, true); return; }
      toast('Φωνή: ' + vs.options[vs.selectedIndex].text + ' — κάλεσε το 902 για να την ακούσεις');
    }; }
    const ap = $('#pxApply');
    if (ap) { ap.onclick = async () => {
      if (!(await window.CNP.cnpConfirm('Να εφαρμοστούν στο 3CX όλες οι αλλαγές χαμηλού ρίσκου (τμήματα, ωράρια, ουρές, εσωτερικό 900, AI ρεσεψιόν, καθάρισμα παλιών τμημάτων);\n\nΗ δρομολόγηση των γραμμών ΔΕΝ αλλάζει από εδώ.', {ok: 'Εφαρμογή', cancel: 'Άκυρο'}))) { return; }
      ap.disabled = true; ap.textContent = 'Εφαρμογή…';
      const r = await api('pbx_apply', {keys: []}).catch(e => ({err: e.message}));
      if (r.err) { toast(r.err, true); R.pbx(); return; }
      toast(r.result.errors.length ? 'Με σφάλματα: ' + r.result.errors[0] : 'Έγιναν ' + r.result.done.length + ' βήματα', !!r.result.errors.length);
      R.pbx();
    }; }
    const pl = $('#pxPlan'); if (pl) { pl.onclick = () => R.pbx(); }
    $$('[data-bp]').forEach(b => b.onclick = async () => {
      const toAi = b.dataset.bp === 'route_ai';
      if (!(await window.CNP.cnpConfirm(toAi
        ? 'Όλες οι εισερχόμενες κλήσεις (Sip1 και Cyprus) θα απαντώνται από την AI ρεσεψιόν (902).\n\nΤο script 806 μένει ως εφεδρεία — «Πίσω στο script» το επαναφέρει.'
        : 'Οι εισερχόμενες κλήσεις επιστρέφουν στο παλιό script 806.',
        {ok: toAi ? 'Στείλε στην AI' : 'Πίσω στο script', cancel: 'Άκυρο', danger: !toAi}))) { return; }
      b.disabled = true;
      const r = await api('pbx_apply', {keys: [b.dataset.bp]}).catch(e => ({err: e.message}));
      if (r.err) { toast(r.err, true); R.pbx(); return; }
      toast(r.result.errors.length ? 'Σφάλμα: ' + r.result.errors[0] : 'Η δρομολόγηση άλλαξε', !!r.result.errors.length);
      R.pbx();
    });
    $$('[data-rate]').forEach(b => b.onclick = async () => {
      const v = await window.CNP.cnpDialog({
        title: 'Κόστος ώρας — ' + b.dataset.name,
        body: 'Ισχύει από σήμερα και μετά. Οι παλιές κλήσεις κρατούν το κόστος που ίσχυε τότε.',
        input: b.dataset.cur || '', placeholder: 'π.χ. 18.50', ok: 'Αποθήκευση'});
      if (v === null) { return; }
      const r = await api('pbx_rate_save', {admin: +b.dataset.rate, rate: v, from: window.CNP.today()})
        .catch(e => ({err: e.message}));
      if (r.err) { toast(r.err, true); return; }
      toast('Καταχωρήθηκε'); R.pbx();
    });
  }
};


/* ═══════════ ΤΗΛΕΦΩΝΙΚΗ ΔΡΑΣΤΗΡΙΟΤΗΤΑ ═══════════
   Η ερώτηση που απαντά: ποιος μίλησε με ποιον, πόση ώρα, ποιος πελάτης μας
   απασχολεί — και τι έγινε σε κάθε κλήση. Χωρίς αυτό, ο χρόνος στο τηλέφωνο
   είναι αόρατος στη μέρα της ομάδας. */
const CALL_CAT = {support: 'Υποστήριξη', technical: 'Τεχνικό', training: 'Εκπαίδευση',
  consulting: 'Συμβουλευτική', sales: 'Πωλήσεις', billing: 'Χρεώσεις',
  complaint: 'Παράπονο', other: 'Άλλο'};
const CALL_BILL = {billable: ['Χρεώσιμο', '#16a26a'], free: ['Χωρίς χρέωση', '#8595ac'],
  contract: ['Στο συμβόλαιο', '#0090dd'], internal: ['Εσωτερικό', '#7b5cd6'],
  warranty: ['Εγγύηση', '#e0a020']};
const callHm = s2 => {
  s2 = Math.max(0, +s2 || 0);
  const h = Math.floor(s2 / 3600), m = Math.floor(s2 % 3600 / 60);
  return h ? h + 'ω ' + m + '΄' : (m ? m + '΄ ' + (s2 % 60) + '΄΄' : s2 + '΄΄');
};

R.calls = async function () {
  if (!cnpCan('reports.calls')) {
    setTop('Τηλεφωνική δραστηριότητα');
    $('#content').innerHTML = cnpDenied({message: 'Χρειάζεται «Αναφορές → Τηλεφωνική δραστηριότητα»'});
    return;
  }
  /* Οι εσωτερικές κλήσεις (συνάδελφος → συνάδελφο) ΔΕΝ μετριούνται: το PBX δεν
     τις δίνει από καμία αναφορά που δουλεύει. Καλύτερα να το λέμε παρά να
     νομίζει κανείς ότι βλέπει όλο τον χρόνο στο τηλέφωνο. */
  setTop('Τηλεφωνική δραστηριότητα', 'Ποιος μίλησε με ποιον, πόση ώρα, ποιος πελάτης απασχολεί — εκτός εσωτερικών κλήσεων');
  const c = $('#content');
  const st = R.calls._s = R.calls._s || {d: window.CNP.today(), days: 1, who: 0,
    dir: '', ans: '', bill: '', cat: '', min: 0, q: '', open: false};
  c.innerHTML = '<div class="skel" style="height:90px;margin-bottom:14px"></div><div class="skel" style="height:420px"></div>';
  const qs = `d=${st.d}&days=${st.days}&who=${st.who}&dir=${st.dir}&ans=${st.ans}`
    + `&bill=${st.bill}&cat=${encodeURIComponent(st.cat)}&min=${st.min}&q=${encodeURIComponent(st.q)}`;
  const d = await api('calls_report&' + qs).catch(() => null);
  if (!d) { c.innerHTML = '<div class="card"><div class="card-b mut">Δεν φορτώθηκε.</div></div>'; return; }
  const t = d.totals;

  const nActive = ['dir', 'ans', 'bill', 'cat', 'q'].filter(k => st[k]).length + (st.min > 0 ? 1 : 0);
  const tile = (n, l, col) => `<div class="su-stat"><div><div class="n" style="color:${col || ''}">${n}</div>
    <div class="l">${l}</div></div></div>`;

  const dirIco = x => x === 'in' ? '<span class="cl-d in" title="Εισερχόμενη">↙</span>'
    : x === 'out' ? '<span class="cl-d out" title="Εξερχόμενη">↗</span>'
    : '<span class="cl-d int" title="Εσωτερική">↔</span>';

  const row = x => `<div class="cl-row${x.logged ? ' done' : ''}" data-call="${x.id}">
    ${dirIco(x.dir)}
    <span class="cl-t">${esc((x.at || '').slice(11, 16))}</span>
    <span class="cl-who">${x.adminName ? esc(x.adminName) : '<span class="mut">—</span>'}</span>
    ${/* ΤΟ ΣΗΜΑ ΕΞΩ ΑΠΟ ΤΟ ΚΕΙΜΕΝΟ. Όταν ζούσε μέσα στο κελί που κόβεται, σε
         στενή οθόνη κοβόταν πρώτο αυτό — δηλαδή χανόταν ακριβώς η πληροφορία
         «αυτός δεν είναι πελάτης ακόμη». Τώρα κόβεται το όνομα, το σήμα μένει. */''}
    <span class="cl-other">${x.clientName ? `<b class="cl-ot">${esc(x.clientName)}</b>`
      : x.skipLabel ? `<span class="cl-ot mut">${esc(x.skipLabel)}</span>`
      : x.anon ? '<span class="cl-ot mut" title="Ο καλών απέκρυψε τον αριθμό του">απόκρυψη αριθμού</span>'
      : x.book ? `<span class="cl-ot">${esc(x.book)}</span><span class="cl-3cx" title="Από τον τηλεφωνικό κατάλογο — δεν είναι συνδεδεμένος με πελάτη WHMCS">κατάλογος</span>`
      : `<span class="cl-ot">${esc(x.other || '—')}</span>`}</span>
    <span class="cl-dur">${x.answered ? callHm(x.talk) : '<span class="cl-miss">αναπάντητη</span>'}</span>
    <span class="cl-sum">${x.summary ? esc(x.summary) : '<span class="mut">—</span>'}</span>
    <span class="cl-bill">${x.bill
      ? `<span class="cl-b" style="--bc:${CALL_BILL[x.bill][1]}">${CALL_BILL[x.bill][0]}</span>`
      : (d.canLog ? '<span class="cl-todo">κατέγραψε</span>' : '')}</span>
  </div>`;

  c.innerHTML = `
  <div class="cl-bar">
    <input type="date" class="inp" id="clD" value="${st.d}" style="width:160px">
    <div class="td-seg cl-seg">
      ${[[1, 'σήμερα'], [7, '7 ημέρες'], [30, '30 ημέρες']].map(([n, l]) =>
        `<button data-cdays="${n}" class="${st.days === n ? 'on' : ''}">${l}</button>`).join('')}
    </div>
    <select class="inp" id="clWho" style="width:210px">
      <option value="0">— όλη η ομάδα —</option>
      ${d.people.map(p => `<option value="${p.id}" ${p.id === st.who ? 'selected' : ''}>${esc(p.name)}</option>`).join('')}
    </select>
    <button class="btn-s${nActive ? ' on' : ''}" id="clFilt">${I.filter || '⚙'} Φίλτρα${nActive ? ` (${nActive})` : ''}</button>
    <span style="flex:1"></span>
    <button class="btn-s" id="clSync" title="Τράβα ό,τι νέο από το τηλεφωνικό κέντρο">↻ Ανανέωση</button>
    <span class="mut" style="font-size:11.5px">${t.logged}/${t.calls} καταγεγραμμένες</span>
  </div>

  ${/* Δεύτερη σειρά, κρυμμένη μέχρι να τη ζητήσεις: η καθημερινή χρήση είναι
       «τι έγινε σήμερα», τα φίλτρα είναι για όταν ψάχνεις κάτι συγκεκριμένο. */''}
  <div class="cl-filters" ${st.open || nActive ? '' : 'hidden'}>
    <input class="inp" id="clQ" placeholder="Πελάτης, αριθμός ή περίληψη…" value="${esc(st.q)}" style="min-width:210px;flex:1">
    <div class="td-seg cl-seg">${[['', 'κάθε κατεύθυνση'], ['in', '↙ εισερχόμενες'], ['out', '↗ εξερχόμενες']]
      .map(([k, l]) => `<button data-cdir="${k}" class="${st.dir === k ? 'on' : ''}">${l}</button>`).join('')}</div>
    <div class="td-seg cl-seg">${[['', 'όλες'], ['yes', 'απαντημένες'], ['no', 'αναπάντητες']]
      .map(([k, l]) => `<button data-cans="${k}" class="${st.ans === k ? 'on' : ''}">${l}</button>`).join('')}</div>
    <select class="inp" id="clBill" style="width:170px">
      <option value="">— κάθε χρέωση —</option>
      <option value="none" ${st.bill === 'none' ? 'selected' : ''}>δεν καταγράφηκαν</option>
      ${Object.entries(CALL_BILL).map(([k, v]) => `<option value="${k}" ${st.bill === k ? 'selected' : ''}>${v[0]}</option>`).join('')}
    </select>
    ${(d.catsSeen || []).length ? `<select class="inp" id="clCat" style="width:160px">
      <option value="">— κάθε κατηγορία —</option>
      ${d.catsSeen.map(k => `<option value="${esc(k.key)}" ${st.cat === k.key ? 'selected' : ''}>${esc(CALL_CAT[k.key] || k.key)} (${k.n})</option>`).join('')}
    </select>` : ''}
    <label class="cl-minl">πάνω από
      <input class="inp" id="clMin" type="number" min="0" max="600" value="${st.min || ''}" style="width:62px"> λεπτά</label>
    ${nActive ? '<button class="btn-s" id="clClr">Καθάρισε</button>' : ''}
  </div>

  <div class="g4 grid" style="margin-bottom:14px">
    ${tile(t.calls, 'κλήσεις')}
    ${tile(callHm(t.talk), 'χρόνος στο τηλέφωνο', 'var(--brand)')}
    ${tile(t.missed, 'αναπάντητες', t.missed ? 'var(--bad)' : '')}
    ${tile(callHm(t.billable), 'χρεώσιμος χρόνος', 'var(--ok)')}
  </div>

  <div class="cl-cols">
    <div class="card"><div class="card-h">${I.users} Ανά χειριστή</div>
      <div class="card-b">${d.perAdmin.length ? d.perAdmin.map(a => `
        <div class="cl-agg pick" data-adm="${a.id}" title="Δες με ποιους μίλησε"><span class="cl-an"><span class="cl-ant">${esc(a.name)}</span></span>
          <span class="cl-ab"><i style="width:${Math.round(a.talk / Math.max(1, d.perAdmin[0].talk) * 100)}%"></i></span>
          <span class="cl-av">${a.calls} κλήσεις · <b>${callHm(a.talk)}</b>${a.missed ? ` · <span style="color:var(--bad)">${a.missed} χαμένες</span>` : ''}</span>
        </div>`).join('') : '<div class="mut" style="font-size:12.5px">—</div>'}</div></div>

    <div class="card"><div class="card-h">${I.building} Ποιος μας απασχολεί
      <span class="mut" style="font-weight:400;font-size:11px;margin-left:auto">κατά χρόνο</span></div>
      <div class="card-b">${d.perClient.length ? d.perClient.map(x => `
        <div class="cl-agg pick" ${x.client ? `data-cli="${x.client}"` : `data-num="${esc(x.num || x.name)}"`}
          title="Δες ποιος τον εξυπηρέτησε">
          <span class="cl-an"><span class="cl-ant">${esc(x.name)}</span>${x.book ? '<span class="cl-3cx" title="Από τον τηλεφωνικό κατάλογο">κατάλογος</span>' : ''}</span>
          <span class="cl-ab"><i style="width:${Math.round(x.talk / Math.max(1, d.perClient[0].talk) * 100)}%;background:#7b5cd6"></i></span>
          <span class="cl-av">${x.calls} · <b>${callHm(x.talk)}</b></span>
        </div>`).join('') : '<div class="mut" style="font-size:12.5px">—</div>'}</div></div>
  </div>

  ${(() => {
    /* ── ΠΟΤΕ ΜΑΣ ΠΙΕΖΟΥΝ ──
       Δεν δείχνουμε μέσο όρο: δείχνουμε ΠΟΤΕ χτυπάει το τηλέφωνο και πότε μας
       ξεφεύγει. Η ένταση του χρώματος είναι ο όγκος· η κόκκινη κουκκίδα κάτω
       λέει ότι εκείνη την ώρα χάνουμε κλήσεις. Οι νεκρές ώρες κόβονται, γιατί
       ένα 24ωρο με άδειες τις μισές στήλες κρύβει αυτό που μετράει. */
    const hrs = d.byHour || [];
    if (!hrs.length || !t.calls) { return ''; }
    /* ΟΧΙ «κάθε ώρα με έστω μία κλήση»: τέσσερις νυχτερινές κλήσεις άπλωναν το
       γράφημα σε 24 στήλες και στρίμωχναν το ωράριο σε ανάγνωστο πλάτος.
       Κρατάμε τις ώρες με ουσιαστικό όγκο και λέμε ρητά πόσες έμειναν απ' έξω. */
    const floor = Math.max(2, Math.round(t.calls * 0.005));
    let lo = 23, hi = 0;
    hrs.forEach((h, i) => { if (h.calls >= floor) { lo = Math.min(lo, i); hi = Math.max(hi, i); } });
    if (lo > hi) { return ''; }
    lo = Math.max(0, lo - 1); hi = Math.min(23, hi + 1);
    const outside = hrs.reduce((n, h, i) => n + ((i < lo || i > hi) ? h.calls : 0), 0);
    const span = [];
    for (let i = lo; i <= hi; i++) { span.push(i); }
    const max = Math.max(...hrs.map(h => h.calls), 1);
    const dayN = ['Δευ', 'Τρί', 'Τετ', 'Πέμ', 'Παρ', 'Σάβ', 'Κυρ'];
    const heat = d.heat || [];
    const hmax = Math.max(1, ...heat.map(r => Math.max(...r)));
    const busiest = hrs.map((h, i) => ({i, ...h})).sort((a, b) => b.calls - a.calls)[0];
    /* Η χειρότερη ώρα ΔΕΝ είναι αυτή με το μεγαλύτερο ποσοστό απωλειών: μια ώρα
       με 2 κλήσεις και 2 χαμένες βγάζει 100% και δεν σημαίνει τίποτα. Κοιτάμε
       μόνο ώρες με πραγματικό όγκο — τουλάχιστον το 10% της αιχμής. */
    const worst = hrs.map((h, i) => ({i, ...h}))
      .filter(h => h.calls >= Math.max(5, max * 0.1))
      .sort((a, b) => (b.missed / b.calls) - (a.missed / a.calls))[0];
    const perDay = d.activeDays ? Math.round(t.calls / d.activeDays) : 0;
    return `
  <div class="card"><div class="card-h">${I.clock} Πότε μας πιέζουν
    <span class="mut" style="font-weight:400;font-size:11px;margin-left:auto">${
      d.activeDays ? `${d.activeDays} ημέρες με κίνηση · κατά μέσο όρο ${perDay} κλήσεις/ημέρα` : ''}</span></div>
    <div class="card-b">
      <div class="pk-bars">
        ${span.map(i => {
          const h = hrs[i] || {calls: 0, missed: 0};
          const pct = Math.round(h.calls / max * 100);
          const mp = h.calls ? Math.round(h.missed / h.calls * 100) : 0;
          return `<div class="pk-c" title="${i}:00 — ${h.calls} κλήσεις${h.missed ? `, ${h.missed} χαμένες (${mp}%)` : ''}">
            <span class="pk-n">${h.calls || ''}</span>
            <span class="pk-b"><i style="height:${pct}%"></i>${
              h.missed ? `<u style="height:${Math.round(h.missed / max * 100)}%"></u>` : ''}</span>
            <span class="pk-h">${i}</span></div>`;
        }).join('')}
      </div>
      <div class="pk-note">
        ${busiest && busiest.calls ? `Αιχμή στις <b>${busiest.i}:00–${busiest.i + 1}:00</b> με ${busiest.calls} κλήσεις.` : ''}
        ${worst && worst.missed ? ` Χειρότερη ώρα οι <b>${worst.i}:00</b> — ${worst.missed} από ${worst.calls} αναπάντητες (${Math.round(worst.missed / worst.calls * 100)}%).` : ''}
        ${outside ? ` <span class="mut">${outside} ${outside === 1 ? 'κλήση' : 'κλήσεις'} εκτός αυτών των ωρών.</span>` : ''}
      </div>
      <div class="pk-grid">
        <div class="pk-gh"><span></span>${span.map(i => `<span>${i}</span>`).join('')}</div>
        ${heat.map((rowD, wd) => `<div class="pk-gr"><span class="pk-gd">${dayN[wd]}</span>${
          span.map(i => {
            const v = rowD[i] || 0;
            return `<span class="pk-gc" style="--o:${v ? (0.14 + 0.86 * v / hmax).toFixed(2) : 0}"
              title="${dayN[wd]} ${i}:00 — ${v} κλήσεις"></span>`;
          }).join('')}</div>`).join('')}
      </div>
    </div></div>`;
  })()}

  <div class="card"><div class="card-h">${I.phone} Οι κλήσεις
    <span class="mut" style="font-weight:400;font-size:11px;margin-left:auto">${
      /* Τα πλακίδια μετρούν ΟΛΟ το διάστημα· η λίστα δείχνει τις πιο πρόσφατες.
         Χωρίς αυτή τη φράση θα νόμιζε κανείς ότι λείπουν κλήσεις. */
      d.shown < t.calls
        ? `οι ${d.shown} πιο πρόσφατες από ${t.calls} · κλικ σε γραμμή για καταγραφή`
        : 'κλικ σε γραμμή για καταγραφή'}</span></div>
    <div class="card-b" style="padding:4px 6px 8px">
      ${d.items.length ? `<div class="cl-list">${d.items.map(row).join('')}</div>`
        : `<div class="cl-empty">Καμία κλήση σε αυτό το διάστημα.</div>`}
    </div></div>`;

  $('#clD').onchange = e => { st.d = e.target.value; R.calls(); };
  $('#clFilt').onclick = () => { st.open = !st.open; R.calls(); };
  const F = $('.cl-filters');
  if (F) {
    let qt = null;
    $('#clQ').oninput = e => { clearTimeout(qt); const v = e.target.value;
      qt = setTimeout(() => { st.q = v.trim(); R.calls(); }, 350); };
    $$('[data-cdir]').forEach(b => b.onclick = () => { st.dir = b.dataset.cdir; R.calls(); });
    $$('[data-cans]').forEach(b => b.onclick = () => { st.ans = b.dataset.cans; R.calls(); });
    $('#clBill').onchange = e => { st.bill = e.target.value; R.calls(); };
    const cc = $('#clCat'); if (cc) { cc.onchange = e => { st.cat = e.target.value; R.calls(); }; }
    $('#clMin').onchange = e => { st.min = Math.max(0, +e.target.value || 0); R.calls(); };
    const cl = $('#clClr');
    if (cl) { cl.onclick = () => { st.dir = ''; st.ans = ''; st.bill = ''; st.cat = ''; st.min = 0; st.q = ''; R.calls(); }; }
  }
  /* Το pulse το κάνει μόνο του κάθε 10΄. Το κουμπί είναι για όποιον μόλις έκλεισε
     το τηλέφωνο και θέλει να δει την κλήση του τώρα, χωρίς να περιμένει. */
  $('#clSync').onclick = async e => {
    const b = e.currentTarget; const was = b.textContent;
    b.disabled = true; b.textContent = '↻ φέρνω…';
    try {
      const r = await api('calls_sync', {days: 2});
      toast(r.sync.new ? `${r.sync.new} νέες κλήσεις` : 'Δεν υπάρχει κάτι νέο');
      if (r.sync.new || r.sync.updated) { R.calls(); return; }
    } catch (err) { toast(err.message || 'Δεν έγινε η ανανέωση', 'err'); }
    b.disabled = false; b.textContent = was;
  };
  $('#clWho').onchange = e => { st.who = +e.target.value; R.calls(); };
  $$('[data-cdays]').forEach(b => b.onclick = () => { st.days = +b.dataset.cdays; R.calls(); });
  $$('[data-cli]').forEach(b => b.onclick = () => window.CNP.go('client360', b.dataset.cli));
  if (d.canLog) {
    $$('[data-call]').forEach(r => r.onclick = () => callNote(d.items.find(x => x.id === +r.dataset.call), d));
  /* Το πλακίδιο λέει ΠΟΣΟ· το κλικ απαντά ΜΕ ΠΟΙΟΝ. */
  $$('[data-adm]').forEach(r => r.onclick = () => callDrill({admin: +r.dataset.adm}, st));
  $$('[data-cli]').forEach(r => r.onclick = () => callDrill({client: +r.dataset.cli}, st));
  $$('[data-num]').forEach(r => r.onclick = () => callDrill({num: r.dataset.num}, st));
  }
};

/** Η καταγραφή του agent: τι έγινε, και αν χρεώνεται — με λόγο. */
/* ═════════ Ανάλυση: με ποιον μίλησε ο χειριστής / ποιος σήκωσε τον πελάτη ═════════
   Και οι δύο κατευθύνσεις είναι η ΙΔΙΑ ερώτηση από την άλλη μεριά, γι' αυτό
   είναι ένας πίνακας και όχι δύο. Κρατάει το διάστημα της αναφοράς — αλλιώς τα
   νούμερα δεν θα έδεναν με αυτά που μόλις κοίταζε ο χρήστης. */
async function callDrill(what, st) {
  const qs = ['d=' + st.d, 'days=' + st.days,
    what.admin ? 'admin=' + what.admin : '',
    what.client ? 'client=' + what.client : '',
    what.num ? 'num=' + encodeURIComponent(what.num) : ''].filter(Boolean).join('&');

  const ovl = document.createElement('div');
  ovl.className = 'ovl show'; ovl.style.zIndex = 320;
  ovl.innerHTML = `<div class="pal-box cd-box" onclick="event.stopPropagation()">
    <div class="cd-b"><div class="skel" style="height:260px"></div></div></div>`;
  document.body.appendChild(ovl);
  const kill = () => ovl.remove();
  ovl.onclick = kill;

  const d = await api('call_drill&' + qs).catch(e => ({error: e && e.message}));
  if (!d || d.error) {
    $('.cd-b', ovl).innerHTML = `<div class="mut" style="padding:20px">${esc((d && d.error) || 'Δεν φορτώθηκε.')}</div>`;
    return;
  }
  const t = d.totals;
  const maxT = Math.max(1, ...d.rows.map(r => r.talk));
  const period = st.days === 1 ? 'εκείνη την ημέρα' : `σε ${st.days} ημέρες`;

  ovl.querySelector('.cd-box').innerHTML = `
    <div class="cd-h">
      <div>
        <b>${esc(d.title)}</b>
        <div class="mut" style="font-size:12px;margin-top:2px">
          ${t.calls} κλήσεις ${period} · <b>${callHm(t.talk)}</b> στο τηλέφωνο
          ${t.missed ? ` · <span style="color:var(--bad)">${t.missed} αναπάντητες</span>` : ''}
          · ${t.in} εισερχ. / ${t.out} εξερχ.</div>
      </div>
      ${d.mode === 'client' && cnpCan('reports.calls') && (what.client || what.num)
        ? `<a class="btn-s" id="cdFull" href="#/clientcalls/${what.client ? 'c' + what.client : ''}"
             title="Ολόκληρη η κίνησή του σε ελεύθερο διάστημα">Πλήρης κίνηση</a>` : ''}
      <button class="cd-x" title="Κλείσιμο">✕</button>
    </div>
    <div class="cd-b">
      <div class="cd-sub">${d.mode === 'admin' ? 'Με ποιους μίλησε' : 'Ποιος τον εξυπηρέτησε'}</div>
      ${d.rows.length ? d.rows.map(r => `
        <div class="cl-agg${d.mode === 'admin' && r.id ? ' pick' : ''}" ${d.mode === 'admin' && r.id ? `data-go="${r.id}"` : ''}>
          <span class="cl-an"><span class="cl-ant">${esc(r.name)}</span>${r.book ? '<span class="cl-3cx" title="Από τον τηλεφωνικό κατάλογο">κατάλογος</span>' : ''}</span>
          <span class="cl-ab"><i style="width:${Math.round(r.talk / maxT * 100)}%;background:${d.mode === 'admin' ? '#7b5cd6' : 'var(--brand)'}"></i></span>
          <span class="cl-av">${r.calls} · <b>${callHm(r.talk)}</b>${
            r.missed ? ` · <span style="color:var(--bad)">${r.missed} χαμ.</span>` : ''}</span>
        </div>`).join('') : '<div class="mut" style="font-size:12.5px">—</div>'}

      <div class="cd-sub" style="margin-top:14px">Οι κλήσεις${
        d.shown < t.calls ? ` <span class="mut" style="font-weight:400">— οι ${d.shown} πιο πρόσφατες από ${t.calls}</span>` : ''}</div>
      <div class="cl-list">${d.items.map(x => `
        <div class="cl-row${x.logged ? ' done' : ''}" data-dcall="${x.id}">
          <span class="cl-d ${x.dir}">${x.dir === 'out' ? '↗' : '↙'}</span>
          <span class="cl-t">${esc((x.at || '').slice(5, 16).replace('-', '/'))}</span>
          <span class="cl-who">${d.mode === 'admin'
            ? (x.clientName ? esc(x.clientName)
               : x.anon ? '<span class="mut">απόκρυψη</span>'
               : x.book ? `${esc(x.book)} <span class="cl-3cx" title="Από τον τηλεφωνικό κατάλογο">κατάλογος</span>`
               : esc(x.other || '—'))
            : (x.adminName ? esc(x.adminName) : '<span class="mut">—</span>')}</span>
          <span class="cl-dur">${x.answered ? callHm(x.talk) : '<span class="cl-miss">αναπάντητη</span>'}</span>
          <span class="cl-sum">${x.summary ? esc(x.summary) : (d.canLog ? '<span class="cl-todo">κατέγραψε</span>' : '')}</span>
        </div>`).join('')}</div>
    </div>`;
  $('.cd-x', ovl).onclick = kill;
  const full = $('#cdFull', ovl);
  if (full) { full.onclick = () => kill(); }   /* ο σύνδεσμος αλλάζει οθόνη — κλείσε το παράθυρο */
  $$('[data-dcall]', ovl).forEach(r => r.onclick = () =>
    callNote(d.items.find(x => x.id === +r.dataset.dcall), d));
  /* Από «με ποιους μίλησε» → μπαίνεις στον πελάτη και βλέπεις ποιοι άλλοι
     τον σηκώνουν. Η ίδια ερώτηση, από την άλλη μεριά. */
  $$('[data-go]', ovl).forEach(r => r.onclick = () => { kill(); callDrill({client: +r.dataset.go}, st); });
}

function callNote(x, d0) {
  if (!x) { return; }
  d0 = d0 || {canLog: true};
  const ovl = document.createElement('div');
  ovl.className = 'ovl show'; ovl.style.zIndex = 330;
  ovl.innerHTML = `<div class="pal-box" style="margin:9vh auto 0;max-width:520px" onclick="event.stopPropagation()">
    <div style="padding:17px 20px 6px">
      <b style="font-size:15px;color:var(--ink)">Τι έγινε σε αυτή την κλήση;</b>
      <div class="mut" style="font-size:12px;margin-top:3px">
        ${esc((x.at || '').slice(0, 16))} · ${x.dir === 'in' ? 'εισερχόμενη από' : x.dir === 'out' ? 'εξερχόμενη προς' : 'εσωτερική'}
        <b>${esc(x.clientName || x.skipLabel || x.book || (x.anon ? 'απόκρυψη αριθμού' : x.other) || '—')}</b>${
          x.book && !x.clientName ? ' <span class="cl-3cx" title="Από τον τηλεφωνικό κατάλογο">κατάλογος</span>' : ''}${x.answered ? ' · ' + callHm(x.talk) : ' · αναπάντητη'}</div>
    </div>
    <div style="padding:8px 20px 4px">
      ${/* Η ΤΑΥΤΙΣΗ ΠΡΩΤΑ: αν δεν ξέρουμε ποιος είναι, τίποτα άλλο δεν έχει
            αξία — ούτε χρέωση, ούτε «ποιος μας απασχολεί». Το WHMCS έχει
            τηλέφωνο μόνο για 210 πελάτες, οπότε το ερώτημα βγαίνει συχνά. */
        (!x.client && x.other && !x.anon && d0.canLog) ? `
      <div class="cn-link" id="cnLinkBox">
        <div style="font-size:12.5px;font-weight:600;color:var(--ink)">Ποιος είναι το ${esc(x.other)};</div>
        <div class="mut" style="font-size:11.5px;margin:2px 0 7px">
          ${x.skipLabel ? 'Καταχωρημένο ως <b>' + esc(x.skipLabel) + '</b> — μπορείς να το αλλάξεις.'
            : x.book ? 'Ο κατάλογος του 3CX λέει <b>' + esc(x.book) + '</b> — βρες τον στο WHMCS για να μετράει κανονικά.'
            : 'Θα ισχύσει για όλες τις κλήσεις του, παλιές και νέες.'}</div>
        <input class="inp" id="cnLq" placeholder="Γράψε όνομα πελάτη…" autocomplete="off"
          value="${esc(x.book ? (x.book.split('—')[0].trim()) : '')}">
        <div id="cnLres"></div>
        <div class="cn-row2">
          ${cnpCan('comms.book') ? `<button type="button" class="btn btn-sm btn-p" id="cnLcard">
            ${x.bookId ? 'Άνοιξε την καρτέλα' : 'Καταχώρηση στον κατάλογο'}</button>` : ''}
          <button type="button" class="btn btn-sm btn-o" id="cnLskip">Δεν είναι πελάτης</button>
        </div>
        ${cnpCan('comms.book.edit') ? `
        <label class="cn-book"><input type="checkbox" id="cnLbook" checked>
          να μπει και στον κατάλογο
          <span class="mut">— για να τον βλέπει η ομάδα στο τηλέφωνο</span></label>` : ''}
      </div>` : ''}
      <label class="lbl">Τι ζήτησε / τι έκανες</label>
      <input class="inp" id="cnS" maxlength="500" value="${esc(x.summary || '')}" placeholder="π.χ. Ρύθμιση XML Skroutz — έγινε επί τόπου">
      <label class="lbl" style="margin-top:11px">Κατηγορία</label>
      <div class="cn-chips" id="cnCat"></div>
      <label class="lbl" style="margin-top:11px">Χρέωση</label>
      <div class="cn-chips" id="cnBill"></div>
      <div id="cnWhyBox" style="margin-top:9px" hidden>
        <label class="lbl">Γιατί χρεώνεται <span class="mut" style="font-weight:400">— υποχρεωτικό</span></label>
        <input class="inp" id="cnWhy" maxlength="255" value="${esc(x.billWhy || '')}" placeholder="π.χ. εκτός συμβολαίου · νέα παραμετροποίηση">
      </div>
      <label style="display:flex;gap:7px;align-items:center;margin-top:11px;font-size:12.5px">
        <input type="checkbox" id="cnF" ${x.followup ? 'checked' : ''}> Χρειάζεται συνέχεια</label>
      <div style="display:flex;gap:8px;margin-top:15px;justify-content:flex-end">
        <button class="btn btn-o" id="cnX">Άκυρο</button>
        <button class="btn btn-p" id="cnOk">Καταχώρηση</button></div>
    </div></div>`;
  document.body.appendChild(ovl);
  const kill = () => ovl.remove();
  ovl.onclick = kill;
  $('#cnX', ovl).onclick = kill;

  /* Αναζήτηση πελάτη — ίδιο endpoint με την υπόλοιπη εφαρμογή. */
  const lq = $('#cnLq', ovl);
  if (lq) {
    let tmr = null;
    const res = $('#cnLres', ovl);
    const link = async body => {
      try {
        const r = await api('call_link', body);
        if (r.skip) { toast('Καταχωρήθηκε ως «' + r.label + '»'); }
        else {
          const bk = r.book === 'created' ? ' · μπήκε και στο τηλεφωνικό κέντρο'
            : r.book === 'updated' ? ' · ενημερώθηκε και το τηλεφωνικό κέντρο'
            : (r.book && r.book.indexOf('error:') === 0) ? ' · ΟΜΩΣ ο κατάλογος 3CX δεν ενημερώθηκε' : '';
          toast(`${r.clientName} — ενημερώθηκαν ${r.updated} κλήσεις${bk}`,
            (r.book && r.book.indexOf('error:') === 0) ? 'err' : '');
        }
        kill(); R.calls();
      } catch (e) { toast(e.message || 'Δεν αποθηκεύτηκε', 'err'); }
    };
    lq.oninput = () => {
      clearTimeout(tmr);
      const v = lq.value.trim();
      if (v.length < 2) { res.innerHTML = ''; return; }
      tmr = setTimeout(async () => {
        const r = await api('client_search&q=' + encodeURIComponent(v)).catch(() => null);
        const list = (r && r.results) || [];
        res.innerHTML = list.length
          ? list.slice(0, 6).map(c => `<div class="cn-pick" data-cid="${c.id}"><b>${esc(c.name)}</b>
              <span class="mut">#${c.id}</span></div>`).join('')
          : '<div class="mut" style="padding:7px 2px;font-size:12px">Κανένα αποτέλεσμα</div>';
        $$('.cn-pick', res).forEach(el => el.onclick = () =>
          link({e164: x.other, client: +el.dataset.cid,
                toBook: !!($('#cnLbook', ovl) || {}).checked}));
      }, 260);
    };
    /* Ο πλήρης δρόμος: ολόκληρη η καρτέλα, με τον αριθμό ήδη μέσα. Εκεί
       μπαίνουν ΑΦΜ, διεύθυνση, υπεύθυνος — όσα δεν χωρούν σε ένα πεδίο. */
    const card = $('#cnLcard', ovl);
    if (card) {
      card.onclick = () => {
        /* ΚΟΥΒΑΛΑΜΕ Ο,ΤΙ ΕΓΡΑΨΕ. Ο συνάδελφος πληκτρολογεί το όνομα εδώ, δεν
           βρίσκεται πελάτης, πατάει «Καταχώρηση» — και μέχρι τώρα η καρτέλα
           άνοιγε άδεια και το ξανάγραφε. Έτσι προέκυψε καρτέλα με το όνομα
           γραμμένο δύο φορές, μία στην επωνυμία και μία στο ονοματεπώνυμο. */
        const typed = (lq.value || '').trim();
        kill();
        bookCard(x.bookId || 0, {phone: x.other, company: x.book || typed});
      };
    }
    $('#cnLskip', ovl).onclick = () => {
      const lbl = prompt('Ποιος είναι; (π.χ. ΔΕΗ, τηλεπωλήσεις, προμηθευτής)', x.skipLabel || '');
      if (lbl && lbl.trim()) { link({e164: x.other, skip: true, label: lbl.trim()}); }
    };
  }

  let cat = x.category || '', bill = x.bill || '';
  const paint = () => {
    $('#cnCat', ovl).innerHTML = Object.entries(CALL_CAT).map(([k, l]) =>
      `<button type="button" class="btn btn-sm ${cat === k ? 'btn-p' : 'btn-o'}" data-cat="${k}">${l}</button>`).join('');
    $('#cnBill', ovl).innerHTML = Object.entries(CALL_BILL).map(([k, [l]]) =>
      `<button type="button" class="btn btn-sm ${bill === k ? 'btn-p' : 'btn-o'}" data-bill="${k}">${l}</button>`).join('');
    $('#cnWhyBox', ovl).hidden = bill !== 'billable';
    $$('[data-cat]', ovl).forEach(b => b.onclick = () => { cat = b.dataset.cat; paint(); });
    $$('[data-bill]', ovl).forEach(b => b.onclick = () => { bill = b.dataset.bill; paint(); });
  };
  paint();

  $('#cnOk', ovl).onclick = async () => {
    const r = await api('call_note_save', {id: x.id, summary: $('#cnS', ovl).value,
      category: cat, bill, why: $('#cnWhy', ovl).value, followup: $('#cnF', ovl).checked})
      .catch(e => ({err: e.message}));
    if (r.err) { toast(r.err, true); return; }
    kill(); toast('Καταχωρήθηκε'); R.calls();
  };
  setTimeout(() => { const i = $('#cnS', ovl); if (i) { i.focus(); } }, 40);
}

/* ═══════════════════ ΤΗΛΕΦΩΝΙΚΟΣ ΚΑΤΑΛΟΓΟΣ ═══════════════════
   Ο κατάλογος της εταιρείας, με καρτέλα ανά επαφή. Δεν είναι καθρέφτης του
   3CX: εδώ είναι η αλήθεια, και από εδώ ενημερώνεται το τηλεφωνικό κέντρο.

   Ταξινομείται κατά ΧΡΟΝΟ ΣΤΟ ΤΗΛΕΦΩΝΟ, όχι αλφαβητικά — γιατί η ερώτηση που
   κάνει κανείς δεν είναι «ποιος υπάρχει» αλλά «ποιος μας απασχολεί». */
const BK_ST = {active: ['Ενεργός', '#2a9d63'], prospect: ['Υποψήφιος', '#1668dc'],
               supplier: ['Προμηθευτής', '#7b5cd6'], inactive: ['Ανενεργός', '#8595ac']};

R.book = async function () {
  if (!cnpCan('comms.book')) {
    setTop('Τηλεφωνικός κατάλογος');
    $('#content').innerHTML = cnpDenied({message: 'Χρειάζεται «Σύστημα → Τηλεφωνικός κατάλογος»'});
    return;
  }
  setTop('Τηλεφωνικός κατάλογος', 'Ποιος είναι πίσω από κάθε αριθμό — και τι τρέχει μαζί του');
  const c = $('#content');
  const st = R.book._s = R.book._s || {q: '', only: '', status: '', sort: 'talk'};
  c.innerHTML = '<div class="skel" style="height:80px;margin-bottom:14px"></div><div class="skel" style="height:420px"></div>';

  const d = await api(`book_list&q=${encodeURIComponent(st.q)}&only=${st.only}&status=${st.status}&sort=${st.sort}`)
    .catch(() => null);
  if (!d) { c.innerHTML = '<div class="card"><div class="card-b mut">Δεν φορτώθηκε.</div></div>'; return; }
  R.book._d = d;

  const K = d.counts;
  const CHIPS = [['', 'όλες', K.all], ['client', 'πελάτες', K.client],
                 ['noclient', 'χωρίς πελάτη', K.all - K.client],
                 ['nopbx', 'δεν πήγαν στα τηλέφωνα', K.nopbx],
                 ['dup', 'διπλό τηλέφωνο', K.dup],
                 ['due', 'θέλουν κίνηση', K.due]];

  c.innerHTML = `
  <div class="cl-bar">
    <div class="kb-sinput" style="max-width:320px"><span class="kb-sico">${I.search}</span>
      <input class="inp" id="bkQ" placeholder="Όνομα, τηλέφωνο, ΑΦΜ, πόλη, ετικέτα…" value="${esc(st.q)}"></div>
    <select class="inp" id="bkSt" style="width:150px">
      <option value="">— κάθε κατάσταση —</option>
      ${Object.entries(d.statuses).map(([k, l]) => `<option value="${k}" ${st.status === k ? 'selected' : ''}>${l}</option>`).join('')}
    </select>
    <select class="inp" id="bkSort" style="width:170px">
      ${[['talk', 'κατά χρόνο'], ['recent', 'πιο πρόσφατες'], ['name', 'αλφαβητικά']].map(([k, l]) =>
        `<option value="${k}" ${st.sort === k ? 'selected' : ''}>${l}</option>`).join('')}
    </select>
    <span style="flex:1"></span>
    ${d.canEdit ? `<button class="btn-s" id="bkImp">Εισαγωγή</button>
      ${K.nopbx ? `<button class="btn-s" id="bkPush" title="Στείλε στο τηλεφωνικό κέντρο όσες εκκρεμούν">↑ Στα τηλέφωνα (${K.nopbx})</button>` : ''}
      <button class="btn btn-p btn-sm" id="bkNew">${I.plus} Νέα καρτέλα</button>` : ''}
  </div>

  <div class="bk-chips">
    ${CHIPS.map(([k, l, n]) => `<button class="kb-chip${st.only === k ? ' on' : ''}" data-bonly="${k}">${l} <b>${n}</b></button>`).join('')}
    ${K.pbxerr ? `<button class="kb-chip bk-err${st.only === 'pbxerr' ? ' on' : ''}" data-bonly="pbxerr">δεν στάλθηκαν <b>${K.pbxerr}</b></button>` : ''}
  </div>

  <div class="card"><div class="card-b" style="padding:4px 6px 8px">
    ${d.items.length ? `<div class="bk-list">${d.items.map(b => {
      const s = BK_ST[b.status] || BK_ST.active;
      /* Γραμμή που κάνει κλικ ΠΡΕΠΕΙ να φτάνεται και με πληκτρολόγιο — αλλιώς
         620 καρτέλες είναι απρόσιτες για όποιον δεν χρησιμοποιεί ποντίκι. */
      return `<div class="bk-row pick" data-bk="${b.id}" role="button" tabindex="0"
        aria-label="${esc(b.name)}">
        <span class="bk-dot" style="background:${s[1]}" title="${s[0]}"></span>
        <span class="bk-n"><span class="bk-nt">${esc(b.name)}</span>${
          b.city ? `<span class="bk-city mut">${esc(b.city)}</span>` : ''}${
          b.dup ? '<span class="bk-dup" title="Ο ίδιος αριθμός υπάρχει και σε άλλη καρτέλα">διπλό</span>' : ''}${
          b.pbxError ? `<span class="bk-dup bk-bad" title="${esc(b.pbxError)}">δεν στάλθηκε</span>` : ''}</span>
        <span class="bk-p">${b.phones.map(p => esc(p.e164)).join(' · ')}</span>
        <span class="bk-c">${b.calls ? `${b.calls} κλήσεις · <b>${callHm(b.talk)}</b>` : '<span class="mut">—</span>'}</span>
        <span class="bk-l">${b.client ? '<span class="bk-ok">πελάτης</span>'
          : (b.toPbx && !b.pbx ? '<span class="bk-no">εκτός 3CX</span>' : '')}
          ${b.nextAt ? `<span class="bk-due" title="${esc(b.nextNote)}">${esc(b.nextAt.slice(5))}</span>` : ''}</span>
      </div>`; }).join('')}</div>`
      : `<div class="cl-empty">Καμία καρτέλα${st.q ? ' για «' + esc(st.q) + '»' : ''}${
          st.only || st.status ? ' με αυτό το φίλτρο' : ''}.${
          (st.q || st.only || st.status) ? ' <a href="#" id="bkClr">Καθάρισε τα φίλτρα</a>' : ''}</div>`}
  </div></div>`;

  let tmr = null;
  $('#bkQ').oninput = e => { clearTimeout(tmr); const v = e.target.value; tmr = setTimeout(() => { st.q = v; R.book(); }, 320); };
  $('#bkSt').onchange = e => { st.status = e.target.value; R.book(); };
  $('#bkSort').onchange = e => { st.sort = e.target.value; R.book(); };
  $$('[data-bonly]').forEach(b => b.onclick = () => { st.only = b.dataset.bonly; R.book(); });
  const clr = $('#bkClr');
  if (clr) { clr.onclick = e => { e.preventDefault(); st.q = ''; st.only = ''; st.status = ''; R.book(); }; }
  $$('[data-bk]').forEach(r => {
    const open = () => bookCard(+r.dataset.bk);
    r.onclick = open;
    r.onkeydown = e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(); } };
  });
  if (d.canEdit) {
    $('#bkNew').onclick = () => bookCard(0);
    $('#bkImp').onclick = () => bookImport();
    const pb = $('#bkPush');
    if (pb) {
      pb.onclick = async () => {
        /* ΓΡΑΦΕΙ ΣΤΟ ΖΩΝΤΑΝΟ ΤΗΛΕΦΩΝΙΚΟ ΚΕΝΤΡΟ. Ένα κατά λάθος κλικ βάζει
           εκατοντάδες επαφές στις συσκευές όλης της ομάδας — και για να τις
           βγάλεις πρέπει να τις σβήσεις μία-μία. Ρωτάμε πρώτα, με τον αριθμό
           μπροστά ώστε να φαίνεται το μέγεθος. */
        if (!confirm(`Θα σταλούν ${K.nopbx} επαφές στο τηλεφωνικό κέντρο.\n\n`
          + 'Θα τις βλέπει όλη η ομάδα στις οθόνες των τηλεφώνων.\n\nΝα προχωρήσω;')) { return; }
        pb.disabled = true; pb.textContent = '↑ στέλνω…';
        try { const r = await api('book_push', {});
          toast(`Στάλθηκαν ${r.res.sent}${r.res.failed ? ` · απέτυχαν ${r.res.failed}` : ''}`,
            r.res.failed ? 'err' : ''); R.book(); }
        catch (e) { toast(e.message || 'Δεν στάλθηκαν', 'err'); pb.disabled = false;
          pb.textContent = `↑ Στα τηλέφωνα (${K.nopbx})`; }
      };
    }
  }
};

/* Η εισαγωγή είναι ΕΦΑΠΑΞ γέμισμα, όχι συγχρονισμός: λέει καθαρά τι θα κάνει,
   γιατί μετά από αυτήν ο κατάλογος εδώ γίνεται η πηγή. */
function bookImport() {
  const ovl = document.createElement('div');
  ovl.className = 'ovl show'; ovl.style.zIndex = 330;
  ovl.innerHTML = `<div class="pal-box" style="margin:12vh auto 0;max-width:460px" onclick="event.stopPropagation()">
    <div style="padding:18px 20px 6px"><b style="font-size:15px">Γέμισμα καταλόγου</b>
      <div class="mut" style="font-size:12px;margin-top:4px">
        Φέρνει ό,τι λείπει. Καρτέλες που έχεις ήδη επεξεργαστεί δεν πειράζονται.</div></div>
    <div style="padding:6px 20px 18px">
      <button class="btn btn-o bk-imp" data-from="pbx">Από το τηλεφωνικό κέντρο (3CX)</button>
      <button class="btn btn-o bk-imp" data-from="whmcs">Από τους πελάτες του WHMCS</button>
      <button class="btn btn-p bk-imp" data-from="both">Και από τα δύο</button>
      <div style="display:flex;justify-content:flex-end;margin-top:14px">
        <button class="btn btn-o" id="biX">Άκυρο</button></div>
    </div></div>`;
  document.body.appendChild(ovl);
  const kill = () => ovl.remove();
  ovl.onclick = kill; $('#biX', ovl).onclick = kill;
  $$('.bk-imp', ovl).forEach(b => b.onclick = async () => {
    $$('.bk-imp', ovl).forEach(x => x.disabled = true);
    b.textContent = 'φέρνω…';
    try {
      const r = await api('book_import', {from: b.dataset.from});
      const p = r.res.pbx, w = r.res.whmcs;
      toast(`${p ? `3CX: ${p.new} νέες · ` : ''}${w ? `WHMCS: ${w.new} νέες` : ''}` || 'Τίποτα νέο');
      kill(); R.book();
    } catch (e) { toast(e.message || 'Δεν έγινε', 'err'); kill(); }
  });
}

/* ── Η ΚΑΡΤΕΛΑ ──────────────────────────────────────────────────────────────
   Τέσσερα πράγματα σε ένα scroll: ποιος είναι, πώς τον βρίσκεις, τι τρέχει
   μαζί του, τι έχει γίνει. Ο κανόνας της καρτέλας task ισχύει και εδώ —
   ένα scroll, χωρίς διπλά. */
async function bookCard(id, pre) {
  const dr = drawer(id ? 'Καρτέλα' : 'Νέα καρτέλα', '<div class="skel" style="height:420px"></div>', true);
  /* Το σώμα του drawer έχει id ppBody — κοινό για όλη την εφαρμογή. */
  const body = $('#ppBody', dr);

  let d;
  if (id) {
    d = await api('book_get&id=' + id).catch(e => ({error: e && e.message}));
    if (!d || d.error) { body.innerHTML = cnpDenied({message: (d && d.error) || 'Δεν φορτώθηκε.'}); return; }
  } else {
    const l = R.book._d || {};
    /* Προσυμπλήρωση όταν ερχόμαστε από κλήση: ο αριθμός είναι ήδη γνωστός και
       δεν έχει νόημα να τον ξαναγράψει κανείς. */
    d = {card: {id: 0, status: 'active', toPbx: 1, company: (pre && pre.company) || ''},
         phones: (pre && pre.phone) ? [{e164: pre.phone, raw: pre.phone, label: 'main'}] : [],
         fields: [], timeline: [], calls: [],
         totals: {calls: 0, talk: 0, missed: 0}, statuses: l.statuses || {active: 'Ενεργός'},
         labels: l.labels || {main: 'Κύριο'}, people: [], canEdit: true, canDel: false};
    /* Τα δικά μας πεδία χρειάζονται και στη νέα καρτέλα. */
    const ff = await api('book_fields').catch(() => null);
    if (ff) { d.fields = (ff.fields || []).filter(f => f.active).map(f => ({...f, value: ''})); }
  }
  const K = d.card;
  const ed = d.canEdit;
  const inp = (key, label, val, extra) => `<div class="bc-f"><label class="lbl">${label}</label>
    <input class="inp" data-k="${key}" value="${esc(val || '')}" ${ed ? '' : 'disabled'} ${extra || ''}></div>`;

  body.innerHTML = `
  <div class="bc-head">
    <div style="flex:1;min-width:0">
      <b class="bc-title">${esc(K.name || 'Νέα καρτέλα')}</b>
      <div class="mut" style="font-size:12px;margin-top:3px">
        ${d.totals.calls ? `${d.totals.calls} κλήσεις · <b>${callHm(d.totals.talk)}</b> στο τηλέφωνο${
          d.totals.missed ? ` · ${d.totals.missed} αναπάντητες` : ''}` : 'Καμία κλήση ακόμη'}
        ${K.updatedAt ? ` · ενημερώθηκε ${esc(String(K.updatedAt).slice(0, 16))}${K.updatedBy ? ' από ' + esc(K.updatedBy) : ''}` : ''}</div>
    </div>
    ${K.pbxError ? `<span class="bk-dup" style="color:var(--bad);border-color:#f2c9cd;background:#fdf0f1"
      title="${esc(K.pbxError)}">δεν στάλθηκε στο 3CX</span>` : ''}
  </div>

  ${/* Η ΣΕΙΡΑ ΕΧΕΙ ΣΗΜΑΣΙΑ: ποιος είναι και πώς τον βρίσκεις πρώτα. Οι
       λεπτομέρειες (ΑΦΜ, διεύθυνση, ετικέτες) πάνε κάτω — στο κινητό έπρεπε
       να προσπεράσεις δώδεκα πεδία κειμένου για να φτάσεις στα τηλέφωνα. */''}
  <div class="bc-grid">
    ${inp('company', 'Επωνυμία', K.company)}
    ${inp('title', 'Θέση / ρόλος', K.title)}
    ${inp('first', 'Όνομα', K.first)}
    ${inp('last', 'Επώνυμο', K.last)}
  </div>

  <div class="bc-sec">${I.phone} Τηλέφωνα</div>
  <div id="bcPh"></div>
  ${ed ? '<button class="btn btn-o btn-sm" id="bcAddPh" style="margin-top:6px">+ τηλέφωνο</button>' : ''}

  <div class="bc-sec">${I.eye} Παρακολούθηση</div>
  <div class="bc-grid">
    <div class="bc-f"><label class="lbl">Κατάσταση</label>
      <select class="inp" data-k="status" ${ed ? '' : 'disabled'}>
        ${Object.entries(d.statuses).map(([k, l]) => `<option value="${k}" ${K.status === k ? 'selected' : ''}>${l}</option>`).join('')}
      </select></div>
    <div class="bc-f"><label class="lbl">Υπεύθυνος</label>
      <select class="inp" data-k="owner" ${ed ? '' : 'disabled'}>
        <option value="0">— κανείς —</option>
        ${(d.people || []).map(p => `<option value="${p.id}" ${K.owner === p.id ? 'selected' : ''}>${esc(p.name)}</option>`).join('')}
      </select></div>
    ${inp('nextAt', 'Επόμενη κίνηση', K.nextAt, 'type="date"')}
    ${inp('nextNote', 'Τι εκκρεμεί', K.nextNote)}
  </div>

  <div class="bc-f" style="margin-top:10px"><label class="lbl">Πελάτης WHMCS</label>
    <div id="bcCli"></div></div>

  <div class="bc-sec">${I.contact || I.user} Στοιχεία</div>
  <div class="bc-grid">
    ${inp('email', 'Email', K.email, 'type="email"')}
    ${inp('website', 'Ιστοσελίδα', K.website)}
    ${inp('vat', 'ΑΦΜ', K.vat)}
    ${inp('taxOffice', 'ΔΟΥ', K.taxOffice)}
    ${inp('address', 'Διεύθυνση', K.address)}
    ${inp('city', 'Πόλη', K.city)}
    ${inp('postcode', 'ΤΚ', K.postcode)}
    ${inp('tags', 'Ετικέτες', K.tags, 'placeholder="χωρισμένες με κόμμα"')}
  </div>

  ${d.fields.length ? `<div class="bc-sec">${I.tree} Δικά μας πεδία</div>
  <div class="bc-grid">${d.fields.map(f => `<div class="bc-f">
    <label class="lbl">${esc(f.label)}${f.hint ? ` <span class="mut" style="font-weight:400">— ${esc(f.hint)}</span>` : ''}</label>
    ${f.type === 'textarea' ? `<textarea class="inp" data-fid="${f.id}" rows="2" ${ed ? '' : 'disabled'}>${esc(f.value)}</textarea>`
      : f.type === 'select' ? `<select class="inp" data-fid="${f.id}" ${ed ? '' : 'disabled'}>
          <option value="">—</option>
          ${f.options.map(o => `<option ${f.value === o ? 'selected' : ''}>${esc(o)}</option>`).join('')}</select>`
      : f.type === 'check' ? `<label style="display:flex;gap:7px;align-items:center;font-size:12.5px">
          <input type="checkbox" data-fid="${f.id}" ${f.value ? 'checked' : ''} ${ed ? '' : 'disabled'}> ναι</label>`
      : `<input class="inp" data-fid="${f.id}" value="${esc(f.value)}"
          type="${f.type === 'number' ? 'number' : f.type === 'date' ? 'date' : f.type === 'url' ? 'url' : 'text'}" ${ed ? '' : 'disabled'}>`}
  </div>`).join('')}</div>` : ''}

  <div class="bc-f" style="margin-top:12px"><label class="lbl">Σημειώσεις</label>
    <textarea class="inp" data-k="notes" rows="3" ${ed ? '' : 'disabled'}>${esc(K.notes || '')}</textarea></div>

  ${ed ? `<label class="cn-book" style="margin-top:12px">
    <input type="checkbox" id="bcPbx" ${K.toPbx ? 'checked' : ''}>
    να φαίνεται στις οθόνες των τηλεφώνων
    <span class="mut">— στέλνεται στο 3CX με την αποθήκευση</span></label>` : ''}

  <div class="bc-actions">
    ${ed && d.canDel && K.id ? '<button class="btn btn-o" id="bcDel" style="color:var(--bad)">Διαγραφή</button>' : ''}
    ${K.id && d.totals.calls && cnpCan('reports.calls')
      ? `<a class="btn btn-o" href="#/clientcalls/${K.client ? 'c' + K.client : 'b' + K.id}">Η κίνησή του</a>` : ''}
    <span style="flex:1"></span>
    ${ed ? `<button class="btn btn-p" id="bcSave">Αποθήκευση</button>` : ''}
  </div>

  ${K.id ? `
  <div class="bc-sec">${I.clock} Τι έγινε</div>
  ${ed ? `<div class="bc-note">
    <input class="inp" id="bcNs" placeholder="Σημείωσε τι έγινε — μία γραμμή">
    <input class="inp" id="bcNf" type="date" style="width:150px" title="Επόμενη κίνηση">
    <button class="btn btn-o btn-sm" id="bcNadd">Προσθήκη</button></div>` : ''}
  <div class="bc-tl">
    ${d.timeline.map(t => `<div class="bc-ti">
      <span class="bc-td">${esc(String(t.at).slice(5, 16))}</span>
      <span class="bc-ts">${esc(t.summary)}${t.detail ? `<div class="mut">${esc(t.detail)}</div>` : ''}</span>
      <span class="bc-tw mut">${esc(t.who)}</span></div>`).join('')
      || '<div class="mut" style="font-size:12.5px;padding:4px 2px">Τίποτα ακόμη.</div>'}
  </div>

  <div class="bc-sec">${I.phone} Οι κλήσεις</div>
  <div class="cl-list">${d.calls.map(x => `
    <div class="cl-row">
      <span class="cl-d ${x.dir}">${x.dir === 'out' ? '↗' : '↙'}</span>
      <span class="cl-t">${esc(String(x.at).slice(5, 16))}</span>
      <span class="cl-who">${esc(x.admin || '—')}</span>
      <span class="cl-dur">${x.answered ? callHm(x.talk) : '<span class="cl-miss">αναπάντητη</span>'}</span>
      <span class="cl-sum">${esc(x.summary || '')}</span>
    </div>`).join('') || '<div class="mut" style="font-size:12.5px;padding:6px 2px">Καμία κλήση.</div>'}</div>` : ''}`;

  /* ── τηλέφωνα ── */
  let phones = d.phones.length ? d.phones.map(p => ({raw: p.raw || p.e164, label: p.label}))
                               : [{raw: '', label: 'main'}];
  const paintPh = () => {
    $('#bcPh', body).innerHTML = phones.map((p, i) => `<div class="bc-ph">
      <input class="inp" data-ph="${i}" value="${esc(p.raw)}" placeholder="Αριθμός" ${ed ? '' : 'disabled'}>
      <select class="inp" data-phl="${i}" ${ed ? '' : 'disabled'}>
        ${Object.entries(d.labels).map(([k, l]) => `<option value="${k}" ${p.label === k ? 'selected' : ''}>${l}</option>`).join('')}
      </select>
      ${ed ? `<button class="bc-phx" data-phx="${i}" title="Αφαίρεση">✕</button>` : ''}
    </div>`).join('');
    $$('[data-ph]', body).forEach(el => el.oninput = () => { phones[+el.dataset.ph].raw = el.value; });
    $$('[data-phl]', body).forEach(el => el.onchange = () => { phones[+el.dataset.phl].label = el.value; });
    $$('[data-phx]', body).forEach(el => el.onclick = () => {
      phones.splice(+el.dataset.phx, 1);
      if (!phones.length) { phones = [{raw: '', label: 'main'}]; }
      paintPh();
    });
  };
  paintPh();
  if (ed) { $('#bcAddPh', body).onclick = () => { phones.push({raw: '', label: 'mobile'}); paintPh(); }; }

  /* ── πελάτης WHMCS ── */
  let client = {id: K.client || 0, name: K.clientName || ''};
  const paintCli = () => {
    const box = $('#bcCli', body);
    box.innerHTML = client.id
      ? `<div class="bc-cli"><a href="#/client/${client.id}"><b>${esc(client.name)}</b></a>
         ${ed ? '<button class="bc-phx" id="bcCx" title="Αποσύνδεση">✕</button>' : ''}</div>`
      : (ed ? `<input class="inp" id="bcCq" placeholder="Ψάξε πελάτη…" autocomplete="off"><div id="bcCr"></div>`
            : '<span class="mut">—</span>');
    if (client.id && ed) { $('#bcCx', box).onclick = () => { client = {id: 0, name: ''}; paintCli(); }; }
    if (!client.id && ed) {
      let t2 = null;
      $('#bcCq', box).oninput = e => {
        clearTimeout(t2); const v = e.target.value.trim();
        if (v.length < 2) { $('#bcCr', box).innerHTML = ''; return; }
        t2 = setTimeout(async () => {
          const r = await api('client_search&q=' + encodeURIComponent(v)).catch(() => null);
          $('#bcCr', box).innerHTML = ((r && r.results) || []).slice(0, 6).map(x =>
            `<div class="cn-pick" data-cid="${x.id}"><b>${esc(x.name)}</b> <span class="mut">#${x.id}</span></div>`).join('')
            || '<div class="mut" style="padding:6px 2px;font-size:12px">Κανένα</div>';
          $$('.cn-pick', box).forEach(el => el.onclick = () => {
            client = {id: +el.dataset.cid, name: el.textContent.replace(/#\d+$/, '').trim()};
            paintCli();
          });
        }, 280);
      };
    }
  };
  paintCli();

  if (!ed) { return; }

  $('#bcSave', body).onclick = async e => {
    const btn = e.currentTarget;
    const g = k => { const el = body.querySelector(`[data-k="${k}"]`); return el ? el.value.trim() : ''; };
    const fields = {};
    $$('[data-fid]', body).forEach(el => {
      fields[el.dataset.fid] = el.type === 'checkbox' ? (el.checked ? '1' : '') : el.value;
    });
    const payload = () => ({
      id: K.id, company: g('company'), first: g('first'), last: g('last'), title: g('title'),
      email: g('email'), website: g('website'), vat: g('vat'), taxOffice: g('taxOffice'),
      address: g('address'), city: g('city'), postcode: g('postcode'), tags: g('tags'),
      notes: g('notes'), status: g('status'), owner: +g('owner') || 0,
      nextAt: g('nextAt'), nextNote: g('nextNote'), client: client.id,
      toPbx: $('#bcPbx', body).checked ? 1 : 0, dropFromPbx: !$('#bcPbx', body).checked,
      phones: phones.filter(p => p.raw.trim()), fields});

    const send = async extra => {
      btn.disabled = true; btn.textContent = 'αποθηκεύω…';
      try {
        const r = await api('book_save', {...payload(), ...extra});
        const pbx = r.pbx;
        let msg = 'Αποθηκεύτηκε';
        if (r.moved) { msg += ` — ${r.moved === 1 ? 'το τηλέφωνο μεταφέρθηκε' : `${r.moved} τηλέφωνα μεταφέρθηκαν`}`; }
        if (pbx && pbx.ok) { msg += ' · και στα τηλέφωνα'; }
        else if (pbx && pbx.why) { msg += ' · ΟΜΩΣ το 3CX: ' + pbx.why; }
        if (r.orphans && r.orphans.length) {
          msg += ` · η «${r.orphans[0]}» έμεινε χωρίς τηλέφωνο`;
        }
        toast(msg, (pbx && !pbx.ok && pbx.why) || (r.orphans && r.orphans.length) ? 'err' : '');
        closeDrawer(); R.book();
        return true;
      } catch (err) {
        /* 409 = το τηλέφωνο ανήκει αλλού. ΔΕΝ είναι αποτυχία, είναι ερώτηση:
           ένα τηλέφωνο ανήκει σε μία καρτέλα, οπότε ή μεταφέρεται ή αλλάζει. */
        const cl = err.data && err.data.clash;
        btn.disabled = false; btn.textContent = 'Αποθήκευση';
        if (cl && cl.length && !extra.takePhones) {
          const list = cl.map(c => `${c.e164} → «${c.name}»`).join('\n');
          if (confirm(`Το τηλέφωνο ανήκει ήδη σε άλλη καρτέλα:\n\n${list}\n\n`
            + 'Να μεταφερθεί εδώ; Η άλλη καρτέλα μένει, απλώς χάνει αυτόν τον αριθμό.')) {
            return send({takePhones: 1});
          }
          return false;
        }
        toast(err.message || 'Δεν αποθηκεύτηκε', 'err');
        return false;
      }
    };
    send({});
  };

  const del = $('#bcDel', body);
  if (del) {
    del.onclick = async () => {
      if (!confirm(`Διαγραφή της καρτέλας «${K.name}»;\n\nΦεύγει και από το τηλεφωνικό κέντρο. Δεν αναιρείται.`)) { return; }
      try { await api('book_del', {id: K.id}); toast('Διαγράφηκε'); closeDrawer(); R.book(); }
      catch (e2) { toast(e2.message || 'Δεν διαγράφηκε', 'err'); }
    };
  }
  const nadd = $('#bcNadd', body);
  if (nadd) {
    nadd.onclick = async () => {
      const s = $('#bcNs', body).value.trim();
      if (!s) { toast('Γράψε τι έγινε', 'err'); return; }
      try {
        await api('book_note', {book: K.id, summary: s, followup: $('#bcNf', body).value || ''});
        toast('Καταγράφηκε'); bookCard(K.id);
      } catch (e3) { toast(e3.message || 'Δεν καταχωρήθηκε', 'err'); }
    };
  }
}

/* ── ΠΕΔΙΑ ΚΑΤΑΛΟΓΟΥ ────────────────────────────────────────────────────────
   Εδώ είναι η ευελιξία: κάθε εταιρεία κρατά κάτι δικό της — ώρες λειτουργίας,
   κωδικό συνεργάτη, αριθμό άδειας. Αν τα βάζαμε σε στήλες, κάθε νέα απαίτηση
   θα ήταν καινούργια έκδοση του προγράμματος. */
const BF_TYPES = {text: 'Κείμενο', textarea: 'Πολλές γραμμές', number: 'Αριθμός',
                  date: 'Ημερομηνία', select: 'Επιλογή από λίστα', check: 'Ναι/όχι', url: 'Σύνδεσμος'};

R.bookfields = async function () {
  if (!cnpCan('comms.book.edit')) {
    setTop('Πεδία καταλόγου');
    $('#content').innerHTML = cnpDenied({message: 'Χρειάζεται «Τηλεφωνικός κατάλογος → Επεξεργασία»'});
    return;
  }
  setTop('Πεδία καταλόγου', 'Τι επιπλέον κρατάμε σε κάθε καρτέλα — το ορίζεις εσύ, χωρίς προγραμματιστή');
  const c = $('#content');
  c.innerHTML = '<div class="skel" style="height:320px"></div>';
  const d = await api('book_fields').catch(() => null);
  if (!d) { c.innerHTML = '<div class="card"><div class="card-b mut">Δεν φορτώθηκε.</div></div>'; return; }

  c.innerHTML = `
  <div class="cl-bar">
    <span class="mut" style="font-size:12.5px;flex:1">
      Ό,τι προσθέσεις εδώ εμφανίζεται σε κάθε καρτέλα του καταλόγου.</span>
    <button class="btn btn-p btn-sm" id="bfNew">${I.plus} Νέο πεδίο</button>
  </div>
  <div class="card"><div class="card-b" style="padding:4px 6px 8px">
    ${d.fields.length ? `<div class="bk-list">${d.fields.map(f => `
      <div class="bk-row pick" data-bf="${f.id}">
        <span class="bk-dot" style="background:${f.active ? '#2a9d63' : '#8595ac'}"></span>
        <span class="bk-n"><span class="bk-nt">${esc(f.label)}</span>${
          f.hint ? `<span class="bk-city mut">${esc(f.hint)}</span>` : ''}</span>
        <span class="bk-p">${esc(BF_TYPES[f.type] || f.type)}${f.options ? ` · ${esc(f.options)}` : ''}</span>
        <span class="bk-c">${f.used ? `${f.used} καρτέλες` : '<span class="mut">αχρησιμοποίητο</span>'}</span>
        <span class="bk-l">${f.active ? '' : '<span class="bk-no">ανενεργό</span>'}</span>
      </div>`).join('')}</div>`
      : `<div class="cl-empty">Κανένα δικό σου πεδίο ακόμη — πάτα «Νέο πεδίο».</div>`}
  </div></div>`;

  $('#bfNew').onclick = () => bookField(null);
  $$('[data-bf]').forEach(r => r.onclick = () => bookField(d.fields.find(x => x.id === +r.dataset.bf)));
};

function bookField(f) {
  const ovl = document.createElement('div');
  ovl.className = 'ovl show'; ovl.style.zIndex = 330;
  ovl.innerHTML = `<div class="pal-box" style="margin:9vh auto 0;max-width:470px" onclick="event.stopPropagation()">
    <div style="padding:17px 20px 4px"><b style="font-size:15px">${f ? 'Αλλαγή πεδίου' : 'Νέο πεδίο'}</b>
      ${f && f.used ? `<div class="mut" style="font-size:12px;margin-top:3px">Χρησιμοποιείται σε ${f.used} καρτέλες.</div>` : ''}</div>
    <div style="padding:8px 20px 4px">
      <label class="lbl">Όνομα πεδίου</label>
      <input class="inp" id="bfL" maxlength="80" value="${esc(f ? f.label : '')}" placeholder="π.χ. Ώρες λειτουργίας">
      <label class="lbl" style="margin-top:11px">Τύπος</label>
      <select class="inp" id="bfT">
        ${Object.entries(BF_TYPES).map(([k, l]) => `<option value="${k}" ${f && f.type === k ? 'selected' : ''}>${l}</option>`).join('')}
      </select>
      <div id="bfOptBox" style="margin-top:11px" ${f && f.type === 'select' ? '' : 'hidden'}>
        <label class="lbl">Επιλογές <span class="mut" style="font-weight:400">— χωρισμένες με |</span></label>
        <input class="inp" id="bfO" maxlength="400" value="${esc(f ? f.options : '')}" placeholder="Μικρό|Μεσαίο|Μεγάλο">
      </div>
      <label class="lbl" style="margin-top:11px">Βοήθεια <span class="mut" style="font-weight:400">— προαιρετικά</span></label>
      <input class="inp" id="bfH" maxlength="160" value="${esc(f ? f.hint : '')}">
      <label style="display:flex;gap:7px;align-items:center;margin-top:11px;font-size:12.5px">
        <input type="checkbox" id="bfA" ${!f || f.active ? 'checked' : ''}> ενεργό</label>
      <div style="display:flex;gap:8px;margin-top:16px;align-items:center">
        ${f ? '<button class="btn btn-o" id="bfD" style="color:var(--bad)">Διαγραφή</button>' : ''}
        <span style="flex:1"></span>
        <button class="btn btn-o" id="bfX">Άκυρο</button>
        <button class="btn btn-p" id="bfOk">Αποθήκευση</button>
      </div>
    </div></div>`;
  document.body.appendChild(ovl);
  const kill = () => ovl.remove();
  ovl.onclick = kill; $('#bfX', ovl).onclick = kill;
  $('#bfT', ovl).onchange = e => { $('#bfOptBox', ovl).hidden = e.target.value !== 'select'; };
  $('#bfL', ovl).focus();

  $('#bfOk', ovl).onclick = async e => {
    /* Το e.currentTarget μηδενίζεται μόλις τελειώσει ο handler — και επειδή
       είμαστε async, το catch τρέχει ΜΕΤΑ. Κρατάμε το κουμπί σε μεταβλητή,
       αλλιώς η αποτυχία έριχνε «Cannot set properties of null» και το κουμπί
       έμενε κλειδωμένο για πάντα. */
    const btn = e.currentTarget;
    btn.disabled = true;
    try {
      await api('book_fields', {save: {id: f ? f.id : 0, label: $('#bfL', ovl).value.trim(),
        key: f ? f.key : '', type: $('#bfT', ovl).value, options: $('#bfO', ovl).value.trim(),
        hint: $('#bfH', ovl).value.trim(), off: !$('#bfA', ovl).checked}});
      toast('Αποθηκεύτηκε'); kill(); R.bookfields();
    } catch (err) { toast(err.message || 'Δεν αποθηκεύτηκε', 'err'); btn.disabled = false; }
  };
  const del = $('#bfD', ovl);
  if (del) {
    del.onclick = async () => {
      /* Η διαγραφή πεδίου παίρνει μαζί ΚΑΙ τις τιμές του σε όλες τις καρτέλες —
         γι' αυτό λέει πόσες, και δεν αναιρείται. */
      if (!confirm(`Διαγραφή του πεδίου «${f.label}»;${f.used ? `\n\nΘα χαθούν οι τιμές του σε ${f.used} καρτέλες.` : ''}\n\nΔεν αναιρείται.`)) { return; }
      try { await api('book_fields', {del: f.id}); toast('Διαγράφηκε'); kill(); R.bookfields(); }
      catch (e2) { toast(e2.message || 'Δεν διαγράφηκε', 'err'); }
    };
  }
}

/* ═══════════════ ΚΙΝΗΣΗ ΠΕΛΑΤΗ ═══════════════
   «Πόσο σας απασχόλησα;» — η ερώτηση που κάνει ο πελάτης, όχι εμείς. Γι' αυτό
   εδώ το διάστημα είναι ελεύθερο (μήνας, τρίμηνο, χρονιά), η ανάλυση είναι ανά
   μήνα και ανά χειριστή, και το αποτέλεσμα βγαίνει σε αρχείο που στέλνεται.

   Δεν αντικαθιστά την ανάλυση της ημερήσιας αναφοράς: εκείνη απαντά «τι έγινε
   χθες», αυτή «τι έγινε φέτος». */
const CC_PRESETS = [
  ['μήνας', () => [new Date().toISOString().slice(0, 8) + '01', window.CNP.today()]],
  ['τρίμηνο', () => { const d = new Date(); d.setMonth(d.getMonth() - 2, 1);
    return [d.toISOString().slice(0, 10), window.CNP.today()]; }],
  ['φέτος', () => [new Date().getFullYear() + '-01-01', window.CNP.today()]],
];

R.clientcalls = async function (arg) {
  if (!cnpCan('reports.calls')) {
    setTop('Κίνηση πελάτη');
    $('#content').innerHTML = cnpDenied({message: 'Χρειάζεται «Αναφορές → Τηλεφωνική δραστηριότητα»'});
    return;
  }
  setTop('Κίνηση πελάτη', 'Πόσες φορές μας πήρε, πόση ώρα, ποιος τον εξυπηρέτησε');
  const c = $('#content');
  const st = R.clientcalls._s = R.clientcalls._s || {client: 0, book: 0, name: '',
    from: new Date().getFullYear() + '-01-01', to: window.CNP.today()};
  /* Από σύνδεσμο: #/clientcalls/c212 ή #/clientcalls/b447 */
  if (arg) {
    const m = String(arg).match(/^([cb])(\d+)$/);
    if (m) { st.client = m[1] === 'c' ? +m[2] : 0; st.book = m[1] === 'b' ? +m[2] : 0; }
  }

  if (!st.client && !st.book) {
    c.innerHTML = `<div class="card"><div class="card-b">
      <div class="cc-pick"><b>Ποιον πελάτη;</b>
        <div class="mut" style="font-size:12.5px;margin:3px 0 10px">
          Ψάξε πελάτη του WHMCS ή οποιαδήποτε επαφή του τηλεφωνικού καταλόγου.</div>
        <input class="inp" id="ccQ" placeholder="Όνομα, επωνυμία ή τηλέφωνο…" autocomplete="off">
        <div id="ccRes"></div></div>
    </div></div>`;
    let t = null;
    $('#ccQ').oninput = e => {
      clearTimeout(t); const v = e.target.value.trim();
      if (v.length < 2) { $('#ccRes').innerHTML = ''; return; }
      t = setTimeout(async () => {
        const r = await api(`book_list&q=${encodeURIComponent(v)}`).catch(() => null);
        const list = ((r && r.items) || []).slice(0, 10);
        $('#ccRes').innerHTML = list.length ? list.map(b => `
          <div class="cn-pick" data-cc="${b.client ? 'c' + b.client : 'b' + b.id}">
            <b>${esc(b.name)}</b>
            <span class="mut">${b.phones.map(p => esc(p.e164)).join(' · ')}${
              b.calls ? ` · ${b.calls} κλήσεις` : ''}</span></div>`).join('')
          : '<div class="mut" style="padding:8px 2px;font-size:12.5px">Κανένα αποτέλεσμα.</div>';
        $$('.cn-pick').forEach(el => el.onclick = () => {
          const m2 = el.dataset.cc.match(/^([cb])(\d+)$/);
          st.client = m2[1] === 'c' ? +m2[2] : 0; st.book = m2[1] === 'b' ? +m2[2] : 0;
          R.clientcalls();
        });
      }, 300);
    };
    $('#ccQ').focus();
    return;
  }

  c.innerHTML = '<div class="skel" style="height:90px;margin-bottom:14px"></div><div class="skel" style="height:420px"></div>';
  const who = st.client ? 'client=' + st.client : 'book=' + st.book;
  const d = await api(`client_calls&${who}&from=${st.from}&to=${st.to}`).catch(() => null);
  if (!d) { c.innerHTML = '<div class="card"><div class="card-b mut">Δεν φορτώθηκε.</div></div>'; return; }
  R.clientcalls._d = d;
  const t = d.totals;
  const maxM = Math.max(1, ...d.months.map(m => m.talk));
  const maxA = Math.max(1, ...d.admins.map(a => a.talk));
  const mName = ym => { const [y, m] = ym.split('-');
    return ['Ιαν','Φεβ','Μαρ','Απρ','Μάι','Ιούν','Ιούλ','Αύγ','Σεπ','Οκτ','Νοέ','Δεκ'][+m - 1] + ' ' + y.slice(2); };

  c.innerHTML = `
  <div class="cl-bar">
    <button class="btn-s" id="ccBack">← άλλος πελάτης</button>
    <input type="date" class="inp" id="ccF" value="${st.from}" style="width:150px">
    <span class="mut">έως</span>
    <input type="date" class="inp" id="ccT" value="${st.to}" style="width:150px">
    <div class="td-seg cl-seg">${CC_PRESETS.map((p, i) => `<button data-ccp="${i}">${p[0]}</button>`).join('')}</div>
    <span style="flex:1"></span>
    <button class="btn-s" id="ccCsv">${I.download || '⭳'} CSV</button>
    <button class="btn-s" id="ccPrint">Εκτύπωση</button>
  </div>

  <div class="card cc-head"><div class="card-b">
    <div style="display:flex;gap:12px;align-items:flex-start;flex-wrap:wrap">
      <div style="flex:1;min-width:220px">
        <b style="font-size:16px;color:var(--ink)">${esc(d.name)}</b>
        <div class="mut" style="font-size:12px;margin-top:3px">
          ${esc(d.from)} → ${esc(d.to)}${t.firstAt
            ? ` · πρώτη επαφή ${esc(String(t.firstAt).slice(0, 10))}, τελευταία ${esc(String(t.lastAt).slice(0, 10))}` : ''}</div>
        ${d.numbers.length ? `<div class="mut" style="font-size:11.5px;margin-top:4px">
          Τηλέφωνα: ${d.numbers.slice(0, 6).map(esc).join(' · ')}${d.numbers.length > 6 ? ` +${d.numbers.length - 6}` : ''}</div>` : ''}
      </div>
      ${d.client ? `<a class="btn-s" href="#/client/${d.client}">Καρτέλα πελάτη</a>` : ''}
    </div>
  </div></div>

  <div class="cl-tiles">
    <div class="su-stat"><div><div class="n">${t.calls}</div><div class="l">κλήσεις</div></div></div>
    <div class="su-stat"><div><div class="n" style="color:var(--brand)">${callHm(t.talk)}</div>
      <div class="l">συνολικός χρόνος</div></div></div>
    <div class="su-stat"><div><div class="n">${t.in} / ${t.out}</div><div class="l">εισερχόμενες / εξερχόμενες</div></div></div>
    <div class="su-stat"><div><div class="n" style="color:${t.missed ? 'var(--bad)' : ''}">${t.missed}</div>
      <div class="l">αναπάντητες</div></div></div>
    <div class="su-stat"><div><div class="n" style="color:var(--ok)">${callHm(t.billable)}</div>
      <div class="l">χρεώσιμος χρόνος</div></div></div>
  </div>

  <div class="cl-cols">
    <div class="card"><div class="card-h">${I.users} Ποιος τον εξυπηρέτησε</div>
      <div class="card-b">${d.admins.length ? d.admins.map(a => `
        <div class="cl-agg"><span class="cl-an"><span class="cl-ant">${esc(a.name)}</span></span>
          <span class="cl-ab"><i style="width:${Math.round(a.talk / maxA * 100)}%"></i></span>
          <span class="cl-av">${a.calls} · <b>${callHm(a.talk)}</b>${
            a.missed ? ` · <span style="color:var(--bad)">${a.missed} χαμ.</span>` : ''}</span>
        </div>`).join('') : '<div class="mut" style="font-size:12.5px">—</div>'}</div></div>

    <div class="card"><div class="card-h">${I.chart || I.clock} Ανά μήνα</div>
      <div class="card-b">${d.months.length ? d.months.map(m => `
        <div class="cl-agg"><span class="cl-an"><span class="cl-ant">${mName(m.ym)}</span></span>
          <span class="cl-ab"><i style="width:${Math.round(m.talk / maxM * 100)}%;background:#7b5cd6"></i></span>
          <span class="cl-av">${m.calls} · <b>${callHm(m.talk)}</b></span>
        </div>`).join('') : '<div class="mut" style="font-size:12.5px">—</div>'}</div></div>
  </div>

  ${d.cats.length ? `<div class="card"><div class="card-h">${I.tree} Τι ζητούσε
    <span class="mut" style="font-weight:400;font-size:11px;margin-left:auto">από ${t.logged} καταγεγραμμένες κλήσεις</span></div>
    <div class="card-b"><div class="cc-cats">${d.cats.map(k => `
      <div class="cc-cat"><b>${esc(CALL_CAT[k.key] || k.key)}</b>
        <span class="mut">${k.calls} · ${callHm(k.talk)}</span></div>`).join('')}</div></div></div>` : ''}

  <div class="card"><div class="card-h">${I.phone} Οι κλήσεις
    <span class="mut" style="font-weight:400;font-size:11px;margin-left:auto">${
      d.shown < t.calls ? `οι ${d.shown} πιο πρόσφατες από ${t.calls}` : `${d.shown} κλήσεις`}</span></div>
    <div class="card-b" style="padding:4px 6px 8px">
      ${d.items.length ? `<div class="cl-list">${d.items.map(x => `
        <div class="cl-row${x.logged ? ' done' : ''}" data-ccall="${x.id}">
          <span class="cl-d ${x.dir}">${x.dir === 'out' ? '↗' : '↙'}</span>
          <span class="cl-t">${esc(String(x.at).slice(0, 16).replace('T', ' ').slice(5))}</span>
          <span class="cl-who">${esc(x.admin || '—')}</span>
          <span class="cl-dur">${x.answered ? callHm(x.talk) : '<span class="cl-miss">αναπάντητη</span>'}</span>
          <span class="cl-sum">${x.summary ? esc(x.summary) : (d.canLog ? '<span class="cl-todo">κατέγραψε</span>' : '')}</span>
          <span class="cl-bill">${x.bill && CALL_BILL[x.bill]
            ? `<span class="cl-b" style="--bc:${CALL_BILL[x.bill][1]}">${CALL_BILL[x.bill][0]}</span>` : ''}</span>
        </div>`).join('')}</div>`
        : '<div class="cl-empty">Καμία κλήση σε αυτό το διάστημα.</div>'}
    </div></div>`;

  $('#ccBack').onclick = () => { st.client = 0; st.book = 0; R.clientcalls(); };
  $('#ccF').onchange = e => { st.from = e.target.value; R.clientcalls(); };
  $('#ccT').onchange = e => { st.to = e.target.value; R.clientcalls(); };
  $$('[data-ccp]').forEach(b => b.onclick = () => {
    const [f, to] = CC_PRESETS[+b.dataset.ccp][1](); st.from = f; st.to = to; R.clientcalls();
  });
  $$('[data-ccall]').forEach(r => r.onclick = () =>
    callNote(d.items.find(x => x.id === +r.dataset.ccall), d));
  $('#ccPrint').onclick = () => window.print();
  $('#ccCsv').onclick = () => {
    /* Το αρχείο φεύγει προς τον πελάτη — γι' αυτό επικεφαλίδες στα ελληνικά,
       ώρες σε λεπτά (όχι δευτερόλεπτα) και ημερομηνίες όπως τις διαβάζει
       άνθρωπος, όχι όπως τις αποθηκεύει η βάση. */
    const q = v => `"${String(v == null ? '' : v).replace(/"/g, '""')}"`;
    const rows = [['Ημερομηνία', 'Ώρα', 'Κατεύθυνση', 'Αριθμός', 'Χειριστής',
      'Διάρκεια (λεπτά)', 'Απαντήθηκε', 'Τι έγινε', 'Χρέωση'].map(q).join(';')];
    d.items.slice().reverse().forEach(x => rows.push([
      String(x.at).slice(0, 10), String(x.at).slice(11, 16),
      x.dir === 'out' ? 'Εξερχόμενη' : 'Εισερχόμενη', x.other, x.admin,
      (x.talk / 60).toFixed(1).replace('.', ','), x.answered ? 'Ναι' : 'Όχι',
      x.summary, (CALL_BILL[x.bill] || [''])[0]].map(q).join(';')));
    rows.push('');
    rows.push([q('ΣΥΝΟΛΟ'), q(t.calls + ' κλήσεις'), q((t.talk / 60).toFixed(1).replace('.', ',') + ' λεπτά')].join(';'));
    /* BOM, αλλιώς το Excel διαβάζει τα ελληνικά ως σύμβολα. */
    const blob = new Blob(['﻿' + rows.join('\r\n')], {type: 'text/csv;charset=utf-8'});
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = `κινηση-${d.name.replace(/[^\wΑ-Ωα-ωά-ώ]+/g, '-').slice(0, 40)}-${d.from}_${d.to}.csv`;
    a.click(); URL.revokeObjectURL(a.href);
  };
};
