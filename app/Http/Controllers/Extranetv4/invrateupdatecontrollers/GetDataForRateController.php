<?php
namespace App\Http\Controllers\Extranetv4\invrateupdatecontrollers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Validator;
use DB;
use App\Inventory;
use App\LogTable;
use App\MasterRoomType;
use App\HotelInformation;
use App\CompanyDetails;
use App\RatePlanLog;
use App\MasterHotelRatePlan;
use App\CmOtaRoomTypeSynchronize;
use App\CmOtaRatePlanSynchronize;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Extranetv4\IpAddressService;
/**
 * This controller is used for rate data and curl call
 * @auther ranjit
 * created date 05/03/19.
 */
class GetDataForRateController extends Controller
{
    public function cUrlCall($url,$headers,$xml)
    {
        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_POST, true );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $xml);
        $ota_rlt = curl_exec($ch);
        curl_close($ch);
        return $ota_rlt;
    }
    public function getDaysUpdate($rateplan_multiple_days,$ota_name)
    {
		$rateplan_multiple_days      = json_decode($rateplan_multiple_days);
		$rateplan_days_data="";
		$prefix="";
        foreach($rateplan_multiple_days as $key=>$value)
		{	/*==============Cleartrip=============*/
			if($ota_name=="Cleartrip")
            {
				if($value==1)
				{
				$rateplan_days_data .=  $prefix .strtoupper($key);
				$prefix=',';
				}
			}
			/*===========Agoda===============*/
			if($ota_name=="Agoda")
			{
				if($value==1)
				{
				$rateplan_days_data.='<dow>'.$this->agodaDays($key).'</dow>';
				}
			}
			/*===========Expedia===============*/
			if($ota_name=='Expedia')
			{
				if($value==1)
				{
				$status="true";
				}
				else if($value==0)
				{
				$status="false";
				}
				$rateplan_days_data.= ' '.strtolower($key).'="'.$status.'"';
			}
			/*==============Goibibo===============*/
			if($ota_name=="Goibibo")
            {

				if($value==1)
				{
				$status="True";
				}
				else if($value==0)
				{
				$status="False";
				}
				$rateplan_days_data.= ' '.$key.'="'.$status.'"';
			}
			/*==========Via.com===========*/
			if($ota_name=='Via.com')
			{

				$rateplan_days_data.= $value;
			}
            /*==============TravelGuru===============*/
            if($ota_name=="Travelguru")
            {
                if($key=='Wed')
                {
                    $key='Weds';
                }
                if($key=='Thu')
                {
                    $key='Thur';
				}
				$status="true";
				if($value==1)
				{
					$status="true";
				}
				if($value==0)
				{
					$status="false";
				}
				$rateplan_days_data.= ' '.$key.'="'.$status.'"';
			}
			if($ota_name=='EaseMyTrip')
			{
				$rateplan_days_data.= $value;
			}

		}
        return $rateplan_days_data;
	}
	public function agodaDays($day)
	{
		$days_data=array("Mon"=>1,"Tue"=>2,"Wed"=>3,"Thu"=>4,"Fri"=>5,"Sat"=>6,"Sun"=>7);
		foreach($days_data as $key=>$value)
		{
			if($key==$day)
			{
				return $value;
			}
		}
	}
	public function goomoDays($rateplan_multiple_days)
	{
        $rateplan_multiple_days      = json_decode($rateplan_multiple_days);
		$rateplan_days_data=array();
		$prefix="";
		foreach($rateplan_multiple_days as $key=>$value)
		{
			if( $value==1)
			{
				$status="true";
			}
			else
			{
				$status="false";
			}
			array_push($rateplan_days_data,$status);

		}
		return $rateplan_days_data;
	}
    public function decideOccupencyPrice($max_adult,$rateplan_bar_price,$rateplan_multiple_price)
	{
		$prices_array=array();
		if($max_adult>=3)
		{
			$key = $max_adult - 1;
			$prices_array[$key]=$rateplan_bar_price;
			if(isset($rateplan_multiple_price[2]) && $rateplan_multiple_price[2] && $rateplan_multiple_price[2]!=0)
			{
				$prices_array[2]=$rateplan_multiple_price[2];
			}
			else
			{
				$prices_array[1]=$rateplan_bar_price;
			}
			if(isset($rateplan_multiple_price[1]) && $rateplan_multiple_price[1] && $rateplan_multiple_price[1]!=0)
			{
				$prices_array[1]=$rateplan_multiple_price[1];
			}
			else
			{
				$prices_array[1]=$rateplan_bar_price;
			}
			if(isset($rateplan_multiple_price[0]) &&$rateplan_multiple_price[0] && $rateplan_multiple_price[0]!=0)
			{
				$prices_array[0]=$rateplan_multiple_price[0];
			}
			else
			{
				$prices_array[0]=$rateplan_bar_price;
			}
		}
		if($max_adult==2)
		{
			$prices_array[2]=0;
			$prices_array[1]=$rateplan_bar_price;
			if(isset($rateplan_multiple_price[0]) && $rateplan_multiple_price[0] && $rateplan_multiple_price[0]!=0)
			{
				$prices_array[0]=$rateplan_multiple_price[0];
			}
			else
			{
				$prices_array[0]=$rateplan_bar_price;
			}
		}
		if($max_adult==1)
		{
			$prices_array[2]=0;
			$prices_array[1]=0;
			if(isset($rateplan_multiple_price[0]) && $rateplan_multiple_price[0] && $rateplan_multiple_price[0]!=0)
			{
				$prices_array[0]=$rateplan_multiple_price[0];
			}
			else
			{
				$prices_array[0]=$rateplan_bar_price;
			}
		}
		return $prices_array;
    }
    public function getCurrency($hotel_id)
    {
        $hotel_info=HotelInformation::where('hotel_id',$hotel_id)->first();
        $company= new CompanyDetails();
        $comp_details=$company->where('company_id',$hotel_info->company_id)->select('currency')->first();
        $currency=$comp_details->currency;
        return $currency;

	}
	public function checkMinMaxPrice($room_type_id,$rate_plan_id,$bar_price,$multiple_occupancy,$hotel_id,$date,$channel){
		$bp=0;
		$mp=0;
		$price=MasterHotelRatePlan::select('min_price','max_price')->where('hotel_id',$hotel_id)->where('room_type_id',$room_type_id)->where('rate_plan_id',$rate_plan_id)->first();
		if($bar_price >= $price->min_price && $bar_price < $price->max_price)
		{
			$bp=1;
		}
		if($bp==0)
		{
			if($channel == 'be'){
				$res=array('status'=>0,'be'=>$channel,'message'=>"date:".$date."bar price should be equal or greater than: ".$price->min_price." and should be lessthan: ".$price->max_price);
				return $res;
			}
			else{
				$res=array('status'=>0,'ota_name'=>$channel,'response_msg'=>"date:".$date."bar price should be equal or greater than: ".$price->min_price." and should be lessthan: ".$price->max_price);
				return $res;
			}
		}
		if(sizeof($multiple_occupancy)>0){
			if($multiple_occupancy[0] >= $price->min_price && $multiple_occupancy[0] < $price->max_price)
			{
				$mp=$mp+1;
			}
		}
		if($mp < 1)
		{
			if($channel == 'be'){
				$res=array('status'=>0,'be'=>$channel,'message'=>"date:".$date."multiple occupancy price should be equal or greater than: ".$price->min_price." and should be lessthan: ".$price->max_price);
				return $res;
			}
			else{
				$res=array('status'=>0,'ota_name'=>$channel,'response_msg'=>"date:".$date."multiple occupancy price should be equal or greater than: ".$price->min_price." and should be lessthan: ".$price->max_price);
				return $res;
			}
		}
	}
 }