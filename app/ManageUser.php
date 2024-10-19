<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
Use App\HotelInformation;
//a class Coupons created
class ManageUser extends Model 
{
    protected $table = 'admin_table';
protected $primaryKey = "admin_id";
     /**
* The attributes that are mass assignable.
     * @author subhradip
* @var array
     */
protected $fillable = array('company_id','role_id','first_name','last_name',
                                'username','password','user_id','client_ip',
'agent_commission','agent_code','new_password'
                                );
// checking for duplicacy
public function checkUsernameDuplicasy($username,$company_id)
{
$conditions=array('username'=> $username,'company_id'=>$company_id);
$manageuser = ManageUser::where($conditions)->first(['admin_id']);
if($manageuser)
{
return "exist";
}
else
{
return "new";
}
}   
// checking for duplicacy
public function checkUserExist($role_id,$company_id)
{
$hotels=HotelInformation::where("company_id",$company_id)->get();
$conditions=array('role_id'=> $role_id,'company_id'=>$company_id);
$manageuser = ManageUser::where($conditions)->get(['admin_id']);
if(sizeof($manageuser)<=sizeof($hotels))
{
return "new";
}
else
{
return "exist";
}
}                           

}	

