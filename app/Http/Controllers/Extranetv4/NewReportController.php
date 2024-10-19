<?php
namespace App\Http\Controllers\Extranetv4;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Validator;
use DB;
use App\Booking;
use App\OTABooking;
use App\OfflineBooking;
use App\Invoice;
use App\CmOtaDetails;
use App\Http\Controllers\Controller;

/**
 * Used for new report implementation
 * @author Ranjit kumar dash
 * date 22/05/2019
 */
class NewReportController extends Controller{
    /**
     * This function is used to get number of nights by ota wise
     */
    public function getRoomNightsByDateRange(int $hotel_id,string $checkin,string $checkout,Request $request){
        $checkin=date('Y-m-d',strtotime($checkin));
        $checkout=date('Y-m-d',strtotime($checkout));
        $getTotalNight=OTABooking::select(DB::raw('SUM(DATEDIFF(checkout_at,checkin_at)) as nights'), 'channel_name')->where('hotel_id',$hotel_id)->where('checkin_at','>=',$checkin)->where('checkin_at','<=',$checkout)
        ->where('confirm_status',1)->where('cancel_status',0)->groupBy('channel_name')->get();

        $getBeDate = Booking::join('invoice_table','hotel_booking.invoice_id','=','invoice_table.invoice_id')
        ->select(DB::raw('SUM(DATEDIFF(hotel_booking.check_out,hotel_booking.check_in)) as nights'))->where('invoice_table.hotel_id',$hotel_id)
        ->where('hotel_booking.check_in','>=',$checkin)
        ->where('hotel_booking.check_in','<=',$checkout)
        ->where('invoice_table.booking_status',1)
        ->get();
        $numberOfNights=array();
        foreach($getTotalNight as $details){
            $numberOfNights[$details->channel_name]=(int)$details->nights;
        }
        foreach($getBeDate as $key => $be_details){
            $numberOfNights['be']=(int)$be_details->nights;
        }
        if(sizeof($numberOfNights)>0){
            $resp=array('status'=>1,'message'=>'Number of nights fetched successfully','data'=>$numberOfNights);
            return response()->json($resp);
        }
        else{
            $resp=array('status'=>0,'message'=>'Number of nights fetching fails');
            return response()->json($resp);
        }
    }
    public function totalRevenueOtaWise(int $hotel_id,$checkin,$checkout,Request $request){
        $checkin=date('Y-m-d',strtotime($checkin));
        $checkout=date('Y-m-d',strtotime($checkout));
        $getTotalAmount=OTABooking::select(DB::raw('SUM(amount) as amt'), 'channel_name')->where('hotel_id',$hotel_id)->where('checkin_at','>=',$checkin)->where('checkout_at','<=',$checkout)
        ->where('confirm_status',1)->where('cancel_status',0)->groupBy('channel_name')->get();
        $getBeTotalAmount=Booking::join('invoice_table','hotel_booking.invoice_id','=','invoice_table.invoice_id')->select(DB::raw('SUM(invoice_table.total_amount) as be_amt'))->where('invoice_table.hotel_id',$hotel_id)->where('hotel_booking.check_in','>=',$checkin)->where('hotel_booking.check_out','<=',$checkout)
        ->where('invoice_table.booking_status',1)->get();
        $totalAmount=array();
        foreach($getTotalAmount as $details){
            $totalAmount[$details->channel_name]=round($details->amt);
        }
        foreach($getBeTotalAmount as $details){
            $totalAmount['BE']=round($details->be_amt);
        }
        if(sizeof($totalAmount)>0){
            $resp=array('status'=>1,'message'=>'Total amount fetched successfully','data'=>$totalAmount);
            return response()->json($resp);
        }
        else{
            $resp=array('status'=>0,'message'=>'Total amount fetching fails');
            return response()->json($resp);
        }
    }
    // public function averageRates(Request $request){
    //     $failure_message="Please provide appropriate information";
    //     $validator = Validator::make($request->all(),$this->rules,$this->messages);
    //     if ($validator->fails())
    //     {
    //         $res['errors']=$validator->errors();
    //         return response()->json($res);
    //     }
    //     $data=$request->all();
    //     $getTotalAmount=OTABooking::select(DB::raw('SUM(amount) as amt'), 'channel_name')->where('hotel_id',$hotel_id)->where('checkin_at','>=',$checkin)->where('checkin_at','<=',$checkout)
    //     ->where('confirm_status',1)->where('cancel_status',0)->groupBy('channel_name')->get();
    //     $totalAmount=array();
    //     foreach($getTotalAmount as $details){
    //         $totalAmount[$details->channel_name]=round($details->amt);
    //     }
    //     if(sizeof($totalAmount)>0){
    //         $resp=array('status'=>1,'message'=>'Total amount fetched successfully','data'=>$totalAmount);
    //         return response()->json($resp);
    //     }
    //     else{
    //         $resp=array('status'=>0,'message'=>'Total amount fetching fails');
    //         return response()->json($resp);
    //     }
    // }
    public function numberOfBookings(int $hotel_id,string $checkin,string $checkout,Request $request){
        $checkin=date('Y-m-d',strtotime($checkin));
        $checkout=date('Y-m-d',strtotime($checkout));
        $getTotalOtaBookings=OTABooking::select(DB::raw('count(id) as no_of_bookings'), 'channel_name')->where('hotel_id',$hotel_id)->whereDate('booking_date','>=',$checkin)->whereDate('booking_date','<=',$checkout)
        ->where('confirm_status',1)->where('cancel_status',0)->groupBy('channel_name')->get();
        $getTotalBeBookings=Booking::join('invoice_table','hotel_booking.invoice_id','=','invoice_table.invoice_id')->select(DB::raw('count(invoice_table.invoice_id) as no_of_be_bookings'))->where('invoice_table.hotel_id',$hotel_id)->whereDate('hotel_booking.booking_date','>=',$checkin)->whereDate('hotel_booking.booking_date','<=',$checkout)
        ->where('invoice_table.booking_status',1)->get();
        $totalBookings=array();

        foreach($getTotalOtaBookings as $details){
            $totalBookings[$details->channel_name]=round($details->no_of_bookings);
        }
        foreach($getTotalBeBookings as $be_details){
            $totalBookings['BE']=round($be_details->no_of_be_bookings);
        }
        if(sizeof($totalBookings)>0){
            $resp=array('status'=>1,'message'=>'Total bookings fetched successfully','data'=>$totalBookings);
            return response()->json($resp);
        }
        else{
            $resp=array('status'=>0,'message'=>'Total bookings fetching fails');
            return response()->json($resp);
        }
    }
    public function averageStay(int $hotel_id,string $checkin,string $checkout,Request $request){
        $checkin=date('Y-m-d',strtotime($checkin));
        $checkout=date('Y-m-d',strtotime($checkout));
        $getOtaAverageStay=OTABooking::select(DB::raw('count(id) as no_of_bookings'),DB::raw('SUM(DATEDIFF(checkout_at,checkin_at)) as nights'),'channel_name')->where('hotel_id',$hotel_id)->where('checkin_at','>=',$checkin)->where('checkin_at','<=',$checkout)
        ->where('confirm_status',1)->where('cancel_status',0)->groupBy('channel_name')->get();
        $getBeAverageStay=Booking::join('invoice_table','hotel_booking.invoice_id','=','invoice_table.invoice_id')->select(DB::raw('count(invoice_table.invoice_id) as no_of_be_bookings'),DB::raw('SUM(DATEDIFF(hotel_booking.check_out,hotel_booking.check_in)) as be_nights'))->where('invoice_table.hotel_id',$hotel_id)->where('hotel_booking.check_in','>=',$checkin)->where('hotel_booking.check_in','<=',$checkout)
        ->where('invoice_table.booking_status',1)->get();
        $averageStay=array();
        foreach($getOtaAverageStay as $details){
            if($details->no_of_bookings != 0){
                $avg_stay=$details->nights/$details->no_of_bookings;
                $averageStay[$details->channel_name]=(float)$avg_stay;
            }
        }
        foreach($getBeAverageStay as $be_details){
            if($be_details->no_of_be_bookings != 0){
                $avgstay=$be_details->be_nights/$be_details->no_of_be_bookings;
                $averageStay['BE']=(float)$avgstay;
            }
        }
        if(sizeof($averageStay)>0){
            $resp=array('status'=>1,'message'=>'Average stay fetched successfully','data'=>$averageStay);
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

        $getOtaRatePlanPerformance = OTABooking::select('rate_code')->where('hotel_id',$hotel_id)->whereDate('checkin_at','>=',$checkin)->whereDate('checkout_at','<=',$checkout)
        ->where('confirm_status',1)->where('cancel_status',0)->get();

        $ratePlanPerformance=array();
        $ratecode=array();
        if(sizeof($getOtaRatePlanPerformance)>0){
            foreach($getOtaRatePlanPerformance as $details){
                $rate_code=explode(',',$details->rate_code);
                foreach($rate_code as $exp){
                    $ratecode[]=$exp;
                }
            }
            $ratecode = array_count_values($ratecode);

            foreach($ratecode as $key => $val){
                if($key != " "){
                    $hotel_rateplan_code=DB::table('cm_ota_rate_plan_synchronize')->join('rate_plan_table','cm_ota_rate_plan_synchronize.hotel_rate_plan_id','=','rate_plan_table.rate_plan_id')->join('room_type_table','cm_ota_rate_plan_synchronize.hotel_room_type_id','=','room_type_table.room_type_id')->select('room_type_table.room_type','rate_plan_table.plan_type')->where('cm_ota_rate_plan_synchronize.ota_rate_plan_id',$key)->where('cm_ota_rate_plan_synchronize.hotel_id',$hotel_id)->first();
                    if($hotel_rateplan_code){
                        $ratePlanPerformance[$hotel_rateplan_code->room_type .'('.$hotel_rateplan_code->plan_type.')']=$val;
                    }
                }
            }
        }
        if(sizeof($ratePlanPerformance)>0){
            $resp=array('status'=>1,'message'=>'Total bookings fetched successfully','data'=>$ratePlanPerformance);
            return response()->json($resp);
        }
        else{
            $resp=array('status'=>0,'message'=>'Total bookings fetching fails');
            return response()->json($resp);
        }
    }
    // public function ratePerformance(int $hotel_id,string $checkin,string $checkout,Request $request){
    //     $checkin=date('Y-m-d',strtotime($checkin));
    //     $checkout=date('Y-m-d',strtotime($checkout));
    //     $getOtaRatePlanPerformance=OTABooking::select('*')->where('hotel_id',$hotel_id)->whereDate('booking_date','>=',$checkin)->whereDate('booking_date','<=',$checkout)
    //     ->where('confirm_status',1)->where('cancel_status',0)->get();
    //     $ratePlanPerformance=array();
    //     $ratecode=array();
    //     if(sizeof($getOtaRatePlanPerformance)>0){
    //         foreach($getOtaRatePlanPerformance as $details){
    //             $rate_code=explode(',',$details->rate_code);
    //             foreach($rate_code as $exp){
    //                 $ratecode[]=$exp;
    //             }
    //         }
    //         $ratecode = array_count_values($ratecode);

    //         foreach($ratecode as $key => $val){
    //             if($key != " "){
    //                 $hotel_rateplan_code=DB::table('cm_ota_rate_plan_synchronize')->join('rate_plan_table','cm_ota_rate_plan_synchronize.hotel_rate_plan_id','=','rate_plan_table.rate_plan_id')->join('room_type_table','cm_ota_rate_plan_synchronize.hotel_room_type_id','=','room_type_table.room_type_id')->select('room_type_table.room_type','rate_plan_table.plan_type')->where('cm_ota_rate_plan_synchronize.ota_rate_plan_id',$key)->where('cm_ota_rate_plan_synchronize.hotel_id',$hotel_id)->first();
    //                 if($hotel_rateplan_code){
    //                     $ratePlanPerformance[$hotel_rateplan_code->room_type .'('.$hotel_rateplan_code->plan_type.')']=$val;
    //                 }
    //             }
    //         }
    //     }
    //     if(sizeof($ratePlanPerformance)>0){
    //         $resp=array('status'=>1,'message'=>'Total bookings fetched successfully','data'=>$ratePlanPerformance);
    //         return response()->json($resp);
    //     }
    //     else{
    //         $resp=array('status'=>0,'message'=>'Total bookings fetching fails');
    //         return response()->json($resp);
    //     }
    // }
}