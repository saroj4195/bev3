<?php
namespace App\Http\Controllers\Extranetv4;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Validator;
use DB;
use App\CheckToken;
use App\Http\Controllers\Controller;

class KernelTestController extends Controller
{
    public function Test($company_id){

        $select_data = CheckToken::where('company_id' , $company_id)->first();
        $currrent_time =  date('Y-m-d  H:i:s');
        $currrent_time = strtotime($currrent_time);
        
        $expairy_time = $select_data->expair_time;
        $expairy_time = strtotime($expairy_time);

        if($currrent_time > $expairy_time){

            return response()->json(array('status'=>0,'message'=>'This token is expaired'));
        }else{
            return response()->json(array('status'=>1,'message'=>'Not expaired'));
        }

    }

}