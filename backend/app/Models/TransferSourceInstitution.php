<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class TransferSourceInstitution extends Model
{
    protected $fillable = ['name', 'code', 'accreditation', 'address', 'city', 'province', 'country'];
}
