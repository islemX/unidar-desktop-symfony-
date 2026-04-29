/**
 * UNIDAR API Utility
 * Centralized API communication functions
 */

// Dynamically determine the base URL
function getApiBaseUrl() {
    // If we are on a known path like /unidar/
    const path = window.location.pathname;
    const projectDir = path.substring(0, path.indexOf('/', 1)); // e.g. /unidar
    
    // Check if we are running in a subdirectory or root
    if (projectDir && projectDir.length > 1 && !projectDir.includes('.')) {
        return projectDir + '/backend/api';
    }
    
    // Fallback: use relative path if we can't determine project dir reliably
    // This works if the HTML files are in the project root
    return 'backend/api';
}

const API_BASE_URL = getApiBaseUrl();

// api.js - Update the apiCall function
async function apiCall(endpoint, options = {}) {
    const url = `${API_BASE_URL}/${endpoint}`;
    console.log('API Call:', url, options); // Debug log

    const defaultOptions = {
        headers: {
            'Content-Type': 'application/json',
        },
        credentials: 'include',
    };

    const config = { ...defaultOptions, ...options };

    // If body is FormData, remove Content-Type to let browser set it with boundary
    if (config.body instanceof FormData) {
        delete config.headers['Content-Type'];
    } else if (config.body && typeof config.body === 'object') {
        config.body = JSON.stringify(config.body);
    }

    try {
        const response = await fetch(url, config);

        // Get response as text first
        const text = await response.text();
        console.log('Raw response:', text); // Debug log

        let data;
        try {
            // Attempt to parse JSON directly
            data = JSON.parse(text);
        } catch (e) {
            // Fallback: Try to find JSON content within mixed output (e.g. PHP warnings)
            console.warn('JSON Parse Warning: Response contained non-JSON data. Attempting to recover.');
            const jsonStart = text.indexOf('{');
            const jsonEnd = text.lastIndexOf('}');

            if (jsonStart !== -1 && jsonEnd !== -1) {
                const jsonString = text.substring(jsonStart, jsonEnd + 1);
                try {
                    data = JSON.parse(jsonString);
                } catch (e2) {
                    console.error('API Error: Could not extract valid JSON', text);
                    throw new Error(`Invalid JSON response from server: ${text.substring(0, 100)}...`);
                }
            } else {
                console.error('API Error: No JSON found in response', text);
                throw new Error(`Invalid JSON response from server: ${text.substring(0, 100)}...`);
            }
        }

        if (!response.ok) {
            throw new Error(data.error || `Request failed with status ${response.status}`);
        }

        return data;
    } catch (error) {
        console.error('API Error:', error);
        throw error;
    }
}

// Authentication API
const AuthAPI = {
    async register(userData) {
        return apiCall('auth.php?action=register', {
            method: 'POST',
            body: userData,
        });
    },

    async login(email, password) {
        return apiCall('auth.php?action=login', {
            method: 'POST',
            body: { email, password },
        });
    },

    async logout() {
        return apiCall('auth.php?action=logout', {
            method: 'GET',
        });
    },

    async checkAuth() {
        return apiCall('auth.php?action=check', {
            method: 'GET',
        });
    },
};

// Listings API
const ListingsAPI = {
    async getAll(filters = {}) {
        const params = new URLSearchParams(filters);
        return apiCall(`listings.php?${params.toString()}`);
    },

    async getById(id) {
        return apiCall(`listings.php?id=${id}`);
    },

    async create(listingData) {
        return apiCall('listings.php', {
            method: 'POST',
            body: listingData,
        });
    },

    async update(id, listingData) {
        return apiCall(`listings.php?id=${id}`, {
            method: 'PUT',
            body: listingData,
        });
    },

    async delete(id) {
        return apiCall(`listings.php?id=${id}`, {
            method: 'DELETE',
        });
    },
};

// Verifications API
const VerificationsAPI = {
    async getMyVerification() {
        return apiCall('verifications.php');
    },

    async submitVerification(formData) {
        return apiCall('verifications.php', {
            method: 'POST',
            body: formData,
            headers: {}, // Let browser set Content-Type for FormData
        });
    },

    async getAll(status = 'pending') {
        return apiCall(`verifications.php?status=${status}`);
    },

    async review(id, action, rejectionReason = null) {
        return apiCall(`verifications.php?id=${id}`, {
            method: 'PUT',
            body: { action, rejection_reason: rejectionReason },
        });
    },
};

