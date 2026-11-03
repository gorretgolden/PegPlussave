<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\DirectoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LedgerController;
use App\Http\Controllers\MobiliseController;
use App\Http\Controllers\MinuteController;
use App\Http\Controllers\NoticeController;
use App\Http\Controllers\PaymentNoticeController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SavingsController;
use App\Http\Controllers\MemberLoanController;
use App\Http\Controllers\TreasuryController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'show'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware(['auth', 'active'])
    ->name('logout');

/**
 * Every screen is a name, and the name is the ability. Nav::GROUPS holds the same pairing, so a
 * route cannot be reached by typing its address by somebody the menu would never have shown it.
 */
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('pages.dashboard')->middleware('can:view-dashboard');
    Route::get('/savings', [SavingsController::class, 'index'])->name('pages.savings')->middleware('can:view-own-savings');
    Route::get('/loans', [MemberLoanController::class, 'index'])->name('pages.loans')->middleware('can:view-own-loans');
    Route::get('/calendar', [CalendarController::class, 'index'])->name('pages.calendar')->middleware('can:view-calendar');
    Route::get('/directory', [DirectoryController::class, 'index'])->name('pages.directory')->middleware('can:view-directory');
    Route::get('/notices', [NoticeController::class, 'index'])->name('pages.notices')->middleware('can:view-notices');
    Route::get('/minutes', [MinuteController::class, 'index'])->name('pages.minutes')->middleware('can:view-minutes');
    Route::get('/approvals', [ApprovalController::class, 'index'])->name('pages.approvals')->middleware('can:view-approvals');
    Route::get('/treasury', [TreasuryController::class, 'index'])->name('pages.treasury')->middleware('can:view-treasury');
    Route::get('/reports', [ReportController::class, 'index'])->name('pages.reports')->middleware('can:view-reports');
    Route::get('/ledger', [LedgerController::class, 'index'])->name('pages.ledger')->middleware('can:view-ledger');
    Route::get('/mobilise', [MobiliseController::class, 'index'])->name('pages.mobilise')->middleware('can:view-mobilisation');
    Route::get('/audit', [AuditController::class, 'index'])->name('pages.audit')->middleware('can:view-audit');
    Route::get('/admin', [AdminController::class, 'index'])->name('pages.admin')->middleware('can:manage-system');

    Route::get('/ledger/export', [LedgerController::class, 'export'])->name('ledger.export')->middleware('can:view-ledger');
    Route::get('/entries/{transaction}', [LedgerController::class, 'show'])->name('entries.show')->middleware('can:view-treasury');
    Route::get('/reports/{report}/excel', [ReportController::class, 'excel'])->name('reports.excel')->middleware('can:view-reports');

    // The figures a form works out while it is being filled in. They are read from the same
    // services that write the entry, so a preview cannot promise a number the books will not keep.
    Route::get('/treasury/interest-figures', [TreasuryController::class, 'figures'])->name('treasury.figures')->middleware('can:view-treasury');
    Route::get('/treasury/repay-figures', [TreasuryController::class, 'repayFigures'])->name('treasury.repay-figures')->middleware('can:view-treasury');
    Route::get('/treasury/exit-figures', [TreasuryController::class, 'exitFigures'])->name('treasury.exit-figures')->middleware('can:view-treasury');

    // A member speaks for themselves only. The scope of these routes never mentions another name.
    Route::post('/savings/notice', [PaymentNoticeController::class, 'store'])->name('paynotice.store')->middleware('can:make-member-entry');
    Route::post('/loans/request', [MemberLoanController::class, 'book'])->name('member-loan.book')->middleware('can:make-member-entry');

    Route::middleware('can:make-treasury-entry')->group(function () {
        Route::post('/treasury/deposit', [TreasuryController::class, 'deposit'])->name('treasury.deposit');
        Route::post('/treasury/loan/book', [TreasuryController::class, 'bookLoan'])->name('treasury.loan.book');
        Route::post('/treasury/loan/{loan}/appraise', [TreasuryController::class, 'appraise'])->name('treasury.loan.appraise');
        Route::post('/treasury/repay', [TreasuryController::class, 'repay'])->name('treasury.repay');
        Route::post('/treasury/interest', [TreasuryController::class, 'interest'])->name('treasury.interest');
        Route::post('/treasury/payout', [TreasuryController::class, 'payout'])->name('treasury.payout');
        Route::post('/treasury/external', [TreasuryController::class, 'external'])->name('treasury.external');
        Route::post('/treasury/external/repay', [TreasuryController::class, 'externalRepay'])->name('treasury.external.repay');
        Route::post('/treasury/exit', [TreasuryController::class, 'exit'])->name('treasury.exit');
        Route::post('/payment-notice/{paymentNotice}/dismiss', [PaymentNoticeController::class, 'dismiss'])->name('paynotice.dismiss');
    });

    // Which office may act on which entry is decided inside the workflow, because the answer is a
    // property of the entry: the Chairperson checks what waits for the chair, the Patron what waits
    // for the patron. The route only has to keep everyone else off the desk.
    Route::post('/approvals/{transaction}/approve', [ApprovalController::class, 'approve'])->name('approvals.approve')->middleware('can:view-approvals');
    Route::post('/approvals/{transaction}/reject', [ApprovalController::class, 'reject'])->name('approvals.reject')->middleware('can:view-approvals');
    Route::post('/approvals/{transaction}/note', [ApprovalController::class, 'note'])->name('approvals.note')->middleware('can:view-audit');

    Route::post('/calendar/event', [CalendarController::class, 'store'])->name('calendar.store')->middleware('can:add-event');
    Route::delete('/calendar/event/{event}', [CalendarController::class, 'destroy'])->name('calendar.destroy')->middleware('can:add-event');

    Route::post('/directory/member', [DirectoryController::class, 'store'])->name('directory.store')->middleware('can:register-member');
    Route::put('/directory/member/{member}', [DirectoryController::class, 'update'])->name('directory.update')->middleware('can:edit-member');
    Route::post('/directory/member/{member}/exit-notice', [DirectoryController::class, 'flagExit'])->name('directory.exit')->middleware('can:flag-exit');
    Route::delete('/directory/member/{member}/exit-notice', [DirectoryController::class, 'unflagExit'])->name('directory.unflag')->middleware('can:flag-exit');

    Route::post('/notices', [NoticeController::class, 'store'])->name('notices.store')->middleware('can:post-notice');
    Route::patch('/notices/{notice}/pin', [NoticeController::class, 'pin'])->name('notices.pin')->middleware('can:post-notice');
    Route::delete('/notices/{notice}', [NoticeController::class, 'destroy'])->name('notices.destroy')->middleware('can:post-notice');

    Route::post('/minutes', [MinuteController::class, 'store'])->name('minutes.store')->middleware('can:record-minutes');
    Route::delete('/minutes/{minute}', [MinuteController::class, 'destroy'])->name('minutes.destroy')->middleware('can:record-minutes');

    Route::post('/mobilise/prospect', [MobiliseController::class, 'storeProspect'])->name('mobilise.prospect')->middleware('can:add-prospect');
    Route::patch('/mobilise/prospect/{prospect}', [MobiliseController::class, 'moveProspect'])->name('mobilise.prospect.move')->middleware('can:add-prospect');
    Route::post('/mobilise/remind/{member}', [MobiliseController::class, 'remind'])->name('mobilise.remind')->middleware('can:view-mobilisation');

    Route::put('/admin/settings', [AdminController::class, 'settings'])->name('admin.settings')->middleware('can:manage-system');
    Route::post('/admin/logo', [AdminController::class, 'logo'])->name('admin.logo')->middleware('can:manage-system');
    Route::delete('/admin/logo', [AdminController::class, 'clearLogo'])->name('admin.logo.clear')->middleware('can:manage-system');
    Route::post('/admin/users', [AdminController::class, 'storeUser'])->name('admin.users.store')->middleware('can:manage-system');
    Route::patch('/admin/user/{user}/role', [AdminController::class, 'role'])->name('admin.users.role')->middleware('can:manage-system');
    Route::patch('/admin/user/{user}/status', [AdminController::class, 'status'])->name('admin.users.status')->middleware('can:manage-system');
    Route::post('/admin/user/{user}/password', [AdminController::class, 'password'])->name('admin.users.password')->middleware('can:manage-system');
});
