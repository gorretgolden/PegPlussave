# PlusSave PEG design system

This is the design system the PEG system is actually built with. It is written out of
`public/css/peg.css`, the Blade components in `resources/views/components/`, and the screens in
`resources/views/pages/`, so every token, size and state below is a value the running app really uses.
Nothing here is a proposal for a new look. It is the existing look written down so the next screen
matches the last one, and so a reviewer can tell when one of them has drifted.

The product is a savings group bookkeeper. Members see their own money, the committee sees everybody's,
and every figure has to be traceable back to the person who made it and the person who checked it.
That single fact decides most of the visual choices below.

## 1. Five rules that outrank the rest

**A ledger, not a dashboard.** The page reads top to bottom in the order a treasurer works: what is in
the box, the rule that governs it, then the rows. No hero images, no decorative panels, no charts that
are not tied to a number in a table underneath.

**Every figure can be opened.** A row in a table is not the end of the story. Rows carry
`class="click"` and open the record behind them, so any amount on screen can be traced to its approvals
without leaving the page.

**Money has an owner and a stamp.** No posted figure appears without its maker-checker trail. The stamp
row (`x-trail`, `x-mini-trail`) is the signature of the product; do not replace it with a status pill
when both could be shown.

**Amounts stay where they belong.** Mobilisation shows names, never figures. Treasury shows figures.
The screen decides who sees what; the design system does not let a view invent a number it was not
given.

**One accent, one signal.** PlusSave green carries action and brand. Gold carries the exception
(money that belongs to members but has not been paid out, pending attention, the Patron tier). Red
means an error or money that is owed and late. If a panel needs more than one of these, the panel is
doing two jobs.

## 2. Foundations

### 2.1 Colour tokens

All colour comes from CSS custom properties on `:root`. Never write a hex value inside a rule or a
Blade attribute; if a screen needs a colour that is not in this table, add a token first.

| Token | Light | Dark | Use |
| --- | --- | --- | --- |
| `--bg` | `#F1F5F2` | `#0B1410` | Page ground behind panels |
| `--surface` | `#FFFFFF` | `#111C17` | Panels, modals, table cells |
| `--surface-2` | `#ECF2EE` | `#16241D` | Raised fill: table headers, footers, quiet cards, readonly inputs |
| `--ink` | `#13211A` | `#E2ECE6` | Body text, primary button text on light |
| `--muted` | `#58695F` | `#93A79B` | Labels, sub lines, secondary text |
| `--line` | `#D7E0D9` | `#21332A` | Hairlines: borders, row separators |
| `--line-strong` | `#BFCDC3` | `#2F453A` | Control outlines: buttons, inputs, chips |
| `--brand` | `#0B5D3B` | `#2E9F68` | Primary action fill, today's date, focus ring |
| `--brand-hover` | `#084A2F` | `#37B377` | Primary action hover |
| `--brand-ink` | `#FFFFFF` | `#03130A` | Text sitting on `--brand` |
| `--brand-soft` | `#DBEEE2` | `#16372A` | Tinted fill behind brand text: rules bar, avatar, tab badge |
| `--brand-text` | `#0B5D3B` | `#5DCB93` | Green text on a light or dark surface (never `--brand` for text in dark mode) |
| `--gold` | `#946A0C` | `#E2B54C` | Interest due, pinned notices, the exception signal |
| `--gold-soft` | `#F6EBCB` | `#372C11` | Gold pill and confidence banner fill |
| `--ok` / `--ok-soft` | `#1D7246` / `#DCF0E3` | `#5DCB93` / `#133428` | Posted, met, current |
| `--warn` / `--warn-soft` | `#925C00` / `#FAEACA` | `#E8AD45` / `#382A0F` | Pending, below minimum, 1 to 60 days late |
| `--bad` / `--bad-soft` | `#B42318` / `#FCE2DE` | `#F2877B` / `#3D1B17` | Rejected, missed, arrears, over 60 days |
| `--info` / `--info-soft` | `#1B5898` / `#DDE8F5` | `#88B7EE` / `#162A43` | Being checked, general meetings |
| `--plum` | `#7A3E8F` | `#C69BD8` | Calendar activities only, so they cannot be read as finance |
| `--on-bad` | `#FFFFFF` | `#1A0503` | Text sitting on a solid red fill |