// Messages API
const MessagesAPI = {
    async getConversations() {
        return apiCall('conversations.php');
    },

    async getConversation(id) {
        return apiCall(`messages.php?conversation_id=${id}`);
    },

    async createConversation(recipientId, listingId = null) {
        const body = { recipient_id: recipientId };
        if (listingId) body.listing_id = listingId;

        return apiCall('conversations.php', {
            method: 'POST',
            body: body
        });
    },

    async sendMessage(conversationId, message) {
        return apiCall('messages.php', {
            method: 'POST',
            body: {
                conversation_id: conversationId,
                message: message
            },
        });
    },

    async markAsRead(conversationId) {
        return apiCall('messages.php', {
            method: 'PUT',
            body: { conversation_id: conversationId }
        });
    },

    async blockUser(userId) {
        // Assuming block logic exists or will be added to messages.php or separate
        return apiCall('messages.php?action=block', {
            method: 'POST',
            body: { user_id: userId }
        });
    },

    async toggleArchive(conversationId, archive = true) {
        return apiCall('conversations.php?action=archive', {
            method: 'POST',
            body: {
                conversation_id: conversationId,
                archive: archive
            }
        });
    },

    async deleteConversation(conversationId) {
        return apiCall('conversations.php?action=delete', {
            method: 'POST',
            body: { conversation_id: conversationId }
        });
    }
};

// Roommates API
const RoommatesAPI = {
    async getPreferences() {
        return apiCall('roommates.php');
    },

    async savePreferences(preferences) {
        return apiCall('roommates.php', {
            method: 'POST',
            body: preferences,
        });
    },

    async getMatches() {
        return apiCall('roommates.php?matches=true');
    },
};

// Reports API
const ReportsAPI = {
    async create(reportData) {
        return apiCall('reports.php', {
            method: 'POST',
            body: reportData,
        });
    },

    async getMyReports() {
        return apiCall('reports.php');
    },

    async getAll(status = 'open') {
        return apiCall(`reports.php?status=${status}`);
    },

    async resolve(id, action, notes = '') {
        return apiCall(`reports.php?id=${id}`, {
            method: 'PUT',
            body: { action, notes },
        });
    },
};

// Admin API
const AdminAPI = {
    async getStats() {
        return apiCall('admin.php?action=stats');
    },

    async getUsers(search = '', role = '') {
        const params = new URLSearchParams();
        if (search) params.append('search', search);
        if (role) params.append('role', role);
        return apiCall(`admin.php?action=users&${params.toString()}`);
    },

    async updateUserStatus(userId, status, notes = '') {
        return apiCall(`admin.php?user_id=${userId}`, {
            method: 'PUT',
            body: { status, notes },
        });
    },

    async deleteUser(userId) {
        return apiCall(`admin.php?user_id=${userId}`, {
            method: 'DELETE',
        });
    },
};

// Saved Listings API
const SavedListingsAPI = {
    async getAll() {
        return apiCall('saved_listings.php');
    },

    async save(listingId) {
        return apiCall('saved_listings.php', {
            method: 'POST',
            body: { listing_id: listingId },
        });
    },

    async unsave(listingId) {
        return apiCall(`saved_listings.php?listing_id=${listingId}`, {
            method: 'DELETE',
        });
    },
};

// Contracts API
const ContractsAPI = {
    async getTerminationRequests() {
        return apiCall('contracts.php?action=get-termination-requests');
    },

    async approveTermination(requestId) {
        return apiCall('contracts.php?action=approve-termination', {
            method: 'POST',
            body: { request_id: requestId }
        });
    },

    async rejectTermination(requestId) {
        return apiCall('contracts.php?action=reject-termination', {
            method: 'POST',
            body: { request_id: requestId }
        });
    },
};

// Subscriptions API
const SubscriptionsAPI = {
    async getStatus() {
        return apiCall('subscriptions.php', { method: 'GET' });
    },

    async subscribe(plan, paymentData) {
        return apiCall('subscriptions.php', {
            method: 'POST',
            body: {
                plan: plan,
                payment_method: paymentData.method || 'card',
                card_number: paymentData.cardNumber || '',
                card_name: paymentData.cardName || '',
                card_expiry: paymentData.cardExpiry || '',
            },
        });
    },
};

// Export for use in other scripts
window.UNIDAR_API = {
    Auth: AuthAPI,
    Listings: ListingsAPI,
    Verifications: VerificationsAPI,
    Messages: MessagesAPI,
    Roommates: RoommatesAPI,
    Reports: ReportsAPI,
    Admin: AdminAPI,
    SavedListings: SavedListingsAPI,
    Contracts: ContractsAPI,
    Subscriptions: SubscriptionsAPI,
};