<?php

namespace App\Http\Controllers;

use App\Models\BroadcastMessage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Telegram\Bot\Laravel\Facades\Telegram;

class BroadcastMessageController extends Controller
{
    public function index()
    {
        $messages = BroadcastMessage::orderByDesc('created_at')
            ->select('id', 'title', 'status', 'sent_at', 'created_at')
            ->get();

        return response()->json($messages);
    }

    public function show($id)
    {
        $message = BroadcastMessage::findOrFail($id);
        return response()->json($message);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        $bm = BroadcastMessage::create([
            'title' => $validated['title'],
            'content' => $validated['content'],
            'status' => 'draft',
            'created_by' => Auth::id(),
        ]);

        return response()->json($bm, 201);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        $bm = BroadcastMessage::findOrFail($id);
        $bm->update($validated);

        return response()->json($bm);
    }

    public function destroy($id)
    {
        $bm = BroadcastMessage::findOrFail($id);
        $bm->delete();
        return response()->json(['deleted' => true]);
    }

    public function send($id)
    {
        $bm = BroadcastMessage::findOrFail($id);

        // All users
        $users = User::whereNotNull('chat_id')->pluck('chat_id');
        $sent = 0; $failed = 0;

        foreach ($users as $chatId) {
            try {
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => $bm->content,
                    'parse_mode' => 'HTML',
                ]);
                $sent++;
            } catch (\Exception $e) {
                $failed++;
                Log::error('Broadcast send failed to '.$chatId.': '.$e->getMessage());
            }
        }

        $bm->status = 'sent';
        $bm->sent_at = now();
        $bm->save();

        return response()->json([
            'status' => 'ok',
            'sent' => $sent,
            'failed' => $failed,
        ]);
    }
}