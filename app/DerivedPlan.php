<?php
namespace App;
use Illuminate\Auth\Authenticatable;
use Laravel\Lumen\Auth\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Support\Facades\Mail;
use Exception;
use DB;
class DerivedPlan extends Model
{
    protected $connection = 'bookingjini_kernel';
    protected $table = 'derived_plan_table';
    protected $primaryKey = "derived_id";
    /**
    * The attributes that are mass assignable.
    *
    * @var array
    */
    protected $fillable = array('hotel_id','room_type_id','rate_plan_id','select_type','amount_type','derived_room_type_id','derived_rate_plan_id','extra_adult_select_type','extra_adult_amount','extra_child_select_type','extra_child_amount');
}
