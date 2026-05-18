<?php

use Illuminate\Support\Facades\Broadcast;

// No auth needed -- using public channels (no PrivateChannel).
// All channel authorization is handled by the event's broadcastOn().
