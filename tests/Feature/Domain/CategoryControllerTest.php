<?php

declare(strict_types=1);

use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\User;

it('lists the tree, one level deep, for this user only', function (): void {
    $user = User::factory()->create();
    $parent = Category::factory()->for($user)->create(['name' => 'Food']);
    Category::factory()->for($user)->childOf($parent)->create(['name' => 'Supermarket']);
    Category::factory()->create(['name' => 'Someone else']);

    $this->actingAs($user)
        ->get(route('categories.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/categories')
            ->has('categories', 1)
            ->where('categories.0.name', 'Food')
            ->has('categories.0.children', 1)
            ->where('categories.0.children.0.name', 'Supermarket'));
});

it('creates a top-level category of the chosen type', function (string $type): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('categories.index'))
        ->post(route('categories.store'), ['name' => 'New One', 'type' => $type])
        ->assertRedirectToRoute('categories.index');

    expect($user->categories()->where('name', 'New One')->firstOrFail()->type->value)->toBe($type);
})->with(['income', 'expense']);

it("gives a subcategory its parent's type, whatever was requested", function (): void {
    $user = User::factory()->create();
    $parent = Category::factory()->for($user)->income()->create();

    $this->actingAs($user)
        ->from(route('categories.index'))
        ->post(route('categories.store'), [
            'name' => 'Child',
            'parent_id' => $parent->id,
            'type' => 'expense',
        ])
        ->assertSessionHasNoErrors();

    expect($user->categories()->where('name', 'Child')->firstOrFail()->type)
        ->toBe(TransactionType::Income);
});

it('allows only one level of nesting', function (): void {
    $user = User::factory()->create();
    $parent = Category::factory()->for($user)->create();
    $child = Category::factory()->for($user)->childOf($parent)->create();

    $this->actingAs($user)
        ->from(route('categories.index'))
        ->post(route('categories.store'), ['name' => 'Grandchild', 'parent_id' => $child->id])
        ->assertSessionHasErrors('parent_id');
});

it('rejects a duplicate name among siblings but allows it under a different parent', function (): void {
    $user = User::factory()->create();
    $first = Category::factory()->for($user)->create(['name' => 'Food']);
    $second = Category::factory()->for($user)->create(['name' => 'Travel']);
    Category::factory()->for($user)->childOf($first)->create(['name' => 'Coffee']);

    $this->actingAs($user)
        ->from(route('categories.index'))
        ->post(route('categories.store'), ['name' => 'Coffee', 'parent_id' => $first->id])
        ->assertSessionHasErrors('name');

    $this->actingAs($user)
        ->from(route('categories.index'))
        ->post(route('categories.store'), ['name' => 'Coffee', 'parent_id' => $second->id])
        ->assertSessionHasNoErrors();
});

it('rejects a duplicate top-level name, which the database index cannot catch', function (): void {
    $user = User::factory()->create();
    Category::factory()->for($user)->create(['name' => 'Housing']);

    $this->actingAs($user)
        ->from(route('categories.index'))
        ->post(route('categories.store'), ['name' => 'Housing', 'type' => 'expense'])
        ->assertSessionHasErrors('name');
});

it('keeps the essential and irregular flags off for income categories', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('categories.store'), [
        'name' => 'Salary Extra',
        'type' => 'income',
        'is_essential' => true,
        'is_irregular' => true,
    ]);

    $category = $user->categories()->where('name', 'Salary Extra')->firstOrFail();

    expect($category->is_essential)->toBeFalse()
        ->and($category->is_irregular)->toBeFalse();
});

it('sets the flags on expense categories', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('categories.store'), [
        'name' => 'Rent',
        'type' => 'expense',
        'is_essential' => true,
        'is_irregular' => true,
    ]);

    $category = $user->categories()->where('name', 'Rent')->firstOrFail();

    expect($category->is_essential)->toBeTrue()
        ->and($category->is_irregular)->toBeTrue();
});

it('renames a category, including a system one', function (): void {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->system(Category::KEY_SALARY)->create(['name' => 'Salary']);

    $this->actingAs($user)
        ->from(route('categories.index'))
        ->patch(route('categories.update', $category), ['name' => 'Wages'])
        ->assertRedirectToRoute('categories.index');

    // The key survives the rename, which is why later milestones reference it rather than
    // the name.
    expect($category->refresh()->name)->toBe('Wages')
        ->and($category->system_key)->toBe(Category::KEY_SALARY);
});

it("never changes a category's type", function (): void {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->expense()->create();

    $this->actingAs($user)->patch(route('categories.update', $category), [
        'name' => $category->name,
        'type' => 'income',
    ]);

    expect($category->refresh()->type)->toBe(TransactionType::Expense);
});

it('deactivates instead of deleting, and takes the children with it', function (): void {
    $user = User::factory()->create();
    $parent = Category::factory()->for($user)->create();
    $child = Category::factory()->for($user)->childOf($parent)->create();

    $this->actingAs($user)
        ->from(route('categories.index'))
        ->delete(route('categories.destroy', $parent))
        ->assertRedirectToRoute('categories.index');

    expect($parent->refresh()->is_active)->toBeFalse()
        ->and($child->refresh()->is_active)->toBeFalse()
        ->and(Category::query()->whereKey($parent->id)->exists())->toBeTrue();
});

it('refuses to remove a system category', function (): void {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->system(Category::KEY_SUBSCRIPTIONS)->create();

    $this->actingAs($user)
        ->delete(route('categories.destroy', $category))
        ->assertForbidden();

    expect($category->refresh()->is_active)->toBeTrue();
});

