{{-- The member has already paid; this box turns that claim into the deposit the books recognise. The
     figures come from the notice and are only changed here if the bank says something different. --}}
@php
    use App\Models\Transaction;
    use App\Support\Format;
    use App\Support\Policy;
@endphp

<div class="modal-back" id="dlg-pn-{{ $notice->id }}" hidden>
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="dlg-pn-{{ $notice->id }}-h">
        <div class="mdl-h">
            <h3 id="dlg-pn-{{ $notice->id }}-h">Record the deposit {{ $notice->ref }}</h3>
            <button type="button" class="icon-btn" data-close-modal aria-label="Close"><x-icon name="x" /></button>
        </div>
        <div class="mdl-b">
            <form method="POST" action="{{ route('treasury.deposit') }}" class="form" data-reset>
                @csrf
                <input type="hidden" name="dep_notice" value="{{ $notice->id }}">
                <p class="muted small">{{ $notice->member?->name }} says this was paid on
                    {{ Format::date($notice->paid_on?->toDateString()) }}. Recording it makes a normal
                    deposit for the Chairperson to check.</p>
                <div class="field">
                    <label for="dn-m">Member</label>
                    <select id="dn-m" name="dep_member">
                        @foreach ($members as $member)
                            <option value="{{ $member->id }}" @selected($member->id === $notice->member_id)>
                                {{ $member->code }} · {{ $member->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="grid2">
                    <div class="field">
                        <label for="dn-a">Amount (UGX)</label>
                        <input id="dn-a" name="dep_amount" type="number" min="{{ Policy::get('min_monthly') }}" step="1000"
                               required value="{{ $notice->amount }}">
                        @error('dep_amount')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="field">
                        <label for="dn-mo">Month covered</label>
                        <input id="dn-mo" name="dep_month" type="month" required value="{{ $notice->month }}">
                    </div>
                    <div class="field">
                        <label for="dn-d">Value date</label>
                        <input id="dn-d" name="dep_date" type="date" required value="{{ $notice->paid_on?->toDateString() }}">
                    </div>
                    <div class="field">
                        <label for="dn-c">Channel</label>
                        <select id="dn-c" name="dep_channel">
                            @foreach (Transaction::CHANNELS as $channel)
                                <option @selected($channel === $notice->channel)>{{ $channel }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="field">
                    <label for="dn-r">Reference</label>
                    <input id="dn-r" name="dep_ref" value="{{ $notice->reference }}" placeholder="Mobile money or bank reference">
                </div>
                <x-maker-note />
                <div class="form-actions">
                    <button type="button" class="btn" data-close-modal>Cancel</button>
                    <button class="btn primary" type="submit">Submit for checking</button>
                </div>
            </form>
        </div>
    </div>
</div>
