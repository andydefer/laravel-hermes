# SearchResultRecordCollection - Référence Technique

## Description

Collection typée d'enregistrements de résultats de recherche retournés par les opérations de recherche.

## Hiérarchie

```
AbstractTypedCollection
    └── SearchResultRecordCollection
```

**Interfaces héritées :** `IteratorAggregate`, `ArrayAccess`, `Countable`

## Rôle principal

Regroupe et manipule des enregistrements de résultats de recherche (`SearchResultRecord`). Fournit des opérations de filtrage, d'extraction de données, de regroupement par namespace, et de chargement d'instances de modèles Eloquent à partir des fingerprints.

## API / Méthodes publiques

### `getDocumentIds(): array`

Extrait tous les identifiants de documents de la collection.

**Retourne :** `string[]` - Tableau des ID de documents

**Exemple :**
```php
<?php

$docIds = $collection->getDocumentIds();
// ['doc_1', 'doc_2', 'doc_3']
```

---

### `getFingerprints(): array`

Extrait tous les fingerprints de la collection.

**Retourne :** `string[]` - Tableau des fingerprints

**Exemple :**
```php
<?php

$fingerprints = $collection->getFingerprints();
// ['App\\Models\\User|1', 'App\\Models\\Product|42']
```

---

### `getNamespaces(): array`

Extrait tous les namespaces uniques de la collection.

**Retourne :** `string[]` - Tableau des namespaces uniques

**Exemple :**
```php
<?php

$namespaces = $collection->getNamespaces();
// ['App\\Models\\User', 'App\\Models\\Product']
```

---

### `getEntityIds(): array`

Extrait tous les identifiants d'entités des fingerprints.

**Retourne :** `string[]` - Tableau des ID d'entités

**Exemple :**
```php
<?php

$ids = $collection->getEntityIds();
// ['1', '42', '3']
```

---

### `filterByMinSimilarity(float $minSimilarity): self`

Filtre les résultats pour ne conserver que ceux ayant un score de similarité supérieur ou égal au seuil donné.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$minSimilarity` | `float` | Seuil minimum (0.0 à 1.0) |

**Retourne :** `self` - Nouvelle collection contenant uniquement les résultats au-dessus du seuil

**Exemple :**
```php
<?php

$highQualityResults = $collection->filterByMinSimilarity(0.8);
```

---

### `filterByField(string $field): self`

Filtre les résultats pour ne conserver que ceux qui ont des correspondances dans le champ donné.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$field` | `string` | Nom du champ (ex: 'name', 'email') |

**Retourne :** `self` - Nouvelle collection contenant uniquement les résultats avec des correspondances dans le champ

**Exemple :**
```php
<?php

$nameResults = $collection->filterByField('name');
```

---

### `filterByNamespace(string $namespace): self`

Filtre les résultats pour ne conserver que ceux appartenant au namespace donné.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$namespace` | `string` | Namespace à filtrer (ex: 'App\\Models\\User') |

**Retourne :** `self` - Nouvelle collection contenant uniquement les résultats correspondants

**Exemple :**
```php
<?php

$userResults = $collection->filterByNamespace('App\\Models\\User');
```

---

### `filterByNamespaces(array $namespaces): self`

Filtre les résultats pour ne conserver que ceux appartenant à l'un des namespaces donnés.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$namespaces` | `string[]` | Tableau des namespaces |

**Retourne :** `self` - Nouvelle collection contenant uniquement les résultats correspondants

**Exemple :**
```php
<?php

$results = $collection->filterByNamespaces([
    'App\\Models\\User',
    'App\\Models\\Product'
]);
```

---

### `getData(): array`

Extrait toutes les données des résultats sous forme de tableaux.

**Retourne :** `array<array<string, mixed>>` - Tableau des données

**Exemple :**
```php
<?php

$allData = $collection->getData();
// [['name' => 'John', 'email' => 'john@test.com'], ...]
```

---

### `getMatches(): array`

Extrait toutes les correspondances des résultats.

