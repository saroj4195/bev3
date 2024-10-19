<?php

namespace App\Http\Controllers\Extranetv4;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\SalesExecutive;
use App\Http\Controllers\Controller;

class SalesExecutiveController extends Controller
{

    public function addSalesExecutive(Request $request)
    {
        $data = $request->all();
        $result = SalesExecutive::insert($data);

        if ($result) {
            $res = array('status' => 1, 'message' => 'Sales Executive Details Saved');
            return response()->json($res);
        } else {
            $res = array('status' => 0, 'message' => 'Sales Executive Details Save failed');
            return response()->json($res);
        }
    }

    public function UpdateSalesExecutive(Request $request, $id)
    {
        $data = $request->all();
        $result = SalesExecutive::where('id', $id)->update($data);

        if ($result) {
            $res = array('status' => 1, 'message' => 'Sales Executive Details Updated');
            return response()->json($res);
        } else {
            $res = array('status' => 0, 'message' => 'Sales Executive Details Update failed');
            return response()->json($res);
        }
    }

    public function SalesExecutive($hotel_id)
    {
        $salesExecutiveList = SalesExecutive::where('hotel_id', $hotel_id)->get();

        $sales_executive = [];
        if (sizeof($salesExecutiveList) > 0) {
            foreach ($salesExecutiveList as $salesExecutive) {
                $executive_details['id'] = $salesExecutive->id;
                $executive_details['hotel_id'] = $salesExecutive->hotel_id;
                $executive_details['name'] = $salesExecutive->name;
                $executive_details['phone'] = $salesExecutive->phone;
                $executive_details['email'] = $salesExecutive->email;
                $executive_details['whatsapp_number'] = $salesExecutive->whatsapp_number;
                $executive_details['is_active'] = $salesExecutive->is_active;
                $sales_executive[] = $executive_details;
            }
        }

        if ($sales_executive) {
            $res = array('status' => 1, 'message' => 'Sales Executive Details fetched', 'list' => $sales_executive);
            return response()->json($res);
        } else {
            $res = array('status' => 0, 'message' => 'No data found');
            return response()->json($res);
        }
    }

    public function UpdateSalesExecutiveStatus(Request $request){
        $data = $request->all();
        $id = $data['sales_executive_id'];
        $hotel_id = $data['hotel_id'];
        $status = $data['status'];
        $SalesExecutiveStatus = SalesExecutive::where('id', $id)->where('hotel_id',$hotel_id)->update(['is_active'=>$status]);

        if ($SalesExecutiveStatus) {
            $res = array('status' => 1, 'message' => 'Status Updated');
            return response()->json($res);
        } else {
            $res = array('status' => 0, 'message' => 'Status Updated failed');
            return response()->json($res);
        }
    }

    public function activeExecutiveList($hotel_id){
        
        $activeSalesExecutive = SalesExecutive::where('hotel_id',$hotel_id)->where('is_active',1)->get();
        $sales_executive = [];
        if (sizeof($activeSalesExecutive) > 0) {
            foreach ($activeSalesExecutive as $salesExecutive) {
                $executive_details['id'] = $salesExecutive->id;
                $executive_details['hotel_id'] = $salesExecutive->hotel_id;
                $executive_details['name'] = $salesExecutive->name;
                $executive_details['phone'] = $salesExecutive->phone;
                $executive_details['email'] = $salesExecutive->email;
                $executive_details['whatsapp_number'] = $salesExecutive->whatsapp_number;
                $executive_details['is_active'] = $salesExecutive->is_active;
                $sales_executive[] = $executive_details;
            }
        }
        if ($activeSalesExecutive) {
            $res = array('status' => 1, 'message' => 'Active Sales Executive list fetched', 'list' => $sales_executive);
            return response()->json($res);
        } else {
            $res = array('status' => 0, 'message' => 'Active Sales Executive list fetched failed');
            return response()->json($res);
        }
    }
}
