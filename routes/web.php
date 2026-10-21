<?php

use App\Http\Controllers\Admin\AdminActivityLogController;
use App\Http\Controllers\Admin\AdminAgentController;
use App\Http\Controllers\Admin\AdminBookingController;
use App\Http\Controllers\Admin\AdminChatController;
use App\Http\Controllers\Admin\AdminClientSheetController;
use App\Http\Controllers\Admin\AdminCorporateAccountController;
use App\Http\Controllers\Admin\AdminCrmController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminDestinationController;
use App\Http\Controllers\Admin\AdminImmigrationCategoryController;
use App\Http\Controllers\Admin\AdminImmigrationPricingController;
use App\Http\Controllers\Admin\AdminInquiryController;
use App\Http\Controllers\Admin\AdminInsurancePlanController;
use App\Http\Controllers\Admin\AdminPackageConfiguratorController;
use App\Http\Controllers\Admin\AdminPackageController;
use App\Http\Controllers\Admin\AdminServiceController;
use App\Http\Controllers\Admin\AdminTestimonialController;
use App\Http\Controllers\Admin\AdminTicketApprovalController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\FileOwnerController;
use App\Http\Controllers\Admin\ImmigrationApprovalController;
use App\Http\Controllers\Admin\ImmigrationCaseController;
use App\Http\Controllers\Admin\ImmigrationDashboardController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\Cashier\CashierController;
use App\Http\Controllers\ClientDuplicateCheckController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DataPrivacyConsentController;
use App\Http\Controllers\GuestChatController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\Srrv\SrrvApplicationController;
use App\Http\Controllers\Srrv\SrrvDashboardController;
use App\Http\Controllers\Srrv\SrrvFormController;
use App\Http\Controllers\Srrv\SrrvRenewalController;
use App\Http\Controllers\Ticketing\BookingAgreementController;
use App\Http\Controllers\Ticketing\TicketAirlineController;
use App\Http\Controllers\Ticketing\TicketBookingController;
use App\Http\Controllers\Ticketing\TicketClientController;
use App\Http\Controllers\Ticketing\TicketDraftController;
use App\Http\Controllers\Ticketing\TicketFlightChangeController;
use App\Http\Controllers\Ticketing\TicketingDashboardController;
use App\Http\Controllers\Ticketing\TicketInsurancePlanController;
use App\Http\Controllers\Ticketing\TicketMessageController;
use App\Http\Controllers\Ticketing\TicketPackageConfiguratorController;
use App\Http\Controllers\TravelPackageController;
use App\Http\Controllers\UserDocumentController;
use App\Http\Controllers\VisaAssistance\TouristVisaApplicationController;
use App\Http\Controllers\VisaAssistance\VisaApplicationController;
use App\Http\Controllers\VisaAssistance\VisaAssistanceDashboardController;
use App\Http\Controllers\VisaAssistance\VisaWorksheetController;
use App\Models\Destination;
use App\Models\ImmigrationClientDocument;
use App\Models\ImmigrationOverstay;
use App\Models\ImmigrationPetition;
use App\Models\ImmigrationRestamping;
use App\Models\TouristVisaApplication;
use App\Models\VisaApplication;
use App\Support\PhotoCredits;
use App\Support\SrrvForms;
use App\Support\VisaWorksheets;
use Illuminate\Http\Request;
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

// Entry point for the ticketing system: signed-in staff go straight to the New Ticket Booking page;
// everyone else signs in first and is sent back to it afterwards.
Route::get('/ticketing/login', function (Request $request) {
    if (auth()->check()) {
        return redirect()->route('ticketing.tickets.create');
    }

    $request->session()->put('url.intended', route('ticketing.tickets.create'));

    return redirect()->route('admin.login');
})->name('ticketing.login');

// Authentication Routes. Clients have no account to sign in to: staff register them
// at the desks. /login stays as the address Laravel sends signed-out visitors to, and
// hands them to the staff sign-in.
Route::get('/login', fn () => redirect()->route('admin.login'))->name('login');

