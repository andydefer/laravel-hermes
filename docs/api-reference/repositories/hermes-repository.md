# HermesRepository - Référence Technique

## Description

Repository pour la recherche de tokens indexés dans Laravel Hermes. Fournit des méthodes de requête avancées avec filtrage par n-grammes, champs, namespaces et clusters multiples.

## Hiérarchie / Implémentations

```
HermesRepositoryInterface
    └── HermesRepository (final)
```

## Rôle principal

`HermesRepository` est le point d'accès aux tokens indexés pour Laravel Hermes. Il :

- **Recherche** : Recherche des tokens par n-grammes avec filtres
- **Filtrage** : Filtre par champs, namespaces et clusters multiples (AND/OR/NOT)
- **Regroupement** : Regroupe les tokens par document
- **Comptage** : Compte les tokens correspondants

## DETAILS

[Voir la classe HermesRepository](https://github.com/andydefer/laravel-hermes/blob/main/src/Repositories/HermesRepository.php)

## API / Méthodes publiques

### `findTokensByNgrams(array $ngrams, ?ContextFilterVOCollection $contexts = null, ?StringTypedCollection $fields = null, int $limit = 100, bool $withDocument = false): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$ngrams` | `array<string>` | Liste des n-grammes à rechercher |
| `$contexts` | `?ContextFilterVOCollection` | Filtres de contexte (namespace + clusters) |
| `$fields` | `?StringTypedCollection` | Noms des champs à filtrer |
| `$limit` | `int` | Nombre maximum de résultats (défaut: 100) |
| `$withDocument` | `bool` | Charger la relation document (défaut: false) |

**Retourne :** `Collection<int, IndexedToken>` - Collection de tokens

**Exemple :**
```php
$ngrams = ['joh', 'ohn', 'john'];
$fields = (new StringTypedCollection())->add('name');
$tokens = $repository->findTokensByNgrams($ngrams, fields: $fields, limit: 10);
```

---

### `getAllTokensByNgrams(array $ngrams, ?ContextFilterVOCollection $contexts = null, ?StringTypedCollection $fields = null): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$ngrams` | `array<string>` | Liste des n-grammes à rechercher |
| `$contexts` | `?ContextFilterVOCollection` | Filtres de contexte |
| `$fields` | `?StringTypedCollection` | Noms des champs à filtrer |

**Retourne :** `Collection<int, IndexedToken>` - Collection complète de tokens

---

### `getTokensGroupedByDocument(array $ngrams, ?ContextFilterVOCollection $contexts = null, ?StringTypedCollection $fields = null, float $minSimilarity = 0.0): array`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$ngrams` | `array<string>` | Liste des n-grammes à rechercher |
| `$contexts` | `?ContextFilterVOCollection` | Filtres de contexte |
| `$fields` | `?StringTypedCollection` | Noms des champs à filtrer |
| `$minSimilarity` | `float` | Seuil de similarité minimum |

**Retourne :** `array<string, array{document_id, fingerprint, data, tokens}>` - Tokens groupés par document

**Exemple :**
```php
$grouped = $repository->getTokensGroupedByDocument(['joh', 'ohn']);
// [
//     'doc-uuid-1' => [
//         'document_id' => 'doc-uuid-1',
//         'fingerprint' => 'App.Models.User|123',
//         'data' => ['name' => 'John Doe'],
//         'tokens' => [
//             ['id' => '...', 'token' => 'joh', 'original_text' => 'John', 'field' => 'name'],
//             ['id' => '...', 'token' => 'ohn', 'original_text' => 'John', 'field' => 'name']
//         ]
//     ]
// ]
```

---

### `countTokensByNgrams(array $ngrams, ?ContextFilterVOCollection $contexts = null, ?StringTypedCollection $fields = null): int`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$ngrams` | `array<string>` | Liste des n-grammes à rechercher |
| `$contexts` | `?ContextFilterVOCollection` | Filtres de contexte |
| `$fields` | `?StringTypedCollection` | Noms des champs à filtrer |

**Retourne :** `int` - Nombre de tokens correspondants

---

## Méthodes internes (privées)

### `applyFilters(Builder $query, ?ContextFilterVOCollection $contexts, ?StringTypedCollection $fields): void`

Applique les filtres à la requête.

- Filtre par champs (`field IN (... )`)
- Filtre par namespace (`fingerprint LIKE 'namespace|%'`)
- Filtre par clusters via `ClusterFilterApplier` (AND/OR/NOT)

**Logique OR entre les contextes :** Chaque `ContextFilterVO` est combiné avec `OR`.

---

## Cas d'utilisation

### Cas 1 : Recherche simple de tokens

```php
$ngrams = ['joh', 'ohn'];
$tokens = $repository->findTokensByNgrams($ngrams);
```

### Cas 2 : Recherche par champs

```php
$fields = new StringTypedCollection();
$fields->add('name');

$tokens = $repository->findTokensByNgrams($ngrams, fields: $fields);
```

### Cas 3 : Filtrage par namespace

```php
$contexts = new ContextFilterVOCollection();
$contexts->add(new ContextFilterVO('App.Models.User'));

$tokens = $repository->findTokensByNgrams($ngrams, contexts: $contexts);
```

### Cas 4 : Filtrage par cluster AND

```php
$clusters = new ClusterVOCollection();
$clusters->add(new ClusterVO('tenant:company_abc@AND'));
$clusters->add(new ClusterVO('env:production@AND'));

$contexts = new ContextFilterVOCollection();
$contexts->add(new ContextFilterVO('App.Models.User', $clusters, 'AND'));

$tokens = $repository->findTokensByNgrams($ngrams, contexts: $contexts);
```

### Cas 5 : Filtrage par cluster OR

```php
$clusters = new ClusterVOCollection();
$clusters->add(new ClusterVO('role_doctor:true@OR'));
$clusters->add(new ClusterVO('role_admin:true@OR'));

$contexts = new ContextFilterVOCollection();
$contexts->add(new ContextFilterVO(null, $clusters, 'OR'));

$tokens = $repository->findTokensByNgrams($ngrams, contexts: $contexts);
```

### Cas 6 : Groupement par document

```php
$grouped = $repository->getTokensGroupedByDocument(['joh', 'ohn']);
// Retourne les tokens regroupés par document pour analyse
```

---

## Flux d'exécution

```
1. Construction de la requête de base
2. Application des filtres:
   a. Filtre par champs
   b. Filtre par namespace
   c. Filtre par clusters (AND/OR/NOT)
3. Application des options (limit, withDocument)
4. Exécution de la requête
5. Retour des résultats
```

## Performance

- Utilisation de `distinct()` pour éviter les doublons
- Filtrage par `whereIn` pour les champs
- Filtrage par `whereHas` pour les relations
- `ClusterFilterApplier` optimise les conditions `LIKE`

---

## Gestion des erreurs

| Situation | Comportement |
|-----------|--------------|
| `ContextFilterVO` sans namespace ni clusters | Exception levée à la construction |
| Cluster sans mode | Exception levée par `ClusterFilterApplier` |
| Opérateur invalide | Exception levée par `ClusterFilterApplier` |
| Aucun n-gramme trouvé | Collection vide |

---

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.2+ | ✅ Complet |
| Laravel 10.x+ | ✅ Complet |
| Laravel Indexer 0.21.1+ | ✅ Requis |

## Dépendances

- `IndexedTokenRepository` - Repository des tokens
- `ClusterFilterApplier` - Application des filtres de clusters
- `ContextFilterVO` - Filtre de contexte (namespace + clusters)

---

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelHermes\Repositories\HermesRepository;
use AndyDefer\LaravelHermes\Collections\ContextFilterVOCollection;
use AndyDefer\LaravelHermes\ValueObjects\ContextFilterVO;
use AndyDefer\LaravelIndexer\Collections\ClusterVOCollection;
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

$repository = app(HermesRepository::class);

// 1. Recherche simple
$ngrams = ['joh', 'ohn', 'john'];
$tokens = $repository->findTokensByNgrams($ngrams);

// 2. Recherche avec filtres
$fields = new StringTypedCollection();
$fields->add('name');

$tokens = $repository->findTokensByNgrams($ngrams, fields: $fields);

// 3. Recherche par namespace
$contexts = new ContextFilterVOCollection();
$contexts->add(new ContextFilterVO('App.Models.User'));

$tokens = $repository->findTokensByNgrams($ngrams, contexts: $contexts);

// 4. Recherche par clusters AND
$clusters = new ClusterVOCollection();
$clusters->add(new ClusterVO('tenant:company_abc@AND'));
$clusters->add(new ClusterVO('env:production@AND'));

$contexts = new ContextFilterVOCollection();
$contexts->add(new ContextFilterVO('App.Models.User', $clusters, 'AND'));

$tokens = $repository->findTokensByNgrams($ngrams, contexts: $contexts);

// 5. Recherche par clusters OR
$clusters = new ClusterVOCollection();
$clusters->add(new ClusterVO('role_doctor:true@OR'));
$clusters->add(new ClusterVO('role_admin:true@OR'));

$contexts = new ContextFilterVOCollection();
$contexts->add(new ContextFilterVO(null, $clusters, 'OR'));

$tokens = $repository->findTokensByNgrams($ngrams, contexts: $contexts);

// 6. Groupement par document
$grouped = $repository->getTokensGroupedByDocument($ngrams);

// 7. Comptage
$count = $repository->countTokensByNgrams($ngrams);
```

## Voir aussi

- `HermesRepositoryInterface` - Interface du repository
- `ContextFilterVO` - Filtre de contexte
- `ClusterFilterApplier` - Application des filtres de clusters
- `IndexedTokenRepository` - Repository des tokens
- `HermesService` - Service principal
- [Laravel Indexer - Documentation](https://github.com/andydefer/laravel-indexer)