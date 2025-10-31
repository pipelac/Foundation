<?php

declare(strict_types=1);

namespace App\Component\Netmap;

use App\Component\Logger;
use App\Component\Exception\Netmap\TopologyBuilderException;

/**
 * Класс для построения топологии сети из собранных LLDP данных
 * 
 * Возможности:
 * - Построение графа сетевых устройств
 * - Определение корневых устройств (core switches)
 * - Построение иерархического дерева
 * - Детектирование циклов/петель в топологии
 * - Вычисление метрик топологии
 * 
 * Системные требования:
 * - PHP 8.1 или выше
 */
class TopologyBuilder
{
    /**
     * Опциональный логгер
     */
    private readonly ?Logger $logger;
    
    /**
     * Узлы графа (устройства)
     * @var array<string, array<string, mixed>>
     */
    private array $nodes = [];
    
    /**
     * Ребра графа (связи между устройствами)
     * @var array<int, array<string, mixed>>
     */
    private array $edges = [];
    
    /**
     * Корневые узлы топологии
     * @var array<int, string>
     */
    private array $rootNodes = [];
    
    /**
     * Граф смежности (для алгоритмов обхода)
     * @var array<string, array<int, string>>
     */
    private array $adjacencyList = [];
    
    /**
     * Обнаруженные циклы в топологии
     * @var array<int, array<int, string>>
     */
    private array $cycles = [];
    
    /**
     * Конструктор класса
     * 
     * @param Logger|null $logger Логгер для записи операций
     */
    public function __construct(?Logger $logger = null)
    {
        $this->logger = $logger;
        
        $this->log('info', 'TopologyBuilder инициализирован');
    }
    
    /**
     * Строит топологию из данных LldpCollector
     * 
     * @param array{devices: array<string, array<string, mixed>>, links: array<int, array<string, mixed>>} $data
     * @return array{nodes: array<string, array<string, mixed>>, edges: array<int, array<string, mixed>>, root_nodes: array<int, string>, cycles: array<int, array<int, string>>}
     * @throws TopologyBuilderException При ошибках построения
     */
    public function buildTopology(array $data): array
    {
        $this->log('info', 'Начало построения топологии');
        
        $startTime = microtime(true);
        
        // Валидируем входные данные
        $this->validateInputData($data);
        
        // Инициализируем узлы
        $this->initializeNodes($data['devices']);
        
        // Инициализируем ребра
        $this->initializeEdges($data['links']);
        
        // Строим граф смежности
        $this->buildAdjacencyList();
        
        // Определяем корневые узлы
        $this->identifyRootNodes();
        
        // Вычисляем уровни устройств в иерархии
        $this->calculateNodeLevels();
        
        // Детектируем циклы
        $this->detectCycles();
        
        // Вычисляем метрики
        $this->calculateMetrics();
        
        $duration = round(microtime(true) - $startTime, 2);
        
        $this->log('info', 'Построение топологии завершено', [
            'nodes_count' => count($this->nodes),
            'edges_count' => count($this->edges),
            'root_nodes_count' => count($this->rootNodes),
            'cycles_count' => count($this->cycles),
            'duration_seconds' => $duration,
        ]);
        
        return [
            'nodes' => $this->nodes,
            'edges' => $this->edges,
            'root_nodes' => $this->rootNodes,
            'cycles' => $this->cycles,
        ];
    }
    
    /**
     * Валидирует входные данные
     * 
     * @param array<string, mixed> $data Данные для валидации
     * @throws TopologyBuilderException Если данные некорректны
     */
    private function validateInputData(array $data): void
    {
        if (!isset($data['devices']) || !is_array($data['devices'])) {
            throw new TopologyBuilderException('Отсутствуют данные об устройствах');
        }
        
        if (!isset($data['links']) || !is_array($data['links'])) {
            throw new TopologyBuilderException('Отсутствуют данные о связях');
        }
        
        if (empty($data['devices'])) {
            throw new TopologyBuilderException('Список устройств не может быть пустым');
        }
    }
    
