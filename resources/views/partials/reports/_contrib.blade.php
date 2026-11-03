{{-- The savings record of every member who was in the group during the year, month against month. A
     gap is only a gap when the member could have saved; the months before joining and after leaving
     are left blank so the eye counts the missed ones correctly. --}}
<div class="sec">
    <div class="sub">
        Monthly savings by member in {{ $year }}. Minimum {{ \App\Support\Format::ugx($minimum) }} a month.
    </div>
    <div class="sec-r">
        <x-legend :items="[
            ['var(--ok-soft)', 'Met minimum'],
            ['var(--warn-soft)', 'Below minimum'],
            ['var(--info-soft)', 'Being checked'],
            ['var(--bad-soft)', 'Missed'],
        ]" />
        @include('partials.reports._csv')
    </div>
</div>

<div class="kpis">
    <x-kpi
        :label="$month.' compliance'"
        :value="$percent.'%'"
        :sub="$saved.' of '.$ofActive.' saved'" />
    <x-kpi label="Being checked" :value="$checking" sub="Recorded, awaiting the Chairperson" />
    <x-kpi
        label="Yet to save"
        :value="$yetToSave"
        :sub="'Deadline '.$deadline"
        :tone="$yetToSave > 0 ? 'warn' : 'ok'" />
    <x-kpi :label="'Saved in '.$year" :value="\App\Support\Format::cash($savedThisYear)" />
</div>

<div class="tbl">
    <table class="heat">
        <thead>
        <tr>
            <th>Member</th>
            @foreach ($grid->first()['months'] ?? [] as $ym => $unused)
                <th class="num">{{ \App\Support\Format::monthTag($ym.'-01') }}</th>
            @endforeach
            <th class="num">Total</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($grid as $row)
            <tr class="click" data-open="dlg-member-{{ $row['member']->id }}">
                <td>
                    {{ $row['member']->name }}
                    @if ($row['member']->status === 'left') <x-pill tone="muted">Left</x-pill> @endif
                </td>
                @foreach ($row['months'] as $cell)
                    <td class="hc {{ $cell['state'] }}">{{ $cell['text'] }}</td>
                @endforeach
                <td class="num">{{ \App\Support\Format::money($row['total']) }}</td>
            </tr>
        @endforeach
        @if ($grid->isEmpty())
            <x-empty-row :cols="14" message="No member was in the group in this year." />
        @endif
        </tbody>
    </table>
</div>
