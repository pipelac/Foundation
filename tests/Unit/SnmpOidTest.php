<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Component\SnmpOid;
use App\Component\Exception\Snmp\SnmpException;
use App\Component\Exception\Snmp\SnmpValidationException;

/**
 * Unit тесты для класса SnmpOid
 */
class SnmpOidTest extends TestCase
{
    private string $configPath;

    protected function setUp(): void
    {
        $this->configPath = __DIR__ . '/../../config/snmp-oids.json';
    }

    /**
     * Тест успешной загрузки конфигурации
     */
    public function testLoadConfiguration(): void
    {
        $oidLoader = new SnmpOid($this->configPath);
        
        $this->assertInstanceOf(SnmpOid::class, $oidLoader);
    }

    /**
     * Тест получения OID по имени из common секции
     */
    public function testGetCommonOid(): void
    {
        $oidLoader = new SnmpOid($this->configPath);
        
        $sysNameOid = $oidLoader->getOid('sysName');
        $this->assertEquals('.1.3.6.1.2.1.1.5.0', $sysNameOid);
        
        $sysDescrOid = $oidLoader->getOid('sysDescr');
        $this->assertEquals('.1.3.6.1.2.1.1.1.0', $sysDescrOid);
    }

    /**
     * Тест получения OID по имени для конкретного устройства
     */
    public function testGetDeviceSpecificOid(): void
    {
        $oidLoader = new SnmpOid($this->configPath);
        
        $cpuOid = $oidLoader->getOid('CPUutilizationIn5sec', 'D-Link DES-3526');
        $this->assertEquals('.1.3.6.1.4.1.171.12.1.1.6.1.0', $cpuOid);
    }

    /**
     * Тест получения OID с суффиксом
     */
    public function testGetOidWithSuffix(): void
    {
        $oidLoader = new SnmpOid($this->configPath);
        
        $portOid = $oidLoader->getOid('ifInOctets', null, '5');
        $this->assertEquals('.1.3.6.1.2.1.2.2.1.10.5', $portOid);
    }

    /**
     * Тест наследования OID (device-specific переопределяет common)
     */
    public function testOidInheritance(): void
    {
        $oidLoader = new SnmpOid($this->configPath);
        
        // Common OID
        $commonDuplex = $oidLoader->getOid('port_duplex', null);
        $this->assertEquals('.1.3.6.1.2.1.10.7.2.1.19.', $commonDuplex);
        
        // Device-specific OID переопределяет common
        $dlinkDuplex = $oidLoader->getOid('port_duplex', 'D-Link DES-3526');
        $this->assertEquals('1.3.6.1.4.1.171.11.64.1.2.4.1.1.5.', $dlinkDuplex);
        
        $this->assertNotEquals($commonDuplex, $dlinkDuplex);
    }

    /**
     * Тест получения метаданных OID
     */
    public function testGetOidData(): void
    {
        $oidLoader = new SnmpOid($this->configPath);
        
        $rebootData = $oidLoader->getOidData('reboot', 'D-Link DES-3526');
        
        $this->assertIsArray($rebootData);
        $this->assertArrayHasKey('oid', $rebootData);
        $this->assertArrayHasKey('description', $rebootData);
        $this->assertArrayHasKey('type', $rebootData);
        $this->assertArrayHasKey('value_type', $rebootData);
        $this->assertArrayHasKey('value', $rebootData);
        
        $this->assertEquals('.1.3.6.1.4.1.171.12.1.2.3.0', $rebootData['oid']);
        $this->assertEquals('set', $rebootData['type']);
        $this->assertEquals('i', $rebootData['value_type']);
        $this->assertEquals('3', $rebootData['value']);
    }

    /**
     * Тест проверки существования OID
     */
    public function testHasOid(): void
    {
        $oidLoader = new SnmpOid($this->configPath);
        
        $this->assertTrue($oidLoader->hasOid('sysName'));
        $this->assertTrue($oidLoader->hasOid('CPUutilizationIn5sec', 'D-Link DES-3526'));
        $this->assertFalse($oidLoader->hasOid('nonExistentOid'));
        $this->assertFalse($oidLoader->hasOid('nonExistentOid', 'D-Link DES-3526'));
    }

