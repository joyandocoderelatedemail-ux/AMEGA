<script>
    function amegaGuestChatWidget() {
        return {
            isOpen: false,
            isLoading: false,
            isSending: false,
            isRequestingAgent: false,
            showInfoModal: false,
            unreadCount: 0,
            guestToken: '',
            guestName: '',
            guestEmail: '',
            chatStatus: 'open',
            assignedAgentName: null,
            inputQuery: '',
            messages: [],
            lastMessageId: 0,
            pollTimer: null,
            quickQuestions: [
                {
                    id: 1,
                    question: '✈️ How do I book a tour package?',
                    reply: 'Booking is simple! You can browse our Tour Packages Directory, pick your destination, and click Book Package.'
                },
                {
                    id: 2,
                    question: '📄 What visa assistance services do you offer?',
                    reply: 'We offer expert visa assistance for Japan, South Korea, Schengen, USA, Australia, Canada, and Philippine Retirement Visas (SRRV).'
                },
                {
                    id: 3,
                    question: '📍 Where is your office & business hours?',
                    reply: 'Our primary branch is located at Angeles City, Pampanga, Philippines. Open Monday – Saturday, 8:30 AM to 5:30 PM.'
                },
                {
                    id: 4,
                    question: '🎒 Can I request a custom group/family tour?',
                    reply: 'Yes! We specialize in custom group itineraries, private transfers, and corporate travel.'
                }
            ],
            init() {
                // Initialize or retrieve Guest Token from localStorage
                let token = localStorage.getItem('amega_guest_token');
                if (!token) {
                    token = 'gst_' + Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
                    localStorage.setItem('amega_guest_token', token);
                }
                this.guestToken = token;

                // Load saved contact details if present
                this.guestName = localStorage.getItem('amega_guest_name') || '';
                this.guestEmail = localStorage.getItem('amega_guest_email') || '';

                // Start background polling for unread badge
                this.startPolling(10000);
            },
            toggleChat() {
                this.isOpen = !this.isOpen;
                if (this.isOpen) {
                    this.initChatSession();
                    this.startPolling(3000);
                } else {
                    this.startPolling(10000);
                }
            },
            initChatSession() {
                this.isLoading = true;
                fetch('{{ route("guest-chat.init") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        guest_token: this.guestToken,
                        guest_name: this.guestName,
                        guest_email: this.guestEmail
                    })
                })
                .then(res => res.json())
                .then(data => {
                    this.isLoading = false;
                    if (data.success) {
                        this.guestToken = data.guest_token;
                        localStorage.setItem('amega_guest_token', data.guest_token);
                        this.messages = data.messages || [];
                        this.chatStatus = data.conversation ? data.conversation.status : 'open';
                        this.assignedAgentName = data.conversation ? data.conversation.assigned_agent_name : null;
                        if (this.messages.length > 0) {
                            this.lastMessageId = this.messages[this.messages.length - 1].id;
                        }
                        this.unreadCount = 0;
                        this.scrollToBottom();
                        this.reinitLucide();
                    }
                })
                .catch(err => {
                    this.isLoading = false;
                    console.error('Failed to init guest chat:', err);
                });
            },
            saveContactDetails() {
                if (this.guestName) localStorage.setItem('amega_guest_name', this.guestName);
                if (this.guestEmail) localStorage.setItem('amega_guest_email', this.guestEmail);

                fetch('{{ route("guest-chat.info") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        guest_token: this.guestToken,
                        guest_name: this.guestName,
                        guest_email: this.guestEmail
                    })
                })
                .then(res => res.json())
                .then(data => {
                    this.showInfoModal = false;
                });
            },
            askQuickQuestion(q) {
                // Instantly show user question & static auto-reply in chat view
                const now = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                
                const userMsg = {
                    id: 'temp_q_' + Date.now(),
                    sender_type: 'guest',
                    message: q.question,
                    formatted_time: now,
                    is_read: false
                };
                this.messages.push(userMsg);

                const autoReply = {
                    id: 'temp_r_' + Date.now(),
                    sender_type: 'admin',
                    message: q.reply,
                    formatted_time: now,
                    is_read: true
                };
                this.messages.push(autoReply);

                this.scrollToBottom();
                this.reinitLucide();

                // Persist question to backend
                fetch('{{ route("guest-chat.send") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        guest_token: this.guestToken,
                        message: q.question,
                        guest_name: this.guestName,
                        guest_email: this.guestEmail
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.message) {
                        userMsg.id = data.message.id;
                        this.lastMessageId = data.message.id;
                    }
                });
            },
            requestLiveAgent() {
                if (this.isRequestingAgent || this.chatStatus === 'pending_agent') return;

                this.isRequestingAgent = true;

                // Push instant local notification message
                const now = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                const localMsg = {
                    id: 'temp_req_' + Date.now(),
                    sender_type: 'system',
                    message: 'Requesting a live agent... Please wait while a travel agent connects to your chat.',
                    formatted_time: now,
                    is_read: true
                };
                this.messages.push(localMsg);
                this.chatStatus = 'pending_agent';
                this.scrollToBottom();
                this.reinitLucide();

                fetch('{{ route("guest-chat.request-agent") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        guest_token: this.guestToken,
                        guest_name: this.guestName,
                        guest_email: this.guestEmail
                    })
                })
                .then(res => res.json())
                .then(data => {
                    this.isRequestingAgent = false;
                    if (data.success) {
                        if (data.guest_token) {
                            this.guestToken = data.guest_token;
                            localStorage.setItem('amega_guest_token', data.guest_token);
                        }
                        this.chatStatus = data.status || 'pending_agent';
                        this.assignedAgentName = data.assigned_agent_name || null;
                        this.pollMessages();
                    }
                })
                .catch(err => {
                    this.isRequestingAgent = false;
                    console.error('Failed to request live agent:', err);
                });
            },
            sendCustomMessage() {
                const text = this.inputQuery.trim();
                if (!text || this.isSending) return;

                this.isSending = true;
                const payload = {
                    guest_token: this.guestToken,
                    message: text,
                    guest_name: this.guestName,
                    guest_email: this.guestEmail,
                    request_agent: true
                };

                this.inputQuery = '';

                fetch('{{ route("guest-chat.send") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify(payload)
                })
                .then(res => res.json())
                .then(data => {
                    this.isSending = false;
                    if (data.success) {
                        this.messages.push(data.message);
                        this.lastMessageId = data.message.id;
                        this.chatStatus = data.conversation_status || 'open';

                        // Custom questions automatically connect to a live agent
                        if (this.chatStatus === 'pending_agent' && !this.assignedAgentName) {
                            const now = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                            this.messages.push({
                                id: 'temp_req_' + Date.now(),
                                sender_type: 'system',
                                message: 'Requesting a live agent... Please wait while a travel agent connects to your chat.',
                                formatted_time: now,
                                is_read: true
                            });
                        }

                        this.scrollToBottom();
                        this.reinitLucide();
                    }
                })
                .catch(err => {
                    this.isSending = false;
                    console.error('Failed to send message:', err);
                });
            },
            pollMessages() {
                if (!this.guestToken) return;

                fetch(`{{ route("guest-chat.poll") }}?guest_token=${encodeURIComponent(this.guestToken)}&last_id=${this.lastMessageId}`, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json'
                    }
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        this.chatStatus = data.status;
                        if (data.assigned_agent_name !== undefined) {
                            this.assignedAgentName = data.assigned_agent_name;
                        }

                        if (data.messages && data.messages.length > 0) {
                            data.messages.forEach(msg => {
                                // Replace optimistic local system message with the persisted one to avoid duplicates
                                if (msg.sender_type === 'system' && typeof msg.message === 'string') {
                                    const tempIdx = this.messages.findIndex(m => m.sender_type === 'system' && String(m.id).startsWith('temp_') && m.message === msg.message);
                                    if (tempIdx !== -1) {
                                        this.messages.splice(tempIdx, 1, msg);
                                        return;
                                    }
                                }
                                // Add only if not already present
                                if (!this.messages.some(m => m.id === msg.id)) {
                                    this.messages.push(msg);
                                }
                            });
                            this.lastMessageId = this.messages[this.messages.length - 1].id;
                            this.scrollToBottom();
                            this.reinitLucide();
                        }

                        if (!this.isOpen) {
                            this.unreadCount = data.unread_count || 0;
                        } else {
                            this.unreadCount = 0;
                        }
                    }
                })
                .catch(err => {
                    // Silent catch for poll error
                });
            },
            startPolling(interval) {
                if (this.pollTimer) clearInterval(this.pollTimer);
                this.pollTimer = setInterval(() => {
                    this.pollMessages();
                }, interval);
            },
            scrollToBottom() {
                this.$nextTick(() => {
                    const el = document.getElementById('chat-scroll-body');
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
        };
    }
</script>

<!-- AMEGA Hallmark Real-Time Guest Chat Widget -->
<div x-data="amegaGuestChatWidget()" 
     class="relative"
     @keydown.escape.window="isOpen = false">

    <!-- Floating "Message Us" Action Button -->
    <button @click="toggleChat()"
            type="button"
            class="fixed bottom-6 right-6 sm:bottom-8 sm:right-8 z-[90] flex items-center gap-3 px-4 sm:px-5 py-3.5 bg-gradient-to-r from-navy via-primary to-primary-dark text-white rounded-full shadow-2xl hover:shadow-primary/50 transition-all duration-300 transform hover:-translate-y-1 border border-accent/40 group focus:outline-none focus:ring-4 focus:ring-accent/40"
            aria-label="Open Instant Message Support">
        
        <!-- Animated Online Status Pulse Indicator -->
        <span class="relative flex h-3 w-3">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
            <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500 border-2 border-white"></span>
        </span>

        <i data-lucide="message-square" class="w-5 h-5 text-accent group-hover:scale-110 transition-transform"></i>

        <span class="font-heading font-extrabold text-xs tracking-wide uppercase text-white">
            Message Us
        </span>

        <!-- Unread Message Notification Counter Badge -->
        <span x-show="unreadCount > 0" 
              x-text="unreadCount"
              x-transition:enter="transition ease-out duration-300"
              x-transition:enter-start="scale-0"
              x-transition:enter-end="scale-100"
              class="px-2 py-0.5 text-[10px] font-black rounded-full bg-accent text-dark shadow-md animate-bounce"></span>
    </button>

    <!-- Instant Message Chat Drawer Popup -->
    <div x-show="isOpen"
         x-cloak
         x-transition:enter="transition ease-out duration-300 transform"
         x-transition:enter-start="opacity-0 translate-y-8 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-200 transform"
         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
         x-transition:leave-end="opacity-0 translate-y-8 scale-95"
         class="fixed bottom-24 right-4 sm:right-8 w-[calc(100vw-2rem)] sm:w-96 h-[560px] max-h-[82vh] bg-white rounded-3xl shadow-2xl border border-gray-100 flex flex-col z-[100] overflow-hidden">

        <!-- Chat Header -->
        <div class="bg-gradient-to-r from-navy via-primary to-navy p-4 text-white flex items-center justify-between shrink-0 border-b border-white/10 relative overflow-hidden">
            <div class="absolute -right-10 -bottom-10 w-32 h-32 rounded-full bg-accent/10 blur-xl pointer-events-none"></div>

            <div class="flex items-center gap-3 relative z-10">
                <div class="relative">
                    <img src="{{ asset('newassets/Amega Brand/LOGO/AMEGA LOGO_UPDATED WHITE.png') }}" alt="AMEGA Support" class="w-9 h-9 rounded-xl object-contain bg-white/10 p-1 border border-white/20">
                    <span class="absolute -bottom-0.5 -right-0.5 w-3 h-3 border-2 border-navy rounded-full" :class="assignedAgentName ? 'bg-emerald-400' : (chatStatus === 'pending_agent' ? 'bg-amber-400 animate-pulse' : 'bg-emerald-400')"></span>
                </div>
                <div>
                    <h3 class="font-heading font-bold text-sm text-white flex items-center gap-1.5">
                        AMEGA Live Support
                        <span x-show="assignedAgentName" class="px-1.5 py-0.5 rounded-full bg-emerald-500/30 text-emerald-300 font-extrabold text-[9px] uppercase tracking-wider border border-emerald-400/40">Connected</span>
                        <span x-show="!assignedAgentName && chatStatus === 'pending_agent'" class="px-1.5 py-0.5 rounded-full bg-amber-500/30 text-amber-300 font-extrabold text-[9px] uppercase tracking-wider border border-amber-400/40 animate-pulse">Waiting Agent</span>
                        <span x-show="!assignedAgentName && chatStatus !== 'pending_agent'" class="px-1.5 py-0.5 rounded-full bg-accent/20 text-accent font-extrabold text-[9px] uppercase tracking-wider border border-accent/30">Auto Reply</span>
                    </h3>
                    <p class="text-[11px] text-white/80 flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full" :class="assignedAgentName ? 'bg-emerald-400 animate-pulse' : 'bg-accent animate-pulse'"></span>
                        <span x-text="assignedAgentName ? 'Agent ' + assignedAgentName + ' is connected' : (chatStatus === 'pending_agent' ? 'Connecting to live agent...' : 'Auto-Reply & Live Support Desk')"></span>
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-1 relative z-10">
                <button @click="showInfoModal = !showInfoModal" 
                        type="button" 
                        class="p-1.5 text-white/70 hover:text-white hover:bg-white/10 rounded-lg transition-colors" 
                        title="Edit Your Contact Details">
                    <i data-lucide="user-cog" class="w-4 h-4"></i>
                </button>
                <button @click="isOpen = false" 
                        type="button" 
                        class="p-1.5 text-white/70 hover:text-white hover:bg-white/10 rounded-lg transition-colors" 
                        title="Close Chat">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
        </div>

        <!-- Optional Guest Info Drawer Sub-Header -->
        <div x-show="showInfoModal" 
             x-transition 
             class="bg-navy-dark p-3 text-xs text-white border-b border-white/10 space-y-2 shrink-0">
            <div class="flex justify-between items-center">
                <span class="font-bold text-accent">Your Contact Details (Optional)</span>
                <button @click="showInfoModal = false" class="text-white/60 hover:text-white text-[10px]">Close</button>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <input x-model="guestName" type="text" placeholder="Your Name" class="px-2.5 py-1.5 bg-white/10 border border-white/20 rounded-lg text-white text-xs placeholder-white/40 focus:outline-none focus:ring-1 focus:ring-accent">
                <input x-model="guestEmail" type="email" placeholder="Your Email" class="px-2.5 py-1.5 bg-white/10 border border-white/20 rounded-lg text-white text-xs placeholder-white/40 focus:outline-none focus:ring-1 focus:ring-accent">
            </div>
            <button @click="saveContactDetails()" class="w-full py-1.5 bg-accent text-dark font-bold text-[11px] rounded-lg hover:bg-accent-dark transition-all">Save Details</button>
        </div>

        <!-- Chat Stream Body -->
        <div id="chat-scroll-body" class="flex-1 overflow-y-auto p-4 space-y-4 bg-gray-50/70">
            
            <!-- Default Welcome Card -->
            <div class="flex items-start gap-2.5">
                <div class="w-7 h-7 rounded-lg bg-navy text-accent font-bold text-xs flex items-center justify-center shrink-0 border border-white/20 shadow-sm mt-1">
                    A
                </div>
                <div class="space-y-2 max-w-[85%]">
                    <div class="bg-white p-3.5 rounded-2xl rounded-tl-none shadow-sm border border-gray-100 text-xs text-dark leading-relaxed">
                        <p class="font-bold text-primary mb-1">MABUHAY! 👋 Welcome to AMEGA Travel & Tours.</p>
                        <p class="mb-2">Our travel agents are online! Pick an auto-reply FAQ below or connect directly to a live agent.</p>
                        <button @click="requestLiveAgent()" 
                                type="button" 
                                :disabled="isRequestingAgent || chatStatus === 'pending_agent'"
                                class="mt-1 w-full py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs rounded-xl shadow-md transition-all flex items-center justify-center gap-1.5 disabled:opacity-50 hover:scale-[1.02] active:scale-[0.98]">
                            <i data-lucide="headphones" class="w-4 h-4"></i>
                            <span x-text="chatStatus === 'pending_agent' ? 'Waiting for Live Agent...' : '🎧 Connect to a Live Agent'"></span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Loading Indicator -->
            <div x-show="isLoading" class="text-center py-4">
                <div class="inline-flex items-center gap-2 px-3 py-1.5 bg-white rounded-full shadow-sm text-xs text-gray-500">
                    <i data-lucide="loader-2" class="w-3.5 h-3.5 animate-spin text-primary"></i>
                    Connecting to live chat...
                </div>
            </div>

            <!-- Messages List from Database -->
            <template x-for="msg in messages" :key="msg.id">
                <div>
                    <!-- Guest (User) Message -->
                    <template x-if="msg.sender_type === 'guest'">
                        <div class="flex items-end justify-end gap-2 my-2">
                            <div class="bg-primary text-white p-3.5 rounded-2xl rounded-tr-none shadow-sm text-xs leading-relaxed max-w-[85%] font-medium">
                                <p x-text="msg.message" class="whitespace-pre-line"></p>

                                <template x-if="msg.attachment_url">
                                    <div class="mt-2 pt-2 border-t border-white/20">
                                        <template x-if="msg.attachment_type === 'image'">
                                            <a :href="msg.attachment_url" target="_blank">
                                                <img :src="msg.attachment_url" class="max-h-40 rounded-lg object-cover">
                                            </a>
                                        </template>
                                        <template x-if="msg.attachment_type !== 'image'">
                                            <a :href="msg.attachment_url" target="_blank" class="flex items-center gap-1.5 text-accent hover:underline text-[11px]">
                                                <i data-lucide="paperclip" class="w-3.5 h-3.5"></i>
                                                View Attachment
                                            </a>
                                        </template>
                                    </div>
                                </template>

                                <div class="flex items-center justify-end gap-1.5 mt-1">
                                    <span class="text-[9px] text-white/70" x-text="msg.formatted_time"></span>
                                    <i x-show="msg.is_read" data-lucide="check-check" class="w-3 h-3 text-accent" title="Read by Agent"></i>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Admin / Agent Response Message -->
                    <template x-if="msg.sender_type === 'admin'">
                        <div class="flex items-start gap-2.5 my-2">
                            <div class="w-7 h-7 rounded-lg bg-navy text-accent font-bold text-xs flex items-center justify-center shrink-0 border border-white/20 shadow-sm mt-1">
                                A
                            </div>
                            <div class="space-y-1 max-w-[85%]">
                                <div class="bg-white p-3.5 rounded-2xl rounded-tl-none shadow-sm border border-gray-100 text-xs text-dark leading-relaxed">
                                    <p x-html="msg.message" class="whitespace-pre-line"></p>

                                    <template x-if="msg.attachment_url">
                                        <div class="mt-2 pt-2 border-t border-gray-100">
                                            <template x-if="msg.attachment_type === 'image'">
                                                <a :href="msg.attachment_url" target="_blank">
                                                    <img :src="msg.attachment_url" class="max-h-40 rounded-lg object-cover">
                                                </a>
                                            </template>
                                            <template x-if="msg.attachment_type !== 'image'">
                                                <a :href="msg.attachment_url" target="_blank" class="flex items-center gap-1.5 text-primary hover:underline text-[11px]">
                                                    <i data-lucide="paperclip" class="w-3.5 h-3.5"></i>
                                                    View Attachment
                                                </a>
                                            </template>
                                        </div>
                                    </template>

                                    <span class="text-[9px] text-dark/40 block text-right mt-1.5" x-text="msg.formatted_time"></span>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- System Message Notification (e.g. Agent Connected) -->
                    <template x-if="msg.sender_type === 'system'">
                        <div class="my-2.5 px-3.5 py-2.5 rounded-2xl text-xs font-bold text-center shadow-sm border flex items-center justify-center gap-2"
                             :class="msg.message.includes('is connected') ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-blue-50 text-blue-800 border-blue-200'">
                            <i :data-lucide="msg.message.includes('is connected') ? 'user-check' : 'bot'" class="w-4 h-4 shrink-0"></i>
                            <span x-text="msg.message"></span>
                        </div>
                    </template>
                </div>
            </template>

            <!-- Status Pending Agent Banner -->
            <div x-show="chatStatus === 'pending_agent' && !assignedAgentName" class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-center text-xs text-amber-900 flex items-center justify-center gap-2 font-medium">
                <i data-lucide="loader-2" class="w-4 h-4 animate-spin text-amber-600"></i>
                Request sent! Waiting for a live travel agent to accept and connect...
            </div>

            <!-- Status Closed Notification -->
            <div x-show="chatStatus === 'closed'" class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-center text-xs text-amber-800">
                <i data-lucide="info" class="w-4 h-4 inline-block mr-1"></i>
                This conversation was marked closed by our agent. Sending a message will automatically reopen it!
            </div>

            <!-- Sending Indicator -->
            <div x-show="isSending" class="flex items-center gap-2 text-dark/50 text-xs py-1.5 px-3 bg-white/80 rounded-2xl w-max shadow-sm border border-gray-100">
                <span class="italic text-[11px]">Sending message...</span>
                <i data-lucide="loader-2" class="w-3 h-3 animate-spin text-primary"></i>
            </div>

        </div>

        <!-- Quick Static Suggested Messages / Preset Chips -->
        <div class="px-4 py-2.5 bg-white border-t border-gray-100 shrink-0 space-y-2">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-extrabold uppercase tracking-widest text-dark/40 block">Auto-Reply FAQs</span>
                <button @click="requestLiveAgent()" 
                        type="button" 
                        :disabled="isRequestingAgent || chatStatus === 'pending_agent'"
                        class="text-[11px] font-extrabold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200/80 px-2.5 py-1 rounded-lg transition-all flex items-center gap-1 shadow-sm disabled:opacity-50">
                    <i data-lucide="headphones" class="w-3.5 h-3.5 text-emerald-600"></i>
                    <span x-text="chatStatus === 'pending_agent' ? 'Waiting Agent...' : 'Connect to Live Agent'"></span>
                </button>
            </div>
            
            <div class="flex flex-col gap-1.5 max-h-28 overflow-y-auto pr-1">
                <template x-for="(q, idx) in quickQuestions" :key="idx">
                    <button @click="askQuickQuestion(q)"
                            type="button"
                            class="text-left px-3 py-1.5 bg-gray-50 hover:bg-accent/15 hover:border-accent/40 rounded-xl text-[11px] font-semibold text-dark/80 hover:text-navy border border-gray-200/80 transition-all flex items-center justify-between group">
                        <span x-text="q.question" class="truncate"></span>
                        <i data-lucide="chevron-right" class="w-3.5 h-3.5 text-dark/30 group-hover:text-primary group-hover:translate-x-0.5 transition-transform shrink-0 ml-1"></i>
                    </button>
                </template>
            </div>
        </div>

        <!-- Custom Input & Send Form -->
        <div class="p-3 bg-white border-t border-gray-100 shrink-0">
            <form @submit.prevent="sendCustomMessage()" class="flex items-center gap-2">
                <input x-model="inputQuery"
                       type="text" 
                       placeholder="Type your message or travel inquiry..."
                       class="flex-1 px-3.5 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs text-dark placeholder-dark/40 focus:outline-none focus:ring-2 focus:ring-primary focus:bg-white transition-all">
                <button type="submit"
                        :disabled="!inputQuery.trim() || isSending"
                        class="p-2.5 bg-accent text-dark disabled:opacity-40 disabled:cursor-not-allowed font-bold rounded-xl hover:bg-accent-dark transition-all shadow-md shrink-0 flex items-center justify-center"
                        title="Send Message">
                    <i data-lucide="send" class="w-4 h-4"></i>
                </button>
            </form>
        </div>

    </div>
</div>
