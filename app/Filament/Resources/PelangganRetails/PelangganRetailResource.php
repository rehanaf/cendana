<?php

namespace App\Filament\Resources\PelangganRetails;

use App\Filament\Resources\Concerns\HasResourcePermissions;
use App\Filament\Resources\PelangganRetails\Pages\ManagePelangganRetails;
use App\Models\PelangganRetail;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class PelangganRetailResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $permissionPrefix = 'retail_customers';

    protected static ?string $model = PelangganRetail::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 3;

    protected static string|\UnitEnum|null $navigationGroup = 'Pelanggan';

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return Heroicon::OutlinedUserGroup;
    }

    public static function getNavigationLabel(): string
    {
        return 'Pelanggan Retail';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Pelanggan Retail';
    }

    public static function getModelLabel(): string
    {
        return 'Pelanggan Retail';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('customer_code')
                    ->label('ID Pelanggan')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                TextInput::make('name')
                    ->label('Nama')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->maxLength(255),
                DatePicker::make('subscription_start_date')
                    ->label('Tgl Mulai Berlangganan')
                    ->default(now()),
                Select::make('internet_package_id')
                    ->label('Paket Internet')
                    ->relationship('paketInternet', 'name')
                    ->searchable()
                    ->preload(),
                TextInput::make('marketing_cost')
                    ->label('Biaya Marketing Bulanan')
                    ->helperText('Biaya marketing yang dibebankan tiap invoice retail pelanggan ini')
                    ->numeric()
                    ->default(0)
                    ->prefix('Rp'),
                TextInput::make('block_location')
                    ->label('Lokasi/Blok')
                    ->maxLength(255),
                Textarea::make('full_address')
                    ->label('Alamat Lengkap')
                    ->rows(3)
                    ->columnSpanFull(),
                TextInput::make('wa')
                    ->label('No. WhatsApp')
                    ->tel()
                    ->maxLength(255),
                TextInput::make('nik')
                    ->label('NIK Pelanggan')
                    ->maxLength(255),
                TextInput::make('billing_customer_id')
                    ->label('ID Billing Customer')
                    ->maxLength(255),
                TextInput::make('billing_username')
                    ->label('Username Billing')
                    ->maxLength(255),
                TextInput::make('reference')
                    ->label('Marketing / Referral')
                    ->maxLength(255),
                Textarea::make('notes')
                    ->label('Keterangan (untuk Invoice)')
                    ->helperText('Keterangan default yang otomatis terisi di invoice retail')
                    ->rows(3)
                    ->columnSpanFull(),
                TextInput::make('ktp_birth_address')
                    ->label('Alamat Lahir (KTP)')
                    ->maxLength(255),
                DatePicker::make('ktp_birth_date')
                    ->label('Tgl Lahir (KTP)')
                    ->default(now()),
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
                TextColumn::make('customer_code')
                    ->label('ID Pelanggan')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('paketInternet.name')
                    ->label('Paket')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('block_location')
                    ->label('Lokasi/Blok')
                    ->searchable(),
                TextColumn::make('marketing_cost')
                    ->label('Biaya Marketing')
                    ->money('IDR', decimalPlaces: 0)
                    ->placeholder('-')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('wa')
                    ->label('WhatsApp')
                    ->searchable(),
                TextColumn::make('subscription_start_date')
                    ->label('Mulai Langganan')
                    ->date('d F Y')
                    ->sortable(),
                TextColumn::make('nik')
                    ->label('NIK')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('billing_username')
                    ->label('Username Billing')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('reference')
                    ->label('Marketing')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('notes')
                    ->label('Keterangan')
                    ->limit(30)
                    ->toggleable(),
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
            'index' => ManagePelangganRetails::route('/'),
        ];
    }
}
