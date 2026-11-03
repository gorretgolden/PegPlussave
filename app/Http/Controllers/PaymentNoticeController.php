<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\PaymentNotice;
use App\Models\Transaction;
use App\Support\Format;
use App\Support\Policy;
use App\Support\Refs;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The member side of the savings book. A notice is not money: it is a member telling the Treasurer
 * that money already moved through a channel the group does not control, so it is filed as a claim
 * and only becomes a deposit when the Treasurer records it and somebody else checks that entry.
 */
class PaymentNoticeController extends Controller
{
    public function store(Request $request)
    {
        $min = (int) Policy::get('min_monthly');

        $data = $request->validate([
            'pn_amount' => ['required', 'integer', 'min:'.$min],
            'pn_month' => ['required', 'date_format:Y-m'],
            'pn_paid_on' => ['required', 'date'],
            'pn_channel' => ['required', 'string', Rule::in(array_diff(Transaction::CHANNELS, ['Salary deduction']))],
            'pn_reference' => ['required', 'string', 'max:60'],
        ], [
            'pn_amount.min' => 'The minimum monthly saving is '.Format::ugx($min).'.',
            'pn_month.date_format' => 'Say which month this payment covers.',
            'pn_paid_on.required' => 'Say the date you paid.',
            'pn_reference.required' => 'Give the reference the payment came in under, so it can be traced.',
        ]);

        $memberId = $request->user()->member_id;

        // A duplicate claim is the one thing that makes the Treasurer's job harder, so it is refused
        // rather than queued: same month, same amount, still waiting to be recorded.
        $already = PaymentNotice::query()
            ->where('member_id', $memberId)
            ->where('month', $data['pn_month'])
            ->where('amount', (int) $data['pn_amount'])
            ->where('status', PaymentNotice::OPEN)
            ->exists();

        if ($already) {
            session()->flash('open_modal', 'dlg-paynotice');

            throw ValidationException::withMessages([
                'pn_amount' => 'You have already sent a notice for '.Format::ugx((int) $data['pn_amount']).' covering '
                    .Format::month($data['pn_month']).'. The Treasurer is working on it.',
            ]);
        }

        $notice = PaymentNotice::create([
            'ref' => Refs::paymentNotice(),
            'member_id' => $memberId,
            'amount' => (int) $data['pn_amount'],
            'month' => $data['pn_month'],
            'paid_on' => $data['pn_paid_on'],
            'channel' => $data['pn_channel'],
            'reference' => trim($data['pn_reference']),
            'status' => PaymentNotice::OPEN,
        ]);

        ActivityLog::record($request->user(), 'Sent payment notice '.Format::ugx($notice->amount), $notice->ref);

        return redirect()->route('pages.savings')
            ->with('status', 'Payment notice sent to the Treasurer');
    }

    /**
     * A notice is dismissed when the Treasurer decides it was never a saving of theirs, a duplicate,
     * or already counted in a deduction run. The member's claim stays on the record either way.
     */
    public function dismiss(Request $request, PaymentNotice $paymentNotice)
    {
        if ($paymentNotice->status !== PaymentNotice::OPEN) {
            return back()->with('status', "{$paymentNotice->ref} has already been dealt with.");
        }

        $paymentNotice->update(['status' => PaymentNotice::DISMISSED]);

        ActivityLog::record($request->user(), "Dismissed payment notice {$paymentNotice->ref}", $paymentNotice->ref);

        return redirect()->route('pages.treasury', ['tab' => 'savings'])
            ->with('status', "{$paymentNotice->ref} dismissed");
    }
}
