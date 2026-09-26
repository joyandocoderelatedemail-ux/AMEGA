<?php

use App\Http\Controllers\Admin\AdminActivityLogController;
use App\Http\Controllers\Admin\AdminAgentController;
use App\Http\Controllers\Admin\AdminBookingController;
use App\Http\Controllers\Admin\AdminChatController;
use App\Http\Controllers\Admin\AdminClientSheetController;
use App\Http\Controllers\Admin\AdminCrmController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminDestinationController;
use App\Http\Controllers\Admin\AdminImmigrationCategoryController;
use App\Http\Controllers\Admin\AdminImmigrationPricingController;
use App\Http\Controllers\Admin\AdminInquiryController;
use App\Http\Controllers\Admin\AdminPackageConfiguratorController;
use App\Http\Controllers\Admin\AdminPackageController;
use App\Http\Controllers\Admin\AdminServiceController;
use App\Http\Controllers\Admin\AdminTestimonialController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\FileOwnerController;
use App\Http\Controllers\Admin\ImmigrationDashboardController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\ClientDashboardController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DataPrivacyConsentController;
use App\Http\Controllers\GuestChatController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\Srrv\SrrvApplicationController;
use App\Http\Controllers\Srrv\SrrvDashboardController;
use App\Http\Controllers\Srrv\SrrvRenewalController;
use App\Http\Controllers\Ticketing\BookingAgreementController;
use App\Http\Controllers\Ticketing\TicketBookingController;
use App\Http\Controllers\Ticketing\TicketClientController;
use App\Http\Controllers\Ticketing\TicketDraftController;
use App\Http\Controllers\Ticketing\TicketingDashboardController;
use App\Http\Controllers\TravelPackageController;
use App\Http\Controllers\UserDocumentController;
use App\Http\Controllers\VisaAssistance\VisaApplicationController;
use App\Http\Controllers\VisaAssistance\VisaAssistanceDashboardController;
use App\Models\Destination;
use App\Support\PhotoCredits;
use Illuminate\Support\Facades\Route;

// Public Front-end Routes
Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/services', [PageController::class, 'services'])->name('services');
Route::get('/immigration-pricing', [PageController::class, 'immigrationPricing'])->name('immigration-pricing');
Route::get('/tours', [PageController::class, 'tours'])->name('tours');
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/contact', [PageController::class, 'contact'])->name('contact');
Route::post('/contact', [ContactController::class, 'submit'])->name('contact.submit')->middleware('throttle:contact');
Route::get('/gallery', [PageController::class, 'gallery'])->name('gallery');
Route::get('/why-us', [PageController::class, 'whyUs'])->name('why-us');
Route::get('/testimonials', [PageController::class, 'testimonials'])->name('testimonials');

// Public Live Chat Routes
// Poll runs on a short loop, so it carries a much higher ceiling than send.
Route::post('/chat/init', [GuestChatController::class, 'init'])->name('guest-chat.init')->middleware('throttle:chat-init');
Route::post('/chat/send', [GuestChatController::class, 'send'])->name('guest-chat.send')->middleware('throttle:chat-send');
Route::get('/chat/poll', [GuestChatController::class, 'poll'])->name('guest-chat.poll')->middleware('throttle:chat-poll');
Route::post('/chat/info', [GuestChatController::class, 'updateInfo'])->name('guest-chat.info')->middleware('throttle:chat-info');
Route::post('/chat/request-agent', [GuestChatController::class, 'requestAgent'])->name('guest-chat.request-agent')->middleware('throttle:chat-send');

// Public Package Directory & Details
Route::get('/packages', [TravelPackageController::class, 'index'])->name('packages.index');
Route::get('/packages/{package}', [TravelPackageController::class, 'show'])->name('packages.show');

// Public Booking System Routes
Route::post('/bookings', [BookingController::class, 'store'])->name('bookings.store')->middleware('throttle:bookings');
Route::get('/bookings/{reference}', [BookingController::class, 'confirmation'])->name('bookings.confirmation');

