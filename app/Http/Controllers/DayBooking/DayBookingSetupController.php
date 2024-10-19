<?php

namespace App\Http\Controllers\DayBooking;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Validator;
use DB;
use App\DayPackage;
use App\DayBookingARI;
use App\DayBookingARILog;
use App\DayBookings;
use App\CompanyDetails;
use App\HotelInformation;
use App\ImageTable;
use App\DayBookingPromotions;
use App\SalesExecutive;

use App\Http\Controllers\Controller;


class DayBookingSetupController extends Controller
{

  public function menuSetup(Request $request, $hotel_id)
  {

    $data = $request->all();

    $hotel_details = HotelInformation::where('hotel_id',$hotel_id)->select('other_info')->first();
    //dd($hotel_details);
    if(isset($hotel_details->other_info)){
        $details = json_decode($hotel_details->other_info);
        if(!empty($details)){
          $details->package_menu_name = $data['package_menu_name'];
          $other_details = json_encode($details);
        }else{
          $details['package_menu_name'] = $data['package_menu_name'];
          $other_details = json_encode($details);
        }
       
    }else{
        $package_menu_name = $data['package_menu_name'];
        $other_details = json_encode($package_menu_name);
    }
   

    $update_menu_details = HotelInformation::where('hotel_id',$hotel_id)->update(['other_info'=>$other_details]);

    if ($update_menu_details) {
      $final_data = ["status" => 1, "message" => "Updated"];
    } else {
      $final_data = ['status' => 0, 'msg' => 'update Failed'];
    }
    return response()->json($final_data);

  }

  public function getMenuSetup($hotel_id)
  {

    $hotel_details = HotelInformation::where('hotel_id',$hotel_id)->select('other_info')->first();

    if(isset($hotel_details->other_info)){
      $details = json_decode($hotel_details->other_info);
      if(isset($details->package_menu_name)){
        $menu = $details->package_menu_name;
      }else{
        $menu = 'Day Booking';
      }
    }
        
    if ($menu) {
      $final_data = ["status" => 1, "data" =>$menu ];
    } else {
      $final_data = ['status' => 0, 'msg' => 'update Failed'];
    }
    return response()->json($final_data);
  }

  public function activePackages($hotel_id)
  {

    $day_outing_Package = DayPackage::where('hotel_id', $hotel_id)->where('is_trash', 0)->get();
    $active_package_array = [];
    if (sizeof($day_outing_Package) > 0) {
      foreach ($day_outing_Package as $package) {
        $blackout_dates = json_decode($package->blackout_dates);
        if(isset($package->blackout_dates)){
          $blackout_dates_count = count($blackout_dates);
        }else{
          $blackout_dates_count = 0;
        }
        $special_price_dates =  DB::table('day_booking_special_price')->where('package_id', $package->id)->count();
        $images_array = [];
        $pack_images = explode(',', $package->package_images);
        $images = ImageTable::whereIn('image_id', $pack_images)->get();
        foreach ($images as $image) {
          $images_array[] = 'https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/' . $image->image_name;
        }

        $active_package_array[] = array(
          'package_id' => $package->id,
          'package_name' => $package->package_name,
          'from_date' => date('d M y', strtotime($package->from_date)),
          'to_date' => date('d M y', strtotime($package->to_date)),
          'package_images' => $images_array,
          'max_guest' => (int)$package->max_guest,
          'blackout_dates' => $blackout_dates_count,
          'special_price_dates' => $special_price_dates,
          'package_status' => ($package->is_trash == 0) ? 1 : 0 //if package is active the status is 1 else 0
        );
      }
      if ($day_outing_Package) {
        $final_data = ['status' => 1, 'data' => $active_package_array];
      } else {
        $final_data = ['status' => 0, 'msg' => 'No package available.'];
      }
    } else {
      $final_data = ['status' => 0, 'msg' => 'No package available.'];
    }
    return response()->json($final_data);
  }

