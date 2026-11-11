<?php

namespace App\Services\VirusScan;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * สแกนด้วยโปรแกรมภายนอกผ่าน command line (ไม่ผ่าน shell จึงไม่มีปัญหา injection จากชื่อไฟล์)
 */
abstract class ProcessScanner implements VirusScanner
{
    /** @return list<string> */
    abstract protected function command(string $absolutePath): array;

    /** แปลง exit code เป็นผลสแกน */
    abstract protected function interpret(int $exitCode): ScanResult;

    public function scan(string $absolutePath): ScanResult
    {
        if (! is_file($absolutePath)) {
            return ScanResult::Failed;
        }

        try {
            $process = new Process($this->command($absolutePath));
            $process->setTimeout((float) config('emcc.virus_scanner.timeout', 120));
            $process->run();

            $result = $this->interpret((int) $process->getExitCode());

            if ($result !== ScanResult::Clean) {
                Log::warning('ผลสแกนไวรัส: '.$result->value, [
                    'scanner' => static::class,
                    'file' => basename($absolutePath),
                    'exit' => $process->getExitCode(),
                    'output' => mb_substr($process->getOutput().$process->getErrorOutput(), 0, 1000),
                ]);
            }

            return $result;
        } catch (\Throwable $e) {
            Log::error('สแกนไวรัสไม่สำเร็จ: '.$e->getMessage());

            return ScanResult::Failed;
        }
    }
}
