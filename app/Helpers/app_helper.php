<?php

if (!function_exists('config_path')) {
     /**
      * Get the configuration path.
      *
      * @param  string $path
      * @return string
      */
     function config_path($path = '')
     {
          return rtrim(app()->basePath() . '/config' . ($path ? '/' . $path : $path));
     }
     if (!function_exists('public_path')) {
          /**
           * Return the path to public dir
           * @param null $path
           * @return string
           */
          function public_path($path = null)
          {
               return rtrim(app()->basePath('public/' . $path), '/');
          }
     }
     if (!function_exists('storage_path')) {
          /**
           * Return the path to storage dir
           * @param null $path
           * @return string
           */
          function storage_path($path = null)
          {
               return app()->storagePath($path);
          }
     }
     if (!function_exists('database_path')) {
          /**
           * Return the path to database dir
           * @param null $path
           * @return string
           */
          function database_path($path = null)
          {
               return app()->databasePath($path);
          }
     }
     if (!function_exists('captureHotelActivityLog')) {

          function captureHotelActivityLog($hotel_id, $user_id, $activity_id, $activity_name = '', $activity_description, $activity_from)
          {
               try {
                    $activity_list = array(
                         'a1' =>  'ACTIVATE-PAY-AT-HOTEL',
                         'a2' =>  'ACTIVATE-PARTIAL-PAYMENT',
                         'a3' =>  'DEACTIVATE-PAY-AT-HOTEL',
                         'a4' =>  'DEACTIVATE-PARTIAL-PAYMENT',
                         'a5' =>  'NEW-PUBLIC-COUPON',
                         'a6' =>  'MODIFY-PUBLIC-COUPON',
                         'a7' =>  'DELETE-PUBLIC-COUPON',
                         'a8' =>  'NEW-PRIVATE-COUPON',
                         'a9' =>  'MODIFY-PUBLIC-COUPON',
                         'a10' => 'DELETE-PUBLIC-COUPON',
                         'a11' => 'NEW-WEBSITE-RESERVATION',
                         'a12' => 'NEW-PMS-RESERVATION',
                         'a13' => 'NEW-GOOGLE-RESERVATION',
                         'a14' => 'NEW-CRS-RESERVATION',
                         'a15' => 'NEW-QUICKPAYMENTLINK-RESERVATION',
                         'a16' => 'CANCEL-WEBSITE-RESERVATION',
                         'a17' => 'CANCEL-PMS-RESERVATION',
                         'a18' => 'CANCEL-GOOGLE-RESERVATION',
                         'a19' => 'CANCEL-CRS-RESERVATION',
                         'a20' => 'CANCEL-QUICKPAYMENTLINK-RESERVATION',
                         'a21' => 'GHC-COUPON-NEW',
                         'a22' => 'GHC-COUPON-UPDATE',
                         'a23' => 'GHC-COUPON-DELETE',
                         'a24' => 'MODIFY-CRS-RESERVATION',
                         'a25' => 'NEW-ADDON-SERVICE',
                         
                    );

                    $data['hotel_id'] = $hotel_id;
                    $data['user_id'] = $user_id;
                    $data['activity_id'] = $activity_list[$activity_id];
                    $data['activity_name'] = $activity_name;
                    $data['activity_description'] = $activity_description;
                    $data['activity_from'] = $activity_from;

                    $apikey = 'Vz9P1vfwj6P6hiTCc9ddC5bNieqA5ScT';

                    $header =  array(
                         'Content-Type: application/json',
                         'X_Auth_API-KEY-Subscription: ' . $apikey
                    );


                    $curl = curl_init();
                    curl_setopt_array($curl, array(
                         CURLOPT_URL => 'https://subscription.bookingjini.com/api/add-log',
                         CURLOPT_RETURNTRANSFER => false,
                         CURLOPT_TIMEOUT => 0,
                         CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                         CURLOPT_CUSTOMREQUEST => 'POST',
                         CURLOPT_POSTFIELDS => json_encode($data),
                         CURLOPT_HTTPHEADER => $header,
                    ));
                    $response = curl_exec($curl);
                    curl_close($curl);
               } catch (Exception $e) {
               }
               return;
          }
     }
}
