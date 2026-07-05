<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Similarity Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the text similarity calculation algorithm.
    | These values control how similarity is computed between two texts.
    |
    */

    'similarity' => [
        /*
        | N-gram generation
        | ----------
        | Minimum and maximum size of n-grams generated from words.
        | Larger ranges increase precision but reduce performance.
        | Default: min=2, max=4
        */
        'gram_min_size' => env('SIMILARITY_GRAM_MIN_SIZE', 2),
        'gram_max_size' => env('SIMILARITY_GRAM_MAX_SIZE', 4),

        /*
        | Vector dimension
        | ----------
        | Number of dimensions for the hash-based vector representation.
        | Higher dimensions reduce hash collisions but increase memory usage.
        | Must be a power of 2 for optimal performance.
        | Default: 128
        */
        'vector_dimension' => env('SIMILARITY_VECTOR_DIMENSION', 128),

        /*
        | Weight distribution
        | ----------
        | Distribution of weight between textual (n-gram) and phonetic (metaphone) similarity.
        | The two values should sum to 1.0.
        | Default: textual=0.6, phonetic=0.4
        */
        'textual_weight' => env('SIMILARITY_TEXTUAL_WEIGHT', 0.6),
        'phonetic_weight' => env('SIMILARITY_PHONETIC_WEIGHT', 0.4),

        /*
        | Bonus multipliers
        | ----------
        | Bonus added for each common letter or bigram between two words.
        | Higher values give more importance to character-level matches.
        | Default: letter=0.05, bigram=0.03
        */
        'letter_bonus' => env('SIMILARITY_LETTER_BONUS', 0.05),
        'bigram_bonus' => env('SIMILARITY_BIGRAM_BONUS', 0.03),

        /*
        | Minimum word length
        | ----------
        | Words shorter than this length are merged with the following word.
        | Prevents extremely short tokens from generating too many n-grams.
        | Default: 2
        */
        'min_word_length' => env('SIMILARITY_MIN_WORD_LENGTH', 2),

        /*
        | Maximum words per text
        | ----------
        | Maximum number of words to keep per text.
        | Words beyond this limit are sampled (beginning, middle, end).
        | Default: 50
        */
        'max_words' => env('SIMILARITY_MAX_WORDS', 50),

        /*
        | Maximum word pairs
        | ----------
        | Maximum number of word pairs to process (words1 × words2).
        | If exceeded, sampling is triggered to reduce the matrix size.
        | Default: 2500 (50 × 50)
        */
        'max_pairs' => env('SIMILARITY_MAX_PAIRS', 2500),

        /*
        | Timeout seconds
        | ----------
        | Maximum time in seconds for similarity calculation.
        | After this time, the calculation stops and returns partial results.
        | Default: 0.5 seconds
        */
        'timeout_seconds' => env('SIMILARITY_TIMEOUT_SECONDS', 0.5),

        /*
        | Levenshtein bonus configuration
        | ----------
        | Bonuses applied based on Levenshtein distance between words.
        |
        | metaphone_threshold: Max distance for metaphone bonus (default: 3)
        | metaphone_bonus: Bonus value when metaphone distance < threshold (default: 0.175 = 17.5%)
        |
        | lexical_threshold: Max distance for lexical bonus (default: 3)
        | lexical_bonus_medium: Bonus when distance < threshold (default: 0.225 = 22.5%)
        | lexical_bonus_high: Bonus when distance < 2 (default: 0.275 = 27.5%)
        |
        | max_bonus: Maximum total Levenshtein bonus allowed (default: 0.45 = 45%)
        */
        'levenshtein' => [
            'metaphone_threshold' => env('SIMILARITY_METAPHONE_THRESHOLD', 3),
            'metaphone_bonus' => env('SIMILARITY_METAPHONE_BONUS', 0.175),

            'lexical_threshold' => env('SIMILARITY_LEXICAL_THRESHOLD', 3),
            'lexical_bonus_medium' => env('SIMILARITY_LEXICAL_BONUS_MEDIUM', 0.225),
            'lexical_bonus_high' => env('SIMILARITY_LEXICAL_BONUS_HIGH', 0.275),

            'max_bonus' => env('SIMILARITY_MAX_LEVENSHTEIN_BONUS', 0.45),
        ],

        /*
        | Gram weights
        | ----------
        | Weight multiplier for n-grams of different sizes.
        | Shorter n-grams typically carry less semantic weight.
        | Default: length 2=0.3, length 3=0.5, length 4=0.7, default=1.0
        */
        'gram_weights' => [
            2 => 0.3,
            3 => 0.5,
            4 => 0.7,
            'default' => 1.0,
        ],

        /*
        | Letter weights
        | ----------
        | Inverse frequency weights for each letter.
        | Common letters (e, a, s) have higher weights (more common = more influence).
        | Rare letters (z, w, k) have lower weights.
        | These values are used in the inverse letter weight calculation.
        */
        'letter_weights' => [
            'e' => 15.0, 'a' => 7.5, 's' => 7.5, 'i' => 7.0,
            'n' => 7.0, 't' => 7.0, 'r' => 6.5, 'u' => 6.0,
            'l' => 5.0, 'o' => 5.0, 'd' => 3.5, 'c' => 3.5,
            'p' => 3.0, 'm' => 3.0, 'v' => 2.0,
            'q' => 1.0, 'g' => 1.0, 'b' => 1.0, 'f' => 1.0,
            'h' => 0.75, 'j' => 0.75,
            'z' => 0.25, 'w' => 0.25, 'k' => 0.25, 'y' => 0.25, 'x' => 0.5,
            'é' => 4.0, 'è' => 3.0, 'ê' => 2.0, 'à' => 1.5,
            'ù' => 1.0, 'ç' => 1.5, 'â' => 1.5, 'î' => 1.0,
            'ô' => 1.0, 'û' => 0.5, 'ë' => 0.5, 'ï' => 0.5, 'ü' => 0.5,
        ],
    ],
];
