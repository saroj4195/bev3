<?php
namespace App;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model 
{
     protected $connection = 'bookingjini_kernel';
     protected $table = 'subscriptions';
     protected $primaryKey = "id";

     protected $fillable = array('id','hotel_id','plan_id','valid_from','valid_to','status');

}	