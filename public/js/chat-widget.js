/**
 * UNIDAR Global Chat Widget
 * Refactored from MessageSystem to support floating widget interface
 */

class ChatWidget {
    constructor() {
        this.isOpen = false;
        this.activeConversationId = null;
        this.pollingInterval = 4000;
        this.listPollingInterval = 10000;
        this.pollTimer = null;
        this.listPollTimer = null;
        this.currentUser = null;
        this.conversations = [];
        this.isInitialized = false;
        this.activeOtherUserId = null; // Track the other user in the active conversation
        this.isOptionsMenuOpen = false;
        this.currentView = 'active'; // 'active' or 'archived'

        this.init();
    }

    // Translation helper - uses UNIDAR_I18N if available
    t(key) {
        if (window.UNIDAR_I18N && typeof window.UNIDAR_I18N.t === 'function') {
            return window.UNIDAR_I18N.t(key);
        }
        // Fallback translations
        const fallbacks = {
            'chat_messages': 'Messages',
            'chat_active': 'Active',
            'chat_archived': 'Archived',
            'chat_no_messages': 'No messages.',
            'chat_type_message': 'Type a message...',
            'chat_send': 'Send',
            'chat_archive': 'Archive',
            'chat_unarchive': 'Unarchive',
            'chat_delete': 'Delete',
            'chat_block': 'Block User',
            'chat_report': 'Report',
            'loading': 'Loading...'
        };
        return fallbacks[key] || key;
    }

    async init() {
        if (this.isInitialized) return;

        // Wait for API to be available
        if (!window.UNIDAR_API) {
            setTimeout(() => this.init(), 500);
            return;
        }

        try {
            const auth = await window.UNIDAR_API.Auth.checkAuth();
            if (!auth.authenticated) return;
            this.currentUser = auth.user;

            this.injectStyles();
            this.render();
            this.bindEvents();

            await this.loadConversations();
            this.startListPolling();

            this.isInitialized = true;
            console.log('ChatWidget initialized');
        } catch (e) {
            console.error('ChatWidget init failed:', e);
        }
    }

    injectStyles() {
        if (document.getElementById('chatWidgetStyles')) return;
        const link = document.createElement('link');
        link.id = 'chatWidgetStyles';
        link.rel = 'stylesheet';
        link.href = 'css/chat-widget.css';
        document.head.appendChild(link);
    }

    render() {
        // Create Bubble
        this.bubble = document.createElement('div');
        this.bubble.className = 'chat-widget-bubble';
        this.bubble.id = 'chatWidgetBubble';
        this.bubble.innerHTML = `
            <span>💬</span>
            <span class="unread-badge" id="chatWidgetBadge" style="display: none;">0</span>
        `;
        document.body.appendChild(this.bubble);

        // Create Modal
        this.modal = document.createElement('div');
        this.modal.className = 'chat-widget-modal';
        this.modal.id = 'chatWidgetModal';
        this.modal.innerHTML = `
            <div class="chat-widget-header">
                <h3 id="chatWidgetTitle">${this.t('chat_messages')}</h3>
                <div class="flex gap-sm items-center">
                    <button id="chatWidgetOptions" class="btn-icon" style="display: none;">⋮</button>
                    <button id="chatWidgetBack" class="btn-icon" style="display: none;">⬅️</button>
                    <button id="chatWidgetClose" class="btn-icon">✖️</button>
                </div>
            </div>
            <div id="chatWidgetOptionsMenu" class="chat-widget-options-menu" style="display: none;">
                <button onclick="chatWidget.handleArchive()">📦 ${this.t('chat_archive')}</button>
                <button onclick="chatWidget.handleDelete()">🗑️ ${this.t('chat_delete')}</button>
                <button onclick="chatWidget.handleBlock()" class="text-danger">🚫 ${this.t('chat_block')}</button>
                <button onclick="chatWidget.handleReport()" class="text-danger">🚩 ${this.t('chat_report')}</button>
            </div>
            <div class="chat-widget-content" id="chatWidgetContent">
                <div class="empty-state">
                    <p>${this.t('chat_loading')}</p>
                </div>
            </div>
        `;
        document.body.appendChild(this.modal);
    }

