<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Qualidade extends Model
{
    use HasFactory;

    protected $table = 'qualidades';

    protected $fillable = ['descricao'];
}
