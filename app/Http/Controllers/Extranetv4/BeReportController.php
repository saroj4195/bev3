<?php
namespace App\Http\Controllers\Extranetv4;
use App\ReportsQuestion;
use App\HotelInformation;
use Eloquent;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Visitors;


class BeReportController extends Controller
{
   // public function getHighestBookingAmount($month = null, $year = null)
   // {
   //     $highestBookingAmount = DB::select(DB::raw("SELECT MAX(CAST(total_amount as DECIMAL(9,2))) as HoighestBooking FROM invoice_table "));
   //    if($highestBookingAmount != [])
   //    {
   //      $msg=array('status' => 1,'message'=>'Highest booking amount found', 'highestBooking' => $highestBookingAmount);
   //      return response()->json($msg);
   //    }
   //    else
   //    {
   //      $msg=array('status' => 0,'message'=>'Highest booking amount not found');
   //      return response()->json($msg);
   //    }
   // }
   // public function bookingStates()
   // {
   //    $hotelDetail = DB::table('invoice_table')->leftjoin('kernel.hotels_table','invoice_table.hotel_id','=','hotels_table.hotel_id')->leftjoin('kernel.state_table','hotels_table.state_id','=','state_table.state_id')->select('hotels_table.hotel_id', 'hotels_table.hotel_name','state_table.state_name','invoice_table.total_amount','state_table.state_id','invoice_table.invoice_id')->where('hotels_table.hotel_id', '!=', null)->get();
      // $hotelDetail = DB::select(DB::raw("SELECT hotels_table.hotel_id, hotels_table.hotel_name, state_table.state_name FROM invoice_table LEFT JOIN hotels_table ON invoice_table.hotel_id = hotels_table.hotel_id LEFT JOIN state_table ON hotels_table.state_id = state_table.state_id WHERE hotels_table.hotel_id != null"));

