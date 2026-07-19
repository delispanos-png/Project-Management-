class Navigation {
    constructor() {
        this.config = {animationSpeed: 250, desktopBreakpoint: '(min-width: 1200px)'};
        this.desktop = window.matchMedia(this.config.desktopBreakpoint);
        this.elements = this.cacheElements();
        if (!this.elements.navigation) return;
        this.init();
    }

    cacheElements() {
        return {
            navbar: document.querySelector('.navigation'),
            navigation: document.querySelector('.navbar-new'),
            navToggle: document.querySelector('.navigation .nav-toggle'),
            quicksearch: document.querySelector('[data-quicksearch]'),
            searchInput: document.querySelector('#intelliSearchForm input.quicksearch'),
            overlay: document.querySelector('#overlay'),
            dropdowns: document.querySelectorAll('[data-dropdown]')
        };
    }

    init() {
        this.setupDropdowns();
        this.setupMobileMenu();
        this.setupSearch();
        this.setupResponsive();
        this.reset();
    }

    setupDropdowns() {
        this.elements.dropdowns.forEach(dropdown => {
            const button = dropdown.querySelector('[data-dropdown-btn]');
            const menu = dropdown.querySelector('[data-dropdown-menu]');
            if (!button || !menu) return;

            if (this.desktop.matches) {
                dropdown.addEventListener('mouseenter', () => this.showDropdown(menu));
                dropdown.addEventListener('mouseleave', () => this.hideDropdown(menu));
            }

            button.addEventListener('click', (e) => {
                e.preventDefault();
                this.handleDropdownClick(dropdown, button, menu);
            });
        });

        document.addEventListener('click', (e) => {
            if (!e.target.closest('[data-dropdown]')) {
                this.closeAllDropdowns();
            }
        });
    }

    handleDropdownClick(dropdown, button, menu) {
        if (this.desktop.matches) {
            this.toggleDropdown(menu);
        } else {
            this.handleMobileDropdown(dropdown, button, menu);
        }
    }

    handleMobileDropdown(dropdown, button, menu) {
        const clonedMenu = menu.cloneNode(true);
        const prevMenu = button.closest('.nav-icons')
            ? this.elements.navigation.querySelector('ul')
            : this.getCurrentVisibleMenu();

        const backButton = this.createBackButton(button.dataset.title, clonedMenu, prevMenu);
        clonedMenu.classList.add('menu-secondary-hidden');
        clonedMenu.prepend(backButton);

        this.setupSubmenuHandlers(clonedMenu);

        prevMenu.classList.add('menu-primary-hidden');
        this.elements.navigation.appendChild(clonedMenu);

        requestAnimationFrame(() => {
            clonedMenu.classList.remove('menu-secondary-hidden');
        });
    }

    getCurrentVisibleMenu() {
        const visibleMenus = Array.from(this.elements.navigation.children).filter(child =>
            child.tagName === 'UL' && !child.classList.contains('menu-primary-hidden')
        );
        return visibleMenus[visibleMenus.length - 1] || this.elements.navigation.querySelector('ul');
    }

    setupSubmenuHandlers(menu) {
        const submenus = menu.querySelectorAll('[data-dropdown]');
        submenus.forEach(submenu => {
            const submenuButton = submenu.querySelector('[data-dropdown-btn]');
            const submenuMenu = submenu.querySelector('[data-dropdown-menu]');

            if (submenuButton && submenuMenu) {
                submenuButton.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this.handleMobileDropdown(submenu, submenuButton, submenuMenu);
                });
            }
        });
    }

    createBackButton(title, currentMenu, prevMenu) {
        const backButton = document.createElement('li');
        backButton.innerHTML = `<a class="menu-back-button"><i class="ph ph-caret-left"></i> ${title}</a>`;
        backButton.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.navigateBack(currentMenu, prevMenu);
        });
        return backButton;
    }

    navigateBack(currentMenu, prevMenu) {
        currentMenu.classList.add('menu-secondary-hidden');

        setTimeout(() => {
            if (prevMenu && prevMenu.parentNode === this.elements.navigation) {
                prevMenu.classList.remove('menu-primary-hidden');
            }

            if (currentMenu.parentNode === this.elements.navigation) {
                this.elements.navigation.removeChild(currentMenu);
            }
        }, this.config.animationSpeed);
    }

    showDropdown(menu) {
        const styles = {opacity: '1', visibility: 'visible'};
        if (this.desktop.matches) styles.transform = 'translateY(0)';
        Object.assign(menu.style, styles);
        menu.setAttribute('aria-hidden', 'false');
        if (this.desktop.matches) this.handleIconDropdownPositioning(menu);
    }

    handleIconDropdownPositioning(menu) {
        const parentDropdown = menu.closest('[data-dropdown]');
        if (!parentDropdown || !parentDropdown.classList.contains('icon-dropdown')) return;

        requestAnimationFrame(() => {
            const menuRect = menu.getBoundingClientRect();
            const effectiveViewportWidth = window.innerWidth - (window.innerWidth - document.documentElement.clientWidth);
            if (menuRect.right > effectiveViewportWidth - 10) {
                menu.style.right = '10px';
                menu.style.left = 'auto';
            }
        });
    }

    hideDropdown(menu) {
        const styles = {opacity: '0', visibility: 'hidden'};
        if (this.desktop.matches) styles.transform = 'translateY(-10px)';
        Object.assign(menu.style, styles);
        menu.setAttribute('aria-hidden', 'true');
        this.resetIconDropdownPositioning(menu);
    }

    resetIconDropdownPositioning(menu) {
        const parentDropdown = menu.closest('[data-dropdown]');
        if (parentDropdown && parentDropdown.classList.contains('icon-dropdown')) {
            menu.style.right = '';
            menu.style.left = '';
        }
    }

    toggleDropdown(menu) {
        const isVisible = getComputedStyle(menu).visibility === 'visible';
        isVisible ? this.hideDropdown(menu) : this.showDropdown(menu);
    }

    closeAllDropdowns() {
        document.querySelectorAll('[data-dropdown].active').forEach(dropdown => {
            dropdown.classList.remove('active');
        });
    }

    setupMobileMenu() {
        this.elements.navToggle.addEventListener('click', () => this.toggleMobileMenu());
        this.elements.overlay.addEventListener('click', () => this.closeMobileMenu());
    }

    toggleMobileMenu() {
        const isActive = this.elements.navigation.classList.contains('active');
        this.elements.navToggle.classList.toggle('active');
        this.elements.overlay.classList.toggle('active');
        document.body.classList.toggle('mobile-active');

        if (isActive) {
            this.closeMobileMenu();
        } else {
            this.elements.navigation.classList.add('active');
        }
    }

    closeMobileMenu() {
        this.elements.navToggle.classList.remove('active');
        this.elements.navigation.classList.remove('active');
        this.elements.navigation.classList.add('active-out');
        setTimeout(() => {
            this.elements.navigation.classList.remove('active-out');
            this.elements.overlay.classList.remove('active');
        }, this.config.animationSpeed);
        document.body.classList.remove('mobile-active');
    }

    setupSearch() {
        if (!this.elements.searchInput) return;
        this.elements.searchInput.addEventListener('focus', () => {
            this.closeMobileMenu();
            this.elements.navbar.classList.add('search-active');
            this.elements.quicksearch.classList.add('active');
        });
        this.elements.searchInput.addEventListener('blur', () => {
            this.elements.navbar.classList.remove('search-active');
            this.elements.quicksearch.classList.remove('active');
        });
    }

    setupResponsive() {
        this.desktop.addEventListener('change', () => this.reset());
    }

    reset() {
        if (this.desktop.matches) {
            this.cleanupMobileMenus();
            this.elements.navToggle.classList.remove('active');
            this.elements.navigation.classList.remove('active');
            this.elements.overlay.classList.remove('active');
            const menuRight = document.querySelector('.navigation .menu-right');
            if (menuRight && this.elements.quicksearch) {
                menuRight.prepend(this.elements.quicksearch);
            }
        } else {
            this.closeAllDropdowns();
            const mobileSearchItem = document.querySelector('.mobile-nav .quick-search-item');
            if (mobileSearchItem && this.elements.quicksearch) {
                mobileSearchItem.append(this.elements.quicksearch);
            }
        }
    }

    cleanupMobileMenus() {
        const allMenus = Array.from(this.elements.navigation.children);
        const mainMenu = this.elements.navigation.querySelector('ul:first-child');

        allMenus.forEach(menu => {
            if (menu.tagName === 'UL' && menu !== mainMenu) {
                this.elements.navigation.removeChild(menu);
            }
        });

        if (mainMenu) {
            mainMenu.classList.remove('menu-primary-hidden', 'menu-secondary-hidden');
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    new Navigation();
});
