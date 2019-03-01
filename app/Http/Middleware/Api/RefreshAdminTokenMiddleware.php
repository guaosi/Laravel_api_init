<?php

namespace App\Http\Middleware\Api;

use App\Jobs\Api\SaveLastTokenJob;
use Auth;
use Closure;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Http\Middleware\BaseMiddleware;

// 注意，我们要继承的是 jwt 的 BaseMiddleware
class RefreshAdminTokenMiddleware extends BaseMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     *
     * @throws \Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException
     *
     * @return mixed
     * @throws TokenInvalidException
     */
    public function handle($request, Closure $next)
    {
        // 检查此次请求中是否带有 token，如果没有则抛出异常。
        $this->checkForToken($request);

        //1. 格式通过，验证是否是专属于这个的token

        //获取当前守护的名称
        $present_guard = Auth::getDefaultDriver();

        //获取当前token
        $token=Auth::getToken();

        //即使过期了，也能获取到token里的 载荷 信息。
        $payload = Auth::manager()->getJWTProvider()->decode($token->get());

        //如果不包含guard字段或者guard所对应的值与当前的guard守护值不相同
        //证明是不属于当前guard守护的token
        if(empty($payload['guard'])||$payload['guard']!=$present_guard){
            throw new TokenInvalidException();
        }
        //使用 try 包裹，以捕捉 token 过期所抛出的 TokenExpiredException  异常
        //2. 此时进入的都是属于当前guard守护的token
        try {
            // 检测用户的登录状态，如果正常则通过
            if ($this->auth->parseToken()->authenticate()) {
                return $next($request);
            }
            throw new UnauthorizedHttpException('jwt-auth', '未登录');
        } catch (TokenExpiredException $exception) {
            // 3. 此处捕获到了 token 过期所抛出的 TokenExpiredException 异常，我们在这里需要做的是刷新该用户的 token 并将它添加到响应头中
            try {
                // 刷新用户的 token
                $token = $this->auth->refresh();
                // 使用一次性登录以保证此次请求的成功
                Auth::onceUsingId($this->auth->manager()->getPayloadFactory()->buildClaimsCollection()->toPlainArray()['sub']);
                //刷新了token，将token存入数据库
                $user = Auth::user();
                SaveLastTokenJob::dispatch($user,$token);
            } catch (JWTException $exception) {
                // 如果捕获到此异常，即代表 refresh 也过期了，用户无法刷新令牌，需要重新登录。
                throw new UnauthorizedHttpException('jwt-auth', $exception->getMessage());
            }
        }

        // 在响应头中返回新的 token
        return $this->setAuthenticationHeader($next($request), $token);
    }
}