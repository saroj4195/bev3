<?php
namespace App\Http\Controllers\Extranetv4;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Validator;
use App\City;
use App\ImageTable;
use App\HotelInformation;
use DB;
use App\Http\Controllers\Controller;

class RetrieveHotelDetailsController extends Controller{
    public function getAllRunningHotelDataByidBE(Request $request,string $hotel_id)
    {
        $hotel_data=HotelInformation::select('*')->where('hotel_id',$hotel_id)->first();
        $image=explode(',',$hotel_data->exterior_image);
        if($image[0] == null || $image[0] == '' || $image[0] ==0){
                $image[0] = 3;
        }
        $img_name=ImageTable::select('image_name')->where('image_id',$image[0])->first();
        if(!isset($img_name->image_name)){
            $img_name=ImageTable::select('image_name')->where('image_id',3)->first();
            $hotel_data->exterior_image=env('S3_IMAGE_PATH').$img_name->image_name;
        }
        else{
                $hotel_data->exterior_image=env('S3_IMAGE_PATH').$img_name->image_name;
        }
        $city=City::where('city_id',$hotel_data->city_id)->select('city_name')->first();
        $hotel_data->city_id=$city->city_name;
        return response()->json(array($hotel_data));
    }
    public function getAllHotelsByCompany(Request $request,string $comp_hash,string $company_id)
    {
        $company_hash_code=openssl_digest($company_id, 'sha512');
        if($comp_hash!=$company_hash_code)
        {
            $res=array('status'=>0,"message"=>"Hotel list retrival failed");
            $res['errors'][] = "Please provide valid company";
            return response()->json($res);
        }
        $hotels=HotelInformation::where('company_id',$company_id)->where('status',1)->select('*')->get();
        if($hotels){
            foreach($hotels as $hotel)
            {
                if($hotel['be_opt']==1)
                {
                    $hotel['be_opt']='enquiry';
                }
                else{
                    $hotel['be_opt']='instant';
                }
                $ext_images=explode(',',$hotel['exterior_image']);
                $images = ImageTable::select('image_id','image_name')
                ->where('image_id', $ext_images)
                ->first();
                if($images)
                {
                    $hotel['exterior_image']=env('S3_IMAGE_PATH').$images->image_name;
                }


                $city_details=City::select('city_name')->where('city_id',$hotel->city_id)->first();
                $hotel->city_name=$city_details->city_name;
            }
        }
        if($hotels)
        {
            $res=array('status'=>1,"message"=>"Hotels retrieved successfully!","data"=>$hotels);
            return response()->json($res);
        }
        else{
            $res=array('status'=>0,"message"=>"Hotels not found!");
            return response()->json($res);
        }
    }
}
