<?php

namespace App\Http\Controllers\V1;

use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\BaseController;

class AuthController extends BaseController
{
    protected $guard = 'api';//设置使用guard为api选项验证，请查看config/auth.php的guards设置项，重要！

    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('refresh', ['except' => ['login','register']]);
    }

    public function test(){
        echo "test!!";
    }

    public function register(Request $request)
    {
        $payload = $request->only('name', 'email', 'password');

        // 创建用户
        $result = User::create([
            'name' => $payload['name'],
            'email' => $payload['email'],
            'password' => bcrypt($payload['password']),
        ]);

        if ($result) {
            return $this->response->array(['success' => '创建用户成功']);
        } else {
            return $this->response->array(['error' => '创建用户失败']);
        }

    }

    /**
     * Get a JWT token via given credentials.
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        if ($token = $this->guard()->attempt($credentials)) {
            return $this->respondWithToken($token);
        }
        return $this->response->errorUnauthorized('登录失败');
    }

    /**
     * Get the authenticated User
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function me()
    {
        return $this->response->array($this->guard()->user());
    }

    /**
     * Log the user out (Invalidate the token)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        $this->guard()->logout();

        //return response()->json(['message' => 'Successfully logged out']);
        return $this->response->array(['message' => '退出成功']);
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        return $this->respondWithToken($this->guard()->refresh());
    }

    /**
     * Get the token array structure.
     *
     * @param  string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => $this->guard()->factory()->getTTL() * 60
        ]);
    }

    /**
     * Get the guard to be used during authentication.
     *
     * @return \Illuminate\Contracts\Auth\Guard
     */
    public function guard()
    {
        return Auth::guard($this->guard);
    }
}
