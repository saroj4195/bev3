<?php

namespace App\Http\Controllers\BEV3;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Validator;
use App\UserCredential;
use App\HotelCancellationPolicy; //class from modelManageRoomType
use App\MasterRoomType;
use DB;
use App\Http\Controllers\Controller;
use App\HotelInformation;
use App\Invoice;
use App\Refund;
//a new class created HotelCancellationController
class RoomNightCancellationController extends Controller
{

    /**
     * Author: Dibyajyoti Mishra 
     * Date: 27-09-2024
     * Description: This api is develop for get the cancellable status for a hotel using hotel id
     */
    public function getCancellableStatus($hotel_id)
    {
        try {
            $hotelExists = HotelInformation::where('hotel_id', $hotel_id)->first();

            if (!$hotelExists) {
                return response()->json(['status' => 0, 'message' => 'Hotel Information Not Found']);
            }

            $cancellableStatus = $hotelExists->is_cancellable;

            return response()->json([
                'status' => 1,
                'message' => 'cancellable status Found',
                'data' => [
                    [
                        'cancellable_status' => $cancellableStatus
                    ]
                ]
            ]);
        } catch (\exception $e) {
            return response()->json(['message' => $e->getMessage()]);
        }
    }

    /**
     * Author: Dibyajyoti Mishra
     * Date: 21-09-2024
     * Description: This api is develop to Check the guest or user's eligibility to cancel booking and check the  refund amount.
     */
    public function checkCancelEligibility(Request $request)
    {
        $is_cancellable = HotelInformation::select('is_cancellable')
            ->where('hotel_id', '=', $request->hotel_id)
            ->first();
        if ($is_cancellable->is_cancellable == 0) {
            return response()->json([
                'status' => 0,
                'message' => 'Cancellation is not available for this Booking',
            ]);
        }

        $booking_id = $request->input('booking_id');
        $pg_charge_per = 2; // in percentage 
        $invoice_id = substr($booking_id, 6);

        $invoice_details = Invoice::join('online_transaction_details', 'invoice_table.invoice_id', '=', 'online_transaction_details.invoice_id')
            ->join('hotel_booking', 'invoice_table.invoice_id', '=', 'hotel_booking.invoice_id')
            ->join('kernel.user_table', 'invoice_table.user_id', '=', 'user_table.user_id')
            ->where('invoice_table.invoice_id', $invoice_id)
            ->select(
                'hotel_booking.check_in',
                'online_transaction_details.payment_id',
                'online_transaction_details.transaction_id',
                'invoice_table.paid_amount',
                'user_table.email_id',
                'user_table.mobile'
            )
            ->first();

        $paymentId = $invoice_details->payment_id;
        $check_in = $invoice_details->check_in;

        $paid_amount = $invoice_details->paid_amount;

        if ($paid_amount == 0) {
            return response()->json([
                'status' => 1,
                'message' => "Refund amount is 0"

            ]);
        }

        $pg_charge = $paid_amount * $pg_charge_per / 100;
        $refund_amount = $this->calculateRefundAmount($request->hotel_id, $check_in, $paid_amount, $pg_charge_per);
        // dd($refund_amount);

        return response()->json([
            'status' => 1,
            'message' => "Refund amount is $refund_amount and Cancellation charge is $pg_charge"
            // 'data' => [
            //          'Refund amount' => $refund_amount,
            //          'Cancellation Charge' => $pg_charge
            //         ]
        ]);
    }

