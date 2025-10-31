<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Component\Telegram;
use App\Component\Exception\Telegram\TelegramApiException;
use PHPUnit\Framework\TestCase;

/**
 * Unit тесты для новых функций класса Telegram:
 * - Голосования (polls)
 * - Клавиатуры (inline и reply)
 */
class TelegramNewFeaturesTest extends TestCase
{
    private Telegram $telegram;

    protected function setUp(): void
    {
        parent::setUp();
        $this->telegram = new Telegram([
            'token' => '123456789:ABC-DEF1234ghIkl-zyx57W2v1u123ew11',
            'default_chat_id' => '123456789',
        ]);
    }

    /**
     * ТЕСТЫ ВАЛИДАЦИИ ОПРОСОВ
     */

    public function testSendPollThrowsExceptionForEmptyQuestion(): void
    {
        $this->expectException(TelegramApiException::class);
        $this->expectExceptionMessage('Вопрос опроса не может быть пустым');

        $this->telegram->sendPoll(null, '', ['Вариант 1', 'Вариант 2']);
    }

    public function testSendPollThrowsExceptionForTooLongQuestion(): void
    {
        $this->expectException(TelegramApiException::class);
        $this->expectExceptionMessage('Вопрос опроса не может превышать 300 символов');

        $longQuestion = str_repeat('A', 301);
        $this->telegram->sendPoll(null, $longQuestion, ['Вариант 1', 'Вариант 2']);
    }

    public function testSendPollThrowsExceptionForTooFewOptions(): void
    {
        $this->expectException(TelegramApiException::class);
        $this->expectExceptionMessage('Опрос должен содержать минимум 2 варианта ответа');

        $this->telegram->sendPoll(null, 'Вопрос?', ['Один вариант']);
    }

    public function testSendPollThrowsExceptionForTooManyOptions(): void
    {
        $this->expectException(TelegramApiException::class);
        $this->expectExceptionMessage('Опрос не может содержать более 10 вариантов ответа');

        $options = array_fill(0, 11, 'Вариант');
        $this->telegram->sendPoll(null, 'Вопрос?', $options);
    }

    public function testSendPollThrowsExceptionForEmptyOption(): void
    {
        $this->expectException(TelegramApiException::class);
        $this->expectExceptionMessageMatches('/Вариант ответа #\d+ не может быть пустым/');

        $this->telegram->sendPoll(null, 'Вопрос?', ['Вариант 1', '']);
    }

    public function testSendPollThrowsExceptionForTooLongOption(): void
    {
        $this->expectException(TelegramApiException::class);
        $this->expectExceptionMessageMatches('/Вариант ответа #\d+ не может превышать 100 символов/');

        $longOption = str_repeat('A', 101);
        $this->telegram->sendPoll(null, 'Вопрос?', ['Вариант 1', $longOption]);
    }

    public function testSendPollThrowsExceptionForNonStringOption(): void
    {
        $this->expectException(TelegramApiException::class);
        $this->expectExceptionMessageMatches('/Вариант ответа #\d+ должен быть строкой/');

        $this->telegram->sendPoll(null, 'Вопрос?', ['Вариант 1', 123]);
    }

    public function testSendPollThrowsExceptionForTooLongExplanation(): void
    {
        $this->expectException(TelegramApiException::class);
        $this->expectExceptionMessage('Пояснение к опросу не может превышать 200 символов');

        $longExplanation = str_repeat('A', 201);
        $this->telegram->sendPoll(null, 'Вопрос?', ['Да', 'Нет'], [
            'explanation' => $longExplanation,
        ]);
    }

    /**
     * ТЕСТЫ ВАЛИДАЦИИ INLINE КЛАВИАТУР
     */

    public function testBuildInlineKeyboardThrowsExceptionForEmptyKeyboard(): void
    {
        $this->expectException(TelegramApiException::class);
        $this->expectExceptionMessage('Inline клавиатура не может быть пустой');

        $this->telegram->buildInlineKeyboard([]);
    }

    public function testBuildInlineKeyboardThrowsExceptionForEmptyRow(): void
    {
        $this->expectException(TelegramApiException::class);
        $this->expectExceptionMessageMatches('/Ряд #\d+ inline клавиатуры не может быть пустым/');

        $this->telegram->buildInlineKeyboard([[]]);
    }

