<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Move verification documents out of the storage disk and in with the rest of
 * the uploads.
 *
 * They were written with `store('sp-documents', 'public')`, which put them
 * behind /storage/sp-documents/… — a URL that describes how Laravel keeps
 * files rather than what the file is. Everything else the app stores lives at
 * /uploads/<type>/YYYY/MM/, and a provider's photo is already under
 * /uploads/providers, so their documents belong beside it.
 *
 * The year and month come from the application's own date, so a document stays
 * filed under the month it arrived in rather than the month this ran.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('service_providers')
            ->whereNotNull('documents')
            ->get(['id', 'documents', 'created_at']);

        foreach ($rows as $row) {
            $documents = json_decode($row->documents, true);
            if (!is_array($documents) || $documents === []) {
                continue;
            }

            $stamp = $row->created_at ? Carbon::parse($row->created_at) : Carbon::now();
            $relativeDir = 'uploads/providers/' . $stamp->format('Y') . '/' . $stamp->format('m');
            $dir = public_path($relativeDir);

            $moved = false;
            foreach ($documents as $i => $document) {
                $path = $document['path'] ?? '';
                // Rows written after this change already carry the new path.
                if ($path === '' || str_starts_with($path, '/uploads/')) {
                    continue;
                }

                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }

                $filename = basename($path);
                $source = storage_path('app/public/' . $path);
                if (is_file($source)) {
                    rename($source, $dir . DIRECTORY_SEPARATOR . $filename);
                }

                // The row is rewritten even when the file itself is missing:
                // the path it holds is dead either way, and leaving half the
                // list on the old shape would need the reader to support both.
                $documents[$i]['path'] = '/' . $relativeDir . '/' . $filename;
                $moved = true;
            }

            if ($moved) {
                DB::table('service_providers')
                    ->where('id', $row->id)
                    ->update(['documents' => json_encode($documents)]);
            }
        }
    }

    public function down(): void
    {
        // One-way. Nothing writes /storage/sp-documents any more, so putting
        // the files back would point the rows at a location the application
        // can no longer produce or read.
    }
};
