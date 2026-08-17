<?php

namespace App\Filament\Resources\TroubleTickets;

use App\Filament\Resources\Concerns\HasResourcePermissions;
use App\Filament\Resources\TroubleTickets\Pages\ManageTroubleTickets;
use App\Models\TroubleTicket;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TroubleTicketResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $permissionPrefix = 'trouble_tickets';

    protected static ?string $model = TroubleTicket::class;

    protected static ?string $recordTitleAttribute = 'description';

    protected static ?int $navigationSort = 4;

    protected static string|\UnitEnum|null $navigationGroup = 'Pelanggan';

    public static function getNavigationIcon(): string | BackedEnum | null
    {
        return Heroicon::OutlinedWrenchScrewdriver;
    }

    public static function getNavigationLabel(): string
    {
        return 'Tiket Gangguan';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Tiket Gangguan';
    }

    public static function getModelLabel(): string
    {
        return 'Tiket Gangguan';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                DatePicker::make('date')
                    ->label('Tanggal')
                    ->required()
                    ->default(now()),
                Select::make('retail_customer_id')
                    ->label('Pelanggan Retail')
                    ->relationship('customer', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record): string => $record->customer_code . ' - ' . $record->name)
                    ->searchable()
                    ->preload()
                    ->required(),
                DateTimePicker::make('start_time')
                    ->label('Waktu Mulai Gangguan')
                    ->seconds(false)
                    ->required()
                    ->default(now()),
                DateTimePicker::make('restored_time')
                    ->label('Waktu Normal Kembali')
                    ->seconds(false),
                Select::make('category')
                    ->label('Kategori Gangguan')
                    ->options([
                        'ringan' => 'Ringan',
                        'sedang' => 'Sedang',
                        'berat' => 'Berat',
                    ])
                    ->default('ringan')
                    ->required(),
                Select::make('status')
                    ->label('Status Tiket')
                    ->options([
                        'open' => 'Terbuka',
                        'progress' => 'Dalam Penanganan',
                        'resolved' => 'Selesai',
                        'closed' => 'Ditutup',
                    ])
                    ->default('open')
                    ->required(),
                Select::make('pics')
                    ->label('Penanggung Jawab (PIC)')
                    ->multiple()
                    ->relationship('pics', 'name')
                    ->searchable()
                    ->preload()
                    ->columnSpanFull(),
                Textarea::make('description')
                    ->label('Keterangan Gangguan')
                    ->rows(3)
                    ->required()
                    ->columnSpanFull(),
                Repeater::make('steps')
                    ->label('Langkah Penanganan')
                    ->relationship()
                    ->reorderable()
                    ->orderColumn('sort')
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('description')
                            ->label('Langkah')
                            ->required()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->columns([
                TextColumn::make('date')
                    ->label('Tanggal')
                    ->date('d F Y')
                    ->sortable(),
                TextColumn::make('customer.customer_code')
                    ->label('ID Pelanggan')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customer.name')
                    ->label('Pelanggan')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category')
                    ->label('Kategori')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'ringan' => 'Ringan',
                        'sedang' => 'Sedang',
                        'berat' => 'Berat',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'ringan' => 'success',
                        'sedang' => 'warning',
                        'berat' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'open' => 'Terbuka',
                        'progress' => 'Dalam Penanganan',
                        'resolved' => 'Selesai',
                        'closed' => 'Ditutup',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'open' => 'danger',
                        'progress' => 'warning',
                        'resolved' => 'success',
                        'closed' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('pics.name')
                    ->label('PIC')
                    ->badge()
                    ->separator(', ')
                    ->placeholder('-'),
                TextColumn::make('start_time')
                    ->label('Mulai')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('restored_time')
                    ->label('Normal Kembali')
                    ->dateTime('d M Y H:i')
                    ->placeholder('-'),
                TextColumn::make('steps_count')
                    ->label('Langkah')
                    ->counts('steps')
                    ->alignRight(),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label('Kategori')
                    ->options([
                        'ringan' => 'Ringan',
                        'sedang' => 'Sedang',
                        'berat' => 'Berat',
                    ]),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'open' => 'Terbuka',
                        'progress' => 'Dalam Penanganan',
                        'resolved' => 'Selesai',
                        'closed' => 'Ditutup',
                    ]),
            ])
            ->recordActions([
                ViewAction::make()->iconButton(),
                EditAction::make()->iconButton(),
                DeleteAction::make()->iconButton(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Hapus yang Dipilih'),
                ]),
            ])
            ->defaultSort('date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTroubleTickets::route('/'),
        ];
    }
}
