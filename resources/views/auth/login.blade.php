@php $s = \App\Support\Policy::all(); $logo = \App\Support\Brand::logo(); @endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Sign in · PlusSave PEG System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="{{ asset('css/peg.css') }}">
</head>
<body>
<div class="login">
    <section class="login-brand">
        <div>
            @if ($logo)
                <div class="logo-img"><img src="{{ $logo }}" alt="PlusSave Financial Solutions"></div>
            @else
                <div class="wordmark" aria-label="PlusSave Financial Solutions">
                    <span class="wm-1">Plus<b>Save</b></span>
                    <span class="wm-2">Financial Solutions</span>
                </div>
            @endif
        </div>
        <div class="lb-mid">
            <span class="peg-big">PEG</span>
            <h1>PlusSave Employee Group</h1>
            <p>Staff savings and loans. The Treasurer makes every entry, the Chairperson checks it, and the Patron authorises the exceptional ones.</p>
        </div>
        <dl class="lb-rules">
            <div><dt>{{ \App\Support\Format::ugx($s['min_monthly']) }}</dt><dd>minimum saving each month</dd></div>
            <div><dt>{{ rtrim(rtrim(number_format($s['loan_rate'], 2, '.', ''), '0'), '.') }}% a month</dt><dd>loan interest, repaid within {{ $s['max_months'] }} months</dd></div>
            <div><dt>Every month</dt><dd>interest shared to each saver</dd></div>
            <div><dt>Principal held</dt><dd>until you leave PlusSave</dd></div>
        </dl>
    </section>

    <section class="login-form">
        <form method="POST" action="{{ route('login') }}" class="lf" autocomplete="off">
            @csrf
            <div>
                <h2>Sign in</h2>
                <p class="muted">Use your PEG username and password.</p>
            </div>
            <div class="field">
                <label for="l-user">Username</label>
                <input id="l-user" name="username" required autocomplete="username" value="{{ old('username') }}" @error('username') aria-invalid="true" class="field-invalid" @enderror>
                @error('username')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="l-pass">Password</label>
                <input id="l-pass" name="password" type="password" required autocomplete="current-password" @error('password') aria-invalid="true" class="field-invalid" @enderror>
                @error('password')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            @if ($errors->has('login'))
                <div class="form-err">{{ $errors->first('login') }}</div>
            @endif
            <button class="btn primary block" type="submit">Sign in</button>
            <label class="field" style="display:flex;gap:8px;align-items:center;font-size:13px">
                <input type="checkbox" name="remember" value="1" @checked(old('remember'))> Keep me signed in on this device
            </label>
        </form>
    </section>
</div>
</body>
</html>
