<?php

namespace App\Http\Controllers\Extranetv4;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Validator;
use App\UserCredential;
use App\ManageUserTable; //class name from model
use App\CmOtaBookingRead; //class name from model
use App\CmOtaRoomTypeSynchronize;
use App\CmOtaRatePlanSynchronize;
use App\Invoice;
use App\Subscription;
use App\Apps;
use DB;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use App\CmOtaDetails;
use App\HotelInformation;
use App\Http\Controllers\Controller;
use App\ImageTable;
use App\HotelBooking;
use App\Model\Commonmodel;
use Exception;

class ListViewBookingsController extends Controller
{


  public function sourceList($hotel_id)
  {
    $failure_message = 'Source Fetch Failed';

    $source_list = [];
    $cm_sources = CmOtaDetails::where('hotel_id', $hotel_id)->select('ota_id', 'ota_name')->get();

    //return $cm_sources;
    if (sizeof($cm_sources) > 0) {
      foreach ($cm_sources as $cm_source) {
        array_push($source_list, array('ota_id' => $cm_source['ota_id'], 'ota_name' => $cm_source['ota_name']));
      }
    }

    $payload = [
      "hotel_id" => $hotel_id,
      "user_id" => 0,
    ];
    
    $payload = json_encode($payload);

    $curl = curl_init();
    curl_setopt_array($curl, array(
      CURLOPT_URL => 'https://subscription.bookingjini.com/api/my-subscription',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS => $payload,
      CURLOPT_HTTPHEADER => array(
        'Content-Type: application/json',
        'X_Auth_Subscription-API-KEY: Vz9P1vfwj6P6hiTCc9ddC5bNieqA5ScT'
      ),
    ));
    
    $response = curl_exec($curl);
    curl_close($curl);

    $be_sources = json_decode($response);

    // return $response;

    // $be_sources =  Subscription::join('kernel.plans', 'kernel.subscriptions.plan_id', '=', 'plans.plan_id')
    //   ->select('kernel.subscriptions.id', 'subscriptions.hotel_id', 'subscriptions.plan_id', 'plans.*')
    //   ->where('hotel_id', $hotel_id)->first();

    // $app_name = [];
    // if (!empty($be_sources)) {
    //   $plan_app = $be_sources->apps;
    //   return $plan_app;
    //   $app_details = explode(',', $plan_app);
    //   foreach ($app_details as $app_detail) {
    //     $apps = Apps::where('id', $app_detail)->first();
    //     array_push($app_name, $apps->app_code);
    //   }
    // }

    $plan_app = $be_sources->apps;

    if ($plan_app) {
      // $be_sources = json_decode($be_sources['product_name'], true);
      foreach ($plan_app as $be_source) {

        if ($be_source->app_code == 'JINI BOOK') {
          array_push($source_list, array('ota_id' => -1, 'ota_name' => 'Booking Engine'));
          array_push($source_list, array('ota_id' => -4, 'ota_name' => 'GOOGLE'));
          array_push($source_list, array('ota_id' => -6, 'ota_name' => 'PAYMENT LINK'));
        } elseif ($be_source == 'JINI HUB') {
          array_push($source_list, array('ota_id' => -2, 'ota_name' => 'CRS'));
        } elseif ($be_source == 'JINI HOST') {
          array_push($source_list, array('ota_id' => -3, 'ota_name' => 'GEMS'));
        } elseif ($be_source == 'JINI TALK') {
          array_push($source_list, array('ota_id' => -5, 'ota_name' => 'CHATBOT'));
        }
      }
    }

    if ((sizeof($cm_sources) > 0) || isset($be_sources)) {

      $result = array('status' => 1, "message" => 'Source Fetch Successfully', 'data' => $source_list);
      return response()->json($result);
    } else {
      $result = array('status' => 0, "message" => $failure_message);
      return response()->json($result);
    }
  }

  //================================================================================================================================

  public function paginate($items, $page_limit, $page = null, $options = [])
  {
    $page = $page ?: (Paginator::resolveCurrentPage() ?: 1);
    $items = $items instanceof Collection ? $items : Collection::make($items);
    return new LengthAwarePaginator($items->forPage($page, $page_limit), $items->count(), $page_limit, $page, $options);
  }

  //================================================================================================================================

  public  function dashboardBookingDetails($hotel_id)
  {

    $current_date = date('Y-m-d');
    //get channel logo
    $ota_logo = $this->channelLogo($hotel_id);

    if (isset($sources)) {
      foreach ($sources as $source) {
        if ($source > 0) {
          array_push($ota_ids, $source);
        } else {
          if ($source == '-2') {
            $booking_source = 'CRS';
          } elseif ($source == '-3') {
            $booking_source = 'GEMS';
          } elseif ($source == '-4') {
            $booking_source = 'google';
          } elseif ($source == '-6') {
            $booking_source = 'QUICKPAYMENT';
          }else {
            $booking_source = 'website';
          }
          array_push($be_ids, $booking_source);
        }
      }
    }

    $be_data = [];
    $cm_data = [];
    $all_booking_data = [];

    $be_data = Invoice::join('kernel.user_table', 'invoice_table.user_id', '=', 'user_table.user_id')
      ->join('kernel.hotels_table', 'invoice_table.hotel_id', '=', 'hotels_table.hotel_id')
      ->join('kernel.company_table', 'hotels_table.company_id', '=', 'company_table.company_id')
      ->select(
        'user_table.first_name',
        'user_table.last_name',
        'user_table.email_id',
        'user_table.mobile',
        'invoice_table.invoice_id',
        'invoice_table.booking_date',
        'invoice_table.check_in_out',
        'invoice_table.booking_source',
        'invoice_table.total_amount',
        'company_table.logo'
      )->where('invoice_table.hotel_id', '=', $hotel_id)
      ->where('invoice_table.booking_status', '=', 1)
      ->orderBy('invoice_table.invoice_id', 'DESC')
      ->take(10)
      ->get();

    if (sizeof($be_data) > 0) {
      foreach ($be_data as $be) {

        $check_in_out = $be->check_in_out;
        $remove_brakets = substr($check_in_out, 1);
        $remove_brakets1 = substr($remove_brakets, 0, -1);

        $explode_check_in_out = explode('-', $remove_brakets1);

        $check_in = $explode_check_in_out[0] . '-' . $explode_check_in_out[1] . '-' . $explode_check_in_out[2];
        $check_out = $explode_check_in_out[3] . '-' . $explode_check_in_out[4] . '-' . $explode_check_in_out[5];

        $date1 = date_create($check_in);
        $date2 = date_create($check_out);
        $diff = date_diff($date1, $date2);
        $no_of_nights = $diff->format("%a");
        if ($no_of_nights == 0) {
          $no_of_nights = 1;
        }

        $last_name = $be->last_name;
        if($last_name == 'undefined'){
          $last_name = '';
        }

        $booking_date =  date_create(date('Y-m-d', strtotime($be->booking_date)));
        $current_date = date_create(date('Y-m-d'));
        $booking_date_diff = date_diff($booking_date, $current_date);
        $booking_date_diff = $booking_date_diff->format("%a");
        if ($booking_date_diff == 0) {
          $booking_details['booking_days'] = 'Today';
        } else if ($booking_date_diff == 1) {
          $booking_details['booking_days'] = 'Yesterday';
        } else {
          $booking_details['booking_days'] = $booking_date_diff . ' days ago.';
        }
        $booking_details['customer_name'] = $be->first_name . ' ' . $last_name;
        $booking_details['customer_email'] = $be->email_id;
        $booking_details['customer_phone'] = isset($be->mobile) ? $be->mobile : 'NA';
        $booking_details['checkin_at'] = $check_in;
        $booking_details['checkout_at'] = $check_out;
        $booking_details['booking_date'] = $be->booking_date;
        $booking_details['paid_amount'] = $be->total_amount;
        $booking_details['display_booking_date'] = date('d M Y', strtotime($be->booking_date));
        $channel_name = $be->booking_source;

        $fetchHotelLogo = ImageTable::where('image_id', $be->logo)->select('image_name')->first();
        if (isset($fetchHotelLogo->image_name)) {
          $booking_details['logo'] = "https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/" . $fetchHotelLogo->image_name;
        } else {
          $booking_details['logo'] = "https://d3ki85qs1zca4t.cloudfront.net/logo/ota/" . $ota_logo[$channel_name];
        }

        $booking_details['check_in_out_at'] = date("d", strtotime($check_in)) . "-" . date("d M Y", strtotime($check_out));
        array_push($all_booking_data, $booking_details);
      }
    }

    $cm_data =  CmOtaBookingRead::select(DB::raw('distinct hotel_id,customer_details,booking_status,channel_name,payment_status,checkin_at,checkout_at,booking_date,confirm_status,cancel_status,amount'))
      ->where('hotel_id', '=', $hotel_id)
      ->orderBy('id', 'DESC')
      ->where('confirm_status', '=', 1)
      ->where('cancel_status', '=', 0)
      ->take(10)
      ->get();


    if (sizeof($cm_data) > 0) {
      foreach ($cm_data as $cm) {

        $date1 = date_create($cm->checkout_at);
        $date2 = date_create($cm->checkin_at);
        $diff = date_diff($date1, $date2);
        $no_of_nights = $diff->format("%a");
        if ($no_of_nights == 0) {
          $no_of_nights = 1;
        }

        $customer_details = explode(',', $cm->customer_details);

        if (isset($customer_details[0])) {
          $booking_details['customer_name'] = $customer_details[0];
        } else {
          $booking_details['customer_name'] = 'NA';
        }

        if (isset($customer_details[1])) {
          $booking_details['customer_email'] = $customer_details[1];
        } else {
          $booking_details['customer_email'] = 'NA';
        }

        if (isset($customer_details[2])) {
          $booking_details['customer_phone'] = $customer_details[2];
        } else {
          $booking_details['customer_phone'] = 'NA';
        }

        $booking_date =  date_create(date('Y-m-d', strtotime($cm->booking_date)));
        $current_date = date_create(date('Y-m-d  h:i:s'));
        $booking_date_diff = date_diff($booking_date, $current_date);
        $booking_date_diff = $booking_date_diff->format("%a");
        if ($booking_date_diff == 0) {
          $booking_details['booking_days'] = 'Today';
        } else if ($booking_date_diff == 1) {
          $booking_details['booking_days'] = 'Yesterday';
        } else {
          $booking_details['booking_days'] = $booking_date_diff . ' days ago.';
        }

        $booking_details['booking_status'] = $cm->booking_status;
        $booking_details['channel_name'] = $cm->channel_name;
        $booking_details['checkin_at'] = $cm->checkin_at;
        $booking_details['checkout_at'] = $cm->checkout_at;
        $booking_details['booking_date'] = $cm->booking_date;
        $booking_details['paid_amount'] = $cm->amount;
        $booking_details['display_booking_date'] = date('d M Y', strtotime($cm->booking_date));
        $booking_details['confirm_status'] = $cm->confirm_status;
        $booking_details['cancel_status'] = $cm->cancel_status;
        $channel_name = $cm->channel_name;
        $booking_details['logo'] = "https://d3ki85qs1zca4t.cloudfront.net/logo/ota/" . $ota_logo[$channel_name];
        $booking_details['check_in_out_at'] = date("d", strtotime($cm->checkin_at)) . "-" . date("d M Y", strtotime($cm->checkout_at));
        array_push($all_booking_data, $booking_details);
      }
    }

    usort($all_booking_data, array($this, 'cmp'));
    $recent_bookings = array_slice($all_booking_data, 0, 5);

    if ($recent_bookings) {
      $result = array('status' => 1, "message" => 'Bookings Fetched', 'data' => $recent_bookings);
      return response()->json($result);
    } else {
      $result = array('status' => 0, "message" => 'No recent bookings found!');
      return response()->json($result);
    }
  }

