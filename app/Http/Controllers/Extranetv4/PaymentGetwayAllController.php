<?php
namespace App\Http\Controllers\Extranetv4;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Validator;
use DB;
use App\PaymentGetway;
use App\Http\Controllers\Controller;

class PaymentGetwayAllController extends Controller
{
       private $rules = array(
              'company_id'=>'required | numeric',
              'provider_name'=>'required',
       );
       //Custom Error Messages
       private $messages = [
              'company_id.required' => 'The company id  field is required.',
              'company_id.numeric' => 'The company id should be numeric.',
              'provider_name.required' => 'The username  field is required.',

       ];
       private $update_rules=array(
              'status'=>'required'
       );
       private $update_messages=[
              'status.required'=>'status shoud be required'
       ];
       //================================================================================================================================
       public function PaymentGetwaySelectById(int $company_id,Request $request)
       {
              $paymentGetwayselect=new PaymentGetway();
              if($company_id)
              {
                     $condition = array('company_id'=>$company_id);
                     $paymentGetway = PaymentGetway::join('booking_engine.payment_gateways','payment_gateway_details.provider_name','=','booking_engine.payment_gateways.pg_name')
                     ->where($condition)
                     ->get();
                     if(sizeof($paymentGetway)>0)
                     {
                            $res = array('status'=>1,'message'=>'PaymentGetway details retrive sucessfully','paymentGetway'=>$paymentGetway[0]);
                            return response()->json($res);
                     }
                     else{
                            $res = array('status'=>0,'message'=>'PaymentGetway details retrive fails');
                            return response()->json($res);
                     }
              }
              else{
                     $res=array('status'=>-1,'message'=>'PaymentGetway details fetching fails');
                     $res['error'][]="PaymentGetway id is not provided";
                     return response()->json($res);
              }
       }
       //================================================================================================================================
       public function PaymentGetwaySelect(Request $request)
       {      
              $paymentGetwayselect=new PaymentGetway();
              $paymentGetway=PaymentGetway::join('kernel.company_table','payment_gateway_details.company_id','=','company_table.company_id')->get();  
              if(sizeof($paymentGetway)>0)
              {
                     $res=array('status'=>1,'message'=>'PaymentGetway details retrive sucessfully','paymentGetway'=>$paymentGetway);
                     return response()->json($res);
              }
              else{
                     $res=array('status'=>0,'message'=>'PaymentGetway details retrive fails');
                     return response()->json($res);
              }
       }
       //================================================================================================================================
       public function PaymentGetwaySelectByName(string $provider_name,Request $request)
       {
              $paymentGetwayselect=new PaymentGetway();
              if($provider_name)
              {
                     $paymentGetway=PaymentGetway::where('provider_name','like','%'.$provider_name.'%')->get();
                     if(sizeof($paymentGetway)>0)
                     {
                            $res=array('status'=>1,'message'=>'PaymentGetway details retrive sucessfully','paymentGetway'=>$paymentGetway);
                            return response()->json($res);
                     }
                     else{
                            $res=array('status'=>0,'message'=>'PaymentGetway details retrive fails');
                            return response()->json($res);
                     }
              }
              else{
                     $res=array('status'=>-1,'message'=>'PaymentGetway details fetching fails');
                     $res['error'][]="PaymentGetway id is not provided";
                     return response()->json($res);
              }
       }
       //================================================================================================================================
       public function paymentGetwayInsert(Request $request)
       {
              $paymentinsert=new PaymentGetway();
              $failure_message='payment getway details saving failed';
              $validator = Validator::make($request->all(),$this->rules,$this->messages);
              if ($validator->fails())
              {
                     return response()->json(array('status'=>0,'message'=>$failure_message,'errors'=>$validator->errors()));
              }
              $data=$request->all();
              $data['user_id']=$request->auth->super_admin_id;
              $payment_details_insert=PaymentGetway::where('company_id',$data['company_id'])->count('company_id');
              if($payment_details_insert>0)
              {
                     $res=array('status'=>0,'message'=>'sorry! the company already exit with this provider name');
                     return response()->json($res);
              }
              else{
                     if($paymentinsert->fill($data)->save())
                     {
                            $res=array('status'=>1,'message'=>'insert sucessfully');
                            return response()->json($res);
                     }
                     else{
                            $res=array('status'=>1,'message'=>'insert fails');
                            return response()->json($res);
                     }
              }

       }
       //================================================================================================================================
       public function paymentGetwayUpdate(int $id,Request $request)
       {
              $failure_message="update into paymentgetway table fails";
              $validator=Validator::make($request->all(),$this->update_rules,$this->update_messages);
              if($validator->fails())
              {
                     $res=array('status'=>0,'message'=>$failure_message,'error'=>$validator->errors());
                     return response()->json($res);
              }
              $data=$request->all();
              $payment_getway_update= PaymentGetway::where('id',$id)->first();
              if($payment_getway_update->id == $id)
              {
                     if($payment_getway_update->fill($data)->save())
                     {
                     $res=array('status'=>1,'message'=>'update sucessfully in to paymentgeyway table');
                     return response()->json($res);
                     }
                     else{
                            $res=array('status'=>-1,'message'=>$failure_message);
                            $res['error'][]="internal server error";
                            return response()->json($res);
                     }
              }
       }
       //================================================================================================================================
}
