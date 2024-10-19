<?php
namespace App\Http\Controllers\crs;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Firebase\JWT\JWT;
use Firebase\JWT\ExpiredException;
use Validator;
use DB;
use App\AdminUser;
use App\HotelInformation;
use App\Http\Controllers\Controller;

class AgentController extends Controller
{

     /**
    * The header name.
    *
    * @var string
    */
    protected $header = 'authorization';
    /**
     * The header prefix.
     *
     * @var string
     */
    protected $prefix = 'bearer';
     /**
     * Custom parameters.
     *
     * @var \Symfony\Component\HttpFoundation\ParameterBag
     *
     * @api
     */
    public $attributes;
    protected function fromAltHeaders(Request $request)
    {
        return $request->server->get('HTTP_AUTHORIZATION') ?: $request->server->get('REDIRECT_HTTP_AUTHORIZATION');
    }
    private $rules=array(
            'email'=>'required | email',
            'password' => array(
                'required',
                'min:6',
                'regex:/^[A-Za-z0-9_~\-!@#\$%\^&*\(\)]+$/'
                     )
    );
    private $messages=[
        'email.required'=>'The email must be required and not be empty',
        'password.required'=>'The password must be required and  not be empty',
        'password.regex'=>'The password must be uppercase/lowercase'
    ];
    private $rules_fpassword = array(
        'username' => 'required | email',

     );
     private $fmessages=[
         'username.required' => 'username field must not be empty',
     ];
     private $rules_changePassword = array(
        'password' => array(
            'required',
            'min:6',
            'regex:/^[A-Za-z0-9_~\-!@#\$%\^&*\(\)]+$/'
        )
    );
    private $messages_changePassword = [
        'password.required'=>'The password field is required it should not be null',
        'password.regex' => 'The password should contain at least one uppercase/lowercase letters and one number.'
    ];
    protected function jwt($email,$exptime,$tp,$scope,$hot_id) {
        $xsrftoken = md5(uniqid(rand(), true));
        $payload = [
            'iss' => env('APP_DOMAIN'), // Issuer of the token
            'sub' => $email, // Subject of the token
            'iat' => time(), // Time when JWT was issued.
            'exp' => $exptime, // Expiration time
            'type'=>$tp,//super admin
            'hot_id' => $hot_id, // Hotel ID ===> It is applicable for Public Login Otherwise it will be {zero}
            'scope'=>$scope,
            'xsrfToken' => $xsrftoken,
        ];
        return JWT::encode($payload, env('JWT_SECRET'));
    }

