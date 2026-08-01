# Laravel Hermes - Documentation Complète

## Table des matières

1. [Installation](#1-installation)
2. [Configuration](#2-configuration)
3. [Préparer votre modèle](#3-préparer-votre-modèle)
4. [Les Clusters contextuels](#4-les-clusters-contextuels)
5. [Completion](#5-completion)
6. [Suggestion](#6-suggestion)
7. [Search](#7-search)
8. [Syntaxe de recherche](#8-syntaxe-de-recherche)
9. [Les Contextes](#9-les-contextes)
10. [Combinaisons avancées](#10-combinaisons-avancées)
11. [Repositories](#11-repositories)
12. [Collections](#12-collections)
13. [Exemple complet : API de recherche](#13-exemple-complet--api-de-recherche)
14. [Cas d'usage concrets](#14-cas-dusage-concrets)
15. [Débogage et résolution des problèmes](#15-débogage-et-résolution-des-problèmes)
16. [Performance et bonnes pratiques](#16-performance-et-bonnes-pratiques)

---

## 1. Installation

### 1.1 Prérequis

- PHP 8.1 ou supérieur
- Laravel 10.x, 11.x, 12.x, 13.x, 14.x ou 15.x
- Laravel Indexer installé et configuré

### 1.2 Installation via Composer

```bash
composer require andydefer/laravel-hermes
```

### 1.3 Migrations

```bash
php artisan vendor:publish --tag=hermes-migrations
php artisan migrate
```

### 1.4 Configuration

```bash
php artisan vendor:publish --tag=hermes-config
```

---

## 2. Configuration

Le fichier de configuration `config/hermes.php` :

```php
<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Configuration de la similarité
    |--------------------------------------------------------------------------
    */
    'similarity' => [
        // Taille des n-grams
        'gram_min_size' => 2,
        'gram_max_size' => 4,

        // Dimension du vecteur pour la similarité
        'vector_dimension' => 128,

        // Poids des composantes
        'textual_weight' => 0.6,
        'phonetic_weight' => 0.4,

        // Bonus pour les correspondances
        'letter_bonus' => 0.05,
        'bigram_bonus' => 0.03,

        // Limites de traitement
        'min_word_length' => 2,
        'max_words' => 50,
        'max_pairs' => 2500,
        'timeout_seconds' => 0.5,

        // Configuration de Levenshtein
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

### 2.1 Variables d'environnement

```env
HERMES_GRAM_MIN_SIZE=2
HERMES_GRAM_MAX_SIZE=4
HERMES_SIMILARITY_TIMEOUT=0.5
```

---

## 3. Préparer votre modèle

Votre modèle doit implémenter l'interface `Indexable` de Laravel Indexer.

```php
<?php

namespace App\Models;

use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;
use AndyDefer\LaravelIndexer\Contracts\Indexable;
use Illuminate\Database\Eloquent\Model;

class User extends Model implements Indexable
{
    /**
     * Détermine si le modèle doit être indexé.
     */
    public function shouldBeIndexed(): bool
    {
        return $this->is_active && !$this->trashed();
    }

    /**
     * Retourne les données à indexer.
     */
    public function getIndexableData(): StrictAssociative
    {
        return StrictAssociative::from([
            'name' => $this->name,
            'email' => $this->email,
            'bio' => $this->bio,
            'skills' => $this->skills,
            'city' => $this->city,
            'country' => $this->country,
        ]);
    }

    /**
     * Retourne la classe morph.
     */
    public function getMorphClass()
    {
        return self::class;
    }

    /**
     * Retourne le cluster contextuel du modèle.
     */
    public function getIndexableCluster(): ClusterVO
    {
        return new ClusterVO([
            'type' => 'user',
            'tenant' => $this->tenant_id,
            'status' => $this->is_active ? 'active' : 'inactive',
            'role' => $this->role,
            'country' => $this->country,
            'city' => $this->city,
        ]);
    }
}
```

---

## 4. Les Clusters contextuels

### 4.1 Qu'est-ce qu'un cluster ?

Un cluster est un **filtre contextuel** qui permet de restreindre les recherches à un contexte spécifique (tenant, rôle, statut, etc.).

### 4.2 Création d'un cluster

```php
use AndyDefer\LaravelCluster\ValueObjects\ClusterVO;

// Création simple
$cluster = new ClusterVO([
    'status' => 'active',
    'role' => 'admin',
    'tenant' => 'company_abc',
]);

// Création avec données imbriquées
$cluster = new ClusterVO([
    'user' => [
        'status' => 'active',
        'role' => 'admin',
    ],
    'addresses' => [
        ['city' => 'Kinshasa', 'country' => 'RDC'],
    ],
]);
```

### 4.3 Accès aux données

```php
$cluster = new ClusterVO([
    'status' => 'active',
    'role' => 'admin',
    'profile' => [
        'name' => 'John Doe',
    ],
]);

// Accès simple
$status = $cluster->get('status'); // 'active'

// Accès par notation pointée
$name = $cluster->get('profile.name'); // 'John Doe'

// Vérification
if ($cluster->has('profile.name')) {
    echo $cluster->get('profile.name');
}
```

### 4.4 Utilisation dans le modèle

```php
public function getIndexableCluster(): ClusterVO
{
    return new ClusterVO([
        'type' => 'user',
        'status' => $this->is_active ? 'active' : 'inactive',
        'role' => $this->role,
        'tenant' => $this->tenant_id,
        'country' => $this->country,
        'city' => $this->city,
        'verified' => $this->email_verified_at !== null ? 'yes' : 'no',
    ]);
}
```

---

## 5. Completion

**Objectif :** L'utilisateur tape un bout de mot → on propose des mots complets triés par similarité.

### 5.1 Utilisation basique

```php
<?php

namespace App\Services;

use AndyDefer\LaravelHermes\Contracts\Services\HermesInterface;
use AndyDefer\LaravelHermes\Records\CompletionRequestRecord;
use AndyDefer\LaravelIndexer\ValueObjects\SearchQueryVO;

class AutocompleteService
{
    public function __construct(
        private readonly HermesInterface $hermes
    ) {}

    public function suggest(string $prefix): array
    {
        $request = CompletionRequestRecord::from([
            'query' => new SearchQueryVO($prefix . '=name,email,bio'),
            'limit' => 10,
        ]);

        $results = $this->hermes->complete($request);

        return $results->getOriginalTexts();
    }
}

// Utilisation
$service = new AutocompleteService($hermes);
$suggestions = $service->suggest('joh');
// ['John Doe', 'Johanna', 'Johnson', ...]
```

### 5.2 Completion avec contexte

```php
use AndyDefer\LaravelHermes\Collections\ContextFilterVOCollection;
use AndyDefer\LaravelHermes\ValueObjects\ContextFilterVO;
use AndyDefer\Repository\ValueObjects\ClusterQueries;

// Filtrer les utilisateurs actifs du tenant company_abc
$contexts = new ContextFilterVOCollection();
$contexts->add(new ContextFilterVO(
    'App\\Models\\User',
    new ClusterQueries(['cluster' => 'status=active & tenant=company_abc'])
));

$request = CompletionRequestRecord::from([
    'query' => new SearchQueryVO('joh=name,email'),
    'limit' => 10,
    'contexts' => $contexts,
]);

$results = $this->hermes->complete($request);
// Résultat : uniquement les utilisateurs actifs de company_abc
```

### 5.3 Completion multi-termes

```php
// Compléter "john" dans name ET "doe" dans email
$request = CompletionRequestRecord::from([
    'query' => new SearchQueryVO('john=name|doe=email'),
    'limit' => 10,
]);

$results = $this->hermes->complete($request);
```

---

## 6. Suggestion

**Objectif :** L'utilisateur a fait une faute de frappe → on propose les mots les plus proches.

### 6.1 Utilisation basique

```php
use AndyDefer\LaravelHermes\Records\SuggestionRequestRecord;

$request = SuggestionRequestRecord::from([
    'query' => new SearchQueryVO('devloper=skills,bio'),
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

### 6.2 Avec seuil de similarité

```php
// Seulement les suggestions très proches (> 70%)
$request = SuggestionRequestRecord::from([
    'query' => new SearchQueryVO('devloper=skills'),
    'min_similarity' => 0.7,
    'limit' => 5,
]);

$results = $this->hermes->suggest($request);
// Résultat : seulement "developer" (0.92)
```

### 6.3 Suggestion avec contexte

```php
$contexts = new ContextFilterVOCollection();
$contexts->add(new ContextFilterVO(
    'App\\Models\\Doctor',
    new ClusterQueries(['cluster' => 'status=active & specialty=cardiology'])
));

$request = SuggestionRequestRecord::from([
    'query' => new SearchQueryVO('card=specialty'),
    'limit' => 5,
    'min_similarity' => 0.3,
    'contexts' => $contexts,
]);

$results = $this->hermes->suggest($request);
// Résultat : suggestions pour les cardiologues actifs
```

---

## 7. Search

**Objectif :** L'utilisateur cherche → on retourne les documents complets avec le détail des matchs.

### 7.1 Utilisation basique

```php
use AndyDefer\LaravelHermes\Records\SearchRequestRecord;

$request = SearchRequestRecord::from([
    'query' => new SearchQueryVO('john=name,email|developer=description'),
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

### 7.2 Search avec contexte

```php
$contexts = new ContextFilterVOCollection();
$contexts->add(new ContextFilterVO(
    'App\\Models\\User',
    new ClusterQueries(['cluster' => 'tenant=company_abc'])
));

$request = SearchRequestRecord::from([
    'query' => new SearchQueryVO('john=name,email'),
    'contexts' => $contexts,
    'limit' => 20,
    'min_similarity' => 0.3,
]);

$results = $this->hermes->search($request);
// Résultat : users de company_abc
```

### 7.3 Search multi-contextes

```php
// Users de company_abc OU Products de company_xyz
$contexts = new ContextFilterVOCollection();
$contexts->add(new ContextFilterVO(
    'App\\Models\\User',
    new ClusterQueries(['cluster' => 'tenant=company_abc'])
));
$contexts->add(new ContextFilterVO(
    'App\\Models\\Product',
    new ClusterQueries(['cluster' => 'tenant=company_xyz'])
));

$request = SearchRequestRecord::from([
    'query' => new SearchQueryVO('john=name|product=name'),
    'contexts' => $contexts,
    'limit' => 20,
]);

$results = $this->hermes->search($request);
```

### 7.4 Récupération des modèles Eloquent

```php
$results = $this->hermes->search($request);

// Récupérer les instances de modèles
$models = $results->getModelInstances(['profile', 'profile.specialty']);

foreach ($models as $user) {
    echo $user->name . "\n";
    if ($user->relationLoaded('profile')) {
        echo "  - " . $user->profile->specialty . "\n";
    }
}
```

---

## 8. Syntaxe de recherche

### 8.1 Format général

```
ngram=field1,field2|ngram2=field3|ngram3=field1,field4
```

### 8.2 Exemples

| Requête | Description |
|---------|-------------|
| `john=name` | Recherche "john" dans le champ "name" |
| `john=name,email` | Recherche "john" dans "name" ou "email" |
| `john=name\|doe=last_name` | Recherche "john" ET "doe" |
| `john=profile.twitter` | Recherche dans un champ imbriqué |

### 8.3 Comment fonctionne la recherche ?

1. Le terme est normalisé (minuscules, accents supprimés)
2. Le système génère tous les n-grams possibles du terme
3. Il recherche les tokens LEXICAL correspondants
4. Si aucun résultat, il recherche les tokens METAPHONE (phonétique)
5. Retourne les documents trouvés

**Exemple :**
- Indexé : "john" → tokens : ["joh", "ohn", "john"]
- Recherche "joh" → trouve "john" car "joh" est un token
- Recherche "jon" → trouve "john" via métaphone (JN → jn)

---

## 9. Les Contextes

### 9.1 Qu'est-ce qu'un contexte ?

Un contexte est un **filtre combiné** (namespace + clusters) pour les recherches.

### 9.2 Création d'un contexte

```php
use AndyDefer\LaravelHermes\ValueObjects\ContextFilterVO;
use AndyDefer\Repository\ValueObjects\ClusterQueries;

// Uniquement namespace
$context = new ContextFilterVO('App\\Models\\User');

// Uniquement clusters
$context = new ContextFilterVO(
    null,
    new ClusterQueries(['cluster' => 'tenant=company_abc'])
);

// Les deux (ET)
$context = new ContextFilterVO(
    'App\\Models\\User',
    new ClusterQueries(['cluster' => 'tenant=company_abc'])
);
```

### 9.3 Collection de contextes

```php
use AndyDefer\LaravelHermes\Collections\ContextFilterVOCollection;

$contexts = new ContextFilterVOCollection();

// Ajout unique
$contexts->add(new ContextFilterVO('App\\Models\\User'));

// Ajout multiple
$contexts->add(
    new ContextFilterVO('App\\Models\\User'),
    new ContextFilterVO('App\\Models\\Product'),
    new ContextFilterVO(null, new ClusterQueries(['cluster' => 'status=active']))
);

// Extraction des données
$namespaces = $contexts->getNamespaces();      // ['App\\Models\\User', 'App\\Models\\Product']
$queries = $contexts->getClusterQueries();     // ['status=active']

// Filtrage
$userContexts = $contexts->filterByNamespace('App\\Models\\User');
$activeContexts = $contexts->filterByClusterQuery('status=active');

// Vérifications
$hasNamespace = $contexts->hasAnyNamespace();  // true
$hasCluster = $contexts->hasAnyCluster();      // true
```

### 9.4 Logique entre contextes

Les contextes sont combinés avec un **OU logique** :

```php
$contexts = new ContextFilterVOCollection();
$contexts->add(new ContextFilterVO('App\\Models\\User'));
$contexts->add(new ContextFilterVO('App\\Models\\Product'));

// Résultat : (Users) OU (Products)
```

---

## 10. Combinaisons avancées

### 10.1 Contextes multiples avec requêtes multiples

```php
// (User ET company_abc) OU (Product ET company_xyz)
// ET "john" dans name OU "developer" dans description
$contexts = new ContextFilterVOCollection();
$contexts->add(new ContextFilterVO(
    'App\\Models\\User',
    new ClusterQueries(['cluster' => 'tenant=company_abc'])
));
$contexts->add(new ContextFilterVO(
    'App\\Models\\Product',
    new ClusterQueries(['cluster' => 'tenant=company_xyz'])
));

$request = SearchRequestRecord::from([
    'query' => new SearchQueryVO('john=name,email|developer=description'),
    'contexts' => $contexts,
    'limit' => 50,
]);

$results = $this->hermes->search($request);
// Résultat : (User ET company_abc) OU (Product ET company_xyz)
// ET (john dans name/email) ET (developer dans description)
```

### 10.2 Completion avec contexte complexe

```php
// Compléter "john" dans name des utilisateurs actifs de company_abc
$contexts = new ContextFilterVOCollection();
$contexts->add(new ContextFilterVO(
    'App\\Models\\User',
    new ClusterQueries(['cluster' => 'tenant=company_abc & status=active'])
));

$request = CompletionRequestRecord::from([
    'query' => new SearchQueryVO('john=name,email'),
    'limit' => 10,
    'contexts' => $contexts,
]);

$results = $this->hermes->complete($request);
```

### 10.3 Suggestion avec contexte multi-conditions

```php
// Suggestions pour les médecins en RDC avec spécialité cardiologie
$contexts = new ContextFilterVOCollection();
$contexts->add(new ContextFilterVO(
    'App\\Models\\Doctor',
    new ClusterQueries(['cluster' => 'country=RDC & specialty=cardiology'])
));

$request = SuggestionRequestRecord::from([
    'query' => new SearchQueryVO('card=specialty'),
    'limit' => 5,
    'min_similarity' => 0.3,
    'contexts' => $contexts,
]);

$results = $this->hermes->suggest($request);
```

---

## 11. Repositories

### 11.1 HermesRepository

Le repository fournit un accès direct aux tokens indexés.

```php
use AndyDefer\LaravelHermes\Repositories\HermesRepository;
use AndyDefer\LaravelHermes\Collections\ContextFilterVOCollection;
use AndyDefer\LaravelHermes\ValueObjects\ContextFilterVO;
use AndyDefer\Repository\ValueObjects\ClusterQueries;

$repository = app(HermesRepository::class);

// Trouver des tokens par n-grammes
$ngrams = ['joh', 'ohn', 'john'];
$tokens = $repository->findTokensByNgrams($ngrams, limit: 10);

// Avec filtres de contexte
$contexts = new ContextFilterVOCollection();
$contexts->add(new ContextFilterVO(
    'App\\Models\\User',
    new ClusterQueries(['cluster' => 'tenant=company_abc'])
));

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

## 12. Collections

### 12.1 CompletionResultRecordCollection

```php
$results = $this->hermes->complete($request);

// Extraction
$tokens = $results->getTokens();            // ['john', 'jane']
$texts = $results->getOriginalTexts();      // ['John Doe', 'Jane Smith']
$ids = $results->getTokenIds();             // ['token_1', 'token_2']
$docIds = $results->getDocumentIds();       // ['doc_1', 'doc_2']
$scores = $results->getSimilarities();      // [0.95, 0.85]
$fields = $results->getFields();            // ['name', 'email']

// Filtrage
$byField = $results->filterByField('name');
$bySimilarity = $results->filterByMinSimilarity(0.5);

// Meilleur résultat
$best = $results->getBestMatch();

// Regroupement
$byDoc = $results->groupByDocument();
$byField = $results->groupByField();
```

### 12.2 SuggestionResultRecordCollection

```php
$results = $this->hermes->suggest($request);

// Extraction (mêmes méthodes que Completion)
$tokens = $results->getTokens();
$texts = $results->getOriginalTexts();
$ids = $results->getIds();                  // ⚠️ token_id (renommer en getTokenIds)
$docIds = $results->getDocumentIds();
$scores = $results->getSimilarities();
$fields = $results->getFields();

// Filtrage
$byField = $results->filterByField('name');
$bySimilarity = $results->filterByMinSimilarity(0.5);

// Meilleur résultat
$best = $results->getBestMatch();

// Regroupement
$byDoc = $results->groupByDocument();
$byField = $results->groupByField();
```

### 12.3 SearchResultRecordCollection

```php
$results = $this->hermes->search($request);

// Extraction
$docIds = $results->getDocumentIds();        // ['doc_1', 'doc_2']
$fingerprints = $results->getFingerprints();  // ['App\\Models\\User|1', ...]
$namespaces = $results->getNamespaces();      // ['App\\Models\\User', ...]
$ids = $results->getEntityIds();              // ['1', '2']
$data = $results->getData();                  // Tableau des données
$matches = $results->getMatches();            // Tableau des matchs

// Filtrage
$bySimilarity = $results->filterByMinSimilarity(0.5);
$byField = $results->filterByField('name');
$byNamespace = $results->filterByNamespace('App\\Models\\User');
$byNamespaces = $results->filterByNamespaces(['App\\Models\\User', 'App\\Models\\Product']);

// Chargement des modèles Eloquent
$models = $results->getModelInstances(['profile', 'profile.specialty']);

// Vérifications
$hasUser = $results->belongsToNamespace('App\\Models\\User');
$hasUserOrProduct = $results->belongsToAnyNamespace(['App\\Models\\User', 'App\\Models\\Product']);

// Regroupement
$byNamespace = $results->groupByNamespace();
```

### 12.4 SearchResultVOCollection

```php
use AndyDefer\LaravelHermes\Collections\SearchResultVOCollection;

$vos = new SearchResultVOCollection();

// Ajout de VO (créés par le service)
$vos->add($vo1, $vo2, $vo3);

// Extraction
$dataObjects = $vos->getDatas();             // DataCollection
$fingerprints = $vos->getFingerprints();     // StringTypedCollection
$matches = $vos->getMatches();               // MatchRecordCollection
$scores = $vos->getSimilarities();           // [0.95, 0.85]
$namespaces = $vos->getNamespaces();         // ['App\\Models\\User', ...]
$dataArrays = $vos->getDataArrays();         // Tableau des données

// Filtrage
$bySimilarity = $vos->filterByMinSimilarity(0.5);
$byNamespace = $vos->filterByNamespace('App\\Models\\User');
$byNamespaces = $vos->filterByNamespaces(['App\\Models\\User', 'App\\Models\\Product']);

// Meilleur résultat
$best = $vos->getBestMatch();

// Regroupement
$byNamespace = $vos->groupByNamespace();
```

---

## 13. Exemple complet : API de recherche

### 13.1 Contrôleur

```php
<?php

namespace App\Http\Controllers;

use AndyDefer\LaravelHermes\Contracts\Services\HermesInterface;
use AndyDefer\LaravelHermes\Records\CompletionRequestRecord;
use AndyDefer\LaravelHermes\Records\SearchRequestRecord;
use AndyDefer\LaravelHermes\Records\SuggestionRequestRecord;
use AndyDefer\LaravelHermes\Collections\ContextFilterVOCollection;
use AndyDefer\LaravelHermes\ValueObjects\ContextFilterVO;
use AndyDefer\LaravelIndexer\ValueObjects\SearchQueryVO;
use AndyDefer\Repository\ValueObjects\ClusterQueries;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __construct(
        private readonly HermesInterface $hermes
    ) {}

    public function autocomplete(Request $request): JsonResponse
    {
        $request = CompletionRequestRecord::from([
            'query' => new SearchQueryVO($request->get('q') . '=name,email,bio'),
            'limit' => 10,
            'contexts' => $this->getContexts($request),
        ]);

        $results = $this->hermes->complete($request);

        return response()->json([
            'suggestions' => $results->map(fn($r) => [
                'text' => $r->original_text,
                'field' => $r->field,
                'score' => $r->similarity,
            ])->toArray(),
        ]);
    }

    public function search(Request $request): JsonResponse
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
        if ($request->get('skills')) {
            $queryParts[] = $request->get('skills') . '=skills';
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
                    'fingerprint' => $result->fingerprint,
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
            'limit' => $request->limit,
        ]);
    }

    public function suggest(Request $request): JsonResponse
    {
        $request = SuggestionRequestRecord::from([
            'query' => new SearchQueryVO($request->get('q') . '=name,email,bio,skills'),
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

        // Filtre par tenant (si l'utilisateur est connecté)
        if ($request->user() && $request->user()->tenant_id) {
            $contexts->add(new ContextFilterVO(
                null,
                new ClusterQueries(['cluster' => 'tenant=' . $request->user()->tenant_id])
            ));
        }

        // Filtre par namespace (si demandé)
        if ($request->get('namespace')) {
            $contexts->add(new ContextFilterVO($request->get('namespace')));
        }

        // Filtre par statut
        if ($request->get('status')) {
            $contexts->add(new ContextFilterVO(
                null,
                new ClusterQueries(['cluster' => 'status=' . $request->get('status')])
            ));
        }

        return $contexts;
    }
}
```

### 13.2 Routes

```php
// routes/api.php
use App\Http\Controllers\SearchController;

Route::prefix('search')->middleware('auth:sanctum')->group(function () {
    Route::get('/autocomplete', [SearchController::class, 'autocomplete']);
    Route::get('/suggest', [SearchController::class, 'suggest']);
    Route::get('/', [SearchController::class, 'search']);
});
```

---

## 14. Cas d'usage concrets

### 14.1 Recherche d'utilisateurs avec filtres

```php
<?php

namespace App\Services;

use AndyDefer\LaravelHermes\Contracts\Services\HermesInterface;
use AndyDefer\LaravelHermes\Records\SearchRequestRecord;
use AndyDefer\LaravelHermes\Collections\ContextFilterVOCollection;
use AndyDefer\LaravelHermes\ValueObjects\ContextFilterVO;
use AndyDefer\LaravelIndexer\ValueObjects\SearchQueryVO;
use AndyDefer\Repository\ValueObjects\ClusterQueries;

class UserSearchService
{
    public function __construct(
        private readonly HermesInterface $hermes
    ) {}

    public function searchDoctors(string $query, string $city, string $specialty): array
    {
        $contexts = new ContextFilterVOCollection();
        $contexts->add(new ContextFilterVO(
            'App\\Models\\Doctor',
            new ClusterQueries([
                'cluster' => "city=$city & specialty=$specialty & status=active"
            ])
        ));

        $request = SearchRequestRecord::from([
            'query' => new SearchQueryVO($query . '=name,bio,specialty'),
            'contexts' => $contexts,
            'limit' => 20,
        ]);

        $results = $this->hermes->search($request);

        return $results->getModelInstances(['profile', 'profile.specialty'])->toArray();
    }
}
```

### 14.2 API de recherche e-commerce

```php
<?php

namespace App\Services;

use AndyDefer\LaravelHermes\Contracts\Services\HermesInterface;
use AndyDefer\LaravelHermes\Records\SearchRequestRecord;
use AndyDefer\LaravelIndexer\ValueObjects\SearchQueryVO;
use AndyDefer\Repository\ValueObjects\ClusterQueries;

class ProductSearchService
{
    public function __construct(
        private readonly HermesInterface $hermes
    ) {}

    public function searchProducts(string $query, array $filters): array
    {
        $conditions = [];

        if (isset($filters['category'])) {
            $conditions[] = "category={$filters['category']}";
        }
        if (isset($filters['min_price'])) {
            $conditions[] = "price>={$filters['min_price']}";
        }
        if (isset($filters['max_price'])) {
            $conditions[] = "price<={$filters['max_price']}";
        }
        if (isset($filters['in_stock'])) {
            $conditions[] = "in_stock=" . ($filters['in_stock'] ? 'yes' : 'no');
        }

        $clusterQuery = implode(' & ', $conditions);

        $request = SearchRequestRecord::from([
            'query' => new SearchQueryVO($query . '=name,description,tags'),
            'limit' => $filters['limit'] ?? 20,
            'min_similarity' => $filters['min_similarity'] ?? 0.3,
            'contexts' => null, // Les clusters sont dans la requête
        ]);

        // Utilisation directe des clusters (alternative)
        $results = $this->hermes->search($request);

        return $results->getModelInstances()->toArray();
    }
}

// Utilisation
$service = new ProductSearchService($hermes);
$products = $service->searchProducts('laptop', [
    'category' => 'electronics',
    'min_price' => 500,
    'max_price' => 2000,
    'in_stock' => true,
    'limit' => 10,
]);
```

### 14.3 API de suggestions en temps réel

```php
<?php

namespace App\Http\Controllers\Api;

use AndyDefer\LaravelHermes\Contracts\Services\HermesInterface;
use AndyDefer\LaravelHermes\Records\CompletionRequestRecord;
use AndyDefer\LaravelIndexer\ValueObjects\SearchQueryVO;

class AutocompleteController extends Controller
{
    public function __construct(
        private readonly HermesInterface $hermes
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $query = $request->get('q');
        $field = $request->get('field', 'name');
        $limit = $request->get('limit', 10);

        if (strlen($query) < 2) {
            return response()->json(['suggestions' => []]);
        }

        $request = CompletionRequestRecord::from([
            'query' => new SearchQueryVO($query . '=' . $field),
            'limit' => $limit,
        ]);

        $results = $this->hermes->complete($request);

        return response()->json([
            'suggestions' => $results->map(fn($r) => [
                'value' => $r->original_text,
                'score' => $r->similarity,
            ])->toArray(),
        ]);
    }
}
```

---

## 15. Débogage et résolution des problèmes

### 15.1 Vérifier les modèles indexés

```php
// Vérifier qu'un modèle est indexé
use AndyDefer\LaravelIndexer\Contracts\IndexerInterface;

$indexer = app(IndexerInterface::class);
$user = User::find(1);
$exists = $indexer->exists($user);
dump($exists);
```

### 15.2 Vérifier les clusters

```php
$user = User::find(1);
dump($user->getIndexableCluster()->toArray());
```

### 15.3 Vérifier les tokens indexés

```php
use AndyDefer\LaravelIndexer\Repositories\IndexedTokenRepository;

$repository = app(IndexedTokenRepository::class);
$tokens = $repository->findByToken('john');
dump($tokens);
```

### 15.4 Problèmes courants

| Problème | Cause | Solution |
|----------|-------|----------|
| Aucun résultat | Requête invalide | Vérifier la syntaxe de recherche |
| Résultats incomplets | Token size trop petit | Augmenter `gram_min_size` dans la config |
| Recherche lente | Pas d'index sur les tokens | Vérifier les migrations et les index |
| Contextes ignorés | ClusterQueries mal formaté | Vérifier la syntaxe `key=value` |
| Erreur de syntaxe | Parenthèses mal équilibrées | Vérifier les parenthèses dans la requête |

### 15.5 Vérification de l'index

```bash
# Vérifier les documents indexés
php artisan tinker
>>> app(AndyDefer\LaravelIndexer\Contracts\IndexerInterface::class)->countIndexed(App\Models\User::class);

# Voir le SQL généré
DB::enableQueryLog();
$results = $hermes->search($request);
dump(DB::getQueryLog());
```

---

## 16. Performance et bonnes pratiques

### 16.1 Recherche

```php
// ✅ Recommandé - Limiter les résultats
$request = SearchRequestRecord::from([
    'query' => $query,
    'limit' => 20,
]);

// ✅ Recommandé - Utiliser les contextes pour filtrer
$contexts->add(new ContextFilterVO(
    'App\\Models\\User',
    new ClusterQueries(['cluster' => 'status=active'])
));

// ✅ Recommandé - Utiliser getModelInstances() pour charger les relations
$models = $results->getModelInstances(['profile', 'profile.specialty']);

// ❌ À éviter - Recherche sans limite
$request = SearchRequestRecord::from([
    'query' => $query,
    'limit' => null,  // Pas de limite = risque de performance
]);

// ❌ À éviter - Chargement individuel des modèles
foreach ($results as $result) {
    $user = User::find($result->id);  // N+1 problème
}
```

### 16.2 Completion et Suggestion

```php
// ✅ Recommandé - Utiliser un seuil de similarité
$request = SuggestionRequestRecord::from([
    'query' => 'devloper=skills',
    'min_similarity' => 0.3,  // Évite les suggestions trop éloignées
    'limit' => 5,
]);

// ✅ Recommandé - Limiter les résultats pour les autocomplétions
$request = CompletionRequestRecord::from([
    'query' => 'joh=name',
    'limit' => 10,  // Max 10 suggestions
]);
```

### 16.3 Contextes

```php
// ✅ Recommandé - Contextes simples
$contexts->add(new ContextFilterVO(
    'App\\Models\\User',
    new ClusterQueries(['cluster' => 'tenant=company_abc'])
));

// ✅ Recommandé - Contextes avec AND
$contexts->add(new ContextFilterVO(
    'App\\Models\\User',
    new ClusterQueries(['cluster' => 'tenant=company_abc & status=active'])
));

// ⚠️ À éviter - Trop de contextes OR (performance)
$contexts->add(new ContextFilterVO('App\\Models\\User'));
$contexts->add(new ContextFilterVO('App\\Models\\Product'));
$contexts->add(new ContextFilterVO('App\\Models\\Order'));
$contexts->add(new ContextFilterVO('App\\Models\\Invoice'));
```

### 16.4 Configuration recommandée

```php
// config/hermes.php
return [
    'similarity' => [
        'gram_min_size' => 2,      // Bon équilibre
        'gram_max_size' => 4,      // Bon équilibre
        'min_word_length' => 2,    // Ignorer les mots trop courts
        'max_words' => 50,         // Limite pour les longs textes
        'max_pairs' => 2500,       // Éviter les explosions de calcul
        'timeout_seconds' => 0.5,  // Timeout pour les calculs lourds
    ],
];
```

### 16.5 Optimisation des modèles

```php
public function shouldBeIndexed(): bool
{
    // ✅ Indexer uniquement les modèles actifs
    return $this->is_active && !$this->trashed();
}

public function getIndexableCluster(): ClusterVO
{
    // ✅ Utiliser des clusters pour le filtrage
    return new ClusterVO([
        'status' => $this->is_active ? 'active' : 'inactive',
        'tenant' => $this->tenant_id,
        // ✅ Éviter les valeurs trop dynamiques (ex: updated_at)
    ]);
}
```

---

## License

MIT © [Andy Defer](https://github.com/andydefer)