# Documentation du système d'indexation avec Eloquent

## 1. Introduction

Le système d'indexation permet de stocker et de récupérer rapidement des données structurées via des tokens (n-grammes et metaphones) générés à partir des champs d'un `IndexableRecord`.

**Objectif :** Recherche en **O(k)** où `k` est le nombre de résultats, sans parcours linéaire sur de grands volumes.

**Approche :** Utilisation de **SQL + Eloquent** pour une meilleure scalabilité, des index automatiques et une maintenance facilitée.

---

## 2. Structure des données

### 2.1 IndexableRecord

```php
$record = new IndexableRecord(
    finger_print: new IndexableFingerPrintVO('App.Models.User|123'),
    cluster: new ClusterVO('model-User|tenant-company_abc|env-production'),
    data: StrictAssociative::from([
        'name' => 'John Doe',
        'description' => 'Software Developer'
    ])
);
```

### 2.2 Structure des tables

#### Table `indexed_documents`

Stocke les documents indexés avec leurs métadonnées.

```php
Schema::create('indexed_documents', function (Blueprint $table) {
    $table->id();
    $table->string('fingerprint')->unique();          // "App.Models.User|123"
    $table->string('namespace');                      // "App.Models.User"
    $table->string('entity_id');                      // "123"
    $table->json('cluster');                          // {"model":"User","tenant":"company_abc","env":"production"}
    $table->json('data');                             // {"name":"John Doe","description":"Software Developer"}
    $table->json('fields');                           // ["name", "description"]
    $table->timestamps();
    
    $table->index(['namespace', 'entity_id']);
    $table->index('namespace');
});
```

#### Table `indexed_tokens`

Stocke tous les tokens générés pour chaque document.

```php
Schema::create('indexed_tokens', function (Blueprint $table) {
    $table->id();
    $table->foreignId('document_id')->constrained('indexed_documents')->onDelete('cascade');
    $table->enum('token_type', ['lexical', 'metaphone']);
    $table->string('token');                          // "john", "jo", "JN"
    $table->char('first_letter', 1);                  // "j"
    $table->string('field')->nullable();              // "name"
    $table->string('cluster_key')->nullable();        // "model"
    $table->string('cluster_value')->nullable();      // "User"
    $table->string('namespace');                      // "App.Models.User"
    $table->timestamps();
    
    // Index pour les recherches rapides
    $table->index(['token', 'field']);
    $table->index(['token', 'cluster_key', 'cluster_value']);
    $table->index(['token_type', 'token']);
    $table->index('namespace');
    $table->index('first_letter');
});
```

### 2.3 Modèles Eloquent

#### Modèle `IndexedDocument`

```php
<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Models;

use AndyDefer\LaravelHermes\Records\IndexableRecord;
use AndyDefer\LaravelHermes\ValueObjects\ClusterVO;
use AndyDefer\LaravelHermes\ValueObjects\IndexableFingerPrintVO;
use AndyDefer\DomainStructures\Utils\StrictAssociative;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class IndexedDocument extends Model
{
    protected $table = 'indexed_documents';

    protected $fillable = [
        'fingerprint',
        'namespace',
        'entity_id',
        'cluster',
        'data',
        'fields',
    ];

    protected $casts = [
        'cluster' => 'array',
        'data' => 'array',
        'fields' => 'array',
    ];

    public function tokens(): HasMany
    {
        return $this->hasMany(IndexedToken::class, 'document_id');
    }

    public function toIndexableRecord(): IndexableRecord
    {
        return new IndexableRecord(
            finger_print: new IndexableFingerPrintVO($this->fingerprint),
            cluster: new ClusterVO($this->cluster['value'] ?? ''),
            data: StrictAssociative::from($this->data),
        );
    }

    public function getFingerPrintVO(): IndexableFingerPrintVO
    {
        return new IndexableFingerPrintVO($this->fingerprint);
    }

    public function getClusterVO(): ClusterVO
    {
        return new ClusterVO($this->cluster['value'] ?? '');
    }
}
```

#### Modèle `IndexedToken`