    bindEvents() {
        if (this.bubble) this.bubble.onclick = () => this.toggle();

        const backBtn = document.getElementById('chatWidgetBack');
        if (backBtn) backBtn.onclick = () => this.showConversations();

        const optionsBtn = document.getElementById('chatWidgetOptions');
        if (optionsBtn) optionsBtn.onclick = () => this.toggleOptionsMenu();

        const closeBtn = document.getElementById('chatWidgetClose');
        if (closeBtn) closeBtn.onclick = () => this.toggle(false);

        // Close options menu when clicking outside
        if (this.modal) {
            this.modal.addEventListener('click', (e) => {
                if (this.isOptionsMenuOpen && !e.target.closest('.chat-widget-options-menu') && !e.target.closest('#chatWidgetOptions')) {
                    this.toggleOptionsMenu(false);
                }
            });
        }

        // Listen for "Message Owner" or "Message User" clicks globally
        // This allows other pages to trigger the chat widget
        window.addEventListener('openChat', (e) => {
            const { userId, listingId } = e.detail;
            this.openConversationWith(userId, listingId);
        });
    }

    toggle(force) {
        this.isOpen = force !== undefined ? force : !this.isOpen;
        this.modal.classList.toggle('open', this.isOpen);

        if (this.isOpen && !this.activeConversationId) {
            this.showConversations();
        }

        if (!this.isOpen) {
            this.stopPolling();
        } else if (this.activeConversationId) {
            this.startPolling();
        }
    }

    async loadConversations(isPoll = false) {
        try {
            const data = await window.UNIDAR_API.Messages.getConversations();
            if (data.success) {
                this.conversations = data.conversations || [];
                this.updateBadge();
                if (!this.activeConversationId || !this.isOpen) {
                    this.renderConversationList();
                }
            }
        } catch (e) {
            if (!isPoll) console.error('Failed to load conversations:', e);
        }
    }

    updateBadge() {
        const totalUnread = this.conversations.reduce((sum, c) => sum + parseInt(c.unread_count || 0), 0);
        const badge = document.getElementById('chatWidgetBadge');
        if (badge) {
            badge.textContent = totalUnread;
            badge.style.display = totalUnread > 0 ? 'flex' : 'none';
            this.bubble.classList.toggle('has-unread', totalUnread > 0);
        }
    }

    renderConversationList() {
        if (this.activeConversationId && this.isOpen) return;

        const content = document.getElementById('chatWidgetContent');
        const filteredConversations = this.conversations.filter(c =>
            this.currentView === 'archived' ? !!parseInt(c.is_archived) : !parseInt(c.is_archived)
        );

        let listHtml = '';
        if (filteredConversations.length === 0) {
            listHtml = `
                <div class="empty-state" style="padding: 40px; text-align: center;">
                    <div style="font-size: 3rem; margin-bottom: 10px;">${this.currentView === 'archived' ? '📦' : '📭'}</div>
                    <p class="text-muted">${this.t('chat_no_messages')}</p>
                </div>
            `;
        } else {
            listHtml = `
                <div class="widget-conv-list">
                    ${filteredConversations.map(conv => `
                        <div class="widget-conv-item" onclick="chatWidget.loadConversation(${conv.id})">
                            <div class="avatar">${(conv.other_user_name || 'U').charAt(0).toUpperCase()}</div>
                            <div style="flex: 1; min-width: 0;">
                                <div class="flex justify-between items-center mb-xs">
                                    <strong class="text-truncate" style="max-width: 150px;">${this.escapeHtml(conv.other_user_name)}</strong>
                                    <small class="text-muted">${this.timeAgo(conv.updated_at)}</small>
                                </div>
                                <div class="flex justify-between items-center">
                                    <p class="text-muted text-truncate" style="font-size: 0.85rem; margin: 0;">
                                        ${this.escapeHtml(conv.last_message || this.t('chat_no_messages'))}
                                    </p>
                                    ${conv.unread_count > 0 ? `<span class="badge badge-warning" style="padding: 2px 6px; font-size: 10px;">${conv.unread_count}</span>` : ''}
                                </div>
                            </div>
                        </div>
                    `).join('')}
                </div>
            `;
        }

        content.innerHTML = `
            <div class="chat-widget-tabs">
                <div class="chat-widget-tab ${this.currentView === 'active' ? 'active' : ''}" onclick="chatWidget.setView('active')">${this.t('chat_active')}</div>
                <div class="chat-widget-tab ${this.currentView === 'archived' ? 'active' : ''}" onclick="chatWidget.setView('archived')">${this.t('chat_archived')}</div>
            </div>
            ${listHtml}
        `;

        document.getElementById('chatWidgetTitle').textContent = this.t('chat_messages');
        document.getElementById('chatWidgetBack').style.display = 'none';
        document.getElementById('chatWidgetOptions').style.display = 'none';
        this.toggleOptionsMenu(false);
    }

