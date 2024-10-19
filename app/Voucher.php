<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
class Voucher extends Model 
{
//protected $connection = 'booking_engine';
 protected $table = 'voucher_table';

 protected $fillable = array('id','invoice_id','hotel_name','booking_source','voucher','booking_status');

}