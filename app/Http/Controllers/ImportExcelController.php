<?php

namespace App\Http\Controllers;

use App\Services\ExcelImportService;
use App\Services\InventoryService;
use Illuminate\Http\Request;

class ImportExcelController extends Controller
{
    public function index()
    {
        return view('importar.index');
    }

    public function preview(Request $request, ExcelImportService $service)
    {
        $request->validate(['excel' => ['required', 'file', 'mimes:xls,xlsx,xlsm']]);
        $path = $request->file('excel')->store('imports');
        $preview = $service->preview(storage_path('app/private/' . $path));
        session(['excel_import_path' => $path, 'excel_import_preview' => $preview]);

        return view('importar.preview', $preview);
    }

    public function confirm(Request $request, ExcelImportService $service, InventoryService $inventory)
    {
        $path = session('excel_import_path');
        abort_unless($path, 422, 'No hay una importacion pendiente.');

        $uploaded = new \Illuminate\Http\UploadedFile(
            storage_path('app/private/' . $path),
            basename($path),
            null,
            null,
            true
        );

        $importacion = $service->confirm($uploaded, $request->user()->id, $inventory);
        session()->forget(['excel_import_path', 'excel_import_preview']);

        return redirect()->route('productos.index')->with('ok', "Importación {$importacion->id} confirmada.");
    }
}
