<?php

namespace App\Filament\Resources\Groups\Pages;

use App\Filament\Resources\Groups\GroupResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGroup extends CreateRecord
{
    protected ?bool $hasDatabaseTransactions = true;

    protected static string $resource = GroupResource::class;
}
