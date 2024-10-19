<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Validator;
use DB;
use App\Booking;
use App\Invoice;
use App\Http\Controllers\Controller;

/**
* Used for new report implementation of CRS
* @author Siri
* date 06/05/2021
*/
class CrsReportingController extends Controller
{
    public function getRoomNightsByDateRange(int $hotel_id,string $checkin,string $checkout,Request $request){
        $checkin=date('Y-m-d',strtotime($checkin));
        $checkout=date('Y-m-d',strtotime($checkout));
        $getCrsDate = DB::table('booking_engine.hotel_booking')->join('booking_engine.invoice_table','hotel_booking.invoice_id','=','invoice_table.invoice_id')
        ->select(DB::raw('SUM(DATEDIFF(hotel_booking.check_out,hotel_booking.check_in)) as nights'))->where('invoice_table.hotel_id',$hotel_id)
        ->where('hotel_booking.check_in','>=',$checkin)
        ->where('hotel_booking.check_in','<=',$checkout)
        ->where('invoice_table.booking_status',1)
        ->where('invoice_table.booking_source','CRS')
        ->get();
        $numberOfNights=array();
        foreach($getCrsDate as $key => $crs_details){
            $numberOfNights['CRS']=(int)$crs_details->nights;
        }
        if(sizeof($numberOfNights)>0){
            $resp=array('status'=>1,'message'=>'Number of nights fetched successfully','data'=>$numberOfNights);
            return response()->json($resp);
        }
        else{
            $numberOfNights['CRS']=0;
            $resp=array('status'=>0,'message'=>'Number of nights fetching fails','data'=>$numberOfNights);
            return response()->json($resp);
        }
    }
    public function totalRevenueOtaWise(int $hotel_id,$checkin,$checkout,Request $request){
        $checkin=date('Y-m-d',strtotime($checkin));
        $checkout=date('Y-m-d',strtotime($checkout));
        $getCrsTotalAmount=DB::table('booking_engine.hotel_booking')->join('booking_engine.invoice_table','hotel_booking.invoice_id','=','invoice_table.invoice_id')->select(DB::raw('SUM(invoice_table.total_amount) as be_amt'))->where('invoice_table.hotel_id',$hotel_id)->where('hotel_booking.check_in','>=',$checkin)->where('hotel_booking.check_in','<=',$checkout)
        ->where('invoice_table.booking_status',1)->where('invoice_table.booking_source','CRS')->get();
        // dd($getCrsTotalAmount);
        $totalAmount=array();
        foreach($getCrsTotalAmount as $details){
            $totalAmount['CRS']=round($details->be_amt);
        }
        if(sizeof($totalAmount)>0){
            $resp=array('status'=>1,'message'=>'Total amount fetched successfully','data'=>$totalAmount);
            return response()->json($resp);
        }
        else{
            $totalAmount['CRS']=0;
            $resp=array('status'=>0,'message'=>'Total amount fetching fails','data'=>$totalAmount);
            return response()->json($resp);
        }
    }
    public function numberOfBookings(int $hotel_id,string $checkin,string $checkout,Request $request){
        $checkin=date('Y-m-d',strtotime($checkin));
        $checkout=date('Y-m-d',strtotime($checkout));
        $getTotalCrsBookings=DB::table('booking_engine.hotel_booking')->join('booking_engine.invoice_table','hotel_booking.invoice_id','=','invoice_table.invoice_id')->select(DB::raw('count(invoice_table.invoice_id) as no_of_be_bookings'))->where('invoice_table.hotel_id',$hotel_id)->whereDate('hotel_booking.booking_date','>=',$checkin)->whereDate('hotel_booking.booking_date','<=',$checkout)
        ->where('invoice_table.booking_status',1)->where('booking_source','CRS')->get();
        $totalBookings=array();
        foreach($getTotalCrsBookings as $crs_details){
            $totalBookings['CRS']=round($crs_details->no_of_be_bookings);
        }
        if(sizeof($totalBookings)>0){
            $resp=array('status'=>1,'message'=>'Total bookings fetched successfully','data'=>$totalBookings);
            return response()->json($resp);
        }
        else{
            $totalBookings['CRS']=0;
            $resp=array('status'=>0,'message'=>'Total bookings fetching fails','data'=>$totalBookings);
            return response()->json($resp);
        }
    }
    public function averageStay(int $hotel_id,string $checkin,string $checkout,Request $request){
        $checkin=date('Y-m-d',strtotime($checkin));
        $checkout=date('Y-m-d',strtotime($checkout));
        $getCrsAverageStay=DB::table('booking_engine.hotel_booking')->join('booking_engine.invoice_table','hotel_booking.invoice_id','=','invoice_table.invoice_id')->select(DB::raw('count(invoice_table.invoice_id) as no_of_be_bookings'),DB::raw('SUM(DATEDIFF(hotel_booking.check_out,hotel_booking.check_in)) as be_nights'))->where('invoice_table.hotel_id',$hotel_id)->where('hotel_booking.check_in','>=',$checkin)->where('hotel_booking.check_in','<=',$checkout)
        ->where('invoice_table.booking_status',1)->where('booking_source','CRS')->get();
        $averageStay=array();
        foreach($getCrsAverageStay as $crs_details){
            if($crs_details->no_of_be_bookings != 0){
                $avgstay=$crs_details->be_nights/$crs_details->no_of_be_bookings;
                $averageStay['CRS']=(float)$avgstay;
            }
        }
        if(sizeof($averageStay)>0){
            $resp=array('status'=>1,'message'=>'Average stay fetched successfully','data'=>$averageStay);
            return response()->json($resp);
        }
        else{
            $averageStay['CRS']=0;
            $resp=array('status'=>0,'message'=>'Average stay fetching fails','data'=>$averageStay);
            return response()->json($resp);
        }
    }
    public function ratePlanPerformance(int $hotel_id,string $checkin,string $checkout,Request $request){
        $checkin=date('Y-m-d',strtotime($checkin));
        $checkout=date('Y-m-d',strtotime($checkout));
        $getRoomType = DB::table('booking_engine.invoice_table')->select(DB::raw('count(invoice_id) as room_no'),'room_type')->where('hotel_id',$hotel_id)->whereDate('booking_date','>=',$checkin)->whereDate('booking_date','<=',$checkout)
        ->where('booking_status',1)->where('booking_source','CRS')->groupBy('room_type')->get();
        $roomtype = '';
        $room_type_details = array();
        foreach($getRoomType as $room){
        $roomtype = substr($room->room_type,4);
        $roomtype = str_replace('"]','',$roomtype);
        $room_type_details[$roomtype] = $room->room_no;
        }
        if(sizeof($room_type_details)>0){
            $resp = array('status'=>1,'message'=>'room rate plan for be','data'=>$room_type_details);
        }
        else{
            $room_type_details['NA'] = 0;
            $resp = array('status'=>0,'message'=>'Fails','data'=>$room_type_details);
        }
        return response()->json($resp);
    }
}