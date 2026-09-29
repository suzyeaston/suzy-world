# Album imports

Use:

```text
Tools → SUZY//WORLD
```

Accepted:

- CSV
- JSON

## Required fields

```text
artist
album
```

## Useful fields

```text
year
review
rating
tags
sonic_notes
favorite_tracks
associations
lesson
```

## Album art metadata

```text
cover_url
cover_credit
cover_creator
cover_source_url
cover_rights
cover_rights_note
cover_display
```

Cover display is opt-in.

## Tags

Separate tags with semicolons.

Optional category syntax:

```text
genre:art-pop; production:negative space; composition:pedal tone
```

## Versions

Re-importing the exact same review creates no second statement.

Changing the review creates the next version on the same album entity.
