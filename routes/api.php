<?php

use App\Http\Controllers\DeployController;
use App\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

// Kontrak API sama dengan versi Vercel, jadi script.js & admin.js tidak perlu diubah.
Route::get('/orders', [OrderController::class, 'index']);
Route::post('/orders', [OrderController::class, 'store']);
Route::match(['patch', 'put'], '/orders/{id?}', [OrderController::class, 'update']);
Route::match(['get', 'post'], '/reset', [OrderController::class, 'reset']);

Route::get('/deploys', [DeployController::class, 'index']);
Route::post('/deploys', [DeployController::class, 'store']);
