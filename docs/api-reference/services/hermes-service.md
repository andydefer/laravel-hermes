# HermesService - Référence Technique

## Description

Service principal implémentant les opérations de recherche, complétion et suggestion pour le package Hermes.

## Hiérarchie

```
HermesInterface
    └── HermesService
```

## Rôle principal

Orchestre les opérations de recherche textuelle en coordonnant le repository, le générateur de n-grammes, le normaliseur de texte et le calculateur de similarité. Gère la récupération des tokens, le calcul des scores de similarité, l'agrégation des résultats et leur tri par pertinence.

## API / Méthodes publiques

### `complete(CompletionRequestRecord $request): CompletionResultRecordCollection`

Effectue une opération de complétion pour suggérer des tokens correspondant à la requête.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$request` | `CompletionRequestRecord` | Requête de complétion contenant la query et ses paramètres |

**Retourne :** `CompletionResultRecordCollection` - Collection des résultats de complétion triés par similarité décroissante

**Exemple :**
```php
<?php

$request = CompletionRequestRecord::from([
    'query' => new SearchQueryVO('john=name'),
    'limit' => 10,
    'contexts' => $contexts,
]);

$results = $hermes->complete($request);

foreach ($results as $result) {
    echo $result->original_text . ' (similarité: ' . $result->similarity . ")\n";
}
```

---

### `suggest(SuggestionRequestRecord $request): SuggestionResultRecordCollection`

Effectue une opération de suggestion pour proposer des mots ou expressions.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$request` | `SuggestionRequestRecord` | Requête de suggestion contenant la query et ses paramètres |

**Retourne :** `SuggestionResultRecordCollection` - Collection des suggestions triées par similarité décroissante

**Exemple :**
```php
<?php

$request = SuggestionRequestRecord::from([
    'query' => new SearchQueryVO('dev=description'),
    'limit' => 5,
    'minSimilarity' => 0.3,
]);

$suggestions = $hermes->suggest($request);
```

---

### `search(SearchRequestRecord $request): SearchResultRecordCollection`

Effectue une recherche complète retournant les documents correspondants.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$request` | `SearchRequestRecord` | Requête de recherche contenant la query et ses paramètres |

**Retourne :** `SearchResultRecordCollection` - Collection des résultats de recherche triés par similarité décroissante

**Exemple :**
```php
<?php

$request = SearchRequestRecord::from([
    'query' => new SearchQueryVO('john=name|developer=description'),
    'limit' => 20,
    'minSimilarity' => 0.3,
    'contexts' => $contexts,
]);

$results = $hermes->search($request);
```

## Cas d'utilisation

### Cas 1 : Complétion de texte en temps réel

**Problème :** Une interface de recherche doit suggérer des complétions pendant que l'utilisateur tape.

```php
<?php

use AndyDefer\LaravelHermes\Records\CompletionRequestRecord;
use AndyDefer\LaravelIndexer\ValueObjects\SearchQueryVO;

$request = CompletionRequestRecord::from([
    'query' => new SearchQueryVO('joh=name'),
    'limit' => 5,
    'contexts' => $contexts,
]);

$results = $hermes->complete($request);

foreach ($results as $result) {
    echo "Suggestion : " . $result->original_text . "\n";
    echo "Champ : " . $result->field . "\n";
    echo "Score : " . $result->similarity . "\n";
}
```

### Cas 2 : Recherche avec multiples champs

**Problème :** On cherche des utilisateurs par nom et par email simultanément.

```php
<?php

$request = SearchRequestRecord::from([
    'query' => new SearchQueryVO('john=name|john.doe=email'),
    'limit' => 20,
    'minSimilarity' => 0.4,
]);

$results = $hermes->search($request);

