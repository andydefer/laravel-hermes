# HermesService - Référence Technique

## Description

Service principal orchestrant les opérations de recherche intelligente pour Laravel Hermes. Gère l'autocomplétion, la correction orthographique et la recherche textuelle avec filtres avancés.

## Hiérarchie / Implémentations

```
HermesInterface
    └── HermesService (final)
```

## Rôle principal

`HermesService` agit comme une façade orchestrant trois opérations principales :

- **COMPLETION** : Complète un texte partiel avec des tokens existants
- **SUGGESTION** : Propose des mots alternatifs basés sur la similarité
- **SEARCH** : Recherche complète retournant des documents entiers avec leurs métadonnées

## DETAILS

[Voir la classe HermesService](https://github.com/andydefer/laravel-hermes/blob/main/src/Services/HermesService.php)

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
$clusters = new ClusterVOCollection();
$clusters->add(new ClusterVO('tenant:company_abc@AND'));

$request = SearchRequestRecord::from([
    'query' => new SearchQueryVO('john=name,email|developer=description'),
    'contexts' => new ContextFilterVOCollection()->add(
        new ContextFilterVO('App.Models.User', $clusters, 'AND')
    ),
    'min_similarity' => 0.3,
    'limit' => 20
]);

$results = $hermes->search($request);
// Retourne les documents contenant "john" dans name/email ET "developer" dans description
```

---

## Méthodes internes (privées)

### `generateTermNgrams(string $term): array`

Génère les n-grammes lexicaux et métaphoniques à partir d'un terme.

- Normalisation du texte
- Génération des n-grammes lexicaux
- Génération du métaphone et de ses n-grammes
- Fusion et dédoublonnage

**Retourne :** `array<string>` - N-grammes uniques

---

### `createStringCollection(array $fields): ?StringTypedCollection`

Crée une collection typée à partir d'un tableau de noms de champs.

**Retourne :** `?StringTypedCollection` - Collection ou `null` si vide

---

### `calculateSimilarity(string $query, string $token): float`

Calcule la similarité entre une requête et un token.

- Normalisation des deux textes
- Délégation au `SimilarityCalculatorService`

**Retourne :** `float` - Score entre 0.0 et 1.0

---

### `buildCompletionResultCollection(array $allResults): CompletionResultRecordCollection`

Construit la collection de résultats de complétion.

- Calcule la similarité moyenne par document
- Crée les records de résultats

---

### `buildSuggestionResultCollection(array $allResults): SuggestionResultRecordCollection`

Construit la collection de résultats de suggestion.

- Calcule la similarité moyenne par document
- Crée les records de résultats

---

### `buildSearchResultCollection(array $documents): SearchResultRecordCollection`

Construit la collection de résultats de recherche.

- Filtre les documents sans similarités
- Calcule la similarité moyenne par document
- Ajoute les matches et les métadonnées

---

### `sortBySimilarityDescending(array $items): array`

Trie les résultats par similarité décroissante.

---

### `sliceCollection(array $items, int $limit, string $collectionClass): object`

Tranche une collection et crée une nouvelle instance de la classe cible.

---

## Cas d'utilisation

### Cas 1 : Autocomplétion simple

```php
$request = CompletionRequestRecord::from([
    'query' => new SearchQueryVO('joh=name,email'),
    'limit' => 10
]);

$results = $hermes->complete($request);
// Suggestions: "John", "Johanna", "Johnson"
```

### Cas 2 : Correction de fautes

```php
$request = SuggestionRequestRecord::from([
    'query' => new SearchQueryVO('devloper=skills'),
    'min_similarity' => 0.3,
    'limit' => 5
]);

$results = $hermes->suggest($request);
// Suggestions: "developer" (0.92), "development" (0.78), "deploy" (0.65)
```

### Cas 3 : Recherche avancée avec clusters

```php
$clusters = new ClusterVOCollection();
$clusters->add(new ClusterVO('tenant:company_abc@AND'));
$clusters->add(new ClusterVO('env:production@AND'));

$contexts = new ContextFilterVOCollection();
$contexts->add(new ContextFilterVO(
    'App.Models.User',
    $clusters,
    'AND'
));

$request = SearchRequestRecord::from([
    'query' => new SearchQueryVO('john=name,email|developer=description'),
    'contexts' => $contexts,
    'min_similarity' => 0.3,
    'limit' => 20
]);

$results = $hermes->search($request);
// Documents User tenant company_abc en production contenant "john" ET "developer"
```

### Cas 4 : Recherche multi-contextes (OR)

```php
$clustersUser = new ClusterVOCollection();
$clustersUser->add(new ClusterVO('tenant:company_abc@AND'));

$clustersProduct = new ClusterVOCollection();
$clustersProduct->add(new ClusterVO('tenant:company_xyz@AND'));

$contexts = new ContextFilterVOCollection();
$contexts->add(new ContextFilterVO(
    'App.Models.User',
    $clustersUser,
    'AND'
));
$contexts->add(new ContextFilterVO(
    'App.Models.Product',
    $clustersProduct,
    'AND'
));

$request = SearchRequestRecord::from([
    'query' => new SearchQueryVO('john=name|product=name'),
    'contexts' => $contexts,
    'limit' => 20
]);

$results = $hermes->search($request);
// Users de company_abc OU Products de company_xyz
```

---

## Flux d'exécution

```
1. Réception de la requête
2. Extraction des n-grammes de la requête
3. Pour chaque n-gramme:
   a. Génération des n-grammes lexicaux et métaphoniques
   b. Recherche des tokens via HermesRepository
   c. Calcul des similarités
   d. Agrégation des résultats par document
4. Construction de la collection de résultats
5. Tri par similarité décroissante
6. Limitation du nombre de résultats
7. Retour de la collection
```

## Performance

| Opération | Multiplicateur | Notes |
|-----------|----------------|-------|
| COMPLETION | `limit * 2` | Récupère 2x plus de tokens pour meilleurs résultats |
| SUGGESTION | `limit * 10` | Récupère 10x plus de candidats pour meilleures suggestions |
| SEARCH | `limit * 1` | Récupère exactement le nombre demandé |

**Optimisations :**
- Utilisation de `distinct()` pour éviter les doublons
- Regroupement par document pour éviter les calculs redondants
- Calcul de similarité uniquement sur les tokens candidats
- Filtrage par seuil de similarité configurable

---

## Gestion des erreurs

| Situation | Comportement |
|-----------|--------------|
| Aucun n-gramme généré | Retourne une collection vide |
| Aucun token trouvé | Passe au n-gramme suivant |
| Score inférieur au seuil | Token ignoré (suggestion/search) |
| Contexte sans namespace ni clusters | Exception levée par `ContextFilterVO` |

---

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.2+ | ✅ Complet |
| Laravel 10.x+ | ✅ Complet |
| Laravel Indexer 0.21.1+ | ✅ Requis |

## Dépendances

- `HermesRepositoryInterface` - Accès aux tokens
- `TextNormalizerInterface` - Normalisation des textes
- `NGramGeneratorInterface` - Génération des n-grammes
- `SimilarityCalculatorService` - Calcul de similarité
- `SimilarityConfig` - Configuration des paramètres

---

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
use AndyDefer\LaravelIndexer\Collections\ClusterVOCollection;
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;
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

// 3. Recherche avec clusters
$clusters = new ClusterVOCollection();
$clusters->add(new ClusterVO('tenant:company_abc@AND'));
$clusters->add(new ClusterVO('env:production@AND'));

$contexts = new ContextFilterVOCollection();
$contexts->add(new ContextFilterVO(
    'App.Models.User',
    $clusters,
    'AND'
));

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
- `ContextFilterVO` - Filtre de contexte (namespace + clusters)
- `Laravel Hermes - Documentation` - Documentation générale du package