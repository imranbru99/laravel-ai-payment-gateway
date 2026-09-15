<x-filament-panels::page>
    <div class="space-y-6">
        @if($this->flaggedTransactions->isEmpty())
            <div class="p-8 text-center bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
                <div class="flex justify-center mb-3">
                    <div class="p-3 bg-green-100 dark:bg-green-900/30 text-green-600 rounded-full">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                </div>
                <h3 class="text-lg font-bold text-gray-900 dark:text-white">AI Review Queue is Clear</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">All incoming manual transactions have either been automatically verified or reviewed.</p>
            </div>
        @else
            <div class="grid grid-cols-1 gap-6">
                @foreach($this->flaggedTransactions as $item)
                    @php
                        $matchedSms = $item->matchedSmsLogs->first();
                    @endphp
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <!-- Header Bar -->
                        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700 flex flex-wrap items-center justify-between gap-4">
                            <div class="flex items-center space-x-3">
                                <span class="font-mono text-sm font-bold text-gray-800 dark:text-gray-200">#{{ $item->truvo_reference }}</span>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
                                    Flagged for Review
                                </span>
                                @if($item->ai_confidence_score !== null)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $item->ai_confidence_score >= 80 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800' }}">
                                        AI Score: {{ $item->ai_confidence_score }}%
                                    </span>
                                @endif
                            </div>
                            <div class="text-xs text-gray-500">
                                Created: {{ $item->created_at->diffForHumans() }}
                            </div>
                        </div>

                        <!-- Side-by-Side Comparison -->
                        <div class="p-6 grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <!-- Left: Customer Order Expectation -->
                            <div class="p-4 rounded-lg bg-gray-50/80 dark:bg-gray-900/40 border border-gray-100 dark:border-gray-800 space-y-3">
                                <h4 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                                    <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                                    Customer Order Expectation
                                </h4>
                                <div class="grid grid-cols-2 gap-2 text-sm">
                                    <div><span class="text-gray-500 text-xs">Order ID:</span> <div class="font-semibold">{{ $item->merchant_order_id }}</div></div>
                                    <div><span class="text-gray-500 text-xs">Amount:</span> <div class="font-bold text-indigo-600 dark:text-indigo-400">{{ $item->currency }} {{ number_format($item->amount, 2) }}</div></div>
                                    <div><span class="text-gray-500 text-xs">Gateway:</span> <div class="font-medium">{{ $item->gateway_key }}</div></div>
                                    <div><span class="text-gray-500 text-xs">Customer Phone:</span> <div class="font-mono text-xs">{{ $item->customer_phone ?? 'N/A' }}</div></div>
                                    <div class="col-span-2"><span class="text-gray-500 text-xs">Customer Reported Sender:</span> <div class="font-mono text-xs text-amber-600 dark:text-amber-400 font-semibold">{{ $item->sender_number ?? 'Not provided' }}</div></div>
                                </div>
                            </div>

                            <!-- Right: Raw Forwarded SMS & AI Extraction -->
                            <div class="p-4 rounded-lg bg-indigo-50/40 dark:bg-indigo-950/20 border border-indigo-100 dark:border-indigo-900/30 space-y-3">
                                <h4 class="text-xs font-bold uppercase tracking-wider text-indigo-700 dark:text-indigo-300 flex items-center gap-1.5">
                                    <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                    Raw SMS & AI Extraction
                                </h4>
                                @if($matchedSms)
                                    <div class="p-3 bg-white dark:bg-gray-900 rounded border border-gray-200 dark:border-gray-700 font-mono text-xs text-gray-800 dark:text-gray-200 break-words">
                                        {{ $matchedSms->raw_body }}
                                    </div>
                                    <div class="grid grid-cols-2 gap-2 text-xs pt-1">
                                        <div><span class="text-gray-500">Sender Header:</span> <span class="font-bold">{{ $matchedSms->sender_address }}</span></div>
                                        <div><span class="text-gray-500">Extracted Amount:</span> <span class="font-bold text-green-600">৳{{ number_format($matchedSms->parsed_amount ?? 0, 2) }}</span></div>
                                        <div><span class="text-gray-500">Extracted TrxID:</span> <span class="font-mono font-bold">{{ $matchedSms->parsed_txid ?? 'None' }}</span></div>
                                        <div><span class="text-gray-500">Parsed Mobile:</span> <span class="font-mono">{{ $matchedSms->parsed_sender ?? 'None' }}</span></div>
                                    </div>
                                @else
                                    <div class="text-xs text-gray-500 italic p-3 bg-white dark:bg-gray-900 rounded border border-dashed border-gray-300">
                                        No exact candidate SMS auto-linked. Customer may have sent payment to an alternate number or SMS delivery is pending.
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- AI Reasoning Alert -->
                        <div class="px-6 pb-4">
                            <div class="p-3 rounded-lg bg-amber-50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-800/40 text-xs text-amber-900 dark:text-amber-200 flex items-start gap-2">
                                <svg class="w-4 h-4 text-amber-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
                                <div>
                                    <strong>AI Reasoning:</strong> {{ $item->ai_reasoning ?: 'Flagged due to confidence score below threshold or missing parameter match.' }}
                                </div>
                            </div>
                        </div>

                        <!-- Action Footer -->
                        <div class="px-6 py-3 bg-gray-50 dark:bg-gray-900/50 border-t border-gray-200 dark:border-gray-700 flex items-center justify-end space-x-3">
                            <button 
                                type="button"
                                wire:click="rejectTransaction({{ $item->id }})"
                                class="px-3.5 py-1.5 text-xs font-semibold rounded-lg border border-red-300 dark:border-red-800 text-red-600 hover:bg-red-50 dark:hover:bg-red-950/50 transition">
                                Reject Payment
                            </button>
                            <button 
                                type="button"
                                wire:click="approveTransaction({{ $item->id }}, '{{ $matchedSms?->parsed_txid ?? $item->transaction_id }}')"
                                class="px-4 py-1.5 text-xs font-semibold rounded-lg bg-green-600 text-white hover:bg-green-700 shadow-sm transition">
                                Approve Payment (TrxID: {{ $matchedSms?->parsed_txid ?? 'Confirm' }})
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
