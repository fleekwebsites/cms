<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

class RemoteAuthorPayload
{
    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function fromRequest(
        array $validated,
        int $id,
        ?RemoteRecord $existing = null,
        ?UploadedFile $profilePhoto = null,
    ): array {
        $payload = [
            'id' => $id,
            'name' => is_string($validated['name'] ?? null) ? trim($validated['name']) : '',
            'credentials' => is_string($validated['credentials'] ?? null) && trim($validated['credentials']) !== ''
                ? trim($validated['credentials'])
                : null,
            'bio' => is_string($validated['bio'] ?? null) && trim($validated['bio']) !== ''
                ? trim($validated['bio'])
                : null,
        ];

        if (array_key_exists('years_of_experience', $validated) && $validated['years_of_experience'] !== null) {
            $payload['years_of_experience'] = (int) $validated['years_of_experience'];
        }

        if ($profilePhoto instanceof UploadedFile) {
            $encoded = $this->encodeUploadedImage($profilePhoto);

            if ($encoded !== null) {
                $payload = [...$payload, ...$encoded];
            }
        } elseif ($existing?->string('profile_photo_url') !== null) {
            $payload['profile_photo_url'] = $existing->string('profile_photo_url');
        }

        return $payload;
    }

    /**
     * @return array{profile_photo_base64: string, profile_photo_mime: string, profile_photo_filename: string}|null
     */
    private function encodeUploadedImage(UploadedFile $file): ?array
    {
        $path = $file->getRealPath();

        if (! is_string($path) || $path === '') {
            return null;
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return null;
        }

        $mime = $file->getMimeType() ?? 'application/octet-stream';

        return [
            'profile_photo_base64' => base64_encode($contents),
            'profile_photo_mime' => $mime,
            'profile_photo_filename' => $file->getClientOriginalName(),
        ];
    }
}
