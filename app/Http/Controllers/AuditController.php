<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

/**
 * The trail behind every figure. Each make, check and authorise is written with the person, the
 * office they held that day and the time, so a question in a meeting can be answered with a name
 * rather than a guess about who was keeper of the books.
 *
 * The System Owner is the one reader the trail hides part of itself from: they can see that an entry
 * was made and checked, but not what it moved. Accounts are their job, other people's savings are
 * not, and the screen says so instead of showing a shorter list that looks complete.
 */
class AuditController extends Controller
{
    /** Reference prefixes that belong to a money entry. */
    private const FINANCIAL = ['TX-', 'LN-', 'EXT-', 'PN-'];

    public function index(Request $request)
    {
        $isOwner = $request->user()->hasRole('admin');

        $entries = ActivityLog::query()
            ->when($isOwner, fn ($q) => $this->notMoney($q))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(300)
            ->get();

        return view('pages.audit', [
            'page' => 'audit',
            'entries' => $entries,
            'isOwner' => $isOwner,
            'hidden' => $isOwner
                ? $this->onlyMoney(ActivityLog::query())->count()
                : 0,
        ]);
    }

    private function notMoney($query)
    {
        return $query->where(function ($q) {
            foreach (self::FINANCIAL as $prefix) {
                $q->where(fn ($one) => $one->whereNull('subject')->orWhere('subject', 'not like', $prefix.'%'));
            }
        });
    }

    private function onlyMoney($query)
    {
        return $query->where(function ($q) {
            foreach (self::FINANCIAL as $prefix) {
                $q->orWhere('subject', 'like', $prefix.'%');
            }
        });
    }
}
