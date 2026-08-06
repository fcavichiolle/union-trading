<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fornecedor extends Model
{
    use HasFactory;

    protected $table = 'fornecedores';

    protected $fillable = ['nome', 'cnpj'];

    public function compras(): HasMany
    {
        return $this->hasMany(Compra::class);
    }
}