    /**
     * Author: Dibyajyoti Mishra
     * Date: 21-09-2024
     */
    private function calculateRefundAmount($hotel_id, $check_in, $paid_amount, $pg_charge_per)
    {
        $cancellation = HotelCancellationPolicy::where('hotel_id', $hotel_id)
            ->where(
                'valid_from',
                '<=',
                $check_in
            )
            ->where('valid_to', '>=', $check_in)
            ->select('cancellation_rules')
            ->first();

        if (!$cancellation) {
            return response()->json([
                'status' => 0,
                'message' => "No cancellation policy found for this date"

            ]);
        }

        $date1 = date_create(date('Y-m-d'));
        $date2 = date_create($check_in);
        $diff = date_diff($date1, $date2);
        $diff = $diff->format("%a");
        $datas = json_decode($cancellation->cancellation_rules);

        foreach ($datas as $data) {
            $data_n = explode(':', $data);
            $days = $data_n[0];
            $refund_per = $data_n[1];

            if ($diff <= $days) {
                $refund_percent = $refund_per;
                break;
            }
        }

        $pg_charge = $paid_amount * $pg_charge_per / 100;
        $amount_after_pg_charge = $paid_amount - $pg_charge;
        $refund_amount = $amount_after_pg_charge - ($amount_after_pg_charge * $refund_percent / 100);

        return $refund_amount;
    }

    /**
     * Author: Dibyajyoti Mishra
     * Date: 21-09-2024
     * Description: This api is develop to Check the guest or user's eligibility to cancel booking and check the  refund amount.
     */
    public function createRefund(Request $request)
    {
        $hotel_id = $request->input('hotel_id');
        $booking_id = $request->input('booking_id');
        $pg_charge_per = 2; // in percentage 

        $invoice_id = substr($booking_id, 6);

        $invoice_details = Invoice::join('online_transaction_details', 'invoice_table.invoice_id', '=', 'online_transaction_details.invoice_id')
            ->join('hotel_booking', 'invoice_table.invoice_id', '=', 'hotel_booking.invoice_id')
            ->join('kernel.user_table', 'invoice_table.user_id', '=', 'user_table.user_id')
            ->where('invoice_table.invoice_id', $invoice_id)
            ->select(
                'hotel_booking.check_in',
                'online_transaction_details.payment_id',
                'online_transaction_details.transaction_id',
                'invoice_table.paid_amount',
                'user_table.email_id',
                'user_table.mobile'
            )
            ->first();

        $paid_amount = $invoice_details->paid_amount;
        $paymentId = $invoice_details->payment_id;
        $check_in = $invoice_details->check_in;

        $refund_amount = $this->calculateRefundAmount($hotel_id, $check_in, $paid_amount, $pg_charge_per);

        $pg_details = DB::table('paymentgateway_details')
            ->select('provider_name', 'credentials')
            ->where('hotel_id', '=', $hotel_id)
            ->where('is_active', '=', 1)
            ->first();

        $credentials = json_decode($pg_details->credentials);

        if ($pg_details->provider_name == 'razorpay') {

            $key = $credentials[0]->key;
            $secret = $credentials[0]->secret;
            $url = "https://api.razorpay.com/v1/payments/{$paymentId}/refund";
            $data = [
                'speed' => 'optimum',
                'amount' => $refund_amount * 100
            ];
            $jsonData = json_encode($data);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
            curl_setopt($ch, CURLOPT_USERPWD, "{$key}:{$secret}");
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json'
            ]);
            $razorpay_response = curl_exec($ch);
            curl_close($ch);

