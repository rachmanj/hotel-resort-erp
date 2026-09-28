<?php

namespace App\Services\Accounting\BankReconciliation\Statement;

use InvalidArgumentException;
use Smalot\PdfParser\Parser;
use Symfony\Component\Process\Process;

class BankStatementTextReader
{
    public function read(string $absolutePath): string
    {
        if (! is_readable($absolutePath)) {
            throw new InvalidArgumentException('Bank statement file is not readable.');
        }

        $pathForParser = $absolutePath;
        $temporaryPath = null;

        try {
            return $this->extractText($pathForParser);
        } catch (\Exception $exception) {
            if (! str_contains($exception->getMessage(), 'Secured pdf')) {
                throw $exception;
            }

            $temporaryPath = $this->decryptSecuredPdfToTemporary($absolutePath);
            $pathForParser = $temporaryPath;

            return $this->extractText($pathForParser);
        } finally {
            if ($temporaryPath !== null && is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }
    }

    private function extractText(string $absolutePath): string
    {
        $parser = new Parser;
        $document = $parser->parseFile($absolutePath);
        $pages = $document->getPages();
        $chunks = [];

        foreach ($pages as $page) {
            $chunks[] = trim((string) $page->getText());
        }

        return implode("\f", $chunks);
    }

    private function decryptSecuredPdfToTemporary(string $absolutePath): string
    {
        $directory = storage_path('app/bank-reconciliation/tmp');

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new InvalidArgumentException('Could not create temporary directory for bank statement decryption.');
        }

        $temporaryPath = $directory.'/'.uniqid('stmt_', true).'.pdf';
        $binary = (string) config('bank_reconciliation.statement_qpdf_binary', 'qpdf');

        if ($binary !== 'qpdf' && ! is_executable($binary)) {
            throw new InvalidArgumentException(
                'This PDF is password-protected and could not be decrypted for text extraction. '
                .'Install the qpdf binary on the server (or set BANK_RECON_QPDF_BINARY), or upload an unencrypted export.',
            );
        }

        $process = new Process([
            $binary,
            '--decrypt',
            '--password=',
            $absolutePath,
            $temporaryPath,
        ]);
        $process->setTimeout(120);
        $process->run();

        if (! $process->isSuccessful() || ! is_readable($temporaryPath)) {
            @unlink($temporaryPath);

            $detail = trim($process->getErrorOutput()) !== ''
                ? trim($process->getErrorOutput())
                : trim($process->getOutput());

            throw new InvalidArgumentException(
                'This PDF is password-protected and could not be decrypted for text extraction using qpdf. '
                .'Install the qpdf binary on the server (or set BANK_RECON_QPDF_BINARY), or upload an unencrypted export.'
                .($detail !== '' ? ' qpdf: '.$detail : ''),
            );
        }

        return $temporaryPath;
    }
}
