# HermesService - Référence Technique

## Description

Service principal orchestrant les opérations de complétion, suggestion et recherche textuelle en utilisant le repository Hermes et le calculateur de similarité.

## Hiérarchie / Implémentations

```
HermesInterface
    └── HermesService (final)
```

## Rôle principal

Agit comme une façade orchestrant trois opérations principales :

- **COMPLETION** : Complète un texte partiel avec des tokens existants
- **SUGGESTION** : Propose des mots alternatifs basés sur la similarité
- **SEARCH** : Recherche complète retournant des documents entiers

## API / Méthodes publiques

### `complete(CompletionRequestRecord $request): CompletionResultRecordCollection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$request` | `CompletionRequestRecord` | Requête de complétion avec query, limite et contextes |

**Retourne :** `CompletionResultRecordCollection` - Collection de résultats de complétion

**Exemple :**
```php
$request = CompletionRequestRecord::from([
    'query' => new SearchQueryVO('joh=name'),
    'limit' => 10
]);

$results = $hermes->complete($request);
// Retourne les mots commençant par "joh" dans le champ "name"
```

---

### `suggest(SuggestionRequestRecord $request): SuggestionResultRecordCollection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$request` | `SuggestionRequestRecord` | Requête de suggestion avec query, limite, seuil de similarité et contextes |

**Retourne :** `SuggestionResultRecordCollection` - Collection de suggestions

**Exemple :**
```php
$request = SuggestionRequestRecord::from([
    'query' => new SearchQueryVO('devloper=description'),
    'min_similarity' => 0.3,
    'limit' => 5
]);

$results = $hermes->suggest($request);
// Retourne "developer", "development" etc. avec leurs scores
```

---

### `search(SearchRequestRecord $request): SearchResultRecordCollection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$request` | `SearchRequestRecord` | Requête de recherche avec query, limite, seuil de similarité et contextes |

**Retourne :** `SearchResultRecordCollection` - Collection de documents complets

**Exemple :**
```php
$request = SearchRequestRecord::from([
    'query' => new SearchQueryVO('john=name,email|developer=description'),
    'min_similarity' => 0.3,
    'limit' => 20
]);

$results = $hermes->search($request);
// Retourne les documents contenant "john" dans name/email ET "developer" dans description
```

## Détails de l'algorithme

### 1. COMPLETION

1. Extrait les n-grammes de la requête
2. Pour chaque n-gramme, génère les n-grammes lexicaux et métaphoniques
3. Recherche les tokens correspondants via le repository
4. Calcule la similarité entre le n-gramme original et le token
5. Regroupe les résultats par document
6. Calcule la similarité moyenne
7. Trie par similarité décroissante
8. Limite au nombre demandé

### 2. SUGGESTION

1. Extrait les n-grammes de la requête
2. Pour chaque n-gramme, génère les n-grammes lexicaux et métaphoniques
3. Recherche les tokens candidats avec une limite plus large (`limit * 10`)
4. Calcule la similarité avancée via `SimilarityCalculatorService`
5. Filtre les scores inférieurs au seuil `min_similarity`
6. Regroupe les résultats par document
7. Calcule la similarité moyenne
8. Trie par similarité décroissante
9. Limite au nombre demandé

### 3. SEARCH

1. Extrait les n-grammes de la requête
2. Pour chaque n-gramme, génère les n-grammes lexicaux et métaphoniques
3. Récupère les tokens groupés par document via le repository
4. Calcule la similarité pour chaque match
5. Filtre les scores inférieurs au seuil
6. Aggrège les matches par document
7. Calcule la similarité moyenne du document
8. Trie par similarité décroissante
9. Limite au nombre demandé

## Cas d'utilisation

### Cas 1 : Autocomplétion simple

```php
// L'utilisateur tape "joh"
$request = CompletionRequestRecord::from([
    'query' => new SearchQueryVO('joh=name,email'),
    'limit' => 10
]);

$results = $hermes->complete($request);
// Suggestions: "John", "Johanna", "Johnson"
```

### Cas 2 : Correction de fautes

```php
// L'utilisateur a écrit "devloper"
$request = SuggestionRequestRecord::from([
    'query' => new SearchQueryVO('devloper=skills'),
    'min_similarity' => 0.3,
    'limit' => 5
]);

$results = $hermes->suggest($request);
// Suggestions: "developer" (0.92), "development" (0.78), "deploy" (0.65)
```

### Cas 3 : Recherche avancée

