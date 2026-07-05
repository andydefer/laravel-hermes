## 📦 HERMES - DOCUMENTATION FINALE DES IDÉES

---

## 1. CONTEXTFILTER

**Objectif :** Filtrer par namespace et/ou cluster.

```php
// Un contexte = namespace + cluster (les deux sont optionnels)
[
    'namespace' => 'App.Models.User',  // Optionnel
    'cluster' => 'tenant:company_abc'   // Optionnel
]
```

---

## 2. CONTEXTS

**Objectif :** Collection de contextes pour filtrer.

```php
// 1. Un seul contexte (namespace ET cluster)
'contexts' => [
    [
        'namespace' => 'App.Models.User',
        'cluster' => 'tenant:company_abc'
    ]
]

// 2. Plusieurs contextes (OU entre les contextes)
'contexts' => [
    [
        'namespace' => 'App.Models.User',
        'cluster' => 'tenant:company_abc'
    ],
    [
        'namespace' => 'App.Models.Product',
        'cluster' => 'tenant:company_xyz'
    ]
]

// 3. Sans contexte (recherche globale)
'contexts' => null  // ou on ne met pas le champ
```

---

## 3. COMPLETION

**Objectif :** L'utilisateur tape un BOUT de mot (pas forcément le début), on propose des mots complets triés par similarité.

**Exemple :**
```
Base contient : "john", "johanna", "johnson", "johny", "joshua"
User tape : "joh"
→ Résultat trié par similarité :
   1. "joh" → "john" (similarité 1.0)
   2. "joh" → "johanna" (similarité 0.83)
   3. "joh" → "johnson" (similarité 0.80)
   4. "joh" → "johny" (similarité 0.75)
   5. "joh" → "joshua" (similarité 0.60)
```

**Entrée :**
```php
$request = CompletionRequestRecord::from([
    'query' => 'joh',
    'limit' => 10,
    'fields' => ['name', 'email'],
    'contexts' => [
        [
            'namespace' => 'App.Models.User',
            'cluster' => 'tenant:company_abc'
        ]
    ]
]);

$results = $hermes->complete($request);
```

**Sortie :**
```php
// CompletionResultRecordCollection - triée par similarité décroissante
[
    CompletionResultRecord::from([
        'token_id' => 'abc-123',
        'document_id' => 'doc-456',
        'token' => 'joh',
        'original_text' => 'John',
        'field' => 'name',
        'similarity' => 1.0
    ]),
    CompletionResultRecord::from([
        'token_id' => 'def-456',
        'document_id' => 'doc-789',
        'token' => 'joh',
        'original_text' => 'Johanna',
        'field' => 'name',
        'similarity' => 0.83
    ]),
    CompletionResultRecord::from([
        'token_id' => 'ghi-789',
        'document_id' => 'doc-012',
        'token' => 'joh',
        'original_text' => 'Johnson',
        'field' => 'name',
        'similarity' => 0.80
    ])
]

// L'utilisateur voit : ["John", "Johanna", "Johnson"]
```

---

## 4. SUGGESTION

**Objectif :** L'utilisateur a fait une faute, on propose les mots les plus proches triés par similarité.

**Exemple :**
```
Base contient : "developer", "development", "deploy", "devops"
User tape : "devloper" (faute)
→ Résultat trié par similarité :
   1. "devloper" → "developer" (similarité 0.92)
   2. "devloper" → "development" (similarité 0.78)
   3. "devloper" → "deploy" (similarité 0.65)
   4. "devloper" → "devops" (similarité 0.45)
```

**Entrée :**
```php
$request = SuggestionRequestRecord::from([
    'query' => 'devloper',
    'limit' => 5,
    'fields' => ['skills', 'bio'],
    'contexts' => [
        [
            'namespace' => 'App.Models.User',
            'cluster' => 'tenant:company_abc'
        ]
    ],
    'min_similarity' => 0.3
]);

$results = $hermes->suggest($request);
```

**Sortie :**
```php
// SuggestionResultRecordCollection - triée par similarité décroissante
[
    SuggestionResultRecord::from([
        'token_id' => 'abc-123',
        'document_id' => 'doc-456',
        'token' => 'dev',
        'original_text' => 'developer',
        'field' => 'skills',
        'similarity' => 0.92
    ]),
    SuggestionResultRecord::from([
        'token_id' => 'def-456',
        'document_id' => 'doc-789',
        'token' => 'dev',
        'original_text' => 'development',
        'field' => 'skills',
        'similarity' => 0.78
    ]),
    SuggestionResultRecord::from([
        'token_id' => 'ghi-789',
        'document_id' => 'doc-012',
        'token' => 'dev',
        'original_text' => 'deploy',
        'field' => 'skills',
        'similarity' => 0.65
    ])
]

// L'utilisateur voit : ["developer", "development", "deploy"]
```

---

## 5. SEARCH

**Objectif :** L'utilisateur cherche, on retourne les documents complets avec le détail des matchs.

