<?php

namespace App\Filament\Resources\Coas;

use App\Filament\Resources\Coas\Pages\ManageCoas;
use App\Models\Coa;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class CoaResource extends Resource
{
    protected static ?string $model = Coa::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 3;

    protected static string|\UnitEnum|null $navigationGroup = 'Kas';

    public static function getNavigationIcon(): string | BackedEnum | null
    {
        return Heroicon::OutlinedBookOpen;
    }

    public static function getNavigationLabel(): string
    {
        return 'Chart of Account';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Chart of Account';
    }

    public static function getModelLabel(): string
    {
        return 'COA';
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->isAdmin() || $user?->hasPermission('view_coas'));
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('code')
                    ->label('Kode Akun')
                    ->required()
                    ->maxLength(255),
                TextInput::make('name')
                    ->label('Nama Akun')
                    ->required()
                    ->maxLength(255),
                Select::make('type')
                    ->label('Tipe')
                    ->options([
                        'asset' => 'Aset',
                        'liability' => 'Kewajiban',
                        'equity' => 'Modal',
                        'income' => 'Pendapatan',
                        'expense' => 'Beban',
                        'tax' => 'Pajak',
                    ])
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Get $get, Set $set) {
                        $category = match ($get('type')) {
                            'income' => 'pemasukan',
                            'expense', 'tax' => 'pengeluaran',
                            default => null,
                        };
                        if ($category) {
                            $set('category', $category);
                        }
                    }),
                Select::make('category')
                    ->label('Kategori')
                    ->options([
                        'pemasukan' => 'Pemasukan',
                        'pengeluaran' => 'Pengeluaran',
                        'transfer' => 'Transfer',
                    ])
                    ->required(),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Kode')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Nama Akun')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category')
                    ->label('Kategori')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'pemasukan' => 'Pemasukan',
                        'pengeluaran' => 'Pengeluaran',
                        'transfer' => 'Transfer',
                        default => '-',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'pemasukan' => 'success',
                        'pengeluaran' => 'danger',
                        'transfer' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('type')
                    ->label('Tipe')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'asset' => 'Aset',
                        'liability' => 'Kewajiban',
                        'equity' => 'Modal',
                        'income' => 'Pendapatan',
                        'expense' => 'Beban',
                        'tax' => 'Pajak',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'asset' => 'info',
                        'liability' => 'warning',
                        'equity' => 'success',
                        'income' => 'success',
                        'expense' => 'danger',
                        'tax' => 'info',
                        default => 'gray',
                    }),
                ToggleColumn::make('is_active')
                    ->label('Aktif')
                    ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false),
            ])
            ->recordActions([
                EditAction::make()
                    ->iconButton()
                    ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false),
                DeleteAction::make()
                    ->iconButton()
                    ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false)
                    ->action(function (DeleteAction $action, Coa $record): void {
                        $usage = $record->usageLabels();

                        if ($usage->isNotEmpty()) {
                            Notification::make()
                                ->danger()
                                ->title('COA tidak dapat dihapus')
                                ->body('Masih dipakai oleh: ' . $usage->implode(', ') . '. Nonaktifkan COA ini bila tidak digunakan lagi.')
                                ->persistent()
                                ->send();

                            $action->halt();

                            return;
                        }

                        $record->delete();

                        Notification::make()
                            ->success()
                            ->title('COA berhasil dihapus')
                            ->send();
                    }),
            ])
            ->defaultSort('code');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCoas::route('/'),
        ];
    }
}
