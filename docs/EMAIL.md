# Email - Документация

## Описание

`Email` - класс для отправки электронных писем с поддержкой SMTP, вложений и HTML контента. Обеспечивает надежную доставку писем с механизмом повторных попыток и гибкими настройками.

## Возможности

- ✅ Отправка через SMTP или функцию mail()
- ✅ Поддержка HTML и текстового формата
- ✅ Вложения файлов (attachments)
- ✅ Множественные получатели (to, cc, bcc)
- ✅ Настраиваемые заголовки
- ✅ Механизм повторных попыток при сбоях
- ✅ Настройка таймаутов
- ✅ SMTP аутентификация (TLS/SSL)
- ✅ Reply-To и Return-Path
- ✅ Кодировка UTF-8
- ✅ Интеграция с Logger
- ✅ Валидация email адресов

## Требования

- PHP 8.1+
- Расширения: `openssl` (для SMTP с TLS/SSL)
- SMTP сервер (опционально, если не используется mail())

## Установка

```bash
composer install
```

## Конфигурация

### SMTP конфигурация

Создайте файл `config/email.json`:

```json
{
    "from_email": "noreply@example.com",
    "from_name": "My Application",
    "reply_to": "support@example.com",
    "reply_name": "Support Team",
    "return_path": "bounces@example.com",
    "charset": "UTF-8",
    "smtp": {
        "host": "smtp.gmail.com",
        "port": 587,
        "encryption": "tls",
        "username": "your-email@gmail.com",
        "password": "your-app-password"
    },
    "retry_attempts": 3,
    "retry_delay": 5,
    "timeout": 30
}
```

### Конфигурация с mail()

```json
{
    "from_email": "noreply@example.com",
    "from_name": "My Application",
    "charset": "UTF-8"
}
```

### Параметры конфигурации

| Параметр | Тип | Обязательный | По умолчанию | Описание |
|----------|-----|--------------|--------------|----------|
| `from_email` | string | Да | - | Email отправителя |
| `from_name` | string | Нет | - | Имя отправителя |
| `reply_to` | string | Нет | - | Reply-To адрес |
| `reply_name` | string | Нет | - | Reply-To имя |
| `return_path` | string | Нет | - | Return-Path адрес |
| `charset` | string | Нет | "UTF-8" | Кодировка |
| `smtp.host` | string | Нет | - | SMTP сервер |
| `smtp.port` | int | Нет | 587 | SMTP порт |
| `smtp.encryption` | string | Нет | - | "tls" или "ssl" |
| `smtp.username` | string | Нет | - | SMTP логин |
| `smtp.password` | string | Нет | - | SMTP пароль |
| `retry_attempts` | int | Нет | 3 | Попытки отправки |
| `retry_delay` | int | Нет | 5 | Задержка между попытками (сек) |
| `timeout` | int | Нет | 30 | Таймаут соединения (сек) |

## Использование

### Инициализация

```php
use App\Component\Email;
use App\Component\Logger;
use App\Component\Config\ConfigLoader;

// С логгером
$config = ConfigLoader::load(__DIR__ . '/config/email.json');
$loggerConfig = ConfigLoader::load(__DIR__ . '/config/logger.json');
$logger = new Logger($loggerConfig);
$email = new Email($config, $logger);

// Без логгера
$email = new Email($config);
```

### Простая отправка

```php
// Текстовое письмо
$email->send(
    'user@example.com',
    'Тема письма',
    'Текст письма'
);

// HTML письмо
$email->send(
    'user@example.com',
    'Добро пожаловать!',
    '<h1>Привет!</h1><p>Спасибо за регистрацию.</p>',
    ['is_html' => true]
);
```

### Множественные получатели

```php
// Несколько получателей
$email->send(
    ['user1@example.com', 'user2@example.com', 'user3@example.com'],
    'Массовая рассылка',
    'Текст письма для всех'
);

// С копиями (CC) и скрытыми копиями (BCC)
$email->send(
    'user@example.com',
    'Отчет',
    'Содержимое отчета',
    [
        'cc' => 'manager@example.com',
        'bcc' => ['admin@example.com', 'archive@example.com'],
    ]
);
```

### С вложениями

