@extends('layout')
@section('title', 'Sandbox Gateway')

@section('content')
  <h1>Sandbox Gateway</h1>
  <p class="sub">This stands in for the payment provider's hosted page. Choose what should happen to the merchant.</p>

  <div class="hint">
    <span class="step">Step 2 of 3</span>
    <p>You have left the merchant's site, exactly as a customer does during a real payment.</p>
    <p class="muted">Whatever you pick, the gateway signs a callback and sends it to the merchant
      <em>server to server</em>. The browser redirect below is only for your eyes — a merchant must
      never mark an order paid because a browser came back.</p>
  </div>

  <div class="card">
    <table>
      <tr><th>Order</th><td class="mono">{{ $payment->order_reference }}</td></tr>
      <tr><th>Gateway reference</th><td class="mono">{{ $payment->reference }}</td></tr>
      <tr><th>Amount</th><td>{{ number_format($payment->amount_cents / 100, 2) }} {{ $payment->currency }}</td></tr>
      <tr><th>Callback URL</th><td class="mono muted">{{ $payment->callback_url }}</td></tr>
    </table>
  </div>

  <div class="card">
    <form method="POST" action="{{ route('gateway.complete', $payment->reference) }}">
      @csrf
      <label style="display:flex; gap:8px; align-items:center; margin-bottom:14px">
        <input type="checkbox" name="duplicate" value="1" style="width:auto">
        Send the callback three times (simulates a retrying gateway)
      </label>
      <div style="display:flex; gap:10px; flex-wrap:wrap">
        <button type="submit" name="outcome" value="success">Pay successfully</button>
        <button type="submit" name="outcome" value="failed" class="secondary">Fail the payment</button>
      </div>
    </form>
  </div>

  <ul class="notes">
    <li>The callback is signed with HMAC-SHA256 and delivered by a queued job.</li>
    <li>If the merchant does not answer 2xx, delivery is retried with backoff.</li>
    <li>Duplicate deliveries must leave the order paid exactly once.</li>
  </ul>
@endsection