// Authentication Routes
Route::middleware(['guest'])->group(function () {
    Route::get('/login', [AuthController::class, 'showClientLogin'])->name('login');
    Route::get('/agent/login', [AuthController::class, 'showAgentLogin'])->name('agent.login');
    Route::get('/admin/login', [AuthController::class, 'showAdminLogin'])->name('admin.login');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');

    // The three login forms previously accepted unlimited attempts.
    Route::middleware('throttle:login')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/agent/login', [AuthController::class, 'agentLogin'])->name('agent.login.submit');
        Route::post('/admin/login', [AuthController::class, 'adminLogin']);
    });

    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register');
});
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Client Portal Routes (Protected by Auth)
Route::middleware(['auth'])->group(function () {
    Route::get('/client/dashboard', [ClientDashboardController::class, 'index'])->name('client.dashboard');
    Route::get('/client/bookings', [ClientDashboardController::class, 'index'])->name('client.bookings');
    Route::get('/client/profile', [ClientDashboardController::class, 'showProfile'])->name('client.profile');
    Route::put('/client/profile', [ClientDashboardController::class, 'updateProfile'])->name('client.profile.update');
});

// Private identity documents. These live on the private disk, so they are only
// reachable here — the controller decides who is entitled to see them.
Route::middleware(['auth'])->group(function () {
    Route::get('/users/{user}/profile-photo', [UserDocumentController::class, 'profilePhoto'])->name('users.profile-photo');
    Route::get('/users/{user}/government-id', [UserDocumentController::class, 'governmentId'])->name('users.government-id');
    Route::get('/users/{user}/passport', [UserDocumentController::class, 'passport'])->name('users.passport');
});

// Ticketing Portal Routes (Protected by Auth & Ticketing Middleware)
Route::middleware(['auth', 'ticketing'])->prefix('ticketing')->name('ticketing.')->group(function () {
    Route::get('/', [TicketingDashboardController::class, 'index']);
    Route::get('/dashboard', [TicketingDashboardController::class, 'index'])->name('dashboard');
    // Find or register the client first; the booking is then filled from their profile.
    Route::get('/clients/search', [TicketClientController::class, 'search'])->name('clients.search');
    Route::get('/clients/create', [TicketClientController::class, 'create'])->name('clients.create');
    Route::post('/clients', [TicketClientController::class, 'store'])->name('clients.store');

    // Tickets saved as pending in the wizard, to continue later (before the tickets resource so 'pending' is not read as a ticket id).
    Route::post('/tickets/pending', [TicketDraftController::class, 'store'])->name('tickets.pending.store');
    Route::delete('/tickets/pending/{draft}', [TicketDraftController::class, 'destroy'])->name('tickets.pending.destroy');
    Route::resource('tickets', TicketBookingController::class)->only(['index', 'create', 'store', 'show']);
    Route::get('/documents/{document}/download', [TicketBookingController::class, 'downloadDocument'])->name('documents.download');

    // Payment must be settled in full before a ticket can be issued; issuance
    // stays a separate, consent-gated action rather than a payment side effect.
    Route::post('/tickets/{ticket}/payment', [TicketBookingController::class, 'updatePayment'])->name('tickets.payment');
    Route::post('/tickets/{ticket}/issue', [TicketBookingController::class, 'issue'])->name('tickets.issue');
    Route::get('/tickets/{ticket}/voucher', [TicketBookingController::class, 'voucher'])->name('tickets.voucher');
    Route::get('/tickets/{ticket}/consent', [DataPrivacyConsentController::class, 'ticket'])->name('tickets.consent');

    // Booking Agreements (Auto-completed with Agent Pricing)
    Route::get('/tickets/{ticket}/agreement/create', [BookingAgreementController::class, 'create'])->name('agreements.create');
    Route::post('/tickets/{ticket}/agreement', [BookingAgreementController::class, 'store'])->name('agreements.store');
    Route::get('/agreements/{agreement}', [BookingAgreementController::class, 'show'])->name('agreements.show');
    Route::get('/agreements/{agreement}/edit', [BookingAgreementController::class, 'edit'])->name('agreements.edit');
    Route::put('/agreements/{agreement}', [BookingAgreementController::class, 'update'])->name('agreements.update');
});