Route::middleware(['guest'])->group(function () {
    Route::get('/agent/login', [AuthController::class, 'showAgentLogin'])->name('agent.login');
    Route::get('/admin/login', [AuthController::class, 'showAdminLogin'])->name('admin.login');

    // The login forms previously accepted unlimited attempts.
    Route::middleware('throttle:login')->group(function () {
        Route::post('/agent/login', [AuthController::class, 'agentLogin'])->name('agent.login.submit');
        Route::post('/admin/login', [AuthController::class, 'adminLogin']);
    });
});
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Private identity documents. These live on the private disk, so they are only
// reachable here — the controller decides who is entitled to see them.
Route::middleware(['auth'])->group(function () {
    Route::get('/users/{user}/profile-photo', [UserDocumentController::class, 'profilePhoto'])->name('users.profile-photo');
    Route::get('/users/{user}/government-id', [UserDocumentController::class, 'governmentId'])->name('users.government-id');
    Route::get('/users/{user}/passport', [UserDocumentController::class, 'passport'])->name('users.passport');
    Route::get('/users/{user}/stamps', [UserDocumentController::class, 'stamps'])->name('users.stamps');
    Route::get('/users/{user}/arrival-stamp', [UserDocumentController::class, 'arrivalStamp'])->name('users.arrival-stamp');
});

