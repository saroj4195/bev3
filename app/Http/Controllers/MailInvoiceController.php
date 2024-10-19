<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Validator;
use DB;
use App\Invoice;
use App\ImageTable;
use App\CompanyDetails;
use App\HotelInformation;
use Illuminate\Support\Facades\Mail;
class MailInvoiceController extends Controller
{
        private $m_rules=array(
                'invoice_id'=>'required | numeric',
        'paid_amount'=>'required | numeric',
                'round_off'=>'required'
        );
        private $m_messages=[
        'invoice_id.required'=>'Invoice id should not be empty',
        'paid_amount.required'=>'Paid amount should not be empty',
        'round_off.required'=>'Round off amount status is required'
        ];
        /**
        * Method to get the Booking tries only for partial payment
        * @params hotel id and Request object
        * @return Boooking tries array with success status else status with error message
        * @author Ranjit
        * * */
        public function getInvoiceDetails(int $hotel_id,Request $request){
                $today=date('Y-m-d');
                $invoices=Invoice::
                join('kernel.user_table as user_table','invoice_table.user_id','=','user_table.user_id')
                ->join('hotel_booking','invoice_table.invoice_id','=','hotel_booking.invoice_id')
                ->select('invoice_table.invoice_id','invoice_table.room_type','invoice_table.total_amount','invoice_table.paid_amount','invoice_table.user_id','invoice_table.paid_amount','invoice_table.check_in_out','invoice_table.booking_date','invoice_table.booking_status','user_table.first_name','user_table.last_name','user_table.email_id','user_table.address','user_table.mobile','hotel_booking.rooms','hotel_booking.check_in','hotel_booking.check_out')
                ->where('invoice_table.hotel_id',$hotel_id)
                ->where('invoice_table.booking_status',2)
                ->distinct('invoice_id')->orderBy('invoice_id','DESC')->get();
                $get_company_id = HotelInformation::select('company_id')->where('hotel_id',$hotel_id)->first();
                $payment_gateway_info = DB::table('paymentgateway_details')
                ->select('provider_name')
                ->where('hotel_id', '=', $hotel_id)
                ->where('is_active', '=', 1)
                ->first();
                if(!$payment_gateway_info){
                    $payment_gateway_info=DB::table('payment_gateway_details')
                    ->select('provider_name')
                    ->where('company_id', '=', $get_company_id->company_id)
                    ->first();
                }
                
                if($payment_gateway_info){
                        $provider_name = $payment_gateway_info->provider_name;
                        if($provider_name == 'hdfc_payu'){
                                $is_actionable = 1;
                        }
                        else{
                                $is_actionable = 0;
                        }
                }
                else{
                        $provider_name = 'Payu';
                        $is_actionable = 1;
                }
                if(sizeof($invoices)>0){
                        foreach($invoices as $key => $inv){
                                $booking_date = date('dmy', strtotime($inv->booking_date));
                                $booking_id = $booking_date . $inv->invoice_id;
                                $invoices[$key]['booking_id'] = $booking_id;
                                $invoices[$key]['is_actionable'] = $is_actionable;
                                $invoices[$key]['provider_name'] = $provider_name;
                                if($inv->check_in >= $today){
                                        $inv->mail_inv_status = 1;
                                }
                                else{
                                     $inv->mail_inv_status = 0;   
                                }
                        }
                        $res=array('status'=>1,'message'=>'details retrive sucessfully','details'=>$invoices);
                        return response()->json($res);
                }
                else{
                        $res=array('status'=>0,'message'=>'details retrive fails');
                        return response()->json($res);
                }
        }
        /**
        * Method to send the partial payment mail invoice
        * @params hotel id and Request object
        * @return success status else status with error message
        * @author Ranjit
        * * */
        public function sendInvoiceMail(int $hotel_id,Request $request){
                $today=date('Y-m-d');
                $failure_message='details retrive fails due to data temporing';
                $validator=Validator::make($request->all(),$this->m_rules,$this->m_messages);
                if($validator->fails()){
                        return response()->json(array('status'=>0,'message'=>$failure_message,'error'=>$validator->errors()));
                }
                $data=$request->all();
                $invoice_details=Invoice::select('invoice_table.total_amount','invoice_table.paid_amount','invoice_table.invoice')
                ->where('invoice_table.hotel_id',$hotel_id)->where('invoice_table.booking_status',2)->where('invoice_table.invoice_id',$data['invoice_id'])->first();
                if(round($data['paid_amount']) < 500 || round($data['paid_amount']) >= round($invoice_details->total_amount)){
                $res=array('status'=>0,"message"=>'Mail invoice sending failed','error'=>array("Paid amount should be less then total amount and more than 500"));
                return response()->json($res);
                }
                $due_amount=$invoice_details->total_amount-$invoice_details->paid_amount;
                $due_amount=number_format((float)$due_amount, 2, '.', '');
                $changed_due_amount=$invoice_details->total_amount-$data['paid_amount'];
                $a = '<span id="pd_amt">'.$invoice_details->paid_amount.'</span>';
                $b = '<span id="pd_amt">'.number_format((float)$data['paid_amount'], 2, '.', '').'</span>';
                $c = '<span id="du_amt">'.$due_amount.'</span>';
                $d = '<span id="du_amt">'.number_format((float)$changed_due_amount, 2, '.', '').'</span>';
                $e = '<span id="du_amt"><strike>'.number_format((float)$changed_due_amount, 2, '.', '').'</strike> 0.00</span>';

                $invoice = str_replace($a, $b, $invoice_details->invoice);
                if($data['round_off']){
                $invoice                = str_replace($c, $e, $invoice);
                }
                else{
                $invoice                = str_replace($c, $d, $invoice);
                }
                
                $save=DB::table('invoice_table')
                        ->where('invoice_id',$data['invoice_id'])
                ->update(array('invoice'=>$invoice,'paid_amount'=>number_format((float)$data['paid_amount'], 2, '.', '')));
                        $details=Invoice::
                join('kernel.user_table as user_table','invoice_table.user_id','=','user_table.user_id')
                        ->join('hotel_booking','invoice_table.invoice_id','=','hotel_booking.invoice_id')
                ->select('invoice_table.hotel_name','invoice_table.room_type','invoice_table.total_amount','invoice_table.user_id','invoice_table.paid_amount','invoice_table.check_in_out','invoice_table.booking_date','invoice_table.booking_status','user_table.first_name','user_table.last_name','user_table.email_id','user_table.address','user_table.mobile','hotel_booking.rooms','hotel_booking.check_in','hotel_booking.check_out')
                        ->where('invoice_table.hotel_id',$hotel_id)
                ->where('invoice_table.booking_status',2)
                        ->where('invoice_table.invoice_id',$data['invoice_id'])
                ->where('hotel_booking.check_in','>=',$today)->first();

                $hoteldetails= DB::connection('bookingjini_kernel')->table('hotels_table')->select('hotels_table.hotel_address','hotels_table.mobile','hotels_table.exterior_image','hotels_table.email_id','hotels_table.company_id')->where('hotel_id',$hotel_id)->first();
                $image_id=explode(',', $hoteldetails->exterior_image);
                $images=DB::connection('bookingjini_kernel')->table('image_table')->select('image_name')->where('image_id',$image_id[0])->where('hotel_id',$hotel_id)->first();
                if($images){
                $hotel_image=$images->image_name;
                }
                else{
                $hotel_image="";
                }
                $email_id=explode(',',$hoteldetails->email_id);
                if(is_array($email_id)){
                        $hoteldetails->email_id=$email_id[0];
                }
                $mobile=explode(',',$hoteldetails->mobile);
                if(is_array($mobile)){
                        $hoteldetails->mobile=$mobile[0];
                }
                $companyDetails=DB::connection('bookingjini_kernel')->table('company_table')
                ->select('subdomain_name')
                ->where('company_id',$hoteldetails->company_id)->first();
                $b_invoice_id = base64_encode($data['invoice_id']);
                $email=$details->email_id;
                $name=$details->first_name." ".$details->last_name;

                $invoice_hashData=$data['invoice_id'].'|'.$details->total_amount.'|'.number_format((float)$data['paid_amount'], 2, '.', '').'|'.$email.'|'.$details->mobile.'|'.$b_invoice_id;
                $invoice_secureHash= hash('sha512', $invoice_hashData);
                if(strpos($companyDetails->subdomain_name,'bookingjini.com')){
                        $secureHash='https://be.bookingjini.com/payment/'.base64_encode($data['invoice_id']).'/'.$invoice_secureHash;
                }
                else{
                        $secureHash=$companyDetails->subdomain_name.'/payment/'.base64_encode($data['invoice_id']).'/'.$invoice_secureHash;
                }
                $formated_invoice_id = date("dmy", strtotime($details->booking_date)).str_pad($data['invoice_id'], 4, '0', STR_PAD_LEFT);
                $supplied_details=array('invoice_id1'=>$formated_invoice_id,'name'=>$name,'check_in'=>$details->check_in,'check_out'=>$details->check_out,'room_type'=>$details->room_type,'booking_date'=>$details->booking_date,'user_mobile'=>$details->mobile,'hotel_display_name'=>$details->hotel_name,'hotel_address'=>$hoteldetails->hotel_address,'hotel_mobile'=>$hoteldetails->mobile,'image_name'=>$hotel_image,'total'=>$details->total_amount,'paid'=>number_format((float)$data['paid_amount'], 2, '.', ''),'hotel_email_id'=>$hoteldetails->email_id,'user_email_id'=>$email,'url'=>$secureHash);
                if($details->sendMail($email,'emails.mailInvoiceTemplate', "Pay now to confirm booking", $supplied_details)){
                        $res=array('status'=>1,"message"=>'Mail invoice sent successfully');
                        return response()->json($res);
                }
                else{
                        $res=array('status'=>-1,"message"=>$failure_message);
                        $res['errors'][] = "Mail invoice sending failed";
                        return response()->json($res);
                }
        }
           /**
         * Method to send the partial payment report download
         * @params hotel id and Request object
         * @return success download unpaid booking csv report 
         * @author Swati
         * * */
        public function UnpaidBookingReportDownload($hotel_id)
        {
                $invoices = Invoice::join('kernel.user_table as user_table', 'invoice_table.user_id', '=', 'user_table.user_id')
                        ->join('hotel_booking', 'invoice_table.invoice_id', '=', 'hotel_booking.invoice_id')
                        ->select('invoice_table.invoice_id', 'invoice_table.hotel_name', 'invoice_table.room_type', 'invoice_table.total_amount', 'invoice_table.paid_amount', 'invoice_table.user_id', 'invoice_table.paid_amount', 'invoice_table.check_in_out', 'invoice_table.booking_date', 'invoice_table.booking_status', 'user_table.first_name', 'user_table.last_name', 'user_table.email_id', 'user_table.address', 'user_table.mobile', 'hotel_booking.rooms', 'hotel_booking.check_in', 'hotel_booking.check_out')
                        ->where('invoice_table.hotel_id', $hotel_id)
                        ->where('invoice_table.booking_status', 2)
                        ->distinct('invoice_id')->orderBy('invoice_id', 'DESC')->get();

                $row = [];

                foreach ($invoices as $invoice) {

                        $user_infromation = $invoice->first_name . ' ' . $invoice->last_name . ' ' . $invoice->email_id . ' ' . $invoice->mobile;
                        $hotel_name = $invoice->hotel_name;
                        $booking_date = $invoice->booking_date;
                        $check_in_out = $invoice->check_in_out;
                        $room_type = $invoice->room_type;
                        $no_of_room = $invoice->rooms;
                        $total_amount = $invoice->total_amount;

                        $row[] = [
                                'User Information' => $user_infromation,
                                'Hotel Name'  => $hotel_name,
                                'Date Of Booking' => $booking_date,
                                'Period Of Booking' => $check_in_out,
                                'Room Type' => $room_type,
                                'No Of Room' => $no_of_room,
                                'Total Amount' => $total_amount,

                        ];
                }

                if (sizeof($row) > 0) {

                        header('Content-Type: text/json; charset=utf-8');
                        header('Content-Disposition: attachment; filename=UnpaidBookingReport.csv');
                        $output = fopen("php://output", "w");
                        fputcsv($output, array('User Information', 'Hotel Name', 'Date Of Booking', 'Period Of Booking', 'Room Type', 'No Of Room', 'Total Amount'));
                        foreach ($row as $data) {
                                fputcsv($output, $data);
                        }
                        fclose($output);
                }
        }
}
