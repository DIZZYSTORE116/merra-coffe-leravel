<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'home');
Route::view('/admin', 'admin.index'); // tanpa login, sama seperti versi sebelumnya
