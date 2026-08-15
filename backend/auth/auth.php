<?php
require_once __DIR__ . '/../settings.php';
require_once PATHS['db'];
require_once __DIR__ . '/jwt.php';

// Инициализируем сессии в БД вместо стандартной системы PHP
initSessionStorage();

session_start();

if (isset($_GET['pending']) && !empty($_SESSION['tg_pending'])) {
    $pending = true;
}

$action = $_GET['action'] ?? (isset($_GET['code']) ? 'callback' : 'page');

if (isset($_GET['token']) && $action !== 'logout') {
    // If we land here with a token, just pass it through
    header('Location: /?token=' . $_GET['token']);
    exit;
}

if ($action === 'logout') {
    $wasApp = !empty($_SESSION['is_app']) || isset($_COOKIE['nc_app']);
    session_destroy();
    
    // Очищаем куки
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    setcookie('nc_session', '', time() - 3600, '/');
    
    header('Location: /' . ($wasApp ? '?app=mobile' : ''));
    exit;
}

if ($action === 'native_login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $id_token = $_POST['id_token'] ?? '';
    if (!$id_token) {
        echo json_encode(['ok' => false, 'error' => 'No token provided']);
        exit;
    }

    $tokenInfoResp = @file_get_contents('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($id_token));
    if (!$tokenInfoResp) {
        echo json_encode(['ok' => false, 'error' => 'Failed to verify token with Google']);
        exit;
    }
    
    $tokenInfo = json_decode($tokenInfoResp, true);

    if (isset($tokenInfo['error']) || empty($tokenInfo['sub'])) {
        echo json_encode(['ok' => false, 'error' => 'Invalid token']);
        exit;
    }

    $googleUser = [
        'id'      => $tokenInfo['sub'],
        'email'   => $tokenInfo['email'] ?? '',
        'name'    => $tokenInfo['name'] ?? '',
        'picture' => $tokenInfo['picture'] ?? ''
    ];

    $user = upsertUser($googleUser);
    $jwtToken = JWT::encode(['id' => $user['id']], JWT_SECRET);

    echo json_encode(['ok' => true, 'token' => $jwtToken]);
    exit;
}

if ($action === 'login') {
    $_SESSION['oauth_state'] = bin2hex(random_bytes(16));
    // CLI: save port and state for localhost callback
    if (!empty($_GET['cli_state'])) {
        $_SESSION['cli_state'] = preg_replace('/[^a-zA-Z0-9\-_]/', '', $_GET['cli_state']);
        $_SESSION['cli_port']  = (int)($_GET['cli_port'] ?? 0);
    }
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

if (isset($_GET['error']) && !isset($_GET['code']) && $action !== 'page') {
    $error = 'Ошибка от Google: ' . htmlspecialchars($_GET['error']);
}

if ($action === 'callback' && isset($_GET['code'])) {
    if (!isset($_GET['state']) || $_GET['state'] !== ($_SESSION['oauth_state'] ?? '')) {
        $error = 'Ошибка безопасности: state mismatch. (Получено: ' . ($_GET['state'] ?? 'null') . ', Ожидалось: ' . ($_SESSION['oauth_state'] ?? 'null') . ')';
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
            $error = 'Google не выдал access_token. Ответ: ' . $tokenResp;
        } else {
            $profileResp = httpGet(
                'https://www.googleapis.com/oauth2/v2/userinfo',
                $token['access_token']
            );
            $profile = json_decode($profileResp, true);

            if (empty($profile['id'])) {
                $error = 'Google не выдал ID профиля. Ответ: ' . $profileResp;
            } else {
                try {
                    $user = upsertUser($profile);
                    if (!$user || empty($user['id'])) {
                        $error = 'upsertUser вернул пустого пользователя!';
                    } else {
                        $jwtToken = JWT::encode(['id' => $user['id']], JWT_SECRET);

                        // CLI: redirect to localhost callback if cli_port and cli_state are present
                        $cliPort  = (int)($_SESSION['cli_port'] ?? 0);
                        $cliState = $_SESSION['cli_state'] ?? '';
                        
                        // Уничтожаем временную сессию OAuth
                        session_destroy();
                        if (ini_get("session.use_cookies")) {
                            $params = session_get_cookie_params();
                            setcookie(session_name(), '', time() - 42000,
                                $params["path"], $params["domain"],
                                $params["secure"], $params["httponly"]
                            );
                        }

                        if ($cliPort >= 1024 && $cliPort <= 65535 && $cliState) {
                            $callbackUrl = 'http://127.0.0.1:' . $cliPort . '/callback'
                                . '?token=' . urlencode($jwtToken)
                                . '&state=' . urlencode($cliState);
                            header('Location: ' . $callbackUrl);
                        } else {
                            header('Location: /?token=' . $jwtToken);
                        }
                        exit;
                    }
                } catch (\Throwable $e) {
                    $error = 'Fatal DB Error в upsertUser: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
                }
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

$redirectUrl = '/';
if ($error) {
    file_put_contents(__DIR__ . '/google_error_log.txt', date('Y-m-d H:i:s') . " - Auth Error: " . $error . PHP_EOL, FILE_APPEND);
    $redirectUrl .= '?error=' . urlencode($error);
} elseif ($wasApp ?? false) {
    $redirectUrl .= '?app=mobile';
}
file_put_contents(__DIR__ . '/google_error_log.txt', date('Y-m-d H:i:s') . " - Redirecting to: " . $redirectUrl . PHP_EOL, FILE_APPEND);
header('Location: ' . $redirectUrl);
exit;
