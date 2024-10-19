<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use DB;
class TestControllerCRM extends Controller
{
    public function getCustomerDetails(Request $request){
        $today                      = date('Y-m-d');
        $getOtaCustomerData         = array();
        $getBeCustomerData          = array();
        $getAdmin                   = array();
        $getJiniAssistCustomerData  = array();
        $getWBCustomerData          = array();
        $getHotelID         = DB::table('kernel.billing_table')->select('*')->where('product_name', 'like', '%CRM%')->get();
        foreach($getHotelID as $id){
            $get_ids = DB::table('kernel.hotels_table')->select('hotel_id')->where('company_id',$id->company_id)->where('status',1)->get();
            $get_admin = DB::table('kernel.admin_table')->select('admin_id')->where('company_id',$id->company_id)->where('role_id',1)->first();
            if(!isset($get_admin->admin_id)){
                continue;
            }
            foreach($get_ids as $hotel_info ){
                $getAdmin[$hotel_info->hotel_id] = $get_admin->admin_id;
                $getOtaCustomerData_info =  DB::connection('bookingjini_cm')->table('cm_ota_booking')
                                ->select('customer_details','hotel_id')
                                ->where('hotel_id',$hotel_info->hotel_id)
                                ->whereDate('booking_date',$today)
                                ->get();
                if(sizeof($getOtaCustomerData_info)>0){
                    $getOtaCustomerData[] = $getOtaCustomerData_info;

                }
                $getBeCustomerData_info =  DB::table('invoice_table')
                                ->join('kernel.user_table','invoice_table.user_id','=','user_table.user_id')
                                ->select('user_table.first_name','user_table.last_name','user_table.email_id','user_table.mobile','invoice_table.hotel_id')
                                ->where('invoice_table.hotel_id',$hotel_info->hotel_id)
                                ->whereDate('invoice_table.booking_date',$today)
                                ->get();
                if(sizeof($getBeCustomerData_info)>0){
                    $getBeCustomerData[] = $getBeCustomerData_info;
                }
                // $getJiniAssistCustomerData[]    =  DB::connection('bookingjini_cm')->table('lisa_leads')
                //                 ->select('name','email','mobile','hotel_id')
                //                 ->where('hotel_id',$hotel_info->hotel_id)
                //                 ->whereDate('created_at',$today)
                //                 ->get();
                // $getWBCustomerData[]            =  DB::connection('mysql3')->table('customer_details_table')
                //                 ->select('user_name','user_email_id','user_contact_number','hotel_id','user_message')
                //                 ->where('hotel_id',$hotel_info->hotel_id)
                //                 ->whereDate('created_at',$today)
                //                 ->get();
            }
        }

        if(sizeof($getOtaCustomerData)>0 || sizeof($getBeCustomerData)>0){
            $resp = array('status'=>1, 'message'=>'Customer details retrieve successfully', 'ota_data'=>$getOtaCustomerData,'be_data'=>$getBeCustomerData,'user_id'=>$getAdmin);
            return response()->json($resp);
        }
        else{
            $resp = array('status'=>0, 'message'=>'Sorry! Not Available');
            return response()->json($resp);
        }
    }
    public function getHotelDetails(Request $request){
        $data = $request->all();
        $getHotelDetails = DB::table('hotels_table')
                            ->select('hotel_name')
                            ->where('hotel_id',$data['hotel_id'])
                            ->first();
        if(sizeof($getHotelDetails)>0){
            $resp = array('status'=>1, 'message'=>'retrieved successfully','data'=>$getHotelDetails->hotel_name);
        }
        else{
            $resp = array('status'=>0, 'message'=>'No data found');
        }
        return response()->json($resp);
    }
    public function getUserDetails(Request $request){
        $data = $request->all();
        $getCompany_id = DB::table('hotels_table')->select('company_id')->where('hotel_id',$data['hotel_id'])->first();
        $getAdminDetails = DB::table('admin_table')->select('*')->where('company_id',$getCompany_id->company_id)->where('role_id',1)->get();
        if(sizeof($getAdminDetails)>0){
            $resp = array('status'=>1,'message'=>'user details','data'=>$getAdminDetails);
            return response()->json($resp);
        }
        else{
            $resp = array('status'=>0,'message'=>'user details retrieve fails');
            return response()->json($resp);
        }
    }
    public function getCustomerDetailsByDate(Request $request){
        $data = $request->all();
        $today                      = date('Y-m-d');
        $from_date                  = date('Y-m-d',strtotime($data['from_date']));
        $getOtaCustomerData         = array();
        $getBeCustomerData          = array();
        $getAdmin                   = array();
        $getJiniAssistCustomerData  = array();
        $getWBCustomerData          = array();
        //$getHotelID         = DB::table('product_details')->select('hotel_id')->where('CRM',1)->get();
        // foreach($getHotelID as $id){
            $getcompany_id = DB::table('hotels_table')->select('company_id')->where('hotel_id',$data['hotel_id'])->first();
            $get_admin = DB::table('admin_table')->select('admin_id')->where('company_id',$getcompany_id->company_id)->where('role_id',1)->first();
            $getAdmin[$data['hotel_id']] = $get_admin->admin_id;

            $getOtaCustomerData[]           =  DB::table('cm_ota_booking')
                                            ->select('customer_details','hotel_id')
                                            ->where('hotel_id',$data['hotel_id'])
                                            ->whereDate('booking_date','>=',$from_date)
                                            ->whereDate('booking_date','<=',$today)
                                            ->get();
            $getBeCustomerData[]            =  DB::table('invoice_table')
                                            ->join('user_table','invoice_table.user_id','=','user_table.user_id')
                                            ->select('user_table.first_name','user_table.last_name','user_table.email_id','user_table.mobile','invoice_table.hotel_id')
                                            ->where('invoice_table.hotel_id',$data['hotel_id'])
                                            ->whereDate('booking_date','>=',$from_date)
                                            ->whereDate('booking_date','<=',$today)
                                            ->get();
            // $getJiniAssistCustomerData[]    =  DB::connection('mysql2')->table('lisa_leads')
            //                                 ->select('name','email','mobile','hotel_id')
            //                                 ->where('hotel_id',$id->hotel_id)
            //                                 ->whereDate('created_at',$today)
            //                                 ->get();
            // $getWBCustomerData[]            =  DB::connection('mysql3')->table('customer_details_table')
            //                                 ->select('user_name','user_email_id','user_contact_number','hotel_id','user_message')
            //                                 ->where('hotel_id',$id->hotel_id)
            //                                 ->whereDate('created_at',$today)
            //                                 ->get();
        // }

        if(sizeof($getOtaCustomerData)>0 || sizeof($getBeCustomerData)>0){
            $resp = array('status'=>1, 'message'=>'Customer details retrieve successfully', 'ota_data'=>$getOtaCustomerData,'be_data'=>$getBeCustomerData,'user_id'=>$getAdmin);
            return response()->json($resp);
        }
        else{
            $resp = array('status'=>0, 'message'=>'Sorry! Not Available');
            return response()->json($resp);
        }
    }

