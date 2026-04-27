/**
 * UNIDAR Chat Widget — Symfony Version
 * Floating chat bubble + modal with conversation list, messaging, polling, archive/delete/block/report.
 * Calls Symfony JSON API endpoints directly (no window.UNIDAR_API dependency).
 */

class ChatWidget {
    constructor(currentUserId) {
        this.isOpen = false;
        this.activeConversationId = null;
        this.pollingInterval = 4000;
        this.listPollingInterval = 10000;
        this.pollTimer = null;
        this.listPollTimer = null;
        this.currentUserId = currentUserId;
        this.conversations = [];
        this.isInitialized = false;
        this.activeOtherUserId = null;
        this.isOptionsMenuOpen = false;
        this.currentView = 'active';

        if (this.currentUserId) {
            this.init();
        }
    }

    // Translation helper
    t(key) {
        if (window.UNIDAR_I18N && typeof window.UNIDAR_I18N.t === 'function') {
            return window.UNIDAR_I18N.t(key);
        }
        const fallbacks = {
            'chat_messages': 'Messages',
            'chat_active': 'Active',
            'chat_archived': 'Archived',
            'chat_no_messages': 'No messages yet.',
            'chat_type_message': 'Type a message...',
            'chat_send': 'Send',
            'chat_archive': 'Archive',
            'chat_unarchive': 'Unarchive',
            'chat_delete': 'Delete',
            'chat_block': 'Block User',
            'chat_report': 'Report',
            'chat_loading': 'Loading...'
        };
        return fallbacks[key] || key;
    }

    // Fetch helper
    async apiFetch(url, options = {}) {
        const defaults = {
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            credentials: 'same-origin',
            redirect: 'error'
        };
        const resp = await fetch(url, { ...defaults, ...options });
        if (resp.status === 402) {
            // Subscription required — surface a nice prompt instead of a generic error.
            let body = {};
            try { body = await resp.json(); } catch (_) {}
            const err = new Error(body.message || 'Subscription required.');
            err.subscriptionRequired = true;
            err.subscriptionUrl = body.subscription_url || '/subscription';
            throw err;
        }
        if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
        return resp.json();
    }

    showSubscriptionBlocker(msg, url) {
        // Inline modal — no alert() spam. Removes itself on close.
        if (document.getElementById('chatSubBlocker')) return;
        const overlay = document.createElement('div');
        overlay.id = 'chatSubBlocker';
        overlay.style.cssText = 'position:fixed;inset:0;z-index:99999;background:rgba(15,23,42,0.55);backdrop-filter:blur(6px);display:flex;align-items:center;justify-content:center;padding:1rem;animation:cwbFade 0.22s ease both;';
        overlay.innerHTML = `
            <style>
                @keyframes cwbFade { from{opacity:0} to{opacity:1} }
                @keyframes cwbPop  { from{opacity:0;transform:translateY(12px) scale(.96)} to{opacity:1;transform:translateY(0) scale(1)} }
            </style>
            <div style="background:#fff;border-radius:20px;max-width:420px;width:100%;box-shadow:0 30px 80px -20px rgba(0,0,0,0.35);overflow:hidden;animation:cwbPop 0.3s cubic-bezier(.2,.8,.2,1) both;">
                <div style="padding:1.5rem 1.5rem 0.75rem;background:linear-gradient(135deg,#6366f1,#ec4899);color:#fff;">
                    <div style="font-size:2rem;line-height:1;margin-bottom:0.5rem;">⭐</div>
                    <div style="font-weight:800;font-size:1.1rem;">Subscription required</div>
                </div>
                <div style="padding:1.25rem 1.5rem;color:#374151;font-size:0.92rem;line-height:1.5;">
                    ${msg || 'You need an active subscription to send messages on UNIDAR.'}
                </div>
                <div style="padding:0 1.5rem 1.5rem;display:flex;gap:0.625rem;">
                    <button id="cwbClose" style="flex:1;padding:0.7rem 1rem;border-radius:10px;border:1px solid #e5e7eb;background:#fff;font-weight:600;cursor:pointer;">Later</button>
                    <a href="${url}" style="flex:1.3;padding:0.7rem 1rem;border-radius:10px;background:linear-gradient(135deg,#6366f1,#ec4899);color:#fff;font-weight:700;text-align:center;text-decoration:none;">Subscribe now →</a>
                </div>
            </div>`;
        document.body.appendChild(overlay);
        const close = () => overlay.remove();
        overlay.addEventListener('click', (e) => { if (e.target === overlay) close(); });
        overlay.querySelector('#cwbClose').addEventListener('click', close);
    }

