<?php
namespace App\Http\Controllers\Extranetv4;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Validator;
use App\UserCredential;
use App\TaxDetails;//found from model
use DB;
use Webpatser\Uuid\Uuid;
use App\Http\Controllers\Controller;
class TaxDetailsController extends Controller
{   
//validation rules
    //edited by ranjit
private $rules = array(
        /*'currency_id' => 'required |  numeric',
'pan_card' => array(
                'required',
'regex:/^([a-zA-Z]){5}([0-9]){4}([a-zA-Z]){1}?$/'
),
'gst_no' => array(
'required',
'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[A-Z0-9]{3}$/'
),*/
'tax_name'=>'required',
'tax_percent'=>'required ',
'hotel_id'=>'required'
);
//Custom Error Messages
private $messages = [
'tax_name.required'=>'tax_name is required',
'tax_percent.required'=>'tax percent is required.',
'hotel_id.required' => 'The hotel id  is missing.'
];
/**
* Hotel  Tax Details
     * Create a new record of Hotel  Tax Details.
* @author subhradip
     * @return Hotel  Tax Details saving status
* function addNewPliciesDescription use for cerating new paid service
    **/
public function addNewTaxDetails(Request $request)
    {   
$taxdetails = new TaxDetails();
        $failure_message='Finance details saving failed';
$validator = Validator::make($request->all(),$this->rules,$this->messages);
        if($validator->fails())
{
            return response()->json(array('status'=>0,'message'=>$failure_message,'errors'=>$validator->errors()));
}
        $data=$request->all();
$data['tax_name']=implode(',',$data['tax_name']);
        $data['tax_percent']=implode(',',$data['tax_percent']);
if($taxdetails->checkTaxDetails($data['hotel_id'])=="new")
        { 
if($taxdetails->fill($data)->save())
            {
$res=array("status"=>1,"message"=>"Finance details saved successfully");
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
$res=array('status'=>1,"message"=>"Finance details already exist");
            return response()->json($res);
}
    }
/**
     * Hotel Tax Details
* Update a  record of Tax Details
     * @author subhradip
* @return Tax Details saving status
     * function updateTaxDetails use for updating Tax
**/
    public function updateTaxDetails(Request $request)
{
        $taxDetails = new TaxDetails();
$failure_message="Finance details  saving failed.";
        $validator = Validator::make($request->all(),$this->rules,$this->messages);
if ($validator->fails())
        {
return response()->json(array('status'=>0,'message'=>$failure_message,'errors'=>$validator->errors()));
        }
$data=$request->all();
        $data['tax_name']=implode(',',$data['tax_name']);
$data['tax_percent']=implode(',',$data['tax_percent']);
        $tax_details = TaxDetails::where('hotel_id',$data['hotel_id'])->first();
if($tax_details->hotel_id == $data['hotel_id'] )
        { 
if($tax_details->fill($data)->save())
{
$res=array('status'=>1,"message"=>"Finance details updated successfully");
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
* Get Tax Details
* get one record of Tax Details
* @author subhradip
* function getTaxDetails is for getting a data.
**/
public function getTaxDetails(int $hotel_id ,Request $request)
{
$taxDetails=new TaxDetails();
if($hotel_id)
{ 
$conditions=array('hotel_id'=>$hotel_id,'is_trash'=>0);
$finDetails=TaxDetails::where($conditions)->first();       
if(sizeof($finDetails)>0)
{  
$res=array('status'=>1,"message"=>"Fianance details retrived successfully",'finDetails'=>$finDetails);
return response()->json($res);
}
else
{   
$res=array('status'=>0,"message"=>"No tax records found");
return response()->json($res);
}
}
else
{
$res=array('status'=>-1,"message"=>"Tax details fetching failed");
return response()->json($res); 
}       
}
}