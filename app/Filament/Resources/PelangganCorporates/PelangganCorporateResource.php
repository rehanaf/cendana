<?php

namespace App\Filament\Resources\PelangganCorporates;

use App\Filament\Resources\Concerns\HasResourcePermissions;
use App\Filament\Resources\PelangganCorporates\Pages\ManagePelangganCorporates;
use App\Models\PelangganCorporate;
use App\Services\SubscriptionInvoiceService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class PelangganCorporateResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $permissionPrefix = 'corporate_customers';

    protected static ?string $model = PelangganCorporate::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 2;

    protected static string|\UnitEnum|null $navigationGroup = 'Pelanggan';

    public static function getNavigationIcon(): string | BackedEnum | null
    {
        return Heroicon::OutlinedBuildingOffice;
    }

    public static function getNavigationLabel(): string
    {
        return 'Pelanggan Corporate';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Pelanggan Corporate';
    }

    public static function getModelLabel(): string
    {
        return 'Pelanggan Corporate';
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
                    ->label('Nama Perusahaan')
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
                DatePicker::make('contract_start')
                    ->label('Awal Kontrak')
                    ->default(now()),
                Toggle::make('is_subscription')
                    ->label('Pelanggan Langganan')
                    ->helperText('Tagihan bulanan dibuat berdasarkan hari jatuh tempo')
                    ->default(false)
                    ->live(),
                TextInput::make('monthly_fee')
                    ->label('Biaya Bulanan')
                    ->numeric()
                    ->default(0)
                    ->prefix('Rp')
                    ->visible(fn (Get $get): bool => (bool) $get('is_subscription')),
                TextInput::make('due_day')
                    ->label('Jatuh Tempo')
                    ->helperText('Tanggal jatuh tempo tagihan (1-28)')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(28)
                    ->default(1)
                    ->visible(fn (Get $get): bool => (bool) $get('is_subscription')),
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
                    ->label('Nama Perusahaan')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('pic')
                    ->label('PIC')
                    ->searchable(),
                TextColumn::make('pic_phone')
                    ->label('No. Telp PIC')
                    ->searchable(),
                TextColumn::make('contract_start')
                    ->label('Awal Kontrak')
                    ->date('d F Y')
                    ->sortable(),
                TextColumn::make('is_subscription')
                    ->label('Langganan')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Ya' : 'Tidak')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                TextColumn::make('monthly_fee')
                    ->label('Biaya Bulanan')
                    ->money('IDR', decimalPlaces: 0)
                    ->sortable(),
                TextColumn::make('due_day')
                    ->label('Jatuh Tempo')
                    ->sortable(),
                TextColumn::make('sales_count')
                    ->label('Penjualan')
                    ->counts('sales'),
                ToggleColumn::make('is_active')
                    ->label('Aktif'),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('generateInvoice')
                    ->label('Generate Invoice')
                    ->icon('heroicon-o-document-text')
                    ->iconButton()
                    ->color('success')
                    ->schema([
                        Select::make('month')
                            ->label('Bulan')
                            ->options([
                                '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
                                '04' => 'April', '05' => 'Mei', '06' => 'Juni',
                                '07' => 'Juli', '08' => 'Agustus', '09' => 'September',
                                '10' => 'Oktober', '11' => 'November', '12' => 'Desember',
                            ])
                            ->default(now()->format('m'))
                            ->required(),
                        Select::make('year')
                            ->label('Tahun')
                            ->options(fn (): array => collect(range(now()->year, now()->year - 5))
                                ->mapWithKeys(fn (int $year): array => [(string) $year => (string) $year])
                                ->toArray()
                            )
                            ->default((string) now()->year)
                            ->required(),
                    ])
                    ->modalHeading('Generate Invoice Langganan')
                    ->modalSubmitActionLabel('Generate')
                    ->action(function (Action $action, PelangganCorporate $record, array $data): void {
                        $service = app(SubscriptionInvoiceService::class);
                        $result = $service->generateForPeriod((int) $data['year'], (int) $data['month'], $record->id);

                        Notification::make()
                            ->success()
                            ->title('Selesai')
                            ->body("Dibuat: {$result['created']}, dilewati: {$result['skipped']}.")
                            ->send();
                    })
                    ->visible(fn (PelangganCorporate $record): bool => $record->is_subscription && $record->is_active),
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
            'index' => ManagePelangganCorporates::route('/'),
        ];
    }
}