    async init() {
        if (this.isInitialized) return;
        try {
            this.render();
            this.bindEvents();
            await this.loadConversations();
            this.startListPolling();
            this.isInitialized = true;
        } catch (e) {
            console.error('ChatWidget init failed:', e);
        }
    }

    render() {
        // Bubble
        this.bubble = document.createElement('div');
        this.bubble.className = 'chat-widget-bubble';
        this.bubble.id = 'chatWidgetBubble';
        this.bubble.innerHTML = `
            <span>💬</span>
            <span class="unread-badge" id="chatWidgetBadge" style="display: none;">0</span>
        `;
        document.body.appendChild(this.bubble);

        // Modal
        this.modal = document.createElement('div');
        this.modal.className = 'chat-widget-modal';
        this.modal.id = 'chatWidgetModal';
        this.modal.innerHTML = `
            <div class="chat-widget-header">
                <h3 id="chatWidgetTitle">${this.t('chat_messages')}</h3>
                <div style="display: flex; align-items: center; gap: 0.25rem;">
                    <button id="chatWidgetOptions" class="btn-icon" style="display: none; background:none; border:none; cursor:pointer; font-size:1.2rem; padding:4px 8px;">⋮</button>
                    <button id="chatWidgetBack" class="btn-icon" style="display: none; background:none; border:none; cursor:pointer; font-size:1.1rem; padding:4px 8px;">⬅️</button>
                    <button id="chatWidgetClose" class="btn-icon" style="background:none; border:none; cursor:pointer; font-size:1.1rem; padding:4px 8px;">✖️</button>
                </div>
            </div>
            <div id="chatWidgetOptionsMenu" class="chat-widget-options-menu" style="display: none;">
                <button data-action="archive">📦 ${this.t('chat_archive')}</button>
                <button data-action="delete">🗑️ ${this.t('chat_delete')}</button>
                <button data-action="block" class="text-danger">🚫 ${this.t('chat_block')}</button>
                <button data-action="report" class="text-danger">🚩 ${this.t('chat_report')}</button>
            </div>
            <div class="chat-widget-content" id="chatWidgetContent">
                <div class="empty-state" style="padding: 40px; text-align: center;">
                    <p>${this.t('chat_loading')}</p>
                </div>
            </div>
        `;
        document.body.appendChild(this.modal);
    }

