<?php

namespace App\Http\Controllers\Extranetv4;

use Illuminate\Http\Request;
use App\GoogleHotelCenter;
use App\Http\Controllers\IpAddressService;
use DB;

/**
 * This controller is used for update the hotel with google hotel center.
 * @author Saroj Patel Date: 07-02-2023
 */
class GoogleHotelCenterController extends Controller
{
    protected $ipService;
    protected $url = 'https://travelpartner.googleapis.com/v3/accounts/2063969751/';

    public function __construct(IpAddressService $ipService)
    {
        $this->ipService = $ipService;
        $this->url;
    }

    public function token()
    {
        $url_token = 'https://kernel.bookingjini.com/google-hotel-center-access-token';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, $url_token);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_HTTPHEADER,  array(
            'Authorization: Bearer '
        ));
        $result = curl_exec($ch);
        curl_close($ch);
        $access_token = json_decode($result);
        $access_token = $access_token->access_token;

        return $access_token;
    }

    //Sync hotel status with GHC
    public function syncHotelList()
    {
        $access_token = $this->token();
        $url = $this->url . 'hotelViews';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_HTTPHEADER,  array(
            'Authorization: Bearer ' . $access_token
        ));
        $result = curl_exec($ch);
        curl_close($ch);
        $results = json_decode($result);
        if ($results) {
            $hotelViews = $results->hotelViews;
            foreach ($hotelViews as $res) {
                if ($res->matchStatus == 'MATCHED') {
                    $match_status = 1;
                } else {
                    $match_status = 0;
                }

                $hotel_details['hotel_id'] = $res->partnerHotelId;
                $hotel_details['ghc_hotel_id'] = isset($res->googleHotelId) ? $res->googleHotelId : '';
                $hotel_details['ghc_display_name'] = isset($res->partnerHotelDisplayName) ? $res->partnerHotelDisplayName:'';
                $hotel_details['hotel_display_name'] = isset($res->googleHotelDisplayName) ? $res->googleHotelDisplayName : '';
                $hotel_details['map_matched_status'] = $match_status;
                $hotel_details['live_ghc_status'] = isset($res->liveOnGoogle) ? $res->liveOnGoogle : '';
                $hotel_details['property_details'] = isset($res->propertyDetails) ? $res->propertyDetails : '';

                // $sync_hotel_details['rate_plan_status'] = 1;
                // $sync_hotel_details['room_type_status'] = 1;
                // $sync_hotel_details['rate_status'] = 1;
                // $sync_hotel_details['inv_status'] = 1;

                // $result = DB::table('google_hotel_center_intranet')->where('hotel_id', $res->partnerHotelId)->update($sync_hotel_details);

                $ghc_details = GoogleHotelCenter::where('hotel_id', $res->partnerHotelId)->first();
                if ($ghc_details) {
                    $ghc_details = GoogleHotelCenter::where('hotel_id', $res->partnerHotelId)->update($hotel_details);
                } else {
                    $ghc_details = GoogleHotelCenter::insert($hotel_details);
                }
            }

            if ($ghc_details) {
                return response()->json(array('status' => 1, 'message' => "Hotel details Sync"));
            } else {
                return response()->json(array('status' => 0, 'message' => "Hotel details Sync Failed"));
            }
        } else {
            return response()->json(array('status' => 0, 'message' => "Hotel details Sync Failed"));
        }
    }

    //Fetch Hotel list
    public function ghcHotelList($filter)
    {
        $hotels_list = GoogleHotelCenter::leftjoin('google_hotel_center_intranet', 'google_hotel_center_intranet.hotel_id', '=', 'google_hotel_center.hotel_id')
            ->select('google_hotel_center.hotel_id', 'google_hotel_center.ghc_display_name', 'google_hotel_center.map_matched_status', 'google_hotel_center.live_ghc_status', 'google_hotel_center.property_details','google_hotel_center_intranet.room_type_rate_plan_status', 'google_hotel_center_intranet.rate_inv_status', 'google_hotel_center_intranet.room_type_rate_plan_sync', 'google_hotel_center_intranet.rate_inv_sync');
        
            //all hotel list for the summery
            $all_hotel_list = $hotels_list->get();

            if($filter=='matched'){
                $hotels_list = $hotels_list->where('map_matched_status',1);
            }elseif($filter=='unmatched'){
                $hotels_list = $hotels_list->where('map_matched_status',0);
            }elseif($filter=='live'){
                $hotels_list = $hotels_list->where('live_ghc_status',1);
            }elseif($filter=='not-live'){
                $hotels_list = $hotels_list->where('live_ghc_status',0);
            }elseif($filter=='match-live'){
                $hotels_list = $hotels_list->where('map_matched_status',1)->where('live_ghc_status',1);
            }elseif($filter=='match-not-live'){
                $hotels_list = $hotels_list->where('map_matched_status',1)->where('live_ghc_status',0);
            }elseif($filter=='unmatch-live'){
                $hotels_list = $hotels_list->where('map_matched_status',0)->where('live_ghc_status',1);
            }elseif($filter=='unmatch-not-live'){
                $hotels_list = $hotels_list->where('map_matched_status',0)->where('live_ghc_status',0);
            }
    
        $hotels_list = $hotels_list->get();

        $total_hotel = count($all_hotel_list);
        $match_hotel = 0;
        $unmatch_hotel = 0;
        $live_hotel = 0;
        $match_with_live = 0;
        $match_with_notlive = 0;
        $unmatch_with_live = 0;
        $unmatch_with_notlive = 0;
        $notlive_hotel = 0;

        $hotel_list_array = [];
        if (sizeof($hotels_list) > 0) {
            foreach ($hotels_list as $details) {
                if($details->room_type_rate_plan_status == 1){
                    $room_type_rate_plan_sync = date('d M Y h:i A', strtotime($details->room_type_rate_plan_sync));
                }else{
                    $room_type_rate_plan_sync = '';
                }

                if($details->rate_inv_status == 1){
                    $rate_inv_sync = date('d M Y h:i A', strtotime($details->rate_inv_sync));
                }else{
                    $rate_inv_sync = '';
                }

                $hotel_data['hotel_id'] = $details->hotel_id;
                $hotel_data['hotel_name'] = $details->ghc_display_name;
                $hotel_data['map_matched_status'] = ($details->map_matched_status == 1) ? 'Matched' : 'Not Matched';
                $hotel_data['live_ghc_status'] = ($details->live_ghc_status == 1) ? 'Live' : 'Not Live';
                $hotel_data['room_type_rate_plan_sync'] = $room_type_rate_plan_sync;
                $hotel_data['rate_inv_sync'] = $rate_inv_sync;
                $hotel_data['property_url'] = $details->property_details;
                array_push($hotel_list_array, $hotel_data);
            }

            //all hotel list for the summery
            foreach ($all_hotel_list as $list) {

                if ($list->map_matched_status == 1) {
                    $match_hotel = $match_hotel + 1;
                } else {
                    $unmatch_hotel = $unmatch_hotel + 1;
                }

                if ($list->live_ghc_status == 1) {
                    $live_hotel = $live_hotel + 1;
                }else{
                    $notlive_hotel = $notlive_hotel + 1;
                }

                if ($list->map_matched_status == 1 && $list->live_ghc_status == 1) {
                    $match_with_live = $match_with_live + 1;
                } elseif ($list->map_matched_status == 1 && $list->live_ghc_status == 0) {
                    $match_with_notlive = $match_with_notlive + 1;
                } elseif ($list->map_matched_status == 0 && $list->live_ghc_status == 1) {
                    $unmatch_with_live = $unmatch_with_live + 1;
                } else {
                    $unmatch_with_notlive = $unmatch_with_notlive + 1;
                }
            }
        }

        $summery[] = array('name' => 'Total Hotel', 'value' => $total_hotel,'filter_key' =>'all');
        $summery[] = array('name' => 'Matched Hotel', 'value' => $match_hotel,'filter_key' =>'matched');
        $summery[] = array('name' => 'Unmatched Hotel', 'value' => $unmatch_hotel,'filter_key' =>'unmatched');
        $summery[] = array('name' => 'Live Hotel', 'value' => $live_hotel,'filter_key' =>'live');
        $summery[] = array('name' => 'Not Live Hotel', 'value' => $notlive_hotel,'filter_key' =>'not-live');
        $summery[] = array('name' => 'Matched with live Hotel', 'value' => $match_with_live,'filter_key' =>'match-live');
        $summery[] = array('name' => 'Matched with not live Hotel', 'value' => $match_with_notlive,'filter_key' =>'match-not-live');
        $summery[] = array('name' => 'Unmatched with live Hotel', 'value' => $unmatch_with_live,'filter_key' =>'unmatch-live');
        $summery[] = array('name' => 'Unmatched with not live Hotel', 'value' => $unmatch_with_notlive,'filter_key' =>'unmatch-not-live');

        if (sizeof($hotel_list_array) > 0) {
            return response()->json(array('status' => 1, 'message' => "Hotels Fetched", 'list' => $hotel_list_array, 'summery' => $summery));
        } else {
            return response()->json(array('status' => 0, 'message' => "Hotels Fetched Failed"));
        }
    }

    //Change live status 
    public function ghcLiveStatus($hotel_id,$status)
    {
        $access_token = $this->token();
        $liveOnGoogle = $status;
        $hotelIds = $hotel_id;
     
        $postData = array("liveOnGoogle" => $liveOnGoogle, "partnerHotelIds" => $hotelIds);
        $url = $this->url . 'hotels:setLiveOnGoogle';
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($postData),
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . $access_token,
                'Content-Type: application/json'
            )
        ));
        $result = curl_exec($curl);
        curl_close($curl);
        $res = json_decode($result);

        if (isset($res->updatedHotelIds)) {
            GoogleHotelCenter::where('hotel_id', $res->updatedHotelIds)->update(['live_ghc_status'=>$status]);
            return response()->json(array('status' => 1, 'message' => "Live status Updated"));
        } else {
            return response()->json(array('status' => 0, 'message' => "Live status Updated Failed"));
        }
    }


    public function ghcPendingHotelList(){

        $hotels_list = DB::select("SELECT ghc.hotel_id, ht.hotel_name, ghc.added_on_ghc
        FROM google_hotel_center_intranet as ghc join kernel.hotels_table as ht on ht.hotel_id = ghc.hotel_id
        WHERE NOT EXISTS 
            (SELECT  *
             FROM google_hotel_center 
             WHERE ghc.hotel_id = google_hotel_center.hotel_id)");

            $hotel_list_array = [];
             foreach($hotels_list as $hotel){

                $hotel_data['hotel_id'] = $hotel->hotel_id;
                $hotel_data['hotel_name'] = $hotel->hotel_name;
                $hotel_data['added_on'] = date('d M Y h:i A', strtotime($hotel->added_on_ghc));
                // $hotel_data['room_type_rate_plan_status'] = $room_type_rate_plan_sync;
                // $hotel_data['rate_inv_status'] = $rate_inv_sync;
              
                array_push($hotel_list_array, $hotel_data);
             }

            if (sizeof($hotel_list_array) > 0) {
                return response()->json(array('status' => 1, 'message' => "Hotels Fetched", 'list' => $hotel_list_array));
            } else {
                return response()->json(array('status' => 0, 'message' => "Hotels Fetched Failed"));
            }

    }

}
