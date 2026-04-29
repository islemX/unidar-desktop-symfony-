/**
 * UNIDAR Utility Functions
 */

// Check authentication and redirect if needed
async function checkAuthAndRedirect(requiredRole = null) {
    try {
        const response = await window.UNIDAR_API.Auth.checkAuth();

        if (!response.authenticated) {
            window.location.href = 'login.html';
            return null;
        }

        // Check role requirement
        if (requiredRole && response.user.role !== requiredRole) {
            window.location.href = 'index.html';
            return null;
        }

        // Verification check for students only
        if (response.user.role === 'student') {
            const currentPage = window.location.pathname.split('/').pop() || 'index.html';

            // Pages that require verification
            const restrictedPages = [
                'listings.html',
                'listing-detail.html',
                'roommates.html'
            ];

            const isRestricted = restrictedPages.includes(currentPage);
            const isVerified = response.user.verification_status === 'approved';

            // Redirect unverified students from restricted pages
            if (isRestricted && !isVerified) {
                console.warn('Access denied: Student account not verified');
                showAlert('⚠️ Please complete your verification to access this page.', 'warning');
                setTimeout(() => {
                    window.location.href = 'user-dashboard.html';
                }, 1500);
                return null;
            }
        }

        return response.user;
    } catch (error) {
        console.error('Auth check failed:', error);
        window.location.href = 'login.html';
        return null;
    }
}

// Format date
function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

// Format time ago
function timeAgo(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    const now = new Date();
    const seconds = Math.floor((now - date) / 1000);

    if (seconds < 60) return 'just now';
    if (seconds < 3600) return `${Math.floor(seconds / 60)} min ago`;
    if (seconds < 86400) return `${Math.floor(seconds / 3600)} hours ago`;
    if (seconds < 604800) return `${Math.floor(seconds / 86400)} days ago`;
    return formatDate(dateString);
}

// Format currency
function formatCurrency(amount) {
    return new Intl.NumberFormat('en-TN', {
        minimumFractionDigits: 3,
        maximumFractionDigits: 3,
    }).format(amount) + ' TND';
}

// Show alert message
function showAlert(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.textContent = message;
    alertDiv.style.position = 'fixed';
    alertDiv.style.top = '20px';
    alertDiv.style.right = '20px';
    alertDiv.style.zIndex = '10000';
    alertDiv.style.minWidth = '300px';

    document.body.appendChild(alertDiv);

    setTimeout(() => {
        alertDiv.style.opacity = '0';
        alertDiv.style.transition = 'opacity 0.3s';
        setTimeout(() => alertDiv.remove(), 300);
    }, 3000);
}

// Show loading spinner
function showLoading(element) {
    element.innerHTML = '<div class="loading-spinner"></div>';
}

// Debounce function
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Get URL parameters
function getUrlParams() {
    const params = new URLSearchParams(window.location.search);
    const result = {};
    for (const [key, value] of params) {
        result[key] = value;
    }
    return result;
}

// Get API Base URL
function getAPIBaseURL() {
    // Return relative path to backend API to avoid absolute path issues
    return 'backend/api';
}