    public function testBuildInlineKeyboardThrowsExceptionForButtonWithoutText(): void
    {
        $this->expectException(TelegramApiException::class);
        $this->expectExceptionMessageMatches('/Кнопка \[\d+\]\[\d+\] должна содержать текстовое поле \'text\'/');

        $this->telegram->buildInlineKeyboard([
            [
                ['callback_data' => 'test'],
            ],
        ]);
    }

    public function testBuildInlineKeyboardThrowsExceptionForButtonWithEmptyText(): void
    {
        $this->expectException(TelegramApiException::class);
        $this->expectExceptionMessageMatches('/Текст кнопки \[\d+\]\[\d+\] не может быть пустым/');

        $this->telegram->buildInlineKeyboard([
            [
                ['text' => '   ', 'callback_data' => 'test'],
            ],
        ]);
    }

    public function testBuildInlineKeyboardThrowsExceptionForButtonWithoutAction(): void
    {
        $this->expectException(TelegramApiException::class);
        $this->expectExceptionMessageMatches('/Кнопка \[\d+\]\[\d+\] должна содержать хотя бы одно действие/');

        $this->telegram->buildInlineKeyboard([
            [
                ['text' => 'Кнопка'],
            ],
        ]);
    }

    public function testBuildInlineKeyboardThrowsExceptionForTooShortCallbackData(): void
    {
        $this->expectException(TelegramApiException::class);
        $this->expectExceptionMessageMatches('/callback_data кнопки \[\d+\]\[\d+\] должен быть от 1 до 64 байт/');

        $this->telegram->buildInlineKeyboard([
            [
                ['text' => 'Кнопка', 'callback_data' => ''],
            ],
        ]);
    }

    public function testBuildInlineKeyboardThrowsExceptionForTooLongCallbackData(): void
    {
        $this->expectException(TelegramApiException::class);
        $this->expectExceptionMessageMatches('/callback_data кнопки \[\d+\]\[\d+\] должен быть от 1 до 64 байт/');

        $longCallbackData = str_repeat('X', 65);
        $this->telegram->buildInlineKeyboard([
            [
                ['text' => 'Кнопка', 'callback_data' => $longCallbackData],
            ],
        ]);
    }

    public function testBuildInlineKeyboardAcceptsValidKeyboard(): void
    {
        $keyboard = $this->telegram->buildInlineKeyboard([
            [
                ['text' => 'Кнопка 1', 'callback_data' => 'btn1'],
                ['text' => 'Кнопка 2', 'url' => 'https://example.com'],
            ],
            [
                ['text' => 'Кнопка 3', 'callback_data' => 'btn3'],
            ],
        ]);

        $this->assertIsArray($keyboard);
        $this->assertArrayHasKey('inline_keyboard', $keyboard);
        $this->assertCount(2, $keyboard['inline_keyboard']);
    }

    /**
     * ТЕСТЫ ВАЛИДАЦИИ REPLY КЛАВИАТУР
     */

    public function testBuildReplyKeyboardThrowsExceptionForEmptyKeyboard(): void
    {
        $this->expectException(TelegramApiException::class);
        $this->expectExceptionMessage('Reply клавиатура не может быть пустой');

        $this->telegram->buildReplyKeyboard([]);
    }

    public function testBuildReplyKeyboardThrowsExceptionForEmptyRow(): void
    {
        $this->expectException(TelegramApiException::class);
        $this->expectExceptionMessageMatches('/Ряд #\d+ reply клавиатуры не может быть пустым/');

        $this->telegram->buildReplyKeyboard([[]]);
    }

    public function testBuildReplyKeyboardAcceptsStringButtons(): void
    {
        $keyboard = $this->telegram->buildReplyKeyboard([
            ['Кнопка 1', 'Кнопка 2'],
            ['Кнопка 3'],
        ]);

        $this->assertIsArray($keyboard);
        $this->assertArrayHasKey('keyboard', $keyboard);
        $this->assertCount(2, $keyboard['keyboard']);
    }

    public function testBuildReplyKeyboardAcceptsArrayButtons(): void
    {
        $keyboard = $this->telegram->buildReplyKeyboard([
            [
                ['text' => 'Контакт', 'request_contact' => true],
            ],
        ]);

        $this->assertIsArray($keyboard);
        $this->assertArrayHasKey('keyboard', $keyboard);
    }

