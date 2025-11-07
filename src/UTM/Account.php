<?php

declare(strict_types=1);

namespace App\Component\UTM;

use App\Component\Logger;
use App\Component\MySQL;
use App\Component\Exception\UTM\AccountException;
use App\Component\Exception\UTM\UtilsValidationException;
use PDOException;

/**
 * Класс для работы с лицевыми счетами в биллинговой системе UTM5
 * 
 * Предоставляет методы для:
 * - Получения информации о балансе
 * - Управления тарифами
 * - Работы с услугами
 * - Управления группами пользователей
 */
class Account
{
    private MySQL $db;
    private ?Logger $logger;

    /**
     * Конструктор класса Account
     *
     * @param MySQL $db Экземпляр подключения к БД UTM
     * @param Logger|null $logger Логгер для записи операций
     */
    public function __construct(MySQL $db, ?Logger $logger = null)
    {
        $this->db = $db;
        $this->logger = $logger;

        $this->log('INFO', 'Account инициализирован');
    }

    /**
     * Получает полную информацию о лицевом счете
     *
     * @param int $accountId ID лицевого счета
     * @return array<string, mixed> Информация о счете
     * @throws AccountException При ошибках работы с БД или отсутствии счета
     */
    public function getAccountInfo(int $accountId): array
    {
        $this->log('INFO', 'Получение информации о счете', ['account_id' => $accountId]);

        try {
            $sql = "SELECT balance, credit, is_blocked, int_status, flags, 
                           dont_charge_if_block, block_recalc_abon, 
                           block_recalc_prepaid, unlimited 
                    FROM accounts 
                    WHERE id = :account_id AND is_deleted = 0";
            
            $result = $this->db->query($sql, ['account_id' => $accountId]);

            if (empty($result)) {
                throw new AccountException("Лицевой счет {$accountId} не найден");
            }

            $this->log('INFO', 'Информация о счете получена', ['account_id' => $accountId]);
            
            return $result[0];
        } catch (PDOException $e) {
            $this->log('ERROR', 'Ошибка при получении информации о счете', [
                'account_id' => $accountId,
                'error' => $e->getMessage()
            ]);
            throw new AccountException("Ошибка при получении информации о счете: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Получает баланс лицевого счета
     *
     * @param int $accountId ID лицевого счета
     * @param string $format Формат вывода:
     *   - 'balance and credit': "1000(500)р." (баланс с кредитом в скобках)
     *   - 'balance + credit': сумма баланса и кредита
     *   - 'balance': только баланс
     *   - 'credit': только кредит
     *   - 'array': массив с balance и credit
     * @param int $precision Количество знаков после запятой
     * @param string $unit Единица измерения (по умолчанию "р.")
     * @return string|array<string, float> Баланс в указанном формате
     * @throws AccountException При ошибках работы с БД
     */
    public function getBalance(
        int $accountId, 
        string $format = 'balance and credit', 
        int $precision = 2, 
        string $unit = "р."
    ): string|array {
        $this->log('INFO', 'Получение баланса счета', [
            'account_id' => $accountId,
            'format' => $format
        ]);

        try {
            $sql = "SELECT balance, credit 
                    FROM accounts 
                    WHERE id = :account_id AND is_deleted = 0";
            
            $result = $this->db->query($sql, ['account_id' => $accountId]);

            if (empty($result)) {
                throw new AccountException("Баланс лицевого счета {$accountId} не найден");
            }

            $balance = (float)$result[0]['balance'];
            $credit = (float)$result[0]['credit'];

            // Округление
            try {
                $balanceStr = Utils::doRound($balance, $precision);
                $creditStr = Utils::doRound($credit, $precision);
            } catch (UtilsValidationException $e) {
                throw new AccountException("Ошибка округления: " . $e->getMessage(), 0, $e);
            }

            // Форматирование вывода
            return match($format) {
                'array' => ['balance' => $balance, 'credit' => $credit],
                'balance and credit' => $credit == 0 
                    ? $balanceStr . $unit 
                    : "{$balanceStr}({$creditStr}){$unit}",
                'balance + credit' => (string)($balance + $credit),
                'credit' => $creditStr,
                default => $balanceStr,
            };
        } catch (PDOException $e) {
            $this->log('ERROR', 'Ошибка при получении баланса', [
                'account_id' => $accountId,
                'error' => $e->getMessage()
            ]);
            throw new AccountException("Ошибка при получении баланса: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Получает текущие тарифы лицевого счета
     *
     * @param int $accountId ID лицевого счета
     * @param string $format Формат вывода:
     *   - 'tariff+id': "Тариф Базовый (id 123)"
     *   - 'tariff': "Тариф Базовый"
     *   - 'id': "123"
     *   - 'array': ['id' => 'название']
     * @param string $separator Разделитель для нескольких тарифов
     * @return string|array<int, string>|null Тарифы в указанном формате или null если нет
     * @throws AccountException При ошибках работы с БД
     */
    public function getCurrentTariff(
        int $accountId, 
        string $format = 'tariff+id', 
        string $separator = "\n"
    ): string|array|null {
        $this->log('INFO', 'Получение текущих тарифов', [
            'account_id' => $accountId,
            'format' => $format
        ]);

        try {
            $sql = match($format) {
                'tariff+id' => "SELECT GROUP_CONCAT(
                                    TRIM(CONCAT(
                                        REPLACE(REPLACE(name, 'тариф', ''), '\"', ''),
                                        ' (id ', id, ')'
                                    )) ORDER BY id ASC SEPARATOR :separator
                                ) as current_tariff 
                                FROM tariffs 
                                WHERE id IN (
                                    SELECT tariff_id 
                                    FROM account_tariff_link 
                                    WHERE account_id = :account_id AND is_deleted = 0
                                ) AND is_deleted = 0",
                'tariff' => "SELECT GROUP_CONCAT(
                                TRIM(REPLACE(REPLACE(name, 'тариф', ''), '\"', ''))
                                ORDER BY id ASC SEPARATOR :separator
                            ) as current_tariff 
                            FROM tariffs 
                            WHERE id IN (
                                SELECT tariff_id 
                                FROM account_tariff_link 
                                WHERE account_id = :account_id AND is_deleted = 0
                            ) AND is_deleted = 0",
                'id' => "SELECT GROUP_CONCAT(id ORDER BY id ASC SEPARATOR :separator) as current_tariff 
                        FROM tariffs 
                        WHERE id IN (
                            SELECT tariff_id 
                            FROM account_tariff_link 
                            WHERE account_id = :account_id AND is_deleted = 0
                        ) AND is_deleted = 0",
                'array' => "SELECT id, TRIM(REPLACE(REPLACE(name, 'тариф', ''), '\"', '')) as current_tariff 
                           FROM tariffs 
                           WHERE id IN (
                               SELECT tariff_id 
                               FROM account_tariff_link 
                               WHERE account_id = :account_id AND is_deleted = 0
                           ) AND is_deleted = 0",
                default => throw new AccountException("Неизвестный формат: {$format}")
            };

            $params = ['account_id' => $accountId];
            if ($format !== 'array') {
                $params['separator'] = $separator;
            }

            $result = $this->db->query($sql, $params);

            if ($format === 'array') {
                if (empty($result)) {
                    $this->log('INFO', 'У счета нет подключенных тарифов', ['account_id' => $accountId]);
                    return null;
                }

                $tariffs = [];
                foreach ($result as $row) {
                    $tariffs[$row['id']] = $row['current_tariff'];
                }
                return $tariffs;
            }

            if (empty($result[0]['current_tariff'])) {
                $this->log('INFO', 'У счета нет подключенных тарифов', ['account_id' => $accountId]);
                return null;
            }

            return $result[0]['current_tariff'];
        } catch (PDOException $e) {
            $this->log('ERROR', 'Ошибка при получении тарифов', [
                'account_id' => $accountId,
                'error' => $e->getMessage()
            ]);
            throw new AccountException("Ошибка при получении тарифов: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Получает следующие тарифы лицевого счета (на которые будет переход)
     *
     * @param int $accountId ID лицевого счета
     * @param int|null $fromTariffId ID тарифа, с которого будет переход (если null - все следующие тарифы)
     * @param string $format Формат вывода (аналогично getCurrentTariff)
     * @param string $separator Разделитель для нескольких тарифов
     * @return string|array<int, string>|null Тарифы в указанном формате или null если нет
     * @throws AccountException При ошибках работы с БД
     */
    public function getNextTariff(
        int $accountId, 
        ?int $fromTariffId = null,
        string $format = 'tariff+id', 
        string $separator = "\n"
    ): string|array|null {
        $this->log('INFO', 'Получение следующих тарифов', [
            'account_id' => $accountId,
            'from_tariff_id' => $fromTariffId,
            'format' => $format
        ]);

        try {
            $whereTariff = $fromTariffId !== null 
                ? "AND tariff_id = :from_tariff_id" 
                : "";

            $sql = match($format) {
                'tariff+id' => "SELECT GROUP_CONCAT(
                                    TRIM(CONCAT(
                                        REPLACE(REPLACE(name, 'тариф', ''), '\"', ''),
                                        ' (id ', id, ')'
                                    )) ORDER BY id ASC SEPARATOR :separator
                                ) as next_tariff 
                                FROM tariffs 
                                WHERE id IN (
                                    SELECT next_tariff_id 
                                    FROM account_tariff_link 
                                    WHERE account_id = :account_id {$whereTariff} AND is_deleted = 0
                                ) AND is_deleted = 0",
                'tariff' => "SELECT GROUP_CONCAT(
                                TRIM(REPLACE(REPLACE(name, 'тариф', ''), '\"', ''))
                                ORDER BY id ASC SEPARATOR :separator
                            ) as next_tariff 
                            FROM tariffs 
                            WHERE id IN (
                                SELECT next_tariff_id 
                                FROM account_tariff_link 
                                WHERE account_id = :account_id {$whereTariff} AND is_deleted = 0
                            ) AND is_deleted = 0",
                'id' => "SELECT GROUP_CONCAT(id ORDER BY id ASC SEPARATOR :separator) as next_tariff 
                        FROM tariffs 
                        WHERE id IN (
                            SELECT next_tariff_id 
                            FROM account_tariff_link 
                            WHERE account_id = :account_id {$whereTariff} AND is_deleted = 0
                        ) AND is_deleted = 0",
                'array' => "SELECT id, TRIM(REPLACE(REPLACE(name, 'тариф', ''), '\"', '')) as next_tariff 
                           FROM tariffs 
                           WHERE id IN (
                               SELECT next_tariff_id 
                               FROM account_tariff_link 
                               WHERE account_id = :account_id {$whereTariff} AND is_deleted = 0
                           ) AND is_deleted = 0",
                default => throw new AccountException("Неизвестный формат: {$format}")
            };

            $params = ['account_id' => $accountId];
            if ($fromTariffId !== null) {
                $params['from_tariff_id'] = $fromTariffId;
            }
            if ($format !== 'array') {
                $params['separator'] = $separator;
            }

            $result = $this->db->query($sql, $params);

            if ($format === 'array') {
                if (empty($result)) {
                    $this->log('INFO', 'У счета нет следующих тарифов', ['account_id' => $accountId]);
                    return null;
                }

                $tariffs = [];
                foreach ($result as $row) {
                    $tariffs[$row['id']] = $row['next_tariff'];
                }
                return $tariffs;
            }

            if (empty($result[0]['next_tariff'])) {
                $this->log('INFO', 'У счета нет следующих тарифов', ['account_id' => $accountId]);
                return null;
            }

            return $result[0]['next_tariff'];
        } catch (PDOException $e) {
            $this->log('ERROR', 'Ошибка при получении следующих тарифов', [
                'account_id' => $accountId,
                'error' => $e->getMessage()
            ]);
            throw new AccountException("Ошибка при получении следующих тарифов: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Получает услуги, подключенные к лицевому счету
     *
     * @param int $accountId ID лицевого счета
     * @param string $format Формат вывода:
     *   - 'service+id': "Услуга (id 123)"
     *   - 'service+cost': "Услуга (100 руб.)"
     *   - 'service': "Услуга"
     *   - 'id': "123"
     *   - 'array': ['id' => ['name' => ..., 'cost' => ..., 'count' => ...]]
     * @param string $separator Разделитель для нескольких услуг
     * @return string|array<int, array<string, mixed>>|null Услуги в указанном формате или null если нет
     * @throws AccountException При ошибках работы с БД
     */
    public function getServices(
        int $accountId, 
        string $format = 'service+id', 
        string $separator = "\n"
    ): string|array|null {
        $this->log('INFO', 'Получение услуг счета', [
            'account_id' => $accountId,
            'format' => $format
        ]);

        try {
            if ($format === 'array') {
                $sql = "SELECT 
                            s.id,
                            TRIM(CONCAT(REPLACE(REPLACE(s.service_name, 'услуга', ''), '\"', ''))) AS service,
                            (SELECT p.cost
                             FROM periodic_services_data p
                             WHERE p.id = s.id AND p.is_deleted = 0
                             LIMIT 1) AS cost,
                            COUNT(sl.service_id) AS service_count
                        FROM services_data s
                        JOIN service_links sl
                            ON s.id = sl.service_id
                            AND sl.account_id = :account_id
                            AND sl.tariff_link_id = 0
                            AND sl.is_deleted = 0
                        WHERE s.is_deleted = 0
                        GROUP BY s.id, s.service_name";
                
                $result = $this->db->query($sql, ['account_id' => $accountId]);

                if (empty($result)) {
                    $this->log('INFO', 'У счета нет подключенных услуг', ['account_id' => $accountId]);
                    return null;
                }

                $services = [];
                foreach ($result as $row) {
                    $services[$row['id']] = [
                        'name' => $row['service'],
                        'cost' => $row['cost'],
                        'count' => (int)$row['service_count']
                    ];
                }
                return $services;
            }

            $sql = match($format) {
                'service+id' => "SELECT GROUP_CONCAT(
                                    TRIM(CONCAT(
                                        REPLACE(REPLACE(service_name, 'услуга', ''), '\"', ''),
                                        ' (id ', id, ')'
                                    )) ORDER BY id ASC SEPARATOR :separator
                                ) as service 
                                FROM services_data 
                                WHERE id IN (
                                    SELECT service_id 
                                    FROM service_links 
                                    WHERE account_id = :account_id 
                                        AND tariff_link_id = 0 
                                        AND is_deleted = 0
                                ) AND is_deleted = 0",
                'service+cost' => "SELECT GROUP_CONCAT(
                                    TRIM(CONCAT(
                                        REPLACE(REPLACE(service_name, 'услуга', ''), '\"', ''),
                                        ' (', 
                                        COALESCE((
                                            SELECT cost 
                                            FROM periodic_services_data 
                                            WHERE periodic_services_data.id = services_data.id 
                                                AND is_deleted = 0 
                                            LIMIT 1
                                        ), 0),
                                        ' руб.)'
                                    )) ORDER BY id ASC SEPARATOR :separator
                                ) as service 
                                FROM services_data 
                                WHERE id IN (
                                    SELECT service_id 
                                    FROM service_links 
                                    WHERE account_id = :account_id 
                                        AND tariff_link_id = 0 
                                        AND is_deleted = 0
                                ) AND is_deleted = 0",
                'service' => "SELECT GROUP_CONCAT(
                                TRIM(REPLACE(REPLACE(service_name, 'услуга', ''), '\"', ''))
                                ORDER BY id ASC SEPARATOR :separator
                            ) as service 
                            FROM services_data 
                            WHERE id IN (
                                SELECT service_id 
                                FROM service_links 
                                WHERE account_id = :account_id 
                                    AND tariff_link_id = 0 
                                    AND is_deleted = 0
                            ) AND is_deleted = 0",
                'id' => "SELECT GROUP_CONCAT(id ORDER BY id ASC SEPARATOR :separator) as service 
                        FROM services_data 
                        WHERE id IN (
                            SELECT service_id 
                            FROM service_links 
                            WHERE account_id = :account_id 
                                AND tariff_link_id = 0 
                                AND is_deleted = 0
                        ) AND is_deleted = 0",
                default => throw new AccountException("Неизвестный формат: {$format}")
            };

            $result = $this->db->query($sql, [
                'account_id' => $accountId,
                'separator' => $separator
            ]);

            if (empty($result[0]['service'])) {
                $this->log('INFO', 'У счета нет подключенных услуг', ['account_id' => $accountId]);
                return null;
            }

            return $result[0]['service'];
        } catch (PDOException $e) {
            $this->log('ERROR', 'Ошибка при получении услуг', [
                'account_id' => $accountId,
                'error' => $e->getMessage()
            ]);
            throw new AccountException("Ошибка при получении услуг: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Получает группы, к которым принадлежит лицевой счет
     *
     * @param int $accountId ID лицевого счета
     * @param string $separator Разделитель для нескольких групп
     * @return string|null ID групп через разделитель или null если нет
     * @throws AccountException При ошибках работы с БД
     */
    public function getGroups(int $accountId, string $separator = ','): ?string
    {
        $this->log('INFO', 'Получение групп счета', ['account_id' => $accountId]);

        try {
            $sql = "SELECT GROUP_CONCAT(group_id ORDER BY group_id ASC SEPARATOR :separator) as group_id 
                    FROM users_groups_link 
                    WHERE user_id = (
                        SELECT uid 
                        FROM users_accounts 
                        WHERE account_id = :account_id AND is_deleted = 0 
                        LIMIT 1
                    )";

            $result = $this->db->query($sql, [
                'account_id' => $accountId,
                'separator' => $separator
            ]);

            if (empty($result[0]['group_id'])) {
                $this->log('INFO', 'Счет не принадлежит ни одной группе', ['account_id' => $accountId]);
                return null;
            }

            return $result[0]['group_id'];
        } catch (PDOException $e) {
            $this->log('ERROR', 'Ошибка при получении групп', [
                'account_id' => $accountId,
                'error' => $e->getMessage()
            ]);
            throw new AccountException("Ошибка при получении групп: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Получает дополнительные параметры пользователя по лицевому счету
     *
     * @param int $accountId ID лицевого счета
     * @param int|null $paramid ID параметра (если null - все параметры)
     * @param int|null $limit Лимит результатов
     * @param string $separator Разделитель для вывода нескольких параметров
     * @return string|null Значение параметра(ов) или null если не найдено
     * @throws AccountException При ошибках работы с БД
     */
    public function getUadParamsByAccount(
        int $accountId, 
        ?int $paramid = null, 
        ?int $limit = null, 
        string $separator = ','
    ): ?string {
        $this->log('INFO', 'Получение дополнительных параметров пользователя', [
            'account_id' => $accountId,
            'paramid' => $paramid
        ]);

        try {
            if (is_null($paramid)) {
                $sql = "SELECT GROUP_CONCAT(CONCAT(paramid, '=', value) SEPARATOR :separator) as value 
                        FROM user_additional_params uap 
                        WHERE userid = (
                            SELECT uid 
                            FROM users_accounts 
                            WHERE account_id = :account_id AND is_deleted = 0 
                            LIMIT 1
                        ) AND value != ''";
                $params = ['account_id' => $accountId, 'separator' => $separator];
            } else {
                $sql = "SELECT value 
                        FROM user_additional_params uap 
                        WHERE paramid = :paramid 
                            AND userid = (
                                SELECT uid 
                                FROM users_accounts 
                                WHERE account_id = :account_id AND is_deleted = 0 
                                LIMIT 1
                            )";
                $params = ['paramid' => $paramid, 'account_id' => $accountId];
            }

            $result = $this->db->query($sql, $params, $limit);

            if (empty($result) || empty($result[0]['value'])) {
                $this->log('INFO', 'Дополнительный параметр не найден', [
                    'account_id' => $accountId,
                    'paramid' => $paramid
                ]);
                return null;
            }

            return $result[0]['value'];
        } catch (PDOException $e) {
            $this->log('ERROR', 'Ошибка при получении дополнительных параметров', [
                'account_id' => $accountId,
                'error' => $e->getMessage()
            ]);
            throw new AccountException("Ошибка при получении дополнительных параметров: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Получает группы пользователя по лицевому счету (обертка для обратной совместимости)
     *
     * @param int $accountId ID лицевого счета
     * @param int|null $limit Лимит результатов (не используется, для совместимости)
     * @return string|null ID групп через запятую или null если нет
     * @throws AccountException При ошибках работы с БД
     */
    public function getGroupByAccount(int $accountId, ?int $limit = null): ?string
    {
        return $this->getGroups($accountId, ',');
    }

    /**
     * Получает название дилера по лицевому счету
     *
     * @param int $accountId ID лицевого счета
     * @param string $separator Разделитель (не используется в данном методе)
     * @return string Название дилера ('Марат', 'Стариков', 'БТ')
     * @throws AccountException При ошибках работы с БД
     */
    public function getDealerNameByAccount(int $accountId, string $separator = '\n'): string
    {
        $this->log('INFO', 'Получение названия дилера', ['account_id' => $accountId]);

        $groups = $this->getGroupByAccount($accountId, 20);
        if ($groups === null) {
            return 'БТ';
        }

        if (str_contains($groups, '88888')) {
            return 'Марат';
        } elseif (str_contains($groups, '99999')) {
            return 'Стариков';
        } else {
            return 'БТ';
        }
    }

    /**
     * Получает лицевой счет по IP-адресу
     *
     * @param string $ip IP-адрес
     * @param int|null $limit Лимит результатов
     * @return int|null ID лицевого счета или null если не найден
     * @throws AccountException При ошибках работы с БД или невалидном IP
     */
    public function getAccountByIP(string $ip, ?int $limit = null): ?int
    {
        $this->log('INFO', 'Поиск счета по IP', ['ip' => $ip]);

        try {
            $ip = Utils::validateIp($ip);
        } catch (UtilsValidationException $e) {
            throw new AccountException("Невалидный IP-адрес: " . $e->getMessage(), 0, $e);
        }

        try {
            $sql = "SELECT users.basic_account as account_id 
                    FROM users, accounts, service_links, iptraffic_service_links, ip_groups
                    WHERE users.is_deleted = 0
                        AND users.basic_account = accounts.id
                        AND accounts.is_deleted = 0
                        AND accounts.id = service_links.account_id
                        AND service_links.is_deleted = 0
                        AND service_links.id = iptraffic_service_links.id
                        AND iptraffic_service_links.is_deleted = 0
                        AND iptraffic_service_links.ip_group_id = ip_groups.ip_group_id
                        AND ip_groups.is_deleted = 0
                        AND INET_NTOA(ip_groups.ip & 0xFFFFFFFF) = :ip";

            $result = $this->db->query($sql, ['ip' => $ip], $limit);

            if (empty($result) || empty($result[0]['account_id'])) {
                $this->log('INFO', 'Абонент с IP не найден', ['ip' => $ip]);
                return null;
            }

            return (int)$result[0]['account_id'];
        } catch (PDOException $e) {
            $this->log('ERROR', 'Ошибка при поиске счета по IP', [
                'ip' => $ip,
                'error' => $e->getMessage()
            ]);
            throw new AccountException("Ошибка при поиске счета по IP: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Получает IP-адреса по лицевому счету
     *
     * @param int $accountId ID лицевого счета
     * @param string $format Формат вывода:
     *   - 'ip': только IP через разделитель
     *   - 'ip+mac': IP с MAC в формате "IP [MAC]"
     *   - 'array': массив ['IP' => 'MAC']
     * @param string $separator Разделитель для нескольких IP
     * @return string|array<string, string>|null IP-адреса в указанном формате или null если нет
     * @throws AccountException При ошибках работы с БД
     */
    public function getIpByAccount(
        int $accountId, 
        string $format = 'ip', 
        string $separator = '\n'
    ): string|array|null {
        $this->log('INFO', 'Получение IP-адресов счета', [
            'account_id' => $accountId,
            'format' => $format
        ]);

        try {
            $baseSql = "FROM users u
                        LEFT JOIN service_links sl ON (sl.user_id = u.id AND sl.is_deleted = 0)
                        RIGHT JOIN services_data sd ON (sd.id = sl.service_id AND sd.service_type NOT IN (1,2) AND sd.is_deleted = 0)
                        LEFT JOIN iptraffic_service_links ipt_sl ON (ipt_sl.id = sl.id AND ipt_sl.is_deleted = 0)
                        LEFT JOIN ip_groups ig ON (ig.ip_group_id = ipt_sl.ip_group_id AND ig.is_deleted = 0)
                        WHERE u.is_deleted = 0 AND u.basic_account = :account_id";

            if ($format === 'ip') {
                $sql = "SELECT GROUP_CONCAT(inet_ntoa(4294967295 & ig.ip) ORDER BY inet_ntoa(4294967295 & ig.ip) ASC SEPARATOR :separator) as ip " . $baseSql;
                $params = ['account_id' => $accountId, 'separator' => $separator];
            } elseif ($format === 'array') {
                $sql = "SELECT inet_ntoa(4294967295 & ig.ip) as ip, mac " . $baseSql;
                $params = ['account_id' => $accountId];
            } else {
                $sql = "SELECT GROUP_CONCAT(IF(ig.mac = '', inet_ntoa(4294967295 & ig.ip), CONCAT(inet_ntoa(4294967295 & ig.ip), ' [', ig.mac, ']')) ORDER BY inet_ntoa(4294967295 & ig.ip) ASC SEPARATOR '\n') as ip " . $baseSql;
                $params = ['account_id' => $accountId];
            }

            $result = $this->db->query($sql, $params);

            if ($format === 'array') {
                if (empty($result)) {
                    $this->log('INFO', 'У счета нет IP-адресов', ['account_id' => $accountId]);
                    return null;
                }

                $resultArray = [];
                foreach ($result as $row) {
                    if (!empty($row['ip'])) {
                        $resultArray[$row['ip']] = $row['mac'] ?? '';
                    }
                }

                return empty($resultArray) ? null : $resultArray;
            }

            if (empty($result[0]['ip'])) {
                $this->log('INFO', 'У счета нет IP-адресов', ['account_id' => $accountId]);
                return null;
            }

            return $result[0]['ip'];
        } catch (PDOException $e) {
            $this->log('ERROR', 'Ошибка при получении IP-адресов', [
                'account_id' => $accountId,
                'error' => $e->getMessage()
            ]);
            throw new AccountException("Ошибка при получении IP-адресов: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Получает лицевой счет по номеру телефона
     *
     * @param string $phone Номер телефона
     * @param string $separator Разделитель для нескольких счетов
     * @return string|null ID лицевых счетов через разделитель или null если не найдено
     * @throws AccountException При ошибках работы с БД
     */
    public function getAccountByPhone(string $phone, string $separator = ','): ?string
    {
        $this->log('INFO', 'Поиск счета по телефону', ['phone' => $phone]);

        try {
            try {
                $phone = Utils::validateMobileNumber($phone);
                $sql = "SELECT GROUP_CONCAT(basic_account SEPARATOR :separator) as account_id 
                        FROM users 
                        WHERE (mobile_telephone = :phone1 OR home_telephone = :phone2 OR work_telephone = :phone3) 
                            AND is_deleted = 0";
                $params = ['phone1' => $phone, 'phone2' => $phone, 'phone3' => $phone, 'separator' => $separator];
            } catch (UtilsValidationException $e) {
                $value = str_replace(' ', '%', trim($phone));
                $sql = "SELECT GROUP_CONCAT(basic_account SEPARATOR :separator) as account_id 
                        FROM users 
                        WHERE (mobile_telephone LIKE :value1 OR home_telephone LIKE :value2 OR work_telephone LIKE :value3) 
                            AND is_deleted = 0";
                $params = [
                    'value1' => '%' . $value . '%', 
                    'value2' => '%' . $value . '%', 
                    'value3' => '%' . $value . '%', 
                    'separator' => $separator
                ];
            }

            $result = $this->db->query($sql, $params);

            if (empty($result) || empty($result[0]['account_id'])) {
                $this->log('INFO', 'Абонент с телефоном не найден', ['phone' => $phone]);
                return null;
            }

            return $result[0]['account_id'];
        } catch (PDOException $e) {
            $this->log('ERROR', 'Ошибка при поиске счета по телефону', [
                'phone' => $phone,
                'error' => $e->getMessage()
            ]);
            throw new AccountException("Ошибка при поиске счета по телефону: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Получает лицевой счет по адресу
     *
     * @param string $address Адрес
     * @param string|null $entrance Подъезд
     * @param string|null $floor Этаж
     * @param string|null $flat Квартира
     * @param string $separator Разделитель для нескольких счетов
     * @return string|null ID лицевых счетов через разделитель или null если не найдено
     * @throws AccountException При ошибках работы с БД
     */
    public function getAccountByAddress(
        string $address, 
        ?string $entrance = null, 
        ?string $floor = null, 
        ?string $flat = null, 
        string $separator = ','
    ): ?string {
        $this->log('INFO', 'Поиск счета по адресу', [
            'address' => $address,
            'entrance' => $entrance,
            'floor' => $floor,
            'flat' => $flat
        ]);

        try {
            $params = ['address' => $address, 'separator' => $separator];
            $sql = "SELECT GROUP_CONCAT(basic_account SEPARATOR :separator) as account_id 
                    FROM users 
                    WHERE actual_address = :address";

            if (!empty($entrance)) {
                $params['entrance'] = $entrance;
                $sql .= " AND entrance = :entrance";
            }
            if (!empty($floor)) {
                $params['floor'] = $floor;
                $sql .= " AND floor = :floor";
            }
            if (!empty($flat)) {
                $params['flat'] = $flat;
                $sql .= " AND flat_number = :flat";
            }

            $sql .= " AND is_deleted = 0 ORDER BY actual_address, entrance, floor, flat_number";

            $result = $this->db->query($sql, $params);

            if (empty($result) || empty($result[0]['account_id'])) {
                $this->log('INFO', 'Абонент по адресу не найден', ['address' => $address]);
                return null;
            }

            return $result[0]['account_id'];
        } catch (PDOException $e) {
            $this->log('ERROR', 'Ошибка при поиске счета по адресу', [
                'address' => $address,
                'error' => $e->getMessage()
            ]);
            throw new AccountException("Ошибка при поиске счета по адресу: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Получает лицевой счет по ФИО
     *
     * @param string $value ФИО или часть ФИО
     * @param string $separator Разделитель для нескольких счетов
     * @return string|null ID лицевых счетов через разделитель или null если не найдено
     * @throws AccountException При ошибках работы с БД
     */
    public function getAccountByFio(string $value, string $separator = ','): ?string
    {
        $this->log('INFO', 'Поиск счета по ФИО', ['fio' => $value]);

        try {
            $value = trim($value);
            $value = str_replace(' ', '%', $value);

            $sql = "SELECT GROUP_CONCAT(basic_account SEPARATOR :separator) as account_id 
                    FROM users 
                    WHERE full_name LIKE :value AND is_deleted = 0";

            $result = $this->db->query($sql, [
                'value' => '%' . $value . '%',
                'separator' => $separator
            ]);

            if (empty($result) || empty($result[0]['account_id'])) {
                $this->log('INFO', 'Абонент с ФИО не найден', ['fio' => $value]);
                return null;
            }

            return $result[0]['account_id'];
        } catch (PDOException $e) {
            $this->log('ERROR', 'Ошибка при поиске счета по ФИО', [
                'fio' => $value,
                'error' => $e->getMessage()
            ]);
            throw new AccountException("Ошибка при поиске счета по ФИО: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Получает лицевой счет по порту коммутатора
     *
     * @param string $switch Название коммутатора
     * @param string $port Номер порта
     * @param string $separator Разделитель для нескольких счетов
     * @return string|null ID лицевых счетов через разделитель или null если не найдено
     * @throws AccountException При ошибках работы с БД
     */
    public function getAccountBySwitchPort(string $switch, string $port, string $separator = ','): ?string
    {
        $this->log('INFO', 'Поиск счета по порту коммутатора', [
            'switch' => $switch,
            'port' => $port
        ]);

        try {
            $sql = "SELECT GROUP_CONCAT(basic_account SEPARATOR :separator) as account_id 
                    FROM users 
                    WHERE id IN (
                        SELECT userid 
                        FROM user_additional_params 
                        WHERE paramid = 2001 
                            AND SUBSTRING_INDEX(value, '_', 1) = :switch 
                            AND find_in_set(:port, SUBSTRING_INDEX(value, '_', -1)) > 0
                    ) AND is_deleted = 0";

            $result = $this->db->query($sql, [
                'switch' => $switch,
                'port' => $port,
                'separator' => $separator
            ]);

            if (empty($result) || empty($result[0]['account_id'])) {
                $this->log('INFO', 'Абонент на порту коммутатора не найден', [
                    'switch' => $switch,
                    'port' => $port
                ]);
                return null;
            }

            return $result[0]['account_id'];
        } catch (PDOException $e) {
            $this->log('ERROR', 'Ошибка при поиске счета по порту коммутатора', [
                'switch' => $switch,
                'port' => $port,
                'error' => $e->getMessage()
            ]);
            throw new AccountException("Ошибка при поиске счета по порту коммутатора: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Получает лицевые счета по VLAN
     *
     * @param int $vlan Номер VLAN
     * @param string $separator Разделитель для нескольких счетов
     * @param int|null $limit Лимит результатов
     * @return string|null ID лицевых счетов через разделитель или null если не найдено
     * @throws AccountException При ошибках работы с БД
     */
    public function getAccountByVlan(int $vlan, string $separator = ',', ?int $limit = null): ?string
    {
        $this->log('INFO', 'Поиск счетов по VLAN', ['vlan' => $vlan]);

        try {
            $sql = "SELECT basic_account as account_id 
                    FROM users 
                    WHERE id IN (
                        SELECT userid 
                        FROM user_additional_params 
                        WHERE paramid = 2001 
                            AND LOCATE(:vlan, value) > 0
                    ) AND is_deleted = 0";

            $result = $this->db->query($sql, ['vlan' => '_' . $vlan . '_'], $limit);

            if (empty($result)) {
                $this->log('INFO', 'Абоненты с VLAN не найдены', ['vlan' => $vlan]);
                return null;
            }

            $accounts = [];
            foreach ($result as $row) {
                $accounts[] = $row['account_id'];
            }

            return implode($separator, $accounts);
        } catch (PDOException $e) {
            $this->log('ERROR', 'Ошибка при поиске счетов по VLAN', [
                'vlan' => $vlan,
                'error' => $e->getMessage()
            ]);
            throw new AccountException("Ошибка при поиске счетов по VLAN: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Получает лицевой счет по серийному номеру Wi-Fi роутера
     *
     * @param string $value Серийный номер или часть
     * @param string $separator Разделитель для нескольких счетов
     * @return string|null ID лицевых счетов через разделитель или null если не найдено
     * @throws AccountException При ошибках работы с БД или некорректной длине значения
     */
    public function getAccountBySnWiFi(string $value, string $separator = ','): ?string
    {
        $this->log('INFO', 'Поиск счета по серийному номеру Wi-Fi роутера', ['sn' => $value]);

        if (strlen($value) < 3) {
            throw new AccountException("Длина указанного значения менее 3 символов");
        }

        try {
            $sql = "SELECT GROUP_CONCAT(
                        (SELECT account_id 
                         FROM users_accounts 
                         WHERE users_accounts.uid = user_additional_params.userid 
                             AND is_deleted = 0) 
                        SEPARATOR :separator
                    ) as account_id 
                    FROM user_additional_params 
                    WHERE paramid = 2009 AND value LIKE :value";

            $result = $this->db->query($sql, [
                'value' => '%' . $value . '%',
                'separator' => $separator
            ]);

            if (empty($result) || empty($result[0]['account_id'])) {
                $this->log('INFO', 'Абонент с серийным номером Wi-Fi не найден', ['sn' => $value]);
                return null;
            }

            return $result[0]['account_id'];
        } catch (PDOException $e) {
            $this->log('ERROR', 'Ошибка при поиске счета по серийному номеру Wi-Fi', [
                'sn' => $value,
                'error' => $e->getMessage()
            ]);
            throw new AccountException("Ошибка при поиске счета по серийному номеру Wi-Fi: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Получает лицевой счет по серийному номеру STB медиаплеера
     *
     * @param string $value Серийный номер или часть
     * @param string $separator Разделитель для нескольких счетов
     * @return string|null ID лицевых счетов через разделитель или null если не найдено
     * @throws AccountException При ошибках работы с БД или некорректной длине значения
     */
    public function getAccountBySnStb(string $value, string $separator = ','): ?string
    {
        $this->log('INFO', 'Поиск счета по серийному номеру STB', ['sn' => $value]);

        if (strlen($value) < 3) {
            throw new AccountException("Длина указанного значения менее 3 символов");
        }

        try {
            $sql = "SELECT GROUP_CONCAT(
                        (SELECT account_id 
                         FROM users_accounts 
                         WHERE users_accounts.uid = user_additional_params.userid 
                             AND is_deleted = 0) 
                        SEPARATOR :separator
                    ) as account_id 
                    FROM user_additional_params 
                    WHERE (paramid = 2007 AND value LIKE :value1) 
                        OR (paramid = 2008 AND value LIKE :value2)";

            $result = $this->db->query($sql, [
                'value1' => '%' . $value . '%',
                'value2' => '%' . $value . '%',
                'separator' => $separator
            ]);

            if (empty($result) || empty($result[0]['account_id'])) {
                $this->log('INFO', 'Абонент с серийным номером STB не найден', ['sn' => $value]);
                return null;
            }

            return $result[0]['account_id'];
        } catch (PDOException $e) {
            $this->log('ERROR', 'Ошибка при поиске счета по серийному номеру STB', [
                'sn' => $value,
                'error' => $e->getMessage()
            ]);
            throw new AccountException("Ошибка при поиске счета по серийному номеру STB: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Получает лицевой счет по SSID
     *
     * @param string $value SSID или часть
     * @param string $separator Разделитель для нескольких счетов
     * @return string|null ID лицевых счетов через разделитель или null если не найдено
     * @throws AccountException При ошибках работы с БД или некорректной длине значения
     */
    public function getAccountBySSID(string $value, string $separator = ','): ?string
    {
        $this->log('INFO', 'Поиск счета по SSID', ['ssid' => $value]);

        if (strlen($value) < 3) {
            throw new AccountException("Длина указанного значения менее 3 символов");
        }

        try {
            $sql = "SELECT GROUP_CONCAT(
                        (SELECT account_id 
                         FROM users_accounts 
                         WHERE users_accounts.uid = user_additional_params.userid 
                             AND is_deleted = 0) 
                        SEPARATOR :separator
                    ) as account_id 
                    FROM user_additional_params 
                    WHERE paramid = 2022 AND value LIKE :value";

            $result = $this->db->query($sql, [
                'value' => '%' . $value . '%',
                'separator' => $separator
            ]);

            if (empty($result) || empty($result[0]['account_id'])) {
                $this->log('INFO', 'Абонент с SSID не найден', ['ssid' => $value]);
                return null;
            }

            return $result[0]['account_id'];
        } catch (PDOException $e) {
            $this->log('ERROR', 'Ошибка при поиске счета по SSID', [
                'ssid' => $value,
                'error' => $e->getMessage()
            ]);
            throw new AccountException("Ошибка при поиске счета по SSID: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Проверяет существование лицевого счета
     *
     * @param int $accountId ID лицевого счета
     * @param int $limit Лимит результатов
     * @return int ID лицевого счета
     * @throws AccountException При ошибках работы с БД или если счет не существует
     */
    public function getAccountId(int $accountId, int $limit = 1): int
    {
        $this->log('INFO', 'Проверка существования счета', ['account_id' => $accountId]);

        try {
            $sql = "SELECT account_id 
                    FROM users_accounts 
                    WHERE is_deleted = 0 AND account_id = :account_id";

            $result = $this->db->query($sql, ['account_id' => $accountId], $limit);

            if (empty($result)) {
                throw new AccountException("Лицевой счет {$accountId} не существует");
            }

            return (int)$result[0]['account_id'];
        } catch (PDOException $e) {
            $this->log('ERROR', 'Ошибка при проверке существования счета', [
                'account_id' => $accountId,
                'error' => $e->getMessage()
            ]);
            throw new AccountException("Ошибка при проверке существования счета: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Получает логин и пароль по ID лицевого счета
     *
     * @param int $accountId ID лицевого счета
     * @param int $limit Лимит результатов
     * @return array<string, string> Массив с login и password
     * @throws AccountException При ошибках работы с БД или если счет не существует
     */
    public function getLoginAndPaswordByAccountId(int $accountId, int $limit = 1): array
    {
        $this->log('INFO', 'Получение логина и пароля', ['account_id' => $accountId]);

        try {
            $sql = "SELECT login, password 
                    FROM users 
                    WHERE basic_account = :account_id AND is_deleted = 0";

            $result = $this->db->query($sql, ['account_id' => $accountId], $limit);

            if (empty($result)) {
                throw new AccountException("Лицевой счет {$accountId} не существует");
            }

            return [
                'login' => $result[0]['login'],
                'password' => $result[0]['password']
            ];
        } catch (PDOException $e) {
            $this->log('ERROR', 'Ошибка при получении логина и пароля', [
                'account_id' => $accountId,
                'error' => $e->getMessage()
            ]);
            throw new AccountException("Ошибка при получении логина и пароля: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Получает порядковый номер учетной записи (не лицевой счет и не id счета)
     *
     * @param int $accountId ID лицевого счета
     * @param int $limit Лимит результатов
     * @return int Порядковый номер (id из users_accounts)
     * @throws AccountException При ошибках работы с БД или если счет не существует
     */
    public function getNumberIdByAccount(int $accountId, int $limit = 1): int
    {
        $this->log('INFO', 'Получение порядкового номера счета', ['account_id' => $accountId]);

        try {
            $sql = "SELECT id 
                    FROM users_accounts 
                    WHERE account_id = :account_id AND is_deleted = 0";

            $result = $this->db->query($sql, ['account_id' => $accountId], $limit);

            if (empty($result)) {
                throw new AccountException("Порядковый номер абонента не существует");
            }

            return (int)$result[0]['id'];
        } catch (PDOException $e) {
            $this->log('ERROR', 'Ошибка при получении порядкового номера счета', [
                'account_id' => $accountId,
                'error' => $e->getMessage()
            ]);
            throw new AccountException("Ошибка при получении порядкового номера счета: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Получает ID лицевого счета по user_id
     *
     * @param int $userId ID пользователя (uid из users)
     * @param int $limit Лимит результатов
     * @return int ID лицевого счета
     * @throws AccountException При ошибках работы с БД или если счет не существует
     */
    public function getAccountByUserId(int $userId, int $limit = 1): int
    {
        $this->log('INFO', 'Получение счета по user_id', ['user_id' => $userId]);

        try {
            $sql = "SELECT account_id 
                    FROM users_accounts 
                    WHERE uid = :user_id AND is_deleted = 0";

            $result = $this->db->query($sql, ['user_id' => $userId], $limit);

            if (empty($result)) {
                throw new AccountException("Лицевой счет для user_id {$userId} не существует");
            }

            return (int)$result[0]['account_id'];
        } catch (PDOException $e) {
            $this->log('ERROR', 'Ошибка при получении счета по user_id', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw new AccountException("Ошибка при получении счета по user_id: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Получает ID последнего лицевого счета
     *
     * @param int $limit Лимит результатов
     * @return int ID последнего лицевого счета
     * @throws AccountException При ошибках работы с БД
     */
    public function getLastAccountId(int $limit = 1): int
    {
        $this->log('INFO', 'Получение последнего account_id');

        try {
            $sql = "SELECT account_id 
                    FROM users_accounts 
                    WHERE is_deleted = 0 
                    ORDER BY id DESC";

            $result = $this->db->query($sql, [], $limit);

            if (empty($result)) {
                throw new AccountException("Не удалось определить номер последнего лицевого счета");
            }

            return (int)$result[0]['account_id'];
        } catch (PDOException $e) {
            $this->log('ERROR', 'Ошибка при получении последнего account_id', [
                'error' => $e->getMessage()
            ]);
            throw new AccountException("Ошибка при получении последнего account_id: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Логирование событий
     *
     * @param string $level Уровень логирования
     * @param string $message Сообщение
     * @param array<string, mixed> $context Контекст
     */
    private function log(string $level, string $message, array $context = []): void
    {
        if ($this->logger === null) {
            return;
        }

        $this->logger->log($level, $message, $context);
    }
}
