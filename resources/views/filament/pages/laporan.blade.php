<x-filament-panels::page>
    <div class="space-y-6">
        @foreach ($categories as $category => $data)
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        @if (isset($data['icon']))
                            <x-filament::icon :icon="$data['icon']" class="h-5 w-5 text-primary-500" />
                        @endif
                        <span>{{ $category }}</span>
                    </div>
                </x-slot>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($data['items'] as $item)
                        <a
                            href="{{ $item['url'] }}"
                            class="group flex items-start gap-3 rounded-xl border border-gray-200 p-4 transition hover:border-primary-500 hover:bg-primary-50 dark:border-white/10 dark:hover:border-primary-500 dark:hover:bg-primary-950"
                        >
                            <span class="mt-0.5 shrink-0 rounded-lg bg-gray-100 p-2 text-gray-500 transition group-hover:bg-primary-100 group-hover:text-primary-600 dark:bg-white/10 dark:text-gray-400">
                                <x-filament::icon :icon="$item['icon']" class="h-5 w-5" />
                            </span>
                            <span>
                                <span class="block text-sm font-semibold text-gray-900 group-hover:text-primary-600 dark:text-white">
                                    {{ $item['label'] }}
                                </span>
                                <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">
                                    {{ $item['description'] }}
                                </span>
                            </span>
                        </a>
                    @endforeach
                </div>
            </x-filament::section>
        @endforeach
    </div>
</x-filament-panels::page>
