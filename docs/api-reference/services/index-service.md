# IndexerService - Référence Technique

## Description

Service orchestrateur principal qui expose l'API publique d'indexation en déléguant les opérations à des composants spécialisés.

## Hiérarchie / Implémentations

```
IndexerInterface
    └── IndexerService (final)
            ├── IndexWriter (indexation)
            ├── IndexDeleter (suppression)
            └── IndexSearcher (recherche)
```

## Rôle principal

Agit comme une **façade** (Facade Pattern) qui :

- Centralise l'API publique d'indexation
- Orchestre les opérations complexes (ex: refresh)
- Délègue les responsabilités à des composants spécialisés
- Fournit un point d'entrée unique pour les utilisateurs du package

## API / Méthodes publiques

### `__construct(IndexWriter $writer, IndexDeleter $deleter, IndexSearcher $searcher)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$writer` | `IndexWriter` | Service d'écriture/indexation |
| `$deleter` | `IndexDeleter` | Service de suppression |
| `$searcher` | `IndexSearcher` | Service de recherche |

**Retourne :** `void`

**Exemple :**
```php
$indexer = new IndexerService(
    new IndexWriter(...),
    new IndexDeleter(...),
    new IndexSearcher(...)
);
```

---

### `index(IndexableRecord $entity): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$entity` | `IndexableRecord` | L'enregistrement à indexer |

**Retourne :** `void`

**Exceptions :** `QueryException` (PDO), `InvalidArgumentException`

**Exemple :**
```php
$record = IndexableRecordFactory::convert($user, $cluster);
$indexer->index($record);
```

---

### `indexMany(IndexableRecordCollection $records): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$records` | `IndexableRecordCollection` | Collection d'enregistrements à indexer |

**Retourne :** `void`

**Exceptions :** `QueryException` (PDO)

**Exemple :**
```php
$records = new IndexableRecordCollection();
$records->add($record1);
$records->add($record2);

$indexer->indexMany($records);
```

---

### `delete(IndexableFingerPrintVO $fingerPrint): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$fingerPrint` | `IndexableFingerPrintVO` | Le fingerprint du document à supprimer |

**Retourne :** `void`

**Exceptions :** Aucune

**Exemple :**
```php
$fingerPrint = new IndexableFingerPrintVO('App.Models.User|123');
$indexer->delete($fingerPrint);
```

---

### `deleteMany(IndexableFingerPrintVOCollection $fingerPrints): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$fingerPrints` | `IndexableFingerPrintVOCollection` | Collection de fingerprints à supprimer |

**Retourne :** `void`

**Exceptions :** Aucune

**Exemple :**
```php
$collection = new IndexableFingerPrintVOCollection();
$collection->add(new IndexableFingerPrintVO('App.Models.User|123'));
$collection->add(new IndexableFingerPrintVO('App.Models.User|456'));

$indexer->deleteMany($collection);
```

---

### `clear(): void`

**Retourne :** `void`

**Exceptions :** Aucune

**Exemple :**
```php
// Vide complètement l'index
$indexer->clear();
```

---

### `exists(IndexableFingerPrintVO $fingerPrint): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$fingerPrint` | `IndexableFingerPrintVO` | Le fingerprint à vérifier |

**Retourne :** `bool` - `true` si le document existe, `false` sinon

**Exemple :**
```php
$fingerPrint = new IndexableFingerPrintVO('App.Models.User|123');

if ($indexer->exists($fingerPrint)) {
    echo "Le document existe\n";
}
```

---

### `search(SearchQueryRecord $query): IndexableSearchResultCollection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$query` | `SearchQueryRecord` | La requête de recherche |

**Retourne :** `IndexableSearchResultCollection<IndexableSearchResultRecord>` - Collection des résultats

**Exemple :**
```php
$query = new SearchQueryRecord(
    query: new SearchQueryVO('john=name'),
    limit: 50
);

$results = $indexer->search($query);

foreach ($results as $result) {
    echo $result->item->finger_print->getValue(); // 'App.Models.User|123'
}
```

---

### `refresh(IndexableRecord $entity): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$entity` | `IndexableRecord` | L'enregistrement à rafraîchir |

**Retourne :** `void`

**Exceptions :** `QueryException` (PDO)

**Exemple :**
```php
// Met à jour un document existant
$record = IndexableRecordFactory::convert($updatedUser, $cluster);
$indexer->refresh($record);
// = delete($old) + index($new)
```

---

### `refreshMany(IndexableRecordCollection $records): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$records` | `IndexableRecordCollection` | Collection d'enregistrements à rafraîchir |

**Retourne :** `void`

**Exceptions :** `QueryException` (PDO)

**Exemple :**
```php
$records = new IndexableRecordCollection();
$records->add($updatedRecord1);
$records->add($updatedRecord2);

$indexer->refreshMany($records);
// = deleteMany(anciens) + indexMany(nouveaux)
```

## Cas d'utilisation

### Cas 1 : Cycle de vie complet d'un document

