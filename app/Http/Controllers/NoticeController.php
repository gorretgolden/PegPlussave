<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Notice;
use App\Support\Refs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * What the group is told. A notice is the Spokesman's, and the board is the same list on every desk
 * that shows it: pinning or removing here changes what everybody else reads.
 */
class NoticeController extends Controller
{
    public function index(Request $request)
    {
        $notices = Notice::query()->ordered()->with('author:id,name')->get();

        return view('pages.notices', [
            'page' => 'notices',
            'notices' => $notices,
            'can' => $request->user()->can('post-notice'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->check($request, 'dlg-notice');

        $notice = Notice::create([
            'ref' => Refs::notice(),
            'title' => $data['title'],
            'body' => $data['body'],
            'pinned' => (bool) ($data['pinned'] ?? false),
            'posted_on' => now()->toDateString(),
            'author_id' => $request->user()->id,
            'author_role' => $request->user()->roleKey(),
        ]);

        ActivityLog::record($request->user(), 'Posted notice "'.$notice->title.'"', $notice->ref);

        return redirect()->route('pages.notices')->with('status', 'Notice posted to the board');
    }

    /**
     * Pinning is not a decoration on somebody else's notice; it says this one matters more than the
     * newer ones under it, so the change is recorded against the notice itself.
     */
    public function pin(Request $request, Notice $notice)
    {
        $notice->update(['pinned' => ! $notice->pinned]);

        ActivityLog::record(
            $request->user(),
            ($notice->pinned ? 'Pinned' : 'Unpinned').' notice "'.$notice->title.'"',
            $notice->ref
        );

        return redirect()->route('pages.notices')
            ->with('status', $notice->pinned ? 'Pinned to the top of the board' : 'Unpinned');
    }

    public function destroy(Request $request, Notice $notice)
    {
        $title = $notice->title;
        $notice->delete();

        ActivityLog::record($request->user(), 'Removed notice "'.$title.'"', $notice->ref);

        return redirect()->route('pages.notices')->with('status', 'Notice removed');
    }

    private function check(Request $request, string $modal): array
    {
        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:2000'],
            'pinned' => ['nullable'],
        ], [], ['title' => 'title', 'body' => 'message']);

        if ($validator->fails()) {
            session()->flash('open_modal', $modal);

            throw new ValidationException($validator);
        }

        return $validator->validated();
    }
}
