<x-filament-panels::page>
    <div class="max-w-4xl mx-auto bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden flex flex-col" style="height: 680px;">
        <!-- Chat Header -->
        <div class="px-6 py-4 bg-gradient-to-r from-indigo-600 to-purple-600 text-white flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="p-2 bg-white/20 rounded-lg">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </div>
                <div>
                    <h3 class="font-bold text-base">Truvo AI Merchant Assistant</h3>
                    <p class="text-xs text-indigo-100">Live Transaction Audit & Security Explainer</p>
                </div>
            </div>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-white/20 text-white">
                Powered by AI Hub
            </span>
        </div>

        <!-- Chat Conversation Body -->
        <div class="flex-1 p-6 overflow-y-auto space-y-4 bg-gray-50/50 dark:bg-gray-900/40">
            @foreach($this->chatHistory as $msg)
                @if($msg['role'] === 'assistant')
                    <div class="flex items-start space-x-3">
                        <div class="w-8 h-8 rounded-full bg-indigo-600 text-white flex items-center justify-center flex-shrink-0 text-xs font-bold">
                            AI
                        </div>
                        <div class="p-4 rounded-2xl rounded-tl-none bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-sm text-gray-800 dark:text-gray-200 max-w-2xl shadow-sm whitespace-pre-line leading-relaxed">
                            {!! nl2br(e($msg['message'])) !!}
                        </div>
                    </div>
                @else
                    <div class="flex items-start justify-end space-x-3">
                        <div class="p-4 rounded-2xl rounded-tr-none bg-indigo-600 text-white text-sm max-w-2xl shadow-sm leading-relaxed">
                            {{ $msg['message'] }}
                        </div>
                        <div class="w-8 h-8 rounded-full bg-gray-400 text-white flex items-center justify-center flex-shrink-0 text-xs font-bold">
                            You
                        </div>
                    </div>
                @endif
            @endforeach
        </div>

        <!-- Input Box -->
        <div class="p-4 bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700">
            <form wire:submit.prevent="sendMessage" class="flex items-center space-x-3">
                <input 
                    type="text" 
                    wire:model="userQuery"
                    placeholder="Ask about a transaction (e.g. Why was #TRUVO-ABC1234 flagged?)..."
                    class="flex-1 px-4 py-2.5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none transition">
                <button 
                    type="submit" 
                    class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold shadow transition flex items-center gap-1.5">
                    <span>Send</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                </button>
            </form>
        </div>
    </div>
</x-filament-panels::page>
