<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Minute;
use App\Models\User;
use App\Support\Refs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * The record of what the group decided. Minutes are the Secretary's, and they are kept as written:
 * a resolution is quoted from here in a meeting, so the words are stored rather than re-derived.
 */
class MinuteController extends Controller
{
    public const KINDS = ['Committee', 'General'];

    public function index(Request $request)
    {
        return view('pages.minutes', [
            'page' => 'minutes',
            'minutes' => Minute::query()->ordered()->with('author:id,name')->get(),
            'can' => $request->user()->can('record-minutes'),
            'kinds' => self::KINDS,
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:150'],
            'date' => ['required', 'date'],
            'kind' => ['required', 'in:'.implode(',', self::KINDS)],
            'attendance' => ['nullable', 'string', 'max:120'],
            'summary' => ['required', 'string', 'max:4000'],
            'decisions' => ['nullable', 'string', 'max:4000'],
        ], [], [
            'title' => 'meeting',
            'date' => 'date',
            'kind' => 'kind',
            'attendance' => 'attendance',
            'summary' => 'summary',
            'decisions' => 'resolutions',
        ]);

        if ($validator->fails()) {
            session()->flash('open_modal', 'dlg-minutes');

            throw new ValidationException($validator);
        }

        $data = $validator->validated();

        $minute = Minute::create([
            'ref' => Refs::minute(),
            'title' => $data['title'],
            'date' => $data['date'],
            'kind' => $data['kind'],
            'attendance' => $data['attendance'] ?? '',
            'summary' => $data['summary'],
            'decisions' => $this->lines($data['decisions'] ?? ''),
            'created_by' => $request->user()->id,
        ]);

        ActivityLog::record($request->user(), 'Recorded minutes "'.$minute->title.'"', $minute->ref);

        return redirect()->route('pages.minutes')->with('status', 'Minutes saved');
    }

    public function destroy(Request $request, Minute $minute)
    {
        $title = $minute->title;
        $minute->delete();

        ActivityLog::record($request->user(), 'Removed minutes "'.$title.'"', $minute->ref);

        return redirect()->route('pages.minutes')->with('status', 'Minutes removed');
    }

    /**
     * One resolution per line, because that is how they are read out and agreed. Blank lines are
     * dropped so a trailing return does not print an empty bullet.
     *
     * @return array<int, string>
     */
    private function lines(string $text): array
    {
        return array_values(array_filter(
            array_map('trim', preg_split('/\R/', $text) ?: []),
            fn (string $line) => $line !== ''
        ));
    }
}
