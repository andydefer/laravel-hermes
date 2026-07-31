# ContextFilterVOCollection - Référence Technique

## Description

Collection typée de filtres de contexte utilisés pour restreindre les opérations de recherche et de complétion.

## Hiérarchie

```
AbstractTypedCollection
    └── ContextFilterVOCollection
```

**Interfaces héritées :** `IteratorAggregate`, `ArrayAccess`, `Countable`

## Rôle principal

Regroupe et manipule des filtres de contexte (`ContextFilterVO`) pour les opérations de recherche. Chaque filtre peut contenir un namespace (restriction par type d'entité) et/ou des requêtes de cluster (restriction par attributs métier). La collection fournit des méthodes d'extraction, de filtrage et de vérification pour faciliter l'application des filtres aux requêtes de recherche.

## API / Méthodes publiques

### `getNamespaces(): array`

Extrait tous les namespaces non vides de la collection.

**Retourne :** `string[]` - Tableau des namespaces

**Exemple :**
```php
<?php

$collection = new ContextFilterVOCollection();
$collection->add(new ContextFilterVO('App\\Models\\User'));
$collection->add(new ContextFilterVO('App\\Models\\Product'));

$namespaces = $collection->getNamespaces();
// ['App\\Models\\User', 'App\\Models\\Product']
```

---

### `getClusterQueries(): array`

Extrait toutes les requêtes de cluster des contextes qui en possèdent.

**Retourne :** `string[]` - Tableau des requêtes de cluster

**Exemple :**
```php
<?php

use AndyDefer\Repository\ValueObjects\ClusterQueries;

$collection = new ContextFilterVOCollection();
$collection->add(new ContextFilterVO(
    null,
    new ClusterQueries(['cluster' => 'tenant=company_abc'])
));

$queries = $collection->getClusterQueries();
// ['tenant=company_abc']
```

---

### `filterByNamespace(string $namespace): self`

Filtre la collection pour ne conserver que les contextes correspondant au namespace donné.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$namespace` | `string` | Namespace à filtrer (ex: 'App\\Models\\User') |

**Retourne :** `self` - Nouvelle collection contenant uniquement les contextes correspondants

**Exemple :**
```php
<?php

$filtered = $collection->filterByNamespace('App\\Models\\User');
// Ne conserve que les contextes du namespace User
```

---

### `filterByClusterQuery(string $clusterQuery): self`

Filtre la collection pour ne conserver que les contextes correspondant à la requête de cluster donnée.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$clusterQuery` | `string` | Requête de cluster à filtrer |

**Retourne :** `self` - Nouvelle collection contenant uniquement les contextes correspondants

**Exemple :**
```php
<?php

$filtered = $collection->filterByClusterQuery('tenant=company_abc');
// Ne conserve que les contextes avec cette requête de cluster
```

---

### `hasAnyCluster(): bool`

Vérifie si au moins un contexte de la collection possède des requêtes de cluster.

**Retourne :** `bool` - True si au moins un contexte a des clusters

**Exemple :**
```php
<?php

if ($collection->hasAnyCluster()) {
    // Appliquer les filtres de cluster
}
```

---

### `hasAnyNamespace(): bool`

Vérifie si au moins un contexte de la collection possède un namespace.

**Retourne :** `bool` - True si au moins un contexte a un namespace

**Exemple :**
```php
<?php

if ($collection->hasAnyNamespace()) {
    // Appliquer les filtres de namespace
}
```

## Cas d'utilisation

### Cas 1 : Recherche restreinte par type d'entité

**Problème :** On souhaite rechercher uniquement dans les utilisateurs, pas dans les produits ou les commandes.

```php
<?php

use AndyDefer\LaravelHermes\Collections\ContextFilterVOCollection;
use AndyDefer\LaravelHermes\ValueObjects\ContextFilterVO;

$contexts = new ContextFilterVOCollection();
$contexts->add(new ContextFilterVO('App\\Models\\User'));

// Utilisation dans la recherche
$request = SearchRequestRecord::from([
    'query' => new SearchQueryVO('john=name'),
    'contexts' => $contexts,
]);

$results = $hermes->search($request);
// Seuls les utilisateurs sont retournés
```

### Cas 2 : Recherche par attributs métier (clusters)

**Problème :** On cherche des utilisateurs appartenant à une entreprise spécifique.

```php
<?php

use AndyDefer\Repository\ValueObjects\ClusterQueries;

$contexts = new ContextFilterVOCollection();
$contexts->add(new ContextFilterVO(
    null,
    new ClusterQueries(['cluster' => 'tenant=company_abc'])
));

$request = SearchRequestRecord::from([
    'query' => new SearchQueryVO('john=name'),
    'contexts' => $contexts,
]);

$results = $hermes->search($request);
// Seuls les utilisateurs de company_abc sont retournés
```

### Cas 3 : Combinaison de filtres

**Problème :** On cherche des utilisateurs actifs dans une entreprise spécifique.

```php
<?php

use AndyDefer\Repository\ValueObjects\ClusterQueries;

$contexts = new ContextFilterVOCollection();
$contexts->add(new ContextFilterVO(
    'App\\Models\\User',
    new ClusterQueries([
        'cluster' => 'tenant=company_abc & status=active'
    ])
));

$request = SearchRequestRecord::from([
    'query' => new SearchQueryVO('john=name'),
    'contexts' => $contexts,
]);

$results = $hermes->search($request);
// Seuls les utilisateurs actifs de company_abc sont retournés
```

### Cas 4 : Filtrage automatique des contextes

**Problème :** On doit préparer une collection de contextes en fonction des permissions de l'utilisateur.

```php
<?php

$availableNamespaces = ['App\\Models\\User', 'App\\Models\\Product'];
$userTenant = 'company_abc';

$contexts = new ContextFilterVOCollection();

foreach ($availableNamespaces as $namespace) {
    $contexts->add(new ContextFilterVO(
        $namespace,
        new ClusterQueries(['cluster' => "tenant={$userTenant}"])
    ));
}

// Vérification rapide
if (!$contexts->hasAnyNamespace()) {
    throw new RuntimeException('Aucun contexte disponible');
}

// Extraction des filtres pour la requête
$namespaces = $contexts->getNamespaces();
$queries = $contexts->getClusterQueries();

// Construction de la requête
$request = SearchRequestRecord::from([
    'query' => new SearchQueryVO('search=all'),
    'contexts' => $contexts,
]);
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Ajout d'un élément de type incorrect | `InvalidArgumentException` | `Item must be of type AndyDefer\LaravelHermes\ValueObjects\ContextFilterVO` |

## Intégration

Cette collection est utilisée par :

- `HermesService::complete()` - Filtrage des résultats de complétion
- `HermesService::search()` - Filtrage des résultats de recherche
- `HermesService::suggest()` - Filtrage des suggestions
- `HermesRepository` - Application des filtres aux requêtes SQL

## Performance

- **Extraction des namespaces** : O(n) - parcours de la collection
- **Extraction des requêtes** : O(n) - parcours de la collection
- **Filtrage** : O(n) - création d'une nouvelle collection avec les éléments filtrés
- **Vérifications** : O(n) - s'arrête au premier élément correspondant
- **Mémoire** : Les méthodes de filtrage créent de nouvelles collections (ne modifient pas l'originale)

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelHermes\Collections\ContextFilterVOCollection;
use AndyDefer\LaravelHermes\ValueObjects\ContextFilterVO;
use AndyDefer\Repository\ValueObjects\ClusterQueries;

// Création de la collection
$contexts = new ContextFilterVOCollection();

// Ajout de contextes variés
$contexts->add(new ContextFilterVO('App\\Models\\User'));
$contexts->add(new ContextFilterVO(
    'App\\Models\\Product',
    new ClusterQueries(['cluster' => 'status=published'])
));
$contexts->add(new ContextFilterVO(
    null,
    new ClusterQueries(['cluster' => 'tenant=company_abc'])
));

// Extraction des données
$namespaces = $contexts->getNamespaces();
$queries = $contexts->getClusterQueries();

echo "Namespaces : " . implode(', ', $namespaces) . "\n";
// Namespaces : App\Models\User, App\Models\Product

echo "Clusters : " . implode(', ', $queries) . "\n";
// Clusters : status=published, tenant=company_abc

// Vérifications
if ($contexts->hasAnyNamespace()) {
    echo "Des namespaces sont présents\n";
}

if ($contexts->hasAnyCluster()) {
    echo "Des clusters sont présents\n";
}

// Filtrage
$userContexts = $contexts->filterByNamespace('App\\Models\\User');
$publishedContexts = $contexts->filterByClusterQuery('status=published');

echo "Contextes utilisateur : " . $userContexts->count() . "\n";
echo "Contextes publiés : " . $publishedContexts->count() . "\n";

// Utilisation dans une requête
$request = SearchRequestRecord::from([
    'query' => new SearchQueryVO('john=name'),
    'contexts' => $contexts,
    'limit' => 20,
]);
```

## Voir aussi

- `ContextFilterVO` - Structure de données d'un filtre de contexte
- `ClusterQueries` - Gestionnaire de requêtes de cluster
- `HermesService` - Service principal de recherche
- `AbstractTypedCollection` - Classe parente fournissant les opérations de base sur les collections