// Ticketing Portal Routes (Protected by Auth & Ticketing Middleware)
Route::middleware(['auth', 'ticketing'])->prefix('ticketing')->name('ticketing.')->group(function () {
    Route::get('/', [TicketingDashboardController::class, 'index']);
    Route::get('/dashboard', [TicketingDashboardController::class, 'index'])->name('dashboard');
    // Find or register the client first; the booking is then filled from their profile.
    Route::get('/clients/search', [TicketClientController::class, 'search'])->name('clients.search');
    Route::get('/clients/create', [TicketClientController::class, 'create'])->name('clients.create');
    Route::get('/clients/check-duplicate', ClientDuplicateCheckController::class)->middleware('throttle:60,1')->name('clients.check-duplicate');
    Route::post('/clients', [TicketClientController::class, 'store'])->name('clients.store');
    // Re-upload from the wizard: replaces the passport or ID scan on the client's profile, which bookings then copy.
    Route::post('/clients/{client}/passport', [TicketClientController::class, 'updatePassport'])->middleware('throttle:30,1')->name('clients.passport');
    Route::post('/clients/{client}/government-id', [TicketClientController::class, 'updateGovernmentId'])->middleware('throttle:30,1')->name('clients.government-id');

    // Tickets saved as pending in the wizard, to continue later (before the tickets resource so 'pending' is not read as a ticket id).
    Route::post('/tickets/pending', [TicketDraftController::class, 'store'])->name('tickets.pending.store');
    Route::delete('/tickets/pending/{draft}', [TicketDraftController::class, 'destroy'])->name('tickets.pending.destroy');
    Route::resource('tickets', TicketBookingController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update']);
    Route::get('/documents/{document}/download', [TicketBookingController::class, 'downloadDocument'])->name('documents.download');

    // Payment must be settled in full before a ticket can be issued; issuance
    // stays a separate, consent-gated action rather than a payment side effect.
    Route::post('/tickets/{ticket}/payment', [TicketBookingController::class, 'updatePayment'])->name('tickets.payment');
    Route::get('/tickets/{ticket}/payments/{payment}/receipt', [TicketBookingController::class, 'receipt'])->name('tickets.payments.receipt');
    // A cancelled booking keeps its record; money already received goes back through a refund entry.
    Route::post('/tickets/{ticket}/cancel', [TicketBookingController::class, 'cancel'])->name('tickets.cancel');
    Route::post('/tickets/{ticket}/refund', [TicketBookingController::class, 'refund'])->name('tickets.refund');
    Route::post('/tickets/{ticket}/issue', [TicketBookingController::class, 'issue'])->name('tickets.issue');
    // A delay, new date or cancellation by the airline: updates the flight, keeps the history and tells the client.
    Route::post('/tickets/{ticket}/flight-changes', [TicketFlightChangeController::class, 'store'])->name('tickets.flight-changes.store');
    Route::post('/tickets/{ticket}/flight-changes/{flightChange}/notify', [TicketFlightChangeController::class, 'notify'])
        ->middleware('throttle:30,1')->scopeBindings()->name('tickets.flight-changes.notify');
    // Documents not at hand in the wizard are attached here, any time before the ticket is issued.
    Route::post('/tickets/{ticket}/passengers/{passenger}/documents', [TicketBookingController::class, 'storeDocument'])
        ->middleware('throttle:60,1')->scopeBindings()->name('tickets.passengers.documents.store');
    Route::put('/tickets/{ticket}/restrictions', [TicketBookingController::class, 'updateRestrictions'])->name('tickets.restrictions');
    Route::get('/tickets/{ticket}/voucher', [TicketBookingController::class, 'voucher'])->name('tickets.voucher');
    Route::get('/tickets/{ticket}/consent', [DataPrivacyConsentController::class, 'ticket'])->name('tickets.consent');

    // Emails staff send the client on demand.
    Route::post('/tickets/{ticket}/reminder', [TicketMessageController::class, 'reminder'])->name('tickets.reminder');
    Route::post('/tickets/{ticket}/documents', [TicketMessageController::class, 'documents'])->name('tickets.documents');

    // Airlines offered in the wizard's fare search. Switched off, never deleted.
    Route::resource('airlines', TicketAirlineController::class)->only(['index', 'store', 'update']);
    // The travel insurance plans offered under Contact & Extras: admins only (the same plans as Admin > Travel Insurance).
    Route::resource('insurance-plans', TicketInsurancePlanController::class)->only(['index', 'store', 'update'])->middleware('admin.only');
    // The ready-made side of the admin package configurator, in the ticketing portal: the same catalog.
    Route::get('/packages', [TicketPackageConfiguratorController::class, 'catalog'])->name('packages.index');
    Route::get('/packages/configurator', [TicketPackageConfiguratorController::class, 'index'])->name('packages.configurator');
    Route::get('/packages/{package}/edit', [TicketPackageConfiguratorController::class, 'edit'])->name('packages.edit');
    Route::put('/packages/{package}', [TicketPackageConfiguratorController::class, 'update'])->name('packages.update');
    Route::post('/packages/configurator/ready-made', [TicketPackageConfiguratorController::class, 'storeReadyMade'])->name('packages.configurator.ready-made');

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

    // Add a new client: choose the country or visa first, since each has its own form.
    Route::get('/visit-visa/new', [VisaApplicationController::class, 'start'])->name('visit.new');

    // Counter files
    Route::resource('applications', VisaApplicationController::class);
    Route::post('/applications/{application}/advance', [VisaApplicationController::class, 'advance'])->name('applications.advance');
    Route::post('/applications/{application}/cancel', [VisaApplicationController::class, 'cancel'])->name('applications.cancel');
    // Each stage's work is recorded before the file may advance.
    Route::post('/applications/{application}/stage', [VisaApplicationController::class, 'recordStage'])->name('applications.stage');
    // Email the client that a finished stage is done.
    Route::post('/applications/{application}/stages/{stage}/notify', [VisaApplicationController::class, 'notifyStage'])
        ->whereIn('stage', array_keys(VisaApplication::STAGE_LABELS))
        ->name('applications.stages.notify');
    // Non-Immigrant: the country playbook's tasks, requests for more documents, and the optional add-ons.
    Route::post('/applications/{application}/tasks/{task}', [VisaApplicationController::class, 'toggleTask'])
        ->where('task', '[a-z0-9_]+')->name('applications.tasks');
    Route::post('/applications/{application}/checklist', [VisaApplicationController::class, 'toggleChecklist'])->name('applications.checklist');
    Route::post('/applications/{application}/checklist/upload', [VisaApplicationController::class, 'uploadChecklistDocument'])
        ->middleware('throttle:60,1')->name('applications.checklist.upload');
    Route::get('/applications/{application}/checklist/print', [VisaApplicationController::class, 'printChecklist'])->name('applications.checklist.print');
    Route::get('/checklists/{key}', [VisaApplicationController::class, 'blankChecklist'])->where('key', '[a-z0-9_]+')->name('checklists.blank');
    Route::post('/applications/{application}/forms', [VisaApplicationController::class, 'storeDraftedForms'])->middleware('throttle:30,1')->name('applications.forms.store');
    Route::post('/applications/{application}/requests', [VisaApplicationController::class, 'storeRequest'])->name('applications.requests.store');
    Route::post('/applications/{application}/requests/{index}/received', [VisaApplicationController::class, 'receiveRequest'])
        ->whereNumber('index')->name('applications.requests.received');
    Route::post('/applications/{application}/addons', [VisaApplicationController::class, 'recordAddons'])->name('applications.addons');
    Route::post('/applications/{application}/payments', [VisaApplicationController::class, 'recordPayment'])->name('applications.payments');
    Route::get('/applications/{application}/acknowledgement', [VisaApplicationController::class, 'acknowledgement'])->name('applications.acknowledgement');
    Route::get('/applications/{application}/agreement', [VisaApplicationController::class, 'agreement'])->name('applications.agreement');
    Route::get('/applications/{application}/consent', [DataPrivacyConsentController::class, 'visa'])->name('applications.consent');

    // Tourist visa extensions: the BI's CGAF and documentary checklist, filled and printed here.
    Route::get('/tourist-visa/clients', [TouristVisaApplicationController::class, 'clients'])->name('tourist-visa.clients');
    Route::get('/tourist-visa/client/{passport}', [TouristVisaApplicationController::class, 'client'])->name('tourist-visa.client');
    Route::delete('/tourist-visa/client/{passport}', [TouristVisaApplicationController::class, 'destroyFolder'])->name('tourist-visa.client.destroy');
    Route::get('/tourist-visa/blank-form', [TouristVisaApplicationController::class, 'blankForm'])->name('tourist-visa.blank-form');
    Route::get('/tourist-visa/blank-checklist', [TouristVisaApplicationController::class, 'blankChecklist'])->name('tourist-visa.blank-checklist');
    Route::get('/tourist-visa/{touristVisa}/photo', [TouristVisaApplicationController::class, 'photo'])->name('tourist-visa.photo');
    Route::get('/tourist-visa/{touristVisa}/documents/{item}', [TouristVisaApplicationController::class, 'document'])
        ->whereIn('item', array_keys(TouristVisaApplication::CHECKLIST + TouristVisaApplication::OVERSTAY_CHECKLIST))
        ->name('tourist-visa.document');
    Route::delete('/tourist-visa/{touristVisa}/payments/{payment}', [TouristVisaApplicationController::class, 'destroyPayment'])->name('tourist-visa.payments.destroy');
    Route::get('/tourist-visa/{touristVisa}/form', [TouristVisaApplicationController::class, 'printForm'])->name('tourist-visa.print-form');
    Route::get('/tourist-visa/{touristVisa}/checklist', [TouristVisaApplicationController::class, 'printChecklist'])->name('tourist-visa.print-checklist');
    Route::resource('tourist-visa', TouristVisaApplicationController::class)
        ->parameters(['tourist-visa' => 'touristVisa'])
        ->except(['show']);

    // Applicants on a file
    Route::post('/applications/{application}/applicants', [VisaApplicationController::class, 'storeApplicant'])->name('applicants.store');
    Route::post('/applications/{application}/applicants/client', [VisaApplicationController::class, 'storeClientApplicant'])->name('applicants.store-client');
    Route::delete('/applications/{application}/applicants/{applicant}', [VisaApplicationController::class, 'destroyApplicant'])->name('applicants.destroy');

    // Each applicant's country worksheet (AMEGA's intake form for the embassy application)
    Route::get('/applications/{application}/applicants/{applicant}/worksheet', [VisaWorksheetController::class, 'edit'])->name('worksheets.edit');
    Route::put('/applications/{application}/applicants/{applicant}/worksheet', [VisaWorksheetController::class, 'update'])->name('worksheets.update');
    Route::get('/applications/{application}/applicants/{applicant}/worksheet/print', [VisaWorksheetController::class, 'print'])->name('worksheets.print');
    Route::get('/worksheets/blank/{worksheet}', [VisaWorksheetController::class, 'blank'])
        ->whereIn('worksheet', VisaWorksheets::COUNTRIES)->name('worksheets.blank');

    // Document checklist
    Route::post('/applications/{application}/documents', [VisaApplicationController::class, 'storeDocument'])->name('documents.store');
    Route::get('/documents/{document}/download', [VisaApplicationController::class, 'downloadDocument'])->name('documents.download');
    Route::delete('/documents/{document}', [VisaApplicationController::class, 'destroyDocument'])->name('documents.destroy');
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

    // The PRA forms, filled from the file, or blank for the counter
    Route::get('/applications/{application}/forms/{form}', [SrrvFormController::class, 'application'])
        ->whereIn('form', SrrvForms::FORMS)->name('applications.forms');
    Route::get('/renewals/{renewal}/request-form', [SrrvFormController::class, 'renewal'])->name('renewals.request-form');
    Route::get('/forms/blank/{form}', [SrrvFormController::class, 'blank'])->whereIn('form', SrrvForms::FORMS)->name('forms.blank');

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
    Route::get('/', [AdminDashboardController::class, 'index'])->middleware('page.access:dashboard');
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->middleware('page.access:dashboard')->name('dashboard');

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
        Route::get('/immigration/cases', [ImmigrationCaseController::class, 'index'])->name('immigration.cases');
        Route::post('/immigration/cases/{kind}/{id}/status', [ImmigrationCaseController::class, 'updateStatus'])->where(['kind' => '[a-z]+', 'id' => '[0-9]+'])->name('immigration.cases.status');
        Route::post('/immigration/cases/{kind}/{id}/release', [ImmigrationCaseController::class, 'release'])->where(['kind' => '[a-z]+', 'id' => '[0-9]+'])->name('immigration.cases.release');
        Route::post('/immigration/cases/{kind}/{id}/reopen', [ImmigrationCaseController::class, 'reopen'])->where(['kind' => '[a-z]+', 'id' => '[0-9]+'])->name('immigration.cases.reopen');
        Route::get('/client-sheets/blank', [AdminClientSheetController::class, 'blank'])->name('client-sheets.blank');
        // A service picked from the menu starts here: name the folder by finding the client, or adding a new one.
        Route::get('/client-sheets/start', [AdminClientSheetController::class, 'start'])->name('client-sheets.start');
        Route::post('/client-sheets/start', [AdminClientSheetController::class, 'startNewClient'])->name('client-sheets.start.store');
        Route::get('/client-sheets/check-duplicate', ClientDuplicateCheckController::class)->middleware('throttle:60,1')->name('client-sheets.check-duplicate');
        // Type-ahead for the counter search box: sheets plus registered clients without one.
        Route::get('/client-sheets/lookup', [AdminClientSheetController::class, 'lookup'])->name('client-sheets.lookup');
        Route::get('/client-sheets/{clientSheet}/print', [AdminClientSheetController::class, 'print'])->name('client-sheets.print');
        // The BI's extension of stay forms (CGAF and checklist), filled from the sheet or blank.
        Route::get('/client-sheets/bi-blank/{form}', [AdminClientSheetController::class, 'printBlankBiForm'])->whereIn('form', ['cgaf', 'checklist'])->name('client-sheets.bi-blank');
        Route::get('/client-sheets/{clientSheet}/bi/{form}', [AdminClientSheetController::class, 'printBiForm'])->whereIn('form', ['cgaf', 'checklist'])->name('client-sheets.bi-form');
        // Visa Overstaying: the BI's request for reconsideration, and the files for its requirements
        Route::get('/client-sheets/overstay-blank', [AdminClientSheetController::class, 'printBlankReconsideration'])->name('client-sheets.reconsideration-blank');
        Route::get('/client-sheets/{clientSheet}/reconsideration', [AdminClientSheetController::class, 'printReconsideration'])->name('client-sheets.reconsideration');
        Route::get('/client-sheets/{clientSheet}/overstays/{overstay}/files/{item}', [AdminClientSheetController::class, 'overstayFile'])
            ->whereIn('item', array_keys(ImmigrationOverstay::CHECKLIST))->scopeBindings()->name('client-sheets.overstay-file');
        // Re Stamping / Amendment: the IRD application form, its two checklists, and requirement files
        Route::get('/client-sheets/restamping-blank/{form}', [AdminClientSheetController::class, 'printBlankRestamping'])
            ->whereIn('form', ['form', 'transfer', 'amendment'])->name('client-sheets.restamping-blank');
        Route::get('/client-sheets/{clientSheet}/restamping/form', [AdminClientSheetController::class, 'printRestampingForm'])->name('client-sheets.restamping-form');
        Route::get('/client-sheets/{clientSheet}/restamping/checklist/{kind}', [AdminClientSheetController::class, 'printRestampingChecklist'])
            ->whereIn('kind', ['transfer', 'amendment'])->name('client-sheets.restamping-checklist');
        Route::get('/client-sheets/{clientSheet}/restampings/{restamping}/files/{item}', [AdminClientSheetController::class, 'restampingFile'])
            ->whereIn('item', array_keys(ImmigrationRestamping::allItems()))->scopeBindings()->name('client-sheets.restamping-file');
        // 13(A): probationary, permanent and ACR I-Card renewal forms, checklists, and requirement files
        Route::get('/client-sheets/petition-blank/{type}/{form}', [AdminClientSheetController::class, 'printBlankPetition'])
            ->whereIn('type', ImmigrationPetition::SERVICES)->whereIn('form', ['form', 'checklist', 'acr'])->name('client-sheets.petition-blank');
        Route::get('/client-sheets/{clientSheet}/petition/{type}/form', [AdminClientSheetController::class, 'printPetitionForm'])
            ->whereIn('type', ImmigrationPetition::SERVICES)->name('client-sheets.petition-form');
        Route::get('/client-sheets/{clientSheet}/petition/{type}/acr', [AdminClientSheetController::class, 'printPetitionAcr'])
            ->whereIn('type', ['spouse', 'permanent'])->name('client-sheets.petition-acr');
        Route::get('/client-sheets/{clientSheet}/petition/{type}/checklist', [AdminClientSheetController::class, 'printPetitionChecklist'])
            ->whereIn('type', ImmigrationPetition::SERVICES)->name('client-sheets.petition-checklist');
        Route::get('/client-sheets/{clientSheet}/petitions/{petition}/files/{item}', [AdminClientSheetController::class, 'petitionFile'])
            ->whereIn('item', array_keys(ImmigrationPetition::allItems()))->scopeBindings()->name('client-sheets.petition-file');
        Route::get('/client-sheets/{clientSheet}/documents/{type}', [AdminClientSheetController::class, 'documentFile'])
            ->whereIn('type', array_keys(ImmigrationClientDocument::TYPES))->name('client-sheets.documents.file');
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
        // Before the resource, so 'check-duplicate' is not read as a user id.
        Route::get('/users/check-duplicate', ClientDuplicateCheckController::class)->middleware('throttle:60,1')->name('users.check-duplicate');
        Route::resource('users', AdminUserController::class);

        // Companies and the clients under them.
        Route::post('/corporates/{corporate}/members', [AdminCorporateAccountController::class, 'assignMember'])->name('corporates.members.store');
        Route::delete('/corporates/{corporate}/members/{user}', [AdminCorporateAccountController::class, 'removeMember'])->name('corporates.members.destroy');
        Route::resource('corporates', AdminCorporateAccountController::class);
    });

    // Ticket bookings submitted by agents, checked before the cashier takes payment.
    Route::middleware('admin.only')->prefix('ticket-approvals')->name('ticket-approvals.')->group(function () {
        Route::get('/', [AdminTicketApprovalController::class, 'index'])->name('index');
        Route::get('/{ticket}', [AdminTicketApprovalController::class, 'show'])->name('show');
        Route::post('/{ticket}/approve', [AdminTicketApprovalController::class, 'approve'])->name('approve');
        Route::post('/{ticket}/reject', [AdminTicketApprovalController::class, 'reject'])->name('reject');
    });

    // What agents save at the immigration counter, checked by an admin before it is printed or filed.
    Route::middleware('admin.only')->prefix('immigration-approvals')->name('immigration-approvals.')->group(function () {
        Route::get('/', [ImmigrationApprovalController::class, 'index'])->name('index');
        Route::post('/{kind}/{id}/approve', [ImmigrationApprovalController::class, 'approve'])->where(['kind' => '[a-z]+', 'id' => '[0-9]+'])->name('approve');
        Route::post('/{kind}/{id}/reject', [ImmigrationApprovalController::class, 'reject'])->where(['kind' => '[a-z]+', 'id' => '[0-9]+'])->name('reject');
    });

    Route::middleware('admin.only')->group(function () {
        Route::resource('agents', AdminAgentController::class);
        // The travel insurance plans offered in the ticket wizard. Switched off, never deleted.
        Route::resource('insurance-plans', AdminInsurancePlanController::class)->only(['index', 'store', 'update']);
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

// Cashier portal: payment on every ticket booking an admin approved.
Route::middleware(['auth', 'cashier'])->prefix('cashier')->name('cashier.')->group(function () {
    Route::get('/', [CashierController::class, 'index'])->name('dashboard');
    Route::get('/paid', [CashierController::class, 'paid'])->name('paid');
    Route::get('/payments/{ticket}', [CashierController::class, 'show'])->name('payments.show');
    Route::post('/payments/{ticket}', [CashierController::class, 'store'])->middleware('throttle:30,1')->name('payments.store');
    Route::get('/payments/{ticket}/receipts/{payment}', [CashierController::class, 'receipt'])->name('payments.receipt');
});
