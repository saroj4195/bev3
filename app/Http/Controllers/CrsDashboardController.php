<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Validator;
use DB;
use App\Invoice;
use App\HotelBooking;
use App\Visitors;
use App\CrsBooking;
use App\Http\Controllers\Controller;
/**
* This Controller is used for CRS Dashboard detais
*@author Siri Date- 19-04-2021
*/

class CrsDashboardController extends Controller{
    
    // Fetch crs earning date wise.
    public function selectInvoice(int $hotel_id,$from_date,$to_date,Request $request)
    {
        $invoiceselect=new Invoice();
        $from_date = date('Y-m-d',strtotime($from_date));
        $to_date = date('Y-m-d',strtotime($to_date));
        if($hotel_id)
        {
            $condition=array('invoice_table.hotel_id'=>$hotel_id,'invoice_table.booking_status'=>1,'invoice_table.booking_source'=>'CRS');
            try{
                $bookingAmount=Invoice::where($condition)
                ->join('hotel_booking','invoice_table.invoice_id','=','hotel_booking.invoice_id')
                ->whereDate('hotel_booking.check_in','>=',$from_date)
                ->whereDate('hotel_booking.check_out','<=',$to_date)
                ->sum('total_amount');
            }
            catch(Exception $e){
                $res = array('status'=>0,'message'=>$e->getMessage());
                return response()->json($res);
            }
            $bookingAmount = round($bookingAmount);
            if(sizeof($bookingAmount)>0)
            {
                $res=array('status'=>1,'message'=>'bookingAmount retrieve successfully','bookingAmount'=>$bookingAmount);
                return response()->json($res);
            }
            else{
                $res=array('status'=>1,'message'=>'bookingAmount retrieve fails');
                return response()->json($res);
            }
        }
        else{
            $res=array('status'=>1,'message'=>'bookingAmount retrieve fails');
                return response()->json($res);
        }
    }

    public function getRoomNightsByDateRange(int $hotel_id,string $checkin,string $checkout,Request $request){
        $checkin=date('Y-m-d',strtotime($checkin));
        $checkout=date('Y-m-d',strtotime($checkout));
        $getCrsRoomNights = CrsBooking::join('invoice_table','crs_booking.invoice_id','=','invoice_table.invoice_id')
        ->select(DB::raw('SUM(DATEDIFF(crs_booking.check_out,crs_booking.check_in)) as nights'))
        ->where('invoice_table.hotel_id',$hotel_id)
        ->where('crs_booking.check_in','>=',$checkin)
        ->where('crs_booking.check_in','<=',$checkout)
        ->where('invoice_table.booking_status',1)
        ->get();
        $numberOfNights=0;
        foreach($getCrsRoomNights as $key => $crs_details){
            $numberOfNights=$numberOfNights+(int)$crs_details->nights;
        }
        if($numberOfNights >0){
            $resp=array('status'=>1,'message'=>'Number of nights fetched successfully','data'=>$numberOfNights);
            return response()->json($resp);
        }
        else{
            $resp=array('status'=>0,'message'=>'Number of nights fetching fails');
            return response()->json($resp);
        }
    }

    public function averageStay(int $hotel_id,string $checkin,string $checkout,Request $request){
        $checkin=date('Y-m-d',strtotime($checkin));
        $checkout=date('Y-m-d',strtotime($checkout));
        $getCrsAverageStay=CrsBooking::join('invoice_table','crs_booking.invoice_id','=','invoice_table.invoice_id')->select(DB::raw('count(invoice_table.invoice_id) as no_of_crs_bookings'),DB::raw('SUM(DATEDIFF(crs_booking.check_out,crs_booking.check_in)) as crs_nights'))->where('invoice_table.hotel_id',$hotel_id)->where('crs_booking.check_in','>=',$checkin)->where('crs_booking.check_in','<=',$checkout)
        ->where('invoice_table.booking_status',1)->get();
        $no_of_booking = 0;
        $no_of_nights = 0;
        foreach($getCrsAverageStay as $crs_details){
            if($crs_details->no_of_crs_bookings != 0){
                $no_of_nights = $no_of_nights + $crs_details->crs_nights;
                $no_of_booking = $no_of_booking+$crs_details->no_of_crs_bookings;
            }
        }
        if($no_of_nights > 0 && $no_of_booking > 0){
            $resp=array('status'=>1,'message'=>'Average stay fetched successfully','no_of_nights'=>$no_of_nights,'no_of_booking'=>$no_of_booking);
            return response()->json($resp);
        }
        else{
            $resp=array('status'=>0,'message'=>'Average stay fetching fails');
            return response()->json($resp);
        }
    }

