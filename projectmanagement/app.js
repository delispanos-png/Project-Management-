/* ═══════════ CloudOn Projects — standalone SPA ═══════════ */
'use strict';

const $ = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => [...r.querySelectorAll(s)];
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const fmtMin = m => { m = +m || 0; const h = Math.floor(m / 60), r = m % 60; return h && r ? `${h}ω ${r}΄` : h ? `${h}ω` : `${r}΄`; };
const fmtEur = v => (+v || 0).toLocaleString((window.CNP_LOCALE||'el-GR'), {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' €';
const dShort = d => d ? new Date(d.replace(' ', 'T')).toLocaleDateString((window.CNP_LOCALE||'el-GR'), {day: '2-digit', month: '2-digit'}) : '';
const tShort = d => d ? new Date(d.replace(' ', 'T')).toLocaleString((window.CNP_LOCALE||'el-GR'), {day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit'}) : '';
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

async function api(a, data) {
  const opt = data ? {method: 'POST', body: JSON.stringify(data), headers: {'Content-Type': 'application/json'}} : {};
  const r = await fetch('api.php?a=' + a + (data ? '' : '&_=' + Date.now()), {credentials: 'same-origin', ...opt});
  if (r.status === 401) { location.href = '/cloudonadminpanel/addonmodules.php?module=cloudonprojects&pmlaunch=1'; throw new Error('auth'); }
  const j = await r.json();
  if (j.error) throw new Error(j.error);
  return j;
}
function toast(msg, err) {
  let w = $('#toasts'); if (!w) { w = document.createElement('div'); w.id = 'toasts'; document.body.appendChild(w); }
  const t = document.createElement('div'); t.className = 'toast' + (err ? ' err' : '');
  t.innerHTML = (err ? '⚠️ ' : '✓ ') + esc(msg); w.appendChild(t);
  setTimeout(() => { t.style.opacity = 0; t.style.transition = 'opacity .3s'; setTimeout(() => t.remove(), 320); }, 2600);
}
const adminName = id => (S.boot.admins.find(a => a.id === +id) || {}).name || '—';
const adminIni = id => (S.boot.admins.find(a => a.id === +id) || {}).ini || '';
const statusOf = id => S.boot.statuses.find(s => s.id === +id) || {};
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

function renderShell() {
  const me = S.boot.me;
  const has = a => me.full || (me.areas || []).includes(a);   // ειδικότητες/πρόσβαση
  const nav = [
    ['Εργασία', [['myday', I.sun, 'Η μέρα μου'], ['todos', I.checkSquare, 'Το πλάνο μου'], ['library', I.book, 'Η βιβλιοθήκη μου'],
      ['vault', I.key, 'Κωδικοί'], ['standup', I.clipboard, 'Standup'], ['calendar', I.cal, 'Ημερολόγιο'], ['chat', I.chat || I.ticket, 'Chat'], ['remotebook', I.monitor, 'Απομακρυσμένες']]],
  ];
  if (has('support')) {
    nav.push(['Υποστήριξη', [['inbox', I.ticket, 'Tickets'], ['knowledge', I.book, 'Γνώση'], ['client360', I.user, 'Πελάτης 360°']]]);
  }
  if (has('projects')) {
    nav.push(['Έργα', [['board', I.board, 'Board'], ['gantt', I.gantt, 'Gantt'], ['list', I.list, 'Λίστα tasks'],
      ['time', I.clock, 'Χρόνος'], ['projects', I.folder, 'Projects'], ['units', I.tree, 'Departments'], ['templates', I.box, 'Modules']]]);
  }
  if (has('sales')) {
    nav.push(['Πωλήσεις', [['crm', I.target, 'CRM'], ['offers', I.doc, 'Προσφορές']]]);
  }
  /* Η «Διοίκηση» ήταν ένα ενιαίο κύκλωμα. Σπασμένη σε τρία, κάθε ενότητα
     εμφανίζεται με το δικό της δικαίωμα — ώστε ένας project manager να παίρνει
     αναφορές χωρίς οικονομικά και χωρίς ρυθμίσεις. */
  const admItems = []
    .concat(has('reports') ? [['triage', I.flag, 'Πλάνο ημέρας'], ['rootcause', I.chart, 'Ανάλυση ριζών'],
      ['kpi', I.chart, 'KPI Dashboard'], ['perf', I.chart, 'Απόδοση']] : [])
    .concat(has('finance') ? [['profit', I.coin, 'Κερδοφορία'],
      ['paytrace', I.search || I.coin, 'Συμφωνία πληρωμών'], ['suspend', I.alert, 'Αναστολές']] : [])
    .concat(has('admin') ? [['teams', I.tree, 'Ομάδες'], ['settings', I.gear, 'Ρυθμίσεις']] : []);
  if (admItems.length) { nav.push(['Διοίκηση', admItems]); }
  if (has('hr')) {
    nav.push(['Προσλήψεις', [['recruit', I.contact || I.users, 'Βιογραφικά']]]);
  }
  nav.push(['Βοήθεια', [['help', I.bulb, 'Οδηγός χρήσης']]]);
  $('#app').innerHTML = `
  <div class="shell${(localStorage.cnpSideCollapsed === '1' && !matchMedia('(max-width:768px)').matches) ? ' collapsed' : ''}">
    <aside class="side">
      <div class="brand"><div class="brand-ico">P</div>
        <div class="brand-t">Cloudon<b>Projects</b><small>Project Manager</small></div></div>
      ${(() => {
        let openSet = null;
        if (localStorage.cnpNavOpen) { try { openSet = new Set(JSON.parse(localStorage.cnpNavOpen)); } catch (e) {} }
        return nav.map(([g, items]) => {
          const hasActive = items.some(([k]) => k === S.view);
          const open = openSet ? openSet.has(g) : hasActive;   // default: ανοιχτή η ενότητα του τρέχοντος view
          return `<div class="snav-grp ${open ? 'open' : ''}">
            <button class="sgroup" data-grptoggle="${esc(g)}">${esc(g)}
              <svg class="chev" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M6 9l6 6 6-6"/></svg></button>
            <div class="snav-items">${items.map(([k, ic, lb]) => `<button class="sitem" data-nav="${k}" data-lb="${esc(lb)}">${ic}<span>${lb}</span></button>`).join('')}</div>
          </div>`;
        }).join('');
      })()}
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
          <button class="btn btn-o btn-ico" id="helpBtn" title="Βοήθεια για αυτή την οθόνη">${I.bulb}</button>
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
        library: 'Βιβλιοθήκη', vault: 'Κωδικοί', remotebook: 'Απομακρ.', client360: 'Πελάτης',
        knowledge: 'Γνώση', list: 'Tasks', projects: 'Projects', offers: 'Προσφορές',
        triage: 'Πλάνο ημ.', rootcause: 'Ρίζες', kpi: 'KPI', profit: 'Κέρδη',
        units: 'Depts', templates: 'Modules', teams: 'Ομάδες', perf: 'Απόδοση', suspend: 'Αναστολές', settings: 'Ρυθμίσεις', recruit: 'Βιογραφικά', help: 'Οδηγός'};
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
  $$('[data-grptoggle]').forEach(b => b.onclick = () => {
    b.closest('.snav-grp').classList.toggle('open');
    localStorage.cnpNavOpen = JSON.stringify($$('.snav-grp.open').map(x => x.querySelector('.sgroup').dataset.grptoggle));
  });
  $('#sideTgl').onclick = () => {
    const sh = $('.shell'); const col = sh.classList.toggle('collapsed');
    localStorage.cnpSideCollapsed = col ? '1' : '0';
  };
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
  const pb = $('#palBtn'); if (pb) pb.onclick = () => window.CNP.palette && window.CNP.palette();
  // ── Πάνω μενού: «+ Νέο» quick-create ──
  $('#newBtn').onclick = e => {
    e.stopPropagation();
    miniMenu($('#newBtn'), [
      {icon: I.checkSquare, label: 'Νέο task', on: () => window.CNP.quickNew && window.CNP.quickNew()},
      {icon: I.target, label: 'Νέο lead', on: async () => { const d = await api('crm').catch(() => null); openLead(null, d || {stages: [], leads: []}); }},
      {icon: I.phone, label: 'Νέα επικοινωνία', on: () => go('comms')},
      {icon: I.clock, label: 'Καταγραφή χρόνου', on: () => go('time')},
    ]);
  };
  // ── Κατάσταση διαθεσιμότητας ──
  $('#statusBtn').onclick = e => {
    e.stopPropagation();
    const opts = [['online', 'Online', 'var(--ok)', ''], ['meeting', 'Σε meeting', '#e0a020', 'Σε meeting'],
      ['busy', 'Απασχολημένος', '#e0552b', 'Απασχολημένος'], ['offline', 'Offline', '#8291a9', 'Μη διαθέσιμος']];
    miniMenu($('#statusBtn'), opts.map(([k, lbl, col, reason]) => ({dot: col, label: lbl, on: async () => {
      await api('chat_status', {status: k === 'online' ? 'online' : 'offline', reason});
      setStatusUI(k === 'online' ? 'online' : 'offline', lbl, col);
    }})));
  };
  loadTopStats();
  if (!window._cnpTopTimer) { window._cnpTopTimer = setInterval(loadTopStats, 60000); }
  updateBell(S.boot.unread);
}
function setStatusUI(status, lbl, col) {
  const dot = $('#statusDot'), l = $('#statusLbl'); if (!dot) return;
  dot.style.background = status === 'online' ? 'var(--ok)' : (col || '#8291a9');
  l.textContent = status === 'online' ? 'Online' : (lbl || 'Offline');
}
async function loadTopStats() {
  const box = $('#topPulse'); if (!box) return;
  const d = await api('topstats').catch(() => null); if (!d) return;
  const rmap = {'Σε meeting': ['Σε meeting', '#e0a020'], 'Απασχολημένος': ['Απασχολημένος', '#e0552b']};
  if (d.status === 'online') { setStatusUI('online'); }
  else { const r = rmap[d.reason] || [d.reason || 'Offline', '#8291a9']; setStatusUI('offline', r[0], r[1]); }
  const chips = [
    {k: 'inbox', ic: I.ticket, n: d.tickets, lbl: 'tickets', col: '#0097e4'},
    {k: 'inbox', ic: I.alert, n: d.sla, lbl: 'SLA', col: '#e0552b', warn: 1},
    {k: 'myday', ic: I.checkSquare, n: d.today, lbl: 'σήμερα', col: '#e0a020'},
    {k: 'myday', ic: I.zap, n: d.ball, lbl: 'εμένα', col: '#7b5cd6'},
  ];
  box.innerHTML = chips.map(c => `<button class="pulse-chip${c.n && c.warn ? ' hot' : ''}${c.n ? '' : ' zero'}" data-pgo="${c.k}" title="${c.lbl}">
    <span class="pc-ic" style="color:${c.col}">${c.ic}</span><span class="n">${c.n}</span><span class="pc-l">${c.lbl}</span></button>`).join('');
  $$('#topPulse [data-pgo]').forEach(b => b.onclick = () => go(b.dataset.pgo));
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
function updateBell(n) { const b = $('#bellN'); if (!b) return; b.style.display = n ? '' : 'none'; b.textContent = n > 99 ? '99+' : n; }
async function toggleBell() {
  const old = $('.pop'); if (old) { old.remove(); return; }
  const d = await api('notifs'); updateBell(d.unread);
  const pop = document.createElement('div'); pop.className = 'pop';
  pop.innerHTML = `<div class="pop-h">Ειδοποιήσεις <a href="#" id="readAll" style="font-size:11px;font-weight:600">όλα ως διαβασμένα</a></div>` +
    (d.items.length ? d.items.map(n => `<a class="nrow ${n.read ? '' : 'unread'}" data-id="${n.id}" data-url="${esc(n.url || '')}">
      <span>${esc(n.title)}</span><span class="tm">${tShort(n.at)}</span></a>`).join('')
    : '<div class="empty" style="padding:22px">Καμία ειδοποίηση</div>');
  $('.bell-wrap').appendChild(pop);
  pop.onclick = async e => {
    const a = e.target.closest('.nrow'); const ra = e.target.closest('#readAll');
    if (ra) { e.preventDefault(); await api('notif_read', {id: 0}); updateBell(0); toggleBell(); return; }
    if (!a) return; e.preventDefault();
    const r = await api('notif_read', {id: +a.dataset.id}); updateBell(r.unread);
    const url = a.dataset.url;
    const m = url && url.match(/tab=task&id=(\d+)/);
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
  setTop('Απομακρυσμένες', 'Αποθηκευμένες συνδέσεις πελατών — ένα κλικ για σύνδεση');
  const c = $('#content');
  const st = R.remotebook._s = R.remotebook._s || {q: '', form: false, edit: null};
  c.innerHTML = `<div class="grid g4" style="margin-bottom:14px">${'<div class="skel" style="height:56px"></div>'.repeat(4)}</div>
    <div class="skel" style="height:300px"></div>`;
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
  $('#rbQ').oninput = () => { clearTimeout(qt); qt = setTimeout(() => { st.q = $('#rbQ').value.trim(); paint(); }, 200); };
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
  const ed = b.closest('.rte-wrap').querySelector('.rte');
  if (!ed) { return; }
  const cmd = b.dataset.cmd;
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
    document.execCommand('formatBlock', false, '<pre>');
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

// επικόλληση: πάντα ΧΩΡΙΣ μορφοποίηση από Word/σελίδες (αλλιώς μπαίνουν styles/fonts)
document.addEventListener('paste', e => {
  const ed = e.target.closest && e.target.closest('.rte');
  if (!ed) { return; }
  e.preventDefault();
  const t = (e.clipboardData || window.clipboardData).getData('text/plain');
  document.execCommand('insertText', false, t);
}, true);

/* ═══ In-app διαλογικά (αντί για browser confirm/prompt) ═══ */
function cnpDialog(opts) {
  return new Promise(resolve => {
    const o = Object.assign({title: '', body: '', ok: 'OK', cancel: 'Άκυρο', input: null, danger: false}, opts);
    const ovl = document.createElement('div');
    ovl.className = 'ovl show';
    ovl.style.zIndex = 300;
    ovl.innerHTML = `<div class="pal-box" style="margin:22vh auto 0;max-width:440px" role="dialog">
      <div style="padding:20px 22px 18px">
        ${o.title ? `<b style="font-size:15.5px;color:var(--ink)">${o.title}</b>` : ''}
        ${o.body ? `<div style="font-size:13px;color:var(--txt);margin-top:8px;white-space:pre-wrap">${o.body}</div>` : ''}
        ${o.input !== null ? (o.rows
          ? `<textarea class="inp" id="cnpDlgIn" rows="${+o.rows}" maxlength="${+o.max || 2000}" placeholder="${esc(o.placeholder || '')}" style="margin-top:12px;width:100%;resize:vertical">${esc(o.input || '')}</textarea>`
          : `<input class="inp" id="cnpDlgIn" placeholder="${esc(o.placeholder || '')}" value="${esc(o.input || '')}" style="margin-top:12px">`) : ''}
        ${o.hint ? `<div class="mut" style="font-size:11.5px;margin-top:7px">${o.hint}</div>` : ''}
        <div style="display:flex;gap:9px;margin-top:16px;justify-content:flex-end;flex-wrap:wrap">
          <button class="btn btn-o" id="cnpDlgNo">${o.cancel}</button>
          ${o.third ? `<button class="btn btn-o" id="cnpDlgTh"${o.thirdPlain ? '' : ' style="color:var(--bad)"'}>${o.third}</button>` : ''}
          <button class="btn ${o.danger ? '' : 'btn-p'}" id="cnpDlgOk" style="${o.danger ? 'background:var(--bad);color:#fff' : ''}">${o.ok}</button>
        </div>
      </div></div>`;
    document.body.appendChild(ovl);
    const inp = ovl.querySelector('#cnpDlgIn');
    const done = v => { ovl.remove(); document.removeEventListener('keydown', onKey); resolve(v); };
    const ok = () => done(o.input !== null ? (inp ? inp.value : '') : true);
    const onKey = e => {
      if (e.key === 'Escape') { e.stopPropagation(); done(o.input !== null ? null : false); }
      if (e.key === 'Enter' && (!inp || document.activeElement === inp)) {
        if (o.rows && !(e.ctrlKey || e.metaKey)) { return; }   // πολυγραμμικό: Enter = νέα γραμμή
        e.preventDefault(); ok();
      }
    };
    document.addEventListener('keydown', onKey);
    ovl.querySelector('#cnpDlgOk').onclick = ok;
    ovl.querySelector('#cnpDlgNo').onclick = () => done(o.input !== null ? null : false);
    const th = ovl.querySelector('#cnpDlgTh');
    if (th) { th.onclick = () => done('third'); }
    // ΟΧΙ κλείσιμο με κλικ έξω — μόνο από τα κουμπιά ή ESC
    setTimeout(() => (inp || ovl.querySelector('#cnpDlgOk')).focus(), 30);
  });
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
  $('#topTitle').textContent = t;
  $('#topSub').textContent = sub || new Date().toLocaleDateString((window.CNP_LOCALE||'el-GR'), {weekday: 'long', day: 'numeric', month: 'long'});
}
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
      ${t.assignee ? `<span class="ava" title="${esc(adminName(t.assignee))}">${esc(adminIni(t.assignee))}</span>` : ''}
      ${t.ball ? `<span class="ball ${t.ball === S.boot.me.id ? 'me' : ''}">⚡${esc(adminIni(t.ball))}</span>` : ''}
      ${t.due ? `<span class="${over ? 'pill pill-bad' : ''}">${I.cal} ${dShort(t.due)}</span>` : ''}
      ${t.mins ? `<span>⏱ ${fmtMin(t.mins)}</span>` : ''}
      ${t.est ? `<span class="mut">~${fmtMin(t.est)}</span>` : ''}
      ${t.check ? `<span class="${t.check[0] >= t.check[1] ? 'pill pill-ok' : ''}">☑ ${t.check[0]}/${t.check[1]}</span>` : ''}
      ${t.ticket ? '<span>' + I.ticket + ' </span>' : ''}
      ${t.blocked ? `<span class="pill pill-bad" title="Μπλοκάρεται από ${t.blocked} tasks">⛓ ${t.blocked}</span>` : ''}
    </div>
    ${t.done && t.doneNote ? `<div class="tcard-done" title="${esc(t.doneNote)}">✔ ${esc(t.doneNote)}</div>` : ''}</div>`;
}
dnd('.tcard', '.kb-col', async (card, col) => {
  const st = +col.dataset.status;
  let note = '';
  if (S.boot.statuses.find(x => x.id === st && x.done)) {
    note = await askDone(card.querySelector('.tcard-t')?.textContent || '');
    if (note === null) { return; }          // άκυρο = η κάρτα μένει όπου ήταν
  }
  const r = await api('move_task', {task: +card.dataset.task, status: st, note}).catch(e => ({ok: false, error: e && e.message}));
  if (r.ok) {
    col.querySelector('.kb-cards').appendChild(card);
    $$('.kb-col').forEach(c => c.querySelector('.kb-n').textContent = c.querySelectorAll('.tcard').length);
  } else { toast(r.error || 'Δεν επιτρέπεται', true); vBoard(); }
}, el => openTask(+el.dataset.task));

/* ═════════ TASK DRAWER ═════════ */
let timerInt = null;
async function openTask(id) {
  const d = await api('task&id=' + id).catch(() => null);
  if (!d) { toast('Δεν έχεις πρόσβαση', true); return; }
  closeDrawer();
  const t = d.task, me = S.boot.me;
  const ovl = document.createElement('div'); ovl.className = 'ovl';   // κλικ έξω ΔΕΝ κλείνει
  const dr = document.createElement('div'); dr.className = 'drawer tk-modal';
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
  /* Ένα μήνυμα της συνομιλίας μαζί με τα συνημμένα του. Οι εικόνες φαίνονται
     επί τόπου — δεν έχει νόημα να ανοίγεις αρχείο για να δεις screenshot. */
  const cmHtml = cm => `<div class="msg ${cm.byId === me.id ? 'mine' : ''}">
    <div class="msg-h">${esc(cm.by)}${cm.to !== null ? ` <span class="pill pill-info">προς: ${cm.to === -1 ? 'Διαχειριστές' : esc(adminName(cm.to))}</span>` : ''}
      <span class="mut">${tShort(cm.at)}</span></div>
    <div class="msg-b">${esc(cm.body)}</div>
    ${(cm.files || []).length ? `<div class="msg-files">${cm.files.map(f => f.kind === 'image'
      ? `<a href="${f.url}" target="_blank" class="msg-img" title="${esc(f.name)}"><img src="${f.url}" loading="lazy" alt="${esc(f.name)}"></a>`
      : `<a href="${f.url}&dl=1" class="msg-file" title="${esc(f.name)}">${f.kind === 'video' ? '🎬' : f.kind === 'audio' ? '🎵' : '📎'} ${esc(f.name)}</a>`
    ).join('')}</div>` : ''}
  </div>`;

  /* Ο χρόνος ήταν χαμένος στη μέση της στήλης — ανεβαίνει στην κεφαλίδα, μαζί
     με την κατάσταση της χρέωσης, γιατί αυτά κοιτάς πρώτα. */
  const billMins = (d.timelogs || []).filter(l => l.billable && !l.running)
    .reduce((a, l) => a + (l.charged || l.mins || 0), 0);
  dr.innerHTML = `
  <div class="drawer-h">
    <span class="dot" style="background:${d.project.color};width:12px;height:12px"></span>
    <h2>${esc(t.title)}</h2>
    <span class="pill" id="dStPill" style="background:${statusOf(t.status).color || '#8291a9'}22;color:${statusOf(t.status).color || '#8291a9'};font-weight:700">${esc(statusOf(t.status).title || '—')}</span>
    <span class="tk-hdr">
      <span class="tk-hdr-i" title="Καταγεγραμμένος χρόνος${t.est ? ' / εκτίμηση' : ''}">⏱ <b>${fmtMin(d.total)}</b>${t.est ? `<small>/ ~${fmtMin(t.est)}</small>` : ''}</span>
      ${billMins ? `<span class="tk-hdr-i ${t.billOk ? 'ok' : 'warn'}" title="${t.billOk ? 'Εγκρίθηκε από το λογιστήριο' : 'Χρεώσιμος χρόνος — χρειάζεται έγκριση λογιστηρίου πριν κλείσει'}">💶 <b>${fmtMin(billMins)}</b> ${t.billOk ? '✔' : '⏳'}</span>` : ''}
      ${d.timerHere ? '<span class="tk-hdr-i live">▶ τρέχει</span>' : ''}
    </span>
    <button class="btn btn-sm ${d.watching ? 'btn-p' : 'btn-o'}" id="dWatch"
      title="${d.watching ? 'Την παρακολουθείς — ειδοποιήσεις σε κάθε αλλαγή. Κλικ για διακοπή.' : 'Παρακολούθηση: ειδοποίηση σε κάθε αλλαγή αυτής της εργασίας.'}"
      aria-label="Παρακολούθηση εργασίας">${I.eye}${d.watchers ? ' ' + d.watchers : ''}</button>
    <button class="drawer-x" id="dX">✕</button>
  </div>
  <div class="drawer-b tk-modal-b">
    ${t.done ? `<div class="card done-card"><div class="card-b">
      <b>✔ Ολοκληρώθηκε</b> <span class="mut">${esc(tShort(t.doneAt))}${t.doneBy ? ' — ' + esc(adminName(t.doneBy)) : ''}</span>
      ${t.doneNote ? `<div class="done-note">${esc(t.doneNote)}</div>` : '<div class="mut" style="font-size:12px;margin-top:4px">Χωρίς σημείωμα.</div>'}
      <button class="btn btn-sm btn-o" id="dReopen" style="margin-top:9px">↩ Ξανάνοιγμα</button>
    </div></div>` : ''}
    ${d.ticket ? (() => {
      const tk = d.ticket;
      const md = (window.CNP && window.CNP.mdToHtml) || (x => esc(x).replace(/\n/g, '<br>'));
      const wl = h => h >= 48 ? Math.floor(h / 24) + ' ημέρες' : (h >= 24 ? '1 ημέρα' : h + 'ω');
      return `<div class="card tkbox">
        <div class="card-h">${I.ticket} <b>#${esc(tk.tid)}</b>
          <span style="font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(tk.title)}</span>
          <span class="pill pill-info" style="margin-left:auto;flex:none">${esc(tk.status)}</span></div>
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
          <div style="display:flex;gap:8px;margin-top:11px;flex-wrap:wrap">
            <button class="btn btn-sm btn-p" data-ibgo="${tk.id}">${I.ticket} Άνοιγμα &amp; απάντηση</button>
            ${tk.clientId ? `<button class="btn btn-sm btn-o" data-c360="${tk.clientId}">${I.user} Πελάτης 360°</button>` : ''}
          </div>
        </div></div>`;
    })() : ''}
    <div class="card"><div class="card-b">
      <label class="lbl">Τίτλος</label>
      <input class="inp" id="fTitle" value="${esc(t.title)}">
      <div class="frow" style="margin-top:12px">
        <div><label class="lbl">Ανάθεση <span class="mut" style="font-weight:400">— ποιος την εκτελεί</span></label>
          <select class="inp" id="fAssignee">${admOpts(t.assignee, t.dept)}</select></div>
        <div><label class="lbl">Κατάσταση</label>
          <select class="inp" id="fStatus">${S.boot.statuses.map(st =>
            `<option value="${st.id}" ${st.id === t.status ? 'selected' : ''}>${esc(st.title)}${st.done ? ' ✔' : ''}</option>`).join('')}</select></div>
        <div><label class="lbl">⚡ Η μπάλα σε</label><select class="inp" id="fBall">${admOpts(t.ball, t.dept)}</select></div>
        <div><label class="lbl">Προτεραιότητα</label>
          <select class="inp" id="fPrio">
            ${['Κανονική', 'Υψηλή', 'Κρίσιμη'].map((p, i) => `<option value="${i}" ${i === t.prio ? 'selected' : ''}>${p}</option>`).join('')}</select></div>
        <div><label class="lbl">Τύπος</label><select class="inp" id="fType"><option value="">— γενικό —</option>
          ${S.boot.types.map(ty => `<option value="${ty.id}" ${ty.id === t.type ? 'selected' : ''}>${esc(ty.name)}</option>`).join('')}</select></div>
        <div><label class="lbl">Department <span class="mut" style="font-weight:400">— πού απευθύνεται</span></label>
          <select class="inp" id="fDept"><option value="">— χωρίς department —</option>
          ${(d.depts || []).map(u => `<option value="${u.id}" ${u.id === t.dept ? 'selected' : ''}>${esc(u.name)}</option>`).join('')}</select></div>
        <div><label class="lbl">Έναρξη (Gantt)</label><input type="date" class="inp" id="fStart" value="${t.start || ''}"></div>
        <div><label class="lbl">Λήξη</label><input type="date" class="inp" id="fDue" value="${t.due || ''}"></div>
        <div><label class="lbl">Πλάνο (πότε θα το δουλέψω)</label><input type="date" class="inp" id="fSched" value="${t.sched || ''}"></div>
      </div>
      <label class="lbl" style="margin-top:12px">${I.doc || ''} <b>1. Το ζητούμενο</b> — τι ακριβώς πρέπει να γίνει</label>
      ${rteHtml('fDescr', d.descr || '', 'Περιγραφή, βήματα, σύνδεσμοι…', {min: 260})}
      <div style="display:flex;gap:9px;margin-top:13px;align-items:center">
        <button class="btn btn-p" id="dSave">Αποθήκευση</button>
        ${t.done ? '' : '<button class="btn btn-ok" id="dDone">✔ Ολοκλήρωση</button>'}
        ${me.full && t.assignee && t.assignee !== me.id ? '<button class="btn btn-o" id="dAsk">❓ Ζήτα ενημέρωση</button>' : ''}
        <span style="margin-left:auto;display:flex;gap:6px;align-items:center;flex-wrap:wrap;justify-content:flex-end">
          ${d.owner ? `<a class="pill pill-info" href="#/client360/${d.owner.id}" data-navclose
             title="${d.owner.via === 'project' ? 'Πελάτης του έργου' : 'Πελάτης του ticket'}">${I.user} ${esc(d.owner.name)}</a>` : ''}
          ${d.project.none
            ? '<span class="pill pill-mut" title="Η εργασία ανήκει μόνο σε department, δεν είναι μέρος έργου">Χωρίς έργο</span>'
            : `<a class="pill pill-mut" href="#/board/${d.project.id}" data-navclose title="Board του έργου">${I.board} ${esc(d.project.name)}</a>`}
          ${(() => { const u = (d.depts || []).find(x => x.id === t.dept); return u ? `<a class="pill pill-mut" href="#/unit/${u.id}" data-navclose title="Εργασίες του department">${esc(u.name)}</a>` : ''; })()}
        </span>
      </div>
    </div></div>

    <div class="card tk-side"><div class="card-h">⏱ Χρόνος <span class="mut" style="font-weight:600">${fmtMin(d.total)}${t.est ? ' / ~' + fmtMin(t.est) : ''}</span>
      <span style="flex:1"></span>
      ${d.timerHere ? `<span class="timer-live" id="tLive"></span><button class="btn btn-sm btn-danger" id="tStop">${I.stop} Stop</button>`
        : d.timerElsewhere ? `<span class="pill pill-warn">τρέχει αλλού</span><button class="btn btn-sm btn-ok" id="tStart">${I.play} Εδώ</button>`
        : `<button class="btn btn-sm btn-ok" id="tStart">${I.play} Start</button>`}
    </div>
    <div class="card-b" style="padding-top:10px">
      ${t.est ? `<div class="bar" style="margin-bottom:12px"><span class="${d.total > t.est ? 'bad' : d.total > t.est * .8 ? 'warn' : 'ok'}" style="width:${Math.min(100, Math.round(d.total / t.est * 100))}%"></span></div>` : ''}
      <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <input class="inp" id="tMins" type="number" min="1" placeholder="λεπτά" style="width:90px">
        <label style="display:flex;gap:5px;align-items:center;font-size:12.5px"><input type="checkbox" id="tBill" ${d.scClient ? 'checked' : ''}> Χρεώσιμο</label>
        <input class="inp" id="tNote" placeholder="σημείωση" style="flex:1;min-width:120px">
        <button class="btn btn-sm btn-o" id="tAdd">Καταχώρηση</button>
      </div>
      ${d.scClient ? `<div class="mut" style="font-size:11.5px;margin-top:7px">Πελάτης: <b>${esc(d.scClient)}</b> — τα χρεώσιμα αφαιρούν προαγορά</div>` : ''}
      ${billMins ? `<div class="bill-gate ${t.billOk ? 'ok' : ''}">
        <div><b>${t.billOk ? '✔ Η χρέωση εγκρίθηκε' : '⏳ Εκκρεμεί έγκριση λογιστηρίου'}</b>
          <div class="mut" style="font-size:11px">${t.billOk
            ? `${esc(t.billOkBy ? adminName(t.billOkBy) : '')}${t.billOkAt ? ' · ' + tShort(t.billOkAt) : ''}`
            : `${fmtMin(billMins)} χρεώσιμος χρόνος — η εργασία δεν κλείνει πριν εγκριθεί`}</div></div>
        ${(me.areas || []).includes('finance')
          ? `<button class="btn btn-sm ${t.billOk ? 'btn-o' : 'btn-p'}" id="dBillOk">${t.billOk ? 'Ανάκληση' : 'Έγκριση χρέωσης'}</button>`
          : '<span class="mut" style="font-size:11px">μόνο το λογιστήριο</span>'}
      </div>` : ''}
      ${d.timelogs.length ? `<div style="margin-top:12px">${d.timelogs.slice(0, 6).map(l =>
        `<div style="display:flex;gap:9px;font-size:12px;padding:4px 0" class="${l.running ? '' : ''}">
          <b>${l.running ? '▶ σε εξέλιξη' : fmtMin(l.mins)}</b>
          ${l.billable ? `<span class="pill pill-warn">χρέωση ${fmtMin(l.charged || l.mins)}</span>` : '<span class="mut">χωρίς χρέωση</span>'}
          <span class="mut">${esc(l.by)}${l.note ? ' · ' + esc(l.note) : ''}</span>
          <span class="mut" style="margin-left:auto">${tShort(l.at)}</span></div>`).join('')}</div>` : ''}
    </div></div>

    <div class="card tk-side"><div class="card-h">${I.link} Εξαρτήσεις <span class="mut" style="font-weight:600;font-size:11px">— πρέπει να τελειώσουν πρώτα</span></div>
      <div class="card-b" id="dDeps">
      ${(d.deps || []).map(dp => `<div style="display:flex;gap:8px;align-items:center;padding:4px 0">
        <span>${dp.done ? '✅' : '⏳'}</span>
        <a style="flex:1;cursor:pointer" data-dgo="${dp.id}">${esc(dp.title)}</a>
        <button class="btn btn-sm btn-o" data-ddel="${dp.depId}">✕</button></div>`).join('')}
      <div style="display:flex;gap:7px;margin-top:8px">
        <select class="inp" id="depSel" style="flex:1"><option value="">— διάλεξε task που μας μπλοκάρει —</option></select>
        <button class="btn btn-sm btn-o" id="depAdd">+</button></div>
    </div></div>

    <div class="card tk-side"><div class="card-h">${I.clip} Αρχεία <span class="mut" style="font-weight:600;font-size:11px">— γενικά της εργασίας</span></div><div class="card-b" id="dFiles">
      <div class="mut" style="font-size:12px">Φόρτωση…</div></div></div>

    <div class="card tk-step"><div class="card-h">${I.checkSquare} <b>2. Ενέργειες</b>
      <span class="mut" style="font-weight:600;font-size:11px">— τα βήματα· η πρόοδος φαίνεται στην κάρτα</span></div><div class="card-b" id="dCheck">
      ${d.check.map(it => `<div class="chk ${it.done ? 'done' : ''}"><input type="checkbox" data-chk="${it.id}" ${it.done ? 'checked' : ''}><span>${esc(it.title)}</span></div>`).join('')}
      <div style="display:flex;gap:8px;margin-top:9px">
        <input class="inp" id="chkNew" placeholder="Νέο βήμα… (Enter)"></div>
    </div></div>

    <div class="card tk-step tk-chat"><div class="card-h">${I.chat} <b>3. Επικοινωνία</b>
      <span class="mut" style="font-weight:600;font-size:11px">— μεταξύ μας· ο πελάτης δεν τη βλέπει</span></div><div class="card-b">
      <div id="dMsgs">${d.comments.map(cmHtml).join('') || '<div class="mut" style="font-size:12.5px">Καμία κουβέντα ακόμη.</div>'}</div>
      <div class="cm-box">
        <textarea class="inp" id="cmBody" rows="3"
          placeholder="Γράψε μήνυμα…&#10;Enter = αποστολή · Shift+Enter = νέα γραμμή"></textarea>
        <div class="cm-row">
          <select class="inp" id="cmTo" title="Υποχρεωτικό — σε ποιον απευθύνεται">
            <option value="">— διάλεξε παραλήπτη —</option>
            <option value="-1">Διαχειριστές (όλοι)</option>
            ${S.boot.admins.filter(a => a.id !== me.id).map(a => `<option value="${a.id}">${esc(a.name)}</option>`).join('')}</select>
          <input type="file" id="cmFiles" multiple hidden>
          <button class="btn btn-o btn-sm" id="cmAttach" title="Επισύναψη στο μήνυμα">${I.clip} Αρχεία</button>
          <span class="mut" id="cmFileN" style="font-size:11.5px"></span>
          <span style="flex:1"></span>
          <button class="btn btn-p btn-sm" id="cmSend">Αποστολή</button>
        </div>
      </div>
    </div></div>

    <details class="card"><summary class="card-h" style="cursor:pointer">${I.clock} Ιστορικό (${d.activity.length})</summary>
      <div class="card-b">${d.activity.map(a => `<div style="font-size:12px;padding:4px 0;border-bottom:1px dashed var(--line)">
        <b>${esc(a.detail || a.action)}</b> <span class="mut">— ${esc(a.by)} · ${tShort(a.at)}</span></div>`).join('')}</div></details>
  </div>`;
  document.body.append(ovl, dr);
  /* Δύο στήλες: αριστερά η ροή (ζητούμενο → ενέργειες → επικοινωνία), δεξιά τα
     βοηθητικά (χρόνος, εξαρτήσεις, αρχεία). Η αναδιάταξη γίνεται εδώ και όχι
     στο template, ώστε η σειρά του DOM να μένει λογική και σε κινητό. */
  (() => {
    const body = $('.tk-modal-b', dr); if (!body) { return; }
    const main = document.createElement('div'); main.className = 'tk-col-main';
    const side = document.createElement('div'); side.className = 'tk-col-side';
    [...body.children].forEach(el => (el.classList.contains('tk-side') ? side : main).appendChild(el));
    body.append(main, side);
  })();
  requestAnimationFrame(() => { ovl.classList.add('show'); dr.classList.add('show'); });

  $('#dX').onclick = () => cnpAskClose(dr);
  /* Αλλάζεις department → αλλάζουν και οι υποψήφιοι για ανάθεση. */
  const fDep = $('#fDept', dr);
  if (fDep) fDep.onchange = () => {
    ['fAssignee', 'fBall'].forEach(id => {
      const el = $('#' + id, dr); if (!el) { return; }
      const keep = el.value;
      el.innerHTML = admOpts(keep, +fDep.value || 0);
      el.value = keep;
    });
  };
  /* Τα «ψίχουλα» (πελάτης / έργο / department) βγάζουν εκτός εργασίας — αν έμενε
     ανοιχτό το drawer, θα σκέπαζε την οθόνη στην οποία μόλις πήγες. */
  $$('[data-navclose]', dr).forEach(a => a.addEventListener('click', () => closeDrawer()));
  $('#dSave', dr).onclick = async () => {
    await api('save_task', {task: id, title: $('#fTitle').value, descr: rteVal('fDescr'),
      due: $('#fDue').value || null, sched: $('#fSched').value || null, start: $('#fStart').value || null,
      type: +$('#fType').value || 0, ball: +$('#fBall').value || 0,
      dept: +(($('#fDept') || {}).value) || 0,
      assignee: +$('#fAssignee').value || 0, prio: +$('#fPrio').value});
    toast('Αποθηκεύτηκε'); closeDrawer(); if (S.view === 'board') vBoard(); if (S.view === 'myday') vMyDay();
  };
  /* Αλλαγή κατάστασης από μέσα στην εργασία, όπως στα tickets: ισχύει αμέσως,
     δεν περιμένει «Αποθήκευση». Αν η νέα κατάσταση είναι τελική, ζητάει δυο
     λόγια για το πώς έκλεισε. */
  const stSel = $('#fStatus', dr); if (stSel) stSel.onchange = async () => {
    const to = +stSel.value, prev = t.status;
    const fin = S.boot.statuses.find(x => x.id === to && x.done);
    let note = '';
    if (fin) {
      note = await askDone($('#fTitle').value || t.title);
      if (note === null) { stSel.value = prev; return; }
    }
    const r = await api('move_task', {task: id, status: to, note}).catch(e => ({ok: false, error: e && e.message}));
    if (!r.ok) { stSel.value = prev; toast(r.error || 'Δεν επιτρέπεται', true); return; }
    toast('Κατάσταση: ' + (statusOf(to).title || '—'));
    openTask(id);
    if (S.view === 'board') { vBoard(); } else if (S.view === 'myday') { vMyDay(); }
  };

  /* Ολοκλήρωση με δυο λόγια. Στέλνει τη μετακίνηση στην «τελική» στήλη — έτσι
     καθαρίζει και η μπάλα, και η εργασία φεύγει από «Η μέρα μου». */
  const dn = $('#dDone', dr); if (dn) dn.onclick = async () => {
    const fin = S.boot.statuses.find(x => x.done);
    if (!fin) { toast('Δεν υπάρχει στήλη ολοκλήρωσης', true); return; }
    const note = await askDone($('#fTitle').value || t.title);
    if (note === null) { return; }
    const r = await api('move_task', {task: id, status: fin.id, note}).catch(e => ({ok: false, error: e && e.message}));
    if (!r.ok) { toast(r.error || 'Δεν επιτρέπεται', true); return; }
    toast('Ολοκληρώθηκε'); closeDrawer();
    if (S.view === 'board') { vBoard(); } else if (S.view === 'myday') { vMyDay(); } else if (window.R && window.R[S.view]) { window.R[S.view](); }
  };
  const rop = $('#dReopen', dr); if (rop) rop.onclick = async () => {
    const first = S.boot.statuses.find(x => !x.done);
    if (!first) { return; }
    await api('move_task', {task: id, status: first.id});
    toast('Ξανάνοιξε'); openTask(id);
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
  const ask = $('#dAsk', dr); if (ask) ask.onclick = async () => { await api('request_update', {task: id}); toast('Στάλθηκε ping στον χειριστή'); };
  const ts = $('#tStart', dr); if (ts) ts.onclick = async () => { await api('timer_start', {task: id}); toast('Ο χρόνος μετράει'); openTask(id); };
  const tp = $('#tStop', dr); if (tp) tp.onclick = async () => {
    const bill = await cnpConfirm('Να χρεωθεί ο χρόνος στον πελάτη;', {ok: I.coin + ' Χρεώσιμο', cancel: 'Χωρίς χρέωση'});
    const r = await api('timer_stop', {billable: bill, note: ''});
    toast('Καταχωρήθηκε ' + fmtMin(r.mins)); openTask(id);
  };
  if (d.timerHere) {
    const since = new Date(d.timerHere.since.replace(' ', 'T')).getTime();
    const tick = () => { const s = Math.floor((Date.now() - since) / 1000);
      const el = $('#tLive'); if (!el) { clearInterval(timerInt); return; }
      el.textContent = `${String(Math.floor(s / 3600)).padStart(2, '0')}:${String(Math.floor(s % 3600 / 60)).padStart(2, '0')}:${String(s % 60).padStart(2, '0')}`; };
    tick(); timerInt = setInterval(tick, 1000);
  }
  $('#tAdd', dr).onclick = async () => {
    const m = +$('#tMins').value; if (!m) return;
    await api('time_add', {task: id, mins: m, billable: $('#tBill').checked, note: $('#tNote').value});
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
    window.cnpAttachments($('#dFiles', dr), {module: 'task', refType: 'task', refId: id});
  }
  $('#chkNew', dr).onkeydown = async e => {
    if (e.key !== 'Enter' || !e.target.value.trim()) return;
    await api('check_add', {task: id, title: e.target.value.trim()}); openTask(id);
  };
  $$('#dCheck input[data-chk]', dr).forEach(cb => cb.onchange = async () => {
    await api('check_toggle', {id: +cb.dataset.chk});
    cb.closest('.chk').classList.toggle('done', cb.checked);
  });
  /* Συνομιλία: Enter στέλνει, Shift+Enter αλλάζει γραμμή. Ο παραλήπτης είναι
     υποχρεωτικός — μήνυμα «προς κανέναν» δεν το διαβάζει κανείς. Τα συνημμένα
     κρέμονται στο ΜΗΝΥΜΑ, οπότε ανεβαίνουν αφού πάρουμε το id του. */
  let cmPicked = [];
  const cmFileIn = $('#cmFiles', dr), cmN = $('#cmFileN', dr);
  if (cmFileIn) {
    $('#cmAttach', dr).onclick = () => cmFileIn.click();
    cmFileIn.onchange = () => {
      cmPicked = [...cmFileIn.files];
      cmN.textContent = cmPicked.length ? `${cmPicked.length} αρχεί${cmPicked.length === 1 ? 'ο' : 'α'}` : '';
    };
  }
  const cmSend = async () => {
    const body = $('#cmBody', dr).value.trim();
    const to = $('#cmTo', dr).value;
    if (!body && !cmPicked.length) { return; }
    if (to === '') { toast('Διάλεξε παραλήπτη', true); $('#cmTo', dr).focus(); return; }
    const r = await api('comment', {task: id, body: body || '(συνημμένο)', to: +to}).catch(e => ({err: e.message}));
    if (r.err) { toast(r.err, true); return; }
    for (const f of cmPicked) {
      const fd = new FormData();
      fd.append('module', 'task'); fd.append('ref_type', 'comment'); fd.append('ref_id', r.id); fd.append('file', f);
      await fetch('api.php?a=file_upload', {method: 'POST', body: fd, credentials: 'same-origin'})
        .then(x => x.json()).catch(() => null);
    }
    toast(cmPicked.length ? 'Στάλθηκε με συνημμένα' : 'Στάλθηκε');
    openTask(id);
  };
  $('#cmSend', dr).onclick = cmSend;
  $('#cmBody', dr).onkeydown = e => {
    if (e.key === 'Enter' && !e.shiftKey && !e.ctrlKey && !e.metaKey) { e.preventDefault(); cmSend(); }
  };
}
/** Άμεσο κλείσιμο ΧΩΡΙΣ ερώτηση — το καλούν τα views ΜΕΤΑ από επιτυχή αποθήκευση. */
function closeDrawer() {
  clearInterval(timerInt);
  $$('.ovl,.drawer').forEach(el => { el.classList.remove('show'); setTimeout(() => el.remove(), 300); });
}

/* ═══ Popups: ΔΕΝ κλείνουν με κλικ έξω — μόνο από ✕/Άκυρο/ESC, και ρωτούν αν
   υπάρχουν μη αποθηκευμένες αλλαγές. Κεντρικό, ισχύει για ΟΛΟ το project. ═══ */

// 1) Σήμανση «βρόμικου» popup σε κάθε πληκτρολόγηση/αλλαγή μέσα του
function _cnpMarkDirty(e) {
  const box = e.target.closest && e.target.closest('.drawer, .pal-box');
  if (box && !box.dataset.cnpClean) { box.dataset.dirty = '1'; }
}
document.addEventListener('input', _cnpMarkDirty, true);
document.addEventListener('change', _cnpMarkDirty, true);

/** Το κουμπί αποθήκευσης ενός popup (για την επιλογή «Αποθήκευση» στην ερώτηση). */
function _cnpSaveBtn(box) {
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
    body: 'Έκανες αλλαγές που δεν έχουν αποθηκευτεί.' + (saveBtn ? ' Θέλεις να τις αποθηκεύσω;' : ''),
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
    if (!box || box.querySelector('.pal-x') || box.querySelector('.drawer-x')) { return; }
    const x = document.createElement('button');
    x.className = 'pal-x'; x.type = 'button'; x.title = 'Κλείσιμο'; x.innerHTML = '✕';
    x.onclick = () => cnpAskClose(box);
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
  if (top.closest('.ovl') && top.querySelector('#cnpDlgOk')) { return; }   // τα ίδια τα dialogs
  e.stopPropagation();
  cnpAskClose(top);
}, true);

/* ═════════ Η ΜΕΡΑ ΜΟΥ ═════════ */
async function vMyDay() {
  const _h = new Date().getHours();
  const [_g, _e] = _h < 5 ? ['Καληνύχτα', '🌙'] : _h < 12 ? ['Καλημέρα', '☀️'] : _h < 18 ? ['Καλησπέρα', '🌤️'] : _h < 22 ? ['Καλησπέρα', '🌆'] : ['Καληνύχτα', '🌙'];
  setTop('Η μέρα μου', _g + ', ' + S.boot.me.name.split(' ')[0] + ' ' + _e);
  const c = $('#content');
  c.innerHTML = '<div class="grid g4">' + '<div class="skel" style="height:90px"></div>'.repeat(4) + '</div>';
  const d = await api('myday');
  const mt = await api('my_todos').catch(() => ({todos: []}));
  const st = d.stats;
  const coachCol = {bad: 'var(--bad)', warn: 'var(--warn)', tip: 'var(--brand)', ok: 'var(--ok)'};
  const coach = d.coach || [];
  const queue = d.queue || [];
  const waitLbl = h => h >= 48 ? Math.floor(h / 24) + ' ημέρες' : (h >= 24 ? '1 ημέρα' : h + 'ω');
  c.innerHTML = `
  ${queue.length ? `<div class="card" style="margin-bottom:14px;border-left:4px solid var(--bad)">
    <div class="card-h">${I.compass} Η σειρά της ημέρας
      <span class="mut" style="font-weight:600;font-size:11.5px">— ξεκίνα από πάνω· πρώτα το SLA, μετά όποιος περιμένει περισσότερο</span>
      <a data-goinbox style="margin-left:auto;font-size:11px;font-weight:600;color:var(--brand);cursor:pointer">όλα τα tickets →</a></div>
    <div class="card-b" style="padding-top:6px">
    ${queue.map((q, i) => `<div class="qrow" data-qtk="${q.id}">
      <span class="qn">${i + 1}</span>
      <span class="pill" style="background:${coachCol[q.lvl]}1e;color:${coachCol[q.lvl]};font-weight:700;flex:none">${esc(q.why)}</span>
      <span class="qt">${esc(q.title)}
        <span class="mut">#${esc(q.tid)}${q.client ? ' · ' + esc(q.client) : ''}${q.dept ? ' · ' + esc(q.dept) : ''}</span></span>
      ${q.urgency === 'High' ? '<span class="pill pill-bad" style="flex:none">επείγον</span>' : ''}
      ${q.assigned ? (q.mine ? '<span class="pill pill-info" style="flex:none">δικό μου</span>'
                             : `<span class="pill pill-mut" style="flex:none">${esc(adminIni(q.assigned))}</span>`)
                   : '<span class="pill pill-warn" style="flex:none">αζήτητο</span>'}
      <span class="qw mut">${waitLbl(q.waitH)}</span>
    </div>`).join('')}
    </div></div>` : ''}
  ${(() => {
    const dl = d.deadlines || [];
    if (!dl.length) { return ''; }
    /* Χρώμα από το πόσο απομένει, όχι από το ποσοστό: μια εργασία με μεγάλο
       ορίζοντα μπορεί να είναι στο 90% του χρόνου και να έχει άνεση εβδομάδων. */
    const col = x => (x.days < 0 || (x.hours !== null && x.hours < 0)) ? 'var(--bad)'
      : (x.hours !== null ? (x.hours <= 4 ? 'var(--bad)' : (x.hours <= 12 ? 'var(--warn)' : 'var(--brand)'))
        : (x.days <= 1 ? 'var(--bad)' : (x.days <= 3 ? 'var(--warn)' : 'var(--brand)')));
    const lbl = x => {
      if (x.hours !== null) {
        return x.hours < 0 ? `ξεπεράστηκε ${Math.abs(x.hours)}ω` : (x.hours <= 48 ? `σε ${x.hours}ω` : `σε ${Math.round(x.hours / 24)} ημέρες`);
      }
      if (x.days < 0) { return `εκπρόθεσμο ${Math.abs(x.days)} ${Math.abs(x.days) === 1 ? 'ημέρα' : 'ημέρες'}`; }
      if (x.days === 0) { return 'λήγει σήμερα'; }
      if (x.days === 1) { return 'αύριο'; }
      return `σε ${x.days} ημέρες`;
    };
    const ico = {project: I.folder, task: I.checkSquare, sla: I.clock, offer: I.doc};
    return `<div class="card" style="margin-bottom:14px">
      <div class="card-h">${I.clock} Προθεσμίες
        <span class="mut" style="font-weight:600;font-size:11.5px">— πόσο κοντά είσαι· η μπάρα δείχνει τον χρόνο που πέρασε, όχι τη δουλειά που έγινε</span></div>
      <div class="card-b" style="padding-top:6px">
      ${dl.map(x => `<div class="dlrow" ${x.kind === 'sla' ? `data-qtk="${x.id}"`
        : x.kind === 'task' ? `data-dltask="${x.id}"`
        : x.kind === 'offer' ? 'data-dloffer="1"' : `data-dlproj="${x.id}"`}>
        <span class="dlic" style="color:${col(x)}">${ico[x.kind] || ''}</span>
        <span class="dlt">${esc(x.title)}<span class="mut"> · ${esc(x.sub)}</span></span>
        <span class="dlbar">${x.pct === null ? '' : `<span style="width:${x.pct}%;background:${col(x)}"></span>`}</span>
        <span class="dld" style="color:${col(x)}">${esc(lbl(x))}</span>
      </div>`).join('')}
      </div></div>`;
  })()}
  ${coach.length ? `<div class="card coach" style="margin-bottom:14px"><div class="card-h">${I.compass} Καθοδήγηση για σένα</div>
    <div class="card-b" style="display:flex;flex-direction:column;gap:8px">
    ${coach.map(x => `<div style="display:flex;gap:10px;align-items:flex-start;padding:8px 11px;border-radius:10px;
      background:${coachCol[x.lvl]}14;border-left:3px solid ${coachCol[x.lvl]}">
      <span style="font-size:16px;line-height:1.4;flex:none">${x.icon}</span>
      <span style="font-size:13px;line-height:1.5">${esc(x.text)}
        ${(x.refs || []).length ? `<span class="crefs">${x.refs.map(r =>
          `<a class="cref" ${r.kind === 'ticket' ? `data-qtk="${r.id}"` : `data-dltask="${r.id}"`}>${esc(r.label)}</a>`).join('')}</span>` : ''}
      </span></div>`).join('')}
    </div></div>` : ''}
  <div class="grid g4" style="margin-bottom:16px">
    ${suStat(I.ticket, st.tickets, 'Tickets μου', st.tickets ? 'var(--brand)' : 'var(--ok)')}
    ${suStat(I.clock, st.nearSla, 'Κοντά σε SLA', st.nearSla ? 'var(--bad)' : 'var(--ok)')}
    ${suStat(I.alert, d.balls.length, 'Απαιτούν ενέργειά μου', d.balls.length ? 'var(--warn)' : 'var(--ok)')}
    ${suStat(I.clock, fmtMin(st.minsToday), 'Χρόνος σήμερα', 'var(--violet)')}
  </div>
  <div class="grid g2">
    <div>
      ${d.plan.length ? `<div class="card"><div class="card-h">${I.cal} Το πλάνο μου σήμερα</div>
        ${d.plan.map(t => `<div class="trow" data-task="${t.id}">
          <span class="dot" style="background:${['#8595ac', '#eba63c', '#e2515f'][t.prio]}"></span>
          <div style="flex:1"><b style="font-size:13px">${esc(t.title)}</b>
            <div class="mut" style="font-size:11px">${esc(t.pname)}</div></div>
          ${t.ball === S.boot.me.id ? '<span class="ball me">⚡ εσύ</span>' : ''}
          ${t.sched < today() ? `<span class="pill pill-bad">από ${dShort(t.sched)}</span>` : ''}
        </div>`).join('')}</div>` : ''}
      ${d.balls.length ? `<div class="card"><div class="card-h">${I.zap} Η μπάλα σε εμένα</div>
        ${d.balls.map(t => `<div class="trow" data-task="${t.id}">
          <span class="dot" style="background:${t.pcolor}"></span>
          <div style="flex:1"><b style="font-size:13px">${esc(t.title)}</b>
            <div class="mut" style="font-size:11px">${esc(t.pname)}</div></div></div>`).join('')}</div>` : ''}
      ${d.follows.length ? `<div class="card"><div class="card-h">${I.phone} Follow-ups σήμερα</div>
        ${d.follows.map(f => `<div class="trow"><span>${I.phone} </span><div style="flex:1"><b>${esc(f.who)}</b>
          ${f.note ? `<div class="mut" style="font-size:11.5px">${esc(f.note)}</div>` : ''}</div>
          ${f.phone ? `<span class="mut">${esc(f.phone)}</span>` : ''}</div>`).join('')}</div>` : ''}
      ${mt.todos.length ? `<div class="card"><div class="card-h">${I.checkSquare} Το πλάνο μου <a data-gotodos style="margin-left:auto;font-size:11px;font-weight:600;color:var(--brand);cursor:pointer">όλα →</a></div>
        <div class="card-b" style="display:flex;flex-direction:column;gap:1px">
        ${mt.todos.slice(0, 8).map(t => `<div style="display:flex;gap:9px;align-items:center;padding:5px 0;border-bottom:1px dashed var(--line)">
          <input type="checkbox" data-mdtog="${t.id}" style="width:16px;height:16px;cursor:pointer;flex:none">
          <div style="flex:1;min-width:0"><span style="font-size:13px">${esc(t.text)}</span>${t.remind ? ` <span class="pill" style="font-size:9px;background:${t.overdue ? 'var(--bad)' : 'var(--warn)'}1a;color:${t.overdue ? 'var(--bad)' : 'var(--warn)'}">${I.clock}</span>` : ''}
            <div class="mut" style="font-size:11px;display:flex;align-items:center;gap:5px"><span class="dot" style="background:${t.pcolor};width:7px;height:7px"></span>${esc(t.pname)}</div></div></div>`).join('')}
        ${mt.todos.length > 8 ? `<a data-gotodos style="cursor:pointer;color:var(--brand);font-size:12px;margin-top:6px">+ ${mt.todos.length - 8} ακόμη…</a>` : ''}
        </div></div>` : ''}
      ${!d.plan.length && !d.balls.length && !d.follows.length && !mt.todos.length ? '<div class="card"><div class="empty"><div class="big">🏖️</div>Καθαρή μέρα — τίποτα προγραμματισμένο!</div></div>' : ''}
    </div>
    <div>
      <div class="card"><div class="card-h">${I.ticket} Τα tickets μου <span class="mut" style="font-weight:600">(${d.tickets.length})</span></div>
      ${d.tickets.length ? d.tickets.map(tk => `<a class="trow" data-ibgo="${tk.id}" style="color:inherit;cursor:pointer">
        <div style="flex:1"><b style="font-size:13px">#${esc(tk.tid)}</b> ${esc(tk.title)}
          <div class="mut" style="font-size:11px">${esc(tk.status)} · ${tk.age} ημ.
            ${tk.waitOn === 'us'
              ? `<span class="pill pill-warn" style="font-size:9.5px">περιμένει εσένα${tk.waitDays ? ' · ' + tk.waitDays + 'η' : ''}</span>`
              : `<span class="pill pill-mut" style="font-size:9.5px">περιμένει πελάτη${tk.waitDays ? ' · ' + tk.waitDays + 'η' : ''}</span>`}</div></div>
        ${tk.slaDue ? `<span class="pill ${tk.over ? 'pill-bad' : 'pill-warn'}">SLA ${tShort(tk.slaDue)}</span>` : ''}
      </a>`).join('') : '<div class="empty" style="padding:24px">Κανένα ανοιχτό δικό σου 🎉</div>'}</div>
    </div>
  </div>`;
  $$('#content .trow[data-task]').forEach(r => r.onclick = () => openTask(+r.dataset.task));
  $$('#content [data-mdtog]').forEach(ch => ch.onclick = async () => { await api('todo_toggle', {id: +ch.dataset.mdtog}); vMyDay(); });
  $$('#content [data-gotodos]').forEach(a => a.onclick = () => go('todos'));
  $$('#content [data-goinbox]').forEach(a => a.onclick = () => go('inbox'));
  $$('#content [data-qtk]').forEach(r => r.onclick = () => go('inbox', r.dataset.qtk));
  $$('#content [data-dltask]').forEach(r => r.onclick = () => openTask(+r.dataset.dltask));
  $$('#content [data-dlproj]').forEach(r => r.onclick = () => go('board', +r.dataset.dlproj));
  $$('#content [data-dloffer]').forEach(r => r.onclick = () => go('offers'));
}

/* ═════════ CRM ═════════ */
async function vCrm() {
  setTop('CRM', 'Pipeline πωλήσεων — στόχοι → επαφή → πελάτες');
  const c = $('#content');
  const f = vCrm._f = vCrm._f || {fa: '', src: '', q: '', stage: '', closed: {}};
  c.innerHTML = crmTabs('crm') + '<div class="kb">' + '<div class="skel" style="flex:1;min-height:280px"></div>'.repeat(5) + '</div>';
  const d = await api('crm');
  const pct = d.target > 0 ? Math.min(100, Math.round(d.won / d.target * 100)) : 0;
  const flt = l => (!f.fa || String(l.assignee || '') === f.fa)
    && (!f.src || (l.source || '').toLowerCase().includes(f.src.toLowerCase()))
    && (!f.q || ((l.company || '') + ' ' + (l.contact || '') + ' ' + (l.email || '') + ' ' + (l.phone || '')).toLowerCase().includes(f.q.toLowerCase()));
  const leads = d.leads.filter(flt);
  const sources = [...new Set(d.leads.map(l => l.source).filter(Boolean))];
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
  <div class="card kb-search">
    <div class="kb-srow">
      <div class="kb-sinput"><span class="kb-sico">${I.search}</span>
        <input class="inp" id="cfQ" placeholder="Ψάξε lead — εταιρεία, επαφή, email, τηλέφωνο…" value="${esc(f.q)}"></div>
      <button class="btn btn-p btn-sm" id="newLead">${I.plus} Νέο lead</button>
    </div>
    <div class="kb-filters">
      <span class="crm-goal">${I.target} <b>${fmtEur(d.won)}</b>${d.target > 0 ? `<span class="mut"> / ${fmtEur(d.target)} μήνα</span>` : '<span class="mut"> πωλήσεις μήνα</span>'}
        ${d.target > 0 ? `<span class="crm-bar"><span class="${pct >= 100 ? 'ok' : ''}" style="width:${pct}%"></span></span>` : ''}</span>
      <select class="inp kb-sort" id="cfA"><option value="">— χειριστής —</option>
        ${S.boot.admins.map(a => `<option value="${a.id}" ${f.fa == a.id ? 'selected' : ''}>${esc(a.name)}</option>`).join('')}</select>
      <select class="inp kb-sort" id="cfS" style="margin-left:0"><option value="">— πηγή —</option>
        ${sources.map(x => `<option ${f.src === x ? 'selected' : ''}>${esc(x)}</option>`).join('')}</select>
    </div>
    ${MOB ? `<div class="kb-filters" style="border-top:0;padding-top:0;margin-top:7px">
      <button class="kb-chip${f.stage === '' ? ' on' : ''}" data-cfstage="">Όλα <b>${leads.length}</b></button>
      ${d.stages.map(sg => { const n = leads.filter(l => l.stage === sg.key).length;
        return `<button class="kb-chip${f.stage === sg.key ? ' on' : ''}" data-cfstage="${sg.key}" style="--kc:${sg.color}">
          <span class="kb-dot" style="background:${sg.color}"></span>${esc(sg.title)} <b>${n}</b></button>`; }).join('')}
    </div>` : ''}
  </div>
  ${MOB ? funnelMob() : funnelDesk()}`;
  $('#cfA').onchange = () => { f.fa = $('#cfA').value; vCrm(); };
  $('#cfS').onchange = () => { f.src = $('#cfS').value; vCrm(); };
  let cqt;
  $('#cfQ').oninput = () => { clearTimeout(cqt); cqt = setTimeout(() => { f.q = $('#cfQ').value.trim(); vCrm(); }, 320); };
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
  c.innerHTML = '<div class="grid g4">' + '<div class="skel" style="height:90px"></div>'.repeat(6) + '</div>';
  const d = await api('kpi').catch(() => null);
  if (!d) { c.innerHTML = `<div class="empty"><div class="big">${I.lock}</div>Μόνο για διαχειριστές.</div>`; return; }
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
window.CNP = {S, api, esc, askDone, dFull, cnpSetDate, suStat, rteHtml, rteVal, fmtMin, fmtEur, dShort, tShort, today, toast, setTop, go, crmTabs, openLead, cnpConfirm, cnpPrompt, cnpDialog, startRemote,
  adminName, adminIni, statusOf, typeOf, dnd, I, openTask, closeDrawer, updateBell, $, $$};

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
  const m = location.hash.match(/^#\/(\w+)(?:\/(\d+))?/);
  if (m && m[1] === 'task' && m[2]) {          // deep-link από email/παλιά URLs
    go('myday');
    openTask(+m[2]);
  } else {
    go(m ? m[1] : 'myday', m ? m[2] : undefined);
  }
  window.addEventListener('hashchange', () => {
    const h = location.hash.match(/^#\/(\w+)(?:\/(\d+))?/);
    /* Και ίδια οθόνη με άλλο id είναι νέα πλοήγηση (πελάτης → έργο → τμήμα). */
    if (h && (h[1] !== S.view || (h[2] || '') !== (S.viewArg || ''))) go(h[1], h[2]);
  });
  document.getElementById('remoteChip').onclick = stopRemote;
  remoteRefresh();
  // realtime: version polling ανά 12" → σιωπηλό refresh όταν αλλάξει κάτι από συναδέλφους
  let lastV = null;
  setInterval(async () => {
    try {
      const d = await api('version');
      updateBell(d.unread);
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
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDrawer(); });
})();
