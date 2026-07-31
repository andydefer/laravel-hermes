# SuggestionResultRecord - Référence Technique

## Description

Enregistrement de résultat pour les opérations de suggestion de texte.

## Hiérarchie

```
AbstractRecord
    └── SuggestionResultRecord
```

**Interfaces héritées :** `ArrayAccess`, `JsonSerializable`

## Rôle principal

Représente un résultat de suggestion retourné par les opérations de complétion et de suggestion. Contient le token suggéré, son texte original, le champ associé, l'identifiant du document source et le score de similarité.

## API / Propriétés publiques

### `$token_id : string`

Identifiant unique du token dans le système d'indexation.

**Exemple :**
```php
<?php

$record->token_id; // 'token_abc123'
```

---

### `$document_id : string`

Identifiant du document source contenant ce token.

**Exemple :**
```php
<?php

$record->document_id; // 'doc_42'
```

---

### `$token : string`

Le token indexé (version normalisée du texte original).

**Exemple :**
```php
<?php

$record->token; // 'john'
```

---

### `$original_text : string`

Le texte original avant normalisation (ex: la valeur réelle affichée à l'utilisateur).

**Exemple :**
```php
<?php

$record->original_text; // 'John Doe'
```

---

### `$field : string`

Le nom du champ dans lequel le token a été trouvé (ex: 'name', 'email', 'description').

**Exemple :**
```php
<?php

$record->field; // 'name'
```

---

### `$similarity : float`

Score de similarité entre la requête et le token (0.0 à 1.0).

**Exemple :**
```php
<?php

$record->similarity; // 0.95
```

## Cas d'utilisation

### Cas 1 : Affichage des suggestions dans une interface utilisateur

**Problème :** On souhaite afficher des suggestions de complétion pour un champ de recherche.

```php
<?php

use AndyDefer\LaravelHermes\Records\SuggestionResultRecord;

$suggestions = $hermes->suggest($request);

foreach ($suggestions as $record) {
    echo "Suggestion : " . $record->original_text . "\n";
    echo "Champ : " . $record->field . "\n";
    echo "Pertinence : " . ($record->similarity * 100) . "%\n";
    echo "---\n";
}
```

### Cas 2 : Regroupement des suggestions par champ

**Problème :** On veut organiser les suggestions par champ pour un affichage structuré.

```php
<?php

$suggestions = $hermes->suggest($request);
$groups = $suggestions->groupByField();

foreach ($groups as $field => $records) {
    echo "Suggestions pour '{$field}':\n";
    foreach ($records as $record) {
        echo "  - " . $record->original_text . "\n";
    }
}
```

### Cas 3 : Filtrage des suggestions par pertinence

**Problème :** On ne veut afficher que les suggestions très pertinentes.

```php
<?php

$suggestions = $hermes->suggest($request);
$filtered = $suggestions->filterByMinSimilarity(0.8);

foreach ($filtered as $record) {
    echo "Suggestion pertinente : " . $record->original_text . "\n";
}
```

### Cas 4 : Récupération du meilleur résultat

**Problème :** On souhaite obtenir la suggestion la plus pertinente.

```php
<?php

$suggestions = $hermes->suggest($request);
$best = $suggestions->getBestMatch();

if ($best !== null) {
    echo "Meilleure suggestion : " . $best->original_text . "\n";
    echo "Score : " . $best->similarity . "\n";
}
```

## Intégration

Cette collection est utilisée par :

- `HermesService::suggest()` - Retourne les suggestions
- `SuggestionResultRecordCollection` - Collection typée pour les résultats de suggestion

## Performance

- **Propriétés immutables** : Toutes les propriétés sont `readonly` pour garantir l'immutabilité
- **Structure légère** : Pas de logique métier dans l'enregistrement
- **Type strict** : Utilisation de types PHP natifs pour la performance

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelHermes\Records\SuggestionResultRecord;
use AndyDefer\LaravelHermes\Collections\SuggestionResultRecordCollection;

// Création d'un enregistrement
$record = new SuggestionResultRecord(
    token_id: 'token_123',
    document_id: 'doc_42',
    token: 'john',
    original_text: 'John Doe',
    field: 'name',
    similarity: 0.95
);

// Accès aux propriétés
echo "Token ID : " . $record->token_id . "\n";
echo "Document ID : " . $record->document_id . "\n";
echo "Token : " . $record->token . "\n";
echo "Texte original : " . $record->original_text . "\n";
echo "Champ : " . $record->field . "\n";
echo "Similarité : " . $record->similarity . "\n";

// Utilisation dans une collection
$collection = new SuggestionResultRecordCollection();
$collection->add($record);

// Filtrage et regroupement
$best = $collection->getBestMatch();
$byField = $collection->groupByField();

if ($best !== null) {
    echo "Meilleur résultat : " . $best->original_text . "\n";
}
```

## Voir aussi

- `SuggestionResultRecordCollection` - Collection typée pour les résultats de suggestion
- `CompletionResultRecord` - Enregistrement pour les résultats de complétion
- `HermesService::suggest()` - Service de suggestion
- `AbstractRecord` - Classe parente fournissant les opérations de base sur les enregistrements