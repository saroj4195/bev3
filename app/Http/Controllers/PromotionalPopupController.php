<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Validator;
use App\UserCredential;
use App\PromotionalPopup;//class name from model
use DB;
use Webpatser\Uuid\Uuid;
class PromotionalPopupController extends Controller
{
private $rules = array(
        'display_type' => 'required  ',
'banner_type' => 'required ',
     
);
    //Custom Error Messages
private $messages = [
        'display_type.required' => 'The display type field is required.',
'banner_type.required' => 'The banner type field is required.'
            ];
/**
     * promotional popup Details
* Create a new record of promotional popup details.
     * @auther subhradip
* @return coupons details saving status
     *  function addNewPromo use for cerating new coupons
**/
     
public function addNewPromo(Request $request)
    {
$promotionalpopup = new PromotionalPopup();
        $failure_message='Promotional popup details saving failed';
$validator = Validator::make($request->all(),$this->rules,$this->messages);
        if ($validator->fails())
{
            return response()->json(array('status'=>0,'message'=>$failure_message,'errors'=>$validator->errors()));
}
        $data=$request->all();
//TO get user id from AUTH token
        if(isset($request->auth->admin_id)){
$data['user_id']=$request->auth->admin_id;
        }else if(isset($request->auth->super_admin_id)){
$data['user_id']=$request->auth->super_admin_id;
        }
else if(isset($request->auth->id)){
            $data['user_id']=$request->auth->id;
}
        if($promotionalpopup->checkCoupons($data['coupon_id'])=="new")
{
            if($promotionalpopup->fill($data)->save())
{
                $res=array('status'=>1,'message'=>"Promotional popup details saved successfully");
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
            $res=array('status'=>1,"message"=>"Coupons already exists");
return response()->json($res);
        }
}
    /**
* Delete promotional popup
     * delete record of promotional popup
* @author subhradip
     * @return promotional popup deleting status
* function DeletePromo used for delete
    **/
public function DeletePromo(int $promo_id ,Request $request)
    {  
$failure_message='Deleted Failure';
        if(PromotionalPopup::where('promo_id',$promo_id)->update(['is_trash' => 1]))
{
            $res=array('status'=>1,"message"=>'Promotional popup deleted successfully');         
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
* promotional popup
     * Update record of promotional popup
* @author subhradip
     * @return promotional popup  saving status
* function UpdatePromo use for update
    **/
public function UpdatePromo(int $coupon_id,Request $request)
    {
$promotionalpopup = new PromotionalPopup();
        $failure_message="Promotional popup  saving failed.";
$validator = Validator::make($request->all(),$this->rules,$this->messages);
        if ($validator->fails())
{
            return response()->json(array('status'=>0,'message'=>$failure_message,'errors'=>$validator->errors()));
}
        $data=$request->all();
// dd($data);
        $promotionalpopup = PromotionalPopup::where('coupon_id',$coupon_id)->first();
if($promotionalpopup->coupon_id == $coupon_id )
        {
if($promotionalpopup->fill($data)->save())
            {
// $res=array('status'=>1,"message"=>"Promotional popup updated successfully");
                $res=array('status'=>1,'message'=>"Promotional popup details saved successfully");
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
     * Get  promotional popup
* get one record of  promotional popup
     * @author subhradip
* function GetPromo for delecting data
    **/
public function GetPromo(int $coupon_id ,Request $request)
    { 
$promotionalpopup=new PromotionalPopup();
        if($coupon_id)
{ 
            $conditions=array('coupon_id'=>$coupon_id,'is_trash'=>0);
$data=PromotionalPopup::where($conditions)->get();       
            if(sizeof($data)>0)
{  
                $res=array('status'=>1,'message'=>"Promotional popup details found",'res'=>$data);
return response()->json($res);
            }
else
            {   
$res=array('status'=>0,"message"=>"No Promotional popup found");
                return response()->json($res);
}
        }
else
        {
$res=array('status'=>-1,"message"=>"Promotional popup  fetching failed");
            return response()->json($res); 
}       
    }
/**
* Get all promotional popup
* get All record of promotional popup
* @author subhradip
* function GetAllPromo for selecting all data
**/
public function GetAllPromo(Request $request)
{
$promotionalpopup=new PromotionalPopup();
$conditions=array('is_trash'=>0);
$res=PromotionalPopup::where($conditions)->get();
if(sizeof($res)>0)
{   
return response()->json($res);
}
else
{   
$res=array('status'=>0,"message"=>"No Promotional popup  records found");
return response()->json($res);
} 
} 
/**
* Get all promotional popup of hotel
* get All record of promotional popup
* @author subhradip
* function GetAllPromo for selecting all data
**/
public function GetPromoByHotel(int $hotel_id,Request $request)
{
$promotionalpopup=new PromotionalPopup();
$conditions=array('hotel_id'=>$hotel_id,'is_trash'=>0);
$res=PromotionalPopup::where($conditions)->get();
if(sizeof($res)>0)
{   
return response()->json($res);
}
else
{   
$res=array('status'=>0,"message"=>"No Promotional popup  records found");
return response()->json($res);
} 
} 
}