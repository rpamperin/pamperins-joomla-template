<?php
/**
 * TEST-ONLY OpenID Connect provider that stands in for the Pamperins CRM while the real one is built.
 * Never deploy this. Run (see dev/README-sso-test.md):
 *   php -S 0.0.0.0:9000 dev/test-idp/router.php
 * Env: IDP_PUBLIC (browser-facing base, default http://localhost:9000), IDP_INTERNAL (server-facing base),
 *      IDP_REDIRECTS (comma-separated allowed redirect URIs)
 */
$pub   = rtrim(getenv('IDP_PUBLIC') ?: 'http://localhost:9000', '/');
$int   = rtrim(getenv('IDP_INTERNAL') ?: $pub, '/');
$cid   = 'pamperins-website';
$csec  = 'test-secret-123';
$allow = array_filter(array_map('trim', explode(',', getenv('IDP_REDIRECTS') ?: '')));
$store = sys_get_temp_dir() . '/idp-state.json';
$users = [
    // Same claim shapes as rsup: sub "client:<uuid>" / "staff:<uuid>"; roles client | staff+tech | staff+office | staff+admin.
    // Client e-mails are NOT verified in rsup (no confirmation e-mail); staff e-mails are typed in by an admin, so verified.
    'alice' => ['sub' => 'client:0b6f1d2e-0000-4000-8000-000000001001', 'name' => 'Alice Client', 'email' => 'alice@example.com', 'email_verified' => false, 'roles' => ['client']],
    'bob'   => ['sub' => 'staff:0b6f1d2e-0000-4000-8000-000000002002', 'name' => 'Bob Tech', 'email' => 'bob@example.com', 'email_verified' => true, 'roles' => ['staff', 'tech']],
    'carol' => ['sub' => 'staff:0b6f1d2e-0000-4000-8000-000000002003', 'name' => 'Carol Admin', 'email' => 'carol@example.com', 'email_verified' => true, 'roles' => ['staff', 'admin']],
    'olga'  => ['sub' => 'staff:0b6f1d2e-0000-4000-8000-000000002006', 'name' => 'Olga Office', 'email' => 'olga@example.com', 'email_verified' => true, 'roles' => ['staff', 'office']],
    'dave'  => ['sub' => 'client:0b6f1d2e-0000-4000-8000-000000001004', 'name' => 'Dave Existing', 'email' => 'customer@example.com', 'email_verified' => false, 'roles' => ['client']],
    'eve'   => ['sub' => 'client:0b6f1d2e-0000-4000-8000-000000001005', 'name' => 'Eve Other', 'email' => 'other@example.com', 'email_verified' => false, 'roles' => ['client']],
];
$load = fn () => is_file($store) ? (json_decode(file_get_contents($store), true) ?: []) : [];
$save = fn ($s) => file_put_contents($store, json_encode($s));
$json = function ($data, int $code = 200) { http_response_code($code); header('Content-Type: application/json'); echo json_encode($data); exit; };
$b64  = fn ($s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Sample office price list (docs/pricing-feed.md). Override with a file pricing.json next to this script's store.
if ($path === '/api/public/pricing') {
    $custom = sys_get_temp_dir() . '/ptidp-pricing.json';
    if (is_file($custom)) { header('Content-Type: application/json'); echo file_get_contents($custom); exit; }
    $json(['version' => 1, 'updated_at' => '2026-11-22T17:00:00Z', 'currency' => 'USD', 'price_note' => 'Prices plus sales tax. On-site visits outside Green Bay & Appleton may include travel.',
        'plans' => [
            ['id' => 'home', 'name' => 'Home', 'price_cents' => 3500, 'period' => 'month', 'summary' => 'For one household', 'features' => [
                ['label' => 'Devices covered', 'value' => '3'], ['label' => 'Remote support', 'value' => 'Business hours'],
                ['label' => 'On-site visits', 'value' => '10% off'], ['label' => 'Backup monitoring', 'value' => false]]],
            ['id' => 'office', 'name' => 'Small Office', 'price_cents' => 8900, 'period' => 'month', 'featured' => true, 'summary' => 'Up to 10 people', 'features' => [
                ['label' => 'Devices covered', 'value' => '12'], ['label' => 'Remote support', 'value' => 'Priority, same day'],
                ['label' => 'On-site visits', 'value' => '1 per month'], ['label' => 'Backup monitoring', 'value' => true], ['label' => 'Network Security Device', 'value' => 'Included']]],
            ['id' => 'business', 'name' => 'Business', 'price_cents' => 0, 'price_text' => 'Call for a quote', 'period' => 'month', 'summary' => '10+ people or PLC/SCADA', 'features' => [
                ['label' => 'Devices covered', 'value' => 'Custom'], ['label' => 'Remote support', 'value' => '24/7'],
                ['label' => 'On-site visits', 'value' => 'As needed'], ['label' => 'Backup monitoring', 'value' => true], ['label' => 'Network Security Device', 'value' => 'Included']]],
        ],
        'services' => [
            ['id' => 'remote', 'name' => 'Remote session', 'price_cents' => 5000, 'unit' => 'session', 'notes' => 'First 30 minutes'],
            ['id' => 'onsite', 'name' => 'On-site visit', 'price_cents' => 9500, 'unit' => 'hour', 'notes' => '1 hour minimum'],
            ['id' => 'virus', 'name' => 'Virus / malware cleanup <b>', 'price_cents' => 12999, 'unit' => 'flat', 'notes' => 'Flat rate'],
        ]]);
}

if ($path === '/.well-known/openid-configuration') {
    $json(['issuer' => $pub, 'authorization_endpoint' => "$pub/authorize", 'token_endpoint' => "$int/token",
        'userinfo_endpoint' => "$int/userinfo", 'jwks_uri' => "$int/jwks", 'response_types_supported' => ['code'],
        'subject_types_supported' => ['public'], 'id_token_signing_alg_values_supported' => ['HS256'],
        'code_challenge_methods_supported' => ['S256'], 'token_endpoint_auth_methods_supported' => ['client_secret_basic'],
        'scopes_supported' => ['openid', 'email', 'profile']]);
}

if ($path === '/authorize') {
    $q = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
    $ok = ($q['client_id'] ?? '') === $cid && in_array($q['redirect_uri'] ?? '', $allow, true) && ($q['response_type'] ?? '') === 'code'
        && ($q['code_challenge_method'] ?? '') === 'S256' && !empty($q['code_challenge']) && str_contains($q['scope'] ?? '', 'openid');
    if (!$ok) { http_response_code(400); exit('bad authorization request'); }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $redir = $q['redirect_uri'] . (str_contains($q['redirect_uri'], '?') ? '&' : '?');
        if (($q['action'] ?? '') === 'deny') { header('Location: ' . $redir . http_build_query(['error' => 'access_denied', 'state' => $q['state'] ?? ''])); exit; }
        if (!isset($users[$q['user'] ?? ''])) { http_response_code(400); exit('pick a user'); }
        $s = $load(); $code = bin2hex(random_bytes(16));
        $s['codes'][$code] = ['user' => $q['user'], 'nonce' => $q['nonce'] ?? '', 'redirect_uri' => $q['redirect_uri'], 'challenge' => $q['code_challenge'], 'exp' => time() + 60];
        $save($s);
        header('Location: ' . $redir . http_build_query(['code' => $code, 'state' => $q['state'] ?? ''])); exit;
    }
    $hidden = ''; foreach ($q as $k => $v) { $hidden .= '<input type="hidden" name="' . htmlspecialchars($k) . '" value="' . htmlspecialchars($v) . '">'; }
    $opts = ''; foreach ($users as $k => $u) { $opts .= "<label style='display:block;margin:.4rem 0'><input type='radio' name='user' value='$k'> " . htmlspecialchars("{$u['name']} — {$u['email']} (" . implode(',', $u['roles']) . ($u['email_verified'] ? '' : ', e-mail NOT verified') . ')') . '</label>'; }
    echo "<!doctype html><title>TEST login server</title><body style='font-family:sans-serif;max-width:36rem;margin:3rem auto'><h1>TEST Pamperins login</h1><p>Stand-in for the CRM sign-in page.</p><form method='post'>$hidden$opts<p><button name='action' value='allow' id='signin'>Sign in</button> <button name='action' value='deny' id='deny'>Cancel</button></p></form>";
    exit;
}

