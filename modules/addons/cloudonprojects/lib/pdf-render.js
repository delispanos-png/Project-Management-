/* Παράγει PDF από HTML με το Chromium του Playwright (pixel-perfect όπως το print).
   Χρήση: node pdf-render.js <htmlPath> <pdfPath>
   Env:   PLAYWRIGHT_BROWSERS_PATH=/opt/pw-browsers (browsers προσβάσιμα από τον FPM user)
   Το playwright φορτώνεται από το ήδη εγκατεστημένο QA setup. */
const fs = require('fs');
const { chromium } = require('/opt/cloudon-visual-qa/node_modules/playwright');

(async () => {
  const htmlPath = process.argv[2];
  const pdfPath = process.argv[3];
  if (!htmlPath || !pdfPath) { console.error('usage: pdf-render.js <html> <pdf>'); process.exit(2); }
  const html = fs.readFileSync(htmlPath, 'utf8');
  const browser = await chromium.launch({ args: ['--no-sandbox', '--disable-dev-shm-usage'] });
  try {
    const page = await browser.newPage();
    // networkidle: να προλάβουν να φορτώσουν τα logos (https://my.cloudon.gr/project/doc-assets/*)
    await page.setContent(html, { waitUntil: 'networkidle', timeout: 30000 });
    await page.pdf({ path: pdfPath, format: 'A4', printBackground: true,
      preferCSSPageSize: true, margin: { top: '0', right: '0', bottom: '0', left: '0' } });
  } finally {
    await browser.close();
  }
})().catch(e => { console.error(e && e.stack || e); process.exit(1); });