```php
<?php

$indexer = app(IndexerService::class);

// 1. Indexation
$user = User::find(123);
$cluster = new ClusterVO('model:User|tenant:company_abc');
$record = IndexableRecordFactory::convert($user, $cluster);
$indexer->index($record);

// 2. Vérification d'existence
$fingerPrint = new IndexableFingerPrintVO('App.Models.User|123');
if ($indexer->exists($fingerPrint)) {
    echo "L'utilisateur est indexé\n";
}

// 3. Recherche
$query = new SearchQueryRecord(
    query: new SearchQueryVO('john=name')
);
$results = $indexer->search($query);

// 4. Mise à jour (refresh)
$user->name = 'John Updated';
$updatedRecord = IndexableRecordFactory::convert($user, $cluster);
$indexer->refresh($updatedRecord);

// 5. Suppression
$indexer->delete($fingerPrint);
```

### Cas 2 : Indexation en masse avec refresh

```php
<?php

// Mise à jour massive des utilisateurs
$users = User::where('updated_at', '>', now()->subDay())->get();
$cluster = new ClusterVO('model:User|tenant:company_abc');

$records = new IndexableRecordCollection();
foreach ($users as $user) {
    $records->add(IndexableRecordFactory::convert($user, $cluster));
}

// Rafraîchir tous les documents modifiés
$indexer->refreshMany($records);
```

### Cas 3 : Nettoyage d'un tenant

```php
<?php

$tenant = 'company_xyz';

// 1. Récupérer tous les documents du tenant
$documentRepo = new IndexedDocumentRepository();
$documents = $documentRepo->findByClusterKeyValue('tenant', $tenant);

// 2. Construire la collection de fingerprints
$fingerPrints = new IndexableFingerPrintVOCollection();
foreach ($documents as $doc) {
    $fingerPrints->add($doc->getFingerPrintVO());
}

// 3. Supprimer tous les documents du tenant
$indexer->deleteMany($fingerPrints);

// Ou simplement vider tout l'index
$indexer->clear();
```

## Flux d'exécution

### Refresh d'un document unique

```
refresh(IndexableRecord $entity)
    ↓
delete($entity->finger_print)  ──→ IndexDeleter
    ↓
index($entity)                  ──→ IndexWriter
```

### Refresh multiple

```
refreshMany(IndexableRecordCollection $records)
    ↓
Collectionner tous les fingerprints
    ↓
deleteMany($fingerPrints)       ──→ IndexDeleter
    ↓
indexMany($records)             ──→ IndexWriter
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Document invalide | `ModelNotFoundException` | Pas d'exception native |
| Erreur PDO | `QueryException` | Erreur de base de données |
| Query invalide | `InvalidArgumentException` | `Search query cannot be empty` |

## Performance

| Opération | Complexité | Notes |
|-----------|------------|-------|
| `index()` | O(n) | n = nombre de tokens |
| `indexMany()` | O(n) | n = nombre total de tokens |
| `delete()` | O(log n) | Suppression par clé unique |
| `search()` | O(log n + k) | k = nombre de résultats |
| `refresh()` | O(n) | Delete + index |
| `refreshMany()` | O(n) | DeleteMany + indexMany |

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

use AndyDefer\LaravelHermes\Services\IndexerService;
use AndyDefer\LaravelHermes\Records\IndexableRecord;
use AndyDefer\LaravelHermes\Records\SearchQueryRecord;
use AndyDefer\LaravelHermes\ValueObjects\SearchQueryVO;
use AndyDefer\LaravelHermes\ValueObjects\ClusterVO;
use AndyDefer\LaravelHermes\ValueObjects\IndexableFingerPrintVO;
use AndyDefer\LaravelHermes\ValueObjects\IndexableRecord;
use AndyDefer\DomainStructures\Utils\StrictAssociative;

$indexer = app(IndexerService::class);

// 1. Indexer un utilisateur
$record = new IndexableRecord(
    finger_print: new IndexableFingerPrintVO('App.Models.User|123'),
    cluster: new ClusterVO('model:User|tenant:company_abc'),
    data: StrictAssociative::from([
        'name' => 'John Doe',
        'email' => 'john@example.com'
    ])
);
$indexer->index($record);

// 2. Rechercher
$query = new SearchQueryRecord(
    query: new SearchQueryVO('john=name'),
    limit: 10
);
$results = $indexer->search($query);

foreach ($results as $result) {
    echo $result->item->finger_print->getId() . "\n";
    echo $result->field . "\n";
    echo $result->gram_value . "\n";
}

// 3. Vérifier l'existence
$fingerPrint = new IndexableFingerPrintVO('App.Models.User|123');
if ($indexer->exists($fingerPrint)) {
    echo "Le document existe\n";
}

// 4. Mettre à jour
$updatedRecord = new IndexableRecord(
    finger_print: new IndexableFingerPrintVO('App.Models.User|123'),
    cluster: new ClusterVO('model:User|tenant:company_abc'),
    data: StrictAssociative::from([
        'name' => 'John Updated',
        'email' => 'john.updated@example.com'
    ])
);
$indexer->refresh($updatedRecord);

// 5. Supprimer
$indexer->delete($fingerPrint);

// 6. Nettoyer tout
$indexer->clear();
```

## Voir aussi

- `IndexWriter` - Service d'indexation
- `IndexDeleter` - Service de suppression
- `IndexSearcher` - Service de recherche
- `IndexerInterface` - Interface du service
- `IndexableRecord` - Record d'entrée
- `SearchQueryRecord` - Record de requête
- `IndexableFingerPrintVO` - Value Object pour les fingerprints