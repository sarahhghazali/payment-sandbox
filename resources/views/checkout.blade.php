@extends('layout')
@section('title', 'Payment Sandbox')

@section('content')
  <h1>Payment Sandbox</h1>
  <p class="sub">A merchant app and a fake payment gateway, so callback handling can be tested on purpose instead of in production.</p>

  <details class="card guide" open>
    <summary>New here? Start with this (about 2 minutes)</summary>
    <ol>
      <li>
        <b>Make a payment.</b> Create an order below, then choose
        <b>Pay successfully</b> on the gateway page. You land back on the order,
        which turns <b>PAID</b> once the callback arrives.
      </li>
      <li>
        <b>Watch duplicates get absorbed.</b> Create another order, but this time
        tick <b>“Send the callback three times”</b>. The gateway sends three
        identical callbacks — the order still records only <b>one</b> payment.
        That is idempotency, and it is why a customer is not charged twice.
      </li>
      <li>
        <b>Attack an order.</b> Open any order and use the
        <b>Try an attack</b> buttons: a forged callback, a tampered amount, a
        replay. Each one is rejected, with the reason recorded.
      </li>
      <li>
        <b>Read the audit trail.</b> Every callback is listed on the order page,
        accepted or not — that is what answers “why was my order not marked
        paid?”.
      </li>
    </ol>
    <p class="muted" style="margin:14px 0 0; font-size:13px">
      Callbacks are delivered by a queued job, so <code>php artisan queue:work</code>
      must be running. If an order stays PENDING, that is usually why.
    </p>
  </details>

  <div class="card">
    <h2 style="margin-top:0">New order <span class="step" style="vertical-align:middle">Step 1 of 3</span></h2>
    <form method="POST" action="{{ route('orders.store') }}">
      @csrf
      <div class="row">
        <div>
          <label for="description">Description</label>
          <input type="text" id="description" name="description" value="{{ old('description', 'Test order') }}" required>
        </div>
        <div>
          <label for="amount">Amount ({{ $currency }})</label>
          <input type="number" id="amount" name="amount" step="0.01" min="0.01" value="{{ old('amount', '49.90') }}" required>
        </div>
        <div style="flex:0 0 auto; display:flex; align-items:flex-end">
          <button type="submit">Pay with sandbox gateway</button>
        </div>
      </div>
      @error('amount') <div class="err">{{ $message }}</div> @enderror
      @error('description') <div class="err">{{ $message }}</div> @enderror
    </form>
  </div>

  <div class="card">
    <h2 style="margin-top:0">Recent orders</h2>
    @if ($orders->isEmpty())
      <p class="muted" style="margin:0">No orders yet. Create one above.</p>
    @else
      <table>
        <thead>
          <tr><th>Status</th><th>Order</th><th>Amount</th><th>Created</th></tr>
        </thead>
        <tbody>
        @foreach ($orders as $order)
          <tr>
            <td><span class="badge {{ $order->status->badgeClass() }}">{{ strtoupper($order->status->value) }}</span></td>
            <td>
              <a class="mono" href="{{ route('orders.show', $order->reference) }}">{{ $order->reference }}</a>
              <div class="muted">{{ $order->description }}</div>
            </td>
            <td>{{ $order->amountForHumans() }}</td>
            <td class="muted">{{ $order->created_at->diffForHumans() }}</td>
          </tr>
        @endforeach
        </tbody>
      </table>
    @endif
  </div>
@endsection