    //Added by Jigyans dt : - 20-09-2022
    public function getCustomerDetailsforBE()
    {
        $todays              = date('Y-m-d');
        $today = date('Y-m-d', strtotime($todays . ' -1 day'));
        $getHotelID         = DB::table('kernel.billing_table')->select('company_id')->where('product_name', 'like', '%CRM%')->get();
        $company_ids = array_column(json_decode(json_encode($getHotelID->toArray()),true),"company_id");
        $get_ids = DB::table('kernel.hotels_table')->select('hotel_id','company_id')->whereIn('company_id',$company_ids)->where('status',1)->get();
        $get_admin = DB::table('kernel.admin_table')->select('admin_id')->whereIn('company_id',$company_ids)->where('role_id',1)->get();
        $hotel_ids = array_column(json_decode(json_encode($get_ids->toArray()),true),"hotel_id");
        $getBeCustomerData_info =  DB::table('invoice_table')
                            ->join('kernel.user_table','invoice_table.user_id','=','user_table.user_id')
                            ->select('user_table.user_id','user_table.first_name','user_table.last_name','user_table.email_id','user_table.mobile','invoice_table.hotel_id')
                            ->whereIn('invoice_table.hotel_id',$hotel_ids)
                            ->whereDate('invoice_table.booking_date',$today)
                            ->get();

        if(sizeof($getBeCustomerData_info)>0)
        {
            foreach($getBeCustomerData_info as $RsGetBeCustomerDataInfo)
            {
                $hotel_info = DB::table('kernel.hotels_table')->select('company_id')->where('hotel_id',$RsGetBeCustomerDataInfo->hotel_id)->where('status',1)->first();
                $get_admin = DB::table('kernel.admin_table')->select('admin_id')->where('company_id',$hotel_info->company_id)->where('role_id',1)->first();
                $RsGetBeCustomerDataInfo->user_id = $get_admin->admin_id;
            }
            $resp = array('status'=>1, 'message'=>'Customer details retrieve successfully','be_data'=>$getBeCustomerData_info);
            return response()->json($resp);
        }
        else{
            $resp = array('status'=>0, 'message'=>'Sorry! Not Available');
            return response()->json($resp);
        }
    }

