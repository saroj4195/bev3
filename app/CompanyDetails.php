<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;

class CompanyDetails extends Model
{
protected $connection = 'bookingjini_kernel';
protected $table = 'company_table';
protected $primaryKey = "company_id";
/**@auther : Shankar Bag
* The attributes that are mass assignable.
*
* @var array
*/
protected $fillable = array('company_full_name','company_url','subdomain_name','api_key','logo','banner','created_by','reseller_id','company_short_name','status','lavel','commision','link_type','home_url');
/*
@auther : Shankar Bag
@Story : This function will check the existance of URL
*@return URL Entry  status
*/
public function checkStatus($url)
{
$hotel_url_data = CompanyDetails::where('subdomain_name', '=', $url)->first(['company_id']);
if($hotel_url_data)
{
return "exist";
}
else
{
return "new";
}
}
/*
@auther : Shankar Bag
@Story : This function will return the id of  of URL
*@return URL Entry  status
*/
public function getId($url)
{
$hotel_url_data = CompanyDetails::where('subdomain_name', '=', $url)->first(['company_id']);
if($hotel_url_data)
{
return $hotel_url_data->id;
}
else
{
return "NOT FOUND";
}
}
}
