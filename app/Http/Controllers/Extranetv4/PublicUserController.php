<?php
namespace App\Http\Controllers\Extranetv4;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Validator;
use App\User;
use App\CompanyDetails;
use App\CancelBooking;
use App\Invoice;
use App\UserIdentity;
use DB;
use App\PaidServices;
use App\CancellationPolicy;
use App\HotelInformation;
use Firebase\JWT\JWT;
use Firebase\JWT\ExpiredException;
use App\Http\Controllers\Controller;
class PublicUserController extends Controller
{
    //validation rules
    private $rules = array(
        'first_name'=>'required',
        // 'last_name'=>'required',
        // 'email_id' => 'required | email',
        'mobile' => 'required',
        'company_url'=>'required'
    );
    private $user_rules=array(
        'mobileno'=>'required'
    );
    private $user_messages=[
       ' mobileno=>required'=>'mobileno should be required'
    ];
    private $select_rules=array(
        'token'=>'required'
    );
    private $select_messages=[
        'token.required'=>'token should be provided'
    ];
     private $cp_rules=array(
        'invoice_id'=>'required | numeric',
        'user_id'=>'required | numeric'
    );
    private $cp_messages=[
        'invoice_id.required'=>'invoice id should be required',
        'user_id.required'=>'user id should be required'
    ];
    private $ca_rules=array(
        'user_id'=>'required | numeric',
        'hotel_id'=>'required | numeric',
        'invoice_id'=>'required | numeric',
        'booking_status'=>'required | numeric'
    );
    private $ca_messages=[
        'user_id.required'=>'invoice id should be required',
        'hotel_id.required'=>'invoice id should be required',
        'invoice_id.required'=>'invoice id should be required',
        'booking_status.required'=>'user id should be required'
    ];
    //validation rules
    private $rules1 = array(
        'email_id' => 'required | email',
       );
    private $rules_public = array(
        'email_id' => 'required | email',
        'company_url' => 'required'
     );
    //Custom Error Messages
    private $messages = [
                'contact_no.required' => 'The contact number field is required.',
                   ];

     /**
     * Create a new token.
     *
     * @param  \App\User   $user
     * @return string
     */
    protected function jwt($email,$exptime,$scope,$type,$company_id) {
        $xsrftoken = md5(uniqid(rand(), true));
        $payload = [
            'iss' => env('APP_DOMAIN'), // Issuer of the token
            'sub' => $email, // Subject of the token
            'type'=> $type, // User Type
            'iat' => time(), // Time when JWT was issued.
            'exp' => $exptime, // Expiration time
            'company_id' => $company_id, // Hotel ID ===> It is applicable for Public Login Otherwise it will be {zero}
            'scope'=>$scope,
            'xsrfToken' => $xsrftoken,
        ];

        // As you can see we are passing `JWT_SECRET` as the second parameter that will
        // be used to decode the token in the future.
        return JWT::encode($payload, env('JWT_SECRET'));
    }
     protected function jwtUser($exptime,$scope,$type,$mobile,$company_id) {
        $xsrftoken = md5(uniqid(rand(), true));
        $payload = [
            'iss' => env('APP_DOMAIN'), // Issuer of the token
            // 'mobile' => $mobile, // Subject of the token

            'sub' => $mobile, // Subject of the token

            'type'=> $type, // User Type
            'iat' => time(), // Time when JWT was issued.
            'exp' => $exptime, // Expiration time
            'scope'=>$scope,

            'company_id' => $company_id,

            'xsrfToken' => $xsrftoken,
        ];

        // As you can see we are passing `JWT_SECRET` as the second parameter that will
        // be used to decode the token in the future.
        return JWT::encode($payload, env('JWT_SECRET'));
    }

