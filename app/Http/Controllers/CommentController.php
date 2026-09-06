<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesWorkspace;
use App\Models\Row;
use App\Models\RowComment;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    use AuthorizesWorkspace;

    public function index(Row $row)
    {
        $this->rowForUser($row);

        return response()->json([
            'comments' => $row->comments()->with('user')->latest()->get()->map(fn (RowComment $c) => [
                'id' => $c->id,
                'body' => $c->body,
                'author' => $c->user->name,
                'created_at' => $c->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function store(Request $request, Row $row)
    {
        $this->rowForUser($row);
        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $comment = $row->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $data['body'],
        ]);

        return response()->json([
            'id' => $comment->id,
            'body' => $comment->body,
            'author' => $request->user()->name,
            'created_at' => $comment->created_at?->toIso8601String(),
        ], 201);
    }
}
