<?php
namespace App;
use Illuminate\Database\Eloquent\Model;

class CrsPaymentReceive extends Model 
{
     protected $table = 'crs_payment_receive';
     protected $primaryKey = "id";

     protected $fillable = array('id','hotel_id','invoice_id','payment_receive_date','receive_amount','payment_mode','ref_no','created_at','updated_at');

}	