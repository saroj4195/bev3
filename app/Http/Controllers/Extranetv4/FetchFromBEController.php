<?php
namespace App\Http\Controllers\Extranetv4;
use App\Customer;
use App\CustomerDetails;
use App\Organisation;
use Eloquent;
use App\Grouping;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Model\Commonmodel;
use App\Http\Controllers\Controller;

/** 
 * @Controller Name: Fetch From BE Controller
 * @author         : Jigyans Lal Singh
 * @Created Date   : 17.02.2022
 * @purpose        : This controller use for fetch customer data of a hotel from Booking Engine.
 * 
 *  $bookingengine_data_roomtype = Commonmodel::curlGet($bookingengine_rateplan_url);
 * **/

class FetchFromBEController extends Controller
{
    public function FetchFromBe()//@ This function use for fetch customer data of a hotel from Booking Engine
    {
        $customer_details = DB::table('kernel.user_table')->where('is_push_crm', 0)->get()->toArray();
        $resultsingle = array();
        $result = array();
        $status = "";
        if(!empty($customer_details)) 
		{
			foreach($customer_details as $RsData)
			{
				$resultsingle['id'] = $RsData->user_id;
                $resultsingle['source_id'] = 2;
				$resultsingle['customer_name'] = $RsData->first_name." ".$RsData->last_name;
				$resultsingle['customer_email'] = $RsData->email_id;
				$resultsingle['customer_phone'] = $RsData->mobile;
                $invoice_details = DB::table('invoice_table')->where("user_id", $RsData->user_id)->first();  
                if(empty($invoice_details))
                {
                    $hotel_details = DB::table('kernel.hotels_table')->where("company_id", $RsData->company_id)->first(); 
                    if(empty($hotel_details))
                    {
                        $resultsingle['hotel_id'] = 0;
                    } 
                    else
                    {   
                        $resultsingle['hotel_id'] = $hotel_details->hotel_id;
                    }
                }
                else
                {
                    $resultsingle['hotel_id'] = $invoice_details->hotel_id;
                }
				$result[] = $resultsingle;
			}
            $status = 1;
            $encodedresult = json_encode($result);
            $crm_post_url = CRM_INSERT_FROM_DIFFERENT_SOURCE_URL;
            $post_fields = array('status' => $status,'customerdtls'=>$encodedresult);
            $bookingengine_data_booking = Commonmodel::curlPost($crm_post_url,$post_fields);
            $ids = array_column($result,"id");
            $pushformdata = [
                'is_push_crm' => 1
            ];
            DB::table('kernel.user_table')->whereIn('user_id', $ids)->update($pushformdata);
        }
        else
        {
           $status = 0;
        }

       if($status == 1)
       {
             $msg=array('status' => 1,'message'=>'Booking Engine Customer Data Insert sucessfully !!');
             return response()->json($msg); 
       }
       else
       {
            $msg=array('status' => 0,'message'=>'No New Customer Data Found !!');
            return response()->json($msg); 
       }
    }
}
    