<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
//a class Coupons created
class QuickPaymentLink extends Model
{
protected $table = 'quick_payment_link';
    protected $primaryKey = "id";
/**
     * The attributes that are mass assignable.
* @author subhradip
     * @var array
*/
    protected $fillable = array('hotel_id','txn_id','name','email','phone','amount','advance_amount',
'comment','url','status','payment_status','pg_name','check_in','check_out','no_of_rooms','room_type','rate_plan','user_id');

}
