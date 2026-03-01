<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasCompanyScopedResource;
use App\Filament\Resources\DocumentResource\Pages;
use App\Models\Document;
use App\Models\Supplier;
use App\Support\Company\CompanyContext;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DocumentResource extends Resource
{
    use HasCompanyScopedResource;

    protected static ?string $model = Document::class;
    protected static ?string $navigationGroup = 'Repairs';

    protected static ?string $navigationLabel = 'Public Repair Links';

    protected static ?string $navigationIcon = 'heroicon-o-link';

    protected static ?int $navigationSort = 30;


    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Hidden::make('company_id')->default(fn () => CompanyContext::get()),
            Forms\Components\Hidden::make('disk')->default(config('filesystems.default')),
            Forms\Components\FileUpload::make('path')
                ->disk(config('filesystems.default'))
                ->directory('documents')
                ->acceptedFileTypes(['application/pdf'])
                ->downloadable()
                ->openable()
                ->required(),
            Forms\Components\TextInput::make('original_name')->required(),
            Forms\Components\TextInput::make('mime_type')->default('application/pdf')->required(),
            Forms\Components\TextInput::make('size_bytes')->numeric()->default(0)->required(),
            Forms\Components\Select::make('supplier_id')->options(fn () => Supplier::query()->pluck('name', 'id')),
            Forms\Components\Select::make('tags')->relationship('tags', 'name')->multiple()->preload(),
            Forms\Components\DatePicker::make('document_date'),
            Forms\Components\Hidden::make('uploaded_at')->default(now()),
            Forms\Components\TextInput::make('status')->default('active'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('original_name')->searchable(),
            Tables\Columns\TextColumn::make('mime_type'),
            Tables\Columns\TextColumn::make('status'),
            Tables\Columns\TextColumn::make('uploaded_at')->dateTime(),
        ])->actions([
            Tables\Actions\Action::make('download')->url(fn (Document $record) => route('documents.download', $record), shouldOpenInNewTab: true),
            Tables\Actions\EditAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListDocuments::route('/'), 'create' => Pages\CreateDocument::route('/create'), 'edit' => Pages\EditDocument::route('/{record}/edit')];
    }
}
