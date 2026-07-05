# IndexSearcher - Référence Technique

## Description

Service de recherche et d'interrogation de l'index, permettant de rechercher des documents via des n-grammes lexicaux et phonétiques (métaphones) avec des filtres avancés.

## Hiérarchie / Implémentations

```
IndexSearcher (final)
    └── Dépendances : IndexedDocumentRepository, IndexedTokenRepository, TextNormalizerInterface, IndexerConfig
```

## Rôle principal

Assure la recherche dans l'index en combinant :

- Recherche lexicale (n-grammes exacts)
- Recherche phonétique (métaphones pour tolérer les fautes d'orthographe)
- Filtrage par champ, cluster, fingerprint
- Intersection de résultats (logique AND)
- Restitution des métadonnées de correspondance (champ, valeur, type)

## API / Méthodes publiques

### `__construct(IndexedDocumentRepository $documentRepository, IndexedTokenRepository $tokenRepository, TextNormalizerInterface $textNormalizer, IndexerConfig $config)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$documentRepository` | `IndexedDocumentRepository` | Repository des documents |
| `$tokenRepository` | `IndexedTokenRepository` | Repository des tokens |
| `$textNormalizer` | `TextNormalizerInterface` | Service de normalisation des textes |
| `$config` | `IndexerConfig` | Configuration de l'indexeur |

**Retourne :** `void`

---

### `exists(IndexableFingerPrintVO $finger_print): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$finger_print` | `IndexableFingerPrintVO` | Fingerprint à vérifier |

**Retourne :** `bool` - `true` si le document existe, `false` sinon

**Exemple :**
```php
$fingerPrint = new IndexableFingerPrintVO('App.Models.User|123');

if ($searcher->exists($fingerPrint)) {
    echo "Le document existe\n";
}
```

---

### `search(SearchQueryRecord $query): IndexableSearchResultCollection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$query` | `SearchQueryRecord` | La requête de recherche (n-grams, filtres, limite) |

**Retourne :** `IndexableSearchResultCollection<IndexableSearchResultRecord>` - Collection des résultats de recherche

**Exemple :**
```php
$query = new SearchQueryRecord(
    query: new SearchQueryVO('john=name|developer=description'),
    cluster: new ClusterVO('tenant:company_abc'),
    limit: 50
);

$results = $searcher->search($query);

foreach ($results as $result) {
    echo $result->item->finger_print->getValue(); // 'App.Models.User|123'
    echo $result->field; // 'name'
    echo $result->gram_value; // 'john'
    echo $result->gram_type->value; // 'lexical'
}
```

## Cas d'utilisation

### Cas 1 : Recherche simple (un seul n-gram)

```php
<?php

use AndyDefer\LaravelHermes\Records\SearchQueryRecord;
use AndyDefer\LaravelHermes\ValueObjects\SearchQueryVO;

$query = new SearchQueryRecord(
    query: new SearchQueryVO('john=name')
);

$results = $searcher->search($query);
// Trouve tous les documents contenant "john" dans le champ "name"
```

### Cas 2 : Recherche multi-champs

```php
<?php

$query = new SearchQueryRecord(
    query: new SearchQueryVO('john=name,email,description')
);

$results = $searcher->search($query);
// Trouve "john" dans "name", "email" ou "description"
```

### Cas 3 : Recherche multi-n-grams (AND)

```php
<?php

$query = new SearchQueryRecord(
    query: new SearchQueryVO('john=name|developer=description')
);

$results = $searcher->search($query);
// Trouve les documents contenant "john" dans "name" ET "developer" dans "description"
```

### Cas 4 : Recherche avec cluster

```php
<?php

use AndyDefer\LaravelHermes\ValueObjects\ClusterVO;

$query = new SearchQueryRecord(
    query: new SearchQueryVO('john=name'),
    cluster: new ClusterVO('tenant:company_abc|env:production')
);

$results = $searcher->search($query);
// Trouve "john" uniquement dans le tenant "company_abc" en production
```

### Cas 5 : Recherche avec fingerprint

```php
<?php

use AndyDefer\LaravelHermes\ValueObjects\IndexableFingerPrintVO;

$query = new SearchQueryRecord(
    query: new SearchQueryVO('john=name'),
    finger_print: new IndexableFingerPrintVO('App.Models.User|123')
);

$results = $searcher->search($query);
// Vérifie si le document 123 contient "john" dans "name"
```

### Cas 6 : Autocomplétion partielle

```php
<?php

// Avec min_size=3, max_size=5
$query = new SearchQueryRecord(
    query: new SearchQueryVO('joh=name')
);

$results = $searcher->search($query);
// Trouve "john", "johndoe", etc. (partielle, car "joh" est un n-gram)
```

## Flux d'exécution

```
┌─────────────────────────────────────────────────────────────────────────────┐
│ IndexSearcher::search(SearchQueryRecord $query)                           │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  1. Résoudre minSize / maxSize (query ou config)                           │
│                                                                             │
│  2. Pour chaque n-gram dans $query->query->getNgrams() :                   │
│     ├── Normaliser le n-gram                                                │
│     ├── Recherche LEXICAL (tokens exacts)                                  │
│     │   └── searchTokens() → collection de document_ids                    │
│     ├── Recherche METAPHONE (phonétique)                                   │
│     │   └── searchTokens() → collection de document_ids                    │
│     └── Fusionner les deux collections (UNION)                             │
│                                                                             │
│  3. Intersecter tous les résultats (AND)                                   │
│                                                                             │
│  4. Appliquer la limite                                                    │
│                                                                             │
│  5. Charger les documents depuis la base                                   │
│                                                                             │
│  6. Pour chaque document :                                                 │
│     └── findMatchInfo() → champ, gram_value, gram_type                     │
│                                                                             │
│  7. Retourner IndexableSearchResultCollection                              │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Query vide | `InvalidArgumentException` | `Search query cannot be empty` |
| n-gram invalide | `InvalidArgumentException` | `Invalid format. Expected "ngram=field1,field2"` |
| Document non trouvé | Aucune | Retourne `null` ou collection vide |

## Performance

| Opération | Complexité | Notes |
|-----------|------------|-------|
| `search()` avec 1 n-gram | O(log n + k) | 1 recherche + chargement des k résultats |
| `search()` avec N n-grams | O(N log n + k) | N recherches + intersection |
| `searchTokens()` LEXICAL | O(log n) | Index sur `token` |
| `searchTokens()` METAPHONE | O(log n) | Index sur `token` |

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Complet |
| Laravel 10.x | ✅ Complet |
| Laravel 11.x | ✅ Complet |
| Laravel 12.x | ✅ Complet |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelHermes\Services\Composants\IndexSearcher;
use AndyDefer\LaravelHermes\Records\SearchQueryRecord;
use AndyDefer\LaravelHermes\ValueObjects\SearchQueryVO;
use AndyDefer\LaravelHermes\ValueObjects\ClusterVO;
use AndyDefer\LaravelHermes\ValueObjects\IndexableFingerPrintVO;

$searcher = new IndexSearcher(
    new IndexedDocumentRepository(),
    new IndexedTokenRepository(),
    new TextNormalizerService(),
    new IndexerConfig()
);

// 1. Recherche simple
$query = new SearchQueryRecord(
    query: new SearchQueryVO('john=name')
);
$results = $searcher->search($query);
echo "Résultats: " . $results->count() . "\n";

// 2. Recherche avec cluster et limite
$query = new SearchQueryRecord(
    query: new SearchQueryVO('john=name|developer=description'),
    cluster: new ClusterVO('tenant:company_abc|env:production'),
    limit: 20
);
$results = $searcher->search($query);

// 3. Affichage des résultats
foreach ($results as $result) {
    echo sprintf(
        "Document: %s | Champ: %s | Token: %s | Type: %s\n",
        $result->item->finger_print->getValue(),
        $result->field,
        $result->gram_value,
        $result->gram_type->value
    );
}

// 4. Vérification d'existence
$fingerPrint = new IndexableFingerPrintVO('App.Models.User|123');
if ($searcher->exists($fingerPrint)) {
    echo "Le document existe\n";
}
```

## Voir aussi

- `SearchQueryRecord` - Record de requête de recherche
- `SearchQueryVO` - Value Object de requête
- `IndexableSearchResultRecord` - Record de résultat
- `IndexableSearchResultCollection` - Collection de résultats
- `GramType` - Types de tokens (LEXICAL, METAPHONE)
- `ClusterVO` - Value Object pour les clusters
- `IndexableFingerPrintVO` - Value Object pour les fingerprints
- `TextNormalizerInterface` - Normalisation des textes
- `IndexerConfig` - Configuration de l'indexeur