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
    'Τα δικά μου': 'My work', 'Υποστήριξη': 'Support', 'Έργα': 'Projects', 'Πελάτες': 'Clients',
    'Έργα & υλοποιήσεις': 'Projects & rollouts', 'Η ομάδα': 'The team', 'Σύστημα': 'System',
    'Διοίκηση': 'Management', 'Προσλήψεις': 'Recruiting', 'Βοήθεια': 'Help',
    /* ── μενού / πλοήγηση ── */
    'Η μέρα μου': 'My day', 'Το πλάνο μου': 'My plan', 'Η βιβλιοθήκη μου': 'My library',
    'Κωδικοί': 'Passwords', 'Ημερολόγιο': 'Calendar', 'Απομακρυσμένες': 'Remote sessions',
    'Γνώση': 'Knowledge', 'Πελάτης 360°': 'Client 360°', 'Λίστα tasks': 'Task list',
    'Χρόνος': 'Time', 'Προσφορές': 'Quotes', 'Πλάνο ημέρας': 'Day plan',
    'Ανάλυση ριζών': 'Root cause analysis', 'Κερδοφορία': 'Profitability', 'Ομάδες': 'Teams',
    'Ρυθμίσεις': 'Settings', 'Βιογραφικά': 'CVs', 'Οδηγός χρήσης': 'User guide',
    /* ── κάτω μπάρα (σύντομα) ── */
    'Σήμερα': 'Today', 'Πλάνο': 'Plan', 'Βιβλιοθήκη': 'Library',
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
    'Μόνο για διαχειριστές.': 'Administrators only.', 'Ενότητες του μενού': 'Menu sections', 'Προαγορά χρόνου': 'Prepaid time', 'Δραστηριότητα': 'Activity',
    'Ακάλυπτος χρόνος': 'Uncovered time', 'Υπόλοιπα πελατών': 'Client balances', 'Δεν έχεις πρόσβαση': 'You don’t have access',
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

    /* ══ πάνω μπάρα / κέλυφος ══ */
    'Αναζήτηση (Ctrl+K)': 'Search (Ctrl+K)', 'Βοήθεια για αυτή την οθόνη': 'Help for this screen',
    'Μεγέθυνση/σμίκρυνση μενού': 'Collapse / expand menu', 'Κατάσταση διαθεσιμότητας': 'Availability status',
    'Κλικ για τερματισμό & χρέωση': 'Click to stop & bill', 'Φίλτρα': 'Filters', 'Θέμα': 'Subject',
    '🟢 Είμαι Online': '🟢 I’m online', '⚫ Είμαι Offline': '⚫ I’m offline', '# Ομάδα': '# Team',
    'Ομάδας': 'Team', 'Δικά μου': 'Mine', 'Αρχείο': 'Archive', 'Σύνολο': 'Total',
    'Κατάσταση': 'Status', 'Πρόοδος': 'Progress', 'Τύπος': 'Type', 'Σημείωση': 'Note', 'Σημ.': 'Note',
    'Πότε': 'When', 'Ποιος': 'Who', 'Όνομα': 'First name', 'Επώνυμο': 'Last name', 'Τηλέφωνο': 'Phone',
    'Εκτίμηση': 'Estimate', 'Χρέωση': 'Charge', 'Χρεώσιμα': 'Billable', 'προθεσμία': 'deadline',
    'Μεσαία': 'Medium', 'διαχειριστής': 'administrator', '(διαχ.)': '(admin)', 'Ομάδα': 'Team',

    /* ══ κοινά φίλτρα & ομαδοποίηση (νέο mobile μοτίβο) ══ */
    'Μόνο δικά μου': 'Only mine', 'Μόνο ανοιχτά': 'Open only', 'Πιο χρήσιμα': 'Most used',
    'Πιο πρόσφατα': 'Most recent', 'Αλφαβητικά': 'Alphabetical', 'Ανά project': 'By project',
    'Ανά χειριστή': 'By operator', 'Ανά στήλη': 'By column', 'Ανά προτεραιότητα': 'By priority',
    'Ανά πελάτη': 'By client', 'Χωρίς ομαδοποίηση': 'No grouping', 'Χωρίς προϊόν': 'No product',
    '— χειριστής —': '— operator —', '— πηγή —': '— source —', '— όλα τα projects —': '— all projects —',
    'Εξαγωγή CSV': 'Export CSV', 'Καμία καταχώρηση': 'No entries', 'Καμία εργασία': 'No work',
    'Αποθήκευση αυτών των φίλτρων ως view': 'Save these filters as a view',

    /* ══ Γνώση ══ */
    'Βιβλιοθήκη γνώσης ανά προϊόν — ψάξε αν το πρόβλημα έχει ξαναλυθεί':
      'Knowledge library by product — check if the problem was solved before',
    'Ψάξε τα πάντα — τίτλο, λέξεις-κλειδιά, κείμενο λύσης, προϊόν…':
      'Search everything — title, keywords, solution text, product…',
    'Και στα tickets': 'Also in tickets', 'Ψάξε και στο ιστορικό των tickets': 'Search ticket history too',
    'Προσθήκη γνώσης': 'Add knowledge', 'Νέα καταχώρηση γνώσης': 'New knowledge entry',
    'Επεξεργασία γνώσης': 'Edit knowledge', 'Αντιγραφή λύσης': 'Copy solution',
    'Τίτλος προβλήματος': 'Problem title', 'Η λύση — βήμα-βήμα': 'The solution — step by step',
    'Συναφή προϊόντα': 'Related products', 'Συναφή από άλλα προϊόντα': 'Related from other products',
    'Παρόμοια tickets στο ιστορικό': 'Similar tickets in history',
    'Η βιβλιοθήκη είναι άδεια': 'The library is empty', 'Κανένα αποτέλεσμα': 'No results',

    /* ══ Λίστα tasks ══ */
    'Όλα τα tasks ομαδοποιημένα — g+l': 'All tasks grouped — g+l', 'Νέο task': 'New task',
    'Ψάξε τα πάντα — τίτλο, project, χειριστή, κατάσταση…':
      'Search everything — title, project, operator, status…',
    'Τι πρέπει να γίνει;': 'What needs to be done?', 'Τίτλος του task': 'Task title',
    'Καμία εργασία ακόμη': 'No tasks yet', 'Κανένα task με αυτά τα φίλτρα': 'No tasks with these filters',
    '+ Νέο task… (Enter)': '+ New task… (Enter)', 'Λειτουργικό': 'Operational',

    /* ══ Gantt ══ */
    '👥 Διαθεσιμότητα': '👥 Availability', '↕ Άνοιγμα όλων': '↕ Expand all',
    'Κανένα task στο χρονοδιάγραμμα': 'No tasks on the timeline', 'Πήγαινε στο Board': 'Go to Board',
    'Σύρε μπάρα = μετακίνηση · άκρη = διάρκεια · κλικ = άνοιγμα':
      'Drag bar = move · edge = duration · click = open',

    /* ══ CRM & Προσφορές ══ */
    'Νέο lead': 'New lead', 'Νέα προσφορά': 'New quote', 'πωλήσεις μήνα': 'sales this month',
    'ανοιχτές': 'open', 'κερδισμένες': 'won', 'Στόχος': 'Target', 'Έγινε επαφή': 'Contacted',
    'Ενδιαφέρεται': 'Interested', 'Σε προσφορά': 'Quoted', 'Έγινε πελάτης': 'Won',
    'Δεν προχώρησε': 'Lost', 'Νέα': 'New', 'Σύνταξη προσφοράς': 'Drafting',
    'Εστάλη — αναμονή': 'Sent — awaiting', 'Αποδεκτή': 'Accepted', 'Χαμένη': 'Lost',
    'Ψάξε lead — εταιρεία, επαφή, email, τηλέφωνο…': 'Search lead — company, contact, email, phone…',
    'Ψάξε προσφορά — τίτλο, πελάτη, αριθμό quote…': 'Search quote — title, client, quote number…',
    'Κανένα lead ακόμη': 'No leads yet', 'Καμία προσφορά ακόμη': 'No quotes yet',
    'Καμία προσφορά με αυτά τα φίλτρα': 'No quotes with these filters',
    'Κανένα lead με αυτά τα φίλτρα': 'No leads with these filters',
    'Ανοιχτά leads στο pipeline': 'Open leads in pipeline', 'Αξία ανοιχτού pipeline': 'Open pipeline value',
    'Win rate (κερδ. / κλεισμένα)': 'Win rate (won / closed)', 'Οι εργασίες μου': 'My tasks',
    'Θερμότερα leads': 'Hottest leads', 'Pipeline ανά στάδιο': 'Pipeline by stage',
    'Εκπρόθεσμα follow-ups': 'Overdue follow-ups', 'Χωρίς επόμενη ενέργεια': 'No next action',
    'Ανοιχτά ανά πηγή': 'Open by source', 'Ανοιχτά ανά χειριστή': 'Open by operator',
    'Νέα επαφή': 'New contact', 'Επαφή': 'Contact', 'Τελ. επαφή': 'Last contact',
    'Καταγραφή επικοινωνίας': 'Log communication', 'Τι ειπώθηκε': 'What was said',
    'Καταγραφή': 'Log', 'Πρόσφατες επικοινωνίες': 'Recent communications',
    'Καμία επικοινωνία ακόμη': 'No communications yet', 'Νέα καμπάνια': 'New campaign',
    'Καμπάνιες — μέλη & απόδοση': 'Campaigns — members & performance',
    'Τηλεφώνημα': 'Phone call', 'Συνάντηση': 'Meeting', 'ψάξε πελάτη…': 'search client…',

    /* ══ Projects ══ */
    'Portfolio — κατάσταση, υγεία, πρόοδος': 'Portfolio — status, health, progress',
    'Ψάξε έργο — όνομα, πελάτη, κατάσταση…': 'Search project — name, client, status…',
    'Νέο project': 'New project', 'Επαναλαμβανόμενα': 'Recurring', 'Έργα πελατών': 'Client projects',
    'Λειτουργικά projects': 'Operational projects', 'Έργο': 'Project', 'Παραδοτέα': 'Deliverables',
    'Χρόνος / εκτίμηση': 'Time / estimate', 'Τάση 7ημ': '7-day trend',
    'τμήματα & καθημερινή λειτουργία (tickets)': 'departments & daily operations (tickets)',

    /* ══ Ρίζες ══ */
    '30 ημέρες': '30 days', '90 ημέρες': '90 days', '180 ημέρες': '180 days', '1 έτος': '1 year',
    'Κορυφαίες ρίζες προβλημάτων': 'Top root causes', 'Ανά περιοχή / προϊόν': 'By area / product',
    'Πίνακας: Περιοχή × Ρίζα': 'Matrix: Area × Root cause', 'κλικ σε αριθμό → τα tickets': 'click a number → the tickets',
    'Καμία ταξινόμηση ακόμη': 'No classification yet', 'αταξινόμητο': 'unclassified',

    /* ══ Απομακρυσμένες ══ */
    'Αποθηκευμένες': 'Saved', 'Συνεδρίες 30 ημ.': 'Sessions (30d)', 'Χρόνος 30 ημ.': 'Time (30d)',
    'Χρεώσιμος χρόνος': 'Billable time', 'Αναζήτηση πελάτη ή ID…': 'Search client or ID…',
    'Νέα σύνδεση': 'New connection', 'Στείλε το πρόγραμμα': 'Send the program',
    'Καμία αποθηκευμένη σύνδεση ακόμη': 'No saved connections yet', 'Πρόσθεσε σύνδεση': 'Add connection',
    'Πρόσφατες συνεδρίες': 'Recent sessions', 'RustDesk ID': 'RustDesk ID',

    /* ══ Ημερολόγιο / συμβάν ══ */
    'Θα παρευρεθείς;': 'Will you attend?', 'Ποιος θα είναι εκεί': 'Who’s attending',
    'Συμμετοχή στο meeting': 'Join the meeting', 'Αντιγραφή συνδέσμου': 'Copy link',
    'Προηγούμενος μήνας': 'Previous month', 'Επόμενος μήνας': 'Next month',
    '📅 Ραντεβού': '📅 Appointment', '🌴 Άδεια': '🌴 Leave', '📌 Άλλο': '📌 Other',
    'ολοήμερο': 'all day',

    /* ══ Standup ══ */
    'ανοιχτά projects': 'open projects', 'ανοιχτά tickets': 'open tickets',
    'περιμένουν εμάς': 'awaiting us', '↻ Ανανέωση': '↻ Refresh',
    'Ανοιχτά Projects': 'Open projects', 'Ανοιχτά Tickets': 'Open tickets',
    'νωρίτερη προθεσμία πρώτη · κλικ → Board': 'earliest deadline first · click → Board',
    'Περιμένουν απάντηση': 'Awaiting reply', 'Περιμένει πελάτη': 'Waiting on client',

    /* ══ Κερδοφορία ══ */
    'Έσοδα': 'Revenue', 'Κόστος': 'Cost', 'Έξοδα': 'Expenses', 'Κέρδος': 'Profit',
    'Περιθώριο': 'Margin', 'Κόστος ώρας:': 'Hourly cost:', 'ποσό €': 'amount €',
    'Καμία εργασία/έξοδο στην περίοδο': 'No work/expenses in this period',
    '(οι ζημιογόνοι πρώτα)': '(loss-making first)',

    /* ══ Ομάδες ══ */
    'Οργανόγραμμα — ποιος ανήκει πού': 'Org chart — who belongs where',
    'Όνομα νέας ομάδας': 'New team name', 'Χωρίς ομάδα': 'No team',

    /* ══ Ρυθμίσεις ══ */
    'Γενικά': 'General', 'Auto-task από tickets': 'Auto-task from tickets',
    'Αναθέσεις, σχόλια, digest, follow-ups': 'Assignments, comments, digest, follow-ups',
    'Δημόσια φόρμα αιτημάτων': 'Public request form',
    'Μοντέλο αξιολόγησης βιογραφικών': 'CV evaluation model',
    'Μηνιαίος στόχος πωλήσεων €': 'Monthly sales target €',
    'Πρόοδος από κερδισμένες προσφορές': 'Progress from won quotes',
    'Κόστος ώρας εργασίας €': 'Hourly labour cost €',
    'Για τον υπολογισμό κερδοφορίας': 'Used for profitability calculation',
    'Χρήστες & πρόσβαση': 'Users & access', 'Πακέτα υποστήριξης': 'Support packages',
    'Κατηγορίες tickets': 'Ticket categories', 'Τμήματα & Status': 'Departments & statuses',
    'Αρχεία & Storage': 'Files & storage', 'Στήλες Board': 'Board columns',

    /* ══ Προσλήψεις ══ */
    'Όλες οι θέσεις': 'All positions', 'Νέος υποψήφιος': 'New candidate',
    'Αναζήτηση ονόματος / email / τηλεφώνου…': 'Search name / email / phone…',
    'Νέες': 'New', 'Υπό αξιολόγηση': 'Under review', 'Συνέντευξη': 'Interview',
    'Απορρίφθηκε': 'Rejected', 'Προσλήφθηκε': 'Hired', 'Απόρριψη': 'Reject', 'Ίσως': 'Maybe',
    'ημ. υποβολής': 'submitted on', 'έχει CV': 'has CV', 'μερικώς AI': 'partly AI',
    'Οικονομικό — Haiku': 'Economical — Haiku', 'Ισορροπημένο — Sonnet': 'Balanced — Sonnet',
    'Αυστηρό — Opus': 'Strict — Opus', 'Κανένας υποψήφιος': 'No candidates',
    '25 / σελίδα': '25 / page', '50 / σελίδα': '50 / page',
    '100 / σελίδα': '100 / page', '200 / σελίδα': '200 / page',

    /* ══ Προφίλ ══ */
    'Τα στοιχεία μου': 'My details', 'Οι προτιμήσεις μου': 'My preferences',
    'Οι ειδοποιήσεις μου': 'My notifications', 'Τα δικαιώματά μου': 'My permissions',
    'Τα projects μου': 'My projects', 'Αλλαγή κωδικού': 'Change password',
    'Τρέχων κωδικός': 'Current password', 'Νέος κωδικός (8+)': 'New password (8+)',
    'Επιβεβαίωση': 'Confirm', 'Ρόλος': 'Role', 'Ειδοποιήσεις email': 'Email notifications',
    'Πρωινό daily digest': 'Morning daily digest',
    'Προσωπικό meeting link': 'Personal meeting link',
    'Θυρίδα κωδικών': 'Password vault', 'Νέος κωδικός': 'New password',
    'Όλων των χειριστών': 'All operators',
    'Καμία καταχώρηση ακόμη — πάτα «Νέος κωδικός»': 'No entries yet — press “New password”',
    'Πλήρης — όλα τα projects, KPI, διαχείριση': 'Full — all projects, KPIs, administration',

    /* ══ τίτλοι ενοτήτων Οδηγού (το σώμα παραμένει ελληνικό) ══ */
    '1. Καλωσόρισες — μια γρήγορη ματιά': '1. Welcome — a quick look',
    '2. Είσοδος, αποσύνδεση & θέμα': '2. Sign in, sign out & theme',
    '3. Το μενού & η πλοήγηση': '3. The menu & navigation',
    '4. Πάνω μπάρα — το κέντρο ελέγχου': '4. Top bar — the control centre',
    '5. Η μέρα μου': '5. My day', '6. Το πλάνο μου (ανά project)': '6. My plan (by project)',
    '7. Η βιβλιοθήκη μου': '7. My library', '8. Κωδικοί (κρυπτογραφημένη θυρίδα)': '8. Passwords (encrypted vault)',
    '9. Board (Kanban) & κάρτα εργασίας': '9. Board (Kanban) & task card',
    '10. Gantt (χρονοδιάγραμμα)': '10. Gantt (timeline)',
    '11. Λίστα tasks & Χρόνος': '11. Task list & Time',
    '13. Tickets (Inbox υποστήριξης)': '13. Tickets (support inbox)',
    '14. Γνώση (Knowledge Base)': '14. Knowledge base',
    '15. Πελάτης 360°': '15. Client 360°', '16. CRM Πωλήσεων': '16. Sales CRM',
    '17. Προσφορές': '17. Quotes', '18. Chat & Απομακρυσμένες': '18. Chat & remote sessions',
    '19. Standup & Ημερολόγιο': '19. Standup & calendar',
    '20. Προσλήψεις (αγγελίες & βιογραφικά)': '20. Recruiting (job ads & CVs)',
    '21. Διοίκηση (μόνο διαχειριστές)': '21. Administration (admins only)',
    '22. Ρόλοι & δικαιώματα': '22. Roles & permissions', '23. Ειδοποιήσεις': '23. Notifications',
    '24. Συντομεύσεις πληκτρολογίου': '24. Keyboard shortcuts', '25. Συχνές ερωτήσεις': '25. FAQ',
    'Εκτύπωση / PDF': 'Print / PDF',

    /* ══ τελευταία παρτίδα: υπότιτλοι οθονών, tabs CRM, κενές καταστάσεις ══ */
    'Επισκόπηση': 'Overview', 'Επαφές': 'Contacts', 'Επικοινωνίες': 'Communications',
    'Καμπάνιες': 'Campaigns', 'Στόχοι προϊόντων': 'Product targets',
    'Pipeline πωλήσεων — στόχοι → επαφή → πελάτες': 'Sales pipeline — targets → contact → clients',
    'Επισκόπηση pipeline — αριθμοί & εκκρεμότητες': 'Pipeline overview — numbers & pending items',
    'Επαφές — leads & πελάτες με CRM δραστηριότητα': 'Contacts — leads & clients with CRM activity',
    'Pipeline προσφορών — δεμένο με WHMCS Quotes': 'Quote pipeline — linked to WHMCS Quotes',
    'Χρονοδιάγραμμα projects & διαθεσιμότητα ομάδας': 'Project timeline & team availability',
    'Ανά project — τι έχεις να κάνεις & πού έμεινες': 'By project — what to do & where you left off',
    'Έσοδα − κόστος εργασίας − έξοδα, ανά πελάτη': 'Revenue − labour cost − expenses, per client',
    'Η θυρίδα κωδικών σου — κρυπτογραφημένα (AES-256)': 'Your password vault — encrypted (AES-256)',
    'Αναζήτηση σε τίτλους, κείμενο, ετικέτες, αρχεία…': 'Search titles, text, tags, files…',
    'Κενή βιβλιοθήκη — πρόσθεσε σημείωση, link ή αρχείο': 'Empty library — add a note, link or file',
    'Αναζήτηση σε leads & πελάτες… (Enter)': 'Search leads & clients… (Enter)',
    'Πληκτρολόγησε ID, όνομα, επωνυμία ή email…': 'Type ID, first name, surname or email…',
    'επείγοντα & αναπάντητα πρώτα · κλικ → ticket': 'urgent & unanswered first · click → ticket',
    '📍 Πού έμεινα / σημειώσεις': '📍 Where I left off / notes',
    'Αποθήκευση σημείωσης': 'Save note', 'Καμία υπενθύμιση ακόμη': 'No reminders yet',
    '+ Προσθήκη υπενθύμισης (Enter)': '+ Add reminder (Enter)',
    'Αναφορές χρόνου ομάδας': 'Team time reports', 'Σύνολο εργασίας': 'Total work',
    'Χωρίς χρέωση': 'Non-billable', 'Χρεώθηκαν (προαγορά)': 'Charged (prepaid)',
    'Καμία ανοιχτή εργασία 🎉': 'No open tasks 🎉', 'Κανένα ανοιχτό lead': 'No open leads',
    'Όλα έχουν πλάνο 👏': 'Everything has a plan 👏', 'Κανένα 🎉': 'None 🎉',
    'Καμία επαφή ακόμη — πάτα «+ Νέα επαφή»': 'No contacts yet — press “+ New contact”',
    'Καμία καμπάνια — πάτα «Νέα καμπάνια»': 'No campaigns — press “New campaign”',
    'SLA σε κίνδυνο / εκτός': 'SLA at risk / breached', 'σύνοψη…': 'summary…',
    'Απαντ.': 'Replies', 'Εκπρόθ.': 'Overdue', 'Έσοδα (πελάτες με έργο)': 'Revenue (clients with projects)',
    '— όρισέ το στις ρυθμίσεις του module': '— set it in the module settings',
    'Γενικά — όλα χωρίς WHMCS admin': 'General — everything without WHMCS admin',
    'περιγραφή': 'description', 'Καταχώρηση': 'Record', 'χωρίς': 'without',
    'Συνημμένα': 'Attachments', 'ετικέτες': 'tags', '⧉ Αντιγραφή': '⧉ Copy',
    '⧉ Διπλότυπα': '⧉ Duplicates', '🇬🇷 Ελληνικά': '🇬🇷 Greek',
    '✓ Σε καλό δρόμο': '✓ On track', '▶ Επόμενο:': '▶ Next:',
    'υπέβαλε 2 φορές (ίδιο email)': 'applied 2 times (same email)',
    'υπέβαλε 3 φορές (ίδιο email)': 'applied 3 times (same email)',
    'Καλησπέρα': 'Good evening', 'Καλημέρα': 'Good morning', 'Καληνύχτα': 'Good night',
  };

  var LS = 'cnpLang';
  var lang = null;
  try { lang = localStorage.getItem(LS); } catch (e) { }
  if (lang !== 'en') { lang = 'el'; }

  // Locale ημερομηνιών/ωρών: ακολουθεί τη γλώσσα (αλλιώς σε EN mode έβγαινε «Ιούλιος 2026»).
  // Το i18n.js φορτώνει ΠΡΙΝ το app.js, οπότε είναι έτοιμο σε κάθε χρήση.
  window.CNP_LOCALE = lang === 'en' ? 'en-GB' : 'el-GR';

  var api = {
    get: function () { return lang; },
    locale: function () { return window.CNP_LOCALE; },
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
