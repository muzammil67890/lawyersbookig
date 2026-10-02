<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 11 QA fix: `cases.notes` was being read and written by both
     * the customer and the lawyer, so neither party's "private" notes
     * were actually private from the other. `notes` is kept as-is and
     * is now treated exclusively as the LAWYER's private notes (no data
     * migration needed -- existing values were always written by
     * whichever party last saved the shared form, so there is no
     * reliable way to attribute old rows to one party after the fact).
     * `customer_notes` is the customer's equivalent, private field.
     *
     * `cancellation_reason` mirrors the column Phase 6 already added to
     * `appointments` for the identical purpose: a free-text reason typed
     * on cancellation used to be appended into the shared `notes`
     * column, which -- now that `notes` is lawyer-private -- would leak
     * a customer's cancellation reason into the lawyer's private notes
     * (or vice versa). Giving cases the same dedicated column
     * appointments already use keeps the reason visible to both parties
     * without going through either one's private notes.
     */
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->text('customer_notes')->nullable()->after('notes');
            $table->text('cancellation_reason')->nullable()->after('closed_at');
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropColumn(['customer_notes', 'cancellation_reason']);
        });
    }
};
