# HermesRepository - Référence Technique

## Description

Repository pour les opérations sur les tokens Hermes, gérant la recherche, la récupération et le comptage de tokens par n-grammes avec filtres contextuels optionnels.

## Hiérarchie

```
HermesRepositoryInterface
    └── HermesRepository
```

## Rôle principal

Fournit des méthodes d'accès aux données pour les opérations de recherche et de suggestion. Gère les requêtes sur les tokens indexés avec des filtres par champs, namespaces et clusters. Sert de couche d'abstraction entre la logique métier et le stockage des données.

## API / Méthodes publiques

### `findTokensByNgrams(array $ngrams, ?ContextFilterVOCollection $contexts = null, ?StringTypedCollection $fields = null, int $limit = 100, bool $withDocument = false): Collection`

Recherche des tokens correspondant aux n-grammes donnés avec filtres et limite.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$ngrams` | `array` | Tableau des n-grammes à rechercher |
| `$contexts` | `ContextFilterVOCollection|null` | Filtres contextuels (namespace, clusters) |
| `$fields` | `StringTypedCollection|null` | Champs à filtrer |
| `$limit` | `int` | Nombre maximum de résultats (défaut: 100) |
| `$withDocument` | `bool` | Charger les documents associés (défaut: false) |

**Retourne :** `Collection` - Collection des tokens trouvés

**Exemple :**
```php
<?php

$tokens = $repository->findTokensByNgrams(
    ngrams: ['joh', 'ohn'],
    limit: 20,
    withDocument: true
);
```

---

### `getAllTokensByNgrams(array $ngrams, ?ContextFilterVOCollection $contexts = null, ?StringTypedCollection $fields = null): Collection`

Récupère tous les tokens correspondant aux n-grammes donnés (sans limite).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$ngrams` | `array` | Tableau des n-grammes à rechercher |
| `$contexts` | `ContextFilterVOCollection|null` | Filtres contextuels (namespace, clusters) |
| `$fields` | `StringTypedCollection|null` | Champs à filtrer |

**Retourne :** `Collection` - Collection de tous les tokens trouvés

**Exemple :**
```php
<?php

$allTokens = $repository->getAllTokensByNgrams(
    ngrams: ['joh', 'ohn'],
    contexts: $contexts
);
```

---

### `getTokensGroupedByDocument(array $ngrams, ?ContextFilterVOCollection $contexts = null, ?StringTypedCollection $fields = null, float $minSimilarity = 0.0): array`

Récupère les tokens groupés par document.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$ngrams` | `array` | Tableau des n-grammes à rechercher |
| `$contexts` | `ContextFilterVOCollection|null` | Filtres contextuels (namespace, clusters) |
| `$fields` | `StringTypedCollection|null` | Champs à filtrer |
| `$minSimilarity` | `float` | Seuil minimum de similarité (défaut: 0.0) |

**Retourne :** `array` - Tableau groupé par document avec structure :

```php
[
    'document_id' => 'doc_1',
    'fingerprint' => 'App\\Models\\User|1',
    'data' => $dataObject,
    'tokens' => [
        ['id' => 1, 'token' => 'john', 'original_text' => 'John Doe', 'field' => 'name'],
        // ...
    ]
]
```

**Exemple :**
```php
<?php

$grouped = $repository->getTokensGroupedByDocument(
    ngrams: ['joh', 'ohn'],
    contexts: $contexts
);

foreach ($grouped as $document) {
    echo "Document : " . $document['fingerprint'] . "\n";
    echo "Tokens : " . count($document['tokens']) . "\n";
}
```

---

### `countTokensByNgrams(array $ngrams, ?ContextFilterVOCollection $contexts = null, ?StringTypedCollection $fields = null): int`

Compte le nombre de tokens correspondant aux n-grammes donnés.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$ngrams` | `array` | Tableau des n-grammes à rechercher |
| `$contexts` | `ContextFilterVOCollection|null` | Filtres contextuels (namespace, clusters) |
| `$fields` | `StringTypedCollection|null` | Champs à filtrer |

**Retourne :** `int` - Nombre de tokens trouvés

**Exemple :**
```php
<?php

$count = $repository->countTokensByNgrams(
    ngrams: ['joh', 'ohn'],
    contexts: $contexts
);

echo "Tokens trouvés : {$count}\n";
```

## Cas d'utilisation

### Cas 1 : Recherche de tokens avec filtres contextuels

**Problème :** On cherche des tokens pour un champ spécifique dans un namespace donné.

