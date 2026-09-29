---
title: "SUZY//WORLD Build Journal"
date: 2026-09-29
source_period: "2026-09-27 to 2026-09-29"
project: "SUZY//WORLD"
document_type: "build-journal"
status: "historical-current"
keywords:
  - SUZY//AI
  - SUZY//WORLD
  - personal AI
  - knowledge graph
  - album reviews
  - WordPress
  - MariaDB
  - REST API
  - Appliance Latent Space
  - POP//CONTEXT
related_repositories:
  - suzyeaston/suzy-world
  - suzyeaston/suzy-ai
  - suzyeaston/appliance-latent-space-live
  - suzyeaston/pop-context
  - suzyeaston/suzyeastonca
---

# SUZY//WORLD Build Journal

> Reconstructed on **2026-09-29** from work spanning **2026-09-27 to 2026-09-29**.
>
> These are public project notes and design decisions, not a dump of private SUZY//AI memory.

## The original question

The project started with a simple problem:

**How do you build an AI that grows alongside a person without reducing that person to a static profile?**

The answer became less about cloning a voice and more about preserving continuity: what I knew then, what I think now, what changed, what influenced the change, and what connects across music, culture, language, hockey, creative work, and whatever comes next.

That led to a core distinction:

- **SUZY//AI** is private, local-first intelligence. It can be messy, contradictory, unfinished, and personal.
- **SUZY//WORLD** is deliberately published knowledge. It should be stable, versioned, retrievable, and usable by people and software through an API.

```text
                    PRIVATE

                  SUZY//AI
             memory / models / learning
                       │
                       │ explicit publish
                       ▼

                     PUBLIC

                 SUZY//WORLD
             versioned knowledge API
                       │
          ┌────────────┼────────────┐
          ▼            ▼            ▼
       website        apps        people
```

## The toaster was the first clue

The Appliance Latent Space started as a programmable audiovisual instrument, with the toaster eventually acting as a Bluetooth control surface.

The important realization was that the toaster did not need to host the intelligence. The computer could remain the compute/audio host while the toaster emitted logical control events.

That generalized into a bigger idea:

> **One intelligence, many interfaces.**

- The Appliance becomes the **actor**: create, perform, manipulate music and visuals.
- POP//CONTEXT becomes the **observer**: watch, listen, extract evidence, and feed approved observations into the same intelligence.
- SUZY//AI becomes the private reasoning and memory layer beneath both.

## SUZY//AI became a living world model

The first SUZY//AI implementation used a private SQLite world database and a local HTTP server.

The goal was never just “chat with a model.” The world needed entities, historical teachings, relationships, provenance, and a timeline.

```text
Björk — Homogenic
      │
      ├── review, 2026
      ├── revised review, 2028
      └── connection discovered later
```

A changed interpretation should not erase the old one.

> **Character development is data.**

## Album reviews exposed the missing public layer

We initially put album teaching directly into local SUZY//AI. That worked, but it exposed another requirement.

Some knowledge should be retrievable by the website, SUZY//AI, The Appliance, POP//CONTEXT, future visualizations, outside developers, and other tools without copying the same information into separate databases.

That meant album reviews needed a stable public home.

## We almost built Postgres + FastAPI

The first public architecture sketch was a separate Postgres database with a FastAPI service.

Then we audited the actual production site before committing to another stack.

The site already had:

- WordPress
- PHP 8.3
- MariaDB 10.6
- WP-CLI
- custom REST endpoints
- custom database tables
- an SSH/SFTP deployment workflow
- an existing WordPress-native data/API plugin as a useful precedent

That changed the decision.

Instead of adding another database server, another process, another auth scheme, and another deployment path, SUZY//WORLD became a standalone open-source WordPress plugin using infrastructure that already existed.

> **Sovereignty does not require building a new server every time you have an idea.**

## The current architecture

### SUZY//AI

Private intelligence. Owns local memories, private teachings, personal timeline, model adapters, retrieval, inference, and experiments.

### SUZY//WORLD

Public knowledge. Owns deliberately published entities, versioned statements/reviews, tags, relationships, media metadata, provenance, and import history.

Public API:

```text
https://www.suzyeaston.ca/wp-json/suzy-world/v1
```

### The Appliance Latent Space

The actor. It can teach SUZY//AI explicitly, but it should never silently publish private material.

### POP//CONTEXT

The observer. Evidence is not automatically truth, and observations are not automatically public knowledge.

## One database, not one memory per app

A major design rule emerged:

> **Do not let every application invent its own memory.**

Private canonical state belongs to SUZY//AI. Published canonical state belongs to SUZY//WORLD. Apps refer to those systems rather than creating competing truth stores.

## The first SUZY//WORLD data model

