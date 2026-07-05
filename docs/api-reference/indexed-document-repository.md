## `IndexedDocumentRepository` - Référence Technique

# IndexedDocumentRepository - Référence Technique

## Description

Repository gérant les opérations CRUD et les requêtes spécialisées pour les documents indexés dans la base de données.

## Hiérarchie / Implémentations

```
AbstractRepository<IndexedDocument, IndexedDocumentRecord>
    └── IndexedDocumentRepository
        └── IndexedDocumentRepositoryInterface
```

## Rôle principal

Fournit une couche d'abstraction pour l'accès aux documents indexés, avec des méthodes spécialisées pour :

- Recherche par fingerprint, namespace, cluster
- Opérations de suppression groupées
- Bulk insertion pour l'indexation massive
- Récupération des métadonnées (clusters distincts, namespaces)

## API / Méthodes publiques

### `__construct()`

**Retourne :** `void`

**Exemple :**
```php
$repository = new IndexedDocumentRepository();
```

---

### `applyFilters(Builder $query, AbstractRecord $filters): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$query` | `Builder` | La requête Eloquent à filtrer |
| `$filters` | `AbstractRecord` | Les filtres à appliquer (doit être `IndexedDocumentFiltersRecord`) |

**Retourne :** `void`

**Exemple :**
```php
$filters = new IndexedDocumentFiltersRecord(
    namespace: 'App.Models.User',
    cluster: new ClusterVO('model:User')
);

$query = IndexedDocument::query();
$repository->applyFilters($query, $filters);
$results = $query->get();
```

---

### `getModel(): Model`

**Retourne :** `Model` - L'instance du modèle Eloquent

**Exemple :**
```php
$model = $repository->getModel();
$count = $model->newQuery()->count();
```

---

### `createMany(array $records): array`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$records` | `array<IndexedDocumentRecord>` | Tableau de records à insérer |

**Retourne :** `array<IndexedDocument>` - Les documents créés

**Exceptions :** Aucune (erreurs PDO possibles)

**Exemple :**
```php
$records = [
    new IndexedDocumentRecord(
        fingerprint: 'App.Models.User|1',
        cluster: 'model:User',
        data: ['name' => 'John']
    ),
    new IndexedDocumentRecord(
        fingerprint: 'App.Models.User|2',
        cluster: 'model:User',
        data: ['name' => 'Jane']
    ),
];

$documents = $repository->createMany($records);
```

---

### `findByFingerPrint(IndexableFingerPrintVO $fingerPrint): ?IndexedDocument`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$fingerPrint` | `IndexableFingerPrintVO` | Fingerprint du document |

**Retourne :** `IndexedDocument|null` - Le document trouvé ou `null`

**Exemple :**
```php
$fingerPrint = new IndexableFingerPrintVO('App.Models.User|123');
$document = $repository->findByFingerPrint($fingerPrint);
```

---

### `findByFingerprintString(string $fingerprint): ?IndexedDocument`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$fingerprint` | `string` | Fingerprint brut |

**Retourne :** `IndexedDocument|null` - Le document trouvé ou `null`

**Exemple :**
```php
$document = $repository->findByFingerprintString('App.Models.User|123');
```

---

### `findByNamespace(string $namespace): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$namespace` | `string` | Namespace à filtrer |

**Retourne :** `Collection<int, IndexedDocument>` - Collection des documents

**Exemple :**
```php
$documents = $repository->findByNamespace('App.Models.User');
```

---

### `findByCluster(ClusterVO $cluster): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$cluster` | `ClusterVO` | Cluster à filtrer |

**Retourne :** `Collection<int, IndexedDocument>` - Collection des documents

**Exemple :**
```php
$cluster = new ClusterVO('model:User|tenant:company_abc');
$documents = $repository->findByCluster($cluster);
```

---

### `findByClusterKeyValue(string $key, string $value): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé du cluster |
| `$value` | `string` | Valeur du cluster |

**Retourne :** `Collection<int, IndexedDocument>` - Collection des documents

**Exemple :**
```php
$documents = $repository->findByClusterKeyValue('model', 'User');
```

---

### `findByIds(array $ids): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$ids` | `array<string>` | Liste des UUIDs des documents |

**Retourne :** `Collection<int, IndexedDocument>` - Collection des documents