  //================================================================================================================================

  public function ListviewBookingsReportDownload(Request $request)
  {
    $data = $request->all();
    $ota_ids = [];
    $be_ids = [];

    if (isset($data['from_date'])) {
      $from_date = date('Y-m-d', strtotime($data['from_date']));
    } else {
      $from_date = date('Y-m-d');
    }
    if (isset($data['to_date'])) {
      $to_date = date('Y-m-d', strtotime($data['to_date']));
    } else {
      $to_date = date('Y-m-d');
    }

    $booking_status =  $data['booking_status'];
    $hotel_id = $data['hotel_id'];
    $sources = $data['source'];
    if (isset($data['date_type'])) {
      $date_type = $data['date_type'];
    }
    if ($date_type == 1) {
      $check_date_type = 'booking_date';
    } elseif ($date_type == 3) {
      $check_date_type = 'checkout_at';
    } else {
      $check_date_type = 'checkin_at';
    }

    if (isset($sources)) {
      foreach ($sources as $source) {
        if ($source > 0) {
          array_push($ota_ids, $source);
        } else {
          if ($source == '-2') {
            $booking_source = 'CRS';
          } elseif ($source == '-3') {
            $booking_source = 'GEMS';
          } elseif ($source == '-4') {
            $booking_source = 'google';
          } elseif ($source == '-6') {
            $booking_source = 'QUICKPAYMENT';
          }else {
            $booking_source = 'WEBSITE';
          }
          array_push($be_ids, $booking_source);
        }
      }
    }

    $all_booking_data = [];

    if (sizeof($ota_ids) > 0) {
      $cm_data =  CmOtaBookingRead::select(DB::raw('distinct hotel_id,unique_id,customer_details,booking_status,channel_name,payment_status,checkin_at,checkout_at,booking_date,confirm_status,cancel_status'))
        ->where('hotel_id', '=', $hotel_id)
        ->whereBetween(DB::raw('date(' . $check_date_type . ')'), array($from_date, $to_date));
      $cm_data = $cm_data->whereIn('ota_id', $ota_ids);

      if ($booking_status == 'confirm') {
        $cm_data = $cm_data->where('confirm_status', '=', 1);
        $cm_data = $cm_data->where('cancel_status', '=', 0);
      } elseif ($booking_status == 'cancelled') {
        $cm_data =  $cm_data->where('cancel_status', '=', 1);
        $cm_data = $cm_data->where('confirm_status', '=', 1);
      }
      $cm_data = $cm_data->orderBy('booking_date', 'DESC')
        ->get();

      if (sizeof($cm_data) > 0) {
        foreach ($cm_data as $cm) {
          $date1 = date_create($cm->checkout_at);
          $date2 = date_create($cm->checkin_at);
          $diff = date_diff($date1, $date2);
          $no_of_nights = $diff->format("%a");
          if ($no_of_nights == 0) {
            $no_of_nights = 1;
          }

          if ($cm->confirm_status == 1 && $cm->cancel_status == 0) {
            $btn_status = 'Confirmed';
          } elseif ($cm->confirm_status == 1 && $cm->cancel_status == 1) {
            $btn_status = 'Cancelled';
          }

          $booking_details['unique_id'] = $cm->unique_id;
          $booking_details['channel_name'] = $cm->channel_name;
          // $booking_details['booking_date'] = $cm->booking_date;
          $booking_details['booking_date'] = date('d M Y', strtotime($cm->booking_date));
          $booking_details['customer_details'] = strtoupper($cm->customer_details);
          $booking_details['nights'] = $no_of_nights;
          $booking_details['checkout_at'] = $cm->checkout_at . '-' . $cm->checkout_at;
          $booking_details['payment_status'] = $cm->payment_status;
          $booking_details['booking_status'] = $btn_status;

          array_push($all_booking_data, $booking_details);
        }
      }
    }

    //fetch booking from BE
    if ($date_type == 1) {
      $check_be_date = 'hotel_booking.booking_date';
    } elseif ($date_type == 3) {
      $check_be_date = 'hotel_booking.check_out';
    } else {
      $check_be_date = 'hotel_booking.check_in';
    }

    if (sizeof($be_ids) > 0) {
      $be_data = Invoice::join('kernel.user_table', 'invoice_table.user_id', '=', 'user_table.user_id')
        ->join('hotel_booking', 'invoice_table.invoice_id', '=', 'hotel_booking.invoice_id')
        ->select(
          'user_table.first_name',
          'user_table.last_name',
          'user_table.user_id',
          'invoice_table.booking_date',
          'invoice_table.hotel_id',
          'invoice_table.pay_to_hotel',
          'invoice_table.check_in_out',
          'invoice_table.booking_status',
          'invoice_table.hotel_name',
          'hotel_booking.hotel_booking_id',
          'hotel_booking.check_in',
          'hotel_booking.check_out',
          'invoice_table.booking_source',
          'hotel_booking.invoice_id'
        )->groupBy('hotel_booking.invoice_id')
        ->where('invoice_table.hotel_id', '=', $hotel_id)
        ->whereBetween(DB::raw('date(' . $check_be_date . ')'), array($from_date, $to_date));


      if (sizeof($be_ids) > 0) {
        $be_data = $be_data->whereIn('invoice_table.booking_source', $be_ids);
      }

      if ($booking_status == 'confirm') {
        $be_data = $be_data->where('invoice_table.booking_status', '=', 1);
      } elseif ($booking_status == 'cancelled') {
        $be_data =  $be_data->where('invoice_table.booking_status', '=', 3);
      } else {
        $be_data =  $be_data->where('invoice_table.booking_status', '!=', 2);
      }

      $be_data =  $be_data->get();

      if (sizeof($be_data) > 0) {
        foreach ($be_data as $be) {
          $date1 = date_create($be->check_in);
          $date2 = date_create($be->check_out);
          $diff = date_diff($date1, $date2);
          $no_of_nights = $diff->format("%a");
          if ($no_of_nights == 0) {
            $no_of_nights = 1;
          }

          if ($be->booking_status == 1) {
            $btn_status = 'Confirmed';
          } elseif ($be->booking_status == 3) {
            $btn_status = 'Cancelled';
          }

          if ($be->booking_source == 'crs') {
            $check_crs_payment_type = DB::table('crs_booking')->where('invoice_id', $be->invoice_id)->where('payment_type', 1)->where('booking_status', 1)->first();
            if ($check_crs_payment_type) {
              $pay_to_hotel = 'Paid';
            } else {
              $pay_to_hotel = 'Pay at hotel';
            }
          } else if ($be->booking_source == 'website' || $be->booking_source == 'google') {
            $pay_to_hotel = 'Paid';
          } else {
            $pay_to_hotel = 'Pay at hotel';
          }

          $last_name = $be->last_name;
          if($last_name == 'undefined'){
            $last_name = '';
          }

          $booking_details['unique_id'] = date('dmy', strtotime($be->booking_date)) . $be->invoice_id;
          $booking_details['channel_name'] = $be->booking_source;
          // $booking_details['booking_date'] = $be->booking_date;
          $booking_details['booking_date'] = date('d M Y', strtotime($be->booking_date));
          $booking_details['customer_details'] = strtoupper($be->first_name) . ' ' . strtoupper($last_name);
          $booking_details['nights'] = $no_of_nights;
          $booking_details['checkout_at'] =  $be->check_in . '-' . $be->check_out;
          $booking_details['payment_status'] = $pay_to_hotel;
          $booking_details['booking_status'] = $btn_status;

          array_push($all_booking_data, $booking_details);
        }
      }
    }


    if ($all_booking_data) {
      
      usort($all_booking_data, array($this, 'cmp'));
      // header("Access-Control-Allow-Origin: *");
      // header("Access-Control-Allow-Headers: *");

      header('Content-Type: text/csv; charset=utf-8');
      header('Content-Disposition: attachment; filename=beBooking.csv');
      $output = fopen("php://output", "w");
      fputcsv($output, array("Booking ID","Booking Source","Booking Date","Guest Name","Booking Nights","Check in out","Payment Status","Booking Status"));
      // dd($all_booking_data);

      foreach ($all_booking_data as $data) {
        fputcsv($output, $data);
        // var_dump(fputcsv($output, $data));
      }
      //  var_dump($output);
      fclose($output);
      
    }
  }

