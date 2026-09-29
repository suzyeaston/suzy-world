---
title: "Blog Seeds: Building SUZY//WORLD"
date: 2026-09-29
source_period: "2026-09-27 to 2026-09-29"
project: "SUZY//WORLD"
document_type: "blog-seeds"
status: "rough"
keywords:
  - personal AI
  - music
  - knowledge graph
  - creative technology
  - AI sovereignty
  - WordPress
  - toaster instrument
---

# Blog Seeds: Building SUZY//WORLD

Rough fragments for future posts on suzyeaston.ca.

## Possible titles

- **I Didn’t Want an AI Clone. I Wanted Character Development.**
- **Open Machinery, Personally Owned Intelligence**
- **I Accidentally Built a Public Knowledge Graph Because I Wanted to Review Records**
- **What If Your AI Could Change Its Mind With You?**
- **The Toaster Was the Easy Part**
- **One Brain, Many Worlds**
- **Why My AI Has a Private Memory and a Public API**
- **Teaching a Machine What an Album Means to Me**
- **The Database Can Be Complicated. Teaching It Shouldn’t Be.**

## Possible opening

> I was trying to build an AI-powered toaster instrument and somehow ended up designing a public knowledge graph for my own brain.

The toaster exposed the real problem: where does the intelligence live, what does it remember, and who owns the memory?

## Thesis

The interesting problem is not making a chatbot sound like a person.

It is building continuity.

A person changes. Their taste changes. They learn new words. They revise old opinions. They discover that a chord they loved years ago is connected to a record they hear differently now.

A useful personal AI should preserve that motion rather than compressing a human into a permanent preference profile.

## Character development is data

Line worth keeping:

> I don’t want the AI to remember only what I think. I want it to remember that I used to think something else.

Possible structure:

1. Most personalization systems want a current snapshot.
2. A current snapshot destroys the path that created it.
3. Contradiction is not necessarily bad data.
4. Versioned opinions can be more useful than a single “truth.”
5. Show the album-review example.
6. Explain why reviews become statements with versions.

## Private intelligence, public knowledge

```text
SUZY//AI = private
SUZY//WORLD = public
```

The private system can contain unfinished thoughts, personal context, experiments, wrong ideas, and private memories.

The public system contains only deliberate publication.

## Why WordPress won

Initial instinct:

```text
Postgres + FastAPI
```

Actual answer after inspecting the real site:

```text
WordPress + MariaDB + REST plugin
```

Not because WordPress is more futuristic.

Because it was already there, deployable, authenticated, backed up, and understood.

> Sovereignty does not require building a new server every time you have an idea.

## Albums as training data, but not in the creepy way

An album review can encode:

- what I heard
- what I cared about
- what it reminded me of
- what it taught me
- what it influenced
- which musical language I use to describe it

Over time, that becomes a map of musical values.

Not “recommend me more songs.”

More like a personal theory of listening.

## The toaster as an interface

The physical toaster does not need to run the model.

It can be a Bluetooth control surface.

```text
embodiment != intelligence
```

The same intelligence can appear through a toaster, browser, music instrument, cultural-analysis tool, or API.

## A personal AI should have provenance

The future system should distinguish:

```text
Suzy said this
the model inferred this
a source reported this
the system observed this
```

Those are different epistemic objects.

They should remain different.

## Deployment failures are part of the story

Two tiny production bumps:

1. shell quoting broke a WP-CLI verification command
2. SQL quoting broke the next verification command

Meanwhile the plugin and database were fine.

Possible line:

> The database existed. The API existed. My verification script was simply having an existential crisis about quotation marks.

## Things not to oversell

- SUZY//AI is not yet a magical autonomous digital twin.
- The current system is infrastructure for an evolving world model.
- A base model is an engine, not the identity.
- The album graph is still early.
- Relationships become interesting only after real data accumulates.
- Continuous learning does not have to mean constantly retraining model weights.

## Possible closing

> I started by asking how to make a toaster into an instrument. I ended up asking where a person ends and their tools begin. For now the answer is a local AI, a small MariaDB database, a public API, and a form where I can tell a machine why I love a record. That feels like enough to keep going.
