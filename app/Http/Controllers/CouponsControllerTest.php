<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Validator;
use App\UserCredential;
use App\Coupons;//class name from model
use App\UsedCoupon;
use App\MasterRoomType;
use DB;
class CouponsControllerTest extends Controller
{
private $rules = array(
        'coupon_name' => 'required ',
        'coupon_for' => 'required ',
        'valid_from' => 'required ',
        'valid_to' => 'required ',
        'discount' => 'required ',
        'hotel_id' =>'required'
    );
private $getCouponRules = array(
        'hotel_id' => 'required ',
        'checkin_date' => 'required',
        'checkout_date' => 'required',
        'room_type_id' => 'required '
    );
private $checkCouponRules = array(
        'hotel_id' => 'required',
        'checkin_date' => 'required',
        'checkout_date' => 'required',
        'code' => 'required'
    );
//Custom Error Messages
    private $messages = [
        'coupon_name.required' => 'The coupon name field is required.',
        'coupon_for.required' => 'The coupon for field is required.',
        'valid_from.required' => 'The valid from field is required.',
        'valid_to.required' => 'The valid to field is required.',
        'discount.required' => 'The discount field is required.',
        'hotel_id.required' =>'Hotel id is required',
        'avail_date.required'=>'Coupon date is required',
        'room_type_id.required'=>'Room type id is required',
        'code.required' =>'Coupon code is required',
        'hotel_id.required' =>'Hotel id is required'
     ];

/**
     * coupons Details
* Create a new record of coupons details.
     * @auther ranjit
* @return coupons details saving status
     *  function addNewCoupons use for cerating new coupons
**/
     //validation rules
public function addNewCoupons(Request $request)
{
      $coupons = new Coupons();
      $failure_message='Coupons details saving failed';
      $validator = Validator::make($request->all(),$this->rules,$this->messages);
            if ($validator->fails())
            {
                    return response()->json(array('status'=>0,'message'=>$failure_message,'errors'=>$validator->errors()));
            }
      $data=$request->all();
      //TO get user id from AUTH token
      $user_id="";
      if(isset($request->auth->admin_id)){
              $data['user_id']=$request->auth->admin_id;
      }else
      if(isset($request->auth->super_admin_id)){
              $data['user_id']=$request->auth->super_admin_id;
      }
      else
      if(isset($request->auth->id)){
              $data['user_id']=$request->auth->id;
      }
      $data['coupon_code'] = $data['coupon_code'];
      $valid_from=$data['valid_from'];
      $valid_to=$data['valid_to'];
      $data['valid_from']=date('Y-m-d', strtotime($valid_from));
      $data['valid_to']=date('Y-m-d', strtotime($valid_to));
      if($coupons->fill($data)->save()){
          try{
            $url = "https://google.bookingjini.com/api/coupon-google-ads/coupon-add";
            // $postdata = http_build_query($data);
            $addcoupon_data = $this->curlCall($url,$data);
            dd($addcoupon_data);
          }
          catch(Execption $e){

          }
          
          $res=array('status'=>1,"message"=>"Coupons details saved successfully");
          return response()->json($res);
      }
      else{
              $res=array('status'=>-1,"message"=>$failure_message);
              $res['errors'][] = "Internal server error";
              return response()->json($res);
      }
    }
/**
     * Delete coupons
* delete record of coupons
     * @author Ranjit
* @return coupons deleting status
     * function DeleteCoupons used for delete
**/
    public function DeleteCoupons(int $coupon_id ,Request $request)
    {
        $failure_message='Coupons Deletion failed';
        if(Coupons::where('coupon_id',$coupon_id)->update(['is_trash' => 1]))
                {
                        $res=array('status'=>1,"message"=>'Coupons Deleted successfully');
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
     * coupons
* Update record ofcoupons
     * @author Ranjit
* @return coupons  saving status
     * function Updatecoupons use for update
**/
    public function Updatecoupons(int $coupon_id,Request $request)
    {
        $coupons = new Coupons();
        $failure_message="Coupons  updation failed.";
        $validator = Validator::make($request->all(),$this->rules,$this->messages);
        if ($validator->fails())
        {
                return response()->json(array('status'=>0,'message'=>$failure_message,'errors'=>$validator->errors()));
        }
        $data=$request->all();
        $valid_from=$data['valid_from'];
        $valid_to=$data['valid_to'];
        $data['valid_from']=date('Y-m-d', strtotime($valid_from));
        $data['valid_to']=date('Y-m-d', strtotime($valid_to));
        $coupons = Coupons::where('coupon_id',$coupon_id)->first();
        if($coupons->coupon_id == $coupon_id )
        {
                if($coupons->fill($data)->save())
                {
                        $res=array('status'=>1,"message"=>"Coupons updated successfully");
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
     public function curlCall($url,$postdata){
            $ch = curl_init();
            curl_setopt( $ch, CURLOPT_URL, $url );
            curl_setopt( $ch, CURLOPT_POST, true );
            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
            curl_setopt( $ch, CURLOPT_POSTFIELDS, $postdata);
            $status = curl_exec($ch);
            curl_close($ch);
            $response = json_decode($status);
            return $response;
     }
}
