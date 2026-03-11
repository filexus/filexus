# Avoiding N+1 Queries

When working with multiple models that have file attachments, it's crucial to avoid N+1 query problems. This guide shows you how to efficiently load files using eager loading and special methods in Filexus.

## Understanding the N+1 Problem

The N+1 problem occurs when you execute one query to fetch models, then execute an additional query for each model when accessing their relationships.

### ❌ The Problem

```php
// Controller
$users = User::all(); // 1 query

// View or Resource
foreach ($users as $user) {
    $avatar = $user->file('avatar'); // N queries (one per user!)
    echo $avatar?->url();
}

// Total: 1 + N queries (101 queries for 100 users!)
```

This code executes:
- 1 query to fetch all users
- 100 queries (one for each user) to fetch their avatar

### ✅ The Solution

```php
// Controller
$users = User::with(['files' => fn($q) => $q->whereCollection('avatar')])->get(); // 2 queries total

// View or Resource
foreach ($users as $user) {
    $avatar = $user->fileFromLoaded('avatar'); // No additional queries!
    echo $avatar?->url();
}

// Total: 2 queries (regardless of number of users!)
```

This code executes:
- 1 query to fetch all users
- 1 query to fetch all avatars for those users

## Methods for Avoiding N+1

Filexus provides two optimized methods specifically designed to work with eager loading:

### fileFromLoaded()

Use instead of `file()` when accessing a single file from a collection.

```php
// Eager load
$posts = Post::with(['files' => fn($q) => $q->whereCollection('thumbnail')])->get();

// Access without N+1
foreach ($posts as $post) {
    $thumbnail = $post->fileFromLoaded('thumbnail');
}
```

### getFilesFromLoaded()

Use instead of `getFiles()` when accessing multiple files from a collection.

```php
// Eager load
$posts = Post::with(['files' => fn($q) => $q->whereCollection('gallery')])->get();

// Access without N+1
foreach ($posts as $post) {
    $images = $post->getFilesFromLoaded('gallery');
}
```

## Common Use Cases

### API Resources

API Resources are a common place for N+1 problems. Always use `fileFromLoaded()` and eager load in your controller.

**❌ Wrong:**

```php
// TenantResource.php
class TenantResource extends JsonResource
{
    public function toArray($request): array
    {
        $profilePicture = $this->file('profile_picture'); // N+1 problem!

        return [
            'id' => $this->id,
            'name' => $this->name,
            'profile_picture_url' => $profilePicture?->url(),
        ];
    }
}

// Controller
return TenantResource::collection(Tenant::all());
```

**✅ Correct:**

```php
// TenantResource.php
class TenantResource extends JsonResource
{
    public function toArray($request): array
    {
        // Use fileFromLoaded() instead
        $profilePicture = $this->fileFromLoaded('profile_picture');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'profile_picture_url' => $profilePicture?->url(),
        ];
    }
}

// Controller - IMPORTANT: Eagerly load the files
$tenants = Tenant::with(['files' => fn($q) => $q->whereCollection('profile_picture')])->get();
return TenantResource::collection($tenants);
```

### Blade Templates

When displaying files in Blade views for multiple models, eager load in your controller.

**❌ Wrong:**

```php
// Controller
public function index()
{
    $products = Product::all();
    return view('products.index', compact('products'));
}
```

```blade
{{-- View --}}
@foreach($products as $product)
    <div class="product">
        @if($thumbnail = $product->file('thumbnail'))
            <img src="{{ $thumbnail->url() }}" />
        @endif
    </div>
@endforeach
```

**✅ Correct:**

```php
// Controller
public function index()
{
    $products = Product::with(['files' => fn($q) => $q->whereCollection('thumbnail')])
        ->get();
    return view('products.index', compact('products'));
}
```

```blade
{{-- View --}}
@foreach($products as $product)
    <div class="product">
        @if($thumbnail = $product->fileFromLoaded('thumbnail'))
            <img src="{{ $thumbnail->url() }}" />
        @endif
    </div>
@endforeach
```

### Multiple Collections

When you need files from multiple collections, eager load them all at once.

```php
// Load multiple collections efficiently
$users = User::with(['files' => function ($query) {
    $query->whereIn('collection', ['avatar', 'cover_photo', 'signature']);
}])->get();

foreach ($users as $user) {
    $avatar = $user->fileFromLoaded('avatar');
    $cover = $user->fileFromLoaded('cover_photo');
    $signature = $user->fileFromLoaded('signature');
}
```

### Nested Relationships

When working with nested relationships, eager load all levels.

```php
// Load posts with their author's avatar
$posts = Post::with([
    'author.files' => fn($q) => $q->whereCollection('avatar'),
    'files' => fn($q) => $q->whereCollection('featured_image')
])->get();

foreach ($posts as $post) {
    // Access both post and author files without N+1
    $featuredImage = $post->fileFromLoaded('featured_image');
    $authorAvatar = $post->author->fileFromLoaded('avatar');
}
```

### Pagination

Eager loading works perfectly with Laravel's pagination.

```php
$users = User::with(['files' => fn($q) => $q->whereCollection('avatar')])
    ->paginate(20);

return view('users.index', compact('users'));
```

