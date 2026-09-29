<?php
declare(strict_types=1);

namespace SuzyWorld;

final class Importer {
    public static function import_file(string $path, string $filename, bool $publish): array {
        $extension = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));

        if ($extension === 'csv') {
            $rows = self::parse_csv($path);
        } elseif ($extension === 'json') {
            $rows = self::parse_json($path);
        } else {
            throw new \InvalidArgumentException('Only CSV and JSON imports are supported.');
        }

        return self::import_album_rows($rows, $filename, $extension, $publish);
    }

    public static function import_album_rows(array $rows, string $filename = 'api.json', string $format = 'json', bool $publish = false): array {
        global $wpdb;

        $imports = Schema::table('imports');
        $publicId = wp_generate_uuid4();
        $now = gmdate('Y-m-d H:i:s');
        $userId = get_current_user_id();

        $wpdb->insert(
            $imports,
            [
                'public_id' => $publicId,
                'user_id' => $userId,
                'filename' => sanitize_file_name($filename),
                'format' => sanitize_key($format),
                'status' => 'running',
                'total_rows' => count($rows),
                'inserted_rows' => 0,
                'duplicate_rows' => 0,
                'error_rows' => 0,
                'summary_json' => '{}',
                'created_at' => $now,
                'completed_at' => null,
            ],
            ['%s', '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s']
        );

        $jobId = (int) $wpdb->insert_id;
        $summary = [
            'total' => count($rows),
            'inserted' => 0,
            'duplicates' => 0,
            'errors' => 0,
            'messages' => [],
        ];

        foreach ($rows as $index => $row) {
            try {
                $result = self::import_album_row(is_array($row) ? $row : [], $publish);
                if (!empty($result['duplicate_review'])) {
                    $summary['duplicates']++;
                } else {
                    $summary['inserted']++;
                }
            } catch (\Throwable $e) {
                $summary['errors']++;
                if (count($summary['messages']) < 20) {
                    $summary['messages'][] = 'Row ' . ($index + 1) . ': ' . $e->getMessage();
                }
            }
        }

        $wpdb->update(
            $imports,
            [
                'status' => $summary['errors'] > 0 ? 'completed_with_errors' : 'completed',
                'inserted_rows' => $summary['inserted'],
                'duplicate_rows' => $summary['duplicates'],
                'error_rows' => $summary['errors'],
                'summary_json' => Repository::json($summary),
                'completed_at' => gmdate('Y-m-d H:i:s'),
            ],
            ['id' => $jobId],
            ['%s', '%d', '%d', '%d', '%s', '%s'],
            ['%d']
        );

        $summary['import_id'] = $publicId;
        return $summary;
    }

    public static function import_album_row(array $row, bool $publish): array {
        $artist = sanitize_text_field((string) ($row['artist'] ?? ''));
        $album = sanitize_text_field((string) ($row['album'] ?? ''));

        if ($artist === '' || $album === '') {
            throw new \InvalidArgumentException('artist and album are required');
        }

        $status = $publish ? 'published' : 'draft';
        $year = isset($row['year']) && $row['year'] !== '' ? (int) $row['year'] : null;

        $artistEntity = Repository::upsert_entity([
            'domain' => 'music',
            'kind' => 'artist',
            'canonical_key' => $artist,
            'name' => $artist,
            'status' => $status,
            'metadata' => [
                'artist' => $artist,
            ],
        ]);

        $albumEntity = Repository::upsert_entity([
            'domain' => 'music',
            'kind' => 'album',
            'canonical_key' => $artist . '::' . $album,
            'name' => $album,
            'summary' => sanitize_textarea_field((string) ($row['summary'] ?? '')),
            'status' => $status,
            'metadata' => [
                'artist' => $artist,
                'artist_id' => $artistEntity['public_id'] ?? '',
                'album' => $album,
                'year' => $year,
            ],
        ]);

        Repository::add_relationship([
            'subject_entity_id' => (int) $albumEntity['id'],
            'predicate' => 'by_artist',
            'object_entity_id' => (int) $artistEntity['id'],
            'source_type' => 'import',
            'status' => $status,
        ]);

        $payload = [
            'sonic_notes' => sanitize_textarea_field((string) ($row['sonic_notes'] ?? '')),
            'favorite_tracks' => self::list_field($row['favorite_tracks'] ?? []),
            'associations' => self::list_field($row['associations'] ?? []),
            'lesson' => sanitize_textarea_field((string) ($row['lesson'] ?? '')),
        ];

        $review = Repository::add_statement([
            'entity_id' => (int) $albumEntity['id'],
            'statement_type' => 'album_review',
            'title' => sanitize_text_field((string) ($row['review_title'] ?? '')),
            'body' => (string) ($row['review'] ?? ''),
            'rating' => ($row['rating'] ?? '') === '' ? null : (float) $row['rating'],
            'payload' => $payload,
            'source_type' => 'human',
            'source_ref' => sanitize_text_field((string) ($row['source_ref'] ?? 'import')),
            'status' => $status,
            'effective_at' => sanitize_text_field((string) ($row['effective_at'] ?? '')),
        ]);

        foreach (self::tag_field($row['tags'] ?? '') as $tagSpec) {
            $tag = Repository::upsert_tag(
                $tagSpec['name'],
                $tagSpec['category']
            );
            Repository::attach_tag((int) $albumEntity['id'], (int) $tag['id'], 'import');
        }

        $coverUrl = esc_url_raw((string) ($row['cover_url'] ?? ''));
        if ($coverUrl !== '') {
            Repository::add_asset([
                'entity_id' => (int) $albumEntity['id'],
                'kind' => 'album_cover',
                'url' => $coverUrl,
                'alt_text' => sanitize_text_field((string) ($row['cover_alt'] ?? ($artist . ' — ' . $album))),
                'creator' => sanitize_text_field((string) ($row['cover_creator'] ?? '')),
                'credit' => sanitize_text_field((string) ($row['cover_credit'] ?? '')),
                'source_url' => esc_url_raw((string) ($row['cover_source_url'] ?? '')),
                'rights_basis' => sanitize_key((string) ($row['cover_rights'] ?? 'unknown')) ?: 'unknown',
                'rights_note' => sanitize_textarea_field((string) ($row['cover_rights_note'] ?? '')),
                'display_ok' => self::bool_field($row['cover_display'] ?? false),
            ]);
        }

        return [
            'artist' => $artistEntity,
            'album' => $albumEntity,
            'duplicate_review' => (bool) ($review['duplicate'] ?? false),
            'review' => $review['statement'] ?? [],
        ];
    }

    private static function parse_csv(string $path): array {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('Could not open CSV.');
        }

        $header = fgetcsv($handle);
        if (!is_array($header)) {
            fclose($handle);
            throw new \InvalidArgumentException('CSV requires a header row.');
        }

        $header = array_map(
            static fn($value): string => sanitize_key((string) $value),
            $header
        );

        $rows = [];
        while (($values = fgetcsv($handle)) !== false) {
            if ($values === [null] || $values === false) {
                continue;
            }

            $row = [];
            foreach ($header as $index => $key) {
                if ($key === '') {
                    continue;
                }
                $row[$key] = $values[$index] ?? '';
            }
            $rows[] = $row;
        }

        fclose($handle);
        return $rows;
    }

    private static function parse_json(string $path): array {
        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new \RuntimeException('Could not read JSON.');
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new \InvalidArgumentException('JSON must decode to an object or array.');
        }

        if (isset($decoded['albums']) && is_array($decoded['albums'])) {
            return $decoded['albums'];
        }

        if (array_is_list($decoded)) {
            return $decoded;
        }

        return [$decoded];
    }

    private static function list_field($value): array {
        if (is_array($value)) {
            return array_values(
                array_filter(
                    array_map('sanitize_text_field', $value),
                    static fn(string $v): bool => $v !== ''
                )
            );
        }

        $value = (string) $value;
        $parts = preg_split('/[;|]+/', $value) ?: [];
        return array_values(
            array_filter(
                array_map('sanitize_text_field', $parts),
                static fn(string $v): bool => $v !== ''
            )
        );
    }

    private static function tag_field($value): array {
        $items = self::list_field($value);
        $result = [];

        foreach ($items as $item) {
            $category = 'general';
            $name = $item;

            if (str_contains($item, ':')) {
                [$candidate, $rest] = array_map('trim', explode(':', $item, 2));
                if ($candidate !== '' && $rest !== '') {
                    $category = sanitize_key($candidate) ?: 'general';
                    $name = $rest;
                }
            }

            if ($name !== '') {
                $result[] = [
                    'category' => $category,
                    'name' => $name,
                ];
            }
        }

        return $result;
    }

    private static function bool_field($value): bool {
        if (is_bool($value)) {
            return $value;
        }
        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'y', 'on'], true);
    }
}
