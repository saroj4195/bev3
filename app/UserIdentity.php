<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use DB;
class UserIdentity extends Model
{
    protected $connection = 'bookingjini_kernel';
    protected $table = 'user_identity_table';
    protected $primaryKey = "identity";
    protected $fillable = array('identity','identity_no','user_id','expiry_date','date_of_birth');
}