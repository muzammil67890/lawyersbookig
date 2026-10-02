<?php

/*
|--------------------------------------------------------------------------
| Lawyer matching (Phase 5 Half 2) -- deterministic, explainable scoring
|--------------------------------------------------------------------------
| This file is the ONLY place scoring numbers live. Nothing in
| App\Services\LawyerMatchingService hard-codes a weight or threshold.
|
| This is deterministic matching infrastructure. It is NOT AI: there is
| no model, no randomness and no external service. Identical input on
| identical data always yields the identical score and ranking. The
| Phase 11 AI Assistant is expected to CALL this layer, not replace it.
*/

return [

    /*
    | Component weights. A lawyer's score is
    |
    |   round( sum(weight_i * fraction_i) / sum(weight_i) * 100 )
    |
    | where the sums run ONLY over the criteria the customer actually
    | supplied (fraction_i is 0..1). So a lawyer who fully satisfies
    | every requested criterion scores exactly 100 no matter how many
    | criteria were requested, and the score is always within 0..100.
    | The weights below sum to 100 purely for readability; only their
    | ratios matter. A weight of 0 disables a component.
    |
    | Rationale: what the customer is asking for (topic) outweighs where
    | / how much / how experienced, because a lawyer in the right city
    | who does the wrong kind of law is not a match.
    */
    'weights' => [
        'service' => 25,        // lawyer offers the requested Service
        'specialization' => 20, // lawyer offers the requested Specialization
        'problem' => 20,        // free-text legal problem relevance
        'location' => 15,       // same city (exact, case-insensitive)
        'budget' => 10,         // consultation_fee vs requested budget
        'experience' => 7,      // years_of_experience vs requested minimum
        'availability' => 3,    // recurring availability on a requested weekday
    ],

    /*
    | Legal-problem relevance tiers (fraction of the 'problem' weight).
    | "direct"  = the customer's own words contain the service/
    |             specialization NAME (e.g. "divorce" -> Divorce).
    | "related" = the words hit an entry of `problem.keywords` below
    |             (e.g. "inheritance" -> Family Law).
    | "text"    = no catalogue hit, but the lawyer's own title/bio/
    |             experience summary contains one of the words.
    | The best tier that applies to a lawyer is used (not summed).
    */
    'problem_tiers' => [
        'specialization_direct' => 1.00,
        'service_direct' => 0.90,
        'specialization_related' => 0.80,
        'service_related' => 0.65,
        'text' => 0.35,
    ],

    /*
    | Budget compatibility. Uses lawyer_profiles.consultation_fee.
    |   within  -> fraction 1.0  (inside the requested min/max)
    |   near    -> `near_score` (outside, but within `tolerance_percent`
    |              of the violated bound; e.g. 20% => a Rs. 3,000 max
    |              still counts as "near" up to Rs. 3,600)
    |   outside -> 0
    | A lawyer with no listed fee can never be "within" or "near".
    | Flexible mode (default) only SCORES this; the `strict[]=budget`
    | request option turns it into a hard SQL filter instead.
    */
    'budget' => [
        'tolerance_percent' => 20,
        'near_score' => 0.5,
    ],

    /*
    | Experience compatibility (only evaluated when a minimum is asked).
    |   below minimum        -> 0
    |   meets minimum        -> `met_score`
    |   exceeds the minimum  -> met_score + (1 - met_score) scaled up to
    |                           `bonus_span_years` extra years, then capped.
    */
    'experience' => [
        'met_score' => 0.70,
        'bonus_span_years' => 10,
    ],

    'limits' => [
        'default' => 10,        // results returned when `limit` is not sent
        'max' => 50,            // hard ceiling for `limit`
        'candidate_cap' => 300, // safety cap on rows scored in PHP per request
    ],

    'problem' => [
        'max_length' => 500,
        'min_token_length' => 4, // shortest word used for lawyer-profile text hits
        'max_text_terms' => 6,

        // Words ignored everywhere in problem analysis (after the same
        // plural-folding applied to the customer's words).
        'stopwords' => [
            'a', 'an', 'the', 'and', 'or', 'of', 'for', 'to', 'in', 'on', 'at', 'with',
            'about', 'my', 'me', 'i', 'we', 'our', 'is', 'are', 'was', 'be', 'need',
            'want', 'help', 'looking', 'lawyer', 'advocate', 'legal', 'law', 'case',
            'matter', 'issue', 'problem', 'regarding', 'related', 'from', 'have', 'has',
            'had', 'get', 'how', 'can', 'do', 'please', 'someone', 'any', 'some', 'that',
            'this', 'it', 'as', 'by', 'not', 'no', 'into', 'after', 'before', 'been',
            'am', 'would', 'like', 'find', 'who', 'what', 'when', 'where', 'which',
        ],

        // Words dropped from a SERVICE name when matching a single word
        // of it ("Criminal Law" -> "criminal").
        'generic_service_words' => ['law', 'legal', 'service'],

        /*
        | Conservative, human-editable synonym table:
        |   customer phrase => catalogue entries it points at (by slug).
        | Slugs that do not exist in the database are silently ignored,
        | so this can never invent a service/specialization. Add rows
        | here (or add real Services/Specializations, which are matched
        | by name automatically) -- no code change required.
        */
        'keywords' => [
            // Family / divorce
            'divorce' => ['services' => ['divorce', 'family-law']],
            'khula' => ['services' => ['divorce', 'family-law']],
            'talaq' => ['services' => ['divorce', 'family-law']],
            'separation' => ['services' => ['divorce', 'family-law']],
            'alimony' => ['services' => ['divorce', 'family-law']],
            'custody' => ['services' => ['family-law'], 'specializations' => ['child-custody']],
            'guardianship' => ['services' => ['family-law'], 'specializations' => ['child-custody']],
            'marriage' => ['services' => ['family-law']],
            'domestic' => ['services' => ['family-law'], 'specializations' => ['domestic-matters']],
            'inheritance' => ['services' => ['family-law', 'property-law']],
            'succession' => ['services' => ['family-law', 'property-law']],

            // Property
            'property dispute' => ['services' => ['property-law', 'civil-law']],
            'land dispute' => ['services' => ['property-law', 'civil-law']],
            'land' => ['services' => ['property-law']],
            'plot' => ['services' => ['property-law']],
            'real estate' => ['services' => ['property-law']],
            'landlord' => ['services' => ['property-law'], 'specializations' => ['tenancy-disputes']],
            'tenant' => ['services' => ['property-law'], 'specializations' => ['tenancy-disputes']],
            'tenancy' => ['services' => ['property-law'], 'specializations' => ['tenancy-disputes']],
            'eviction' => ['services' => ['property-law'], 'specializations' => ['tenancy-disputes']],
            'title transfer' => ['services' => ['property-law'], 'specializations' => ['title-transfer']],
            'property transfer' => ['services' => ['property-law'], 'specializations' => ['title-transfer']],

            // Criminal
            'criminal' => ['services' => ['criminal-law']],
            'crime' => ['services' => ['criminal-law']],
            'fir' => ['services' => ['criminal-law']],
            'arrest' => ['services' => ['criminal-law'], 'specializations' => ['bail-applications']],
            'bail' => ['services' => ['criminal-law'], 'specializations' => ['bail-applications']],
            'murder' => ['services' => ['criminal-law']],
            'theft' => ['services' => ['criminal-law']],
            'assault' => ['services' => ['criminal-law']],
            'fraud' => ['services' => ['criminal-law']],
            'defence' => ['services' => ['criminal-law'], 'specializations' => ['trial-defence']],
            'defense' => ['services' => ['criminal-law'], 'specializations' => ['trial-defence']],

            // Corporate / business
            'company registration' => ['services' => ['corporate-law'], 'specializations' => ['business-formation']],
            'incorporation' => ['services' => ['corporate-law'], 'specializations' => ['business-formation']],
            'startup' => ['services' => ['corporate-law'], 'specializations' => ['business-formation']],
            'business' => ['services' => ['corporate-law']],
            'company' => ['services' => ['corporate-law']],
            'contract' => ['services' => ['corporate-law', 'civil-law']],
            'contract drafting' => ['services' => ['corporate-law'], 'specializations' => ['contract-drafting']],

            // Tax
            'tax' => ['services' => ['tax-law']],
            'income tax' => ['services' => ['tax-law']],
            'sales tax' => ['services' => ['tax-law']],
            'fbr' => ['services' => ['tax-law']],
            'tax notice' => ['services' => ['tax-law'], 'specializations' => ['tax-disputes']],
            'tax dispute' => ['services' => ['tax-law'], 'specializations' => ['tax-disputes']],
            'tax planning' => ['services' => ['tax-law'], 'specializations' => ['corporate-tax-planning']],

            // Immigration
            'visa' => ['services' => ['immigration'], 'specializations' => ['visa-applications']],
            'work permit' => ['services' => ['immigration'], 'specializations' => ['visa-applications']],
            'residency' => ['services' => ['immigration'], 'specializations' => ['residency']],
            'citizenship' => ['services' => ['immigration']],
            'deportation' => ['services' => ['immigration']],

            // Employment
            'employment' => ['services' => ['employment-law']],
            'termination' => ['services' => ['employment-law']],
            'dismissal' => ['services' => ['employment-law']],
            'fired' => ['services' => ['employment-law']],
            'salary' => ['services' => ['employment-law']],
            'workplace' => ['services' => ['employment-law']],
            'labour' => ['services' => ['employment-law']],
            'labor' => ['services' => ['employment-law']],

            // Civil / documents
            'civil suit' => ['services' => ['civil-law']],
            'damages' => ['services' => ['civil-law']],
            'defamation' => ['services' => ['civil-law']],
            'recovery' => ['services' => ['civil-law']],
            'affidavit' => ['services' => ['affidavit']],
            'attestation' => ['services' => ['affidavit']],
            'notary' => ['services' => ['affidavit']],
        ],
    ],
];
