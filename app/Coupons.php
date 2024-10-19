<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
//a class Coupons created
class Coupons extends Model 
{
protected $table = 'coupons';
    protected $primaryKey = "coupon_id";
/**
     * The attributes that are mass assignable.
* @author subhradip
     * @var array
*/
    protected $fillable = array('company_id','hotel_id','room_type_id',
'user_id','coupon_name','coupon_code','coupon_for',
                                'valid_from','valid_to','discount_type','discount',
'client_ip');

}   