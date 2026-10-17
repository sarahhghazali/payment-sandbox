@extends('layout')
@section('title', 'Payment Sandbox')

@section('content')
  <h1>Payment Sandbox</h1>
  <p class="sub">A merchant app and a fake payment gateway, so callback handling can be tested on purpose instead of in production.</p>

  <div class="card">
    <h2 style="margin-top:0">New order</h2>
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
