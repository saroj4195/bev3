<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
class HotelBankDetail extends Model 
{
    protected $table = 'bank_account_details';
protected $primaryKey = "id";
     /**
* The attributes that are mass assignable.
     *
* @var array
     */
protected $fillable = array('bank_account_no','ifsc_code','swift_code','bank_name','bank_branch','beneficiary_name','bank_address','user_id','hotel_id');
	
/*
    *Check status of the hotel user
*@param $email for user email
    *
*@return hotel user  status`
    */
public function checkAccStatus($bank_acc_no,$ifsc_code)
    {
$conditions=array('bank_account_no'=> $bank_acc_no,'ifsc_code'=> $ifsc_code);
        $hotel_bank_data = HotelBankDetail::where($conditions)->first(['id']);
if($hotel_bank_data)
        {
return "exist";
        }
else
        {
return "new";
        }
}
}