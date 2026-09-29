# Data model

## Entity

A thing in the public world.

Examples:

```text
artist
album
player
game
film
person
idea
idiom
```

Identity is deduplicated by:

```text
domain + kind + canonical_key
```

Every entity also receives a stable public UUID.

## Statement

A versioned assertion, review, opinion, observation, or note attached to an entity.

Example:

```text
album
  ├── album_review v1
  ├── album_review v2
  └── album_review v3
```

Changing your mind creates history instead of rewriting it.

Exact repeated content is deduplicated by content hash.

## Tags

Tags are proper records, not comma-separated text blobs.

A tag has:

```text
category
name
slug
description
```

Examples:

```text
production: negative space
composition: pedal tone
personal: changed my playing
scene: Vancouver punk
```

## Relationships

Relationships connect entities:

```text
Homogenic --by_artist--> Björk
Album X --influenced--> Musical Idea Y
Player X --changed_opinion_after--> Game Y
```

## Assets

External/public media metadata:

```text
url
credit
creator
source_url
rights_basis
rights_note
display_ok
```

`display_ok` is an explicit publishing control.

## Public/private boundary

SUZY//WORLD is public knowledge.

SUZY//AI may have private memories, experiments, or contradictory thoughts that never belong here.

Publication must be deliberate.
