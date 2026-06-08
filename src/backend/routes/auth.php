<?php

use App\Auth\Http\Action\LoginAction;
use App\Auth\Http\Action\RefreshAction;
use App\Auth\Http\Action\RegisterAction;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:10,1'])->group(function (): void {
    Route::post('/auth/register', RegisterAction::class);
    Route::post('/auth/login', LoginAction::class);
    Route::post('/auth/refresh', RefreshAction::class);
});
