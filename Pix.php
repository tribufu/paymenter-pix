<?php

// Copyright (c) Tribufu. All Rights Reserved.
// SPDX-License-Identifier: MIT

namespace Paymenter\Extensions\Gateways\Pix;

use App\Attributes\ExtensionMeta;
use App\Classes\Extension\Gateway;
use App\Helpers\ExtensionHelper;
use App\Models\Invoice;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

#[ExtensionMeta(
    name: 'Pix',
    description: 'Accept Pix payments with a QR code and the Pix copy and paste code, confirmed by the Pix API of the bank or manually.',
    version: '1.0.0',
    author: 'Tribufu',
    url: 'https://github.com/tribufu/paymenter-pix',
    // Pix logo of Simple Icons (CC0), in its brand color.
    icon: 'data:image/svg+xml;base64,PHN2ZyB2aWV3Qm94PSIwIDAgMjQgMjQiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHBhdGggZmlsbD0iIzc3QjZBOCIgZD0iTTUuMjgzIDE4LjM2YTMuNTA1IDMuNTA1IDAgMCAwIDIuNDkzLTEuMDMybDMuNi0zLjZhLjY4NC42ODQgMCAwIDEgLjk0NiAwbDMuNjEzIDMuNjEzYTMuNTA0IDMuNTA0IDAgMCAwIDIuNDkzIDEuMDMyaC43MWwtNC41NiA0LjU2YTMuNjQ3IDMuNjQ3IDAgMCAxLTUuMTU2IDBMNC44NSAxOC4zNlpNMTguNDI4IDUuNjI3YTMuNTA1IDMuNTA1IDAgMCAwLTIuNDkzIDEuMDMybC0zLjYxMyAzLjYxNGEuNjcuNjcgMCAwIDEtLjk0NiAwbC0zLjYtMy42QTMuNTA1IDMuNTA1IDAgMCAwIDUuMjgzIDUuNjRoLS40MzRsNC41NzMtNC41NzJhMy42NDYgMy42NDYgMCAwIDEgNS4xNTYgMGw0LjU1OSA0LjU1OVpNMS4wNjggOS40MjIgMy43OSA2LjY5OWgxLjQ5MmEyLjQ4MyAyLjQ4MyAwIDAgMSAxLjc0NC43MjJsMy42IDMuNmExLjczIDEuNzMgMCAwIDAgMi40NDMgMGwzLjYxNC0zLjYxM2EyLjQ4MiAyLjQ4MiAwIDAgMSAxLjc0NC0uNzIzaDEuNzY3bDIuNzM3IDIuNzM3YTMuNjQ2IDMuNjQ2IDAgMCAxIDAgNS4xNTZsLTIuNzM2IDIuNzM2aC0xLjc2OGEyLjQ4MiAyLjQ4MiAwIDAgMS0xLjc0NC0uNzIybC0zLjYxMy0zLjYxM2ExLjc3IDEuNzcgMCAwIDAtMi40NDQgMGwtMy42IDMuNmEyLjQ4MyAyLjQ4MyAwIDAgMS0xLjc0NC43MjJIMy43OTFsLTIuNzIzLTIuNzIzYTMuNjQ2IDMuNjQ2IDAgMCAxIDAtNS4xNTYiLz48L3N2Zz4=',
)]
class Pix extends Gateway
{
    // Seconds the payer has to pay a charge of the Pix API.
    private const CHARGE_EXPIRATION = 3600;

    public function boot()
    {
        require __DIR__ . '/routes.php';
        View::addNamespace('gateways.pix', __DIR__ . '/resources/views');
        app('translator')->addJsonPath(__DIR__ . '/lang');
    }

    public function getConfig($values = [])
    {
        return [
            [
                'name' => 'pix_key',
                'label' => 'Pix Key',
                'type' => 'text',
                'required' => true,
                'description' => 'CPF or CNPJ with digits only, email, phone with the country code (+5511999999999), or random key.',
            ],
            [
                'name' => 'merchant_name',
                'label' => 'Receiver Name',
                'type' => 'text',
                'required' => true,
                'description' => 'Name of the account that receives the payments, up to 25 characters.',
            ],
            [
                'name' => 'merchant_city',
                'label' => 'Receiver City',
                'type' => 'text',
                'required' => true,
                'description' => 'City of the account that receives the payments, up to 15 characters.',
            ],
            [
                'name' => 'api',
                'type' => 'placeholder',
                'label' => 'Pix API of the bank, to confirm payments automatically. Leave it empty to confirm them manually. Saving the gateway registers the webhook in the bank.',
            ],
            [
                'name' => 'api_url',
                'label' => 'Pix API URL',
                'type' => 'text',
                'required' => false,
                'description' => 'Such as https://cdpj.partners.bancointer.com.br/pix/v2 for Banco Inter.',
            ],
            [
                'name' => 'token_url',
                'label' => 'Token URL',
                'type' => 'text',
                'required' => false,
                'description' => 'Such as https://cdpj.partners.bancointer.com.br/oauth/v2/token for Banco Inter.',
            ],
            [
                'name' => 'client_id',
                'label' => 'Client ID',
                'type' => 'text',
                'required' => false,
            ],
            [
                'name' => 'client_secret',
                'label' => 'Client Secret',
                'type' => 'password',
                'required' => false,
                'encrypted' => true,
            ],
            [
                'name' => 'certificate',
                'label' => 'Certificate',
                'type' => 'textarea',
                'required' => false,
                'encrypted' => true,
                'description' => 'Client certificate issued by the bank, in PEM.',
            ],
            [
                'name' => 'private_key',
                'label' => 'Private Key',
                'type' => 'textarea',
                'required' => false,
                'encrypted' => true,
                'description' => 'Private key of the certificate, in PEM.',
            ],
        ];
    }

