<?php

namespace App\Libraries\VirusScan;

use Config\Emcc;
use Symfony\Component\Process\Process;
use Throwable;

abstract class ProcessScanner implements VirusScanner
{

    abstract protected function command(string $absolutePath): array;

    abstract protected function interpret(int $exitCode): ScanResult;

    public function scan(string $absolutePath): ScanResult
    {
        if (! is_file($absolutePath)) {
            return ScanResult::Failed;
        }

        try {
            $process = new Process($this->command($absolutePath));
            $process->setTimeout((float) config(Emcc::class)->virusScanTimeout);
            $process->run();

            $result = $this->interpret((int) $process->getExitCode());

            if ($result !== ScanResult::Clean) {
                log_message('warning', 'ผลสแกนไวรัส: {result} ({scanner}) {file} exit={exit} {output}', [
                    'result'  => $result->value,
                    'scanner' => static::class,
                    'file'    => basename($absolutePath),
                    'exit'    => (string) $process->getExitCode(),
                    'output'  => mb_substr($process->getOutput() . $process->getErrorOutput(), 0, 1000),
                ]);
            }

            return $result;
        } catch (Throwable $e) {
            log_message('error', 'สแกนไวรัสไม่สำเร็จ: ' . $e->getMessage());

            return ScanResult::Failed;
        }
    }
}
