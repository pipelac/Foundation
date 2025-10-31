<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Component\TelegramBot\Core\AccessControl;
use App\Component\TelegramBot\Exceptions\AccessControlException;

/**
 * Тесты для системы контроля доступа TelegramBot
 */
class TelegramBotAccessControlTest extends TestCase
{
    private string $tempDir;
    private string $configPath;
    private string $usersPath;
    private string $rolesPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Создаем временную директорию для тестовых конфигов
        $this->tempDir = sys_get_temp_dir() . '/telegram_bot_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);

        $this->configPath = $this->tempDir . '/access_control.json';
        $this->usersPath = $this->tempDir . '/users.json';
        $this->rolesPath = $this->tempDir . '/roles.json';
    }

    protected function tearDown(): void
    {
        // Удаляем временные файлы
        if (file_exists($this->configPath)) {
            unlink($this->configPath);
        }
        if (file_exists($this->usersPath)) {
            unlink($this->usersPath);
        }
        if (file_exists($this->rolesPath)) {
            unlink($this->rolesPath);
        }
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }

        parent::tearDown();
    }

    /**
     * Создает тестовую конфигурацию
     */
    private function createTestConfig(bool $enabled = true): void
    {
        $config = [
            'enabled' => $enabled,
            'users_file' => $this->usersPath,
            'roles_file' => $this->rolesPath,
            'default_role' => 'default',
            'access_denied_message' => 'Access denied',
        ];

        file_put_contents($this->configPath, json_encode($config));
    }

    /**
     * Создает тестовых пользователей
     */
    private function createTestUsers(): void
    {
        $users = [
            'default' => [
                'first_name' => 'Guest',
                'role' => 'default',
            ],
            '123456' => [
                'first_name' => 'Admin',
                'role' => 'admin',
            ],
            '789012' => [
                'first_name' => 'User',
                'role' => 'user',
            ],
        ];

        file_put_contents($this->usersPath, json_encode($users));
    }

    /**
     * Создает тестовые роли
     */
    private function createTestRoles(): void
    {
        $roles = [
            'default' => [
                'commands' => ['/start', '/help'],
            ],
            'user' => [
                'commands' => ['/start', '/help', '/profile'],
            ],
            'admin' => [
                'commands' => ['/start', '/help', '/profile', '/admin', '/settings'],
            ],
        ];

        file_put_contents($this->rolesPath, json_encode($roles));
    }

    public function testConstructorWithValidConfig(): void
    {
        $this->createTestConfig(true);
        $this->createTestUsers();
        $this->createTestRoles();

        $accessControl = new AccessControl($this->configPath);

        $this->assertTrue($accessControl->isEnabled());
    }

    public function testConstructorWithDisabledConfig(): void
    {
        $this->createTestConfig(false);

        $accessControl = new AccessControl($this->configPath);

        $this->assertFalse($accessControl->isEnabled());
    }

    public function testConstructorThrowsExceptionWhenConfigNotFound(): void
    {
        $this->expectException(AccessControlException::class);
        $this->expectExceptionMessage('Конфигурационный файл не найден');

        new AccessControl('/nonexistent/config.json');
    }

    public function testCheckAccessWhenDisabled(): void
    {
        $this->createTestConfig(false);

        $accessControl = new AccessControl($this->configPath);

        // Когда отключено - все команды доступны
        $this->assertTrue($accessControl->checkAccess(123456, '/admin'));
        $this->assertTrue($accessControl->checkAccess(999999, '/any_command'));
    }

    public function testCheckAccessForAdmin(): void
    {
        $this->createTestConfig(true);
        $this->createTestUsers();
        $this->createTestRoles();

        $accessControl = new AccessControl($this->configPath);

        // Админ имеет доступ к админским командам
        $this->assertTrue($accessControl->checkAccess(123456, '/admin'));
        $this->assertTrue($accessControl->checkAccess(123456, '/settings'));
        $this->assertTrue($accessControl->checkAccess(123456, '/start'));
    }

    public function testCheckAccessForRegularUser(): void
    {
        $this->createTestConfig(true);
        $this->createTestUsers();
        $this->createTestRoles();

        $accessControl = new AccessControl($this->configPath);

        // Обычный пользователь имеет доступ к своим командам
        $this->assertTrue($accessControl->checkAccess(789012, '/profile'));
        $this->assertTrue($accessControl->checkAccess(789012, '/start'));

        // Но не к админским
        $this->assertFalse($accessControl->checkAccess(789012, '/admin'));
        $this->assertFalse($accessControl->checkAccess(789012, '/settings'));
    }

    public function testCheckAccessForUnknownUser(): void
    {
        $this->createTestConfig(true);
        $this->createTestUsers();
        $this->createTestRoles();

        $accessControl = new AccessControl($this->configPath);

        // Неизвестный пользователь получает роль по умолчанию
        $this->assertTrue($accessControl->checkAccess(999999, '/start'));
        $this->assertTrue($accessControl->checkAccess(999999, '/help'));
        $this->assertFalse($accessControl->checkAccess(999999, '/admin'));
    }

    public function testNormalizeCommand(): void
    {
        $this->createTestConfig(true);
        $this->createTestUsers();
        $this->createTestRoles();

        $accessControl = new AccessControl($this->configPath);

        // Команды с / и без должны работать одинаково
        $this->assertTrue($accessControl->checkAccess(123456, 'admin'));
        $this->assertTrue($accessControl->checkAccess(123456, '/admin'));
    }

    public function testGetUserRole(): void
    {
        $this->createTestConfig(true);
        $this->createTestUsers();
        $this->createTestRoles();

        $accessControl = new AccessControl($this->configPath);

        $this->assertEquals('admin', $accessControl->getUserRole(123456));
        $this->assertEquals('user', $accessControl->getUserRole(789012));
        $this->assertEquals('default', $accessControl->getUserRole(999999));
    }

    public function testGetUserInfo(): void
    {
        $this->createTestConfig(true);
        $this->createTestUsers();
        $this->createTestRoles();

        $accessControl = new AccessControl($this->configPath);

        $userInfo = $accessControl->getUserInfo(123456);
        $this->assertIsArray($userInfo);
        $this->assertEquals('Admin', $userInfo['first_name']);
        $this->assertEquals('admin', $userInfo['role']);

        // Неизвестный пользователь
        $unknownInfo = $accessControl->getUserInfo(999999);
        $this->assertIsArray($unknownInfo);
        $this->assertEquals('default', $unknownInfo['role']);
    }

    public function testGetAllowedCommands(): void
    {
        $this->createTestConfig(true);
        $this->createTestUsers();
        $this->createTestRoles();

        $accessControl = new AccessControl($this->configPath);

        $adminCommands = $accessControl->getAllowedCommands(123456);
        $this->assertIsArray($adminCommands);
        $this->assertContains('/admin', $adminCommands);
        $this->assertContains('/settings', $adminCommands);

        $userCommands = $accessControl->getAllowedCommands(789012);
        $this->assertContains('/profile', $userCommands);
        $this->assertNotContains('/admin', $userCommands);
    }

    public function testIsUserRegistered(): void
    {
        $this->createTestConfig(true);
        $this->createTestUsers();
        $this->createTestRoles();

        $accessControl = new AccessControl($this->configPath);

        $this->assertTrue($accessControl->isUserRegistered(123456));
        $this->assertTrue($accessControl->isUserRegistered(789012));
        $this->assertFalse($accessControl->isUserRegistered(999999));
    }

    public function testGetAllRoles(): void
    {
        $this->createTestConfig(true);
        $this->createTestUsers();
        $this->createTestRoles();

        $accessControl = new AccessControl($this->configPath);

        $roles = $accessControl->getAllRoles();
        $this->assertIsArray($roles);
        $this->assertContains('default', $roles);
        $this->assertContains('user', $roles);
        $this->assertContains('admin', $roles);
    }

    public function testGetRoleInfo(): void
    {
        $this->createTestConfig(true);
        $this->createTestUsers();
        $this->createTestRoles();

        $accessControl = new AccessControl($this->configPath);

        $adminRole = $accessControl->getRoleInfo('admin');
        $this->assertIsArray($adminRole);
        $this->assertArrayHasKey('commands', $adminRole);
        $this->assertIsArray($adminRole['commands']);

        $nonexistentRole = $accessControl->getRoleInfo('nonexistent');
        $this->assertNull($nonexistentRole);
    }

    public function testGetAccessDeniedMessage(): void
    {
        $this->createTestConfig(true);
        $this->createTestUsers();
        $this->createTestRoles();

        $accessControl = new AccessControl($this->configPath);

        $message = $accessControl->getAccessDeniedMessage();
        $this->assertEquals('Access denied', $message);
    }

    public function testReload(): void
    {
        $this->createTestConfig(false);

        $accessControl = new AccessControl($this->configPath);
        $this->assertFalse($accessControl->isEnabled());

        // Изменяем конфиг
        $this->createTestConfig(true);
        $this->createTestUsers();
        $this->createTestRoles();

        // Перезагружаем
        $accessControl->reload($this->configPath);
        $this->assertTrue($accessControl->isEnabled());
    }

    public function testInvalidJsonInConfigThrowsException(): void
    {
        file_put_contents($this->configPath, '{invalid json}');

        $this->expectException(AccessControlException::class);
        $this->expectExceptionMessage('Ошибка парсинга JSON');

        new AccessControl($this->configPath);
    }

    public function testMissingRequiredFieldsThrowsException(): void
    {
        $config = [
            'enabled' => true,
            // Отсутствуют users_file и roles_file
        ];

        file_put_contents($this->configPath, json_encode($config));

        $this->expectException(AccessControlException::class);
        $this->expectExceptionMessage('не указаны users_file или roles_file');

        new AccessControl($this->configPath);
    }

    public function testMissingUsersFileThrowsException(): void
    {
        $config = [
            'enabled' => true,
            'users_file' => '/nonexistent/users.json',
            'roles_file' => $this->rolesPath,
            'default_role' => 'default',
        ];

        file_put_contents($this->configPath, json_encode($config));

        $this->expectException(AccessControlException::class);
        $this->expectExceptionMessage('Файл не найден');

        new AccessControl($this->configPath);
    }

    public function testCanIgnoreReconstructionModeForAdmin(): void
    {
        $this->createTestConfig(true);
        $this->createTestUsers();
        
        // Создаем роли с поддержкой reconstructionModeIgnore
        $roles = [
            'default' => [
                'commands' => ['/start'],
                'reconstructionModeIgnore' => 'no',
            ],
            'admin' => [
                'commands' => ['/start', '/admin'],
                'reconstructionModeIgnore' => 'yes',
            ],
            'user' => [
                'commands' => ['/start'],
                'reconstructionModeIgnore' => 'no',
            ],
        ];
        file_put_contents($this->rolesPath, json_encode($roles));

        $accessControl = new AccessControl($this->configPath);

        // Админ может работать в режиме профилактики
        $this->assertTrue($accessControl->canIgnoreReconstructionMode(123456));
        
        // Обычный пользователь не может
        $this->assertFalse($accessControl->canIgnoreReconstructionMode(789012));
        
        // Неизвестный пользователь не может
        $this->assertFalse($accessControl->canIgnoreReconstructionMode(999999));
    }

    public function testCanIgnoreReconstructionModeWhenDisabled(): void
    {
        $this->createTestConfig(false);

        $accessControl = new AccessControl($this->configPath);

        // Когда контроль доступа выключен, все могут работать
        $this->assertTrue($accessControl->canIgnoreReconstructionMode(123456));
        $this->assertTrue($accessControl->canIgnoreReconstructionMode(999999));
    }

    public function testShouldDisableSoundNotificationInRange(): void
    {
        $this->createTestConfig(true);
        $this->createTestUsers();
        
        // Создаем роли с поддержкой disable_sound_notification
        $roles = [
            'default' => [
                'commands' => ['/start'],
                'disable_sound_notification' => null,
            ],
            'admin' => [
                'commands' => ['/start', '/admin'],
                'disable_sound_notification' => '22:00-09:00',
            ],
            'user' => [
                'commands' => ['/start'],
                'disable_sound_notification' => '23:00-08:00',
            ],
        ];
        file_put_contents($this->rolesPath, json_encode($roles));

        $accessControl = new AccessControl($this->configPath);

        // Тест в диапазоне (23:30)
        $time = new \DateTime('2024-01-01 23:30:00');
        $this->assertTrue($accessControl->shouldDisableSoundNotification(123456, $time));
        
        // Тест в диапазоне (08:00)
        $time = new \DateTime('2024-01-01 08:00:00');
        $this->assertTrue($accessControl->shouldDisableSoundNotification(123456, $time));
        
        // Тест вне диапазона (12:00)
        $time = new \DateTime('2024-01-01 12:00:00');
        $this->assertFalse($accessControl->shouldDisableSoundNotification(123456, $time));
    }

    public function testShouldDisableSoundNotificationForRoleWithoutRange(): void
    {
        $this->createTestConfig(true);
        $this->createTestUsers();
        
        $roles = [
            'default' => [
                'commands' => ['/start'],
                'disable_sound_notification' => null,
            ],
            'admin' => [
                'commands' => ['/start'],
            ],
        ];
        file_put_contents($this->rolesPath, json_encode($roles));

        $accessControl = new AccessControl($this->configPath);

        // Роль без диапазона - всегда false
        $time = new \DateTime('2024-01-01 23:00:00');
        $this->assertFalse($accessControl->shouldDisableSoundNotification(123456, $time));
    }

    public function testShouldDisableSoundNotificationWhenDisabled(): void
    {
        $this->createTestConfig(false);

        $accessControl = new AccessControl($this->configPath);

        // Когда контроль доступа выключен - всегда false
        $time = new \DateTime('2024-01-01 23:00:00');
        $this->assertFalse($accessControl->shouldDisableSoundNotification(123456, $time));
    }

    public function testGetReconstructionModeIgnore(): void
    {
        $this->createTestConfig(true);
        $this->createTestUsers();
        
        $roles = [
            'default' => [
                'commands' => ['/start'],
                'reconstructionModeIgnore' => 'no',
            ],
            'admin' => [
                'commands' => ['/start'],
                'reconstructionModeIgnore' => 'yes',
            ],
            'user' => [
                'commands' => ['/start'],
            ],
        ];
        file_put_contents($this->rolesPath, json_encode($roles));

        $accessControl = new AccessControl($this->configPath);

        $this->assertEquals('yes', $accessControl->getReconstructionModeIgnore(123456));
        $this->assertEquals('no', $accessControl->getReconstructionModeIgnore(789012));
    }

    public function testGetDisableSoundNotification(): void
    {
        $this->createTestConfig(true);
        $this->createTestUsers();
        
        $roles = [
            'default' => [
                'commands' => ['/start'],
                'disable_sound_notification' => null,
            ],
            'admin' => [
                'commands' => ['/start'],
                'disable_sound_notification' => '22:00-09:00',
            ],
            'user' => [
                'commands' => ['/start'],
                'disable_sound_notification' => '23:00-08:00',
            ],
        ];
        file_put_contents($this->rolesPath, json_encode($roles));

        $accessControl = new AccessControl($this->configPath);

        $this->assertEquals('22:00-09:00', $accessControl->getDisableSoundNotification(123456));
        $this->assertEquals('23:00-08:00', $accessControl->getDisableSoundNotification(789012));
    }

    public function testTimeRangeAcrossMidnight(): void
    {
        $this->createTestConfig(true);
        $this->createTestUsers();
        
        $roles = [
            'admin' => [
                'commands' => ['/start'],
                'disable_sound_notification' => '22:00-09:00',
            ],
        ];
        file_put_contents($this->rolesPath, json_encode($roles));

        $accessControl = new AccessControl($this->configPath);

        // 22:30 - в диапазоне
        $time = new \DateTime('2024-01-01 22:30:00');
        $this->assertTrue($accessControl->shouldDisableSoundNotification(123456, $time));
        
        // 02:00 - в диапазоне (после полуночи)
        $time = new \DateTime('2024-01-01 02:00:00');
        $this->assertTrue($accessControl->shouldDisableSoundNotification(123456, $time));
        
        // 08:30 - в диапазоне
        $time = new \DateTime('2024-01-01 08:30:00');
        $this->assertTrue($accessControl->shouldDisableSoundNotification(123456, $time));
        
        // 10:00 - вне диапазона
        $time = new \DateTime('2024-01-01 10:00:00');
        $this->assertFalse($accessControl->shouldDisableSoundNotification(123456, $time));
        
        // 15:00 - вне диапазона
        $time = new \DateTime('2024-01-01 15:00:00');
        $this->assertFalse($accessControl->shouldDisableSoundNotification(123456, $time));
    }
}
