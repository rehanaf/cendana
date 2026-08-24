<?php

namespace App\Filament\Resources\TroubleTickets;

use App\Filament\Resources\Concerns\HasResourcePermissions;
use App\Filament\Resources\TroubleTickets\Pages\ManageTroubleTickets;
use App\Models\PelangganRetail;
use App\Models\TroubleTicket;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
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

    public static function customerOptions(): array
    {
        return PelangganRetail::query()
            ->orderBy('customer_code')
            ->get()
            ->mapWithKeys(fn (PelangganRetail $customer): array => [
                $customer->id => $customer->customer_code . ' - ' . $customer->name . (! $customer->is_active ? ' (Nonaktif)' : ''),
            ])
            ->toArray();
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
                    ->options(fn (): array => static::customerOptions())
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
                    ->options(TroubleTicket::CATEGORIES)
                    ->default('ringan')
                    ->required(),
                Select::make('handling_method')
                    ->label('Langkah Penanganan')
                    ->options(TroubleTicket::HANDLING_METHODS)
                    ->placeholder('Pilih Langkah Penanganan'),
                Select::make('status')
                    ->label('Status Tiket')
                    ->options(TroubleTicket::STATUSES)
                    ->default('progress')
                    ->required(),
                Select::make('pic_teams')
                    ->label('Penanggung Jawab (PIC)')
                    ->options(TroubleTicket::PIC_TEAMS)
                    ->multiple()
                    ->maxItems(3)
                    ->columnSpanFull(),
                Textarea::make('description')
                    ->label('Keterangan Gangguan')
                    ->rows(3)
                    ->required()
                    ->columnSpanFull(),
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
                    ->formatStateUsing(fn (string $state): string => TroubleTicket::CATEGORIES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'ringan' => 'success',
                        'sedang' => 'warning',
                        'berat', 'kritis' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => TroubleTicket::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'progress' => 'warning',
                        'closed' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('handling_method')
                    ->label('Langkah Penanganan')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => TroubleTicket::HANDLING_METHODS[$state] ?? '-')
                    ->color('info')
                    ->placeholder('-'),
                TextColumn::make('pic_teams')
                    ->label('PIC')
                    ->formatStateUsing(fn ($state): string => collect(
                        is_array($state) ? $state : ((array) json_decode((string) $state, true))
                    )
                        ->map(fn ($team): string => TroubleTicket::PIC_TEAMS[$team] ?? $team)
                        ->implode(', '))
                    ->placeholder('-'),
                TextColumn::make('start_time')
                    ->label('Mulai')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('restored_time')
                    ->label('Normal Kembali')
                    ->dateTime('d M Y H:i')
                    ->placeholder('-')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label('Kategori')
                    ->options(TroubleTicket::CATEGORIES),
                SelectFilter::make('handling_method')
                    ->label('Langkah Penanganan')
                    ->options(TroubleTicket::HANDLING_METHODS),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(TroubleTicket::STATUSES),
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
