<?php

namespace App\Models;

use App\Enums\CaseDocumentCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'case_id', 'uploaded_by', 'original_filename', 'stored_path',
        'mime_type', 'size_bytes', 'category',
    ];

    /**
     * Phase 7 requirement #24: never expose the private filesystem
     * path through an accidental toArray()/toJson() -- every legitimate
     * use of this model (Blade views, CaseDocumentController) accesses
     * stored_path directly as an Eloquent attribute, not through
     * serialization, so hiding it here costs nothing and closes a gap.
     */
    protected $hidden = ['stored_path'];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'category' => CaseDocumentCategory::class,
        ];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
