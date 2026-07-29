<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\User\DashboardController as UserDashboardController;
use App\Http\Controllers\User\MonitoringController as UserMonitoringController;
use App\Http\Controllers\User\CameraController as UserCameraController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\MonitoringController as AdminMonitoringController;
use App\Http\Controllers\Admin\CameraController as AdminCameraController;
use App\Http\Controllers\Admin\AlarmController;
use App\Http\Controllers\Admin\SettingController;

/*
|--------------------------------------------------------------------------
| LOGIN ADMIN
|--------------------------------------------------------------------------
*/
Route::get('/login', function () {
    return view('auth.login');
})->name('login');
Route::post('/login', [LoginController::class, 'login'])
    ->name('login.process');
Route::post('/logout', [LoginController::class, 'logout'])
    ->name('logout');

/*
|--------------------------------------------------------------------------
| USER
|--------------------------------------------------------------------------
*/
Route::get('/', [UserDashboardController::class, 'index'])
    ->name('dashboard');
Route::get('/monitoring', [UserMonitoringController::class, 'index'])
    ->name('monitoring');
Route::get('/camera', [UserCameraController::class, 'index'])
    ->name('camera');

/*
|--------------------------------------------------------------------------
| ADMIN
|--------------------------------------------------------------------------
*/

Route::prefix('admin')
    ->middleware('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])
            ->name('dashboard');
        Route::get('/monitoring', [AdminMonitoringController::class, 'index'])
            ->name('monitoring');
        Route::get('/camera', [AdminCameraController::class, 'index'])
            ->name('camera');
        Route::get('/alarm', [AlarmController::class, 'index'])
            ->name('alarm');
        Route::get('/settings', [SettingController::class, 'index'])
            ->name('settings');
        Route::get('/settings/account', [SettingController::class, 'account'])
            ->name('settings.account');
        Route::get('/settings/account/edit', [SettingController::class, 'editAccount'])
            ->name('settings.account.edit');
        Route::post('/settings/account/edit', [SettingController::class, 'updateAccount'])
            ->name('settings.account.update');
        Route::get('/settings/account/password', [SettingController::class, 'resetPassword'])
            ->name('settings.account.password');
        Route::post('/settings/account/password', [SettingController::class, 'updatePassword'])
            ->name('settings.account.password.update');
        Route::post('/monitoring/track', [AdminMonitoringController::class, 'updateTrack'])
            ->name('monitoring.track');
    });