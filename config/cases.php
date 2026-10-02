<?php

/**
 * Phase 7: Case Management + Secure Document Management.
 *
 * Centralizes the few constants this phase needs so they are never
 * hardcoded inline in controllers or Form Requests, matching the
 * config/appointments.php convention from Phase 6.
 */
return [

    /**
     * Case documents are validated against this fixed allow-list of
     * extensions/MIME types (requirement #20/#25) -- never against the
     * client-supplied filename or Content-Type header alone. Kept to
     * common, non-executable document and image formats.
     */
    'document_mimes' => ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'],

    /** Maximum case document size, in kilobytes. */
    'document_max_kb' => 10240,

];
