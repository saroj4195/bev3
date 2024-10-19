<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Validator;
use App\OfflineBooking;
use App\User;
use App\Invoice;//class name from model
use App\Coupons;
use App\HotelInformation;
use App\CmOtaBooking;//class name from model
use App\CmOtaRoomTypeSynchronize;
use App\CmOtaRatePlanSynchronize;
use DB;

//create a new class OfflineBookingController
class BookingDetailsDownloadController extends Controller
{
    public function getSearchData($booking_data,Request $request)
    {
        $data=json_decode(urldecode ($booking_data));
        $from_date = date('Y-m-d',strtotime($data[0]->from_date));
        $to_date =  date('Y-m-d',strtotime($data[0]->to_date));
        $row=array();
        if($data[0]->status == 'be')
        {
            if($data[0]->date_status==2)
            {
                $bet_date='check_in';
            }
            else if($data[0]->date_status==1)
            {
                $bet_date='booking_date';
            }
            else
            {
                $bet_date='check_in';
            }
            $con='hotel_booking.'.$bet_date;
            $hotel_id = $data[0]->hotel_id;
             $result =  Invoice::join('hotel_booking','invoice_table.invoice_id','=','hotel_booking.invoice_id')->select('invoice_table.invoice_id','hotel_booking.rooms','hotel_booking.check_in','hotel_booking.check_out','invoice_table.hotel_name','invoice_table.room_type','invoice_table.total_amount','invoice_table.hotel_id','invoice_table.paid_amount','invoice_table.booking_date','invoice_table.agent_code','invoice_table.booking_source','invoice_table.booking_status','invoice_table.user_id')
             ->where('invoice_table.hotel_id',$hotel_id)
             ->whereBetween($con, array($from_date, $to_date))
             ->get();
             if(sizeof($result)>0)
            {
                 foreach($result as $rslt)
                {
                    $userdetails=DB::connection('bookingjini_kernel')->table('user_table')->select('first_name','last_name','email_id','mobile')->where('user_id',$rslt->user_id)->first();
                    $getCommision= HotelInformation::join('company_table','hotels_table.company_id','=','company_table.company_id')->select('company_table.commission')->where('hotel_id',$rslt->hotel_id)->first();
                    $commision_amount=$rslt->total_amount*$getCommision->commission/100;
                    $hotelier_amount=$rslt->total_amount-$commision_amount;
                    $unique_id=date('dmy',strtotime($rslt->booking_date)).$rslt->invoice_id;
                    if($userdetails){
                        $username=$userdetails->first_name.' '.$userdetails->last_name;
                        $email_id = $userdetails->email_id;
                        $mobile = $userdetails->mobile;
                    }
                    if($rslt->booking_status == 1){
                        $booking_status = 'Confirmed';
                    }
                    else if($rslt->booking_status == 3){
                        $booking_status = 'Cancelled';
                    }
                    else if($rslt->booking_status == 2){
                        continue;
                    }
                    $date1 = date_create($rslt->check_in);
                    $date2 = date_create($rslt->check_out);
                    $number_of_night=date_diff($date1,$date2);
                    $number_of_night=$number_of_night->d;
                    if($rslt->agent_code){
                        $get_coupon_name = Coupons::select('coupon_name')
                        ->where('hotel_id',$rslt->hotel_id)
                        ->where('coupon_code','=',$rslt->agent_code)
                        ->first();
                        if($get_coupon_name){
                            $coupon_name = $get_coupon_name->coupon_name;
                            $agent_name = $coupon_name;   
                        }
                        else{
                            $agent_name = $rslt->agent_code;
                        }
                    }
                    else{
                        $agent_name = $rslt->agent_code;
                    }
                    $booking_source = $rslt->booking_source;
                    $row[]=array('Reference No'=>$unique_id, 'Guest Name'=>$username, 'Email'=>$email_id, 'Mobile'=>$mobile,'Hotel Name'=>$rslt->hotel_name,'Booking Status'=>$booking_status,'Booking Date'=>$rslt->booking_date,'Checkin Date'=>$rslt->check_in,'Checkout Date'=>$rslt->check_out,'Room Type'=>$rslt->room_type,'Rooms'=>$rslt->rooms,'Total Nights'=>$number_of_night,'Total Amount'=>$rslt->total_amount,'Paid Amount'=>$rslt->paid_amount,'Comission Amount'=>$commision_amount,'Hotelier Amount'=>$hotelier_amount,'Agent Name'=>$agent_name,'Booking Source'=>$booking_source);
                 }
            }
             else{
                 echo "Sorry!No booking available.";
            }
        }
        if($data[0]->status == 'be')
        {
            if(sizeof($row)>0)
            {
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename=beBooking.csv');
                $output = fopen("php://output", "w");
                fputcsv($output, array('Reference No','Guest Name','Email','Mobile','Hotel Name','Booking Status','Booking Date','Checkin Date','Checkout Date','Room Type','Rooms','Total Nights','Total Amount','Paid Amount','Comission Amount','Hotelier Amount','Agent Name','Booking Source'));

                foreach($row as $data)
                {
                    fputcsv($output, $data);
                }
                fclose($output);
            }
        }
        else{
            if(sizeof($row)>0)
            {
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename=offlineBooking.csv');
                $output = fopen("php://output", "w");
                fputcsv($output, array('Reference No','Guest Name','Email','Mobile','Hotel Name','Booking Status','Booking Date','Checkin Date','Checkout Date','Room Type','Rooms','Total Nights','Total Amount','Paid Amount','Agent Name','Booking Source'));
                foreach($row as $data)
                {
                    fputcsv($output, $data);
                }
                fclose($output);
            }
        }
    }
}
