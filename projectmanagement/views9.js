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
