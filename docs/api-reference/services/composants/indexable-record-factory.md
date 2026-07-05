# IndexableRecordFactory - Référence Technique

## Description

Fabrique statique qui transforme une entité `Indexable` en un `IndexedDocumentRecord` prêt à être persisté dans la base de données.

## Hiérarchie / Implémentations

```
IndexableRecordFactory (final)
    └── Méthode statique : convert()
```

## Rôle principal

Centralise la conversion des entités indexables en documents persistables. Cette fabrique est le point d'entrée pour toute opération d'indexation :

- Construction du fingerprint à partir du type et de l'ID de l'entité
- Passage du cluster obligatoire pour le filtrage
- Conservation des données indexables

## API / Méthodes publiques

### `convert(Indexable $entity, ClusterVO $cluster): IndexedDocumentRecord`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$entity` | `Indexable` | L'entité à convertir (doit implémenter l'interface `Indexable`) |
| `$cluster` | `ClusterVO` | Le cluster obligatoire pour le document (ex: `model:User|tenant:company_abc`) |

**Retourne :** `IndexedDocumentRecord` - Le record prêt à être persisté

**Exceptions :** Aucune

**Exemple :**
```php
$entity = new User();
$cluster = new ClusterVO('model:User|tenant:company_abc|env:production');

$record = IndexableRecordFactory::convert($entity, $cluster);

// Le record peut maintenant être indexé
$indexer->index($record);
```

## Cas d'utilisation

### Cas 1 : Indexation d'une entité unique

```php
<?php

use AndyDefer\LaravelHermes\Services\Composants\IndexableRecordFactory;
use AndyDefer\LaravelHermes\ValueObjects\ClusterVO;

$user = User::find(123);
$cluster = new ClusterVO('model:User|tenant:company_abc|env:production');

$record = IndexableRecordFactory::convert($user, $cluster);

$indexer->index($record);
```

### Cas 2 : Indexation en masse avec la même entité dans différents clusters

```php
<?php

$user = User::find(123);

// Indexer le même utilisateur dans différents tenants
$tenants = ['company_abc', 'company_xyz', 'company_def'];

foreach ($tenants as $tenant) {
    $cluster = new ClusterVO("model:User|tenant:{$tenant}|env:production");
    $record = IndexableRecordFactory::convert($user, $cluster);
    $indexer->index($record);
}
```

### Cas 3 : Indexation de plusieurs entités dans le même cluster

```php
<?php

$users = User::where('active', true)->get();
$cluster = new ClusterVO('model:User|tenant:company_abc|env:production');

$records = new IndexableRecordCollection();
foreach ($users as $user) {
    $records->add(IndexableRecordFactory::convert($user, $cluster));
}

$indexer->indexMany($records);
```

### Cas 4 : Construction du fingerprint

```php
<?php

// Pour une entité User avec id = 123 et morphClass = 'App\Models\User'
// Le fingerprint sera : "App\Models\User|123"

// Le fingerprint est composé de :
// - morphClass (namespace de l'entité)
// - key (ID de l'entité)
// Format : "{morphClass}|{key}"
```

## Flux d'exécution

```
┌─────────────────────────────────────────────────────────────────────────────┐
│ IndexableRecordFactory::convert(Indexable $entity, ClusterVO $cluster)    │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  1. $data = $entity->getIndexableData()                                    │
│     → Récupère les données à indexer (StrictAssociative)                   │
│                                                                             │
│  2. $key = $entity->getKey()                                               │
│     → Récupère l'identifiant unique (int|string)                           │
│                                                                             │
│  3. $morphClass = $entity->getMorphClass()                                 │
│     → Récupère le type (FQCN)                                              │
│                                                                             │
│  4. fingerprint = new IndexableFingerPrintVO($morphClass.'|'.$key)         │
│     → Construit l'identifiant unique du document                           │
│                                                                             │
│  5. return new IndexedDocumentRecord(                                      │
│         fingerprint: $fingerprint,                                         │
│         cluster: $cluster,                                                 │
│         data: $data                                                        │
│     )                                                                      │
│     → Retourne le record prêt à être persisté                              │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

## Intégration

### Avec `IndexWriter`

```php
$record = IndexableRecordFactory::convert($entity, $cluster);
$writer->index($record);
```

### Avec `IndexerService`

```php
$record = IndexableRecordFactory::convert($entity, $cluster);
$indexer->index($record);
```

### Avec `IndexableRecordCollection`

```php
$records = new IndexableRecordCollection();
$records->add(IndexableRecordFactory::convert($entity, $cluster));
$indexer->indexMany($records);
```

## Performance

| Aspect | Impact |
|--------|--------|
| **Création d'objets** | Minimale (quelques microsecondes) |
| **Mémoire** | Négligeable (quelques centaines d'octets) |
| **Temps d'exécution** | O(1) - constant |

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |
| PHP 7.4 | ❌ Non supporté (utilise `readonly`) |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelHermes\Services\Composants\IndexableRecordFactory;
use AndyDefer\LaravelHermes\Services\IndexerService;
use AndyDefer\LaravelHermes\ValueObjects\ClusterVO;
use AndyDefer\LaravelHermes\ValueObjects\IndexableFingerPrintVO;

// 1. Entité existante
$user = User::find(123);

// 2. Configuration du cluster
$cluster = new ClusterVO('model:User|tenant:company_abc|env:production');

// 3. Conversion
$record = IndexableRecordFactory::convert($user, $cluster);

// 4. Vérification du résultat
echo $record->fingerprint->getValue();   // 'App\Models\User|123'
echo $record->cluster->value;            // 'model:User|tenant:company_abc|env:production'
print_r($record->data->toArray());       // ['name' => 'John Doe', 'email' => ...]

// 5. Indexation
$indexer = new IndexerService();
$indexer->index($record);

// 6. Recherche
$query = new SearchQueryRecord(
    query: new SearchQueryVO('john=name'),
    cluster: $cluster
);
$results = $indexer->search($query);
```

## Voir aussi

- `Indexable` - Interface pour les entités indexables
- `IndexedDocumentRecord` - Record de document indexé
- `IndexableFingerPrintVO` - Value Object pour les fingerprints
- `ClusterVO` - Value Object pour les clusters
- `IndexWriter` - Service d'indexation
- `IndexerService` - Service principal d'indexation