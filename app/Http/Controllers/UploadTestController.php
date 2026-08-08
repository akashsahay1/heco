<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

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
    /** A form, so the same thing can be tried from a browser and from curl. */
    public function show(): Response
    {
        return response(<<<'HTML'
            <!doctype html>
            <meta name="viewport" content="width=device-width,initial-scale=1">
            <title>Upload test</title>
            <body style="font-family:system-ui;max-width:34rem;margin:3rem auto;padding:0 1rem">
                <h1 style="font-size:1.25rem">Upload test</h1>
                <p>Pick any file and submit. The reply says what reached PHP.</p>
                <form method="post" enctype="multipart/form-data">
                    <input type="file" name="document" required>
                    <button type="submit">Send</button>
                </form>
            </body>
            HTML)->header('Content-Type', 'text/html');
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
