@extends('layouts.admin')

@section('title', 'Live Guest Chats - AMEGA Staff Portal')
@section('page_title', 'Guest Conversations Desk')

@section('content')
<div x-data="adminChatDashboard()" x-init="init()" class="h-[calc(100vh-6rem)] lg:h-[calc(100vh-5rem)] flex flex-col -m-4 sm:-m-6">

    <!-- Top Action Bar & Stat Badges Header -->
    <div class="bg-white border-b border-gray-200 px-4 sm:px-6 py-3 shrink-0 flex flex-wrap items-center justify-between gap-3 z-10 shadow-sm">
        
        <!-- Left: Page Title & Filters -->
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-2xl bg-primary/10 text-primary flex items-center justify-center font-bold shadow-sm">
                <i data-lucide="messages-square" class="w-5 h-5"></i>
            </div>
            <div>
                <h1 class="font-heading font-extrabold text-dark text-base sm:text-lg tracking-tight flex items-center gap-2">
                    Live Guest Support Desk
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                </h1>
                <p class="text-xs text-gray-500 hidden sm:block">Manage and respond to website visitors in real-time</p>
            </div>
        </div>

        <!-- Middle: Filter Pills -->
        <div class="flex items-center gap-1.5 bg-gray-100 p-1 rounded-2xl border border-gray-200/80">
            <button @click="setFilter('all')" 
                    :class="filter === 'all' ? 'bg-white text-navy font-bold shadow-sm' : 'text-gray-600 hover:text-dark'" 
                    class="px-3 py-1.5 rounded-xl text-xs transition-all flex items-center gap-1">
                <span>All</span>
                <span class="px-1.5 py-0.2 text-[10px] rounded-full bg-gray-200 text-gray-700" x-text="stats.total"></span>
            </button>
            <button @click="setFilter('pending')" 
                    :class="filter === 'pending' ? 'bg-white text-amber-800 font-bold shadow-sm' : 'text-gray-600 hover:text-dark'" 
                    class="px-3 py-1.5 rounded-xl text-xs transition-all flex items-center gap-1">
                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                <span>Pending Agent</span>
                <span class="px-1.5 py-0.2 text-[10px] rounded-full bg-amber-100 text-amber-900 font-extrabold" x-text="stats.pending"></span>
            </button>
            <button @click="setFilter('open')" 
                    :class="filter === 'open' ? 'bg-white text-emerald-700 font-bold shadow-sm' : 'text-gray-600 hover:text-dark'" 
                    class="px-3 py-1.5 rounded-xl text-xs transition-all flex items-center gap-1">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                <span>Open</span>
                <span class="px-1.5 py-0.2 text-[10px] rounded-full bg-emerald-100 text-emerald-800" x-text="stats.open"></span>
            </button>
            <button @click="setFilter('unread')" 
                    :class="filter === 'unread' ? 'bg-white text-amber-700 font-bold shadow-sm' : 'text-gray-600 hover:text-dark'" 
                    class="px-3 py-1.5 rounded-xl text-xs transition-all flex items-center gap-1">
                <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                <span>Unread</span>
                <span class="px-1.5 py-0.2 text-[10px] rounded-full bg-amber-100 text-amber-800 font-bold" x-text="stats.unread"></span>
            </button>
            <button @click="setFilter('closed')" 
                    :class="filter === 'closed' ? 'bg-white text-gray-800 font-bold shadow-sm' : 'text-gray-600 hover:text-dark'" 
                    class="px-3 py-1.5 rounded-xl text-xs transition-all flex items-center gap-1">
                <span>Closed</span>
                <span class="px-1.5 py-0.2 text-[10px] rounded-full bg-gray-200 text-gray-700" x-text="stats.closed"></span>
            </button>
        </div>

        <!-- Right: Search Input & Manual Refresh -->
        <div class="flex items-center gap-2">
            <div class="relative w-48 sm:w-64">
                <input x-model="searchQuery" 
                       @input.debounce.300ms="fetchConversations()"
                       type="text" 
                       placeholder="Search name, token, email..." 
                       class="w-full pl-8 pr-3 py-1.5 bg-gray-50 border border-gray-200 rounded-xl text-xs text-dark placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary focus:bg-white transition-all">
                <i data-lucide="search" class="w-4 h-4 text-gray-400 absolute left-2.5 top-1/2 -translate-y-1/2"></i>
            </div>

            <button @click="fetchConversations()" 
                    class="p-2 rounded-xl border border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-dark transition-all" 
                    title="Refresh List">
                <i data-lucide="refresh-cw" class="w-4 h-4" :class="isRefreshing ? 'animate-spin' : ''"></i>
            </button>
        </div>

    </div>

    <!-- Dual Panel Workspace -->
    <div class="flex-1 flex overflow-hidden bg-gray-100/50">

        <!-- Left Sidebar: Conversations List -->
        <div class="w-full md:w-80 lg:w-96 bg-white border-r border-gray-200 flex flex-col shrink-0"
             :class="activeConversationId ? 'hidden md:flex' : 'flex'">

            <!-- List Header Info -->
            <div class="px-4 py-2 bg-gray-50/80 border-b border-gray-100 flex items-center justify-between text-[11px] text-gray-500 shrink-0">
                <span>Showing <strong x-text="conversations.length"></strong> conversations</span>
                <span class="flex items-center gap-1 text-emerald-600">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                    Auto-Syncing
                </span>
            </div>

            <!-- Conversations Scroll Container -->
            <div class="flex-1 overflow-y-auto divide-y divide-gray-100">

                <template x-if="conversations.length === 0">
                    <div class="p-8 text-center text-gray-400">
                        <i data-lucide="inbox" class="w-10 h-10 mx-auto text-gray-300 mb-2"></i>
                        <p class="text-xs font-semibold">No conversations found</p>
                        <p class="text-[11px] text-gray-400 mt-1">Try adjusting your filters or search term</p>
                    </div>
                </template>

                <template x-for="c in conversations" :key="c.id">
                    <button @click="selectConversation(c.id)"
                            type="button"
                            :class="activeConversationId === c.id ? 'bg-primary/5 border-l-4 border-l-primary' : 'hover:bg-gray-50'"
                            class="w-full text-left p-4 transition-all flex items-start gap-3 relative group">

                        <!-- Guest Avatar -->
                        <div class="relative shrink-0 mt-0.5">
                            <div :class="c.is_registered ? 'bg-emerald-600 text-white' : 'bg-navy text-accent'"
                                 class="w-10 h-10 rounded-2xl font-extrabold text-sm flex items-center justify-center shadow-sm border border-white/20">
                                <span x-text="c.display_name.charAt(0).toUpperCase()"></span>
                            </div>
                            <span x-show="c.status === 'open'" class="absolute -bottom-0.5 -right-0.5 w-3 h-3 bg-emerald-400 border-2 border-white rounded-full"></span>
                        </div>

                        <!-- Info Content -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-1 mb-1">
                                <span class="font-bold text-xs text-dark truncate group-hover:text-primary transition-colors" x-text="c.display_name"></span>
                                <span class="text-[10px] text-gray-400 shrink-0" x-text="c.last_message_time || c.formatted_last_activity"></span>
                            </div>

                            <p class="text-xs text-gray-500 truncate mb-1.5" x-text="c.last_message"></p>

                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span x-show="c.status === 'pending_agent' || !c.is_accepted" class="px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-900 text-[9px] font-extrabold uppercase animate-pulse border border-amber-300">Pending Agent</span>
                                    <span x-show="c.is_accepted" class="px-1.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[9px] font-extrabold flex items-center gap-0.5">
                                        <i data-lucide="check-check" class="w-3 h-3 text-emerald-600"></i>
                                        <span x-text="c.assigned_agent_name ? 'Agent: ' + c.assigned_agent_name : 'Accepted'"></span>
                                    </span>
                                    <span x-show="c.status === 'closed'" class="px-1.5 py-0.5 rounded-full bg-gray-100 text-gray-600 text-[9px] font-extrabold uppercase">Closed</span>
                                    <span x-show="c.is_registered" class="px-1.5 py-0.5 rounded-full bg-blue-100 text-blue-800 text-[9px] font-extrabold">Customer</span>
                                </div>

                                <div class="flex items-center gap-1.5 shrink-0">
                                    <!-- Accept Button on Chat Card in Live Support Desk List -->
                                    <button x-show="!c.is_accepted || c.status === 'pending_agent'"
                                            @click.stop="acceptChat(c.id)"
                                            type="button"
                                            class="px-3 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-extrabold flex items-center gap-1 shadow-md transition-all hover:scale-105 active:scale-95 z-10">
                                        <i data-lucide="user-check" class="w-3.5 h-3.5"></i>
                                        <span>Accept</span>
                                    </button>

                                    <span x-show="c.unread_count > 0" 
                                          x-text="c.unread_count" 
                                          class="px-2 py-0.5 rounded-full bg-accent text-navy text-[10px] font-black shadow-sm"></span>
                                </div>
                            </div>
                        </div>

                    </button>
                </template>

            </div>

        </div>

        <!-- Right Main Panel: Active Chat Thread -->
        <div class="flex-1 bg-white flex flex-col min-w-0"
             :class="!activeConversationId ? 'hidden md:flex' : 'flex'">

            <!-- Empty State when no chat selected -->
            <template x-if="!activeConversationId">
                <div class="flex-1 flex flex-col items-center justify-center p-8 text-center bg-gray-50/50">
                    <div class="w-16 h-16 rounded-3xl bg-navy/5 text-navy flex items-center justify-center mb-4 border border-navy/10">
                        <i data-lucide="message-square" class="w-8 h-8 text-navy/40"></i>
                    </div>
                    <h3 class="font-heading font-extrabold text-base text-dark mb-1">Select a Conversation</h3>
                    <p class="text-xs text-gray-500 max-w-sm">Choose a visitor chat from the left column to view message history and send real-time replies.</p>
                </div>
            </template>

            <!-- Active Chat Header & Message Area -->
            <template x-if="activeConversation">
                <div class="flex-1 flex flex-col h-full overflow-hidden">

                    <!-- Chat Header -->
                    <div class="px-6 py-3.5 bg-white border-b border-gray-200 flex items-center justify-between shrink-0 shadow-sm z-10">
                        
                        <div class="flex items-center gap-3">
                            <!-- Mobile Back to List Button -->
                            <button @click="activeConversationId = null" 
                                    class="md:hidden p-1.5 rounded-lg text-gray-500 hover:bg-gray-100">
                                <i data-lucide="arrow-left" class="w-5 h-5"></i>
                            </button>

                            <div class="w-10 h-10 rounded-2xl bg-navy text-accent font-black text-sm flex items-center justify-center shadow-sm">
                                <span x-text="activeConversation.display_name.charAt(0).toUpperCase()"></span>
                            </div>

                            <div>
                                <div class="flex items-center gap-2">
                                    <h2 class="font-heading font-bold text-sm text-dark" x-text="activeConversation.display_name"></h2>
                                    <span :class="activeConversation.status === 'open' ? 'bg-emerald-100 text-emerald-800 border-emerald-200' : (activeConversation.status === 'pending_agent' ? 'bg-amber-100 text-amber-900 border-amber-300 animate-pulse' : 'bg-gray-100 text-gray-700 border-gray-200')"
                                          class="px-2 py-0.5 rounded-full text-[10px] font-extrabold uppercase border"
                                          x-text="activeConversation.status"></span>
                                </div>
                                <div class="text-[11px] text-gray-500 flex items-center gap-3 mt-0.5">
                                    <span class="font-mono text-[10px] text-gray-400" x-text="'Token: ' + activeConversation.guest_token"></span>
                                    <span x-show="activeConversation.guest_email" x-text="'Email: ' + activeConversation.guest_email"></span>
                                    <span x-show="activeConversation.guest_phone" x-text="'Phone: ' + activeConversation.guest_phone"></span>
                                </div>
                            </div>
                        </div>

                        <!-- Top Action Controls -->
                        <div class="flex items-center gap-2">
                            <!-- Accept Chat Action Button -->
                            <button @click="acceptChat(activeConversation.id)" 
                                    type="button" 
                                    :class="activeConversation.is_accepted ? 'bg-emerald-50 text-emerald-800 border-emerald-300 hover:bg-emerald-100' : 'bg-emerald-600 hover:bg-emerald-700 text-white shadow-md hover:scale-105 active:scale-95'"
                                    class="px-3.5 py-1.5 rounded-xl font-extrabold text-xs transition-all flex items-center gap-1.5 border">
                                <i :data-lucide="activeConversation.is_accepted ? 'shield-check' : 'user-check'" class="w-4 h-4"></i>
                                <span x-text="activeConversation.is_accepted ? 'Connected: Agent ' + activeConversation.assigned_agent_name : 'Accept Chat'"></span>
                            </button>

                            <template x-if="activeConversation.status === 'open' || activeConversation.status === 'pending_agent'">
                                <button @click="toggleStatus('closed')" 
                                        type="button" 
                                        class="px-3 py-1.5 rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold text-xs transition-all flex items-center gap-1.5">
                                    <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-600"></i>
                                    <span>Close Chat</span>
                                </button>
                            </template>

                            <template x-if="activeConversation.status === 'closed'">
                                <button @click="toggleStatus('open')" 
                                        type="button" 
                                        class="px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs transition-all flex items-center gap-1.5 shadow-sm">
                                    <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                                    <span>Reopen Chat</span>
                                </button>
                            </template>

                            <button @click="deleteConversation()" 
                                    type="button" 
                                    class="p-2 rounded-xl text-rose-500 hover:bg-rose-50 transition-colors" 
                                    title="Delete Conversation">
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                            </button>
                        </div>

                    </div>

                    <!-- Messages Thread Stream -->
                    <div id="admin-chat-scroll-body" class="flex-1 overflow-y-auto p-6 space-y-4 bg-gray-50/60">
                        
                        <div class="text-center my-2">
                            <span class="px-3 py-1 bg-gray-200/80 rounded-full text-[10px] font-semibold text-gray-600 uppercase tracking-wider">
                                Conversation Started • <span x-text="activeConversation.formatted_last_activity"></span>
                            </span>
                        </div>

                        <template x-for="msg in activeMessages" :key="msg.id">
                            <div>
                                <!-- Guest Message (Left) -->
                                <template x-if="msg.sender_type === 'guest'">
                                    <div class="flex items-start gap-3 my-3">
                                        <div class="w-8 h-8 rounded-xl bg-gray-200 text-gray-700 font-bold text-xs flex items-center justify-center shrink-0 shadow-sm mt-0.5">
                                            G
                                        </div>
                                        <div class="space-y-1 max-w-[75%]">
                                            <div class="bg-white p-4 rounded-2xl rounded-tl-none shadow-sm border border-gray-200 text-xs text-dark leading-relaxed">
                                                <p x-text="msg.message" class="whitespace-pre-line font-normal"></p>

                                                <template x-if="msg.attachment_url">
                                                    <div class="mt-2 pt-2 border-t border-gray-100">
                                                        <template x-if="msg.attachment_type === 'image'">
                                                            <a :href="msg.attachment_url" target="_blank">
                                                                <img :src="msg.attachment_url" class="max-h-48 rounded-lg object-cover">
                                                            </a>
                                                        </template>
                                                        <template x-if="msg.attachment_type !== 'image'">
                                                            <a :href="msg.attachment_url" target="_blank" class="flex items-center gap-1.5 text-primary hover:underline font-semibold">
                                                                <i data-lucide="paperclip" class="w-3.5 h-3.5"></i>
                                                                Download Attachment
                                                            </a>
                                                        </template>
                                                    </div>
                                                </template>
                                            </div>
                                            <span class="text-[10px] text-gray-400 block px-1" x-text="msg.formatted_time + ' (' + msg.formatted_date + ')'"></span>
                                        </div>
                                    </div>
                                </template>

                                <!-- Admin Message (Right) -->
                                <template x-if="msg.sender_type === 'admin'">
                                    <div class="flex items-end justify-end gap-3 my-3">
                                        <div class="space-y-1 max-w-[75%]">
                                            <div class="bg-primary text-white p-4 rounded-2xl rounded-tr-none shadow-sm text-xs leading-relaxed font-medium">
                                                <p x-text="msg.message" class="whitespace-pre-line"></p>

                                                <template x-if="msg.attachment_url">
                                                    <div class="mt-2 pt-2 border-t border-white/20">
                                                        <template x-if="msg.attachment_type === 'image'">
                                                            <a :href="msg.attachment_url" target="_blank">
                                                                <img :src="msg.attachment_url" class="max-h-48 rounded-lg object-cover">
                                                            </a>
                                                        </template>
                                                        <template x-if="msg.attachment_type !== 'image'">
                                                            <a :href="msg.attachment_url" target="_blank" class="flex items-center gap-1.5 text-accent hover:underline font-semibold">
                                                                <i data-lucide="paperclip" class="w-3.5 h-3.5"></i>
                                                                Download Attachment
                                                            </a>
                                                        </template>
                                                    </div>
                                                </template>
                                            </div>

                                            <div class="flex items-center justify-end gap-1.5 px-1">
                                                <span class="text-[10px] text-gray-400" x-text="msg.sender_name + ' • ' + msg.formatted_time"></span>
                                                <i data-lucide="check-check" class="w-3.5 h-3.5 text-emerald-500" title="Delivered"></i>
                                            </div>
                                        </div>

                                        <div class="w-8 h-8 rounded-xl bg-navy text-accent font-bold text-xs flex items-center justify-center shrink-0 shadow-sm mt-0.5">
                                            A
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>

                    </div>

                    <!-- Quick Reply Bar & Admin Message Input -->
                    <div class="p-4 bg-white border-t border-gray-200 shrink-0 space-y-3">
                        
                        <!-- Quick Canned Replies Preset Chips -->
                        <div class="flex items-center gap-2 overflow-x-auto pb-1 text-xs">
                            <span class="text-[10px] font-extrabold uppercase text-gray-400 tracking-wider shrink-0">Quick Reply:</span>
                            <template x-for="preset in cannedReplies" :key="preset">
                                <button @click="replyText = preset" 
                                        type="button" 
                                        class="px-2.5 py-1 bg-gray-100 hover:bg-primary/10 hover:text-primary rounded-lg text-gray-700 text-[11px] whitespace-nowrap transition-colors border border-gray-200/80">
                                    <span x-text="preset"></span>
                                </button>
                            </template>
                        </div>

                        <!-- Form Input -->
                        <form @submit.prevent="sendAdminReply()" class="flex items-end gap-2">
                            <div class="flex-1 bg-gray-50 border border-gray-200 rounded-2xl focus-within:ring-2 focus-within:ring-primary focus-within:bg-white transition-all p-2">
                                <textarea x-model="replyText" 
                                          @keydown.ctrl.enter="sendAdminReply()"
                                          rows="2" 
                                          placeholder="Type your response... (Ctrl + Enter to send)"
                                          class="w-full bg-transparent border-none focus:outline-none text-xs text-dark placeholder-gray-400 resize-none px-2"></textarea>
                            </div>

                            <button type="submit" 
                                    :disabled="!replyText.trim() || isSubmitting"
                                    class="px-5 py-3 bg-gradient-to-r from-navy via-primary to-primary text-white font-extrabold text-xs rounded-2xl hover:shadow-lg disabled:opacity-40 disabled:cursor-not-allowed transition-all flex items-center gap-2 shadow-md shrink-0">
                                <span x-show="!isSubmitting">Send Reply</span>
                                <span x-show="isSubmitting">Sending...</span>
                                <i data-lucide="send" class="w-4 h-4" x-show="!isSubmitting"></i>
                                <i data-lucide="loader-2" class="w-4 h-4 animate-spin" x-show="isSubmitting" style="display: none;"></i>
                            </button>
                        </form>

                    </div>

                </div>
            </template>

        </div>

    </div>

