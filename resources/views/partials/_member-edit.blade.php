{{-- Detail corrections are the Secretary's, and they do not go through the Chairperson: the register
     is their book. The pledge changes what a member is expected to save from the next month, never
     what has already been posted. --}}
<div class="modal-back" id="dlg-editmember-{{ $member->id }}" hidden>
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="dlg-editmember-{{ $member->id }}-h">
        <div class="mdl-h">
            <h3 id="dlg-editmember-{{ $member->id }}-h">Edit {{ $member->name }}</h3>
            <button type="button" class="icon-btn" data-close-modal aria-label="Close"><x-icon name="x" /></button>
        </div>
        <div class="mdl-b">
            <form method="POST" action="{{ route('directory.update', $member) }}" class="form">
                @csrf
                @method('PUT')

                <div class="grid2">
                    <div class="field">
                        <label for="em-n-{{ $member->id }}">Full name</label>
                        <input id="em-n-{{ $member->id }}" name="name" required maxlength="120"
                               value="{{ old('name', $member->name) }}">
                        @error('name')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="field">
                        <label for="em-d-{{ $member->id }}">Department</label>
                        <select id="em-d-{{ $member->id }}" name="dept" required>
                            @foreach ($departments as $option)
                                <option value="{{ $option }}" @selected(old('dept', $member->department) === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                        @error('dept')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="field">
                        <label for="em-e-{{ $member->id }}">Extension</label>
                        <input id="em-e-{{ $member->id }}" name="ext" maxlength="12" value="{{ old('ext', $member->extension) }}">
                        @error('ext')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="field">
                        <label for="em-p-{{ $member->id }}">Monthly pledge (UGX)</label>
                        <input id="em-p-{{ $member->id }}" name="monthly" type="number" min="{{ $minimum }}" step="10000"
                               inputmode="numeric" required value="{{ old('monthly', $member->monthly_pledge) }}">
                        @error('monthly')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                </div>

                <p class="muted small">The joining date stays as it was posted. If it is wrong, the Chairperson corrects it against the registration entry so the books keep the change.</p>

                <div class="form-actions">
                    <button type="button" class="btn" data-close-modal>Cancel</button>
                    <button class="btn primary" type="submit">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
