<?php

namespace App\Http\Controllers;

use App\Models\Major;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MajorController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Major::query()->latest()->paginate());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:majors,code'],
            'name' => ['required', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
        ]);

        $major = Major::create($data);

        return response()->json($major, 201);
    }

    public function show(Major $major): JsonResponse
    {
        return response()->json($major->load('classes'));
    }

    public function update(Request $request, Major $major): JsonResponse
    {
        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:20', 'unique:majors,code,'.$major->id],
            'name' => ['sometimes', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
        ]);

        $major->update($data);

        return response()->json($major);
    }

    public function destroy(Major $major): JsonResponse
    {
        $major->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