The plugin creates seven WordPress-prefixed tables:

```text
sw_entities
sw_statements
sw_tags
sw_entity_tags
sw_relationships
sw_assets
sw_imports
```

### Entities

Things in the public world: artist, album, musical idea, film, player, game, person, idiom, concept, and future domains.

### Statements

Versioned reviews, opinions, observations, and notes attached to entities. Revisions create history instead of replacing it.

### Tags

Tags are real records, not comma-separated decoration.

```text
production: negative space
composition: pedal tone
scene: Vancouver punk
personal: changed my playing
```

The long-term point is not only filtering. Tags become clues the AI can reason across.

### Relationships

This is where the world starts behaving like a graph.

```text
Homogenic --by_artist--> Björk
Album X --influenced--> Musical Idea Y
Chord X --learned_from--> Record Y
```

### Assets

Media metadata is separate from knowledge. Album-cover records can contain URL, source, creator, credit, rights basis, rights note, and an explicit display flag.

Importing an image URL does not automatically mean “publish this image.”

## The first API

Public reads:

```http
GET /wp-json/suzy-world/v1/schema
GET /wp-json/suzy-world/v1/entities
GET /wp-json/suzy-world/v1/entities/{uuid}
GET /wp-json/suzy-world/v1/albums
GET /wp-json/suzy-world/v1/tags
```

Authenticated writes:

```http
POST /wp-json/suzy-world/v1/entities
POST /wp-json/suzy-world/v1/statements
POST /wp-json/suzy-world/v1/relationships
POST /wp-json/suzy-world/v1/imports/albums
```

External writers can use normal WordPress Application Passwords instead of inventing a second credential system.

## The deployment comedy

The first production deploy activated the plugin successfully, but a post-activation verification command broke because shell quoting mangled a WP-CLI command.

The next verification attempt hit the same family of problem with a SQL string.

Neither failure meant the database was broken.

The final verification moved the checks inside WordPress/PHP instead of making several parsers agree about nested quoting.

```text
plugin_loaded: true
schema_installed: true
table_count: 7
route_registered: true
public API: live
```

Useful lesson:

> **When deployment verification becomes more complicated than the thing being verified, move the check closer to the application.**

## The missing human interface

The first working admin screen was technically correct and emotionally absurd.

It mostly offered CSV and JSON imports.

That was useful for bulk data, but the real human task was simpler:

> **Sit down, listen to a record, and tell the system what it means.**

So SUZY//WORLD got a proper top-level WordPress admin screen with an **Add an album** form.

It asks for artist, album, year, review, optional rating, musical/production notes, favourite tracks, associations/influences, what the record taught me, tags, and optional album-cover metadata.

Then:

```text
Save Draft
Publish to SUZY//WORLD
```

The bulk importer still exists, but it is no longer the main interface.

> **The database can be sophisticated. The act of teaching it should feel ordinary.**

## Why albums first?

Albums are unusually rich world-model objects.

A record can connect a person, a time in their life, harmony, production, scene, technology, visual aesthetics, memories, other artists, performance technique, and future creative ideas.

Eventually SUZY//AI should be able to notice patterns such as:

> “You keep using ‘negative space’ when talking about Leonard Cohen, Albini recordings, and certain Björk arrangements. Is that the same musical value showing up in different forms?”

That kind of connection is the point of the project.

## Principles that survived the build

### Open machinery. Personally owned intelligence.

The software can be public. A person’s private memories, recordings, model data, and world state do not have to be.

### Human teaching and machine inference are different.

Future provenance should distinguish:

```text
human teaching
direct evidence
model inference
current sourced research
```

### Publishing is explicit.

Private memory should never leak into SUZY//WORLD merely because an app can access both systems.

### History beats overwrite.

Changed opinions are meaningful. Version them.

### The AI is not the database.

The database provides stable memory and relationships. The model provides reasoning and connection-making.

### The model is not the person.

Models can be replaced. The evolving world is what creates continuity.

## Where this can go

Music is only the first world.

```text
MUSIC
albums / artists / chords / musical ideas

LANGUAGE
idioms / expressions / meanings / contexts

CANUCKS
players / games / seasons / changing opinions

CULTURE
films / people / scenes / references

CREATIVE LIFE
projects / tools / influences / discoveries
```

One world model does not mean one giant undifferentiated blob.

It means many domains connected by shared identity and history.

## Next experiments

1. Add a handful of albums through the WordPress form.
2. Keep the first entries as drafts.
3. See which fields are genuinely useful.
4. Inspect the API representation.
5. Refine the tagging vocabulary.
6. Begin adding explicit relationships.
7. Later teach SUZY//AI how to retrieve and publish selected SUZY//WORLD records.

The project will probably change again once real music is inside it.

That is the point.
