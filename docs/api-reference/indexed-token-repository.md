# IndexedTokenRepository - Référence Technique

## Description

Repository gérant les opérations CRUD et les requêtes spécialisées pour les tokens indexés dans la base de données.

## Hiérarchie / Implémentations

```
AbstractRepository<IndexedToken, IndexedTokenRecord>
    └── IndexedTokenRepository
        └── IndexedTokenRepositoryInterface
```

## Rôle principal

Fournit une couche d'abstraction pour l'accès aux tokens indexés, avec des méthodes spécialisées pour :

- Recherche par token, type, champ, document, namespace, cluster
- Autocomplétion et suggestions
- Récupération des IDs de documents associés
- Gestion de la fréquence des tokens
- Opérations de suppression groupées
- Bulk insertion pour l'indexation massive

## API / Méthodes publiques

### `__construct()`

**Retourne :** `void`

**Exemple :**
```php
$repository = new IndexedTokenRepository();
```

---

### `applyFilters(Builder $query, AbstractRecord $filters): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$query` | `Builder` | La requête Eloquent à filtrer |
| `$filters` | `AbstractRecord` | Les filtres à appliquer (doit être `IndexedTokenFiltersRecord`) |

**Retourne :** `void`

**Exemple :**
```php
$filters = new IndexedTokenFiltersRecord(
    token: 'john',
    token_type: GramType::LEXICAL,
    field: 'name'
);

$query = IndexedToken::query();
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

### `findByToken(string $token): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$token` | `string` | La valeur du token à rechercher |

**Retourne :** `Collection<int, IndexedToken>` - Collection des tokens trouvés

**Exemple :**
```php
$tokens = $repository->findByToken('john');
```

---

### `findByType(GramType $type): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$type` | `GramType` | Le type de token (`LEXICAL` ou `METAPHONE`) |

**Retourne :** `Collection<int, IndexedToken>` - Collection des tokens trouvés

**Exemple :**
```php
$lexicalTokens = $repository->findByType(GramType::LEXICAL);
```

---

### `findByField(string $field): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$field` | `string` | Le nom du champ |

**Retourne :** `Collection<int, IndexedToken>` - Collection des tokens trouvés

**Exemple :**
```php
$tokens = $repository->findByField('name');
```

---

### `findByDocumentId(string $documentId): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$documentId` | `string` | L'UUID du document |

**Retourne :** `Collection<int, IndexedToken>` - Collection des tokens trouvés

**Exemple :**
```php
$tokens = $repository->findByDocumentId('550e8400-e29b-41d4-a716-446655440000');
```

---

### `findByDocumentFingerPrint(IndexableFingerPrintVO $fingerPrint): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$fingerPrint` | `IndexableFingerPrintVO` | Le fingerprint du document |

**Retourne :** `Collection<int, IndexedToken>` - Collection des tokens trouvés

**Exemple :**
```php
$fingerPrint = new IndexableFingerPrintVO('App.Models.User|123');
$tokens = $repository->findByDocumentFingerPrint($fingerPrint);
```

---

### `findByNamespace(string $namespace): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$namespace` | `string` | Le namespace à filtrer |

**Retourne :** `Collection<int, IndexedToken>` - Collection des tokens trouvés

**Exemple :**
```php
$tokens = $repository->findByNamespace('App.Models.User');
```

---

### `findByCluster(ClusterVO $cluster): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$cluster` | `ClusterVO` | Le cluster à filtrer |

**Retourne :** `Collection<int, IndexedToken>` - Collection des tokens trouvés

**Exemple :**
```php
$cluster = new ClusterVO('model:User|tenant:company_abc');
$tokens = $repository->findByCluster($cluster);
```

---

### `findByClusterKeyValue(string $key, string $value): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | La clé du cluster |
| `$value` | `string` | La valeur du cluster |

**Retourne :** `Collection<int, IndexedToken>` - Collection des tokens trouvés

**Exemple :**
```php
$tokens = $repository->findByClusterKeyValue('model', 'User');
```

---

### `findByTokenAndField(string $token, string $field): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$token` | `string` | La valeur du token |
| `$field` | `string` | Le nom du champ |

