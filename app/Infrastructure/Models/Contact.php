<?php

namespace App\Infrastructure\Models;

use Database\Factories\ContactFactory;
use Domain\Enums\ContactStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'contacts';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'score',
        'status',
        'processed_at',
    ];

    protected $casts = [
        'score' => 'integer',
        'status' => ContactStatus::class,
        'processed_at' => 'datetime',
    ];

    protected static function newFactory(): ContactFactory
    {
        return ContactFactory::new();
    }
}
