<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Validator;
use App\UserCredential;
use App\PaidServices;
use DB;
use Webpatser\Uuid\Uuid;
use App\ImageTable;
class PaidServicesController extends Controller
{
//validation rules
    private $rules = array(
'service_name' => 'required ',
        'service_amount' => 'required | numeric',
);
    //Custom Error Messages
private $messages = [
        'service_name.required' => 'The name field is required.',
'service_amount.numeric' => 'The price field is required.',
        'service_amount.numeric' => 'The price must be numeric.',
];
/**
* Hotel  paid service
     * Create a new record of Hotel  paid services.
* @author subhradip
     * @return Hotel  paid servicesaving status
* function addNewPliciesDescription use for cerating new paid service
    **/
public function addNewPaidService(Request $request){
    $paid_services = new PaidServices();
    $failure_message='paid service Details saving failed';
    $validator = Validator::make($request->all(),$this->rules,$this->messages);
    if ($validator->fails()){
        return response()->json(array('status'=>0,'message'=>$failure_message,'errors'=>$validator->errors()));
    }
    $data=$request->all();
    $data['client_ip'] = $request->ip();
    if($paid_services->checkPaidServiceStatus($data['service_name'],$data['hotel_id'])=="new"){
        if($paid_services->fill($data)->save()){
            $res=array('status'=>1,"message"=>"Hotel paid service details saved successfully");
            return response()->json($res);
        }
        else{
            $res=array('status'=>-1,"message"=>$failure_message);
            $res['errors'][] = "Internal server error";
            return response()->json($res);
        }
    }
    else{
        $res=array('status'=>1,"message"=>"This paid service Already registered");
        return response()->json($res);;
    }
}
    /**
* Hotel paid service Details
     * Create a new record of paid service details.
*@author subhradip
     * @return paid service details saving status
* function updatePaidService use for updating paid service
    **/
public function updatePaidService(int $id,Request $request){
    $paid_services = new PaidServices();
    $failure_message="Hotel's paid service  saving failed.";
    $validator = Validator::make($request->all(),$this->rules,$this->messages);
    if ($validator->fails()){
        return response()->json(array('status'=>0,'message'=>$failure_message,'errors'=>$validator->errors()));
    }
    $data=$request->all();
    //TO get user id from AUTH token
    // if(isset($request->auth->admin_id)){
    //     $data['user_id']=$request->auth->admin_id;
    // }else if(isset($request->auth->super_admin_id)){
    //     $data['user_id']=$request->auth->super_admin_id;
    // }else if(isset($request->auth->id)){
    //     $data['user_id']=$request->auth->id;
    // }
    $paid_services = PaidServices::where('paid_service_id',$id)->first();
    if($paid_services->paid_service_id == $id){
        if($paid_services->fill($data)->save()){
            $res=array('status'=>1,"message"=>"Hotel paid service updated successfully");
            return response()->json($res);
        }else{
            $res=array('status'=>-1,"message"=>$failure_message);
            $res['errors'][] = "Internal server error";
            return response()->json($res);
        }
    }
}
/**
* Get paid service
* get one record of paid service
* @author subhradip
* function getHotelPaidServices is for getting a data.
**/
    public function getHotelPaidServices(int $hotel_id, Request $request)
    {
        $paid_services = new PaidServices();
        if ($hotel_id) {
            $conditions = array('hotel_id' => $hotel_id, 'is_trash' => 0);
            $paidServices = PaidServices::where($conditions)->get();

            if($paidServices){
                foreach($paidServices as $paidService){
                    $images = ImageTable::where('image_id', $paidService->image)->first();
                    $images_url = '';
                    if ($images) {
                        $images_url = 'https://d3ki85qs1zca4t.cloudfront.net/bookingEngine/' . $images->image_name;
                    }
                    $paidService->image = $images_url;
                }
            }


            if (count($paidServices) > 0) {
                $res = array('status' => 1, "message" => "Hotel's paid service retrieved successfully", 'paidServices' => $paidServices);
                return response()->json($res);
            } else {
                $res = array('status' => 0, "message" => "No paid service  records found");
                return response()->json($res);
            }
        } else {
            $res = array('status' => -1, "message" => "paid service  fetching failed");
            return response()->json($res);
        }
    }
/**
* Get paid service
* get one record of paid service
* @author subhradip
* function getHotelPaidServices is for getting a data.
**/
public function getHotelPaidService(int $paid_service_id ,Request $request)
{
$paid_services=new PaidServices();
if($paid_service_id)
{
$conditions=array('paid_service_id'=>$paid_service_id,'is_trash'=>0);
$paidService=PaidServices::where($conditions)->first();
if($paidService)
{
$res=array('status'=>1,"message"=>"Hotel's paid service retrieved successfully",'paidService'=>$paidService);
return response()->json($res);
}
else
{
$res=array('status'=>0,"message"=>"No paid service  records found");
return response()->json($res);
}
}
else
{
$res=array('status'=>-1,"message"=>"paid service  fetching failed");
return response()->json($res);
}
}
/**
* Hotel paid service Details
*Delete of paid service details.
*@author Godti Vinod
* @return paid service details delete status
* function updatePaidService use for updating paid service
**/
public function DeletePaidServices(int $paid_service_id,Request $request)
{
$paidservices = new PaidServices();
$failure_message="Hotel's paid service deletion failed.";
$paid_services = PaidServices::where('paid_service_id',$paid_service_id)->first();
if($paid_services->paid_service_id == $paid_service_id )
{
if(PaidServices::where('paid_service_id',$paid_service_id)->update(['is_trash' => 1]))
{
                    $res=array('status'=>1,"message"=>"Hotel paid service deleted successfully");
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
$res['errors'][] = "Internal server error";
            return response()->json($res);
}

}
}