  //================================================================================================================================

  public  function cmp($a, $b)
  {
    if(isset($a['date_type'])){
      $data = $a['date_type'];
      return $b[$data] > $a[$data];
    }else{   
      return $b['booking_date'] > $a['booking_date'];
    }
  }

  //================================================================================================================================

  public function channelLogo($hotel_id)
  {

    //ota logo list
    $ota_logo['MakeMyTrip'] = 'mmticon.png';
    $ota_logo['Goibibo'] = 'goibiboicon.png';
    $ota_logo['Expedia'] = 'expediaicon.png';
    $ota_logo['Cleartrip'] = 'cleartripicon.png';
    $ota_logo['Agoda'] = 'agodaicon.png';
    $ota_logo['Travelguru'] = 'tarvelguruicon.png';
    $ota_logo['Booking.com'] = 'bookingcomicon.png';
    $ota_logo['Via.com'] = 'viaicon.png';
    $ota_logo['Goomo'] = 'googmoicon.png';
    $ota_logo['Airbnb'] = 'airbnbicon.png';
    $ota_logo['EaseMyTrip'] = 'easemytripicon.png';
    $ota_logo['HappyEasyGo'] = 'happyeasygoicon.png';
    $ota_logo['Hostelworld'] = 'hostelworldicon.png';
    $ota_logo['Akbar'] = 'akbartravelicon.png';
    $ota_logo['IRCTC'] = 'irctcicon.png';
    $ota_logo['MMTGCC'] = 'mmticon.png';
    $ota_logo['Simplotel'] = 'simplotel.png';
    $ota_logo['Onlinevacations'] = 'OnlineVacations.png';
    $ota_logo['CleartripNew'] = 'cleartrip-new-icon.png';

    // $cuntry_id = DB::table('kernel.hotels_table')->select('country_id')->where('hotel_id', $hotel_id)->first();
    // if ($cuntry_id->country_id == '1') {
      $ota_logo['website'] = 'bj.png';
      $ota_logo['CRS'] = 'bj.png';
      $ota_logo['GEMS'] = 'bj.png';
      $ota_logo['QUICKPAYMENT'] = 'bj.png';
      $ota_logo['google'] = 'bj.png';
      $ota_logo['jiniassist'] = 'bj.png';
    // } else {
    //   $ota_logo['website'] = 'kiteicon.png';
    //   $ota_logo['CRS'] = 'kiteicon.png';
    //   $ota_logo['GEMS'] = 'kiteicon.png';
    //   $ota_logo['QUICKPAYMENT'] = 'kiteicon.png';
    //   $ota_logo['google'] = 'kiteicon.png';
    // }

    return $ota_logo;
  }




  public function datatoexcel($data)
  {
    $all_date = urldecode($data);
    $data = explode('#',$all_date);
    
    $from_date = $data[0];
    $to_date = $data[1];
    $date_type = $data[2];
    $ota_ids = [];
    $be_ids = [];

    if (isset($from_date)) {
      $from_date = date('Y-m-d', strtotime($from_date));
    } else {
      $from_date = date('Y-m-d');
    }
    if (isset($to_date)) {
      $to_date = date('Y-m-d', strtotime($to_date));
    } else {
      $to_date = date('Y-m-d');
    }

    $sources = explode(',',$data[3]);
  
    $booking_status = $data[4];
    $hotel_id = $data[5];
   
    if (isset($date_type)) {
      $date_type = $date_type;
    }
    if ($date_type == 1) {
      $check_date_type = 'booking_date';
    } elseif ($date_type == 3) {
      $check_date_type = 'checkout_at';
    } else {
      $check_date_type = 'checkin_at';
    }

    if (isset($sources)) {
      foreach ($sources as $source) {
        if ($source > 0) {
          array_push($ota_ids, $source);
        } else {
          if ($source == '-2') {
            $booking_source = 'CRS';
          } elseif ($source == '-3') {
            $booking_source = 'GEMS';
          }  elseif ($source == '-4') {
            $booking_source = 'google';
          }elseif ($source == '-6') {
            $booking_source = 'QUICKPAYMENT';
          } else {
            $booking_source = 'WEBSITE';
          }
          array_push($be_ids, $booking_source);
        }
      }
    }

    $all_booking_data = [];

    if (sizeof($ota_ids) > 0) {

      // if($hotel_id == '1953'){
        $source_ota = json_encode($sources);
        $post_data = array('hotel_id'=> $hotel_id,'check_date_type'=>$check_date_type,'from_date'=>$from_date,'to_date'=>$to_date, 'booking_id'=>'','booking_status'=>$booking_status,'source_ota'=>$source_ota);
  
        $url = 'https://cm.bookingjini.com/extranetv4/cm-booking-lists';
  
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
          CURLOPT_POSTFIELDS => $post_data,
          ));
          $cm_data = curl_exec($curl);
          curl_close($curl);
          $cm_data = json_decode($cm_data);

          // return $cm_data;
      // }else{

      //   $cm_data =  CmOtaBookingRead::select(DB::raw('distinct hotel_id,unique_id,customer_details,booking_status,channel_name,payment_status,checkin_at,checkout_at,booking_date,confirm_status,cancel_status,room_type,amount,tax_amount,rooms_qty,ota_id'))
      //     ->where('hotel_id', '=', $hotel_id)
      //     ->whereBetween(DB::raw('date(' . $check_date_type . ')'), array($from_date, $to_date));
      //   $cm_data = $cm_data->whereIn('ota_id', $ota_ids);
      
