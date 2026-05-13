<?php

use App\Http\Controllers\Api\ContactController;
use Illuminate\Support\Facades\Route;

Route::apiResource('contacts', ContactController::class)->only(['index', 'store']);
