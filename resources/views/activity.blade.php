@extends('layout')
@section('title', 'Live activity')

@section('content')
  <header style="display:flex; justify-content:space-between; align-items:baseline; gap:12px; flex-wrap:wrap">
    <div>
      <h1>Live activity</h1>
      <p class="sub">The queue, the delivery attempts and the callbacks — refreshing every 2 seconds.</p>
    </div>
    <span class="muted" style="font-size:13px">updated <span id="updated">{{ $initial['updated'] }}</span></span>
  </header>

  <div class="hint">
    <p>Keep this open in a second tab while you pay for an order.</p>
    <p class="muted">A job appears in <b>Queue</b>, the worker picks it up, a row lands in
      <b>Delivery attempts</b>, and the merchant's decision shows in <b>Callbacks</b>. If jobs pile up
      and nothing moves, <code>php artisan queue:work</code> is not running.</p>
  </div>

  <h2>Queue <span class="muted" id="queue-summary"></span></h2>
  <div class="card">
    <table>
      <thead><tr><th>Job</th><th>Queue</th><th class="num">Attempts</th><th class="num">Retry in</th></tr></thead>
      <tbody id="queue-rows"></tbody>
    </table>
  </div>

  <h2>Delivery attempts <span class="muted">(gateway &rarr; merchant)</span></h2>
  <div class="card">
    <table>
      <thead><tr><th>Result</th><th>Order</th><th class="num">Attempt</th><th>When</th></tr></thead>
      <tbody id="delivery-rows"></tbody>
    </table>
  </div>

  <h2>Callbacks <span class="muted">(what the merchant decided)</span></h2>
  <div class="card">
    <table>
      <thead><tr><th>Outcome</th><th>Order</th><th>Signature</th><th>When</th></tr></thead>
      <tbody id="callback-rows"></tbody>
    </table>
  </div>

<script>
  const initial = @json($initial);

  function cell(text, className) {
    const td = document.createElement('td');
    td.textContent = text;
    if (className) td.className = className;
    return td;
  }

  function badge(text, kind) {
    const td = document.createElement('td');
    const span = document.createElement('span');
    span.className = 'badge ' + kind;
    span.textContent = text;
    td.append(span);
    return td;
  }

  function empty(colspan, message) {
    const tr = document.createElement('tr');
    const td = cell(message, 'muted');
    td.colSpan = colspan;
    tr.append(td);
    return tr;
  }

  function render(data) {
    document.getElementById('updated').textContent = data.updated;

    const q = data.queue;
    document.getElementById('queue-summary').textContent =
      `— ${q.waiting} waiting, ${q.failed} failed (driver: ${q.driver})`;

    const queueRows = document.getElementById('queue-rows');
    queueRows.replaceChildren();
    if (!q.jobs.length) {
      queueRows.append(empty(4, q.driver === 'sync'
        ? 'Running jobs synchronously — nothing is queued.'
        : 'Queue is empty. Jobs appear here the moment a payment is made.'));
    } else {
      for (const job of q.jobs) {
        const tr = document.createElement('tr');
        tr.append(
          cell(job.name),
          cell(job.queue, 'muted'),
          cell(job.attempts, 'num'),
          cell(job.retry_in > 0 ? job.retry_in + 's' : 'now', 'num'),
        );
        queueRows.append(tr);
      }
    }

    const deliveryRows = document.getElementById('delivery-rows');
    deliveryRows.replaceChildren();
    if (!data.deliveries.length) {
      deliveryRows.append(empty(4, 'No callbacks delivered yet.'));
    } else {
      for (const d of data.deliveries) {
        const tr = document.createElement('tr');
        tr.append(
          badge(d.acknowledged ? 'HTTP ' + d.http_status : (d.http_status ? 'HTTP ' + d.http_status : 'error'),
                d.acknowledged ? 'paid' : 'failed'),
          cell(d.order ?? '—', 'mono'),
          cell(d.attempt, 'num'),
          cell(d.when ?? '', 'muted'),
        );
        deliveryRows.append(tr);
      }
    }

    const callbackRows = document.getElementById('callback-rows');
    callbackRows.replaceChildren();
    if (!data.callbacks.length) {
      callbackRows.append(empty(4, 'No callbacks received yet.'));
    } else {
      for (const c of data.callbacks) {
        const tr = document.createElement('tr');
        tr.append(
          badge(c.outcome, c.accepted ? 'paid' : 'failed'),
          cell(c.order, 'mono'),
          badge(c.signature_valid ? 'valid' : 'invalid', c.signature_valid ? 'paid' : 'failed'),
          cell(c.when, 'muted'),
        );
        callbackRows.append(tr);
      }
    }
  }

  render(initial);

  async function refresh() {
    try {
      render(await (await fetch("{{ route('activity.data') }}", { cache: 'no-store' })).json());
    } catch (e) {
      // Server restarting; try again on the next tick.
    }
  }

  setInterval(refresh, 2000);
</script>
@endsection