    /**
     * Hotel User Registration
     * Create a new hotel user.
     *
     * @return hotel user registration status
    **/
    public function register(Request $request)
    {
        $user = new User();
        $user_identity = new UserIdentity();
        $company= new CompanyDetails();
        $failure_message='Account creation failed';
        $validator = Validator::make($request->all(),$this->rules,$this->messages);
        if ($validator->fails())
        {
            return response()->json(array('status'=>0,'message'=>$failure_message,'errors'=>$validator->errors()));
        }
        $data=$request->all();
        //Get company id
        $conditions=array('company_url'=>$data['company_url']);
        $company_details=CompanyDetails::where($conditions)->first();
        $data['company_id']=$company_details->company_id;
        $data['password']=uniqid();//To generate unique rsndom number
        $password=$data['password'];
        $data['password']=Hash::make($data['password']);//Password encryption
        $data['status']= 1;
        $data['registered_date']= date('Y-m-d');
        $data['GSTIN'] = $data['GST_IN'];
        $identity_data  = array();
        $data['first_name'] = trim($data['first_name']);
        $data['last_name'] = trim($data['last_name']);
        $data['email_id'] = trim($data['email_id']);
        $data['mobile'] = trim($data['mobile']);
        if(isset($data['last_name'])){
            $data['last_name']= $data['last_name'];
        }else{
            $data['last_name'] = 'NA';
        }
        if($user->checkMobileStatus($data['mobile'],$data['company_id'])=="new")
        {
            DB::beginTransaction();
            if($user->fill($data)->save())
            {
                if(isset($data['identity_no']) && $data['identity_no'] != ''){
                    $user_id = $user->user_id;
                    $identity_data['user_id']       = $user_id;
                    $identity_data['identity_no']   = $data['identity_no'];
                    $identity_data['identity']   = $data['identity'];
                    $identity_data['expiry_date']   = date('Y-m-d',strtotime($data['expiry_date']));
                    $identity_data['date_of_birth'] = date('Y-m-d',strtotime($data['date_of_birth']));
                    $user_identity->fill($identity_data)->save();
                }
                $exp_time = strtotime("+30 day");//Expiration time
                $scope=['login'];
                $tp = env('PUBLIC_USER');
                DB::commit();
                $token = $this->jwt($data['mobile'],$exp_time,$scope,$tp,$company_details->company_id);
                $res['status']=1;
                $res['message']='Account creation successfull';
                $res['auth_token']=$token;
                return response()->json($res);
            }
            else
            {
                $res=array('status'=>-1,"message"=>$failure_message);
                $res['errors'][] = "Internal server error";
                return response()->json($res);
            }
        }
        else if($user->checkMobileStatus($data['mobile'],$data['company_id'])=="exist")
        {
            if(!isset($data['company_name']))
                $data['company_name'] = '';
            $user_update = User::where('company_id', '=', $data['company_id'])
                            ->where('mobile', '=', $data['mobile'])
                            ->update(["first_name"=>$data['first_name'],"last_name"=>$data['last_name'],"email_id"=>$data['email_id'],"address"=>$data['address'],"zip_code"=>$data['zip_code'],"country"=>$data['country'],"state"=>$data['state'],"city"=>$data['city'],"GSTIN"=>$data['GST_IN'],"company_name"=>$data['company_name']]);
            $exp_time = strtotime("+30 day");//Expiration time
            $scope=['login'];
            $tp = env('PUBLIC_USER');
            $token = $this->jwt($data['mobile'],$exp_time,$scope,$tp,$company_details->company_id);
            $res['status']=1;
            $res['message']='Account login successfull';
            $res['auth_token']=$token;
            return response()->json($res);
        }
        else//unexpected failure
        {
            $res=array('status'=>-1,"message"=>$failure_message);
            $res['errors'][] = "Internal server error";
            return response()->json($res);
        }

    }

    /**
     * Hotel User Activation
     * Activates the hotel users.
     *
     * @return hotel user activation status
    **/
    public function activateUser(Request $request)
    {
        $data=$request->all();
        $hotel_user_data=false;
        $hotel_user_data = UserCredential::where('status', '=', $data['status'])->first(['email', 'id']);
        if($hotel_user_data)
        {
            $updated=DB::table('user_credentials')
            ->where('id', $hotel_user_data['id'])
            ->update(['status' => "active"]);

            if($updated)
            {
                $res=array('status'=>1,"message"=>"Successfully activated account");
                return response()->json($res);
            }
            else
            {
                $res=array('status'=>-1,"message"=>"Internal server error.");
                return response()->json($res);
            }
        }
        else
        {
            $res=array('status'=>-1,"message"=>"Failed to activate account");
            $res['errors'][]="Invalid verification token";
            return response()->json($res);
        }
    }
    /**
     * Hotel User resend activation email
     *
     *
     * @return hotel user resend activation email sent status
    **/
    public function resendEmail(string $email,Request $request)
    {
        $hotel_user = new UserCredential();
        $failure_message='Failed to re-send account activation email.';
        $emailArr=array("email"=>$email);
        $validator = Validator::make($emailArr,$this->rules1,$this->messages);//EmailArr should be array not string
        if ($validator->fails())
        {
            return response()->json(array('status'=>0,'message'=>$failure_message,'errors'=>$validator->errors()));
        }
        if($hotel_user->checkStatus($email)=="pending")
        {
            $status= md5(uniqid(rand(), true));//32 bit random unique numer
            $verificationCode=env('APP_DOMAIN').'hotel_users/activate/' .$status;
            if($hotel_user->sendMail($email,'emails.accountActivationTemplate', "Bookingjini Account Activation", $verificationCode))
            {

                $res=array('status'=>1,"message"=>'Successfully resent account activation email.');
                return response()->json($res);
            }
            else
            {

                $res=array('status'=>-1,"message"=>$failure_message);
                $res['errors'][] = "Account verification email sending failed";
                return response()->json($res);
            }
        }
        else if($hotel_user->checkStatus($email)=="exist")
        {
            $res['status'] = '0' ;
            $res['message'] =  $failure_message;
            $res['errors'][] = "User already active";
            return response()->json($res);
        }
        else if($hotel_user->checkStatus($email)=="new")
        {
            $res['status'] = '0' ;
            $res['message'] =  $failure_message;
            $res['errors'][] = "User doesn't exists";
            return response()->json($res);
        }
        else//unexpected failure
        {
            $res=array('status'=>-1,"message"=>$failure_message);
            $res['errors'][] = "Internal server error";
            return response()->json($res);
        }

    }