```php
<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Models;

use AndyDefer\LaravelHermes\Enums\GramType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class IndexedToken extends Model
{
    protected $table = 'indexed_tokens';

    protected $fillable = [
        'document_id',
        'token_type',
        'token',
        'first_letter',
        'field',
        'cluster_key',
        'cluster_value',
        'namespace',
    ];

    protected $casts = [
        'token_type' => 'string',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(IndexedDocument::class, 'document_id');
    }

    public function getGramType(): GramType
    {
        return GramType::from($this->token_type);
    }
}
```

---

## 3. Processus d'indexation

### 3.1 Étapes

```
1. Réception d'un IndexableRecord
    ↓
2. Sauvegarde du document
   → indexed_documents table
    ↓
3. Pour chaque champ de data
    ↓
4. Normalisation du texte
    ↓
5. Génération des tokens :
    ├── N-grammes (taille 2 à 4)
    └── Metaphone
    ↓
6. Pour chaque token :
    ├── Enregistrement dans indexed_tokens
    │   ├── token_type (lexical/metaphone)
    │   ├── token (valeur)
    │   ├── field (champ concerné)
    │   └── namespace (pour isolation)
    ↓
7. Pour chaque cluster :
    ├── Enregistrement dans indexed_tokens
    │   ├── cluster_key
    │   └── cluster_value
```

### 3.2 Exemple d'indexation

**Donnée :**
```php
$record = new IndexableRecord(
    finger_print: new IndexableFingerPrintVO('App.Models.User|123'),
    cluster: new ClusterVO('model-User|tenant-company_abc|env-production'),
    data: StrictAssociative::from([
        'name' => 'John Doe',
        'description' => 'Software Developer'
    ])
);
```

**Enregistrement dans `indexed_documents` :**
```sql
INSERT INTO indexed_documents VALUES (
    fingerprint: 'App.Models.User|123',
    namespace: 'App.Models.User',
    entity_id: '123',
    cluster: '{"model":"User","tenant":"company_abc","env":"production"}',
    data: '{"name":"John Doe","description":"Software Developer"}',
    fields: '["name", "description"]'
);
```

**Enregistrement des tokens dans `indexed_tokens` :**

| token_type | token | first_letter | field | namespace |
|------------|-------|--------------|-------|-----------|
| lexical | jo | j | name | App.Models.User |
| lexical | john | j | name | App.Models.User |
| lexical | oh | o | name | App.Models.User |
| lexical | hn | h | name | App.Models.User |
| lexical | so | s | description | App.Models.User |
| lexical | soft | s | description | App.Models.User |
| lexical | of | o | description | App.Models.User |
| lexical | ft | f | description | App.Models.User |
| lexical | tw | t | description | App.Models.User |
| lexical | wa | w | description | App.Models.User |
| lexical | ar | a | description | App.Models.User |
| lexical | re | r | description | App.Models.User |
| lexical | el | e | description | App.Models.User |
| lexical | lo | l | description | App.Models.User |
| lexical | op | o | description | App.Models.User |
| lexical | pe | p | description | App.Models.User |
| lexical | er | e | description | App.Models.User |
| metaphone | JN | J | name | App.Models.User |
| metaphone | SFTWR | S | description | App.Models.User |

---

## 4. Processus de recherche

### 4.1 Recherche simple

```sql
-- Recherche : "john"
SELECT DISTINCT document_id 
FROM indexed_tokens 
WHERE token = 'john';

-- Résultat : [123, 456]
-- Complexité : O(k) où k = nombre de résultats
```

**En Eloquent :**
```php
$documentIds = IndexedToken::where('token', 'john')
    ->pluck('document_id')
    ->toArray();
```

### 4.2 Recherche avec filtre champ

```sql
-- Recherche : "john" dans "name"
SELECT DISTINCT document_id 
FROM indexed_tokens 
WHERE token = 'john' 
  AND field = 'name';

-- Résultat : [123, 456]
-- Complexité : O(k) où k = nombre de résultats
```

**En Eloquent :**
```php
$documentIds = IndexedToken::where('token', 'john')
    ->where('field', 'name')
    ->pluck('document_id')
    ->toArray();
```

### 4.3 Recherche avec filtre cluster

