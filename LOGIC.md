Voici la documentation mise à jour et alignée sur l'implémentation réelle avec l'interface `SimilarityConfigInterface` et le `SimilarityCalculatorService` :

---

# 📦 HERMES - DOCUMENTATION TECHNIQUE

## 1. SIMILARITY CONFIGURATION

**Objectif :** Configurer tous les paramètres du calcul de similarité.

```php
interface SimilarityConfigInterface
{
    // Longueur minimale d'un mot (fusion avec le voisin si plus court)
    public function getMinWordLength(): int;
    
    // Poids de la similarité lexicale (n-grammes)
    public function getTextualWeight(): float;
    
    // Poids de la similarité phonétique (Metaphone)
    public function getPhoneticWeight(): float;
    
    // Poids d'un n-gramme selon sa longueur
    public function getGramWeight(int $gramLength): float;
    
    // Poids d'une lettre pour la pondération inverse
    public function getLetterWeight(string $letter): float;
    
    // Taille minimale des n-grammes
    public function getGramMinSize(): int;
    
    // Taille maximale des n-grammes
    public function getGramMaxSize(): int;
    
    // Dimension des vecteurs
    public function getVectorDimension(): int;
    
    // Bonus par lettre unique en commun
    public function getLetterBonus(): float;
    
    // Bonus par bigramme en commun
    public function getBigramBonus(): float;
}
```

### Configuration par défaut (Français)

```php
final class FrenchSimilarityConfig implements SimilarityConfigInterface
{
    // Valeurs par défaut
    public function getMinWordLength(): int    { return 2; }
    public function getTextualWeight(): float  { return 0.6; }
    public function getPhoneticWeight(): float { return 0.4; }
    public function getGramMinSize(): int      { return 2; }
    public function getGramMaxSize(): int      { return 4; }
    public function getVectorDimension(): int  { return 1000; }
    public function getLetterBonus(): float    { return 0.05; }
    public function getBigramBonus(): float    { return 0.07; }
    
    public function getGramWeight(int $length): float
    {
        return match($length) {
            2 => 1.0,   // Bigrammes
            3 => 0.85,  // Trigrammes
            4 => 0.70,  // 4-grammes
            default => 0.5
        };
    }
    
    public function getLetterWeight(string $letter): float
    {
        return match(mb_strtolower($letter)) {
            'e' => 0.95, 'a' => 0.85, 'i' => 0.85,
            's' => 0.80, 't' => 0.80, 'n' => 0.75,
            'r' => 0.75, 'u' => 0.70, 'l' => 0.70,
            'o' => 0.65, 'd' => 0.65, 'c' => 0.60,
            'p' => 0.60, 'm' => 0.55, 'v' => 0.55,
            'q' => 0.50, 'f' => 0.50, 'b' => 0.45,
            'g' => 0.45, 'h' => 0.40, 'j' => 0.35,
            'x' => 0.30, 'y' => 0.30, 'z' => 0.25,
            'k' => 0.20, 'w' => 0.15,
            default => 0.5
        };
    }
}
```

---

## 2. CONTEXT FILTER

**Objectif :** Filtrer par namespace et/ou cluster.

```php
// Un contexte = namespace + cluster (les deux sont optionnels)
[
    'namespace' => 'App.Models.User',  // Optionnel
    'cluster' => 'tenant:company_abc'   // Optionnel
]
```

---

## 3. CONTEXTS

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

## 4. COMPLETION

**Objectif :** L'utilisateur tape un BOUT de mot (pas forcément le début), on propose des mots complets triés par similarité.

### Exemple
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

### Entrée
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

### Sortie
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

## 5. SUGGESTION

**Objectif :** L'utilisateur a fait une faute, on propose les mots les plus proches triés par similarité.

### Exemple
```
Base contient : "developer", "development", "deploy", "devops"
User tape : "devloper" (faute)
→ Résultat trié par similarité :
   1. "devloper" → "developer" (similarité 0.92)
   2. "devloper" → "development" (similarité 0.78)
   3. "devloper" → "deploy" (similarité 0.65)
   4. "devloper" → "devops" (similarité 0.45)
```

### Entrée
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

### Sortie
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

## 6. SEARCH

**Objectif :** L'utilisateur cherche, on retourne les documents complets avec le détail des matchs.

### Entrée
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

### Sortie
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

### Algorithme détaillé

```php
class SimilarityCalculatorService
{
    public function calculateSimilarity(string $text1, string $text2): float
    {
        // 1. Normalisation des textes
        $normalizedText1 = $this->normalizer->normalize($text1);
        $normalizedText2 = $this->normalizer->normalize($text2);

        // 2. Extraction et fusion des mots trop courts
        $words1 = $this->extractAndMergeWords($normalizedText1);
        $words2 = $this->extractAndMergeWords($normalizedText2);

        // 3. Calcul de la matrice de similarité
        $similarityMatrix = [];
        foreach ($words1 as $i => $word1) {
            foreach ($words2 as $j => $word2) {
                $similarityMatrix[$i][$j] = $this->calculateWordSimilarity($word1, $word2);
            }
        }

        // 4. Sélection des meilleurs matchs 1 vs 1
        $bestMatches = $this->getBestMatches($similarityMatrix, count($words1), count($words2));

        // 5. Moyenne des meilleurs matchs
        $baseScore = array_sum($bestMatches) / count($bestMatches);

        // 6. Correction de longueur
        return $this->applyLengthCorrection($baseScore, $normalizedText1, $normalizedText2);
    }
}
```

