/**
 * Common functionality shared across all pages
 * Includes global notifications, owner navbar handling, and Global Chat Widget integration.
 */

var UNIDAR_CURRENT_USER = null;

/**
 * Initializes the common functionality. 
 * Supports both DOMContentLoaded and cases where the script is loaded later.
 */
async function initCommon() {
    if (!window.UNIDAR_API) {
        console.warn('UNIDAR_API not found during common init');
        return;
    }

    try {
        var auth = await window.UNIDAR_API.Auth.checkAuth();
        if (auth && auth.authenticated && auth.user) {
            UNIDAR_CURRENT_USER = auth.user;
            console.log('User authenticated:', UNIDAR_CURRENT_USER.full_name);
        }
    } catch (e) {
        console.error('Auth check in common failed:', e);
        UNIDAR_CURRENT_USER = null;
    }

    // Inject language switcher into all navbars
    injectLanguageSwitcher();

    if (UNIDAR_CURRENT_USER) {
        applyRoleNavbar(getCurrentPageName(), UNIDAR_CURRENT_USER);
    }

    // Load Global Chat Widget only for verified users
    if (UNIDAR_CURRENT_USER) {
        const isVerified = UNIDAR_CURRENT_USER.role !== 'student' ||
            UNIDAR_CURRENT_USER.verification_status === 'approved';

        if (isVerified) {
            loadChatWidget();
        } else {
            console.log('Chat widget disabled - student account pending verification');
        }
    }
}

// Ensure init runs regardless of when script is loaded
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCommon);
} else {
    initCommon();
}

/**
 * Dynamically loads the Global Chat Widget assets
 */
function loadChatWidget() {
    if (document.getElementById('chatWidgetScript')) return;

    console.log('Injecting ChatWidget script...');
    const script = document.createElement('script');
    script.id = 'chatWidgetScript';
    // Use timestamp to bypass cache
    script.src = 'js/chat-widget.js?v=' + Date.now();
    document.body.appendChild(script);
}

// Make loadChatWidget accessible globally for manual triggers
window.loadChatWidget = loadChatWidget;

async function updateGlobalUnreadCount() {
    // This is now handled by ChatWidget internally to ensure sync.
    // We keep the function signature for compatibility but it will delegate 
    // to the widget if it exists.
    if (window.chatWidget) {
        window.chatWidget.loadConversations(true);
    }
}

// function handleOwnerMode() {
//     var page = getCurrentPageName();
//     applyRoleNavbar(page);
// }
// Deprecated handleOwnerMode logic is now integrated into applyRoleNavbar

function getCurrentPageName() {
    var path = window.location.pathname || '';
    var lastSlash = path.lastIndexOf('/');
    if (lastSlash === -1) {
        return path || 'index.html';
    }
    var name = path.substring(lastSlash + 1);
    return name || 'index.html';
}

/**
 * Centralized function to apply navigation bar based on user role.
 * @param {string} page - Current page filename
 * @param {object} user - User object from auth check
 */