**Retourne :** `array<array<string, mixed>>` - Tableau des correspondances

**Exemple :**
```php
<?php

$allMatches = $collection->getMatches();
// [[['field' => 'name', 'original_text' => 'John', ...]], ...]
```

---

### `getModelInstances(array $with = []): Collection`

Récupère les instances des modèles pour tous les résultats en une seule requête par classe. Les modèles non trouvés sont ignorés silencieusement. Les instances sont retournées dans l'ordre de la collection.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$with` | `string[]` | Relations à charger (ex: ['profile', 'profile.specialty']) |

**Retourne :** `Collection<int, Model&Indexable>` - Collection des instances de modèles

**Exceptions :** `InvalidArgumentException` - Si une relation demandée n'existe pas sur un modèle

**Exemple :**
```php
<?php

// Chargement simple
$models = $collection->getModelInstances();

// Chargement avec relations
$models = $collection->getModelInstances(['profile', 'profile.specialty']);

foreach ($models as $model) {
    echo $model->name;
}
```

---

### `getGroupedIdsByClass(): array`

Groupe les identifiants d'entités par classe complète.

**Retourne :** `array<string, array<int|string>>` - Tableau associatif [classe => IDs]

**Exemple :**
```php
<?php

$grouped = $collection->getGroupedIdsByClass();
// ['App\\Models\\User' => ['1', '2'], 'App\\Models\\Product' => ['42']]
```

---

### `belongsToNamespace(string $namespace): bool`

Vérifie si un résultat appartient au namespace donné.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$namespace` | `string` | Namespace à vérifier |

**Retourne :** `bool` - True si au moins un résultat appartient au namespace

**Exemple :**
```php
<?php

if ($collection->belongsToNamespace('App\\Models\\User')) {
    // Traiter les résultats utilisateur
}
```

---

### `belongsToAnyNamespace(array $namespaces): bool`

Vérifie si un résultat appartient à l'un des namespaces donnés.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$namespaces` | `string[]` | Tableau des namespaces |

**Retourne :** `bool` - True si au moins un résultat appartient à un namespace

**Exemple :**
```php
<?php

if ($collection->belongsToAnyNamespace(['App\\Models\\User', 'App\\Models\\Product'])) {
    // Traiter les résultats utilisateur ou produit
}
```

---

### `groupByNamespace(): array`

Groupe les résultats par namespace.

**Retourne :** `array<string, self>` - Tableau associatif [namespace => collection]

**Exemple :**
```php
<?php

$groups = $collection->groupByNamespace();

foreach ($groups as $namespace => $records) {
    echo "Namespace {$namespace} : " . $records->count() . " résultats\n";
}
```

## Cas d'utilisation

### Cas 1 : Affichage des résultats par type d'entité

**Problème :** On souhaite afficher les résultats de recherche regroupés par type d'entité (utilisateurs, produits, etc.).

```php
<?php

$groups = $collection->groupByNamespace();

foreach ($groups as $namespace => $records) {
    $type = basename(str_replace('\\', '/', $namespace));
    echo "=== {$type} ===\n";
    
    foreach ($records as $record) {
        $data = $record->data->toArray();
        echo "- " . ($data['name'] ?? $data['title'] ?? 'Sans nom') . "\n";
    }
}
```

### Cas 2 : Chargement des modèles avec relations

**Problème :** On doit afficher les résultats avec leurs relations (ex: utilisateurs avec leurs adresses).

```php
<?php

$collection = $hermes->search($request);
$models = $collection->getModelInstances(['addresses']);

foreach ($models as $user) {
    echo "Utilisateur : " . $user->name . "\n";
    echo "Adresses :\n";
    foreach ($user->addresses as $address) {
        echo "  - " . $address->street . ", " . $address->city . "\n";
    }
}
```

### Cas 3 : Filtrage par type et seuil de pertinence

**Problème :** On cherche uniquement les utilisateurs avec une similarité élevée.

