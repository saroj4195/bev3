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
class CancelBooking extends Model 
{
protected $table = 'cancel_booking';
protected $primaryKey = "cancel_id";
/**
* The attributes that are mass assignable.
*
* @var array
*/
protected $fillable = array('invoice_id','cancel_date','user_id');
public function checkStatus($invoice_id)
{
$condition=array('invoice_id'=>$invoice_id);
$check=CancelBooking::where($condition)->first();
if($check)
{
return "exist";
}
else{
return "new";
}
}
}