// Visa Assistance Counter (Protected by Auth & Visa Assistance Middleware)
// Three services at one counter: visit visa, e-Visa, and passporting.
Route::middleware(['auth', 'visa'])->prefix('visa-assistance')->name('visa.')->group(function () {
    Route::get('/', [VisaAssistanceDashboardController::class, 'index']);
    Route::get('/dashboard', [VisaAssistanceDashboardController::class, 'index'])->name('dashboard');

    // Type-ahead: counter files and registered clients, by name, email, phone or passport
    Route::get('/lookup', [VisaApplicationController::class, 'lookup'])->name('lookup');

    // Counter files
    Route::resource('applications', VisaApplicationController::class);
    Route::post('/applications/{application}/advance', [VisaApplicationController::class, 'advance'])->name('applications.advance');
    Route::post('/applications/{application}/cancel', [VisaApplicationController::class, 'cancel'])->name('applications.cancel');
    // Each stage's work is recorded before the file may advance.
    Route::post('/applications/{application}/stage', [VisaApplicationController::class, 'recordStage'])->name('applications.stage');
    Route::post('/applications/{application}/payments', [VisaApplicationController::class, 'recordPayment'])->name('applications.payments');
    Route::get('/applications/{application}/acknowledgement', [VisaApplicationController::class, 'acknowledgement'])->name('applications.acknowledgement');
    Route::get('/applications/{application}/consent', [DataPrivacyConsentController::class, 'visa'])->name('applications.consent');

    // Applicants on a file
    Route::post('/applications/{application}/applicants', [VisaApplicationController::class, 'storeApplicant'])->name('applicants.store');
    Route::delete('/applications/{application}/applicants/{applicant}', [VisaApplicationController::class, 'destroyApplicant'])->name('applicants.destroy');

    // Document checklist
    Route::post('/applications/{application}/documents', [VisaApplicationController::class, 'storeDocument'])->name('documents.store');
    Route::get('/documents/{document}/download', [VisaApplicationController::class, 'downloadDocument'])->name('documents.download');
});

// SRRV Desk (Protected by Auth & SRRV Middleware)
// PRA retiree visa work: renewal application, annual renewal, and re-stamping.
Route::middleware(['auth', 'srrv'])->prefix('srrv')->name('srrv.')->group(function () {
    Route::get('/', [SrrvDashboardController::class, 'index']);
    Route::get('/dashboard', [SrrvDashboardController::class, 'index'])->name('dashboard');

    // Retiree files: the renewal application and re-stamping jobs
    Route::resource('applications', SrrvApplicationController::class);
    Route::post('/applications/{application}/advance', [SrrvApplicationController::class, 'advance'])->name('applications.advance');
    Route::post('/applications/{application}/cancel', [SrrvApplicationController::class, 'cancel'])->name('applications.cancel');
    Route::get('/applications/{application}/consent', [DataPrivacyConsentController::class, 'srrvApplication'])->name('applications.consent');
    // Each stage's work is recorded before the file may advance.
    Route::post('/applications/{application}/stage', [SrrvApplicationController::class, 'recordStage'])->name('applications.stage');
    Route::post('/applications/{application}/payments', [SrrvApplicationController::class, 'recordPayment'])->name('applications.payments');
    Route::post('/applications/{application}/documents', [SrrvApplicationController::class, 'storeDocument'])->name('applications.documents.store');
    Route::get('/documents/{document}/download', [SrrvApplicationController::class, 'downloadDocument'])->name('documents.download');

    // The annual renewal, due once a year
    Route::resource('renewals', SrrvRenewalController::class);
    Route::post('/renewals/{renewal}/advance', [SrrvRenewalController::class, 'advance'])->name('renewals.advance');
    Route::post('/renewals/{renewal}/collect', [SrrvRenewalController::class, 'collect'])->name('renewals.collect');
    Route::post('/renewals/{renewal}/stage', [SrrvRenewalController::class, 'recordStage'])->name('renewals.stage');
    Route::post('/renewals/{renewal}/payments', [SrrvRenewalController::class, 'recordPayment'])->name('renewals.payments');
    Route::post('/renewals/{renewal}/cancel', [SrrvRenewalController::class, 'cancel'])->name('renewals.cancel');
    Route::get('/renewals/{renewal}/consent', [DataPrivacyConsentController::class, 'srrvRenewal'])->name('renewals.consent');
});

