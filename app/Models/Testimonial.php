<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Testimonial extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'location',
        'comment',
        'rating',
        'reviewed_on',
        'avatar',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'reviewed_on' => 'date',
            'is_published' => 'boolean',
        ];
    }
}
