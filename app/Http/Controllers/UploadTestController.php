<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Does a file upload reach PHP on this server at all?
 *
 * Signup on live answers 403 to any POST carrying a file, and the 403 is
 * Apache's own error page — so the request is being refused before Laravel
 * sees it. Everything about signup is therefore beside the point until this
 * one question is settled, and signup is a poor way to ask it: it validates
 * twenty fields and writes to four tables.
 *
 * This is the question on its own. No auth, no CSRF, no database — a file goes
 * in, what arrived comes back. If this 403s, nothing in the application is
 * wrong. If it answers, the block is narrower than it looks and worth chasing.
 *
 * Delete it once the answer is in hand.
 */
class UploadTestController extends Controller
{
    /**
     * The page, on the portal. Its form posts by AJAX to the API route below,
     * so the request takes the same shape the Become a Partner form's upload
     * takes — same host, same door, one file and nothing else.
     */
    public function show(): View
    {
        return view('upload-test');
    }

    public function store(Request $request): JsonResponse
    {
        $file = $request->file('document');

        return response()->json([
            'reached_php' => true,
            'method' => $request->method(),
            'content_type' => $request->header('Content-Type'),
            'content_length' => $request->header('Content-Length'),
            'file' => $file ? [
                'name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime' => $file->getMimeType(),
                'valid' => $file->isValid(),
            ] : null,
            // Worth knowing in the same breath: a request that arrives but is
            // too big for PHP fails differently, and silently.
            'php' => [
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'max_execution_time' => ini_get('max_execution_time'),
                'file_uploads' => (bool) ini_get('file_uploads'),
            ],
        ]);
    }
}
