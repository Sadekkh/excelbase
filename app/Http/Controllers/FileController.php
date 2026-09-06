<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:12288'],
        ]);

        $file = $request->file('file');
        $path = $file->store('row-files', 'public');

        return response()->json([
            'name' => $file->getClientOriginalName(),
            'url' => Storage::disk('public')->url($path),
            'size' => $file->getSize(),
        ], 201);
    }
}