     /**
     * @auther : Shankar Bag
     * Public User Login
     * @Story : This Function will check email and password. If natch then generate tocken.
     *
     * @return email sent status
    **/
    public function login(Request $request)
    {
        $failure_message='Authentication failed';
        $res['status']="0";
        $res['message']=$failure_message;
        $user=new User();
        $validator = Validator::make($request->all(),$this->rules_public,$this->messages);
        if ($validator->fails())
        {
            $res['errors']=$validator->errors();
            return response()->json($res);
        }

        $company=new CompanyDetails();
        $conditions=array('company_url'=>$request->input('company_url'));
        $company_details=CompanyDetails::where($conditions)->first();
        if(!$company_details)
        {
            $res['errors'][]='Invalid Company URL';
            $res['status']="0";
            return response()->json( $res);
        }

            $user = User::where('email_id', $request->input('email_id'))->where('is_trash',0)->where('status',1) ->first();
            $exp_time = strtotime("+30 day");//Expiration time
            $scope=['login'];
            $tp = env('PUBLIC_USER');
            if (Hash::check($request->input('password'), $user['password'])) {
                    $token = $this->jwt($user->email_id,$exp_time,$scope,$tp,$company_details->company_id);
                    $res['status']="1";
                    $res['message']='User authentication successful';
                    $res['auth_token']=$token;
                    return response()->json($res);
            }
            else
            {
                $res['errors'][]='Invalid credentials';
                $res['status']="0";
                return response()->json( $res);
            }
    }
     public function userLogin(Request $request)
    {
        $failure_message='Authentication failed';
        $res['status']="0";
        $res['message']=$failure_message;
        $validator = Validator::make($request->all(),$this->user_rules,$this->user_messages);
        if ($validator->fails())
        {
            $res['errors']=$validator->errors();
            return response()->json($res);
        }
        $data=$request->all();
        $user=User::select('mobile')->where('mobile',$data['mobileno'])->first();
        if(!$user)
        {
            $res['errors'][]='Invalid Mobile number';
            $res['status']="0";
            return response()->json( $res);
        }
        $exp_time = strtotime("+30 day");//Expiration time
        $scope=['login'];
        $tp = env('PUBLIC_USER');
        $mobile=$data['mobileno'];
        $email=$data['mobileno'];
        if($data['mobileno']==$user->mobile)
        {
            $token = $this->jwtUser($exp_time,$scope,$tp,$mobile,$data['company_id']);
            $res['status']="1";
            $res['message']='User authentication successful';
            $res['auth_token']=$token;
            return response()->json($res);
        }
        else
        {
            $res['errors'][]='Invalid credentials';
            $res['status']="0";
            return response()->json( $res);
        }

    }
    public function selectDetails(int $hotel_id,Request $request)
    {
        $failour_message="details retrive fails";
        $validator=Validator::make($request->all(),$this->select_rules,$this->select_messages);
        if ($validator->fails())
        {
            $res['errors']=$validator->errors();
            return response()->json($res);
        }
        $token=$request->input('token');
        try {
            $userdetails = JWT::decode($token, env('JWT_SECRET'), ['HS256']);
        }
        catch(ExpiredException $e) {
            return response()->json([
                'error' => 'Provided token is expired.'
            ], 400);
        }
        $user=User::select('user_id')->where('mobile',$userdetails->mobile)->first();
        $user->user_id = 21789;
        $details=DB::table('invoice_table')->join('kernel.user_table','kernel.user_table.user_id','=','invoice_table.user_id')
        ->select('invoice_table.hotel_name','invoice_table.total_amount','invoice_table.paid_amount','invoice_table.invoice','invoice_table.hotel_id','invoice_table.invoice_id','invoice_table.user_id','invoice_table.booking_status','invoice_table.booking_date','invoice_table.room_type','kernel.user_table.first_name','kernel.user_table.last_name','kernel.user_table.email_id','kernel.user_table.address','kernel.user_table.mobile')
        ->where([['invoice_table.user_id',$user->user_id],['invoice_table.hotel_id',$hotel_id],['invoice_table.booking_status',1]])->orWhere([['invoice_table.user_id',$user->user_id],['invoice_table.hotel_id',$hotel_id],['invoice_table.booking_status',3]])->orWhere([['invoice_table.user_id',$user->user_id],['invoice_table.hotel_id',$hotel_id],['invoice_table.booking_status',5]])->get();
        //dd($details);
        foreach($details as $data_up)
        {
            $booking_id     = date("dmy", strtotime($data_up->booking_date)).str_pad($data_up->invoice_id, 4, '0', STR_PAD_LEFT);
            $data_up->invoice           = str_replace("#####", $booking_id, $data_up->invoice);
            $data_up->invoice_id     =  $booking_id;
        }
        if(sizeof($details)>0)
        {
            $res=array('status'=>1,'message'=>'details retrive sucessfully','details'=>$details);
            return response()->json($res);
        }
        else{
            $res=array('status'=>0,'message'=>'details retrive fails');
            return response()->json($res);
        }
    }
    public function cancelationPolicy(Request $request)
    {
        $today=date('Y-m-d');
        $insert=new CancelBooking;
        $failour_message="cancelation fails";
        $validator=Validator::make($request->all(),$this->cp_rules,$this->cp_messages);
        if ($validator->fails())
        {
            $res['errors']=$validator->errors();
            return response()->json($res);
        }
        $data=$request->all();
        $roomtype_id=DB::table('hotel_booking')->select('room_type_id')->where('hotel_id',$data['hotel_id'])->where('invoice_id',$data['invoice_id'])->first();
        $data['booking_date']=date('dmy',strtotime($data['booking_date']));
        $data['invoice_id']=str_replace($data['booking_date'],'',$data['invoice_id']);
        $data['cancel_date']=date('Y-m-d h:i:sa');
        $policy=DB::table('hotel_cancellation_policy')->where('room_type_id',$roomtype_id['room_type_id'])->get();
        $invoiceDetails=DB::table('invoice_table')->join('hotel_booking','invoice_table.invoice_id','=','hotel_booking.invoice_id')
        ->select('check_in','paid_amount')->where('invoice_table.invoice_id',$data['invoice_id'])->where('invoice_table.hotel_id',$data['hotel_id'])->where('invoice_table.booking_status',1)->first();
        $total=(int)$invoiceDetails->paid_amount;

        if(sizeof($policy) > 0)
        {
            $refund=DB::table('hotel_cancellation_policy')->select('percentage_refund')->where('room_type_id',$data['room_type_id'])
            ->where('from_date','<=',$invoiceDetails->check_in)->where('to_date','>=',$invoiceDetails->check_in)->first();
            if(sizeof($refund)>0)
            {
                $insertData=DB::table('invoice_table')->where('user_id',$data['user_id'])
                ->where('invoice_id',$data['invoice_id'])->update(['booking_status' => '5']);
                if($insert->checkStatus($data['invoice_id'])=='exist')
                {
                    $res=array('status'=>0,'message'=>'Already Cancelled');
                    return response()->json($res);
                }
                else{
                    $percentage=$refund->percentage_refund;
                $totalAmountRefund=round($total*($percentage/100));
                $res=array('status'=>1,'message'=>'Request Pending','amount'=>$totalAmountRefund);
                 return response()->json($res);
                }
            }
            else
            {
                $res=array('status'=>0,'message'=>'refund is not possible cancelation date exided');
                return response()->json($res);
            }
        }
        elseif(sizeof($policy) == 0)
        {
            $refund=DB::table('hotel_cancellation_policy')->select('percentage_refund')->where('room_type_id',0)->where('from_date','<=',$invoiceDetails->check_in)->where('to_date','>=',$invoiceDetails->check_in)->first();

            if(sizeof($refund)>0)
            {
                $insertData=DB::table('invoice_table')->where('user_id',$data['user_id'])
                ->where('invoice_id',$data['invoice_id'])->update(['booking_status' => '5']);
                if($insert->checkStatus($data['invoice_id'])=='exist')
                {
                    $res=array('status'=>0,'message'=>'Already Cancelled');
                    return response()->json($res);
                }
                else{
                    $percentage=$refund->percentage_refund;
                    $totalAmountRefund=round($total*($percentage/100));
                    $res=array('status'=>1,'message'=>'Request Pending','amount'=>$totalAmountRefund);
                     return response()->json($res);
                }
            }
            else
            {
                $res=array('status'=>0,'message'=>'Please Contact to hotel reservation team');
                return response()->json($res);
            }
        }
    }
    public function cancelationAccepted(Request $request)
    {
        $insert=new CancelBooking;
        $failour_message="cancelation fails";
        $validator=Validator::make($request->all(),$this->ca_rules,$this->ca_messages);
        if ($validator->fails())
        {
            $res['errors']=$validator->errors();
            return response()->json($res);
        }
        $data=$request->all();
        $data['cancel_date']=date('Y-m-d h:i:sa');
        if($insert->checkStatus($data['invoice_id'])=='exist')
                {
                    $res=array('status'=>0,'message'=>'Already Cancelled');
                    return response()->json($res);
                }
        else if($data['booking_status']!=3)
        {
            $insertData=DB::table('invoice_table')->where('hotel_id',$data['hotel_id'])
            ->where('invoice_id',$data['invoice_id'])->update(['booking_status' => '3']);
            $insertData=DB::table('hotel_booking')->where('hotel_id',$data['hotel_id'])
            ->where('invoice_id',$data['invoice_id'])->update(['booking_status' => '3']);
            $insert->fill($data)->save();
            $res=array('status'=>1,'message'=>'Request is accepted');
            return response()->json($res);
        }
        else{
            $res=array('status'=>0,'message'=>'Already Cancelled');
                    return response()->json($res);
        }
    }
     public function getHotelPolicy(int $hotel_id,Request $request)
    {
         //$hotel=new HotelInformation();
        //  $cond=array('api_key'=>$api_key);
        //  $comp_info=CompanyDetails::select('company_id')
        //  ->where($cond)->first();
        //  if(!$comp_info['company_id'])
        //  {
        //     $res=array('status'=>1,'message'=>"Invalid hotel or company");
        //     return response()->json($res);
        //  }
        $conditions=array('hotel_id'=>$hotel_id);
        $info=HotelInformation::select('child_policy','cancel_policy','terms_and_cond','hotel_policy','hotel_address','mobile','latitude','longitude')->where($conditions)->first();

        if($info)
        {
            $res=array('status'=>1,'message'=>"Hotel description successfully ",'data'=>$info);
            return response()->json($res);
        }
        else
        {
            $res=array('status'=>1,'message'=>"Invalid hotel or company");
            return response()->json($res);
        }
    }
     //Fetch Mobile number change status
     public function changeUserMobileNumberStatus($mobile_no,$company_id,Request $request)
     {
         
         $user_details=User::select('mobile','change_number_status')->where('mobile',$mobile_no)->where('company_id',$company_id)->first();
         $res = array('status'=>1,'message'=>'Retrieved',"user_details"=>$user_details);
         return response()->json($res);
         
     }
     //Fetch Mobile number change status
 
