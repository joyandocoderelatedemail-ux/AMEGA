@extends('layouts.app')

@section('title', 'Privacy Policy | AMEGA Travel and Tours')
@section('meta_description', 'How AMEGA Travel and Tours Services collects, uses, stores and protects your personal information, in line with the Data Privacy Act of 2012.')

@section('content')
    <div class="pt-20 bg-primary-dark text-white text-center py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <span class="inline-block text-xs font-semibold uppercase tracking-widest text-accent mb-2">Data Privacy Act of 2012</span>
            <h1 class="font-heading text-4xl sm:text-5xl font-bold">Privacy Policy</h1>
            <p class="text-blue-200/70 text-base max-w-2xl mx-auto mt-3">How we collect, use and protect your personal information.</p>
        </div>
    </div>

    <section class="py-16 bg-white">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-dark/80 text-[15px] leading-relaxed space-y-8 [&_h2]:font-heading [&_h2]:text-xl [&_h2]:text-dark [&_h2]:font-bold [&_h2]:mb-3 [&_ul]:list-disc [&_ul]:pl-5 [&_ul]:space-y-1.5">
            <p class="text-sm text-dark/50">Last updated: {{ \Illuminate\Support\Carbon::parse('2026-10-28')->format('F j, Y') }}</p>

            <div>
                <p>Amega Travel and Tours Services ("AMEGA", "we") respects your privacy. In line with the Data Privacy Act of 2012 (Republic Act No. 10173), its Implementing Rules and Regulations, and the issuances of the National Privacy Commission, this policy explains what personal information we collect through this website and at our office, why we collect it, and how we keep it safe.</p>
            </div>

            <div>
                <h2>What we collect</h2>
                <ul>
                    <li><strong>Inquiries:</strong> your name, email address, mobile number, nationality, number of travellers and your message, when you send the contact form.</li>
                    <li><strong>Tour package bookings:</strong> your name, email address, phone number, travel date, number of travellers and any special requests.</li>
                    <li><strong>Live chat:</strong> your name and email address and the messages you send. Your browser also remembers your chat name and email so you do not have to type them again.</li>
                    <li><strong>Travel and visa services:</strong> when you book a ticket or ask us to process a visa, immigration, passport or SRRV application, we collect what that service needs, such as your passport details, birth date, contact details, government IDs, photos and supporting documents.</li>
                </ul>
            </div>

            <div>
                <h2>Why we use it</h2>
                <ul>
                    <li>To answer your inquiries and chats, and to send quotations.</li>
                    <li>To book flights, tours and hotels, and to process the visa, immigration, passport or SRRV service you ask for.</li>
                    <li>To record your payments and issue receipts, tickets, vouchers and booking agreements.</li>
                    <li>To send you updates about your booking, such as payment confirmations, issued tickets, flight changes and travel reminders.</li>
                </ul>
                <p class="mt-3">We use your information only for the transaction you asked us to handle. We do not sell it, and we do not use it for unrelated marketing.</p>
            </div>

            <div>
                <h2>Who we share it with</h2>
                <p>Only with the parties needed to complete your transaction, such as airlines, hotels, tour operators, insurance providers, embassies and consulates, the Bureau of Immigration, the Department of Foreign Affairs and the Philippine Retirement Authority, and only the information they require. We may also disclose information when the law requires it.</p>
            </div>

            <div>
                <h2>How we protect it</h2>
                <ul>
                    <li>Passports, IDs, photos and other documents are kept in private storage that is not reachable from the internet. Only authorised AMEGA staff can open them, after signing in.</li>
                    <li>Each staff member sees only what their work requires, and staff activity is logged.</li>
                    <li>Before we process a service, we ask you to sign our Data Privacy Consent Form.</li>
                </ul>
            </div>

            <div>
                <h2>How long we keep it</h2>
                <p>We keep your information for as long as your transaction needs it, and afterwards only as long as required by law, for example for tax and accounting records. When it is no longer needed, we delete it or make it anonymous.</p>
            </div>

            <div>
                <h2>Your rights</h2>
                <p>Under the Data Privacy Act you have the right to be informed, to access your personal information, to object, to have it corrected, to have it erased or blocked, to data portability, and to claim damages. You may also file a complaint with the National Privacy Commission (privacy.gov.ph).</p>
            </div>

            <div>
                <h2>Contact us</h2>
                <p>For any question or request about your personal information, contact us:</p>
                <ul>
                    <li>Email: <a href="mailto:sales@amegatravelandtour.com" class="text-primary font-semibold hover:underline">sales@amegatravelandtour.com</a></li>
                    <li>Office: Unit 1 &amp; 2 Astrofield Bldg, Mitchell Avenue, Balibago, Angeles City, Pampanga 2009</li>
                    <li>Phone: +63 992 922 5733, +63 949 990 0663</li>
                </ul>
            </div>
        </div>
    </section>
@endsection
