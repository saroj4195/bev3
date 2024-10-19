<?php
namespace App\Http\Controllers\Extranetv4;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Validator;
use App\UserCredential;
use App\HotelCancellationPolicy; //class from modelManageRoomType
use App\MasterRoomType;
use App\HotelInformation;
use App\Invoice;
use App\Refund;
use DB;
use App\Http\Controllers\Controller;
//a new class created HotelCancellationController
class HotelCancellationController extends Controller
{
    private $rules = array(
        'from_date' => 'required ',
        'to_date' => 'required ',
        'cancellation_before_days' => 'required | numeric',
        'percentage_refund' => 'required | numeric',
        'hotel_id' =>'required | numeric'
        );
         //Custom Error Messages
        private $messages = [

        'from_date.required' => 'The from date  field is required.',
        'to_date.required' => 'The to date  field is required.',

        'cancellation_before_days.required' => 'The cancellation before days  field is required.',
        'cancellation_before_days.numeric' => 'The cancellation before days  should be numeric.',

        'percentage_refund.required' => 'The percentage refund  field is required.',
        'percentage_refund.numeric' => 'The percentage refund should be numeric.',
        'hotel_id.required' =>'Hotel id is required'
    ];

    /**
     * Hotel cancellation policy
     * Create a new record of Hotel cancellation policy .
     * @author subhradip
     * @return Hotel cancellation policy Name saving status
     *function addNewCancellationPolicies is  for creating a new cancellation policy name
    **/
    public function addNewCancellationPolicies(Request $request)
    {
        $hotel_cancellationpolicy = new HotelCancellationPolicy();
        $failure_message='Hotel cancellation policies saving failed';
        $validator = Validator::make($request->all(),$this->rules,$this->messages);
        if ($validator->fails())
        {
            return response()->json(array('status'=>0,'message'=>$failure_message,'errors'=>$validator->errors()));
        }
        $data=$request->all();
        $data['from_date']=date('Y-m-d',strtotime($data['from_date']));
        $data['to_date']=date('Y-m-d',strtotime($data['to_date']));
        //TO get user id from AUTH token
        if(isset($request->auth->admin_id)){
            $data['user_id']=$request->auth->admin_id;
        }else if(isset($request->auth->super_admin_id)){
            $data['user_id']=$request->auth->super_admin_id;
        }
        else if(isset($request->auth->id)){
            $data['user_id']=$request->auth->id;
        }
        if($hotel_cancellationpolicy->checkCancellationPolicy($data['room_type_id'],$data['hotel_id'],$data['from_date'],$data['to_date'])=="new")
        {
            if($hotel_cancellationpolicy->fill($data)->save())
            {
                $res=array('status'=>1,'message'=>"Hotel cancellation polices saved successfully",'res'=>$data);
                return response()->json($res);
            }
            else
            {
                $res=array('status'=>-1,"message"=>$failure_message);
                $res['errors'][] = "Internal server error";
                return response()->json($res);
            }
        }
        else
        {
            $res=array('status'=>-1,"message"=>"Cancellation policy of hotel already exists");
            return response()->json($res);;
        }
    }
    /**
     * Hotel cancellation policy
     * Update record of Hotel cancellation policy
     * @author subhradip
     * @return Hotel cancellation policy saving status
     * function updateCancellationPolicy id for updating  cancellation policy
    **/
    public function updateCancellationPolicy(int $id ,Request $request)
    {
      $hotel_cancellationpolicy = new HotelCancellationPolicy();
      $failure_message="Hotel's cancellation policies  saving failed.";
        $validator = Validator::make($request->all(),$this->rules,$this->messages);
        if ($validator->fails())
        {
        return response()->json(array('status'=>0,'message'=>$failure_message,'errors'=>$validator->errors()));
        }
      $data=$request->all();
      $data['from_date']=date('Y-m-d',strtotime($data['from_date']));
      $data['to_date']=date('Y-m-d',strtotime($data['to_date']));
      $hotel_cancellation_policy = HotelCancellationPolicy::where('id',$id)->first();
      if($hotel_cancellation_policy->id == $id )
        {
            if($hotel_cancellation_policy->fill($data)->save())
            {
                $res=array('status'=>1,'message'=>"Hotel cancellation polices updated successfully",'res'=>$data);
                return response()->json($res);
            }
            else
            {
                $res=array('status'=>-1,"message"=>$failure_message);
                $res['errors'][] = "Internal server error";
                return response()->json($res);
            }
        }
    }
    /**
     * Get Hotel cancellation policy
     * get one record of Hotel cancellation policy
     * @author subhradip
     * function getHotelCancellationPolicy is for getting a data.
    **/
    public function getHotelCancellationPolicy(int $id ,Request $request)
    {
        $hotel_cancellation_policy=new HotelCancellationPolicy();
        if($id)
        {
            $conditions=array('id'=>$id,'is_trash'=>0);
            $hotel_cancell_policy=HotelCancellationPolicy::where($conditions)->first();
            if($hotel_cancell_policy)
            {
                $data=$hotel_cancell_policy;
                $res=array('status'=>1,'message'=>"Hotel cancellation policy retrieved successfully",'data'=>$data);

                return response()->json($res);
            }
            else
            {
                $res=array('status'=>0,"message"=>"No hotel cancellation policy records found");
                return response()->json($res);
            }
        }
        else
        {
            $res=array('status'=>-1,"message"=>"Hotel id is invalid");
            return response()->json($res);
        }

    }
     /**
     * Get all  Hotel cancellation policy
     * get All record of  Hotel cancellation policy
     * @author subhradip
     * function GetAllCancellationPolicy for selecting all data
    **/
    public function GetAllCancellationPolicy(int $hotel_id ,Request $request)
    {
        $data= DB::table('hotel_cancellation_policy')
        ->leftJoin('kernel.room_type_table', 'hotel_cancellation_policy.room_type_id', '=', 'room_type_table.room_type_id')
        ->where('hotel_cancellation_policy.hotel_id' , '=' , $hotel_id)
        ->where('hotel_cancellation_policy.is_trash' , '=' , 0)
        ->select('hotel_cancellation_policy.id','hotel_cancellation_policy.from_date', 'hotel_cancellation_policy.to_date',
                 'hotel_cancellation_policy.cancellation_before_days','hotel_cancellation_policy.percentage_refund',
                 'room_type_table.room_type', 'hotel_cancellation_policy.room_type_id'
               )
               ->get();
        if($data)
        {
            foreach($data as $cancel_policy)
            {
                if($cancel_policy->room_type_id==0)
                {
                    $cancel_policy->room_type="All";
                }
            }
        }
        if(sizeof($data)>0)
        {
            $res=array('status'=>1,'message'=>"Found hotel cancellation",'data'=>$data);
            return response()->json($res);
        }
        else
        {
            $data=array('status'=>0,"message"=>"No  Hotel cancellation policy  records found");
            return response()->json($data);
        }
    }

