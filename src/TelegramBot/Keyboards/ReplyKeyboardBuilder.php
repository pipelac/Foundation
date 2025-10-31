<?php

declare(strict_types=1);

namespace App\Component\TelegramBot\Keyboards;

use App\Component\TelegramBot\Exceptions\ValidationException;
use App\Component\TelegramBot\Utils\Validator;

/**
 * Построитель reply клавиатур (обычные кнопки)
 * 
 * Предоставляет fluent API для создания reply клавиатур
 * с текстовыми кнопками, кнопками запроса контакта и локации
 */
class ReplyKeyboardBuilder
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
     * Параметры клавиатуры
     * 
     * @var array<string, mixed>
     */
    private array $params = [];

    /**
     * Добавляет текстовую кнопку в текущий ряд
     *
     * @param string $text Текст на кнопке
     * @return self
     */
    public function addButton(string $text): self
    {
        $this->currentRow[] = ['text' => $text];
        return $this;
    }

    /**
     * Добавляет кнопку запроса контакта
     *
     * @param string $text Текст на кнопке
     * @return self
     */
    public function addContactButton(string $text): self
    {
        $this->currentRow[] = [
            'text' => $text,
            'request_contact' => true,
        ];

        return $this;
    }

    /**
     * Добавляет кнопку запроса местоположения
     *
     * @param string $text Текст на кнопке
     * @return self
     */
    public function addLocationButton(string $text): self
    {
        $this->currentRow[] = [
            'text' => $text,
            'request_location' => true,
        ];

        return $this;
    }

    /**
     * Добавляет кнопку запроса опроса
     *
     * @param string $text Текст на кнопке
     * @param string|null $type Тип опроса: 'quiz' или 'regular' (null = любой)
     * @return self
     */
    public function addPollButton(string $text, ?string $type = null): self
    {
        $button = [
            'text' => $text,
            'request_poll' => $type !== null ? ['type' => $type] : new \stdClass(),
        ];

        $this->currentRow[] = $button;

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
     * @param array<array<string, mixed>|string> $buttons Массив кнопок (строки или массивы)
     * @return self
     */
    public function addRow(array $buttons): self
    {
        if (!empty($this->currentRow)) {
            $this->row();
        }

        $normalizedRow = [];
        foreach ($buttons as $button) {
            $normalizedRow[] = is_string($button) ? ['text' => $button] : $button;
        }

        $this->rows[] = $normalizedRow;

        return $this;
    }

    /**
     * Включает автоматическую подстройку размера клавиатуры
     *
     * @param bool $resize True для включения
     * @return self
     */
    public function resizeKeyboard(bool $resize = true): self
    {
        $this->params['resize_keyboard'] = $resize;
        return $this;
    }

    /**
     * Включает одноразовую клавиатуру (скрывается после использования)
     *
     * @param bool $oneTime True для включения
     * @return self
     */
    public function oneTime(bool $oneTime = true): self
    {
        $this->params['one_time_keyboard'] = $oneTime;
        return $this;
    }

    /**
     * Устанавливает placeholder для поля ввода
     *
     * @param string $placeholder Текст placeholder (макс. 64 символа)
     * @return self
     */
    public function placeholder(string $placeholder): self
    {
        $this->params['input_field_placeholder'] = $placeholder;
        return $this;
    }

    /**
     * Включает селективный режим (показывать только определенным пользователям)
     *
     * @param bool $selective True для включения
     * @return self
     */
    public function selective(bool $selective = true): self
    {
        $this->params['selective'] = $selective;
        return $this;
    }

    /**
     * Устанавливает персистентность клавиатуры
     *
     * @param bool $persistent True если клавиатура должна всегда показываться
     * @return self
     */
    public function persistent(bool $persistent = true): self
    {
        $this->params['is_persistent'] = $persistent;
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
        $this->params = [];

        return $this;
    }

    /**
     * Строит и возвращает структуру reply клавиатуры
     *
     * @return array<string, mixed>
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
                'keyboard',
                []
            );
        }

        Validator::validateReplyKeyboard($this->rows);

        return array_merge($this->params, ['keyboard' => $this->rows]);
    }

    /**
     * Строит клавиатуру и сбрасывает построитель
     *
     * @return array<string, mixed>
     * @throws ValidationException Если клавиатура пуста или некорректна
     */
    public function buildAndReset(): array
    {
        $result = $this->build();
        $this->reset();
        return $result;
    }

    /**
     * Создает простую клавиатуру из массива текстов
     * Каждый элемент становится отдельным рядом с одной кнопкой
     *
     * @param array<string> $buttons Массив текстов кнопок
     * @param bool $resize Автоматически подстраивать размер
     * @param bool $oneTime Одноразовая клавиатура
     * @return array<string, mixed>
     */
    public static function makeSimple(array $buttons, bool $resize = true, bool $oneTime = false): array
    {
        $builder = new self();

        if ($resize) {
            $builder->resizeKeyboard();
        }

        if ($oneTime) {
            $builder->oneTime();
        }

        foreach ($buttons as $text) {
            $builder->addButton($text)->row();
        }

        return $builder->build();
    }

    /**
     * Создает клавиатуру-сетку с указанным количеством кнопок в ряд
     *
     * @param array<string> $buttons Массив текстов кнопок
     * @param int $columns Количество кнопок в ряд
     * @param bool $resize Автоматически подстраивать размер
     * @param bool $oneTime Одноразовая клавиатура
     * @return array<string, mixed>
     */
    public static function makeGrid(array $buttons, int $columns = 2, bool $resize = true, bool $oneTime = false): array
    {
        $builder = new self();

        if ($resize) {
            $builder->resizeKeyboard();
        }

        if ($oneTime) {
            $builder->oneTime();
        }

        $count = 0;
        foreach ($buttons as $text) {
            $builder->addButton($text);
            $count++;

            if ($count >= $columns) {
                $builder->row();
                $count = 0;
            }
        }

        return $builder->build();
    }

    /**
     * Создает команду удаления клавиатуры
     *
     * @param bool $selective Удалить только для определенных пользователей
     * @return array{remove_keyboard: true, selective: bool}
     */
    public static function remove(bool $selective = false): array
    {
        return [
            'remove_keyboard' => true,
            'selective' => $selective,
        ];
    }

    /**
     * Создает команду принудительного ответа
     *
     * @param string|null $placeholder Подсказка в поле ввода
     * @param bool $selective Только для определенных пользователей
     * @return array<string, mixed>
     */
    public static function forceReply(?string $placeholder = null, bool $selective = false): array
    {
        $result = [
            'force_reply' => true,
            'selective' => $selective,
        ];

        if ($placeholder !== null) {
            $result['input_field_placeholder'] = $placeholder;
        }

        return $result;
    }
}
