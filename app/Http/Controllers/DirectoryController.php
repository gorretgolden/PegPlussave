<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Member;
use App\Models\Prospect;
use App\Models\Transaction;
use App\Services\EntryWorkflow;
use App\Support\Format;
use App\Support\Policy;
use App\Support\TreasuryPosition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The members register. A name becomes a member in two steps: an officer puts the registration in,
 * and the Chairperson approves it. Until then the person is a claim on a pending entry, so the
 * register can show who is waiting without counting them as somebody who has joined.
 *
 * New members are made here; a member leaving starts here too, as an exit notice for the Treasurer.
 */
class DirectoryController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->input('status', 'active');
        $status = in_array($status, ['active', 'left', 'all'], true) ? $status : 'active';
        $department = $request->input('dept', 'all');

        $members = Member::query()
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($department !== 'all', fn ($q) => $q->where('department', $department))
            ->orderBy('code')
            ->with('user:id,member_id,name,username,status')
            ->get();

        $user = $request->user();
        $isTreasury = $user->can('view-treasury');

        // Arriving from the membership drive means one job: put this person's name in. The box is
        // opened by itself so the officer is not asked to find the button again.
        $prospect = $request->integer('prospect') && $user->can('register-member')
            ? Prospect::query()->find($request->integer('prospect'))
            : null;

        if ($prospect) {
            session()->flash('open_modal', 'dlg-addmember');
        }

        return view('pages.directory', [
            'page' => 'directory',
            'members' => $members,
            'status' => $status,
            'department' => $department,
            'departments' => Member::DEPARTMENTS,
            'counts' => [
                'active' => Member::query()->where('status', 'active')->count(),
                'left' => Member::query()->where('status', 'left')->count(),
                'all' => Member::query()->count(),
            ],
            'waiting' => Transaction::query()
                ->where('type', Transaction::MEMBERSHIP)
                ->where('status', 'like', 'pending%')
                ->orderBy('made_at')
                ->get(),
            'canRegister' => $user->can('register-member'),
            'canFlag' => $user->can('flag-exit'),
            'prospect' => $prospect,
            'rows' => $isTreasury ? TreasuryPosition::memberBalances() : [],
            'ledgers' => $isTreasury ? TreasuryPosition::loanLedgers() : [],
            'minimum' => (int) Policy::get('min_monthly'),
        ]);
    }

    /**
     * Registering sends the name to the Chairperson; it does not create a member. The PEG number and
     * the login are only taken up when the entry posts, so a rejected registration leaves no shell of
     * an account behind.
     */
    public function store(Request $request)
    {
        $data = $this->validated($request, joining: true);

        $prospect = empty($data['prospect_id'])
            ? null
            : Prospect::query()->findOrFail((int) $data['prospect_id']);

        $transaction = DB::transaction(function () use ($data, $request, $prospect) {
            $transaction = EntryWorkflow::make([
                'type' => Transaction::MEMBERSHIP,
                'amount' => (int) $data['monthly'],
                'value_date' => $data['joined'],
                'meta' => [
                    'name' => $data['name'],
                    'dept' => $data['dept'],
                    'ext' => $data['ext'] ?? '',
                    'monthly' => (int) $data['monthly'],
                    'joined' => $data['joined'],
                    'prospect_id' => $prospect?->id,
                ],
            ], $request->user());

            $transaction->update(['meta' => array_merge($transaction->meta, [
                'source' => $prospect ? 'Membership drive' : 'Register',
            ])]);

            return $transaction;
        });

        ActivityLog::record(
            $request->user(),
            'Sent the registration of '.$data['name'].' for approval',
            $transaction->ref
        );

        return redirect()->route('pages.directory')
            ->with('status', $data['name'].' is with the Chairperson for approval');
    }

    public function update(Request $request, Member $member)
    {
        $data = $this->validated($request, joining: false);

        $member->update([
            'name' => $data['name'],
            'department' => $data['dept'],
            'extension' => $data['ext'] ?? '',
            'monthly_pledge' => (int) $data['monthly'],
        ]);

        ActivityLog::record($request->user(), 'Updated the register entry of '.$member->name, $member->code);

        return redirect()->route('pages.directory', ['status' => 'all'])
            ->with('status', $member->name.' updated');
    }

    /**
     * An exit notice is the Secretary telling the Treasurer to prepare a settlement. The member stays
     * on the register and their savings stay in the books until the settlement itself is posted.
     */
    public function flagExit(Request $request, Member $member)
    {
        abort_unless($member->isActive(), 422, 'This member has already left.');

        $this->check(
            $request,
            [
                'note' => ['required', 'string', 'max:200'],
                'exit_on' => ['nullable', 'date'],
            ],
            ['note' => 'note for the Treasurer', 'exit_on' => 'last day'],
            [],
            'dlg-exitnotice-'.$member->id
        );

        $data = $request->only(['note', 'exit_on']);

        $member->update([
            'exit_flagged' => true,
            'exit_note' => $data['note'],
            'exit_on' => $data['exit_on'] ?: now()->toDateString(),
        ]);

        ActivityLog::record($request->user(), 'Recorded an exit notice for '.$member->name, $member->code);

        return redirect()->route('pages.directory', ['status' => 'active'])
            ->with('status', 'Exit notice recorded for '.$member->name);
    }

    public function unflagExit(Request $request, Member $member)
    {
        $member->update(['exit_flagged' => false, 'exit_note' => null, 'exit_on' => null]);

        ActivityLog::record($request->user(), 'Cancelled the exit notice for '.$member->name, $member->code);

        return redirect()->route('pages.directory', ['status' => 'active'])
            ->with('status', 'Exit notice for '.$member->name.' cancelled');
    }

    /**
     * Both forms ask the same four questions; only the registration adds a joining date. A name
     * already on the register is stopped here rather than sent to the Chairperson to reject.
     */
    private function validated(Request $request, bool $joining): array
    {
        $name = ['required', 'string', 'max:120'];
        $name[] = $joining
            ? Rule::unique('members', 'name')
            : Rule::unique('members', 'name')->ignore($request->route('member'));

        $rules = [
            'name' => $name,
            'dept' => ['required', Rule::in(Member::DEPARTMENTS)],
            'ext' => ['nullable', 'string', 'max:12'],
            'monthly' => ['required', 'integer', 'min:'.Policy::get('min_monthly')],
            'joined' => [$joining ? 'required' : 'nullable', 'date'],
        ];

        if ($joining) {
            $rules['prospect_id'] = ['nullable', 'integer', 'exists:prospects,id'];
        }

        return $this->check(
            $request,
            $rules,
            [
                'name' => 'full name',
                'dept' => 'department',
                'ext' => 'office extension',
                'monthly' => 'monthly pledge',
                'joined' => 'joining date',
            ],
            [
                'name.unique' => 'That name is already on the register.',
                'monthly.min' => 'The pledge cannot be under '.\App\Support\Format::ugx(Policy::get('min_monthly')).'.',
            ],
            $joining ? 'dlg-addmember' : 'dlg-editmember-'.$request->route('member')
        );
    }

    /**
     * A form that came back wrong is opened again with the words beside the boxes, so the officer is
     * not left hunting for the button a second time.
     */
    private function check(Request $request, array $rules, array $labels, array $messages, string $modal): array
    {
        $validator = Validator::make($request->all(), $rules, $messages, $labels);

        if ($validator->fails()) {
            session()->flash('open_modal', $modal);

            throw new ValidationException($validator);
        }

        return $validator->validated();
    }
}
