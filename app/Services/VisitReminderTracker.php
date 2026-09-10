<?php

namespace App\Services;

use App\Models\JadwalVisit;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotency for visit reminders without requiring a production migration.
 *
 * Prefers jadwal_visits.notification_sent_at when the column exists.
 * Otherwise stores sent visit IDs in storage/app/private/visit_reminders_sent.json.
 */
class VisitReminderTracker
{
    public function hasColumn(): bool
    {
        try {
            return Schema::hasColumn('jadwal_visits', 'notification_sent_at');
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function alreadySent(JadwalVisit $visit): bool
    {
        if ($this->hasColumn()) {
            return filled($visit->notification_sent_at);
        }

        return in_array((int) $visit->id, $this->fileIds(), true);
    }

    public function markSent(JadwalVisit $visit): void
    {
        if ($this->hasColumn()) {
            $visit->forceFill(['notification_sent_at' => now()])->save();

            return;
        }

        $ids = $this->fileIds();
        $id = (int) $visit->id;
        if (! in_array($id, $ids, true)) {
            $ids[] = $id;
            $this->writeFileIds($ids);
        }
    }

    /**
     * @return list<int>
     */
    private function fileIds(): array
    {
        $path = $this->filePath();
        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_map('intval', $decoded));
    }

    /**
     * @param  list<int>  $ids
     */
    private function writeFileIds(array $ids): void
    {
        $path = $this->filePath();
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        $fh = fopen($path, 'c+');
        if ($fh === false) {
            return;
        }

        try {
            flock($fh, LOCK_EX);
            ftruncate($fh, 0);
            rewind($fh);
            fwrite($fh, json_encode(array_values(array_unique($ids))));
            fflush($fh);
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }

    private function filePath(): string
    {
        return storage_path('app/private/visit_reminders_sent.json');
    }
}
