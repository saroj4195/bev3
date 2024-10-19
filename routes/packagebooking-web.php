<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/
$router->get('/package_booking/auth/{company_url}',['uses'=>'PackageBookingController@getAccess']);
//package booking details
$router->group(['prefix'=>'package_booking','middleware' => 'jwt.auth'],function($router){
     $router->post('/bookings/{api_key}',['uses'=>'PackageBookingController@packageBookings']);
     $router->get('/invoice-details/{invoice_id}',['uses'=>'PackageBookingController@invoiceDetails']);
 });
 $router->post('/package_booking-new/{api_key}',['uses'=>'PackageBookingController@packageBookings']);
$router->get('/package_booking/get_package_details/{hotel_id}/{from_date}/{currency_name}',['uses'=>'PackageBookingController@getSpecificPackageDetails']);
$router->get('/package_booking/get_package_details-new/{hotel_id}/{from_date}/{currency_name}',['uses'=>'PackageBookingController@getSpecificPackageDetailsNew']);

$router->get('/package_booking/retrive_details/{hotel_id}/{currency_name}',['uses'=>'PackageBookingController@packageBookingDetails']);
$router->get('/package_booking/package_details/{package_id}',['uses'=>'PackageBookingController@getPackageDetails']);
$router->get('/package_booking/basecurrency/{company_id}',['uses'=>'CurrencyController@getCurrencyName']);
$router->get('/package_booking/currency_details/{currency_name}/{base_currency_name}',['uses'=>'CurrencyController@getCurrencyDetails']);
$router->get('/package_booking/get_package_details_by_package_id/{hotel_id}/{package_id}/{from_date}/{currency_name}',['uses'=>'PackageBookingController@getPackageDetailsByPackageID']);



///Package Bookings to Get inventory

$router->get('get_inventory/{room_type_id}/{date_from}/{date_to}/{mindays}',['uses'=>'ManageInventoryController@getInventery']);

$router->get('/generate_password/{password}',['uses'=>'CompanyRegistrationController@generatePassword']);


//Added for changes in package
$router->get('/package_booking/get_package_details_test/{hotel_id}/{from_date}/{currency_name}',['uses'=>'PackageBookingControllerTest@getSpecificPackageDetails']);


$router->get('/package/package_details/{id}/{from}/{to}',['uses'=>'PackageBookingController@getPackages']);
