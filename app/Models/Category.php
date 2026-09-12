<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TransactionType;
use Carbon\CarbonInterface;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read int $id
 * @property-read string $user_id
 * @property-read int|null $parent_id
 * @property-read TransactionType $type
 * @property-read string $name
 * @property-read string|null $system_key
 * @property-read bool $is_active
 * @property-read bool $is_essential
 * @property-read bool $is_irregular
 * @property-read int $sort_order
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read User $user
 * @property-read self|null $parent
 * @property-read Collection<int, self> $children
 */
#[Fillable([
    'parent_id',
    'type',
    'name',
    'is_active',
    'is_essential',
    'is_irregular',
    'sort_order',
])]
final class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    /**
     * System categories are referenced by key rather than by name, so renaming one does
     * not break the salary model or subscriptions. They can be renamed but never deleted.
     */
    public const string KEY_SALARY = 'salary';

    public const string KEY_CHRISTMAS_BONUS = 'christmas_bonus';

    public const string KEY_EASTER_BONUS = 'easter_bonus';

    public const string KEY_VACATION_ALLOWANCE = 'vacation_allowance';

    public const string KEY_SUBSCRIPTIONS = 'subscriptions';

    /**
     * @return Builder<self>
     */
    public static function topLevel(): Builder
    {
        return self::query()->whereNull('parent_id');
    }

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'user_id' => 'string',
            'parent_id' => 'integer',
            'type' => TransactionType::class,
            'name' => 'string',
            'system_key' => 'string',
            'is_active' => 'boolean',
            'is_essential' => 'boolean',
            'is_irregular' => 'boolean',
            'sort_order' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<self, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function isTopLevel(): bool
    {
        return $this->parent_id === null;
    }

    public function isSystem(): bool
    {
        return $this->system_key !== null;
    }

    /**
     * Whether anything depends on this category.
     *
     * Subcategories, plan items and transactions all count, whether the category is their
     * main one or their subcategory. Subscriptions add their clause in milestone 6.
     *
     * This is what keeps history readable: a category something already refers to may be
     * deactivated and renamed, but never removed or repointed (CAT-03, CAT-04).
     */
    public function isInUse(): bool
    {
        if ($this->children()->exists()) {
            return true;
        }

        $referencesThis = fn (Builder $query): Builder => $query
            ->where('category_id', $this->id)
            ->orWhere('subcategory_id', $this->id);

        return PlanItem::query()->where($referencesThis)->exists()
            || Transaction::query()->where($referencesThis)->exists();
    }
}
