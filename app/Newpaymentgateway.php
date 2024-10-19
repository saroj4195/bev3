<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
class Newpaymentgateway extends Model
{
 
    protected $table = 'paymentgateway_details';
    protected $primaryKey = "id";
     /**
* The attributes that are mass assignable.
     *@auther ranjit
* @var array
     */
protected $fillable = array('id','hotel_id','provider_name','credentials','fail_url','user_id','client_ip');
}
