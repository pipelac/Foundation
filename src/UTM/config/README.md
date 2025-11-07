# UTM Config Files

Эта папка содержит конфигурационные файлы для модуля UTM.

## Файлы конфигурации

### account.json
Конфигурация для работы с лицевыми счетами, создания пользователей, тарифов и VLAN.

**Секции:**
- `general` - Общие настройки (лимиты поиска)
- `add_user` - Параметры создания новых пользователей
- `dealer` - Маппинг групп на дилеров
- `phys_tariff` - Тарифы для физических лиц
- `jur_tariff` - Тарифы для юридических лиц
- `combo_tariff` - Комбо-тарифы (контракты)
- `switch_client_vlans` - VLAN для конкретных коммутаторов
- `multicast_vlans` - Мультикастные VLAN (запрещены для абонентов)
- `public_vlans` - Публичные VLAN с подсетями
- `private_vlans` - Приватные VLAN с подсетями

## Использование

```php
use App\Config\ConfigLoader;

// Загрузка конфигурации
$config = ConfigLoader::load('src/UTM/config/account.json');

// Доступ к параметрам
$searchLimit = $config['general']['search_results_limit'];
$dealerGroups = $config['dealer']['88888'];
$defaultTariffs = $config['phys_tariff']['default'];
```

## Миграция с INI

Для обратной совместимости со старым `account.ini`:

| INI секция | JSON ключ |
|-----------|-----------|
| `[general]` | `general` |
| `[add_user]` | `add_user` |
| `[dealer]` | `dealer` |
| `[phys_tariff]` | `phys_tariff` |
| `[jur_tariff]` | `jur_tariff` |
| `[combo_tariff]` | `combo_tariff` |
| `[switch_client_vlans]` | `switch_client_vlans` |
| `[multicast_vlans]` | `multicast_vlans` |
| `[public_vlans]` | `public_vlans` |
| `[private_vlans]` | `private_vlans` |

**Отличия формата:**
- Списки в INI (`1, 2, 3`) → массивы в JSON (`[1, 2, 3]`)
- Комбо-тарифы: `294 = 12 - 8400` → `"294": {"months": 12, "amount": 8400}`
- Булевые значения: `0/1` → `0/1` (числа, не boolean)