```php
// Одно вложение
$email->send(
    'user@example.com',
    'Документ',
    'Во вложении документ',
    [
        'attachments' => [
            ['path' => '/path/to/document.pdf'],
        ],
    ]
);

// Несколько вложений с пользовательскими именами
$email->send(
    'user@example.com',
    'Файлы проекта',
    'Прикрепляю файлы',
    [
        'is_html' => true,
        'attachments' => [
            [
                'path' => '/path/to/report.pdf',
                'name' => 'Отчет_2024.pdf',
            ],
            [
                'path' => '/path/to/data.xlsx',
                'name' => 'Данные.xlsx',
            ],
            [
                'path' => '/path/to/image.jpg',
                'name' => 'Фото.jpg',
                'mime' => 'image/jpeg',
            ],
        ],
    ]
);
```

### Расширенные опции

```php
$email->send(
    ['recipient@example.com'],
    'Тема письма',
    '<h1>Заголовок</h1><p>Текст письма</p>',
    [
        'is_html' => true,
        'cc' => 'copy@example.com',
        'bcc' => ['hidden1@example.com', 'hidden2@example.com'],
        'reply_to' => 'support@example.com',
        'reply_name' => 'Support Team',
        'return_path' => 'bounces@example.com',
        'attachments' => [
            ['path' => '/path/to/file.pdf'],
        ],
        'headers' => [
            'X-Priority' => '1',
            'X-Mailer' => 'MyApp/1.0',
        ],
    ]
);
```

## Примеры использования

### Регистрация пользователя

```php
function sendWelcomeEmail(Email $email, array $user): void
{
    $html = "
    <html>
    <body style='font-family: Arial, sans-serif;'>
        <h1 style='color: #333;'>Добро пожаловать, {$user['name']}!</h1>
        <p>Спасибо за регистрацию на нашем сайте.</p>
        <p>Ваши данные для входа:</p>
        <ul>
            <li><strong>Email:</strong> {$user['email']}</li>
            <li><strong>Имя пользователя:</strong> {$user['username']}</li>
        </ul>
        <p>
            <a href='https://example.com/activate?token={$user['token']}' 
               style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>
                Активировать аккаунт
            </a>
        </p>
        <p style='color: #666; font-size: 12px;'>
            Если вы не регистрировались на нашем сайте, просто игнорируйте это письмо.
        </p>
    </body>
    </html>
    ";
    
    $email->send(
        $user['email'],
        'Добро пожаловать на наш сайт!',
        $html,
        ['is_html' => true]
    );
}

// Использование
$user = [
    'name' => 'Иван Иванов',
    'email' => 'ivan@example.com',
    'username' => 'ivan',
    'token' => 'abc123...',
];

sendWelcomeEmail($email, $user);
```

### Сброс пароля

```php
function sendPasswordResetEmail(Email $email, string $to, string $resetLink): void
{
    $html = "
    <html>
    <body style='font-family: Arial, sans-serif;'>
        <h2>Сброс пароля</h2>
        <p>Вы запросили сброс пароля.</p>
        <p>Для установки нового пароля перейдите по ссылке:</p>
        <p>
            <a href='{$resetLink}' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>
                Сбросить пароль
            </a>
        </p>
        <p style='color: #666;'>
            Ссылка действительна в течение 24 часов.
        </p>
        <p style='color: #999; font-size: 12px;'>
            Если вы не запрашивали сброс пароля, просто игнорируйте это письмо.
        </p>
    </body>
    </html>
    ";
    
    $email->send(
        $to,
        'Сброс пароля',
        $html,
        [
            'is_html' => true,
            'headers' => [
                'X-Priority' => '1', // Высокий приоритет
            ],
        ]
    );
}

// Использование
sendPasswordResetEmail($email, 'user@example.com', 'https://example.com/reset?token=xyz');
```

### Отправка отчетов