     //Added by Jigyans dt : - 20-09-2022
     public function getCustomerDetailsforCM()
     {
        $todays              = date('Y-m-d');
        $today = date('Y-m-d', strtotime($todays . ' -1 day'));
         $getHotelID         = DB::table('kernel.billing_table')->select('company_id')->where('product_name', 'like', '%CRM%')->get();
         $company_ids = array_column(json_decode(json_encode($getHotelID->toArray()),true),"company_id");
         $get_ids = DB::table('kernel.hotels_table')->select('hotel_id','company_id')->whereIn('company_id',$company_ids)->where('status',1)->get();
         $get_admin = DB::table('kernel.admin_table')->select('admin_id')->whereIn('company_id',$company_ids)->where('role_id',1)->get();
         $hotel_ids = array_column(json_decode(json_encode($get_ids->toArray()),true),"hotel_id");
        $getOtaCustomerData_info =  DB::connection('bookingjini_cm')->table('cm_ota_booking')
        ->select('id','customer_details','hotel_id','channel_name')
        ->whereIn('hotel_id',$hotel_ids)
        ->whereDate('booking_date',$today)
        ->get();

         if(sizeof($getOtaCustomerData_info)>0)
         {
             foreach($getOtaCustomerData_info as $RsGetCmCustomerDataInfo)
             {
                 $hotel_info = DB::table('kernel.hotels_table')->select('company_id')->where('hotel_id',$RsGetCmCustomerDataInfo->hotel_id)->where('status',1)->first();
                 $get_admin = DB::table('kernel.admin_table')->select('admin_id')->where('company_id',$hotel_info->company_id)->where('role_id',1)->first();
                 $RsGetCmCustomerDataInfo->user_id = $get_admin->admin_id;
             }
             $resp = array('status'=>1, 'message'=>'Customer details retrieve successfully','ota_data'=>$getOtaCustomerData_info);
             return response()->json($resp);
         }
         else{
             $resp = array('status'=>0, 'message'=>'Sorry! Not Available');
             return response()->json($resp);
         }
     }

     //Added by Jigyans dt : - 20-09-2022
    public function getCustomerDetailsforJA()
    {
        $getHotelID         = DB::table('kernel.billing_table')->select('company_id')->where('product_name', 'like', '%CRM%')->get();
        $company_ids = array_column(json_decode(json_encode($getHotelID->toArray()),true),"company_id");
        $get_ids = DB::table('kernel.hotels_table')->select('hotel_id','company_id')->whereIn('company_id',$company_ids)->where('status',1)->get();
        $hotel_ids = array_column(json_decode(json_encode($get_ids->toArray()),true),"hotel_id");
        $url = 'https://bookingjini.info/jiniassist_api/ja-customer-details';
        $getJiniAssistCustomerData = $this->postDataCurl($hotel_ids,$url);
        if(sizeof($getJiniAssistCustomerData)>0)
        {
            foreach($getJiniAssistCustomerData as $RsGetBeCustomerDataInfo)
            {
                $hotel_info = DB::table('kernel.hotels_table')->select('company_id')->where('hotel_id',$RsGetBeCustomerDataInfo->hotel_id)->where('status',1)->first();
                $get_admin = DB::table('kernel.admin_table')->select('admin_id')->where('company_id',$hotel_info->company_id)->where('role_id',1)->first();
                $RsGetBeCustomerDataInfo->user_id = $get_admin->admin_id;
            }
            $resp = array('status'=>1, 'message'=>'Customer details retrieve successfully','ja_data'=>$getJiniAssistCustomerData);
            return response()->json($resp);
        }
        else{
            $resp = array('status'=>0, 'message'=>'Sorry! Not Available');
            return response()->json($resp);
        }
    }