Rail (the deep green column) has its own set so the shell does not shift when the workspace theme
changes: `--rail` `#0A3826`, `--rail-ink` `#D6EADF`, `--rail-muted` `#8CB59F`, `--rail-active` `#13553A`,
`--rail-line` `#15492F`, `--rail-gold` `#E5BB50`, `--rail-on` `#FFFFFF`. The logo sits on
`--logo-bg` (white) so a transparent upload stays readable on the dark rail.

Overlays and depth: `--scrim` (light `rgba(5,22,13,.48)`, dark `rgba(0,0,0,.62)`) and
`--shadow-lg` (`0 2px 6px rgba(8,36,22,.08), 0 18px 48px rgba(8,36,22,.18)`). Shadows are used on the
modal and the toast only. Nothing else in the product floats.

### 2.2 Themes

Light is the default. The theme is one attribute, `data-theme="light|dark"` on `<html>`, chosen per
account and stored with the user; `layouts/peg.blade.php` prints it before any CSS loads so there is no
flash. `color-scheme` is set inside each theme block so form controls, scrollbars and date pickers
follow. Dark mode is a token swap, never a second stylesheet, so a new component gets both themes for
free as long as it only uses tokens.

Contrast is a hard requirement in both themes. Status colours are already dark enough for text in light
mode and light enough in dark mode; do not "brighten" a tone for decoration.

### 2.3 Type

Two families, no third.

- `--f-ui`: Plus Jakarta Sans, 400/500/600/700/800. Everything a person reads.
- `--f-num`: IBM Plex Mono, 400/500/600. Every figure, reference, member number, date cell and
  time stamp. It is set with `font-variant-numeric: tabular-nums`, so a column of amounts lines up
  without padding.

The scale in use, largest to smallest. These are the only sizes a new screen should reach for.

| Size | Weight | Where |
| --- | --- | --- |
| `clamp(68px,10vw,128px)` | 800, tracking .03em | `PEG` on the sign-in brand panel |
| `clamp(22px,3vw,30px)` | 800, -.025em | `.hero-h` page headline on the member desk |
| 25px | 500 (b at 800) | `.wm-1` PlusSave wordmark |
| 21px | 600, `--f-num` | `.kpi .val` figure |
| 20px | 800, -.01em | `.tb-title h1` the page name in the top bar |
| 20px | 600, `--f-num` | `.ap-amt` amount on an approval card |
| 17px | 600, `--f-num` | `.ev-date b` day number in a calendar entry |
| 16px | 800 | `.mdl-h h3` modal title |
| 15px | 800 | `.sec h2` section title, `.wf-row.total` |
| 14px | 400 | body, table cells, inputs |
| 13.5px | 500/700 | rail items, `.ev-t`, `.ap-type`, `.act b`, section headings in a card |
| 13px | 700 | `.btn`, pills of prose, notices, notes, banner text |
| 12.5px | 400/500/600/700 | `.small`, `.sub`, `.btn.sm`, chips, field errors, `.toast`, `.cell-sub` siblings |
| 12px | 600/700 | `.field label`, `.hint`, `.kpi .sub`, `.lp-fig b` |
| 11.5px | 700/800 | `.pill`, `.ev-type`, day cells, `.note span` |
| 11px | 600/700/800 | `.eyebrow`, `.group-label`, `.rail-badge`, `.tn` |
| 10.5px | 700, uppercase, .08 to .14em | `.kpi .lbl`, `th`, `.rail-label`, `.tb-crumb` |
| 10px | 700, .1 to .24em | `.ev-date span`, `.wm-2`, day-of-week headers |

