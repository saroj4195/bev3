<?php
namespace App\Http\Controllers\hotel_chain;
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
use App\HotelStatistics;
use App\Http\Controllers\Controller;

class HotelController extends Controller 
{
    public function getGroupHotelList($group_id)
    {
        //Hotel List
        if($group_id == "BYKE")
        {
            $hotel_ids = array(3084,3058,3057,3053,3047,3046,3045,3043,3039,3038,3037,3036,3033,3419);
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
        $hotels_data = array();
        
        //Place List
        if($group_id != 0)
        {
            $group_hotels = DB::table('kernel.city_table')
            ->Where(DB::raw('lower(kernel.city_table.city_name)'), 'like',"$query_text%")
            ->select(DB::raw('kernel.city_table.city_id,city_table.city_name'))
            ->take(20)
            ->get();
        }
        else
        {
            $group_hotels = DB::table('kernel.hotels_table')
            ->join('kernel.city_table', 'hotels_table.city_id', '=', 'city_table.city_id')
            ->Where('kernel.hotels_table.status', 1)
            ->Where(DB::raw('lower(kernel.city_table.city_name)'), 'like',"$query_text%")
            ->Where('city_table.city_name', '!=',"Darjeeling")
            ->Where('city_table.city_name', '!=',"Manali")
            ->select(DB::raw('DISTINCT kernel.city_table.city_id,city_table.city_name'))
            ->take(20)
            ->get();
        }

        
        foreach($group_hotels as $hotel_info)
        {
            $city_record['city_id'] = $hotel_info->city_id;
            $city_record['city_name'] = $hotel_info->city_name;
            $city_record['type'] = 'CITY';
            $hotels_data[] = $city_record;
        }

        //Hotel List
        if($group_id != 0)
        {
            $group_hotels = DB::table('kernel.hotels_table')
            ->Where('kernel.hotels_table.company_id', $group_id)
            ->Where('kernel.hotels_table.status', 1)
            ->Where(DB::raw('lower(kernel.hotels_table.hotel_name)'), 'like',"$query_text%")
            ->select('kernel.hotels_table.hotel_id','hotel_name')
            ->take(20)
            ->get();
        }
        else
        {
            $group_hotels = DB::table('kernel.hotels_table')
            ->Where('kernel.hotels_table.status', 1)
            ->Where(DB::raw('lower(kernel.hotels_table.hotel_name)'), 'like',"%$query_text%")
            ->select('kernel.hotels_table.hotel_id','hotel_name')
            ->take(20)
            ->get();
        }

        
        foreach($group_hotels as $hotel_info)
        {
            $hotel_record['hotel_id'] = $hotel_info->hotel_id;
            $hotel_record['hotel_name'] = $hotel_info->hotel_name;
            $hotel_record['type'] = 'HOTEL';
            $hotels_data[] = $hotel_record;
        }

        

        //area List
        if($group_id != 0)
        {
            $group_hotels = DB::table('kernel.hotels_table')
            ->Where('kernel.hotels_table.company_id', $group_id)
            ->Where('kernel.hotels_table.status', 1)
            ->Where('kernel.hotels_table.hotel_address', 'like',"%$query_text%")
            ->select(DB::raw('DISTINCT kernel.hotels_table.hotel_address'))
            ->take(20)
            ->get();
        }
        else
        {
            // $group_hotels = DB::table('kernel.hotels_table')
            // ->Where('kernel.hotels_table.status', 1)
            // ->Where('kernel.hotels_table.hotel_address', 'like',"%$query_text%")
            // ->select(DB::raw('DISTINCT kernel.hotels_table.hotel_address'))
            // ->take(20)
            // ->get();
            $group_hotels = [];
        }
 
        
        foreach($group_hotels as $hotel_info)
        {
            $area_record['city_id'] = 1;
            $area_record['city_name'] = $hotel_info->hotel_address;
            $area_record['type'] = 'AREA';
            $hotels_data[] = $area_record;
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
        if(isset($hotel_images[0]))
            $hotel_record['image'] = env('S3_IMAGE_PATH').$hotel_images[0]->image_name;
        else
            $hotel_record['image'] = '';
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

        $today = date('Y-m-d');
        $curl = curl_init();

        curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://be.bookingjini.com/extranetv4/min-hotel-price',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS =>'{
            "hotel_id":['.$hotel_id.'],
            "from_date":"'.$today.'"
        }',
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json'
        ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        $response = json_decode($response, true);
        $data = $response['data'];

        $hotel_record['starting_price'] = $data[0]['minimum_rates'];

        
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
            else if($group_id == 3610) //AWAY holidays
                $destinations = array(0=>'Kochi');
        }
        $msg = array('status' => 1,'message'=>'Destination List','destinations' => $destinations);
        return response()->json($msg);

    }
    //================================================================================================================================
    public function getGroupHotelCities($group_id)
    {
        //Hotel List
        if($group_id == "BYKE")
        {
            $hotel_ids = array(3084,3058,3057,3053,3047,3046,3045,3043,3039,3038,3037,3036,3033,3419);
            $group_hotels = DB::table('kernel.hotels_table')
            ->join('kernel.city_table', 'hotels_table.city_id', '=', 'city_table.city_id')
            ->WhereIn('kernel.hotels_table.hotel_id', $hotel_ids)
            //->Where('city_table.city_name', '!=', 'Darjeeling')
            //->Where('city_table.city_name', '!=', 'Manali')
            ->Where('city_table.city_name', '!=', 'Bihar Sharif')
            ->select(DB::raw('distinct city_table.city_id, city_name'))
            ->orderBy('city_table.city_name')
            ->get();
        }
        else
        {
            $group_hotels = DB::table('kernel.hotels_table')
            ->join('kernel.city_table', 'hotels_table.city_id', '=', 'city_table.city_id')
            ->Where('kernel.hotels_table.company_id', $group_id)
            ->Where('city_table.city_name', '!=', 'Darjeeling')
            ->Where('city_table.city_name', '!=', 'Manali')
            ->Where('city_table.city_name', '!=', 'Bihar Sharif')
            ->select(DB::raw('distinct city_table.city_id, city_name'))
            ->get();
        }
        
        
        $msg = array('status' => 1,'message'=>'City List','cities' => $group_hotels);
        return response()->json($msg);

    }
    //================================================================================================================================
    public function getGroupHotelsByCity($group_id, $city_id)
    {
        //Hotel List
        if($group_id == "BYKE")
        {
            $hotel_ids = array(3084,3083,3058,3057,3053,3047,3046,3045,3043,3039,3038,3037,3036,3033,3419);
            $group_hotels = DB::table('kernel.hotels_table')
            ->join('kernel.city_table', 'hotels_table.city_id', '=', 'city_table.city_id')
            ->join('kernel.company_table', 'hotels_table.company_id', '=', 'company_table.company_id')
            ->WhereIn('kernel.hotels_table.hotel_id', $hotel_ids)
            ->Where('city_table.city_id', $city_id)
            ->select('hotels_table.hotel_id','hotels_table.hotel_name','subdomain_name as be_url')
            ->get();
        }
        else
        {
            $group_hotels = DB::table('kernel.hotels_table')
            ->join('kernel.city_table', 'hotels_table.city_id', '=', 'city_table.city_id')
            ->join('kernel.company_table', 'hotels_table.company_id', '=', 'company_table.company_id')
            ->Where('kernel.hotels_table.company_id', $group_id)
            ->Where('city_table.city_id', $city_id)
            ->select('hotels_table.hotel_id','hotels_table.hotel_name','subdomain_name as be_url')
            ->get();
        }
        
        
        
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
        $page_no =  (int)$request['page_no'];
        $skip = ($page_no - 1) * 10;
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
            ->orderBy('hotel_name')
            ->skip($skip)
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
            ->select('hotels_table.hotel_id','hotels_table.hotel_name','hotels_table.hotel_address')
            ->get();
        $areas = array();
        foreach($group_hotels as $group_hotel)
        {
            
            $address = $group_hotel->hotel_address;
            $arr_address = explode('|',$address);
            if($arr_address[2] != '')
            {
                $areas[] = $arr_address[2];
            }
            
        }
        $areas = array_unique($areas);
        $area_id = 1;
        foreach($areas as $area)
        {
            $result['id'] = $area_id;
            $result['name'] = $area;
            $result['type'] = 'area';
            $results[] = $result;
            $area_id++;
        }

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
        $hotel_categories = array();
        $hotels_data = array();
        $property_types = array();
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
    //============================================================================================================
    public function getPopularHotels($group_id )
    {
        $company_id = $group_id;
        $checkin_date = date('Y-m-d');
        $checkout_date = date('Y-m-d',strtotime('+1 day'));
        $no_of_rooms = 1;
        $group_hotels = DB::table('kernel.hotels_table')
            ->join('kernel.image_table', DB::raw('SUBSTRING_INDEX(exterior_image, \',\', 1)'), '=', 'image_table.image_id')
            ->join('kernel.city_table', 'hotels_table.city_id', '=', 'city_table.city_id')
            ->join('kernel.room_rate_plan', 'hotels_table.hotel_id', '=', 'room_rate_plan.hotel_id')
            ->join('kernel.company_table', 'hotels_table.company_id', '=', 'company_table.company_id')
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
            ->take(20)
            ->get()
            ;
        
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
            $msg = array('status' => 2,'message'=>'Hotels List','hotels_data' => array(),'hotels_found'=>0);
        }
        
        return response()->json($msg);
    }
    //============================================================================================================
    public function getRecentViewedHotels($group_id, $user_id )
    {
        $company_id = $group_id;
        $checkin_date = date('Y-m-d');
        $checkout_date = date('Y-m-d',strtotime('+1 day'));
        $no_of_rooms = 1;
        $group_hotels = DB::table('kernel.hotels_table')
            ->join('kernel.image_table', DB::raw('SUBSTRING_INDEX(exterior_image, \',\', 1)'), '=', 'image_table.image_id')
            ->join('kernel.city_table', 'hotels_table.city_id', '=', 'city_table.city_id')
            ->join('kernel.room_rate_plan', 'hotels_table.hotel_id', '=', 'room_rate_plan.hotel_id')
            ->join('kernel.company_table', 'hotels_table.company_id', '=', 'company_table.company_id')
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
            ->take(6)
            ->get()
            ;
        
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
    //================================================================================================
    public function getPopularDestinations($group_id )
    {
        $company_id = $group_id;
        $hotel_locations = DB::table('kernel.hotels_table')
            ->join('kernel.city_table', 'hotels_table.city_id', '=', 'city_table.city_id')
            ->Where('kernel.hotels_table.company_id', $company_id)
            // ->WhereIn('city_table.city_name', ['Bhagalpur','Delhi','Bhubaneswar','Coorg','Mumbai'])
            ->select('city_table.city_id', 'city_name', DB::raw('count(hotel_id) AS hotel_count'))
            ->groupBy('city_table.city_id', 'city_name')
            ->take(5)
            ->get();
        $popular_destinations = array();
        foreach($hotel_locations as $hotel_location)
        {
            $destination['city_id'] = $hotel_location->city_id;
            $city_name =  $hotel_location->city_name;
            $destination['city_name'] = $city_name;
            $destination['city_image'] = env('S3_ROOT_PATH')."group-website/$group_id/destination-photos/".strtolower($city_name).'.jpg';
            $destination['hotel_count'] = $hotel_location->hotel_count; 
            $popular_destinations[] = $destination;
        }
        $msg = array('status' => 1,'message'=>'data found','destinations'=>$popular_destinations);
        return response()->json($msg);
    }
    //================================================================================================================================
    public function checkAvailabilityByHotelID(Request $request )
    {
        $hotel_id = (int)$request['hotel_id'];
        $checkin_date = date('d-m-Y',strtotime($request['checkin_date']));
        $checkout_date = date('d-m-Y',strtotime($request['checkout_date']));
        $no_of_rooms = (int)$request['no_of_rooms'];
        
        $group_hotels = DB::table('kernel.hotels_table')
            ->join('kernel.image_table', DB::raw('SUBSTRING_INDEX(exterior_image, \',\', 1)'), '=', 'image_table.image_id')
            ->join('kernel.room_rate_plan', 'hotels_table.hotel_id', '=', 'room_rate_plan.hotel_id')
            ->join('kernel.company_table', 'hotels_table.company_id', '=', 'company_table.company_id')
            ->Where('kernel.hotels_table.hotel_id', $hotel_id)
            ->Where('kernel.hotels_table.status', 1)
            ->groupBy('kernel.hotels_table.hotel_id','hotel_name','star_of_property','exterior_image','image_name',
            'hotels_table.hotel_description','kernel.company_table.api_key','hotel_address','company_table.api_key')
            ->select('kernel.hotels_table.hotel_id','hotel_name','star_of_property',
            DB::raw('min(bar_price) as starting_price'), 
            DB::raw('max(bar_price) as ending_price'),
            DB::raw('SUBSTRING_INDEX(exterior_image, \',\', 1) AS image_id'),
            'kernel.image_table.image_name',
            'hotels_table.hotel_description','kernel.company_table.api_key','hotel_address','company_table.api_key')
            ->get()
            ;
       
        $hotels_data = array();
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
            $hotel_data = null;
            if($hotel_has_rooms)
            {
                $hotel_record['hotel_id'] = $hotel_info->hotel_id;
                $hotel_record['hotel_name'] = $hotel_info->hotel_name;
                $hotel_record['star'] = $hotel_info->star_of_property;
                $hotel_record['image'] = env('S3_IMAGE_PATH').$hotel_info->image_name;
                $hotel_record['ending_price'] = $hotel_info->ending_price;
                $hotel_record['starting_price'] = $hotel_info->starting_price;
                $hotel_record['hotel_description'] = strip_tags($hotel_info->hotel_description);
                $hotel_record['max_guest'] = 3;
                $hotel_record['hotel_address'] = $hotel_info->hotel_address;
                $hotel_record['api_key'] = $hotel_info->api_key;
                $hotel_data = $hotel_record;
                $status = 1;
                $message = 'Room Available';
            }  
            else
            {
                $status = 0;
                $message = 'No Room Available';
            }
        }
        $msg = array('status' => $status,'message'=>$message,'hotel_data' => $hotel_data);
        return response()->json($msg);
    }
    //================================================================================================================================
    public function getBEIFrameURL($hotel_id, $checkin_date, $checkout_date)
    {
        
        $company_details = DB::table('kernel.hotels_table')
        ->join('kernel.company_table', 'hotels_table.company_id', '=', 'company_table.company_id')
        ->Where('kernel.hotels_table.hotel_id', $hotel_id)
        ->select('kernel.hotels_table.hotel_id', 'company_table.subdomain_name', 'company_table.company_id')
        ->first();
        date_default_timezone_set('Asia/Calcutta');
        $checkin_date = date('Y-m-d',strtotime($checkin_date));
        $checkout_date = date('Y-m-d', strtotime($checkout_date));
        $q = base64_encode((strtotime($checkin_date) * 1000)."|".(strtotime($checkout_date) * 1000)."|".$hotel_id."||||");
        $url = $company_details->subdomain_name;
        $be_url = 'https://'.$url.'/property/?q='.$q;
        header("location: $be_url");
    }
    //================================================================================================================
    
