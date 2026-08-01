# SearchResultVOCollection - Référence Technique

## Description

Collection typée d'objets de valeur de résultats de recherche (`SearchResultVO`).

## Hiérarchie

```
AbstractTypedCollection
    └── SearchResultVOCollection
```

**Interfaces héritées :** `IteratorAggregate`, `ArrayAccess`, `Countable`

## Rôle principal

Regroupe et manipule des objets de valeur de résultats de recherche enrichis. Fournit des opérations d'extraction de données, de filtrage par similarité et namespace, de regroupement, et d'accès aux informations structurées des résultats.

## API / Méthodes publiques

### `getDatas(): DataCollection`

Extrait tous les objets de données de la collection.

**Retourne :** `DataCollection` - Collection des objets de données

**Exemple :**
```php
<?php

$dataObjects = $collection->getDatas();

foreach ($dataObjects as $data) {
    echo $data->name . "\n";
}
```

---

### `getFingerprints(): StringTypedCollection`

Extrait tous les fingerprints de la collection.

**Retourne :** `StringTypedCollection` - Collection des fingerprints

**Exemple :**
```php
<?php

$fingerprints = $collection->getFingerprints();
// Collection contenant : ['App\\Models\\User|1', 'App\\Models\\Product|42']
```

---

### `getMatches(): MatchRecordCollection`

Extrait tous les enregistrements de correspondance de la collection.

**Retourne :** `MatchRecordCollection` - Collection des correspondances

**Exemple :**
```php
<?php

$matches = $collection->getMatches();

foreach ($matches as $match) {
    echo "Champ : " . $match->field . "\n";
    echo "Texte : " . $match->original_text . "\n";
}
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

### `filterByNamespace(string $namespace): self`

Filtre les résultats pour ne conserver que ceux appartenant au namespace donné.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$namespace` | `string` | Namespace à filtrer |

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

### `groupByNamespace(): StrictAssociative`

Groupe les résultats par namespace.

**Retourne :** `StrictAssociative<string, self>` - Tableau associatif [namespace => collection]

**Exemple :**
```php
<?php

$groups = $collection->groupByNamespace();

foreach ($groups as $namespace => $records) {
    echo "Namespace {$namespace} : " . $records->count() . " résultats\n";
}
```

---

### `getSimilarities(): array`

Extrait tous les scores de similarité de la collection.

**Retourne :** `float[]` - Tableau des scores de similarité

**Exemple :**
```php
<?php

$scores = $collection->getSimilarities();
// [0.95, 0.85, 0.75]
```

---

### `getBestMatch(): ?SearchResultVO`

Retourne le résultat avec le score de similarité le plus élevé.

**Retourne :** `SearchResultVO|null` - Le meilleur résultat, ou null si la collection est vide

**Exemple :**
```php
<?php

$best = $collection->getBestMatch();

if ($best !== null) {
    echo "Meilleur résultat : " . $best->getFingerprint() . "\n";
}
```

---

### `getDataArrays(): array`

Extrait toutes les données sous forme de tableaux.

**Retourne :** `array<array<string, mixed>>` - Tableau des données

**Exemple :**
```php
<?php

$dataArrays = $collection->getDataArrays();
// [['name' => 'John', 'email' => 'john@test.com'], ...]
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

## Cas d'utilisation

### Cas 1 : Affichage des résultats enrichis

**Problème :** On souhaite afficher les résultats de recherche avec leurs métadonnées.

```php
<?php

$vos = new SearchResultVOCollection();

foreach ($vos as $vo) {
    $data = $vo->getValue();
    echo "Fingerprint : " . $data['fingerprint'] . "\n";
    echo "Similarité : " . $data['similarity'] . "\n";
    echo "Données : " . json_encode($data['data']->toArray()) . "\n";
    echo "Matches : " . count($data['matches']) . "\n";
    echo "---\n";
}
```

### Cas 2 : Regroupement par type d'entité

**Problème :** On veut organiser les résultats par type d'entité pour un affichage structuré.

```php
<?php

$groups = $collection->groupByNamespace();

