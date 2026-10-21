<?php
if (PHP_SAPI !== 'cli') { exit(1); }
require dirname(__DIR__) . '/includes/functions.php';
require dirname(__DIR__) . '/includes/demo.php';
require dirname(__DIR__) . '/includes/paymongo-setup.php';
$checks = 0;
function setup_check(bool $ok, string $label): void {
    global $checks;
    if (!$ok) { throw new RuntimeException($label); }
    $checks++; echo 'PASS: ' . $label . "\n";
}
$base = 'https://demo.example.com';
setup_check(demo_base_url(' https://demo.example.com/ ') === $base, 'Public base URL is normalized');
setup_check(demo_base_url($base . '/customer/menu.php') === $base, 'Pasted ordering link does not get a duplicated route');
setup_check(demo_base_url($base . '/bbq') === $base . '/bbq', 'Subfolder deployment is preserved');
foreach (['http://demo.example.com','javascript:alert(1)','https://user:pass@demo.example.com','https://demo.example.com/?token=secret','https://demo.example.com/#bad','https://demo.example.com:8080','https://demo.example.com/%2e%2e/config','https://demo.example.com/%5csecret','https://demo.example.com/%0asecret','https://127.0.0.1','https://localhost'] as $bad) {
    $rejected = false; try { demo_base_url($bad); } catch (InvalidArgumentException $error) { $rejected = true; }
    setup_check($rejected,'Unsafe/ambiguous ordering URL is rejected');
}
$dir = sys_get_temp_dir() . '/bbq-demo-setup-' . bin2hex(random_bytes(6));
mkdir($dir,0700);
$config = $dir . '/paymongo.php';
$key = 'sk_test_fixtureonly123';
$calls = [];
$hook = ['id'=>'hook_fixture123','attributes'=>['url'=>$base . '/api/paymongo-webhook.php','livemode'=>false,'status'=>'enabled','events'=>['checkout_session.payment.paid'],'secret_key'=>'whsk_fixture_secret_123456']];
try {
    $transport = function ($method,$path,$payload=null) use (&$calls,$hook,$base): array {
        $calls[] = [$method,$path];
        if ($method === 'GET') return ['data'=>[]];
        setup_check($payload === ['data'=>['attributes'=>['url'=>$base . '/api/paymongo-webhook.php','events'=>['checkout_session.payment.paid']]]], 'Webhook setup uses only the saved server URL and expected event');
        return ['data'=>$hook];
    };
    $result = configure_paymongo_test($key,$base,$config,$transport);
    $saved = require $config;
    setup_check($result['ok'] && $saved['enabled'] && $saved['secret_key']===$key && $saved['webhook_secret']===$hook['attributes']['secret_key'], 'Test webhook credentials save atomically after verification');
    setup_check($calls === [['GET','/v1/webhooks'],['POST','/v1/webhooks']], 'Setup looks for an existing webhook before creating one');
    $calls = [];
    configure_paymongo_test($key,$base,$config,function($method,$path,$payload=null) use (&$calls,$hook) { $calls[]=$method; return ['data'=>[$hook]]; });
    setup_check($calls === ['GET'], 'Repeated setup reuses the existing webhook without a new POST');
    $before = file_get_contents($config);
    foreach (['sk_live_rejected','sk_test_differentkey'] as $invalid) {
        $blocked = false;
        try { configure_paymongo_test($invalid,$base,$config,static function(){throw new RuntimeException('Network must not be called.');}); }
        catch (InvalidArgumentException | RuntimeException $error) { $blocked = true; }
        setup_check($blocked && file_get_contents($config)===$before, 'Live/mismatched keys cannot replace saved credentials');
    }
    $unsafe = $hook; $unsafe['attributes']['livemode']=true;
    $blocked = false;
    try { configure_paymongo_test($key,$base,$config,static fn($method,$path,$payload=null)=>$method==='GET'?['data'=>[]]:['data'=>$unsafe]); }
    catch (RuntimeException $error) { $blocked=true; }
    setup_check($blocked && file_get_contents($config)===$before, 'Live-mode provider response is rejected without changing configuration');
    $unknownConfig = $dir . '/unknown.php';
    $failed = false;
    try { configure_paymongo_test($key,$base,$unknownConfig,static function($method,$path,$payload=null){if($method==='GET')return ['data'=>[]];throw new RuntimeException('Simulated timeout');}); }
    catch (RuntimeException $error) { $failed=true; }
    setup_check($failed && !is_file($unknownConfig), 'Ambiguous provider creation never reports configured');
    $posts = 0;
    configure_paymongo_test($key,$base,$unknownConfig,function($method,$path,$payload=null)use($hook,&$posts){if($method==='POST')$posts++;return ['data'=>[$hook]];});
    setup_check($posts===0 && is_file($unknownConfig), 'Retry discovers a previously created webhook instead of duplicating it');
    setup_check(write_demo_settings($dir . '/demo.php',['public_base_url'=>$base]) && (require $dir . '/demo.php')['public_base_url']===$base, 'Non-secret demo URL is saved independently of payment credentials');
    echo $checks . " demo setup checks passed. No external requests or payments were made.\n";
} finally {
    foreach (glob($dir . '/*') as $file) { unlink($file); }
    rmdir($dir);
}
