<?php

use App\Http\Controllers\Api\ContactController;
use Illuminate\Support\Facades\Route;

Route::apiResource('contacts', ContactController::class)->only(['index', 'store', 'show', 'update', 'destroy']);

Route::post('contacts/{id}/process-score', [ContactController::class, 'processScore']);
