<?php

namespace App\Http\Controllers\Api\v2;

use App\Http\Controllers\Controller;
use App\Models\BlogCoverImage;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use App\Models\AdminBlogKeyword;
use App\Models\AdminBlogType;
use App\Models\AdminBlog;
use Illuminate\Http\Request;

class AdminBlogController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index($store = NULL): JsonResponse
    {
        try {
            // Retrieve paginated blogs, manipulate images, and return JSON response
            $blogs = $this->getBlogsQuery()
                ->select('id', 'type', 'title', 'sub_title', 'key_word', 'thumbnail', 'image', 'slug', 'permalink', 'canonical_url', 'custom_script', 'website', 'created_at', 'updated_at')
                ->where('store_id', $store)
                ->Paginate(10)->onEachSide(1)->setPath('');

            if (isset($blogs) && count($blogs)) {
                $this->manipulateBlogImages($blogs);
            }

            $coverImage = BlogCoverImage::where("store_id", $store)->first();

            return sendResponse("Successful", [
                "data" => $blogs,
                "coverImage" => $this->getCoverImageURL($coverImage),
            ]);
        } catch (\Exception $e) {
            // Return error response in case of exception
            return serverError();
        }
    }


    public function getCoverImageURL($coverImage)
    {
        $imageURL = NULL;
        if (isset($coverImage->image)) {
            $imageURL = asset('BlogImages') . "/" . $coverImage->image ?? "";
        }

        return $imageURL;
    }

    public function getStoreByURL($name = "")
    {
        $store = Store::where('url', $name)->where('expiry_date', '>=', Carbon::now())->first();
        return $store->id ?? "";
    }

    /**
     * Retrieve blogs query.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function getBlogsQuery()
    {
        // Query to retrieve blogs with status 1 and order by ID descending
        return AdminBlog::query()->with("type")->where('status', 1)->whereNull('deleted_at')->orderBy('id', 'desc');
    }

    /**
     * Manipulate blog image paths for display.
     *
     * @param \Illuminate\Contracts\Pagination\LengthAwarePaginator $blogs
     * @return void
     */
    private function manipulateBlogImages(&$blogs): void
    {
        // Manipulate each blog's image URLs and keywords
        foreach ($blogs as $blog) {
            $this->setBlogImageUrls($blog);
            $this->setBlogKeywords($blog);
        }
    }

    /**
     * Set blog image URLs.
     *
     * @param \App\Models\AdminBlog $blog
     * @return void
     */
    private function setBlogImageUrls(AdminBlog $blog): void
    {
        // Set the thumbnail and image URLs for a blog
        $blog->thumbnail = $this->getImageUrl($blog->thumbnail);
        $blog->image = $this->getImageUrl($blog->image);
    }

    /**
     * Get full image URL.
     *
     * @param string|null $imageName
     * @return string
     */
    private function getImageUrl(?string $imageName): string
    {
        // Generate full image URL based on the image name
        return empty($imageName) ? url('/assets/images/icon/default_category_icon.jpg') : url('/BlogImages/' . $imageName);
    }

    /**
     * Set blog keywords.
     *
     * @param \App\Models\AdminBlog $blog
     * @return void
     */
    private function setBlogKeywords(AdminBlog $blog): void
    {
        // Set keywords for a blog
        $keywordIds = is_array($blog->key_word) ? $blog->key_word : json_decode($blog->key_word, true);
        $keywordNames = [];

        if (!is_null($keywordIds)) {
            $allKeywords = AdminBlogKeyword::all();

            foreach ($keywordIds as $keywordId) {
                $keyword = $allKeywords->where('id', $keywordId)->first();

                if ($keyword) {
                    $keywordNames[] = $keyword->name;
                }
            }
        }

        $blog->key_word = $keywordNames;
    }

    /**
     * Generate an error response.
     *
     * @param int $statusCode
     * @param string $message
     * @return array
     */
    private function generateErrorResponse($statusCode, $message): array
    {
        // Format the error response
        return [
            'status' => $statusCode,
            'error_message' => $message,
        ];
    }

    /**
     * Display the specified resource.
     *
     * @param string $slug
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($slug): JsonResponse
    {
        try {
            $blog = AdminBlog::where(function ($q) use ($slug) {
                $q->where('permalink', $slug)->orWhere('slug', $slug);
            })->where('status', 1)->first();

            if (isset($blog)) {
                $this->setBlogImageUrls($blog);
                $this->setBlogKeywords($blog);
            }

            return sendResponse("Successful", [
                "data" => $blog
            ]);
        } catch (\Exception $e) {
            return serverError();
        }
    }

    /**
     * Retrieve all blog types along with their associated posts and return as JSON response.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function blogTypes($store = NULL)
    {
        try {
            $blogTypes = AdminBlogType::where("store_id", $store)->get();

            return sendResponse("Successful", [
                "data" => $blogTypes
            ]);
        } catch (\Exception $e) {
            // Return error response in case of exception
            return serverError();
        }
    }

    /**
     * Retrieve all blog types along with their associated posts and return as JSON response.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function typeBlogs($id)
    {
        try {
            if (!is_null($id) && empty($id)) {
                return response()->json(['status' => 404, 'message' => 'ID not found.']);
            }

            $posts = $this->getBlogsQuery()
                ->select('id', 'type', 'title', 'sub_title', 'key_word', 'thumbnail', 'image', 'slug', 'permalink', 'canonical_url', 'custom_script', 'website', 'created_at', 'updated_at')
                ->where('type', $id)
                ->where('status', 1)
                ->Paginate(5)->onEachSide(1)->setPath('');

            if (isset($posts) && count($posts)) {
                $this->manipulateBlogImages($posts);
            }

            return sendResponse("Successful", [
                "data" => $posts
            ]);
        } catch (\Exception $e) {
            // Return error response in case of exception
            return serverError();
        }
    }

    public function popularBlog($store = NULL)
    {
        try {
            // Retrieve paginated blogs, manipulate images, and return JSON response
            $blogs = $this->getBlogsQuery()
                ->select('id', 'type', 'title', 'sub_title', 'key_word', 'thumbnail', 'image', 'slug', 'permalink', 'canonical_url', 'custom_script', 'website', 'created_at', 'updated_at')
                ->where("store_id", $store)
                ->where('popular', 1)
                ->where('status', 1)
                ->Paginate(4)->onEachSide(1)->setPath('');

            if (isset($blogs) && count($blogs)) {
                foreach ($blogs as $blog) {
                    $this->setBlogImageUrls($blog);
                }
            }

            $coverImage = BlogCoverImage::where("store_id", $store)->first();

            return sendResponse("Successful", [
                "data" => $blogs,
                "coverImage" => $this->getCoverImageURL($coverImage),
            ]);
        } catch (\Exception $e) {
            // Return error response in case of exception
            return serverError();
        }
    }


    public function recentBlog($store = NULL)
    {
        try {
            // Retrieve paginated blogs, manipulate images, and return JSON response
            $blogs = $this->getBlogsQuery()
                ->select('id', 'type', 'title', 'sub_title', 'key_word', 'thumbnail', 'image', 'slug', 'permalink', 'canonical_url', 'custom_script', 'website', 'created_at', 'updated_at')
                ->where("store_id", $store)
                ->where('status', 1)
                ->latest()
                ->Paginate(4)->onEachSide(1)->setPath('');

            if (isset($blogs) && count($blogs)) {
                foreach ($blogs as $blog) {
                    $this->setBlogImageUrls($blog);
                }
            }

            $coverImage = BlogCoverImage::where("store_id", $store)->first();

            return sendResponse("Successful", [
                "data" => $blogs,
                "coverImage" => $this->getCoverImageURL($coverImage),
            ]);
        } catch (\Exception $e) {
            // Return error response in case of exception
            return serverError();
        }
    }

    public function siteMap($store = NULL)
    {
        try {
            $blogs = AdminBlog::select('id', 'slug', 'permalink', 'canonical_url', 'website', 'created_at', 'updated_at')
                ->where("store_id", $store)
                ->whereNull('deleted_at')
                ->where('status', 1)
                ->orderBy('id', 'desc')
                ->get();

            return sendResponse("Successful", [
                "data" => $blogs,
            ]);

        } catch (\Exception $e) {
            return serverError();
        }

    }

    /**
     * Generate a standard response format for API.
     *
     * @param \Illuminate\Contracts\Pagination\LengthAwarePaginator $response
     * @return array
     */
    private function generateResponse($response): array
    {
        // Format the response data
        return [
            'status' => 200,
            'total' => $response->total(),
            'per_page' => $response->perPage(),
            'current_page' => $response->currentPage(),
            'results' => $response->items(),
            'next_page_url' => $response->nextPageUrl(),
            'prev_page_url' => $response->previousPageUrl()
        ];
    }
}
