<?php

// Copyright (c) Tribufu. All Rights Reserved.
// SPDX-License-Identifier: MIT

namespace Paymenter\Extensions\Gateways\Pix;

use Illuminate\Support\Str;

/**
 * Payload of a static Pix QR code (BR Code), as the EMV fields of the Pix Initiation Standards Manual of the Central
 * Bank of Brazil.
 */
class BrCode
{
    public function __construct(
        private string $key,
        private string $merchantName,
        private string $merchantCity,
        private ?string $amount = null,
        private ?string $txid = null,
    ) {}

    public function payload(): string
    {
        $merchantAccount = $this->field('00', 'br.gov.bcb.pix') . $this->field('01', $this->key);

        $payload = $this->field('00', '01')
            . $this->field('26', $merchantAccount)
            . $this->field('52', '0000')
            . $this->field('53', '986')
            . ($this->amount !== null ? $this->field('54', $this->amount) : '')
            . $this->field('58', 'BR')
            . $this->field('59', $this->text($this->merchantName, 25))
            . $this->field('60', $this->text($this->merchantCity, 15))
            . $this->field('62', $this->field('05', $this->txid ?: '***'))
            . '6304';

        return $payload . $this->crc16($payload);
    }

    private function field(string $id, string $value): string
    {
        return $id . str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT) . $value;
    }

    // Names and cities are sent without accents, as the manual recommends, and cut to the length of their field.
    private function text(string $value, int $length): string
    {
        return substr(trim(Str::ascii($value)), 0, $length);
    }

    // CRC-16/CCITT-FALSE: polynomial 0x1021, initial value 0xFFFF.
    private function crc16(string $payload): string
    {
        $crc = 0xFFFF;

        foreach (str_split($payload) as $character) {
            $crc ^= ord($character) << 8;

            for ($bit = 0; $bit < 8; $bit++) {
                $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) & 0xFFFF : ($crc << 1) & 0xFFFF;
            }
        }

        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }
}
