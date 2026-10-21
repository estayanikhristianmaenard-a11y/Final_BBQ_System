<?php
/** CLI setup transport is separate from customer checkout; never accepts live keys. */
function paymongo_setup_request(string $key, string $method, string $path, ?array $payload = null): array
{
    if (!preg_match('/^sk_test_[A-Za-z0-9_]+$/D', $key)
        || !in_array([$method,$path], [['GET','/v1/webhooks'],['POST','/v1/webhooks']], true)) {
        throw new InvalidArgumentException('Only a PayMongo TEST secret key and webhook setup requests are supported.');
    }
    $curl = curl_init('https://api.paymongo.com' . $path);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_CUSTOMREQUEST=>$method,
        CURLOPT_USERPWD=>$key . ':', CURLOPT_HTTPAUTH=>CURLAUTH_BASIC,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json','Accept: application/json'],
        CURLOPT_CONNECTTIMEOUT=>5, CURLOPT_TIMEOUT=>20, CURLOPT_FOLLOWLOCATION=>false,
        CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_SSL_VERIFYHOST=>2, CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS]);
    if ($payload !== null) { curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($payload, JSON_THROW_ON_ERROR)); }
    $body = curl_exec($curl); $status = curl_getinfo($curl,CURLINFO_RESPONSE_CODE); curl_close($curl);
    if (!is_string($body) || $status < 200 || $status >= 300) {
        throw new RuntimeException($status === 401 ? 'PayMongo rejected the test key. Check it in your account.' : 'PayMongo setup did not complete. Check account access and internet connectivity, then retry; no payment was created.');
    }
    $data = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
    if (!is_array($data['data'] ?? null)) { throw new RuntimeException('PayMongo returned an incomplete setup response.'); }
    return $data;
}
function configure_paymongo_test(string $key, string $base, string $path, ?callable $transport = null): array
{
    if (PHP_SAPI !== 'cli') { throw new RuntimeException('Use the local PayMongo setup launcher.'); }
    $base = demo_base_url($base);
    if (!preg_match('/^sk_test_[A-Za-z0-9_]+$/D', $key)) { throw new InvalidArgumentException('Enter a secret TEST key starting with sk_test_. Live keys are rejected.'); }
    $lock = fopen($path . '.setup.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) { throw new RuntimeException('Another PayMongo setup is running.'); }
    $temp = null;
    try {
        @chmod($path . '.setup.lock', 0600);
        $previous = is_file($path) ? require $path : [];
        if (!is_array($previous)) { throw new RuntimeException('Existing payment settings need repair.'); }
        if (!empty($previous['secret_key']) && !hash_equals($previous['secret_key'], $key)) {
            throw new RuntimeException('A different test key is already saved. Keep the existing key while demo orders are pending.');
        }
        $request = $transport ?? static fn($method,$endpoint,$payload=null) => paymongo_setup_request($key,$method,$endpoint,$payload);
        $url = $base . '/api/paymongo-webhook.php';
        $list = $request('GET','/v1/webhooks');
        $hook = null;
        foreach ($list['data'] as $candidate) {
            $attributes = $candidate['attributes'] ?? [];
            if (($attributes['url'] ?? '') === $url && ($attributes['livemode'] ?? null) === false
                && in_array('checkout_session.payment.paid', $attributes['events'] ?? [], true)) { $hook=$candidate; break; }
        }
        if (!$hook) {
            $hook = $request('POST','/v1/webhooks', ['data'=>['attributes'=>['url'=>$url,'events'=>['checkout_session.payment.paid']]]])['data'] ?? null;
        }
        $a = $hook['attributes'] ?? [];
        $secret = $a['secret_key'] ?? (($previous['webhook_id'] ?? '') === ($hook['id'] ?? null) ? ($previous['webhook_secret'] ?? '') : '');
        if (($a['livemode'] ?? null) !== false || ($a['url'] ?? '') !== $url || ($a['status'] ?? '') !== 'enabled'
            || !in_array('checkout_session.payment.paid',$a['events'] ?? [],true) || !is_string($secret) || strlen($secret)<16
            || !preg_match('/^hook_[A-Za-z0-9]+$/D',$hook['id'] ?? '')) {
            throw new RuntimeException('The test webhook is not enabled or its signing secret is unavailable. Check this endpoint in the PayMongo dashboard; saved settings were not changed.');
        }
        $settings = ['enabled'=>true,'secret_key'=>$key,'webhook_secret'=>$secret,'public_base_url'=>$base,'webhook_id'=>$hook['id']];
        $temp = tempnam(dirname($path),'paymongo-');
        if (!$temp) { throw new RuntimeException('Payment settings could not be saved.'); }
        @chmod($temp,0600);
        if (file_put_contents($temp,"<?php\n// Local TEST payment credentials. Never commit.\nreturn " . var_export($settings,true) . ";\n",LOCK_EX)===false || !rename($temp,$path)) {
            throw new RuntimeException('The test webhook exists, but settings could not be saved. Retry setup after fixing config directory permissions.');
        }
        if (function_exists('opcache_invalidate')) { @opcache_invalidate($path,true); }
        return ['ok'=>true,'webhook_url'=>$url,'webhook_id'=>$hook['id']];
    } finally {
        if ($temp && is_file($temp)) { @unlink($temp); }
        flock($lock,LOCK_UN); fclose($lock);
    }
}
