<?php
namespace App;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model 
{
     protected $table = 'subscription_plan_setup';
     protected $primaryKey = "subscription_plan_id";

     protected $fillable = array('subscription_plan_id','hotel_id', 'user_id', 'subscription_plan_name', 'feature', 'amount', 'discount', 'is_active', 'is_trash');

}	