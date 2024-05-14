<?php

use Illuminate\Support\Facades\Route;

Route::fallback(function () {
    return redirect()->to(filament()->getUrl());
});

//TODO : Add Calendar for Position
//TODO : Add Tagging for Candidate

