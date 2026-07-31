# SearchResultVO - Référence Technique

## Description

Objet de valeur représentant un résultat de recherche enrichi.

## Hiérarchie

```
AbstractValueObject
    └── SearchResultVO
```

**Interfaces héritées :** `JsonSerializable`

## Rôle principal

Enrichit un `SearchResultRecord` avec des données calculées supplémentaires : les meilleures correspondances par champ, un score de similarité arrondi, et l'accès direct aux données hydratées. Facilite l'affichage et la manipulation des résultats de recherche dans les couches présentation.

## API / Propriétés publiques

### `__construct(SearchResultRecord $record, AbstractData $data)`

Initialise l'objet de valeur avec un enregistrement de recherche et ses données hydratées.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$record` | `SearchResultRecord` | Enregistrement de recherche source |
| `$data` | `AbstractData` | Données hydratées de l'entité |

**Exemple :**
```php
<?php

$vo = new SearchResultVO(
    record: $searchResult,
    data: $userData
);
```

---

### `getFingerprint(): string`

Retourne le fingerprint du résultat de recherche.

**Retourne :** `string` - Le fingerprint (ex: 'App.Models.User|1')

**Exemple :**
```php
<?php

$fingerprint = $vo->getFingerprint();
// 'App.Models.User|1'
```

---

### `getValue(): StrictAssociative`

Retourne l'objet de valeur sous forme de tableau associatif strict.

**Retourne :** `StrictAssociative<string, mixed>` - Structure :

```php
[
    'data' => AbstractData,
    'similarity' => float,
    'matches' => [
        ['field' => 'name', 'original_text' => 'John', 'similarity' => 0.95],
        // ...
    ],
    'fingerprint' => string
]
```

**Exemple :**
```php
<?php

$value = $vo->getValue();
echo "Similarité : " . $value['similarity'] . "\n";
echo "Fingerprint : " . $value['fingerprint'] . "\n";

foreach ($value['matches'] as $match) {
    echo "Match : " . $match['original_text'] . " (" . $match['field'] . ")\n";
}
```

---

### `toArray(): array`

Convertit l'objet de valeur en tableau PHP simple.

**Retourne :** `array<string, mixed>` - Représentation en tableau

**Exemple :**
```php
<?php

$array = $vo->toArray();
// ['data' => [...], 'similarity' => 0.95, 'matches' => [...], 'fingerprint' => '...']
```

---

### `getSimilarity(): float`

Retourne le score de similarité.

**Retourne :** `float` - Score arrondi à 2 décimales

**Exemple :**
```php
<?php

$score = $vo->getSimilarity(); // 0.95
```

---

### `getBestMatches(): array`

Retourne les meilleures correspondances par champ.

**Retourne :** `array<int, array{field: string, original_text: string, similarity: float}>` - Meilleures correspondances

**Exemple :**
```php
<?php

$bestMatches = $vo->getBestMatches();

foreach ($bestMatches as $match) {
    echo "Champ : " . $match['field'] . "\n";
    echo "Texte : " . $match['original_text'] . "\n";
    echo "Score : " . $match['similarity'] . "\n";
}
```

---

### `getData(): AbstractData`

Retourne l'objet de données hydraté.

**Retourne :** `AbstractData` - L'objet de données

**Exemple :**
```php
<?php

$data = $vo->getData();
echo "Nom : " . $data->name . "\n";
echo "Email : " . $data->email . "\n";
```

## Cas d'utilisation

### Cas 1 : Affichage des résultats de recherche

**Problème :** On souhaite afficher les résultats de recherche avec leurs correspondances.

```php
<?php

$vo = new SearchResultVO($record, $userData);

$value = $vo->getValue();

echo "Résultat : " . $value['data']->name . "\n";
echo "Score global : " . $value['similarity'] . "\n";
echo "Fingerprint : " . $value['fingerprint'] . "\n";
echo "Correspondances :\n";

foreach ($value['matches'] as $match) {
    echo "  - " . $match['original_text'] . " (" . $match['field'] . ")\n";
}
```

### Cas 2 : Extraction des meilleures correspondances pour l'UI

**Problème :** On veut afficher les meilleures correspondances pour chaque champ.