    public function openBEURL($hotel_id)
    {
        $company_details = DB::table('kernel.hotels_table')
        ->join('kernel.company_table', 'hotels_table.company_id', '=', 'company_table.company_id')
        ->Where('kernel.hotels_table.hotel_id', $hotel_id)
        ->select('kernel.hotels_table.hotel_id', 'company_table.subdomain_name', 'company_table.company_id')
        ->first();
        date_default_timezone_set('Asia/Calcutta');
        $checkin_date = date('Y-m-d');
        $checkout_date = date('Y-m-d', strtotime("+1 day"));
        $q = base64_encode((strtotime($checkin_date) * 1000)."|".(strtotime($checkout_date) * 1000)."|".$hotel_id."||||");
        $url = $company_details->subdomain_name;
        $be_url = 'https://'.$url.'/property/?q='.$q;
        //echo $be_url;
        echo '<script>window.location.href="'.$be_url.'"</script>';
        //header("location: $be_url");
    }
    //================================================================================================================================
    public function setCSMKPITarget($month, $year)
    {
        $days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);

        //get all CSM LIST
        $csm_sql = "SELECT super_admin_id, username
        FROM kernel.super_admin WHERE role_id = 23
        AND status = 1";
        $all_csm_list = DB::select("$csm_sql");

