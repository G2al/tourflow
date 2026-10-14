<?php

use Illuminate\Support\Facades\Route;

// Il backend non ha pagine pubbliche: la home porta al pannello admin.
Route::redirect('/', '/admin');