### Similarité entre deux mots

```php
private function calculateWordSimilarity(string $word1, string $word2): float
{
    // 1. Vecteurs lexicaux (n-grammes pondérés) et phonétiques (Metaphone)
    $lexicalVector1 = $this->generateWeightedLexicalVector($word1);
    $lexicalVector2 = $this->generateWeightedLexicalVector($word2);
    $phoneticVector1 = $this->generateWeightedPhoneticVector($word1);
    $phoneticVector2 = $this->generateWeightedPhoneticVector($word2);

    // 2. Cosine similarity
    $lexicalSimilarity = $this->vectorGenerator->cosineSimilarity($lexicalVector1, $lexicalVector2);
    $phoneticSimilarity = $this->vectorGenerator->cosineSimilarity($phoneticVector1, $phoneticVector2);

    // 3. Bonus (lettres et bigrammes en commun)
    $bonus = $this->calculateBonus($word1, $word2);

    // 4. Combinaison pondérée (lexical + phonétique) + bonus
    $textualWeight = $this->config->getTextualWeight();
    $phoneticWeight = $this->config->getPhoneticWeight();
    $baseSimilarity = ($lexicalSimilarity * $textualWeight) + ($phoneticSimilarity * $phoneticWeight);

    return min(1.0, $baseSimilarity + $bonus);
}
```

### Bonus et pondération

```php
private function calculateBonus(string $word1, string $word2): float
{
    // 1. Lettres uniques en commun → bonus configurable (5% par défaut)
    $commonLetters = array_intersect(
        array_unique(mb_str_split($word1)),
        array_unique(mb_str_split($word2))
    );
    $letterBonus = count($commonLetters) * $this->config->getLetterBonus() * $averageInverseWeight;

    // 2. Bigrammes en commun → bonus configurable (7% par défaut)
    $commonBigrams = array_intersect(
        $this->extractBigrams($word1),
        $this->extractBigrams($word2)
    );
    $bigramBonus = count($commonBigrams) * $this->config->getBigramBonus() * $averageInverseWeight;

    return $letterBonus + $bigramBonus;
}

// Pondération inverse des lettres : 1 / (poids + 1)
// Une lettre rare a un poids faible → pondération inverse élevée → plus d'influence
private function calculateInverseLetterWeight(string $token): float
{
    $totalInverseWeight = 0.0;
    foreach (mb_str_split($token) as $letter) {
        $weight = $this->config->getLetterWeight($letter);
        $totalInverseWeight += 1 / ($weight + 1);
    }
    return $totalInverseWeight / count($letters);
}
```

---

## 8. PERFORMANCES

### Temps de calcul pour 25 comparaisons (5x5 mots)

| Étape | Temps | % du total |
|-------|-------|------------|
| Extraction des mots | 0.02 ms | 0.2% |
| Matrice de similarité | 10.02 ms | 98.9% |
| Sélection des matchs | 0.006 ms | 0.06% |
| Correction de longueur | 0.021 ms | 0.2% |
| **TOTAL** | **10.13 ms** | **100%** |

### Optimisations

1. **Cache des vecteurs** : Les vecteurs sont calculés une seule fois par mot unique
   - Premier appel : ~0.6-0.7 ms
   - Appels suivants : **~0.001 ms** (gain de 99.8%)

2. **Matrice optimisée** : Seulement 25 comparaisons pour 5x5 mots
   - 0.4 ms par comparaison en moyenne

---

## 9. EXEMPLES D'UTILISATION

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

## 10. RÉSUMÉ DES RECORDS

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

## 11. RÉSUMÉ DES SERVICES

| Service | Méthode | Entrée | Sortie | Description |
|---------|---------|--------|--------|-------------|
| **COMPLETION** | `complete()` | `CompletionRequestRecord` | `CompletionResultRecordCollection` | Tape un BOUT de mot → mots complets |
| **SUGGESTION** | `suggest()` | `SuggestionRequestRecord` | `SuggestionResultRecordCollection` | Faute de frappe → mots corrigés |
| **SEARCH** | `search()` | `SearchRequestRecord` | `SearchResultRecordCollection` | Cherche → documents complets |

---

## 12. INTERFACE FINALE

```php
interface HermesInterface
{
    public function complete(CompletionRequestRecord $request): CompletionResultRecordCollection;
    
    public function suggest(SuggestionRequestRecord $request): SuggestionResultRecordCollection;
    
    public function search(SearchRequestRecord $request): SearchResultRecordCollection;
}
```

---

## 13. DEPENDANCES PRINCIPALES

```php
// Service de similarité
final class SimilarityCalculatorService implements SimilarityCalculatorInterface
{
    public function __construct(
        private readonly TextNormalizerInterface $normalizer,
        private readonly NGramGeneratorInterface $ngramGenerator,
        private readonly WordVectorGeneratorInterface $vectorGenerator,
        private readonly SimilarityConfigInterface $config,
    ) {}
}

// Configuration
interface SimilarityConfigInterface
{
    // 10 méthodes pour configurer tous les paramètres
}

// Records
readonly class CompletionRequestRecord
readonly class CompletionResultRecord
readonly class SuggestionRequestRecord
readonly class SuggestionResultRecord
readonly class SearchRequestRecord
readonly class SearchResultRecord
```

---

**Documentation mise à jour le :** 2026-07-05
**Version :** 2.0
**Auteur :** Andy Defer