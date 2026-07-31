# CompletionResultRecordCollection - Référence Technique

## Description

Collection typée de résultats de complétion retournés par les opérations de recherche prédictive.

## Hiérarchie

```
AbstractTypedCollection
    └── CompletionResultRecordCollection
```

**Interfaces héritées :** `IteratorAggregate`, `ArrayAccess`, `Countable`

## Rôle principal

Regroupe et manipule des enregistrements de résultats de complétion (`CompletionResultRecord`). Fournit des opérations de filtrage, de regroupement et d'extraction de données pour faciliter l'exploitation des résultats de complétion de texte.

## API / Méthodes publiques

### `getTokens(): array`

Retourne tous les tokens des résultats.

| Paramètre | Type | Description |
|-----------|------|-------------|
| - | - | - |

**Retourne :** `string[]` - Tableau des tokens

**Exemple :**
```php
<?php

$collection = new CompletionResultRecordCollection();
$collection->add(new CompletionResultRecord(...));

$tokens = $collection->getTokens();
// ['john', 'jane', 'product']
```

---

### `getOriginalTexts(): array`

Retourne tous les textes originaux des résultats.

**Retourne :** `string[]` - Tableau des textes originaux

**Exemple :**
```php
<?php

$originalTexts = $collection->getOriginalTexts();
// ['John Doe', 'Jane Smith', 'Laptop Pro']
```

---

### `getTokenIds(): array`

Retourne tous les identifiants des tokens.

**Retourne :** `string[]` - Tableau des ID de tokens

**Exemple :**
```php
<?php

$tokenIds = $collection->getTokenIds();
// ['token_123', 'token_456', 'token_789']
```

---

### `getDocumentIds(): array`

Retourne tous les identifiants des documents.

**Retourne :** `string[]` - Tableau des ID de documents

**Exemple :**
```php
<?php

$docIds = $collection->getDocumentIds();
// ['doc_1', 'doc_1', 'doc_2']
```

---

### `filterByField(string $field): self`

Filtre les résultats pour ne conserver que ceux correspondant au champ donné.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$field` | `string` | Nom du champ (ex: 'name', 'email') |

**Retourne :** `self` - Nouvelle collection contenant uniquement les résultats correspondants

**Exemple :**
```php
<?php

$nameMatches = $collection->filterByField('name');
// Ne conserve que les résultats du champ 'name'
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

$highQualityMatches = $collection->filterByMinSimilarity(0.8);
// Ne conserve que les résultats avec similarité >= 0.8
```

---

### `getSimilarities(): array`

Retourne tous les scores de similarité.

**Retourne :** `float[]` - Tableau des scores de similarité

**Exemple :**
```php
<?php

$scores = $collection->getSimilarities();
// [0.95, 0.85, 0.75]
```

---

### `getFields(): array`

Retourne tous les noms de champs des résultats.

**Retourne :** `string[]` - Tableau des noms de champs

**Exemple :**
```php
<?php

$fields = $collection->getFields();
// ['name', 'email', 'description']
```

---

### `getBestMatch(): ?CompletionResultRecord`

Retourne l'enregistrement avec le score de similarité le plus élevé.

**Retourne :** `CompletionResultRecord|null` - Le meilleur résultat, ou null si la collection est vide

**Exemple :**
```php
<?php

$best = $collection->getBestMatch();

if ($best !== null) {
    echo "Meilleur résultat : " . $best->original_text;
}
```

---

### `groupByDocument(): array`

Groupe les résultats par identifiant de document.

**Retourne :** `array<string, self>` - Tableau associatif [document_id => collection]

**Exemple :**
```php
<?php

$groups = $collection->groupByDocument();

foreach ($groups as $docId => $records) {
    echo "Document {$docId} : " . $records->count() . " résultats";
}
```

---

### `groupByField(): array`

Groupe les résultats par nom de champ.

**Retourne :** `array<string, self>` - Tableau associatif [field => collection]

**Exemple :**
```php
<?php

$groups = $collection->groupByField();

foreach ($groups as $field => $records) {
    echo "Champ {$field} : " . $records->count() . " résultats";
}
```

## Cas d'utilisation

### Cas 1 : Affichage des résultats de complétion par champ

**Problème :** Une interface de recherche doit afficher les suggestions de complétion regroupées par champ (nom, email, description).

```php
<?php

use AndyDefer\LaravelHermes\Collections\CompletionResultRecordCollection;
use AndyDefer\LaravelHermes\Records\CompletionResultRecord;

