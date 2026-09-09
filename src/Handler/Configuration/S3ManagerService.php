<?php

namespace App\Handler\Configuration;

use App\Interface\Configuration\S3ManagerInterface;
use App\Interface\UseCase\Security\LogInterface;
use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class S3ManagerService implements S3ManagerInterface
{
    private $s3Client;
    private $bucket;

    public function __construct(
        private readonly ParameterBagInterface $parameters,
        private readonly LogInterface $log
    ) {
        $config = [
            'version' => 'latest',
            'region'  => $parameters->get("aws_region"),
            'credentials' => [
                'key'    => $parameters->get("aws_access_key"),
                'secret' => $parameters->get("aws_secret_access_key"),
            ],
        ];

        $endpoint = $parameters->get("aws_endpoint");
        if (!empty($endpoint)) {

            $config['endpoint'] = $endpoint;
            $config['use_path_style_endpoint'] = true;
        }

        $this->s3Client = new S3Client($config);

        $this->bucket = $parameters->get("aws_bucket_name");
    }

    public function create(UploadedFile $file, string $path): ?string
    {
        $return = null;
        try {
            $key = rtrim($path, '/') . "/" . ltrim($file->getClientOriginalName(), "/");

            $this->s3Client->putObject([
                'Bucket' => $this->bucket,
                'Key'    => $key,
                'SourceFile' => $file->getPathname()
            ]);

            $return = rtrim($this->parameters->get("cdn_base_url"), '/') . '/' . $key;
        } catch (AwsException $e) {
            $this->log->handler($e);
        } catch (\Throwable $th) {
            $this->log->handler($th);
        }

        return $return;
    }

    public function delete(string $file): void
    {
        try {

            $this->s3Client->deleteObject([
                'Bucket' => $this->bucket,
                'Key'    => $this->keyToDelete($file),
            ]);
        } catch (AwsException $e) {
            $this->log->handler($e);
        } catch (\Throwable $th) {
            $this->log->handler($th);
        }
    }

    private function keyToDelete(string $file)
    {
        $key = str_replace(
            $this->parameters->get("cdn_base_url"),
            "",
            $file
        );

        return trim($key, '/');
    }
}
