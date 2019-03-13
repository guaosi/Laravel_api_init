<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Book extends Model
{

    protected $table = 'books';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id', 'name'
    ];
}
