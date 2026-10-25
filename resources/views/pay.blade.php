<div class="flex flex-col items-center gap-4 text-center" x-data="{ copied: false }">
    <p class="text-sm text-muted">{{ __('Scan the QR code with the app of your bank, or copy the Pix code below.') }}</p>

    <img src="{{ $qrCode }}" alt="{{ __('Pix QR code') }}" class="size-64 rounded-md bg-white">

    <div class="flex w-full gap-2">
        <input type="text" readonly value="{{ $payload }}" x-ref="payload"
            class="w-full min-w-0 rounded-md border border-neutral bg-background px-3 py-2 text-sm text-base"
            @focus="$event.target.select()">
        <x-button.secondary type="button" class="!w-auto shrink-0"
            @click="navigator.clipboard.writeText($refs.payload.value); copied = true; setTimeout(() => copied = false, 2000)">
            <span x-show="!copied">{{ __('Copy') }}</span>
            <span x-show="copied" x-cloak>{{ __('Copied') }}</span>
        </x-button.secondary>
    </div>

    <p class="text-sm text-muted">{{ __('The invoice is marked as paid once the payment is confirmed.') }}</p>

    <a href="{{ $paidUrl }}" class="w-full">
        <x-button.primary type="button">{{ __('I have paid') }}</x-button.primary>
    </a>
</div>
