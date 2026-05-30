<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportRegistrationsRequest;
use App\Imports\RegistrationsImport;
use App\Models\Event;
use Maatwebsite\Excel\Facades\Excel;

class ImportController extends Controller
{
    public function import(ImportRegistrationsRequest $request, Event $event)
    {
        $import = new RegistrationsImport($event);
        Excel::import($import, $request->file('file'));

        return response()->json([
            'imported' => $import->getImportedCount(),
            'errors' => $import->getErrors(),
        ]);
    }
}
