@php
    $u = auth()->user();
    $page = $page ?? 'dashboard';
    $crumb = \App\Support\Nav::label($page);
    $locked = \App\Support\Nav::locked($page);
    $logo = \App\Support\Brand::logo();
@endphp
<!doctype html>
<html lang="en" data-theme="{{ $u->theme ?: 'light' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $crumb }} · PlusSave PEG System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="{{ asset('css/peg.css') }}">
    @stack('styles')
</head>
<body @if (session('open_modal')) data-open="{{ session('open_modal') }}" @endif>
<div id="app">
    <div class="shell">
        <aside class="rail" id="rail" aria-label="Main menu">
            <div class="rail-brand">
                @if ($logo)
                    <div class="logo-img"><img src="{{ $logo }}" alt="PlusSave Financial Solutions"></div>
                @else
                    <div class="wordmark" aria-label="PlusSave Financial Solutions">
                        <span class="wm-1">Plus<b>Save</b></span>
                        <span class="wm-2">Financial Solutions</span>
                    </div>
                @endif
                <div class="rail-peg"><span class="peg-mark">PEG</span><span>PlusSave Employee Group</span></div>
            </div>

            <nav class="rail-nav">
                @foreach (\App\Support\Nav::forUser($u) as $group)
                    <div class="rail-group">
                        <div class="rail-label">{{ $group['group'] }}</div>
                        @foreach ($group['items'] as $item)
                            @php $badge = \App\Support\Nav::badge($u, $item[0]); @endphp
                            <a class="rail-item {{ $page === $item[0] ? 'on' : '' }}" href="{{ route('pages.'.$item[0]) }}">
                                <x-icon :name="$item[2]" />
                                <span>{{ $item[1] }}</span>
                                @if ($item[4])<span class="lock" title="Confidential"><x-icon name="lock" /></span>@endif
                                @if ($badge)<span class="rail-badge">{{ $badge }}</span>@endif
                            </a>
                        @endforeach
                    </div>
                @endforeach
            </nav>

            <div class="rail-foot">
                <span>Signed in as {{ $u->username }}</span>
                <span>System owner: Marvin Bategeka</span>
            </div>
        </aside>

        <div class="main">
            <header class="topbar">
                <button class="icon-btn menu-btn" data-menu aria-label="Open menu"><x-icon name="menu" /></button>
                <div class="tb-title">
                    <div class="tb-crumb">
                        PEG
                        @if ($locked)<span class="conf"><x-icon name="lock" />Confidential</span>@endif
                    </div>
                    <h1>{{ $crumb }}</h1>
                </div>
                <div class="tb-right">
                    <div class="me">
                        <span class="avatar" aria-hidden="true">{{ \App\Support\Format::initials($u->name) }}</span>
                        <span class="me-txt"><b>{{ $u->name }}</b><span>{{ $u->roleLabel() }}</span></span>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="icon-btn" type="submit" aria-label="Sign out" title="Sign out"><x-icon name="logout" /></button>
                    </form>
                </div>
            </header>

            <main class="content" id="content">
                @if (session('status.banner'))
                    <div class="warnbox"><x-icon name="info" /><span>{{ session('status.banner') }}</span></div>
                @endif
                @yield('content')
            </main>
        </div>
    </div>
</div>

<div class="scrim" id="scrim" hidden></div>

<div id="toast" class="toast {{ session('error') ? 'bad' : '' }}" @if (! session('status') && ! session('error')) hidden @endif>
    <x-icon :name="session('error') ? 'alert' : 'check'" />
    <span>{{ session('error') ?: session('status') }}</span>
</div>

@include('partials._member-modals')
@yield('modals')

<script src="{{ asset('js/peg.js') }}"></script>
@stack('scripts')
</body>
</html>