    setView(view) {
        this.currentView = view;
        this.renderConversationList();
    }

    async loadConversation(id) {
        this.activeConversationId = id;
        this.toggle(true);
        this.startPolling();
        await this.fetchMessages();

        document.getElementById('chatWidgetBack').style.display = 'block';
        document.getElementById('chatWidgetOptions').style.display = 'block';
    }

    showConversations() {
        this.activeConversationId = null;
        this.stopPolling();
        this.renderConversationList();
    }

    async fetchMessages() {
        if (!this.activeConversationId) return;
        try {
            const data = await window.UNIDAR_API.Messages.getConversation(this.activeConversationId);
            if (data.success) {
                this.activeOtherUserId = data.messages.length > 0 ? (data.messages[0].sender_id == this.currentUser.id ? data.messages[0].receiver_id : data.messages[0].sender_id) : null;
                // If no messages yet, we need another way to get other user id. 
                // But typically conversations have messages. If not, this is a fallback.
                if (!this.activeOtherUserId) {
                    const conv = this.conversations.find(c => c.id == this.activeConversationId);
                    if (conv) this.activeOtherUserId = conv.other_user_id;
                }

                this.renderChatArea(data.messages, data.other_user_name);
                this.markAsRead();
            }
        } catch (e) {
            console.error('Fetch messages failed:', e);
        }
    }

    renderChatArea(messages, otherUserName) {
        const content = document.getElementById('chatWidgetContent');
        document.getElementById('chatWidgetTitle').textContent = otherUserName;

        if (!document.getElementById('widgetChatArea')) {
            content.innerHTML = `
                <div id="widgetChatArea" class="widget-chat-area">
                    <div id="widgetMessagesContainer" class="widget-messages-container"></div>
                    <form id="widgetMessageForm" class="widget-message-form">
                        <input type="text" id="widgetMessageInput" placeholder="${this.t('chat_type_message')}" autocomplete="off">
                        <button type="submit" class="btn btn-primary" style="padding: 8px 16px;">${this.t('chat_send')}</button>
                    </form>
                </div>
            `;

            const form = document.getElementById('widgetMessageForm');
            if (form) {
                form.onsubmit = (e) => {
                    e.preventDefault();
                    this.sendMessage();
                };
            } else {
                console.error('Widget message form not found after render');
            }
        }

        const container = document.getElementById('widgetMessagesContainer');
        const wasAtBottom = container.scrollHeight - container.scrollTop <= container.clientHeight + 50;

        container.innerHTML = messages.map(msg => {
            const isMe = msg.sender_id == this.currentUser.id;
            return `
                <div class="message-bubble ${isMe ? 'sent' : 'received'}" style="font-size: 0.85rem; padding: 10px 14px; border-radius: 18px;">
                    <div>${this.escapeHtml(msg.message)}</div>
                    <small style="font-size: 0.65rem; opacity: 0.7; display: block; margin-top: 4px; text-align: right;">
                        ${this.timeAgo(msg.created_at)}
                    </small>
                </div>
            `;
        }).join('');

        if (wasAtBottom) {
            container.scrollTop = container.scrollHeight;
        }
    }

    toggleOptionsMenu(force) {
        this.isOptionsMenuOpen = force !== undefined ? force : !this.isOptionsMenuOpen;
        const menu = document.getElementById('chatWidgetOptionsMenu');
        if (menu) {
            menu.style.display = this.isOptionsMenuOpen ? 'block' : 'none';

            // Update Archive button text dynamically
            if (this.isOptionsMenuOpen && this.activeConversationId) {
                const conv = this.conversations.find(c => c.id == this.activeConversationId);
                const archiveBtn = menu.querySelector('button[onclick*="handleArchive"]');
                if (archiveBtn && conv) {
                    const isArchived = parseInt(conv.is_archived) === 1;
                    archiveBtn.innerHTML = isArchived ? '📤 Unarchive' : '📦 Archive';
                }
            }
        }
    }

