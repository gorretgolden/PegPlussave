@props(['options', 'current', 'param' => 'tab', 'label' => null])
<div class="tabs" role="tablist"@if($label) aria-label="{{ $label }}" @endif>
    @foreach ($options as $value => $text)
        <a class="tab {{ $value === $current ? 'on' : '' }}" role="tab"
           aria-selected="{{ $value === $current ? 'true' : 'false' }}"
           href="{{ request()->fullUrlWithQuery([$param => $value]) }}">{!! $text !!}</a>
    @endforeach
</div>
