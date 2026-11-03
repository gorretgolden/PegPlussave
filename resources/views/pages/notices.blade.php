{{-- The whole board. The dashboard shows three of these; this is the list a member goes down when
     they want the earlier ones, so it keeps the same order: pinned first, then newest. --}}
@extends('layouts.peg')

@section('content')
    @include('partials._notice-board', [
        'title' => $notices->count().' '.str('notice')->plural($notices->count()),
        'sub' => 'Published by the Spokesman for all members',
        'notices' => $notices,
        'manage' => $can,
        'noAll' => true,
        'post' => $can,
    ])
@endsection

@section('modals')
    @if ($can)
        <div class="modal-back" id="dlg-notice" hidden>
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="dlg-notice-h">
                <div class="mdl-h">
                    <h3 id="dlg-notice-h">Post a notice</h3>
                    <button type="button" class="icon-btn" data-close-modal aria-label="Close"><x-icon name="x" /></button>
                </div>
                <div class="mdl-b">
                    <form method="POST" action="{{ route('notices.store') }}" class="form" data-reset>
                        @csrf
                        <p class="muted small">Every signed-in member reads this board, so a notice is written for the group rather than for one person.</p>
                        <div class="field">
                            <label for="no-t">Title</label>
                            <input id="no-t" name="title" required maxlength="150" value="{{ old('title') }}">
                            @error('title')<div class="field-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="field">
                            <label for="no-b">Message</label>
                            <textarea id="no-b" name="body" required rows="5" maxlength="2000">{{ old('body') }}</textarea>
                            @error('body')<div class="field-error">{{ $message }}</div>@enderror
                        </div>
                        <label class="small"><input type="checkbox" name="pinned" value="1" @checked(old('pinned'))> Pin to the top of the board</label>
                        <div class="form-actions">
                            <button type="button" class="btn" data-close-modal>Cancel</button>
                            <button class="btn primary" type="submit">Publish</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endsection
