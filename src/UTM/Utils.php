<?php

declare(strict_types=1);

namespace App\Component\UTM;

use App\Component\Exception\UTM\UtilsValidationException;

/**
 * Класс утилит для работы с данными, строками и форматированием
 * 
 * Содержит вспомогательные функции для:
 * - Валидации email, телефонов, IP-адресов
 * - Форматирования чисел и времени
 * - Работы со строками и массивами
 * - Транслитерации и конвертации
 */
class Utils
{
    /**
     * Проверяет валидность email адреса
     *
     * @param string $email Email для проверки
     * @return bool True если email валидный
     */
    public static function isValidEmail(string $email): bool
    {
        $email = trim($email);
        $emailCheck = filter_var($email, FILTER_VALIDATE_EMAIL);
        
        return $email === $emailCheck;
    }

    /**
     * Проверяет и форматирует номер мобильного телефона
     *
     * @param string $tel Номер телефона
     * @return string Отформатированный номер в формате 7XXXXXXXXXX
     * @throws UtilsValidationException Если номер некорректный
     */
    public static function validateMobileNumber(string $tel): string
    {
        $tel = trim($tel);

        if (!$tel) {
            throw new UtilsValidationException("Номер телефона не может быть пустым");
        }

        $tel = preg_replace('#[^0-9+]+#uis', '', $tel);
        if (!preg_match('#^(?:\\+?7|8|)(.*?)$#uis', $tel, $m)) {
            throw new UtilsValidationException("Неверный формат номера телефона");
        }

        $tel = '+7' . preg_replace('#[^0-9]+#uis', '', $m[1]);
        if (!preg_match('#^\\+7[0-9]{10}$#uis', $tel)) {
            throw new UtilsValidationException("Неверный формат номера телефона");
        }

        $tel = ltrim($tel, "+");
        if (strlen($tel) !== 11) {
            throw new UtilsValidationException("Номер телефона должен содержать 11 цифр");
        }

        return $tel;
    }

    /**
     * Проверяет и форматирует IP-адрес
     *
     * @param string $ip IP-адрес
     * @return string Отформатированный IP-адрес
     * @throws UtilsValidationException Если IP-адрес некорректный
     */
    public static function validateIp(string $ip): string
    {
        $ip = trim($ip);
        $ip = preg_replace('/[\r\t\v\e\f\n]/', ' ', $ip);
        $ip = str_replace(' ', '.', $ip);
        $ip = preg_replace('/\.+/', '.', $ip);

        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }

        throw new UtilsValidationException("IP-адрес имеет неверный формат");
    }

    /**
     * Проверяет попадает ли IP-адрес в заданную подсеть
     *
     * @param string $ip IP-адрес для проверки
     * @param string $range Подсеть в формате "192.168.1.0/24"
     * @return bool True если IP попадает в подсеть
     */
    public static function isIpInRange(string $ip, string $range): bool
    {
        $rangeParts = explode('/', $range);
        $rangeStart = ip2long($rangeParts[0]);
        $rangeEnd = $rangeStart + pow(2, 32 - (int)$rangeParts[1]) - 1;
        $ipLong = ip2long($ip);

        return ($ipLong >= $rangeStart && $ipLong <= $rangeEnd);
    }

    /**
     * Округляет число до заданной точности, убирая незначащие нули
     *
     * @param float|int|string $num Число для округления
     * @param int $precision Количество знаков после запятой
     * @return string Округленное число в виде строки
     * @throws UtilsValidationException Если число имеет неверный формат
     */
    public static function doRound(float|int|string $num, int $precision = 2): string
    {
        if (!is_numeric($num)) {
            throw new UtilsValidationException("Число имеет неверный формат");
        }

        $num = (float)$num;
        if ($num < 0) {
            $num = $num - 0.01;
        }

        // Округляем до нужной точности
        $num = round($num, $precision);

        $parts = explode('.', (string)$num);
        $intPart = $parts[0];
        $fracPart = isset($parts[1]) ? $parts[1] : '';

        // Отрезаем лишние нули справа
        $fracPart = rtrim($fracPart, '0');

        if (empty($fracPart)) {
            return $intPart;
        }

        return $intPart . '.' . $fracPart;
    }

    /**
     * Формирует правильные окончания слов в зависимости от числа
     *
     * @param int $value Число
     * @param array<int, string> $words Массив форм слова [1, 2, 5] (день, дня, дней)
     * @param bool $show Показывать ли число в результате
     * @return string Число со словом в правильной форме
     */
    public static function numWord(int $value, array $words, bool $show = true): string
    {
        $num = $value % 100;
        if ($num > 19) {
            $num = $num % 10;
        }

        $out = $show ? $value . ' ' : '';
        
        return match(true) {
            $num === 1 => $out . $words[0],
            $num >= 2 && $num <= 4 => $out . $words[1],
            default => $out . $words[2],
        };
    }

    /**
     * Конвертирует минуты в читабельный формат времени
     *
     * @param int $min Количество минут
     * @param bool $ext Расширенный формат (true) или короткий (false)
     * @return string Отформатированное время
     */
    public static function min2hour(int $min, bool $ext = true): string
    {
        $min = (int)ceil($min);
        $days = (int)floor($min / 1440);
        $hours = (int)floor(($min - $days * 1440) / 60);
        $minutes = (int)round($min - $days * 1440 - $hours * 60);

        if ($ext) {
            $result = '';
            if ($days !== 0) {
                $result .= self::numWord($days, ['день', 'дня', 'дней']) . " ";
            }
            if ($hours !== 0) {
                $result .= self::numWord($hours, ['час', 'часа', 'часов']) . " ";
            }
            if ($minutes !== 0) {
                $result .= self::numWord($minutes, ['минута', 'минуты', 'минут']);
            }
            return trim($result);
        }

        $result = '';
        if ($days !== 0) {
            $result .= $days . "д:";
        }
        if ($hours !== 0) {
            $result .= $hours . "ч:";
        }
        if ($minutes !== 0) {
            $result .= $minutes . "м";
        }

        return rtrim(trim($result), ':');
    }

    /**
     * Преобразует HEX строку в обычную строку
     *
     * @param string $hex HEX строка (с пробелами или без)
     * @return string Результирующая строка
     */
    public static function hexToStr(string $hex): string
    {
        $str = '';
        $hex = trim($hex);
        $hex = str_replace(' ', '', $hex);

        for ($i = 0; $i < strlen($hex) - 1; $i += 2) {
            if ($hex[$i] . $hex[$i + 1] !== '00') {
                $str .= chr(hexdec($hex[$i] . $hex[$i + 1]));
            }
        }

        return $str;
    }

    /**
     * Преобразует строку в HEX формат
     *
     * @param string $string Исходная строка
     * @return string HEX представление строки
     */
    public static function strToHex(string $string): string
    {
        $hex = '';
        for ($i = 0; $i < strlen($string); $i++) {
            $hex .= dechex(ord($string[$i]));
        }
        return $hex;
    }

    /**
     * Транслитерация с латиницы на кириллицу
     *
     * @param string $input Строка на латинице
     * @return string Строка на кириллице
     */
    public static function lat2rus(string $input): string
    {
        $gost = [
            "shch" => "щ", "zh" => "ж", "yo" => "ё", "yu" => "ю", "ch" => "ч", "sh" => "ш", "ya" => "я",
            "Shch" => "Щ", "Zh" => "Ж", "Yo" => "Ё", "Yu" => "Ю", "Ch" => "Ч", "Sh" => "Ш", "Ya" => "Я",
            "''" => "Ъ", "'" => "ь",
            "a" => "а", "b" => "б", "v" => "в", "g" => "г", "d" => "д", "e" => "е", "z" => "з",
            "i" => "и", "j" => "й", "k" => "к", "l" => "л", "m" => "м", "n" => "н", "o" => "о",
            "p" => "п", "r" => "р", "s" => "с", "t" => "т", "f" => "ф", "h" => "х", "c" => "ц",
            "y" => "ы", "u" => "у",
            "A" => "А", "B" => "Б", "V" => "В", "G" => "Г", "D" => "Д", "E" => "Е", "Z" => "З",
            "I" => "И", "J" => "Й", "K" => "К", "L" => "Л", "M" => "М", "N" => "Н", "O" => "О",
            "P" => "П", "R" => "Р", "S" => "С", "T" => "Т", "F" => "Ф", "H" => "Х", "C" => "Ц",
            "Y" => "Ы", "U" => "У",
        ];

        return strtr($input, $gost);
    }

    /**
     * Транслитерация с кириллицы на латиницу
     *
     * @param string $input Строка на кириллице
     * @return string Строка на латинице
     */
    public static function rus2lat(string $input): string
    {
        $gost = [
            "а" => "a", "б" => "b", "в" => "v", "г" => "g", "д" => "d", "е" => "e", "ё" => "yo",
            "ж" => "zh", "з" => "z", "и" => "i", "й" => "j", "к" => "k", "л" => "l", "м" => "m",
            "н" => "n", "о" => "o", "п" => "p", "р" => "r", "с" => "s", "т" => "t", "у" => "u",
            "ф" => "f", "х" => "h", "ц" => "c", "ч" => "ch", "ш" => "sh", "щ" => "shch", "ъ" => "''",
            "ы" => "y", "ь" => "'", "э" => "e", "ю" => "yu", "я" => "ya",
            "А" => "A", "Б" => "B", "В" => "V", "Г" => "G", "Д" => "D", "Е" => "E", "Ё" => "Yo",
            "Ж" => "Zh", "З" => "Z", "И" => "I", "Й" => "J", "К" => "K", "Л" => "L", "М" => "M",
            "Н" => "N", "О" => "O", "П" => "P", "Р" => "R", "С" => "S", "Т" => "T", "У" => "U",
            "Ф" => "F", "Х" => "H", "Ц" => "C", "Ч" => "Ch", "Ш" => "Sh", "Щ" => "Shch", "Ъ" => "''",
            "Ы" => "Y", "Ь" => "'", "Э" => "E", "Ю" => "Yu", "Я" => "Ya",
        ];

        return strtr($input, $gost);
    }

    /**
     * Мультибайтовая функция для перевода первого символа в верхний регистр
     *
     * @param string $str Исходная строка
     * @param string $enc Кодировка (по умолчанию UTF-8)
     * @return string Строка с первым символом в верхнем регистре
     */
    public static function mbUcfirst(string $str, string $enc = 'UTF-8'): string
    {
        return mb_strtoupper(mb_substr($str, 0, 1, $enc), $enc) . 
               mb_substr($str, 1, mb_strlen($str, $enc), $enc);
    }

    /**
     * Генерирует случайную строку заданной длины
     *
     * @param int $length Длина строки
     * @return string Случайная строка из букв и цифр
     */
    public static function generateString(int $length = 10): string
    {
        $permittedChars = '0123456789abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
        $randomString = '';
        $inputLength = strlen($permittedChars);

        for ($i = 0; $i < $length; $i++) {
            $randomString .= $permittedChars[mt_rand(0, $inputLength - 1)];
        }

        return $randomString;
    }

    /**
     * Генерирует случайный числовой пароль
     *
     * @param int $length Длина пароля
     * @return string Случайный числовой пароль
     */
    public static function generatePassword(int $length = 9): string
    {
        $permittedChars = '0123456789';
        $randomString = '';
        $inputLength = strlen($permittedChars);

        for ($i = 0; $i < $length; $i++) {
            $randomString .= $permittedChars[mt_rand(0, $inputLength - 1)];
        }

        return $randomString;
    }

    /**
     * Парсит строку с числами и диапазонами (например, "1,3-5,7" -> [1,3,4,5,7])
     *
     * @param string $str Строка с числами и диапазонами
     * @param bool $sort Сортировать ли результат
     * @return array<int, int> Массив чисел
     */
    public static function parseNumbers(string $str, bool $sort = true): array
    {
        $numbers = [];

        // Удаляем пробелы вокруг дефисов
        $str = preg_replace('/\s*-\s*/', '-', $str);

        // Разделение строки на элементы
        $parts = preg_split('/[^0-9-]+/', $str, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($parts as $part) {
            if (strpos($part, '-') !== false) {
                // Это диапазон
                [$start, $end] = explode('-', $part);
                $numbers = array_merge($numbers, range((int)trim($start), (int)trim($end)));
            } else {
                $numbers[] = (int)trim($part);
            }
        }

        if ($sort) {
            sort($numbers, SORT_NUMERIC);
        }

        return array_unique($numbers, SORT_NUMERIC);
    }

    /**
     * Конвертирует двумерный массив в одномерный
     *
     * @param array<int|string, mixed> $array2 Двумерный массив
     * @param string $format Формат вывода ('array' или 'string')
     * @return array<int, mixed>|string Одномерный массив или строка
     * @throws UtilsValidationException Если входные данные некорректны
     */
    public static function array2ToArray1(array $array2, string $format = 'array'): array|string
    {
        if (!is_array($array2)) {
            throw new UtilsValidationException("Ошибка в передаваемых данных");
        }

        $result = $format === 'array' ? [] : '';

        foreach ($array2 as $value1) {
            foreach ($value1 as $value2) {
                if ($format === 'array') {
                    $result[] = $value2;
                } else {
                    $result .= $value2;
                }
            }
        }

        return $result;
    }

    /**
     * Преобразует одномерный массив в список строк
     *
     * @param array<string|int, mixed> $array Массив для преобразования
     * @param string|null $lineIcon Символ в начале каждой строки
     * @param string $separator Разделитель между ключом и значением
     * @return string Отформатированный список
     * @throws UtilsValidationException Если входные данные некорректны
     */
    public static function array1ToList(array $array, ?string $lineIcon = null, string $separator = ":"): string
    {
        if (!is_array($array)) {
            throw new UtilsValidationException("Непредвиденная ошибка");
        }

        $result = '';
        foreach ($array as $key => $value) {
            $result .= $lineIcon . $key . $separator . " " . $value . PHP_EOL;
        }

        return $result;
    }

    /**
     * Мультибайтовая замена строк
     *
     * @param string|array<int, string> $search Что искать
     * @param string $replace Чем заменить
     * @param string $string Где заменять
     * @param bool $caseInsensitive Регистронезависимый поиск
     * @return string Результат замены
     */
    public static function mbStrReplace(string|array $search, string $replace, string $string, bool $caseInsensitive = true): string
    {
        if (is_array($search)) {
            foreach ($search as $s) {
                $string = self::mbStrReplace($s, $replace, $string, $caseInsensitive);
            }
            return $string;
        }

        $modifier = $caseInsensitive ? 'i' : '';
        return preg_replace('/' . preg_quote($search, '/') . '/su' . $modifier, $replace, $string);
    }

    /**
     * Преобразует HEX строку в двоичную (для портов коммутаторов)
     *
     * @param string $hexString HEX строка
     * @return string Двоичная строка
     */
    public static function memberPortsHex2Bin(string $hexString): string
    {
        $hex = ['Hex-STRING: ', ' ', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'A', 'B', 'C', 'D', 'E', 'F'];
        $bin = ['', '', '0000', '0001', '0010', '0011', '0100', '0101', '0110', '0111', '1000', '1001', '1010', '1011', '1100', '1101', '1110', '1111'];

        return str_replace($hex, $bin, $hexString);
    }

    /**
     * Преобразует двоичную строку в HEX (для портов коммутаторов)
     *
     * @param string $binString Двоичная строка
     * @return string HEX строка
     */
    public static function memberPortsBin2Hex(string $binString): string
    {
        $binParts = str_split($binString, 4);
        $result = '';

        $hexMap = [
            '0000' => '0', '0001' => '1', '0010' => '2', '0011' => '3',
            '0100' => '4', '0101' => '5', '0110' => '6', '0111' => '7',
            '1000' => '8', '1001' => '9', '1010' => 'A', '1011' => 'B',
            '1100' => 'C', '1101' => 'D', '1110' => 'E', '1111' => 'F'
        ];

        foreach ($binParts as $bin) {
            $result .= $hexMap[$bin] ?? '';
        }

        return trim($result);
    }

    /**
     * Преобразует десятичное число в HEX (для MAC-адресов)
     *
     * @param string $decimal Десятичное число в виде строки
     * @return string HEX представление
     * @throws UtilsValidationException Если bcmath не установлен
     */
    public static function dec2hex(string $decimal): string
    {
        if (!function_exists('bcmod')) {
            throw new UtilsValidationException('bcmath не установлен');
        }

        $hex = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'a', 'b', 'c', 'd', 'e', 'f'];
        $hexval = "";

        while ($decimal !== '0') {
            $hexval = $hex[(int)bcmod($decimal, '16')] . $hexval;
            $decimal = bcdiv($decimal, '16', 0);
        }

        if (strlen($hexval) === 0) {
            $hexval = "00";
        }
        if (strlen($hexval) === 1) {
            $hexval = "0" . $hexval;
        }

        return $hexval;
    }
}