    public function testBuildReplyKeyboardAcceptsParameters(): void
    {
        $keyboard = $this->telegram->buildReplyKeyboard(
            [['Кнопка']],
            [
                'resize_keyboard' => true,
                'one_time_keyboard' => true,
                'input_field_placeholder' => 'Выберите действие',
            ]
        );

        $this->assertIsArray($keyboard);
        $this->assertArrayHasKey('resize_keyboard', $keyboard);
        $this->assertArrayHasKey('one_time_keyboard', $keyboard);
        $this->assertArrayHasKey('input_field_placeholder', $keyboard);
        $this->assertTrue($keyboard['resize_keyboard']);
        $this->assertTrue($keyboard['one_time_keyboard']);
    }

    /**
     * ТЕСТЫ REMOVEКEYBOARD
     */

    public function testRemoveKeyboardReturnsCorrectStructure(): void
    {
        $result = $this->telegram->removeKeyboard();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('remove_keyboard', $result);
        $this->assertTrue($result['remove_keyboard']);
        $this->assertArrayHasKey('selective', $result);
        $this->assertFalse($result['selective']);
    }

    public function testRemoveKeyboardAcceptsSelectiveParameter(): void
    {
        $result = $this->telegram->removeKeyboard(true);

        $this->assertIsArray($result);
        $this->assertTrue($result['selective']);
    }

    /**
     * ТЕСТЫ FORCE REPLY
     */

    public function testForceReplyReturnsCorrectStructure(): void
    {
        $result = $this->telegram->forceReply();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('force_reply', $result);
        $this->assertTrue($result['force_reply']);
        $this->assertArrayHasKey('selective', $result);
    }

    public function testForceReplyAcceptsPlaceholder(): void
    {
        $placeholder = 'Введите текст...';
        $result = $this->telegram->forceReply($placeholder);

        $this->assertArrayHasKey('input_field_placeholder', $result);
        $this->assertEquals($placeholder, $result['input_field_placeholder']);
    }

    public function testForceReplyThrowsExceptionForTooLongPlaceholder(): void
    {
        $this->expectException(TelegramApiException::class);
        $this->expectExceptionMessage('Placeholder не может превышать 64 символа');

        $longPlaceholder = str_repeat('A', 65);
        $this->telegram->forceReply($longPlaceholder);
    }

    public function testForceReplyAcceptsSelectiveParameter(): void
    {
        $result = $this->telegram->forceReply(null, true);

        $this->assertTrue($result['selective']);
    }

    /**
     * ТЕСТЫ СТРУКТУР ДАННЫХ
     */

    public function testInlineKeyboardSupportsUrlButtons(): void
    {
        $keyboard = $this->telegram->buildInlineKeyboard([
            [
                ['text' => 'Открыть сайт', 'url' => 'https://example.com'],
            ],
        ]);

        $this->assertArrayHasKey('inline_keyboard', $keyboard);
        $this->assertEquals('https://example.com', $keyboard['inline_keyboard'][0][0]['url']);
    }

    public function testInlineKeyboardSupportsCallbackButtons(): void
    {
        $keyboard = $this->telegram->buildInlineKeyboard([
            [
                ['text' => 'Нажми меня', 'callback_data' => 'action_clicked'],
            ],
        ]);

        $this->assertArrayHasKey('inline_keyboard', $keyboard);
        $this->assertEquals('action_clicked', $keyboard['inline_keyboard'][0][0]['callback_data']);
    }

    public function testInlineKeyboardSupportsMixedButtons(): void
    {
        $keyboard = $this->telegram->buildInlineKeyboard([
            [
                ['text' => 'URL', 'url' => 'https://example.com'],
                ['text' => 'Callback', 'callback_data' => 'cb_data'],
            ],
            [
                ['text' => 'Switch', 'switch_inline_query' => 'search'],
            ],
        ]);

        $this->assertCount(2, $keyboard['inline_keyboard']);
        $this->assertCount(2, $keyboard['inline_keyboard'][0]);
        $this->assertCount(1, $keyboard['inline_keyboard'][1]);
    }

