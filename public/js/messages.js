/**
 * UNIDAR Messaging System
 * Handles real-time chat, polling, and UI updates
 */

class MessageSystem {
    constructor() {
        this.activeConversationId = null;
        this.pollingInterval = 3000; // 3 seconds
        this.pollTimer = null;
        this.listPollTimer = null;
        this.currentUser = null;
        
        this.elements = {
            conversationList: document.getElementById('conversationsList'),
            messagesView: document.getElementById('messagesView'),
            emptyState: document.querySelector('#messagesView .empty-state')
        };
        
        this.init();
    }
    
    async init() {
        this.currentUser = await checkAuthAndRedirect();
        if (!this.currentUser) return;

        if (this.currentUser.role === 'owner' && typeof applyOwnerNavbar === 'function' && typeof getCurrentPageName === 'function') {
            applyOwnerNavbar(getCurrentPageName());
        }
        
        // Handle URL parameters for initialization
        await this.handleUrlParams();
        
        this.bindEvents();
        this.loadConversations();
        
        // Poll for list updates (unread counts) every 10 seconds
        this.listPollTimer = setInterval(() => this.loadConversations(true), 10000);
    }
    
    async handleUrlParams() {
        const params = getUrlParams();
        
        try {
            if (params.conversation) {
                await this.loadConversation(params.conversation);
            } else if (params.user) {
                const userId = parseInt(params.user);
                if (isNaN(userId) || userId <= 0) {
                    console.error('Invalid user ID:', params.user);
                    return;
                }

                if (this.currentUser && userId === this.currentUser.id) {
                     console.warn('Cannot message yourself');
                     this.updateUrl(null);
                     return;
                }

                const result = await this.createConversation(userId);
                if (result.success && result.conversation_id) {
                    this.updateUrl(result.conversation_id);
                    await this.loadConversation(result.conversation_id);
                }
            } else if (params.listing && params.owner) {
                const ownerId = parseInt(params.owner);
                const listingId = parseInt(params.listing);

                if (isNaN(ownerId) || ownerId <= 0 || isNaN(listingId) || listingId <= 0) {
                     console.error('Invalid parameters:', params);
                     return;
                }

                if (this.currentUser && ownerId === this.currentUser.id) {
                    console.warn('Cannot message yourself');
                    this.updateUrl(null);
                    return;
                }

                const result = await this.createConversation(ownerId, listingId);
                if (result.success && result.conversation_id) {
                    this.updateUrl(result.conversation_id);
                    await this.loadConversation(result.conversation_id);
                }
            }
        } catch (error) {
            console.error('Initialization error:', error);
            const errorMessage = error.message && error.message.length > 0 
                ? error.message 
                : 'Failed to initialize conversation. Please try again.';
            showAlert(errorMessage, 'error');
            this.updateUrl(null);
        }
    }
    
    async createConversation(recipientId, listingId = null) {
        const hasApi = window.UNIDAR_API && window.UNIDAR_API.Messages && typeof window.UNIDAR_API.Messages.createConversation === 'function';
        if (hasApi) {
            return window.UNIDAR_API.Messages.createConversation(recipientId, listingId);
        }
        const body = { recipient_id: recipientId };
        if (listingId) body.listing_id = listingId;
        return apiCall('conversations.php', {
            method: 'POST',
            body: body
        });
    }
    
    updateUrl(conversationId) {
        const newPath = window.location.pathname;
        const newUrl = conversationId ? `${newPath}?conversation=${conversationId}` : newPath;
        window.history.replaceState({ path: newUrl }, '', newUrl);
    }
    
    bindEvents() {
        // Global event delegation for dynamic content
        document.addEventListener('click', (e) => {
            if (e.target.matches('#archiveBtn')) {
                this.toggleArchive();
            }
        });
        
        // Form submission is handled within renderChatArea to attach to dynamic element
    }
    