```php
class ReportMailer
{
    private Email $email;
    private MySQL $mysql;
    
    public function __construct(Email $email, MySQL $mysql)
    {
        $this->email = $email;
        $this->mysql = $mysql;
    }
    
    public function sendMonthlyReport(array $recipients): void
    {
        // Генерация отчета
        $stats = $this->mysql->queryOne('
            SELECT 
                COUNT(*) as total_orders,
                SUM(amount) as total_revenue,
                AVG(amount) as avg_order
            FROM orders
            WHERE MONTH(created_at) = MONTH(CURRENT_DATE)
        ');
        
        // Создание HTML
        $html = $this->buildReportHtml($stats);
        
        // Генерация PDF
        $pdfPath = $this->generatePdfReport($stats);
        
        // Отправка
        $this->email->send(
            $recipients,
            'Ежемесячный отчет - ' . date('F Y'),
            $html,
            [
                'is_html' => true,
                'attachments' => [
                    [
                        'path' => $pdfPath,
                        'name' => 'Отчет_' . date('Y_m') . '.pdf',
                    ],
                ],
            ]
        );
        
        // Удалить временный файл
        unlink($pdfPath);
    }
    
    private function buildReportHtml(array $stats): string
    {
        return "
        <html>
        <body>
            <h1>Отчет за " . date('F Y') . "</h1>
            <table border='1' cellpadding='10'>
                <tr>
                    <td><strong>Всего заказов:</strong></td>
                    <td>{$stats['total_orders']}</td>
                </tr>
                <tr>
                    <td><strong>Общая выручка:</strong></td>
                    <td>\$" . number_format($stats['total_revenue'], 2) . "</td>
                </tr>
                <tr>
                    <td><strong>Средний чек:</strong></td>
                    <td>\$" . number_format($stats['avg_order'], 2) . "</td>
                </tr>
            </table>
            <p>Подробный отчет во вложении.</p>
        </body>
        </html>
        ";
    }
    
    private function generatePdfReport(array $stats): string
    {
        // Генерация PDF (упрощенно)
        $pdfPath = '/tmp/report_' . uniqid() . '.pdf';
        // ... код генерации PDF
        return $pdfPath;
    }
}

// Использование
$mailer = new ReportMailer($email, $mysql);
$mailer->sendMonthlyReport([
    'manager@example.com',
    'director@example.com',
]);
```

### Уведомления

```php
class NotificationMailer
{
    private Email $email;
    
    public function __construct(Email $email)
    {
        $this->email = $email;
    }
    
    public function notifyOrderCreated(array $order, string $customerEmail): void
    {
        $html = "
        <h2>Заказ #{$order['id']} оформлен</h2>
        <p>Спасибо за ваш заказ!</p>
        <h3>Детали заказа:</h3>
        <ul>
            <li>Номер заказа: #{$order['id']}</li>
            <li>Дата: {$order['created_at']}</li>
            <li>Сумма: \${$order['total']}</li>
            <li>Статус: {$order['status']}</li>
        </ul>
        <p>Мы свяжемся с вами для подтверждения.</p>
        ";
        
        $this->email->send(
            $customerEmail,
            "Заказ #{$order['id']} оформлен",
            $html,
            ['is_html' => true]
        );
    }
    
    public function notifyOrderShipped(array $order, string $customerEmail): void
    {
        $html = "
        <h2>Заказ #{$order['id']} отправлен</h2>
        <p>Ваш заказ отправлен!</p>
        <p><strong>Трек-номер:</strong> {$order['tracking_number']}</p>
        <p>Ожидаемая дата доставки: {$order['estimated_delivery']}</p>
        ";
        
        $this->email->send(
            $customerEmail,
            "Заказ #{$order['id']} отправлен",
            $html,
            ['is_html' => true]
        );
    }
    
    public function notifyLowStock(string $productName, int $quantity): void
    {
        $this->email->send(
            'warehouse@example.com',
            'Низкий остаток товара',
            "<p>Товар <strong>{$productName}</strong> заканчивается.</p><p>Осталось: {$quantity} шт.</p>",
            [
                'is_html' => true,
                'headers' => [
                    'X-Priority' => '1',
                ],
            ]
        );
    }
}

// Использование
$notifier = new NotificationMailer($email);
$notifier->notifyOrderCreated($order, $customerEmail);
$notifier->notifyLowStock('iPhone 15', 5);
```

### HTML шаблоны

