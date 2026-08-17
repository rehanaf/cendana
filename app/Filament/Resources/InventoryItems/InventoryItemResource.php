<?php

namespace App\Filament\Resources\InventoryItems;

use App\Filament\Resources\Concerns\HasResourcePermissions;
use App\Filament\Resources\InventoryItems\Pages\ManageInventoryItems;
use App\Models\InventoryItem;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class InventoryItemResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $permissionPrefix = 'inventory_items';

    protected static ?string $model = InventoryItem::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 5;

    protected static string|\UnitEnum|null $navigationGroup = 'Penjualan';

    public static function getNavigationIcon(): string | BackedEnum | null
    {
        return Heroicon::OutlinedCube;
    }

    public static function getNavigationLabel(): string
    {
        return 'Persediaan Barang';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Persediaan Barang';
    }

    public static function getModelLabel(): string
    {
        return 'Barang';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->label('Nama Barang')
                    ->required()
                    ->maxLength(255),
                TextInput::make('price')
                    ->label('Harga')
                    ->numeric()
                    ->prefix('Rp')
                    ->required()
                    ->default(0),
                TextInput::make('minimum_stock')
                    ->label('Stok Minimum')
                    ->numeric()
                    ->required()
                    ->default(0),
                TextInput::make('stock')
                    ->label('Stok')
                    ->numeric()
                    ->required()
                    ->default(0)
                    ->live()
                    ->afterStateUpdated(function (TextInput $component): void {
                        $stock = (float) $component->getState();
                        $minimum = (float) $component->getContainer()->getComponent('minimum_stock')?->getState() ?? 0;
                        $component->getContainer()->getComponent('status')?->state($stock <= $minimum ? 'to_be_order' : 'in_stock');
                    }),
                TextInput::make('status')
                    ->label('Status')
                    ->disabled()
                    ->dehydrated()
                    ->default('in_stock'),
                Textarea::make('notes')
                    ->label('Catatan')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Barang')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('price')
                    ->label('Harga')
                    ->money('IDR', decimalPlaces: 0)
                    ->sortable(),
                TextColumn::make('stock')
                    ->label('Stok')
                    ->numeric(decimalPlaces: 2)
                    ->sortable()
                    ->color(fn ($record): string => (float) $record->stock <= (float) $record->minimum_stock ? 'danger' : 'success'),
                TextColumn::make('minimum_stock')
                    ->label('Stok Minimum')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'in_stock' => 'Tersedia',
                        'to_be_order' => 'Perlu Dipesan',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'in_stock' => 'success',
                        'to_be_order' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('notes')
                    ->label('Catatan')
                    ->limit(40),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'in_stock' => 'Tersedia',
                        'to_be_order' => 'Perlu Dipesan',
                    ]),
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
            'index' => ManageInventoryItems::route('/'),
        ];
    }
}
