/**
 * Μετράει τη διάταξη της καρτέλας εργασίας σε πραγματικό browser, αντί να την εικάζει.
 * Κάθε κανόνας είναι αναλλοίωτο που ΠΡΕΠΕΙ να ισχύει — κανονικά και σε μεγέθυνση.
 */
const {chromium} = require('/opt/cloudon-visual-qa/node_modules/playwright');
const path = 'file://' + __dirname + '/tk.html';

const SIZES = [[1920, 1080], [1440, 900], [1280, 800]];
let fails = 0;

const ck = (label, ok, detail) => {
  if (!ok) { fails++; }
  console.log(`  ${ok ? '✓' : '✗'} ${label.padEnd(52)} ${detail || ''}`);
};

(async () => {
  const browser = await chromium.launch();
  for (const [w, h] of SIZES) {
    const page = await browser.newPage({viewport: {width: w, height: h}});
    await page.goto(path);
    await page.waitForTimeout(150);

    for (const max of [false, true]) {
      if (max) { await page.click('#maxBtn'); await page.waitForTimeout(120); }
      console.log(`\n── ${w}×${h} · ${max ? 'ΜΕΓΕΘΥΝΣΗ' : 'κανονικά'} ──`);

      const m = await page.evaluate(() => {
        const r = s => { const e = document.querySelector(s); return e ? e.getBoundingClientRect() : null; };
        const body = document.querySelector('.tk-modal-b');
        const list = document.querySelector('.tk-step-list');
        const txt = document.querySelector('[data-ctext="2"]');
        const row = txt ? txt.closest('.chk') : null;
        const side = document.querySelector('.tk-col-side');
        return {
          bodyRect: r('.tk-modal-b'), foot: r('.tk-step-foot'), input: r('#chkNew'),
          /* Το ωφέλιμο πλάτος: το border-box περιλαμβάνει το padding του drawer-b. */
          bodyInner: (() => {
            const b = document.querySelector('.tk-modal-b'); if (!b) { return 0; }
            const cs = getComputedStyle(b);
            return b.getBoundingClientRect().width - parseFloat(cs.paddingLeft) - parseFloat(cs.paddingRight);
          })(),
          stepCard: r('.tk-step'), brief: r('.tk-brief'),
          sideVisible: side ? getComputedStyle(side).display !== 'none' : false,
          briefVisible: (() => { const b = document.querySelector('.tk-brief');
            return b ? getComputedStyle(b).display !== 'none' : false; })(),
          sideRect: r('.tk-col-side'), saveBtn: r('#saveBtn'),
          txtW: txt ? txt.getBoundingClientRect().width : 0,
          rowW: row ? row.getBoundingClientRect().width : 0,
          listScrolls: list ? list.scrollHeight > list.clientHeight + 2 : false,
          bodyOverflows: body ? body.scrollHeight > body.clientHeight + 2 : false,
          briefScrollsInside: (() => {
            const b = document.querySelector('.tk-brief > .card-b');
            return b ? b.scrollHeight > b.clientHeight + 2 : false;
          })(),
          docOverflows: document.documentElement.scrollHeight > window.innerHeight + 2,
          /* Η αναλογία: οι ενέργειες είναι ο χώρος εργασίας, όχι υποσημείωση. */
          colH: (document.querySelector('.tk-col-main') || {}).clientHeight || 0,
          stepH: (document.querySelector('.tk-step') || {}).clientHeight || 0,
          visibleRows: [...document.querySelectorAll('.tk-step-list .chk')].filter(el => {
            const l = document.querySelector('.tk-step-list').getBoundingClientRect();
            const r = el.getBoundingClientRect();
            return r.top >= l.top - 1 && r.bottom <= l.bottom + 1;
          }).length,
        };
      });

      const inBody = m.input && m.bodyRect
        && m.input.bottom <= m.bodyRect.bottom + 1 && m.input.top >= m.bodyRect.top - 1;
      ck('το πεδίο γραφής είναι μέσα στην καρτέλα', !!inBody,
        m.input ? `input.bottom=${Math.round(m.input.bottom)} body.bottom=${Math.round(m.bodyRect.bottom)}` : 'λείπει');
      ck('το πεδίο έχει ύψος (δεν είναι ψαλιδισμένο)', !!(m.input && m.input.height > 20),
        m.input ? `h=${Math.round(m.input.height)}` : '');
      ck('το κείμενο βήματος πιάνει ≥85% της γραμμής',
        m.rowW > 0 && m.txtW / m.rowW >= 0.85,
        `${Math.round(m.txtW)}px από ${Math.round(m.rowW)}px = ${Math.round(m.txtW / m.rowW * 100)}%`);
      ck('τίποτα δεν ξεχειλίζει από το σώμα', !m.bodyOverflows);
      ck('οι ενέργειες παίρνουν ≥55% της στήλης',
        m.colH > 0 && m.stepH / m.colH >= 0.55,
        `${Math.round(m.stepH)}px από ${Math.round(m.colH)}px = ${Math.round(m.stepH / m.colH * 100)}%`);
      ck('φαίνονται ≥2 ολόκληρα βήματα χωρίς κύλιση', m.visibleRows >= 2, m.visibleRows + ' βήματα');
      ck('η σελίδα δεν κυλά (η καρτέλα κρατά το ύψος)', !m.docOverflows);
      if (max) {
        ck('στη μεγέθυνση κρύβεται η δεξιά στήλη', !m.sideVisible);
        ck('στη μεγέθυνση κρύβεται και το «ζητούμενο»', !m.briefVisible);
        ck('οι ενέργειες πιάνουν όλο το ωφέλιμο πλάτος',
          !!(m.stepCard && m.stepCard.width >= m.bodyInner - 2),
          `${Math.round(m.stepCard.width)} / ${Math.round(m.bodyInner)} ωφέλιμα`);
      } else {
        ck('η δεξιά στήλη είναι ορατή και ακέραιη',
          !!(m.sideVisible && m.saveBtn && m.saveBtn.bottom <= m.bodyRect.bottom + 1),
          m.saveBtn ? `κουμπί «Αποθήκευση» στο y=${Math.round(m.saveBtn.bottom)}` : 'λείπει');
        ck('οι ενέργειες ΔΕΝ καλύπτουν τη δεξιά στήλη',
          !!(m.stepCard && m.sideRect && m.stepCard.right <= m.sideRect.left + 1),
          `step.right=${Math.round(m.stepCard.right)} side.left=${Math.round(m.sideRect.left)}`);
        ck('το μεγάλο «ζητούμενο» κυλά μέσα του', m.briefScrollsInside);
      }
    }
    await page.close();
  }
  await browser.close();
  console.log(`\n${fails === 0 ? '✅ ΟΛΑ ΣΩΣΤΑ' : '❌ ' + fails + ' αποτυχίες'}`);
  process.exit(fails ? 1 : 0);
})();
