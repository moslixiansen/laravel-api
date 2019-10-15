<?php

// 启用 jwt鉴权
app('Dingo\Api\Auth\Auth')->extend('jwt', function ($app) {
    return new Dingo\Api\Auth\Provider\JWT($app['Tymon\JWTAuth\JWTAuth']);
});

$api = app('Dingo\Api\Routing\Router');

$api->version('v1', function ($api) {
    // 公开API
    $api->group([], function ($api) {
        $api->post('auth/login', '\App\Http\Controllers\V1\AuthController@login');
        $api->post('auth/register', '\App\Http\Controllers\V1\AuthController@register');



    });

    // 被保护的私密API, 私密和非私密路由同时存在而且有冲突的情况下，先声明的路由规则生效
    $api->group(['protected' => true, 'middleware' => 'api.auth' ], function ($api) {
        /**
         * 验证表为 users
         */
        $api->get('auth/test', '\App\Http\Controllers\V1\AuthController@test');
        $api->get('auth/logout', '\App\Http\Controllers\V1\AuthController@logout');
        $api->get('auth/me', '\App\Http\Controllers\V1\AuthController@me');
        $api->get('auth/refresh', '\App\Http\Controllers\V1\AuthController@refresh');



//        $api->controller('auth', \App\Http\Controllers\V1\AuthController::class);

    });
});

app('Dingo\Api\Transformer\Factory')->register('Product', 'ProductTransformer');
