<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceSubmission extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['snapshot', 'submission_hash'];

    protected function casts(): array
    {
        return ['snapshot' => 'array'];
    }
}
