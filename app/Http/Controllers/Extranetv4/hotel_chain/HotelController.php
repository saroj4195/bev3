<?php
namespace App\Http\Controllers\Extranetv4\hotel_chain;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Validator;
use DB;
use App\HotelInformation;
use App\CompanyDetails;
use App\ImageTable;
use App\Invoice;
use App\HotelBooking;
use App\HotelFAQ;
use App\Http\Controllers\Controller;

class HotelController extends Controller 
{
    public function getGroupHotelList($group_id)
    {
        //Hotel List
        if($group_id == "BYKE")
        {
            $hotel_ids = array(3084,3083,3058,3057,3053,3047,3046,3045,3043,3039,3038,3037,3036,3035,3033);
            $group_hotels = DB::table('kernel.hotels_table')
            ->join('kernel.image_table', DB::raw('SUBSTRING_INDEX(exterior_image, \',\', 1)'), '=', 'image_table.image_id')
            ->join('kernel.city_table', 'hotels_table.city_id', '=', 'city_table.city_id')
            ->join('kernel.company_table', 'hotels_table.company_id', '=', 'company_table.company_id')
            ->WhereIn('kernel.hotels_table.hotel_id', $hotel_ids)
            ->select('kernel.hotels_table.hotel_id','hotel_name','star_of_property',DB::raw('1500 as starting_price'), 'company_table.api_key', 'company_table.subdomain_name',
            DB::raw('SUBSTRING_INDEX(exterior_image, \',\', 1) AS image_id'),'kernel.image_table.image_name','city_table.city_name','hotels_table.hotel_description')
            ->get();
        }
        else
        {
            $group_hotels = DB::table('kernel.hotels_table')
            ->join('kernel.image_table', DB::raw('SUBSTRING_INDEX(exterior_image, \',\', 1)'), '=', 'image_table.image_id')
            ->join('kernel.city_table', 'hotels_table.city_id', '=', 'city_table.city_id')
            ->Where('kernel.hotels_table.company_id', $group_id)
            ->select('kernel.hotels_table.hotel_id','hotel_name','star_of_property',DB::raw('1500 as starting_price'),
            DB::raw('SUBSTRING_INDEX(exterior_image, \',\', 1) AS image_id'),'kernel.image_table.image_name','city_table.city_name','hotels_table.hotel_description')
            ->take(6)
            ->get();
        }
        

        $hotels_data = array();
        foreach($group_hotels as $hotel_info)
        {
            $hotel_record['hotel_id'] = $hotel_info->hotel_id;
            $hotel_record['hotel_name'] = $hotel_info->hotel_name;
            $hotel_record['star'] = $hotel_info->star_of_property;
            $hotel_record['image'] = env('S3_IMAGE_PATH').$hotel_info->image_name;
            $hotel_record['starting_price'] = $hotel_info->starting_price;
            $hotel_record['city_name'] = $hotel_info->city_name;
            $hotel_record['hotel_description'] = $hotel_info->hotel_description;
            $hotel_record['max_guest'] = 3;
            if($group_id == "BYKE")
            {
                $hotel_record['api_key'] = $hotel_info->api_key;
                $hotel_record['be_url'] = 'https://'.$hotel_info->subdomain_name;
            }
            $hotels_data[] = $hotel_record;
        }
        $msg = array('status' => 1,'message'=>'Hotels List','hotels_data' => $hotels_data);
        return response()->json($msg);

    }
    //================================================================================================================================
    public function getQueryResult($group_id, $query_text)
    {
        $query_text = strtolower(urldecode($query_text));
        //Hotel List
        $group_hotels = DB::table('kernel.hotels_table')
        ->Where('kernel.hotels_table.company_id', $group_id)
        ->Where(DB::raw('lower(kernel.hotels_table.hotel_name)'), 'like',"$query_text%")
        ->select('kernel.hotels_table.hotel_id','hotel_name')
        ->take(20)
        ->get();

        $hotels_data = array();
        foreach($group_hotels as $hotel_info)
        {
            $hotel_record['hotel_id'] = $hotel_info->hotel_id;
            $hotel_record['hotel_name'] = $hotel_info->hotel_name;
            $hotel_record['type'] = 'HOTEL';
            $hotels_data[] = $hotel_record;
        }

        //Place List
        $group_hotels = DB::table('kernel.hotels_table')
        ->join('kernel.city_table', 'hotels_table.city_id', '=', 'city_table.city_id')
        ->Where('kernel.hotels_table.company_id', $group_id)
        ->Where(DB::raw('lower(kernel.city_table.city_name)'), 'like',"$query_text%")
        ->Where('city_table.city_name', '!=',"Darjeeling")
        ->Where('city_table.city_name', '!=',"Manali")
        ->select(DB::raw('DISTINCT kernel.city_table.city_id,city_table.city_name'))
        ->take(20)
        ->get();

        
        foreach($group_hotels as $hotel_info)
        {
            $hotel_record['city_id'] = $hotel_info->city_id;
            $hotel_record['city_name'] = $hotel_info->city_name;
            $hotel_record['type'] = 'CITY';
            $hotels_data[] = $hotel_record;
        }
        $msg = array('status' => 1,'message'=>'Hotels List','hotels_data' => $hotels_data);
        return response()->json($msg);

    }
    //================================================================================================================================
    public function getFilteredHotelList(Request $request )
    {
        $destination_image = '';
        $group_id = $request['group_id'];
        $city_name = $request['city_name'];
        $star_rating = (int)$request['star_rating'];
        $min_price = $request['min_price'];
        $max_price = $request['max_price'];
        //$str_amenities = isset($request['amenities']) ? $request['amenities'] : '';
        
        $ttdc_premium_hotel_list = [2501,2490,2491, 2487, 2505, 2506, 2493, 2499, 2509, 2482, 2520];
        $group_hotels = DB::table('kernel.hotels_table')
            ->join('kernel.image_table', DB::raw('SUBSTRING_INDEX(exterior_image, \',\', 1)'), '=', 'image_table.image_id')
            ->join('kernel.city_table', 'hotels_table.city_id', '=', 'city_table.city_id')
            ->join('kernel.room_rate_plan', 'hotels_table.hotel_id', '=', 'room_rate_plan.hotel_id')
            ->Where('kernel.hotels_table.company_id', $group_id)
            ->groupBy('kernel.hotels_table.hotel_id','hotel_name','star_of_property','original_price','exterior_image','image_name','city_table.city_name','hotels_table.hotel_description')
            ->select('kernel.hotels_table.hotel_id','hotel_name','star_of_property',DB::raw('min(bar_price) as starting_price'), DB::raw('2000 as original_price'),
            DB::raw('SUBSTRING_INDEX(exterior_image, \',\', 1) AS image_id'),'kernel.image_table.image_name','city_table.city_name','hotels_table.hotel_description')
            ;
        if($city_name != '')
        {
            $group_hotels = $group_hotels->Where('kernel.city_table.city_name', $city_name);
            $destination_image = env('S3_ROOT_PATH')."group-website/$group_id/destination-photos/".strtolower($city_name).'.jpg';
        }
        if($star_rating != '')
        {
            $group_hotels = $group_hotels->Where('kernel.hotels_table.star_of_property', $star_rating);
        }
        // if($str_amenities != '')
        // {
        //     $arr_amenities = explode(",",$str_amenities);
        //     foreach($arr_amenities as $amenity)
        //     {
        //         $group_hotels = $group_hotels->Where('kernel.hotels_table.facility',  'like', "%$amenity%");
        //     }
        // }
        $group_hotels = $group_hotels->get();
        $hotels_data = array();
        foreach($group_hotels as $hotel_info)
        {
            $hotel_record['hotel_id'] = $hotel_info->hotel_id;
            $hotel_record['hotel_name'] = $hotel_info->hotel_name;
            $hotel_record['star'] = $hotel_info->star_of_property;
            $hotel_record['image'] = env('S3_IMAGE_PATH').$hotel_info->image_name;
            $hotel_record['original_price'] = $hotel_info->original_price;
            $hotel_record['starting_price'] = $hotel_info->starting_price;
            $hotel_record['city_name'] = $hotel_info->city_name;
            $hotel_record['hotel_description'] = strip_tags($hotel_info->hotel_description);
            $hotel_record['max_guest'] = 3;
            if(in_array($hotel_info->hotel_id,$ttdc_premium_hotel_list))
                $hotel_record['is_premium'] = TRUE;
            else
                $hotel_record['is_premium'] = FALSE;
            
            $hotels_data[] = $hotel_record;
        }
        $msg = array('status' => 1,'message'=>'Hotels List','hotels_data' => $hotels_data,'destination_image'=>$destination_image);
        return response()->json($msg);

    }
    //============================================================================================================================
    public function getHotelDetails(Request $request)
    {
        $hotel_id = (int)$request['hotel_id'];
        $hotel_data = DB::table('kernel.hotels_table')
            ->join('kernel.city_table', 'hotels_table.city_id', '=', 'city_table.city_id')
            ->join('kernel.company_table', 'hotels_table.company_id', '=', 'company_table.company_id')
            ->join('kernel.room_rate_plan', 'hotels_table.hotel_id', '=', 'room_rate_plan.hotel_id')
            ->Where('kernel.hotels_table.hotel_id', $hotel_id)
            ->groupBy('kernel.hotels_table.hotel_id','hotel_name','star_of_property','city_table.city_name','hotels_table.hotel_description','hotels_table.company_id','hotels_table.hotel_address',
            'hotels_table.latitude','hotels_table.longitude','hotel_policy','child_policy','cancel_policy','terms_and_cond',
            'facility','subdomain_name','exterior_image')
            ->select('kernel.hotels_table.hotel_id','hotel_name','star_of_property',DB::raw('min(bar_price) as starting_price'),
            'city_table.city_name','hotels_table.hotel_description','hotels_table.company_id','hotels_table.hotel_address',
            'hotels_table.latitude','hotels_table.longitude','hotel_policy','child_policy','cancel_policy','terms_and_cond',
            'facility','subdomain_name','exterior_image')
            ->first();

        $hotel_aminities = DB::table('kernel.hotel_amenities')
        ->join('kernel.amenity_categories', 'hotel_amenities.category_id', '=','amenity_categories.category_id')
        ->Where('kernel.amenity_categories.is_hotel', 1)
        ->select('kernel.hotel_amenities.hotel_amenities_id','hotel_amenities_name')
        ->get();

        $images = $hotel_data->exterior_image;
        $arr_images = explode(',',$images);
        $hotel_images = DB::table('kernel.image_table')
        ->WhereIn('image_id', $arr_images)
        ->select('image_name')
        ->get();

        $str_images = array();
        foreach($hotel_images as $hotel_image)
        {
            $str_images[] = env('S3_IMAGE_PATH').$hotel_image->image_name;
        }

        $facilities = array();

        $arr_facility = explode(',',$hotel_data->facility);
        foreach($arr_facility as $facility_id)
        {
            foreach($hotel_aminities as $hotel_aminity)
            {
                if($hotel_aminity->hotel_amenities_id == $facility_id)
                {
                    $facilities[] = $hotel_aminity->hotel_amenities_name;
                }
            }
        }

        
        $company_id = $hotel_data->company_id;
        $hotel_record['hotel_id'] = $hotel_data->hotel_id;
        $hotel_record['hotel_name'] = $hotel_data->hotel_name;
        $hotel_record['star'] = $hotel_data->star_of_property;
        $hotel_record['image'] = env('S3_IMAGE_PATH').$hotel_images[0]->image_name;
        $hotel_record['starting_price'] = $hotel_data->starting_price;
        $hotel_record['city_name'] = $hotel_data->city_name;
        $hotel_record['address'] = $hotel_data->hotel_address;
        $hotel_record['latitude'] = $hotel_data->latitude;
        $hotel_record['longitude'] = $hotel_data->longitude;
        $hotel_record['hotel_description'] = $hotel_data->hotel_description;
        $hotel_record['hotel_policy'] = $hotel_data->hotel_policy;
        $hotel_record['child_policy'] = $hotel_data->child_policy;
        $hotel_record['cancel_policy'] = $hotel_data->cancel_policy;
        $hotel_record['terms_and_cond'] = $hotel_data->terms_and_cond;
        $hotel_record['facility'] = $facilities;
        $hotel_record['be_url'] = $hotel_data->subdomain_name;
        $hotel_record['max_guest'] = 3;

        $conditions=array('company_id'=>$company_id);
        $info=CompanyDetails::select('banner','logo','home_url','api_key')
            ->where($conditions)->first();
        $info->logo =$this->getImages(array($info->logo));
        $info->logo = $info->logo[0]->image_name;
        $api_key = $info->api_key;

        $info->logo = $info->logo;
        $info->banner =$this->getBannerImages($info->banner);
        $info->banner = $info->banner;
        foreach($info->banner as $key => $value){
            $info->banner[$key]->image_name =  $value->image_name;
        }

        $hotel_record['banners'] = $info->banner;
        $hotel_record['api_key'] = $api_key;
        $hotel_record['images'] = $str_images;
        
        $msg = array('status' => 1,'message'=>'Hotels List','hotel_data' => $hotel_record);
        return response()->json($msg);

    }
    //============================================================================================================================
    public function getImages($imgs)
    {
        $images=ImageTable::whereIn('image_id', $imgs)
            ->select('image_name')
            ->get();
        if(sizeof($images)>0)
        {
            return $images;
        }
        else
        {
            $images=ImageTable::where('image_id', 3)
                ->select('image_name')
                ->get();
            return $images;
        }
    }
    //============================================================================================================================
    public function getBannerImages($imgs)
    {
        $banner_ids = explode(",", $imgs);
        $images=ImageTable::whereIn('image_id',  $banner_ids)
                ->select('image_name')
                ->get();
        if(sizeof($images)!=0)
        {
            return $images;
        }
        else
        {
            $images=ImageTable::where('image_id',  2)
            ->select('image_name')
            ->get();
            return $images;
        }
    }
    //============================================================================================================================
    
