<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
class PaidServices extends Model 
{
    protected $table = 'paid_service';
protected $primaryKey = "paid_service_id";
     /**
* The attributes that are mass assignable.
     *
* @var array
     */
protected $fillable = array('hotel_id','service_name','service_amount','paid_service_desc','service_tax','client_ip','is_trash','is_enable','created_by','updated_by');
 
/*
    *Check status of the hotel paid services
*
    *@auther subhradip
*@return hotel paid services
    */
// function checkPaidServiceStatus used for checkng duplicasy
    public function checkPaidServiceStatus($service_name,$hotel_id)
{
        $conditions=array('service_name'=> $service_name,'hotel_id'=>$hotel_id,'is_trash'=>0);
$Paid_Services = PaidServices::where($conditions)->first(['paid_service_id']);
        if($Paid_Services)
{
            return "exist";
}
        else
{
            return "new";
}
    }
}