Rules: sentence case everywhere except the micro-labels above, which are uppercase with wide tracking
on purpose. Never set body copy in the mono face; it is for figures and identifiers. Money and dates
take `--f-num` even inside a sentence. Line height is 1.5 for body, 1.2 for headings, 1.1 for date
stamps. `text-wrap: balance` is on by default for headings.

### 2.4 Space, radius, borders

The spacing ladder is 2, 4, 5, 6, 7, 8, 10, 11, 12, 14, 16, 18, 20, 24, 28, 40, 48, 56 px. Use the
nearest existing step rather than a new one. The fixed frames:

| Frame | Value |
| --- | --- |
| `.content` padding | 24px top, 56px bottom, 28px inline (16px below 960px) |
| `.content` row gap | 20px |
| `.panel` padding | 18px vertical, 20px horizontal |
| `.stack` gap | 12px |
| `.kpis` gap | 12px |
| `.cols2` / `.cols-21` gap | 20px |
| `.form` gap | 14px, `.field` internal gap 5px |
| Table cells | 10px vertical, 12px horizontal |
| Rail | 256px wide, items 8px 10px, group gap 14px |
| Top bar | 12px block padding, 28px inline, sticky |

Radius: `--r` is 10px and owns every card, panel, banner and board column. Controls (buttons, inputs,
icon buttons, notes, boards cards, chips in a modal) are 8px. The small interior marks are 7px
(`.stamp`), 6px (`.day`), 4px (`code`, `.peg-mark`, `.bar` tops). Pills, chips, `.rail-badge`, `.mt`
and `.prog` are 999px. The modal is the only 14px in the product.

Borders are 1px `--line` for containers and rows, 1px `--line-strong` for anything a person can act on.
A 2px border is a signal, used twice on purpose: `.stamp` outlines and `.field-invalid`.

### 2.5 Breakpoints

Mobile up. The shell collapses first, the panels follow, and dense grids reflow last.

| Width | What changes |
| --- | --- |
| 1380px | Year calendar 3 columns to 2 |
| 1180px | Calendar sidebar drops under the year, then the year returns to 3 columns |
| 1100px | KPI row 4 to 2, treasury action grid 4 to 2, 3-KPI row stays 3 |
| 960px | Rail becomes a drawer behind a menu button, top bar and content go to 16px padding, account switcher and name hide |
| 900px | Two-column layouts stack |
| 860px | Sign-in splits to one column, brand panel loses its ledger rules |
| 820px | Year calendar 3 to 2 |
| 760px | Mobilisation board stacks |
| 700px | 3-KPI row stacks |
| 640px | Loan preview stacks |
| 600px | Chart columns and labels tighten |
| 560px | `.grid2` and `.grid3` stack |
| 540px | Year calendar to one column |
| 470px | KPI row and action grid to one column |

Anything new has to read at 390px as well as 1440px, because members open this on a phone at the
banking till.

### 2.6 Motion, icons, a11y primitives

Motion is minimal and functional: the rail drawer slides in 0.22s and `prefers-reduced-motion: reduce`
turns it off. There are no other transitions. Focus is never removed: `:focus-visible` draws a 2px
`--brand` ring at 2px offset on every control, including day cells and chips.

Icons come from `App\Support\Icons` through `<x-icon name="..." />`: home, wallet, loan, cal, users,
mega, notes, check, vault, chart, ledger, flag, shield, gear, lock, logout, menu, plus, x, clock,
arrow, copy, alert, info, pin, bank, out, upload, inbox, percent. They are one stroke weight on a
24 grid, `18px` in the rail, `16px` inside buttons and banners, `15px` in a stamp, `13px` for a lock,
`11px` in a mini trail dot. `class="flip"` rotates the arrow to point left. Do not add a filled icon,
an emoji, or an icon from another set.

