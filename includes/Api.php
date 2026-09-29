<?php
declare(strict_types=1);

namespace SuzyWorld;

use WP_REST_Request;
use WP_REST_Response;

final class Api {
    private const NS = 'suzy-world/v1';

    public static function bootstrap(): void {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    public static function register_routes(): void {
        register_rest_route(
            self::NS,
            '/schema',
            [
                'methods' => 'GET',
                'permission_callback' => '__return_true',
                'callback' => [self::class, 'schema'],
            ]
        );

        register_rest_route(
            self::NS,
            '/entities',
            [
                'methods' => 'GET',
                'permission_callback' => '__return_true',
                'callback' => [self::class, 'entities'],
                'args' => [
                    'domain' => ['sanitize_callback' => 'sanitize_key'],
                    'kind' => ['sanitize_callback' => 'sanitize_key'],
                    'search' => ['sanitize_callback' => 'sanitize_text_field'],
                    'limit' => ['sanitize_callback' => 'absint'],
                    'offset' => ['sanitize_callback' => 'absint'],
                ],
            ]
        );

        register_rest_route(
            self::NS,
            '/entities/(?P<id>[a-f0-9-]{36})',
            [
                'methods' => 'GET',
                'permission_callback' => '__return_true',
                'callback' => [self::class, 'entity'],
            ]
        );

        register_rest_route(
            self::NS,
            '/albums',
            [
                'methods' => 'GET',
                'permission_callback' => '__return_true',
                'callback' => [self::class, 'albums'],
                'args' => [
                    'limit' => ['sanitize_callback' => 'absint'],
                ],
            ]
        );

        register_rest_route(
            self::NS,
            '/tags',
            [
                'methods' => 'GET',
                'permission_callback' => '__return_true',
                'callback' => [self::class, 'tags'],
            ]
        );

        register_rest_route(
            self::NS,
            '/entities',
            [
                'methods' => 'POST',
                'permission_callback' => [self::class, 'can_write'],
                'callback' => [self::class, 'create_entity'],
            ]
        );

        register_rest_route(
            self::NS,
            '/statements',
            [
                'methods' => 'POST',
                'permission_callback' => [self::class, 'can_write'],
                'callback' => [self::class, 'create_statement'],
            ]
        );

        register_rest_route(
            self::NS,
            '/relationships',
            [
                'methods' => 'POST',
                'permission_callback' => [self::class, 'can_write'],
                'callback' => [self::class, 'create_relationship'],
            ]
        );

        register_rest_route(
            self::NS,
            '/imports/albums',
            [
                'methods' => 'POST',
                'permission_callback' => [self::class, 'can_write'],
                'callback' => [self::class, 'import_albums'],
            ]
        );
    }

    public static function can_write(): bool {
        return current_user_can('manage_options');
    }

    public static function schema(): WP_REST_Response {
        return new WP_REST_Response(
            [
                'name' => 'SUZY//WORLD',
                'version' => SUZY_WORLD_VERSION,
                'db_version' => SUZY_WORLD_DB_VERSION,
                'mode' => 'public-read-explicit-write',
                'tables' => Schema::diagnostics(),
                'resources' => [
                    'entities' => rest_url(self::NS . '/entities'),
                    'albums' => rest_url(self::NS . '/albums'),
                    'tags' => rest_url(self::NS . '/tags'),
                ],
                'write_auth' => 'WordPress authenticated administrator; Application Passwords work for external clients.',
            ],
            200
        );
    }

    public static function entities(WP_REST_Request $request): WP_REST_Response {
        $items = Repository::entities([
            'domain' => (string) $request->get_param('domain'),
            'kind' => (string) $request->get_param('kind'),
            'search' => (string) $request->get_param('search'),
            'limit' => (int) ($request->get_param('limit') ?: 50),
            'offset' => (int) ($request->get_param('offset') ?: 0),
        ]);

        foreach ($items as &$item) {
            unset($item['id']);
        }

        return new WP_REST_Response(['items' => $items], 200);
    }

    public static function entity(WP_REST_Request $request): WP_REST_Response {
        $entity = Repository::entity_by_public_id((string) $request['id']);
        if (!$entity || ($entity['status'] ?? '') !== 'published') {
            return new WP_REST_Response(['error' => 'not_found'], 404);
        }

        return new WP_REST_Response(Repository::public_entity_detail($entity), 200);
    }

    public static function albums(WP_REST_Request $request): WP_REST_Response {
        $limit = max(1, min(100, (int) ($request->get_param('limit') ?: 50)));
        return new WP_REST_Response(['items' => Repository::albums($limit)], 200);
    }

    public static function tags(): WP_REST_Response {
        global $wpdb;
        $table = Schema::table('tags');
        $join = Schema::table('entity_tags');
        $entities = Schema::table('entities');
        $rows = $wpdb->get_results(
            "SELECT DISTINCT t.public_id, t.category, t.name, t.slug, t.description, t.created_at, t.updated_at
             FROM {$table} t
             INNER JOIN {$join} et ON et.tag_id = t.id
             INNER JOIN {$entities} e ON e.id = et.entity_id
             WHERE e.status = 'published'
             ORDER BY t.category, t.name",
            ARRAY_A
        );

        return new WP_REST_Response(['items' => is_array($rows) ? $rows : []], 200);
    }

    public static function create_entity(WP_REST_Request $request): WP_REST_Response {
        try {
            $entity = Repository::upsert_entity((array) $request->get_json_params());
            unset($entity['id']);
            return new WP_REST_Response($entity, 201);
        } catch (\Throwable $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }

    public static function create_statement(WP_REST_Request $request): WP_REST_Response {
        $payload = (array) $request->get_json_params();

        if (!empty($payload['entity_public_id']) && empty($payload['entity_id'])) {
            $entity = Repository::entity_by_public_id((string) $payload['entity_public_id']);
            if (!$entity) {
                return new WP_REST_Response(['error' => 'entity_not_found'], 404);
            }
            $payload['entity_id'] = (int) $entity['id'];
        }

        try {
            $result = Repository::add_statement($payload);
            if (isset($result['statement']['entity_id'])) {
                unset($result['statement']['entity_id']);
            }
            return new WP_REST_Response($result, !empty($result['duplicate']) ? 200 : 201);
        } catch (\Throwable $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }

    public static function create_relationship(WP_REST_Request $request): WP_REST_Response {
        $payload = (array) $request->get_json_params();

        try {
            $result = Repository::add_relationship($payload);
            return new WP_REST_Response($result, !empty($result['duplicate']) ? 200 : 201);
        } catch (\Throwable $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }

    public static function import_albums(WP_REST_Request $request): WP_REST_Response {
        $payload = (array) $request->get_json_params();
        $rows = $payload['albums'] ?? null;
        if (!is_array($rows)) {
            return new WP_REST_Response(['error' => 'albums array is required'], 400);
        }

        $publish = !empty($payload['publish']);

        try {
            $result = Importer::import_album_rows($rows, 'api.json', 'json', $publish);
            return new WP_REST_Response($result, 201);
        } catch (\Throwable $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }
}