```php
<?php

$filtered = $collection
    ->filterByNamespace('App\\Models\\User')
    ->filterByMinSimilarity(0.8);

$models = $filtered->getModelInstances();

echo "Utilisateurs pertinents trouvés : " . $models->count() . "\n";
```

### Cas 4 : Extraction et analyse des résultats

**Problème :** On veut analyser la répartition des résultats par type.

```php
<?php

$namespaces = $collection->getNamespaces();
$counts = [];

foreach ($namespaces as $namespace) {
    $counts[$namespace] = $collection
        ->filterByNamespace($namespace)
        ->count();
}

foreach ($counts as $namespace => $count) {
    $type = basename(str_replace('\\', '/', $namespace));
    echo "{$type} : {$count} résultats\n";
}
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Ajout d'un élément de type incorrect | `InvalidArgumentException` | `Item must be of type AndyDefer\LaravelHermes\Records\SearchResultRecord` |
| Relation demandée inexistante sur un modèle | `InvalidArgumentException` | `Relations [X] do not exist on model [Y]` |

## Intégration

Cette collection est utilisée par :

- `HermesService::search()` - Retourne les résultats de recherche
- `SearchResultVO` - Enrichit les résultats pour l'affichage
- Les résultats peuvent être convertis en modèles Eloquent via `getModelInstances()`

## Performance

- **Extraction de données** : O(n) pour chaque méthode d'extraction
- **Filtrage** : O(n) - création d'une nouvelle collection
- **Regroupement** : O(n) - parcours unique des éléments
- **Chargement des modèles** : O(n + m) - n = résultats, m = requêtes (une par classe distincte)
- **Vérifications** : O(n) pour les méthodes `belongsTo*`
- **Mémoire** : Les méthodes de filtrage et regroupement créent de nouvelles collections

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |
| Laravel 10+ | ✅ Complet (pour getModelInstances) |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelHermes\Collections\SearchResultRecordCollection;
use AndyDefer\LaravelHermes\Records\SearchResultRecord;

// Création de la collection
$collection = new SearchResultRecordCollection();

// Ajout de résultats
$collection->add(new SearchResultRecord(
    document_id: 'doc_1',
    fingerprint: 'App\\Models\\User|1',
    data: StrictAssociative::from(['name' => 'John Doe', 'email' => 'john@test.com']),
    matches: new MatchRecordCollection(),
    similarity: 0.95
));

$collection->add(new SearchResultRecord(
    document_id: 'doc_2',
    fingerprint: 'App\\Models\\User|2',
    data: StrictAssociative::from(['name' => 'Jane Smith', 'email' => 'jane@test.com']),
    matches: new MatchRecordCollection(),
    similarity: 0.85
));

$collection->add(new SearchResultRecord(
    document_id: 'doc_3',
    fingerprint: 'App\\Models\\Product|42',
    data: StrictAssociative::from(['name' => 'Laptop Pro', 'price' => 1299.99]),
    matches: new MatchRecordCollection(),
    similarity: 0.75
));

// Extraction des données
$namespaces = $collection->getNamespaces();
$ids = $collection->getEntityIds();

echo "Namespaces : " . implode(', ', $namespaces) . "\n";
echo "IDs : " . implode(', ', $ids) . "\n";

// Filtrage des résultats de qualité
$qualityResults = $collection
    ->filterByMinSimilarity(0.8)
    ->filterByNamespace('App\\Models\\User');

echo "Utilisateurs pertinents : " . $qualityResults->count() . "\n";

// Regroupement par namespace
$groups = $collection->groupByNamespace();

foreach ($groups as $namespace => $records) {
    $count = $records->count();
    $avgSimilarity = $records->average(function ($record) {
        return $record->similarity;
    });
    
    echo "{$namespace} : {$count} résultats, similarité moyenne : " . round($avgSimilarity, 2) . "\n";
}
```

## Voir aussi

- `SearchResultRecord` - Structure de données d'un résultat de recherche
- `MatchRecordCollection` - Collection des correspondances
- `HermesService` - Service principal de recherche
- `AbstractTypedCollection` - Classe parente des collections typées