# ContextFilterVO - Référence Technique

## Description

Objet de valeur représentant un filtre de contexte pour les requêtes de documents.

## Hiérarchie

```
AbstractValueObject
    └── ContextFilterVO
```

**Interfaces héritées :** `JsonSerializable`

## Rôle principal

Encapsule les critères de filtrage par namespace et par cluster pour les recherches de tokens. Permet de restreindre les requêtes à un type d'entité spécifique (namespace) et/ou à des attributs métier (clusters). Au moins un des deux critères doit être fourni.

## API / Propriétés publiques

### `$namespace : ?string`

Namespace à filtrer (ex: 'App\Models\User', 'App.Models.Product').

**Exemple :**
```php
<?php

$context->namespace; // 'App.Models.User'
```

---

### `$clusterQueries : ?ClusterQueries`

Requêtes de cluster à appliquer.

**Exemple :**
```php
<?php

$context->clusterQueries; // ClusterQueries object
```

---

### `hasNamespace(): bool`

Vérifie si un filtre de namespace est défini.

**Retourne :** `bool` - True si un namespace est défini

**Exemple :**
```php
<?php

if ($context->hasNamespace()) {
    // Appliquer le filtre namespace
}
```

---

### `hasClusters(): bool`

Vérifie si des requêtes de cluster sont définies.

**Retourne :** `bool` - True si des clusters sont définis

**Exemple :**
```php
<?php

if ($context->hasClusters()) {
    // Appliquer les filtres de cluster
}
```

---

### `getClusterColumn(): string`

Retourne le nom de la colonne pour les requêtes de cluster.

**Retourne :** `string` - 'cluster'

**Exemple :**
```php
<?php

$column = $context->getClusterColumn(); // 'cluster'
```

---

### `getClusterQuery(): ?string`

Retourne l'expression de requête de cluster combinée.

**Retourne :** `string|null` - La requête de cluster, ou null si aucune n'est définie

**Exemple :**
```php
<?php

$query = $context->getClusterQuery();
// 'tenant=company_abc'
// ou 'tenant=company_abc & status=active'
```

---

### `getValue(): StrictAssociative`

Retourne le filtre sous forme de tableau associatif strict.

**Retourne :** `StrictAssociative<string, mixed>` - Le filtre sous forme structurée

**Exemple :**
```php
<?php

$value = $context->getValue();
// ['namespace' => 'App.Models.User', 'cluster_queries' => ['cluster' => 'tenant=company_abc']]
```

## Cas d'utilisation

### Cas 1 : Filtrage par namespace uniquement

**Problème :** On cherche uniquement dans les utilisateurs, pas dans les autres entités.

```php
<?php

use AndyDefer\LaravelHermes\ValueObjects\ContextFilterVO;

$context = new ContextFilterVO('App\\Models\\User');

// Utilisation dans une requête
$tokens = $repository->findTokensByNgrams(
    ngrams: ['joh', 'ohn'],
    contexts: new ContextFilterVOCollection([$context])
);
```

### Cas 2 : Filtrage par cluster uniquement

**Problème :** On cherche dans tous les types d'entités, mais seulement celles d'une entreprise spécifique.

```php
<?php

use AndyDefer\Repository\ValueObjects\ClusterQueries;

$context = new ContextFilterVO(
    null,
    new ClusterQueries(['cluster' => 'tenant=company_abc'])
);

// Recherche dans tous les types d'entités de l'entreprise
$results = $hermes->search($request);
```

### Cas 3 : Filtrage combiné namespace + cluster

**Problème :** On cherche uniquement les produits publiés d'une entreprise.

```php
<?php

use AndyDefer\Repository\ValueObjects\ClusterQueries;

$context = new ContextFilterVO(
    'App\\Models\\Product',
    new ClusterQueries([
        'cluster' => 'tenant=company_abc',
        'status' => 'published'
    ])
);

// La requête combinée sera : 'tenant=company_abc & status=published'
$query = $context->getClusterQuery();
```

