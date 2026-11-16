<?php

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Auth, email verification, password reset, Google OAuth handoff,
| dashboard stats, calendar creation/listing, plans, organizations and
| billing (Stripe checkout + webhook).
|
*/

require __DIR__.'/api/auth.php';
require __DIR__.'/api/dashboard.php';
require __DIR__.'/api/calendars.php';
require __DIR__.'/api/plans.php';
require __DIR__.'/api/organizations.php';
require __DIR__.'/api/billing.php';
