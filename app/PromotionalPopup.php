<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
//a class Coupons created
class PromotionalPopup extends Model 
{
protected $table = 'promotional_popup';
    protected $primaryKey = "promo_id";
/**
     * The attributes that are mass assignable.
* @author subhradip
     * @var array
*/
    
protected $fillable = array('display_type','banner_type','banner_url',
                                'company_id','coupon_id','hotel_id','user_id',
'client_ip');

// function checkPaidServiceStatus used for checkng duplicasy
    public function checkCoupons($coupon_id)
{
        $conditions=array('coupon_id'=> $coupon_id);
$coupon = PromotionalPopup::where($conditions)->first(['promo_id']);
        if($coupon)
{
            return "exist";
}
        else
{
            return "new";
}
    }                           

}	

