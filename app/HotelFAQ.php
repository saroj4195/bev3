<?php
namespace App;
use Illuminate\Auth\Authenticatable;
use Laravel\Lumen\Auth\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Exception;
use DB;
class HotelFAQ extends Model
{
  protected $connection = 'bookingjini_kernel';
  protected $table = 'hotel_faqs';
  protected $primaryKey = "id";

}
