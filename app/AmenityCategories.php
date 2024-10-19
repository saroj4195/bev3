<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Exception;
use DB;
class AmenityCategories extends Model
{
    protected $connection = 'bookingjini_kernel';
    protected $table = 'amenity_categories';
    protected $primaryKey = "category_id";
	protected $fillable = array('subcategory_id','category_icon','catgegories');
}