     //Change user mobile number
     
     public function changeUserMobileNumber(Request $request)
     {
         $failure_message='Authentication failed';
         $res['message']=$failure_message;
         $validator = Validator::make($request->all(),$this->user_rules,$this->user_messages);
         if ($validator->fails())
         {
             $res['errors']=$validator->errors();
             return response()->json($res);
         }
         $data=$request->all();
 
         $user=User::select('mobile')->where('mobile',$data['mobileno'])->where('company_id',$data['company_id'])->first();
         if($user){
             $update_mobile_number= User::where('company_id', $data['company_id'])->where('mobile',$data['mobileno'])->update(['mobile'=>$data['new_mobile_no'],'change_number_status'=>$data['status']]);
 
             if($update_mobile_number){
                 $res = array('status'=>1,'message'=>'mobile number updated successfully');
                 return response()->json($res);
             }else{
                 $res = array('status'=>0,'message'=>'mobile number updation failed');
                 return response()->json($res);
             }
         }else{
             $res['errors'][]='Invalid Mobile number';
             $res['status']=0;
             return response()->json( $res);
         }
         
     }
     public function fetchBookingDetails(Request $request)
     {
 
         $data = $request->all();
         $hotel_id = $data['hotel_id'];
         $date=date('Y-m-d',strtotime($data['date']));
         $last_date=date('Y-m-t',strtotime($data['date'].'+30 days'));
         
         $failour_message="details retrive fails";
         $validator=Validator::make($request->all(),$this->select_rules,$this->select_messages);
         if ($validator->fails())
         {
             $res['errors']=$validator->errors();
             return response()->json($res);
         }
         $token=$request->input('token');
         try {
             $userdetails = JWT::decode($token, env('JWT_SECRET'), ['HS256']);
         }
         catch(ExpiredException $e) {
             return response()->json([
                 'error' => 'Provided token is expired.'
             ], 400);
         }
       
         $user=User::select('user_id')->where('mobile',$userdetails->sub)->where('company_id',$data['company_id'])->orderBy('user_id','DESC')->first();
        
        //  $details=Invoice::join('kernel.user_table','invoice_table.user_id','=','user_table.user_id')
        //  ->join('hotel_booking','invoice_table.invoice_id','=','hotel_booking.invoice_id')
        //  ->select('invoice_table.hotel_name','invoice_table.total_amount','invoice_table.paid_amount','invoice_table.invoice','invoice_table.hotel_id','invoice_table.invoice_id','invoice_table.user_id','invoice_table.booking_status','invoice_table.booking_date','invoice_table.room_type','invoice_table.check_in_out','user_table.first_name','user_table.last_name','user_table.email_id','user_table.address','user_table.mobile','hotel_booking.check_in')
        //  ->where([['invoice_table.user_id',$user->user_id],['invoice_table.hotel_id',$hotel_id],['invoice_table.booking_status',1],['hotel_booking.check_in','>=',$date],['hotel_booking.check_in','<=',$last_date]])->paginate(12);
        //  foreach($details as $data_up)
        //  {
        //     $booking_id     = date("dmy", strtotime($data_up->booking_date)).str_pad($data_up->invoice_id, 4, '0', STR_PAD_LEFT);
        //     $data_up->invoice_id =  $booking_id;
        //     $data_up->booking_id =  $booking_id;
        //     $data_up->invoice   = str_replace("#####", $booking_id, $data_up->invoice);
        //  }


        $details=Invoice::join('kernel.user_table','invoice_table.user_id','=','user_table.user_id')
        ->join('hotel_booking','invoice_table.invoice_id','=','hotel_booking.invoice_id')
        ->select(DB::raw("group_concat(hotel_booking.room_type_id) as room_type_id"),DB::raw("group_concat(hotel_booking.rooms) as rooms"),
        'invoice_table.hotel_name','invoice_table.total_amount','invoice_table.paid_amount','invoice_table.invoice','invoice_table.hotel_id','invoice_table.invoice_id','invoice_table.user_id','invoice_table.booking_status','invoice_table.booking_date','invoice_table.room_type','invoice_table.check_in_out','invoice_table.extra_details','user_table.first_name','user_table.last_name','user_table.email_id','user_table.address','user_table.mobile','hotel_booking.check_in','hotel_booking.check_out','invoice_table.package_id','invoice_table.paid_service_id')->where([['invoice_table.user_id',$user->user_id],['invoice_table.hotel_id',$hotel_id],['invoice_table.booking_status',1],['hotel_booking.check_in','>=',$date],['hotel_booking.check_in','<=',$last_date]]) ->groupBy('invoice_table.invoice_id')->orderBy('invoice_table.invoice_id','DESC')->paginate(12);  
   
        foreach($details as $data_up)
        {
            $adult = array();
            $child = array(); 

            $data_up->send_invoice_id=$data_up->invoice_id;
            $booking_id     = date("dmy", strtotime($data_up->booking_date)).str_pad($data_up->invoice_id, 4, '0', STR_PAD_LEFT);
            $data_up->booking_id =  $booking_id;
            $data_up->invoice_id =  $data_up->invoice_id;
            $data_up->invoice = str_replace("#####", $booking_id, $data_up->invoice);
            $room_dlt = json_decode($data_up->room_type);
        
            if($data_up->package_id == 0){          // for Room Bookings rate plan should be there
                $room_dlt = substr($room_dlt[0],2);
                preg_match_all("/\\((.*?)\\)/", $room_dlt, $matches); 
                $rate = json_encode($matches[1][0]);
                $rate = trim($rate,'"');
                $rate_plan = DB::table('kernel.rate_plan_table')->select('rate_plan_id','plan_name')->where('plan_type',$rate)->where('hotel_id',$hotel_id)->first();
                $data_up->rate_plan = isset($rate_plan->plan_name)?$rate_plan->plan_name:'NA';
                $data_up->rate_plan_id = isset($rate_plan->rate_plan_id)?$rate_plan->rate_plan_id:0;
            }else{   
                $data_up->rate_plan = null;         // for package bookings no rate plan should not be there
                $data_up->rate_plan_id = null;
            }
            
            $data_up->display_room_type = $room_dlt;
            $extra_details = json_decode($data_up->extra_details, true);
            for($i=0;$i<sizeof($extra_details);$i++){
                $keys=array_keys($extra_details[$i]);
                $adult[] = $extra_details[$i][$keys[0]][0];
                $child[] = $extra_details[$i][$keys[0]][1]; 
            }
            $data_up->adult = $adult;
            $data_up->child = $child;

            $invoice = array();
            $room_type_id = explode(',',$data_up->room_type_id);
            $no_of_rooms = explode(',',$data_up->rooms);
            $room_rate_type = explode(',',$data_up->room_type);
            
            for($i=0;$i<sizeof($room_type_id);$i++){
                $invoice_data['room_type_id'] = $room_type_id[$i];
                $invoice_data['no_of_rooms'] = $no_of_rooms[$i];
                if($data_up->package_id == 0){
                    $room_type = trim(trim($room_rate_type[$i],'["'),'"]');
                    preg_match_all("/\\((.*?)\\)/", $room_type, $matches); 
                    $rate = $matches[1][0];
                    $rate_plan = DB::table('kernel.rate_plan_table')->select('rate_plan_id','plan_name')->where('plan_type',$rate)->where('hotel_id',$hotel_id)->first();
                    $invoice_data['rate_plan_id'] = isset($rate_plan->rate_plan_id)?$rate_plan->rate_plan_id:0;
                }else{
                    $invoice_data['rate_plan_id'] = null;
                }
                $invoice[] = $invoice_data;
            }
            $data_up->booking_object=$invoice;


            //for paid services
            $paid_services=explode(',',$data_up->paid_service_id);
            $total_paid_service_amount = 0;
            if(sizeof($paid_services)>0){
                foreach($paid_services as $pay){
                    $paid_services_amount = PaidServices::select('service_amount')->where('paid_service_id',$pay)->where('hotel_id',$hotel_id)->first();
                    $total_paid_service_amount = $total_paid_service_amount + $paid_services_amount["service_amount"];
                }
            }
            $data_up->total_paid_service_amount = $total_paid_service_amount;
            //for paid services

        }

 
         if(sizeof($details)>0)
         {
             $res=array('status'=>1,'message'=>'details retrive sucessfully','details'=>$details);
             return response()->json($res);
         }
         else{
             $res=array('status'=>0,'message'=>'details retrive fails');
             return response()->json($res);
         }
     }
      //Fetch User Login Details
    public function fetchUserLoginDetails($mobile_no,$company_id,Request $request)
    {
        $user_details=User::select('user_id','mobile','change_number_status','first_name','last_name')->where('mobile',$mobile_no)->where('company_id',$company_id)->first();

        if($user_details){
            $res = array('status'=>1,'message'=>'User Login Details fetched successfully',"user_details"=>$user_details);
            return response()->json($res);
        }else{
            $res = array('status'=>0,'message'=>'Fetch failed');
            return response()->json($res);
        }
    }
     // Cancel Booking Report
     public function fetchCancelledBookings(Request $request)
     {
         $data = $request->all();
         $hotel_id = $data['hotel_id'];
         $date = $data['date'];
         $date=date('Y-m-d',strtotime($date));
         $last_date=date('Y-m-t',strtotime($date));
 
         $failour_message="details retrive fails";
         $validator=Validator::make($request->all(),$this->select_rules,$this->select_messages);
         if ($validator->fails())
         {
             $res['errors']=$validator->errors();
             return response()->json($res);
         }
         $token=$request->input('token');
         try {
             $userdetails = JWT::decode($token, env('JWT_SECRET'), ['HS256']);
         }
         catch(ExpiredException $e) {
             return response()->json([
                 'error' => 'Provided token is expired.'
             ], 400);
         }
         $user=User::select('user_id')->where('mobile',$userdetails->sub)->where('company_id',$data['company_id'])->first();
         $details=Invoice::join('kernel.user_table','invoice_table.user_id','=','user_table.user_id')
         ->join('hotel_booking','invoice_table.invoice_id','=','hotel_booking.invoice_id')
         ->select('invoice_table.hotel_name','invoice_table.total_amount','invoice_table.paid_amount','invoice_table.invoice','invoice_table.hotel_id','invoice_table.invoice_id','invoice_table.user_id','invoice_table.booking_status','invoice_table.booking_date','invoice_table.room_type','invoice_table.check_in_out','invoice_table.updated_at','user_table.first_name','user_table.last_name','user_table.email_id','user_table.address','user_table.mobile','hotel_booking.check_in')
         ->where([['invoice_table.user_id',$user->user_id],['invoice_table.hotel_id',$hotel_id],['invoice_table.booking_status',3],['hotel_booking.check_in','>=',$date],['hotel_booking.check_in','<=',$last_date]])->paginate(12);
 
         
         foreach($details as $data_up)
         {
             $invoice_id_for_cancellation=$data_up->invoice_id;
 
             $booking_id     = date("dmy", strtotime($data_up->booking_date)).str_pad($data_up->invoice_id, 4, '0', STR_PAD_LEFT);
             $data_up->invoice           = str_replace("#####", $booking_id, $data_up->invoice);
             $data_up->invoice_id     =  $booking_id;
 
 
             $booking_date_display= date("d-m-y", strtotime($data_up->booking_date));
             $data_up->booking_date_display=$booking_date_display;
 
             $data_up->cancelled_date=date("d-m-Y", strtotime($data_up->updated_at));
             $cancelled_date=date("Y-m-d", strtotime($data_up->updated_at));
             $check_in_date=$data_up->check_in;
 
             if($check_in_date){
                 $days = abs(strtotime($check_in_date) - strtotime($cancelled_date)) / 86400;            
                 if($days >= 0){
                     $get_cancellation_policy = CancellationPolicy::where('hotel_id',$hotel_id)->first();                
                     if($get_cancellation_policy){
                         $closest = null;
                         $refund_days = $get_cancellation_policy->policy_data;
                         $refund_days=json_decode($refund_days);
                         for($i=0;$i<sizeof($refund_days);$i++){
                             $ref_data = explode(':',$refund_days[$i]);
                             $ref_per = $ref_data[1];
                             $daterange = explode('-',$ref_data[0]);
                             if($days  >= $daterange[0] && $days  <= $daterange[1]){
                                 $data_up->refund_amount = $data_up->total_amount * ( $ref_per / 100);
                             }
                             else{
                                 $data_up->refund_amount = 0;
                             } 
                         }
                     }
                     else{
                        $data_up->refund_amount = 0;
                     }  
                 }
             }
         }
         if(sizeof($details)>0)
         {
             $res=array('status'=>1,'message'=>'Cancelled bookings retrieved sucessfully','details'=>$details);
             return response()->json($res);
         }
         else{
             $res=array('status'=>0,'message'=>'Cancelled bookings retrieved fails');
             return response()->json($res);
         }
     }

