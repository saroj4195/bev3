<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Validator;
use DB;
use App\IdsXml;
use App\CmOtaRoomTypeSynchronizeRead;
use App\Http\Controllers\InventoryService;


use App\MasterHotelRatePlan; //class name from model
use App\RatePlanLog; //class name from model
use App\CompanyDetails;
use App\HotelInformation;
use App\DayBookingARI;
use App\DayPackage;
use App\DayBookings;
use App\ImageTable;
use App\DayBookingPromotions;

class TestController extends Controller
{
    protected $invService;

    public function __construct(InventoryService $invService)
    {
       $this->invService = $invService;
      
    }
   
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
    public function testCmProcess(){
        $url = "https://cm.bookingjini.com/inventory-update-to-cm";
        $cm_array = array(
            "hotel_id"=>1953,
            "room_type_id"=>6216,
            "rooms"=>4,
            "old_check_in"=>'2022-04-22',
            "old_check_out"=>'2022-04-24',
        );
        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_POST, true );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $cm_array);
        $response = curl_exec($ch);
        curl_close($ch);
        dd($response);
    } 

    public function insertDataIntoAddonCharges(){
        $getData = DB::table('kernel.hotels_table')->where('status',1)->get();
        foreach($getData as $value){
            $data = array(
                "hotel_id" => $value->hotel_id,
                "infant"=>"0-5",
                "children"=>"5-12"
            );
            $insert_data = DB::table('bookingengine_age_setup')->insert($data);
        }
    }

    public function testGetRatesByRoomnRatePlan(Request $request){
        $data = $request->all();

        $rates =  $this->invService->getRatesByRoomnRatePlan($data['room_id'],$data['rate_id'],$data['date_from'],$data['date_to']);
        print_r($rates);

        $rates =  $this->getRatesByRoomnRatePlan($data['room_id'],$data['rate_id'],$data['date_from'],$data['date_to']);
        print_r($rates);exit;

    }

    public function getRatesByRoomnRatePlan(int $room_type_id, int $rate_plan_id, string $date_from, string $date_to)
    {
        $filtered_rate = array();
        $date1 = date_create($date_from);
        $date2 = date_create($date_to);
        $date3 = date_create(date('Y-m-d'));
        $diff = date_diff($date1, $date2);
        $diff = $diff->format("%a");
        $rateplanlog = new RatePlanLog();
        $masterhotelrateplan = new MasterHotelRatePlan();
        $room_rate_plan_data = $masterhotelrateplan->where(['rate_plan_id' => $rate_plan_id])
            ->select('hotel_id')
            ->where(['room_type_id' => $room_type_id])
            ->first();
        $hotel_id = $room_rate_plan_data->hotel_id;
        $hotel_info = HotelInformation::where('hotel_id', $hotel_id)->first();
        $comp_info = CompanyDetails::where('company_id', $hotel_info->company_id)->first();
        $hex_code = $comp_info->hex_code;
        $currency = $comp_info->currency;
        for ($i = 1; $i <= $diff; $i++) {
            $d = $date_from;
            $timestamp = strtotime($d);
            $day = date('D', $timestamp);

            $room_rate_plan_details = $masterhotelrateplan
                ->where(['rate_plan_id' => $rate_plan_id])
                ->where(['room_type_id' => $room_type_id])
                ->where('is_trash', 0)
                ->where('be_rate_status', 0)
                ->where('from_date', '<=', $d)
                ->where('to_date', '>=', $d)
                ->first();
            if (!isset($room_rate_plan_details->rate_plan_id)) //If Room rate plans Not with in date range then Latest created room rate plan is considered
            {
                $rate_plan_details = $masterhotelrateplan
                    ->where(['rate_plan_id' => $rate_plan_id])
                    ->where(['room_type_id' => $room_type_id])
                    ->where('is_trash', 0)
                    ->where('be_rate_status', 0)
                    ->orderBy('created_at', 'desc')
                    ->first();
            } else {
                $rate_plan_details = $room_rate_plan_details;
            }

            //when rate is not updated in rate plan log table it's consider the rate from room rate plan table that is wrong so change it to 0;

            $bar_price = 0;
            $bookingjini_price = 0;
            $extra_adult_price = 0;
            $extra_child_price = 0;
            $multiple_occupancy = $rate_plan_details['multiple_occupancy'];
            $before_days_offer = $rate_plan_details['before_days_offer'];
            $stay_duration_offer = $rate_plan_details['stay_duration_offer'];
            $lastminute_offer = $rate_plan_details['lastminute_offer'];
            
            $rate_plan_log_details = $rateplanlog
                ->select('bar_price', 'multiple_occupancy', 'multiple_days', 'block_status', 'extra_adult_price', 'extra_child_price')
                ->where(['room_type_id' => $room_type_id])
                ->where('rate_plan_id', '=', $rate_plan_id)
                ->where('from_date', '<=', $d)
                ->where('to_date', '>=', $d)
                ->orderBy('rate_plan_log_id', 'desc')
                ->get();


            if ($rate_plan_log_details->isEmpty()) {
                $array = array(
                    'bar_price' => $bar_price,
                    'multiple_occupancy' => json_decode($multiple_occupancy),
                    'bookingjini_price' => $bookingjini_price,
                    'extra_adult_price' => $extra_adult_price,
                    'extra_child_price' => $extra_child_price,
                    'before_days_offer' => $before_days_offer,
                    'stay_duration_offer' => $stay_duration_offer,
                    'lastminute_offer' => $lastminute_offer,
                    'rate_plan_id' => $rate_plan_id,
                    'room_type_id' => $room_type_id,
                    'date' => $date_from,
                    'day' => $day,
                    'hex_code' => $hex_code,
                    'block_status' => 0,
                    'currency' => $currency

                );
                array_push($filtered_rate, $array);
            } else {

                foreach ($rate_plan_log_details as $rate_plan_log_detail) {
                    $multiple_days = json_decode($rate_plan_log_detail->multiple_days);
                    $block_status     = $rate_plan_log_detail['block_status'];
                    if ($multiple_days != null) {
                        if ($multiple_days->$day == 0) {
                            continue;
                        } else {
                            $array = array(
                                'bar_price' => $rate_plan_log_detail['bar_price'],
                                'multiple_occupancy' => json_decode($rate_plan_log_detail['multiple_occupancy']),
                                'bookingjini_price' => $bookingjini_price,
                                'extra_adult_price' => $rate_plan_log_detail['extra_adult_price'],
                                'extra_child_price' => $rate_plan_log_detail['extra_child_price'],
                                'before_days_offer' => $before_days_offer,
                                'stay_duration_offer' => $stay_duration_offer,
                                'lastminute_offer' => $lastminute_offer,
                                'rate_plan_id' => $rate_plan_id,
                                'room_type_id' => $room_type_id,
                                'date' => $date_from,
                                'day' => $day,
                                'block_status' => $block_status,
                                'hex_code' => $hex_code,
                                'currency' => $currency
                            );
                            break;
                        }
                    } else {
                        $array = array(
                            'bar_price' => $rate_plan_log_detail['bar_price'],
                            'multiple_occupancy' => json_decode($rate_plan_log_detail['multiple_occupancy']),
                            'bookingjini_price' => $bookingjini_price,
                            'extra_adult_price' => $rate_plan_log_detail['extra_adult_price'],
                            'extra_child_price' => $rate_plan_log_detail['extra_child_price'],
                            'before_days_offer' => $before_days_offer,
                            'stay_duration_offer' => $stay_duration_offer,
                            'lastminute_offer' => $lastminute_offer,
                            'rate_plan_id' => $rate_plan_id,
                            'room_type_id' => $room_type_id,
                            'date' => $date_from,
                            'day' => $day,
                            'block_status' => $block_status,
                            'hex_code' => $hex_code,
                            'currency' => $currency
                        );
                    }
                }
                array_push($filtered_rate, $array);
            }
            $date_from = date('Y-m-d', strtotime($d . ' +1 day'));
        }
        return $filtered_rate;
    }

    public function paymentGatewayHotelDetails(){


        $payment_gateway_details = DB::table('payment_gateway_details')->join('hotels_table','hotels_table');

    }

    public function dayOutingPackageListTest(Request $request)
  {
    $data = $request->all();
    $hotel_id = $data['hotel_id'];
    $checkin = $data['checkin'];

    // if ($hotel_id == 2600) {
      $package_list = [];
      $package_details = DayBookingARI::where('hotel_id', $hotel_id)->where('day_outing_dates', $checkin)->groupBy('package_id')->get();
      if($package_details){
        foreach ($package_details as $package) {
          $day_outing_Package = DayPackage::where('hotel_id', $hotel_id)->where('id', $package->package_id)->where('is_trash','0')->first();
          if(isset($day_outing_Package)){

            $day_promotion_per = DayBookingPromotions::where('hotel_id', $hotel_id)
            // ->where('day_package_id', 90)
            ->where('day_package_id', $package->package_id)
            ->where('is_active', '1')
            ->where(function($query) use ($checkin) {
                $query->where('valid_from', '<=', $checkin)
                      ->where('valid_to', '>=', $checkin);
            })
            ->whereRaw('NOT FIND_IN_SET(?, blackout_dates)', [$checkin])
            ->max('discount_percentage');
        
           if ($day_promotion_per) {
            $price_after_discount = $day_outing_Package->price - ($day_outing_Package->price * ($day_promotion_per / 100));
           }
        //  dd($price_after_discount);

  
          $images_array = [];
          $pack_images = explode(',', $day_outing_Package->package_images);
          $images = ImageTable::whereIn('image_id', $pack_images)->get();
          foreach ($images as $image) {
            $images_array[] = 'https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/' . $image->image_name;
          }
  
          if ($day_outing_Package->max_guest > 2) {
            $seat_availability_color =  '#2A8D48';
          } else {
            $seat_availability_color =  '#ee392d';
          }
          if (!empty($day_outing_Package->inclusion)) {
            $inclusion = explode(',', $day_outing_Package->inclusion);
          } else {
            $inclusion = [];
          }
  
          if (!empty($day_outing_Package->exclusion)) {
            $exclusion = explode(',', $day_outing_Package->exclusion);
          } else {
            $exclusion = [];
          }
          if (isset($package->block_status) && $package->block_status == 0) {
            $max_guest = $package->no_of_guest;
          } else {
            $max_guest = 0;
          }
          
          $package_list[] = array(
            'package_id' => $day_outing_Package->id,
            'package_name' => $day_outing_Package->package_name,
            'from_date' => date('d M y', strtotime($day_outing_Package->from_date)),
            'to_date' => date('d M y', strtotime($day_outing_Package->to_date)),
            'start_time' => date('h:i A', strtotime('+5 hours 30 minutes', strtotime($day_outing_Package->start_time))),
            'end_time' => date('h:i A', strtotime('+5 hours 30 minutes', strtotime($day_outing_Package->end_time))),
            'package_images' => $images_array,
            'package_description' => $day_outing_Package->description,
            'max_guest' => $max_guest,
            'price' => (int)$day_outing_Package->price,
            'discount_price' => (int)isset($package->rate) ? $package->rate : 0,
            'coupon_percentage' => isset($day_promotion_per) ? $day_promotion_per : 0,
            'price_after_discount' => isset($price_after_discount) ? $price_after_discount : (int)$day_outing_Package->price,
            'inclusion' => $inclusion,
            'exclusion' => $exclusion,
            'seat_availability_color' => $seat_availability_color,
            'tax_percentage' => (int)$day_outing_Package->tax_percentage,
            'blackout_dates' => ($day_outing_Package->blackout_dates) ? json_decode($day_outing_Package->blackout_dates) : [],
            'special_price_dates' => ($day_outing_Package->special_price_dates) ? json_decode($day_outing_Package->special_price_dates) : [],
            'package_status' => ($day_outing_Package->is_trash == 0) ? 1 : 0 //if package is active the status is 1 else 0
          );
        }
        }
      }
     

      // if ($package_list) {
        $final_data = ['status' => 1, 'data' => $package_list];
      // } else {
      //   $final_data = ['status' => 0, 'msg' => 'No package available for the selected date'];
      // }
      return response()->json($final_data);
    
    // } else {

    //   $day_outing_Package = DayPackage::where('hotel_id', $hotel_id)
    //     ->where('from_date', '<=', $checkin)
    //     ->where('to_date', '>=', $checkin)
    //     ->where('is_trash', 0)
    //     ->orderby('id', 'desc')
    //     ->get();

    //   $package_list = [];
    //   if ($day_outing_Package) {
    //     foreach ($day_outing_Package as $package) {

    //       $pkg_rates = DayBookingARI::where('package_id', $package->id)->where('day_outing_dates', $checkin)->first();
    //       // if($hotel_id==2319){
    //       //   print_r($pkg_rates);
    //       // }

    //       $images_array = [];
    //       $pack_images = explode(',', $package->package_images);
    //       $images = ImageTable::whereIn('image_id', $pack_images)->get();
    //       foreach ($images as $image) {
    //         $images_array[] = 'https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/' . $image->image_name;
    //       }

    //       if ($package->max_guest > 2) {
    //         $seat_availability_color =  '#2A8D48';
    //       } else {
    //         $seat_availability_color =  '#ee392d';
    //       }
    //       if (!empty($package->inclusion)) {
    //         $inclusion = explode(',', $package->inclusion);
    //       } else {
    //         $inclusion = [];
    //       }

    //       if (!empty($package->exclusion)) {
    //         $exclusion = explode(',', $package->exclusion);
    //       } else {
    //         $exclusion = [];
    //       }
    //       if (isset($pkg_rates->block_status) && $pkg_rates->block_status == 0) {
    //         $max_guest = $pkg_rates->no_of_guest;
    //       } else {
    //         $max_guest = 0;
    //       }

    //       $package_list[] = array(
    //         'package_id' => $package->id,
    //         'package_name' => $package->package_name,
    //         'from_date' => date('d M y', strtotime($package->from_date)),
    //         'to_date' => date('d M y', strtotime($package->to_date)),
    //         'start_time' => date('h:i A', strtotime('+5 hours 30 minutes', strtotime($package->start_time))),
    //         'end_time' => date('h:i A', strtotime('+5 hours 30 minutes', strtotime($package->end_time))),
    //         'package_images' => $images_array,
    //         'package_description' => $package->description,
    //         'max_guest' => $max_guest,
    //         'price' => (int)$package->price,
    //         'discount_price' => (int)isset($pkg_rates->rate) ? $pkg_rates->rate : 0,
    //         'inclusion' => $inclusion,
    //         'exclusion' => $exclusion,
    //         'seat_availability_color' => $seat_availability_color,
    //         'tax_percentage' => (int)$package->tax_percentage,
    //         'blackout_dates' => ($package->blackout_dates) ? json_decode($package->blackout_dates) : [],
    //         'special_price_dates' => ($package->special_price_dates) ? json_decode($package->special_price_dates) : [],
    //         'package_status' => ($package->is_trash == 0) ? 1 : 0 //if package is active the status is 1 else 0
    //       );
    //     }
    //     // if($hotel_id==2319){
    //     // exit;
    //     // }
    //   }

    //   if ($day_outing_Package) {
    //     $final_data = ['status' => 1, 'data' => $package_list];
    //   } else {
    //     $final_data = ['status' => 0, 'data' => $package_list, 'msg' => 'No package available for the selected date'];
    //   }
    //   return response()->json($final_data);
    // }
  }



  public function bookingVoucherTest($invoice_id)
    {

        $dayOutingBookings =  DayBookings::leftjoin('kernel.user_table', 'user_table.user_id', '=', 'day_bookings.user_id')
            ->where('day_bookings.booking_id', $invoice_id)
            ->select('day_bookings.*', 'user_table.first_name', 'user_table.last_name', 'user_table.email_id', 'user_table.mobile', 'user_table.address')
            ->first();

        $dayBookingDetails = DayPackage::where('id', $dayOutingBookings->package_id)->first();

        $package_price = $dayBookingDetails->price;
        // $price_after_discount = $dayBookingDetails->discount_price;
        // $discount_price = $package_price - $price_after_discount;
        $tax_percentage = $dayBookingDetails->tax_percentage;

        $discount_price = $dayOutingBookings->discount_amount;
        $price_after_discount = $package_price - $discount_price;


        // if($invoice_id=='DB-131'){
        //     $expeted_arrival =  isset($dayOutingBookings->arrival_time)?$dayOutingBookings->arrival_time:'';
        //     dd($expeted_arrival);
        // }

        $guest_note = $dayOutingBookings->guest_note;
        $expeted_arrival =  isset($dayOutingBookings->arrival_time) ? $dayOutingBookings->arrival_time : '';
        // $package_time = date('h:i:s A', strtotime($dayBookingDetails->arrival_time));


        $hotel_details = HotelInformation::where('hotel_id', $dayOutingBookings->hotel_id)->first();

        $get_logo_info = CompanyDetails::select('logo')->where('company_id', $hotel_details->company_id)->first();
        $get_logo = ImageTable::select('image_name')->where('image_id', $get_logo_info->logo)->first();

        if (isset($get_logo->image_name)) {
            // $hotel_logo = 'https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/' . $get_logo->image_name;
            $hotel_logo = 'https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/uploads/1708752917938080.png';
        } else {
            $hotel_logo = 'https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/uploads/1708752917938080.png';
        }

        $booking_id = $dayOutingBookings->booking_id . date('dmy');
        $guest_name = $dayOutingBookings->first_name . ' ' . $dayOutingBookings->last_name;
        $mobile =  $dayOutingBookings->mobile;
        $email_id =  $dayOutingBookings->email_id;
        $address =  $dayOutingBookings->address;

        $booking_date = date('d M Y', strtotime($dayOutingBookings->booking_date));
        $outing_date = date('d M Y', strtotime($dayOutingBookings->outing_dates));
        $package_name = $dayOutingBookings->package_name;
        $hotel_name = $hotel_details->hotel_name;

        $hotel_address = $hotel_details->hotel_address;
        $hotel_mobile = $hotel_details->mobile;
        $hotel_email_id = $hotel_details->email_id;
        $hotel_terms_and_cond = $hotel_details->terms_and_cond;



        $no_of_guest = $dayOutingBookings->no_of_guest;
        $tax_amount = $dayOutingBookings->tax_amount;

        $total_guest_price_excluding_tax = $price_after_discount * $no_of_guest;
        $total_guest_price_including_tax = $total_guest_price_excluding_tax + $tax_amount;

        $paid_amount = $dayOutingBookings->paid_amount;
        $selling_price = $dayOutingBookings->selling_price;
        $pay_at_hotel = $total_guest_price_including_tax - $paid_amount;

        $gstin = $dayOutingBookings->gstin ; 
        // dd($gstin);

        // If the selling price is zero, set all related amounts to zero
        if($selling_price == 0){
            $total_guest_price_excluding_tax = 0;
            $tax_amount = 0;
            $tax_percentage = 0;
            $total_guest_price_including_tax = 0;
            $paid_amount = 0;
            $pay_at_hotel = 0;

        }



        $body = '<!DOCTYPE html>
        <html>
        <head>
            <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@700" />
            <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Urbanist:wght@600;700" />
            <title></title>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
            <meta http-equiv="X-UA-Compatible" content="IE=edge" />
            <meta name="x-apple-disable-message-reformatting" />
            <style></style>
        </head>
        
        <body>
            <div
                style="font-size: 0px; line-height: 1px; mso-line-height-rule: exactly; display: none; max-width: 0px; max-height: 0px; opacity: 0; overflow: hidden; mso-hide: all">
            </div>
            <center lang="und" dir="auto"
                style="width: 100%; table-layout: fixed; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%">
                <table cellpadding="0" cellspacing="0" border="0" role="presentation" bgcolor="white" width="1157"
                    style="background-color: white; width: 1157px; border-spacing: 0; font-family: Urbanist">
                    <tr>
                        <td valign="top" class="force-w100" width="100.00%"
                            style="padding-top: 36px; padding-bottom: 60px; width: 100%">
                            <table class="force-w100" cellpadding="0" cellspacing="0" border="0" role="presentation"
                                width="100.00%" style="width: 100%; border-spacing: 0">
                                <tr>
                                    <td align="center" style="padding-bottom: 15.5px">
                                        <p
                                            style="font-family: Inter; font-size: 30px; font-weight: 700; color: #3d3d3d; margin: 0; padding: 0; line-height: 36px; mso-line-height-rule: exactly">
                                            Booking Confirmation</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="center" style="padding-top: 15.5px; padding-bottom: 11.5px">
                                        <table cellpadding="0" cellspacing="0" border="0" role="presentation"
                                            style="border-spacing: 0; font-family: Proxima Nova">
                                            <tr>
                                                <td valign="middle" width="700" style="width: 700px">
                                                    <img src="' . $hotel_logo . '"
                                                        width="139" style="max-width: initial; width: 139px; display: block" />
                                                </td>
                                                <td align="right" valign="middle" width="377" style="width: 377px">
                                                    <table class="force-w100" cellpadding="0" cellspacing="0" border="0"
                                                        role="presentation" width="377" style="width: 377px; border-spacing: 0">
                                                        <tr>
                                                            <td align="right" style="padding-bottom: 8px">
                                                                <p
                                                                    style="color: #3e3e3e; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                                    <span
                                                                        style="font-family: Inter; font-size: 16px; font-weight: 400">Booking
                                                                        No.</span>
                                                                    <span
                                                                        style="font-family: Inter; font-size: 16px; font-weight: 700; color: #3e3e3e">' . $booking_id . '
                                                                    </span>
                                                                </p>
                                                            </td>
                                                        </tr>
        
                                                        <tr>
                                                            <td align="right" style="padding-bottom: 8px">
                                                                <p
                                                                    style="color: #3e3e3e; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                                    <span
                                                                        style="font-family: Inter; font-size: 16px; font-weight: 400">Payment
                                                                        Reference Number: </span>
                                                                    <span
                                                                        style="font-family: Inter; font-size: 16px; font-weight: 700; color: #3e3e3e">' . $booking_id . '
                                                                    </span>
                                                                </p>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="center" style="padding-top: 11.5px; padding-bottom: 11px">
                                        <div width="1077" style="width: 1077px; border-top: 2px solid #e7e7e7"></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="left" style="padding-top: 11px; padding-bottom: 6px; padding-left: 40px">
                                        <p
                                            style="font-size: 18px; font-weight: 600; color: #616161; margin: 0; padding: 0; line-height: 22px; mso-line-height-rule: exactly">
                                            Dear ' . $guest_name . ',</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="left" style="padding-top: 6px; padding-bottom: 11px; padding-left: 40px">
                                        <p width="1077" height="66"
                                            style="font-size: 18px; font-weight: 600; text-align: left; color: #616161; margin: 0; padding: 0; width: 1077px; height: 66px">
                                            <span>Thank you for choosing </span>
                                            <span
                                                style="font-size: 18px; font-weight: 700; color: #1C5EAA; text-align: left">' . $hotel_name . '</span>
                                            <span>, as your property of choice for your visit and booking through our hotels website. Your booking confirmation details have been provided below: </span>
                                            
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="left" style="padding-top: 11px; padding-bottom: 4px; padding-left: 40px">
                                        <p
                                            style="font-size: 20px; font-weight: 600; color: #616161; margin: 0; padding: 0; line-height: 24px; mso-line-height-rule: exactly">
                                            Property name</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="center" style="padding-top: 4px;">
                                        <table cellpadding="0" cellspacing="0" border="0" role="presentation"
                                            style="border-spacing: 0">
                                            <tr>
                                                <td valign="top" width="790" style="width: 790px">
                                                    <p
                                                        style="font-size: 34px; font-weight: 700; color: #1C5EAA; margin: 0; padding: 0; line-height: 41px; mso-line-height-rule: exactly">
                                                        <span>' . $hotel_name . ' </span>
                                                    </p>
                                                </td>
                                                <td align="right" valign="top" width="287"
                                                    style="padding-bottom: 4px; width: 287px">
                                                    <p style="font-family: Inter; font-size: 16px; font-weight: 400">
                                                        Booked on:
                                                        <span
                                                            style="font-family: Inter; font-size: 16px; font-weight: 700; color: #3e3e3e">' . $booking_date . '
                                                    </span>
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="left" style="padding-top: 12px; padding-bottom: 8px; padding-left: 40px">
                                        <table cellpadding="0" cellspacing="0" border="0" role="presentation"
                                            style="border-spacing: 0">
                                            <tr>
                                                <td width="533" style="padding-right: 7px; width: 533px">
                                                    <table cellpadding="0" cellspacing="0" border="0" role="presentation"
                                                        width="100.00%" height="261"
                                                        style="border-radius: 12px; border: 2px solid #c7c6c6; width: 100%; height: 261px; border-spacing: 0">
                                                        <tr>
                                                            <td valign="top" style="padding-top: 26px; padding-bottom: 22px;  padding-left: 22px;">
                                                                <table align="left" cellpadding="0" cellspacing="0" border="0"
                                                                    role="presentation" style="border-spacing: 0">
                                                                    <tr>
                                                                        <td align="center" valign="top" height="107"
                                                                            style="height: 107px">
                                                                            <table cellpadding="0" cellspacing="0" border="0"
                                                                                role="presentation" style="border-spacing: 0">
                                                                                <tr>
                                                                                    <td valign="middle">
                                                                                        <p
                                                                                        style="font-size: 18px; font-weight: 700; color: #1c5eaa; margin: 0; padding: 0; line-height: 22px; mso-line-height-rule: exactly">
                                                                                        Package Date</p>
        
                                                                                        <p
                                                                                            style="margin-top: 4px; margin-bottom: 0px;">
                                                                                            <span
                                                                                                style="font-family: Inter; font-size: 16px; font-weight: 700; color: #3e3e3e;">
                                                                                                ' . $outing_date . '
                                                                                            </span>
                                                                                        </p>
        
                                                                                       
                                                                                    </td>
                                                                                    
                                                                                    
                                                                                </tr>
                                                                            </table>
                                                                        </td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td align="center" style="padding-top: 16px">
                                                                            <table cellpadding="0" cellspacing="0" border="0"
                                                                                role="presentation"
                                                                                style="width: 100%; border-spacing: 0">
                                                                                <tbody>
                                                                                    <tr>
                                                                                        <td style="padding-bottom: 6px">
                                                                                            <p
                                                                                                style="font-size: 18px; font-weight: 700; color: #1c5eaa; margin: 0; padding: 0; line-height: 22px; mso-line-height-rule: exactly">
                                                                                                Package Name</p>
                                                                                        </td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <td valign="middle"
                                                                                            style="padding-bottom: 6px;">
                                                                                            <p
                                                                                                style="font-family: Inter; font-size: 16px; font-weight: 700; color: #3e3e3e; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                                                                ' . $package_name . '</p>
                                                                                        </td>

                                                                                    </tr>
                                                                                   
                                                                                </tbody>
                                                                            </table>
                                                                        </td>
                                                                    </tr> ';
                                                                    
                                                                    if($gstin != null){
                                                                     $body .= '<tr>
                                                                        <td align="center" style="padding-top: 16px">
                                                                            <table cellpadding="0" cellspacing="0" border="0"
                                                                                role="presentation"
                                                                                style="width: 100%; border-spacing: 0">
                                                                                <tbody>
                                                                                    <tr>
                                                                                        <td style="padding-bottom: 6px">
                                                                                            <p
                                                                                                style="font-size: 18px; font-weight: 700; color: #1c5eaa; margin: 0; padding: 0; line-height: 22px; mso-line-height-rule: exactly">
                                                                                                GST </p>
                                                                                        </td>
                                                                                    </tr>
                                                                                    <tr>
                                                                                        <td valign="middle"
                                                                                            style="padding-bottom: 6px;">
                                                                                            <p
                                                                                                style="font-family: Inter; font-size: 16px; font-weight: 700; color: #3e3e3e; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                                                                ' . $gstin . '</p>
                                                                                        </td>

                                                                                    </tr>
                                                                                   
                                                                                </tbody>
                                                                            </table>
                                                                        </td>
                                                                    </tr>';
                                                                      }
                                                                $body .= '</table>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                                <td width="533" style="padding-left: 8px; width: 533px">
                                                    <table cellpadding="0" cellspacing="0" border="0" role="presentation"
                                                        width="100.00%" height="261"
                                                        style="border-radius: 12px; border: 2px solid #c7c6c6; width: 100%; height: 261px; border-spacing: 0">
                                                        <tr>
                                                            <td align="left" valign="top"
                                                                style="padding-top: 26px; padding-bottom: 22px; padding-left: 24px">
                                                                <table cellpadding="0" cellspacing="0" border="0"
                                                                    role="presentation" style="border-spacing: 0">
                                                                    <tr>
                                                                        <td style="padding-bottom: 6px">
                                                                            <p
                                                                                style="font-size: 18px; font-weight: 700; color: #1c5eaa; margin: 0; padding: 0; line-height: 22px; mso-line-height-rule: exactly">
                                                                                Primary Guest Details</p>
                                                                        </td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td style="padding-top: 6px; padding-bottom: 4px">
                                                                            <p
                                                                                style="font-family: Inter; font-size: 16px; font-weight: 700; color: #3e3e3e; margin: 0; padding: 0; line-height: 24px; mso-line-height-rule: exactly">
                                                                                ' . $guest_name . '</p>
                                                                        </td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td align="left"
                                                                            style="padding-top: 4px; padding-bottom: 4.5px">
                                                                            <table cellpadding="0" cellspacing="0" border="0"
                                                                                role="presentation" style="border-spacing: 0">
                                                                                <tr>
                                                                                    <td valign="middle">
                                                                                        <img src="https://pripgoimages.s3.ap-south-1.amazonaws.com/icon/call_b.png"
                                                                                        
                                                                                            width="15.00" height="15.00"
                                                                                            style="width: 15px; height: 15px; display: block" />
                                                                                    </td>
                                                                                    <td valign="middle"
                                                                                        style="padding-left: 8px">
                                                                                        <p
                                                                                            style="font-family: Inter; font-size: 16px; font-weight: 400; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                                                            ' . $mobile . '</p>
                                                                                    </td>
                                                                                </tr>
                                                                            </table>
                                                                        </td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td align="left"
                                                                            style="padding-top: 4.5px; padding-bottom: 4.5px">
                                                                            <table cellpadding="0" cellspacing="0" border="0"
                                                                                role="presentation" style="border-spacing: 0">
                                                                                <tr>
                                                                                    <td valign="middle">
                                                                                        <img src="https://pripgoimages.s3.ap-south-1.amazonaws.com/icon/email_b.png"
                                                                                            width="15.00" height="15.00"
                                                                                            style="width: 15px; height: 15px; display: block" />
                                                                                    </td>
                                                                                    <td valign="middle"
                                                                                        style="padding-left: 8px">
                                                                                        <p
                                                                                            style="font-family: Inter; font-size: 16px; font-weight: 400; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                                                            ' . $email_id . '</p>
                                                                                    </td>
                                                                                </tr>
                                                                            </table>
                                                                        </td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td style="padding-top: 4.5px; padding-bottom: 6px">
                                                                            <table cellpadding="0" cellspacing="0" border="0"
                                                                                role="presentation" style="border-spacing: 0">
                                                                                <tr>
                                                                                    <td valign="middle">
                                                                                        <img src="https://pripgoimages.s3.ap-south-1.amazonaws.com/icon/location_b.png"
                                                                                            width="15.00" height="15.00"
                                                                                            style="width: 15px; height: 15px; display: block" />
                                                                                    </td>
                                                                                    <td valign="middle"
                                                                                        style="padding-left: 8px">
                                                                                        <p
                                                                                            style="font-family: Inter; font-size: 16px; font-weight: 400; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                                                            ' . $address . '</p>
                                                                                    </td>
                                                                                </tr>
                                                                            </table>
                                                                        </td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td style="padding-top: 10px; padding-bottom: 6px">
                                                                            <p
                                                                                style="font-size: 18px; font-weight: 700; color: #1c5eaa; margin: 0; padding: 0; line-height: 22px; mso-line-height-rule: exactly">
                                                                                Expected Arrival</p>
                                                                        </td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td style="padding-top: 2px">
                                                                            <p
                                                                                style="font-family: Inter; font-size: 16px; font-weight: 700; color: #3e3e3e; margin: 0; padding: 0; line-height: 22px; mso-line-height-rule: exactly">
                                                                                ' . $expeted_arrival . '</p>
                                                                        </td>
                                                                    </tr>
                                                                </table>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="left" valign="top" height="96"
                                        style="padding-top: 8px; padding-left: 40px; height: 96px">
                                        <table cellpadding="0" cellspacing="0" border="0" role="presentation" width="1077"
                                            height="67"
                                            style="border-radius: 12px; border: 2px solid #c7c6c6; width: 1077px; height: 67px; border-spacing: 0">
                                            <tr>
                                                <td align="left" valign="middle" style="padding-left: 24px">
                                                    <table cellpadding="0" cellspacing="0" border="0" role="presentation"
                                                        style="border-spacing: 0">
                                                        <tr>
                                                            <td>
                                                                <p
                                                                    style="font-size: 18px; font-weight: 700; color: #1c5eaa; margin: 0; padding: 0; line-height: 22px; mso-line-height-rule: exactly">
                                                                    Guest Note:</p>
                                                            </td>
                                                            <td style="padding-left: 6px">
                                                                <p
                                                                    style="font-family: Inter; font-size: 16px; font-weight: 700; color: #3e3e3e; margin: 0; padding: 0; line-height: 24px; mso-line-height-rule: exactly">
                                                                    ' . $guest_note . '</p>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="right" style="padding-top: 16px; padding-bottom: 12px; padding-right: 40px; padding-left: 589px;">
                                        <p
                                            style="font-family: Inter; font-size: 18px; font-weight: 700; text-align: left; color: #3e3e3e; margin: 0; padding: 0; line-height: 24px; mso-line-height-rule: exactly">
                                            Price Breakup</p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <td align="right" width="100.00%" style="padding-top: 10px; width: 100%">
                                        <table cellpadding="0" cellspacing="0" border="0" role="presentation" width="100.00%"
                                            style="width: 100%; border-spacing: 0">
                                            
                                            <tr>
                                                <td style="padding-left: 589px; padding-right: 36px">
                                                    <table cellpadding="0" cellspacing="0" border="0" role="presentation"
                                                        width="530" height="222"
                                                        style="border-radius: 12px; border: 2px solid #c7c6c6; width: 530px; height: 222px; border-spacing: 0">
                                                        <tr>
                                                            <td width="100.00%" style="width: 100%">
                                                                <table cellpadding="0" cellspacing="0" border="0"
                                                                    role="presentation" width="100.00%" height="129"
                                                                    style="border-bottom: 1px solid #c7c6c6; width: 100%; height: 129px; border-spacing: 0">
                                                                    <tr>
                                                                        <td align="left" valign="top"
                                                                            style="padding-top: 15px; padding-bottom: 12px; padding-left: 16px">
                                                                            <table cellpadding="0" cellspacing="0" border="0"
                                                                                role="presentation" style="border-spacing: 0">
                                                                                <tr>
                                                                                    <td style="padding-bottom: 3.5px">
                                                                                        <table cellpadding="0" cellspacing="0"
                                                                                            border="0" role="presentation"
                                                                                            style="border-spacing: 0">
                                                                                            <tr>
                                                                                                <td width="280"
                                                                                                    style="width: 280px">
                                                                                                    <p
                                                                                                        style="font-size: 14px; font-weight: 700; color: #616161; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                                                                        Date</p>
                                                                                                </td>
                                                                                                <td align="right" width="215"
                                                                                                    style="width: 215px">
                                                                                                    <p
                                                                                                        style="font-size: 14px; font-weight: 700; color: #313131; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                                                                        ' . $outing_date . '</p>
                                                                                                </td>
                                                                                            </tr>
                                                                                        </table>
                                                                                    </td>
                                                                                </tr>
                                                                                <tr>
                                                                                    <td
                                                                                        style="padding-top: 3.5px; padding-bottom: 5px">
                                                                                        <table cellpadding="0" cellspacing="0"
                                                                                            border="0" role="presentation"
                                                                                            style="border-spacing: 0">
                                                                                            <tr>
                                                                                                <td width="280"
                                                                                                    style="width: 280px">
                                                                                                    <p height="19"
                                                                                                        style="font-size: 14px; font-weight: 700; color: #616161; margin: 0; padding: 0; height: 19px; line-height: 19px; mso-line-height-rule: exactly">
                                                                                                       Package Price</p>
                                                                                                </td>
                                                                                                <td align="right" width="215"
                                                                                                    style="width: 215px">
                                                                                                    <p
                                                                                                        style="font-size: 14px; font-weight: 700; color: #313131; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                                                                        ₹' . $package_price . '</p>
                                                                                                </td>
                                                                                            </tr>
                                                                                        </table>
                                                                                    </td>
                                                                                </tr>
                                                                                <tr>
                                                                                    <td
                                                                                        style="padding-top: 5px; padding-bottom: 5px">
                                                                                        <table cellpadding="0" cellspacing="0"
                                                                                            border="0" role="presentation"
                                                                                            style="border-spacing: 0">
                                                                                            <tr>
                                                                                                <td width="280"
                                                                                                    style="width: 280px">
                                                                                                    <p
                                                                                                        style="font-size: 14px; font-weight: 700; color: #616161; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                                                                        Discount Price
                                                                                                    </p>
                                                                                                </td>
                                                                                                <td align="right" width="215"
                                                                                                    style="width:215px">
                                                                                                    <p
                                                                                                        style="font-size: 14px; font-weight: 700; color: #313131; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                                                                        ₹' . $discount_price . '</p>
                                                                                                </td>
                                                                                            </tr>
                                                                                        </table>
                                                                                    </td>
                                                                                </tr>
                                                                                <tr>
                                                                                    <td style="padding-top: 5px">
                                                                                        <table cellpadding="0" cellspacing="0"
                                                                                            border="0" role="presentation"
                                                                                            style="border-spacing: 0">
                                                                                            <tr>
                                                                                                <td width="280"
                                                                                                    style="width: 280px">
                                                                                                    <p
                                                                                                        style="font-size: 14px; font-weight: 700; color: #616161; margin: 0; padding: 0; line-height: 18px; mso-line-height-rule: exactly">
                                                                                                        Price after discount</p>
                                                                                                </td>
                                                                                                <td align="right" width="215"
                                                                                                    style="width: 215px">
                                                                                                    <p
                                                                                                        style="font-size: 14px; font-weight: 700; color: #313131; margin: 0; padding: 0; line-height: 18px; mso-line-height-rule: exactly">
                                                                                                        ₹' . $price_after_discount . '</p>
                                                                                                </td>
                                                                                            </tr>
                                                                                        </table>
                                                                                    </td>
                                                                                </tr>
                                                                                <tr>
                                                                                    <td style="padding-top: 5px">
                                                                                        <table cellpadding="0" cellspacing="0"
                                                                                            border="0" role="presentation"
                                                                                            style="border-spacing: 0">
                                                                                            <tr>
                                                                                                <td width="280"
                                                                                                    style="width: 280px">
                                                                                                    <p
                                                                                                        style="font-size: 14px; font-weight: 700; color: #616161; margin: 0; padding: 0; line-height: 18px; mso-line-height-rule: exactly">
                                                                                                        Selling Price</p>
                                                                                                </td>
                                                                                                <td align="right" width="215"
                                                                                                    style="width: 215px">
                                                                                                    <p
                                                                                                        style="font-size: 14px; font-weight: 700; color: #313131; margin: 0; padding: 0; line-height: 18px; mso-line-height-rule: exactly">
                                                                                                        ₹' . $selling_price . '</p>
                                                                                                </td>
                                                                                            </tr>
                                                                                        </table>
                                                                                    </td>
                                                                                </tr>
                                                                                <tr>
                                                                                <td style="padding-top: 5px">
                                                                                    <table cellpadding="0" cellspacing="0"
                                                                                        border="0" role="presentation"
                                                                                        style="border-spacing: 0">
                                                                                        <tr>
                                                                                            <td width="280"
                                                                                                style="width: 280px">
                                                                                                <p
                                                                                                    style="font-size: 14px; font-weight: 700; color: #616161; margin: 0; padding: 0; line-height: 18px; mso-line-height-rule: exactly">
                                                                                                    No of guest</p>
                                                                                            </td>
                                                                                            <td align="right" width="215"
                                                                                                style="width: 215px">
                                                                                                <p
                                                                                                    style="font-size: 14px; font-weight: 700; color: #313131; margin: 0; padding: 0; line-height: 18px; mso-line-height-rule: exactly">
                                                                                                    ' . $no_of_guest . '</p>
                                                                                            </td>
                                                                                        </tr>
                                                                                    </table>
                                                                                </td>
                                                                            </tr>
                                                                            <tr>
                                                                                <td style="padding-top: 5px">
                                                                                    <table cellpadding="0" cellspacing="0"
                                                                                        border="0" role="presentation"
                                                                                        style="border-spacing: 0">
                                                                                        <tr>
                                                                                            <td width="280"
                                                                                                style="width: 280px">
                                                                                                <p
                                                                                                    style="font-size: 14px; font-weight: 700; color: #616161; margin: 0; padding: 0; line-height: 18px; mso-line-height-rule: exactly">
                                                                                                    Amount Excl. Tax</p>
                                                                                            </td>
                                                                                            <td align="right" width="215"
                                                                                                style="width: 215px">
                                                                                                <p
                                                                                                    style="font-size: 14px; font-weight: 700; color: #313131; margin: 0; padding: 0; line-height: 18px; mso-line-height-rule: exactly">
                                                                                                    ₹' . $total_guest_price_excluding_tax . '</p>
                                                                                            </td>
                                                                                        </tr>
                                                                                    </table>
                                                                                </td>
                                                                             </tr>
                                                                             <tr>
                                                                                <td style="padding-top: 5px">
                                                                                    <table cellpadding="0" cellspacing="0"
                                                                                        border="0" role="presentation"
                                                                                        style="border-spacing: 0">
                                                                                        <tr>
                                                                                            <td width="280"
                                                                                                style="width: 280px">
                                                                                                <p
                                                                                                    style="font-size: 14px; font-weight: 700; color: #616161; margin: 0; padding: 0; line-height: 18px; mso-line-height-rule: exactly">
                                                                                                    Tax Price</p>
                                                                                            </td>
                                                                                            <td align="right" width="215"
                                                                                                style="width: 215px">
                                                                                                <p
                                                                                                    style="font-size: 14px; font-weight: 700; color: #313131; margin: 0; padding: 0; line-height: 18px; mso-line-height-rule: exactly">
                                                                                                    ₹' . $tax_amount . '(' . $tax_percentage . '%)</p>
                                                                                            </td>
                                                                                        </tr>
                                                                                    </table>
                                                                                </td>
                                                                             </tr>
                                                                            </table>
                                                                        </td>
                                                                    </tr>
                                                                </table>
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td width="100.00%" style="width: 100%">
                                                                <table cellpadding="0" cellspacing="0" border="0"
                                                                    role="presentation" width="100.00%" height="97"
                                                                    style="width: 100%; height: 97px; border-spacing: 0">
                                                                    <tr>
                                                                        <td align="left" valign="top"
                                                                            style="padding-top: 12px; padding-bottom: 14px; padding-left: 16px">
                                                                            <table cellpadding="0" cellspacing="0" border="0"
                                                                                role="presentation" style="border-spacing: 0">
                                                                                <tr>
                                                                                    <td style="padding-bottom: 3.5px">
                                                                                        <table cellpadding="0" cellspacing="0"
                                                                                            border="0" role="presentation"
                                                                                            style="border-spacing: 0">
                                                                                            <tr>
                                                                                                <td width="280"
                                                                                                    style="width: 280px">
                                                                                                    <p
                                                                                                        style="font-size: 14px; font-weight: 700; color: #616161; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                                                                        Total Amount Incl. tax
                                                                                                    </p>
                                                                                                </td>
                                                                                                <td align="right" width="215"
                                                                                                    style="width: 215px">
                                                                                                    <p
                                                                                                        style="font-size: 14px; font-weight: 700; color: #313131; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                                                                        ₹' . $total_guest_price_including_tax . '</p>
                                                                                                </td>
                                                                                            </tr>
                                                                                        </table>
                                                                                    </td>
                                                                                </tr>
                                                                                <tr>
                                                                                    <td
                                                                                        style="padding-top: 3.5px; padding-bottom: 4px">
                                                                                        <table cellpadding="0" cellspacing="0"
                                                                                            border="0" role="presentation"
                                                                                            style="border-spacing: 0">
                                                                                            <tr>
                                                                                                <td width="280"
                                                                                                    style="width: 280px">
                                                                                                    <p
                                                                                                        style="font-size: 14px; font-weight: 700; color: #616161; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                                                                        Total Paid Amount</p>
                                                                                                </td>
                                                                                                <td align="right" width="215"
                                                                                                    style="width: 215px">
                                                                                                    <p
                                                                                                        style="font-size: 14px; font-weight: 700; color: #313131; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                                                                        ₹' . $paid_amount . '</p>
                                                                                                </td>
                                                                                            </tr>
                                                                                        </table>
                                                                                    </td>
                                                                                </tr>
                                                                                <tr>
                                                                                    <td style="padding-top: 4px">
                                                                                        <table cellpadding="0" cellspacing="0"
                                                                                            border="0" role="presentation"
                                                                                            style="border-spacing: 0">
                                                                                            <tr>
                                                                                                <td width="280"
                                                                                                    style="width: 280px">
                                                                                                    <p
                                                                                                        style="font-size: 14px; font-weight: 700; color: #616161; margin: 0; padding: 0; line-height: 18px; mso-line-height-rule: exactly">
                                                                                                        Pay at hotel</p>
                                                                                                </td>
                                                                                                <td align="right" width="215"
                                                                                                    style="width: 215px">
                                                                                                    <p
                                                                                                        style="font-size: 14px; font-weight: 700; color: #313131; margin: 0; padding: 0; line-height: 18px; mso-line-height-rule: exactly">
                                                                                                        ₹' . $pay_at_hotel . '</p>
                                                                                                </td>
                                                                                            </tr>
                                                                                        </table>
                                                                                    </td>
                                                                                </tr>
                                                                            </table>
                                                                        </td>
                                                                    </tr>
                                                                </table>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="left" style="padding-bottom: 6.5px; padding-left: 40px">
                                        <p
                                            style="font-family: Inter; font-size: 16px; font-weight: 700; color: #3e3e3e; margin: 0; padding: 0; line-height: 22px; mso-line-height-rule: exactly">
                                            Regards</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="left" style="padding-top: 6.5px; padding-bottom: 5.5px; padding-left: 40px">
                                        <p
                                            style="font-family: Inter; font-size: 16px; font-weight: 700; color: #3e3e3e; margin: 0; padding: 0; line-height: 22px; mso-line-height-rule: exactly">
                                            Reservation Team</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="left" style="padding-top: 5.5px; padding-bottom: 7.5px; padding-left: 40px">
                                        <table cellpadding="0" cellspacing="0" border="0" role="presentation"
                                            style="border-spacing: 0">
                                            <tr>
                                                <td valign="middle">
                                                    <img src="https://pripgoimages.s3.ap-south-1.amazonaws.com/icon/location_bl.png"
                                                        width="15.00" height="16.00"
                                                        style="width: 15px; height: 16px; display: block" />
                                                </td>
                                                <td valign="middle" style="padding-left: 8px">
                                                    <p
                                                        style="font-size: 16px; font-weight: 600; color: #3e3e3e; margin: 0; padding: 0; line-height: 22px; mso-line-height-rule: exactly">
                                                        ' . $hotel_address . '</p>
                                                </td>
                                            </tr>
                                            
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="left" style="padding-top: 7.5px; padding-bottom: 4.5px; padding-left: 40px">
                                        <table cellpadding="0" cellspacing="0" border="0" role="presentation"
                                            style="border-spacing: 0">
                                            <tr>
                                                <td valign="middle">
                                                    <img src="https://pripgoimages.s3.ap-south-1.amazonaws.com/icon/call_bl.png"
                                                        width="15.00" height="15.00"
                                                        style="width: 15px; height: 15px; display: block" />
                                                </td>
                                                <td valign="middle" style="padding-left: 8px">
                                                    <p
                                                        style="font-size: 16px; font-weight: 600; color: #3e3e3e; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                        ' . $hotel_mobile . '</p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="left" valign="top" height="40"
                                        style="padding-top: 4.5px; padding-left: 40px; height: 40px">
                                        <table cellpadding="0" cellspacing="0" border="0" role="presentation"
                                            style="border-spacing: 0">
                                            <tr>
                                                <td valign="middle">
                                                    <img src="https://pripgoimages.s3.ap-south-1.amazonaws.com/icon/email_bl.png"
                                                        width="15.00" height="15.00"
                                                        style="width: 15px; height: 15px; display: block" />
                                                </td>
                                                <td valign="middle" style="padding-left: 8px">
                                                    <p
                                                        style="font-size: 16px; font-weight: 600; color: #3e3e3e; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                        <p
                                                        style="font-size: 16px; font-weight: 600; color: #3e3e3e; margin: 0; padding: 0; line-height: 19px; mso-line-height-rule: exactly">
                                                        ' . $hotel_email_id . '</p>
                                                        
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="left" style="padding-top: 16px; padding-bottom: 8.5px; padding-left: 35px; padding-right:35px">
                                        <p
                                            style="font-size: 16px; font-weight: 700; color: #3e3e3e; margin: 0; padding: 0; line-height: 22px; mso-line-height-rule: exactly">
                                            <span>' . $hotel_terms_and_cond . '</span>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="left" style="padding-top: 16px; padding-bottom: 8.5px; padding-left: 35px; padding-right:35px">
                                        <p
                                            style="font-size: 16px; font-weight: 700; color: #3e3e3e; margin: 0; padding: 0; line-height: 22px; mso-line-height-rule: exactly">
                                            <span>' . $hotel_details->cancel_policy . '</span>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="left" style="padding-top: 16px; padding-bottom: 8.5px; padding-left: 35px; padding-right:35px">
                                        <p
                                            style="font-size: 16px; font-weight: 700; color: #3e3e3e; margin: 0; padding: 0; line-height: 22px; mso-line-height-rule: exactly">
                                            <span>' . $hotel_details->hotel_policy . '</span>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                
                                                <td valign="middle" width="700" style="width: 700px">
                                                
                                                <span valign="middle" style="padding-left:50px;">Powered by</span>
                                                    <img src="' . $hotel_logo . '"
                                                        width="139" style="max-width: initial; width: 139px; display: block;padding-left: 35px;" />
                                                </td>
                                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </center>
        </body>
        
        </html>';
        return $body;
    }

}
