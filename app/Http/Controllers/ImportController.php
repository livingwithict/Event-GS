<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportRegistrationsRequest;
use App\Imports\RegistrationsImport;
use App\Models\Event;
use App\Models\ImportBatch;
use Maatwebsite\Excel\Facades\Excel;

class ImportController extends Controller
{
    public function import(ImportRegistrationsRequest $request, Event $event)
    {
        $file = $request->file('file');

        $batch = ImportBatch::create([
            'event_id' => $event->id,
            'imported_by' => auth()->id(),
            'file_name' => $file->getClientOriginalName(),
            'status' => 'pending',
        ]);

        $import = new RegistrationsImport($event, batch: $batch);
        Excel::import($import, $file);

        $batch->refresh();

        return response()->json([
            'batch_id' => $batch->id,
            'imported' => $import->getImportedCount(),
            'failed' => count($import->getErrors()),
            'total' => $batch->total_rows,
            'errors' => $import->getErrors(),
        ]);
    }
}
