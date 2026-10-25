<?php

// Copyright (c) Tribufu. All Rights Reserved.
// SPDX-License-Identifier: MIT

namespace Paymenter\Extensions\Gateways\Pix;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Client of the Pix API of the Central Bank of Brazil, which the banks expose to their account holders with OAuth 2
 * client credentials over mutual TLS.
 */
class PixApi
{
    private const SCOPES = 'cob.read cob.write pix.read webhook.read webhook.write';

    public function __construct(
        private string $apiUrl,
        private string $tokenUrl,
        private string $clientId,
        private string $clientSecret,
        private string $certificate,
        private string $privateKey,
    ) {}

    /**
     * Creates an immediate charge with the given transaction id, which the payer has the given seconds to pay.
     */
    public function createCharge(string $txid, string $key, string $amount, string $description, int $expiration): array
    {
        return $this->request('put', 'cob/' . $txid, [
            'calendario' => ['expiracao' => $expiration],
            'valor' => ['original' => $amount],
            'chave' => $key,
            'solicitacaoPagador' => substr($description, 0, 140),
        ]);
    }

    public function getCharge(string $txid): array
    {
        return $this->request('get', 'cob/' . $txid);
    }

    /**
     * Sets the address the bank notifies, with `/pix` appended, when a Pix to the given key is received.
     */
    public function setWebhook(string $key, string $url): void
    {
        $this->request('put', 'webhook/' . rawurlencode($key), ['webhookUrl' => $url]);
    }

    private function request(string $method, string $path, ?array $body = null): array
    {
        return $this->withCertificate(function (PendingRequest $http) use ($method, $path, $body) {
            $http = $http->withToken($this->token($http))->acceptJson();
            $url = rtrim($this->apiUrl, '/') . '/' . $path;

            return ($body === null ? $http->$method($url) : $http->$method($url, $body))->throw()->json() ?? [];
        });
    }

    private function token(PendingRequest $http): string
    {
        $key = 'pix.token.' . sha1($this->tokenUrl . $this->clientId);

        if ($token = Cache::get($key)) {
            return $token;
        }

        $response = (clone $http)->asForm()->post($this->tokenUrl, [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type' => 'client_credentials',
            'scope' => self::SCOPES,
        ])->throw()->json();

        Cache::put($key, $response['access_token'], max(($response['expires_in'] ?? 300) - 60, 60));

        return $response['access_token'];
    }

    // Guzzle reads the client certificate from files, so the ones of the settings are written to temporary files for
    // the request only.
    private function withCertificate(callable $callback): mixed
    {
        $certificate = tempnam(sys_get_temp_dir(), 'pix');
        $privateKey = tempnam(sys_get_temp_dir(), 'pix');

        try {
            file_put_contents($certificate, $this->certificate);
            file_put_contents($privateKey, $this->privateKey);

            return $callback(Http::withOptions(['cert' => $certificate, 'ssl_key' => $privateKey]));
        } finally {
            unlink($certificate);
            unlink($privateKey);
        }
    }
}
