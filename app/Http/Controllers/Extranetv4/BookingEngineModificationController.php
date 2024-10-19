<?php
namespace App\Http\Controllers\Extranetv4;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Extranetv4\BookingEngineController;
use Illuminate\Support\Facades\Hash;
use Validator;
use App\Invoice;
use App\User;
Use App\HotelBooking;
use DB;
use App\Http\Controllers\Extranetv4\BookingEngineCancellationController;
use App\Http\Controllers\Controller;

class BookingEngineModificationController extends Controller
{
    protected $bookingEngineCancellationController;
    protected $bookingEngineController;
    public function __construct(BookingEngineCancellationController $bookingEngineCancellationController, BookingEngineController $bookingEngineController)
    {
       $this->bookingEngineCancellationController = $bookingEngineCancellationController;
       $this->bookingEngineController = $bookingEngineController;
    }

    public function beUserModify($user_id,$first_name,$last_name){
       
        $update_user = User::where('user_id',$user_id)->update(['first_name'=>$first_name,'last_name'  => $last_name]);
        return $update_user;
    }

    public function beModification(Request $request){
   
        $invoice =  new Invoice();
        $hotel_booking = new HotelBooking();
        $data = $request->all();
        $invoice_id = $data['invoice_id'];
        $booking_id = $data['booking_id'];
        $hotel_id = $data['hotel_id'];
        $first_name = $data['first_name'];
        $last_name = $data['last_name'];
        $modify_check_in = $data['check_in'];
        $modify_check_out = $data['check_out'];
        $cart_data = $data['cart'];
        $modified_date = date('Y-m-d H:i:s');
        
        //get invoice details
        $pre_invoice_details = Invoice::where('invoice_id',$invoice_id)->first();
        $user_id = $pre_invoice_details->user_id;
        $booking_status = $pre_invoice_details->booking_status;
        $ids_re_id = $pre_invoice_details->ids_re_id;
        $ktdc_id = $pre_invoice_details->ktdc_id;
        $body = $pre_invoice_details->invoice;
        $body = str_replace("BOOKING CONFIRMATION", "BOOKING MODIFICATION", $body);
        $hotel_booking_info = HotelBooking::where('invoice_id',$invoice_id)->first();
        $prv_dsp_check_in=date("jS M, Y", strtotime($hotel_booking_info->check_in));
        $prv_dsp_check_out=date("jS M, Y", strtotime($hotel_booking_info->check_out));
        $user_id = $pre_invoice_details->user_id;
        $guest_info = User::where('user_id',$user_id)->first();
        $prv_guest_details = $guest_info->first_name.' '.$guest_info->last_name;
        $dsp_check_in=date("jS M, Y", strtotime($modify_check_in));
        $dsp_check_out=date("jS M, Y", strtotime($modify_check_out));
        $guest_details = $first_name.' '.$last_name;
        $body = str_replace($prv_guest_details, $guest_details, $body);
        $body = str_replace($prv_dsp_check_in, $dsp_check_in, $body);
        $body = str_replace($prv_dsp_check_out, $dsp_check_out, $body);
        if($booking_status != 3){
            if(empty($cart_data) && ($first_name != '' || $last_name != '')){   // only update user details
                $update_user = $this->beUserModify($user_id,$first_name,$last_name);
                if($update_user){
                    $res = array('status'=>1,'message'=>'User Details Modified Successful');
                    return response()->json($res);
                }
            }else{   
                if($first_name != '' || $last_name != ''){      // update user & booking details
                    $this->beUserModify($user_id,$first_name,$last_name);
                }                                                
                $details['booking_id'] = $booking_id;
                $details['modify_status'] = 1;
                $url = 'https://be.bookingjini.com/cancell-booking';
                $ch = curl_init();
                curl_setopt( $ch, CURLOPT_URL, $url );
                curl_setopt( $ch, CURLOPT_POST, true );
                curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
                curl_setopt( $ch, CURLOPT_POSTFIELDS, $details);
                $status = curl_exec($ch);
                curl_close($ch);
                $resp = json_decode($status);
                
                if($resp){ 
                    $invoice_data = [
                        'total_amount' => $cart_data[0]['paid_amount'],
                        'paid_amount' =>  $cart_data[0]['paid_amount'],
                        'tax_amount' =>   $cart_data[0]['tax'][0]['gst_price'],
                        'discount_amount' => $cart_data[0]['discounted_price'],
                        'check_in_out' => '['.$modify_check_in.'-'.$modify_check_out.']',
                        'modify_date' => $modified_date,
                        'booking_status' => 1,
                        'modify_status' => 1,
                        'invoice' => $body
                    ];
                    $update_invoice = Invoice::where('invoice_id',$invoice_id)->update($invoice_data);
                    if($update_invoice){
                        $hotel_booking_data = [
                            'check_in' => $modify_check_in,
                            'check_out' => $modify_check_out,
                            'booking_status' => 1,
                            'modify_date' => $modified_date,
                            'modify_status' => 1
                        ];
                        $update_hotel_booking = HotelBooking::where('invoice_id',$invoice_id)->update($hotel_booking_data);
                    }
    
                    // call handle ids to push updated booking to IDS
                    if($update_hotel_booking){
                        $inv_data= 0;
                        if($ids_re_id > 0){
                            $inv_data=$this->bookingEngineController->handleIds($cart_data,$modify_check_in,$modify_check_out,$modified_date,$hotel_id,$user_id,'Modify');
                        }
                        if($ktdc_id > 0){
                            $inv_data=$this->bookingEngineController->handleKtdc($cart_data,$modify_check_in,$modify_check_out,$modified_date,$hotel_id,$user_id,'Modify');
                        }
                        //update modified ids_re_id in invoice table
                        $update_ids_id = Invoice::where('invoice_id',$invoice_id)->update(['ids_re_id'=>$inv_data]);    
                        $gems_res = $this->bookingEngineController->bookingModification($invoice_id,'modify',$request);
                        $get_invoice_data = Invoice::join('kernel.user_table','invoice_table.user_id','user_table.user_id')->select('invoice_id','total_amount','paid_amount','email_id','mobile')->where('invoice_id',$invoice_id)->first();
                        $b_invoice_id=base64_encode($get_invoice_data->invoice_id);

                        $invoice_hashData=$get_invoice_data->invoice_id.'|'.$get_invoice_data->total_amount.'|'.$get_invoice_data->paid_amount.'|'.$get_invoice_data->email_id.'|'.$get_invoice_data->mobile.'|'.$b_invoice_id;
                        $invoice_secureHash= hash('sha512', $invoice_hashData);

                        $res = array('status'=>1,'message'=>'Booking Details Modified Successfully','securehash'=>$invoice_secureHash);
                        return response()->json($res);
                    }
                }else{
                    $res = array('status'=>1,'message'=>'Booking Details Modification Fails');
                    return response()->json($res);
                }
            }
        }
        else if($booking_status == 3){
            $res = array('status'=>0,'message'=>'Booking Already Cancelled, Cannot Modify This Booking');
            return response()->json($res);
        }
    } 
}

?>