<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Validator;
use DB;
use App\PGSetup;

class PGSetupController extends Controller
{
    public function getPaymentGateways(Request $request)
    {
        $data_rows = array(); 
        $pg_records = PGSetup::get();
        if($pg_records)
        {
            foreach($pg_records as $pg_record)
            {
                $data_row['pg_id'] = $pg_record['pg_id'];
                $data_row['pg_name'] = $pg_record['pg_name'];
                $data_row['pg_parameters'] = $pg_record['pg_parameters'];
                $data_row['pg_status'] = $pg_record['pg_status'];
                $data_rows[] = $data_row; 
            }
            $res = array('status'=>1,"message"=>"payment gateways",'pg_records'=>$data_rows);
        }
        else
        {
            $res = array('status'=>0,"message"=>"No payment gateways");
        }
        return response()->json($res);
    }
    //================================================================================================================
    public function addPaymentGateway(Request $request)
    {
        $pg_name = $request['pg_name'];
        $super_admin_id = $request['super_admin_id'];
        $str_pg_parameters = $request['pg_parameters'];
        
        $pg_record = new PGSetup();
        $pg_record->pg_name = $pg_name;
        $pg_record->pg_parameters = $str_pg_parameters;
        $pg_record->pg_status = 1;
        $pg_record->created_by = $super_admin_id;
        $pg_record->created_at = date('Y-m-d H:i:s');
        $pg_record->updated_by = $super_admin_id;
        $pg_record->updated_at = date('Y-m-d H:i:s');
        $result = $pg_record->save();
        if($result)
        {
            $res = array('status'=>1,"message"=>"payment gateway added");
        }
        else
        {
            $res = array('status'=>0,"message"=>"error in payment gateways");
        }
        return response()->json($res);
    }
    //================================================================================================================
    public function updatePaymentGatewayStatus(Request $request)
    {
        $pg_id = (int)$request['pg_id'];
        $super_admin_id = $request['super_admin_id'];
        $pg_status = $request['pg_status'];
        
        $result = PGSetup::where('pg_id',$pg_id)->update(array('pg_status'=>$pg_status,'updated_by'=>$super_admin_id,'updated_at'=>date('Y-m-d H:i:s')));
        if($result)
        {
            $res = array('status'=>1,"message"=>"status updated");
        }
        else
        {
            $res = array('status'=>0,"message"=>"error in status update");
        }
        return response()->json($res);
    }
    //================================================================================================================
    public function updatePaymentGateway(Request $request)
    {
        $pg_id = (int)$request['pg_id'];
        $pg_name = $request['pg_name'];
        $str_pg_parameters = $request['pg_parameters'];
        $super_admin_id = $request['super_admin_id'];
        
        $result = PGSetup::where('pg_id',$pg_id)->update(array('pg_name'=>$pg_name,'pg_parameters'=>$str_pg_parameters,'updated_by'=>$super_admin_id,'updated_at'=>date('Y-m-d H:i:s')));
        if($result)
        {
            $res = array('status'=>1,"message"=>"details updated");
        }
        else
        {
            $res = array('status'=>0,"message"=>"error in details update");
        }
        return response()->json($res);
    }
    //=======================================================================================================================================
    public function getActivePaymentGateways(Request $request)
    {
        $data_rows = array(); 
        $pg_records = PGSetup::where('pg_status',1)->get();
        if($pg_records)
        {
            foreach($pg_records as $pg_record)
            {
                $data_row['pg_id'] = $pg_record['pg_id'];
                $data_row['pg_name'] = $pg_record['pg_name'];
                $data_row['pg_info'] = $pg_record['pg_parameters'];
                $data_rows[] = $data_row; 
            }
            $res = array('status'=>1,"message"=>"payment gateways",'pg_records'=>$data_rows);
        }
        else
        {
            $res = array('status'=>0,"message"=>"No payment gateways");
        }
        return response()->json($res);
    }
    //================================================================================================================
    public function getPaymentGatewayParameters(Request $request)
    {
        $pg_id = (int)$request['pg_id'];
        $pg_record = PGSetup::where('pg_status',1)->where('pg_id',$pg_id)->first();
        
        if($pg_record)
        {
            $data_row['pg_parameters'] = $pg_record['pg_parameters'];
            $res = array('status'=>1,"message"=>"payment gateways",'pg_parameters'=>$data_row);
        }
        else
        {
            $res = array('status'=>0,"message"=>"No payment gateways");
        }
        return response()->json($res);
    }
}
