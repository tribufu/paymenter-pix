<?php

// Copyright (c) Tribufu. All Rights Reserved.
// SPDX-License-Identifier: MIT

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use Paymenter\Extensions\Gateways\Pix\Pix;

// The bank appends /pix to the registered address; both are accepted. Anyone can call it, and each charge it names is
// read from the API of the bank, so the calls are limited.
Route::post('/extensions/pix/webhook', [Pix::class, 'webhook'])
    ->middleware('throttle:120,1')
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('extensions.gateways.pix.webhook');
Route::post('/extensions/pix/webhook/pix', [Pix::class, 'webhook'])
    ->middleware('throttle:120,1')
    ->withoutMiddleware([VerifyCsrfToken::class]);

Route::get('/extensions/pix/check/{invoice}', [Pix::class, 'check'])
    ->middleware(['web', 'auth', 'can:view,invoice', 'throttle:10,1'])
    ->name('extensions.gateways.pix.check');
