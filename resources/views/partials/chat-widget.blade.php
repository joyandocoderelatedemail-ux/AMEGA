<!-- AMEGA Hallmark Instant Message Widget -->
<div x-data="amegaChatWidget()" 
     class="relative"
     @keydown.escape.window="isOpen = false">

    <!-- Floating "Message Now" Action Button -->
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
            Message Now
        </span>

        <!-- Unread / Notification Pill -->
        <span x-show="hasUnread" 
              x-transition:enter="transition ease-out duration-300"
              x-transition:enter-start="scale-0"
              x-transition:enter-end="scale-100"
              class="w-2.5 h-2.5 rounded-full bg-accent animate-bounce"></span>
    </button>

    <!-- Instant Message Chat Drawer Popup -->
    <div x-show="isOpen"
         x-transition:enter="transition ease-out duration-300 transform"
         x-transition:enter-start="opacity-0 translate-y-8 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-200 transform"
         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
         x-transition:leave-end="opacity-0 translate-y-8 scale-95"
         class="fixed bottom-24 right-4 sm:right-8 w-[calc(100vw-2rem)] sm:w-96 h-[540px] max-h-[82vh] bg-white rounded-3xl shadow-2xl border border-gray-100 flex flex-col z-[100] overflow-hidden"
         style="display: none;">

        <!-- Chat Header -->
        <div class="bg-gradient-to-r from-navy via-primary to-navy p-4 text-white flex items-center justify-between shrink-0 border-b border-white/10 relative overflow-hidden">
            <div class="absolute -right-10 -bottom-10 w-32 h-32 rounded-full bg-accent/10 blur-xl pointer-events-none"></div>

            <div class="flex items-center gap-3 relative z-10">
                <div class="relative">
                    <img src="{{ asset('newassets/Amega Brand/LOGO/AMEGA LOGO_UPDATED WHITE.png') }}" alt="AMEGA Support" class="w-9 h-9 rounded-xl object-contain bg-white/10 p-1 border border-white/20">
                    <span class="absolute -bottom-0.5 -right-0.5 w-3 h-3 bg-emerald-400 border-2 border-navy rounded-full"></span>
                </div>
                <div>
                    <h3 class="font-heading font-bold text-sm text-white flex items-center gap-1.5">
                        AMEGA Live Assistant
                        <span class="px-1.5 py-0.5 rounded-full bg-accent/20 text-accent font-extrabold text-[9px] uppercase tracking-wider border border-accent/30">Official</span>
                    </h3>
                    <p class="text-[11px] text-white/70 flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                        Travel Agent Desk • Active Now
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-1 relative z-10">
                <button @click="resetChat()" 
                        type="button" 
                        class="p-1.5 text-white/60 hover:text-white hover:bg-white/10 rounded-lg transition-colors" 
                        title="Restart Conversation">
                    <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                </button>
                <button @click="isOpen = false" 
                        type="button" 
                        class="p-1.5 text-white/60 hover:text-white hover:bg-white/10 rounded-lg transition-colors" 
                        title="Close Chat">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
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
                        <p class="font-bold text-primary mb-1">Mabinogi! 👋 Welcome to AMEGA Travel & Tours.</p>
                        <p>How can we help you plan your journey today? Tap a quick question below or type your inquiry!</p>
                    </div>
                </div>
            </div>

            <!-- Messages List -->
            <template x-for="(msg, index) in messages" :key="index">
                <div>
                    <!-- User Message -->
                    <template x-if="msg.sender === 'user'">
                        <div class="flex items-end justify-end gap-2 my-2">
                            <div class="bg-primary text-white p-3.5 rounded-2xl rounded-tr-none shadow-sm text-xs leading-relaxed max-w-[82%] font-medium">
                                <p x-text="msg.text"></p>
                                <span class="text-[9px] text-white/60 block text-right mt-1" x-text="msg.time"></span>
                            </div>
                        </div>
                    </template>

                    <!-- Bot Response Message -->
                    <template x-if="msg.sender === 'bot'">
                        <div class="flex items-start gap-2.5 my-2">
                            <div class="w-7 h-7 rounded-lg bg-navy text-accent font-bold text-xs flex items-center justify-center shrink-0 border border-white/20 shadow-sm mt-1">
                                A
                            </div>
                            <div class="space-y-2 max-w-[85%]">
                                <div class="bg-white p-3.5 rounded-2xl rounded-tl-none shadow-sm border border-gray-100 text-xs text-dark leading-relaxed">
                                    <div x-html="msg.text"></div>
                                    <span class="text-[9px] text-dark/40 block text-right mt-1.5" x-text="msg.time"></span>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </template>

            <!-- Typing Indicator Simulation -->
            <div x-show="isTyping" class="flex items-center gap-2 text-dark/50 text-xs py-2 px-3 bg-white/80 rounded-2xl w-max shadow-sm border border-gray-100">
                <div class="w-6 h-6 rounded-lg bg-navy text-accent font-bold text-[10px] flex items-center justify-center shrink-0">A</div>
                <span class="italic text-[11px]">AMEGA Agent is typing...</span>
                <div class="flex gap-1 items-center">
                    <span class="w-1.5 h-1.5 rounded-full bg-primary animate-bounce"></span>
                    <span class="w-1.5 h-1.5 rounded-full bg-primary animate-bounce [animation-delay:0.2s]"></span>
                    <span class="w-1.5 h-1.5 rounded-full bg-primary animate-bounce [animation-delay:0.4s]"></span>
                </div>
            </div>

        </div>

        <!-- Quick Static Suggested Messages / Preset Chips -->
        <div class="px-4 py-2.5 bg-white border-t border-gray-100 shrink-0 space-y-2">
            <span class="text-[10px] font-extrabold uppercase tracking-widest text-dark/40 block">Frequently Asked Questions</span>
            
            <div class="flex flex-col gap-1.5 max-h-32 overflow-y-auto pr-1">
                <template x-for="(q, idx) in quickQuestions" :key="idx">
                    <button @click="askQuickQuestion(q)"
                            type="button"
                            class="text-left px-3 py-2 bg-gray-50 hover:bg-accent/15 hover:border-accent/40 rounded-xl text-[11px] font-semibold text-dark/80 hover:text-navy border border-gray-200/80 transition-all flex items-center justify-between group">
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
                        :disabled="!inputQuery.trim()"
                        class="p-2.5 bg-accent text-dark disabled:opacity-40 disabled:cursor-not-allowed font-bold rounded-xl hover:bg-accent-dark transition-all shadow-md shrink-0"
                        title="Send Message">
                    <i data-lucide="send" class="w-4 h-4"></i>
                </button>
            </form>
        </div>

    </div>
