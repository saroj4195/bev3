<?php
namespace App\Http\Controllers\Extranetv4;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use DB;
use App\Http\Controllers\Controller;
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
                $getOtaCustomerData[]           =  DB::connection('bookingjini_cm')->table('cm_ota_booking')
                                ->select('customer_details','hotel_id')
                                ->where('hotel_id',$hotel_info->hotel_id)
                                ->whereDate('booking_date',$today)
                                ->get();
                $getBeCustomerData[]            =  DB::table('invoice_table')
                                ->join('kernel.user_table','invoice_table.user_id','=','user_table.user_id')
                                ->select('user_table.first_name','user_table.last_name','user_table.email_id','user_table.mobile','invoice_table.hotel_id')
                                ->where('invoice_table.hotel_id',$hotel_info->hotel_id)
                                ->whereDate('invoice_table.booking_date',$today)
                                ->get();
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
}
