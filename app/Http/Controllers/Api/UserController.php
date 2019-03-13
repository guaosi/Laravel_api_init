<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\UserRequest;
use App\Http\Resources\Api\UserResource;
use App\Jobs\Api\SaveLastTokenJob;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;

class UserController extends Controller
{

    //返回用户列表
    public function index(){
        //3个用户为一页
        $users = User::paginate(3);
        return UserResource::collection($users);
    }
    //返回单一用户信息
    public function show(User $user){
        return $this->success(new UserResource($user));
    }
    //返回当前登录用户信息
    public function info(){
        $user = Auth::user();
        return $this->success(new UserResource($user));
    }
    //用户注册
    public function store(UserRequest $request){
        $user = User::create($request->all());
        if($user){
            return $this->setStatusCode(201)->success('用户注册成功');
        }
        return $this->failed('用户注册失败');

    }
    //用户登录
    public function login(Request $request){
        $token=Auth::claims(['guard'=>'api'])->attempt(['name'=>$request->name,'password'=>$request->password]);
        if($token) {
            //如果登陆，先检查原先是否有存token，有的话先失效，然后再存入最新的token
            $user = Auth::user();
            if($user->last_token){
                try{
                    Auth::setToken($user->last_token)->invalidate();
                }catch (TokenExpiredException $e){
                    //因为让一个过期的token再失效，会抛出异常，所以我们捕捉异常，不需要做任何处理
                }
            }
            SaveLastTokenJob::dispatch($user,$token);

            return $this->setStatusCode(201)->success(['token' => 'bearer ' . $token]);
        }
        return $this->failed('账号或密码错误',400);
    }
    //用户退出
    public function logout(){
        Auth::logout();
        return $this->success('退出成功...');
    }
    public function test(Request $request){
        $token = Auth::guard('api')->getToken();
        $token = Auth::guard('admin')->setToken($token)->refresh();
        dd(Auth::guard('admin')->setToken($token)->check());

        return $token;
        $token = Auth::guard('api')->getToken();
        Auth::guard('api')->setToken($token)->invalidate();
    }
}