```sql
-- Recherche : "john" ET cluster "model-User"
SELECT DISTINCT document_id 
FROM indexed_tokens 
WHERE token = 'john' 
  AND cluster_key = 'model' 
  AND cluster_value = 'User';

-- Résultat : [123, 456]
-- Complexité : O(k) où k = nombre de résultats
```

**En Eloquent :**
```php
$documentIds = IndexedToken::where('token', 'john')
    ->where('cluster_key', 'model')
    ->where('cluster_value', 'User')
    ->pluck('document_id')
    ->toArray();
```

### 4.4 Recherche multiple

```sql
-- Recherche : "john" AND "soft" AND cluster "model-User"
SELECT document_id 
FROM indexed_tokens 
WHERE token = 'john' 
INTERSECT
SELECT document_id 
FROM indexed_tokens 
WHERE token = 'soft' 
INTERSECT
SELECT document_id 
FROM indexed_tokens 
WHERE cluster_key = 'model' 
  AND cluster_value = 'User';

-- Résultat : [123]
-- Complexité : O(k1 + k2 + k3)
```

**En Eloquent :**
```php
$ids1 = IndexedToken::where('token', 'john')->pluck('document_id');
$ids2 = IndexedToken::where('token', 'soft')->pluck('document_id');
$ids3 = IndexedToken::where('cluster_key', 'model')
    ->where('cluster_value', 'User')
    ->pluck('document_id');

$finalIds = $ids1->intersect($ids2)->intersect($ids3);
```

### 4.5 Recherche avec limite

```php
// Recherche avec limite
$documentIds = IndexedToken::where('token', 'john')
    ->pluck('document_id')
    ->take($query->limit)
    ->toArray();
```

---

## 5. Requêtes optimisées

### 5.1 Recherche avec tous les filtres

```php
public function search(SearchQueryRecord $query): IndexableSearchResultCollection
{
    $tokenQuery = IndexedToken::query();
    
    // Filtrer par n-gram
    $tokenQuery->where('token', $query->query->getNgrams()[0] ?? '');
    
    // Filtrer par champ
    if ($fields = $query->query->getFieldsForNgram($query->query->getNgrams()[0] ?? '')) {
        $tokenQuery->whereIn('field', $fields);
    }
    
    // Filtrer par namespace
    if ($query->finger_print) {
        $tokenQuery->where('namespace', $query->finger_print->getNamespace());
    }
    
    // Filtrer par cluster
    if ($query->cluster) {
        foreach ($query->cluster->all() as $key => $value) {
            $tokenQuery->where(function ($q) use ($key, $value) {
                $q->where('cluster_key', $key)
                  ->where('cluster_value', $value);
            });
        }
    }
    
    // Récupérer les IDs avec limite
    $documentIds = $tokenQuery
        ->pluck('document_id')
        ->take($query->limit ?? config('indexer.default_limit', 100))
        ->toArray();
    
    // Charger les documents
    $documents = IndexedDocument::whereIn('id', $documentIds)->get();
    
    // Construire les résultats
    $results = new IndexableSearchResultCollection();
    foreach ($documents as $document) {
        $results->add(new IndexableSearchResultRecord(
            item: $document->toIndexableRecord(),
            field: '', // À déterminer selon le match
            gram_value: '', // À déterminer selon le match
            gram_type: GramType::LEXICAL,
        ));
    }
    
    return $results;
}
```

---

## 6. Avantages de l'approche SQL + Eloquent

| Aspect | Fichiers JSON | SQL + Eloquent |
|--------|---------------|----------------|
| **Scalabilité** | Limitée par le nombre de fichiers | ✅ Supporte des millions d'enregistrements |
| **Indexation** | Manuelle (dossiers) | ✅ Index automatiques |
| **Requêtes complexes** | Difficiles | ✅ Faciles avec Eloquent |
| **Transactions** | Non | ✅ Supportées |
| **Intégrité** | Manuelle | ✅ Clés étrangères |
| **Performance** | Bonne pour O(k) | ✅ Excellente avec indexes |
| **Maintenance** | Difficile | ✅ Facile |
| **Backup** | Manuelle | ✅ Intégré à la base de données |
| **Concurrence** | Risques de verrouillage | ✅ Gérée par le SGBD |
| **Recherches avancées** | Limitées | ✅ Possibilité de FULLTEXT |

