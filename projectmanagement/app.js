/* ═══════════ CloudOn Projects — standalone SPA ═══════════ */
'use strict';

const $ = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => [...r.querySelectorAll(s)];
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const fmtMin = m => { m = +m || 0; const h = Math.floor(m / 60), r = m % 60; return h && r ? `${h}ω ${r}΄` : h ? `${h}ω` : `${r}΄`; };
const fmtEur = v => (+v || 0).toLocaleString('el-GR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' €';
const dShort = d => d ? new Date(d.replace(' ', 'T')).toLocaleDateString('el-GR', {day: '2-digit', month: '2-digit'}) : '';
const tShort = d => d ? new Date(d.replace(' ', 'T')).toLocaleString('el-GR', {day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit'}) : '';
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
function renderShell() {
  const me = S.boot.me;
  const has = a => me.full || (me.areas || []).includes(a);   // ειδικότητες/πρόσβαση
  const nav = [
    ['Εργασία', [['myday', I.sun, 'Η μέρα μου'], ['standup', I.clipboard, 'Standup'],
      ['calendar', I.cal, 'Ημερολόγιο'], ['chat', I.chat || I.ticket, 'Chat'], ['remotebook', I.monitor, 'Απομακρυσμένες']]],
  ];
  if (has('support')) {
    nav.push(['Υποστήριξη', [['inbox', I.ticket, 'Tickets'], ['knowledge', I.book, 'Γνώση'], ['client360', I.user, 'Πελάτης 360°']]]);
  }
  if (has('projects')) {
    nav.push(['Έργα', [['board', I.board, 'Board'], ['gantt', I.gantt, 'Gantt'], ['list', I.list, 'Λίστα tasks'],
      ['time', I.clock, 'Χρόνος'], ['projects', I.folder, 'Projects']]]);
  }
  if (has('sales')) {
    nav.push(['Πωλήσεις', [['crm', I.target, 'CRM'], ['offers', I.doc, 'Προσφορές']]]);
  }
  if (me.full) {
    nav.push(['Διοίκηση', [['triage', I.flag, 'Πλάνο ημέρας'], ['rootcause', I.chart, 'Ανάλυση ριζών'], ['kpi', I.chart, 'KPI Dashboard'],
      ['profit', I.coin, 'Κερδοφορία'], ['teams', I.tree, 'Ομάδες'], ['settings', I.gear, 'Ρυθμίσεις']]]);
  }
  $('#app').innerHTML = `
  <div class="shell${localStorage.cnpSideCollapsed === '1' ? ' collapsed' : ''}">
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
      </div>
    </aside>
    <div class="main">
      <div class="top">
        <div><h1 id="topTitle"></h1><small id="topSub"></small></div>
        <div class="top-acts">
          <button class="btn btn-o btn-ico" id="sideTgl" title="Μεγέθυνση/σμίκρυνση μενού"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 6h18M3 12h18M3 18h18"/></svg></button>
          <button id="remoteChip" style="display:none;border:0;border-radius:99px;background:var(--bad);color:#fff;font-weight:800;padding:7px 14px;cursor:pointer;font-size:12.5px" title="Κλικ για τερματισμό & χρέωση"></button>
          <button class="btn btn-o btn-sm" id="palBtn" title="Ctrl+K">${I.search} <span class="mut" style="font-size:10px">Ctrl K</span></button>
          <div class="bell-wrap"><button class="btn btn-o btn-ico" id="bellBtn" style="position:relative">${I.bell}
            <span class="bell-n" id="bellN" style="display:none"></span></button></div>
        </div>
      </div>
      <div class="content" id="content"></div>
    </div>
  </div>`;
  $$('.sitem').forEach(b => b.onclick = () => go(b.dataset.nav));
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
  };
  $('#bellBtn').onclick = toggleBell;
  const pb = $('#palBtn'); if (pb) pb.onclick = () => window.CNP.palette && window.CNP.palette();
  updateBell(S.boot.unread);
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
  ovl.onclick = e => { if (e.target === ovl) ovl.remove(); };
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
window.R.remotebook = async function () {
  setTop('Απομακρυσμένες', 'Αποθηκευμένες συνδέσεις πελατών — ένα κλικ για σύνδεση');
  const c = $('#content');
  c.innerHTML = '<div class="skel" style="height:300px"></div>';
  const d = await api('remote_book').catch(() => null);
  if (!d) { c.innerHTML = '<div class="empty"><div class="big">' + I.monitor + '</div>Δεν φορτώθηκε</div>'; return; }
  const rows = d.book || [];
  c.innerHTML = `
  <div class="card"><div class="card-h">${I.monitor} Οι συνδέσεις μου <span class="mut" style="font-weight:600">(${rows.length})</span>
    <span class="mut" style="font-weight:400;font-size:11px;margin-left:auto">κλικ «Σύνδεση» → ανοίγει το RustDesk έτοιμο</span></div>
    <div class="card-b" style="display:flex;flex-direction:column;gap:8px">
    ${rows.length ? rows.map(r => `
      <div style="display:flex;align-items:center;gap:12px;padding:10px 13px;border:1px solid var(--line);border-radius:11px">
        <span style="font-size:18px">${I.monitor}</span>
        <div style="flex:1;min-width:0">
          <div style="font-weight:700;font-size:13.5px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(r.name)}</div>
          <div class="mut" style="font-size:11.5px">ID: <b style="letter-spacing:1px">${esc((r.rustdesk_id || '').replace(/(\d{3})(?=\d)/g, '$1 '))}</b>${r.label ? ' · ' + esc(r.label) : ''}</div></div>
        <button class="btn btn-sm" data-rbgo="${r.clientid}" data-name="${esc(r.name)}" data-peer="${esc(r.rustdesk_id)}" style="background:var(--ok);color:#fff">${I.monitor} Σύνδεση</button>
        <button class="btn btn-sm btn-o" data-rbdel="${r.clientid}" title="Αφαίρεση" style="color:var(--bad)">${I.trash}</button>
      </div>`).join('')
      : `<div class="empty" style="padding:30px"><div class="big">${I.monitor}</div>Καμία αποθηκευμένη σύνδεση ακόμη.<br>
         <span class="mut" style="font-size:12px">Μόλις συνδεθείς σε έναν πελάτη (από ticket ή Πελάτη 360°), το ID του αποθηκεύεται εδώ αυτόματα.</span></div>`}
    </div></div>`;
  $$('[data-rbgo]').forEach(b => b.onclick = () => startRemote(+b.dataset.rbgo, b.dataset.name, 0, {savedPeer: b.dataset.peer}));
  $$('[data-rbdel]').forEach(b => b.onclick = async () => {
    if (!(await cnpConfirm('Αφαίρεση αυτής της σύνδεσης από τη λίστα;', {danger: true, ok: 'Αφαίρεση'}))) return;
    await api('remote_save_peer', {client: +b.dataset.rbdel, peer: ''});
    toast('Αφαιρέθηκε'); R.remotebook();
  });
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
        ${o.input !== null ? `<input class="inp" id="cnpDlgIn" placeholder="${esc(o.placeholder || '')}" value="${esc(o.input || '')}" style="margin-top:12px">` : ''}
        <div style="display:flex;gap:9px;margin-top:16px;justify-content:flex-end">
          <button class="btn btn-o" id="cnpDlgNo">${o.cancel}</button>
          <button class="btn ${o.danger ? '' : 'btn-p'}" id="cnpDlgOk" style="${o.danger ? 'background:var(--bad);color:#fff' : ''}">${o.ok}</button>
        </div>
      </div></div>`;
    document.body.appendChild(ovl);
    const inp = ovl.querySelector('#cnpDlgIn');
    const done = v => { ovl.remove(); document.removeEventListener('keydown', onKey); resolve(v); };
    const ok = () => done(o.input !== null ? (inp ? inp.value : '') : true);
    const onKey = e => {
      if (e.key === 'Escape') { e.stopPropagation(); done(o.input !== null ? null : false); }
      if (e.key === 'Enter' && (!inp || document.activeElement === inp)) { e.preventDefault(); ok(); }
    };
    document.addEventListener('keydown', onKey);
    ovl.querySelector('#cnpDlgOk').onclick = ok;
    ovl.querySelector('#cnpDlgNo').onclick = () => done(o.input !== null ? null : false);
    ovl.onclick = e => { if (e.target === ovl) done(o.input !== null ? null : false); };
    setTimeout(() => (inp || ovl.querySelector('#cnpDlgOk')).focus(), 30);
  });
}
const cnpConfirm = (body, opts) => cnpDialog(Object.assign({title: 'Επιβεβαίωση', body, ok: 'Ναι', cancel: 'Όχι'}, opts));
const cnpPrompt = (body, opts) => cnpDialog(Object.assign({title: '', body, input: '', ok: 'OK'}, opts));

function crmTabs(act) {
  const tabs = [['crm', I.funnel, 'Funnel'], ['crmov', I.chart, 'Επισκόπηση'], ['contacts', I.users, 'Επαφές'], ['comms', I.phone, 'Επικοινωνίες']];
  if (S.boot.me.full) tabs.push(['targets', I.target, 'Στόχοι προϊόντων']);
  return `<div class="ib-tabs" style="margin-bottom:16px;flex-wrap:wrap;border:0;background:0">
    ${tabs.map(([k, ic, l]) => `<button class="ib-tab ${act === k ? 'on' : ''}" data-crmtab="${k}"><span class="tico">${ic}</span>${l}</button>`).join('')}</div>`;
}
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
});
function setTop(t, sub) {
  $('#topTitle').textContent = t;
  $('#topSub').textContent = sub || new Date().toLocaleDateString('el-GR', {weekday: 'long', day: 'numeric', month: 'long'});
}
function go(view, arg) {
  S.view = view;
  location.hash = '#/' + view + (arg ? '/' + arg : '');
  $$('.sitem').forEach(b => b.classList.toggle('on', b.dataset.nav === view));
  const c = $('#content'); c.classList.remove('enter'); void c.offsetWidth; c.classList.add('enter');
  ((window.R && window.R[view]) || vMyDay)(arg);
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
  if (!S.project && S.boot.projects[0]) S.project = S.boot.projects[0].id;
  setTop('Board');
  const c = $('#content');
  if (!S.boot.projects.length) { c.innerHTML = `<div class="empty"><div class="big">${I.folder}</div>Δεν έχεις πρόσβαση σε κανένα project.</div>`; return; }
  c.innerHTML = `<div style="display:flex;gap:10px;margin-bottom:16px;align-items:center">
    <select class="inp" id="projSel" style="max-width:340px">
      ${S.boot.projects.map(p => `<option value="${p.id}" ${p.id === S.project ? 'selected' : ''}>${esc(p.name)}${p.clientName ? ' — ' + esc(p.clientName) : ''}</option>`).join('')}
    </select><div style="flex:1"></div></div>
    <div class="kb" id="kb">${S.boot.statuses.map(() => '<div class="skel" style="flex:1;min-height:300px"></div>').join('')}</div>`;
  $('#projSel').onchange = e => { S.project = +e.target.value; vBoard(); };
  const d = await api('board&project=' + S.project);
  const kb = $('#kb'); if (!kb) return;
  kb.innerHTML = d.columns.map(col => {
    const st = statusOf(col.status);
    return `<div class="kb-col" data-status="${st.id}">
      <div class="kb-h" style="border-color:${st.color}">${esc(st.title)}<span class="kb-n">${col.tasks.length}</span></div>
      <div class="kb-cards">${col.tasks.map(cardHtml).join('')}</div>
      <div class="kb-add"><input placeholder="+ Νέο task… (Enter)" data-status="${st.id}"></div>
    </div>`;
  }).join('');
  $$('.kb-add input', kb).forEach(inp => inp.onkeydown = async e => {
    if (e.key !== 'Enter' || !inp.value.trim()) return;
    const r = await api('quick_task', {project: S.project, status: +inp.dataset.status, title: inp.value.trim()});
    if (r.ok) { toast('Δημιουργήθηκε'); vBoard(); }
  });
}
function cardHtml(t) {
  const ty = t.type ? typeOf(t.type) : null;
  const over = t.due && t.due < today() && !t.done;
  return `<div class="tcard ${over ? 'overdue' : ''}" data-task="${t.id}">
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
    </div></div>`;
}
dnd('.tcard', '.kb-col', async (card, col) => {
  const r = await api('move_task', {task: +card.dataset.task, status: +col.dataset.status}).catch(() => ({ok: false}));
  if (r.ok) {
    col.querySelector('.kb-cards').appendChild(card);
    $$('.kb-col').forEach(c => c.querySelector('.kb-n').textContent = c.querySelectorAll('.tcard').length);
  } else toast('Δεν επιτρέπεται', true);
}, el => openTask(+el.dataset.task));

/* ═════════ TASK DRAWER ═════════ */
let timerInt = null;
async function openTask(id) {
  const d = await api('task&id=' + id).catch(() => null);
  if (!d) { toast('Δεν έχεις πρόσβαση', true); return; }
  closeDrawer();
  const t = d.task, me = S.boot.me;
  const ovl = document.createElement('div'); ovl.className = 'ovl'; ovl.onclick = closeDrawer;
  const dr = document.createElement('div'); dr.className = 'drawer';
  const admOpts = sel => '<option value="">— κανείς —</option>' + S.boot.admins.map(a => `<option value="${a.id}" ${a.id === +sel ? 'selected' : ''}>${esc(a.name)}</option>`).join('');
  dr.innerHTML = `
  <div class="drawer-h">
    <span class="dot" style="background:${d.project.color};width:12px;height:12px"></span>
    <h2>${esc(t.title)}</h2>
    <button class="btn btn-sm ${d.watching ? 'btn-p' : 'btn-o'}" id="dWatch">${I.eye}${d.watchers || ''}</button>
    <button class="drawer-x" id="dX">✕</button>
  </div>
  <div class="drawer-b">
    ${d.ticket ? `<a class="card" style="display:flex;padding:11px 15px;gap:9px;align-items:center;cursor:pointer" data-ibgo="${d.ticket.id}">${I.ticket}
      <b>#${esc(d.ticket.tid)}</b> ${esc(d.ticket.title)} <span class="pill pill-info" style="margin-left:auto">${esc(d.ticket.status)}</span></a>` : ''}
    <div class="card"><div class="card-b">
      <label class="lbl">Τίτλος</label>
      <input class="inp" id="fTitle" value="${esc(t.title)}">
      <div class="frow" style="margin-top:12px">
        <div><label class="lbl">Ανάθεση ${me.full ? '' : (I.lock)}</label>
          <select class="inp" id="fAssignee" ${me.full ? '' : 'disabled'}>${admOpts(t.assignee)}</select></div>
        <div><label class="lbl">⚡ Η μπάλα σε</label><select class="inp" id="fBall">${admOpts(t.ball)}</select></div>
        <div><label class="lbl">Προτεραιότητα ${me.full ? '' : (I.lock)}</label>
          <select class="inp" id="fPrio" ${me.full ? '' : 'disabled'}>
            ${['Κανονική', 'Υψηλή', 'Κρίσιμη'].map((p, i) => `<option value="${i}" ${i === t.prio ? 'selected' : ''}>${p}</option>`).join('')}</select></div>
        <div><label class="lbl">Τύπος</label><select class="inp" id="fType"><option value="">— γενικό —</option>
          ${S.boot.types.map(ty => `<option value="${ty.id}" ${ty.id === t.type ? 'selected' : ''}>${esc(ty.name)}</option>`).join('')}</select></div>
        <div><label class="lbl">Έναρξη (Gantt)</label><input type="date" class="inp" id="fStart" value="${t.start || ''}"></div>
        <div><label class="lbl">Λήξη</label><input type="date" class="inp" id="fDue" value="${t.due || ''}"></div>
        <div><label class="lbl">Πλάνο (πότε θα το δουλέψω)</label><input type="date" class="inp" id="fSched" value="${t.sched || ''}"></div>
      </div>
      <label class="lbl" style="margin-top:12px">Περιγραφή</label>
      <textarea class="inp" id="fDescr" rows="3">${esc(d.descr || '')}</textarea>
      <div style="display:flex;gap:9px;margin-top:13px;align-items:center">
        <button class="btn btn-p" id="dSave">Αποθήκευση</button>
        ${me.full && t.assignee && t.assignee !== me.id ? '<button class="btn btn-o" id="dAsk">❓ Ζήτα ενημέρωση</button>' : ''}
        <span class="mut" style="margin-left:auto;font-size:12px">${esc(d.project.name)}</span>
      </div>
    </div></div>

    <div class="card"><div class="card-h">⏱ Χρόνος <span class="mut" style="font-weight:600">${fmtMin(d.total)}${t.est ? ' / ~' + fmtMin(t.est) : ''}</span>
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
      ${d.timelogs.length ? `<div style="margin-top:12px">${d.timelogs.slice(0, 6).map(l =>
        `<div style="display:flex;gap:9px;font-size:12px;padding:4px 0" class="${l.running ? '' : ''}">
          <b>${l.running ? '▶ σε εξέλιξη' : fmtMin(l.mins)}</b>
          ${l.billable ? `<span class="pill pill-warn">χρέωση ${fmtMin(l.charged || l.mins)}</span>` : '<span class="mut">χωρίς χρέωση</span>'}
          <span class="mut">${esc(l.by)}${l.note ? ' · ' + esc(l.note) : ''}</span>
          <span class="mut" style="margin-left:auto">${tShort(l.at)}</span></div>`).join('')}</div>` : ''}
    </div></div>

    <div class="card"><div class="card-h">${I.link} Εξαρτήσεις <span class="mut" style="font-weight:600;font-size:11px">— πρέπει να τελειώσουν πρώτα</span></div>
      <div class="card-b" id="dDeps">
      ${(d.deps || []).map(dp => `<div style="display:flex;gap:8px;align-items:center;padding:4px 0">
        <span>${dp.done ? '✅' : '⏳'}</span>
        <a style="flex:1;cursor:pointer" data-dgo="${dp.id}">${esc(dp.title)}</a>
        <button class="btn btn-sm btn-o" data-ddel="${dp.depId}">✕</button></div>`).join('')}
      <div style="display:flex;gap:7px;margin-top:8px">
        <select class="inp" id="depSel" style="flex:1"><option value="">— διάλεξε task που μας μπλοκάρει —</option></select>
        <button class="btn btn-sm btn-o" id="depAdd">+</button></div>
    </div></div>

    <div class="card"><div class="card-h">${I.clip} Αρχεία</div><div class="card-b" id="dFiles">
      <div class="mut" style="font-size:12px">Φόρτωση…</div></div></div>

    <div class="card"><div class="card-h">${I.checkSquare} Checklist</div><div class="card-b" id="dCheck">
      ${d.check.map(it => `<div class="chk ${it.done ? 'done' : ''}"><input type="checkbox" data-chk="${it.id}" ${it.done ? 'checked' : ''}><span>${esc(it.title)}</span></div>`).join('')}
      <div style="display:flex;gap:8px;margin-top:9px">
        <input class="inp" id="chkNew" placeholder="Νέο βήμα… (Enter)"></div>
    </div></div>

    <div class="card"><div class="card-h">${I.chat} Συνομιλία</div><div class="card-b">
      <div id="dMsgs">${d.comments.map(cm => `<div class="msg ${cm.byId === me.id ? 'mine' : ''}">
        <div class="msg-h">${esc(cm.by)}${cm.to !== null ? ` <span class="pill pill-info">προς: ${cm.to === -1 ? 'Διαχειριστές' : esc(adminName(cm.to))}</span>` : ''}
          <span class="mut">${tShort(cm.at)}</span></div>
        <div class="msg-b">${esc(cm.body)}</div></div>`).join('') || '<div class="mut" style="font-size:12.5px">Καμία κουβέντα ακόμη.</div>'}</div>
      <div style="display:flex;gap:8px;margin-top:12px">
        <input class="inp" id="cmBody" placeholder="Γράψε μήνυμα… (Enter)" style="flex:1">
        <select class="inp" id="cmTo" style="width:150px"><option value="">— απλό —</option>
          <option value="-1">Διαχειριστές (όλοι)</option>
          ${S.boot.admins.filter(a => a.id !== me.id).map(a => `<option value="${a.id}">${esc(a.name)}</option>`).join('')}</select>
      </div>
    </div></div>

    <details class="card"><summary class="card-h" style="cursor:pointer">${I.clock} Ιστορικό (${d.activity.length})</summary>
      <div class="card-b">${d.activity.map(a => `<div style="font-size:12px;padding:4px 0;border-bottom:1px dashed var(--line)">
        <b>${esc(a.detail || a.action)}</b> <span class="mut">— ${esc(a.by)} · ${tShort(a.at)}</span></div>`).join('')}</div></details>
  </div>`;
  document.body.append(ovl, dr);
  requestAnimationFrame(() => { ovl.classList.add('show'); dr.classList.add('show'); });

  $('#dX').onclick = closeDrawer;
  $('#dSave', dr).onclick = async () => {
    await api('save_task', {task: id, title: $('#fTitle').value, descr: $('#fDescr').value,
      due: $('#fDue').value || null, sched: $('#fSched').value || null, start: $('#fStart').value || null,
      type: +$('#fType').value || 0, ball: +$('#fBall').value || 0,
      assignee: +$('#fAssignee').value || 0, prio: +$('#fPrio').value});
    toast('Αποθηκεύτηκε'); closeDrawer(); if (S.view === 'board') vBoard(); if (S.view === 'myday') vMyDay();
  };
  $('#dWatch', dr).onclick = async () => {
    const r = await api('watch', {task: id});
    toast(r.watching ? 'Παρακολουθείς το task' : 'Σταμάτησες την παρακολούθηση'); openTask(id);
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
  /* deps */
  api('board&project=' + d.project.id).then(bd => {
    const sel = $('#depSel', dr);
    if (!sel) return;
    bd.columns.forEach(col => col.tasks.forEach(tt => {
      if (tt.id !== id) sel.innerHTML += `<option value="${tt.id}">${esc(tt.title)}</option>`;
    }));
  });
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
  const renderFiles = async () => {
    const ff = await api('files&task=' + id);
    const fb = $('#dFiles', dr);
    if (!fb) return;
    const fmtSize = s => s > 1048576 ? (s / 1048576).toFixed(1) + 'MB' : Math.round(s / 1024) + 'KB';
    fb.innerHTML = ff.files.map(f => `<div style="display:flex;gap:8px;align-items:center;padding:4px 0">
        <span>${I.fileText} </span><a href="api.php?a=file_get&id=${f.id}" style="flex:1">${esc(f.name)}</a>
        <span class="mut" style="font-size:11px">${fmtSize(f.size)} · ${esc(f.by)}</span>
        <button class="btn btn-sm btn-o" data-fdel="${f.id}">✕</button></div>`).join('') +
      `<label class="btn btn-o btn-sm" style="margin-top:8px;cursor:pointer">${I.clip} Ανέβασμα αρχείου
        <input type="file" id="fUp" style="display:none"></label>
       <span class="mut" style="font-size:11px;margin-left:7px">έως 20MB</span>`;
    $('#fUp', fb).onchange = async e => {
      const file = e.target.files[0];
      if (!file) return;
      const fd = new FormData();
      fd.append('task', id); fd.append('file', file);
      toast('Ανεβαίνει…');
      const r = await fetch('api.php?a=file_upload', {method: 'POST', body: fd, credentials: 'same-origin'}).then(x => x.json());
      if (r.ok) { toast('Ανέβηκε'); renderFiles(); } else toast(r.error || 'Σφάλμα', true);
    };
    $$('[data-fdel]', fb).forEach(b => b.onclick = async () => {
      await api('file_del', {id: +b.dataset.fdel}); renderFiles();
    });
  };
  renderFiles();
  $('#chkNew', dr).onkeydown = async e => {
    if (e.key !== 'Enter' || !e.target.value.trim()) return;
    await api('check_add', {task: id, title: e.target.value.trim()}); openTask(id);
  };
  $$('#dCheck input[data-chk]', dr).forEach(cb => cb.onchange = async () => {
    await api('check_toggle', {id: +cb.dataset.chk});
    cb.closest('.chk').classList.toggle('done', cb.checked);
  });
  $('#cmBody', dr).onkeydown = async e => {
    if (e.key !== 'Enter' || !e.target.value.trim()) return;
    await api('comment', {task: id, body: e.target.value.trim(), to: $('#cmTo').value === '' ? null : +$('#cmTo').value});
    toast('Στάλθηκε'); openTask(id);
  };
}
function closeDrawer() {
  clearInterval(timerInt);
  $$('.ovl,.drawer').forEach(el => { el.classList.remove('show'); setTimeout(() => el.remove(), 300); });
}

/* ═════════ Η ΜΕΡΑ ΜΟΥ ═════════ */
async function vMyDay() {
  setTop('Η μέρα μου', 'Καλημέρα, ' + S.boot.me.name.split(' ')[0] + ' ☀️');
  const c = $('#content');
  c.innerHTML = '<div class="grid g4">' + '<div class="skel" style="height:90px"></div>'.repeat(4) + '</div>';
  const d = await api('myday');
  const st = d.stats;
  setTimeout(loadMyNext, 50);   // ▶ επόμενο ticket (lazy, μετά το render)
  const coachCol = {bad: 'var(--bad)', warn: 'var(--warn)', tip: 'var(--brand)', ok: 'var(--ok)'};
  const coach = d.coach || [];
  c.innerHTML = `
  ${coach.length ? `<div class="card coach" style="margin-bottom:14px"><div class="card-h">${I.compass} Καθοδήγηση για σένα</div>
    <div class="card-b" style="display:flex;flex-direction:column;gap:8px">
    ${coach.map(x => `<div style="display:flex;gap:10px;align-items:flex-start;padding:8px 11px;border-radius:10px;
      background:${coachCol[x.lvl]}14;border-left:3px solid ${coachCol[x.lvl]}">
      <span style="font-size:16px;line-height:1.4;flex:none">${x.icon}</span>
      <span style="font-size:13px;line-height:1.5">${esc(x.text)}</span></div>`).join('')}
    </div></div>` : ''}
  <div class="card" id="myNext" style="display:none;margin-bottom:14px;border-left:4px solid var(--brand)"></div>
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
      ${!d.plan.length && !d.balls.length && !d.follows.length ? '<div class="card"><div class="empty"><div class="big">🏖️</div>Καθαρή μέρα — τίποτα προγραμματισμένο!</div></div>' : ''}
    </div>
    <div>
      <div class="card"><div class="card-h">${I.ticket} Τα tickets μου <span class="mut" style="font-weight:600">(${d.tickets.length})</span></div>
      ${d.tickets.length ? d.tickets.map(tk => `<a class="trow" data-ibgo="${tk.id}" style="color:inherit;cursor:pointer">
        <div style="flex:1"><b style="font-size:13px">#${esc(tk.tid)}</b> ${esc(tk.title)}
          <div class="mut" style="font-size:11px">${esc(tk.status)} · ${tk.age} ημ.</div></div>
        ${tk.slaDue ? `<span class="pill ${tk.over ? 'pill-bad' : 'pill-warn'}">SLA ${tShort(tk.slaDue)}</span>` : ''}
      </a>`).join('') : '<div class="empty" style="padding:24px">Κανένα ανοιχτό δικό σου 🎉</div>'}</div>
    </div>
  </div>`;
  $$('#content .trow[data-task]').forEach(r => r.onclick = () => openTask(+r.dataset.task));
}

/* ═════════ CRM ═════════ */
function loadMyNext() {
  api('mynext').then(r => {
    const el = $('#myNext'); if (!el || !r.next.length) return;
    el.style.display = '';
    el.innerHTML = `<div class="card-h">▶ Τι δουλεύω τώρα <span class="mut" style="margin-left:auto;font-size:11px;font-weight:400">κρισιμότητα · αναμονή · SLA</span></div>
      <div class="card-b">${r.next.map((t, i) => `
        <div class="set-row" data-nxgo="${t.id}" style="cursor:pointer">
          <b style="width:22px;text-align:center;color:${i === 0 ? 'var(--bad)' : 'var(--mut)'}">${i + 1}</b>
          <div style="flex:1;min-width:0"><b style="font-size:12.5px">#${esc(t.tid)} — ${esc(t.title)}</b>
            <span class="mut" style="font-size:11px"> · ${esc(t.client || '—')}</span>
            ${!t.mine ? '<span class="pill pill-warn" style="font-size:9.5px">χωρίς ανάθεση</span>' : ''}
            ${t.waiting ? '<span class="pill pill-bad" style="font-size:9.5px">περιμένει</span>' : ''}</div>
          <b style="color:${t.score >= 45 ? 'var(--bad)' : 'var(--warn)'}">${t.score}</b></div>`).join('')}</div>`;
    el.querySelectorAll('[data-nxgo]').forEach(x => x.onclick = () => go('inbox', +x.dataset.nxgo));
  }).catch(() => {});
}
async function vCrm() {
  setTop('CRM', 'Pipeline πωλήσεων — στόχοι → επαφή → πελάτες');
  const c = $('#content');
  const f = vCrm._f = vCrm._f || {fa: '', src: '', q: ''};
  c.innerHTML = crmTabs('crm') + '<div class="kb">' + '<div class="skel" style="flex:1;min-height:280px"></div>'.repeat(5) + '</div>';
  const d = await api('crm');
  const pct = d.target > 0 ? Math.min(100, Math.round(d.won / d.target * 100)) : 0;
  const flt = l => (!f.fa || String(l.assignee || '') === f.fa)
    && (!f.src || (l.source || '').toLowerCase().includes(f.src.toLowerCase()))
    && (!f.q || ((l.company || '') + ' ' + (l.contact || '') + ' ' + (l.email || '') + ' ' + (l.phone || '')).toLowerCase().includes(f.q.toLowerCase()));
  const leads = d.leads.filter(flt);
  const sources = [...new Set(d.leads.map(l => l.source).filter(Boolean))];
  c.innerHTML = crmTabs('crm') + `
  <div style="display:flex;gap:14px;align-items:center;margin-bottom:14px;flex-wrap:wrap">
    <div class="card" style="flex:1;min-width:280px;margin:0;padding:14px 18px">
      <div style="display:flex;justify-content:space-between;align-items:baseline">
        <b style="color:var(--ink);display:inline-flex;align-items:center;gap:7px">${I.target} Πωλήσεις μήνα</b>
        <span><b style="font-size:18px;color:var(--ink)">${fmtEur(d.won)}</b>
        ${d.target > 0 ? `<span class="mut"> / ${fmtEur(d.target)}</span>` : ''}</span></div>
      ${d.target > 0 ? `<div class="bar" style="margin-top:9px"><span class="${pct >= 100 ? 'ok' : ''}" style="width:${pct}%"></span></div>` : ''}
    </div>
    <select class="inp" id="cfA" style="width:auto"><option value="">— χειριστής —</option>
      ${S.boot.admins.map(a => `<option value="${a.id}" ${f.fa == a.id ? 'selected' : ''}>${esc(a.name)}</option>`).join('')}</select>
    <select class="inp" id="cfS" style="width:auto"><option value="">— πηγή —</option>
      ${sources.map(x => `<option ${f.src === x ? 'selected' : ''}>${esc(x)}</option>`).join('')}</select>
    <input class="inp" id="cfQ" placeholder="αναζήτηση… (Enter)" value="${esc(f.q)}" style="width:170px">
    <button class="btn btn-p" id="newLead">${I.plus} Νέο lead</button>
  </div>
  <div class="kb" id="crmKb" style="min-height:calc(100vh - 340px)">
    ${d.stages.map(sg => {
      const sl = leads.filter(l => l.stage === sg.key);
      const val = sl.reduce((t, l) => t + (l.value || 0), 0);
      return `<div class="kb-col lcol" data-stage="${sg.key}">
        <div class="kb-h" style="border-color:${sg.color}">${esc(sg.title)}<span class="kb-n">${sl.length}</span>
          ${val ? `<span class="mut" style="margin-left:auto;font-size:10.5px">${fmtEur(val)}</span>` : ''}</div>
        <div class="kb-cards">${sl.map(l => `
          <div class="tcard lcard ${l.next && l.next <= today() && !sg.closed ? 'overdue' : ''}" data-lead="${l.id}">
            <div class="tcard-t">${esc(l.company || l.contact || '—')}</div>
            <div class="tcard-m">
              ${l.contact && l.company ? `<span>${I.user} ${esc(l.contact)}</span>` : ''}
              ${l.phone ? `<span>${I.phone} ${esc(l.phone)}</span>` : ''}
              ${l.value ? `<span class="pill pill-ok" style="font-weight:700">${fmtEur(l.value)}</span>` : ''}
              ${l.source ? `<span class="pill pill-mut">${esc(l.source)}</span>` : ''}
              ${l.next && !sg.closed ? `<span class="${l.next <= today() ? 'pill pill-bad' : ''}">${I.bell} ${dShort(l.next)}</span>` : ''}
              ${!l.next && !sg.closed ? `<span class="pill pill-warn" title="Χωρίς επόμενη ενέργεια">${I.snow} </span>` : ''}
              ${l.client ? '<span class="pill pill-ok">✓ πελάτης</span>' : ''}
              ${sg.key === 'lost' && l.lostReason ? `<span class="pill pill-bad" title="${esc(l.lostReason)}">${I.chat} </span>` : ''}
            </div></div>`).join('')}</div>
      </div>`;
    }).join('')}
  </div>`;
  $('#cfA').onchange = () => { f.fa = $('#cfA').value; vCrm(); };
  $('#cfS').onchange = () => { f.src = $('#cfS').value; vCrm(); };
  $('#cfQ').onkeydown = e => { if (e.key === 'Enter') { f.q = $('#cfQ').value.trim(); vCrm(); } };
  $('#newLead').onclick = () => openLead(null, d);
  dndLead(d);
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
      const r = await api('move_lead', {lead: +card.dataset.lead, stage: col.dataset.stage, reason}).catch(() => ({ok: false}));
      if (r.ok) { col.querySelector('.kb-cards').appendChild(card);
        $$('.lcol').forEach(c => c.querySelector('.kb-n').textContent = c.querySelectorAll('.tcard').length);
      } else toast('Δεν επιτρέπεται', true);
    }, async el => { const d = await api('crm'); openLead(d.leads.find(l => l.id === +el.dataset.lead), d); });
  }
}
function openLead(l, d) {
  closeDrawer();
  const isNew = !l; l = l || {stage: 'target'};
  const ovl = document.createElement('div'); ovl.className = 'ovl'; ovl.onclick = closeDrawer;
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
      <textarea class="inp" id="lDescr" rows="4">${esc(l.descr || '')}</textarea>
      <div style="margin-top:13px"><button class="btn btn-p" id="lSave">Αποθήκευση</button></div>
    </div></div>
    ${!isNew ? `<div class="card"><div class="card-h">${I.puzzle} Επιπλέον στοιχεία</div><div class="card-b" id="lFieldsBox">
      <div class="mut" style="font-size:12px">Φόρτωση…</div></div></div>
    <div class="card"><div class="card-h">${I.users} Πρόσωπα επαφής</div><div class="card-b" id="lPeopleBox">
      <div class="mut" style="font-size:12px">Φόρτωση…</div></div></div>
    <div class="card"><div class="card-h">${I.phone} Γρήγορη καταγραφή επικοινωνίας</div><div class="card-b">
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <select class="inp" id="iKind" style="width:140px">
          <option value="call">Τηλεφώνημα</option><option value="email">Email</option>
          <option value="meeting">Συνάντηση</option><option value="note">Σημείωση</option></select>
        <input class="inp" id="iSum" placeholder="τι ειπώθηκε…" style="flex:1;min-width:160px">
        <input type="date" class="inp" id="iFup" style="width:150px" title="follow-up">
        <button class="btn btn-o" id="iSave">Καταγραφή</button>
      </div></div></div>` : ''}
  </div>`;
  document.body.append(ovl, dr);
  requestAnimationFrame(() => { ovl.classList.add('show'); dr.classList.add('show'); });
  $('#dX').onclick = closeDrawer;
  if (!isNew) {
    loadLeadExtras(l.id, dr);
  }
  $('#lSave', dr).onclick = async () => {
    await api('save_lead', {lead: l.id || 0, company: $('#lCompany').value, contact: $('#lContact').value,
      email: $('#lEmail').value, phone: $('#lPhone').value, source: $('#lSource').value,
      stage: $('#lStage').value, assignee: +$('#lAssignee').value || 0,
      value: $('#lValue').value.trim(), lostReason: $('#lLost').value,
      next: $('#lNext').value || null, nextNote: $('#lNextNote').value, descr: $('#lDescr').value});
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
        <div class="grid" style="grid-template-columns:1fr 1fr;gap:10px">
          <div class="stat ok" style="margin:0"><b>${fmtEur(d.month.won)}</b><small>Κερδισμένες προσφορές</small></div>
          <div class="stat warn" style="margin:0"><b>${fmtEur(d.month.laborCost)}</b><small>Κόστος εργασίας (${fmtMin(d.month.minutes)})</small></div>
          <div class="stat warn" style="margin:0"><b>${fmtEur(d.month.expenses)}</b><small>Έξοδα projects</small></div>
          <div class="stat ${net >= 0 ? 'ok' : 'bad'}" style="margin:0"><b>${fmtEur(net)}</b><small>Καθαρό</small></div>
        </div></div></div>
    </div>
  </div>`;
}

/* ───────── exports για views2.js ───────── */
window.CNP = {S, api, esc, suStat, fmtMin, fmtEur, dShort, tShort, today, toast, setTop, go, crmTabs, openLead, cnpConfirm, cnpPrompt, cnpDialog, startRemote,
  adminName, adminIni, statusOf, typeOf, dnd, I, openTask, closeDrawer, updateBell, $, $$};

/* ───────── init ───────── */
(async function init() {
  try {
    S.boot = await api('boot');
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
    if (h && h[1] !== S.view) go(h[1], h[2]);
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