     /**
     * Delete Cancellation policy
     * delete record of Cancellation policy
     * @author subhradip
     * @return Cancellation policy deleting status
     * function DeleteCancellationPolicy used for delete
    **/
    public function DeleteCancellationPolicy(int $cancel_policy_id ,Request $request)
    {
        $failure_message='Cancellation deletion failed';
        if(HotelCancellationPolicy::where('id',$cancel_policy_id)->update(['is_trash' => 1]))
        {
            $res=array('status'=>1,"message"=>'Cancellation policy Deleted successfully');
            return response()->json($res);
        }
        else
        {
            $res=array('status'=>-1,"message"=>$failure_message);
            $res['errors'][] = "Internal server error";
            return response()->json($res);
        }
    }


    /**
    * Author: Dibyajyoti Mishra
    * Date: 27-09-2024
    * Description: This api is develop for create a new cancellation rule for the hotel.
    */
    public function addNewCancellationRules(Request $request)
    {
        try {

            $data  =  $request->all();

            $valid_from = date('Y-m-d', strtotime($data['valid_from']));
            $valid_to = date('Y-m-d', strtotime($data['valid_to']));

            $policy_details = [
                'hotel_id' => $data['hotel_id'],
                'cancellation_policy_name' => $data['policy_name'],
                'policy_description' => $data['policy_description'],
                'valid_from' => $valid_from,
                'valid_to' => $valid_to,
                'is_default' => 0,
                'cancellation_rules' => json_encode($data['cancellation_rules']),

            ];

            $check_policy = HotelCancellationPolicy::where('hotel_id', $data['hotel_id'])
            ->where(function ($query) use ($valid_from, $valid_to) {
                $query->whereBetween('valid_from', [$valid_from, $valid_to])
                    ->orWhereBetween('valid_to', [$valid_from, $valid_to]);
            })
                ->first();

            if ($check_policy) {
                return response()->json(["status" => 0, "message" => 'Cancellation policy is already available for this date']);
            }

            $policy_details = HotelCancellationPolicy::insert($policy_details);

            if ($policy_details) {
                $final_data = ["status" => 1, "message" => 'Cancellation Policy Created Successfully'];
            } else {
                $final_data = ['status' => 0, 'message' => 'Cancellation Policy Creation Failed'];
            }
            return response()->json($final_data);
        } catch (\Exception $e) {
            $response_message = $e->getMessage();
            return response()->json(['message' => $response_message]);
        }
    }

