<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Validator;
use DB;
use App\Inventory;
use App\RatePlanLog;
use App\Coupons;
/**
 * This controller is used for fetch the inventory and rate from booking engine database to google hotel ads database
 * @author Ranjit Date: 06-04-2021
 */
class GoogleHotelAdsInvRateFetchController extends Controller
{
    public function inventoryFetchForGoogle(Request $request){
        $inventory_data = $request->all();
        $today = date('Y-m-d');
        $getNewInventory = Inventory::select('*')->where('hotel_id',$inventory_data['hotel_id'])->where('inventory_id','>',$inventory_data['inventory_id'])->where('date_to','>=',$today)->get();
        return response()->json(array('data'=>$getNewInventory));
      
    }
    public function rateFetchForGoogle(Request $request){
        $rate_data = $request->all();
        $today = date('Y-m-d');
        $getNewRate = RatePlanLog::select('*')->where('hotel_id',$rate_data['hotel_id'])->where('rate_plan_log_id','>',$rate_data['rate_plan_log_id'])->where('to_date','>=',$today)->get();
        return response()->json(array('data'=>$getNewRate));
    }
    public function couponFetchForGoogle(Request $request){
        $coupon_data = $request->all();
        $today = date('Y-m-d');
        $getNewCoupon = Coupons::select('*')->where('hotel_id',$coupon_data['hotel_id'])->where('coupon_id','>',$coupon_data['coupon_id'])->where('valid_to','>=',$today)->get();
        return response()->json(array('data'=>$getNewCoupon));
    }
}