**Entrée :**
```php
$request = SearchRequestRecord::from([
    'query' => 'john',
    'limit' => 20,
    'fields' => ['name', 'email', 'bio'],
    'contexts' => [
        [
            'namespace' => 'App.Models.User',
            'cluster' => 'tenant:company_abc'
        ]
    ],
    'min_similarity' => 0.3,
    'use_phonetic' => true
]);

$results = $hermes->search($request);
```

**Sortie :**
```php
// SearchResultRecordCollection - triée par similarité globale décroissante
[
    SearchResultRecord::from([
        'document_id' => 'doc-456',
        'fingerprint' => 'App.Models.User|123',
        'data' => StrictAssociative::from([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'bio' => 'Software Developer'
        ]),
        'matches' => [
            [
                'field' => 'name',
                'original_text' => 'John',
                'similarity' => 1.0
            ],
            [
                'field' => 'email',
                'original_text' => 'john@example.com',
                'similarity' => 0.85
            ]
        ],
        'similarity' => 0.95  // Score global du document
    ]),
    SearchResultRecord::from([
        'document_id' => 'doc-789',
        'fingerprint' => 'App.Models.User|456',
        'data' => StrictAssociative::from([
            'name' => 'Johanna Smith',
            'email' => 'johanna@example.com',
            'bio' => 'Senior Developer'
        ]),
        'matches' => [
            [
                'field' => 'name',
                'original_text' => 'Johanna',
                'similarity' => 0.80
            ]
        ],
        'similarity' => 0.80
    ])
]

// L'utilisateur voit :
// - John Doe (95% de pertinence, match sur name et email)
// - Johanna Smith (80% de pertinence, match sur name)
```

---

## 6. EXEMPLES D'UTILISATION

### COMPLETION - Sans contexte (global)
```php
$request = CompletionRequestRecord::from([
    'query' => 'joh',
    'limit' => 10
]);
```

### COMPLETION - Avec namespace uniquement
```php
$request = CompletionRequestRecord::from([
    'query' => 'joh',
    'contexts' => [
        ['namespace' => 'App.Models.User']
    ]
]);
```

### COMPLETION - Avec cluster uniquement
```php
$request = CompletionRequestRecord::from([
    'query' => 'joh',
    'contexts' => [
        ['cluster' => 'tenant:company_abc']
    ]
]);
```

### COMPLETION - Avec namespace + cluster
```php
$request = CompletionRequestRecord::from([
    'query' => 'joh',
    'contexts' => [
        [
            'namespace' => 'App.Models.User',
            'cluster' => 'tenant:company_abc'
        ]
    ]
]);
```

### COMPLETION - Avec plusieurs contextes
```php
$request = CompletionRequestRecord::from([
    'query' => 'joh',
    'contexts' => [
        [
            'namespace' => 'App.Models.User',
            'cluster' => 'tenant:company_abc'
        ],
        [
            'namespace' => 'App.Models.Product',
            'cluster' => 'tenant:company_xyz'
        ]
    ]
]);
```

---

## 7. RÉSUMÉ DES RECORDS

| Record | Champs |
|--------|--------|
| **CompletionRequestRecord** | `query`, `limit`, `fields`, `contexts` |
| **CompletionResultRecord** | `token_id`, `document_id`, `token`, `original_text`, `field`, `similarity` |
| **SuggestionRequestRecord** | `query`, `limit`, `fields`, `contexts`, `min_similarity` |
| **SuggestionResultRecord** | `token_id`, `document_id`, `token`, `original_text`, `field`, `similarity` |
| **SearchRequestRecord** | `query`, `limit`, `fields`, `contexts`, `min_similarity`, `use_phonetic` |
| **SearchResultRecord** | `document_id`, `fingerprint`, `data`, `matches`, `similarity` |

**matches :**
```php
'matches' => [
    [
        'field' => 'name',
        'original_text' => 'John',
        'similarity' => 1.0
    ],
    [
        'field' => 'email',
        'original_text' => 'john@example.com',
        'similarity' => 0.85
    ]
]
```

---

## 8. RÉSUMÉ DES SERVICES

| Service | Méthode | Entrée | Sortie | Description |
|---------|---------|--------|--------|-------------|
| **COMPLETION** | `complete()` | `CompletionRequestRecord` | `CompletionResultRecordCollection` | Tape un BOUT de mot → mots complets |
| **SUGGESTION** | `suggest()` | `SuggestionRequestRecord` | `SuggestionResultRecordCollection` | Faute de frappe → mots corrigés |
| **SEARCH** | `search()` | `SearchRequestRecord` | `SearchResultRecordCollection` | Cherche → documents complets |

---

## 9. INTERFACE FINALE

```php
interface HermesInterface
{
    public function complete(CompletionRequestRecord $request): CompletionResultRecordCollection;
    
    public function suggest(SuggestionRequestRecord $request): SuggestionResultRecordCollection;
    
    public function search(SearchRequestRecord $request): SearchResultRecordCollection;
}
```