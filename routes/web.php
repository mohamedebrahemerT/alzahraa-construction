<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\SiteController;
use App\Support\SiteBackup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', [SiteController::class, 'home'])->name('home');
Route::get('/about', [SiteController::class, 'about'])->name('about');
Route::get('/services', [SiteController::class, 'services'])->name('services');
Route::get('/services/{slug}', [SiteController::class, 'service'])->name('services.show');
Route::get('/projects', [SiteController::class, 'projects'])->name('projects');
Route::get('/projects/{slug}', [SiteController::class, 'project'])->name('projects.show');
Route::get('/equipment', [SiteController::class, 'equipment'])->name('equipment');
Route::get('/contact', [SiteController::class, 'contact'])->name('contact');
Route::post('/estimate', [SiteController::class, 'estimate'])->middleware('throttle:5,10')->name('estimate.store');
Route::get('/sitemap.xml', [SiteController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SiteController::class, 'robots'])->name('robots');

Route::get('/admin/login', [AdminController::class, 'loginForm'])->middleware('guest')->name('login');
Route::post('/admin/login', [AdminController::class, 'login'])->middleware(['guest', 'throttle:6,1'])->name('admin.login');
Route::middleware(['auth', 'active.admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::post('/logout', [AdminController::class, 'logout'])->name('logout');
    Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/pages', [AdminController::class, 'pages'])->name('pages.index');
    Route::post('/pages/order', [AdminController::class, 'reorderPages'])->name('pages.order');
    Route::get('/pages/create', [AdminController::class, 'pageForm'])->name('pages.create');
    Route::get('/pages/{page}/preview', [AdminController::class, 'pagePreview'])->whereNumber('page')->name('pages.preview');
    Route::get('/pages/{page}/preview-frame', [AdminController::class, 'pagePreviewFrame'])->whereNumber('page')->name('pages.preview.frame');
    Route::post('/pages', [AdminController::class, 'savePage'])->name('pages.store');
    Route::get('/pages/{page}/edit', [AdminController::class, 'pageForm'])->whereNumber('page')->name('pages.edit');
    Route::put('/pages/{page}', [AdminController::class, 'savePage'])->whereNumber('page')->name('pages.update');
    Route::delete('/pages/{page}', [AdminController::class, 'deletePage'])->whereNumber('page')->name('pages.delete');
    Route::post('/revisions/{revision}/restore', [AdminController::class, 'restoreRevision'])->whereNumber('revision')->name('revisions.restore');
    Route::get('/catalog/{kind}', [AdminController::class, 'catalog'])->name('catalog');
    Route::post('/catalog/{kind}/order', [AdminController::class, 'reorder'])->name('catalog.order');
    Route::post('/catalog/{kind}/{id}/revisions/{revision}/restore', [AdminController::class, 'restoreItemRevision'])
        ->whereNumber('id')->whereNumber('revision')->name('catalog.revisions.restore');
    Route::post('/catalog/{kind}', [AdminController::class, 'saveItem'])->name('catalog.store');
    Route::put('/catalog/{kind}/{id}', [AdminController::class, 'saveItem'])->whereNumber('id')->name('catalog.update');
    Route::delete('/catalog/{kind}/{id}', [AdminController::class, 'deleteItem'])->whereNumber('id')->name('catalog.delete');
    Route::get('/settings', [AdminController::class, 'settings'])->middleware('manager')->name('settings');
    Route::put('/settings', [AdminController::class, 'saveSettings'])->middleware('manager')->name('settings.update');
    Route::get('/requests', [AdminController::class, 'leads'])->name('leads');
    Route::get('/requests/export.csv', [AdminController::class, 'exportLeads'])->name('leads.export');
    Route::put('/requests/{lead}', [AdminController::class, 'updateLead'])->whereNumber('lead')->name('leads.update');
    Route::get('/media', [AdminController::class, 'media'])->name('media');
    Route::post('/media', [AdminController::class, 'uploadMedia'])->name('media.upload');
    Route::put('/media/{media}', [AdminController::class, 'updateMedia'])->whereNumber('media')->name('media.update');
    Route::delete('/media/{media}', [AdminController::class, 'deleteMedia'])->whereNumber('media')->name('media.delete');
    Route::get('/activity', [AdminController::class, 'activity'])->middleware('manager')->name('activity');
    Route::get('/backup', function (SiteBackup $backup) {
        $path = $backup->create();

        return response()->download($path, basename($path), ['Content-Type' => 'application/zip']);
    })->middleware('manager')->name('backup.download');
    Route::post('/backup/restore', function (Request $request, SiteBackup $backup) {
        $data = $request->validate(['backup' => ['required', 'file', 'mimes:zip', 'max:102400']]);
        $backup->restore($data['backup']->getRealPath());

        return redirect()->route('admin.dashboard')->with('status', 'تم استعادة النسخة الاحتياطية.');
    })->middleware('manager')->name('backup.restore');
    Route::middleware('manager')->group(function () {
        Route::get('/users', [AdminController::class, 'users'])->name('users');
        Route::post('/users', [AdminController::class, 'saveUser'])->name('users.store');
        Route::put('/users/{user}', [AdminController::class, 'saveUser'])->whereNumber('user')->name('users.update');
    });
});

Route::prefix('en')->name('en.')->group(function () {
    Route::get('/', [SiteController::class, 'home'])->name('home');
    Route::get('/about', [SiteController::class, 'about'])->name('about');
    Route::get('/services', [SiteController::class, 'services'])->name('services');
    Route::get('/services/{slug}', [SiteController::class, 'service'])->name('services.show');
    Route::get('/projects', [SiteController::class, 'projects'])->name('projects');
    Route::get('/projects/{slug}', [SiteController::class, 'project'])->name('projects.show');
    Route::get('/equipment', [SiteController::class, 'equipment'])->name('equipment');
    Route::get('/contact', [SiteController::class, 'contact'])->name('contact');
    Route::post('/estimate', [SiteController::class, 'estimate'])->middleware('throttle:5,10')->name('estimate.store');
    Route::get('/sitemap.xml', [SiteController::class, 'sitemap'])->name('sitemap');
    Route::get('/robots.txt', [SiteController::class, 'robots'])->name('robots');
    Route::get('/{slug}', [SiteController::class, 'page'])
        ->where('slug', '^(?!admin$|services$|projects$|contact$|equipment$|about$|sitemap\.xml$|robots\.txt$)[A-Za-z0-9_-]+$')
        ->name('pages.show');
});

Route::get('/{slug}', [SiteController::class, 'page'])
    ->where('slug', '^(?!admin$|services$|projects$|contact$|equipment$|about$|sitemap\.xml$|robots\.txt$)[A-Za-z0-9_-]+$')
    ->name('pages.show');