  //     if($hotelDetail != [])
  //     {
  //       $msg=array('status' => 1,'message'=>'Highest booking amount found', 'hotelDetail' => $hotelDetail);
  //       return response()->json($msg);
  //     }
  //     else
  //     {
  //       $msg=array('status' => 0,'message'=>'Highest booking amount not found');
  //       return response()->json($msg);
  //     }
  // }
  // public function noBookingState()
  // {
  //   $stateName = DB::table('state_table')->leftjoin('hotels_table','invoice_table.hotel_id','=','hotels_table.hotel_id')->leftjoin('hotels_table','state_table.state_id','!=','hotels_table.state_id')->select('hotels_table.hotel_id', 'hotels_table.hotel_name','state_table.state_name','invoice_table.total_amount','state_table.state_id','invoice_table.invoice_id')->where('hotels_table.hotel_id', '!=', null)->get();
  //
  //   if($stateName != [])
  //     {
  //       $msg=array('status' => 1,'message'=>'No booking State found', 'hotelDetail' => $stateName);
  //       return response()->json($msg);
  //     }
  //     else
  //     {
  //       $msg=array('status' => 0,'message'=>'No booking State not found');
  //       return response()->json($msg);
  //     }
  // }
  // public function highestBookingHotel()
  // {
  //   $highestBookingHotel = DB::select(DB::raw("SELECT max(`no_of_booking`) AS `number`, `hotel_name` FROM `hotel_booking_count_for_report` GROUP BY `hotel_name` ORDER BY `number` DESC LIMIT 1"));
  //   if($highestBookingHotel != [])
  //     {
  //       $msg=array('status' => 1,'message'=>'Highest booking Hotel found', 'highestBooking' => $highestBookingHotel);
  //       return response()->json($msg);
  //     }
  //     else
  //     {
  //       $msg=array('status' => 0,'message'=>'Highest booking Hotel not found');
  //       return response()->json($msg);
  //     }
  // }
  // public function topFiveBookingHotel()
  // {
  //   $topFiveBookingHotel = DB::select(DB::raw("SELECT MAX(`no_of_booking`) AS `number`, `hotel_name` FROM `hotel_booking_count_for_report` GROUP BY `hotel_name` ORDER BY `number` DESC LIMIT 5"));
  //
  //   if($topFiveBookingHotel != [])
  //   {
  //     $hotelName = [];
  //     $noOfBookings = [];
  //     foreach($topFiveBookingHotel as $val)
  //     {
  //         array_push($hotelName, $val->hotel_name);
  //         array_push($noOfBookings, $val->number);
  //     }
  //     $msg=array('status' => 1,'message'=>'Top 5 Highest Booking Hotels Found', 'hotelName' => $hotelName, 'noOfBookings' => $noOfBookings);
  //     return response()->json($msg);
  //   }
  //   else
  //   {
  //     $msg=array('status' => 0,'message'=>'Top 5 Highest Booking Hotels not found');
  //     return response()->json($msg);
  //   }
  // }
  // public function lastFiveBookingHotel()
  // {
  //   $lastFiveBookingHotel = DB::select(DB::raw("SELECT MAX(`no_of_booking`) AS `number`, `hotel_name` FROM `hotel_booking_count_for_report` GROUP BY `hotel_name` ORDER BY `number` ASC LIMIT 5"));
  //
  //   if($lastFiveBookingHotel != [])
  //   {
  //     $hotelName = [];
  //     $noOfBookings = [];
  //     foreach($lastFiveBookingHotel as $val)
  //     {
  //         array_push($hotelName, $val->hotel_name);
  //         array_push($noOfBookings, $val->number);
  //     }
  //     $msg=array('status' => 1,'message'=>'Last 5 Highest Booking Hotels Found', 'hotelName' => $hotelName, 'noOfBookings' => $noOfBookings);
  //     return response()->json($msg);
  //   }
  //   else
  //   {
  //     $msg=array('status' => 0,'message'=>'Last 5 Highest Booking Hotels not found');
  //     return response()->json($msg);
  //   }
  // }
  // public function hotelUseingBE()
  //   {
  //       $allActiveHotels = DB::select(DB::raw("SELECT hotels_table.hotel_id, hotels_table.hotel_name FROM hotels_table LEFT JOIN billing_table ON hotels_table.company_id = billing_table.company_id WHERE product_name LIKE '%Booking Engine%'"));
  //
  //
  //       $format = [["name" => "Hotel Id", "field" => "hotel_id", "options" => ["filter" => true, "sort" => true]], ["name" => "Hotel Name", "field" => "hotel_name", "options" => ["filter" => true, "sort" => true]]];
  //       if($allActiveHotels != [])
  //       {
  //          $msg=array('status' => 1,'message'=>'All hotel Detail Found','hotelDeatails' => $allActiveHotels, 'format' => $format);
  //          return response()->json($msg);
  //       }
  //       else
  //       {
  //          $msg=array('status' => 0,'message'=>'Hotels Detail not Found');
  //          return response()->json($msg);
  //       }
  //
  //   }
  //   public function hotelNotUseingBE()
  //   {
  //       $allActiveHotels = DB::select(DB::raw("SELECT hotels_table.hotel_id, hotels_table.hotel_name FROM hotels_table LEFT JOIN billing_table ON hotels_table.company_id = billing_table.company_id WHERE product_name NOT LIKE '%Booking Engine%'"));
  //
  //
  //       $format = [["name" => "Hotel Id", "field" => "hotel_id", "options" => ["filter" => true, "sort" => true]], ["name" => "Hotel Name", "field" => "hotel_name", "options" => ["filter" => true, "sort" => true]]];
  //       if($allActiveHotels != [])
  //       {
  //          $msg=array('status' => 1,'message'=>'All hotel Detail Found','hotelDeatails' => $allActiveHotels, 'format' => $format);
  //          return response()->json($msg);
  //       }
  //       else
  //       {
  //          $msg=array('status' => 0,'message'=>'Hotels Detail not Found');
  //          return response()->json($msg);
  //       }
  //
  //   }
  //   public function maxBookingMonthWise($year)
  //   {
  //       $totalHotelMonthWise = DB::select(DB::raw("SELECT MAX(CAST(total_amount AS DECIMAL(10,2))) as maxAmount, MONTH(`created_at`) as monthName FROM invoice_table WHERE YEAR(`created_at`) = 2019 GROUP BY MONTH(`created_at`)"));
  //     $maxAmount = [];
  //     $monthName = [];
  //
  //     foreach($totalHotelMonthWise as $total)
  //     {
  //        array_push($maxAmount, $total->maxAmount);
  //        array_push($monthName, $total->monthName);
  //     }
  //     if($totalHotelMonthWise != [])
  //     {
  //        $msg=array('status' => 1,'message'=>'Monthly avarage new hotels','maxAmount' => $maxAmount, 'monthName' => $monthName);
  //        return response()->json($msg);
  //     }
  //     else
  //     {
  //        $msg=array('status' => 0,'message'=>'No hotels Found');
  //        return response()->json($msg);
  //     }
  //   }
  //   public function avgBookingMonthWise($year)
  //   {
  //       $totalHotelMonthWise = DB::select(DB::raw("SELECT AVG(CAST(total_amount AS DECIMAL(10,2))) as avgAmount, MONTH(`created_at`) as monthName FROM invoice_table WHERE YEAR(`created_at`) = 2019 GROUP BY MONTH(`created_at`)"));
  //     $avgAmount = [];
  //     $year = [];
  //
  //     foreach($totalHotelMonthWise as $total)
  //     {
  //        array_push($avgAmount, $total->avgAmount);
  //        array_push($year, $total->monthName);
  //     }
  //     if($totalHotelMonthWise != [])
  //     {
  //        $msg=array('status' => 1,'message'=>'Monthly avarage new hotels','maxAmount' => $avgAmount, 'stateName' => $year);
  //        return response()->json($msg);
  //     }
  //     else
  //     {
  //        $msg=array('status' => 0,'message'=>'No hotels Found');
  //        return response()->json($msg);
  //     }
  //   }
  //   public function totalBookingTillDate($year = null)
  //   {
  //       $totalHotelMonthWise = DB::select(DB::raw("SELECT SUM(CAST(total_booking_amount AS DECIMAL(10,2))) as totalAmount FROM hotel_booking_count_for_report "));
  //       //   $totalHotel = [];
  //       //   $year = [];
  //
  //       //   foreach($totalHotelMonthWise as $total)
  //       //   {
  //       //      array_push($totalHotel, $total->totalAmount);
  //       //     //  array_push($year, $total->monthName);
  //       //   }
  //     if($totalHotelMonthWise != [])
  //     {
  //        $msg=array('status' => 1,'message'=>'Monthly avarage new hotels','noOfHotels' => $totalHotelMonthWise);
  //        return response()->json($msg);
  //     }
  //     else
  //     {
  //        $msg=array('status' => 0,'message'=>'No hotels Found');
  //        return response()->json($msg);
  //     }
  //   }
  //   public function topFiveBookingState($country = null)
  // {
  //   $topFiveBookingHotel = DB::select(DB::raw("SELECT COUNT(`invoice_id`) AS `number`, state_table.state_name FROM `invoice_table` left join `hotels_table` ON invoice_table.hotel_id = hotels_table.hotel_id left join `state_table` on hotels_table.state_id  = state_table.state_id WHERE hotels_table.country_id = $country GROUP BY hotels_table.state_id ORDER BY `number` DESC LIMIT 5"));
  //
  //   if($topFiveBookingHotel != [])
  //   {
  //     $state_name = [];
  //     $noOfBookings = [];
  //     foreach($topFiveBookingHotel as $val)
  //     {
  //         array_push($state_name, $val->state_name);
  //         array_push($noOfBookings, $val->number);
  //     }
  //     $msg=array('status' => 1,'message'=>'Top 5 Highest Booking Hotels Found', 'state_name' => $state_name, 'noOfBookings' => $noOfBookings);
  //     return response()->json($msg);
  //   }
  //   else
  //   {
  //     $msg=array('status' => 0,'message'=>'Top 5 Highest Booking Hotels not found');
  //     return response()->json($msg);
  //   }
  // }
  // public function lastFiveBookingState($country = null)
  // {
  //   $topFiveBookingHotel = DB::select(DB::raw("SELECT COUNT(`invoice_id`) AS `number`, state_table.state_name FROM `invoice_table` left join `hotels_table` ON invoice_table.hotel_id = hotels_table.hotel_id left join `state_table` on hotels_table.state_id  = state_table.state_id WHERE hotels_table.country_id = $country GROUP BY hotels_table.state_id ORDER BY `number` ASC LIMIT 5"));
  //
  //   if($topFiveBookingHotel != [])
  //   {
  //     $state_name = [];
  //     $noOfBookings = [];
  //     foreach($topFiveBookingHotel as $val)
  //     {
  //         array_push($state_name, $val->state_name);
  //         array_push($noOfBookings, $val->number);
  //     }
  //     $msg=array('status' => 1,'message'=>'Last 5 Highest Booking Hotels Found', 'state_name' => $state_name, 'noOfBookings' => $noOfBookings);
  //     return response()->json($msg);
  //   }
  //   else
  //   {
  //     $msg=array('status' => 0,'message'=>'Last 5 Highest Booking Hotels not found');
  //     return response()->json($msg);
  //   }
  // }
  // public function topFiveBookingAmountHotel()
  // {
  //   $topFiveBookingHotel = DB::select(DB::raw("SELECT MAX(`total_booking_amount`) AS `number`, `hotel_name` FROM `hotel_booking_count_for_report` GROUP BY `hotel_name` ORDER BY `number` DESC LIMIT 5"));
  //
  //   if($topFiveBookingHotel != [])
  //   {
  //     $hotelName = [];
  //     $noOfBookings = [];
  //     foreach($topFiveBookingHotel as $val)
  //     {
  //         array_push($hotelName, $val->hotel_name);
  //         array_push($noOfBookings, $val->number);
  //     }
  //     $msg=array('status' => 1,'message'=>'Top 5 Highest Booking Hotels Found', 'hotelName' => $hotelName, 'bookingAmount' => $noOfBookings);
  //     return response()->json($msg);
  //   }
  //   else
  //   {
  //     $msg=array('status' => 0,'message'=>'Top 5 Highest Booking Amount Hotels not found');
  //     return response()->json($msg);
  //   }
  // }
  // public function lastFiveBookingAmountHotel()
  // {
  //   $lastFiveBookingHotel = DB::select(DB::raw("SELECT MAX(`total_booking_amount`) AS `number`, `hotel_name` FROM `hotel_booking_count_for_report` GROUP BY `hotel_name` ORDER BY `number` ASC LIMIT 5"));
  //
  //   if($lastFiveBookingHotel != [])
  //   {
  //     $hotelName = [];
  //     $noOfBookings = [];
  //     foreach($lastFiveBookingHotel as $val)
  //     {
  //         array_push($hotelName, $val->hotel_name);
  //         array_push($noOfBookings, $val->number);
  //     }
  //     $msg=array('status' => 1,'message'=>'Last 5 Highest Booking Amount Hotels Found', 'hotelName' => $hotelName, 'bookingAmount' => $noOfBookings);
  //     return response()->json($msg);
  //   }
  //   else
  //   {
  //     $msg=array('status' => 0,'message'=>'Last 5 Highest Booking Hotels not found');
  //     return response()->json($msg);
  //   }
  // }
  public function totalBeBooking($from_date, $to_date, $hotel_id, $question_id)
  {
    $beBookings = DB::select(DB::raw("SELECT count(invoice_table.hotel_id) as noOfBooking, SUM(CAST(`total_amount` AS DECIMAL(10,2))) as totalAmount
    FROM invoice_table WHERE invoice_table.booking_status = 1 AND date(booking_date) between '$from_date' AND '$to_date' AND hotel_id = '$hotel_id' GROUP BY invoice_table.hotel_id"));

      $totalBooking = [];
      $bookingAmount = [];
      foreach($beBookings as $total)
      {
        if($total != null)
        {
           array_push($totalBooking, $total->noOfBooking);
           array_push($bookingAmount, $total->totalAmount);
        }
      }
      if($beBookings != 0)
      {
         $msg=array('status' => 1,'message'=> 'Be Bookings','numberOfBooking' => $totalBooking,'totalBookingAmount'=> $bookingAmount);
         return response()->json($msg);
      }
      else
      {
         $msg=array('status' => 0,'message'=>'No Data Found');
         return response()->json($msg);
      }
  }
  public function noOfLastSevenDaysBEBookings($hotel_id)
  {
    $from_date=date('Y-m-d',strtotime(' - 7 days'));
    $to_date=date('Y-m-d');
    // $hotel_id=1698;
    $beBookings = DB::select(DB::raw("SELECT count(invoice_table.hotel_id) as noOfBooking, SUM(CAST(`total_amount` AS DECIMAL(10,2))) as totalAmount FROM invoice_table WHERE invoice_table.booking_status = 1 AND date(booking_date) between '$from_date' AND '$to_date' AND hotel_id = '$hotel_id' GROUP BY invoice_table.hotel_id"));
    if($beBookings){
      $msg=array('status' => 1, 'bookings' => $beBookings);
      return response()->json($msg);
    }
    else{
      $msg=array('status' => 0);
      return response()->json($msg);
    }

  }
  public function paymentgetwayList($hotel_id)
  {
    $company=DB::select(DB::raw("SELECT kernel.company_table.* from kernel.company_table,kernel.hotels_table where company_table.company_id=hotels_table.company_id and hotels_table.hotel_id=".$hotel_id));
    $paymentgetwayList=DB::select(DB::raw("SELECT * from payment_gateway_details where company_id=".$company[0]->company_id));
    $msg=array('status' => 1, 'paymentgetwayList' => $paymentgetwayList);
    return response()->json($msg);

  }
  public function getPaymentGetwayList()
  {
  	$list=DB::connection('be')->table('payment_gateway_details')->get();
  	if($list){
  		$msg=array('status' => 1, 'list' => $list);
  	    return response()->json($msg);
  	}
  	else{
  		$msg=array('status' => 0);
  	    return response()->json($msg);	
  	}  	
  }
  public function downloadCommissionBooking(Request $request)
  {
    $data=$request->all();
    // print_r($data);
    $companyData=DB::select(DB::raw("SELECT company_table.*,company_profile.gst_no from kernel.company_table left join kernel.company_profile on company_table.company_id=company_profile.company_id  where company_table.company_id=(SELECT company_id from kernel.hotels_table where hotel_id='".$data['hotel_id']."')"));
    // print_r($companyData);
    $commission=$companyData[0]->commission;
    $gst_no=$companyData[0]->gst_no;
    $bookingData=DB::select(DB::raw("SELECT invoice_table.invoice_id,invoice_table.hotel_id,invoice_table.hotel_name,invoice_table.ref_no,invoice_table.total_amount,invoice_table.paid_amount,invoice_table.tax_amount,invoice_table.tax_amount,invoice_table.discount_amount,invoice_table.check_in_out,invoice_table.booking_date,online_transaction_details.payment_mode,online_transaction_details.transaction_id,online_transaction_details.payment_id from invoice_table left join online_transaction_details on invoice_table.invoice_id=online_transaction_details.invoice_id where hotel_id='".$data['hotel_id']."' and CAST(`booking_date` as DATE) between '".$data['from_date']."' and '".$data['to_date']."' and booking_status=1"));
    // print_r($bookingData);
    $table="<table>
    <tr>
    <th>Booking Date</th>
    <th>Booking Id</th>
    <th>Payment Gateway</th>
    <th>Payment Gateway ID</th>
    <th>Check In Date</th>
    <th>Hotel ID</th>
    <th>GSTIN</th>
    <th>Hotel Name</th>
    <th>BOOKING AMOUNT with TAX</th>
    <th>BOOKING AMOUNT without TAX</th>
    <th>AMOUNT RECEIVED</th>
    <th>Amount Payable</th>
    <th>Comm %</th>
    <th>Comm Amount</th>
    <th>GST on Comm</th>
    <th>Total Comm with GST</th>
    <th>TCS Amount @1% on amount Payable</th>
    <th>Final Net Payable</th>
    <th>Status</th>
    <th>Payment Date</th>
    </tr>";
    foreach ($bookingData as $key => $booking) {
      $withouttax=$booking->total_amount-$booking->tax_amount;
      $commission_amount=$withouttax*($commission/100);
      $commission_gst=$commission_amount*(18/100);
      $total_commission_with_gst=$commission_amount+$commission_gst;
      $amount_payable=$booking->paid_amount-$total_commission_with_gst;
      $tcs_amount=$amount_payable*(1/100);
      $final_net_payable=$amount_payable-$tcs_amount;
      // $checkin_out=json_decode($booking->check_in_out,true);
      // print_r($checkin_out);
      $tr="<tr>
      <td>".date('d/m/Y',strtotime($booking->booking_date))."</td>
      <td>101212".$booking->invoice_id."</td>
      <td>Pay U</td>
      <td>".$booking->payment_id."</td>
      <td>".substr($booking->check_in_out,1,10)."</td>
      <td>100".$booking->hotel_id."</td>
      <td>".$gst_no."</td>
      <td>".$booking->hotel_name."</td>
      <td>".$booking->total_amount."</td>
      <td>".($booking->total_amount-$booking->tax_amount)."</td>
      <td>".$booking->paid_amount."</td>
      <td>".$amount_payable."</td>
      <td>".$commission."%</td>
      <td>".$commission_amount."</td>
      <td>".$commission_gst."</td>
      <td>".$total_commission_with_gst."</td>
      <td>".$tcs_amount."</td>
      <td>".$final_net_payable."</td>
      <td></td>
      <td></td>
      </tr>";
      $table=$table.$tr;
    }
    $table=$table."</table>";
    header("Content-type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=commission.xlsx");
    echo $table;
    // print_r($bookingData);  
  }
  public function getPriceWiseHotel(Request $request){
    $data=$request->all();
    $hotelList=DB::table('rate_plan_log_table')->select('hotel_id')->where('bar_price','>=',$data['min_price'])->where('bar_price','<=',$data['max_price'])->where('from_date','>=',date('Y-m-d',strtotime(str_replace("/", "-",$data['from_date']))))->where('to_date','<=',date('Y-m-d',strtotime(str_replace("/", "-",$data['to_date']))))->groupBY('hotel_id')->paginate(25);
    if($hotelList){
      $response = array('status' =>1, 'message'=>'Hotel list is retrived','hotelList'=>$hotelList);
      return response()->json($response);
    }
    else{
      $response = array('status' =>0, 'message'=>'No Hotel','sql'=>$sql);
      return response()->json($response); 
    }
  }
  public function uniqueBEVisitors($company_id, $duration, $date_from, $date_to)
  {
    $today_date = date('Y-m-d');
    $from_date = '';
    $to_date = '';
    if($duration == 'CUSTOM')
    {
        $from_date = $date_from;
        $to_date = $date_to;
    }
    else if($duration == 'TODAY')
    {
        $from_date = $today_date;
        $to_date = $today_date;
    }
    else if($duration == 'MTD')
    {
        $cur_month = date('m',strtotime($today_date));
        $cur_year = date('Y',strtotime($today_date));
        $from_date = "$cur_year-$cur_month-01";
        $to_date = $today_date;
    }
    else if($duration == 'YTD')
    {
        $cur_month = date('m',strtotime($today_date));
        if($cur_month <= 3)
        {
            $fin_year = date('Y',strtotime($today_date)) - 1;
            $from_date = "$fin_year-04-01";
            $to_date = $today_date;
        }
        else 
        {
            $fin_year = date('Y',strtotime($today_date));
            $from_date = "$fin_year-04-01";
            $to_date = $today_date;
        }
    }
    $visitor_count = Visitors::whereBetween('visitor_date',[$from_date, $to_date])->where('company_id',$company_id)->distinct('visitor_ip')->count();
    $res = array('status'=>1,'message'=>'Data retrieved','visitor_count'=>$visitor_count);
    return response()->json($res);
  }
  
}
