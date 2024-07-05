<?php

namespace App\Observers;

use App\Models\Email;

class EmailObserver
{
    public function creating(Email $email): void
    {
        $email->sort = Email::where('position_id', $email->position_id)->max('sort') + 1;
    }
}
