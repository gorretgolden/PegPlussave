{{-- Registration is a request, not an act. The officer typing here is putting a name in front of the
     Chairperson; the PEG number and the login arrive only when that approval posts. --}}
<div class="modal-back" id="dlg-addmember" hidden>
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="dlg-addmember-h">
        <div class="mdl-h">
            <h3 id="dlg-addmember-h">Register a new member</h3>
            <button type="button" class="icon-btn" data-close-modal aria-label="Close"><x-icon name="x" /></button>
        </div>
        <div class="mdl-b">
            <form method="POST" action="{{ route('directory.store') }}" class="form" data-reset>
                @csrf
                @if ($prospect)
                    <input type="hidden" name="prospect_id" value="{{ $prospect->id }}">
                    <p class="muted small">Bringing {{ $prospect->name }} in from the membership drive. The Chairperson approves new members; once approved they get a PEG number and a login.</p>
                @else
                    <p class="muted small">The Chairperson approves new members. Once approved they get a PEG number and a login.</p>
                @endif

                <div class="grid2">
                    <div class="field">
                        <label for="am-n">Full name (surname first)</label>
                        <input id="am-n" name="name" required maxlength="120"
                               value="{{ old('name', $prospect?->name) }}">
                        @error('name')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="field">
                        <label for="am-d">Department</label>
                        <select id="am-d" name="dept" required>
                            @foreach ($departments as $option)
                                <option value="{{ $option }}"
                                        @selected(old('dept', $prospect?->department ?: 'Operations') === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                        @error('dept')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="field">
                        <label for="am-e">Office extension</label>
                        <input id="am-e" name="ext" placeholder="e.g. 163" value="{{ old('ext') }}">
                        @error('ext')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="field">
                        <label for="am-p">Monthly pledge (UGX)</label>
                        <input id="am-p" name="monthly" type="number" min="{{ $minimum }}" step="10000" inputmode="numeric"
                               required value="{{ old('monthly', $minimum * 2) }}">
                        @error('monthly')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="field">
                        <label for="am-j">Joining date</label>
                        <input id="am-j" name="joined" type="date" required
                               value="{{ old('joined', now()->toDateString()) }}">
                        @error('joined')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn" data-close-modal>Cancel</button>
                    <button class="btn primary" type="submit">Send for approval</button>
                </div>
            </form>
        </div>
    </div>
</div>
