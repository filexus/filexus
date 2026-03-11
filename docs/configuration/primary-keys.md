# Primary Key Types

Filexus supports three types of primary keys for the `files` table: auto-increment integers, UUIDs, and ULIDs.

## Configuration

Configure the primary key type in `config/filexus.php`:

```php
'primary_key_type' => env('FILEXUS_PRIMARY_KEY_TYPE', 'id'),
```

**Available Options:**
- `'id'` - Auto-increment integer (default)
- `'uuid'` - UUID v4
- `'ulid'` - ULID (Universally Unique Lexicographically Sortable Identifier)

::: danger Important
This must be configured **before running migrations**. Changing it after migration requires creating a new migration to alter the table structure.
:::

## Auto-Increment IDs (Default)

Traditional auto-incrementing integer primary keys.

```php
// config/filexus.php
'primary_key_type' => 'id',
```

**Characteristics:**
- Sequential: `1, 2, 3, ...`
- Efficient for indexing and joins
- Smallest storage footprint
- Predictable and exposable via URLs

**Example:**
```php
$file = $post->attach('thumbnail', $uploadedFile);
echo $file->id; // 42
```

## UUIDs

Universally Unique Identifiers (version 4).

```php
// config/filexus.php
'primary_key_type' => 'uuid',
```

Or via environment variable:

```env
FILEXUS_PRIMARY_KEY_TYPE=uuid
```

**Characteristics:**
- Unique across systems: `550e8400-e29b-41d4-a716-446655440000`
- Safe for distributed systems
- No order correlation
- Larger storage requirement (36 characters)

**Example:**
```php
$file = $post->attach('thumbnail', $uploadedFile);
echo $file->id; // "9d4e7a32-1234-4567-89ab-cdef01234567"

// Works seamlessly with relationships
$post->files()->get();
```

**When to Use:**
- Distributed systems or microservices
- Multi-tenant applications
- When you want to avoid exposing sequential IDs
- APIs that need globally unique identifiers

## ULIDs

Universally Unique Lexicographically Sortable Identifiers.

```php
// config/filexus.php
'primary_key_type' => 'ulid',
```

Or via environment variable:

```env
FILEXUS_PRIMARY_KEY_TYPE=ulid
```

**Characteristics:**
- Unique and sortable: `01ARZ3NDEKTSV4RRFFQ69G5FAV`
- Timestamp-ordered (sortable by creation time)
- URL-safe (no hyphens)
- 26 characters
- Case-insensitive

**Example:**
```php
$file = $post->attach('thumbnail', $uploadedFile);
echo $file->id; // "01HN9T9XQZJ8K6VWXYZ123ABCD"

// ULIDs are naturally sortable
File::orderBy('id')->get(); // Ordered by creation time
```

**When to Use:**
- When you need both uniqueness and sortability
- Time-ordered queries are important
- Distributed systems that need chronological ordering
- Replacing auto-increment IDs while maintaining sort order

## Migration

The migration automatically adapts to your configuration:

```php
// database/migrations/2024_01_01_000000_create_files_table.php
$keyType = config('filexus.primary_key_type', 'id');

Schema::create('files', function (Blueprint $table) use ($keyType) {
    match ($keyType) {
        'uuid' => $table->uuid('id')->primary(),
        'ulid' => $table->ulid('id')->primary(),
        default => $table->id(),
    };

    // Polymorphic relations match the key type
    match ($keyType) {
        'uuid' => $table->uuidMorphs('fileable'),
        'ulid' => $table->ulidMorphs('fileable'),
        default => $table->morphs('fileable'),
    };

    // ... rest of the schema
});
```

## Polymorphic Relations

Filexus automatically configures Laravel's morph types:

```php
// For UUIDs
Model::morphUsingUuids();

// For ULIDs
Model::morphUsingUlids();
```

This ensures polymorphic relationships work correctly with your chosen key type.

## Performance Considerations

### Auto-Increment IDs
- ✅ Fastest for indexing and joins
- ✅ Smallest storage footprint
- ✅ Best for single-server applications
- ❌ Not suitable for distributed systems

### UUIDs
- ✅ Perfect for distributed systems
- ✅ No collision risk across servers
- ❌ Larger storage requirement
- ❌ Random order (not chronological)
- ❌ Slightly slower indexing

### ULIDs
- ✅ Suitable for distributed systems
- ✅ Chronologically sortable
- ✅ Smaller than UUIDs (26 vs 36 chars)
- ✅ Better indexing than random UUIDs
- ❌ Larger than integers

## Comparison Table

| Feature               | Auto-Increment | UUID                   | ULID              |
| --------------------- | -------------- | ---------------------- | ----------------- |
| Size                  | 8 bytes        | 36 chars (UUID string) | 26 chars          |
| Sortable              | ✅ Sequential   | ❌ Random               | ✅ Timestamp-based |
| Unique Across Systems | ❌              | ✅                      | ✅                 |
| Index Performance     | ⭐⭐⭐⭐⭐          | ⭐⭐⭐                    | ⭐⭐⭐⭐              |
| Human Readable        | ✅              | ❌                      | ⚠️ (partly)        |
| URL Safe              | ✅              | ✅ (with hyphens)       | ✅ (no hyphens)    |

## Changing Primary Key Type

::: warning
Changing the primary key type after migration requires manual intervention.
:::

To change the key type:

1. **Create a new migration**:
```bash
php artisan make:migration change_files_table_primary_key
```

2. **Drop and recreate the table** (loses data):
```php
public function up()
{
    Schema::dropIfExists('files');

    // Then recreate with new key type
    require_once __DIR__ . '/2024_01_01_000000_create_files_table.php';
    (new CreateFilesTable)->up();
}
```

3. **Or alter the table** (complex, database-specific)

## Examples by Use Case

### E-commerce Product Images
```php
// config/filexus.php
'primary_key_type' => 'id', // Simple auto-increment is fine
```

### Multi-Tenant SaaS
```php
// config/filexus.php
'primary_key_type' => 'uuid', // Unique across all tenants
```

### Distributed CMS
```php
// config/filexus.php
'primary_key_type' => 'ulid', // Unique and time-sortable
```

### API with Rate Limiting
```php
// config/filexus.php
'primary_key_type' => 'uuid', // Hide sequential patterns
```