  public function inactivePackages($hotel_id)
  {
    $day_outing_Package = DayPackage::where('hotel_id', $hotel_id)->where('is_trash', 1)->get();
    $active_package_array = [];
    if (sizeof($day_outing_Package) > 0) {
      foreach ($day_outing_Package as $package) {
        $blackout_dates = json_decode($package->blackout_dates);
        if(isset($package->blackout_dates)){
          $blackout_dates_count = count($blackout_dates);
        }else{
          $blackout_dates_count = [];
        }
        $special_price_dates = DB::table('day_booking_special_price')->where('package_id', $package->id)->count();
        $images_array = [];
        $pack_images = explode(',', $package->package_images);
        $images =ImageTable::whereIn('image_id', $pack_images)->get();
        foreach ($images as $image) {
          $images_array[] = 'https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/' . $image->image_name;
        }

        $active_package_array[] = array(
          'package_id' => $package->id,
          'package_name' => $package->package_name,
          'from_date' => date('d M y', strtotime($package->from_date)),
          'to_date' => date('d M y', strtotime($package->to_date)),
          'package_images' => $images_array,
          'max_guest' => (int)$package->max_guest,
          'blackout_dates' => $blackout_dates_count,
          'special_price_dates' => $special_price_dates,
          'package_status' => ($package->is_trash == 0) ? 1 : 0 //if package is active the status is 1 else 0
        );
      }
      if ($active_package_array) {
        $final_data = ['status' => 1, 'data' => $active_package_array];
      } else {
        $final_data = ['status' => 0, 'msg' => 'No package available.'];
      }
    } else {
      $final_data = ['status' => 0, 'msg' => 'No package available.'];
    }

    return response()->json($final_data);
  }

  public function addDayBookingPackage(Request $request)
  {
    $data = $request->all();
    // Converting date formats
    $data['from_date'] = date('Y-m-d', strtotime($data['from_date']));
    $data['to_date'] = date('Y-m-d', strtotime($data['to_date']));

    // Converting arrays to strings
    $data['package_images'] = implode(',', $data['package_images']);
    $data['inclusion'] = implode(',', $data['inclusion']);
    $data['exclusion'] = implode(',', $data['exclusion']);

    // Inserting DayOuting record and retrieving its ID
    $dop = DayPackage::insertGetId($data);
    if ($dop) {
      $ari_data = [];
      $ari_log_data = [
        "hotel_id" => $data['hotel_id'],
        "package_id" => $dop,
        "from_date" => $data['from_date'],
        "to_date" => $data['to_date'],
        "no_of_guest" => $data['max_guest'],
        "rate" =>$data['price'],
      ];

      // Inserting DayOutingPackageARILog
      $dop_ari = DayBookingARILog::insert($ari_log_data);

      $toDate = date('Y-m-d', strtotime($data['to_date'] . ' +1 day'));

      // Generating date range
      $period = new \DatePeriod(
        new \DateTime($data['from_date']),
        new \DateInterval('P1D'),
        new \DateTime($toDate)
      );

      foreach ($period as $value) {
        $index = $value->format('Y-m-d');
        $ari_data[] = [
          "hotel_id" => $data['hotel_id'],
          "package_id" => $dop,
          "day_outing_dates" => $index,
          "no_of_guest" => $data['max_guest'],
          "rate" =>$data['price'],
        ];
      }

      // Inserting DayOutingPackageARI records
      $dop_ari_log = DayBookingARI::insert($ari_data);

      if ($dop_ari && $dop_ari_log) {
        $final_data = ['status' => 1, 'data' => $dop, 'msg' => 'Day Booking package details saved'];
      } else {
        $final_data = ['status' => 0, 'msg' => 'Save failed'];
      }
    } else {
      $final_data = ['status' => 0, 'msg' => 'Save failed'];
    }

    // Returning response in JSON format
    return response()->json($final_data);
  }