    //================================================================================================
    public function getUserBookingList(Request $request)
    {
        $data = $request->all();
        $date = date('Y-m-d',strtotime($data['date']));
        $last_date = date('Y-m-t',strtotime($data['date']));
        $failour_message = "details retrive fails";
        // $validator = Validator::make($request->all(),$this->select_rules,$this->select_messages);
        // if ($validator->fails())
        // {
        //     $res['errors']=$validator->errors();
        //     return response()->json($res);
        // }
        $token=$request->input('token');
        try {
            $userdetails = JWT::decode($token, env('JWT_SECRET'), ['HS256']);
        }
        catch(ExpiredException $e) {
            return response()->json([
                'error' => 'Provided token is expired.'
            ], 400);
        }
    
        $user=User::select('user_id')->where('mobile',$userdetails->sub)->where('company_id',$data['company_id'])->first();
    
    
        $details=Invoice::join('kernel.user_table','invoice_table.user_id','=','user_table.user_id')
            ->join('hotel_booking','invoice_table.invoice_id','=','hotel_booking.invoice_id')
            ->select(DB::raw("group_concat(hotel_booking.room_type_id) as room_type_id"),
            DB::raw("group_concat(hotel_booking.rooms) as rooms"),
            'invoice_table.hotel_name','invoice_table.total_amount','invoice_table.paid_amount',
            'invoice_table.invoice','invoice_table.hotel_id','invoice_table.invoice_id','invoice_table.user_id',
            'invoice_table.booking_status','invoice_table.booking_date','invoice_table.room_type',
            'invoice_table.check_in_out','invoice_table.extra_details','user_table.first_name','user_table.last_name',
            'user_table.email_id','user_table.address','user_table.mobile','hotel_booking.check_in',
            'hotel_booking.check_out','invoice_table.package_id','invoice_table.paid_service_id')
            ->where([
                ['invoice_table.user_id',$user->user_id],
                ['invoice_table.booking_status',1],
                ['hotel_booking.check_in','>=',$date],
                ['hotel_booking.check_in','<=',$last_date]
            ])
            ->groupBy('invoice_table.invoice_id')
            ->orderBy('invoice_table.invoice_id','DESC')
            ->paginate(12);  

        foreach($details as $data_up)
        {
            $adult = array();
            $child = array(); 

            $data_up->send_invoice_id=$data_up->invoice_id;
            $booking_id     = date("dmy", strtotime($data_up->booking_date)).str_pad($data_up->invoice_id, 4, '0', STR_PAD_LEFT);
            $data_up->booking_id =  $booking_id;
            $data_up->invoice_id =  $data_up->invoice_id;
            $data_up->invoice = str_replace("#####", $booking_id, $data_up->invoice);
            $room_dlt = json_decode($data_up->room_type);
        
            if($data_up->package_id == 0){          // for Room Bookings rate plan should be there
                $room_dlt = substr($room_dlt[0],2);
                preg_match_all("/\\((.*?)\\)/", $room_dlt, $matches); 
                $rate = json_encode($matches[1][0]);
                $rate = trim($rate,'"');
                $rate_plan = DB::table('kernel.rate_plan_table')->select('rate_plan_id','plan_name')->where('plan_type',$rate)->where('hotel_id',$hotel_id)->first();
                $data_up->rate_plan = isset($rate_plan->plan_name)?$rate_plan->plan_name:'NA';
                $data_up->rate_plan_id = isset($rate_plan->rate_plan_id)?$rate_plan->rate_plan_id:0;
            }else{   
                $data_up->rate_plan = null;         // for package bookings no rate plan should not be there
                $data_up->rate_plan_id = null;
            }
            
            $data_up->display_room_type = $room_dlt;
            $extra_details = json_decode($data_up->extra_details, true);
            for($i=0;$i<sizeof($extra_details);$i++){
                $keys=array_keys($extra_details[$i]);
                $adult[] = $extra_details[$i][$keys[0]][0];
                $child[] = $extra_details[$i][$keys[0]][1]; 
            }
            $data_up->adult = $adult;
            $data_up->child = $child;

            $invoice = array();
            $room_type_id = explode(',',$data_up->room_type_id);
            $no_of_rooms = explode(',',$data_up->rooms);
            $room_rate_type = explode(',',$data_up->room_type);
            
            for($i=0;$i<sizeof($room_type_id);$i++){
                $invoice_data['room_type_id'] = $room_type_id[$i];
                $invoice_data['no_of_rooms'] = $no_of_rooms[$i];
                if($data_up->package_id == 0){
                    $room_type = trim(trim($room_rate_type[$i],'["'),'"]');
                    preg_match_all("/\\((.*?)\\)/", $room_type, $matches); 
                    $rate = $matches[1][0];
                    $rate_plan = DB::table('kernel.rate_plan_table')->select('rate_plan_id','plan_name')->where('plan_type',$rate)->where('hotel_id',$hotel_id)->first();
                    $invoice_data['rate_plan_id'] = isset($rate_plan->rate_plan_id)?$rate_plan->rate_plan_id:0;
                }else{
                    $invoice_data['rate_plan_id'] = null;
                }
                $invoice[] = $invoice_data;
            }
            $data_up->booking_object=$invoice;
            //for paid services
            $paid_services=explode(',',$data_up->paid_service_id);
            $total_paid_service_amount = 0;
            if(sizeof($paid_services)>0){
                foreach($paid_services as $pay){
                    $paid_services_amount = PaidServices::select('service_amount')->where('paid_service_id',$pay)->where('hotel_id',$hotel_id)->first();
                    $total_paid_service_amount = $total_paid_service_amount + $paid_services_amount["service_amount"];
                }
            }
            $data_up->total_paid_service_amount = $total_paid_service_amount;
        }


        if(sizeof($details)>0)
        {
            $res = array('status'=>1,'message'=>'details retrive sucessfully','details'=>$details);
            return response()->json($res);
        }
        else{
            $res = array('status'=>0,'message'=>'No Bookings found');
            return response()->json($res);
        }
    }
    //=======================================================================================
    public function getUserCancelledBookingList(Request $request)
    {
        $data = $request->all();
        $date = $data['date'];
        $date=date('Y-m-d',strtotime($date));
        $last_date=date('Y-m-t',strtotime($date));

        $failour_message="details retrive fails";
        // $validator=Validator::make($request->all(),$this->select_rules,$this->select_messages);
        // if ($validator->fails())
        // {
        //     $res['errors']=$validator->errors();
        //     return response()->json($res);
        // }
        $token=$request->input('token');
        try {
            $userdetails = JWT::decode($token, env('JWT_SECRET'), ['HS256']);
        }
        catch(ExpiredException $e) {
            return response()->json([
                'error' => 'Provided token is expired.'
            ], 400);
        }
        $user=User::select('user_id')->where('mobile',$userdetails->sub)->where('company_id',$data['company_id'])->first();
        $details=Invoice::join('kernel.user_table','invoice_table.user_id','=','user_table.user_id')
        ->join('hotel_booking','invoice_table.invoice_id','=','hotel_booking.invoice_id')
        ->select('invoice_table.hotel_name','invoice_table.total_amount','invoice_table.paid_amount',
        'invoice_table.invoice','invoice_table.hotel_id','invoice_table.invoice_id','invoice_table.user_id',
        'invoice_table.booking_status','invoice_table.booking_date','invoice_table.room_type',
        'invoice_table.check_in_out','invoice_table.updated_at','user_table.first_name','user_table.last_name',
        'user_table.email_id','user_table.address','user_table.mobile','hotel_booking.check_in')
        ->where([
            ['invoice_table.user_id',$user->user_id],
            ['invoice_table.booking_status',3],
            ['hotel_booking.check_in','>=',$date],
            ['hotel_booking.check_in','<=',$last_date]
        ])
        ->paginate(12);

        
        foreach($details as $data_up)
        {
            $invoice_id_for_cancellation=$data_up->invoice_id;

            $booking_id     = date("dmy", strtotime($data_up->booking_date)).str_pad($data_up->invoice_id, 4, '0', STR_PAD_LEFT);
            $data_up->invoice           = str_replace("#####", $booking_id, $data_up->invoice);
            $data_up->invoice_id     =  $booking_id;


            $booking_date_display= date("d-m-y", strtotime($data_up->booking_date));
            $data_up->booking_date_display=$booking_date_display;

            $data_up->cancelled_date=date("d-m-Y", strtotime($data_up->updated_at));
            $cancelled_date=date("Y-m-d", strtotime($data_up->updated_at));
            $check_in_date=$data_up->check_in;

            if($check_in_date){
                $days = abs(strtotime($check_in_date) - strtotime($cancelled_date)) / 86400;            
                if($days >= 0){
                    $get_cancellation_policy = CancellationPolicy::where('hotel_id',$hotel_id)->first();                
                    if($get_cancellation_policy){
                        $closest = null;
                        $refund_days = $get_cancellation_policy->policy_data;
                        $refund_days=json_decode($refund_days);
                        for($i=0;$i<sizeof($refund_days);$i++){
                            $ref_data = explode(':',$refund_days[$i]);
                            $ref_per = $ref_data[1];
                            $daterange = explode('-',$ref_data[0]);
                            if($days  >= $daterange[0] && $days  <= $daterange[1]){
                                $data_up->refund_amount = $data_up->total_amount * ( $ref_per / 100);
                            }
                            else{
                                $data_up->refund_amount = 0;
                            } 
                        }
                    }
                    else{
                    $data_up->refund_amount = 0;
                    }  
                }
            }
        }
        if(sizeof($details)>0)
        {
            $res=array('status'=>1,'message'=>'Cancelled bookings retrieved sucessfully','details'=>$details);
            return response()->json($res);
        }
        else{
            $res=array('status'=>0,'message'=>'Cancelled bookings retrieved fails');
            return response()->json($res);
        }
    }
}
