<?php

namespace App\Filament\Resources\PaketInternets;

use App\Filament\Resources\Concerns\HasResourcePermissions;
use App\Filament\Resources\PaketInternets\Pages\ManagePaketInternets;
use App\Models\PaketInternet;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class PaketInternetResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $permissionPrefix = 'internet_packages';

    protected static ?string $model = PaketInternet::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 1;

    protected static string|\UnitEnum|null $navigationGroup = 'Pelanggan';

    public static function getNavigationIcon(): string | BackedEnum | null
    {
        return Heroicon::OutlinedWifi;
    }

    public static function getNavigationLabel(): string
    {
        return 'Paket Internet';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Paket Internet';
    }

    public static function getModelLabel(): string
    {
        return 'Paket Internet';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->label('Nama Paket')
                    ->required()
                    ->maxLength(255),
                TextInput::make('speed')
                    ->label('Kecepatan')
                    ->placeholder('cth: 10 Mbps')
                    ->maxLength(255),
                TextInput::make('price')
                    ->label('Harga')
                    ->numeric()
                    ->default(0)
                    ->prefix('Rp'),
                TextInput::make('description')
                    ->label('Deskripsi')
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Paket')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('speed')
                    ->label('Kecepatan')
                    ->sortable(),
                TextColumn::make('price')
                    ->label('Harga')
                    ->money('IDR', decimalPlaces: 0)
                    ->sortable(),
                TextColumn::make('pelangganRetails_count')
                    ->label('Pelanggan')
                    ->counts('pelangganRetails'),
                ToggleColumn::make('is_active')
                    ->label('Aktif'),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()->iconButton(),
                DeleteAction::make()->iconButton(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Hapus yang Dipilih'),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePaketInternets::route('/'),
        ];
    }
}
