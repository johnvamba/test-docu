<?php

use Illuminate\Support\Facades\Route;

// Every non-API path renders the SPA shell; vue-router takes it from there.
Route::view('/{any?}', 'app')->where('any', '^(?!api|sanctum|up|build|app/).*$');
