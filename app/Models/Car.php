<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Models\Concerns\CompanyBranchScopes;

class Car extends Model
{
    use HasFactory, CompanyBranchScopes;

    protected $table = 'cars';

    protected $fillable = ['evc', 'model', 'company_id', 'user_id'];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
