<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CommentController extends Controller
{
    // ============ Public Endpoints ============

    /**
     * Get approved comments for an article (with nested replies)
     */
    public function index(Request $request, $articleId)
    {
        $article = Article::findOrFail($articleId);

        $comments = Comment::where('article_id', $article->id)
            ->approved()
            ->rootLevel()
            ->with(['user:id,name,avatar', 'shelter:id,name,logo', 'replies'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 10));

        return response()->json($comments);
    }

    /**
     * Store a new comment
     */
    public function store(Request $request, $articleId)
    {
        $article = Article::where('id', $articleId)
            ->where('status', 'published')
            ->firstOrFail();

        // Rate limiting check
        $ip = $request->ip();
        $rateLimitKey = "comment_rate:{$ip}";
        $commentCount = Cache::get($rateLimitKey, 0);

        if ($commentCount >= 3) {
            return response()->json([
                'message' => 'Terlalu banyak komentar. Tunggu beberapa menit sebelum mencoba lagi.'
            ], 429);
        }

        // Validate request
        $rules = [
            'content' => 'required|string|min:5|max:2000',
            'parent_id' => 'nullable|exists:comments,id',
            'captcha_answer' => 'required|string',
            'captcha_token' => 'required|string',
            'honeypot' => 'size:0', // Must be empty (bot trap)
        ];

        // Guest fields required if not authenticated
        if (!auth()->check()) {
            $rules['guest_name'] = 'required|string|min:2|max:100';
            $rules['guest_email'] = 'required|email|max:150';
        }

        $validated = $request->validate($rules);

        // Validate CAPTCHA
        if (!$this->validateCaptcha($validated['captcha_token'], $validated['captcha_answer'])) {
            return response()->json([
                'message' => 'Jawaban CAPTCHA salah. Silakan coba lagi.',
                'errors' => ['captcha_answer' => ['Jawaban tidak benar']]
            ], 422);
        }

        // Build comment data
        $commentData = [
            'article_id' => $article->id,
            'content' => $validated['content'],
            'parent_id' => $validated['parent_id'] ?? null,
            'ip_address' => $ip,
            'user_agent' => substr($request->userAgent(), 0, 500),
            'status' => 'pending', // All comments need approval
        ];

        // Set author based on authentication
        $user = auth()->user();
        if ($user) {
            // Check if user is a shelter
            if ($user->shelter) {
                $commentData['shelter_id'] = $user->shelter->id;
            } else {
                $commentData['user_id'] = $user->id;
            }

            // Auto-approve for admin
            if (in_array($user->role, ['admin', 'superadmin'])) {
                $commentData['status'] = 'approved';
                $commentData['approved_at'] = now();
                $commentData['approved_by'] = $user->id;
            }
        } else {
            $commentData['guest_name'] = $validated['guest_name'];
            $commentData['guest_email'] = $validated['guest_email'];
        }

        // Create comment
        $comment = Comment::create($commentData);

        // Update rate limit counter
        Cache::put($rateLimitKey, $commentCount + 1, now()->addMinutes(10));

        // Log for monitoring
        Log::info('Comment created', [
            'article_id' => $article->id,
            'comment_id' => $comment->id,
            'author_type' => $comment->author_type,
            'ip' => $ip
        ]);

        $message = $comment->status === 'approved'
            ? 'Komentar berhasil ditambahkan!'
            : 'Komentar akan ditampilkan setelah dimoderasi.';

        return response()->json([
            'message' => $message,
            'comment' => $comment->load(['user:id,name,avatar', 'shelter:id,name,logo']),
            'requires_moderation' => $comment->status === 'pending'
        ], 201);
    }

    /**
     * Generate CAPTCHA challenge
     */
    public function getCaptcha()
    {
        $num1 = rand(1, 10);
        $num2 = rand(1, 10);
        $operation = rand(0, 1) ? '+' : '-';

        // Make sure result is positive for subtraction
        if ($operation === '-' && $num1 < $num2) {
            $temp = $num1;
            $num1 = $num2;
            $num2 = $temp;
        }

        $answer = $operation === '+' ? $num1 + $num2 : $num1 - $num2;

        // Create token with answer (encrypted)
        $token = encrypt([
            'answer' => $answer,
            'expires' => now()->addMinutes(10)->timestamp
        ]);

        return response()->json([
            'question' => "{$num1} {$operation} {$num2} = ?",
            'token' => $token
        ]);
    }

    /**
     * Validate CAPTCHA answer
     */
    private function validateCaptcha(string $token, string $answer): bool
    {
        try {
            $data = decrypt($token);

            // Check expiration
            if ($data['expires'] < now()->timestamp) {
                return false;
            }

            // Check answer
            return (int) $answer === (int) $data['answer'];
        } catch (\Exception $e) {
            return false;
        }
    }

    // ============ Admin Endpoints ============

    /**
     * Get all comments for moderation
     */
    public function adminIndex(Request $request)
    {
        $user = auth()->user();
        if (!$user || !in_array($user->role, ['admin', 'superadmin'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $query = Comment::with([
            'article:id,title,slug',
            'user:id,name,avatar',
            'shelter:id,name,logo',
            'parent:id,content'
        ]);

        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Filter by article
        if ($request->has('article_id')) {
            $query->where('article_id', $request->article_id);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('content', 'like', "%{$search}%")
                    ->orWhere('guest_name', 'like', "%{$search}%")
                    ->orWhere('guest_email', 'like', "%{$search}%");
            });
        }

        $comments = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        // Add stats
        $stats = [
            'pending' => Comment::pending()->count(),
            'approved' => Comment::approved()->count(),
            'spam' => Comment::spam()->count(),
            'total' => Comment::count()
        ];

        return response()->json([
            'comments' => $comments,
            'stats' => $stats
        ]);
    }

    /**
     * Approve a comment
     */
    public function approve(Request $request, $id)
    {
        $user = auth()->user();
        if (!$user || !in_array($user->role, ['admin', 'superadmin'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $comment = Comment::findOrFail($id);
        $comment->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $user->id
        ]);

        return response()->json([
            'message' => 'Komentar berhasil disetujui',
            'comment' => $comment
        ]);
    }

    /**
     * Reject a comment
     */
    public function reject(Request $request, $id)
    {
        $user = auth()->user();
        if (!$user || !in_array($user->role, ['admin', 'superadmin'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $comment = Comment::findOrFail($id);
        $comment->update(['status' => 'rejected']);

        return response()->json([
            'message' => 'Komentar berhasil ditolak'
        ]);
    }

    /**
     * Mark as spam
     */
    public function markSpam(Request $request, $id)
    {
        $user = auth()->user();
        if (!$user || !in_array($user->role, ['admin', 'superadmin'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $comment = Comment::findOrFail($id);
        $comment->update(['status' => 'spam']);

        return response()->json([
            'message' => 'Komentar ditandai sebagai spam'
        ]);
    }

    /**
     * Bulk action
     */
    public function bulkAction(Request $request)
    {
        $user = auth()->user();
        if (!$user || !in_array($user->role, ['admin', 'superadmin'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:comments,id',
            'action' => 'required|in:approve,reject,spam,delete'
        ]);

        $comments = Comment::whereIn('id', $validated['ids']);

        switch ($validated['action']) {
            case 'approve':
                $comments->update([
                    'status' => 'approved',
                    'approved_at' => now(),
                    'approved_by' => $user->id
                ]);
                $message = count($validated['ids']) . ' komentar disetujui';
                break;
            case 'reject':
                $comments->update(['status' => 'rejected']);
                $message = count($validated['ids']) . ' komentar ditolak';
                break;
            case 'spam':
                $comments->update(['status' => 'spam']);
                $message = count($validated['ids']) . ' komentar ditandai spam';
                break;
            case 'delete':
                $comments->delete();
                $message = count($validated['ids']) . ' komentar dihapus';
                break;
        }

        return response()->json(['message' => $message]);
    }

    /**
     * Delete a comment
     */
    public function destroy(Request $request, $id)
    {
        $user = auth()->user();
        if (!$user || !in_array($user->role, ['admin', 'superadmin'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $comment = Comment::findOrFail($id);
        $comment->delete();

        return response()->json(['message' => 'Komentar berhasil dihapus']);
    }
}
