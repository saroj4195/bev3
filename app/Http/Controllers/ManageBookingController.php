<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Validator;
use App\UserCredential;
use App\ManageBooking;//class name from model
use App\ManageUserTable;//class name from model
use App\Invoice;
use DB;

//create a new class ManageBookingController
class ManageBookingController extends Controller
{
    public function GetAllBooking($hotel_id,$type,$from_date,$to_date, Request $request)
    {
        $bet_date="";
        $from_date=date('Y-m-d',strtotime($from_date));
        $to_date=date('Y-m-d',strtotime($to_date));
        if($type==2)
        {
            $bet_date='check_in';
        }
        else if($type==1)
        {
            $bet_date='booking_date';
        }
        else
        {
            $bet_date='check_in';
        }
        $cond='hotel_booking.'.$bet_date;
        $data= DB::table('invoice_table')
        ->join('kernel.user_table', 'invoice_table.user_id', '=', 'user_table.user_id')
        ->join('hotel_booking', 'invoice_table.invoice_id', '=', 'hotel_booking.invoice_id')
        ->join('kernel.company_table', 'user_table.company_id', '=', 'company_table.company_id')
        ->select('company_table.commission','user_table.first_name', 'user_table.last_name',
                    'user_table.email_id','user_table.address',
                    'user_table.mobile','user_table.user_id','invoice_table.room_type',
                    'invoice_table.total_amount','invoice_table.paid_amount',
                    'invoice_table.booking_date','invoice_table.check_in_out',
                    'invoice_table.invoice','invoice_table.booking_status','invoice_table.invoice_id','invoice_table.hotel_name','hotel_booking.hotel_booking_id','hotel_booking.rooms'
                )
                ->where('invoice_table.hotel_id', '=', $hotel_id)
                ->whereIn('invoice_table.booking_source', ['WEBSITE','google'])
                ->whereBetween($cond, array($from_date, $to_date))
                ->where('invoice_table.booking_status', '=', 1)
                ->orWhere(function($query) use ($hotel_id,$from_date,$to_date,$cond){
                    $query->where('invoice_table.hotel_id', '=', $hotel_id)
                    ->where('invoice_table.booking_source', '=','QUICKPAYMENT')
                    ->where('invoice_table.booking_status', '=', 1)
                    ->whereBetween($cond, array($from_date, $to_date));
                    })
                ->orWhere(function($query) use ($hotel_id,$from_date,$to_date,$cond){
                    $query->where('invoice_table.hotel_id', '=', $hotel_id)
                    ->whereIn('invoice_table.booking_source', ['WEBSITE','google'])
                    ->where('invoice_table.booking_status', '=', 3)
                    ->whereBetween($cond, array($from_date, $to_date));
                    })
                ->orWhere(function($query) use ($hotel_id,$from_date,$to_date,$cond){
                    $query->where('invoice_table.hotel_id', '=', $hotel_id)
                    ->whereIn('invoice_table.booking_source', ['WEBSITE','google'])
                    ->where('invoice_table.booking_status', '=', 5)
                    ->whereBetween($cond, array($from_date, $to_date));
                    })
                ->groupBy('invoice_table.invoice_id')
                ->orderBy('invoice_table.invoice_id','DESC')
                ->get();
        foreach($data as $data_up)
        {
          $data_up->invoice_id_details=$data_up->invoice_id;
          $c_amt=$data_up->total_amount*($data_up->commission/100);
          $c_amt=$c_amt+($c_amt*0.18);
          $h_amt=$data_up->total_amount-$c_amt;
          $booking_id     = date("dmy", strtotime($data_up->booking_date)).str_pad($data_up->invoice_id, 4, '0', STR_PAD_LEFT);
          $data_up->invoice           = str_replace("#####", $booking_id, $data_up->invoice);
          $data_up->commission_amount=round($c_amt,2);
          $data_up->hotelier_amount=round($h_amt,2);
          $data_up->invoice_id     =  $booking_id;
        }

        if(sizeof($data)>0)
        {
            $res=array("status"=>1,"message"=>"Booking data retrived successfully!","data"=>$data);
            return response()->json($res);
        }
        else
        {
            $res=array("status"=>0,"message"=>"Booking data retrival failed!");
            return response()->json($res);
        }
    }
    public function GetOneBooking($hotel_id,$type,$from_date,$to_date,$invoice_id, Request $request)
    {
        $bet_date="";
        $from_date=date('Y-m-d',strtotime($from_date));
        $to_date=date('Y-m-d',strtotime($to_date . ' +1 day'));
        if($type==2)
        {
            $bet_date='check_in';
        }
        else if($type==1)
        {
            $bet_date='booking_date';
        }
        else
        {
            $bet_date='check_in';
        }
        $cond='hotel_booking.'.$bet_date;
        $data= DB::table('invoice_table')
        ->join('kernel.user_table', 'invoice_table.user_id', '=', 'user_table.user_id')
        ->join('hotel_booking', 'invoice_table.invoice_id', '=', 'hotel_booking.invoice_id')
        ->join('kernel.company_table', 'user_table.company_id', '=', 'company_table.company_id')
        ->select('company_table.commission','user_table.first_name', 'user_table.last_name',
                    'user_table.email_id','user_table.address',
                    'user_table.mobile','user_table.user_id','invoice_table.room_type',
                    'invoice_table.total_amount','invoice_table.paid_amount',
                    'invoice_table.booking_date','invoice_table.check_in_out',
                    'invoice_table.invoice','invoice_table.booking_status','invoice_table.invoice_id','invoice_table.hotel_name','hotel_booking.hotel_booking_id','hotel_booking.rooms'
                )
                ->where('invoice_table.hotel_id', '=', $hotel_id)
                ->whereIn('invoice_table.booking_source', ['WEBSITE','google'])
                ->where('invoice_table.invoice_id',$invoice_id)
                ->whereBetween($cond, array($from_date, $to_date))
                ->where('invoice_table.booking_status', '=', 1)
                ->orWhere(function($query) use ($hotel_id,$from_date,$to_date,$cond,$invoice_id){
                    $query->where('invoice_table.hotel_id', '=', $hotel_id)
                    ->where('invoice_table.invoice_id',$invoice_id)
                    ->where('invoice_table.booking_status', '=', 3)
                    ->whereBetween($cond, array($from_date, $to_date));
                    })
                ->orWhere(function($query) use ($hotel_id,$from_date,$to_date,$cond,$invoice_id){
                    $query->where('invoice_table.hotel_id', '=', $hotel_id)
                    ->where('invoice_table.invoice_id',$invoice_id)
                    ->where('invoice_table.booking_status', '=', 5)
                    ->whereBetween($cond, array($from_date, $to_date));
                    })
                ->groupBy('invoice_table.invoice_id')
                ->orderBy('invoice_table.invoice_id','DESC')
                ->get();
        foreach($data as $data_up)
        {
          $data_up->invoice_id_details=$data_up->invoice_id;
          $c_amt=$data_up->total_amount*($data_up->commission/100);
          $c_amt=$c_amt+($c_amt*0.18);
          $h_amt=$data_up->total_amount-$c_amt;
          $booking_id     = date("dmy", strtotime($data_up->booking_date)).str_pad($data_up->invoice_id, 4, '0', STR_PAD_LEFT);
          $data_up->invoice           = str_replace("#####", $booking_id, $data_up->invoice);
          $data_up->commission_amount=round($c_amt,2);
          $data_up->hotelier_amount=round($h_amt,2);
          $data_up->invoice_id     =  $booking_id;
        }

        if(sizeof($data)>0)
        {
            $res=array("status"=>1,"message"=>"Booking data retrived successfully!","data"=>$data);
            return response()->json($res);
        }
        else
        {
            $res=array("status"=>0,"message"=>"Booking data retrival failed!");
            return response()->json($res);
        }
    }
    public function GetSpInvoiceBooking($hotel_id,$invoice_id){
        $booking_id = substr($invoice_id, 6);
        $booking_id = (int)$booking_id;
        $get_invoice = Invoice::select('invoice')
                        ->where("hotel_id",$hotel_id)
                        ->where("invoice_id",$booking_id)
                        ->first();
        if($get_invoice){
            return response()->json(array("status"=>1,"message"=>'Invoice Fetched','data'=>$get_invoice->invoice));
        }
        else{
            return response()->json(array("status"=>0,"message"=>'Invoice Fetch Fails'));
        }
    }
}