**Retourne :** `Collection<int, IndexedToken>` - Collection des tokens trouvés

**Exemple :**
```php
$tokens = $repository->findByTokenAndField('john', 'name');
```

---

### `findByTokenAndType(string $token, GramType $type): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$token` | `string` | La valeur du token |
| `$type` | `GramType` | Le type de token |

**Retourne :** `Collection<int, IndexedToken>` - Collection des tokens trouvés

**Exemple :**
```php
$tokens = $repository->findByTokenAndType('john', GramType::LEXICAL);
```

---

### `findByTokenAndNamespace(string $token, string $namespace): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$token` | `string` | La valeur du token |
| `$namespace` | `string` | Le namespace à filtrer |

**Retourne :** `Collection<int, IndexedToken>` - Collection des tokens trouvés

**Exemple :**
```php
$tokens = $repository->findByTokenAndNamespace('john', 'App.Models.User');
```

---

### `findByTokenAndCluster(string $token, ClusterVO $cluster): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$token` | `string` | La valeur du token |
| `$cluster` | `ClusterVO` | Le cluster à filtrer |

**Retourne :** `Collection<int, IndexedToken>` - Collection des tokens trouvés

**Exemple :**
```php
$cluster = new ClusterVO('model:User');
$tokens = $repository->findByTokenAndCluster('john', $cluster);
```

---

### `findByTokenFieldAndNamespace(string $token, string $field, string $namespace): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$token` | `string` | La valeur du token |
| `$field` | `string` | Le nom du champ |
| `$namespace` | `string` | Le namespace à filtrer |

**Retourne :** `Collection<int, IndexedToken>` - Collection des tokens trouvés

**Exemple :**
```php
$tokens = $repository->findByTokenFieldAndNamespace('john', 'name', 'App.Models.User');
```

---

### `autocomplete(string $prefix, ?int $limit = 10): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$prefix` | `string` | Le préfixe pour l'autocomplétion |
| `$limit` | `?int` | Nombre maximum de suggestions (défaut: 10) |

**Retourne :** `Collection<int, IndexedToken>` - Collection des suggestions distinctes

**Exemple :**
```php
$suggestions = $repository->autocomplete('jo', 5);
// ['john', 'joe', 'jordan']
```

---

### `startingWith(string $letter, ?int $limit = null): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$letter` | `string` | La lettre de début |
| `$limit` | `?int` | Nombre maximum de résultats |

**Retourne :** `Collection<int, IndexedToken>` - Collection des tokens trouvés

**Exemple :**
```php
$tokens = $repository->startingWith('j', 20);
```

---

### `getDocumentIdsForToken(string $token): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$token` | `string` | La valeur du token |

**Retourne :** `Collection<int, string>` - Collection des UUIDs des documents

**Exemple :**
```php
$documentIds = $repository->getDocumentIdsForToken('john');
```

---

### `getDocumentIdsForTokenAndField(string $token, string $field): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$token` | `string` | La valeur du token |
| `$field` | `string` | Le nom du champ |

**Retourne :** `Collection<int, string>` - Collection des UUIDs des documents

**Exemple :**
```php
$documentIds = $repository->getDocumentIdsForTokenAndField('john', 'name');
```

---

### `getDocumentIdsForTokenAndCluster(string $token, ClusterVO $cluster): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$token` | `string` | La valeur du token |
| `$cluster` | `ClusterVO` | Le cluster à filtrer |

**Retourne :** `Collection<int, string>` - Collection des UUIDs des documents

**Exemple :**
```php
$cluster = new ClusterVO('model:User');
$documentIds = $repository->getDocumentIdsForTokenAndCluster('john', $cluster);
```

---

### `getDocumentIdsForTokenFieldAndCluster(string $token, string $field, ClusterVO $cluster): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$token` | `string` | La valeur du token |
| `$field` | `string` | Le nom du champ |
| `$cluster` | `ClusterVO` | Le cluster à filtrer |

**Retourne :** `Collection<int, string>` - Collection des UUIDs des documents

**Exemple :**
```php
$cluster = new ClusterVO('model:User');
$documentIds = $repository->getDocumentIdsForTokenFieldAndCluster('john', 'name', $cluster);
```

