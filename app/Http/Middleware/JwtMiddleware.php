<?php

namespace App\Http\Middleware;

use Closure;
use Exception;
use App\UserCredential;
use App\AdminUser;
use App\User;
use App\SuperAdmin;
use App\Reseller;
use Firebase\JWT\JWT;
use Firebase\JWT\ExpiredException;
use DB;
use Illuminate\Http\Request;
use Firebase\JWT\Key;

class JwtMiddleware
{
        /**
         * The header name.
         *
         * @var string
         */
        protected $header = 'authorization';
        /**
         * The header prefix.
         *
         * @var string
         */
        protected $prefix = 'bearer';
        /**
         * Custom parameters.
         *
         * @var \Symfony\Component\HttpFoundation\ParameterBag
         *
         * @api
         */
        public $attributes;
        protected function fromAltHeaders(Request $request)
        {
                return $request->server->get('HTTP_AUTHORIZATION') ?: $request->server->get('REDIRECT_HTTP_AUTHORIZATION');
        }
        public function handle($request, Closure $next, $guard = null)
        {
                $header = $request->headers->get($this->header) ?: $this->fromAltHeaders($request);
                if ($header && preg_match('/' . $this->prefix . '\s*(.*)\b/i', $header, $matches)) {
                        $token = $matches[1];
                } else {
                        $token = false;
                }
                if (!$token) {
                        // Unauthorized response if token not there
                        return response()->json([
                                'error' => 'Token not provided.'
                        ], 401);
                }
                try {
                        $key = env('JWT_SECRET');
                        $credentials = JWT::decode($token, new Key($key, 'HS256'));

                } catch (ExpiredException $e) {
                        return response()->json([
                                'error' => 'Provided token is expired.'
                        ], 401);
                } catch (Exception $e) {
                        return response()->json([
                                'error' => 'An error while decoding token.'
                        ], 401);
                }

             
                $user = DB::connection('bookingjini_kernel')->table('user_table_new')->where('user_id', $credentials->user_id)->first();
                // Now let's put the user in the request class so that you can grab it from there
                $request->auth = $user;
                $request->attributes->add(['scope' => $credentials->scope]);
                return $next($request);
        }
}
