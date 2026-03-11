# File Expiration

Set expiration dates on files for automatic cleanup with the prune command.

## Setting Expiration

### During Upload

```php
$file = $post->attach('temporary_download', $uploadedFile);
$file->expires_at = now()->addDays(7);
$file->save();
```

### After Upload

```php
$file = File::find($fileId);
$file->expires_at = now()->addHours(24);
$file->save();
```

### Bulk Setting

```php
// Set expiration on all files in a collection
$post->files('temporary')->each(function ($file) {
    $file->update(['expires_at' => now()->addDays(30)]);
});
```

## Checking Expiration

### Is Expired

```php
if ($file->isExpired()) {
    // File has expired
}
```

### Time Until Expiration

```php
if ($file->expires_at) {
    echo $file->expires_at->diffForHumans(); // "in 2 days"
}
```

## Querying Expired Files

### Using Scope

```php
use Filexus\Models\File;

// Get all expired files
$expired = File::whereExpired()->get();

// Get non-expired files
$active = File::whereNotExpired()->get();
```

### Manual Query

```php
// Files that expired in the last week
$recentlyExpired = File::where('expires_at', '<=', now())
    ->where('expires_at', '>=', now()->subWeek())
    ->get();
```

## Automatic Cleanup

Expired files are automatically deleted by the prune command:

```bash
php artisan filexus:prune --expired
```

Schedule it in `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Run daily at 2 AM
    $schedule->command('filexus:prune --expired')->dailyAt('02:00');
}
```

## Use Cases

### Temporary Downloads

```php
// Downloadable report valid for 48 hours
$report = $user->attach('reports', $generatedPdf);
$report->expires_at = now()->addHours(48);
$report->save();

// Email the link
Mail::to($user)->send(new ReportReady($report));
```

### Temporary Uploads Before Processing

```php
// Upload expires after 1 hour if not processed
$upload = $session->attach('pending_upload', $file);
$upload->expires_at = now()->addHour();
$upload->save();

// Process later...
ProcessUploadJob::dispatch($upload)->delay(now()->addMinutes(5));
```

### Trial Period Content

```php
// Demo files that expire when trial ends
$trial = $user->trial_ends_at;

foreach ($demoFiles as $file) {
    $attachment = $user->attach('demo_content', $file);
    $attachment->expires_at = $trial;
    $attachment->save();
}
```

### Promotional Content

```php
// Holiday campaign assets that expire after the event
$campaign = Campaign::find(1);
$endDate = Carbon::parse('2024-12-31 23:59:59');

foreach ($promotionalImages as $image) {
    $file = $campaign->attach('promotional', $image);
    $file->expires_at = $endDate;
    $file->save();
}
```

## Warning Users

### Check Before Download

```php
public function download(File $file)
{
    if ($file->isExpired()) {
        abort(410, 'This file has expired and is no longer available.');
    }

    if ($file->expires_at && $file->expires_at->isFuture()) {
        $hoursLeft = $file->expires_at->diffInHours();

        if ($hoursLeft < 24) {
            session()->flash('warning', "This file expires in $hoursLeft hours.");
        }
    }

    return response()->download(
        Storage::disk($file->disk)->path($file->path),
        $file->original_name
    );
}
```

### Display Expiration Notice

```vue
<template v-if="file.expires_at">
    <div class="alert alert-warning">
        <template v-if="file.isExpired()">
            <strong>Expired:</strong> This file is no longer available.
        </template>
        <template v-else>
            <strong>Expires:</strong> {{ file.expires_at.diffForHumans() }}
        </template>
    </div>
</template>
```

## Extending Expiration

### Manual Extension

```php
$file = File::find($fileId);

// Extend by 7 days
$file->expires_at = $file->expires_at->addDays(7);
$file->save();
```

### On Access

Extend expiration each time file is accessed:

```php
public function show(File $file)
{
    if ($file->expires_at) {
        // Extend expiration by 24 hours on access
        $file->expires_at = now()->addHours(24);
        $file->save();
    }

    return view('files.show', compact('file'));
}
```

## Batch Operations

### Set Expiration on Upload

```php
public function uploadTemp(Request $request)
{
    $files = $request->file('files');
    $expiresAt = now()->addDays(7);

    foreach ($files as $uploadedFile) {
        $file = $user->attach('temp', $uploadedFile);
        $file->expires_at = $expiresAt;
        $file->save();
    }

    return back()->with('success', 'Files uploaded. Expires in 7 days.');
}
```

### Clean Up Specific Collection

```php
// Mark all files in collection as expiring soon
$post->files('temporary')->each(function ($file) {
    $file->update(['expires_at' => now()->addHours(1)]);
});
```

## API Response

```php
use Illuminate\Http\Resources\Json\JsonResource;

class FileResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->original_name,
            'url' => $this->url(),
            'expiration' => [
                'expires_at' => $this->expires_at?->toISOString(),
                'is_expired' => $this->isExpired(),
                'expires_in_human' => $this->expires_at?->diffForHumans(),
                'hours_remaining' => $this->expires_at?->diffInHours(),
            ],
        ];
    }
}
```

## Prune Command Options

```bash
# Prune only expired files
php artisan filexus:prune --expired

# Prune both expired and orphaned files
php artisan filexus:prune

# Dry run (show what would be deleted)
php artisan filexus:prune --expired --dry-run
```

## Event Handling

Listen for file deletions due to expiration:

```php
use Filexus\Events\FileDeleted;

Event::listen(FileDeleted::class, function (FileDeleted $event) {
    if ($event->file->expires_at && $event->file->isExpired()) {
        Log::info("Expired file deleted: $event->file->original_name");

        // Notify user
        if ($event->file->fileable instanceof User) {
            $event->file->fileable->notify(
                new FileExpiredNotification($event->file)
            );
        }
    }
});
```

## Best Practices

1. **Set reasonable expiration periods**: Don't set arbitrary short periods
2. **Notify users**: Warn users before files expire
3. **Log expirations**: Keep track of expired files for auditing
4. **Schedule pruning**: Run the prune command regularly
5. **Grace periods**: Consider a grace period before permanent deletion
6. **User extensions**: Allow users to extend expiration when needed