---

### `findByTokenFieldAndDocument(string $token, string $field, string $documentId, GramType $tokenType): ?IndexedToken`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$token` | `string` | La valeur du token |
| `$field` | `string` | Le nom du champ |
| `$documentId` | `string` | L'UUID du document |
| `$tokenType` | `GramType` | Le type de token |

**Retourne :** `IndexedToken|null` - Le token trouvé ou `null`

**Exemple :**
```php
$token = $repository->findByTokenFieldAndDocument(
    'john',
    'name',
    '550e8400-e29b-41d4-a716-446655440000',
    GramType::LEXICAL
);
```

---

### `countDistinctTokens(): int`

**Retourne :** `int` - Nombre de tokens distincts

**Exemple :**
```php
$count = $repository->countDistinctTokens();
```

---

### `countByType(GramType $type): int`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$type` | `GramType` | Le type de token |

**Retourne :** `int` - Nombre de tokens

**Exemple :**
```php
$count = $repository->countByType(GramType::LEXICAL);
```

---

### `countByField(string $field): int`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$field` | `string` | Le nom du champ |

**Retourne :** `int` - Nombre de tokens

**Exemple :**
```php
$count = $repository->countByField('name');
```

---

### `countByNamespace(string $namespace): int`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$namespace` | `string` | Le namespace à filtrer |

**Retourne :** `int` - Nombre de tokens

**Exemple :**
```php
$count = $repository->countByNamespace('App.Models.User');
```

---

### `deleteByDocumentId(string $documentId): int`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$documentId` | `string` | L'UUID du document |

**Retourne :** `int` - Nombre de tokens supprimés

**Exemple :**
```php
$count = $repository->deleteByDocumentId('550e8400-e29b-41d4-a716-446655440000');
```

---

### `deleteByDocumentFingerPrint(IndexableFingerPrintVO $fingerPrint): int`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$fingerPrint` | `IndexableFingerPrintVO` | Le fingerprint du document |

**Retourne :** `int` - Nombre de tokens supprimés

**Exemple :**
```php
$fingerPrint = new IndexableFingerPrintVO('App.Models.User|123');
$count = $repository->deleteByDocumentFingerPrint($fingerPrint);
```

---

### `deleteByNamespace(string $namespace): int`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$namespace` | `string` | Le namespace à supprimer |

**Retourne :** `int` - Nombre de tokens supprimés

**Exemple :**
```php
$count = $repository->deleteByNamespace('App.Models.User');
```

---

### `deleteByCluster(ClusterVO $cluster): int`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$cluster` | `ClusterVO` | Le cluster à supprimer |

**Retourne :** `int` - Nombre de tokens supprimés

**Exemple :**
```php
$cluster = new ClusterVO('model:User');
$count = $repository->deleteByCluster($cluster);
```

---

### `deleteByClusterKeyValue(string $key, string $value): int`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$key` | `string` | La clé du cluster |
| `$value` | `string` | La valeur du cluster |

**Retourne :** `int` - Nombre de tokens supprimés

**Exemple :**
```php
$count = $repository->deleteByClusterKeyValue('model', 'User');
```

---

### `deleteByToken(string $token): int`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$token` | `string` | La valeur du token |

**Retourne :** `int` - Nombre de tokens supprimés

**Exemple :**
```php
$count = $repository->deleteByToken('john');
```

---

### `deleteByTokenAndField(string $token, string $field): int`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$token` | `string` | La valeur du token |
| `$field` | `string` | Le nom du champ |

**Retourne :** `int` - Nombre de tokens supprimés

**Exemple :**
```php
$count = $repository->deleteByTokenAndField('john', 'name');
```

---

### `getDistinctTokens(): Collection`

**Retourne :** `Collection<int, string>` - Liste des tokens distincts

**Exemple :**
```php
$tokens = $repository->getDistinctTokens();
// ['john', 'jane', 'doe', 'smith']
```

---

### `getDistinctFields(): Collection`

**Retourne :** `Collection<int, string>` - Liste des champs distincts

**Exemple :**
```php
$fields = $repository->getDistinctFields();
// ['name', 'email', 'description', 'bio']
```

