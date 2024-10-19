<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
//a class Coupons created
class BookingjiniPaymentgateway extends Model
{
    protected $connection = 'bookingjini_kernel';
    protected $table = 'bookingjini_paymentgateway';
    protected $primaryKey = "id";
    /**
         *this model is used for bookingjini paymentgateway.
    * @author Ranjit
        * @var array
    */
    protected $fillable = array('paymentgateway_name');

}
