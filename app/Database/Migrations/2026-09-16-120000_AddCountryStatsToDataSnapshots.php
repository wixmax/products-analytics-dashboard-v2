<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCountryStatsToDataSnapshots extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('data_snapshots')) {
            if (!$this->db->fieldExists('country_stats', 'data_snapshots')) {
                $this->forge->addColumn('data_snapshots', [
                    'country_stats' => [
                        'type'       => 'TEXT',
                        'null'       => true,
                        'default'    => null,
                        'after'      => 'product_count',
                    ],
                ]);
            }

            // Backfill country_stats for existing snapshots
            $snapshots = $this->db->table('data_snapshots')
                ->select('id, origin, raw_json')
                ->where('country_stats IS NULL OR country_stats = \'\'')
                ->get()
                ->getResultArray();

            foreach ($snapshots as $snap) {
                $rawJson = $snap['raw_json'] ?? '';
                if (empty($rawJson)) {
                    continue;
                }

                if (str_starts_with($rawJson, '__GZ64__:')) {
                    $rawJson = @gzdecode(base64_decode(substr($rawJson, 9)));
                }

                $stats = $this->calculateStats($rawJson ?: '', $snap['origin'] ?? '');
                if (!empty($stats)) {
                    $this->db->table('data_snapshots')
                        ->where('id', $snap['id'])
                        ->update(['country_stats' => json_encode($stats, JSON_UNESCAPED_UNICODE)]);
                }
            }
        }
    }

    public function down()
    {
        if ($this->db->tableExists('data_snapshots')) {
            if ($this->db->fieldExists('country_stats', 'data_snapshots')) {
                $this->forge->dropColumn('data_snapshots', 'country_stats');
            }
        }
    }

    private function calculateStats(string $rawJson, string $origin): array
    {
        $decoded = json_decode($rawJson, true);
        if (!is_array($decoded)) {
            return [];
        }

        $base = (isset($decoded[0]) && is_array($decoded[0])) ? $decoded[0] : $decoded;
        $entries = [];

        foreach (['result.data.json', 'data.json', 'json', ''] as $path) {
            $target = $base;
            if ($path !== '') {
                $parts = explode('.', $path);
                foreach ($parts as $p) {
                    if (isset($target[$p])) {
                        $target = $target[$p];
                    } else {
                        $target = null;
                        break;
                    }
                }
            }
            if ($target === null) {
                continue;
            }

            if (is_array($target) && array_is_list($target) && !empty($target) && is_array($target[0]) && (isset($target[0]['product_url']) || isset($target[0]['productUrl']) || isset($target[0]['title']) || isset($target[0]['product_title']))) {
                $entries = $target;
                break;
            }
            if (isset($target['productsEntries']) && is_array($target['productsEntries'])) {
                $entries = $target['productsEntries'];
                break;
            }
            if (isset($target['results']) && is_array($target['results'])) {
                $entries = $target['results'];
                break;
            }
        }

        if (empty($entries) && is_array($decoded) && isset($decoded[0]) && is_array($decoded[0]) && (isset($decoded[0]['product_url']) || isset($decoded[0]['productUrl']) || isset($decoded[0]['title']))) {
            $entries = $decoded;
        }

        if (empty($entries)) {
            return [];
        }

        $counts = [];
        foreach ($entries as $p) {
            $c = trim($p['country'] ?? '');
            if ($c === '') {
                if ($origin === 'China') {
                    $c = 'CN';
                } elseif ($origin === 'Japan') {
                    $c = 'JP';
                } elseif ($origin === 'Local') {
                    $c = 'MA';
                } else {
                    $c = 'UNKNOWN';
                }
            }

            $parts = preg_split('/[;,]/', $c);
            foreach ($parts as $part) {
                $code = strtoupper(trim($part));
                if ($code !== '') {
                    $counts[$code] = ($counts[$code] ?? 0) + 1;
                }
            }
        }

        arsort($counts);
        return $counts;
    }
}