        //GET Assigned Hotels
        $manager_sql = "SELECT distinct hotel_id, super_admin_id FROM kernel.manager_details
        where status = 1
        AND hotel_id != 0
        and person_type = 23
        and hotel_id IN (select hotel_id from kernel.hotels_table where status = 1)
        ";
        $all_manager_list = DB::select("$manager_sql");

        foreach($all_manager_list as $manager_row)
        {
            $csm_hotel_list[$manager_row->super_admin_id][] = $manager_row->hotel_id;
        }

        //GET Hotels with BE
        $be_hotels_sql = "select T.hotel_id, sum(B.total_rooms) as total_rooms
            FROM
            (select distinct B.hotel_id from kernel.billing_table A
            left join kernel.hotels_table B ON A.company_id = B.company_id
            where A.product_name like '%Booking Engine%'
            and hotel_id is not null
            and B.status = 1)T
            INNER JOIN kernel.room_type_table B ON T.hotel_id = B.hotel_id
            WHERE B.is_trash = 0
            GROUP BY T.hotel_id
            ORDER BY T.hotel_id
        ";
        $be_hotel_list = DB::select("$be_hotels_sql");

        //GET Hotels With CM
        $cm_hotels_sql = "select T.hotel_id, sum(B.total_rooms) as total_rooms
            FROM
            (select distinct B.hotel_id from kernel.billing_table A
            left join kernel.hotels_table B ON A.company_id = B.company_id
            where A.product_name like '%Channel Manager%'
            and hotel_id is not null
            and B.status = 1)T
            INNER JOIN kernel.room_type_table B ON T.hotel_id = B.hotel_id
            WHERE B.is_trash = 0
            GROUP BY T.hotel_id
            ORDER BY T.hotel_id
        ";
        $cm_hotel_list = DB::select("$cm_hotels_sql");