    /**
     * Author: Dibyajyoti Mishra
     * Date: 27-09-2024
     * Description: This api is develop for update the cancellation rule using the id
     */
    public function updateCancellationRules(Request $request,$policy_id)
    {
        try {
   
           $data  =  $request->all();
   
           $valid_from = date('Y-m-d',strtotime($data['valid_from']));
           $valid_to = date('Y-m-d',strtotime($data['valid_to']));
   
           $policy_details = [
               'cancellation_policy_name' => $data['policy_name'],
               'policy_description' => $data['policy_description'],
               'cancellation_rules' => json_encode($data['cancellation_rules']),
               'valid_from' => $valid_from,
               'valid_to' => $valid_to,
           ];

           $check_policy = HotelCancellationPolicy::where('hotel_id', $data['hotel_id'])
            ->where('id','!=',$policy_id)
            ->where(function ($query) use ($valid_from, $valid_to) {
                $query->whereBetween('valid_from', [$valid_from, $valid_to])
                    ->orWhereBetween('valid_to', [$valid_from, $valid_to]);
            })
            ->first();

            if ($check_policy) {
                return response()->json(["status" => 0, "message" => 'Cancellation policy is already available for this date']);
            }

           $update_data = HotelCancellationPolicy::where('id',$policy_id)->update($policy_details);
   
           if($update_data){
             return response()->json(["status" => 1, "message" => 'Cancellation Policy Updated Successfully']);
           }else{
             return response()->json(['status' => 0, 'message' => 'Update Failed']);
           }
           } catch (\Exception $e) {
               $response_message = $e->getMessage();
               return response()->json(['message' => $response_message]);
          }
    }

    /**
     * Author: Dibyajyoti Mishra
     * Date: 27-09-2024
     * Description: This api is develop for get all the cancellation rules for a hotel
     */
    public function getCancellationRules(Request $request, $hotel_id)
    {
        try {
            $policy_list = HotelCancellationPolicy::where('hotel_id', $hotel_id)
                ->where('is_trash', 0)
                ->get();

            $policy_list_array = [];
            if ($policy_list) {
                foreach ($policy_list as $policy) {

                    $list = [];
                    $datas = json_decode($policy->cancellation_rules);
                    if(isset($datas)){
                    foreach ($datas as $data) {
                        $data_n = explode(':', $data);
                        $days = $data_n[0];
                        $refund_per = $data_n[1];
                        $list[] = [
                            'cancel_before_days' => $days,
                            'refund_percentage' => $refund_per,
                        ];
                    }
                 }

                    $policy_list_array[] = array(
                        'policy_id' => $policy->id,
                        'cancellation_rules' => $list,
                        'cancellation_policy_name' => $policy->cancellation_policy_name,
                        'policy_description' => isset($policy->policy_description) ? $policy->policy_description : "",
                        'valid_from' => $policy->valid_from,
                        'valid_to' => $policy->valid_to,
                        'is_default' => $policy->is_default
                    );
                }
            }

            if (sizeof($policy_list_array) > 0) {
                $final_data = ["status" => 1, "data" => $policy_list_array];
                return response()->json($final_data);
            } else {
                $final_data = ['status' => 1, 'message' => 'Cancellaction policy not found'];
                return response()->json($final_data);
            }
        } catch (\Exception $e) {
            $response_message = $e->getMessage();
            return response()->json(['message' => $response_message]);
        }
    }

    /**
     * Author: Dibyajyoti Mishra
     * Date: 27-09-2024
     * Description: This api is develop for update the cancellable status for a hotel(whether the user can cancel the booking or not)
     */
    public function updateCancellableStatus(Request $request)
    {
        try {

            $is_cancellable = $request->is_cancellable;
            $hotel_id = $request->hotel_id;

            $hotelExists = HotelInformation::where('hotel_id', $hotel_id)
                ->first();

            if (!$hotelExists) {
                return response()->json(["status" => 0, "message" => 'Hotel Information Not Found']);
            }

            $hotel = HotelInformation::where('hotel_id', $hotel_id)->first();

            $hotel->is_cancellable = $is_cancellable;
            $update_status = $hotel->save();

            if ($update_status) {
                $final_data = ["status" => 1, "message" => 'Cancellable Status Updated Successfully'];
            } else {
                $final_data = ['status' => 0, 'message' => 'Cancellation Status Updation Failed'];
            }
            return response()->json($final_data);
        } catch (\Exception $e) {
            $response_message = $e->getMessage();
            return response()->json(['message' => $response_message]);
        }
    }

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

    


    /// REFUND Razorpay  Easebuzz  Hdfc-payu
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
        ->where(
            'hotel_id',
            '=',
            $hotel_id
        )
        ->where('is_active', '=', 1)
        ->first();

        // dd($pg_details);


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

                $data = json_decode($razorpay_response,
                    true
                );
                // dd($data);

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

        if ($pg_details->provider_name == 'easebuzz'
        ) {

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

                $data = json_decode($easebuzz_response,
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

        if ($pg_details->provider_name == 'hdfc_payu'
        ) {

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

            $ch = curl_init($url);

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

                $data = json_decode($hdfc_payu_response,
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

    private function calculateRefundAmount($hotel_id,$check_in,$paid_amount, $pg_charge_per)
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


        if(!$cancellation){
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


     public function checkRefundStatus(Request $request)
    {

        $pg_details = DB::table('paymentgateway_details')
            ->select('provider_name', 'credentials')
            ->where('hotel_id', '=', $request->hotel_id)
            ->where('is_active', '=', 1)
            ->first();

        $refundId = $request->input('refund_id');

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