</div>

<script>
    function amegaChatWidget() {
        return {
            isOpen: false,
            hasUnread: true,
            isTyping: false,
            inputQuery: '',
            messages: [],
            quickQuestions: [
                {
                    id: 1,
                    question: '✈️ How do I book a tour package?',
                    reply: 'Booking is simple! You can browse our <a href="/packages" class="text-primary font-bold hover:underline">Tour Packages Directory</a>, pick your destination, and click <strong>Book Package</strong>. You can also view and track your reservations in your <a href="/client/dashboard" class="text-primary font-bold hover:underline">Client Portal</a>.'
                },
                {
                    id: 2,
                    question: '📄 What visa assistance services do you offer?',
                    reply: 'We offer expert visa assistance for <strong>Japan 🇯🇵, South Korea 🇰🇷, Schengen 🇪🇺, USA 🇺🇸, Australia 🇦🇺, Canada 🇨🇦</strong>, as well as <strong>Philippine Retirement Visas (SRRV)</strong> and visa extensions. Submit an inquiry form to get full document checklists!'
                },
                {
                    id: 3,
                    question: '📍 Where is your office & business hours?',
                    reply: 'Our primary branch is located at <strong>Angeles City, Pampanga, Philippines</strong>. We are open <strong>Monday – Saturday, 8:30 AM to 5:30 PM</strong>. Walk-ins and consultations are welcome!'
                },
                {
                    id: 4,
                    question: '🎒 Can I request a custom group/family tour?',
                    reply: 'Yes! We specialize in custom group itineraries, private transfers, and corporate travel. Submit a request on our <a href="/contact" class="text-primary font-bold hover:underline">Contact & Inquiry Page</a> or leave your contact details here!'
                }
            ],
            toggleChat() {
                this.isOpen = !this.isOpen;
                if (this.isOpen) {
                    this.hasUnread = false;
                    this.scrollToBottom();
                }
            },
            resetChat() {
                this.messages = [];
                this.isTyping = false;
            },
            getCurrentTime() {
                const now = new Date();
                return now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            },
            askQuickQuestion(q) {
                const userText = q.question;
                const botReply = q.reply;

                this.messages.push({
                    sender: 'user',
                    text: userText,
                    time: this.getCurrentTime()
                });

                this.isTyping = true;
                this.scrollToBottom();

                setTimeout(() => {
                    this.isTyping = false;
                    this.messages.push({
                        sender: 'bot',
                        text: botReply,
                        time: this.getCurrentTime()
                    });
                    this.scrollToBottom();
                    this.reinitLucide();
                }, 600);
            },
            sendCustomMessage() {
                const text = this.inputQuery.trim();
                if (!text) return;

                this.messages.push({
                    sender: 'user',
                    text: text,
                    time: this.getCurrentTime()
                });

                this.inputQuery = '';
                this.isTyping = true;
                this.scrollToBottom();

                setTimeout(() => {
                    this.isTyping = false;
                    this.messages.push({
                        sender: 'bot',
                        text: 'Thank you for your message! Our travel agents have logged your inquiry. For immediate assistance or custom bookings, feel free to fill out our <a href="/contact" class="text-primary font-bold hover:underline">Official Inquiry Form</a> or call us directly at <strong>(045) 123-4567</strong>.',
                        time: this.getCurrentTime()
                    });
                    this.scrollToBottom();
                    this.reinitLucide();
                }, 800);
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
        }
    }
</script>
