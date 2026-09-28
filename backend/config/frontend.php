<?php

return [
    // The public Next.js storefront's base URL — used only where the backend
    // must emit an absolute link to it (the blog RSS feed's <link>/<guid>).
    'url' => env('FRONTEND_URL', 'http://localhost:3000'),
];
