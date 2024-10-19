<?php
namespace App\Http\Controllers\Extranetv4;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Validator;
use App\BeSettings;
use DB;
use App\Http\Controllers\Controller;
class BeregistrationController extends Controller
{
  public function updateBookingPageDetails(int $company_id,Request $request){
      $data=array();
      $info=$request->all();
      $value=json_decode($info["data"]);
      $data['home_url']=$value->home_url;
      $data['logo']=$value->logo;
      $data['banner']=$value->banner;
      if($request->hasFile('file')){
            $filePath = 'bookingEngine/css/'.$company_id;
            if(Storage::disk('s3')->put($filePath, file_get_contents($request->file('file')),'public')){
              $res=array('status'=>1,"message"=>"Booking page details updated successfully");
              return response()->json($res);
            }
            else{
              $res=array('status'=>0,"message"=>$failure_message);
              $res['errors'][] = "Internal server error";
              return response()->json($res);
            }
        }
    }
}
