<?php

namespace App\Http\Controllers\BEV3;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use App\ImageTable;
use App\CompanyDetails;


class BasicSetupController extends Controller  
{
    public function getHotelbanner(int $company_id, Request $request)
    {
        $conditions = array('company_id' => $company_id);
        $info = CompanyDetails::select('banner')
            ->where($conditions)->first();
    
        if (!$info) {
            $res = array('status' => 0, 'message' => "Invalid company credentials");
            return response()->json($res);
        }
    
        $imp_images = explode(',', $info->banner);
        $images = ImageTable::whereIn('image_id', $imp_images)
            ->select('image_name')
            ->orderByRaw("FIELD(image_id, " . implode(',', $imp_images) . ") ASC")
            ->get();
    
        if ($images->isEmpty()) {
            $images = ImageTable::where('image_id', 3)
                ->select('image_name')
                ->get();
        }
    
        $info->banner = $images;

        $banner = [];
    
        foreach ($info->banner as $key => $value) {
            $banner[] = $value->image_name;
        }
    
        $res = array('status' => 1, 'message' => "Company banners retrieved successfully", 'data' => $banner);
        return response()->json($res);
    }
}