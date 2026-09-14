<?php

namespace Tests\Unit;

use App\Support\RemoteAuthorPayload;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RemoteAuthorPayloadTest extends TestCase
{
    #[Test]
    public function it_builds_author_payload_with_experience_and_profile_photo(): void
    {
        $payload = (new RemoteAuthorPayload)->fromRequest(
            [
                'name' => 'Elena Marsh',
                'credentials' => 'DNP',
                'bio' => 'Educator',
                'years_of_experience' => 12,
            ],
            456789,
            profilePhoto: UploadedFile::fake()->image('profile.jpg'),
        );

        $this->assertSame(456789, $payload['id']);
        $this->assertSame('Elena Marsh', $payload['name']);
        $this->assertSame(12, $payload['years_of_experience']);
        $this->assertArrayHasKey('profile_photo_base64', $payload);
        $this->assertSame('image/jpeg', $payload['profile_photo_mime']);
    }
}