## 3. How the data is spoken

Figures come from `App\Support\Format`, and every screen must use it rather than formatting inline.

| Case | Helper | Output |
| --- | --- | --- |
| Amount in a table cell | `money()` | `1,234,567`, no decimals, no cents anywhere |
| Amount in prose | `ugx()` | `UGX 1,234,567` |
| Amount as a KPI value | `cash()` | `UGX` small and grey, then the figure. Pass it through `{!! !!}` or `x-kpi` |
| Movement in a ledger | `signed()` | `+150,000` or `−120,000`, with the real minus sign U+2212 |
| Compressed figure on an axis | `compact()` | `1.2M` style, axis labels only |
| Rate | `rate()` | `1.5%` |
| Share | `percent($v, $dec = 1)` | `66.6%` |
| Day of the month in prose | `ordinal()` | `28th`, because a person says "by the twenty-eighth" |
| Date | `date()` | `3 Nov 2026` |
| Stamp | `dateTime()` | `3 Nov 2026, 14:20` |
| Month as a heading | `month()` / `monthName()` | `November 2026` |
| Month as a date-stamp tag | `monthTag()` | `NOV` |

Status vocabulary is fixed. Use these words and these tones together, everywhere:

| Word | Tone | Means |
| --- | --- | --- |
| Posted | `ok` | Checked and in the books |
| Pending chair / Pending patron | `warn` | Waiting on a named checker |
| Being checked | `info` | A figure the Treasurer has not vouched for yet |
| Rejected | `bad` | Sent back with a reason |
| In arrears | `bad` | An installment is past its due date |
| Leaving | `warn` | Exit notice recorded, settlement not yet made |
| Left | `muted` | Closed, with the exit date beside it |

Money that is late or negative goes in `class="neg"` (`--bad`), never in red text alone; the word or
the column header carries the meaning too.

Calendar kinds each own a colour through `--ev`, and the tint class goes on the container so the label
and the dot match: `t-meeting` brand, `t-general` info, `t-deadline` warn, `t-finance` gold,
`t-activity` plum. The heat grid uses `met`, `low`, `pend`, `miss`, `na`, with `·` for a month before
joining and `Due` for the current month.

## 4. Components

The Blade component is the unit of the system. A screen composes components; it does not write a
`<div class="kpi">` by hand. These are the ones that exist.

### Layout and shell

- **Rail** (`.rail`, `layouts/peg.blade.php`): 256px, deep green, logo block, three navigation groups
  (My PEG, Committee, System), then the theme and sign-out footer. A group label is 10.5px uppercase
  `--rail-muted`. The active item gets `--rail-active` fill, white text, 700 weight and a gold icon.
  Pages a person can see but not open show a `lock` glyph at 55% opacity instead of disappearing, so
  the member knows the desk exists and who owns it. A gold badge counts what is waiting.
- **Top bar** (`.topbar`): eyebrow breadcrumb with the confidentiality mark in gold, page name at 20px
  800, then the account switcher, avatar and menu. The avatar is `--brand-soft` with initials in
  `--brand-text`.
- **`.content`**: one column grid with a 20px gap and a 1340px maximum. Every page is a stack of these
  blocks; no page positions panels any other way.
- **`x-sec`**: a section title with an optional `.sub` and an `actions` slot that lands in `.sec-r`.
  This is the only header pattern; a panel never has two.
- **`x-tabs`**: underline tabs, scrollable, `role="tablist"`, one tab carries a `.tn` count. The active
  tab is `--brand-text` with a 2px `--brand` underline. Counts are read live, never hardcoded.
- **`x-chips`**: filter chips, which are links, so a filtered view is shareable.

### Numbers and text

- **`x-kpi`**: label, `--f-num` value, optional sub, optional tone `gold|ok|warn|bad`. Four across, two
  under 1100px, one under 470px. `.kpis.flat` is the quieter variant used inside modals.
