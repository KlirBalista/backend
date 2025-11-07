<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;

class SupabaseStorageAdapter implements FilesystemAdapter
{
    protected Client $client;
    protected string $bucket;
    protected string $url;
    protected string $key;

    public function __construct(array $config)
    {
        $this->url = $config['url'];
        $this->key = $config['key'];
        $this->bucket = $config['bucket'];

        $this->client = new Client([
            'base_uri' => "{$this->url}/storage/v1/",
            'headers' => [
                'Authorization' => "Bearer {$this->key}",
                'apikey' => $this->key,
            ],
        ]);
    }

    public function fileExists(string $path): bool
    {
        try {
            $this->client->head("object/{$this->bucket}/{$path}");
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function directoryExists(string $path): bool
    {
        return true; // Supabase doesn't have real directories
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $this->client->post("object/{$this->bucket}/{$path}", [
            'body' => $contents,
            'headers' => [
                'Content-Type' => $config->get('mimetype', 'application/octet-stream'),
            ],
        ]);
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $this->write($path, stream_get_contents($contents), $config);
    }

    public function read(string $path): string
    {
        $response = $this->client->get("object/{$this->bucket}/{$path}");
        return $response->getBody()->getContents();
    }

    public function readStream(string $path)
    {
        $response = $this->client->get("object/{$this->bucket}/{$path}", ['stream' => true]);
        return $response->getBody()->detach();
    }

    public function delete(string $path): void
    {
        $this->client->delete("object/{$this->bucket}/{$path}");
    }

    public function deleteDirectory(string $path): void
    {
        // Supabase requires listing and deleting files individually
    }

    public function createDirectory(string $path, Config $config): void
    {
        // Supabase doesn't require directory creation
    }

    public function setVisibility(string $path, string $visibility): void
    {
        // Handled at bucket level in Supabase
    }

    public function visibility(string $path): FileAttributes
    {
        return new FileAttributes($path, null, 'public');
    }

    public function mimeType(string $path): FileAttributes
    {
        $response = $this->client->head("object/{$this->bucket}/{$path}");
        $mimeType = $response->getHeader('Content-Type')[0] ?? 'application/octet-stream';
        return new FileAttributes($path, null, null, null, $mimeType);
    }

    public function lastModified(string $path): FileAttributes
    {
        $response = $this->client->head("object/{$this->bucket}/{$path}");
        $lastModified = strtotime($response->getHeader('Last-Modified')[0] ?? 'now');
        return new FileAttributes($path, null, null, $lastModified);
    }

    public function fileSize(string $path): FileAttributes
    {
        $response = $this->client->head("object/{$this->bucket}/{$path}");
        $size = (int) ($response->getHeader('Content-Length')[0] ?? 0);
        return new FileAttributes($path, $size);
    }

    public function listContents(string $path, bool $deep): iterable
    {
        return [];
    }

    public function move(string $source, string $destination, Config $config): void
    {
        $contents = $this->read($source);
        $this->write($destination, $contents, $config);
        $this->delete($source);
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        $contents = $this->read($source);
        $this->write($destination, $contents, $config);
    }

    public function publicUrl(string $path): string
    {
        return "{$this->url}/storage/v1/object/public/{$this->bucket}/{$path}";
    }
}