    /**
     * Инициализирует узлы графа из устройств
     * 
     * @param array<string, array<string, mixed>> $devices Устройства
     */
    private function initializeNodes(array $devices): void
    {
        foreach ($devices as $chassisId => $device) {
            $this->nodes[$chassisId] = [
                'chassis_id' => $chassisId,
                'sys_name' => $device['sys_name'] ?? 'Unknown',
                'sys_desc' => $device['sys_desc'] ?? '',
                'management_ip' => $device['management_ip'] ?? null,
                'capabilities' => $device['capabilities'] ?? [],
                'local_ports' => $device['local_ports'] ?? [],
                'discovered_at' => $device['discovered_at'] ?? date('Y-m-d H:i:s'),
                'level' => -1, // Будет вычислено позже
                'in_degree' => 0, // Количество входящих связей
                'out_degree' => 0, // Количество исходящих связей
                'is_root' => false,
            ];
        }
        
        $this->log('debug', 'Инициализировано узлов', ['count' => count($this->nodes)]);
    }
    
    /**
     * Инициализирует ребра графа из связей
     * 
     * @param array<int, array<string, mixed>> $links Связи
     */
    private function initializeEdges(array $links): void
    {
        foreach ($links as $link) {
            $sourceChassisId = $link['source_chassis_id'] ?? null;
            $targetChassisId = $link['target_chassis_id'] ?? null;
            
            if ($sourceChassisId === null || $targetChassisId === null) {
                continue;
            }
            
            // Проверяем что оба устройства существуют
            if (!isset($this->nodes[$sourceChassisId]) || !isset($this->nodes[$targetChassisId])) {
                $this->log('warning', 'Пропущена связь с несуществующим устройством', [
                    'source' => $sourceChassisId,
                    'target' => $targetChassisId,
                ]);
                continue;
            }
            
            $this->edges[] = [
                'source_chassis_id' => $sourceChassisId,
                'source_port_index' => $link['source_port_index'] ?? null,
                'target_chassis_id' => $targetChassisId,
                'target_port_id' => $link['target_port_id'] ?? null,
                'target_port_desc' => $link['target_port_desc'] ?? '',
                'discovered_at' => $link['discovered_at'] ?? date('Y-m-d H:i:s'),
            ];
            
            // Обновляем степени узлов
            $this->nodes[$sourceChassisId]['out_degree']++;
            $this->nodes[$targetChassisId]['in_degree']++;
        }
        
        $this->log('debug', 'Инициализировано ребер', ['count' => count($this->edges)]);
    }
    
    /**
     * Строит список смежности для графа
     */
    private function buildAdjacencyList(): void
    {
        // Инициализируем пустые списки
        foreach ($this->nodes as $chassisId => $node) {
            $this->adjacencyList[$chassisId] = [];
        }
        
        // Заполняем списки смежности
        foreach ($this->edges as $edge) {
            $source = $edge['source_chassis_id'];
            $target = $edge['target_chassis_id'];
            
            $this->adjacencyList[$source][] = $target;
        }
        
        $this->log('debug', 'Построен граф смежности');
    }
    
    /**
     * Определяет корневые узлы топологии
     * 
     * Корневые узлы - это устройства с наибольшим количеством исходящих связей
     * и наименьшим количеством входящих, обычно это core switches или routers
     */
    private function identifyRootNodes(): void
    {
        $this->rootNodes = [];
        
        // Сортируем узлы по метрике "корневости"
        $nodeScores = [];
        foreach ($this->nodes as $chassisId => $node) {
            // Узлы без входящих связей - потенциальные корни
            if ($node['in_degree'] === 0) {
                $nodeScores[$chassisId] = 1000 + $node['out_degree'];
            } else {
                // Вычисляем score: больше исходящих и меньше входящих = выше score
                $nodeScores[$chassisId] = $node['out_degree'] - $node['in_degree'];
            }
            
            // Бонус для роутеров
            if (in_array('router', $node['capabilities'], true)) {
                $nodeScores[$chassisId] += 100;
            }
        }
        
        // Сортируем по убыванию score
        arsort($nodeScores);
        
        // Берем топ узлов как корневые (или все с нулевой входящей степенью)
        $threshold = 0;
        foreach ($nodeScores as $chassisId => $score) {
            if ($this->nodes[$chassisId]['in_degree'] === 0 || count($this->rootNodes) < 3) {
                $this->rootNodes[] = $chassisId;
                $this->nodes[$chassisId]['is_root'] = true;
            }
            
            if (count($this->rootNodes) >= 10) {
                break;
            }
        }
        
        $this->log('info', 'Определены корневые узлы', [
            'count' => count($this->rootNodes),
            'nodes' => array_map(fn($id) => $this->nodes[$id]['sys_name'], $this->rootNodes),
        ]);
    }
    