    async handleArchive() {
        if (!this.activeConversationId) return;
        const conv = this.conversations.find(c => c.id == this.activeConversationId);
        const isCurrentlyArchived = conv ? parseInt(conv.is_archived) === 1 : false;

        try {
            await window.UNIDAR_API.Messages.toggleArchive(this.activeConversationId, !isCurrentlyArchived);
            this.toggleOptionsMenu(false);
            // Load conversations first to get fresh state, then show list
            await this.loadConversations(true);
            this.showConversations();
        } catch (e) {
            alert('Failed to update archive status: ' + e.message);
        }
    }

    async handleDelete() {
        if (!this.activeConversationId) return;
        if (!confirm('Are you sure you want to delete this conversation? This action cannot be undone.')) return;

        try {
            await window.UNIDAR_API.Messages.deleteConversation(this.activeConversationId);
            this.toggleOptionsMenu(false);
            this.showConversations();
            await this.loadConversations(true);
        } catch (e) {
            alert('Failed to delete: ' + e.message);
        }
    }

    async handleBlock() {
        if (!this.activeOtherUserId) return;
        if (!confirm('Are you sure you want to block this user? You will no longer be able to message each other.')) return;

        try {
            await window.UNIDAR_API.Messages.blockUser(this.activeOtherUserId);
            alert('User blocked successfully.');
            this.toggleOptionsMenu(false);
            this.showConversations();
            await this.loadConversations(true);
        } catch (e) {
            alert('Failed to block: ' + e.message);
        }
    }

    async handleReport() {
        if (!this.activeOtherUserId) return;
        const reason = prompt('Reason for reporting? (scam_fraud, inappropriate_content, inappropriate_behavior, fake_listing, other)');
        if (!reason) return;
        const description = prompt('Provide more details:');
        if (!description) return;

        try {
            await window.UNIDAR_API.Reports.create({
                report_type: 'user',
                reported_user_id: this.activeOtherUserId,
                reason: reason,
                description: description
            });
            alert('Thank you. Your report has been submitted.');
            this.toggleOptionsMenu(false);
        } catch (e) {
            alert('Failed to submit report: ' + e.message);
        }
    }

    async sendMessage() {
        const input = document.getElementById('widgetMessageInput');
        const text = input.value.trim();
        if (!text || !this.activeConversationId) return;

        try {
            input.value = '';
            await window.UNIDAR_API.Messages.sendMessage(this.activeConversationId, text);
            await this.fetchMessages();
            this.loadConversations(true);
        } catch (e) {
            alert('Failed to send: ' + e.message);
        }
    }

    async markAsRead() {
        if (!this.activeConversationId) return;
        try {
            await window.UNIDAR_API.Messages.markAsRead(this.activeConversationId);
        } catch (e) { }
    }

    async openConversationWith(userId, listingId = null) {
        try {
            const result = await window.UNIDAR_API.Messages.createConversation(userId, listingId);
            if (result.success && result.conversation_id) {
                await this.loadConversation(result.conversation_id);
            }
        } catch (e) {
            alert('Could not start conversation: ' + e.message);
        }
    }

    startPolling() {
        this.stopPolling();
        this.pollTimer = setInterval(() => this.fetchMessages(), this.pollingInterval);
    }

    stopPolling() {
        if (this.pollTimer) clearInterval(this.pollTimer);
    }

    startListPolling() {
        if (this.listPollTimer) clearInterval(this.listPollTimer);
        this.listPollTimer = setInterval(() => this.loadConversations(true), this.listPollingInterval);
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }

    timeAgo(dateString) {
        const now = new Date();
        const past = new Date(dateString);
        const diffMs = now - past;
        const diffSec = Math.floor(diffMs / 1000);
        const diffMin = Math.floor(diffSec / 60);
        const diffHrs = Math.floor(diffMin / 60);
        const diffDays = Math.floor(diffHrs / 24);

        if (diffSec < 60) return 'Just now';
        if (diffMin < 60) return `${diffMin}m ago`;
        if (diffHrs < 24) return `${diffHrs}h ago`;
        if (diffDays < 7) return `${diffDays}d ago`;
        return past.toLocaleDateString();
    }
}

// Global instance for convenience and cross-page access
let chatWidget = new ChatWidget();
window.chatWidget = chatWidget; // Ensure global visibility