foreach ($results as $result) {
    $data = $result->data->toArray();
    echo "Utilisateur : " . $data['name'] . "\n";
    echo "Email : " . $data['email'] . "\n";
    echo "Score : " . $result->similarity . "\n";
    
    foreach ($result->matches as $match) {
        echo "  - Match : " . $match->original_text . " (" . $match->field . ")\n";
    }
}
```

### Cas 3 : Suggestion avec filtres contextuels

**Problème :** On veut suggérer des termes uniquement pour les utilisateurs actifs d'une entreprise.

```php
<?php

use AndyDefer\Repository\ValueObjects\ClusterQueries;

$contexts = new ContextFilterVOCollection();
$contexts->add(new ContextFilterVO(
    'App\\Models\\User',
    new ClusterQueries(['cluster' => 'tenant=company_abc & status=active'])
));

$request = SuggestionRequestRecord::from([
    'query' => new SearchQueryVO('dev=description'),
    'limit' => 10,
    'minSimilarity' => 0.3,
    'contexts' => $contexts,
]);

$suggestions = $hermes->suggest($request);
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Query vide | Aucune exception | Retourne une collection vide |
| Repository indisponible | `RuntimeException` | Message d'erreur du repository |
| Normalisation échoue | `InvalidArgumentException` | Message d'erreur de normalisation |

## Performance

- **Génération de n-grammes** : O(n * m) - n = longueur du terme, m = taille des n-grammes
- **Recherche de tokens** : O(k * log n) - k = nombre de n-grammes, n = tokens indexés
- **Calcul de similarité** : O(p) - p = longueur moyenne des textes
- **Tri des résultats** : O(n * log n) - n = nombre de résultats
- **Mémoire** : Les collections et les résultats intermédiaires peuvent consommer de la mémoire pour les grandes requêtes

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

use AndyDefer\LaravelHermes\Services\HermesService;
use AndyDefer\LaravelHermes\Records\SearchRequestRecord;
use AndyDefer\LaravelHermes\Records\CompletionRequestRecord;
use AndyDefer\LaravelHermes\Records\SuggestionRequestRecord;
use AndyDefer\LaravelHermes\Collections\ContextFilterVOCollection;
use AndyDefer\LaravelHermes\ValueObjects\ContextFilterVO;
use AndyDefer\LaravelIndexer\ValueObjects\SearchQueryVO;

// Injection du service (via le conteneur Laravel)
$hermes = app(HermesService::class);

// 1. COMPLÉTION
$completionRequest = CompletionRequestRecord::from([
    'query' => new SearchQueryVO('john=name'),
    'limit' => 5,
]);

$completions = $hermes->complete($completionRequest);

echo "Complétions :\n";
foreach ($completions as $result) {
    echo "  - " . $result->original_text . " (score: " . $result->similarity . ")\n";
}

// 2. SUGGESTION
$suggestionRequest = SuggestionRequestRecord::from([
    'query' => new SearchQueryVO('dev=description'),
    'limit' => 10,
    'minSimilarity' => 0.3,
]);

$suggestions = $hermes->suggest($suggestionRequest);

echo "\nSuggestions :\n";
foreach ($suggestions as $result) {
    echo "  - " . $result->original_text . " (score: " . $result->similarity . ")\n";
}

// 3. RECHERCHE
$searchRequest = SearchRequestRecord::from([
    'query' => new SearchQueryVO('john=name|developer=description'),
    'limit' => 20,
    'minSimilarity' => 0.3,
]);

$results = $hermes->search($searchRequest);

echo "\nRésultats de recherche :\n";
foreach ($results as $result) {
    $data = $result->data->toArray();
    echo "Document : " . ($data['name'] ?? 'Sans nom') . "\n";
    echo "  Score : " . $result->similarity . "\n";
    echo "  Matches : " . $result->matches->count() . "\n";
}
```

## Voir aussi

- `HermesInterface` - Contrat du service
- `HermesRepositoryInterface` - Repository pour les opérations de données
- `SimilarityCalculatorService` - Calcul des scores de similarité
- `NGramGeneratorInterface` - Génération des n-grammes
- `TextNormalizerInterface` - Normalisation du texte