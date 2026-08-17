<?php

namespace App\Filament\Pages;

use App\Models\Sop;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class MenuSop extends Page implements HasTable
{
    use InteractsWithTable {
        makeTable as makeBaseTable;
    }

    protected static string|\UnitEnum|null $navigationGroup = 'SOP';

    protected static ?int $navigationSort = 2;

    public static function getNavigationIcon(): string | BackedEnum | null
    {
        return Heroicon::OutlinedDocumentText;
    }

    public static function getNavigationLabel(): string
    {
        return 'Menu SOP';
    }

    public function getTitle(): string
    {
        return 'Menu SOP';
    }

    public static function getDefaultSlug(): string
    {
        return 'menu-sop';
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) ($user && ($user->isAdmin() || $user->hasPermission('view_sops')));
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                EmbeddedTable::make(),
            ]);
    }

    protected function makeTable(): Table
    {
        $user = auth()->user();

        return $this->makeBaseTable()
            ->query(fn () => Sop::query()
                ->where(function ($query) use ($user): void {
                    if ($user?->isAdmin()) {
                        return;
                    }
                    $query->whereIn('access_type', [Sop::ACCESS_PUBLIC, Sop::ACCESS_ALL_DIVISIONS]);
                    if ($user?->role_id) {
                        $query->orWhere(function ($q) use ($user) {
                            $q->where('access_type', Sop::ACCESS_SPECIFIC_DIVISION)
                                ->where('role_id', $user->role_id);
                        });
                    }
                })
                ->orderBy('title')
            )
            ->columns([
                TextColumn::make('title')
                    ->label('Judul SOP')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('description')
                    ->label('Deskripsi')
                    ->limit(60)
                    ->toggleable(),
                TextColumn::make('access_type')
                    ->label('Akses')
                    ->badge()
                    ->formatStateUsing(fn (?string $state, Sop $record): string => match ($state) {
                        Sop::ACCESS_PUBLIC => 'Publik',
                        Sop::ACCESS_ALL_DIVISIONS => 'Semua Divisi',
                        Sop::ACCESS_SPECIFIC_DIVISION => 'Divisi: ' . ($record->role?->name ?? '-'),
                        default => '-'
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        Sop::ACCESS_PUBLIC => 'success',
                        Sop::ACCESS_ALL_DIVISIONS => 'warning',
                        Sop::ACCESS_SPECIFIC_DIVISION => 'info',
                        default => 'gray'
                    }),
            ])
            ->recordActions([
                Action::make('lihat_pdf')
                    ->label('Lihat PDF')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->url(fn (Sop $record): string => route('sop.view', $record))
                    ->openUrlInNewTab(),
            ]);
    }
}