    //================================================================================================================================
    public function getGroupPackageList($group_id)
    {
        $today = date('Y-m-d');
        $group_id = (int)$group_id;
        $group_package = DB::table('kernel.package_table')
        ->join('kernel.hotels_table', 'hotels_table.hotel_id', '=', 'package_table.hotel_id')
        ->Where('kernel.hotels_table.company_id', $group_id)
        //->Where('kernel.package_table.date_to', '>', $today)
        ->select('package_table.package_name',DB::raw('count(hotels_table.hotel_id) AS hotel_count'))
        ->groupBy('package_table.package_name')
        ->get();
        $package_details = array();
        foreach($group_package as $group_packag)
        {
            $package_detail['package_name'] = $group_packag->package_name;
            $package_detail['hotel_count'] = $group_packag->hotel_count;
            $package_details[] = $package_detail;
        }
        //$package_names = array_unique($package_names);

        $msg = array('status' => 1,'message'=>'Package List','package_details' => $package_details);
        return response()->json($msg);

    }
    //================================================================================================================================
    public function getPackageHotelList(Request $request)
    {
        //Hotel List
        $today = date('Y-m-d');
        $group_id = (int)$request['group_id'];
        $package_name = $request['package_name'];
        $group_packages = DB::table('kernel.package_table')
        ->join('kernel.hotels_table', 'hotels_table.hotel_id', '=', 'package_table.hotel_id')
        ->join('kernel.city_table', 'hotels_table.city_id', '=', 'city_table.city_id')
        ->join('kernel.image_table', DB::raw('SUBSTRING_INDEX(package_image, \',\', 1)'), '=', 'image_table.image_id')
        ->Where('kernel.hotels_table.company_id', $group_id)
        //->Where('kernel.package_table.date_to', '>', $today)
        ->Where('kernel.package_table.package_name', $package_name)
        ->select('package_table.package_name','hotels_table.hotel_name','hotels_table.hotel_id',
        'package_table.package_id','package_table.date_from','package_table.date_to','kernel.image_table.image_name',
        'city_table.city_id','city_table.city_name'
        )
        ->get();
        
        $package_destinations = array();
        $package_records = array();
        
        $destination_ids = array();
        foreach($group_packages as $group_package)
        {
            $package_record['hotel_id'] = $group_package->hotel_id;
            $package_record['hotel_name'] = $group_package->hotel_name;
            $package_record['package_id'] = $group_package->package_id;
            $package_record['package_name'] = $group_package->package_name;
            $package_record['date_from'] = $group_package->date_from;
            $package_record['date_to'] = $group_package->date_to;
            $package_record['image'] = env('S3_IMAGE_PATH').$group_package->image_name;
            $package_record['city_id'] = $group_package->city_id;
            $package_record['city_name'] = $group_package->city_name;
            $package_records[] = $package_record;
            $destination_record = array();
            if(!in_array($group_package->city_id,$destination_ids))
            {
                $destination_record['city_id'] = $group_package->city_id;
                $destination_record['city_name'] = $group_package->city_name;
                $package_destinations[] = $destination_record;
                $destination_ids[] = $group_package->city_id;
            }
            
        }
        $msg = array('status' => 1,'message'=>'Package List','package_hotel_list' => $package_records,'package_destinations'=>$package_destinations);
        return response()->json($msg);

    }
    //================================================================================================================================
    public function getGroupHotelDestinations($group_id,$filter)
    {
        //City List
        if($filter == 'ALL')
        {
            $group_hotels = DB::table('kernel.hotels_table')
            ->join('kernel.city_table', 'hotels_table.city_id', '=', 'city_table.city_id')
            ->Where('kernel.hotels_table.company_id', $group_id)
            ->Where('city_table.city_name', '!=', 'Darjeeling')
            ->Where('city_table.city_name', '!=', 'Manali')
            ->Where('city_table.city_name', '!=', 'Bihar Sharif')
            ->select('city_table.city_name')
            ->get();
            $destinations = array();
            foreach($group_hotels as $hotel_info)
            {
                if(!in_array($hotel_info->city_name,$destinations))
                    array_push($destinations,$hotel_info->city_name);
            }
        }
        else if($filter == 'TOP')
        {
            if($group_id == 2533) //wb
                $destinations = array(0=>'Mumbai',1=>'New Delhi',2=>'Kolkata',3=>'Goa');
            else if($group_id == 2565) //romt
                $destinations = array(0=>'Coimbatore',1=>'Yercaud',2=>'KodaiKanal',3=>'Mysore');
        }
        $msg = array('status' => 1,'message'=>'Destination List','destinations' => $destinations);
        return response()->json($msg);

    }
    //================================================================================================================================
    public function getGroupHotelCities($group_id)
    {
        $group_hotels = DB::table('kernel.hotels_table')
        ->join('kernel.city_table', 'hotels_table.city_id', '=', 'city_table.city_id')
        ->Where('kernel.hotels_table.company_id', $group_id)
        ->Where('city_table.city_name', '!=', 'Darjeeling')
        ->Where('city_table.city_name', '!=', 'Manali')
        ->Where('city_table.city_name', '!=', 'Bihar Sharif')
        ->select(DB::raw('distinct city_table.city_id, city_name'))
        ->get();
        
        $msg = array('status' => 1,'message'=>'City List','cities' => $group_hotels);
        return response()->json($msg);

    }
    //================================================================================================================================
    public function getGroupHotelsByCity($group_id, $city_id)
    {
        $group_hotels = DB::table('kernel.hotels_table')
        ->join('kernel.city_table', 'hotels_table.city_id', '=', 'city_table.city_id')
        ->Where('kernel.hotels_table.company_id', $group_id)
        ->Where('city_table.city_id', $city_id)
        ->select('hotels_table.hotel_id','hotels_table.hotel_name')
        ->get();
        
        
        $msg = array('status' => 1,'message'=>'Hotels List By City','hotels' => $group_hotels);
        return response()->json($msg);

    }
    //================================================================================================================================
    public function getGroupHotelAmenities($group_id)
    {
        $group_hotels = DB::table('kernel.hotels_table')
        ->Where('kernel.hotels_table.company_id', $group_id)
        ->Where('kernel.hotels_table.status', 1)
        ->select('facility')
        ->get();

        $all_facilities = array();
        foreach($group_hotels as $hotel)
        {
            $str_facility_list = $hotel->facility;
            if($str_facility_list != '')
            {
                $arr_facility = explode(",",$str_facility_list);
                if(count($arr_facility) > 0)
                {
                    foreach($arr_facility as $facility)
                    {
                        if(!in_array($facility,$all_facilities))
                            array_push($all_facilities,(int)$facility);
                    }
                }
                    
            }
        }
        
        $facility_data = DB::table('kernel.hotel_amenities')
        ->WhereIn('kernel.hotel_amenities.hotel_amenities_id', $all_facilities)
        ->Where('kernel.hotel_amenities.is_trash', 0)
        ->select('hotel_amenities_id','hotel_amenities_name')
        ->get();
        
        $msg = array('status' => 1,'message'=>'Amenities List','facilities' => $facility_data);
        return response()->json($msg);

    }
    //================================================================================================================================
    public function getFilteredHotelList2(Request $request )
    {
        $destination_image = '';
        $group_id = $request['group_id'];
        $city_name = $request['city_name'];
        $star_rating = (int)$request['star_rating'];
        $min_price = (int)$request['min_price'];
        $max_price = (int)$request['max_price'];
        $str_amenities = $request['amenities'];
        
        $ttdc_premium_hotel_list = [2501,2490,2491, 2487, 2505, 2506, 2493, 2499, 2509, 2482, 2520];
        $group_hotels = DB::table('kernel.hotels_table')
            ->join('kernel.image_table', DB::raw('SUBSTRING_INDEX(exterior_image, \',\', 1)'), '=', 'image_table.image_id')
            ->join('kernel.city_table', 'hotels_table.city_id', '=', 'city_table.city_id')
            ->join('kernel.room_rate_plan', 'hotels_table.hotel_id', '=', 'room_rate_plan.hotel_id')
            ->Where('kernel.hotels_table.company_id', $group_id)
            ->groupBy('kernel.hotels_table.hotel_id','hotel_name','star_of_property','exterior_image','image_name','city_table.city_name','hotels_table.hotel_description')
            ->select('kernel.hotels_table.hotel_id','hotel_name','star_of_property',DB::raw('min(bar_price) as starting_price'), DB::raw('max(bar_price) as ending_price'),
            DB::raw('SUBSTRING_INDEX(exterior_image, \',\', 1) AS image_id'),'kernel.image_table.image_name','city_table.city_name','hotels_table.hotel_description')
            ;
        if($city_name != '')
        {
            $group_hotels = $group_hotels->Where('kernel.city_table.city_name', $city_name);
            $destination_image = env('S3_ROOT_PATH')."group-website/$group_id/destination-photos/".strtolower($city_name).'.jpg';
        }
        if($star_rating != '')
        {
            $group_hotels = $group_hotels->Where('kernel.hotels_table.star_of_property', $star_rating);
        }
        if($str_amenities != '')
        {
            $arr_amenities = explode(",",$str_amenities);
            foreach($arr_amenities as $amenity)
            {
                $group_hotels = $group_hotels->Where('kernel.hotels_table.facility',  'like', "%$amenity%");
            }
        }
        if($min_price != '' && $max_price != '')
        {
            $group_hotels = $group_hotels->havingRaw('starting_price >= '.$min_price);
            $group_hotels = $group_hotels->havingRaw('ending_price <= '.$max_price);
            
        }
        $group_hotels = $group_hotels->get();
        $hotels_data = array();
        foreach($group_hotels as $hotel_info)
        {
            $hotel_record['hotel_id'] = $hotel_info->hotel_id;
            $hotel_record['hotel_name'] = $hotel_info->hotel_name;
            $hotel_record['star'] = $hotel_info->star_of_property;
            $hotel_record['image'] = env('S3_IMAGE_PATH').$hotel_info->image_name;
            $hotel_record['ending_price'] = $hotel_info->ending_price;
            $hotel_record['starting_price'] = $hotel_info->starting_price;
            $hotel_record['city_name'] = $hotel_info->city_name;
            $hotel_record['hotel_description'] = strip_tags($hotel_info->hotel_description);
            $hotel_record['max_guest'] = 3;
            if(in_array($hotel_info->hotel_id,$ttdc_premium_hotel_list))
                $hotel_record['is_premium'] = TRUE;
            else
                $hotel_record['is_premium'] = FALSE;
            
            $hotels_data[] = $hotel_record;
        }
        $msg = array('status' => 1,'message'=>'Hotels List','hotels_data' => $hotels_data,'destination_image'=>$destination_image);
        return response()->json($msg);

    }
    //============================================================================================================================
    public function checkAvailability(Request $request )
    {
        $group_id = (int)$request['group_id'];
        $city_id = (int)$request['city_id'];
        $hotel_id = (int)$request['hotel_id'];
        $checkin_date = date('d-m-Y',strtotime($request['checkin_date']));
        $checkout_date = date('d-m-Y',strtotime($request['checkout_date']));
        $no_of_rooms = (int)$request['no_of_rooms'];
        $star_rating = (int)$request['star_rating'];
        $min_price = $request['min_price'];
        $max_price = $request['max_price'];
        $str_amenities = $request['amenities'];
        
        // $group_hotels = DB::table('kernel.hotels_table')
        //     ->join('kernel.city_table', 'hotels_table.city_id', '=', 'city_table.city_id')
        //     ->join('kernel.company_table', 'hotels_table.company_id', '=', 'company_table.company_id')
        //     ->Where('kernel.hotels_table.company_id', $group_id)
        //     ->Where('kernel.city_table.city_id', $city_id)
        //     ->select('kernel.hotels_table.hotel_id','kernel.hotels_table.hotel_name','kernel.company_table.api_key','kernel.city_table.city_name')
        //     ;
        $group_hotels = DB::table('kernel.hotels_table')
            ->join('kernel.image_table', DB::raw('SUBSTRING_INDEX(exterior_image, \',\', 1)'), '=', 'image_table.image_id')
            ->join('kernel.city_table', 'hotels_table.city_id', '=', 'city_table.city_id')
            ->join('kernel.room_rate_plan', 'hotels_table.hotel_id', '=', 'room_rate_plan.hotel_id')
            ->join('kernel.company_table', 'hotels_table.company_id', '=', 'company_table.company_id')
            ->Where('kernel.hotels_table.company_id', $group_id)
            ->Where('kernel.city_table.city_id', $city_id)
            ->Where('kernel.hotels_table.hotel_id', $hotel_id)
            ->groupBy('kernel.hotels_table.hotel_id','hotel_name','star_of_property','exterior_image','image_name','city_table.city_name','hotels_table.hotel_description','kernel.company_table.api_key')
            ->select('kernel.hotels_table.hotel_id','hotel_name','star_of_property',DB::raw('min(bar_price) as starting_price'), DB::raw('max(bar_price) as ending_price'),
            DB::raw('SUBSTRING_INDEX(exterior_image, \',\', 1) AS image_id'),'kernel.image_table.image_name','city_table.city_name','hotels_table.hotel_description',
            'kernel.company_table.api_key')
            ;
        
        if($star_rating != '')
        {
            $group_hotels = $group_hotels->Where('kernel.hotels_table.star_of_property', $star_rating);
        }
        if($str_amenities != '')
        {
            $arr_amenities = explode(",",$str_amenities);
            foreach($arr_amenities as $amenity)
            {
                $group_hotels = $group_hotels->Where('kernel.hotels_table.facility',  'like', "%$amenity%");
            }
        }
        
        $group_hotels = $group_hotels->get();
        $hotels_data = array();
        $city_name = $group_hotels[0]->city_name;
        foreach($group_hotels as $hotel_info)
        {
            $hotel_id = $hotel_info->hotel_id;
            $api_key = $hotel_info->api_key;
            //get inventory
            $curl = curl_init();

            curl_setopt_array($curl, array(
            CURLOPT_URL => "https://be.bookingjini.com/bookingEngine/get-inventory/$api_key/$hotel_id/$checkin_date/$checkout_date/INR",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            ));

            $response = curl_exec($curl);

            curl_close($curl);
            $res_json = json_decode($response, true);
            $hotel_has_rooms = FALSE;
            if($res_json['status'] == 1)
            {
                $room_types = $res_json['room_data'];
                foreach($room_types as $room_type)
                {
                    $room_type_room_available = TRUE;
                    $room_inventories = $room_type['inv'];
                    foreach($room_inventories as $room_inventory)
                    {
                        if($no_of_rooms  > $room_inventory['no_of_rooms'])
                        {
                            $room_type_room_available = FALSE;
                            break;
                        }
                    }
                    if($room_type_room_available)
                    {
                        $hotel_has_rooms = TRUE;
                        break;
                    }
                }
            }
            if($hotel_has_rooms)
            {
                $hotel_record['hotel_id'] = $hotel_info->hotel_id;
                $hotel_record['hotel_name'] = $hotel_info->hotel_name;
                $hotel_record['star'] = $hotel_info->star_of_property;
                $hotel_record['image'] = env('S3_IMAGE_PATH').$hotel_info->image_name;
                $hotel_record['ending_price'] = $hotel_info->ending_price;
                $hotel_record['starting_price'] = $hotel_info->starting_price;
                $hotel_record['city_name'] = $hotel_info->city_name;
                $hotel_record['hotel_description'] = strip_tags($hotel_info->hotel_description);
                $hotel_record['max_guest'] = 3;
                $hotels_data[] = $hotel_record;
            }  
        }
        $destination_image = env('S3_ROOT_PATH')."group-website/$group_id/destination-photos/".strtolower($city_name).'.jpg';
        $msg = array('status' => 1,'message'=>'Hotels List','hotels_data' => $hotels_data,'destination_image'=>$destination_image,'hotels_found'=>count($hotels_data));
        return response()->json($msg);
    }
    
