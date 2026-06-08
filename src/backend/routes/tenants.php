<?php

use Illuminate\Support\Facades\Route;

// Tenants module routes — scaffold
Route::get('/tenants/{id}', fn (string $id) => response()->json(['module' => 'tenants', 'id' => $id]));
