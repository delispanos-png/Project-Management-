/* ═══════════ CloudOn Agent — ΔΙΑΣΥΝΔΕΣΗ 3CX ═══════════
   Φάση 0–2: πού είναι το PBX, ποιος είμαστε απέναντί του, τι υποστηρίζει
   πραγματικά, και πόσο κοστίζει η ώρα του καθενός.
   Ο έλεγχος σύνδεσης ΔΕΝ είναι διακοσμητικός: ρωτάει το ίδιο το PBX και
   «παγώνει το συμβόλαιο» — τι υπάρχει, τι όχι, πόσο γρήγορα απαντά. */
'use strict';
const {S, api, esc, toast, setTop, cnpConfirm, cnpDenied, cnpCan, dShort, I, $, $$} = window.CNP;
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
  const [d, m] = await Promise.all([
    api('pbx_settings').catch(() => null),
    api('pbx_map').catch(() => null),
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
  const st = R.calls._s = R.calls._s || {d: window.CNP.today(), days: 1, who: 0};
  c.innerHTML = '<div class="skel" style="height:90px;margin-bottom:14px"></div><div class="skel" style="height:420px"></div>';
  const d = await api(`calls_report&d=${st.d}&days=${st.days}&who=${st.who}`).catch(() => null);
  if (!d) { c.innerHTML = '<div class="card"><div class="card-b mut">Δεν φορτώθηκε.</div></div>'; return; }
  const t = d.totals;

  const tile = (n, l, col) => `<div class="su-stat"><div><div class="n" style="color:${col || ''}">${n}</div>
    <div class="l">${l}</div></div></div>`;

  const dirIco = x => x === 'in' ? '<span class="cl-d in" title="Εισερχόμενη">↙</span>'
    : x === 'out' ? '<span class="cl-d out" title="Εξερχόμενη">↗</span>'
    : '<span class="cl-d int" title="Εσωτερική">↔</span>';

  const row = x => `<div class="cl-row${x.logged ? ' done' : ''}" data-call="${x.id}">
    ${dirIco(x.dir)}
    <span class="cl-t">${esc((x.at || '').slice(11, 16))}</span>
    <span class="cl-who">${x.adminName ? esc(x.adminName) : '<span class="mut">—</span>'}</span>
    <span class="cl-other">${x.clientName ? `<b>${esc(x.clientName)}</b>`
      : x.skipLabel ? `<span class="mut">${esc(x.skipLabel)}</span>`
      : x.anon ? '<span class="mut" title="Ο καλών απέκρυψε τον αριθμό του">απόκρυψη αριθμού</span>'
      : esc(x.other || '—')}</span>
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
    <span style="flex:1"></span>
    <button class="btn-s" id="clSync" title="Τράβα ό,τι νέο από το τηλεφωνικό κέντρο">↻ Ανανέωση</button>
    <span class="mut" style="font-size:11.5px">${t.logged}/${t.calls} καταγεγραμμένες</span>
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
        <div class="cl-agg"><span class="cl-an">${esc(a.name)}</span>
          <span class="cl-ab"><i style="width:${Math.round(a.talk / Math.max(1, d.perAdmin[0].talk) * 100)}%"></i></span>
          <span class="cl-av">${a.calls} κλήσεις · <b>${callHm(a.talk)}</b>${a.missed ? ` · <span style="color:var(--bad)">${a.missed} χαμένες</span>` : ''}</span>
        </div>`).join('') : '<div class="mut" style="font-size:12.5px">—</div>'}</div></div>

    <div class="card"><div class="card-h">${I.building} Ποιος μας απασχολεί
      <span class="mut" style="font-weight:400;font-size:11px;margin-left:auto">κατά χρόνο</span></div>
      <div class="card-b">${d.perClient.length ? d.perClient.map(x => `
        <div class="cl-agg${x.client ? ' pick' : ''}" ${x.client ? `data-cli="${x.client}"` : ''}>
          <span class="cl-an">${esc(x.name)}</span>
          <span class="cl-ab"><i style="width:${Math.round(x.talk / Math.max(1, d.perClient[0].talk) * 100)}%;background:#7b5cd6"></i></span>
          <span class="cl-av">${x.calls} · <b>${callHm(x.talk)}</b></span>
        </div>`).join('') : '<div class="mut" style="font-size:12.5px">—</div>'}</div></div>
  </div>

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
  }
};

/** Η καταγραφή του agent: τι έγινε, και αν χρεώνεται — με λόγο. */
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
        <b>${esc(x.clientName || x.skipLabel || (x.anon ? 'απόκρυψη αριθμού' : x.other) || '—')}</b>${x.answered ? ' · ' + callHm(x.talk) : ' · αναπάντητη'}</div>
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
            : 'Θα ισχύσει για όλες τις κλήσεις του, παλιές και νέες.'}</div>
        <input class="inp" id="cnLq" placeholder="Γράψε όνομα πελάτη…" autocomplete="off">
        <div id="cnLres"></div>
        <button type="button" class="btn btn-sm btn-o" id="cnLskip" style="margin-top:7px">Δεν είναι πελάτης</button>
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
        else { toast(`${r.clientName} — ενημερώθηκαν ${r.updated} κλήσεις`); }
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
          link({e164: x.other, client: +el.dataset.cid}));
      }, 260);
    };
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
