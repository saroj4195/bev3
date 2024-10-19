<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;

class BeSettings extends Model
{

	protected $table = 'bookingengine_settings';
    protected $primaryKey = "id";
     /**@auther : Shankar Bag
     * The attributes that are mass assignable.
     *
     * @var array
     */
	protected $fillable = array('company_id','home_url','logo','banner');
	/*
    @auther : Shankar Bag
    @Story : This function will check the existance of URL
	*@return URL Entry  status
    */
	// public function checkStatus($url)
	// {
	// 	$hotel_url_data = CompanyDetails::where('subdomain_name', '=', $url)->first(['company_id']);
	// 	if($hotel_url_data)
	// 	{
	// 		return "exist";
	// 	}
	// 	else
	// 	{
	// 		return "new";
	// 	}
	// }


}