if ($path === '/token' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    [$u, $p] = array_map('rawurldecode', explode(':', base64_decode(substr($_SERVER['HTTP_AUTHORIZATION'] ?? '', 6)) ?: ':', 2) + ['', '']);
    if ($u !== $cid || $p !== $csec) $json(['error' => 'invalid_client'], 401);
    if (isset($_POST['client_secret'])) $json(['error' => 'invalid_request', 'error_description' => 'use one client auth method'], 400);
    $s = $load(); $c = $s['codes'][$_POST['code'] ?? ''] ?? null; unset($s['codes'][$_POST['code'] ?? '']); $save($s);   // single use
    if (!$c || $c['exp'] < time() || $c['redirect_uri'] !== ($_POST['redirect_uri'] ?? '')) $json(['error' => 'invalid_grant'], 400);
    if ($b64(hash('sha256', $_POST['code_verifier'] ?? '', true)) !== $c['challenge']) $json(['error' => 'invalid_grant', 'error_description' => 'PKCE failed'], 400);
    $user = $users[$c['user']]; $now = time();
    $claims = ['iss' => $pub, 'sub' => $user['sub'], 'aud' => $cid, 'iat' => $now, 'exp' => $now + 300, 'nonce' => $c['nonce'], 'email' => $user['email'], 'email_verified' => $user['email_verified'], 'name' => $user['name']];
    $h = $b64(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])); $pl = $b64(json_encode($claims));
    $at = bin2hex(random_bytes(16)); $s = $load(); $s['tokens'][$at] = $c['user']; $save($s);
    $json(['access_token' => $at, 'token_type' => 'Bearer', 'expires_in' => 300, 'id_token' => "$h.$pl." . $b64(hash_hmac('sha256', "$h.$pl", $csec, true))]);
}

if ($path === '/userinfo') {
    $s = $load(); $at = substr($_SERVER['HTTP_AUTHORIZATION'] ?? '', 7); $k = $s['tokens'][$at] ?? null;
    if (!$k) $json(['error' => 'invalid_token'], 401);
    $json($users[$k]);
}

http_response_code(404); echo 'not found';
