<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title', 'Payment Sandbox')</title>
<style>
  :root {
    --bg: #f5f6f8; --card: #fff; --text: #1c2024; --muted: #6b7280;
    --line: #e5e7eb; --ok: #16a34a; --bad: #dc2626; --warn: #d97706;
    --accent: #2563eb;
  }
  @media (prefers-color-scheme: dark) {
    :root {
      --bg: #14171a; --card: #1d2125; --text: #e8eaed; --muted: #9aa3ad;
      --line: #2d3339; --ok: #4ade80; --bad: #f87171; --warn: #fbbf24;
      --accent: #60a5fa;
    }
  }
  * { box-sizing: border-box; }
  body {
    margin: 0; padding: 24px 16px; background: var(--bg); color: var(--text);
    font: 15px/1.55 system-ui, -apple-system, "Segoe UI", sans-serif;
  }
  .wrap { max-width: 860px; margin: 0 auto; }
  a { color: var(--accent); }
  h1 { font-size: 21px; margin: 0 0 4px; }
  h2 { font-size: 15px; margin: 28px 0 10px; }
  .sub { color: var(--muted); margin: 0 0 22px; font-size: 14px; }
  .card { background: var(--card); border: 1px solid var(--line); border-radius: 10px; padding: 18px; margin-bottom: 18px; }
  label { display: block; font-size: 13px; color: var(--muted); margin-bottom: 4px; }
  input[type=text], input[type=number] {
    width: 100%; padding: 9px 11px; border: 1px solid var(--line); border-radius: 7px;
    background: var(--bg); color: var(--text); font: inherit;
  }
  .row { display: flex; gap: 12px; flex-wrap: wrap; }
  .row > * { flex: 1 1 200px; }
  button {
    padding: 9px 16px; border-radius: 7px; border: 1px solid transparent; font: inherit;
    font-weight: 600; cursor: pointer; background: var(--accent); color: #fff;
  }
  button.secondary { background: transparent; border-color: var(--line); color: var(--text); }
  table { width: 100%; border-collapse: collapse; }
  th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid var(--line); vertical-align: top; }
  th { font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); }
  tr:last-child td { border-bottom: none; }
  code, .mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 13px; }
  .badge { display: inline-block; padding: 2px 9px; border-radius: 999px; font-size: 12px; font-weight: 600; border: 1px solid var(--line); }
  .badge.paid { color: var(--ok); } .badge.failed { color: var(--bad); } .badge.pending { color: var(--warn); }
  .muted { color: var(--muted); }
  .err { color: var(--bad); font-size: 13px; margin-top: 6px; }
  ul.notes { color: var(--muted); font-size: 13px; padding-left: 18px; }
</style>
</head>
<body>
<div class="wrap">
  @yield('content')
</div>
</body>
</html>
