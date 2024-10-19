<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
//a class Coupons created
class OnlineTransactionDetail extends Model 
{
protected $table = 'online_transaction_details';
    protected $primaryKey = "tr_id";
/**
     * The attributes that are mass assignable.
* @author subhradip
     * @var array
*/
    protected $fillable = array('payment_mode','invoice_id','transaction_id','payment_id','secure_hash'
);

}	
