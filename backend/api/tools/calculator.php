<?php
// backend/api/tools/calculator.php

/**
 * Функция для вычисления математического выражения
 * @param float $a
 * @param string $operator
 * @param float|null $b (необязателен для некоторых операций)
 * @return string
 */
function tool_calculator($a, $operator, $b) {
    if (!is_numeric($a) || (!is_numeric($b) && $b !== null)) {
        return "Ошибка: операнды должны быть числами.";
    }

    $a = (float)$a;
    $b = $b !== null ? (float)$b : null;

    switch ($operator) {
        case '+':
            return (string)($a + $b);
        case '-':
            return (string)($a - $b);
        case '*':
            return (string)($a * $b);
        case '/':
            if ($b == 0) {
                return "Ошибка: деление на ноль.";
            }
            return (string)($a / $b);
        case '%':
            if ($b == 0) {
                return "Ошибка: деление на ноль.";
            }
            return (string)($a % $b);
        case '^':
        case '**':
            return (string)(pow($a, $b));
        default:
            return "Ошибка: неизвестный оператор '$operator'. Поддерживаются: +, -, *, /, %, ^";
    }
}
