<?php
namespace App;
use DB; 
use Exception;
use Illuminate\Database\Eloquent\Model;

class ProductPrice extends Model 
{
     /**
    * The attributes that are mass assignable.
    * @author rajendra 
    */ 
    protected $table        = 'booking_assure_table';
   
    protected $primaryKey   = "booking_assure_id";
    protected $fillable     = array(
                                'booking_assure_id',
                                'sold',
                                'user_id',
                                'hotel_id',
                                'invoice_id',
                                'product_price', 
                                'product_code',
                                'currency_code',
                                'premium_rate',
                                'offering_method', 
                                'refundable_cancellation_mode',
                                'refundable_cancellation_status',
                                'refund_protect_price',
                                'response_status'
                            );
        
}
 