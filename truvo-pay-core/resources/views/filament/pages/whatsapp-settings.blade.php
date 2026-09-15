<x-filament-panels::page>
    <div class="space-y-6 max-w-4xl">
        <div class="p-6 bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between pb-4 border-b border-gray-100 dark:border-gray-700">
                <div class="flex items-center space-x-3">
                    <div class="p-2.5 bg-green-100 dark:bg-green-950/40 text-green-600 rounded-xl">
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-bold text-lg text-gray-900 dark:text-white">WhatsApp Integration Plugin</h3>
                        <p class="text-xs text-gray-500">Automate customer payment receipts, payment links, and merchant security alerts via WhatsApp.</p>
                    </div>
                </div>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300">
                    Active Driver: {{ strtoupper($this->provider) }}
                </span>
            </div>

            <!-- Settings Grid -->
            <div class="mt-6 space-y-5">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 mb-2">WhatsApp Provider Driver</label>
                        <select wire:model.live="provider" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm">
                            <option value="meta">Meta WhatsApp Business Cloud API (Official)</option>
                            <option value="twilio">Twilio WhatsApp API</option>
                            <option value="ultramsg">UltraMsg / Instance Gateway (QR Pair)</option>
                            <option value="fake">Truvo Simulator (Local Test Driver)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 mb-2">Merchant Alert Phone Number</label>
                        <input type="text" wire:model="alertPhone" placeholder="+88017XXXXXXXX" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm">
                        <p class="text-xs text-gray-500 mt-1">Receives instant fraud alerts & device offline warnings.</p>
                    </div>
                </div>

                <!-- Webhook URLs -->
                <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-700">
                    <h4 class="text-xs font-bold uppercase text-gray-500 mb-2">Inbound WhatsApp Webhook URL</h4>
                    <p class="font-mono text-xs text-indigo-600 dark:text-indigo-400 select-all">{{ url('/api/v1/whatsapp/webhook') }}</p>
                    <p class="text-xs text-gray-500 mt-1">Configure this endpoint in your Meta Developer Portal or Twilio Console to enable conversational order tracking and automated SMS proof ingestion.</p>
                </div>

                <!-- Test Message Sender -->
                <div class="pt-4 border-t border-gray-100 dark:border-gray-700">
                    <h4 class="text-sm font-bold text-gray-900 dark:text-white mb-2">Test WhatsApp Delivery</h4>
                    <div class="flex items-center space-x-3">
                        <input 
                            type="text" 
                            wire:model="testRecipient" 
                            placeholder="Enter recipient phone (e.g. +88017XXXXXXXX)"
                            class="flex-1 px-4 py-2.5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm">
                        <button 
                            type="button" 
                            wire:click="sendTestMessage"
                            class="px-5 py-2.5 rounded-xl bg-green-600 hover:bg-green-700 text-white text-sm font-semibold shadow transition">
                            Send Test Message
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