- **`x-kv`**: the fact sheet. Label above, value under, auto-filling columns at 165px minimum. It is
  the one component allowed to receive markup, because a value may be a pill or a `neg` figure.
- **`x-pill`**: 11.5px, 999px radius, soft background with solid text of the same hue. Tones `ok warn
  bad info gold muted`. A pill never holds a sentence; it holds one word or a date.
- **`x-empty-row`**: a table with no rows says why in the body of the table, spanning every column.
  Never leave a bare `<tbody>` and never say "no data" without naming what would have been there.
- **`x-legend`**: colour squares for a chart or heat grid, in the section header where it belongs to
  one figure only.
- **`x-conf-banner`**, **`.rules`**, **`.warnbox`**, **`.badbox`**, **`x-maker-note`**: the five message
  bars. They are chosen by what they say, not by how loud they need to be:
  - `conf-banner` (gold) states who can read the page; it is printed at the top of any screen that
    shows money across members.
  - `rules` (brand soft) states the policy that produced these numbers, with the action button at the
    right when there is one.
  - `warnbox` (warn soft) is a queue that a named person has to clear.
  - `badbox` (bad soft) is something that failed and what to do about it.
  - `maker-note` (surface-2) is the small print inside a form: what happens to this entry after submit.

### Data

- **Table** (`.tbl`): horizontal scroll wrapper, `min-width: 680px` so a narrow screen scrolls instead
  of squashing figures; `.tbl.fit` drops that for short tables inside a panel. The wrapper carries
  `min-width: 0`, which is what lets it shrink inside a grid and scroll sideways on its own; a table
  must never give the document a horizontal scrollbar. Header is 10.5px
  uppercase on `--surface-2`, right-aligned figures use `th.num` in the UI face and `td.num` in the
  mono face. The last row has no underline. `tr.click` opens the record. `tfoot` is bold on
  `--surface-2` with a `--line-strong` top border, and it is where a total belongs; a screen with two
  totals in a row is showing the wrong figures. `.cell-sub` puts the department or purpose under a
  name rather than in an extra column.
- **Stamp trail** (`x-trail`): made, checked, authorised, each a bordered 7px box with a 9.5px
  uppercase role line, a name, and an icon. `done` is solid green, `no` solid red, `wait` dashed grey
  with the person it is waiting for named. Connectors are `--line-strong` arrows.
- **Mini trail** (`x-mini-trail`): the same three states as 18px circles, for a table column. It has to
  be readable without a tooltip, so it is only used next to a full status pill or in a row that opens
  the full trail.
- **Bar chart** (`x-bars`): 52px axis gutter, dashed gridlines, bars at `--brand` or `--gold`, radius
  3px on the top corners only, a `legend` above and an `aria` label naming what is plotted. Series
  colours come from tokens, never a hex.
- **Progress** (`.prog`) and **half bar** (`.hbar`): 10px and 8px tracks on `--surface-2`. `.hbar` is
  used inside a table to show a share of a total, with the number still written beside it.
- **Waterfall** (`.wf-row`): label left, `--f-num` figure right, deductions marked with `−`, and the
  last row gets a 2px `--ink` top border. This is how "cash to available to lend" is explained.
- **Heat grid** (`.heat`, `.hc`): one cell per member per month, `--f-num` 11.5px, vertical rules so a
  column can be followed. States are `met low pend miss na`, and a legend must be printed above it.
- **Board** (`.board`, `.bcol`, `.bcard`) and **name chips** (`.name-chips`, `.nchip`): the mobilisation
  patterns. A board column header counts its cards; a name chip is the only way a mobilisation screen
  may name a member.
- **Approval card** (`.ap-card`): type at 15px 800, reference under it in mono `--muted`, amount at
  20px mono on the right, the facts in `x-kv`, the checks in `x-checks`, then the decision row: an
  optional comment input that grows, and Approve and Reject side by side. Decided cards are not
  actionable and carry `class="selfnote"` when the same person made the entry.
