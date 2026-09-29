# API

Base:

```text
https://www.suzyeaston.ca/wp-json/suzy-world/v1
```

## Schema

```http
GET /schema
```

## Albums

```http
GET /albums
```

## Entities

```http
GET /entities
GET /entities?domain=music&kind=album
GET /entities?search=bjork
GET /entities/{uuid}
```

## Tags

```http
GET /tags
```

## Writes

Write routes require a WordPress administrator.

WordPress Application Passwords are the preferred first external-auth mechanism.

Example:

```bash
curl --user 'USERNAME:APPLICATION_PASSWORD' \
  -H 'Content-Type: application/json' \
  -d '{
    "domain":"music",
    "kind":"album",
    "name":"Example"
  }' \
  https://www.suzyeaston.ca/wp-json/suzy-world/v1/entities
```

Do not put an Application Password in a public repository.
