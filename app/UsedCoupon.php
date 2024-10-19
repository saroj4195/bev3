<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
//a class Coupons created
class UsedCoupon extends Model 
{
protected $table = 'used_coupons';
    protected $primaryKey = "id";
/**
     * The attributes that are mass assignable.
* @author subhradip
     * @var array
*/
    protected $fillable = array('user_id','code','ip','used_date');
}