    // Pix only moves Brazilian reais.
    public function canUseGateway($total, $currency, $type, $items = [])
    {
        return $currency === 'BRL';
    }

    public function updated($gateway)
    {
        $this->api()?->setWebhook($this->config('pix_key'), route('extensions.gateways.pix.webhook'));
    }

    public function pay(Invoice $invoice, $total)
    {
        $amount = number_format($total, 2, '.', '');

        if ($api = $this->api()) {
            // A charge is reused while it can be paid, so opening the payment again shows the same code.
            $charge = Cache::remember("pix.charge.{$invoice->id}.{$amount}", self::CHARGE_EXPIRATION - 60, function () use ($api, $invoice, $amount) {
                $txid = $this->chargeTxid($invoice);
                $description = __('invoices.payment_for_invoice', ['number' => $invoice->number ?? $invoice->id]);
                $charge = $api->createCharge($txid, $this->config('pix_key'), $amount, $description, self::CHARGE_EXPIRATION);

                return ['txid' => $txid, 'payload' => $charge['pixCopiaECola']];
            });

            $payload = $charge['payload'];
            $paidUrl = route('extensions.gateways.pix.check', ['invoice' => $invoice, 'txid' => $charge['txid']]);
        } else {
            $payload = (new BrCode($this->config('pix_key'), $this->config('merchant_name'), $this->config('merchant_city'), $amount, self::staticTxid($invoice)))->payload();
            $paidUrl = route('invoices.show', $invoice) . '?checkPayment';
        }

        return view('gateways.pix::pay', [
            'payload' => $payload,
            'qrCode' => (new SvgWriter)->write(new QrCode($payload, size: 280, margin: 12))->getDataUri(),
            'paidUrl' => $paidUrl,
        ]);
    }

    /**
     * Notification of the bank of received Pix. Its content is not trusted: each charge is read from the API.
     */
    public function webhook(Request $request)
    {
        $txids = array_filter(array_column(array_filter((array) $request->input('pix', []), 'is_array'), 'txid'), 'is_string');

        // Each charge is read once, however many times the notification repeats it.
        foreach (array_unique($txids) as $txid) {
            $this->confirm($txid);
        }

        return response()->noContent();
    }

    /**
     * Checks the charge of the invoice when the payer says it is paid, in case the notification of the bank is late.
     */
    public function check(Request $request, Invoice $invoice): RedirectResponse
    {
        $txid = (string) $request->query('txid');

        if ($this->invoiceOf($txid) === $invoice->id) {
            $this->confirm($txid);
        }

        return redirect()->to(route('invoices.show', $invoice) . '?checkPayment');
    }

    // Records the Pix of a paid charge on its invoice, once each, by its end to end id.
    private function confirm(string $txid): void
    {
        $api = $this->api();
        $invoiceId = $this->invoiceOf($txid);

        if (!$api || !$invoiceId || !Invoice::whereKey($invoiceId)->exists()) {
            return;
        }

        $charge = $api->getCharge($txid);

        if (($charge['status'] ?? null) !== 'CONCLUIDA') {
            return;
        }

        foreach ($charge['pix'] ?? [] as $pix) {
            ExtensionHelper::addPayment($invoiceId, 'Pix', $pix['valor'], transactionId: $pix['endToEndId']);
        }
    }

    /**
     * Transaction id of the static QR code, which identifies the invoice in the statement of the receiver: the letters
     * and digits of its number, up to 25.
     */
    public static function staticTxid(Invoice $invoice): string
    {
        return substr(preg_replace('/[^A-Za-z0-9]/', '', (string) ($invoice->number ?? $invoice->id)), 0, 25);
    }

    // A charge transaction id has 26 to 35 letters and digits: here the padded invoice id and a random part.
    private function chargeTxid(Invoice $invoice): string
    {
        return 'INV' . str_pad((string) $invoice->id, 10, '0', STR_PAD_LEFT) . Str::random(19);
    }

    private function invoiceOf(string $txid): ?int
    {
        return preg_match('/^INV(\d{10})[A-Za-z0-9]{19}$/', $txid, $matches) ? (int) $matches[1] : null;
    }

    private function api(): ?PixApi
    {
        if (!$this->config('api_url')) {
            return null;
        }

        return new PixApi(
            $this->config('api_url'),
            $this->config('token_url'),
            $this->config('client_id'),
            $this->config('client_secret'),
            $this->config('certificate'),
            $this->config('private_key'),
        );
    }
}
