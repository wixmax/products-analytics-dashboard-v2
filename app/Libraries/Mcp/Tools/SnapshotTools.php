<?php

namespace App\Libraries\Mcp\Tools;

use App\Libraries\Mcp\ToolInterface;

class SnapshotTools implements ToolInterface
{
    protected string $action;

    public function __construct(string $action = 'list_snapshots')
    {
        $this->action = $action;
    }

    public function getName(): string
    {
        return $this->action;
    }

    public function getDescription(): string
    {
        if ($this->action === 'get_snapshot_by_date') {
            return 'Request product data snapshot entries by date string, api_version, or snapshot_id.';
        }
        return 'List available data snapshots stored in the system, with optional origin filtering and pagination.';
    }

    public function getInputSchema(): array
    {
        if ($this->action === 'get_snapshot_by_date') {
            return [
                'type' => 'object',
                'properties' => [
                    'date'        => ['type' => 'string', 'description' => 'Date or api_version substring (e.g. 2026-07-26)'],
                    'snapshot_id' => ['type' => 'number', 'description' => 'Exact snapshot ID'],
                    'origin'      => ['type' => 'string', 'description' => 'Origin category (default Winning)'],
                    'country'     => ['type' => 'string', 'description' => 'Country code (e.g. MA, SA)'],
                    'limit'       => ['type' => 'number', 'description' => 'Max items to return (default 100)']
                ],
                'additionalProperties' => false
            ];
        }

        return [
            'type' => 'object',
            'properties' => [
                'origin' => ['type' => 'string', 'description' => 'Filter by origin (Winning, China, Japan, Competitor, Local)'],
                'limit'  => ['type' => 'number', 'description' => 'Limit results (default 20)'],
                'offset' => ['type' => 'number', 'description' => 'Offset results (default 0)']
            ],
            'additionalProperties' => false
        ];
    }

    public static function parseSnapshotEntries($rawJsonStr): array
    {
        if (empty($rawJsonStr)) {
            return [];
        }
        try {
            if (is_string($rawJsonStr)) {
                $rawJsonStr = \App\Libraries\Storage\SnapshotStorageHelper::decompress($rawJsonStr);
            }
            $decoded = is_string($rawJsonStr) ? json_decode($rawJsonStr, true) : $rawJsonStr;
            if (!$decoded || !is_array($decoded)) {
                return [];
            }

            // If it's a tRPC batch structure: [0 => ['result' => ['data' => ['json' => ...]]]]
            $base = isset($decoded[0]) ? $decoded[0] : $decoded;

            if (isset($base['result']['data']['json']) && is_array($base['result']['data']['json'])) {
                $json = $base['result']['data']['json'];
                if (isset($json['productsEntries']) && is_array($json['productsEntries'])) {
                    return $json['productsEntries'];
                }
                if (isset($json['results']) && is_array($json['results'])) {
                    return $json['results'];
                }
                if (isset($json['products']) && is_array($json['products'])) {
                    return $json['products'];
                }
                if (isset($json['data']) && is_array($json['data'])) {
                    return $json['data'];
                }
            }

            // Direct array structures
            if (isset($decoded['productsEntries']) && is_array($decoded['productsEntries'])) {
                return $decoded['productsEntries'];
            }
            if (isset($decoded['results']) && is_array($decoded['results'])) {
                return $decoded['results'];
            }
            if (isset($decoded['products']) && is_array($decoded['products'])) {
                return $decoded['products'];
            }
            if (isset($decoded['data']) && is_array($decoded['data'])) {
                return $decoded['data'];
            }

            // If direct list of items
            if (isset($decoded[0]) && is_array($decoded[0])) {
                return $decoded;
            }

            return [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function execute(array $args, ?array $context = null): array
    {
        $db = \Config\Database::connect();

        if ($this->action === 'get_snapshot_by_date') {
            $dateStr       = $args['date'] ?? null;
            $snapshotId    = $args['snapshot_id'] ?? null;
            $origin        = $args['origin'] ?? 'Winning';
            $countryFilter = isset($args['country']) ? strtoupper($args['country']) : null;
            $limit         = intval($args['limit'] ?? 100);

            $snapshotRow = null;
            if ($snapshotId) {
                $snapshotRow = $db->table('data_snapshots')->where('id', $snapshotId)->get()->getRowArray();
            } elseif (!empty($dateStr)) {
                $escapedDate = $db->escapeLikeString($dateStr);
                $snapshotRow = $db->table('data_snapshots')
                                  ->groupStart()
                                      ->like('api_version', $dateStr)
                                      ->orWhere("CAST(created_at AS TEXT) LIKE '%{$escapedDate}%'")
                                  ->groupEnd()
                                  ->where('origin', $origin)
                                  ->orderBy('id', 'DESC')
                                  ->get()
                                  ->getRowArray();
            } else {
                $snapshotRow = $db->table('data_snapshots')->where('origin', $origin)->orderBy('id', 'DESC')->get()->getRowArray();
            }

            if (!$snapshotRow) {
                return [
                    'status'   => 'error',
                    'total'    => 0,
                    'items'    => [],
                    'products' => [],
                    'error'    => 'No snapshot found matching criteria'
                ];
            }

            $entries = self::parseSnapshotEntries($snapshotRow['raw_json'] ?? '');
            if ($countryFilter) {
                $entries = array_values(array_filter($entries, function($e) use ($countryFilter) {
                    $cList = array_map('trim', explode(';', strtoupper($e['country'] ?? '')));
                    return in_array($countryFilter, $cList, true);
                }));
            }

            $total = count($entries);
            $entries = array_slice($entries, 0, $limit);

            return [
                'status'            => 'success',
                'total'             => $total,
                'items'             => $entries,
                'products'          => $entries,
                'snapshot' => [
                    'id'            => $snapshotRow['id'],
                    'origin'        => $snapshotRow['origin'],
                    'api_version'   => $snapshotRow['api_version'],
                    'product_count' => $snapshotRow['product_count'],
                    'created_at'    => $snapshotRow['created_at']
                ],
                'returned_count'    => count($entries),
                'total_in_snapshot' => $total
            ];
        }

        // Default action: list_snapshots
        $origin = $args['origin'] ?? null;
        $limit  = intval($args['limit'] ?? 20);
        $offset = intval($args['offset'] ?? 0);

        $builder = $db->table('data_snapshots')
                      ->select('id, origin, api_version, product_count, created_at, updated_at');
        if (!empty($origin)) {
            $builder->where('origin', $origin);
        }
        $snapshots = $builder->orderBy('id', 'DESC')->limit($limit, $offset)->get()->getResultArray();

        return [
            'status'    => 'success',
            'total'     => count($snapshots),
            'count'     => count($snapshots),
            'snapshots' => $snapshots
        ];
    }
}
