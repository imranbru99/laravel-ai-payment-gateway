<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center space-x-2">
                <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                <span>Paired SMS Listener Devices & Phone Health</span>
            </div>
        </x-slot>

        <x-slot name="description">
            Monitors real-time heartbeats and battery states of Android phones running the Truvo Pay notification listener.
        </x-slot>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mt-2">
            @forelse($this->devices as $device)
                @php
                    $isOnline = $device->isOnline();
                @endphp
                <div class="p-4 rounded-xl border {{ $isOnline ? 'border-green-200 bg-green-50/20 dark:border-green-900/40 dark:bg-green-950/10' : 'border-red-200 bg-red-50/20 dark:border-red-900/40 dark:bg-red-950/10' }} flex flex-col justify-between space-y-3">
                    <div class="flex items-start justify-between">
                        <div>
                            <h4 class="font-bold text-sm text-gray-900 dark:text-white">{{ $device->name }}</h4>
                            <p class="font-mono text-xs text-gray-500">{{ $device->device_id }}</p>
                        </div>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold {{ $isOnline ? 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300' : 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300' }}">
                            <span class="w-1.5 h-1.5 mr-1.5 rounded-full {{ $isOnline ? 'bg-green-500 animate-pulse' : 'bg-red-500' }}"></span>
                            {{ $isOnline ? 'ONLINE' : 'OFFLINE' }}
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-2 text-xs text-gray-600 dark:text-gray-300 pt-2 border-t border-gray-100 dark:border-gray-800">
                        <div>
                            <span class="text-gray-400">Battery:</span> 
                            <span class="font-semibold {{ ($device->battery_level ?? 100) <= 15 ? 'text-red-500 font-bold' : '' }}">
                                {{ $device->battery_level !== null ? $device->battery_level . '%' : 'Unknown' }}
                            </span>
                        </div>
                        <div>
                            <span class="text-gray-400">Network:</span> 
                            <span class="font-semibold">{{ $device->network_type ?? 'WiFi' }}</span>
                        </div>
                        <div class="col-span-2">
                            <span class="text-gray-400">Last Heartbeat:</span> 
                            <span class="font-medium text-gray-700 dark:text-gray-300">{{ $device->last_seen_at ? $device->last_seen_at->diffForHumans() : 'Never' }}</span>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-3 p-6 text-center text-sm text-gray-500">
                    No listener devices paired yet. Add a device in <strong>Devices & Listeners</strong> to pair an Android phone.
                </div>
            @endforelse
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
