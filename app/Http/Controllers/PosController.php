<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use App\HotelInformation;
use App\CompanyDetails;
use App\MetaSearchEngineSetting;
class PosController extends Controller
{
    //Function to redirect google pos url to BE 
    public function redirectToBe(Request $request){
        $hotel_id=$request->query('hid');
        $checkin_date=$request->query('checkin');
        $checkout_date=$request->query('checkout');
        $hotel=HotelInformation ::select('company_id')->where('hotel_id',$hotel_id)->first();
        if(!$hotel) return $avl_arr;
        $company=CompanyDetails::where('company_id',$hotel->company_id)->select('company_url')->first();
        $be_url=strpos($company->company_url,'bookingjini') ? 'https://'.$company->company_url : 'http://'.$company->company_url;
        $q=base64_encode((strtotime($checkin_date) * 1000)."|".(strtotime($checkout_date) * 1000)."|".$hotel_id."||||||google");
        $be_url=$be_url.'/property?q='.$q;
        header("Location: $be_url");
        die();
    }
    public function checkGoogleHotelId(Request $request){
        $data=$request->all();
        $hotel_id=$data['hotel_id'];
        $hotels_data=MetaSearchEngineSetting::where('name','google-hotel')->select('hotels')->first();
        $hotelArr=explode(",",$hotels_data->hotels);
        if(in_array($hotel_id,$hotelArr)){
            return response()->json(array("status"=>true));
        }
        else{
            return response()->json(array("status"=>false));
        }
    }
}
