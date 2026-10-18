<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\MasterDataController;
use App\Http\Controllers\Admin\PersonnelController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\DapodikController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\FjpController;
use App\Http\Controllers\KitchenController;
use App\Http\Controllers\PermitController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuarterlyTargetController;
use App\Http\Controllers\QuickReportController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\Warehouse\InventoryController;
use App\Http\Controllers\Warehouse\MrvController;
use App\Http\Controllers\Warehouse\ProductController;
use App\Http\Controllers\Warehouse\StockReceiptController;
use App\Http\Controllers\Warehouse\StockReturnController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // ---------------------------------------------------------------------
    // Workspaces (UI shell)
    // ---------------------------------------------------------------------
    Route::get('/permitter', [WorkspaceController::class, 'permitter'])->middleware('permission:fjp.view')->name('workspace.permitter');
    Route::get('/tim', [WorkspaceController::class, 'tim'])->middleware('permission:events.view')->name('workspace.tim');
    Route::get('/gudang', [WorkspaceController::class, 'gudang'])->middleware('permission:warehouse.view')->name('workspace.gudang');
    Route::get('/dapur', [WorkspaceController::class, 'dapur'])->middleware('permission:kitchen.view')->name('workspace.dapur');
    Route::get('/pic', [WorkspaceController::class, 'pic'])->middleware('permission:events.view')->name('workspace.pic');
    Route::get('/admin', [WorkspaceController::class, 'admin'])->middleware('permission:reports.view')->name('workspace.admin');
    Route::get('/admin-event', [WorkspaceController::class, 'adminEvent'])->middleware('permission:reports.view')->name('workspace.admin-event');
    Route::get('quarterly-targets', [QuarterlyTargetController::class, 'index'])->middleware('permission:targets.manage')->name('quarterly-targets.index');
    Route::put('quarterly-targets/{brand}', [QuarterlyTargetController::class, 'updateForBrand'])->middleware('permission:targets.manage')->name('quarterly-targets.update');

    // ---------------------------------------------------------------------
    // Events (fixed routes before parameterised ones)
    // ---------------------------------------------------------------------
    Route::get('events', [EventController::class, 'index'])->middleware('permission:events.view')->name('events.index');
    Route::get('events/create', [EventController::class, 'create'])->middleware('permission:events.create')->name('events.create');
    Route::post('events', [EventController::class, 'store'])->middleware('permission:events.create')->name('events.store');
    Route::get('events/{event}', [EventController::class, 'show'])->middleware('permission:events.view')->name('events.show');
    Route::get('events/{event}/edit', [EventController::class, 'edit'])->middleware('permission:events.update')->name('events.edit');
    Route::put('events/{event}', [EventController::class, 'update'])->middleware('permission:events.update')->name('events.update');
    Route::patch('events/{event}/status', [EventController::class, 'updateStatus'])->middleware('permission:events.update')->name('events.status');
    Route::post('events/{event}/close', [EventController::class, 'close'])->middleware('permission:events.close')->name('events.close');
    Route::post('events/{event}/team', [EventController::class, 'assignTeam'])->middleware('permission:events.assign_team')->name('events.team.assign');
    Route::delete('events/{event}/team/{member}', [EventController::class, 'removeTeam'])->middleware('permission:events.assign_team')->name('events.team.remove');

    // ---------------------------------------------------------------------
    // FJP target quartal (separate route for workspace tab)
    // ---------------------------------------------------------------------
    Route::get('/fjp/target-quartal', [QuarterlyTargetController::class, 'fjpIndex'])->middleware('permission:fjp.view')->name('fjp.quarterly-targets.index');
    Route::put('/fjp/target-quartal', [QuarterlyTargetController::class, 'update'])->middleware('permission:fjp.update')->name('fjp.quarterly-targets.update');

    // ---------------------------------------------------------------------
    // FJP
    // ---------------------------------------------------------------------
    Route::get('fjp', [FjpController::class, 'index'])->middleware('permission:fjp.view')->name('fjp.index');
    Route::get('fjp/create', [FjpController::class, 'create'])->middleware('permission:fjp.create')->name('fjp.create');
    Route::post('fjp', [FjpController::class, 'store'])->middleware('permission:fjp.create')->name('fjp.store');
    Route::get('fjp/{fjp}', [FjpController::class, 'show'])->middleware('permission:fjp.view')->name('fjp.show');
    Route::get('fjp/{fjp}/edit', [FjpController::class, 'edit'])->middleware('permission:fjp.update')->name('fjp.edit');
    Route::put('fjp/{fjp}', [FjpController::class, 'update'])->middleware('permission:fjp.update')->name('fjp.update');
    Route::patch('fjp/{fjp}/inline', [FjpController::class, 'updateInline'])->middleware('permission:fjp.update')->name('fjp.inline');
    Route::delete('fjp/{fjp}', [FjpController::class, 'destroy'])->middleware('permission:fjp.delete')->name('fjp.destroy');
    Route::post('fjp/{fjp}/dapodik', [FjpController::class, 'syncDapodik'])->middleware('permission:fjp.update')->name('fjp.dapodik');
    Route::post('fjp/{fjp}/workflow', [FjpController::class, 'workflow'])->middleware('permission:fjp.update')->name('fjp.workflow');
    Route::post('fjp/{fjp}/create-event', [FjpController::class, 'createEvent'])->middleware('permission:events.create')->name('fjp.create-event');
    Route::post('fjp/{fjp}/attachments', [FjpController::class, 'uploadAttachment'])->middleware('permission:fjp.update')->name('fjp.attachments.store');

    // ---------------------------------------------------------------------
    // Dapodik school lookup (proxied to dapo.kemendikdasmen.go.id)
    // ---------------------------------------------------------------------
    Route::get('dapodik/search', [DapodikController::class, 'search'])->middleware('permission:fjp.view')->name('dapodik.search');
    Route::get('dapodik/{npsn}', [DapodikController::class, 'show'])->where('npsn', '[A-Za-z0-9]+')->middleware('permission:fjp.view')->name('dapodik.show');

    // ---------------------------------------------------------------------
    // Permits
    // ---------------------------------------------------------------------
    Route::get('permits', [PermitController::class, 'index'])->middleware('permission:permits.view')->name('permits.index');
    Route::get('permits/create', [PermitController::class, 'create'])->middleware('permission:permits.create')->name('permits.create');
    Route::post('permits', [PermitController::class, 'store'])->middleware('permission:permits.create')->name('permits.store');
    Route::get('permits/{permit}', [PermitController::class, 'show'])->middleware('permission:permits.view')->name('permits.show');
    Route::post('permits/{permit}/transition', [PermitController::class, 'transition'])->middleware('permission:permits.view')->name('permits.transition');
    Route::post('permits/{permit}/documents', [PermitController::class, 'uploadDocument'])->middleware('permission:permits.update')->name('permits.documents.store');

    // ---------------------------------------------------------------------
    // Warehouse
    // ---------------------------------------------------------------------
    Route::prefix('warehouse')->name('warehouse.')->middleware('permission:warehouse.view')->group(function () {
        Route::get('products', [ProductController::class, 'index'])->name('products.index');
        Route::get('products/create', [ProductController::class, 'create'])->middleware('permission:warehouse.manage')->name('products.create');
        Route::post('products', [ProductController::class, 'store'])->middleware('permission:warehouse.manage')->name('products.store');
        Route::get('products/{product}/edit', [ProductController::class, 'edit'])->middleware('permission:warehouse.manage')->name('products.edit');
        Route::put('products/{product}', [ProductController::class, 'update'])->middleware('permission:warehouse.manage')->name('products.update');
        Route::delete('products/{product}', [ProductController::class, 'destroy'])->middleware('permission:warehouse.manage')->name('products.destroy');

        Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
        Route::get('inventory/movements', [InventoryController::class, 'movements'])->name('inventory.movements');
        Route::post('inventory/adjust', [InventoryController::class, 'adjust'])->middleware('permission:warehouse.manage')->name('inventory.adjust');

        Route::get('receipts', [StockReceiptController::class, 'index'])->name('receipts.index');
        Route::get('receipts/create', [StockReceiptController::class, 'create'])->middleware('permission:warehouse.manage')->name('receipts.create');
        Route::post('receipts', [StockReceiptController::class, 'store'])->middleware('permission:warehouse.manage')->name('receipts.store');
        Route::get('receipts/{receipt}/photo', [StockReceiptController::class, 'downloadPhoto'])->name('receipts.photo');

        Route::get('mrv', [MrvController::class, 'index'])->name('mrv.index');
        Route::get('mrv/create', [MrvController::class, 'create'])->middleware('permission:warehouse.request')->name('mrv.create');
        Route::post('mrv', [MrvController::class, 'store'])->middleware('permission:warehouse.request')->name('mrv.store');
        Route::get('mrv/{mrv}', [MrvController::class, 'show'])->name('mrv.show');
        Route::post('mrv/{mrv}/approve', [MrvController::class, 'approve'])->middleware('permission:warehouse.approve')->name('mrv.approve');
        Route::post('mrv/{mrv}/reject', [MrvController::class, 'reject'])->middleware('permission:warehouse.approve')->name('mrv.reject');
        Route::post('mrv/{mrv}/issue', [MrvController::class, 'issue'])->middleware('permission:warehouse.issue')->name('mrv.issue');
        Route::get('mrv/{mrv}/photo', [MrvController::class, 'downloadPhoto'])->name('mrv.photo');

        Route::get('returns', [StockReturnController::class, 'index'])->name('returns.index');
        Route::get('returns/create', [StockReturnController::class, 'create'])->middleware('permission:warehouse.return')->name('returns.create');
        Route::post('returns', [StockReturnController::class, 'store'])->middleware('permission:warehouse.return')->name('returns.store');
        Route::get('returns/{stockReturn}/photo', [StockReturnController::class, 'downloadPhoto'])->name('returns.photo');
    });

    // ---------------------------------------------------------------------
    // Kitchen
    // ---------------------------------------------------------------------
    Route::get('kitchen', [KitchenController::class, 'index'])->middleware('permission:kitchen.view')->name('kitchen.index');
    Route::get('kitchen/create', [KitchenController::class, 'create'])->middleware('permission:kitchen.manage')->name('kitchen.create');
    Route::post('kitchen', [KitchenController::class, 'store'])->middleware('permission:kitchen.manage')->name('kitchen.store');
    Route::get('kitchen/{kitchen}', [KitchenController::class, 'show'])->middleware('permission:kitchen.view')->name('kitchen.show');
    Route::patch('kitchen/{kitchen}/status', [KitchenController::class, 'updateStatus'])->middleware('permission:kitchen.manage')->name('kitchen.status');
    Route::post('kitchen/{kitchen}/reports', [KitchenController::class, 'addReport'])->middleware('permission:kitchen.manage')->name('kitchen.reports.store');
    Route::get('kitchen-reports/{report}/photo', [KitchenController::class, 'downloadPhoto'])->name('kitchen.reports.photo');
    Route::get('kitchen-reports/{report}/washing-photo', [KitchenController::class, 'downloadWashingPhoto'])->name('kitchen.reports.washing-photo');

    // ---------------------------------------------------------------------
    // Reports
    // ---------------------------------------------------------------------
    Route::get('reports', [ReportController::class, 'index'])->middleware('permission:reports.view')->name('reports.index');
    Route::get('reports/by-event', [ReportController::class, 'byEvent'])->middleware('permission:reports.view')->name('reports.by-event');
    Route::get('reports/create', [ReportController::class, 'create'])->middleware('permission:reports.create')->name('reports.create');
    Route::post('reports', [ReportController::class, 'store'])->middleware('permission:reports.create')->name('reports.store');
    Route::get('reports/{report}', [ReportController::class, 'show'])->middleware('permission:reports.view')->name('reports.show');
    Route::put('reports/{report}', [ReportController::class, 'update'])->middleware('permission:reports.create')->name('reports.update');
    Route::post('reports/{report}/submit', [ReportController::class, 'submit'])->middleware('permission:reports.create')->name('reports.submit');
    Route::post('reports/{report}/verify', [ReportController::class, 'verify'])->middleware('permission:reports.verify')->name('reports.verify');
    Route::post('reports/{report}/revision', [ReportController::class, 'revision'])->middleware('permission:reports.revision')->name('reports.revision');

    // ---------------------------------------------------------------------
    // Quick reports
    // ---------------------------------------------------------------------
    Route::get('quick-reports', [QuickReportController::class, 'index'])->middleware('permission:quick_reports.view')->name('quick-reports.index');
    Route::get('quick-reports/create', [QuickReportController::class, 'create'])->middleware('permission:quick_reports.create')->name('quick-reports.create');
    Route::post('quick-reports', [QuickReportController::class, 'store'])->middleware('permission:quick_reports.create')->name('quick-reports.store');
    Route::get('quick-reports/{report}/photo', [QuickReportController::class, 'downloadPhoto'])->name('quick-reports.photo');

    // ---------------------------------------------------------------------
    // Attendance
    // ---------------------------------------------------------------------
    Route::get('attendance', [AttendanceController::class, 'index'])->middleware('permission:attendance.view')->name('attendance.index');
    Route::get('attendance/payroll', [AttendanceController::class, 'payroll'])->middleware('permission:attendance.payroll')->name('attendance.payroll');
    Route::post('attendance', [AttendanceController::class, 'store'])->middleware('permission:attendance.create')->name('attendance.store');
    Route::post('attendance/{attendance}/check-in', [AttendanceController::class, 'checkIn'])->middleware('permission:attendance.create')->name('attendance.check-in');
    Route::post('attendance/{attendance}/check-out', [AttendanceController::class, 'checkOut'])->middleware('permission:attendance.create')->name('attendance.check-out');
    Route::post('attendance/{attendance}/invalidate', [AttendanceController::class, 'invalidate'])->middleware('permission:attendance.manage')->name('attendance.invalidate');
    Route::patch('attendance/{attendance}/correct', [AttendanceController::class, 'correct'])->middleware('permission:attendance.manage')->name('attendance.correct');

    // ---------------------------------------------------------------------
    // Team
    // ---------------------------------------------------------------------
    Route::get('team', [TeamController::class, 'index'])->middleware('permission:events.view')->name('team.index');

    // ---------------------------------------------------------------------
    // Documents
    // ---------------------------------------------------------------------
    Route::post('documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::get('documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::delete('documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');
    Route::get('fjp-attachments/{attachment}/download', [DocumentController::class, 'downloadFjp'])->name('fjp.attachments.download');
    Route::get('permit-documents/{document}/download', [DocumentController::class, 'downloadPermit'])->name('permits.documents.download');

    // ---------------------------------------------------------------------
    // Admin
    // ---------------------------------------------------------------------
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('users', [UserController::class, 'index'])->middleware('permission:admin.users.manage')->name('users.index');
        Route::get('users/create', [UserController::class, 'create'])->middleware('permission:admin.users.manage')->name('users.create');
        Route::post('users', [UserController::class, 'store'])->middleware('permission:admin.users.manage')->name('users.store');
        Route::get('users/{user}/edit', [UserController::class, 'edit'])->middleware('permission:admin.users.manage')->name('users.edit');
        Route::put('users/{user}', [UserController::class, 'update'])->middleware('permission:admin.users.manage')->name('users.update');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->middleware('permission:admin.users.manage')->name('users.destroy');

        Route::get('personnel', [PersonnelController::class, 'index'])->middleware('permission:admin.personnel.manage')->name('personnel.index');
        Route::post('personnel', [PersonnelController::class, 'store'])->middleware('permission:admin.personnel.manage')->name('personnel.store');
        Route::put('personnel/{personnel}', [PersonnelController::class, 'update'])->middleware('permission:admin.personnel.manage')->name('personnel.update');
        Route::delete('personnel/{personnel}', [PersonnelController::class, 'destroy'])->middleware('permission:admin.personnel.manage')->name('personnel.destroy');

        Route::get('master-data', [MasterDataController::class, 'index'])->middleware('permission:admin.settings.manage')->name('master.index');
        Route::post('master-data/{type}', [MasterDataController::class, 'store'])->middleware('permission:admin.settings.manage')->name('master.store');
        Route::put('master-data/{type}/{id}', [MasterDataController::class, 'update'])->middleware('permission:admin.settings.manage')->name('master.update');
        Route::delete('master-data/{type}/{id}', [MasterDataController::class, 'destroy'])->middleware('permission:admin.settings.manage')->name('master.destroy');

        Route::get('settings', [SettingController::class, 'index'])->middleware('permission:admin.settings.manage')->name('settings.index');
        Route::put('settings', [SettingController::class, 'update'])->middleware('permission:admin.settings.manage')->name('settings.update');
        Route::post('shift-rates', [SettingController::class, 'storeShiftRate'])->middleware('permission:admin.settings.manage')->name('shift-rates.store');
        Route::put('shift-rates/{shiftRate}', [SettingController::class, 'updateShiftRate'])->middleware('permission:admin.settings.manage')->name('shift-rates.update');
        Route::delete('shift-rates/{shiftRate}', [SettingController::class, 'destroyShiftRate'])->middleware('permission:admin.settings.manage')->name('shift-rates.destroy');

        Route::get('quarterly-targets', [QuarterlyTargetController::class, 'adminIndex'])->middleware('permission:admin.settings.manage')->name('quarterly-targets.index');
        Route::put('quarterly-targets', [QuarterlyTargetController::class, 'update'])->middleware('permission:admin.settings.manage')->name('quarterly-targets.update');

        Route::get('audit-logs', [AuditLogController::class, 'index'])->middleware('permission:admin.audit.view')->name('audit.index');
    });

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
