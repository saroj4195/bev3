<?php
namespace App;
use Illuminate\Auth\Authenticatable;
use Laravel\Lumen\Auth\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Support\Facades\Mail;
use Exception;
use DB;
class Currency extends Model 
{  
    protected $connection = 'bookingjini_kernel';
protected $table = 'currency_table';
    protected $primaryKey = "id";
/**
     * The attributes that are mass assignable.
*
     * @var array
*/
    protected $fillable = array('auth_parameter');
}