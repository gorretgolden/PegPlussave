/* The PEG screens are rendered on the server. This file only moves things the server cannot know
   about: which box is open, whether the phone menu is out, and when a message has been read.
   Nothing here is needed to book, check or post an entry; a form still sends with scripting off. */
(function () {
  'use strict';

  var openBox = null;

  // One box serves many rows. A button that opens it for a particular member or loan carries the
  // value in a data-set-<field> attribute, so the box is showing the row that was pressed.
  function fill(box, trigger) {
    if (!trigger) return;
    Array.prototype.forEach.call(trigger.attributes, function (attr) {
      if (attr.name.indexOf('data-set-') !== 0) return;
      var field = box.querySelector('[name="' + attr.name.slice(9) + '"]');
      if (!field) return;
      field.value = attr.value;
      field.dispatchEvent(new Event('input', { bubbles: true }));
      field.dispatchEvent(new Event('change', { bubbles: true }));
    });
  }

  function show(id, trigger) {
    var box = document.getElementById(id);
    if (!box) return;
    hide();
    fill(box, trigger);
    box.hidden = false;
    document.body.classList.add('noscroll');
    openBox = box;
    var first = box.querySelector('input:not([type=hidden]):not([disabled]), select, textarea');
    if (first && !box.dataset.nofocus) first.focus();
    document.dispatchEvent(new CustomEvent('peg:opened', { detail: box }));
  }

  function hide() {
    if (openBox) {
      openBox.hidden = true;
      document.dispatchEvent(new CustomEvent('peg:closed', { detail: openBox }));
    }
    openBox = null;
    document.body.classList.remove('noscroll');
    closeMenu();
  }

  function openMenu() {
    var rail = document.getElementById('rail');
    if (!rail) return;
    rail.classList.add('open');
    document.getElementById('scrim').hidden = false;
  }

  function closeMenu() {
    var rail = document.getElementById('rail');
    if (rail) rail.classList.remove('open');
    var scrim = document.getElementById('scrim');
    if (scrim) scrim.hidden = true;
  }

  document.addEventListener('click', function (event) {
    var trigger = event.target.closest('[data-open]');
    if (trigger) {
      event.preventDefault();
      show(trigger.getAttribute('data-open'), trigger);
      return;
    }

    if (event.target.closest('[data-close-modal]')) {
      event.preventDefault();
      hide();
      return;
    }

    var menu = event.target.closest('[data-menu]');
    if (menu) {
      event.preventDefault();
      var rail = document.getElementById('rail');
      rail && rail.classList.contains('open') ? closeMenu() : openMenu();
      return;
    }

    if (event.target.id === 'scrim') {
      hide();
    }
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      if (openBox) hide();
      else closeMenu();
    }
  });

  // A box that is closed with the background still showing was never dismissed on purpose; the next
  // time it opens it should be empty rather than holding the last half-typed figure.
  document.addEventListener('peg:closed', function (event) {
    var form = event.detail.querySelector('form[data-reset]');
    if (form) form.reset();
  });

  var toast = document.getElementById('toast');

  function flash(message) {
    if (!toast) return;
    toast.querySelector('span').textContent = message;
    toast.classList.remove('bad');
    toast.hidden = false;
    setTimeout(function () {
      toast.hidden = true;
    }, 3600);
  }

  if (toast) {
    setTimeout(function () {
      toast.hidden = true;
    }, 3600);
  }

  // A form that came back with an error is opened again where it was left, so the reader does not
  // have to find the button a second time.
  if (document.body.dataset.open) {
    show(document.body.dataset.open);
    document.body.dataset.open = '';
  }

  // A filter that needs no other button. The form still carries its Go button for the reader whose
  // scripting is off; this only saves the tap. A file box counts too, because nothing else can be
  // typed into it: choosing the file is the whole instruction.
  document.addEventListener('change', function (event) {
    var form = event.target.closest && event.target.closest('form[data-submit]');
    if (!form) return;
    if (event.target.matches('select') || event.target.matches('input[type=checkbox]') || event.target.matches('input[type=file]')) {
      form.submit();
    }
  });

  // A row that is really a link, so a phone user can tap anywhere on it, not only the button. The
  // address is in the cell too, so the row still goes somewhere when nothing runs here.
  document.querySelectorAll('tr[data-open], tr[data-href]').forEach(function (row) {
    row.addEventListener('click', function (event) {
      if (event.target.closest('button, a, input, select, label')) return;
      if (row.dataset.href) { window.location = row.dataset.href; return; }
      show(row.getAttribute('data-open'), row);
    });
  });

  // A box that narrows a list already on the screen. The rows stay in the page, so a reader whose
  // scripting is off simply sees the whole list instead of a broken half of it.
  document.querySelectorAll('[data-search]').forEach(function (box) {
    var rows = Array.prototype.slice.call(document.querySelectorAll(box.getAttribute('data-search')));
    if (!rows.length) return;

    var last = '';

    box.addEventListener('input', function () {
      var needle = box.value.trim().toLowerCase();
      if (needle === last) return;
      last = needle;

      rows.forEach(function (row) {
        row.hidden = needle !== '' && row.textContent.toLowerCase().indexOf(needle) === -1;
      });

      var body = rows[0].parentNode;
      var none = body.querySelector('.no-match');

      if (needle === '') {
        none && none.remove();
        return;
      }

      var seen = rows.some(function (row) { return !row.hidden; });

      if (!seen && !none) {
        none = document.createElement('tr');
        none.className = 'no-match';
        var cell = document.createElement('td');
        cell.colSpan = rows[0].children.length;
        cell.className = 'empty';
        cell.textContent = 'Nothing matches that.';
        none.appendChild(cell);
        body.appendChild(none);
      }
    });
  });

  // Removing something from the group's record is not reversible, and a row that looks like any other
  // is easy to tap wrong. The question is asked once, on the form itself, so the reader who says no
  // has changed nothing; the words are in the page, so a browser without scripting still sends.
  document.addEventListener('submit', function (event) {
    var form = event.target.closest('form[data-confirm]');
    if (!form || form.dataset.confirmSent === '1') return;
    event.preventDefault();

    var ask = function (yes) {
      if (!yes) return;
      form.dataset.confirmSent = '1';
      form.requestSubmit();
    };

    if (window.confirm) ask(window.confirm(form.getAttribute('data-confirm')));
    else form.submit();
  });

  // "Copy as CSV" puts the table the reader is looking at on the clipboard. The rows are the ones
  // already rendered, so what is copied is what is on the screen; the download link is the fallback.
  document.querySelectorAll('[data-csv]').forEach(function (button) {
    button.addEventListener('click', function () {
      var rows = JSON.parse(button.dataset.csv || '[]');
      var text = rows.map(function (row) {
        return row.map(function (cell) {
          var value = String(cell === null || cell === undefined ? '' : cell);
          return /[",\n]/.test(value) ? '"' + value.replace(/"/g, '""') + '"' : value;
        }).join(',');
      }).join('\r\n');

      var done = function () { flash('Copied as CSV'); };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(done, function () { window.prompt('Copy the text below', text); });
      } else {
        window.prompt('Copy the text below', text);
      }
    });
  });
})();
