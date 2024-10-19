<?php

namespace App\Http\Controllers\Extranetv4;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Validator;
use App\Newpaymentgateway;
use App\PGSetup;


class PaymentgatwayNewController extends Controller
{
    // Functions for Paymentgateway setup add, update,select  //
    /**
     * @author Swati date: 23/06/22
     */
    public function AddPaymentgatewaySetup(request $request)
    {
        $data = $request->all();

        $seleced_data = Newpaymentgateway::where('hotel_id', $data['hotel_id'])->where('provider_name', $data['provider_name'])->first();
        if ($seleced_data) {
            return response()->json(array('status' => 0, 'message' => "Already exit"));
        } else {
            $setup_array = [
                'hotel_id' => $data['hotel_id'],
                'provider_name' => $data['provider_name'],
                'user_id' => $data['user_id'],
                'client_ip' => "NA",
                'is_active' => 0
            ];

            $setup_data = Newpaymentgateway::insert($setup_array);
            if ($setup_data) {
                return response()->json(array('status' => 1, 'message' => ADD_PAYMENTGATEWAY_MESSAGE));
            } else {
                return response()->json(array('status' => 0, 'message' => FAILED_PAYMENTGATEWAY_MESSAGE));
            }
        }
    }

    public function EditPaymentgatwaySetup($id, request $request)
    {
        $data = $request->all();
        $credentials_info[] = $request->credentials;
        $credential = json_encode($credentials_info, true);
        $edit_setup_array = [
            'hotel_id' => $data['hotel_id'],
            'provider_name' => $data['provider_name'],
            'credentials' => $credential,
            'user_id' => $data['user_id']
        ];

        $setup_data = Newpaymentgateway::where('id', $id)->update($edit_setup_array);
        if ($setup_data) {
            return response()->json(array('status' => 1, 'message' => UPDATE_PAYMENTGATEWAY_MESSAGE));
        } else {
            return response()->json(array('status' => 0, 'message' => FAILED_PAYMENTGATEWAY_MESSAGE));
        }
    }

    public function SelectPaymentgatewaySetup($hotel_id, $id)
    {
        $select_paymentgateway_setup_data = Newpaymentgateway::select('*')->where('hotel_id', $hotel_id)->where('id', $id)->first();
        $credentials = [];
        if (isset($select_paymentgateway_setup_data->credentials)) {
            $credentials = json_decode($select_paymentgateway_setup_data->credentials, true);
        }
        $select_paymentgateway_setup_data->credentials = $credentials;
        if ($select_paymentgateway_setup_data) {
            return response()->json(array('status' => 1, 'message' => "data retrieve successfully", 'Data' => $select_paymentgateway_setup_data));
        } else {
            return response()->json(array('status' => 0, 'message' => "Data retrieve Faild"));
        }
    }

    public function SelectPaymentgateway($hotel_id)
    {
        $select_paymentgateway_data = Newpaymentgateway::join('payment_gateways', 'paymentgateway_details.provider_name', '=', 'payment_gateways.pg_name')
            ->select('paymentgateway_details.*', 'payment_gateways.payment_gateway_logos')
            ->where('paymentgateway_details.hotel_id', $hotel_id)
            ->where('payment_gateways.pg_status', 1)
            ->get();
        if (sizeof($select_paymentgateway_data) > 0) {
            return response()->json(array('status' => 1, 'message' => "data retrieve successfully", 'Data' => $select_paymentgateway_data));
        } else {
            return response()->json(array('status' => 0, 'message' => NO_PAYMENTGATEWAY_MESSAGE));
        }
    }
    public function SelectAllPaymentgateway($hotel_id)
    {
        $active_paymentgateway = PGSetup::select('*')->where('pg_status', 1)->get();
        if ($active_paymentgateway) {

            $all_paymentgateway = Newpaymentgateway::join('payment_gateways', 'paymentgateway_details.provider_name', '=', 'payment_gateways.pg_name')
                ->select('paymentgateway_details.*', 'payment_gateways.payment_gateway_logos')
                ->where('paymentgateway_details.hotel_id', $hotel_id)
                ->where('payment_gateways.pg_status', 1)
                ->get();
            if (sizeof($active_paymentgateway) > 0) {

                return response()->json(array('status' => 1, 'message' => "data retrieve successfully", 'Data' => $all_paymentgateway, 'Data 2' => $active_paymentgateway));
            } else {
                return response()->json(array('status' => 0, 'message' => NO_PAYMENTGATEWAY_MESSAGE));
            }
        }
    }

    public function inactivePaymentgatway($hotel_id, $provider_name, $is_active)
    {
        
            $inactive_paymentgatway = Newpaymentgateway::where('hotel_id', $hotel_id)
                ->update(['is_active' => 0]);
            if ($inactive_paymentgatway) {
                $active_paymentgatway_details = Newpaymentgateway::where('hotel_id', $hotel_id)->where('provider_name', $provider_name)
                    ->update(['is_active' => $is_active]);
            }
            if ($active_paymentgatway_details && $is_active == 1) {
                $ins =  array('status' => 1, "message" => 'Activated');
                return response()->json($ins);
            } else {
                $ins1 =  array('status' => 0, "message" => 'Inactivated');
                return response()->json($ins1);
            }
        

        // $delete_paymentgateway_details = Newpaymentgateway::where('hotel_id', $hotel_id)->where('provider_name', $provider_name)
        //     ->update(['is_active' => $is_active]);
        // if ($delete_paymentgateway_details && $is_active == 1) {
        //     $ins =  array('status' => 1, "message" => 'Activated');
        //     return response()->json($ins);
        // } else {
        //     $ins1 =  array('status' => 0, "message" => 'Inactivated');
        //     return response()->json($ins1);
        // }
    }
}
