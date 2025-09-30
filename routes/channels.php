<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('trips.{tripId}', function ($user, int $tripId) {
    return !is_null($user);
});
