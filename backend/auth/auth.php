<?php
require_once __DIR__ . '/../settings.php';
require_once PATHS['db'];

// Инициализируем сессии в БД вместо стандартной системы PHP
initSessionStorage();
session_set_cookie_params(SESSION_LIFETIME);
session_start();

if (isset($_GET['pending']) && !empty($_SESSION['tg_pending'])) {
    $pending = true;
}

$action = $_GET['action'] ?? (isset($_GET['code']) ? 'callback' : 'page');

if (isset($_SESSION['user']) && $action !== 'logout') {
    if (isset($_GET['app']) && $_GET['app'] === 'mobile') $_SESSION['is_app'] = true;
    header('Location: /');
    exit;
}

if ($action === 'logout') {
    $wasApp = !empty($_SESSION['is_app']) || isset($_COOKIE['nc_app']);
    session_destroy();
    header('Location: /' . ($wasApp ? '?app=mobile' : ''));
    exit;
}

if ($action === 'login') {
    $_SESSION['oauth_state'] = bin2hex(random_bytes(16));
    $params = http_build_query([
        'client_id'     => GOOGLE_CLIENT_ID,
        'redirect_uri'  => GOOGLE_REDIRECT_URI,
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'state'         => $_SESSION['oauth_state'],
        'prompt'        => 'select_account',
    ]);
    header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $params);
    exit;
}

$error   = null;
if (!isset($pending)) $pending = false;

if ($action === 'callback' && isset($_GET['code'])) {
    if (!isset($_GET['state']) || $_GET['state'] !== ($_SESSION['oauth_state'] ?? '')) {
        $error = 'Ошибка безопасности. Попробуй ещё раз.';
    } else {
        $tokenResp = httpPost('https://oauth2.googleapis.com/token', [
            'code'          => $_GET['code'],
            'client_id'     => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'redirect_uri'  => GOOGLE_REDIRECT_URI,
            'grant_type'    => 'authorization_code',
        ]);
        $token = json_decode($tokenResp, true);

        if (empty($token['access_token'])) {
            $error = 'Не удалось получить токен от Google.';
        } else {
            $profileResp = httpGet(
                'https://www.googleapis.com/oauth2/v2/userinfo',
                $token['access_token']
            );
            $profile = json_decode($profileResp, true);

            if (empty($profile['id'])) {
                $error = 'Не удалось получить профиль от Google.';
            } else {
                $user = upsertUser($profile);
                $_SESSION['user'] = $user;
                unset($_SESSION['oauth_state']);
                header('Location: /');
                exit;
            }
        }
    }
}

function httpPost(string $url, array $data): string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($data),
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $r = curl_exec($ch);
    curl_close($ch);
    return $r ?: '';
}

function httpGet(string $url, string $token): string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token],
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $r = curl_exec($ch);
    curl_close($ch);
    return $r ?: '';
}

if (isset($_GET['app']) && $_GET['app'] === 'mobile') {
    $_SESSION['is_app'] = true;
    setcookie('nc_app', '1', time() + 365*24*3600, '/');
}
$isApp = !empty($_SESSION['is_app']) || isset($_COOKIE['nc_app']);

header('Location: /');
exit;