```blade
@foreach($users as $user)
    @if($avatar = $user->fileFromLoaded('avatar'))
        <img src="{{ $avatar->url() }}" />
    @endif
@endforeach

{{ $users->links() }}
```

## Eager Loading All Files

If you need all files (not just from specific collections), eager load without constraints.

```php
$posts = Post::with('files')->get();

foreach ($posts as $post) {
    // Get all files efficiently
    $allFiles = $post->getFilesFromLoaded();
    
    // Or filter by collection in memory
    $thumbnails = $post->getFilesFromLoaded('thumbnails');
    $documents = $post->getFilesFromLoaded('documents');
}
```

## Important Edge Cases

### Constrained Eager Loading

**⚠️ Warning:** When you eager load with a collection constraint, only those collections are loaded into memory.

```php
// Only loads 'avatar' files
$users = User::with(['files' => fn($q) => $q->whereCollection('avatar')])->get();

foreach ($users as $user) {
    // ✅ Works - avatar was eager loaded
    $avatar = $user->fileFromLoaded('avatar');
    
    // ❌ Returns null - cover_photo was NOT eager loaded
    $cover = $user->fileFromLoaded('cover_photo');
}
```

**Solution:** Eager load all collections you need:

```php
$users = User::with(['files' => function ($query) {
    $query->whereIn('collection', ['avatar', 'cover_photo']);
}])->get();

foreach ($users as $user) {
    // ✅ Both work now
    $avatar = $user->fileFromLoaded('avatar');
    $cover = $user->fileFromLoaded('cover_photo');
}
```

### Relationship Loaded vs Collection Loaded

The methods check if the `files` **relationship** is loaded, not whether a specific **collection** is loaded. This means:

```php
// Eager load only avatars
$user = User::with(['files' => fn($q) => $q->whereCollection('avatar')])->first();

// Relationship IS loaded (the 'files' property exists)
// But only contains avatars

$avatar = $user->fileFromLoaded('avatar');     // ✅ Returns the avatar
$thumbnail = $user->fileFromLoaded('thumbnail'); // ⚠️ Returns null (not in loaded data)
```

If you try to access a collection that wasn't included in the constraint, `fileFromLoaded()` will return `null` or an empty collection **without triggering an additional query**. The method assumes you intentionally didn't load that collection.

**When to use constrained loading:**
- ✅ You know exactly which collections you need
- ✅ You want to minimize data transfer
- ✅ Performance is critical

**When to load all files:**
```php
// Load everything if you need multiple collections
$users = User::with('files')->get();
```

## Fallback Behavior

The `fileFromLoaded()` and `getFilesFromLoaded()` methods are safe to use even when you forget to eager load:

```php
// Forgot to eager load? No problem, it still works!
$user = User::find(1);

// Falls back to querying when relationship not loaded
$avatar = $user->fileFromLoaded('avatar'); // Still returns the file
```

This fallback ensures your code doesn't break, but you'll still have the N+1 problem. The methods are designed to:
- ✅ Be performant when you remember to eager load
- ✅ Still work when you forget to eager load

## Debugging N+1 Queries

Use Laravel Debugbar or Telescope to identify N+1 problems:

```php
// Enable query logging
DB::enableQueryLog();

$users = User::all();
foreach ($users as $user) {
    $avatar = $user->file('avatar');
}

// Check query count
dd(count(DB::getQueryLog())); // Should be 2, not 101!
```

## Best Practices Summary

1. **Always eager load** when accessing files for multiple models
2. **Use `fileFromLoaded()`** instead of `file()` in loops and resources
3. **Use `getFilesFromLoaded()`** instead of `getFiles()` in loops
4. **Constrain eager loading** to specific collections when possible
5. **Use debugging tools** to catch N+1 problems during development

## Real-World Example

Here's a complete example of a typical API endpoint:

```php
// routes/api.php
Route::get('/tenants', [TenantController::class, 'index']);

// app/Http/Controllers/TenantController.php
class TenantController extends Controller
{
    public function index()
    {
        $tenants = Tenant::query()
            ->with([
                'files' => fn($q) => $q->whereCollection('profile_picture'),
                'store.files' => fn($q) => $q->whereCollection('logo'),
            ])
            ->paginate(20);

        return TenantResource::collection($tenants);
    }
}

// app/Http/Resources/TenantResource.php
class TenantResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->getKey(),
            'name' => $this->name,
            'email' => $this->email,
            'profile_picture_url' => $this->fileFromLoaded('profile_picture')?->url(),
            'store' => StoreResource::make($this->whenLoaded('store')),
        ];
    }
}

// app/Http/Resources/StoreResource.php
class StoreResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'logo_url' => $this->fileFromLoaded('logo')?->url(),
        ];
    }
}
```

This pattern ensures:
- ✅ Only 3 queries total (tenants, files, stores)
- ✅ No N+1 problems
- ✅ Clean, maintainable code
- ✅ Fast response times

## Next Steps

- [Collections Guide](./collections.md) - Learn more about organizing files
- [API Reference](../api/trait-methods.md) - Complete method documentation
- [Advanced Scopes](../advanced/scopes.md) - Query optimization techniques