            if ($razorpay_response) {
                $data = json_decode(
                    $razorpay_response,
                    true
                );
                $refund_details = new Refund();
                $refund_details->refund_id = $data['id'];
                $refund_details->hotel_id = $hotel_id;
                $refund_details->booking_id = $invoice_id;
                $refund_details->payment_id = $paymentId;
                $refund_details->refund_status = "Initiated";
                $refund_details->refund_amount = $refund_amount;
                $refund_details->provider_name = "razorpay";
                $refund_details->save();

                return response()->json([
                    'status' => 1,
                    'message' => 'Razorpay Refund Initiated successfully',
                    'data' => $data
                ]);
            } else {
                return response()->json([
                    'status' => 0,
                    'message' => 'Failed to create refund for Razorpay',
                ]);
            }
        }

        if ($pg_details->provider_name == 'easebuzz') {

            $transaction_id = $invoice_details->transaction_id;
            $email = $invoice_details->email_id;
            $phone = $invoice_details->mobile;
            $access_key = $credentials[0]->merchant_key;
            $secret_key = $credentials[0]->salt;

            $url = 'https://dashboard.easebuzz.in/transaction/v1/refund';
            $refund_data = [
                'key' => $access_key,
                'txnid' => $transaction_id,
                'refund_amount' => (float) $refund_amount,
                'amount' => (float) $paid_amount, //total amount
                'phone' => $phone,
                'email' => $email,
            ];

            $hash_string = $access_key . '|' . $transaction_id . '|' . $paid_amount . '|' . $refund_amount . '|' . $email . '|' . $phone . '|' . $secret_key;
            $refund_data['hash'] = hash('sha512', $hash_string);

            $curl = curl_init();

            curl_setopt_array($curl, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => json_encode($refund_data),
                CURLOPT_HTTPHEADER => [
                    "Accept: application/json",
                    "Content-Type: application/json"
                ],
            ]);

            $easebuzz_response = curl_exec($curl);
            $err = curl_error($curl);
            curl_close($curl);

            if ($easebuzz_response) {
                $data = json_decode(
                    $easebuzz_response,
                    true
                );

                $refund_details = new Refund();
                $refund_details->refund_id = $data->id;
                $refund_details->hotel_id = $hotel_id;
                $refund_details->booking_id = $invoice_id;
                $refund_details->transaction_id = $transaction_id;
                $refund_details->refund_status = "Initiated";
                $refund_details->refund_amount = $refund_amount;
                $refund_details->provider_name = "easebuzz";
                $refund_details->save();

                return response()->json([
                    'status' => 1,
                    'message' => 'Easebuzz Refund Initiated successfully',
                    'data' => $data
                ]);
            } else {
                return response()->json([
                    'status' => 0,
                    'message' => 'Failed to create refund for Easebuzz',
                ]);
            }
        }

        if ($pg_details->provider_name == 'hdfc_payu') {

            $transactionId = $invoice_details->transaction_id;
            $merchantKey = $credentials[0]->key;
            $merchantSalt = $credentials[0]->salt;

            $hashString = $merchantKey . '|cancel_refund_transaction|' . $transactionId . '|' . $refund_amount . '|' . $merchantSalt;
            $hash = strtolower(hash('sha512', $hashString));

            $url = 'https://secure.payu.in/merchant/postservice?form=2';
            $postData = [
                'key'        => $merchantKey,
                'command'    => 'cancel_refund_transaction',
                'var1'       => $transactionId,
                'var2'       => $refund_amount,
                'hash'       => $hash,
            ];
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => $postData,
                CURLOPT_HTTPHEADER => [
                    "accept: application/json",
                    "content-type: application/x-www-form-urlencoded"
                ],
            ]);
            $hdfc_payu_response = curl_exec($curl);

            if ($hdfc_payu_response) {
                $data = json_decode(
                    $hdfc_payu_response,
                    true
                );
                $refund_details = new Refund();
                $refund_details->refund_id = $data->id;
                $refund_details->hotel_id = $hotel_id;
                $refund_details->booking_id = $invoice_id;
                $refund_details->transaction_id = $transactionId;
                $refund_details->refund_status = "Initiated";
                $refund_details->refund_amount = $refund_amount;
                $refund_details->provider_name = "hdfc_payu";
                $refund_details->save();

                return response()->json([
                    'status' => 1,
                    'message' => 'HDFC PayU Refund Initiated successfully',
                    'data' => $data
                ]);
            } else {
                return response()->json([
                    'status' => 0,
                    'message' => 'Failed to create refund for HDFC PayU',
                ]);
            }
        }
    }

    /**
     * Author: Dibyajyoti Mishra
     * Date: 21-09-2024
     * Description: This api is develop to Check the refund status.
     */
    public function checkRefundStatus(Request $request)
    {
        $hotel_id = $request->input('hotel_id');
        $booking_id = $request->input('booking_id');
        $invoice_id = substr($booking_id, 6);

        $refund_details = Refund::where('booking_id',$invoice_id)->where('hotel_id',$hotel_id)->first();

        if($refund_details){
            $refundId = $refund_details->refund_id;
        }



        $pg_details = DB::table('paymentgateway_details')
            ->select('provider_name', 'credentials')
            ->where('hotel_id', '=', $request->hotel_id)
            ->where('is_active', '=', 1)
            ->first();

        if ($pg_details->provider_name == 'razorpay') {

            $credentials = json_decode($pg_details->credentials);
            $key = $credentials[0]->key;
            $secret = $credentials[0]->secret;

            $url = "https://api.razorpay.com/v1/refunds/{$refundId}";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_USERPWD, "{$key}:{$secret}");

            $razorpay_response = curl_exec($ch);
            if ($razorpay_response) {
                return response()->json([
                    'status' => 1,
                    'message' => 'Refund status retrieved successfully',
                    'data' => json_decode($razorpay_response, true)
                ]);
            } else {
                return response()->json([
                    'status' => 0,
                    'message' => 'Failed to retrieve refund status',
                ]);
            }
        }

        if ($pg_details->provider_name == 'easebuzz') {
            $credentials = json_decode($pg_details->credentials);
            $easebuzz_key = $credentials[0]->merchant_key;
            $easebuzz_id = $request->input('easebuzz_id');
            $merchant_refund_id = $request->input('merchant_refund_id');
            $secret_key = $credentials[0]->salt;
            $hash_string = $easebuzz_key . '|' . $easebuzz_id . '|' . $secret_key;
            $hash = hash('sha512', $hash_string);

            $postData = [
                'key' => $easebuzz_key,
                'easebuzz_id' => $easebuzz_id,
                'hash' => $hash,
                'merchant_refund_id' => $merchant_refund_id,
            ];

            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => "https://dashboard.easebuzz.in/refund/v1/retrieve",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => json_encode($postData),
                CURLOPT_HTTPHEADER => [
                    "Accept: application/json",
                    "Content-Type: application/json"
                ],
            ]);

            $easebuzz_response = curl_exec($curl);
            if ($easebuzz_response) {
                return response()->json([
                    'status' => 1,
                    'message' => 'Refund status retrieved successfully',
                    'data' => json_decode($easebuzz_response, true)
                ]);
            } else {
                return response()->json([
                    'status' => 0,
                    'message' => 'Failed to retrieve refund status',
                ]);
            }
        }

        if ($pg_details->provider_name == 'hdfc_payu') {
            $credentials = json_decode($pg_details->credentials);
            $merchantKey = $credentials[0]->key;
            $merchantSalt = $credentials[0]->salt;

            $hashString = $merchantKey . '|get_refund_details|' . $refundId . '|' . $merchantSalt;
            $hash = strtolower(hash('sha512', $hashString));

            $url = 'https://secure.payu.in/merchant/postservice?form=2';
            $postData = http_build_query([
                'key'     => $merchantKey,
                'command' => 'get_refund_details',
                'var1'    => $refundId,
                'hash'    => $hash,
            ]);

            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => $postData,
                CURLOPT_HTTPHEADER => [
                    "accept: application/json",
                    "content-type: application/x-www-form-urlencoded"
                ],
            ]);

            $hdfc_payu_response = curl_exec($curl);
            curl_close($curl);

            if ($hdfc_payu_response) {
                return response()->json([
                    'status' => 1,
                    'message' => 'Refund status retrieved successfully',
                    'data' => json_decode($hdfc_payu_response, true)
                ]);
            } else {
                return response()->json([
                    'status' => 0,
                    'message' => 'Failed to retrieve refund status',
                ]);
            }
        }
    }
}
