# Laravel Hermes

## Table des matières

- [Installation](#installation)
- [Concepts fondamentaux](#concepts-fondamentaux)
- [Completion](#completion)
- [Suggestion](#suggestion)
- [Search](#search)
- [Requêtes multiples (AND)](#requêtes-multiples-and)
- [Les clusters](#les-clusters)
- [Les contextes](#les-contextes)
- [Contextes multiples](#contextes-multiples)
- [Repositories](#repositories)
- [Collections](#collections)
- [Exemple complet : API de recherche](#exemple-complet--api-de-recherche)

---

## Installation

```bash
composer require andydefer/laravel-hermes
```

### Migrations

```bash
php artisan vendor:publish --tag=hermes-migrations
php artisan migrate
```

### Configuration

```bash
php artisan vendor:publish --tag=hermes-config
```

```php
// config/hermes.php
return [
    'similarity' => [
        'gram_min_size' => 2,
        'gram_max_size' => 4,
        'vector_dimension' => 128,
        'textual_weight' => 0.6,
        'phonetic_weight' => 0.4,
        'letter_bonus' => 0.05,
        'bigram_bonus' => 0.03,
        'min_word_length' => 2,
        'max_words' => 50,
        'max_pairs' => 2500,
        'timeout_seconds' => 0.5,
        'levenshtein' => [
            'metaphone_threshold' => 3,
            'metaphone_bonus' => 0.175,
            'lexical_threshold' => 3,
            'lexical_bonus_medium' => 0.225,
            'lexical_bonus_high' => 0.275,
            'max_bonus' => 0.45,
        ],
    ],
];
```

---

## Concepts fondamentaux

Laravel Hermes est un package de **recherche intelligente** qui s'appuie sur **Laravel Indexer** pour offrir trois services :

| Service | Description |
|---------|-------------|
| **COMPLETION** | Complète un bout de mot avec des mots existants |
| **SUGGESTION** | Corrige les fautes de frappe |
| **SEARCH** | Recherche textuelle avec résultats détaillés |

### Architecture

```
Laravel Hermes
    ├── HermesService (orchestrateur)
    │   ├── complete()
    │   ├── suggest()
    │   └── search()
    ├── HermesRepository (accès aux tokens)
    ├── SimilarityCalculatorService (calcul de similarité)
    └── Records & Collections (DTOs typés)
```

### Prérequis

Ce package nécessite **Laravel Indexer** pour l'indexation des données.

```php
// Votre modèle doit implémenter Indexable
use AndyDefer\LaravelIndexer\Contracts\Indexable;

class User extends Model implements Indexable
{
    public function shouldBeIndexed(): bool
    {
        return $this->is_active;
    }

    public function getIndexableData(): StrictAssociative
    {
        return StrictAssociative::from([
            'name' => $this->name,
            'email' => $this->email,
            'bio' => $this->bio,
            'skills' => $this->skills,
        ]);
    }

    public function getKey(): int|string
    {
        return $this->id;
    }

    public function getMorphClass(): string
    {
        return self::class;
    }
}
```

---

## Completion

**Objectif :** L'utilisateur tape un bout de mot, on propose des mots complets triés par similarité.

### Exemple

```
Base contient : "john", "johanna", "johnson", "johny", "joshua"
User tape : "joh"
→ Résultat trié par similarité :
   1. "joh" → "john" (similarité 1.0)
   2. "joh" → "johanna" (similarité 0.83)
   3. "joh" → "johnson" (similarité 0.80)
```

### Utilisation

```php
use AndyDefer\LaravelHermes\Contracts\Services\HermesInterface;
use AndyDefer\LaravelHermes\Records\CompletionRequestRecord;
use AndyDefer\LaravelIndexer\ValueObjects\SearchQueryVO;

class UserController
{
    public function __construct(
        private HermesInterface $hermes
    ) {}

    public function autocomplete(Request $request)
    {
        $request = CompletionRequestRecord::from([
            'query' => new SearchQueryVO($request->get('q') . '=name,email'),
            'limit' => 10,
        ]);

        $results = $this->hermes->complete($request);

        return response()->json([
            'suggestions' => $results->getOriginalTexts()
        ]);
    }
}
```

### Avec filtres de contexte

```php
use AndyDefer\LaravelHermes\Collections\ContextFilterVOCollection;
use AndyDefer\LaravelHermes\ValueObjects\ContextFilterVO;
use AndyDefer\LaravelIndexer\Collections\ClusterVOCollection;
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;

$clusters = new ClusterVOCollection();
$clusters->add(new ClusterVO('tenant:company_abc@AND'));

$contexts = new ContextFilterVOCollection();
$contexts->add(new ContextFilterVO('App.Models.User', $clusters, 'AND'));

$request = CompletionRequestRecord::from([
    'query' => 'joh=name',
    'limit' => 10,
    'contexts' => $contexts,
]);

$results = $this->hermes->complete($request);
```

---

## Suggestion

**Objectif :** L'utilisateur a fait une faute de frappe, on propose les mots les plus proches.

### Exemple

```
Base contient : "developer", "development", "deploy", "devops"
User tape : "devloper" (faute)
→ Résultat trié par similarité :
   1. "devloper" → "developer" (similarité 0.92)
   2. "devloper" → "development" (similarité 0.78)
   3. "devloper" → "deploy" (similarité 0.65)
```

### Utilisation

```php
use AndyDefer\LaravelHermes\Records\SuggestionRequestRecord;

$request = SuggestionRequestRecord::from([
    'query' => 'devloper=skills,bio',
    'limit' => 5,
    'min_similarity' => 0.3,
]);

$results = $this->hermes->suggest($request);

foreach ($results as $result) {
    echo $result->original_text . ' (' . round($result->similarity, 2) . ")\n";
}
// developer (0.92)
// development (0.78)
// deploy (0.65)
```

### Avec seuil de similarité

```php
// Seulement les suggestions très proches (> 70%)
$request = SuggestionRequestRecord::from([
    'query' => 'devloper=skills',
    'min_similarity' => 0.7,
    'limit' => 5,
]);

$results = $this->hermes->suggest($request);
// Résultat : seulement "developer" (0.92)
```

---

## Search

**Objectif :** L'utilisateur cherche, on retourne les documents complets avec le détail des matchs.

### Utilisation

```php
use AndyDefer\LaravelHermes\Records\SearchRequestRecord;
use AndyDefer\LaravelHermes\Collections\ContextFilterVOCollection;
use AndyDefer\LaravelHermes\ValueObjects\ContextFilterVO;
use AndyDefer\LaravelIndexer\Collections\ClusterVOCollection;
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;

$clusters = new ClusterVOCollection();
$clusters->add(new ClusterVO('tenant:company_abc@AND'));

$contexts = new ContextFilterVOCollection();
$contexts->add(new ContextFilterVO('App.Models.User', $clusters, 'AND'));

$request = SearchRequestRecord::from([
    'query' => 'john=name,email|developer=description',
    'contexts' => $contexts,
    'limit' => 20,
    'min_similarity' => 0.3,
]);

$results = $this->hermes->search($request);

foreach ($results as $result) {
    echo "Document: " . $result->fingerprint . "\n";
    echo "Score global: " . round($result->similarity, 2) . "\n";
    
    foreach ($result->matches as $match) {
        echo "  - " . $match->field . ": " . $match->original_text . "\n";
        echo "    Score: " . round($match->similarity, 2) . "\n";
    }
}
```

### Structure du résultat

```php
// SearchResultRecord
[
    'document_id' => 'uuid',
    'fingerprint' => 'App.Models.User|123',
    'data' => StrictAssociative::from([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'description' => 'Software Developer'
    ]),
    'matches' => [
        ['field' => 'name', 'original_text' => 'John', 'similarity' => 1.0],
        ['field' => 'email', 'original_text' => 'john@example.com', 'similarity' => 0.85]
    ],
    'similarity' => 0.95
]
```

---

## Requêtes multiples (AND)

### Format

La requête supporte plusieurs termes séparés par `|` (pipe) :

```
terme1=champ1,champ2|terme2=champ3|terme3=champ1,champ4
```

### Logique

- **Plusieurs termes** = **ET** (intersection)
- **Plusieurs champs** = **OU** (union)

### Exemples de requêtes multiples

#### Deux termes (AND)

```php
// "john" dans name ET "developer" dans description

$request = SearchRequestRecord::from([
    'query' => 'john=name|developer=description',
    'limit' => 20,
]);

$results = $this->hermes->search($request);
// Résultat : documents qui contiennent "john" DANS name ET "developer" DANS description
```

#### Terme avec plusieurs champs (OR)

```php
// "john" dans name OU email
$query = new SearchQueryVO('john=name,email');

$results = $this->hermes->search($request);
// Résultat : documents qui contiennent "john" DANS name OU email
```

#### Deux termes avec plusieurs champs

```php
// "john" dans name OU email ET "developer" dans description OU bio
$query = new SearchQueryVO('john=name,email|developer=description,bio');

$results = $this->hermes->search($request);
// Résultat : (john dans name/email) ET (developer dans description/bio)
```

#### Trois termes

```php
// "john" dans name ET "developer" dans skills ET "laravel" dans framework
$query = new SearchQueryVO('john=name|developer=skills|laravel=framework');

$results = $this->hermes->search($request);
// Résultat : documents qui remplissent les TROIS conditions
```

### Completion avec requêtes multiples

```php
// Completion pour "john" dans name ET "jane" dans email
$request = CompletionRequestRecord::from([
    'query' => new SearchQueryVO('john=name|jane=email'),
    'limit' => 10,
]);

$results = $this->hermes->complete($request);
// Résultat : mots qui correspondent aux DEUX conditions
```

### Suggestion avec requêtes multiples

```php
// Suggestion pour "devloper" dans skills ET "musik" dans categories
$request = SuggestionRequestRecord::from([
    'query' => new SearchQueryVO('devloper=skills|musik=categories'),
    'limit' => 5,
    'min_similarity' => 0.3,
]);

$results = $this->hermes->suggest($request);
// Résultat : suggestions qui correspondent aux DEUX conditions
```

---

## Les clusters

Le cluster est un **filtre contextuel** pour les recherches multi-tenant.

### Format du cluster

```
key1:value1|key2:value2|key3:value3@AND
key1:value1|key2:value2|key3:value3@OR
key1:value1|key2:value2|key3:value3@NOT
```

| Élément | Description |
|---------|-------------|
| `key:value` | Paire clé-valeur (une clé = une valeur) |
| `|` | Séparateur de paires |
| `@AND` / `@OR` / `@NOT` | Mode de recherche (obligatoire pour la recherche) |

**Caractères autorisés :**
- **Clés** : `a-z`, `A-Z`, `0-9`, `_` uniquement
- **Valeurs** : Tous les caractères (libre)

### Créer un cluster

```php
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;

// Stockage (sans mode)
$cluster = new ClusterVO('tenant:company_abc');

// Recherche (avec mode)
$cluster = new ClusterVO('tenant:company_abc@AND');
$cluster = new ClusterVO('tenant:company_abc|env:production@OR');
$cluster = new ClusterVO('status:inactive@NOT');

// Builder fluent
$cluster = ClusterVO::make('type', 'user')
    ->with('role', 'doctor')
    ->withTernary('status', $isActive, 'active', 'inactive');
```

### Lire un cluster

```php
$cluster = new ClusterVO('tenant:company_abc|env:production');

$cluster->get('tenant');  // 'company_abc'
$cluster->get('env');     // 'production'
$cluster->has('tenant');  // true
$cluster->all();          // ['tenant' => 'company_abc', 'env' => 'production']
```

### Manipuler un cluster

```php
$cluster = new ClusterVO('tenant:company_abc');

// Ajouter
$new = $cluster->with('env', 'production');

// Supprimer
$new = $cluster->without('tenant');

// Chaînage
$new = $cluster
    ->with('env', 'production')
    ->with('region', 'europe');
```

### Collection de clusters

```php
use AndyDefer\LaravelIndexer\Collections\ClusterVOCollection;

$clusters = new ClusterVOCollection();
$clusters->add(new ClusterVO('tenant:company_abc@AND'));
$clusters->add(new ClusterVO('env:production@AND'));

// Opérateurs entre clusters
$clusters->applyToQuery($query, 'AND'); // Tous doivent correspondre
$clusters->applyToQuery($query, 'OR');  // Au moins un doit correspondre
$clusters->applyToQuery($query, 'NOT'); // Aucun ne doit correspondre
```

### Utiliser un cluster dans une recherche

```php
$clusters = new ClusterVOCollection();
$clusters->add(new ClusterVO('tenant:company_abc@AND'));

$request = SearchRequestRecord::from([
    'query' => new SearchQueryVO('john=name'),
    'clusters' => $clusters,
    'clustersOperator' => 'AND'
]);

$results = $this->hermes->search($request);
// Résultat : uniquement les documents du tenant company_abc
```

---

## Les contextes

Le contexte est un **filtre combiné** (namespace + clusters) pour les recherches.

### Créer un contexte

```php
use AndyDefer\LaravelHermes\ValueObjects\ContextFilterVO;
use AndyDefer\LaravelIndexer\Collections\ClusterVOCollection;
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;

// Uniquement namespace
$context = new ContextFilterVO('App.Models.User');

// Uniquement clusters
$clusters = new ClusterVOCollection();
$clusters->add(new ClusterVO('tenant:company_abc@AND'));
$context = new ContextFilterVO(null, $clusters, 'AND');

// Les deux (ET)
$context = new ContextFilterVO('App.Models.User', $clusters, 'AND');
```

### Utiliser un contexte

```php
$clusters = new ClusterVOCollection();
$clusters->add(new ClusterVO('tenant:company_abc@AND'));

$contexts = new ContextFilterVOCollection();
$contexts->add(new ContextFilterVO('App.Models.User', $clusters, 'AND'));

$request = SearchRequestRecord::from([
    'query' => new SearchQueryVO('john=name'),
    'contexts' => $contexts,
]);

// Résultat : Users ET tenant company_abc
```

---

## Contextes multiples

### Logique OR entre les contextes

```php
$clusters = new ClusterVOCollection();
$clusters->add(new ClusterVO('tenant:company_abc@AND'));

$contexts = new ContextFilterVOCollection();
$contexts->add(new ContextFilterVO('App.Models.User'));
$contexts->add(new ContextFilterVO(null, $clusters, 'AND'));

$request = SearchRequestRecord::from([
    'query' => new SearchQueryVO('john=name'),
    'contexts' => $contexts,
]);

// Résultat : (Users) OU (documents du tenant company_abc)
```

### Logique AND à l'intérieur d'un contexte

```php
$clusters = new ClusterVOCollection();
$clusters->add(new ClusterVO('tenant:company_abc@AND'));
$clusters->add(new ClusterVO('env:production@AND'));

$contexts = new ContextFilterVOCollection();
$contexts->add(new ContextFilterVO('App.Models.User', $clusters, 'AND'));

// Résultat : Users ET tenant company_abc ET env production
```

### Combinaison complexe de contextes

```php
$clustersUser = new ClusterVOCollection();
$clustersUser->add(new ClusterVO('tenant:company_abc@AND'));

$clustersProduct = new ClusterVOCollection();
$clustersProduct->add(new ClusterVO('tenant:company_xyz@AND'));

$contexts = new ContextFilterVOCollection();
$contexts->add(new ContextFilterVO('App.Models.User', $clustersUser, 'AND'));
$contexts->add(new ContextFilterVO('App.Models.Product', $clustersProduct, 'AND'));

$request = SearchRequestRecord::from([
    'query' => new SearchQueryVO('john=name'),
    'contexts' => $contexts,
]);

// Résultat : Users de company_abc OU Products de company_xyz
```

### Contextes multiples avec requêtes multiples

```php
// (User ET company_abc) OU (Product ET company_xyz)
// ET "john" dans name OU "developer" dans description
$contexts = new ContextFilterVOCollection();
$contexts->add(new ContextFilterVO('App.Models.User', $clustersUser, 'AND'));
$contexts->add(new ContextFilterVO('App.Models.Product', $clustersProduct, 'AND'));

$request = SearchRequestRecord::from([
    'query' => new SearchQueryVO('john=name,email|developer=description'),
    'contexts' => $contexts,
    'limit' => 50,
]);

$results = $this->hermes->search($request);
// Résultat : (User ET company_abc) OU (Product ET company_xyz)
// ET (john dans name/email) ET (developer dans description)
```

---

## Repositories

### HermesRepository

```php
use AndyDefer\LaravelHermes\Repositories\HermesRepository;
use AndyDefer\LaravelHermes\Collections\ContextFilterVOCollection;
use AndyDefer\LaravelHermes\ValueObjects\ContextFilterVO;
use AndyDefer\LaravelIndexer\Collections\ClusterVOCollection;
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;

$repository = app(HermesRepository::class);

// Trouver des tokens par n-grammes
$ngrams = ['joh', 'ohn', 'john'];
$tokens = $repository->findTokensByNgrams($ngrams, limit: 10);

// Avec filtres de contexte
$clusters = new ClusterVOCollection();
$clusters->add(new ClusterVO('tenant:company_abc@AND'));

$contexts = new ContextFilterVOCollection();
$contexts->add(new ContextFilterVO('App.Models.User', $clusters, 'AND'));

$tokens = $repository->findTokensByNgrams(
    $ngrams,
    contexts: $contexts,
    limit: 10
);

// Groupés par document
$grouped = $repository->getTokensGroupedByDocument($ngrams);

// Compter les tokens
$count = $repository->countTokensByNgrams($ngrams);
```

---

## Collections

### CompletionResultRecordCollection

```php
$results = $this->hermes->complete($request);

// Extraction
$tokens = $results->getTokens();
$originalTexts = $results->getOriginalTexts();
$ids = $results->getIds();
$documentIds = $results->getDocumentIds();

// Filtrage
$byField = $results->filterByField('name');
$bySimilarity = $results->filterByMinSimilarity(0.5);
```

### SuggestionResultRecordCollection

```php
$results = $this->hermes->suggest($request);

// Extraction
$tokens = $results->getTokens();
$originalTexts = $results->getOriginalTexts();
$ids = $results->getIds();
$documentIds = $results->getDocumentIds();

// Filtrage
$byField = $results->filterByField('name');
$bySimilarity = $results->filterByMinSimilarity(0.5);
```

### SearchResultRecordCollection

```php
$results = $this->hermes->search($request);

// Extraction
$documentIds = $results->getDocumentIds();
$fingerprints = $results->getFingerprints();
$data = $results->getData();
$matches = $results->getMatches();

// Filtrage
$bySimilarity = $results->filterByMinSimilarity(0.5);
$byField = $results->filterByField('name');
```

### ContextFilterVOCollection

```php
$contexts = new ContextFilterVOCollection();

// Ajout
$contexts->add(new ContextFilterVO('App.Models.User'));

// Extraction
$namespaces = $contexts->getNamespaces();
$clusterCollections = $contexts->getClusterCollections();
$allClusters = $contexts->getAllClusters();

// Filtrage
$byNamespace = $contexts->filterByNamespace('App.Models.User');
$withClusters = $contexts->filterWithClusters();
$withNamespace = $contexts->filterWithNamespace();

// Groupement
$groupedByOperator = $contexts->getClustersGroupedByOperator();
```

---

## Exemple complet : API de recherche

### Contrôleur

```php
<?php

namespace App\Http\Controllers;

use AndyDefer\LaravelHermes\Contracts\Services\HermesInterface;
use AndyDefer\LaravelHermes\Records\CompletionRequestRecord;
use AndyDefer\LaravelHermes\Records\SearchRequestRecord;
use AndyDefer\LaravelHermes\Records\SuggestionRequestRecord;
use AndyDefer\LaravelHermes\Collections\ContextFilterVOCollection;
use AndyDefer\LaravelHermes\ValueObjects\ContextFilterVO;
use AndyDefer\LaravelIndexer\Collections\ClusterVOCollection;
use AndyDefer\LaravelIndexer\ValueObjects\ClusterVO;
use AndyDefer\LaravelIndexer\ValueObjects\SearchQueryVO;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __construct(
        private HermesInterface $hermes
    ) {}

    public function autocomplete(Request $request)
    {
        $request = CompletionRequestRecord::from([
            'query' => new SearchQueryVO($request->get('q') . '=name,email,bio'),
            'limit' => 10,
            'contexts' => $this->getContexts($request),
        ]);

        $results = $this->hermes->complete($request);

        return response()->json([
            'suggestions' => $results->getOriginalTexts()
        ]);
    }

    public function search(Request $request)
    {
        // Construction de la query
        $queryParts = [];
        
        if ($request->get('name')) {
            $queryParts[] = $request->get('name') . '=name';
        }
        
        if ($request->get('email')) {
            $queryParts[] = $request->get('email') . '=email';
        }
        
        if ($request->get('bio')) {
            $queryParts[] = $request->get('bio') . '=bio';
        }
        
        $queryString = implode('|', $queryParts);

        $request = SearchRequestRecord::from([
            'query' => new SearchQueryVO($queryString),
            'limit' => $request->get('limit', 20),
            'min_similarity' => $request->get('min_similarity', 0.3),
            'contexts' => $this->getContexts($request),
        ]);

        $results = $this->hermes->search($request);

        return response()->json([
            'results' => $results->map(function ($result) {
                return [
                    'id' => $result->document_id,
                    'data' => $result->data->toArray(),
                    'score' => $result->similarity,
                    'matches' => $result->matches->map(function ($match) {
                        return [
                            'field' => $match->field,
                            'value' => $match->original_text,
                            'similarity' => $match->similarity,
                        ];
                    })->toArray(),
                ];
            })->toArray(),
            'total' => $results->count(),
        ]);
    }

    public function suggest(Request $request)
    {
        $request = SuggestionRequestRecord::from([
            'query' => new SearchQueryVO($request->get('q') . '=name,email,bio'),
            'limit' => 5,
            'min_similarity' => $request->get('min_similarity', 0.3),
            'contexts' => $this->getContexts($request),
        ]);

        $results = $this->hermes->suggest($request);

        return response()->json([
            'suggestions' => $results->map(function ($result) {
                return [
                    'text' => $result->original_text,
                    'field' => $result->field,
                    'similarity' => $result->similarity,
                ];
            })->toArray(),
        ]);
    }

    private function getContexts(Request $request): ContextFilterVOCollection
    {
        $contexts = new ContextFilterVOCollection();

        if ($request->user()) {
            // Filtre par tenant (cluster)
            if ($request->user()->tenant_id) {
                $clusters = new ClusterVOCollection();
                $clusters->add(new ClusterVO('tenant:' . $request->user()->tenant_id . '@AND'));
                $contexts->add(new ContextFilterVO(null, $clusters, 'AND'));
            }

            // Filtre par namespace
            if ($request->get('namespace')) {
                $contexts->add(new ContextFilterVO($request->get('namespace')));
            }

            // Les deux
            if ($request->user()->tenant_id && $request->get('namespace')) {
                $clusters = new ClusterVOCollection();
                $clusters->add(new ClusterVO('tenant:' . $request->user()->tenant_id . '@AND'));
                $contexts->add(new ContextFilterVO($request->get('namespace'), $clusters, 'AND'));
            }
        }

        return $contexts;
    }
}
```

### Routes

```php
// routes/api.php
use App\Http\Controllers\SearchController;

Route::prefix('search')->middleware('auth:sanctum')->group(function () {
    Route::get('/autocomplete', [SearchController::class, 'autocomplete']);
    Route::get('/search', [SearchController::class, 'search']);
    Route::get('/suggest', [SearchController::class, 'suggest']);
});
```

---

## License

MIT © [Andy Defer](https://github.com/andydefer)