**Exemple :**
```php
$ids = ['550e8400-e29b-41d4-a716-446655440000', '550e8400-e29b-41d4-a716-446655440001'];
$documents = $repository->findByIds($ids);
```

---

### `deleteByFingerPrint(IndexableFingerPrintVO $fingerPrint): int`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$fingerPrint` | `IndexableFingerPrintVO` | Fingerprint du document à supprimer |

**Retourne :** `int` - Nombre de lignes supprimées (0 ou 1)

**Exemple :**
```php
$fingerPrint = new IndexableFingerPrintVO('App.Models.User|123');
$count = $repository->deleteByFingerPrint($fingerPrint);
```

---

### `deleteByFingerprintString(string $fingerprint): int`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$fingerprint` | `string` | Fingerprint brut du document à supprimer |

**Retourne :** `int` - Nombre de lignes supprimées

**Exemple :**
```php
$count = $repository->deleteByFingerprintString('App.Models.User|123');
```

---

### `deleteByNamespace(string $namespace): int`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$namespace` | `string` | Namespace à supprimer |

**Retourne :** `int` - Nombre de lignes supprimées

**Exemple :**
```php
$count = $repository->deleteByNamespace('App.Models.User');
```

---

### `deleteByCluster(ClusterVO $cluster): int`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$cluster` | `ClusterVO` | Cluster à supprimer |

**Retourne :** `int` - Nombre de lignes supprimées

**Exemple :**
```php
$cluster = new ClusterVO('model:User|tenant:company_abc');
$count = $repository->deleteByCluster($cluster);
```

---

### `deleteByClusterKeyValue(string $key, string $value): int`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé du cluster |
| `$value` | `string` | Valeur du cluster |

**Retourne :** `int` - Nombre de lignes supprimées

**Exemple :**
```php
$count = $repository->deleteByClusterKeyValue('model', 'User');
```

---

### `countByNamespace(string $namespace): int`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$namespace` | `string` | Namespace à compter |

**Retourne :** `int` - Nombre de documents

**Exemple :**
```php
$count = $repository->countByNamespace('App.Models.User');
```

---

### `countByCluster(ClusterVO $cluster): int`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$cluster` | `ClusterVO` | Cluster à compter |

**Retourne :** `int` - Nombre de documents

**Exemple :**
```php
$cluster = new ClusterVO('model:User');
$count = $repository->countByCluster($cluster);
```

---

### `getDistinctNamespaces(): Collection`

**Retourne :** `Collection<int, string>` - Liste des namespaces uniques

**Exemple :**
```php
$namespaces = $repository->getDistinctNamespaces();
// ['App.Models.User', 'App.Models.Product']
```

---

### `getDistinctClusterKeys(): Collection`

**Retourne :** `Collection<int, string>` - Liste des clés de cluster uniques

**Exemple :**
```php
$keys = $repository->getDistinctClusterKeys();
// ['model', 'tenant', 'env', 'category']
```

---

### `getDistinctClusterValues(string $key): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | Clé du cluster |

**Retourne :** `Collection<int, string>` - Liste des valeurs uniques pour une clé donnée

**Exemple :**
```php
$values = $repository->getDistinctClusterValues('model');
// ['User', 'Product', 'Order']
```

---

### `existsByFingerPrint(IndexableFingerPrintVO $fingerPrint): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$fingerPrint` | `IndexableFingerPrintVO` | Fingerprint à vérifier |

**Retourne :** `bool` - `true` si le document existe, `false` sinon

**Exemple :**
```php
$fingerPrint = new IndexableFingerPrintVO('App.Models.User|123');
if ($repository->existsByFingerPrint($fingerPrint)) {
    // Le document existe
}
```

---

### `existsByNamespace(string $namespace): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$namespace` | `string` | Namespace à vérifier |

**Retourne :** `bool` - `true` si au moins un document existe

**Exemple :**
```php
if ($repository->existsByNamespace('App.Models.User')) {
    // Des utilisateurs existent
}
```

---

### `existsByCluster(ClusterVO $cluster): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$cluster` | `ClusterVO` | Cluster à vérifier |

**Retourne :** `bool` - `true` si au moins un document existe

**Exemple :**
```php
$cluster = new ClusterVO('model:User');
if ($repository->existsByCluster($cluster)) {
    // Des utilisateurs existent
}
```

---

### `findAllWithTokens(): Collection`

