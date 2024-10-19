<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
class ImageTable extends Model 
{
    protected $connection = 'bookingjini_kernel';
    protected $table = 'image_table';
    protected $primaryKey = "image_id";
     /**
* @auther: Shankar Bag
     * The attributes that are mass assignable.
*
     * @var array
*/
    // Data Feeling
protected $fillable = array('hotel_id','image_name');
    
//Get the Image Path having  UUID
//@auther : Shankar Bag
//@Story : this function will return the path of image having UUID coming as parameter.
public function getName($id)
{
$sp_conditions=array("image_id"=>$id);
$hotel_user_data = ImageTable::where($sp_conditions)->first(['image_name']);
if($hotel_user_data)
{
return $hotel_user_data['image_name'];
}
else
{
return "NOT FOUND";
}
}
}