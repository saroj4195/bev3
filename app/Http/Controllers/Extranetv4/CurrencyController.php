<?php
namespace App\Http\Controllers\Extranetv4;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Validator;
use DB;
use App\Currency;
use App\CurrencyDetails;
use App\CompanyDetails;
use App\CurrencyInfo;
use App\Http\Controllers\Controller;
class CurrencyController extends Controller
{
     public function currencyDetails($amount,$currency_name,$base_currency)
     {
        $currency_value=CurrencyDetails::select('currency_value','updated_at')->get();
        $currency_array=array();
        $cur_price=array();
        $i=0;
        
        foreach($currency_value as $curs)
        {
              $currency_array[$i]=$curs->currency_value;
              $i++;
        }
        //Convert amount to base currency price
        if($base_currency=='USD'){
          $amount=$amount/$currency_array[0];
        }elseif($base_currency=='EUR'){
          $amount=$amount/$currency_array[1];
        }
        elseif($base_currency=='AUD'){
          $amount=$amount/$currency_array[2];
        }
        elseif($base_currency=='GBP'){
          $amount=$amount/$currency_array[3];
        }
        elseif($base_currency=='BDT'){
          $amount=$amount/$currency_array[5];
        }else{
          $amount=$amount;
        }

        //Convert to desired currency price
        if($currency_name == 'USD'){
          $amount=$amount*$currency_array[0];
          return  $amount;
        }
        else if($currency_name == 'EUR'){
            $amount=$amount*$currency_array[1];
            return  $amount;
        }
        else if($currency_name == 'AUD'){
          $amount=$amount*$currency_array[2];
          return  $amount;
        }
        else if($currency_name == 'GBP'){
            $amount=$amount*$currency_array[3];
            return  $amount;
        }
        else if($currency_name == 'BDT'){
          $amount=$amount*$currency_array[5];
          return  $amount;
      }
        else{
            return  $amount;
        }
     }
     public function getCurrencyDetails(string $currency_name,string $base_currency_name,Request $request)
     {
          $today_date=date('Y-m-d');
          $data=Currency::select('auth_parameter','url','updated_at')->first();
          $currency_update=CurrencyDetails::select('updated_at')->first();
          $updated_date=date("Y-m-d",strtotime($currency_update->updated_at));
          $today = date('Y-m-d');
          $check = CurrencyDetails::select('*')->whereDate('created_at',$today)->first();
          if(empty($check)){
            $getBDT_data = $this -> getBDT();
          }
          if($updated_date != $today_date)
          {
                $token=json_decode($data->auth_parameter);
                $access_token=trim($token->access_token);
                $currency=trim($token->currency);
                $commonData=$data->url;
                $url=$commonData.'?token='.$access_token.'&currency='.$currency;


                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
                $headers = array();
                $headers[] = "Content-Type: application/json";
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                $result = curl_exec($ch);
                if (curl_errno($ch)) {
                    echo 'Error:' . curl_error($ch);
                }
                curl_close ($ch);
                $array_data=json_decode($result);
                $i=1;
                foreach($array_data->currency as $info)
                {
                  $update=CurrencyDetails::where('id',$i)->update(['currency_name'=>$info->currency,'currency_value'=>$info->value]);
                    $i++;
                }
          }
          //Requested currency value
          $requested_currency=CurrencyDetails::select('currency_value','updated_at')->where('name',$currency_name)->first();
          //base currency value
          $base_currency=CurrencyDetails::select('currency_value','updated_at')->where('name',$base_currency_name)->first();
          //Required currency value
          $currency_value=$requested_currency['currency_value']/$base_currency['currency_value'];

          if(!empty($currency_value))
          {
            $res=array('status'=>1,"message"=>"currency details fetched","currency_value"=>$currency_value);
            return response()->json($res);
          }
          else{
            $res=array('status'=>0,"message"=>"currency details fetch failed");
            return response()->json($res);
          }
     }
     public function getCurrencyName(int $company_id,Request $request)
     {
        $currencyname= CompanyDetails::where('company_id',$company_id)->select('currency','hex_code')->first();

        if($currencyname)
        {
          $res=array('status'=>1,"message"=>"currency name fetched","currency_name"=>$currencyname['currency'],'hex_code'=>$currencyname['hex_code']);
          return response()->json($res);
        }
        else{
          $res=array('status'=>0,"message"=>"currency name fetch failed");
          return response()->json($res);
        }
     }
     public function getCurrencyInfo(Request $request){
      $getCurrencyDetails = CurrencyInfo::select('*')
                            ->get();
      if(sizeof($getCurrencyDetails) > 0){
          $resp = array('status'=>1,'message'=>'currency details fetch successfully','data'=>$getCurrencyDetails);
      }
      else{
        $resp = array('status'=>0,'message'=>'currency details fetch fails');
      }
      return response()->json($resp);
   }
   public function getBDT(){

    // $amount = 1;
    // $from   = 'INR';
    // $to     = 'BDT';
    // $url    = "https://free.currconv.com/api/v7/convert?q=INR_BDT&compact=ultra&apiKey=61dbf14b0c0a691f513c";

    // $ch = curl_init();
    // curl_setopt($ch, CURLOPT_URL, $url);
    // curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    // curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
    // $result = curl_exec($ch);
    // if (curl_errno($ch)) {
    //     echo 'Error:' . curl_error($ch);
    // }
    // curl_close ($ch);
    // $array_data=json_decode($result);
    // $currency = $array_data->INR_BDT;
    $currency = '1.16323';
    if($currency != 0){
      $updateBDT = CurrencyDetails::where('id',6)
                    ->update(['currency_value'=>$currency]);
      if($updateBDT){
        return "success";
      }
      else{
        return "fails";
      }
    }
}
}
