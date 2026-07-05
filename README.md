# Laravel Indexer

[![Latest Version on Packagist](https://img.shields.io/packagist/v/andydefer/laravel-indexer.svg)](https://packagist.org/packages/andydefer/laravel-indexer)
[![PHP Version Require](https://img.shields.io/packagist/php-v/andydefer/laravel-indexer.svg)](https://packagist.org/packages/andydefer/laravel-indexer)
[![Laravel Version](https://img.shields.io/badge/Laravel-10%2F11%2F12%2F13%2F14%2F15-ff2d20.svg)](https://laravel.com)
[![License](https://img.shields.io/packagist/l/andydefer/laravel-indexer.svg)](https://packagist.org/packages/andydefer/laravel-indexer)

---

## Table des matières

- [Installation](#installation)
- [Préparer votre modèle](#préparer-votre-modèle)
- [Indexer des données](#indexer-des-données)
- [Rechercher](#rechercher)
- [Les clusters](#les-clusters)
- [Autocomplétion](#autocomplétion)
- [Supprimer](#supprimer)
- [Repositories](#repositories)
- [Collections](#collections)

---

## Installation

```bash
composer require andydefer/laravel-indexer
```

### Migrations

```bash
php artisan vendor:publish --tag=indexer-migrations
php artisan migrate
```

### Configuration (optionnel)

```bash
php artisan vendor:publish --tag=indexer-config
```

```php
// config/indexer.php
return [
    'token_types' => [
        'ngrams' => [
            'min_size' => 3,
            'max_size' => 5,
        ],
        'metaphone' => true,
    ],
    'default_limit' => 100,
];
```

---

## Préparer votre modèle

Votre modèle doit implémenter l'interface `Indexable`.

```php
<?php

namespace App\Models;

use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelIndexer\Contracts\Indexable;
use Illuminate\Database\Eloquent\Model;

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
            'profile' => [
                'twitter' => $this->twitter,
                'github' => $this->github,
            ],
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

## Indexer des données

### Indexer un document

```php
use AndyDefer\LaravelHermes\Contracts\IndexerInterface;
use AndyDefer\LaravelHermes\Services\Composants\IndexableRecordFactory;
use AndyDefer\LaravelHermes\ValueObjects\ClusterVO;

class UserService
{
    public function __construct(
        private IndexerInterface $indexer
    ) {}

    public function indexUser(User $user): void
    {
        $cluster = new ClusterVO('tenant:' . $user->tenant_id);
        $record = IndexableRecordFactory::convert($user, $cluster);
        $this->indexer->index($record);
    }
}
```

### Indexer en masse

```php
use AndyDefer\LaravelHermes\Collections\IndexableRecordCollection;

public function indexAllUsers(): void
{
    $records = new IndexableRecordCollection;

    foreach (User::where('is_active', true)->cursor() as $user) {
        $cluster = new ClusterVO('tenant:' . $user->tenant_id);
        $records->add(IndexableRecordFactory::convert($user, $cluster));
    }

    $this->indexer->indexMany($records);
}
```

### Rafraîchir (mise à jour)

```php
public function updateUser(User $user): void
{
    $user->save();
    
    $cluster = new ClusterVO('tenant:' . $user->tenant_id);
    $record = IndexableRecordFactory::convert($user, $cluster);
    $this->indexer->refresh($record);
}
```

---

## Rechercher

### Comment fonctionne la recherche ?

1. Le terme est normalisé (minuscules, accents supprimés)
2. Le système génère tous les n-grammes possibles du terme
3. Il recherche les tokens LEXICAL correspondants
4. Si aucun résultat, il recherche les tokens METAPHONE (phonétique)
5. Retourne les documents trouvés

**Exemple :**
- Indexé : "john" → tokens : ["joh", "ohn", "john"]
- Recherche "joh" → trouve "john" car "joh" est un token
- Recherche "jon" → trouve "john" via métaphone (JN → jn)

### Recherche simple

```php
use AndyDefer\LaravelHermes\Records\SearchQueryRecord;
use AndyDefer\LaravelHermes\ValueObjects\SearchQueryVO;

public function searchUsers(string $query): array
{
    // Recherche "john" dans name, email, bio
    $searchQuery = new SearchQueryRecord(
        query: new SearchQueryVO($query . '=name,email,bio')
    );

    $results = $this->indexer->search($searchQuery);
    
    // Récupérer les IDs
    $userIds = $results->getItems()->getIdValues()->toArray();
    
    return User::whereIn('id', $userIds)->get();
}
```

### Recherche multi-termes (AND)

```php
// "john" dans name ET "developer" dans bio
$query = new SearchQueryRecord(
    query: new SearchQueryVO('john=name|developer=bio')
);
```

### Recherche multi-champs (OR)

```php
// "john" dans name OU email OU bio
$query = new SearchQueryRecord(
    query: new SearchQueryVO('john=name,email,bio')
);
```

### Recherche avec limite

```php
$query = new SearchQueryRecord(
    query: new SearchQueryVO('john=name,email'),
    limit: 20
);
```

### Filtrer par tenant (cluster)

```php
use AndyDefer\LaravelHermes\ValueObjects\ClusterVO;

$query = new SearchQueryRecord(
    query: new SearchQueryVO('john=name,email'),
    cluster: new ClusterVO('tenant:company_abc')
);
```

### Vérifier l'existence d'un document

```php
use AndyDefer\LaravelHermes\ValueObjects\IndexableFingerPrintVO;

$fingerPrint = new IndexableFingerPrintVO('App.Models.User|123');
$exists = $this->indexer->exists($fingerPrint);
```

---

## Les clusters

Le cluster est un **filtre contextuel**. Il permet de filtrer les recherches par contexte (tenant, environnement, etc.).

### Créer un cluster

```php
use AndyDefer\LaravelHermes\ValueObjects\ClusterVO;

// Simple
$cluster = new ClusterVO('tenant:company_abc');

// Multiple
$cluster = new ClusterVO('tenant:company_abc|env:production|region:europe');

// Valeurs multiples
$cluster = new ClusterVO('tenant:company_abc,company_xyz|category:electronics,music');
```

### Lire un cluster

```php
$cluster = new ClusterVO('tenant:company_abc,company_xyz|env:production');

$cluster->get('tenant');     // ['company_abc', 'company_xyz']
$cluster->get('env');        // ['production']
$cluster->has('tenant');     // true
$cluster->has('unknown');    // false
$cluster->contains('tenant', 'company_abc');  // true
$cluster->all();
// ['tenant' => ['company_abc', 'company_xyz'], 'env' => ['production']]
```

### Manipuler un cluster

```php
$cluster = new ClusterVO('tenant:company_abc');

// Ajouter
$new = $cluster->with('env', 'production');
$new = $cluster->withMany('category', ['electronics', 'music']);

// Supprimer
$new = $cluster->without('tenant', 'company_abc');
$new = $cluster->without('env');

// Chaînage
$new = $cluster
    ->with('env', 'production')
    ->with('region', 'europe');
```

---

## Autocomplétion

```php
use AndyDefer\LaravelHermes\Repositories\IndexedTokenRepository;

class AutocompleteService
{
    public function __construct(
        private IndexedTokenRepository $tokenRepository
    ) {}

    public function suggest(string $prefix): array
    {
        $tokens = $this->tokenRepository->autocomplete($prefix, 10);
        return $tokens->pluck('token')->toArray();
    }
}
```

### Autocomplétion par champ

```php
$tokens = $this->tokenRepository->getModel()
    ->newQuery()
    ->where('token', 'LIKE', $prefix . '%')
    ->where('field', 'name')
    ->select('token')
    ->distinct()
    ->limit(10)
    ->get();
```

### Autocomplétion avec tenant

```php
$tokens = $this->tokenRepository->getModel()
    ->newQuery()
    ->where('token', 'LIKE', $prefix . '%')
    ->whereHas('document', function ($q) use ($tenantId) {
        $q->where('cluster', 'LIKE', '%tenant:' . $tenantId . '%');
    })
    ->select('token')
    ->distinct()
    ->limit(10)
    ->get();
```

---

## Supprimer

### Supprimer un document

```php
use AndyDefer\LaravelHermes\ValueObjects\IndexableFingerPrintVO;

$fingerPrint = new IndexableFingerPrintVO('App.Models.User|123');
$this->indexer->delete($fingerPrint);
```

### Supprimer plusieurs

```php
use AndyDefer\LaravelHermes\Collections\IndexableFingerPrintVOCollection;

$collection = new IndexableFingerPrintVOCollection;
$collection->add(new IndexableFingerPrintVO('App.Models.User|123'));
$collection->add(new IndexableFingerPrintVO('App.Models.User|456'));
$this->indexer->deleteMany($collection);
```

### Supprimer par namespace

```php
use AndyDefer\LaravelHermes\Repositories\IndexedDocumentRepository;

$repository = app(IndexedDocumentRepository::class);
$repository->deleteByNamespace('App.Models.User');
```

### Supprimer par cluster

```php
$cluster = new ClusterVO('tenant:company_abc');
$repository->deleteByCluster($cluster);

$repository->deleteByClusterKeyValue('tenant', 'company_abc');
```

### Vider l'index

```php
$this->indexer->clear();
```

---

## Repositories

### IndexedDocumentRepository

```php
use AndyDefer\LaravelHermes\Repositories\IndexedDocumentRepository;

$repository = app(IndexedDocumentRepository::class);

// Trouver
$doc = $repository->findByFingerPrint($fingerPrint);
$doc = $repository->findByFingerprintString('App.Models.User|123');
$docs = $repository->findByNamespace('App.Models.User');
$docs = $repository->findByCluster($cluster);
$docs = $repository->findByClusterKeyValue('tenant', 'company_abc');
$docs = $repository->findByIds(['uuid1', 'uuid2']);

// Compter
$count = $repository->countByNamespace('App.Models.User');
$count = $repository->countByCluster($cluster);

// Distinct
$namespaces = $repository->getDistinctNamespaces();
$keys = $repository->getDistinctClusterKeys();
$values = $repository->getDistinctClusterValues('tenant');

// Vérifier
$exists = $repository->existsByFingerPrint($fingerPrint);
$exists = $repository->existsByNamespace('App.Models.User');
$exists = $repository->existsByCluster($cluster);

// Supprimer
$repository->deleteByFingerPrint($fingerPrint);
$repository->deleteByFingerprintString('App.Models.User|123');
$repository->deleteByNamespace('App.Models.User');
$repository->deleteByCluster($cluster);
$repository->deleteByClusterKeyValue('tenant', 'company_abc');

// Tout avec tokens
$docs = $repository->findAllWithTokens();
```

### IndexedTokenRepository

```php
use AndyDefer\LaravelHermes\Repositories\IndexedTokenRepository;
use AndyDefer\LaravelHermes\Enums\GramType;

$repository = app(IndexedTokenRepository::class);

// Trouver
$tokens = $repository->findByToken('john');
$tokens = $repository->findByType(GramType::LEXICAL);
$tokens = $repository->findByField('name');
$tokens = $repository->findByDocumentId('uuid');
$tokens = $repository->findByDocumentFingerPrint($fingerPrint);
$tokens = $repository->findByNamespace('App.Models.User');
$tokens = $repository->findByCluster($cluster);
$tokens = $repository->findByClusterKeyValue('tenant', 'company_abc');

// Token + critères
$tokens = $repository->findByTokenAndField('john', 'name');
$tokens = $repository->findByTokenAndType('john', GramType::LEXICAL);
$tokens = $repository->findByTokenAndNamespace('john', 'App.Models.User');
$tokens = $repository->findByTokenAndCluster('john', $cluster);
$tokens = $repository->findByTokenFieldAndNamespace('john', 'name', 'App.Models.User');

// Document IDs par token
$ids = $repository->getDocumentIdsForToken('john');
$ids = $repository->getDocumentIdsForTokenAndField('john', 'name');
$ids = $repository->getDocumentIdsForTokenAndCluster('john', $cluster);
$ids = $repository->getDocumentIdsForTokenFieldAndCluster('john', 'name', $cluster);

// Compter
$count = $repository->countDistinctTokens();
$count = $repository->countByType(GramType::LEXICAL);
$count = $repository->countByField('name');
$count = $repository->countByNamespace('App.Models.User');

// Supprimer
$repository->deleteByDocumentId('uuid');
$repository->deleteByDocumentFingerPrint($fingerPrint);
$repository->deleteByNamespace('App.Models.User');
$repository->deleteByCluster($cluster);
$repository->deleteByClusterKeyValue('tenant', 'company_abc');
$repository->deleteByToken('john');
$repository->deleteByTokenAndField('john', 'name');

// Autres
$tokens = $repository->getDistinctTokens();
$fields = $repository->getDistinctFields();
$token = $repository->findByTokenFieldAndDocument('john', 'name', 'uuid', GramType::LEXICAL);
$frequency = $repository->incrementFrequency('token-id');
```

---

## Collections

### IndexableSearchResultCollection

```php
$results = $this->indexer->search($query);

// Itération
foreach ($results as $result) {
    $item = $result->item;
    $fingerprint = $item->finger_print->getValue();
    $field = $result->field;
    $gram = $result->gram_value;
    $type = $result->gram_type->value; // 'lexical' ou 'metaphone'
}

// Filtrage
$byField = $results->filterByField('name');
$byNamespace = $results->filterByNamespace('App.Models.User');

// Extraction
$ids = $results->getIds();
$items = $results->getItems();
$fingerPrints = $results->getFingerPrints();

// Groupement
$byField = $results->groupByField();
$byNamespace = $results->groupByNamespace();
```

### IndexableRecordCollection

```php
$records = new IndexableRecordCollection;

// Ajout
$records->add($record);

// Découpage
$chunks = $records->chunk(100);

// Filtrage
$users = $records->filterByNamespace('App.Models.User');
$withTenant = $records->filterByCluster('tenant', 'company_abc');

// Extraction
$fingerPrints = $records->getFingerPrints();
$ids = $records->getIdValues();

// Recherche
$record = $records->findById('123');
$record = $records->findByIdAndNamespace('123', 'App.Models.User');

// Vérification
$hasId = $records->containsId('123');
$hasNamespace = $records->containsNamespace('App.Models.User');

// Indexation
$this->indexer->indexMany($records);
```

### IndexableFingerPrintVOCollection

```php
$fingerPrints = new IndexableFingerPrintVOCollection;

// Filtrage
$users = $fingerPrints->filterByNamespace('App.Models.User');

// Extraction
$ids = $fingerPrints->getIds();
$namespaces = $fingerPrints->getNamespaces();

// Vérification
$hasId = $fingerPrints->containsId('123');
$hasNamespace = $fingerPrints->containsNamespace('App.Models.User');

// Recherche
$fp = $fingerPrints->findByValue('App.Models.User|123');
$fp = $fingerPrints->findByIdAndNamespace('123', 'App.Models.User');

// Groupement
$grouped = $fingerPrints->groupByNamespace();
```

### ClusterVOCollection

```php
$clusters = new ClusterVOCollection;

// Filtrage
$withTenant = $clusters->filterByKey('tenant');
$withSpecific = $clusters->filterByPair('tenant', 'company_abc');

// Extraction
$values = $clusters->getValuesForKey('tenant');
$keys = $clusters->getUniqueKeys();

// Groupement
$grouped = $clusters->groupByKey('tenant');

// Vérification
$hasKey = $clusters->hasKey('tenant');
$hasPair = $clusters->hasPair('tenant', 'company_abc');

// Fusion
$merged = $clusters->mergeAll();
```

---

## License

MIT © [Andy Defer](https://github.com/andydefer)