$collection = new CompletionResultRecordCollection();

// Ajout de résultats
$collection->add(new CompletionResultRecord(
    token_id: 'token_1',
    document_id: 'doc_1',
    token: 'john',
    original_text: 'John Doe',
    field: 'name',
    similarity: 0.95
));

$collection->add(new CompletionResultRecord(
    token_id: 'token_2',
    document_id: 'doc_1',
    token: 'johndoe',
    original_text: 'johndoe@example.com',
    field: 'email',
    similarity: 0.85
));

// Regroupement par champ
$grouped = $collection->groupByField();

foreach ($grouped as $field => $records) {
    echo "Suggestions pour le champ '{$field}':\n";
    foreach ($records as $record) {
        echo "  - " . $record->original_text . " (similarité: " . $record->similarity . ")\n";
    }
}
```

### Cas 2 : Filtrage des résultats par seuil de pertinence

**Problème :** On souhaite n'afficher que les suggestions très pertinentes (similarité > 0.8) pour un champ spécifique.

```php
<?php

$collection = new CompletionResultRecordCollection();

// ... remplissage de la collection ...

// Filtre par similarité puis par champ
$filtered = $collection
    ->filterByMinSimilarity(0.8)
    ->filterByField('email');

foreach ($filtered as $record) {
    echo "Email suggéré : " . $record->original_text . "\n";
}
```

### Cas 3 : Extraction des données pour affichage

**Problème :** On veut afficher une liste simple des suggestions avec leurs scores.

```php
<?php

$collection = new CompletionResultRecordCollection();

// ... remplissage de la collection ...

$texts = $collection->getOriginalTexts();
$scores = $collection->getSimilarities();

// Affichage sous forme de tableau
$data = array_map(
    fn($text, $score) => "{$text} ({$score}%)",
    $texts,
    $scores
);

echo implode("\n", $data);
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Ajout d'un élément de type incorrect | `InvalidArgumentException` | `Item must be of type AndyDefer\LaravelHermes\Records\CompletionResultRecord` |

## Intégration

Cette collection est utilisée par :

- `HermesService::complete()` - Retourne les résultats de complétion encapsulés dans cette collection
- Les résultats sont typés pour garantir la cohérence des données

## Performance

- **Extraction de données** : O(n) pour chaque méthode d'extraction (`getTokens()`, `getOriginalTexts()`, etc.)
- **Filtrage** : O(n) - une nouvelle collection est créée avec les éléments filtrés
- **Regroupement** : O(n) - parcours unique des éléments
- **Meilleur résultat** : O(n) - parcours unique pour trouver le maximum
- **Mémoire** : Les méthodes de filtrage et de regroupement créent de nouvelles collections (ne modifient pas l'originale)

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelHermes\Collections\CompletionResultRecordCollection;
use AndyDefer\LaravelHermes\Records\CompletionResultRecord;

// Création de la collection
$collection = new CompletionResultRecordCollection();

// Ajout de résultats
$collection->add(new CompletionResultRecord(
    token_id: 'token_1',
    document_id: 'doc_user_1',
    token: 'john',
    original_text: 'John Doe',
    field: 'name',
    similarity: 0.95
));

$collection->add(new CompletionResultRecord(
    token_id: 'token_2',
    document_id: 'doc_user_1',
    token: 'johndoe',
    original_text: 'john.doe@company.com',
    field: 'email',
    similarity: 0.85
));

$collection->add(new CompletionResultRecord(
    token_id: 'token_3',
    document_id: 'doc_product_1',
    token: 'prod',
    original_text: 'Laptop Pro',
    field: 'name',
    similarity: 0.75
));

// Filtrage : uniquement les résultats du champ 'name' avec similarité >= 0.8
$filtered = $collection
    ->filterByField('name')
    ->filterByMinSimilarity(0.8);

// Meilleur résultat
$best = $filtered->getBestMatch();

echo "Meilleure suggestion : " . ($best ? $best->original_text : 'Aucune') . "\n";
// Affiche : "Meilleure suggestion : John Doe"

// Extraction des données
$tokens = $filtered->getTokens();
$texts = $filtered->getOriginalTexts();

echo "Résultats trouvés : " . count($tokens) . "\n";
```

## Voir aussi

- `CompletionResultRecord` - Structure de données d'un résultat de complétion
- `HermesService` - Service principal de recherche et complétion
- `AbstractTypedCollection` - Classe parente fournissant les opérations de base sur les collections