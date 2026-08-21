<?php

namespace App\Filament\Resources\Sops;

use App\Filament\Resources\Concerns\HasResourcePermissions;
use App\Filament\Resources\Sops\Pages\ManageSops;
use App\Models\Role;
use App\Models\Sop;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SopResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $permissionPrefix = 'sops';

    protected static ?string $model = Sop::class;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = 1;

    protected static string|\UnitEnum|null $navigationGroup = 'SOP';

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return Heroicon::OutlinedDocumentText;
    }

    public static function getNavigationLabel(): string
    {
        return 'Kelola SOP';
    }

    public static function getPluralModelLabel(): string
    {
        return 'SOP';
    }

    public static function getModelLabel(): string
    {
        return 'SOP';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('title')
                    ->label('Judul SOP')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Textarea::make('description')
                    ->label('Deskripsi')
                    ->rows(3)
                    ->columnSpanFull(),
                Radio::make('access_type')
                    ->label('Akses SOP')
                    ->options([
                        Sop::ACCESS_ALL_DIVISIONS => 'Semua Divisi',
                        Sop::ACCESS_SPECIFIC_DIVISION => 'Salah Satu Divisi',
                        Sop::ACCESS_PUBLIC => 'Publik (Tanpa Login)',
                    ])
                    ->default(Sop::ACCESS_ALL_DIVISIONS)
                    ->inline()
                    ->required()
                    ->live()
                    ->columnSpanFull(),
                Select::make('role_id')
                    ->label('Pilih Divisi')
                    ->placeholder('Pilih divisi')
                    ->options(fn (): array => Role::orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray()
                    )
                    ->searchable()
                    ->visible(fn (Get $get): bool => $get('access_type') === Sop::ACCESS_SPECIFIC_DIVISION)
                    ->required(fn (Get $get): bool => $get('access_type') === Sop::ACCESS_SPECIFIC_DIVISION)
                    ->columnSpanFull(),
                FileUpload::make('file_path')
                    ->label('File PDF')
                    ->disk('local')
                    ->directory('sops')
                    ->acceptedFileTypes(['application/pdf'])
                    ->maxSize(10240)
                    ->required()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('title')
                    ->label('Judul SOP')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('access_type')
                    ->label('Akses')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        Sop::ACCESS_PUBLIC => 'Publik',
                        Sop::ACCESS_ALL_DIVISIONS => 'Semua Divisi',
                        Sop::ACCESS_SPECIFIC_DIVISION => 'Salah Satu Divisi',
                        default => '-'
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        Sop::ACCESS_PUBLIC => 'success',
                        Sop::ACCESS_ALL_DIVISIONS => 'warning',
                        Sop::ACCESS_SPECIFIC_DIVISION => 'info',
                        default => 'gray'
                    }),
                TextColumn::make('role.name')
                    ->label('Divisi')
                    ->placeholder('-')
                    ->formatStateUsing(fn ($state, Sop $record): string => match ($record->access_type) {
                        Sop::ACCESS_SPECIFIC_DIVISION => $record->role?->name ?? '-',
                        Sop::ACCESS_ALL_DIVISIONS => 'Semua Divisi',
                        Sop::ACCESS_PUBLIC => 'Publik',
                        default => '-',
                    })
                    ->searchable(),
                TextColumn::make('creator.name')
                    ->label('Dibuat oleh')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->date('d F Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('access_type')
                    ->label('Tingkat Akses')
                    ->options([
                        Sop::ACCESS_ALL_DIVISIONS => 'Semua Divisi',
                        Sop::ACCESS_SPECIFIC_DIVISION => 'Salah Satu Divisi',
                        Sop::ACCESS_PUBLIC => 'Publik',
                    ]),
                SelectFilter::make('role')
                    ->label('Divisi')
                    ->relationship('role', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                Action::make('lihat_pdf')
                    ->label('Lihat PDF')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->url(fn (Sop $record): string => route('sop.view', $record))
                    ->openUrlInNewTab(),
                EditAction::make()->iconButton(),
                DeleteAction::make()->iconButton(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Hapus yang Dipilih'),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSops::route('/'),
        ];
    }
}
