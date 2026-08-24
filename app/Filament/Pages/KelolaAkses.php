<?php

namespace App\Filament\Pages;

use App\Models\Permission;
use App\Models\Role;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class KelolaAkses extends Page
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.kelola-akses';

    protected static ?int $navigationSort = 2;

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan';

    public ?int $roleId = null;

    public array $matrix = [];

    public static function getNavigationIcon(): string | BackedEnum | null
    {
        return Heroicon::OutlinedLockClosed;
    }

    public static function getNavigationLabel(): string
    {
        return 'Hak Akses';
    }

    public static function getDefaultSlug(): string
    {
        return 'kelola-akses';
    }

    public function getTitle(): string
    {
        return 'Hak Akses';
    }

    public function mount(): void
    {
        $this->form->fill();

        $this->roleId = Role::query()
            ->where('name', '!=', 'Administrator')
            ->orderBy('name')
            ->value('id')
            ?? Role::query()->orderBy('name')->value('id');

        $this->loadMatrix();
    }

    public static function canAccess(): bool
    {
        return (bool) (auth()->user()?->isAdmin());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('roleId')
                    ->label('Jabatan')
                    ->placeholder('Pilih Jabatan')
                    ->options(
                        Role::query()
                            ->orderBy('name')
                            ->pluck('name', 'id')
                    )
                    ->preload()
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(fn () => $this->loadMatrix()),
            ]);
    }

    protected function loadMatrix(): void
    {
        $matrix = [];
        $role = Role::find($this->roleId);

        if ($role) {
            $permissionNames = $role->permissions()->pluck('name');
            foreach (static::modules() as $module => $label) {
                $matrix[$module] = [
                    'label' => $label,
                    'create' => $permissionNames->contains("create_{$module}"),
                    'view' => $permissionNames->contains("view_{$module}"),
                    'edit' => $permissionNames->contains("edit_{$module}"),
                    'delete' => $permissionNames->contains("delete_{$module}"),
                ];
            }
        }

        $this->matrix = $matrix;
    }

    public function save(): void
    {
        $role = Role::find($this->roleId);

        if (! $role) {
            return;
        }

        foreach ($this->matrix as $module => $row) {
            foreach (['view', 'create', 'edit', 'delete'] as $action) {
                if (! array_key_exists($action, $row)) {
                    continue;
                }

                $permission = Permission::firstOrCreate([
                    'name' => "{$action}_{$module}",
                    'guard_name' => 'web',
                ]);

                if ($row[$action]) {
                    $role->permissions()->syncWithoutDetaching([$permission->id]);
                } else {
                    $role->permissions()->detach($permission->id);
                }
            }
        }

        $this->loadMatrix();

        Notification::make()
            ->title('Hak akses berhasil disimpan')
            ->success()
            ->send();
    }

    public static function modules(): array
    {
        $names = Permission::query()->pluck('name');

        $modules = [];
        foreach ($names as $name) {
            if (preg_match('/^(view|create|edit|delete)_(.+)$/', $name, $m)) {
                $modules[$m[2]] = static::moduleLabel($m[2]);
            }
        }

        ksort($modules);

        return $modules;
    }

    protected static function moduleLabel(string $module): string
    {
        return match ($module) {
            'sales' => 'Penjualan',
            'purchases' => 'Pembelian',
            'subscription_invoices' => 'Tagihan Langganan',
            'retail_invoices' => 'Nota Retail',
            'corporate_customers' => 'Pelanggan Corporate',
            'retail_customers' => 'Pelanggan Retail',
            'internet_packages' => 'Paket Internet',
            'inventory_items' => 'Persediaan Barang',
            'trouble_tickets' => 'Tiket Gangguan',
            'sops' => 'SOP',
            'vendors' => 'Vendor',
            'transactions' => 'Transaksi',
            'wallets' => 'Dompet',
            'coas' => 'COA',
            default => ucwords(str_replace('_', ' ', $module)),
        };
    }
}