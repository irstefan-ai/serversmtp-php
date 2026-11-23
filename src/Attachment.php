<?php

declare(strict_types=1);

namespace ServerSMTP;

/** Builds attachment arrays for Emails::send(). Up to 10 files, 10 MB in total. */
final class Attachment
{
    /**
     * @return array{filename: string, content: string, contentType?: string}
     */
    public static function fromPath(string $path, ?string $filename = null, ?string $contentType = null): array
    {
        $bytes = @file_get_contents($path);
        if ($bytes === false) {
            throw new \InvalidArgumentException('Cannot read attachment file: ' . $path);
        }
        return self::fromString($bytes, $filename ?? basename($path), $contentType);
    }

    /**
     * @param string $bytes raw file content (not base64)
     * @return array{filename: string, content: string, contentType?: string}
     */
    public static function fromString(string $bytes, string $filename, ?string $contentType = null): array
    {
        $attachment = ['filename' => $filename, 'content' => base64_encode($bytes)];
        if ($contentType !== null) {
            $attachment['contentType'] = $contentType;
        }
        return $attachment;
    }
}
