# MatchRecordCollection - Référence Technique

## Description

Collection typée d'enregistrements de correspondance retournés par les opérations de recherche.

## Hiérarchie

```
AbstractTypedCollection
    └── MatchRecordCollection
```

**Interfaces héritées :** `IteratorAggregate`, `ArrayAccess`, `Countable`

## Rôle principal

Regroupe et manipule des enregistrements de correspondance (`MatchRecord`) issus des résultats de recherche. Fournit des opérations d'extraction, de filtrage, de regroupement, d'analyse statistique et de tri pour faciliter l'exploitation des correspondances trouvées par champ.

## API / Méthodes publiques

### `getFields(): array`

Extrait tous les noms de champs des enregistrements.

**Retourne :** `string[]` - Tableau des noms de champs

**Exemple :**
```php
<?php

$collection = new MatchRecordCollection();
$collection->add(new MatchRecord('name', 'John', 0.95));
$collection->add(new MatchRecord('email', 'john@test.com', 0.85));

$fields = $collection->getFields();
// ['name', 'email']
```

---

### `getOriginalTexts(): array`

Extrait tous les textes originaux des enregistrements.

**Retourne :** `string[]` - Tableau des textes originaux

**Exemple :**
```php
<?php

$texts = $collection->getOriginalTexts();
// ['John', 'john@test.com', 'Laptop Pro']
```

---

### `getSimilarities(): array`

Extrait tous les scores de similarité des enregistrements.

**Retourne :** `float[]` - Tableau des scores de similarité

**Exemple :**
```php
<?php

$scores = $collection->getSimilarities();
// [0.95, 0.85, 0.75]
```

---

### `filterByField(string $field): self`

Filtre la collection pour ne conserver que les enregistrements correspondant au champ donné.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$field` | `string` | Nom du champ (ex: 'name', 'email') |

**Retourne :** `self` - Nouvelle collection contenant uniquement les enregistrements correspondants

**Exemple :**
```php
<?php

$nameMatches = $collection->filterByField('name');
// Ne conserve que les correspondances du champ 'name'
```

---

### `filterByMinSimilarity(float $minSimilarity): self`

Filtre la collection pour ne conserver que les enregistrements ayant un score de similarité supérieur ou égal au seuil donné.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$minSimilarity` | `float` | Seuil minimum (0.0 à 1.0) |

**Retourne :** `self` - Nouvelle collection contenant uniquement les enregistrements au-dessus du seuil

**Exemple :**
```php
<?php

$highQualityMatches = $collection->filterByMinSimilarity(0.8);
// Ne conserve que les correspondances avec similarité >= 0.8
```

---

### `getAverageSimilarity(): float`

Calcule la moyenne des scores de similarité de tous les enregistrements.

**Retourne :** `float` - Moyenne des similarités, ou 0.0 si la collection est vide

**Exemple :**
```php
<?php

$avg = $collection->getAverageSimilarity();
// 0.85 (moyenne de 0.95, 0.85, 0.75)
```

---

### `getBestMatch(): ?MatchRecord`

Retourne l'enregistrement avec le score de similarité le plus élevé.

**Retourne :** `MatchRecord|null` - Le meilleur résultat, ou null si la collection est vide

**Exemple :**
```php
<?php

$best = $collection->getBestMatch();

if ($best !== null) {
    echo "Meilleure correspondance : " . $best->original_text;
}
```

---

### `groupByField(): array`

Groupe les enregistrements par nom de champ.

**Retourne :** `array<string, self>` - Tableau associatif [field => collection]

**Exemple :**
```php
<?php

$groups = $collection->groupByField();

foreach ($groups as $field => $records) {
    echo "Champ {$field} : " . $records->count() . " correspondances\n";
}
```

---

### `sortBySimilarityDesc(): self`

Trie les enregistrements par score de similarité décroissant (du plus élevé au plus bas).

**Retourne :** `self` - Nouvelle collection triée par similarité décroissante

**Exemple :**
```php
<?php

$sorted = $collection->sortBySimilarityDesc();
$best = $sorted->first(); // Meilleure correspondance en premier
```

## Cas d'utilisation

### Cas 1 : Affichage des correspondances par champ

**Problème :** On souhaite afficher les correspondances regroupées par champ pour une meilleure lisibilité.

```php
<?php

use AndyDefer\LaravelHermes\Collections\MatchRecordCollection;
use AndyDefer\LaravelHermes\Records\MatchRecord;

$collection = new MatchRecordCollection();

// Ajout de correspondances
$collection->add(new MatchRecord('name', 'John Doe', 0.95));
$collection->add(new MatchRecord('email', 'john@test.com', 0.85));
$collection->add(new MatchRecord('name', 'Jonny', 0.75));
$collection->add(new MatchRecord('description', 'Developer', 0.65));

// Regroupement par champ
$grouped = $collection->groupByField();

foreach ($grouped as $field => $records) {
    echo "Correspondances pour '{$field}' :\n";
    foreach ($records as $record) {
        echo "  - " . $record->original_text . " (" . $record->similarity . ")\n";
    }
}
```

### Cas 2 : Filtrage et analyse des résultats

**Problème :** On veut analyser la qualité des correspondances pour un champ spécifique.

