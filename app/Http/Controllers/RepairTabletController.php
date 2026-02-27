<?php

namespace App\Http\Controllers;

use App\Domain\Repairs\RepairPublicFlowService;
use RuntimeException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class RepairTabletController extends Controller
{
    public function show(string $token, RepairPublicFlowService $service): View
    {
        try {
            $publicToken = $service->resolveValidToken($token);
        } catch (RuntimeException $e) {
            abort(410, $e->getMessage());
        }

        return view('public.repairs.tablet', [
            'token' => $publicToken,
            'repair' => $publicToken->repair()->withoutGlobalScopes()->with('customer')->firstOrFail(),
            'error' => null,
            'success' => false,
            'feedbackToken' => null,
        ]);
    }

    public function sign(string $token, Request $request, RepairPublicFlowService $service): Response
    {
        try {
            $publicToken = $service->resolveValidToken($token);
        } catch (RuntimeException $e) {
            abort(410, $e->getMessage());
        }

        $request->validate([
            'signature' => ['required', 'string'],
            'signer_name' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            $result = $service->submitSignature(
                $publicToken,
                (string) $request->input('signature'),
                $request->string('signer_name')->toString() ?: null,
                (string) $request->ip(),
                $request->userAgent()
            );

            return response()->view('public.repairs.tablet', [
                'token' => $publicToken,
                'repair' => $publicToken->repair()->withoutGlobalScopes()->with('customer')->firstOrFail(),
                'error' => null,
                'success' => true,
                'feedbackToken' => $result['feedbackToken'],
            ]);
        } catch (RuntimeException $e) {
            $status = in_array($e->getMessage(), ['Token expired.', 'Token already used.'], true) ? 410 : 409;

            return response()->view('public.repairs.tablet', [
                'token' => $publicToken,
                'repair' => $publicToken->repair()->withoutGlobalScopes()->with('customer')->firstOrFail(),
                'error' => $e->getMessage(),
                'success' => false,
                'feedbackToken' => null,
            ], $status);
        }
    }

    public function feedback(string $token, Request $request, RepairPublicFlowService $service): RedirectResponse
    {
        try {
            $publicToken = $service->resolveValidToken($token);
        } catch (RuntimeException $e) {
            abort(410, $e->getMessage());
        }

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string'],
        ]);

        try {
            $service->submitFeedback($publicToken, (int) $validated['rating'], $validated['comment'] ?? null);
        } catch (RuntimeException $e) {
            abort(409, $e->getMessage());
        }

        return redirect('/');
    }
}