function applyRoleNavbar(page, user) {
    var container = null;
    var navbarRoot = document.querySelector('.navbar .nav-container');

    // Use passed user or global one
    var userData = user || UNIDAR_CURRENT_USER;
    if (!userData) return;

    if (navbarRoot) {
        container = navbarRoot;
    } else {
        // Fallback for pages using old header structure
        var header = document.querySelector('.header');
        var headerContainer = document.querySelector('.header .container');

        if (header && headerContainer) {
            header.className = 'navbar';
            headerContainer.className = 'nav-container';
            container = headerContainer;
        } else if (headerContainer) {
            headerContainer.className = 'nav-container';
            container = headerContainer;
        }
    }

    // Skip if no container or if it is an admin dashboard (focused UI)
    if (!container || page === 'admin-dashboard.html') {
        // Even on admin dashboard, ensure logout works if buttons exist
        var topLogout = document.querySelector('button[onclick="handleLogout()"]');
        if (topLogout && !topLogout.dataset.listenerSet) {
            topLogout.onclick = function () { window.handleLogout && window.handleLogout(); };
            topLogout.dataset.listenerSet = 'true';
        }
        return;
    }

    var active = '';
    if (page === 'owner-listings.html') active = 'my_properties';
    else if (page === 'user-dashboard.html') active = 'dashboard';
    else if (page === 'listings.html' || page === 'listing-detail.html') active = 'listings';
    else if (page === 'roommates.html') active = 'roommates';
    else if (page === 'index.html') active = 'home';

    function linkClass(name) {
        return 'nav-link' + (active === name ? ' active' : '');
    }

    // Get translations
    var t = window.UNIDAR_I18N ? function (key) { return window.UNIDAR_I18N.t(key); } : function (key) {
        var defaults = {
            nav_home: 'Home',
            nav_listings: 'Listings',
            nav_roommates: 'Roommates',
            nav_dashboard: 'Dashboard',
            nav_my_properties: 'My Properties',
            nav_logout: 'Logout'
        };
        return defaults[key] || key;
    };

    var linksHTML = '';
    var role = userData.role;

    if (role === 'owner') {
        linksHTML =
            '<a href="user-dashboard.html" class="' + linkClass('dashboard') + '" data-i18n="nav_dashboard">' + t('nav_dashboard') + '</a>' +
            '<a href="owner-listings.html" class="' + linkClass('my_properties') + '" data-i18n="nav_my_properties">' + t('nav_my_properties') + '</a>';
    } else if (role === 'admin') {
        linksHTML =
            '<a href="admin-dashboard.html" class="' + linkClass('dashboard') + '" data-i18n="nav_dashboard">' + t('nav_dashboard') + '</a>' +
            '<a href="listings.html" class="' + linkClass('listings') + '" data-i18n="nav_listings">' + t('nav_listings') + '</a>';
    } else {
        // Student / Standard user
        const isVerified = userData.verification_status === 'approved';
        const isSubscribed = userData.subscription_status === 'active';
        const hasFullAccess = isVerified && isSubscribed;

        if (hasFullAccess) {
            // Verified + subscribed student - show all links
            linksHTML =
                '<a href="index.html" class="' + linkClass('home') + '" data-i18n="nav_home">' + t('nav_home') + '</a>' +
                '<a href="listings.html" class="' + linkClass('listings') + '" data-i18n="nav_listings">' + t('nav_listings') + '</a>' +
                '<a href="roommates.html" class="' + linkClass('roommates') + '" data-i18n="nav_roommates">' + t('nav_roommates') + '</a>' +
                '<a href="user-dashboard.html" class="' + linkClass('dashboard') + '" data-i18n="nav_dashboard">' + t('nav_dashboard') + '</a>';
        } else {
            // Not fully activated - only home and dashboard
            linksHTML =
                '<a href="index.html" class="' + linkClass('home') + '" data-i18n="nav_home">' + t('nav_home') + '</a>' +
                '<a href="user-dashboard.html" class="' + linkClass('dashboard') + '" data-i18n="nav_dashboard">' + t('nav_dashboard') + '</a>';
        }
    }

    var langSwitcherHTML = window.UNIDAR_I18N ? window.UNIDAR_I18N.createSwitcherHTML() : '';

    container.innerHTML =
        '<a href="index.html" class="nav-logo">' +
        '<span style="color: var(--color-brand);">UNI</span>DAR' +
        '</a>' +
        '<div class="nav-links">' + linksHTML + '</div>' +
        '<div style="display: flex; align-items: center; gap: var(--space-md);">' +
        langSwitcherHTML +
        '<span id="userName" class="text-muted text-small" style="font-weight: 500;">' + (userData ? userData.full_name : '') + '</span>' +
        '<button class="btn btn-secondary" id="logoutBtn" style="padding: var(--space-xs) var(--space-md); font-size: var(--font-size-xs);" data-i18n="nav_logout">' + t('nav_logout') + '</button>' +
        '</div>';

    var logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
        logoutBtn.onclick = async function () {
            if (window.handleLogout) {
                await window.handleLogout();
            } else if (window.UNIDAR_API) {
                try { await window.UNIDAR_API.Auth.logout(); } catch (e) { }
                window.location.href = 'login.html';
            }
        };
    }

    if (window.UNIDAR_I18N) {
        window.UNIDAR_I18N.attachSwitcherEvents(container);
    }
}

// Support for old name to avoid breaking calls immediately
window.applyOwnerNavbar = applyRoleNavbar;
window.applyRoleNavbar = applyRoleNavbar;

/**
 * Inject language switcher into all navbars on the page
 */
function injectLanguageSwitcher() {
    if (!window.UNIDAR_I18N) {
        console.warn('UNIDAR_I18N not loaded, skipping language switcher injection');
        return;
    }

    // Find all nav containers
    var navContainers = document.querySelectorAll('.nav-container');

    navContainers.forEach(function (container) {
        // Skip if already has language switcher
        if (container.querySelector('.lang-switcher')) return;

        // Find the actions div (contains logout button, etc.)
        var actionsDiv = container.querySelector('div[style*="display: flex"]') ||
            container.querySelector('.header-actions');

        if (actionsDiv) {
            // Insert at the beginning of actions div
            var switcherHTML = window.UNIDAR_I18N.createSwitcherHTML();
            actionsDiv.insertAdjacentHTML('afterbegin', switcherHTML);
            window.UNIDAR_I18N.attachSwitcherEvents(container);
        }
    });

    // Translate the page after injection
    window.UNIDAR_I18N.translatePage();
}

// Make injectLanguageSwitcher accessible globally
window.injectLanguageSwitcher = injectLanguageSwitcher;

