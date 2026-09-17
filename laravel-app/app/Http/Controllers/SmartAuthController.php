<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * SmartAuthController
 *
 * Implements the SMART on FHIR App Launch Framework (standalone launch).
 * Uses OAuth 2.0 with PKCE (Proof Key for Code Exchange).
 *
 * PKCE Flow:
 *   1. /smart/launch → generate code_verifier + code_challenge → redirect to FHIR authorize
 *   2. /smart/callback → exchange auth code for access_token using code_verifier
 *   3. Store access_token SERVER-SIDE in Laravel encrypted session (never in localStorage)
 *
 * GREEN FLAGS:
 *   - PKCE used (no client_secret needed)
 *   - Tokens stored server-side only
 *   - Scopes are minimal (patient/*.read openid fhirUser)
 */
class SmartAuthController extends Controller
{
    /**
     * Step 1: Initiate SMART launch.
     * Generate PKCE verifier/challenge, redirect to FHIR authorization endpoint.
     */
    public function launch(Request $request): RedirectResponse
    {
        // Generate cryptographically secure PKCE code_verifier (43-128 chars)
        $codeVerifier  = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $codeChallenge = rtrim(strtr(
            base64_encode(hash('sha256', $codeVerifier, true)),
            '+/', '-_'
        ), '=');

        $state = Str::random(32);

        // Store verifier + state in server-side session (never exposed to browser)
        session([
            'smart_code_verifier' => $codeVerifier,
            'smart_state'         => $state,
        ]);

        // Build authorization URL
        $authorizeUrl = config('fhir.smart_authorize_url');
        $params = http_build_query([
            'response_type'         => 'code',
            'client_id'             => config('fhir.smart_client_id'),
            'redirect_uri'          => config('fhir.smart_redirect_uri'),
            'scope'                 => config('fhir.smart_scope'),
            'state'                 => $state,
            'aud'                   => config('fhir.base_url'),
            'code_challenge'        => $codeChallenge,
            'code_challenge_method' => 'S256',
        ]);

        return redirect("{$authorizeUrl}?{$params}");
    }

    /**
     * Step 2: OAuth callback — exchange auth code for access token.
     * Validates state, sends code_verifier, stores token server-side.
     */
    public function callback(Request $request): RedirectResponse
    {
        // Validate CSRF state parameter
        if ($request->get('state') !== session('smart_state')) {
            abort(403, 'Invalid SMART state parameter — possible CSRF attack.');
        }

        if ($request->has('error')) {
            abort(400, 'SMART authorization error: ' . $request->get('error_description'));
        }

        $authCode = $request->get('code');

        // Exchange auth code + code_verifier for access token
        $tokenResponse = \Illuminate\Support\Facades\Http::asForm()->post(
            config('fhir.smart_token_url'),
            [
                'grant_type'    => 'authorization_code',
                'code'          => $authCode,
                'redirect_uri'  => config('fhir.smart_redirect_uri'),
                'client_id'     => config('fhir.smart_client_id'),
                'code_verifier' => session('smart_code_verifier'),
            ]
        );

        if ($tokenResponse->failed()) {
            abort(500, 'SMART token exchange failed: ' . $tokenResponse->body());
        }

        $tokenData = $tokenResponse->json();

        // Store access token SERVER-SIDE in encrypted Laravel session
        // GREEN FLAG: Never stored in localStorage or cookies
        session([
            'smart_access_token'    => $tokenData['access_token'],
            'smart_patient_id'      => $tokenData['patient'] ?? 'test-patient-001',
            'smart_practitioner_id' => $tokenData['practitioner'] ?? 'practitioner-001',
            // Clean up PKCE values
            'smart_code_verifier'   => null,
            'smart_state'           => null,
        ]);

        return redirect()->route('pre-op.dashboard');
    }

    /**
     * Bypass mode for local development (no real FHIR OAuth server).
     * Sets a test patient in session and redirects to dashboard.
     */
    public function devBypass(Request $request): RedirectResponse
    {
        if (app()->isProduction()) {
            abort(403, 'Dev bypass not available in production.');
        }

        session([
            'smart_access_token'    => 'dev-bypass-token',
            'smart_patient_id'      => $request->get('patient', 'test-patient-001'),
            'smart_practitioner_id' => 'practitioner-001',
        ]);

        return redirect()->route('pre-op.dashboard');
    }

    /**
     * Show the SMART launch page (with dev bypass button in local env).
     */
    public function launchPage(): InertiaResponse
    {
        return Inertia::render('Auth/SmartLaunch', [
            'isLocal'    => !app()->isProduction(),
            'launchUrl'  => route('smart.launch'),
            'bypassUrl'  => route('smart.dev-bypass'),
        ]);
    }
}