```php
<?php

$collection = new MatchRecordCollection();
// ... remplissage de la collection ...

// Filtrage par champ et seuil
$filtered = $collection
    ->filterByField('email')
    ->filterByMinSimilarity(0.7);

// Analyse
$best = $filtered->getBestMatch();
$avg = $filtered->getAverageSimilarity();

echo "Meilleure correspondance email : " . ($best ? $best->original_text : 'Aucune') . "\n";
echo "Similarité moyenne : " . $avg . "\n";
echo "Nombre de correspondances : " . $filtered->count() . "\n";
```

### Cas 3 : Tri pour affichage par pertinence

**Problème :** On souhaite afficher les correspondances triées par pertinence décroissante.

```php
<?php

$collection = new MatchRecordCollection();
// ... remplissage de la collection ...

// Tri par similarité
$sorted = $collection->sortBySimilarityDesc();

// Affichage des résultats triés
foreach ($sorted as $index => $record) {
    $position = $index + 1;
    echo "#{$position} : {$record->original_text} (similarité: {$record->similarity})\n";
}
```

### Cas 4 : Statistiques de qualité

**Problème :** On doit évaluer la qualité globale des correspondances pour un champ.

```php
<?php

function analyzeMatches(MatchRecordCollection $collection): array
{
    return [
        'total' => $collection->count(),
        'average' => $collection->getAverageSimilarity(),
        'best' => $collection->getBestMatch()?->original_text,
        'best_score' => $collection->getBestMatch()?->similarity,
        'fields' => $collection->getFields(),
        'high_quality' => $collection->filterByMinSimilarity(0.8)->count(),
    ];
}

$collection = new MatchRecordCollection();
// ... remplissage de la collection ...

$stats = analyzeMatches($collection);

echo "Rapport d'analyse :\n";
echo "- Total : {$stats['total']}\n";
echo "- Moyenne : {$stats['average']}\n";
echo "- Meilleur score : {$stats['best_score']}\n";
echo "- Correspondances de qualité : {$stats['high_quality']}\n";
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Ajout d'un élément de type incorrect | `InvalidArgumentException` | `Item must be of type AndyDefer\LaravelHermes\Records\MatchRecord` |

## Intégration

Cette collection est utilisée par :

- `SearchResultRecord` - Contient les correspondances d'un résultat de recherche
- `SearchResultVO` - Enrichit les correspondances pour l'affichage
- `HermesService::search()` - Retourne les correspondances trouvées

## Performance

- **Extraction de données** : O(n) pour chaque méthode d'extraction
- **Filtrage** : O(n) - création d'une nouvelle collection
- **Regroupement** : O(n) - parcours unique des éléments
- **Meilleur résultat** : O(n) - parcours unique pour trouver le maximum
- **Tri** : O(n log n) - tri par similarité décroissante
- **Moyenne** : O(n) - parcours unique
- **Mémoire** : Les méthodes de filtrage, regroupement et tri créent de nouvelles collections

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelHermes\Collections\MatchRecordCollection;
use AndyDefer\LaravelHermes\Records\MatchRecord;

// Création de la collection
$collection = new MatchRecordCollection();

// Ajout de correspondances
$collection->add(new MatchRecord('name', 'John Doe', 0.95));
$collection->add(new MatchRecord('email', 'john.doe@company.com', 0.88));
$collection->add(new MatchRecord('name', 'Jane Smith', 0.82));
$collection->add(new MatchRecord('email', 'jane.smith@company.com', 0.79));
$collection->add(new MatchRecord('name', 'Johnny', 0.65));
$collection->add(new MatchRecord('description', 'Senior Developer', 0.60));

// Extraction des données
$fields = $collection->getFields();
$texts = $collection->getOriginalTexts();
$scores = $collection->getSimilarities();

echo "Nombre de correspondances : " . $collection->count() . "\n";
echo "Champs : " . implode(', ', array_unique($fields)) . "\n";

// Filtrer les correspondances de qualité
$highQuality = $collection
    ->filterByMinSimilarity(0.8)
    ->sortBySimilarityDesc();

echo "\nCorrespondances de qualité (>= 0.8) :\n";
foreach ($highQuality as $index => $record) {
    $position = $index + 1;
    echo "#{$position} - {$record->field}: {$record->original_text} ({$record->similarity})\n";
}

// Statistiques
$avg = $collection->getAverageSimilarity();
$best = $collection->getBestMatch();

echo "\nStatistiques :\n";
echo "- Similarité moyenne : " . round($avg, 2) . "\n";
echo "- Meilleure correspondance : " . ($best ? $best->original_text : 'Aucune') . "\n";
echo "- Score max : " . ($best ? $best->similarity : 0) . "\n";

// Regroupement par champ
$grouped = $collection->groupByField();

echo "\nRegroupement par champ :\n";
foreach ($grouped as $field => $records) {
    $avgField = $records->getAverageSimilarity();
    echo "- {$field}: {$records->count()} correspondances (moyenne: " . round($avgField, 2) . ")\n";
}
```

## Voir aussi

- `MatchRecord` - Structure de données d'une correspondance
- `SearchResultRecord` - Résultat de recherche contenant des correspondances
- `AbstractTypedCollection` - Classe parente fournissant les opérations de base sur les collections