<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Loan;
use App\Models\Member;
use App\Models\Transaction;
use App\Support\Eligibility;
use App\Support\Format;
use App\Support\LoanFacts;
use App\Support\Policy;
use App\Support\Refs;
use App\Support\TreasuryPosition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * What a member does with loans: read the terms the group lends on, follow the one being paid back,
 * and ask for the next. A request raised here is only a request. Nothing in the books moves until
 * the Treasurer appraises it and the booking clears the Chairperson.
 */
class MemberLoanController extends Controller
{
    /**
     * The smallest amount the box will cut. It is a floor on the entry, not a policy rate, so it
     * stays here rather than in the settings the committee can change.
     */
    public const MINIMUM = 50000;

    public function index()
    {
        $member = Member::query()->findOrFail(auth()->user()->member_id);
        $ledgers = TreasuryPosition::loanLedgers();

        $mine = $member->loans()
            ->with('guarantors')
            ->orderByDesc('requested_at')
            ->orderByDesc('id')
            ->get();

        $running = $mine->first(fn (Loan $loan) => $loan->disbursed_on
            && ($ledgers[$loan->id]['state'] ?? 'Closed') !== 'Closed');

        return view('pages.loans', [
            'page' => 'loans',
            'member' => $member,
            'loans' => $mine,
            'ledgers' => $ledgers,
            'running' => $running ? $ledgers[$running->id] : null,
            'balance' => (int) (TreasuryPosition::memberBalances()[$member->id]['balance'] ?? 0),
            'facts' => LoanFacts::for([$member->id]),
            'policy' => LoanFacts::policy(),
        ]);
    }

    /**
     * Send a request. It carries the terms on offer today rather than any terms the member types,
     * because the rate and the split are the group's to set; and it is refused here, in front of the
     * member, when it does not meet the same checks the Treasurer will read a day later.
     */
    public function book(Request $request)
    {
        $member = Member::query()->findOrFail(auth()->user()->member_id);

        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:'.self::MINIMUM],
            'months' => ['required', 'integer', 'min:1', 'max:'.Policy::get('max_months')],
            'purpose' => ['required', 'string', Rule::in(Transaction::PURPOSES)],
            'detail' => ['nullable', 'string', 'max:180'],
            'guarantor_1' => ['nullable', 'integer'],
            'guarantor_2' => ['nullable', 'integer'],
        ], [
            'amount.min' => 'Enter a loan amount of at least '.Format::ugx(self::MINIMUM).'.',
            'months.max' => 'The longest loan PEG runs is '.Policy::get('max_months').' months.',
        ]);

        $amount = (int) $data['amount'];
        $months = (int) $data['months'];
        $missed = Eligibility::hard(Eligibility::checks($member, $amount, $months));

        if ($missed !== []) {
            session()->flash('open_modal', 'dlg-loanbook');

            throw ValidationException::withMessages([
                'amount' => 'This request does not meet: '
                    .implode('; ', array_map(fn (array $check) => mb_strtolower($check['label']), $missed)).'.',
            ]);
        }

        $loan = DB::transaction(fn () => $this->record($member, $data, $amount, $months));

        ActivityLog::record($request->user(), "Requested loan {$loan->ref}", $loan->ref);

        return redirect()
            ->route('pages.loans')
            ->with('status', "Loan request {$loan->ref} sent to the Treasurer");
    }

    private function record(Member $member, array $data, int $amount, int $months): Loan
    {
        $detail = trim((string) ($data['detail'] ?? ''));

        $loan = Loan::create([
            'ref' => Refs::loan(),
            'member_id' => $member->id,
            'amount' => $amount,
            'months' => $months,
            'rate' => Policy::get('loan_rate'),
            'method' => Policy::get('loan_method'),
            'purpose' => $detail === '' ? $data['purpose'] : $data['purpose'].': '.$detail,
            'status' => Loan::REQUESTED,
            'requested_by' => auth()->id(),
            'requested_at' => now(),
        ]);

        $guarantors = collect([$data['guarantor_1'] ?? null, $data['guarantor_2'] ?? null])
            ->filter()
            ->unique()
            ->reject(fn (int $id) => $id === $member->id);

        $loan->guarantors()->sync(
            Member::query()->whereIn('id', $guarantors)->pluck('id')
        );

        return $loan;
    }
}
