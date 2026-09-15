<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center justify-between w-full">
                <div class="flex items-center space-x-2">
                    <div class="p-1.5 bg-purple-100 dark:bg-purple-950/40 text-purple-600 rounded-lg">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    </div>
                    <span class="font-bold text-sm">AI Security & Anomaly Digest</span>
                </div>
                <button wire:click="refreshDigest" type="button" class="text-xs text-indigo-600 hover:text-indigo-800 flex items-center gap-1 font-medium">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Regenerate
                </button>
            </div>
        </x-slot>

        <div class="p-4 rounded-xl bg-gradient-to-br from-indigo-50/50 to-purple-50/30 dark:from-indigo-950/20 dark:to-purple-950/10 border border-indigo-100/60 dark:border-indigo-900/40 text-xs text-gray-700 dark:text-gray-300 leading-relaxed space-y-2">
            <div class="font-semibold text-indigo-950 dark:text-indigo-300 text-xs uppercase tracking-wider flex items-center gap-1">
                <span>Executive AI Intelligence</span>
            </div>
            <p class="whitespace-pre-line">{{ $this->digest }}</p>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
