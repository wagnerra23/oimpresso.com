<?php

// use App\Http\Controllers\Modules;
// use Illuminate\Support\Facades\Route;

Route::middleware('web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu')->group(function () {
    Route::prefix('essentials')->group(function () {
        Route::get('/dashboard', [Modules\Essentials\Http\Controllers\DashboardController::class, 'essentialsDashboard']);
        Route::get('/install', [Modules\Essentials\Http\Controllers\InstallController::class, 'index']);
        Route::get('/install/update', [Modules\Essentials\Http\Controllers\InstallController::class, 'update']);
        Route::get('/install/uninstall', [Modules\Essentials\Http\Controllers\InstallController::class, 'uninstall']);

        Route::get('/', [Modules\Essentials\Http\Controllers\EssentialsController::class, 'index']);

        //document controller
        // D8 Security Wave 15 — throttle upload anti-DOS: 30 store / 60 download por minuto.
        Route::post('document', [Modules\Essentials\Http\Controllers\DocumentController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('document.store');
        Route::resource('document', 'Modules\Essentials\Http\Controllers\DocumentController')->only(['index', 'destroy', 'show']);
        Route::get('document/download/{id}', [Modules\Essentials\Http\Controllers\DocumentController::class, 'download'])
            ->middleware('throttle:60,1');

        //document share controller
        Route::resource('document-share', 'Modules\Essentials\Http\Controllers\DocumentShareController')->only(['edit', 'update']);

        //todo controller
        Route::resource('todo', 'ToDoController');

        // D8 Security Wave 15 — throttle 60/min em endpoints que aceitam comment/upload livre.
        Route::post('todo/add-comment', [Modules\Essentials\Http\Controllers\ToDoController::class, 'addComment'])
            ->middleware('throttle:60,1');
        Route::get('todo/delete-comment/{id}', [Modules\Essentials\Http\Controllers\ToDoController::class, 'deleteComment']);
        Route::get('todo/delete-document/{id}', [Modules\Essentials\Http\Controllers\ToDoController::class, 'deleteDocument']);
        Route::post('todo/upload-document', [Modules\Essentials\Http\Controllers\ToDoController::class, 'uploadDocument'])
            ->middleware('throttle:30,1');
        Route::get('view-todo-{id}-share-docs', [Modules\Essentials\Http\Controllers\ToDoController::class, 'viewSharedDocs']);

        //reminder controller
        Route::resource('reminder', 'Modules\Essentials\Http\Controllers\ReminderController')->only(['index', 'store', 'edit', 'update', 'destroy', 'show']);

        //message controller
        Route::get('get-new-messages', [Modules\Essentials\Http\Controllers\EssentialsMessageController::class, 'getNewMessages']);
        // D8 Security Wave 15 — throttle anti-spam chat (60 msg/min por user)
        Route::post('messages', [Modules\Essentials\Http\Controllers\EssentialsMessageController::class, 'store'])
            ->middleware('throttle:60,1')
            ->name('messages.store');
        Route::resource('messages', 'Modules\Essentials\Http\Controllers\EssentialsMessageController')->only(['index', 'destroy']);

        //Allowance and deduction controller
        Route::resource('allowance-deduction', 'Modules\Essentials\Http\Controllers\EssentialsAllowanceAndDeductionController');

        Route::resource('knowledge-base', 'Modules\Essentials\Http\Controllers\KnowledgeBaseController');

        Route::get('user-sales-targets', [Modules\Essentials\Http\Controllers\DashboardController::class, 'getUserSalesTargets']);
    });

    Route::prefix('hrm')->group(function () {
        Route::get('/dashboard', [Modules\Essentials\Http\Controllers\DashboardController::class, 'hrmDashboard'])->name('hrmDashboard');
        Route::resource('/leave-type', 'Modules\Essentials\Http\Controllers\EssentialsLeaveTypeController');
        Route::resource('/leave', 'Modules\Essentials\Http\Controllers\EssentialsLeaveController');
        Route::post('/change-status', [Modules\Essentials\Http\Controllers\EssentialsLeaveController::class, 'changeStatus']);
        Route::get('/leave/activity/{id}', [Modules\Essentials\Http\Controllers\EssentialsLeaveController::class, 'activity']);
        Route::get('/user-leave-summary', [Modules\Essentials\Http\Controllers\EssentialsLeaveController::class, 'getUserLeaveSummary']);

        Route::get('/settings', [Modules\Essentials\Http\Controllers\EssentialsSettingsController::class, 'edit']);
        Route::post('/settings', [Modules\Essentials\Http\Controllers\EssentialsSettingsController::class, 'update']);

        // Presença web do HRM CEDE ao Ponto (D1 [W] 2026-09-05; ADR 0014 emenda). As 11 rotas de
        // attendance viram 301 para o destino no Ponto, pra não deixar link morto em quem guardou
        // favorito ou tem a tela antiga aberta. O AttendanceController NÃO é apagado aqui: o dado
        // de essentials_attendances migra em PR próprio (dupla prova, proibicoes.md VALOR).
        Route::permanentRedirect('/import-attendance', '/ponto/importacoes');
        Route::permanentRedirect('/attendance', '/ponto/espelho');
        Route::permanentRedirect('/attendance/{qualquer}', '/ponto/espelho')->where('qualquer', '.*');
        Route::permanentRedirect('/clock-in-clock-out', '/ponto');
        Route::permanentRedirect('/validate-clock-in-clock-out', '/ponto');
        Route::permanentRedirect('/get-attendance-by-shift', '/ponto/espelho');
        Route::permanentRedirect('/get-attendance-by-date', '/ponto/espelho');
        Route::permanentRedirect('/get-attendance-row/{user_id}', '/ponto/espelho');
        Route::permanentRedirect('/user-attendance-summary', '/ponto/espelho');

        Route::get('/location-employees', [Modules\Essentials\Http\Controllers\PayrollController::class, 'getEmployeesBasedOnLocation']);
        Route::get('/my-payrolls', [Modules\Essentials\Http\Controllers\PayrollController::class, 'getMyPayrolls']);
        Route::get('/get-allowance-deduction-row', [Modules\Essentials\Http\Controllers\PayrollController::class, 'getAllowanceAndDeductionRow']);
        Route::get('/payroll-group-datatable', [Modules\Essentials\Http\Controllers\PayrollController::class, 'payrollGroupDatatable']);
        Route::get('/view/{id}/payroll-group', [Modules\Essentials\Http\Controllers\PayrollController::class, 'viewPayrollGroup']);
        Route::get('/edit/{id}/payroll-group', [Modules\Essentials\Http\Controllers\PayrollController::class, 'getEditPayrollGroup']);
        Route::post('/update-payroll-group', [Modules\Essentials\Http\Controllers\PayrollController::class, 'getUpdatePayrollGroup']);
        Route::get('/payroll-group/{id}/add-payment', [Modules\Essentials\Http\Controllers\PayrollController::class, 'addPayment']);
        Route::post('/post-payment-payroll-group', [Modules\Essentials\Http\Controllers\PayrollController::class, 'postAddPayment']);
        Route::resource('/payroll', 'Modules\Essentials\Http\Controllers\PayrollController');
        Route::resource('/holiday', 'EssentialsHolidayController');

        Route::get('/shift/assign-users/{shift_id}', [Modules\Essentials\Http\Controllers\ShiftController::class, 'getAssignUsers']);
        Route::post('/shift/assign-users', [Modules\Essentials\Http\Controllers\ShiftController::class, 'postAssignUsers']);
        Route::resource('/shift', 'Modules\Essentials\Http\Controllers\ShiftController');
        Route::get('/sales-target', [Modules\Essentials\Http\Controllers\SalesTargetController::class, 'index']);
        Route::get('/set-sales-target/{id}', [Modules\Essentials\Http\Controllers\SalesTargetController::class, 'setSalesTarget']);
        Route::post('/save-sales-target', [Modules\Essentials\Http\Controllers\SalesTargetController::class, 'saveSalesTarget']);
    });
});
