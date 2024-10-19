<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
class RateUpdateLog extends Model 
{
    protected $table = 'rate_update_logs';
protected $primaryKey = "id";
     /**
* The attributes that are mass assignable.
     *
* @var array
     */
protected $fillable = array('action_id','hotel_id','ota_id','rate_ref_id', 'user_id','request_msg','response_msg','request_url','status','comment','ip');
}