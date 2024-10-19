<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
//a class ManageBooking created
class ManageUserTable extends Model 
{
protected $table = 'user_table';
    protected $primaryKey = "user_id";
/**
     * The attributes that are mass assignable.
* @auther subhradip
     * @var array
*/
    protected $fillable = array('role_id','company_id','club_id','title','first_name',
'last_name','company_name','email_id','password','address','mobile','zip_code',
                                'country','state','city','telephone','FAX','website','member_photo',
'company_logo','registered_date','status','is_trash');
    
}	

