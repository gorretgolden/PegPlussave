/* The panels under a money field that move while it is being typed. Everything they show is the
   same maths the server runs before it accepts the entry, so a form never promises a figure the
   books will not agree to. With scripting off the panels stay empty and the server still answers:
   nothing here is required to send an entry. */
(function () {
  'use strict';

  // More than one screen can ask for this file; the panels are bound once or a field would answer
  // the same keystroke twice.
  if (window.pegLiveBound) return;
  window.pegLiveBound = true;

  var ICONS = {
    check: '<path d="M4 12.5l5 5L20 6.5"/>',
    x: '<path d="M6 6l12 12M18 6L6 18"/>',
    alert: '<path d="M12 3l10 18H2z"/><path d="M12 10v5M12 18h.01"/>'
  };

  function svg(name) {
    return '<svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"'
      + ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + (ICONS[name] || '') + '</svg>';
  }

  function money(n) { return Math.round(n || 0).toLocaleString('en-US'); }
  function ugx(n) { return 'UGX ' + money(n); }

  var MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

  function iso(d) {
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
  }

  function parse(s) { var p = String(s).slice(0, 10).split('-'); return new Date(+p[0], +p[1] - 1, +p[2] || 1); }

  function addMonths(date, n) {
    var day = date.getDate();
    var moved = new Date(date.getFullYear(), date.getMonth() + n, 1);
    var last = new Date(moved.getFullYear(), moved.getMonth() + 1, 0).getDate();
    moved.setDate(Math.min(day, last));
    return moved;
  }

  function fdate(s) { var d = parse(s); return d.getDate() + ' ' + MONTHS[d.getMonth()] + ' ' + d.getFullYear(); }

  function fmon(ym) { var p = String(ym).split('-'); return MONTHS[(+p[1] || 1) - 1] + ' ' + p[0]; }

  /* The definition lists the server-rendered panels use, so a figure fetched from the books and a
     figure worked out here look identical. */
  function kv(pairs) {
    return '<dl class="kv">' + pairs.filter(Boolean).map(function (pair) {
      return '<div><dt>' + pair[0] + '</dt><dd>' + pair[1] + '</dd></div>';
    }).join('') + '</dl>';
  }

  function field(f, key, fallback) {
    var name = f.dataset[key] || fallback;
    return f.querySelector('[name="' + name + '"]');
  }

  // A box that is closed is not worth a query. The figures are asked for the first time the box is
  // opened, and after that only when a field inside it moves.
  function onOpen(f, run) {
    document.addEventListener('peg:opened', function (event) {
      if (event.detail && event.detail.contains(f)) run();
    });
  }

  /* The two ways PEG splits a loan, matching LoanSchedule::build to the shilling. */
  function schedule(principal, ratePercent, months, method, startDate) {
    if (principal <= 0 || months <= 0) return [];
    var rate = ratePercent / 100, rows = [], i;

    if (method === 'reducing') {
      var balance = principal;
      var instalment = rate === 0
        ? Math.round(principal / months)
        : Math.round(principal * rate / (1 - Math.pow(1 + rate, -months)));

      for (i = 1; i <= months; i++) {
        var interest = Math.round(balance * rate);
        var part = i === months ? balance : Math.round(instalment - interest);
        balance -= part;
        rows.push({ due: iso(addMonths(startDate, i)), principal: part, interest: interest, total: part + interest });
      }

      return rows;
    }

    var eachPrincipal = Math.round(principal / months);
    var eachInterest = Math.round(principal * rate);

    for (i = 1; i <= months; i++) {
      var lastPart = i === months ? principal - eachPrincipal * (months - 1) : eachPrincipal;
      rows.push({
        due: iso(addMonths(startDate, i)),
        principal: lastPart,
        interest: eachInterest,
        total: lastPart + eachInterest
      });
    }

    return rows;
  }

  function maxTenure(amount, policy) {
    var bands = policy.bands || [];

    for (var i = 0; i < bands.length; i++) {
      if (bands[i].upTo === null || amount <= bands[i].upTo) {
        return Math.min(bands[i].months, policy.maxMonths);
      }
    }

    return policy.maxMonths;
  }

  /* The six questions a booking has to answer, plus the treasury one. Same order, same words as
     Eligibility::checks, because the officer is reading the same list on both sides of the screen. */
  function checks(fact, amount, months, policy, treasury) {
    if (!fact) return [];
    var limit = fact.balance * policy.multiple;
    var mx = maxTenure(amount, policy);
    var out = [
      {
        label: 'Active PEG member',
        ok: fact.active && !fact.exit,
        detail: fact.exit ? 'Exit notice recorded' : (fact.active ? 'Active' : 'Left')
      },
      {
        label: 'Saved for at least ' + policy.minMonthsSaved + ' months',
        ok: fact.months >= policy.minMonthsSaved,
        detail: fact.months + ' months saved'
      },
      {
        label: 'No running loan or open application',
        ok: fact.runningRefs.length === 0,
        detail: fact.runningRefs.length ? fact.runningRefs.join(', ') : 'None'
      },
      {
        label: 'Within ' + policy.multiple + '× savings',
        ok: amount > 0 && amount <= limit,
        detail: 'Limit ' + ugx(limit)
      },
      {
        label: 'Tenure within policy',
        ok: months >= 1 && months <= mx,
        detail: 'Up to ' + mx + ' months for this amount'
      },
      {
        label: 'Contributions up to date',
        ok: fact.savedLast,
        soft: true,
        detail: fact.savedLast ? policy.lastMonth + ' saved' : 'No saving for ' + policy.lastMonth
      }
    ];

    if (treasury && policy.available !== null) {
      out.push({
        label: 'Funds available in PEG',
        ok: amount <= policy.available,
        detail: 'Available ' + ugx(policy.available)
      });
    }

    return out;
  }

  function checksHTML(list) {
    return '<ul class="checks">' + list.map(function (c) {
      return '<li class="' + (c.ok ? 'ok' : (c.soft ? 'soft' : 'bad')) + '">'
        + svg(c.ok ? 'check' : (c.soft ? 'alert' : 'x'))
        + '<span>' + c.label + '</span><em>' + (c.detail || '') + '</em></li>';
    }).join('') + '</ul>';
  }

  function figure(label, value) {
    return '<div><span class="lbl-s">' + label + '</span><b class="mono">' + value + '</b></div>';
  }

  /* A panel whose numbers are the entry's own maths, fetched as the markup it will be booked in.
     Only one request is ever in flight, and a late answer is dropped if the field moved again. */
  function remote(url, render) {
    var token = 0;

    return function () {
      var mine = ++token;

      fetch(url(), { headers: { 'Accept': 'application/json' } })
        .then(function (response) { return response.ok ? response.json() : null; })
        .then(function (data) { if (mine === token && data) render(data); })
        .catch(function () { /* the panel is a courtesy; the server is the authority */ });
    };
  }

  function loanForm(f) {
    var policy = JSON.parse(f.dataset.policy || '{}');
    var facts = JSON.parse(f.dataset.facts || '{}');
    var amount = field(f, 'liveAmount', 'amount');
    var months = field(f, 'liveMonths', 'months');
    var preview = f.querySelector('.loan-preview');
    if (!amount || !months || !preview) return;

    var scope = f.dataset.scope || 'm';
    var treasury = scope !== 'm';

    function fact() {
      var id = f.dataset.member
        || (f.querySelector('[name="lb_member"],[name="memberId"]') || {}).value;
      return facts[id] || null;
    }

    function draw() {
      var value = +amount.value || 0;
      var mx = maxTenure(value, policy);

      if (+months.dataset.max !== mx) {
        var wanted = +months.value || +(f.dataset.months || 0) || mx;
        months.innerHTML = '';
        for (var i = 1; i <= mx; i++) {
          var option = document.createElement('option');
          option.value = i;
          option.textContent = i + (i > 1 ? ' months' : ' month');
          option.selected = i === Math.min(wanted, mx);
          months.appendChild(option);
        }
        months.dataset.max = mx;
      }

      var n = +months.value || 1;
      var list = schedule(value, +(f.dataset.rate || policy.rate), n, f.dataset.method || policy.method, new Date());
      var interestTotal = list.reduce(function (t, row) { return t + row.interest; }, 0);
      var list2 = checks(fact(), value, n, policy, treasury);
      var hard = list2.filter(function (c) { return !c.ok && !c.soft; }).length;
      var escalation = value > policy.patronThreshold || (treasury && hard);

      preview.innerHTML = '<div class="lp"><div class="lp-fig">'
        + figure('Monthly installment', ugx(list.length ? list[0].total : 0))
        + figure('Total interest', ugx(interestTotal))
        + figure('Total to repay', ugx(value + interestTotal))
        + figure('Rate', policy.rate + '% a month, ' + (policy.method === 'flat' ? 'flat' : 'reducing'))
        + figure('First due', list.length ? fdate(list[0].due) + ' if paid out today' : '')
        + figure('Approval route', escalation ? 'Chairperson, then Patron' : 'Chairperson')
        + '</div><div><div class="lbl-s" style="margin-bottom:6px">Eligibility</div>'
        + checksHTML(list2)
        + (hard && !treasury
          ? '<p class="small" style="color:var(--bad);margin-top:8px;font-weight:600">Fix the items marked with a cross before sending.</p>'
          : (hard ? '<p class="small" style="color:var(--warn);margin-top:8px;font-weight:600">Outside policy. Add a note and it goes to the Patron as an exception.</p>' : ''))
        + '</div></div>';
    }

    f.addEventListener('input', draw);
    f.addEventListener('change', draw);
    draw();
  }

  /* The loan being paid back decides the figure: the box offers what is actually owing today so a
     typing slip does not turn into a payment the Chairperson has to send back. */
  function repayForm(f) {
    var loan = f.querySelector('[name="rp_loan"]');
    var amount = f.querySelector('[name="rp_amount"]');
    var panel = f.querySelector('.repay-info');
    if (!loan || !amount || !panel || !f.dataset.figures) return;

    var load = remote(function () {
      return f.dataset.figures + '?loan=' + encodeURIComponent(loan.value);
    }, function (data) {
      panel.innerHTML = data.html;
      if (data.suggest > 0 && (!amount.value || +amount.value === last)) amount.value = data.suggest;
      last = data.suggest;
    });

    var last = 0;

    loan.addEventListener('change', load);
    load();
  }

  /* The pool is the Treasurer's call; everything else in the month follows from it, which is why
     the panel is asked for whenever either box moves. */
  function interestForm(f) {
    var month = f.querySelector('[name="in_month"]');
    var pool = f.querySelector('[name="in_pool"]');
    var calc = f.querySelector('.int-calc');
    var alloc = f.querySelector('.int-alloc');
    if (!month || !pool || !calc || !alloc || !f.dataset.figures) return;

    var load = remote(function () {
      var url = f.dataset.figures + '?month=' + encodeURIComponent(month.value);
      if (pool.value !== '') url += '&pool=' + encodeURIComponent(pool.value);
      return url;
    }, function (data) {
      calc.innerHTML = data.html;
      alloc.innerHTML = data.allocHtml || '';
      if (pool.value === '' || +pool.value === suggested) pool.value = data.pool;
      suggested = data.pool;
    });

    var suggested = 0;

    month.addEventListener('change', function () { pool.value = ''; load(); });
    pool.addEventListener('input', load);
    load();
  }

  /* What the facility costs a month, before anybody has lent the money. */
  function extForm(f) {
    var amount = f.querySelector('[name="ex_amount"]');
    var rate = f.querySelector('[name="ex_rate"]');
    var months = f.querySelector('[name="ex_months"]');
    var panel = f.querySelector('.ext-calc');
    if (!amount || !rate || !months || !panel) return;

    function draw() {
      var a = +amount.value || 0, r = +rate.value || 0, n = +months.value || 1;
      var principal = Math.round(a / n), interest = Math.round(a * r / 100);

      panel.innerHTML = kv([
        ['Monthly installment', ugx(principal + interest)],
        ['Total interest', ugx(interest * n)],
        ['Total to repay', ugx(a + interest * n)]
      ]);
    }

    f.addEventListener('input', draw);
    draw();
  }

  /* One instalment of a facility the group already owes. The lender's own schedule is offered, and
     the Treasurer overrides it when a payment is late or short. */
  function extRepayForm(f) {
    var facility = f.querySelector('[name="xr_loan"]');
    var principal = f.querySelector('[name="xr_principal"]');
    var interest = f.querySelector('[name="xr_interest"]');
    if (!facility || !principal || !interest) return;

    function fill() {
      var option = facility.options[facility.selectedIndex];
      if (!option) return;
      principal.value = option.dataset.principal || 0;
      interest.value = option.dataset.interest || 0;
    }

    facility.addEventListener('change', fill);
    fill();
  }

  /* What leaving is worth, at the date being typed. */
  function exitForm(f) {
    var member = f.querySelector('[name="exi_member"]');
    var date = f.querySelector('[name="exi_date"]');
    var panel = f.querySelector('.exit-calc');
    if (!member || !panel || !f.dataset.figures) return;

    var load = remote(function () {
      var url = f.dataset.figures + '?member=' + encodeURIComponent(member.value);
      if (date && date.value) url += '&as_of=' + encodeURIComponent(date.value);
      return url;
    }, function (data) { panel.innerHTML = data.html; });

    member.addEventListener('change', load);
    if (date) date.addEventListener('change', load);
    load();
  }

  document.querySelectorAll('form[data-live="loan"]').forEach(loanForm);
  document.querySelectorAll('form[data-live="repay"]').forEach(repayForm);
  document.querySelectorAll('form[data-live="interest"]').forEach(interestForm);
  document.querySelectorAll('form[data-live="ext"]').forEach(extForm);
  document.querySelectorAll('form[data-live="extrepay"]').forEach(extRepayForm);
  document.querySelectorAll('form[data-live="exit"]').forEach(exitForm);
})();