        foreach($all_csm_list as $csm_row)
        {
            $csm_id = $csm_row->super_admin_id;
            $assigned_hotel_count = count($csm_hotel_list[$csm_id]);
            
            //1. No. of Hotels Logins in Extranet
            $kpi_id = 1;
            $target_sql = "INSERT INTO `booking_engine`.`csm_kpi_achievements`(`csm_id`,`month`,`year`,`kpi_id`,`base`,`target`,`achievement`)
                VALUES ($csm_id, $month, $year, $kpi_id, NULL, $assigned_hotel_count,0)
                ON DUPLICATE KEY UPDATE
                target = $assigned_hotel_count";
            DB::select("$target_sql");
            
            //2. Login Extranet Average Days/Month
            $kpi_id = 2;
            $target_sql = "INSERT INTO `booking_engine`.`csm_kpi_achievements`(`csm_id`,`month`,`year`,`kpi_id`,`base`,`target`,`achievement`)
                VALUES ($csm_id, $month, $year, $kpi_id, NULL, $days_in_month,0)
                ON DUPLICATE KEY UPDATE
                target = $days_in_month";
            DB::select("$target_sql");

            
            //3. No. of hotels getting Direct Bookings (BE)
            $kpi_id = 3;
            $csm_hotels_with_be = 0;
            $total_be_room_nights = 0;
            foreach($csm_hotel_list[$csm_id] as $hid)
            {
                foreach($be_hotel_list as $be_hotel_row)
                {
                    if($be_hotel_row->hotel_id == $hid)
                    {
                        $csm_hotels_with_be++;
                        $total_be_room_nights += $be_hotel_row->total_rooms * $days_in_month;
                        break;
                    }
                    
                }
            }
            
            $target_sql = "INSERT INTO `booking_engine`.`csm_kpi_achievements`(`csm_id`,`month`,`year`,`kpi_id`,`base`,`target`,`achievement`)
                VALUES ($csm_id, $month, $year, $kpi_id, NULL, $csm_hotels_with_be,0)
                ON DUPLICATE KEY UPDATE
                target = $csm_hotels_with_be";
            DB::select("$target_sql");

            //Total Room Nights (BE)
            $kpi_id = 5;
            $target_be_room_nights = $total_be_room_nights * 0.04;
            $target_sql = "INSERT INTO `booking_engine`.`csm_kpi_achievements`(`csm_id`,`month`,`year`,`kpi_id`,`base`,`target`,`achievement`)
                VALUES ($csm_id, $month, $year, $kpi_id, NULL, $target_be_room_nights,0)
                ON DUPLICATE KEY UPDATE
                target = $target_be_room_nights";
            DB::select("$target_sql");



            //Average Room Nights (BE)
            $kpi_id = 6;
            $target_sql = "INSERT INTO `booking_engine`.`csm_kpi_achievements`(`csm_id`,`month`,`year`,`kpi_id`,`base`,`target`,`achievement`)
                VALUES ($csm_id, $month, $year, $kpi_id, NULL, round($target_be_room_nights/$csm_hotels_with_be,0),0)
                ON DUPLICATE KEY UPDATE
                target = round($target_be_room_nights/$csm_hotels_with_be,0)";
            DB::select("$target_sql");

            //No. of  OTA/Hotel
            $kpi_id = 8;
            $target_sql = "INSERT INTO `booking_engine`.`csm_kpi_achievements`(`csm_id`,`month`,`year`,`kpi_id`,`base`,`target`,`achievement`)
                VALUES ($csm_id, $month, $year, $kpi_id, NULL, 7,0)
                ON DUPLICATE KEY UPDATE
                target = 7";
            DB::select("$target_sql");


            //Hotels getting booking from OTA
            $kpi_id = 9;
            $csm_hotels_with_cm = 0;
            $total_cm_room_nights = 0;
            foreach($csm_hotel_list[$csm_id] as $hid)
            {
                foreach($cm_hotel_list as $cm_hotel_row)
                {
                    if($cm_hotel_row->hotel_id == $hid)
                    {
                        $csm_hotels_with_cm++;
                        $total_cm_room_nights += $cm_hotel_row->total_rooms * $days_in_month;
                        break;
                    }
                    
                }
            }
            $target_sql = "INSERT INTO `booking_engine`.`csm_kpi_achievements`(`csm_id`,`month`,`year`,`kpi_id`,`base`,`target`,`achievement`)
                VALUES ($csm_id, $month, $year, $kpi_id, NULL, $csm_hotels_with_cm,0)
                ON DUPLICATE KEY UPDATE
                target = $csm_hotels_with_cm";
            DB::select("$target_sql");


            //Total Room Nights (OTA)
            $kpi_id = 10;
            $target_cm_room_nights = $total_cm_room_nights * 0.5;
            $target_sql = "INSERT INTO `booking_engine`.`csm_kpi_achievements`(`csm_id`,`month`,`year`,`kpi_id`,`base`,`target`,`achievement`)
                VALUES ($csm_id, $month, $year, $kpi_id, NULL, $target_cm_room_nights,0)
                ON DUPLICATE KEY UPDATE
                target = $target_cm_room_nights";
            DB::select("$target_sql");


            //Average Room Night (OTA)
            $kpi_id = 11;
            $target_sql = "INSERT INTO `booking_engine`.`csm_kpi_achievements`(`csm_id`,`month`,`year`,`kpi_id`,`base`,`target`,`achievement`)
                VALUES ($csm_id, $month, $year, $kpi_id, NULL, round($target_cm_room_nights/$csm_hotels_with_cm,0),0)
                ON DUPLICATE KEY UPDATE
                target = round($target_cm_room_nights/$csm_hotels_with_cm,0)";
            DB::select("$target_sql");

            //Has Promotions in BE
            $kpi_id = 12;
            $target_sql = "INSERT INTO `booking_engine`.`csm_kpi_achievements`(`csm_id`,`month`,`year`,`kpi_id`,`base`,`target`,`achievement`)
                VALUES ($csm_id, $month, $year, $kpi_id, NULL, $csm_hotels_with_be,0)
                ON DUPLICATE KEY UPDATE
                target = $csm_hotels_with_be";
            DB::select("$target_sql");

            //Has Addon in BE
            $kpi_id = 13;
            $target_sql = "INSERT INTO `booking_engine`.`csm_kpi_achievements`(`csm_id`,`month`,`year`,`kpi_id`,`base`,`target`,`achievement`)
                VALUES ($csm_id, $month, $year, $kpi_id, NULL, $csm_hotels_with_be,0)
                ON DUPLICATE KEY UPDATE
                target = $csm_hotels_with_be";
            DB::select("$target_sql");
            
            //Present in GHC
            $kpi_id = 14;
            $target_sql = "INSERT INTO `booking_engine`.`csm_kpi_achievements`(`csm_id`,`month`,`year`,`kpi_id`,`base`,`target`,`achievement`)
                VALUES ($csm_id, $month, $year, $kpi_id, NULL, $csm_hotels_with_be,0)
                ON DUPLICATE KEY UPDATE
                target = $csm_hotels_with_be";
            DB::select("$target_sql");
            
        }
    }
    //================================================================================================================================
    public function updateHotelStatistics($month, $year)
    {
        //BE room nights
        $be_confirmed_room_nights_sql = "INSERT INTO hotel_statistics (hotel_id, month, year, be_confirmed_room_nights)
            select T2.hotel_id, $month as month, $year as year, T2.room_nights FROM(
            select T.hotel_id,  sum(T.room_nights) AS room_nights from
                (
                    select A.hotel_id, A.hotel_name, A.booking_date, (B.rooms * DATEDIFF(B.check_out, B.check_in)) as room_nights   from
                    booking_engine.invoice_table A
                    inner join booking_engine.hotel_booking B ON A.invoice_id = B.invoice_id
                    where  A.booking_status = 1
                    and month(A.booking_date) = $month
                    AND year(A.booking_date) = $year
                    AND A.booking_source IN ('WEBSITE','GOOGLE')
                    order by hotel_name
                ) T
                group by T.hotel_id)T2
            ON DUPLICATE KEY UPDATE
            be_confirmed_room_nights = T2.room_nights";
        $be_confirmed_room_nights_result = DB::insert("$be_confirmed_room_nights_sql");

        //extranet logins
        $extranet_logins_sql = "INSERT INTO hotel_statistics (hotel_id, month, year, extranet_logins)
            select T2.hotel_id, $month as month, $year as year, T2.extranet_logins FROM (
            select T.hotel_id,count(hotel_id) AS extranet_logins FROM (
            select  distinct B.hotel_id, date(login_date)
            from kernel.login_log A
            inner join kernel.hotels_table B ON A.company_id = B.company_id
            where month(login_date) = $month
            and year(login_date) = $year) T
            group by T.hotel_id)T2
            ON DUPLICATE KEY UPDATE
            extranet_logins = T2.extranet_logins";
        $extranet_logins_result = DB::insert("$extranet_logins_sql");

        //has be promotion
        $be_promotion_sql = "INSERT INTO hotel_statistics (hotel_id, month, year, has_be_promotion)
            select T2.hotel_id, $month as month, $year as year, 1 FROM (
            select distinct hotel_id from coupons
            where is_trash = 0
            and (valid_from between '$year-$month-01' and '$year-$month-31' OR  valid_to between '$year-$month-01' and '$year-$month-31')
            and coupon_for = 1)T2
            ON DUPLICATE KEY UPDATE
            has_be_promotion = 1";
        $be_promotion_result = DB::insert("$be_promotion_sql");

        //has be addon
        $be_addon_sql = "INSERT INTO hotel_statistics (hotel_id, month, year, has_be_addon)
            select T2.hotel_id, $month as month, $year as year, 1 FROM (
            select distinct hotel_id from paid_service
            where is_trash = 0
            and is_enable = 1)T2
            ON DUPLICATE KEY UPDATE
            has_be_addon = 1";
        $be_addon_result = DB::insert("$be_addon_sql");




        //OTA room nights
        $ota_sql = "select T.hotel_id, sum(T.room_nights) as booked_room_nights, 
        sum(amount) as total_amount
        from (
        select B.hotel_id, B.hotel_name, 
        A.rooms_qty * DATEDIFF(A.checkout_at, A.checkin_at) as room_nights,
        A.channel_name, A.amount
        from
        cmlive.cm_ota_booking A
        inner join kernel.hotels_table B ON A.hotel_id = B.hotel_id
        where  A.confirm_status = 1
        and A.cancel_status = 0
        AND month(A.booking_date) = $month
        AND year(A.booking_date) = $year) T
        group by T.hotel_id";
        $ota_bookings = DB::connection('bookingjini_cm')->select("$ota_sql");

        foreach($ota_bookings as $ota_booking)
        {
            $ota_confirmed_room_nights = $ota_booking->booked_room_nights;
            $hotel_id = $ota_booking->hotel_id;
            $ota_confirmed_room_nights_sql = "INSERT INTO hotel_statistics (hotel_id, month, year, ota_confirmed_room_nights)
            VALUES($hotel_id, $month, $year, $ota_confirmed_room_nights)
            ON DUPLICATE KEY UPDATE
            ota_confirmed_room_nights = $ota_confirmed_room_nights";
            $ota_confirmed_room_nights_result = DB::insert("$ota_confirmed_room_nights_sql");
        }

        //google hotel center
        $ghc_sql = "SELECT hotels FROM booking_engine.meta_search_engine_settings WHERE name = 'google-hotel'";
        $ghc_hotels = DB::select("$ghc_sql");
        $str_ghc_hotels = $ghc_hotels[0]->hotels;
        $arr_ghc_hotels = explode(",",$str_ghc_hotels);
       
        $result = HotelStatistics::whereIn('hotel_id',$arr_ghc_hotels)
        ->where('month',$month)
        ->where('year',$year)
        ->update(array('is_in_ghc'=>1));

        

        //Connected OTAs
        $ota_sql = "SELECT A.hotel_id, count(A.ota_name) AS number_of_ota
        FROM cm_ota_details A
        WHERE A.is_active = 1
        GROUP BY A.hotel_id";
        $connected_otas = DB::connection('bookingjini_cm')->select("$ota_sql");

        foreach($connected_otas as $connected_ota)
        {
            $result = HotelStatistics::where('hotel_id',$connected_ota->hotel_id)
            ->where('month',$month)
            ->where('year',$year)
            ->update(array('number_of_ota'=>$connected_ota->number_of_ota));
        }


    }
    //================================================================================================================================
    public function getHotelStatistics(Request $request)
    {
        
        $month = (int)$request['month'];
        $year = (int)$request['year'];
        
        if($request->input('super_admin_id'))
        {
            $super_admin_id = $request->input('super_admin_id');
        }
        else
        {
            $super_admin_id = 1;
        }
            
        // $sts_sql = "SELECT A.hotel_id, B.hotel_name, C.first_name, A.extranet_logins, A.be_confirmed_room_nights, A.number_of_ota,
        //     A.ota_confirmed_room_nights, 
        //     CASE WHEN A.has_be_promotion = 1 THEN 'Yes' ELSE 'No' END AS has_be_promotion, 
        //     CASE WHEN A.has_be_addon = 1 THEN 'Yes' ELSE 'No' END AS has_be_addon, 
        //     CASE WHEN A.is_in_ghc = 1 THEN 'Yes' ELSE 'No' END AS is_in_ghc
        //     FROM booking_engine.hotel_statistics A
        //     INNER JOIN kernel.hotels_table B ON A.hotel_id = B.hotel_id
        //     LEFT JOIN kernel.manager_details C ON A.hotel_id = C.hotel_id AND C.person_type = 23
        //     LEFT JOIN kernel.super_admin D ON C.super_admin_id = D.super_admin_id 
        //     WHERE month = $month
        //     AND YEAR = $year
        //     AND (D.status IS NULL OR D.status = 1)
        //     AND B.is_demo = 0
        //     AND B.status = 1
        //     ORDER BY A.hotel_id ";
        if($super_admin_id == 1 || $super_admin_id == 2)
        {
            $sts_sql = "SELECT C.hotel_id, B.hotel_name, C.first_name, A.extranet_logins, A.be_confirmed_room_nights, A.number_of_ota,
            A.ota_confirmed_room_nights, 
            CASE WHEN A.has_be_promotion = 1 THEN 'Yes' ELSE 'No' END AS has_be_promotion, 
            CASE WHEN A.has_be_addon = 1 THEN 'Yes' ELSE 'No' END AS has_be_addon, 
            CASE WHEN A.is_in_ghc = 1 THEN 'Yes' ELSE 'No' END AS is_in_ghc
            FROM kernel.manager_details C
            LEFT JOIN booking_engine.hotel_statistics A ON C.hotel_id = A.hotel_id AND month = $month AND YEAR = $year
            LEFT JOIN kernel.hotels_table B ON C.hotel_id = B.hotel_id
            LEFT JOIN kernel.super_admin D ON C.super_admin_id = D.super_admin_id 
            WHERE 
            (D.status IS NULL OR D.status = 1)
            AND C.person_type = 23
            AND B.is_demo = 0
            AND B.status = 1
            AND C.status = 1
            ORDER BY C.hotel_id";
        }
        else
        {
            $sts_sql = "SELECT C.hotel_id, B.hotel_name, C.first_name, A.extranet_logins, A.be_confirmed_room_nights, A.number_of_ota,
            A.ota_confirmed_room_nights, 
            CASE WHEN A.has_be_promotion = 1 THEN 'Yes' ELSE 'No' END AS has_be_promotion, 
            CASE WHEN A.has_be_addon = 1 THEN 'Yes' ELSE 'No' END AS has_be_addon, 
            CASE WHEN A.is_in_ghc = 1 THEN 'Yes' ELSE 'No' END AS is_in_ghc
            FROM kernel.manager_details C
            LEFT JOIN booking_engine.hotel_statistics A ON C.hotel_id = A.hotel_id AND month = $month AND YEAR = $year
            LEFT JOIN kernel.hotels_table B ON C.hotel_id = B.hotel_id
            LEFT JOIN kernel.super_admin D ON C.super_admin_id = D.super_admin_id 
            WHERE 
            (D.status IS NULL OR D.status = 1)
            AND C.person_type = 23
            AND B.is_demo = 0
            AND B.status = 1
            AND C.super_admin_id = $super_admin_id
            AND C.status = 1
            ORDER BY C.hotel_id";
        }
        
        $all_hotels = DB::select("$sts_sql");

        $format = [
            ["name" => "Hotel ID", "field" => "hotel_id", "options" => ["filter" => false, "ordersort" => true, "headerNoWrap"=>true]], 
            ["name" => "Hotel Name", "field" => "hotel_name", "options" => ["filter" => false, "sort" => true, "headerNoWrap"=>true]],
            ["name" => "CSM", "field" => "first_name", "options" => ["filter" => true, "sort" => true, "headerNoWrap"=>true]],
            ["name" => "Logins in Extranet", "field" => "extranet_logins", "options" => ["filter" => true, "sort" => true, "headerNoWrap"=>true]], 
            ["name" => "Confirmed Room Nights (BE)", "field" => "be_confirmed_room_nights", "options" => ["filter" => false, "sort" => true, "headerNoWrap"=>true]], 
            ["name" => "Connected OTA", "field" => "number_of_ota", "options" => ["filter" => true, "sort" => true, "headerNoWrap"=>true]], 
            ["name" => "Confirmed Room Nights (OTA)", "field" => "ota_confirmed_room_nights", "options" => ["filter" => false, "sort" => true, "headerNoWrap"=>true]], 
            ["name" => "Has Promotions in BE", "field" => "has_be_promotion", "options" => ["filter" => true, "sort" => true, "headerNoWrap"=>true]], 
            ["name" => "Has Addon in BE", "field" => "has_be_addon", "options" => ["filter" => true, "sort" => true, "headerNoWrap"=>true]], 
            ["name" => "Present in GHC", "field" => "is_in_ghc", "options" => ["filter" => true, "sort" => true, "headerNoWrap"=>true]]
            
        ];
        if($all_hotels != [])
        {
            $msg = array('status' => 1,'message'=>'All Hotels','data' => $all_hotels, 'format' => $format);
            return response()->json($msg);
        }
        else
        {
            $msg = array('status' => 0,'message'=>'No Hotels Found');
            return response()->json($msg);
        } 

    }
    //================================================================================================================================
    public function updateCSMKPIAchievements($month, $year)
    {
        
        // $month = (int)$request['month'];
        // $year = (int)$request['year'];
        
        $sts_sql = "SELECT T.super_admin_id, T.first_name, 
        COALESCE(count(extranet_logins),0) as cnt_extranet_logins, 
        COALESCE(avg(extranet_logins),0) as avg_extranet_logins,
        COALESCE(count(be_confirmed_room_nights),0) as cnt_direct_booking, 
        COALESCE(sum(be_confirmed_room_nights),0) as total_be_room_nights,
        COALESCE(avg(be_confirmed_room_nights),0) as avg_be_room_nights,
        COALESCE(count(number_of_ota),0) as hotels_with_ota, 
        COALESCE(avg(number_of_ota),0) as avg_ota_per_hotel,
        COALESCE(count(ota_confirmed_room_nights),0) as cnt_hotels_with_ota_bookings, 
        COALESCE(sum(ota_confirmed_room_nights),0) as total_ota_room_nights, 
        COALESCE(avg(ota_confirmed_room_nights),0) as avg_ota_room_nights, 
        COALESCE(count(has_be_promotion),0) as cnt_hotel_be_promotion,
        COALESCE(count(has_be_addon),0) as cnt_hotel_be_addon,
        COALESCE(count(is_in_ghc),0) as cnt_hotel_in_ghc
        FROM
        (SELECT C.hotel_id, D.super_admin_id, C.first_name, A.extranet_logins, A.be_confirmed_room_nights, A.number_of_ota,
        A.ota_confirmed_room_nights, has_be_promotion,  has_be_addon, is_in_ghc
        FROM kernel.manager_details C
        LEFT JOIN booking_engine.hotel_statistics A ON C.hotel_id = A.hotel_id AND month = $month AND YEAR = $year
        LEFT JOIN kernel.hotels_table B ON C.hotel_id = B.hotel_id
        LEFT JOIN kernel.super_admin D ON C.super_admin_id = D.super_admin_id 
        WHERE 
        (D.status IS NULL OR D.status = 1)
        AND C.person_type = 23
        AND B.is_demo = 0
        AND B.status = 1
        ORDER BY C.hotel_id) T
        GROUP BY T.super_admin_id, T.first_name";
        $all_achievements = DB::select("$sts_sql");

        foreach($all_achievements as $achievement)
        {
            $csm_id = $achievement->super_admin_id;

            //No. of Hotels Logins in Extranet
            $update_sql = "UPDATE booking_engine.csm_kpi_achievements SET
            achievement = $achievement->cnt_extranet_logins
            WHERE month = $month
            AND year = $year
            AND csm_id = $csm_id
            AND kpi_id = 1";
            $result = DB::update("$update_sql");

            //Login Extranet Average Days/Month
            $update_sql = "UPDATE booking_engine.csm_kpi_achievements SET
            achievement = $achievement->avg_extranet_logins
            WHERE month = $month
            AND year = $year
            AND csm_id = $csm_id
            AND kpi_id = 2";
            $result = DB::update("$update_sql");

            //No. of hotels getting Direct Bookings (BE)
            $update_sql = "UPDATE booking_engine.csm_kpi_achievements SET
            achievement = $achievement->cnt_direct_booking
            WHERE month = $month
            AND year = $year
            AND csm_id = $csm_id
            AND kpi_id = 3";
            $result = DB::update("$update_sql");

            //Total Room Nights (BE)
            $update_sql = "UPDATE booking_engine.csm_kpi_achievements SET
            achievement = $achievement->total_be_room_nights
            WHERE month = $month
            AND year = $year
            AND csm_id = $csm_id
            AND kpi_id = 5";
            $result = DB::update("$update_sql");

            //Average Room Nights (BE)
            $update_sql = "UPDATE booking_engine.csm_kpi_achievements SET
            achievement = $achievement->avg_be_room_nights
            WHERE month = $month
            AND year = $year
            AND csm_id = $csm_id
            AND kpi_id = 6";
            $result = DB::update("$update_sql");

            //No. of hotels with OTA
            $update_sql = "UPDATE booking_engine.csm_kpi_achievements SET
            achievement = $achievement->hotels_with_ota
            WHERE month = $month
            AND year = $year
            AND csm_id = $csm_id
            AND kpi_id = 7";
            $result = DB::update("$update_sql");

            //No. of  OTA/Hotel
            $update_sql = "UPDATE booking_engine.csm_kpi_achievements SET
            achievement = $achievement->avg_ota_per_hotel
            WHERE month = $month
            AND year = $year
            AND csm_id = $csm_id
            AND kpi_id = 8";
            $result = DB::update("$update_sql");

            //Hotels getting booking from OTA
            $update_sql = "UPDATE booking_engine.csm_kpi_achievements SET
            achievement = $achievement->cnt_hotels_with_ota_bookings
            WHERE month = $month
            AND year = $year
            AND csm_id = $csm_id
            AND kpi_id = 9";
            $result = DB::update("$update_sql");

            //Total Room Nights (OTA)
            $update_sql = "UPDATE booking_engine.csm_kpi_achievements SET
            achievement = $achievement->total_ota_room_nights
            WHERE month = $month
            AND year = $year
            AND csm_id = $csm_id
            AND kpi_id = 10";
            $result = DB::update("$update_sql");

            //Average Room Night (OTA)
            $update_sql = "UPDATE booking_engine.csm_kpi_achievements SET
            achievement = $achievement->avg_ota_room_nights
            WHERE month = $month
            AND year = $year
            AND csm_id = $csm_id
            AND kpi_id = 11";
            $result = DB::update("$update_sql");

            //Has Promotions in BE
            $update_sql = "UPDATE booking_engine.csm_kpi_achievements SET
            achievement = $achievement->cnt_hotel_be_promotion
            WHERE month = $month
            AND year = $year
            AND csm_id = $csm_id
            AND kpi_id = 12";
            $result = DB::update("$update_sql");

            //Has Addon in BE
            $update_sql = "UPDATE booking_engine.csm_kpi_achievements SET
            achievement = $achievement->cnt_hotel_be_addon
            WHERE month = $month
            AND year = $year
            AND csm_id = $csm_id
            AND kpi_id = 13";
            $result = DB::update("$update_sql");

            //Present in GHC
            $update_sql = "UPDATE booking_engine.csm_kpi_achievements SET
            achievement = $achievement->cnt_hotel_in_ghc
            WHERE month = $month
            AND year = $year
            AND csm_id = $csm_id
            AND kpi_id = 14";
            $result = DB::update("$update_sql");


        }




    }
    //================================================================================================================================
    public function getCSMKPIAchievements($month, $year, $super_admin_id)
    {
        
        $sa_sql = "SELECT name, username, role_id
            FROM 
            kernel.super_admin 
            WHERE super_admin_id = $super_admin_id
            AND status = 1";
        $super_admin_details = DB::select("$sa_sql");
        if($super_admin_id != 2)
        {
            if($super_admin_id != 1)
            {
                if(!isset($super_admin_details[0]) || $super_admin_details[0]->role_id != 23)
                {
                    $msg = array('status' => 0,'message'=>'No Data Found');
                    return response()->json($msg);
                }
            }
            
        }
        
        $sts_sql = "SELECT A.csm_id, C.name, A.kpi_id, B.kpi_text, A.base, A.target, A.achievement 
        FROM 
        booking_engine.csm_kpi_achievements A
        INNER JOIN booking_engine.csm_kpi B ON A.kpi_id = B.kpi_id
        INNER JOIN kernel.super_admin C ON A.csm_id = C.super_admin_id
        WHERE A.month = $month
        AND A.year = $year";
        $all_achievements = DB::select("$sts_sql");
        $all_kpis = [];
        $all_csms = [];
        $arr_kpi_id = [];
        $arr_csm_id = [];

        $arr_bases = [];
        $arr_targets = [];
        $arr_achievements = [];

        foreach($all_achievements as $achievements)
        {
            if(!in_array($achievements->kpi_id,$arr_kpi_id))
            {
                $arr_kpi_id[] = $achievements->kpi_id;
                $kpi_record['kpi_id'] = $achievements->kpi_id;
                $kpi_record['kpi_text'] = $achievements->kpi_text;
                $all_kpis[] = $kpi_record;
            }
            if(!in_array($achievements->csm_id,$arr_csm_id))
            {
                $arr_csm_id[] = $achievements->csm_id;
                $csm_record['csm_id'] = $achievements->csm_id;
                $csm_record['csm_name'] = $achievements->name;
                $all_csms[] = $csm_record;
            }
            $arr_bases[$achievements->kpi_id][$achievements->csm_id] = $achievements->base;
            $arr_targets[$achievements->kpi_id][$achievements->csm_id] = $achievements->target;
            $arr_achievements[$achievements->kpi_id][$achievements->csm_id] = $achievements->achievement;
        }

        //$all_kpis = DB::select("SELECT * FROM booking_engine.csm_kpi");

        $html = '<table border="1">';
        $html .= '<tr>';
        $html .= '<th rowspan="2">KPI</th>';
        foreach($all_csms as $csm)
        {
            $html .= '<th colspan="3">'.$csm['csm_name'].'</th>';
        }
        $html .= '</tr>';
        $html .= '<tr>';
        foreach($all_csms as $csm)
        {
            $html .= '<th>Target</th>';
            $html .= '<th>Achievement</th>';
            $html .= '<th>% Achieved</th>';
        }
        $html .= '</tr>';

        foreach($all_kpis as $kpi)
        {
            $colour = '#fff';
            $html .= '<tr>';
            $html .= '<td>'.$kpi['kpi_text'].'</td>';
            foreach($all_csms as $csm)
            {
                $html .= '<td>'.$arr_targets[$kpi['kpi_id']][$csm['csm_id']].'</td>';
                $html .= '<td>'.$arr_achievements[$kpi['kpi_id']][$csm['csm_id']].'</td>';
                if($arr_targets[$kpi['kpi_id']][$csm['csm_id']] != 0)
                    $achievement_percentage = round($arr_achievements[$kpi['kpi_id']][$csm['csm_id']]/$arr_targets[$kpi['kpi_id']][$csm['csm_id']]*100,2);
                else
                    $achievement_percentage = 0;
                
                if($achievement_percentage > 0 && $achievement_percentage <=50)
                    $colour = "#fff";
                else if($achievement_percentage > 50 && $achievement_percentage <90)
                    $colour = "#a3d9c5";
                else if($achievement_percentage >90)
                    $colour = "#1ea673";
                else if($achievement_percentage == 100)
                    $colour = "#2ba14b";
                $html .= '<td bgColor="'.$colour.'">'.$achievement_percentage.'</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</table>';

        if($super_admin_id == 1)
        {
            echo $html;
        }
        else
        {
            $msg = array('status' => 1,'message'=>'Data Found','data'=>$html);
            return response()->json($msg);
        }
        


    }
    //================================================================================================================================
    public function getFilteredHotelList5(Request $request )
    {
        $destination_image = '';
        $destination_description = '';
        $group_id = $request['group_id'];
        $city_name = $request['city_name'];
        $brands = $request['brands'];
        
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
        if($brands != '')
        {
            $arr_brand = explode(",",$brands);
            $group_hotels = $group_hotels->WhereIn('kernel.hotels_table.hotel_category', $arr_brand);
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
            $destination_city_image = isset($group_destination->city_image) ? $group_destination->city_image : '';
            $destination_image = env('S3_ROOT_PATH')."group-website/$group_id/destination-photos/".$destination_city_image;
            $destination_city_description = isset($group_destination->city_description) ? $group_destination->city_description : '';
            $destination_description = $destination_city_description;
        }
        $hotels_data = array();
        $arr_hotel_ids = [];
        if(isset($group_hotels) && count($group_hotels) > 0)
        {
            foreach($group_hotels as $hotel_info)
            {
                $hotel_record['hotel_id'] = $hotel_info->hotel_id;
                $arr_hotel_ids[] = $hotel_info->hotel_id;
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
            $str_hotel_ids = implode(',',$arr_hotel_ids);
            $today = date('Y-m-d');
            $curl = curl_init();

            curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://be.bookingjini.com/extranetv4/min-hotel-price',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS =>'{
                "hotel_id":['.$str_hotel_ids.'],
                "from_date":"'.$today.'"
            }',
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
            ));

            $response = curl_exec($curl);

            curl_close($curl);
            
            $response = json_decode($response, true);

            $rates_data = $response['data'];
            $hotel_min_price = [];

            foreach($rates_data as $rate_data)
            {
                $hotel_min_price[$rate_data['hotel_id']] = $rate_data['minimum_rates'];
            }

            $idx = 0;
            foreach($hotels_data as $hotel_data)
            {
                $hotels_data[$idx]['starting_price'] = $hotel_min_price[$hotel_data['hotel_id']];
                $idx++;
            }
        }
        else
        {
            $hotels_data = [];
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
    public function getFilteredHotelList6(Request $request )
    {
        $destination_image = '';
        $destination_description = '';
        $group_id = $request['group_id'];
        $area_name = $request['area_name'];
        $brands = $request['brands'];
        // $checkin_date = $request['checkin_date'];
        // $checkout_date = $request['checkout_date'];
        
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
        if($area_name != '')
        {
            $group_hotels = $group_hotels->Where('kernel.hotels_table.hotel_address', 'like', "%$area_name%");
        }
        if($brands != '')
        {
            $arr_brand = explode(",",$brands);
            $group_hotels = $group_hotels->WhereIn('kernel.hotels_table.hotel_category', $arr_brand);
        }
        
        $group_hotels = $group_hotels->get();

        
        $hotels_data = array();
        $arr_hotel_ids = [];
        if(isset($group_hotels) && count($group_hotels) > 0)
        {
            foreach($group_hotels as $hotel_info)
            {
                $hotel_record['hotel_id'] = $hotel_info->hotel_id;
                $arr_hotel_ids[] = $hotel_info->hotel_id;
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
            $str_hotel_ids = implode(',',$arr_hotel_ids);
            $today = date('Y-m-d');
            $curl = curl_init();

            curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://be.bookingjini.com/extranetv4/min-hotel-price',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS =>'{
                "hotel_id":['.$str_hotel_ids.'],
                "from_date":"'.$today.'"
            }',
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
            ));

            $response = curl_exec($curl);

            curl_close($curl);
            
            $response = json_decode($response, true);

            $rates_data = $response['data'];
            $hotel_min_price = [];

            foreach($rates_data as $rate_data)
            {
                $hotel_min_price[$rate_data['hotel_id']] = $rate_data['minimum_rates'];
            }

            $idx = 0;
            foreach($hotels_data as $hotel_data)
            {
                $hotels_data[$idx]['starting_price'] = $hotel_min_price[$hotel_data['hotel_id']];
                $idx++;
            }
        }
        else
        {
            $hotels_data = [];
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
    
    public function getPopularCities(Request $request)
    {
        $popular_cities = array();
        $city_sql = "SELECT city_id, city_name 
            FROM 
            kernel.city_table 
            WHERE city_name in ('puri','kolkata','bangalore','mumbai','hyderabad')
            ORDER BY city_name";
        $all_popular_city = DB::select("$city_sql");
        
        foreach($all_popular_city as $popular_city)
        {
            $city['city_id'] = $popular_city->city_id;
            $city['city_name'] = $popular_city->city_name;
            $city['city_image'] = env('S3_ROOT_PATH')."group-website/bookingjini/destination-photos/".strtolower($popular_city->city_name).'.png';
            $popular_cities[] = $city;
        }
        
        
        $msg = array('status' => 1,'message'=>'data found','cities'=>$popular_cities);
        return response()->json($msg);
    }
    //================================================================================================
    public function getTopDestinations(Request $request)
    {
        $popular_cities = array();
        $city_sql = "SELECT city_table.city_id, city_name, count(hotel_id) as hotels_present
            FROM 
            kernel.city_table 
            INNER JOIN kernel.hotels_table ON city_table.city_id = hotels_table.city_id
            WHERE hotels_table.status = 1
            GROUP BY city_table.city_id, city_name
            ORDER BY count(hotel_id) DESC
            LIMIT 10";
        $all_popular_city = DB::select("$city_sql");



        
        foreach($all_popular_city as $popular_city)
        {
            $city['city_id'] = $popular_city->city_id;
            $city['city_name'] = $popular_city->city_name;
            $city['city_image'] = env('S3_ROOT_PATH')."group-website/bookingjini/city_images/".$popular_city->city_id.'.png';
            $popular_cities[] = $city;
        }
        
        
        $msg = array('status' => 1,'message'=>'data found','destinations'=>$popular_cities);
        return response()->json($msg);
    }
}