  public function updateDayBookingPackage(Request $request, $package_id)
  {
    $data = $request->all();
    // Retrieving existing day outing package
    $day_outing_Package = DayPackage::where('id', $package_id)->first();

    if (empty($data['package_images'])) {
      $package_images = $day_outing_Package->package_images;
    } else {
      $package_images = $day_outing_Package->package_images.','.implode(',', $data['package_images']);
    }

    $data['from_date'] = date('Y-m-d', strtotime($data['from_date']));
    $data['to_date'] = date('Y-m-d', strtotime($data['to_date']));
    $data['package_images'] = $package_images;
    $data['inclusion'] = implode(',', $data['inclusion']);
    $data['exclusion'] = implode(',', $data['exclusion']);


    $max_guest = $day_outing_Package['max_guest'];

    // Calculating additional guests
    $updated_max_guest = $data['max_guest'];
    // $additional_guest = $updated_max_guest - $max_guest;


    // Updating day outing package setup
    $update_day_pack = DayPackage::where('id', $package_id)->update($data);

    

    // Updating ARI (Availability, Rates, and Inventory) details
    // $current_ari_details = DayBookingARI::where('package_id', $package_id)->update(['block_status'=>1]);

    $toDate = date('Y-m-d', strtotime($data['to_date'] . ' +1 day'));


    $period = new \DatePeriod(
      new \DateTime($data['from_date']),
      new \DateInterval('P1D'),
      new \DateTime($toDate)
    );

    foreach ($period as $value) {
      $index = $value->format('Y-m-d');
      $ari_data = [
        "hotel_id" => $data['hotel_id'],
        "package_id" => $package_id,
        "day_outing_dates" => $index,
        "no_of_guest" => $data['max_guest'],
        "rate" =>$data['price'],
        // "block_status" =>0,
      ];

      $cur_inv = DayBookingARI::updateOrInsert(
        [
            'hotel_id' => $data['hotel_id'],
            'day_outing_dates' => $index,
            'package_id' => $package_id,
            
        ],
        $ari_data
    );
    }


    // if ($current_ari_details->isNotEmpty()) {
    //   foreach ($current_ari_details as $current_ari_detail) {
    //     $no_of_guest = $current_ari_detail->no_of_guest + $additional_guest;
    //     $rate = $data['discount_price'];
    //     DayBookingARI::where('id', $current_ari_detail->id)->update(['no_of_guest' => $no_of_guest, 'rate' => $rate]);
    //   }
    // }

    $ari_log_data = [
      "hotel_id" => $data['hotel_id'],
      "package_id" => $package_id,
      "from_date" => $data['from_date'],
      "to_date" => $data['to_date'],
      "no_of_guest" => $data['max_guest'],
      "rate" => $data['price'],
    ];
    $current_ari_log_details = DayBookingARILog::insert($ari_log_data);

    if ($current_ari_log_details) {
      $final_data = ['status' => 1, 'msg' => 'Day Booking package details Updated'];
    } else {
      $final_data = ['status' => 0, 'msg' => 'Save failed'];
    }
    return response()->json($final_data);
  }

