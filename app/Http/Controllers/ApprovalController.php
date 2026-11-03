<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\EntryWorkflow;
use App\Support\TreasuryPosition;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The checking desk. It is the same card in three queues: what is in front of this officer, what has
 * moved on without them, and what they have already decided. Nothing on this page changes a number by
 * hand; a yes posts the entry and the books move on their own, a no sends it back with the reason.
 */
class ApprovalController extends Controller
{
    public const TABS = ['mine', 'other', 'decided'];

    private const WITH = [
        'member', 'loan.member', 'externalLoan', 'maker', 'checks', 'notes', 'allocations',
    ];

    public function index(Request $request)
    {
        $user = $request->user();
        $patronSide = $user->hasRole('patron') || $user->hasRole('admin');

        $mine = $this->queue($patronSide ? Transaction::PENDING_PATRON : Transaction::PENDING_CHAIR);
        $other = $this->queue($patronSide ? Transaction::PENDING_CHAIR : Transaction::PENDING_PATRON);

        $decided = Transaction::query()->with(self::WITH)
            ->whereHas('checks', fn ($query) => $query->where('user_id', $user->id))
            ->get()
            ->sortByDesc(fn (Transaction $t) => optional($t->checks->filter(
                fn ($check) => $check->user_id === $user->id)->last())->checked_at)
            ->take(40)
            ->values();

        $position = TreasuryPosition::group();

        return view('pages.approvals', [
            'page' => 'approvals',
            'tab' => in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'mine',
            'mine' => $mine,
            'other' => $other,
            'decided' => $decided,
            'isPatronSide' => $patronSide,
            'balances' => $position['balances'],
            'ledgers' => $position['ledgers'],
            'isTreasurer' => $user->can('make-treasury-entry'),
            'isChair' => $user->hasRole('chair'),
        ]);
    }

    public function approve(Request $request, Transaction $transaction)
    {
        $field = 'comment-'.$transaction->id;
        $data = $request->validate([
            $field => ['nullable', 'string', 'max:300'],
        ]);

        $message = $this->check(
            fn () => EntryWorkflow::approve($transaction, $request->user(), $data[$field] ?? null),
            $field
        );

        return back()->with('status', $message);
    }

    public function reject(Request $request, Transaction $transaction)
    {
        $field = 'comment-'.$transaction->id;
        $data = $request->validate([
            $field => ['required', 'string', 'max:300'],
        ], [
            $field.'.required' => 'Add a reason so the Treasurer knows what to fix.',
        ]);

        $message = $this->check(
            fn () => EntryWorkflow::reject($transaction, $request->user(), $data[$field]),
            $field
        );

        return back()->with('status', $message);
    }

    /**
     * A note is not a decision. It is allowed on any entry by anybody who can see the audit trail,
     * because the point is that the reason a figure was queried stays beside the figure.
     */
    public function note(Request $request, Transaction $transaction)
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'max:600'],
        ], [
            'text.required' => 'Write the note before saving it.',
        ]);

        $transaction->notes()->create([
            'user_id' => $request->user()->id,
            'role' => $request->user()->roleKey(),
            'text' => trim($data['text']),
            'noted_at' => now(),
        ]);

        return back()->with('status', 'Note added');
    }

    /**
     * The workflow is the only place that knows whether this officer may act on this entry, so it is
     * asked rather than re-implemented here. Its refusal comes back as the field error on the card
     * that was clicked, because a checker who is not the right checker needs to know which card.
     */
    private function check(callable $action, string $field): string
    {
        try {
            return $action();
        } catch (HttpException $exception) {
            throw ValidationException::withMessages([
                $field => $exception->getMessage() !== ''
                    ? $exception->getMessage()
                    : 'This entry cannot be checked from your account.',
            ]);
        }
    }

    private function queue(string $status)
    {
        return Transaction::query()->with(self::WITH)
            ->where('status', $status)
            ->orderBy('made_at')
            ->orderBy('id')
            ->get();
    }
}