    /**
     * Вычисляет уровни узлов в иерархии (BFS от корневых узлов)
     */
    private function calculateNodeLevels(): void
    {
        if (empty($this->rootNodes)) {
            $this->log('warning', 'Корневые узлы не определены, пропускается вычисление уровней');
            return;
        }
        
        // Инициализируем все уровни как -1
        foreach ($this->nodes as $chassisId => $node) {
            if ($this->nodes[$chassisId]['is_root']) {
                $this->nodes[$chassisId]['level'] = 0;
            }
        }
        
        // BFS от каждого корневого узла
        $queue = [];
        $visited = [];
        
        foreach ($this->rootNodes as $rootId) {
            $queue[] = ['id' => $rootId, 'level' => 0];
            $visited[$rootId] = true;
        }
        
        while (!empty($queue)) {
            $current = array_shift($queue);
            $currentId = $current['id'];
            $currentLevel = $current['level'];
            
            // Обновляем уровень если не был установлен или новый уровень меньше
            if ($this->nodes[$currentId]['level'] === -1 || $this->nodes[$currentId]['level'] > $currentLevel) {
                $this->nodes[$currentId]['level'] = $currentLevel;
            }
            
            // Добавляем соседей в очередь
            foreach ($this->adjacencyList[$currentId] as $neighborId) {
                if (!isset($visited[$neighborId])) {
                    $queue[] = ['id' => $neighborId, 'level' => $currentLevel + 1];
                    $visited[$neighborId] = true;
                }
            }
        }
        
        // Узлы с уровнем -1 недостижимы из корневых (изолированные)
        $unreachable = 0;
        foreach ($this->nodes as $chassisId => $node) {
            if ($node['level'] === -1) {
                $unreachable++;
                // Присваиваем большой уровень изолированным узлам
                $this->nodes[$chassisId]['level'] = 999;
            }
        }
        
        if ($unreachable > 0) {
            $this->log('warning', 'Обнаружены недостижимые узлы', ['count' => $unreachable]);
        }
    }
    
    /**
     * Детектирует циклы в топологии (DFS)
     */
    private function detectCycles(): void
    {
        $this->cycles = [];
        
        $visited = [];
        $recursionStack = [];
        $path = [];
        
        foreach ($this->nodes as $chassisId => $node) {
            if (!isset($visited[$chassisId])) {
                $this->dfsDetectCycle($chassisId, $visited, $recursionStack, $path);
            }
        }
        
        if (count($this->cycles) > 0) {
            $this->log('warning', 'Обнаружены циклы в топологии', [
                'count' => count($this->cycles),
            ]);
        } else {
            $this->log('info', 'Циклы не обнаружены');
        }
    }
    
    /**
     * DFS для детектирования циклов
     * 
     * @param string $node Текущий узел
     * @param array<string, bool> $visited Посещенные узлы
     * @param array<string, bool> $recursionStack Стек рекурсии
     * @param array<int, string> $path Текущий путь
     */
    private function dfsDetectCycle(string $node, array &$visited, array &$recursionStack, array &$path): void
    {
        $visited[$node] = true;
        $recursionStack[$node] = true;
        $path[] = $node;
        
        foreach ($this->adjacencyList[$node] as $neighbor) {
            if (!isset($visited[$neighbor])) {
                $this->dfsDetectCycle($neighbor, $visited, $recursionStack, $path);
            } elseif (isset($recursionStack[$neighbor]) && $recursionStack[$neighbor]) {
                // Найден цикл
                $cycleStart = array_search($neighbor, $path);
                if ($cycleStart !== false) {
                    $cycle = array_slice($path, $cycleStart);
                    $cycle[] = $neighbor; // Замыкаем цикл
                    $this->cycles[] = $cycle;
                }
            }
        }
        
        array_pop($path);
        $recursionStack[$node] = false;
    }
    
