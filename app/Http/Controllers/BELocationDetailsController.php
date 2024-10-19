<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Validator;
use DB;
use App\Country;
use App\State;
use App\City;


class BELocationDetailsController extends Controller
{
    
    public function getAllCountry(Request $request)
    {

        $country_details=Country::select('country_id','country_name','country_code','country_dial_code')->get();
        if( sizeof($country_details)>0)
        {
            $res=array('status'=>1,'message'=>'details retrive sucessfully','all_country'=>$country_details);
            return response()->json($res);
        }
        else{
            $res=array('status'=>0,'message'=>'details retrive not completed');
            return response()->json($res);
        }
    }

    public function getAllStates(int $country_id,Request $request)
    {
        if($country_id)
        {
            $states=State::select('state_id','state_name','country_id')->where('country_id',$country_id)->get();

            if(sizeof($states)>0)
            {
                $res=array('status'=>1,'message'=>'state details retrive sucessfully','states'=>$states);
                return response()->json($res);
            }
            else{
                $res=array('status'=>0,'message'=>'state details retrive fails');
                return response()->json($res);
            }
        }
        else{
            $res=array('status'=>-1,'message'=>'state details fetching fails');
            $res['error'][]="country id is not provided";
            return response()->json($res);
        }
    }
    

    public function getAllCity(int $state_id,Request $request){
        if($state_id)
        {
                $city=City::select('city_id','city_name','state_id')->where('state_id',$state_id)->where('is_user_defined',0)->get();

                if(sizeof($city)>0)
                {
                    $res=array('status'=>1,'message'=>'city details retrive sucessfully','city'=>$city);
                    return response()->json($res);
                }
                else{
                    $res=array('status'=>0,'message'=>'city details retrive fails');
                    return response()->json($res);
                }
        }
        else{
            $res=array('status'=>-1,'message'=>'city details fetching fails');
            $res['error'][]="state id is not provided";
            return response()->json($res);
        }
    }

}