    bindEvents() {
        this.bubble.onclick = () => this.toggle();

        document.getElementById('chatWidgetBack').onclick = () => this.showConversations();
        document.getElementById('chatWidgetOptions').onclick = () => this.toggleOptionsMenu();
        document.getElementById('chatWidgetClose').onclick = () => this.toggle(false);

        // Options menu actions
        const menu = document.getElementById('chatWidgetOptionsMenu');
        menu.querySelector('[data-action="archive"]').onclick = () => this.handleArchive();
        menu.querySelector('[data-action="delete"]').onclick = () => this.handleDelete();
        menu.querySelector('[data-action="block"]').onclick = () => this.handleBlock();
        menu.querySelector('[data-action="report"]').onclick = () => this.handleReport();

        // Close options when clicking outside
        this.modal.addEventListener('click', (e) => {
            if (this.isOptionsMenuOpen && !e.target.closest('.chat-widget-options-menu') && !e.target.closest('#chatWidgetOptions')) {
                this.toggleOptionsMenu(false);
            }
        });

        // Global event: other pages can dispatch openChat to start a conversation
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
            const data = await this.apiFetch('/api/conversations');
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
        const filtered = this.conversations.filter(c =>
            this.currentView === 'archived' ? parseInt(c.is_archived) === 1 : parseInt(c.is_archived) !== 1
        );

        let listHtml = '';
        if (filtered.length === 0) {
            listHtml = `
                <div class="empty-state" style="padding: 40px; text-align: center;">
                    <div style="font-size: 3rem; margin-bottom: 10px;">${this.currentView === 'archived' ? '📦' : '📭'}</div>
                    <p class="text-muted">${this.t('chat_no_messages')}</p>
                </div>
            `;
        } else {
            listHtml = `<div class="widget-conv-list">` +
                filtered.map(conv => `
                    <div class="widget-conv-item" data-conv-id="${conv.id}">
                        <div class="avatar" style="width:40px;height:40px;font-size:0.9rem;">${(conv.other_user_name || 'U').charAt(0).toUpperCase()}</div>
                        <div style="flex: 1; min-width: 0;">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:2px;">
                                <strong style="font-size:0.9rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:150px;">${this.escapeHtml(conv.other_user_name)}</strong>
                                <small style="color:var(--color-text-muted);font-size:0.75rem;">${this.timeAgo(conv.updated_at)}</small>
                            </div>
                            <div style="display:flex;justify-content:space-between;align-items:center;">
                                <p style="font-size:0.82rem;color:var(--color-text-muted);margin:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:200px;">
                                    ${this.escapeHtml(conv.last_message || this.t('chat_no_messages'))}
                                </p>
                                ${conv.unread_count > 0 ? `<span class="badge badge-warning" style="padding:2px 6px;font-size:10px;">${conv.unread_count}</span>` : ''}
                            </div>
                        </div>
                    </div>
                `).join('') +
                `</div>`;
        }

        content.innerHTML = `
            <div class="chat-widget-tabs">
                <div class="chat-widget-tab ${this.currentView === 'active' ? 'active' : ''}" data-view="active">${this.t('chat_active')}</div>
                <div class="chat-widget-tab ${this.currentView === 'archived' ? 'active' : ''}" data-view="archived">${this.t('chat_archived')}</div>
            </div>
            ${listHtml}
        `;

        // Bind tab clicks
        content.querySelectorAll('.chat-widget-tab').forEach(tab => {
            tab.onclick = () => { this.currentView = tab.dataset.view; this.renderConversationList(); };
        });

        // Bind conversation item clicks
        content.querySelectorAll('.widget-conv-item').forEach(item => {
            item.onclick = () => this.loadConversation(parseInt(item.dataset.convId));
        });

        document.getElementById('chatWidgetTitle').textContent = this.t('chat_messages');
        document.getElementById('chatWidgetBack').style.display = 'none';
        document.getElementById('chatWidgetOptions').style.display = 'none';
        this.toggleOptionsMenu(false);
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
        this.activeOtherUserId = null;
        this.stopPolling();
        this.loadConversations();
    }

