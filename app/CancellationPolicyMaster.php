<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
use Illuminate\Support\Facades\Mail;
class CancellationPolicyMaster extends Model
{
    protected $table = 'cancellation_policy_master';
    protected $primaryKey = "id";
     
    protected $fillable = array('id','days_before_checkin');

}
