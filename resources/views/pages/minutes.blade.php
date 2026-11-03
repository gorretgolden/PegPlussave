{{-- The book of decisions. The newest meeting is opened because that is the one people come to read;
     the rest stay folded so the page is a list of meetings, not an essay. --}}
@extends('layouts.peg')

@section('content')
    <x-sec title="Meeting minutes" sub="Recorded by the Secretary">
        @if ($can)
            <x-slot:actions>
                <button class="btn primary" type="button" data-open="dlg-minutes"><x-icon name="plus" />Record minutes</button>
            </x-slot:actions>
        @endif
    </x-sec>

    <div class="stack">
        @forelse ($minutes as $minute)
            <details class="mins" @if ($loop->first) open @endif>
                <summary>
                    <span>
                        <b>{{ $minute->title }}</b>
                        <span class="cell-sub">{{ \App\Support\Format::date($minute->date->toDateString()) }}@if ($minute->attendance) &middot; {{ $minute->attendance }}@endif</span>
                    </span>
                    <x-pill :tone="$minute->kind === 'General' ? 'info' : 'ok'">{{ $minute->kind }}</x-pill>
                </summary>
                <div class="mins-b">
                    <p>{{ $minute->summary }}</p>

                    @if (! empty($minute->decisions))
                        <div>
                            <div class="lbl-s">Resolutions</div>
                            <ol>
                                @foreach ($minute->decisions as $decision)
                                    <li>{{ $decision }}</li>
                                @endforeach
                            </ol>
                        </div>
                    @endif

                    <div class="muted small">Recorded by {{ $minute->author?->name ?? 'the Secretary' }} &middot; {{ $minute->ref }}</div>

                    @if ($can)
                        <div class="form-actions">
                            <form method="POST" action="{{ route('minutes.destroy', $minute) }}" class="inline"
                                  data-confirm="Remove {{ $minute->title }}? The resolutions recorded with it go too.">
                                @csrf
                                @method('DELETE')
                                <button class="link-btn" type="submit">Remove</button>
                            </form>
                        </div>
                    @endif
                </div>
            </details>
        @empty
            <div class="panel empty">No minutes yet.</div>
        @endforelse
    </div>
@endsection

@section('modals')
    @if ($can)
        <div class="modal-back" id="dlg-minutes" hidden>
            <div class="modal wide" role="dialog" aria-modal="true" aria-labelledby="dlg-minutes-h">
                <div class="mdl-h">
                    <h3 id="dlg-minutes-h">Record meeting minutes</h3>
                    <button type="button" class="icon-btn" data-close-modal aria-label="Close"><x-icon name="x" /></button>
                </div>
                <div class="mdl-b">
                    <form method="POST" action="{{ route('minutes.store') }}" class="form" data-reset>
                        @csrf
                        <div class="grid2">
                            <div class="field">
                                <label for="mi-t">Meeting</label>
                                <input id="mi-t" name="title" required maxlength="150"
                                       placeholder="e.g. Committee meeting, November 2026" value="{{ old('title') }}">
                                @error('title')<div class="field-error">{{ $message }}</div>@enderror
                            </div>
                            <div class="field">
                                <label for="mi-d">Date</label>
                                <input id="mi-d" name="date" type="date" required value="{{ old('date', now()->toDateString()) }}">
                                @error('date')<div class="field-error">{{ $message }}</div>@enderror
                            </div>
                            <div class="field">
                                <label for="mi-k">Kind</label>
                                <select id="mi-k" name="kind">
                                    @foreach ($kinds as $kind)
                                        <option @selected(old('kind', 'Committee') === $kind)>{{ $kind }}</option>
                                    @endforeach
                                </select>
                                @error('kind')<div class="field-error">{{ $message }}</div>@enderror
                            </div>
                            <div class="field">
                                <label for="mi-a">Attendance</label>
                                <input id="mi-a" name="attendance" maxlength="120"
                                       placeholder="e.g. 7 of 7 committee members" value="{{ old('attendance') }}">
                                @error('attendance')<div class="field-error">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="field">
                            <label for="mi-s">Summary</label>
                            <textarea id="mi-s" name="summary" required rows="4" maxlength="4000">{{ old('summary') }}</textarea>
                            @error('summary')<div class="field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="field">
                            <label for="mi-r">Resolutions (one per line)</label>
                            <textarea id="mi-r" name="decisions" rows="3" maxlength="4000">{{ old('decisions') }}</textarea>
                            @error('decisions')<div class="field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="form-actions">
                            <button type="button" class="btn" data-close-modal>Cancel</button>
                            <button class="btn primary" type="submit">Save minutes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endsection
