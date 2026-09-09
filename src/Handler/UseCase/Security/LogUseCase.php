<?php

namespace App\Handler\UseCase\Security;

use App\ArgumentHandler\LogsArgument;
use App\Entity\Configurations\Globals\Logs;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Configurations\Globals\LogsRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Throwable;

final class LogUseCase implements LogInterface
{
    public function __construct(
        private readonly LogsRepository $logsRepository,
        private readonly KernelInterface $kernel,
        private readonly CustomeEntityManagerInterface $customeEntityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handler(Throwable $throwable): ?Logs
    {
        $log = new Logs();
        $log->add(new LogsArgument($this->returnData($throwable)));

        try {
            $this->customeEntityManager->add($log, true);
        } catch (Throwable $persistFailure) {

            $this->logger->error(sprintf(
                'No se pudo persistir el log %s (id de referencia %s). Excepción original: %s',
                $persistFailure->getMessage(),
                $log->getShortReference(),
                $throwable->__toString()
            ), [
                'reference' => $log->getShortReference(),
                'persist_failure' => $persistFailure->__toString(),
            ]);
        }

        return $log;
    }

    private function returnData(Throwable $throwable): array
    {
        return [
            "message" => $throwable->getMessage(),
            "code" => $throwable->getCode(),
            "line" => $throwable->getLine(),
            "file" => $throwable->getFile(),
            "trace" => $throwable->getTraceAsString(),
            "complete" => $throwable->__toString()
        ];
    }

    public function deleteLogsFile(string $days): void
    {
        try {
            $logDir = $this->kernel->getLogDir();
            $logFiles = glob($logDir . '/*.log');
            $threshold = strtotime("-$days days");

            foreach ($logFiles as $file) {
                if (filemtime($file) < $threshold) {
                    unlink($file);
                }
            }
        } catch (Throwable $th) {
            $this->handler($th);
        }
    }
}
