<?php
namespace App\Http\Controllers\Extranetv4;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use DB;
use Illuminate\Database\QueryException;
/**
 * dt 5.7.22
 * @author p_d
 */
class BookingEnginePromotionController extends Controller{

    public function fetchPromotion(Request $request){
        $data = $request->getcontent();
        $curl_call_array = json_decode(json_encode(simplexml_load_string($data)), true);
        //echo "<pre>";print_r($curl_call_array);exit;
        $rate_plans = $curl_call_array['RatePlans']['RatePlan'];
        if(isset($rate_plans['@attributes']['Id'])){
            $rate_plans_ids = $rate_plans['@attributes']['Id'];
        }else{
            foreach($rate_plans as $key => $val){
                $rate_plans_ids[] = $val['@attributes']['Id'];
            }
            $rate_plans_ids = implode(',',$rate_plans_ids);
        }
        $room_ids = $curl_call_array['Rooms']['Room'];
        if(isset($room_ids['@attributes']['Id'])){
            $room_plans_ids = $room_ids['@attributes']['Id'];
        }else{
            foreach($room_ids as $key1 => $val1){
                $room_type_ids[] = $val1['@attributes']['Id'];
            }
            $room_type_ids = implode(',',$room_type_ids);
        }
        //echo $rate_plans_ids;echo $room_type_ids;exit;
        $promotion_data['is_active'] = 1;
        $promotion_data['is_trash'] = 0;
        $promotion_data['ota_rate_plan_id'] = $rate_plans_ids;
        $promotion_data['ota_room_id'] = $room_type_ids;
        $promotion_data['promotion_type'] = $curl_call_array['Promotion']['@attributes']['PromotionType'];
        $promotion_data['promotion_name'] = $curl_call_array['Promotion']['@attributes']['PromotionName'];
        $promotion_data['promotion_id'] = $curl_call_array['Promotion']['@attributes']['PromotionExternalId'];
        $promotion_data['hotel_id'] = $curl_call_array['Promotion']['@attributes']['HotelId'];
        $promotion_data['sale_date_start'] = $curl_call_array['SaleDateRange']['@attributes']['Start'];
        $promotion_data['sale_date_end'] = $curl_call_array['SaleDateRange']['@attributes']['End'];
        $promotion_data['stay_date_start'] = $curl_call_array['StayDateRange']['@attributes']['Start'];
        $promotion_data['stay_date_end'] = $curl_call_array['StayDateRange']['@attributes']['End'];
        $promotion_data['min_numof_room'] = isset($curl_call_array['MinNoOfRooms'])?$curl_call_array['MinNoOfRooms']:null;
        $promotion_data['block_date_start'] = $curl_call_array['BlackoutDateRange']['DateRange']['@attributes']['Start'];
        $promotion_data['block_date_end'] = $curl_call_array['BlackoutDateRange']['DateRange']['@attributes']['End'];
        $promotion_data['los_min'] = isset($curl_call_array['LengthOfStay']['@attributes']['Min'])?$curl_call_array['LengthOfStay']['@attributes']['Min']:null;
        $promotion_data['los_max'] = isset($curl_call_array['LengthOfStay']['@attributes']['Max'])?$curl_call_array['LengthOfStay']['@attributes']['Max']:null;
        $promotion_data['discount'] = $curl_call_array['Discount']['AmountPerBooking']['@attributes']['Value'];
        $promotion_data['ota_promotion_code'] = str_pad(mt_rand(1,99999999),8,'0',STR_PAD_LEFT);
        $insert_data = DB::table('booking_engine.be_promotion')->insert($promotion_data);
        if($insert_data){
            $ota_promotion_code = $promotion_data['ota_promotion_code'];
            $xml = '<?xml version="1.0" encoding="UTF-8"?>
            <HotelPromotion_RS  PromotionId="'.$ota_promotion_code.'">
            <Success/>
            </HotelPromotion_RS>';
            return $xml;
        }else{
            $error_code = str_pad(mt_rand(1,999),3,'0',STR_PAD_LEFT);
            $xml = '<?xml version="1.0" encoding="UTF-8"?>
            <HotelPromotion_RS>
            <Errors>
                <Error Code="'.$error_code.'" ShortText="Data not Processing" Status="NotProcessed"/>
            </Errors>
            </HotelPromotion_RS>';
        }
    //dd($promotion_data);
    }
    public function UpdatePromotion(Request $request){
        $data = $request->getcontent();
        $curl_call_array = json_decode(json_encode(simplexml_load_string($data)), true);
        //echo "<pre>";print_r($curl_call_array);exit;
        $promotion_id = $curl_call_array['Promotion']['@attributes']['PromotionId'];
        $rate_plans = $curl_call_array['RatePlans']['RatePlan'];
        if(isset($rate_plans['@attributes']['Id'])){
            $rate_plans_ids = $rate_plans['@attributes']['Id'];
        }else{
            foreach($rate_plans as $key => $val){
                $rate_plans_ids[] = $val['@attributes']['Id'];
            }
            $rate_plans_ids = implode(',',$rate_plans_ids);
        }
        $room_ids = $curl_call_array['Rooms']['Room'];
        if(isset($room_ids['@attributes']['Id'])){
            $room_plans_ids = $room_ids['@attributes']['Id'];
        }else{
            foreach($room_ids as $key1 => $val1){
                $room_type_ids[] = $val1['@attributes']['Id'];
            }
            $room_type_ids = implode(',',$room_type_ids);
        }
        //echo $rate_plans_ids;echo $room_type_ids;exit;
        $promotion_data['is_active'] = 1;
        $promotion_data['is_trash'] = 0;
        $promotion_data['ota_rate_plan_id'] = $rate_plans_ids;
        $promotion_data['ota_room_id'] = $room_type_ids;
        $promotion_data['promotion_type'] = $curl_call_array['Promotion']['@attributes']['PromotionType'];
        $promotion_data['promotion_name'] = $curl_call_array['Promotion']['@attributes']['PromotionName'];
        $promotion_data['promotion_id'] = $curl_call_array['Promotion']['@attributes']['PromotionExternalId'];
        $promotion_data['hotel_id'] = $curl_call_array['Promotion']['@attributes']['HotelId'];
        $promotion_data['sale_date_start'] = $curl_call_array['SaleDateRange']['@attributes']['Start'];
        $promotion_data['sale_date_end'] = $curl_call_array['SaleDateRange']['@attributes']['End'];
        $promotion_data['stay_date_start'] = $curl_call_array['StayDateRange']['@attributes']['Start'];
        $promotion_data['stay_date_end'] = $curl_call_array['StayDateRange']['@attributes']['End'];
        $promotion_data['min_numof_room'] = isset($curl_call_array['MinNoOfRooms'])?$curl_call_array['MinNoOfRooms']:null;
        $promotion_data['block_date_start'] = $curl_call_array['BlackoutDateRange']['DateRange']['@attributes']['Start'];
        $promotion_data['block_date_end'] = $curl_call_array['BlackoutDateRange']['DateRange']['@attributes']['End'];
        $promotion_data['los_min'] = isset($curl_call_array['LengthOfStay']['@attributes']['Min'])?$curl_call_array['LengthOfStay']['@attributes']['Min']:null;
        $promotion_data['los_max'] = isset($curl_call_array['LengthOfStay']['@attributes']['Max'])?$curl_call_array['LengthOfStay']['@attributes']['Max']:null;
        $promotion_data['discount'] = $curl_call_array['Discount']['AmountPerBooking']['@attributes']['Value'];
        $promotion_data['ota_promotion_code'] = str_pad(mt_rand(1,99999999),8,'0',STR_PAD_LEFT);
        $insert_data = DB::table('be_promotion')->where('promotion_id',$promotion_id)->update($promotion_data);
        if($insert_data){
            $ota_promotion_code = $promotion_data['ota_promotion_code'];
            $xml = '<?xml version="1.0" encoding="UTF-8"?>
            <HotelPromotion_RS  PromotionId="'.$ota_promotion_code.'">
            <Success/>
            </HotelPromotion_RS>';
            return $xml;
        }else{
            $error_code = str_pad(mt_rand(1,999),3,'0',STR_PAD_LEFT);
            $xml = '<?xml version="1.0" encoding="UTF-8"?>
            <HotelPromotion_RS>
            <Errors>
                <Error Code="'.$error_code.'" ShortText="Data not Processing" Status="NotProcessed"/>
            </Errors>
            </HotelPromotion_RS>';
        }
    //dd($promotion_data);
    }
    public function deletePromotion(Request $request){
        $data = $request->getcontent();
        $curl_call_array = json_decode(json_encode(simplexml_load_string($data)), true);
        $promotion_id = $curl_call_array['@attributes']['PromotionId'];
        if(isset($curl_call_array['@attributes']['IsDelete']) == true){
            $update_data = DB::table('be_promotion')->where('promotion_id',$promotion_id)->update(['is_trash'=>1]);
        }else{
            $update_data = DB::table('be_promotion')->where('promotion_id',$promotion_id)->update(['is_trash'=>0]);
        }
        if($update_data){
            $xml = '<?xml version="1.0" encoding="UTF-8"?>
            <HotelPromotion_RS  PromotionId="'.$promotion_id.'">
            <Success/>
            </HotelPromotion_RS>';
        }else{
            $error_code = str_pad(mt_rand(1,999),3,'0',STR_PAD_LEFT);
            $xml = '<?xml version="1.0" encoding="UTF-8"?>
            <HotelPromotion_RS>
            <Errors>
                <Error Code="'.$error_code.'" ShortText="Faild to delete data" Status="NotProcessed"/>
            </Errors>
            </HotelPromotion_RS>';
        }
        return $xml;
    }
    public function activePromotion(Request $request){
        $data = $request->getcontent();
        $curl_call_array = json_decode(json_encode(simplexml_load_string($data)), true);
        $promotion_id = $curl_call_array['@attributes']['PromotionId'];
        //echo $promotion_id.$curl_call_array['@attributes']['IsActive'];exit;
        if(isset($curl_call_array['@attributes']['IsActive']) && $curl_call_array['@attributes']['IsActive'] == 'true'){
            $update_data = DB::table('be_promotion')->where('promotion_id',$promotion_id)->update(['is_active'=>1]);
        }else{
            $update_data = DB::table('be_promotion')->where('promotion_id',$promotion_id)->update(['is_active'=>0]);
        }
        if($update_data){
            $xml = '<?xml version="1.0" encoding="UTF-8"?>
            <HotelPromotion_RS  PromotionId="'.$promotion_id.'">
            <Success/>
            </HotelPromotion_RS>';
        }else{
            $error_code = str_pad(mt_rand(1,999),3,'0',STR_PAD_LEFT);
            $xml = '<?xml version="1.0" encoding="UTF-8"?>
            <HotelPromotion_RS>
            <Errors>
                <Error Code="'.$error_code.'" ShortText="Faild to Process data" Status="NotProcessed"/>
            </Errors>
            </HotelPromotion_RS>';
        }
        return $xml;
    }
}
?>
