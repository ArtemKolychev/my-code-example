<?php

use Illuminate\Support\Facades\Route;

// Users module routes — scaffold
Route::get('/users/{id}', fn (string $id) => response()->json(['module' => 'users', 'id' => $id]));
