<?php
namespace App;
use Illuminate\Database\Eloquent\Model;

class BePromotions extends Model 
{
protected $table = 'be_promotions';
protected $primaryKey = 'id';

protected $fillable =[
    'id',
    'hotel_id',
    'promotion_type',
    'promotion_name',
    'selected_room_rate_plan',
    'offer_type',
    'discount_percentage',
    'discounted_amount',
    'stay_start_date',
    'stay_end_date',
    'booking_start_date',
    'booking_end_date',
    'user_id',
    'min_los',
    'max_los',
    'advance_booking_days',
    'booking_days_within',
    'blackout_dates',
    'blackout_days',
    'member_only',
    'mobile_users_only',
    'status',
    'created_at',
    'updated_at'
];


}   