    async fetchMessages() {
        if (!this.activeConversationId) return;
        try {
            const data = await this.apiFetch('/api/messages/' + this.activeConversationId);
            if (data.success) {
                this.activeOtherUserId = data.other_user_id;
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
                        <button type="submit" class="btn btn-primary" style="padding: 8px 16px; border-radius: 50px;">${this.t('chat_send')}</button>
                    </form>
                </div>
            `;
            document.getElementById('widgetMessageForm').onsubmit = (e) => {
                e.preventDefault();
                this.sendMessage();
            };
        }

        const container = document.getElementById('widgetMessagesContainer');
        const wasAtBottom = container.scrollHeight - container.scrollTop <= container.clientHeight + 50;

        container.innerHTML = messages.map(msg => {
            const isMe = msg.sender_id == this.currentUserId;
            return `
                <div class="message-bubble ${isMe ? 'sent' : 'received'}" style="font-size: 0.85rem; padding: 10px 14px; border-radius: 18px;">
                    <div>${this.escapeHtml(msg.message)}</div>
                    <small style="font-size: 0.65rem; opacity: 0.7; display: block; margin-top: 4px; text-align: right;">
                        ${this.timeAgo(msg.created_at)}
                    </small>
                </div>
            `;
        }).join('');

        if (wasAtBottom || messages.length <= 20) {
            container.scrollTop = container.scrollHeight;
        }
    }

    toggleOptionsMenu(force) {
        this.isOptionsMenuOpen = force !== undefined ? force : !this.isOptionsMenuOpen;
        const menu = document.getElementById('chatWidgetOptionsMenu');
        if (menu) {
            menu.style.display = this.isOptionsMenuOpen ? 'block' : 'none';
            if (this.isOptionsMenuOpen && this.activeConversationId) {
                const conv = this.conversations.find(c => c.id == this.activeConversationId);
                const archiveBtn = menu.querySelector('[data-action="archive"]');
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
            await this.apiFetch('/api/conversations/' + this.activeConversationId + '/archive', {
                method: 'POST',
                body: JSON.stringify({ archive: !isCurrentlyArchived })
            });
            this.toggleOptionsMenu(false);
            this.showConversations();
        } catch (e) {
            alert('Failed to update archive status.');
        }
    }

    async handleDelete() {
        if (!this.activeConversationId) return;
        if (!confirm('Are you sure you want to delete this conversation? This action cannot be undone.')) return;

        try {
            await this.apiFetch('/api/conversations/' + this.activeConversationId + '/delete', { method: 'POST' });
            this.toggleOptionsMenu(false);
            this.showConversations();
        } catch (e) {
            alert('Failed to delete conversation.');
        }
    }

    async handleBlock() {
        if (!this.activeOtherUserId) return;
        if (!confirm('Are you sure you want to block this user?')) return;

        try {
            await this.apiFetch('/api/users/' + this.activeOtherUserId + '/block', { method: 'POST' });
            alert('User blocked successfully.');
            this.toggleOptionsMenu(false);
            this.showConversations();
        } catch (e) {
            alert('Failed to block user.');
        }
    }

    async handleReport() {
        if (!this.activeOtherUserId) return;
        const reason = prompt('Reason for reporting? (scam_fraud, inappropriate_content, inappropriate_behavior, fake_listing, other)');
        if (!reason) return;
        const description = prompt('Provide more details:');
        if (!description) return;

        try {
            await this.apiFetch('/api/reports', {
                method: 'POST',
                body: JSON.stringify({
                    report_type: 'user',
                    reported_user_id: this.activeOtherUserId,
                    reason: reason,
                    description: description
                })
            });
            alert('Thank you. Your report has been submitted.');
            this.toggleOptionsMenu(false);
        } catch (e) {
            alert('Failed to submit report.');
        }
    }

    async sendMessage() {
        const input = document.getElementById('widgetMessageInput');
        const text = input.value.trim();
        if (!text || !this.activeConversationId) return;

        try {
            input.value = '';
            await this.apiFetch('/api/messages/' + this.activeConversationId, {
                method: 'POST',
                body: JSON.stringify({ message: text })
            });
            await this.fetchMessages();
            this.loadConversations(true);
        } catch (e) {
            if (e && e.subscriptionRequired) {
                this.showSubscriptionBlocker(e.message, e.subscriptionUrl);
                return;
            }
            alert('Failed to send message.');
        }
    }

    async markAsRead() {
        if (!this.activeConversationId) return;
        try {
            await this.apiFetch('/api/messages/' + this.activeConversationId + '/read', { method: 'PUT' });
        } catch (e) { /* silent */ }
    }

    async openConversationWith(userId, listingId) {
        try {
            const body = { recipient_id: userId };
            if (listingId) body.listing_id = listingId;

            const result = await this.apiFetch('/api/conversations/create', {
                method: 'POST',
                body: JSON.stringify(body)
            });
            if (result.success && result.conversation_id) {
                await this.loadConversation(result.conversation_id);
            }
        } catch (e) {
            if (e && e.subscriptionRequired) {
                this.showSubscriptionBlocker(e.message, e.subscriptionUrl);
                return;
            }
            alert('Could not start conversation.');
        }
    }

    startPolling() {
        this.stopPolling();
        this.pollTimer = setInterval(() => this.fetchMessages(), this.pollingInterval);
    }

    stopPolling() {
        if (this.pollTimer) { clearInterval(this.pollTimer); this.pollTimer = null; }
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
        if (!dateString) return '';
        const now = new Date();
        const past = new Date(dateString);
        const diffSec = Math.floor((now - past) / 1000);
        if (diffSec < 60) return 'Just now';
        const diffMin = Math.floor(diffSec / 60);
        if (diffMin < 60) return diffMin + 'm ago';
        const diffHrs = Math.floor(diffMin / 60);
        if (diffHrs < 24) return diffHrs + 'h ago';
        const diffDays = Math.floor(diffHrs / 24);
        if (diffDays < 7) return diffDays + 'd ago';
        return past.toLocaleDateString();
    }
}
