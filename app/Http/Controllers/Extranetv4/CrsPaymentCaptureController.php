<?php

namespace App\Http\Controllers\Extranetv4;

use Illuminate\Http\Request;
use App\CrsPaymentReceive;
use App\CrsBooking;

class CrsPaymentCaptureController extends Controller
{
    public function crsCapturePayment(Request $request)
    {
        $data = $request->all();
        $invoice_id =substr($data['booking_id'],6);
        $payment_details['invoice_id'] = $invoice_id;
        $payment_details['hotel_id'] = $data['hotel_id'];
        $payment_details['receive_amount'] = $data['amount'];
        $payment_details['payment_mode'] = $data['payment_mode'];
        $payment_details['ref_no'] = $data['ref_no'];
        $payment_details['payment_receive_date'] = date('Y-m-d H:i:s', strtotime($data['date']));
        $result = CrsPaymentReceive::insert($payment_details);
        if ($result) {
            $booking_details= CrsBooking::where('invoice_id',$invoice_id)->select('payment_type')->first();
            if(isset($booking_details->payment_type) && $booking_details->payment_type==1){  //To Expired the payment link
             $res = CrsBooking::where('invoice_id',$invoice_id)->update([
                'is_payment_received'=>'1',
                'payment_link_status'=>'invalid',
                'updated_status'=>'1',
             ]);
            }
            return response()->json(array('status' => 1, 'message' => 'Payment Received'));
        } else {
            return response()->json(array('status' => 0, 'message' => 'Payment Received Failed'));
        }
    }

    public function crsCapturePaymentList($booking_id)
    {
        $invoice_id =substr($booking_id,6);
        $paymentList = CrsPaymentReceive::where('invoice_id',$invoice_id)->get();
        $payment_rec_details = [];

        if($paymentList){
            $total_amount = 0;
            foreach($paymentList as $payment){
                $payment_rec_list['invoice_id'] = $payment->invoice_id;
                $payment_rec_list['payment_receive_date'] = date('d M Y',strtotime($payment->payment_receive_date));
                $payment_rec_list['receive_amount'] = $payment->receive_amount;
                $payment_rec_list['payment_mode'] = $payment->payment_mode;
                $payment_rec_list['ref_no'] = $payment->ref_no;
                $payment_rec_details[] = $payment_rec_list;
                $total_amount = $total_amount + $payment->receive_amount;
            }
            // $payment_rec_de[]['total_amount'] = $total_amount;
            
            // array_push($payment_rec_details,array('total_amount'=>$total_amount));

            if (sizeof($payment_rec_details)>0) {
                return response()->json(array('status' => 1,'list' =>$payment_rec_details,'total_amount'=>$total_amount));
            } else {
                return response()->json(array('status' => 0, 'message' => 'Payment Received Failed'));
            }
        }else{
            return response()->json(array('status' => 0, 'message' => 'Payment Received Failed'));
        }
      
        
    }
}
   