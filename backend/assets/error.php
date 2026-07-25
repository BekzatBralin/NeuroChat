<?php
$error_code = isset($_GET['code']) ? (int)$_GET['code'] : 404;

$errors = [
    403 => [
        'title' => '403: Доступ заблокирован',
        'desc'  => 'Safety Filter: Ваша попытка доступа была отклонена протоколами безопасности. Кажется, у вас недостаточно токенов прав для этого сектора.',
        'color' => '#ff4b2b' // Оранжево-красный (как Vega)
    ],
    404 => [
        'title' => '404: Путь не найден',
        'desc'  => 'Модель галлюцинирует: запрашиваемый путь не обнаружен в весах нашей нейросети. Возможно, страница была архивирована или никогда не существовала.',
        'color' => '#00d2ff' // Голубой (как Orion)
    ],
    405 => [
        'title' => '405: Метод отклонен',
        'desc'  => 'Invalid Logic: Вы пытаетесь применить действие, которое не поддерживается этой архитектурой. Нейросеть не может обработать запрос данным методом.',
        'color' => '#a100ff' // Фиолетовый (как Gemini/Live)
    ],
    500 => [
        'title' => '500: Критический сбой нейронов',
        'desc'  => 'Internal Server Error: Произошел фатальный коллапс логики. Мы уже отправили запрос в Rigel Pro для исправления ситуации.',
        'color' => '#a8ff78' // Зеленый (как Rigel)
    ],
    503 => [
        'title' => '503: Переобучение системы',
        'desc'  => 'System Maintenance: Мы временно офлайн — проводим дообучение моделей на новых данных. Lyria пока пишет музыку, а мы скоро вернемся.',
        'color' => '#f7971e' // Оранжевый (как Lyria)
    ]
];

$current = $errors[$error_code] ?? [
    'title' => $error_code . ': Неизвестная аномалия',
    'desc'  => 'Произошло что-то странное. Даже DeepSeek не может это объяснить.',
    'color' => '#ffffff'
];
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NeuroChat | Error <?php echo $error_code; ?></title>
    <style>
        :root {
            --accent-color: <?php echo $current['color']; ?>;
            --bg-color: #050505;
        }

        body {
            margin: 0;
            padding: 0;
            background-color: var(--bg-color);
            background-image: radial-gradient(circle at center, #111 0%, #050505 100%);
            color: #e0e0e0;
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            overflow: hidden;
        }

        /* Эффект звезд как на твоем скриншоте */
        .stars {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background: url('https://www.transparenttextures.com/patterns/stardust.png');
            opacity: 0.3;
            pointer-events: none;
        }

        .error-container {
            text-align: center;
            z-index: 1;
            max-width: 600px;
            padding: 40px;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            box-shadow: 0 20px 50px rgba(0,0,0,0.5);
        }

        .error-code {
            font-size: 120px;
            font-weight: 800;
            margin: 0;
            background: linear-gradient(135deg, #fff 0%, var(--accent-color) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: -5px;
            line-height: 1;
        }

        h1 {
            font-size: 24px;
            margin: 10px 0 20px;
            font-weight: 400;
            color: var(--accent-color);
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        p {
            font-size: 16px;
            line-height: 1.6;
            color: #888;
            margin-bottom: 30px;
        }

        .btn-home {
            display: inline-block;
            padding: 12px 30px;
            background: transparent;
            color: #fff;
            text-decoration: none;
            border: 1px solid var(--accent-color);
            border-radius: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .btn-home:hover {
            background: var(--accent-color);
            box-shadow: 0 0 20px var(--accent-color);
            color: #000;
        }

        /* Декоративный элемент "нейронная сеть" */
        .glitch-line {
            height: 2px;
            width: 50px;
            background: var(--accent-color);
            margin: 20px auto;
            box-shadow: 0 0 10px var(--accent-color);
        }
    </style>
</head>
<body>
    <div class="stars"></div>
    
    <div class="error-container">
        <div class="error-code"><?php echo $error_code; ?></div>
        <div class="glitch-line"></div>
        <h1><?php echo $current['title']; ?></h1>
        <p><?php echo $current['desc']; ?></p>
        <a href="/index.php" class="btn-home">Вернуться в терминал</a>
    </div>
</body>
</html>