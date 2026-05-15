<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Endereco extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'rua', 'logradouro', 'cep'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