**Retourne :** `Collection<int, IndexedDocument>` - Tous les documents avec leurs tokens préchargés

**Exemple :**
```php
$documents = $repository->findAllWithTokens();
foreach ($documents as $document) {
    $tokens = $document->tokens;
}
```

---

## Cas d'utilisation

### Cas 1 : Indexation en masse

```php
$records = [];
for ($i = 1; $i <= 1000; $i++) {
    $records[] = new IndexedDocumentRecord(
        fingerprint: 'App.Models.User|' . $i,
        cluster: 'model:User|tenant:company_abc',
        data: ['name' => 'User ' . $i]
    );
}

$documents = $repository->createMany($records);
// 1 requête SQL pour 1000 documents
```

### Cas 2 : Nettoyage d'un tenant

```php
$tenant = 'company_xyz';
$cluster = new ClusterVO('tenant:' . $tenant);

// Compter avant suppression
$count = $repository->countByCluster($cluster);
echo "Suppression de $count documents\n";

// Supprimer
$repository->deleteByCluster($cluster);
```

### Cas 3 : Exploration des données

```php
// Quels types de modèles sont indexés ?
$namespaces = $repository->getDistinctNamespaces();

// Quelles sont les catégories ?
$categories = $repository->getDistinctClusterValues('category');

// Quels tenants sont présents ?
$tenants = $repository->getDistinctClusterValues('tenant');
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Record invalide | `ModelNotFoundException` | Pas d'exception native (gérée par Laravel) |
| Insertion en base | `QueryException` | Erreur PDO (contraintes, syntaxe) |
| ID inexistant | `ModelNotFoundException` | `No query results for model` |

---

## Performance

| Opération | Complexité | Notes |
|-----------|------------|-------|
| `createMany()` | O(1) | 1 requête INSERT pour N documents |
| `findByFingerPrint()` | O(log n) | Index sur `fingerprint` |
| `findByNamespace()` | O(log n + k) | Index sur `fingerprint` + LIKE |
| `findByCluster()` | O(log n + k) | Index sur `cluster` |
| `getDistinct*()` | O(n) | Scan complet, à utiliser avec précaution |

---

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Complet |
| Laravel 10.x | ✅ Complet |
| Laravel 11.x | ✅ Complet |
| Laravel 12.x | ✅ Complet |

---

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelHermes\Repositories\IndexedDocumentRepository;
use AndyDefer\LaravelHermes\Records\IndexedDocumentRecord;
use AndyDefer\LaravelHermes\ValueObjects\ClusterVO;

$repository = new IndexedDocumentRepository();

// 1. Créer plusieurs documents
$records = [
    new IndexedDocumentRecord(
        fingerprint: 'App.Models.User|1',
        cluster: 'model:User|tenant:company_abc',
        data: ['name' => 'John Doe']
    ),
    new IndexedDocumentRecord(
        fingerprint: 'App.Models.User|2',
        cluster: 'model:User|tenant:company_abc',
        data: ['name' => 'Jane Smith']
    ),
    new IndexedDocumentRecord(
        fingerprint: 'App.Models.Product|1',
        cluster: 'model:Product|tenant:company_abc',
        data: ['name' => 'Laptop Pro']
    ),
];

$documents = $repository->createMany($records);

// 2. Rechercher par namespace
$users = $repository->findByNamespace('App.Models.User');
echo "Nombre d'utilisateurs : " . $users->count() . "\n";

// 3. Rechercher par cluster
$cluster = new ClusterVO('model:User');
$userDocs = $repository->findByCluster($cluster);

// 4. Vérifier l'existence
$fingerPrint = new IndexableFingerPrintVO('App.Models.User|1');
if ($repository->existsByFingerPrint($fingerPrint)) {
    echo "Le document existe\n";
}

// 5. Explorer les données
$namespaces = $repository->getDistinctNamespaces();
$keys = $repository->getDistinctClusterKeys();
$models = $repository->getDistinctClusterValues('model');

// 6. Nettoyer un tenant
$count = $repository->deleteByClusterKeyValue('tenant', 'company_abc');
echo "Supprimé $count documents\n";
```

## Voir aussi

- `IndexedTokenRepository` - Gestion des tokens
- `IndexableFingerPrintVO` - Value Object pour les fingerprints
- `ClusterVO` - Value Object pour les clusters
- `IndexedDocumentFiltersRecord` - Record de filtrage