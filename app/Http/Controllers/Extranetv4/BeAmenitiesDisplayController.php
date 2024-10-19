<?php
namespace App\Http\Controllers\Extranetv4;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Firebase\JWT\JWT;
use Firebase\JWT\ExpiredException;
use App\HotelAmenities;
use App\AmenityCategories;
use App\HotelInformation;
use Validator;
use DB;

class BeAmenitiesDisplayController extends Controller
{
    private $rules = array(
        'hotel_id' => 'required | numeric'
    );
    private $messages = [
      'hotel_id.required' => 'Hotel details should be required',
      'hotel_id.numeric' => 'Hotel id should be a number'
    ];

    public function amenityGroup(Request $request){
        $failure_message="Hotel Amenities details fetch fails";
        $validator = Validator::make($request->all(),$this->rules,$this->messages);
        if($validator->fails()){
            return response()->json(array('status'=>0,'message'=>$failure_message,'errors'=>$validator->errors()));
        }
        $info = $request->all();
        $amenity_category = AmenityCategories::get();
        $amenity_group = array();
        $a = 0;
        foreach($amenity_category as $data){
          $hotel_amenity_details = array();
          $getAminities_ids = HotelInformation::select('facility')->where('hotel_id',$info['hotel_id'])->first();
          $hotel_amenities = explode(',',$getAminities_ids->facility);
          foreach ($hotel_amenities as $value) {
            if($value == ''){
              continue;
            }
            $hotel_amenity = HotelAmenities::select('hotel_amenities_name')->where('category_id', $data->category_id)->where('hotel_amenities_id',$value)->where('is_trash',0)->first();
            // var_dump($hotel_amenity);
            if($hotel_amenity){
              $hotel_amenity_details[] = $hotel_amenity->hotel_amenities_name;
            }
          }
          if($hotel_amenity_details != []){
              $amenity_group[$data->catgegories] = $hotel_amenity_details;
          }
        }

       if($amenity_group != []){
          $msg=array('status' => 1,'message'=>'Amenity Found','amenities'=>$amenity_group);
          return response()->json($msg);
       }
       else{
          $msg=array('status' => 0,'message'=>'Amenity Not Found');
          return response()->json($msg);
       }
   }
}
