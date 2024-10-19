<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class RefundTransactionDetails extends Model 
{
protected $table = 'refund_transaction_details';

protected $fillable = array('refund_id','hotel_id','transaction_id','refund_status','refund_amount');

}
