<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Tag;
use App\Services\ArticleGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ArticleController extends Controller
{
    // ========================
    // PUBLIC ENDPOINTS
    // ========================

    /**
     * Get published articles with pagination
     */
    public function index(Request $request)
    {
        $query = Article::published()
            ->with(['author:id,name,avatar', 'tags:id,name,slug']);

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('excerpt', 'like', "%{$search}%");
            });
        }

        // Filter by category
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        // Filter by tag
        if ($request->filled('tag')) {
            $query->byTag($request->tag);
        }

        $articles = $query->latest('published_at')
            ->paginate($request->get('per_page', 10));

        return response()->json($articles);
    }

    /**
     * Get single published article by slug
     */
    public function show($slug)
    {
        $article = Article::published()
            ->with(['author:id,name,avatar', 'tags:id,name,slug'])
            ->where('slug', $slug)
            ->firstOrFail();

        // Increment view count
        $article->incrementViewCount();

        // Get related articles (same category, exclude current)
        $related = Article::published()
            ->where('id', '!=', $article->id)
            ->where('category', $article->category)
            ->with(['tags:id,name,slug'])
            ->latest('published_at')
            ->limit(3)
            ->get();

        return response()->json([
            'article' => $article,
            'related' => $related,
        ]);
    }

    /**
     * Get all categories
     */
    public function categories()
    {
        $categories = Article::published()
            ->select('category')
            ->distinct()
            ->whereNotNull('category')
            ->orderBy('category')
            ->pluck('category');

        return response()->json([
            'categories' => $categories,
        ]);
    }

    /**
     * Get all tags
     */
    public function tags()
    {
        $tags = Tag::withCount([
            'articles' => function ($q) {
                $q->where('is_published', true);
            }
        ])
            ->orderBy('name')
            ->get();

        return response()->json([
            'tags' => $tags,
        ]);
    }

    // ========================
    // ADMIN ENDPOINTS
    // ========================

    /**
     * Get all articles for admin (including drafts)
     */
    public function adminIndex(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $query = Article::with(['author:id,name', 'tags:id,name,slug']);

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('excerpt', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->filled('status')) {
            if ($request->status === 'published') {
                $query->where('is_published', true);
            } elseif ($request->status === 'draft') {
                $query->where('is_published', false);
            }
        }

        // Filter by category
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        $articles = $query->latest()->paginate($request->get('per_page', 15));

        return response()->json($articles);
    }

    /**
     * Create new article
     */
    public function store(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'excerpt' => 'nullable|string|max:500',
            'content' => 'required|string',
            'category' => 'nullable|string|max:50',
            'thumbnail' => 'nullable|string',
            'meta_title' => 'nullable|string|max:70',
            'meta_description' => 'nullable|string|max:160',
            'is_published' => 'boolean',
            'is_ai_generated' => 'boolean',
            'tags' => 'nullable|array',
            'tags.*' => 'string',
        ]);

        // Create slug
        $slug = Str::slug($validated['title']);
        $originalSlug = $slug;
        $counter = 1;
        while (Article::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter++;
        }

        $article = Article::create([
            'author_id' => $request->user()->id,
            'title' => $validated['title'],
            'slug' => $slug,
            'excerpt' => $validated['excerpt'] ?? null,
            'content' => $validated['content'],
            'category' => $validated['category'] ?? null,
            'thumbnail' => $validated['thumbnail'] ?? null,
            'meta_title' => $validated['meta_title'] ?? null,
            'meta_description' => $validated['meta_description'] ?? null,
            'is_ai_generated' => $validated['is_ai_generated'] ?? false,
            'is_published' => $validated['is_published'] ?? false,
            'published_at' => ($validated['is_published'] ?? false) ? now() : null,
        ]);

        // Sync tags
        if (!empty($validated['tags'])) {
            $tagIds = $this->syncTags($validated['tags']);
            $article->tags()->sync($tagIds);
        }

        return response()->json([
            'message' => 'Artikel berhasil dibuat',
            'article' => $article->load('tags'),
        ], 201);
    }

    /**
     * Update article
     */
    public function update(Request $request, $id)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $article = Article::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'excerpt' => 'nullable|string|max:500',
            'content' => 'sometimes|required|string',
            'category' => 'nullable|string|max:50',
            'thumbnail' => 'nullable|string',
            'meta_title' => 'nullable|string|max:70',
            'meta_description' => 'nullable|string|max:160',
            'is_published' => 'boolean',
            'tags' => 'nullable|array',
            'tags.*' => 'string',
        ]);

        // Update slug if title changed
        if (isset($validated['title']) && $validated['title'] !== $article->title) {
            $slug = Str::slug($validated['title']);
            $originalSlug = $slug;
            $counter = 1;
            while (Article::where('slug', $slug)->where('id', '!=', $id)->exists()) {
                $slug = $originalSlug . '-' . $counter++;
            }
            $validated['slug'] = $slug;
        }

        // Set published_at if publishing for first time
        if (isset($validated['is_published']) && $validated['is_published'] && !$article->is_published) {
            $validated['published_at'] = now();
        }

        $article->update($validated);

        // Sync tags
        if (isset($validated['tags'])) {
            $tagIds = $this->syncTags($validated['tags']);
            $article->tags()->sync($tagIds);
        }

        return response()->json([
            'message' => 'Artikel berhasil diupdate',
            'article' => $article->fresh()->load('tags'),
        ]);
    }

    /**
     * Delete article
     */
    public function destroy(Request $request, $id)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $article = Article::findOrFail($id);

        // Delete thumbnail if exists
        if ($article->thumbnail && !str_starts_with($article->thumbnail, 'http')) {
            Storage::disk('public')->delete($article->thumbnail);
        }

        $article->delete();

        return response()->json([
            'message' => 'Artikel berhasil dihapus',
        ]);
    }

    /**
     * Toggle publish status
     */
    public function publish(Request $request, $id)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $article = Article::findOrFail($id);

        $article->is_published = !$article->is_published;

        if ($article->is_published && !$article->published_at) {
            $article->published_at = now();
        }

        $article->save();

        return response()->json([
            'message' => $article->is_published ? 'Artikel dipublish' : 'Artikel di-unpublish',
            'article' => $article,
        ]);
    }

    /**
     * Upload image for article
     */
    public function uploadImage(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        $path = $request->file('image')->store('articles', 'public');

        return response()->json([
            'url' => url('/storage/' . $path),
            'path' => $path,
        ]);
    }

    /**
     * Generate article with AI
     */
    public function generateWithAI(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'topic' => 'required|string|max:200',
        ]);

        try {
            $generator = new ArticleGeneratorService();
            $result = $generator->generate($validated['topic']);

            return response()->json([
                'message' => 'Artikel berhasil di-generate',
                'article' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal generate artikel: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ========================
    // HELPER METHODS
    // ========================

    /**
     * Sync tags - create if not exists
     */
    private function syncTags(array $tagNames): array
    {
        $tagIds = [];

        foreach ($tagNames as $name) {
            $name = trim($name);
            if (empty($name))
                continue;

            $tag = Tag::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name]
            );

            $tagIds[] = $tag->id;
        }

        return $tagIds;
    }
}
