<?php

namespace App\Http\Controllers;

use App\Models\CallbackDelivery;
use App\Models\PaymentEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * A live view of what the queue and the callbacks are doing, so the parts of
 * the flow that normally happen invisibly in a worker process can be watched
 * in the browser.
 */
class ActivityController extends Controller
{
    public function index(): View
    {
        return view('activity', ['initial' => $this->snapshot()]);
    }

    public function data(): JsonResponse
    {
        return response()->json($this->snapshot())
            ->header('Cache-Control', 'no-store');
    }

    /** @return array<string, mixed> */
    private function snapshot(): array
    {
        return [
            'queue' => $this->queue(),
            'deliveries' => $this->deliveries(),
            'callbacks' => $this->callbacks(),
            'updated' => now()->format('H:i:s'),
        ];
    }

    /**
     * Jobs still waiting, and jobs that exhausted their retries.
     *
     * Reads the queue tables directly because that is the point of the page:
     * seeing work that has not run yet.
     *
     * @return array<string, mixed>
     */
    private function queue(): array
    {
        // With QUEUE_CONNECTION=sync there are no queue tables at all.
        if (! Schema::hasTable('jobs')) {
            return ['driver' => config('queue.default'), 'waiting' => 0, 'failed' => 0, 'jobs' => []];
        }

        $jobs = [];
        $rows = DB::table('jobs')
            ->orderBy('id')
            ->limit(10)
            ->get(['id', 'queue', 'attempts', 'available_at', 'payload']);

        foreach ($rows as $row) {
            $payload = json_decode((string) $row->payload, true);
            $name = is_array($payload) ? ($payload['displayName'] ?? 'job') : 'job';

            $jobs[] = [
                'id' => (int) $row->id,
                'name' => class_basename(is_string($name) ? $name : 'job'),
                'queue' => (string) $row->queue,
                'attempts' => (int) $row->attempts,
                // A job with a future available_at is waiting out its backoff.
                'retry_in' => max(0, (int) $row->available_at - now()->timestamp),
            ];
        }

        return [
            'driver' => config('queue.default'),
            'waiting' => DB::table('jobs')->count(),
            'failed' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0,
            'jobs' => $jobs,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function deliveries(): array
    {
        return CallbackDelivery::with('payment')
            ->latest('id')
            ->limit(12)
            ->get()
            ->map(fn (CallbackDelivery $d) => [
                'order' => $d->payment?->order_reference,
                'event_id' => $d->event_id,
                'attempt' => $d->attempt,
                'http_status' => $d->http_status,
                'acknowledged' => $d->acknowledged,
                'error' => $d->error,
                'when' => $d->created_at?->diffForHumans(),
            ])->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function callbacks(): array
    {
        return PaymentEvent::latest('id')
            ->limit(12)
            ->get()
            ->map(fn (PaymentEvent $e) => [
                'order' => $e->order_reference,
                'event_id' => $e->event_id,
                'outcome' => $e->outcome->value,
                'accepted' => $e->outcome->changedOrder(),
                'signature_valid' => $e->signature_valid,
                'status' => $e->status,
                'when' => $e->created_at->diffForHumans(),
            ])->all();
    }
}