```php
class EmailTemplate
{
    private string $templateDir;
    
    public function __construct(string $templateDir)
    {
        $this->templateDir = $templateDir;
    }
    
    public function render(string $template, array $data = []): string
    {
        $templatePath = $this->templateDir . '/' . $template . '.html';
        
        if (!file_exists($templatePath)) {
            throw new Exception("Template not found: {$template}");
        }
        
        $html = file_get_contents($templatePath);
        
        foreach ($data as $key => $value) {
            $html = str_replace("{{" . $key . "}}", $value, $html);
        }
        
        return $html;
    }
}

// Шаблон: templates/welcome.html
/*
<html>
<body>
    <h1>Привет, {{name}}!</h1>
    <p>{{message}}</p>
    <a href="{{link}}">{{link_text}}</a>
</body>
</html>
*/

// Использование
$templates = new EmailTemplate('./templates');

$html = $templates->render('welcome', [
    'name' => 'Иван',
    'message' => 'Добро пожаловать на наш сайт!',
    'link' => 'https://example.com/dashboard',
    'link_text' => 'Перейти в панель управления',
]);

$email->send('user@example.com', 'Добро пожаловать!', $html, ['is_html' => true]);
```

## API Reference

### Конструктор

```php
public function __construct(array $config = [], ?Logger $logger = null)
```

**Параметры:**
- `$config` (array) - Конфигурация email
- `$logger` (Logger|null) - Опциональный логгер

**Исключения:**
- `EmailValidationException` - Некорректная конфигурация

### send()

```php
public function send(
    string|array $recipients,
    string $subject,
    string $body,
    array $options = []
): void
```

Отправляет email.

**Параметры:**
- `$recipients` - Email адрес(а) получателей
- `$subject` - Тема письма
- `$body` - Содержимое письма
- `$options` - Дополнительные опции

**Опции:**
- `is_html` (bool) - HTML формат
- `cc` (string|array) - Копия
- `bcc` (string|array) - Скрытая копия
- `reply_to` (string) - Reply-To адрес
- `reply_name` (string) - Reply-To имя
- `return_path` (string) - Return-Path адрес
- `attachments` (array) - Вложения
- `headers` (array) - Дополнительные заголовки

## Обработка ошибок

### Исключения

- `EmailException` - Базовое исключение
- `EmailValidationException` - Ошибка валидации

```php
use App\Component\Exception\EmailException;
use App\Component\Exception\EmailValidationException;

try {
    $email->send('user@example.com', 'Subject', 'Body');
} catch (EmailValidationException $e) {
    echo "Ошибка валидации: {$e->getMessage()}\n";
} catch (EmailException $e) {
    echo "Ошибка отправки: {$e->getMessage()}\n";
}
```

## Лучшие практики

1. **Используйте HTML шаблоны** для единообразия

2. **Всегда указывайте Reply-To**:
   ```php
   'reply_to' => 'support@example.com'
   ```

3. **Используйте BCC** для массовых рассылок

4. **Не отправляйте спам** - соблюдайте законы (GDPR, CAN-SPAM)

5. **Логируйте отправку**:
   ```php
   $email = new Email($config, $logger);
   ```

6. **Используйте очереди** для больших объемов

7. **Проверяйте email адреса**:
   ```php
   if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
       throw new Exception('Invalid email');
   }
   ```

8. **Храните пароли безопасно** (environment variables)

## SMTP провайдеры

### Gmail

```json
{
    "smtp": {
        "host": "smtp.gmail.com",
        "port": 587,
        "encryption": "tls",
        "username": "your-email@gmail.com",
        "password": "your-app-password"
    }
}
```

### SendGrid

```json
{
    "smtp": {
        "host": "smtp.sendgrid.net",
        "port": 587,
        "encryption": "tls",
        "username": "apikey",
        "password": "your-sendgrid-api-key"
    }
}
```

### Mailgun

```json
{
    "smtp": {
        "host": "smtp.mailgun.org",
        "port": 587,
        "encryption": "tls",
        "username": "postmaster@your-domain.mailgun.org",
        "password": "your-mailgun-password"
    }
}
```

## Производительность

- Используйте очереди для массовых рассылок
- Настройте retry для надежности
- Используйте BCC вместо множественных отправок
- Кешируйте шаблоны
- Оптимизируйте размер вложений

## Безопасность

1. Не храните пароли в коде
2. Используйте TLS/SSL для SMTP
3. Валидируйте email адреса
4. Санитизируйте HTML контент
5. Ограничивайте размер вложений
6. Проверяйте файлы перед отправкой

## См. также

- [Logger документация](LOGGER.md) - для логирования отправки писем
- [Telegram документация](TELEGRAM.md) - альтернатива для уведомлений
