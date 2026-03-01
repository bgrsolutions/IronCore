<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasCompanyScopedResource;
use App\Filament\Resources\TagResource\Pages;
use App\Models\Tag;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TagResource extends Resource
{
    use HasCompanyScopedResource;

    protected static ?string $model = Tag::class;
    protected static ?string $navigationGroup = 'Admin';

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?int $navigationSort = 50;


    public static function form(Form $form): Form
    {
        return $form->schema([TextInput::make('name')->required()]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')->searchable()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListTags::route('/'), 'create' => Pages\CreateTag::route('/create'), 'edit' => Pages\EditTag::route('/{record}/edit')];
    }
}
