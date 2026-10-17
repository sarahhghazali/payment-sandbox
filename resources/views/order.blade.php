@extends('layout')
@section('title', $order->reference)

@section('content')
  <h1>{{ $order->description }}</h1>
  <p class="sub mono">{{ $order->reference }}</p>

  @if ($simulation = session('simulation'))
    <div class="hint result {{ $simulation['changed'] ? '' : 'blocked' }}">
      <p><b>{{ $simulation['changed'] ? 'Accepted' : 'Blocked' }}:</b> {{ $simulation['scenario'] }}</p>
      <p class="muted">
        The merchant answered <code>{{ $simulation['status'] }}</code> with
        <code>{{ $simulation['outcome'] }}</code>.
        {{ $simulation['changed']
            ? 'This callback was legitimate, so the order was updated.'
            : 'The order was left untouched. The attempt is recorded below.' }}
      </p>
    </div>
  @endif

  <div class="hint">
    <span class="step">Step 3 of 3</span>
    <p>This is what the merchant knows about the payment.</p>
    <p class="muted">
      The status changes only when a <em>signed</em> callback arrives from the gateway and passes
      every check. Still PENDING? Make sure <code>php artisan queue:work</code> is running.
    </p>
  </div>

  <div class="card">
    <table>
      <tr><th>Status</th><td><span class="badge {{ $order->status->badgeClass() }}" id="status-badge">{{ strtoupper($order->status->value) }}</span></td></tr>
      <tr><th>Amount</th><td>{{ $order->amountForHumans() }}</td></tr>
      <tr><th>Gateway reference</th><td class="mono" id="gateway-ref">{{ $order->gateway_reference ?? '—' }}</td></tr>
      <tr><th>Paid at</th><td id="paid-at">{{ $order->paid_at?->toDayDateTimeString() ?? '—' }}</td></tr>
    </table>
    <p class="muted" style="margin:14px 0 0; font-size:13px">
      This page updates itself every 2 seconds &mdash; no refresh needed.
    </p>
  </div>

  <h2>Try an attack</h2>
  <div class="card">
    <p class="muted" style="margin-top:0; font-size:14px">
      Each button sends a callback straight to the merchant's handler — the same code path a real
      gateway hits. Watch the status above stay exactly where it is.
    </p>
    <form method="POST" action="{{ route('orders.simulate', $order->reference) }}">
      @csrf
      <div style="display:flex; gap:10px; flex-wrap:wrap">
        @foreach ($scenarios as $key => $label)
          <button type="submit" name="scenario" value="{{ $key }}" class="secondary" title="{{ $label }}">
            {{ ucfirst(str_replace('_', ' ', $key)) }}
          </button>
        @endforeach
      </div>
    </form>
    <ul class="notes" style="margin-bottom:0">
      @foreach ($scenarios as $key => $label)
        <li><b>{{ ucfirst(str_replace('_', ' ', $key)) }}</b> — {{ $label }}</li>
      @endforeach
    </ul>
  </div>

  <h2>Callbacks received <span class="muted" id="event-count"></span></h2>
  <div class="card">
    <table id="events-table" @if ($events->isEmpty()) hidden @endif>
      <thead>
        <tr><th>Outcome</th><th>Event</th><th>Reported</th><th>Signature</th><th>When</th></tr>
      </thead>
      <tbody id="event-rows">
      @foreach ($events as $event)
        <tr>
          <td><strong>{{ $event->outcome->value }}</strong></td>
          <td class="mono muted">{{ $event->event_id }}</td>
          <td>
            {{ $event->status }}
            @if ($event->amount_cents !== null)
              <div class="muted">{{ number_format($event->amount_cents / 100, 2) }} {{ $event->currency }}</div>
            @endif
          </td>
          <td>{!! $event->signature_valid ? '<span class="badge paid">valid</span>' : '<span class="badge failed">invalid</span>' !!}</td>
          <td class="muted">{{ $event->created_at->diffForHumans() }}</td>
        </tr>
      @endforeach
      </tbody>
    </table>
    <p class="muted" id="events-empty" style="margin:0" @if ($events->isNotEmpty()) hidden @endif>
      Waiting for the first callback&hellip; it is delivered by a queued job, so
      <code>php artisan queue:work</code> must be running.
    </p>
  </div>

  <details class="card guide">
    <summary>What do these outcomes mean?</summary>
    <table style="margin-top:12px">
      <tr><td><code>applied</code></td><td>Signed, fresh, matched the order. The order was updated.</td></tr>
      <tr><td><code>duplicate</code></td><td>This exact event was already processed. Answered 200 so the gateway stops retrying.</td></tr>
      <tr><td><code>order_already_final</code></td><td>A later callback arrived for an order that is already paid or failed. Ignored.</td></tr>
      <tr><td><code>invalid_signature</code></td><td>Missing, wrong, or no longer matching the payload after tampering.</td></tr>
      <tr><td><code>expired</code></td><td>Correctly signed but too old — a replay of a captured callback.</td></tr>
      <tr><td><code>amount_mismatch</code></td><td>Signed by the gateway, but not for what this order costs.</td></tr>
      <tr><td><code>currency_mismatch</code></td><td>Right number, wrong currency.</td></tr>
      <tr><td><code>unknown_order</code></td><td>No such order reference.</td></tr>
    </table>
  </details>

<script>
  // Poll the order so a callback delivered by the queue worker appears here
  // without the visitor refreshing.
  const statusUrl = "{{ route('orders.status', $order->reference) }}";

  function cell(text, className) {
    const td = document.createElement('td');
    td.textContent = text;
    if (className) td.className = className;
    return td;
  }

  function render(data) {
    const badge = document.getElementById('status-badge');
    badge.textContent = data.status.toUpperCase();
    badge.className = 'badge ' + data.badge;

    document.getElementById('gateway-ref').textContent = data.gateway_reference ?? '—';
    document.getElementById('paid-at').textContent = data.paid_at ?? '—';

    const table = document.getElementById('events-table');
    const empty = document.getElementById('events-empty');
    const rows = document.getElementById('event-rows');
    const count = document.getElementById('event-count');

    table.hidden = data.events.length === 0;
    empty.hidden = data.events.length > 0;
    count.textContent = data.events.length ? `(${data.events.length})` : '';

    rows.replaceChildren();
    for (const e of data.events) {
      const tr = document.createElement('tr');

      const outcome = document.createElement('td');
      const strong = document.createElement('strong');
      strong.textContent = e.outcome;
      outcome.append(strong);

      const reported = document.createElement('td');
      reported.append(document.createTextNode(e.status));
      if (e.amount) {
        const amount = document.createElement('div');
        amount.className = 'muted';
        amount.textContent = e.amount;
        reported.append(amount);
      }

      const signature = document.createElement('td');
      const sig = document.createElement('span');
      sig.className = 'badge ' + (e.signature_valid ? 'paid' : 'failed');
      sig.textContent = e.signature_valid ? 'valid' : 'invalid';
      signature.append(sig);

      tr.append(outcome, cell(e.event_id, 'mono muted'), reported, signature, cell(e.when, 'muted'));
      rows.append(tr);
    }
  }

  async function refresh() {
    try {
      render(await (await fetch(statusUrl, { cache: 'no-store' })).json());
    } catch (e) {
      // Server restarting; try again on the next tick.
    }
  }

  setInterval(refresh, 2000);
</script>
@endsection