    //================================================================================================================================
    public function checkAvailabilityByCityAndDates(Request $request )
    {
        $city_id = (int)$request['city_id'];
        $checkin_date = date('d-m-Y',strtotime($request['checkin_date']));
        $checkout_date = date('d-m-Y',strtotime($request['checkout_date']));
        $no_of_rooms = (int)$request['no_of_rooms'];
        $star_rating = (int)$request['star_rating'];
        $min_price = $request['min_price'];
        $max_price = $request['max_price'];
        $str_amenities = $request['amenities'];
        
        $group_hotels = DB::table('kernel.hotels_table')
            ->join('kernel.image_table', DB::raw('SUBSTRING_INDEX(exterior_image, \',\', 1)'), '=', 'image_table.image_id')
            ->join('kernel.city_table', 'hotels_table.city_id', '=', 'city_table.city_id')
            ->join('kernel.room_rate_plan', 'hotels_table.hotel_id', '=', 'room_rate_plan.hotel_id')
            ->join('kernel.company_table', 'hotels_table.company_id', '=', 'company_table.company_id')
            ->Where('kernel.city_table.city_id', $city_id)
            ->Where('kernel.hotels_table.status', 1)
            ->groupBy('kernel.hotels_table.hotel_id','hotel_name','star_of_property','exterior_image','image_name','city_table.city_name',
            'hotels_table.hotel_description','kernel.company_table.api_key','hotel_address','company_table.api_key')
            ->select('kernel.hotels_table.hotel_id','hotel_name','star_of_property',
            DB::raw('min(bar_price) as starting_price'), 
            DB::raw('max(bar_price) as ending_price'),
            DB::raw('SUBSTRING_INDEX(exterior_image, \',\', 1) AS image_id'),
            'kernel.image_table.image_name','city_table.city_name',
            'hotels_table.hotel_description','kernel.company_table.api_key','hotel_address','company_table.api_key')
            ->take(10)
            ;
        
        
        if($star_rating != '')
        {
            $group_hotels = $group_hotels->Where('kernel.hotels_table.star_of_property', $star_rating);
        }
        if($str_amenities != '')
        {
            $arr_amenities = explode(",",$str_amenities);
            foreach($arr_amenities as $amenity)
            {
                $group_hotels = $group_hotels->Where('kernel.hotels_table.facility',  'like', "%$amenity%");
            }
        }
        
        $group_hotels = $group_hotels->get();
        $hotels_data = array();
        $city_name = $group_hotels[0]->city_name;
        foreach($group_hotels as $hotel_info)
        {
            $hotel_id = $hotel_info->hotel_id;
            $api_key = $hotel_info->api_key;
            //get inventory
            $curl = curl_init();

            curl_setopt_array($curl, array(
            CURLOPT_URL => "https://be.bookingjini.com/bookingEngine/get-inventory/$api_key/$hotel_id/$checkin_date/$checkout_date/INR",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            ));

            $response = curl_exec($curl);

            curl_close($curl);
            $res_json = json_decode($response, true);
            $hotel_has_rooms = FALSE;
            if($res_json['status'] == 1)
            {
                $room_types = $res_json['room_data'];
                foreach($room_types as $room_type)
                {
                    $room_type_room_available = TRUE;
                    $room_inventories = $room_type['inv'];
                    foreach($room_inventories as $room_inventory)
                    {
                        if($no_of_rooms  > $room_inventory['no_of_rooms'])
                        {
                            $room_type_room_available = FALSE;
                            break;
                        }
                    }
                    if($room_type_room_available)
                    {
                        $hotel_has_rooms = TRUE;
                        break;
                    }
                }
            }
            if($hotel_has_rooms)
            {
                $hotel_record['hotel_id'] = $hotel_info->hotel_id;
                $hotel_record['hotel_name'] = $hotel_info->hotel_name;
                $hotel_record['star'] = $hotel_info->star_of_property;
                $hotel_record['image'] = env('S3_IMAGE_PATH').$hotel_info->image_name;
                $hotel_record['ending_price'] = $hotel_info->ending_price;
                $hotel_record['starting_price'] = $hotel_info->starting_price;
                $hotel_record['city_name'] = $hotel_info->city_name;
                $hotel_record['hotel_description'] = strip_tags($hotel_info->hotel_description);
                $hotel_record['max_guest'] = 3;
                $hotel_record['hotel_address'] = $hotel_info->hotel_address;
                $hotel_record['api_key'] = $hotel_info->api_key;
                $hotels_data[] = $hotel_record;
            }  
        }
        $msg = array('status' => 1,'message'=>'Hotels List','hotels_data' => $hotels_data,'hotels_found'=>count($hotels_data));
        return response()->json($msg);
    }
    
