<?php

// Intentionally empty. This app's AJAX endpoints are all same-origin
// calls from the Blade UI that need the web session (to identify which
// user is asking) and are session-cookie authenticated — the `api`
// middleware group this file is mounted under doesn't start a session at
// all, so nothing here would actually be able to authenticate a user.
// Those routes live in routes/web.php (under /api/... via
// Route::prefix('api')) instead. Keep this file for genuinely stateless,
// token-authenticated API needs if that ever comes up.
