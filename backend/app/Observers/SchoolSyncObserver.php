<?php

namespace App\Observers;

use App\Support\SchoolSync;
use Illuminate\Database\Eloquent\Model;

class SchoolSyncObserver
{
    public function saved(Model $record): void
    {
        if ($record->wasRecentlyCreated || $record->wasChanged()) {
            SchoolSync::changed();
        }
    }

    public function deleted(Model $record): void
    {
        SchoolSync::changed();
    }
}
