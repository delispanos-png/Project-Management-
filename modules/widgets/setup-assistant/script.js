/**
 * Setup Assistant Widget JavaScript
 */

let setupPanelTrigger = null;

const toggleSetupTasksPanel = (isOpen) => {
    const panel = document.getElementById('setupTasksPanel');
    if (!panel) return;

    // Each widget refresh (e.g. after re-enabling a hidden widget) re-renders this
    // markup in place, so a stale copy reparented to <body> by an earlier open would
    // otherwise be orphaned with a duplicate id. Drop any such leftovers first.
    document.querySelectorAll('#setupTasksPanel').forEach((el) => {
        if (el !== panel) {
            el.remove();
        }
    });

    // The dashboard grid (Masonry) applies a CSS transform to widget tiles once it
    // lays out the grid, which creates a new containing block for this panel's
    // position:fixed and traps it inside the tile instead of the viewport.
    // Reparenting to <body> keeps the panel viewport-relative regardless.
    if (panel.parentElement !== document.body) {
        document.body.appendChild(panel);
    }

    panel.classList.toggle('active', isOpen);
    document.body.style.overflow = isOpen ? 'hidden' : '';
};

// Escape key closes panel and restores focus
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' || e.keyCode === 27) {
        const panel = document.getElementById('setupTasksPanel');
        if (panel && panel.classList.contains('active')) {
            toggleSetupTasksPanel();
            if (setupPanelTrigger) {
                setupPanelTrigger.focus();
            }
        }
    }
});