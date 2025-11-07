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