---

### `incrementFrequency(string $id): int`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$id` | `string` | L'UUID du token |

**Retourne :** `int` - La nouvelle valeur de fréquence

**Exemple :**
```php
$newFrequency = $repository->incrementFrequency($token->id);
```

---

## Cas d'utilisation

### Cas 1 : Recherche de documents contenant un token

```php
// Trouver tous les documents contenant "john" dans le champ "name"
$documentIds = $repository->getDocumentIdsForTokenAndField('john', 'name');

$documents = IndexedDocument::whereIn('id', $documentIds)->get();
```

### Cas 2 : Autocomplétion pour une barre de recherche

```php
$query = 'joh';
$suggestions = $repository->autocomplete($query, 10);

// Afficher les suggestions
foreach ($suggestions as $suggestion) {
    echo $suggestion->token . "\n";
}
// john, johnson, johndoe, ...
```

### Cas 3 : Analyse de la couverture des tokens

```php
$totalTokens = $repository->countDistinctTokens();
$lexicalCount = $repository->countByType(GramType::LEXICAL);
$metaphoneCount = $repository->countByType(GramType::METAPHONE);

echo "Tokens distincts: $totalTokens\n";
echo "Lexicaux: $lexicalCount\n";
echo "Métaphones: $metaphoneCount\n";

$fields = $repository->getDistinctFields();
foreach ($fields as $field) {
    $count = $repository->countByField($field);
    echo "$field: $count tokens\n";
}
```

### Cas 4 : Nettoyage des tokens d'un tenant

```php
$tenant = 'company_xyz';
$cluster = new ClusterVO('tenant:' . $tenant);

$count = $repository->deleteByCluster($cluster);
echo "Supprimé $count tokens du tenant $tenant\n";
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Token inexistant | `ModelNotFoundException` | Pas d'exception native (retourne `null` ou collection vide) |
| Insertion en base | `QueryException` | Erreur PDO (contraintes, syntaxe) |
| ID inexistant | `ModelNotFoundException` | `No query results for model` |

---

## Performance

| Opération | Complexité | Notes |
|-----------|------------|-------|
| `findByToken()` | O(log n) | Index sur `token` |
| `autocomplete()` | O(log n + k) | Index sur `token` avec LIKE |
| `getDocumentIdsForToken()` | O(log n + k) | Index sur `token` et `document_id` |
| `incrementFrequency()` | O(1) | Mise à jour directe par ID |
| `deleteByCluster()` | O(log n + k) | Suppression via sous-requête |

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

use AndyDefer\LaravelHermes\Repositories\IndexedTokenRepository;
use AndyDefer\LaravelHermes\Enums\GramType;
use AndyDefer\LaravelHermes\ValueObjects\ClusterVO;

$repository = new IndexedTokenRepository();

// 1. Rechercher un token
$tokens = $repository->findByToken('john');

// 2. Filtrer par type et champ
$tokens = $repository->findByTokenAndField('john', 'name');
$tokens = $repository->findByTokenAndType('john', GramType::LEXICAL);

// 3. Autocomplétion
$suggestions = $repository->autocomplete('jo', 10);

// 4. Récupérer les documents associés
$documentIds = $repository->getDocumentIdsForToken('john');

// 5. Compter
$count = $repository->countDistinctTokens();
$countByName = $repository->countByField('name');

// 6. Incrémenter la fréquence
$token = $repository->findByToken('john')->first();
if ($token) {
    $newFrequency = $repository->incrementFrequency($token->id);
    echo "Nouvelle fréquence: $newFrequency\n";
}

// 7. Supprimer
$deleted = $repository->deleteByToken('john');
echo "Supprimé $deleted tokens\n";

// 8. Explorer
$fields = $repository->getDistinctFields();
$allTokens = $repository->getDistinctTokens();
```

## Voir aussi

- `IndexedDocumentRepository` - Gestion des documents
- `GramType` - Types de tokens (LEXICAL, METAPHONE)
- `IndexedTokenFiltersRecord` - Record de filtrage
- `IndexableFingerPrintVO` - Value Object pour les fingerprints
- `ClusterVO` - Value Object pour les clusters