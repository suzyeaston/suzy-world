<?php
declare(strict_types=1);

namespace SuzyWorld;

final class Schema {
    public static function table(string $name): string {
        global $wpdb;
        return $wpdb->prefix . 'sw_' . $name;
    }

    public static function install(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();

        $entities = self::table('entities');
        $statements = self::table('statements');
        $tags = self::table('tags');
        $entityTags = self::table('entity_tags');
        $relationships = self::table('relationships');
        $assets = self::table('assets');
        $imports = self::table('imports');

        dbDelta(
            "CREATE TABLE {$entities} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                public_id char(36) NOT NULL,
                domain varchar(40) NOT NULL DEFAULT 'world',
                kind varchar(60) NOT NULL,
                canonical_key varchar(191) NOT NULL,
                name varchar(255) NOT NULL,
                slug varchar(191) NOT NULL,
                summary text NULL,
                metadata_json longtext NULL,
                status varchar(20) NOT NULL DEFAULT 'published',
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY public_id (public_id),
                UNIQUE KEY domain_kind_canonical (domain,kind,canonical_key),
                KEY domain_kind (domain,kind),
                KEY status (status),
                KEY slug (slug)
            ) {$charset};"
        );

        dbDelta(
            "CREATE TABLE {$statements} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                public_id char(36) NOT NULL,
                entity_id bigint(20) unsigned NOT NULL,
                statement_type varchar(80) NOT NULL,
                version int(10) unsigned NOT NULL DEFAULT 1,
                title varchar(255) NOT NULL DEFAULT '',
                body longtext NULL,
                rating decimal(5,2) NULL,
                payload_json longtext NULL,
                source_type varchar(40) NOT NULL DEFAULT 'human',
                source_ref varchar(191) NOT NULL DEFAULT '',
                content_hash char(64) NOT NULL,
                status varchar(20) NOT NULL DEFAULT 'published',
                effective_at datetime NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY public_id (public_id),
                UNIQUE KEY entity_type_version (entity_id,statement_type,version),
                UNIQUE KEY entity_type_hash (entity_id,statement_type,content_hash),
                KEY entity_id (entity_id),
                KEY statement_type (statement_type),
                KEY status (status),
                KEY created_at (created_at)
            ) {$charset};"
        );

        dbDelta(
            "CREATE TABLE {$tags} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                public_id char(36) NOT NULL,
                category varchar(40) NOT NULL DEFAULT 'general',
                name varchar(191) NOT NULL,
                slug varchar(191) NOT NULL,
                description text NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY public_id (public_id),
                UNIQUE KEY category_slug (category,slug),
                KEY category (category)
            ) {$charset};"
        );

        dbDelta(
            "CREATE TABLE {$entityTags} (
                entity_id bigint(20) unsigned NOT NULL,
                tag_id bigint(20) unsigned NOT NULL,
                source_type varchar(40) NOT NULL DEFAULT 'human',
                created_at datetime NOT NULL,
                PRIMARY KEY  (entity_id,tag_id),
                KEY tag_id (tag_id)
            ) {$charset};"
        );

        dbDelta(
            "CREATE TABLE {$relationships} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                public_id char(36) NOT NULL,
                subject_entity_id bigint(20) unsigned NOT NULL,
                predicate varchar(80) NOT NULL,
                object_entity_id bigint(20) unsigned NULL,
                object_label varchar(255) NOT NULL DEFAULT '',
                object_url text NULL,
                notes text NULL,
                source_type varchar(40) NOT NULL DEFAULT 'human',
                confidence decimal(5,4) NULL,
                content_hash char(64) NOT NULL,
                status varchar(20) NOT NULL DEFAULT 'published',
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY public_id (public_id),
                UNIQUE KEY content_hash (content_hash),
                KEY subject_predicate (subject_entity_id,predicate),
                KEY object_entity_id (object_entity_id),
                KEY status (status)
            ) {$charset};"
        );

        dbDelta(
            "CREATE TABLE {$assets} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                public_id char(36) NOT NULL,
                entity_id bigint(20) unsigned NOT NULL,
                kind varchar(40) NOT NULL DEFAULT 'image',
                url text NOT NULL,
                attachment_id bigint(20) unsigned NULL,
                alt_text varchar(255) NOT NULL DEFAULT '',
                creator varchar(255) NOT NULL DEFAULT '',
                credit varchar(255) NOT NULL DEFAULT '',
                source_url text NULL,
                rights_basis varchar(80) NOT NULL DEFAULT 'unknown',
                rights_note text NULL,
                display_ok tinyint(1) NOT NULL DEFAULT 0,
                content_hash char(64) NOT NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY public_id (public_id),
                UNIQUE KEY content_hash (content_hash),
                KEY entity_id (entity_id),
                KEY kind (kind),
                KEY display_ok (display_ok)
            ) {$charset};"
        );

        dbDelta(
            "CREATE TABLE {$imports} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                public_id char(36) NOT NULL,
                user_id bigint(20) unsigned NOT NULL,
                filename varchar(255) NOT NULL DEFAULT '',
                format varchar(20) NOT NULL,
                status varchar(30) NOT NULL DEFAULT 'started',
                total_rows int(10) unsigned NOT NULL DEFAULT 0,
                inserted_rows int(10) unsigned NOT NULL DEFAULT 0,
                duplicate_rows int(10) unsigned NOT NULL DEFAULT 0,
                error_rows int(10) unsigned NOT NULL DEFAULT 0,
                summary_json longtext NULL,
                created_at datetime NOT NULL,
                completed_at datetime NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY public_id (public_id),
                KEY user_id (user_id),
                KEY created_at (created_at)
            ) {$charset};"
        );

        update_option('suzy_world_db_version', SUZY_WORLD_DB_VERSION, false);
    }

    public static function diagnostics(): array {
        global $wpdb;

        $tables = [
            'entities',
            'statements',
            'tags',
            'entity_tags',
            'relationships',
            'assets',
            'imports',
        ];

        $result = [];
        foreach ($tables as $short) {
            $table = self::table($short);
            $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
            $result[$short] = [
                'table' => $table,
                'exists' => $exists,
            ];
        }

        return $result;
    }
}
