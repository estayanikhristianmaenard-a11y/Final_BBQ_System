<?php
/** Copy to paymongo.php on the server. Never commit credentials. TEST MODE ONLY. */
return [
    'enabled' => false,
    'secret_key' => '', // sk_test_... from your PayMongo account.
    'webhook_secret' => '', // Signing secret for this server's TEST webhook endpoint.
    'public_base_url' => '', // HTTPS ngrok URL INCLUDING /Final_BBQ_System/bulkbites-bbq if applicable.
];