---

## 7. Index recommandés

```sql
-- Index pour les recherches par token + champ
CREATE INDEX idx_token_field ON indexed_tokens (token, field);

-- Index pour les recherches par token + cluster
CREATE INDEX idx_token_cluster ON indexed_tokens (token, cluster_key, cluster_value);

-- Index pour les recherches par type + token
CREATE INDEX idx_type_token ON indexed_tokens (token_type, token);

-- Index pour les recherches par namespace
CREATE INDEX idx_namespace ON indexed_tokens (namespace);

-- Index pour les recherches par première lettre
CREATE INDEX idx_first_letter ON indexed_tokens (first_letter);

-- Index composite pour les documents
CREATE INDEX idx_namespace_entity ON indexed_documents (namespace, entity_id);
```

---

## 8. Schéma récapitulatif

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                               INDEXATION                                   │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  IndexableRecord → Normalisation → Tokenisation → Stockage SQL             │
│                                                                             │
│  indexed_documents:                                                         │
│  └── fingerprint, namespace, entity_id, cluster, data, fields              │
│                                                                             │
│  indexed_tokens:                                                            │
│  └── document_id, token_type, token, first_letter, field,                  │
│      cluster_key, cluster_value, namespace                                 │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
                                       ↓
┌─────────────────────────────────────────────────────────────────────────────┐
│                               RECHERCHE                                    │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  1. Query Eloquent → Index utilisé → O(1)                                  │
│  2. Récupération des IDs → O(k)                                            │
│  3. Chargement des documents → O(k)                                        │
│  4. Retour des résultats → O(1)                                            │
│                                                                             │
│  Avec filtre champ :                                                        │
│  1. Query + WHERE field = 'name' → O(1)                                    │
│  2. Récupération des IDs → O(k)                                            │
│  3. Chargement des documents → O(k)                                        │
│                                                                             │
│  Avec filtre cluster :                                                      │
│  1. Query + WHERE cluster_key = 'model' → O(1)                             │
│  2. Récupération des IDs → O(k)                                            │
│  3. Chargement des documents → O(k)                                        │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 9. Exemple complet d'utilisation

```php
<?php

use AndyDefer\LaravelHermes\Services\IndexerService;
use AndyDefer\LaravelHermes\Records\IndexableRecord;
use AndyDefer\LaravelHermes\Records\SearchQueryRecord;
use AndyDefer\LaravelHermes\ValueObjects\IndexableFingerPrintVO;
use AndyDefer\LaravelHermes\ValueObjects\ClusterVO;
use AndyDefer\LaravelHermes\ValueObjects\SearchQueryVO;
use AndyDefer\DomainStructures\Utils\StrictAssociative;

// 1. Indexation
$record = new IndexableRecord(
    finger_print: new IndexableFingerPrintVO('App.Models.User|123'),
    cluster: new ClusterVO('model-User|tenant-company_abc|env-production'),
    data: StrictAssociative::from([
        'name' => 'John Doe',
        'description' => 'Software Developer'
    ])
);

$indexer = new IndexerService();
$indexer->index($record);

// 2. Recherche
$query = new SearchQueryRecord(
    query: new SearchQueryVO('john=name,description'),
    limit: 50
);

$results = $indexer->search($query);

// 3. Affichage des résultats
foreach ($results as $result) {
    echo $result->item->finger_print->getId(); // '123'
    echo $result->item->data['name']; // 'John Doe'
}
```

---

## 10. Conclusion

L'approche SQL + Eloquent offre :

1. ✅ **Scalabilité** : Supporte des millions de documents
2. ✅ **Performance** : Index automatiques pour des recherches rapides
3. ✅ **Maintenance** : Facile à gérer et à maintenir
4. ✅ **Intégrité** : Transactions et clés étrangères
5. ✅ **Flexibilité** : Possibilité d'ajouter des fonctionnalités avancées
6. ✅ **Professionnalisme** : Approche standard et éprouvée

La complexité reste en **O(k)** où `k` est le nombre de résultats, grâce aux index et à l'optimisation des requêtes Eloquent.