    public function ratePlanPerformance(int $hotel_id,string $checkin,string $checkout,Request $request){
        $checkin=date('Y-m-d',strtotime($checkin));
        $checkout=date('Y-m-d',strtotime($checkout));
        $getRoomType = Invoice::select(DB::raw('count(invoice_id) as room_no'),'room_type')->where('hotel_id',$hotel_id)->whereDate('booking_date','>=',$checkin)->whereDate('booking_date','<=',$checkout)
        ->where('booking_status',1)->where('booking_source','CRS')->groupBy('room_type')->orderBy('room_no','DESC')->limit(1)->get();
        $roomtype = '';
        foreach($getRoomType as $room){
           $roomtype = substr($room->room_type,4);
           $roomtype = str_replace('"]','',$roomtype);
        }
        if($roomtype){
            $resp = array('status'=>1,'message'=>'Room Rate Plan Performance Data Fetch Sucessful','data'=>$roomtype);
        }
        else{
            $resp = array('status'=>0,'message'=>'Room Rate Plan Performance Data Fetch Fails');
        }
        return response()->json($resp);
    }

    public function getCrsBookings(int $hotel_id,Request $request)
    {
        $today=date("Y-m-d");
        $HotelBookingdetails=Invoice::join('kernel.user_table','invoice_table.user_id','=','user_table.user_id')
                    ->join('hotel_booking','invoice_table.invoice_id','=','hotel_booking.invoice_id')
                    ->select('invoice_table.invoice_id','invoice_table.booking_date','check_in','total_amount','paid_amount','first_name','last_name','check_out')
                    ->where('invoice_table.booking_status',1)
                    ->where('check_in',$today)
                    ->where('booking_source','CRS')
                    ->where('invoice_table.hotel_id',$hotel_id)
                    ->get();

            if(sizeof($HotelBookingdetails)>0)
            {
                $res = array('status'=>1,'message'=>'Data retrieve successfully','data'=>$HotelBookingdetails);
                return response()->json($res);
            }
            else
            {
                $res = array('status'=>0,'message'=>"crs booking details retrieve fails");
                return response()->json($res);
            }
    }

    public function getCrsBookingsCheckOut(int $hotel_id,Request $request)
    {
        $today=date("Y-m-d");
        $HotelBookingdetails=Invoice::join('kernel.user_table','invoice_table.user_id','=','user_table.user_id')
            ->join('hotel_booking','invoice_table.invoice_id','=','hotel_booking.invoice_id')
            ->select('invoice_table.invoice_id','invoice_table.booking_date','check_in','total_amount','paid_amount','first_name','last_name','check_out')
            ->where('invoice_table.booking_status',1)
            ->where('check_out',$today)
            ->where('invoice_table.booking_status','CRS')
            ->where('invoice_table.hotel_id',$hotel_id)
            ->get();
            if(sizeof($HotelBookingdetails)>0)
            {
                $res = array('status'=>1,'message'=>'Data retrieve successfully','data'=>$HotelBookingdetails);
                return response()->json($res);
            }
            else
            {
                $res = array('status'=>0,'message'=>"hotel booking details retrieve fails");
                return response()->json($res);
            }
    }

    public function dashboardBookingDetails(int $hotel_id,$from_date,$to_date, Request $request)
    {
        $from_date  = date('Y-m-d',strtotime($from_date));
        $to_date    = date('Y-m-d',strtotime($to_date));
        $start_date = $from_date;
        $end_date   = $to_date;
        $beBooking  = DB::select('CALL booking_engine.crsBookingGraph(?,?,?)',["$hotel_id","$from_date","$to_date"]);
        $bookings = array();
        $bookings_data = array();
        if(count($beBooking)>0)
        {
            $i = 0;
            $check_date = array();
            while (strtotime($from_date) <= strtotime($to_date)) {
                foreach ($beBooking as $key=> $value) {
                    if(strtotime($from_date) == strtotime($value->index_date)){
                        $bookings[$i]["bookings"] = $value->bookings;
                        $bookings[$i]["index_date"] = date("d-M-Y",strtotime($value->index_date));
                        $check_date[]=$value->index_date;
                        $i++;
                    }
                }
                $from_date = date ("Y-m-d", strtotime("+1 days", strtotime($from_date)));
            }
              while (strtotime($start_date) <= strtotime($end_date)) {
                  foreach ($bookings as $value) {
                      if(isset($value["index_date"])){
                          if(!in_array($start_date,$check_date)){
                            $bookings_data["bookings"] =0;
                            $bookings_data["index_date"] = date("d-M-Y",strtotime($start_date));
                            $check_date[]=$start_date;
                            $bookings[]=$bookings_data;
                          }
                      }
                  }
                  $start_date = date ("Y-m-d", strtotime("+1 days", strtotime($start_date)));
              }
              usort($bookings, function($a, $b) {
                return $a['index_date'] <=> $b['index_date'];
            });
            $res=array("status"=>1,"message"=>"CRS booking details retrive sucessfully","crsBooking"=>$bookings);
            return response()->json($res);
        }
        else
        {
          $i = 0;
          while (strtotime($from_date) <= strtotime($to_date)) {
                $bookings[$i]["bookings"] = 0;
                $bookings[$i]["index_date"] = date("d-M-Y",strtotime($from_date));
                $from_date = date ("Y-m-d", strtotime("+1 days", strtotime($from_date)));
                $i++;
            }
            $res=array("status"=>0,"message"=>"CRS booking details retrive fails",'crsBooking'=>$bookings);
            return response()->json($res);
        }
    }
}
?>