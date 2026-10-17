@extends('layout')
@section('title', $order->reference)

@section('content')
  <h1>{{ $order->description }}</h1>
  <p class="sub mono">{{ $order->reference }}</p>

  <div class="card">
    <table>
      <tr><th>Status</th><td><span class="badge {{ $order->status->badgeClass() }}">{{ strtoupper($order->status->value) }}</span></td></tr>
      <tr><th>Amount</th><td>{{ $order->amountForHumans() }}</td></tr>
      <tr><th>Gateway reference</th><td class="mono">{{ $order->gateway_reference ?? '—' }}</td></tr>
      <tr><th>Paid at</th><td>{{ $order->paid_at?->toDayDateTimeString() ?? '—' }}</td></tr>
    </table>
  </div>

  <h2>Callbacks received</h2>
  <div class="card">
    @if ($events->isEmpty())
      <p class="muted" style="margin:0">
        Nothing yet. Callbacks are delivered by a queued job, so run
        <code>php artisan queue:work</code> and refresh.
      </p>
    @else
      <table>
        <thead>
          <tr><th>Outcome</th><th>Event</th><th>Reported</th><th>Signature</th><th>When</th></tr>
        </thead>
        <tbody>
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
    @endif
  </div>

  <p><a href="{{ route('checkout') }}">&larr; Back to orders</a></p>
@endsection