    /**
     * Тест получения списка имен OID
     */
    public function testGetOidNames(): void
    {
        $oidLoader = new SnmpOid($this->configPath);
        
        $commonOids = $oidLoader->getOidNames(null);
        $this->assertIsArray($commonOids);
        $this->assertContains('sysName', $commonOids);
        $this->assertContains('sysDescr', $commonOids);
        
        $deviceOids = $oidLoader->getOidNames('D-Link DES-3526');
        $this->assertIsArray($deviceOids);
        $this->assertContains('sysName', $deviceOids); // Наследуется от common
        $this->assertContains('CPUutilizationIn5sec', $deviceOids); // Специфичный
    }

    /**
     * Тест получения списка типов устройств
     */
    public function testGetDeviceTypes(): void
    {
        $oidLoader = new SnmpOid($this->configPath);
        
        $deviceTypes = $oidLoader->getDeviceTypes();
        $this->assertIsArray($deviceTypes);
        $this->assertContains('D-Link DES-3526', $deviceTypes);
        $this->assertNotContains('common', $deviceTypes); // common не должен быть в списке
    }

    /**
     * Тест получения описания OID
     */
    public function testGetDescription(): void
    {
        $oidLoader = new SnmpOid($this->configPath);
        
        $description = $oidLoader->getDescription('sysName');
        $this->assertEquals('Имя системы', $description);
        
        $description2 = $oidLoader->getDescription('CPUutilizationIn5sec', 'D-Link DES-3526');
        $this->assertEquals('Утилизация CPU за 5 секунд', $description2);
    }

    /**
     * Тест получения типа операции
     */
    public function testGetOperationType(): void
    {
        $oidLoader = new SnmpOid($this->configPath);
        
        $type = $oidLoader->getOperationType('sysName');
        $this->assertEquals('get', $type);
        
        $type2 = $oidLoader->getOperationType('reboot', 'D-Link DES-3526');
        $this->assertEquals('set', $type2);
    }

    /**
     * Тест получения типа значения
     */
    public function testGetValueType(): void
    {
        $oidLoader = new SnmpOid($this->configPath);
        
        $valueType = $oidLoader->getValueType('sysName');
        $this->assertNull($valueType); // GET операции обычно не имеют value_type
        
        $valueType2 = $oidLoader->getValueType('reboot', 'D-Link DES-3526');
        $this->assertEquals('i', $valueType2);
    }

    /**
     * Тест получения значения по умолчанию
     */
    public function testGetDefaultValue(): void
    {
        $oidLoader = new SnmpOid($this->configPath);
        
        $defaultValue = $oidLoader->getDefaultValue('reboot', 'D-Link DES-3526');
        $this->assertEquals('3', $defaultValue);
    }

    /**
     * Тест получения статистики
     */
    public function testGetStats(): void
    {
        $oidLoader = new SnmpOid($this->configPath);
        
        $stats = $oidLoader->getStats();
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('config_path', $stats);
        $this->assertArrayHasKey('device_types_count', $stats);
        $this->assertArrayHasKey('device_types', $stats);
        $this->assertArrayHasKey('total_oids', $stats);
        
        $this->assertGreaterThan(0, $stats['device_types_count']);
        $this->assertGreaterThan(0, $stats['total_oids']);
    }

    /**
     * Тест исключения при несуществующем файле конфигурации
     */
    public function testThrowsExceptionForNonExistentConfig(): void
    {
        $this->expectException(SnmpException::class);
        $this->expectExceptionMessage('Файл конфигурации OID не найден');
        
        new SnmpOid('/path/to/nonexistent/config.json');
    }

    /**
     * Тест исключения при попытке получить несуществующий OID
     */
    public function testThrowsExceptionForNonExistentOid(): void
    {
        $oidLoader = new SnmpOid($this->configPath);
        
        $this->expectException(SnmpValidationException::class);
        $this->expectExceptionMessage("OID с именем 'nonExistentOid'");
        
        $oidLoader->getOid('nonExistentOid');
    }

    /**
     * Тест кеширования объединенных OID
     */
    public function testOidCaching(): void
    {
        $oidLoader = new SnmpOid($this->configPath);
        
        // Первый вызов - загрузка и кеширование
        $oid1 = $oidLoader->getOid('sysName', 'D-Link DES-3526');
        
        // Второй вызов - из кеша
        $oid2 = $oidLoader->getOid('sysName', 'D-Link DES-3526');
        
        $this->assertEquals($oid1, $oid2);
    }
}
