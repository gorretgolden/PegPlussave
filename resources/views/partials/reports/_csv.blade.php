{{-- The two ways out of a report. Copy puts the same rows the screen shows onto the clipboard for a
     minute in a meeting; the file is the one to keep. --}}
<button class="btn sm" type="button" data-csv="{{ json_encode($csv) }}"><x-icon name="copy" />Copy as CSV</button>
<a class="btn sm" href="{{ route('reports.excel', ['report' => $tab, 'year' => $year]) }}">Download CSV</a>
