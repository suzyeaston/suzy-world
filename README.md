# SUZY//WORLD

**Public knowledge. Private intelligence.**

SUZY//WORLD is the deliberately published knowledge layer for [SUZY//AI](https://github.com/suzyeaston/suzy-ai).

It is an open-source WordPress plugin backed by normal WordPress MariaDB/MySQL storage.

```text
                    PRIVATE
                   SUZY//AI
                      │
              explicit publish
                      │
                      ▼
                    PUBLIC
                 SUZY//WORLD
                      │
          ┌───────────┼───────────┐
          ▼           ▼           ▼
      WordPress      API         apps
```

The public database is not a dump of private AI memory.

## First world: music

The first importer handles:

- artists
- albums
- versioned reviews
- structured tags
- relationships
- cover-art metadata / rights notes
- import history

Exact duplicate reviews are ignored. Changed reviews become new versions.

## REST API

After activation:

```text
GET /wp-json/suzy-world/v1/schema
GET /wp-json/suzy-world/v1/entities
GET /wp-json/suzy-world/v1/entities/{uuid}
GET /wp-json/suzy-world/v1/albums
GET /wp-json/suzy-world/v1/tags
```

Authenticated administrator writes:

```text
POST /wp-json/suzy-world/v1/entities
POST /wp-json/suzy-world/v1/statements
POST /wp-json/suzy-world/v1/relationships
POST /wp-json/suzy-world/v1/imports/albums
```

For external write clients, use a WordPress **Application Password** for the publishing account.

## WordPress admin

Use the top-level **SUZY//WORLD** menu in WordPress admin.

The main screen has a human-friendly **Add an album** form. CSV/JSON import remains available for bulk work.

New entries can be saved as drafts or deliberately published.

## Public album page

Create a WordPress page and add:

```text
[suzy_world_albums]
```

## Database

The plugin creates seven prefixed tables:

```text
sw_entities
sw_statements
sw_tags
sw_entity_tags
sw_relationships
sw_assets
sw_imports
```

WordPress adds the site's actual table prefix automatically.

See [`docs/DATA-MODEL.md`](docs/DATA-MODEL.md).

## Album artwork

The database separates the image URL from its rights/credit metadata.

An imported cover is not displayed merely because a URL exists. `display_ok` must be explicitly true.

This is a product-control mechanism, not a legal determination about whether a particular image use is permitted.

## Open source

Apache-2.0.


<!-- ALBUM-FORM-V011 -->

## Human-friendly album editor

WordPress now has a top-level **SUZY//WORLD** admin menu.

Use **Add an album** for normal one-at-a-time reviews.

The same importer/repository path is used by:

- the WordPress form,
- CSV / JSON bulk imports,
- future authenticated API writes.

This keeps entity identity, tags, review versioning, and deduplication consistent regardless of how a record enters the system.

<!-- AI-RESEARCH-ENTRYPOINT -->

## AI / research entry point

For future research, continuation, or AI-assisted work, start with:

- [`AI-CONTEXT.md`](AI-CONTEXT.md) — compact current project state and canonical architecture.
- [`docs/research/README.md`](docs/research/README.md) — chronological research index.
- [`docs/research/index.json`](docs/research/index.json) — machine-readable document manifest.

Research notes are dated `YYYY-MM-DD-topic.md` so the design history remains reconstructable instead of being silently overwritten.