foreach ($groups as $namespace => $records) {
    $type = basename(str_replace('.', '/', $namespace));
    echo "=== {$type} ===\n";
    
    foreach ($records as $record) {
        $data = $record->getValue()['data']->toArray();
        echo "- " . ($data['name'] ?? 'Sans nom') . "\n";
    }
}
```

### Cas 3 : Extraction des correspondances pour analyse

**Problème :** On doit analyser toutes les correspondances trouvées.

```php
<?php

$allMatches = $collection->getMatches();

$fieldStats = [];
foreach ($allMatches as $match) {
    $field = $match->field;
    if (!isset($fieldStats[$field])) {
        $fieldStats[$field] = 0;
    }
    $fieldStats[$field]++;
}

foreach ($fieldStats as $field => $count) {
    echo "Champ '{$field}' : {$count} correspondances\n";
}
```

### Cas 4 : Filtrage multi-critères

**Problème :** On cherche des résultats spécifiques avec plusieurs critères.

```php
<?php

$filtered = $collection
    ->filterByNamespace('App\\Models\\User')
    ->filterByMinSimilarity(0.8);

$best = $filtered->getBestMatch();

if ($best !== null) {
    $data = $best->getValue();
    echo "Meilleur utilisateur : " . $data['data']->name . "\n";
    echo "Similarité : " . $data['similarity'] . "\n";
}
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Ajout d'un élément de type incorrect | `InvalidArgumentException` | `Item must be of type AndyDefer\LaravelHermes\ValueObjects\SearchResultVO` |

## Intégration

Cette collection est utilisée par :

- `HermesService::search()` - Transformation des résultats en VO
- Interfaces utilisateur - Présentation des résultats enrichis
- Systèmes d'analyse - Extraction et traitement des données

## Performance

- **Extraction de données** : O(n) pour chaque méthode d'extraction
- **Filtrage** : O(n) - création d'une nouvelle collection
- **Regroupement** : O(n) - parcours unique des éléments
- **Meilleur résultat** : O(n) - parcours unique
- **Mémoire** : Les méthodes de filtrage et regroupement créent de nouvelles collections

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelHermes\Collections\SearchResultVOCollection;
use AndyDefer\LaravelHermes\ValueObjects\SearchResultVO;

// Création de la collection (exemple avec des données simulées)
$collection = new SearchResultVOCollection();

// Ajout de VO (généralement créés par HermesService)
// $collection->add($vo1, $vo2, $vo3);

// Extraction des données
$fingerprints = $collection->getFingerprints();
$scores = $collection->getSimilarities();
$namespaces = $collection->getNamespaces();

echo "Nombre de résultats : " . $collection->count() . "\n";
echo "Namespaces : " . implode(', ', $namespaces) . "\n";

// Filtrage des résultats de qualité
$qualityResults = $collection
    ->filterByMinSimilarity(0.8)
    ->filterByNamespace('App\\Models\\User');

echo "Utilisateurs pertinents : " . $qualityResults->count() . "\n";

// Meilleur résultat
$best = $collection->getBestMatch();
if ($best !== null) {
    $data = $best->getValue();
    echo "Meilleur score : " . $data['similarity'] . "\n";
    echo "Fingerprint : " . $data['fingerprint'] . "\n";
}

// Regroupement par namespace
$groups = $collection->groupByNamespace();

foreach ($groups as $namespace => $records) {
    $avgSimilarity = array_sum(
        array_map(
            fn($vo) => $vo->getValue()['similarity'],
            $records->toArray()
        )
    ) / $records->count();
    
    $type = basename(str_replace('.', '/', $namespace));
    echo "{$type} : {$records->count()} résultats, similarité moyenne : " . round($avgSimilarity, 2) . "\n";
}

// Extraction des données pour export
$dataArrays = $collection->getDataArrays();
$json = json_encode($dataArrays, JSON_PRETTY_PRINT);
file_put_contents('results.json', $json);
```

## Voir aussi

- `SearchResultVO` - Objet de valeur d'un résultat de recherche
- `SearchResultRecordCollection` - Collection des enregistrements sources
- `MatchRecordCollection` - Collection des correspondances
- `DataCollection` - Collection de données typée
- `AbstractTypedCollection` - Classe parente des collections typées