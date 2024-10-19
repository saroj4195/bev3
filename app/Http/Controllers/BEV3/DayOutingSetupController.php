<?php

namespace App\Http\Controllers\BEV3;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Validator;
use DB;
use App\DayOuting;
use App\Http\Controllers\Controller;


class DayOutingSetupController extends Controller
{

  public function addDayOutingPackage(Request $request)
  {

    $data = $request->all(); 
    $data['from_date'] = date('Y-m-d', strtotime($data['from_date']));
    $data['to_date'] = date('Y-m-d', strtotime($data['to_date']));
    $data['blackout_days'] = implode(',', $data['blackout_days']);

    // $day_outing_Package = DayOuting::insert($data);

    if ($day_outing_Package) {
      $final_data = array('status' => 1, 'msg' => 'Day outing package details Saved');
      return response()->json($final_data);
    } else {
      $final_data = array('status' => 0, 'msg' => 'Save Failed');
      return response()->json($final_data);
    }
  }
}
