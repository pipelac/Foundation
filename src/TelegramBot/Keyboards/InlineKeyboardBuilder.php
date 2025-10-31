<?php

declare(strict_types=1);

namespace App\Component\TelegramBot\Keyboards;

use App\Component\TelegramBot\Exceptions\ValidationException;
use App\Component\TelegramBot\Utils\Validator;

/**
 * Построитель inline клавиатур (callback кнопки)
 * 
 * Предоставляет fluent API для создания inline клавиатур
 * с кнопками callback, url, web_app и другими типами
 */
class InlineKeyboardBuilder
{
    /**
     * Массив рядов кнопок
     * 
     * @var array<array<array<string, mixed>>>
     */
    private array $rows = [];

    /**
     * Текущий ряд кнопок
     * 
     * @var array<array<string, mixed>>
     */
    private array $currentRow = [];

    /**
     * Добавляет кнопку с callback в текущий ряд
     *
     * @param string $text Текст на кнопке
     * @param string $callbackData Данные для callback (макс. 64 байта)
     * @return self
     * @throws ValidationException Если callback_data превышает лимит
     */
    public function addCallbackButton(string $text, string $callbackData): self
    {
        Validator::validateCallbackData($callbackData);

        $this->currentRow[] = [
            'text' => $text,
            'callback_data' => $callbackData,
        ];

        return $this;
    }

    /**
     * Добавляет кнопку с URL в текущий ряд
     *
     * @param string $text Текст на кнопке
     * @param string $url URL для открытия
     * @return self
     */
    public function addUrlButton(string $text, string $url): self
    {
        $this->currentRow[] = [
            'text' => $text,
            'url' => $url,
        ];

        return $this;
    }

    /**
     * Добавляет кнопку для запуска Web App
     *
     * @param string $text Текст на кнопке
     * @param string $webAppUrl URL Web App
     * @return self
     */
    public function addWebAppButton(string $text, string $webAppUrl): self
    {
        $this->currentRow[] = [
            'text' => $text,
            'web_app' => ['url' => $webAppUrl],
        ];

        return $this;
    }

    /**
     * Добавляет кнопку для переключения на inline режим
     *
     * @param string $text Текст на кнопке
     * @param string $query Inline запрос
     * @return self
     */
    public function addSwitchInlineButton(string $text, string $query = ''): self
    {
        $this->currentRow[] = [
            'text' => $text,
            'switch_inline_query' => $query,
        ];

        return $this;
    }

    /**
     * Добавляет кнопку для переключения на inline режим в текущем чате
     *
     * @param string $text Текст на кнопке
     * @param string $query Inline запрос
     * @return self
     */
    public function addSwitchInlineCurrentChatButton(string $text, string $query = ''): self
    {
        $this->currentRow[] = [
            'text' => $text,
            'switch_inline_query_current_chat' => $query,
        ];

        return $this;
    }

    /**
     * Добавляет кнопку для авторизации через Telegram Login
     *
     * @param string $text Текст на кнопке
     * @param string $loginUrl URL для авторизации
     * @param array<string, mixed> $options Дополнительные опции
     * @return self
     */
    public function addLoginButton(string $text, string $loginUrl, array $options = []): self
    {
        $loginData = array_merge(['url' => $loginUrl], $options);

        $this->currentRow[] = [
            'text' => $text,
            'login_url' => $loginData,
        ];

        return $this;
    }

    /**
     * Завершает текущий ряд и начинает новый
     *
     * @return self
     */
    public function row(): self
    {
        if (!empty($this->currentRow)) {
            $this->rows[] = $this->currentRow;
            $this->currentRow = [];
        }

        return $this;
    }

    /**
     * Добавляет целый ряд кнопок
     *
     * @param array<array<string, mixed>> $buttons Массив кнопок для ряда
     * @return self
     */
    public function addRow(array $buttons): self
    {
        if (!empty($this->currentRow)) {
            $this->row();
        }

        $this->rows[] = $buttons;

        return $this;
    }

    /**
     * Сбрасывает построитель в исходное состояние
     *
     * @return self
     */
    public function reset(): self
    {
        $this->rows = [];
        $this->currentRow = [];

        return $this;
    }

    /**
     * Строит и возвращает структуру inline клавиатуры
     *
     * @return array{inline_keyboard: array<array<array<string, mixed>>>}
     * @throws ValidationException Если клавиатура пуста или некорректна
     */
    public function build(): array
    {
        if (!empty($this->currentRow)) {
            $this->row();
        }

        if (empty($this->rows)) {
            throw new ValidationException(
                'Клавиатура пуста. Добавьте хотя бы одну кнопку',
                'inline_keyboard',
                []
            );
        }

        Validator::validateInlineKeyboard($this->rows);

        return ['inline_keyboard' => $this->rows];
    }

    /**
     * Строит клавиатуру и сбрасывает построитель
     *
     * @return array{inline_keyboard: array<array<array<string, mixed>>>}
     * @throws ValidationException Если клавиатура пуста или некорректна
     */
    public function buildAndReset(): array
    {
        $result = $this->build();
        $this->reset();
        return $result;
    }

    /**
     * Создает простую клавиатуру из массива кнопок
     * Каждый элемент становится отдельным рядом с одной кнопкой
     *
     * @param array<string, string> $buttons Массив [text => callback_data]
     * @return array{inline_keyboard: array<array<array<string, mixed>>>}
     */
    public static function makeSimple(array $buttons): array
    {
        $builder = new self();

        foreach ($buttons as $text => $callbackData) {
            $builder->addCallbackButton($text, $callbackData)->row();
        }

        return $builder->build();
    }

    /**
     * Создает клавиатуру-сетку с указанным количеством кнопок в ряд
     *
     * @param array<string, string> $buttons Массив [text => callback_data]
     * @param int $columns Количество кнопок в ряд
     * @return array{inline_keyboard: array<array<array<string, mixed>>>}
     */
    public static function makeGrid(array $buttons, int $columns = 2): array
    {
        $builder = new self();
        $count = 0;

        foreach ($buttons as $text => $callbackData) {
            $builder->addCallbackButton($text, $callbackData);
            $count++;

            if ($count >= $columns) {
                $builder->row();
                $count = 0;
            }
        }

        return $builder->build();
    }
}