    //Added by Jigyans dt : - 20-09-2022
    public function getCustomerDetailsforWB()
    {
        $today                      = date('Y-m-d');
        $getHotelID         = DB::table('kernel.billing_table')->select('company_id')->where('product_name', 'like', '%CRM%')->get();
        $company_ids = array_column(json_decode(json_encode($getHotelID->toArray()),true),"company_id");
        $get_ids = DB::table('kernel.hotels_table')->select('hotel_id','company_id')->whereIn('company_id',$company_ids)->where('status',1)->get();
        $get_admin = DB::table('kernel.admin_table')->select('admin_id')->whereIn('company_id',$company_ids)->where('role_id',1)->get();
        $hotel_ids = array_column(json_decode(json_encode($get_ids->toArray()),true),"hotel_id");

        $url = 'https://bookingjini.info/website-builder-api-work/wb-customer-details';
        $getWBCustomerData = $this->postDataCurl($hotel_ids,$url);
        if(sizeof($getWBCustomerData)>0)
        {
            foreach($getWBCustomerData as $RsGetBeCustomerDataInfo)
            {
                $hotel_info = DB::table('kernel.hotels_table')->select('company_id')->where('hotel_id',$RsGetBeCustomerDataInfo->hotel_id)->where('status',1)->first();
                $get_admin = DB::table('kernel.admin_table')->select('admin_id')->where('company_id',$hotel_info->company_id)->where('role_id',1)->first();
                $RsGetBeCustomerDataInfo->user_id = $get_admin->admin_id;
            }
            $resp = array('status'=>1, 'message'=>'Customer details retrieve successfully','wb_data'=>$getWBCustomerData);
            return response()->json($resp);
        }
        else{
            $resp = array('status'=>0, 'message'=>'Sorry! Not Available');
            return response()->json($resp);
        }
    }

    //Added by Jigyans dt : - 24-09-2022
    public function updateStatusForBEfromCRM(Request $request)
    {
        $data = $request->all();
        $update_customer_details =  DB::table('kernel.user_table')
        ->whereIn('user_id',$data['customer_ids'])->update(array("is_push_crm"=>1));
    }

    //Added by Jigyans dt : - 24-09-2022
    public function updateStatusForBEBookingfromCRM(Request $request)
    {
        $data = $request->all();
        $update_customer_details =  DB::table('invoice_table')
        ->whereIn('invoice_id',$data['invoice_ids'])->update(array("is_push_crm_booking"=>1));
    }
    
    //Added by Jigyans dt : - 24-09-2022
    public function updateStatusForCMfromCRM(Request $request)
    {
        $data = $request->all();
        $update_customer_details =  DB::connection('bookingjini_cm')->table('cm_ota_booking')
        ->whereIn('id',$data['customer_ids'])->update(array("is_push_crm"=>1));
    }

    //Added by Jigyans dt : - 24-09-2022
    public function updateStatusForCMBookingfromCRM(Request $request)
    {
        $data = $request->all();
        $update_customer_details =  DB::connection('bookingjini_cm')->table('cm_ota_booking')
        ->whereIn('id',$data['customer_ids'])->update(array("is_push_crm_booking"=>1));
    }


    //Added by Jigyans dt : - 24-09-2022
    public function updateStatusForJAfromCRM(Request $request)
    {
        $data = $request->all();
        $update_customer_details =  DB::table('bookingjini_jiniassist.lisa_leads')
        ->whereIn('id',$data['customer_ids'])->update(array("is_push_crm"=>1));
    }

    //Added by Jigyans dt : - 24-09-2022
    public function updateStatusForWBfromCRM(Request $request)
    {
        $data = $request->all();
        $update_customer_details =  DB::table('bookingjini_jiniassist.lisa_leads')
        ->whereIn('id',$data['customer_ids'])->update(array("is_push_crm"=>1));
    }
 

    function postDataCurl($post_data,$url)
    {
        $hotel_ids =json_encode($post_data);
        $curl = curl_init();
        curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS =>'{
            "hotel_ids":'.$hotel_ids.'
        }',
        CURLOPT_HTTPHEADER => array(
        'Content-Type: application/json'
        ),
        ));
        $response = curl_exec($curl);
        $result = json_decode($response);
        return $result;
    }
}
