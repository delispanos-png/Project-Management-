/* ═══════════ CloudOn Projects — standalone SPA ═══════════ */
'use strict';

const $ = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => [...r.querySelectorAll(s)];
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const fmtMin = m => { m = +m || 0; const h = Math.floor(m / 60), r = m % 60; return h && r ? `${h}ω ${r}΄` : h ? `${h}ω` : `${r}΄`; };
const fmtEur = v => (+v || 0).toLocaleString((window.CNP_LOCALE||'el-GR'), {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' €';
/* ═══ ΩΡΑ 24ΩΡΗ, ΠΑΝΤΟΥ ═══
   Το <input type="time"> το ζωγραφίζει ο browser με τη ΔΙΚΗ του γλώσσα: στο ίδιο
   πεδίο άλλος έβλεπε «02:30 PM» κι άλλος «14:30». Ούτε το lang του πεδίου ούτε η
   γλώσσα της σελίδας το αλλάζουν — το μετρήσαμε (23/09/2026). Και η ώρα δεν είναι
   προτίμηση συσκευής: όταν λέμε «η σύσκεψη λήγει 23:40» πρέπει να το διαβάζουν
   όλοι το ίδιο.
   Γι' αυτό γράφουμε δικό μας πεδίο. Η ΤΙΜΗ μένει ακριβώς η ίδια («ΩΩ:ΛΛ»), ώστε
   κάθε υπάρχον `.value` να δουλεύει χωρίς αλλαγή. */
function timeInput(id, value, extra) {
  return `<input type="text" class="inp tinp" ${id ? 'id="' + id + '" ' : ''}value="${esc(value || '')}"`
    + ` maxlength="5" inputmode="numeric" autocomplete="off" placeholder="ωω:λλ"`
    + ` aria-label="Ώρα, 24ωρη μορφή"${extra ? ' ' + extra : ''}>`;
}
/** «9» → 09:00 · «930» → 09:30 · «9:5» → 09:05 · ό,τι δεν στέκει → κενό. */
function cnpTimeNorm(v) {
  const d = String(v || '').replace(/\D/g, '');
  if (!d) { return ''; }
  let h, m;
  if (d.length <= 2) { h = +d; m = 0; }
  else if (d.length === 3) { h = +d.slice(0, 1); m = +d.slice(1); }
  else { h = +d.slice(0, 2); m = +d.slice(2, 4); }
  if (h > 23 || m > 59) { return ''; }
  return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
}
document.addEventListener('input', e => {
  const el = e.target;
  if (!el.classList || !el.classList.contains('tinp')) { return; }
  /* Μόνο ψηφία· η άνω-κάτω τελεία μπαίνει μόνη της ΜΟΛΙΣ βγάζει νόημα. Αν τα δύο
     πρώτα ψηφία δεν είναι ώρα (π.χ. γράφεις «930» για 9:30), δεν τη βάζουμε — θα
     έδειχνε «93:0». Το blur το τακτοποιεί. */
  const raw = el.value.replace(/\D/g, '').slice(0, 4);
  el.value = (raw.length > 2 && +raw.slice(0, 2) <= 23) ? raw.slice(0, 2) + ':' + raw.slice(2, 4) : raw;
});
document.addEventListener('blur', e => {
  const el = e.target;
  if (!el.classList || !el.classList.contains('tinp')) { return; }
  const before = el.value;
  el.value = cnpTimeNorm(el.value);
  /* Η αυτόματη αποθήκευση ακούει το `change` — αν διορθώσαμε τη μορφή, ειδοποίησέ τη. */
  if (el.value !== before) { el.dispatchEvent(new Event('change', {bubbles: true})); }
}, true);

const dShort = d => d ? new Date(d.replace(' ', 'T')).toLocaleDateString((window.CNP_LOCALE||'el-GR'), {day: '2-digit', month: '2-digit'}) : '';
/* ΠΑΝΤΟΥ 24ΩΡΟ. Χωρίς hour12:false η μορφή ακολουθεί τον browser: ο ίδιος χρόνος
   έβγαινε «02:31 μ.μ.» σε έναν υπολογιστή και «14:31» σε άλλον — και σε λίστες
   κλήσεων ή συσκέψεων αυτό διαβάζεται λάθος. Η ώρα είναι δεδομένο, όχι προτίμηση
   συσκευής. */
const tShort = d => d ? new Date(d.replace(' ', 'T')).toLocaleString((window.CNP_LOCALE||'el-GR'), {day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit', hour12: false}) : '';
/* Πλήρης ημερομηνία, ΠΑΝΤΑ ηη/μμ/εεεε. Το ISO (2026-08-18) και η αμερικανική
   σειρά δεν εμφανίζονται πουθενά στην εφαρμογή. */
const dFull = d => {
  if (!d) { return ''; }
  const t = new Date(String(d).replace(' ', 'T'));
  if (isNaN(t)) { return String(d); }
  const p2 = n => String(n).padStart(2, '0');
  return `${p2(t.getDate())}/${p2(t.getMonth() + 1)}/${t.getFullYear()}`;
};
const today = () => new Date().toISOString().slice(0, 10);

const I = { // inline icons
  board: '<svg width="16" height="16" style="vertical-align:-2px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="18" rx="2"/><rect x="14" y="3" width="7" height="12" rx="2"/></svg>',
  sun: '<svg width="16" height="16" style="vertical-align:-2px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M2 12h2m16 0h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>',
  target: '<svg width="16" height="16" style="vertical-align:-2px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1.5" fill="currentColor"/></svg>',
  chart: '<svg width="16" height="16" style="vertical-align:-2px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 20V10m6 10V4m6 16v-7m4 7H2"/></svg>',
  bell: '<svg width="16" height="16" style="vertical-align:-2px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0"/></svg>',
  sos: '<svg width="16" height="16" style="vertical-align:-2px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="4"/><path d="M4.9 4.9l4.2 4.2M14.9 14.9l4.2 4.2M19.1 4.9l-4.2 4.2M9.1 14.9l-4.2 4.2"/></svg>',
  plus: '<svg width="16" height="16" style="vertical-align:-2px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M12 5v14M5 12h14"/></svg>',
  eye: '<svg width="16" height="16" style="vertical-align:-2px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>',
  play: '<svg width="16" height="16" style="vertical-align:-2px" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>',
  stop: '<svg width="16" height="16" style="vertical-align:-2px" viewBox="0 0 24 24" fill="currentColor"><rect x="6" y="6" width="12" height="12" rx="2"/></svg>',
  ticket: '<svg width="16" height="16" style="vertical-align:-2px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 3v4m0 10v4M3 12h4m10 0h4"/></svg>',
  list: '<svg width="16" height="16" style="vertical-align:-2px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>',
  cal: '<svg width="16" height="16" style="vertical-align:-2px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>',
  clock: '<svg width="16" height="16" style="vertical-align:-2px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>',
  doc: '<svg width="16" height="16" style="vertical-align:-2px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M9 15h6M9 11h2"/></svg>',
  book: '<svg width="16" height="16" style="vertical-align:-2px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V4a2 2 0 0 0-2-2H6.5A2.5 2.5 0 0 0 4 4.5z"/><path d="M4 19.5A2.5 2.5 0 0 0 6.5 22H20v-5"/></svg>',
  chat: '<svg width="16" height="16" style="vertical-align:-2px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
  send: '<svg width="15" height="15" style="vertical-align:-2px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>',
  lock: '<svg width="14" height="14" style="vertical-align:-2px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>',
  sparkle: '<svg width="15" height="15" style="vertical-align:-2px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l1.9 5.8a2 2 0 0 0 1.3 1.3L21 12l-5.8 1.9a2 2 0 0 0-1.3 1.3L12 21l-1.9-5.8a2 2 0 0 0-1.3-1.3L3 12l5.8-1.9a2 2 0 0 0 1.3-1.3z"/></svg>',
  fileText: '<svg width="15" height="15" style="vertical-align:-2px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>',
  clip: '<svg width="16" height="16" style="vertical-align:-3px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>',
  monitor: '<svg width="16" height="16" style="vertical-align:-3px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>',
  clipboard: '<svg width="15" height="15" style="vertical-align:-2px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/><path d="M9 12l2 2 4-4"/></svg>',
  phone: '<svg width="16" height="16" style="vertical-align:-2px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3-8.7A2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.13.96.36 1.9.7 2.8a2 2 0 0 1-.45 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.45c.9.34 1.85.57 2.8.7a2 2 0 0 1 1.7 2.05z"/></svg>',
  flag: '<svg width="16" height="16" style="vertical-align:-2px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1zM4 22v-7"/></svg>',
  user: '<svg width="16" height="16" style="vertical-align:-2px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
  coin: '<svg width="16" height="16" style="vertical-align:-2px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M15 9.5c-.6-1-1.7-1.5-3-1.5-1.8 0-3 1-3 2.2 0 3 6 1.5 6 4.3 0 1.3-1.3 2.2-3 2.2-1.4 0-2.5-.5-3-1.5M12 6v2m0 8v2"/></svg>',
  tree: '<svg width="16" height="16" style="vertical-align:-2px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="2" width="6" height="5" rx="1"/><rect x="2" y="17" width="6" height="5" rx="1"/><rect x="16" y="17" width="6" height="5" rx="1"/><path d="M12 7v4m0 0H5v6m7-6h7v6"/></svg>',
  gantt: '<svg width="16" height="16" style="vertical-align:-2px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 5h8m-8 7h13M3 19h6"/><rect x="13" y="3" width="7" height="4" rx="1.5"/><rect x="18" y="10" width="4" height="4" rx="1.5"/><rect x="11" y="17" width="9" height="4" rx="1.5"/></svg>',
  gear: '<svg width="16" height="16" style="vertical-align:-2px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33h.01a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51h.01a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82v.01a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
  folder: '<svg width="16" height="16" style="vertical-align:-2px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>',
  tag: '<svg width="15" height="15" style="vertical-align:-2px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>',
  link: '<svg width="14" height="14" style="vertical-align:-2px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>',
  copy: '<svg width="14" height="14" style="vertical-align:-2px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>',
  chev: '<svg width="14" height="14" style="vertical-align:-2px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>',
  grip: '<svg width="14" height="14" style="vertical-align:-2px" viewBox="0 0 24 24" fill="currentColor"><circle cx="9" cy="6" r="1.6"/><circle cx="15" cy="6" r="1.6"/><circle cx="9" cy="12" r="1.6"/><circle cx="15" cy="12" r="1.6"/><circle cx="9" cy="18" r="1.6"/><circle cx="15" cy="18" r="1.6"/></svg>',
  trash: '<svg width="14" height="14" style="vertical-align:-2px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2m2 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>',
  funnel: '<svg width="15" height="15" style="vertical-align:-2px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 3H2l8 9.46V19l4 2v-8.54z"/></svg>',
  users: '<svg width="15" height="15" style="vertical-align:-2px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
};
// επιπλέον inline icons (headers/labels) — ίδιο Feather-style
const _mkI = (p) => '<svg width="15" height="15" style="vertical-align:-2px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + p + '</svg>';
Object.assign(I, {
  box: _mkI('<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>'),
  shield: _mkI('<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>'),
  rocket: _mkI('<path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"/><path d="M12 15l-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"/><path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0"/><path d="M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5"/>'),
  repeat: _mkI('<polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/>'),
  lab: _mkI('<path d="M9 3h6v5l4.4 8.3a2 2 0 0 1-1.77 2.94H6.37A2 2 0 0 1 4.6 16.3L9 8z"/><path d="M9 3v5M15 3v5M7 14h10"/>'),
  checkSquare: _mkI('<polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>'),
  compass: _mkI('<circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/>'),
  zap: _mkI('<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>'),
  trophy: _mkI('<path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6M18 9h1.5a2.5 2.5 0 0 0 0-5H18M4 22h16M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22M18 2H6v7a6 6 0 0 0 12 0V2Z"/>'),
  scale: _mkI('<path d="M12 3v18M3 7h18M6 7l-3 6a3 3 0 0 0 6 0zM18 7l-3 6a3 3 0 0 0 6 0zM7 21h10"/>'),
  contact: _mkI('<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M6 16a3 3 0 0 1 6 0"/><line x1="15" y1="9" x2="18" y2="9"/><line x1="15" y1="13" x2="18" y2="13"/>'),
  receipt: _mkI('<path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z"/><path d="M8 7h8M8 11h8M8 15h5"/>'),
  building: _mkI('<rect x="4" y="2" width="16" height="20" rx="2"/><path d="M9 22v-4h6v4M8 6h.01M12 6h.01M16 6h.01M8 10h.01M12 10h.01M16 10h.01M8 14h.01M12 14h.01M16 14h.01"/>'),
  fire: _mkI('<path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.07-2.14-.22-4.05 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.15.43-2.29 1-3a2.5 2.5 0 0 0 2.5 2.5z"/>'),
  snow: _mkI('<line x1="12" y1="2" x2="12" y2="22"/><path d="m20 16-4-4 4-4M4 8l4 4-4 4M16 4l-4 4-4-4M8 20l4-4 4 4"/><line x1="2" y1="12" x2="22" y2="12"/>'),
  megaphone: _mkI('<path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/>'),
  key: _mkI('<path d="M21 2l-2 2m-7.6 7.6a5.5 5.5 0 1 1-7.78 7.78 5.5 5.5 0 0 1 7.77-7.78zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3"/>'),
  brain: _mkI('<path d="M12 5a3 3 0 0 0-5.99.14 3 3 0 0 0-2.2 4.9 3 3 0 0 0 .7 4.86A3 3 0 0 0 9 19a3 3 0 0 0 3-1zM12 5a3 3 0 0 1 5.99.14 3 3 0 0 1 2.2 4.9 3 3 0 0 1-.7 4.86A3 3 0 0 1 15 19a3 3 0 0 1-3-1z"/>'),
  bot: _mkI('<rect x="3" y="11" width="18" height="10" rx="2"/><circle cx="12" cy="5" r="2"/><path d="M12 7v4M8 16h.01M16 16h.01"/>'),
  heart: _mkI('<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8z"/>'),
  bulb: _mkI('<path d="M9 18h6M10 22h4M12 2a7 7 0 0 0-4 12.7c.6.5 1 1.3 1 2.1V17h6v-.2c0-.8.4-1.6 1-2.1A7 7 0 0 0 12 2z"/>'),
  trendUp: _mkI('<polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/>'),
  puzzle: _mkI('<path d="M4 7h3a1 1 0 0 0 1-1V5a2 2 0 0 1 4 0v1a1 1 0 0 0 1 1h3v3a1 1 0 0 0 1 1h1a2 2 0 0 1 0 4h-1a1 1 0 0 0-1 1v3h-3a1 1 0 0 1-1-1v-1a2 2 0 0 0-4 0v1a1 1 0 0 1-1 1H4v-3a1 1 0 0 0-1-1H2a2 2 0 0 1 0-4h1a1 1 0 0 0 1-1z"/>'),
  save: _mkI('<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>'),
  download: _mkI('<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>'),
  edit: _mkI('<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>'),
  video: _mkI('<polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/>'),
  pin: _mkI('<path d="M12 17v5"/><path d="M9 10.76a2 2 0 0 1-1.11 1.79l-1.78.9A2 2 0 0 0 5 15.24V16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-.76a2 2 0 0 0-1.11-1.79l-1.78-.9A2 2 0 0 1 15 10.76V7a1 1 0 0 1 1-1 2 2 0 0 0 0-4H8a2 2 0 0 0 0 4 1 1 0 0 1 1 1z"/>'),
  search: _mkI('<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>'),
  mail: _mkI('<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 5L2 7"/>'),
  crown: _mkI('<path d="m2 4 3 12h14l3-12-6 7-4-7-4 7-6-7z"/><path d="M5 20h14"/>'),
  briefcase: _mkI('<rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>'),
  handshake: _mkI('<path d="M11 17l2 2a1 1 0 1 0 3-3"/><path d="M14 14l2.5 2.5a1 1 0 1 0 3-3l-3.88-3.88a3 3 0 0 0-4.24 0l-.88.88a1 1 0 1 1-3-3l2.81-2.81a5.79 5.79 0 0 1 7.06-.87l.47.28a2 2 0 0 0 1.42.25L21 4"/><path d="M3 4h8"/><path d="M3 3l-1 11 6.5 6.5a1 1 0 1 0 3-3"/>'),
  moon: _mkI('<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>'),
  alert: _mkI('<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>'),
});

// stat tile (icon + αριθμός + label) — ενιαίο στυλ dashboards
const suStat = (ic, n, l, col, extra) => `<div class="su-stat" ${extra || ''}><div class="ic" style="background:${col}1a;color:${col}">${ic}</div>
  <div><div class="n">${n}</div><div class="l">${l}</div></div></div>`;
const S = {boot: null, view: 'myday', project: 0, theme: localStorage.cnpTheme || 'light'};
document.documentElement.dataset.theme = S.theme;

/* HTML από επικόλληση/εισαγωγή: ξαναπερνά από τον parser σε απομονωμένο <template>, ώστε
   κάθε ανοιχτό tag να κλείσει ΜΕΣΑ στο απόσπασμα. Αλλιώς ένα ανισόρροπο <div> σε μία ενέργεια
   «κατάπινε» ό,τι ακολουθούσε στην καρτέλα (εργασία #191). */
/* Ο server αρνήθηκε κώδικα μέσα σε ενέργεια: ξεκάθαρο παράθυρο, όχι toast που χάνεται. */
async function cnpCodeRefused(er) {
  const d = er && er.data; if (!d || d.need !== 'attach') { return false; }
  await cnpDialog({title: '📎 Ο κώδικας πάει σε συνημμένο', body: (er.message || d.error) + '\n\nΤο κείμενό σου παραμένει στη σύνθεση — σβήσε τον κώδικα, επισύναψε το αρχείο και στείλε ξανά.', ok: 'Κατάλαβα', cancel: null});
  return true;
}
function cnpBalanced(html) {
  const s = String(html || ''); if (s.indexOf('<') < 0) { return s; }
  const t = document.createElement('template'); t.innerHTML = s; return t.innerHTML;
}
async function api(a, data) {
  const opt = data ? {method: 'POST', body: JSON.stringify(data), headers: {'Content-Type': 'application/json'}} : {};
  const r = await fetch('api.php?a=' + a + (data ? '' : '&_=' + Date.now()), {credentials: 'same-origin', ...opt});
  if (r.status === 401) { location.href = '/cloudonadminpanel/addonmodules.php?module=cloudonprojects&pmlaunch=1'; throw new Error('auth'); }
  const j = await r.json();
  /* Το σφάλμα κουβαλά ΟΛΟ το σώμα: ο server στέλνει και δομημένα πεδία (π.χ.
     need:'due') που το UI χρειάζεται για να αντιδράσει, όχι μόνο το μήνυμα. */
  if (j.error) { const e = new Error(j.error); e.data = j; throw e; }
  return j;
}
function toast(msg, err) {
  let w = $('#toasts'); if (!w) { w = document.createElement('div'); w.id = 'toasts'; document.body.appendChild(w); }
  const t = document.createElement('div'); t.className = 'toast' + (err ? ' err' : '');
  t.innerHTML = (err ? '⚠️ ' : '✓ ') + esc(msg); w.appendChild(t);
  setTimeout(() => { t.style.opacity = 0; t.style.transition = 'opacity .3s'; setTimeout(() => t.remove(), 320); }, 2600);
}
/** Toast με κουμπιά — για ενέργειες που έγιναν μόνες τους και θέλουν αναίρεση. */
function toastDo(msg, actions, ms) {
  let w = $('#toasts'); if (!w) { w = document.createElement('div'); w.id = 'toasts'; document.body.appendChild(w); }
  const t = document.createElement('div');
  t.className = 'toast toast-do';
  t.innerHTML = '<span>' + esc(msg) + '</span>'
    + (actions || []).map((a, i) => `<button data-ta="${i}">${esc(a.label)}</button>`).join('');
  w.appendChild(t);
  const kill = () => { t.style.opacity = 0; t.style.transition = 'opacity .3s'; setTimeout(() => t.remove(), 320); };
  (actions || []).forEach((a, i) => {
    const b = t.querySelector(`[data-ta="${i}"]`);
    if (b) { b.onclick = () => { kill(); if (a.on) { a.on(); } }; }
  });
  setTimeout(kill, ms || 9000);
}

/* ── Ένα σφάλμα JS δεν επιτρέπεται να είναι αόρατο ───────────────────────────
   Αν σκάσει μέσα σε handler, η οθόνη συνεχίζει να ΦΑΙΝΕΤΑΙ σωστή αλλά δεν
   αποθηκεύει τίποτα — και ο χειριστής νομίζει ότι φταίει αυτός («ό,τι κι αν
   κάνω, δεν καταχωρεί»). Σχεδόν πάντα η αιτία είναι καρτέλα που έμεινε ανοιχτή
   πάνω σε παλιά έκδοση, γι' αυτό λέμε ΚΑΙ τι να κάνει. */
let _cnpErrAt = 0;
function cnpFatal(msg) {
  if (Date.now() - _cnpErrAt < 60000) { return; }     // όχι καταιγισμός
  _cnpErrAt = Date.now();
  try { toast('Σφάλμα στην οθόνη — οι αλλαγές ίσως δεν αποθηκεύονται. Ανανέωσε (Ctrl+Shift+R).', true); } catch (e) { /* ignore */ }
  try { if (window.cnpUpdBanner) { window.cnpUpdBanner(); } } catch (e) { /* ignore */ }
  try { console.error('[CNP]', msg); } catch (e) { /* ignore */ }
}
window.addEventListener('error', e => cnpFatal((e && e.message) || 'error'));
window.addEventListener('unhandledrejection', e => {
  const r = e && e.reason;
  if (r && (r.message === 'auth' || String(r).includes('auth'))) { return; }   // το login το χειρίζεται το api()
  cnpFatal((r && r.message) || String(r));
});

const adminName = id => (S.boot.admins.find(a => a.id === +id) || {}).name || '—';
const adminIni = id => (S.boot.admins.find(a => a.id === +id) || {}).ini || '';
const statusOf = id => S.boot.statuses.find(s => s.id === +id) || {};
/* Η ΜΙΑ ετικέτα κατάστασης εργασίας — παντού η ίδια: κάρτα, λίστες, kanban, ημερολόγιο,
   μέρα ομάδας. Κανένα άλλο σημείο δεν χρωματίζει κατάσταση μόνο του (20/09/2026). */
const stPill = (id, extra) => {
  const s = statusOf(id); const c = s.color || '#8291a9';
  return `<span class="st-pill${s.phase ? ' st-' + s.phase : ''}" style="color:${c};background:${c}22;border-color:${c}55" title="Κατάσταση: ${esc(s.title || '—')}"${extra ? ' ' + extra : ''}>${esc(s.title || '—')}</span>`;
};
/* Η ίδια κατάσταση ως ΚΟΥΚΚΙΔΑ, για στενά κελιά (ημερολόγιο): ίδιο χρώμα, το όνομα στο title. */
const stDot = id => { const s = statusOf(id); return `<i class="st-dot" style="background:${s.color || '#8291a9'}" title="Κατάσταση: ${esc(s.title || '—')}"></i>`; };
/* Η κατάσταση «Ολοκληρώθηκε» — η φάση done, ΟΧΙ το «Ακυρωμένο» (κι αυτό είναι τελικό). */
const doneStatus = () => S.boot.statuses.find(x => x.phase === 'done') || S.boot.statuses.find(x => x.done && !x.cancel) || S.boot.statuses.find(x => x.done);
const typeOf = id => S.boot.types.find(t => t.id === +id);

/* ───────── shell ───────── */
/* ── edge swipe (native feel): σύρσιμο από την αριστερή άκρη → πίσω ή άνοιγμα μενού ── */
(function edgeSwipe() {
  let x0 = null, y0 = 0, t0 = 0;
  document.addEventListener('touchstart', e => {
    x0 = null;
    if (e.touches.length !== 1) { return; }
    const t = e.touches[0];
    if (t.clientX > 30) { return; }              // μόνο από την αριστερή άκρη
    x0 = t.clientX; y0 = t.clientY; t0 = Date.now();
  }, {passive: true});
  document.addEventListener('touchend', e => {
    if (x0 === null) { return; }
    const t = e.changedTouches[0];
    const dx = t.clientX - x0, dy = Math.abs(t.clientY - y0), dt = Date.now() - t0;
    x0 = null;
    if (dx < 70 || dy > 55 || dt > 700) { return; }   // καθαρό, γρήγορο οριζόντιο swipe
    if (!matchMedia('(max-width:768px)').matches) { return; }
    if (document.querySelector('.drawer.show, .ovl.show')) { return; }   // ανοιχτό πάνελ → μην παρεμβαίνεις
    const back = document.querySelector('#ibBack, #chBack');
    if (document.body.classList.contains('detail-open') && back) { back.click(); }
  }, {passive: true});
})();

/* ══ Ο ΕΠΙΛΟΓΕΑΣ ΤΟΥ ΔΙΚΟΥ ΣΟΥ ΜΕΝΟΥ ══════════════════════════════════════
   Δείχνει ΟΛΟ το μενού που δικαιούσαι, ομαδοποιημένο, και σε αφήνει να κρατήσεις
   όσα πιάνεις στα χέρια σου. Ολόκληρο κύκλωμα με ένα κλικ, ή μεμονωμένες οθόνες.

   Δύο κανόνες που δεν παραβιάζονται:
   • Κρύβει, δεν απαγορεύει — τα δικαιώματα δίνονται από τις Ομάδες και δεν τα
     αγγίζει αυτό εδώ. Ό,τι κρύψεις το φτάνεις πάντα με Ctrl+K.
   • Ζει στον server, όχι στον browser: σε ακολουθεί σε κάθε συσκευή. */
function cnpMyMenu() {
  const groups = S.navAll || [];
  const pick = new Set(S.menuMine || []);
  const ovl = document.createElement('div'); ovl.className = 'ovl mym-ovl';
  const box = document.createElement('div'); box.className = 'mym-box';
  const startMine = S.boot.me.menuMode === 'mine';
  box.innerHTML = `
    <div class="mym-h"><div><b>Το μενού μου</b>
      <div class="mut">Διάλεξε ολόκληρα κυκλώματα ή μεμονωμένες οθόνες — όσες θέλεις.</div></div>
      <span class="mym-n" id="mymN">${pick.size}</span></div>
    <div class="mym-b">${groups.map(([g, items], gi) => `
      <div class="mym-g" data-g="${gi}">
        <label class="mym-gh"><input type="checkbox" data-gall="${gi}"><span class="mym-gt">${esc(g)}</span>
          <span class="mym-gn">${items.length}</span><span class="mym-all">όλο το κύκλωμα</span></label>
        ${items.map(([k, ic, lb]) => `<label class="mym-i"><input type="checkbox" data-mk="${esc(k)}"${
          pick.has(k) ? ' checked' : ''}><span>${esc(lb)}</span></label>`).join('')}
      </div>`).join('')}</div>
    <div class="mym-f">
      <label class="mym-def"><input type="checkbox" id="mymStart"${startMine ? ' checked' : ''}>
        <span>Να ξεκινώ με το δικό μου μενού όταν συνδέομαι</span></label>
      <div class="mym-btns">
        <button class="btn btn-o btn-sm" id="mymNo">Άκυρο</button>
        <button class="btn btn-p btn-sm" id="mymOk">Αποθήκευση</button></div>
    </div>`;
  ovl.appendChild(box); document.body.appendChild(ovl);
  requestAnimationFrame(() => ovl.classList.add('show'));   /* το .ovl ξεκινά διάφανο */

  const chks = () => [...box.querySelectorAll('[data-mk]')];
  const paint = () => {
    $('#mymN', box).textContent = chks().filter(x => x.checked).length;
    box.querySelectorAll('[data-gall]').forEach(ga => {
      const its = [...ga.closest('.mym-g').querySelectorAll('[data-mk]')];
      const on = its.filter(x => x.checked).length;
      ga.checked = on === its.length && its.length > 0;
      ga.indeterminate = on > 0 && on < its.length;   // «κάτι από αυτό το κύκλωμα»
    });
  };
  box.querySelectorAll('[data-gall]').forEach(ga => ga.onchange = () => {
    ga.closest('.mym-g').querySelectorAll('[data-mk]').forEach(x => { x.checked = ga.checked; });
    paint();
  });
  chks().forEach(c => c.onchange = paint);
  paint();

  const close = () => ovl.remove();
  $('#mymNo', box).onclick = close;
  ovl.onclick = e => { if (e.target === ovl) { close(); } };
  $('#mymOk', box).onclick = async () => {
    const keys = chks().filter(x => x.checked).map(x => x.dataset.mk);
    const start = $('#mymStart', box).checked;
    /* Καμία επιλογή + «ξεκίνα με τα δικά μου» θα έδινε άδειο πλαϊνό μενού.
       Κρατάμε τον άνθρωπο στο Κεντρικό μέχρι να διαλέξει κάτι. */
    const mode = (start && keys.length) ? 'mine' : 'all';
    S.boot.me.menuMine = keys.join(',');
    S.boot.me.menuMode = keys.length ? (start ? 'mine' : (S.menuMode === 'mine' ? 'mine' : 'all')) : 'all';
    close();
    renderShell(); go(S.view);
    await api('profile_pref', {key: 'menu_mine', value: keys.join(',')}).catch(() => null);
    await api('profile_pref', {key: 'menu_mode', value: mode}).catch(() => null);
    toast(keys.length ? `Το μενού σου: ${keys.length} οθόνες` : 'Καθαρίστηκε — βλέπεις ξανά τα πάντα');
  };
}

function renderShell() {
  const me = S.boot.me;
  /* Το δικαίωμα είναι πια **δυνατότητα** μέσα σε ενότητα: `projects.board`.
     Όποιος κρατά ολόκληρη την ενότητα (`projects`) τις έχει όλες. */
  const caps = me.caps || [];
  /* Οι «ονομαστικές» δυνατότητες δεν κληρονομούνται από το κύκλωμα (βλ. server:
     cnp_explicit_caps) — αφορούν δεδομένα άλλων ανθρώπων. */
  const explicit = me.explicitCaps || [];
  const has = c => me.full || caps.includes(c)
    || (!explicit.includes(c) && (me.areas || []).includes(c.includes('.') ? c.split('.')[0] : c));
  /* ── Το μενού ΕΙΝΑΙ τα δικαιώματα ────────────────────────────────────
     Κάθε ενότητα απαντά σε μία ερώτηση· κάθε στοιχείο της κρατά τη δική του
     δυνατότητα. Έτσι μπορείς να δώσεις το Board χωρίς τα Modules, ή τις
     Αναστολές χωρίς την Κερδοφορία. Ενότητα που δεν έμεινε με κανένα ορατό
     στοιχείο εξαφανίζεται μόνη της.
     Οι δύο χωρίς δικαίωμα («Τα δικά μου», «Η ομάδα») είναι η δική σου δουλειά
     και η συνεννόηση: δεν κλειδώνονται, γιατί χωρίς αυτές δεν δουλεύει κανείς. */
  const groups = [
    ['Τα δικά μου', 'ό,τι αφορά εμένα σήμερα', [
      ['myday', I.sun, 'Η μέρα μου'],
      ['todos', I.checkSquare, 'Το πλάνο μου'],
      ['supervised', I.eye, 'Επιβλέπω'],   /* εργασίες που άνοιξα εγώ — ανοιχτές / ολοκληρωμένες */
      ['requests', I.sos || I.chat, 'Αιτήματα'],   /* «σε ζητούν» με ιστορικό: ποιος ρώτησε, τι απαντήθηκε */
      ...((S.boot.me.cardsPm || S.boot.me.cardsEsc) ? [['cards', I.clipboard, 'Κάρτες διαχείρισης']] : []),
      ['time', I.clock, 'Ο χρόνος μου'],   /* ΜΟΝΟ δικός μου — η ομάδα είναι στις Αναφορές */
      ['myleave', I.sun, 'Οι άδειές μου'],   /* προσωπική οθόνη — χωρίς cap, όπως «Ο χρόνος μου» */
      ['library', I.book, 'Η βιβλιοθήκη μου'],
      ['vault', I.key, 'Κωδικοί'],
      ['profile', I.contact || I.user, 'Το προφίλ μου'],
    ]],
    ['Πελάτες', 'ποιοι είναι, τι θέλουν, τι τους έχουμε προτείνει', [
      ['clientlist', I.users || I.list, 'Λίστα πελατών', 'clients.card'],
      ['client360', I.user, 'Πελάτης 360°', 'clients.card'],
      ['crm', I.target, 'CRM & leads', 'clients.crm'],
      ['offers', I.doc, 'Προσφορές', 'clients.offers'],
    ]],
    ['Υποστήριξη', 'τα αιτήματα που περιμένουν απάντηση', [
      ['inbox', I.ticket, 'Tickets', 'support.tickets'],
      ['calllog', I.phone, 'Καταγραφές κλήσεων', 'support.calllog'],
      ['complaints', I.alert, 'Παράπονα', 'support.complaints'],
      ['knowledge', I.book, 'Βάση γνώσης', 'support.kb'],
    ]],
    /* ΟΛΟ ΤΟ ΚΕΝΤΡΟ ΣΕ ΕΝΑ ΣΗΜΕΙΟ (20/09/2026).
       Οι οθόνες του τηλεφωνικού κέντρου ήταν σκορπισμένες σε ΤΡΕΙΣ ενότητες:
       ο κατάλογος και η δρομολόγηση στους «Πελάτες», οι κλήσεις και η κίνηση
       πελάτη στις «Αναφορές», η διασύνδεση στο «Σύστημα». Κανείς δεν ήξερε
       πού να ψάξει τι. Εξαίρεση μένει μόνο ο ΟΡΙΣΜΟΣ των πεδίων του καταλόγου,
       που είναι ρύθμιση και ζει στο «Σύστημα». */
    ['Τηλεφωνικό κέντρο', 'ποιος μας πήρε, ποιος απάντησε, πού πάει η επόμενη κλήση', [
      ['calls', I.phone, 'Τηλεφωνική δραστηριότητα', 'comms.calls'],
      ['book', I.contact || I.users, 'Τηλεφωνικός κατάλογος', 'comms.book'],
      ['clientcalls', I.building || I.user, 'Κίνηση πελάτη', 'comms.calls'],
      ['route', I.zap, 'Δρομολόγηση κλήσεων', 'comms.route'],
    ]],
    ['Έργα & υλοποιήσεις', 'τι παραδίδουμε, σε ποιον, με ποια βήματα', [
      ['projects', I.folder, 'Έργα', 'projects.portfolio'],   /* πελατών ΚΑΙ εσωτερικά (R&D) */
      ['board', I.board, 'Board', 'projects.board'],
      ['list', I.list, 'Λίστα tasks', 'projects.board'],   /* ανοίγει στα δικά σου — δεν υπόσχεται «όλα» */
      ['gantt', I.gantt, 'Χρονοδιάγραμμα', 'projects.board'],
      ['scheduler', I.cal, 'Πρόγραμμα ομάδας', 'projects.board'],
      ['templates', I.box, 'Modules', 'projects.modules'],
      ['units', I.tree, 'Departments', 'projects.depts'],
    ]],
    /* «Η ομάδα» είναι πλέον κανονικό κύκλωμα δικαιωμάτων (team.*) — δίνεται από
       τις ομάδες όπως όλα τα άλλα, δεν είναι «ανοιχτό σε όλους». */
    ['Η ομάδα', 'συνεννόηση και διαθεσιμότητα', [
      ['chat', I.chat || I.ticket, 'Chat', 'team.chat'],
      ['calendar', I.cal, 'Ημερολόγιο', 'team.calendar'],
      ['standup', I.clipboard, 'Standup', 'team.standup'],
      ['remotebook', I.monitor, 'Απομακρυσμένες', 'team.remote'],
    ]],
    ['Προαγορά χρόνου', 'πόσο χρόνο έχουν αγοράσει, πόσο έχει μείνει, τι δεν καλύφθηκε', [
      ['prepaid', I.clock, 'Υπόλοιπα πελατών', 'prepaid.view'],
      ['uncovered', I.alert, 'Ακάλυπτος χρόνος', 'prepaid.view'],
    ]],
    ['Αναφορές & απόδοση', 'τι προχωράει, τι κολλάει, ποιος παραδίδει', [
      ...((me.leads || []).length || me.full ? [['myteam', I.crown || I.users, 'Η ομάδα μου']] : []),
      ['activity', I.zap, 'Δραστηριότητα', 'reports.activity'],
      ['teamday', I.sun, 'Η μέρα της ομάδας', 'reports.activity'],
      ['reschedules', I.cal, 'Αναπρογραμματισμοί', 'reports.activity'],
      ['triage', I.flag, 'Πλάνο ημέρας', 'reports.triage'],
      ['kpi', I.chart, 'KPI Dashboard', 'reports.kpi'],
      ['rootcause', I.chart, 'Ανάλυση ριζών', 'reports.rootcause'],
      ['perf', I.chart, 'Απόδοση χειριστών', 'reports.perf'],
      ['pool', I.users || I.tree, 'Οι χειριστές σήμερα', 'reports.pool'],
      ['timeteam', I.clock, 'Χρόνος ομάδας', 'reports.time'],
    ]],
    ['Οικονομικά', 'τι μπαίνει, τι βγαίνει, τι δεν πληρώθηκε', [
      ['profit', I.coin, 'Κερδοφορία', 'finance.profit'],
      ['paytrace', I.search || I.coin, 'Συμφωνία πληρωμών', 'finance.paytrace'],
      ['balances', I.receipt, 'Ανοιχτά υπόλοιπα', 'finance.balances'],
      ['suspend', I.alert, 'Αναστολές', 'finance.suspend'],
    ]],
    /* Η ενότητα λεγόταν «Προσλήψεις» και στέγαζε μόνο βιογραφικά. Με τις άδειες
       καλύπτει πλέον ολόκληρο τον κύκλο του εργαζομένου, και το όνομα της
       ενότητας πρέπει να ταυτίζεται με την περιοχή δικαιωμάτων (`hr`). */
    ['Προσωπικό', 'άδειες, υπόλοιπα και υποψήφιοι', [
      ['leave', I.sun, 'Άδειες', 'hr.leave'],
      ['roles', I.tree || I.users, 'Ρόλοι & ειδικότητες', 'hr.roles'],
      ['recruit', I.contact || I.users, 'Βιογραφικά', 'hr.cv'],
    ]],
    ['Σύστημα', 'ποιος μπαίνει, τι βλέπει, πώς δουλεύει', [
      ['teams', I.tree, 'Ομάδες & δικαιώματα', 'admin.teams'],
      /* ΟΙ ΡΥΘΜΙΣΕΙΣ ΕΝΟΣ ΚΥΚΛΩΜΑΤΟΣ ΖΟΥΝ ΕΔΩ, ΟΧΙ ΣΤΟ ΚΥΚΛΩΜΑ.
         Η «Διασύνδεση 3CX» δείχνει URL, client id και κρυπτογραφημένο secret του
         τηλεφωνικού κέντρου — δεν είναι οθόνη καθημερινής δουλειάς και δεν έχει
         λόγο να βρίσκεται σε κοινή θέα δίπλα στις κλήσεις. Ίδια λογική με τα
         «Πεδία καταλόγου»: ο ΟΡΙΣΜΟΣ είναι ρύθμιση, η ΧΡΗΣΗ είναι το κύκλωμα.
         Τα δικαιώματα μένουν στο κύκλωμα που αφορούν (comms.*), ώστε όποιος
         δίνει το τηλεφωνικό κέντρο να βλέπει μαζεμένο τι δίνει. */
      ['pbx', I.link || I.gear, 'Διασύνδεση 3CX', 'comms.pbx'],
      ['bookfields', I.tree, 'Πεδία καταλόγου', 'comms.book.edit'],
      ['settings', I.gear, 'Ρυθμίσεις', 'admin.settings'],
    ]],
  ].map(([title, hint, items]) => [title, hint, items.filter(it => !it[3] || has(it[3]))]);
  // Ενότητα χωρίς στοιχεία δεν εμφανίζεται καθόλου.
  const navAll = groups.filter(g => g[2].length).map(g => [g[0], g[2], g[1]]);
  navAll.push(['Βοήθεια', [['help', I.bulb, 'Οδηγός χρήσης']], 'πώς δουλεύει το εργαλείο']);

  /* ══ ΤΟ ΔΙΚΟ ΣΟΥ ΜΕΝΟΥ ═══════════════════════════════════════════════════
     Τα κυκλώματα είναι πολλά γιατί η δουλειά είναι πολλή — αλλά κανείς δεν τα
     χρησιμοποιεί όλα. Εδώ ο καθένας κρατά στο πλάι μόνο όσα πιάνει στα χέρια
     του, και μπορεί να ξεκινά με αυτά μόλις συνδεθεί.

     ΚΡΥΒΕΙ, ΔΕΝ ΑΠΑΓΟΡΕΥΕΙ. Το Ctrl+K και ο διακόπτης «Όλα» φτάνουν πάντα
     παντού — τα δικαιώματα είναι αλλού, και δεν τα αγγίζει αυτό εδώ. */
  const mineSet = new Set(String(me.menuMine || '').split(',').map(x => x.trim()).filter(Boolean));
  /* Κενή επιλογή = δεν έχει διαλέξει ακόμη· τότε «τα δικά μου» δεν κρύβει τίποτα,
     αλλιώς θα έβλεπε άδειο πλαϊνό και θα νόμιζε ότι χάλασε. */
  S.menuMode = (me.menuMode === 'mine' && mineSet.size) ? 'mine' : 'all';
  S.menuMine = mineSet;
  S.navAll = navAll;
  const nav = S.menuMode === 'mine'
    ? navAll.map(([g, items, hint]) => [g, items.filter(([k]) => mineSet.has(k)), hint]).filter(g => g[1].length)
    : navAll;
  /* Το ίδιο μενού, σε επίπεδη μορφή, για το Ctrl+K: ό,τι βλέπεις στο πλάι μπορείς να το
     φτάσεις και γράφοντας. Φιλτραρισμένο ήδη από τα δικαιώματα — μία πηγή, όχι δύο. */
  /* Η παλέτα (Ctrl+K) βλέπει ΟΛΟ το μενού, όχι το προσωπικό: το προσωπικό
     μενού είναι συντόμευση, όχι τοίχος. */
  S.nav = [];
  navAll.forEach(([g, items]) => items.forEach(([k, ic, lb]) => S.nav.push({k, icon: ic, label: lb, group: g})));
  $('#app').innerHTML = `
  <div class="shell${(localStorage.cnpSideCollapsed === '1' && !matchMedia('(max-width:768px)').matches) ? ' collapsed' : ''}">
    <aside class="side">
      <div class="brand"><div class="brand-ico">P</div>
        <div class="brand-t">Cloudon<b>Projects</b><small>Project Manager</small></div></div>
      ${/* Ο διακόπτης του προσωπικού μενού. Το μολύβι ανοίγει τον επιλογέα. */''}
      <div class="mymenu" role="group" aria-label="Μορφή μενού">
        <button class="mym-t${S.menuMode === 'all' ? ' on' : ''}" data-menumode="all" title="Όλα τα κυκλώματα">Κεντρικό</button>
        <button class="mym-t${S.menuMode === 'mine' ? ' on' : ''}" data-menumode="mine" title="Μόνο όσα διάλεξες">Δικά μου</button>
        <button class="mym-e" id="myMenuEdit" title="Διάλεξε τι θα βλέπεις εδώ" aria-label="Επεξεργασία του μενού μου">${I.edit}</button>
      </div>
      <nav class="snav">
      ${(() => {
        let openSet = null;
        if (localStorage.cnpNavOpen) { try { openSet = new Set(JSON.parse(localStorage.cnpNavOpen)); } catch (e) {} }
        return nav.map(([g, items, hint]) => {
          const hasActive = items.some(([k]) => k === S.view);
          const open = openSet ? openSet.has(g) : hasActive;   // default: ανοιχτή η ενότητα του τρέχοντος view
          return `<div class="snav-grp ${open ? 'open' : ''}">
            <button class="sgroup" data-grptoggle="${esc(g)}"${hint ? ` title="${esc(hint)}"` : ''}><span class="sgroup-t">${esc(g)}</span>
              <svg class="chev" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M6 9l6 6 6-6"/></svg></button>
            <div class="snav-items">${items.map(([k, ic, lb]) => `<button class="sitem" data-nav="${k}" data-lb="${esc(lb)}" data-grp="${esc(g)}">${ic}<span>${lb}</span></button>`).join('')}</div>
          </div>`;
        }).join('');
      })()}
      </nav>
      <div class="side-foot">
        <span class="ava" data-profile style="cursor:pointer" title="Το προφίλ μου">${esc(me.ini)}</span>
        <div data-profile style="cursor:pointer" title="Το προφίλ μου"><div class="side-foot-name">${esc(me.name)}</div>
        <div class="side-foot-role">${me.full ? 'Διαχειριστής' : 'Agent'}</div></div>
        <button class="theme-btn" id="themeBtn" title="Θέμα">${S.theme === 'dark' ? '☀️' : '🌙'}</button>
        <button class="theme-btn" id="logoutBtn" title="Αποσύνδεση"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></button>
      </div>
    </aside>
    <div class="main">
      <div class="top">
        <button class="hamb" id="hambBtn" aria-label="Μενού" title="Μενού"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 6h18M3 12h18M3 18h18"/></svg></button>
        <div class="top-l"><h1 id="topTitle"></h1><small id="topSub"></small></div>
        <button class="top-search" id="palBtn" title="Αναζήτηση (Ctrl+K)">${I.search}<span>Αναζήτηση…</span><kbd>Ctrl K</kbd></button>
        <div class="top-pulse" id="topPulse"></div>
        <div class="top-acts">
          <button id="remoteChip" style="display:none;border:0;border-radius:99px;background:var(--bad);color:#fff;font-weight:800;padding:7px 14px;cursor:pointer;font-size:12.5px" title="Κλικ για τερματισμό & χρέωση"></button>
          <button class="status-btn" id="statusBtn" title="Κατάσταση διαθεσιμότητας"><span class="dot" id="statusDot"></span><span id="statusLbl">Online</span></button>
          <button class="btn btn-p btn-sm" id="newBtn" title="Δημιουργία">${I.plus} Νέο</button>
          <div class="bell-wrap"><button class="btn btn-o btn-ico" id="attnBtn" style="position:relative"
            title="Τι να προσέξεις — ανανεώνεται κάθε ώρα">${I.eye}
            <span class="bell-n" id="attnN" style="display:none"></span></button></div>
          <button class="btn btn-o btn-ico" id="helpBtn" title="Βοήθεια για αυτή την οθόνη">${I.bulb}</button>
          <div class="bell-wrap"><button class="btn btn-o btn-ico" id="chatBtn" style="position:relative" title="Chat ομάδας">${I.chat}
            <span class="bell-n" id="chatN" style="display:none"></span></button></div>
          <div class="bell-wrap"><button class="btn btn-o btn-ico" id="bellBtn" style="position:relative">${I.bell}
            <span class="bell-n" id="bellN" style="display:none"></span></button></div>
          <button class="ava top-ava" id="topAva" title="Ο λογαριασμός μου">${esc(me.ini)}</button>
          <button class="btn btn-o btn-ico" id="sideTgl" title="Μεγέθυνση/σμίκρυνση μενού"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 6h18M3 12h18M3 18h18"/></svg></button>
        </div>
      </div>
      <div class="content" id="content"></div>
    </div>
    <div class="side-scrim" id="sideScrim"></div>
    ${(() => {
      // ── bottom tab bar (μόνο κινητό): ΟΛΟ το μενού σε μία μπάρα που σέρνεται ──
      const flat = nav.flatMap(([, items]) => items);
      const SHORT = {myday: 'Σήμερα', inbox: 'Tickets', calendar: 'Ημερολόγιο', todos: 'Πλάνο',
        library: 'Βιβλιοθήκη', vault: 'Κωδικοί', remotebook: 'Απομακρ.', client360: 'Πελάτης', clientlist: 'Πελάτες',
        knowledge: 'Γνώση', list: 'Tasks', projects: 'Έργα', offers: 'Προσφορές',
        profile: 'Προφίλ', gantt: 'Χρονοδ.', time: 'Χρόνος', crm: 'CRM',
        triage: 'Πλάνο ημ.', rootcause: 'Ρίζες', kpi: 'KPI', profit: 'Κέρδη',
        units: 'Depts', templates: 'Modules', teams: 'Ομάδες', perf: 'Απόδοση', suspend: 'Αναστολές',
        settings: 'Ρυθμίσεις', pbx: 'Διασύνδεση 3CX', book: 'Τηλεφωνικός κατάλογος', bookfields: 'Πεδία καταλόγου', calls: 'Τηλεφωνική δραστηριότητα', clientcalls: 'Κίνηση πελάτη', recruit: 'Βιογραφικά', paytrace: 'Πληρωμές', standup: 'Standup',
        chat: 'Chat', board: 'Board', prepaid: 'Προαγορά', activity: 'Δραστηρ.', complaints: 'Παράπονα', uncovered: 'Ακάλυπτα', help: 'Οδηγός'};
      const FIRST = ['myday', 'inbox', 'chat', 'calendar', 'board', 'todos'];
      const ordered = FIRST.map(k => flat.find(x => x[0] === k)).filter(Boolean)
        .concat(flat.filter(x => !FIRST.includes(x[0])));
      return `<nav class="tabbar" id="tabBar" aria-label="Κύρια πλοήγηση">
        ${ordered.map(([k, ic, lb]) => `<button class="tab" data-tab="${k}">${ic}<span>${esc(SHORT[k] || lb)}</span></button>`).join('')}
      </nav>`;
    })()}
  </div>`;
  $$('.sitem').forEach(b => b.onclick = () => { $('.shell').classList.remove('nav-open'); go(b.dataset.nav); });
  // ── mobile off-canvas menu ──
  {
    const _sh = $('.shell');
    const _hb = $('#hambBtn'); if (_hb) _hb.onclick = () => _sh.classList.toggle('nav-open');
    const _sc = $('#sideScrim'); if (_sc) _sc.onclick = () => _sh.classList.remove('nav-open');
    // bottom tabs (όλο το μενού — σέρνεται οριζόντια)
    $$('#tabBar [data-tab]').forEach(b => b.onclick = () => { _sh.classList.remove('nav-open'); go(b.dataset.tab); });
    // avatar header → λογαριασμός (προφίλ / θέμα / αποσύνδεση)
    const _av = $('#topAva');
    if (_av) {
      _av.onclick = e => {
        e.stopPropagation();
        miniMenu(_av, [
          {icon: I.user || I.contact, label: 'Το προφίλ μου', on: () => { const p = $('[data-profile]'); if (p) { p.click(); } }},
          {icon: I.edit, label: 'Το μενού μου', on: () => cnpMyMenu()},
          {icon: I.bulb, label: S.theme === 'dark' ? 'Φωτεινό θέμα' : 'Σκοτεινό θέμα', on: () => $('#themeBtn').click()},
          {icon: I.lock, label: 'Αποσύνδεση', on: () => $('#logoutBtn').click()},
        ]);
      };
    }
    if (!window._cnpNavEsc) {
      window._cnpNavEsc = 1;
      document.addEventListener('keydown', e => { if (e.key === 'Escape') { const s = document.querySelector('.shell'); if (s) s.classList.remove('nav-open'); } });
    }
  }
  /* ── Ο διακόπτης και ο επιλογέας του προσωπικού μενού ────────────────── */
  $$('[data-menumode]').forEach(b => b.onclick = async () => {
    const mode = b.dataset.menumode;
    if (mode === S.menuMode) { return; }
    if (mode === 'mine' && !S.menuMine.size) { cnpMyMenu(); return; }   // δεν έχει διαλέξει ακόμη
    S.boot.me.menuMode = mode;
    renderShell(); go(S.view);
    await api('profile_pref', {key: 'menu_mode', value: mode}).catch(() => null);
  });
  { const me2 = $('#myMenuEdit'); if (me2) { me2.onclick = () => cnpMyMenu(); } }
  $$('[data-grptoggle]').forEach(b => b.onclick = () => {
    b.closest('.snav-grp').classList.toggle('open');
    localStorage.cnpNavOpen = JSON.stringify($$('.snav-grp.open').map(x => x.querySelector('.sgroup').dataset.grptoggle));
  });
  $('#sideTgl').onclick = () => {
    const sh = $('.shell'); const col = sh.classList.toggle('collapsed');
    localStorage.cnpSideCollapsed = col ? '1' : '0';
    sideTipHide();
    if (col) { scrollActiveIntoView(); }
  };
  sideTips();
  $('#themeBtn').onclick = () => {
    S.theme = S.theme === 'dark' ? 'light' : 'dark';
    localStorage.cnpTheme = S.theme; document.documentElement.dataset.theme = S.theme;
    $('#themeBtn').textContent = S.theme === 'dark' ? '☀️' : '🌙';
  };  $('#logoutBtn').onclick = async () => {
    if (!await cnpConfirm('Αποσύνδεση από την εφαρμογή;', {ok: 'Αποσύνδεση'})) { return; }
    location.href = '?logout=1';
  };
  $('#bellBtn').onclick = toggleBell;
  const hb = $('#helpBtn'); if (hb) hb.onclick = () => window.CNP.openHelp && window.CNP.openHelp(S.view);
  { const ab = $('#attnBtn'); if (ab) { ab.onclick = e => { e.stopPropagation(); toggleAttn(); }; } }
  { const cb = $('#chatBtn'); if (cb) { cb.onclick = () => { stopChatTitle(); go('chat'); }; } }
  const pb = $('#palBtn'); if (pb) pb.onclick = () => window.CNP.palette && window.CNP.palette();
  // ── Πάνω μενού: «+ Νέο» quick-create ──
  $('#newBtn').onclick = e => {
    e.stopPropagation();
    miniMenu($('#newBtn'), [
      {icon: I.checkSquare, label: 'Νέο task', on: () => window.CNP.quickNew && window.CNP.quickNew()},
      {icon: I.target, label: 'Νέο lead', on: async () => { const d = await api('crm').catch(() => null); openLead(null, d || {stages: [], leads: []}); }},
      {icon: I.users, label: 'Νέα σύσκεψη', on: () => newMeeting()},
      {icon: I.phone, label: 'Καταγραφή κλήσης', on: () => window.CNP.quickCall && window.CNP.quickCall()},
      {icon: I.alert, label: 'Παράπονο πελάτη', on: () => window.CNP.quickCx && window.CNP.quickCx()},
      {icon: I.clock, label: 'Καταγραφή χρόνου', on: () => go('time')},
      {icon: I.sos, label: 'Ζήτα βοήθεια', on: () => window.CNP.quickHelp && window.CNP.quickHelp()},
    ]);
  };
  // ── Κατάσταση διαθεσιμότητας ──
  $('#statusBtn').onclick = e => { e.stopPropagation(); statusPicker(); };
  loadTopStats();
  if (!window._cnpTopTimer) { window._cnpTopTimer = setInterval(loadTopStats, 60000); }
  /* Η εποπτεία ανανεώνεται ΚΑΘΕ ΩΡΑ — δεν είναι σφυγμός. */
  loadAttention();
  if (!window._cnpAttnTimer) { window._cnpAttnTimer = setInterval(loadAttention, 3600000); }
  updateBell(S.boot.unread);
}
/* ═══ ΚΑΤΑΣΤΑΣΗ ΔΙΑΘΕΣΙΜΟΤΗΤΑΣ ════════════════════════════════════════════════
   Η κατάσταση δηλώνεται με το χέρι και ισχύει όπως δηλώθηκε — δεν «βγαίνει» μόνη
   της. Ο αυτόματος παλμός μπαίνει μόνο όταν δεν έχει δηλωθεί τίποτα. Γι' αυτό
   κάθε δήλωση έχει και διάρκεια: λήγει μόνη της, ώστε να μη μείνει ξεχασμένη. */
/* ΙΔΙΕΣ ΜΕ ΤΟ ΤΗΛΕΦΩΝΟ. Οι πέντε επιλέξιμες καταστάσεις είναι ακριβώς αυτές που
   δείχνει ο client του 3CX, ώστε να μη λέμε «Απασχολημένος» εδώ και «Do Not
   Disturb» εκεί και να μην ξέρει κανείς αν εννοούμε το ίδιο. Το αγγλικό όνομα
   μπαίνει δίπλα, γιατί έτσι το βλέπει ο καθένας στο τηλέφωνό του.
   Το «Σε σύσκεψη» και το «Εκτός» ΔΕΝ επιλέγονται: τα βάζει το ημερολόγιο και ο
   παλμός αντίστοιχα — μένουν εδώ μόνο για να ζωγραφίζονται σωστά. */
const CNP_ST = [
  ['online', 'Διαθέσιμος', '#36B213', 'Δουλεύω κανονικά — γράψτε μου', 'Available'],
  ['away', 'Λείπω', '#FF9A0C', 'Γυρίζω σε λίγο', 'Away'],
  ['dnd', 'Μην ενοχλείτε', '#E05B4A', 'Μη με διακόπτετε — σιγάζουν οι ειδοποιήσεις', 'Do Not Disturb'],
  ['lunch', 'Διάλειμμα', '#2FBBB3', 'Φαγητό ή διάλειμμα — σιγάζουν οι ειδοποιήσεις', 'Lunch'],
  ['trip', 'Εκτός έδρας', '#1D8FD2', 'Σε πελάτη ή μετακίνηση', 'Business Trip'],
  ['meeting', 'Σε σύσκεψη', '#E05B4A', 'Σε σύσκεψη — το τηλέφωνο πάει «Out of office»', 'Out of office'],
  ['offline', 'Εκτός', '#5d6b85', 'Δεν είσαι στην εφαρμογή', '', 1],
];
const CNP_ST_REASONS = {
  online: ['Στο γραφείο', 'Τηλεργασία'],
  away: ['Σύντομο διάλειμμα', 'Σε μετακίνηση'],
  dnd: ['Deep work', 'Σε άλλον πελάτη', 'Επείγον περιστατικό'],
  lunch: ['Φαγητό', 'Διάλειμμα'],
  trip: ['Σε πελάτη', 'Ταξίδι εργασίας', 'Εκτός γραφείου'],
  meeting: ['Σύσκεψη ομάδας', 'Με πελάτη', 'Παρουσίαση', 'Συνέντευξη'],
};
const CNP_ST_DUR = [['0', 'μέχρι να το αλλάξω'], ['30', '30 λεπτά'], ['60', '1 ώρα'],
  ['120', '2 ώρες'], ['240', '4 ώρες'], ['eod', 'μέχρι το τέλος της ημέρας']];
/* Η ΣΥΣΚΕΨΗ ΘΕΛΕΙ ΠΑΝΤΑ ΛΗΞΗ. Κατεβάζει το τηλέφωνο σε «Out of office» — μια
   σύσκεψη χωρίς τέλος σημαίνει χειριστής άφαντος για ώρες. Ο server το επιβάλλει·
   εδώ απλώς δεν προσφέρουμε την επιλογή που θα απορριπτόταν. */
const cnpStDur = k => (k === 'meeting' ? CNP_ST_DUR.filter(d => d[0] !== '0') : CNP_ST_DUR);
const cnpStDef = k => CNP_ST.find(x => x[0] === k) || CNP_ST[0];

/** Ζωγραφίζει την κουκκίδα/ετικέτα στην πάνω μπάρα από ένα αντικείμενο presence. */
/** «+ Νέο → Νέα σύσκεψη»: ανοίγει τη φόρμα με λογική προεπιλογή ώρας. */
function newMeeting() {
  if (!cnpCan('team.calendar.edit')) { toast('Δεν έχεις δικαίωμα δημιουργίας — χρειάζεται «Ημερολόγιο → Επεξεργασία»', true); return; }
  const n = new Date();
  n.setMinutes(n.getMinutes() < 30 ? 30 : 60, 0, 0);     // επόμενο μισάωρο
  const p2 = x => String(x).padStart(2, '0');
  const fmt = d2 => d2.getFullYear() + '-' + p2(d2.getMonth() + 1) + '-' + p2(d2.getDate())
    + 'T' + p2(d2.getHours()) + ':' + p2(d2.getMinutes());
  const e2 = new Date(n.getTime() + 3600000);
  window.CNP.openEvent({kind: 'meeting', start: fmt(n), end: fmt(e2), attendees: [S.boot.me.id]});
}

function setStatusUI(p) {
  const dot = $('#statusDot'), l = $('#statusLbl'), b = $('#statusBtn');
  if (!dot || !p) { return; }
  dot.style.background = p.color || cnpStDef(p.status)[2];
  l.innerHTML = esc(p.label || cnpStDef(p.status)[1])
    + (p.manual ? '' : ' <span class="st-tag">auto</span>');
  if (b) {
    b.title = (p.manual ? 'Το δήλωσες εσύ — αυτόματο: OFF' : 'Αυτόματο: ON — από τον παλμό της εφαρμογής')
      + (p.reason ? ' · ' + p.reason : '') + (p.untilTxt ? ' · ' + p.untilTxt : '');
    b.classList.toggle('is-manual', !!p.manual);
  }
  window._cnpPresence = p;
}

/** Ο διάλογος επιλογής: κατάσταση + λόγος + διάρκεια. */
function statusPicker() {
  const cur = window._cnpPresence || {status: 'online', manual: false, reason: '', until: 0};
  let pick = cur.manual ? cur.status : 'online';
  const ovl = document.createElement('div');
  ovl.className = 'ovl show'; ovl.style.zIndex = 330;
  ovl.innerHTML = `<div class="pal-box st-box" style="margin:10vh auto 0;max-width:430px" role="dialog" onclick="event.stopPropagation()">
    <div class="st-head"><b>Η κατάστασή μου</b>
      <div class="mut">Την επιλέγεις εσύ — η ομάδα βλέπει ακριβώς ό,τι δηλώσεις.</div></div>
    <button class="st-auto" id="stAuto" role="switch">
      <span class="sw"><span class="sw-k"></span></span>
      <span class="st-t"><b>Αυτόματη κατάσταση <span class="sw-v" id="stAutoV"></span></b>
        <span class="mut" id="stAutoH"></span></span></button>
    ${/* Μόνο οι επιλέξιμες. Το «εκτός» το βάζει ο παλμός, δεν δηλώνεται.
         Τη σύσκεψη τη βάζει και το ημερολόγιο, αλλά δηλώνεται και με το χέρι
         — με υποχρεωτική λήξη, ώστε το τηλέφωνο να ξανανοίξει μόνο του. */''}
    <div class="st-list" id="stList">${CNP_ST.filter(x => !x[5]).map(([k, lbl, col, hint, pbx]) => `
      <button class="st-opt${k === pick ? ' on' : ''}" data-st="${k}">
        <span class="dot" style="background:${col}"></span>
        <span class="st-t"><b>${esc(lbl)}</b>${pbx ? `<span class="st-pbx" title="Έτσι το βλέπεις στο τηλέφωνό σου">${esc(pbx)}</span>` : ''}<span class="mut">${esc(hint)}</span></span>
        <span class="st-chk">✓</span></button>`).join('')}</div>
    <div class="st-form">
      <label>Λόγος <span class="mut">(προαιρετικό — φαίνεται δίπλα στο όνομά σου)</span></label>
      <div class="st-chips" id="stChips"></div>
      <input class="inp" id="stReason" maxlength="80" placeholder="…ή γράψε δικό σου" value="${esc(cur.manual ? (cur.reason || '') : '')}">
      <div id="stDurBox">
        <label style="margin-top:11px">Για πόσο</label>
        <select class="inp" id="stDur">${cnpStDur(pick).map(([v, t]) => `<option value="${v}">${t}</option>`).join('')}</select>
      </div>
    </div>
    <div class="st-foot">
      <span style="flex:1"></span>
      <button class="btn btn-o" id="stCancel">Άκυρο</button>
      <button class="btn btn-p" id="stOk">Εφαρμογή</button></div></div>`;
  document.body.appendChild(ovl);
  /* Ο λόγος και η διάρκεια ανήκουν στην κατάσταση που διάλεξες: αλλάζεις
     κατάσταση → καθαρίζουν. Αν γυρίσεις πίσω μέσα στον ίδιο διάλογο, βρίσκεις
     ό,τι είχες γράψει — δεν ξαναπληκτρολογείς. */
  const mem = {};
  if (cur.manual) { mem[cur.status] = {r: cur.reason || '', d: '0'}; }
  const stash = () => { mem[pick] = {r: $('#stReason', ovl).value, d: $('#stDur', ovl).value}; };
  const chips = () => {
    $('#stChips', ovl).innerHTML = (CNP_ST_REASONS[pick] || []).map(r =>
      `<button class="btn btn-o btn-sm stR" data-r="${esc(r)}">${esc(r)}</button>`).join('');
    $$('.stR', ovl).forEach(b => b.onclick = () => { $('#stReason', ovl).value = b.dataset.r; });
    /* Και η διάρκεια: η σύσκεψη δεν δέχεται «μέχρι να το αλλάξω». */
    const ds = $('#stDur', ovl);
    if (ds) {
      const keep = ds.value;
      ds.innerHTML = cnpStDur(pick).map(([v, t]) => `<option value="${v}">${esc(t)}</option>`).join('');
      ds.value = [...ds.options].some(o => o.value === keep) ? keep : ds.options[0].value;
    }
  };
  let auto = !cur.manual;                 // ON = αποφασίζει ο παλμός · OFF = το δηλώνω εγώ
  const paintAuto = () => {
    const btn = $('#stAuto', ovl);
    btn.classList.toggle('on', auto);
    btn.setAttribute('aria-checked', auto ? 'true' : 'false');
    $('#stAutoV', ovl).textContent = auto ? 'ON' : 'OFF';
    $('#stAutoH', ovl).textContent = auto
      ? 'Αποφασίζει η εφαρμογή από τον παλμό σου — τώρα: ' + (cur.label || 'Διαθέσιμος')
      : 'Ισχύει ό,τι διαλέξεις παρακάτω';
    $('#stList', ovl).classList.toggle('off', auto);
    $('.st-form', ovl).classList.toggle('off', auto);
  };
  const sel = k => {
    if (auto) { auto = false; paintAuto(); }   // διαλέγεις → το αυτόματο κλείνει
    if (k === pick) { return; }
    stash();
    pick = k;
    $$('.st-opt', ovl).forEach(o => o.classList.toggle('on', o.dataset.st === k));
    const m = mem[k] || {r: '', d: '0'};
    $('#stReason', ovl).value = m.r;
    $('#stDur', ovl).value = m.d;
    /* «Διαθέσιμος για 30 λεπτά» δεν σημαίνει τίποτα — η διάρκεια αφορά απουσία. */
    $('#stDurBox', ovl).hidden = (k === 'online');
    chips();
  };
  $$('.st-opt', ovl).forEach(o => o.onclick = () => sel(o.dataset.st));
  chips();
  $('#stDurBox', ovl).hidden = (pick === 'online');
  $('#stAuto', ovl).onclick = () => { auto = !auto; paintAuto(); };
  /* Κάθε επέμβαση στον λόγο ή στη διάρκεια σημαίνει «το αναλαμβάνω εγώ». */
  const offAuto = () => { if (auto) { auto = false; paintAuto(); } };
  $('#stReason', ovl).onfocus = offAuto;
  $('#stDur', ovl).onchange = offAuto;
  paintAuto();
  const close = () => ovl.remove();
  ovl.onclick = e => { if (e.target === ovl) { close(); } };
  $('#stCancel', ovl).onclick = close;
  const apply = async body => {
    const r = await api('chat_status', body).catch(e => ({err: e.message}));
    if (r.err) { toast(r.err, true); return; }
    close();
    setStatusUI(r.presence);
    toast(r.presence.manual
      ? '● ' + r.presence.label + (r.presence.reason ? ' · ' + r.presence.reason : '')
        + (r.presence.untilTxt ? ' (' + r.presence.untilTxt + ')' : '')
      : 'Αυτόματη κατάσταση');
    if (S.view === 'chat' && window.R && window.R.chat) { window.R.chat(); }
  };
  $('#stOk', ovl).onclick = () => apply(auto ? {status: 'auto'} : {status: pick,
    reason: $('#stReason', ovl).value.trim(),
    mins: pick === 'online' ? '0' : $('#stDur', ovl).value});
}
/* ═══════════ ΤΙ ΝΑ ΠΡΟΣΕΞΩ — επόπτης & coach στην πάνω μπάρα ═══════════
   ΓΙΑΤΙ ΕΔΩ ΚΑΙ ΟΧΙ ΣΤΗ «ΜΕΡΑ ΜΟΥ»: ο coach υπήρχε ήδη, αλλά έπρεπε να ανοίξεις
   τη «Μέρα μου» για να τον δεις. Όποιος δούλευε όλη μέρα μέσα στα tickets δεν
   τον έβλεπε ποτέ. Εδώ είναι μπροστά σου σε κάθε οθόνη.

   ΚΑΘΕ ΩΡΑ, ΟΧΙ ΚΑΘΕ ΛΕΠΤΟ: δεν είναι σφυγμός, είναι εποπτεία. Τίποτα από αυτά
   δεν αλλάζει μέσα σε ένα λεπτό, και μια ένδειξη που αναβοσβήνει συνέχεια
   παύει να προσέχεται. */
let cnpAttn = null;

async function loadAttention() {
  cnpAttn = await api('attention').catch(() => null);
  const b = $('#attnBtn'), n = $('#attnN');
  if (!b || !n) { return; }
  const cnt = cnpAttn ? cnpAttn.n : 0;
  n.textContent = cnt;
  n.style.display = cnt ? '' : 'none';
  n.style.background = cnpAttn && cnpAttn.lvl === 'bad' ? 'var(--bad)'
    : (cnpAttn && cnpAttn.lvl === 'warn' ? 'var(--warn,#b45309)' : 'var(--brand)');
  b.title = cnt ? `${cnt} ${cnt === 1 ? 'θέμα θέλει' : 'θέματα θέλουν'} την προσοχή σου`
    : 'Τίποτα δεν χρειάζεται την προσοχή σου';
}

function toggleAttn() {
  const old = $('.pop'); if (old) { old.remove(); return; }
  const wrap = $('#attnBtn') && $('#attnBtn').parentElement;
  if (!wrap) { return; }
  const d = cnpAttn;
  const pop = document.createElement('div'); pop.className = 'pop';
  const refRow = r => `<a class="attn-ref" data-rk="${r.kind}" data-ri="${r.id}">${esc(r.label)}</a>`;
  const body = (d && d.groups && d.groups.length)
    ? d.groups.map(g => `<div class="pop-sec">${esc(g.title)}</div>`
        /* Μια γραμμή που λέει «κάνε κάτι» πρέπει να ΠΑΕΙ κάπου. Με `go` όλη η
           γραμμή γίνεται σύνδεσμος προς την οθόνη που το λύνει. */
        + g.items.map(it => `<div class="attn-row ${it.lvl}${it.go ? ' link' : ''}"${
            it.go ? ` data-attgo="${esc(it.go)}"` : ''}>
            <span class="attn-ic">${it.icon}</span>
            <div class="attn-b"><div>${esc(it.text)}</div>
              ${(it.refs || []).length ? `<div class="attn-refs">${it.refs.map(refRow).join('')}</div>` : ''}
              ${/* ΜΙΑ ΚΙΝΗΣΗ, ΟΧΙ ΜΙΑ ΔΙΑΠΙΣΤΩΣΗ. Το «7 εκπρόθεσμες» δεν λέει γιατί
                   αργούν — και το να ανοίξεις επτά καρτέλες για να ρωτήσεις είναι
                   δουλειά που κανείς δεν κάνει. Ένα κουμπί, μία ερώτηση στον
                   άνθρωπο, με τη λίστα του μέσα. */''}
              ${it.ask ? `<div class="attn-act"><button class="btn btn-o btn-sm attn-ask"
                data-ask="${it.ask}" data-askn="${esc(it.askName || '')}"
                data-askids="${(it.refs || []).filter(r => r.kind === 'task').map(r => r.id).join(',')}"
                title="Στείλε του ερώτηση — γίνεται κανονικό αίτημα που περιμένει απάντηση">💬 Ζήτα ενημέρωση</button></div>` : ''}
            </div></div>`).join('')).join('')
    : '<div class="empty" style="padding:24px">Τίποτα δεν χρειάζεται την προσοχή σου αυτή τη στιγμή.</div>';
  pop.innerHTML = `<div class="pop-h">Τι να προσέξεις
    <a href="#" id="attnRef" style="font-size:11px;font-weight:600">ανανέωση</a></div>${body}
    ${d && d.at ? `<div class="mut" style="padding:7px 12px;font-size:11px">τελευταίος έλεγχος ${esc(String(d.at).slice(11, 16))} · ανανεώνεται κάθε ώρα</div>` : ''}`;
  wrap.appendChild(pop);
  pop.querySelector('#attnRef').onclick = async e => {
    e.preventDefault(); e.stopPropagation();
    pop.remove(); await loadAttention(); toggleAttn();
  };
  pop.querySelectorAll('.attn-ask').forEach(b => b.onclick = async e => {
    e.stopPropagation();
    const who = +b.dataset.ask, name = b.dataset.askn || 'τον συνάδελφο';
    const ids = (b.dataset.askids || '').split(',').filter(Boolean);
    const lst = ids.length ? ids.map(i => '#' + i).join(', ') : '';
    const def = 'Έχεις ' + (ids.length || '') + (ids.length === 1 ? ' εργασία' : ' εργασίες')
      + ' με περασμένη ημερομηνία' + (lst ? ' (' + lst + ')' : '')
      + '. Δες τες και ενημέρωσέ με τι παίζει με την καθεμιά: αν χρειάζεσαι κάτι, '
      + 'αν κολλάει κάπου, ή αν πρέπει να βάλουμε νέα ημερομηνία.';
    const msg = await cnpDialog({
      title: '💬 Ζήτα ενημέρωση από ' + name,
      body: 'Θα φτάσει ως αίτημα που μένει ανοιχτό μέχρι να απαντηθεί — δεν είναι ειδοποίηση που αγνοείται.',
      input: def, rows: 5, max: 500, ok: 'Στείλ᾽ το', cancel: 'Άκυρο'});
    if (msg === null || !String(msg).trim()) { return; }
    const r = await api('team_ask', {id: who, message: String(msg).trim()}).catch(er => ({err: er && er.message}));
    if (r && r.err) { toast(r.err, true); return; }
    toast('Στάλθηκε στον/στην ' + name + ' — περιμένει απάντηση');
    pop.remove();
  });
  pop.querySelectorAll('[data-attgo]').forEach(r => r.onclick = () => {
    const v = r.dataset.attgo;
    pop.remove();
    go(v);
  });
  pop.querySelectorAll('.attn-ref').forEach(a => a.onclick = e => {
    e.preventDefault(); e.stopPropagation();
    const k = a.dataset.rk, id = +a.dataset.ri;
    pop.remove();
    if (k === 'task') { openTask(id); }
    else if (k === 'project') { go('board', id); }
    else if (k === 'request') { go('requests'); }
  });
}

async function loadTopStats() {
  const box = $('#topPulse'); if (!box) return;
  const d = await api('topstats').catch(() => null); if (!d) return;
  if (d.presence) { setStatusUI(d.presence); }
  const chips = [
    {k: 'inbox', ic: I.ticket, n: d.tickets, lbl: 'tickets', col: '#0097e4'},
    {k: 'inbox', ic: I.alert, n: d.sla, lbl: 'SLA', col: '#e0552b', warn: 1},
    {k: 'myday', ic: I.checkSquare, n: d.today, lbl: 'σήμερα', col: '#e0a020'},
    {k: 'myday', ic: I.zap, n: d.ball, lbl: 'εμένα', col: '#7b5cd6'},
  ];
  /* 🆘 Σε ζητούν: εκκλήσεις βοήθειας, ερωτήσεις «τι γίνεται», κλήσεις στη φωνή και
     προσκλήσεις σε σύσκεψη που δεν απάντησες. Ανοίγει το καμπανάκι, όπου απαντιούνται. */
  chips.push({k: 'needs', ic: I.sos || I.bell, n: d.needs || 0, lbl: 'σε ζητούν', col: '#e2515f', warn: 1});
  /* Εκκρεμείς εγκρίσεις χρέωσης — το chip βγαίνει μόνο σε όποιον τις δίνει. */
  if (d.billPend) { chips.push({k: 'billq', ic: I.coin, n: d.billPend, lbl: 'εγκρίσεις', col: '#e0a020', warn: 1}); }
  box.innerHTML = chips.map(c => `<button class="pulse-chip${c.n && c.warn ? ' hot' : ''}${c.n ? '' : ' zero'}" data-pgo="${c.k}" title="${c.lbl}">
    <span class="pc-ic" style="color:${c.col}">${c.ic}</span><span class="n">${c.n}</span><span class="pc-l">${c.lbl}</span></button>`).join('');
  $$('#topPulse [data-pgo]').forEach(b => b.onclick = () => {
    if (b.dataset.pgo === 'billq') { billingQueue(); return; }
    if (b.dataset.pgo === 'needs') { const old = $('.pop'); if (old) { old.remove(); } go('requests'); return; }
    go(b.dataset.pgo);
  });
  /* Pop-up: μία φορά ανά συνεδρία, και ξανά μόλις εμφανιστεί ΚΑΙΝΟΥΡΓΙΑ έγκριση.
     Ο σκοπός είναι να μην περιμένει η εργασία — όχι να γίνει ενοχλητικό. */
  const seen = window._cnpBillSeen;
  if (d.billPend && (seen === undefined || d.billPend > seen)) { billingQueue(); }
  window._cnpBillSeen = d.billPend || 0;
}

/* Η ουρά εγκρίσεων χρέωσης — εγκρίνεις επί τόπου, χωρίς να ανοίξεις κάθε καρτέλα. */
async function billingQueue() {
  /* Ο αυτόματος έλεγχος και το κλικ στο chip μπορούν να συμπέσουν — ένα παράθυρο
     αρκεί, δύο στοιβαγμένα είναι σκέτη σύγχυση. */
  if (document.querySelector('#bqBox')) { return; }
  const d = await api('billing_pending').catch(() => null);
  if (!d || !d.mine || !d.items.length) { return; }
  if (document.querySelector('#bqBox')) { return; }
  const ovl = document.createElement('div');
  ovl.className = 'ovl show'; ovl.style.zIndex = 320;
  const row = i => `<div class="bq-row" data-bq="${i.id}">
    <div style="flex:1;min-width:0">
      <b style="font-size:13px;color:var(--ink)">${esc(i.title)}</b>
      <div class="mut" style="font-size:11px">#${i.id}${i.project ? ' · ' + esc(i.project) : ''}${i.who ? ' · ' + esc(i.who) : ''}</div>
    </div>
    <span class="pill pill-warn" style="white-space:nowrap">${fmtMin(i.mins)}</span>
    <button class="btn btn-sm btn-p" data-bqok="${i.id}">Έγκριση</button>
    <button class="btn btn-sm btn-o" data-bqno="${i.id}" title="Δεν χρεώνεται — κλείνει το θέμα">Χωρίς χρέωση</button>
    <button class="btn btn-sm btn-o" data-bqopen="${i.id}">Άνοιγμα</button>
  </div>`;
  ovl.innerHTML = `<div class="pal-box" id="bqBox" style="margin:12vh auto 0;max-width:620px" role="dialog">
    <div style="padding:20px 22px 18px">
      <b style="font-size:15.5px;color:var(--ink)">${I.coin} Εκκρεμούν εγκρίσεις χρέωσης</b>
      <div class="mut" style="font-size:12px;margin-top:5px">Αυτές οι εργασίες <b>δεν κλείνουν</b> πριν εγκρίνεις τη χρέωση.</div>
      <div id="bqList" style="margin-top:13px;max-height:52vh;overflow:auto">${d.items.map(row).join('')}</div>
      <div style="display:flex;gap:9px;margin-top:16px;justify-content:flex-end">
        <button class="btn btn-o" id="bqClose">Αργότερα</button>
      </div>
    </div></div>`;
  document.body.appendChild(ovl);
  const shut = () => ovl.remove();
  $('#bqClose', ovl).onclick = shut;
  /* Πριν ήταν <a href="#/task/N">: η αλλαγή hash ΔΕΝ άνοιγε την εργασία (ο router
     ψάχνει «οθόνη», και «task» δεν είναι οθόνη) — γι' αυτό χρειαζόταν refresh.
     Τώρα ανοίγει κατευθείαν την καρτέλα. */
  $$('[data-bqopen]', ovl).forEach(a => a.onclick = () => { shut(); openTask(+a.dataset.bqopen); });
  $$('[data-bqno]', ovl).forEach(b => b.onclick = async () => {
    const id = +b.dataset.bqno;
    const why = await cnpDialog({title: 'Χωρίς χρέωση;',
      body: 'Ο χρόνος θα γίνει μη χρεώσιμος και το αίτημα φεύγει από την ουρά.\nΑν έχει ήδη περάσει στο πακέτο του πελάτη, αναιρείται.',
      input: '', placeholder: 'Λόγος (προαιρετικό) — π.χ. δικό μας bug', ok: 'Ναι, χωρίς χρέωση'});
    if (why === null) { return; }
    b.disabled = true; b.textContent = '…';
    const r = await api('task_billing_none', {task: id, note: why}).catch(e => ({err: e.message}));
    if (r && r.err) { toast(r.err, true); b.disabled = false; b.textContent = 'Χωρίς χρέωση'; return; }
    const line = ovl.querySelector(`[data-bq="${id}"]`);
    if (line) { line.remove(); }
    toast('Χωρίς χρέωση — το θέμα έκλεισε');
    if (!$$('[data-bq]', ovl).length) { shut(); }
    loadTopStats();
  });
  $$('[data-bqok]', ovl).forEach(b => b.onclick = async () => {
    b.disabled = true; b.textContent = '…';
    const r = await api('task_billing_ok', {task: +b.dataset.bqok, ok: true}).catch(e => ({err: e.message}));
    if (r && r.err) { toast(r.err, true); b.disabled = false; b.textContent = 'Έγκριση'; return; }
    const line = ovl.querySelector(`[data-bq="${b.dataset.bqok}"]`);
    if (line) { line.remove(); }
    toast('Η χρέωση εγκρίθηκε');
    if (!$$('[data-bq]', ovl).length) { shut(); }
    loadTopStats();
  });
}
function miniMenu(anchor, items) {
  const ex = $('#miniMenu'); if (ex) { ex.remove(); }
  const r = anchor.getBoundingClientRect();
  const m = document.createElement('div'); m.id = 'miniMenu'; m.className = 'mini-menu';
  m.style.top = (r.bottom + 6) + 'px'; m.style.right = (window.innerWidth - r.right) + 'px';
  m.innerHTML = items.map((it, i) => `<button class="mini-row" data-i="${i}">${it.dot ? `<span class="dot" style="background:${it.dot}"></span>` : (it.icon || '')}<span>${esc(it.label)}</span></button>`).join('');
  document.body.appendChild(m);
  requestAnimationFrame(() => m.classList.add('show'));
  items.forEach((it, i) => { const b = m.querySelector(`[data-i="${i}"]`); if (b) { b.onclick = () => { m.remove(); if (it.on) { it.on(); } }; } });
  const closer = e => { if (!m.contains(e.target)) { m.remove(); document.removeEventListener('click', closer); } };
  setTimeout(() => document.addEventListener('click', closer), 0);
}
/* ── Δυνατή ειδοποίηση chat ────────────────────────────────────────────────
   Κάρτα κάτω δεξιά που ΔΕΝ μπλοκάρει την οθόνη (μήνυμα δεν είναι επείγον σαν
   το 🆘), αλλά συνοδεύεται από ήχο και αναβοσβήνει ο τίτλος — τα δύο πράγματα
   που σε φτάνουν όταν κοιτάς αλλού. */
let chatTitleTimer = null;
const CHAT_TITLE0 = document.title;
/**
 * ΕΝΑΣ AudioContext, όχι ένας ανά μήνυμα.
 *
 * Έφτιαχνε καινούργιο σε κάθε ήχο και δεν τον έκλεινε ποτέ. Οι browsers
 * επιτρέπουν λίγους ταυτόχρονα (Chrome ~6): μετά το έκτο μήνυμα η δημιουργία
 * πετούσε σφάλμα, το `catch` το κατάπινε σιωπηλά, και ο ήχος έσβηνε για τα
 * καλά μέχρι να ξαναφορτώσεις τη σελίδα. Γι' αυτό «δεν ακουγόταν».
 *
 * Και δεύτερο: ο browser κρατά το AudioContext σε αναστολή μέχρι ο χρήστης
 * αγγίξει τη σελίδα. Το ξυπνάμε στην πρώτη αλληλεπίδραση, μία φορά.
 */
let cnpAudio = null;
function cnpAudioCtx() {
  const AC = window.AudioContext || window.webkitAudioContext;
  if (!AC) { return null; }
  if (!cnpAudio) { try { cnpAudio = new AC(); } catch (e) { return null; } }
  if (cnpAudio.state === 'suspended') { cnpAudio.resume().catch(() => {}); }
  return cnpAudio;
}
['pointerdown', 'keydown'].forEach(ev =>
  window.addEventListener(ev, () => cnpAudioCtx(), {once: true, passive: true}));

/**
 * Ο ήχος παραλαβής: δύο νότες που ανεβαίνουν, με σώμα μια οκτάβα κάτω.
 *
 * Το ημιτονοειδές μόνο του ακουγόταν λεπτό και χανόταν σε ανοιχτό γραφείο. Το
 * τρίγωνο έχει αρμονικές, άρα «κόβει» χωρίς να τσιρίζει, και η χαμηλή νότα του
 * δίνει βάρος. Η ένταση ανέβηκε από 0,13 σε 0,34 — δυνατά, αλλά όχι συναγερμός:
 * δέκα μηνύματα την ώρα δεν πρέπει να γίνουν λόγος να κλείσει κανείς τον ήχο.
 */
function chatBeep() {
  try {
    const a = cnpAudioCtx();
    if (!a) { return; }
    //   συχνότητα, πότε, ένταση, κυματομορφή
    [[880.0,   0,    0.34, 'triangle'],
     [1174.7,  0.10, 0.34, 'triangle'],
     [440.0,   0,    0.14, 'sine']].forEach(([hz, at, vol, type]) => {
      const o = a.createOscillator(), g = a.createGain();
      o.connect(g); g.connect(a.destination);
      o.type = type; o.frequency.value = hz;
      const t = a.currentTime + at;
      g.gain.setValueAtTime(0.0001, t);
      g.gain.exponentialRampToValueAtTime(vol, t + 0.015);
      g.gain.exponentialRampToValueAtTime(0.0001, t + 0.32);
      o.start(t); o.stop(t + 0.34);
    });
  } catch (e) { /* χωρίς ήχο — η κάρτα φτάνει */ }
}
function chatPop(m) {
  let w = $('#chatPops');
  if (!w) { w = document.createElement('div'); w.id = 'chatPops'; document.body.appendChild(w); }
  if (w.children.length >= 3) { w.firstElementChild.remove(); }
  const el = document.createElement('div');
  el.className = 'chat-pop';
  el.innerHTML = `<div class="cp-h"><span class="cp-ava">${esc(adminIni(m.fromId) || '?')}</span>
      <b>${esc(m.from)}</b>${m.where ? `<span class="mut">· ${esc(m.where)}</span>` : ''}
      <span style="flex:1"></span><button class="cp-x" title="Κλείσιμο">✕</button></div>
    <div class="cp-b">${esc(m.text)}</div>
    <div class="cp-f"><button class="btn btn-sm btn-p">${I.chat} Άνοιξε</button></div>`;
  w.appendChild(el);
  requestAnimationFrame(() => el.classList.add('show'));
  const kill = () => { el.classList.remove('show'); setTimeout(() => el.remove(), 220); };
  el.querySelector('.cp-x').onclick = kill;
  el.querySelector('.btn').onclick = () => { kill(); stopChatTitle(); go('chat'); };
  setTimeout(kill, 14000);
  chatBeep();
  if (!chatTitleTimer) {
    let on = false;
    chatTitleTimer = setInterval(() => { on = !on; document.title = on ? '💬 Νέο μήνυμα' : CHAT_TITLE0; }, 1100);
    window.addEventListener('focus', stopChatTitle, {once: true});
  }
}
/* ═══ ΠΡΟΣΚΛΗΣΗ ΣΕ ΣΥΣΚΕΨΗ — δυνατά, με απάντηση επί τόπου ═══════════════════
   Μια πρόσκληση δεν πρέπει να περιμένει να κοιτάξεις καμπανάκι. Κάρτα που
   μένει μέχρι να απαντήσεις (δεν σβήνει μόνη της), με ήχο και αναβοσβήνον
   τίτλο — και με τα «Θα είμαι εκεί / Δεν μπορώ» πάνω της, ώστε να μη χρειάζεται
   να ανοίξεις τίποτα. Η υπενθύμιση πριν την έναρξη φέρνει το κουμπί συμμετοχής. */
function meetPop(a) {
  if (!a || !a.id) { return; }
  window._cnpMeetPop_ = window._cnpMeetPop_ || {};
  const key = a.id + ':' + a.alert;
  if (window._cnpMeetPop_[key]) { return; }
  window._cnpMeetPop_[key] = 1;
  const seen = () => api('event_alert_seen', {id: a.id, alert: a.alert}).catch(() => {});
  let w = $('#chatPops');
  if (!w) { w = document.createElement('div'); w.id = 'chatPops'; document.body.appendChild(w); }
  const soon = a.alert === 'soon';
  /* ΞΕΠΕΡΑΣΕ ΤΗΝ ΩΡΑ ΤΗΣ. Δεν είναι υπενθύμιση — είναι ερώτηση, και δεν φεύγει
     μόνη της: όσο δεν απαντηθεί, ο χειριστής φαίνεται διαθέσιμος ενώ δεν είναι. */
  const over = a.alert === 'over';
  const el = document.createElement('div');
  el.className = 'chat-pop meet-pop' + (soon ? ' soon' : '') + (over ? ' over' : '');
  el.innerHTML = `<div class="cp-h"><span class="mp-ic">${over ? '⏳' : soon ? '⏰' : '📅'}</span>
      <b>${over ? 'Πέρασε η ώρα της' : soon ? (a.inMin > 0 ? 'Σε ' + a.inMin + '΄ αρχίζει' : 'Αρχίζει τώρα') : 'Πρόσκληση σε σύσκεψη'}</b>
      <span style="flex:1"></span><button class="cp-x" title="Κλείσιμο">✕</button></div>
    <div class="cp-b"><b class="mp-t">${esc(a.title)}</b>
      <div class="mp-meta">${esc(a.whenTxt)}${a.how ? ' · ' + esc(a.how) : ''}</div>
      ${a.client ? `<div class="mp-meta">👤 ${esc(a.client)}</div>` : ''}
      ${over ? `<div class="mp-over">+${a.overMin}΄ πάνω από την ώρα της${
        a.extended ? ` · έχεις ήδη πάρει ${a.extended}΄ παράταση` : ''}</div>
        <div class="mp-meta">Η απάντηση αφορά <b>εσένα</b> — οι υπόλοιποι μπορεί να συνεχίζουν.
        Όσο δεν απαντάς, φαίνεσαι <b>διαθέσιμος</b>.</div>` : ''}
      ${!soon && !over && a.by ? `<div class="mp-meta">από ${esc(a.by)}</div>` : ''}</div>
    <div class="cp-f">
      ${over
        ? `<button class="btn btn-sm btn-p" data-go="done" title="Για εσένα — οι υπόλοιποι μπορεί να συνεχίζουν">✔ Βγήκα</button>
           <button class="btn btn-sm btn-o" data-go="ext15">+15΄</button>
           <button class="btn btn-sm btn-o" data-go="ext30">+30΄</button>
           <button class="btn btn-sm btn-o" data-go="openend" title="Δεν ξέρω πότε — ξαναρωτάμε σε μισή ώρα">Συνεχίζεται</button>`
        : soon
        ? (a.join ? `<button class="btn btn-sm btn-p" data-go="join">${a.mode === 'phone' ? I.phone : I.video} Συμμετοχή</button>` : '')
          + `<button class="btn btn-sm btn-o" data-go="open">Άνοιγμα</button>`
        : `<button class="btn btn-sm btn-p" data-go="acc">✔ Θα είμαι εκεί</button>
           <button class="btn btn-sm btn-o" data-go="dec">✖ Δεν μπορώ</button>
           <button class="btn btn-sm btn-o" data-go="open">Λεπτομέρειες</button>`}
    </div>`;
  w.appendChild(el);
  requestAnimationFrame(() => el.classList.add('show'));
  const kill = () => { el.classList.remove('show'); setTimeout(() => el.remove(), 220); };
  el.querySelector('.cp-x').onclick = () => { seen(); kill(); };
  el.querySelectorAll('[data-go]').forEach(b => b.onclick = async () => {
    const go2 = b.dataset.go;
    if (go2 === 'acc' || go2 === 'dec') {
      await api('event_rsvp', {id: a.id, status: go2 === 'acc' ? 'accepted' : 'declined'}).catch(() => {});
      toast(go2 === 'acc' ? '✔ Δήλωσες συμμετοχή — η κατάστασή σου θα γίνει «Σε σύσκεψη» την ώρα της'
        : 'Καταγράφηκε ότι δεν μπορείς');
    } else if (go2 === 'done' || go2.indexOf('ext') === 0 || go2 === 'openend') {
      const body = go2 === 'done' ? {what: 'done'}
        : go2 === 'openend' ? {what: 'open'}
        : {what: 'extend', minutes: +go2.slice(3)};
      const x = await api('event_outcome', {id: a.id, ...body}).catch(e => ({err: e.message}));
      if (x && (x.err || x.error)) { toast(x.err || x.error, true); return; }
      toast(x.msg || 'Καταγράφηκε');
      /* Η κάρτα «over» ΔΕΝ σημειώνεται ως «την είδα»: αν πήρε παράταση πρέπει
         να ξαναρωτήσει όταν περάσει και η νέα ώρα. Ο server τη σβήνει. */
      kill(); stopChatTitle();
      return;
    } else if (go2 === 'join' && a.join) {
      if (a.mode === 'phone') { location.href = 'tel:' + String(a.join).replace(/\s/g, ''); }
      else { window.open(a.join, '_blank'); }
    } else { go('calendar'); }
    seen(); kill(); stopChatTitle();
  });
  if (soon) { setTimeout(() => { seen(); kill(); }, 60000); }   // η υπενθύμιση φεύγει μόνη
  /* Η ερώτηση της υπέρβασης ΔΕΝ φεύγει μόνη: θέλει απάντηση. */
  chatBeep();
  if (!chatTitleTimer) {
    let on = false;
    const txt = over ? '⏳ Είσαι ακόμη στη σύσκεψη;' : soon ? '⏰ Σύσκεψη τώρα' : '📅 Πρόσκληση σε σύσκεψη';
    chatTitleTimer = setInterval(() => { on = !on; document.title = on ? txt : CHAT_TITLE0; }, 1100);
    window.addEventListener('focus', stopChatTitle, {once: true});
  }
}

function stopChatTitle() {
  if (chatTitleTimer) { clearInterval(chatTitleTimer); chatTitleTimer = null; document.title = CHAT_TITLE0; }
}

function updateBell(n) { const b = $('#bellN'); if (!b) return; b.style.display = n ? '' : 'none'; b.textContent = n > 99 ? '99+' : n; }

/* ── Web Push: ειδοποιήσεις συσκευής (κινητό/desktop) ακόμη κι με κλειστή εφαρμογή ── */
function urlB64ToU8(str) {
  const pad = '='.repeat((4 - str.length % 4) % 4);
  const bin = atob((str + pad).replace(/-/g, '+').replace(/_/g, '/'));
  return Uint8Array.from([...bin].map(c => c.charCodeAt(0)));
}
async function cnpPushSubscribe(reg) {
  const pk = await api('push_pubkey').catch(() => null);
  if (!pk || !pk.enabled || !pk.key) { return false; }
  let sub = await reg.pushManager.getSubscription();
  if (!sub) { sub = await reg.pushManager.subscribe({userVisibleOnly: true, applicationServerKey: urlB64ToU8(pk.key)}); }
  const j = sub.toJSON();
  await api('push_subscribe', {endpoint: sub.endpoint, p256dh: j.keys.p256dh, auth: j.keys.auth});
  return true;
}
async function cnpPushInit() {
  try {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) { return; }
    if (Notification.permission !== 'granted') { return; }   // αυτόματα ΔΕΝ ρωτάμε
    const reg = await navigator.serviceWorker.ready;
    await cnpPushSubscribe(reg);
  } catch (e) {}
}
async function cnpPushEnable() {
  if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
    toast('Η συσκευή/browser δεν υποστηρίζει ειδοποιήσεις', true); return;
  }
  const perm = await Notification.requestPermission();
  if (perm !== 'granted') { toast('Δεν δόθηκε άδεια ειδοποιήσεων', true); return; }
  try {
    const reg = await navigator.serviceWorker.ready;
    const ok = await cnpPushSubscribe(reg);
    toast(ok ? '🔔 Ειδοποιήσεις ενεργές σε αυτή τη συσκευή' : 'Δεν ενεργοποιήθηκαν', !ok);
    const b = $('#pushEnable'); if (b) { b.remove(); }
  } catch (e) { toast('Σφάλμα ενεργοποίησης', true); }
}
async function toggleBell() {
  const old = $('.pop'); if (old) { old.remove(); return; }
  const d = await api('notifs'); updateBell(d.unread + (d.pendingCount || 0));
  const pop = document.createElement('div'); pop.className = 'pop';
  /* Εκκρεμότητες: ό,τι ζητήθηκε από εμένα (βοήθεια, ερώτηση, φωνή, σύσκεψη) και δεν
     απαντήθηκε — μένει εδώ μέχρι να το τακτοποιήσω, ακόμη κι αν έκλεισα το popup. */
  const pd = d.pending || {help: [], meetings: [], mine: []};
  const hk = k => k === 'voice' ? '🔊' : (k === 'checkin' ? '❓' : (k === 'offer' ? '📄' : (k === 'mention' ? '💬' : '🆘')));
  const pendHtml = (pd.help.length || pd.meetings.length || pd.mine.length) ? `
    <div class="pop-sec">Εκκρεμούν — θέλουν απάντηση</div>
    ${pd.help.map(h => `<div class="prow" data-help="${h.id}">
      <span class="prow-ic">${hk(h.kind)}</span>
      <span class="prow-t"><b>${esc(h.from)}</b> ${h.kind === 'voice' ? 'σε καλεί στη φωνή' : (h.kind === 'checkin' ? 'ρωτά τι γίνεται' : (h.kind === 'offer' ? 'ζητά να φτιάξεις προσφορά' : (h.kind === 'mention' ? 'σε ρωτά σε εργασία — περιμένει απάντηση' : 'χρειάζεται τη βοήθειά σου')))}
        <span class="mut">${esc(h.message.slice(0, 90))}${h.taskTitle ? ' · ' + esc(h.taskTitle.slice(0, 50)) : ''}</span></span>
      <span class="prow-a"><button class="btn btn-sm btn-p" data-hopen="${h.id}" title="Ξανανοίγει το αίτημα για να απαντήσεις">Άνοιξε</button>
        ${h.kind !== 'checkin' ? `<button class="btn btn-sm btn-o" data-hdone="${h.id}" title="Τακτοποιήθηκε — φεύγει από εδώ">✓</button>` : ''}</span>
      <span class="tm">${tShort(h.at)}</span></div>`).join('')}
    ${pd.meetings.map(m => `<div class="prow" data-meet="${m.id}">
      <span class="prow-ic">📅</span>
      <span class="prow-t"><b>Πρόσκληση:</b> ${esc(m.title)} <span class="mut">${esc(m.whenTxt)}${m.by ? ' · από ' + esc(m.by) : ''}</span></span>
      <span class="prow-a"><button class="btn btn-sm btn-p" data-mrsvp="accepted" title="Θα είμαι εκεί">✔</button>
        <button class="btn btn-sm btn-o" data-mrsvp="declined" title="Δεν μπορώ">✖</button>
        <button class="btn btn-sm btn-o" data-mopen="${m.id}" title="Λεπτομέρειες">…</button></span></div>`).join('')}
    ${pd.mine.map(h => `<div class="prow mine" data-help="${h.id}">
      <span class="prow-ic">${hk(h.kind)}</span>
      <span class="prow-t"><span class="mut">${h.kind === 'mention' ? 'Τον ανέφερες, περιμένεις απάντηση από' : 'Περιμένεις απάντηση από'}</span> <b>${esc(h.to)}</b> <span class="mut">${esc(h.message.slice(0, 70))}${h.seen ? ' · το είδε' : ' · δεν το έχει δει'}</span></span>
      <span class="prow-a"><button class="btn btn-sm btn-o" data-hdone="${h.id}" title="Ακύρωση / τακτοποιήθηκε">✓</button></span>
      <span class="tm">${tShort(h.at)}</span></div>`).join('')}` : '';
  const pushRow = ('Notification' in window && 'PushManager' in window && Notification.permission !== 'granted')
    ? `<a href="#" id="pushEnable" class="push-enable">${I.bell} Ενεργοποίηση ειδοποιήσεων σε αυτή τη συσκευή</a>` : '';
  pop.innerHTML = `<div class="pop-h">Ειδοποιήσεις <a href="#" id="readAll" style="font-size:11px;font-weight:600">όλα ως διαβασμένα</a></div>` + pushRow + pendHtml +
    (d.items.length ? d.items.map(n => `<a class="nrow ${n.read ? '' : 'unread'}" data-id="${n.id}" data-url="${esc(n.url || '')}">
      <span>${esc(n.title)}</span><span class="tm">${tShort(n.at)}</span></a>`).join('')
    : '<div class="empty" style="padding:22px">Καμία ειδοποίηση</div>');
  $('.bell-wrap').appendChild(pop);
  const pe = pop.querySelector('#pushEnable'); if (pe) { pe.onclick = e => { e.preventDefault(); e.stopPropagation(); cnpPushEnable(); }; }
  /* Εκκρεμότητες: ξανάνοιγμα του ίδιου popup (με απάντηση), τακτοποίηση, RSVP επί τόπου. */
  pop.querySelectorAll('[data-hopen]').forEach(b => b.onclick = e => {
    e.stopPropagation();
    const h = pd.help.find(x => x.id === +b.dataset.hopen); if (!h) { return; }
    pop.remove();
    if (window.CNP.showHelpAlert) { window.CNP.showHelpAlert(h, true); }
  });
  pop.querySelectorAll('[data-hdone]').forEach(b => b.onclick = async e => {
    e.stopPropagation();
    await api('help_done', {id: +b.dataset.hdone}).catch(() => {});
    toast('Τακτοποιήθηκε'); pop.remove(); toggleBell();
  });
  pop.querySelectorAll('[data-mrsvp]').forEach(b => b.onclick = async e => {
    e.stopPropagation();
    const id = +b.closest('[data-meet]').dataset.meet;
    await api('event_rsvp', {id, status: b.dataset.mrsvp}).catch(() => {});
    toast(b.dataset.mrsvp === 'accepted' ? '✔ Δήλωσες συμμετοχή' : 'Καταγράφηκε ότι δεν μπορείς');
    pop.remove(); toggleBell();
  });
  pop.querySelectorAll('[data-mopen]').forEach(b => b.onclick = e => {
    e.stopPropagation();
    const m = pd.meetings.find(x => x.id === +b.dataset.mopen); if (!m) { return; }
    pop.remove();
    window._cnpMeetPop_ = window._cnpMeetPop_ || {}; delete window._cnpMeetPop_[m.id + ':' + m.alert];
    meetPop(m);
  });
  pop.onclick = async e => {
    if (e.target.closest('.prow')) { return; }
    const a = e.target.closest('.nrow'); const ra = e.target.closest('#readAll');
    if (ra) { e.preventDefault(); const r0 = await api('notif_read', {id: 0}); updateBell(r0.pending || 0); toggleBell(); return; }
    if (!a) return; e.preventDefault();
    const r = await api('notif_read', {id: +a.dataset.id}); updateBell(r.unread + (r.pending || 0));
    const url = a.dataset.url;
    /* ΚΑΘΕ ΕΠΟΧΗ ΣΥΝΔΕΣΜΟΥ ΑΝΟΙΓΕΙ. Οι νέες ειδοποιήσεις γράφονται πλέον ως
       `/project/#/task/N` (Db::appLink), αλλά στη βάση ζουν ακόμη 520 παλιές με
       `tab=task&id=N`. Και οι δύο μορφές πρέπει να ανοίγουν την κάρτα — αλλιώς
       πατάς την ειδοποίηση και δεν βρίσκεις τίποτα. */
    const m = url && (url.match(/tab=task&id=(\d+)/) || url.match(/\/project\/#\/task\/(\d+)/));
    const mt = url && url.match(/supporttickets\.php\?action=view&id=(\d+)/);
    const hv = url && url.match(/\/project(?:management)?\/#\/(\w+)/);
    if (m) { pop.remove(); openTask(+m[1]); }
    else if (mt) { pop.remove(); go('inbox', +mt[1]); }
    else if (/^https?:\/\//.test(url || '') && !hv) { pop.remove(); window.open(url, '_blank'); }   // π.χ. link meeting
    else if (hv) { pop.remove(); go(hv[1]); }                                                        // εσωτερικά views
    else if (url && S.boot.me.full) window.open('/cloudonadminpanel/' + url, '_blank');
  };
  setTimeout(() => document.addEventListener('click', function h(e) {
    if (!pop.contains(e.target) && !e.target.closest('#bellBtn')) { pop.remove(); document.removeEventListener('click', h); }
  }), 10);
}
/* ═══ 🖥 Remote συνεδρίες: καθολικό χρονόμετρο + χρέωση ═══ */
let _remote = null, _remoteTick = null;
function remoteChipUpdate() {
  const c = document.getElementById('remoteChip');
  if (!c) return;
  if (!_remote) { c.style.display = 'none'; clearInterval(_remoteTick); return; }
  const s = Math.max(0, Math.floor((Date.now() - _remote.t0) / 1000));
  c.style.display = '';
  c.textContent = '🖥 ' + _remote.clientName.slice(0, 18) + ' · ' +
    String(Math.floor(s / 3600)).padStart(2, '0') + ':' + String(Math.floor(s / 60) % 60).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0');
}
async function remoteRefresh() {
  try {
    const r = await api('remote_active');
    _remote = r.session ? Object.assign(r.session, {t0: Date.now() - r.session.secs * 1000}) : null;
  } catch (e) { _remote = null; }
  clearInterval(_remoteTick);
  if (_remote) _remoteTick = setInterval(remoteChipUpdate, 1000);
  remoteChipUpdate();
}
function startRemote(clientId, clientName, ticketId, opts) {
  opts = opts || {};
  const dl = (S.boot.rustdeskDl || 'https://remote.cloudon.gr/download/CloudOn-Remote.exe');
  const ovl = document.createElement('div'); ovl.className = 'ovl show'; ovl.style.zIndex = 300;
  
  ovl.innerHTML = `<div class="pal-box" style="margin:9vh auto 0;max-width:480px" onclick="event.stopPropagation()">
    <div style="padding:20px 22px">
      <b style="font-size:15.5px;color:var(--ink)">${I.monitor} Απομακρυσμένη υποστήριξη</b>
      <div class="mut" style="font-size:12.5px;margin-top:2px">${esc(clientName)}${opts.ticketLabel ? ' · ' + I.ticket + ' ' + esc(opts.ticketLabel) : ''}</div>

      <div style="margin-top:14px;padding:12px 14px;border:1px solid var(--line);border-radius:11px">
        <b style="font-size:12.5px;color:var(--ink)">① Ο πελάτης δεν έχει το πρόγραμμα;</b>
        <div style="display:flex;gap:7px;margin-top:8px">
          <input class="inp" id="rmEmail" value="${esc(opts.email || '')}" placeholder="email πελάτη" style="flex:1">
          <button class="btn btn-o btn-sm" id="rmSend" style="white-space:nowrap">${I.mail} Στείλε πρόγραμμα</button></div>
        <div class="mut" style="font-size:11px;margin-top:6px">Ή δώσ' του τον σύνδεσμο:
          <a href="${dl}" id="rmDl" style="font-weight:700">CloudOn Remote ⬇</a>
          <button class="btn btn-o" id="rmCopy" style="padding:1px 7px;font-size:10px;margin-left:4px">Αντιγραφή</button></div>
      </div>

      <div style="margin-top:12px;padding:12px 14px;border:1px solid var(--line);border-radius:11px">
        <b style="font-size:12.5px;color:var(--ink)">② Σύνδεση</b>
        <div class="mut" style="font-size:11px;margin-top:3px" id="rmPeerHint">Ο πελάτης ανοίγει το πρόγραμμα και σου διαβάζει το <b>ID (9 ψηφία)</b>.</div>
        <input class="inp" id="rmPeer" placeholder="RustDesk ID πελάτη — π.χ. 123 456 789" style="margin-top:8px;font-size:16px;letter-spacing:1px">
        <input class="inp" id="rmNote" placeholder="Τι θα κάνεις (για τη χρέωση)…" style="margin-top:8px">
        <div class="mut" style="font-size:10.5px;margin-top:6px">Το ID αποθηκεύεται αυτόματα για αυτόν τον πελάτη — την επόμενη φορά θα είναι έτοιμο.</div>
      </div>

      <div style="display:flex;gap:9px;margin-top:15px;justify-content:flex-end">
        <button class="btn btn-o" id="rmNo">Άκυρο</button>
        <button class="btn" id="rmGo" style="background:var(--ok);color:#fff">${I.monitor} Σύνδεση</button></div>
    </div></div>`;
  document.body.appendChild(ovl);
  $('#rmDl', ovl).href = dl;
  // 📇 φέρε το αποθηκευμένο RustDesk ID αυτού του πελάτη (αν υπάρχει) → prefill
  if (opts.savedPeer) {
    ovl.querySelector('#rmPeer').value = opts.savedPeer.replace(/(\d{3})(?=\d)/g, '$1 ');
  } else if (clientId) {
    api('remote_peer&client=' + clientId).then(r => {
      const inp = ovl.querySelector('#rmPeer');
      if (r && r.rustdesk_id && inp && !inp.value) {
        inp.value = r.rustdesk_id.replace(/(\d{3})(?=\d)/g, '$1 ');
        const h = ovl.querySelector('#rmPeerHint');
        if (h) { h.innerHTML = '💾 <b>Αποθηκευμένο ID</b> — έτοιμο για σύνδεση (μπορείς να το αλλάξεις).'; }
      }
    }).catch(() => {});
  }
  setTimeout(() => ovl.querySelector('#rmPeer').focus(), 30);
  ovl.querySelector('#rmNo').onclick = () => ovl.remove();
  ovl.querySelector('#rmCopy').onclick = () => navigator.clipboard.writeText(dl).then(() => toast('Ο σύνδεσμος αντιγράφηκε 📋'));
  ovl.querySelector('#rmSend').onclick = async () => {
    const r = await api('remote_send_client', {client: clientId, email: ovl.querySelector('#rmEmail').value.trim()}).catch(e => ({err: e.message}));
    if (r.err) { toast(r.err, true); return; }
    toast('📧 Στάλθηκε στο ' + r.sent);
  };
  const go = async () => {
    const peer = ovl.querySelector('#rmPeer').value.replace(/\D/g, '');
    if (peer.length < 6) { toast('Δώσε το ID (9 ψηφία) του πελάτη', true); return; }
    const r = await api('remote_start', {client: clientId, ticket: ticketId || 0,
      peer, note: ovl.querySelector('#rmNote').value}).catch(e => ({err: e.message}));
    if (r.err) { toast(r.err, true); return; }
    ovl.remove();
    remoteRefresh();
    if (r.gatewayUrl) window.location.href = r.gatewayUrl;   // ανοίγει το RustDesk του χειριστή
    toast('🖥 Σύνδεση — ο χρόνος μετράει. Αν δεν άνοιξε το RustDesk, εγκατέστησέ το μία φορά.');
  };
  ovl.querySelector('#rmGo').onclick = go;
  ovl.querySelector('#rmPeer').onkeydown = e => { if (e.key === 'Enter') go(); };
}

// 📇 Address book: αποθηκευμένες RustDesk συνδέσεις πελατών — ένα κλικ, χωρίς να ξαναρωτάς
window.R = window.R || {};
const rbId = s => String(s || '').replace(/(\d{3})(?=\d)/g, '$1 ');   // 123456789 → 123 456 789
const rbIni = n => (String(n || '?').trim().split(/\s+/).map(w => w[0] || '').slice(0, 2).join('') || '?').toUpperCase();
/** «πριν 3 ημέρες» / «σήμερα» — σύντομη σχετική ώρα. */
function rbAgo(dt) {
  if (!dt) return '';
  const days = Math.floor((Date.now() - new Date(String(dt).replace(' ', 'T')).getTime()) / 86400000);
  if (days <= 0) return 'σήμερα';
  if (days === 1) return 'χθες';
  if (days < 30) return `πριν ${days} ημ.`;
  const m = Math.round(days / 30);
  return m < 12 ? `πριν ${m} μήνα${m > 1 ? 'ς' : ''}` : `πριν ${Math.round(days / 365)} χρόνια`;
}

window.R.remotebook = async function () {
  /* Φρουρός κυκλώματος «Η ομάδα» (12/9/2026): ό,τι κόβει ο server, δεν ανοίγει καν. */
  if (!cnpCan('team.remote')) { setTop('Απομακρυσμένες'); $('#content').innerHTML = cnpDenied({message: 'Το βιβλίο απομακρυσμένων δίνεται από το κύκλωμα «Η ομάδα → Απομακρυσμένες»'}); return; }
  setTop('Απομακρυσμένες', 'Αποθηκευμένες συνδέσεις πελατών — ένα κλικ για σύνδεση');
  const c = $('#content');
  const st = R.remotebook._s = R.remotebook._s || {q: '', form: false, edit: null};
  cnpSkel(c, `<div class="grid g4" style="margin-bottom:14px">${'<div class="skel" style="height:56px"></div>'.repeat(4)}</div>
    <div class="skel" style="height:300px"></div>`);
  const d = await api('remote_book').catch(() => null);
  if (!d) { c.innerHTML = '<div class="empty"><div class="big">' + I.monitor + '</div>Δεν φορτώθηκε</div>'; return; }
  const rows = d.book || [], recent = d.recent || [], sx = d.stats || {};
  const dl = d.dl || S.boot.rustdeskDl || '';

  const card = r => `<div class="rbk" data-rbcard="${r.clientid}">
    <div class="rbk-top">
      <span class="rbk-ava">${esc(rbIni(r.name))}</span>
      <div class="rbk-id-wrap">
        <div class="rbk-name" title="${esc(r.name)}">${esc(r.name)}</div>
        <div class="rbk-id">${esc(rbId(r.rustdesk_id))}
          <button class="rbk-copy" data-rbcopy="${esc(r.rustdesk_id)}" title="Αντιγραφή ID">${I.copy || I.link}</button></div>
      </div>
    </div>
    ${r.label ? `<div class="rbk-label">${esc(r.label)}</div>` : ''}
    <div class="rbk-meta">${r.sessions
      ? `<span>${I.clock} ${r.sessions} ${r.sessions === 1 ? 'συνεδρία' : 'συνεδρίες'}</span><span class="sep">·</span><span>${esc(rbAgo(r.lastAt))}</span>`
      : '<span class="mut">Καμία συνεδρία ακόμη</span>'}</div>
    <div class="rbk-acts">
      <button class="btn btn-sm rb-go" data-rbgo="${r.clientid}" data-name="${esc(r.name)}" data-peer="${esc(r.rustdesk_id)}">${I.monitor} Σύνδεση</button>
      <button class="btn btn-sm btn-o rbk-ico" data-rbedit="${r.clientid}" title="Επεξεργασία">${I.edit}</button>
      <button class="btn btn-sm btn-o rbk-ico rb-del" data-rbdel="${r.clientid}" title="Αφαίρεση">${I.trash}</button>
    </div></div>`;

  c.innerHTML = `
  <div class="grid g4" style="margin-bottom:14px">
    ${suStat(I.contact, sx.saved || 0, 'Αποθηκευμένες', '#0090dd')}
    ${suStat(I.monitor, sx.n30 || 0, 'Συνεδρίες 30 ημ.', '#7b5cd6')}
    ${suStat(I.clock, fmtMin(sx.mins30 || 0), 'Χρόνος 30 ημ.', '#1f9d57')}
    ${suStat(I.coin, fmtMin(sx.bmins30 || 0), 'Χρεώσιμος χρόνος', '#e0a020')}
  </div>

  <div class="card rbk-bar">
    <input class="inp" id="rbQ" placeholder="Αναζήτηση πελάτη ή ID…" value="${esc(st.q)}">
    <button class="btn btn-p btn-sm" id="rbNew">${I.plus} Νέα σύνδεση</button>
    <button class="btn btn-o btn-sm" id="rbSend">${I.mail} Στείλε το πρόγραμμα</button>
  </div>
  <div id="rbForm"></div>

  <div id="rbGrid" class="rbk-grid"></div>

  ${recent.length ? `<div class="card" style="margin-top:16px"><div class="card-h">${I.clock} Πρόσφατες συνεδρίες</div>
    <div class="card-b" style="padding-top:4px">
      ${recent.map(s => `<div class="rbk-hrow">
        <span class="rbk-hdot" style="background:${s.billable ? '#1f9d57' : '#8291a9'}"></span>
        <div style="flex:1;min-width:0">
          <div class="rbk-hname">${esc(s.name)}</div>
          <div class="rbk-hmeta">${esc(tShort(s.startedAt))}${s.by ? ' · ' + esc(s.by) : ''}${s.note ? ' · ' + esc(s.note) : ''}</div>
        </div>
        <span class="su-chip" style="background:${s.billable ? '#1f9d5718' : '#8291a918'};color:${s.billable ? '#1f9d57' : '#8291a9'}">${fmtMin(s.minutes)}</span>
      </div>`).join('')}
    </div></div>` : ''}`;

  /* ── λίστα (φιλτραρίσιμη) ── */
  const paint = () => {
    const q = st.q.toLowerCase();
    const list = q ? rows.filter(r => (r.name || '').toLowerCase().includes(q)
      || (r.rustdesk_id || '').includes(q.replace(/\D/g, '')) && q.replace(/\D/g, '')
      || (r.label || '').toLowerCase().includes(q)) : rows;
    $('#rbGrid').innerHTML = list.length ? list.map(card).join('')
      : (rows.length ? `<div class="empty" style="padding:34px;grid-column:1/-1">Κανένα αποτέλεσμα για «${esc(st.q)}»</div>`
        : `<div class="empty rbk-empty" style="grid-column:1/-1"><div class="big">${I.monitor}</div>
           <b style="color:var(--ink);font-size:15px">Καμία αποθηκευμένη σύνδεση ακόμη</b>
           <div class="mut" style="font-size:12.5px;margin-top:6px;max-width:420px;line-height:1.6">
             Κάθε φορά που συνδέεσαι σε πελάτη (από ticket ή Πελάτη 360°) το RustDesk ID του αποθηκεύεται εδώ αυτόματα.
             Μπορείς και να το καταχωρήσεις μόνος σου.</div>
           <button class="btn btn-p" id="rbNew2" style="margin-top:14px">${I.plus} Πρόσθεσε σύνδεση</button></div>`);
    $$('[data-rbgo]').forEach(b => b.onclick = e => { e.stopPropagation(); startRemote(+b.dataset.rbgo, b.dataset.name, 0, {savedPeer: b.dataset.peer}); });
    $$('[data-rbcopy]').forEach(b => b.onclick = e => {
      e.stopPropagation();
      navigator.clipboard.writeText(b.dataset.rbcopy).then(() => toast('Το ID αντιγράφηκε'));
    });
    $$('[data-rbedit]').forEach(b => b.onclick = e => {
      e.stopPropagation();
      openForm(rows.find(r => r.clientid === +b.dataset.rbedit));
    });
    $$('[data-rbdel]').forEach(b => b.onclick = async e => {
      e.stopPropagation();
      const r = rows.find(x => x.clientid === +b.dataset.rbdel);
      if (!(await cnpConfirm(`Αφαίρεση της σύνδεσης «${r ? r.name : ''}» από τη λίστα;`, {danger: true, ok: 'Αφαίρεση'}))) return;
      await api('remote_save_peer', {client: +b.dataset.rbdel, peer: ''});
      toast('Αφαιρέθηκε'); R.remotebook();
    });
    const n2 = $('#rbNew2'); if (n2) n2.onclick = () => openForm(null);
  };

  /* ── inline φόρμα (νέα / επεξεργασία) ── */
  const openForm = (rec) => {
    st.edit = rec;
    $('#rbForm').innerHTML = `<div class="card rbk-form">
      <div class="card-h">${rec ? I.edit + ' Επεξεργασία σύνδεσης' : I.plus + ' Νέα σύνδεση'}</div>
      <div class="card-b">
        <div class="frow">
          <div><label>Πελάτης</label>
            ${rec ? `<input class="inp" value="${esc(rec.name)}" disabled>`
              : `<input class="inp" id="rbCli" list="rbCliL" placeholder="Όνομα ή email πελάτη…" autocomplete="off">
                 <datalist id="rbCliL"></datalist><input type="hidden" id="rbCliId">`}</div>
          <div><label>RustDesk ID</label>
            <input class="inp" id="rbPeer" placeholder="π.χ. 123 456 789" style="letter-spacing:1px" value="${esc(rbId(rec ? rec.rustdesk_id : ''))}"></div>
        </div>
        <div style="margin-top:10px"><label>Περιγραφή <span class="mut">(προαιρετικά — π.χ. «PC λογιστηρίου»)</span></label>
          <input class="inp" id="rbLabel" placeholder="Ποιο μηχάνημα είναι;" value="${esc(rec ? (rec.label || '') : '')}"></div>
        <div style="display:flex;gap:9px;margin-top:14px;justify-content:flex-end">
          <button class="btn btn-o" id="rbCancel">Άκυρο</button>
          <button class="btn btn-p" id="rbSave">${I.save} Αποθήκευση</button></div>
      </div></div>`;
    if (!rec && window.CNP.clientAuto) window.CNP.clientAuto('rbCli', 'rbCliL', 'rbCliId');
    setTimeout(() => { const f = $(rec ? '#rbPeer' : '#rbCli'); if (f) f.focus(); }, 30);
    $('#rbCancel').onclick = () => { $('#rbForm').innerHTML = ''; st.edit = null; };
    $('#rbSave').onclick = async () => {
      const cid = rec ? rec.clientid : +($('#rbCliId').value || 0);
      if (!cid) { toast('Διάλεξε πελάτη από τη λίστα', true); return; }
      const peer = $('#rbPeer').value.replace(/\D/g, '');
      if (peer.length < 6) { toast('Δώσε το RustDesk ID (9 ψηφία)', true); return; }
      const r = await api('remote_save_peer', {client: cid, peer, label: $('#rbLabel').value.trim()}).catch(e => ({err: e.message}));
      if (r.err) { toast(r.err, true); return; }
      toast(rec ? 'Αποθηκεύτηκε' : 'Η σύνδεση προστέθηκε');
      R.remotebook();
    };
  };

  paint();
  let qt;
  cnpSearch('rbQ', v => { st.q = v; paint(); }, 200);
  $('#rbNew').onclick = () => openForm(null);
  $('#rbSend').onclick = async () => {
    const em = await cnpPrompt('Σε ποιο email να σταλεί το πρόγραμμα «CloudOn Remote»;', {placeholder: 'email πελάτη', ok: 'Αποστολή'});
    if (!em) return;
    const r = await api('remote_send_client', {client: 0, email: em.trim()}).catch(e => ({err: e.message}));
    if (r.err) { toast(r.err, true); return; }
    toast('Στάλθηκε στο ' + r.sent);
  };
};

async function stopRemote() {
  if (!_remote) return;
  const mins = Math.max(1, Math.round((Date.now() - _remote.t0) / 60000));
  const ovl = document.createElement('div'); ovl.className = 'ovl show'; ovl.style.zIndex = 300;
  ovl.innerHTML = `<div class="pal-box" style="margin:20vh auto 0;max-width:440px" onclick="event.stopPropagation()">
    <div style="padding:20px 22px">
      <b style="font-size:15.5px;color:var(--ink)">⏹ Τέλος remote — ${esc(_remote.clientName)}</b>
      <div style="font-size:13px;margin-top:8px">Διάρκεια: <b>${mins}΄</b></div>
      <label style="display:flex;gap:6px;align-items:center;margin-top:10px;font-size:13px">
        <input type="checkbox" id="rsBill" checked>${I.coin} Χρεώσιμη (αφαιρείται από το πακέτο ωρών του πελάτη)</label>
      <input class="inp" id="rsNote" placeholder="Σημείωση για την αναφορά…" value="${esc(_remote.note || '')}" style="margin-top:10px">
      <div style="display:flex;gap:9px;margin-top:15px;justify-content:flex-end">
        <button class="btn btn-o" id="rsNo">Συνέχισε</button>
        <button class="btn btn-p" id="rsGo">Καταχώρηση</button></div>
    </div></div>`;
  document.body.appendChild(ovl);
  ovl.querySelector('#rsNo').onclick = () => ovl.remove();
  ovl.querySelector('#rsGo').onclick = async () => {
    const r = await api('remote_stop', {id: _remote.id, billable: ovl.querySelector('#rsBill').checked,
      note: ovl.querySelector('#rsNote').value}).catch(e => ({err: e.message}));
    if (r.err) { toast(r.err, true); return; }
    ovl.remove();
    toast(`⏹ Καταχωρήθηκε: ${r.minutes}΄` + (r.charged ? ` (χρέωση ${r.charged}΄)` : ' (χωρίς χρέωση)'));
    remoteRefresh();
  };
}

/* ═══ Rich-text editor — ΕΝΑ component για όλα τα πεδία κειμένου ═══
   Χρήση:  rteHtml('fDescr', htmlΉΚείμενο, 'placeholder…')  → markup
           rteVal('fDescr')                                  → το HTML για αποθήκευση
   Το wiring γίνεται με ΚΑΘΟΛΙΚΟ delegation (παρακάτω) — δεν χρειάζεται bind ανά χρήση,
   δουλεύει και σε drawers/modals που χτίζονται δυναμικά.
   ΠΡΟΣΟΧΗ: κάθε πεδίο που γίνεται RTE πρέπει (α) να περνά από cnp_clean_html στο api.php
   και (β) να εμφανίζεται ως HTML (όχι esc()) όπου προβάλλεται read-only. */
const _rteB = (cmd, label, title, arg) =>
  `<button type="button" class="rte-b" data-cmd="${cmd}"${arg ? ` data-arg="${arg}"` : ''} title="${title}">${label}</button>`;

/** Αν η τιμή είναι σκέτο κείμενο (χωρίς tags), τα newlines γίνονται <br> ώστε να μη χαθεί η μορφή. */
function _rteSeed(v) {
  const s = String(v == null ? '' : v);
  return /<(p|div|br|ul|ol|li|b|strong|i|em|u|h3|h4|blockquote|pre|code|span|a)\b/i.test(s)
    ? s : esc(s).replace(/\n/g, '<br>');
}

/* Η ΕΡΓΑΛΕΙΟΘΗΚΗ ΤΗΣ ΣΥΖΗΤΗΣΗΣ, ΜΙΑ ΦΟΡΑ. Ήταν γραμμένη μόνο μέσα στον συνθέτη
   νέου μηνύματος — οπότε όταν πήγαινες να ΔΙΟΡΘΩΣΕΙΣ ένα καταχωρημένο, έχανες
   έντονα, χρώματα και κουκκίδες. Ίδιο πεδίο, άλλες δυνατότητες. */
function actTbHtml() {
  return `<div class="act-tb rte-tb"><div class="rte-tools">
    ${_rteB('bold', '<b>B</b>', 'Έντονα (Ctrl+B)')}${_rteB('italic', '<i>I</i>', 'Πλάγια (Ctrl+I)')}${_rteB('underline', '<u>U</u>', 'Υπογράμμιση (Ctrl+U)')}
    <span class="rte-sep"></span>
    ${['#e2515f', '#e0a020', '#16a26a', '#0090dd', '#7b5cd6'].map(c => `<button type="button" class="rte-b rte-col" data-cmd="foreColor" data-arg="${c}" title="Χρώμα κειμένου"><span style="background:${c}"></span></button>`).join('')}
    <button type="button" class="rte-b rte-col" data-cmd="hiliteColor" data-arg="#fff3a3" title="Επισήμανση"><span style="background:#fff3a3;border:1px solid #e0c040"></span></button>
    <span class="rte-sep"></span>
    ${_rteB('insertUnorderedList', '&bull;', 'Κουκκίδες')}${_rteB('removeFormat', '✕', 'Καθαρισμός μορφοποίησης')}
  </div></div>`;
}

function rteHtml(id, value, placeholder, opts) {
  const o = opts || {};
  return `<div class="rte-wrap"${o.style ? ` style="${o.style}"` : ''}>
    <div class="rte-tb">
      <div class="rte-tools">
        ${_rteB('bold', '<b>B</b>', 'Έντονα (Ctrl+B)')}
        ${_rteB('italic', '<i>I</i>', 'Πλάγια (Ctrl+I)')}
        ${_rteB('underline', '<u>U</u>', 'Υπογράμμιση (Ctrl+U)')}
        <span class="rte-sep"></span>
        ${_rteB('insertUnorderedList', '&bull;&nbsp;<span class="rte-l">Λίστα</span>', 'Κουκκίδες')}
        ${_rteB('insertOrderedList', '1.&nbsp;<span class="rte-l">Λίστα</span>', 'Αρίθμηση')}
        <span class="rte-sep"></span>
        ${_rteB('formatBlock', 'H', 'Επικεφαλίδα', 'h3')}
        ${_rteB('formatBlock', '&ldquo;&rdquo;', 'Παράθεση', 'blockquote')}
        ${_rteB('__code', '&lt;/&gt;', 'Κώδικας')}
        <span class="rte-sep"></span>
        ${_rteB('__link', I.link, 'Σύνδεσμος')}
        ${_rteB('removeFormat', '✕', 'Καθαρισμός μορφοποίησης')}
      </div>
      ${_rteB('__ai', I.sparkle + ' <span class="rte-ai-l">Έλεγχος</span>', 'Ορθογραφικός & συντακτικός έλεγχος με AI')}
    </div>
    <div class="rte" id="${id}" contenteditable="true"${o.min ? ` style="min-height:${o.min}px"` : ''}
      data-ph="${esc(placeholder || '')}">${_rteSeed(value)}</div>
  </div>`;
}

/**
 * Εικόνες άρθρων που δεν φορτώνουν (σπασμένος σύνδεσμος στην ΠΗΓΗ) αντικαθίστανται
 * με διακριτική σήμανση — αλλιώς το width/height τους άφηνε τεράστιο κενό στη σελίδα.
 * Καθολικό: πιάνει και ό,τι ζωγραφίζεται αργότερα.
 */
document.addEventListener('error', e => {
  const img = e.target;
  if (!img || img.tagName !== 'IMG' || !img.closest('.kb-sol,.rt-view,.sol-html')) { return; }
  const span = document.createElement('span');
  span.className = 'kb-img-bad';
  span.textContent = '🖼 Η εικόνα δεν είναι διαθέσιμη στην πηγή';
  span.title = img.getAttribute('src') || '';
  img.replaceWith(span);
}, true);

/** Το περιεχόμενο ενός RTE (κενό → '' ώστε να μη σώζεται σκέτο <br>). */
function rteVal(id, root) {
  const el = (root || document).querySelector('#' + id);
  if (!el) { return ''; }
  const h = el.innerHTML.trim();
  return (h === '<br>' || h === '<div><br></div>' || el.textContent.trim() === '') ? '' : h;
}

/* Καθολικό wiring της μπάρας εργαλείων — ισχύει για ΚΑΘΕ .rte στη σελίδα.
   ΣΗΜΑΝΤΙΚΟ: capture phase (true). Τα modals (.pal-box) έχουν
   onclick="event.stopPropagation()" ώστε να μην κλείνει το overlay — που σημαίνει
   ότι στο bubble phase το κλικ ΔΕΝ φτάνει ποτέ στο document και η μπάρα ήταν νεκρή
   μέσα σε modal (βιβλιοθήκη, ταξινόμηση ticket, kbCapture). Το capture τρέχει πριν. */
/* ⚠️ ΚΡΙΣΙΜΟ: mousedown → preventDefault. Το <button> παίρνει focus με το mousedown και
   ΚΑΤΑΣΤΡΕΦΕΙ την επιλογή μέσα στο contenteditable — γι' αυτό «δεν εφάρμοζε τίποτα»
   (bold/underline/H σε επιλεγμένο κείμενο). Έτσι η επιλογή μένει άθικτη. */
document.addEventListener('mousedown', e => {
  if (e.target.closest && e.target.closest('.rte-b')) { e.preventDefault(); }
}, true);

document.addEventListener('click', e => {
  const b = e.target.closest && e.target.closest('.rte-b');
  if (!b) { return; }
  e.preventDefault();
  /* Ο ίδιος μηχανισμός για τον πλήρη editor (.rte-wrap > .rte) και για τη σύνθεση ενέργειας (.act-composer > .act-edit). */
  const wrap = b.closest('.rte-wrap, .act-composer');
  const ed = wrap ? wrap.querySelector('.rte, .act-edit') : null;
  if (!ed) { return; }
  const cmd = b.dataset.cmd;
  if (cmd === 'foreColor' || cmd === 'hiliteColor') { try { document.execCommand('styleWithCSS', false, true); } catch (e) {} }
  // αν χάθηκε η εστίαση (π.χ. tab/πρόγραμμα ανάγνωσης), επανέφερέ τη στον editor
  if (!ed.contains(document.activeElement) && document.activeElement !== ed) { ed.focus(); }
  if (cmd === '__ai') {
    rteProof(ed, b);
    return;
  }
  if (cmd === '__link') {
    const sel = getSelection();
    const saved = sel.rangeCount ? sel.getRangeAt(0).cloneRange() : null;
    cnpPrompt('Διεύθυνση συνδέσμου (URL):', {ok: 'Εισαγωγή', placeholder: 'https://…'}).then(u => {
      if (!u) { return; }
      ed.focus();
      if (saved) { const s2 = getSelection(); s2.removeAllRanges(); s2.addRange(saved); }
      document.execCommand('createLink', false, /^https?:|^mailto:/.test(u) ? u : 'https://' + u);
    });
    return;
  }
  if (cmd === '__code') {
    /* ΕΝΑΛΛΑΓΗ, όπως η επικεφαλίδα και η παράθεση. Πριν έμπαινε μόνο: πατούσες
       το κουμπί και ΟΛΟ το υπόλοιπο μήνυμα γραφόταν μέσα στο <pre>, χωρίς
       τρόπο να βγεις. Ένα κείμενο οδηγιών κατέληγε να φαίνεται ως κώδικας και
       ο χρήστης δεν καταλάβαινε γιατί. */
    const cur = (document.queryCommandValue('formatBlock') || '').toLowerCase().replace(/[<>]/g, '');
    document.execCommand('formatBlock', false, cur === 'pre' ? '<p>' : '<pre>');
    return;
  }
  if (cmd === 'formatBlock') {
    // toggle: αν είσαι ήδη σε h3/blockquote, γύρνα σε παράγραφο
    const tag = (b.dataset.arg || 'p').toLowerCase();
    const cur = (document.queryCommandValue('formatBlock') || '').toLowerCase().replace(/[<>]/g, '');
    document.execCommand('formatBlock', false, cur === tag ? '<p>' : '<' + tag + '>');
    return;
  }
  document.execCommand(cmd, false, b.dataset.arg || null);
}, true);
/** ✨ Ορθογραφικός/συντακτικός έλεγχος του editor — δείχνει ΤΙ αλλάζει πριν εφαρμοστεί. */
async function rteProof(ed, btn) {
  if (!ed.textContent.trim()) { toast('Γράψε πρώτα κείμενο', true); return; }
  const before = ed.innerHTML;
  const old = btn.innerHTML;
  btn.innerHTML = '<span class="rte-spin"></span>';
  btn.disabled = true;
  const r = await api('ai_proofread', {html: before, mode: 'fix'}).catch(e => ({err: e.message}));
  btn.innerHTML = old; btn.disabled = false;
  if (r.err) { toast(r.err, true); return; }
  if (r.clean) { toast('Κανένα λάθος — το κείμενο είναι σωστό'); return; }

  const rows = (r.changes || []).map(c => `<div class="pf-row">
      <span class="pf-from">${esc(c.from)}</span><span class="pf-arr">→</span><span class="pf-to">${esc(c.to)}</span>
      ${c.why ? `<span class="pf-why">${esc(c.why)}</span>` : ''}</div>`).join('');
  const ovl = document.createElement('div'); ovl.className = 'ovl show'; ovl.style.zIndex = 320;
  
  ovl.innerHTML = `<div class="pal-box pf-box" onclick="event.stopPropagation()">
    <div style="padding:18px 20px">
      <b style="font-size:15.5px;color:var(--ink);display:flex;align-items:center;gap:8px">${I.sparkle} Προτεινόμενες διορθώσεις</b>
      ${r.summary ? `<div class="mut" style="font-size:12.5px;margin-top:4px">${esc(r.summary)}</div>` : ''}
      <div class="pf-list">${rows || '<div class="mut">—</div>'}</div>
      <div class="pf-prev"><div class="mut" style="font-size:11px;font-weight:700;margin-bottom:5px">ΠΡΟΕΠΙΣΚΟΠΗΣΗ</div>
        <div class="rt-view">${r.html}</div></div>
      <div style="display:flex;gap:9px;margin-top:15px;justify-content:flex-end;flex-wrap:wrap">
        <button class="btn btn-o" id="pfNo">Άκυρο</button>
        <button class="btn btn-o" id="pfPolish">${I.sparkle} Και βελτίωση ύφους</button>
        <button class="btn btn-p" id="pfYes">Εφαρμογή</button></div>
    </div></div>`;
  document.body.appendChild(ovl);
  ovl.querySelector('#pfNo').onclick = () => ovl.remove();
  ovl.querySelector('#pfYes').onclick = () => {
    ed.innerHTML = r.html;
    ovl.remove();
    toast('Οι διορθώσεις εφαρμόστηκαν — μην ξεχάσεις Αποθήκευση');
  };
  ovl.querySelector('#pfPolish').onclick = async () => {
    const btn2 = ovl.querySelector('#pfPolish');
    btn2.innerHTML = '<span class="rte-spin"></span>'; btn2.disabled = true;
    const r2 = await api('ai_proofread', {html: before, mode: 'polish'}).catch(e => ({err: e.message}));
    if (r2.err) { toast(r2.err, true); btn2.disabled = false; return; }
    ed.innerHTML = r2.html;
    ovl.remove();
    toast('Το κείμενο βελτιώθηκε — μην ξεχάσεις Αποθήκευση');
  };
}

/**
 * Ανεβάζει εικόνα του προχείρου και τη βάζει ΜΕΣΑ στο κείμενο.
 * Κοινό για όλα τα πεδία πλούσιου κειμένου (ζητούμενο, ενέργειες, σημειώσεις).
 * Κρατάμε τη θέση του δρομέα πριν το ανέβασμα και την επαναφέρουμε μετά — αλλιώς
 * η εικόνα θα προσγειωνόταν στην αρχή ή έξω από το πεδίο.
 */
async function cnpPasteImage(el, file) {
  if (!file) { return false; }
  const sel = window.getSelection();
  const range = sel && sel.rangeCount ? sel.getRangeAt(0).cloneRange() : null;
  toast('Ανέβασμα εικόνας…');
  const fd = new FormData();
  fd.append('module', 'task'); fd.append('ref_type', 'rte'); fd.append('ref_id', '0');
  fd.append('file', file);
  const r = await fetch('api.php?a=file_upload', {method: 'POST', body: fd, credentials: 'same-origin'})
    .then(x => x.json()).catch(() => null);
  if (!r || !r.file) { toast('Η εικόνα δεν ανέβηκε', true); return false; }
  el.focus();
  if (range) { sel.removeAllRanges(); sel.addRange(range); }
  document.execCommand('insertHTML', false,
    `<img src="api.php?a=file_get&id=${r.file.id}" alt="${esc(r.file.name || '')}">`);
  if (window.CNP_markDirty) { window.CNP_markDirty(el); }
  return true;
}
/** Η εικόνα μέσα σε ένα paste event, αν υπάρχει. */
function cnpClipImage(e) {
  const items = [...(((e.clipboardData || window.clipboardData) || {}).items || [])];
  const it = items.find(x => x.type && x.type.startsWith('image/'));
  return it ? it.getAsFile() : null;
}

/* Επικόλληση σε πεδίο πλούσιου κειμένου:
   — εικόνα → ανεβαίνει και μπαίνει στη ροή (ίδια συμπεριφορά με τις Ενέργειες)
   — κείμενο → ΠΑΝΤΑ χωρίς μορφοποίηση από Word/σελίδες (αλλιώς μπαίνουν styles/fonts) */
document.addEventListener('paste', e => {
  const ed = e.target.closest && e.target.closest('.rte');
  if (!ed) { return; }
  const img = cnpClipImage(e);
  if (img) { e.preventDefault(); cnpPasteImage(ed, img); return; }
  e.preventDefault();
  const t = (e.clipboardData || window.clipboardData).getData('text/plain');
  document.execCommand('insertText', false, t);
}, true);

/* ═══ ΚΕΙΜΕΝΟ ΜΗΝΥΜΑΤΟΣ → ΠΑΤΗΣΙΜΟ ΠΕΡΙΕΧΟΜΕΝΟ ════════════════════════════════
   Ένα link προς εργασία μέσα σε μήνυμα δεν έχει νόημα ως κείμενο: ο συνάδελφος
   θέλει να ΤΟ ΑΝΟΙΞΕΙ, όχι να αντιγράψει URL και να αλλάξει παράθυρο. Ό,τι
   δείχνει μέσα στην εφαρμογή ανοίγει ΕΔΩ· ό,τι είναι απ' έξω ανοίγει σε νέα
   καρτέλα. Γράφουμε μόνο <b> και <a> — το υπόλοιπο μένει escaped. */
function cnpMsgHtml(text) {
  const raw = String(text || '');
  let h = esc(raw);
  /* **έντονα** — έτσι τα στέλνει το «Στείλε εργασία» */
  h = h.replace(/\*\*([^*\n]{1,200})\*\*/g, (m, x) => '<b>' + x + '</b>');
  /* Σύνδεσμοι. Το esc έχει ήδη κάνει & → &amp;, γι' αυτό το βλέπουμε κι έτσι. */
  h = h.replace(/(https?:\/\/[^\s<]+[^\s<.,;:!?)\]}"'])|(?:^|\s)(#\/\w+(?:\/\d+)?)/g, (m, url, hash) => {
    const pre = url ? '' : m.slice(0, m.length - hash.length);
    const u = url || hash;
    const inApp = /\/project(?:management)?\/?#\//.test(u) || /^#\//.test(u);
    if (inApp) {
      const r = /#\/(\w+)(?:\/(\d+))?/.exec(u);
      if (r) {
        const view = r[1], id = r[2] || '';
        const label = view === 'task' && id ? 'Άνοιγμα εργασίας #' + id
          : view === 'task' ? 'Άνοιγμα εργασίας'
          : 'Άνοιγμα: ' + view;
        return pre + `<a class="msg-go" href="${u}" data-view="${view}" data-id="${id}">`
          + I.link + ' ' + label + '</a>';
      }
    }
    return pre + `<a href="${u}" target="_blank" rel="noopener noreferrer">${u.replace(/^https?:\/\//, '')}</a>`;
  });
  return h.replace(/\n/g, '<br>');
}
/** Δένει τα εσωτερικά links ενός κόμβου ώστε να ανοίγουν ΜΕΣΑ στην εφαρμογή. */
function cnpWireMsgLinks(root) {
  (root || document).querySelectorAll('a.msg-go:not([data-wired])').forEach(a => {
    a.dataset.wired = '1';
    a.onclick = e => {
      e.preventDefault();
      const view = a.dataset.view, id = +a.dataset.id || 0;
      if (view === 'task' && id) { openTask(id); return; }
      go(view, id || undefined);
    };
  });
}

/** Το ψηλότερο z-index ανοιχτού overlay — ώστε το επόμενο να μπει από πάνω. */
function cnpTopZ() {
  let z = 300;
  document.querySelectorAll('.ovl, .drawer').forEach(el => {
    const v = parseInt(getComputedStyle(el).zIndex, 10);
    if (!isNaN(v) && v > z) { z = v; }
  });
  return z;
}

/* ═══ In-app διαλογικά (αντί για browser confirm/prompt) ═══ */
/* ═══════════ ΓΡΑΜΜΗ ΦΙΛΤΡΩΝ — κοινός τρόπος για όλες τις οθόνες ═══════════
   Ένα φίλτρο = ένα κουμπάκι με ετικέτα και τιμή. Όσα χρειάζονται πάντα είναι
   μόνιμα· τα υπόλοιπα μπαίνουν προοδευτικά με το «+ φίλτρο», ΣΤΗΝ ΙΔΙΑ γραμμή,
   και βγαίνουν με το ✕ τους. Έτσι η οθόνη δεν ξεκινά με δέκα άδεια πεδία. */
const fSel = (key, opts, val) => `<select class="fchip-s" data-fk="${key}">${opts.map(([v, l]) =>
  `<option value="${v}" ${String(val) === String(v) ? 'selected' : ''}>${esc(l)}</option>`).join('')}</select>`;

const fChip = (label, inner, on, rm) =>
  `<label class="fchip${on ? ' on' : ''}"><span class="fchip-l">${esc(label)}</span>${inner}${
    rm ? `<span class="fchip-x" data-fx="${rm}" title="Αφαίρεση">✕</span>` : ''}</label>`;

/* Φίλτρο «ναι/όχι» (π.χ. «μόνο ανοιχτά»). Δεν έχει ετικέτα+τιμή σαν τα άλλα:
   ΕΙΝΑΙ η τιμή του. Γι' αυτό είναι κουμπί που ανάβει, όχι κουτάκι με λεζάντα —
   ένα checkbox μέσα σε κουμπάκι διαβαζόταν σαν δεύτερη φόρμα. */
const fBool = (key, label, on, rm) =>
  `<button type="button" class="fchip fchip-b${on ? ' on' : ''}" data-fb="${key}">${
    on ? '✓ ' : ''}${esc(label)}${rm ? `<span class="fchip-x" data-fx="${rm}" title="Αφαίρεση">✕</span>` : ''}</button>`;

/* Χτίζει ΜΟΝΟ του το κουμπάκι ενός πρόσθετου φίλτρου από τον ορισμό του, ώστε
   κάθε οθόνη να μη γράφει ξανά την ίδια τριάδα if. */
const fOne = (k, F, st, d) => F.bool ? fBool(k, F.label, !!st[k], k)
  : fChip(F.label,
      F.num ? `<input type="number" class="fchip-s" data-fk="${k}" min="0" max="${F.max || 600}" value="${st[k] || ''}" style="width:52px">${
                F.unit ? `<span class="fchip-u">${esc(F.unit)}</span>` : ''}`
      : F.text ? `<input class="fchip-s" ${F.id ? `id="${F.id}"` : ''} data-fk="${k}" value="${esc(st[k] || '')}" placeholder="${esc(F.ph || '')}" style="width:${F.w || 190}px">`
      : fSel(k, F.opts(d), st[k]), !!st[k], k);

/* Το «+ φίλτρο» δείχνει ΜΟΝΟ όσα δεν είναι ήδη στη γραμμή. Όταν δεν μένει
   κανένα, εξαφανίζεται — κουμπί που δεν κάνει τίποτα είναι θόρυβος. */
const fAdd = (defs, shown) => {
  const left = Object.entries(defs).filter(([k]) => !shown.includes(k));
  return left.length ? `<div class="fadd"><button class="fchip fchip-add" data-fadd-btn>+ φίλτρο</button>
    <div class="fmenu" data-fmenu hidden>${left.map(([k, v]) =>
      `<button data-fadd="${k}">${esc(v.label)}</button>`).join('')}</div></div>` : '';
};

/* Δένει τα κουμπάκια μιας γραμμής με την κατάσταση και ξαναζωγραφίζει. */
const fWire = (st, defs, redraw) => {
  const ad = $('[data-fadd-btn]'), mn = $('[data-fmenu]');
  if (ad && mn) {
    ad.onclick = e => { e.preventDefault(); e.stopPropagation(); mn.hidden = !mn.hidden; };
    document.addEventListener('click', () => { mn.hidden = true; }, {once: true});
    $$('[data-fadd]', mn).forEach(b => b.onclick = () => { st.shown.push(b.dataset.fadd); redraw(); });
  }
  $$('[data-fk]').forEach(el => el.onchange = () => {
    const k = el.dataset.fk;
    st[k] = el.type === 'number' ? Math.max(0, +el.value || 0) : el.value;
    if (defs[k] && defs[k].num) { st[k] = Math.max(0, +el.value || 0); }
    redraw();
  });
  $$('[data-fb]').forEach(el => el.onclick = e => {
    if (e.target.dataset.fx) { return; }          // το ✕ έχει δική του δουλειά
    const k = el.dataset.fb;
    st[k] = st[k] ? 0 : 1;
    redraw();
  });
  $$('[data-fx]').forEach(el => el.onclick = e => {
    e.preventDefault(); e.stopPropagation();
    const k = el.dataset.fx;
    st.shown = st.shown.filter(x => x !== k);
    st[k] = (defs[k] && (defs[k].num || defs[k].bool)) ? 0 : '';
    redraw();
  });
};

/**
 * Πεδίο αναζήτησης που ΔΕΝ χάνει τον κέρσορα.
 *
 * Το πρόβλημα: κάθε γράμμα ξαναέγραφε ολόκληρη την οθόνη. Το παλιό <input>
 * καταστρεφόταν μαζί με τα υπόλοιπα, οπότε η εστίαση έφευγε και το επόμενο
 * γράμμα πήγαινε στο κενό — έπρεπε να ξανακλικάρεις μετά από κάθε πληκτρολόγηση.
 *
 * Εδώ κρατάμε ό,τι χρειάζεται ΠΡΙΝ το ξαναγράψιμο (κείμενο, θέση δρομέα, τυχόν
 * επιλεγμένο κομμάτι) και το επαναφέρουμε μετά, βρίσκοντας το ΝΕΟ πεδίο με το
 * ίδιο id. Αν ο χρήστης συνέχισε να γράφει όσο γινόταν το ξαναγράψιμο, κρατάει
 * ό,τι έγραψε — δεν του «γυρίζει» την οθόνη πίσω.
 *
 * @param id     το id του πεδίου, χωρίς δίεση
 * @param apply  τι κάνουμε με το κείμενο (συνήθως: κράτα το και ξαναζωγράφισε)
 * @param ms     πόσο περιμένουμε να σταματήσει να γράφει
 */
cnpSearch._on = 0;                 // πόσα φιλτραρίσματα τρέχουν αυτή τη στιγμή
function cnpSearch(id, apply, ms = 280) {
  const el = $('#' + id);
  if (!el) { return; }
  let t = null, last = el.value, pos = el.selectionStart;
  el.oninput = () => {
    last = el.value;
    pos = el.selectionStart;
    clearTimeout(t);
    t = setTimeout(async () => {
      const typed = last;
      cnpSearch._on++;
      try { await apply(typed.trim(), typed); }
      catch (e) { /* το φιλτράρισμα δεν ρίχνει την οθόνη */ }
      finally { cnpSearch._on--; }
      const n = $('#' + id);
      if (!n) { return; }
      /* Ο χρήστης μπορεί να έγραψε κι άλλο όσο τρέχαμε — προτεραιότητα ΠΑΝΤΑ σε
         αυτόν: κρατάμε το ΤΕΛΕΥΤΑΙΟ κείμενο (last), όχι αυτό που είχε όταν
         ξεκίνησε το φιλτράρισμα. Αλλιώς του «έτρωγε» γράμματα. */
      if (n.value !== last) { n.value = last; }
      if (document.activeElement !== n) { n.focus({preventScroll: true}); }
      try { n.setSelectionRange(pos, pos); } catch (e) { }
    }, ms);
  };
}

/**
 * Σκελετός φόρτωσης — ΕΚΤΟΣ αν ο χρήστης πληκτρολογεί σε αναζήτηση.
 *
 * Το «σβήνω τα πάντα και βάζω γκρι πλαίσια» είναι σωστό όταν μπαίνεις σε μια
 * οθόνη. Όταν όμως γράφεις στο πεδίο αναζήτησης, παίρνει μαζί του και το ΙΔΙΟ
 * το πεδίο: η οθόνη «αναβοσβήνει» και όσα πλήκτρα πατήθηκαν όσο έλειπε πήγαν
 * στο κενό. Σε αυτή την περίπτωση η παλιά οθόνη μένει στη θέση της μέχρι να
 * είναι έτοιμη η καινούργια.
 */
function cnpSkel(el, html) { if (el && !cnpSearch._on) { el.innerHTML = html; } }

function cnpDialog(opts) {
  return new Promise(resolve => {
    const o = Object.assign({title: '', body: '', ok: 'OK', cancel: 'Άκυρο', input: null, danger: false}, opts);
    const ovl = document.createElement('div');
    ovl.className = 'ovl show';
    /* Ένας διάλογος επιβεβαίωσης πρέπει ΠΑΝΤΑ να κάθεται πάνω από αυτό που τον
       κάλεσε. Με σταθερό z-index 300 άνοιγε πίσω από παράθυρα με μεγαλύτερο
       (π.χ. η ουρά εγκρίσεων στο 320) και έμοιαζε να μην ανταποκρίνεται. */
    ovl.style.zIndex = cnpTopZ() + 10;
    /* noClose: ερώτηση που ΠΡΕΠΕΙ να απαντηθεί — χωρίς ✕, χωρίς ESC, χωρίς κλικ
       έξω. Χρειάζεται όπου το «έκλεισα το παράθυρο» θα παρέκαμπτε κανόνα (π.χ.
       η ερώτηση «ξεκινάς τον χρόνο;»: ο ✕ άφηνε την καρτέλα ξεκλείδωτη). */
    ovl.innerHTML = `<div class="pal-box" style="margin:22vh auto 0;max-width:440px" role="dialog"
      data-cnp-dlg="1"${o.noClose ? ' data-noclose="1"' : ''}>
      <div style="padding:20px 22px 18px">
        ${o.title ? `<b style="font-size:15.5px;color:var(--ink)">${o.title}</b>` : ''}
        ${o.body ? `<div style="font-size:13px;color:var(--txt);margin-top:8px;white-space:pre-wrap;max-height:46vh;overflow:auto">${o.body}</div>` : ''}
        ${o.input !== null ? (o.rows
          ? `<textarea class="inp" id="cnpDlgIn" rows="${+o.rows}" maxlength="${+o.max || 2000}" placeholder="${esc(o.placeholder || '')}" style="margin-top:12px;width:100%;resize:vertical">${esc(o.input || '')}</textarea>`
          : `<input class="inp" type="${o.inputType || 'text'}" id="cnpDlgIn" placeholder="${esc(o.placeholder || '')}" value="${esc(o.input || '')}" style="margin-top:12px">`) : ''}
        ${o.hint ? `<div class="mut" style="font-size:11.5px;margin-top:7px">${o.hint}</div>` : ''}
        <div style="display:flex;gap:9px;margin-top:16px;justify-content:flex-end;flex-wrap:wrap">
          ${o.cancel === null ? '' : `<button class="btn btn-o" id="cnpDlgNo">${o.cancel}</button>`}
          ${o.third ? `<button class="btn btn-o" id="cnpDlgTh"${o.thirdPlain ? '' : ' style="color:var(--bad)"'}>${o.third}</button>` : ''}
          <button class="btn ${o.danger ? '' : 'btn-p'}" id="cnpDlgOk" style="${o.danger ? 'background:var(--bad);color:#fff' : ''}">${o.ok}</button>
        </div>
      </div></div>`;
    document.body.appendChild(ovl);
    const inp = ovl.querySelector('#cnpDlgIn');
    const done = v => { ovl.remove(); document.removeEventListener('keydown', onKey); resolve(v); };
    const ok = () => done(o.input !== null ? (inp ? inp.value : '') : true);
    const onKey = e => {
      if (e.key === 'Escape' && !o.noClose) { e.stopPropagation(); done(o.input !== null ? null : false); }
      if (e.key === 'Escape' && o.noClose) { e.stopPropagation(); e.preventDefault(); }
      if (e.key === 'Enter' && (!inp || document.activeElement === inp)) {
        if (o.rows && !(e.ctrlKey || e.metaKey)) { return; }   // πολυγραμμικό: Enter = νέα γραμμή
        e.preventDefault(); ok();
      }
    };
    document.addEventListener('keydown', onKey);
    ovl.querySelector('#cnpDlgOk').onclick = ok;
    { const nb = ovl.querySelector('#cnpDlgNo'); if (nb) { nb.onclick = () => done(o.input !== null ? null : false); } }
    const th = ovl.querySelector('#cnpDlgTh');
    if (th) { th.onclick = () => done('third'); }
    // ΟΧΙ κλείσιμο με κλικ έξω — μόνο από τα κουμπιά ή ESC
    setTimeout(() => (inp || ovl.querySelector('#cnpDlgOk')).focus(), 30);
  });
}
/**
 * Ο ΚΑΝΟΝΑΣ ΤΗΣ ΜΠΑΛΑΣ — δίδυμο του `cnp_scope_mine` του server.
 *
 * Μια εργασία είναι ΔΙΚΗ ΜΟΥ όταν έχω τη μπάλα, ή όταν είναι δική μου ΚΑΙ η
 * μπάλα δεν έχει δοθεί σε κανέναν. Ανάθεση σε εμένα ΔΕΝ σημαίνει ότι περιμένει
 * εμένα: όταν η εργασία πήγε «Προς τιμολόγηση» και περιμένει το λογιστήριο, η
 * δική μου δουλειά τελείωσε.
 *
 * ΓΙΑΤΙ ΕΔΩ: ο server είχε τον κανόνα, η οθόνη όχι — και το φίλτρο «μόνο δικά
 * μου» στα «Όλα τα tasks» κοίταζε σκέτο τον ανάδοχο. Αποτέλεσμα: η ίδια εργασία
 * φαινόταν ταυτόχρονα σε δύο ανθρώπους, και κανείς δεν ήξερε ποιος την κρατά.
 * Μία εργασία, ΕΝΑΣ άνθρωπος κάθε φορά.
 */
/**
 * ΠΟΙΟΣ ΚΡΑΤΑΕΙ ΤΗΝ ΕΡΓΑΣΙΑ — ο ίδιος κανόνας με το cnpIsMine, σε μορφή «ποιος».
 *
 * Κάθε οθόνη που δείχνει ή ομαδοποιεί «ανά χειριστή» περνά από εδώ. Η Λίστα
 * tasks ομαδοποιούσε με την ΑΝΑΘΕΣΗ, οπότε ο Βάκρινος έβλεπε κάτω από το όνομά
 * του εργασίες που τις τρέχει άλλος — ενώ το «Πλάνο μου» τις είχε ήδη πάψει να
 * δείχνει. Δύο οθόνες, δύο απαντήσεις για το ίδιο πράγμα.
 */
function cnpHolder(t) { return +(t.ball || 0) || +(t.assignee || 0) || 0; }

function cnpIsMine(t, meId) {
  const me = meId || (S.boot && S.boot.me && S.boot.me.id);
  const ball = +(t.ball || 0);
  if (ball) { return ball === me; }
  return +(t.assignee || 0) === me;
}

const cnpConfirm = (body, opts) => cnpDialog(Object.assign({title: 'Επιβεβαίωση', body, ok: 'Ναι', cancel: 'Όχι'}, opts));
const cnpPrompt = (body, opts) => cnpDialog(Object.assign({title: '', body, input: '', ok: 'OK'}, opts));

function crmTabs(act) {
  const tabs = [['crm', I.funnel, 'Funnel'], ['crmov', I.chart, 'Επισκόπηση'], ['contacts', I.users, 'Επαφές'], ['comms', I.phone, 'Επικοινωνίες'], ['campaigns', I.megaphone, 'Καμπάνιες']];
  if (S.boot.me.full) { tabs.push(['targets', I.target, 'Στόχοι προϊόντων'], ['reports', I.chart, 'Reports'], ['crmdata', I.save, 'Import/Export']); }
  // κινητό: dropdown αντί για 3 σειρές pills (ίδιο μοτίβο με τις Ρυθμίσεις)
  return `<select class="inp set-subsel" id="crmSubSel" aria-label="Ενότητα CRM">
      ${tabs.map(([k, , l]) => `<option value="${k}" ${act === k ? 'selected' : ''}>${l}</option>`).join('')}</select>
    <div class="ib-tabs set-subtabs" style="margin-bottom:16px;flex-wrap:wrap;border:0;background:0">
    ${tabs.map(([k, ic, l]) => `<button class="ib-tab ${act === k ? 'on' : ''}" data-crmtab="${k}"><span class="tico">${ic}</span>${l}</button>`).join('')}</div>`;
}
// το select του κινητού (delegation — το markup ξαναχτίζεται σε κάθε render)
document.addEventListener('change', e => {
  const s = e.target.closest('#crmSubSel');
  if (!s) { return; }
  go(s.value);
  $$('.sitem').forEach(b => b.classList.toggle('on', b.dataset.nav === 'crm'));
});
document.addEventListener('click', e => {
  if (e.target.closest('[data-profile]')) { go('profile'); $$('.sitem').forEach(b => b.classList.remove('on')); return; }
  const ct = e.target.closest('[data-crmtab]');
  if (ct) {
    go(ct.dataset.crmtab);
    $$('.sitem').forEach(b => b.classList.toggle('on', b.dataset.nav === 'crm'));  // CRM section μένει αναμμένο
    return;
  }
  const a = e.target.closest('[data-ibgo]');
  if (a) { e.preventDefault(); closeDrawer(); go('inbox', +a.dataset.ibgo); }
  const c3 = e.target.closest('[data-c360]');
  if (c3) { e.preventDefault(); closeDrawer(); go('client360', +c3.dataset.c360); }
});
/* ── Ημερομηνίες πάντα ηη/μμ/εεεε ──────────────────────────────────────────
   Το native input[type=date] το ζωγραφίζει ο browser και ακολουθεί ΤΗ ΓΛΩΣΣΑ
   ΤΟΥ BROWSER — ούτε το <html lang> ούτε τίποτα δικό μας το αλλάζει (το
   επιβεβαιώσαμε: με locale el-GR έδειχνε πάλι mm/dd/yyyy).

   Λύση: κρατάμε το ίδιο το input (άρα μένουν το ημερολόγιο, η ρόδα στο κινητό
   και το .value σε ISO — καμία αλλαγή στον υπόλοιπο κώδικα), κάνουμε το κείμενό
   του διαφανές και ζωγραφίζουμε από πάνω τη δική μας μορφή. */
function cnpDateSkin(inp) {
  if (inp.dataset.cnpDs) { return; }
  inp.dataset.cnpDs = '1';
  const wrap = document.createElement('span');
  wrap.className = 'cnp-dw';
  inp.parentNode.insertBefore(wrap, inp);
  wrap.appendChild(inp);
  const lbl = document.createElement('span');
  lbl.className = 'cnp-dt';
  wrap.appendChild(lbl);
  const paint = () => {
    const v = inp.value;
    lbl.textContent = v ? dFull(v) : 'ηη/μμ/εεεε';
    lbl.classList.toggle('empty', !v);
  };
  inp.addEventListener('input', paint);
  inp.addEventListener('change', paint);
  inp.addEventListener('blur', paint);
  /* Ο κώδικας αλλάζει τιμές και προγραμματιστικά (π.χ. «σε 1 εβδομάδα»), οπότε
     δεν αρκούν τα events του χρήστη. */
  wrap._cnpPaint = paint;
  paint();
}
/* ΠΡΟΣΟΧΗ: η ζωγραφική της ένδειξης είναι κι αυτή αλλαγή στο DOM. Αν ο
   observer ζωγράφιζε σε κάθε μεταβολή, θα αυτοτροφοδοτούνταν σε ατέρμονο
   βρόχο. Γι' αυτό εδώ γίνεται ΜΟΝΟ ντύσιμο των καινούριων πεδίων. */
function cnpDatesScan() {
  const fresh = document.querySelectorAll('input[type=date]:not([data-cnp-ds])');
  if (!fresh.length) { return; }
  fresh.forEach(cnpDateSkin);
}
let cnpDsQueued = false;
new MutationObserver(() => {
  if (cnpDsQueued) { return; }
  cnpDsQueued = true;
  requestAnimationFrame(() => { cnpDsQueued = false; cnpDatesScan(); });
}).observe(document.documentElement, {childList: true, subtree: true});
document.addEventListener('DOMContentLoaded', cnpDatesScan);
setTimeout(cnpDatesScan, 300);

/* Όταν ο κώδικας βάζει τιμή μόνος του (π.χ. «σε 1 εβδομάδα»), το input δεν
   στέλνει event — το κάνουμε εμείς, ώστε να ενημερωθεί η ένδειξη. */
function cnpSetDate(inp, iso) {
  if (!inp) { return; }
  inp.value = iso || '';
  inp.dispatchEvent(new Event('change'));
}

function setTop(t, sub) {
  /* Ο υπότιτλος έτρωγε τη μισή μπάρα (21/9/2026): έμεινε μόνο ως επεξήγηση στον τίτλο, ώστε η
     αναζήτηση και οι δείκτες να πιάνουν από αριστερά όλο τον χώρο. */
  const el = $('#topTitle');
  el.textContent = t;
  el.title = sub || '';
  $('#topSub').textContent = '';
}
/* Κινητό: η γραμμή φίλτρων (.fbar) έπιανε 3-4 σειρές. Μένει η αναζήτηση + κουμπί «Φίλτρα» που
   ανοίγει τα υπόλοιπα. Δένεται αυτόματα σε κάθε οθόνη που τη σχεδιάζει (MutationObserver). */
function cnpFbarMobile() {
  if (!matchMedia('(max-width:768px)').matches) { return; }
  document.querySelectorAll('#content .fbar:not([data-mob])').forEach(bar => {
    bar.dataset.mob = '1';
    const kids = [...bar.children].filter(el => !el.classList.contains('fbar-sp'));
    if (kids.length < 3) { return; }
    const first = kids[0];
    const rest = kids.slice(1);
    const go2 = rest.find(el => el.classList.contains('fchip-go'));
    const active = rest.filter(el => el.classList.contains('on') || (el.querySelector && el.querySelector('.fchip.on, .on'))).length;
    const btn = document.createElement('button');
    btn.type = 'button'; btn.className = 'fchip fbar-toggle'; btn.innerHTML = I.funnel + ' Φίλτρα' + (active ? ' <b>' + active + '</b>' : '');
    first.after(btn);
    rest.forEach(el => { if (el !== go2) { el.classList.add('fbar-hide'); } });
    btn.onclick = () => { const open = bar.classList.toggle('open'); btn.classList.toggle('on', open); };
  });
}
new MutationObserver(() => cnpFbarMobile()).observe(document.body, {childList: true, subtree: true});
function go(view, arg) {
  S.view = view;
  S.viewArg = arg ? String(arg) : '';
  location.hash = '#/' + view + (arg ? '/' + arg : '');
  $$('.sitem').forEach(b => b.classList.toggle('on', b.dataset.nav === view));
  $$('#tabBar [data-tab]').forEach(b => {
    const on = b.dataset.tab === view;
    b.classList.toggle('on', on);
    if (on && matchMedia('(max-width:768px)').matches) {   // φέρε το ενεργό tab στο κέντρο
      try { b.scrollIntoView({inline: 'center', block: 'nearest', behavior: 'smooth'}); } catch (e) { }
    }
  });
  document.body.classList.remove('detail-open');   // νέα οθόνη → επαναφορά tab bar
  const c = $('#content'); c.classList.remove('enter'); void c.offsetWidth; c.classList.add('enter');
  c.scrollTop = 0;   // νέα οθόνη → ξεκίνα από την κορυφή (όπως σε native app)
  /* Deep link ή bookmark σε οθόνη χωρίς δικαίωμα: δείξε καθαρό μήνυμα αντί να
     σκάσει ανεπιτήρητο σφάλμα. */
  try {
    const r = ((window.R && window.R[view]) || vMyDay)(arg);
    if (r && typeof r.catch === 'function') {
      r.catch(err => {
        const msg = String((err && err.message) || '');
        c.innerHTML = /πρόσβαση|forbidden|perm/i.test(msg)
          ? `<div class="empty"><div class="big">${I.lock}</div>${esc(msg)}
              <div class="mut" style="font-size:12.5px;margin-top:8px">Επίλεξε άλλη οθόνη από το μενού.</div></div>`
          : `<div class="empty"><div class="big">⚠️</div>Κάτι πήγε στραβά${msg ? ': ' + esc(msg) : ''}.</div>`;
      });
    }
  } catch (e) {
    c.innerHTML = `<div class="empty"><div class="big">⚠️</div>Κάτι πήγε στραβά.</div>`;
  }
  if (typeof loadTopStats === 'function') { loadTopStats(); }
}
window.R = window.R || {};
Object.assign(window.R, {get board() { return vBoard; }, get myday() { return vMyDay; },
  get crm() { return vCrm; }, get kpi() { return vKpi; }});

/* ───────── generic pointer drag&drop ───────── */
function dnd(cardSel, colSel, onDrop, onClick) {
  let down = null, ghost = null, dragEl = null;
  document.addEventListener('pointerdown', e => {
    const c = e.target.closest(cardSel); if (!c || e.button !== 0) return;
    if (e.target.closest('input,button,select,a,textarea')) return;
    down = {x: e.clientX, y: e.clientY, el: c};
  });
  document.addEventListener('pointermove', e => {
    if (!down) return;
    if (!ghost && Math.hypot(e.clientX - down.x, e.clientY - down.y) > 6) {
      dragEl = down.el; dragEl.classList.add('drag');
      ghost = dragEl.cloneNode(true); ghost.classList.add('ghost'); ghost.classList.remove('drag');
      document.body.appendChild(ghost);
    }
    if (ghost) {
      ghost.style.left = (e.clientX - 120) + 'px'; ghost.style.top = (e.clientY - 20) + 'px';
      $$(colSel).forEach(col => col.classList.toggle('over', col.contains(document.elementFromPoint(e.clientX, e.clientY))));
    }
  });
  document.addEventListener('pointerup', e => {
    if (!down) return;
    const wasDrag = !!ghost;
    if (ghost) {
      ghost.remove(); ghost = null;
      const col = $$(colSel).find(c => c.classList.contains('over'));
      $$(colSel).forEach(c => c.classList.remove('over'));
      dragEl.classList.remove('drag');
      if (col && !col.contains(dragEl)) onDrop(dragEl, col);
      dragEl = null;
    } else if (onClick) onClick(down.el);
    down = null;
  });
}

/* ═════════ BOARD ═════════ */
async function vBoard(arg) {
  if (arg) S.project = +arg;
  /* Το έργο μπορεί να έχει διαγραφεί ή να μην είναι ορατό: μην επιμένεις σε
     id που δεν υπάρχει στη λίστα — το API θα απαντούσε 403/404. */
  if (S.project && !S.boot.projects.some(p => p.id === +S.project)) { S.project = 0; }
  if (!S.project && S.boot.projects[0]) S.project = S.boot.projects[0].id;
  setTop('Board');
  const c = $('#content');
  if (!S.boot.projects.length) {
    c.innerHTML = `<div class="empty"><div class="big">${I.folder}</div>Δεν έχεις πρόσβαση σε κανένα έργο.
      <div class="mut" style="font-size:12.5px;margin-top:8px;max-width:460px;margin-inline:auto;line-height:1.7">
        Το board δείχνει έργα πελατών. Οι εργασίες που δεν ανήκουν σε έργο (π.χ. από tickets)
        βρίσκονται στα <a href="#/units">Departments</a> και στη <a href="#/list">Λίστα tasks</a>.</div></div>`;
    return;
  }
  c.innerHTML = `<div style="display:flex;gap:10px;margin-bottom:16px;align-items:center">
    <select class="inp" id="projSel" style="max-width:340px">
      ${S.boot.projects.map(p => `<option value="${p.id}" ${p.id === S.project ? 'selected' : ''}>${esc(p.name)}${p.clientName ? ' — ' + esc(p.clientName) : ''}</option>`).join('')}
    </select><div style="flex:1"></div></div>
    <div id="kbHead"></div>
    <div class="kb" id="kb">${S.boot.statuses.map(() => '<div class="skel" style="flex:1;min-height:300px"></div>').join('')}</div>`;
  $('#projSel').onchange = e => { S.project = +e.target.value; vBoard(); };
  const d = await api('board&project=' + S.project);
  const kb = $('#kb'); if (!kb) return;
  if (d.meta) { boardHead(d.meta); }
  /* Κινητό (21/9/2026): οι στήλες είναι 84vw και το «Backlog» άδειο έπιανε όλη την οθόνη — οι εργασίες
     ήταν εκτός οθόνης χωρίς ένδειξη. Τώρα: γραμμή chips με τις στήλες (και μετρητές) που πηδά στη
     στήλη, και αυτόματο άνοιγμα στην πρώτη στήλη που έχει εργασίες. */
  { const old = $('#kbMobNav'); if (old) old.remove(); }
  if (matchMedia('(max-width:768px)').matches) {
    const nav = document.createElement('div'); nav.id = 'kbMobNav'; nav.className = 'kb-mobnav';
    nav.innerHTML = d.columns.map(col => { const st = statusOf(col.status); return `<button type="button" data-kbjump="${st.id}" style="--c:${st.color}">${esc(st.title)} <b>${col.tasks.length}</b></button>`; }).join('');
    kb.before(nav);
    const jump = id => { const colEl = kb.querySelector('.kb-col[data-status="' + id + '"]'); if (!colEl) return; kb.scrollTo({left: colEl.offsetLeft - 8, behavior: 'smooth'}); nav.querySelectorAll('[data-kbjump]').forEach(b => b.classList.toggle('on', +b.dataset.kbjump === +id)); };
    nav.querySelectorAll('[data-kbjump]').forEach(b => b.onclick = () => jump(+b.dataset.kbjump));
    const firstFull = d.columns.find(c => c.tasks.length) || d.columns[0];
    setTimeout(() => { if (firstFull) jump(statusOf(firstFull.status).id); }, 60);
    kb.addEventListener('scroll', () => { let best = null, bd = 1e9; kb.querySelectorAll('.kb-col').forEach(c => { const dd = Math.abs(c.offsetLeft - kb.scrollLeft); if (dd < bd) { bd = dd; best = c; } }); if (best) nav.querySelectorAll('[data-kbjump]').forEach(b => b.classList.toggle('on', b.dataset.kbjump === best.dataset.status)); }, {passive: true});
  }
  kb.innerHTML = d.columns.map(col => {
    const st = statusOf(col.status);
    return `<div class="kb-col" data-status="${st.id}">
      <div class="kb-h" style="border-color:${st.color}">${esc(st.title)}<span class="kb-n">${col.tasks.length}</span></div>
      <div class="kb-cards">${col.tasks.map(cardHtml).join('')}</div>
      <div class="kb-add"><input placeholder="+ Νέο task… (Enter)" data-status="${st.id}">
        ${(S.boot.depts || []).length ? `<select class="kb-add-u" title="Department στο οποίο θα ανήκει">
          <option value="">department: αυτόματα</option>
          ${S.boot.depts.map(u => `<option value="${u.id}">${esc(u.name)}</option>`).join('')}</select>` : ''}</div>
    </div>`;
  }).join('');
  /* Νέα εργασία κατευθείαν στη στήλη. Το department το διαλέγεις εδώ: σε έργο
     πελάτη δεν υπάρχει τι να κληρονομήσει, οπότε αλλιώς θα έμενε αζήτητη. */
  $$('.kb-add', kb).forEach(box => {
    const inp = box.querySelector('input'), sel = box.querySelector('select');
    inp.onkeydown = async e => {
      if (e.key !== 'Enter' || !inp.value.trim()) return;
      const r = await api('quick_task', {project: S.project, status: +inp.dataset.status,
        dept: sel ? +sel.value || 0 : 0, title: inp.value.trim()});
      if (r.ok) { toast('Δημιουργήθηκε'); vBoard(); }
    };
  });
}
/* Διάλογος ολοκλήρωσης: δυο λόγια για το πώς έκλεισε η εργασία.
   Επιστρέφει το κείμενο (μπορεί και κενό) ή null αν ακυρώθηκε. */
const askDone = title => cnpDialog({
  title: '✔ Ολοκλήρωση',
  body: title || '',
  input: '', rows: 3, max: 500,
  placeholder: 'π.χ. Έγινε ρύθμιση DNS και επιβεβαιώθηκε με τον πελάτη',
  hint: 'Προαιρετικό — Ctrl+Enter για γρήγορο κλείσιμο',
  ok: 'Ολοκλήρωση', cancel: 'Άκυρο',
});

/* Κεφαλίδα έργου: ΣΕ ΠΟΙΟΝ παραδίδεται και ΠΟΙΑ ΤΜΗΜΑΤΑ το κρατάνε πίσω.
   Χωρίς αυτό, το board είναι μια στοίβα εργασιών χωρίς παραλήπτη. */
function boardHead(m) {
  const h = $('#kbHead'); if (!h) return;
  const uOf = id => (S.boot.depts || []).find(u => u.id === id);
  const tot = m.deptSplit.reduce((a, x) => a + x.total, 0);
  const dn = m.deptSplit.reduce((a, x) => a + x.done, 0);
  const chips = m.deptSplit.map(x => {
    const u = uOf(x.dept);
    const left = x.total - x.done;
    return `<a class="us-chip ${left ? (x.late ? 'late' : '') : 'done'}"
      href="${u ? '#/unit/' + u.id : 'javascript:'}"
      title="${u ? esc(u.name) : 'Χωρίς department'}: ${x.done}/${x.total} ολοκληρωμένες${x.late ? ' — ' + x.late + ' εκπρόθεσμες' : ''}">
      <i style="background:${u ? u.color : '#8595ac'}">${esc(u ? u.icon : '?')}</i>
      ${esc(u ? u.name : 'Χωρίς department')} <b>${x.done}/${x.total}</b>
      ${x.late ? `<span class="pill pill-bad" style="padding:0 5px">${x.late}</span>` : ''}</a>`;
  }).join('');
  h.innerHTML = `<div class="card" style="margin-bottom:14px"><div class="card-b" style="padding:11px 15px;display:flex;flex-direction:column;gap:9px">
    <div style="display:flex;align-items:center;gap:9px;flex-wrap:wrap">
      ${m.clientId
        ? `<a class="pill pill-info" href="#/client360/${m.clientId}" title="Καρτέλα πελάτη">${I.user} ${esc(m.client)}</a>`
        : '<span class="pill pill-mut">Λειτουργικό project — χωρίς πελάτη</span>'}
      ${m.manager ? `<span class="pill pill-mut">Υπεύθυνος: ${esc(m.manager)}</span>` : ''}
      ${m.due ? `<span class="pill ${m.due < today() && m.pstatus !== 'done' ? 'pill-bad' : 'pill-mut'}">${I.cal} Παράδοση ${dShort(m.due)}</span>` : ''}
      <span style="flex:1"></span>
      ${m.clientId && m.canEdit ? `<button class="btn btn-sm btn-o" id="kbAssign" title="Ανάθεση δικών μας προϊόντων/modules σε αυτό το έργο">${I.box} Ανάθεση προϊόντων</button>` : ''}
      <b style="font-variant-numeric:tabular-nums">${dn}/${tot}</b><small class="mut">εργασίες</small>
    </div>
    ${(m.modules || []).length ? `<div class="us-strip" title="Modules της υλοποίησης">${m.modules.map(x =>
      `<a class="us-chip ${x.total && x.done === x.total ? 'done' : ''}" href="javascript:" data-modfilter="${x.id}"
         title="${esc(x.name)}: ${x.done}/${x.total} εργασίες — κλικ για να δεις μόνο αυτές">
        <i style="background:${x.color}">${I.box}</i>${esc(x.name)} <b>${x.done}/${x.total}</b></a>`).join('')}</div>` : ''}
    ${chips ? `<div class="us-strip">${chips}</div>` : ''}
  </div></div>`;
  /* Ανάθεση προϊόντων από εδώ: το έργο είναι ανοιχτό, δεν χρειάζεται να πάει
     κανείς στη φόρμα επεξεργασίας για να δηλώσει τι παραδίδει. */
  const asg = $('#kbAssign', h);
  if (asg && window.openAssignModules) {
    asg.onclick = () => window.openAssignModules(m.id, () => vBoard());
  }
  /* Κλικ σε module → μένουν μόνο οι κάρτες του στο board· ξανά κλικ → όλες. */
  $$('[data-modfilter]', h).forEach(a => a.onclick = () => {
    const id = +a.dataset.modfilter, on = a.classList.toggle('sel');
    $$('[data-modfilter]', h).forEach(o => { if (o !== a) o.classList.remove('sel'); });
    $$('.tcard').forEach(c => { c.style.display = (!on || +c.dataset.module === id) ? '' : 'none'; });
  });
}

function cardHtml(t) {
  const ty = t.type ? typeOf(t.type) : null;
  const over = t.due && t.due < today() && !t.done;
  return `<div class="tcard ${over ? 'overdue' : ''}" data-task="${t.id}" data-module="${t.module || 0}">
    <div class="tcard-t">${ty ? `<i class="fas ${ty.icon}" style="color:${ty.color};margin-right:4px"></i>` : ''}${esc(t.title)}</div>
    <div class="tcard-m">
      <span class="dot" style="background:${['#8595ac', '#eba63c', '#e2515f'][t.prio]}"></span>
      <span class="tk-idc" title="Αριθμός εργασίας">#${t.id}</span>
      ${(t.ticket || t.ticketRef)
        ? `<span class="tk-flag tk-flag-tk" title="Από ticket — προτεραιότητα στην ανάθεση">${I.ticket} ${
            t.ticketNo ? '#' + esc(t.ticketNo) : t.ticketRef ? '#' + esc(t.ticketRef) : 'ticket'}</span>` : ''}
      ${t.isOffer ? `<span class="tk-flag tk-flag-of" title="Αφορά προσφορά — προτεραιότητα">${I.doc} προσφορά</span>` : ''}
      ${/* ΠΟΙΟΣ ΤΟ ΚΡΑΤΑΕΙ, όχι ποιανού είναι στα χαρτιά. Μια κάρτα με το
           αρχικό του αναδόχου ενώ την τρέχει άλλος λέει ψέματα σε όποιον
           κοιτάζει το board για να καταλάβει ποιος κάνει τι. */''}
      ${cnpHolder(t) ? `<span class="ava" title="${esc(adminName(cnpHolder(t)))}${
        cnpHolder(t) !== +(t.assignee || 0) && t.assignee
          ? ' — ανάθεση: ' + esc(adminName(t.assignee)) : ''}">${esc(adminIni(cnpHolder(t)))}</span>` : ''}
      ${t.ball ? `<span class="ball ${t.ball === S.boot.me.id ? 'me' : ''}" title="Η μπάλα: περιμένει ενέργεια από ${esc(adminName(t.ball))}">⚡${esc(adminIni(t.ball))}</span>` : ''}
      ${t.due ? `<span class="${over ? 'pill pill-bad' : ''}">${I.cal} ${dShort(t.due)}</span>` : ''}
      ${t.mins ? `<span>⏱ ${fmtMin(t.mins)}</span>` : ''}
      ${t.est ? `<span class="mut">~${fmtMin(t.est)}</span>` : ''}
      ${t.check ? `<span class="${t.check[0] >= t.check[1] ? 'pill pill-ok' : ''}">☑ ${t.check[0]}/${t.check[1]}</span>` : ''}
      ${t.att ? `<span class="tk-flag tk-flag-att" title="${t.att} συνημμένα">${I.clip} ${t.att}</span>` : ''}
      ${t.blocked ? `<span class="pill pill-bad" title="Μπλοκάρεται από ${t.blocked} tasks">⛓ ${t.blocked}</span>` : ''}
    </div>
    ${t.done && t.doneNote ? `<div class="tcard-done" title="${esc(t.doneNote)}">✔ ${esc(t.doneNote)}</div>` : ''}</div>`;
}
/* Οι κάρτες προσφορών δανείζονται τα ίδια class (.tcard/.kb-col) για την εμφάνιση,
   και το delegation του dnd είναι καθολικό: χωρίς αυτό το φίλτρο, ένα κλικ σε προσφορά
   ζητούσε task «NaN» και ένα σύρσιμο θα καλούσε move_task για ανύπαρκτη εργασία. */
dnd('.tcard[data-task]', '.kb-col[data-status]', async (card, col) => {
  const st = +col.dataset.status;
  let note = '';
  if (S.boot.statuses.find(x => x.id === st && x.done)) {
    note = await askDone(card.querySelector('.tcard-t')?.textContent || '');
    if (note === null) { return; }          // άκυρο = η κάρτα μένει όπου ήταν
  }
  const r = await cnpMoveTask(+card.dataset.task, st, note);
  if (r.cancelled) { vBoard(); return; }
  if (r.ok) {
    col.querySelector('.kb-cards').appendChild(card);
    $$('.kb-col').forEach(c => c.querySelector('.kb-n').textContent = c.querySelectorAll('.tcard').length);
  } else { toast(r.error || 'Δεν επιτρέπεται', true); vBoard(); }
}, el => openTask(+el.dataset.task));

/* Ο άνθρωπος δεν είναι ελεύθερος σε αυτό το διάστημα.
   Δεν το απαγορεύουμε — υπάρχουν νόμιμες επικαλύψεις — αλλά δεν επιτρέπεται να
   γίνει στα τυφλά: δείχνουμε ΠΟΙΕΣ εργασίες πέφτουν πάνω, με ώρες, και ο
   χειριστής αποφασίζει ρητά.
   Επιστρέφει 'force' (ανάθεσε έτσι κι αλλιώς), 'again' (άλλαξε ώρες) ή null. */
function askBusy(info) {
  return new Promise(resolve => {
    const rng = c => `${dShort(c.start)}${c.startT ? ' ' + c.startT : ''} → ${dShort(c.due)}${c.dueT ? ' ' + c.dueT : ''}`;
    const ovl = document.createElement('div');
    ovl.className = 'ovl show'; ovl.style.zIndex = 340;
    ovl.innerHTML = `<div class="pal-box" style="margin:12vh auto 0;max-width:560px" role="dialog">
      <div style="padding:20px 22px 18px">
        <b style="font-size:15.5px;color:var(--ink)">${I.alert} ${esc(info.name || '')} δεν είναι διαθέσιμος/η</b>
        <div style="font-size:13px;color:var(--txt);margin-top:8px">${esc(info.error || '')}</div>
        <div style="margin-top:12px;max-height:40vh;overflow:auto">
          ${(info.clashes || []).map(c => `<div class="bq-row">
            <div style="flex:1;min-width:0">
              <b style="font-size:12.5px;color:var(--ink)">${esc(c.title)}</b>
              <div class="mut" style="font-size:11px">#${c.id}${c.project ? ' · ' + esc(c.project) : ''}</div>
            </div>
            <span class="pill pill-warn" style="white-space:nowrap">${rng(c)}</span>
          </div>`).join('')}
        </div>
        <div style="display:flex;gap:9px;margin-top:16px;justify-content:flex-end;flex-wrap:wrap">
          <button class="btn btn-o" id="buNo">Άκυρο</button>
          <button class="btn btn-o" id="buAgain">Άλλαξε ώρες</button>
          <button class="btn" id="buGo" style="background:var(--warn);color:#fff">Ανάθεσέ το έτσι κι αλλιώς</button>
        </div>
      </div></div>`;
    document.body.appendChild(ovl);
    const done = v => { ovl.remove(); resolve(v); };
    $('#buNo', ovl).onclick = () => done(null);
    $('#buAgain', ovl).onclick = () => done('again');
    $('#buGo', ovl).onclick = () => done('force');
  });
}

/* Χωρίς χρόνο υλοποίησης δεν ανατίθεται εργασία σε agent.
   Ο διάλογος δεν λέει απλώς «όχι»: δίνει τις δύο νόμιμες εξόδους —
   πρόχειρο στον διαχειριστή, ή ανάθεση με σαφείς ημερομηνίες.
   Επιστρέφει {start,due} ή {assignee:<admin>} ή null (άκυρο). */
function askImplDates(info, me) {
  return new Promise(resolve => {
    const d0 = (info.start || today());
    const d1 = new Date(); d1.setDate(d1.getDate() + 7);
    const dEnd = info.due || d1.toISOString().slice(0, 10);
    const ovl = document.createElement('div');
    ovl.className = 'ovl show'; ovl.style.zIndex = 330;
    ovl.innerHTML = `<div class="pal-box" style="margin:14vh auto 0;max-width:520px" role="dialog">
      <div style="padding:20px 22px 18px">
        <b style="font-size:15.5px;color:var(--ink)">${I.alert} Χωρίς χρόνο υλοποίησης δεν ανατίθεται</b>
        <div style="font-size:13px;color:var(--txt);margin-top:8px">${esc(info.error || '')}</div>
        <div class="frow" style="margin-top:14px">
          <div><label class="lbl">Έναρξη</label>
            <div class="dt2"><input type="date" class="inp" id="idStart" value="${d0}">
              ${timeInput('idStartT', info.startT)}</div></div>
          <div><label class="lbl">Λήξη</label>
            <div class="dt2"><input type="date" class="inp" id="idDue" value="${dEnd}">
              ${timeInput('idDueT', info.dueT)}</div></div>
        </div>
        <div class="mut" style="font-size:11px;margin-top:2px">Η ώρα είναι προαιρετική — κενή σημαίνει ότι πιάνει όλη τη μέρα.</div>
        <div id="idErr" class="mut" style="font-size:11.5px;color:var(--bad);margin-top:4px" hidden></div>
        <div style="display:flex;gap:9px;margin-top:14px;justify-content:flex-end;flex-wrap:wrap">
          <button class="btn btn-o" id="idNo">Άκυρο</button>
          <button class="btn btn-o" id="idDraft">Κράτησέ το πρόχειρο σε μένα</button>
          <button class="btn btn-p" id="idGo">Ανάθεση με αυτές τις ημερομηνίες</button>
        </div>
        <div class="mut" style="font-size:11px;margin-top:8px">Πρόχειρη κρατάς μόνο στον εαυτό σου — σε άλλον χρειάζονται ημερομηνίες.</div>
      </div></div>`;
    document.body.appendChild(ovl);
    const done = v => { ovl.remove(); resolve(v); };
    $('#idNo', ovl).onclick = () => done(null);
    $('#idDraft', ovl).onclick = () => done({assignee: me.id, start: null, due: null});
    $('#idGo', ovl).onclick = () => {
      const a = $('#idStart', ovl).value, b = $('#idDue', ovl).value, er = $('#idErr', ovl);
      if (!a || !b) { er.hidden = false; er.textContent = 'Χρειάζονται και οι δύο ημερομηνίες.'; return; }
      if (a > b) { er.hidden = false; er.textContent = 'Η λήξη δεν μπορεί να είναι πριν την έναρξη.'; return; }
      done({start: a, due: b,
        startT: $('#idStartT', ovl).value || null, dueT: $('#idDueT', ovl).value || null});
    };
    setTimeout(() => $('#idStart', ovl).focus(), 40);
  });
}

/* Αλλαγή κατάστασης εργασίας — ΜΙΑ διαδρομή για όλα τα σημεία (σύρσιμο στο
   board, dropdown της καρτέλας, «Ολοκλήρωση»). Αν ο server ζητήσει προθεσμία
   επειδή η εργασία φεύγει από το Backlog, τη ζητάμε εδώ και ξαναστέλνουμε —
   ο χειριστής δεν πρέπει να χάσει την κίνησή του για μια ημερομηνία. */
async function cnpMoveTask(id, status, note) {
  const send = due => api('move_task', Object.assign({task: id, status, note: note || ''}, due ? {due} : {}))
    .then(r => {
      /* Ο server σταματά το χρονόμετρο όταν αλλάζεις κατάσταση — πες το, με
         δρόμο επιστροφής αν συνεχίζεις να δουλεύεις. */
      if (r && r.timerStopped) { cnpTimerStoppedToast(id, r.timerStopped.mins); }
      return {ok: !!r.ok};
    })
    .catch(e => ({ok: false, error: e && e.message, data: e && e.data}));
  /* ΤΟ ΚΛΕΙΣΙΜΟ ΘΕΛΕΙ ΛΗΞΗ: ΗΜΕΡΟΜΗΝΙΑ ΚΑΙ ΩΡΑ. Δεν είναι τυπικότητα — είναι η
     μόνη στιγμή που κάποιος ξέρει πότε τελείωσε πραγματικά. Μόλις κλείσει, η
     εργασία φεύγει από τις οθόνες και κανείς δεν ξαναγυρίζει να το συμπληρώσει. */
  const askDueTime = () => new Promise(resolve => {
    const ovl = document.createElement('div');
    ovl.className = 'ovl show'; ovl.style.zIndex = 330;
    const now = new Date();
    ovl.innerHTML = `<div class="pal-box" style="margin:16vh auto 0;max-width:460px" role="dialog">
      <div style="padding:20px 22px 18px">
        <b style="font-size:15.5px;color:var(--ink)">✔ Πότε τελείωσε;</b>
        <div style="font-size:13px;color:var(--txt);margin-top:8px">Συμπλήρωσε τη λήξη για να κλείσει η εργασία.</div>
        ${/* Προσυμπληρώνουμε ΤΩΡΑ, όχι την προγραμματισμένη λήξη: η ερώτηση είναι
             «πότε τελείωσε», και η προγραμματισμένη μπορεί να είναι στο μέλλον —
             θα κατέγραφε παράδοση που δεν έχει γίνει ακόμη. */''}
        <div style="margin-top:14px"><label class="lbl">Λήξη <span class="mut" style="font-weight:400">— ημ/νία &amp; ώρα</span></label>
          <div class="dt2"><input type="date" class="inp" id="dtD" value="${today()}">
            ${timeInput('dtT', String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0'))}</div></div>
        <div id="dtErr" class="mut" style="font-size:11.5px;color:var(--bad);margin-top:6px" hidden></div>
        <div style="display:flex;gap:9px;margin-top:15px;justify-content:flex-end">
          <button class="btn btn-o" id="dtNo">Άκυρο</button>
          <button class="btn btn-ok" id="dtGo">Ολοκλήρωση</button></div>
      </div></div>`;
    document.body.appendChild(ovl);
    const fin = v => { ovl.remove(); resolve(v); };
    $('#dtNo', ovl).onclick = () => fin(null);
    $('#dtGo', ovl).onclick = () => {
      const dd = $('#dtD', ovl).value, tt = $('#dtT', ovl).value, er = $('#dtErr', ovl);
      if (!dd || !tt) { er.hidden = false; er.textContent = 'Χρειάζονται και η ημερομηνία και η ώρα.'; return; }
      fin({due: dd, dueT: tt});
    };
    setTimeout(() => $('#dtT', ovl).focus(), 40);
  });

  let r = await send(null);
  if (!r.ok && r.data && r.data.need === 'duetime') {
    const pick = await askDueTime();
    if (!pick) { return {ok: false, cancelled: true}; }
    r = await api('move_task', {task: id, status, note: note || '', due: pick.due, dueT: pick.dueT})
      .then(x => ({ok: !!x.ok}))
      .catch(e => ({ok: false, error: e && e.message, data: e && e.data}));
    return r;
  }
  if (!r.ok && r.data && r.data.need === 'due') {
    const d = new Date(); d.setDate(d.getDate() + 7);
    const pick = await cnpDialog({
      title: 'Πότε παραδίδεται;',
      body: r.error + '\n\nΜπαίνει ως προθεσμία της εργασίας — μπορείς να την αλλάξεις αργότερα.',
      input: d.toISOString().slice(0, 10), inputType: 'date',
      ok: 'Ξεκίνα την', cancel: 'Άκυρο'});
    if (pick === null || !pick) { return {ok: false, cancelled: true}; }
    r = await send(pick);
  }
  return r;
}

/* Ποιες εργασίες έχουν ήδη ρωτηθεί «ξεκινάς;» σε αυτή τη συνεδρία. */
window._cnpTimerAsked_ = window._cnpTimerAsked_ || {};

/* ═════════ TASK DRAWER ═════════ */
let timerInt = null;
/* ΟΤΙ ΕΓΡΑΨΕΣ ΚΑΙ ΔΕΝ ΚΑΤΑΧΩΡΗΣΕΣ, ΕΠΙΖΕΙ ΤΟΥ ΞΑΝΑΣΧΕΔΙΑΣΜΟΥ (23/09/2026).
   Η καρτέλα ξαναχτίζεται ολόκληρη σε δεκαπέντε αφορμές: αποθήκευση, χρονόμετρο,
   καταχώρηση χρόνου, δέσιμο προσφοράς… Ό,τι είχες μισογραμμένο αριστερά —
   μια απάντηση στη συζήτηση, μια διόρθωση στο ζητούμενο — έσβηνε μαζί.
   Τα κρατάμε εδώ, σε ΕΝΑ σημείο απ' όπου περνούν όλες οι αφορμές, αντί να
   θυμάται η καθεμιά να τα σώσει. */
function cnpTaskDraftGrab(id) {
  const dr = document.querySelector('.drawer.tk-modal');
  if (!dr || +dr.dataset.task !== +id) { return null; }
  const g = sel => { const e = dr.querySelector(sel); return e ? (e.isContentEditable ? e.innerHTML : e.value) : null; };
  const chk = g('#chkNew');
  return {
    chk: (chk && chk.trim() && chk.trim() !== '<br>') ? chk : null,
    descr: g('#fDescr'),
    mins: g('#tMins') || null,
    note: g('#tNote') || null,
  };
}

function cnpTaskDraftPut(dr, draft) {
  if (!draft) { return; }
  const put = (sel, v) => {
    if (v === null || v === undefined || v === '') { return; }
    const e = dr.querySelector(sel); if (!e) { return; }
    if (e.isContentEditable) { e.innerHTML = v; } else { e.value = v; }
  };
  put('#chkNew', draft.chk);
  put('#tMins', draft.mins);
  put('#tNote', draft.note);
  /* Το ζητούμενο μόνο αν όντως διαφέρει από αυτό που μόλις ήρθε — αλλιώς θα
     «επανέφερε» παλιό κείμενο πάνω σε αποθηκευμένο. */
  const fd = dr.querySelector('#fDescr');
  if (fd && draft.descr !== null && draft.descr !== undefined && draft.descr !== fd.innerHTML) {
    fd.innerHTML = draft.descr;
    fd.dataset.dirty = '1';
  }
}

async function openTask(id, entryId, opts) {
  opts = opts || {};
  const draft = cnpTaskDraftGrab(id);
  const d = await api('task&id=' + id).catch(() => null);
  if (!d) { toast('Δεν έχεις πρόσβαση', true); return; }
  closeDrawer();
  /* Το closeDrawer αφαιρεί την παλιά καρτέλα μετά από 300ms (animation). Στο ξανασχεδίασμα
     (Start/Stop/αποθήκευση) η νέα μπαίνει ΑΜΕΣΩΣ — και για 300ms υπήρχαν δύο #dX: το ✕ της
     νέας δενόταν στην παλιά και έμενε νεκρό («δεν με αφήνει να βγω»). Εδώ φεύγει τώρα. */
  $$('.drawer.tk-modal').forEach(el => { const o = el.closest('.ovl'); el.remove(); if (o) { o.remove(); } });
  const t = d.task, me = S.boot.me;
  /* Το «ζητούμενο» το επεξεργάζεται μόνο ο δημιουργός της εργασίας ή Full admin·
     οι υπόλοιποι το βλέπουν read-only (ο server επιβάλλει τον ίδιο κανόνα). */
  const creatorId = d.creatorId || 0;
  const canEditBrief = !!(me.full || (creatorId && me.id === creatorId));
  /* Ποιος κρατάει την εργασία — ο ίδιος κανόνας με παντού (cnpHolder). */
  const dueHolder = cnpHolder(t);
  const dueLock = !!(dueHolder && dueHolder !== me.id);
  const ovl = document.createElement('div'); ovl.className = 'ovl';   // κλικ έξω ΔΕΝ κλείνει
  const dr = document.createElement('div'); dr.className = 'drawer tk-modal';
  dr.dataset.task = id;            // ώστε να ξέρει ο επόμενος ξανασχεδιασμός σε ποια εργασία ανήκε το πρόχειρο
  /* Το department δεν εκτελεί — εκτελεί ένας άνθρωπος από τις ομάδες που το
     εξυπηρετούν. Τους φέρνουμε πρώτους· οι υπόλοιποι μένουν διαθέσιμοι. */
  const admOpts = (sel, didFor) => {
    const dep = didFor ? (d.depts || []).find(x => x.id === +didFor) : null;
    const mem = dep ? (dep.members || []) : [];
    const opt = a => `<option value="${a.id}" ${a.id === +sel ? 'selected' : ''}>${esc(a.name)}</option>`;
    const head = '<option value="">— κανείς —</option>';
    if (!mem.length) { return head + S.boot.admins.map(opt).join(''); }
    const inD = S.boot.admins.filter(a => mem.includes(a.id));
    const out = S.boot.admins.filter(a => !mem.includes(a.id));
    return head
      + `<optgroup label="${esc(dep.name)}">${inD.map(opt).join('')}</optgroup>`
      + (out.length ? `<optgroup label="Εκτός department">${out.map(opt).join('')}</optgroup>` : '');
  };
  /* Χωρίς τόνους, ώστε η αναζήτηση στη συνομιλία να βρίσκει «Νικος» και «Νίκος». */
  const norm = x => String(x || '').toLowerCase()
    .replace(/ά/g, 'α').replace(/έ/g, 'ε').replace(/ή/g, 'η').replace(/[ίϊΐ]/g, 'ι')
    .replace(/ό/g, 'ο').replace(/[ύϋΰ]/g, 'υ').replace(/ώ/g, 'ω');
  /* Σταθερό χρώμα avatar ανά όνομα — για να ξεχωρίζει ο ομιλητής με τη ματιά
     όταν η κουβέντα έχει εκατοντάδες μηνύματα. */
  const avColor = n => { let h = 0; for (const ch of String(n)) { h = (h * 31 + ch.charCodeAt(0)) % 360; } return `hsl(${h} 42% 46%)`; };
  /* Ένα βήμα είναι είτε μια φράση είτε ένα κομμάτι κώδικα/λάθους. Ό,τι έχει αλλαγές
     γραμμής ή ```fences βγαίνει σε μονόστοιχο μπλοκ — αλλιώς δεν διαβάζεται. */
  const stepHtml = txt => {
    const v = String(txt || '');
    const fence = v.match(/^```[a-zA-Z0-9+#-]*\r?\n?([\s\S]*?)```\s*$/);
    const code = fence ? fence[1] : (v.includes('\n') ? v : null);
    return code !== null ? `<pre class="chk-code">${esc(code.replace(/\s+$/, ''))}</pre>` : esc(v);
  };
  const dayLabel = iso => {
    const ss = (iso || '').slice(0, 10); if (!ss) { return ''; }
    const d0 = new Date(ss + 'T00:00:00'), now = new Date();
    const t0 = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const diff = Math.round((t0 - d0) / 86400000);
    if (diff === 0) { return 'Σήμερα'; }
    if (diff === 1) { return 'Χθες'; }
    return d0.toLocaleDateString('el-GR', {day: 'numeric', month: 'long',
      year: d0.getFullYear() === now.getFullYear() ? undefined : 'numeric'});
  };
  /* Ένα μήνυμα της συνομιλίας μαζί με τα συνημμένα του. Οι εικόνες φαίνονται
     επί τόπου — δεν έχει νόημα να ανοίγεις αρχείο για να δεις screenshot.
     Τα data-* τροφοδοτούν την αναζήτηση και τα φίλτρα. */
  const cmHtml = cm => {
    const toMe = cm.to === me.id || cm.to === -1;
    const hasF = (cm.files || []).length > 0;
    return `<div class="msg ${cm.byId === me.id ? 'mine' : ''}" data-me="${toMe ? 1 : 0}" data-f="${hasF ? 1 : 0}" data-s="${esc(norm(cm.by + ' ' + cm.body))}">
    <div class="msg-h"><span class="msg-av" style="background:${avColor(cm.by)}">${esc(adminIni(cm.byId) || (cm.by || '?').slice(0, 2))}</span><b>${esc(cm.by)}</b>${cm.to !== null ? ` <span class="msg-to">→ ${cm.to === -1 ? 'όλοι' : esc(adminName(cm.to))}</span>` : ''}<span class="mut msg-t">${tShort(cm.at)}</span></div>
    <div class="msg-b">${esc(cm.body)}</div>
    ${hasF ? `<div class="msg-files">${cm.files.map(f => f.kind === 'image'
      ? `<a href="${f.url}" target="_blank" class="msg-img" title="${esc(f.name)}"><img src="${f.url}" loading="lazy" alt="${esc(f.name)}"></a>`
      : `<a href="${f.url}&dl=1" class="msg-file" title="${esc(f.name)}">${f.kind === 'video' ? '🎬' : f.kind === 'audio' ? '🎵' : '📎'} ${esc(f.name)}</a>`
    ).join('')}</div>` : ''}
  </div>`;
  };
  /* Η ροή με διαχωριστικά ημέρας — αλλιώς 560 μηνύματα είναι ένας τοίχος. */
  const threadHtml = () => {
    let last = '', h = '';
    (d.comments || []).forEach(cm => {
      const day = (cm.at || '').slice(0, 10);
      if (day !== last) { last = day; h += `<div class="msg-day"><span>${esc(dayLabel(cm.at))}</span></div>`; }
      h += cmHtml(cm);
    });
    return h;
  };

  /* Ο χρόνος ήταν χαμένος στη μέση της στήλης — ανεβαίνει στην κεφαλίδα, μαζί
     με την κατάσταση της χρέωσης, γιατί αυτά κοιτάς πρώτα. */
  const billMins = (d.timelogs || []).filter(l => l.billable && !l.running)
    .reduce((a, l) => a + (l.charged || l.mins || 0), 0);
  const stO = statusOf(t.status);
  /* Ο επιβλέπων = όποιος άνοιξε την εργασία (ορίζεται αυτόματα, δεν επιλέγεται). */
  const supId = creatorId || t.ball || 0;
  const chkDone = d.check.filter(x => x.done).length;
  /* Αντιδράσεις: ο κωδικός ζει στη βάση (utf8mb3 — όχι emoji), το emoji ζει εδώ. */
  const REACT = {up: '👍', ok: '✅', eyes: '👀', heart: '❤️', party: '🎉', think: '🤔', down: '👎'};
  const REACT_LBL = {up: 'Συμφωνώ', ok: 'Έγινε', eyes: 'Το είδα', heart: 'Μπράβο', party: 'Τέλεια', think: 'Το σκέφτομαι', down: 'Διαφωνώ'};
  const avaColor = aid => { const P = ['#0090dd', '#7b5cd6', '#1f9d57', '#e0a020', '#e2515f', '#0aa5a8', '#c2569b', '#5c6bc0']; return P[(+aid || 0) % P.length]; };
  const tkD = d.ticket || null;
  /* Χρεώσιμο: η απόφαση ανήκει στην ΕΡΓΑΣΙΑ και μένει. Μέχρι τις 15/9/2026 η
     προεπιλογή ήταν σκέτο «υπάρχει πελάτης → ναι» και ξαναγύριζε σε «ναι» σε κάθε
     ξαναχτίσιμο — ο χειριστής το ξετσέκαρε και δεν έμενε ποτέ. */
  const billOn = t.billDefault === null || t.billDefault === undefined ? !!d.owner : !!t.billDefault;
  dr.innerHTML = `
  <div class="drawer-h">
    <span class="dot" style="background:${d.project.color};width:12px;height:12px"></span>
    <div class="tk-head-main">
      <div class="tk-head-t" id="dTitleBox">
        <span class="tk-id" title="Αριθμός εργασίας — γράψε #${t.id} στην αναζήτηση (Ctrl+K) ή στη λίστα">#${t.id}</span>
        <h2 id="dTitle">${esc(t.title)}</h2>
        <button type="button" class="tk-ttl-edit" id="dTitleEdit" title="Αλλαγή τίτλου">${I.edit}</button>
        ${d.owner ? `<a class="tk-cust-tag" href="#/client360/${d.owner.id}" data-navclose
           title="${d.owner.via === 'project' ? 'Πελάτης του έργου' : 'Πελάτης του ticket'}">${I.user} ${esc(d.owner.name)}</a>` : ''}
        ${!d.owner ? (t.internal || d.project.kind === 'internal'
          ? `<span class="tk-cust-tag tk-internal" title="Εσωτερική διαδικασία / R&D / βελτίωση — δεν κρέμεται σε πελάτη, ο χρόνος μετρά ως κόστος">${I.box} Εσωτερικό / R&D</span>`
          : `<span class="tk-cust-tag tk-noclient" title="Δεν έχει δηλωθεί πελάτης ούτε ότι είναι εσωτερική — όρισέ το από τα πεδία δεξιά">${I.user} χωρίς πελάτη</span>`) : ''}
        ${d.project && !d.project.none ? `<a class="tk-cust-tag tk-pj-tag" href="#/board/${d.project.id}" data-navclose
           title="Το έργο στο οποίο ανήκει η εργασία${d.project.product ? ' · προϊόν: ' + esc(d.project.product) : ''}">📁 ${esc(d.project.name)}${
             d.project.product ? ` <span class="tk-pj-prod">· ${esc(d.project.product)}</span>` : ''}</a>` : ''}
        <button type="button" class="pill tk-st st-pill" id="dStPill" style="background:${stO.color || '#8291a9'}22;color:${stO.color || '#8291a9'}" title="Αλλαγή κατάστασης">${esc(stO.title || '—')} ▾</button>
        ${t.ticket
          ? `<span class="tk-flag tk-flag-tk" title="Προήλθε από ticket — προτεραιότητα στην ανάθεση">${I.ticket} Ticket${tkD ? ' #' + esc(tkD.tid) : ''}</span>`
          : t.ticketRef
            ? `<span class="tk-flag tk-flag-tk" title="Προήλθε από ticket της παλιάς πλατφόρμας — προτεραιότητα στην ανάθεση">${I.ticket} Ticket #${esc(t.ticketRef)}</span>` : ''}
        ${t.isOffer ? `<span class="tk-flag tk-flag-of" title="Αφορά προσφορά — προτεραιότητα">${I.doc} Προσφορά${t.offerRef ? ' ' + esc(t.offerRef) : (d.offer ? ' #' + d.offer.id : '')}</span>` : ''}
      </div>
      <div class="tk-sub">
        ${supId ? `<span class="tk-sup" title="Άνοιξε την εργασία και έχει την ευθύνη να την παρακολουθεί — ορίζεται αυτόματα από τον χρήστη">${I.eye} Επιβλέπων: <b>${esc(adminName(supId))}</b></span>` : ''}
        ${t.createdAt ? `<span class="tk-sup" title="Πότε άνοιξε η εργασία">${I.cal} Άνοιξε: <b>${dShort(t.createdAt)}</b></span>` : ''}
        ${t.ball ? `<span class="tk-sup tk-ball${t.ball === me.id ? ' me' : ''}" title="Η εργασία περιμένει ενέργεια από αυτόν — όσο την κρατά, της εμφανίζεται στη «Μέρα μου» του">${I.zap} Μπάλα: <b>${t.ball === me.id ? 'εσύ' : esc(adminName(t.ball))}</b></span>` : ''}
        ${(d.path && d.path.length) ? `<span class="tk-crumb" title="Διαδρομή φακέλων">${
          d.path.map(p => `<a href="#/board/${p.id}" data-navclose>${esc(p.name)}</a>`).join('<span class="tk-crumb-sep">›</span>')
        }</span>` : ''}
      </div>
    </div>
    <span class="tk-hdr">
      <span class="tk-hdr-i" title="Καταγεγραμμένος χρόνος${t.est ? ' / εκτίμηση' : ''}">⏱ <b>${fmtMin(d.total)}</b>${t.est ? `<small>/ ~${fmtMin(t.est)}</small>` : ''}</span>
      ${billMins ? `<span class="tk-hdr-i ${t.billOk ? 'ok' : 'warn'}" title="${t.billOk ? 'Εγκρίθηκε από το λογιστήριο' : 'Χρεώσιμος χρόνος — χρειάζεται έγκριση λογιστηρίου πριν κλείσει'}">💶 <b>${fmtMin(billMins)}</b> ${t.billOk ? '✔' : '⏳'}</span>` : ''}
      ${d.timerHere ? `<span class="tk-hdr-i live">▶ <span class="timer-live" id="tLive" style="font-size:12.5px"></span></span><button class="btn btn-sm btn-danger" id="tStop">${I.stop} Stop</button>`
        : d.timerElsewhere ? `<span class="tk-hdr-i warn" title="Τρέχει χρονόμετρο σε άλλη εργασία">τρέχει αλλού</span><button class="btn btn-sm btn-ok" id="tStart">${I.play} Εδώ</button>`
        : `<button class="btn btn-sm btn-ok" id="tStart" title="Ξεκίνα χρονόμετρο σε αυτή την εργασία">${I.play} Start</button>`}
    </span>
    <button class="btn btn-sm ${d.watching ? 'btn-p' : 'btn-o'}" id="dWatch"
      title="${d.watching ? 'Την παρακολουθείς — ειδοποιήσεις σε κάθε αλλαγή. Κλικ για διακοπή.' : 'Παρακολούθηση: ειδοποίηση σε κάθε αλλαγή αυτής της εργασίας.'}"
      aria-label="Παρακολούθηση εργασίας">${I.eye}${d.watchers ? ' ' + d.watchers : ''}</button>
    <button class="drawer-x" id="dX">✕</button>
  </div>
  <div class="drawer-b tk-modal-b">
    ${(() => {
      /* ⚠ Υπέρβαση εκτίμησης: φαίνεται σε όλους· ο επικεφαλής/υπεύθυνος μπορεί να ρωτήσει
         «τι γίνεται» με ένα κλικ, και εδώ φαίνεται και η απάντηση. */
      const ov = d.overrun || {};
      if (!ov.hours && !ov.days) { return ''; }
      const worst = Math.max(ov.hours ? ov.hours.pct : 0, ov.days ? ov.days.pct : 0);
      const ck = ov.checkin;
      const ckHtml = ck
        ? (ck.status === 'done'
          ? `<div class="tk-over-ck ${ck.answer === 'help' ? 'help' : 'ok'}">${ck.answer === 'help' ? '🆘' : '✅'} <b>${esc(ck.to)}</b> απάντησε ${esc(tShort(ck.doneAt || ck.at))}: ${ck.answer === 'help' ? 'χρειάζεται βοήθεια' : 'όλα καλά'}${ck.answerNote ? ` — «${esc(ck.answerNote)}»` : ''}</div>`
          : `<div class="tk-over-ck wait">💬 <b>${esc(ck.by)}</b> ρώτησε ${esc(tShort(ck.at))} — περιμένει απάντηση από ${esc(ck.to)}</div>`)
        : '';
      return `<div class="tk-over ${worst >= 100 ? 'l3' : (worst >= 50 ? 'l2' : 'l1')}">
        <div class="tk-over-h">⚠ <b>Υπέρβαση εκτίμησης</b>
          ${ov.hours ? `<span class="pill pill-warn" title="Καταγεγραμμένος χρόνος σε σχέση με την εκτίμηση">⏱ ${esc(ov.hoursText)}</span>` : ''}
          ${ov.days ? `<span class="pill pill-bad" title="Ημέρες από την έναρξη σε σχέση με το πλάνο">📅 ${esc(ov.daysText)}</span>` : ''}
          <span style="flex:1"></span>
          ${ov.canAsk ? `<button class="btn btn-sm btn-p" id="dAsk" title="Στέλνει στον ${esc(adminName(ov.agent))} ερώτηση «τι γίνεται; χρειάζεσαι βοήθεια;» — η απάντηση γυρίζει σε σένα">💬 Ρώτα τι γίνεται</button>` : ''}
        </div>
        ${ckHtml}
      </div>`;
    })()}
    <div class="card tk-brief"><div class="card-h">${I.doc || ''} <b>Το ζητούμενο</b>
      <span class="mut" style="font-weight:600;font-size:11px">— τι ακριβώς πρέπει να γίνει · γράψε <b>@Όνομα</b> για να ειδοποιήσεις συνάδελφο</span>
      ${canEditBrief ? '' : '<span class="pill pill-mut" style="margin-left:auto;flex:none" title="Το ορίζει μόνο ο δημιουργός της εργασίας">read-only</span>'}</div>
      <div class="card-b">
        ${canEditBrief
          ? rteHtml('fDescr', d.descr || '', 'Περιγραφή, βήματα, σύνδεσμοι… (@Όνομα = ειδοποίηση)', {min: 110})
            /* ΜΙΑ ΑΠΟΘΗΚΕΥΣΗ, ΣΤΑ ΔΕΞΙΑ. Εδώ υπήρχε δεύτερο κουμπί μόνο για το
               ζητούμενο — δύο «αποθηκεύσεις» στην ίδια καρτέλα, και έπρεπε να
               ξέρεις ποια σώζει τι. Το δεξί «Αποθήκευση» τα σώζει όλα, και το
               ζητούμενο μαζί. */
            + `<div class="tk-brief-foot"><span class="mut" id="dBriefHint" style="font-size:11.5px">Αποθηκεύεται με το «Αποθήκευση» δεξιά.</span></div>`
          : `<div class="tk-brief-ro">${d.descr && d.descr.trim() ? cnpBalanced(d.descr) : '<span class="mut">— Δεν έχει οριστεί ζητούμενο.</span>'}</div>`}
        <details class="tk-att" open><summary id="dFilesSum">${I.clip} Συνημμένα ζητουμένου<b data-attn></b></summary>
          <div id="dFiles"><div class="mut" style="font-size:12px">Φόρτωση…</div></div></details>
      </div></div>

    ${(() => {
      /* ── Η ροή της εργασίας, όπως συζήτηση: οι ενέργειες (posts), τα παλιά μηνύματα
         και τα ΣΥΜΒΑΝΤΑ του ιστορικού (κατάσταση, μπάλα, χρέωση, χρόνος) σε ένα
         χρονολόγιο — ποιος είπε τι, πότε, και τι άλλαξε ανάμεσα. ── */
      const EV_SKIP = {edit: 1, comment: 1};
      const evText = a => {
        const dt = esc(a.detail || '');
        if (a.action === 'status') { const m = String(a.detail || '').split('→'); return m.length === 2 ? `άλλαξε την κατάσταση σε <b class="th-st">${esc(m[1].trim())}</b> <span class="mut">(από ${esc(m[0].trim())})</span>` : `άλλαξε την κατάσταση — ${dt}`; }
        if (a.action === 'create') { return `δημιούργησε την εργασία <span class="mut">${dt}</span>`; }
        if (a.action === 'auto') { return `<span class="mut">${dt}</span>`; }
        if (a.action === 'assign') { return dt; }
        if (a.action === 'billing') { return `💶 ${dt}`; }
        if (a.action === 'time' || a.action === 'timer') { return `⏱ ${dt}`; }
        if (a.action === 'checkin') { return `❓ ${dt}`; }
        return dt || esc(a.action);
      };
      const items = [];
      (d.check || []).forEach(it => items.push({k: 'post', at: it.at || '', it}));
      (d.comments || []).forEach(c => items.push({k: 'msg', at: c.at || '', c}));
      (d.activity || []).forEach(a => { if (!EV_SKIP[a.action]) { items.push({k: 'ev', at: a.at || '', a}); } });
      items.sort((x, y) => String(x.at).localeCompare(String(y.at)));
      const SHOW = 12, hidden = Math.max(0, items.length - SHOW);
      const files = list => (list || []).length ? `<div class="th-files">${list.map(f => `
        <a class="th-file" href="api.php?a=file_get&id=${f.id}" target="_blank" rel="noopener" title="${esc(f.name)}">
          <span class="th-file-ic">${I.fileText || I.clip}</span><span class="th-file-n">${esc(f.name)}</span>${f.sizeh ? `<span class="mut">${esc(f.sizeh)}</span>` : ''}</a>`).join('')}</div>` : '';
      const groupReacts = rs => { const g = {}; (rs || []).forEach(r => { (g[r.code] = g[r.code] || {code: r.code, n: 0, who: [], mine: false}); g[r.code].n++; g[r.code].who.push(r.by); if (r.byId === me.id) { g[r.code].mine = true; } }); return Object.values(g); };
      /* Χωρίς συγγραφέα (βήμα από module ή παλιά εγγραφή): ουδέτερο avatar, όχι «?». */
      const ava = (aid, name) => aid
        ? `<span class="th-ava" style="background:${avaColor(aid)}" title="${esc(name || '')}">${esc(adminIni(aid) || String(name || '?').slice(0, 2).toUpperCase())}</span>`
        : `<span class="th-ava th-ava-sys" title="Βήμα εργασίας">${I.checkSquare}</span>`;
      const row = (x, i) => {
        const hide = i < hidden ? ' th-hid' : '';
        if (x.k === 'ev') { const a = x.a; return `<div class="th-item th-ev${hide}">${ava(a.byId, a.by)}<div class="th-main"><span class="th-evt"><b>${esc(a.by)}</b> ${evText(a)}</span><span class="th-time">${tShort(a.at)}</span></div></div>`; }
        if (x.k === 'msg') { const c = x.c; return `<div class="th-item th-post th-legacy${hide}">${ava(c.byId, c.by)}<div class="th-main">
          <div class="th-head"><b>${esc(c.by)}</b><span class="mut" style="font-size:11px">μήνυμα${c.to && c.to > 0 ? ' προς ' + esc(adminName(c.to)) : ''}</span><span class="th-time">${tShort(c.at)}</span></div>
          <div class="th-body">${esc(c.body || '').replace(/\n/g, '<br>')}</div>${files(c.files)}</div></div>`; }
        const it = x.it; const reacts = groupReacts(it.reacts);
        return `<div class="th-item th-post${it.done ? ' done' : ''}${hide}" data-crow="${it.id}" id="thp${it.id}">
          ${ava(it.byId, it.by)}
          <div class="th-main">
            <div class="th-head">
              ${t.isDelivery ? `<input type="checkbox" class="act-chk" data-chk="${it.id}" ${it.done ? 'checked' : ''} title="Ενέργεια παράδοσης">` : ''}
              <b>${esc(it.by || 'Βήμα εργασίας')}</b>
              <span class="th-time">${tShort(it.at)}</span>
              <span style="flex:1"></span>
              <button type="button" class="th-btn" data-react="${it.id}" title="Αντίδραση">☺</button>
              <button type="button" class="th-btn" data-more="${it.id}" title="Ενέργειες πάνω σε αυτό">⋯</button>
            </div>
            <div class="act-body th-body" data-ctext="${it.id}">${it.fmt === 'html' ? cnpBalanced(it.title) : stepHtml(it.title)}</div>
            ${files(it.files)}
            ${reacts.length ? `<div class="th-reacts">${reacts.map(r => `<button type="button" class="th-react${r.mine ? ' mine' : ''}" data-rc="${r.code}" data-rid="${it.id}" title="${esc(r.who.join(', '))}">${REACT[r.code] || r.code} ${r.n}</button>`).join('')}</div>` : ''}
            <span hidden><button type="button" data-cedit="${it.id}"></button><button type="button" data-cattach="${it.id}"></button><button type="button" data-cdelstep="${it.id}"></button></span>
          </div></div>`;
      };
      return `<div class="card tk-step"><div class="card-h">💬 <b>Συζήτηση & ενέργειες</b>
      <span class="pill ${t.isDelivery ? (chkDone >= d.check.length && d.check.length ? 'pill-ok' : 'pill-mut') : 'pill-mut'}" style="flex:none">${t.isDelivery ? chkDone + '/' + d.check.length : d.check.length}</span>
      <span class="mut" style="font-weight:600;font-size:11px">— ποιος είπε τι και τι άλλαξε · <b>@Όνομα</b> ειδοποιεί · επικόλλησε εικόνα · <b>Enter</b> καταχωρεί</span></div>
      <div class="card-b">
        <div id="dCheck" class="th">
          ${hidden ? `<button type="button" class="th-more" id="thMore">${I.chev} ${hidden} παλαιότερα</button>` : ''}
          ${items.map(row).join('') || '<div class="mut" style="font-size:12.5px;padding:8px 2px">Καμία ενέργεια ακόμη — γράψε την πρώτη από κάτω.</div>'}
        </div>
        <div class="act-composer">
          ${actTbHtml()}
          <div class="act-edit" id="chkNew" contenteditable="true" data-ph="Τι έκανες ή τι πρέπει να γίνει… · @όνομα για να ειδοποιήσεις · επικόλλησε εικόνα με Ctrl+V"></div>
          ${/* ΚΑΤΑΧΩΡΕΙ ΤΟ ENTER. Το κουμπί ήταν μια τρίτη κίνηση για κάτι που
               γράφεις σαν μήνυμα — και κρατούσε ύψος που έσπρωχνε τη συζήτηση
               κάτω. Shift+Enter για νέα γραμμή, όπως παντού. */''}
          <div class="act-foot">
            <button type="button" class="btn btn-sm btn-o" id="chkClip" title="Επισύναψη αρχείου στη νέα ενέργεια">${I.clip}</button>
            <span class="mut" id="chkHint" style="font-size:11px;flex:1"></span>
            <span class="mut act-tip"><b>Enter</b> καταχωρεί · <b>Shift+Enter</b> νέα γραμμή</span>
            ${/* Στο κινητό το Enter είναι πλήκτρο νέας γραμμής — εκεί μένει κουμπί. */''}
            <button type="button" class="btn btn-sm btn-p act-send" id="chkGo" title="Καταχώρηση">${I.send}</button>
          </div>
          <input type="file" id="chkFile" multiple hidden>
        </div>
        ${d.legacyFiles && d.legacyFiles.length ? `<details class="tk-att" id="dCheckAtt" style="margin-top:10px">
          <summary>${I.clip} Παλαιά συνημμένα ενεργειών <b>${d.legacyFiles.length}</b></summary>
          <div id="dCheckFiles"><div class="mut" style="font-size:12px">Φόρτωση…</div></div></details>` : ''}
      </div></div>`;
    })()}

    ${t.done ? `<div class="card done-card"><div class="card-b">
      <b>✔ Ολοκληρώθηκε</b> <span class="mut">${esc(tShort(t.doneAt))}${t.doneBy ? ' — ' + esc(adminName(t.doneBy)) : ''}</span>
      ${t.doneNote ? `<div class="done-note">${esc(t.doneNote)}</div>` : '<div class="mut" style="font-size:12px;margin-top:4px">Χωρίς σημείωμα.</div>'}
      <div class="mut" style="font-size:12px;margin-top:7px">🔒 Κλειδωμένη — τίποτα δεν αλλάζει όσο είναι ολοκληρωμένη.
        Την πάτησες κατά λάθος; Το ξανάνοιγμα τη γυρίζει εκεί που ήταν.</div>
      <button class="btn btn-sm btn-p" id="dReopen" style="margin-top:9px">↩ Ξανάνοιγμα για επεξεργασία</button>
    </div></div>` : ''}

    <div class="card tk-side tk-time"><div class="card-h">⏱ Χρόνος
      <span class="mut" style="font-weight:600">${fmtMin(d.total)}${t.est ? ' / ~' + fmtMin(t.est) : ''}</span>
      ${d.timelogs.length > 3 ? `<span class="mut" style="margin-left:auto;font-size:11px">${d.timelogs.length} καταχωρήσεις</span>` : ''}</div>
    <div class="card-b">
      ${t.est ? `<div class="bar" style="margin-bottom:9px"><span class="${d.total > t.est ? 'bad' : d.total > t.est * .8 ? 'warn' : 'ok'}" style="width:${Math.min(100, Math.round(d.total / t.est * 100))}%"></span></div>` : ''}
      <div class="tk-time-row">
        <input class="inp" id="tMins" type="number" min="1" placeholder="λεπτά">
        ${d.owner ? `<label class="tk-bill" title="Χρεώσιμος χρόνος προς τον πελάτη — χρειάζεται έγκριση λογιστηρίου.&#10;Η επιλογή ΜΕΝΕΙ στην εργασία: ό,τι ορίσεις εδώ ισχύει και την επόμενη φορά."><input type="checkbox" id="tBill" ${billOn ? 'checked' : ''}> ${I.coin} Χρεώσιμο</label>` : ''}
        <input class="inp" id="tNote" placeholder="σημείωση">
        <button class="btn btn-sm btn-p" id="tAdd">Καταχώρηση</button>
      </div>
      ${!d.owner ? `<div class="mut" style="font-size:11px;margin-top:6px">${d.project.kind === 'internal'
        ? 'Εσωτερική ανάπτυξη — ο χρόνος μετριέται ως κόστος, δεν χρεώνεται σε πελάτη.'
        : 'Χωρίς πελάτη — ο χρόνος δεν χρεώνεται πουθενά.'}</div>` : ''}
      ${d.scClient ? `<div class="mut" style="font-size:11px;margin-top:6px">Πελάτης: <b>${esc(d.scClient)}</b> — τα χρεώσιμα αφαιρούν προαγορά</div>` : ''}
      ${billMins ? `<div class="bill-gate ${t.billOk ? 'ok' : ''}">
        <div><b>${t.billOk ? '✔ Η χρέωση εγκρίθηκε' : '⏳ Εκκρεμεί έγκριση χρέωσης'}</b>
          <div class="mut" style="font-size:11px">${t.billOk
            ? `${esc(t.billOkBy ? adminName(t.billOkBy) : '')}${t.billOkAt ? ' · ' + tShort(t.billOkAt) : ''}`
            : `${fmtMin(billMins)} χρεώσιμος χρόνος — η εργασία δεν κλείνει πριν εγκριθεί`}</div></div>
        ${(d.billApprover || {}).me
          ? `<button class="btn btn-sm ${t.billOk ? 'btn-o' : 'btn-p'}" id="dBillOk">${t.billOk ? 'Ανάκληση' : 'Έγκριση χρέωσης'}</button>`
          : `<span class="mut" style="font-size:11px;text-align:right">${(d.billApprover || {}).name
              ? 'εγκρίνει μόνο<br><b>' + esc(d.billApprover.name) + '</b>' : 'μόνο ο διαχειριστής'}</span>`}
      </div>` : ''}
      ${d.timelogs.length ? `<div class="mut" style="font-size:10.5px;margin-top:8px">Για να αλλάξεις χρέωση σε καταχώρηση που έγινε, πάτα το σημάδι «χρέωση» / «χωρίς χρέωση» δίπλα της.</div>
      <div style="margin-top:4px" id="tLogs">${d.timelogs.map(l =>
        `<div class="tk-log">
          <b>${l.running ? '▶ σε εξέλιξη' : fmtMin(l.mins)}</b>
          ${l.running || !d.owner ? '' : `<button type="button" class="pill ${l.billable ? 'pill-warn' : 'pill-mut'} tk-billtog"
            data-tbill="${l.id}" data-on="${l.billable ? 1 : 0}" style="font-size:9.5px"
            title="Κλικ για αλλαγή — χρεώσιμο ή όχι">${l.billable ? 'χρέωση ' + fmtMin(l.charged || l.mins) : 'χωρίς χρέωση'}</button>`}
          <span class="mut" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;min-width:0">${esc(l.by)}${l.note ? ' · ' + esc(l.note) : ''}</span>
          <span class="mut" style="margin-left:auto;flex:none">${tShort(l.at)}</span></div>`).join('')}</div>` : ''}
    </div></div>

    <div class="card tk-side"><div class="card-b">
      <div class="frow tk-frow">
        <div><label class="lbl">Ανάθεση <span class="mut" style="font-weight:400">— ποιος την εκτελεί</span></label>
          <select class="inp" id="fAssignee">${admOpts(t.assignee, t.dept)}</select></div>
        <div><label class="lbl">${I.zap} Μπάλα <span class="mut" style="font-weight:400">— ποιος δρα τώρα</span></label>
          <select class="inp" id="fBall"><option value="">— σε κανέναν —</option>
          ${S.boot.admins.map(a => `<option value="${a.id}" ${a.id === t.ball ? 'selected' : ''}>${esc(a.name)}${a.id === me.id ? ' (εσύ)' : ''}</option>`).join('')}</select>
          <div class="mut" style="font-size:11px;margin-top:3px">Όσο την κρατάς, η εργασία μένει στη «Μέρα μου» σου.</div></div>
        <div><label class="lbl">Προτεραιότητα</label>
          <select class="inp" id="fPrio">
            ${['Κανονική', 'Υψηλή', 'Κρίσιμη'].map((p, i) => `<option value="${i}" ${i === t.prio ? 'selected' : ''}>${p}</option>`).join('')}</select></div>
        <div><label class="lbl">Τύπος</label><select class="inp" id="fType"><option value="">— γενικό —</option>
          ${S.boot.types.map(ty => `<option value="${ty.id}" ${ty.id === t.type ? 'selected' : ''}>${esc(ty.name)}</option>`).join('')}</select></div>
        <div><label class="lbl">Department</label>
          <select class="inp" id="fDept"><option value="">— χωρίς department —</option>
          ${(d.depts || []).map(u => `<option value="${u.id}" ${u.id === t.dept ? 'selected' : ''}>${esc(u.name)}</option>`).join('')}</select></div>
        <div><label class="lbl">Έναρξη <span class="mut" style="font-weight:400">— ημ/νία &amp; ώρα</span></label>
          <div class="dt2"><input type="date" class="inp" id="fStart" value="${t.start || ''}">
            ${timeInput('fStartT', t.startT, 'title="Προαιρετικό — κενό = όλη η μέρα"')}</div></div>
        ${/* Η ΛΗΞΗ ΕΙΝΑΙ ΤΟΥ ΧΕΙΡΙΣΤΗ. Όποιος ανοίγει την εργασία ορίζει έναρξη και
             deadline· το «πότε θα τελειώσει» το δηλώνει αυτός που θα το κάνει.
             Ο server επιβάλλει τον ίδιο κανόνα — εδώ απλώς φαίνεται. */''}
        <div><label class="lbl">Λήξη <span class="mut" style="font-weight:400">— ημ/νία &amp; ώρα${dueLock ? ' · το δηλώνει ο χειριστής' : ''}</span></label>
          <div class="dt2"><input type="date" class="inp" id="fDue" value="${t.due || ''}" ${dueLock ? 'disabled' : ''}>
            ${timeInput('fDueT', t.dueT, (dueLock ? 'disabled ' : '') + 'title="Προαιρετικό — κενό = όλη η μέρα"')}</div>
          ${dueLock ? `<div class="mut" style="font-size:11px;margin-top:3px">Τη συμπληρώνει ο/η <b>${esc(adminName(dueHolder))}</b> — εσύ ορίζεις έναρξη και deadline.</div>` : ''}</div>
        <div><label class="lbl">${I.flag || ''} Deadline <span class="mut" style="font-weight:400">— δεν μετατίθεται άλλο</span></label>
          <input type="date" class="inp" id="fSched" value="${t.sched || ''}">
          <div class="mut" style="font-size:11px;margin-top:3px">Έναρξη/Λήξη = πότε θα δουλευτεί. Deadline = πότε ΠΡΕΠΕΙ να έχει τελειώσει.</div></div>
      </div>
      <label class="tk-offer" title="Σήμανε την εργασία ως σχετική με προσφορά — φαίνεται στις κάρτες και παίρνει προτεραιότητα">
        <input type="checkbox" id="fOffer" ${t.isOffer ? 'checked' : ''}> ${I.doc} <b>Αφορά προσφορά</b> <span class="mut">— προτεραιότητα</span></label>
      <div class="tk-offer-box" id="fOfferBox" ${t.isOffer ? '' : 'hidden'}>
        ${d.offer ? `<div class="tk-offer-lnk">${t.offerRef ? `<span class="mut" style="font-size:11.5px">Αρ. <b>${esc(t.offerRef)}</b></span>` : ''}<button type="button" class="pill pill-info" id="fOfferOpen" title="Άνοιγμα της προσφοράς">${I.doc} ${esc(d.offer.title)} · ${esc(d.offer.stageName)}${d.offer.amount ? ' · ' + fmtEur(d.offer.amount) : ''}</button>
            <button type="button" class="th-btn" id="fOfferUnlink" title="Λύσιμο από αυτή την προσφορά">✕</button></div>`
          : `<div class="tk-offer-ref"><label class="lbl" style="margin:0 0 3px">Αρ. προσφοράς</label>
              <input class="inp" id="fOfferRef" value="${esc(t.offerRef || '')}" placeholder="π.χ. CLD-2026-2600005 ή #57" title="Γράψε τον αριθμό/κωδικό της προσφοράς. Αν είναι δική μας, δένεται μόνη της.">
              <div class="mut" style="font-size:10.5px;margin-top:3px">Αποθηκεύεται με το «Αποθήκευση». Αν είναι δική μας προσφορά, δένεται αυτόματα.</div></div>
            <details class="tk-offer-more"><summary class="mut" style="cursor:pointer;font-size:11px">ή διάλεξε από τις προσφορές του πελάτη / νέα / ζήτα από συνάδελφο</summary>
            <select class="inp" id="fOfferSel" title="Δέσε με υπάρχουσα προσφορά του πελάτη" style="margin-top:6px"><option value="">— δέσε με υπάρχουσα προσφορά… —</option></select>
            <div class="tk-offer-acts">
              <button type="button" class="btn btn-sm btn-p" id="fOfferNew">${I.plus} Νέα προσφορά</button>
              <button type="button" class="btn btn-sm btn-o" id="fOfferAsk" title="Ζήτα από συνάδελφο να φτιάξει την προσφορά — θα ειδοποιηθεί και θα μείνει στις εκκρεμότητές του">📣 Ζήτα από συνάδελφο…</button>
            </div>
            ${d.offerReq ? `<div class="mut" style="font-size:11px;margin-top:5px">${d.offerReq.status === 'open' ? '⏳' : '✓'} Ζητήθηκε από <b>${esc(d.offerReq.by)}</b> προς <b>${esc(d.offerReq.to)}</b> · ${tShort(d.offerReq.at)}${d.offerReq.status === 'open' ? ' — εκκρεμεί' : ''}</div>` : ''}</details>`}
      </div>
      ${!d.owner ? `<div class="tk-src" title="Εσωτερική διαδικασία / R&D: δεν κρέμεται σε πελάτη — ο χρόνος μετρά ως κόστος, δεν χρεώνεται">
        <span class="mut">Αφορά:</span>
        <button type="button" class="src-chip${t.internal ? ' on' : ''}" id="fInternalChip">${I.box} Εσωτερικό / R&D</button>
        ${!t.internal ? '<span class="mut" style="font-size:11px">— ή δέσε την σε έργο πελάτη</span>' : ''}
        <input type="hidden" id="fInternal" value="${t.internal ? 1 : 0}">
      </div>` : ''}
      <div class="tk-src" title="Από ποιο κανάλι μας ήρθε το αίτημα">
        <span class="mut">Ήρθε από:</span>
        ${[['phone', '📞 Τηλεφωνική'], ['email', '✉ Email']].map(([k, lb]) =>
          `<button type="button" class="src-chip${t.source === k ? ' on' : ''}" data-src="${k}">${lb}</button>`).join('')}
        ${t.ticket ? `<span class="src-chip on" style="cursor:default" title="Δεμένη σε ticket">${I.ticket} Ticket</span>` : ''}
        <input type="hidden" id="fSource" value="${esc(t.source || '')}">
      </div>
      ${t.ticket
        ? `<label class="tk-tkref linked" title="Η εργασία είναι δεμένη σε ticket του WHMCS — ο αριθμός έρχεται από εκεί και δεν αλλάζει χειροκίνητα">
            ${I.ticket} <b>Ticket</b>
            <a class="tk-tkref-no" href="#/inbox/${t.ticket}" data-navclose>#${esc(tkD ? tkD.tid : t.ticket)}</a>
            <span class="mut" style="margin-left:auto">από το WHMCS — αυτόματα</span></label>`
        : `<label class="tk-tkref" title="Για εργασίες που ήρθαν από την ΠΑΛΙΑ πλατφόρμα ticket. Τα ticket του WHMCS συμπληρώνονται μόνα τους.">
            ${I.ticket} <b>Ticket</b>
            <input class="inp" id="fTkRef" placeholder="π.χ. 4821" maxlength="40" value="${esc(t.ticketRef || '')}">
            <span class="mut" style="margin-left:auto">παλιά πλατφόρμα</span></label>`}
      <label class="tk-est" title="Πόσο υπολογίζει ο τεχνικός ότι θα του πάρει. Φαίνεται στην κάρτα δίπλα στον πραγματικό χρόνο, και μετράει στον φόρτο της ομάδας.">
        ⏱ <b>Εκτίμηση</b>
        <input class="inp" id="fEst" inputmode="decimal" placeholder="π.χ. 1,5"
          value="${t.est ? String(Math.round(t.est / 60 * 100) / 100).replace('.', ',') : ''}">
        <span class="mut">ώρες</span>
        <span class="mut" id="fEstHint" style="margin-left:auto">${t.est ? '= ' + fmtMin(t.est) : ''}</span></label>
      ${/* Τα πεδία σώζονται μόνα τους· το κουμπί μένει για «τελείωσα, κλείσ᾽ το». */''}
      <div class="tk-auto"><span class="tk-auto-d"></span><span id="dAutoS">αποθηκεύεται μόνο του</span></div>
      ${/* ΧΩΡΙΣ ΚΟΥΜΠΙ ΑΠΟΘΗΚΕΥΣΗΣ. Αφού τα πεδία σώζονται μόνα τους, ένα κουμπί
           «Αποθήκευση» δεν έχει τι να κάνει — και όσο υπάρχει, σε βάζει να
           αναρωτιέσαι αν χρειάζεται να το πατήσεις. Κλείνεις με το ✕. */''}
      <div class="tk-actions">
        ${t.done ? '' : '<button class="btn btn-ok" id="dDone">✔ Ολοκλήρωση</button>'}
        ${t.done ? '' : `<button class="btn btn-o" id="dHand" title="Τελείωσε το δικό σου κομμάτι — δώσε τη σκυτάλη στον επόμενο">${I.zap} Παράδοση</button>`}
        ${d.canAsk ? `<button class="btn btn-o" id="dAskDelay"
          title="Στέλνει στον ${esc(d.holderName || '')} ερώτηση που ΠΡΕΠΕΙ να απαντηθεί — ο κύκλος κλείνει με την απάντησή του">❓ Γιατί καθυστερεί;</button>` : ''}
        <button class="btn btn-o" id="dHelp" title="Ζήτα ζωντανά τη βοήθεια συναδέλφου για αυτό">${I.sos} Βοήθεια</button>
        <button class="btn btn-o" id="dShare" title="Στείλε την εργασία σε συνάδελφο (chat) ή σε email">${I.link} Στείλε</button>
        ${d.canDelete ? `<button class="btn btn-o" id="dDel" style="color:var(--bad);margin-left:auto"
          title="${d.delLeft ? 'Την άνοιξες εσύ — μπορείς να τη διαγράψεις για ' + Math.ceil(d.delLeft / 60) + ' ακόμη λεπτά' : 'Διαγραφή εργασίας (διαχειριστής)'}">${I.trash} Διαγραφή</button>` : ''}
      </div>
      <div class="tk-pills">
        ${d.project.none
          ? '<span class="pill pill-mut" title="Η εργασία ανήκει μόνο σε department, δεν είναι μέρος έργου">Χωρίς έργο</span>'
          : `<a class="pill pill-mut" href="#/board/${d.project.id}" data-navclose title="Board του έργου">${I.board} ${esc(d.project.name)}</a>`}
        ${(() => { const u = (d.depts || []).find(x => x.id === t.dept); return u ? `<a class="pill pill-mut" href="#/unit/${u.id}" data-navclose title="Εργασίες του department">${esc(u.name)}</a>` : ''; })()}
      </div>
    </div></div>

    <div class="card tk-side"><div class="card-h">${I.link} Εξαρτήσεις <span class="mut" style="font-weight:600;font-size:11px">— πρέπει να τελειώσουν πρώτα</span></div>
      <div class="card-b" id="dDeps">
      ${(d.deps || []).map(dp => `<div style="display:flex;gap:8px;align-items:center;padding:3px 0;font-size:12.5px">
        <span>${dp.done ? '✅' : '⏳'}</span>
        <a style="flex:1;cursor:pointer;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" data-dgo="${dp.id}">${esc(dp.title)}</a>
        <button class="btn btn-sm btn-o" data-ddel="${dp.depId}">✕</button></div>`).join('')}
      <div style="display:flex;gap:7px;margin-top:6px">
        <select class="inp" id="depSel" style="flex:1;font-size:12px;padding:6px 8px"><option value="">— διάλεξε task που μας μπλοκάρει —</option></select>
        <button class="btn btn-sm btn-o" id="depAdd">+</button></div>
    </div></div>

    ${tkD ? (() => {
      const tk = tkD;
      const md = (window.CNP && window.CNP.mdToHtml) || (x => esc(x).replace(/\n/g, '<br>'));
      const wl = h => h >= 48 ? Math.floor(h / 24) + ' ημέρες' : (h >= 24 ? '1 ημέρα' : h + 'ω');
      return `<details class="card tkbox tk-tkd">
        <summary class="card-h" style="cursor:pointer">${I.ticket} <b>Ticket #${esc(tk.tid)}</b>
          <span style="font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;min-width:0">${esc(tk.title)}</span>
          <span class="pill pill-info" style="margin-left:auto;flex:none">${esc(tk.status)}</span></summary>
        <div class="card-b">
          <div class="tkmeta">
            ${tk.client ? `<span>${I.user} ${esc(tk.client)}</span>` : ''}
            ${tk.email ? `<span class="mut">${esc(tk.email)}</span>` : ''}
            ${tk.dept ? `<span class="mut">${esc(tk.dept)}</span>` : ''}
            ${tk.urgency === 'High' ? '<span class="pill pill-bad">επείγον</span>' : ''}
            ${tk.status !== 'Closed' ? (tk.waitOn === 'us'
              ? `<span class="pill pill-warn">περιμένει εμάς · ${wl(tk.waitH)}</span>`
              : `<span class="pill pill-mut">περιμένει πελάτη · ${wl(tk.waitH)}</span>`) : ''}
          </div>
          <div class="tkthread">
            ${(tk.msgs || []).map(m => `<div class="tkmsg ${m.us ? 'us' : ''}">
              <div class="tkmsg-h"><b>${esc(m.by)}</b><span class="mut">${esc(tShort(m.at))}</span></div>
              <div class="tkmsg-b">${md(m.body)}</div>
              <button class="tkmore" type="button" hidden>περισσότερα ▾</button></div>`).join('')}
          </div>
          ${tk.total > (tk.msgs || []).length
            ? `<div class="mut" style="font-size:11.5px;margin-top:6px">…και ${tk.total - tk.msgs.length} παλαιότερα μηνύματα</div>` : ''}
          <div style="display:flex;gap:8px;margin-top:9px;flex-wrap:wrap">
            <button class="btn btn-sm btn-p" data-ibgo="${tk.id}">${I.ticket} Άνοιγμα &amp; απάντηση</button>
            ${tk.clientId ? `<button class="btn btn-sm btn-o" data-c360="${tk.clientId}">${I.user} Πελάτης 360°</button>` : ''}
          </div>
        </div></details>`;
    })() : ''}

    <details class="card"><summary class="card-h" style="cursor:pointer">${I.clock} Ιστορικό (${d.activity.length})</summary>
      <div class="card-b">${d.activity.map(a => `<div style="font-size:12px;padding:4px 0;border-bottom:1px dashed var(--line)">
        <b>${esc(a.detail || a.action)}</b> <span class="mut">— ${esc(a.by)} · ${tShort(a.at)}</span></div>`).join('')}</div></details>
  </div>`;
  document.body.append(ovl, dr);
  cnpTaskDraftPut(dr, draft);      // ό,τι ήταν μισογραμμένο πριν τον ξανασχεδιασμό, γυρίζει στη θέση του
  /* Δύο στήλες. Αριστερά (main): ΖΗΤΟΥΜΕΝΟ πάνω και από κάτω οι ΕΝΕΡΓΕΙΕΣ — το
     μόνο που κυλά είναι η λίστα ενεργειών. Δεξιά (side), συμπαγώς και χωρίς
     scroll: χρόνος, πεδία, εξαρτήσεις, ticket (κλειστό), ιστορικό (κλειστό).
     Τίτλος/πελάτης/κατάσταση ζουν ΜΟΝΟ στην κεφαλίδα — όχι δεύτερη φορά. */
  (() => {
    const body = $('.tk-modal-b', dr); if (!body) { return; }
    const main = document.createElement('div'); main.className = 'tk-col-main';
    const side = document.createElement('div'); side.className = 'tk-col-side';
    const toMain = el => el.classList.contains('tk-brief') || el.classList.contains('tk-step');
    [...body.children].forEach(el => (toMain(el) ? main : side).appendChild(el));
    body.append(main, side);
    /* Κινητό (21/9/2026): η κύρια στήλη κατέρρεε σε 2px (flex μέσα σε στήλη) και το ζητούμενο +
       η συζήτηση ήταν ΑΟΡΑΤΑ. Τώρα δύο καρτέλες: «Συζήτηση» (ζητούμενο + ενέργειες) και
       «Στοιχεία» (χρόνος, ανάθεση, ημερομηνίες, ενέργειες). */
    if (matchMedia('(max-width:900px)').matches) {
      const tabs = document.createElement('div');
      tabs.className = 'tk-mtabs';
      tabs.innerHTML = `<button type="button" class="on" data-mt="main">${I.chat} Συζήτηση</button><button type="button" data-mt="side">${I.list} Στοιχεία</button>`;
      body.before(tabs);
      const setTab = k => { body.classList.toggle('tk-mob-side', k === 'side'); tabs.querySelectorAll('[data-mt]').forEach(b => b.classList.toggle('on', b.dataset.mt === k)); body.scrollTop = 0; };
      tabs.querySelectorAll('[data-mt]').forEach(b => b.onclick = () => setTab(b.dataset.mt));
      /* Το «Αποθήκευση» ζει στα Στοιχεία: όταν υπάρχουν αλλαγές, το tab παίρνει κουκκίδα. */
      new MutationObserver(() => { tabs.querySelector('[data-mt="side"]').classList.toggle('dirty', dr.dataset.dirty === '1'); }).observe(dr, {attributes: true, attributeFilter: ['data-dirty']});
      /* Μεγάλο ζητούμενο: διπλώνει στις ~9 γραμμές με «Δείξε όλο», για να φτάνεις γρήγορα στη συζήτηση. */
      setTimeout(() => {
        const ro = main.querySelector('.tk-brief-ro, .tk-brief .rte');
        if (ro && ro.scrollHeight > 300) {
          ro.classList.add('tk-brief-fold');
          const more = document.createElement('button'); more.type = 'button'; more.className = 'btn btn-o btn-sm tk-brief-more'; more.textContent = 'Δείξε όλο το ζητούμενο';
          ro.after(more);
          more.onclick = () => { const on = ro.classList.toggle('tk-brief-fold'); more.textContent = on ? 'Δείξε όλο το ζητούμενο' : 'Σύμπτυξη'; };
        }
      }, 50);
    }
    /* Μεγέθυνση (πρόταση Διονύση): με ένα κλικ στην κεφαλίδα των «Ενεργειών» το
       ζητούμενο και η δεξιά στήλη μαζεύονται, και οι ενέργειες παίρνουν ΟΛΗ την
       καρτέλα — για να διαβάζεται μια μεγάλη απάντηση χωρίς κύλιση. Θυμάται. */
    const steps = main.querySelector('.tk-step');
    const hdr = steps && steps.querySelector('.card-h');
    if (hdr) {
      const btn = document.createElement('button');
      btn.type = 'button'; btn.className = 'tk-step-max';
      const sync = () => {
        const on = body.classList.contains('tk-steps-max');
        btn.innerHTML = on ? '⤡' : '⤢';
        btn.title = on ? 'Επαναφορά — ξαναφέρνει το ζητούμενο και τα πεδία' : 'Μεγέθυνση σε όλη την καρτέλα';
        btn.setAttribute('aria-label', btn.title);
      };
      const set = on => {
        body.classList.toggle('tk-steps-max', on);
        try { localStorage.setItem('cnpStepsMax', on ? '1' : '0'); } catch (e) {}
        sync();
      };
      btn.onclick = e => { e.stopPropagation(); set(!body.classList.contains('tk-steps-max')); };
      hdr.appendChild(btn);
      hdr.onclick = e => { if (!e.target.closest('button,a,input')) { set(!body.classList.contains('tk-steps-max')); } };
      hdr.style.cursor = 'pointer';
      let want = '0';
      try { want = localStorage.getItem('cnpStepsMax') || '0'; } catch (e) {}
      if (want === '1') { body.classList.add('tk-steps-max'); }
      sync();
    }
  })();
  requestAnimationFrame(() => { ovl.classList.add('show'); dr.classList.add('show'); });

  { const ic = $('#fInternalChip', dr); if (ic) { ic.onclick = () => {
      const h = $('#fInternal', dr); const on = !(+h.value); h.value = on ? 1 : 0; ic.classList.toggle('on', on); markDirty(dr); }; } }
  /* Πρόχειρο (μόλις δημιουργήθηκε από την παλέτα): το ✕ ρωτά «να κρατηθεί;».
     «Όχι» = σβήνεται αθόρυβα, σαν να μην άνοιξε ποτέ. «Ναι» = αποθηκεύεται ό,τι γράφτηκε. */
  dr.dataset.fresh = opts.fresh ? '1' : '';
  const askFresh = async () => {
    const r = await cnpDialog({title: I.alert + ' Να κρατηθεί η εργασία;',
      body: 'Μόλις τη δημιούργησες και δεν πάτησες «Αποθήκευση». Αν την άνοιξες κατά λάθος, πες «Όχι» και θα σβηστεί.',
      ok: 'Ναι, κράτα την', cancel: 'Συνέχεια επεξεργασίας', third: 'Όχι, σβήσ’ την'});
    if (r === false || r === null) { return false; }
    if (r === 'third') {
      const x = await api('task_delete', {id, draft: 1}).catch(e => ({err: e && e.message}));
      if (x && x.err) { toast(x.err, true); return false; }
      dr.dataset.fresh = ''; dr.dataset.dirty = ''; closeDrawer(); toast('Η εργασία δεν κρατήθηκε');
      if (S.view === 'board') { vBoard(); } else if (S.view === 'myday') { vMyDay(); }
      return true;
    }
    dr.dataset.fresh = ''; dr.dataset.dirty = '';
    if (dr._saveNow) { await dr._saveNow(); }
    closeDrawer();
    if (S.view === 'board') { vBoard(); } else if (S.view === 'myday') { vMyDay(); }
    return true;
  };
  /* ΤΙ ΜΠΟΡΕΙ ΠΡΑΓΜΑΤΙΚΑ ΝΑ ΧΑΘΕΙ. Τα πεδία είναι ήδη αποθηκευμένα, οπότε η παλιά
     ερώτηση «έχεις μη αποθηκευμένες αλλαγές» δεν ισχύει πια. Το μόνο που χάνεται
     στο κλείσιμο είναι ένα μήνυμα που έγραψες και δεν πάτησες Enter. */
  dr._askClose = async () => {
    if (dr.dataset.fresh === '1') { return askFresh(); }
    const ed = $('#chkNew', dr);
    const html = ed ? ed.innerHTML.trim() : '';
    if (html && html !== '<br>') {
      const r = await cnpDialog({
        title: '💬 Μισογραμμένο μήνυμα',
        body: 'Έγραψες κάτι στη συζήτηση και δεν το καταχώρησες. Τα υπόλοιπα πεδία είναι ήδη αποθηκευμένα.',
        ok: 'Καταχώρησέ το', cancel: 'Συνέχεια επεξεργασίας', third: 'Πέτα το', thirdPlain: true});
      if (r === false || r === null) { return false; }
      if (r !== 'third') {
        const ra = await api('check_add', {task: id, title: html, html: 1}).catch(er => ({err: er, er}));
        if (ra && ra.err) { if (!(await cnpCodeRefused(ra.er))) { toast('Δεν καταχωρήθηκε', true); } return false; }
      }
      ed.innerHTML = '';
      closeDrawer();
      if (S.view === 'board') { vBoard(); } else if (S.view === 'myday') { vMyDay(); }
      return true;
    }
    return cnpAskClose(dr);
  };
  $('#dX', dr).onclick = () => dr._askClose();
  /* Χωρίς «Board: επεξεργασία» και χωρίς να είναι δική του εργασία (ανάδοχος/
     επιβλέπων/δημιουργός), η καρτέλα είναι ΜΟΝΟ για διάβασμα — ό,τι θα απέρριπτε
     ο server δεν προσφέρεται καν (12/9/2026). Κρύβουμε αντί να αφαιρούμε, ώστε
     οι handlers από κάτω να δένουν χωρίς σφάλμα. */
  const canWork = !!(me.full || cnpCan('projects.board.edit') || [t.assignee, t.creator, t.ball].includes(me.id));
  if (!canWork) {
    ['#dDone', '#dAsk', '#dAskDelay', '#dTitleEdit', '#tStart', '#tStop', '#depAdd', '#dBillOk']
      .forEach(sel => { const e = $(sel, dr); if (e) { e.style.display = 'none'; } });
    /* Σε καρτέλα μόνο-προβολής η ένδειξη «αποθηκεύεται μόνο του» θα ήταν ψέμα. */
    { const au = $('.tk-auto', dr); if (au) { au.style.display = 'none'; } }
    $$('.tk-time-row, .tk-step-foot, [data-ddel]', dr).forEach(e => { e.style.display = 'none'; });
    /* Το πεδίο των ενεργειών κρύβεται — αλλά χωρίς εξήγηση μοιάζει με βλάβη. */
    const sl = $('#dCheck', dr);
    if (sl) {
      sl.insertAdjacentHTML('afterend', '<div class="mut" style="padding:10px 14px;font-size:12px;'
        + 'border-top:1px solid var(--line)">🔒 Δεν μπορείς να γράψεις εδώ: η εργασία δεν είναι δική σου '
        + '(ανάθεση/επίβλεψη/δημιουργία) και δεν έχεις «Board: επεξεργασία».</div>');
    }
    $$('#dCheck input[data-chk], .tk-frow .inp, #fOffer', dr).forEach(e => { e.disabled = true; });
    const stp = $('#dStPill', dr); if (stp) { stp.disabled = true; stp.style.cursor = 'default'; stp.textContent = stp.textContent.replace(' ▾', ''); }
    const ro = $('#fDescr', dr); if (ro) { ro.contentEditable = 'false'; }
    const bh = $('.tk-brief .card-h', dr);
    if (bh) { bh.insertAdjacentHTML('beforeend', '<span class="pill pill-mut" style="margin-left:auto;flex:none" title="Χρειάζεται δικαίωμα «Board: επεξεργασία» — ή να είναι δική σου εργασία">μόνο προβολή</span>'); }
  }
  /* Αλλάζεις department → αλλάζουν και οι υποψήφιοι για ανάθεση. */
  const fDep = $('#fDept', dr);
  if (fDep) fDep.onchange = () => {
    ['fAssignee'].forEach(id => {
      const el = $('#' + id, dr); if (!el) { return; }
      const keep = el.value;
      el.innerHTML = admOpts(keep, +fDep.value || 0);
      el.value = keep;
    });
  };
  /* Τα «ψίχουλα» (πελάτης / έργο / department) βγάζουν εκτός εργασίας — αν έμενε
     ανοιχτό το drawer, θα σκέπαζε την οθόνη στην οποία μόλις πήγες. */
  $$('[data-navclose]', dr).forEach(a => a.addEventListener('click', () => closeDrawer()));
  /* Η αλλαγή αποθηκεύεται ΑΜΕΣΩΣ στην εργασία — αλλιώς ο χειριστής ξετσεκάρει,
     κάτι ξαναχτίζει την καρτέλα και το «χρεώσιμο» επιστρέφει σαν να μην πάτησε ποτέ. */
  { const tb = $('#tBill', dr);
    if (tb) { tb.onchange = async () => {
      const on = tb.checked;
      const r = await api('save_task', {task: id, bill_default: on ? 1 : 0})
        .catch(e => ({err: (e && e.message) || 'σφάλμα'}));
      if (r && r.err) { tb.checked = !on; toast(r.err, true); return; }
      t.billDefault = on ? 1 : 0;
      toast(on ? 'Ο χρόνος αυτής της εργασίας χρεώνεται' : 'Ο χρόνος αυτής της εργασίας ΔΕΝ χρεώνεται');
    }; } }
  { const fe = $('#fEst', dr), fh = $('#fEstHint', dr);
    if (fe && fh) { fe.oninput = () => { const m = estMins(fe.value); fh.textContent = m ? '= ' + fmtMin(m) : ''; }; } }
  /* ΑΠΟΘΗΚΕΥΕΤΑΙ ΜΟΝΟ ΤΟΥ (23/09/2026). Η ίδια διαδικασία εξυπηρετεί και το κουμπί
     και την αυτόματη αποθήκευση: ίδιος έλεγχος, ίδιοι διάλογοι (ημερομηνίες
     υλοποίησης, σύγκρουση ώρας), ίδια μηνύματα. Δύο δρόμοι αποθήκευσης θα
     απέκλιναν την πρώτη φορά που άλλαζε κανόνας σε έναν από τους δύο. */
  const saveFields = async (o0) => {
    const opts = o0 || {};
    const payload = over => Object.assign({task: id,
      due: $('#fDue', dr).value || null, sched: $('#fSched', dr).value || null, start: $('#fStart', dr).value || null,
      startT: $('#fStartT', dr) ? ($('#fStartT', dr).value || null) : undefined,
      dueT: $('#fDueT', dr) ? ($('#fDueT', dr).value || null) : undefined,
      type: +$('#fType', dr).value || 0,
      dept: +(($('#fDept', dr) || {}).value) || 0,
      assignee: +$('#fAssignee', dr).value || 0, prio: +$('#fPrio', dr).value,
      ball: $('#fBall', dr) ? (+$('#fBall', dr).value || 0) : undefined,
      is_offer: ($('#fOffer', dr) && $('#fOffer', dr).checked) ? 1 : 0,
      offer_ref: $('#fOfferRef', dr) ? $('#fOfferRef', dr).value.trim() : undefined,
      internal: $('#fInternal', dr) ? (+$('#fInternal', dr).value ? 1 : 0) : undefined,
      source: $('#fSource', dr) ? $('#fSource', dr).value : undefined,
      est: estMins(($('#fEst', dr) || {}).value),
      /* «Αποθήκευση» σώζει ΟΛΑ όσα άλλαξαν στην καρτέλα — και το ζητούμενο, αν
         το επεξεργάστηκε ο συντάκτης του. Αλλιώς το κείμενο χανόταν όταν το
         «Αποθήκευση» πατιόταν από την ερώτηση του ✕. */
      descr: (canEditBrief && $('#fDescr', dr) && rteVal('fDescr') !== (d.descr || '')) ? rteVal('fDescr') : undefined,
      ticket_ref: $('#fTkRef', dr) ? $('#fTkRef', dr).value : undefined}, over || {});

    /* Μισογραμμένη ενέργεια στον συνθέτη: καταχωρείται κι αυτή, δεν πετιέται.
       ΜΟΝΟ όταν πατάς «Αποθήκευση» — η αυτόματη δεν στέλνει μισές προτάσεις. */
    if (opts.composer) { const ed = $('#chkNew', dr); const html = ed ? ed.innerHTML.trim() : '';
      if (html && html !== '<br>') {
        const ra = await api('check_add', {task: id, title: html, html: 1}).catch(er => ({err: er && er.message, er}));
        if (ra && ra.err) { if (!(await cnpCodeRefused(ra.er))) { toast('Η ενέργεια δεν καταχωρήθηκε: ' + ra.err, true); } return; }
        ed.innerHTML = '';
      } }
    let r = await api('save_task', payload()).then(d => ({ok: true, res: d}))
      .catch(e => ({ok: false, error: e && e.message, data: e && e.data}));

    /* Ανάθεση σε agent χωρίς χρόνο υλοποίησης: δεν αρκεί να το απαγορεύσουμε —
       ο χειριστής έχει δύο νόμιμες προθέσεις και πρέπει να διαλέξει ρητά.
       Ή το κρατά πρόχειρο στον εαυτό του, ή το αναθέτει με σαφείς ημερομηνίες. */
    let extra = {};
    for (let round = 0; round < 4 && !r.ok && r.data; round++) {
      if (r.data.need === 'dates') {
        const pick = await askImplDates(r.data, me);
        if (!pick) { return; }                     // άκυρο = δεν αποθηκεύεται τίποτα
        extra = Object.assign(extra, pick);
        if (pick.assignee === me.id) { extra.force = 1; }   // πρόχειρο σε μένα: χωρίς έλεγχο διαθεσιμότητας
      } else if (r.data.need === 'conflict') {
        const what = await askBusy(r.data);
        if (!what) { return; }
        if (what === 'force') { extra.force = 1; }
        else {
          /* «Άλλαξε ώρες»: ξαναζητάμε διάστημα, κρατώντας ό,τι είχε γράψει. */
          const again = await askImplDates(Object.assign({}, r.data, extra,
            {error: 'Διάλεξε διάστημα που δεν πέφτει πάνω στα υπόλοιπα.'}), me);
          if (!again) { return; }
          extra = Object.assign(extra, again);
        }
      } else { break; }
      r = await api('save_task', payload(extra)).then(() => ({ok: true}))
        .catch(e => ({ok: false, error: e && e.message, data: e && e.data}));
    }
    if (r.ok && extra.assignee === me.id) { toast('Κρατήθηκε πρόχειρο σε εσένα'); }
    if (!r.ok) { toast(r.error || 'Δεν αποθηκεύτηκε', true); return false; }
    dr.dataset.fresh = ''; dr.dataset.dirty = '';
    /* ΤΟ ΣΤΑΜΑΤΗΜΑ ΤΟΥ ΧΡΟΝΟΥ ΔΕΝ ΓΙΝΕΤΑΙ ΣΙΩΠΗΛΑ. Ο server κόβει το χρονόμετρο
       όταν παραδίδεις την μπάλα — αν δεν το πει, ο χειριστής νομίζει ότι μετράει
       ακόμη και δεν ξέρει πόσα γράφτηκαν. */
    const bs = r.res && r.res.ballStopped;
    if (bs) {
      toast('Ο χρόνος σου σταμάτησε — ' + fmtMin(bs.mins) + ' · η μπάλα πήγε στον/στην ' + bs.to);
    }
    if (!opts.close) { return true; }
    if (!bs) { toast('Αποθηκεύτηκε'); }
    closeDrawer(); if (S.view === 'board') vBoard(); if (S.view === 'myday') vMyDay();
    return true;
  };
  dr._saveNow = () => saveFields({close: false, composer: false});

  /* ── Αυτόματη αποθήκευση των πεδίων ──────────────────────────────────────
     Δεν χρειάζεται να θυμάσαι να πατήσεις τίποτα: αλλάζεις, σώζεται. Οι λίστες
     και οι ημερομηνίες σώζονται μόλις αλλάξουν· τα πεδία που πληκτρολογείς
     περιμένουν λίγο να τελειώσεις. Αν ο server ζητήσει κάτι (ημερομηνίες
     υλοποίησης, σύγκρουση ώρας), εμφανίζεται ο ίδιος διάλογος με πριν. */
  {
    const st = $('#dAutoS', dr), box = st && st.parentElement;
    const mark = k => {
      if (!st || !box) { return; }
      box.dataset.s = k;
      st.textContent = k === 'saving' ? 'αποθήκευση…'
        : k === 'saved' ? 'αποθηκεύτηκε'
        : k === 'error' ? 'δεν αποθηκεύτηκε — δοκίμασε «Αποθήκευση»' : 'αποθηκεύεται μόνο του';
      if (k === 'saved') { clearTimeout(box._t); box._t = setTimeout(() => mark('idle'), 2200); }
    };
    let tmr = null, busy = false, again = false;
    const run = async () => {
      /* Κλειδωμένη καρτέλα (ολοκληρωμένη ή ξένη): ο server θα το απέρριπτε ούτως
         ή άλλως — δεν στέλνουμε για να μην κοκκινίζει η ένδειξη χωρίς λόγο. */
      if (dr.classList.contains('tk-locked')) { return; }
      if (busy) { again = true; return; }
      busy = true; mark('saving');
      let ok = false;
      try { ok = await saveFields({close: false, composer: false}); } catch (e) { ok = false; }
      busy = false;
      mark(ok ? 'saved' : 'error');
      if (again) { again = false; run(); }
    };
    const bump = ms => { clearTimeout(tmr); tmr = setTimeout(run, ms); };
    dr._autosave = () => bump(0);
    ['#fAssignee', '#fBall', '#fPrio', '#fType', '#fDept', '#fStart', '#fStartT', '#fDue', '#fDueT',
      '#fSched', '#fOffer', '#fInternal', '#fSource', '#fOfferRef', '#fEst', '#fTkRef'].forEach(sel => {
      const e = $(sel, dr);
      if (!e || e.disabled) { return; }
      const quick = e.tagName === 'SELECT' || e.type === 'date' || e.type === 'time' || e.type === 'checkbox';
      e.addEventListener('change', () => bump(quick ? 120 : 600));
      if (!quick) { e.addEventListener('input', () => bump(900)); }
    });
    /* Και το ζητούμενο, που δεν έχει πια δικό του κουμπί. */
    const fd = $('#fDescr', dr);
    if (fd && fd.isContentEditable) { fd.addEventListener('input', () => bump(1400)); }
  }
  /* Ανοίγει «καθαρό»: μέχρι να αλλάξει κάτι, το κουμπί είναι γκρίζο. */
  _cnpPaintSave(dr);
  /* Το «ζητούμενο» έχει δικό του πλήκτρο αποθήκευσης (μόνο για δημιουργό/Full),
     ώστε ο συντάκτης να σώζει το κείμενο χωρίς να κλείνει το παράθυρο. */
  /* Αλλαγή κατάστασης από το κουμπί της κεφαλίδας (μία μόνο θέση): ισχύει αμέσως.
     Αν η νέα κατάσταση είναι τελική, ζητάει δυο λόγια για το πώς έκλεισε. */
  const stPill = $('#dStPill', dr); if (stPill) stPill.onclick = () => {
    miniMenu(stPill, S.boot.statuses.map(s => ({dot: s.color || '#8291a9', label: s.title + (s.done ? ' ✔' : ''), on: async () => {
      const to = s.id; if (to === t.status) { return; }
      let note = '';
      if (s.done) { note = await askDone(dr.dataset.title || t.title); if (note === null) { return; } }
      const r = await cnpMoveTask(id, to, note);
      if (!r.ok) { if (!r.cancelled) { toast(r.error || 'Δεν επιτρέπεται', true); } return; }
      toast('Κατάσταση: ' + (statusOf(to).title || '—'));
      openTask(id);
      if (S.view === 'board') { vBoard(); } else if (S.view === 'myday') { vMyDay(); }
    }})));
  };

  /* Ολοκλήρωση με δυο λόγια. Στέλνει τη μετακίνηση στην «τελική» στήλη — έτσι
     καθαρίζει και η μπάλα, και η εργασία φεύγει από «Η μέρα μου». */
  const dn = $('#dDone', dr); if (dn) dn.onclick = async () => {
    const fin = doneStatus();
    if (!fin) { toast('Δεν υπάρχει στήλη ολοκλήρωσης', true); return; }
    const note = await askDone((dr.dataset.title || t.title));
    if (note === null) { return; }
    const r = await cnpMoveTask(id, fin.id, note);
    if (!r.ok) { if (!r.cancelled) { toast(r.error || 'Δεν επιτρέπεται', true); } return; }
    toast('Ολοκληρώθηκε'); closeDrawer();
    if (S.view === 'board') { vBoard(); } else if (S.view === 'myday') { vMyDay(); } else if (window.R && window.R[S.view]) { window.R[S.view](); }
  };
  const rop = $('#dReopen', dr); if (rop) rop.onclick = async () => {
    /* Ο server ξέρει πού ήταν πριν κλείσει — δεν τη ρίχνουμε στο Backlog. */
    const r = await api('task_reopen', {task: id}).catch(e => ({err: e.message}));
    if (r.err) { toast(r.err, true); return; }
    toast('Ξανάνοιξε' + (r.statusTitle ? ' — επέστρεψε σε «' + r.statusTitle + '»' : ''));
    openTask(id);
  };
  /* Το «περισσότερα» εμφανίζεται μόνο όταν το μήνυμα ξεπερνά το ύψος — αλλιώς
     θα ήταν κουμπί που δεν κάνει τίποτα. */
  $$('.tkmsg', dr).forEach(m => {
    const body = m.querySelector('.tkmsg-b'), btn = m.querySelector('.tkmore');
    if (!body || !btn) { return; }
    if (body.scrollHeight > body.clientHeight + 4) {
      btn.hidden = false;
      btn.onclick = () => {
        const open = m.classList.toggle('open');
        btn.textContent = open ? 'λιγότερα ▴' : 'περισσότερα ▾';
      };
    }
  });

  const bok = $('#dBillOk', dr);
  if (bok) bok.onclick = async () => {
    const r = await api('task_billing_ok', {task: id, ok: !t.billOk}).catch(e => ({err: e.message}));
    if (r.err) { toast(r.err, true); return; }
    toast(r.billOk ? 'Η χρέωση εγκρίθηκε' : 'Η έγκριση ανακλήθηκε');
    openTask(id);
  };
  $('#dWatch', dr).onclick = async () => {
    const r = await api('watch', {task: id});
    toast(r.watching ? 'Θα ειδοποιείσαι σε κάθε αλλαγή αυτής της εργασίας'
                     : 'Δεν θα ειδοποιείσαι πια για αυτή την εργασία'); openTask(id);
  };
  /* ΟΧΙ «ping». Ένα ping το αγνοείς· αυτό είναι ΕΡΩΤΗΣΗ που μένει ανοιχτή μέχρι
     να απαντηθεί, φαίνεται στα «Αιτήματα» του χειριστή και στο «Τι να προσέξεις»
     και των δύο — δικό του ως αναπάντητη, δικό μου ως «δεν μου απάντησε». */
  /* ΟΧΙ «#dAsk»: το id το κρατά ήδη το κουμπί του κυκλώματος υπέρβασης, και ο
     querySelector έπιανε ΕΚΕΙΝΟ — το κλικ άνοιγε άλλον διάλογο. Ίδιο λάθος με
     το «ibClose» των tickets· γι' αυτό κάθε νέο κουμπί παίρνει δικό του id. */
  const ask = $('#dAskDelay', dr);
  if (ask) { ask.onclick = async () => {
    const why = await cnpDialog({title: '❓ Γιατί καθυστερεί;',
      body: `Θα σταλεί στον <b>${esc(d.holderName || '')}</b> ως ερώτηση που πρέπει να απαντήσει.`
        + ' Μέχρι να απαντήσει, μένει ανοιχτή και στους δύο σας.',
      input: 'Τι κρατά αυτή την εργασία; Χρειάζεσαι κάτι για να προχωρήσει;',
      rows: 3, max: 500, ok: 'Στείλε την ερώτηση', cancel: 'Άκυρο'});
    if (why === null || !String(why).trim()) { return; }
    try {
      const r = await api('team_ask', {id: d.holder, task: id, message: String(why).trim()});
      toast('Στάλθηκε στον ' + (r.toName || '') + ' — θα φανεί όταν απαντήσει');
    } catch (e) { toast(e.message, 1); }
  }; }
  /* Ένα αίτημα έρχεται από ΕΝΑ κανάλι — τα chips είναι αμοιβαία αποκλειόμενα,
     και ξανακλικ το καθαρίζει (μπορεί να μπήκε κατά λάθος). */
  $$('[data-src]', dr).forEach(b => b.onclick = () => {
    const cur = $('#fSource', dr).value;
    const nv = cur === b.dataset.src ? '' : b.dataset.src;
    $('#fSource', dr).value = nv;
    $$('[data-src]', dr).forEach(x => x.classList.toggle('on', x.dataset.src === nv));
    markDirty();
  });
  const dhl = $('#dHelp', dr); if (dhl) dhl.onclick = () => window.CNP.quickHelp && window.CNP.quickHelp({task: id, taskTitle: t.title});

  /* ── Στείλε την εργασία ───────────────────────────────────────────────────
     Δύο διαφορετικές ανάγκες με ίδια αφετηρία: «δες το αυτό» σε συνάδελφο
     (chat, άμεσο) και «κράτα το αυτό» προς τα έξω (email). Το μήνυμα κουβαλά
     σύνοψη — έργο, πελάτη, κατάσταση, λήξη — ώστε ο παραλήπτης να καταλαβαίνει
     ΤΙ του στέλνεις χωρίς να ανοίξει τίποτα. */
  const shb = $('#dShare', dr); if (shb) shb.onclick = () => {
    const others = S.boot.admins.filter(a => a.id !== me.id);
    const ovl = document.createElement('div');
    ovl.className = 'ovl show'; ovl.style.zIndex = 320;
    ovl.innerHTML = `<div class="pal-box" style="margin:8vh auto 0;max-width:560px;max-height:84vh;overflow:auto" role="dialog">
      <div style="padding:20px 22px 18px">
        <b style="font-size:15.5px;color:var(--ink)">${I.link} Στείλε την εργασία</b>
        <div class="mut" style="font-size:12px;margin-top:4px">#${id} ${esc(t.title)}</div>

        <label class="lbl" style="margin-top:14px">Σε ποιους συναδέλφους</label>
        <div class="shr-grid">${others.map(a => `<label class="shr-p"><input type="checkbox" class="shTo" value="${a.id}"> ${esc(a.name)}</label>`).join('')}</div>

        <label class="lbl" style="margin-top:12px">…ή σε διεύθυνση email <span class="mut" style="font-weight:400">— προαιρετικό</span></label>
        <input class="inp" id="shMail" type="email" placeholder="π.χ. synergatis@example.com">

        <label class="lbl" style="margin-top:12px">Σημείωμα <span class="mut" style="font-weight:400">— τι θέλεις να προσέξει</span></label>
        <textarea class="inp" id="shNote" rows="2" maxlength="1000" placeholder="π.χ. Δες το πριν μιλήσουμε με τον πελάτη"></textarea>

        <label class="lbl" style="margin-top:12px">Πώς</label>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          ${[['chat', I.chat + ' Chat'], ['email', '✉ Email'], ['both', 'Και τα δύο']].map(([k, lb], i) =>
            `<label class="shr-v"><input type="radio" name="shVia" value="${k}" ${i === 0 ? 'checked' : ''}> ${lb}</label>`).join('')}
        </div>
        <div id="shErr" class="mut" style="font-size:11.5px;color:var(--bad);margin-top:8px" hidden></div>
        <div style="display:flex;gap:9px;margin-top:15px;justify-content:flex-end">
          <button class="btn btn-o" id="shNo">Άκυρο</button>
          <button class="btn btn-p" id="shGo">Αποστολή</button>
        </div>
      </div></div>`;
    document.body.appendChild(ovl);
    $('#shNo', ovl).onclick = () => ovl.remove();
    $('#shGo', ovl).onclick = async () => {
      const to = $$('.shTo:checked', ovl).map(x => +x.value);
      const mail = $('#shMail', ovl).value.trim();
      const via = (ovl.querySelector('input[name="shVia"]:checked') || {}).value || 'chat';
      const err = $('#shErr', ovl);
      if (!to.length && !mail) {
        err.hidden = false; err.textContent = 'Διάλεξε συνάδελφο ή γράψε διεύθυνση email.'; return;
      }
      if (via === 'chat' && !to.length) {
        err.hidden = false; err.textContent = 'Το chat πάει μόνο σε συναδέλφους — διάλεξε τουλάχιστον έναν.'; return;
      }
      const btn = $('#shGo', ovl); btn.disabled = true; btn.textContent = '…';
      const r = await api('task_share', {task: id, to, email: mail, via, note: $('#shNote', ovl).value.trim()})
        .catch(e => ({err: e && e.message}));
      if (r && r.err) {
        err.hidden = false; err.textContent = r.err;
        btn.disabled = false; btn.textContent = 'Αποστολή'; return;
      }
      ovl.remove();
      toast('Στάλθηκε' + (r.chat ? ` · ${r.chat} chat` : '') + (r.mail ? ` · ${r.mail} email` : ''));
    };
    setTimeout(() => { const f = ovl.querySelector('.shTo'); if (f) { f.focus(); } }, 40);
  };

  /* ── Παράδοση σκυτάλης ────────────────────────────────────────────────────
     Δεν είναι «αλλαγή αναδόχου». Είναι: κλείνω το δικό μου κομμάτι, γράφω τι
     έγινε, λέω τι χρειάζεται από τον επόμενο — και η εργασία φεύγει από τη
     μέρα μου και μπαίνει στη δική του. Χωρίς το «τι έγινε ως εδώ», ο επόμενος
     ξεκινά από το μηδέν και η παράδοση γίνεται μετακύλιση. */
  const hnd = $('#dHand', dr); if (hnd) hnd.onclick = () => {
    const others = S.boot.admins.filter(a => a.id !== me.id);
    const ovl = document.createElement('div');
    ovl.className = 'ovl show'; ovl.style.zIndex = 320;
    ovl.innerHTML = `<div class="pal-box" style="margin:9vh auto 0;max-width:540px" role="dialog">
      <div style="padding:20px 22px 18px">
        <b style="font-size:15.5px;color:var(--ink)">${I.zap} Παράδοση σε συνάδελφο</b>
        <div class="mut" style="font-size:12px;margin-top:5px">Η εργασία φεύγει από «Η μέρα μου» και μπαίνει στη δική του.</div>
        <label class="lbl" style="margin-top:14px">Σε ποιον</label>
        <select class="inp" id="hoTo"><option value="">— διάλεξε συνάδελφο —</option>
          ${others.map(a => `<option value="${a.id}">${esc(a.name)}</option>`).join('')}</select>
        <label class="lbl" style="margin-top:11px">Τι έκανες εσύ <span class="mut" style="font-weight:400">— μπαίνει ως ολοκληρωμένο βήμα</span></label>
        <textarea class="inp" id="hoDid" rows="2" maxlength="500" placeholder="π.χ. Εντοπίστηκε η αιτία, διορθώθηκε το query"></textarea>
        <label class="lbl" style="margin-top:11px">Τι χρειάζεται από αυτόν <span style="color:var(--bad)">*</span></label>
        <textarea class="inp" id="hoNext" rows="2" maxlength="500" placeholder="π.χ. Δοκίμασε σε staging και ενημέρωσε τον πελάτη"></textarea>
        <label class="lbl" style="margin-top:11px">Νέα προθεσμία <span class="mut" style="font-weight:400">— προαιρετικό</span></label>
        <input type="date" class="inp" id="hoDue" value="${t.due || ''}">
        <label style="display:flex;gap:7px;align-items:center;margin-top:11px;font-size:12.5px">
          <input type="checkbox" id="hoMove" checked> Να περάσει και η ανάθεση σε αυτόν</label>
        <div id="hoErr" class="mut" style="font-size:11.5px;color:var(--bad);margin-top:8px" hidden></div>
        <div style="display:flex;gap:9px;margin-top:16px;justify-content:flex-end">
          <button class="btn btn-o" id="hoNo">Άκυρο</button>
          <button class="btn btn-p" id="hoGo">${I.zap} Παράδοση</button>
        </div>
      </div></div>`;
    document.body.appendChild(ovl);
    setTimeout(() => $('#hoTo', ovl).focus(), 40);
    $('#hoNo', ovl).onclick = () => ovl.remove();
    $('#hoGo', ovl).onclick = async () => {
      const to = +$('#hoTo', ovl).value || 0;
      const next = $('#hoNext', ovl).value.trim();
      const err = $('#hoErr', ovl);
      if (!to || !next) {
        err.hidden = false;
        err.textContent = !to ? 'Διάλεξε σε ποιον παραδίδεις.' : 'Γράψε τι χρειάζεται από αυτόν.';
        (!to ? $('#hoTo', ovl) : $('#hoNext', ovl)).focus(); return;
      }
      const btn = $('#hoGo', ovl); btn.disabled = true; btn.textContent = '…';
      const r = await api('task_handoff', {task: id, to, next,
        did: $('#hoDid', ovl).value.trim(), due: $('#hoDue', ovl).value || '',
        move: $('#hoMove', ovl).checked}).catch(e => ({err: e.message}));
      if (r && r.err) {
        err.hidden = false; err.textContent = r.err;
        btn.disabled = false; btn.innerHTML = I.zap + ' Παράδοση'; return;
      }
      ovl.remove(); closeDrawer();
      toast('Παραδόθηκε στον/στην ' + (r.name || ''));
      if (window.R && window.R[S.view]) { window.R[S.view](); }
    };
  };
  const ddl = $('#dDel', dr); if (ddl) ddl.onclick = async () => {
    const mins = Math.round((d.total || 0));
    const lost = [mins ? fmtMin(mins) + ' καταγεγραμμένου χρόνου' : '',
      (d.comments || []).length ? (d.comments || []).length + ' σχόλια' : '',
      (d.check || []).length ? (d.check || []).length + ' ενέργειες' : ''].filter(Boolean);
    const ok = await cnpConfirm(
      `Οριστική διαγραφή της εργασίας #${id};` + (lost.length ? '\n\nΧάνονται μαζί: ' + lost.join(' · ') + '.' : '')
      + '\n\nΔεν γυρίζει πίσω, και ειδοποιούνται οι διαχειριστές.',
      {ok: I.trash + ' Διαγραφή', cancel: 'Άκυρο', danger: true});
    if (!ok) { return; }
    const r = await api('task_delete', {id}).catch(e => ({err: (e && e.message) || 'Δεν διαγράφηκε'}));
    if (r && r.err) { toast(r.err, true); return; }
    toast('Η εργασία διαγράφηκε');
    closeDrawer();
    if (r && r.project) { go('board', r.project); } else { R[S.view] && R[S.view](); }
  };
  /* Start/Stop ξανασχεδιάζουν την καρτέλα: ό,τι έγραψες και δεν αποθηκεύτηκε θα χανόταν
     σιωπηλά. Αποθηκεύεται πρώτα· αν η αποθήκευση αποτύχει (π.χ. λάθος ημερομηνίες), το
     χρονόμετρο δεν αγγίζεται και βλέπεις το μήνυμα. */
  const saveFirst = async () => {
    if (dr.dataset.dirty !== '1') { return true; }
    if (dr._saveNow) { await dr._saveNow(); }
    return dr.dataset.dirty !== '1';
  };
  const ts = $('#tStart', dr); if (ts) ts.onclick = async () => { if (!(await saveFirst())) { return; } await api('timer_start', {task: id}); toast('Ο χρόνος μετράει'); openTask(id); };
  const tp = $('#tStop', dr); if (tp) tp.onclick = async () => {
    if (!(await saveFirst())) { return; }
    const bill = d.owner
      ? await cnpConfirm('Να χρεωθεί ο χρόνος στον πελάτη;', {ok: I.coin + ' Χρεώσιμο', cancel: 'Χωρίς χρέωση'})
      : false;
    const r = await api('timer_stop', {billable: bill, note: ''});
    toast('Καταχωρήθηκε ' + fmtMin(r.mins)); openTask(id);
  };
  if (d.timerHere) {
    const since = new Date(d.timerHere.since.replace(' ', 'T')).getTime();
    const tick = () => { const s = Math.floor((Date.now() - since) / 1000);
      const el = $('#tLive', dr); if (!el) { clearInterval(timerInt); return; }
      el.textContent = `${String(Math.floor(s / 3600)).padStart(2, '0')}:${String(Math.floor(s % 3600 / 60)).padStart(2, '0')}:${String(s % 60).padStart(2, '0')}`; };
    tick(); timerInt = setInterval(tick, 1000);
  }
  /* Η χρέωση διορθώνεται επί τόπου: ένα λάθος κλικ στο χρονόμετρο δεν πρέπει να
     κοστίζει χρεώσιμο χρόνο που δεν τιμολογήθηκε ποτέ. */
  $$('[data-tbill]', dr).forEach(b => b.onclick = async () => {
    const on = b.dataset.on !== '1';
    const r = await api('time_bill', {id: +b.dataset.tbill, billable: on}).catch(e => ({err: (e && e.message) || 'σφάλμα'}));
    if (r && r.err) { toast(r.err, true); return; }
    toast(on ? 'Χρεώσιμο ✓' : 'Χωρίς χρέωση');
    openTask(id);
  });
  /* ── «Αφορά προσφορά»: δέσιμο με υπάρχουσα, νέα, ή αίτημα σε συνάδελφο ── */
  { const cb = $('#fOffer', dr), box = $('#fOfferBox', dr);
    if (cb && box) { cb.addEventListener('change', () => { box.hidden = !cb.checked; }); }
    const ownerId = (d.owner && d.owner.id) || 0, ownerName = (d.owner && d.owner.name) || '';
    const sel = $('#fOfferSel', dr);
    if (sel && !cnpCan('clients.offers')) { sel.innerHTML = '<option value="">— (χωρίς δικαίωμα προσφορών — γράψε μόνο τον αριθμό) —</option>'; sel.disabled = true; }
    if (sel && cnpCan('clients.offers')) {
      api('offers' + (ownerId ? '&client=' + ownerId : '')).then(od => {
        const list = (od.offers || []).filter(o => !ownerId || o.client === ownerId);
        sel.innerHTML = '<option value="">— δέσε με υπάρχουσα προσφορά… —</option>' + list.map(o =>
          `<option value="${o.id}">${esc(o.title)}${o.value ? ' · ' + fmtEur(o.value) : ''}</option>`).join('')
          + (list.length ? '' : '<option value="" disabled>ο πελάτης δεν έχει προσφορές</option>');
      }).catch(() => {});
      sel.onchange = async () => {
        const oid = +sel.value || 0; if (!oid) { return; }
        const r = await api('save_task', {task: id, offer: oid}).catch(e => ({err: e && e.message}));
        if (r && r.err) { toast(r.err, true); return; }
        toast('Δέθηκε με την προσφορά'); openTask(id);
      };
    }
    const nb = $('#fOfferNew', dr); if (nb) { nb.onclick = e => { e.stopPropagation();
      miniMenu(nb, [{icon: I.doc, label: 'Γενική προσφορά', on: () => window.CNP.newOfferFor({client: ownerId, name: ownerName, task: id, kind: 'plain'})},
        {icon: I.doc, label: 'PharmacyOne', on: () => window.CNP.newOfferFor({client: ownerId, name: ownerName, task: id, kind: 'pharmacyone'})},
        {icon: I.phone, label: 'Τηλεφωνικό κέντρο', on: () => window.CNP.newOfferFor({client: ownerId, name: ownerName, task: id, kind: 'pbx'})}]); }; }
    const ab = $('#fOfferAsk', dr); if (ab) { ab.onclick = e => { e.stopPropagation();
      const isSvc = a => /support team|\bbot\b/i.test(a.name || '') || String(a.name || '').trim() === 'Cloud On';
      miniMenu(ab, (S.boot.admins || []).filter(a => a.id !== me.id && !isSvc(a)).map(a => ({label: a.name, on: async () => {
        const msg = await cnpDialog({title: '📣 Ζήτα προσφορά από τον ' + a.name, body: 'Θα του φτάσει ως αίτημα (καμπανάκι + «σε ζητούν») και θα μείνει εκκρεμές μέχρι να φτιάξει και να δέσει την προσφορά.',
          input: `Χρειάζεται προσφορά για «${(t.title || '').slice(0, 80)}»${ownerName ? ' — πελάτης ' + ownerName : ''}. Όταν τη φτιάξεις, δέσε την με την εργασία.`, rows: 4, max: 2000, ok: '📣 Στείλε', cancel: 'Άκυρο'});
        if (msg === null) { return; }
        const r = await api('task_offer_request', {task: id, to: a.id, message: msg}).catch(er => ({err: er && er.message}));
        if (r && r.err) { toast(r.err, true); return; }
        toast('📣 Ζητήθηκε προσφορά από τον ' + (r.to || a.name)); openTask(id);
      }}))); }; }
    const ob = $('#fOfferOpen', dr); if (ob) { ob.onclick = () => { if (window.CNP.openOfferById) { window.CNP.openOfferById(d.offer.id); } }; }
    const ub = $('#fOfferUnlink', dr); if (ub) { ub.onclick = async () => {
      if (!(await cnpConfirm('Λύσιμο της εργασίας από την προσφορά «' + (d.offer.title || '') + '»;', {ok: 'Λύσιμο'}))) { return; }
      const r = await api('save_task', {task: id, offer: 0}).catch(e => ({err: e && e.message}));
      if (r && r.err) { toast(r.err, true); return; }
      openTask(id);
    }; }
  }
  /* «Ρώτα τι γίνεται»: προσυμπληρωμένη ερώτηση, αλλάζει πριν φύγει. */
  { const ab = $('#dAsk', dr); if (ab) ab.onclick = async () => {
    const who = adminName((d.overrun || {}).agent);
    const msg = await cnpDialog({title: '💬 Ρώτα τον ' + who, body: 'Θα του φτάσει ως ειδοποίηση με δύο κουμπιά: «Όλα καλά» / «Χρειάζομαι βοήθεια». Η απάντηση γυρίζει σε σένα.',
      input: `Βλέπω ότι «${(t.title || '').slice(0, 80)}» ξεπέρασε την εκτίμηση. Τι γίνεται; Χρειάζεσαι βοήθεια;`, rows: 4, max: 2000, ok: '💬 Στείλε', cancel: 'Άκυρο'});
    if (msg === null) { return; }
    const r = await api('overrun_checkin', {what: 'task', id, message: msg}).catch(e => ({err: e && e.message}));
    if (r && r.err) { toast(r.err, true); return; }
    toast('💬 Ρωτήθηκε ο ' + (r.to || who)); openTask(id);
  }; }
  $('#tAdd', dr).onclick = async () => {
    const m = +$('#tMins', dr).value; if (!m) return;
    if (m < 0) { toast('Αρνητικός χρόνος δεν καταχωρείται', true); return; }
    const body = {task: id, mins: m, billable: $('#tBill', dr) ? $('#tBill', dr).checked : false, note: $('#tNote', dr).value};
    let r = await api('time_add', body).catch(e => ({err: e.message, data: e.data}));
    if (r && r.err && r.data && r.data.need === 'confirm') {
      /* Πάνω από 12 ώρες σε μία καταχώρηση: ο server ζητά επιβεβαίωση — συνήθως είναι λάθος πληκτρολόγησης. */
      if (!(await cnpConfirm(r.err + '\n\n' + fmtMin(m) + ' σε μία καταχώρηση.', {title: '⚠ Πολύς χρόνος', ok: 'Ναι, είναι σωστό', cancel: 'Όχι, να το διορθώσω'}))) return;
      r = await api('time_add', Object.assign({force: 1}, body)).catch(e => ({err: e.message}));
    }
    if (r && r.err) { toast(r.err, true); return; }
    toast('Καταχωρήθηκε ' + fmtMin(m)); openTask(id);
  };
  /* Εξαρτήσεις: υποψήφιες οι «αδελφές» εργασίες. Για εργασία έργου είναι οι
     υπόλοιπες του board· για εργασία χωρίς έργο, οι ανοιχτές του department
     της — αλλιώς δεν υπήρχε από πού να διαλέξεις. */
  (async () => {
    const sel = $('#depSel', dr);
    if (!sel) { return; }
    const add = (tid, title) => { if (tid !== id) { sel.innerHTML += `<option value="${tid}">${esc(title)}</option>`; } };
    if (!d.project.none) {
      const bd = await api('board&project=' + d.project.id).catch(() => null);
      if (bd) { bd.columns.forEach(col => col.tasks.forEach(tt => add(tt.id, tt.title))); }
    } else if (t.dept) {
      const dv = await api('dept_view&id=' + t.dept).catch(() => null);
      if (dv) { dv.groups.forEach(g => g.tasks.forEach(tt => add(tt.id, tt.title))); }
    }
  })();
  $('#depAdd', dr).onclick = async () => {
    const on = +$('#depSel', dr).value;
    if (!on) return;
    const r = await api('dep_add', {task: id, on}).catch(() => ({ok: 0}));
    if (r.ok) { toast('Προστέθηκε εξάρτηση'); openTask(id); }
    else toast('Δεν γίνεται (κύκλος;)', true);
  };
  $$('[data-ddel]', dr).forEach(b => b.onclick = async () => {
    await api('dep_del', {id: +b.dataset.ddel}); openTask(id);
  });
  $$('[data-dgo]', dr).forEach(a => a.onclick = () => openTask(+a.dataset.dgo));
  /* αρχεία */
  if ($('#dFiles', dr) && window.cnpAttachments) {
    window.cnpAttachments($('#dFiles', dr), {module: 'task', refType: 'task', refId: id,
      canDelete: !t.done,
      onCount: n => attCount($('#dFilesSum', dr), n)});
  }
  /* Διόρθωση επί τόπου: το βήμα γίνεται πεδίο, Enter αποθηκεύει, Esc ακυρώνει. */
  /* ══ ΕΝΕΡΓΕΙΕΣ — ημερολόγιο δουλειάς, όχι checklist ══════════════════════
     Κάθε ενέργεια είναι ένα μπλοκ κειμένου με εικόνες ΜΕΣΑ στη ροή (όχι
     συνημμένα δίπλα), τα αρχεία της από κάτω, και μία διαδρομή αποθήκευσης:
     ένα κουμπί «Καταχώρηση». Το Enter αλλάζει γραμμή — σε μεγάλο κείμενο το
     Enter-ως-αποθήκευση κόβει τη σκέψη στη μέση. */

  /* Επικόλληση εικόνας: ανεβαίνει και μπαίνει ΜΕΣΑ στο κείμενο. */
  const actUpload = async (file, refId) => {
    const fd = new FormData();
    fd.append('module', 'task'); fd.append('ref_type', 'check'); fd.append('ref_id', refId || 0);
    fd.append('file', file);
    const r = await fetch('api.php?a=file_upload', {method: 'POST', body: fd, credentials: 'same-origin'})
      .then(x => x.json()).catch(() => null);
    return r && r.file ? r.file : null;
  };
  const wireEditor = el => {
    el.addEventListener('paste', async e => {
      const f = cnpClipImage(e);
      if (!f) { return; }                         // κείμενο: το χειρίζεται ο καθολικός handler
      e.preventDefault();
      const hint = $('#chkHint', dr); if (hint) { hint.textContent = 'Ανέβασμα εικόνας…'; }
      await cnpPasteImage(el, f);
      if (hint) { hint.textContent = ''; }
      markDirty(el);
    });
    /* @όνομα: λίστα συναδέλφων επί τόπου. Η ειδοποίηση φεύγει από τον server
       μόλις αποθηκευτεί — εδώ απλώς βοηθάμε να γραφτεί σωστά το όνομα. */
    el.addEventListener('keyup', e => {
      if (['ArrowUp', 'ArrowDown', 'Enter', 'Escape'].includes(e.key)) { return; }
      const sel = window.getSelection();
      if (!sel || !sel.focusNode) { return; }
      const txt = (sel.focusNode.textContent || '').slice(0, sel.focusOffset);
      const m = /@([\p{L}\p{N}_.\-]*)$/u.exec(txt);
      closeMentions();
      if (!m) { return; }
      const q = m[1].toLowerCase();
      const hits = S.boot.admins.filter(a => a.id !== me.id
        && (!q || a.name.toLowerCase().includes(q))).slice(0, 6);
      if (!hits.length) { return; }
      openMentions(el, hits, m[1].length);
    });
    el.addEventListener('blur', () => setTimeout(closeMentions, 180));
  };
  let mentBox = null;
  const closeMentions = () => { if (mentBox) { mentBox.remove(); mentBox = null; } };
  const openMentions = (el, hits, typedLen) => {
    const sel = window.getSelection();
    const rect = sel.rangeCount ? sel.getRangeAt(0).getBoundingClientRect() : el.getBoundingClientRect();
    mentBox = document.createElement('div');
    mentBox.className = 'ment-box';
    mentBox.style.top = (rect.bottom + 4) + 'px';
    mentBox.style.left = Math.max(8, rect.left) + 'px';
    mentBox.innerHTML = hits.map((a, i) =>
      `<button type="button" class="ment-row${i === 0 ? ' on' : ''}" data-m="${esc(a.name)}">
        <span class="ment-av">${esc(adminIni(a.id) || '?')}</span>${esc(a.name)}</button>`).join('');
    document.body.appendChild(mentBox);
    mentBox.querySelectorAll('[data-m]').forEach(b => b.onmousedown = ev => {
      ev.preventDefault();
      const s2 = window.getSelection();
      if (s2 && s2.rangeCount) {
        const r2 = s2.getRangeAt(0);
        r2.setStart(r2.endContainer, Math.max(0, r2.endOffset - typedLen));
        r2.deleteContents();
      }
      document.execCommand('insertText', false, b.dataset.m + ' ');
      closeMentions(); markDirty(el);
    });
  };

  /* ── νέα ενέργεια ── */
  { const ed = $('#chkNew', dr);
    if (ed) {
      wireEditor(ed);
      const pending = [];                         // αρχεία που διάλεξε πριν καταχωρήσει
      const paint = () => {
        const h = $('#chkHint', dr);
        if (h) { h.textContent = pending.length ? `${pending.length} αρχείο${pending.length > 1 ? 'α' : ''} προς επισύναψη` : ''; }
      };
      const fi = $('#chkFile', dr);
      const clip = $('#chkClip', dr);
      if (clip && fi) {
        clip.onclick = () => fi.click();
        fi.onchange = () => { [...fi.files].forEach(f => pending.push(f)); fi.value = ''; paint(); };
      }
      let busy = false;   // χωρίς κουμπί, ο φύλακας διπλής αποστολής ζει εδώ
      const submit = async () => {
        const html = ed.innerHTML.trim();
        if (busy || !html || html === '<br>') { return; }
        busy = true; ed.setAttribute('aria-busy', '1');
        const r = await api('check_add', {task: id, title: html, html: 1})
          .catch(er => ({err: (er && er.message) || 'σφάλμα', er}));
        if (r && r.err) { busy = false; ed.removeAttribute('aria-busy');
          if (!(await cnpCodeRefused(r.er))) { toast(r.err, true); } return; }
        for (const f of pending) { await actUpload(f, r.id); }
        /* ΚΑΘΑΡΙΣΕ ΠΡΙΝ ΤΟΝ ΞΑΝΑΣΧΕΔΙΑΣΜΟ. Παλιά το σβήσιμο γινόταν «από μόνο του»
           επειδή η καρτέλα ξαναχτιζόταν· τώρα που τα πρόχειρα επιζούν, το ήδη
           καταχωρημένο κείμενο θα επέστρεφε στον συνθέτη σαν να μην στάλθηκε. */
        ed.innerHTML = '';
        openTask(id);
      };
      /* Enter = καταχώρηση. Shift+Enter = νέα γραμμή. Το Ctrl/⌘+Enter μένει για
         όποιον το συνήθισε. Όσο είναι ανοιχτή η λίστα @mentions, το Enter ανήκει
         σε εκείνη — διαλέγει όνομα, δεν στέλνει μισό μήνυμα. */
      { const gb = $('#chkGo', dr); if (gb) { gb.onclick = submit; } }
      ed.addEventListener('keydown', e => {
        if (e.key !== 'Enter') { return; }
        if (document.querySelector('.ment-box')) { return; }
        if (e.shiftKey) { return; }
        e.preventDefault();
        submit();
      });
    } }

  /* ── επεξεργασία υπάρχουσας ── */
  $$('[data-cedit]', dr).forEach(b => b.onclick = () => {
    const cid = +b.dataset.cedit;
    const it = (d.check || []).find(x => x.id === cid); if (!it) { return; }
    const body = dr.querySelector(`[data-ctext="${cid}"]`); if (!body) { return; }
    if (body.isContentEditable) { return; }
    const before = body.innerHTML;
    /* Ίδιο κουτί με τη σύνθεση: εργαλειοθήκη πάνω, κείμενο, κουμπιά κάτω. Το
       `.act-composer` είναι αυτό που ψάχνει η καθολική delegation των .rte-b για
       να βρει ποιο πεδίο αφορά το κουμπί — χωρίς αυτό η εργαλειοθήκη θα ήταν
       διακοσμητική. */
    const wrap = document.createElement('div');
    wrap.className = 'act-composer act-editing';
    body.parentNode.insertBefore(wrap, body);
    wrap.insertAdjacentHTML('beforeend', actTbHtml());
    wrap.appendChild(body);
    body.contentEditable = 'true';
    body.classList.add('act-edit');
    wireEditor(body);
    body.focus();
    const bar = document.createElement('div');
    bar.className = 'act-foot';
    bar.innerHTML = `<span style="flex:1"></span>
      <button type="button" class="btn btn-sm btn-o" data-eno>Άκυρο</button>
      <button type="button" class="btn btn-sm btn-p" data-eok>Αποθήκευση</button>`;
    wrap.appendChild(bar);
    bar.querySelector('[data-eno]').onclick = () => { body.innerHTML = before; openTask(id); };
    bar.querySelector('[data-eok]').onclick = async () => {
      const v = body.innerHTML.trim();
      if (!v) { toast('Κενή ενέργεια — γράψε κάτι ή διάγραψέ την', true); return; }
      const r = await api('check_edit', {id: cid, title: v, html: 1})
        .catch(er => ({err: (er && er.message) || 'σφάλμα'}));
      if (r && r.err) { toast(r.err, true); return; }
      openTask(id);
    };
  });

  /* ── επισύναψη σε υπάρχουσα ενέργεια ── */
  $$('[data-cattach]', dr).forEach(b => b.onclick = () => {
    const cid = +b.dataset.cattach;
    const fi = document.createElement('input');
    fi.type = 'file'; fi.multiple = true;
    fi.onchange = async () => {
      for (const f of fi.files) { await actUpload(f, cid); }
      openTask(id);
    };
    fi.click();
  });

  $$('[data-cdelstep]', dr).forEach(b => b.onclick = async () => {
    if (!await cnpConfirm('Διαγραφή ενέργειας;', {
      body: 'Φεύγει μαζί με τα συνημμένα της. Δεν γυρίζει πίσω.', danger: true})) { return; }
    const r = await api('check_del', {id: +b.dataset.cdelstep}).catch(er => ({err: (er && er.message) || 'σφάλμα'}));
    if (r && r.err) { toast(r.err, true); return; }
    openTask(id);
  });
  /* ── ⋯ μενού ανά post: ό,τι μπορείς να κάνεις ΠΑΝΩ σε μια ενέργεια ── */
  const plainOf = it => String(it.fmt === 'html' ? it.title.replace(/<br\s*\/?>/gi, ' ').replace(/<[^>]+>/g, '') : it.title).replace(/&nbsp;/g, ' ').replace(/\s+/g, ' ').trim();
  const clickHidden = (attr, cid) => { const x = dr.querySelector(`[${attr}="${cid}"]`); if (x) { x.click(); } };
  $$('[data-more]', dr).forEach(b => b.onclick = e => {
    e.stopPropagation();
    const cid = +b.dataset.more; const it = (d.check || []).find(x => x.id === cid); if (!it) { return; }
    const plain = plainOf(it);
    const rows = [
      {icon: I.edit, label: 'Επεξεργασία', on: () => clickHidden('data-cedit', cid)},
      {icon: I.link, label: 'Αντιγραφή συνδέσμου', on: async () => {
        try { await navigator.clipboard.writeText(location.origin + '/project/#/task/' + id + '/e/' + cid); toast('Ο σύνδεσμος αντιγράφηκε'); } catch (x) { toast('Δεν αντιγράφηκε', true); } }},
      {icon: I.checkSquare, label: 'Στο πλάνο μου', on: async () => {
        const r = await api('todo_add', {text: (plain.slice(0, 240) || 'Ενέργεια') + ' — #' + id}).catch(er => ({err: er && er.message}));
        toast(r && r.err ? r.err : 'Μπήκε στο πλάνο σου', !!(r && r.err)); }},
      {icon: I.clock, label: 'Καταγραφή χρόνου', on: () => {
        const n = $('#tNote', dr), m = $('#tMins', dr);
        if (n) { n.value = plain.slice(0, 120); }
        if (m) { m.scrollIntoView({block: 'center', behavior: 'smooth'}); m.focus(); }
        toast('Γράψε τα λεπτά — η σημείωση συμπληρώθηκε από την ενέργεια'); }},
      {icon: I.rocket, label: 'Μετατροπή σε εργασία', on: async () => {
        if (!(await cnpConfirm('Να γίνει η ενέργεια ξεχωριστή εργασία;', {body: 'Νέα εργασία στο ίδιο έργο/τμήμα, με το κείμενο της ενέργειας ως ζητούμενο. Η ενέργεια μένει εδώ.', ok: 'Δημιουργία'}))) { return; }
        const r = await api('check_to_task', {id: cid}).catch(er => ({err: er && er.message}));
        if (r && r.err) { toast(r.err, true); return; }
        toast('Δημιουργήθηκε η εργασία #' + r.id); openTask(r.id); }},
      {icon: I.clip, label: 'Επισύναψη αρχείου', on: () => clickHidden('data-cattach', cid)},
      {icon: I.trash, label: 'Διαγραφή', on: () => clickHidden('data-cdelstep', cid)},
      {icon: I.user, label: 'Από ' + (it.by || '—') + (it.at ? ' · ' + tShort(it.at) : '')},
    ];
    miniMenu(b, rows);
  });
  const react = async (cid, code) => {
    const r = await api('check_react', {id: cid, code}).catch(er => ({err: er && er.message}));
    if (r && r.err) { toast(r.err, true); return; }
    openTask(id);
  };
  $$('[data-react]', dr).forEach(b => b.onclick = e => {
    e.stopPropagation();
    const cid = +b.dataset.react;
    miniMenu(b, Object.keys(REACT).map(code => ({label: REACT[code] + '  ' + REACT_LBL[code], on: () => react(cid, code)})));
  });
  $$('.th-react', dr).forEach(b => b.onclick = () => react(+b.dataset.rid, b.dataset.rc));
  { const more = $('#thMore', dr); if (more) { more.onclick = () => { $$('.th-hid', dr).forEach(x => x.classList.remove('th-hid')); more.remove(); }; } }
  /* Σύνδεσμος σε συγκεκριμένη ενέργεια (#/task/N/e/M): ξεδίπλωσε, κύλισε, φώτισε. */
  { const em = entryId ? [null, String(entryId)] : location.hash.match(/\/e\/(\d+)/);
    if (em) {
      const el = $('#thp' + em[1], dr);
      if (el) {
        $$('.th-hid', dr).forEach(x => x.classList.remove('th-hid')); const mb = $('#thMore', dr); if (mb) { mb.remove(); }
        setTimeout(() => { el.scrollIntoView({block: 'center'}); el.classList.add('th-hl'); }, 250);
      }
    } }
  $$('#dCheck input[data-chk]', dr).forEach(cb => cb.onchange = async () => {
    await api('check_toggle', {id: +cb.dataset.chk});
    const rowEl = cb.closest('.th-post, .act'); if (rowEl) { rowEl.classList.toggle('done', cb.checked); }
  });

  /* Τίτλος: αλλάζει επί τόπου από την κεφαλίδα — δεν υπάρχει πια δεύτερο πεδίο δεξιά. */
  { const te = $('#dTitleEdit', dr);
    if (te) te.onclick = () => {
      const h2 = $('#dTitle', dr); if (!h2 || $('#dTitleInp', dr)) { return; }
      const inp = document.createElement('input'); inp.className = 'inp tk-ttl-inp'; inp.id = 'dTitleInp';
      inp.value = dr.dataset.title || t.title; h2.replaceWith(inp); inp.focus(); inp.select();
      let fin = false;
      const done = async save => {
        if (fin) { return; } fin = true;
        const v = inp.value.trim();
        if (save && v && v !== (dr.dataset.title || t.title)) {
          const r = await api('save_task', {task: id, title: v}).catch(e => ({err: e && e.message}));
          if (r && r.err) { toast(r.err, true); } else { dr.dataset.title = v; toast('Ο τίτλος άλλαξε'); }
        }
        const h = document.createElement('h2'); h.id = 'dTitle'; h.textContent = dr.dataset.title || t.title;
        inp.replaceWith(h);
      };
      inp.onkeydown = e => { if (e.key === 'Enter') { e.preventDefault(); done(true); } else if (e.key === 'Escape') { done(false); } };
      inp.onblur = () => done(true);
    }; }
  /* ── ΚΛΕΙΔΩΜΕΝΗ ΕΡΓΑΣΙΑ ──────────────────────────────────────────────────────
     Ολοκληρωμένη εργασία είναι αρχείο, όχι πρόχειρο: αν άλλαζαν εκ των υστέρων τα
     βήματα, ο χρόνος ή η ανάθεση, θα άλλαζε η ιστορία της και μαζί οι χρεώσεις.
     Ο server το επιβάλλει (cnp_task_lock_guard)· εδώ το δείχνουμε, ώστε να μην
     πληκτρολογήσει κανείς κάτι που θα απορριφθεί. Ανοιχτά μένουν μόνο: το
     ξανάνοιγμα, η αλλαγή κατάστασης, η πλοήγηση και η ΛΗΨΗ συνημμένων. */
  /* Κοινό κλείδωμα: «κοίτα, μην αγγίζεις». Χρησιμοποιείται από δύο διαφορετικές
     καταστάσεις — ολοκληρωμένη εργασία, και δική σου εργασία που δεν δουλεύεις. */
  const lockCard = (why, freeExtra) => {
    dr.classList.add('tk-locked');
    const free = '#dReopen,#dStPill,.drawer-x,.tkmore,.tk-step-max,.tk-mtabs button,[data-navclose],[data-c3task]'
      + (freeExtra ? ',' + freeExtra : '');
    $$('input,select,textarea,button', dr).forEach(el => {
      if (el.matches(free) || el.closest(free)) { return; }
      el.disabled = true; el.title = why;
    });
    const ttl = $('#dTitleEdit', dr); if (ttl) { ttl.hidden = true; }
    /* Το «ζητούμενο» είναι contenteditable, όχι <input> — δεν το πιάνει το disabled. */
    $$('.rte,.act-edit,[contenteditable]', dr).forEach(el => { el.setAttribute('contenteditable', 'false'); });
    $$('.rte-tb', dr).forEach(el => { el.hidden = true; });
  };

  if (t.done) {
    lockCard('Η εργασία είναι ολοκληρωμένη — πάτα «↩ Ξανάνοιγμα» για να την αλλάξεις', '#dDel');
  }

  /* ── Ο χρόνος πρώτα ────────────────────────────────────────────────────────
     Δική σου εργασία, ανοιχτή, χωρίς χρονόμετρο: ρωτάμε αν ξεκινάς τώρα. Όχι
     για γραφειοκρατία — αν ο χρόνος δεν ξεκινήσει με τη δουλειά, δεν
     καταγράφεται ποτέ σωστά και μετά τον «θυμόμαστε» στο τέλος της μέρας.
     «Όχι» = μόνο προβολή: μπορείς να διαβάσεις, όχι να αλλάξεις. Ένα κουμπί
     «Ξεκίνα τον χρόνο» ξεκλειδώνει — δεν υπάρχει άλλη πόρτα. */
  /* Πρόχειρο που μόλις δημιουργήθηκε: καμία ερώτηση για χρόνο — πρώτα γράφει, μετά αποφασίζει. */
  /* Ρωτά ΜΙΑ φορά την ημέρα ανά εργασία (localStorage): αν είπες «μόνο θα δω», δεν σε
     ξαναρωτά σε κάθε άνοιγμα — υπάρχει το «▶ Ξεκίνα τον χρόνο» μέσα στην καρτέλα. */
  const askedKey = 'cnpTimerAsked:' + id, askedToday = (() => { try { return localStorage.getItem(askedKey) === today(); } catch (e) { return false; } })();
  if (!opts.fresh && !t.done && cnpIsMine(t, me.id) && !d.timerHere && !window._cnpTimerAsked_[id] && !askedToday) {
    window._cnpTimerAsked_[id] = 1;
    try { localStorage.setItem(askedKey, today()); } catch (e) {}
    const go2 = await cnpDialog({
      noClose: true,             // ο ✕ άφηνε την καρτέλα ξεκλείδωτη — τώρα δεν υπάρχει
      title: '▶ Ξεκινάς τώρα αυτή την εργασία;',
      body: `«${t.title}»\n\nΑν ναι, ξεκινά ο χρόνος και μπορείς να δουλέψεις.\nΑν όχι, θα την ανοίξω μόνο για ανάγνωση.\n\nΑν τρέχει χρονόμετρο σε άλλη εργασία, θα σταματήσει.`,
      ok: '▶ Ναι, ξεκινάω', cancel: 'Όχι, μόνο θα δω'});
    if (go2) {
      await api('timer_start', {task: id}).catch(() => null);
      openTask(id);
      return;
    }
    delete window._cnpTimerAsked_[id];   // αύριο ξαναρωτάμε (localStorage ανά ημέρα)
    t._viewOnly = true;
  } else if (!opts.fresh && !t.done && cnpIsMine(t, me.id) && !d.timerHere && askedToday) {
    t._viewOnly = true;   // απάντησε σήμερα «μόνο θα δω» — ίδιο αποτέλεσμα, χωρίς ερώτηση
  }
  if (t._viewOnly) {
    /* Η διαγραφή δεν χρειάζεται χρονόμετρο: όποιος έχει το δικαίωμα, σβήνει και από εδώ. */
    lockCard('Μόνο προβολή — πάτα «Ξεκίνα τον χρόνο» για να δουλέψεις', '#tStart,#dViewStart,#dDel');
    const banner = document.createElement('div');
    banner.className = 'tk-viewonly';
    banner.innerHTML = `${I.eye} <b>Μόνο προβολή</b>
      <span class="mut">δεν τρέχει χρόνος</span>
      <span style="flex:1"></span>
      <button class="btn btn-sm btn-p" id="dViewStart">▶ Ξεκίνα τον χρόνο</button>`;
    /* ΜΕΣΑ στην κύρια στήλη, όχι στο .drawer-b: εκεί το flex το έκανε ΤΡΙΤΗ
       στήλη και έπιανε ολόκληρο πλάτος δίπλα στο περιεχόμενο. Εδώ είναι μια
       λεπτή λωρίδα πάνω από το ζητούμενο. */
    const host = dr.querySelector('.tk-col-main') || dr.querySelector('.drawer-b');
    if (host) { host.prepend(banner); }
    const vs = $('#dViewStart', dr);
    if (vs) { vs.onclick = async () => { await api('timer_start', {task: id}).catch(() => null); openTask(id); }; }
  }

  /* Συνημμένα ενεργειών: ίδιος μηχανισμός αρχείων, δικό τους «καλάθι» (ref_type=check). */
  if ($('#dCheckFiles', dr) && window.cnpAttachments) {
    /* Σε κλειδωμένη εργασία το Ctrl+V δεν πρέπει να ανεβάζει screenshot: το πεδίο
       είναι κρυφό, αλλά ο listener του προχείρου θα δούλευε ακόμη. */
    window.cnpAttachments($('#dCheckFiles', dr), {module: 'task', refType: 'check', refId: id,
      paste: !t.done, canDelete: !t.done,
      onCount: n => attCount($('#dCheckSum', dr), n)});
  }

}
/**
 * Η εκτίμηση γράφεται σε ΩΡΕΣ γιατί έτσι τη σκέφτεται ο τεχνικός («μιάμιση»),
 * αλλά αποθηκεύεται σε λεπτά. Δεχόμαστε και κόμμα και τελεία — ένα πεδίο που
 * απορρίπτει το «1,5» σε ελληνικό πληκτρολόγιο είναι πεδίο που δεν συμπληρώνεται.
 */
function estMins(v) {
  const n = parseFloat(String(v == null ? '' : v).trim().replace(',', '.'));
  return isFinite(n) && n > 0 ? Math.round(n * 60) : 0;
}

/**
 * Το «Συνημμένα» πρέπει να φωνάζει όταν ΕΧΕΙ αρχεία. Κλειστό και γκρίζο, κανείς δεν
 * το ανοίγει — και το συνημμένο μένει αόρατο (το ζήσαμε στο task #120). Με αρχεία:
 * αριθμός σε παρένθεση και χρώμα, ώστε να φαίνεται χωρίς κλικ.
 */
function attCount(sum, n) {
  if (!sum) { return; }
  const b = sum.querySelector('[data-attn]');
  if (b) { b.textContent = n ? ' (' + n + ')' : ''; }
  sum.classList.toggle('has-att', !!n);
}

/** Άμεσο κλείσιμο ΧΩΡΙΣ ερώτηση — το καλούν τα views ΜΕΤΑ από επιτυχή αποθήκευση. */
/* ═══ ΠΟΤΕ ΣΤΑΜΑΤΑΕΙ ΜΟΝΟ ΤΟΥ ΤΟ ΧΡΟΝΟΜΕΤΡΟ ══════════════════════════════════
   ΟΧΙ όταν κλείνει η καρτέλα: ο χειριστής ξεκινά τον χρόνο και φεύγει να
   δουλέψει εκεί που πρέπει — σε server, σε πάνελ πελάτη, σε απομακρυσμένη
   σύνδεση. Κλειστή καρτέλα δεν σημαίνει «σταμάτησε η δουλειά».
   ΟΧΙ από αδράνεια: όσο δουλεύει έξω από την εφαρμογή δεν στέλνει παλμό — θα
   κόβαμε ακριβώς τον χρόνο που όντως δούλεψε.
   ΝΑΙ όταν δηλώσει νέα κατάσταση: αυτό είναι το «τελείωσα εδώ», ακόμη κι αν
   ξέχασε το Stop. Το κάνει ο server· εδώ απλώς το λέμε, με δρόμο επιστροφής. */
/** Ερώτηση (όχι κόψιμο) όταν ένα χρονόμετρο τρέχει πολλές ώρες. */
function timerCheckPop(t) {
  window._cnpTimerPop_ = window._cnpTimerPop_ || {};
  if (window._cnpTimerPop_[t.task]) { return; }
  window._cnpTimerPop_[t.task] = 1;
  const hrs = fmtMin(t.mins);
  let w = $('#chatPops');
  if (!w) { w = document.createElement('div'); w.id = 'chatPops'; document.body.appendChild(w); }
  const el = document.createElement('div');
  el.className = 'chat-pop meet-pop soon';
  el.innerHTML = `<div class="cp-h"><span class="mp-ic">⏱</span><b>Τρέχει ${esc(hrs)}</b>
      <span style="flex:1"></span><button class="cp-x" title="Κλείσιμο">✕</button></div>
    <div class="cp-b"><b class="mp-t">${esc(t.title || 'Εργασία #' + t.task)}</b>
      <div class="mp-meta">ξεκίνησε ${esc(t.sinceTxt)} · ακόμα δουλεύεις πάνω της;</div></div>
    <div class="cp-f">
      <button class="btn btn-sm btn-o" data-tk="go">Ναι, συνεχίζω</button>
      <button class="btn btn-sm btn-p" data-tk="stop">Σταμάτησέ το</button>
      <button class="btn btn-sm btn-o" data-tk="open">Άνοιγμα</button></div>`;
  w.appendChild(el);
  requestAnimationFrame(() => el.classList.add('show'));
  const kill = () => { el.classList.remove('show'); setTimeout(() => el.remove(), 220); };
  el.querySelector('.cp-x').onclick = kill;
  el.querySelectorAll('[data-tk]').forEach(b => b.onclick = async () => {
    const a = b.dataset.tk;
    if (a === 'stop') {
      const r = await api('timer_stop', {billable: false, note: ''}).catch(() => null);
      if (r) { cnpTimerStoppedToast(t.task, r.mins); }
    } else if (a === 'open') { openTask(t.task); }
    kill();
  });
  chatBeep();
}

function cnpTimerStoppedToast(taskId, mins) {
  if (!mins) { return; }
  toastDo('Ο χρόνος σταμάτησε: ' + fmtMin(mins), [
    {label: '↩ Συνέχισε', on: async () => {
      await api('timer_start', {task: taskId}).catch(() => null);
      toast('Ο χρόνος μετράει ξανά');
    }},
    {label: 'Χρέωσέ το', on: async () => {
      const lg = await api('task&id=' + taskId).catch(() => null);
      const logs = (lg && lg.timelogs) || [];
      const last = logs.length ? logs[logs.length - 1] : null;
      if (!last) { toast('Δεν βρέθηκε η καταχώρηση', true); return; }
      const rr = await api('time_bill', {id: last.id, billable: true}).catch(e => ({err: e.message}));
      toast(rr && rr.err ? rr.err : 'Σημάνθηκε χρεώσιμο', !!(rr && rr.err));
    }},
  ]);
}

function closeDrawer() {
  clearInterval(timerInt);
  /* Το κλείσιμο ΔΕΝ σταματά χρόνο — ο χειριστής συνεχίζει να δουλεύει αλλού. */
  /* ΟΧΙ `.ovl` σκέτο: αυτό έσβηνε ΚΑΘΕ overlay της σελίδας, και μαζί παράθυρα που δεν
     έχουν καμία σχέση με την κάρτα εργασίας — η γρήγορη απάντηση, η μέρα ενός ανθρώπου,
     οι συντομεύσεις. Όποιο παράθυρο ζει μόνο του σημαδεύεται `ovl-keep`. */
  $$('.ovl:not(.ovl-keep),.drawer').forEach(el => { el.classList.remove('show'); setTimeout(() => el.remove(), 300); });
}

/* ═══ Popups: ΔΕΝ κλείνουν με κλικ έξω — μόνο από ✕/Άκυρο/ESC, και ρωτούν αν
   υπάρχουν μη αποθηκευμένες αλλαγές. Κεντρικό, ισχύει για ΟΛΟ το project. ═══ */

// 1) Σήμανση «βρόμικου» popup σε κάθε πληκτρολόγηση/αλλαγή μέσα του
function _cnpMarkDirty(e) {
  const box = e.target.closest && e.target.closest('.drawer, .pal-box');
  if (box && !box.dataset.cnpClean) { box.dataset.dirty = '1'; _cnpPaintSave(box); }
}
document.addEventListener('input', _cnpMarkDirty, true);
document.addEventListener('change', _cnpMarkDirty, true);

/* Το κουμπί αποθήκευσης δείχνει αν υπάρχει κάτι να αποθηκευτεί. Ένα πάντα-μπλε
   «Αποθήκευση» δεν λέει τίποτα· γκρίζο σημαίνει «δεν άλλαξες κάτι». Δεν το
   κάνουμε disabled — ο χειριστής μπορεί να θέλει να σώσει έτσι κι αλλιώς. */
function _cnpPaintSave(box) {
  const b = _cnpSaveBtn(box);
  if (!b) { return; }
  const dirty = box.dataset.dirty === '1';
  b.classList.toggle('btn-p', dirty);
  b.classList.toggle('btn-o', !dirty);
  b.classList.toggle('is-clean', !dirty);
  b.classList.toggle('has-changes', dirty);
  b.title = dirty ? 'Υπάρχουν αλλαγές που δεν έχουν αποθηκευτεί' : 'Δεν έχεις αλλάξει κάτι ακόμη';
  /* Μια λέξη δίπλα στο κουμπί: το «γκρίζο = τίποτα ν' αποθηκευτεί» δεν το διαβάζει κανείς. */
  let hint = b.parentElement ? b.parentElement.querySelector('.unsaved-hint') : null;
  if (dirty && !hint && b.parentElement) { hint = document.createElement('span'); hint.className = 'unsaved-hint'; hint.textContent = '● μη αποθηκευμένες αλλαγές'; b.after(hint); }
  if (!dirty && hint) { hint.remove(); }
}
/** Σημάδεψε χειροκίνητα (για κουμπιά/chips που δεν είναι input). */
function markDirty(el) {
  const box = (el || document.querySelector('.drawer.show, .pal-box'));
  const b2 = box && box.closest ? box.closest('.drawer, .pal-box') : box;
  if (b2) { b2.dataset.dirty = '1'; _cnpPaintSave(b2); }
}
window.CNP_markDirty = markDirty;

/** Το κουμπί αποθήκευσης ενός popup (για την επιλογή «Αποθήκευση» στην ερώτηση). */
function _cnpSaveBtn(box) {
  /* Η αναζήτηση με το ΚΕΙΜΕΝΟ είναι για τα popup. Στην καρτέλα εργασίας δεν
     υπάρχει πια κουμπί αποθήκευσης (τα πεδία σώζονται μόνα τους) και το
     «Καταχώρηση» του χρόνου ΔΕΝ είναι αυτό — θα πατιόταν κατά λάθος. */
  if (!box.querySelector('[data-save]') && box.classList && box.classList.contains('drawer')) { return null; }
  return box.querySelector('[data-save]')
    || Array.prototype.find.call(box.querySelectorAll('.btn-p, button.btn'),
      b => /αποθήκευ|δημιουργ|καταχώρ|save/i.test(b.textContent || '')) || null;
}

/**
 * Κλείσιμο popup με έλεγχο αλλαγών.
 * @param {Element} box  το .drawer ή .pal-box (αν λείπει → το ανοιχτό)
 * @returns {Promise<boolean>} true αν έκλεισε
 */
async function cnpAskClose(box) {
  box = box || document.querySelector('.drawer.show, .drawer') || document.querySelector('.pal-box');
  const isDrawer = box && box.classList.contains('drawer');
  const kill = () => {
    if (isDrawer || !box) { closeDrawer(); return; }
    const ovl = box.closest('.ovl') || box;
    ovl.classList.remove('show');
    setTimeout(() => ovl.remove(), 200);
  };
  if (!box || box.dataset.dirty !== '1') { kill(); return true; }
  const saveBtn = _cnpSaveBtn(box);
  const r = await cnpDialog({
    title: I.alert + ' Μη αποθηκευμένες αλλαγές',
    body: 'Έκανες αλλαγές που δεν έχουν αποθηκευτεί.' + (saveBtn ? ' Πάτα «Αποθήκευση» για να κρατηθούν (πεδία, ζητούμενο, κείμενο ενέργειας) — ή «Απόρριψη» για να χαθούν.' : ''),
    ok: saveBtn ? 'Αποθήκευση' : 'Κλείσιμο χωρίς αποθήκευση',
    cancel: 'Συνέχεια επεξεργασίας',
    third: saveBtn ? 'Απόρριψη αλλαγών' : null,
  });
  if (r === false || r === null) { return false; }          // Άκυρο → μένει ανοιχτό
  if (r === 'third' || !saveBtn) { kill(); return true; }   // Απόρριψη
  box.dataset.dirty = '';                                    // Αποθήκευση → το view κλείνει μόνο του
  saveBtn.click();
  return true;
}
window.cnpAskClose = cnpAskClose;

// 2) Κάθε popup παίρνει αυτόματα ✕ (αν δεν έχει) — και κανένα δεν κλείνει με κλικ έξω
new MutationObserver(ms => {
  ms.forEach(m => m.addedNodes.forEach(node => {
    if (node.nodeType !== 1) { return; }
    const ovl = node.classList && node.classList.contains('ovl') ? node : null;
    if (!ovl) { return; }
    ovl.addEventListener('click', ev => { if (ev.target === ovl) { ev.stopPropagation(); } }, true);
    const box = ovl.querySelector('.pal-box');
    /* ΑΝ ΕΧΕΙ ΗΔΗ ✕, ΜΗΝ ΒΑΛΕΙΣ ΔΕΥΤΕΡΟ. Ο έλεγχος κοίταζε μόνο `.pal-x` και
       `.drawer-x`, οπότε τα popup που φτιάχνουν το δικό τους μέσα στην κεφαλίδα
       (`.qr-x`: καρτέλα κλήσης, γρήγορο ticket, γρήγορο αίτημα) έπαιρναν ΔΕΥΤΕΡΟ
       ✕ από πάνω τους — δύο Χ, το ένα πάνω στο άλλο. */
    if (!box || box.querySelector('.pal-x, .drawer-x, .qr-x, [data-close]')) { return; }
    /* Ερώτηση χωρίς έξοδο διαφυγής: ΚΑΝΕΝΑ ✕. Ο χειριστής πρέπει να διαλέξει. */
    if (box.dataset.noclose === '1') { return; }
    const x = document.createElement('button');
    x.className = 'pal-x'; x.type = 'button'; x.title = 'Κλείσιμο'; x.innerHTML = '✕';
    /* Σε διάλογο ερώτησης, το ✕ σημαίνει «Άκυρο» — αλλιώς το popup έφευγε χωρίς
       να απαντήσει ποτέ η υπόσχεση και ο κώδικας πίσω του δεν εκτελούνταν. */
    const noBtn = box.querySelector('#cnpDlgNo');
    x.onclick = () => (noBtn ? noBtn.click() : cnpAskClose(box));
    box.style.position = box.style.position || 'relative';
    box.prepend(x);
  }));
}).observe(document.body, {childList: true});

// 3) ESC → ελεγχόμενο κλείσιμο του πιο πρόσφατου popup
document.addEventListener('keydown', e => {
  if (e.key !== 'Escape') { return; }
  const boxes = document.querySelectorAll('.pal-box, .drawer');
  if (!boxes.length) { return; }
  const top = boxes[boxes.length - 1];
  if (top.dataset && top.dataset.noclose === '1') { e.stopPropagation(); return; }
  if (top.closest('.ovl') && top.querySelector('#cnpDlgOk')) { return; }   // τα ίδια τα dialogs
  e.stopPropagation();
  cnpAskClose(top);
}, true);

/* ═════════ Η ΜΕΡΑ ΜΟΥ ═════════ */
/* ═══ ΤΟ ΚΑΛΩΣΟΡΙΣΜΑ ΤΟΥ ΠΡΩΙΝΟΥ ═══
   Μία φορά την ημέρα, στην πρώτη εμφάνιση του πρωινού. Δεν είναι ειδοποίηση και
   δεν πάει πουθενά αλλού: το βλέπει μόνο ο ίδιος. Τρία πράγματα μαζί — καλημέρα,
   μια ευχή, και πού βρίσκεται σε σχέση με το ωράριό του. Η καθυστέρηση λέγεται
   ως γεγονός, όχι ως παρατήρηση: πληροφορία για τον ίδιο, όχι καταγγελία. */
function cnpGreet(g) {
  if (!g || !g.hi) { return; }
  if (document.querySelector('.gr-ovl')) { return; }   // ποτέ δύο μαζί
  /* ΔΕΥΤΕΡΗ ΑΣΦΑΛΕΙΑ, ΣΤΟΝ BROWSER. Ο server κρατά ήδη «δόθηκε σήμερα», αλλά αν
     για οποιονδήποτε λόγο ξαναφτάσει (δύο καρτέλες, επαναφορά σημαδιού, δοκιμή),
     δεν θα ξαναπεταχτεί στον ίδιο άνθρωπο την ίδια μέρα. Ένα καλωσόρισμα που
     εμφανίζεται δεύτερη φορά παύει να είναι καλωσόρισμα. */
  const day = new Date().toISOString().slice(0, 10) + '|' + (g.kind || '');
  try {
    if (localStorage.cnpGreetSeen === day) { return; }
    localStorage.cnpGreetSeen = day;
  } catch (e) { /* ιδιωτική περιήγηση: ο server αρκεί */ }
  const tone = {early: 'ok', ontime: 'ok', late: 'warn', plain: 'ok'}[g.kind] || 'ok';
  const ico = {early: '🌅', ontime: '☕', late: '⏰', plain: '🌞'}[g.kind] || '🌞';
  const ovl = document.createElement('div');
  ovl.className = 'ovl show gr-ovl';
  ovl.innerHTML = `<div class="gr-box gr-${tone}" role="dialog" aria-label="Καλημέρα">
    <div class="gr-ico">${ico}</div>
    <b class="gr-hi">${esc(g.hi)}</b>
    ${g.line ? `<div class="gr-line">${esc(g.line)}</div>` : ''}
    ${/* ΠΡΩΤΑ Η ΕΙΚΟΝΑ ΤΗΣ ΗΜΕΡΑΣ, ΜΕΤΑ Η ΕΥΧΗ. Μια ευχή χωρίς εικόνα είναι
         ευγένεια· με την εικόνα γίνεται προετοιμασία — ξέρεις τι σε περιμένει
         πριν πατήσεις «Ξεκινάμε». */''}
    ${(g.sum || []).length ? `<ul class="gr-sum">${g.sum.map(x =>
      `<li><span class="gr-sic">${esc(x.ic || '')}</span>${esc(x.txt || '')}</li>`).join('')}</ul>` : ''}
    <div class="gr-wish">${esc(g.wish || '')}</div>
    <button class="btn btn-p gr-go" id="grGo">Ξεκινάμε</button>
  </div>`;
  document.body.appendChild(ovl);
  const close = () => { ovl.classList.remove('show'); setTimeout(() => ovl.remove(), 180); };
  $('#grGo', ovl).onclick = close;
  ovl.onclick = e => { if (e.target === ovl) { close(); } };
  document.addEventListener('keydown', function k(e) {
    if (e.key === 'Escape') { close(); document.removeEventListener('keydown', k); }
  });
  setTimeout(() => { const b = $('#grGo', ovl); if (b) { b.focus(); } }, 60);
}

async function vMyDay() {
  /* ═══════════ «Η μέρα μου» v2 (18/9/2026) ═══════════
     Μία ματιά = μία απάντηση: «τι κάνω τώρα;». Δομή κατά τις καλές πρακτικές των
     Today-οθονών (Todoist/Things/Sunsama/Linear):
       1. Τώρα         — τι τρέχει (χρονόμετρο), η επόμενη σύσκεψη, πρόοδος ημέρας
       2. Θέλουν εσένα — ό,τι ΠΕΡΙΜΕΝΕΙ απάντηση/ενέργεια, ταξινομημένο κατά σοβαρότητα,
                         ένα κουμπί ανά γραμμή (όχι λίστες ανά πηγή)
       3. Το πρόγραμμα — συσκέψεις με ώρα + εργασίες σήμερα + follow-ups + σημειώσεις, με ▶/✔
       4. Ουρά tickets — η σειρά της ημέρας (πτυσσόμενη)
       Δεξιά: αριθμοί, καθοδήγηση, προθεσμίες 7 ημερών, «περιμένω άλλους», «η ομάδα μου» (πτυσσόμενα).
     Κανόνας: ό,τι είναι ΓΙΑ ΑΛΛΟΥΣ (ομάδα, περιμένω) ποτέ πάνω από ό,τι είναι για μένα. */
  const _h = new Date().getHours();
  const [_g, _e] = _h < 5 ? ['Καληνύχτα', '🌙'] : _h < 12 ? ['Καλημέρα', '☀️'] : _h < 18 ? ['Καλησπέρα', '🌤️'] : _h < 22 ? ['Καλησπέρα', '🌆'] : ['Καληνύχτα', '🌙'];
  const me = S.boot.me;
  setTop('Η μέρα μου', _g + ', ' + me.name.split(' ')[0] + ' ' + _e);
  const c = $('#content');
  cnpSkel(c, '<div class="myd-wrap"><div class="skel" style="height:96px;margin-bottom:14px"></div><div class="myd-cols"><div><div class="skel" style="height:220px"></div></div><div><div class="skel" style="height:220px"></div></div></div></div>');
  const [d, mt, ovr] = await Promise.all([
    api('myday'),
    api('my_todos').catch(() => ({todos: []})),
    api('overruns').catch(() => ({items: []})),
  ]);
  const todos = mt.todos || [], overruns = ovr.items || [], pend = d.pending || {help: [], meetings: [], mine: []};
  const st = d.stats || {}, TODAY = today(), fin = doneStatus();
  const dayName = new Date().toLocaleDateString('el-GR', {weekday: 'long', day: 'numeric', month: 'long'});
  const hm = s => (s || '').slice(11, 16);
  const colL = {bad: 'var(--bad)', warn: 'var(--warn)', tip: 'var(--brand)', ok: 'var(--ok)', info: 'var(--info)'};
  const open = k => { try { return localStorage.getItem('cnpMd:' + k); } catch (e) { return null; } };
  const setOpen = (k, v) => { try { localStorage.setItem('cnpMd:' + k, v ? '1' : '0'); } catch (e) {} };

  /* ── 2. Θέλουν εσένα: μία λίστα από όλες τις πηγές, με βαθμό σοβαρότητας ── */
  const att = [];
  const seenTask = new Set();
  const hkLbl = {voice: ['🔊', 'σε καλεί στη φωνή'], checkin: ['❓', 'ρωτά τι γίνεται'], offer: ['📄', 'ζητά προσφορά'],
    mention: ['💬', 'σε ρωτά — περιμένει απάντηση'], help: ['🆘', 'χρειάζεται βοήθεια']};
  (pend.help || []).forEach(h => {
    const k = hkLbl[h.kind] || hkLbl.help;
    att.push({sev: h.kind === 'help' || h.kind === 'voice' ? 1 : 3, lvl: h.kind === 'help' ? 'bad' : 'warn', ic: k[0], why: k[1],
      title: h.from, sub: (h.message || '').slice(0, 110) + (h.taskTitle ? ' · ' + h.taskTitle : ''), when: h.at,
      act: 'Άνοιξε', on: () => openRequestQuick(h.id, vMyDay),
      done: h.kind !== 'checkin' ? async () => { await api('help_done', {id: h.id}).catch(() => {}); } : null});
    if (h.taskId) { seenTask.add(h.taskId); }
  });
  (pend.meetings || []).forEach(m => {
    att.push({sev: 3, lvl: 'info', ic: '📅', why: 'πρόσκληση', title: m.title, sub: (m.whenTxt || '') + (m.by ? ' · από ' + m.by : ''),
      act: '✔ Θα είμαι', on: async () => { await api('event_rsvp', {id: m.id, status: 'accepted'}).catch(() => {}); toast('✔ Δήλωσες συμμετοχή'); vMyDay(); },
      alt: '✖', altT: 'Δεν μπορώ', altOn: async () => { await api('event_rsvp', {id: m.id, status: 'declined'}).catch(() => {}); vMyDay(); }});
  });
  (d.tickets || []).forEach(tk => {
    if (tk.waitOn !== 'us') { return; }
    if (tk.over) { att.push({sev: 0, lvl: 'bad', ic: I.ticket, why: 'SLA πέρασε', title: '#' + tk.tid + ' ' + tk.title, sub: tk.status + (tk.waitDays ? ' · περιμένει ' + tk.waitDays + ' ημ.' : ''), act: 'Απάντησε', on: () => openTicketQuick(tk.id, vMyDay)}); }
    else if (tk.slaDue && (new Date(tk.slaDue.replace(' ', 'T')) - Date.now()) < 24 * 3600e3) { att.push({sev: 2, lvl: 'warn', ic: I.ticket, why: 'SLA ' + tShort(tk.slaDue), title: '#' + tk.tid + ' ' + tk.title, sub: tk.status, act: 'Απάντησε', on: () => openTicketQuick(tk.id, vMyDay)}); }
    else if (tk.waitDays >= 2) { att.push({sev: 5, lvl: 'tip', ic: I.ticket, why: 'περιμένει ' + tk.waitDays + ' ημ.', title: '#' + tk.tid + ' ' + tk.title, sub: tk.status, act: 'Απάντησε', on: () => openTicketQuick(tk.id, vMyDay)}); }
  });
  /* «Θέλουν εσένα» = ό,τι αφορά ΕΜΕΝΑ. Ο server σημαδεύει κάθε προθεσμία με `mine`· χωρίς αυτό
     ο Full έβλεπε εδώ κάθε εκπρόθεσμο έργο/προσφορά της εταιρείας και η λίστα έχανε την αξία της
     (22/9/2026). Ό,τι δεν είναι δικό μου μένει στις «Προθεσμίες μπροστά» δεξιά. */
  (d.deadlines || []).filter(x => x.mine !== false).forEach(x => {
    if (x.kind === 'task' && x.days !== null && x.days < 0 && !seenTask.has(x.id)) {
      seenTask.add(x.id);
      att.push({sev: 2, lvl: 'bad', ic: I.checkSquare, why: 'εκπρόθεσμη ' + Math.abs(x.days) + ' ημ.', title: x.title, sub: x.sub, act: 'Άνοιξε', on: () => openTask(x.id), task: x.id});
    } else if (x.kind === 'offer' && x.days !== null && x.days <= 0) {
      att.push({sev: 4, lvl: 'warn', ic: I.doc, why: x.days < 0 ? 'follow-up άργησε' : 'follow-up σήμερα', title: x.title, sub: x.sub, act: 'Άνοιξε', on: () => go('offers')});
    } else if (x.kind === 'project' && x.days !== null && x.days < 0) {
      att.push({sev: 4, lvl: 'warn', ic: I.folder, why: 'έργο εκπρόθεσμο ' + Math.abs(x.days) + ' ημ.', title: x.title, sub: x.sub, act: 'Board', on: () => go('board', x.id)});
    }
  });
  (d.plan || []).forEach(t => {
    if (t.sched && t.sched < TODAY && !seenTask.has(t.id)) {
      seenTask.add(t.id);
      att.push({sev: 4, lvl: 'warn', ic: I.checkSquare, why: 'προγραμματισμένη από ' + dShort(t.sched), title: t.title, sub: t.pname, act: 'Άνοιξε', on: () => openTask(t.id), task: t.id});
    }
  });
  todos.filter(t => t.overdue).forEach(t => att.push({sev: 6, lvl: 'tip', ic: I.bell, why: 'υπενθύμιση', title: t.text, sub: t.pname, act: '✓', on: async () => { await api('todo_toggle', {id: t.id}); vMyDay(); }}));
  att.sort((a, b) => a.sev - b.sev);

  /* ── 3. Το πρόγραμμα σήμερα ── */
  const evs = (d.events || []).slice().sort((a, b) => (a.allDay ? 0 : 1) - (b.allDay ? 0 : 1) || a.start.localeCompare(b.start));
  const nextEv = evs.find(e => !e.over && !e.now && !e.allDay);
  const nowEv = evs.find(e => e.now && !e.allDay);
  const planTasks = [];
  const pushT = (t, tag) => { if (!planTasks.some(x => x.id === t.id) && !seenTask.has(t.id)) { planTasks.push(Object.assign({tag}, t)); } };
  (d.plan || []).forEach(t => pushT(t, t.sched === TODAY ? 'σήμερα' : ''));
  (d.balls || []).forEach(t => pushT(t, 'μπάλα'));
  (d.deadlines || []).filter(x => x.kind === 'task' && x.days === 0).forEach(x => { if (!planTasks.some(t => t.id === x.id) && !seenTask.has(x.id)) { planTasks.push({id: x.id, title: x.title, pname: x.sub, pcolor: '#8595ac', prio: 0, tag: 'λήγει σήμερα', due: TODAY}); } });
  planTasks.sort((a, b) => (b.prio || 0) - (a.prio || 0) || ((a.due || '9') < (b.due || '9') ? -1 : 1));
  /* Ό,τι τρέχει τώρα ανήκει στο σημερινό πρόγραμμα, ακόμη κι αν δεν είχε ημερομηνία. */
  if (d.timer && !planTasks.some(t => t.id === d.timer.task)) { planTasks.unshift({id: d.timer.task, title: d.timer.title, pname: '', pcolor: '#16a26a', prio: 0, tag: ''}); }
  else if (d.timer) { const i = planTasks.findIndex(t => t.id === d.timer.task); if (i > 0) { planTasks.unshift(planTasks.splice(i, 1)[0]); } }
  const timer = d.timer;
  const planN = evs.length + planTasks.length + (d.follows || []).length;
  const doneN = d.doneToday || 0;
  const ring = (n, tot) => { const p = tot ? Math.min(100, Math.round(n / tot * 100)) : 0; return `<div class="myd-ring" style="--p:${p}"><b>${n}</b><small>/${tot}</small></div>`; };

  /* ── γραμμές ── */
  /* Το κλειδί ΔΕΝ είναι η θέση: μετά από κάθε ενέργεια η λίστα ξαναχτίζεται και το «a0»
     θα σήμαινε άλλο πράγμα — το «Επόμενο» θα ξαναπρότεινε ό,τι μόλις πέρασες. */
  const attKey = a => (a.task ? 't' + a.task : 'w' + String(a.title || '').slice(0, 40));
  const attRow = (a, i) => `<div class="myd-row att ${a.lvl}" data-atti="${i}" data-attk="${esc(attKey(a))}">
    <span class="myd-ic" style="color:${colL[a.lvl]}">${a.ic}</span>
    <span class="myd-why" style="color:${colL[a.lvl]};background:${colL[a.lvl]}18">${esc(a.why)}</span>
    <span class="myd-t"><b>${esc(a.title)}</b>${a.sub ? `<span class="mut"> · ${esc(a.sub)}</span>` : ''}</span>
    <span class="myd-a">${a.done ? `<button class="btn btn-sm btn-o" data-attdone="${i}" title="Τακτοποιήθηκε — φεύγει από εδώ">✓</button>` : ''}
      ${a.alt ? `<button class="btn btn-sm btn-o" data-attalt="${i}" title="${esc(a.altT || '')}">${a.alt}</button>` : ''}
      <button class="btn btn-sm btn-p" data-attgo="${i}">${esc(a.act)}</button></span></div>`;
  const evRow = e => `<div class="myd-row ev${e.over ? ' past' : ''}${e.now ? ' now' : ''}" data-cal="${e.id}">
    <span class="myd-time">${e.allDay ? 'όλη μέρα' : hm(e.start) + '–' + hm(e.end)}</span>
    <span class="myd-t"><b>${esc(e.title)}</b><span class="mut"> · ${e.left ? 'βγήκες ' + hm(e.leftAt) + ' · ' : ''}${e.kind === 'appointment' ? 'ραντεβού' : e.kind === 'meeting' ? 'σύσκεψη' : esc(e.kind)}${e.clientName ? ' · ' + esc(e.clientName) : ''}${e.location && !/^https?:/i.test(e.location) ? ' · ' + esc(e.location) : ''}${e.mode ? ' · ' + esc(e.mode) : ''}</span></span>
    ${e.location && /^https?:/i.test(e.location) ? `<a class="btn btn-sm btn-o" href="${esc(e.location)}" target="_blank" rel="noopener" onclick="event.stopPropagation()" style="flex:none" title="${esc(e.location)}">🎥 Μπες</a>` : ''}
    ${/* «ΒΓΗΚΑ» ΟΣΟ ΤΡΕΧΕΙ, ΟΧΙ ΜΟΝΟ ΣΤΗΝ ΥΠΕΡΒΑΣΗ. Η σύσκεψη μπορεί να συνεχίζεται
         για τους υπόλοιπους κι εσύ να έχεις φύγει· μέχρι τώρα έπρεπε να περιμένεις
         να ξεπεράσει την ώρα της για να το δηλώσεις. Η απάντηση είναι ατομική:
         φεύγει από ΤΟ ΔΙΚΟ σου πρόγραμμα, ελευθερώνεται η κατάστασή σου και
         ξανανοίγει το τηλέφωνό σου — οι άλλοι συνεχίζουν. */''}
    ${e.now && e.kind !== 'leave' ? `<button class="btn btn-sm btn-o" data-evleft="${e.id}" style="flex:none"
      title="Βγήκα — φεύγει από το πρόγραμμά μου και ελευθερώνεται η κατάστασή μου· οι υπόλοιποι συνεχίζουν">Βγήκα</button>` : ''}
    ${e.now ? '<span class="pill pill-ok" style="flex:none">τώρα</span>' : e.rsvp === 'accepted' ? '<span class="pill pill-mut" style="flex:none" title="Δήλωσες συμμετοχή">✔</span>' : e.rsvp === '' && !e.over ? '<span class="pill pill-warn" style="flex:none" title="Δεν απάντησες στην πρόσκληση">αναπάντητη</span>' : ''}</div>`;
  const prioDot = p => ['#8595ac', '#eba63c', '#e2515f'][p || 0];
  /* Η ένδειξη «⚡ μπάλα» ξεχωρίζει μόνο όταν ΔΕΝ την έχουν όλες — αλλιώς είναι επανάληψη σε κάθε γραμμή. */
  const ballAll = planTasks.length > 1 && planTasks.every(t => t.ball === me.id);
  const taskRow = t => { const here = timer && timer.task === t.id; return `<div class="myd-row task${here ? ' live' : ''}" data-mdtask="${t.id}">
    <button class="myd-play${here ? ' on' : ''}" data-mdplay="${t.id}" title="${here ? 'Τρέχει ο χρόνος εδώ — πάτα για στοπ' : 'Ξεκίνα τον χρόνο σε αυτή'}">${here ? I.stop : I.play}</button>
    <span class="myd-t"><b>${esc(t.title)}</b><span class="mut"> · <span class="dot" style="background:${t.pcolor || '#8595ac'};width:7px;height:7px;display:inline-block;border-radius:50%"></span> ${esc(t.pname || 'Χωρίς έργο')}${t.est ? ' · ⏱ ' + fmtMin(t.est) : ''}</span></span>
    ${t.prio ? `<span class="dot" style="background:${prioDot(t.prio)};flex:none" title="Προτεραιότητα"></span>` : ''}
    ${/* Η ΚΑΤΑΣΤΑΣΗ ΦΑΙΝΕΤΑΙ ΚΑΙ ΕΔΩ. Το πρόγραμμα της ημέρας έδειχνε τι θα κάνεις,
         όχι σε τι σημείο είναι: «Backlog» και «Έλεγχος» έμοιαζαν ίδια γραμμή, ενώ
         η μία θέλει ξεκίνημα και η άλλη μια ματιά. Ίδιο χρώμα και ίδιο όνομα με
         κάθε άλλη οθόνη — stPill, μία πηγή. */''}
    <span class="myd-st">${stPill(t.status)}</span><span class="myd-stD">${stDot(t.status)}</span>
    ${t.ball === me.id && !ballAll ? '<span class="pill pill-info" style="flex:none">⚡ μπάλα</span>' : ''}
    ${t.tag && t.tag !== 'μπάλα' && t.tag !== 'σήμερα' ? `<span class="pill pill-warn" style="flex:none">${esc(t.tag)}</span>` : ''}
    ${fin ? `<button class="btn btn-sm btn-o myd-done" data-mddone="${t.id}" title="Ολοκλήρωση">✔</button>` : ''}</div>`; };
  const folRow = f => `<div class="myd-row fol" data-lead="${f.lead}">
    <span class="myd-ic" style="color:var(--ok)">${I.phone}</span>
    <span class="myd-t"><b>${esc(f.who)}</b>${f.note ? `<span class="mut"> · ${esc(f.note)}</span>` : ''}</span>
    ${f.phone ? `<a class="pill pill-mut" href="tel:${esc(f.phone)}" onclick="event.stopPropagation()" style="flex:none">${esc(f.phone)}</a>` : ''}</div>`;
  const todoRow = t => `<label class="myd-row todo"><input type="checkbox" data-mdtog="${t.id}">
    <span class="myd-t">${esc(t.text)}<span class="mut"> · ${esc(t.pname)}</span></span>
    ${t.remind ? `<span class="pill ${t.overdue ? 'pill-bad' : 'pill-mut'}" style="flex:none">${I.clock} ${tShort(t.remind)}</span>` : ''}</label>`;
  const sec = (key, title, hint, count, body, opts) => { opts = opts || {};
    const st0 = open(key), isOpen = st0 === null ? !opts.collapsed : st0 === '1';
    return `<div class="card myd-sec ${opts.cls || ''}${isOpen ? '' : ' closed'}" data-sec="${key}">
      <div class="card-h myd-h" data-sect="${key}"><span class="myd-h-ic">${opts.ic || ''}</span>${title}
        ${hint ? `<span class="mut myd-hint">— ${hint}</span>` : ''}
        ${count !== null && count !== undefined ? `<span class="kb-n">${count}</span>` : ''}
        ${opts.link ? `<a class="myd-link" data-go="${opts.link[0]}">${opts.link[1]}</a>` : ''}
        <span class="myd-chev">${I.chev}</span></div>
      <div class="myd-b">${body}</div></div>`; };

  /* ── 1. Τώρα ── */
  const elapsed = since => { const s = Math.max(0, Math.floor((Date.now() - new Date(since.replace(' ', 'T')).getTime()) / 1000)); return `${String(Math.floor(s / 3600)).padStart(2, '0')}:${String(Math.floor(s % 3600 / 60)).padStart(2, '0')}`; };
  const minsTo = e => Math.round((new Date(e.start.replace(' ', 'T')) - Date.now()) / 60000);
  const hero = `<div class="myd-hero">
    <div class="myd-hero-l"><div class="myd-date">${esc(dayName)}</div>
      ${timer ? `<div class="myd-now live"><span class="myd-now-k">▶ Δουλεύεις τώρα</span><b data-mdtask="${timer.task}" class="myd-now-t">${esc(timer.title)}</b>
          <span class="myd-now-e" id="mdElapsed">${elapsed(timer.since)}</span><button class="btn btn-sm btn-danger" id="mdStop">${I.stop} Stop</button></div>`
        : nowEv ? `<div class="myd-now"><span class="myd-now-k">📅 Τώρα</span><b class="myd-now-t" data-cal="${nowEv.id}">${esc(nowEv.title)}</b><span class="myd-now-e">έως ${hm(nowEv.end)}</span></div>`
        : `<div class="myd-now idle"><span class="myd-now-k">Δεν τρέχει χρόνος</span><span class="mut">διάλεξε από το πρόγραμμα και πάτα ▶ — ο χρόνος που δεν ξεκινά, δεν καταγράφεται</span></div>`}
    </div>
    <button class="btn btn-p myd-next-b" id="mydNext" title="Πήγαινέ με στο επόμενο που πρέπει να πιάσω (n)">▶ Επόμενο</button>
    <button class="myd-kbd" id="mydLay" title="Τι βλέπω σε αυτή την οθόνη και με ποια σειρά">⚙</button>
    <button class="myd-kbd" id="mydKbd" title="Συντομεύσεις πληκτρολογίου (?)">⌨</button>
    <div class="myd-hero-r">
      <div class="myd-kpi">${ring(doneN, doneN + planTasks.length)}<div class="myd-kpi-l">έγιναν<br>σήμερα</div></div>
      <div class="myd-kpi"><div class="myd-kpi-n">${fmtMin(st.minsToday || 0)}</div><div class="myd-kpi-l">χρόνος<br>σήμερα</div></div>
      <div class="myd-kpi"><div class="myd-kpi-n" style="color:${att.length ? 'var(--bad)' : 'var(--ok)'}">${att.length}</div><div class="myd-kpi-l">θέλουν<br>εσένα</div></div>
      <div class="myd-kpi"><div class="myd-kpi-n">${st.tasks || 0}</div><div class="myd-kpi-l">ανοιχτές<br>εργασίες</div></div>
    </div></div>`;

  const ATT_MAX = 6;
  const attBody = att.length ? att.slice(0, ATT_MAX).map(attRow).join('') + (att.length > ATT_MAX ? `<div class="myd-more" data-attmore>${att.slice(ATT_MAX).map(attRow).join('')}</div><a class="myd-morelink" data-attmore-t>+ ${att.length - ATT_MAX} ακόμη…</a>` : '')
    : '<div class="myd-empty">✨ Τίποτα δεν σε περιμένει — καθαρό τραπέζι.</div>';
  const planBody = (evs.length ? `<div class="myd-grp">Συσκέψεις & ραντεβού</div>${evs.map(evRow).join('')}` : '')
    + (planTasks.length ? `<div class="myd-grp">Εργασίες</div>${planTasks.map(taskRow).join('')}` : '')
    + ((d.follows || []).length ? `<div class="myd-grp">Follow-ups πωλήσεων</div>${d.follows.map(folRow).join('')}` : '')
    + (todos.length ? `<div class="myd-grp">Σημειώσεις πλάνου <a class="myd-link" data-go="todos">όλες →</a></div>${todos.slice(0, 5).map(todoRow).join('')}` : '')
    /* ΤΑ ΤΗΛΕΦΩΝΑ ΕΙΝΑΙ ΜΕΡΟΣ ΤΗΣ ΜΕΡΑΣ, ΟΧΙ ΞΕΧΩΡΙΣΤΟ ΣΥΡΤΑΡΙ. Ο χρόνος που
       μίλησες μετράει ήδη στη μέρα σου· αν η καταγραφή του ζει σε άλλο κουτί,
       διαβάζεται σαν δεύτερη δουλειά αντί για την ίδια. Γεμίζει ασύγχρονα. */
    + '<div class="myd-grp" id="mydCallsG" hidden>Τηλέφωνα χωρίς καταγραφή</div><div id="mydCalls"></div>'
    + (!evs.length && !planTasks.length && !(d.follows || []).length && !todos.length ? '<div class="myd-empty">🏖️ Καθαρή μέρα — τίποτα προγραμματισμένο. Πάρε κάτι από την ουρά ή το board.</div>' : '');
  const queue = d.queue || [];
  const queueBody = queue.length ? queue.slice(0, 6).map((q, i) => `<div class="myd-row q" data-qtk="${q.id}">
      <span class="qn">${i + 1}</span>
      <span class="myd-why" style="color:${colL[q.lvl]};background:${colL[q.lvl]}18">${esc(q.why)}</span>
      <span class="myd-t"><b>${esc(q.title)}</b><span class="mut"> · #${esc(q.tid)}${q.client ? ' · ' + esc(q.client) : ''}</span></span>
      ${q.urgency === 'High' ? '<span class="pill pill-bad" style="flex:none">επείγον</span>' : ''}
      ${q.assigned ? (q.mine ? '' : `<span class="pill pill-mut" style="flex:none">${esc(adminIni(q.assigned))}</span>`) : '<span class="pill pill-warn" style="flex:none">αζήτητο</span>'}</div>`).join('')
    + (queue.length > 6 ? `<a class="myd-morelink" data-go="inbox">+ ${queue.length - 6} ακόμη στο Inbox →</a>` : '')
    : '<div class="myd-empty">Κανένα ticket δεν περιμένει 🎉</div>';

  /* δεξιά στήλη */
  const dlAhead = (d.deadlines || []).filter(x => x.days === null ? (x.hours !== null && x.hours >= 0) : x.days >= 0);
  const dlCol = x => (x.hours !== null ? (x.hours <= 4 ? 'var(--bad)' : x.hours <= 12 ? 'var(--warn)' : 'var(--brand)') : (x.days <= 1 ? 'var(--bad)' : x.days <= 3 ? 'var(--warn)' : 'var(--brand)'));
  const dlLbl = x => x.hours !== null ? (x.hours <= 48 ? `σε ${x.hours}ω` : `σε ${Math.round(x.hours / 24)} ημ.`) : (x.days === 0 ? 'σήμερα' : x.days === 1 ? 'αύριο' : `σε ${x.days} ημ.`);
  const dlIco = {project: I.folder, task: I.checkSquare, sla: I.clock, offer: I.doc};
  const dlBody = dlAhead.length ? dlAhead.slice(0, 8).map(x => `<div class="dlrow" ${x.kind === 'sla' ? `data-qtk="${x.id}"` : x.kind === 'task' ? `data-dltask="${x.id}"` : x.kind === 'offer' ? 'data-dloffer="1"' : `data-dlproj="${x.id}"`}>
      <span class="dlic" style="color:${dlCol(x)}">${dlIco[x.kind] || ''}</span>
      <span class="dlt">${esc(x.title)}<span class="mut"> · ${esc(x.sub)}${x.mine === false ? ' · <i title="Δεν είναι δικό σου — το βλέπεις ως εποπτεία">εποπτεία</i>' : ''}</span></span>
      <span class="dld" style="color:${dlCol(x)}">${esc(dlLbl(x))}</span></div>`).join('') : '<div class="myd-empty">Καμία προθεσμία μπροστά σου.</div>';
  const coach = d.coach || [];
  const coachBody = coach.map(x => `<div class="myd-coach" style="border-left-color:${colL[x.lvl]};background:${colL[x.lvl]}10">
      <span>${x.icon}</span><span>${esc(x.text)}${(x.refs || []).length ? `<span class="crefs">${x.refs.map(r => `<a class="cref" ${r.kind === 'ticket' ? `data-qtk="${r.id}"` : `data-dltask="${r.id}"`}>${esc(r.label)}</a>`).join('')}</span>` : ''}</span></div>`).join('');
  const waitN = (d.waiting || []).length + (pend.mine || []).length + (d.tickets || []).filter(t => t.waitOn === 'client').length;
  const waitBody = (pend.mine || []).map(h => `<div class="myd-row wait"><span class="myd-ic">${(hkLbl[h.kind] || hkLbl.help)[0]}</span><span class="myd-t"><b>${esc(h.to)}</b><span class="mut"> · ${esc((h.message || '').slice(0, 80))}${h.seen ? ' · το είδε' : ' · δεν το είδε ακόμη'}</span></span><button class="btn btn-sm btn-o" data-attdone-m="${h.id}" title="Ακύρωση / τακτοποιήθηκε">✓</button></div>`).join('')
    + (d.waiting || []).map(w => `<div class="myd-row wait" data-dltask="${w.id}"><span class="dot" style="background:${w.pcolor};flex:none"></span><span class="myd-t"><b>${esc(w.title)}</b><span class="mut"> · ${esc(w.pname || '—')} · περιμένει <b>${esc(w.ballName || '—')}</b></span></span><span class="pill pill-mut" style="flex:none">${esc(w.statusName || '')}</span></div>`).join('')
    + (d.tickets || []).filter(t => t.waitOn === 'client').map(tk => `<div class="myd-row wait" data-qtk="${tk.id}"><span class="myd-ic">${I.ticket}</span><span class="myd-t"><b>#${esc(tk.tid)} ${esc(tk.title)}</b><span class="mut"> · περιμένει πελάτη${tk.waitDays ? ' ' + tk.waitDays + ' ημ.' : ''}</span></span></div>`).join('')
    || '<div class="myd-empty">Δεν περιμένεις κανέναν.</div>';
  /* 👥 Η μπάρα παρουσίας είναι κοινό κομμάτι (cnpPeopleBar) — ίδια παντού. */
  const team = d.team || [];
  const teamOn = team.filter(x => x.status !== 'offline');
  const workN = team.filter(x => x.workingOn).length;
  const dayBar = cnpDayStrip(evs, {link: ['calendar', 'ημερολόγιο →']});
  const tmbBar = cnpPeopleBar(team, {
    hint: teamOn.length + ' μέσα' + (workN ? ' · ' + workN + ' με χρονόμετρο' : '') + (d.teamScope ? ' · ' + d.teamScope : ''),
    link: ['activity', 'δραστηριότητα →']});

  /* 👁 Επιβλέπω: ό,τι άνοιξα εγώ και το κάνει άλλος. Δεν είναι εκκρεμότητά μου —
     είναι η ευθύνη μου να δω ότι προχώρησε. Η σειρά έρχεται από τον server: πρώτα
     όσα κόλλησαν, μετά τα εκπρόθεσμα· όσα τρέχουν κανονικά δεν θέλουν το μάτι σου. */
  const sup = d.supervising || {items: [], total: 0, stuck: 0, late: 0};
  const supBody = (sup.items || []).map(t => `<div class="myd-row sup-row" data-dltask="${t.id}">
      <span class="dot" style="background:${esc(t.pcolor)};flex:none"></span>
      <span class="myd-t"><b>${esc(t.title)}</b><span class="mut"> · ${esc(t.pname || 'Χωρίς έργο')}</span></span>
      <span class="sup-w" title="Το έχει ο/η ${esc(t.whoName)}">${esc(adminIni(t.who))}</span>
      ${t.stuck ? `<span class="pill pill-bad" style="flex:none" title="Καμία κίνηση εδώ και ${t.idle} ημέρες">⏸ ${t.idle} ημ.</span>`
        : t.late ? '<span class="pill pill-warn" style="flex:none">εκπρόθεσμη</span>'
        : `<span class="pill pill-mut" style="flex:none" title="Τελευταία κίνηση">${t.idle === 0 ? 'σήμερα' : t.idle + ' ημ.'}</span>`}
    </div>`).join('')
    + (sup.total > (sup.items || []).length ? `<a class="myd-morelink" data-go="supervised">+ ${sup.total - sup.items.length} ακόμη — δες τα όλα →</a>` : '')
    || '<div class="myd-empty">Δεν έχεις αναθέσει τίποτα ανοιχτό σε άλλον.</div>';
  const supHint = sup.total ? (sup.stuck ? sup.stuck + ' κόλλησαν' : 'όλα κινούνται') + (sup.late ? ' · ' + sup.late + ' εκπρόθεσμα' : '') : '';

  const ovBody = overruns.length ? overruns.map(o => `<div class="myd-row ov-row" data-ovwhat="${o.what}" data-ovid="${o.id}">
      <span class="pill ${o.worst >= 100 ? 'pill-bad' : 'pill-warn'}" style="flex:none;font-weight:700">+${esc(String(Math.round(o.worst)))}%</span>
      <span class="myd-t qt"><b>${o.what === 'project' ? '📁 ' : ''}${esc(o.title)}</b><span class="mut"> · ${esc(o.agentName)}${o.hoursText ? ' · ⏱ ' + esc(o.hoursText) : ''}${o.daysText ? ' · 📅 ' + esc(o.daysText) : ''}</span></span>
      ${o.checkin ? (o.checkin.status === 'done' ? `<span class="pill ${o.checkin.answer === 'help' ? 'pill-bad' : 'pill-ok'}" style="flex:none" title="${esc(o.checkin.answerNote || '')}">${o.checkin.answer === 'help' ? '🆘 θέλει βοήθεια' : '✅ όλα καλά'}</span>` : '<span class="pill pill-info" style="flex:none">💬 ρωτήθηκε</span>') : `<button class="btn btn-sm btn-o" data-ovask style="flex:none">💬 Ρώτα</button>`}
    </div>`).join('') : '';

  /* Κάθε μπλοκ χτίζεται μία φορά και μπαίνει ΜΕ ΤΗ ΣΕΙΡΑ που το θέλει ο χρήστης.
     Ό,τι έχει σβήσει δεν υπολογίζεται καν στη σελίδα. */
  const BLK = {
    day: () => dayBar,
    team: () => tmbBar,
    att: () => sec('att', 'Θέλουν εσένα', 'από το πιο επείγον', att.length, attBody, {ic: I.alert, cls: 'att' + (att.length ? '' : ' ok')}),
    plan: () => sec('plan', 'Το πρόγραμμά μου σήμερα', '▶ χρόνος · ✔ ολοκλήρωση', planN, planBody, {ic: I.sun, link: ['calendar', 'ημερολόγιο →']}),
    queue: () => sec('queue', 'Ουρά tickets', 'πρώτα SLA, μετά ο παλαιότερος', queue.length, queueBody, {ic: I.compass, collapsed: !queue.some(q => q.lvl === 'bad' || q.lvl === 'warn'), link: ['inbox', 'όλα →']}),
    coach: () => (coach.length ? sec('coach', 'Καθοδήγηση', '', null, coachBody, {ic: I.compass}) : ''),
    dl: () => (dlAhead.length ? sec('dl', 'Προθεσμίες μπροστά', '', dlAhead.length, dlBody, {ic: I.clock, collapsed: !dlAhead.some(x => (x.days !== null && x.days <= 1) || (x.hours !== null && x.hours <= 12))}) : ''),
    sup: () => (sup.total ? sec('sup', 'Επιβλέπω', supHint, sup.total, supBody,
      {ic: I.eye, collapsed: !sup.stuck && !sup.late, link: ['supervised', 'όλα →']}) : ''),
    wait: () => (waitN ? sec('wait', 'Περιμένω άλλους', 'όχι δική σου εκκρεμότητα', waitN, waitBody, {ic: I.clock, collapsed: true}) : ''),
    ov: () => (overruns.length ? sec('ov', 'Υπερβάσεις ομάδας', 'πάνω από την εκτίμηση ' + esc(String(ovr.pct || 10)) + '%', overruns.length, ovBody, {ic: I.alert, collapsed: true}) : ''),
    /* ΑΚΥΡΩΣΕΙΣ. Το περιεχόμενο έρχεται μετά (δες mydCancels): δεν κρατάμε τη
       μέρα πίσω για ένα ερώτημα που αφορά λίγους. */
    cancels: () => sec('cancels', 'Ακυρώσεις υπηρεσιών', 'τι ζητήθηκε και αν έκλεισε', null,
      '<div id="mydCn" class="mut" style="font-size:12.5px">φόρτωση…</div>', {ic: I.alert}),
  };
  const put = col => mydCol(col).map(k => (BLK[k] ? BLK[k]() : '')).join('');

  c.innerHTML = `<div class="myd-wrap">${hero}${put('bar')}
  <div class="myd-cols">
    <div class="myd-main">${put('main')}</div>
    <div class="myd-rail">${put('rail')}</div>
  </div></div>`;

  /* ── δέσιμο ── */
  $$('#content .myd-h').forEach(h => h.onclick = e => { if (e.target.closest('a,button')) { return; } const s = h.closest('.myd-sec'); s.classList.toggle('closed'); setOpen(h.dataset.sect, !s.classList.contains('closed')); });
  $$('#content [data-go]').forEach(a => a.onclick = e => { e.stopPropagation(); go(a.dataset.go); });
  $$('#content [data-attgo]').forEach(b => b.onclick = e => { e.stopPropagation(); att[+b.dataset.attgo].on(); });
  $$('#content [data-attalt]').forEach(b => b.onclick = e => { e.stopPropagation(); att[+b.dataset.attalt].altOn(); });
  $$('#content [data-attdone]').forEach(b => b.onclick = async e => { e.stopPropagation(); await att[+b.dataset.attdone].done(); toast('Τακτοποιήθηκε'); vMyDay(); });
  $$('#content [data-attdone-m]').forEach(b => b.onclick = async e => { e.stopPropagation(); await api('help_done', {id: +b.dataset.attdoneM}).catch(() => {}); vMyDay(); });
  $$('#content .myd-row.att').forEach(r => r.onclick = e => { if (e.target.closest('button,a')) { return; } att[+r.dataset.atti].on(); });
  { const t = $('#content [data-attmore-t]'); if (t) { t.onclick = () => { $('#content [data-attmore]').classList.add('show'); t.remove(); }; } }
  $$('#content [data-mdtask]').forEach(r => r.onclick = e => { if (e.target.closest('button,a,input')) { return; } openTask(+r.dataset.mdtask); });
  $$('#content [data-cal]').forEach(r => r.onclick = e => { if (e.target.closest('button,a')) { return; } go('calendar'); });
  $$('#content [data-evleft]').forEach(b => b.onclick = async e => {
    e.stopPropagation();
    const r = await api('event_outcome', {id: +b.dataset.evleft, what: 'done'}).catch(er => ({err: er && er.message}));
    if (r && r.err) { toast(r.err, true); return; }
    toast('Βγήκες από τη σύσκεψη — η κατάστασή σου ελευθερώθηκε');
    vMyDay();
  });
  cnpWireDash($('#content'));
  cnpGreet(d.greet);          // μία φορά την ημέρα — ο server αποφασίζει πότε
  mydCancels();
  /* Το κουτί των κλήσεων είναι το ΙΔΙΟ με της οθόνης «Καταγραφές κλήσεων» — μία
     υλοποίηση, δύο θέσεις. Μετά τον χαρακτηρισμό ξαναζωγραφίζεται η μέρα. */
  if ($('#mydCalls') && window.CNP.clPending) {
    window.CNP.clPending('mydCalls', vMyDay).then(() => {
      /* Η ετικέτα εμφανίζεται μόνο αν όντως υπάρχουν κλήσεις — αλλιώς η μέρα θα
         έγραφε «Τηλέφωνα χωρίς καταγραφή» πάνω από ένα κενό. */
      const g = $('#mydCallsG'), bx = $('#mydCalls');
      if (g && bx) { g.hidden = !bx.querySelector('.cl-p'); if (g.hidden) { bx.innerHTML = ''; } }
    });
  }

  /* ⌨ Ο δρομέας πάνω στις γραμμές που ΚΑΝΟΥΝ κάτι. Η θέση επιβιώνει του redraw:
     κάθε ενέργεια ξαναζωγραφίζει την οθόνη και χωρίς αυτό θα ξεκινούσες από την αρχή. */
  /* ▶ Επόμενο: η οθόνη ξέρει ήδη τη σειρά — «Θέλουν εσένα» από το πιο επείγον, μετά η
     σύσκεψη που τρέχει, μετά το πρόγραμμα, μετά η ουρά. Το κουμπί απλώς την ακολουθεί
     και ΕΚΤΕΛΕΙ την πρώτη γραμμή που δεν έχει τακτοποιηθεί, ώστε να μη διαλέγεις εσύ.
     Κρατά όσα πέρασες σε αυτή τη συνεδρία, για να μη σε γυρνάει στο ίδιο πράγμα. */
  const nextSkip = vMyDay._skip = vMyDay._skip || new Set();
  const nextPick = () => {
    const nowRow = $('#content .myd-row.ev.now');
    if (nowRow && !nextSkip.has('ev' + nowRow.dataset.cal)) { return nowRow; }
    const order = ['#content .myd-row.att', '#content .myd-row.q', '#content .myd-row.task', '#content .myd-row.ev'];
    for (const sel of order) {
      const hit = $$(sel).find(el => el.offsetParent !== null && !nextSkip.has(nextKey(el)));
      if (hit) { return hit; }
    }
    return null;
  };
  const nextKey = el => (el.dataset.attk ? 'a' + el.dataset.attk
    : el.dataset.qtk ? 'q' + el.dataset.qtk
    : el.dataset.mdtask ? 't' + el.dataset.mdtask
    : el.dataset.cal ? 'ev' + el.dataset.cal : 'x' + Math.random());
  const goNext = () => {
    const el = nextPick();
    if (!el) {
      toast('✨ Τίποτα άλλο δεν σε περιμένει — καθαρή μέρα');
      return;
    }
    nextSkip.add(nextKey(el));
    el.classList.add('kb-cur');
    el.scrollIntoView({block: 'center', behavior: 'smooth'});
    const b = el.querySelector('[data-attgo]');
    setTimeout(() => (b || el).click(), 180);
  };
  { const lb = $('#mydLay'); if (lb) { lb.onclick = () => mydLayoutDialog(() => vMyDay()); } }
  { const nb = $('#mydNext'); if (nb) { nb.onclick = goNext; } }
  { const kb = $('#mydKbd'); if (kb) { kb.onclick = () => cnpKeyHelp([['t', 'ξεκίνα / σταμάτα χρόνο'],
      ['e', 'ολοκλήρωσε ή τακτοποίησε'], ['r', 'απάντησε (ticket / αίτημα)']]); } }
  const kbKeep = vMyDay._kb;
  cnpKeyNav({
    sel: '#content .myd-row.att, #content .myd-row.ev, #content .myd-row.task, #content .myd-row.q',
    start: typeof kbKeep === 'number' ? kbKeep : -1,
    onMove: n => { vMyDay._kb = n; },
    help: [['t', 'ξεκίνα / σταμάτα χρόνο'], ['e', 'ολοκλήρωσε ή τακτοποίησε'],
      ['r', 'απάντησε (ticket / αίτημα)'], ['n', 'το επόμενο που σε περιμένει']],
    keys: {
      Enter: el => { const b = el.querySelector('[data-attgo]'); (b || el).click(); },
      r: el => { const b = el.querySelector('[data-attgo]'); (b || el).click(); },
      t: el => { const b = el.querySelector('[data-mdplay]'); if (b) { b.click(); } else { toast('Χρονόμετρο μόνο σε εργασία', true); } },
      e: el => { const b = el.querySelector('[data-mddone], [data-attdone]'); if (b) { b.click(); } else { toast('Δεν ολοκληρώνεται από εδώ', true); } },
    },
    globalKeys: {n: () => goNext()},
  });
  $$('#content [data-lead]').forEach(r => r.onclick = async () => { const dd = await api('crm').catch(() => null); if (dd) { const ld = (dd.leads || []).find(x => x.id === +r.dataset.lead); openLead(ld || null, dd); } });
  $$('#content [data-mdplay]').forEach(b => b.onclick = async e => {
    e.stopPropagation(); const id = +b.dataset.mdplay;
    if (timer && timer.task === id) { const r = await api('timer_stop', {billable: false, note: ''}).catch(() => null); if (r) { toast('Καταχωρήθηκε ' + fmtMin(r.mins)); } }
    else { await api('timer_start', {task: id}).catch(err => toast(err.message, true)); toast('▶ Ο χρόνος μετράει'); }
    vMyDay();
  });
  $$('#content [data-mddone]').forEach(b => b.onclick = async e => {
    e.stopPropagation(); const id = +b.dataset.mddone; const t = planTasks.find(x => x.id === id) || {};
    const note = await askDone(t.title || ''); if (note === null) { return; }
    const r = await cnpMoveTask(id, fin.id, note); if (!r.ok) { if (!r.cancelled) { toast(r.error || 'Δεν επιτρέπεται', true); } return; }
    toast('✔ Ολοκληρώθηκε'); vMyDay();
  });
  { const sb = $('#mdStop'); if (sb) { sb.onclick = async () => { const r = await api('timer_stop', {billable: false, note: ''}).catch(() => null); if (r) { toast('Καταχωρήθηκε ' + fmtMin(r.mins)); } vMyDay(); }; } }
  if (timer) { const el = $('#mdElapsed'); const iv = setInterval(() => { if (!document.body.contains(el)) { clearInterval(iv); return; } el.textContent = elapsed(timer.since); }, 30000); }
  $$('#content [data-tmid]').forEach(r => r.onclick = () => go('activity'));
  $$('#content [data-mdtog]').forEach(ch => ch.onclick = async () => { await api('todo_toggle', {id: +ch.dataset.mdtog}); vMyDay(); });
  $$('#content .ov-row').forEach(r => {
    const what = r.dataset.ovwhat, oid = +r.dataset.ovid;
    r.onclick = e => { if (e.target.closest('[data-ovask]')) { return; } if (what === 'task') { openTask(oid); } else { go('board', oid); } };
    const b = r.querySelector('[data-ovask]');
    if (b) b.onclick = async e => {
      e.stopPropagation();
      const o = overruns.find(x => x.what === what && x.id === oid) || {};
      const msg = await cnpDialog({title: '💬 Ρώτα τον ' + (o.agentName || ''), body: 'Θα του φτάσει ως ειδοποίηση με «Όλα καλά» / «Χρειάζομαι βοήθεια». Η απάντηση γυρίζει σε σένα.',
        input: `Βλέπω ότι «${(o.title || '').slice(0, 80)}» ξεπέρασε την εκτίμηση. Τι γίνεται; Χρειάζεσαι βοήθεια;`, rows: 4, max: 2000, ok: '💬 Στείλε', cancel: 'Άκυρο'});
      if (msg === null) { return; }
      const x = await api('overrun_checkin', {what, id: oid, message: msg}).catch(err => ({err: err && err.message}));
      if (x && x.err) { toast(x.err, true); return; }
      toast('💬 Ρωτήθηκε ο ' + (x.to || o.agentName)); vMyDay();
    };
  });
  $$('#content [data-qtk]').forEach(r => r.onclick = e => { e.stopPropagation(); openTicketQuick(+r.dataset.qtk, vMyDay); });
  $$('#content [data-dltask]').forEach(r => r.onclick = e => { e.stopPropagation(); openTask(+r.dataset.dltask); });
  $$('#content [data-dlproj]').forEach(r => r.onclick = () => go('board', +r.dataset.dlproj));
  $$('#content [data-dloffer]').forEach(r => r.onclick = () => go('offers'));
}

/* ═════════ CRM ═════════ */
/* Τα δύο προαιρετικά φίλτρα του funnel. Ήταν δύο μόνιμα select δίπλα στον
   στόχο πωλήσεων — δηλαδή δύο άδεια κουτιά που έπιαναν χώρο σε κάθε φόρτωση
   ακόμη κι όταν κανείς δεν φιλτράρει. */
const CRM_F = {
  fa:  {label: 'Χειριστής', opts: () => [['', '— κάθε —']]
          .concat(S.boot.admins.map(a => [String(a.id), a.name]))},
  src: {label: 'Πηγή', opts: d => [['', '— κάθε —']]
          .concat((d.__sources || []).map(x => [x, x]))},
};

async function vCrm() {
  setTop('CRM', 'Ποιες ευκαιρίες τρέχουν, σε ποιο στάδιο, ποιος τις κρατά');
  const c = $('#content');
  const f = vCrm._f = vCrm._f || {fa: '', src: '', q: '', stage: '', closed: {}, shown: []};
  if (!f.shown) { f.shown = []; }
  ['fa', 'src'].forEach(k => { if (f[k] && !f.shown.includes(k)) { f.shown.push(k); } });
  cnpSkel(c, crmTabs('crm') + '<div class="kb">' + '<div class="skel" style="flex:1;min-height:280px"></div>'.repeat(5) + '</div>');
  const d = await api('crm');
  const pct = d.target > 0 ? Math.min(100, Math.round(d.won / d.target * 100)) : 0;
  const flt = l => (!f.fa || String(l.assignee || '') === f.fa)
    && (!f.src || (l.source || '').toLowerCase().includes(f.src.toLowerCase()))
    && (!f.q || ((l.company || '') + ' ' + (l.contact || '') + ' ' + (l.email || '') + ' ' + (l.phone || '')).toLowerCase().includes(f.q.toLowerCase()));
  const leads = d.leads.filter(flt);
  const sources = [...new Set(d.leads.map(l => l.source).filter(Boolean))];
  d.__sources = sources;
  const MOB = matchMedia('(max-width:768px)').matches;
  const leadChips = (l, sg) => `
    ${l.contact && l.company ? `<span>${I.user} ${esc(l.contact)}</span>` : ''}
    ${l.phone ? `<span>${I.phone} ${esc(l.phone)}</span>` : ''}
    ${l.value ? `<span class="pill pill-ok" style="font-weight:700">${fmtEur(l.value)}</span>` : ''}
    ${l.source ? `<span class="pill pill-mut">${esc(l.source)}</span>` : ''}
    ${l.next && !sg.closed ? `<span class="${l.next <= today() ? 'pill pill-bad' : ''}">${I.bell} ${dShort(l.next)}</span>` : ''}
    ${!l.next && !sg.closed ? `<span class="pill pill-warn" title="Χωρίς επόμενη ενέργεια">${I.snow} </span>` : ''}
    ${l.client ? '<span class="pill pill-ok">✓ πελάτης</span>' : ''}
    ${sg.key === 'lost' && l.lostReason ? `<span class="pill pill-bad" title="${esc(l.lostReason)}">${I.chat} </span>` : ''}`;

  /* Κινητό: λίστα ανά στάδιο (το kanban ήθελε ατέλειωτο swipe σε 6 στήλες των 84vw).
     Αλλαγή σταδίου γίνεται από το drawer του lead. Desktop: το kanban ως έχει. */
  const funnelMob = () => (leads.length ? '' : `<div class="card"><div class="empty" style="padding:44px 20px">
      <div class="big">${I.target}</div>
      <b style="color:var(--ink);font-size:15px">${d.leads.length ? 'Κανένα lead με αυτά τα φίλτρα' : 'Κανένα lead ακόμη'}</b>
      <div class="mut" style="font-size:12.5px;margin-top:6px">${d.leads.length
        ? 'Καθάρισε την αναζήτηση ή τα φίλτρα.' : 'Ξεκίνα καταχωρώντας τον πρώτο υποψήφιο πελάτη.'}</div>
      <button class="btn btn-p" id="newLead2" style="margin-top:14px">${I.plus} Νέο lead</button></div></div>`)
    + d.stages.map(sg => {
    const sl = leads.filter(l => l.stage === sg.key);
    const val = sl.reduce((t, l) => t + (l.value || 0), 0);
    if (f.stage !== '' && f.stage !== sg.key) { return ''; }
    if (!sl.length && f.stage === '') { return ''; }   // άδεια στάδια κρύβονται (φαίνονται στα chips με 0)
    return `<div class="card kb-group">
      <div class="card-h kb-ghead" data-cstage="${sg.key}">
        <span class="kb-gbar" style="background:${sg.color}"></span>${esc(sg.title)}
        <span class="kb-n">${sl.length}</span>
        ${val ? `<span class="mut" style="font-size:11px;margin-left:6px">${fmtEur(val)}</span>` : ''}
        <span style="flex:1"></span>
        <span class="kb-gchev ${f.closed[sg.key] ? '' : 'open'}">${I.chev}</span></div>
      <div class="card-b kb-gbody" ${f.closed[sg.key] ? 'style="display:none"' : ''}>
        ${sl.length ? sl.map(l => `<div class="kb-trow lrow ${l.next && l.next <= today() && !sg.closed ? 'overdue' : ''}" data-lead="${l.id}">
            <span class="kb-dot" style="background:${sg.color}"></span>
            <b>${esc(l.company || l.contact || '—')}</b>
            <span class="kb-sum-meta">${leadChips(l, sg)}</span></div>`).join('')
          : '<div class="mut" style="font-size:12.5px;padding:4px 2px">Κανένα lead σε αυτό το στάδιο.</div>'}
      </div></div>`;
  }).join('');

  const funnelDesk = () => `<div class="kb" id="crmKb" style="min-height:calc(100vh - 340px)">
    ${d.stages.map(sg => {
      const sl = leads.filter(l => l.stage === sg.key);
      const val = sl.reduce((t, l) => t + (l.value || 0), 0);
      return `<div class="kb-col lcol" data-stage="${sg.key}">
        <div class="kb-h" style="border-color:${sg.color}">${esc(sg.title)}<span class="kb-n">${sl.length}</span>
          ${val ? `<span class="mut" style="margin-left:auto;font-size:10.5px">${fmtEur(val)}</span>` : ''}</div>
        <div class="kb-cards">${sl.map(l => `
          <div class="tcard lcard ${l.next && l.next <= today() && !sg.closed ? 'overdue' : ''}" data-lead="${l.id}">
            <div class="tcard-t">${esc(l.company || l.contact || '—')}</div>
            <div class="tcard-m">${leadChips(l, sg)}</div></div>`).join('')}</div>
      </div>`;
    }).join('')}
  </div>`;

  c.innerHTML = crmTabs('crm') + `
  <div class="fbar">
    ${fChip('Αναζήτηση', `<input class="fchip-s" id="cfQ" value="${esc(f.q)}"
      placeholder="εταιρεία, επαφή, email, τηλέφωνο…" style="width:250px">`, !!f.q, '')}
    ${f.shown.map(k => fChip(CRM_F[k].label, fSel(k, CRM_F[k].opts(d), f[k]), !!f[k], k)).join('')}
    ${fAdd(CRM_F, f.shown)}
    <span class="fbar-sp"></span>
    <span class="crm-goal">${I.target} <b>${fmtEur(d.won)}</b>${d.target > 0 ? `<span class="mut"> / ${fmtEur(d.target)} μήνα</span>` : '<span class="mut"> πωλήσεις μήνα</span>'}
      ${d.target > 0 ? `<span class="crm-bar"><span class="${pct >= 100 ? 'ok' : ''}" style="width:${pct}%"></span></span>` : ''}</span>
    <button class="fchip fchip-go" id="newLead">${I.plus} Νέο lead</button>
  </div>
  ${MOB ? `<div class="fchips">
    <button class="kb-chip${f.stage === '' ? ' on' : ''}" data-cfstage="">Όλα <b>${leads.length}</b></button>
    ${d.stages.map(sg => { const n = leads.filter(l => l.stage === sg.key).length;
      return `<button class="kb-chip${f.stage === sg.key ? ' on' : ''}" data-cfstage="${sg.key}" style="--kc:${sg.color}">
        <span class="kb-dot" style="background:${sg.color}"></span>${esc(sg.title)} <b>${n}</b></button>`; }).join('')}
  </div>` : ''}
  ${MOB ? funnelMob() : funnelDesk()}`;
  fWire(f, CRM_F, () => vCrm());
  cnpSearch('cfQ', v => { f.q = v; vCrm(); }, 320);
  $('#newLead').onclick = () => openLead(null, d);
  const nl2 = $('#newLead2'); if (nl2) { nl2.onclick = () => openLead(null, d); }
  $$('[data-cfstage]').forEach(b => b.onclick = () => { f.stage = b.dataset.cfstage; vCrm(); });
  $$('.kb-ghead[data-cstage]').forEach(h => h.onclick = () => {
    const k = h.dataset.cstage; f.closed[k] = !f.closed[k];
    h.nextElementSibling.style.display = f.closed[k] ? 'none' : '';
    h.querySelector('.kb-gchev').classList.toggle('open', !f.closed[k]);
  });
  $$('.lrow[data-lead]').forEach(r => r.onclick = () => openLead(d.leads.find(x => x.id === +r.dataset.lead), d));
  if (!MOB) { dndLead(d); }
}
let leadDndBound = false;
function dndLead(data) {
  if (!leadDndBound) {
    leadDndBound = true;
    dnd('.lcard', '.lcol', async (card, col) => {
      let reason = '';
      if (col.dataset.stage === 'lost') {
        reason = (await cnpPrompt('Γιατί χάθηκε η πώληση;', {title: I.chat + ' Αιτία απώλειας', placeholder: 'προαιρετικό — βοηθά στη στατιστική', ok: 'Καταχώρηση', cancel: 'Χωρίς αιτία'})) || '';
      }
      const r = await api('move_lead', {lead: +card.dataset.lead, stage: col.dataset.stage, reason}).catch(e => ({ok: false, error: e && e.message}));
      if (r.ok) { col.querySelector('.kb-cards').appendChild(card);
        $$('.lcol').forEach(c => c.querySelector('.kb-n').textContent = c.querySelectorAll('.tcard').length);
      } else toast('Δεν επιτρέπεται', true);
    }, async el => { const d = await api('crm'); openLead(d.leads.find(l => l.id === +el.dataset.lead), d); });
  }
}
function openLead(l, d) {
  closeDrawer();
  const isNew = !l; l = l || {stage: 'target'};
  const ovl = document.createElement('div'); ovl.className = 'ovl';   // κλικ έξω ΔΕΝ κλείνει
  const dr = document.createElement('div'); dr.className = 'drawer';
  dr.innerHTML = `
  <div class="drawer-h"><h2>${isNew ? 'Νέος στόχος / lead' : esc(l.company || l.contact)}</h2>
    <button class="drawer-x" id="dX">✕</button></div>
  <div class="drawer-b">
    <div class="card"><div class="card-b">
      <div class="frow">
        <div><label class="lbl">Επωνυμία</label><input class="inp" id="lCompany" value="${esc(l.company || '')}"></div>
        <div><label class="lbl">Πρόσωπο</label><input class="inp" id="lContact" value="${esc(l.contact || '')}"></div>
        <div><label class="lbl">Email</label><input class="inp" id="lEmail" value="${esc(l.email || '')}"></div>
        <div><label class="lbl">Τηλέφωνο</label><input class="inp" id="lPhone" value="${esc(l.phone || '')}"></div>
        <div><label class="lbl">Πηγή</label><input class="inp" id="lSource" value="${esc(l.source || '')}" list="srcL">
          <datalist id="srcL"><option>Σύσταση</option><option>Site</option><option>Κλήση</option><option>LinkedIn</option><option>Έκθεση</option></datalist></div>
        <div><label class="lbl">Στάδιο</label><select class="inp" id="lStage">
          ${d.stages.map(s => `<option value="${s.key}" ${s.key === l.stage ? 'selected' : ''}>${esc(s.title)}</option>`).join('')}</select></div>
        <div><label class="lbl">Χειριστής</label><select class="inp" id="lAssignee"><option value="">—</option>
          ${S.boot.admins.map(a => `<option value="${a.id}" ${a.id === +l.assignee ? 'selected' : ''}>${esc(a.name)}</option>`).join('')}</select></div>
        <div><label class="lbl">${I.bell} Επόμενη ενέργεια</label><input type="date" class="inp" id="lNext" value="${l.next || ''}"></div>
        <div><label class="lbl">${I.coin} Αξία deal €</label><input class="inp" id="lValue" value="${l.value ?? ''}" placeholder="π.χ. 1500"></div>
        <div><label class="lbl">${I.chat} Αιτία απώλειας</label><input class="inp" id="lLost" value="${esc(l.lostReason || '')}" placeholder="μόνο για Χαμένα"></div>
      </div>
      <label class="lbl">Τι θα γίνει</label><input class="inp" id="lNextNote" value="${esc(l.nextNote || '')}" placeholder="π.χ. τηλέφωνο για demo">
      <label class="lbl" style="margin-top:11px">Σημειώσεις</label>
      ${rteHtml('lDescr', l.descr || '', 'Σημειώσεις για το lead…', {min: 130})}
      <div style="margin-top:13px"><button class="btn btn-p" id="lSave">Αποθήκευση</button></div>
    </div></div>
    ${!isNew ? `<div class="card" id="lScoreCard"><div class="card-b" id="lScoreBox"><div class="mut" style="font-size:12px">Υπολογισμός βαθμολογίας…</div></div></div>
    <div class="card"><div class="card-h">${I.box} Προϊόντα deal <span class="mut" style="font-weight:400;font-size:11px;margin-left:auto">η αξία ενημερώνεται αυτόματα</span></div>
      <div class="card-b" id="lProdBox"><div class="mut" style="font-size:12px">Φόρτωση…</div></div></div>
    <div class="card"><div class="card-h">${I.puzzle} Επιπλέον στοιχεία</div><div class="card-b" id="lFieldsBox">
      <div class="mut" style="font-size:12px">Φόρτωση…</div></div></div>
    <div class="card"><div class="card-h">${I.users} Πρόσωπα επαφής</div><div class="card-b" id="lPeopleBox">
      <div class="mut" style="font-size:12px">Φόρτωση…</div></div></div>
    <div class="card"><div class="card-h">${I.checkSquare} Εργασίες / Δραστηριότητες</div><div class="card-b" id="lTasksBox">
      <div class="mut" style="font-size:12px">Φόρτωση…</div></div></div>
    <div class="card"><div class="card-h">${I.phone} Γρήγορη καταγραφή επικοινωνίας</div><div class="card-b">
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <select class="inp" id="iKind" style="width:140px">
          <option value="call">Τηλεφώνημα</option><option value="email">Email</option>
          <option value="meeting">Συνάντηση</option><option value="note">Σημείωση</option></select>
        <input class="inp" id="iSum" placeholder="τι ειπώθηκε…" style="flex:1;min-width:160px">
        <input type="date" class="inp" id="iFup" style="width:150px" title="follow-up">
        <button class="btn btn-o" id="iSave">Καταγραφή</button>
      </div></div></div>
    <div class="card"><div class="card-h">${I.clock} Ιστορικό (timeline)</div><div class="card-b" id="lTimeBox">
      <div class="mut" style="font-size:12px">Φόρτωση…</div></div></div>` : ''}
  </div>`;
  document.body.append(ovl, dr);
  requestAnimationFrame(() => { ovl.classList.add('show'); dr.classList.add('show'); });
  $('#dX').onclick = () => cnpAskClose(dr);
  if (!isNew) {
    loadLeadExtras(l.id, dr);
  }
  $('#lSave', dr).onclick = async () => {
    await api('save_lead', {lead: l.id || 0, company: $('#lCompany').value, contact: $('#lContact').value,
      email: $('#lEmail').value, phone: $('#lPhone').value, source: $('#lSource').value,
      stage: $('#lStage').value, assignee: +$('#lAssignee').value || 0,
      value: $('#lValue').value.trim(), lostReason: $('#lLost').value,
      next: $('#lNext').value || null, nextNote: $('#lNextNote').value, descr: rteVal('lDescr')});
    toast('Αποθηκεύτηκε'); closeDrawer(); vCrm();
  };
  const iS = $('#iSave', dr); if (iS) iS.onclick = async () => {
    if (!$('#iSum').value.trim()) return;
    await api('interaction', {lead: l.id, kind: $('#iKind').value, summary: $('#iSum').value.trim(),
      followup: $('#iFup').value || null});
    toast('Καταγράφηκε'); closeDrawer(); vCrm();
  };
}

/* ── Κ5: custom πεδία + πρόσωπα στο lead drawer ── */
async function loadLeadExtras(leadId, dr) {
  // πεδία CRM
  const lf = await api('lead_fields&lead=' + leadId);
  const fb = $('#lFieldsBox', dr);
  if (fb) {
    fb.innerHTML = lf.fields.length ? lf.fields.map(f => `
      <div style="display:flex;gap:9px;align-items:center;padding:4px 0">
        <label class="lbl" style="margin:0;width:130px">${esc(f.label)}</label>
        ${f.type === 'select'
          ? `<select class="inp" data-lf="${f.id}" style="flex:1"><option value="">—</option>
             ${f.options.map(o => `<option ${lf.values[f.id] === o ? 'selected' : ''}>${esc(o)}</option>`).join('')}</select>`
          : `<input class="inp" type="${f.type === 'date' ? 'date' : 'text'}" data-lf="${f.id}"
             value="${esc(lf.values[f.id] || '')}" style="flex:1">`}
      </div>`).join('')
      : '<div class="mut" style="font-size:12px">Δεν έχουν οριστεί πεδία CRM — πρόσθεσε στις Ρυθμίσεις.</div>';
    $$('[data-lf]', fb).forEach(el => el.onchange = async () => {
      await api('lead_value_save', {lead: leadId, field: +el.dataset.lf, value: el.value});
      toast('Αποθηκεύτηκε');
    });
  }
  // lead score (θερμότητα + ανάλυση παραγόντων)
  const renderScore = async () => {
    const box = $('#lScoreBox', dr); if (!box) return;
    const s = await api('lead_score&lead=' + leadId).catch(() => null);
    if (!s) { box.innerHTML = ''; return; }
    const tmap = {hot: ['Θερμό', '#e0552b', I.fire], warm: ['Χλιαρό', '#e0a020', I.snow], cold: ['Ψυχρό', '#0097e4', I.snow]};
    const [tl, tc, ti] = tmap[s.temp] || tmap.cold;
    box.innerHTML = `<div style="display:flex;align-items:center;gap:14px">
      <div style="position:relative;width:64px;height:64px;flex:none">
        <svg width="64" height="64" viewBox="0 0 64 64"><circle cx="32" cy="32" r="27" fill="none" stroke="var(--line)" stroke-width="7"/>
          <circle cx="32" cy="32" r="27" fill="none" stroke="${tc}" stroke-width="7" stroke-linecap="round" stroke-dasharray="${2 * Math.PI * 27}" stroke-dashoffset="${2 * Math.PI * 27 * (1 - s.score / 100)}" transform="rotate(-90 32 32)"/></svg>
        <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:17px;color:var(--ink)">${s.score}</div></div>
      <div style="flex:1">
        <div style="display:flex;align-items:center;gap:6px;font-weight:800;color:${tc};font-size:15px">${ti} ${tl} lead</div>
        <div class="mut" style="font-size:11.5px;margin-top:2px">Βαθμολογία 0–100 βάσει σταδίου, επαφών & δραστηριότητας</div>
      </div>
      <button class="btn btn-o btn-sm" id="lScoreToggle">Ανάλυση</button></div>
      <div id="lScoreFactors" style="display:none;margin-top:11px;border-top:1px solid var(--line);padding-top:10px">
        ${s.factors.map(f => `<div style="display:flex;align-items:center;gap:8px;font-size:12px;padding:3px 0;opacity:${f.on ? 1 : 0.4}">
          <span style="width:16px;text-align:center;color:${f.on ? 'var(--ok)' : 'var(--mut)'}">${f.on ? '✓' : '○'}</span>
          <span style="flex:1">${esc(f.label)}</span>
          <b style="color:${f.on ? 'var(--ink)' : 'var(--mut)'}">${f.on ? '+' + f.pts : '0'}</b></div>`).join('')}
      </div>`;
    const tg = $('#lScoreToggle', box); if (tg) tg.onclick = () => {
      const fx = $('#lScoreFactors', box); fx.style.display = fx.style.display === 'none' ? 'block' : 'none';
      tg.textContent = fx.style.display === 'none' ? 'Ανάλυση' : 'Απόκρυψη';
    };
  };
  renderScore();
  // προϊόντα deal (auto-sum → αξία)
  const setDealVal = t => { const v = $('#lValue', dr); if (v) v.value = t; };
  const renderProducts = async () => {
    const pr = await api('lead_products&lead=' + leadId);
    const box = $('#lProdBox', dr); if (!box) return;
    box.innerHTML = (pr.items.length ? pr.items.map(it => `
      <div style="display:flex;gap:8px;align-items:center;padding:5px 0;border-bottom:1px dashed var(--line)">
        <div style="flex:1;min-width:0"><b style="font-size:12.5px">${esc(it.name)}</b></div>
        <span class="mut" style="font-size:11.5px;white-space:nowrap">${it.qty} × ${fmtEur(it.price)}</span>
        <b style="width:82px;text-align:right">${fmtEur(it.total)}</b>
        <button class="btn btn-sm btn-o" data-lpdel="${it.id}" style="color:var(--bad)">✕</button></div>`).join('') : '') + `
      <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:9px">
        <input class="inp" id="lpN" list="lpCat" placeholder="προϊόν / υπηρεσία" style="flex:1;min-width:140px">
        <datalist id="lpCat">${pr.catalog.map(p => `<option data-pid="${p.id}">${esc(p.name)}</option>`).join('')}</datalist>
        <input class="inp" type="number" id="lpQ" value="1" step="0.5" style="width:66px" title="ποσότητα">
        <input class="inp" type="number" id="lpP" placeholder="€/τεμ" step="0.01" style="width:96px">
        <button class="btn btn-p btn-sm" id="lpAdd">+</button></div>
      ${pr.items.length ? `<div style="display:flex;justify-content:flex-end;align-items:center;gap:10px;margin-top:10px;padding-top:9px;border-top:2px solid var(--line)">
        <span class="mut" style="font-size:12px">Σύνολο deal:</span><b style="color:var(--ok);font-size:16px">${fmtEur(pr.total)}</b></div>` : ''}`;
    $('#lpAdd', box).onclick = async () => {
      const n = $('#lpN', box).value.trim(); if (!n) return;
      const opt = [...$('#lpCat', box).options].find(o => o.value === n);
      const r = await api('lead_product_save', {lead: leadId, name: n, product_id: opt ? +opt.dataset.pid : 0,
        qty: +$('#lpQ', box).value || 1, price: +$('#lpP', box).value || 0});
      setDealVal(r.total); toast('Προστέθηκε'); renderProducts();
    };
    $$('[data-lpdel]', box).forEach(b => b.onclick = async () => {
      const r = await api('lead_product_del', {id: +b.dataset.lpdel});
      await renderProducts();
      const pr2 = await api('lead_products&lead=' + leadId); setDealVal(pr2.total);
    });
  };
  renderProducts();
  // πρόσωπα
  const renderPeople = async () => {
    const pp = await api('people&lead=' + leadId);
    const pb = $('#lPeopleBox', dr);
    if (!pb) return;
    pb.innerHTML = pp.people.map(p => `
      <div style="display:flex;gap:9px;align-items:center;padding:5px 0;border-bottom:1px dashed var(--line)">
        <span class="ava">${esc((p.name || '?').split(/\s+/).map(x => x[0]).join('').slice(0, 2).toUpperCase())}</span>
        <div style="flex:1"><b>${esc(p.name)}</b>${p.title ? ` <span class="pill pill-mut">${esc(p.title)}</span>` : ''}
          <div class="mut" style="font-size:11px">${esc([p.phone, p.email].filter(Boolean).join(' · ') || '—')}</div></div>
        <button class="btn btn-sm btn-o" data-pdel="${p.id}">✕</button></div>`).join('') + `
      <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:9px">
        <input class="inp" id="ppN" placeholder="Όνομα" style="flex:1;min-width:110px">
        <input class="inp" id="ppT" placeholder="ρόλος" style="width:110px">
        <input class="inp" id="ppP" placeholder="τηλέφωνο" style="width:120px">
        <input class="inp" id="ppE" placeholder="email" style="width:150px">
        <button class="btn btn-sm btn-o" id="ppAdd">+</button></div>`;
    $('#ppAdd', pb).onclick = async () => {
      if (!$('#ppN', pb).value.trim()) return;
      await api('person_save', {id: 0, lead: leadId, name: $('#ppN', pb).value,
        title: $('#ppT', pb).value, phone: $('#ppP', pb).value, email: $('#ppE', pb).value});
      toast('Προστέθηκε'); renderPeople();
    };
    $$('[data-pdel]', pb).forEach(b => b.onclick = async () => {
      await api('person_del', {id: +b.dataset.pdel}); renderPeople();
    });
  };
  renderPeople();
  // εργασίες / δραστηριότητες
  const kindLbl = {call: 'Κλήση', email: 'Email', meeting: 'Συνάντηση', todo: 'To-do'};
  const renderTasks = async () => {
    const tt = await api('lead_tasks&lead=' + leadId);
    const tb = $('#lTasksBox', dr); if (!tb) return;
    tb.innerHTML = (tt.tasks.length ? tt.tasks.map(t => `
      <div style="display:flex;gap:9px;align-items:center;padding:6px 0;border-bottom:1px dashed var(--line);${t.done ? 'opacity:.55' : ''}">
        <input type="checkbox" data-ttog="${t.id}" ${t.done ? 'checked' : ''} style="width:17px;height:17px;cursor:pointer">
        <div style="flex:1;min-width:0"><b style="font-size:13px;${t.done ? 'text-decoration:line-through' : ''}">${esc(t.title)}</b>
          <div class="mut" style="font-size:11px">${kindLbl[t.kind] || 'To-do'}${t.due ? ` · <span style="${!t.done && t.due < today() ? 'color:var(--bad);font-weight:700' : ''}">έως ${dShort(t.due)}</span>` : ''}${t.who ? ' · ' + esc(t.who) : ''}</div></div>
        <button class="btn btn-sm btn-o" data-tdel="${t.id}" style="color:var(--bad)">✕</button></div>`).join('')
      : '<div class="mut" style="font-size:12px">Καμία εργασία ακόμη</div>') + `
      <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:9px">
        <input class="inp" id="ntT" placeholder="π.χ. Κάλεσε για demo" style="flex:1;min-width:150px">
        <select class="inp" id="ntK" style="width:100px"><option value="todo">To-do</option><option value="call">Κλήση</option><option value="email">Email</option><option value="meeting">Συνάντηση</option></select>
        <input type="date" class="inp" id="ntD" style="width:150px" title="προθεσμία">
        <select class="inp" id="ntA" style="width:130px"><option value="">— ανάθεση —</option>${S.boot.admins.map(a => `<option value="${a.id}">${esc(a.name)}</option>`).join('')}</select>
        <button class="btn btn-p btn-sm" id="ntAdd">+</button></div>`;
    $$('[data-ttog]', tb).forEach(ch => ch.onchange = async () => { await api('lead_task_toggle', {id: +ch.dataset.ttog}); renderTasks(); });
    $$('[data-tdel]', tb).forEach(b => b.onclick = async () => { await api('lead_task_del', {id: +b.dataset.tdel}); renderTasks(); });
    $('#ntAdd', tb).onclick = async () => {
      const v = $('#ntT', tb).value.trim(); if (!v) return;
      await api('lead_task_save', {lead: leadId, title: v, kind: $('#ntK', tb).value, due: $('#ntD', tb).value || null, assignee: +$('#ntA', tb).value || 0});
      toast('Προστέθηκε'); renderTasks();
    };
  };
  renderTasks();
  // timeline (ενιαίο ιστορικό)
  const kindIcoT = {call: '📞', email: '✉️', meeting: '🤝', note: '📝', todo: '✓'};
  const loadTimeline = async () => {
    const tl = await api('lead_timeline&lead=' + leadId);
    const tb = $('#lTimeBox', dr); if (!tb) return;
    tb.innerHTML = tl.events.length ? tl.events.map(e => `
      <div style="display:flex;gap:9px;padding:5px 0;border-bottom:1px dashed var(--line)">
        <span style="font-size:14px;flex:none">${kindIcoT[e.kind] || (e.type === 'task' ? '✓' : '•')}</span>
        <div style="flex:1;min-width:0"><b style="font-size:12.5px">${esc(e.text)}</b>
          <div class="mut" style="font-size:11px">${e.type === 'task' ? 'εργασία' + (e.done ? ' ✓' : '') : (kindLbl[e.kind] || '')}${e.at ? ' · ' + dShort(e.at) : ''}${e.by ? ' · ' + esc(e.by) : ''}${e.fup ? ' · ⏰ ' + dShort(e.fup) : ''}</div></div></div>`).join('')
      : '<div class="mut" style="font-size:12px">Καμία δραστηριότητα ακόμη</div>';
  };
  loadTimeline();
}
/* ═════════ KPI ═════════ */
async function vKpi() {
  setTop('KPI Dashboard', 'Η εικόνα της ομάδας σήμερα');
  const c = $('#content');
  cnpSkel(c, '<div class="grid g4">' + '<div class="skel" style="height:90px"></div>'.repeat(6) + '</div>');
  let dErr = null;
  const d = await api('kpi').catch(e => { dErr = e; return null; });
  if (!d) { c.innerHTML = cnpDenied(dErr); return; }
  const k = d.cards;
  const net = d.month.won - d.month.laborCost - d.month.expenses;
  c.innerHTML = `
  <div class="grid g4" style="margin-bottom:6px">
    ${suStat(I.ticket, k.open, 'Ανοιχτά tickets', k.open ? 'var(--brand)' : 'var(--ok)')}
    ${suStat(I.alert, k.slaOver, 'Εκτός SLA', k.slaOver ? 'var(--bad)' : 'var(--ok)')}
    ${suStat(I.chat, k.waiting, 'Περιμένουν απάντηση', k.waiting ? 'var(--warn)' : 'var(--ok)')}
    ${suStat(I.clock, k.stale, 'Ανοιχτά >7 ημερών', k.stale ? 'var(--bad)' : 'var(--ok)')}
    ${suStat(I.checkSquare, k.closedToday, 'Έκλεισαν σήμερα', 'var(--ok)')}
    ${suStat(I.user, k.unassigned, 'Χωρίς ανάθεση', k.unassigned ? 'var(--warn)' : 'var(--ok)')}
  </div>
  <div class="grid g2">
    <div class="card"><div class="card-h">${I.trophy} Παραγωγικότητα σήμερα</div>
      <table class="tbl"><thead><tr><th>Agent</th><th>Ομάδα</th><th>Tickets</th><th>Απαντ.</th><th>Tasks ✓</th><th>Χρόνος</th><th>Score</th></tr></thead>
      <tbody>${d.agents.length ? d.agents.map((a, i) => `<tr>
        <td><span class="ava" style="margin-right:7px">${esc(a.ini)}</span><b>${i === 0 && a.score > 0 ? (I.trophy + ' ') : ''}${esc(a.name)}</b></td>
        <td class="mut">${esc(a.team || '—')}</td><td>${a.open}</td><td>${a.replies}</td><td>${a.done}</td>
        <td>${a.mins ? fmtMin(a.mins) : '—'}</td><td><b>${a.score}</b></td></tr>`).join('')
        : '<tr><td colspan="7" class="empty">Καμία δραστηριότητα ακόμη σήμερα</td></tr>'}</tbody></table></div>
    <div>
      <div class="card"><div class="card-h">${I.scale} Φόρτος ομάδας</div>
        <table class="tbl"><thead><tr><th>Agent</th><th>Ανοιχτά</th><th>Εκτίμηση</th><th>Σήμερα</th><th>Εκπρόθ.</th></tr></thead>
        <tbody>${d.workload.length ? d.workload.map(w => `<tr><td><b>${esc(w.name)}</b></td>
          <td>${w.open}</td><td>${w.est ? `<span class="${w.est >= 480 ? 'pill pill-bad' : ''}">${fmtMin(w.est)}</span>` : '—'}</td>
          <td>${w.today}</td><td>${w.over ? `<b style="color:var(--bad)">${w.over}</b>` : '0'}</td></tr>`).join('')
          : '<tr><td colspan="5" class="empty">Χωρίς ανοιχτά tasks με ανάθεση</td></tr>'}</tbody></table></div>
      <div class="card"><div class="card-h">${I.coin} Μήνας — γρήγορη εικόνα</div><div class="card-b">
        <div class="grid" style="grid-template-columns:repeat(2,minmax(0,1fr));gap:10px">
          <div class="stat ok" style="margin:0"><b>${fmtEur(d.month.won)}</b><small>Κερδισμένες προσφορές</small></div>
          <div class="stat warn" style="margin:0"><b>${fmtEur(d.month.laborCost)}</b><small>Κόστος εργασίας (${fmtMin(d.month.minutes)})</small></div>
          <div class="stat warn" style="margin:0"><b>${fmtEur(d.month.expenses)}</b><small>Έξοδα projects</small></div>
          <div class="stat ${net >= 0 ? 'ok' : 'bad'}" style="margin:0"><b>${fmtEur(net)}</b><small>Καθαρό</small></div>
        </div></div></div>
    </div>
  </div>`;
}

/* ───────── exports για views2.js ───────── */
/* Η άρνηση έρχεται από την πύλη δικαιωμάτων και λέει ΠΟΙΑ ενότητα λείπει.
   Το «Μόνο για διαχειριστές» ήταν λάθος αφότου σπάσαμε τη Διοίκηση σε τρία. */
/* ───────── Κλειστό μενού: όνομα στο hover ─────────
   Το tooltip ζει στο <body>, όχι ως ::after μέσα στη στήλη: το .snav κυλάει,
   και ό,τι βγαίνει από τα όριά του θα κοβόταν. Δείχνει και την ενότητα, γιατί
   όταν το μενού είναι κλειστό οι επικεφαλίδες δεν φαίνονται πουθενά. */
let _sideTipEl = null;
let _sideTipFor = null;
function sideTipHide() {
  if (_sideTipEl) { _sideTipEl.remove(); _sideTipEl = null; }
  _sideTipFor = null;
}
/** Τοποθέτηση δίπλα στο εικονίδιο, πάντα μέσα στην οθόνη. */
function sideTipPlace(btn, t) {
  const r = btn.getBoundingClientRect();
  const h = t.offsetHeight;
  t.style.left = Math.round(r.right + 10) + 'px';
  t.style.top = Math.round(Math.max(6, Math.min(r.top + r.height / 2 - h / 2, innerHeight - h - 6))) + 'px';
}
function sideTipShow(btn) {
  if (_sideTipFor === btn && _sideTipEl) { sideTipPlace(btn, _sideTipEl); return; }
  sideTipHide();
  const lb = btn.dataset.lb || '';
  const gp = btn.dataset.grp || '';
  if (!lb) { return; }
  const t = document.createElement('div');
  t.className = 'side-tip';
  t.innerHTML = esc(lb) + (gp && gp !== lb ? `<small>${esc(gp)}</small>` : '');
  document.body.appendChild(t);
  sideTipPlace(btn, t);
  _sideTipEl = t;
  _sideTipFor = btn;
}
/** Ένας ακροατής στη στήλη — όχι 34 ακροατές που ξαναδένονται σε κάθε render. */
function sideTips() {
  const side = $('.side');
  if (!side || side._tips) { return; }
  side._tips = 1;
  const on = e => {
    const b = e.target.closest ? e.target.closest('.sitem') : null;
    if (!b || !document.querySelector('.shell.collapsed')) { sideTipHide(); return; }
    sideTipShow(b);
  };
  side.addEventListener('mouseover', on);
  side.addEventListener('mouseleave', sideTipHide);
  side.addEventListener('click', sideTipHide);
  const nav = $('.snav');
  if (nav) {
    nav.addEventListener('scroll', () => {
      if (!_sideTipFor || !_sideTipEl) { return; }
      const r = _sideTipFor.getBoundingClientRect();
      const nr = nav.getBoundingClientRect();
      // βγήκε από το ορατό μέρος της στήλης → δεν έχει νόημα να δείχνει
      if (r.bottom < nr.top + 2 || r.top > nr.bottom - 2) { sideTipHide(); return; }
      sideTipPlace(_sideTipFor, _sideTipEl);
    }, {passive: true});
  }
  addEventListener('resize', sideTipHide);
}
/** Μετά το μάζεμα, φέρε το τρέχον κύκλωμα σε κοινή θέα. */
function scrollActiveIntoView() {
  const el = document.querySelector('.sitem.on');
  if (el && el.scrollIntoView) { el.scrollIntoView({block: 'nearest'}); }
}
/** Έχει ο τρέχων χειριστής τη δυνατότητα; Δέχεται «ενότητα.δυνατότητα» ή σκέτη ενότητα. */
function cnpCan(cap) {
  const me = S.boot && S.boot.me;
  if (!me) { return false; }
  if (me.full) { return true; }
  if ((me.caps || []).includes(cap)) { return true; }
  // Η Διαγραφή δίνεται ΜΟΝΟ ρητά — δεν κληρονομείται από την πρόσβαση στο κύκλωμα.
  if (cap.endsWith(".delete") || cap === "clients.offer_delete") { return false; }
  return (me.areas || []).includes(cap.includes(".") ? cap.split(".")[0] : cap);
}

function cnpDenied(err) {
  const msg = (err && err.message) || '';
  return `<div class="empty"><div class="big">${I.lock}</div>${esc(msg) || 'Δεν έχεις πρόσβαση σε αυτή την οθόνη'}
    <div class="mut" style="font-size:12.5px;margin-top:8px">Τα δικαιώματα δίνονται από τις ομάδες — ζήτησέ το από διαχειριστή.</div></div>`;
}
/* ═══ Αναζήτηση παντού (Ctrl+K) ═══
   Το κουμπί υπήρχε από την αρχή, η υλοποίηση όχι — έψαχνες πελάτη και δεν άνοιγε
   τίποτα. Ένα πεδίο, τέσσερις ομάδες αποτελεσμάτων, πλοήγηση με βελάκια. */
/* ═════════ ⌘K — πήγαινε, ψάξε ή ΚΑΝΕ (22/9/2026) ═════════
   Ήταν μόνο αναζήτηση. Τώρα είναι και εκτοξευτήρας: κάθε οθόνη που δικαιούσαι και οι
   συνηθισμένες ενέργειες γράφονται αντί να ψάχνονται στο πλαϊνό μενού. Ανοίγει με
   προτάσεις, χωρίς να γράψεις τίποτα — αλλιώς κανείς δεν θα μάθαινε ότι κάνει κι αυτό. */
function cnpPalNorm(x) {
  return String(x || '').toLowerCase()
    .replace(/[άΆ]/g, 'α').replace(/[έΈ]/g, 'ε').replace(/[ήΉ]/g, 'η').replace(/[ίΊϊΐ]/g, 'ι')
    .replace(/[όΌ]/g, 'ο').replace(/[ύΎϋΰ]/g, 'υ').replace(/[ώΏ]/g, 'ω').replace(/ς/g, 'σ');
}

function cnpPalette() {
  if (document.getElementById('palOvl')) { return; }
  const ovl = document.createElement('div');
  ovl.className = 'ovl ovl-keep show'; ovl.id = 'palOvl'; ovl.style.zIndex = 260;
  ovl.innerHTML = `<div class="pal-box" style="margin:10vh auto 0;max-width:560px" onclick="event.stopPropagation()">
    <div style="padding:12px 14px;border-bottom:1px solid var(--line);display:flex;gap:9px;align-items:center">
      <span style="font-size:15px">⌘</span>
      <input class="inp" id="palQ" autocomplete="off" style="border:none;box-shadow:none;font-size:15px;padding:4px 0"
        placeholder="Πήγαινε, ψάξε ή κάνε…  (π.χ. «board», «νέο task», «30 #123»)">
      <kbd style="font-size:10px;color:var(--mut)">Esc</kbd>
    </div>
    <div id="palRes" style="max-height:62vh;overflow:auto;padding:6px"></div></div>`;
  document.body.append(ovl);
  const close = () => { ovl.remove(); document.removeEventListener('keydown', onKey, true); };
  ovl.onclick = close;
  const res = ovl.querySelector('#palRes'), q = ovl.querySelector('#palQ');
  let items = [], cur = 0, tmr = null, seq = 0;

  /* ── Ενέργειες: ό,τι ανοίγει το «+ Νέο», συν όσα έχουν νόημα ως εντολή ── */
  const C = window.CNP;
  const acts = () => [
    {icon: I.checkSquare, title: 'Νέο task', kw: 'νεο task εργασια new', go: () => C.quickNew && C.quickNew()},
    {icon: I.sos || I.chat, title: 'Ζήτα βοήθεια / ρώτα συνάδελφο', kw: 'ρωτα βοηθεια αιτημα help', go: () => C.quickHelp && C.quickHelp()},
    {icon: I.users, title: 'Νέα σύσκεψη', kw: 'συσκεψη meeting ραντεβου', go: () => newMeeting()},
    {icon: I.phone, title: 'Καταγραφή κλήσης', kw: 'κληση call τηλεφωνο', go: () => C.quickCall && C.quickCall()},
    {icon: I.alert, title: 'Παράπονο πελάτη', kw: 'παραπονο complaint', go: () => C.quickCx && C.quickCx()},
    {icon: I.target, title: 'Νέο lead', kw: 'lead ευκαιρια crm', go: async () => { const d = await api('crm').catch(() => null); openLead(null, d || {stages: [], leads: []}); }},
    {icon: I.stop, title: 'Σταμάτα τον χρόνο', kw: 'στοπ stop χρονος σταματα', go: async () => {
      const r = await api('timer_stop', {billable: false, note: ''}).catch(e => ({err: e.message}));
      if (r && r.err) { toast(r.err, true); return; }
      toast('Καταχωρήθηκε ' + fmtMin(r.mins)); if (window.R[S.view]) { window.R[S.view](); } }},
    {icon: I.list, title: 'Συντομεύσεις πληκτρολογίου', kw: 'συντομευσεις πληκτρολογιο keyboard shortcuts',
      go: () => cnpKeyHelp([['t', 'ξεκίνα / σταμάτα χρόνο'], ['e', 'ολοκλήρωσε'], ['r', 'απάντησε']])},
  ];

  const paint = () => {
    if (!items.length) {
      res.innerHTML = '<div class="mut" style="padding:16px;text-align:center;font-size:12.5px">Κανένα αποτέλεσμα</div>';
      return;
    }
    let last = '', h = '';
    items.forEach((it, i) => {
      if (it.group !== last) {
        last = it.group;
        h += `<div class="mut" style="padding:9px 10px 4px;font-size:10.5px;font-weight:700;letter-spacing:.06em;text-transform:uppercase">${esc(it.group)}</div>`;
      }
      h += `<a href="javascript:" class="pal-row${i === cur ? ' on' : ''}" data-i="${i}">
        <span class="pal-ic">${it.icon}</span>
        <span class="pal-t">${esc(it.title)}</span>
        ${it.sub ? `<span class="mut pal-s">${esc(it.sub)}</span>` : ''}</a>`;
    });
    res.innerHTML = h;
    res.querySelectorAll('[data-i]').forEach(a => {
      /* ΤΟ ΠΟΝΤΙΚΙ ΔΕΝ ΞΑΝΑΖΩΓΡΑΦΙΖΕΙ. Πριν, το mouseenter καλούσε paint() και
         ξανάγραφε ΟΛΗ τη λίστα — το στοιχείο κάτω από τον κέρσορα καταστρεφόταν
         την ίδια στιγμή, και το κλικ έπεφτε σε αποσυνδεδεμένο κόμβο. Το
         αποτέλεσμα βρισκόταν αλλά δεν άνοιγε ΠΟΤΕ με το ποντίκι — μόνο με
         Enter, που δεν περνά από mouseenter. Εδώ αλλάζουμε μόνο την κλάση. */
      a.onmouseenter = () => {
        cur = +a.dataset.i;
        res.querySelectorAll('.pal-row').forEach(r => r.classList.toggle('on', r === a));
      };
      a.onclick = e => { e.preventDefault(); const it = items[+a.dataset.i]; close(); it.go(); };
    });
    const on = res.querySelector('.pal-row.on'); if (on && on.scrollIntoView) { on.scrollIntoView({block: 'nearest'}); }
  };

  /* Τοπικά (οθόνες + ενέργειες) — απαντούν ΑΜΕΣΩΣ, χωρίς γύρο στον server. */
  const local = v => {
    const n = cnpPalNorm(v), out = [];
    /* Κατάταξη: πρώτα όσα ΑΡΧΙΖΟΥΝ από αυτό που έγραψες, μετά όσα το περιέχουν στον
       τίτλο, τελευταία όσα ταιριάζουν μόνο σε συνώνυμο — αλλιώς το «board» έφερνε
       πρώτο τις «Συντομεύσεις πληκτρολογίου», επειδή το συνώνυμό τους λέει «keyboard». */
    const score = (title, kw) => {
      if (!n) { return 0; }
      const t = cnpPalNorm(title);
      if (t.startsWith(n)) { return 0; }
      if (t.includes(n)) { return 1; }
      if (cnpPalNorm(kw || '').includes(n)) { return 2; }
      return -1;
    };
    acts().forEach(a => { const sc = score(a.title, a.kw);
      if (sc >= 0) { out.push({group: 'Ενέργειες', icon: a.icon, title: a.title, go: a.go, _s: sc}); } });
    (S.nav || []).forEach(x => { const sc = score(x.label, x.group);
      if (sc >= 0) { out.push({group: 'Οθόνες', icon: x.icon, title: x.label, sub: x.group, go: () => go(x.k), _s: sc}); } });
    /* Η ομάδα που έχει το καλύτερο ταίριασμα πάει πρώτη — όχι πάντα οι «Ενέργειες». */
    const best = {};
    out.forEach(x => { best[x.group] = Math.min(best[x.group] === undefined ? 9 : best[x.group], x._s); });
    out.sort((a, b) => (best[a.group] - best[b.group])
      || (a.group < b.group ? -1 : a.group > b.group ? 1 : 0) || a._s - b._s);
    /* «30 #123» → χρέωση χρόνου. Η πιο συχνή εντολή που δεν αξίζει οθόνη.
       ΤΟ # ΕΙΝΑΙ ΥΠΟΧΡΕΩΤΙΚΟ. Ήταν προαιρετικό, οπότε το «105» — ένας άνθρωπος
       που ψάχνει την εργασία #105 — διαβαζόταν ως «10΄ στην εργασία #5» και
       έμπαινε ΠΡΩΤΟ στη λίστα. Ένα κλικ και χρεωνόταν χρόνος σε λάθος εργασία. */
    const m = v.match(/^(\d{1,4})\s*(?:λ|min|΄|')?\s*#\s*(\d{1,7})$/);
    if (m) {
      const mins = +m[1], tid = +m[2];
      out.unshift({group: 'Ενέργειες', icon: I.clock, title: 'Χρέωσε ' + fmtMin(mins) + ' στην εργασία #' + tid,
        sub: 'χρόνος', go: async () => {
          const r = await api('time_add', {task: tid, mins}).catch(e => ({err: e.message}));
          if (r && r.err) { toast(r.err, true); return; }
          toast('✔ Καταχωρήθηκε ' + fmtMin(mins) + ' στην #' + tid);
          if (window.R[S.view]) { window.R[S.view](); } }});
    }
    return out;
  };

  const run = async () => {
    const v = q.value.trim();
    const my = ++seq;
    items = local(v);
    cur = 0; paint();
    if (v.length < 2) { return; }
    const d = await api('search&q=' + encodeURIComponent(v)).catch(() => null);
    if (!d || my !== seq) { return; }
    (d.clients || []).forEach(c => items.push({group: 'Πελάτες', icon: '🏢', title: c.name,
      sub: [c.afm ? 'ΑΦΜ ' + c.afm : '', c.email, '#' + c.id].filter(Boolean).join(' · '),
      go: () => go('client360', c.id)}));
    (d.tasks || []).forEach(t => items.push({group: 'Εργασίες', icon: '🟦', title: t.title,
      sub: t.pname || '', go: () => openTask(t.id)}));
    (d.tickets || []).forEach(t => items.push({group: 'Tickets', icon: '🎫', title: t.title,
      sub: '#' + t.tid + ' · ' + (t.status || ''), go: () => openTicketQuick(t.id)}));
    (d.leads || []).forEach(l => items.push({group: 'Leads', icon: '🎯', title: l.name,
      sub: l.stage || '', go: () => go('crm')}));
    paint();
  };

  const onKey = e => {
    if (e.key === 'Escape') { e.preventDefault(); close(); return; }
    if (!items.length) { return; }
    if (e.key === 'ArrowDown') { e.preventDefault(); cur = (cur + 1) % items.length; paint(); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); cur = (cur - 1 + items.length) % items.length; paint(); }
    else if (e.key === 'Enter') { e.preventDefault(); const it = items[cur]; close(); it.go(); }
  };
  document.addEventListener('keydown', onKey, true);
  q.oninput = () => { items = local(q.value.trim()); cur = 0; paint(); clearTimeout(tmr); tmr = setTimeout(run, 220); };
  run();
  setTimeout(() => q.focus(), 40);
}
/* Ctrl/⌘+K από παντού — εκτός αν γράφεις ήδη κάπου αλλού. */
document.addEventListener('keydown', e => {
  if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) {
    e.preventDefault(); cnpPalette();
  }
}, true);

/* ═════════ 📊 Dashboard kit (22/9/2026) ═════════
   Τα τρία κομμάτια που ξαναγράφονταν σε κάθε οθόνη με νούμερα ζουν ΕΔΩ,
   ώστε ο επόμενος πίνακας να μοιάζει με τον προηγούμενο χωρίς να το ξανασχεδιάσει κανείς.
   Κανόνες: χρώμα ΜΟΝΟ όταν σημαίνει κάτι, τίτλος που λέει τι μετράει, και tooltip
   που εξηγεί ΠΩΣ βγήκε ο αριθμός — ένας ανεξήγητος αριθμός δεν λύνει απόφαση. */

/** Σειρά αριθμών. items: [{n, label, color, tip, go}] — το `label` δέχεται <br>. */
function cnpKpis(items, opts) {
  opts = opts || {};
  const it = (items || []).filter(Boolean);
  if (!it.length) { return ''; }
  return `<div class="dkpi${opts.cls ? ' ' + opts.cls : ''}" style="--n:${it.length}">${it.map(k =>
    `<div class="dkpi-i${k.go || k.act ? ' go' : ''}${k.on ? ' on' : ''}"${k.go ? ` data-dkgo="${esc(k.go)}"` : ''}${k.act ? ` data-dkact="${esc(k.act)}"` : ''}${k.tip ? ` title="${esc(k.tip)}"` : ''}>
      <b${k.color ? ` style="color:${k.color}"` : ''}>${k.n}</b><small>${k.label}</small></div>`).join('')}</div>`;
}

/** Στήλες ρυθμού. days: [{d, date, mins, today}] — μία μέρα δεν λέει τίποτα, επτά λένε. */
function cnpSpark(days, opts) {
  opts = opts || {};
  const ds = days || [];
  if (!ds.length) { return ''; }
  const fmt = opts.fmt || fmtMin;
  const mx = Math.max(1, ...ds.map(x => x.mins || 0));
  return `<div class="dspark">${ds.map(x => `<span class="dspark-b${x.today ? ' on' : ''}"
    style="--h:${Math.round((x.mins || 0) / mx * 100)}%"
    title="${esc(x.d || '')}${x.date ? ' ' + esc(x.date.slice(8, 10) + '/' + x.date.slice(5, 7)) : ''} — ${esc(fmt(x.mins || 0))}"><i></i><small>${esc(x.d || '')}</small></span>`).join('')}</div>`;
}

/** Πόση ώρα πέρασε — σύντομα, για σήμα πάνω από κύκλο. */
function cnpLastLbl(at) {
  if (!at) { return '—'; }
  const dt = new Date(String(at).replace(' ', 'T')), n = new Date();
  const dd = Math.round((new Date(n.getFullYear(), n.getMonth(), n.getDate()) - new Date(dt.getFullYear(), dt.getMonth(), dt.getDate())) / 86400000);
  return dd <= 0 ? dt.toLocaleTimeString('el-GR', {hour: '2-digit', minute: '2-digit', hour12: false})
    : dd === 1 ? 'χθες' : dd < 7 ? dd + ' ημ.'
    : String(dt.getDate()).padStart(2, '0') + '/' + String(dt.getMonth() + 1).padStart(2, '0');
}

/**
 * Οριζόντια μπάρα ανθρώπων: ένας κύκλος ο καθένας, πράσινος όταν είναι μέσα.
 * Πάνω από τον κύκλο πόση ώρα είναι μέσα σήμερα (εκτός → πότε ήταν η τελευταία
 * του κίνηση), και ▶ όταν τρέχει χρονόμετρο. Κλικ → η μέρα του (openTeamPulse).
 * people: [{id, name, ini, color, status, label, hint, team, connMins, connSince, seenAt, login, workingOn}]
 */
function cnpPeopleBar(people, opts) {
  opts = opts || {};
  const ps = people || [];
  if (!ps.length) { return ''; }
  /* Κάθε οθόνη μπορεί να βάλει ΔΙΚΟ της σήμα και δικό της κλικ: στα αιτήματα το σήμα
     είναι «πόσο καιρό σε περιμένει», όχι «πόση ώρα είναι μέσα». */
  const attr = opts.attr || 'tmp';
  const inN = ps.filter(x => x.status !== 'offline').length;
  const workN = ps.filter(x => x.workingOn).length;
  return `<div class="tmb${opts.cls ? ' ' + opts.cls : ''}">
    <div class="tmb-h">${I.users}<b>${esc(opts.title || 'Η ομάδα τώρα')}</b>
      <span class="mut">${esc(opts.hint || (inN + ' μέσα' + (workN ? ' · ' + workN + ' με χρονόμετρο' : '')))}</span>
      <span style="flex:1"></span>
      ${/* ΤΙ ΣΗΜΑΙΝΕΙ ΤΟ ΧΡΩΜΑ. Χωρίς υπόμνημα, το κίτρινο διαβαζόταν ως «έφυγε» —
           ενώ σημαίνει απλώς ότι δεν άγγιξε το εργαλείο τα τελευταία λεπτά. */''}
      <span class="tmb-leg" title="Το χρώμα δείχνει αν ο άνθρωπος κινείται ΜΕΣΑ στο εργαλείο — όχι αν δουλεύει">
        <i style="background:var(--ok)"></i>στην εφαρμογή
        <i style="background:var(--warn,#eba63c)"></i>χωρίς κίνηση 5΄+
        <i style="background:#9aa7ba"></i>εκτός</span>
      ${opts.link ? `<a class="myd-link" data-go="${esc(opts.link[0])}">${esc(opts.link[1])}</a>` : ''}
      ${opts.right || ''}</div>
    <div class="tmb-strip">${ps.map(x => {
      const inNow = x.status !== 'offline';
      const badge = opts.badge ? opts.badge(x)
        : inNow ? (x.connMins ? fmtMin(x.connMins) : 'μόλις') : cnpLastLbl(x.seenAt || x.login);
      return `<button class="tmb-p${inNow ? ' in' : ''}${x.workingOn ? ' work' : ''}${x.sel ? ' sel' : ''}" data-${attr}="${x.id}"
        title="${esc(opts.tip ? opts.tip(x) : (x.name + (x.team ? ' · ' + x.team : '') + ' — ' + (x.label || '') + (x.hint ? ' (' + x.hint + ')' : '') + (x.workingOn ? ' — ▶ ' + x.workingOn.title : '')))}">
        <span class="tmb-badge${x.hot ? ' hot' : ''}">${esc(badge)}</span>
        <span class="tmb-av" style="--sc:${esc(x.color || '#9aa7b8')}">${esc(x.ini || '?')}${x.workingOn ? '<i class="tmb-run">▶</i>' : ''}</span>
        <span class="tmb-n">${esc(String(x.name || '').split(' ')[0])}</span></button>`;
    }).join('')}</div></div>`;
}

/**
 * 📅 Η μέρα σε μία γραμμή: πού πάνε οι συσκέψεις, πού είναι το τώρα, πού είναι ο αέρας.
 *
 * Μια λίστα σου λέει τι έχεις· μια γραμμή σου λέει αν χωράει κάτι ακόμη — και αυτό
 * είναι η ερώτηση που κάνεις το πρωί. Οι αναπάντητες προσκλήσεις χτυπάνε στο μάτι,
 * γιατί μια σύσκεψη που δεν απάντησες είναι το πιο πιθανό να σου φύγει.
 *
 * events: [{id, title, start, end, allDay, kind, clientName, location, mode, rsvp, now, over, mine, by, people}]
 */
function cnpDayStrip(events, opts) {
  opts = opts || {};
  const evs = (events || []).slice();
  const allDay = evs.filter(e => e.allDay);
  const timed = evs.filter(e => !e.allDay).sort((a, b) => String(a.start).localeCompare(String(b.start)));
  const T = x => new Date(String(x).replace(' ', 'T')).getTime();
  const now = Date.now();
  const d0 = new Date(); d0.setHours(0, 0, 0, 0);
  const H = ms => (ms - d0.getTime()) / 3600000;

  /* Το παράθυρο μεγαλώνει μόνο όσο χρειάζεται: μια σύσκεψη στις 7 το πρωί
     δεν πρέπει να πέσει έξω από τη γραμμή επειδή διαλέξαμε σταθερό 8:00–18:00. */
  let a = 8, b = 18;
  timed.forEach(e => { a = Math.min(a, Math.floor(H(T(e.start)))); b = Math.max(b, Math.ceil(H(T(e.end)))); });
  a = Math.max(0, Math.min(a, Math.floor(H(now))));
  b = Math.min(24, Math.max(b, Math.ceil(H(now)) + 1));
  if (b - a < 6) { b = Math.min(24, a + 6); }
  const span = b - a;
  const pos = ms => Math.max(0, Math.min(100, (H(ms) - a) / span * 100));

  const hm = ms => { const dt = new Date(ms); return String(dt.getHours()).padStart(2, '0') + ':' + String(dt.getMinutes()).padStart(2, '0'); };
  const ticks = [];
  for (let h = a; h <= b; h++) { ticks.push(`<span class="dday-tk" style="left:${(h - a) / span * 100}%"><i></i><small>${String(h).padStart(2, '0')}</small></span>`); }

  const unans = timed.filter(e => !e.over && !e.mine && !e.rsvp).length;
  const nowEv = timed.find(e => T(e.start) <= now && T(e.end) >= now);
  const nextEv = timed.find(e => T(e.start) > now);
  const mins = Math.round(timed.reduce((n, e) => n + (T(e.end) - T(e.start)) / 60000, 0));
  let head;
  if (nowEv) { head = 'τώρα σε σύσκεψη ως ' + hm(T(nowEv.end)); }
  else if (nextEv) { const dm = Math.round((T(nextEv.start) - now) / 60000);
    head = 'ελεύθερος ως ' + hm(T(nextEv.start)) + (dm <= 90 ? ' — σε ' + dm + '΄' : ''); }
  else if (timed.length) { head = 'καμία σύσκεψη μπροστά σου σήμερα'; }
  else { head = 'καμία σύσκεψη σήμερα — όλη η μέρα δική σου'; }

  const blk = e => {
    const l = pos(T(e.start)), w = Math.max(2.2, pos(T(e.end)) - l);
    const st = e.over ? 'past' : (T(e.start) <= now && T(e.end) >= now) ? 'live' : 'next';
    const rs = e.mine ? 'mine' : e.rsvp === 'accepted' ? 'yes' : e.rsvp === 'declined' ? 'no' : 'ask';
    const who = e.mine ? 'εσύ διοργανώνεις' : 'σε κάλεσε ο ' + (e.by || '—');
    const ans = e.mine ? '' : e.rsvp === 'accepted' ? ' · δήλωσες ✔' : e.rsvp === 'declined' ? ' · δήλωσες ✖' : ' · ΔΕΝ έχεις απαντήσει';
    return `<button class="dday-e ${st} rs-${rs}" style="left:${l}%;width:${w}%" data-ddev="${e.id}"
      title="${esc(hm(T(e.start)) + '–' + hm(T(e.end)) + ' · ' + e.title + ' · ' + who + ans + (e.clientName ? ' · ' + e.clientName : '') + (e.people ? ' · ' + e.people + ' άτομα' : ''))}">
      ${rs === 'ask' && !e.over ? '<i class="dday-q">?</i>' : ''}<span>${esc(e.title)}</span></button>`;
  };

  return `<div class="dday${opts.cls ? ' ' + opts.cls : ''}">
    <div class="dday-h">${I.cal}<b>${esc(opts.title || 'Το ημερολόγιό μου σήμερα')}</b>
      <span class="mut">${esc(timed.length ? timed.length + (timed.length === 1 ? ' σύσκεψη' : ' συσκέψεις') + (mins ? ' · ' + fmtMin(mins) : '') + ' · ' + head : head)}</span>
      ${unans ? `<span class="pill pill-warn" title="Προσκλήσεις που δεν έχεις απαντήσει">${unans} αναπάντητη${unans > 1 ? 'ς' : ''}</span>` : ''}
      <span style="flex:1"></span>
      ${allDay.map(e => `<span class="pill pill-mut" title="Όλη μέρα">${esc(e.title)}</span>`).join('')}
      ${opts.link ? `<a class="myd-link" data-go="${esc(opts.link[0])}">${esc(opts.link[1])}</a>` : ''}</div>
    <div class="dday-tr">${ticks.join('')}
      <span class="dday-now" style="left:${pos(now)}%" title="τώρα ${esc(hm(now))}"></span>
      ${timed.map(blk).join('')}</div>
    ${timed.length ? `<div class="dday-list">${timed.map(e => `<button class="dday-c${e.over ? ' past' : ''}" data-ddev="${e.id}">
      <b>${esc(hm(T(e.start)))}</b> ${esc(e.title)}${!e.mine && !e.rsvp && !e.over ? ' <i class="dday-q">?</i>' : ''}</button>`).join('')}</div>` : ''}</div>`;
}

/** Δένει ό,τι έχει φτιάξει το kit μέσα σε ένα root (κύκλοι ανθρώπων, κλικαριστά πλακίδια). */
function cnpWireDash(root) {
  const r = root || document.querySelector('#content');
  if (!r) { return; }
  r.querySelectorAll('[data-tmp]').forEach(b => b.onclick = () => openTeamPulse(+b.dataset.tmp));
  r.querySelectorAll('[data-dkgo]').forEach(b => b.onclick = () => go(b.dataset.dkgo));
  r.querySelectorAll('[data-ddev]').forEach(b => b.onclick = () => go('calendar'));
}

/* ═════════ 🧩 Η διάταξη της «Μέρας μου» ανά άνθρωπο (22/9/2026) ═════════
   Ο project manager θέλει τις Κάρτες, ο τεχνικός την Ουρά, ο πωλητής τα follow-ups.
   Ίδια οθόνη, ο καθένας κρύβει ό,τι δεν του λέει και βάζει πρώτο ό,τι κοιτάζει πρώτο.
   Η διάταξη ζει στον server (pref myday_layout), ώστε να ακολουθεί το άτομο και όχι
   τον browser. Νέο μπλοκ που θα προστεθεί αύριο εμφανίζεται μόνο του στο τέλος. */
/**
 * ΑΚΥΡΩΣΕΙΣ ΥΠΗΡΕΣΙΩΝ στην κάρτα της ημέρας.
 *
 * ΚΑΤΟΝΟΜΑΖΕΙ, ΔΕΝ ΑΘΡΟΙΖΕΙ. Ένα «καμία εκκρεμότητα» δεν επιβεβαιώνει τίποτα:
 * για να ξέρεις ότι οι τρεις σημερινές ακυρώσεις έκλεισαν σωστά, πρέπει να δεις
 * τις τρεις — με όνομα πελάτη και ετυμηγορία η καθεμία.
 *
 * Σειρά: πρώτα ό,τι έχει ΠΡΟΒΛΗΜΑ (από οποιαδήποτε μέρα — ένα ξεχασμένο
 * μηχάνημα κοστίζει κάθε μέρα), μετά τι ζητήθηκε και εκκρεμεί, μετά οι
 * σημερινές με ✔/✘. Αν δεν έγινε καμία σήμερα, δείχνει τις τελευταίες — ώστε
 * να μη μένει ποτέ κενό.
 *
 * Η ΕΝΕΡΓΕΙΑ δεν γίνεται από εδώ: οι υπηρεσίες ζουν στο cloudonadminpanel.
 */
async function mydCancels() {
  const box = $('#mydCn');
  if (!box) { return; }
  const d = await api('cancels').catch(() => null);
  if (!d) { box.innerHTML = '<span class="mut">Δεν φορτώθηκε.</span>'; return; }

  const dot = r => r.vmState === 'live' ? 'var(--bad,#b91c1c)'
    : r.vmState === 'unknown' ? 'var(--warn,#b45309)' : 'var(--ok,#15803d)';
  const mark = r => r.ok ? '✔' : (r.vmState === 'unknown' ? '?' : '✘');

  const d10 = v => (v ? String(v).slice(8, 10) + '/' + String(v).slice(5, 7) : '');

  /* ΤΙ ΖΗΤΗΣΕ Ο ΠΕΛΑΤΗΣ ΚΑΙ ΠΟΤΕ. Έλεγε μόνο «το ζήτησε ο πελάτης» — δεν
     ξεχώριζε μια ακύρωση που έπρεπε να γίνει ΑΜΕΣΩΣ από μία που δικαιούνταν να
     τρέχει ως τη λήξη της περιόδου. Η αιτιολογία του πελάτη στο tooltip. */
  const asked = r => r.asked
    ? `<b class="cn-ask" title="${esc(r.reason || 'χωρίς αιτιολογία')}">${esc(r.typeLbl || 'ακύρωση')}</b>${
        r.askedAt ? ' · ζητήθηκε ' + d10(r.askedAt) : ''}`
    : 'ακύρωση από εμάς';

  const row = (r, showNote) => `<div class="myd-row${r.ok ? '' : ' wait'}">
    <span class="dot" style="background:${dot(r)};flex:none"></span>
    <span class="myd-t"><b>${esc(r.client)}</b><span class="mut"> · ${esc(r.product || 'υπηρεσία')}
      · #${r.service}${r.vm ? ' · VM ' + r.vm : ''}</span>
      ${showNote ? `<div class="cn-sub">${asked(r)} · ${esc(r.note)}</div>` : ''}</span>
    <span class="cn-mk" style="color:${dot(r)};flex:none" title="${esc(r.note)}">${mark(r)}</span></div>`;

  const bad = d.bad || [], open = d.open || [], today = d.today || [], recent = d.recent || [];
  let html = '';
  if (bad.length) {
    html += `<div class="myd-lbl bad">Θέλουν χέρι — ${bad.length}</div>`
          + bad.map(r => row(r, true)).join('');
  }
  if (open.length) {
    html += `<div class="myd-lbl">Ζητήθηκαν, εκκρεμούν — ${open.length}</div>`
          + open.map(r => row(r, true)).join('');
  }
  if (today.length) {
    const okN = today.filter(r => r.ok).length;
    html += `<div class="myd-lbl">Έκλεισαν σήμερα — ${today.length}${
      okN === today.length ? ' · όλες σωστά' : ` · ${today.length - okN} με πρόβλημα`}</div>`
          + today.map(r => row(r, true)).join('');
  } else if (recent.length) {
    html += `<div class="myd-lbl">Καμία σήμερα — οι τελευταίες ${recent.length}</div>`
          + recent.map(r => row(r, true)).join('');
  }
  if (!html) { html = '<span class="mut">Καμία ακύρωση τις τελευταίες ημέρες.</span>'; }
  if (d.stale) {
    /* Νοικοκυριό, όχι πρόβλημα. Η αρχική διατύπωση («παλιές εγγραφές σύνδεσης»)
       δεν έλεγε σε κανέναν τι σημαίνει — και μια γραμμή που θέλει εξήγηση δεν
       έχει λόγο να κάθεται σε κάρτα που διαβάζεται σε πέντε δευτερόλεπτα. */
    html += `<div class="mut" style="font-size:11.5px;margin-top:8px"
      title="Ο πίνακας που λέει «ποιο VM ανήκει σε ποια υπηρεσία» κράτησε τη γραμμή και μετά τη διαγραφή. Δεν επηρεάζει τίποτα: διαβάζεται μόνο όταν ενεργούμε πάνω σε ζωντανή υπηρεσία.">
      ${d.stale} ${d.stale === 1 ? 'ακυρωμένη υπηρεσία δείχνει' : 'ακυρωμένες υπηρεσίες δείχνουν'}
      ακόμη σε σβησμένο VM <b>στα αρχεία μας</b> — δεν επηρεάζει τίποτα.</div>`;
  }
  box.innerHTML = html;
}

const MYD_BLOCKS = [
  {k: 'day', col: 'bar', label: 'Το ημερολόγιό μου σήμερα'},
  {k: 'team', col: 'bar', label: 'Η ομάδα τώρα'},
  {k: 'att', col: 'main', label: 'Θέλουν εσένα'},
  {k: 'plan', col: 'main', label: 'Το πρόγραμμά μου σήμερα'},
  {k: 'queue', col: 'main', label: 'Ουρά tickets'},
  {k: 'coach', col: 'rail', label: 'Καθοδήγηση'},
  {k: 'dl', col: 'rail', label: 'Προθεσμίες μπροστά'},
  {k: 'sup', col: 'rail', label: 'Επιβλέπω — τα ανέθεσα εγώ'},
  {k: 'wait', col: 'rail', label: 'Περιμένω άλλους'},
  {k: 'ov', col: 'rail', label: 'Υπερβάσεις ομάδας'},
  /* Φαίνεται μόνο σε όποιον έχει το δικαίωμα — ξεκινά με τους διαχειριστές και
     δίνεται σε όποιον ορίσουμε, χωρίς αλλαγή κώδικα. */
  {k: 'cancels', col: 'rail', label: 'Ακυρώσεις υπηρεσιών', cap: 'reports.cancels'},
];

/** Η αποθηκευμένη σειρά, συμπληρωμένη με ό,τι δεν ξέρει ακόμη. */
function mydLayout() {
  const saved = String((S.boot.me && S.boot.me.mydLayout) || '').split(',').filter(Boolean);
  const known = MYD_BLOCKS.map(b => b.k);
  const out = [];
  saved.forEach(x => { const k = x.replace(/^-/, ''); if (known.includes(k) && !out.some(o => o.k === k)) { out.push({k, on: x[0] !== '-'}); } });
  known.forEach(k => { if (!out.some(o => o.k === k)) { out.push({k, on: true}); } });
  return out;
}
const mydOn = k => { const f = mydLayout().find(x => x.k === k); return !f || f.on; };
/** Τα κλειδιά μιας στήλης, με τη σειρά του χρήστη και μόνο τα ανοιχτά. */
const mydCol = col => mydLayout().filter(x => x.on)
  .map(x => MYD_BLOCKS.find(b => b.k === x.k))
  .filter(b => b && b.col === col && (!b.cap || cnpCan(b.cap))).map(b => b.k);

/** ⚙ Ο ρυθμιστής: τι βλέπω και με ποια σειρά. */
function mydLayoutDialog(after) {
  let st = mydLayout().filter(x => {
    const b = MYD_BLOCKS.find(y => y.k === x.k);
    return b && (!b.cap || cnpCan(b.cap));
  });
  const ovl = document.createElement('div');
  ovl.className = 'ovl ovl-keep show'; ovl.style.zIndex = cnpTopZ() + 10;
  document.body.appendChild(ovl);
  const close = () => { ovl.remove(); document.removeEventListener('keydown', onK, true); };
  const onK = e => { if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); close(); } };
  setTimeout(() => document.addEventListener('keydown', onK, true), 0);
  ovl.onclick = e => { if (e.target === ovl) { close(); } };

  const COL = {bar: 'Πλάτος οθόνης', main: 'Κύρια στήλη', rail: 'Δεξιά στήλη'};
  const paint = () => {
    ovl.innerHTML = `<div class="pal-box" style="width:min(460px,94vw);margin:10vh auto 0;padding:20px 22px">
      <b style="font-size:15px;color:var(--ink)">Τι βλέπω στη «Μέρα μου»</b>
      <div class="mut" style="font-size:12px;margin-top:4px">Σβήσε ό,τι δεν σου λέει· ανέβασε ό,τι κοιτάς πρώτο.</div>
      <div class="lay">${st.map((x, i) => { const b = MYD_BLOCKS.find(y => y.k === x.k); if (!b) { return ''; }
        return `<div class="lay-r${x.on ? '' : ' off'}">
          <label class="lay-c"><input type="checkbox" data-layk="${b.k}"${x.on ? ' checked' : ''}><span>${esc(b.label)}</span></label>
          <span class="lay-col">${esc(COL[b.col])}</span>
          <button class="lay-b" data-layup="${i}"${i === 0 ? ' disabled' : ''} title="Πάνω">↑</button>
          <button class="lay-b" data-laydn="${i}"${i === st.length - 1 ? ' disabled' : ''} title="Κάτω">↓</button></div>`; }).join('')}</div>
      <div style="display:flex;gap:9px;margin-top:16px;align-items:center">
        <button class="btn btn-sm btn-o" id="layReset">Επαναφορά</button>
        <span style="flex:1"></span>
        <button class="btn btn-sm btn-o" id="layNo">Άκυρο</button>
        <button class="btn btn-sm btn-p" id="layOk">Αποθήκευση</button></div></div>`;
    ovl.querySelectorAll('[data-layk]').forEach(c => c.onchange = () => {
      const it = st.find(x => x.k === c.dataset.layk); if (it) { it.on = c.checked; } paint(); });
    ovl.querySelectorAll('[data-layup]').forEach(b => b.onclick = () => {
      const i = +b.dataset.layup; [st[i - 1], st[i]] = [st[i], st[i - 1]]; paint(); });
    ovl.querySelectorAll('[data-laydn]').forEach(b => b.onclick = () => {
      const i = +b.dataset.laydn; [st[i + 1], st[i]] = [st[i], st[i + 1]]; paint(); });
    ovl.querySelector('#layNo').onclick = close;
    ovl.querySelector('#layReset').onclick = () => { st = MYD_BLOCKS.map(b => ({k: b.k, on: true})); paint(); };
    ovl.querySelector('#layOk').onclick = async () => {
      const v = st.map(x => (x.on ? '' : '-') + x.k).join(',');
      const r = await api('profile_pref', {key: 'myday_layout', value: v}).catch(e => ({err: e.message}));
      if (r && r.err) { toast(r.err, true); return; }
      S.boot.me.mydLayout = r.value !== undefined ? r.value : v;
      toast('Αποθηκεύτηκε'); close(); if (after) { after(); }
    };
  };
  paint();
}

/* ═════════ ⌨ Πλοήγηση με πληκτρολόγιο σε λίστες (22/9/2026) ═════════
   Χώρος εργασίας χωρίς πληκτρολόγιο είναι πίνακας ανακοινώσεων. Ένας δρομέας πάνω
   στις γραμμές: j/k κινείται, Enter ανοίγει, και κάθε οθόνη δηλώνει τα δικά της
   γράμματα. Σιωπά όταν γράφεις κάπου ή όταν υπάρχει ανοιχτό παράθυρο — αλλιώς το
   «t» μέσα σε μια απάντηση θα ξεκινούσε χρονόμετρο. */
let CNP_KB = null;

function cnpKeyNav(opts) {
  if (CNP_KB) { CNP_KB.destroy(); }
  const sel = opts.sel;
  const keys = opts.keys || {};
  let i = typeof opts.start === 'number' ? opts.start : -1;

  const rows = () => Array.from(document.querySelectorAll(sel)).filter(el => el.offsetParent !== null);
  const paint = () => {
    const rs = rows();
    rs.forEach((el, n) => el.classList.toggle('kb-cur', n === i));
    if (i >= 0 && rs[i]) { rs[i].scrollIntoView({block: 'nearest', behavior: 'smooth'}); }
    if (opts.onMove) { opts.onMove(i, rs[i]); }
  };
  const move = d => { const rs = rows(); if (!rs.length) { return; }
    i = i < 0 ? (d > 0 ? 0 : rs.length - 1) : Math.max(0, Math.min(rs.length - 1, i + d)); paint(); };
  const cur = () => rows()[i] || null;

  /* Ένα παράθυρο, ένας διάλογος ή ένα πεδίο κειμένου έχουν ΠΑΝΤΑ προτεραιότητα. */
  const blocked = () => {
    const vis = el => el && el.offsetParent !== null;
    if (Array.from(document.querySelectorAll('.ovl, .drawer, #miniMenu')).some(vis)) { return true; }
    const a = document.activeElement;
    return !!(a && (a.tagName === 'INPUT' || a.tagName === 'TEXTAREA' || a.tagName === 'SELECT' || a.isContentEditable));
  };
  const onKey = e => {
    if (e.ctrlKey || e.metaKey || e.altKey || blocked()) { return; }
    const k = e.key;
    if (k === 'j' || k === 'ArrowDown') { e.preventDefault(); move(1); return; }
    if (k === 'k' || k === 'ArrowUp') { e.preventDefault(); move(-1); return; }
    if (k === 'Escape' && i >= 0) { e.preventDefault(); i = -1; paint(); return; }
    /* Το «?» έρχεται αλλιώς ανά διάταξη πληκτρολογίου — δεχόμαστε και το Shift+/ . */
    if (k === '?' || (e.shiftKey && (k === '/' || e.code === 'Slash'))) { e.preventDefault(); cnpKeyHelp(opts.help || []); return; }
    /* Πλήκτρα που ΔΕΝ χρειάζονται γραμμή (π.χ. «n» = πήγαινε στο επόμενο) πρέπει να
       δουλεύουν και πριν κουνηθεί ο δρομέας — αλλιώς ο χρήστης πρέπει πρώτα να πατήσει j. */
    const gk = (opts.globalKeys || {})[k];
    if (gk) { e.preventDefault(); gk(); return; }
    const el = cur();
    if (!el) { return; }
    if (k === 'Enter') { e.preventDefault(); (keys.Enter || (x => x.click()))(el); return; }
    const fn = keys[k] || keys[k.toLowerCase()];
    if (fn) { e.preventDefault(); fn(el); }
  };
  document.addEventListener('keydown', onKey, true);
  paint();
  CNP_KB = {destroy() { document.removeEventListener('keydown', onKey, true);
    document.querySelectorAll('.kb-cur').forEach(el => el.classList.remove('kb-cur'));
    if (CNP_KB === this) { CNP_KB = null; } },
    get index() { return i; }};
  return CNP_KB;
}

/** Το «?» — ο μόνος τρόπος να μάθει κανείς ότι υπάρχουν συντομεύσεις. */
function cnpKeyHelp(list) {
  if (document.getElementById('kbHelp')) { return; }
  const ovl = document.createElement('div');
  ovl.className = 'ovl ovl-keep show'; ovl.id = 'kbHelp'; ovl.style.zIndex = cnpTopZ() + 10;
  const base = [['j / ↓', 'επόμενη γραμμή'], ['k / ↑', 'προηγούμενη'], ['Enter', 'άνοιξε']];
  ovl.innerHTML = `<div class="pal-box" style="width:min(420px,92vw);margin:16vh auto 0;padding:20px 22px">
    <b style="font-size:15px;color:var(--ink)">Συντομεύσεις πληκτρολογίου</b>
    <div class="kbh">${base.concat(list).map(([k, v]) =>
      `<div class="kbh-r"><kbd>${esc(k)}</kbd><span>${esc(v)}</span></div>`).join('')}
      <div class="kbh-r"><kbd>Esc</kbd><span>καθάρισε τον δρομέα</span></div>
      <div class="kbh-r"><kbd>Ctrl K</kbd><span>αναζήτηση & ενέργειες</span></div></div>
    <div style="text-align:right;margin-top:14px"><button class="btn btn-sm btn-p" id="kbhX">Εντάξει</button></div></div>`;
  document.body.appendChild(ovl);
  const close = () => { ovl.remove(); document.removeEventListener('keydown', onK, true); };
  /* Κλείνει ΜΟΝΟ με Esc, κουμπί ή κλικ έξω. Με «?» θα έκλεινε από το ίδιο πάτημα που
     το άνοιξε, σε διατάξεις όπου το πλήκτρο στέλνει δεύτερο συμβάν. */
  const onK = e => { if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); close(); } };
  setTimeout(() => document.addEventListener('keydown', onK, true), 0);
  ovl.onclick = e => { if (e.target === ovl) { close(); } };
  ovl.querySelector('#kbhX').onclick = close;
}

/* ═════════ ✉ Γρήγορη απάντηση χωρίς να φύγεις από τη «Μέρα μου» (22/9/2026) ═════════
   Η «Μέρα μου» γίνεται ο χώρος εργασίας: ό,τι πιάνεις ανοίγει ΠΑΝΩ της, δεν σε πετάει
   αλλού. Εδώ ζει το 80% της καθημερινής δουλειάς σε ticket ή αίτημα — διάβασε το νήμα,
   απάντησε, κλείσ' το. Ό,τι θέλει περισσότερα (κατηγορία, remote, προτάσεις, συνημμένα)
   ζει στην πλήρη οθόνη, με το «Άνοιξε ολόκληρο» ένα κλικ μακριά: δεν αντιγράφουμε το
   Inbox εδώ, θα ήταν δύο υλοποιήσεις της ίδιας οθόνης που θα ξεσυγχρονίζονταν. */
function qrShell(title) {
  const ovl = document.createElement('div');
  ovl.className = 'ovl ovl-keep show';
  ovl.style.zIndex = cnpTopZ() + 10;
  ovl.innerHTML = `<div class="pal-box qr-box"><div style="padding:22px"><div class="skel" style="height:180px"></div></div></div>`;
  document.body.appendChild(ovl);
  const onKey = e => { if (e.key === 'Escape') { e.stopPropagation(); close(); } };
  const close = () => { ovl.remove(); document.removeEventListener('keydown', onKey); };
  document.addEventListener('keydown', onKey);
  ovl.onclick = e => { if (e.target === ovl) { close(); } };
  return {ovl, box: ovl.querySelector('.qr-box'), close};
}

/** 🎫 Ticket: το νήμα και το πεδίο απάντησης, πάνω στην οθόνη που ήσουν. */
async function openTicketQuick(id, after) {
  const {box, close} = qrShell();
  const d = await api('ticket&id=' + id).catch(e => ({err: e.message}));
  if (!d || d.err) { box.innerHTML = `<div style="padding:24px" class="mut">${esc((d && d.err) || 'Δεν έχεις πρόσβαση σε αυτό το ticket.')}</div>`; return; }
  const t = d.ticket;
  const md = window.CNP.mdToHtml || (x => esc(x || ''));
  const msgs = (d.conv || []).slice(-6);
  const canReply = S.boot.me.canReply;
  const open = t.status !== 'Closed';
  box.innerHTML = `
  <div class="qr-h">
    <span class="qr-ic">${I.ticket}</span>
    <div class="qr-hn"><b>#${esc(t.tid)} — ${esc(t.title)}</b>
      <span class="mut">${esc(t.client || '')}${t.status ? ' · ' + esc(t.status) : ''}${t.slaDue ? ' · SLA ' + esc(tShort(t.slaDue)) : ''}</span></div>
    <button class="btn btn-sm btn-o" id="qrFull" title="Όλα τα εργαλεία: κατηγορία, remote, συνημμένα, προτάσεις">Άνοιξε ολόκληρο →</button>
    <button class="btn btn-sm btn-o qr-x" title="Κλείσιμο">✕</button></div>
  <div class="qr-body" id="qrBody">
    ${(d.conv || []).length > msgs.length ? `<div class="qr-more">δείχνονται τα τελευταία ${msgs.length} από ${d.conv.length} μηνύματα</div>` : ''}
    ${msgs.map(m => `<div class="qr-m${m.admin ? ' me' : ''}">
      <div class="qr-m-h">${esc(m.by || '—')}${m.admin ? ' <span class="pill pill-info" style="font-size:9px">team</span>' : ''}
        <span class="mut">${esc(tShort(m.at))}</span></div>
      <div class="qr-m-b md">${md(m.body)}</div></div>`).join('') || '<div class="mut" style="padding:10px">Καμία συνομιλία ακόμη.</div>'}
  </div>
  ${canReply ? `<div class="qr-a">
    <textarea class="inp" id="qrTxt" rows="3" placeholder="Γράψε την απάντηση στον πελάτη…  (Ctrl+Enter στέλνει)"></textarea>
    <div class="qr-btns">
      <label class="mut qr-lbl" title="Η απάντηση φεύγει ως «Support Team» — εσωτερικά καταγράφεται ποιος έγραψε"><input type="checkbox" id="qrAlias"> ως Support Team</label>
      <span style="flex:1"></span>
      ${open ? '<button class="btn btn-sm btn-o" id="qrSendClose">Απάντηση & κλείσιμο</button>' : ''}
      <button class="btn btn-sm btn-p" id="qrSend">${I.send} Απάντηση</button></div></div>`
    : '<div class="qr-a mut" style="font-size:12.5px">Δεν έχεις δικαίωμα απάντησης σε πελάτη — χρησιμοποίησε εσωτερική σημείωση από την πλήρη οθόνη.</div>'}`;

  const bd = box.querySelector('#qrBody'); if (bd) { bd.scrollTop = bd.scrollHeight; }
  box.querySelector('.qr-x').onclick = close;
  box.querySelector('#qrFull').onclick = () => { close(); go('inbox', id); };
  const tx = box.querySelector('#qrTxt');
  const send = async closeIt => {
    const body = (tx.value || '').trim();
    if (!body) { tx.focus(); return; }
    const pl = {ticket: id, body, alias: box.querySelector('#qrAlias') && box.querySelector('#qrAlias').checked ? 1 : 0};
    if (closeIt) { pl.status = 'Closed'; }
    const r = await api('ticket_reply', pl).catch(e => ({err: e.message}));
    if (r && r.err) { toast(r.err, true); return; }
    toast(closeIt ? '✔ Απαντήθηκε και έκλεισε' : '✔ Στάλθηκε');
    close();
    if (after) { after(); }
  };
  if (tx) {
    tx.focus();
    tx.onkeydown = e => { if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); send(false); } };
  }
  { const b = box.querySelector('#qrSend'); if (b) { b.onclick = () => send(false); } }
  { const b = box.querySelector('#qrSendClose'); if (b) { b.onclick = () => send(true); } }
}

/** ❓ Αίτημα («σε ζητούν»): το ζητούμενο, το νήμα, η απάντηση — επί τόπου. */
async function openRequestQuick(id, after) {
  const {box, close} = qrShell();
  const d = await api('request_get&id=' + id).catch(e => ({err: e.message}));
  if (!d || d.err) { box.innerHTML = `<div style="padding:24px" class="mut">${esc((d && d.err) || 'Δεν βρέθηκε το αίτημα.')}</div>`; return; }
  const q = d.req;
  const canReply = q.forMe || q.mine;
  box.innerHTML = `
  <div class="qr-h">
    <span class="qr-ic">${esc(q.icon)}</span>
    <div class="qr-hn"><b>${esc(q.kindLbl)} — ${esc(q.forMe ? q.from : 'προς ' + q.to)}</b>
      <span class="mut">${esc(tShort(q.at))}${q.taskTitle ? ' · #' + q.taskId + ' ' + esc(q.taskTitle) : ''}${q.projectName ? ' · ' + esc(q.projectName) : ''}</span></div>
    ${q.status === 'done' ? '<span class="pill pill-ok">τακτοποιήθηκε</span>' : '<span class="pill pill-warn">ανοιχτό</span>'}
    <button class="btn btn-sm btn-o qr-x" title="Κλείσιμο">✕</button></div>
  <div class="qr-body" id="qrBody">
    <div class="qr-q">${esc(q.message)}</div>
    ${(d.msgs || []).map(m => `<div class="qr-m${m.mine ? ' me' : ''}">
      <div class="qr-m-h">${esc(m.byName)}<span class="mut">${esc(tShort(m.at))}</span></div>
      <div class="qr-m-b">${esc(m.body)}</div></div>`).join('')}
  </div>
  ${canReply ? `<div class="qr-a">
    <textarea class="inp" id="qrTxt" rows="3" placeholder="Γράψε την απάντησή σου…  (Ctrl+Enter στέλνει)"></textarea>
    <div class="qr-btns">
      ${q.forMe && q.status === 'open' ? '<label class="mut qr-lbl"><input type="checkbox" id="qrKeep"> κράτα το ανοιχτό</label>' : ''}
      <span style="flex:1"></span>
      ${q.taskId ? `<button class="btn btn-sm btn-o" id="qrTask">Άνοιξε την εργασία</button>` : ''}
      ${q.status === 'open' ? '<button class="btn btn-sm btn-o" id="qrDone">✓ Τακτοποιήθηκε</button>' : ''}
      <button class="btn btn-sm btn-p" id="qrSend">${I.send} Απάντηση</button></div></div>` : ''}`;

  const bd = box.querySelector('#qrBody'); if (bd) { bd.scrollTop = bd.scrollHeight; }
  box.querySelector('.qr-x').onclick = close;
  const tx = box.querySelector('#qrTxt');
  const send = async () => {
    const body = (tx.value || '').trim();
    if (!body) { tx.focus(); return; }
    const keep = box.querySelector('#qrKeep');
    const r = await api('help_reply', {id: q.id, body, keepOpen: keep && keep.checked ? 1 : 0}).catch(e => ({err: e.message}));
    if (r && r.err) { toast(r.err, true); return; }
    toast('✔ Στάλθηκε'); close(); if (after) { after(); }
  };
  if (tx) { tx.focus(); tx.onkeydown = e => { if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); send(); } }; }
  { const b = box.querySelector('#qrSend'); if (b) { b.onclick = send; } }
  { const b = box.querySelector('#qrTask'); if (b) { b.onclick = () => { close(); openTask(q.taskId); }; } }
  { const b = box.querySelector('#qrDone'); if (b) { b.onclick = async () => {
      await api('help_done', {id: q.id}).catch(() => {});
      toast('Τακτοποιήθηκε'); close(); if (after) { after(); } }; } }
}

/* ═════════ 👤 Η μέρα ενός ανθρώπου — pop-up απόφασης (22/9/2026) ═════════
   Ανοίγει από τον κύκλο της μπάρας παρουσίας. Μία οθόνη, χωρίς πλοήγηση:
   τι κάνει τώρα, πώς πάει η μέρα του, τι κρατάει ανοιχτό και τι μπορείς να κάνεις
   γι' αυτό επί τόπου. Ο καταγεγραμμένος χρόνος μπαίνει ΔΙΠΛΑ στον συνδεδεμένο:
   μόνος του δεν ξεχωρίζει «δεν δούλεψε» από «δούλεψε χωρίς να πατήσει χρονόμετρο». */
async function openTeamPulse(id) {
  const ovl = document.createElement('div');
  ovl.className = 'ovl ovl-keep show';
  ovl.style.zIndex = cnpTopZ() + 10;
  ovl.innerHTML = '<div class="pal-box tp-box"><div class="tp-load"><div class="skel" style="height:180px"></div></div></div>';
  document.body.appendChild(ovl);
  const close = () => { ovl.remove(); document.removeEventListener('keydown', onKey); };
  const onKey = e => { if (e.key === 'Escape') { e.stopPropagation(); close(); } };
  document.addEventListener('keydown', onKey);
  ovl.onclick = e => { if (e.target === ovl) { close(); } };

  const d = await api('team_pulse&id=' + id).catch(e => ({err: e.message}));
  const box = ovl.querySelector('.tp-box');
  if (!d || d.err) { box.innerHTML = `<div style="padding:24px"><div class="mut">${esc((d && d.err) || 'Δεν φόρτωσε.')}</div></div>`; return; }

  const w = d.who, t = d.today, l = d.load, wk = d.week;
  const hmm = at => at ? new Date(String(at).replace(' ', 'T')).toLocaleTimeString('el-GR', {hour: '2-digit', minute: '2-digit', hour12: false}) : '—';
  /* Καταγραφή = πόσο από τον χρόνο που ήταν μέσα έχει χρονόμετρο από πίσω —
     μετράει ΤΗΝ ΚΑΤΑΓΡΑΦΗ, όχι την αξία της δουλειάς. Κάτω από 30' παρουσία δεν λέει τίποτα. */
  /* ΚΑΛΥΨΗ = καταγραφή + ΤΗΛΕΦΩΝΟ, πάνω στον χρόνο μέσα στο εργαλείο.
     Ο χρόνος ομιλίας είναι δουλειά που κανένα χρονόμετρο δεν πιάνει — κανείς
     δεν σταματά να πατήσει «έναρξη» επειδή χτύπησε το τηλέφωνο. Χωρίς αυτόν,
     όποιος σηκώνει τα τηλέφωνα φαινόταν να μην καταγράφει τίποτα. */
  const acc = (t.logged || 0) + (t.phone || 0);
  const cap = t.conn >= 30 ? Math.min(100, Math.round(acc / t.conn * 100)) : null;
  const capC = cap === null ? 'var(--mut)' : cap >= 70 ? 'var(--ok)' : cap >= 40 ? 'var(--warn)' : 'var(--bad)';
  const kpi = (n, lbl, col, tip) => ({n, label: lbl, color: col, tip});
  const taskLine = x => `<div class="tp-row" data-tptask="${x.id}">
    <span class="dot" style="background:${esc(x.pcolor)}"></span>
    <span class="tp-t"><b>${esc(x.title)}</b><span class="mut"> · ${esc(x.pname || 'Χωρίς έργο')}</span></span>
    ${x.due ? `<span class="pill ${x.late ? 'pill-bad' : 'pill-mut'}">${esc(dShort(x.due))}</span>` : '<span class="pill pill-warn">χωρίς ημ/νία</span>'}</div>`;

  box.innerHTML = `
  <div class="tp-h">
    <span class="act-ava tp-av" style="--sc:${esc(w.color)}">${esc(w.ini)}</span>
    <div class="tp-hn"><b>${esc(w.name)}${w.lead ? ' <i class="tm-lead" title="Επικεφαλής ομάδας">★</i>' : ''}</b>
      <span class="mut">${esc([w.team, w.role].filter(Boolean).join(' · ') || '—')}</span>
      <span class="tp-st" style="color:${esc(w.color)}">● ${esc(w.label)}${w.hint ? ' · ' + esc(w.hint) : ''}${t.since ? ' · μέσα από ' + hmm(t.since) : ''}</span></div>
    <button class="btn btn-sm btn-o tp-x" title="Κλείσιμο">✕</button></div>

  <div class="tp-now ${d.now ? 'live' : 'idle'}">
    ${d.now ? `<span class="tp-now-k">▶ Τώρα</span><b data-tptask="${d.now.id}">${esc(d.now.title)}</b><span class="tp-now-e">${esc(fmtMin(d.now.mins))}</span>`
      : `<span class="tp-now-k">Δεν τρέχει χρονόμετρο</span><span class="mut">${
        t.logged ? 'έχει καταγράψει ' + esc(fmtMin(t.logged)) + ' σήμερα' : 'καμία καταγραφή χρόνου σήμερα'
        }${t.phone ? ' · ' + esc(fmtMin(t.phone)) + ' στο τηλέφωνο' : ''}</span>`}</div>

  <div class="tp-kwrap">${cnpKpis([
    kpi(esc(fmtMin(t.logged)), 'καταγεγραμμένος<br>χρόνος', null, 'Όσο χρόνο έχει χρεώσει σε εργασίες σήμερα'),
    kpi(esc(fmtMin(t.phone || 0)), 'στο<br>τηλέφωνο', (t.phone ? 'var(--brand)' : null),
      'Χρόνος ΟΜΙΛΙΑΣ από το τηλεφωνικό κέντρο — χωρίς κουδούνισμα και αναμονή. Μετριέται μόνος του.'),
    kpi(esc(fmtMin(t.conn)), 'μέσα στο<br>εργαλείο', null, 'Από την πρώτη ως την τελευταία κίνηση σήμερα'),
    kpi(cap === null ? '—' : cap + '%', 'καλυμμένος<br>χρόνος', capC,
      'Καταγεγραμμένος χρόνος ΚΑΙ ομιλία στο τηλέφωνο, πάνω στον χρόνο που ήταν μέσα. Μετράει τι είναι εξηγημένο, όχι την αξία της δουλειάς.'),
    kpi(t.done, 'ολοκληρώθηκαν<br>σήμερα', t.done ? 'var(--ok)' : null, 'Εργασίες που έκλεισε σήμερα')])}</div>

  <div class="tp-sec"><div class="tp-lbl">Η εβδομάδα — ${esc(fmtMin(wk.logged))} καταγραφή${
      wk.phone ? ' · <span class="tp-ph">' + esc(fmtMin(wk.phone)) + ' στο τηλέφωνο</span>' : ''
    } · ${wk.done} ${wk.done === 1 ? 'ολοκλήρωση' : 'ολοκληρώσεις'}</div>
    ${cnpSpark(wk.days.map(x => ({...x, mins: (x.mins || 0) + (x.phone || 0)})))}
    ${wk.phone ? '<div class="mut" style="font-size:11px;margin-top:4px">Οι στήλες δείχνουν καταγραφή <b>και</b> ομιλία μαζί.</div>' : ''}</div>

  <div class="tp-sec"><div class="tp-lbl">Τι κρατάει ανοιχτό</div>
    <div class="tp-chips">
      <span class="pill pill-mut">${l.open} ανοιχτές</span>
      ${l.overdue ? `<span class="pill pill-bad">${l.overdue} εκπρόθεσμες</span>` : ''}
      ${l.dueToday ? `<span class="pill pill-warn">${l.dueToday} λήγουν σήμερα</span>` : ''}
      ${l.ball ? `<span class="pill pill-info">${l.ball} με τη μπάλα</span>` : ''}
      ${l.noEstimate ? `<span class="pill pill-mut" title="Χωρίς εκτίμηση δεν μπορείς να προβλέψεις τη μέρα του">${l.noEstimate} χωρίς εκτίμηση</span>` : ''}
    </div>
    ${(l.tasks || []).length ? `<div class="tp-list">${l.tasks.map(taskLine).join('')}</div>` : '<div class="mut" style="padding:8px 2px;font-size:12.5px">Καμία ανοιχτή εργασία.</div>'}</div>

  ${(t.events || []).length ? `<div class="tp-sec"><div class="tp-lbl">Το πρόγραμμα του σήμερα${t.meetMins ? ' — ' + esc(fmtMin(t.meetMins)) + ' σε συσκέψεις' : ''}</div>
    <div class="tp-list">${t.events.map(e => `<div class="tp-row${e.now ? ' now' : ''}">
      <span class="tp-time">${e.allDay ? 'όλη μέρα' : hmm(e.start) + '–' + hmm(e.end)}</span>
      <span class="tp-t"><b>${esc(e.title)}</b></span>
      ${e.now ? '<span class="pill pill-ok">τώρα</span>' : ''}</div>`).join('')}</div></div>` : ''}

  ${(l.tickets || []).length ? `<div class="tp-sec"><div class="tp-lbl">Tickets που τον περιμένουν</div>
    <div class="tp-list">${l.tickets.map(k => `<div class="tp-row" data-tptk="${k.id}">
      <span class="tp-t"><b>#${esc(k.tid)} ${esc(k.title)}</b><span class="mut"> · ${esc(k.status)}</span></span>
      ${k.days !== null ? `<span class="pill ${k.days >= 2 ? 'pill-warn' : 'pill-mut'}">${k.days} ημ.</span>` : ''}</div>`).join('')}</div></div>` : ''}

  ${(t.doneList || []).length ? `<div class="tp-sec"><div class="tp-lbl">Έκλεισε σήμερα</div>
    <div class="tp-list">${t.doneList.map(x => `<div class="tp-row done" data-tptask="${x.id}"><span class="tp-t">✔ ${esc(x.title)}</span></div>`).join('')}</div></div>` : ''}

  <div class="tp-a">
    ${d.canAsk ? '<button class="btn btn-sm btn-p" id="tpAsk">Ρώτα τι γίνεται</button>' : ''}
    <button class="btn btn-sm btn-o" id="tpChat">Μήνυμα</button>
    <span style="flex:1"></span>
    <button class="btn btn-sm btn-o" id="tpAct">Δραστηριότητα →</button></div>`;

  box.querySelector('.tp-x').onclick = close;
  box.querySelectorAll('[data-tptask]').forEach(r => r.onclick = () => { close(); openTask(+r.dataset.tptask); });
  box.querySelectorAll('[data-tptk]').forEach(r => r.onclick = () => { close(); openTicketQuick(+r.dataset.tptk); });
  { const b = box.querySelector('#tpChat'); if (b) { b.onclick = () => { close(); go('chat'); }; } }
  { const b = box.querySelector('#tpAct'); if (b) { b.onclick = () => { close(); go('activity'); }; } }
  { const b = box.querySelector('#tpAsk'); if (b) { b.onclick = async () => {
      const msg = await cnpDialog({title: 'Ρώτα τον ' + String(w.name).split(' ')[0],
        body: 'Θα το δει στα «σε ζητούν» και η απάντησή του έρχεται πίσω στα αιτήματα.',
        input: 'Πώς πάει η μέρα σου; Χρειάζεσαι κάτι;', rows: 3, max: 400, ok: 'Στείλε', cancel: 'Άκυρο'});
      if (msg === null || msg === false || !String(msg).trim()) { return; }
      const r = await api('team_ask', {id: w.id, message: String(msg).trim()}).catch(e => ({err: e.message}));
      if (r && r.err) { toast(r.err, true); return; }
      toast('Η ερώτηση στάλθηκε'); close();
    }; } }
}

window.CNP = {S, api, esc, timeInput, cnpTimeNorm, cnpBalanced, billingQueue, palette: cnpPalette, cnpDenied, cnpCan, sideTipHide, askDone, dFull, cnpSetDate, suStat, rteHtml, rteVal, fmtMin, fmtEur, dShort, tShort, today, toast, setTop, go, crmTabs, openLead, cnpConfirm, cnpPrompt, cnpDialog, startRemote,
  adminName, adminIni, statusOf, stPill, stDot, doneStatus, typeOf, dnd, I, openTask, closeDrawer, updateBell, miniMenu,
  statusPicker, setStatusUI, CNP_ST, cnpStDef, meetPop, timerCheckPop, openTeamPulse,
  cnpKpis, cnpSpark, cnpPeopleBar, cnpDayStrip, cnpWireDash, cnpLastLbl,
  openTicketQuick, openRequestQuick, cnpKeyNav, cnpKeyHelp, mydLayoutDialog,
  cnpMsgHtml, cnpWireMsgLinks, cnpSearch, cnpSkel,
  fChip, fSel, fBool, fOne, fAdd, fWire, cnpIsMine, cnpHolder, chatBeep, $, $$};

/* ───────── init ───────── */
(async function init() {
  try {
    S.boot = await api('boot');
    // γλώσσα από το προφίλ του χρήστη (ισχύει σε κάθε συσκευή)
    if (window.CNP_I18N && S.boot.me && S.boot.me.lang && S.boot.me.lang !== window.CNP_I18N.get()) {
      window.CNP_I18N.set(S.boot.me.lang);   // αποθηκεύει + reload μία φορά
      return;
    }
  } catch (e) {
    $('#app').innerHTML = '<div class="boot"><div class="boot-logo">P</div><div class="boot-txt">Σύνδεση…</div></div>';
    return;
  }
  renderShell();
  /* Η παράμετρος ΔΕΝ είναι πάντα αριθμός: η «Κίνηση πελάτη» λέει αν μιλάμε για
     πελάτη WHMCS ή για καρτέλα καταλόγου (c212 / b447). Με μόνο ψηφία, ο
     σύνδεσμος έχανε το όρισμα και άνοιγε άδεια οθόνη. */
  const m = location.hash.match(/^#\/(\w+)(?:\/(\w+))?/);
  if (m && m[1] === 'task' && m[2]) {          // deep-link από email/παλιά URLs
    const em0 = location.hash.match(/\/e\/(\d+)/);   // …/e/45 = συγκεκριμένη ενέργεια
    go('myday');
    openTask(+m[2], em0 ? +em0[1] : 0);
  } else {
    go(m ? m[1] : 'myday', m ? m[2] : undefined);
  }
  window.addEventListener('hashchange', () => {
    const h = location.hash.match(/^#\/(\w+)(?:\/(\w+))?/);
    if (!h) { return; }
    /* «task» δεν είναι οθόνη — είναι καρτέλα. Χωρίς αυτό, ένα #/task/119 από
       ειδοποίηση ή μήνυμα δεν άνοιγε τίποτα μέχρι να κάνεις refresh. */
    if (h[1] === 'task' && h[2]) { const em1 = location.hash.match(/\/e\/(\d+)/); openTask(+h[2], em1 ? +em1[1] : 0); return; }
    /* Και ίδια οθόνη με άλλο id είναι νέα πλοήγηση (πελάτης → έργο → τμήμα). */
    if (h[1] !== S.view || (h[2] || '') !== (S.viewArg || '')) {
      /* Πλοήγηση σε άλλη οθόνη (π.χ. tab bar στο κινητό) ενώ είναι ανοιχτή καρτέλα χωρίς αλλαγές:
         η καρτέλα κλείνει — αλλιώς έμενε πάνω από τη νέα οθόνη. Με αλλαγές μένει, να μη χαθούν. */
      const od = document.querySelector('.drawer.show');
      if (od && od.dataset.dirty !== '1' && od.dataset.fresh !== '1') { closeDrawer(); }
      go(h[1], h[2]);
    }
  });
  document.getElementById('remoteChip').onclick = stopRemote;
  remoteRefresh();
  // Web Push: αν έχει ήδη δοθεί άδεια, ξανα-συγχρόνισε τη συνδρομή αυτής της συσκευής
  cnpPushInit();
  // tap σε push → πλοήγηση μέσα στην ήδη ανοιχτή εφαρμογή
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.addEventListener('message', e => {
      if (e.data && e.data.type === 'cnp-nav' && e.data.url) {
        const h = String(e.data.url).split('#')[1];
        if (h) { location.hash = h.startsWith('/') ? h : '/' + h; }
        try { window.focus(); } catch (x) {}
      }
    });
  }
  /* 🔄 Αυτόματος έλεγχος έκδοσης: όταν γίνει deploy νεότερο app, ο browser το
     καταλαβαίνει μόνος του και φορτώνει το φρέσκο — τέλος στα «βλέπω ακόμη το
     παλιό» λόγω cache. Δεν διακόπτουμε τον χρήστη ενώ γράφει ή με ανοιχτό πάνελ. */
  let cnpNewBuild = false;
  const cnpUpdBanner = window.cnpUpdBanner = () => {
    if (document.getElementById('cnpUpd')) { return; }
    const b = document.createElement('div');
    b.id = 'cnpUpd';
    b.style.cssText = 'position:fixed;left:50%;bottom:18px;transform:translateX(-50%);z-index:99999;'
      + 'background:#131e33;color:#fff;padding:10px 15px;border-radius:12px;font-size:13px;font-weight:600;'
      + 'box-shadow:0 12px 34px rgba(0,0,0,.4);display:flex;gap:11px;align-items:center';
    const btn = document.createElement('button');
    btn.textContent = 'Ανανέωση τώρα';
    btn.style.cssText = 'background:#0090dd;color:#fff;border:0;border-radius:8px;padding:6px 12px;'
      + 'font-weight:700;cursor:pointer;font-size:12.5px';
    btn.onclick = () => location.reload();
    b.append(document.createTextNode('🔄 Νέα έκδοση διαθέσιμη '), btn);
    document.body.appendChild(b);
  };
  const cnpMaybeReload = () => {
    cnpUpdBanner();
    const tag = ((document.activeElement || {}).tagName || '').toLowerCase();
    const busy = tag === 'input' || tag === 'textarea'
      || document.querySelector('.drawer.show') || document.querySelector('.ovl.show');
    if (!busy) { try { location.reload(); } catch (e) { location.href = location.href; } }
  };
  window.addEventListener('focus', () => { if (cnpNewBuild) { cnpMaybeReload(); } });
  // realtime: version polling ανά 12" → σιωπηλό refresh όταν αλλάξει κάτι από συναδέλφους
  let lastV = null;
  setInterval(async () => {
    try {
      const d = await api('version' + (window._cnpChatSeen ? '&chatSince=' + window._cnpChatSeen : ''));
      updateBell(d.unread + (d.pending || 0));
      /* το chip «σε ζητούν» ακολουθεί ζωντανά, χωρίς να ξαναφορτώνει όλα τα stats */
      { const nc = document.querySelector('#topPulse [data-pgo="needs"]');
        if (nc) { const n = d.pending || 0; nc.querySelector('.n').textContent = n; nc.classList.toggle('hot', n > 0); nc.classList.toggle('zero', !n); } }
      /* Μήνυμα chat ενώ δουλεύεις αλλού: το καμπανάκι δεν αρκεί. Βγάζουμε
         κάρτα με τον αποστολέα και το κείμενο, με ήχο και αναβοσβήνει ο τίτλος
         της καρτέλας — ώστε να το δεις ακόμη κι αν κοιτάς άλλο παράθυρο.
         Όταν ΕΙΣΑΙ στο chat δεν χρειάζεται: το βλέπεις ήδη. */
      if (Array.isArray(d.chatNew) && d.chatNew.length) {
        /* ΜΟΝΟ ΤΑ ΟΝΤΩΣ ΚΑΙΝΟΥΡΓΙΑ. Ο server στέλνει ό,τι δεν έχεις διαβάσει,
           άρα το ίδιο αδιάβαστο μήνυμα ξαναέρχεται σε κάθε σφυγμό. Χωρίς αυτό,
           αν έμενες στο chat σε άλλο κανάλι, θα χτυπούσε κάθε λίγα δευτερόλεπτα
           για το ίδιο πράγμα — και θα έκλεινες τον ήχο. */
        const seen = window._cnpChatSeen || 0;
        const fresh = d.chatNew.filter(m => m.id > seen);
        d.chatNew.forEach(m => { if (m.id > (window._cnpChatSeen || 0)) { window._cnpChatSeen = m.id; } });
        if (fresh.length) {
          if (S.view !== 'chat') {
            fresh.forEach(chatPop);            // κάρτα + ήχος + τίτλος που αναβοσβήνει
          } else {
            /* ΚΑΙ ΜΕΣΑ ΣΤΟ CHAT. Το ότι η οθόνη είναι ανοιχτή δεν σημαίνει ότι
               την κοιτάς — μπορεί να είσαι σε άλλο κανάλι ή σε άλλο παράθυρο.
               Κάθε κοινό chat χτυπά. Μόνο ήχος: το μήνυμα φαίνεται ήδη στη
               συζήτηση, η κάρτα θα ήταν διπλή. */
            chatBeep();
          }
        }
      }
      // Νεότερη έκδοση deployed → ανανέωση (ήπια, όταν δεν ενοχλεί).
      if (d.build && window.CNP_BUILD && d.build !== window.CNP_BUILD) { cnpNewBuild = true; }
      if (cnpNewBuild) { cnpMaybeReload(); }
      // 🆘 δυνατές εκκλήσεις βοήθειας — «κάνουν μπαμ» ό,τι κι αν κάνει ο χρήστης
      if (Array.isArray(d.alerts) && window.CNP.showHelpAlert) { d.alerts.forEach(a => window.CNP.showHelpAlert(a)); }
      // 📅 προσκλήσεις σε σύσκεψη & υπενθύμιση πριν την έναρξη
      if (Array.isArray(d.meetAlerts)) { d.meetAlerts.forEach(meetPop); }
      /* 🌞 Το καλωσόρισμα του πρωινού. Έρχεται από τον σφυγμό, ώστε όποιος μπήκε
         πριν το ωράριό του να πάρει τη μέρα του ΣΤΗΝ ΩΡΑ ΤΟΥ, χωρίς να χρειαστεί
         να ανοίξει ή να ανανεώσει κάτι. */
      if (d.greet) { cnpGreet(d.greet); }
      // ⏱ «τρέχει πολλή ώρα — ακόμα δουλεύεις;»
      if (d.myTimer) { timerCheckPop(d.myTimer); }
      /* 💬 μετρητής στην πάνω μπάρα: το chat είναι θαμμένο στο πλάι μέσα σε
         ενότητα που μπορεί να είναι κλειστή — εκεί δεν το βλέπεις ποτέ. */
      { const cb = $('#chatN');
        if (cb) {
          cb.style.display = d.chatUnread > 0 ? '' : 'none';
          cb.textContent = d.chatUnread > 99 ? '99+' : d.chatUnread;
          const btn = $('#chatBtn');
          if (btn) { btn.classList.toggle('has-new', d.chatUnread > 0); }
        } }
      // 💬 badge στο Chat nav item
      const chatItem = document.querySelector('.sitem[data-nav="chat"]');
      if (chatItem) {
        let b = chatItem.querySelector('.chat-n');
        if (d.chatUnread > 0) {
          if (!b) { b = document.createElement('span'); b.className = 'chat-n'; chatItem.appendChild(b); }
          b.textContent = d.chatUnread > 99 ? '99+' : d.chatUnread;
        } else if (b) { b.remove(); }
      }
      if (lastV === null) { lastV = d.v; return; }
      if (d.v === lastV) return;
      lastV = d.v;
      const tag = (document.activeElement?.tagName || '').toLowerCase();
      if (tag === 'input' || tag === 'textarea') return;   // μην ενοχλείς ενώ γράφει
      if (document.querySelector('.drawer')) return;        // ούτε με ανοιχτό πάνελ
      if (['board', 'myday', 'list', 'crm', 'kpi', 'inbox'].includes(S.view) && window.R[S.view]) {
        window.R[S.view]();
      }
    } catch (e) {}
  }, 12000);
  document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') { return; }
    if (document.querySelector('.pal-box, .help-ovl, #miniMenu')) { return; }   // διάλογοι/μενού έχουν δικό τους Esc
    const fd = document.querySelector('.drawer.show');
    if (fd && fd._askClose) { fd._askClose(); return; }
    if (fd && fd.dataset.dirty === '1') { cnpAskClose(fd); return; }
    closeDrawer();
  });
})();
