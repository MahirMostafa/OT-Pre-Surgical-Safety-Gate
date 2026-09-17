<?php

return [
    /*
    |--------------------------------------------------------------------------
    | FHIR Server Configuration
    |--------------------------------------------------------------------------
    */
    'base_url' => env('FHIR_BASE_URL', 'http://hapi-fhir:8080/fhir'),

    /*
    |--------------------------------------------------------------------------
    | SMART on FHIR OAuth 2.0 Configuration
    |--------------------------------------------------------------------------
    */
    'smart_client_id'    => env('SMART_CLIENT_ID', 'ot-safety-gate-local'),
    'smart_redirect_uri' => env('SMART_REDIRECT_URI', 'http://localhost/smart/callback'),
    'smart_scope'        => env('SMART_SCOPE', 'openid fhirUser launch patient/*.read'),
    'smart_authorize_url'=> env('SMART_AUTHORIZE_URL', 'http://localhost:8080/fhir/smart/authorize'),
    'smart_token_url'    => env('SMART_TOKEN_URL', 'http://localhost:8080/fhir/smart/token'),
];