- **Desk rows** (`.desk-row`): 40px mono number, the task, the action. A zero is dimmed rather than
  hidden, because an empty queue is information.
- **Notice** (`.notice`), **event** (`.ev`), **minutes** (`details.mins`): the three list patterns that
  read as documents rather than records. A pinned notice takes a gold pin icon; an event date is a 50px
  stamp box; minutes collapse with the title, date and attendance in the summary and the resolutions as
  an ordered list inside.

### Controls

- **`btn`**: 36px tall, 8px radius, 14px inline padding, 13px 700 label, `--line-strong` outline on
  `--surface`. `.primary` is solid `--brand` with `--brand-ink`; `.danger` is red text and outline that
  fills `--bad-soft` on hover; `.sm` is 30px; `.block` is 42px full width. Icons sit left at 16px with
  a 7px gap. One `.primary` per view; a form that offers two equal choices is a form with no default.
- **`icon-btn`**: 36px square, one icon, always with `aria-label`. Used for close, year steps and the
  mobile menu.
- **`link-btn`**: a sentence-level action, 12.5px 700 in `--brand-text`, no underline. Used for Remove,
  Undo and Copy inside a row.
- **Inputs**: 40px tall, 8px radius, `--line-strong` outline, 11px inline padding. A readonly field
  drops to `--surface-2` and stays readable; a disabled select keeps full opacity so a locked row does
  not look broken. A label is always a real `<label for>`; where the design shows no visible label,
  `.sr` carries one. Textareas start at 80px and resize vertically only.
- **Field error**: `.field-invalid` thickens the border to 2px and reddens it, and `.field-error` prints
  under the control with a circled `!` and normal weight text. The wording names the field and says
  what to type, in the officer's own vocabulary. A banner above the form lists each failed field as a
  link to it. Never one sentence repeated for every field, and never colour alone.
- **Form**: `.form` grid with 14px gap, `.grid2` or `.grid3` for paired fields, `.fieldset` for a group
  of policy settings, `.form-actions` right-aligned with Cancel before the affirmative button. Modal
  forms carry `data-reset` so a second open starts clean.
- **Modal**: scrim `--scrim`, panel `min(620px,100%)` or `min(920px,100%)` for `.wide`, 14px radius,
  `--shadow-lg`, sticky header with an `x` icon button, body grid at 16px gap. It is a dialog with
  `aria-modal="true"`, `aria-labelledby`, closes on backdrop click and Escape, and the page behind
  cannot scroll. A box that came back with a validation error must reopen itself with the words still
  beside the fields, which is what `session()->flash('open_modal', 'dlg-...')` is for.
- **Toast**: bottom-right, `--ink` fill with `--surface` text, 13px 600, or solid `--bad` for a failure.
  It names what happened and, for a copy action, that it happened.
- **Chips**: `.chip-f` filter pills are links that carry their state in the query string; the selected
  one inverts to `--ink` fill. `.chip` on the sign-in page is a card with a name and a mono role.

## 5. Screen patterns

Every screen in the product is one of these, and a new screen has to pick one before it is built.

1. **Desk** (dashboard): greeting hero, KPI row, rules bar, then the numbered `.desk` queue of what the
   reader personally owes an action, then what is coming up. A member sees their own money; a treasurer
   sees a queue. Same page id, different content, decided by ability, never by an `if role` shortcut.
2. **Ledger** (savings, loans, cash book): KPI row, rule bar, then a statement table with credit and
   debit columns and a running balance in `tfoot`. Filters live in the address.
3. **Register** (directory, notices, minutes, calendar): a filter row, then either a table of rows that
   open a record, or document-style list items. Add actions sit in `.sec-r` at the right of the section
   header, and only for the offices that may add.
4. **Queue** (approvals, treasury): tabs carrying counts, cards rather than rows, because each item
   needs its facts, its checks and its decision in one place. Decided items lose their buttons and
   keep their stamps.
