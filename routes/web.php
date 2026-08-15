<?php

use App\Http\Controllers\Admin\AdminActivityLogController;
use App\Http\Controllers\Admin\AdminAgentController;
use App\Http\Controllers\Admin\AdminBookingController;
use App\Http\Controllers\Admin\AdminChatController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminDestinationController;
use App\Http\Controllers\Admin\AdminInquiryController;
use App\Http\Controllers\Admin\AdminPackageController;
use App\Http\Controllers\Admin\AdminServiceController;
use App\Http\Controllers\Admin\AdminTestimonialController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\ClientDashboardController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\GuestChatController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\TravelPackageController;
use Illuminate\Support\Facades\Route;

// Public Front-end Routes
Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/services', [PageController::class, 'services'])->name('services');
Route::get('/tours', [PageController::class, 'tours'])->name('tours');
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/contact', [PageController::class, 'contact'])->name('contact');
Route::post('/contact', [ContactController::class, 'submit'])->name('contact.submit');
Route::get('/gallery', [PageController::class, 'gallery'])->name('gallery');
Route::get('/why-us', [PageController::class, 'whyUs'])->name('why-us');
Route::get('/testimonials', [PageController::class, 'testimonials'])->name('testimonials');

// Public Live Chat Routes
Route::post('/chat/init', [GuestChatController::class, 'init'])->name('guest-chat.init');
Route::post('/chat/send', [GuestChatController::class, 'send'])->name('guest-chat.send');
Route::get('/chat/poll', [GuestChatController::class, 'poll'])->name('guest-chat.poll');
Route::post('/chat/info', [GuestChatController::class, 'updateInfo'])->name('guest-chat.info');
Route::post('/chat/request-agent', [GuestChatController::class, 'requestAgent'])->name('guest-chat.request-agent');

// Public Package Directory & Details
Route::get('/packages', [TravelPackageController::class, 'index'])->name('packages.index');
Route::get('/packages/{package}', [TravelPackageController::class, 'show'])->name('packages.show');

// Public Booking System Routes
Route::post('/bookings', [BookingController::class, 'store'])->name('bookings.store');
Route::get('/bookings/{reference}', [BookingController::class, 'confirmation'])->name('bookings.confirmation');

// Authentication Routes
Route::middleware(['guest'])->group(function () {
    Route::get('/login', [AuthController::class, 'showClientLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/agent/login', [AuthController::class, 'showAgentLogin'])->name('agent.login');
    Route::post('/agent/login', [AuthController::class, 'agentLogin'])->name('agent.login.submit');
    Route::get('/admin/login', [AuthController::class, 'showAdminLogin'])->name('admin.login');
    Route::post('/admin/login', [AuthController::class, 'adminLogin']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Client Portal Routes (Protected by Auth)
Route::middleware(['auth'])->group(function () {
    Route::get('/client/dashboard', [ClientDashboardController::class, 'index'])->name('client.dashboard');
    Route::get('/client/bookings', [ClientDashboardController::class, 'index'])->name('client.bookings');
    Route::get('/client/profile', [ClientDashboardController::class, 'showProfile'])->name('client.profile');
    Route::put('/client/profile', [ClientDashboardController::class, 'updateProfile'])->name('client.profile.update');
});

// Admin Management Routes (Protected by Auth & Admin Middleware)
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index']);
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

    // Live Guest Chat Management
    Route::get('/chats', [AdminChatController::class, 'index'])->name('chats.index');
    Route::get('/chats/{conversation}', [AdminChatController::class, 'show'])->name('chats.show');
    Route::post('/chats/{conversation}/accept', [AdminChatController::class, 'accept'])->name('chats.accept');
    Route::post('/chats/{conversation}/reply', [AdminChatController::class, 'reply'])->name('chats.reply');
    Route::post('/chats/{conversation}/status', [AdminChatController::class, 'updateStatus'])->name('chats.status');
    Route::post('/chats/{conversation}/read', [AdminChatController::class, 'markRead'])->name('chats.read');
    Route::delete('/chats/{conversation}', [AdminChatController::class, 'destroy'])->name('chats.destroy');

    Route::post('/packages/{package}/toggle-featured', [AdminPackageController::class, 'toggleFeatured'])->name('packages.toggle-featured');
    Route::resource('packages', AdminPackageController::class);
    Route::post('/destinations/{destination}/toggle-featured', [AdminDestinationController::class, 'toggleFeatured'])->name('destinations.toggle-featured');
    Route::resource('destinations', AdminDestinationController::class);
    Route::post('/bookings/{booking}/status', [AdminBookingController::class, 'updateStatus'])->name('bookings.update-status');
    Route::resource('bookings', AdminBookingController::class)->only(['index', 'destroy']);
    Route::resource('inquiries', AdminInquiryController::class)->only(['index', 'destroy']);
    Route::post('/services/{service}/toggle-status', [AdminServiceController::class, 'toggleStatus'])->name('services.toggle-status');
    Route::resource('services', AdminServiceController::class);
    Route::resource('testimonials', AdminTestimonialController::class)->only(['index', 'store', 'destroy']);
    Route::resource('users', AdminUserController::class);
    Route::resource('agents', AdminAgentController::class);
    Route::get('/activity-logs', [AdminActivityLogController::class, 'index'])->name('activity-logs.index');
    Route::get('/activity-logs/stream', [AdminActivityLogController::class, 'stream'])->name('activity-logs.stream');
});