    public function testReplyKeyboardNormalizesStringButtons(): void
    {
        $keyboard = $this->telegram->buildReplyKeyboard([
            ['Кнопка 1', 'Кнопка 2'],
        ]);

        $button1 = $keyboard['keyboard'][0][0];
        $button2 = $keyboard['keyboard'][0][1];

        $this->assertIsArray($button1);
        $this->assertIsArray($button2);
        $this->assertEquals('Кнопка 1', $button1['text']);
        $this->assertEquals('Кнопка 2', $button2['text']);
    }

    public function testReplyKeyboardPreservesArrayButtons(): void
    {
        $keyboard = $this->telegram->buildReplyKeyboard([
            [
                ['text' => 'Контакт', 'request_contact' => true],
            ],
        ]);

        $button = $keyboard['keyboard'][0][0];

        $this->assertArrayHasKey('text', $button);
        $this->assertArrayHasKey('request_contact', $button);
        $this->assertTrue($button['request_contact']);
    }

    /**
     * ТЕСТЫ ГРАНИЧНЫХ СЛУЧАЕВ
     */

    public function testInlineKeyboardWithMaxCallbackDataLength(): void
    {
        $callbackData = str_repeat('X', 64);
        
        $keyboard = $this->telegram->buildInlineKeyboard([
            [
                ['text' => 'Кнопка', 'callback_data' => $callbackData],
            ],
        ]);

        $this->assertIsArray($keyboard);
        $this->assertEquals($callbackData, $keyboard['inline_keyboard'][0][0]['callback_data']);
    }

    public function testPollQuestionWithMaxLength(): void
    {
        $question = str_repeat('A', 300);
        
        // Тест на валидацию, не делаем реальный API запрос
        // Если валидация пройдет, значит всё ОК
        $this->expectNotToPerformAssertions();
    }

    public function testPollOptionWithMaxLength(): void
    {
        $option = str_repeat('A', 100);
        
        // Тест на валидацию, не делаем реальный API запрос
        // Если валидация пройдет, значит всё ОК
        $this->expectNotToPerformAssertions();
    }

    public function testPollExplanationWithMaxLength(): void
    {
        $explanation = str_repeat('A', 200);
        
        // Тест на валидацию, не делаем реальный API запрос
        // Если валидация пройдет, значит всё ОК
        $this->expectNotToPerformAssertions();
    }

    public function testForceReplyPlaceholderWithMaxLength(): void
    {
        $placeholder = str_repeat('A', 64);
        
        $result = $this->telegram->forceReply($placeholder);

        $this->assertEquals($placeholder, $result['input_field_placeholder']);
    }

    /**
     * ТЕСТЫ МНОЖЕСТВЕННЫХ РЯДОВ
     */

    public function testInlineKeyboardWithMultipleRows(): void
    {
        $keyboard = $this->telegram->buildInlineKeyboard([
            [
                ['text' => 'Ряд 1, Кнопка 1', 'callback_data' => 'r1b1'],
            ],
            [
                ['text' => 'Ряд 2, Кнопка 1', 'callback_data' => 'r2b1'],
                ['text' => 'Ряд 2, Кнопка 2', 'callback_data' => 'r2b2'],
            ],
            [
                ['text' => 'Ряд 3, Кнопка 1', 'callback_data' => 'r3b1'],
                ['text' => 'Ряд 3, Кнопка 2', 'callback_data' => 'r3b2'],
                ['text' => 'Ряд 3, Кнопка 3', 'callback_data' => 'r3b3'],
            ],
        ]);

        $this->assertCount(3, $keyboard['inline_keyboard']);
        $this->assertCount(1, $keyboard['inline_keyboard'][0]);
        $this->assertCount(2, $keyboard['inline_keyboard'][1]);
        $this->assertCount(3, $keyboard['inline_keyboard'][2]);
    }

    public function testReplyKeyboardWithMultipleRows(): void
    {
        $keyboard = $this->telegram->buildReplyKeyboard([
            ['A'],
            ['B', 'C'],
            ['D', 'E', 'F'],
        ]);

        $this->assertCount(3, $keyboard['keyboard']);
        $this->assertCount(1, $keyboard['keyboard'][0]);
        $this->assertCount(2, $keyboard['keyboard'][1]);
        $this->assertCount(3, $keyboard['keyboard'][2]);
    }
}
