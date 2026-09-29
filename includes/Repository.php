<?php
declare(strict_types=1);

namespace SuzyWorld;

final class Repository {
    private static function now(): string {
        return gmdate('Y-m-d H:i:s');
    }

    private static function uuid(): string {
        return wp_generate_uuid4();
    }

    public static function canonical(string $value): string {
        $value = remove_accents($value);
        $value = strtolower(trim($value));
        $value = preg_replace('/\s+/u', ' ', $value) ?: $value;
        return sanitize_title($value);
    }

    public static function json(array $value): string {
        return (string) wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function entity_by_public_id(string $publicId): ?array {
        global $wpdb;
        $table = Schema::table('entities');
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE public_id = %s LIMIT 1", $publicId),
            ARRAY_A
        );
        return is_array($row) ? self::hydrate_entity($row) : null;
    }

    public static function entity_by_id(int $id): ?array {
        global $wpdb;
        $table = Schema::table('entities');
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id),
            ARRAY_A
        );
        return is_array($row) ? self::hydrate_entity($row) : null;
    }

    public static function upsert_entity(array $input): array {
        global $wpdb;

        $domain = sanitize_key((string) ($input['domain'] ?? 'world')) ?: 'world';
        $kind = sanitize_key((string) ($input['kind'] ?? 'subject')) ?: 'subject';
        $name = sanitize_text_field((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('Entity name is required.');
        }

        $canonical = sanitize_text_field((string) ($input['canonical_key'] ?? ''));
        if ($canonical === '') {
            $canonical = self::canonical($name);
        } else {
            $canonical = self::canonical($canonical);
        }

        $summary = sanitize_textarea_field((string) ($input['summary'] ?? ''));
        $status = in_array(($input['status'] ?? 'published'), ['draft', 'published'], true)
            ? (string) $input['status']
            : 'draft';

        $metadata = is_array($input['metadata'] ?? null) ? $input['metadata'] : [];
        $metadataJson = self::json($metadata);
        $slug = sanitize_title((string) ($input['slug'] ?? $name));
        $now = self::now();
        $table = Schema::table('entities');

        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE domain = %s AND kind = %s AND canonical_key = %s LIMIT 1",
                $domain,
                $kind,
                $canonical
            ),
            ARRAY_A
        );

        if (is_array($existing)) {
            // A private/draft import must not silently unpublish an entity that was
            // already deliberately public.
            $resolvedStatus = (($existing['status'] ?? '') === 'published' && $status === 'draft')
                ? 'published'
                : $status;

            $wpdb->update(
                $table,
                [
                    'name' => $name,
                    'slug' => $slug,
                    'summary' => $summary,
                    'metadata_json' => $metadataJson,
                    'status' => $resolvedStatus,
                    'updated_at' => $now,
                ],
                ['id' => (int) $existing['id']],
                ['%s', '%s', '%s', '%s', '%s', '%s'],
                ['%d']
            );

            return self::entity_by_id((int) $existing['id']) ?? [];
        }

        $publicId = self::uuid();
        $wpdb->insert(
            $table,
            [
                'public_id' => $publicId,
                'domain' => $domain,
                'kind' => $kind,
                'canonical_key' => $canonical,
                'name' => $name,
                'slug' => $slug,
                'summary' => $summary,
                'metadata_json' => $metadataJson,
                'status' => $status,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        if (!$wpdb->insert_id) {
            throw new \RuntimeException('Could not insert entity.');
        }

        return self::entity_by_id((int) $wpdb->insert_id) ?? [];
    }

    public static function add_statement(array $input): array {
        global $wpdb;

        $entityId = (int) ($input['entity_id'] ?? 0);
        if ($entityId <= 0 || !self::entity_by_id($entityId)) {
            throw new \InvalidArgumentException('Valid entity_id is required.');
        }

        $type = sanitize_key((string) ($input['statement_type'] ?? 'note')) ?: 'note';
        $title = sanitize_text_field((string) ($input['title'] ?? ''));
        $body = wp_kses_post((string) ($input['body'] ?? ''));
        $rating = ($input['rating'] ?? '') === '' || $input['rating'] === null
            ? null
            : (float) $input['rating'];

        $payload = is_array($input['payload'] ?? null) ? $input['payload'] : [];
        $sourceType = sanitize_key((string) ($input['source_type'] ?? 'human')) ?: 'human';
        $sourceRef = sanitize_text_field((string) ($input['source_ref'] ?? ''));
        $status = in_array(($input['status'] ?? 'published'), ['draft', 'published'], true)
            ? (string) $input['status']
            : 'draft';

        $effectiveAt = sanitize_text_field((string) ($input['effective_at'] ?? ''));
        if ($effectiveAt === '') {
            $effectiveAt = null;
        }

        $hashInput = [
            'entity_id' => $entityId,
            'statement_type' => $type,
            'title' => $title,
            'body' => $body,
            'rating' => $rating,
            'payload' => $payload,
            'source_type' => $sourceType,
            'source_ref' => $sourceRef,
            'status' => $status,
        ];
        $contentHash = hash('sha256', self::json($hashInput));
        $table = Schema::table('statements');

        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE entity_id = %d AND statement_type = %s AND content_hash = %s LIMIT 1",
                $entityId,
                $type,
                $contentHash
            ),
            ARRAY_A
        );

        if (is_array($existing)) {
            return [
                'duplicate' => true,
                'statement' => self::hydrate_statement($existing),
            ];
        }

        $version = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(MAX(version), 0) + 1 FROM {$table} WHERE entity_id = %d AND statement_type = %s",
                $entityId,
                $type
            )
        );

        $now = self::now();
        $publicId = self::uuid();

        $wpdb->insert(
            $table,
            [
                'public_id' => $publicId,
                'entity_id' => $entityId,
                'statement_type' => $type,
                'version' => $version,
                'title' => $title,
                'body' => $body,
                'rating' => $rating,
                'payload_json' => self::json($payload),
                'source_type' => $sourceType,
                'source_ref' => $sourceRef,
                'content_hash' => $contentHash,
                'status' => $status,
                'effective_at' => $effectiveAt,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%s', '%d', '%s', '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        if (!$wpdb->insert_id) {
            throw new \RuntimeException('Could not insert statement.');
        }

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", (int) $wpdb->insert_id),
            ARRAY_A
        );

        return [
            'duplicate' => false,
            'statement' => is_array($row) ? self::hydrate_statement($row) : [],
        ];
    }

    public static function upsert_tag(string $name, string $category = 'general', string $description = ''): array {
        global $wpdb;

        $name = sanitize_text_field($name);
        $category = sanitize_key($category) ?: 'general';
        $slug = sanitize_title($name);
        if ($name === '' || $slug === '') {
            throw new \InvalidArgumentException('Tag name is required.');
        }

        $table = Schema::table('tags');
        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE category = %s AND slug = %s LIMIT 1",
                $category,
                $slug
            ),
            ARRAY_A
        );

        $now = self::now();
        if (is_array($existing)) {
            if ($description !== '') {
                $wpdb->update(
                    $table,
                    [
                        'description' => sanitize_textarea_field($description),
                        'updated_at' => $now,
                    ],
                    ['id' => (int) $existing['id']],
                    ['%s', '%s'],
                    ['%d']
                );
            }
            return self::tag_by_id((int) $existing['id']) ?? [];
        }

        $wpdb->insert(
            $table,
            [
                'public_id' => self::uuid(),
                'category' => $category,
                'name' => $name,
                'slug' => $slug,
                'description' => sanitize_textarea_field($description),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        return self::tag_by_id((int) $wpdb->insert_id) ?? [];
    }

    public static function tag_by_id(int $id): ?array {
        global $wpdb;
        $table = Schema::table('tags');
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id),
            ARRAY_A
        );
        return is_array($row) ? self::hydrate_tag($row) : null;
    }

    public static function attach_tag(int $entityId, int $tagId, string $sourceType = 'human'): void {
        global $wpdb;
        $table = Schema::table('entity_tags');
        $wpdb->query(
            $wpdb->prepare(
                "INSERT IGNORE INTO {$table} (entity_id, tag_id, source_type, created_at) VALUES (%d, %d, %s, %s)",
                $entityId,
                $tagId,
                sanitize_key($sourceType) ?: 'human',
                self::now()
            )
        );
    }

    public static function add_relationship(array $input): array {
        global $wpdb;

        $subject = (int) ($input['subject_entity_id'] ?? 0);
        $predicate = sanitize_key((string) ($input['predicate'] ?? ''));
        $object = (int) ($input['object_entity_id'] ?? 0);
        $object = $object > 0 ? $object : null;
        $objectLabel = sanitize_text_field((string) ($input['object_label'] ?? ''));
        $objectUrl = esc_url_raw((string) ($input['object_url'] ?? ''));
        $notes = sanitize_textarea_field((string) ($input['notes'] ?? ''));
        $sourceType = sanitize_key((string) ($input['source_type'] ?? 'human')) ?: 'human';
        $confidence = ($input['confidence'] ?? '') === '' || $input['confidence'] === null
            ? null
            : max(0.0, min(1.0, (float) $input['confidence']));
        $status = in_array(($input['status'] ?? 'published'), ['draft', 'published'], true)
            ? (string) $input['status']
            : 'draft';

        if ($subject <= 0 || $predicate === '') {
            throw new \InvalidArgumentException('subject_entity_id and predicate are required.');
        }

        $hash = hash(
            'sha256',
            self::json([
                'subject' => $subject,
                'predicate' => $predicate,
                'object' => $object,
                'object_label' => $objectLabel,
                'object_url' => $objectUrl,
                'notes' => $notes,
                'source_type' => $sourceType,
                'status' => $status,
            ])
        );

        $table = Schema::table('relationships');
        $existing = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE content_hash = %s LIMIT 1", $hash),
            ARRAY_A
        );

        if (is_array($existing)) {
            return ['duplicate' => true, 'relationship' => self::hydrate_relationship($existing)];
        }

        $now = self::now();
        $wpdb->insert(
            $table,
            [
                'public_id' => self::uuid(),
                'subject_entity_id' => $subject,
                'predicate' => $predicate,
                'object_entity_id' => $object,
                'object_label' => $objectLabel,
                'object_url' => $objectUrl,
                'notes' => $notes,
                'source_type' => $sourceType,
                'confidence' => $confidence,
                'content_hash' => $hash,
                'status' => $status,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%s', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s']
        );

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", (int) $wpdb->insert_id),
            ARRAY_A
        );

        return [
            'duplicate' => false,
            'relationship' => is_array($row) ? self::hydrate_relationship($row) : [],
        ];
    }

    public static function add_asset(array $input): array {
        global $wpdb;

        $entityId = (int) ($input['entity_id'] ?? 0);
        $url = esc_url_raw((string) ($input['url'] ?? ''));
        if ($entityId <= 0 || $url === '') {
            throw new \InvalidArgumentException('entity_id and url are required.');
        }

        $kind = sanitize_key((string) ($input['kind'] ?? 'image')) ?: 'image';
        $alt = sanitize_text_field((string) ($input['alt_text'] ?? ''));
        $creator = sanitize_text_field((string) ($input['creator'] ?? ''));
        $credit = sanitize_text_field((string) ($input['credit'] ?? ''));
        $sourceUrl = esc_url_raw((string) ($input['source_url'] ?? ''));
        $rightsBasis = sanitize_key((string) ($input['rights_basis'] ?? 'unknown')) ?: 'unknown';
        $rightsNote = sanitize_textarea_field((string) ($input['rights_note'] ?? ''));
        $displayOk = !empty($input['display_ok']) ? 1 : 0;

        $hash = hash(
            'sha256',
            self::json([
                'entity_id' => $entityId,
                'kind' => $kind,
                'url' => $url,
                'credit' => $credit,
                'source_url' => $sourceUrl,
                'rights_basis' => $rightsBasis,
            ])
        );

        $table = Schema::table('assets');
        $existing = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE content_hash = %s LIMIT 1", $hash),
            ARRAY_A
        );

        if (is_array($existing)) {
            return ['duplicate' => true, 'asset' => self::hydrate_asset($existing)];
        }

        $now = self::now();
        $wpdb->insert(
            $table,
            [
                'public_id' => self::uuid(),
                'entity_id' => $entityId,
                'kind' => $kind,
                'url' => $url,
                'attachment_id' => null,
                'alt_text' => $alt,
                'creator' => $creator,
                'credit' => $credit,
                'source_url' => $sourceUrl,
                'rights_basis' => $rightsBasis,
                'rights_note' => $rightsNote,
                'display_ok' => $displayOk,
                'content_hash' => $hash,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s']
        );

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", (int) $wpdb->insert_id),
            ARRAY_A
        );

        return [
            'duplicate' => false,
            'asset' => is_array($row) ? self::hydrate_asset($row) : [],
        ];
    }

    public static function entities(array $filters = []): array {
        global $wpdb;
        $table = Schema::table('entities');

        $where = ["status = 'published'"];
        $args = [];

        if (!empty($filters['domain'])) {
            $where[] = 'domain = %s';
            $args[] = sanitize_key((string) $filters['domain']);
        }
        if (!empty($filters['kind'])) {
            $where[] = 'kind = %s';
            $args[] = sanitize_key((string) $filters['kind']);
        }
        if (!empty($filters['search'])) {
            $like = '%' . $wpdb->esc_like((string) $filters['search']) . '%';
            $where[] = '(name LIKE %s OR summary LIKE %s)';
            $args[] = $like;
            $args[] = $like;
        }

        $limit = max(1, min(100, (int) ($filters['limit'] ?? 50)));
        $offset = max(0, (int) ($filters['offset'] ?? 0));

        $sql = "SELECT * FROM {$table} WHERE " . implode(' AND ', $where) . ' ORDER BY updated_at DESC LIMIT %d OFFSET %d';
        $args[] = $limit;
        $args[] = $offset;

        $prepared = $wpdb->prepare($sql, ...$args);
        $rows = $wpdb->get_results($prepared, ARRAY_A);

        return array_map([self::class, 'hydrate_entity'], is_array($rows) ? $rows : []);
    }

    public static function tags_for_entity(int $entityId): array {
        global $wpdb;
        $tags = Schema::table('tags');
        $join = Schema::table('entity_tags');

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT t.* FROM {$tags} t INNER JOIN {$join} et ON et.tag_id = t.id WHERE et.entity_id = %d ORDER BY t.category, t.name",
                $entityId
            ),
            ARRAY_A
        );

        return array_map([self::class, 'hydrate_tag'], is_array($rows) ? $rows : []);
    }

    public static function statements_for_entity(int $entityId, bool $publicOnly = true): array {
        global $wpdb;
        $table = Schema::table('statements');
        $where = $publicOnly ? " AND status = 'published'" : '';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE entity_id = %d {$where} ORDER BY statement_type, version DESC",
                $entityId
            ),
            ARRAY_A
        );

        return array_map([self::class, 'hydrate_statement'], is_array($rows) ? $rows : []);
    }

    public static function latest_statement(int $entityId, string $type): ?array {
        global $wpdb;
        $table = Schema::table('statements');
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE entity_id = %d AND statement_type = %s AND status = 'published' ORDER BY version DESC LIMIT 1",
                $entityId,
                sanitize_key($type)
            ),
            ARRAY_A
        );

        return is_array($row) ? self::hydrate_statement($row) : null;
    }

    public static function relationships_for_entity(int $entityId): array {
        global $wpdb;
        $table = Schema::table('relationships');
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE subject_entity_id = %d AND status = 'published' ORDER BY created_at DESC",
                $entityId
            ),
            ARRAY_A
        );

        return array_map([self::class, 'hydrate_relationship'], is_array($rows) ? $rows : []);
    }

    public static function assets_for_entity(int $entityId): array {
        global $wpdb;
        $table = Schema::table('assets');
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE entity_id = %d ORDER BY created_at DESC",
                $entityId
            ),
            ARRAY_A
        );

        return array_map([self::class, 'hydrate_asset'], is_array($rows) ? $rows : []);
    }

    public static function public_entity_detail(array $entity): array {
        $id = (int) ($entity['id'] ?? 0);
        $entity['tags'] = self::tags_for_entity($id);
        $entity['statements'] = self::statements_for_entity($id, true);
        $entity['relationships'] = self::relationships_for_entity($id);
        $entity['assets'] = array_values(
            array_filter(
                self::assets_for_entity($id),
                static fn(array $asset): bool => !empty($asset['display_ok'])
            )
        );
        unset($entity['id']);
        return $entity;
    }

    public static function albums(int $limit = 50): array {
        $entities = self::entities([
            'domain' => 'music',
            'kind' => 'album',
            'limit' => $limit,
        ]);

        $result = [];
        foreach ($entities as $entity) {
            $id = (int) $entity['id'];
            $metadata = is_array($entity['metadata']) ? $entity['metadata'] : [];
            $latest = self::latest_statement($id, 'album_review');
            $assets = array_values(
                array_filter(
                    self::assets_for_entity($id),
                    static fn(array $asset): bool => !empty($asset['display_ok'])
                )
            );

            $result[] = [
                'id' => $entity['public_id'],
                'name' => $entity['name'],
                'artist' => (string) ($metadata['artist'] ?? ''),
                'album' => (string) ($metadata['album'] ?? $entity['name']),
                'year' => $metadata['year'] ?? null,
                'summary' => $entity['summary'],
                'tags' => self::tags_for_entity($id),
                'latest_review' => $latest,
                'assets' => $assets,
                'updated_at' => $entity['updated_at'],
            ];
        }

        return $result;
    }

    public static function counts(): array {
        global $wpdb;
        $result = [];
        foreach (['entities', 'statements', 'tags', 'relationships', 'assets', 'imports'] as $short) {
            $table = Schema::table($short);
            $result[$short] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        }
        return $result;
    }

    private static function hydrate_entity(array $row): array {
        $row['id'] = (int) $row['id'];
        $row['metadata'] = self::decode((string) ($row['metadata_json'] ?? ''));
        unset($row['metadata_json']);
        return $row;
    }

    private static function hydrate_statement(array $row): array {
        $row['id'] = (int) $row['id'];
        $row['entity_id'] = (int) $row['entity_id'];
        $row['version'] = (int) $row['version'];
        $row['rating'] = $row['rating'] === null ? null : (float) $row['rating'];
        $row['payload'] = self::decode((string) ($row['payload_json'] ?? ''));
        unset($row['payload_json'], $row['content_hash']);
        return $row;
    }

    private static function hydrate_tag(array $row): array {
        $row['id'] = (int) $row['id'];
        return $row;
    }

    private static function hydrate_relationship(array $row): array {
        $row['id'] = (int) $row['id'];
        $row['subject_entity_id'] = (int) $row['subject_entity_id'];
        $row['object_entity_id'] = $row['object_entity_id'] === null ? null : (int) $row['object_entity_id'];
        $row['confidence'] = $row['confidence'] === null ? null : (float) $row['confidence'];
        unset($row['content_hash']);
        return $row;
    }

    private static function hydrate_asset(array $row): array {
        $row['id'] = (int) $row['id'];
        $row['entity_id'] = (int) $row['entity_id'];
        $row['display_ok'] = (bool) $row['display_ok'];
        unset($row['content_hash']);
        return $row;
    }

    private static function decode(string $json): array {
        if ($json === '') {
            return [];
        }
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }
}