5. **Board** (reports, treasury position): a year or scope selector, KPI row, one chart, one table, one
   explanation bar. Each report keeps the same order so a person who has read one can read any of them.
6. **Panel** (admin): tabs, then a settings form in `.fieldset` groups, with a note about what the
   change affects and what it does not.

The reading order is always: the figure, the rule that produced it, the rows, the total. A chart never
sits above the table it summarises.

## 6. Permission as a visual language

This is the part a reviewer will test, so it is a rule and not a convention.

- A page a person can read but not change shows the action controls for the office that can, and says
  who that is in `.conf-banner` or `.rules`. Nothing is hidden behind a mystery.
- Amounts a reader is not entitled to are not rendered at all. Not blurred, not `disabled`, not hidden
  with CSS. The server does not send them.
- The System Owner account sees the audit trail without the financial entries; that is stated in the
  banner on the page rather than discovered by accident.
- Money that a member could act on appears beside the fields they type into, read-only and unnamed, so
  the derived figure is never mistaken for an input.
- An entry always shows its maker and checker by name and office, including the stamp on a posted row.
- A locked navigation item is dimmed with a lock, not removed.

## 7. Content and voice

- Sentence case, plain Uganda English, no jargon. "Yet to save", "Being checked", "Available to lend",
  not "Non-compliant", "In review", "Deployable liquidity".
- Say who does the thing: "Waiting for the Chairperson to approve", not "Approval pending".
- A helper line states the rule and the number behind it: "Save at least UGX 20,000 by the 28th of each
  month." A date is written the way it is said.
- An error names the field, states the fix, and stays in the form. One error per field.
- No em dashes or en dashes in the interface copy. Use commas, brackets or a full stop.
- Nothing is labelled TODO, coming soon, or left as lorem text in front of a member.
- Numbers are never rounded in prose to make a point; a claim about money links to the row it came from.

## 8. Accessibility floor

- Every interactive element is a real `button`, `a` or `input` with a name. Icon-only controls carry
  `aria-label`; a chip filter carries `aria-pressed`; tabs carry `role="tablist"`; dialogs carry
  `aria-modal` and a labelled heading.
- Focus order follows the reading order, focus never leaves the modal while it is open, and a closed
  modal is removed from the tab order (`hidden`, not `display:none` in a stylesheet).
- No information travels by colour alone. A status has its word, an error has its `!` mark and a thicker
  border, a heat cell has its text as well as its fill.
- Target sizes are 30px minimum for `.btn.sm` and 36px for everything else; day cells are the one
  exception and they carry an `aria-label` naming the date and how many items sit on it.
- Tables keep their `<th>` scope and their header row; a chart gets an `aria` description that names
  the series rather than repeating the legend.
- Text at 10.5px and below is only used for uppercase labels with wide tracking, never for a sentence.

## 9. Adding to the system

1. Need a colour that is not a token? Add it to both theme blocks in `peg.css` before using it. A rule
   with a hex value in it will not survive a theme change.
2. Need a new control? Write it as a Blade component in `resources/views/components/`, give it props
   with defaults, and document it here in the same commit.
3. Need a new state? Reuse `ok|warn|bad|info|gold|muted`. A new tone has to be justified by a state the
   maker-checker flow actually produces.
4. Need a new size? Use the nearest step in the tables above. Off-grid values are how the panels start
   to drift.
5. Build it, then open it at 1440px and 390px, in light and dark, signed in as the office that owns it
   and as a plain member. A screen that only works for the treasurer is not finished.

`public/css/peg.css` is the source of truth for tokens and components. This file explains why they are
the way they are, and is the place to record a new pattern once it has been built.

`design-system.html` in this folder is the live sheet: it renders every token, type step, control and
component above using the app's own stylesheet, with a switch between the two themes. Open it in a
browser to check a value against the build. It is documentation, not a screen members see.
