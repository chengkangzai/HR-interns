<?php

use Illuminate\Support\Facades\Route;
Auth::loginUsingId(1);
Route::fallback(function () {
    return redirect()->to(filament()->getUrl());
});

//TODO : Add Calendar for Position
//TODO : Add Tagging for Candidate
