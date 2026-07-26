/**
 * CloudOn Projects — i18n (EL default / EN)
 *
 * Το SPA είναι γραμμένο με ελληνικά κείμενα inline (~1300 σημεία). Αντί να
 * αλλάξουμε κάθε σημείο κλήσης, μεταφράζουμε στο DOM μετά από κάθε render:
 *  • ΜΟΝΟ πλήρης ταύτιση ολόκληρου text node (όχι υπο-συμβολοσειρές) → δεν
 *    πειράζει δεδομένα χρήστη (ονόματα πελατών, τίτλους ticket κ.λπ.)
 *  • ίδια λογική για placeholder/title/aria-label
 *  • idempotent: το μεταφρασμένο κείμενο δεν ταιριάζει ξανά σε ελληνικό κλειδί
 *  • αλλαγή γλώσσας → reload (καθαρή επαναφορά, χωρίς μισομεταφρασμένο state)
 * Ό,τι δεν υπάρχει στο λεξικό μένει ελληνικά (graceful degradation).
 */
(function () {
  'use strict';

  var DICT = {
    /* ── ομάδες μενού (το CSS τα κεφαλαιοποιεί — το DOM τα έχει κανονικά) ── */
    'Εργασία': 'Work', 'Υποστήριξη': 'Support', 'Έργα': 'Projects', 'Πωλήσεις': 'Sales',
    'Διοίκηση': 'Management', 'Προσλήψεις': 'Recruiting', 'Βοήθεια': 'Help',
    /* ── μενού / πλοήγηση ── */
    'Η μέρα μου': 'My day', 'Το πλάνο μου': 'My plan', 'Η βιβλιοθήκη μου': 'My library',
    'Κωδικοί': 'Passwords', 'Ημερολόγιο': 'Calendar', 'Απομακρυσμένες': 'Remote sessions',
    'Γνώση': 'Knowledge', 'Πελάτης 360°': 'Client 360°', 'Λίστα tasks': 'Task list',
    'Χρόνος': 'Time', 'Προσφορές': 'Quotes', 'Πλάνο ημέρας': 'Day plan',
    'Ανάλυση ριζών': 'Root cause analysis', 'Κερδοφορία': 'Profitability', 'Ομάδες': 'Teams',
    'Ρυθμίσεις': 'Settings', 'Βιογραφικά': 'CVs', 'Οδηγός χρήσης': 'User guide',
    /* ── κάτω μπάρα (σύντομα) ── */
    'Σήμερα': 'Today', 'Ατζέντα': 'Agenda', 'Πλάνο': 'Plan', 'Βιβλιοθήκη': 'Library',
    'Απομακρ.': 'Remote', 'Πελάτης': 'Client', 'Πλάνο ημ.': 'Day plan', 'Ρίζες': 'Root causes',
    'Κέρδη': 'Profit', 'Οδηγός': 'Guide', 'Μενού': 'Menu',
    /* ── λογαριασμός ── */
    'Το προφίλ μου': 'My profile', 'Σκοτεινό θέμα': 'Dark theme', 'Φωτεινό θέμα': 'Light theme',
    'Αποσύνδεση': 'Sign out', 'Γλώσσα': 'Language', 'Ελληνικά': 'Greek', 'Αγγλικά': 'English',
    'Ο λογαριασμός μου': 'My account', 'Διαχειριστής': 'Administrator', 'Χειριστής': 'Operator',
    /* ── γενικές ενέργειες ── */
    'Νέο': 'New', 'Αποθήκευση': 'Save', 'Αποθήκευση ρυθμίσεων': 'Save settings',
    'Άκυρο': 'Cancel', 'Διαγραφή': 'Delete', 'Αποστολή': 'Send', 'Κλείσιμο': 'Close',
    'Επεξεργασία': 'Edit', 'Προβολή': 'View', 'Αναζήτηση': 'Search', 'Αναζήτηση…': 'Search…',
    'Δημιουργία': 'Create', 'Πίσω': 'Back', 'Ενημέρωση': 'Update', 'Προσθήκη': 'Add',
    'Αφαίρεση': 'Remove', 'Αντιγραφή': 'Copy', 'Λήψη': 'Download', 'Άνοιγμα': 'Open',
    'Επιλογή': 'Select', 'Όλα': 'All', 'Όλες': 'All', 'Ναι': 'Yes', 'Όχι': 'No',
    'Σύνδεση': 'Connect', 'Επιστροφή': 'Back', 'Ολοκληρώθηκε': 'Completed',
    /* ── καταστάσεις / χρόνος ── */
    'Ανοιχτά': 'Open', 'Κλειστά': 'Closed', 'Δικά μου': 'Mine', 'Χωρίς ανάθεση': 'Unassigned',
    'Περιμένουν': 'Waiting', 'περιμένει': 'waiting', 'Ενεργό': 'Active', 'Ανενεργό': 'Inactive',
    'Ενεργή': 'Active', 'Ανενεργή': 'Inactive', 'Σε αναστολή': 'Suspended', 'Αναστολή': 'Suspended',
    'σήμερα': 'today', 'εμένα': 'me', 'Σήμερα;': 'Today?', 'Καθυστερημένα': 'Overdue',
    'Εκπρόθεσμο': 'Overdue', 'εκπρόθεσμο': 'overdue', 'Ολοήμερο': 'All day',
    /* ── ημερολόγιο ── */
    'Ημερολόγιο ομάδας': 'Team calendar', 'Νέο συμβάν': 'New event', 'Νέο εδώ': 'New here',
    'Meeting': 'Meeting', 'Ραντεβού': 'Appointment', 'Άδεια': 'Leave', 'Άλλο': 'Other',
    'Καμία δραστηριότητα αυτή τη μέρα.': 'No activity on this day.',
    'Δευ': 'Mon', 'Τρί': 'Tue', 'Τετ': 'Wed', 'Πέμ': 'Thu', 'Παρ': 'Fri', 'Σάβ': 'Sat', 'Κυρ': 'Sun',
    'Ιανουάριος': 'January', 'Φεβρουάριος': 'February', 'Μάρτιος': 'March', 'Απρίλιος': 'April',
    'Μάιος': 'May', 'Ιούνιος': 'June', 'Ιούλιος': 'July', 'Αύγουστος': 'August',
    'Σεπτέμβριος': 'September', 'Οκτώβριος': 'October', 'Νοέμβριος': 'November', 'Δεκέμβριος': 'December',
    /* ── tickets ── */
    'Όλη η υποστήριξη σε ένα inbox': 'All support in one inbox', 'Διάλεξε ticket': 'Select a ticket',
    'Απάντηση στον πελάτη': 'Reply to client', 'Εσωτερική σημείωση': 'Internal note',
    'και κλείσιμο ticket': 'and close ticket', 'Έτοιμες απαντήσεις…': 'Canned replies…',
    'AI απάντηση': 'AI reply', 'Σύνοψη': 'Summary', 'Κατηγορία': 'Category',
    'Εσωτερικά αρχεία & βίντεο': 'Internal files & video', 'αόρατα στον πελάτη': 'hidden from the client',
    'Κανένα συνημμένο ακόμη.': 'No attachments yet.', 'Επισύναψη': 'Attach',
    'Το έχουμε ξαναδεί — πιθανές λύσεις:': 'We’ve seen this before — possible solutions:',
    'Όλες οι περιοχές': 'All areas', 'Όλες οι ρίζες': 'All root causes',
    'Γράψε απάντηση… (Ctrl+Enter για αποστολή)': 'Write a reply… (Ctrl+Enter to send)',
    /* ── chat ── */
    'Εσωτερική επικοινωνία ομάδας — με αρχεία': 'Internal team communication — with files',
    'Είμαι Online': 'I’m online', 'Είμαι Offline': 'I’m offline',
    'Νέα ομάδα': 'New group', 'Όλη η ομάδα': 'Everyone', 'Ομαδική συνομιλία': 'Group chat',
    'Καμία συζήτηση ακόμη — πες ένα γεια 👋': 'No messages yet — say hi 👋',
    'Μήνυμα… (Enter)': 'Message… (Enter)', 'Γιατί είσαι offline;': 'Why are you offline?',
    /* ── myday / standup ── */
    'Καθοδήγηση για σένα': 'Guidance for you', 'Τι δουλεύω τώρα': 'What I’m working on',
    'Tickets μου': 'My tickets', 'Κοντά σε SLA': 'Near SLA', 'Απαιτούν ενέργειά μου': 'Need my action',
    'Χρόνος σήμερα': 'Time today', 'Τα tickets μου': 'My tickets',
    'Καθαρή μέρα — τίποτα προγραμματισμένο!': 'Clear day — nothing scheduled!',
    'Κανένα ανοιχτό δικό σου 🎉': 'None of yours open 🎉',
    /* ── πελάτης 360 ── */
    'Το πλήρες ιστορικό ενός πελάτη': 'A client’s full history',
    'Ενεργές υπηρεσίες': 'Active services', 'Ανοιχτά tasks': 'Open tasks',
    'Ανοιχτά tickets': 'Open tickets', 'Υπόλοιπο προαγοράς': 'Prepaid balance',
    'Υπηρεσίες & προγράμματα': 'Services & plans', 'SLA & Συμβόλαιο': 'SLA & Contract',
    'Remote υποστήριξη': 'Remote support', 'Ανοιχτό υπόλοιπο': 'Outstanding balance',
    'Επικοινωνία': 'Contact', 'Ιστορικό': 'History', 'Χωρίς οφειλές': 'No outstanding balance',
    'Καμία ενεργή υπηρεσία': 'No active services', 'Δεν βρέθηκε πελάτης': 'No client found',
    'Καμία δραστηριότητα': 'No activity',
    /* ── απομακρυσμένες ── */
    'Οι συνδέσεις μου': 'My connections',
    'Αποθηκευμένες συνδέσεις πελατών — ένα κλικ για σύνδεση': 'Saved client connections — one click to connect',
    /* ── κοινά κενά/φορτώσεις ── */
    'Δεν φορτώθηκε': 'Failed to load', 'Μόνο για διαχειριστές': 'Administrators only',
    'Μόνο για διαχειριστές.': 'Administrators only.', 'Δεν έχεις πρόσβαση': 'You don’t have access',
    'Καμία εγγραφή': 'No records', 'Κανένα αποτέλεσμα': 'No results',
    /* ── ρυθμίσεις (ενότητες) ── */
    'Γενικά': 'General', 'Απαντήσεις': 'Replies', 'Χρήστες & πρόσβαση': 'Users & access',
    'Πακέτα υποστήριξης': 'Support packages', 'Κατηγορίες tickets': 'Ticket categories',
    'Τμήματα & Status': 'Departments & statuses', 'Αρχεία & Storage': 'Files & storage',
    'Λειτουργία': 'Operation', 'AI βοηθός': 'AI assistant', 'Οικονομικά': 'Financials',
    'Πρόσβαση': 'Access', 'Στήλες Board': 'Board columns', 'Τύποι tasks': 'Task types',
    /* ── κείμενα με σύμβολα/prefix (πρέπει να ταιριάζουν ΑΚΡΙΒΩΣ) ── */
    '▶ Τι δουλεύω τώρα': '▶ What I’m working on',
    'κρισιμότητα · αναμονή · SLA': 'urgency · waiting · SLA',
    'χωρίς ανάθεση': 'unassigned', '— ανάθεση —': '— assign —',
    'Όλα υπό έλεγχο — καμία εκκρεμότητα εκτός χρονοδιαγράμματος. Συνέχισε έτσι!':
      'All under control — nothing overdue. Keep it up!',
    'σκορ = κρισιμότητα + αναμονή + SLA + συμβόλαιο + παλαιότητα':
      'score = urgency + waiting + SLA + contract + age',
    'Πρόταση ημέρας — με σειρά προτεραιότητας': 'Today’s suggestion — by priority',
    'σκορ': 'score', 'Κρισιμότητα': 'Urgency', 'Αναμονή': 'Waiting', 'Παλαιότητα': 'Age',
    'Συμβόλαιο': 'Contract',
    /* ── επιπλέον συχνά ── */
    'Meetings · ραντεβού · άδειες · λήξεις tasks — η διαθεσιμότητα όλων':
      'Meetings · appointments · leave · task due dates — everyone’s availability',
    'Αποθηκευμένες συνδέσεις πελατών — ένα κλικ για σύνδεση':
      'Saved client connections — one click to connect',
    'κλικ «Σύνδεση» → ανοίγει το RustDesk έτοιμο': 'click “Connect” → opens RustDesk ready',
    'Καμία αποθηκευμένη σύνδεση ακόμη.': 'No saved connections yet.',
    'Βιογραφικά υποψηφίων — αξιολόγηση με AI co-pilot': 'Candidate CVs — AI co-pilot evaluation',
    'Υποψήφιοι': 'Candidates', 'Θέσεις / Αγγελίες': 'Positions / Job ads',
    'Νέος υποψήφιος': 'New candidate', 'Νέα θέση': 'New position', 'Διπλότυπα': 'Duplicates',
    'Η εικόνα της ομάδας σήμερα': 'The team’s picture today',
    'Παραγωγικότητα σήμερα': 'Productivity today', 'Φόρτος ομάδας': 'Team workload',
    'Μήνας — γρήγορη εικόνα': 'Month — quick view', 'Καθαρό': 'Net',
    'Κερδισμένες προσφορές': 'Won quotes', 'Κόστος εργασίας': 'Labour cost', 'Έξοδα projects': 'Project expenses',
    'Εκτός SLA': 'SLA breached', 'Περιμένουν απάντησή μας': 'Awaiting our reply',
    'Ανοιχτά >7 ημερών': 'Open >7 days', 'Έκλεισαν σήμερα': 'Closed today',
    'Επαναλαμβανόμενα προβλήματα (90 ημ.)': 'Recurring problems (90 days)',
    'Υγεία πελατών — χαμηλότερο σκορ πρώτα': 'Client health — lowest score first',
    'Κανένα μοτίβο — καλό σημάδι 🎉': 'No pattern — good sign 🎉',
    'Πρόταση: με ποια tickets ασχολούμαστε σήμερα — κρισιμότητα · αναμονή · SLA':
      'Suggestion: which tickets to work on today — urgency · waiting · SLA',
    'Κανένα ανοιχτό ticket 🎉': 'No open tickets 🎉',
  };

  var LS = 'cnpLang';
  var lang = null;
  try { lang = localStorage.getItem(LS); } catch (e) { }
  if (lang !== 'en') { lang = 'el'; }

  var api = {
    get: function () { return lang; },
    set: function (l) {
      try { localStorage.setItem(LS, l === 'en' ? 'en' : 'el'); } catch (e) { }
      location.reload();          // καθαρή επαναφορά και προς τις δύο κατευθύνσεις
    },
  };
  window.CNP_I18N = api;

  if (lang !== 'en') { return; }   // ελληνικά = τίποτα να κάνουμε
  document.documentElement.lang = 'en';

  var SKIP = {SCRIPT: 1, STYLE: 1, TEXTAREA: 1, CODE: 1, PRE: 1};
  var ATTRS = ['placeholder', 'title', 'aria-label'];

  function tr(s) {
    var k = s.trim();
    if (!k) { return null; }
    var v = DICT[k];
    if (v === undefined) { return null; }
    return s.replace(k, v);        // κράτα τα γύρω κενά όπως ήταν
  }

  function walk(root) {
    if (!root || root.nodeType === 3) {
      if (root && root.nodeType === 3) {
        var t = tr(root.nodeValue);
        if (t !== null) { root.nodeValue = t; }
      }
      return;
    }
    if (root.nodeType !== 1) { return; }
    if (SKIP[root.tagName] || root.hasAttribute('data-noi18n')) { return; }
    for (var i = 0; i < ATTRS.length; i++) {
      var a = ATTRS[i];
      if (root.hasAttribute(a)) {
        var v = tr(root.getAttribute(a));
        if (v !== null) { root.setAttribute(a, v); }
      }
    }
    var n = root.firstChild;
    while (n) {
      var next = n.nextSibling;      // κράτα το επόμενο (το DOM μπορεί να αλλάξει)
      if (n.nodeType === 3) {
        var s = tr(n.nodeValue);
        if (s !== null) { n.nodeValue = s; }
      } else if (n.nodeType === 1) {
        walk(n);
      }
      n = next;
    }
  }

  var pending = false;
  function schedule() {
    if (pending) { return; }
    pending = true;
    requestAnimationFrame(function () { pending = false; walk(document.body); });
  }

  function start() {
    walk(document.body);
    new MutationObserver(schedule).observe(document.body, {childList: true, subtree: true});
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();
