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
/**
* this module keep the billing payment table
* @auther ranjit
*/
class BillingPaymentStatus extends Model 
{
protected $table = ' billing_payment_table';
protected $primaryKey = "id";
/**
* The attributes that are mass assignable.
*
* @var array
*/
protected $fillable = array('company_id','pay_date','product_price','product_name','conform_status');
}