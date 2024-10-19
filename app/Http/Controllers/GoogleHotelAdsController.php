<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Validator;
use DB;
use App\HotelInformation;
use App\MasterRoomType;
use App\MasterHotelRatePlan;
use App\ImageTable;
use App\MasterRatePlan;
use App\MetaSearchEngineSetting;
use App\Inventory;
use App\RatePlanLog;
use App\City;
use App\State;
use App\Country;
use App\GoogleHotelCenterIntranet;
use App\GoogleHotelCenter;
use App\Coupons;

/**
 * This controller is used for fetch the inventory and rate from booking engine database to google hotel ads database
 * @author Ranjit Date: 24-07-2021
 */
class GoogleHotelAdsController extends Controller
{
    public function googleHotelAdsHotelData(Request $request)
    {
        // $hotel_id = $request->input('hotel_id');
        $h_id = json_decode($request->getcontent());
        $hotel_id = $h_id->hotel_id;
        $getRoomData = MasterRoomType::select('*')->where('hotel_id', $hotel_id)->where('is_trash', 0)->get();
        $getRatePlanData = MasterRatePlan::join('room_rate_plan', 'rate_plan_table.rate_plan_id', '=', 'room_rate_plan.rate_plan_id')
            ->select('*')->where('rate_plan_table.hotel_id', $hotel_id)->where('room_rate_plan.be_rate_status', 0)->where('room_rate_plan.is_trash', 0)->get();
        $id = uniqid();
        $time = time();
        $rate_plan_array = [];
        $time = gmdate("Y-m-d", $time) . "T" . gmdate("H:i:s", $time) . '+05:30';
        $xml_data = '<?xml version="1.0" encoding="UTF-8"?>
                    <Transaction timestamp="' . $time . '"
                                 id="' . $id . '"
                                 partner="bookingjini_ari">
                        <PropertyDataSet action="overlay">
                            <Property>' . $hotel_id . '</Property>';
        foreach ($getRoomData as $key => $rooms) {
            $getRate_plan_id = MasterHotelRatePlan::select('rate_plan_id')
                ->where('hotel_id', $hotel_id)
                ->where('room_type_id', $rooms->room_type_id)
                ->where('be_rate_status', 0)
                ->where('is_trash', 0)
                ->get();
            if (sizeof($getRate_plan_id) <= 0) {
                continue;
            }
            $max_occupants = $rooms->max_people + $rooms->extra_person;
            $getImageName = ImageTable::select('image_name')
                ->where('image_id', $rooms->image)->first();
            
            if(isset($getImageName->image_name)){
                $image_url = "https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/" . $getImageName->image_name;
            }else{
                $image_url = "https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/uploads/hotel.jpg";
            }
            
            
            $rooms_description = strip_tags($rooms->description);
            $rooms_description = preg_replace('/[^\p{L}\p{N}\s]/u', '', $rooms_description);
            $room_type_info = str_replace('/', ' ', $rooms->room_type);
            $xml_data .= '<RoomData>
                                <RoomID>' . $rooms->room_type_id . '</RoomID>
                                <Name>
                                    <Text text="' . $room_type_info . '" language="en"/>
                                </Name>
                                <Description>
                                    <Text text="' . $rooms_description . '" language="en"/>
                                </Description>
                                <AllowablePackageIDs>';
            foreach ($getRate_plan_id as $key => $plans) {
                $xml_data .= '<AllowablePackageID>' . $plans->rate_plan_id . '</AllowablePackageID>';
            }
            $xml_data .= '</AllowablePackageIDs>
                                <Capacity>' . $max_occupants . '</Capacity>
                                <PhotoURL>
                                    <Caption>
                                    <Text text="' . $room_type_info . '" language="en"/>
                                    </Caption>
                                    <URL>' . $image_url . '</URL>
                                </PhotoURL>
                            </RoomData>';
        }
        foreach ($getRatePlanData as $key => $rate_plan) {
            $rate_plan_info = str_replace('/', ' ', $rate_plan->plan_name);
            $rate_plan_info = str_replace('&', 'and', $rate_plan->plan_name);
            if (in_array($rate_plan->rate_plan_id, $rate_plan_array)) {
                continue;
            }
            $xml_data .= '<PackageData>
                                    <PackageID>' . $rate_plan->rate_plan_id . '</PackageID>
                                    <Name>
                                        <Text text="' . $rate_plan_info . '" language="en"/>
                                    </Name>
                                    <Description>
                                        <Text text="' . $rate_plan_info . '" language="en"/>
                                    </Description>
                                    <Refundable available="false"/>';
            if ($rate_plan->plan_type != 'EP') {
                $xml_data .= '<BreakfastIncluded>1</BreakfastIncluded>';
            } else {
                $xml_data .= '<BreakfastIncluded>0</BreakfastIncluded>';
            }

            $xml_data .= '</PackageData>';
            $rate_plan_array[] = $rate_plan->rate_plan_id;
        }
        $xml_data .= '</PropertyDataSet>
                    </Transaction>';
        $headers = array(
            "Content-Type: application/xml",
        );


        $url =  'https://www.google.com/travel/hotels/uploads/property_data';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml_data);
        $google_resp = curl_exec($ch);
        curl_close($ch);
        // dd($xml_data,$google_resp);
        $google_resp = json_decode(json_encode(simplexml_load_string($google_resp)), true);
        // if($hotel_id='5032'){
        //      dd($google_resp);
        // }
        if (isset($google_resp["Success"])) {

            $result = GoogleHotelCenterIntranet::where('hotel_id', $hotel_id)->update(['room_type_rate_plan_status' => 1, 'room_type_rate_plan_sync' => date('Y-m-d H:i:s')]);

            $resp = array('status' => 1, 'message' => 'Room creation successfully');
            return response()->json($resp);
        } else {
            $resp = array('status' => 0, 'message' => $google_resp["Issues"]["Issue"]);
            return response()->json($resp);
        }
    }
    public function addHotelToGoogleHotelAds($hotel_id)
    {
        $lat_long_details = HotelInformation::where('hotel_id',$hotel_id)->select('latitude','longitude')->first();
        if(empty($lat_long_details->latitude)){
            $resp = array("status" => 0, "Message" => "Latitude and Longitude are missing.");
            return response()->json($resp);
        }

        $check_hotel_id_exist = MetaSearchEngineSetting::where('name', 'google-hotel')
            ->whereRaw('FIND_IN_SET(' . $hotel_id . ',hotels)')
            ->first();
        if ($check_hotel_id_exist) {
            $resp = array("status" => 0, "Message" => "Hotel code already exist");
            return response()->json($resp);
        } else {
            $get_details = MetaSearchEngineSetting::select('*')
                ->where('name', 'google-hotel')
                ->first();
            if ($get_details) {
                $google_hotel_id = explode(',', $get_details->hotels);
                $google_hotel_id[] = $hotel_id;
                $google_hotel_dlt = implode(',', $google_hotel_id);
                $update = MetaSearchEngineSetting::where('name', 'google-hotel')->update(["hotels" => $google_hotel_dlt]);

                //added hotel in google hotel center intranet table
                $result = GoogleHotelCenterIntranet::where('hotel_id', $hotel_id)->insert(['hotel_id' => $hotel_id]);

                $resp = array("status" => 1, "Message" => "Hotel code created successfully");
                return response()->json($resp);
            }
        }
    }
    public function removeHotelFromGoogleHotelAds($hotel_id)
    {
        $check_hotel_id_exist = MetaSearchEngineSetting::where('name', 'google-hotel')
            ->whereRaw('FIND_IN_SET(' . $hotel_id . ',hotels)')
            ->first();
        if ($check_hotel_id_exist) {
            $google_hotel_id = explode(',', $check_hotel_id_exist->hotels);
            if (($key = array_search($hotel_id, $google_hotel_id)) !== FALSE) {
                unset($google_hotel_id[$key]);
            }
            $google_hotel_dlt = implode(',', $google_hotel_id);
            $update = MetaSearchEngineSetting::where('name', 'google-hotel')->update(["hotels" => $google_hotel_dlt]);

            //remove hotel from google hotel center table
            $result = GoogleHotelCenterIntranet::where('hotel_id', $hotel_id)->delete();
            $result = GoogleHotelCenter::where('hotel_id', $hotel_id)->delete();

            $resp = array("status" => 1, "Message" => "Hotel code removed successfully");
            return response()->json($resp);
        } else {
            $resp = array("status" => 0, "Message" => "Hotel code not present");
            return response()->json($resp);
        }
    }
    public function retrieveGoogleHotelAds()
    {
        $get_all_hotels =  $get_details = MetaSearchEngineSetting::select('*')
            ->where('name', 'google-hotel')
            ->first();
        $hotel_details = array();
        if ($get_all_hotels) {
            $all_hotels = explode(",", $get_all_hotels->hotels);
            foreach ($all_hotels as $key => $hotel_id) {
                $get_hotel_details = HotelInformation::select('hotel_name')->where('hotel_id', $hotel_id)->first();
                $hotel_details[$key]['hotel_id'] = $hotel_id;
                $hotel_details[$key]['hotel_name'] = isset($get_hotel_details->hotel_name) ? $get_hotel_details->hotel_name : 'Deleted';
            }
            if ($hotel_details) {
                $resp = array("status" => 1, "message" => "Google Hotel Ads retrieve successfully", "data" => $hotel_details);
                return response()->json($resp);
            } else {
                $resp = array("status" => 0, "message" => "Google Hotel Ads hotel not available");
                return response()->json($resp);
            }
        } else {
            $resp = array("status" => 0, "message" => "Google Hotel Ads hotel not available");
            return response()->json($resp);
        }
    }
    public function syncInventoryRateToGoogleHotelAds($hotel_id)
    {
        $get_resp = $this->syncInventoryToGoogleHotelAds($hotel_id);
        $get_rate_resp = $this->syncRateToGoogleHotelAds($hotel_id);
        if ($get_resp && $get_rate_resp) {

            $result = GoogleHotelCenterIntranet::where('hotel_id', $hotel_id)->update(['rate_inv_status' => 1, 'rate_inv_sync' => date('Y-m-d H:i:s')]);

            $resp = array("status" => 1, "message" => "Inventory and Rate updated successfully");
            return response()->json($resp);
        } else {
            if ($get_resp) {
                $resp = array("status" => 1, "message" => "Inventory updated successfully and Rate update fails ");
                return response()->json($resp);
            }
            if ($get_rate_resp) {
                $resp = array("status" => 1, "message" => "Rate updated successfully and Inventory update fails");
                return response()->json($resp);
            } else {
                $resp = array("status" => 0, "message" => "Inventory and Rate updated fails");
                return response()->json($resp);
            }
        }
    }
    public function syncInventoryToGoogleHotelAds($hotel_id)
    {
        $start_date = date('Y-m-d');
        // $end_date = date('Y-m-d', strtotime($start_date . "+1 month"));
        $end_date = date('Y-m-d',strtotime($start_date."+3 months"));
        $p_start = $start_date;
        $p_end = $end_date;
        $period     = new \DatePeriod(
            new \DateTime($p_start),
            new \DateInterval('P1D'),
            new \DateTime($p_end)
        );
        $google_rate_xml = '';
        $get_room_types = MasterRoomType::select('*')->where('hotel_id', $hotel_id)->get();
        $id = uniqid();
        $time = time();
        $push_inv = [];
        $time = gmdate("Y-m-d", $time) . "T" . gmdate("H:i:s", $time) . '+05:30';
        $google_xml = '<?xml version="1.0" encoding="UTF-8"?>
        <OTA_HotelInvCountNotifRQ xmlns="http://www.opentravel.org/OTA/2003/05"
                                        EchoToken="' . $id . '"
                                        TimeStamp="' . $time . '"
                                        Version="3.0">
            <POS>
                <Source>
                <RequestorID ID="bookingjini_ari"/>
                </Source>
            </POS>
            <Inventories HotelCode="' . $hotel_id . '">';

        // if ($hotel_id == 42) {
            $restriction_xml_data = '<?xml version="1.0" encoding="UTF-8"?>
                <OTA_HotelAvailNotifRQ xmlns="http://www.opentravel.org/OTA/2003/05"
                                        EchoToken="' . $id . '"
                                        TimeStamp="' . $time . '"
                                        Version="3.0">
                    <POS>
                        <Source>
                        <RequestorID ID="bookingjini_ari"/>
                        </Source>
                    </POS>
                    <AvailStatusMessages HotelCode="' . $hotel_id . '">';
        // }

        foreach ($get_room_types as $key => $rooms) {
            foreach ($period as $key1 => $value) {
                $index = $value->format('Y-m-d');
                $room_type_id = $rooms->room_type_id;
                $get_cur_inv_count = Inventory::select('no_of_rooms', 'block_status', 'los')
                    ->where('hotel_id', $hotel_id)
                    ->where('room_type_id', $room_type_id)
                    ->where('date_from', '<=', $index)
                    ->where('date_to', '>=', $index)
                    ->orderBy('inventory_id', 'DESC')
                    ->first();
                if (empty($get_cur_inv_count)) {
                    continue;
                }
                array_push($push_inv,$get_cur_inv_count);

                $rate_plan_array = [];
                $getRatePlans = MasterHotelRatePlan::select('rate_plan_id')
                    ->where('hotel_id', $hotel_id)
                    ->where('room_type_id', $room_type_id)
                    ->get();

                foreach ($getRatePlans as $rate_id) {
                    $get_cur_rate = RatePlanLog::select('bar_price', 'multiple_days', 'block_status')
                        ->where('hotel_id', $hotel_id)
                        ->where('room_type_id', $room_type_id)
                        ->where('rate_plan_id', $rate_id->rate_plan_id)
                        ->where('from_date', '<=', $index)
                        ->where('to_date', '>=', $index)
                        ->orderBy('rate_plan_log_id', 'DESC')
                        ->first();

                    $rate_plan_array[] = array('rate_plan' => $rate_id->rate_plan_id, 'block_status' => $get_cur_rate['block_status']);
                }

                $no_of_rooms = isset($get_cur_inv_count->no_of_rooms) ? $get_cur_inv_count->no_of_rooms : 0;
                if ($no_of_rooms >= 0 && $get_cur_inv_count->block_status == 0) {
                    $google_xml .= '<Inventory>
                                    <StatusApplicationControl Start="' . $index . '"
                                                                End="' . $index . '"
                                                                InvTypeCode="' . $room_type_id . '"/>
                                    <InvCounts>
                                        <InvCount Count="' . $no_of_rooms . '" CountType="2"/>
                                    </InvCounts>
                                </Inventory>';
                    // if ($hotel_id == 42) {
                        // $res = $this->rateBlockStatusUpdateToGoogleAds($hotel_id, $room_type_id, $rate_plan_array, $index, $index, $get_cur_inv_count->los);

                        foreach ($rate_plan_array as $rate) {
                            $restriction_xml_data .= '<AvailStatusMessage>
                                        <StatusApplicationControl Start="' . $index . '"
                                                                    End="' . $index . '"
                                                                    Mon="1"
                                                                    Tue="1"
                                                                    Weds="1"
                                                                    Thur="1"
                                                                    Fri="1"
                                                                    Sat="1"
                                                                    Sun="1"
                                                                    InvTypeCode="' . $room_type_id . '"
                                                                    RatePlanCode="' . $rate['rate_plan'] . '"/>
                                        <LengthsOfStay>
                                        <LengthOfStay Time="' . $get_cur_inv_count->los . '"
                                                    TimeUnit="Day"
                                                    MinMaxMessageType="SetMinLOS"/>
                                        </LengthsOfStay>';

                            if ($rate['block_status'] == 0) {
                                $block_status = 'Open';
                            } else {
                                $block_status = 'Close';
                            }

                            $restriction_xml_data .= '<RestrictionStatus Status="' . $block_status . '" Restriction="Master"/>
                                    </AvailStatusMessage>';
                        }
                    // }
                }
                if ($no_of_rooms >= 0 && $get_cur_inv_count->block_status == 1) {
                    $google_xml .= '<Inventory>
                                    <StatusApplicationControl Start="' . $index . '"
                                                                End="' . $index . '"
                                                                InvTypeCode="' . $room_type_id . '"/>
                                    <InvCounts>
                                        <InvCount Count="0" CountType="2"/>
                                    </InvCounts>
                                </Inventory>';
                }
                // if ($no_of_rooms == 0) {
                //     $getRatePlans = MasterHotelRatePlan::select('rate_plan_id')
                //         ->where('hotel_id', $hotel_id)
                //         ->where('room_type_id', $room_type_id)
                //         ->get();
                //     foreach ($getRatePlans as $rate_id) {
                //         $rate_plan_id = $rate_id->rate_plan_id;
                //         $google_rate_xml .= '<RateAmountMessage>
                //                                 <StatusApplicationControl Start="' . $index . '"
                //                                                                 End="' . $index . '"
                //                                                                 Mon="1"
                //                                                                 Tue="1"
                //                                                                 Weds="1"
                //                                                                 Thur="1"
                //                                                                 Fri="1"
                //                                                                 Sat="1"
                //                                                                 Sun="1"
                //                                                                 InvTypeCode="' . $room_type_id . '"
                //                                                                 RatePlanCode="' . $rate_plan_id . '"/>
                //                                 <Rates>
                //                                     <Rate>
                //                                         <BaseByGuestAmts>
                //                                             <BaseByGuestAmt AmountBeforeTax="0"
                //                                                             AmountAfterTax="0"
                //                                                             CurrencyCode="INR"
                //                                                             NumberOfGuests="2"/>
                //                                         </BaseByGuestAmts>
                //                                     </Rate>
                //                                 </Rates>
                //                             </RateAmountMessage>';
                //     }
                // }
            }
        }

        if(empty($push_inv)){
            return 0;
        }

        // if ($hotel_id == 42) {
            $restriction_xml_data .= '</AvailStatusMessages>
                            </OTA_HotelAvailNotifRQ>';
            
             $res = $this->rateBlockStatusUpdateToGoogleAds($restriction_xml_data);

        // }

        $google_xml .= '</Inventories>
        </OTA_HotelInvCountNotifRQ>';
        $update_inv =  $this->inventoryUpdateToGoogleAds($google_xml, $google_rate_xml, $hotel_id);
        return $update_inv;
    }
    public function inventoryUpdateToGoogleAds($inv_xml_data, $rate_xml_data, $hotel_id)
    {
        $headers = array(
            "Content-Type: application/xml",
        );
        $url =  'https://www.google.com/travel/hotels/uploads/ota/hotel_inv_count_notif';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $inv_xml_data);
        $google_resp = curl_exec($ch);
        curl_close($ch);
        $google_resp = json_decode(json_encode(simplexml_load_string($google_resp)), true);
        if (isset($google_resp["Success"])) {
            if ($rate_xml_data != '') {
                $id = uniqid();
                $time = time();
                $time = gmdate("Y-m-d", $time) . "T" . gmdate("H:i:s", $time) . '+05:30';
                $google_rate_xml_data = '<?xml version="1.0" encoding="UTF-8"?>
                <OTA_HotelRateAmountNotifRQ xmlns="http://www.opentravel.org/OTA/2003/05"
                                                    EchoToken="' . $id . '"
                                                    TimeStamp="' . $time . '"
                                                    Version="3.0"
                                                    NotifType="Overlay"
                                                    NotifScopeType="ProductRate">
                    <POS>
                        <Source>
                        <RequestorID ID="bookingjini_ari"/>
                        </Source>
                    </POS>
                    <RateAmountMessages HotelCode="' . $hotel_id . '">
                        ' . $rate_xml_data . '
                    </RateAmountMessages>
                </OTA_HotelRateAmountNotifRQ>';
                $headers = array(
                    "Content-Type: application/xml",
                );
                $url =  'https://www.google.com/travel/hotels/uploads/ota/hotel_rate_amount_notif';
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $google_rate_xml_data);
                $google_rate_resp = curl_exec($ch);
                curl_close($ch);
                $google_rate_resp = json_decode(json_encode(simplexml_load_string($google_rate_resp)), true);
            }
            return 1;
        } else {
            return 0;
        }
    }
    public function syncRateToGoogleHotelAds($hotel_id)
    {
        $hotel_info = HotelInformation::join('kernel.company_table','hotels_table.company_id','=','company_table.company_id')
        ->where('hotel_id',$hotel_id)
        ->select('hotels_table.is_taxable','company_table.currency')
        ->first();

        if($hotel_info){
            $currency = $hotel_info->currency;
        }else{
            $currency ='INR';
        }
        $start_date = date('Y-m-d');
        $end_date = date('Y-m-d', strtotime($start_date . "+3 months"));
        $p_start = $start_date;
        $p_end = $end_date;
        $period     = new \DatePeriod(
            new \DateTime($p_start),
            new \DateInterval('P1D'),
            new \DateTime($p_end)
        );
        $google_rate_xml = '';
        $get_room_types = MasterRoomType::select('*')->where('hotel_id', $hotel_id)->get();
        $id = uniqid();
        $time = time();
        $push_rates = [];
        $time = gmdate("Y-m-d", $time) . "T" . gmdate("H:i:s", $time) . '+05:30';
        $google_rate_xml = '<?xml version="1.0" encoding="UTF-8"?>
        <OTA_HotelRateAmountNotifRQ xmlns="http://www.opentravel.org/OTA/2003/05" EchoToken="' . $id . '" TimeStamp="' . $time . '" Version="3.0" NotifType="Overlay" NotifScopeType="ProductRate"><POS><Source><RequestorID ID="bookingjini_ari"/></Source></POS><RateAmountMessages HotelCode="' . $hotel_id . '">';
        foreach ($get_room_types as $key => $rooms) {
            $room_type_id = $rooms->room_type_id;
            $getRatePlans = MasterHotelRatePlan::select('rate_plan_id')
                ->where('hotel_id', $hotel_id)
                ->where('room_type_id', $room_type_id)
                ->where('be_rate_status', 0)
                ->get();
            foreach ($getRatePlans as $rate_id) {
                foreach ($period as $key1 => $value) {
                    $index = $value->format('Y-m-d');
                    $rate_plan_id = $rate_id->rate_plan_id;
                    $get_cur_rate_count = RatePlanLog::select('bar_price', 'multiple_days')
                        ->where('hotel_id', $hotel_id)
                        ->where('room_type_id', $room_type_id)
                        ->where('rate_plan_id', $rate_plan_id)
                        ->where('from_date', '<=', $index)
                        ->where('to_date', '>=', $index)
                        ->where('block_status', '!=', 1)
                        ->orderBy('rate_plan_log_id', 'DESC')
                        ->first();
                    if (!$get_cur_rate_count) {
                        continue;
                    } else {

                        array_push($push_rates,$get_cur_rate_count);


                        // $check_coupon = Coupons::select('discount')
                        // ->where(["hotel_id" => $hotel_id, "room_type_id" => $room_type_id, 'coupon_for' => 1])
                        // ->where('valid_from', '<=', $index)
                        // ->where('valid_to', '>=', $index)
                        // ->orWhere(function ($query) use ($hotel_id, $index) {
                        //     $query->where(["hotel_id" => $hotel_id, "room_type_id" => 0, 'coupon_for' => 1])
                        //         ->where('valid_from', '<=', $index)
                        //         ->where('valid_to', '>=', $index);
                        // })
                        // ->orderBy('coupon_id', 'DESC')
                        // ->first();
                        // if($check_coupon){
                        //     $discount_per = $check_coupon->discount;
                        //     $discount_amt = round($get_cur_rate_count->bar_price * $discount_per / 100);
                        //     $bar_price  = $get_cur_rate_count->bar_price - $discount_amt;
                        // }else{
                            $bar_price = (int)$get_cur_rate_count->bar_price;
                        // }
                     
                        if($hotel_info->is_taxable==1){
                            $get_Gst_price = $this->gstPrice($bar_price);
                        }else{
                            $get_Gst_price = 0;
                        }
                       
                        $bar_price = (float)$bar_price;
                        $total_price = (float)($bar_price + $get_Gst_price);
                        $multiple_days = json_decode($get_cur_rate_count->multiple_days);
                        foreach ($multiple_days as $key => $days) {
                            if ($key == 'Mon') {
                                $Mon = $days;
                            }
                            if ($key == 'Tue') {
                                $Tue = $days;
                            }
                            if ($key == 'Wed') {
                                $Weds = $days;
                            }
                            if ($key == 'Thu') {
                                $Thur = $days;
                            }
                            if ($key == 'Fri') {
                                $Fri = $days;
                            }
                            if ($key == 'Sat') {
                                $Sat = $days;
                            }
                            if ($key == 'Sun') {
                                $Sun = $days;
                            }
                        }
                        if (isset($bar_price) && $bar_price >= 0) {
                            $google_rate_xml .= '<RateAmountMessage>
                            <StatusApplicationControl Start="' . $index . '" End="' . $index . '" Mon="' . $Mon . '" Tue="' . $Tue . '" Weds="' . $Weds . '" Thur="' . $Thur . '" Fri="' . $Fri . '" Sat="' . $Sat . '" Sun="' . $Sun . '" InvTypeCode="' . $room_type_id . '" RatePlanCode="' . $rate_plan_id . '"/>
                            <Rates><Rate><BaseByGuestAmts><BaseByGuestAmt AmountBeforeTax="' . $bar_price . '" AmountAfterTax="' . $total_price . '" CurrencyCode="'.$currency.'" NumberOfGuests="2"/></BaseByGuestAmts></Rate></Rates></RateAmountMessage>';
                        }
                    }
                }
            }
        }

        // if($hotel_id==860){
            if(empty($push_rates)){
                return 0;
            }
        // }

        
        $google_rate_xml .= '</RateAmountMessages></OTA_HotelRateAmountNotifRQ>';
        $update_rate =  $this->rateUpdateToGoogleAds($google_rate_xml);
        return $update_rate;
    }
    public function rateUpdateToGoogleAds($google_rate_xml)
    {
        $headers = array(
            "Content-Type: application/xml",
        );
        $url =  'https://www.google.com/travel/hotels/uploads/ota/hotel_rate_amount_notif';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $google_rate_xml);
        $google_resp = curl_exec($ch);
        curl_close($ch);
        $google_resp = json_decode(json_encode(simplexml_load_string($google_resp)), true);

        if (isset($google_resp["Success"])) {
            return 1;
        } else {
            return 0;
        }
    }
    public function gstPrice($bar_price)
    {
        $percentage = 0;
        if ($bar_price > 7500) {
            $percentage = 18;
        } else if ($bar_price > 0 && $bar_price <= 7500) {
            $percentage = 12;
        }
        $gstprice = $bar_price * $percentage / 100;
        return $gstprice;
    }
    public function downloadXML()
    {
        $google_hotel_info = MetaSearchEngineSetting::select('*')
            ->where('name', 'google-hotel')
            ->first();
        $hotel_info = explode(',', $google_hotel_info->hotels);
        $google_xml = '<?xml version="1.0" encoding="UTF-8"?>
        <listings xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
            xsi:noNamespaceSchemaLocation="http://www.gstatic.com/localfeed/local_feed.xsd">
        <language>en</language>';
        foreach ($hotel_info as $hotel_id) {
            //Get City name by city id
            $hotel_dlt = HotelInformation::select('*')->where('hotel_id', $hotel_id)->first();
            if (empty($hotel_dlt)) {
                continue;
            }
            $city = City::where('city_id', $hotel_dlt->city_id)->select('city_name')->first();
            $city = isset($city->city_name) ? $city->city_name : "";
            //Get State name by state id
            $state = State::where('state_id', $hotel_dlt->state_id)->select('state_name')->first();
            $state = isset($state->state_name) ? $state->state_name : '';
            //Get Country name by country id
            $country = Country::where('country_id', $hotel_dlt->country_id)->select('country_name', 'country_code')->first();
            $country = isset($country->country_code) ? $country->country_code : "";
            //Get email id
            $email_arr = explode(',', $hotel_dlt->email_id);
            //Get phone 
            $phone_arr = explode(',', $hotel_dlt->mobile);
            $description = str_replace(array("\r", "\n", "\t"), '', $hotel_dlt->hotel_description); //Removing the carrige return from string
            $hotel_dlt->hotel_address = str_replace(array("\r", "\n", "\t"), '', $hotel_dlt->hotel_address); //Removing the carrige return from string
            $hotel_dlt->hotel_address = str_replace(array("&"), '&amp;', $hotel_dlt->hotel_address); //Removing the & from string
            $state = str_replace(array("&"), '&amp;', $state);
            $city = str_replace(array("&"), '&amp;', $city);
            $hotel_dlt->hotel_name = str_replace(array("&"), '&amp;', $hotel_dlt->hotel_name);
            $google_xml .= '<listing>
            <id>' . $hotel_dlt->hotel_id . '</id>
            <name>' . $hotel_dlt->hotel_name . '</name>
            <address format="simple">
            <component name="addr1">' . $hotel_dlt->hotel_address . '</component>
            <component name="city">' . $city . '</component>
            <component name="province">' . $state . '</component>
            <component name="postal_code">' . $hotel_dlt->pin . '</component>
            </address>
            <country>' . $country . '</country>
            <latitude>' . $hotel_dlt->latitude . '</latitude>
            <longitude>' . $hotel_dlt->longitude . '</longitude>
            <phone type="main">' . $phone_arr[0] . '</phone>
            <category>hotel</category>
        </listing>';
        }
        // exit();
        $google_xml .= '</listings>';
        $filename = "googleHotel_" . date('Y_m_d_h_i_s') . ".xml";
        header('Content-type: text/xml');
        header('Content-Disposition: attachment; filename=' . $filename);
        echo $google_xml;
    }


    public function ghcHotelList()
    {
        $google_hotel_info = MetaSearchEngineSetting::select('*')
            ->where('name', 'google-hotel')
            ->first();
        $hotel_info = explode(',', $google_hotel_info->hotels);
        $google_xml = '<?xml version="1.0" encoding="UTF-8"?>
        <listings xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
            xsi:noNamespaceSchemaLocation="http://www.gstatic.com/localfeed/local_feed.xsd">
        <language>en</language>';
        foreach ($hotel_info as $hotel_id) {
            //Get City name by city id
            $hotel_dlt = HotelInformation::select('*')->where('hotel_id', $hotel_id)->first();
            if (empty($hotel_dlt)) {
                continue;
            }
            $city = City::where('city_id', $hotel_dlt->city_id)->select('city_name')->first();
            $city = isset($city->city_name) ? $city->city_name : "";
            //Get State name by state id
            $state = State::where('state_id', $hotel_dlt->state_id)->select('state_name')->first();
            $state = isset($state->state_name) ? $state->state_name : '';
            //Get Country name by country id
            $country = Country::where('country_id', $hotel_dlt->country_id)->select('country_name', 'country_code')->first();
            $country = isset($country->country_code) ? $country->country_code : "";
            //Get email id
            $email_arr = explode(',', $hotel_dlt->email_id);
            //Get phone 
            $phone_arr = explode(',', $hotel_dlt->mobile);
            $description = str_replace(array("\r", "\n", "\t"), '', $hotel_dlt->hotel_description); //Removing the carrige return from string
            $hotel_dlt->hotel_address = str_replace(array("\r", "\n", "\t"), '', $hotel_dlt->hotel_address); //Removing the carrige return from string
            $hotel_dlt->hotel_address = str_replace(array("&"), '&amp;', $hotel_dlt->hotel_address); //Removing the & from string
            $state = str_replace(array("&"), '&amp;', $state);
            $city = str_replace(array("&"), '&amp;', $city);
            $hotel_dlt->hotel_name = str_replace(array("&"), '&amp;', $hotel_dlt->hotel_name);
            $google_xml .= '<listing>
            <id>' . $hotel_dlt->hotel_id . '</id>
            <name>' . $hotel_dlt->hotel_name . '</name>
            <address format="simple">
            <component name="addr1">' . $hotel_dlt->hotel_address . '</component>
            <component name="city">' . $city . '</component>
            <component name="province">' . $state . '</component>
            <component name="postal_code">' . $hotel_dlt->pin . '</component>
            </address>
            <country>' . $country . '</country>
            <latitude>' . $hotel_dlt->latitude . '</latitude>
            <longitude>' . $hotel_dlt->longitude . '</longitude>
            <phone type="main">' . $phone_arr[0] . '</phone>
            <category>hotel</category>
        </listing>';
        }
        // exit();
        $google_xml .= '</listings>';
        $filename = "Bookingjini ARI_local.xml";
        header('Content-type: text/xml');
        header('Content-Disposition: attachment; filename=' . $filename);
        echo $google_xml;
    }

    public function rateBlockStatusUpdateToGoogleAds($xml_data)
    {

        $headers = array(
            "Content-Type: application/xml",
        );

        $url =  'https://www.google.com/travel/hotels/uploads/ota/hotel_avail_notif';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml_data);
        $google_resp = curl_exec($ch);
        curl_close($ch);

        $google_resp = json_decode(json_encode(simplexml_load_string($google_resp)), true);
        // dd($google_resp,$xml_data);
        if (isset($google_resp["Success"])) {
            $resp = array('status' => 1, 'response_msg' => 'Rate Block Updated');
            return response()->json($resp);
        } else {
            $resp = array('status' => 0, 'response_msg' => 'Rate Block Update failed');
            return response()->json($resp);
        }
    }


    public function roomTypeSync()
    {

        // $room_type_all = GoogleHotelCenterIntranet::join('google_hotel_center','google_hotel_center.hotel_id','=','google_hotel_center_intranet.hotel_id')
        // ->where('google_hotel_center_intranet.rate_inv_sync','>=','2023-02-14')
        // ->where('google_hotel_center_intranet.rate_inv_sync','<=','2023-02-15')
        // ->where('google_hotel_center.live_ghc_status','1')
        // ->skip(20)
        // ->take(20)->get();

        // return $room_type_all;

        $room_type_all = [4327,4328,4330,4341,4344,4345,4346,4358,4364,4376,4398,4399];


        foreach ($room_type_all as $data) {
            $hotel_id = $data;
            $get_resp = $this->syncInventoryToGoogleHotelAds($hotel_id);
            $get_rate_resp = $this->syncRateToGoogleHotelAds($hotel_id);

            if ($get_resp && $get_rate_resp) {
                $result = GoogleHotelCenterIntranet::where('hotel_id', $hotel_id)->update(['rate_inv_status' => 1, 'rate_inv_sync' => date('Y-m-d H:i:s')]);
            }
        }
    }


}
