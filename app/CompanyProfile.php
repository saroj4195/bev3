<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;

class CompanyProfile extends Model
{
protected $table = 'company_profile';
protected $primaryKey = "profile_id";
/**@auther : Shankar Bag
* The attributes that are mass assignable.
*
* @var array
*/
protected $fillable = array('company_id','company_full_name','company_type','regd_no','registered_address','director_name','director_email','director_mobile','contact_person_name','contact_person_email','contact_person_mobile');
public function checkCompStatus($company_full_name)
{
$company_full_name=strtoupper($company_full_name);
$compa_data=CompanyProfile::where(DB::raw('upper(company_full_name)'),$company_full_name)->first(['company_id']);
if($compa_data)
{
return "exist";
}
else
{
return "new";
}    
}
}