```php
<?php

use AndyDefer\LaravelHermes\ValueObjects\ContextFilterVO;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

// Filtre par namespace
$contexts = new ContextFilterVOCollection();
$contexts->add(new ContextFilterVO('App\\Models\\User'));

// Filtre par champ
$fields = new StringTypedCollection();
$fields->add('name');
$fields->add('email');

// Recherche
$tokens = $repository->findTokensByNgrams(
    ngrams: ['joh', 'ohn'],
    contexts: $contexts,
    fields: $fields,
    limit: 50,
    withDocument: true
);
```

### Cas 2 : Récupération groupée par document

**Problème :** On veut afficher les résultats de recherche regroupés par document.

```php
<?php

$grouped = $repository->getTokensGroupedByDocument(
    ngrams: ['pro', 'rod'],
    contexts: $contexts
);

foreach ($grouped as $document) {
    echo "Document : " . $document['fingerprint'] . "\n";
    echo "  - " . $document['data']->name . "\n";
    
    foreach ($document['tokens'] as $token) {
        echo "    * " . $token['original_text'] . " (" . $token['field'] . ")\n";
    }
}
```

### Cas 3 : Comptage avec filtres complexes

**Problème :** On souhaite compter les tokens correspondant à une requête avec des filtres de cluster.

```php
<?php

use AndyDefer\Repository\ValueObjects\ClusterQueries;

$contexts = new ContextFilterVOCollection();
$contexts->add(new ContextFilterVO(
    'App\\Models\\Product',
    new ClusterQueries(['cluster' => 'status=published & category=electronics'])
));

$count = $repository->countTokensByNgrams(
    ngrams: ['lap', 'top'],
    contexts: $contexts,
    fields: $fields
);

echo "Produits électroniques publiés trouvés : {$count}\n";
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Query invalide | `QueryException` | Message d'erreur SQL |
| Relation document inexistante | `QueryException` | `Column not found` |

## Intégration

Ce repository est utilisé par :

- `HermesService` - Service principal de recherche
- `HermesRepositoryInterface` - Contrat d'implémentation
- `IndexedTokenRepository` - Repository sous-jacent pour les tokens indexés

## Performance

- **Recherche** : O(n * log n) - requête SQL avec index sur `token`
- **Filtrage par namespace** : Utilise `LIKE` sur `fingerprint` (peut être lent sur grands volumes)
- **Filtrage par cluster** : Utilise `whereCluster()` avec index adapté
- **Regroupement** : O(n) - parcours des résultats
- **Recommandation** : Utiliser des index sur `token`, `field`, `document_id`

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Complet |
| Laravel 10+ | ✅ Complet |
| MySQL 5.7+ | ✅ Complet |
| PostgreSQL 12+ | ✅ Complet |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelHermes\Repositories\HermesRepository;
use AndyDefer\LaravelHermes\Collections\ContextFilterVOCollection;
use AndyDefer\LaravelHermes\ValueObjects\ContextFilterVO;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\Repository\ValueObjects\ClusterQueries;

// Création du repository (via le conteneur Laravel)
$repository = app(HermesRepository::class);

// Préparation des filtres
$contexts = new ContextFilterVOCollection();
$contexts->add(new ContextFilterVO(
    'App\\Models\\User',
    new ClusterQueries(['cluster' => 'tenant=company_abc & status=active'])
));

$fields = new StringTypedCollection();
$fields->add('name');
$fields->add('email');

// Recherche avec limite
$tokens = $repository->findTokensByNgrams(
    ngrams: ['joh', 'ohn', 'jon'],
    contexts: $contexts,
    fields: $fields,
    limit: 50,
    withDocument: true
);

echo "Tokens trouvés : " . $tokens->count() . "\n";

foreach ($tokens as $token) {
    echo "Token : " . $token->original_text . "\n";
    echo "  Champ : " . $token->field . "\n";
    echo "  Document : " . $token->document->fingerprint . "\n";
}

// Récupération groupée par document
$grouped = $repository->getTokensGroupedByDocument(
    ngrams: ['joh', 'ohn', 'jon'],
    contexts: $contexts,
    fields: $fields
);

echo "\nGroupé par document :\n";
foreach ($grouped as $document) {
    echo "Document : " . $document['fingerprint'] . "\n";
    echo "  Tokens : " . count($document['tokens']) . "\n";
}

// Comptage
$count = $repository->countTokensByNgrams(
    ngrams: ['joh', 'ohn', 'jon'],
    contexts: $contexts,
    fields: $fields
);

echo "\nTotal : {$count} tokens\n";
```

## Voir aussi

- `HermesRepositoryInterface` - Contrat du repository
- `IndexedTokenRepository` - Repository des tokens indexés
- `ContextFilterVOCollection` - Collection de filtres contextuels
- `HermesService` - Service de recherche principal