### Cas 4 : Filtres multiples avec opérateur AND

**Problème :** On combine plusieurs requêtes de cluster sur différentes colonnes.

```php
<?php

use AndyDefer\Repository\ValueObjects\ClusterQueries;

$context = new ContextFilterVO(
    'App\\Models\\User',
    new ClusterQueries([
        'tenant' => 'company_abc',
        'role' => 'admin'
    ])
);

// La requête sera : 'tenant=company_abc & role=admin'
$query = $context->getClusterQuery();
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Aucun namespace et aucun cluster fourni | `InvalidArgumentException` | `At least one of namespace or clusterQueries must be provided` |
| ClusterQueries vide | `InvalidArgumentException` | `At least one of namespace or clusterQueries must be provided` |

## Intégration

Ce Value Object est utilisé par :

- `ContextFilterVOCollection` - Collection de filtres de contexte
- `HermesRepository` - Application des filtres aux requêtes SQL
- `HermesService` - Filtrage des résultats de recherche
- `SearchRequestRecord` - Paramètre de requête de recherche
- `CompletionRequestRecord` - Paramètre de requête de complétion

## Performance

- **Structure légère** : Objet de valeur immuable, pas de logique lourde
- **Validation à la construction** : Validation précoce pour éviter les erreurs
- **Aucune requête SQL** : Le VO ne contient pas de logique de base de données

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |
| Laravel 10+ | ✅ Complet |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelHermes\ValueObjects\ContextFilterVO;
use AndyDefer\Repository\ValueObjects\ClusterQueries;
use AndyDefer\LaravelHermes\Collections\ContextFilterVOCollection;

// 1. Filtre simple par namespace
$namespaceFilter = new ContextFilterVO('App\\Models\\User');

echo "Namespace : " . $namespaceFilter->namespace . "\n";
echo "Has namespace : " . ($namespaceFilter->hasNamespace() ? 'true' : 'false') . "\n";
echo "Has clusters : " . ($namespaceFilter->hasClusters() ? 'true' : 'false') . "\n";

// 2. Filtre avec cluster unique
$clusterFilter = new ContextFilterVO(
    null,
    new ClusterQueries(['cluster' => 'tenant=company_abc'])
);

echo "Cluster query : " . $clusterFilter->getClusterQuery() . "\n";
echo "Cluster column : " . $clusterFilter->getClusterColumn() . "\n";

// 3. Filtre combiné
$combinedFilter = new ContextFilterVO(
    'App\\Models\\Product',
    new ClusterQueries([
        'tenant' => 'company_abc',
        'status' => 'published'
    ])
);

echo "Namespace : " . $combinedFilter->namespace . "\n";
echo "Cluster query : " . $combinedFilter->getClusterQuery() . "\n";
// Affiche : 'tenant=company_abc & status=published'

// 4. Collection de filtres
$contexts = new ContextFilterVOCollection();
$contexts->add($namespaceFilter);
$contexts->add($clusterFilter);
$contexts->add($combinedFilter);

echo "\nNombre de filtres : " . $contexts->count() . "\n";
echo "Namespaces : " . implode(', ', $contexts->getNamespaces()) . "\n";
echo "Cluster queries : " . implode(', ', $contexts->getClusterQueries()) . "\n";

// 5. Validation - ceci lève une exception
try {
    $invalid = new ContextFilterVO(null, null);
} catch (InvalidArgumentException $e) {
    echo "\nErreur : " . $e->getMessage() . "\n";
    // Affiche : 'At least one of namespace or clusterQueries must be provided'
}
```

## Voir aussi

- `ContextFilterVOCollection` - Collection de filtres de contexte
- `ClusterQueries` - Gestionnaire de requêtes de cluster
- `HermesRepository` - Application des filtres aux requêtes
- `AbstractValueObject` - Classe parente des objets de valeur