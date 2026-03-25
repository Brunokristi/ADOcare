<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'path',
        'insurance_company_id',
        'period',
        'type',
        'total',
        'invoice_number',
        'related_invoice_id',
        'mime_type',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function insuranceCompany()
    {
        return $this->belongsTo(InsuranceCompany::class, 'insurance_company_id');
    }

    public function relatedInvoice()
    {
        return $this->belongsTo(self::class, 'related_invoice_id');
    }
}