it('reactivates a subcategory along with its parent', function (): void {
    $user = User::factory()->create();
    $parent = Category::factory()->for($user)->inactive()->create();
    $child = Category::factory()->for($user)->childOf($parent)->inactive()->create();

    $this->actingAs($user)
        ->from(route('categories.index'))
        ->post(route('category-activation.store', $child))
        ->assertRedirectToRoute('categories.index');

    expect($child->refresh()->is_active)->toBeTrue()
        ->and($parent->refresh()->is_active)->toBeTrue();
});

it('reorders by swapping with the sibling in that position', function (): void {
    $user = User::factory()->create();
    $first = Category::factory()->for($user)->create(['sort_order' => 0]);
    $second = Category::factory()->for($user)->create(['sort_order' => 1]);

    $this->actingAs($user)->patch(route('categories.update', $second), [
        'name' => $second->name,
        'sort_order' => 0,
    ]);

    expect($second->refresh()->sort_order)->toBe(0)
        ->and($first->refresh()->sort_order)->toBe(1);
});

it('moves a subcategory to another parent of the same type', function (): void {
    $user = User::factory()->create();
    $from = Category::factory()->for($user)->expense()->create();
    $to = Category::factory()->for($user)->expense()->create();
    $child = Category::factory()->for($user)->childOf($from)->create();

    $this->actingAs($user)
        ->from(route('categories.index'))
        ->patch(route('subcategory-parent.update', $child), [
            'parent_id' => $to->id,
            'update_existing' => true,
        ])
        ->assertRedirectToRoute('categories.index');

    expect($child->refresh()->parent_id)->toBe($to->id);
});

it('refuses to move a subcategory across types', function (): void {
    $user = User::factory()->create();
    $from = Category::factory()->for($user)->expense()->create();
    $to = Category::factory()->for($user)->income()->create();
    $child = Category::factory()->for($user)->childOf($from)->create();

    $this->actingAs($user)
        ->from(route('categories.index'))
        ->patch(route('subcategory-parent.update', $child), ['parent_id' => $to->id])
        ->assertSessionHasErrors('parent_id');

    expect($child->refresh()->parent_id)->toBe($from->id);
});

it('refuses to move a top-level category', function (): void {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create();
    $target = Category::factory()->for($user)->create();

    $this->actingAs($user)
        ->from(route('categories.index'))
        ->patch(route('subcategory-parent.update', $category), ['parent_id' => $target->id])
        ->assertSessionHasErrors('parent_id');
});

it('refuses to move a subcategory under another subcategory', function (): void {
    $user = User::factory()->create();
    $parent = Category::factory()->for($user)->create();
    $child = Category::factory()->for($user)->childOf($parent)->create();
    $otherChild = Category::factory()->for($user)->childOf($parent)->create();

    $this->actingAs($user)
        ->from(route('categories.index'))
        ->patch(route('subcategory-parent.update', $child), ['parent_id' => $otherChild->id])
        ->assertSessionHasErrors('parent_id');
});

it('reports a category as in use once it has children', function (): void {
    $user = User::factory()->create();
    $parent = Category::factory()->for($user)->create();

    expect($parent->isInUse())->toBeFalse();

    Category::factory()->for($user)->childOf($parent)->create();

    expect($parent->refresh()->isInUse())->toBeTrue();
});

it('refuses to move a subcategory to a category that is not yours', function (): void {
    $user = User::factory()->create();
    $parent = Category::factory()->for($user)->create();
    $child = Category::factory()->for($user)->childOf($parent)->create();

    $someoneElses = Category::factory()->create();

    $this->actingAs($user)
        ->from(route('categories.index'))
        ->patch(route('subcategory-parent.update', $child), ['parent_id' => $someoneElses->id])
        ->assertSessionHasErrors('parent_id');

    expect($child->refresh()->parent_id)->toBe($parent->id);
});

it('refuses to make a subcategory its own parent', function (): void {
    $user = User::factory()->create();
    $parent = Category::factory()->for($user)->create();
    $child = Category::factory()->for($user)->childOf($parent)->create();

    $this->actingAs($user)
        ->from(route('categories.index'))
        ->patch(route('subcategory-parent.update', $child), ['parent_id' => $child->id])
        ->assertSessionHasErrors('parent_id');
});

it('refuses to create a category under a parent that is not yours', function (): void {
    $user = User::factory()->create();
    $someoneElses = Category::factory()->create();

    $this->actingAs($user)
        ->from(route('categories.index'))
        ->post(route('categories.store'), ['name' => 'Sneaky', 'parent_id' => $someoneElses->id])
        ->assertSessionHasErrors('parent_id');

    expect($user->categories()->where('name', 'Sneaky')->exists())->toBeFalse();
});

it("refuses to rename a category onto a sibling's name", function (): void {
    $user = User::factory()->create();
    Category::factory()->for($user)->create(['name' => 'Housing']);
    $other = Category::factory()->for($user)->create(['name' => 'Travel']);

    $this->actingAs($user)
        ->from(route('categories.index'))
        ->patch(route('categories.update', $other), ['name' => 'Housing'])
        ->assertSessionHasErrors('name');

    expect($other->refresh()->name)->toBe('Travel');
});

it('allows renaming a subcategory onto a name used under a different parent', function (): void {
    $user = User::factory()->create();
    $first = Category::factory()->for($user)->create();
    $second = Category::factory()->for($user)->create();

    Category::factory()->for($user)->childOf($first)->create(['name' => 'Coffee']);
    $child = Category::factory()->for($user)->childOf($second)->create(['name' => 'Tea']);

    $this->actingAs($user)
        ->from(route('categories.index'))
        ->patch(route('categories.update', $child), ['name' => 'Coffee'])
        ->assertSessionHasNoErrors();

    expect($child->refresh()->name)->toBe('Coffee');
});
