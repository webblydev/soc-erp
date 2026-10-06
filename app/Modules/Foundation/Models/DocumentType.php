<?php

namespace App\Modules\Foundation\Models;

use App\Support\AuditTrail\Auditable;
use App\Support\Lookups\IsLookup;
use Database\Factories\Foundation\DocumentTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Attachment category with its own upload rules (docs/01 §3.9, FD-BR-08).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property list<string>|null $allowed_mimes File extensions, lowercase; null allows the global list.
 * @property int $max_size_mb
 * @property bool $is_active
 * @property bool $is_system
 */
#[Fillable(['code', 'name', 'description', 'sort_order', 'color', 'is_active', 'is_system', 'allowed_mimes', 'max_size_mb'])]
#[UseFactory(DocumentTypeFactory::class)]
class DocumentType extends Model
{
    /** @use HasFactory<DocumentTypeFactory> */
    use Auditable, HasFactory, IsLookup;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'allowed_mimes' => 'array',
            'max_size_mb' => 'integer',
        ];
    }

    /**
     * Extensions this type accepts: its own list within the global one, or the global list.
     *
     * @return list<string>
     */
    public function allowedExtensions(): array
    {
        /** @var list<string> $global */
        $global = config('foundation.attachments.extensions');

        return $this->allowed_mimes ? array_values(array_intersect($this->allowed_mimes, $global)) : $global;
    }
}
