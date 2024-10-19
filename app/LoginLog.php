<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
class LoginLog extends Model
{
  protected $connection = 'bookingjini_kernel';
    protected $table = 'login_log';
    protected $primaryKey = "login_log_id";
     /**
* The attributes that are mass assignable.
     *@auther ranjit
* @var array
     */
protected $fillable = array('user_id','role_id','company_id','ip_address','browser','login_date');
}
