<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\DocumentStorage;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves client profile photos, government ID scans and passport scans.
 *
 * These used to sit on the public disk, where the web server would hand a
 * passport or ID scan to anyone who knew the URL. They now live on the private
 * document disk and leave only through here, and only for someone entitled to
 * see them: the client themselves, an administrator, or staff who already work
 * the client directory.
 */
class UserDocumentController extends Controller
{
    /**
     * Stream the client's profile photo.
     */
    public function profilePhoto(User $user): StreamedResponse
    {
        return $this->serve($user, $user->profile_photo);
    }

    /**
     * Stream the client's government ID scan.
     */
    public function governmentId(User $user): StreamedResponse
    {
        return $this->serve($user, $user->government_id_photo);
    }

    /**
     * Stream the client's passport scan.
     */
    public function passport(User $user): StreamedResponse
    {
        return $this->serve($user, $user->passport_photo);
    }

    /**
     * Authorise, then stream the file inline so it can be used in an <img> tag.
     */
    private function serve(User $user, ?string $path): StreamedResponse
    {
        $this->authorizeViewing($user);

        abort_if(blank($path), 404);

        $disk = DocumentStorage::disk();

        abort_unless($disk->exists($path), 404);

        return $disk->response($path);
    }

    /**
     * Who may look at a client's identity documents.
     */
    private function authorizeViewing(User $user): void
    {
        $viewer = Auth::user();

        $allowed = $viewer !== null
            && ($viewer->id === $user->id
                || $viewer->isAdmin()
                || $viewer->canAccessPage('users'));

        abort_unless($allowed, 403);
    }
}
