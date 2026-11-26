<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AlertController;
use App\Http\Controllers\AssistantController;
use App\Http\Controllers\CitizenEvidenceController;
use App\Http\Controllers\CommunicationCenterController;
use App\Http\Controllers\CommunityChatController;
use App\Http\Controllers\DistrictRiskController;
use App\Http\Controllers\DistrictZoneController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\LostFoundItemController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\MapLiveController;
use App\Http\Controllers\NeedHelpRequestController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PrivateChatController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ResguardaModeController;
use App\Http\Controllers\SafeZoneController;
use App\Http\Controllers\SosAlertController;
use App\Http\Controllers\SosDeliveryReviewController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Laravel\Fortify\Features;

// Pantalla de bienvenida para visitantes; quien ya inició sesión va directo al mapa.
Route::get('/', fn (Request $request): RedirectResponse|InertiaResponse => $request->user()
    ? redirect()->route('map')
    : Inertia::render('Welcome', ['canRegister' => Features::enabled(Features::registration())]))
    ->name('home');

Route::middleware(['auth'])->group(function () {
    // El mapa es la pantalla principal; se conserva /dashboard para enlaces antiguos.
    Route::redirect('dashboard', '/map')->name('dashboard');
    Route::get('map', [MapController::class, 'index'])->name('map');
    Route::get('map/open-spaces', [MapLiveController::class, 'openSpaces'])->middleware('throttle:30,1')->name('map.open-spaces');
    Route::get('map/crowd-streets', [MapLiveController::class, 'crowdStreets'])->middleware('throttle:30,1')->name('map.crowd-streets');
    Route::get('map/traffic/{zoom}/{x}/{y}.png', [MapLiveController::class, 'traffic'])->whereNumber(['zoom', 'x', 'y'])->middleware('throttle:900,1')->name('map.traffic');
    Route::get('location/lookup', [LocationController::class, 'lookup'])
        ->middleware('throttle:60,1')
        ->name('location.lookup');
    Route::get('safe-zones', [SafeZoneController::class, 'index'])->name('safe-zones.index');
    Route::post('safe-zones', [SafeZoneController::class, 'store'])->middleware('throttle:10,1')->name('safe-zones.store');
    Route::get('safe-zones/{safeZone}', [SafeZoneController::class, 'show'])->name('safe-zones.show');
    Route::post('safe-zones/{safeZone}/vote', [SafeZoneController::class, 'vote'])->middleware('throttle:30,1')->name('safe-zones.vote');

    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::post('reports', [ReportController::class, 'store'])->name('reports.store');
    Route::get('reports/{report}', [ReportController::class, 'show'])->name('reports.show');
    Route::post('reports/{report}/vote', [ReportController::class, 'vote'])->name('reports.vote');

    Route::get('evidence', [CitizenEvidenceController::class, 'index'])->name('citizen-evidence.index');
    Route::post('evidence', [CitizenEvidenceController::class, 'store'])->name('citizen-evidence.store');
    Route::get('evidence/{evidence}/photo', [CitizenEvidenceController::class, 'photo'])->name('citizen-evidence.photo');
    Route::post('evidence/{evidence}/vote', [CitizenEvidenceController::class, 'vote'])->name('citizen-evidence.vote');
    Route::post('evidence/{evidence}/report', [CitizenEvidenceController::class, 'report'])->name('citizen-evidence.report');
    Route::patch('evidence/{evidence}/status', [CitizenEvidenceController::class, 'updateStatus'])->name('citizen-evidence.status');

    Route::get('lost-found', [LostFoundItemController::class, 'index'])->name('lost-found.index');
    Route::post('lost-found', [LostFoundItemController::class, 'store'])->name('lost-found.store');
    Route::get('lost-found/{lostFoundItem}', [LostFoundItemController::class, 'show'])->name('lost-found.show');
    Route::get('lost-found/{lostFoundItem}/photo', [LostFoundItemController::class, 'photo'])->name('lost-found.photo');
    Route::post('lost-found/{lostFoundItem}/contact', [LostFoundItemController::class, 'contact'])
        ->middleware('throttle:20,1')
        ->name('lost-found.contact');
    Route::put('lost-found/{lostFoundItem}', [LostFoundItemController::class, 'update'])->name('lost-found.update');
    Route::delete('lost-found/{lostFoundItem}', [LostFoundItemController::class, 'destroy'])->name('lost-found.destroy');

    Route::get('risk', [DistrictRiskController::class, 'index'])->name('district-risk.index');
    Route::get('risk/{district}', [DistrictRiskController::class, 'show'])->name('district-risk.show');
    Route::get('district-zones', [DistrictZoneController::class, 'index'])->name('district-zones.index');
    Route::post('district-zones', [DistrictZoneController::class, 'store'])->name('district-zones.store');
    Route::patch('district-zones/{districtZone}', [DistrictZoneController::class, 'update'])->name('district-zones.update');
    Route::delete('district-zones/{districtZone}', [DistrictZoneController::class, 'destroy'])->name('district-zones.destroy');

    Route::get('help', [NeedHelpRequestController::class, 'index'])->name('need-help.index');
    Route::post('help', [NeedHelpRequestController::class, 'store'])->name('need-help.store');
    Route::post('help/sos', [SosAlertController::class, 'store'])->middleware('throttle:sos')->name('need-help.sos');
    Route::get('help/{needHelpRequest}', [NeedHelpRequestController::class, 'show'])->name('need-help.show');
    Route::patch('help/{needHelpRequest}/resolve', [NeedHelpRequestController::class, 'resolve'])->name('need-help.resolve');

    Route::get('alerts', [AlertController::class, 'index'])->name('alerts.index');
    Route::post('alerts', [AlertController::class, 'store'])->name('alerts.store');

    Route::get('communication', [CommunicationCenterController::class, 'index'])->name('communication.index');
    Route::get('communication/conversations/{conversation}', [CommunicationCenterController::class, 'conversation'])->name('communication.conversations.show');
    Route::post('communication/conversations/{conversation}', [PrivateChatController::class, 'reply'])->name('communication.conversations.reply');

    Route::get('chat', [CommunityChatController::class, 'index'])->name('chat.index');
    Route::post('chat', [CommunityChatController::class, 'store'])->middleware('throttle:30,1')->name('chat.store');
    Route::get('chat/messages/{communityMessage}/photo', [CommunityChatController::class, 'photo'])->name('chat.photo');
    Route::get('communication/messages/{privateMessage}/photo', [PrivateChatController::class, 'photo'])->name('communication.messages.photo');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/summary', [NotificationController::class, 'summary'])->name('notifications.summary');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::patch('notifications/preferences', [NotificationController::class, 'updatePreferences'])->name('notifications.preferences');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');

    Route::get('private-chat', [PrivateChatController::class, 'index'])->name('private-chat.index');
    Route::post('private-chat', [PrivateChatController::class, 'store'])->name('private-chat.store');
    Route::get('private-chat/{conversation}', [PrivateChatController::class, 'show'])->name('private-chat.show');

    Route::get('resguarda-mode', [ResguardaModeController::class, 'mode'])->name('resguarda-mode');
    Route::get('emergency', [ResguardaModeController::class, 'emergency'])->name('emergency');

    Route::get('assistant', [AssistantController::class, 'index'])->name('assistant.index');
    Route::post('assistant/chat', [AssistantController::class, 'reply'])
        ->middleware('throttle:assistant')
        ->name('assistant.chat');
    Route::post('assistant/stream', [AssistantController::class, 'stream'])
        ->middleware('throttle:assistant')
        ->name('assistant.stream');

    Route::get('admin', [AdminController::class, 'index'])->name('admin.index');
    Route::get('admin/users', [AdminController::class, 'users'])->name('admin.users');
    Route::get('admin/reports', [AdminController::class, 'reports'])->name('admin.reports');
    Route::patch('admin/reports/{report}/status', [AdminController::class, 'updateReportStatus'])->name('admin.reports.status.update');
    Route::get('admin/moderation', [AdminController::class, 'moderation'])->name('admin.moderation');
    Route::patch('admin/evidence/{evidence}/status', [AdminController::class, 'updateEvidenceStatus'])->name('admin.evidence.status.update');
    Route::patch('admin/help/{needHelpRequest}/status', [AdminController::class, 'updateHelpStatus'])->name('admin.help.status.update');
    Route::get('admin/alerts', [AdminController::class, 'alerts'])->name('admin.alerts');
    Route::get('admin/districts', [AdminController::class, 'districts'])->name('admin.districts');
    Route::get('admin/safe-zones', [AdminController::class, 'safeZones'])->name('admin.safe-zones');
    Route::get('admin/sos-deliveries', [SosDeliveryReviewController::class, 'index'])->name('admin.sos-deliveries.index');
    Route::patch('admin/sos-deliveries/{sosAlertDelivery}', [SosDeliveryReviewController::class, 'update'])->name('admin.sos-deliveries.resolve');
});

require __DIR__.'/settings.php';
