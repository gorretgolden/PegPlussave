<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Member;
use App\Models\User;
use App\Support\Brand;
use App\Support\PermissionCatalog;
use App\Support\Policy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Accounts and the numbers the group is run by. This is the one desk that can change the rules, so
 * it is deliberately kept away from the money: the System Owner opens no savings book and reads no
 * balances. Policy changes are written down, because "who moved the rate" is the same question as
 * "who moved the cash".
 */
class AdminController extends Controller
{
    public const TABS = [
        'users' => 'Users & roles',
        'settings' => 'Policy settings',
        'branding' => 'Branding',
    ];

    public function index(Request $request)
    {
        $tab = $request->input('tab', 'users');
        $tab = array_key_exists($tab, self::TABS) ? $tab : 'users';

        return view('pages.admin', [
            'page' => 'admin',
            'tab' => $tab,
            'tabs' => self::TABS,
            'accounts' => User::query()->with(['member:id,name,department', 'roles'])->orderBy('name')->get(),
            'policy' => Policy::all(),
            'departments' => Member::DEPARTMENTS,
            'roles' => array_merge(['member' => 'Member'], array_map(
                fn (string $role) => PermissionCatalog::label($role),
                PermissionCatalog::COMMITTEE
            )),
            'members' => Member::query()->whereDoesntHave('user')->orderBy('name')->get(['id', 'name', 'code']),
            'logo' => Brand::logo(),
        ]);
    }

