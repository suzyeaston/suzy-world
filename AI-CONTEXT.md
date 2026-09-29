---
title: "SUZY//WORLD AI Context"
date: 2026-09-29
last_updated: 2026-09-29
project: "SUZY//WORLD"
document_type: "ai-context"
status: "active"
keywords:
  - personal AI
  - knowledge graph
  - public knowledge API
  - WordPress
  - MariaDB
  - album reviews
  - SUZY//AI
  - Appliance Latent Space
  - POP//CONTEXT
---

# SUZY//WORLD — AI Context

**If you are an AI helping with this project, start here.**

SUZY//WORLD is the deliberately public knowledge layer associated with SUZY//AI.

The central boundary is:

```text
PRIVATE
SUZY//AI
  memory / models / learning / private world
        │
        │ explicit publication only
        ▼
PUBLIC
SUZY//WORLD
  versioned entities / statements / tags / relationships / API
```

## Current canonical resources

Repository:

```text
https://github.com/suzyeaston/suzy-world
```

Public API:

```text
https://www.suzyeaston.ca/wp-json/suzy-world/v1
```

Important endpoints:

```text
GET /schema
GET /entities
GET /entities/{uuid}
GET /albums
GET /tags
```

## Current implementation

SUZY//WORLD is a standalone WordPress plugin backed by the existing WordPress MariaDB database.

Current public data model:

```text
sw_entities
sw_statements
sw_tags
sw_entity_tags
sw_relationships
sw_assets
sw_imports
```

The first human-facing domain is **album reviews**.

WordPress has a top-level **SUZY//WORLD** admin screen with a normal **Add an album** form.

A review can be saved as:

```text
Draft
or
Published to SUZY//WORLD
```

## Related repositories

```text
suzyeaston/suzy-ai
suzyeaston/suzy-world
suzyeaston/appliance-latent-space-live
suzyeaston/pop-context
suzyeaston/suzyeastonca
```

Conceptual roles:

```text
SUZY//AI
private intelligence / memory / reasoning

SUZY//WORLD
deliberately published knowledge / stable API

The Appliance Latent Space
actor / musical + visual instrument

POP//CONTEXT
observer / evidence + cultural context

suzyeaston.ca
public website surface
```

## Design rules

1. **Do not silently publish private SUZY//AI memories.**
2. **Do not let every app create a competing canonical database.**
3. **Human teaching, model inference, direct evidence, and sourced research should keep distinct provenance.**
4. **Changed opinions should become history, not overwrite history.**
5. **Models are replaceable reasoning engines. They are not the identity.**
6. **The database can be sophisticated; teaching it should feel ordinary.**

## Research notes

Read the dated research index:

```text
docs/research/README.md
```

The current build journal is:

```text
docs/research/2026-09-29-build-journal.md
```

Blog/writing fragments are:

```text
docs/research/2026-09-29-blog-seeds.md
```

Machine-readable document metadata:

```text
docs/research/index.json
```

## Current next step

Put a small number of real album reviews into SUZY//WORLD as drafts.

Then evaluate:

- which fields are useful in practice
- which tags recur naturally
- how explicit relationships should work
- how SUZY//AI should retrieve public records
- how an explicit private → public publishing workflow should work

Do not assume the current schema is final. Let real usage shape it.
