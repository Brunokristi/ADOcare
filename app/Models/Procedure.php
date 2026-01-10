<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Procedure extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'description',
    ];


    // Procedure prices based on insurance companies
    public function insuranceCompaniesPrices()
    {
        return $this->belongsToMany(InsuranceCompany::class, 'procedure_company_prices')
                    ->withPivot('price')
                    ->withTimestamps();
    }

    public function insuranceCompaniesPricesMinimal()
    {
        return $this->belongsToMany(InsuranceCompany::class, 'procedure_company_prices')
                    ->select('insurance_companies.id')
                    ->withPivot('price')
                    ->withTimestamps();
    }

}
