---
name: db-work
description: This skill should be used when the user asks to work with the database, write migrations, create or update Doctrine entities, write queries, work with repositories, asks about "baza dannyh", "migraciya", "entity", "repozitorij", "zapros", "SQL", or discusses database schema, Doctrine ORM, PostgreSQL, or data persistence in this project.
version: 1.0.0
---

# Database Work — Bazar AI

This project uses **Symfony** with **Doctrine ORM** and **PostgreSQL**.

## Stack

- ORM: Doctrine (annotations/attributes)
- DB: PostgreSQL 15+
- Migrations: `doctrine/migrations` — files in `src/be/migrations/`
- Repositories: `src/be/src/Repository/`
- Entities: `src/be/src/Entity/`

## Migrations

To create a migration after changing an entity:

```bash
docker-compose exec php bin/console doctrine:migrations:diff
```

To apply:

```bash
docker-compose exec php bin/console doctrine:migrations:migrate
```

Migration files are stored in `src/be/migrations/`. Each file starts with `Version<timestamp>.php`.

## Entities

- Use PHP 8 attributes for Doctrine mapping (`#[ORM\Entity]`, `#[ORM\Column]`, etc.)
- Place entities in `src/be/src/Entity/`
- Always define a `Repository` class for each entity

Example entity:
```php
#[ORM\Entity(repositoryClass: ArticleRepository::class)]
#[ORM\Table(name: 'articles')]
class Article
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $title;
}
```

## Repositories

- Extend `ServiceEntityRepository`
- Place in `src/be/src/Repository/`
- Use QueryBuilder for complex queries; use `findBy` / `find` for simple ones

Example:
```php
class ArticleRepository extends ServiceEntityRepository
{
    public function findPublished(): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.status = :status')
            ->setParameter('status', 'published')
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
```

## Rules

- Never use raw SQL unless Doctrine cannot do it
- Always use parameterized queries (never string concatenation)
- Keep DB logic in Repositories, not in Controllers or Services
- After adding a new column, always generate a migration — never modify the DB manually
- For bulk operations, use `EntityManager::flush()` once after multiple `persist()` calls
