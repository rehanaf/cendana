<?php

namespace App\Filament\Resources\Vendors;

use App\Filament\Resources\Concerns\HasResourcePermissions;
use App\Filament\Resources\Vendors\Pages\ManageVendors;
use App\Models\Vendor;
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

class VendorResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $permissionPrefix = 'vendors';

    protected static ?string $model = Vendor::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 1;

    protected static string|\UnitEnum|null $navigationGroup = 'Pembelian';

    public static function getNavigationIcon(): string | BackedEnum | null
    {
        return Heroicon::OutlinedBuildingOffice2;
    }

    public static function getNavigationLabel(): string
    {
        return 'Vendor';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Vendor';
    }

    public static function getModelLabel(): string
    {
        return 'Vendor';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->label('Nama Vendor')
                    ->required()
                    ->maxLength(255),
                Textarea::make('address')
                    ->label('Alamat')
                    ->rows(3)
                    ->columnSpanFull(),
                TextInput::make('pic')
                    ->label('PIC')
                    ->maxLength(255),
                TextInput::make('pic_phone')
                    ->label('No. Telp PIC')
                    ->tel()
                    ->maxLength(255),
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
                    ->label('Nama Vendor')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('pic')
                    ->label('PIC')
                    ->searchable(),
                TextColumn::make('pic_phone')
                    ->label('No. Telp PIC')
                    ->searchable(),
                TextColumn::make('purchases_count')
                    ->label('Pembelian')
                    ->counts('purchases'),
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
            'index' => ManageVendors::route('/'),
        ];
    }
}
