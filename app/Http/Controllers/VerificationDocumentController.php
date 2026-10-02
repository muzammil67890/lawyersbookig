<?php

namespace App\Http\Controllers;

use App\Models\LawyerVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves CNIC/face verification images. These live on the private
 * 'local' disk (see LawyerFileUploadService) and are never reachable
 * by a public URL -- the only way to view one is through one of these
 * two authorized routes, both gated by LawyerVerificationPolicy::view():
 * a lawyer may view their own documents, an admin may view any
 * lawyer's, and nobody else can reach either route (a customer or a
 * different lawyer gets a 403, not a redirect that would hint the
 * record exists).
 */
class VerificationDocumentController extends Controller
{
    /** A lawyer viewing their own CNIC/face document. */
    public function own(Request $request, string $type): StreamedResponse
    {
        $verification = $request->user()->lawyerVerification;

        abort_if(! $verification, 404);

        return $this->respond($request, $verification, $type);
    }

    /** An admin viewing a specific lawyer's CNIC/face document. */
    public function forVerification(Request $request, LawyerVerification $lawyerVerification, string $type): StreamedResponse
    {
        return $this->respond($request, $lawyerVerification, $type);
    }

    protected function respond(Request $request, LawyerVerification $verification, string $type): StreamedResponse
    {
        $this->authorize('view', $verification);

        abort_unless(in_array($type, ['cnic', 'face'], true), 404);

        $path = $type === 'cnic' ? $verification->cnic_image_path : $verification->face_image_path;

        abort_if(! $path, 404);
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }
}