  public function dayBookingPackageDetails($package_id)
  {
    $day_outing_Package = DayPackage::where('id', $package_id)
      ->orderby('id', 'desc')
      ->first();
    if ($day_outing_Package) {
      $images_array = [];
      $pack_images = explode(',', $day_outing_Package->package_images);
      $images = ImageTable::whereIn('image_id', $pack_images)->get();
      foreach ($images as $image) {
        $images_array[] = array(
        'image_id'=>$image->image_id,
        'image_name'=>'https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/' . $image->image_name);
      }
      if ($day_outing_Package->max_guest > 2) {
        $seat_availability_color =  '#47e90e';
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

      $special_price_details = DB::table('day_booking_special_price')->where('package_id', $day_outing_Package->id)->where('is_trash', 0)->get();
      $special_price_details_array = [];
      foreach ($special_price_details as $special_price) {
        $special_price_details_array[] = array(
          'special_price_id' => $special_price->id,
          'date' => date('d M y', strtotime($special_price->date)),
          'price' => $special_price->price,
          'discount_price' => $special_price->discount_price
        );
      }

      $blackout_dates_array = [];
      $blackout_dates = json_decode($day_outing_Package->blackout_dates);
      if(!empty($blackout_dates)){
        if (sizeof($blackout_dates) > 0) {
          foreach ($blackout_dates as $blackout_date) {
            $blackout_dates_array[] = date('d M y', strtotime($blackout_date));
          }
        }
      }
     

      $package_list = array(
        'package_id' => $day_outing_Package->id,
        'package_name' => $day_outing_Package->package_name,
        'from_date' => date('d M y', strtotime($day_outing_Package->from_date)),
        'to_date' => date('d M y', strtotime($day_outing_Package->to_date)),
        'start_time' => $day_outing_Package->start_time,
        'end_time' => $day_outing_Package->end_time,
        'package_images' => $images_array,
        'package_description' => $day_outing_Package->description,
        'max_guest' => (int)$day_outing_Package->max_guest,
        'price' => (int)$day_outing_Package->price,
        'discount_price' => (int)$day_outing_Package->discount_price,
        'inclusion' => $inclusion,
        'exclusion' => $exclusion,
        'seat_availability_color' => $seat_availability_color,
        'tax_percentage' => (int)$day_outing_Package->tax_percentage,
        'blackout_dates' => $blackout_dates_array,
        'special_price_dates' => $special_price_details_array,
        'package_status' => ($day_outing_Package->is_trash == 0) ? 1 : 0 //if package is active the status is 1 else 0
      );
    }

    if ($day_outing_Package) {
      $final_data = ['status' => 1, 'data' => $package_list];
    } else {
      $final_data = ['status' => 0, 'msg' => 'No package available for the selected date'];
    }
    return response()->json($final_data);
  }

  public function packageStatus($package_id, $status)
  {
    $status = ($status == 1) ? 0 : 1;

    $day_outing_Package = DayPackage::where('id', $package_id)->update(['is_trash' => $status]);

    $msg_status = ($status == 1) ? 'Deactivated' : 'Activated';

    if ($day_outing_Package) {
      $final_data = ['status' => 1, 'msg' => 'Package' . ' ' . $msg_status];
    } else {
      $final_data = ['status' => 0, 'msg' => 'failed'];
    }
    return response()->json($final_data);
  }

  public function updateSpecialPrice(Request $request, $package_id)
  {
    $special_price_details = $request->all();
    $special_price_array = [];

    foreach ($special_price_details as $special_price) {

      // $is_price = DB::table('day_booking_special_price')
      // ->where('package_id', $package_id)
      // ->where('date', $special_price['date'])
      // ->first();

      $available_guest_details = DayBookingARI::where('day_outing_dates', $special_price['date'])
        ->where('package_id', $package_id)
        ->first();

      // if (empty($is_price)) {
      $special_price_array[] = array(
        "hotel_id" => $available_guest_details['hotel_id'],
        'package_id' => $package_id,
        'date' => $special_price['date'],
        'price' => $special_price['price'],
        'discount_price' => $special_price['discount_price'],
      );
      // }

      $ari_log_data[] = [
        "hotel_id" => $available_guest_details['hotel_id'],
        "package_id" => $package_id,
        "from_date" => $special_price['date'],
        "to_date" => $special_price['date'],
        "no_of_guest" => $available_guest_details['no_of_guest'],
        "rate" => $special_price['discount_price'],
      ];

      $dop_ari = DayBookingARI::where('day_outing_dates', $special_price['date'])
        ->where('package_id', $package_id)
        ->update(["rate" => $special_price['discount_price']]);
    }

    $special_price_data = DB::table('day_booking_special_price')->insert($special_price_array);


    // Insert ARI log data
    $dop_ari_log = DayBookingARILog::insert($ari_log_data);

    if ($special_price_data && $dop_ari_log) {
      $final_data = ['status' => 1, 'msg' => 'Day Booking package details saved'];
    } else {
      $final_data = ['status' => 0, 'msg' => 'Save failed'];
    }

    return response()->json($final_data);
  }

  public function deleteSpecialPrice($id)
  {

    $special_price_data = DB::table('day_booking_special_price')->where('id', $id)->update(['is_trash' => 1]);

    if ($special_price_data) {
      $final_data = ['status' => 1, 'msg' => 'Special price deleted'];
    } else {
      $final_data = ['status' => 0, 'msg' => 'deleted failed'];
    }

    return response()->json($final_data);
  }

  public function deleteBlackoutDates(Request $request, $package_id){

      $data = $request->all();
      $date = date('Y-m-d',strtotime($data['blackout_dates']));
      $hotel_id = $data['hotel_id'];

      $black_out_dates = DayPackage::where('id', $package_id)->select('blackout_dates')->first();
      $blackout_dates = json_decode($black_out_dates->blackout_dates);

      $dates = array_diff($blackout_dates, [$date]);
      // $data = json_decode($dates, true);
      $resultArray = array_values($dates);

      $day_package_details = DayPackage::where('id', $package_id)->update(['blackout_dates' => json_encode($resultArray)]);

      $day_package_details = DayBookingARI::where('package_id', $package_id)
      ->where('day_outing_dates', $date)
      ->update(['block_status' => 0]);

      if ($day_package_details) {
        $final_data = ['status' => 1,'msg' => 'Black out dates Deleted'];
      } else {
        $final_data = ['status' => 0, 'msg' => 'Black out dates Delete failed'];
      }
      return response()->json($final_data);

  }


  public function updateBlackoutDates(Request $request, $package_id)
  {
      $data = $request->all();
      $dates = $data['blackout_dates'];
      $hotel_id = $data['hotel_id'];

      $black_out_dates = [];
      if(!empty($dates)){
        foreach($dates as $date){
          $black_out_dates[] = date('Y-m-d',strtotime($date));
        }
      }
  
      // Update block_status to 0 for existing records
      // DayBookingARI::where('package_id', $package_id)->update(['block_status' => 0]);
  
      // Fetch DayBookingARI records with blackout dates
      $day_booking_ari = DayBookingARI::whereIn('day_outing_dates', $black_out_dates)
          ->where('package_id', $package_id)
          ->get();
  
      $ari_log_data = [];
  
      // Prepare data for ARI log
      foreach ($day_booking_ari as $day_booking) {
          $ari_log_data[] = [
              "hotel_id" => $hotel_id,
              "package_id" => $package_id,
              "from_date" => date('Y-m-d',strtotime($day_booking->day_outing_dates)),
              "to_date" => date('Y-m-d',strtotime($day_booking->day_outing_dates)),
              "no_of_guest" => $day_booking->no_of_guest,
              "rate" => $day_booking->rate,
              "block_status" => 1,
          ];
      }
  
      // Update blackout_dates in DayPackage table
      $day_package_details = DayPackage::where('id', $package_id)
          ->update(['blackout_dates' => json_encode($black_out_dates)]);
  
      // Insert ARI log data in bulk
       DayBookingARILog::insert($ari_log_data);

      DayBookingARI::where('package_id', $package_id)
      ->whereIn('day_outing_dates', $data['blackout_dates'])
          ->update(['block_status' => 1]);

  
      if ($day_package_details) {
        $final_data = ['status' => 1,'msg' => 'Black out dates updated'];
      } else {
        $final_data = ['status' => 0, 'msg' => 'Black out dates update failed'];
      }
      return response()->json($final_data);
  }
  
  public function avabilityCalendar($package_id, $form_date, $to_date)
  {
    $ARI_details = DayBookingARI::where('package_id', $package_id)
      ->where('day_outing_dates', '>=', $form_date)
      ->where('day_outing_dates', '<=', $to_date)
      ->get();

    $avability_array = [];

    if (sizeof($ARI_details) > 0) {
      foreach ($ARI_details as $ARI_detail) {
        if($ARI_detail->block_status==1){
          $avability_array[$ARI_detail->day_outing_dates] = array('guest' => 0, 'price' => 0);

        }else{
          $avability_array[$ARI_detail->day_outing_dates] = array('guest' => $ARI_detail->no_of_guest, 'price' => $ARI_detail->rate);

        }
      }
    }

    // return $avability_array;
    $to_date = date('Y-m-d', strtotime($to_date . "+1 day"));

    $special_price_data = DB::table('day_booking_special_price')->where('package_id', $package_id)->where('is_trash', 0)->get();

    $avl_details = [];

    $period = new \DatePeriod(
      new \DateTime($form_date),
      new \DateInterval('P1D'),
      new \DateTime($to_date)
    );

    foreach ($period as $value) {
      $index = $value->format('Y-m-d');

      $is_package_available = 0;
      $back_ground_color = '#FFD9D9';
      $text_color = '#E64467';
      $currency_code = '20B9';

      if (isset($avability_array[$index])) {
        if ($avability_array[$index]['guest'] > 0) {
          $is_package_available = 1;
          $back_ground_color =  '#ffffff';
          $text_color = '#020601';
        }

        $avl_details[] = array('date' => $index, 'guest' => $avability_array[$index]['guest'], 'price' => $avability_array[$index]['price'], 'is_package_available' => $is_package_available, 'back_ground_color' => $back_ground_color, 'text_color' => $text_color, 'currency_code' => $currency_code);
      } else {
        $avl_details[] = array('date' => $index, 'guest' => 0, 'price' => 0, 'is_package_available' => $is_package_available, 'back_ground_color' => $back_ground_color, 'text_color' => $text_color, 'currency_code' => $currency_code);
      }
    }

    if ($avl_details) {
      $final_data = ['status' => 1, 'data' => $avl_details];
    } else {
      $final_data = ['status' => 0, 'msg' => 'No package available for the selected date'];
    }
    return response()->json($final_data);
  }

  public function bookingList(Request $request, $hotel_id)
  {
    $data = $request->all();
    $form_date = date('Y-m-d', strtotime($data['from_date']));
    $to_date = date('Y-m-d', strtotime($data['to_date'] . "+1 day"));

    $date_type = $data['date_type'];
    $booking_status = $data['booking_status'];

    if ($date_type == 1) {
      $date_type = 'day_bookings.booking_date';
    } else {
      $date_type = 'day_bookings.outing_dates';
    }

    if(isset($data['admin_id'])){
      $admin_id = $data['admin_id'];
    }else{
      $admin_id = 0;
    }

 
     $bookingList = DayBookings::where('day_bookings.hotel_id', $hotel_id)
      ->where($date_type, '>=', $form_date)
      ->where($date_type, '<=', $to_date)
      ->where('day_bookings.booking_status', '!=', 2);

    if ($booking_status == 'confirm') {
      $bookingList = $bookingList->where('day_bookings.booking_status', '=', 1);
    } elseif ($booking_status == 'cancelled') {
      $bookingList = $bookingList->where('day_bookings.booking_status', '=', 3);
    } else {
      $bookingList = $bookingList;
    }

    $sales_executive_details = SalesExecutive::where('admin_id',$admin_id)->first();

        if(isset($sales_executive_details)){
          $sales_executive_id = $sales_executive_details->id;
        }else{
          $sales_executive_id = 0;
        }

    if($sales_executive_id > 0){
      $bookingList = $bookingList->where('day_bookings.sales_executive_id',$sales_executive_id);
    }

    $bookingList = $bookingList->select('day_bookings.*')
    ->orderBy('day_bookings.id','Desc')
      ->get();


    $all_bookings = [];

    if (sizeof($bookingList) > 0) {
      foreach ($bookingList as $booking) {

        if($booking->booking_status == 1){
          $booking_status = 'Confirm';
        }elseif($booking->booking_status == 3){
          $booking_status = 'Cancelled';
        }else{
          $booking_status = 'Failed';
        }

        if($booking->booking_status == 1){
          $is_cancelable = 1;
          $is_modifiable = 1;
        }else{
          $is_cancelable = 0;
          $is_modifiable = 0;
        }

        $all_bookings[] = array(
          'booking_id' => $booking->booking_id . date('dmy', strtotime($booking->booking_date)),
          'invoice_id' => $booking->booking_id,
          'package_name' => $booking->package_name,
          'booking_date' => date('d M y', strtotime($booking->booking_date)),
          'no_of_guest' => $booking->no_of_guest,
          'guest_name' => $booking->guest_name,
          'outing_dates' => date('d M y', strtotime($booking->outing_dates)),
          'booking_status' => $booking_status,
          'payment_status' => ($booking->booking_status == 2) ? 'Unpaid'  : 'Paid',
          'text_color' => ($booking->booking_status == 2) ? '#E64467'  : '#2DB321',
          'business_booking' =>  isset($bookingList->gstin) ? 1 : 0,
          'is_cancelable' => $is_cancelable,
          'is_modifiable' => $is_modifiable,
          'booking_source' => $booking->booking_source,
        );
      }
    }

    if ($all_bookings) {
      $final_data = ['status' => 1, 'data' => $all_bookings];
    } else {
      $final_data = ['status' => 0, 'msg' => 'No bookings available'];
    }
    return response()->json($final_data);
  }

  public function bookingDetails($booking_id)
  {
    // $bookingList = DayBookings::leftjoin('kernel.user_table', 'user_table.user_id', '=', 'day_bookings.user_id')
    $bookingList = DayBookings::leftjoin('day_package','day_package.id','=','day_bookings.package_id')
      ->where('day_bookings.booking_id', $booking_id)
      // ->select('day_bookings.*','day_package.package_images','user_table.first_name', 'user_table.last_name', 'user_table.email_id', 'user_table.mobile')
      ->select('day_bookings.*','day_package.package_images')
      ->first();

    $images_array = [];

    if (isset($bookingList->package_images)) {
      $pack_images = explode(',', $bookingList->package_images);
      $images = ImageTable::whereIn('image_id', $pack_images)->get();
      
      foreach ($images as $image) {
        $images_array[] = 'https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/' . $image->image_name;
      }
    }
    


    $all_bookings = [];

    if ($bookingList) {
      $all_bookings = array(
        'booking_id' => $bookingList->booking_id,
        'package_name' => $bookingList->package_name,
        'booking_date' => date('d M Y',strtotime($bookingList->booking_date)),
        'no_of_guest' => $bookingList->no_of_guest,
        'outing_dates' => date('d M Y',strtotime($bookingList->outing_dates)),
        'paid_amount' => $bookingList->paid_amount,
        'total_amount' => $bookingList->total_amount,
        'discount_amount' => $bookingList->discount_amount,
        'tax_amount' => $bookingList->tax_amount,
        'booking_status' => ($bookingList->booking_status == 2) ? 'Failed'  : 'Confirm',
        'payment_status' => ($bookingList->booking_status == 2) ? 'Unpaid'  : 'Paid',
        'guest_note' => $bookingList->guest_note,
        'guest_name' => $bookingList->guest_name,
        'mobile' => $bookingList->guest_phone,
        'email' => $bookingList->guest_email,
        'package_image' => isset($images_array[0]) ? $images_array[0] : '',
        'company_name' => $bookingList->company_name,
        'gstin' =>  $bookingList->gstin,
        'business_booking' =>  isset($bookingList->gstin) ? 1 : 0,
        'currency_code' => '20B9',
        'tax_type' => 'GST',
        'total_amount_excluding_gst'=>$bookingList->total_amount - $bookingList->tax_amount,
        'total_amount_including_gst'=>$bookingList->total_amount,
      );
    }

    if ($all_bookings) {
      $final_data = ['status' => 1, 'data' => $all_bookings];
    } else {
      $final_data = ['status' => 0, 'msg' => 'Data No available'];
    }
    return response()->json($final_data);
  }

  public function dayOutingPackageList(Request $request)
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
            
          // if($hotel_id == 2319)
          // {

            $day_promotion_per = 0;
            $discounted_price = 0;
            $price_after_discount = $day_outing_Package->price;

              $day_promotions = DayBookingPromotions::where('hotel_id', $hotel_id)
            // ->where('day_package_id', $package->package_id)
            ->where('is_active', '1')
            ->where(function($query) use ($checkin) {
                $query->where('valid_from', '<=', $checkin)
                      ->where('valid_to', '>=', $checkin);
            })
            ->whereRaw('NOT FIND_IN_SET(?, blackout_dates)', [$checkin])
            // ->max('discount_percentage')
            ->get();
           
            $max_discount = 0;

            foreach ($day_promotions as $promotion) {
              $day_package_ids = explode(',', $promotion->day_package_id);
              if (in_array($package->package_id, $day_package_ids)) {
                  $max_discount = max($max_discount, $promotion->discount_percentage);
              }
          }
          
          $day_promotion_per = $max_discount;
        
           if ($day_promotion_per) {
            $price_after_discount = $day_outing_Package->price - ($day_outing_Package->price * ($day_promotion_per / 100));
            $discounted_price = $day_outing_Package->price * $day_promotion_per / 100;

           }
          // }

    
  
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
            'discounted_price' => isset($discounted_price) ? $discounted_price : 0,
            'price_after_discount' => isset($price_after_discount) ? (int)$price_after_discount : (int)$package->rate,
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

  public function dayBookingsHotelListByCompany($company_id)
  {
    $hotels = HotelInformation::where('company_id', $company_id)->where('status', 1)->select('*')->get();

    if ($hotels) {
      $hotels = $hotels->toArray();

      foreach ($hotels as $key => $hotel) {

        $day_outing_Package = DayPackage::where('hotel_id', $hotel['hotel_id'])->first();

        if ($day_outing_Package) {
          continue;
        } else {
          unset($hotels[$key]);
        }
      }

      $hotel_list = array_values($hotels);

      if ($hotel_list) {
        $final_data = ['status' => 1, 'data' => $hotel_list];
      } else {
        $final_data = ['status' => 0, 'msg' => 'No package available for the selected date'];
      }
      return response()->json($final_data);
    }
  }



  public function dayOutingPackageListCrs(Request $request)
  {
   
    $data = $request->all();
    $hotel_id = $data['hotel_id'];
    $checkin = $data['checkin'];


    $day_outing_Package = DayBookingARI::where('hotel_id', $hotel_id)->where('day_outing_dates', $checkin)->groupBy('package_id')->get();
  
    $package_list = [];
    if (!$day_outing_Package->isEmpty()) {

      if($day_outing_Package){
        foreach ($day_outing_Package as $package) {
          $day_outing_Package = DayPackage::where('hotel_id', $hotel_id)->where('id', $package->package_id)->where('is_trash','0')->first();
          if(isset($day_outing_Package)){
  
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
         
          
          $package_list[] = array(
            'package_id' => $day_outing_Package->id,
            'package_name' => $day_outing_Package->package_name,
            'from_date' => date('d M y', strtotime($day_outing_Package->from_date)),
            'to_date' => date('d M y', strtotime($day_outing_Package->to_date)),
            'start_time' => date('h:i A', strtotime('+5 hours 30 minutes', strtotime($day_outing_Package->start_time))),
            'end_time' => date('h:i A', strtotime('+5 hours 30 minutes', strtotime($day_outing_Package->end_time))),
            'package_images' => $images_array,
            'package_description' => $day_outing_Package->description,
            'max_guest' => $package->no_of_guest,
            'price' => (int)$day_outing_Package->price,
            'discount_price' => (int)isset($package->rate) ? $package->rate : 0,
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
     

      $final_data = ['status' => 1, 'data' => $package_list];
    }
    else{
      $pkg_rates = DayBookingARI::
      join('booking_engine.day_package', 'day_booking_ari.package_id', '=', 'day_package.id')
     ->where('day_booking_ari.hotel_id', $hotel_id)
     ->where('day_outing_dates', '>', $checkin)
     ->where('day_outing_dates', '<=', date('Y-m-d', strtotime($checkin . ' + 7 days')))
     ->orderBy('day_outing_dates','asc')
     ->get();
    
    
    $grouped_packages = [];
    $grouped_array = [];
    foreach ($pkg_rates as $pkg_rate) {
      $date = date('d M y', strtotime($pkg_rate->day_outing_dates));
      if (!isset($grouped_packages[$date])) {
      $grouped_packages[$date] = [];
      }
      if ($pkg_rate->no_of_guest > 2) {
          $seat_availability_color =  '#2A8D48';
      } else {
          $seat_availability_color =  '#ee392d';
      }
      
      $grouped_packages[$date][] = array(
          'package_id' => $pkg_rate->id,
          'package_name' => $pkg_rate->package_name,
          'dates' => $pkg_rate->day_outing_dates,
          'day_outing_dates' => date('d M y',strtotime($pkg_rate->day_outing_dates)),
          'max_guest' => $pkg_rate->no_of_guest,
          'seat_availability_color' => $seat_availability_color,
      );
      }

      ksort($grouped_packages);
      $alternate_dates = array_values($grouped_packages);


      $final_data = ['status' => 1, 'msg' => 'No package available for the selected date', 'alternate_dates' => $alternate_dates];
    }


    return response()->json($final_data);
  }

  
}