// Admin Management Routes (Protected by Auth & Admin Middleware)
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index']);
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

    // Live Guest Chat Management
    Route::middleware('page.access:chats')->group(function () {
        Route::get('/chats', [AdminChatController::class, 'index'])->name('chats.index');
        Route::get('/chats/{conversation}', [AdminChatController::class, 'show'])->name('chats.show');
        Route::post('/chats/{conversation}/accept', [AdminChatController::class, 'accept'])->name('chats.accept');
        Route::post('/chats/{conversation}/reply', [AdminChatController::class, 'reply'])->name('chats.reply');
        Route::post('/chats/{conversation}/status', [AdminChatController::class, 'updateStatus'])->name('chats.status');
        Route::post('/chats/{conversation}/read', [AdminChatController::class, 'markRead'])->name('chats.read');
        Route::delete('/chats/{conversation}', [AdminChatController::class, 'destroy'])->name('chats.destroy');
    });

    Route::middleware('page.access:packages')->group(function () {
        // Package Configurator (Ready-Made & Custom)
        Route::get('/packages/configurator', [AdminPackageConfiguratorController::class, 'index'])->name('packages.configurator');
        Route::post('/packages/configurator/ready-made', [AdminPackageConfiguratorController::class, 'storeReadyMade'])->name('packages.configurator.ready-made');
        Route::post('/packages/configurator/custom', [AdminPackageConfiguratorController::class, 'storeCustom'])->name('packages.configurator.custom');
        Route::get('/packages/custom-inquiries/{inquiry}', [AdminPackageConfiguratorController::class, 'showCustom'])->name('packages.custom-inquiries.show');
        Route::patch('/packages/custom-inquiries/{inquiry}/status', [AdminPackageConfiguratorController::class, 'updateCustomStatus'])->name('packages.custom-inquiries.status');
        Route::delete('/packages/custom-inquiries/{inquiry}', [AdminPackageConfiguratorController::class, 'destroyCustom'])->name('packages.custom-inquiries.destroy');

        Route::post('/packages/{package}/toggle-featured', [AdminPackageController::class, 'toggleFeatured'])->name('packages.toggle-featured');
        Route::resource('packages', AdminPackageController::class)->except(['show']);
    });

    Route::middleware('page.access:destinations')->group(function () {
        Route::post('/destinations/{destination}/toggle-featured', [AdminDestinationController::class, 'toggleFeatured'])->name('destinations.toggle-featured');
        Route::resource('destinations', AdminDestinationController::class)->except(['show']);
    });

    Route::middleware('page.access:bookings')->group(function () {
        Route::post('/bookings/{booking}/status', [AdminBookingController::class, 'updateStatus'])->name('bookings.update-status');
        Route::resource('bookings', AdminBookingController::class)->only(['index', 'destroy']);
    });

    Route::middleware('page.access:inquiries')->group(function () {
        Route::resource('inquiries', AdminInquiryController::class)->only(['index', 'destroy']);
    });

    Route::middleware('page.access:crm')->group(function () {
        Route::get('/crm', [AdminCrmController::class, 'index'])->name('crm.index');
        Route::post('/crm/sync', [AdminCrmController::class, 'sync'])->name('crm.sync');
        Route::post('/crm/leads', [AdminCrmController::class, 'store'])->name('crm.leads.store');
        Route::get('/crm/leads/{lead}', [AdminCrmController::class, 'show'])->name('crm.leads.show');
        Route::put('/crm/leads/{lead}', [AdminCrmController::class, 'update'])->name('crm.leads.update');
        Route::delete('/crm/leads/{lead}', [AdminCrmController::class, 'destroy'])->name('crm.leads.destroy');
        Route::post('/crm/leads/{lead}/stage', [AdminCrmController::class, 'updateStage'])->name('crm.leads.stage');
        Route::post('/crm/leads/{lead}/notes', [AdminCrmController::class, 'storeNote'])->name('crm.leads.notes');
    });

    Route::middleware('page.access:services')->group(function () {
        Route::post('/services/{service}/toggle-status', [AdminServiceController::class, 'toggleStatus'])->name('services.toggle-status');
        Route::resource('services', AdminServiceController::class)->except(['show']);
    });

    // Immigration counter — its own portal, gated by the "immigration" page permission
    Route::middleware('immigration')->group(function () {
        Route::get('/immigration', [ImmigrationDashboardController::class, 'index'])->name('immigration.dashboard');
        Route::get('/client-sheets/blank', [AdminClientSheetController::class, 'blank'])->name('client-sheets.blank');
        // Type-ahead for the counter search box: sheets plus registered clients without one.
        Route::get('/client-sheets/lookup', [AdminClientSheetController::class, 'lookup'])->name('client-sheets.lookup');
        Route::get('/client-sheets/{clientSheet}/print', [AdminClientSheetController::class, 'print'])->name('client-sheets.print');
        Route::get('/client-sheets/{clientSheet}/consent', [DataPrivacyConsentController::class, 'immigration'])->name('client-sheets.consent');
        Route::resource('client-sheets', AdminClientSheetController::class)->except(['show']);
        Route::post('/immigration-categories/{immigration_category}/toggle-status', [AdminImmigrationCategoryController::class, 'toggleStatus'])->name('immigration-categories.toggle-status');
        Route::resource('immigration-categories', AdminImmigrationCategoryController::class)->except(['show']);
        Route::post('/immigration-pricing/{immigration_pricing}/toggle-status', [AdminImmigrationPricingController::class, 'toggleStatus'])->name('immigration-pricing.toggle-status');
        Route::post('/immigration-pricing/{immigration_pricing}/confirm-review', [AdminImmigrationPricingController::class, 'confirmReview'])->name('immigration-pricing.confirm-review');
        Route::resource('immigration-pricing', AdminImmigrationPricingController::class)
            ->parameters(['immigration-pricing' => 'immigration_pricing'])
            ->except(['show']);
    });

    Route::middleware('page.access:testimonials')->group(function () {
        Route::resource('testimonials', AdminTestimonialController::class)->only(['index', 'store', 'destroy']);
    });

    Route::middleware('page.access:users')->group(function () {
        Route::resource('users', AdminUserController::class);
    });

    Route::middleware('admin.only')->group(function () {
        Route::resource('agents', AdminAgentController::class)->except(['show']);
        // Hand a desk file to another staff member (desk files are private to their owner).
        Route::post('/files/{type}/{id}/owner', [FileOwnerController::class, 'update'])->whereNumber('id')->name('files.owner');
        Route::get('/activity-logs', [AdminActivityLogController::class, 'index'])->name('activity-logs.index');
        Route::get('/activity-logs/stream', [AdminActivityLogController::class, 'stream'])->name('activity-logs.stream');
    });
});

// Component preview sandbox — remove once the marquee is placed for real.
Route::get('/ui/diagonal-marquee', function () {
    return view('ui.diagonal-marquee-preview', [
        'destinations' => Destination::query()->take(8)->get(),
    ]);
})->name('ui.diagonal-marquee');

Route::get('/photo-credits', function () {
    return view('pages.photo-credits', ['photos' => PhotoCredits::deck()]);
})->name('photo-credits');