    //================================================================================================================================
    public function getGroupHotelCityAndHotels($group_id)
    {
        $results = array();
        $group_cities = DB::table('kernel.hotels_table')
        ->join('kernel.city_table', 'hotels_table.city_id', '=', 'city_table.city_id')
        ->Where('kernel.hotels_table.company_id', $group_id)
        ->Where('city_table.city_name', '!=', 'Darjeeling')
        ->Where('city_table.city_name', '!=', 'Manali')
        ->Where('city_table.city_name', '!=', 'Bihar Sharif')
        ->select(DB::raw('distinct city_table.city_id, city_name'))
        ->get();

        foreach($group_cities as $group_city)
        {
            $result['id'] = $group_city->city_id;
            $result['name'] = $group_city->city_name;
            $result['type'] = 'city';
            $results[] = $result;
        }

        $group_hotels = DB::table('kernel.hotels_table')
        ->join('kernel.city_table', 'hotels_table.city_id', '=', 'city_table.city_id')
        ->Where('kernel.hotels_table.company_id', $group_id)
        ->select('hotels_table.hotel_id','hotels_table.hotel_name')
        ->get();

        foreach($group_hotels as $group_hotel)
        {
            $result['id'] = $group_hotel->hotel_id;
            $result['name'] = $group_hotel->hotel_name;
            $result['type'] = 'hotel';
            $results[] = $result;
        }
        
        $msg = array('status' => 1,'message'=>'City and Hotel List','results' => $results);
        return response()->json($msg);

    }
    //================================================================================================================================
    public function getGroupHotelsCategory($group_id)
    {
        //Hotel List
        $group_hotels = DB::table('kernel.hotels_table')
        ->Where('kernel.hotels_table.company_id', $group_id)
        ->select('kernel.hotels_table.hotel_id','hotel_name','hotel_category','property_type')
        ->get();

        $luxury_hotels = array();
        $boutique_hotels = array();
        $business_hotels = array();
        $top_hotels = array();

        $hotels_data = array();
        foreach($group_hotels as $hotel_info)
        {
            
            $hotel_record['hotel_id'] = $hotel_info->hotel_id;
            $hotel_record['hotel_name'] = $hotel_info->hotel_name;
            if($hotel_info->hotel_category != '' && $hotel_info->hotel_category != 'NA')
                $hotel_categories[$hotel_info->hotel_category][] = $hotel_record;
            if($hotel_info->property_type != '' && $hotel_info->property_type != 'NA')
                $property_types[$hotel_info->property_type][] = $hotel_record;
            if(
                $hotel_info->hotel_id == 2484 
                || $hotel_info->hotel_id == 2897
                || $hotel_info->hotel_id == 2498
                || $hotel_info->hotel_id == 2868
            )
            {
                //$hotel_categories['TOP'] = $hotel_record;
                $top_hotels[] = $hotel_record;
            }
        }
        $msg = array('status' => 1,'message'=>'Hotels List','hotels_categories' => $hotel_categories,'property_types'=>$property_types,'top_hotels'=>$top_hotels);
        return response()->json($msg);

    }
    //================================================================================================================================
    public function getFilteredHotelList3(Request $request )
    {
        $destination_image = '';
        $group_id = $request['group_id'];
        $city_name = $request['city_name'];
        $star_rating = (int)$request['star_rating'];
        $min_price = (int)$request['min_price'];
        $max_price = (int)$request['max_price'];
        $str_amenities = $request['amenities'];
        $str_category_name = $request['category'];
        
        $ttdc_premium_hotel_list = [2501,2490,2491, 2487, 2505, 2506, 2493, 2499, 2509, 2482, 2520];
        $group_hotels = DB::table('kernel.hotels_table')
            ->join('kernel.image_table', DB::raw('SUBSTRING_INDEX(exterior_image, \',\', 1)'), '=', 'image_table.image_id')
            ->join('kernel.city_table', 'hotels_table.city_id', '=', 'city_table.city_id')
            ->join('kernel.room_rate_plan', 'hotels_table.hotel_id', '=', 'room_rate_plan.hotel_id')
            ->Where('kernel.hotels_table.company_id', $group_id)
            ->groupBy('kernel.hotels_table.hotel_id','hotel_name','star_of_property','exterior_image','image_name','city_table.city_name','hotels_table.hotel_description')
            ->select('kernel.hotels_table.hotel_id','hotel_name','star_of_property',DB::raw('min(bar_price) as starting_price'), DB::raw('max(bar_price) as ending_price'),
            DB::raw('SUBSTRING_INDEX(exterior_image, \',\', 1) AS image_id'),'kernel.image_table.image_name','city_table.city_name','hotels_table.hotel_description','hotels_table.hotel_category')
            ;
        if($city_name != '')
        {
            $group_hotels = $group_hotels->Where('kernel.city_table.city_name', $city_name);
            $destination_image = env('S3_ROOT_PATH')."group-website/$group_id/destination-photos/".strtolower($city_name).'.jpg';
        }
        if($star_rating != '')
        {
            $group_hotels = $group_hotels->Where('kernel.hotels_table.star_of_property', $star_rating);
        }
        if($str_amenities != '')
        {
            $arr_amenities = explode(",",$str_amenities);
            foreach($arr_amenities as $amenity)
            {
                $group_hotels = $group_hotels->Where('kernel.hotels_table.facility',  'like', "%$amenity%");
            }
        }
        if($str_category_name != '')
        {
            $group_hotels = $group_hotels->Where('kernel.hotels_table.hotel_category', $str_category_name);
        }
        if($min_price != '' && $max_price != '')
        {
            $group_hotels = $group_hotels->havingRaw('starting_price >= '.$min_price);
            $group_hotels = $group_hotels->havingRaw('ending_price <= '.$max_price);
            
        }
        $group_hotels = $group_hotels->get();
        $hotels_data = array();
        foreach($group_hotels as $hotel_info)
        {
            $hotel_record['hotel_id'] = $hotel_info->hotel_id;
            $hotel_record['hotel_name'] = $hotel_info->hotel_name;
            $hotel_record['star'] = $hotel_info->star_of_property;
            $hotel_record['image'] = env('S3_IMAGE_PATH').$hotel_info->image_name;
            $hotel_record['ending_price'] = $hotel_info->ending_price;
            $hotel_record['starting_price'] = $hotel_info->starting_price;
            $hotel_record['city_name'] = $hotel_info->city_name;
            $hotel_record['hotel_description'] = strip_tags($hotel_info->hotel_description);
            $hotel_record['max_guest'] = 3;
            if(in_array($hotel_info->hotel_id,$ttdc_premium_hotel_list))
                $hotel_record['is_premium'] = TRUE;
            else
                $hotel_record['is_premium'] = FALSE;
            $hotel_record['category'] = $hotel_info->hotel_category;
            $hotels_data[] = $hotel_record;
        }
        $msg = array('status' => 1,'message'=>'Hotels List','hotels_data' => $hotels_data,'destination_image'=>$destination_image);
        return response()->json($msg);

    }
    //============================================================================================================================
    public function getHotelFAQ(int $hotel_id)
    {
        $faqs = array();
        $hotel_faqs = HotelFAQ::where('hotel_id',$hotel_id)->where('status',1)->get();
        if($hotel_faqs)
        {
            $all_faqs = $hotel_faqs;
        }
        

        foreach($all_faqs as $h_faq)
        {
            $faq['question'] = $h_faq['question'];
            $faq['answer'] = $h_faq['answer'];
            $faqs[] = $faq;
        }
        
        $msg = array('status' => 1,'message'=>'FAQ found','faqs' => $faqs);
        return response()->json($msg);

    }
    //================================================================================================================================
    public function getPackageBanners(int $group_id)
    {
        $group_package_banners = DB::table('kernel.package_banner')
        ->Where('kernel.package_banner.company_id', $group_id)
        ->Where('kernel.package_banner.status', 1)
        ->select('package_banner.package_name','package_banner.package_banner_description','package_banner.package_banner_image'
        )
        ->get();
        
        $package_banners = array();
        foreach($group_package_banners as $group_package_banner)
        {
            $banner_record['package_name'] = $group_package_banner->package_name;
            $banner_record['package_banner_description'] = $group_package_banner->package_banner_description;
            $banner_record['banner_image'] = env('S3_ROOT_PATH')."group-website/$group_id/package-banner/".$group_package_banner->package_banner_image;
            $package_banners[] = $banner_record;
        }
        $msg = array('status' => 1,'message'=>'Package Banners','package_banners' => $package_banners);
        return response()->json($msg);

    }
    //================================================================================================================================
    public function getFilteredHotelList4(Request $request )
    {
        $destination_image = '';
        $destination_description = '';
        $group_id = $request['group_id'];
        $city_name = $request['city_name'];
        $star_rating = (int)$request['star_rating'];
        $min_price = (int)$request['min_price'];
        $max_price = (int)$request['max_price'];
        $str_amenities = $request['amenities'];
        $str_category_name = $request['category'];
        $str_property_type = $request['property_type'];
        
        $group_hotels = DB::table('kernel.hotels_table')
            ->join('kernel.image_table', DB::raw('SUBSTRING_INDEX(exterior_image, \',\', 1)'), '=', 'image_table.image_id')
            ->join('kernel.city_table', 'hotels_table.city_id', '=', 'city_table.city_id')
            ->join('kernel.room_rate_plan', 'hotels_table.hotel_id', '=', 'room_rate_plan.hotel_id')
            ->Where('kernel.hotels_table.company_id', $group_id)
            ->groupBy('kernel.hotels_table.hotel_id','hotel_name','star_of_property','exterior_image','image_name','city_table.city_name','hotels_table.hotel_description','city_table.city_id')
            ->select('kernel.hotels_table.hotel_id','hotel_name','star_of_property',DB::raw('min(bar_price) as starting_price'), DB::raw('max(bar_price) as ending_price'),
            DB::raw('SUBSTRING_INDEX(exterior_image, \',\', 1) AS image_id'),'kernel.image_table.image_name','city_table.city_name','hotels_table.hotel_description','hotels_table.hotel_category',
            'city_table.city_id')
            ;
        if($city_name != '')
        {
            $group_hotels = $group_hotels->Where('kernel.city_table.city_name', $city_name);
        }
        if($star_rating != '')
        {
            $group_hotels = $group_hotels->Where('kernel.hotels_table.star_of_property', $star_rating);
        }
        if($str_amenities != '')
        {
            $arr_amenities = explode(",",$str_amenities);
            foreach($arr_amenities as $amenity)
            {
                $group_hotels = $group_hotels->Where('kernel.hotels_table.facility',  'like', "%$amenity%");
            }
        }
        if($str_category_name != '')
        {
            $group_hotels = $group_hotels->Where('kernel.hotels_table.hotel_category', $str_category_name);
        }
        if($str_property_type != '')
        {
            $group_hotels = $group_hotels->Where('kernel.hotels_table.property_type', $str_property_type);
        }
        
        if($min_price != '' && $max_price != '')
        {
            $group_hotels = $group_hotels->havingRaw('starting_price >= '.$min_price);
            $group_hotels = $group_hotels->havingRaw('ending_price <= '.$max_price);
            
        }
        $group_hotels = $group_hotels->get();

        
        if($city_name != '')
        {
            //$city_id = $group_hotels[0]->city_id;
            //destinations table
            $group_destination = DB::table('kernel.group_destinations')
            ->join('kernel.city_table', 'group_destinations.city_id', '=', 'city_table.city_id')
            ->Where('kernel.group_destinations.company_id', $group_id)
            ->Where('kernel.city_table.city_name', $city_name)
            ->select('city_image','city_description')
            ->first();
            $destination_image = env('S3_ROOT_PATH')."group-website/$group_id/destination-photos/".$group_destination->city_image;
            $destination_description = $group_destination->city_description;
        }
        $hotels_data = array();
        foreach($group_hotels as $hotel_info)
        {
            $hotel_record['hotel_id'] = $hotel_info->hotel_id;
            $hotel_record['hotel_name'] = $hotel_info->hotel_name;
            $hotel_record['star'] = $hotel_info->star_of_property;
            $hotel_record['image'] = env('S3_IMAGE_PATH').$hotel_info->image_name;
            $hotel_record['ending_price'] = $hotel_info->ending_price;
            $hotel_record['starting_price'] = $hotel_info->starting_price;
            $hotel_record['city_name'] = $hotel_info->city_name;
            $hotel_record['city_id'] = $hotel_info->city_id;
            $hotel_record['hotel_description'] = strip_tags($hotel_info->hotel_description);
            $hotel_record['max_guest'] = 3;
            $hotel_record['category'] = $hotel_info->hotel_category;
            $hotels_data[] = $hotel_record;
        }
        $msg = array(
            'status' => 1,
            'message'=>'Hotels List',
            'hotels_data' => $hotels_data,
            'destination_image'=>$destination_image,
            'destination_description'=>$destination_description
        );
        return response()->json($msg);

    }
    //============================================================================================================================
    public function getPackageHotelListByDestination(Request $request)
    {
        //Hotel List
        $today = date('Y-m-d');
        $group_id = (int)$request['group_id'];
        $package_name = $request['package_name'];
        $city_id = $request['city_id'];
        $group_packages = DB::table('kernel.package_table')
        ->join('kernel.hotels_table', 'hotels_table.hotel_id', '=', 'package_table.hotel_id')
        ->join('kernel.city_table', 'hotels_table.city_id', '=', 'city_table.city_id')
        ->join('kernel.image_table', DB::raw('SUBSTRING_INDEX(package_image, \',\', 1)'), '=', 'image_table.image_id')
        ->Where('kernel.hotels_table.company_id', $group_id)
        //->Where('kernel.package_table.date_to', '>', $today)
        ->Where('kernel.package_table.package_name', $package_name)
        ->select('package_table.package_name','hotels_table.hotel_name','hotels_table.hotel_id',
        'package_table.package_id','package_table.date_from','package_table.date_to','kernel.image_table.image_name',
        'city_table.city_id','city_table.city_name'
        );
        if($city_id != 'ALL')
        {
            $city_id = (int)$city_id;
            $group_packages = $group_packages->Where('kernel.hotels_table.city_id', $city_id);
        }
        $group_packages = $group_packages->get();
        
        $package_destinations = array();
        $package_records = array();
        
        $destination_ids = array();
        foreach($group_packages as $group_package)
        {
            $package_record['hotel_id'] = $group_package->hotel_id;
            $package_record['hotel_name'] = $group_package->hotel_name;
            $package_record['package_id'] = $group_package->package_id;
            $package_record['package_name'] = $group_package->package_name;
            $package_record['date_from'] = $group_package->date_from;
            $package_record['date_to'] = $group_package->date_to;
            $package_record['image'] = env('S3_IMAGE_PATH').$group_package->image_name;
            $package_record['city_id'] = $group_package->city_id;
            $package_record['city_name'] = $group_package->city_name;
            $package_records[] = $package_record;
            $destination_record = array();
            if(!in_array($group_package->city_id,$destination_ids))
            {
                $destination_record['city_id'] = $group_package->city_id;
                $destination_record['city_name'] = $group_package->city_name;
                $package_destinations[] = $destination_record;
                $destination_ids[] = $group_package->city_id;
            }
            
        }
        $msg = array('status' => 1,'message'=>'Package List','package_hotel_list' => $package_records,'package_destinations'=>$package_destinations);
        return response()->json($msg);

    }
    //============================================================================================================
    public function checkGroupAvailabilityByCityAndDates(Request $request )
    {
        $city_id = (int)$request['city_id'];
        $checkin_date = date('d-m-Y',strtotime($request['checkin_date']));
        $checkout_date = date('d-m-Y',strtotime($request['checkout_date']));
        $no_of_rooms = (int)$request['no_of_rooms'];
        $star_rating = (int)$request['star_rating'];
        $min_price = $request['min_price'];
        $max_price = $request['max_price'];
        $str_amenities = $request['amenities'];
        $company_id = isset($request->company_id)?(int)$request->company_id:'';
        
        $group_hotels = DB::table('kernel.hotels_table')
            ->join('kernel.image_table', DB::raw('SUBSTRING_INDEX(exterior_image, \',\', 1)'), '=', 'image_table.image_id')
            ->join('kernel.city_table', 'hotels_table.city_id', '=', 'city_table.city_id')
            ->join('kernel.room_rate_plan', 'hotels_table.hotel_id', '=', 'room_rate_plan.hotel_id')
            ->join('kernel.company_table', 'hotels_table.company_id', '=', 'company_table.company_id')
            ->Where('kernel.city_table.city_id', $city_id)
            ->Where('kernel.hotels_table.status', 1)
            ->Where('kernel.company_table.company_id', $company_id)
            ->groupBy('kernel.hotels_table.hotel_id','hotel_name','star_of_property','exterior_image','image_name','city_table.city_name',
            'hotels_table.hotel_description','kernel.company_table.api_key','hotel_address','company_table.api_key')
            ->select('kernel.hotels_table.hotel_id','hotel_name','star_of_property',
            DB::raw('min(bar_price) as starting_price'), 
            DB::raw('max(bar_price) as ending_price'),
            DB::raw('SUBSTRING_INDEX(exterior_image, \',\', 1) AS image_id'),
            'kernel.image_table.image_name','city_table.city_name',
            'hotels_table.hotel_description','kernel.company_table.api_key','hotel_address','company_table.api_key')
            ;
        
        
        if($star_rating != '')
        {
            $group_hotels = $group_hotels->Where('kernel.hotels_table.star_of_property', $star_rating);
        }
        if($str_amenities != '')
        {
            $arr_amenities = explode(",",$str_amenities);
            foreach($arr_amenities as $amenity)
            {
                $group_hotels = $group_hotels->Where('kernel.hotels_table.facility',  'like', "%$amenity%");
            }
        }
        
        $group_hotels = $group_hotels->get();
        $hotels_data = array();
        if(count($group_hotels) > 0)
        {
            $city_name = $group_hotels[0]->city_name;
            foreach($group_hotels as $hotel_info)
            {
                $hotel_id = $hotel_info->hotel_id;
                $api_key = $hotel_info->api_key;
                //get inventory
                $curl = curl_init();
    
                curl_setopt_array($curl, array(
                CURLOPT_URL => "https://be.bookingjini.com/bookingEngine/get-inventory/$api_key/$hotel_id/$checkin_date/$checkout_date/INR",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                ));
    
                $response = curl_exec($curl);
    
                curl_close($curl);
                $res_json = json_decode($response, true);
                $hotel_has_rooms = FALSE;
                if($res_json['status'] == 1)
                {
                    $room_types = $res_json['room_data'];
                    foreach($room_types as $room_type)
                    {
                        $room_type_room_available = TRUE;
                        $room_inventories = $room_type['inv'];
                        foreach($room_inventories as $room_inventory)
                        {
                            if($no_of_rooms  > $room_inventory['no_of_rooms'])
                            {
                                $room_type_room_available = FALSE;
                                break;
                            }
                        }
                        if($room_type_room_available)
                        {
                            $hotel_has_rooms = TRUE;
                            break;
                        }
                    }
                }
                if($hotel_has_rooms)
                {
                    $hotel_record['hotel_id'] = $hotel_info->hotel_id;
                    $hotel_record['hotel_name'] = $hotel_info->hotel_name;
                    $hotel_record['star'] = $hotel_info->star_of_property;
                    $hotel_record['image'] = env('S3_IMAGE_PATH').$hotel_info->image_name;
                    $hotel_record['ending_price'] = $hotel_info->ending_price;
                    $hotel_record['starting_price'] = $hotel_info->starting_price;
                    $hotel_record['city_name'] = $hotel_info->city_name;
                    $hotel_record['hotel_description'] = strip_tags($hotel_info->hotel_description);
                    $hotel_record['max_guest'] = 3;
                    $hotel_record['hotel_address'] = $hotel_info->hotel_address;
                    $hotel_record['api_key'] = $hotel_info->api_key;
                    $hotels_data[] = $hotel_record;
                }  
            }
            $msg = array('status' => 1,'message'=>'Hotels List','hotels_data' => $hotels_data,'hotels_found'=>count($hotels_data));
        }
        else
        {
            $msg = array('status' => 1,'message'=>'Hotels List','hotels_data' => array(),'hotels_found'=>0);
        }
        
        return response()->json($msg);
    }
    
}
