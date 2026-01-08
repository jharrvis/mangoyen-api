<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Adoption;
use App\Models\Message;
use App\Events\NewMessage;
use App\Events\UserTyping;
use App\Helpers\ContentFilter;
use App\Services\AIModerator;
use App\Services\MangoyenHelper;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    /**
     * Get messages for an adoption (only if approved)
     */
    public function index(Request $request, $adoptionId)
    {
        $user = $request->user();
        $adoption = Adoption::with(['adopter', 'cat.shelter.user'])->findOrFail($adoptionId);

        // Check if user is part of this adoption
        if ($adoption->adopter_id !== $user->id && $adoption->cat->shelter->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Only allow chat for approved adoptions
        if ($adoption->status !== 'approved' && $adoption->status !== 'waiting_payment') {
            return response()->json(['message' => 'Chat hanya tersedia untuk adopsi yang sudah disetujui'], 400);
        }

        $messages = Message::where('adoption_id', $adoptionId)
            ->with('sender:id,name,avatar')
            ->orderBy('created_at', 'asc')
            ->get();

        // Mark messages as read for current user
        Message::where('adoption_id', $adoptionId)
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        // Determine partner (the other person in the chat)
        $isAdopter = $adoption->adopter_id === $user->id;
        $partner = $isAdopter ? $adoption->cat->shelter->user : $adoption->adopter;

        return response()->json([
            'messages' => $messages,
            'adoption' => [
                'id' => $adoption->id,
                'status' => $adoption->status,
                'cat_name' => $adoption->cat->name,
                'adopter_name' => $adoption->adopter->name,
                'shelter_name' => $adoption->cat->shelter->name,
            ],
            'partner' => [
                'id' => $partner->id,
                'name' => $partner->name,
                'avatar' => $partner->avatar,
                'last_seen_at' => $partner->last_seen_at,
            ],
        ]);
    }

    /**
     * Send a message - ultra minimal for speed
     */
    public function store(Request $request, $adoptionId)
    {
        $user = $request->user();

        // Skip heavy validation for now - just insert
        $content = $request->input('content', '');
        if (empty($content)) {
            return response()->json(['message' => 'Content required'], 400);
        }

        // Check if this is a @mangoyen mention (should not be moderated)
        $isMangoyenMention = MangoyenHelper::hasMention($content);

        // Layer 1: Apply regex content filter (instant) - skip for @mangoyen
        $filterResult = ContentFilter::filter($content);
        $filteredContent = $filterResult['text'];
        $wasCensored = $isMangoyenMention ? false : $filterResult['censored']; // Don't censor @mangoyen
        $aiWarning = null;

        // Layer 2: AI moderation - skip for @mangoyen mentions
        $aiRuleViolated = null;
        if (!$wasCensored && !$isMangoyenMention && AIModerator::isConfigured()) {
            $aiResult = AIModerator::moderate($content);

            // Lower confidence threshold to 0.5 - AI should be more aggressive
            if ($aiResult['flagged'] && $aiResult['confidence'] >= 0.5) {
                $wasCensored = true;
                $aiWarning = $aiResult['reason'];
                $aiRuleViolated = $aiResult['rule_violated'] ?? null;
                $detectedPattern = $aiResult['detected_pattern'] ?? null;

                // Censor the message based on AI detection
                $rulePart = $aiRuleViolated ? "[$aiRuleViolated]" : '';
                $filteredContent = "🤖[AI-disensor$rulePart: " . ($aiResult['type'] ?? 'kontak') . ']';
            }
        }

        // Direct insert user message
        $message = Message::create([
            'adoption_id' => $adoptionId,
            'sender_id' => $user->id,
            'content' => substr($filteredContent, 0, 1000),
            'is_censored' => $wasCensored,
        ]);

        // MangOyen Admin Bot - send AI-generated warning message if violation detected
        $mangoyenMessage = null;
        if ($wasCensored) {
            // Generate natural, context-aware MangOyen response using AI
            $violationType = $aiWarning ? ($filterResult['types'][0] ?? 'contact') : 'contact';
            if (!empty($filterResult['types'])) {
                $violationType = $filterResult['types'][0];
            }

            $mangoyenContent = AIModerator::generateMangoyenResponse(
                $content,  // Original message before censoring
                $violationType,
                $aiWarning ?? 'Informasi kontak terdeteksi',
                $aiRuleViolated  // Pass the rule that was violated
            );

            // Insert MangOyen bot message
            $mangoyenMessage = Message::create([
                'adoption_id' => $adoptionId,
                'sender_id' => null, // null = system/bot message
                'content' => $mangoyenContent,
                'is_censored' => false,
            ]);
        }

        // Check for @mangoyen mention - answer user questions
        if (!$wasCensored && MangoyenHelper::hasMention($content)) {
            $question = MangoyenHelper::extractQuestion($content);
            if (!empty($question)) {
                $answerContent = MangoyenHelper::ask($question);

                // Insert MangOyen Q&A response
                $mangoyenMessage = Message::create([
                    'adoption_id' => $adoptionId,
                    'sender_id' => null,
                    'content' => $answerContent,
                    'is_censored' => false,
                ]);
            }
        }

        // Build warning message for UI
        $censoredWarning = null;
        $strikeWarning = null;

        if ($wasCensored) {
            if ($aiWarning) {
                $censoredWarning = '🤖 AI: ' . $aiWarning;
            } else {
                $censoredWarning = ContentFilter::getWarningMessage($filterResult['types']);
            }
            $strikeWarning = ContentFilter::getStrikeWarning();
        }

        return response()->json([
            'message' => [
                'id' => $message->id,
                'adoption_id' => $message->adoption_id,
                'sender_id' => $message->sender_id,
                'content' => $message->content,
                'created_at' => $message->created_at,
                'is_censored' => $wasCensored,
                'censored_warning' => $censoredWarning,
                'strike_warning' => $strikeWarning,
                'ai_moderated' => $aiWarning !== null,
                'sender' => [
                    'id' => $user->id,
                    'name' => $user->name,
                ],
            ],
            'mangoyen_message' => $mangoyenMessage ? [
                'id' => $mangoyenMessage->id,
                'adoption_id' => $mangoyenMessage->adoption_id,
                'sender_id' => null,
                'content' => $mangoyenMessage->content,
                'created_at' => $mangoyenMessage->created_at,
                'is_bot' => true,
                'sender' => [
                    'id' => 0,
                    'name' => '🐱 MangOyen Admin',
                ],
            ] : null,
        ], 201);
    }

    /**
     * Send typing indicator
     */
    public function typing(Request $request, $adoptionId)
    {
        // Skip broadcast for now - it's slow
        // $user = $request->user();
        // $isTyping = $request->boolean('typing', true);
        // broadcast(new UserTyping($adoptionId, $user->id, $user->name, $isTyping))->toOthers();

        return response()->json(['status' => 'ok']);
    }

    /**
     * Fast polling endpoint - get messages since a given ID
     * Also marks received messages as read
     */
    public function since(Request $request, $adoptionId)
    {
        $user = $request->user();
        $lastId = $request->query('last_id', 0);

        // Get adoption with partner info
        $adoption = Adoption::with(['adopter', 'cat.shelter.user'])->find($adoptionId);
        if (!$adoption) {
            return response()->json(['messages' => [], 'count' => 0]);
        }

        // Determine partner
        $isAdopter = $adoption->adopter_id === $user->id;
        $partner = $isAdopter ? $adoption->cat->shelter->user : $adoption->adopter;

        // Get new messages
        $messages = Message::where('adoption_id', $adoptionId)
            ->where('id', '>', $lastId)
            ->with('sender:id,name,avatar')
            ->orderBy('id', 'asc')
            ->limit(50)
            ->get();

        // Mark messages from others as read
        if ($messages->isNotEmpty()) {
            Message::where('adoption_id', $adoptionId)
                ->where('sender_id', '!=', $user->id)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
        }

        // Also get any recently read messages (for sender to see blue checks)
        $recentlyRead = Message::where('adoption_id', $adoptionId)
            ->where('sender_id', $user->id)
            ->whereNotNull('read_at')
            ->where('id', '>', max(0, $lastId - 50))
            ->pluck('read_at', 'id');

        return response()->json([
            'messages' => $messages,
            'count' => $messages->count(),
            'read_status' => $recentlyRead,
            'partner' => [
                'id' => $partner->id,
                'name' => $partner->name,
                'last_seen_at' => $partner->last_seen_at,
            ],
        ]);
    }

    /**
     * Get unread message count for user
     */
    public function unreadCount(Request $request)
    {
        $user = $request->user();

        // Get adoptions where user is involved
        $adoptionIds = Adoption::where('adopter_id', $user->id)
            ->orWhereHas('cat.shelter', fn($q) => $q->where('user_id', $user->id))
            ->pluck('id');

        $count = Message::whereIn('adoption_id', $adoptionIds)
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->count();

        return response()->json(['unread_count' => $count]);
    }
}

