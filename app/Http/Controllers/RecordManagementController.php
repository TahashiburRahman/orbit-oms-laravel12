<?php

namespace App\Http\Controllers;

use App\Support\RecordDeletion;

class RecordManagementController extends Controller
{
    public function destroy(string $resource, int $record)
    {
        return redirect()->route(RecordDeletion::delete($resource, $record))->with('status', 'Record deleted. Its audit history is retained.');
    }
}
