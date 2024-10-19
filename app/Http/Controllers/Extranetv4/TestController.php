<?php
namespace App\Http\Controllers\Extranetv4;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Validator;
use DB;
use App\IdsXml;
use App\CmOtaRoomTypeSynchronizeRead;
use App\Http\Controllers\Controller;
use App\HotelInformation;
use App\Invoice;
use App\Coupons;

class TestController extends Controller
{
   
    public function memoryLength(){
        $str = str_repeat('a',  255*1024);
        $x = 'helo';
        $ids_test=new IdsXml();
        $data_test['ids_xml']=$x;
        $resp =  $ids_test->fill($data_test)->save();
    }
    public function multiJoin(){
        $get_ota_room = CmOtaRoomTypeSynchronizeRead::
        join("cm_ota_details",function($join){
            $join->on("cm_ota_details.ota_id","=","cm_ota_room_type_synchronize.ota_type_id")
                ->on("cm_ota_details.hotel_id","=","cm_ota_room_type_synchronize.hotel_id");
        })->where('cm_ota_details.is_active',1)->where('cm_ota_room_type_synchronize.hotel_id',$hotel_id)->where('cm_ota_room_type_synchronize.room_type_id',$room_type[$i])->first();
        
        dd($get_ota_room);
    }

    public function getAppliedCouponDetails(Request $request)
    {
        try
        {
            $data = $request->all();

            $company_id = (isset($data['company_id']) && $data['company_id'] != '') ? $data['company_id'] : '';
            $hotel_id = isset($data['hotel_id']) ? $data['hotel_id'] : '';
            $from_date = date('Y-m-d', strtotime($data['from_date']));
            $to_date = date('Y-m-d', strtotime($data['to_date'].' +1 day'));

            $coupons = new Coupons();

            if ($company_id != '') {

                    $hotels = HotelInformation::where('company_id', $company_id)
                    ->where('status',1)
                    ->pluck('hotel_id');

                    $hotel_ids = array_values($hotels->toArray());

                    $coupons = Coupons::where('company_id', $company_id)
                            // ->where('is_trash', 0)
                            ->get();
            } else {
                    $coupons = Coupons::where('hotel_id', $hotel_id)
                            // ->where('is_trash', 0)
                            ->get();
                    $hotel_ids[] = $hotel_id;
            }
            $coupon_data = [];
            foreach($coupons as $coupon){
                $data['coupon_id']=$coupon['coupon_id']; 
                $data['coupon_code']=$coupon['coupon_code']; 
                $data['coupon_name']=$coupon['coupon_name']; 
                $data['discount']=$coupon['discount']; 
                $coupon_data[$data['coupon_code']]=$data;
            }

           $bookings = Invoice::select('hotel_id','hotel_name','agent_code',DB::raw('group_concat(invoice_id) as invoice_ids'), DB::raw('count(*) as count'))
           ->whereIN('hotel_id',$hotel_ids)
            ->where('booking_status', '=', 1)
            ->where('agent_code', '!=', 'NA')
            ->whereBetween('Booking_date', [$from_date, $to_date])
            ->groupBy('hotel_id','hotel_name','agent_code')
            ->get();
           
            $resp_data = [];
            foreach($bookings as $booking){
                if($booking['agent_code'] != 'NA'){
                    $booking['coupon_name'] = isset($coupon_data[$booking['agent_code']])?$coupon_data[$booking['agent_code']]['coupon_name']:''; 
                    $booking['coupon_code'] = isset($coupon_data[$booking['agent_code']])? $coupon_data[$booking['agent_code']]['coupon_code']:$booking['agent_code']; 
                    $booking['discount'] = isset($coupon_data[$booking['agent_code']])?$coupon_data[$booking['agent_code']]['discount']:'';
                }else{
                    $booking['coupon_name'] ='NA'; 
                    $booking['coupon_code'] = 'NA';
                    $booking['discount'] = 'NA';
                }
               
                $resp_data[] =  $booking;
            }
            $resp =array('status'=>1,'message'=>'Fetched','data'=>$resp_data);
        }catch(Exception $e){
            $resp =array('status'=>0,'message'=>'Fetch failed');
        }
           
            return response()->json($resp);
    }


    public function payuUnpaidBookingTestkit($tnx_id)
    {
            $key = '7rnFly';
            $salt = 'pjVQAWpA';

            $url = 'https://test.payu.in/merchant/postservice.php?form=2';
            $hash_value = $key . '|verify_payment|' . $tnx_id . '|' . $salt;
            $hash = hash('sha512', $hash_value);
            $post = array('key' => $key, 'command' => 'verify_payment', 'hash' => $hash, 'var1' => $tnx_id);
            // dd($hash_value,$hash,$post);
            //  Initiate curl
            $ch = curl_init();
            // Disable SSL verification
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            // Will return the response, if false it print the response
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            // Set the url
            curl_setopt($ch, CURLOPT_URL, $url);
            //curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: '. $key));
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
            // Execute
            $result = curl_exec($ch);
            // Closing
            curl_close($ch);
            // $data = json_decode($result);

            return $result;
            $tr_id = $tnx_id;
            // $payment_status = $data->transaction_details->$tr_id->status;

           
    }

}
