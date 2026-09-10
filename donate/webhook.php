<?php
// Mollie webhook endpoint. Mollie sends the payment id here after status changes.
// No secret is stored in this file. Payment status can be retrieved server-side
// with the same MOLLIE_API_KEY if donation accounting is added later.
http_response_code(200);
header('Content-Type: text/plain; charset=utf-8');
echo 'OK';
