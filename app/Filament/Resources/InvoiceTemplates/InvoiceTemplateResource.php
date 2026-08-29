<?php

namespace App\Filament\Resources\InvoiceTemplates;

use App\Filament\Resources\Concerns\HasResourcePermissions;
use App\Filament\Resources\InvoiceTemplates\Pages\ManageInvoiceTemplates;
use App\Models\InvoiceTemplate;
use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InvoiceTemplateResource extends Resource
{
    use HasResourcePermissions;

    protected static ?string $permissionPrefix = 'invoice_templates';

    protected static ?string $model = InvoiceTemplate::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 5;

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan';

    public static function getNavigationIcon(): string|Heroicon|null
    {
        return Heroicon::OutlinedSquares2x2;
    }

    public static function getNavigationLabel(): string
    {
        return 'Template Invoice';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Template Invoice';
    }

    public static function getModelLabel(): string
    {
        return 'Template Invoice';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Identitas Template')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Template')
                            ->required()
                            ->maxLength(255),
                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true),
                    ]),
                Section::make('Data Perusahaan')
                    ->description('Yang membedakan antar template — semua template memakai file layout invoice yang sama.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('company_name')
                            ->label('Nama Perusahaan')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Textarea::make('company_address')
                            ->label('Alamat Perusahaan')
                            ->hint('Setiap baris = satu baris alamat')
                            ->rows(3)
                            ->columnSpanFull(),
                        Textarea::make('footer_text')
                            ->label('Teks Footer / Informasi Bank')
                            ->hint('Setiap baris = satu baris footer')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
                Section::make('Logo & Stempel')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        FileUpload::make('logo_image')
                            ->label('Logo')
                            ->image()
                            ->directory('invoice-templates')
                            ->disk('public')
                            ->maxSize(2048),
                        TextInput::make('logo_width')
                            ->label('Lebar Logo (px)')
                            ->numeric()
                            ->placeholder('Kosong = penuh sel'),
                        TextInput::make('logo_height')
                            ->label('Tinggi Logo (px)')
                            ->numeric()
                            ->placeholder('Kosong = penuh sel'),
                        FileUpload::make('signature_image')
                            ->label('Stempel / Tanda Tangan')
                            ->image()
                            ->directory('invoice-templates')
                            ->disk('public')
                            ->maxSize(2048),
                        TextInput::make('signature_width')
                            ->label('Lebar Stempel (px)')
                            ->numeric()
                            ->placeholder('Kosong = penuh sel'),
                        TextInput::make('signature_height')
                            ->label('Tinggi Stempel (px)')
                            ->numeric()
                            ->placeholder('Kosong = penuh sel'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Template')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('company_name')
                    ->label('Perusahaan')
                    ->placeholder('-'),
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
                TextColumn::make('default_for')
                    ->label('Default untuk')
                    ->formatStateUsing(function (InvoiceTemplate $record): string {
                        $labels = [];

                        if ((int) Setting::get('invoice_template_penjualan_id') === $record->id) {
                            $labels[] = 'Penjualan';
                        }
                        if ((int) Setting::get('invoice_template_pembelian_id') === $record->id) {
                            $labels[] = 'Pembelian';
                        }
                        if ((int) Setting::get('invoice_template_langganan_id') === $record->id) {
                            $labels[] = 'Langganan';
                        }
                        if ((int) Setting::get('invoice_template_retail_id') === $record->id) {
                            $labels[] = 'Retail';
                        }

                        return $labels ? implode(', ', $labels) : '-';
                    }),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->date('d F Y')
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('cetak_template')
                    ->label('Cetak')
                    ->icon('heroicon-o-printer')
                    ->color('info')
                    ->iconButton()
                    ->url(fn (InvoiceTemplate $record): string => route('invoice.template.preview', ['template' => $record->id]) . '?print=1')
                    ->openUrlInNewTab(),
                Action::make('preview_template')
                    ->label('Preview')
                    ->icon('heroicon-m-eye')
                    ->iconButton()
                    ->modalHeading(fn (InvoiceTemplate $record): string => 'Preview: ' . $record->name)
                    ->modalWidth(Width::SixExtraLarge)
                    ->modalContent(fn (InvoiceTemplate $record) => view('filament.modals.invoice-template-preview', ['record' => $record]))
                    ->modalSubmitAction(false),
                EditAction::make()->iconButton(),
                DeleteAction::make()->iconButton()
                    ->modalDescription('Template yang sedang dijadikan default (Penjualan/Pembelian/Langganan/Retail) akan menjadi tidak valid. Yakin hapus?'),
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
            'index' => ManageInvoiceTemplates::route('/'),
        ];
    }
}