    /**
     * The settings are saved as the group reads them, not as the code names them: each label is
     * written once, here, and the form reproduces it.
     */
    public function settings(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'min_monthly' => ['required', 'integer', 'min:0'],
            'contribution_day' => ['required', 'integer', 'min:1', 'max:31'],
            'payout_month' => ['required', 'integer', 'min:1', 'max:12'],
            'distribution_share' => ['required', 'integer', 'min:0', 'max:100'],
            'reserve_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'loan_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'loan_method' => ['required', Rule::in(['flat', 'reducing'])],
            'max_months' => ['required', 'integer', 'min:1', 'max:24'],
            'loan_multiple' => ['required', 'numeric', 'min:1', 'max:10'],
            'min_saving_months' => ['required', 'integer', 'min:0', 'max:24'],
            'patron_threshold' => ['required', 'integer', 'min:0'],
            'band_1_up_to' => ['required', 'integer', 'min:0'],
            'band_1_months' => ['required', 'integer', 'min:1', 'max:24'],
            'band_2_up_to' => ['required', 'integer', 'min:0'],
            'band_2_months' => ['required', 'integer', 'min:1', 'max:24'],
            'band_3_months' => ['required', 'integer', 'min:1', 'max:24'],
        ], [], [
            'min_monthly' => 'minimum monthly saving',
            'contribution_day' => 'contribution deadline',
            'payout_month' => 'annual interest payout month',
            'distribution_share' => 'share of net loan interest paid to savers',
            'reserve_percent' => 'liquidity reserve',
            'loan_rate' => 'interest rate',
            'loan_method' => 'interest method',
            'max_months' => 'longest repayment period',
            'loan_multiple' => 'loan limit',
            'min_saving_months' => 'months saved before borrowing',
            'patron_threshold' => 'Patron authorises loans above',
            'band_1_up_to' => 'first band ceiling',
            'band_1_months' => 'first band months',
            'band_2_up_to' => 'second band ceiling',
            'band_2_months' => 'second band months',
            'band_3_months' => 'longest band months',
        ]);

        $validator->after(function ($validator) use ($request) {
            if ((int) $request->input('distribution_share') + (int) $request->input('reserve_percent') > 100) {
                $validator->errors()->add(
                    'reserve_percent',
                    'The share paid to savers and the liquidity reserve cannot add up to more than 100%.'
                );
            }

            if ((int) $request->input('band_1_up_to') >= (int) $request->input('band_2_up_to')) {
                $validator->errors()->add('band_2_up_to', 'The second band has to reach higher than the first.');
            }
        });

        if ($validator->fails()) {
            throw ValidationException::withMessages(
                collect($validator->errors()->messages())->map(fn (array $lines) => $lines[0])->all()
            );
        }

        $data = $validator->validated();

        Policy::putMany([
            'min_monthly' => (int) $data['min_monthly'],
            'contribution_day' => (int) $data['contribution_day'],
            'payout_month' => (int) $data['payout_month'],
            'distribution_share' => (int) $data['distribution_share'],
            'reserve_percent' => (int) $data['reserve_percent'],
            'loan_rate' => (float) $data['loan_rate'],
            'loan_method' => $data['loan_method'],
            'max_months' => (int) $data['max_months'],
            'loan_multiple' => (float) $data['loan_multiple'],
            'min_saving_months' => (int) $data['min_saving_months'],
            'patron_threshold' => (int) $data['patron_threshold'],
            'tenure_bands' => [
                ['upTo' => (int) $data['band_1_up_to'], 'months' => (int) $data['band_1_months']],
                ['upTo' => (int) $data['band_2_up_to'], 'months' => (int) $data['band_2_months']],
                ['upTo' => null, 'months' => (int) $data['band_3_months']],
            ],
        ]);

        ActivityLog::record($request->user(), 'Changed the PEG policy settings', 'POLICY');

        return redirect()->route('pages.admin', ['tab' => 'settings'])
            ->with('status', 'Settings saved. New entries use them; booked loans keep their terms.');
    }

    public function logo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'logo' => ['required', 'file', 'mimes:png,jpg,jpeg,svg,webp', 'max:600'],
        ], [
            'logo.max' => 'The logo has to be under 600 KB.',
            'logo.mimes' => 'Use a PNG, SVG, JPEG or WebP file.',
        ], ['logo' => 'logo']);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $file = $request->file('logo');

        Brand::setLogo('data:'.$file->getMimeType().';base64,'.base64_encode(file_get_contents($file->getRealPath())));

        ActivityLog::record($request->user(), 'Uploaded a new PlusSave logo', 'BRAND');

        return redirect()->route('pages.admin', ['tab' => 'branding'])->with('status', 'Logo updated');
    }

    public function clearLogo(Request $request)
    {
        Brand::setLogo(null);

        ActivityLog::record($request->user(), 'Returned to the PlusSave wordmark', 'BRAND');

        return redirect()->route('pages.admin', ['tab' => 'branding'])->with('status', 'Wordmark restored');
    }

    /**
     * An account for somebody who is not on the register yet: a patron or an accountant who is not a
     * saving member, or a member whose login has to be made by hand.
     */
    public function storeUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'max:40', 'alpha_dash', Rule::unique('users', 'username')],
            'role' => ['required', Rule::in(array_keys(PermissionCatalog::ROLES))],
            'member_id' => ['nullable', 'integer', Rule::unique('users', 'member_id')],
            'password' => ['required', 'string', 'min:8'],
        ], [
            'username.unique' => 'That sign-in name is taken.',
            'member_id.unique' => 'That member already has an account.',
            'alpha_dash' => 'Use letters, numbers, dashes or underscores only.',
        ], [
            'name' => 'name',
            'username' => 'sign-in name',
            'role' => 'PEG role',
            'member_id' => 'member',
            'password' => 'password',
        ]);

        if ($validator->fails()) {
            session()->flash('open_modal', 'dlg-adduser');

            throw new ValidationException($validator);
        }

        $data = $validator->validated();

        $user = User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'password' => $data['password'],
            'member_id' => $data['member_id'] ?? null,
            'status' => 'active',
        ]);

        $user->assignRole($data['role']);

        ActivityLog::record($request->user(), 'Opened an account for '.$user->name.' as '.PermissionCatalog::label($data['role']), $user->username);

        return redirect()->route('pages.admin', ['tab' => 'users'])
            ->with('status', $user->name.' can sign in as '.$user->username);
    }

    /**
     * Changing an office changes what the person can open, not what the books already say they did.
     * The stamp on each entry keeps the office held on the day.
     */
    public function role(Request $request, User $user)
    {
        $role = $request->input('role');
        abort_unless(array_key_exists($role, PermissionCatalog::ROLES), 422, 'That is not a PEG role.');

        if ($user->id === $request->user()->id && $role !== 'admin') {
            throw ValidationException::withMessages(['role' => 'You cannot take your own office away.']);
        }

        $before = $user->roleLabel();
        $user->syncRoles([$role]);

        ActivityLog::record(
            $request->user(),
            'Changed the PEG role of '.$user->name.' from '.$before.' to '.PermissionCatalog::label($role),
            $user->username
        );

        return redirect()->route('pages.admin', ['tab' => 'users'])
            ->with('status', $user->name.' is now '.PermissionCatalog::label($role).'. It takes effect at their next sign-in.');
    }

    public function status(Request $request, User $user)
    {
        $close = $user->isClosed();

        if (! $close && $user->id === $request->user()->id) {
            throw ValidationException::withMessages(['status' => 'You cannot close your own account.']);
        }

        $user->update(['status' => $close ? 'active' : 'closed']);

        ActivityLog::record(
            $request->user(),
            ($close ? 'Reopened' : 'Closed').' the account of '.$user->name,
            $user->username
        );

        return redirect()->route('pages.admin', ['tab' => 'users'])
            ->with('status', $user->name.($close ? ' can sign in again' : ' can no longer sign in'));
    }

    /**
     * The owner sets a temporary password and tells the member to change it. The system never emails
     * a password, and nothing here reveals one that is already stored.
     */
    public function password(Request $request, User $user)
    {
        $validator = Validator::make($request->all(), [
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], ['password.confirmed' => 'The two passwords do not match.'], ['password' => 'new password']);

        if ($validator->fails()) {
            session()->flash('open_modal', 'dlg-password-'.$user->id);

            throw new ValidationException($validator);
        }

        $user->update(['password' => $validator->validated()['password']]);

        ActivityLog::record($request->user(), 'Reset the sign-in password for '.$user->name, $user->username);

        return redirect()->route('pages.admin', ['tab' => 'users'])
            ->with('status', 'Password set for '.$user->name.'. Tell them to change it after signing in.');
    }
}
