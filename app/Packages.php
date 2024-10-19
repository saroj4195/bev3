<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
//a class Coupons created
class Packages extends Model
{
  protected $connection = 'bookingjini_kernel';
protected $table = 'package_table';
    protected $primaryKey = "package_id";
/**
     * The attributes that are mass assignable.
* @author subhradip
     * @var array
*/
    protected $fillable = array('company_id','hotel_id','room_type_id','user_id','package_name','date_from',
'date_to','adults','nights','amount','discounted_amount',
                                'package_description','package_image','max_child','extra_person',
'extra_person_price','extra_child','extra_child_price','client_ip',
                                'tax_type','tax_name','tax_amount',
);

}