    async loadConversations(isPoll = false) {
        try {
            const data = await window.UNIDAR_API.Messages.getConversations();
            if (data.success) {
                this.renderConversationList(data.conversations);
                this.updateTotalUnreadCount(data.conversations);
            }
        } catch (error) {
            if (error && error.message && error.message.indexOf('Conversation ID required') !== -1) {
                this.renderConversationList([]);
                this.updateTotalUnreadCount([]);
                return;
            }
            if (!isPoll) console.error('Failed to load conversations:', error);
        }
    }
    
    renderConversationList(conversations) {
        const list = this.elements.conversationList;
        if (!list) return;
        
        const safeConversations = Array.isArray(conversations) ? conversations : [];
        
        if (safeConversations.length === 0) {
            list.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon">📭</div>
                    <p>No conversations yet</p>
                </div>
            `;
            return;
        }
        
        list.innerHTML = safeConversations.map(conv => `
            <div class="conversation-item ${conv.id == this.activeConversationId ? 'active' : ''}" 
                 onclick="messageSystem.loadConversation(${conv.id})">
                <div class="avatar">${(conv.other_user_name || 'U').charAt(0).toUpperCase()}</div>
                <div class="conversation-info">
                    <div class="conversation-header">
                        <span class="conversation-name">${this.escapeHtml(conv.other_user_name)}</span>
                        <span class="conversation-time">${timeAgo(conv.updated_at)}</span>
                    </div>
                    ${conv.listing_title ? `<div class="conversation-listing"><small>Re: ${this.escapeHtml(conv.listing_title)}</small></div>` : ''}
                    <div class="conversation-preview">
                        ${conv.unread_count > 0 ? `<span class="badge badge-warning">${conv.unread_count}</span>` : ''}
                        ${this.escapeHtml(conv.last_message || 'No messages yet')}
                    </div>
                </div>
            </div>
        `).join('');
    }
    
    async loadConversation(id) {
        const numericId = parseInt(id, 10);
        if (!numericId || numericId <= 0) {
            console.error('Invalid conversation ID:', id);
            return;
        }
        
        if (this.activeConversationId === numericId && document.getElementById('chatArea')) return;
        
        this.activeConversationId = numericId;
        
        // Highlight in list
        const items = this.elements.conversationList.querySelectorAll('.conversation-item');
        items.forEach(item => item.classList.remove('active'));
        // We'll re-render list soon, but for instant feedback:
        // (Optional: find item by index/id and add class)
        
        this.startPolling();
        await this.fetchMessages();
    }
    
    async fetchMessages() {
        if (!this.activeConversationId || this.activeConversationId <= 0) return;
        
        try {
            const data = await window.UNIDAR_API.Messages.getConversation(this.activeConversationId);
            if (data.success) {
                this.renderChatArea(data.messages, data.other_user_name || 'User');
                this.markAsRead();
            }
        } catch (error) {
            console.error('Failed to load messages:', error);
            if (error && error.message) {
                if (error.message.indexOf('Conversation ID required') !== -1 || error.message.indexOf('Access denied') !== -1) {
                    this.activeConversationId = null;
                    if (this.pollTimer) {
                        clearInterval(this.pollTimer);
                        this.pollTimer = null;
                    }
                    if (this.elements.messagesView) {
                        this.elements.messagesView.innerHTML = `
                            <div class="empty-state">
                                <div class="empty-state-icon">💬</div>
                                <h3 class="mb-sm">Conversation unavailable</h3>
                                <p>This conversation cannot be loaded. Please select another one.</p>
                            </div>
                        `;
                    }
                } else if (typeof showAlert === 'function') {
                    showAlert(error.message, 'error');
                }
            }
        }
    }
    
    renderChatArea(messages, otherUserName) {
        const container = this.elements.messagesView;
        
        // If chat area doesn't exist yet, create structure
        if (!document.getElementById('chatArea')) {
            container.innerHTML = `
                <div id="chatArea" class="chat-area">
                    <div class="chat-header">
                        <div class="flex items-center gap-sm">
                            <h3 id="chatHeaderName">${this.escapeHtml(otherUserName)}</h3>
                        </div>
                        <div class="chat-actions">
                            <button id="archiveBtn" class="btn-icon" title="Archive">📂</button>
                            <button id="blockBtn" class="btn-icon danger" title="Block User">🚫</button>
                        </div>
                    </div>
                    
                    <div id="messagesContainer" class="messages-container"></div>
                    
                    <form id="messageForm" class="message-form">
                        <input type="text" id="messageInput" class="form-input" placeholder="Type a message..." autocomplete="off">
                        <button type="submit" class="btn btn-primary">Send</button>
                    </form>
                </div>
            `;
            
            // Bind form submit
            document.getElementById('messageForm').addEventListener('submit', (e) => {
                e.preventDefault();
                this.sendMessage();
            });
            
            // Bind Block button
            document.getElementById('blockBtn').addEventListener('click', () => {
                 if(confirm('Are you sure you want to block this user? You will no longer receive messages from them.')) {
                     // logic to block
                     alert('Block feature coming soon');
                 }
            });
        } else {
             // Update header name if needed
             document.getElementById('chatHeaderName').textContent = this.escapeHtml(otherUserName);
        }
        
        const msgContainer = document.getElementById('messagesContainer');
        const wasScrolledToBottom = msgContainer.scrollHeight - msgContainer.scrollTop <= msgContainer.clientHeight + 100;
        
        msgContainer.innerHTML = messages.map(msg => {
            const isMe = msg.sender_id == this.currentUser.id;
            return `
                <div class="message-bubble ${isMe ? 'sent' : 'received'}">
                    <div class="message-meta">
                        <span>${isMe ? 'You' : this.escapeHtml(msg.sender_name)}</span>
                        <span>${timeAgo(msg.created_at)}</span>
                    </div>
                    <div class="message-text">${this.escapeHtml(msg.message)}</div>
                    ${isMe && msg.is_read ? '<div class="text-right" style="font-size: 10px; opacity: 0.7;">Read</div>' : ''}
                </div>
            `;
        }).join('');
        
        if (wasScrolledToBottom) {
            msgContainer.scrollTop = msgContainer.scrollHeight;
        }
    }
    
    async sendMessage() {
        const input = document.getElementById('messageInput');
        const text = input.value.trim();
        
        if (!text || !this.activeConversationId) return;
        
        try {
            input.value = ''; // Optimistic clear
            await window.UNIDAR_API.Messages.sendMessage(this.activeConversationId, text);
            this.fetchMessages(); // Immediate refresh
            this.loadConversations(); // Refresh list
        } catch (error) {
            alert('Failed to send message: ' + error.message);
        }
    }
    
    async markAsRead() {
        if (!this.activeConversationId) return;
        try {
            await window.UNIDAR_API.Messages.markAsRead(this.activeConversationId);
            // We should update the unread count in the list locally or fetch list again
            // this.loadConversations(); // Maybe too frequent?
        } catch (e) {
            // Ignore errors
        }
    }
    
    async toggleArchive() {
        if (!this.activeConversationId) return;
        if (!confirm('Archive this conversation?')) return;
        
        try {
            await window.UNIDAR_API.Messages.toggleArchive(this.activeConversationId, true);
            this.activeConversationId = null;
            this.elements.messagesView.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon">💬</div>
                    <h3>Conversation Archived</h3>
                    <p>Select another conversation</p>
                </div>
            `;
            this.loadConversations();
        } catch (error) {
            showAlert(error.message, 'error');
        }
    }
    
    updateTotalUnreadCount(conversations) {
        const list = Array.isArray(conversations) ? conversations : [];
        const total = list.reduce((sum, conv) => sum + parseInt(conv.unread_count || 0), 0);
        
        // Update nav badge if it exists (assuming ID 'navUnreadCount')
        const navBadge = document.getElementById('navUnreadCount');
        if (navBadge) {
            if (total > 0) {
                navBadge.textContent = total;
                navBadge.style.display = 'inline-block';
            } else {
                navBadge.style.display = 'none';
            }
        }
    }
    
    startPolling() {
        if (this.pollTimer) clearInterval(this.pollTimer);
        this.pollTimer = setInterval(() => this.fetchMessages(), this.pollingInterval);
    }
    
    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Initialize system
const messageSystem = new MessageSystem();
