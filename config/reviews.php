<?php

/**
 * Phase 9: Reviews, Ratings & Lawyer Reputation.
 *
 * Centralizes the few constants this phase needs so they are never
 * hardcoded inline in controllers, services, or Form Requests, matching
 * the config/appointments.php and config/cases.php convention.
 */
return [

    /** Maximum length of a review comment, in characters. */
    'comment_max_length' => 2000,

    /**
     * A comment is optional, but when one is provided it must carry at
     * least this many characters after trimming -- rejects "effectively
     * empty" content (e.g. a lone space or a single character) without
     * forcing every reviewer to write a long comment.
     */
    'comment_min_length' => 3,

    /** How many published reviews are shown per page on a public lawyer profile. */
    'public_page_size' => 10,

    /** How many of a customer's own submitted reviews are shown per page. */
    'customer_page_size' => 10,

    /** How many reviews are shown per page in the admin oversight queue. */
    'admin_page_size' => 20,

    /** How many of a lawyer's own published reviews are shown per page on their dashboard-linked list. */
    'lawyer_page_size' => 10,

];