   public function agentLogin(Request $request)
   {
            $failure_message='Authentication failed';
            $res['status']="0";
            $res['message']=$failure_message;
            $hotel_user=new AdminUser();
            $validator = Validator::make($request->all(),$this->rules,$this->messages);
            if ($validator->fails())
            {
                $res['errors']=$validator->errors();
                return response()->json($res);
            }
            if($hotel_user->checkEmailDuplicacy($request->input('email'))=='Exist')
            {
                $user = AdminUser::where('username', $request->input('email'))->where('role_id',4)->first();
                if($user)
                {
                    $exp_time = strtotime("+1 day");//Expirition time
                    $tp="Agent";
                    $scope=['login'];

                    if (Hash::check($request->input('password'), $user->password)) {
                            $token = $this->jwt($user->username,$exp_time,$tp,$scope,0);
                            $res['status']="1";
                            $res['message']='User authentication successful';
                            $res['auth_token']=$token;
                            $res['role_id']="4";
                            $res['company_id']=$user->company_id;
                            $res['hotel_id'] = $user->hotel_id;
                            $res['admin_id']=$user->admin_id;
                            $res['full_name']=$user->first_name." ".$user->last_name;
                            return response()->json($res);
                    }
                    else
                    {
                        $res['errors'][]='Invalid credentials';
                        return response()->json( $res);
                    }
                }
                else
                {
                    $res['errors'][]="Invalid credentials";
                    return response()->json( $res);
                }

            }
            else
            {
                $res['errors'][]="Invalid credentials";
                return response()->json( $res);
            }
   }
   public function forgotPasswordAgent(Request $request)
   {
       $forgetpassword=new AdminUser();
       $failure_message="fail to get data";
       $validator=Validator::make($request->all(),$this->rules_fpassword,$this->fmessages);
       if($validator->fails())
       {
           return response()->json(array('status'=>0,'message'=>$failure_message,'error'=>$validator->errors()));
       }
       $data=$request->all();
       $email=trim($request->input('username'));
       if($forgetpassword->checkEmailDuplicacyWithType($email)=='Exist')
       {
           $exp_time=time()*12*60*60;//expiritation time
           $tp="Agent";
           $scope=['password reset'];
           $token=$this->jwt($email,$exp_time,$tp,$scope,0);

           $verificationCode=env('APP_DOMAIN').'/agent/reset_password/'.$token;
          // $temp_mail = new TempMailCode();
           if($forgetpassword->sendMail($email,'emails.passwordResetTemplate', "Bookingjini Password Reset", $verificationCode))
           {

               $res=array('status'=>1,"message"=>'Password reset email sent successfully');
               return response()->json($res);
           }
           else
           {
               $res=array('status'=>-1,"message"=>$failure_message);
               $res['errors'][] = "Password reset email sending failed";
               return response()->json($res);
           }


       }
           else//unexpected failure
           {
               $res=array('status'=>0,"message"=>$failure_message);
               $res['errors'][] = "Invalid Email ID";
               return response()->json($res);
           }
   }
   public function changePasswordAgent(Request $request)
   {

       $hotel_user = new AdminUser();
       $failure_message='Password reset failed';
       $scope=array();
       $scope=$request->get('scope');
       $token=$request->input('token');
       $validator=Validator::make($request->all(),$this->rules_changePassword,$this->messages_changePassword);
       if($validator->fails())
       {
           return response()->json(array('status'=>0,'message'=>$failure_message,'error'=>$validator->errors()));
       }

       try {
           $admindetails = JWT::decode($token, env('JWT_SECRET'), ['HS256']);
       } catch(ExpiredException $e) {
           return response()->json([
               'error' => 'Provided token is expired.'
           ], 400);
       }
        $user = AdminUser::where('username', $admindetails->sub)->first();

       if($scope[0]=env('RESET_PASSWORD'))
       {

           if(AdminUser::where('username',$user->username)->update(['password' => Hash::make($request->input('password')) ]))
           {

               $res=array('status'=>1,"message"=>'Password reset successfully');
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
           $res=array('status'=>-1,"message"=>$failure_message);
           $res['errors'][] = "Verification token is invalid";
           return response()->json($res);
       }
   }
   public function verifyUser(Request $request)
   {
       $header = $request->headers->get($this->header) ?: $this->fromAltHeaders($request);
       if ($header && preg_match('/'.$this->prefix.'\s*(.*)\b/i', $header, $matches))
       {
           $token=$matches[1];
       }
       else
       {
           $token=false;
       }
       if(!$token)
       {
           return response()->json("NoAuth", 200);
       }
       try {
           $credentials = JWT::decode($token, env('JWT_SECRET'), array('HS256'));
       } catch(ExpiredException $e) {
           return response()->json([
               'error' => 'Provided token is expired.'
           ], 200);
       }
       catch(Exception $e) {
           return response()->json([
               'error' => 'An error while decoding token.'
           ], 401);
       }
       if($credentials)
       {
           return response()->json('Token verified', 200);
       }
       else
       {
           return response()->json([
               'error' => 'An error while decoding token.'
           ], 401);
       }
   }
   public function getAgentHotels(Request $request){
    $hotelData = AdminUser::join('hotels_table','admin_table.hotel_id','=','hotels_table.hotel_id')
    ->where('username', $request->auth->username)
    ->where('role_id', 4)//Agent role
    ->select('hotel_name','hotels_table.hotel_id','admin_table.company_id')
    ->distinct()->get();
    if($hotelData){
        $resp=array("status"=>1,"message"=>"Hotels fetched successfully","data"=>$hotelData);
    }else{
        $resp=array("status"=>0,"message"=>"No hotels found");
    }
    return response()->json($resp, 200);
    }
}