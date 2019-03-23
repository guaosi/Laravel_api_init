<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\UserRequest;
use App\Http\Resources\Api\AdminResource;
use App\Jobs\Api\SaveLastTokenJob;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;

class AdminController extends Controller
{

    //返回用户列表
    public function index()
    {
        //3个用户为一页
        $admins = Admin::paginate(3);
        return AdminResource::collection($admins);
    }

    //返回单一用户信息
    public function show(Admin $admin)
    {
        return $this->success(new AdminResource($admin));
    }

    //返回当前登录用户信息
    public function info()
    {
        $admins = Auth::user();
        return $this->success(new AdminResource($admins));
    }

    //用户注册
    public function store(UserRequest $request)
    {
        Admin::create($request->all());
        return $this->setStatusCode(201)->success('用户注册成功');
    }

    //用户登录
    public function login(Request $request)
    {
        //获取当前守护的名称
        $present_guard = Auth::getDefaultDriver();
        $token = Auth::claims(['guard'=>$present_guard])->attempt(['name' => $request->name, 'password' => $request->password]);
        if ($token) {
            //如果登陆，先检查原先是否有存token，有的话先失效，然后再存入最新的token
            $user = Auth::user();
            if ($user->last_token) {
                try{
                    Auth::setToken($user->last_token)->invalidate();
                }catch (TokenExpiredException $e){
                    //因为让一个过期的token再失效，会抛出异常，所以我们捕捉异常，不需要做任何处理
                }
            }
            SaveLastTokenJob::dispatch($user,$token);
            return $this->setStatusCode(201)->success(['token' => 'bearer ' . $token]);
        }
        return $this->failed('账号或密码错误', 400);
    }

    //用户退出
    public function logout()
    {
        Auth::logout();
        return $this->success('退出成功...');
    }
}