      //   if ($booking_status == 'confirm') {
      //     $cm_data = $cm_data->where('confirm_status', '=', 1);
      //     $cm_data = $cm_data->where('cancel_status', '=', 0);
      //   } elseif ($booking_status == 'cancelled') {
      //     $cm_data =  $cm_data->where('cancel_status', '=', 1);
      //     $cm_data = $cm_data->where('confirm_status', '=', 1);
      //   }

      //   $cm_data = $cm_data->orderBy('booking_date', 'DESC')
      //     ->get();

      // }
      
        if (sizeof($cm_data) > 0) {
          foreach ($cm_data as $cm) {
            $date1 = date_create($cm->checkout_at);
            $date2 = date_create($cm->checkin_at);
            $diff = date_diff($date1, $date2);
            $no_of_nights = $diff->format("%a");
            if ($no_of_nights == 0) {
              $no_of_nights = 1;
            }

            $room_types = explode(',', $cm->room_type);
            $room_type_array = [];
            if ($room_types) {
              foreach ($room_types as $room_type) {
                $data = DB::connection('bookingjini_cm')->table('cmlive.cm_ota_room_type_synchronize')
                ->join('kernel.room_type_table', 'cm_ota_room_type_synchronize.room_type_id', '=', 'kernel.room_type_table.room_type_id')
                  ->select('room_type_table.room_type')
                  ->where('cm_ota_room_type_synchronize.ota_room_type', $room_type)
                  ->first();
                $room_type_array[] = isset($data->room_type)?$data->room_type:'NA';
              }

              $room_type_array = implode(',', $room_type_array);
            }
      
            if ($cm->confirm_status == 1 && $cm->cancel_status == 0) {
              $btn_status = 'Confirmed';
            } elseif ($cm->confirm_status == 1 && $cm->cancel_status == 1) {
              $btn_status = 'Cancelled';
            }
      
            $customer_details = explode(',', $cm->customer_details);
      
            $booking_details['unique_id'] = $cm->unique_id;
            if (isset($customer_details[0])) {
              $booking_details['customer_name'] = strtoupper($customer_details[0]);
            } else {
              $booking_details['customer_name'] = 'NA';
            }
            if (isset($customer_details[1])) {
              $booking_details['customer_email'] = $customer_details[1];
            } else {
              $booking_details['customer_email'] = 'NA';
            }
            if (isset($customer_details[2])) {
              $booking_details['customer_phone'] = $customer_details[2];
            } else {
              $booking_details['customer_phone'] = 'NA';
            }
            $booking_details['channel_name'] = $cm->channel_name;
            $booking_details['booking_status'] = $btn_status;
            $booking_details['booking_date'] = $cm->booking_date;
            $booking_details['checkin_at'] = $cm->checkin_at;
            $booking_details['checkout_at'] = $cm->checkout_at;
            $booking_details['room_type'] = $room_type_array;
            $booking_details['no_of_rooms'] = $cm->rooms_qty;
            $booking_details['nights'] = $no_of_nights;
            $booking_details['amount'] = $cm->amount;
            $booking_details['tax_amount'] = $cm->tax_amount;
            $booking_details['checkout_at'] = $cm->checkout_at;
            $booking_details['payment_status'] = $cm->payment_status;
  
            array_push($all_booking_data, $booking_details);
          }
        }
      }
      
      //fetch booking from BE
      if ($date_type == 1) {
        $check_be_date = 'hotel_booking.booking_date';
      } elseif ($date_type == 3) {
        $check_be_date = 'hotel_booking.check_out';
      } else {
        $check_be_date = 'hotel_booking.check_in';
      }
      
      if (sizeof($be_ids) > 0) {
        $be_data = Invoice::join('kernel.user_table', 'invoice_table.user_id', '=', 'user_table.user_id')
          ->join('hotel_booking', 'invoice_table.invoice_id', '=', 'hotel_booking.invoice_id')
          ->select(
            'user_table.first_name',
            'user_table.last_name',
            'user_table.user_id',
            'user_table.mobile',
            'user_table.email_id',
            'invoice_table.booking_date',
            'invoice_table.hotel_id',
            'invoice_table.pay_to_hotel',
            'invoice_table.check_in_out',
            'invoice_table.booking_status',
            'invoice_table.hotel_name',
            'invoice_table.total_amount',
            'invoice_table.paid_amount',
            'invoice_table.tax_amount',
            'hotel_booking.hotel_booking_id',
            'hotel_booking.check_in',
            'hotel_booking.check_out',
            'invoice_table.booking_source',
            'hotel_booking.invoice_id',
            'invoice_table.room_type'
          )->groupBy('hotel_booking.invoice_id')
          ->where('invoice_table.hotel_id', '=', $hotel_id)
          ->whereBetween(DB::raw('date(' . $check_be_date . ')'), array($from_date, $to_date));
      
        if (sizeof($be_ids) > 0) {
          $be_data = $be_data->whereIn('invoice_table.booking_source', $be_ids);
        }
      
        if ($booking_status == 'confirm') {
          $be_data = $be_data->where('invoice_table.booking_status', '=', 1);
        } elseif ($booking_status == 'cancelled') {
          $be_data =  $be_data->where('invoice_table.booking_status', '=', 3);
        } else {
          $be_data =  $be_data->where('invoice_table.booking_status', '!=', 2);
        }
      
        $be_data =  $be_data->get();
      
        if (sizeof($be_data) > 0) {
          foreach ($be_data as $be) {
         
            $booked_roomtype = [];
            if ($be->booking_source == 'CRS') {
              $check_crs_payment_type = DB::table('crs_booking')->where('invoice_id', $be->invoice_id)->where('payment_type', 1)->where('booking_status', 1)->first();
              if ($check_crs_payment_type) {
                $pay_to_hotel = 'Paid';
              } else {
                $pay_to_hotel = 'Pay at Hotel';
              }
            } else {
              if ($be->paid_amount == 0) {
                $pay_to_hotel = 'Pay at Hotel';
              } else {
                $pay_to_hotel = 'Paid';
              }
            }
      
            $fetch_room_type = HotelBooking::join('kernel.room_type_table', 'hotel_booking.room_type_id', '=', 'room_type_table.room_type_id')
              ->select('room_type_table.room_type')
              ->where('invoice_id', $be->invoice_id)->get();
            foreach ($fetch_room_type as $room_type) {
              $booked_roomtype[] = $room_type->room_type;
            }
            $booked_roomtype = implode(',', $booked_roomtype);
      
            $date1 = date_create($be->check_in);
            $date2 = date_create($be->check_out);
            $diff = date_diff($date1, $date2);
            $no_of_nights = $diff->format("%a");
            if ($no_of_nights == 0) {
              $no_of_nights = 1;
            }
      
            if ($be->booking_status == 1) {
              $btn_status = 'Confirmed';
            } elseif ($be->booking_status == 3) {
              $btn_status = 'Cancelled';
            }
      
            //Fetch Total no of rooms.
            $fetch_rooms = json_decode($be->room_type);
            $total_rooms = 0;
            foreach ($fetch_rooms as $rooms) {
              $no_of_rooms = substr($rooms, 0, 1);
              $total_rooms += (int) $no_of_rooms;
            }

            $last_name = $be->last_name;
            if($last_name == 'undefined'){
              $last_name = '';
            }
      
            $booking_details_be['unique_id'] = date('dmy', strtotime($be->booking_date)) . $be->invoice_id;
            $booking_details_be['customer_details'] = strtoupper($be->first_name) . ' ' . strtoupper($last_name);
            $booking_details_be['email_id'] = $be->email_id;
            $booking_details_be['phone'] = $be->mobile;
            $booking_details_be['channel_name'] = $be->booking_source;
            $booking_details_be['booking_status'] = $btn_status;
            $booking_details_be['booking_date'] = $be->booking_date;
            $booking_details_be['check_in'] = $be->check_in;
            $booking_details_be['check_out'] = $be->check_out;
            $booking_details_be['room_type'] = $booked_roomtype;
            $booking_details_be['no_of_rooms'] = $total_rooms;
            $booking_details_be['nights'] = $no_of_nights;
            $booking_details_be['amount'] = $be->total_amount;
            $booking_details_be['tax_amount'] = $be->tax_amount;
            $booking_details_be['payment_status'] = $pay_to_hotel;
      
            array_push($all_booking_data, $booking_details_be);
          }
        }
      }
      if ($all_booking_data) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=otaBooking.csv');
        $output = fopen("php://output", "w");
        fputcsv($output, array('Booking ID', 'Guest Name', 'Email', 'Mobile', 'Channel Name', 'Booking Status', 'Booking Date', 'Checkin Date', 'Checkout Date', 'Room Type', 'Rooms', 'Total Nights', 'Total amount', 'tax amount', 'Payment Status'));

        foreach ($all_booking_data as $data) {
          fputcsv($output, $data);
        }
        fclose($output);
      }
    
  }



  public  function listViewBookings(Request $request)
  {
    $failure_message = 'Bookings Fetch Failed';

    $data = $request->all();
    if (isset($data['from_date'])) {
      $from_date = date('Y-m-d', strtotime($data['from_date']));
    } else {
      $from_date = date('Y-m-d');
    }

    if (isset($data['to_date'])) {
      $to_date = date('Y-m-d', strtotime($data['to_date']));
    } else {
      $to_date = date('Y-m-d');
    }

    $booking_status = $data['booking_status'];
    $hotel_id = $data['hotel_id'];
    $booking_id = $data['booking_id'];
    $sources = $data['source'];
    if (isset($data['date_type'])) {
      $date_type = $data['date_type'];
    }
    $ota_ids = [];
    $be_ids = [];

    //fetch channel logo
    $ota_logo = $this->channelLogo($hotel_id);

    if ($date_type == 1) {
      $check_date_type = 'booking_date';
    } elseif ($date_type == 3) {
      $check_date_type = 'checkout_at';
    } else {
      $check_date_type = 'checkin_at';
    }

    if (isset($sources)) {
      foreach ($sources as $source) {
        if ($source > 0) {
          array_push($ota_ids, $source);
        } else {
          if ($source == '-2') {
            $booking_source = 'CRS';
          } elseif ($source == '-3') {
            $booking_source = 'GEMS';
          } elseif ($source == '-4') {
            $booking_source = 'google';
          } elseif ($source == '-5') {
            $booking_source = 'jiniassist';
          }elseif ($source == '-6') {
            $booking_source = 'QUICKPAYMENT';
          } else {
            $booking_source = 'website';
          }
          array_push($be_ids, $booking_source);
        }
      }
    }

    $all_booking_data = [];
    // get ota bookings
    if (sizeof($ota_ids) > 0) {
      $source_ota = json_encode($data['source']);
      $post_data = array('hotel_id'=> $hotel_id,'check_date_type'=>$check_date_type,'from_date'=>$from_date,'to_date'=>$to_date, 'booking_id'=>$booking_id,'booking_status'=>$booking_status,'source_ota'=>$source_ota);

      $url = 'https://cm.bookingjini.com/extranetv4/cm-booking-lists';

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
        CURLOPT_POSTFIELDS => $post_data,
        ));
        $cm_data = curl_exec($curl);
        curl_close($curl);
        $cm_data = json_decode($cm_data);

        // return $cm_data;
     
     
        $is_airPay_enable = DB::connection('bookingjini_cm')
        ->table('payment_gateway_details')
        ->where('hotel_id',$hotel_id)
        ->first();
        if($is_airPay_enable){
          $is_card_chargeable = 1;
        }else{
          $is_card_chargeable = 0;
        }
      
      
      if (sizeof($cm_data) > 0) {
        foreach ($cm_data as $cm) {
          $date1 = date_create($cm->checkout_at);
          $date2 = date_create($cm->checkin_at);
          $diff = date_diff($date1, $date2);
          $no_of_nights = $diff->format("%a");

          if ($no_of_nights == 0) {
            $no_of_nights = 1;
          }

          if ($cm->payment_status == 'Paid' || $cm->payment_status == 'Prepaid' || $cm->payment_status == 'Bill To Company') {
            $payment_status_color = 'prepaid__label';
            $payment_status = 'Paid';
          } elseif ($cm->payment_status == 'Pay at hotel') {
            $payment_status_color = 'pay__at__hotel';
            $payment_status = $cm->payment_status;
          }else{
            $payment_status_color = 'pay__at__hotel';
            $payment_status = $cm->payment_status;
          }

          if ($cm->confirm_status == 1 && $cm->cancel_status == 0) {
            $btn_status = 'Confirmed';
          } elseif ($cm->confirm_status == 1 && $cm->cancel_status == 1) {
            $btn_status = 'Cancelled';
          }

          $booking_details['nights'] = $no_of_nights;
          $booking_details['hotel_id'] = $cm->hotel_id;
          $booking_details['unique_id'] = $cm->unique_id;
          $booking_details['no_of_rooms'] = $cm->rooms_qty;
          $booking_details['customer_details'] = strtoupper($cm->customer_details);
          $booking_details['booking_status'] = $cm->booking_status;
          $booking_details['channel_name'] = $cm->channel_name;
          $booking_details['payment_status'] = $payment_status;
          $booking_details['payment_status_color'] = $payment_status_color;
          $booking_details['checkin_at'] = $cm->checkin_at;
          $booking_details['checkout_at'] = $cm->checkout_at;
          $booking_details['booking_date'] = date("Y-m-d H:i:s", strtotime($cm->booking_date));
          $booking_details['display_booking_date'] = date('d M Y', strtotime($cm->booking_date));
          $booking_details['confirm_status'] = $cm->confirm_status;
          $booking_details['cancel_status'] = $cm->cancel_status;
          $booking_details['btn_status'] = $btn_status;
          $booking_details['ota_id'] = $cm->ota_id;
          $channel_name = $cm->channel_name;
          $booking_details['logo'] = "https://d3ki85qs1zca4t.cloudfront.net/logo/ota/" . $ota_logo[$channel_name];
          $booking_details['is_mail_send_enable'] = 0;
          $booking_details['date_type'] = $check_date_type;
         
            if($cm->channel_name == 'Booking.com' && $is_card_chargeable == 1){
              $booking_details['is_card_chargeable'] = 1;
            }else{
              $booking_details['is_card_chargeable'] = 0;
            }
          

          $today = date('Y-m-d');
          $noshow_date = date('Y-m-d', strtotime($cm->checkin_at . ' +1 day'));
          if ($today > date('Y-m-d', strtotime($cm->checkin_at)) && $today >= $noshow_date && $cm->confirm_status == 1 && $cm->cancel_status == 0 && $today <= date('Y-m-d', strtotime($cm->checkout_at))) {
            if (($cm->channel_name == 'Goibibo' && $cm->payment_status == 'Pay at hotel') || $cm->channel_name == 'Booking.com') {
              $is_ota_active = CmOtaDetails::where('hotel_id', $cm->hotel_id)->where('ota_name', $cm->channel_name)->where('ota_id', $cm->ota_id)->first();
              if (isset($is_ota_active->is_active) && $is_ota_active->is_active == 1) {
                $booking_details['no_show_flag'] = 1;
                $booking_details['no_show_message'] = '';
              } else {
                $booking_details['no_show_flag'] = 0;
                $booking_details['no_show_message'] = 'No show only applicable for Booking.com and Goibibo(Pay at hotel) Bookings';
              }
            } else {
              $booking_details['no_show_flag'] = 0;
              $booking_details['no_show_message'] = 'No show only applicable for Booking.com and Goibibo(Pay at hotel) Bookings';
            }
          } else {
            if($cm->confirm_status == 1 && $cm->cancel_status == 1){
              $booking_details['no_show_flag'] = 0;
              $booking_details['no_show_message'] = 'No show is not available for Cancel Bookings';
            }else if($today > date('Y-m-d', strtotime($cm->checkout_at))){
              $booking_details['no_show_flag'] = 0;
              $booking_details['no_show_message'] = 'No show date exceeded';
            }else{
              $booking_details['no_show_flag'] = 0;
              $booking_details['no_show_message'] = 'No show only applicable after check-in';
            }
     
          }

          array_push($all_booking_data, $booking_details);
        }
      }
    }

    //fetch booking from BE
    if ($date_type == 1) {
      $check_be_date = 'hotel_booking.booking_date';
    } elseif ($date_type == 3) {
      $check_be_date = 'hotel_booking.check_out';
    } else {
      $check_be_date = 'hotel_booking.check_in';
    }

    if (sizeof($be_ids) > 0) {
      $be_data = Invoice::join('kernel.user_table', 'invoice_table.user_id', '=', 'user_table.user_id')
        ->join('hotel_booking', 'invoice_table.invoice_id', '=', 'hotel_booking.invoice_id')
        ->join('kernel.hotels_table', 'invoice_table.hotel_id', '=', 'hotels_table.hotel_id')
        ->join('kernel.company_table', 'hotels_table.company_id', '=', 'company_table.company_id')
        ->select(
          'user_table.first_name',
          'user_table.last_name',
          'user_table.user_id',
          'invoice_table.company_name',
          'invoice_table.GSTIN',
          'invoice_table.booking_date',
          'invoice_table.hotel_id',
          'invoice_table.pay_to_hotel',
          'invoice_table.check_in_out',
          'invoice_table.booking_status',
          'invoice_table.hotel_name',
          'invoice_table.total_amount',
          'invoice_table.paid_amount',
          'hotel_booking.hotel_booking_id',
          'hotel_booking.check_in',
          'hotel_booking.check_out',
          'invoice_table.booking_source',
          'hotel_booking.invoice_id',
          'invoice_table.room_type',
          'company_table.logo'
        )
        ->groupBy('hotel_booking.invoice_id')
        ->where('invoice_table.hotel_id', '=', $hotel_id)
        ->whereBetween(DB::raw('date(' . $check_be_date . ')'), array($from_date, $to_date));


      if (sizeof($be_ids) > 0) {
        $be_data = $be_data->whereIn('invoice_table.booking_source', $be_ids);
      }

      if ($booking_status == 'confirm') {
        $be_data = $be_data->where('invoice_table.booking_status', '=', 1);
      } elseif ($booking_status == 'cancelled') {
        $be_data =  $be_data->where('invoice_table.booking_status', '=', 3);
      } else {
        $be_data =  $be_data->where('invoice_table.booking_status', '!=', 2);
      }

      if ($booking_id) {
        $be_data = $be_data->orWhere('invoice_table.invoice_id', 'like', '%' . $booking_id . '%')
          ->orWhere('first_name', 'like', '%' . $booking_id . '%');
      }
      $be_data =  $be_data->get();

      if (sizeof($be_data) > 0) {
        foreach ($be_data as $be) {

          // if ($be->booking_source == 'CRS') {
          //   $check_crs_payment_type = DB::table('crs_booking')->where('invoice_id', $be->invoice_id)->where('payment_type', 1)->where('booking_status', 1)->first();
          //   if ($check_crs_payment_type) {
          //     $pay_to_hotel = 'Paid';
          //     $payment_status_color = 'prepaid__label';
          //   } else {
          //     $pay_to_hotel = 'Pay at hotel';
          //     $payment_status_color = 'pay__at__hotel';
          //   }
          // } else {
          //   if ($be->paid_amount == 0) {
          //     $pay_to_hotel ='Pay at hotel';
          //     $payment_status_color = 'pay__at__hotel';
          //   } else {
          //     $pay_to_hotel = 'Paid';
          //     $payment_status_color = 'prepaid__label';
          //   }
          // }

          // if($hotel_id==2600){
            $total_amount = floor($be->total_amount);
            $paid_amount =  floor($be->paid_amount);

            if ($be->booking_source == 'CRS') {
              $check_crs_payment_type = DB::table('crs_booking')->where('invoice_id', $be->invoice_id)->where('payment_type', 1)->where('booking_status', 1)->first();
              $payment_capture = DB::table('crs_payment_receive')->where('invoice_id', $be->invoice_id)->sum('receive_amount');

              if ($check_crs_payment_type) {
                if($check_crs_payment_type->is_payment_received == 1 && $payment_capture>0){
                  $paid_amount = floor($payment_capture) + $paid_amount;
                }else if($check_crs_payment_type->is_payment_received == 0 && $payment_capture>0){
                  $paid_amount = floor($payment_capture);
                }

                if(($check_crs_payment_type->is_payment_received == 1) && ($total_amount == $paid_amount)){
                  $pay_to_hotel = 'Paid';
                  $payment_status_color = 'prepaid__label';
                }else if(($check_crs_payment_type->is_payment_received == 1) && ($total_amount != $paid_amount)){
                  $pay_to_hotel = 'Partially Paid';
                  $payment_status_color = 'prepaid__label';
                }else{
                  $pay_to_hotel = 'Waiting for payment';
                  $payment_status_color = 'pay__at__hotel';
                }
              } else {
                $pay_to_hotel = 'Pay at hotel';
                $payment_status_color = 'pay__at__hotel';
              }
            } else {
              if ($paid_amount == 0) {
                $pay_to_hotel = 'Pay at hotel';
                $payment_status_color = 'pay__at__hotel';
              } else if($total_amount == $paid_amount){
                $pay_to_hotel = 'Paid';
                $payment_status_color = 'prepaid__label';
              }else{
                $pay_to_hotel = 'Partially Paid';
                $payment_status_color = 'prepaid__label';
              }
            }

          // }

          //Fetch Total no of rooms.
          $fetch_rooms = json_decode($be->room_type);
          $total_rooms = 0;
          foreach ($fetch_rooms as $rooms) {
            $no_of_rooms = substr($rooms, 0, 1);
            $total_rooms += (int) $no_of_rooms;
          }

          $date1 = date_create($be->check_in);
          $date2 = date_create($be->check_out);
          $diff = date_diff($date1, $date2);
          $no_of_nights = $diff->format("%a");
          if ($no_of_nights == 0) {
            $no_of_nights = 1;
          }

          if ($be->booking_status == 1) {
            $btn_status = 'Confirmed';
          } elseif ($be->booking_status == 3) {
            $btn_status = 'Cancelled';
          }

          // $corporate_booking = [
          //   'company_name' => $be->company_name,
          //   'gstin' => $be->GSTIN,
          // ];

          $last_name = $be->last_name;
          if($last_name == 'undefined'){
            $last_name = '';
          }

          $booking_details['nights'] = $no_of_nights;
          $booking_details['hotel_id'] = $be->hotel_id;
          $booking_details['unique_id'] = date('dmy', strtotime($be->booking_date)) . $be->invoice_id;
          $booking_details['no_of_rooms'] = $total_rooms;
          $booking_details['customer_details'] = strtoupper($be->first_name) . ' ' . strtoupper($last_name);
          $booking_details['booking_status'] = $be->booking_status;
          $booking_details['channel_name'] = $be->booking_source;
          $booking_details['payment_status'] = $pay_to_hotel;
          $booking_details['payment_status_color'] = $payment_status_color;
          $booking_details['checkin_at'] = $be->check_in;
          $booking_details['checkout_at'] = $be->check_out;
          $booking_details['booking_date'] = $be->booking_date;
          $booking_details['display_booking_date'] = date('d M Y', strtotime($be->booking_date));
          $booking_details['confirm_status'] = $be->booking_status;
          $booking_details['btn_status'] = $btn_status;
          $booking_details['ota_id'] = -1;
          $booking_details['no_show_flag'] = 0;
          $booking_details['is_card_chargeable'] = 0;
          $booking_details['no_show_message'] = 'No show only applicable for Booking.com and Goibibo(Pay at hotel) Bookings';
          $booking_details['date_type'] = $check_date_type;
          // $booking_details['cancel_status'] = $be->cancel_status;
          $channel_name = $be->booking_source;
          $fetchHotelLogo = ImageTable::where('image_id', $be->logo)->select('image_name')->first();
          if (isset($fetchHotelLogo->image_name)) {
            $booking_details['logo'] = "https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/" . $fetchHotelLogo->image_name;
          } else {
            $booking_details['logo'] = "https://d3ki85qs1zca4t.cloudfront.net/logo/ota/" . $ota_logo[$channel_name];
          }

          if ($be->booking_source == 'WEBSITE' || $be->booking_source == 'website') {
            $booking_details['is_mail_send_enable'] = 1;
          }
           if($be->gstin != ''){
            $booking_details['business_booking'] = 1;
          }else{
            $booking_details['business_booking'] = 0;
          }

          array_push($all_booking_data, $booking_details);
        }
      }
    }

    if ($all_booking_data) {
      usort($all_booking_data, array($this, 'cmp'));
      $result = array('status' => 1, "message" => 'Bookings Fetch Successfully', 'data' => $all_booking_data);
      return response()->json($result);
    } else {
      $result = array('status' => 0, "message" => $failure_message);
      return response()->json($result);
    }
  }

  public  function listViewBookingsCrs(Request $request)
  {

    $failure_message = 'Bookings Fetch Failed';

    $data = $request->all();
    if (isset($data['from_date'])) {
      $from_date = date('Y-m-d', strtotime($data['from_date']));
    } else {
      $from_date = date('Y-m-d');
    }

    if (isset($data['to_date'])) {
      $to_date = date('Y-m-d', strtotime($data['to_date']));
    } else {
      $to_date = date('Y-m-d');
    }

    

    $booking_status = $data['booking_status'];
    $hotel_id = $data['hotel_id'];
    $booking_id = $data['booking_id'];
    $sources = $data['source'];
    if (isset($data['date_type'])) {
      $date_type = $data['date_type'];
    }
    

    $be_ids = [];
    //fetch channel logo
    $ota_logo = $this->channelLogo($hotel_id);

    if ($date_type == 1) {
      $check_date_type = 'booking_date';
    } elseif ($date_type == 3) {
      $check_date_type = 'checkout_at';
    } else {
      $check_date_type = 'checkin_at';
    }
    $booking_source = 'CRS';
     

    $all_booking_data = [];

    //fetch booking from BE
    if ($date_type == 1) {
      $check_be_date = 'hotel_booking.booking_date';
    } elseif ($date_type == 3) {
      $check_be_date = 'hotel_booking.check_out';
    } else {
      $check_be_date = 'hotel_booking.check_in';
    }
    


      $be_data = Invoice::join('kernel.user_table', 'invoice_table.user_id', '=', 'user_table.user_id')
        ->join('hotel_booking', 'invoice_table.invoice_id', '=', 'hotel_booking.invoice_id')
        ->join('kernel.hotels_table', 'invoice_table.hotel_id', '=', 'hotels_table.hotel_id')
        ->join('kernel.company_table', 'hotels_table.company_id', '=', 'company_table.company_id')
        ->select(
          'user_table.first_name',
          'user_table.last_name',
          'user_table.user_id',
          'invoice_table.booking_date',
          'invoice_table.hotel_id',
          'invoice_table.pay_to_hotel',
          'invoice_table.check_in_out',
          'invoice_table.booking_status',
          'invoice_table.hotel_name',
          'invoice_table.total_amount',
          'invoice_table.paid_amount',
          'hotel_booking.hotel_booking_id',
          'hotel_booking.check_in',
          'hotel_booking.check_out',
          'invoice_table.booking_source',
          'hotel_booking.invoice_id',
          'invoice_table.room_type',
          'company_table.logo'
        )
        ->groupBy('hotel_booking.invoice_id')
        ->where('invoice_table.hotel_id', '=', $hotel_id)
        ->whereBetween(DB::raw('date(' . $check_be_date . ')'), array($from_date, $to_date));


        $be_data = $be_data->where('invoice_table.booking_source', 'CRS');


      if ($booking_status == 'confirm') {
        $be_data = $be_data->where('invoice_table.booking_status', '=', 1);
      } elseif ($booking_status == 'cancelled') {
        $be_data =  $be_data->where('invoice_table.booking_status', '=', 3);
      } else {
        $be_data =  $be_data->where('invoice_table.booking_status', '!=', 2);
      }

      if ($booking_id) {
        $be_data = $be_data->orWhere('invoice_table.invoice_id', 'like', '%' . $booking_id . '%')
          ->orWhere('first_name', 'like', '%' . $booking_id . '%');
      }
      $be_data =  $be_data->get();

      if(sizeof($be_data)>0){
        foreach ($be_data as $be) {
          if ($be->booking_source == 'CRS') {
            $check_crs_payment_type = DB::table('crs_booking')->where('invoice_id', $be->invoice_id)->where('payment_type', 1)->where('booking_status', 1)->first();
            if ($check_crs_payment_type) {
              $pay_to_hotel = 'Paid';
              $payment_status_color = 'prepaid__label';
            } else {
              $pay_to_hotel = 'Pay at hotel';
              $payment_status_color = 'pay__at__hotel';
            }
          }

          //Fetch Total no of rooms.
          $fetch_rooms = json_decode($be->room_type);
          $total_rooms = 0;
          foreach ($fetch_rooms as $rooms) {
            $no_of_rooms = substr($rooms, 0, 1);
            $total_rooms += (int) $no_of_rooms;
          }

          $date1 = date_create($be->check_in);
          $date2 = date_create($be->check_out);
          $diff = date_diff($date1, $date2);
          $no_of_nights = $diff->format("%a");
          if ($no_of_nights == 0) {
            $no_of_nights = 1;
          }

          if ($be->booking_status == 1) {
            $btn_status = 'Confirmed';
          } elseif ($be->booking_status == 3) {
            $btn_status = 'Cancelled';
          }

          $booking_details['nights'] = $no_of_nights;
          $booking_details['hotel_id'] = $be->hotel_id;
          $booking_details['unique_id'] = date('dmy', strtotime($be->booking_date)) . $be->invoice_id;
          $booking_details['no_of_rooms'] = $total_rooms;
          $booking_details['customer_details'] = strtoupper($be->first_name) . ' ' . strtoupper($be->last_name);
          $booking_details['booking_status'] = $be->booking_status;
          $booking_details['channel_name'] = $be->booking_source;
          $booking_details['payment_status'] = $pay_to_hotel;
          $booking_details['payment_status_color'] = $payment_status_color;
          $booking_details['checkin_at'] = $be->check_in;
          $booking_details['checkout_at'] = $be->check_out;
          $booking_details['booking_date'] = $be->booking_date;
          $booking_details['display_booking_date'] = date('d M Y', strtotime($be->booking_date));
          $booking_details['confirm_status'] = $be->booking_status;
          $booking_details['btn_status'] = $btn_status;
          $booking_details['ota_id'] = -1;
          // $booking_details['no_show_flag'] = 0;
          // $booking_details['is_card_chargeable'] = 0;
          // $booking_details['no_show_message'] = 'No show only applicable for Booking.com and Goibibo(Pay at hotel) Bookings';
          $booking_details['date_type'] = $check_date_type;

          // $booking_details['cancel_status'] = $be->cancel_status;
          $channel_name = $be->booking_source;
          $fetchHotelLogo = ImageTable::where('image_id', $be->logo)->select('image_name')->first();
          if (isset($fetchHotelLogo->image_name)) {
            $booking_details['logo'] = "https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/" . $fetchHotelLogo->image_name;
          } else {
            $booking_details['logo'] = "https://d3ki85qs1zca4t.cloudfront.net/logo/ota/" . $ota_logo[$channel_name];
          }

          // if ($be->booking_source == 'WEBSITE' || $be->booking_source == 'website') {
          //   $booking_details['is_mail_send_enable'] = 1;
          // }

          array_push($all_booking_data, $booking_details);
        }
      }
      
    

    if ($all_booking_data) {
      usort($all_booking_data, array($this, 'cmp'));
      $result = array('status' => 1, "message" => 'Bookings Fetch Successfully', 'data' => $all_booking_data);
      return response()->json($result);
    } else {
      $result = array('status' => 0, "message" => $failure_message);
      return response()->json($result);
    }
  }


  public function getBookingDetailsById(Request $request,$booking_id)
  {
    try{
      $bookingid = $booking_id;
      $current_date = date('Y-m-d');

          $invoice_id = substr($bookingid, 6);
          $country_id =  Invoice::join('kernel.hotels_table', 'invoice_table.hotel_id', '=', 'kernel.hotels_table.hotel_id')
              ->select('hotels_table.country_id')
              ->where('invoice_table.invoice_id', $invoice_id)
              ->first();
       
          if ($country_id->country_id == '1') {
              $booking_details['currency_icon'] =  'fa fa-inr';
              $booking_details['currency_name'] =  'INR';
              $booking_details['tax'] =  'GST';
          } else {
              $booking_details['currency_icon'] = 'fa fa-usd';
              $booking_details['currency_name'] = 'USD';
              $booking_details['tax'] = 'VAT';
          }

        


          $be_bookings =  Invoice::join('kernel.user_table', 'invoice_table.user_id', '=', 'user_table.user_id')
              ->join('hotel_booking', 'invoice_table.invoice_id', '=', 'hotel_booking.invoice_id')
              ->join('kernel.hotels_table', 'hotel_booking.hotel_id', '=', 'hotels_table.hotel_id')
              ->join('kernel.company_table', 'hotels_table.company_id', '=', 'company_table.company_id')
              ->select(
                  'user_table.first_name',
                  'user_table.last_name',
                  'user_table.email_id',
                  'user_table.address',
                  'user_table.mobile',
                  'user_table.user_id',
                  'user_table.mobile',
                  'user_table.user_address',
                  'user_table.company_name',
                  'user_table.GSTIN',
                  'invoice_table.room_type',
                  'invoice_table.total_amount',
                  'invoice_table.tax_amount',
                  'invoice_table.paid_amount',
                  'invoice_table.booking_date',
                  'invoice_table.invoice',
                  'invoice_table.booking_status',
                  'invoice_table.invoice_id',
                  'invoice_table.booking_source',
                  'invoice_table.hotel_name',
                  'invoice_table.hotel_id',
                  'invoice_table.ref_no',
                  'invoice_table.extra_details',
                  'invoice_table.agent_code',
                  'hotel_booking.hotel_booking_id',
                  'hotel_booking.rooms',
                  'hotel_booking.check_in',
                  'hotel_booking.check_out',
                  'hotels_table.state_id',
                  'company_table.logo'
              )
              ->where('invoice_table.invoice_id', '=', $invoice_id)
              ->first();

          if ($be_bookings) {

           
              $check_in = date_create($be_bookings->check_in);
              $check_out = date_create($be_bookings->check_out);
              $diff = date_diff($check_in, $check_out);
              $diff = (int)$diff->format("%a");
              if ($diff == 0) {
                  $diff = 1;
              }
              $booking_details['invoice_id'] = $invoice_id;
              $booking_details['guest_name'] = $be_bookings->first_name . ' ' . $be_bookings->last_name;
              $booking_details['email_id'] = $be_bookings->email_id;
              $booking_details['mobile'] = $be_bookings->mobile;
              $booking_details['address'] = $be_bookings->address;
              if (isset($be_bookings->user_address)) {
                  $booking_details['user_address'] = $be_bookings->user_address;
              } else {
                  $booking_details['user_address'] = "";
              }
              if (empty($be_bookings->GSTIN) || $be_bookings->GSTIN == '' || $be_bookings->GSTIN == 'NULL' || $be_bookings->GSTIN == null) {
                  $booking_details['business_booking'] = 0;
              } else {
                  $booking_details['business_booking'] = 1;
              }
              $booking_details['company_name'] = $be_bookings->company_name;
              $booking_details['GSTIN'] = $be_bookings->GSTIN;
              $booking_details['nights'] = $diff;
              $booking_details['bookingid'] = $bookingid;
              $booking_details['booking_date'] = date('d M Y', strtotime($be_bookings->booking_date));
              $booking_details['checkin_at'] = date('d M Y', strtotime($be_bookings->check_in));
              $booking_details['checkout_at'] = date('d M Y', strtotime($be_bookings->check_out));
              $booking_details['price'] = $be_bookings->total_amount;
              $booking_details['tax_amount'] = $be_bookings->tax_amount;
              // $booking_details['discount'] = 0;
              $booking_details['state_id'] = $be_bookings->state_id;
              $booking_details['hotel_name'] = $be_bookings->hotel_name;

             

              $get_plan_details = DB::table('kernel.subscriptions')
                  ->join('kernel.plans', 'kernel.subscriptions.plan_id', '=', 'kernel.plans.plan_id')
                  ->where('hotel_id', $be_bookings->hotel_id)
                  ->orderBy('id', 'DESC')
                  ->first();
              //check the hotel have JINI HOST or not.
              $fetch_apps = explode(',', $get_plan_details->apps);
              $check_apps = in_array('6', $fetch_apps);
              if ($be_bookings->booking_status == 3) {
                  $booking_details['download_invoice'] = 0;
                  $booking_details['is_modify'] = 0;
                  $booking_details['is_checkin'] = 0;
                  $booking_details['is_cancel'] = 0;
              } else {

                  if ($request->exists('allocation') && $request->allocation == 4) {
                      $booking_details['download_invoice'] = 1;
                      $booking_details['is_modify'] = 0;
                      $booking_details['is_checkin'] = 0;
                      $booking_details['is_cancel'] = 0;
                  } else {
                      $booking_details['download_invoice'] = 0;
                    
                          $booking_details['is_modify'] = 0;
                          if ($check_apps && $current_date >= $be_bookings->check_in && $current_date <= $be_bookings->check_out) {
                              $booking_details['is_checkin'] = 0; //1
                          } else {
                              $booking_details['is_checkin'] = 0;
                          }
                          $booking_details['is_cancel'] = 0;
                  }
              }

              $room_type_details = HotelBooking::where('invoice_id', $invoice_id)->get();
              $room_type_wise_price = DB::table('be_booking_details_table')->where('ref_no',$be_bookings->ref_no)->get();


              $booking_details['coupon_code'] = 'NA';
              $booking_details['discount'] = 0;


              // if($be_bookings->agent_code != 'NA'){
              //   $coupon_details = DB::table('coupons')->where('coupon_code',$be_bookings->agent_code)->first();
               
              //   $booking_details['coupon_name'] = $coupon_details->coupon_name;
              //   $booking_details['discount'] =  $coupon_details->discount;
              // }
             


              $room_rate = array();
              foreach($room_type_wise_price as $k => $v){
                $room_rate[$v->room_type_id] = ($v->room_rate - $v->discount_price + $v->tax_amount )/$v->rooms ;
              }

              $room_type_array = [];
              $total_room_type_adult = 0;
              $total_room_type_child = 0;
              $occupancy_details = json_decode($be_bookings->extra_details, true);
              foreach ($room_type_details as $key => $room_type) {
                  $details = DB::table('kernel.room_rate_plan')
                      ->join('kernel.room_type_table', 'kernel.room_rate_plan.room_type_id', 'room_type_table.room_type_id')
                      ->join('kernel.rate_plan_table', 'kernel.room_rate_plan.rate_plan_id', 'rate_plan_table.rate_plan_id')
                      ->select('room_type_table.room_type', 'room_type_table.room_type_id', 'room_type_table.image', 'rate_plan_table.plan_type', 'rate_plan_table.plan_name', 'rate_plan_table.rate_plan_id', 'room_rate_plan.bar_price')
                      ->where('room_rate_plan.room_type_id', $room_type->room_type_id)
                      ->first();
                  if (isset($details->image)) {
                      $images = explode(',', $details->image);
                      $image_url = ImageTable::where('image_id', $images['0'])->first();
                      if ($image_url) {
                          $img = $image_url->image_name;
                      } else {
                          $img = '';
                      }
                  } else {
                      $img = '';
                  }

              
                  $rate_plan_info = json_decode($be_bookings->room_type);
                  $rate_info = explode('(', $rate_plan_info[$key]);
                  if (isset($rate_info[1])) {
                      $rate_plan_end = end($rate_info);
                      if (isset($rate_plan_end)) {
                          $rate_info_sep = explode(')', $rate_plan_end);
                          $rate_plan_dtl = $rate_info_sep[0];
                      } else {
                          $rate_plan_dtl = 'NA';
                      }
                  } else {
                      $rate_plan_dtl = 'NA';
                  }

                  foreach ($occupancy_details as $occupancy_detail) {
                      if (isset($occupancy_detail)) {
                          foreach ($occupancy_detail as $rm_id => $extra) {
                              if ($rm_id == $room_type->room_type_id) {
                                  $total_room_type_adult = $extra[0];
                                  $total_room_type_child = $extra[1];
                              }
                          }
                      }
                  }

                $coupon_code= 'NA';
                $discount =  0;
              if($be_bookings->agent_code != 'NA'){
                $coupon_details = DB::table('coupons')->where('coupon_code',$be_bookings->agent_code)->first();
               
                $coupon_code= $coupon_details->coupon_code;
                $discount =  $coupon_details->discount;
              }


                  $room_details = [
                      'no_of_rooms' => $room_type->rooms,
                      'room_type' => $details->room_type,
                      'room_type_id' => $room_type->room_type_id,
                      'rate_plan_id' => $details->rate_plan_id,
                      'plan_type' => $rate_plan_dtl,
                      'plan_name' => $details->plan_name,
                      'room_image' => $img,
                      'adult' => $total_room_type_adult == "" ? 0 : $total_room_type_adult,
                      'child' => $total_room_type_child == "" ? 0 : $total_room_type_child,
                      'price' =>  $room_rate[$room_type->room_type_id],
                      'coupon_code' =>  $coupon_code, 
                      'discount' =>  $discount 
                  ];

                  array_push($room_type_array, $room_details);
              }

              $booking_details['room_data'] = $room_type_array;
              $booking_details['other_information'] = null;

             
              if ($booking_details) {
                  return response()->json(array('status' => 1, 'message' => "Booking Details Fetched Successfully", 'data' => $booking_details));
              } else {
                  return response()->json(array('status' => 0, 'message' => "Booking Details Fetched Failed"));
              }
            }
    }catch(Exception $e){
      // echo $e->getLine()."@". $e->getFile();
      // echo $e->getMessage();exit;
      return response()->json(array('status' => 0, 'message' => "Booking Details Fetched Failed"));
      
    }
      
  }


}