</div>

<script>
    function adminChatDashboard() {
        return {
            conversations: [],
            stats: { total: 0, open: 0, closed: 0, unread: 0 },
            filter: 'all',
            searchQuery: '',
            activeConversationId: null,
            activeConversation: null,
            activeMessages: [],
            replyText: '',
            isRefreshing: false,
            isSubmitting: false,
            pollTimer: null,
            cannedReplies: [
                "Hello! Thank you for contacting Amega Travel and Tours Services. How may we assist you?",
                "We are currently reviewing your inquiry. Please give us a moment to confirm details.",
                "You can view complete package details and pricing on our website!",
                "Thank you for choosing Amega Travel and Tours Services! Have a wonderful day."
            ],
            init() {
                const urlParams = new URLSearchParams(window.location.search);
                const initialChatId = urlParams.get('chat');
                if (initialChatId) {
                    this.activeConversationId = parseInt(initialChatId);
                }

                this.fetchConversations();
                if (this.activeConversationId) {
                    this.fetchActiveThread();
                }

                // Start background live sync poll every 3 seconds
                this.pollTimer = setInterval(() => {
                    this.fetchConversations(true);
                    if (this.activeConversationId) {
                        this.fetchActiveThread(true);
                    }
                }, 3000);
            },
            setFilter(f) {
                this.filter = f;
                this.fetchConversations();
            },
            fetchConversations(silent = false) {
                if (!silent) this.isRefreshing = true;

                const url = `{{ route("admin.chats.index") }}?status=${this.filter}&search=${encodeURIComponent(this.searchQuery)}`;
                fetch(url, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(res => res.json())
                .then(data => {
                    this.isRefreshing = false;
                    if (data.success) {
                        this.conversations = data.conversations || [];
                        this.stats = data.stats || this.stats;
                        this.reinitLucide();
                    }
                })
                .catch(err => {
                    this.isRefreshing = false;
                });
            },
            selectConversation(id) {
                this.activeConversationId = id;
                if (window.history.pushState) {
                    const newUrl = `${window.location.pathname}?chat=${id}`;
                    window.history.pushState({ path: newUrl }, '', newUrl);
                }
                this.fetchActiveThread();
            },
            fetchActiveThread(silent = false) {
                if (!this.activeConversationId) return;

                const url = `{{ url('admin/chats') }}/${this.activeConversationId}`;
                fetch(url, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(res => {
                    if (!res.ok) {
                        throw { status: res.status };
                    }
                    return res.json();
                })
                .then(data => {
                    if (data.success) {
                        this.activeConversation = data.conversation;
                        this.activeMessages = data.messages || [];
                        if (!silent) {
                            this.scrollToBottom();
                        }
                        this.reinitLucide();
                    }
                })
                .catch(err => {
                    // Conversation no longer exists — fall back to the conversation list
                    if (err && err.status === 404) {
                        this.activeConversationId = null;
                        this.activeConversation = null;
                        this.activeMessages = [];
                        if (window.history.replaceState) {
                            window.history.replaceState({}, '', window.location.pathname);
                        }
                        this.reinitLucide();
                    }
                    console.error('Failed to fetch chat thread:', err);
                });
            },
            sendAdminReply() {
                const text = this.replyText.trim();
                if (!text || !this.activeConversationId || this.isSubmitting) return;

                this.isSubmitting = true;
                const url = `{{ url('admin/chats') }}/${this.activeConversationId}/reply`;

                fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ message: text })
                })
                .then(res => res.json())
                .then(data => {
                    this.isSubmitting = false;
                    if (data.success) {
                        this.replyText = '';
                        this.activeMessages.push(data.message);
                        this.scrollToBottom();
                        this.fetchConversations(true);
                        this.reinitLucide();
                    }
                })
                .catch(err => {
                    this.isSubmitting = false;
                    console.error('Failed to send reply:', err);
                });
            },
            acceptChat(chatId) {
                const targetId = chatId || this.activeConversationId;
                if (!targetId) return;

                const url = `{{ url('admin/chats') }}/${targetId}/accept`;
                fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    }
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        this.fetchConversations(true);
                        if (this.activeConversationId === targetId) {
                            this.fetchActiveThread(true);
                        } else {
                            this.selectConversation(targetId);
                        }
                        this.reinitLucide();
                    }
                })
                .catch(err => {
                    console.error('Failed to accept chat:', err);
                });
            },
            toggleStatus(newStatus) {
                if (!this.activeConversationId) return;

                const url = `{{ url('admin/chats') }}/${this.activeConversationId}/status`;
                fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ status: newStatus })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        if (this.activeConversation) {
                            this.activeConversation.status = data.status;
                        }
                        this.fetchConversations(true);
                    }
                });
            },
            deleteConversation() {
                if (!confirm('Are you sure you want to delete this conversation and all its messages?')) return;

                const url = `{{ url('admin/chats') }}/${this.activeConversationId}`;
                fetch(url, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    }
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        this.activeConversationId = null;
                        this.activeConversation = null;
                        this.activeMessages = [];
                        this.fetchConversations();
                    }
                });
            },
            scrollToBottom() {
                this.$nextTick(() => {
                    const el = document.getElementById('admin-chat-scroll-body');
                    if (el) el.scrollTop = el.scrollHeight;
                });
            },
            reinitLucide() {
                this.$nextTick(() => {
                    if (window.lucide) {
                        window.lucide.createIcons();
                    }
                });
            }
        }
    }
</script>
@endsection
