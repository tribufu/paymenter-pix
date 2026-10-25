<?php

// Copyright (c) Tribufu. All Rights Reserved.
// SPDX-License-Identifier: MIT

namespace Paymenter\Extensions\Gateways\Pix\Admin\Pages;

use App\Admin\Resources\InvoiceResource;
use App\Helpers\ExtensionHelper;
use App\Models\Invoice;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Paymenter\Extensions\Gateways\Pix\Pix;

/**
 * Pending invoices in BRL, to record the Pix that the receiver saw arrive in the bank when the payments are confirmed
 * by hand.
 */
class PixPayments extends Page implements HasActions, HasTable
{
    use InteractsWithActions, InteractsWithTable;

    protected string $view = 'gateways.pix::admin.payments';

    protected static string|\BackedEnum|null $navigationIcon = 'ri-qr-code-line';

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    protected static ?string $navigationLabel = 'Pix';

    protected static ?string $title = 'Pix';

    public static function canAccess(): bool
    {
        return Auth::user()?->hasPermission('admin.invoices.update') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Invoice::query()->where('status', 'pending')->where('currency_code', 'BRL')->with('user'))
            ->defaultSort('created_at', 'desc')
            ->description(__('Pending invoices in BRL. Confirm one once its Pix is in the statement of the bank.'))
            ->columns([
                TextColumn::make('number')
                    ->label(__('Invoice'))
                    ->state(fn (Invoice $invoice) => $invoice->number ?? $invoice->id)
                    ->description(fn (Invoice $invoice) => __('Pix identifier: :txid', ['txid' => Pix::staticTxid($invoice)]))
                    ->url(fn (Invoice $invoice) => InvoiceResource::getUrl('edit', ['record' => $invoice])),
                TextColumn::make('user.name')
                    ->label(__('Customer'))
                    ->description(fn (Invoice $invoice) => $invoice->user?->email)
                    ->searchable(['first_name', 'last_name', 'email']),
                TextColumn::make('remaining')
                    ->label(__('Amount due'))
                    ->state(fn (Invoice $invoice) => $invoice->formattedRemaining),
                TextColumn::make('created_at')
                    ->label(__('Created'))
                    ->since()
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('confirm')
                    ->label(__('Confirm Pix'))
                    ->icon('ri-check-line')
                    ->modalHeading(fn (Invoice $invoice) => __('Confirm the Pix of invoice :number', ['number' => $invoice->number ?? $invoice->id]))
                    ->schema([
                        TextInput::make('transaction_id')
                            ->label(__('End to end id'))
                            ->helperText(__('Optional. Shown on the receipt of the Pix in the bank; with it, the same Pix is never recorded twice.'))
                            ->maxLength(64),
                        TextInput::make('amount')
                            ->label(__('Amount received'))
                            ->numeric()
                            ->minValue(0.01)
                            ->required()
                            ->default(fn (Invoice $invoice) => number_format($invoice->remaining, 2, '.', '')),
                    ])
                    ->action(function (Invoice $invoice, array $data) {
                        ExtensionHelper::addPayment($invoice, 'Pix', $data['amount'], transactionId: trim($data['transaction_id'] ?? '') ?: null);

                        Notification::make()
                            ->title(__('Pix confirmed'))
                            ->body($invoice->fresh()->status === 'paid' ? __('The invoice is paid.') : __('The invoice still has an amount due.'))
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