```php
// Recherche multi-critères avec contexte
$contexts = new ContextFilterVOCollection();
$contexts->add(new ContextFilterVO('App.Models.User'));

$request = SearchRequestRecord::from([
    'query' => new SearchQueryVO('john=name,email|developer=description'),
    'contexts' => $contexts,
    'min_similarity' => 0.3,
    'limit' => 20
]);

$results = $hermes->search($request);
// Documents User contenant "john" ET "developer"
```

### Cas 4 : Recherche avec cluster

```php
$contexts = new ContextFilterVOCollection();
$contexts->add(new ContextFilterVO(null, 'tenant:company_abc'));

$request = SearchRequestRecord::from([
    'query' => new SearchQueryVO('john=name'),
    'contexts' => $contexts,
    'limit' => 10
]);

$results = $hermes->search($request);
// Documents du tenant company_abc contenant "john"
```

## Gestion des erreurs

| Situation | Comportement |
|-----------|--------------|
| Requête vide | Retourne une collection vide |
| Aucun n-gramme généré | Passe au n-gramme suivant |
| Aucun token trouvé | Passe au n-gramme suivant |
| Score inférieur au seuil | Token ignoré (suggestion/search) |

## Performance

| Opération | Multiplicateur | Notes |
|-----------|----------------|-------|
| COMPLETION | `limit * 2` | Récupère 2x plus de tokens pour meilleurs résultats |
| SUGGESTION | `limit * 10` | Récupère 10x plus de candidats pour meilleures suggestions |

**Temps typique :** Dépend du nombre de n-grammes et de tokens

**Optimisations :**
- Utilisation de `distinct()` pour éviter les doublons
- Regroupement par document pour éviter les calculs redondants
- Calcul de similarité uniquement sur les tokens candidats

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Complet |
| Laravel 10.x+ | ✅ Complet |
| Laravel Indexer 0.6.1+ | ✅ Requis |

## Dépendances

- `HermesRepositoryInterface` - Accès aux tokens
- `TextNormalizerInterface` - Normalisation des textes
- `NGramGeneratorInterface` - Génération des n-grammes
- `SimilarityCalculatorService` - Calcul de similarité
- `SimilarityConfig` - Configuration des paramètres

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelHermes\Services\HermesService;
use AndyDefer\LaravelHermes\Records\CompletionRequestRecord;
use AndyDefer\LaravelHermes\Records\SuggestionRequestRecord;
use AndyDefer\LaravelHermes\Records\SearchRequestRecord;
use AndyDefer\LaravelHermes\Collections\ContextFilterVOCollection;
use AndyDefer\LaravelHermes\ValueObjects\ContextFilterVO;
use AndyDefer\LaravelIndexer\ValueObjects\SearchQueryVO;

$hermes = app(HermesService::class);

// 1. Autocomplétion
$completionRequest = CompletionRequestRecord::from([
    'query' => new SearchQueryVO('joh=name'),
    'limit' => 10
]);

$completions = $hermes->complete($completionRequest);
foreach ($completions as $result) {
    echo $result->original_text . "\n";
}

// 2. Suggestion
$suggestionRequest = SuggestionRequestRecord::from([
    'query' => new SearchQueryVO('devloper=skills'),
    'min_similarity' => 0.3,
    'limit' => 5
]);

$suggestions = $hermes->suggest($suggestionRequest);
foreach ($suggestions as $result) {
    echo $result->original_text . " (" . round($result->similarity, 2) . ")\n";
}

// 3. Recherche
$contexts = new ContextFilterVOCollection();
$contexts->add(new ContextFilterVO('App.Models.User'));

$searchRequest = SearchRequestRecord::from([
    'query' => new SearchQueryVO('john=name|developer=description'),
    'contexts' => $contexts,
    'min_similarity' => 0.3,
    'limit' => 20
]);

$results = $hermes->search($searchRequest);
foreach ($results as $result) {
    echo "Document: " . $result->fingerprint . "\n";
    echo "Score: " . round($result->similarity, 2) . "\n";
    foreach ($result->matches as $match) {
        echo "  Match: " . $match->field . " = " . $match->original_text . "\n";
    }
}
```

## Voir aussi

- `HermesInterface` - Interface du service
- `HermesRepositoryInterface` - Interface du repository
- `SimilarityCalculatorService` - Service de calcul de similarité
- `CompletionRequestRecord` - Record de requête de complétion
- `SuggestionRequestRecord` - Record de requête de suggestion
- `SearchRequestRecord` - Record de requête de recherche
- `Laravel Hermes - Documentation` - Documentation générale du package