```php
<?php

$vo = new SearchResultVO($record, $userData);
$bestMatches = $vo->getBestMatches();

foreach ($bestMatches as $match) {
    echo "Meilleure correspondance pour '{$match['field']}': " . $match['original_text'] . "\n";
}
```

### Cas 3 : Utilisation dans une collection

**Problème :** On manipule une collection de VO pour l'affichage.

```php
<?php

use AndyDefer\LaravelHermes\Collections\SearchResultVOCollection;

$vos = new SearchResultVOCollection();

foreach ($results as $result) {
    $data = $this->hydrateData($result);
    $vos->add(new SearchResultVO($result, $data));
}

$filtered = $vos->filterByMinSimilarity(0.8);
$best = $filtered->getBestMatch();

if ($best !== null) {
    echo "Meilleur résultat : " . $best->getData()->name . "\n";
    echo "Score : " . $best->getSimilarity() . "\n";
}
```

### Cas 4 : Sérialisation pour API

**Problème :** On expose les résultats via une API REST.

```php
<?php

$vos = $this->searchService->search($request);

$response = $vos->map(function (SearchResultVO $vo) {
    return [
        'id' => $vo->getFingerprint(),
        'score' => $vo->getSimilarity(),
        'data' => $vo->getData()->toArray(),
        'matches' => $vo->getBestMatches(),
    ];
});

return response()->json($response->toArray());
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Record sans correspondances | Aucune | `bestMatches` sera un tableau vide |
| Record avec similarité non définie | `Error` | Propriété `similarity` doit exister |

## Intégration

Ce Value Object est utilisé par :

- `SearchResultVOCollection` - Collection de VO
- `HermesService::search()` - Transformation des résultats
- Interfaces utilisateur - Présentation des résultats
- APIs - Sérialisation des résultats

## Performance

- **Construction** : O(n) - n = nombre de correspondances dans le record
- **Méthodes getters** : O(1) - accès direct aux propriétés
- **Mémoire** : Stocke les meilleures correspondances (généralement une par champ)

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelHermes\ValueObjects\SearchResultVO;
use AndyDefer\LaravelHermes\Records\SearchResultRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractData;
use AndyDefer\LaravelHermes\Collections\SearchResultVOCollection;

// Création d'un record (simulé)
$record = new SearchResultRecord(
    document_id: 'doc_123',
    fingerprint: 'App.Models.User|42',
    data: StrictAssociative::from([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'role' => 'admin'
    ]),
    matches: $matchCollection,
    similarity: 0.95
);

// Création du VO avec données hydratées
// Supposons que UserData est une classe étendant AbstractData
$userData = UserData::from([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'role' => 'admin'
]);

$vo = new SearchResultVO($record, $userData);

// Accès aux données via getters
echo "Fingerprint : " . $vo->getFingerprint() . "\n";
echo "Similarité : " . $vo->getSimilarity() . "\n";
echo "Nom : " . $vo->getData()->name . "\n";

// Accès via getValue()
$value = $vo->getValue();
echo "\nValeur structurée :\n";
echo "- Similarité : " . $value['similarity'] . "\n";
echo "- Fingerprint : " . $value['fingerprint'] . "\n";

if (!empty($value['matches'])) {
    echo "- Meilleures correspondances :\n";
    foreach ($value['matches'] as $match) {
        echo "  * " . $match['field'] . " : " . $match['original_text'] . " (" . $match['similarity'] . ")\n";
    }
}

// Meilleures correspondances
$bestMatches = $vo->getBestMatches();
echo "\nMeilleures correspondances :\n";
foreach ($bestMatches as $match) {
    echo "- " . $match['field'] . " : " . $match['original_text'] . "\n";
}

// Utilisation dans une collection
$collection = new SearchResultVOCollection();
$collection->add($vo);

if ($collection->isNotEmpty()) {
    $best = $collection->getBestMatch();
    echo "\nMeilleur VO : " . $best->getData()->name . " (" . $best->getSimilarity() . ")\n";
}

// Sérialisation
$array = $vo->toArray();
echo "\nSérialisation :\n";
print_r($array);
```

## Voir aussi

- `SearchResultRecord` - Enregistrement source
- `SearchResultVOCollection` - Collection de VO
- `AbstractValueObject` - Classe parente des objets de valeur
- `AbstractData` - Classe parente des données hydratées