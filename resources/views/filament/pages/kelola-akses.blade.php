@php
    $actions = [
        'view' => 'View',
        'create' => 'Create',
        'edit' => 'Edit',
        'delete' => 'Delete',
    ];
@endphp

<x-filament-panels::page>
    <div class="space-y-4">
        <div class="max-w-md">
            {{ $this->form }}
        </div>

        @if ($roleId)
            <x-filament::section>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-white/10">
                                <th class="py-3 pr-4 text-left font-semibold text-gray-950 dark:text-white">
                                    Modul
                                </th>
                                @foreach ($actions as $key => $label)
                                    <th class="py-3 px-4 text-center font-semibold text-gray-950 dark:text-white">
                                        {{ $label }}
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($matrix as $module => $row)
                                <tr class="border-b border-gray-100 dark:border-white/5 last:border-0">
                                    <td class="py-2.5 pr-4 font-medium text-gray-950 dark:text-white">
                                        {{ $row['label'] }}
                                    </td>
                                    @foreach ($actions as $key => $label)
                                        <td class="py-2.5 px-4 text-center">
                                            <x-filament::toggle
                                                :state="'$wire.entangle(' . var_export('matrix.' . $module . '.' . $key, true) . ')'"
                                                on-color="primary"
                                                off-color="gray"
                                            />
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>