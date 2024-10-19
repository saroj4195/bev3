<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
class TaxDetails extends Model
{
  protected $connection = 'bookingjini_kernel';
    protected $table = 'tax_details';
protected $primaryKey = "id";
     /**
* The attributes that are mass assignable.
     *
* @var array
     */
protected $fillable = array('hotel_id','tax_pay_hotel','user_id','tax_name','tax_percent');

public function checkTaxDetails($hotel_id)
    {
$conditions=array('hotel_id'=> $hotel_id);
        $taxdetails = TaxDetails::where($conditions)->first(['id']);
if($taxdetails)
        {
return "exist";
        }
else
        {
return "new";
        }
}
}