    /**
     * Вычисляет метрики топологии
     */
    private function calculateMetrics(): void
    {
        foreach ($this->nodes as $chassisId => $node) {
            // Вычисляем общую степень узла
            $degree = $node['in_degree'] + $node['out_degree'];
            $this->nodes[$chassisId]['degree'] = $degree;
            
            // Определяем тип узла на основе степени и capabilities
            $this->nodes[$chassisId]['node_type'] = $this->determineNodeType($node, $degree);
        }
    }
    
    /**
     * Определяет тип узла (core, distribution, access)
     * 
     * @param array<string, mixed> $node Данные узла
     * @param int $degree Степень узла
     * @return string Тип узла
     */
    private function determineNodeType(array $node, int $degree): string
    {
        // Core switches: высокая степень, роутинг, уровень 0-1
        if ($node['is_root'] || 
            ($node['level'] <= 1 && $degree >= 5) ||
            (in_array('router', $node['capabilities'], true) && $degree >= 3)) {
            return 'core';
        }
        
        // Distribution switches: средняя степень, уровень 1-3
        if ($node['level'] >= 1 && $node['level'] <= 3 && $degree >= 3) {
            return 'distribution';
        }
        
        // Access switches: низкая степень, уровень > 2
        if ($node['level'] > 2 || $degree <= 2) {
            return 'access';
        }
        
        return 'unknown';
    }
    
    /**
     * Получает построенную топологию
     * 
     * @return array{nodes: array<string, array<string, mixed>>, edges: array<int, array<string, mixed>>, root_nodes: array<int, string>, cycles: array<int, array<int, string>>}
     */
    public function getTopology(): array
    {
        return [
            'nodes' => $this->nodes,
            'edges' => $this->edges,
            'root_nodes' => $this->rootNodes,
            'cycles' => $this->cycles,
        ];
    }
    
    /**
     * Получает узлы графа
     * 
     * @return array<string, array<string, mixed>> Узлы
     */
    public function getNodes(): array
    {
        return $this->nodes;
    }
    
    /**
     * Получает ребра графа
     * 
     * @return array<int, array<string, mixed>> Ребра
     */
    public function getEdges(): array
    {
        return $this->edges;
    }
    
    /**
     * Получает корневые узлы
     * 
     * @return array<int, string> Chassis ID корневых узлов
     */
    public function getRootNodes(): array
    {
        return $this->rootNodes;
    }
    
    /**
     * Получает обнаруженные циклы
     * 
     * @return array<int, array<int, string>> Циклы
     */
    public function getCycles(): array
    {
        return $this->cycles;
    }
    
    /**
     * Получает статистику топологии
     * 
     * @return array<string, mixed> Статистика
     */
    public function getStats(): array
    {
        $nodesByLevel = [];
        $nodesByType = [];
        
        foreach ($this->nodes as $node) {
            $level = $node['level'];
            $type = $node['node_type'] ?? 'unknown';
            
            $nodesByLevel[$level] = ($nodesByLevel[$level] ?? 0) + 1;
            $nodesByType[$type] = ($nodesByType[$type] ?? 0) + 1;
        }
        
        return [
            'nodes_total' => count($this->nodes),
            'edges_total' => count($this->edges),
            'root_nodes_count' => count($this->rootNodes),
            'cycles_count' => count($this->cycles),
            'nodes_by_level' => $nodesByLevel,
            'nodes_by_type' => $nodesByType,
        ];
    }
    
    /**
     * Сбрасывает состояние builder
     */
    public function reset(): void
    {
        $this->nodes = [];
        $this->edges = [];
        $this->rootNodes = [];
        $this->adjacencyList = [];
        $this->cycles = [];
        
        $this->log('info', 'Состояние builder сброшено');
    }
    
    /**
     * Логирует сообщение через Logger
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
        
        try {
            $this->logger->log($level, '[TopologyBuilder] ' . $message, $context);
        } catch (\Exception $e) {
            error_log('Ошибка логирования TopologyBuilder: ' . $e->getMessage());
        }
    }
}
