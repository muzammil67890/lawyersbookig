<?php

/**
 * Phase 6 Part 1: Lawyer Availability + Appointment Booking Foundation.
 *
 * Centralizes the few numeric constants the booking foundation needs so
 * they are never hardcoded inline in controllers, services, or Form
 * Requests. Phase 6 Part 2 (accept/reject, reschedule, cancellation)
 * should read from here too rather than introducing a second source.
 */
return [

    /**
     * Every appointment slot is this many minutes long. The product
     * does not yet support variable-length consultations; a single
     * fixed duration keeps slot generation and conflict detection
     * simple and deterministic. Revisit only if a future phase adds
     * per-service or per-lawyer custom durations.
     */
    'default_duration_minutes' => 30,

    /**
     * A customer may not book further than this many days into the
     * future. Keeps the slot-generation window bounded and matches a
     * sensible real-world booking horizon.
     */
    'booking_window_days' => 60,

];
