<?php

namespace App\Http\Controllers;

use App\Payments\ApplyPaymentCallback;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The merchant endpoint a gateway calls server-to-server.
 *
 * It is deliberately thin: all the decisions live in ApplyPaymentCallback so
 * they can be unit tested without HTTP.
 */
class PaymentCallbackController extends Controller
{
    public function __invoke(Request $request, ApplyPaymentCallback $callback): JsonResponse
    {
        $payload = $request->all();
        $signature = $payload['signature'] ?? $request->header('X-Signature');

        $outcome = $callback->handle($payload, is_string($signature) ? $signature : null);

        return response()->json([
            'outcome' => $outcome->value,
            'acknowledged' => $outcome->isAcknowledged(),
        ], $outcome->httpStatus());
    }
}
